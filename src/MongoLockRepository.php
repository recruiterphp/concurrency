<?php

declare(strict_types=1);

namespace Recruiter\Concurrency;

use MongoDB\Collection;

// TODO: converge MongoLockRepository and the HydraLockFactory
class MongoLockRepository
{
    public function __construct(private readonly Collection $collection)
    {
    }

    /**
     * @todo expose only not expired locks
     *
     * @return array<int, array{program: string, process: string}>
     */
    public function all(): array
    {
        $result = [];
        foreach ($this->collection->find() as $document) {
            $document = (array) $document;
            $result[] = [
                'program' => $document['program'],
                'process' => $document['process'],
            ];
        }

        return $result;
    }

    /**
     * @private Use only in testing environments
     */
    public function removeAll(): void
    {
        $this->collection->deleteMany([]);
    }
}
