<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

final class Timeout
{
    private int $elapsed = 0;
    private mixed $waitingFor;
    private \Closure $afterCheck;
    private ?int $pollingInterval = null;

    public static function inSeconds(int $timeout, callable|string $waitingFor = ''): self
    {
        return new self($timeout * 1_000_000, $waitingFor);
    }

    private function __construct(private readonly int $maximum, callable|string $waitingFor)
    {
        $this->waitingFor = $waitingFor;
        $this->afterCheck = function (): void {
        };
    }

    /**
     * @return $this
     */
    public function checkEvery(int $microseconds, ?callable $afterCheck = null): self
    {
        $this->pollingInterval = $microseconds;
        if (null !== $afterCheck) {
            $this->afterCheck = $afterCheck(...);
        }

        return $this;
    }

    public function elapse(int $microseconds): void
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
    public function until(callable $callback, ?int $microseconds = null): void
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
            ($this->afterCheck)();
            $this->elapse($microseconds);
        }
    }
}
