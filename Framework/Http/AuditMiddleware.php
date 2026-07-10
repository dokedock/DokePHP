<?php

namespace Framework\Http;

use Framework\Foundation\Application;
use Framework\Foundation\MiddlewareInterface;
use Framework\Support\Logger;

class AuditMiddleware implements MiddlewareInterface
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle($request, $next, $scope = '')
    {
        $cfg = $this->app->config('audit', array());
        if (!is_array($cfg) || empty($cfg['enabled'])) {
            return call_user_func($next, $request);
        }

        $scope = trim((string) $scope);
        if ($scope === '') {
            $scope = 'default';
        }

        $method = strtoupper((string) $request->method());
        $path = (string) $request->path();
        $ip = method_exists($request, 'ip') ? (string) $request->ip((array) $this->app->config('app.trusted_proxies', array())) : '';
        $rid = method_exists($request, 'attribute') ? (string) $request->attribute('request_id', '') : '';
        $auth = method_exists($request, 'attribute') ? $request->attribute('auth', null) : null;

        $resp = call_user_func($next, $request);

        $status = $resp instanceof Response ? (int) $resp->status() : 200;
        $should = $this->shouldAudit($scope, $method, $path, $status);
        if ($should) {
            $this->write($cfg, $scope, $rid, $ip, $method, $path, $status, $auth, $request);
        }

        return $resp;
    }

    private function shouldAudit($scope, $method, $path, $status)
    {
        $scope = (string) $scope;
        $method = (string) $method;
        $path = (string) $path;
        $status = (int) $status;

        if ($scope === 'admin') {
            if ($status === 401 || $status === 403) {
                return true;
            }
            return in_array($method, array('POST', 'PUT', 'PATCH', 'DELETE'), true);
        }

        return in_array($method, array('POST', 'PUT', 'PATCH', 'DELETE'), true);
    }

    private function write(array $cfg, $scope, $rid, $ip, $method, $path, $status, $auth, $request)
    {
        $actorUid = null;
        $actorIdentityId = null;
        if (is_array($auth)) {
            if (isset($auth['uid']) && $auth['uid'] !== '') {
                $actorUid = (string) $auth['uid'];
            }
            if (isset($auth['identity_id']) && (int) $auth['identity_id'] > 0) {
                $actorIdentityId = (int) $auth['identity_id'];
            }
        }

        $data = null;
        if (method_exists($request, 'all')) {
            $data = $request->all();
        }

        $action = $this->actionOf($scope, $method, $path);
        $meta = array(
            'action' => $action,
            'data' => $this->sanitize($data, 0),
        );

        $maxBytes = isset($cfg['max_meta_bytes']) ? (int) $cfg['max_meta_bytes'] : 2048;
        if ($maxBytes <= 0) {
            $maxBytes = 2048;
        }

        $metaJson = json_encode($meta);
        if (!is_string($metaJson)) {
            $metaJson = '';
        }
        if (strlen($metaJson) > $maxBytes) {
            $metaJson = substr($metaJson, 0, $maxBytes);
        }

        $now = date('Y-m-d H:i:s');

        $dbCfg = $this->app->config('db', array());
        $dsn = is_array($dbCfg) && isset($dbCfg['dsn']) ? trim((string) $dbCfg['dsn']) : '';
        if ($dsn !== '') {
            try {
                $this->app->db()->exec(
                    'INSERT INTO audit_logs (request_id,scope,action,actor_uid,actor_identity_id,ip,method,path,status_code,success,created_at,meta) VALUES (:rid,:s,:a,:u,:aid,:ip,:m,:p,:sc,:ok,:t,:meta)',
                    array(
                        'rid' => (string) $rid,
                        's' => (string) $scope,
                        'a' => (string) $action,
                        'u' => $actorUid,
                        'aid' => $actorIdentityId,
                        'ip' => (string) $ip,
                        'm' => (string) $method,
                        'p' => (string) $path,
                        'sc' => (int) $status,
                        'ok' => (int) ($status < 400 ? 1 : 0),
                        't' => (string) $now,
                        'meta' => (string) $metaJson,
                    )
                );
                return;
            } catch (\Exception $e) {
                try {
                    $this->app->db()->exec(
                        'INSERT INTO audit_logs (request_id,scope,actor_uid,actor_identity_id,ip,method,path,status_code,success,created_at,meta) VALUES (:rid,:s,:u,:aid,:ip,:m,:p,:sc,:ok,:t,:meta)',
                        array(
                            'rid' => (string) $rid,
                            's' => (string) $scope,
                            'u' => $actorUid,
                            'aid' => $actorIdentityId,
                            'ip' => (string) $ip,
                            'm' => (string) $method,
                            'p' => (string) $path,
                            'sc' => (int) $status,
                            'ok' => (int) ($status < 400 ? 1 : 0),
                            't' => (string) $now,
                            'meta' => (string) $metaJson,
                        )
                    );
                    return;
                } catch (\Exception $e2) {
                } catch (\Throwable $e2) {
                }
            } catch (\Throwable $e) {
            }
        }

        $file = $this->app->basePath() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'audit.jsonl';
        $logger = new Logger($file);
        $logger->info('audit', array(
            'request_id' => (string) $rid,
            'scope' => (string) $scope,
            'action' => (string) $action,
            'actor_uid' => $actorUid,
            'actor_identity_id' => $actorIdentityId,
            'ip' => (string) $ip,
            'method' => (string) $method,
            'path' => (string) $path,
            'status' => (int) $status,
            'created_at' => (string) $now,
            'meta' => $meta,
        ));
    }

    private function actionOf($scope, $method, $path)
    {
        $scope = (string) $scope;
        $method = strtoupper((string) $method);
        $path = (string) $path;

        if ($scope !== 'admin') {
            return $scope . ':' . $method;
        }

        if ($path === '/admin/roles') {
            return $method === 'POST' ? 'admin.roles.create' : 'admin.roles.index';
        }
        if (preg_match('#^/admin/roles/([^/]+)$#', $path)) {
            if ($method === 'DELETE') {
                return 'admin.roles.delete';
            }
            if ($method === 'PUT' || $method === 'PATCH') {
                return 'admin.roles.update';
            }
        }
        if (preg_match('#^/admin/roles/([^/]+)/permissions$#', $path)) {
            return $method === 'POST' ? 'admin.roles.permissions.set' : 'admin.roles.permissions.get';
        }

        if ($path === '/admin/permissions') {
            return $method === 'POST' ? 'admin.permissions.create' : 'admin.permissions.index';
        }
        if (preg_match('#^/admin/permissions/([^/]+)$#', $path)) {
            if ($method === 'DELETE') {
                return 'admin.permissions.delete';
            }
            if ($method === 'PUT' || $method === 'PATCH') {
                return 'admin.permissions.update';
            }
        }

        if ($path === '/admin/identities') {
            return $method === 'POST' ? 'admin.identities.create' : 'admin.identities.index';
        }
        if (preg_match('#^/admin/identities/([^/]+)$#', $path)) {
            if ($method === 'PUT' || $method === 'PATCH') {
                return 'admin.identities.update';
            }
        }
        if (preg_match('#^/admin/identities/([^/]+)/roles$#', $path)) {
            return $method === 'POST' ? 'admin.identities.roles.set' : 'admin.identities.roles.get';
        }
        if (preg_match('#^/admin/identities/([^/]+)/tokens/revoke_all$#', $path)) {
            return 'admin.identities.tokens.revoke_all';
        }

        if ($path === '/admin/tokens') {
            return $method === 'POST' ? 'admin.tokens.create' : 'admin.tokens.index';
        }
        if (preg_match('#^/admin/tokens/([^/]+)/revoke$#', $path)) {
            return 'admin.tokens.revoke';
        }
        if (preg_match('#^/admin/tokens/([^/]+)/rotate$#', $path)) {
            return 'admin.tokens.rotate';
        }
        if (preg_match('#^/admin/tokens/([^/]+)$#', $path) && $method === 'DELETE') {
            return 'admin.tokens.delete';
        }

        if ($path === '/admin/audit') {
            return 'admin.audit.query';
        }
        if ($path === '/admin/rbac/snapshot') {
            return 'admin.rbac.snapshot';
        }

        return 'admin.unknown';
    }

    private function sanitize($v, $depth)
    {
        if ($depth >= 3) {
            return is_array($v) ? array() : (is_string($v) ? $this->clip($v) : $v);
        }

        if (is_array($v)) {
            $out = array();
            foreach ($v as $k => $vv) {
                $key = is_string($k) ? strtolower($k) : '';
                if ($key !== '' && $this->isSensitiveKey($key)) {
                    $out[$k] = '***';
                    continue;
                }
                $out[$k] = $this->sanitize($vv, $depth + 1);
            }
            return $out;
        }

        if (is_string($v)) {
            return $this->clip($v);
        }

        return $v;
    }

    private function isSensitiveKey($k)
    {
        if ($k === 'token' || $k === 'password' || $k === 'secret' || $k === 'authorization') {
            return true;
        }
        if (strpos($k, 'token') !== false) {
            return true;
        }
        return false;
    }

    private function clip($s)
    {
        $s = (string) $s;
        if (strlen($s) <= 256) {
            return $s;
        }
        return substr($s, 0, 256);
    }
}
