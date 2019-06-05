<?php

namespace RecruiterPhp\Concurrency;

interface Patience
{
    /**
     * Insists calling $function for a while until it returns true.
     *
     * @param callable $function returns a boolean
     */
    public function trial($function);
}
