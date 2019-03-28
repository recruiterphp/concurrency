<?php

namespace Onebip\Concurrency;

class Timeout
{
    private $maximum;
    private $elapsed = 0;
    private $waitingFor;
    private $afterCheck;

    /**
     * @param int             $timeout
     * @param string|callable $waitingFor
     */
    public static function inSeconds($timeout, $waitingFor = '')
    {
        if (!is_numeric($timeout)) {
            throw new \InvalidArgumentException("The timeout must be numeric, since it's expressed in seconds. Instead it is `$timeout`.");
        }

        return new self($timeout * 1000 * 1000, $waitingFor);
    }

    private $pollingInterval;

    private function __construct($microseconds, $waitingFor)
    {
        $this->maximum = $microseconds;
        $this->waitingFor = $waitingFor;
        $this->afterCheck = function () {
        };
    }

    public function checkEvery($microseconds, $afterCheck = null)
    {
        $this->pollingInterval = $microseconds;
        if (null !== $this->afterCheck) {
            $this->afterCheck = $afterCheck;
        }

        return $this;
    }

    public function elapse($microseconds)
    {
        $this->elapsed += $microseconds;
        if ($this->elapsed > $this->maximum) {
            $waitingFor = $this->waitingFor;
            if (is_callable($waitingFor)) {
                $waitingFor = $waitingFor();
            }
            throw new TimeoutException("Waiting for $waitingFor");
        }
        usleep($microseconds);
    }

    /**
     * @param callable $callback should return true when the condition you are waiting for is met
     *
     * @throws TimeoutException
     */
    public function until($callback, $microseconds = null)
    {
        if (null === $microseconds) {
            if (null !== $this->pollingInterval) {
                $microseconds = $this->pollingInterval;
            } else {
                $microseconds = 200000;
            }
        }
        while (true) {
            if ($callback()) {
                return;
            }
            $afterCheck = $this->afterCheck;
            $afterCheck();
            $this->elapse($microseconds);
        }
    }
}
