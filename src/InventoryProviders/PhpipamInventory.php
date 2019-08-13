<?php

namespace Epiecs\Mikodo\InventoryProviders;

use GuzzleHttp\Client as HttpClient;

/**
*  PhpipamInventory class
*
*  Instantiates PhpipamInventory for use with mikodo.
*
*  @author Gregory Bers
*/

class PhpipamInventory extends BaseInventory implements InventoryInterface
{
    /**
     * String containing the authentication token
     * @var string
     */

    var $authToken = '';

    /**
     * Contains the base fields of a device in phpipam
     * @var array
     */

    var $baseFields = array(
        'id',
        'hostname',
        'ip',
        'type',
        'description',
        'sections',
        'editDate',
        'snmp_community',
        'snmp_version',
        'snmp_port',
        'snmp_timeout',
        'snmp_queries',
        'rack',
        'rack_start',
        'rack_size',
        'location',
        'snmp_v3_sec_level',
        'snmp_v3_auth_protocol',
        'snmp_v3_ctx_engine_id',
        'snmp_v3_ctx_name',
        'snmp_v3_priv_pass',
        'snmp_v3_priv_protocol',
        'snmp_v3_auth_pass'
    );

    /**
     * These fields will be applied to the host instead of being set as a group.
     * @var array
     */

    var $hostFields = array(
        'username',
        'password',
        'port',
        'device_type',
    );

    /**
     * Initialize the connection to PhpIpam. On construction it loads all devices into the
     * inventory.
     *
     * A group will be applied for:
     *
     * - each device type that is know in phpipam.
     * - the rack(s) known for that devices
     * - the section(s) known for that device
     * - the location(s) known for that device
     * - if there are custom fields set for a device.
     *
     * If a custom field is supplied for username, password, port and/or device_type then this will not be
     * applied as a group but directly to the object.
     *
     * $inventory = new \Epiecs\Mikodo\PhpipamInventory($inventoryDirectory);
     *
     * The following authentication methods are supported:
     * - User token (unencrypted, username and password is required)
     * - SSL with user token (encrypted, username and password is required), provide a https api link
     * - SSL with app code token (encrypted, appCode is required), provide a https link
     *
     * @param string  $apiUrl   The url of your api. You dont need to add api to the end. Just the baseurl. eg. https://phpipam.local/api
     * @param string  $appId    The name of your app id that you set.
     *
     * @param string  $username Your phpipam username, required if you use a user token
     * @param string  $password Your phpipam password, required if you use a user token
     *
     * @param string  $appCode  Your phpipam app code token, required if you use an app code
     */

	public function __construct(string $apiUrl, string $appId, string $username = "", string $password = "", string $appCode = "")
	{
        $apiUrlRegex = "/(?<apiurl>(?:https|http):\/\/[A-Za-z0-9\.]+\/api).*/m";

        preg_match_all($apiUrlRegex, $apiUrl, $matches, PREG_SET_ORDER);

        if(count($matches) == 0){ throw new \Exception("Api url not in the correct format. Expecting http://<hostname>/api or https://<hostname>/api");}

        $phpIpamUrl = "{$matches[0]['apiurl']}/{$appId}/";

        if($username == "" && $password == "" && $appCode == ""){ throw new \Exception("Either provide username/password or the appcode"); }

        if($appCode != "")
        {
            $apiToken = $appCode;

            if(strpos($phpIpamUrl, "https") === false){ throw new \Exception("You need a https link when using an app code"); }
        }
        else
        {
            // Fetch temp api token
            $phpIpamApiAuth = new HttpClient([
                'base_uri'      => $phpIpamUrl,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode("{$username}:{$password}"),
                ]
            ]);

            $response = $phpIpamApiAuth->request('POST', "user");
            $apiToken = json_decode((string) $response->getBody())->data->token;
        }

		// Prep http client with token
		$phpIpamApi = new HttpClient([
            'base_uri' => $phpIpamUrl,
			'headers'  => [
                'Content-type'  => 'application/json',
				'phpipam-token' => $apiToken,
			]
		]);

        // Fetch device types
		$response        = $phpIpamApi->request("GET", "tools/device_types", ['verify' => false]);
		$ipamDeviceTypes = json_decode((string) $response->getBody(), true)['data'];

        $deviceTypes = array();

        foreach($ipamDeviceTypes as $ipamDeviceType)
        {
            $deviceTypes[$ipamDeviceType['tid']] = $ipamDeviceType['tname'];
        }

        // Fetch sections
        $response        = $phpIpamApi->request("GET", "sections", ['verify' => false]);
        $ipamSections    = json_decode((string) $response->getBody(), true)['data'];

        $sections = array();

        foreach($ipamSections as $ipamSection)
        {
            $sections[$ipamSection['id']] = $ipamSection['name'];
        }

        // Fetch locations
        $response        = $phpIpamApi->request("GET", "tools/locations", ['verify' => false]);
        $ipamLocations   = json_decode((string) $response->getBody(), true)['data'];

        $locations = array();

        foreach($ipamLocations as $ipamLocation)
        {
            $locations[$ipamLocation['id']] = $ipamLocation['name'];
        }

        // Fetch locations
        $response        = $phpIpamApi->request("GET", "tools/racks", ['verify' => false]);
        $ipamRacks   = json_decode((string) $response->getBody(), true)['data'];

        $racks = array();

        foreach($ipamRacks as $ipamRack)
        {
            $racks[$ipamRack['id']] = $ipamRack['name'];
        }

        // Fetch devices
        $response    = $phpIpamApi->request("GET", "devices", ['verify' => false]);
        $ipamDevices = json_decode((string) $response->getBody(), true)['data'];

        if(count($ipamDevices) == 0){ throw new \Exception("You have no devices in your PhpIpam");}

        // Get custom fields

        $customFields = array_diff_key($ipamDevices['0'], array_flip($this->baseFields));

        foreach($customFields as $key => $value)
        {
            $customFields[$key] = str_replace("custom_", "", $key);
        }

        $customFields = array_flip($customFields);

        foreach($ipamDevices as $ipamDevice)
        {
            $hosts[$ipamDevice['hostname']]['hostname'] = $ipamDevice['ip'];

            !in_array($ipamDevice['type'], [0, null]) ? $hosts[$ipamDevice['hostname']]['groups'][]     = $deviceTypes[$ipamDevice['type']] : false;
            !in_array($ipamDevice['sections'], [0, null]) ? $hosts[$ipamDevice['hostname']]['groups'][] = $sections[$ipamDevice['sections']] : false;
            !in_array($ipamDevice['location'], [0, null]) ? $hosts[$ipamDevice['hostname']]['groups'][] = $locations[$ipamDevice['location']] : false;
            !in_array($ipamDevice['rack'], [0, null]) ? $hosts[$ipamDevice['hostname']]['groups'][]     = $racks[$ipamDevice['rack']] : false;

            foreach($customFields as $fieldName => $ipamFieldName)
            {
                if(in_array($fieldName, $this->hostFields))
                {
                    $ipamDevice[$ipamFieldName] != null ? $hosts[$ipamDevice['hostname']][$fieldName] = $ipamDevice[$ipamFieldName] : false;
                }
                else
                {
                    !in_array($ipamDevice[$ipamFieldName], [0, null]) ? $hosts[$ipamDevice['hostname']]['groups'][] = $ipamDevice[$ipamFieldName] : false;
                }
            }
        }

        $this->setHosts($hosts);
    }
}
