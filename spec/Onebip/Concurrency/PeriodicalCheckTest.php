<?php
namespace Onebip\Concurrency;

use DateInterval;
use DateTime;
use Eris;
use Eris\Generator;
use Eris\Listener;
use Onebip\Clock;

class PeriodicalCheckTest extends \PHPUnit_Framework_TestCase
{
    use Eris\TestTrait;

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
            ->then(function($startingDate, $period, $deltas) {
                $clock = new SettableClock($startingDate);
                $check = PeriodicalCheck::every($period, $clock);
                $this->counter = 0;
                $check->onFire(function() {
                    $this->counter++;
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

class SettableClock implements Clock
{
    private $current;
    
    public function __construct(DateTime $current)
    {
        $this->current = $current;
    }

    public function advance($seconds)
    {
        $this->current->add(new DateInterval("PT{$seconds}S"));
    }

    public function current()
    {
        return $this->current;
    }
}
