#!/usr/bin/env php
<?php
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '10000');

$arguments = array();

for($i = 1; $i < $argc; $i++)
{
    if(substr($argv[$i], 0, 2) === '--')
    {
        $arguments[substr($argv[$i], 2)] = $argv[$i+1];
        $i++;
    }
}



$app = require __DIR__ . '/../app/app.php';

$fetcher = new \App\Fanstealer\Fetcher($app, $arguments['task-id']);

try {
    $fetcher->run();
} catch(\Exception $e) {
    $fetcher->getTask()->setStatus(\App\Fanstealer\Task::STATUS_ERROR);
    printf('Error occured: %s', $e->getMessage());
    echo $e->getTraceAsString();
}
