<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

/**
 * @template T
 */
final class InProcessRetry
{
    private int $retries = 1;

    /**
     * Creates a new InProcessRetry instance.
     *
     * @param \Closure(): T $what The closure to be retried.
     * @param class-string<\Exception> $exceptionClass
     * @return self<T>
     */
    public static function of(\Closure $what, string $exceptionClass): self
    {
        return new self($what, $exceptionClass);
    }

    /**
     * @param \Closure(): T $what The closure to be retried.
     * @param class-string $exceptionClass
     */
    private function __construct(private readonly \Closure $what, private readonly string $exceptionClass)
    {
    }

    /**
     * @return $this
     */
    public function forTimes(int $retries): self
    {
        $this->retries = $retries;

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(): mixed
    {
        $possibleRetries = $this->retries;
        for ($i = 0; $i <= $possibleRetries; ++$i) {
            try {
                return call_user_func($this->what);
            } catch (\Exception $e) {
                if (!($e instanceof $this->exceptionClass)) {
                    throw $e;
                }
            }
        }
        throw $e ?? new \InvalidArgumentException("Invalid number of retries: $possibleRetries");
    }
}
