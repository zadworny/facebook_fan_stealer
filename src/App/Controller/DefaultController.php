<?php

namespace App\Controller;

use App\Silex\Application;
use App\Silex\Controller;
use App\Silex\Utils;
use Facebook\FacebookRequest;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class DefaultController extends Controller {

    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function indexAction()
    {
        $dateTo = new \DateTime('now');
        $dateFrom = clone $dateTo;

        $dateFrom->sub(new \DateInterval('P1M'));

        if($dateFrom->format('j') != $dateTo->format('j'))
        {
            $dateFrom->sub(new \DateInterval('P' . $dateFrom->format('j') . 'D'));
        }

        return $this->app['twig']->render('index.html.twig', array(
            'from' => $dateFrom,
            'to'   => $dateTo,
        ));
    }

    public function statusAction()
    {
        if(!isset($this->app['fb.user']))
        {
            return new RedirectResponse($this->generateUrl('home'));
        }

        $tasksAwaiting = $this->db->fetchAll('SELECT id, page_id, emails_count, posts_count, posts_processed_count, status
                                              FROM tasks
                                              WHERE fb_user_id = ?
                                              ORDER BY created_at ASC',
            array($this->app['fb.user']['id']));

        $tasksDone = $this->db->fetchAll('SELECT id, page_id, emails_count
                                          FROM tasks_done
                                          WHERE fb_user_id = ?
                                          ORDER BY created_at ASC',
            array($this->app['fb.user']['id']));

        $tasks = array();
        $ids = array();

        foreach(array_merge($tasksDone, $tasksAwaiting) as $task)
        {
            $tasks[] = array_merge($task, array(
                'isDone' => ! isset($task['status']),
                'status' => isset($task['status']) ? $task['status'] : null,
            ));
            $ids[$task['page_id']] = 1;
        }

        if(count($ids) > 0)
        {
            $request = new FacebookRequest($this->app['fb.session'], 'GET', '/?ids=' . implode(array_keys($ids), ',') . '&fields=id,category,name,picture,likes');
            $response = json_decode($request->execute()->getRawResponse(), true);

            foreach($tasks as &$task)
            {
                $task['data'] = array(
                    'name'      => $response[$task['page_id']]['name'],
                    'category'  => $response[$task['page_id']]['category'],
                    'picture'   => $response[$task['page_id']]['picture']['data']['url'],
                    'likes'     => number_format($response[$task['page_id']]['likes'], 0, '.', '.'),
                );
            }
        }

        return $this->app['twig']->render('status.html.twig', array(
            'tasks' => $tasks
        ));
    }

    public function downloadAction($id)
    {
        $task = $this->db->fetchAssoc('SELECT * FROM tasks_done WHERE id = ?', array($id));
        $isDone = true;

        if( ! $task)
        {
            $task = $this->db->fetchAssoc('SELECT * FROM tasks WHERE id = ?', array($id));
            $isDone = false;
        }

        if( ! $task || $task['fb_user_id'] !== $this->app['fb.user']['id'])
        {
            return new RedirectResponse($this->generateUrl('home'));
        }

        $now = new \DateTime('now');
        $taskFrom = new \DateTime($task['interval_from']);
        $taskTo = new \DateTime($task['interval_to']);

        $request = new FacebookRequest($this->app['fb.session'], 'GET', '/' . $task['page_id'] . '?fields=name', array(
                    'access_token' => $_SESSION['fb.access_token'],
                ));
        $response = json_decode($request->execute()->getRawResponse(), true);

        $pageName = strtolower(Utils::convertAccentsAndSpecialToNormal($response['name']));
        $pageName = preg_replace('/[^a-z0-9]+/', '', $pageName);

        $filename = sprintf('%s_%s%s_%s.csv', $now->format('Ymd'), $taskFrom->format('Ymd'),
            $taskTo->format('Ymd'), $pageName);

        $file = $this->app['fs.options']['csv_path'] . '/' . $id . '.csv';

        if( ! file_exists($file))
        {
            return new RedirectResponse($this->generateUrl('home'));
        }

        return $this->app
            ->sendFile($file, 200, array('Content-type' => 'text/csv'))
            ->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    }
}
