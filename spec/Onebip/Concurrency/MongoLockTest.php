<?php
namespace Onebip\Concurrency;
use DateTime;
use Phake;
use MongoClient;

class MongoLockTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->lockCollection = (new MongoClient())->test->lock;
        $this->clock = Phake::mock('Onebip\Clock');

        $this->slept = [];
        $this->sleep = function($amount) { 
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
    }

    /**
     * @expectedException Onebip\Concurrency\LockNotAvailableException
     * @expectedExceptionMessage ws-a-30:23 cannot acquire a lock for the program windows_defrag
     */
    public function testAnAlreadyAcquiredLockCannotBeAcquiredAgain()
    {
        $this->givenTimeIsFixed();
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-30:23', $this->clock);
        $second->acquire();
    }

    public function testAnAlreadyAcquiredLockCanExpireSoThatItCanBeAcquiredAgain()
    {
        Phake::when($this->clock)->current()
            ->thenReturn(new DateTime('2014-01-01T10:00:00'))
            ->thenReturn(new DateTime('2014-01-01T11:00:01'))
        ;
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire(3600);

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-30:23', $this->clock);
        $second->acquire(3600);
    }

    public function testLockForDifferentProgramsDoNotInterfereWithEachOther()
    {
        $this->givenTimeIsFixed();
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();

        $second = new MongoLock($this->lockCollection, 'sll_world_domination', 'ws-a-30:23', $this->clock);
        $second->acquire();
    }

    public function testLocksCanBeReleasedToMakeThemAvailableAgain()
    {
        $this->givenTimeIsFixed();
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire();
        $first->release();

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-30:23', $this->clock);
        $second->acquire();
    }

    /**
     * @expectedException Onebip\Concurrency\LockNotAvailableException
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
                'acquired_at' => '2014-01-01T00:00:00+0000',
                'expires_at' => '2014-01-01T01:00:00+0000',
            ],
            $second->show()
        );
    }

    public function testALockCanBeWaitedOnUntilItsDisappearance()
    {
        $allCalls = Phake::when($this->clock)->current()
            ->thenReturn(new DateTime('2014-01-01T00:00:00Z'))
            ->thenReturn(new DateTime('2014-01-01T00:00:00'))
            ->thenReturn(new DateTime('2014-01-01T00:00:00'))
            ->thenReturn(new DateTime('2014-01-01T00:00:30'))
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
            ->thenReturn(new DateTime('2014-01-01T00:00:00'))
            ->thenReturn(new DateTime('2014-01-01T00:00:30'))
            ->thenReturn(new DateTime('2014-01-01T00:01:00Z'));
        $first = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock);
        $first->acquire(3600);

        $second = new MongoLock($this->lockCollection, 'windows_defrag', 'ws-a-25:42', $this->clock, $this->sleep);
        try {
            $second->wait($polling = 30, $maximumInterval = 60);
            $this->fail("Should fail after 60 seconds");
        } catch (LockNotAvailableException $e) {
            $this->assertEquals(
                "I have been waiting up until 2014-01-01T00:01:00+0100 for the lock windows_defrag (60 seconds), but it is still not available.", 
                $e->getMessage()
            );
        }
        
    }

    private function givenTimeIsFixed()
    {
        Phake::when($this->clock)->current()->thenReturn(new DateTime('2014-01-01'));
    }
}
