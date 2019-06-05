<?php

namespace Recruiter\Concurrency;

class Idle
{
    public static function sleepUntilDeath()
    {
        while (true) {
            sleep(3600);
        }
    }
}
