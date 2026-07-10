<?php

/**
 * 文件作用：应用路由定义文件（将 URI 映射到控制器方法）。
 */

$router->get('/', 'App\\Controllers\\HomeController@index');
$router->get('/ping', 'App\\Controllers\\HomeController@ping');
$router->get('/hello/{name}', 'App\\Controllers\\HomeController@hello');
$router->post('/echo', 'App\\Controllers\\HomeController@echoData');
$router->get('/health', 'App\\Controllers\\HealthController@index');
$router->get('/__routes', 'App\\Controllers\\DocsController@routes');
$router->get('/__debug', 'App\\Controllers\\DocsController@requestInfo');

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

$router->group(array(
    'prefix' => '/admin',
    'middleware' => array('auth_required', 'rbac_required', 'audit:admin', 'permission:admin'),
), function ($router) {
    $router->get('/stats', 'App\\Controllers\\HomeController@adminStats');
    $router->get('/rbac', 'App\\Controllers\\HomeController@adminRbac');
    $router->get('/rbac/snapshot', 'App\\Controllers\\AdminRbacController@snapshot', array('middleware' => array('feature_required:rbac_db', 'feature_required:auth_db')));
    $router->get('/audit', 'App\\Controllers\\AdminAuditController@index', array('middleware' => array('feature_required:audit_db')));

    $router->group(array(
        'middleware' => array('feature_required:rbac_db'),
    ), function ($router) {
        $router->get('/roles', 'App\\Controllers\\AdminRbacController@rolesIndex');
        $router->post('/roles', 'App\\Controllers\\AdminRbacController@rolesCreate');
        $router->add('PUT', '/roles/{id}', 'App\\Controllers\\AdminRbacController@rolesUpdate');
        $router->add('DELETE', '/roles/{id}', 'App\\Controllers\\AdminRbacController@rolesDelete');
        $router->get('/roles/{id}/permissions', 'App\\Controllers\\AdminRbacController@rolePermissionsGet');
        $router->post('/roles/{id}/permissions', 'App\\Controllers\\AdminRbacController@rolePermissionsSet');

        $router->get('/permissions', 'App\\Controllers\\AdminRbacController@permissionsIndex');
        $router->post('/permissions', 'App\\Controllers\\AdminRbacController@permissionsCreate');
        $router->add('PUT', '/permissions/{id}', 'App\\Controllers\\AdminRbacController@permissionsUpdate');
        $router->add('DELETE', '/permissions/{id}', 'App\\Controllers\\AdminRbacController@permissionsDelete');

        $router->get('/identities/{id}/roles', 'App\\Controllers\\AdminRbacController@identityRolesGet');
        $router->post('/identities/{id}/roles', 'App\\Controllers\\AdminRbacController@identityRolesSet');
    });

    $router->group(array(
        'middleware' => array('feature_required:auth_db'),
    ), function ($router) {
        $router->get('/identities', 'App\\Controllers\\AdminRbacController@identitiesIndex');
        $router->post('/identities', 'App\\Controllers\\AdminRbacController@identitiesCreate');
        $router->add('PUT', '/identities/{id}', 'App\\Controllers\\AdminRbacController@identitiesUpdate');
        $router->post('/identities/{id}/tokens/revoke_all', 'App\\Controllers\\AdminRbacController@identityTokensRevokeAll');

        $router->get('/tokens', 'App\\Controllers\\AdminRbacController@tokensIndex');
        $router->post('/tokens', 'App\\Controllers\\AdminRbacController@tokensCreate');
        $router->post('/tokens/{id}/revoke', 'App\\Controllers\\AdminRbacController@tokensRevoke');
        $router->post('/tokens/{id}/rotate', 'App\\Controllers\\AdminRbacController@tokensRotate');
        $router->add('DELETE', '/tokens/{id}', 'App\\Controllers\\AdminRbacController@tokensDelete');
    });
});
