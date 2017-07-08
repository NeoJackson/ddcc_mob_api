<?php
class TopicModel {
    private $db;
    private $redis;
    public $type_code_arr = array(
        '1'=>'mood','2'=>'photo','3'=>'blog','4'=>'topic','5'=>'stage','6'=>'wish','7'=>'memorial','8'=>'pray','9'=>'share','10'=>'event','11'=>'stage_message'
    );
    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
        $this->contentRedis = CRedis::getContentInstance();
    }
    /**
     * 统计我发布的普通帖子数量
     */
    public function getTopicNum($uid){
        $stmt = $this->db->prepare("select count(id) as num from topic where uid=:uid and status<2");
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    /**
     * 统计我回复的帖子数量
     */
    public function getTopicReplyNum($uid,$sid=0){
        $sql="select count(distinct obj_id) as num from comment where uid=:uid and type=4 and status<2 ";
        if($sid){
            $sql.=" and obj_id in(select id from topic where status<2 and sid=$sid)";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        if($sid){
            $stmt->bindValue(':sid', intval($sid), PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    //判断普通帖子标题是否已存在
    public function titleIsExist($title,$tid=''){
        if(!$tid){
            $stmt = $this->db->prepare("select count(id) as num from topic where replace(title,' ','')=replace(:title,' ','') and status<2");
            $array = array(
                ':title' => $title,
            );
        }else{
            $stmt = $this->db->prepare("select count(id) as num from topic where replace(title,' ','')=replace(:title,' ','') and id!=:tid and status<2");
            $array = array(
                ':title' => $title,
                ':tid' => $tid,
            );
        }
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);;
        return $result['num'];
    }

    /**
     * 更新驿站表中的帖子数目
     */
    public function updateTopicCount($sid,$type){
        $topic_num = $this->countTopicNum($sid);
        if($type == 1){//添加帖子
            $sql = "update stage set topic_num = :topic_num, last_topic_time = :last_topic_time where sid = :sid";
            $array = array(
                ':sid' => $sid,
                ':topic_num' => $topic_num,
                ':last_topic_time' => date('Y-m-d H:i:s')
            );
        }else if($type == 2){//删除帖子
            $sql = "update stage set topic_num = :topic_num where sid = :sid";
            $array = array(
                ':sid' => $sid,
                ':topic_num' => $topic_num,
            );
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        $stageModel = new StageModel();
        $stageModel->clearStageData($sid);//清除缓存里驿站信息
        return 1;
    }
    //统计该驿站下的帖子总数
    public function countTopicNum($sid){
        $sql = "select count(id) as num from topic where status<2 and sid=:sid";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    /**
     * 发布普通帖子
     */
    public function save($sid,$title,$content,$type,$origin,$uid){
        $stageModel = new StageModel();
        $security = new Security();
        $stage_num = $stageModel->stageIsExist($sid);
        if($stage_num == 0){
            return -1;
        }
        $join_info = $stageModel->getJoinStage($sid,$uid);
        if(!$join_info){
            return -2;
        }
        $img_arr = Common::pregMatchImg($content);
        $is_pic = 0;
        if($img_arr[3]){
            $is_pic = 1;
        }
        $content = $security->xss_clean($content);
        $content = strtr($content, array('<embed'=>'<embed wmode="opaque"'));
        $summary = Common::msubstr(Common::deleteHtml($content),0,120,'UTF-8',false);
        $stmt = $this->db->prepare("insert into topic (uid,sid,type,title,summary,content,is_pic,last_comment_time,origin)
        select :uid,:sid,:type,:title,:summary,:content,:is_pic,:last_comment_time,:origin from dual
        where not exists (select * from topic where uid = :uid and UNIX_TIMESTAMP() - 15 < UNIX_TIMESTAMP(add_time))");
        $array = array(
            ':uid' => $uid,
            ':sid' => $sid,
            ':type' => $type,
            ':title' => $title,
            ':summary'=>$summary,
            ':content' => $content,
            ':is_pic' => $is_pic,
            ':last_comment_time' => date('Y-m-d H:i:s'),
            ':origin'=>$origin
        );
        try{
            $stmt->execute($array);
        } catch (PDOException $e) {
            Common::echoAjaxJson(500,"内容包含非法字符，请重新编辑");
        }
        $id = $this->db->lastInsertId();
        if(!$id){
            Common::echoAjaxJson(600,"发的太快，休息一下吧");
        }
        //添加帖子到动态
        $feedModel = new FeedModel();
        Common::http(OPEN_DOMAIN."/common/addFeed",array('scope'=>1,'uid'=>$uid,'type'=>'topic',"id"=>$id,"time"=>time()),"POST");
        $feedModel->addStage($sid,'topic',$id);
        $userModel = new UserModel();
        $this->updateTopicCount($sid,1);//更新帖子数目和最新发表帖子时间
        $userModel->clearUserData($uid);//清除缓存里用户信息(更新帖子数)
        $scoreModel = new ScoreModel();
        $scoreModel->add($uid,0,'topic',$id);
        //添加到队列
        $this->initTopicImg($id);
        return $id;
    }

    private function initTopicImg($id){
        $key = "init:topic:img";
        $this->redis->rPush($key,(int)$id);
    }


    /**
     * 更新普通帖子信息
     */
    public function update($tid,$title,$content,$type,$uid){
        $topicInfo = $this->getBasicTopicById($tid);
        $security = new Security();
        if(!$topicInfo){
            return -1;
        }
        $sid = isset($topicInfo['sid'])?$topicInfo['sid']:'';
        $stageModel = new StageModel();
        $join_info = $stageModel->getJoinStage($sid,$uid);
        if(!$join_info){
            return -2;
        }
        if($topicInfo['uid'] != $uid){
            return -4;
        }
        $img_arr = Common::pregMatchImg($content);
        $is_pic = 0;
        if($img_arr[3]){
            $is_pic = 1;
        }
        $content = $security->xss_clean($content);
        $summary = Common::msubstr(Common::deleteHtml($content),0,120,'UTF-8',false);
        $stmt = $this->db->prepare("update topic set  type = :type , title = :title , summary = :summary ,
        content = :content , is_pic = :is_pic, update_time = :update_time  where id = :id");
        $array = array(
            ':id' => $tid,
            ':type' => $type,
            ':title' => $title,
            ':summary' => $summary,
            ':content' => $content,
            ':is_pic' => $is_pic,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count < 1){
            return 0;
        }
        //添加到队列
        $this->initTopicImg($tid);
        return $tid;
    }

    /**
     * 根据帖子Id查询普通帖子基本信息
     */
    public function getBasicTopicById($id){
        $result = $this->getTopicRedisById($id);
        $stageModel = new StageModel();
        $visitModel = new VisitModel();
        $result['type'] = $result['type'] ? $result['type'] :1;
        $result['view_num'] = (int)$visitModel->getVisitNum('topic',$result['id']);
        $result['summary'] = Common::deleteHtml($result['summary']);
        $result['summary'] = Common::linkReplace($result['summary']);
        $stageInfo = $stageModel->getBasicStageBySid($result['sid']);
        $result['stage_name'] = isset($stageInfo['name']) ? $stageInfo['name'] :'';
        $result['icon'] = isset($stageInfo['icon']) ?Common::show_img($stageInfo['icon'],4,160,160) : '';
        if($result['img_json']){
            $img_arr = json_decode($result['img_json'],true);
            if($img_arr){
                $result['img_src'] = $img_arr;
            }else{
                $result['img_src'] = array();
            }
        }elseif(!$result['img_json']&&$result['is_pic']==1){
            $img_arr = Common::pregMatchImg($result['content']);
            if($img_arr){
                $result['img_src'] = $img_arr[3];
            }else{
                $result['img_src'] = array();
            }
        }else{
            $result['img_src'] = array();
        }
        unset($result['content']);
        return $result;
    }
    /**
     * 根据帖子Id查询普通帖子基本信息与关联信息
     */
    public function getTopicById($id,$uid=0){
        $stmt = $this->db->prepare("select id,uid,title,summary,type,cate_id,comment_num,like_num,share_num,collect_num,reward_num,
        add_time,sid,is_top,is_good,is_recommend,content,status from topic where id=:id and status<2");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if($result){
            $img_arr = Common::pregMatchImg($result['content']);
            if($img_arr){
                $result['img_src'] = $img_arr[3];
            }else{
                $result['img_src'] = array();
            }
            $stageModel = new StageModel();
            $userModel = new UserModel();
            $stageInfo = $stageModel->getStageData($result['sid']);
            $userInfo = $userModel->getUserData($result['uid'],$uid);
            $result['add_time'] = Common::show_time($result['add_time']);
            $result['stage_name'] = $stageInfo['name'];
            $result['did'] = $userInfo['did'];
            $result['nick_name'] = $userInfo['nick_name'];
            $result['avatar'] = $userInfo['avatar'];
            $result['ico_type'] = $userInfo['ico_type'];
            //获取插入的视频
            $char_length= is_int(strpos($result['content'],'<span class="editor-video-data"'));
            if($char_length){
                $BlogModel = new BlogModel();
                $img_arr =  $BlogModel->pregMatchVideo($result['content']);
                $rs_video = $BlogModel->modThVideo($img_arr['spa']);//读取的视频数组
                $result['content'] = str_replace($img_arr['emb'],$rs_video,$result['content']);
            }
        }
        return $result;
    }
    //正则匹配抓取内容里视频
    public function pregMatchVideo($content){
        preg_match_all('/<p class="editor-video-data"[^>]*?>.*?<\/p>/i',$content,$img_arr);
        return $img_arr[0];
    }
    //视频替换
    public function modThVideo($video_arr){
        foreach($video_arr as $ks=> $a){
            preg_match_all('/data-img-large="(.*?)"/',$a,$matched);
            preg_match_all('/data-v_url="(.*?)"/',$a,$data_v_url);
            $img_url=$matched[1][0];
            $href_url=$data_v_url[1][0];
            $count_video[$ks]='<p class="editor-video-data"  onclick="location.href='."'".$href_url."'".'"><i class="video-i"></i><img src="'.$img_url.'"></p>';

        }
        return $count_video;
    }
    /**
     * 获取我发布的帖子列表
     */
    public function getMyTopicList($uid,$page,$size){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select id from topic where uid=:uid and status<2 order by add_time desc limit :start,:size");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $stageModel = new StageModel();
            $userModel = new UserModel();
            foreach($result as $key=>$val){
                $result[$key] = $this->getBasicTopicById($val['id']);
                $stageInfo = $stageModel->getStageData($result[$key]['sid']);
                $result[$key]['stage_name'] = $stageInfo['name'];
                $result[$key]['user'] = $userModel->getUserData($result[$key]['uid']);
                $result[$key]['summary'] = Common::deleteHtml($result[$key]['summary']);
                $len = 105;
                $result[$key]['is_read'] = mb_strlen($result[$key]['summary'],'UTF-8')>$len ? 1 : 0;
                $result[$key]['summary'] = Common::msubstr($result[$key]['summary'],0,$len,'UTF-8');
            }
        }
        return $result;
    }
    /**
     * 获取我回复的帖子列表-分页
     */
    public function getMyTopicReplyList($uid,$page,$size){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select distinct obj_id,type from comment where uid=:uid and type IN(4,10) and status<2 order by add_time desc limit :start,:size");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            foreach($result as $key=>$val){
                if($val['type']==4){
                  $result[$key] = $this->getBasicTopicById($val['obj_id']);
                }elseif($val['type']==10){
                  $eventModel = new EventModel();
                  $result[$key] = $eventModel->getEvent($val['obj_id']);
                }
            }
        }
        return $result;
    }
    //最新帖子
    public function newGoodTopicList($last_id,$size,$uid){
        $condition = $last_id ? 'and id<:last_id' :'';
        $stmt = $this->db->prepare("select id from topic where status< 2 $condition ORDER BY add_time DESC limit :size ");
        if($last_id){
            $stmt->bindValue(':last_id', $last_id, PDO::PARAM_INT);
        }
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list =array();
        if($result){
            foreach($result as $key=>$val){
             $list[$key] = $this->getBasicTopicById($val['id']);
             $userModel = new UserModel();
             $likeModel = new LikeModel();
             $collectModel = new CollectModel();
             $list[$key]['user'] = $userModel->getUserData($list[$key]['uid']);
             $list[$key]['is_like'] = $likeModel->hasData(4,$val['id'],$uid);
             $list[$key]['is_collect'] = $collectModel->hasData(4,$val['id'],$uid);
            }
        }

        return $list;
    }
    //获得推荐帖子封面
    public function getTopicImg($tid){
        $stmt = $this->db->prepare("select img,title,description from topic_push where status=1 and tid=:tid");
        $stmt->bindValue(':tid', $tid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    /**
     * 查询该驿站下的帖子列表
     */
    public function getTopicListBySid($sid,$page,$size){
        $stageModel = new StageModel();
        $stage_num = $stageModel->stageIsExist($sid);
        if($stage_num == 0){
            return -1;
        }
        $start = ($page-1)*$size;
            $stmt = $this->db->prepare("select id from topic where sid=:sid and status<2 order by is_recommend desc,
            is_top desc,last_comment_time desc limit :start,:size");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    /**
     * 根据帖子Id查询首页帖子基本信息
     */
    public function getIndexTopicById($id){
        $stmt = $this->db->prepare("select id,uid,title,content,summary,add_time,sid,like_num,comment_num,share_num,add_time from topic where id=:id and status<2");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result){
            $result['summary'] = Common::deleteHtml($result['summary']);
            $result['summary'] = Common::linkReplace($result['summary']);
            $img_arr = Common::pregMatchImg($result['content']);
            $result['img_src'] = $img_arr ? $img_arr[3] : array();
            unset($result['content']);
        }
        return $result;
    }

    /**
     * 删除帖子
     */
    public function delTopic($tid,$uid){
        $topicInfo = $this->getBasicTopicById($tid, 0, 3);
        if(!$topicInfo){
            return -1;
        }
        $sid = $topicInfo['sid'];
        $stageModel = new StageModel();
        $join_info = $stageModel->getJoinStage($sid,$uid);
        $stageInfo = $stageModel->getBasicStageBySid($sid);
        $stageName = $stageInfo['name'];
        unset($stageInfo);
        if(!$join_info){
            return -2;
        }
        if($join_info['status'] == 0){
            return -3;
        }
        if(!in_array($join_info['role'],array(1,2)) && $topicInfo['uid'] != $uid){
            return -4;
        }
        $sql = "update topic set status = 4, update_time = :update_time where id = :id ";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':id' => $tid,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        $noticeModel = new NoticeModel();
        if(in_array($join_info['role'],array(1,2)) && $uid !=$topicInfo['uid']){
            $content = '您在<a target= "_blank" href="/s/' . $sid . '">' . $stageName . '</a>发表的帖子《' . $topicInfo['title'] .'》已被删除。原因：不符合社区发帖规范，良好的学习与交流环境需要大家共同营造，如有疑问，请联系驿站管理员。';
            $noticeModel->addNotice($topicInfo['uid'],$content);
        }
        $userModel = new UserModel();
        $userModel->clearUserData($uid);//清除缓存里用户信息(更新帖子数)
        $feedModel = new FeedModel();
        $commonModel = new CommonModel();
        $commonModel->updateRelationByObjId(4,$tid,4);//删除相对应的评论、喜欢、打赏等相关信息
        $feedModel->del($uid,'topic',$tid);//删除动态信息
        $feedModel->delStage($sid,'topic',$tid);//删除动态信息
        $this->updateTopicCount($sid,2);
        $this->contentRedis->del(Common::getRedisKey(4).$tid);
        return 1;
    }
    public function getTopicInfoById($id) {
        $stmt = $this->db->prepare("select * from topic WHERE id = :id");
        $stmt->execute(array(':id'=>$id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    //审核帖子
    public function checkTopic($tid,$uid,$status,$content){
        $topicInfo = $this->getTopicInfoById($tid);
        if(!$topicInfo){
            return -1;
        }
        if($topicInfo['status'] == $status) {
            return -5;
        }
        $sid = $topicInfo['sid'];
        $stageModel = new StageModel();
        $join_info = $stageModel->joinStageInfo($sid,$uid);
        if(!$join_info){
            return -2;
        }
        if($join_info['status'] == 0){
            return -3;
        }
        if(!in_array($join_info['role'],array(1,2)) && $topicInfo['uid'] != $uid){
            return -4;
        }
        $sql = "update topic set status = :status, update_time = :update_time where id = :id ";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':id' => $tid,
            ':status'=>$status,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        $snsCheckModel = new SnsCheckModel();
        $snsCheckModel->insert($uid, $tid, $status, $content);
        if(in_array($status,array(2,4))) {
            $noticeModel = new NoticeModel();
            if(in_array($join_info['role'],array(1,2)) && $uid !=$topicInfo['uid']){
                if($status == 2) {

                    //获取驿站信息
                    $stageInfo = $stageModel->getBasicStageBySid($topicInfo['sid']);

                    $content = str_replace(':', '', $content);

                    $content = '十分抱歉，您在"<a target="_blank" href="/s/' . $topicInfo['sid'] . '">' . $stageInfo['name'] . '</a>"发表的帖子《<a target="_blank" href="/t/' . $tid . '" >'.$topicInfo['title'].'</a>》由于<span style="color:red">'.$content.'</span>未能通过驿站管理人员的审核，您可以根据要求再次编辑后重新发布。<a target="_blank" href="/stage/modifyTopic?tid='.$tid.'">进入编辑>></a>';
                }
                if($status == 4) {
                    $content = '十分抱歉，您的帖子《'.$topicInfo['title'].'》由于'.$content.'原因被删除';
                }
                $noticeModel->addNotice($topicInfo['uid'],$content);
            }
            $userModel = new UserModel();
            $userModel->clearUserData($uid);//清除缓存里用户信息(更新帖子数)
            $feedModel = new FeedModel();
            $feedModel->del($uid,'topic',$tid);//删除动态信息
            $feedModel->delStage($sid,'topic',$tid);//删除动态信息
            $this->updateTopicCount($sid,2);
        }
        if($tid){
            $commonModel = new CommonModel();
            $commonModel->updateRelationByObjId(4,$tid,$status);//删除和恢复相对应的评论、喜欢、打赏等相关信息
        }
        if($status ==1){
            $this->updateTopicCount($sid,1);
        }
        return 1;
    }
    /**
     * 根据驿站id查询最新帖子列表-驿站动态
     */
    public function getNewTopicListBySid($sid,$size,$uid=0){
        if($size > 50){
            $size = 50;
        }
        $stmt = $this->db->prepare("select id from topic where sid=:sid and status<2 order by add_time desc limit :size");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $userModel=new UserModel();
            foreach($result as $key=>$val){
                $topicData = $this->getBasicTopicById($val['id']);
                $user = (array)$userModel->getUserData($topicData['uid'],$uid);
                $topicData['did'] = isset($user['did']) ? $user['did'] : '';
                $topicData['nick_name'] = isset($user['nick_name']) ? $user['nick_name'] : '未知用户';
                $topicData['avatar'] = isset($user['avatar']) ? $user['avatar'] : '';
                $result[$key] = $topicData;
            }
        }
        return $result;
    }
    //优秀图贴
    public function getGoodTopicList($start,$size,$uid=0,$token,$version){
        $redisKey = 'good:topic:'.$start.':'.$size;
        $result = $this->redis->get($redisKey);
        if($result) {
            $result = json_decode($result,true);
        } else {
            $stmt = $this->db->prepare("select id,tid,add_time,title,description,img,sort from topic_push
            where status=1 and tid in(select id from topic where status<2) order by sort,add_time desc limit :start,:size");
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);
            $stmt->bindValue(':size', $size, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if($result){
                $topicModel = new TopicModel();
                foreach($result as $key=>$val){
                    $result[$key] = $topicModel->getBasicTopicById($val['tid']);
                    $result[$key]['img'] = $val['img'];
                    if($val['title']){
                        $result[$key]['title'] = $val['title'];
                    }
                    if($val['description']){
                        $result[$key]['summary'] = $val['description'];
                    }
                }
            }
            $this->redis->set($redisKey,json_encode($result));
        }
        $userModel = new UserModel();
        $likeModel = new LikeModel();
        foreach($result as $key=>$val){
            $result[$key]['url'] = I_DOMAIN.'/t/'.$val['id'].'?token='.$token.'&version='.$version;
            $result[$key]['user'] = $userModel->getUserData($result[$key]['uid'],$uid);
            $result[$key]['is_like'] = $likeModel->hasData(4,$val['id'],$uid);
            $result[$key]['likeList'] = $likeModel->likeList($val['id'],4,1,15,0);
            if($val['img_src']){
                foreach($val['img_src'] as $k1=>$v1){
                    $result[$key]['img_src'][$k1] = $v1.'?imageMogr2/thumbnail/!300x300r/gravity/North/crop/300x300';
                }
            }
        }
        return $result;
    }

    /**
     * 更新置顶、加精和推荐为公告帖子
     */
    public function updateTop($uid,$tid,$type=1,$num){
        $topicInfo = $this->getBasicTopicById($tid);
        if(!$topicInfo){
            return -1;
        }
        $sid = isset($topicInfo['sid'])?$topicInfo['sid']:'';
        $stageModel = new StageModel();
        $join_info = $stageModel->joinStageInfo($sid,$uid);
        if(!$join_info){
            return -2;
        }
        if($join_info['status'] == 0){
            return -3;
        }
        if(!in_array($join_info['role'],array(1,2))){
            return -4;
        }
        if($type == 1){
            $field = 'is_top = 1';

        }else if($type == 2){
            $field = 'is_good = 1';
        }else if($type == 3) {
            $field = 'is_recommend = 1, is_top = 0';
            $updateStmt = $this->db->prepare('update topic set  is_recommend = 0 where sid = :sid and is_recommend = 1');
            $updateStmt->execute(array(':sid' => $sid));
        }

        if(1 == $type){
            $rst = $this->setTop($tid);
            if($rst){
                $this->resetTop($sid);
            }else{
                return 0;
            }
        }else{
            $sql = "update topic set $field,update_time = :update_time where id = :id and sid = :sid";
            $stmt = $this->db->prepare($sql);
            $array = array(
                ':id' => $tid,
                ':sid' => $sid,
                ':update_time'=>date('Y-m-d H:i:s')
            );
            $stmt->execute($array);
            $count = $stmt->rowCount();
            if($count<1){
                return 0;
            }

        }

        //发送通知
        $stageInfo = $stageModel->getStageById($sid);
        $noticeModel = new NoticeModel();

        //获取用户代代id
        $userModel = new UserModel();
        $userInfo = $userModel->getUserByUid($stageInfo['uid']);
        $did = $userInfo['did'];
        unset($userModel, $userInfo);
        if(in_array($join_info['role'],array(1,2)) && $uid !=$topicInfo['uid']){
            $str = $type == 1 ? '置顶' : ($type == 2 ? '加精' : '公告');
            $content = '恭喜您，<a target="_blank" href="/s/'.$stageInfo['sid'].'" class="blue">'.$stageInfo['name'].'</a>的驿长<a target="_blank" href="/'.$did.'" class=blue >'.$stageInfo['nick_name'].'</a>对您的帖子<a target="_blank" href="/t/'.$topicInfo['id'].'" class="blue">《'.$topicInfo['title'].'》</a>设置了'.$str;
            $noticeModel->addNotice($topicInfo['uid'],$content);
        }
        return 1;
    }

    protected  function resetTop( $sid ){
        $maxTopNum = 3;
        if( $sid ){
            $sql = 'SELECT id FROM topic WHERE sid=:sid AND is_top=1 ORDER BY last_top_time DESC,id DESC';
            $stmt = $this->db->prepare( $sql );
            $stmt->execute(array(
                ':sid' => $sid,
            ));
            $rst = $stmt->fetchAll( PDO::FETCH_COLUMN );
            $num = count( $rst );
            if( $num > $maxTopNum ){
                //重置置顶数为三个
                for($i=3; $i<$num; $i++){
                    $curId = $rst[$i];
                    $sql = 'UPDATE topic SET is_top=0 WHERE is_top=1 AND id=:id';
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute(array(
                        ':id' => $curId,
                    ));

                }
            }
        }
    }

    public function setTop($id){
        $rst = false;
        if($id){
            $sql = "update topic set is_top=1, is_recommend = 0, last_top_time = :update_time where id = :id";
            $stmt = $this->db->prepare($sql);
            $array = array(
                ':id' => $id,
                ':update_time'=>date('Y-m-d H:i:s')
            );
            $stmt->execute($array);
            $rst = $stmt->rowCount();
        }
        return $rst;
    }
    //文化圈随机一条精帖数据
    public function randGoodTopic($uid,$version,$token){
        $stmt = $this->db->prepare("select id,tid from topic_push where status=1 and tid in(select id from topic where status<2) order by sort,add_time desc limit 10");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $k = array_rand($result);
        $userModel = new UserModel();
        $likeModel = new LikeModel();
        $collectModel = new CollectModel();
        $feedModel = new FeedModel();
        $data = $this->getBasicTopicById($result[$k]['tid']);
        $data['user'] = $userModel->getUserData($data['uid'],$uid);
        $data['feed_type'] = 4;
        $data['good'] = 1;
        $data['is_like'] = $likeModel->hasData(4,$data['id'],$uid);
        $data['is_collect'] = $collectModel->hasData(4,$data['id'],$uid);
        $data['like_list'] = $likeModel->likeList($data['id'],4,1,10,$uid);
        $data['comment_list'] = $feedModel->getRedisCommentList($uid,4,$data['id']);
        //如果redis里没有评论，就重新获取存入redis
        if(!$data['comment_list']){
            $commentList = $feedModel->getCommentListById(4,$data['id']);
            $type_code = $this->type_code_arr[4];
            $feedModel->addRedisComment($type_code,$data['id'],$commentList);
            $data['comment_list'] = $feedModel->getRedisCommentList($uid,4,$data['id']);
        }
        $data['comment_list'] = $data['comment_list']?$data['comment_list']:array();
        $messageModel = new MessageModel();
        $reward_list = $messageModel->getRewardList(4,$data['id'],1,11);
        $data['reward_list'] = $reward_list['list'];
        $data['reward_list'] = isset($data['reward_list'])&&$data['reward_list']?$data['reward_list']:array();
        $data['url'] =I_DOMAIN.'/t/'.$result[$k]['tid'].'?token='.$token.'&version='.$version;
        return $data;
    }

    //帖子详情页底部相关推荐优秀图贴
    public function getRecommendTopicList($id,$start,$size,$uid=0,$token,$version){
        $time = date("Y-m-d H:m:s",strtotime("-6 month"));
        $stmt = $this->db->prepare("select id,title,uid,summary,add_time,content,img_json,is_pic from topic where status<2 and is_pic = 1 and id!=:id and
        add_time>:time order by rand() limit :start,:size");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':time', $time, PDO::PARAM_STR);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $userModel = new UserModel();
            $visitModel = new VisitModel();
            foreach($result as $key=>$val){
                $result[$key]['user'] = $userModel->getUserData($val['uid'],$uid);
                $result[$key]['view_num'] = (int)$visitModel->getVisitNum('topic',$val['id']);
                $result[$key]['url'] = $token ? I_DOMAIN.'/t/'.$val['id'].'?token='.$token.'&version='.$version :I_DOMAIN.'/t/'.$val['id'].'?version='.$version;
                if($val['img_json']){
                    $img_arr = json_decode($val['img_json'],true);
                    $result[$key]['img'] = $img_arr[0];
                }elseif(!$val['img_json']&&$val['is_pic']==1){
                    $img_arr = Common::pregMatchImg($val['content']);
                    $result[$key]['img'] = $img_arr[3][0];
                }
            }
        }
        return $result;
    }

    //获取推荐设置的封面
    public function getRecommendCoverById($id){
        $stmt = $this->db->prepare("select id,img from topic_push");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getInfoForFeed($id){
        $info = $this->getTopicRedisById($id);
        $stageModel = new StageModel();
        $info['summary'] = Common::deleteHtml($info['content']);
        $info['summary'] = Common::linkReplace($info['summary']);
        $stageInfo = $stageModel->getBasicStageBySid($info['sid']);
        $info['stage_name'] = $stageInfo['name'];
        if($info['img_json']){
            $img_arr = json_decode($info['img_json'],true);
            if($img_arr){
                $info['img_src'] = $img_arr;
            }else{
                $info['img_src'] = array();
            }
        }elseif(!$info['img_json']&&$info['is_pic']==1){
            $img_arr = Common::pregMatchImg($info['content']);
            if($img_arr){
                $info['img_src'] = $img_arr[3];
            }else{
                $info['img_src'] = array();
            }
        }else{
            $info['img_src'] = array();
        }
        if(isset($info['img_src'])){
            foreach($info['img_src'] as $v){
                $info['showImg'][] = Common::show_img($v,0,450,450);
            }
        }
        unset($info['content']);
        if($info['status']<2){
            return $info;
        }else{
            return array();
        }

    }

    //帖子详情页根据id查询帖子信息
    public function getTopicDetailById($id,$uid=0){
        $result =$this->getTopicRedisById($id);
        if($result){
            $followModel = new FollowModel();
            $userModel = new UserModel();
            $result['user'] = $userModel->getUserData($result['uid'],$uid);
            $result['relation'] = $followModel->getRelation($uid,$result['uid']);
            //获取插入的视频
            $char_length = is_int(strpos($result['content'],'<span class="editor-video-data"'));
            if($char_length){
                $BlogModel = new BlogModel();
                $img_arr =  $BlogModel->pregMatchVideo($result['content']);
                $rs_video = $BlogModel->modThVideo($img_arr['spa']);//读取的视频数组
                $result['content'] = str_replace($img_arr['emb'],$rs_video,$result['content']);
            }
        }
        return $result;
    }
    //获取缓存中的帖子数据
    public function getTopicRedisById($id){
        $redisKey = Common::getRedisKey(4).$id;
        $result = $this->contentRedis->get($redisKey);
        if($result) {
            $result = json_decode($result,true);
        }else{
            $stmt = $this->db->prepare("select * from topic where id=:id");
            $array = array(
                ':id' => $id
            );
            $stmt->execute($array);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->contentRedis->set($redisKey,json_encode($result));
        }
        return $result;
    }
    //抓取图片后更新内容
    public function fetchUpdateContent($id,$content,$img_json=''){
        $stmt = $this->db->prepare("update topic set img_json=:img_json, content = :content where id = :id");
        $array = array(
            ':id' => $id,
            ':img_json' => $img_json,
            ':content' => $content
        );
        $stmt->execute($array);
    }
}