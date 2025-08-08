<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

use Eris;
use Eris\Generator;
use MongoDB;
use Phake;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Recruiter\Clock\ProgressiveClock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Process\Process;

class MongoLockTest extends TestCase
{
    use Eris\TestTrait;

    private MongoDB\Collection $lockCollection;
    private ClockInterface&Phake\IMock $clock;
    private int $iteration;

    protected function setUp(): void
    {
        $uri = getenv('MONGODB_URI') ?: null;
        $this->lockCollection = new MongoDB\Client($uri)->selectCollection('concurrency-test', 'lock');
        $this->clock = \Phake::mock(ClockInterface::class);
    }

    protected function tearDown(): void
    {
        $this->lockCollection->drop();
    }

    public function testALockCanBeAcquired(): void
    {
        $this->givenTimeIsFixed();
        $lock = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $lock->acquire();
        $this->expectNotToPerformAssertions();
    }

    public function testAnAlreadyAcquiredLockCannotBeAcquiredAgain(): void
    {
        $this->givenTimeIsFixed();
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-30:23', $this->clock);

        $this->expectExceptionMessage('ws-a-30:23 cannot acquire a lock for the program windows_defrag');
        $this->expectException(LockNotAvailableException::class);

        $second->acquire();
    }

    public function testAnAlreadyAcquiredLockCannotBeAcquiredAgainEvenWithRefreshMethod(): void
    {
        $this->givenTimeIsFixed();
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-30:23', $this->clock);

        $this->expectExceptionMessage('ws-a-30:23 cannot acquire a lock for the program windows_defrag');
        $this->expectException(LockNotAvailableException::class);

        $second->refresh();
    }

    public function testAnAlreadyAcquiredLockCanExpireSoThatItCanBeAcquiredAgain(): void
    {
        \Phake::when($this->clock)->now()
            ->thenReturn(new \DateTimeImmutable('2014-01-01T10:00:00Z'))
            ->thenReturn(new \DateTimeImmutable('2014-01-01T11:00:01Z'))
        ;
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire(3600);

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-30:23', $this->clock);
        $second->acquire(3600);
        $this->expectNotToPerformAssertions();
    }

    public function testLockForDifferentProgramsDoNotInterfereWithEachOther(): void
    {
        $this->givenTimeIsFixed();
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();

        $second = new MongoLock($this->lockCollection, 'sll_world_domination', 'ws-a-30:23', $this->clock);
        $second->acquire();
        $this->expectNotToPerformAssertions();
    }

    public function testLocksCanBeReleasedToMakeThemAvailableAgain(): void
    {
        $this->givenTimeIsFixed();
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();
        $first->release();

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-30:23', $this->clock);
        $second->acquire();
        $this->expectNotToPerformAssertions();
    }

    public function testALockCannotBeReleasedBySomeoneElseThanTheProcessAcquiringIt(): void
    {
        $this->givenTimeIsFixed();
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();

        $this->expectExceptionMessage('ws-a-30:23 does not have a lock for windows_defrag to release');
        $this->expectException(LockNotAvailableException::class);

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-30:23', $this->clock);
        $second->release();
    }

    public function testALockCanBeForcedToBeReleasedIfYouReallyKnowWhatYouReDoing(): void
    {
        $this->givenTimeIsFixed();
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-30:23', $this->clock);
        $second->release($force = true);
        $this->expectNotToPerformAssertions();
    }

    public function testALockCanBeShownEvenByOtherProcessesWorkingOnTheSameProgram(): void
    {
        \Phake::when($this->clock)->now()
            ->thenReturn(new \DateTimeImmutable('2014-01-01T00:00:00Z'))
        ;
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire(3600);

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $this->assertEquals(
            [
                'program' => 'windows_defrag',
                'process' => 'ws-a-25:42',
                'acquired_at' => '2014-01-01T00:00:00+00:00',
                'expires_at' => '2014-01-01T01:00:00+00:00',
            ],
            $second->show(),
        );
    }

    public function testALockCanBeWaitedOnUntilItsDisappearance(): void
    {
        $allCalls = \Phake::when($this->clock)->now()
            ->thenReturn(new \DateTimeImmutable('2014-01-01T00:00:00Z'))
            ->thenReturn(new \DateTimeImmutable('2014-01-01T00:00:00Z'))
            ->thenReturn(new \DateTimeImmutable('2014-01-01T00:00:00Z'))
            ->thenReturn(new \DateTimeImmutable('2014-01-01T00:00:30Z'))
            ->thenReturn(new \DateTimeImmutable('2014-01-01T00:01:00Z'))
        ;
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire(45);

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $second->wait($polling = 30);
        \Phake::verify($this->clock, \Phake::times(2))->sleep(30);
    }

