<?php
use Onebip\Concurrency\MongoLock;
use Onebip\Concurrency\LockNotAvailableException;
require __DIR__ . '/../../../vendor/autoload.php';

$name = "p{$argv[1]}";
$operations = explode(',', $argv[2]);

$lockCollection = (new MongoClient())->test->lock;
$lock = new MongoLock($lockCollection, 'ilium_gate', $name);
$log = function($data) {
    fputcsv(
        STDOUT,
        array_merge(
            [
                'time' => (int) (microtime(true) * 1000000),
            ],
            $data
        )
    );
};
foreach ($operations as $operation) {
    $log([
        'process' => $name,
        'type' => 'invoke',
        'f' => $operation,
    ]);
    try {
        $lock->$operation();
        $type = 'ok';
    } catch (LockNotAvailableException $e) {
        $type = 'fail';
    }
    $log([
        'process' => $name,
        'type' => $type,
        'f' => $operation,
    ]);
}
