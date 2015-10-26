<?php
namespace Onebip\Concurrency;

interface Lock
{
    /**
     * @throws LockNotAvailableException
     */
    public function acquire();

    public function release();

    /**
     * @return array  diagnostic information
     */
    public function show();

    /**
     * @return void
     */
    public function wait();
}
