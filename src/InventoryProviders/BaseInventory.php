<?php

namespace Epiecs\Mikodo\InventoryProviders;

/**
*  BaseInventory class, can be extended by other inventory providers
*
*  Instantiates BaseInventory for use with mikodo.
*
*  @author Gregory Bers
*/

class BaseInventory implements InventoryInterface
{
    /**
     * Contains all the hosts
     * @var array
     */

    protected $hosts = array();

    /**
     * Contains all the groups
     *
     * @var array
     */

    protected $groups = array();

    /**
     * Contains all the defaults
     *
     * @var array
     */

    protected $defaults = array();

    /**
     * Initialize the inventory.
     *
     * @param array $hosts      The hosts for the inventory
     * @param array $groups     The groups for the inventory
     * @param array $defaults   The default settings
     */

    public function __construct(array $hosts = array(), array $groups = array(), array $defaults = array())
    {
        $this->setHosts($hosts);
        $this->setGroups($groups);
        $this->setDefaults($defaults);
    }

    /**
     * Sets an array as host(s) as the new hosts in the inventory and applies
     * group and default settings where hosts to not have the corresponding setting
     * applied. Hosts settings have preference over group settings and group
     * settings have preference over default settings.
     *
     * @param array $hosts The hosts for the inventory
     *
     * @return void
     */

    public function setHosts(array $hosts) : void
    {
        $this->hosts = $hosts;
        $this->mergeConfigs();
    }

    /**
     * Sets an array of group(s) as the groups in the inventory and applies
     * the group config to hosts where the hosts are a member of the group and
     * do not have the global settings defined in the group
     *
     * @param array $groups Array containing groups
     *
     * @return void
     */

    public function setGroups(array $groups) : void
    {
        $this->groups = $groups;
        $this->mergeConfigs();
    }

    /**
     * Sets the default settings for all hosts in the inventory. Only applies
     * these settings if there is no corresponding host or group setting.
     *
     * @param array $defaults Array containing defaults
     *
     * @return void
     */

    public function setDefaults(array $defaults) : void
    {
        $this->defaults = $defaults;
        $this->mergeConfigs();
    }

    /**
     * Fetches the hosts from the inventory.
     *
     * @param  array $hosts Array containing the hosts that need to be fetched
     *
     * @throws Exception When nonexisting hosts are provided
     *
     * @return array        Inventory containing all the requested hosts
     */

    public function getHosts(array $hosts) : array
    {
        $nonExistingHosts = array_diff($hosts, array_keys($this->hosts));
        if(count($nonExistingHosts) > 0)
        {
            throw new \Exception("Host(s) (" . implode(", ", $nonExistingHosts) . ") do not exist.", 1);
        }

        $filteredInventory = array_filter($this->hosts, function ($host, $hostname) use ($hosts) {
            return in_array($hostname, $hosts) ? true : false;
        }, ARRAY_FILTER_USE_BOTH);

        return $filteredInventory;
    }

    /**
     * Fetches the hosts from the inventory that are a member of the provided groups.
     *
     * @param  array $groups Array containing the groups that need to be fetched
     *
     * @throws Exception When nonexisting groups are provided
     *
     * @return array        Inventory containing all the requested hosts
     */

    public function getGroups(array $groups, array $filterGroups = array()) : array
    {
        $groupColumn = array_column($this->hosts, 'groups');


        $assignedHostGroups = array();

        foreach($groupColumn as $hostGroups)
        {
            $assignedHostGroups = array_unique(array_merge($assignedHostGroups, $hostGroups));
        }

        $nonExistingGroups       = array_diff($groups, $assignedHostGroups);
        $nonExistingFilterGroups = array_diff($filterGroups, $assignedHostGroups);

        if(count($nonExistingGroups) > 0 || count($nonExistingFilterGroups) > 0)
        {
            throw new \Exception("No host(s) assigned to group(s) (" . implode(", ", array_merge($nonExistingGroups, $nonExistingFilterGroups)) . ").", 1);
        }

        /**
         * First build the entire inventory with all the groups that are requested
         */

        $inventory = array_filter($this->hosts, function ($host) use ($groups) {
            return count(array_intersect($host['groups'], $groups)) > 0 ? true : false;
        });

        /**
         * If filterGroups are defined we iterate the built inventory and filter
         * where needed
         */

        if(count($filterGroups) > 0)
        {
            $inventory = array_filter($inventory, function ($host) use ($filterGroups) {
                return empty(array_diff($filterGroups, $host['groups']));
            });
        }

        return $inventory;
    }

    /**
     * Fetches the full inventory.
     *
     * @return array        Full inventory with a sub key for each config element (hosts/groups/defaults)
     */

    public function getInventory() : array
    {
        $inventory = [
            'defaults' => $this->defaults,
            'groups'   => $this->groups,
            'hosts'    => $this->hosts
        ];

        return $inventory;
    }

    /**
     * Private functions
     */

    /**
     * Merges the configs in the class in the correct order hosts > groups > defaults
     */

    private function mergeConfigs() : void
    {
        // Apply the defaults and group config in the correct order :: defaults > group > host

        foreach($this->hosts as $hostname => $details)
        {
            $this->hosts[$hostname] = array_merge($this->defaults, $this->hosts[$hostname]);

            foreach($details['groups'] as $group)
            {
                // Add groups only found in the hosts file to the group list

                if(!isset($this->groups[$group]))
                {
                    $this->groups[$group] = array();
                }

                if(isset($this->groups[$group]) && is_array($this->groups[$group]))
                {
                    $this->hosts[$hostname] = array_merge($this->groups[$group], $this->hosts[$hostname]);
                }
            }
        }
    }
}
