<?php

namespace Recruiter\Concurrency;

use Recruiter\Clock;
use Recruiter\Clock\SystemClock;

class PeriodicalCheck
{
    private array|\Closure $check;
    private int $lastCheck;

    public static function every(int $seconds, ?Clock $clock = null): self
    {
        if (null === $clock) {
            $clock = new SystemClock();
        }

        return new self($seconds, $clock);
    }

    private function __construct(private readonly int $seconds, private readonly Clock $clock)
    {
        $this->lastCheck = 0;
    }

    /**
     * @return $this
     */
    public function onFire(callable $check): self
    {
        $this->check = $check;

        return $this;
    }

    public function __invoke(): void
    {
        $this->execute();
    }

    public function execute(): void
    {
        $now = $this->clock->current()->getTimestamp();
        if ($now - $this->lastCheck >= $this->seconds) {
            call_user_func($this->check);
            $this->lastCheck = $now;
        }
    }
}
