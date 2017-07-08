<?php
/**
 * @name FollowModel
 * @desc Follow数据获取类, 可以访问数据库，文件，其它系统等
 * @author {&$AUTHOR&}
 */
class FeedModel {
    private $redis;
    private $max_feed_num = 1000;
    private $max_feedList_num = 480;
    public $type_arr = array(
        'mood'=>1,'photo'=>2,'blog'=>3,'topic'=>4,'stage'=>5,'wish'=>6,'memorial'=>7,'pray'=>8,'share'=>9,'event'=>10,'stage_message'=>11,'stage_goods'=>12
    );//1心境 2图片 3日志 4帖子 5驿站 6许愿 7缅怀祭拜留言  8祈福 9分享
    public $type_code_arr = array(
        '1'=>'mood','2'=>'photo','3'=>'blog','4'=>'topic','5'=>'stage','6'=>'wish','7'=>'memorial','8'=>'pray','9'=>'share','10'=>'event','11'=>'stage_message','12'=>'stage_goods '
    );//1心境 4帖子 5驿站 9分享 10服务 12 驿站商品
    private $comment_type_arr = array(1,2,3,4,9,10,11,12);//1:心境 2.相册照片 3:日志 4:帖子 9:分享 10:服务 11:驿站商品
     public function __construct() {
        $this->redis = CRedis::getInstance();
        $this->db = DB::getInstance();
    }

    /**
     * 获取动态key值
     * @param $uid
     * @return bool|string
     */
    public function getFeedKey($uid){
        if(!$uid){
            return false;
        }
        return "u:feed:".$uid;
    }

    /**
     * 获取APP动态key值
     * @param $uid
     * @return bool|string
     */
    public function getAppFeedKey($uid){
        if(!$uid){
            return false;
        }
        return "app:feed:".$uid;
    }

    /**
     * 获取驿站动态key值
     * @param $uid
     * @return bool|string
     */
    public function getStageFeedKey($sid){
        if(!$sid){
            return false;
        }
        return "s:feed:".$sid;
    }

    public function getStageFeedsKey($sid,$type=''){
        if(!$sid){
            return false;
        }
        if($type == ''){
            return "s:feeds:".$sid;
        }else{
            return "s:".$type."s:".$sid;
        }
    }

    public function getLikeKey($uid){
        if(!$uid){
            return false;
        }
        return "u:like:".$uid;
    }
    public function getFeedsKey($uid,$type=''){
        if(!$uid){
            return false;
        }
        if($type == ''){
            return "u:feeds:".$uid;
        }else{
            return "u:".$type."s:".$uid;
        }
    }

    public function getAppFeedsKey($uid){
        if(!$uid){
            return false;
        }
        return "app:feeds:".$uid;
    }

    public function getGroupFeedsKey($group_id,$type=''){
        if(!$group_id){
            return false;
        }
        if($type == ''){
            return "u:gfeeds:".$group_id;
        }else{
            return "u:g".$type."s:".$group_id;
        }
    }

    public function getTypeKey($uid,$type){
        if(!$uid){
            return false;
        }
        return "u:".$type.":".$uid;
    }

    public function getStageTypeKey($sid,$type){
        if(!$sid){
            return false;
        }
        return "s:".$type.":".$sid;
    }

    private function addByType($uid,$type,$id,$time){
        $typeKey = $this->getTypeKey($uid,$type);
        $this->redis->zAdd($typeKey,$time,$id);
    }

    private function delByType($uid,$type,$id){
        $typeKey = $this->getTypeKey($uid,$type);
        $this->redis->zDelete($typeKey,$id);
    }

    private function addStageByType($sid,$type,$id,$time){
        $typeKey = $this->getStageTypeKey($sid,$type);
        $this->redis->zAdd($typeKey,$time,$id);
    }

    private function delStageByType($sid,$type,$id){
        $typeKey = $this->getStageTypeKey($sid,$type);
        $this->redis->zDelete($typeKey,$id);
    }

    public function add($uid,$type,$id,$time = ''){
        if($time == ''){
            $time = time();
        }
        $this->addByType($uid,$type,$id,$time);
        $type = $this->type_arr[$type];
        if(!$type){
            return false;
        }
        $feedKey = $this->getFeedKey($uid);
        if($type == '5'){
            $arr = array($type,(int)$id,(int)$uid);
        }elseif($type == '2'){
            $arr = array($type,(int)$id,(int)$time);
        }else{
            $arr = array($type,(int)$id);
        }
        $data = json_encode($arr);
        $this->redis->zAdd($feedKey,$time,$data);
        if($this->redis->zSize($feedKey) > $this->max_feed_num){
            $this->redis->zRemRangeByRank($feedKey, 0, 100);
        }
        //添加到u:feeds
        $feedsKey = $this->getFeedsKey($uid);
        if($this->redis->exists($feedsKey)){
            $this->redis->zAdd($feedsKey,$time,$data);
        }
        return true;
    }

    public function addApp($uid,$type,$id,$time = ''){
        if($time == ''){
            $time = time();
        }
        $type = $this->type_arr[$type];
        if(!$type){
            return false;
        }
        $appFeedKey = $this->getAppFeedKey($uid);
        if($type == '5'){
            $arr = array($type,(int)$id,(int)$uid);
        }elseif($type == '2'){
            $arr = array($type,(int)$id,(int)$time);
        }else{
            $arr = array($type,(int)$id);
        }
        $data = json_encode($arr);
        $this->redis->zAdd($appFeedKey,$time,$data);
        if($this->redis->zSize($appFeedKey) > $this->max_feed_num){
            $this->redis->zRemRangeByRank($appFeedKey, 0, 100);
        }
        //添加到app:feeds
        $appFeedsKey = $this->getAppFeedsKey($uid);
        if($this->redis->exists($appFeedsKey)){
            $this->redis->zAdd($appFeedsKey,$time,$data);
        }
        return true;
    }

    /**
     * 添加到驿站动态--此业务暂已去除
     * @param $sid
     * @param $type
     * @param $id
     * @param string $time
     * @return bool
     */
    public function addStage($sid,$type,$id,$time = ''){
        if($time == ''){
            $time = time();
        }
        $this->addStageByType($sid,$type,$id,$time);
        $type = $this->type_arr[$type];
        if(!$type){
            return false;
        }
        $feedKey = $this->getStageFeedKey($sid);
        if($type == '5'){
            $arr = array($type,(int)$sid,(int)$id);
        }elseif($type == '2'){
            $arr = array($type,(int)$id,(int)$time);
        }else{
            $arr = array($type,(int)$id);
        }
        $data = json_encode($arr);
        $this->redis->zAdd($feedKey,$time,$data);
        if($this->redis->zSize($feedKey) > $this->max_feed_num){
            $this->redis->zRemRangeByRank($feedKey, 0, 100);
        }
        return true;
    }

    public function del($uid,$type,$id,$add_time=0){
        $this->delByType($uid,$type,$id);
        $type = $this->type_arr[$type];
        if(!$type){
            return false;
        }
        $feedKey = $this->getFeedKey($uid);
        if($type == '5'){
            $arr = array($type,(int)$id,(int)$uid);
        }elseif($type == '2'){
            $arr = array($type,(int)$id,(int)$add_time);
        }else{
            $arr = array($type,(int)$id);
        }
        $data = json_encode($arr);
        $this->redis->zDelete($feedKey,$data);
        $this->clearFeeds($uid);
        return true;
    }
    public function delApp($uid,$type,$id,$add_time=0){
        $this->delByType($uid,$type,$id);
        $type = $this->type_arr[$type];
        if(!$type){
            return false;
        }
        $feedKey = $this->getAppFeedKey($uid);
        if($type == '5'){
            $arr = array($type,(int)$id,(int)$uid);
        }elseif($type == '2'){
            $arr = array($type,(int)$id,(int)$add_time);
        }else{
            $arr = array($type,(int)$id);
        }
        $data = json_encode($arr);
        $this->redis->zDelete($feedKey,$data);
        $this->clearFeeds($uid);
        return true;
    }
    public function delStage($sid,$type,$id){
        $this->delStageByType($sid,$type,$id);
        $type = $this->type_arr[$type];
        if(!$type){
            return false;
        }
        $feedKey = $this->getStageFeedKey($sid);
        if($type == '5'){
            $arr = array($type,(int)$sid,(int)$id);
        }else{
            $arr = array($type,(int)$id);
        }
        $data = json_encode($arr);
        $this->redis->zDelete($feedKey,$data);
        $this->clearStageFeeds($sid);
        return true;
    }

    public function clearStageFeeds($sid){
        $feedsKey = $this->getStageFeedsKey($sid);
        $topicsKey = $this->getFeedsKey($sid,'topic');
        $this->redis->multi()->del($feedsKey)->del($topicsKey)->exec();
    }

