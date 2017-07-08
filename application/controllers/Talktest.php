<?php
class TalktestController extends Yaf_Controller_Abstract
{
    public function indexAction(){
        $parameters = array(
            'token' =>'978a939a89c3173b2870baa9ada07089',
            'keyword'=>'春天来了',
            'follow'=>0
        );
        Common::verify($parameters, '/talk/index');
    }
    public function getListAction(){
        $parameters = array(
            'token' =>'cb8573932d8de25cf3dcd3f0e37628ad',
            'last_id'=>0,
            'size'=>10
        );
        Common::verify($parameters, '/talk/getList');
    }
}