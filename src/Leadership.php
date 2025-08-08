<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

readonly class Leadership
{
    public static function unto(): self
    {
        return new self('/var/run/unto');
    }

    public function __construct(private string $file)
    {
    }

    public function amIStillTheLeader(): bool
    {
        return file_exists($this->file);
    }
}
