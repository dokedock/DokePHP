<?php

/**
 * 文件作用：基于原生 PDO 的数据库访问封装（连接、查询、事务）。
 */

namespace Framework\Database;

class Db
{
    private $cfg;
    private $pdo;
    private $driver;

    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
        $this->pdo = null;
        $this->driver = null;
    }

    public function pdo()
    {
        if ($this->pdo instanceof \PDO) {
            return $this->pdo;
        }

        $dsn = isset($this->cfg['dsn']) ? (string) $this->cfg['dsn'] : '';
        $user = isset($this->cfg['username']) ? (string) $this->cfg['username'] : '';
        $pass = isset($this->cfg['password']) ? (string) $this->cfg['password'] : '';
        $opt = isset($this->cfg['options']) && is_array($this->cfg['options']) ? $this->cfg['options'] : array();

        $defaults = array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        );

        foreach ($opt as $k => $v) {
            $defaults[$k] = $v;
        }

        $this->pdo = new \PDO($dsn, $user, $pass, $defaults);
        return $this->pdo;
    }

    public function driverName()
    {
        if ($this->driver !== null) {
            return $this->driver;
        }

        $name = '';
        try {
            $name = (string) $this->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        } catch (\Exception $e) {
            $name = '';
        }

        $this->driver = strtolower($name);
        return $this->driver;
    }

    public function quoteIdentifier($name)
    {
        $name = (string) $name;
        $driver = $this->driverName();
        $parts = explode('.', $name);
        if (empty($parts)) {
            throw new \InvalidArgumentException('invalid_identifier');
        }

        $out = array();
        foreach ($parts as $seg) {
            $seg = (string) $seg;
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $seg)) {
                throw new \InvalidArgumentException('invalid_identifier');
            }

            if ($driver === 'mysql') {
                $out[] = '`' . $seg . '`';
                continue;
            }

            if ($driver === 'pgsql' || $driver === 'sqlite') {
                $out[] = '"' . $seg . '"';
                continue;
            }

            $out[] = $seg;
        }

        return implode('.', $out);
    }

    public function query($sql, array $params = array())
    {
        $st = $this->pdo()->prepare($sql);
        $this->bind($st, $params);
        $st->execute();
        return $st;
    }

    public function fetchOne($sql, array $params = array())
    {
        $st = $this->query($sql, $params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll($sql, array $params = array())
    {
        $st = $this->query($sql, $params);
        return $st->fetchAll();
    }

    public function paginate($sqlItems, $sqlCount, array $params, $page, $pageSize)
    {
        $page = (int) $page;
        $pageSize = (int) $pageSize;
        if ($page <= 0) {
            $page = 1;
        }
        if ($pageSize <= 0) {
            $pageSize = 20;
        }

        $offset = ($page - 1) * $pageSize;
        if ($offset < 0) {
            $offset = 0;
        }

        $countRow = $this->fetchOne($sqlCount, $params);
        $total = 0;
        if (is_array($countRow)) {
            $first = array_values($countRow);
            $total = isset($first[0]) ? (int) $first[0] : 0;
        }

        $sqlItems = rtrim((string) $sqlItems, " \t\n\r;");
        $sqlItems .= ' LIMIT ' . (int) $pageSize . ' OFFSET ' . (int) $offset;
        $items = $this->fetchAll($sqlItems, $params);

        return array(
            'items' => is_array($items) ? $items : array(),
            'total' => (int) $total,
            'page' => (int) $page,
            'page_size' => (int) $pageSize,
        );
    }

    public function exec($sql, array $params = array())
    {
        $st = $this->pdo()->prepare($sql);
        $this->bind($st, $params);
        $st->execute();
        return $st->rowCount();
    }

    public function lastId($name = null)
    {
        return $this->pdo()->lastInsertId($name);
    }

    public function begin()
    {
        return $this->pdo()->beginTransaction();
    }

    public function commit()
    {
        return $this->pdo()->commit();
    }

    public function rollback()
    {
        return $this->pdo()->rollBack();
    }

    public function transaction($callback)
    {
        $this->begin();
        try {
            $result = call_user_func($callback, $this);
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function bind(\PDOStatement $st, array $params)
    {
        foreach ($params as $k => $v) {
            if (is_int($k)) {
                $st->bindValue($k + 1, $v);
                continue;
            }

            $name = (string) $k;
            if ($name === '' || $name[0] !== ':') {
                $name = ':' . $name;
            }
            $st->bindValue($name, $v);
        }
    }
}
