<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

class Poison
{
    public static function forProgram(string $programName): self
    {
        return new self($programName);
    }

    private function __construct(private readonly string $programName)
    {
    }

    /**
     * @param int $timeLimit in seconds
     *
     * @return $this
     */
    public function drinkAfter(int $timeLimit): self
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

    private function sleepUntilThereIsSomethingInteresting(int $timeLimit, int $child): void
    {
        pcntl_signal(SIGALRM, [$this, 'alarm'], true);
        pcntl_alarm($timeLimit);
        pcntl_waitpid($child, $status);
        // pcntl_signal_dispatch();
    }

    public function alarm(): void
    {
        // error_log("Alarm has triggered");
    }

    private function isAlive(int $pid): bool
    {
        return posix_kill($pid, 0);
    }

    private function kill(int $pid): void
    {
        posix_kill($pid, SIGKILL);
    }
}