    public function testALockShouldNotBeWaitedUponForever(): void
    {
        $clock = new ProgressiveClock(new \DateTimeImmutable('2014-01-01T00:00:00Z'));

        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $clock);
        $first->acquire(3600);

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $clock);
        try {
            $second->wait($polling = 30, $maximumInterval = 60);
            $this->fail('Should fail after 60 seconds');
        } catch (LockNotAvailableException $e) {
            $this->assertEquals(
                'I have been waiting up until 2014-01-01T00:01:01+00:00 for the lock windows_defrag (60 seconds polling every 30 seconds), but it is still not available (now is 2014-01-01T00:01:04+00:00).',
                $e->getMessage(),
            );
        }
    }

    public function testALockWaitedUponCanBeImmediatelyReacquired(): void
    {
        $allCalls = \Phake::when($this->clock)->now()
            ->thenReturn(new \DateTimeImmutable('2014-01-01T00:00:00Z'))
            ->thenReturn(new \DateTimeImmutable('2014-01-01T00:00:30Z'))
            ->thenReturn(new \DateTimeImmutable('2014-01-01T00:00:30Z'))
            ->thenReturn(new \DateTimeImmutable('2014-01-01T00:00:30Z'))
            ->thenReturn(new \DateTimeImmutable('2014-01-01T00:00:31Z'))
            ->thenReturn(new \DateTimeImmutable('2014-01-01T00:00:31Z'))
        ;
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire(30);

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $second->wait($polling = 1);
        $second->acquire();
        $this->expectNotToPerformAssertions();
    }

    public function testAnAlreadyAcquiredLockCanBeRefreshed(): void
    {
        \Phake::when($this->clock)->now()
            ->thenReturn(new \DateTimeImmutable('2014-01-01T00:00:00Z'))
            ->thenReturn(new \DateTimeImmutable('2014-01-01T00:10:00Z'))
        ;

        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $second->refresh();

        $this->assertEquals(
            [
                'program' => 'windows_defrag',
                'process' => 'ws-a-25:42',
                'acquired_at' => '2014-01-01T00:00:00+00:00',
                'expires_at' => '2014-01-01T01:10:00+00:00',
            ],
            $second->show(),
        );
    }

    public function testAnExpiredLockCannotBeRefreshed(): void
    {
        \Phake::when($this->clock)->now()
            ->thenReturn(new \DateTimeImmutable('2014-01-01T00:00:00Z'))
            ->thenReturn(new \DateTimeImmutable('2014-01-01T02:00:00Z'))
        ;

        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();

        $this->expectExceptionMessage('ws-a-25:42 cannot acquire a lock for the program windows_defrag');
        $this->expectException(LockNotAvailableException::class);

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $second->refresh();
    }

    private function givenTimeIsFixed(): void
    {
        \Phake::when($this->clock)->now()->thenReturn(new \DateTimeImmutable('2014-01-01'));
    }

    #[Group('long')]
    public function testPropertyBased(): void
    {
        $this->iteration = 0;
        $this
            ->forAll(
                Generator\vector(
                    2,
                    Generator\seq(
                        Generator\elements(['acquire', 'release']),
                        // TODO: add 'sleep'
                    ),
                ),
            )
            ->when(function ($sequencesOfSteps) {
                foreach ($sequencesOfSteps as $sequence) {
                    if (!$sequence) {
                        return false;
                    }
                }

                return true;
            })
            ->then(function ($sequencesOfSteps): void {
                $this->lockCollection->drop();
                $log = "/tmp/mongolock_{$this->iteration}.log";
                if (file_exists($log)) {
                    unlink($log);
                }

                $processes = [];
                foreach ($sequencesOfSteps as $i => $sequence) {
                    $processName = "p{$i}";
                    $steps = implode(',', $sequence);
                    $process = Process::fromShellCommandline('exec php ' . __DIR__ . "/mongolock.php $processName $steps >> $log");
                    $process->start();
                    $processes[] = $process;
                }
                foreach ($processes as $process) {
                    $process->wait();
                    $this->assertExitedCorrectly($process, 'Error in MongoLock run');
                }
                $process = Process::fromShellCommandline('exec java -jar ' . __DIR__ . "/knossos-recruiterphp.jar mongo-lock $log");
                $process->run();
                $this->assertExitedCorrectly($process, "Non-linearizable history in $log");
                ++$this->iteration;
            })
        ;
    }

    private function assertExitedCorrectly(Process $process, string $errorMessage): void
    {
        $this->assertEquals(
            0,
            $process->getExitCode(),
            $errorMessage . PHP_EOL .
            $process->getErrorOutput() . PHP_EOL .
            $process->getOutput(),
        );
    }
}
