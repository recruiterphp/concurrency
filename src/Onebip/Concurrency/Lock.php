<?php
namespace Onebip\Concurrency;

interface Lock
{
    /**
     * @throws LockNotAvailableException
     * @param $duration (in seconds)
     */
    public function acquire($duration = 360);

    /**
     * @param $force boolean
     */
    public function release($force = false);

    /**
     * @throws LockNotavailableexception
     * @param $duration (in seconds)
     */
    public function refresh($duration = 3600);

    /**
     * @return array  diagnostic information
     */
    public function show();

    /**
     * @param $polling (in seconds)
     * @param $maximumWaitingTime (in seconds)
     * @return void
     */
    public function wait($polling = 30, $maximumWaitingTime = 3600);
}
