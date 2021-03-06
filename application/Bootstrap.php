<?php
/**
 * @name Bootstrap
 * @author {&$AUTHOR&}
 * @desc 所有在Bootstrap类中, 以_init开头的方法, 都会被Yaf调用,
 * @see http://www.php.net/manual/en/class.yaf-bootstrap-abstract.php
 * 这些方法, 都接受一个参数:Yaf_Dispatcher $dispatcher
 * 调用的次序, 和申明的次序相同
 */
class Bootstrap extends Yaf_Bootstrap_Abstract{

    public function _initSession(Yaf_Dispatcher $dispatcher) {
        /*
         * start a session
         */
        ini_set("session.cookie_domain",DOMAIN);
        Yaf_Session::getInstance()->start();
    }

    public function _initConfig(Yaf_Dispatcher $dispatcher) {
        //把配置保存起来
        $config = new Yaf_Config_Ini(APPLICATION_PATH . "/conf/application.ini", ENVIRONMENT);
        Yaf_Registry::set('config', $config);
        Yaf_Dispatcher::getInstance()->autoRender(FALSE);
    }

    public function _initPlugin(Yaf_Dispatcher $dispatcher) {
        //注册一个插件
        //$objSamplePlugin = new SamplePlugin();
        //$dispatcher->registerPlugin($objSamplePlugin);
    }

    public function _initRoute(Yaf_Dispatcher $dispatcher) {
        //在这里注册自己的路由协议,默认使用简单路由
        $router = Yaf_Dispatcher::getInstance()->getRouter();
        /**
         * 添加配置中的路由
         */
        $router->addConfig(Yaf_Registry::get("config")->routes);
    }

    public function _initView(Yaf_Dispatcher $dispatcher){
        //在这里注册自己的view控制器，例如smarty,firekylin
    }
}