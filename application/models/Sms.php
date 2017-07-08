<?php
/**
 * @name SmsModel
 * @desc 短信类
 * @author {&$AUTHOR&}
 */
class SmsModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
    }


    /**
     * 用户中心验证手机发送短信验证码
     * @param $uid
     * @param $mobile
     * @param $type 1注册验证 2找回密码 3更换手机号 4个人中心手机验证 5文化天使 6账号中心手机绑定 7.短信验证码登录 8.报名注册 9服务信息下单验证 11取消绑定手机 12提现申请
     * @return int
     */
    public function addSmsCode($uid,$mobile,$type,$sms_type=1,$reg_country_code='+86',$num=30){
        if(!in_array($type,array(1,2,3,4,5,6,7,8,9,11,12))){
            return -1;
        }
        //判断是否已经发送验证码超过5次
        if($this->getSmsNumByTime($mobile,$type,86400,$uid) > $num){
            return -2;
        }
        //判断距离最新的一条短信是否超过60秒
        if($this->getSmsNumByTime($mobile,$type,60,$uid) >0){
            return -3;
        }
        $code = mt_rand(100000,999999);
        switch($type){
            case 1:
                $msg = '尊敬的用户，您的验证码为：'.$code.'，您正在通过手机进行注册，验证码24小时内有效，如非本人操作请勿理会。';
                break;
            case 2:
                $msg = '尊敬的用户，您的验证码为：'.$code.'，您正在通过手机找回密码，验证码24小时内有效，如非本人操作请勿理会。';
                break;
            case 3:
                $msg = '尊敬的用户，您的验证码为：'.$code.'，您正在更换手机号，验证码24小时内有效，如非本人操作请勿理会。';
                break;
            case 4:
                $msg = '尊敬的用户，您的验证码为：'.$code.'，您正在绑定手机号，验证码24小时内有效，如非本人操作请勿理会。';
                break;
            case 5:
                $msg = '尊敬的用户，您的验证码为：'.$code.'，您正在进行文化天使手机验证，验证码24小时内有效，如非本人操作请勿理会。';
                break;
            case 6:
                $msg = '尊敬的用户，您的验证码为：'.$code.'，您正在绑定手机号，验证码24小时内有效，如非本人操作请勿理会。';
                break;
            case 7:
                $msg = '尊敬的用户，您的验证码为：'.$code.'，您正在通过手机进行登陆，验证码24小时内有效，如非本人操作请勿理会。';
                break;
            case 8:
                $msg = '尊敬的用户，您的验证码为：'.$code.'，您正在通过手机进行注册报名，验证码24小时内有效，如非本人操作请勿理会。报名成功后，您将自动成为才府用户 。初始密码是：手机号后六位('.substr($mobile,5).')，为保证您的账户安全请尽快登录：http://sns.91ddcc.com（或下载才府APP）修改密码。';
                break;
            case 9:
                $msg = '尊敬的用户，您的验证码为：'.$code.'，您正在通过手机进行下单，验证码24小时内有效，如非本人操作请勿理会。';
                break;
            case 11:
                $msg = '尊敬的用户，您的验证码为：'.$code.'，您正在更换绑定手机号，验证码24小时内有效，如非本人操作请勿理会。';
                break;
            case 12:
                $msg = '尊敬的用户，您的验证码为：'.$code.'，您正在进行提现验证，验证码24小时内有效，请勿把验证码透露给他人';
                break;
        }
        $reg_country_code = str_replace("+","",$reg_country_code);
        if($sms_type==1){
            $smsResult = Sms::send($reg_country_code.$mobile, $msg); //调用手机api发送短信，调用api后判断状态 0,密码错误
            $smsResultArr = explode(',',$smsResult);
            if(!isset($smsResultArr[0]) || (!isset($smsResultArr[1]) && $smsResultArr[1] != 0)){
                return -4;
            }
        }else{
            $smsResult = Sms::sendToOverseas($reg_country_code.$mobile, $msg);
            if(!$smsResult){
                return -4;
            }

        }
        $stmt = $this->db->prepare("insert into sms (uid,mobile,type,code,expire_time) select :uid,:mobile,:type,:code,:expire_time
        from dual where not exists (select uid,mobile,type,code,expire_time from sms where mobile = :mobile and UNIX_TIMESTAMP() - 60 < UNIX_TIMESTAMP(add_time))");
        $array = array(
            ':uid' => $uid,
            ':mobile' => $reg_country_code.$mobile,
            ':type' => $type,
            ':code' => $code,
            ':expire_time' => date("Y-m-d H:i:s",time()+86400)
        );
        $stmt->execute($array);
        $count = $this->db->lastInsertId();
        if(!$count){
            return -3;
        }
        return 1;
    }

    public function getSmsNumByTime($mobile,$type,$time,$uid=''){
        $add_time = date("Y-m-d H:i:s",time() - $time);
        if($uid!=''){
            $stmt = $this->db->prepare("select count(*) as num from sms where uid = :uid and type =:type and add_time > :add_time");
            $array = array(
                ':uid' => $uid,
                ':type' => $type,
                ':add_time' => $add_time
            );
        }else{
            $stmt = $this->db->prepare("select count(*) as num from sms where mobile = :mobile and type =:type and add_time > :add_time");
            $array = array(
                ':mobile' => $mobile,
                ':type' => $type,
                ':add_time' => $add_time
            );
        }
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    public function getSmsCode($mobile,$type){
        $mobile = str_replace("+","",$mobile);
        $stmt = $this->db->prepare("select id,code from sms where mobile = :mobile and type =:type and expire_time > :expire_time order by add_time desc limit 1");
        $array = array(
            ':mobile' => $mobile,
            ':type' => $type,
            ':expire_time' => date("Y-m-d H:i:s",time())
        );
        $stmt->execute($array);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateSmsCodeExpireTime($id){
        $stmt = $this->db->prepare("update sms set expire_time = :expire_time where id = :id");
        $array = array(
            ':expire_time' => date("Y-m-d H:i:s"),
            ':id' => $id
        );
        $stmt->execute($array);
        return $stmt->rowCount();
    }

    public function updateUserInfoMobile($uid,$mobile){
        $stmt = $this->db->prepare("update user_info set mobile = :mobile where uid = :uid");
        $array = array(
            ':mobile' => $mobile,
            ':uid' => $uid
        );
        $stmt->execute($array);
        return $stmt->rowCount();
    }

    /**
     * 用户中心验证手机发送短信验证码
     * @param $uid
     * @param $mobile
     * @param $type 1注册验证 2找回密码 3更换手机号 4个人中心手机验证 5文化天使
     * @return int
     */
    public function addVerifySmsCode($uid,$mobile,$type){
        if(!in_array($type,array(1,2,3,4,5,6))){
            return -1;
        }
        //判断是否已经发送验证码超过3次
        if($this->getSmsNumByTime($mobile,$type,86400,$uid) > 3){
            return -2;
        }
        //判断距离最新的一条短信是否超过90秒
        if($this->getSmsNumByTime($mobile,$type,90,$uid) >0){
            return -3;
        }
        $code = mt_rand(100000,999999);
        $msg = '尊敬的用户，您正在进行手机验证，验证码24小时内有效，验证码：'.$code.'，如非本人操作请勿理会。【代代传承】';
        $smsResult = Sms::send($mobile, $msg); //调用手机api发送短信，调用api后判断状态 0,密码错误
        $smsResultArr = explode(',',$smsResult);
        if(!isset($smsResultArr[0]) || $smsResultArr[0] != 1){
            return -4;
        }
        $stmt = $this->db->prepare("insert into sms (uid,mobile,type,code,expire_time) values (:uid,:mobile,:type,:code,:expire_time)");
        $array = array(
            ':uid' => $uid,
            ':mobile' => $mobile,
            ':type' => $type,
            ':code' => $code,
            ':expire_time' => date("Y-m-d H:i:s",time()+86400)
        );
        $stmt->execute($array);
        return $this->db->lastInsertId();
    }
}

