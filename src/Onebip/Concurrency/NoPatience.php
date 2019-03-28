<?php

namespace Onebip\Concurrency;

class NoPatience implements Patience
{
    private $onFailure;

    public function __construct($onFailure)
    {
        $this->onFailure = $onFailure;
    }

    public function trial($function)
    {
        if (!$function()) {
            $onFailure = $this->onFailure;
            $onFailure();
        }
    }
}
