<?php

namespace Recruiter\Concurrency;

class TimeoutPatience implements Patience
{
    private $timeout;

    public function __construct(Timeout $timeout)
    {
        $this->timeout = $timeout;
    }

    public function trial($function)
    {
        $this->timeout->until($function);
    }
}
