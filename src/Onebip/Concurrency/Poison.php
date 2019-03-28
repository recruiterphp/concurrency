<?php

namespace Onebip\Concurrency;

class Poison
{
    private $programName;

    public static function forProgram($programName)
    {
        return new self($programName);
    }

    private function __construct($programName)
    {
        $this->programName = $programName;
    }

    /**
     * @param int $timeLimit in seconds
     *
     * @return self
     */
    public function drinkAfter($timeLimit)
    {
        $pid = pcntl_fork();
        if (!($pid >= 0)) {
            throw new \RuntimeException("Cannot fork {$this->programName} to perform timeout checks. The script will not run.");
        }
        if ($pid) {
            // we are in father
            $child = $pid;
            $this->sleepUntilThereIsSomethingInteresting($timeLimit, $child);
            if ($this->isAlive($child)) {
                $this->kill($child);
                throw new TimeoutException("{$this->programName} ($pid) killed after it reached time limit of {$timeLimit}!");
            }
            exit;
        } else {
            // we are in child
            return $this;
        }
    }

    private function sleepUntilThereIsSomethingInteresting($timeLimit, $child)
    {
        pcntl_signal(SIGALRM, [$this, 'alarm'], true);
        pcntl_alarm($timeLimit);
        pcntl_waitpid($child, $status);
        //pcntl_signal_dispatch();
    }

    public function alarm()
    {
        //error_log("Alarm has triggered");
    }

    private function isAlive($pid)
    {
        return posix_kill($pid, 0);
    }

    private function kill($pid)
    {
        posix_kill($pid, 9);
    }
}
