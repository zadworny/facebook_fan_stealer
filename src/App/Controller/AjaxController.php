<?php

namespace App\Controller;

use App\Silex\Application;
use App\Silex\Controller;
use Facebook\FacebookRequest;
use Facebook\FacebookSession;

class AjaxController extends Controller {

    public function __construct(Application $app)
    {
        parent::__construct($app);

        header('Content-Type: application/json');

        if( !isset($_SESSION['fb.access_token']) || !isset($this->app['fb.user']))
        {
            return $this->jsonResponse(array(
                'error' => 'Unauthorized',
            ), 403);
        }
    }

    public function addTasksAction()
    {
        if( ! isset($_SESSION['fb.access_token']))
        {
            return $this->jsonResponse(array(
                'error' => 'Unauthorized access',
            ), 403);
        }

        $now = new \DateTime();

        $ids = explode(';', $_POST['ids']);

        if(count($ids) > $this->app['fs.options']['max_pages_at_once'])
        {
            return $this->jsonResponse(array(
                'error' => 'You can add up to '. $this->app['fs.options']['max_pages_at_once'] .' fanpages per session.',
            ), 500);
        }

        $day = new \DateInterval('P1D');
        $utc = new \DateTimeZone('UTC');

        $intervalFrom = new \DateTime($_POST['date_from'], $utc);
        $intervalFrom->add($day);
        $intervalFrom->setTime(0,0,0);
        $intervalTo   = new \DateTime($_POST['date_to'], $utc);
        $intervalTo->add($day);
        $intervalTo->setTime(23,59,59);

        foreach($ids as $id)
        {
            $this->db->insert('tasks', array(
                'page_id'       => $id,
                'fb_user_id'    => $this->app['fb.user']['id'],
                'interval_from' => $intervalFrom,
                'interval_to'   => $intervalTo,
                'access_token'  => $_SESSION['fb.access_token'],
                'app_id'        => ($_SESSION['selected_app'] == 'myapp' && isset($_SESSION['myapp_app_id']) ? $_SESSION['myapp_app_id'] : ''),
                'secret'        => ($_SESSION['selected_app'] == 'myapp' && isset($_SESSION['myapp_secret']) ? $_SESSION['myapp_secret'] : ''),
                'created_at'    => $now,
                'wait_until'    => $now,
            ), array(
                \PDO::PARAM_STR, //id
                \PDO::PARAM_STR, //fb_user_id
                'datetime',      //from
                'datetime',      //to
                \PDO::PARAM_STR, //access_token
                \PDO::PARAM_STR, //app_id
                \PDO::PARAM_STR, //secret
                'datetime',      //created_at
                'datetime',      //wait_until
            ));
        }

        return $this->jsonResponse(array(
            'message' => 'OK',
        ), 200);
    }

    public function deleteTaskAction()
    {
        if( ! isset($_SESSION['fb.access_token']))
        {
            return $this->jsonResponse(array(
                'error' => 'Unauthorized',
            ), 403);
        }


        $id = $_POST['id'];

        $task = $this->db->fetchAssoc('SELECT * FROM tasks WHERE id = ?', array($id));
        $isDone = false;

        if( ! $task)
        {
            $task = $this->db->fetchAssoc('SELECT * FROM tasks_done WHERE id = ?', array($id));
            $isDone = true;
        }

        if( ! $task || $task['fb_user_id'] !== $this->app['fb.user']['id'])
        {
            return $this->jsonResponse(array(
                'error' => 'Bad task',
            ), 403);
        }

        if( ! $isDone)
        {
            $this->db->delete('tasks', array('id' => $id));
            $this->db->delete('posts', array('task_id' => $id));
        }
        else
        {
            $this->db->delete('tasks_done', array('id' => $id));
            $file = realpath($this->app['fs.options']['csv_path'] . '/' . $id . '.csv');

            if($file && file_exists($file) && is_writable($file))
            {
                unlink($file);
            }
        }


        return $this->jsonResponse(array(
            'message' => 'OK',
        ), 200);
    }

    public function searchPageAction()
    {
        if(empty($_GET['name']))
        {
            return $this->jsonResponse(array(
                'error' => 'No query provided',
            ), 500);
        }

        if(!isset($this->app['fb.session']))
            $fbSession = FacebookSession::newAppSession($this->app['fb.options']['app_id'], $this->app['fb.options']['secret']);
        else
            $fbSession = $this->app['fb.session'];

        $request = new FacebookRequest($fbSession, 'GET', '/search?q=' . $_GET['name'] . '&type=page&limit=5&fields=id,category,name,picture,likes');
        $response = json_decode($request->execute()->getRawResponse(), true);

        $pages = array();

        if(isset($response['data']))
        {
            foreach($response['data'] as $page)
            {
                $pages[] = array(
                    'id' => $page['id'],
                    'likes' => number_format($page['likes'], 0, '.', '.'),
                    'picture' => $page['picture']['data']['url'],
                    'name' => $page['name'],
                    'category' => $page['category'],
                );
            }
        }

        return $this->jsonResponse($pages, 200);
    }

    public function statusAction()
    {
        if(!isset($_SESSION['fb.user_id']))
            return $this->jsonResponse(array(
                'error' => 'Unauthorized access',
            ), 403);

        $tasksAwaiting = $this->db->fetchAll('SELECT id, page_id, posts_count, posts_processed_count, emails_count, status FROM tasks WHERE fb_user_id = ?',
            array($_SESSION['fb.user_id']));

        $tasksDone = $this->db->fetchAll('SELECT id, page_id, emails_count FROM tasks_done WHERE fb_user_id = ?',
            array($_SESSION['fb.user_id']));

        return $this->jsonResponse(array_merge($tasksDone, $tasksAwaiting), 200);
    }

    public function selectAppAction()
    {
        if(!in_array($_POST['value'], array('default', 'myapp')))
        {
            return $this->jsonResponse(array(
                'error' => 'Unknown type',
            ), 500);
        }

        $_SESSION['selected_app'] = $_POST['value'];

        return $this->jsonResponse(array(
            'message' => 'OK',
        ), 200);
    }

    public function appSettingsAction()
    {
        $_SESSION['myapp_app_id'] = strip_tags($_POST['app_id']);
        $_SESSION['myapp_secret'] = strip_tags($_POST['secret']);

        return $this->jsonResponse(array(
            'message' => 'OK',
        ), 200);
    }

    private function jsonResponse(array $data, $code = 200)
    {
        http_response_code($code);

        return json_encode(array(
            'code' => $code,
            'data' => $data,
        ));
    }
}
