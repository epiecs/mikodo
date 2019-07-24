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

## Examples:

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

#### Inventories

explanation

##### Base inventory

preference host > group > defaults

##### Nornir inventory

[Nornir inventory documentation](https://nornir.readthedocs.io/en/stable/tutorials/intro/inventory.html)

##### Extending Base inventory + interface

#### Sending commands

When sending commands you can either provide the resp. function with a string or an array consisting of commands. Either way is fine. When providing an array the commands are run in order.

For the difference between the types of commands you can refer to the  [Phpmiko documentation](https://github.com/epiecs/phpmiko)

###### Sending one command as string

```php
echo $device->operation('show interfaces ge-0/0/0');
```

###### Sending one command as an array

```php
echo $device->operation([
	'show interfaces ge-0/0/0',
]);
```

###### Sending multiple commands

```php
echo $device->operation([
	'show interfaces ge-0/0/0',
	'show interfaces ge-0/0/1',
]);
```

###### Fetching results and output



All output will be returned as an array where the key is the command that was run

```plaintext
array (2) [
    'run show version' => "fpc0:
--------------------------------------------------------------------------
Hostname: SW-Junos
Model: ex3300-48p
Junos: 15.1R5-S3.4
JUNOS EX  Software Suite [15.1R5-S3.4]
JUNOS FIPS mode utilities [15.1R5-S3.4]
JUNOS Online Documentation [15.1R5-S3.4]
JUNOS EX 3300 Software Suite [15.1R5-S3.4]
JUNOS Web Management Platform Package [15.1R5-S3.4]
"
    'run show cli' => "CLI complete-on-space set to on
CLI idle-timeout disabled
CLI restart-on-upgrade set to on
CLI screen-length set to 10000
CLI screen-width set to 400
CLI terminal is 'vt100'
CLI is operating in enhanced mode
CLI timestamp disabled
CLI working directory is '/var/root'
"
]
```
