<?php

namespace App\Fanstealer;

use App\Silex\Application;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Facebook\Entities\AccessToken;
use Facebook\FacebookRequest;
use Facebook\FacebookResponse;
use Facebook\FacebookSession;

class Fetcher {

    /** @var Application */
    private $app;

    /** @var Connection */
    private $db;

    /** @var Task */
    private $task;

    /** @var array */
    private $users;

    /** @var FacebookSession */
    private $fbSession;


    /**
     * @param Application   $app
     * @param int           $taskId
     */
    public function __construct(Application $app, $taskId)
    {
        $this->app = $app;
        $this->db  = $this->app['db'];
        $this->users = array();

        $this->task = new Task($this->app,
            $this->db->fetchAssoc('SELECT * FROM tasks WHERE id = ?', array($taskId)));

        $this->initFacebook();

        $this->log('START');
    }

    public function __destruct()
    {
        $this->log('END');
    }

    /**
     * @return Task
     */
    public function getTask()
    {
        return $this->task;
    }

    public function run()
    {
        $this->db->setFetchMode(\PDO::FETCH_ASSOC);
        $dbPosts = $this->db->fetchAll('SELECT post_id FROM posts WHERE task_id = ? AND done = 0', array($this->task->getId()));

        if(count($dbPosts) > 0)
        {
            $this->log('Found ' . count($dbPosts) . ' to be processed posts saved in db');
            $postsIds = array();
            foreach($dbPosts as $post)
                $postsIds[] = $post['post_id'];
        }
        else
        {
            $this->log('Starting count posts...');
            $postsIds = $this->getPostsIds();
            $this->log('Found ' . count($postsIds) . ' posts');
            $this->savePostIds($postsIds);
        }

        $this->task->setPostsCount(count($postsIds));
        $this->task->setStatus(Task::STATUS_PROCESSING);

        $this->log('Set status to PROCESSING');

        try {
            $this->getUsersFromPosts($postsIds);
        } catch (\Exception $e) {
            $this->log('Exception was thrown. Saving emails (details after that)');

            $emails = $this->getEmailsFromUsers();
            $emailsSaved = $this->saveCsv($emails);

            $this->task->setEmailsCount($emailsSaved);

            $nextRun = new \DateTime();
            $nextRun->add(new \DateInterval('PT10M'));

            $this->task->setWaitUntil($nextRun);

            $this->log('Will wait until ' . $nextRun->format('Y-m-d H:i:s'));

            $this->task->save();

            throw $e;
        }

        $this->task->setStatus(Task::STATUS_FINISHING);
        $this->log('Set status to FINISHING');

        $emails = $this->getEmailsFromUsers();
        $emailsSaved = $this->saveCsv($emails);

        $this->task->finish($emailsSaved);
        $this->db->delete('posts', array('task_id' => $this->task->getId()));
    }

    private function saveCsv(array $emails)
    {
        $csvFilename = $this->task->getId() . '.csv';

        if(!is_dir($this->app['fs.options']['csv_path']))
        {
            mkdir($this->app['fs.options']['csv_path'], 0755, true);
        }

        $filepath = $this->app['fs.options']['csv_path'] . '/' . $csvFilename;

        if(file_exists($filepath))
            $emails = array_merge($emails, file($filepath, FILE_IGNORE_NEW_LINES));

        $emails = array_unique($emails);

        $csv = fopen($filepath, 'w');

        foreach($emails as $email)
        {
            fputcsv($csv, array($email));
        }

        fclose($csv);

        $this->log(sprintf('Saved %d emails to %s', count($emails), $csvFilename));

        return count($emails);
    }

    private function getEmailsFromUsers()
    {
        $data = array();

        $params = array(
            'fields=username',
            //'access_token=' . $this->task->getAccessToken(),
        );

        $usersCnt = count($this->users);

        $this->log('Start getting emails from ' . $usersCnt . ' users');

        for($i = 0; $i < $usersCnt; $i += $this->app['fs.options']['process_users_size'])
        {
            $portion = array_slice($this->users, $i, $this->app['fs.options']['process_users_size']);
            $this->log('Processing ' . count($portion) . ' users (' . $i . ' / ' . $usersCnt . ')');
            $ids = implode($portion, ',');

            $path = sprintf('/?ids=%s&%s', $ids, implode($params, '&'));
            //$response = json_decode(file_get_contents('https://graph.facebook.com/v1.0' . $path), true);
            $response = json_decode($this->curl('https://graph.facebook.com/v1.0' . $path), true);

            $emailsCnt = 0;
            foreach($response as $row)
            {
                if(isset($row['username']))
                {
                    $data[] = $row['username'];
                    $emailsCnt++;
                }
            }
            $this->log('Added ' . $emailsCnt . ' emails');
        }

        return array_map(function($x) {
            return $x . '@facebook.com';
        }, $data);
    }

    private function getAll($row, $type)
    {
        if (isset($row['data'])) {
            foreach ($row['data'] as $entry) {
                if ($type === 'likes') {
                    $this->users[] = $entry['id'];
                } elseif ($type === 'comments') {
                    $this->users[] = $entry['from']['id'];
                }
            }
            $this->log('Fetching ' . $type . ' initiated with ' . count($row['data']) . ' users');
        }

        if (isset($row['paging']['next']))
        {
            $url = $row['paging']['next'];

            while ($url)
            {
                $response = json_decode($this->curl($url), true);

                if (isset($response['error']))
                {
                    throw new \Exception(sprintf('%s (%s)', $response['error']['message'], $response['error']['type']));
                }

                if (isset($response['data']))
                {
                    foreach ($response['data'] as $entry)
                    {
                        if ($type === 'likes')
                            $this->users[] = $entry['id'];
                        elseif ($type === 'comments')
                            $this->users[] = $entry['from']['id'];
                    }
                    $this->users = array_unique($this->users);
                    $this->log('Added ' . count($response['data']) . ' users / total ' . count($this->users) . ' unique');
                }

                if (isset($response['paging']['next']))
                    $url = $response['paging']['next'];
                else
                    $url = null;
            }
        }
    }

