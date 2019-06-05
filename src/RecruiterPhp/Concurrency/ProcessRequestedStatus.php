<?php

namespace RecruiterPhp\Concurrency;

class ProcessRequestedStatus
{
    private $shouldTerminate;
    private $why;

    public static function active()
    {
        return new self(false);
    }

    private function __construct($shouldTerminate, array $why = [])
    {
        $this->shouldTerminate = $shouldTerminate;
        $this->why = $why;
    }

    public function stop($why)
    {
        $this->shouldTerminate = true;
        $this->why[] = $why;

        return $this;
    }

    public function shouldTerminate()
    {
        return $this->shouldTerminate;
    }

    public function why()
    {
        return $this->why;
    }
}
