<?php
class MessagetestController extends Yaf_Controller_Abstract
{
    //新消息数量
    public function getNumAction(){
        $parameters = array(
            'token'=>'7420cafb38c40d50e3791e9df793fa02',
            'type'=>4
        );
        Common::verify($parameters, '/message/getNum');
    }
    public function getNumNewAction(){
        $parameters = array(
            'token'=>'cbdc467d29836ec5f94773b29d34b5e3',
            'type'=>2,
            //'version'=>'2.5.2'
        );
        Common::verify($parameters, '/message/getNumNew');
    }

    //查看评论
    public function commentNewAction(){
        $parameters = array(
            'token'=>'3640d08e6ac880cf4b13b4ce9844de01',
            'page'=>1,
            'size'=>10

        );
        Common::verify($parameters, '/message/commentNew');
    }
    //获取系统消息
    public function noticeAction(){
        $parameters = array(
            'token'=>'7420cafb38c40d50e3791e9df793fa02',
            'type'=>3,
            'page'=>1,
            'size'=>10
        );
        Common::verify($parameters, '/message/notice');
    }
    //消息中心
    public function messageAction(){
        $parameters = array(
            'token'=>'bbd15d45e5e224745fc1170d6bb24b73'
        );
        Common::verify($parameters, '/message/message');
    }

    //提到我的
    public function mentionAction(){
        $parameters = array(
            'token'=>'ae6d763b53b837363cbd7ac8e6d63170',
            'page'=>1,
            'size'=>10

        );
        Common::verify($parameters, '/message/mention');
    }
    //喜欢我的列表
    public function likeAction(){
        $parameters = array(
            'token'=>'3640d08e6ac880cf4b13b4ce9844de01',
            'page'=>1,
            'size'=>10
        );
        Common::verify($parameters, '/message/like');
    }
    //打赏我的列表
    public function rewardAction(){
        $parameters = array(
            'token'=>'b6385ab93cc94ecf82c5f210d9ad0e7f',
            'page'=>1,
            'size'=>5
        );
        Common::verify($parameters, '/message/reward');
    }

}