    private function getUsersFromPosts(array $posts)
    {
        $fields = array(
            'id',
            'created_time',
            'likes.limit('. $this->app['fs.options']['batch_data_size'] .'){id}',
            'comments.limit('. $this->app['fs.options']['batch_data_size'] .'){from{id}}'
        );

        $params = array(
            'fields=' . implode($fields, ','),
            'limit=' . $this->app['fs.options']['batch_posts_size'],
            'access_token=' . $this->task->getAccessToken(),
        );

        $postsCnt = count($posts);
        $processedCnt = 0;

        for($i = 0; $i < $postsCnt; $i += $this->app['fs.options']['batch_posts_size'])
        {
            $portion = array_slice($posts, $i, $this->app['fs.options']['batch_posts_size']);
            $this->log('Processing ' . count($portion) . ' posts (' . $i . ' / ' . $postsCnt . ')');
            $ids = implode($portion, ',');

            $path = sprintf('/?ids=%s&%s', $ids, implode($params, '&'));
            $response = json_decode($this->curl('https://graph.facebook.com/v2.2' . $path), true);

            if(isset($response['error']))
            {
                throw new \Exception(sprintf('When getting users from posts: %s', implode(' / ', $response['error'])));
            }
            elseif(isset($response['error_code']))
            {
                throw new \Exception(sprintf('When getting users from posts: %s (%s)', $response['error_msg'], $response['error_code']));
            }

            foreach($response as $row)
            {
                if(isset($row['likes']))
                {
                    $this->getAll($row['likes'], 'likes');
                }

                if(isset($row['comments']))
                {
                    $this->getAll($row['comments'], 'comments');
                }

                $processedCnt++;

                /*if($processedCnt == 1) {
                    $this->log('/!\ /!\ /!\ firing test exception');
                    throw new \Exception('test exception');
                }*/

                // Update database on every post (bad idea, but...)
                $this->task->setPostsProcessedCount($processedCnt);
                $this->db->update('posts', array('done' => 1), array('post_id' => $row['id'], 'task_id' => $this->task->getId()));
            }
        }
    }

    private function getPostsIds()
    {
        $fields = array(
            'id',
            'created_time',
        );

        $params = array(
            'since=' . $this->task->getIntervalFrom()->format('U'),
            //'until=' . $this->task->getIntervalTo()->format('U'), // goes into loop if this is uncommented
            'date_format=U',
            'fields=' . implode($fields, ','),
            'limit=' . $this->app['fs.options']['batch_posts_size'],
        );

        $path = sprintf('/%s/feed?%s', $this->task->getPageId(), implode($params, '&'));

        $request = new FacebookRequest($this->fbSession, 'GET', $path, array(
            'access_token' => $this->task->getAppId() . '|' . $this->task->getSecret(),
        ));

        $dateTo = $this->task->getIntervalTo()->format('U');

        $ids = array();

        $this->log(sprintf('Between %s and %s', $this->task->getIntervalFrom()->format('Y-m-d H:is'),
            $this->task->getIntervalTo()->format('Y-m-d H:i:s')));

        while($request)
        {
            /** @var FacebookResponse $response */
            $response = $request->execute();
            $responseArray = json_decode($response->getRawResponse(), true);

            if( ! isset($responseArray['data']))
            {
                break;
            }

            // check for each post is needed, because if `until` is set in initial request,
            // it goes into loop (next until goes back and forth)
            foreach($responseArray['data'] as $page)
            {
                if($page['created_time'] > $dateTo)
                    continue;

                $ids[] = $page['id'];
            }

            $request = $response->getRequestForNextPage();
        }

        return $ids;
    }

    private function savePostIds(array $data)
    {
        if(count($data) < 1)
            return;

        $query = 'INSERT INTO posts (post_id, task_id) VALUES ';
        $inserts = array();

        foreach($data as $postId)
        {
            $inserts[] = '("' . $postId . '", ' . $this->task->getId() . ')';
        }

        $query .= implode(',', $inserts);

        $this->db->executeQuery($query);
    }

    private function initFacebook()
    {
        if($this->task->getAppId() && $this->task->getSecret())
        {
            FacebookSession::setDefaultApplication($this->task->getAppId(), $this->task->getSecret());
        }
        else
        {
            $this->task->setAppId($this->app['fb.options']['app_id']);
            $this->task->setSecret($this->app['fb.options']['secret']);
        }

        $code = AccessToken::getCodeFromAccessToken($this->task->getAccessToken());
        $accessToken = AccessToken::getAccessTokenFromCode($code);

        $this->fbSession = new FacebookSession($accessToken);
    }

    private function curl($url)
    {
        $curl_handle=curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $url);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
        curl_setopt($curl_handle, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:36.0) Gecko/20100101 Firefox/36.0');
        $query = curl_exec($curl_handle);
        $error = curl_error($curl_handle);
        curl_close($curl_handle);

        if($query === false)
            throw new \Exception(sprintf('CURL Error on "%s" (%s)', $url, $error));

        return $query;
    }

    private function log($message)
    {
        if($this->app['debug'])
            printf("%s :: %5d :: %s\n", date('Y-m-d H:i:s'), $this->task->getId(), $message);
    }
}
