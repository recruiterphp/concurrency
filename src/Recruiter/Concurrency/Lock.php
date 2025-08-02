<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

interface Lock
{
    /**
     * @param int $duration (in seconds)
     *
     * @throws LockNotAvailableException
     */
    public function acquire($duration = 360): void;

    /**
     * @param bool $force
     */
    public function release($force = false): void;

    /**
     * @param int $duration (in seconds)
     *
     * @throws LockNotavailableexception
     */
    public function refresh($duration = 3600): void;

    /**
     * @return array diagnostic information
     */
    public function show(): ?array;

    /**
     * @param int $polling            (in seconds)
     * @param int $maximumWaitingTime (in seconds)
     */
    public function wait($polling = 30, $maximumWaitingTime = 3600): void;

    public function __toString(): string;
}
