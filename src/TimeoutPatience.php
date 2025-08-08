<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

readonly class TimeoutPatience implements Patience
{
    public function __construct(private Timeout $timeout)
    {
    }

    #[\Override]
    public function trial(callable $function): void
    {
        $this->timeout->until($function);
    }
}
