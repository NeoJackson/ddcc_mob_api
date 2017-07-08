<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 15-5-18
 * Time: 下午2:33
 */
class CommonModel{
    private $db;
    private $redis;
    public $type_code_arr = array(
        '1'=>'mood','2'=>'album_photo','3'=>'blog','4'=>'topic','9'=>'share','10'=>'event','11'=>'stage_message'
    );
    private $type_arr = array(1,2,3,4,9,10,11);
    private $status_arr = array(0,1,2,3,4);
    private $like_arr = array(1,2,3,4,9,10);
    private $reward_arr = array(1,3,4,9);
    private $no_status_arr = array(2,4);
    //提到我的类型转换
    public $mention_arr = array(
        '1'=>'1','9'=>'3','11'=>'2'
    );
    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }

    /**
     * 更新1:心境 2.相册照片 3:日志 4:普通帖子 9:分享 10:商家活动 11 驿站留言等 对应评论、喜欢、打赏等
     * $status 0 未审核  1审核通过  2审核不通过  3后台删除  4前台删除
     */
    public function updateRelationByObjId($type,$obj_id,$status=2){
        if(!$obj_id || !$type || !in_array($type,$this->type_arr) || !in_array($status,$this->status_arr)){
            return false;
        }
        $messageModel = new MessageModel();
        $messageModel->updateComment($type,$obj_id,$status);//评论
        $new_status = in_array($status,$this->no_status_arr)?0:1;
        if(in_array($type,$this->like_arr)){//喜欢
            $likeModel = new LikeModel();
            $likeModel->updateLike($type,$obj_id,$new_status);
        }
        if(in_array($type,$this->reward_arr)){//打赏
            $messageModel->updateReward($type,$obj_id,$new_status);
        }
        //更新数量
        $field = $this->type_code_arr[$type];
        $stmt_num = $this->db->prepare("select count(1) as num from comment where obj_id = :obj_id and type = :type and status<2
        union all select count(1) as num from `like` where obj_id = :obj_id and type = :type and status=1
        union all select count(1) as num from bounty_push where obj_id = :obj_id and type = :type and status=1");
        $array = array(
            ':obj_id' => $obj_id,
            ':type' => $type,
        );
        $stmt_num->execute($array);
        $data_num = $stmt_num->fetchAll(PDO::FETCH_ASSOC);
        $comment_num = $data_num[0]['num'];
        $like_num = $data_num[1]['num'];
        $reward_num = $data_num[2]['num'];
        $condition_like = in_array($type,$this->like_arr) ? ' ,like_num = :like_num' : '';
        $condition = in_array($type,$this->reward_arr) ? ' ,reward_num = :reward_num' : '';
        if($field){
            $stmt = $this->db->prepare("update $field set comment_num =:comment_num , update_time=:update_time $condition_like $condition where id =:id");
            $stmt->bindValue(':id', $obj_id, PDO::PARAM_INT);
            $stmt->bindValue(':comment_num', $comment_num, PDO::PARAM_INT);
            $stmt->bindValue(':update_time', date('Y-m-d H:i:s'), PDO::PARAM_STR);
            if(in_array($type,$this->like_arr)){
                $stmt->bindValue(':like_num', $like_num, PDO::PARAM_INT);
            }
            if(in_array($type,$this->reward_arr)){
                $stmt->bindValue(':reward_num', $reward_num, PDO::PARAM_INT);
            }
            $stmt->execute();
        }
        //心境、驿站留言、分享删除时删除提到我的数据
        if(in_array($type,array(1,9,11))){
            $messageModel = new MessageModel();
            $type = $this->mention_arr[$type];
            $messageModel->updateMention($type,$obj_id,$new_status);
        }
        return 1;
    }
    public function updateIsRead($type,$uid,$to_uid){
        switch($type){
            case 1://粉丝
                $followModel = new FollowModel();
                $followModel->updateIsRead($uid);
                break;
            case 2://一对一聊天
                $chatModel = new ChatModel();
                $chatModel->clearUserUnRead($uid,$to_uid);
                break;
            case 3:
                break;
            case 4://评论
                $stmt = $this->db->prepare("update comment_push set is_read = 1 where uid =:uid and is_read = 0");
                $array = array(
                    ':uid'=>$uid
                );
                $stmt->execute($array);
                break;
            case 5://喜欢
                $stmt = $this->db->prepare("update `like` set is_read = 1 where obj_uid =:uid and is_read = 0");
                $array = array(
                    ':uid'=>$uid
                );
                $stmt->execute($array);
                break;
            case 6://打赏
                $stmt = $this->db->prepare("update bounty_push set is_read = 1 where obj_uid =:uid and is_read = 0");
                $array = array(
                    ':uid'=>$uid
                );
                $stmt->execute($array);
                break;
            case 7://@我
                $stmt = $this->db->prepare("update mention set is_read = 1 where m_uid =:uid and is_read = 0");
                $array = array(
                    ':uid'=>$uid
                );
                $stmt->execute($array);
                break;
        }
    }

    public function modifyAddress($province,$city,$town){
        $province_num = $this->getAddress($province,1);
        $city_num = $this->getAddress($city,2);
        $town_num = $this->getAddress($town,3);
        if($province_num==0){
            $province_id = $this->insertCity($province,0,1);
            $city_id = $this->insertCity($city,$province_id,2);
            $this->insertCity($town,$city_id,3);
        }
        if($province_num>0&&$city_num==0){
            $province_id = $this->getCity($province,1);
            $city_id = $this->insertCity($city,$province_id,2);
            $this->insertCity($town,$city_id,3);
        }
        if($province_num>0&&$city_num>0&&$town_num==0){
            $city_id = $this->getCity($city,2);
            $this->insertCity($town,$city_id,3);
        }
    }
    //根据某一个地址查询id
    public function getCity($name){
        $sql = "select id from address where name = :name ";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':name' => $name
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['id'];
    }
    //添加预览数据
    public function addPreview($type,$jsonStr){
        $stmt = $this->db->prepare("insert into preview (type,content) values (:type,:content)");
        $array = array(
            ':type' => $type,
            ':content' => $jsonStr
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        if(!$id){
           return 0;
        }
        return $id;
    }
    //获取预览数据
    public function getPreview($id){
        $sql = "select * from preview where id=:id ";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':id' => $id,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //友盟推送错误信息入库
    public function addUmengError($message,$device_token,$origin){
        $stmt = $this->db->prepare("insert into umeng_error (message,device_token,origin) values (:message,:device_token,:origin)");
        $array = array(
            ':message' => $message,
            ':device_token' => $device_token,
            ':origin' => $origin
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        return $id;
    }
    public function addJpushError($message,$registration_id,$origin){
        $stmt = $this->db->prepare("insert into jpush_error (message,registrationid,origin) values (:message,:registrationid,:origin)");
        $array = array(
            ':message' => $message,
            ':registrationid' => $registration_id,
            ':origin' => $origin
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        return $id;
    }
    public function getCountry(){
        $sql = "select id,name,code from sms_country where status =1 ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    //用户设置佣金记录
    public function userSetCommissionRate($type,$obj_id,$commission_rate,$uid){
        $stmt = $this->db->prepare("insert into user_set_commission_rate (type,obj_id,commission_rate,uid) values (:type,:obj_id,:commission_rate,:uid)");
        $array = array(
            ':type' => $type,
            ':obj_id' => $obj_id,
            ':commission_rate' => $commission_rate,
            ':uid' => $uid
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        return $id;
    }
    //根据id,type,obj_id查询上一条的设置佣金记录
    public function getUserSetLastId($id,$type,$obj_id){
        if($id){
            $fields = ' and id<'.$id.'';
        }else{
            $fields = '';
        }
        $stmt = $this->db->prepare("select id from user_set_commission_rate where obj_id=:obj_id and type=:type and time is null $fields order by id desc limit 1 ");
        $array = array(
            ':type' => $type,
            ':obj_id' => $obj_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['id'];
    }
    //修改佣金有效期
    public function updateCommissionTime($id,$time){
        $stmt = $this->db->prepare("update user_set_commission_rate set time =:time where id=:id");
        $array = array(
            ':time'=>$time,
            ':id'=>$id
        );
        $stmt->execute($array);
    }
    //用户推广记录入库
    public function addSharePromote($type,$id,$uid,$commission_rate,$set_id){
        $stmt = $this->db->prepare("insert into share_promote (type,obj_id,uid,commission_rate,user_set_id) values (:type,:obj_id,:uid,:commission_rate,:user_set_id)");
        $array = array(
            ':type'=>$type,
            ':obj_id' => $id,
            ':uid' => $uid,
            ':commission_rate'=>$commission_rate,
            ':user_set_id'=>$set_id
        );
        $stmt->execute($array);
        $rs = $this->db->lastInsertId();
        return $rs;
    }
    public function getPlatformRuleByType($type){
        $stmt = $this->db->prepare("select num from platform_rule where type=:type ");
        $array = array(
            ':type' => $type,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
}