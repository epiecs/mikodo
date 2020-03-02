Mikodo
=======

Concurrent library on top of phpmiko. Speeds up the process of sending commands. There are included libraries for inventory management Libraries. Supported libraries include and are not limited to: Nornir yaml files, PhpIpam,...

#### Requires:

- Php >= 7.1
- ext-sockets
- ext-pcntl

- A UNIX or BSD OS. Native windows is not supported at the moment but you can use WSL.

#### Installation:

```bash
composer require epiecs/mikodo
```

#### Supported inventories:

###### Implemented

- Basic
- Nornir
- PhpIpam

###### Planned

- Netbox
- ...

## Basic examples:

#### Initializing Mikodo

```php
require_once __DIR__ . '/vendor/autoload.php';

$mikodo = new \Epiecs\Mikodo\Mikodo();
```

##### Buffer size

The size in bytes that is available for each worker for communicating to the parent process. Set this to a higher number if you require a lot of text to be sent (eg.) complete config files. You can set this lower to reduce memory usage but you might not receive all output.

```php
$mikodo->bufferSize(65535); // Defaults to 65535
```

#### Running commands

Mikodo uses the same 3 methods like Phpmiko to send commands to devices: cli, operation and configure.

When sending commands you can either provide the method with a string or an array consisting of commands. Either way is fine. When providing an array the commands are run in order.

