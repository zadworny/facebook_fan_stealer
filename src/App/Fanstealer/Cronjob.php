<?php

namespace App\Fanstealer;

use App\Silex\Application;
use Doctrine\DBAL\Connection;

class Cronjob {

    /** @var Application */
    private $app;

    /** @var Connection */
    private $db;


    public function __construct(Application $app)
    {
        $this->app = $app;

        $this->db = $this->app['db'];
    }

    public function run()
    {
        $tasks = $this->fetchTasks();
        $this->log(sprintf('Fetched %d tasks', count($tasks)));

        $i = 0;

        /** @var Task $task */
        foreach($tasks as $task)
        {
            if($task instanceof Task)
            {
                $this->log(sprintf('Starting task #%d', $task->getId()));
                $task->run();
                $i++;

                if($i >= $this->app['fs.options']['max_background_tasks'])
                    break;
            }
        }
    }

    private function fetchTasks()
    {
        $data = $this->db->fetchAll('SELECT *
                                   FROM tasks
                                   WHERE status IN (-1,0) OR (status > 0 AND pid > 0)
                                   GROUP BY fb_user_id
                                   ORDER BY pid DESC, created_at ASC');

        $tasks = array();
        $now = new \DateTime();

        foreach($data as $task)
        {
            $t = new Task($this->app, $task);
            if($t->getWaitUntil() < $now && ($t->getPid() == -1 || ! $t->isRunning()))
                $tasks[] = $t;
        }

        return $tasks;
    }

    private function log($message)
    {
        if($this->app['debug'])
            printf("%s :: CRON :: %s\n", date('Y-m-d H:i:s'), $message);
    }

}
