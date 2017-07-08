<?php
class TagtestController extends Yaf_Controller_Abstract
{
    //获取商家驿站状态
    public function addAction(){
        $parameters = array(
            'token' =>'8fae09c894adfacb9dcaa4789e28efdf',
            'type'=>1,
            'content'=>'胖是的还是感到很是'
        );
        Common::verify($parameters, '/tag/add');
    }
    //保存标签关联关系
    public function saveRelationAction(){
        $parameters = array(
            'token' =>'8fae09c894adfacb9dcaa4789e28efdf',
            'type'=>1,
            'tag'=>'2338&2339&2336'
        );
        Common::verify($parameters, '/tag/saveRelation');
    }
    //删除标签关联关系
    public function delRelationAction(){
        $parameters = array(
            'token' =>'8fae09c894adfacb9dcaa4789e28efdf',
            'type'=>1,
            'id'=>'8195'
        );
        Common::verify($parameters, '/tag/delRelation');
    }
    //获取官方推荐的标签
    public function getTagListAction(){
        $parameters = array(
            'token' =>'8fae09c894adfacb9dcaa4789e28efdf',
        );
        Common::verify($parameters, '/tag/getListByUserPush');
    }
    //获取官方推荐的标签
    public function getListByUserPushAction(){
        $parameters = array(
            'token' =>'76502fb1c7346f2d2414da69ceea4e3b',
        );
        Common::verify($parameters, '/tag/getListByUserPush');
    }
}