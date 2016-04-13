<?php
namespace Onebip\Concurrency;

interface Lock
{
    /**
     * @throws LockNotAvailableException
     * @param $duration (in seconds)
     */
    public function acquire($duration);

    /**
     * @param $force boolean
     */
    public function release($force);

    /**
     * @throws LockNotavailableexception
     * @param $duration (in seconds)
     */
    public function refresh($duration);

    /**
     * @return array  diagnostic information
     */
    public function show();

    /**
     * @param $polling (in seconds)
     * @param $maximumWaitingTime (in seconds)
     * @return void
     */
    public function wait($polling, $maximumWaitingTime);
}
