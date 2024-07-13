<?php

/**
 * This file should be included from app.php, and is where you hook
 * up routes to controllers.
 *
 * @link http://silex.sensiolabs.org/doc/usage.html#routing
 * @link http://silex.sensiolabs.org/doc/providers/service_controller.html
 */

$app->get('/', 'app.default_controller:indexAction')->bind('home');
$app->get('/status', 'app.default_controller:statusAction')->bind('status');
$app->get('/download/{id}', 'app.default_controller:downloadAction')->bind('download');

$app->get('/logout', 'app.auth_controller:logoutAction')->bind('logout');

$app->post('/ajax/add/tasks', 'app.ajax_controller:addTasksAction')->bind('ajax_add_tasks');
$app->get('/ajax/search', 'app.ajax_controller:searchPageAction')->bind('ajax_search_page');
$app->get('/ajax/status', 'app.ajax_controller:statusAction')->bind('ajax_status');
$app->post('/ajax/delete/task', 'app.ajax_controller:deleteTaskAction')->bind('ajax_delete_task');
$app->post('/ajax/select_app', 'app.ajax_controller:selectAppAction')->bind('ajax_select_app');
$app->post('/ajax/app_settings', 'app.ajax_controller:appSettingsAction')->bind('ajax_app_settings');
