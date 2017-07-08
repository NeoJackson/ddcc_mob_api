<?php
class RegController extends Yaf_Controller_Abstract {
    public function init(){
        $this->startTime = microtime(true);
    }
    /**
     *  手机注册
     */
    public function mobileAction(){
        $user_name = strtolower($this->getRequest()->getPost('user_name'));//用户名
        $nick_name = $this->getRequest()->getPost('nick_name');//昵称
        $pwd = $this->getRequest()->getPost('pwd');//登录密码
        $pwd_type['pwd_type'] =Common::getPwdType($pwd);//密码强度
        $pwd_type=$pwd_type['pwd_type'];
        $avatar = $this->getRequest()->getPost('avatar');//用户头像
        $intro = $this->getRequest()->getPost('intro');//个人简介
        $origin = $this->getRequest()->getPost('origin');//用户来源
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $reg_country_code = $this->getRequest()->getPost('reg_country_code') ? $this->getRequest()->getPost('reg_country_code') : '+86';//手机注册国家区号
        $birthday_status = 4;//生日权限 4:仅自己可见
        $birthday_type = 1;//生日类型  1：阳历
        $userModel = new UserModel();
        $rs = $userModel->nickNameIsExist($nick_name);
        if($rs == -1){
            Common::echoAjaxJson(2,'昵称为2-8位的中文或中文加数字。');
        }
        if(!preg_match('/^[\x{4e00}-\x{9fa5}0-9]{2,8}$/u',$nick_name)){
            Common::echoAjaxJson(3,'昵称为2-8位的中文或中文加数字。');
        }
        if(preg_match('/^[0-9]*$/',$nick_name)){
            Common::echoAjaxJson(3,'昵称为2-8位的中文或中文加数字。');
        }
        if(Common::badWord($nick_name)){
            Common::echoAjaxJson(4,'昵称含有敏感词');
        }
        if($rs > 0){
            Common::echoAjaxJson(5,'此昵称太受欢迎，已有人抢了');
        }
        if(!$avatar){
            Common::echoAjaxJson(16,"请上传头像");
        }
        if(!$user_name){
            Common::echoAjaxJson(6,'请输入手机号');
        }
        if($reg_country_code=='+86'&&!preg_match('/^1[0-9]{10}$/',$user_name)){
            Common::echoAjaxJson(7,'请输入正确的手机号');
        }elseif(!preg_match('/^[0-9]{5,20}$/',$user_name)){
            Common::echoAjaxJson(7,'请输入正确的手机号');
        }
        $ret = $userModel->isBindNameUsed($user_name,$reg_country_code);
        if($ret){
            Common::echoAjaxJson(15,"此手机号已被绑定，无法注册");
        }
        $list =array();
        $list[]=$user_name;
        $rs = $userModel->userNameIsExist($list,$reg_country_code);
        if($rs){
            Common::echoAjaxJson(8,'手机号已注册，请登录或找回密码');
        }
        if(!preg_match('/^[\w!@#$%?\^&\*\(\)_]{6,16}$/',$pwd)){
            Common::echoAjaxJson(9,'请输入6-16位数字、字母或常用符号区分大小写');
        }
        if(!in_array($pwd_type,array(1,2,3))){
            Common::echoAjaxJson(10,'密码类型不正确');
        }

        if($intro){
            if(preg_match('/[A-Za-z]/',$intro)){
                Common::echoAjaxJson(11,'用户简介不能包含英文字符');
            }
            $intro_num = mb_strlen($intro,'utf-8');
            if($intro_num > 70){
                Common::echoAjaxJson(12,'您输入的的简介超出指定长度');
            }
        }
        if(!$origin){
            Common::echoAjaxJson(13,'请标明用户来源');
        }
        $security = new Security();
        $intro = $security->xss_clean($intro);
        $uid = $userModel->addUser($user_name,$nick_name,$avatar,$pwd,$pwd_type,2,1,$reg_country_code);
        if(!$uid){
            Common::echoAjaxJson(14, '注册失败');
        }
        $userModel->addUserInfo($uid,$origin,$birthday_type,$birthday_status,$intro,$user_name);
        $userModel->updateUserLogin($uid);
        $tokenModel = new TokenModel();
        $token = $tokenModel->hasToken($uid);
        Common::appLog('reg/modifyUserName',$this->startTime,$version);
        Common::echoAjaxJson(1, '注册成功',$token);
    }
    /**
     *  发送短信验证码
     */
    public function sendSmsCodeAction(){
        $user_name = intval($this->getRequest()->getPost('user_name'));
        $reg_country_code = $this->getRequest()->getPost('reg_country_code') ? $this->getRequest()->getPost('reg_country_code') : '+86';//手机注册国家区号
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$user_name){
            Common::echoAjaxJson(2,'请输入手机号');
        }
        $smsModel = new SmsModel();
        if($reg_country_code=='+86'&&!preg_match('/^1[0-9]{10}$/',$user_name)){
            Common::echoAjaxJson(6,'请输入正确的手机号');
        }elseif(!preg_match('/^[0-9]{6,20}$/',$user_name)){
            Common::echoAjaxJson(6,'请输入正确的手机号');
        }
        if($reg_country_code=='+86'){
            $sms_type=1;
        }else{
            $sms_type=2;
        }
        $status = $smsModel->addSmsCode(0,$user_name,1,$sms_type,$reg_country_code);
        if($status == -1){
            Common::echoAjaxJson(2,'验证码类型不正确');
        }else if($status == -2){
            Common::echoAjaxJson(3,'24小时内发送的短信超出次数');
        }else if($status == -3){
            Common::echoAjaxJson(4,'短信发送太频繁');
        }else if($status == -4){
            Common::echoAjaxJson(5,'短信发送失败，请重新点击发送');
        }
        Common::appLog('reg/sendSmsCode',$this->startTime,$version);
        Common::echoAjaxJson(1,'验证码发送成功');
    }
    /**
     *  校验注册短信验证码
     */
    public function verifyRegSmsCodeAction(){
        $user_name = strtolower($this->getRequest()->getPost('user_name'));
        $reg_country_code = $this->getRequest()->getPost('reg_country_code') ? $this->getRequest()->getPost('reg_country_code') : '+86';//手机注册国家区号
        $check_code = $this->getRequest()->getPost('check_code');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if($check_code===''){
            Common::echoAjaxJson(2,'请输入验证码');
        }
        if(!preg_match('/^\d{6}$/',$check_code)){
            Common::echoAjaxJson(3,'验证码格式错误');
        }
        $smsModel = new SmsModel();
        $sms_info = $smsModel->getSmsCode($reg_country_code.$user_name,1);
        if($sms_info['code'] != $check_code){
            Common::echoAjaxJson(4,'输入的验证码无效');
        }
        $smsModel->updateSmsCodeExpireTime($sms_info['id']);
        Common::appLog('reg/verifyRegSmsCode',$this->startTime,$version);
        Common::echoAjaxJson(1,'验证成功');
    }

    /**
     * 判断昵称是否存在
     */
    public function nickNameIsExistAction() {
        $nick_name = $this->getRequest()->getPost('nick_name');
        $user_name = strtolower($this->getRequest()->getPost('user_name'));//用户名
        $pwd = $this->getRequest()->getPost('pwd');//登录密码
        $reg_country_code = $this->getRequest()->getPost('reg_country_code') ? $this->getRequest()->getPost('reg_country_code') : '+86';//手机注册国家区号
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $user = new UserModel();
        $rs = $user->nickNameIsExist($nick_name);
        if($rs == -1){
            Common::echoAjaxJson(2,'昵称为2-8位的中文或中文加数字。');
        }
        if(!preg_match('/^[\x{4e00}-\x{9fa5}0-9]{2,8}$/u',$nick_name)){
            Common::echoAjaxJson(3,'昵称为2-8位的中文或中文加数字。');
        }
        if(preg_match('/^[0-9]*$/',$nick_name)){
            Common::echoAjaxJson(3,'昵称为2-8位的中文或中文加数字。');
        }
        if(Common::badWord($nick_name)){
            Common::echoAjaxJson(4,'昵称含有敏感词');
        }
        if($rs > 0){
            Common::echoAjaxJson(5,'此昵称太受欢迎，已有人抢了');
        }
        if(!$user_name){
            Common::echoAjaxJson(6,'请输入手机号');
        }
        if($reg_country_code=='+86'&&!preg_match('/^1[0-9]{10}$/',$user_name)){
            Common::echoAjaxJson(7,'请输入正确的手机号');
        }elseif(!preg_match('/^[0-9]{5,20}$/',$user_name)){
            Common::echoAjaxJson(7,'请输入正确的手机号');
        }
        $list =array();
        $list[]=$user_name;
        $rs = $user->userNameIsExist($list,$reg_country_code);
        if($rs){
            Common::echoAjaxJson(8,'手机号已注册，请登录或找回密码');
        }
        $ret = $user->isBindNameUsed($user_name,$reg_country_code);
        if($ret){
            Common::echoAjaxJson(10,'此手机号已被绑定');
        }
        if(!preg_match('/^[\w!@#$%?\^&\*\(\)_]{6,16}$/',$pwd)){
            Common::echoAjaxJson(9,'请输入6-16位数字、字母或常用符号区分大小写');
        }
        Common::appLog('reg/nickNameIsExist',$this->startTime,$version);
        Common::echoAjaxJson(1,'注册信息符合要求');
    }
    //验证昵称
    public function modifyNickNameAction(){
        $nick_name = $this->getRequest()->getPost('nick_name');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $user = new UserModel();
        $rs = $user->nickNameIsExist($nick_name);
        if($rs == -1){
            Common::echoAjaxJson(2,'昵称为2-8位的中文或中文加数字。');
        }
        if(!preg_match('/^[\x{4e00}-\x{9fa5}0-9]{2,8}$/u',$nick_name)){
            Common::echoAjaxJson(3,'昵称为2-8位的中文或中文加数字。');
        }
        if(preg_match('/^[0-9]*$/',$nick_name)){
            Common::echoAjaxJson(3,'昵称为2-8位的中文或中文加数字。');
        }
        if(Common::badWord($nick_name)){
            Common::echoAjaxJson(4,'昵称含有敏感词');
        }
        if($rs > 0){
            Common::echoAjaxJson(5,'此昵称太受欢迎，已有人抢了');
        }
        Common::appLog('reg/modifyNickName',$this->startTime,$version);
        Common::echoAjaxJson(1,'昵称未被人使用');
    }
    //验证用户名
    public function modifyUserNameAction(){
        $user_name = strtolower($this->getRequest()->getPost('user_name'));//用户名
        $reg_country_code = $this->getRequest()->getPost('reg_country_code') ? $this->getRequest()->getPost('reg_country_code') : '+86';//国家区号
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $user = new UserModel();
        if(!$user_name){
            Common::echoAjaxJson(2,'请输入手机号');
        }
        if($reg_country_code=='+86'){
            if(!preg_match('/^1[0-9]{10}$/',$user_name)){
                Common::echoAjaxJson(3,'请输入正确的手机号');
            }
        }else{
            if(!preg_match('/^[0-9]{6,20}$/',$user_name)){
                Common::echoAjaxJson(3,'请输入正确的手机号');
            }
        }
        $list =array();
        $list[]=$user_name;
        $rs = $user->userNameIsExist($list,$reg_country_code);
        if($rs){
            Common::echoAjaxJson(4,'手机号已注册，请登录或找回密码');
        }
        $ret = $user->isBindNameUsed($user_name,$reg_country_code);
        if($ret){
            Common::echoAjaxJson(5,'此手机号已被绑定');
        }
        Common::appLog('reg/modifyUserName',$this->startTime,$version);
        Common::echoAjaxJson(1,'该手机号可以注册');
    }
    /**
     * 校验用户名和登录密码
     */
    public function verifyUserNameAndPwdAction() {
        $user_name = strtolower($this->getRequest()->getPost('user_name'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $reg_country_code = $this->getRequest()->getPost('reg_country_code') ? $this->getRequest()->getPost('reg_country_code') : '+86';//手机注册国家区号
        $pwd = $this->getRequest()->getPost('pwd');
        $userModel= new UserModel();
        if(!$user_name){
            Common::echoAjaxJson(9,'请输入手机号');
        }
        if(!preg_match('/^1[0-9]{10}$/',$user_name)){
            Common::echoAjaxJson(10,'请输入正确的手机号');
        }
        $list =array();
        $list[]=$user_name;
        $rs = $userModel->userNameIsExist($list,$reg_country_code);
        if($rs){
            Common::echoAjaxJson(11,'手机号已注册，请登录或找回密码');
        }
        if(!preg_match('/^[\w!@#$%?\^&\*\(\)_]{6,16}$/',$pwd)){
            Common::echoAjaxJson(12,'请输入6-16位数字、字母或常用符号区分大小写');
        }
        Common::appLog('reg/verifyUserNameAndPwd',$this->startTime,$version);
        Common::echoAjaxJson(1,"该手机号可以注册");
    }
    //获取海外国家数据
    public function getCountryAction(){
        $token = $this->getRequest()->getPost("token");
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $commonModel = new CommonModel();
        $list = $commonModel->getCountry();
        Common::echoAjaxJson(1,"获取成功",$list);
    }
}
?>