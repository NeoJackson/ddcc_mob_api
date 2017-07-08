<?php
/**
 * @name CheckinModel
 * @desc Checkin数据获取类, 可以访问数据库，文件，其它系统等
 * @author {&$AUTHOR&}
 */
class CheckinModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
    }
    /*
     * @name 签到表中用户指定时间签到记录是否存在
     * @return bool
     */
    public function record($uid,$date){
        $stmt = $this->db->prepare("select count(id) as num from checkin where uid=:uid and add_time=:date ");
        $array = array(
            ':uid'=>$uid,
            ':date'=>$date,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }

    public function add($uid,$date){
        $isRecord = $this->record($uid,$date);
        if($isRecord>0){
            return -1;
        }
        $series = $this->series($uid);
        $stmt = $this->db->prepare("insert into checkin (uid,series,add_time) values (:uid,:series,:add_time)");
        $array = array(
            ':uid'=>$uid,
            ':series'=>$series[0]+1,
            ':add_time'=>$date,
        );
        $stmt->execute($array);
        $obj_id = $this->db->lastInsertId();
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        //发放福报值
        $scoreModel = new ScoreModel();
        $value = $scoreModel->add($uid,0,'checkin',$obj_id);
        return $value;
    }

    public function series($uid){
        $time = date("Y-m-d",strtotime("-1 day"));
        $stmt = $this->db->prepare("select * from checkin where add_time = :add_time and uid=:uid");
        $array = array(
            ':add_time'=>date("Y-m-d"),
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        if($rs){
            return array($rs['series'],'1');
        }
        $stmt = $this->db->prepare("select * from checkin where add_time = :add_time and uid=:uid");
        $array = array(
            ':add_time'=>$time,
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        if($rs){
            return array($rs['series'],'0');
        }else{
            return array(0,0);
        }
    }
    //用户累计签到
    public function getUserCheckIn($uid){
        $stmt = $this->db->prepare('SELECT COUNT(1) AS num FROM checkin WHERE uid = :uid');
        $array = array(
            ':uid'=>$uid
        );
        $stmt->execute($array);
        $rsNum = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $this->db->prepare("select id from checkin where add_time = :add_time and uid=:uid");
        $array = array(
            ':add_time'=>date("Y-m-d"),
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        if($rs){
            return array($rsNum['num'],'1');
        }else{
            return array($rsNum['num'],'0');
        }
    }
}