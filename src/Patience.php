<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

interface Patience
{
    /**
     * Insists calling $function for a while until it returns true.
     *
     * @param callable(): bool $function returns a boolean
     */
    public function trial(callable $function): void;
}
