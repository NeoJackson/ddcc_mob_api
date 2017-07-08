<?php
class MoodModel {
    private $db;
    public  $event_type_name = array('1'=>'活动','3'=>'培训','6'=>'展览','7'=>'演出');
    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
        $this->contentRedis = CRedis::getContentInstance();//主表内容缓存(心境，帖子，商品，活动)
    }

    /*
     * @name 增加心境
     *
     */
    public function add($uid,$content,$imgArray=array(),$sortArray=array(),$is_address = 0,$origin,$is_public=2){
        $is_img = $is_video = $is_at = 0;
        if($imgArray){
            $is_img = 1;
        }
        /*$content = Common::contentSpace($content);*///去除掉，影响换行显示
        list($content,$atArray) = Common::atUser($uid,$content);
        $stmt = $this->db->prepare("insert into mood (uid,content,is_img,is_video,is_at,is_address,is_public,origin) select :uid,:content,:is_img,:is_video,:is_at,:is_address,:is_public,:origin
        from dual where not exists (select * from mood where uid = :uid and UNIX_TIMESTAMP() - 10 < UNIX_TIMESTAMP(add_time))");
        $array = array(
            ':uid'=>$uid,
            ':content'=>$content,
            ':is_img'=>$is_img,
            ':is_video'=>$is_video,
            ':is_at'=>$is_at,
            ':is_address'=>$is_address,
            ':is_public'=>$is_public,
            ':origin'=>$origin
        );
        $stmt->execute($array);
        $mood_id = $this->db->lastInsertId();
        if(!$mood_id){
            Common::echoAjaxJson(600,"发的太快，休息一下吧");
        }
        if($is_img){
            $this->addImages($uid,$mood_id,$imgArray,$sortArray);
        }
        if($is_public==2){
            $feedModel = new FeedModel();
            if($atArray){
                $feedModel->mentionUser(1,$uid,$mood_id,$atArray);
            }
            //添加心境到动态
            Common::http(OPEN_DOMAIN."/common/addFeed",array('scope'=>1,'uid'=>$uid,'type'=>'mood',"id"=>$mood_id,"time"=>time()),"POST");
            //添加心境到话题
            $talkModel = new TalkModel();
            $talkModel->add($mood_id,$uid,$content);
        }
        //发放福报值和经验
        $scoreModel = new ScoreModel();
        $scoreModel->add($uid,0,'mood',$mood_id);
        return $mood_id;
    }
    //发布心境地址处理
    public function addAddress($uid,$mood_id,$lng,$lat,$address){
        $stmt = $this->db->prepare("insert into mood_address (uid,mood_id,lng,lat,address) values (:uid,:mood_id,:lng,:lat,:address)");
        $array = array(
            ':uid'=>$uid,
            ':mood_id'=>$mood_id,
            ':lng'=>$lng,
            ':lat'=>$lat,
            ':address'=>$address
        );
        $stmt->execute($array);
    }
    /*
     * @name 增加心境时图片处理
     */
    public function addImages($uid,$mood_id,$img,$sort){
        $albumModel = new AlbumModel();
        $album_id = $albumModel->getInitAlbumId($uid,3);
        $add_time = date("Y-m-d H:i:s");
        $is_add = $this->isAddImages($mood_id);
        if($is_add==0){
            foreach($img as $k=> $v){
                $num = $this->getMoodImgByPath($v,$mood_id);
                if(!$num){
                    if(count($img)>1){
                        $height = 200;
                        $width = 200;
                    }else{
                        $imgInfo = getimagesize(IMG_DOMAIN.$v);
                        $height = $imgInfo[1];
                        $width = $imgInfo[0];
                    }
                    $stmt = $this->db->prepare("insert into mood_images (uid,mood_id,img,width,height,sort) values (:uid,:mood_id,:img,:width,:height,:sort)");
                    $array = array(
                        ':uid'=>$uid,
                        ':mood_id'=>$mood_id,
                        ':img'=>$v,
                        ':width'=>$width,
                        ':height'=>$height,
                        ':sort'=>$sort[$k]
                    );
                    $stmt->execute($array);
                    $photo = array('0'=>$v);
                    $intro = array('0'=>$v);
                    $albumModel->addPhoto($uid,$album_id,$photo,$intro,$add_time,0);
                }
            }
            $this->contentRedis->del(Common::getRedisKey(1).$mood_id);//删除心境缓存数据
            $this->get($mood_id);
        }
    }
    public function isAddImages($mood_id){
        $stmt = $this->db->prepare("select count(*) as num from mood_images where mood_id=:mood_id");
        $array = array(
            ':mood_id' => $mood_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //查询心境图片是否存在
    public function getMoodImgByPath($img,$mood_id){
        $stmt = $this->db->prepare("select count(*) as num from mood_images where img=:img and mood_id=:mood_id");
        $array = array(
            ':img' => $img,
            ':mood_id'=>$mood_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }


    public function share($uid,$content,$share_type,$share_id,$shared_id=0){
        list($content,$atArray) = Common::atUser($uid,$content);
        $stmt = $this->db->prepare("insert into share (uid,content,type,obj_id) values (:uid,:content,:type,:obj_id)");
        $array = array(
            ':uid' => $uid,
            ':content' => $content,
            ':type' => $share_type,
            ':obj_id' => $share_id,
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        if($id<1){
            return 0;
        }
        $feedModel = new FeedModel();
        if($atArray){
            $feedModel->mentionUser(1,$uid,$id,$atArray);
        }
        //更新分享的数量
        $this->updateShareNum($share_id,$share_type);
        //更新分享的分享数量
        $this->updateSharedNum($shared_id);
        //添加分享到动态
        if(in_array($share_type,array(1,2,4))){
            Common::http(OPEN_DOMAIN."/common/addFeed",array('scope'=>1,'uid'=>$uid,'type'=>'share',"id"=>$id,"time"=>time()),"POST");
        }elseif(in_array($share_type,array(10))){
            Common::http(OPEN_DOMAIN."/common/addFeed",array('scope'=>2,'uid'=>$uid,'type'=>'share',"id"=>$id,"time"=>time()),"POST");
        }
        return $id;
    }

    //更新分享的数量
    public function updateShareNum($id,$type){
        $array = array(
            ':id'=>$id,
        );
        switch($type){
            case 1:
                $stmt = $this->db->prepare("update mood set share_num = share_num + 1 where id=:id ");
                $stmt->execute($array);
            case 2:
                $stmt = $this->db->prepare("update album_photo set share_num = share_num + 1 where id=:id ");
                $stmt->execute($array);
            case 3:
                $stmt = $this->db->prepare("update blog set share_num = share_num + 1 where id=:id ");
                $stmt->execute($array);
            case 4:
                $stmt = $this->db->prepare("update topic set share_num = share_num + 1 where id=:id ");
                $stmt->execute($array);
            case 10:
                $stmt = $this->db->prepare("update event set share_num = share_num + 1 where id=:id ");
                $stmt->execute($array);
        }
    }
    //更新分享的分享数量
    public function updateSharedNum($id){
        $array = array(
            ':id'=>$id,
        );
        $stmt = $this->db->prepare("update share set share_num = share_num + 1 where id=:id ");
        $stmt->execute($array);
    }
    /*
     * @name 心境列表显示
     */
    public function getList($uid,$page,$size){
        $page_start = ($page-1)*$size;
        $stmt = $this->db->prepare("select id,uid,content,is_img,is_video,is_at,share_type,share_id,comment_num,like_num,status,add_time from mood where status < 2 and uid=:uid and is_public=2 order by add_time desc,id desc limit $page_start,$size");
        $array = array(
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $arr = array();
        if($rs){
            $userModel = new UserModel();
            $likeModel = new LikeModel();
            $collectModel = new CollectModel();
            $user = $userModel->getUserData($uid);
            foreach($rs as $key=>$val){
                $rs[$key] = $this->getExtInfo($val);
                $rs[$key]['user'] = $user;
                $rs[$key]['feed_type'] = 1;
                $rs[$key]['add_time'] = Common::show_time($rs[$key]['add_time']);
                $rs[$key]['is_like'] = $likeModel->hasData(1,$val['id'],$uid);
                $rs[$key]['is_collect'] = $collectModel->hasData(1,$val['id'],$uid);
            }
            $arr['list'] = $rs;
            $arr['num'] = $this->getNum($uid);
        }
        return $arr;
    }
    /*
     * @name 统计心境数
     */
    public function getNum($uid){
        $stmt = $this->db->prepare("select count(id) as num from mood where uid=:uid and status<2");
        $array = array(
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }

    public function get($id,$flag = 0,$is_public=2){
        $redisKey = Common::getRedisKey(1).$id;
        $info = $this->contentRedis->get($redisKey);
        if($info) {
            $info = json_decode($info,true);
        } else {
            if($is_public==2){
                $field = ' and is_public = 2';
            }else{
                $field = '';
            }
            $status = $flag ? ' and status in (0,1,5)' : ' and status<2';
            $stmt = $this->db->prepare("select id,uid,lng,lat,mood_address,add_time,content,is_img,is_video,is_public,video_name,video_img,status,like_num,comment_num,is_address,reward_num,video_name,client_id from mood where id=:id $field $status");
            $array = array(
                ':id'=>$id,
            );
            $stmt->execute($array);
            $mood = $stmt->fetch(PDO::FETCH_ASSOC);
            if($mood){
               // $mood['content'] = Common::linkReplace($mood['content']);
                $mood['content'] = Common::showEmoticon($mood['content'],1);
                $mood['lng'] = $mood['lng'] ? $mood['lng'] :'';
                $mood['lat'] = $mood['lat'] ? $mood['lat'] :'';
                $mood['mood_address'] = $mood['mood_address'] ? $mood['mood_address'] :'';
                $mood['video_name'] = $mood['video_name'] ? VIDEO_DOMAIN.$mood['video_name'] :'';
                $mood['video_img'] = $mood['video_img'] ? IMG_DOMAIN.$mood['video_img'] :'';
                $info = $this->getExtInfo($mood);
                $this->contentRedis->set($redisKey,json_encode($info));
                return $info;
            }
        }
        return $info;
    }

    public function getMood($id){
        $stmt = $this->db->prepare("select * from mood where id=:id and status<2");
        $array = array(
            ':id'=>$id,
        );
        $stmt->execute($array);
        $mood = $stmt->fetch(PDO::FETCH_ASSOC);
        if($mood){
            $mood['video_name'] = $mood['video_name'] ? VIDEO_DOMAIN.$mood['video_name'] :'';
            $mood['video_img'] = $mood['video_img'] ? IMG_DOMAIN.$mood['video_img'] :'';
        }
        return $mood;
    }
    public function getExtInfo($mood){
        if($mood['is_img']){
            $stmt_img = $this->db->prepare("select id,img,width,height from mood_images where status<2 and mood_id=:mood_id order by sort asc,add_time desc");
            $array_img = array(
                ':mood_id'=>$mood['id']
            );
            $stmt_img->execute($array_img);
            $rs_img = $stmt_img->fetchAll(PDO::FETCH_ASSOC);
            if($rs_img){
                foreach($rs_img as $k=>$v){
                    $rs_img[$k]['show_img'] = Common::show_img($v['img'],0,450,450);
                }
                $mood['img'] =$rs_img;
            }
        }else{
            $mood['img'] = array();
        }
        if($mood['is_video']&&$mood['is_video']==1){
            $stmt_video = $this->db->prepare("select id,url,title,img_large,img_small from mood_video where status<2 and mood_id=:mood_id");
            $array_video = array(
                ':mood_id'=>$mood['id'],
            );
            $stmt_video->execute($array_video);
            $rs_video = $stmt_video->fetchAll(PDO::FETCH_ASSOC);
            if($rs_video){
                $mood['video'] = $rs_video;
            }
        }else{
            $mood['video'] = array();
        }

        return $mood;
    }

    public function del($uid,$id){
        $stmt = $this->db->prepare("update mood set status = 4 where uid = :uid and id=:id ");
        $array = array(
            ':uid'=>$uid,
            ':id'=>$id,
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if($rs == 1){
            $feedModel = new FeedModel();
            $commonModel = new CommonModel();
            $commonModel->updateRelationByObjId(1,$id,4);//删除相对应的评论、喜欢、打赏等相关信息
            $feedModel->del($uid,'mood',$id);//删除动态信息
            $this->contentRedis->del(Common::getRedisKey(1).$id);//删除心境缓存数据
        }
        return $rs;
    }

    //查询用户最新一条心境
    public function getNewMoodByUid($uid){
        $stmt = $this->db->prepare("select id,content,add_time from mood where uid=:uid and status<2 and is_public=2 order by add_time desc limit 1");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    //更新心境、日志、帖子中的打赏数
    public function updateRewardNum($obj_id,$type=1){
        if(!$type || !in_array($type,array(1,3,4))){
            return false;
        }
        if($type == 1){
            $table = 'mood';
        }elseif($type == 3){
            $table = 'blog';
        }elseif($type == 4){
            $table = 'topic';
        }
        $stmt_select = $this->db->prepare("select count(1) as num from bounty_push where obj_id = :obj_id and type=:type");
        $array_select = array(
            ':obj_id' => $obj_id,
            ':type' => $type,
        );
        $stmt_select->execute($array_select);
        $reward_num = $stmt_select->fetch(PDO::FETCH_ASSOC);
        $stmt = $this->db->prepare("update $table set reward_num = :reward_num where id=:id ");
        $array = array(
            ':id' => $obj_id,
            ':reward_num' => $reward_num['num'],
        );
        $stmt->execute($array);
        return $stmt->rowCount();
    }
    //查询用户的心境相册最新4张图片
    public function getMoodImg($uid){
        $stmt = $this->db->prepare("SELECT ap.id,ap.img,ap.album_id,ap.add_time FROM album AS a
            LEFT JOIN album_photo AS ap ON a.id = ap.album_id
            WHERE a.uid =:uid AND a.type = 3 AND ap.status < 2 ORDER BY ap.add_time desc LIMIT 4");
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        return $stmt->fetchALL(PDO::FETCH_ASSOC);
    }

    //分享数据查询
    public function getShare($id,$uid=0,$share_type=0, $status=2,$version,$token){
        $condition = !$share_type?' and content_type=1':'';
        $stmt = $this->db->prepare("select id,type,content_type,obj_id,obj_uid,content,uid,status,comment_num,like_num,share_num,
        collect_num,reward_num,add_time from share where id=:id and status<:status $condition");
        $array = array(
            ':id' => $id,
            ':status' => $status,
        );
        $stmt->execute($array);
        $share = $stmt->fetch(PDO::FETCH_ASSOC);
        if($share){
            $share['content'] = Common::linkReplace($share['content']);
            $share['content'] = Common::showEmoticon($share['content'],1);
        }
        if(isset($share['type']) && $share['type'] && $share['obj_id']){
            $userModel = new UserModel();
            if($share['content_type']==1){//分享内容
                switch($share['type']){
                    case 1:
                        $data = $this->get($share['obj_id'],$uid);
                        $share['message_content'] = isset($data['content'])&&$data['content']?$data['content']:'';//用于消息中心首页显示
                        break;
                    case 2:
                        $albumModel = new AlbumModel();
                        $data = $albumModel->getPhotoById($share['obj_id'],$uid);
                        $data['album_img'] = Common::show_img($data['album_img'],4,160,160);
                        $data['album'] = $albumModel->getAlbumByPhotoId($share['obj_id']);
                        $share['message_content'] = isset($data['id'])&&$data['id']?SNS_DOMAIN.'/a/'.$data['id']:'';
                        break;
                    case 3:
                        $blogModel = new BlogModel();
                        $data = $blogModel->getBasicBlogById($share['obj_id'],2,$uid);
                        if(isset($data['summary'])){
                            $data['summary'] = Common::deleteHtml($data['summary']);
                            $len = 100;
                            $data['is_read'] = mb_strlen($data['summary'],'UTF-8')>$len ? 1 : 0;
                            $data['summary'] = Common::msubstr($data['summary'],0,$len,'UTF-8');
                        }
                        $share['message_content'] = isset($data['title'])&&$data['title']?$data['title']:'';
                        $share['url'] = I_DOMAIN.'/b/'.$share['obj_id'].'?token='.$token.'&version='.$version;
                        break;
                    case 4:
                        $topicModel = new TopicModel();
                        $data = $topicModel->getInfoForFeed($share['obj_id']);
                        if($data){
                            if(isset($data['summary'])){
                                $data['summary'] = Common::deleteHtml($data['summary']);
                                $len = 100;
                                $data['is_read'] = mb_strlen($data['summary'],'UTF-8')>$len ? 1 : 0;
                                $data['summary'] = Common::msubstr($data['summary'],0,$len,'UTF-8');
                            }
                            $share['message_content'] = isset($data['title'])&&$data['title']?$data['title']:'';
                            $share['url'] = I_DOMAIN.'/t/'.$share['obj_id'].'?token='.$token.'&version='.$version;
                            $data['url'] = I_DOMAIN.'/t/'.$share['obj_id'].'?token='.$token.'&version='.$version;
                        }
                        break;
                    case 10:
                        $eventModel = new EventModel();
                        $stageModel = new StageModel();
                        $data = $eventModel->getFeedEventInfo($share['obj_id']);
                        if($data){
                            if($data['type']==1){
                                $type_info = $eventModel->getBusinessEventType($data['type_code']);
                            }else{
                                $type_info = Common::eventType($data['type']);
                            }
                            $data['type_name'] = $type_info['name'];
                            $data['code_name'] = $type_info['code'];
                            $stage = $stageModel->getBasicStageBySid($data['sid']);
                            $data['stage_name'] = $stage['name'];
                            $share['message_content'] = isset($data['title'])&&$data['title']?$data['title']:'';
                            $share['url'] = I_DOMAIN.'/e/'.$share['obj_id'].'?token='.$token.'&version='.$version;
                            $data['url'] = I_DOMAIN.'/e/'.$share['obj_id'].'?token='.$token.'&version='.$version;
                        }
                        //print_r($data);exit;
                        break;
                    case 12:
                        $stagegoodsModel = new StagegoodsModel();
                        $data = $stagegoodsModel->getFeedGoodsInfo($share['obj_id']);
                        $share['message_content'] = isset($data['name'])&&$data['name']?$data['name']:'';
                        $share['url'] = I_DOMAIN.'/g/'.$share['obj_id'].'?token='.$token.'&version='.$version;
                        $data['url'] = I_DOMAIN.'/g/'.$share['obj_id'].'?token='.$token.'&version='.$version;
                        break;
                }
            }elseif($share['content_type']==2){
                //推荐内容
                switch($share['type']){
                    case 1:
                        $userModel = new UserModel();
                        $data = $userModel->getUserData($share['obj_id'],$uid);
                        $userModel = new UserModel();
                        $info = $userModel->getAngelInfoByUid($data['uid']);
                        if($info){
                            $data['intro'] = $info['info'];
                        }
                        if(isset($data['intro'])){
                            $data['intro'] = Common::msubstr($data['intro'],0,65,'UTF-8');
                        }
                        break;
                    case 2:
                        $stageModel = new StageModel();
                        $data = $stageModel->getBasicStageBySid($share['obj_id']);
                        if(isset($data['intro'])){
                            $data['intro'] = Common::msubstr($data['intro'],0,65,'UTF-8');
                        }
                        break;
                }
            }
            //$data['add_time'] = Common::show_time($data['add_time']);
            $share['share_data'] = array();
            if(isset($data['uid'])){
                $user= $userModel->getUserData($data['uid'],$uid);
                $data['user']['uid'] = $user['uid'];
                $data['user']['did'] = $user['did'];
                $data['user']['nick_name'] = $user['nick_name'];
                $data['user']['avatar'] = $user['avatar'];
                $data['user']['self'] = $user['self'];
                $data['user']['ico_type'] = $user['ico_type'];
                $data['user']['relation'] = $user['relation'];
                $data['user']['type'] = $user['type'];
                $share['share_data'] = $data;
            }
        }
        return $share;
    }

    //删除分享
    public function delShare($id,$uid){
        $stmt = $this->db->prepare("update share set status = 4, update_time = :update_time where id = :id and uid=:uid");
        $array = array(
            ':id' => $id,
            ':uid' => $uid,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        $feedModel = new FeedModel();
        $commonModel = new CommonModel();
        $commonModel->updateRelationByObjId(9,$id,4);//删除相对应的评论、喜欢、打赏等相关信息
        $feedModel->del($uid,'share',$id);//删除动态信息
        $feedModel->delApp($uid,'share',$id);//删除动态信息
        return 1;
    }

    //获取心境列表
    public function getMoodList($last_id,$size=20,$uid=0){
        $sql = "select id,uid,content,is_img,is_video,is_at,share_type,share_id,comment_num,share_num,like_num,status,add_time,reward_num,video_name,video_img
        from mood where status<2 and share_type=0 and is_show=1 and is_public=2";
        if($uid) {
            $sql .= " and uid not in(SELECT f_uid FROM follow WHERE uid = :uid and status=1) and uid != :uid";
        }
        $field = $last_id ? " and id<$last_id" : '';
        $sql .= "$field order by add_time desc limit :size";
        $stmt = $this->db->prepare($sql);
        if($uid) {
            $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        }
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $moodModel = new MoodModel();
            $userModel  = new UserModel();
            $collectModel = new CollectModel();
            $feedModel = new FeedModel();
            $likeModel = new LikeModel();
            foreach($result as $key=>$val){
                $data = $moodModel->get($val['id'],0);
                $data['feed_type'] = 1;
                $data['lng'] = $data['lng'] ? $data['lng'] :'';
                $data['lat'] = $data['lat'] ? $data['lat'] : '';
                $data['mood_address'] = $data['mood_address'] ? $data['mood_address'] : '';
                $data['unix_time'] = strtotime($data['add_time']);
                $data['add_time'] = Common::show_time($data['add_time']);
                $data['user'] = $userModel->getUserData($val['uid'],$uid);
                $data['is_like'] = $likeModel->hasData(1,$val['id'],$uid);
                $data['is_collect'] = $collectModel->hasData(1,$val['id'],$uid);
                $data['comment_list'] = $feedModel->getRedisCommentList($uid,1,$val['id']);
                $data['like_list'] = $likeModel->likeList($val['id'],1,1,10,$uid);
                //如果redis里没有评论，就重新获取存入redis
                if(!$data['comment_list']){
                    $commentList = $feedModel->getCommentListById(1,$val['id']);
                    $feedModel->addRedisComment('mood',$val['id'],$commentList);
                    $data['comment_list'] = $feedModel->getRedisCommentList($uid,1,$val['id']);
                }
                if(isset($data['img'])){
                    foreach($data['img'] as $k1 =>$v1){
                        $data['img'][$k1]['show_img'] = Common::show_img($v1['img'],4,300,300);
                    }
                }
                $messageModel = new MessageModel();
                $reward_list = $messageModel->getRewardList(1,$val['id'],1,8);
                $data['reward_list'] = $reward_list['list'];
                $data['comment_list'] = $data['comment_list']?$data['comment_list']:array();
                $result[$key] = $data;
            }
        }
        return $result;
    }

    public function hasNewMood($lastTime,$uid='') {
        $lastTime = date('Y-m-d H:i:s',$lastTime);
        $sql = 'select count(*) as num,max(add_time) as last_time from mood where add_time > :last_time and status<2 and share_type=0 and is_show=1 and is_public=2';
        if($uid) {
            $sql .= " and uid NOT IN(SELECT f_uid FROM follow WHERE uid = :uid and status=1) and uid != :uid";
        }
        $stmt = $this->db->prepare($sql);
        if($uid) {
            $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        }
        $stmt->bindValue(':last_time', $lastTime, PDO::PARAM_INT);
        $stmt->execute();
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs;
    }
    /*
     * @name 增加心境
     *
     */
    public function addNew($uid,$content,$is_img = 0,$is_address = 0,$is_public=2,$origin,$lat,$lng,$address,$is_video=0,$video_name = '',$video_img='',$version='3.7',$client_id=''){
        $is_at = 0;
        if($is_img&&$version<'3.7.1'){
            $status = 5;
        }else{
            $status = 0;
        }
        /*$content = Common::contentSpace($content);*///去除掉，影响换行显示
        list($content,$atArray) = Common::atUser($uid,$content);
        $stmt = $this->db->prepare("insert into mood (uid,content,is_img,is_video,is_at,is_address,is_public,video_name,video_img,origin,lng,lat,mood_address,client_id,status) select :uid,:content,:is_img,:is_video,:is_at,:is_address,:is_public,:video_name,:video_img,:origin,:lng,:lat,:address,:client_id,:status from dual where not exists (select * from mood where uid = :uid and UNIX_TIMESTAMP() - 10 < UNIX_TIMESTAMP(add_time))");
        $array = array(
            ':uid'=>$uid,
            ':content'=>$content,
            ':is_img'=>$is_img,
            ':is_video'=>$is_video,
            ':is_at'=>$is_at,
            ':is_address'=>$is_address,
            ':is_public'=>$is_public,
            ':video_name'=>$video_name,
            ':video_img'=>$video_img,
            ':origin'=>$origin,
            ':lng'=>$lng,
            ':lat'=>$lat,
            ':address'=>$address,
            ':client_id'=>$client_id,
            ':status'=>$status
        );
        $stmt->execute($array);
        $mood_id = $this->db->lastInsertId();
        if(!$mood_id){
            Common::echoAjaxJson(600,"发的太快，休息一下吧");
        }
        if($is_public==2){
            $feedModel = new FeedModel();
            if($atArray){
                $feedModel->mentionUser(1,$uid,$mood_id,$atArray);
            }
            //添加心境到话题
            $talkModel = new TalkModel();
            $talkModel->add($mood_id,$uid,$content);
        }
        //发放福报值和经验
        $scoreModel = new ScoreModel();
        $scoreModel->add($uid,0,'mood',$mood_id);
        return $mood_id;
    }
    //修改心境状态
    public function updateMood($mood_id){
        $stmt = $this->db->prepare("update mood set status = 0, update_time = :update_time where id = :id");
        $array = array(
            ':id' => $mood_id,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        return 1;
    }
    //获取用户发布的心境总数(各种状态)
    public function getMoodNum($uid){
        $sql = 'select count(*) as num from mood where uid=:uid';
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }
    //获取心境列表
    public function getMoodListByUid($last_id,$size=20,$flag=0,$uid){
        $flag = $flag ? " and is_public =2" : " ";
        $field = $last_id ? " and id<$last_id" : '';
        $sql = "select id,uid,content,status,add_time from mood where uid=:uid and status in(0,1,5) $flag $field order by add_time desc limit :size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $moodModel = new MoodModel();
            $userModel = new UserModel();
            foreach($result as $key=>$val){
                $data = $moodModel->get($val['id'],1,999);
                $data['feed_type'] = 1;
                $data['unix_time'] = strtotime($val['add_time']);
                $data['add_time'] = Common::show_time($val['add_time']);
                $data['user'] = $userModel->getUserData($val['uid'],$uid);
                $angelInfo = $userModel->getInfo($val['uid']);
                $data['user']['angel_info'] = isset($angelInfo['info']) ? $angelInfo['info'] :'';
                $result[$key] = $data;
            }
        }
        return $result;
    }
    public function getMoodLength($uid){
        $sql = 'select mood_length from user_info where uid=:uid';
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($rs['mood_length']) ? $rs['mood_length'] : 200;
    }
    public function getListForFeed($last_id,$size=20,$uid=0){
        $sql = "select id from mood where status <2 and share_type=0 and is_show=1 and is_public=2";
        if($uid) {
            $sql .= " and uid not in(SELECT f_uid FROM follow WHERE uid = :uid and status=1) and uid != :uid";
        }
        $field = $last_id ? " and id<$last_id" : '';
        $sql .= "$field order by add_time desc limit :size";
        $stmt = $this->db->prepare($sql);
        if($uid) {
            $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        }
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($data){
            $userModel  = new UserModel();
            $collectModel = new CollectModel();
            $feedModel = new FeedModel();
            $likeModel = new LikeModel();
            $messageModel = new MessageModel();
            $stageModel = new StageModel();
            foreach($data as $k=>$val){
                $info[$k] = $this->get($val['id']);
                $info[$k]['feed_type'] = 1;
                $info[$k]['unix_time'] = strtotime($info[$k]['add_time']);
                $info[$k]['add_time'] = Common::show_time($info[$k]['add_time']);
                //发布者信息
                $add_user = $userModel->getUserData($info[$k]['uid'],$uid);
                $angelInfo = $userModel->getInfo($add_user['uid']);
                $info[$k]['user']['angel_info'] = isset($angelInfo['info']) ? $angelInfo['info'] :'';
                $info[$k]['user']['uid'] = $add_user['uid'];
                $info[$k]['user']['did'] = $add_user['did'];
                $info[$k]['user']['nick_name'] = $add_user['nick_name'];
                $info[$k]['user']['avatar'] = $add_user['avatar'];
                $info[$k]['user']['type'] = $add_user['type'];
                $info[$k]['user']['relation'] = $add_user['relation'];
                $b_num = $stageModel->getSidByUid($add_user['uid']);
                if($b_num){
                    $info[$k]['user']['is_business']['num'] =1;
                    $info[$k]['user']['is_business']['sid'] =$b_num['sid'];
                }else{
                    $info[$k]['user']['is_business']['num'] =0;
                    $info[$k]['user']['is_business']['sid'] ='';
                }
                //是否喜欢
                $info[$k]['is_like'] = $likeModel->hasData(1,$val['id'],$uid);
                //是否收藏
                $info[$k]['is_collect'] = $collectModel->hasData(1,$val['id'],$uid);
                //评论列表
                $info[$k]['comment_list'] = $feedModel->getRedisCommentList($uid,1,$val['id']);
                //如果redis里没有评论，就重新获取存入redis
                if(!$info[$k]['comment_list']){
                    $commentList = $feedModel->getCommentListById(1,$val['id']);
                    $feedModel->addRedisComment('mood',$val['id'],$commentList);
                    $info[$k]['comment_list'] = $feedModel->getRedisCommentList($uid,1,$val['id']);
                }
                $info[$k]['comment_num'] = $feedModel->getCommentNum(1,$val['id']);
                //喜欢列表
                $info[$k]['like_list'] = $likeModel->likeList($val['id'],1,1,10,$uid);
                //打赏列表
                $info[$k]['reward_list'] = $messageModel->getRewardList(1,$val['id'],1,8);
                $comment_num  = $this->moodCommentNum($info[$k]['uid'],1,$val['id']);
                $like_num  = $this->moodLikeNum($info[$k]['uid'],1,$val['id']);
                if($comment_num>=5&&$like_num>=8){
                    $info[$k]['is_hot'] = 1;
                }else{
                    $info[$k]['is_hot'] = 0;
                }
            }
        }
        return $info;
    }
    //获取心境图片
    public function getImgByMoodId($mood_id){
        $stmt_img = $this->db->prepare("select id,img,width,height from mood_images where status<2 and mood_id=:mood_id order by sort asc,add_time desc");
        $array_img = array(
            ':mood_id'=>$mood_id
        );
        $stmt_img->execute($array_img);
        $rs =$stmt_img->fetchAll(PDO::FETCH_ASSOC);
        if($rs){
            foreach($rs as $k=>$v){
               $rs[$k]['show_img'] = Common::show_img($v['img'],0,450,450);
            }
        }
        return $rs;
    }

    //获去心境视屏
    public function getVideoByMoodId($mood_id){
        $stmt_video = $this->db->prepare("select id,url,title,img_large,img_small from mood_video where status<2 and mood_id=:mood_id");
        $array_video = array(
            ':mood_id'=>$mood_id,
        );
        $stmt_video->execute($array_video);
        return $stmt_video->fetchAll(PDO::FETCH_ASSOC);


    }
    /*
    * 查询心境评论数 主评
    */
    public function moodCommentNum($uid,$type,$obj_id){
        $stmt = $this->db->prepare("SELECT count(id) AS num  FROM `comment` WHERE  STATUS < 2 AND TYPE =:type  AND reply_uid =:reply_uid AND uid!=:uid  AND reply_id = 0 and obj_id=:obj_id");
        $array = array(
            ':type'=>$type,
            ':reply_uid'=>$uid,
            ':uid'=>$uid,
            ':obj_id'=>$obj_id
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];

    }

    /*
     * 查询心境点赞数
     */
    public function moodLikeNum($uid,$type,$obj_id){
        $stmt = $this->db->prepare("SELECT COUNT(id) AS num  FROM `like` WHERE obj_uid =:uid AND STATUS =1 AND TYPE =:type  AND uid !=:uid and obj_id=:obj_id");
        $array = array(
            ':obj_uid' => $uid,
            ':type'=>$type,
            ':uid'=>$uid,
            ':obj_id'=>$obj_id
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }
    public function getMoodByClientIdAndUid($client_id,$uid){
        $stmt = $this->db->prepare("SELECT id FROM `mood` WHERE client_id =:client_id and uid=:uid");
        $array = array(
            ':client_id' => $client_id,
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['id'] ? $rs['id'] : 0;
    }
}