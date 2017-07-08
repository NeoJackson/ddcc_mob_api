<?php
/**
 * @name SampleModel
 * @desc sample数据获取类, 可以访问数据库，文件，其它系统等
 * @author {&$AUTHOR&}
 */
class EmailModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
    }

    public function addCode($email,$type,$code){
        if(!in_array($type,array(1,2,3,4))){
            return -1;
        }
        //判断距离最新的一封邮件是否超过15分钟
        if($this->getEmailNumByTime($email,$type,86400) > 3 && ($this->getEmailNumByTime($email,$type,900) >0)){
            return -2;
        }
        $stmt = $this->db->prepare("insert into email (email,type,code,expire_time) values (:email,:type,:code,:expire_time)");
        $array = array(
            ':email' => $email,
            ':type' => $type,
            ':code' => $code,
            ':expire_time' => date("Y-m-d H:i:s",time()+86400)
        );
        $stmt->execute($array);
        return $this->db->lastInsertId();
    }

    public function getEmailNumByTime($email,$type,$time){
        $add_time = date("Y-m-d H:i:s",time() - $time);
        $stmt = $this->db->prepare("select count(*) as num from email where email = :email and type =:type and add_time > :add_time");
        $array = array(
            ':email' => $email,
            ':type' => $type,
            ':add_time' => $add_time
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    public function getCode($email,$type){
        $stmt = $this->db->prepare("select code from email where email = :email and type =:type and expire_time > :expire_time order by add_time desc limit 1");
        $array = array(
            ':email' => $email,
            ':type' => $type,
            ':expire_time' => date("Y-m-d H:i:s",time())
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['code'];
    }

    /**
     * 判断邮件验证码是否存在，如果存在更新过期时间
     * @param $code 1 注册 2找回密码
     * @return bool
     */
    public function hasCode($code,$type){
        if(!preg_match('/^[0-9a-z]{32}$/',$code)){
            return false;
        }
        if(!in_array($type,array(1,2,3))){
            return false;
        }
        $stmt = $this->db->prepare("select * from email where code = :code and type = :type order by id desc");
        $array = array(
            ':code' => $code,
            ':type' => $type
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$result){
            return false;
        }
        $user_name = $result['email'];
        $result['status'] = 1;
        if($type == 1){
            $userModel = new UserModel();
            $user = $userModel->getUserByUserName($user_name);
            //如果已经验证直接返回结果
            if($user['status'] != 0){
                return $result;
            }
        }
        if($result['expire_time'] < date("Y-m-d H:i:s")){
            $result['status'] = 0;
            return $result;
        }
        //更新过期时间
        $stmt = $this->db->prepare("update email set expire_time = :expire_time where id = :id");
        $array = array(
            ':expire_time' => date("Y-m-d H:i:s",time()+1800),
            ':id' => $result['id']
        );
        $stmt->execute($array);
        return $result;
    }

    public function regVerify($user,$code){
        $this->addCode($user['user_name'],1,$code);
        $content = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>无标题文档</title>
<style>
body{
	color: #555;
	line-height: 22px;
	background :#fff;
}
.clr {
clear: both;
height: 0;
line-height: 0;
font-size: 1px;
}
.pwd-reg {
	width: 545px;
	margin: 0 auto;
}
.pad_10_20 {
	padding: 10px 20px;
}
.pad_15 {
padding: 15px;
}

.pwd-reg .true-btn {
width: 140px;
margin: 0 auto;
}
.pwd-reg .true-btn a {
text-decoration: none;
}
.fr {
float: right;
}
.fl {
float: left;
}
.hgt10 {
clear: both;
height: 10px;
font-size: 1px;
}
.blue {
color: #389bbd;
}
.f_14 {
font-size: 14px;
}
.bold {
font-weight: bold;
}
.gray {
color: #888888;
}
.line_30 {
line-height: 30px;
}
.mail-log {
background: url('.STATIC_DOMAIN.'/images/base/mail_ico.jpg) no-repeat;
width: 58px;
height: 54px;
}

span {
font-size: 13px;
}
.true-btn a{
	display: inline-block;
	background-color: #37a2c4;
	background: -ms-linear-gradient(top, #41afd3,  #2f94b5);
	background:-moz-linear-gradient(top,#41afd3,  #2f94b5);
	background:-webkit-gradient(linear, 0% 0%, 0% 100%,from(#41afd3), to(#2f94b5));
	background: -webkit-gradient(linear, 0% 0%, 0% 100%, from(#41afd3), to(#2f94b5));
	background: -webkit-linear-gradient(top, #41afd3,  #2f94b5);
	background: -o-linear-gradient(top, #41afd3,  #2f94b5);
	border: 1px solid #2f8cac;
	color: #fff;
	line-height: 30px;
	padding: 0px 26px;
	cursor: pointer;
	-moz-border-radius: 3px;
	-webkit-border-radius: 3px;
	border-radius: 3px;
	font-size: 12px;
}
.true-btn a:hover{
	background-color: #2091b0;
    background: -ms-linear-gradient(top, #3ca7cb,  #1e81a1);
    background:-moz-linear-gradient(top,#3ca7cb,  #1e81a1);
    background:-webkit-gradient(linear, 0% 0%, 0% 100%,from(#3ca7cb), to(#1e81a1));
    background: -webkit-gradient(linear, 0% 0%, 0% 100%, from(#3ca7cb), to(#1e81a1));
    background: -webkit-linear-gradient(top, #3ca7cb,  #1e81a1);
    background: -o-linear-gradient(top, #3ca7cb,  #1e81a1);
}
.pad_10_20 {
padding: 10px 20px;
}
.pwd-reg .head-02 {
	background-color: #3b9cbb;
	background: -ms-linear-gradient(top, #47a4c2,  #2e93b4);
	background:-moz-linear-gradient(top,#47a4c2,  #2e93b4);
	background:-webkit-gradient(linear, 0% 0%, 0% 100%,from(#47a4c2), to(#2e93b4));
	background: -webkit-gradient(linear, 0% 0%, 0% 100%, from(#47a4c2), to(#2e93b4));
	background: -webkit-linear-gradient(top, #47a4c2,  #2e93b4);
	background: -o-linear-gradient(top, #47a4c2,  #2e93b4);

width: 547px;
height: 60px;
}
.pwd-reg .head-02 span{
	display: block;
	font-family: Microsoft YaHei,"微软雅黑体";
	font-size: 26px;
	line-height: 60px;
	color: #fff;
	padding-left: 25px;
}
.blue_round {
border: #9cd4e7 1px solid;
border-radius: 4px;
}
a{
	word-break:break-all;
　　word-wrap:break-word;
}
</style>
</head>
<body>
 <div class="pwd-reg center blue_round">
   <div class="head-02 center"><span>代代传承用户中心</span></div>
   <div class="pad_10_20">
      <div class="hgt10"></div>
      <div><span class="blue bold f_14">'.$user['nick_name'].'</span><span class="gray">（代代号：'.$user['did'].'）</span><span class="bold f_14">欢迎您入驻代代传承！</span></div>
      <div class="hgt10"></div><div class="hgt10"></div>
      <div class="resetting pad_15 border_all">
          <div class="resetting-info line_30"><span class="f_14">为了您能更好的体验代代传承的全部功能，如：找回密码、创建驿站、等级系统等，请验证您的邮箱一键激活您的帐号。</span></div>
          <div class="clr"></div>
      </div>
       <div class="hgt10"></div> <div class="hgt10"></div>
      <div class="true-btn center">
         <a  href="'.SNS_DOMAIN.'/reg/confirm/code/'.$code.'" style="text-decoration:none;">点击立即激活</a>
      </div>
      <div class="hgt10"></div>
      <div class="hgt10"> </div>
	  <span class=" fr gary">代代传承敬上</span>
      <div class="hgt10"></div>
      <div>
        <span class="f_14 line_25">如果以上按钮无法打开，请把下面的链接复制到浏览器地址栏中打开：</span><a href="'.SNS_DOMAIN.'/reg/confirm/code/'.$code.'" class="f_14 blue">'.SNS_DOMAIN.'/reg/confirm/code/'.$code.'</a>
      </div>
      <div class="hgt10"></div>
      <div>
        <span class="gary">这封信是由我们服务器自动发出的，请不要回复</span>
      </div>
    </div>
 </div>
</body>
</html>';
        $subject = '代代传承注册帐号激活';
        Mail::send($subject,$content,$user['user_name'],$user['nick_name']);
    }

    public function resetPwd($user,$code){
        $content = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>无标题文档</title>

<style>
body{
	color: #555;
	line-height: 22px;
	background :#fff;
}
.clr {
clear: both;
height: 0;
line-height: 0;
font-size: 1px;
}
.pwd-reg {
	width: 545px;
	margin: 0 auto;
}
.pad_10_20 {
	padding: 10px 20px;
}
.pad_15 {
padding: 15px;
}
.pwd-reg .true-btn {
width: 140px;
margin: 0 auto;
}
.pwd-reg .true-btn a{
text-decoration: none;
}
.fr {
float: right;
}
.fl {
float: left;
}
.hgt10 {
clear: both;
height: 10px;
font-size: 1px;
}
.blue {
color: #389bbd;
}
.f_14 {
font-size: 14px;
}
.bold {
font-weight: bold;
}
.gray {
color: #888888;
}
.line_30 {
line-height: 30px;
}
.mail-log {
background: url('.STATIC_DOMAIN.'/images/base/mail_ico.jpg) no-repeat;
width: 58px;
height: 54px;
}

span {
font-size: 13px;
}
.true-btn a{
	display: inline-block;
	background-color: #37a2c4;
	background: -ms-linear-gradient(top, #41afd3,  #2f94b5);
	background:-moz-linear-gradient(top,#41afd3,  #2f94b5);
	background:-webkit-gradient(linear, 0% 0%, 0% 100%,from(#41afd3), to(#2f94b5));
	background: -webkit-gradient(linear, 0% 0%, 0% 100%, from(#41afd3), to(#2f94b5));
	background: -webkit-linear-gradient(top, #41afd3,  #2f94b5);
	background: -o-linear-gradient(top, #41afd3,  #2f94b5);
	border: 1px solid #2f8cac;
	color: #fff;
	line-height: 30px;
	padding: 0px 26px;
	cursor: pointer;
	-moz-border-radius: 3px;
	-webkit-border-radius: 3px;
	border-radius: 3px;
	font-size: 12px;
}
.true-btn a:hover{
	background-color: #2091b0;
    background: -ms-linear-gradient(top, #3ca7cb,  #1e81a1);
    background:-moz-linear-gradient(top,#3ca7cb,  #1e81a1);
    background:-webkit-gradient(linear, 0% 0%, 0% 100%,from(#3ca7cb), to(#1e81a1));
    background: -webkit-gradient(linear, 0% 0%, 0% 100%, from(#3ca7cb), to(#1e81a1));
    background: -webkit-linear-gradient(top, #3ca7cb,  #1e81a1);
    background: -o-linear-gradient(top, #3ca7cb,  #1e81a1);
}

.pad_10_20 {
padding: 10px 20px;
}
.pwd-reg .head-02 {
	background-color: #3b9cbb;
	background: -ms-linear-gradient(top, #47a4c2,  #2e93b4);
	background:-moz-linear-gradient(top,#47a4c2,  #2e93b4);
	background:-webkit-gradient(linear, 0% 0%, 0% 100%,from(#47a4c2), to(#2e93b4));
	background: -webkit-gradient(linear, 0% 0%, 0% 100%, from(#47a4c2), to(#2e93b4));
	background: -webkit-linear-gradient(top, #47a4c2,  #2e93b4);
	background: -o-linear-gradient(top, #47a4c2,  #2e93b4);

width: 547px;
height: 60px;
}
.pwd-reg .head-02 span{
	display: block;
	font-family: Microsoft YaHei,"微软雅黑体";
	font-size: 26px;
	line-height: 60px;
	color: #fff;
	padding-left: 25px;
}
.blue_round {
border: #9cd4e7 1px solid;
border-radius: 4px;
}
a{
	word-break:break-all;
　　word-wrap:break-word;
}
</style>
</head>
<body>
 <div class="pwd-reg center blue_round">
   <div class="head-02 center">
	<span>代代传承用户中心</span>
   </div>
   <div class="pad_10_20">
      <div class="hgt10"></div>
      <div><span class="blue bold f_14">'.$user['nick_name'].'</span><span class="gray">（代代号：'.$user['did'].'）</span><span class="bold f_14">您好</span></div>
      <div class="hgt10"></div><div class="hgt10"></div>
      <div class="resetting pad_15 border_all">
          <div class="resetting-info line_30"><span class="f_14">代代传承已经收到了您的重置密码请求，请在24小时内点击下面的按钮重置密码。</span></div>
          <div class="clr"></div>
      </div>
       <div class="hgt10"></div> <div class="hgt10"></div>
      <div class="true-btn center">
         <a href="'.SNS_DOMAIN.'/login/emailResetPwd/code/'.$code.'" style="text-decoration: none;">设置新密码</a>
      </div>
      <div class="hgt10"></div>
      <div class="hgt10"> </div>
	  <span class=" fr gary">代代传承敬上</span>
      <div class="hgt10"></div>
      <div>
        <span class="f_14 line_25">如果以上按钮无法打开，请把下面的链接复制到浏览器地址栏中打开：</span><a href="'.SNS_DOMAIN.'/login/emailResetPwd/code/'.$code.'" class="f_14 blue">'.SNS_DOMAIN.'/login/emailResetPwd/code/'.$code.'</a>
      </div>
      <div class="hgt10"></div>
      <div>
        <span class="gary">这封信是由我们服务器自动发出的，请不要回复</span>
      </div>
    </div>
 </div>
</body>
</html>';
        $subject = '代代传承重置密码';
        Mail::send($subject,$content,$user['user_name'],$user['nick_name']);
    }

    public function resetEmail($user,$code){
        $content = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>邮件模板--更改邮箱</title>
<style>
body{
	color: #555;
	line-height: 22px;
	background :#fff;
}
.clr {
clear: both;
height: 0;
line-height: 0;
font-size: 1px;
}
.pwd-reg {
	width: 545px;
	margin: 0 auto;
}
.pad_10_20 {
	padding: 10px 15px;
}
.pad_15 {
padding: 5px;
}
.pwd-reg .true-btn {
width: 140px;
margin: 0 auto;
}
.pwd-reg .true-btn a{
text-decoration: none;
}
.fr {
float: right;
}
.fl {
float: left;
}
.hgt10 {
clear: both;
height: 10px;
font-size: 1px;
}
.blue {
color: #389bbd;
}
.f_14 {
font-size: 14px;
}
.bold {
font-weight: bold;
}
.gray {
color: #888888;
}
.line_30 {
line-height: 30px;
}
.mail-log {
background: url('.STATIC_DOMAIN.'/images/base/mail_ico.jpg) no-repeat;
width: 58px;
height: 54px;
}

span {
font-size: 13px;
}
.true-btn a{
	display: inline-block;
	background-color: #37a2c4;
	background: -ms-linear-gradient(top, #41afd3,  #2f94b5);
	background:-moz-linear-gradient(top,#41afd3,  #2f94b5);
	background:-webkit-gradient(linear, 0% 0%, 0% 100%,from(#41afd3), to(#2f94b5));
	background: -webkit-gradient(linear, 0% 0%, 0% 100%, from(#41afd3), to(#2f94b5));
	background: -webkit-linear-gradient(top, #41afd3,  #2f94b5);
	background: -o-linear-gradient(top, #41afd3,  #2f94b5);
	border: 1px solid #2f8cac;
	color: #fff;
	line-height: 30px;
	padding: 0px 26px;
	cursor: pointer;
	-moz-border-radius: 3px;
	-webkit-border-radius: 3px;
	border-radius: 3px;
	font-size: 12px;
}
.true-btn a:hover{
	background-color: #2091b0;
    background: -ms-linear-gradient(top, #3ca7cb,  #1e81a1);
    background:-moz-linear-gradient(top,#3ca7cb,  #1e81a1);
    background:-webkit-gradient(linear, 0% 0%, 0% 100%,from(#3ca7cb), to(#1e81a1));
    background: -webkit-gradient(linear, 0% 0%, 0% 100%, from(#3ca7cb), to(#1e81a1));
    background: -webkit-linear-gradient(top, #3ca7cb,  #1e81a1);
    background: -o-linear-gradient(top, #3ca7cb,  #1e81a1);
}
.pad_10_20 {
padding: 10px 20px;
}
.pwd-reg .head-02 {
	background-color: #3b9cbb;
	background: -ms-linear-gradient(top, #47a4c2,  #2e93b4);
	background:-moz-linear-gradient(top,#47a4c2,  #2e93b4);
	background:-webkit-gradient(linear, 0% 0%, 0% 100%,from(#47a4c2), to(#2e93b4));
	background: -webkit-gradient(linear, 0% 0%, 0% 100%, from(#47a4c2), to(#2e93b4));
	background: -webkit-linear-gradient(top, #47a4c2,  #2e93b4);
	background: -o-linear-gradient(top, #47a4c2,  #2e93b4);

width: 547px;
height: 60px;
}
.pwd-reg .head-02 span{
	display: block;
	font-family: Microsoft YaHei,"微软雅黑体";
	font-size: 26px;
	line-height: 60px;
	color: #fff;
	padding-left: 25px;
}
.blue_round {
border: #9cd4e7 1px solid;
border-radius: 4px;
}
a{
	word-break:break-all;
　　word-wrap:break-word;
}
</style>
</head>
<body>
 <div class="pwd-reg center blue_round">
   <div class="head-02 center">
	<span>代代传承用户中心</span>
   </div>
   <div class="pad_10_20">
      <div class="hgt10"></div>
      <div><span class="blue bold f_14">'.$user['nick_name'].'</span><span class="gray">（代代号：'.$user['did'].'）</span><span class="bold f_14">欢迎您入驻代代传承！</span></div>
      <div class="hgt10"></div><div class="hgt10"></div>
      <div class="resetting pad_15 border_all">
          <div class="resetting-info line_30"><span class="f_14">您在代代传承设置了新的登录邮箱，请在 24 小时内点击下面的按钮进行确认。</span></div>
          <div class="clr"></div>
      </div>
       <div class="hgt10"></div> <div class="hgt10"></div>
      <div class="true-btn center">
         <a href="'.SNS_DOMAIN.'/reg/reset/code/'.$code.'/u/'.$user['uid'].'/n/'.$user['user_name'].'" style="text-decoration: none">确认更改邮箱</a>
      </div>
      <div class="hgt10"></div>
      <div class="hgt10"> </div>
	  <span class=" fr gary">代代传承敬上</span>
      <div class="hgt10"></div>
      <div>
        <span class="f_14 line_25">如果以上按钮无法打开，请把下面的链接复制到浏览器地址栏中打开：</span><a href="'.SNS_DOMAIN.'/reg/reset/code/'.$code.'/u/'.$user['uid'].'/n/'.$user['user_name'].'" class="f_14 blue">'.SNS_DOMAIN.'/reg/reset/code/'.$code.'/u/'.$user['uid'].'/n/'.$user['user_name'].'</a>
      </div>
      <div class="hgt10"></div>
      <div>
        <span class="gary">这封信是由我们服务器自动发出的，请不要回复</span>
      </div>
    </div>
 </div>
</body>
</html>';
        $subject = '代代传承更换注册邮箱';
        Mail::send($subject,$content,$user['user_name'],$user['nick_name']);
    }


    public function bindActiveEmail($user,$email){
        $content = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>邮件模板--绑定邮箱激活</title>
<style>
body{
	color: #555;
	line-height: 22px;
	background :#fff;
}
.clr {
clear: both;
height: 0;
line-height: 0;
font-size: 1px;
}
.pwd-reg {
	width: 545px;
	margin: 0 auto;
}
.pad_10_20 {
	padding: 10px 15px;
}
.pad_15 {
padding: 5px;
}
.pwd-reg .true-btn {
width: 140px;
margin: 0 auto;
}
.pwd-reg .true-btn a{
text-decoration: none;
}
.fr {
float: right;
}
.fl {
float: left;
}
.hgt10 {
clear: both;
height: 10px;
font-size: 1px;
}
.blue {
color: #389bbd;
}
.f_14 {
font-size: 14px;
}
.bold {
font-weight: bold;
}
.gray {
color: #888888;
}
.line_30 {
line-height: 30px;
}
.mail-log {
background: url('.STATIC_DOMAIN.'/images/base/mail_ico.jpg) no-repeat;
width: 58px;
height: 54px;
}

span {
font-size: 13px;
}
.true-btn a{
	display: inline-block;
	background-color: #37a2c4;
	background: -ms-linear-gradient(top, #41afd3,  #2f94b5);
	background:-moz-linear-gradient(top,#41afd3,  #2f94b5);
	background:-webkit-gradient(linear, 0% 0%, 0% 100%,from(#41afd3), to(#2f94b5));
	background: -webkit-gradient(linear, 0% 0%, 0% 100%, from(#41afd3), to(#2f94b5));
	background: -webkit-linear-gradient(top, #41afd3,  #2f94b5);
	background: -o-linear-gradient(top, #41afd3,  #2f94b5);
	border: 1px solid #2f8cac;
	color: #fff;
	line-height: 30px;
	padding: 0px 26px;
	cursor: pointer;
	-moz-border-radius: 3px;
	-webkit-border-radius: 3px;
	border-radius: 3px;
	font-size: 12px;
}
.true-btn a:hover{
	background-color: #2091b0;
    background: -ms-linear-gradient(top, #3ca7cb,  #1e81a1);
    background:-moz-linear-gradient(top,#3ca7cb,  #1e81a1);
    background:-webkit-gradient(linear, 0% 0%, 0% 100%,from(#3ca7cb), to(#1e81a1));
    background: -webkit-gradient(linear, 0% 0%, 0% 100%, from(#3ca7cb), to(#1e81a1));
    background: -webkit-linear-gradient(top, #3ca7cb,  #1e81a1);
    background: -o-linear-gradient(top, #3ca7cb,  #1e81a1);
}
.pad_10_20 {
padding: 10px 20px;
}
.pwd-reg .head-02 {
	background-color: #3b9cbb;
	background: -ms-linear-gradient(top, #47a4c2,  #2e93b4);
	background:-moz-linear-gradient(top,#47a4c2,  #2e93b4);
	background:-webkit-gradient(linear, 0% 0%, 0% 100%,from(#47a4c2), to(#2e93b4));
	background: -webkit-gradient(linear, 0% 0%, 0% 100%, from(#47a4c2), to(#2e93b4));
	background: -webkit-linear-gradient(top, #47a4c2,  #2e93b4);
	background: -o-linear-gradient(top, #47a4c2,  #2e93b4);

width: 547px;
height: 60px;
}
.pwd-reg .head-02 span{
	display: block;
	font-family: Microsoft YaHei,"微软雅黑体";
	font-size: 26px;
	line-height: 60px;
	color: #fff;
	padding-left: 25px;
}
.blue_round {
border: #9cd4e7 1px solid;
border-radius: 4px;
}
a{
	word-break:break-all;
　　word-wrap:break-word;
}
</style>
</head>
<body>
 <div class="pwd-reg center blue_round">
   <div class="head-02 center">
	<span>代代传承用户中心</span>
   </div>
   <div class="pad_10_20">
      <div class="hgt10"></div>
      <div><span class="blue bold f_14">尊敬的'.$user['nick_name'].'</span><span class="gray">（代代号：'.$user['did'].'）</span><span class="bold f_14"></span></div>
      <div class="hgt10"></div><div class="hgt10">您好！</div>
      <div class="resetting pad_15 border_all">
          <div class="resetting-info line_30"><span class="f_14">您正在绑定邮箱，用于找回密码使用。</span></div>
          <div class="resetting-info line_30"><span class="f_14">请点击以下链接进行激活，激活后，则绑定成功。</span></div>
          <div class="clr"></div>
      </div>
       <div class="hgt10"></div> <div class="hgt10"></div>
      <div class="true-btn center">
         <a href="'.SNS_DOMAIN.'/reg/avtiveEmail/e/'.$email.'/u/'.$user['uid'].'" style="text-decoration: none">确认激活邮箱</a>
      </div>
      <div class="hgt10"></div>
      <div class="hgt10"> </div>
	  <span class=" fr gary">代代传承敬上</span>
      <div class="hgt10"></div>
      <div>
        <span class="f_14 line_25">如果以上按钮无法打开，请把下面的链接复制到浏览器地址栏中打开：</span><a href="'.SNS_DOMAIN.'/reg/avtiveEmail/e/'.$email.'/u/'.$user['uid'].'" class="f_14 blue">'.SNS_DOMAIN.'/reg/avtiveEmail/e/'.$email.'/u/'.$user['uid'].'</a>
      </div>
      <div class="hgt10"></div>
      <div>
        <span class="gary">这封信是由我们服务器自动发出的，请不要回复</span>
      </div>
    </div>
 </div>
</body>
</html>';
        $subject = '代代传承邮箱绑定激活';
        Mail::send($subject,$content,$email,$user['nick_name']);
    }



}

