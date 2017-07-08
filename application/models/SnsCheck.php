<?php
/**
 * @name CheckinModel
 * @desc Checkin数据获取类, 可以访问数据库，文件，其它系统等
 * @author {&$AUTHOR&}
 */
class SnsCheckModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
    }

    /*
     * 驿站帖子审核插入数据
     */
    public function insert($uid, $topicId, $status,  $remark=''){
        $rst = 0;
        if($uid && $topicId && $status){
            $sql = 'INSERT INTO sns_check (uid, obj_id, operation_type, type, source, remark, add_time) VALUE (:uid, :obj_id, :operation_type, 1, 1, :remark, :add_time)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(
                array(
                    ':uid' => $uid,
                    ':obj_id' => $topicId,
                    ':operation_type' => $status,
                    ':remark' => $remark,
                    ':add_time' => date("Y-m-d H:i:s"),
                )
            );
            $rst = $stmt->rowCount();
        }
        return $rst;
    }
    //获取审核数据
    public function get($obj_id,$operation_type,$type){
        $sql = "SELECT id,remark FROM sns_check WHERE obj_id =:obj_id and operation_type=:operation_type and type=:type order by id desc limit 1";
        $stmt = $this->db->prepare($sql);
        $stmt = $this->db->prepare($sql);
        $stmt->execute(
            array(
                ':obj_id' => $obj_id,
                ':operation_type' => $operation_type,
                ':type' => $type
            )
        );
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? '您的驿站审核不通过，原因是：'.Common::deleteHtml($result['remark']) : '';

    }
}