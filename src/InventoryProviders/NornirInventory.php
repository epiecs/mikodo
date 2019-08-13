<?php

namespace Epiecs\Mikodo\InventoryProviders;

use Symfony\Component\Yaml\Yaml;

/**
*  NornirInventory class
*
*  Instantiates NornirInventory for use with mikodo.
*
*  @author Gregory Bers
*/

class NornirInventory extends BaseInventory implements InventoryInterface
{
	/**
	 * Initialize the inventory. Needs at least a hosts.yaml file to be able to
	 * work. groups.yaml and defaults.yaml is optional
	 *
	 * @param string $inventoryDirectory Folder path to the nornir inventory.
	 *
	 * $inventory = new \Epiecs\Mikodo\NornirInventory($inventoryDirectory);
	 *
     * @throws Exception when the directory is empty or does not exist
	 */

	public function __construct($inventoryDirectory)
	{
        if(substr($inventoryDirectory, -1) != DIRECTORY_SEPARATOR)
        {
            $inventoryDirectory .= DIRECTORY_SEPARATOR;
        }

        if(!file_exists($inventoryDirectory))
        {
            throw new \Exception("Inventory directory ({$inventoryDirectory}) does not exist.", 1);
        }

        if(!file_exists($inventoryDirectory . "hosts.yaml"))
        {
            throw new \Exception("No hosts file found in ({$inventoryDirectory}).", 1);
        }

        file_exists($inventoryDirectory . "defaults.yaml") ? $this->setDefaults(Yaml::parseFile($inventoryDirectory . "defaults.yaml")) : false;
        file_exists($inventoryDirectory . "groups.yaml") ? $this->setGroups(Yaml::parseFile($inventoryDirectory . "groups.yaml")) : false;
        file_exists($inventoryDirectory . "hosts.yaml") ? $this->setHosts(Yaml::parseFile($inventoryDirectory . "hosts.yaml")) : false;
	}

}
