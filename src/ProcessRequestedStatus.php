<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

final class ProcessRequestedStatus
{
    public static function active(): self
    {
        return new self(false);
    }

    /**
     * @param array<int,string> $why
     */
    private function __construct(private bool $shouldTerminate, private array $why = [])
    {
        $this->why = $why;
    }

    /**
     * @return $this
     */
    public function stop(string $why): self
    {
        $this->shouldTerminate = true;
        $this->why[] = $why;

        return $this;
    }

    public function shouldTerminate(): bool
    {
        return $this->shouldTerminate;
    }

    /**
     * @return array<int,string>
     */
    public function why(): array
    {
        return $this->why;
    }
}
