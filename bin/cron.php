#!/usr/bin/env php
<?php
/*
$arguments = array();

for($i = 1; $i < $argc; $i++)
{
    if(substr($argv[$i], 0, 2) === '--')
    {
        $arguments[substr($argv[$i], 2)] = $argv[$i+1];
        $i++;
    }
}
*/

$app = require __DIR__ . '/../app/app.php';

$cron = new \App\Fanstealer\Cronjob($app);

try {
    $cron->run();
} catch(\Exception $e) {
    //printf('Error occured: %s', $e->getMessage());
    echo $e->getTraceAsString();
}
