<?php

namespace Onebip\Concurrency;

class NullLock implements Lock
{
    /**
     * @throws LockNotAvailableException
     *
     * @param int $duration (in seconds)
     */
    public function acquire($duration = 360): void
    {
    }

    /**
     * @param bool $force
     */
    public function release($force = false): void
    {
    }

    /**
     * @throws LockNotavailableexception
     *
     * @param int $duration (in seconds)
     */
    public function refresh($duration = 3600): void
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
    public function wait($polling = 30, $maximumWaitingTime = 3600): void
    {
    }

    public function __toString(): string
    {
        return 'No locking';
    }
}
