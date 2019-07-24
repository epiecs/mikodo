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
     * $mikodo = new \Epiecs\Mikodo\BaseInventory();
     */

    public function __construct(array $hosts = array(), array $groups = array(), array $defaults = array())
    {
        $this->setHosts($hosts);
        $this->setGroups($groups);
        $this->setDefaults($defaults);
    }

    public function setHosts(array $hosts) : void
    {
        $this->hosts = $hosts;
        $this->mergeConfigs();
    }

    public function setGroups(array $groups) : void
    {
        $this->groups = $groups;
        $this->mergeConfigs();
    }

    public function setDefaults(array $defaults) : void
    {
        $this->defaults = $defaults;
        $this->mergeConfigs();
    }

    public function getHosts(array $hosts) : array
    {
        $nonExistingHosts = array_diff(array_keys($hosts), $this->hosts);
        if(count($nonExistingHosts) > 0)
        {
            throw new \Exception("Host(s) (" . implode(", ", $nonExistingHosts) . ") do not exist.", 1);
        }

        $filteredInventory = array_filter($this->hosts, function ($host, $hostname) use ($hosts) {
            return in_array($hostname, $hosts) ? true : false;
        }, ARRAY_FILTER_USE_BOTH);

        return $filteredInventory;
    }

    public function getGroups(array $groups) : array
    {
        $groupColumn = array_column($this->hosts, 'groups');

        $assignedHostGroups = array();

        foreach($groupColumn as $hostGroups)
        {
            $assignedHostGroups = array_merge($assignedHostGroups, $hostGroups);
        }


        $nonExistingGroups = array_diff($groups, $assignedHostGroups);
        if(count($nonExistingGroups) > 0)
        {
            throw new \Exception("No host(s) assigned to group(s) (" . implode(", ", $nonExistingGroups) . ").", 1);
        }

        $filteredInventory = array_filter($this->hosts, function ($host) use ($groups) {
            return count(array_intersect($host['groups'], $groups)) > 0 ? true : false;
        });

        return $filteredInventory;
    }

    public function getDefaults() : array
    {
        return $this->defaults;
    }

    public function getAllHosts() : array
    {
        return $this->hosts;
    }

    public function inventory() : array
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
