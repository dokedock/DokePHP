<?php

namespace App\Services;

use Framework\Foundation\Application;
use Framework\Support\Auth;

class AuthService
{
    private $app;
    private $rbac;

    public function __construct(Application $app, RbacService $rbac)
    {
        $this->app = $app;
        $this->rbac = $rbac;
    }

    public function authenticateBearer($token)
    {
        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        $cfg = $this->app->config('auth', array());
        if (!is_array($cfg)) {
            $cfg = array();
        }

        $driver = isset($cfg['driver']) ? strtolower(trim((string) $cfg['driver'])) : 'file';
        if ($driver === '') {
            $driver = 'file';
        }

        if ($driver === 'db') {
            return $this->authByDb($token, $cfg);
        }

        if ($driver === 'hybrid') {
            $p = $this->authByFile($token, $cfg);
            if ($p) {
                return $p;
            }
            return $this->authByDb($token, $cfg);
        }

        return $this->authByFile($token, $cfg);
    }

    private function authByFile($token, array $cfg)
    {
        $tokenMap = Auth::loadTokenMap($this->app, $cfg);
        return Auth::validateToken($token, $tokenMap);
    }

    private function authByDb($token, array $cfg)
    {
        $db = $this->app->db();

        $hash = hash('sha256', $token);

        $sql = "SELECT t.id, t.identity_id, t.expires_at, t.revoked_at, i.uid, i.status AS identity_status
                FROM auth_tokens t
                JOIN auth_identities i ON i.id = t.identity_id
                WHERE t.token_hash = :h
                LIMIT 1";
        $row = $db->fetchOne($sql, array('h' => $hash));
        if (!is_array($row)) {
            return false;
        }

        $identityStatus = isset($row['identity_status']) ? (int) $row['identity_status'] : 0;
        if ($identityStatus !== 1) {
            return false;
        }

        $revokedAt = isset($row['revoked_at']) ? trim((string) $row['revoked_at']) : '';
        if ($revokedAt !== '') {
            return false;
        }

        $expTs = 0;
        $expiresAt = isset($row['expires_at']) ? trim((string) $row['expires_at']) : '';
        if ($expiresAt !== '') {
            $ts = strtotime($expiresAt);
            if ($ts !== false) {
                $expTs = (int) $ts;
            }
        }
        if ($expTs !== 0 && time() > $expTs) {
            return false;
        }

        $uid = isset($row['uid']) ? (string) $row['uid'] : null;
        if ($uid === '') {
            $uid = null;
        }

        $roles = array();
        $identityId = isset($row['identity_id']) ? (int) $row['identity_id'] : 0;
        if ($identityId > 0) {
            $roles = $this->rbac->rolesForIdentityId($identityId);
        }

        if (isset($cfg['db']) && is_array($cfg['db']) && !empty($cfg['db']['touch_last_used'])) {
            $db->exec('UPDATE auth_tokens SET last_used_at = :t WHERE id = :id', array('t' => date('Y-m-d H:i:s'), 'id' => (int) $row['id']));
        }

        return array(
            'token' => $token,
            'exp' => (int) $expTs,
            'uid' => $uid,
            'roles' => $roles,
            'identity_id' => (int) $identityId,
        );
    }
}
