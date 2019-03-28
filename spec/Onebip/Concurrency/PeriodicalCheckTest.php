<?php

namespace Onebip\Concurrency;

use Eris;
use Eris\Generator;
use Eris\Listener;
use Onebip\Clock\SettableClock;
use PHPUnit\Framework\TestCase;

class PeriodicalCheckTest extends TestCase
{
    use Eris\TestTrait;

    private $counter;

    public function testDoesNotPerformTheCheckTooManyTimes()
    {
        $this
            ->forAll(
                Generator\date(),
                Generator\choose(10, 30),
                Generator\seq(
                    Generator\choose(1, 60)
                )
            )
            //->hook(Listener\collectFrequencies())
            ->then(function ($startingDate, $period, $deltas) {
                $clock = new SettableClock($startingDate);
                $check = PeriodicalCheck::every($period, $clock);
                $this->counter = 0;
                $check->onFire(function () {
                    ++$this->counter;
                });
                $check->__invoke();
                foreach ($deltas as $delta) {
                    $clock->advance($delta);
                    $check->__invoke();
                }
                $totalInterval = array_sum($deltas);
                $maximumNumberOfCalls = ceil($totalInterval / $period);
                $actualNumberOfCallsExcludingTheFirst = $this->counter - 1;
                $this->assertLessThanOrEqual($maximumNumberOfCalls, $actualNumberOfCallsExcludingTheFirst);
            });
    }
}
