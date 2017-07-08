<?php

/**
 * Class AddressModel
 */
class AddressModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
    }

    public function getListByPid($pid){
        $stmt = $this->db->prepare("select id,name,pid from address where pid = :pid and status = 1");
        $array = array(
            ':pid' => $pid,
        );
        $stmt->execute($array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    //获取地址列表
    public function getAddressList(){
        $stmt  = $this->db->prepare("select id,name,pid from address where status = 1 and pid = 0");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /*
     * @name 取省城市区域名
     */
    public function getNameById($id){
        $stmt = $this->db->prepare("select name from address where id = :id");
        $array = array(
            ':id'=>$id,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        $name = '';
        if($rs){
            $name = $rs['name'];
        }
        return $name;
    }
    /*
     * @name 根据乡镇的id查上级的标签id
     */
    public function cityParent($id){
        $stmt = $this->db->prepare("select * from address where id = :id");
        $array = array(
            ':id' => $id,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt_parent = $this->db->prepare("select * from address where id = :pid");
        $array_parent = array(
            ':pid' => $result['pid'],
        );
        $stmt_parent->execute($array_parent);
        $result_p = $stmt_parent->fetch(PDO::FETCH_ASSOC);
        return $result_p;
    }
    /*
 * @name 根据二级的id查上级的标签id
 */
    public function parent($id){
        $stmt = $this->db->prepare("select * from address where id = :id");
        $array = array(
            ':id' => $id,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    /*
     * @name 增加用户收货地址
     */
    public function addShipping($uid,$consignee_name,$town_id,$detail_address,$phone,$is_default){
        $user = new UserModel();
        $user_info= $user -> getUserByUid($uid);
        if(!$user_info){
            return -1;
        }
        $area_info = $this->cityParent($town_id);
        if(!$area_info){
            return -2;
        }
        $city_id = $area_info['id'];
        $province_id = $area_info['pid'];
        if(!$province_id){
            return -3;
        }
        $date_time =date("Y-m-d H:i:s");
        $count_rs = $this ->countShipping($uid);
        if($count_rs >= 10){
            return -4;
        }
        if($is_default==1){
            $stmt_update = $this->db->prepare("update shipping set is_default = 0 where uid=:uid");
            $array_update = array(
                ':uid'=>$uid,
            );
            $stmt_update -> execute($array_update);
        }
        $stmt = $this->db->prepare("insert into shipping (consignee_name,province_id,city_id,town_id,detail_address,phone,is_default,uid,add_time,update_time) values (:consignee_name,:province_id,:city_id,:town_id,:detail_address,:phone,:is_default,:uid,:add_time,:update_time)");
        $array = array(
            ':consignee_name'=>$consignee_name,
            ':province_id'=>$province_id,
            ':city_id'=>$city_id,
            ':town_id'=>$town_id,
            ':detail_address'=>$detail_address,
            ':phone'=>$phone,
            ':is_default'=>$is_default,
            ':uid'=>$uid,
            ':add_time'=>$date_time,
            ':update_time'=>$date_time,
        );
        $stmt->execute($array);
        $count  =  $stmt->rowCount ();
        if($count<1){
            return 0;
        }
        return $this->db->lastInsertId();
    }
    public function setLastDefault($uid){
        $sql = "select * from shipping where uid=:uid and status<2 order by is_default desc, add_time desc";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result && count($result) == 1){
            $stmt = $this->db->prepare("update shipping set is_default = 1 where uid=:uid and address_id = :address_id");
            $array = array(
                ':uid' => $uid,
                ':address_id' => $result[0]['address_id'],
            );
            $stmt->execute($array);
        }
    }
    /*
     * @name 统计收货地址
     */
    public function countShipping($uid){
        $stmt = $this->db->prepare("select count(address_id) as num from shipping  where uid=:uid and status < 3");
        $array = array(
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    /*
 * @name 删除收货地址
 */
    public function delShipping($uid,$address_id){
        $user = new UserModel();
        $user_info= $user -> getUserByUid($uid);
        if(!$user_info){
            return -1;
        }
        $stmt = $this->db->prepare("update shipping set status = 4 where uid=:uid and address_id=:address_id");
        $array = array(
            ':uid'=>$uid,
            ':address_id'=>$address_id,
        );
        $stmt->execute($array);
        $count = $stmt ->rowCount();
        if($count<1){
            return 0;
        }
        return 1;
    }
    /*
     * @name 设置默认收货地址
     */
    public function setDefaultShipping($uid,$address_id){
        $user = new UserModel();
        $user_info= $user -> getUserByUid($uid);
        if(!$user_info){
            return -1;
        }
        $stmt_update = $this->db->prepare("update shipping set is_default = 0 where uid=:uid and is_default = 1");
        $array_update = array(
            ':uid'=>$uid,
        );
        $stmt_update -> execute($array_update);
        $stmt = $this->db->prepare("update shipping set is_default = 1 where uid=:uid and address_id=:address_id");
        $array = array(
            ':uid'=>$uid,
            ':address_id'=>$address_id,
        );
        $stmt->execute($array);
        $count = $stmt ->rowCount();
        if($count<1){
            return 0;
        }
        return 1;
    }
    public function getShippingById($uid,$address_id){
        $stmt = $this->db->prepare("select address_id,consignee_name,province_id,city_id,town_id,detail_address,phone,is_default from shipping where uid=:uid and address_id = :address_id");
        $array = array(
            ':uid' => $uid,
            ':address_id' => $address_id
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = $this->addressName($result);
        return $result;
    }
    //账号设置里做修改使用
    public function getUserShipping($uid,$address_id){
        $stmt = $this->db->prepare("select address_id,consignee_name,province_id,city_id,town_id,detail_address,phone,is_default from shipping where uid=:uid and address_id = :address_id");
        $array = array(
            ':uid' => $uid,
            ':address_id' => $address_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    /*
     * @name 根据省城市区域id在区域表中取用户的区域名称
     * @$arr 传二维数组
     */
    public function addressName($arr){
        if(!$arr){
            return array();
        }
        foreach($arr as $k=>$v){
            $stmt_p = $this->db->prepare("select name from address where id = :p_id");
            $stmt_p->bindParam(":p_id", $v['province_id'], PDO::PARAM_INT);
            $stmt_p->execute();
            $rs_p = $stmt_p->fetch(PDO::FETCH_ASSOC);
            $stmt_c = $this->db->prepare("select name from address where id = :c_id");
            $stmt_c->bindParam(":c_id", $v['city_id'], PDO::PARAM_INT);
            $stmt_c->execute();
            $rs_c = $stmt_c->fetch(PDO::FETCH_ASSOC);
            $stmt_t = $this->db->prepare("select name from address where id = :t_id");
            $stmt_t->bindParam(":t_id", $v['town_id'], PDO::PARAM_INT);
            $stmt_t->execute();
            $rs_t = $stmt_t->fetch(PDO::FETCH_ASSOC);
            $arr[$k]['province_name'] = $rs_p['name'];
            $arr[$k]['city_name'] = $rs_c['name'];
            $arr[$k]['town_name'] = $rs_t['name'];
            if(isset($v['phone_number'])){
                $arr[$k]['phone_number'] = $v['phone_number'] ? explode('-',$v['phone_number']) : '';
            }
            if(isset($v['work_time_start']) && strstr($v['work_time_start'], '-') ){
                $arr[$k]['work_time_start'] = $v['work_time_start'] ? explode('-',$v['work_time_start']) : '';
            }
            if(isset($v['work_time_end']) && strstr($v['work_time_end'], '-')){
                $arr[$k]['work_time_end'] = $v['work_time_end'] ? explode('-',$v['work_time_end']) : '';
            }
        }
        return $arr;
    }
    /*
     * @name 列表显示收货地址
     */
    public function listShipping($uid){
        $stmt = $this->db->prepare("select address_id,consignee_name,province_id,city_id,town_id,detail_address,phone,is_default from shipping where uid=:uid and status<2 order by is_default desc, add_time desc");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = $this->addressName($result);
        return $result;
    }
    /*
     * @name 修改用户收货地址
     */
    public function modifyShipping($uid,$address_id,$consignee_name,$town_id,$detail_address,$phone,$is_default){
        $user = new UserModel();
        $user_info= $user -> getUserByUid($uid);
        if(!$user_info){
            return -1;
        }
        $area_info = $this->cityParent($town_id);
        if(!$area_info){
            return -2;
        }
        $city_id = $area_info['id'];
        $province_id = $area_info['pid'];
        if(!$province_id){
            return -3;
        }
        $date_time =date("Y-m-d H:i:s");
        if($is_default==1){
            $stmt_update = $this->db->prepare("update shipping set is_default = 0 where uid=:uid");
            $array_update = array(
                ':uid'=>$uid,
            );
            $stmt_update -> execute($array_update);
        }
        $stmt = $this->db->prepare("update shipping set consignee_name =:consignee_name,province_id=:province_id,city_id=:city_id,town_id=:town_id,detail_address=:detail_address,phone=:phone,is_default=:is_default,update_time=:update_time where uid=:uid and address_id=:address_id");
        $array = array(
            ':consignee_name'=>$consignee_name,
            ':province_id'=>$province_id,
            ':city_id'=>$city_id,
            ':town_id'=>$town_id,
            ':detail_address'=>$detail_address,
            ':phone'=>$phone,
            ':is_default'=>$is_default,
            ':update_time'=>$date_time,
            ':uid'=>$uid,
            ':address_id'=>$address_id,
        );
        $stmt->execute($array);
        $count  =  $stmt->rowCount();
        if($count<1){
            return 0;
        }
        return 1;
    }
    public function getDefaultShipping($uid){
        $stmt = $this->db->prepare("select address_id,consignee_name,province_id,city_id,town_id,detail_address,phone,is_default from shipping where uid=:uid and is_default = 1 and status < 2");
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = $this->addressName($result);
        return $result;
    }
}

