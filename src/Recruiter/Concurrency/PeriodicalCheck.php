<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

class PeriodicalCheck
{
    private array|\Closure $check;
    private int $lastCheck;

    public static function every(int $seconds, ?ClockInterface $clock = null): self
    {
        return new self($seconds, $clock ?? new NativeClock());
    }

    private function __construct(private readonly int $seconds, private readonly ClockInterface $clock)
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
        $now = $this->clock->now()->getTimestamp();
        if ($now - $this->lastCheck >= $this->seconds) {
            call_user_func($this->check);
            $this->lastCheck = $now;
        }
    }
}
