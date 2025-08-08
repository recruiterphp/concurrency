<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

use Eris;
use Eris\Generator;
use Eris\Listener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class PeriodicalCheckTest extends TestCase
{
    use Eris\TestTrait;

    private int $counter;

    public function testDoesNotPerformTheCheckTooManyTimes(): void
    {
        $this
            ->forAll(
                Generator\date(),
                Generator\choose(10, 30),
                Generator\seq(
                    Generator\choose(1, 60),
                ),
            )
            // ->hook(Listener\collectFrequencies())
            ->then(function (\DateTime $startingDate, int $period, array $deltas): void {
                $clock = new MockClock(\DateTimeImmutable::createFromMutable($startingDate));
                $check = PeriodicalCheck::every($period, $clock);
                $this->counter = 0;
                $check->onFire(function (): void {
                    ++$this->counter;
                });
                $check->__invoke();

                /** @var array<int> $deltas */
                foreach ($deltas as $delta) {
                    $clock->modify("+{$delta} seconds");
                    $check->__invoke();
                }
                $totalInterval = array_sum($deltas);
                $maximumNumberOfCalls = ceil($totalInterval / $period);
                $actualNumberOfCallsExcludingTheFirst = $this->counter - 1;
                $this->assertLessThanOrEqual($maximumNumberOfCalls, $actualNumberOfCallsExcludingTheFirst);
            })
        ;
    }
}
