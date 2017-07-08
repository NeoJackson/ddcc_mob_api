<?php
class LoginController extends Yaf_Controller_Abstract {
    public function init(){
        $this->startTime = microtime(true);
    }
    //用户登录
    public function loginAction(){
        $user_name = strtolower($this->getRequest()->getPost('user_name'));
        $pwd = $this->getRequest()->getPost('pwd');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : '';
        $userModel = new UserModel();
        $country_code = $this->getRequest()->getPost('country_code') ? $this->getRequest()->getPost('country_code') : '+86';//手机注册国家区号
        $login_type = $this->getRequest()->getPost('login_type') ? $this->getRequest()->getPost('login_type') : '1';//1代代号邮箱 2手机号
        if($version>='3.7.2'){
            if($login_type==1){
                $user = $userModel->getEmailByLogin($user_name);
                if(!$user){
                    $user = $userModel->getUserByDid($user_name);
                }
            }else{
                $user = $userModel->getUserByLogin($user_name,$country_code);
                if(!$user){
                    $bind_uid = $userModel->isBindNameUsed($user_name,$country_code);
                    if(!$bind_uid){
                        Common::echoAjaxJson(5,'用户名不存在');
                    }else{
                        $user = $userModel->getUserByUid($bind_uid);
                    }

                }
            }
        }else{
            if(filter_var($user_name, FILTER_VALIDATE_EMAIL)){
                $user = $userModel->getEmailByLogin($user_name);
            }elseif(preg_match('/^1[0-9]{10}$/',$user_name)){
                $user = $userModel->getUserByLogin($user_name,$country_code);
            }elseif(preg_match('/^[1-9][0-9]{5,9}$/',$user_name)){
                $user = $userModel->getUserByDid($user_name);
            }else {
                Common::echoAjaxJson(4, '请输入正确的手机号、邮箱或代代号');
            }
        }
        if(!$user){
            Common::echoAjaxJson(5,'用户名不存在');
        }
        $pwd = base64_decode($pwd);
        if(!preg_match('/^[\w!@#$%?.\^&\*\(\)_]{6,16}$/',$pwd)){
            Common::echoAjaxJson(3,'请输入6-16位数字、字母或常用符号区分大小写');
        }
        if($userModel->generatePassword('',$user['salt']) == $user['pwd']){
            Common::echoAjaxJson(8,'未设置本站密码，请用第三方账号登录');
        }
        if($userModel->generatePassword($pwd,$user['salt']) != $user['pwd']){
            Common::echoAjaxJson(6,'密码不正确');
        }
        if ($user['status'] == 2){
            Common::echoAjaxJson(7, '你的帐号已被冻结,请联系客服：13012888193');
        }
        $userModel->updateUserLogin($user['uid']);
        $tokenModel = new TokenModel();
        $token = $tokenModel->hasToken($user['uid']);
        Common::appLog('login/login',$this->startTime,$version);
        Common::echoAjaxJson(1,'登录成功',$token);
    }
    //用户登录3.8版本以上
    public function appLoginAction(){
        $user_name = strtolower($this->getRequest()->getPost('user_name'));
        $pwd = $this->getRequest()->getPost('pwd');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : '';
        $userModel = new UserModel();
        $country_code = $this->getRequest()->getPost('country_code') ? $this->getRequest()->getPost('country_code') : '+86';//手机注册国家区号
        $login_type = $this->getRequest()->getPost('login_type') ? $this->getRequest()->getPost('login_type') : '1';//1代代号邮箱国内手机号 2国外手机号
        if($login_type==1){
            if(filter_var($user_name, FILTER_VALIDATE_EMAIL)){
                $user = $userModel->getEmailByLogin($user_name);
            }elseif(preg_match('/^1[0-9]{10}$/',$user_name)){
                $user = $userModel->getUserByLogin($user_name,$country_code);
            }elseif(preg_match('/^[1-9][0-9]{5,9}$/',$user_name)){
                $user = $userModel->getUserByDid($user_name);
            }else {
                Common::echoAjaxJson(4, '请输入正确的手机号、邮箱或代代号，或选择选择下方的“国内外手机登录”');
            }
        }else{
            $user = $userModel->getUserByLogin($user_name,$country_code);
        }

        if(!$user){
            $bind_uid = $userModel->isBindNameUsed($user_name,$country_code);
            if(!$bind_uid){
                Common::echoAjaxJson(5,'您还不是才府用户，请先注册');
            }else{
                $user = $userModel->getUserByUid($bind_uid);
            }
        }
        if(!$user){
            Common::echoAjaxJson(5,'您还不是才府用户，请先注册');
        }
        $pwd = base64_decode($pwd);
        if(!preg_match('/^[\w!@#$%?.\^&\*\(\)_]{6,16}$/',$pwd)){
            Common::echoAjaxJson(3,'密码错误，请重新输入');
        }
        if($userModel->generatePassword('',$user['salt']) == $user['pwd']){
            Common::echoAjaxJson(8,'未设置本站密码，请用第三方账号登录');
        }
        if($userModel->generatePassword($pwd,$user['salt']) != $user['pwd']){
            Common::echoAjaxJson(6,'密码错误，请重新输入');
        }
        if ($user['status'] == 2){
            Common::echoAjaxJson(7, '你的帐号已被冻结,请联系客服：13012888193');
        }
        $userModel->updateUserLogin($user['uid']);
        $tokenModel = new TokenModel();
        $token = $tokenModel->hasToken($user['uid']);
        Common::appLog('login/login',$this->startTime,$version);
        Common::echoAjaxJson(1,'登录成功',$token);
    }
    //用户添加手机识别码
    public function addDeviceTokensAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $device_tokens = $this->getRequest()->getPost('device_tokens');
        $origin = $this->getRequest()->getPost('origin');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$device_tokens){
            Common::echoAjaxJson(2, "用户手机识别码不能为空");
        }
        if(!$origin||!in_array($origin,array(3,4))){
            Common::echoAjaxJson(5, "请标明来源");
        }
        $tokenModel = new TokenModel();
        $rs = $tokenModel->modifyDeviceTokens($user['uid'],$device_tokens,$origin);
        if($rs == -1){
            Common::echoAjaxJson(3, "添加失败");
        }
        Common::appLog('login/addDeviceTokens',$this->startTime,$version);
        Common::echoAjaxJson(1, "添加成功");
    }
    /*
     * 用户登出
     */
    public function logoutAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $token =$this->getRequest()->getPost("token");
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $tokenModel = new TokenModel();
        $userModel = new UserModel();
        $tokenModel->delToken($token);
        $userModel->clearUserLogin($user['uid']);
        Common::appLog('login/logout',$this->startTime,$version);
        Common::echoAjaxJson(1,'登出成功');
    }
    /**
     *  发送重置密码短信验证码
     */
    public function sendResetPwdSmsCodeAction(){
        $user_name = strtolower($this->getRequest()->getPost('user_name'));
        $country_code = $this->getRequest()->getPost('country_code') ? $this->getRequest()->getPost('country_code') : '+86';//国家区号
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
//        if(!preg_match('/^1[0-9]{10}$/',$user_name)){
//            Common::echoAjaxJson(2,'请输入正确的11位手机号');
//        }
        $userModel = new UserModel();
        $user = $userModel->getUserByUserName($user_name,$country_code);
        if(!$user){
            $bind_uid = $userModel->isBindNameUsed($user_name,$country_code);
            if(!$bind_uid){
                Common::echoAjaxJson(3,"用户不存在");
            }
            $uid = $bind_uid;
        }else{
            $uid = $user['uid'];
        }
        if($country_code =='+86'){
            $sms_type=1;
        }else{
            $sms_type=2;
        }
        $smsModel = new SmsModel();
        $status = $smsModel->addSmsCode($uid,$user_name,2,$sms_type,$country_code);
        if($status == -1){
            Common::echoAjaxJson(4,'输入的验证码不正确');
        }else if($status == -2){
            Common::echoAjaxJson(5,'24小时内发送的短信超出次数');
        }else if($status == -3){
            Common::echoAjaxJson(6,'短信发送太频繁');
        }else if($status == -4){
            Common::echoAjaxJson(7,'短信发送失败，请重新点击发送');
        }
        Common::appLog('login/sendResetPwdSmsCode',$this->startTime,$version);
        Common::echoAjaxJson(1,'验证码发送成功',$uid);
    }
    /**
     *  重置密码短信验证
     */
    public function resetPwdVerifySmsCodeAction(){
        $user_name = strtolower($this->getRequest()->getPost('user_name'));
        $check_code = $this->getRequest()->getPost('check_code');
        $country_code = $this->getRequest()->getPost('country_code') ? $this->getRequest()->getPost('country_code') : '+86';//国家区号
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
//        if(!preg_match('/^1[0-9]{10}$/',$user_name)){
//            Common::echoAjaxJson(2,'请输入正确的11位手机号');
//        }
        $userModel = new UserModel();
        $user = $userModel->getUserByUserName($user_name,$country_code);
        if(!$user){
            $bind_uid = $userModel->isBindNameUsed($user_name,$country_code);
            if(!$bind_uid){
                Common::echoAjaxJson(3,"用户不存在");
            }
        }
        if($check_code===''){
            Common::echoAjaxJson(6,'请输入验证码');
        }
        if(!preg_match('/^\d{6}$/',$check_code)){
            Common::echoAjaxJson(4,'输入的验证码不正确');
        }
        $smsModel = new SmsModel();
        $sms_info = $smsModel->getSmsCode($country_code.$user_name,2);
        if($sms_info['code'] != $check_code){
            Common::echoAjaxJson(5,'输入的验证码不正确');
        }
        Common::appLog('login/resetPwdVerifySmsCode',$this->startTime,$version);
        Common::echoAjaxJson(1,'验证成功');
    }
    /*
     * 重置密码接口
     */
    public function resetPwdAction(){
        $pwd = $this->getRequest()->getPost('pwd');
        $uid = $this->getRequest()->getPost('uid');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!preg_match('/^[\w!@#$%?\^&\*\(\)_]{6,16}$/',$pwd)){
            Common::echoAjaxJson(2,'请输入6-16位数字、字母或常用符号区分大小写');
        }
        if(!$uid){
            Common::echoAjaxJson(3,"请重新获取验证码");
        }
        $userModel = new UserModel();
        $rs = $userModel->resetPwd($uid,$pwd);
        if($rs == -1){
            Common::echoAjaxJson(4,"用户不存在");
        }else if($rs == -2){
            Common::echoAjaxJson(5,"修改密码失败");
        }
        Common::appLog('login/resetPwd',$this->startTime,$version);
        Common::echoAjaxJson(1,"密码重置成功");
    }
    //发送短信验证登录验证码
    public function sendLoginSmsCodeAction(){
        $user_name = strtolower($this->getRequest()->getPost('user_name'));
        $country_code = $this->getRequest()->getPost('country_code') ? $this->getRequest()->getPost('country_code') : '+86';//国家区号
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
//        if(!preg_match('/^1[0-9]{10}$/',$user_name)){
//            Common::echoAjaxJson(2,'请输入正确的11位手机号');
//        }
        $userModel = new UserModel();
        $user = $userModel->getUserByUserName($user_name,$country_code);
        $bind_uid = $userModel->isBindNameUsed($user_name,$country_code);
        if(!$user){
            Common::echoAjaxJson(3,"您还不是才府用户，请先注册");
        }else{
            $uid = $user['uid'];
        }
        if($user){
            $uid = $user['uid'];
        }
        if($bind_uid){
            $uid = $bind_uid;
        }
        if ($user['status'] == 2){
            Common::echoAjaxJson(8, '你的帐号已被冻结,请联系客服：13012888193');
        }
        $smsModel = new SmsModel();
        if($country_code =='+86'){
            $sms_type=1;
        }else{
            $sms_type=2;
        }
        $status = $smsModel->addSmsCode($uid,$user_name,7,$sms_type,$country_code);
        if($status == -1){
            Common::echoAjaxJson(4,'输入的验证码不正确');
        }else if($status == -2){
            Common::echoAjaxJson(5,'24小时内发送的短信超出次数');
        }else if($status == -3){
            Common::echoAjaxJson(6,'短信发送太频繁');
        }else if($status == -4){
            Common::echoAjaxJson(7,'短信发送失败，请重新点击发送');
        }
        Common::appLog('login/sendLoginSmsCode',$this->startTime,$version);
        Common::echoAjaxJson(1,'验证码发送成功',$uid);
    }
    //手机验证码登录
    public function smsCodeLoginAction(){
        $user_name = strtolower($this->getRequest()->getPost('user_name'));
        $check_code = $this->getRequest()->getPost('check_code');
        $country_code = $this->getRequest()->getPost('country_code') ? $this->getRequest()->getPost('country_code') : '+86';//国家区号
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if($check_code===''){
            Common::echoAjaxJson(2,'请输入验证码');
        }
        if(!preg_match('/^\d{6}$/',$check_code)){
            Common::echoAjaxJson(3,'验证码格式错误');
        }
        $smsModel = new SmsModel();
        $userModel = new UserModel();
        $user = $userModel->getUserByUserName($user_name,$country_code);
        if(!$user){
            Common::echoAjaxJson(4,'您还不是才府用户，请先注册');
        }
        $sms_info = $smsModel->getSmsCode($country_code.$user_name,7);
        if($sms_info['code'] != $check_code){
            Common::echoAjaxJson(4,'输入的验证码不正确');
        }

        $userModel->updateUserLogin($user['uid']);
        $tokenModel = new TokenModel();
        $token = $tokenModel->hasToken($user['uid']);
        Common::appLog('login/smsCodeLogin',$this->startTime,$version);
        Common::echoAjaxJson(1,'登录成功',$token);
    }
    //打开应用设置
    public function openSetAction(){
        $user_name = strtolower($this->getRequest()->getPost('user_name'));
        $lng = strtolower($this->getRequest()->getPost('lng'));
        $lat = strtolower($this->getRequest()->getPost('lat'));
        $device_tokens = strtolower($this->getRequest()->getPost('device_tokens'));
        $origin= strtolower($this->getRequest()->getPost('origin'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        $user = $userModel->getUserByUserName($user_name);
        if(!$user){
            $user = $userModel->getUserByDid($user_name);
        }
        if(!$user){
            Common::echoAjaxJson(5,'用户名不存在');
        }
        if ($user['status'] == 2){
            Common::echoAjaxJson(4, '你的帐号已被冻结,请联系客服：13012888193');
        }
        $userModel->openSet($user['uid']);
        $tokenModel = new TokenModel();
        $tokenModel->modifyDeviceTokens($user['uid'],$device_tokens,$origin);
        $token = $tokenModel->hasToken($user['uid']);
        if($lat&&$lng){
            $userModel = new UserModel();
            $data = $userModel->getAppUserInfo($user['uid']);
            if(!$data){
                $userModel->setUserCoordinate($user['uid'],$lng,$lat);
            }else{
                $userModel->updateUserCoordinate($data['id'],$lng,$lat);
            }
        }
        Common::appLog('login/openSet',$this->startTime,$version);
        Common::echoAjaxJson(1,'设置成功',$token);
    }
    //用户登录APP设置兴趣标签弹层标识
    public function setUserTagTypeAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $userModel = new UserModel();
        $userModel->setUserTagType($user['uid']);
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        Common::appLog('login/setUserTagType',$this->startTime,$version);
        Common::echoAjaxJson(1,'设置成功');
    }
    //用户登录APP获取兴趣标签弹层标识
    public function getUserTagTypeAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $userModel = new UserModel();
        $rs = $userModel->getUserTagType($user['uid']);
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        Common::appLog('login/getUserTagType',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$rs);
    }
    //用户登录获取APP广告
    public function getLoginBannerAction(){
        $token = $this->getRequest()->getPost('token');//用户登录token
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') : APP_VERSION;//版本名
        $indexModel = new IndexModel();
        $rs = $indexModel->indexBanner('app_login',$token,$version);
        $list = $rs ? $rs : array();
        Common::appLog('login/getLoginBanner',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    //用户添加手机识别码
    public function addRegistrationIdAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $registrationId = $this->getRequest()->getPost('registrationid');
        $origin = $this->getRequest()->getPost('origin');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$registrationId){
            Common::echoAjaxJson(2, "用户手机识别码不能为空");
        }
        if(!$origin||!in_array($origin,array(3,4))){
            Common::echoAjaxJson(5, "请标明来源");
        }
        $tokenModel = new TokenModel();
        $rs = $tokenModel->modifyRegistrationId($user['uid'],$registrationId,$origin);
        if($rs == -1){
            Common::echoAjaxJson(3, "添加失败");
        }
        Common::appLog('login/addRegistrationId',$this->startTime,$version);
        Common::echoAjaxJson(1, "添加成功");
    }
}
?>