<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

class Idle
{
    public static function sleepUntilDeath()
    {
        /** @phpstan-ignore-next-line */
        while (true) {
            sleep(3600);
        }
    }
}
