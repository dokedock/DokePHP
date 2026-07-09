<?php

/**
 * 文件作用：模型基类，统一注入 Application 与 Db，并提供基础查询能力示例。
 */

namespace Framework\Foundation;

abstract class BaseModel
{
    protected $app;
    protected $db;

    protected $table = '';
    protected $pk = 'id';

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->db = $app->db();
    }

    public function db()
    {
        return $this->db;
    }

    public function find($id)
    {
        if ($this->table === '') {
            throw new \RuntimeException('model_table_not_set');
        }

        $t = $this->db()->quoteIdentifier($this->table);
        $pk = $this->db()->quoteIdentifier($this->pk);
        $sql = 'SELECT * FROM ' . $t . ' WHERE ' . $pk . ' = :id LIMIT 1';
        return $this->db()->fetchOne($sql, array('id' => $id));
    }
}