For the difference between the types of commands you can refer to the  [Phpmiko documentation](https://github.com/epiecs/phpmiko)

##### Preparing the inventory

Mikodo makes use of an inventory to perform its magic. The most basic way is to provide the __inventory()__ method with an array consisting of hosts:

```php
$mikodo->inventory([
    'Hostname_1' => [
        'device_type'    => "junos",
        'username'       => "username",
        'password'       => "password",
        'hostname'       => "hostname or ip"
    ],
    'Hostname_2' => [
        'device_type'    => "junos",
        'username'       => "username",
        'password'       => "password",
        'hostname'       => "hostname or ip"
    ]
]);
```

Each host should at least include the most basic required information for Phpmiko: __device_type__, __username__, __password__ and __hostname__.

##### Sending command(s)

When the inventory has been prepared you can start sending commands.

```php
$results = $mikodo->cli([
    'date',
    'ping -c 2 8.8.8.8'
]);
```

If all went well you will see output like this. Offcourse in real life you will have (pretty?) colors.

```plaintext
Starting mikodo,  2 queued jobs
[cli]
        date
        ping -c 2 8.8.8.8
=========================================================================> 100%
Retrieving output from Hostname_2
```

###### Retrieving results

Mikodo will return the values in the form of an array where you can expect a key for each host and within the key of that host another key per command that has been run.

```php
[
    'Hostname_1' => [
        'date' => "
            Wed Jul 24 15:48:36 CEST 2019
            "
        'ping -c 2 8.8.8' => "
            PING 8.8.8.8 (8.8.8.8): 56 data bytes
            64 bytes from 8.8.8.8: icmp_seq=0 ttl=64 time=10.706 ms
            64 bytes from 8.8.8.8: icmp_seq=1 ttl=64 time=11.214 ms
            --- 8.8.8.8 ping statistics ---
            2 packets transmitted, 2 packets received, 0% packet loss
            round-trip min/avg/max/stddev = 10.706/10.960/11.214/0.254 ms
            "
    ]
    'Hostname_2' => [
        'date' => "
            Wed Jul 24 15:48:36 CEST 2019
            "
        'ping -c 2 8.8.8.8' => "
            PING 8.8.8.8 (8.8.8.8): 56 data bytes
            64 bytes from 8.8.8.8: icmp_seq=0 ttl=64 time=54.188 ms
            64 bytes from 8.8.8.8: icmp_seq=1 ttl=64 time=11.252 ms
            --- 8.8.8.8 ping statistics ---
            2 packets transmitted, 2 packets received, 0% packet loss
            round-trip min/avg/max/stddev = 11.252/32.720/54.188/21.468 ms
            "
    ]
]
```

###### Printing results

To output the results to the terminal you just provide the returned results to the Mikodo->print() function.

```php
$mikodo->print($results);
```

Also, in real life you should get pretty colors.

```plaintext
Hostname_1
date

Wed Jul 24 15:33:48 CEST 2019

ping -c 2 8.8.8.8

PING 8.8.8.8 (8.8.8.8): 56 data bytes
64 bytes from 8.8.8.8 icmp_seq=0 ttl=64 time=10.798 ms
64 bytes from 8.8.8.8 icmp_seq=1 ttl=64 time=11.348 ms
--- 8.8.8.8 ping statistics ---
2 packets transmitted, 2 packets received, 0% packet loss
round-trip min/avg/max/stddev = 10.798/11.073/11.348/0.275 ms

Hostname_2
date

Wed Jul 24 15:33:48 CEST 2019

ping -c 2 8.8.8.8

PING 8.8.8.8 (8.8.8.8): 56 data bytes
64 bytes from 8.8.8.8 icmp_seq=0 ttl=64 time=11.876 ms
64 bytes from 8.8.8.8 icmp_seq=1 ttl=64 time=11.193 ms
--- 8.8.8.8 ping statistics ---
2 packets transmitted, 2 packets received, 0% packet loss
round-trip min/avg/max/stddev = 11.193/11.534/11.876/0.341 ms

```

## Inventories

To ease your life Mikodo includes several inventory providers.

Inventories allow you to easily select specific hosts and/or groups from your inventory. Inventories also allow you to set default values on a global and/or group level.

You can even mix and match different inventories. One example can be that you load all hosts from PhpIpam and supply a default username/password via a simpleinventory.


One caveat that you have to take into account is the order in which the settings are applied.

The order of preference is hosts > groups > defaults. Imagine a situation where you have an inventory and have set a default username and password in the default settings. Within your inventory you also have one host where there is a password set within the host config.

The password defined in the host will take precedence over the password provided in the default settings.

### Writing your own inventory providers

All inventory providers can extend the base inventory class and should implement the DeviceInterface interface.

It is recommended to extend the baseInventory class and use the sethosts/setgroups/setdefaults commands. These commands will make sure that the config is merged correctly as expected.

The InventoryInterface provides you with a nice structure as how a array containing hosts, groups and defaults should function.

### Base inventory

The Base inventory can be initialized in three ways

- via the constructor
- via setters
- a combination of both

__example__

```php
require_once __DIR__ . '/vendor/autoload.php';

use Epiecs\Mikodo\Mikodo;
use Epiecs\Mikodo\InventoryProviders\BaseInventory;

$baseInventory = new BaseInventory();

// Sethosts is used here but you can always use the constructor if you'd like

$baseInventory->setGroups([
    'core_switches' => [
        'device_type' => "cisco_ios",
    ],
    'lab_switches' => [
        'username'    => 'lab_username',
        'password'    => 'lab_password',
        'port'        => 2020
    ]
]);

$baseInventory->setDefaults([
     'port'     => 22,
     'username' => "defaultusername",
     'password' => "defaultpassword"
]);

$baseInventory->setHosts([
    'Hostname_1' => [
      'device_type' => 'junos',
      'port'        => 22,
      'username'    => 'my_default_username',
      'password'    => 'my_default_password',
      'hostname'    => '192.168.0.1',
      'groups'      => [
          'core_switches',
      ],
    ],
    'Hostname_2' => [
      'device_type' => 'junos',
      'port'        => 22,
      'username'    => 'my_default_username',
      'password'    => 'my_default_password',
      'hostname'    => '192.168.0.1',
      'groups'      => [
          'core_switches'
      ]
    ],
    'Hostname_3' => [
      'device_type' => 'cisco_ios',
      'port'        => 22,
      'hostname'    => '172.16.2.10',
      'groups'      => [
          'lab_switches',
          'core_switches'
      ]
    ],
]);

$mikodo = new Mikodo();

$mikodo->inventory($baseInventory->getGroups(['lab_swithches', 'core_switches']));
```

The following methods are supported:

```php
$baseInventory = new BaseInventory(array $hosts = array(), array $groups = array(), array $defaults = array());

// Setting the inventory
$baseInventory->setHosts(array $hosts);
$baseInventory->setGroups(array $groups);
$baseInventory->setDefaults(array $defaults);

// Getting/fetching the inventory
$baseInventory->getHosts(array $hosts);
$baseInventory->getGroups(array $groups);

// Get the full inventory
$baseInventory->getInventory();
```

### PhpIpam inventory

Mikodo can use an existing instance of PhpIpam. The inventory provider will fetch all devices from phpipam and will automatically apply some groups to each hostname as long as the corresponding value in phpipam exists and isn't null.

Afterwards if deemed neccesary you can still set group and/or default settings via the provided inventory methods.

A group will be applied for:

- each device type that is know in phpipam.
- the rack(s) known for that devices
- the section(s) known for that device
- the location(s) known for that device
- if there are custom fields set for a device.

If a custom field is supplied for username, password, port and/or device_type then this will not be applied as a group but directly to the object. This way it is possible to set some defaults in PhpIpam

If you wish you can also provide a custom field named 'groups' containing comma delimited groups. These will be added to the groups known for that device.

The following authentication methods are supported:

- User token (unencrypted, username and password is required)
- SSL with user token (encrypted, username and password is required), provide a https api link
- SSL with app code token (encrypted, appCode is required), provide a https link

```php

$ipamUrl  = 'https://phpipam.local/api'
$appId    = 'ipamappId'
$username = 'ipamuser';
$password = 'ipampassword';

$appCode  = 'ipamAppCode';

// When using username and password
$phpipamInventory = new \Epiecs\Mikodo\InventoryProviders\PhpipamInventory($ipamUrl, $appId, $username, $password);

// When using an app code token
$phpipamInventory = new \Epiecs\Mikodo\InventoryProviders\PhpipamInventory($ipamUrl, $appId, "", "", $appCode);

// Set the group and default settings if neccesary
$phpipamInventory->setGroups([
    'switch' => [
        'device_type' => "cisco_ios",
        'password'    => "password"
    ],
    'firewall' => [
        'device_type' => "junos",
        'port'        => 2020
    ]
]);

$phpipamInventory->setDefaults([
     'port'     => 22,
     'username' => "defaultusername",
     'password' => "defaultpassword"
]);

$mikodo = new \Epiecs\Mikodo\Mikodo();

$mikodo->inventory($phpipamInventory->getGroups(['Switch']));
```

### Nornir inventory

If you like Nornir you most likely already have a Nornir inventory. I have the following directory structure in my project folder:

```plaintext
└── inventory
    ├── defaults.yaml
    ├── groups.yaml
    └── hosts.yaml
```

I can load this directory with the NornirInventory provider and query it just the same way like a can with the base inventory. __The only file that is required is the hosts.yaml file__.

For the sake of simplicity I will use the following inventory as reference. Although brief it does suffice as an example to show you priorities of all inventory components.

__default.yaml__
```yaml
---
port: 22
username: my_default_username
password: my_default_password
```

__groups.yaml__
```yaml
---
core_switches:
    device_type: junos

lab_switches:
    device_type: cisco_ios
    port: 2000
```

__hosts.yaml__
```yaml
---
Hostname_1:
    hostname: 192.168.0.1
    groups:
        - core_switches
Hostname_2:
    hostname: 192.168.0.1
    groups:
        - core_switches
Hostname_3:
    hostname: 172.16.2.10
    groups:
        - lab_switches
Hostname_4:
    hostname: 172.16.2.20
    groups:
        - lab_switches
Hostname_5:
    hostname: 172.16.2.30
    groups:
        - lab_switches
Hostname_6:
    hostname: 172.16.2.50
    groups:
        - lab_switches
        - core_switches
```

```php
$nornirInventory = new \Epiecs\Mikodo\InventoryProviders\NornirInventory(__DIR__ . DIRECTORY_SEPARATOR . 'inventory');

$mikodo = new \Epiecs\Mikodo\Mikodo();

$mikodo->inventory($nornirInventory->getGroups(['lab_switches', 'core_switches']));
```
