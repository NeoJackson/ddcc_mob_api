<?php
class ReporttestController extends Yaf_Controller_Abstract
{
    //测试注册接口
    public function selectReportAction(){
        $parameters = array(
            'token' =>'aba58659c61ec973c2e8f0302321445f',
            'obj_id' =>'12345',
            'type' =>'4'
        );
        Common::verify($parameters, '/report/selectReport');
    }
}