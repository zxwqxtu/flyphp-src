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
    /** @var string 数据库类型 */
    protected $dbType = 'mysql';

    /** @var string 数据库配置 */
    protected $dbSelect= 'default';

    /** @var object 数据库 */
    protected $db = null;
    
    /** @var array 实例对象 */
    private static $_instance = array();

    /**
     * 构造函数
     *
     */
    final private function __construct($dbName='')
    {
        $this->init($dbName);
    }

    /**
     * 实例化
     *
     * @param $dbName string 选择哪个数据库
     * @return \Boot\Init
     */
    final public static function getInstance($dbName="")
    {
        $className = get_called_class();
        $key = $className . $dbName;

        if (empty(self::$_instance[$key])) {
            self::$_instance[$key] = new $className($dbName);
        }        
        return self::$_instance[$key];
    }

    /**
     * 初始化
     *
     * @return void
     */
    protected function init($dbName="")
    {
        if (!empty($dbName)) {
            $this->dbSelect = $dbName;
        }
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
     * @param array $sort  []
     *
     * @return array|cursor
     */
    public function findAll($sort=[])
    {
        return $this->findBy([], $sort);
    }
    /**
     * 返回符合条件的所有记录
     *
     * @param array $where []
     * @param array $sort  []
     *
     * @return array|cursor
     */
    public function findBy($where=[], $sort=[])
    {
        switch ($this->dbType) {
        case 'mongodb':
            $cursor = $this->db->selectCollection($this->collection)->find($where);
            if (empty($sort)) {
                return $cursor;
            }
            return $cursor->sort($sort);
        default:
            $where = $this->buildWhere($where);
            $sql = "SELECT * FROM {$this->table} {$where}";
            if (!empty($sort)) {
                $sql .= " ORDER BY ".implode($sort, ',');
            }
            $stmt = $this->db->prepare($sql);
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
    public function findOneBy($where=[], $sort=[])
    {
        switch ($this->dbType) {
        case 'mongodb':
            $cursor = $this->db->selectCollection($this->collection)->find($where)->limit(1);
            if (!empty($sort)) {
                $cursor = $cursor->sort($sort);
            }
            return $cursor->getNext();
        default:
            $where = $this->buildWhere($where);
            $sql = "SELECT * FROM {$this->table} {$where}";
            if (!empty($sort)) {
                $sql .= " ORDER BY ".implode($sort, ',');
            }
            $stmt = $this->db->prepare("{$sql} LIMIT 1");
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
     * 插入多条记录
     *
     * @param array $data []
     *
     * @return bool
     */
    public function insertMany($data=[])
    {
        switch ($this->dbType) {
        case 'mongodb':
            return $this->db->selectCollection($this->collection)->batchInsert($data);
        default:
            $columns = implode(',', array_keys($data[0]));
            $valuesStr = '';
            $db = $this->db;
            foreach ($data as $item) {
                $item = array_map(function ($n) use($db) { 
                    return $db->quote($n);
                }, $item);    
                $value = implode(',', $item);
                $valuesStr .= "({$value}),";
            }
            $valuesStr = trim($valuesStr, ',');
            return $this->db->exec("INSERT INTO {$this->table}({$columns}) VALUES {$valuesStr}");
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
