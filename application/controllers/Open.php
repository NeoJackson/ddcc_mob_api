<?php
class OpenController extends Yaf_Controller_Abstract {
    public function init(){
        $this->startTime = microtime(true);
    }
    //验证open_id
    public function isBindAction(){
        $open_id = $this->getRequest()->getPost('open_id');
        $type = $this->getRequest()->getPost('type');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$open_id){
            Common::echoAjaxJson(2,'open_id不能为空');
        }
        if(!$type||!in_array($type,array(1,2,3))){
            Common::echoAjaxJson(3,'第三方帐号类型不正确');
        }
        $userModel = new UserModel();
        $uid = $userModel->isBind($type,$open_id);
        if(!$uid){
            Common::echoAjaxJson(4,'您该第三方账号还未绑定');
        }
        $userModel->updateUserLogin($uid);
        $tokenModel = new TokenModel();
        $token = $tokenModel->hasToken($uid);
        Common::appLog('open/isBind',$this->startTime,$version);
        Common::echoAjaxJson(1,'登录成功',$token);
    }
    //第三方账号注册代代账号
    public function addUserAction(){
        $open_id = $this->getRequest()->getPost('open_id');
        $type = $this->getRequest()->getPost('type');
        $nick_name = $this->getRequest()->getPost('nick_name');
        $sex = $this->getRequest()->getPost('sex');
        $access_token = $this->getRequest()->getPost('access_token');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!in_array($type,array(1,2,3))){
            Common::echoAjaxJson(2,'第三方帐号类型不正确');
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
        $userModel = new UserModel();
        $status = $userModel->nickNameIsExist($nick_name);
        if($status != 0){
            Common::echoAjaxJson(5,'此昵称太受欢迎，已有人抢了');
        }
        if(!$sex){
            $sex =0;
        }
        $uid = $userModel->addBindUser($type,$nick_name,$open_id,'',1,'',$sex);
        $userModel->setToken($type,$open_id,$access_token,$uid,1);
        if(!$uid){
            Common::echoAjaxJson(8,"已经绑定，请直接登录");
        }
        $user = $userModel->getUserByUid($uid);
        $userModel->bindUser($user['uid'],$type,$open_id,1);//注册自动绑定
        $userModel->updateUserLogin($user['uid']);
        $tokenModel = new TokenModel();
        $token = $tokenModel->hasToken($user['uid']);
        Common::appLog('open/addUser',$this->startTime,$version);
        Common::echoAjaxJson(1,'添加成功',$token);
    }
    //第三方账号注册代代账号
    public function addUserForOpenAction(){
        $user_name = $this->getRequest()->getPost('user_name');//用户名
        $nick_name = $this->getRequest()->getPost('nick_name');//昵称
        $pwd = $this->getRequest()->getPost('pwd');//登录密码
        $pwd_type['pwd_type'] =Common::getPwdType($pwd);//密码强度
        $pwd_type=$pwd_type['pwd_type'];
        $avatar = $this->getRequest()->getPost('avatar');//用户头像
        $origin = $this->getRequest()->getPost('origin');//用户来源
        $reg_country_code = $this->getRequest()->getPost('country_code') ? $this->getRequest()->getPost('country_code') : '+86';//手机注册国家区号
        $open_id = $this->getRequest()->getPost('open_id');
        $type = $this->getRequest()->getPost('type');
        $access_token = $this->getRequest()->getPost('access_token');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $sex =0;
        if(!in_array($type,array(1,2,3))){
            Common::echoAjaxJson(2,'第三方帐号类型不正确');
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
        $userModel = new UserModel();
        $status = $userModel->nickNameIsExist($nick_name);
        if($status != 0){
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
        $ret = $userModel->isBindNameUsed($user_name,$reg_country_code);
        if($ret){
            Common::echoAjaxJson(10,"此手机号已被绑定，无法注册");
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
            Common::echoAjaxJson(11,'密码类型不正确');
        }
        $uid = $userModel->addBindUserForOpen($type,$nick_name,$open_id,$pwd,$pwd_type,$sex,$origin,$avatar,$user_name,$reg_country_code);
        $userModel->setToken($type,$open_id,$access_token,$uid,1);
        if(!$uid){
            Common::echoAjaxJson(12,"已经绑定，请直接登录");
        }
        $user = $userModel->getUserByUid($uid);
        $userModel->bindUser($user['uid'],$type,$open_id,1);//注册自动绑定
        $userModel->updateUserLogin($user['uid']);
        $tokenModel = new TokenModel();
        $token = $tokenModel->hasToken($user['uid']);
        Common::appLog('open/addUserForOpen',$this->startTime,$version);
        Common::echoAjaxJson(1,'添加成功',$token);
    }

    //第三方账号绑定手机账号
    public function bindUserForOpenAction(){
        $user_name = $this->getRequest()->getPost('user_name');//用户名

        $pwd = $this->getRequest()->getPost('pwd');//登录密码
        $country_code = $this->getRequest()->getPost('country_code') ? $this->getRequest()->getPost('country_code') : '+86';//手机注册国家区号
        $open_id = $this->getRequest()->getPost('open_id');
        $type = $this->getRequest()->getPost('type');
        $access_token = $this->getRequest()->getPost('access_token');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$user_name){
            Common::echoAjaxJson(2,'请输入手机号');
        }
        if($country_code=='+86'&&!preg_match('/^1[0-9]{10}$/',$user_name)){
            Common::echoAjaxJson(3,'请输入正确的手机号');
        }elseif(!preg_match('/^[0-9]{5,20}$/',$user_name)){
            Common::echoAjaxJson(3,'请输入正确的手机号');
        }
        $userModel = new UserModel();
        $ret = $userModel->isBindNameUsed($user_name,$country_code);
        if($ret){
            Common::echoAjaxJson(4,"此手机号已被绑定");
        }
        $pwd = base64_decode($pwd);
        if(!preg_match('/^[\w!@#$%?\^&\*\(\)_]{6,16}$/',$pwd)){
            Common::echoAjaxJson(5,'请输入6-16位数字、字母或常用符号区分大小写');
        }
        $user = $userModel->getUserByLogin($user_name,$country_code);
        if(!$user){
            Common::echoAjaxJson(8,'该用户不存在');
        }
        if($userModel->generatePassword($pwd,$user['salt']) != $user['pwd']){
            Common::echoAjaxJson(6,'密码不正确');
        }
        if($user['status'] == 2){
            Common::echoAjaxJson(7, '您要绑定的帐号已被冻结,请联系客服：13012888193');
        }
        $userModel->setToken($type,$open_id,$access_token,$user['uid'],2);
        $userModel->updateUserLogin($user['uid']);
        $tokenModel = new TokenModel();
        $token = $tokenModel->hasToken($user['uid']);
        Common::appLog('open/bindUserForOpen',$this->startTime,$version);
        Common::echoAjaxJson(1,'绑定成功',$token);
    }

}