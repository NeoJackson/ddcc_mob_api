<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 16-12-28
 * Time: 下午1:46
 */
class MalltestController extends Yaf_Controller_Abstract{
    public function indexAction(){
        $parameters = array(

        );
        Common::verify($parameters, '/mall/index');
    }
}