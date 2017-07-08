<?php
class TokenModel {
    private $db;
    private $expireTime = 25920000;//30*24*3600  30天
    public function __construct() {
        $this->db = DB::getInstance();
    }

    public function hasToken($uid) {
        $sql = 'select * from user_token where uid = :uid and status = 0';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':uid'=>$uid));
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$rs){
            $sql = 'insert into user_token(uid,token,expire_time) values(:uid,:token,:expire_time)';
            $stmt = $this->db->prepare($sql);
            $expire_time = date('Y-m-d H:i:s',time()+$this->expireTime);
            $token = md5(md5($uid.$expire_time));
            $stmt->execute(array(':uid'=>$uid,':token'=>$token,':expire_time'=>$expire_time));
            return $token;
        }
        if(date('Y-m-d H:i:s',time()) < $rs['expire_time']){
            return $rs['token'];
        }
        return $this->modifyToken($rs['uid']);
    }

    public function verifyToken($token) {
        $sql = 'SELECT a.id,a.uid,a.token,a.expire_time,a.status,a.add_time,a.update_time FROM user_token AS a LEFT JOIN user AS u
                ON a.uid = u.uid
                WHERE a.token = :token AND u.status < 2';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':token'=>$token));
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$rs){
            return false;
        }
        if(date('Y-m-d H:i:s',time()) < $rs['expire_time']){
            return array('uid'=>$rs['uid'],'token'=>$token);
        }
        $token = $this->modifyToken($rs['uid']);
        return array('uid'=>$rs['uid'],'token'=>$token);
    }

    public function modifyToken($uid) {
        $sql = 'update user_token set token = :token,expire_time = :expire_time where uid = :uid';
        $stmt = $this->db->prepare($sql);
        $expire_time = date('Y-m-d H:i:s',time()+$this->expireTime);
        $token = md5(md5($uid.$expire_time));
        $stmt->execute(array(':token'=>$token,':expire_time'=>$expire_time,':uid'=>$uid));
        return $token;
    }
    public function delToken($token){
        $sql = 'update user_token set status=1 where token=:token';
        $stmt = $this->db->prepare($sql);
        $array=array(
            ':token'=>$token
        );
        $stmt->execute($array);
        $count  =  $stmt->rowCount();
        if($count<1){
            return 0;
        }
        return $count;
    }
    //添加用户手机识别号
    public function addDeviceTokens($uid,$device_tokens,$origin){
        $sql = 'insert into app_device_tokens(uid,device_tokens,origin) values(:uid,:device_tokens,:origin)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':uid'=>$uid,':device_tokens'=>$device_tokens,':origin'=>$origin));
        $count = $stmt->rowCount();
        if($count<1){
            return -1;
        }
        return 1;
    }
    //验证用户手机识别号是否存在
    public function modifyDeviceTokens($uid,$device_tokens,$origin){
        $sql = 'select * from app_device_tokens where device_tokens =:device_tokens and status = 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':device_tokens'=>$device_tokens));
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$rs){
            $this->addDeviceTokens($uid,$device_tokens,$origin);
        }
        if($rs['uid']!=$uid){
            $this->updateDeviceTokens($uid,$device_tokens);
        }
        return 1;
    }
    //修改手机识别信息
    public function updateDeviceTokens($uid,$device_tokens){
        $sql = 'update app_device_tokens set uid=:uid ,update_time=:update_time where device_tokens=:device_tokens and status = 1';
        $stmt = $this->db->prepare($sql);
        $array=array(
            ':uid'=>$uid,
            ':device_tokens'=>$device_tokens,
            ':update_time'=>date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
    }
    //根据用户uid查询该用户的手机识别号
    public function getDeviceTokensByUid($uid){
        $sql = 'select * from app_device_tokens where uid = :uid and status = 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':uid'=>$uid));
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rs;
    }
    //验证用户手机识别号是否存在
    public function modifyRegistrationId($uid,$registrationId,$origin){
        $sql = 'select * from app_registrationid where registrationid =:registrationid and status = 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':registrationid'=>$registrationId));
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$rs){
            $this->addRegistrationId($uid,$registrationId,$origin);
        }
        if($rs['uid']!=$uid){
            $this->updateRegistrationId($uid,$registrationId);
        }
        return 1;
    }
    //添加用户手机识别号
    public function addRegistrationId($uid,$registrationId,$origin){
        $sql = 'insert into app_registrationid(uid,registrationid,origin) values(:uid,:registrationid,:origin)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':uid'=>$uid,':registrationid'=>$registrationId,':origin'=>$origin));
        $count = $stmt->rowCount();
        if($count<1){
            return -1;
        }
        return 1;
    }
    //修改手机识别信息
    public function updateRegistrationId($uid,$registrationId){
        $sql = 'update app_registrationid set uid=:uid ,update_time=:update_time where registrationid=:registrationid and status = 1';
        $stmt = $this->db->prepare($sql);
        $array=array(
            ':uid'=>$uid,
            ':registrationid'=>$registrationId,
            ':update_time'=>date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
    }
}