<?php

class LikeModel{
    private $db;
    private $redis;
    public $type_arr = array(
        1=>'mood',2=>'photo',3=>'blog',4=>'topic',9=>'share',10=>'event',12=>'stage_goods'
    );//1心境 2图片 3日志 4帖子
    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }

    /**
     * 添加喜欢
     * @param $type
     * @param $obj_id
     * @param $uid
     * @return int
     */
    public function add($type,$obj_id,$uid){
        if(!isset($this->type_arr[$type])){
            return -1;
        }
        $feedModel = new FeedModel();
        $data = $feedModel->getDataByTypeAndId($type,$obj_id);
        if(!$data){
            return -2;
        }
        if($this->hasData($type,$obj_id,$uid)){
            return -3;
        }
        $is_read = 0;
        if($uid == $data['uid']){
            $is_read = 1;
        }
        $stmt = $this->db->prepare("insert into `like` (obj_id,obj_uid,uid,type,is_read) values (:obj_id,:obj_uid,:uid,:type,:is_read)
        on duplicate key update status = 1,add_time=:add_time");
        $array = array(
            ':obj_id' => $obj_id,
            ':obj_uid' => $data['uid'],
            ':uid' => $uid,
            ':type' => $type,
            ':is_read' => $is_read,
            ':add_time' => date("Y-m-d H:i:s")
        );
        $stmt->execute($array);
        $like_id = $this->db->lastInsertId();
        //清除最新喜欢的缓存
        $like_key = $this->type_arr[$type].':like:'.$obj_id;
        $this->redis->del($like_key);
        //更新喜欢的数量
        $this->updateLikeNum($obj_id,$type);
        //发放福报值和经验
        $scoreModel = new ScoreModel();
        $scoreModel->add($data['uid'],0,'like',$like_id);
        return $like_id;
    }

    public function updateLikeNum($id,$type){
        $array = array(
            ':id'=>$id,
        );
        switch($type){
            case 1:
                $stmt = $this->db->prepare("update mood set like_num = like_num + 1 where id=:id ");
                $stmt->execute($array);
            case 2:
                $stmt = $this->db->prepare("update album_photo set like_num = like_num + 1 where id=:id ");
                $stmt->execute($array);
            case 3:
                $stmt = $this->db->prepare("update blog set like_num = like_num + 1 where id=:id ");
                $stmt->execute($array);
            case 4:
                $stmt = $this->db->prepare("update topic set like_num = like_num + 1 where id=:id ");
                $stmt->execute($array);
            case 9:
                $stmt = $this->db->prepare("update share set like_num = like_num + 1 where id=:id ");
                $stmt->execute($array);
            case 10:
                $stmt = $this->db->prepare("update event set like_num = like_num + 1 where id=:id ");
                $stmt->execute($array);
        }
    }
    /**
     * 取消喜欢
     * @param $id
     * @param $uid
     * @return mixed
     */
    public function del($id,$uid){
        $stmt = $this->db->prepare("update `like` set status = 0 where id = :id and uid = :uid");
        $array = array(
            ':id' => $id,
            ':uid' => $uid,
        );
        $stmt->execute($array);
        return $stmt->rowCount();
    }

    //获取收藏动态列表
    public function getFeedList($uid,$start=0,$size=1,$type=0){
        if($type == 0){
            $stmt = $this->db->prepare("select id,obj_id,type,add_time from `like` where uid = :uid and status = 1 order by id desc limit :start,:size");
            $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
            $stmt->execute();
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $this->db->prepare("select count(*) as num from `like` where uid = :uid and status = 1");
            $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
        }else{
            $stmt = $this->db->prepare("select id,obj_id,type,add_time from `like` where uid = :uid and type = :type and status = 1 order by id desc limit :start,:size");
            $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':type' ,  $type ,  PDO :: PARAM_INT );
            $stmt->execute();
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $this->db->prepare("select count(*) as num from `like` where uid = :uid and type = :type and status = 1");
            $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':type' ,  $type ,  PDO :: PARAM_INT );
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        $feedList = array();
        if($list){
            foreach($list as $val){
                if($type == 0){
                    $feedKey = json_encode(array($val['type'],$val['obj_id']));
                }else{
                    $feedKey = $val['obj_id'];
                }
                $feedList[$feedKey] = $val['add_time'];
            }
        }
        return array(
            'list' => $feedList,
            'size' => $count['num']
        );
    }

    //获取喜欢内容的用户列表
    public function getUserList($obj_id,$type,$start=0,$size=1,$uid=0){
        if($type == 0){
            $stmt = $this->db->prepare("select id,uid,type,add_time from `like` where obj_id = :obj_id and status = 1 order by id desc limit :start,:size");
            $stmt->bindValue ( ':obj_id' ,  $obj_id ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
            $stmt->execute();
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $list;
        }else{
            //$field = $uid ? " and uid != $uid " : "";
            $field = "";
            $stmt = $this->db->prepare("select id,uid,type,add_time from `like` where obj_id = :obj_id and type = :type and status = 1 $field order by id desc limit :start,:size");
            $stmt->bindValue ( ':obj_id' ,  $obj_id ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':type' ,  $type ,  PDO :: PARAM_INT );
            $stmt->execute();
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $list;
        }
    }

    //获取赞我的列表
    public function getLikeMeList($obj_uid,$start=0,$size=1,$update_is_read=0,$token,$version){
        $stmt = $this->db->prepare("select id,obj_id,uid,type,add_time,is_read from `like` where uid != :obj_uid and obj_uid = :obj_uid and status = 1
        order by is_read,add_time desc limit :start,:size");
        $stmt->bindValue ( ':obj_uid' ,  $obj_uid ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
        $stmt->execute();
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->db->prepare("select count(*) as num from `like` where uid != :obj_uid and obj_uid = :obj_uid and status = 1");
        $stmt->bindValue ( ':obj_uid' ,  $obj_uid,  PDO :: PARAM_INT );
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        if($list){
            $feedModel = new FeedModel();
            $userModel = new UserModel();
            foreach($list as $key=>$val){
                $list[$key] = $feedModel->getDataByTypeAndId($val['type'],$val['obj_id'],1);
                if($update_is_read){
                    $ids[] = $val['id'];
                }
                if(!$list[$key]){
                    unset($list[$key]);
                    continue;
                }
                $list[$key]['is_read'] = $val['is_read'];
                $list[$key]['push_id'] = $val['id'];
                $list[$key]['feed_type'] = $val['type'];
                $list[$key]['add_time'] = Common::show_time($val['add_time']);
                $list[$key]['time'] = $val['add_time'];
                $list[$key]['user'] = $userModel->getUserData($val['uid'],$obj_uid);
                if($list[$key]['feed_type']==3||($list[$key]['feed_type']==9&&$list[$key]['type']==3)){
                    $list[$key]['url'] = I_DOMAIN.'/b/'.$val['obj_id'].'?token='.$token.'&version='.$version;
                    $list[$key]['share_data']['url'] = I_DOMAIN.'/b/'.$val['obj_id'].'?token='.$token.'&version='.$version;
                }elseif($list[$key]['feed_type']==9&&$list[$key]['type']==4){
                    $list[$key]['url'] = I_DOMAIN.'/t/'.$list[$key]['obj_id'].'?token='.$token.'&version='.$version;
                    $list[$key]['share_data']['url'] = $list[$key]['url'];
                }elseif($list[$key]['feed_type']==9&&$list[$key]['type']==10){
                    $list[$key]['url'] = I_DOMAIN.'/e/'.$list[$key]['obj_id'].'?token='.$token.'&version='.$version;
                    $list[$key]['share_data']['url'] = $list[$key]['url'];
                }elseif($list[$key]['feed_type']==9&&$list[$key]['type']==12){
                    $list[$key]['url'] = I_DOMAIN.'/g/'.$list[$key]['obj_id'].'?token='.$token.'&version='.$version;
                    $list[$key]['share_data']['url'] = $list[$key]['url'];
                }elseif($list[$key]['feed_type']==3){
                    $list[$key]['url'] = I_DOMAIN.'/b/'.$list[$key]['id'].'?token='.$token.'&version='.$version;
                }elseif($list[$key]['feed_type']==4){
                    $list[$key]['url'] = I_DOMAIN.'/t/'.$list[$key]['id'].'?token='.$token.'&version='.$version;
                }elseif($list[$key]['feed_type']==10){
                    $list[$key]['url'] = I_DOMAIN.'/e/'.$list[$key]['id'].'?token='.$token.'&version='.$version;
                }elseif($list[$key]['feed_type']==12){
                    $list[$key]['url'] = I_DOMAIN.'/g/'.$list[$key]['id'].'?token='.$token.'&version='.$version;
                }else{
                    $list[$key]['url'] = '';
                }
            }
            if($update_is_read){
                $this->updateIsRead($ids);
            }
        }
        $data = array();
        foreach($list as $v){
          $data[] = $v;
        }
        return array(
            'list' => $data,
            'size' => $count['num']
        );
    }

    private function getLike($type,$id,$start,$end){
        $like_key = $type.':like:'.$id;
        $key = $type.':info:'.$id;
        if(!$this->redis->exists($like_key)){
            $list = $this->getUserList($id,array_search($type,$this->type_arr),0,8);
            $pipeline = $this->redis->multi();
            $pipeline->hSet($key,'like_num', $list['size']);
            if($list['list']){
                foreach($list['list'] as $val){
                    $pipeline->zAdd($like_key,strtotime($val['add_time']),$val['uid']);
                }
            }
            $pipeline->expire($like_key,3600);
            $pipeline->exec();
        }
        return $this->redis->zRevRange($like_key, $start, $end, true);
    }

    //获取访问列表,包括用户信息
    public function getList($type,$id,$start=0,$end=1){
        $arr = $this->getLike($type,$id,$start,$end);
        $list = array();
        if($arr){
            $userModel = new UserModel();
            foreach($arr as $k=>$v){
                $user = $userModel->getUserData($k);
                $user['like_time'] = Common::show_time($v);
                $list[] = $user;
            }
        }
        $home_key = $type.':info:'.$id;
        $size = $this->redis->hGet($home_key,'like_num');
        return array(
            'list' => $list,
            'size' => $size
        );
    }

    public function updateIsRead($ids){
        if(!$ids){
            return false;
        }
        $id = implode(',',$ids);
        $stmt = $this->db->prepare("update `like` set is_read = 1 where id in ($id) and is_read = 0");
        $stmt->execute();
        return true;
    }

    public function getUnReadNum($uid){
        $stmt = $this->db->prepare("select count(*) as num,max(add_time) as last_time from `like` where uid != :obj_uid and obj_uid = :obj_uid and is_read = 0 and status = 1 and type in(1,4,9,10,12) ");
        $array = array(
            ':obj_uid' => $uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        if($rs['num']==0){
            $stmt_time = $this->db->prepare("select update_time as last_time from `like` where uid != :obj_uid and obj_uid = :obj_uid and is_read = 1 and status = 1 and type in(1,4,9,10,12) order by update_time desc limit 1 ");
            $array = array(
                ':obj_uid' => $uid,
            );
            $stmt_time->execute($array);
            $rs_time = $stmt_time->fetch(PDO::FETCH_ASSOC);
            $rs['last_time'] = $rs_time['last_time'];
        }
        return $rs;
    }

    //type  1心境 2照片 3日志 4帖子 9分享 10商家活动
    public function hasData($type,$obj_id,$uid){
        $stmt = $this->db->prepare("select count(*) as num from `like` where uid =:uid and type = :type and obj_id = :obj_id and status = 1");
        $array = array(
            ':uid' => $uid,
            ':type' => $type,
            ':obj_id' => $obj_id
        );
        $stmt->execute($array);
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        return $count['num'];
    }
    //获得喜欢该对象的信息列表
    public function likeList($obj_id,$type,$page,$size,$uid){
        $start = ($page-1)*$size;
        $result = $this->getUserList($obj_id,$type,$start,$size,$uid);
        $list = array();
        if($result){
            $userModel = new UserModel();
            foreach($result as $key=>$val){
                $user = $userModel->getUserData($val['uid'],$uid);
                $list[$key]['uid'] = $user['uid'];
                $list[$key]['did'] = $user['did'];
                $list[$key]['nick_name'] = $user['nick_name'];
                $list[$key]['avatar'] = $user['avatar'];
                $list[$key]['self'] = $user['self'];
                $list[$key]['ico_type'] = $user['ico_type'];
                $list[$key]['relation'] = $user['relation'];
                $list[$key]['type'] = $user['type'];
            }
        }
        return array(
            'list' => $list,
            'size' => $this->getLikeNum($obj_id,$type),
            'feed_type'=>$type,
            'id'=>$obj_id
        );
    }
    //获得喜欢该对象的信息列表
    public function getLikeList($obj_id,$type,$page,$size){
        $start = ($page-1)*$size;
        $result = $this->getUserList($obj_id,$type,$start,$size);
        return $result;
    }

    public function getLikeNum($obj_id,$type) {
        $stmt = $this->db->prepare("select count(*) as num from `like` where obj_id = :obj_id and type =:type and status = 1");
        $stmt->bindValue ( ':obj_id' ,  $obj_id ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':type' ,  $type ,  PDO :: PARAM_INT );
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        return $count['num'];
    }
    //获取所有的喜欢列表
    public function getAllLikeList($obj_id,$type,$uid){
        $stmt = $this->db->prepare("select id,uid,type,add_time from `like` where obj_id = :obj_id
        and type = :type and status = 1 order by id desc");
        $stmt->bindValue ( ':obj_id' ,  $obj_id ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':type' ,  $type ,  PDO :: PARAM_INT );
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $userModel = new UserModel();
            foreach($result as $key=>$val){
                $result[$key] = $userModel->getUserData($val['uid']);
                $followModel = new FollowModel();//是否已关注
                $result[$key]['relation'] = $followModel->getRelation($uid,$val['uid']);
            }
        }
        return $result;
    }

    //根据obj_id更新喜欢数据
    public function updateLike($type,$obj_id,$status){
        $stmt = $this->db->prepare("update `like` set status = :status, update_time = :update_time where obj_id = :obj_id and type = :type");
        $array = array(
            ':obj_id' => $obj_id,
            ':type' => $type,
            ':status' => $status,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        return $stmt->rowCount();
    }

    /**
     * 数据库操作队列--喜欢
     *
     * @param $uid
     * @param $id
     * @param $type
     */
    public function initLike($uid, $id, $type,$message)
    {
        $key = "init:like:".$this->type_arr[$type];
        $this->redis->rPush($key, json_encode(array('uid' => $uid, 'id' => $id, 'type' => $type,'message'=>$message)));
    }
}