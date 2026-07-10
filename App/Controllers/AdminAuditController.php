<?php

namespace App\Controllers;

use Framework\Foundation\BaseController;
use Framework\Http\Request;
use Framework\Support\Api;
use Framework\Support\ErrorCodes;
use Framework\Support\Paginator;

class AdminAuditController extends BaseController
{
    public function index(Request $request, array $params = array())
    {
        $dbCfg = $this->app->config('db', array());
        $dsn = is_array($dbCfg) && isset($dbCfg['dsn']) ? trim((string) $dbCfg['dsn']) : '';
        if ($dsn === '') {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $audit = $this->app->config('audit', array());
        if (!is_array($audit) || empty($audit['enabled'])) {
            return Api::fail('audit_disabled', ErrorCodes::CONFLICT, 409, null);
        }

        $pg = Paginator::fromRequest($request, 20, 200);

        $where = array();
        $bind = array();

        $scope = trim((string) $request->query('scope', ''));
        if ($scope !== '') {
            $where[] = 'scope = :scope';
            $bind['scope'] = $scope;
        }

        $rid = trim((string) $request->query('request_id', ''));
        if ($rid !== '') {
            $where[] = 'request_id = :rid';
            $bind['rid'] = $rid;
        }

        $actorUid = trim((string) $request->query('actor_uid', ''));
        if ($actorUid !== '') {
            $where[] = 'actor_uid = :au';
            $bind['au'] = $actorUid;
        }

        $action = trim((string) $request->query('action', ''));
        if ($action !== '') {
            $where[] = 'action = :action';
            $bind['action'] = $action;
        }

        $from = trim((string) $request->query('from', ''));
        if ($from !== '') {
            $t = strtotime($from);
            if ($t !== false) {
                $where[] = 'created_at >= :from';
                $bind['from'] = date('Y-m-d H:i:s', $t);
            }
        }

        $to = trim((string) $request->query('to', ''));
        if ($to !== '') {
            $t = strtotime($to);
            if ($t !== false) {
                $where[] = 'created_at <= :to';
                $bind['to'] = date('Y-m-d H:i:s', $t);
            }
        }

        $whereSql = '';
        if (!empty($where)) {
            $whereSql = ' WHERE ' . implode(' AND ', $where);
        }

        $sqlItems = 'SELECT id,request_id,scope,action,actor_uid,actor_identity_id,ip,method,path,status_code,success,created_at,meta FROM audit_logs' . $whereSql . ' ORDER BY id DESC';
        $sqlCount = 'SELECT COUNT(*) FROM audit_logs' . $whereSql;

        $res = $this->app->db()->paginate($sqlItems, $sqlCount, $bind, $pg['page'], $pg['page_size']);
        return $this->ok($res);
    }
}
