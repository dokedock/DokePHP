<?php

/**
 * 文件作用：健康检查接口（/health），用于线上探活与基础依赖检测。
 */

namespace App\Controllers;

use Framework\Foundation\BaseController;
use Framework\Http\Request;

class HealthController extends BaseController
{
    public function index(Request $request, array $params = array())
    {
        $out = array(
            'ok' => true,
            'time' => date('Y-m-d H:i:s'),
        );

        $dbCheck = $this->app->config('health.check_db', false);
        if ($dbCheck) {
            $out['db'] = $this->checkDb();
        }

        return $this->ok($out);
    }

    private function checkDb()
    {
        try {
            $this->app->db()->query('SELECT 1');
            return array('ok' => true);
        } catch (\Exception $e) {
            return array('ok' => false);
        } catch (\Throwable $e) {
            return array('ok' => false);
        }
    }
}

