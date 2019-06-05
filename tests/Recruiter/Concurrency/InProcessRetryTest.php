<?php

namespace Recruiter\Concurrency;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class InProcessRetryTest extends TestCase
{
    public function setUp()
    {
        $this->count = 0;
        $this->counter = function () {
            ++$this->count;

            return $this->count;
        };
    }

    private function exceptionalCounterFactory($exceptionClass)
    {
        return function () use ($exceptionClass) {
            ++$this->count;
            throw new $exceptionClass();
        };
    }

    private function limitedExceptionalCounterFactory($exceptionClass, $limit)
    {
        return function () use ($exceptionClass, &$limit) {
            ++$this->count;
            if ($limit > 0) {
                --$limit;
                throw new $exceptionClass();
            }

            return $this->count;
        };
    }

    public function testPerformsOnceATaskIfItIsSuccessful()
    {
        $retry = InProcessRetry::of($this->counter, 'InvalidArgumentException');
        $retry->__invoke();
        $this->assertEquals(1, $this->count);
    }

    public function testReturnsTheValueReturnedByTheTask()
    {
        $retry = InProcessRetry::of($this->counter, 'InvalidArgumentException');
        $this->assertEquals(1, $retry->__invoke());
    }

    public function testInCaseOfSpecifiedExceptionRetriesOnceByDefault()
    {
        $retry = InProcessRetry::of($this->exceptionalCounterFactory('InvalidArgumentException'), 'InvalidArgumentException');
        try {
            $retry->__invoke();
            $this->fail('Should let the 2nd InvalidArgumentException bubble up');
        } catch (InvalidArgumentException $e) {
        }
        $this->assertEquals(2, $this->count);
    }

    public function testInCaseOfSpecifiedExceptionRetriesReturnTheOriginalValueReturnedByTheTask()
    {
        $retry = InProcessRetry::of($this->limitedExceptionalCounterFactory('InvalidArgumentException', 1), 'InvalidArgumentException');
        $this->assertEquals(2, $retry->__invoke());
    }

    public function testInCaseOfGenericExceptionDoesNotRetry()
    {
        $retry = InProcessRetry::of($this->exceptionalCounterFactory('Exception'), 'InvalidArgumentException');
        try {
            $retry->__invoke();
            $this->fail('Should let the 1st Exception bubble up');
        } catch (Exception $e) {
        }
        $this->assertEquals(1, $this->count);
    }

    public function testCanPerformMultipleRetriesUntilAnHappyReturn()
    {
        $failures = 4;
        $retry = InProcessRetry::of($this->limitedExceptionalCounterFactory('InvalidArgumentException', $failures), 'InvalidArgumentException')
            ->forTimes($failures);
        $this->assertEquals($totalCalls = $failures + 1, $retry->__invoke());
    }

    public function testCanPerformMultipleRetriesUntilTheyAreFinished()
    {
        $failures = 4;
        $retry = InProcessRetry::of($this->exceptionalCounterFactory('InvalidArgumentException', $failures), 'InvalidArgumentException')
            ->forTimes($failures);
        try {
            $retry->__invoke();
            $this->fail('Even multiple invocations should always fail.');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals($totalCalls = $failures + 1, $this->count);
        }
    }
}
