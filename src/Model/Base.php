<?php
/**
 * Base.php
 *
 * Model base
 *
 * @category Base
 * @package  Models
 * @author   wangqiang <960875184@qq.com>
 * @tag      Models Base
 * @version  GIT: $Id$
 */
namespace FlyPhp\Model;

use FlyPhp\Core\Config;
use FlyPhp\Core\Single;

/**
 * Base.php
 *
 * 模型base
 *
 * @category Base
 * @package  Models
 * @author   wangqiang <960875184@qq.com>
 */
abstract class Base
{
    //使用单例
    use Single;

    /** @var string 数据库类型 */
    protected $dbType = 'mysql';

    /** @var string 数据库配置 */
    protected $dbSelect= 'default';

    /** @var object 数据库 */
    protected $db = null;
    
    /**
     * 初始化
     *
     * @return void
     */
    protected function init()
    {
        $config = Config::database($this->dbType, $this->dbSelect);

        switch ($this->dbType) {
        case 'mongodb':
            $dbClass = '\PhpDb\Mongodb\PhpMongo';
            break;
        default:
            $dbClass = '\PhpDb\Pdo\PhpPdo';
        } 

        $this->db = $dbClass::getInstance()->connect($config, $this->dbType);
    }

    /**
     * 根据ID获取一条记录
     *
     * @param int $id id
     *
     * @return array
     */
    public function find($id)
    {
        switch ($this->dbType) {
        case 'mongodb':
            $where = ['id' => $id];
            break;
        default:
            $where = [['id', intval($id)]];
        }

        return $this->findOneBy($where);
    }
    /**
     * 返回所有记录
     *
     * @return array|cursor
     */
    public function findAll()
    {
        return $this->findBy();
    }
    /**
     * 返回符合条件的所有记录
     *
     * @param array $where []
     *
     * @return array|cursor
     */
    public function findBy($where=[])
    {
        switch ($this->dbType) {
        case 'mongodb':
            return $this->db->selectCollection($this->collection)->find($where);
        default:
            $where = $this->buildWhere($where);
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} {$where}");
            $stmt->execute();
            return $stmt->fetchAll();
        }
    }
    /**
     * 返回符合条件的第一条记录
     *
     * @param array $where []
     *
     * @return array|cursor
     */
    public function findOneBy($where=[])
    {
        switch ($this->dbType) {
        case 'mongodb':
            return $this->db->selectCollection($this->collection)->findOne($where);
        default:
            $where = $this->buildWhere($where);
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} {$where}");
            $stmt->execute();
            return $stmt->fetch();
        }
    }
    /**
     * 插入一条记录
     *
     * @param array $data []
     *
     * @return bool
     */
    public function insert($data=[])
    {
        switch ($this->dbType) {
        case 'mongodb':
            return $this->db->selectCollection($this->collection)->insert($data);
        default:
            $columns = implode(',', array_keys($data));
            $exp = trim(str_repeat('?,', count($data)), ',');

            $stmt = $this->db->prepare("INSERT INTO {$this->table}({$columns}) VALUE({$exp})");
            return $stmt->execute(array_values($data));
        }
    }
    /**
     * 获取一条记录ID
     *
     * @return int 
     */
    public function lastInsertId()
    {
        return $this->db->lastInsertId();
    }
    /**
     * 更新记录
     *
     * @param array $data  []
     * @param array $where []
     *
     * @return int
     */
    public function update($data=[], $where=[])
    {
        switch ($this->dbType) {
        case 'mongodb':
            return $this->db->selectCollection($this->collection)->update(
                $where, 
                ['$set' => $data], 
                ['multiple' => true, 'upsert' => false]
            );
        default:
            $where = $this->buildWhere($where);
            $value = '';
            foreach ($data as $k => $v) {
                $value .= "{$k}=?,";
            }
            $value = trim($value, ',');
            $stmt = $this->db->prepare("UPDATE {$this->table} SET {$value} {$where}");
            return $stmt->execute(array_values($data));
        }
    }
    /**
     * 删除记录
     *
     * @param array $where []
     *
     * @return int 
     */
    public function delete($where=[])
    {
        switch ($this->dbType) {
        case 'mongodb':
            return $this->db->selectCollection($this->collection)->remove(
                $where, 
                ['justOne' => false]
            );
        default:
            $where = $this->buildWhere($where);
            return $this->db->exec("DELETE FROM {$this->table} {$where}");
        }
    }
    /**
     * @example ['name' => ['%wq%', 'like'], 'id' => 1] 
     * pdo 专属
     *
     * @param array $filters []
     *
     * @return string
     */
    protected function buildWhere($filters)
    {
        $where = 'WHERE 1 = 1 ';
        foreach ($filters as $v) {
            $column = $v[0];
            $val = $v[1];
            if (!empty($v[2])) {
                $expression = $v[2];
            } elseif (is_array($val)) {
                $expression = 'in';
            } else {
                $expression = '=';
            }

            if (strtolower($expression) == 'in') {
                $val = "'".implode("','", $val)."'";
                $where .= " AND {$column} {$expression} ({$val})";
            } else {
                $where .= " AND {$column} {$expression} ".$this->db->quote($val);
            }
        } 
        return $where;
    }

}
