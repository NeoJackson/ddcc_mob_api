<?php
class CentertestController extends Yaf_Controller_Abstract
{
    //用户福报值记录接口
    public function recordAction(){
        $parameters = array(
            'token' =>'f3969c6fc40a776d5c8ba460de46ef88',
            //'page' =>1,
            'last_time'=>'2015-11-03 13:40:24',
            'size'=>10
        );
        Common::verify($parameters, '/center/record');
    }
    //每日使命
    public function dayOfMissionAction(){
        $parameters = array(
            'token' =>'a1a271b917706fc009a0838ba1087b40',

        );
        Common::verify($parameters, '/center/dayOfMission');
    }
    //用户关注
    public function getAttListAction(){
        $parameters = array(
            'token' =>'684be66780f000d6a17936216bd84f9f',
            'last_time'=>0,
            'size'=>10,
            'uid' => 13438
        );
        Common::verify($parameters, '/center/getAttList');
    }
    //用户粉丝
    public function getFansListAction(){
        $parameters = array(
            'token' =>'f3969c6fc40a776d5c8ba460de46ef88',
            'uid'=>12804,
            'last_time' => '2015-06-11 15:39:46',
            'size' => 5
        );
        Common::verify($parameters, '/center/getFansList');
    }
    //我的知己
    public function friendAction(){
        $parameters = array(
            'token' =>'4270fc4159a5d511c3d8bd55c0ec945b',
        );
        Common::verify($parameters, '/center/friend');
    }
    //用户添加意见
    public function addAdviceAction(){
        $parameters = array(
            'token' =>'70cd656d3a29c5d8ae18bccacd7443d7',
            'content' =>'',
            //'email'=>'32@163.com'
        );
        Common::verify($parameters, '/center/addAdvice');
    }
    //我的收藏
    public function getCollectListAction(){
        $parameters = array(
            'token' =>'542a2f9c81c54edde98ae7905e9fe650',
            'page'=>1,
            'size'=>10,
            'version'=>'3.8'
        );
        Common::verify($parameters, '/center/getCollectList');
    }

    public function viewFeedAction(){
        $parameters = array(
            'token' =>'f4b32c7db5b5171fe38a7f7153b7ec09'
        );
        Common::verify($parameters, '/center/viewFeed');
     }

    //点赞
    public function addLikeAction(){
        $parameters = array(
            'token' =>'0d20451cf73e5c62de4c66fd77051f89',
            'type' =>1,
            'id'=>26350,
        );
        Common::verify($parameters, '/center/addLike');
    }
    //我的收藏筛选条件
    public function getCollectConditionAction(){
        $parameters = array(
            'token' =>'f3284a4e5c9cb5c380f295fb61816b91'
        );
        Common::verify($parameters, '/center/getCollectCondition');
    }
    //我的收藏
    public function getCollectListNewAction(){
        $parameters = array(
            'token' =>'25366045edd009665a0b05803c76cd50',
            'sort'=>'默认',
            'version' => "2.6",
            'city'=>'全国'

        );
        Common::verify($parameters, '/center/getCollectListNew');
    }
}
