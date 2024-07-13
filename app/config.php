<?php

// Global
$config['debug'] = true;
$config['timer.start'] = $startTime;

// Monolog
$config['monolog.name'] = 'silex-bootstrap';
$config['monolog.level'] = \Monolog\Logger::DEBUG;
$config['monolog.logfile'] = __DIR__ . '/log/app.log';

// Twig
$config['twig.path'] = __DIR__ . '/../src/App/views';
$config['twig.options'] = array(
    'cache' => __DIR__ . '/cache/twig',
);

// Doctrine DBAL
$config['db.options'] = array(
    'driver'   => 'pdo_mysql',
    'host'     => 'localhost',
    'dbname'   => 'fanstealer',
    'user'     => 'fanstealeru',
    'password' => '1234',
    'charset'  => 'utf8',
);

// Facebook
$config['fb.options'] = array(
    'app_id' => '143538529042403',
    'secret' => '56ad95a15929b34cce3b1711b448b125',
    'version'=> 'v2.2',
    'permissions' => array('public_profile')
);

$config['fs.options'] = array(
    /**
     * How often to update status page (time in ms)
     */
    'polling_time' => 2000,

    /**
     * How much posts to fetch at once.
     */
    'batch_posts_size' => 10,

    /**
     * Numbers of user ids processed at once (to get username).
     */
    'process_users_size' => 500,

    /**
     * How much likes or comments to fetch at once.
     */
    'batch_data_size' => 1000,

    /**
     * How many fetchers to run simultaneously (max)
     */
    'max_background_tasks' => 4,

    /**
     * Maximum number of pages which can be added at once
     */
    'max_pages_at_once' => 4,

    /**
     * Path to bin/fetch.php
     */
    'fetcher_path' => __DIR__ . '/../bin/fetch.php',

    /**
     * Where to save .csv files
     */
    'csv_path' => __DIR__ . '/../csv',
);

return $config;