    public function clearFeeds($uid){
        $feedsKey = $this->getFeedsKey($uid);
        $appFeedsKey = $this->getAppFeedsKey($uid);
        $moodsKey = $this->getFeedsKey($uid,'mood');
        $topicsKey = $this->getFeedsKey($uid,'topic');
        $photosKey = $this->getFeedsKey($uid,'photo');
        $blogsKey = $this->getFeedsKey($uid,'blog');
        $this->redis->multi()->del($feedsKey)->del($appFeedsKey)->del($moodsKey)->del($topicsKey)->del($photosKey)->del($blogsKey)->exec();
    }

    //清除分组动态
    public function clearGroupFeeds($group_id){
        $feedsKey = $this->getGroupFeedsKey($group_id);
        $moodsKey = $this->getGroupFeedsKey($group_id,'mood');
        $topicsKey = $this->getGroupFeedsKey($group_id,'topic');
        $photosKey = $this->getGroupFeedsKey($group_id,'photo');
        $blogsKey = $this->getGroupFeedsKey($group_id,'blog');
        $this->redis->multi()->del($feedsKey)->del($moodsKey)->del($topicsKey)->del($photosKey)->del($blogsKey)->exec();
    }

    /*
     * 获取动态列表详情
     */
    public function getData($source,$list,$type=0,$uid=0,$flag =0,$version='',$token=''){
        $feedList = array();
        if(!$list){
            return $feedList;
        }
        $userModel = new UserModel();
        $feedModel = new FeedModel();
        $likeModel = new LikeModel();
        $collectModel = new CollectModel();
        $stageModel = new StageModel();
        foreach($list as $key=>$val){
            if($type == 0){
                $item = json_decode($key,true);
            }elseif(array_search($type,$feedModel->type_arr)){
                $item = json_decode($key,true);
                if(is_array($item)){ //加入驿站
                    $item = array($type,$item[0],$uid);
                }else{
                    $item = array($type,$key,$uid);
                }
            }else{
                Common::echoAjaxJson(2,'获取失败');
            }
            if(in_array($source,array('follow','user'))){
                $data = $feedModel->getFeedByItem($item,$val,$uid,$flag,$version,$token);
                if(!isset($data['uid'])){
                    continue;
                }elseif($data['uid']!=$uid && !in_array($data['status'],array(0,1))){
                    continue;
                }elseif($data['uid']==$uid && !in_array($data['status'],array(0,1,5))){
                    continue;
                }
                if($item[0]==2 && $data['photo']['size']==0 ){
                    continue;
                }
            }else{
                if($source == "share"){
                    $data = $feedModel->getDataByTypeAndId($item[0],$item[1],$uid,1);
                }elseif($source == 'collect'){
                    $data = $feedModel->getDataByTypeAndId($item[0],$item[1],$uid, 0, 5);
                }else{
                    $data = $feedModel->getDataByTypeAndId($item[0],$item[1],$uid,$flag);
                }
            }
            if(!isset($data['uid']) || (isset($data['is_public']) &&  $data['is_public'] == 0) || (isset($data['photo']['list']) &&  !$data['photo']['list']) ){
                continue;
            }
            $data['add_time'] = Common::show_time($val);
            $data['last_time'] = (string)$val;
            $data['feed_type'] = (int)$item[0];
            if($item[0] == 5 && $item[2]){
                $data['user'] = $userModel->getUserData($item[2],$uid);
            }else{
                $user= $userModel->getUserData($data['uid'],$uid);
                $angelInfo = $userModel->getInfo($data['uid']);
                $data['user']['angel_info'] = isset($angelInfo['info']) ? $angelInfo['info'] :'';
                $data['user']['uid'] = $user['uid'];
                $data['user']['did'] = $user['did'];
                $data['user']['nick_name'] = $user['nick_name'];
                $data['user']['avatar'] = $user['avatar'];
                $data['user']['self'] = $user['self'];
                $data['user']['ico_type'] = $user['ico_type'];
                $data['user']['relation'] = $user['relation'];
                $b_num = $stageModel->getSidByUid($user['uid']);
                if($b_num){
                    $data['user']['is_business']['num'] =1;
                    $data['user']['is_business']['sid'] =$b_num['sid'];
                }else{
                    $data['user']['is_business']['num'] =0;
                    $data['user']['is_business']['sid'] ='';
                }
                $data['user']['type'] = $user['type'];
                $data['is_like'] = $likeModel->hasData($item[0],$item[1],$uid);
                $data['is_collect'] = $collectModel->hasData($item[0],$item[1],$uid);
                $data['like_list'] = $likeModel->likeList($item[1],$item[0],1,10,$uid);
                $data['comment_list'] = $this->getRedisCommentList($uid,$item[0],$item[1]);
                //如果redis里没有评论，就重新获取存入redis
                if(!$data['comment_list']){
                    $commentList = $this->getCommentListById($item[0],$item[1]);
                    $type_code = $this->type_code_arr[$item[0]];
                    $this->addRedisComment($type_code,$item[1],$commentList);
                    $data['comment_list'] = $this->getRedisCommentList($uid,$item[0],$item[1]);
                }
                $data['comment_num'] = $feedModel->getCommentNum($item[0],$item[1]);
                $data['comment_list'] = $data['comment_list']?$data['comment_list']:array();
                if(in_array($item[0],array(1,4,9,10))){
                    $messageModel = new MessageModel();
                    $data['reward_list'] = $messageModel->getRewardList($item[0],$item[1],1,11);
                }
            }
            $feedList[] = $data;
        }
        return $feedList;
    }


    /*
     * 获取APP动态列表详情
     */
    public function getAppData($list,$uid,$version,$token){
        $feedList = array();
        if(!$list){
            return $feedList;
        }
        $userModel = new UserModel();
        $feedModel = new FeedModel();
        $likeModel = new LikeModel();
        $collectModel = new CollectModel();
        foreach($list as $key=>$val){
            $item = json_decode($key,true);
            $data = $feedModel->getFeedByItem($item,$val,$uid,1,$version,$token);
            if(!isset($data['uid'])){
                continue;
            }elseif($data['uid']!=$uid && !in_array($data['status'],array(0,1))){
                continue;
            }elseif($data['uid']==$uid && !in_array($data['status'],array(0,1,5))){
                continue;
            }
//            if(!isset($data['uid']) || !in_array($data['status'],array(0,1))){
//                continue;
//            }
            if($item[0]==2 && $data['photo']['size']==0 ){
                continue;
            }
            $data['add_time'] = Common::show_time($val);
            $data['last_time'] = (string)$val;
            $data['feed_type'] = (int)$item[0];
            if($item[0] == 5 && $item[2]){
                $data['user'] = $userModel->getUserData($item[2],$uid);
            }else{
                $data['user'] = $userModel->getUserData($data['uid'],$uid);
                $data['is_like'] = $likeModel->hasData($item[0],$item[1],$uid);
                $data['is_collect'] = $collectModel->hasData($item[0],$item[1],$uid);
                $data['like_list'] = $likeModel->likeList($item[1],$item[0],1,3,$uid);
                $data['comment_list'] = $this->getRedisCommentList($uid,$item[0],$item[1]);
                //如果redis里没有评论，就重新获取存入redis
                if(!$data['comment_list']){
                    $commentList = $this->getCommentListById($item[0],$item[1]);
                    $type_code = $this->type_code_arr[$item[0]];
                    $this->addRedisComment($type_code,$item[1],$commentList);
                    $data['comment_list'] = $this->getRedisCommentList($uid,$item[0],$item[1]);
                }
                $data['comment_list'] = $data['comment_list']?$data['comment_list']:array();
                if(in_array($item[0],array(1,4,9,10))){
                    $messageModel = new MessageModel();
                    $reward_list = $messageModel->getRewardList($item[0],$item[1],1,11);
                    $data['reward_list'] = $reward_list['list'];
                }
                $data['reward_list'] = isset($data['reward_list'])&&$data['reward_list']?$data['reward_list']:array();
            }
            $feedList[] = $data;
        }
        return $feedList;
    }
    //更新商家活动参与人数
    public function updatePartakeNum($tid,$num=1){
        $stmt = $this->db->prepare("update event set partake_num = partake_num + $num where id=:tid ");
        $array = array(
            ':tid'=>$tid
        );
        $stmt->execute($array);
        $rowCount = $stmt->rowCount();
        return $rowCount;
    }

