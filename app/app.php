<?php
session_start();

$startTime = microtime(true);
require_once __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/config.php';

// Initialize Application
$app = new App\Silex\Application($config);

// Initialize Facebook
\Facebook\FacebookSession::setDefaultApplication($app['fb.options']['app_id'], $app['fb.options']['secret']);

// Register services
$app->register(new Silex\Provider\DoctrineServiceProvider(), $config['db.options']);
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

// Register controllers
$app['app.default_controller'] = $app->share(
    function () use ($app) {
        return new \App\Controller\DefaultController($app);
    }
);
$app['app.auth_controller'] = $app->share(
    function () use ($app) {
        return new \App\Controller\AuthController($app);
    }
);
$app['app.ajax_controller'] = $app->share(
    function () use ($app) {
        return new \App\Controller\AjaxController($app);
    }
);

// Map routes to controllers
include __DIR__ . '/routing.php';

if(!isset($_SESSION['selected_app']))
    $_SESSION['selected_app'] = 'default';

// Check if logged in with facebook. Here may be a good place for WP auth check
$app->before(function(\Symfony\Component\HttpFoundation\Request $request) use ($app) {

    $excludedRoutes = array(
        'ajax_status',
        'ajax_select_app',
        'ajax_app_settings',
        'ajax_search_page',
    );

    if(in_array($request->attributes->get('_route'), $excludedRoutes))
        return;

    $helper = new \Facebook\FacebookRedirectLoginHelper($app['url_generator']->generate('home', array(), \Symfony\Component\Routing\Generator\UrlGenerator::ABSOLUTE_URL));

    try {
        $session = $helper->getSessionFromRedirect();
    } catch(\Facebook\FacebookRequestException $ex) {
        // When Facebook returns an error
    } catch(\Exception $ex) {
        // When validation fails or other local issues
    }

    // see if we have a session
    if (isset($session))
    {
        $accessToken = $session->getAccessToken();
        $_SESSION['fb.access_token'] = (string) $accessToken;
    }

    if(isset($_SESSION['fb.access_token']))
    {
        try {
            //echo $_SESSION['fb.access_token'];
            $code = \Facebook\Entities\AccessToken::getCodeFromAccessToken($_SESSION['fb.access_token']);
            $accessToken = \Facebook\Entities\AccessToken::getAccessTokenFromCode($code);
            $longLivedAT = $accessToken->extend();

            $app['fb.session'] = new \Facebook\FacebookSession($longLivedAT);
            $app['fb.user'] = (new \Facebook\FacebookRequest($app['fb.session'], 'GET', '/me?fields=id,name,picture'))
                ->execute()
                ->getGraphObject()
                ->asArray();

            $_SESSION['fb.user_id'] = $app['fb.user']['id'];
            $app['isLogged'] = 1;
            $app['twig']->addGlobal('user', $app['fb.user']);
        } catch(\Facebook\FacebookSDKException $e) {
            echo 'APP: Error getting code: ' . $e->getMessage();
            exit;
        }
    }
    else
    {
        $app['isLogged'] = 0;
        $app['fb.session'] = \Facebook\FacebookSession::newAppSession();

        $loginUrl = $helper->getLoginUrl($app['fb.options']['permissions'], $app['fb.options']['version']);
        $app['twig']->addGlobal('loginUrl', $loginUrl);
    }

    $app['twig']->addGlobal('isLogged', $app['isLogged']);
});

$app['twig']->addGlobal('fs', $app['fs.options']);
$app['twig']->addGlobal('session', $_SESSION);

return $app;
