<?php
class UsertestController extends Yaf_Controller_Abstract {

    //用户修改密码
    public function modifyPwdAction(){
        $parameters = array(
            'token' =>'33e39174e60322f7562dc2c1673081ab',
            'old_pwd' =>'111111',
            'pwd'=>'daidai1234',
            'confirm_pwd'=>'daidai1234'
        );
        Common::verify($parameters, '/user/modifyPwd');
    }
    //用户个人基本资料
    public function getBasicInfoAction(){
        $parameters = array(
            'token' =>'64ed4dbe04b4a089431c6037c49fc335',
            'uid' =>'13476'
        );
        Common::verify($parameters, '/user/getBasicInfo');
    }
    public function userAddTypeAction(){
        $parameters = array(
            'token' =>'f1542f11362110b0c81c720c1c5e0fc4',
        );
        Common::verify($parameters, '/user/userAddType');
    }
    //用户个人基本资料
    public function getBasicInfoByDidAction(){
        $parameters = array(
            'token' =>'21cd3b6d6d679947801441981ce11cc5',
            'did' =>'1003913'
        );
        Common::verify($parameters, '/user/getBasicInfoByDid');
    }
    //用户个人基本资料
    public function getBasicInfoByNicknameAction(){
        $parameters = array(
            'token' =>'21cd3b6d6d679947801441981ce11cc5',
            'nick_name' =>'代代编辑部'
        );
        Common::verify($parameters, '/user/getBasicInfoByNickname');
    }
    //用户封面
    public function setHomeCoverAction(){
        $parameters = array(
            'token' =>'388aec6739ea20615b4802bf8e579c87',
            'cover' =>'123456.jpg'
        );
        Common::verify($parameters, '/user/setHomeCover');
    }
    public function updateBasicInfoAction(){
        $parameters = array(
            'token' =>'24f54ecea4909317b468c2e43c156b5d',
            'type' =>'3',
            'intro'=>'‘’'
        );
        Common::verify($parameters, '/user/updateBasicInfo');
    }
    //猜你喜欢
    public function guessYouLikeAction(){
        $parameters = array(
            'token' =>'f4b32c7db5b5171fe38a7f7153b7ec09',
        );
        Common::verify($parameters, '/user/guessYouLike');
    }
    //我的等级
    public function myLevelAction(){
        $parameters = array(
            'token' =>'f4b32c7db5b5171fe38a7f7153b7ec09',
        );
        Common::verify($parameters, '/user/myLevel');
    }
    //推荐头像
    public function recommendAvatarAction(){
        $parameters = array(

        );
        Common::verify($parameters, '/user/recommendAvatar');
    }
    //验证绑定
    public function isBindAction(){
        $parameters = array(
            'token' =>'ec4d2d217e9e07464241efaa1dee1c67',
        );
        Common::verify($parameters, '/user/isBind');
    }
    //验证绑定
    public function bindMobileAction(){
        $parameters = array(
            'token' =>'1f8288464a651d5653f684dc732fb9c3',
            'mobile' =>'18700000158',
            'code'=>'163486'
        );
        Common::verify($parameters, '/user/bindMobile');
    }
    //验证绑定
    public function sendSmsCodeAction(){
        $parameters = array(
            'token' =>'ba9a368ac8c3627f5182eee67623acd3',
            'mobile' =>'18995863009',
            'countyr_code'=>'+213'
        );
        Common::verify($parameters, '/user/sendSmsCode');
    }
    //个人资料默认图
    public function getDefaultCoverAction(){
        $parameters = array(
            'token' =>'f4b32c7db5b5171fe38a7f7153b7ec09',
        );
        Common::verify($parameters, '/user/getDefaultCover');
    }
    public function newFansAction(){
        $parameters = array(
            'token' =>'cd21fec9756e3c031da85e24bc942d17',
        );
        Common::verify($parameters, '/user/newFans');
    }
    public function mobileIsBindAction(){
        $parameters = array(
            'token' =>'3cd1f62370875b1aeebf1f739822c3c2',
            'mobile'=>13200000012,
            'country_code'=>'+92'
        );
        Common::verify($parameters, '/user/mobileIsBind');
    }
    public function sendSmsCodeForBindAction(){
        $parameters = array(
            'token' =>'16275521ceefd087f02d4e7a117a2be7',
            'mobile'=>13300000001
        );
        Common::verify($parameters, '/user/sendSmsCodeForBind');
    }
    public function getMoodListAction(){
        $parameters = array(
            'token' =>'ecd4586a5bf68386fe82d65bd905dbab',
            'uid'=>12476
        );
        Common::verify($parameters, '/user/getMoodList');
    }
    public function getAppApplyAction(){
        $parameters = array(
            'token' =>'0eea7ef1df905e515c778070460b0d72',
        );
        Common::verify($parameters, '/user/getAppApply');
    }
    public function getUserGoodsOrderNumAction(){
        $parameters = array(
            'token' =>'69ce80ac6242b63921d148619eef1fba',
            'type'=>2
        );
        Common::verify($parameters, '/user/getUserGoodsOrderNum');
    }
    public function getUserBindTypeAction(){
        $parameters = array(
            'token' =>'5720e3e0daeeccde8a7e58e41c0655f0',
            'version'=>'3.7.2'
        );
        Common::verify($parameters, '/user/getUserBindType');
    }
    public function commissionIndexAction(){
        $parameters = array(
            'token' =>'7a2afe4bca099b587b00202fc83c1eec',
            'version'=>'3.8'
        );
        Common::verify($parameters, '/user/commissionIndex');
    }
    public function getUserSpStatisticsAction(){
        $parameters = array(
            'token' =>'2b4387bb09053e89c603f69f59185452',
            'version'=>'3.8'
        );
        Common::verify($parameters, '/user/getUserSpStatistics');
    }
    public function getInfoAction(){
        $parameters = array(
            'token' =>'8e9da967d0e7cf1d9310dce4fa082041',
        );
        Common::verify($parameters, '/user/getInfo');
    }
    public function getUserWithDrawInfoAction(){
        $parameters = array(
            'token' =>'98681b6441542838fdb0c6acded09114',
        );
        Common::verify($parameters, '/user/getUserWithDrawInfo');
    }
    public function isWithDrawAction(){
        $parameters = array(
            'token' =>'f6760d7278776730a454231c7766d5f9',
        );
        Common::verify($parameters, '/user/isWithDraw');
    }
    public function getUserBankAction(){
        $parameters = array(
            'token' =>'5f5ca2721966b1b68a5ac87b4cd7f36b',
        );
        Common::verify($parameters, '/user/getUserBank');
    }
    public function getOneSerialInfoAction(){
        $parameters = array(
            'token' =>'57c50b1dcd97006ebf88d1fd07ea55e8',
            'id'=>24,
            'info_type'=>1
        );
        Common::verify($parameters, '/user/getOneSerialInfo');
    }
    public function getUserSerialListAction(){
        $parameters = array(
            'token' =>'2b4387bb09053e89c603f69f59185452',
            'last_id'=>0,
            'type'=>4
        );
        Common::verify($parameters, '/user/getUserSerialList');
    }
    public function delUserBankAction(){
        $parameters = array(
            'token' =>'7d4302b936b9e5f3b6b5a81ca589753d',
            'id'=>2
        );
        Common::verify($parameters, '/user/delUserBank');
    }
}