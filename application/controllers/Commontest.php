<?php
class CommontestController extends Yaf_Controller_Abstract
{
    //用户签到
    public function userCheckinAction(){
        $parameters = array(
            'token' =>'fc5143d104508b68d59563552cbbf0b7'
        );
        Common::verify($parameters, '/common/userCheckin');
    }
    //获取地址列表
    public function getAddressListAction(){
        $parameters = array();
        Common::verify($parameters, '/common/getAddressList');
    }
    //测试获取地址接口
    public function getChildAddressListAction(){
        $parameters = array(
            'pid' =>'0',
        );
        Common::verify($parameters, '/common/getChildAddressList');
    }
    //测试获取登录用户信息接口
    public function getUserAction()
    {
        $parameters = array(
            'token' => 'e681505946a3e27264b7cbdd53f7b6ce'
        );
        Common::verify($parameters, '/common/getUser');
    }
    //设置用户坐标
    public function setUserCoordinateAction(){
        $parameters = array(
            'token' =>'7420cafb38c40d50e3791e9df793fa02',
            'lng'=>'121.5270340000',
            'lat'=>'31.2361450000'
        );
        Common::verify($parameters, '/common/setUserCoordinate');
    }
    //打赏
    public function rewardAction(){
        $parameters = array(
            'token' =>'301bf943c0380430a6500a9cb75fc49d',
            'obj_id'=>'54797',
            'type'=>1,
            'reward_type'=>1,
            'score_value'=>55,
            'content'=>'哈哈哈'
        );
        Common::verify($parameters, '/common/reward');
    }
    //判断打赏权限
    public function conditionAction(){
        $parameters = array(
            'token' =>'25bf67b91ce56abc17112178224f3da4',
            'type'=>1,
            'obj_id'=>52087
        );
        Common::verify($parameters, '/common/condition');

    }
    //喜欢列表
    public function likeListAction(){
        $parameters = array(
            'obj_id'=>'48894',
            'type'=>1
        );
        Common::verify($parameters, '/common/likeList');
    }
    //参与列表
    public function partakeListAction(){
        $parameters = array(
            'token' => '25bf67b91ce56abc17112178224f3da4',
            'obj_id'=>'1013',
            'type'=>1
        );
        Common::verify($parameters, '/common/partakeList');
    }
    //喜欢列表
    public function rewardListAction(){
        $parameters = array(
            'token' =>'42c79355479b3a0231fa718bb151ed07',
            'obj_id'=>'58769',
            'type'=>1,
            'last_time'=>'0'
        );
        Common::verify($parameters, '/common/rewardList');
    }
    //获取某个用户的信息
    public function getUserDataAction(){
        $parameters = array(
            'token' =>'a1a271b917706fc009a0838ba1087b40',
            'id'=>'12083&3482'
        );
        Common::verify($parameters, '/common/getUserData');
    }
    //批量上传图片
    public function uploadImgsAction(){
        $parameters = array(
            'Filedata'=> Array(
                array(
                    'name' => '5s-地址图标.png',
                    'type' => 'application/octet-stream',
                    'tmp_name' => '/tmp/phpwVbgpK',
                    'error' => 0,
                    'size' => 1425
                ),
                array(
                    'name' => '5s-收藏图标.png',
                    'type' => 'application/octet-stream',
                    'tmp_name' => '/tmp/php1UmLIr',
                    'error' => 0,
                    'size' => 1194
                ),
            )
        );
        Common::verify($parameters, '/common/uploadImgs');
    }
    //添加手机联系人
    public function addMobileUserAction(){
        $parameters = array(
            'token' =>'7420cafb38c40d50e3791e9df793fa02',
            'user_name' =>'13300000000&18930080155&13600000000'
        );
        Common::verify($parameters, '/common/addMobileUser');
    }
    //生成二维码
    public function getQrCodeAction(){
        $parameters = array(
            'obj_id' =>853,
            'type' =>6,
            'size'=>3,
            'p_uid'=>12788
        );
        Common::verify($parameters, '/common/getQrCode');
    }

    //获得html发布心境url
    public function getAddMoodUrlAction(){
        $parameters = array(
            'token' =>'7420cafb38c40d50e3791e9df793fa02',
        );
        Common::verify($parameters, '/common/getAddMoodUrl');
    }
    //验证版本
    public function verifyVersionAction(){
        $parameters = array(
            'token' =>'34f516a164b5b87514bcead320a34a3f',
            'type'=>'2',
            'version'=>'11'
        );
        Common::verify($parameters, '/common/verifyVersionNew');
    }

    public function testQrcodeAction() {
        PHPQRCode::createQRCode('http://sns.91ddcc.com/',array('logo'=>'http://img.91ddcc.com/14106015941785_avatar.png','w'=>400,'logoMargin'=>5));
    }

    //对外分享
    public function foreignShareAction(){
        $parameters = array(
            'id' =>'2918',
            //'url'=>'http://di.91ddcc.com/index/personSpecial',
            'type'=>'10',
            'token'=>'bfd41438dbbd44aa139eb9527d8d84de'
        );
        Common::verify($parameters, '/common/foreignShare');
    }

    //对外分享
    public function viewAppAction(){
        $parameters = array(
            'token'=>'056e493564ce1a2f2025cb805f0de0e7'
        );
        Common::verify($parameters, '/common/viewApp');
    }
    //对外分享
    public function setLoginRedisAction(){
        $parameters = array(
            'token'=>'52063d34890ef22d22cc2c6cb3ac268f'
        );
        Common::verify($parameters, '/common/setLoginRedis');
    }

    public function myLevelAction(){
        $parameters = array(
            'token'=>'52063d34890ef22d22cc2c6cb3ac268f'
        );
        Common::verify($parameters, '/common/myLevel');
    }

    //测试获取登录用户信息接口
    public function getAddressAction()
    {
        $parameters = array(

        );
        Common::verify($parameters, '/common/getAddress');
    }
    public function appUpdateTypeAction()
    {
        $parameters = array(
            'version'=>'3.7.1',
            'type'=>1
        );
        Common::verify($parameters, '/common/appUpdateType');
    }
    public function getLogisticsCompanyAction()
    {
        $parameters = array(

        );
        Common::verify($parameters, '/common/getLogisticsCompany');
    }
    public function getMobileBookFriendAction(){
        $user_json = array(
            0=>array(
                'mobile'=>'17301650402',
                'name'=>'老孙',
            ),
            1=>array(
                'mobile'=>'15951829621',
                'name'=>'李晨',
            ),
            2=>array(
                'mobile'=>'+8615026544701',
                'name'=>'周周',
            ),
            3=>array(
                'mobile'=>'15121148711',
                'name'=>'大米',
            ),
        );
        $parameters = array(
            'token'=>'cd6b31cb559109160469860b17ee6dde',
            'user_json'=>json_encode($user_json),
            'version'=>'3.7.2'
        );
        Common::verify($parameters, '/common/getMobileBookFriend');
    }
    public function getCommissionRateAction()
    {
        $parameters = array(
            'token'=>'cd6b31cb559109160469860b17ee6dde',
        );
        Common::verify($parameters, '/common/getCommissionRate');
    }

}