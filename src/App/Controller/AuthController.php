<?php

namespace App\Controller;

use App\Silex\Application;
use App\Silex\Controller;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGenerator;

class AuthController extends Controller {

    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function logoutAction()
    {
        unset($_SESSION['fb.access_token']);
        unset($_SESSION['fb.user_id']);
        return new RedirectResponse($this->generateUrl('home'));
    }

}
