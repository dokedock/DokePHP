<?php

/**
 * 文件作用：SQL 迁移执行器（migrations），按文件名顺序执行并记录到 migrations 表。
 */

namespace Framework\Database;

use Framework\Foundation\Application;

class Migrator
{
    private $app;
    private $db;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->db = $app->db();
    }

    public function migrate($dir)
    {
        $dir = rtrim((string) $dir, DIRECTORY_SEPARATOR);
        if (!is_dir($dir)) {
            return array('applied' => array(), 'skipped' => array());
        }

        $this->ensureTable();
        $applied = $this->appliedFiles();

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql');
        if (!is_array($files)) {
            $files = array();
        }
        sort($files);

        $appliedNow = array();
        $skipped = array();

        foreach ($files as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                $skipped[] = $name;
                continue;
            }

            $sql = file_get_contents($file);
            if (!is_string($sql) || trim($sql) === '') {
                $skipped[] = $name;
                continue;
            }

            $this->db->transaction(function ($db) use ($sql, $name) {
                $db->pdo()->exec($sql);
                $db->exec('INSERT INTO ' . $this->tableName() . ' (filename, applied_at) VALUES (:f, :t)', array(
                    'f' => $name,
                    't' => date('Y-m-d H:i:s'),
                ));
            });

            $appliedNow[] = $name;
        }

        return array('applied' => $appliedNow, 'skipped' => $skipped);
    }

    private function ensureTable()
    {
        $driver = $this->db->driverName();
        $table = $this->tableName();

        if ($driver === 'sqlite') {
            $sql = 'CREATE TABLE IF NOT EXISTS ' . $table . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, filename TEXT UNIQUE, applied_at TEXT)';
            $this->db->pdo()->exec($sql);
            return;
        }

        if ($driver === 'pgsql') {
            $sql = 'CREATE TABLE IF NOT EXISTS ' . $table . ' (id SERIAL PRIMARY KEY, filename VARCHAR(255) UNIQUE, applied_at VARCHAR(32))';
            $this->db->pdo()->exec($sql);
            return;
        }

        $sql = 'CREATE TABLE IF NOT EXISTS ' . $table . ' (id INT AUTO_INCREMENT PRIMARY KEY, filename VARCHAR(255) UNIQUE, applied_at VARCHAR(32))';
        $this->db->pdo()->exec($sql);
    }

    private function appliedFiles()
    {
        $rows = $this->db->fetchAll('SELECT filename FROM ' . $this->tableName());
        $out = array();
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (is_array($r) && isset($r['filename'])) {
                    $out[(string) $r['filename']] = true;
                }
            }
        }
        return $out;
    }

    private function tableName()
    {
        try {
            return $this->db->quoteIdentifier('migrations');
        } catch (\Exception $e) {
            return 'migrations';
        } catch (\Throwable $e) {
            return 'migrations';
        }
    }
}
