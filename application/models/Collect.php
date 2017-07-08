<?php

class CollectModel{
    private $db;
    private $redis;
    public $type_arr = array(
        1=>'mood',2=>'photo',3=>'blog',4=>'topic',9=>'share',10=>'event',12=>'stage_goods'
    );//1心境 2图片 3日志 4帖子 5商家活动 9分享
    public $eventType = array(
        1 => '活动', 2 => '推广', 3 => '培训', 4 => '商品', 5 => '投票',6=>'展览',7=>'演出',8=>'展演'
    );
    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }

    /**
     * 添加收藏
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
        $stmt = $this->db->prepare("insert into collect (obj_id,uid,type) values (:obj_id,:uid,:type) on duplicate key update status = 1");
        $array = array(
            ':obj_id' => $obj_id,
            ':uid' => $uid,
            ':type' => $type
        );
        $stmt->execute($array);
        $num = $stmt->rowCount();
        //更新收藏的数量
        $this->updateCollectNum($obj_id,$type);
        return $num;
    }

    //更新收藏的数量
    public function updateCollectNum($id,$type,$num=1){
        $array = array(
            ':id'=>$id,
        );
        switch($type){
            case 1:
                $stmt = $this->db->prepare("update mood set collect_num = collect_num + $num where id=:id ");
                $stmt->execute($array);
            case 2:
                $stmt = $this->db->prepare("update album_photo set collect_num = collect_num + $num where id=:id ");
                $stmt->execute($array);
            case 3:
                $stmt = $this->db->prepare("update blog set collect_num = collect_num + $num where id=:id ");
                $stmt->execute($array);
            case 4:
                $stmt = $this->db->prepare("update topic set collect_num = collect_num + $num where id=:id ");
                $stmt->execute($array);
            case 10:
                $stmt = $this->db->prepare("update event set collect_num = collect_num + $num where id=:id ");
                $stmt->execute($array);
            case 12:
                $stmt = $this->db->prepare("update stage_goods set collect_num = collect_num + $num where id=:id ");
                $stmt->execute($array);
        }
    }
    /**
     * 取消收藏
     * @param $type
     * @param $id
     * @param $uid
     * @return mixed
     */
    public function del($type,$id,$uid){
        $stmt = $this->db->prepare("update collect set status = 0 where type = :type and obj_id = :obj_id and uid = :uid");
        $array = array(
            ':type' => $type,
            ':obj_id' => $id,
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $rowCount = $stmt->rowCount();
        if($rowCount){
            $this->updateCollectNum($id,$type,-1);
        }
        return $rowCount;

    }

    //获取收藏列表(帖子+服务)
    public function getFeedList($uid,$start=0,$size=1,$type=0){
        if($type == 0){
            $stmt = $this->db->prepare("select id,obj_id,type,add_time from collect where uid =:uid and status = 1 order by id desc limit :start,:size");
            $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
            $stmt->execute();
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $this->db->prepare("select count(*) as num from collect where uid =:uid and status = 1");
            $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
        }else{
            $stmt = $this->db->prepare("select id,obj_id,type,add_time from collect where uid =:uid and type = :type and status = 1 order by id desc limit :start,:size");
            $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':type' ,  $type ,  PDO :: PARAM_INT );
            $stmt->execute();
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $this->db->prepare("select count(*) as num from collect where uid =:uid and type = :type and status = 1");
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

    public function hasData($type,$obj_id,$uid){
        $stmt = $this->db->prepare("select count(*) as num from collect where uid =:uid and type = :type and obj_id = :obj_id and status = 1");
        $array = array(
            ':uid' => $uid,
            ':type' => $type,
            ':obj_id' => $obj_id
        );
        $stmt->execute($array);
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        return $count['num'];
    }
    //福报值排行
    public function scoreRank(){
        $sql = 'select * from ( SELECT @rownum:=@rownum+1 AS rownum, f.*
                                   FROM (SELECT @rownum:=0) r,
                                        (SELECT i.score,i.exp,u.nick_name,u.avatar,u.did,u.uid
                                            FROM user_info i,user u
                                            WHERE i.uid=u.uid
                                             AND   u.uid  not in (8931,8934,8935,8937,8938,8951,11520)
                                            ORDER BY i.score DESC,u.uid limit 9
                                         ) AS f
                                 ) tmp  ';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $ret = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $ret;
    }
    //2.6版本收藏列表
    public function getCollectList($type,$id,$city,$sort,$page,$size,$uid,$token,$version){
        $start = ($page -1)*$size;
        $conditionType = $type ? ' and type=:type' : '';
        $conditionId = $id ? ' and type_code=:id' : '';
        $conditionCity = $city ? ' and (province=:city or city=:city)' : '';
        if($sort=='默认' ||$sort=='最近开始'){
            $conditionSort = ' order by start_time asc';
        }elseif($sort=='最晚开始'){
            $conditionSort = ' order by start_time desc';
        }
        $sql = "SELECT id,title,type,type_code,cover,add_time FROM event WHERE status < 2 and id IN (SELECT obj_id FROM collect WHERE uid =:uid AND TYPE = 10 AND STATUS = 1)$conditionType $conditionId $conditionCity $conditionSort limit :start,:size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        if($type){
            $stmt->bindValue(':type', $type, PDO::PARAM_INT);
        }
        if($id){
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        }
        if($city){
            $stmt->bindValue(':city', $city, PDO::PARAM_INT);
        }
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $eventModel = new EventModel();
            foreach($result as $k=>$v){
                if($v['type']==1){
                    $type_info = $eventModel->getBusinessEventType($v['type_code']);
                    $result[$k]['type_name'] =$type_info['name'];
                }else{
                    $result[$k]['type_name'] = $this->eventType[$v['type']];
                }
                $priceInfo = $eventModel->getPrice($v['id']);
                $result[$k]['price_count'] = count($priceInfo);
                if($priceInfo){
                    if(count($priceInfo)>1){
                        $arr = Common::array2sort($priceInfo,'unit_price');
                        $result[$k]['price'] = $arr[0]['unit_price'];
                    }else{
                        $result[$k]['price'] = $priceInfo[0]['unit_price'];
                    }
                }else{
                    $result[$k]['price'] = 0;
                }

                $result[$k]['url'] = I_DOMAIN.'/e/'.$v['id'].'?token='.$token.'&version='.$version;
                $result[$k]['type_code'] = $v['type_code'] ? $v['type_code']:'';
                $result[$k]['cover'] = IMG_DOMAIN.$v['cover'];
            }
        }
        return $result;
    }
}