<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

class Leadership
{
    private $file;

    public static function unto()
    {
        return new self('/var/run/unto');
    }

    public function __construct($file)
    {
        $this->file = $file;
    }

    public function amIStillTheLeader()
    {
        return file_exists($this->file);
    }
}
