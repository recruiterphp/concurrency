<?php

namespace Onebip\Concurrency;

use Exception;
use InvalidArgumentException;

class InProcessRetry
{
    private $what;
    private $exceptionClass;
    private $retries = 1;

    public static function of($what, $exceptionClass)
    {
        return new self($what, $exceptionClass);
    }

    private function __construct($what, $exceptionClass)
    {
        $this->what = $what;
        $this->exceptionClass = $exceptionClass;
    }

    public function forTimes($retries)
    {
        $this->retries = $retries;

        return $this;
    }

    public function __invoke()
    {
        $possibleRetries = $this->retries;
        for ($i = 0; $i <= $possibleRetries; ++$i) {
            try {
                return call_user_func($this->what);
            } catch (Exception $e) {
                if (!($e instanceof $this->exceptionClass)) {
                    throw $e;
                }
            }
        }
        throw $e ?? new InvalidArgumentException("Invalid number of retries: $possibleRetries");
    }
}
