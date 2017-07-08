<?php
class SendtestController extends Yaf_Controller_Abstract
{
    public function testAction(){
        $parameters = array(
        );
        Common::verify($parameters, '/send/test');
    }
}