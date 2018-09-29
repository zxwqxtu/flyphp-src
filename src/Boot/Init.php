<?php
/**
 * Init.php
 *
 * 启动类
 *
 * @category Init
 * @package  Boot
 * @author   wangqiang <960875184@qq.com>
 * @tag      Boot Init 
 * @version  GIT: $Id$
 */
namespace FlyPhp\Boot;

use FlyPhp\Core\Config;
use FlyPhp\Core\Single;
use FlyPhp\Boot\Route;
use FlyPhp\Response\Render;

/**
 * Boot\Init
 *
 * 启动类
 *
 * @category Init
 * @package  Boot
 * @author   wangqiang <960875184@qq.com>
 */
class Init
{
    //使用单例
    use Single;

    /** @var \Boot\Route 路由器对象 */
    private $_route = null;

    /**
     * 错误日志记录
     *
     * @return void
     */
    protected function logError()
    {
        error_reporting(E_ALL);
        ini_set('log_errors', 'On');
        defined('DEBUG') && ini_set('display_errors', intval(DEBUG));

        if (!empty(Config::get('errorLog'))) {
            $errorLog = Config::get('errorLog');
        } else {
            $errorLog = ROOT_PATH . "/logs"; 
            !is_dir($errorLog) && mkdir($errorLog, 0777, true);
            $errorLog .= "/error.log";
        }

        return ini_set('error_log', $errorLog);
    }

    /**
     * 启动初始化
     * 加载配置文件
     *
     * @return void
     */    
    protected function init()
    {
        $this->_route = Route::getInstance();
        define('DEBUG', Config::get('debug'));

        //log日志
        $this->logError();
        //时区
        date_default_timezone_set(Config::get('timezone'));
        
        //url解释对应action,controller
        $this->_route->urlToClass();
    }
    
    /**
     * 启动程序，查找相应的文件controller，action
     *
     * @return boolean
     */
    public function start()
    {
        $controller = $this->_route->getController();
        $action = $this->_route->getAction();
        $params = $this->_route->getParams();

        $controller = empty($controller) ? Config::get('indexController') : $controller;
        $className = "App\\Controller\\".ucfirst($controller);
        if (!class_exists($className)) {
            return Render::getInstance()->setHeaders(['HTTP/1.1 404 Not Found'])
                ->output("404 Not Found [CONTROLLER-NO-EXISTS:{$controller}]");
        }

        $ctrl = new $className();

        $action = empty($action) ? $ctrl->getDefaultAction(): $action;
        $method = $action."Action";
        if (empty($action) || !method_exists($className, $method)) {
            return Render::getInstance()->setHeaders(['HTTP/1.1 404 Not Found'])
                ->output("404 Not Found [ACTION-NO-EXISTS:{$controller}->{$action}]");
        }

        $cacheKey = get_class($ctrl)."::".$method;
        $cacheTime = intval(Config::cache($cacheKey));

        if (php_sapi_name() == 'cli') {
            $cacheFile = Config::get('cachePath').'/'.md5(json_encode($params));
        } else {
            $cacheFile = Config::get('cachePath').'/'.md5($_SERVER['REQUEST_URI']);
        }
        $flag = (empty(DEBUG) && $cacheTime > 0);

        //debug=false环境下才有cache
        if ($flag && file_exists($cacheFile) && filemtime($cacheFile)>time()-$cacheTime) {
            $result = unserialize(file_get_contents($cacheFile));
        } else {
            $result = [
                'data' => call_user_func_array(array($ctrl, $method), $params),
                'view' => $ctrl->getViewFile(),
                'layout' => $ctrl->getLayoutFile(),
                'header' => $ctrl->getHeaders(),
            ];

            $flag && file_put_contents($cacheFile, serialize($result));
        }

        if (php_sapi_name() == 'cli') {
            return Render::getInstance()->output($result['data'], false);
        } elseif (empty($result['view'])) {
            return Render::getInstance()->setHeaders($result['header'])->output($result['data'], true);
        } elseif (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json') {
            return Render::getInstance()->setHeaders($result['header'])->output($result['data'], true);
        }
        return Render::getInstance()->setHeaders($result['header'])
            ->view($result['data'], $result['view'], $result['layout']);
    }
}
