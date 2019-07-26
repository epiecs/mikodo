<?php

namespace Epiecs\Mikodo;

use Epiecs\PhpMiko\ConnectionHandler;
use League\CLImate\CLImate;


/**
*  Mikodo is a library on top of Phpmiko that allows you to concurrently process
*  commands on devices.
*
*  There are also built in inventory providers to ease searching and processing
*  hosts and settings
*
*  Instantiates Mikodo.
*
*  @author Gregory Bers
*/

class Mikodo
{
    /**
     * Array containing the list of all hosts on which the commands will be run
     *
     * A host should be a valid phpmiko host:
     *
     * 'hostname' = [
	 *     'device_type' => 'junos',        //required
	 *     'hostname'    => '192.168.0.1',  //required
	 *     'username'    => 'username',     //required
	 *     'password'    => 'password',     //required
	 *     'port'        => 22,             //defaults to 22 if not set
	 *     'secret'      => 'secret',       //default is ''. eg. enable password for cisco
	 *     'raw'         => false           //default is false, returns raw unfiltered output if true
	 * ]
     *
     * @var array
     */

    private $inventory;

    /**
     * Contains a CLImate object
     * @var \League\CLImate\CLImate
     */
    private $cli;

    /**
     * The buffer size used in inter process communication. If set too small not all
     * returned text from a device will fit. Can be set lower to preserve memory
     * @var integer
     */

    private $bufferSize;

	/**
	 * Sets base values.
	 *
	 * @param array $parameters Array containing parameters.
	 *
	 * Parameters are defined as follows:
	 *
	 * $mikodo = new \Epiecs\Mikodo\Mikodo([
	 *     '$this->bufferSize'         => 65535 //default is 65535
	 * ]);
	 *
     * @throws Exception when using windows because pcntl_fork is not supported on that platform
	 */

	public function __construct(int $bufferSize = 65535)
	{
		if(strtoupper(substr(PHP_OS, 0, 3)) == 'WIN'){ throw new \Exception("Mikodo is only compatible with unix based systems. Windows does not support pcntl_fork", 1);}

        $this->bufferSize = $bufferSize;
        $this->cli = new CLImate;
	}

	/**
	 * Public methods
	 */

    /**
     * Adds one or more hosts to the inventory. Check the inventory public variable to see how a
     * host should look.
     *
     * This is a fluent interface that can be chained
     *
     * @param  mixed $inventory A fully qualified host or array of hosts
     * @return void
     *
     * @throws Exception if a invalid host is provided
     */

    public function inventory($inventory) : void
    {
        $inventory = is_array($inventory) ? $inventory : array($inventory);

        array_walk($inventory, function ($details, $hostname){

            if(empty($details['device_type']) || $details['device_type'] === null) { throw new \Exception("device_type is not set or null for {$hostname}", 1);}
            if(empty($details['hostname']) || $details['hostname'] === null) { throw new \Exception("hostname is not set or null for {$hostname}", 1);}
            if(empty($details['username']) || $details['username'] === null) { throw new \Exception("username is not set or null for {$hostname}", 1);}
            if(empty($details['password']) || $details['password'] === null) { throw new \Exception("password is not set or null for {$hostname}", 1);}

        });

        $this->inventory = $inventory;
    }

    /**
     * Runs cli commands on the devices in the inventory
     * @param  mixed $commands string or array containing commands
     * @return array           array with command results
     */

	public function cli($commands) : array
	{
        $commands = is_array($commands) ? $commands : array($commands);

        return $this->run('cli', $commands);
	}

    /**
     * Runs operational commands on the devices in the inventory
     * @param  mixed $commands string or array containing commands
     * @return array           array with command results
     */

	public function operation($commands) : array
	{
        $commands = is_array($commands) ? $commands : array($commands);

		return $this->run('operation', $commands);
	}

    /**
     * Runs configuration commands on the devices in the inventory
     * @param  mixed $commands string or array containing commands
     * @return array           array with command results
     */

	public function configure($commands) : array
	{
        $commands = is_array($commands) ? $commands : array($commands);

		return $this->run('configure', $commands);
	}

    /**
     * Gives a nice output with all the results using CLImate
     *
     * @return void
     */

    public function print(array $results) : void
    {
        $this->cli->cyan("Results of run {$run}");

        foreach($runResults as $hostname => $commands)
        {
            $this->cli->lightGreen($hostname);

            foreach($commands as $command => $output)
            {
                $this->cli->lightYellow($command);
                $this->cli->out($output . "\n");
            }
        }
    }

    /**
	 * Private methods
	 */

    /**
     * Runs the given command type
     * @param  string $commandType should be cli|operation|configure
     * @param  array  $commands    array containing all commands to be run
     *
     * @return array               returns an array containing the results of that run
     */

    private function run(string $commandType, array $commands) : array
    {
        $results = array();
        $runId = uniqid("[{$commandType}] :: ");

        $this->cli->yellow()->out("Starting mikodo,  " . count($this->inventory) . " queued jobs");

        $this->cli->lightGreen()->out("[{$commandType}]");

        foreach($commands as $command)
        {
            $this->cli->out("\t{$command}");
        }

        $forks   = array();
        $sockets = array();

        // * 2 because we have to send and receive data
        $progress = $this->cli->progress()->total(count($this->inventory) * 2);

        foreach ($this->inventory as $hostname => $deviceDetails)
        {
            $progress->advance(1, "Sending command(s) to {$hostname}");

            $sockets[$hostname] = array();
            socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets[$hostname]);
            $pid = pcntl_fork();

            if($pid == 0)
            {
                $device = new \Epiecs\PhpMiko\ConnectionHandler($deviceDetails);

                $result =  $device->$commandType($commands);

                socket_write($sockets[$hostname][0], str_pad(serialize($result), $this->bufferSize), $this->bufferSize);
                socket_close($sockets[$hostname][0]);

                exit();
            }
            elseif($pid > 0)
            {
                $forks[$pid] = $hostname;
            }
        }

        $results = array();

        while(count($forks) > 0)
        {
            //We use a while loop on top of the foreach to check if a process has finished. That way we can
            //efficiently process returned output without having to wait on devices that are stalling before the
            //current device in the queue

            foreach($forks as $pid => $hostname)
            {
                if(posix_getpgid($pid) == false) // proc has exited
                {
                    $progress->advance(1, "Retrieving output from {$hostname}");

                    pcntl_waitpid($pid, $status);
                    $results[$hostname] = unserialize(trim(socket_read($sockets[$hostname][1], $this->bufferSize)));
                    socket_close($sockets[$hostname][1]);

                    unset($forks[$pid]);
                }
            }
        }

        return $results;
    }
}
