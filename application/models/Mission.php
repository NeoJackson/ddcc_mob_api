<?php
/**
 * @name MissionModel
 * @desc mission（才府使命.任务）数据获取类
 * @author wuzb
 */
class MissionModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }
    /**
     * @日常使命、每日使命 -- 添加粉丝数
     * @param $uid
     * @return mixed
     */
    public function myTodayFansNum($uid){
        $start_time = date('Y-m-d 00:00:00');
        $end_time = date('Y-m-d 23:59:59');
        $and  = " and add_time between '$start_time' and '$end_time'";
        $stmt = $this->db->prepare("select count(id) as num from follow where f_uid=:uid and status = 1".$and);
        $array = array( ':uid' => $uid);
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    /**
     * @日常使命、每日使命 -- 在他人内容中点喜欢
     * @param $uid
     * @return mixed
     */
    public function myTodayLikeNum($uid){
        $start_time = date('Y-m-d 00:00:00');
        $end_time = date('Y-m-d 23:59:59');
        $and  = " and add_time between '$start_time'  and '$end_time' ";
        $stmt = $this->db->prepare("select count(id) as num from `like` where uid=:uid and obj_uid <> :uid and status = 1 ".$and);
        $array = array( ':uid' => $uid);
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    /**
     * @我的评论数
     * @param $uid
     * @return mixed
     */
    public function commentNum($uid){
        $stmt = $this->db->prepare('select count(id) as num from comment where uid = :uid and status < 2');
        $stmt->execute(array(':uid' => $uid));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    public function activeDays($uid){
        $stmt = $this->db->prepare('select active_days as num from mission_online where uid = :uid');
        $stmt->execute(array('uid'=>$uid));
        $ret = $stmt->fetch(PDO::FETCH_ASSOC);
        return $ret['num'];
    }

    //今日任务里统计对应的任务条数
    public function getMissionNum($uid,$table,$date=''){
        $and = $date ? " and DATE_FORMAT(add_time,'%Y-%m-%d') = :add_time " : '';
        $sql = "select count(1) as num from mood where uid = :uid and status<2 ".$and;
        if($table == 'topic'){
            $sql = "select count(1) as num from $table where uid = :uid and status<2 ".$and;
        }elseif($table == 'comment'){
            $sql = "select count(1) as num from comment where uid = :uid and status<2 ".$and;
        }
        $stmt = $this->db->prepare($sql);
        $array = $date ? array(':uid' => $uid, ':add_time'=>date('Y-m-d')) : array(':uid' => $uid);
        $stmt->execute($array);
        if($table == 'photo'){
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return count($result);
        }else{
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['num'];
        }
    }


}