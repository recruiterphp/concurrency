<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

final readonly class NoPatience implements Patience
{
    private \Closure $onFailure;

    public function __construct(callable $onFailure)
    {
        $this->onFailure = $onFailure(...);
    }

    #[\Override]
    public function trial(callable $function): void
    {
        if (!$function()) {
            $onFailure = $this->onFailure;
            $onFailure();
        }
    }
}
