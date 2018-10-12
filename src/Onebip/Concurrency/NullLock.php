<?php

namespace Onebip\Concurrency;

class NullLock implements Lock
{
    /**
     * @throws LockNotAvailableException
     * @param $duration (in seconds)
     */
    public function acquire($duration = 360)
    {
    }

    /**
     * @param $force boolean
     */
    public function release($force = false)
    {
    }

    /**
     * @throws LockNotavailableexception
     * @param $duration (in seconds)
     */
    public function refresh($duration = 3600)
    {
    }

    public function show()
    {
        return [
            'program' => 'null-lock',
            'acquired_at' => '1970-01-01T00:00:00+00:00',
            'expires_at' => '1970-01-01T00:00:00+00:00',
        ];
    }

    /**
     * @param $polling (in seconds)
     * @param $maximumWaitingTime (in seconds)
     * @return void
     */
    public function wait($polling = 30, $maximumWaitingTime = 3600)
    {
    }
}