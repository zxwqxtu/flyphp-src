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
final class Init
{
    //使用单例
    use Single;

    /** @var string controller */
    protected $controller = "";

    /** @var string action */
    protected $action = "";

    /** @var array params */
    protected $params = [];

    /**
     * 日志记录
     *
     * <p>项目config.php配置logPath，默认是项目logs目录,
     * 错误日志文件error.log</p>
     *
     * @return void
     */
    protected function setLog()
    {
       $logPath = Config::get('logPath');
        if (empty($logPath)) {
            $logPath = Config::getRootPath().'/logs';
            !is_dir($logPath) && mkdir($logPath, 0777, true);
        }

        error_reporting(E_ALL);
        ini_set('log_errors', 'On');
        ini_set('display_errors', empty(Config::get('debug')) ? 0 : 1);
        ini_set('error_log', rtrim($logPath, '/').'/error.log');
    }

    /**
     * 设置项目根目录
     *
     * @param $rootPath string web项目根目录
     *
     * @return \FlyPhp\Boot\Init
     */
    public function setWebPath($rootPath)
    {
        Config::setRootPath($rootPath);
        return $this;
    }
    
    /**
     * 路由解析
     * 查找相应的文件controller，action
     *
     * @return void
     */
    protected function loadRoute()
    {
        $route = Route::getInstance();

        //url解释对应action,controller
        $route->urlToClass();

        //controller
        $controller = $route->getController();
        $controller = empty($controller) ? Config::get('indexController') : $controller;
        $this->controller = "App\\Controller\\".ucfirst($controller);

        //action
        $action = $route->getAction();
        if (!empty($action)) {
            $this->action = $action."Action";
        }

        //params
        $this->params = $route->getParams();
    }

    /**
     * 缓存文件
     *
     * 返回文件，内容
     * @return array
     */
    protected function getCache()
    {
        $cacheKey = $this->controller."::".$this->action;
        $cacheTime = intval(Config::cache($cacheKey));

        if (empty(Config::get('debug')) || $cacheTime == 0 || php_sapi_name() == 'cli') {
            return null;
        }

        $result = '';
        $cacheFile = Config::get('cachePath').'/'.md5($_SERVER['REQUEST_URI']);

        if (file_exists($cacheFile) && filemtime($cacheFile) > time() - $cacheTime) {
            $result = unserialize(file_get_contents($cacheFile));
        }
 
        return ['file' => $cacheFile, 'content' => $result];
    }

    /**
     * 启动程序
     *
     * @return boolean
     */
    public function start()
    {
        //时区
        date_default_timezone_set(Config::get('timezone'));
 
        //log日志
        $this->setLog();

        //解析路由
        $this->loadRoute();

        //controller不存在
        if (!class_exists($this->controller)) {
            error_log("CONTROLLER-NO-EXISTS[{$this->controller}]");
            return Render::getInstance()->setHeaders(['HTTP/1.1 404 Not Found'])->output("404 Not Found");
        }

        //controller
        $ctrl = new $this->controller();

        if (empty($this->action)) {
            $this->action = $ctrl->getDefaultAction()."Action";
        }
        if (empty($this->action) || !method_exists($this->controller, $this->action)) {
            error_log("ACTION-NO-EXISTS[{$this->controller}::{$this->action}]");
            return Render::getInstance()->setHeaders(['HTTP/1.1 404 Not Found'])->output("404 Not Found");
        }

        //缓存
        $cacheData = $this->getCache();

        if (!empty($cacheData['content'])) {
            $result = $cacheData['content'];
        } else {
             $result = [
                'data' => call_user_func_array(array($ctrl, $this->action), $this->params),
                'view' => $ctrl->getViewFile(),
                'layout' => $ctrl->getLayoutFile(),
                'header' => $ctrl->getHeaders(),
            ];
             
            !empty($cacheData['file']) && file_put_contents($cacheData['file'], serialize($result));
        }

        if (php_sapi_name() == 'cli') {
            return Render::getInstance()->output($result['data'], false);
        } elseif (empty($result['view'])) {
            return Render::getInstance()->setHeaders($result['header'])->output($result['data'], true);
        } elseif (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            return Render::getInstance()->setHeaders($result['header'])->output($result['data'], true);
        }
        return Render::getInstance()->setHeaders($result['header'])
            ->view($result['data'], $result['view'], $result['layout']);
    }
}
