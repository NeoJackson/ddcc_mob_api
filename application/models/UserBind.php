<?php
class UserBindModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
    }

    public function getList($uid){
        $stmt = $this->db->prepare("select id,type,flag from user_bind where uid = :uid");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function del($id,$uid){
        $stmt = $this->db->prepare("update user_bind set uid = 0 , flag = 0 where id = :id and uid = :uid");
        $array = array(
            ':id' => $id,
            ':uid' => $uid,
        );
        $stmt->execute($array);
        return $stmt->rowCount();
    }

    //判断有没有被绑定过（登陆后-账号设置-绑定第三方）
    public function isUsedBind($type,$openid,$access_token){
        $sql = "select id from user_bind
                where type = :type
                and access_token = :access_token
                and openid = :openid
                and uid > 0";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':type'=> $type,
            ':openid' => $openid,
            ':access_token' => $access_token
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    //微信-判断有没有被绑定过（登陆后-账号设置-绑定第三方）
    public function isUsedBindweixin($type,$openid){
        $sql = "select id from user_bind
                where type = :type
               and openid = :openid
                and uid > 0";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':type'=> $type,
            ':openid' => $openid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function getFlag($type,$openid){
        $sql = "select flag from user_bind where openid = :openid and type = :type";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':type'=> $type,
            ':openid' => $openid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['flag'] : 0;
    }

    //判断是否用来注册过
    public function isUsedReg($type,$openid){
        $sql = "SELECT a.reg_type,b.flag,b.access_token FROM user a, user_bind b
                WHERE a.uid = b.uid
                AND b.type = :type
                AND b.openid = :openid";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':type'=> $type,
            ':openid' => $openid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //获取用户绑定手机or邮箱（账号安全）
    public function getUserBindName($uid){
        $sql = "SELECT bind_name,bind_status FROM user_info WHERE uid = :uid";
        $stmt = $this->db->prepare($sql);
        $array = array(':uid' => $uid);
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result : false;
    }

    public function isBindNameUsed($bind_name,$country_code=''){
        $fields = $country_code ? 'and bind_country_code='.$country_code.'' :'';
        $sql = "SELECT uid FROM user_info WHERE bind_name = :bind_name and bind_status = 1 $fields";
        $stmt = $this->db->prepare($sql);
        $array = array(':bind_name' => $bind_name);
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['uid'] : false;
    }
    //绑定手机号or邮箱
    public function addBind($uid,$bind_name,$flag='email'){
        $bind_status = ($flag == 'email') ? 0 : 1;
        $sql = "UPDATE user_info
                SET bind_name = :bind_name,
                    bind_status = $bind_status,
                    update_time = :update_time
                WHERE uid = :uid";
        $stmt = $this->db->prepare($sql);
        $array = array(':uid'=>$uid, ':bind_name' => $bind_name, ':update_time'=>date('Y-m-d H:i:s'));
        $stmt->execute($array);
        return $stmt->rowCount();
    }

    //更换绑定email或者mobile(控制层bindEmailAction使用)
    public function changeBind($uid,$bind_name,$flag = 'email'){
        $bind_status = ($flag == 'email') ? 0 : 1;
        $sql = "UPDATE user_info
                SET bind_name = :bind_name, bind_status = $bind_status, update_time = :update_time
                WHERE uid = :uid";
        $stmt = $this->db->prepare($sql);
        $array = array(':uid'=>$uid, ':bind_name' => $bind_name, ':update_time'=>date('Y-m-d H:i:s'));
        $stmt->execute($array);
        return $stmt->rowCount();
    }

    //取消绑定（账号安全）
    public function unBind($uid){
        $sql = "UPDATE user_info SET bind_name = '', bind_status = 0, update_time = :update_time
                WHERE uid = :uid";
        $stmt = $this->db->prepare($sql);
        $array = array(':uid'=>$uid, ':update_time'=>date('Y-m-d H:i:s'));
        $stmt->execute($array);
        return $stmt->rowCount();
    }

    //激活绑定内容
    public function activeBindName($uid,$bind_name){
        $sql = "UPDATE user_info SET bind_status = 1,update_time = :update_time
                WHERE uid = :uid AND bind_name = :bind_name";
        $stmt = $this->db->prepare($sql);
        $array = array(':uid'=>$uid, ':bind_name'=>$bind_name, ':update_time'=>date('Y-m-d H:i:s'));
        $stmt->execute($array);
        return $stmt->rowCount();
    }

}