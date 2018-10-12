<?php

namespace Onebip\Concurrency;

class TimeoutPatience implements Patience
{
    public function __construct(Timeout $timeout)
    {
        $this->timeout = $timeout;
    }

    public function trial($function)
    {
        $this->timeout->until($function);
    }
}
