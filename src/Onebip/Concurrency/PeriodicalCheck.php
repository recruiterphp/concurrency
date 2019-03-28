<?php

namespace Onebip\Concurrency;

use Onebip\Clock;
use Onebip\Clock\SystemClock;

class PeriodicalCheck
{
    private $check;
    private $clock;
    private $lastCheck;
    private $seconds;

    public static function every($seconds, Clock $clock = null)
    {
        if (null === $clock) {
            $clock = new SystemClock();
        }

        return new self($seconds, $clock);
    }

    private function __construct($seconds, $clock)
    {
        $this->seconds = $seconds;
        $this->clock = $clock;
        $this->lastCheck = 0;
    }

    public function onFire(callable $check)
    {
        $this->check = $check;

        return $this;
    }

    public function __invoke()
    {
        return $this->execute();
    }

    public function execute()
    {
        $now = $this->clock->current()->getTimestamp();
        if ($now - $this->lastCheck >= $this->seconds) {
            call_user_func($this->check);
            $this->lastCheck = $now;
        }
    }
}
