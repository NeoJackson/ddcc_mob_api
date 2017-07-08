<?php
class FollowtestController extends Yaf_Controller_Abstract
{
    //增加关注
    public function addFollowAction(){
        $parameters = array(
            'token' =>'e5742a4c3b0d71283bacabf7a3993364',
            'f_uid'=>13744
        );
        Common::verify($parameters, '/follow/addFollow');
    }
    //取消关注
    public function delFollowAction(){
        $parameters = array(
            'token' =>'882f7bb7291e01fce4876d75f1fedb5e',
            'f_uid'=>696
        );
        Common::verify($parameters, '/follow/delFollow');
    }
    //取消关注
    public function removeAction(){
        $parameters = array(
            'token' =>'02c8f65d53ef050dfc92cc25ba6e526f',
            'f_uid'=>12205
        );
        Common::verify($parameters, '/follow/remove');
    }
    //增加分组
    public function addGroupAction(){
        $parameters = array(
            'token' =>'3ae0dc1c1252cced8c83b02857effe46',
            'name'=>'老孙测试分组'
        );
        Common::verify($parameters, '/follow/addGroup');
    }
    //增加分组
    public function modifyGroupAction(){
        $parameters = array(
            'token' =>'3ae0dc1c1252cced8c83b02857effe46',
            'id'=>64111,
            'name'=>'老孙修改分组'
        );
        Common::verify($parameters, '/follow/modifyGroup');
    }
    //删除分组
    public function delGroupAction(){
        $parameters = array(
            'token' =>'3ae0dc1c1252cced8c83b02857effe46',
            'id'=>64112
        );
        Common::verify($parameters, '/follow/delGroup');
    }

    public function setRemarkAction(){
        $parameters = array(
            'token' =>'64ed4dbe04b4a089431c6037c49fc335',
            'f_uid'=>12219,
            'remark'=>'测试接口'
        );
        Common::verify($parameters, '/follow/setRemark');
    }
    public function getGroupUserByIdAction(){
        $parameters = array(
            'token' =>'9ea2df9b8d1ea03b14f2323a87eae85d',
            'group_id'=>62181,
            'page'=>1,
            'size'=>10
        );
        Common::verify($parameters, '/follow/getGroupUserById');
    }
    public function modifyGroupFollowAction(){
        $parameters = array(
            'token' =>'f345e875bc0bc643935ff06bd202d07c',
            'f_uid'=>12434,
            'group_id'=>64580
        );
        Common::verify($parameters, '/follow/modifyGroupFollow');
    }
}