<?php
/**
 * @name ReportModel
 * @desc Report数据获取类, 可以访问数据库，文件，其它系统等
 * @author {&$AUTHOR&}
 */
class ReportModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
    }
    /*
     *  查询举报分类
     * */
    public  function seReportReason(){
        $stmt = $this->db->prepare("select id,name,add_time  from report_reason where status=1 order by sort asc, add_time desc ");
        $stmt->execute();
        $result['list'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if(!$result){
            return false;
        }
        return $result;
    }
    /*
     * 查询该用户是否已经举报
     * */
    public function selectReport($uid,$reType,$reObjId){
        $stmt = $this->db->prepare("select id from report where uid= :uid and type = :reType and obj_id = :reObjId and status < 2 ");
        $array = array(
            ':reType' =>$reType,
            ':reObjId' => $reObjId,
            ':uid' =>$uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$result){
            return 1;
        }
        return 0;
    }
    /*
     * 添加举报信息
    * */
    public function addReport($uid,$reType,$reObjId,$reRid,$reDetails){
        $reDetails=$reDetails?$reDetails:'';
        $time = date("Y-m-d H:i:s");
        $stmt = $this->db->prepare("insert into report(type,obj_id,rid,uid,details,add_time,update_time) values
         (:reType,:reObjId,:reRid,:uid,:reDetails,:add_time,:update_time) ");
        $array = array(
            ':reType' =>$reType,
            ':reObjId' => $reObjId,
            ':reRid' => $reRid,
            ':uid' =>$uid,
            ':reDetails' => $reDetails,
            ':add_time' => $time,
            ':update_time' => $time
        );
        $stmt->execute($array);
        $aid = $this->db->lastInsertId();
        if(!$aid){
            return 0;
        }
        return 1;

    }


}