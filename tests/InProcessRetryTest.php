<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InProcessRetry::class)]
final class InProcessRetryTest extends TestCase
{
    private int $count;
    private \Closure $counter;

    protected function setUp(): void
    {
        $this->count = 0;
        $this->counter = function (): int {
            ++$this->count;

            return $this->count;
        };
    }

    /**
     * @param class-string<\Exception> $exceptionClass
     */
    private function exceptionalCounterFactory(string $exceptionClass): \Closure
    {
        return function () use ($exceptionClass): never {
            ++$this->count;
            throw new $exceptionClass();
        };
    }

    /**
     * @param class-string<\Exception> $exceptionClass
     */
    private function limitedExceptionalCounterFactory(string $exceptionClass, int $limit): \Closure
    {
        return function () use ($exceptionClass, &$limit): int {
            ++$this->count;
            if ($limit > 0) {
                --$limit;
                throw new $exceptionClass();
            }

            return $this->count;
        };
    }

    /**
     * @throws \Exception
     */
    public function testPerformsOnceATaskIfItIsSuccessful(): void
    {
        $retry = InProcessRetry::of($this->counter, 'InvalidArgumentException');
        $retry->__invoke();
        $this->assertSame(1, $this->count);
    }

    /**
     * @throws \Exception
     */
    public function testReturnsTheValueReturnedByTheTask(): void
    {
        $retry = InProcessRetry::of($this->counter, 'InvalidArgumentException');
        $this->assertEquals(1, $retry->__invoke());
    }

    /**
     * @throws \Exception
     */
    public function testInCaseOfSpecifiedExceptionRetriesOnceByDefault(): void
    {
        $retry = InProcessRetry::of($this->exceptionalCounterFactory('InvalidArgumentException'), 'InvalidArgumentException');
        try {
            $retry->__invoke();
            $this->fail('Should let the 2nd InvalidArgumentException bubble up');
        } catch (\InvalidArgumentException) {
        }
        $this->assertSame(2, $this->count);
    }

    /**
     * @throws \Exception
     */
    public function testInCaseOfSpecifiedExceptionRetriesReturnTheOriginalValueReturnedByTheTask(): void
    {
        $retry = InProcessRetry::of($this->limitedExceptionalCounterFactory('InvalidArgumentException', 1), 'InvalidArgumentException');
        $this->assertEquals(2, $retry->__invoke());
    }

    public function testInCaseOfGenericExceptionDoesNotRetry(): void
    {
        $retry = InProcessRetry::of($this->exceptionalCounterFactory('Exception'), 'InvalidArgumentException');
        try {
            $retry->__invoke();
            $this->fail('Should let the 1st Exception bubble up');
        } catch (\Exception) {
        }
        $this->assertSame(1, $this->count);
    }

    /**
     * @throws \Exception
     */
    public function testCanPerformMultipleRetriesUntilAnHappyReturn(): void
    {
        $failures = 4;
        $retry = InProcessRetry::of($this->limitedExceptionalCounterFactory('InvalidArgumentException', $failures), 'InvalidArgumentException')
            ->forTimes($failures)
        ;
        $this->assertEquals($totalCalls = $failures + 1, $retry->__invoke());
    }

    /**
     * @throws \Exception
     */
    public function testCanPerformMultipleRetriesUntilTheyAreFinished(): void
    {
        $failures = 4;
        $retry = InProcessRetry::of($this->exceptionalCounterFactory('InvalidArgumentException'), 'InvalidArgumentException')
            ->forTimes($failures)
        ;
        try {
            $retry->__invoke();
            $this->fail('Even multiple invocations should always fail.');
        } catch (\InvalidArgumentException) {
            $this->assertSame($totalCalls = $failures + 1, $this->count);
        }
    }
}
