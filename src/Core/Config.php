<?php
/**
 * Config.php
 *
 * 配置文件类
 *
 * @category Config 
 * @package  Core 
 * @author   wangqiang <960875184@qq.com>
 * @tag      Config 
 * @version  GIT: $Id$
 */
namespace FlyPhp\Core;

/**
 * Core\Config
 *
 * 配置文件类
 *
 * @category Config
 * @package  Core 
 * @author   wangqiang <960875184@qq.com>
 */
class Config 
{    
    /** @var array 配置数组 */
    private static $_config = array();

    /**
     * 获取config.php文件值
     *
     * @param string $key    key
     * @param string $item   item 
     *
     * @return string|int|array|null
     */
    public static function get($key=null, $item=null)
    {
        return self::config($key, $item);
    }

    /**
     * 设置config属性
     *
     * @param string $file      文件前缀名
     *
     * @return array
     */
    public static function load($file = 'config')
    {
        $file = ROOT_PATH.DIRECTORY_SEPARATOR."config/{$file}.php";
        return file_exists($file)? include($file): [];
    }

    /**
     * 魔术方法
     * 获取配置信息
     *
     * @param string $name      method name
     * @param array  $arguments 魔术方法参数 
     *
     * @return string|int|array|null
     */
    public static function __callStatic($name, $arguments)
    {
        if (!isset(self::$_config[$name])) {
            self::$_config[$name] = self::load($name);
        }
        $config = self::$_config[$name];

        $key = empty($arguments[0]) ? null : $arguments[0];
        $item = empty($arguments[1]) ? null : $arguments[1];
        if (empty($key)) {
            return $config;
        }
        if (empty($item)) {
            return isset($config[$key])? $config[$key]: null;
        }

        return isset($config[$key][$item])? $config[$key][$item]: null;
    }
}
