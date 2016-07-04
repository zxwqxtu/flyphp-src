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
        if (!empty($action) && !method_exists($className, $action)) {
            return Render::getInstance()->setHeaders(['HTTP/1.1 404 Not Found'])
                ->output("404 Not Found [ACTION-NO-EXISTS:{$controller}->{$action}]");
        }

        $data = call_user_func_array(array($ctrl, $action), $params);

        if (php_sapi_name() == 'cli') {
            return Render::getInstance()->output($data, false);
        } elseif (empty($ctrl->getView())) {
            return Render::getInstance()->setHeaders($ctrl->getHeaders())->output($data, true);
        } elseif (isset($_SERVER['Content-Type']) && $_SERVER['Content-Type'] == 'application/json') {
            return Render::getInstance()->setHeaders($ctrl->getHeaders())->output($data, true);
        }
        return Render::getInstance()->setHeaders($ctrl->getHeaders())
            ->view($data, $ctrl->getViewFile(), $ctrl->getLayoutFile());
    }
}
