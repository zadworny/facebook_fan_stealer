<?php

namespace App\Fanstealer;

use App\Silex\Utils;
use Doctrine\DBAL\Connection;
use Silex\Application;

class Task {

    const STATUS_ERROR      = -1;
    const STATUS_AWAITING   = 0;
    const STATUS_PREPARING  = 1;
    const STATUS_PROCESSING = 2;
    const STATUS_FINISHING  = 3;
    const STATUS_RETRY      = 10;

    /** @var Application */
    private $app;

    /** @var Connection */
    private $db;

    /** @var int */
    private $id;

    /** @var string */
    private $pageId;

    /** @var string */
    private $fbUserId;

    /** @var \DateTime */
    private $intervalFrom;

    /** @var \DateTime */
    private $intervalTo;

    /** @var string */
    private $accessToken;

    /** @var string|null */
    private $appId;

    /** @var string|null */
    private $secret;

    /** @var int */
    private $postsCount;

    /** @var int */
    private $postsProcessedCount;

    /** @var int */
    private $emailsCount;

    /** @var \DateTime */
    private $createdAt;

    /** @var \DateTime */
    private $waitUntil;

    /** @var int */
    private $status;

    /** @var int */
    private $pid;


    public function __construct(Application $app, array $data)
    {
        $this->app = $app;
        $this->db  = $this->app['db'];

        $this->id           = $data['id'];
        $this->pageId       = $data['page_id'];
        $this->fbUserId     = $data['fb_user_id'];
        $this->intervalFrom = new \DateTime($data['interval_from']);
        $this->intervalTo   = new \DateTime($data['interval_to']);
        $this->accessToken  = $data['access_token'];
        $this->appId        = $data['app_id'];
        $this->secret       = $data['secret'];
        $this->postsCount   = $data['posts_count'];
        $this->postsProcessedCount = $data['posts_processed_count'];
        $this->emailsCount  = $data['emails_count'];
        $this->createdAt    = new \DateTime($data['created_at']);
        $this->waitUntil    = new \DateTime($data['wait_until']);
        $this->status       = $data['status'];
        $this->pid          = $data['pid'];
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getPageId()
    {
        return $this->pageId;
    }

    /**
     * @return string
     */
    public function getFbUserId()
    {
        return $this->fbUserId;
    }

    /**
     * @return \DateTime
     */
    public function getIntervalFrom()
    {
        return $this->intervalFrom;
    }

    /**
     * @return \DateTime
     */
    public function getIntervalTo()
    {
        return $this->intervalTo;
    }

    /**
     * @return string
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @return null|string
     */
    public function getAppId()
    {
        return $this->appId;
    }

    /**
     * @param null|string $appId
     */
    public function setAppId($appId)
    {
        $this->appId = $appId;
    }

    /**
     * @return null|string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * @param null|string $secret
     */
    public function setSecret($secret)
    {
        $this->secret = $secret;
    }

    /**
     * @return int
     */
    public function getPostsCount()
    {
        return $this->postsCount;
    }

    /**
     * @param int $postsCount
     */
    public function setPostsCount($postsCount)
    {
        $this->postsCount = $postsCount;

        $this->db->update('tasks', array(
            'posts_count' => $postsCount,
        ), array('id' => $this->id));
    }

    /**
     * @return int
     */
    public function getPostsProcessedCount()
    {
        return $this->postsProcessedCount;
    }

    /**
     * @param int $postsProcessedCount
     */
    public function setPostsProcessedCount($postsProcessedCount)
    {
        $this->postsProcessedCount = $postsProcessedCount;

        $this->db->update('tasks', array(
            'posts_processed_count' => $postsProcessedCount,
        ), array('id' => $this->id));
    }

    /**
     * @return int
     */
    public function getEmailsCount()
    {
        return $this->emailsCount;
    }

    /**
     * @param int $emailsCount
     */
    public function setEmailsCount($emailsCount)
    {
        $this->emailsCount = $emailsCount;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getWaitUntil()
    {
        return $this->waitUntil;
    }

    /**
     * @param \DateTime $waitUntil
     */
    public function setWaitUntil($waitUntil)
    {
        $this->waitUntil = $waitUntil;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status, $updateDb = true)
    {
        if(!in_array($status, array(
            self::STATUS_PREPARING,
            self::STATUS_AWAITING,
            self::STATUS_PROCESSING,
            self::STATUS_ERROR,
            self::STATUS_FINISHING,
            self::STATUS_RETRY
        )))
        {
            throw new \Exception('Invalid Task status');
        }

        $this->status = $status;

        if($updateDb)
            $this->db->update('tasks', array('status' => $this->status), array('id' => $this->id));
    }

    /**
     * @return int
     */
    public function getPid()
    {
        return $this->pid;
    }

    public function isRunning()
    {
        try {
            $result = shell_exec(sprintf('ps %d', $this->pid));
            if(count(preg_split("/\n/", $result)) > 2)
            {
                return true;
            }
        } catch(\Exception $e) {
        }

        return false;
    }

    /**
     * @return void
     * @throws \Exception  Thrown if attempt to run task under Windows environment
     */
    public function run()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        {
            throw new \Exception('Running tasks under Windows is not supported');
        }

        $cmd = $this->app['fs.options']['fetcher_path'];

        $cmd .= ' --task-id ' . $this->id;

        exec(sprintf("%s >> %s 2>&1 & echo $!", $cmd, realpath(__DIR__ . '/../../../app/log') . '/fetcher.log'), $pidArr);

        if(!isset($pidArr[0]))
        {
            throw new \Exception('Could not start process');
        }

        $this->pid = $pidArr[0];

        $this->db->update('tasks', array(
            'pid' => $this->pid,
            'status' => Task::STATUS_PREPARING,
            'posts_count' => 0,
            'posts_processed_count' => 0,
        ), array('id' => $this->id));
    }

    public function finish($emailsCount = 0)
    {
        $now = new \DateTime();

        $this->db->insert('tasks_done', array(
            'id'          => $this->id,
            'page_id'     => $this->pageId,
            'fb_user_id'  => $this->fbUserId,
            'interval_from' => $this->intervalFrom,
            'interval_to' => $this->intervalTo,
            'emails_count' => $emailsCount,
            'created_at'  => $this->createdAt,
            'finished_at' => $now,
        ), array(
            \PDO::PARAM_INT, //id
            \PDO::PARAM_STR, //page_idid
            \PDO::PARAM_STR, //fb_user_id
            'datetime',      //from
            'datetime',      //to
            \PDO::PARAM_INT, //posts_count
            'datetime',      //created_at
            'datetime',      //finished_at
        ));

        $this->db->delete('tasks', array('id' => $this->id));
    }

    public function save()
    {
        $this->db->update('tasks', array(
            'emails_count' => $this->emailsCount,
            'wait_until' => $this->waitUntil,
            'status' => $this->status,
        ), array('id' => $this->id), array(
            \PDO::PARAM_INT,
            'datetime',
            \PDO::PARAM_INT,
        ));
    }
}
