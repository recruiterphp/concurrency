<?php

namespace Onebip\Concurrency;

use DateTime;
use Eris;
use Eris\Generator;
use MongoDB;
use Phake;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class MongoLockTest extends TestCase
{
    use Eris\TestTrait;

    private $lockCollection;
    private $clock;
    private $slept;
    private $sleep;

    public function setUp()
    {
        $this->lockCollection = (new MongoDB\Client())->test->lock;
        $this->clock = Phake::mock('Onebip\Clock');

        $this->slept = [];
        $this->sleep = function ($amount) {
            $this->slept[] = $amount;
        };
    }

    public function tearDown()
    {
        $this->lockCollection->drop();
    }

    public function testALockCanBeAcquired()
    {
        $this->givenTimeIsFixed();
        $lock = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $lock->acquire();
        $this->assertTrue(true, 'make PHPUnit happy');
    }

    /**
     * @expectedException \Onebip\Concurrency\LockNotAvailableException
     * @expectedExceptionMessage ws-a-30:23 cannot acquire a lock for the program windows_defrag
     */
    public function testAnAlreadyAcquiredLockCannotBeAcquiredAgain()
    {
        $this->givenTimeIsFixed();
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-30:23', $this->clock);
        $second->acquire();
        // $this->assertTrue(true, 'make PHPUnit happy');
    }

    public function testAnAlreadyAcquiredLockCanExpireSoThatItCanBeAcquiredAgain()
    {
        Phake::when($this->clock)->current()
            ->thenReturn(new DateTime('2014-01-01T10:00:00Z'))
            ->thenReturn(new DateTime('2014-01-01T11:00:01Z'))
        ;
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire(3600);

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-30:23', $this->clock);
        $second->acquire(3600);
        $this->assertTrue(true, 'make PHPUnit happy');
    }

    public function testLockForDifferentProgramsDoNotInterfereWithEachOther()
    {
        $this->givenTimeIsFixed();
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();

        $second = new MongoLock($this->lockCollection, 'sll_world_domination', 'ws-a-30:23', $this->clock);
        $second->acquire();
        $this->assertTrue(true, 'make PHPUnit happy');
    }

    public function testLocksCanBeReleasedToMakeThemAvailableAgain()
    {
        $this->givenTimeIsFixed();
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();
        $first->release();

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-30:23', $this->clock);
        $second->acquire();
        $this->assertTrue(true, 'make PHPUnit happy');
    }

    /**
     * @expectedException \Onebip\Concurrency\LockNotAvailableException
     * @expectedExceptionMessage ws-a-30:23 does not have a lock for windows_defrag to release
     */
    public function testALockCannotBeReleasedBySomeoneElseThanTheProcessAcquiringIt()
    {
        $this->givenTimeIsFixed();
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-30:23', $this->clock);
        $second->release();
    }

    public function testALockCanBeForcedToBeReleasedIfYouReallyKnowWhatYouReDoing()
    {
        $this->givenTimeIsFixed();
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-30:23', $this->clock);
        $second->release($force = true);
        $this->assertTrue(true, 'make PHPUnit happy');
    }

    public function testALockCanBeShownEvenByOtherProcessesWorkingOnTheSameProgram()
    {
        Phake::when($this->clock)->current()
            ->thenReturn(new DateTime('2014-01-01T00:00:00Z'));
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
            $second->show()
        );
        $this->assertTrue(true, 'make PHPUnit happy');
    }

    public function testALockCanBeWaitedOnUntilItsDisappearance()
    {
        $allCalls = Phake::when($this->clock)->current()
            ->thenReturn(new DateTime('2014-01-01T00:00:00Z'))
            ->thenReturn(new DateTime('2014-01-01T00:00:00Z'))
            ->thenReturn(new DateTime('2014-01-01T00:00:00Z'))
            ->thenReturn(new DateTime('2014-01-01T00:00:30Z'))
            ->thenReturn(new DateTime('2014-01-01T00:01:00Z'));
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire(45);

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock, $this->sleep);
        $second->wait($polling = 30);
        $this->assertEquals([30, 30], $this->slept);
    }

    public function testALockShouldNotBeWaitedUponForever()
    {
        $allCalls = Phake::when($this->clock)->current()
            ->thenReturn(new DateTime('2014-01-01T00:00:00Z'))
            ->thenReturn(new DateTime('2014-01-01T00:00:00Z'))
            ->thenReturn(new DateTime('2014-01-01T00:00:30Z'))
            ->thenReturn(new DateTime('2014-01-01T00:00:50Z'))
            ->thenReturn(new DateTime('2014-01-01T00:01:01Z'))
            ->thenThrow(new \LogicException('Should not call anymore'));
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire(3600);

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock, $this->sleep);
        try {
            $second->wait($polling = 30, $maximumInterval = 60);
            $this->fail('Should fail after 60 seconds');
        } catch (LockNotAvailableException $e) {
            $this->assertEquals(
                'I have been waiting up until 2014-01-01T00:01:00+00:00 for the lock windows_defrag (60 seconds polling every 30 seconds), but it is still not available (now is 2014-01-01T00:01:01+00:00).',
                $e->getMessage()
            );
        }
    }

    public function testALockWaitedUponCanBeImmediatelyReacquired()
    {
        $allCalls = Phake::when($this->clock)->current()
            ->thenReturn(new DateTime('2014-01-01T00:00:00Z'))
            ->thenReturn(new DateTime('2014-01-01T00:00:30Z'))
            ->thenReturn(new DateTime('2014-01-01T00:00:30Z'))
            ->thenReturn(new DateTime('2014-01-01T00:00:30Z'))
            ->thenReturn(new DateTime('2014-01-01T00:00:31Z'))
            ->thenReturn(new DateTime('2014-01-01T00:00:31Z'))
            ;
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire(30);

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock, $this->sleep);
        $second->wait($polling = 1);
        $second->acquire();
        $this->assertTrue(true, 'make PHPUnit happy');
    }

    public function testAnAlreadyAcquiredLockCanBeRefreshed()
    {
        Phake::when($this->clock)->current()
            ->thenReturn(new DateTime('2014-01-01T00:00:00Z'))
            ->thenReturn(new DateTime('2014-01-01T00:10:00Z'));

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
            $second->show()
        );
    }

    /**
     * @expectedException \Onebip\Concurrency\LockNotAvailableException
     * @expectedExceptionMessage ws-a-25:42 cannot acquire a lock for the program windows_defrag
     */
    public function testAnExpiredLockCannotBeRefreshed()
    {
        Phake::when($this->clock)->current()
            ->thenReturn(new DateTime('2014-01-01T00:00:00Z'))
            ->thenReturn(new DateTime('2014-01-01T02:00:00Z'));

        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $second->refresh();
    }

    private function givenTimeIsFixed()
    {
        Phake::when($this->clock)->current()->thenReturn(new DateTime('2014-01-01'));
    }

    /**
     * @group long
     */
    public function testPropertyBased()
    {
        $this->iteration = 0;
        $this
            ->forAll(
                Generator\vector(
                    2,
                    Generator\seq(
                        Generator\elements(['acquire', 'release'])
                        // TODO: add 'sleep'
                    )
                )
            )
            ->when(function ($sequencesOfSteps) {
                foreach ($sequencesOfSteps as $sequence) {
                    if (!$sequence) {
                        return false;
                    }
                }

                return true;
            })
            ->then(function ($sequencesOfSteps) {
                $this->lockCollection->drop();
                $log = "/tmp/mongolock_{$this->iteration}.log";
                if (file_exists($log)) {
                    unlink($log);
                }

                $processes = [];
                foreach ($sequencesOfSteps as $i => $sequence) {
                    $processName = "p{$i}";
                    $steps = implode(',', $sequence);
                    $process = new Process('exec php ' . __DIR__ . "/mongolock.php $processName $steps >> $log");
                    $process->start();
                    $processes[] = $process;
                }
                foreach ($processes as $process) {
                    $process->wait();
                    $this->assertExitedCorrectly($process, 'Error in MongoLock run');
                }
                $process = new Process('exec java -jar ' . __DIR__ . "/knossos-onebip.jar mongo-lock $log");
                $process->run();
                $this->assertExitedCorrectly($process, "Non-linearizable history in $log");
                ++$this->iteration;
            });
    }

    private function assertExitedCorrectly($process, $errorMessage)
    {
        $this->assertEquals(
            0,
            $process->getExitCode(),
            $errorMessage . PHP_EOL .
            $process->getErrorOutput() . PHP_EOL .
            $process->getOutput()
        );
    }
}
