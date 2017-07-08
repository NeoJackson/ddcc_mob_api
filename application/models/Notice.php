<?php
class NoticeModel {
    private $db;
    private $redis;
    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }
    public function get($id){
        if(!$id){
            return false;
        }
        $stmt = $this->db->prepare("select id,uid,content,start_time from notice where status = 1 and id = :id");
        $array = array(
            ':id' => $id,
        );
        $stmt->execute($array);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data;
    }
    //消息列表
    public function getPushList($uid,$type,$start,$size){
        $data = array(
            'list' => $this->getList($uid,$type,$start,$size,1),
            'size' =>$this->getUnReadNum($uid,$type)
        );
        return $data;
    }
    //更新未读为已读
    public function updateIsRead($ids){
        if(!$ids){
            return false;
        }
        $place_holders  =  implode ( ',' ,  array_fill ( 0 ,  count ( $ids ),  '?' ));
        $stmt = $this->db->prepare("update notice_push set is_read = 1 where id in ($place_holders) and is_read = 0");
        $stmt->execute($ids);
        return true;
    }

    //删除消息
    public function del($id,$uid){
        if(!$id || !$uid){
            return -1;
        }
        $stmt = $this->db->prepare("update notice_push set status = 0 where id = :id and uid = :uid");
        $array = array(
            ':id' => $id,
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        return 1;
    }

    public function getUnReadNum($uid,$type){
        $fields='';
        if($type==2){
            $fields = 'and is_ios = 1';
        }elseif($type ==3){
            $fields = 'and is_android = 1';
        }
        $stmt = $this->db->prepare(" SELECT COUNT(*) AS num,max(add_time) as last_time FROM notice_push  WHERE uid = :uid AND is_read = 0 AND status = 1 $fields");
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        if($rs['num']==0){
            $stmt_time = $this->db->prepare(" SELECT update_time as last_time FROM notice_push  WHERE uid = :uid AND is_read = 1 AND status = 1 $fields order by update_time desc limit 1");
            $array = array(
                ':uid' => $uid
            );
            $stmt_time->execute($array);
            $rs_time = $stmt_time->fetch(PDO::FETCH_ASSOC);
            $rs['last_time'] = $rs_time['last_time'];
        }
        return $rs;
    }

    public function getList($uid,$type,$start,$size,$update_is_read=0){
        $fields='';
        if($type==2){
            $fields = 'and is_ios = 1';
        }elseif($type ==3){
            $fields = 'and is_android = 1';
        }
        $stmt = $this->db->prepare("select id, uid, notice_id, notice_uid, app_content as content, is_read, is_sns, is_android,
        is_ios, status, add_time, update_time from notice_push where uid = :uid and status = 1 $fields order by is_read,add_time desc limit :offset,:size");
        $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':offset' ,  $start ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
        $stmt->execute();
        $pushList =  $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($pushList){
            $userModel = new UserModel();
            $ids = array();
            foreach($pushList as $key=>$val){
                if($update_is_read){
                    $ids[] = $val['id'];
                }
                $pushList[$key]['add_time'] = Common::show_time($val['add_time']);
                $num = $this->getUnReadNum($uid,$type);
                $pushList[$key]['num'] =$num['num'];
                $noticeUid = $val['notice_uid'] ? $val['notice_uid'] : 8931;
                $pushList[$key]['user'] = $userModel->getUserData($noticeUid);//暂默认才府小管家
                $pushList[$key]['user_info'] = $userModel->getUserData($val['uid']);
                $pushList[$key]['content'] = Common::showEmoticon($val['content'],1);
            }
            if($update_is_read){
                $this->updateIsRead($ids);
            }
        }
        return $pushList;
    }
    //发送系统消息
    public function addNotice($uid,$content,$is_sns=1,$is_android=1,$is_ios=1){
        $app_content = strip_tags($content);
        $stmt = $this->db->prepare("insert into notice_push (uid,notice_id,content,app_content,is_sns,is_android,is_ios) values (:uid,0,:content,:app_content,:is_sns,:is_android,:is_ios)");
        $array = array(
            ':uid' => $uid,
            ':content' => $content,
            ':app_content' => $app_content,
            ':is_sns'=>$is_sns,
            ':is_android'=>$is_android,
            ':is_ios'=>$is_ios
        );
        $stmt->execute($array);
        return $stmt->rowCount();
    }
}