<?php

use Onebip\Concurrency\LockNotAvailableException;
use Onebip\Concurrency\MongoLock;

require __DIR__ . '/../../../vendor/autoload.php';

if (count($argv) < 3) {
    fwrite(STDERR, 'Usage: php ' . __FILE__ . ' <PROCESS_NAME> <ACTIONS>' . PHP_EOL);
    fwrite(STDERR, "ACTIONS are: 'acquire,release,acquire,...'" . PHP_EOL);
    exit(-1);
}
$name = "p{$argv[1]}";
if (!$argv[2]) {
    fwrite(STDERR, 'ACTIONS must be not empty' . PHP_EOL);
    exit(-2);
}
$operations = explode(',', $argv[2]);

$lockCollection = (new MongoDB\Client())->test->lock;
$lock = new MongoLock($lockCollection, 'ilium_gate', $name);
$log = function ($data) {
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
