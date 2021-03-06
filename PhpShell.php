<?php
/**
 * Created by PhpStorm.
 * Project: php-shell
 * @author: Evgeny Pynykh bpteam22@gmail.com
 */

namespace bpteam\PhpShell;

use \CommandLine;

class PhpShell {

    protected $arguments = [];
    protected $pid;
    protected $command = '';
    protected $executor = 'php';
    /**
     * @var resource           
     */
    protected $handle;
    protected $success;
    protected $pidFile;

    function __construct()
    {
        $this->pidFile = tempnam(sys_get_temp_dir(), 'PhpShellPID');
        $this->success = tempnam(sys_get_temp_dir(), 'PhpShellSuccess');
    }

    function __destruct()
    {
        $this->close();
    }

    protected function close()
    {
        if (is_resource($this->handle)) {
            pclose($this->handle);
            unlink($this->pidFile);
            unlink($this->success);
        }
        if ($this->isRunning()) {
            $this->kill();
        }
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function setCommand($command)
    {
        $this->command = $command;
    }

    public function getPid()
    {
        return $this->pid;
    }

    public function setExecutor($executor)
    {
        $this->executor = $executor;
    }

    public function addArgument($name, $value = true)
    {
        $this->arguments[$name] = $value;
    }

    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
    }

    public function makeArgument($name, $value)
    {
        if ($value === true) {
            $argument = $name;
        } elseif (is_int($name)) {
            $argument = preg_match('%\s%', $value) || $value==='' ? escapeshellarg($value) : escapeshellcmd($value);
        } else {
            $argument = $name . '=' . escapeshellarg($value);
        }
        return $argument;
    }

    public function parse($command)
    {
        switch (true) {
            case is_array($command):
                $data = CommandLine::parseArgv($command);
                $this->setArguments($data);
                break;
            case is_string($command):
                $data = CommandLine::parseString($command);
                $this->setExecutor(array_shift($data));
                $this->setArguments($data);
                break;
            default:
                return false;
        }
        return true;
    }

    public function build()
    {
        $command[] = $this->executor;
        foreach ($this->arguments as $name => $value) {
            $command[] = $this->makeArgument($name, $value);
        }
        $this->setCommand(implode(' ', $command));
    }

    public function exec($return = false)
    {
        $this->build();
        return $return ? $this->execOutput($this->command) : $this->execBackground($this->command);
    }

    protected function execOutput($command)
    {
        $output = [];
        $return_val = null;
        exec($command, $output, $return_val);
        return $output ? implode("\n", $output) : $return_val;
    }

    protected function execBackground($command)
    {
        $this->close();
        $command = "(" . $command . " && touch " . $this->success . ") & echo $! > " . $this->pidFile . " &";
        $this->handle = popen($command, 'r');
        $this->pid = (int)file_get_contents($this->pidFile);
        return $this->pid;
    }

    public function isRunning()
    {
        return function_exists('posix_getsid') ? posix_getsid($this->pid) : false;
    }

    public function kill()
    {
        if ($this->isRunning()){
            return $this->execOutput("kill -9 " . $this->pid);
        }
        return false;
    }

}