<?php

/**
 * 文件作用：应用路由定义文件（将 URI 映射到控制器方法）。
 */

$router->get('/', 'App\\Controllers\\HomeController@index');
$router->get('/ping', 'App\\Controllers\\HomeController@ping');
$router->get('/hello/{name}', 'App\\Controllers\\HomeController@hello');
$router->post('/echo', 'App\\Controllers\\HomeController@echoData');
$router->get('/health', 'App\\Controllers\\HealthController@index');

$router->group(array(
    'prefix' => '/account',
    'middleware' => array('Framework\\Http\\AuthMiddleware'),
), function ($router) {
    $router->get('/me', 'App\\Controllers\\HomeController@me');
});

$router->group(array(
    'prefix' => '/secure',
    'middleware' => array('Framework\\Http\\AuthRequiredMiddleware'),
), function ($router) {
    $router->get('/me', 'App\\Controllers\\HomeController@me');
});
