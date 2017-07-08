<?php
/**
 * Created by PhpStorm.
 * User: lichen
 * Date: 14-8-1
 * Time: 下午1:14
 */
class MessageModel {
    private $db;
    public function __construct() {
        $this->redis = CRedis::getInstance();
        $this->db = DB::getInstance();
    }

    //发送私信
    public function addLetter($uid,$to_uid,$content,$type=0){
        $userModel = new UserModel();
        $user_info = $userModel->getUserByUid($to_uid);
        if(!$user_info){
            return -1;
        }
        $stmt = $this->db->prepare("insert into message (uid, to_uid, type, content, add_time)
        values (:uid, :to_uid, :type, :content, :add_time)");
        $array = array(
            ':uid' => $uid,
            ':to_uid' => $to_uid,
            ':type' => $type,
            ':content' => $content,
            ':add_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        return $id;
    }

    //发给我的私信数目
    public function getLetterNum($uid,$field){
        if(!$uid || !$field){
            return false;
        }
        $stmt = $this->db->prepare("select count($field) as num from message where to_uid = :to_uid and to_status = 1");
        $stmt->bindValue(':to_uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //发给我的私信列表
    public function getLetterList($to_uid,$page,$size){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select mm.id,mm.uid,mm.to_uid,
        (select count(id) from message m where m.uid = mm.uid and m.to_uid = mm.to_uid and is_read = 0 ) as num,
        (SELECT is_read FROM message m WHERE m.uid = mm.uid AND m.to_uid = mm.to_uid AND to_status = 1 ORDER BY add_time DESC LIMIT 1) AS is_read,
        (SELECT add_time FROM message m WHERE m.uid = mm.uid AND m.to_uid = mm.to_uid AND to_status = 1 ORDER BY add_time DESC LIMIT 1) AS add_time
        from message mm where to_uid = :to_uid and to_status = 1
        group by mm.uid order by is_read,add_time desc limit :start,:size");
        $stmt->bindValue(':to_uid', $to_uid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $num = $this->getLetterNum($to_uid,'distinct uid');
        $sum = $this->getLetterNum($to_uid,'id');
        if(!$num){
            $num = 0;
        }
        if($result){
            $userModel = new UserModel();
            $uid_arr = $to_uid_arr = array();
            foreach($result as $key=>$val){
                $stmt = $this->db->prepare("select id,uid,to_uid,content,is_read,add_time from message where uid = :uid
                and to_uid = :to_uid and to_status = 1 order by add_time desc LIMIT 1");
                $stmt->bindValue(':uid', $val['uid'], PDO::PARAM_INT);
                $stmt->bindValue(':to_uid', $val['to_uid'], PDO::PARAM_INT);
                $stmt->execute();
                $result[$key] = $stmt->fetch(PDO::FETCH_ASSOC);
                $result[$key]['content'] = $result[$key]['content'];
                $result[$key]['num'] = $val['num'];
                $result[$key]['user'] = $userModel->getUserData($val['uid']);
                $result[$key]['add_time'] = Common::show_time($result[$key]['add_time']);
                $uid_arr[$key] = $val['uid'];
                $to_uid_arr[$key] = $val['to_uid'];
            }
            $this->updateIsRead($uid_arr,$to_uid_arr);
        }
        return array(
            'list' => $result,
            'num' => $num,
            'sum' => $sum
        );
    }

    //更新私信推送为已读
    public function updateIsRead($uid_arr,$to_uid_arr){
        if(!$uid_arr || !$to_uid_arr){
            return false;
        }
        $uid = implode(',',$uid_arr);
        $to_uid = implode(',',$to_uid_arr);
        $stmt = $this->db->prepare("update message set is_read = 1 where uid in ($uid) and to_uid in ($to_uid) and is_read = 0");
        $stmt->execute();
        return true;
    }

    //当前用户和某个用户的私信条数
    public function getLetterDialogNum($uid,$to_uid){
        $stmt = $this->db->prepare("select count(id) as num from message where (uid = :uid and  to_uid = :to_uid and status = 1)
        or (uid = :to_uid and  to_uid = :uid and status = 1) and status = 1 order by add_time desc,id");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':to_uid', $to_uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //当前用户和某个用户的私信列表
    public function getLetterDialogList($uid,$to_uid,$page,$size){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select id,uid,to_uid,content,type,add_time from message where (uid = :uid and to_uid = :to_uid and status = 1)
        or (uid = :to_uid and to_uid = :uid and to_status = 1) order by add_time desc,id limit :start,:size");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':to_uid', $to_uid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchALL(PDO::FETCH_ASSOC);
        if($result){
            $userModel = new UserModel();
            foreach($result as $key=>$val){
                $result[$key]['user'] = $userModel->getUserData($val['uid']);
                if($val['type'] == 0){
                    $result[$key]['content'] = $val['content'];
                }
                $result[$key]['add_time'] = Common::show_time($val['add_time']);
                $receive_user = $userModel->getUserData($val['to_uid']);
                $result[$key]['receive_user'] = $receive_user;
            }
        }
        return $result;
    }

    //当前用户和某个用户的私信(包括list和num)
    public function getLetterDialog($uid,$to_uid,$page,$size){
        $list = $this->getLetterDialogList($uid,$to_uid,$page,$size);
        $num = $this->getLetterDialogNum($uid,$to_uid);
        return array(
            'list' => $list,
            'num' => $num
        );
    }

    //删除私信
    public function delLetter($uid,$id){
        $letter_info = $this->getLetterById($id);
        if(!$letter_info){
            return -1;
        }
        if($letter_info['uid'] != $uid && $letter_info['to_uid'] != $uid){
            return -2;
        }
        $field = 'status';
        if($letter_info['uid'] == $uid){
            $field = 'status';
        }
        if($letter_info['to_uid'] == $uid){
            $field = 'to_status';
        }
        $stmt = $this->db->prepare("update message set $field = 0 where id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        return 1;
    }

    //根据id查询私信信息
    public function getLetterById($id){
        $stmt = $this->db->prepare("select id,uid,to_uid from message where id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //删除某个人和我的所有对话
    public function delAllLetter($uid,$to_uid){
        $letter_info = $this->getAllLetter($uid,$to_uid);
        if(!$letter_info){
            return -1;
        }
        if($letter_info['uid'] != $uid && $letter_info['to_uid'] != $uid){
            return -2;
        }
        $stmt = $this->db->prepare("update message set status = 0 where uid = :uid and to_uid = :to_uid");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':to_uid', $to_uid, PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->rowCount();
        $stmt_t = $this->db->prepare("update message set to_status = 0 where uid = :to_uid and to_uid = :uid");
        $stmt_t->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt_t->bindValue(':to_uid', $to_uid, PDO::PARAM_INT);
        $stmt_t->execute();
        $count_t = $stmt_t->rowCount();
        if($count<1 && $count_t<1){
            return 0;
        }
        return 1;
    }

    //查询某个人和我的所有对话
    public function getAllLetter($uid,$to_uid){
        $stmt = $this->db->prepare("select id,uid,to_uid from message where (uid = :uid and to_uid = :to_uid and status = 1)
        or (uid = :to_uid and to_uid = :uid and to_status = 1)");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':to_uid', $to_uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //获取评论推送消息数量
    public function getCommentPushNum($uid){
        $stmt = $this->db->prepare("select count(id) as num from comment_push where comment_id in (select id from comment where status<2)
        and status = 1 and uid=:uid");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //获取评论推送消息
    public function getCommentPushList($uid,$page,$size,$token,$version){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select id,is_read,comment_id,type,add_time from comment_push where uid = :uid and status = 1
        and comment_id in (select id from comment where status<2) order by is_read,add_time desc limit :start,:size");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchALL(PDO::FETCH_ASSOC);
        $num = $this->getCommentPushNum($uid);
        if($result){
            $feedModel = new FeedModel();
            $userModel = new UserModel();
            $ids = array();
            foreach($result as $key=>$val){
                $result[$key] = $feedModel->getCommentInfoById($val['comment_id']);
                $ids[$key] = $val['id'];
                if($result[$key]['reply_id']){
                    $result[$key]['comment_info'] = $feedModel->getCommentInfoById($result[$key]['reply_id']);//该回复的主评
                    $result[$key]['comment_user'] = $userModel->getUserData($result[$key]['reply_uid'],$uid);
                }
                $result[$key]['time'] = $val['add_time'];
                $result[$key]['add_time'] = Common::show_time($val['add_time']);
                $result[$key]['is_read'] = $val['is_read'];
                $result[$key]['push_id'] = $val['id'];
                $result[$key]['info_type'] = $val['type'];
                $result[$key]['user'] = $userModel->getUserData($result[$key]['uid'],$uid);
                $result[$key]['reply_user'] = $userModel->getUserData($result[$key]['reply_uid'],$uid);
                $result[$key]['obj_info'] = $feedModel->getDataByTypeAndId($result[$key]['type'],$result[$key]['obj_id']);
                if($result[$key]['type']==3){
                    $result[$key]['obj_info']['url'] = I_DOMAIN.'/b/'.$result[$key]['obj_id'].'?token='.$token.'&version='.$version;
                }elseif($result[$key]['type']==4){
                    $result[$key]['obj_info']['url'] = I_DOMAIN.'/t/'.$result[$key]['obj_id'].'?token='.$token.'&version='.$version;
                }elseif($result[$key]['type']==10){
                    $result[$key]['obj_info']['url'] = I_DOMAIN.'/e/'.$result[$key]['obj_id'].'?token='.$token.'&version='.$version;
                }elseif($result[$key]['type']==12){
                    $result[$key]['obj_info']['url'] = I_DOMAIN.'/g/'.$result[$key]['obj_id'].'?token='.$token.'&version='.$version;
                }elseif($result[$key]['type']==9&&isset($result[$key]['obj_info']['type'])&&$result[$key]['obj_info']['type']==3){
                    $result[$key]['obj_info']['url'] = I_DOMAIN.'/b/'.$result[$key]['obj_info']['obj_id'].'?token='.$token.'&version='.$version;
                    $result[$key]['obj_info']['share_data']['url'] = I_DOMAIN.'/b/'.$result[$key]['obj_info']['obj_id'].'?token='.$token.'&version='.$version;
                }elseif($result[$key]['type']==9&&isset($result[$key]['obj_info']['type'])&&$result[$key]['obj_info']['type']==4){
                    $result[$key]['obj_info']['url'] = I_DOMAIN.'/t/'.$result[$key]['obj_info']['obj_id'].'?token='.$token.'&version='.$version;
                    $result[$key]['obj_info']['share_data']['url'] = I_DOMAIN.'/t/'.$result[$key]['obj_info']['obj_id'].'?token='.$token.'&version='.$version;
                }elseif($result[$key]['type']==9&&isset($result[$key]['obj_info']['type'])&&$result[$key]['obj_info']['type']==10){
                    $result[$key]['obj_info']['url'] = I_DOMAIN.'/e/'.$result[$key]['obj_info']['obj_id'].'?token='.$token.'&version='.$version;
                    $result[$key]['obj_info']['share_data']['url'] = I_DOMAIN.'/t/'.$result[$key]['obj_info']['obj_id'].'?token='.$token.'&version='.$version;
                }elseif($result[$key]['type']==9&&isset($result[$key]['obj_info']['type'])&&$result[$key]['obj_info']['type']==12){
                    $result[$key]['obj_info']['url'] = I_DOMAIN.'/g/'.$result[$key]['obj_info']['obj_id'].'?token='.$token.'&version='.$version;
                    $result[$key]['obj_info']['share_data']['url'] = I_DOMAIN.'/g/'.$result[$key]['obj_info']['obj_id'].'?token='.$token.'&version='.$version;
                }else{
                    $result[$key]['obj_info']['url'] = '';
                }
            }
            $this->updateCommentIsRead($ids,'comment_push');
        }
        return array(
            'list' => $result,
            'num' => $num
        );
    }
    //更新评论推送已读
    public function updateCommentIsRead($ids,$table){
        if(!$ids){
            return false;
        }
        $id = implode(',',$ids);
        $stmt = $this->db->prepare("update $table set is_read = 1 where id in ($id) and is_read = 0");
        $stmt->execute();
        return true;
    }

    //根据id查询评论推送消息
    public function getCommentInfoById($id,$table){
        $field = 'uid';
        if($table=='mention'){
            $field = 'm_uid';
        }
        $stmt = $this->db->prepare("select $field from $table where id = :id and status = 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //删除推送的评论
    public function delCommentPush($uid,$id){
        $comment_push_info = $this->getCommentInfoById($id,'comment_push');
        if(!$comment_push_info){
            return -1;
        }
        if($comment_push_info['uid'] != $uid){
            return -2;
        }
        $stmt = $this->db->prepare("update comment_push set status = 0 where id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        return 1;
    }

    //获取@我推送消息数量
    public function getMentionPushNum($uid){
        $stmt = $this->db->prepare("select obj_id, type from mention where m_uid = :m_uid and type in(0,1,3)");
        $stmt->bindValue(':m_uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $feedModel = new FeedModel();
            $moodModel = new MoodModel();
            foreach($result as $key=>$val){
                $obj_info = array();
                if($val['type'] == 0){//评论
                    $obj_info = $feedModel->getCommentInfoById($val['obj_id']);
                }elseif($val['type'] == 1){//心境
                    $obj_info = $moodModel->get($val['obj_id']);
                }
                if(!$obj_info){
                    unset($result[$key]);
                }
            }
        }
        return count($result);
    }

    //消息推送提到我的@我
    public function getMentionPushList($uid,$page,$size,$token,$version){
        if($size > 50){
            $size = 50;
        }
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select id,uid,m_uid,type,obj_id,is_read,add_time from mention where m_uid = :m_uid and status = 1
        order by is_read,add_time desc limit :start,:size");
        $stmt->bindValue(':m_uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchALL(PDO::FETCH_ASSOC);
        $num = $this->getMentionPushNum($uid);
        if($result){
            $feedModel = new FeedModel();
            $userModel = new UserModel();
            $moodModel = new MoodModel();
            $stageMessageModel = new StageMessageModel();
            $ids = array();
            foreach($result as $key=>$val){
                if($val['type'] == 0){//评论
                    $obj_info = $feedModel->getCommentInfo($val['obj_id']);
                    if(isset($obj_info['type'])&&$obj_info['type'] == 2){//相册
                        $result[$key]['album_info'] = $feedModel->getDataByTypeAndId(2,$obj_info['obj_id']);
                    }
                    $result[$key]['url'] = '';
                    if(isset($obj_info['type'])&&$obj_info['type'] == 3){//日志
                        $result[$key]['url'] = I_DOMAIN.'/b/'.$obj_info['obj_id'].'?token='.$token.'&version='.$version;
                    }
                    if(isset($obj_info['type'])&&$obj_info['type'] == 4){//帖子
                        $result[$key]['url'] = I_DOMAIN.'/t/'.$obj_info['obj_id'].'?token='.$token.'&version='.$version;
                    }
                    if(isset($obj_info['type'])&&$obj_info['type'] == 10){//活动信息
                        $result[$key]['url'] = I_DOMAIN.'/e/'.$obj_info['obj_id'].'?token='.$token.'&version='.$version;
                    }
                }elseif($val['type'] == 1){//心境
                    $obj_info = $moodModel->get($val['obj_id'],1);
                }elseif($val['type'] == 2){//驿站留言
                    $obj_info = $stageMessageModel->getStageMessageById($val['obj_id']);
                }elseif($val['type'] == 3){//分享
                    $obj_info = $feedModel->getDataByTypeAndId(9,$val['obj_id']);
                    $result[$key]['url'] = '';
                    if(isset($obj_info['type'])&&$obj_info['type'] == 3){//日志
                        $result[$key]['url'] = I_DOMAIN.'/b/'.$obj_info['obj_id'].'?token='.$token.'&version='.$version;
                    }
                    if(isset($obj_info['type'])&&$obj_info['type'] == 4){//帖子
                        $result[$key]['url'] = I_DOMAIN.'/t/'.$obj_info['obj_id'].'?token='.$token.'&version='.$version;
                    }
                    if(isset($obj_info['type'])&&$obj_info['type'] == 10){//活动信息
                        $result[$key]['url'] = I_DOMAIN.'/e/'.$obj_info['obj_id'].'?token='.$token.'&version='.$version;
                    }
                    if(isset($obj_info['type'])&&$obj_info['type'] == 12){//商品
                        $result[$key]['url'] = I_DOMAIN.'/g/'.$obj_info['obj_id'].'?token='.$token.'&version='.$version;
                    }
                }
                $ids[$key] = $val['id'];
                if(!$obj_info){
                    unset($result[$key]);
                    continue;
                }
                $result[$key]['push_id'] = $val['id'];
                $result[$key]['add_time'] = Common::show_time($val['add_time']);
                $result[$key]['time'] = $val['add_time'];
                $result[$key]['obj_info'] = $obj_info;
                $result[$key]['user'] = $userModel->getUserData($val['uid'],$uid);
            }
            $this->updateCommentIsRead($ids,'mention');
        }
        return array(
            'list' => $result,
            'num' => $num
        );
    }


    //删除@提到我的推送信息
    public function delMentionPush($uid,$id){
        $mention_push_info = $this->getCommentInfoById($id,'mention');
        if(!$mention_push_info){
            return -1;
        }
        if($mention_push_info['m_uid'] != $uid){
            return -2;
        }
        $stmt = $this->db->prepare("update mention set status = 0 where id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        return 1;
    }

    //评论推送、@我推送、私信我未读消息
    public function getUnReadNum($uid,$type){
        if($type == 1){
            $stmt = $this->db->prepare("select count(id) as num,max(add_time) as last_time from comment_push where uid = :uid and is_read = 0 and status = 1
            and comment_id in (select id from comment where status < 2 and type in(1,4,9,10,12))");
        }elseif($type == 2){
            $stmt = $this->db->prepare("select count(id) as num,max(add_time) as last_time from mention m where m_uid = :uid and is_read = 0 and status = 1
            and (obj_id in(select id from mood where status<2 and m.type=1) or obj_id in(select id from comment where status<2 and m.type=0 and type in(1,4,9,10,12))
            or obj_id in(select id from share where status<2 and m.type=3 and content_type = 1 and type in (1,4,10,12)))");
        }elseif($type == 3){
            $stmt = $this->db->prepare("select count(id) as num,max(add_time) as last_time from bounty_push where obj_uid = :uid and is_read = 0 and status = 1");
        }
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$rs['num']){
            if($type == 1){
                $stmt_time = $this->db->prepare("SELECT add_time AS last_time FROM comment_push WHERE uid = :uid AND is_read = 1 AND STATUS = 1
            AND comment_id IN (SELECT id FROM comment WHERE STATUS < 2 AND TYPE IN(1,4,9,10,12)) ORDER BY add_time DESC LIMIT 1");
            }elseif($type==2){
                $stmt_time = $this->db->prepare("select add_time as last_time from mention m where m_uid = :uid and is_read = 1 and status = 1
            and (obj_id in(select id from mood where status<2 and m.type=1) or obj_id in(select id from comment where status<2 and m.type=0 and type in(1,4,9,10,12))
            or obj_id in(select id from share where status<2 and m.type=3 and content_type = 1 and type in (1,4,10,12))) ORDER BY add_time DESC limit 1");
            }elseif($type==3){
                $stmt_time = $this->db->prepare("select add_time as last_time from bounty_push where obj_uid = :uid and is_read = 1 and status = 1 ORDER BY add_time DESC limit 1");
            }
            $array = array(
                ':uid' => $uid,
            );
            $stmt_time->execute($array);
            $rs_time = $stmt_time->fetch(PDO::FETCH_ASSOC);
            $rs['last_time'] = $rs_time['last_time'];
        }
        return $rs;
    }
    //评论推送、@我推送、喜欢、私信、打赏我未读消息
    public function getList($uid,$type){
        if($type == 1){
            $sql = "select id,uid,comment_id from comment_push where uid = :uid and status = 1 and comment_id in (select id from comment where status<2 and type in(1,4,9,10,12))
            order by add_time desc limit 1";
        }elseif($type == 2){
            $sql = "select id,uid,m_uid,type,obj_id,add_time from mention m where m_uid = :uid and status = 1 and (obj_id in(select id from mood
            where status<2 and m.type=1) or obj_id in(select id from comment where status<2 and m.type=0 and type in(1,4,9,10,12))or obj_id in(select id from share where status<2 and m.type=3  and content_type=1 and type in(1,2,3,4,10,12))) order by add_time desc limit 1";
        }elseif($type == 3){
            $sql = "select id,obj_id,uid,type,add_time,is_read from `like` m where obj_uid = :uid and uid != :uid and status = 1 and (obj_id in(select id from mood
            where status<2 and m.type=1) or obj_id in(select id from topic where status<2 and m.type=4 )
            or obj_id in(select id from share where status<2 and m.type=9 and content_type=1) OR obj_id IN(SELECT id FROM event WHERE STATUS<2 AND m.type=10)) order by add_time desc limit 1";
        }elseif($type == 4){
            $sql = "SELECT id,uid,obj_uid,type,content,obj_id,add_time FROM bounty_push m WHERE obj_uid = :uid AND STATUS = 1  AND (obj_id IN(SELECT id FROM mood
            WHERE STATUS<2 AND m.type=1) OR obj_id IN(SELECT id FROM topic WHERE STATUS<2 AND m.type=4 ) OR obj_id IN(SELECT id FROM share WHERE STATUS<2 AND m.type=9 AND content_type=1) OR obj_id IN(SELECT id FROM event WHERE STATUS<2 AND m.type=10) OR obj_id IN(SELECT id FROM stage_goods WHERE STATUS<2 AND m.type=12))
            ORDER BY add_time DESC LIMIT 1";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result_stmt = $stmt->fetch(PDO::FETCH_ASSOC);
        $result = array();
        if($result_stmt){
            $userModel = new UserModel();
            $feedModel = new FeedModel();
            $moodModel = new MoodModel();
            if($type == 1){
                $result = $feedModel->getCommentInfoById($result_stmt['comment_id']);
                $result['user'] = $userModel->getUserData($result['uid'],$uid);
            }elseif($type == 2){
                if($result_stmt['type'] == 0){
                    $result = $feedModel->getCommentInfoById($result_stmt['obj_id']);
                    $result['user'] = $userModel->getUserData($result['uid'],$uid);
                }elseif($result_stmt['type'] == 1){
                    $result = $moodModel->getMood($result_stmt['obj_id']);
                    $result['user'] = $userModel->getUserData($result['uid'],$uid);
                }elseif($result_stmt['type'] == 3){//分享
                $result = $feedModel->getDataByTypeAndId(9,$result_stmt['obj_id']);
                $result['content'] = isset($result['message_content']) ? $result['message_content'] : '';//用于消息中心首页分享原内容显示
                $result['user'] = $userModel->getUserData($result['uid'],$uid);
                if(!$result['share_data']){
                    $result['share_data'] = (object)array();
                }
                $result['add_time'] = $result_stmt['add_time'];
            }
            }elseif($type == 3){
                $result = $feedModel->getDataByTypeAndId($result_stmt['type'],$result_stmt['obj_id']);
                $result['user'] = $userModel->getUserData($result_stmt['uid'],$uid);
                $result['add_time'] = $result_stmt['add_time'];
            }elseif($type == 4){
                $result = $feedModel->getDataByTypeAndId($result_stmt['type'],$result_stmt['obj_id']);
                $result['user'] = $userModel->getUserData($result_stmt['uid'],$uid);
                $result['add_time'] = $result_stmt['add_time'];
            }
        }
        return $result;
    }

    //更新提到我的数据
    public function updateMention($type,$id_arr,$status){
        if(!$id_arr){
            return false;
        }
        $ids  =  is_array($id_arr) ? implode ( ',' , $id_arr) : $id_arr;
        $stmt = $this->db->prepare("update mention set status = :status, update_time = :update_time where obj_id in($ids) and type = :type");
        $array = array(
            ':type' => $type,
            ':status' => $status,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        return $stmt->rowCount();
    }

    //插入赏金推送信息
    public function addReward($uid,$obj_uid,$obj_id,$type,$content,$value){
        $sql = 'insert into bounty_push (uid,obj_uid,obj_id,type,content,value,add_time) SELECT :uid,:obj_uid,:obj_id,:type,:content,:value,:add_time FROM dual
        WHERE NOT EXISTS (SELECT * FROM bounty_push WHERE uid=:uid AND obj_id=:obj_id AND obj_uid=:obj_uid AND UNIX_TIMESTAMP() - 10 < UNIX_TIMESTAMP(add_time))';
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':uid' => $uid,
            ':obj_uid' => $obj_uid,
            ':obj_id' => $obj_id,
            ':type' => $type,
            ':content' => $content,
            ':value' => $value,
            ':add_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        if($id < 1){
            Common::echoAjaxJson(600,"打赏的太快，休息一下吧");
        }
        return 1;
    }

    //打赏消息推送
    public function getRewardMeList($uid,$page,$size,$token,$version){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select id,obj_id,uid,type,add_time,is_read,content,value from bounty_push where obj_uid = :obj_uid and status = 1
        order by is_read,add_time desc limit :start,:size");
        $stmt->bindValue ( ':obj_uid' ,  $uid ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
        $stmt->execute();
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->db->prepare("select count(1) as num from bounty_push where obj_uid = :obj_uid and status = 1");
        $stmt->bindValue ( ':obj_uid' ,  $uid,  PDO :: PARAM_INT );
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        if($list){
            $feedModel = new FeedModel();
            $userModel = new UserModel();
            foreach($list as $key=>$val){
                $list[$key]['push_id'] = $val['id'];
                $list[$key]['obj_info'] = $feedModel->getDataByTypeAndId($val['type'],$val['obj_id']);
                if($val['type']==3){
                    $list[$key]['obj_info']['url'] = I_DOMAIN.'/b/'.$val['obj_id'].'?token='.$token.'&version='.$version;
                }elseif($val['type']==4){
                    $list[$key]['obj_info']['url'] = I_DOMAIN.'/t/'.$val['obj_id'].'?token='.$token.'&version='.$version;
                }elseif($val['type']==10){
                    $list[$key]['obj_info']['url'] = I_DOMAIN.'/e/'.$val['obj_id'].'?token='.$token.'&version='.$version;
                }elseif($val['type']==12){
                    $list[$key]['obj_info']['url'] = I_DOMAIN.'/g/'.$val['obj_id'].'?token='.$token.'&version='.$version;
                }elseif($val['type']==9&&$list[$key]['obj_info']['type']==3&&!(object)$list[$key]['obj_info']['share_data']){
                    $list[$key]['obj_info']['url'] = I_DOMAIN.'/b/'.$list[$key]['obj_info']['obj_id'].'?token='.$token.'&version='.$version;
                    $list[$key]['obj_info']['share_data']['url'] = $list[$key]['obj_info']['url'];
                }elseif($val['type']==9&&$list[$key]['obj_info']['type']==4&&!(object)$list[$key]['obj_info']['share_data']){
                    $list[$key]['obj_info']['url'] = I_DOMAIN.'/t/'.$list[$key]['obj_info']['obj_id'].'?token='.$token.'&version='.$version;
                    $list[$key]['obj_info']['share_data']['url'] = $list[$key]['obj_info']['url'];
                }elseif($val['type']==9&&$list[$key]['obj_info']['type']==10&&!(object)$list[$key]['obj_info']['share_data']){
                    $list[$key]['obj_info']['url'] = I_DOMAIN.'/e/'.$list[$key]['obj_info']['obj_id'].'?token='.$token.'&version='.$version;
                    $list[$key]['obj_info']['share_data']['url'] = $list[$key]['obj_info']['url'];
                }elseif($val['type']==9&&$list[$key]['obj_info']['type']==12&&!(object)$list[$key]['obj_info']['share_data']){
                    $list[$key]['obj_info']['url'] = I_DOMAIN.'/g/'.$list[$key]['obj_info']['obj_id'].'?token='.$token.'&version='.$version;
                    $list[$key]['obj_info']['share_data']['url'] =$list[$key]['obj_info']['url'];
                }else{
                    $list[$key]['obj_info']['url'] = '';
                }
                $list[$key]['time'] = $list[$key]['add_time'];
                $list[$key]['add_time'] = Common::show_time($list[$key]['add_time']);
                $list[$key]['user'] = $userModel->getUserData($val['uid'],$uid);
                $ids[] = $val['id'];
                if(!$list[$key]['obj_info']){
                    unset($list[$key]);
                    continue;
                }
            }
            $this->updateCommentIsRead($ids,'bounty_push');
        }
        $data = array();
        foreach($list as $v){
            $data[] = $v;
        }
        return array(
            'list' => $data,
            'num' => $count['num']
        );
    }

    //删除打赏
    public function delRewardPush($id,$uid){
        $reward_push_info = $this->getRewardInfoById($id);
        if(!$reward_push_info){
            return -1;
        }
        if($reward_push_info['obj_uid'] != $uid){
            return -2;
        }
        $stmt = $this->db->prepare("update bounty_push set status = 0 where id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        return 1;
    }

    //根据id查询打赏推送消息
    public function getRewardInfoById($id){
        $stmt = $this->db->prepare("select obj_uid from bounty_push where id = :id and status = 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //获取打赏记录列表
    public function getRewardList($type,$obj_id,$page,$size,$uid = 0){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select id,uid,obj_uid,obj_id,value,type,add_time from bounty_push where type = :type
        and obj_id = :obj_id order by add_time desc limit :start,:size");
        $stmt->bindValue(':type', $type, PDO::PARAM_INT);
        $stmt->bindValue(':obj_id', $obj_id, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->db->prepare("select count(1) as num from bounty_push where type = :type and obj_id = :obj_id");
        $stmt->bindValue(':type', $type, PDO::PARAM_INT);
        $stmt->bindValue(':obj_id', $obj_id, PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result){
            $userModel = new UserModel();
            foreach($result as $key=>$val){
                $user = $userModel->getUserData($val['uid'],$uid);
                $result[$key]['user']['uid'] = $user['uid'];
                $result[$key]['user']['did'] = $user['did'];
                $result[$key]['user']['nick_name'] = $user['nick_name'];
                $result[$key]['user']['avatar'] = $user['avatar'];
                $result[$key]['user']['self'] = $user['self'];
                $result[$key]['user']['ico_type'] = $user['ico_type'];
                $result[$key]['user']['relation'] = $user['relation'];
                $result[$key]['user']['type'] = $user['type'];
                $result[$key]['user']['sex'] = $user['sex'];
            }
        }
        return array(
            'list' => $result,
            'size' => $count['num']
        );
    }

    public function getRewardListByLastTime($type,$obj_id,$last_time,$size,$uid){
        if($last_time){
            $stmt = $this->db->prepare("select id,uid,obj_uid,obj_id,value,type,add_time from bounty_push where type = :type
        and obj_id = :obj_id and add_time < :last_time order by add_time desc limit :size");
            $stmt->bindValue(':last_time', $last_time, PDO::PARAM_STR);
        }else{
            $stmt = $this->db->prepare("select id,uid,obj_uid,obj_id,value,type,add_time from bounty_push where type = :type
        and obj_id = :obj_id order by add_time desc limit :size");
        }
        $stmt->bindValue(':type', $type, PDO::PARAM_INT);
        $stmt->bindValue(':obj_id', $obj_id, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->db->prepare("select count(1) as num from bounty_push where type = :type and obj_id = :obj_id");
        $stmt->bindValue(':type', $type, PDO::PARAM_INT);
        $stmt->bindValue(':obj_id', $obj_id, PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result){
            $userModel = new UserModel();
            foreach($result as $key=>$val){
                $user= $userModel->getUserData($val['uid'],$uid);
                $result[$key]['user']['uid'] = $user['uid'];
                $result[$key]['user']['did'] = $user['did'];
                $result[$key]['user']['nick_name'] = $user['nick_name'];
                $result[$key]['user']['avatar'] = $user['avatar'];
                $result[$key]['user']['self'] = $user['self'];
                $result[$key]['user']['ico_type'] = $user['ico_type'];
                $result[$key]['user']['relation'] = $user['relation'];
                $result[$key]['user']['type'] = $user['type'];
                $result[$key]['user']['sex'] = $user['sex'];
            }
        }
        return array(
            'list' => $result,
            'size' => $count['num']
        );
    }

    //根据obj_id更新打赏数据
    public function updateReward($type,$obj_id,$status){
        $stmt = $this->db->prepare("update bounty_push set status = :status, update_time = :update_time where obj_id = :obj_id and type = :type");
        $array = array(
            ':obj_id' => $obj_id,
            ':type' => $type,
            ':status' => $status,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        return $stmt->rowCount();
    }

    //查询推送给我的评论消息
    public function getPush($uid,$page,$size){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select comment_id from comment_push where uid = :uid and status = 1
        and comment_id in (select id from comment where status<2) order by is_read,add_time desc");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchALL(PDO::FETCH_ASSOC);
        if($result){
            $feedModel = new FeedModel();
            foreach($result as $key=>$val){
                $rs = $feedModel->getReplyIdById($val['comment_id']);
                if($rs){
                    if($rs['reply_id']){
                        $result[$key]['comment_id'] = $rs['reply_id'];
                    }else{
                        $result[$key]['comment_id'] = $rs['id'];
                    }
                }
            }
        }
        $result = array_slice(array_unique($this->arrayMultiToSingle($result,'comment_id')),$start,$start+$size,true);
        $list = array();
        if($result){
            foreach($result as $val){
                $list[] = $feedModel->getCommentInfoById($val);
            }
        }
        return $result;
    }

    //将多维数组转化成一维数组
    public function arrayMultiToSingle($array,$field) {
        foreach($array as $key=>$val){
            $array[$key] = $val[$field];
        }
        return $array;
    }
    //查询推送给我的评论消息
    public function getCommentPush($uid,$page,$size){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select comment_id from comment_push where uid = :uid and status = 1
        and comment_id in (select id from comment where status<2) order by is_read,add_time desc");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchALL(PDO::FETCH_ASSOC);
        if($result){
            $feedModel = new FeedModel();
            foreach($result as $key=>$val){
                $rs = $feedModel->getReplyIdById($val['comment_id']);
                if($rs){
                    if($rs['reply_id']){
                        $result[$key]['comment_id'] = $rs['reply_id'];
                    }else{
                        $result[$key]['comment_id'] = $rs['id'];
                    }
                }
            }
        }
        $commentList = array_unique(Common::arrayMultiToSingle($result,'comment_id'));
        $count = count($commentList);
        $result = array_slice($commentList,$start,$size,true);
        $list = $ids = array();
        if($result){
            $userModel = new UserModel();
            foreach($result as $val){
               $data = $feedModel->getCommentInfoById($val);
                if($data){
                    $pushInfo = $this->getCommentPushById($uid,$val);//查询评论推送
                    $push_id_array = array();
                    if($pushInfo){
                        $ids[] = $pushInfo['id'];
                        $data['is_read'] = $pushInfo['is_read'];
                        $push_id_array[] = $pushInfo['id'];
                        $data['add_time'] = Common::show_time($pushInfo['add_time']);
                        $data['time'] = $pushInfo['add_time'];
                    }
                    $replyList = array();
                    if($data['id']){
                        $replyList = $this->getReplyListById($val,$uid);//根据主评查询回复列表
                        if($replyList){
                            foreach($replyList as $re_val){
                                $replyInfo = $this->getCommentPushById($uid,$re_val['id']);//查询回复推送
                                if($replyInfo){
                                    $ids[] = $replyInfo['id'];
                                    $push_id_array[] = $replyInfo['id'];
                                    $data['is_read'] = $replyInfo['is_read'];
                                    $data['add_time'] = Common::show_time($replyInfo['add_time']);
                                    $data['time'] = $replyInfo['add_time'];
                                }
                            }
                        }
                    }
                    $data['push_id'] = json_encode($push_id_array);
                    $data['reply_list'] = $replyList;
                    $data['user'] = $userModel->getUserData($data['uid'],$uid);
                    $obj_info = $feedModel->getDataByTypeAndId($data['type'],$data['obj_id'],$uid);
                    $data['obj_info'] = $obj_info?$obj_info:(object)array();
                    $list[] = $data;
                    }
                }
            }
         $this->updateCommentIsRead($ids,'comment_push');
        return array('list'=>$list,'size'=>$count);
    }
    //根据评论id查询推送信息
    public function getCommentPushById($uid,$comment_id){
        $stmt = $this->db->prepare("select id,is_read,comment_id,type,add_time from comment_push where uid = :uid and comment_id=:comment_id and status = 1");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':comment_id', $comment_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    //根据主评id查询次评列表和未读推送回复
    public function getReplyListById($comment_id,$uid=0){
        $stmt = $this->db->prepare("SELECT id,type,obj_id,content,uid,reply_uid,reply_id,status,add_time
        FROM `comment` AS cm WHERE reply_id=:comment_id AND STATUS<2 order by add_time asc");
        $stmt->bindValue(':comment_id', $comment_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $userModel = new UserModel();
            foreach($result as $key=>$val){
                $result[$key]['add_time'] = Common::show_time($result[$key]['add_time']);
                $result[$key]['content'] = $result[$key]['content'];
                $result[$key]['user'] = $userModel->getUserData($val['uid'],$uid);
                $result[$key]['reply_user'] = $userModel->getUserData($val['reply_uid'],$uid);
                $result[$key]['reply_user']['avatar'] = Common::show_img($result[$key]['reply_user']['avatar'],1,160,160);
            }
        }
        return $result;
    }

    //根据obj_id更新评论数据
    public function updateComment($type,$obj_id,$status){
        $stmt = $this->db->prepare("update comment set status = :status, update_time = :update_time where obj_id = :obj_id and type = :type");
        $array = array(
            ':obj_id' => $obj_id,
            ':type' => $type,
            ':status' => $status,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $feedModel = new FeedModel();
        $commentList = $feedModel->getCommentByObjId($type,$obj_id);
        if($commentList){
            $messageModel = new MessageModel();
            foreach($commentList as $val){
                $id_arr[] = $val['id'];
            }
            $status = in_array($status,array(0,1)) ? 1 : 0;
            $messageModel->updateMention(0,isset($id_arr) && $id_arr ? $id_arr : 0,$status);//删除回复中的提到我的
        }
        return $stmt->rowCount();
    }

}