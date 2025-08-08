<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

class NullLock implements Lock
{
    /**
     * @param int $duration (in seconds)
     */
    public function acquire(int $duration = 360): void
    {
    }

    public function release(bool $force = false): void
    {
    }

    /**
     * @param int $duration (in seconds)
     */
    public function refresh(int $duration = 3600): void
    {
    }

    public function show(): array
    {
        return [
            'program' => 'null-lock',
            'acquired_at' => '1970-01-01T00:00:00+00:00',
            'expires_at' => '2100-01-01T00:00:00+00:00',
        ];
    }

    /**
     * @param int $polling            (in seconds)
     * @param int $maximumWaitingTime (in seconds)
     */
    public function wait(int $polling = 30, int $maximumWaitingTime = 3600): void
    {
    }

    public function __toString(): string
    {
        return 'No locking';
    }
}
