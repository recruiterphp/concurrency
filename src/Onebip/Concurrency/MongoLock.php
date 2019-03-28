<?php

namespace Onebip\Concurrency;

use DateInterval;
use DateTime;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\Exception\BulkWriteException;
use Onebip\Clock\SystemClock;

class MongoLock implements Lock
{
    const DUPLICATE_KEY = 11000;

    private $collection;
    private $processName;
    private $programName;
    private $clock;
    private $sleep;

    public function __construct(Collection $collection, $programName, $processName, $clock = null, $sleep = 'sleep')
    {
        $this->collection = $collection;
        $this->collection->createIndex(['program' => 1], ['unique' => true]);
        $this->programName = $programName;
        $this->processName = $processName;
        if (null === $clock) {
            $clock = new SystemClock();
        }
        $this->clock = $clock;
        $this->sleep = $sleep;
    }

    public static function forProgram($programName, Collection $collection): self
    {
        return new self($collection, $programName, gethostname() . ':' . getmypid());
    }

    public function acquire($duration = 3600): void
    {
        $now = $this->clock->current();

        $this->removeExpiredLocks($now);

        $expiration = clone $now;
        $expiration->add(new DateInterval("PT{$duration}S"));

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
                throw new LockNotAvailableException(
                    "{$this->processName} cannot acquire a lock for the program {$this->programName}"
                );
            }
            throw $e;
        }
    }

    public function refresh($duration = 3600): void
    {
        $now = $this->clock->current();

        $this->removeExpiredLocks($now);

        $expiration = clone $now;
        $expiration->add(new DateInterval("PT{$duration}S"));

        $result = $this->collection->updateOne(
            ['program' => $this->programName, 'process' => $this->processName],
            ['$set' => ['expires_at' => new UTCDateTime($expiration)]]
        );

        if (!$this->lockRefreshed($result)) {
            throw new LockNotAvailableException(
                "{$this->processName} cannot acquire a lock for the program {$this->programName}, result is: " . var_export($result, true)
            );
        }
    }

    public function show(): ?array
    {
        $document = $this->collection->findOne(
            ['program' => $this->programName],
            ['typeMap' => ['root' => 'array']]
        );

        if (!is_null($document)) {
            $document['acquired_at'] = $this->convertToIso8601String($document['acquired_at']);
            $document['expires_at'] = $this->convertToIso8601String($document['expires_at']);
            unset($document['_id']);
        }

        return $document;
    }

    public function release($force = false): void
    {
        $query = ['program' => $this->programName];
        if (!$force) {
            $query['process'] = $this->processName;
        }
        $operationResult = $this->collection->deleteMany($query);
        if (1 !== $operationResult->getDeletedCount()) {
            throw new LockNotAvailableException(
                "{$this->processName} does not have a lock for {$this->programName} to release"
            );
        }
    }

    /**
     * @param int $polling            how frequently to check the lock presence
     * @param int $maximumWaitingTime a limit to the waiting
     */
    public function wait($polling = 30, $maximumWaitingTime = 3600): void
    {
        $timeLimit = $this->clock->current()->add(new DateInterval("PT{$maximumWaitingTime}S"));
        while (true) {
            $now = $this->clock->current();
            $result = $this->collection->count($query = [
                'program' => $this->programName,
                'expires_at' => ['$gte' => new UTCDateTime($now)],
            ]);

            if ($result) {
                if ($now > $timeLimit) {
                    throw new LockNotAvailableException(
                        "I have been waiting up until {$timeLimit->format(DateTime::ATOM)} for the lock $this->programName ($maximumWaitingTime seconds polling every $polling seconds), but it is still not available (now is {$now->format(DateTime::ATOM)})."
                    );
                }
                call_user_func($this->sleep, $polling);
            } else {
                break;
            }
        }
    }

    public function __toString(): string
    {
        return var_export($this->show(), true);
    }

    private function removeExpiredLocks(DateTime $now): void
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

        return $datetime->format(DateTime::ATOM);
    }

    private function lockRefreshed($result): bool
    {
        if (1 === $result->getModifiedCount()) {
            return true;
        }

        // result is not known (write concern is not set) so we check to see if
        // a lock document exists, if lock document exists we are pretty sure
        // that its update succeded
        return !is_null($this->show());
    }
}
