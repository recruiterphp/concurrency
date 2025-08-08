<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

interface Lock extends \Stringable
{
    /**
     * @param int $duration (in seconds)
     *
     * @throws LockNotAvailableException
     */
    public function acquire(int $duration = 360): void;

    public function release(bool $force = false): void;

    /**
     * @param int $duration (in seconds)
     *
     * @throws LockNotAvailableException
     */
    public function refresh(int $duration = 3600): void;

    /**
     * Returns the lock status.
     *
     * @return ?array<string, string>
     */
    public function show(): ?array;

    /**
     * @param int $polling            (in seconds)
     * @param int $maximumWaitingTime (in seconds)
     */
    public function wait(int $polling = 30, int $maximumWaitingTime = 3600): void;
}
