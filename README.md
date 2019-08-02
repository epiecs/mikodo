Mikodo
=======

Concurrent library on top of phpmiko. Speeds up the process of sending commands. Libraries are bundled (or planned) to be able to use different providers such as Nornir yaml files, PhpIpam, etc...

#### Requires:

- Php >= 7.1
- ext-sockets
- ext-pcntl

- A UNIX or BSD OS. Native windows is not supported ATM but you can use WSL.

#### Installation:

```bash
composer require epiecs/mikodo
```

#### Supported inventories:

###### Implemented

- Nornir Inventory

###### Planned

- PhpIpam
- Netbox
- ...

## Basic examples:

#### Initializing Mikodo

```php
require_once __DIR__ . '/vendor/autoload.php';

$device = new \Epiecs\Mikodo\Mikodo();
```

##### Buffer size

The size in bytes that is available for each worker for communicating to the parent process. Set this to a higher number if you require a lot of text to be sent (eg.) complete config files.

```php
$mikodo->bufferSize(65535); // Defaults to 65535
```

#### Running commands

Mikodo uses the same 3 methods like Phpmiko to send commands to devices: cli, operation and configure.

When sending commands you can either provide the method with a string or an array consisting of commands. Either way is fine. When providing an array the commands are run in order.

For the difference between the types of commands you can refer to the  [Phpmiko documentation](https://github.com/epiecs/phpmiko)

##### Preparing the inventory

To be able to use Mikodo you have to provide it with an inventory. The most basic way is to provide the __inventory()__ method with an array consisting of hosts:

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

Each host has the neccesary information for Phpmiko. __device_type__, __username__, __password__ and __hostname__ are required.

##### Sending command(s)

When the inventory has been prepared you can start sending commands.

```php
$results = $mikodo->cli([
    'date'
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

When you have a variable with the return value of the cli/operation/configure function it will have the following structure:

Each run has the mode in between brackets with a run id. Next up is each hostname that has had commands run and then we have a key per run command.

```php
[
    '[cli] :: 5d3861b31c8fd' => [
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
]
```

###### Printing results

To output the results to the terminal you just send the returned results to the Mikodo->print() function.

```php
$results = $mikodo->print($results);
```

Also, in real life you should get pretty colors.

```plaintext
Results of run [cli] :: 5d385e3b0e496
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

To ease your life Mikodo includes several inventory providers. As of now only the BaseInventory and NornirInventory is implemented. PhpIpam is in the works.

Inventories allow you to easily select specific hosts and/or groups from your inventory. Inventories also allow you to set default values on a global level and/or group level.

So basically you prep one or more inventories and then select some hosts/groups from that inventory that you feed to the inventory function of mikodo.

However in order to keep everything working the same way no matter the order of setting hosts/groups/defaults some rules have been set.

Basically hosts > groups > defaults. This means that if you set a default setting for a password but set that value in the host itself the host will not take over the default value. Same goes for groups. Group vars are worth less that host vars but more than default vars.

###### Writing your own inventory providers

All inventory providers can extend the base inventory class and should implement the DeviceInterface interface.

The InventoryInterface provides you with a nice structure as how a array containing hosts, groups and defaults should look.

##### Base inventory

The Base inventory can be initialized in three always

- via the constructor
- via setters
- a combination of both

__example__

```php
require_once __DIR__ . '/vendor/autoload.php';

use Epiecs\Mikodo\Mikodo;
use Epiecs\Mikodo\InventoryProviders\BaseInventory;

$baseInventory = new BaseInventory();

// Sethosts is used here but you can always use the constructor
// if you'd like

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
      'username'    => 'my_default_username',
      'password'    => 'my_default_password',
      'hostname'    => '172.16.2.10',
      'groups'      => [
          'lab_switches', 'core_switches'
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

// Getting/fetcing the inventory
$baseInventory->getHosts(array $hosts);
$baseInventory->getGroups(array $groups);

// Get the full inventory
$baseInventory->getInventory();
```

##### Nornir inventory

I like Nornir, so I have a nornir inventory :D. I have the following directory structure in my project folder:

```plaintext
└── inventory
    ├── defaults.yaml
    ├── groups.yaml
    └── hosts.yaml
```

I can load this directory with the NornirInventory provider and query it just the same way like a can with the base inventory. __The only file that is required is the hosts.yaml file__.

For the sake of brevity I will use the following inventory as reference. Although brief it does suffice as an example to show you priorities of all inventory components.

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

Be sure to check out the [Nornir inventory documentation](https://nornir.readthedocs.io/en/stable/tutorials/intro/inventory.html)
