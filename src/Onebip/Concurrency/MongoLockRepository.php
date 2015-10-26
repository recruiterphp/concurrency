<?php
namespace Onebip\Concurrency;
use MongoCollection;

// TODO: converge MongoLockRepository and the HydraLockFactory
class MongoLockRepository 
{
    private $collection;

    public function __construct(MongoCollection $collection)
    {
        $this->collection = $collection;
    }

    // TODO: expose only not expired locks
    public function all()
    {
        $result = [];
        foreach ($this->collection->find() as $document) {
            $result[] = [
                'program' => $document['program'],
                'process' => $document['process'],
            ];
        }
        return $result;
    }

    /**
     * @private Use only in testing environments
     * @return void
     */
    public function removeAll()
    {
        $this->collection->remove();
    }
}
