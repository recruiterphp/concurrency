<?php

namespace Onebip\Concurrency;

use MongoDB\Collection;

// TODO: converge MongoLockRepository and the HydraLockFactory
class MongoLockRepository
{
    private $collection;

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    // TODO: expose only not expired locks
    public function all()
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
    public function removeAll()
    {
        $this->collection->deleteMany([]);
    }
}
