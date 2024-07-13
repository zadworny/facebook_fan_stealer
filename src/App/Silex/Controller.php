<?php

namespace App\Silex;

use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Generator\UrlGenerator;

abstract class Controller {

    /** @var Application */
    protected $app;

    /** @var Connection */
    protected $db;


    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->db  = $app['db'];
    }

    protected function generateUrl($routeName)
    {
        return $this->app['url_generator']->generate($routeName, array(), UrlGenerator::ABSOLUTE_URL);
    }

}
