<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

class Timeout
{
    private int $elapsed = 0;
    private $waitingFor;
    private \Closure|array|string|null $afterCheck = null;
    private ?int $pollingInterval = null;

    /**
     * @return $this
     */
    public static function inSeconds(int $timeout, callable|string $waitingFor = ''): static
    {
        if (!is_numeric($timeout)) {
            throw new \InvalidArgumentException("The timeout must be numeric, since it's expressed in seconds. Instead it is `$timeout`.");
        }

        return new self($timeout * 1000 * 1000, $waitingFor);
    }

    private function __construct(private readonly int $maximum, callable $waitingFor)
    {
        $this->waitingFor = $waitingFor;
        $this->afterCheck = function (): void {
        };
    }

    /**
     * @return $this
     */
    public function checkEvery(int $microseconds, ?callable $afterCheck = null): static
    {
        $this->pollingInterval = $microseconds;
        // TODO: this behaviour is weird
        if (null !== $this->afterCheck) {
            $this->afterCheck = $afterCheck;
        }

        return $this;
    }

    public function elapse($microseconds): void
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
            $afterCheck = $this->afterCheck;
            $afterCheck();
            $this->elapse($microseconds);
        }
    }
}
