<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\UpdateResult;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

class MongoLock implements Lock
{
    private const int DUPLICATE_KEY = 11000;
    private readonly ClockInterface $clock;

    public function __construct(
        private readonly Collection $collection,
        private readonly string $programName,
        private readonly string $processName,
        ?ClockInterface $clock = null
    ) {
        $this->collection->createIndex(['program' => 1], ['unique' => true]);
        $this->clock = $clock ?? new NativeClock();
    }

    public static function forProgram(string $programName, Collection $collection): self
    {
        return new self($collection, $programName, gethostname() . ':' . getmypid());
    }

    public function acquire(int $duration = 3600): void
    {
        $now = $this->clock->now();

        $this->removeExpiredLocks($now);

        $expiration = $now->add(new \DateInterval("PT{$duration}S"));

        try {
            $document = [
                'program' => $this->programName,
                'process' => $this->processName,
                'acquired_at' => new UTCDateTime($now),
                'expires_at' => new UTCDateTime($expiration),
            ];
            $this->collection->insertOne($document);
        } catch (BulkWriteException $e) {
            if (self::DUPLICATE_KEY == $e->getCode()) {
                throw new LockNotAvailableException("{$this->processName} cannot acquire a lock for the program {$this->programName}");
            }
            throw $e;
        }
    }

    public function refresh(int $duration = 3600): void
    {
        $now = $this->clock->now();

        $this->removeExpiredLocks($now);

        $expiration = $now->add(new \DateInterval("PT{$duration}S"));

        $result = $this->collection->updateOne(
            ['program' => $this->programName, 'process' => $this->processName],
            ['$set' => ['expires_at' => new UTCDateTime($expiration)]],
        );

        if (!$this->lockRefreshed($result)) {
            throw new LockNotAvailableException("{$this->processName} cannot acquire a lock for the program {$this->programName}, result is: " . var_export($result, true));
        }
    }

    public function show(): ?array
    {
        $document = $this->collection->findOne(
            ['program' => $this->programName, 'process' => $this->processName],
            ['typeMap' => ['root' => 'array']],
        );

        if (null === $document) {
            return null;
        }

        assert(is_array($document));
        assert(isset($document['acquired_at'], $document['expires_at']));
        $document['acquired_at'] = $this->convertToIso8601String($document['acquired_at']);
        $document['expires_at'] = $this->convertToIso8601String($document['expires_at']);
        unset($document['_id']);

        return $document;
    }

    public function release(bool $force = false): void
    {
        $query = ['program' => $this->programName];
        if (!$force) {
            $query['process'] = $this->processName;
        }
        $operationResult = $this->collection->deleteMany($query);
        if (1 !== $operationResult->getDeletedCount()) {
            throw new LockNotAvailableException("{$this->processName} does not have a lock for {$this->programName} to release");
        }
    }

    /**
     * @param int $polling            how frequently to check the lock presence
     * @param int $maximumWaitingTime a limit to the waiting
     */
    public function wait(int $polling = 30, int $maximumWaitingTime = 3600): void
    {
        $timeLimit = $this->clock->now()->add(new \DateInterval("PT{$maximumWaitingTime}S"));
        while (true) {
            $now = $this->clock->now();
            $result = $this->collection->count($query = [
                'program' => $this->programName,
                'expires_at' => ['$gte' => new UTCDateTime($now)],
            ]);

            if ($result !== 0) {
                if ($now > $timeLimit) {
                    throw new LockNotAvailableException("I have been waiting up until {$timeLimit->format(\DateTime::ATOM)} for the lock $this->programName ($maximumWaitingTime seconds polling every $polling seconds), but it is still not available (now is {$now->format(\DateTime::ATOM)}).");
                }
                $this->clock->sleep($polling);
            } else {
                break;
            }
        }
    }

    public function __toString(): string
    {
        return var_export($this->show(), true);
    }

    private function removeExpiredLocks(\DateTimeImmutable $now): void
    {
        $this->collection->deleteMany($query = [
            'program' => $this->programName,
            'expires_at' => [
                '$lt' => new UTCDateTime($now),
            ],
        ]);
    }

    private function convertToIso8601String(UTCDateTime $mongoDateTime): string
    {
        $datetime = $mongoDateTime->toDateTime();

        return $datetime->format(\DateTime::ATOM);
    }

    private function lockRefreshed(UpdateResult $result): bool
    {
        return 1 === $result->getModifiedCount();
    }
}
