<?php

namespace Onebip\Concurrency;

interface Lock
{
    /**
     * @throws LockNotAvailableException
     *
     * @param int $duration (in seconds)
     */
    public function acquire($duration = 360): void;

    /**
     * @param bool $force
     */
    public function release($force = false): void;

    /**
     * @throws LockNotavailableexception
     *
     * @param int $duration (in seconds)
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

    /**
     * @return string
     */
    public function __toString(): string;
}
