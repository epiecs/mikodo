<?php

namespace Epiecs\Mikodo\InventoryProviders;

interface inventoryInterface
{
    /**
     * Sets the provided hosts. Host settings always have precedence over group and default settings. An example is provided below
     *
     * [
     *     'Hostname_1' => [
     *         'device_type'    => "junos"
     *         'port'           => 22
     *         'username'       => "username"
     *         'password'       => "password"
     *         'hostname'       => "hostname or ip"
     *         'groups' => [
     *             "core_switches",
     *             "lab_switches"
     *         ]
     *     ]
     *     'Hostname_2' => [
     *         'device_type'    => "junos"
     *         'port'           => 22
     *         'username'       => "username"
     *         'password'       => "password"
     *         'hostname'       => "hostname or ip"
     *         'groups' => [
     *             "core_switches"
     *         ]
     *     ]
     * ]
     *
     * @param array $hosts array containing hosts
     */

    public function setHosts(array $hosts) : void;

    /**
     * Sets the provided groups. Group settings always have precedence over default settings. An example is provided below
     *
     * [
     *     'lab_switches' => [
     *         'device_type' => "junos",
     *         'password'    => "password"
     *     ],
     *     'core_switches' => [
     *         'device_type' => "junos"
     *     ]
     * ]
     *
     * @param array $groups array containing groups
     */

    public function setGroups(array $groups) : void;


    /**
    * Sets the defaults. Default settings always have the lowest precedence. An example is provided below
    *
    *
    * [
    *     'port'     => 22,
    *     'username' => "defaultusername",
    *     'password' => "defaultpassword"
    * ]
    *
    * @param array $defaults array containing default settings
    */

    public function setDefaults(array $defaults) : void;

    /**
     * Returns all hosts that are currently configured in the inventory
     *
     * @return array hosts inventory array
     */

    public function getAllHosts() : array;

    /**
     * Gets all hosts from the inventory based on the hostnames that are supplied in the
     * $hosts parameter.
     *
     * @param  array $hosts array containing hostnames that need to be returned
     * @return array        the filtered hostnames from the inventory
     */

    public function getHosts(array $hosts) : array;

    /**
     * Gets all hosts from the inventory based on the groupnames that are supplied in the
     * $groups parameter.
     *
     * @param  array $groups array containing groupnames in wich hosts reside that need to
     *                       be returned
     * @return array         the filtered hostnames from the inventory
     */

    public function getGroups(array $groups) : array;

    /**
     * Returns an array containing all the defaults that have been set
     *
     * @return array array containing defaults
     */

    public function getDefaults() : array;

    /**
     * Returns the full inventory (defaults, groups, hosts)
     * 
     * @return array the complete inventory
     */
    public function inventory() : array;
}
