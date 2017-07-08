<?php
class RegtestController extends Yaf_Controller_Abstract
{
    //测试注册接口
    public function mobileAction(){
        $parameters = array(
            'user_name' =>'13400000004',
            'nick_name' =>'推广购买小号2',
            'avatar' =>'img_62.png',
            'pwd' =>'999999',
            'intro'=>'推广分享小号推广分享小号推广分享小号推广分享小号推广分享小号',
            'origin'=>4
        );
        Common::verify($parameters, '/reg/mobile');
    }
    //测试昵称是否存在
    public function nickNameIsExistAction(){
        $parameters = array(
            'user_name' =>'13244556655',
            'nick_name' =>'爸爸爷爷奶奶',
            'pwd' =>'999999',
        );
        Common::verify($parameters, '/reg/nickNameIsExist');
    }
    //测试校验用户名和登录密码接口
    public function verifyUserNameAndPwdAction(){
        $parameters = array(
            'user_name' =>'18930080155',
            'pwd'=>'dsdd_ss@aa11'
        );
        Common::verify($parameters, '/reg/verifyUserNameAndPwd');
    }
    //测试注册时发送手机验证码接口
    public function sendSmsCodeAction(){
        $parameters = array(
            'user_name' =>'1880000002',
            "reg_country_code" => "+86"

        );
        Common::verify($parameters, '/reg/sendSmsCode');
    }
    //测试注册短信验证码校验接口
    public function verifyRegSmsCodeAction(){
        $parameters = array(
            'user_name' =>'16600000000',
            'check_code'=>'754194',
            "reg_country_code" => "+86",
        );
        Common::verify($parameters, '/reg/verifyRegSmsCode');
    }
    public function modifyUserNameAction(){
        $parameters = array(
            'user_name' =>'13654224557',
        );
        Common::verify($parameters, '/reg/modifyUserName');
    }
    public function getCountryAction(){
        $parameters = array(
            //'user_name' =>'13654224557',
        );
        Common::verify($parameters, '/reg/getCountry');
    }
}