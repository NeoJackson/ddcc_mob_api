<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 15-6-11
 * Time: 下午5:44
 */
class TalkModel {
    private $db;
    private $redis;
    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }
    /*
    * 添加心境话题
    * @mid 心境ID
    * @uid 用户ID
    * @$content 心境内容
    */
    public function add($mid,$uid,$content){
        if(!$mid || !$content) {
            return false;
        }
        $array = array();
        preg_match_all("/\#([^\#|^@]+)\#/",$content,$ids);
        $arrTalk = array_unique($ids[1]);
        if(count($arrTalk)){
            foreach($arrTalk as $val){
                if(!trim($val)){
                    continue;
                }
                $talkInfo = $this->getTalkInfo($val,5);
                if(!$talkInfo['id']){
                    $talkInfo['id'] = $this->talkInsert($val,$uid);
                    if($talkInfo['id']){
                        $this->addTalkViewTime($talkInfo['id']);//把发布时间时间储存到redis，用于浏览数基数处理
                    }
                }
                $obj_id = $this->talkDetailInsert($talkInfo['id'],$mid,$uid);
                $val = (string)$val;
                if(isset($obj_id) && $obj_id){
                    $array[$val] = $obj_id;
                }
            }
        }
        return $array;
    }

    /*
     * 获取话题基本信息
     * @keyword 话题关键字
     */
    public function getTalkInfo($keyword,$status = 2){

        $stmt = $this->db->prepare("select id,`keyword`,`lead`,`set_up`,status from talk where keyword=:keyword and status < $status");
        $array = array(
            ':keyword' => $keyword,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    /*
     * @data 添加新话题
     */
    public function talkInsert($keyword,$uid){
        $time = date("Y-m-d H:i:s");
        $stmt = $this->db->prepare('insert into talk (keyword,add_time,update_time,`lead`,uid) values (:keyword,:add_time,:update_time,"",:uid)');
        $array = array(
            ':keyword'=>$keyword,
            ':add_time'=>$time,
            ':update_time'=>$time,
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        return $this->db->lastInsertId();
    }

    /*
    * @data 新增心境话题
    */
    public function talkDetailInsert($tid,$mid,$uid){
        $time = date("Y-m-d H:i:s");
        $stmt = $this->db->prepare('insert into talk_detail (tid,mid,uid,add_time) values (:tid,:mid,:uid,:add_time)');
        $array = array(
            ':tid'=>$tid,
            ':mid'=>$mid,
            ':uid'=>$uid,
            ':add_time'=>$time,
        );
        $stmt->execute($array);
        return $this->db->lastInsertId();
    }

    /*
 * @keyword 话题关键字
 */
    public function getTalkLists($tid,$follow=0,$uid,$page,$size,$is_wall=0,$version){
        if($size > 50){
            $size = 50;
        }
        $start = ($page-1)*$size;
        $f_uid = $follow ? $uid:'';
        $whereClause = $f_uid ? ' and uid in (select f_uid from follow where status = 1 and uid = '.$f_uid.') ' : '';

        $stmt = $this->db->prepare("select id,mid,is_top,is_recommend,status
        from talk_detail as td where tid=:tid and status<2 and mid in(select id from mood where status in(0,1,5))
        $whereClause order by is_top desc,add_time desc, id desc  limit :start,:size");
        $stmt->bindValue ( ':tid' ,  $tid ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );

        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = $this->getNum($tid,$f_uid);
        $talkLists = array();
        if($result){
            $likeModel = new LikeModel();
            $feedModel = new FeedModel();
            $moodModel = new MoodModel();
            $collectModel = new CollectModel();
            foreach($result as $val){
                $data = $moodModel->get($val['mid'],1,2,$is_wall);
                $data['obj_id'] = $val['id'];
                $data['is_top'] = $val['is_top'];
                $data['is_recommend'] = $val['is_recommend'];
                $data['last_time'] = strtotime($data['add_time']);
                $data['add_time'] = Common::show_time($data['add_time']);
                $data['feed_type'] = 1;
                $data['is_like'] = $likeModel->hasData(1,$data['id'],$uid);
                $data['is_collect'] = $collectModel->hasData(1,$data['id'],$uid);
                $data['like_list'] = $likeModel->likeList($data['id'],1,1,5,0);
                $data['comment_list'] = $feedModel->getRedisCommentList($uid,1,$data['id']);
                //如果redis里没有评论，就重新获取存入redis
                if(!$data['comment_list']){
                    $commentList = $feedModel->getCommentListById(1,$data['id']);
                    $feedModel->addRedisComment('mood',$data['id'],$commentList);
                    $data['comment_list'] = $feedModel->getRedisCommentList($uid,1,$data['id']);
                }
                $data['comment_num'] = $feedModel->getCommentNum(1,$data['id']);
                $messageModel = new MessageModel();
                $reward_list = $messageModel->getRewardList(1,$data['id'],1,11);
                //if($version<'3.7'){
                   // $data['reward_list'] = $reward_list['list'];
                    //$data['reward_list'] = isset($data['reward_list'])&&$data['reward_list']?$data['reward_list']:array();
                //}else{
                    $data['reward_list'] = $reward_list;
                //}
                $talkLists[] = $data;
            }
        }
        return array('list'=>$talkLists,'size'=>$count);
    }
    public function getNum($tid,$uid=''){
        if($uid){
            $whereClause = ' and uid in (select f_uid from follow where uid = '.$uid.') ';
        }else
            $whereClause = ' and 1 = 1 ';
        $stmt = $this->db->prepare("select count(id) as num from talk_detail where tid=:tid and status<2 and mid in(select id from mood where status<2) $whereClause");
        $array = array(
            ':tid'=>$tid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }
 /*
  * 获取话题管理员
  * param tid 话题ID
  * param uid 用户id
  */
    public function getTalkManager($tid,$uid){
        $stmt = $this->db->prepare("select `id`,`tid`,`uid`,`reason`,`status`,`result`,`add_time` ,`update_time` from talk_managers where tid=:tid and status=1");
        $array = array(':tid'=>$tid);
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        if(empty($rs)){
            $stmt = $this->db->prepare("select `id`,`tid`,`uid`,`reason`,`status`,`result`,`add_time` ,`update_time` from talk_managers where tid=:tid and uid=:uid and status<3");
            $array = array(':tid'=>$tid,':uid'=>$uid);
            $stmt->execute($array);
            $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return $rs;
    }
    //话题列表
    public function getList($last_id,$size){
        $fields = $last_id ? 'and id <'.$last_id : '';
        $stmt = $this->db->prepare("SELECT id,keyword,view_num FROM talk WHERE STATUS < 2 $fields ORDER BY add_time desc limit :size");
        $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
        $stmt->execute();
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($rs){
            foreach($rs as $k=>$v){
                $rs[$k]['keyword'] = '#'.$v['keyword'].'#';
            }
        }
        return $rs;
    }
    //发现的推荐话题
    public function recommendTalkAndTopic($size=1,$version,$token=''){
        $redisKey = 'app:index:rmhd';
        $rs = $this->redis->get($redisKey);
        if($rs) {
            $rs = json_decode($rs,true);
        }else{
            $stmt = $this->db->prepare("SELECT tid,title,type,type_name FROM subject WHERE status =1 and type in(2,3) order by add_time desc limit $size" );
            $stmt->execute();
            $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->redis->set($redisKey,json_encode($rs));
        }
        if($rs){
            foreach($rs as $k=>$v){
                if($v['type']==2){
                  $rs[$k]['title'] = '#'.$v['title'].'#';
                  $rs[$k]['url'] = '';
                }
                if($v['type']==3){
                  $rs[$k]['url'] = $token ? I_DOMAIN.'/t/'.$v['tid'].'?token='.$token.'&version='.$version :I_DOMAIN.'/t/'.$v['tid'].'?version='.$version;
                }
            }
        }
        return $rs;
    }
    /*
    * 访问更新浏览量
    */
    public function updateViewNum($id){
        $stmt = $this->db->prepare("update talk set view_num = view_num + 1 where id=:id");
        $array = array(':id'=>$id,);
        $stmt->execute($array);
        return 1;
    }

    //把发布话题时间存储到redis-服务于添加浏览数基数
    public function addTalkViewTime($id){
        $key = "talk:view:time";
        $this->redis->hSet($key,$id,time());
    }
    public function getInfo($id){
        $stmt = $this->db->prepare("select id,`keyword`,`lead`,`set_up`,status from talk where id=:id and status < 2");
        $array = array(
            ':id' => $id,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }


}