<?php
class LogintestController extends Yaf_Controller_Abstract
{
    //测试登出接口
    public function logoutAction(){
        $parameters = array(
            'token'=>'52063d34890ef22d22cc2c6cb3ac268f'
        );
        Common::verify($parameters, '/login/logout');
    }

    //测试发送重置密码短信接口
    public function sendResetPwdSmsCodeAction(){
        $parameters = array(
            'user_name' => '13781226666'
        );
        Common::verify($parameters, '/login/sendResetPwdSmsCode');
    }
    //测试登录接口
    public function loginAction()
    {
        $parameters = array(
            'user_name' => '13744466666',//'13200000001',
            'country_code'=>'+86',
            'pwd'=>'999999',
            'version'=>'3.8',
            'login_type'=>2
        );
        Common::verify($parameters, '/login/login');
    }
    public function addDeviceTokensAction(){
        $parameters = array(
            'token' => '9fced2aabea6d5507b9a2c0b40310c2e',
            "device_tokens" =>"7c437df7021e626ef2af7e2bb11340312137e258596df94fb87c5324b01aa6ab",
            'origin'=> 3
        );
        Common::verify($parameters, '/login/addDeviceTokens');
    }

    //修改密码
    public function resetPwdAction()
    {
        $parameters = array(
            'uid' => 12100,
            'pwd'=>'111111'
        );
        Common::verify($parameters, '/login/resetPwd');
    }

    //发送短信验证登录验证码
    public function sendLoginSmsCodeAction(){
        $parameters = array(
            'user_name' => '12545214',
           // 'check_code'=>'841074',
            'country_code'=>'+61'
        );
        Common::verify($parameters, '/login/sendLoginSmsCode');
    }
    public function smsCodeLoginAction(){
        $parameters = array(
            'user_name' => '12545214',
            'check_code'=>'841074',
            'country_code'=>'+61'
        );
        Common::verify($parameters, '/login/smsCodeLogin');
    }
    public function setUserTagTypeAction(){
        $parameters = array(
            'token' => 'e7e3d4b76ead4fe2edb4b1c5499f0bee'
        );
        Common::verify($parameters, '/login/setUserTagType');
    }
    public function getUserTagTypeAction(){
        $parameters = array(
            'token' => '97a51193b5f9ea46fa4e1ad4ca54900f'
        );
        Common::verify($parameters, '/login/getUserTagType');
    }
    public function getLoginBannerAction(){
        $parameters = array(
            //'token' => '97a51193b5f9ea46fa4e1ad4ca54900f'
        );
        Common::verify($parameters, '/login/getLoginBanner');
    }
}