    //验证用户是否参与某个商家商家活动
    public function modifyIsPartake($uid,$id){
        $stmt = $this->db->prepare("SELECT COUNT(id) AS num FROM event_partake_info WHERE uid =:uid AND f_id = :id AND status < 2");
        $stmt->bindValue(':uid',$uid, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    //验证用户参与某个商家商家活动的手机号码
    public function modifyMobile($mobile,$oid){
        $stmt = $this->db->prepare("SELECT COUNT(id) AS num FROM event_partake_info WHERE content =:mobile AND oid = :oid AND status < 2");
        $stmt->bindValue(':content', $mobile, PDO::PARAM_STR);
        $stmt->bindValue(':oid', $oid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    public function modifyMobileNew($id,$oid,$content){
        $stmt = $this->db->prepare("SELECT COUNT(*) AS num  FROM event_partake WHERE p_info_id IN( SELECT id FROM event_partake_info WHERE f_id = :id AND STATUS < 2) AND oid = :oid AND content = :content AND STATUS < 2");
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
        $stmt->bindValue(':oid', $oid, PDO::PARAM_INT);
        $stmt->bindValue(':content', $content, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    //删除用户参与商家活动信息
    public function delPartakeEvent($id){
        $sql = "update event_partake_info set status = 4, update_time = :update_time where eid = :eid";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':eid' => $id,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $rowCount = $stmt->rowCount();
        //$this->updatePartakeNum($id,-$rowCount);
        $sql_event = "update event set partake_num = 0, update_time = :update_time where id = :id";
        $stmt_event = $this->db->prepare($sql_event);
        $array_event = array(
            ':id' => $id,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt_event->execute($array_event);
        return $rowCount;
    }
    //获取app的关注列表
    private function getAppList($uid){
        //获取我关注的人的动态
        $followModel = new FollowModel();
        $attList = $followModel->getAttListByReids($uid,0);
        if($attList['size'] >= 1000){
            $every_feed_num = 30;
            $day = 7;
        }elseif($attList['size'] >= 500 && $attList['size'] < 1000){
            $every_feed_num = 40;
            $day = 15;
        }else{
            $every_feed_num = 50;
            $day = 30;
        }
        $pipeline = $this->redis->multi();
        foreach($attList['list'] as $key=>$val){
            $rKey = $this->getAppFeedKey($key);
            $pipeline->zRevRangeByScore($rKey,time(),time()-$day*24*3600,array('withscores' => TRUE, 'limit' => array(0, $every_feed_num)));
        }
        $rKey = $this->getAppFeedKey($uid);
        $pipeline->zRevRange($rKey,0,$every_feed_num,true);
        $feeds = $pipeline->exec();
        $feedList = array();
        if($feeds){
            foreach($feeds as $val){
                if($val){
                    $feedList = $feedList+$val;
                }
            }
        }
        arsort($feedList);
        return array_slice($feedList,0,$this->max_feedList_num,true);
    }

    private function getList($uid,$type_val=0,$group_type=0){
        //获取我关注的人的动态
        $followModel = new FollowModel();
        $attList = $followModel->getAttListByReids($uid,$group_type);
        if($attList['size'] >= 1000){
            $every_feed_num = 30;
            $day =7;
        }elseif($attList['size'] >= 500 && $attList['size'] < 1000){
            $every_feed_num = 40;
            $day =15;
        }else{
            $every_feed_num = 50;
            $day =30;
        }
        $pipeline = $this->redis->multi();
        foreach($attList['list'] as $key=>$val){
            $k_uid = $group_type ? $val['f_uid'] : $key;
            if($type_val == ''){
                $rKey = $this->getFeedKey($k_uid);
            }else{
                $rKey = $this->getTypeKey($k_uid,$type_val);
            }
            $pipeline->zRevRangeByScore($rKey,time(),time()-$day*24*3600,array('withscores' => TRUE, 'limit' => array(0, $every_feed_num)));
        }
        if(!$group_type){
            //获取自己的动态
            if($type_val == ''){
                $rKey = $this->getFeedKey($uid);
            }else{
                $rKey = $this->getTypeKey($uid,$type_val);
            }
            $pipeline->zRevRange($rKey,0,$every_feed_num,true);
        }
        //获取我加入的驿站的动态-当是全部动态时才会有，关注分组筛选时没有
        /*if(!$group_type){
            //获取我加入的驿站
            $stageModel = new StageModel();
            $stageList = $stageModel->getStageList($uid,3,0);
            foreach($stageList as $val){
                if($type_val == ''){
                    $sKey = $this->getStageFeedKey($val['sid']);
                }else{
                    $sKey = $this->getStageTypeKey($val['sid'],$type_val);
                }
                $pipeline->zRevRange($sKey,0,$every_feed_num,true);
            }
        }*/
        $feeds = $pipeline->exec();
        $feedList = array();
        if($feeds){
            foreach($feeds as $val){
                if($val){
                    $feedList = $feedList+$val;
                }
            }
        }
        arsort($feedList);
        return array_slice($feedList,0,$this->max_feedList_num,true);
    }

    /**
     * 获取新动态的条数
     * @param $uid
     * @param $last_time
     * @return mixed
     */
    public function getLastNum($uid,$last_time){
        $feedsKey = $this->getFeedsKey($uid);
        $feedKey = $this->getFeedKey($uid);
        $this->clearFeeds($uid);
        $this->getAttList($uid,0,1);
        $feeds_num = $this->redis->zCount($feedsKey,$last_time+1,time());
        $feed_num = $this->redis->zCount($feedKey,$last_time+1,time());
        return $feeds_num - $feed_num;
    }

    /**
     * 获取APP新动态的条数
     * @param $uid
     * @param $last_time
     * @return mixed
     */
    public function getLastAppNum($uid,$last_time){
        $appFeedsKey = $this->getAppFeedsKey($uid);
        $appFeedKey = $this->getAppFeedKey($uid);
        $this->clearFeeds($uid);
        $this->getAttAppList($uid,0,1);
        $app_feeds_num = $this->redis->zCount($appFeedsKey,$last_time+1,time());
        $app_feed_num = $this->redis->zCount($appFeedKey,$last_time+1,time());
        return $app_feeds_num - $app_feed_num;
    }
    public function getLastAppNumNew($uid,$last_time){
        $feedsKey = $this->getFeedsKey($uid);
        $feedKey = $this->getFeedKey($uid);
        $this->clearFeeds($uid);
        $this->getAttList($uid,0,1);
        $feeds_num = $this->redis->zCount($feedsKey,$last_time+1,time());
        $feed_num = $this->redis->zCount($feedKey,$last_time+1,time());
        return $feeds_num - $feed_num;
    }
    /**
     * 获取好友的动态
     * @param $uid
     * @param $start
     * @param $length
     * @param int $type
     * @return array
     */
    public function getAttList($uid,$last,$length,$type=0,$group_id=0){
        if($last == 0){
            $last = time()+10;
        }
        $type_val = '';
        if($type != 0){
            $type_val = array_search($type,$this->type_arr);
        }
        if($group_id){
            $feedsKey = $this->getGroupFeedsKey($group_id,$type_val);
        }else{
            $feedsKey = $this->getFeedsKey($uid,$type_val);
        }
        if(!$this->redis->exists($feedsKey)){
            $feedList = $this->getList($uid,$type_val,$group_id);
            if($feedList){
                $pipeline = $this->redis->multi();
                foreach($feedList as $key=>$val){
                    $pipeline->zAdd($feedsKey,$val,$key);
                }
                $pipeline->exec();
                $this->redis->expire($feedsKey,120);
            }
            $size = count($feedList);
        }else{
            $size = $this->redis->zSize($feedsKey);
        }
        $feedList = $this->redis->zRevRangeByScore($feedsKey,$last-1,0,array('withscores' => TRUE, 'limit' => array(0, $length)));
        return array(
            'list'=>$feedList,
            'size'=>$size
        );
    }

    /**
     * 获取好友app的动态
     * @param $uid
     * @param $start
     * @param $length
     * @param int $type
     * @return array
     */
    public function getAttAppList($uid,$last,$length){
        if($last == 0){
            $last = time()+10;
        }
        $appFeedsKey = $this->getAppFeedsKey($uid);
        if(!$this->redis->exists($appFeedsKey)){
            $feedList = $this->getAppList($uid);
            if($feedList){
                $pipeline = $this->redis->multi();
                foreach($feedList as $key=>$val){
                    $pipeline->zAdd($appFeedsKey,$val,$key);
                }
                $pipeline->exec();
                $this->redis->expire($appFeedsKey,120);
            }
            $size = count($feedList);
        }else{
            $size = $this->redis->zSize($appFeedsKey);
        }
        $feedList = $this->redis->zRevRangeByScore($appFeedsKey,$last-1,0,array('withscores' => TRUE, 'limit' => array(0, $length)));
        return array(
            'list'=>$feedList,
            'size'=>$size
        );
    }

    /**
     * 获取单个用户的动态
     * @param $uid
     * @param $start
     * @param $length
     * @return array
     */
    public function getUserList($uid,$last,$length){
        if($last == 0){
            $last = time();
        }
        $feedKey = $this->getFeedKey($uid);
        $size = $this->redis->zSize($feedKey);
        $list = $this->redis->zRevRangeByScore($feedKey,$last-1,0,array('withscores' => TRUE, 'limit' => array(0, $length-1)));
        return array('size'=>$size,'list'=>$list);
    }

    /**
     * 获取单个用户的APP动态
     * @param $uid
     * @param $last
     * @param $length
     * @return array
     */
    public function getUserAppList($uid,$last,$length){
        if($last == 0){
            $last = time();
        }
        $appFeedKey = $this->getAppFeedKey($uid);
        $size = $this->redis->zSize($appFeedKey);
        $list = $this->redis->zRevRangeByScore($appFeedKey,$last-1,0,array('withscores' => TRUE, 'limit' => array(0, $length-1)));
        return array('size'=>$size,'list'=>$list);
    }

    /**
     * 获得评论对象对应的表
     */
    private  function getTableByType($type){
        if(!in_array($type,$this->comment_type_arr)){
            return false;
        }
        switch($type){
            case 1:
                $table = 'mood';
                break;
            case 2:
                $table = 'album_photo';
                break;
            case 3:
                $table = 'blog';
                break;
            case 4:
                $table = 'topic';
                break;
            case 9:
                $table = 'share';
                break;
            case 10:
                $table = 'event';
                break;
            case 11:
                $table = 'stage_message';
                break;
            case 12:
                $table = 'stage_goods';
                break;
        }
        return $table;
    }

    /**
     * 查询评论对应的对象是否存在
     */
    public function getObjInfoById($type,$obj_id){
        $table = $this->getTableByType($type);
        $field = '';
        if(in_array($type,array(4,10,11))){//当为帖子时要查询所属驿站的sid
            $field = ',sid';
        }
        $stmt = $this->db->prepare("select id, uid $field from $table where id = :id");
        $array = array(
            ':id' => $obj_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 插入评论信息
     * @param $type  评论类型:1:心境 2.相册照片 3:日志 4:帖子 9:分享 10:商家活动
     * @param $obj_id 评论对象ID
     * @param $uid  帖子评论人或回复人id
     * @param $reply_uid  评论或回复的用户id
     * @param $reply_id 回复的评论ID
     */
    public function addComment($type,$obj_id,$uid,$content,$reply_uid,$reply_id,$is_share,$share_type,$share_id,$shared_id=0,$share_uid){
        $obj_info = $this->getObjInfoById($type,$obj_id);
        if(!$obj_info){
            return -1;
        }

        list($content,$atArray) = Common::atUser($uid,$content);
        $stmt = $this->db->prepare("insert into comment (type, obj_id, uid, content, reply_uid, reply_id)
        select :type, :obj_id, :uid, :content, :reply_uid, :reply_id from dual
        where not exists (select * from comment where uid = :uid and UNIX_TIMESTAMP() - 5 < UNIX_TIMESTAMP(add_time))");
        $array = array(
            ':type' => $type,
            ':obj_id' => $obj_id,
            ':uid' => $uid,
            ':content' => $content,
            ':reply_uid' => $reply_uid,
            ':reply_id' => $reply_id
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        if($id < 1){
            Common::echoAjaxJson(600,"发的太快，休息一下吧");
        }
        $this->updateCommentNum($type,$obj_id,$uid);//更新评论数
        if(!$reply_id){//评论
            if($uid != $obj_info['uid']){
                $this->addCommentPush($obj_info['uid'],$id,0);
            }
        }else{//回复
            $comment_info = $this->getCommentInfoById($reply_id);
            if($obj_info['uid'] != $uid && $obj_info['uid'] != $reply_uid && $obj_info['uid'] != $comment_info['uid']){
                $this->addCommentPush($obj_info['uid'],$id,0);
            }
            if($comment_info['uid'] != $uid && $comment_info['uid'] != $reply_uid){
                $this->addCommentPush($comment_info['uid'],$id,1);
            }
            if($uid != $reply_uid){
                $push_type = 2;
                if($comment_info['uid'] == $reply_uid){
                    $push_type = 1;
                }
                $this->addCommentPush($reply_uid,$id,$push_type);
            }
        }
        if($atArray){
            $this->mentionUser(0,$uid,$id,$atArray);
        }
        $scoreModel = new ScoreModel();
        $scoreModel->add($uid,0,'comment',$id);
        if($is_share){//是否评论对象分享到书房
            $moodModel = new MoodModel();
            $shared_id = $moodModel->share($uid,$content,$share_type,$share_id,$shared_id,$share_uid);
        }
        $commentList = $this->getCommentListById($type,$obj_id);//保存该对象前五条评论数据到redis
        $newList = array();
        if($commentList){
            $userModel = new UserModel();
            foreach($commentList as $val){
                $commentInfo = $this->getCommentInfoById($val['id']);
                $commentInfo['add_time'] = Common::show_time($commentInfo['add_time']);
                $commentInfo['content'] = Common::linkReplace($commentInfo['content']);
                $commentInfo['content'] = Common::showEmoticon($commentInfo['content'],1);
                $commentInfo['user'] = $userModel->getUserData($commentInfo['uid']);
                if($commentInfo['reply_id']){
                    $commentInfo['reply_user'] = $userModel->getUserData($commentInfo['reply_uid']);
                }
                $commentInfo['is_delete'] = $this->isDeleteComment($uid,$type,$obj_id,$commentInfo);
                $newList[] = $commentInfo;
            }
        }
        $type_code = $this->type_code_arr[$type];
        if(!$type_code){
            return false;
        }
        $this->addRedisComment($type_code,$obj_id,$commentList);
        //帖子，服务信息评论一次浏览数加20
        if($type==4||$type==10){
            $typeName = $type==4 ? 'topic' : 'event';
            $visitModel = new VisitModel();
            $visitModel->addVisitNum($typeName,$obj_id,mt_rand(20,200));//添加浏览数
        }
        return array('list'=>$newList,'shared_id'=>$shared_id);
    }

    //插入@人数据
    public function mentionUser($type,$uid,$obj_id,$at_arr){
        foreach($at_arr as $val){
            if($uid != $val){
                $stmt = $this->db->prepare("insert into mention (uid, m_uid, type, obj_id, add_time)
                values (:uid, :m_uid, :type, :obj_id, :add_time)");
                $array = array(
                    ':uid' => $uid,
                    ':m_uid' => $val,
                    ':type' => $type,
                    ':obj_id' => $obj_id,
                    ':add_time' => date('Y-m-d H:i:s')
                );
                $stmt->execute($array);
            }
        }
        return 1;
    }

    //插入评论推送消息
    public function addCommentPush($uid,$comment_id,$type){
        $stmt = $this->db->prepare("insert into comment_push (uid, comment_id, type, add_time)
        values (:uid, :comment_id, :type, :add_time)");
        $array = array(
            ':uid' => $uid,
            ':comment_id' => $comment_id,
            ':type' => $type,
            ':add_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        if($id < 1){
            return 0;
        }
        return 1;
    }

    /**
     * 添加时更新对应对象表中的评论数和最新评论时间
     */
    public function updateCommentNum($type,$obj_id,$uid){
        $table = $this->getTableByType($type);
        $field = '';
        if($type == 4){
            $field = ',last_comment_uid = :last_comment_uid, last_comment_time = :last_comment_time';
        }elseif($type == 3){
            $field = ',last_comment_time = :last_comment_time';
        }
        $comment_num = $this->getCommentNumByObjId($type,$obj_id);
        $comment_time = null;
        if($comment_num>0){
            $comment_time = date('Y-m-d H:i:s');
        }
        $stmt = $this->db->prepare("update $table set comment_num = :comment_num $field where id = :id");
        if($type == 4){
            $array = array(
                ':id' => $obj_id,
                ':comment_num' => $comment_num,
                ':last_comment_uid' => $uid,
                ':last_comment_time' => $comment_time
            );
        }elseif($type == 3){
            $array = array(
                ':id' => $obj_id,
                ':comment_num' => $comment_num,
                ':last_comment_time' => $comment_time
            );
        }else{
            $array = array(
                ':id' => $obj_id,
                ':comment_num' => $comment_num,
            );
        }
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        if($type == 4){
            $userModel = new UserModel();
            $userModel->clearUserData($uid);//清除缓存里用户信息(用户回复的帖子数量)
        }
        return 1;
    }

    //查询该对象下的评论数
    public function getCommentNumByObjId($type,$obj_id){
        $stmt = $this->db->prepare("select count(id) as num from comment where type = :type and obj_id = :obj_id and status<2");
        $stmt->bindValue(':obj_id', $obj_id, PDO::PARAM_INT);
        $stmt->bindValue(':type', $type, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //查询该对象下的所有评论
    public function getCommentByObjId($type,$obj_id){
        $stmt = $this->db->prepare("select id from comment where type = :type and obj_id = :obj_id");
        $stmt->bindValue(':obj_id', $obj_id, PDO::PARAM_INT);
        $stmt->bindValue(':type', $type, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 获取对应对象的评论列表
     * @param $type  评论类型
     * @param $obj_id  评论对象id
     */
    public function getCommentList($uid,$type,$obj_id,$page,$size,$flag=0){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select id, type, obj_id, content, uid, reply_id, add_time from comment
        where type = :type and obj_id = :obj_id and reply_id = 0 and status<2 order by add_time desc limit :start,:size");
        $stmt->bindValue(':obj_id', $obj_id, PDO::PARAM_INT);
        $stmt->bindValue(':type', $type, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = $this->getCommentNum($type,$obj_id,1);
        if($result){
            $userModel = new UserModel();
            foreach($result as $key=>$val){
                $result[$key]['user'] = $userModel->getUserData($val['uid'],$uid);
                $result[$key]['add_time'] = Common::show_time($val['add_time']);
                $result[$key]['content'] = Common::linkReplace($result[$key]['content']);
                if($flag==1){
                    $result[$key]['content'] = Common::showEmoticon($result[$key]['content'],0);
                }else{
                    $result[$key]['content'] = Common::showEmoticon($result[$key]['content'],1);
                }
                $result[$key]['reply_list'] = $this->getReplyList($uid,$type,$obj_id,$val['id'],$flag);
                $result[$key]['is_delete'] = $this->isDeleteComment($uid,$type,$obj_id,$val);
            }
        }
        return array(
            'list' => $result,
            'size' => $count
        );
    }
    public function getCommentListByLastTime($uid,$type,$obj_id,$last_time,$size,$flag=0){
        if($last_time){
            $stmt = $this->db->prepare("select id, type, obj_id, content, uid, reply_id, add_time from comment
        where type = :type and obj_id = :obj_id and reply_id = 0 and status<2 and add_time < :last_time order by add_time desc limit :size");
            $stmt->bindValue(':last_time', $last_time, PDO::PARAM_STR);
        }else{
            $stmt = $this->db->prepare("select id, type, obj_id, content, uid, reply_id, add_time from comment
        where type = :type and obj_id = :obj_id and reply_id = 0 and status<2 order by add_time desc limit :size");
        }
        $stmt->bindValue(':obj_id', $obj_id, PDO::PARAM_INT);
        $stmt->bindValue(':type', $type, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = $this->getCommentNum($type,$obj_id,1);
        if($result){
            $userModel = new UserModel();
            foreach($result as $key=>$val){
                $result[$key]['user'] = $userModel->getUserData($val['uid'],$uid);
                $result[$key]['time'] = $val['add_time'];
                $result[$key]['add_time'] = Common::show_time($val['add_time']);
                $result[$key]['content'] = Common::linkReplace($result[$key]['content']);
                if($flag==1){
                    $result[$key]['content'] = Common::showEmoticon($result[$key]['content'],0);
                }else{
                    $result[$key]['content'] = Common::showEmoticon($result[$key]['content'],1);
                }
                $result[$key]['reply_list'] = $this->getReplyList($uid,$type,$obj_id,$val['id'],$flag);
                $result[$key]['is_delete'] = $this->isDeleteComment($uid,$type,$obj_id,$val);
            }
        }
        return array(
            'list' => $result,
            'size' => $count
        );
    }
    //获取评论或回复的删除权限
    public function isDeleteComment($uid,$type,$obj_id,$comment_info){
        if(!in_array($type,$this->comment_type_arr)){
            return false;
        }
        $obj_info = $this->getObjInfoById($type,$obj_id);
        if($obj_info){
            if($type == 4 || $type == 11){
                $stageModel = new StageModel();
                $join_info = $stageModel->isJoinStage($obj_info['sid'],$uid);
                if($uid == $comment_info['uid'] || $uid == $obj_info['uid'] || in_array(isset($join_info['role'])?$join_info['role']:0,array(1,2))){
                    return 1;
                }
            }else{
                if($uid == $comment_info['uid'] || $uid == $obj_info['uid']){
                    return 1;
                }
            }
        }
        return 0;
    }

    /**
     * 查询次级评论（回复）列表
     */
    public function getReplyList($uid,$type,$obj_id,$reply_id,$flag){
        $stmt = $this->db->prepare("select id, type, obj_id, content, uid, reply_uid, reply_id, add_time from comment
        where type = :type and obj_id = :obj_id and reply_id = :reply_id and status<2 order by add_time,id");
        $array = array(
            ':type' => $type,
            ':obj_id' => $obj_id,
            ':reply_id' => $reply_id
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $userModel = new UserModel();
            foreach($result as $key=>$val){
                $result[$key]['add_time'] = Common::show_time($val['add_time']);
                $result[$key]['user'] = $userModel->getUserData($val['uid'],$uid);
                $result[$key]['reply_user'] = $userModel->getUserData($val['reply_uid'],$uid);
                $result[$key]['content'] = Common::linkReplace($result[$key]['content']);
                if($flag==1){
                    $result[$key]['content'] = Common::showEmoticon($result[$key]['content'],0);
                }else{
                    $result[$key]['content'] = Common::showEmoticon($result[$key]['content'],1);
                }

                $result[$key]['is_delete'] = $this->isDeleteComment($uid,$type,$obj_id,$val);
            }
        }
        return $result;
    }

    /**
     * 获取对应对象的评论总数
     * $reply_id  0是代表主次评总数  1代表主评总数
     */
    public function getCommentNum($type,$obj_id,$reply_id=0){
        $condition = $reply_id?' and reply_id=0':'';
        $stmt = $this->db->prepare("select count(id) as num from comment where obj_id = :obj_id and type = :type and status<2 $condition");
        $array = array(
            ':obj_id' => $obj_id,
            ':type' => $type
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /**
     * 删除对应对象的评论
     */
    public function delComment($id,$uid){
        $comment_info = $this->getCommentInfoById($id);
        if(!$comment_info){
            return -1;
        }
        $obj_id = $comment_info['obj_id'];
        $type = $comment_info['type'];
        if($this->isDeleteComment($uid,$type,$obj_id,$comment_info) != 1){
            return -2;
        }
        $stmt = $this->db->prepare("update comment set status = 3 , update_time = :update_time where id = :id");
        $array = array(
            ':id' => $id,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        $messageModel = new MessageModel();
        $messageModel->updateMention(0,$id,0);//删除评论中的提到我的
        if(!$comment_info['reply_id']){
            $this->delReply($comment_info['id']);//删除评论时要删除对应的回复数据
        }
        $userModel = new UserModel();
        $this->updateCommentNum($type,$obj_id,$uid);//更新对应对象评论数目
        if($type == 4){
            $userModel->clearUserData($uid);//清除缓存里用户信息(用户回复的帖子数量)
        }
        //获取最新组装的评论
        $rs = $this->getRedisCommentList($uid,$type,$obj_id);
        if(count($rs)<5){
            $type_code = $this->type_code_arr[$type];
            //如果redis里评论条数不租5条，就重新获取存入redis
            $feedModel = new FeedModel();
            $commentList = $feedModel->getCommentListById($type,$obj_id);
            $feedModel->addRedisComment($type_code,$obj_id,$commentList);
            $rs = $feedModel->getRedisCommentList($uid,$type,$obj_id);
        }
        $count = $this->getCommentNum($type,$obj_id);
        $commentList = array('list'=>$rs,'size'=>$count);
        return $commentList;
    }

    /**
     * 根据评论删除回复
     */
    public function delReply($reply_id){
        $stmt = $this->db->prepare("update comment set status = 4, update_time = :update_time where reply_id = :reply_id");
        $array = array(
            ':reply_id' => $reply_id,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        $replyList = $this->getCommentByReplyId($reply_id,4);
        if($replyList){
            $messageModel = new MessageModel();
            foreach($replyList as $val){
                $id_arr[] = $val['id'];
            }
            $messageModel->updateMention(0,isset($id_arr) && $id_arr ? $id_arr : 0,0);//删除回复中的提到我的
        }
        return $count;
    }
    
    //根据reply_id查询评论信息
    public function getCommentByReplyId($reply_id,$status=2){
        $whereClause = $status == 4 ? 'status <= :status' : ' status < :status';
        $stmt = $this->db->prepare("select id from comment where reply_id = :reply_id and $whereClause");
        $array = array(
            ':reply_id' => $reply_id,
            ':status' => $status
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 根据评论id查询对应对象id,当是消息中心的心境时at不加链接
     */
    public function getCommentInfoById($id){
        $stmt = $this->db->prepare("select id,type,obj_id,content,uid,reply_uid,reply_id,add_time from comment where id = :id and status<2");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result){
            $result['content'] = Common::showEmoticon($result['content'],1);
        }
        return $result;
    }

    //根据回复的评论id查询该评论下的回复条数
    public function getCommentDialogNum($reply_id){
        $stmt = $this->db->prepare("select count(id) as num from comment where reply_id = :reply_id and status < 2");
        $stmt->bindValue(':reply_id', $reply_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //根据回复的评论id查询该评论下的回复
    public function getReplyListByReplyId($reply_id,$page,$size){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select id,type,obj_id,content,uid,reply_uid,reply_id,add_time from
        comment where reply_id = :reply_id and status < 2 order by add_time desc,id limit :start,:size");
        $stmt->bindValue(':reply_id', $reply_id, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $userModel = new UserModel();
            foreach($result as $key=>$val){
                $result[$key]['add_time'] = Common::show_time($result[$key]['add_time']);
                $result[$key]['content'] = Common::showEmoticon($result[$key]['content'],1);
                $result[$key]['user'] = $userModel->getUserData($val['uid']);
                $result[$key]['reply_user'] = $userModel->getUserData($val['reply_uid']);
            }
        }
        return $result;
    }

    public function getFeedByItem($item,$val,$uid,$flag,$version,$token){
        $data = array();
        switch($item[0]){
            case 1:
                $moodModel = new MoodModel();
                $data = $moodModel->get($item[1],$flag);
                $comment_num  = $moodModel->moodCommentNum($data['uid'],1,$item[1]);
                $like_num  = $moodModel->moodLikeNum($data['uid'],1,$item[1]);
                if($comment_num>=5&&$like_num>=8){
                    $data['is_hot'] = 1;
                }else{
                    $data['is_hot'] = 0;
                }
                break;
            case 2:
                $albumModel = new AlbumModel();
                $data = $albumModel->getAlbumForFeed($item[1]);
                $data['photo'] = $albumModel->getPhotoByAlbumIdForFeed($item[1],date("Y-m-d H:i:s",$val),$uid);
                break;
            case 3:
                $blogModel = new BlogModel();
                $data = $blogModel->getBasicBlogById($item[1]);
                if(isset($data['summary'])){
                    $data['summary'] = Common::deleteHtml($data['summary']);
                    $len = 105;
                    $data['is_read'] = mb_strlen($data['summary'],'UTF-8')>$len ? 1 : 0;
                    $data['summary'] = Common::msubstr($data['summary'],0,$len,'UTF-8');
                    $data['url'] = I_DOMAIN.'/b/'.$item[1].'?token='.$token.'&version='.$version;
                }
                break;
            case 4:
                $topicModel = new TopicModel();
                $data = $topicModel->getInfoForFeed($item[1]);
                if(isset($data['summary'])){
                    $data['summary'] = Common::deleteHtml($data['summary']);
                    $len = 105;
                    $data['is_read'] = mb_strlen($data['summary'],'UTF-8')>$len ? 1 : 0;
                    $data['summary'] = htmlspecialchars_decode(Common::msubstr($data['summary'],0,$len,'UTF-8'));
                    $data['url'] = I_DOMAIN.'/t/'.$item[1].'?token='.$token.'&version='.$version;
                }
                break;
            case 5:
                $stageModel = new StageModel();
                $data = $stageModel->getBasicStageBySid($item[1]);
                $data['icon'] = $data['icon']?$data['icon']:'default_avatar.png';
                if(isset($data['intro'])){
                    $data['intro'] = Common::msubstr($data['intro'],0,20,'UTF-8');
                }
                break;
            case 6:
                $wishModel = new WishModel();
                $data = $wishModel->getWishById($item[1]);
                break;
            case 7:
                $memorialModel = new MemorialModel();
                $data = $memorialModel->getMemorialById($item[1]);
                $data['url'] = D_DOMAIN.'/memorial?token='.$_POST['token'];
                $data['d_url'] = D_DOMAIN.'/memorial/showMemorial?id='.$data['m_id'].'&token='.$token;
                break;
            case 8:
                $prayModel = new TempleModel();
                $data = $prayModel->getPrayWishById($item[1]);
                break;
            case 9:
                $moodModel = new MoodModel();
                $data = $moodModel->getShare($item[1],$uid,1,2,$version,$token);
                if(!$data['share_data']){
                    $data['share_data'] = (object)array();
                }
                break;
            case 10:
                $eventModel = new EventModel();
                $data = $eventModel->getFeedEventInfo($item[1]);
                $data['url'] = I_DOMAIN.'/e/'.$item[1].'?token='.$token.'&version='.$version;
                break;
            case 12:
                $stagegoodsModel = new StagegoodsModel();
                $data = $stagegoodsModel->getFeedGoodsInfo($item[1]);
                $data['url'] = I_DOMAIN.'/g/'.$item[1].'?token='.$token.'&version='.$version;
                break;
        }

        return $data;
    }

    //根据type,obj_id获取内容信息,当是消息中心的心境时at不加链接
    public function getDataByTypeAndId($type,$obj_id,$uid=0,$flag =0){
        $data = array();
        switch($type){
            case 1:
                $moodModel = new MoodModel();
                $data = $moodModel->get($obj_id,$flag);
                break;
            case 2:
                $albumModel = new AlbumModel();
                $data = $albumModel->getAlbumByPhotoId($obj_id);
                $data['photo'] = $albumModel->getPhotoById($obj_id);
                break;
            case 3:
                $blogModel = new BlogModel();
                $data = $blogModel->getBasicBlogById($obj_id, 2, 0);
                if(isset($data['summary'])){
                    $data['summary'] = Common::deleteHtml($data['summary']);
                    $len = 105;
                    $data['is_read'] = mb_strlen($data['summary'],'UTF-8')>$len ? 1 : 0;
                    $data['summary'] = Common::msubstr($data['summary'],0,$len,'UTF-8');
                }
                break;
            case 4:
                $topicModel = new TopicModel();
                $stageModel = new StageModel();
                $data = $topicModel->getBasicTopicById($obj_id);
                if($data){
                    $stageInfo = $stageModel->getBasicStageBySid($data['sid']);
                    $data['stage'] =$stageInfo?$stageInfo:(object)array();
                }
                if(isset($data['summary'])){
                    $data['summary'] = Common::deleteHtml($data['summary']);
                    $len = 105;
                    $data['is_read'] = mb_strlen($data['summary'],'UTF-8')>$len ? 1 : 0;
                    $data['summary'] = htmlspecialchars_decode(Common::msubstr($data['summary'],0,$len,'UTF-8'));

                }
                break;
            case 9:
                $moodModel = new MoodModel();
                $data = $moodModel->getShare($obj_id,$uid,0,2,'','');

                if(!$data['share_data']){
                    $data['share_data'] = (object)array();
                }
                break;
            case 10:
                $eventModel = new EventModel();
                $stageModel = new StageModel();
                $data = $eventModel->getEvent($obj_id);
                if($data){
                    $stage = $stageModel->getBasicStageBySid($data['sid']);
                    $data['show_start_time'] = Common::getEventStartTime($obj_id);
                    $data['stage']['sid'] = $stage['sid'];
                    $data['stage']['name'] = $stage['name'];
                    $data['stage']['intro'] = $stage['intro'];
                    $data['stage']['type'] = $stage['type'];
                    if(isset($stage['imgs'])){
                        $data['stage']['imgs'] = $stage['imgs'];
                    }else{
                        $data['stage']['imgs'] = array();
                    }

                    $data['stage']['icon'] = $stage['icon'];
                    $data['stage']['user_num'] = $stage['user_num'];
                    $data['stage']['topic_num'] = $stage['topic_num'];
                }
                break;
            case 12:
                $stagegoodsModel = new StagegoodsModel();
                $stageModel = new StageModel();
                $data = $stagegoodsModel->getInfo($obj_id);
                if($data){
                    $stage = $stageModel->getBasicStageBySid($data['sid']);
                    if(isset($stage['sid'])){
                        $data['stage']['sid'] = $stage['sid'];
                        $data['stage']['name'] = $stage['name'];
                        $data['stage']['intro'] = $stage['intro'];
                        $data['stage']['type'] = $stage['type'];
                        if(isset($stage['imgs'])){
                            $data['stage']['imgs'] = $stage['imgs'];
                        }else{
                            $data['stage']['imgs'] = array();
                        }

                        $data['stage']['icon'] = $stage['icon'];
                        $data['stage']['user_num'] = $stage['user_num'];
                        $data['stage']['topic_num'] = $stage['topic_num'];
                    }
                }
                break;
        }
        return $data;
    }

    //查询相册中照片评论数总和
    public function getAlbumCommentNum($album_id){
        $stmt = $this->db->prepare("select coalesce(sum(comment_num),0) as num from album_photo where status<2 and album_id = :album_id");
        $stmt->bindValue(':album_id', $album_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //查询用户最新一条评论
    public function getNewCommentByUid($uid){
        $stmt = $this->db->prepare("select id,content,add_time from comment where uid=:uid and status<2 order by add_time desc limit 1");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $mood = $stmt->fetch(PDO::FETCH_ASSOC);
        return $mood;
    }

    //根据id查询reply_id
    public function getReplyIdById($comment_id){
        $stmt = $this->db->prepare("select id,reply_id from comment where id = :comment_id");
        $stmt->bindValue(':comment_id', $comment_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    //从redis中获取评论
    public function getRedisCommentList($uid,$type,$obj_id){
        $type_code = $this->type_code_arr[$type];
        $commentKey = $this->getCommentTypeKey($type_code,$obj_id);
        $commentList = $this->redis->zRangeByScore($commentKey,0,5,array('withscores' => TRUE, 'limit' => array(0, 5)));//$this->redis->zRange($commentKey,0,5,true);
        $list = array();
        if($commentList){
            $userModel = new UserModel();
            foreach($commentList as $key=>$val){
                $commentInfo = $this->getCommentInfoById($key);
                if(!$commentInfo){
                    continue;
                }
                $commentInfo['add_time'] = Common::show_time($commentInfo['add_time']);
                //$commentInfo['content'] = Common::linkReplace($commentInfo['content'],1);
                $commentInfo['content'] = Common::showEmoticon($commentInfo['content'],1);
                $user = $userModel->getUserData($commentInfo['uid'],$uid);
                $commentInfo['user']['uid'] = $user['uid'];
                $commentInfo['user']['did'] = $user['did'];
                $commentInfo['user']['nick_name'] = $user['nick_name'];
                $commentInfo['user']['avatar'] = $user['avatar'];
                $commentInfo['user']['self'] = $user['self'];
                $commentInfo['user']['ico_type'] = $user['ico_type'];
                $commentInfo['user']['relation'] = $user['relation'];
                $commentInfo['user']['type'] = $user['type'];
                if($commentInfo['reply_id']){
                    $reply_user = $userModel->getUserData($commentInfo['reply_uid'],$uid);
                    $commentInfo['reply_user']['uid'] = $reply_user['uid'];
                    $commentInfo['reply_user']['did'] = $reply_user['did'];
                    $commentInfo['reply_user']['nick_name'] = $reply_user['nick_name'];
                    $commentInfo['reply_user']['avatar'] = $reply_user['avatar'];
                    $commentInfo['reply_user']['self'] = $reply_user['self'];
                    $commentInfo['reply_user']['ico_type'] = $reply_user['ico_type'];
                    $commentInfo['reply_user']['relation'] = $reply_user['relation'];
                }
                $commentInfo['is_delete'] = $this->isDeleteComment($uid,$type,$obj_id,$commentInfo);
                $list[] = $commentInfo;
            }
        }
        return $list;
    }

    //评论后组装最新5条评论数据
    public function getCommentListById($type,$obj_id){
        $new_list = array();//显示评论列表
        $comment_list = $this->getCommentListLimit($type,$obj_id);
        if($comment_list){
            foreach($comment_list as $val){
                if(count($new_list)>=5){
                    break;
                }
                $new_list[] = $val;
                $reply_list = $this->getReplyListLimit($type,$obj_id,$val['id']);
                if($reply_list){
                    foreach($reply_list as $val_r){
                        if(count($new_list)>=5){
                            break;
                        }
                        $new_list[] = $val_r;
                    }
                }
            }
        }
        return $new_list;
    }

    //动态主评
    public function getCommentListLimit($type,$obj_id){
        $stmt = $this->db->prepare("select id, type, obj_id, content, uid, reply_id, add_time from comment
        where type = :type and obj_id = :obj_id and reply_id = 0 and status<2 order by add_time desc limit 5");
        $stmt->bindValue(':obj_id', $obj_id, PDO::PARAM_INT);
        $stmt->bindValue(':type', $type, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //根据回复的评论id查询该评论下的回复
    public function getReplyListLimit($type,$obj_id,$reply_id){
        $stmt = $this->db->prepare("select id,type,obj_id,content,uid,reply_uid,reply_id,add_time
        from comment where type=:type and obj_id=:obj_id and reply_id = :reply_id and status < 2 order by add_time,id");
        $stmt->bindValue(':type', $type, PDO::PARAM_INT);
        $stmt->bindValue(':obj_id', $obj_id, PDO::PARAM_INT);
        $stmt->bindValue(':reply_id', $reply_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //删除评论redis
    public function delCommentRedis($type,$obj_id){
        $commentKey = $this->getCommentTypeKey($type,$obj_id);
        $this->redis->delete($commentKey);
        return true;
    }

    //获得评论key
    public function getCommentTypeKey($type,$obj_id){
        if(!$obj_id){
            return false;
        }
        return "c:".$type.":".$obj_id;
    }

    //存储心境、照片、日志、帖子、分享前5条评论存入redis
    public function addRedisComment($type,$obj_id,$commentList){
        $this->delCommentRedis($type,$obj_id);
        $commentKey = $this->getCommentTypeKey($type,$obj_id);
        foreach($commentList as $key=>$val){
            $this->redis->zAdd($commentKey,$key,$val['id']);
        }
        return true;
    }

    public function getRandGoodTopic($uid) {
        $stmt = $this->db->prepare("select id,tid,add_time,img,sort from topic_push where status=1 and tid in(select id from topic where status<2) order by sort limit 12");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $k = array_rand($result);
        $topicModel = new TopicModel();
        $stageModel = new StageModel();
        $userModel = new UserModel();
        $likeModel = new LikeModel();
        $collectModel = new CollectModel();
        $data = $topicModel->getBasicTopicById($result[$k]['tid']);
        $data['stage'] = $stageModel->getBasicStageBySid($data['sid']);
        if(isset($data['summary'])){
            $data['summary'] = Common::deleteHtml($data['summary']);
            $len = 40;
            $data['is_read'] = mb_strlen($data['summary'],'UTF-8') > $len ?1:0;
            $data['summary'] = Common::msubstr($data['summary'],0,$len,'UTF-8');
        }
        $data['add_time'] = Common::show_time($data['add_time']);
        $data['feed_type'] = 4;
        $data['user'] = $userModel->getUserData($data['uid']);
        if($uid){
            $data['is_like'] = $likeModel->hasData(4,$data['id'],$uid);
            $data['is_collect'] = $collectModel->hasData(4,$data['id'],$uid);
        }
        if(isset($data['is_like']) && $data['is_like']){
            $data['like_list'] = $likeModel->likeList($data['id'],4,1,4,$uid);
        }else{
            $data['like_list'] = $likeModel->likeList($data['id'],4,1,5,$uid);
        }
        return $data;
    }

    //消息中心查询评论信息
    public function getCommentInfo($id){
        $stmt = $this->db->prepare("select id,type,obj_id,content,uid,reply_uid,reply_id,add_time from comment
        where id = :id and status<2");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result){
            $result['content'] = Common::showEmoticon(Common::linkReplace($result['content']),1);
        }
        $moodModel = new MoodModel();
         if($result['type']==9){
             $shareInfo = $moodModel->getShare($result['obj_id'],0,0,2,'','');
             $result['mention_type'] = $shareInfo['type'];
         }else{
             $result['mention_type'] = 0;
         }
        return $result;
    }
    //根据type,obj_id获取内容信息,当是消息中心的心境时at不加链接
    public function getDataUidByTypeAndId($type,$obj_id){
        $table ='';
        switch($type){
            case 1:
                $table = 'mood';
                break;
            case 2:
                $table = 'album_photo';
                break;
            case 3:
                $table = 'blog';
                break;
            case 4:
                $table = 'topic';
                break;
            case 9:
                $table = 'share';
                break;
            case 10:
                $table = 'event';
                break;
            case 12:
                $table = 'stage_goods';
                break;
        }
        $stmt = $this->db->prepare("select uid from ".$table." where id = :id");
        $array = array(
            ':id' => $obj_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    //极光队列--喜欢
    public function initJpush($uid,$message){
        $key = "init:app:jpush";
        $this->redis->rPush($key,json_encode(array('uid'=>$uid,'message'=>$message)));
    }
    //极光队列--关注
    public function initJpushFollow($uid,$message){
        $key = "init:app:jpush_follow";
        $this->redis->rPush($key,json_encode(array('uid'=>$uid,'message'=>$message)));
    }
    //极光队列--关注
    public function initJpushReward($uid,$message){
        $key = "init:app:jpush_reward";
        $this->redis->rPush($key,json_encode(array('uid'=>$uid,'message'=>$message)));
    }
    //极光队列--评论
    public function initJpushComment($uid,$message){
        $key = "init:app:jpush_comment";
        $this->redis->rPush($key,json_encode(array('uid'=>$uid,'message'=>$message)));
    }
    //文化圈推荐用户心境
    public function getMoodList($uid,$last,$size){
        if($last==0){
            $fields = '';
        }else{
            $fields = 'and add_time <"'.date('Y-m-d H:i:s',$last).'" ';
        }
        $stmt = $this->db->prepare("select id,add_time,uid from mood where uid in (SELECT uid FROM user_follow WHERE STATUS = 0 AND uid!=:uid) and status < 2 and is_public =2 $fields order by add_time desc limit :size ");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        $moodModel = new MoodModel();
        $userModel = new UserModel();
        $stageModel = new StageModel();
        $likeModel = new LikeModel();
        $collectModel = new CollectModel();
        $feedModel = new FeedModel();
        if($result){
            foreach($result as $k=> $v){
                $list[$k] = $moodModel->get($v['id']);
                $list[$k]['add_time'] = Common::show_time($v['add_time']);
                $list[$k]['last_time'] = strtotime($v['add_time']);
                $list[$k]['feed_type'] = 1;
                $user= $userModel->getUserData($v['uid'],$uid);
                $angelInfo = $userModel->getInfo($user['uid']);
                $list[$k]['user']['angel_info'] = isset($angelInfo['info']) ? $angelInfo['info'] :'';
                $list[$k]['user']['uid'] = $user['uid'];
                $list[$k]['user']['did'] = $user['did'];
                $list[$k]['user']['nick_name'] = $user['nick_name'];
                $list[$k]['user']['avatar'] = $user['avatar'];
                $list[$k]['user']['self'] = $user['self'];
                $list[$k]['user']['ico_type'] = $user['ico_type'];
                $list[$k]['user']['relation'] = $user['relation'];
                $b_num = $stageModel->getSidByUid($user['uid']);
                if($b_num){
                    $list[$k]['user']['is_business']['num'] =1;
                    $list[$k]['user']['is_business']['sid'] =$b_num['sid'];
                }else{
                    $list[$k]['user']['is_business']['num'] =0;
                    $list[$k]['user']['is_business']['sid'] ='';
                }
                $list[$k]['user']['type'] = $user['type'];
                $list[$k]['is_like'] = $likeModel->hasData(1,$v['id'],$uid);
                $list[$k]['is_collect'] = $collectModel->hasData(1,$v['id'],$uid);
                $list[$k]['like_list'] = $likeModel->likeList($v['id'],1,1,10,$uid);
                $list[$k]['comment_list'] = $this->getRedisCommentList($uid,1,$v['id']);
                //如果redis里没有评论，就重新获取存入redis
                if(!$list[$k]['comment_list']){
                    $commentList = $this->getCommentListById(1,$v['id']);
                    $type_code = $this->type_code_arr[1];
                    $this->addRedisComment($type_code,$v['id'],$commentList);
                    $list[$k]['comment_list'] = $this->getRedisCommentList($uid,1,$v['id']);
                }
                $list[$k]['comment_num'] = $feedModel->getCommentNum(1,$v['id'],1);
                $list[$k]['comment_list'] = $list[$k]['comment_list']?$list[$k]['comment_list']:array();
                $messageModel = new MessageModel();
                $reward_list = $messageModel->getRewardList(1,$v['id'],1,11);
                $list[$k]['reward_list'] = $reward_list;
            }
        }
        return $list;
    }
    //文化圈推荐用户心境总数
    public function getMoodNum($uid){
        $stmt = $this->db->prepare("select count(*) as num from mood where uid in (SELECT uid FROM user_follow WHERE STATUS = 0 AND uid!=:uid) and status < 2 and is_public =2 ");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    /*
       * 获取动态列表详情
       */
    public function getCollectData($list,$type=0,$uid=0){
        $feedList = array();
        if(!$list){
            return $feedList;
        }
        $userModel = new UserModel();
        $feedModel = new FeedModel();
        $likeModel = new LikeModel();
        $collectModel = new CollectModel();
        $stageModel = new StageModel();
        foreach($list as $key=>$val){
            if($type == 0){
                $item = json_decode($key,true);
            }elseif(array_search($type,$feedModel->type_arr)){
                $item = json_decode($key,true);
                if(is_array($item)){ //加入驿站
                    $item = array($type,$item[0],$uid);
                }else{
                    $item = array($type,$key,$uid);
                }
            }else{
                Common::echoAjaxJson(2,'获取失败');
            }
            $data = $feedModel->getCollectDataByTypeAndId($item[0],$item[1],$uid, 0, 5);

            if(!isset($data['uid']) || (isset($data['is_public']) &&  $data['is_public'] == 0) || (isset($data['photo']['list']) &&  !$data['photo']['list']) ){
                continue;
            }
            $data['add_time'] = Common::show_time($val);
            $data['last_time'] = (string)$val;
            $data['feed_type'] = (int)$item[0];
            if($item[0] == 5 && $item[2]){
                $data['user'] = $userModel->getUserData($item[2],$uid);
            }else{
                $user= $userModel->getUserData($data['uid'],$uid);
                $angelInfo = $userModel->getInfo($data['uid']);
                $data['user']['angel_info'] = isset($angelInfo['info']) ? $angelInfo['info'] :'';
                $data['user']['uid'] = $user['uid'];
                $data['user']['did'] = $user['did'];
                $data['user']['nick_name'] = $user['nick_name'];
                $data['user']['avatar'] = $user['avatar'];
                $data['user']['self'] = $user['self'];
                $data['user']['ico_type'] = $user['ico_type'];
                $data['user']['relation'] = $user['relation'];
                $b_num = $stageModel->getSidByUid($user['uid']);
                if($b_num){
                    $data['user']['is_business']['num'] =1;
                    $data['user']['is_business']['sid'] =$b_num['sid'];
                }else{
                    $data['user']['is_business']['num'] =0;
                    $data['user']['is_business']['sid'] ='';
                }
                $data['user']['type'] = $user['type'];
                $data['is_like'] = $likeModel->hasData($item[0],$item[1],$uid);
                $data['is_collect'] = $collectModel->hasData($item[0],$item[1],$uid);
                $data['like_list'] = $likeModel->likeList($item[1],$item[0],1,10,$uid);
                $data['comment_list'] = $this->getRedisCommentList($uid,$item[0],$item[1]);
                //如果redis里没有评论，就重新获取存入redis
                if(!$data['comment_list']){
                    $commentList = $this->getCommentListById($item[0],$item[1]);
                    $type_code = $this->type_code_arr[$item[0]];
                    $this->addRedisComment($type_code,$item[1],$commentList);
                    $data['comment_list'] = $this->getRedisCommentList($uid,$item[0],$item[1]);
                }
                $data['comment_num'] = $feedModel->getCommentNum($item[0],$item[1]);
                $data['comment_list'] = $data['comment_list']?$data['comment_list']:array();
                if(in_array($item[0],array(1,4,9,10))){
                    $messageModel = new MessageModel();
                    $data['reward_list'] = $messageModel->getRewardList($item[0],$item[1],1,11);
                }
            }
            $feedList[] = $data;
        }
        return $feedList;
    }
    //根据type,obj_id获取内容信息,当是消息中心的心境时at不加链接
    public function getCollectDataByTypeAndId($type,$obj_id,$uid=0,$share_type=0, $status=2){
        $data = array();
        switch($type){
            case 1:
                $moodModel = new MoodModel();
                $data = $moodModel->get($obj_id, 0, $status);
                break;
            case 2:
                $albumModel = new AlbumModel();
                $data = $albumModel->getAlbumByPhotoId($obj_id, $status);
                $data['photo'] = $albumModel->getPhotoById($obj_id, 0, $status);
                break;
            case 3:
                $blogModel = new BlogModel();
                $data = $blogModel->getBasicBlogById($obj_id, 2, 0, $status);
                if(isset($data['summary'])){
                    $data['summary'] = Common::deleteHtml($data['summary']);
                    $len = 105;
                    $data['is_read'] = mb_strlen($data['summary'],'UTF-8')>$len ? 1 : 0;
                    $data['summary'] = Common::msubstr($data['summary'],0,$len,'UTF-8');
                }
                break;
            case 4:
                $topicModel = new TopicModel();
                $stageModel = new StageModel();
                $data = $topicModel->getBasicTopicById($obj_id, 0, $status);
                if($data){
                    $data['stage'] = $stageModel->getBasicStageBySid($data['sid']);
                }
                if(isset($data['summary'])){
                    $data['summary'] = Common::deleteHtml($data['summary']);
                    $len = 105;
                    $data['is_read'] = mb_strlen($data['summary'],'UTF-8')>$len ? 1 : 0;
                    $data['summary'] = Common::msubstr($data['summary'],0,$len,'UTF-8');
                }
                break;
            case 6:
                $wishModel = new WishModel();
                $data = $wishModel->getWishById($obj_id);
                if(isset($data['content'])){
                    $data['content'] = Common::deleteHtml($data['content']);
                }
                break;
            case 7:
                $memorialModel = new MemorialModel();
                $data = $memorialModel->getMemorialById($obj_id);
                if(isset($data['content'])){
                    $data['content'] = Common::deleteHtml($data['content']);
                    $data['intro'] =  Common::deleteHtml($data['intro']);
                }
                break;
            case 8:
                $prayModel = new TempleModel();
                $data = $prayModel->getPrayWishById($obj_id);
                if(isset($data['content'])){
                    $data['content'] = Common::deleteHtml($data['content']);
                }
                break;
            case 9:
                $moodModel = new MoodModel();
                $data = $moodModel->getShare($obj_id,$uid,$share_type,$status);
                break;
            case 10:
                $eventModel = new EventModel();
                $stageModel = new StageModel();
                $data = $eventModel->getEvent($obj_id,$uid,$status);
                if($data){
                    $data['stage'] = $stageModel->getBasicStageBySid($data['sid']);
                }
                if(isset($data['summary'])){
                    $data['summary'] = Common::msubstr(Common::deleteHtml($data['summary']),0,60,'UTF-8');
                }
                break;
            case 11:
                $stageMessageModel = new StageMessageModel();
                $data = $stageMessageModel->getStageMessageById($obj_id);
                break;
            case 12:
                $stagegoods = new StagegoodsModel();
                $stageModel = new StageModel();
                $data = $stagegoods->getFeedGoodsInfo($obj_id);
                if($data){
                    $data['stage'] = $stageModel->getBasicStageBySid($data['sid']);
                }
                break;
        }
        return $data;
    }
}