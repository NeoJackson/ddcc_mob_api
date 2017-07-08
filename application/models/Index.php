<?php

class IndexModel{
    private $db;
    private $redis;
    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }
    //APP首页banner
    public function indexBanner($code,$token='',$version=''){
        $fields = $version ? ' version ="'.$version.'" and ' : '';
        $date_time = date("Y-m-d H:i:s");
        $stmt = $this->db->prepare("select id,title as name,url,img,start_time,end_time,version from ad where $fields status = 1 and
            start_time<=:date_time and end_time>:date_time and
        location_id = (select id from ad_location where code =:code and status=1) order by sort asc");
        $array=array(
            ':code'=>$code,
            ':date_time'=>$date_time
        );
        $stmt->execute($array);
        $rs = $stmt->fetchALL(PDO::FETCH_ASSOC);
        if($rs){
            foreach($rs as $k=>$v){
                $rs[$k]['img'] = IMG_DOMAIN.$v['img'];
                if($v['url']){
//                   $url_array = explode('/',$v['url']);
//                   $count = count($url_array);
//                   if($url_array[$count-2]=='u'){
//                       $url_array[$count-2] = 'user';
//                       $userModel = new UserModel();
//                       $user_info = $userModel->getUserByDid($url_array[$count-1]);
//                       $url_array[$count-1] = $user_info['uid'];
//                   }
//                    $v['url'] = implode("/",$url_array);
                   $rs[$k]['url'] = $token ? $v['url'].'?token='.$token : $v['url'];
                }
            }
        }
        return $rs;
    }
    //APP首页驿站推荐
    public function stagePush($page,$size,$version='3.0'){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select sid from app_stage where status=1 and type = 0 and sid in(select sid from stage where status=1 and type = 2)
        order by sort asc,add_time desc  limit :start,:size");
        $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
        $stmt->execute();
        $rs = $stmt->fetchALL(PDO::FETCH_ASSOC);
        $list = array();
        $stageModel = new StageModel();
        if($rs){
            foreach($rs as $val){
                $data = $stageModel->getIndexStageInfo($val['sid']);
                $data['icon'] = Common::show_img($data['icon'],4,140,140);
                if($version>'2.6.2'){
                    $addressModel = new AddressModel();
                    $data['city_name'] = $addressModel->getNameById($data['province']);
                    if(!in_array($data['city_name'],array('上海市','北京市','重庆市','天津市'))){
                        $data['city_name'] = $addressModel->getNameById($data['city']);
                    }
                }
                $list[] = $data;
            }
        }
        return $list;
    }

    //首页精选服务
    public function hotEventPush($page,$size,$uid=0,$token,$version,$type=0){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("SELECT tid,title,type,img FROM app_topic WHERE STATUS = 1 and type=:type ORDER BY sort ASC,add_time asc LIMIT :start,:size");
        $stmt->bindValue ( ':type' ,  $type ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
        $stmt->execute();
        $rs = $stmt->fetchALL(PDO::FETCH_ASSOC);
        $list = array();
        if($rs){
            $eventModel = new EventModel();
            foreach($rs as $v){
                $data = $eventModel->getBasicEvent($v['tid']);
                if($data){
                    $addressModel = new AddressModel();
                    $data['city_name'] = $addressModel->getNameById($data['province']);
                    if(!in_array($data['city_name'],array('上海市','北京市','重庆市','天津市'))){
                       $data['city_name'] = $addressModel->getNameById($data['city']);
                    }
                    $data['url'] = $token ? I_DOMAIN.'/e/'.$v['tid'].'?token='.$token.'&version='.$version:I_DOMAIN.'/e/'.$v['tid'].'?version='.$version;

                    $data['push_type'] = 10;
                    if($v['img']){
                        $data['push_img'] = $v['img'];
                    }else{
                        $data['push_img'] = $data['img_src'] ? $data['img_src'][0] : '';
                    }
                    $data['title'] = $v['title'];
                    $data['add_time'] = Common::show_time($data['add_time']);
                    $list[] = $data;
            }
            unset($data['img_src']);
            unset($data['img']);
            }
        }
        return $list;
    }
    //大家喜欢
    public function getLikeList($uid){
        $list = $this->getLike();
        $userModel = new UserModel();
        foreach($list as $key=>$val){
            $list[$key]['add_time'] = Common::show_time($val['add_time']);
            $list[$key]['user'] = $userModel->getUserData($val['uid'],$uid);
            $list[$key]['user']['avatar'] = Common::show_img($list[$key]['user']['avatar'],1,160,160);
            $topicInfo = $this->getTopicList($val['uid'],4);
            foreach($topicInfo as $k=>$v){
                $list[$key]['topicList'][$k]['id'] = $v['id'];
                $list[$key]['topicList'][$k]['img'] = $v['img_src']?Common::show_img($v['img_src']['0'],4,140,140):'';
            }
        }
        return $list;
    }

    //查询喜欢数据
    public function getLike(){
            $sql = "SELECT t.num,t.uid,t.add_time FROM (SELECT COUNT(*) AS num,uid,(SELECT add_time FROM `like` WHERE uid = lk.uid AND STATUS=1 AND TYPE=4 AND
            obj_id IN(SELECT id FROM topic WHERE STATUS<2 AND is_pic=1) ORDER BY add_time DESC LIMIT 1) AS add_time
            FROM `like` AS lk WHERE STATUS=1 AND TYPE=4 AND obj_id IN(SELECT id FROM topic WHERE STATUS<2 AND is_pic=1)
            AND DATE(add_time) = CURDATE() GROUP BY uid HAVING num>3 ORDER BY add_time DESC LIMIT 20 ) t ORDER BY RAND() LIMIT 1 ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    private $tid_arr = array();
    //查询用户的最新点赞的四条有图帖子
    public function getTopicList($uid,$size){
        $tid = '';
        if($this->tid_arr){
            $tid  =  implode ( ",", $this->tid_arr);
        }
        $condition = $this->tid_arr?' AND obj_id NOT IN ('.$tid.')':'';
        $sql = "SELECT obj_id FROM `like` WHERE STATUS=1 AND TYPE=4 AND obj_id IN(SELECT id FROM topic WHERE STATUS<2 AND is_pic=1)
        AND uid=:uid $condition ORDER BY add_time DESC limit :size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $topicModel = new TopicModel();
            foreach($result as $key=>$val){
                if(!in_array($val['obj_id'],$this->tid_arr)){
                    $this->tid_arr[] = $val['obj_id'];
                }
                $result[$key] = $topicModel->getBasicTopicById($val['obj_id']);
            }
        }
        return $result;
    }
    //根据登录时间查询文化天使和文化人数据
    public function getListByLoginTime($uid,$page,$size){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select uid,type from user where type >0 and status = 1 and is_show = 1 and uid!=$uid and uid NOT IN (select f_uid from
        follow where uid=$uid and status=1) order by login_time desc limit :start,:size");
        $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
        $stmt->execute();
        $data = $stmt->fetchALL(PDO::FETCH_ASSOC);

        $list = array();
        if($data){
            $userModel = new UserModel();
            foreach($data as $k=> $v){
                $userInfo = $userModel->getUserData($v['uid'],$uid);
                if($v['type']>1){
                     $angelInfo = $this->getAngelInfoByUid($v['uid']);
                     $list[$k]['uid'] = $angelInfo['uid'];
                     $list[$k]['real_name'] = $angelInfo['real_name'];
                     $list[$k]['real_photo'] = Common::show_img($angelInfo['real_photo'],1,160,160);
                     $list[$k]['info'] = $angelInfo['info'];
                }elseif($v['type']==1){
                     $list[$k]['uid'] = $userInfo['uid'];
                     $list[$k]['real_name'] = $userInfo['nick_name'];
                     $list[$k]['real_photo'] = Common::show_img($userInfo['avatar'],1,160,160);
                     $list[$k]['info'] = $userInfo['intro'];
                }
                $list[$k]['type'] = $v['type'];
                $list[$k]['sex'] = $userInfo['sex'];
                $list[$k]['att_num'] = $userInfo['att_num'];
                $list[$k]['fans_num'] = $userInfo['fans_num'];
                $list[$k]['stage_num'] = $userInfo['stage_num'];
                $list[$k]['relation'] = $userInfo['relation'];
                $list[$k]['self'] = $userInfo['self'];
                $mood = $this->getNewMood($v['uid'],$uid);
                if($mood){
                    $mood['content'] = Common::showEmoticon($mood['content'],1);
                    $list[$k]['mood'] = $mood;
                }else{
                    $list[$k]['mood'] = (object)array();
                }
            }
        }
        return $list;
    }
    //根据登录时间查询文化天使和文化人数据
    public function getAngelByLoginTime($uid,$page,$size){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select uid,type from user where type >1 and status = 1 and uid!=$uid and uid NOT IN (select f_uid from
        follow where uid=$uid and status=1) order by login_time desc limit :start,:size");
        $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
        $stmt->execute();
        $data = $stmt->fetchALL(PDO::FETCH_ASSOC);

        $list = array();
        if($data){
            $userModel = new UserModel();
            foreach($data as $k=> $v){
                $userInfo = $userModel->getUserData($v['uid'],$uid);
                $angelInfo = $this->getAngelInfoByUid($v['uid']);
                $list[$k]['uid'] = $angelInfo['uid'];
                $list[$k]['real_name'] = $angelInfo['real_name'];
                $list[$k]['real_photo'] = Common::show_img($angelInfo['real_photo'],1,160,160);
                $list[$k]['info'] = $angelInfo['info'];
                $list[$k]['type'] = $v['type'];
                $list[$k]['sex'] = $userInfo['sex'];
                $list[$k]['att_num'] = $userInfo['att_num'];
                $list[$k]['fans_num'] = $userInfo['fans_num'];
                $list[$k]['stage_num'] = $userInfo['stage_num'];
                $list[$k]['relation'] = $userInfo['relation'];
                $list[$k]['self'] = $userInfo['self'];
                $mood = $this->getNewMood($v['uid'],$uid);
                if($mood){
                    $mood['content'] = Common::showEmoticon($mood['content'],1);
                    $list[$k]['mood'] = $mood;
                }else{
                    $list[$k]['mood'] = (object)array();
                }
            }
        }
        return $list;
    }
    //根据uid查询文化天使信息
    public function getAngelInfoByUid($uid){
        $stmt = $this->db->prepare("SELECT uid,real_name,real_photo,info FROM user_angel WHERE uid=:uid ");
        $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
        $stmt->execute();
        $list = $stmt->fetch(PDO::FETCH_ASSOC);
        return $list;
    }
    //根据uid 获取最新的一条无视频心境
    public function getNewMood($uid,$f_uid){
        $sql = "SELECT id,content,is_img,add_time,like_num,comment_num FROM mood WHERE uid = :uid AND STATUS < 2 AND is_video = 0 ORDER BY add_time DESC LIMIT 1 ";
        $array=array(
            ':uid'=>$uid
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result){
            $likeModel = new LikeModel();
            $result['is_like'] = $likeModel->hasData(1,$result['id'],$f_uid);
            $result['type'] = 1;
        }
        return $result;
    }
    //首页文化天使随机换一组
    public function getIndexAngel($uid,$page,$size){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select uid from `user` where type >0 and status = 1 and is_show= 1 and uid !=:uid and uid NOT IN (select f_uid from
        follow where uid=:uid and status=1) order by login_time desc limit :start,:size");
        $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $userModel = new UserModel();
            foreach($result as $k=>$v){
                $angelInfo = $userModel->getUserData($v['uid']);
                if($angelInfo['type']==1){
                    $result[$k]['uid'] = $angelInfo['uid'];
                    $result[$k]['real_name'] = $angelInfo['nick_name'];
                    $result[$k]['type'] = $angelInfo['type'];
                    $result[$k]['real_photo'] = Common::show_img($angelInfo['avatar'],1,320,320);
                    $result[$k]['info'] = $angelInfo['intro'];
                    $result[$k]['relation'] = $angelInfo['relation'];
                }elseif($angelInfo['type']>1){
                    $info = $this->getAngelInfoByUid($angelInfo['uid']);
                    $result[$k]['uid'] = $info['uid'];
                    $result[$k]['real_name'] = $info['real_name'];
                    $result[$k]['type'] = $angelInfo['type'];
                    $result[$k]['real_photo'] = Common::show_img($info['real_photo'],1,320,320);
                    $result[$k]['info'] = $info['info'];
                    $result[$k]['relation'] = $angelInfo['relation'];
                }
            }
        }
        return $result;
    }
    //场馆驿站列表
    public function getSiteList($lat,$lng,$uid,$page,$size){
        //get_distance(:lat,:lng,lat,lng) as distance
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("select sid from app_stage where status=1 and type = 1 and sid in(select sid from stage where status=1)
        order by sort asc,add_time desc  limit :start,:size");
        $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
        $stmt->execute();
        $rs = $stmt->fetchALL(PDO::FETCH_ASSOC);
        $list = array();
        $stageModel = new StageModel();
        if($rs){
            foreach($rs as $val){
                $data = $stageModel->getIndexStageInfo($val['sid']);
                $data['icon'] = Common::show_img($data['icon'],4,140,140);
                $data['is_join'] = $stageModel->getJoinStage($val['sid'],$uid);
                $cateInfo = $stageModel->getCultureCateById($data['cate_id']);
                if($cateInfo){
                    $data['cate_name'] = $cateInfo['name'];
                }else{
                    $data['cate_name'] = '';
                }
                $info = $stageModel->getSiteInfo($val['sid'],$lat,$lng);
                $data['stage_address'] = $info['stage_address'];
                $data['range_info'] = Common::showRange($info['distance']);
                $list[] = $data;
            }
        }
        return $list;
    }

    //优秀图贴
    public function getGoodTopicList($start,$size,$uid=0,$token,$version){
        $redisKey = 'good:topic:'.$start.':'.$size;
        $result = $this->redis->get($redisKey);
        if($result) {
            $result = json_decode($result,true);
        } else {
            $stmt = $this->db->prepare("select tid as id,add_time,title,description,img,sort from topic_push
            where status=1 and tid in(select id from topic where status<2) order by sort limit :start,:size");
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);
            $stmt->bindValue(':size', $size, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $data = array();
        if($result){
            $topicModel = new TopicModel();
            $stageModel = new StageModel();
            foreach($result as $key=>$val){
                $data[$key] = $topicModel->getIndexTopicById($val['id']);
                $data[$key]['title'] = $val['title'] ? $val['title'] : $data[$key]['title'];
                $data[$key]['lng'] = '';
                $data[$key]['lat'] ='';
                $data[$key]['city_name'] = '';
                $data[$key]['push_type'] =4;
                if(isset($val['img']) && $val['img']){
                    $img = $val['img'];
                    $app_img = $val['img'];
                }else{
                    if($data[$key]['img_src']){
                        $show_img = explode('/',$data[$key]['img_src'][0]);
                        $img =$show_img[3].'?imageMogr2/thumbnail/!300x160r/gravity/North/crop/300x160';
                        $app_img = $show_img[3];
                    }else{
                        $img = '';
                        $app_img ='';
                    }
                }
                if($data[$key]['img_src']){
                    foreach($data[$key]['img_src'] as $k1=>$v1){
                        $data[$key]['img_src'][$k1] = $v1.'?imageMogr2/thumbnail/!300x160r/gravity/North/crop/300x160';
                    }
                }
                $likeModel = new LikeModel();
                $data[$key]['is_like'] = $likeModel->hasData(4,$val['id'],$uid);
                $data[$key]['push_img'] = $img;
                $data[$key]['app_img'] = $app_img;
                $data[$key]['add_time'] = Common::show_time($data[$key]['add_time']);
                $data[$key]['url'] = $token ? I_DOMAIN.'/t/'.$val['id'].'?token='.$token.'&version='.$version :I_DOMAIN.'/t/'.$val['id'].'?version='.$version;
                $stageInfo = $stageModel->getBasicStageBySid($data[$key]['sid']);
                $data[$key]['stage_name'] = $stageInfo['name'];
            }
        }
        $this->redis->setEx($redisKey,50,json_encode($result));
        return $data;
    }
    //首页精选服务
    public function eventPush($page,$size,$uid,$token,$version){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare("SELECT tid,title,type,img FROM app_topic WHERE STATUS = 1 and type=1 ORDER BY sort ASC,add_time asc LIMIT :start,:size");
        $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
        $stmt->execute();
        $rs = $stmt->fetchALL(PDO::FETCH_ASSOC);
        $list = $data = $result = array();
        if($rs){
            $eventModel = new EventModel();
            $addressModel = new AddressModel();
            $stageModel = new StageModel();
            foreach($rs as $v){
                $info = $eventModel->getEventRedisById($v['tid']);
                $data['id'] = $info['id'];
                $data['type'] = $info['type'];
                $data['show_start_time'] = Common::getEventStartTime($info['id']);
                $province_name = $info['province'] ? $addressModel->getNameById($info['province']) : '';
                $city_name = $info['city'] ? $addressModel->getNameById($info['city']) : '';
                $data['address_name'] = $province_name.$city_name;
                $data['url'] = $token ? I_DOMAIN.'/e/'.$info['id'].'?token='.$token.'&version='.$version:I_DOMAIN.'/e/'.$info['id'].'?version='.$version;
                if($v['img']){
                    $data['push_img'] = $v['img'];
                }else{
                    $data['push_img'] = $info['img_src'] ? $info['img_src'][0] : '';
                }
                $data['title'] = $v['title'];
                $data['add_time'] = Common::show_time($info['add_time']);
                if($info['type']!=1){
                    $type_info = Common::eventType($info['type']);
                }else{
                    $type_info = $eventModel->getBusinessEventType($info['type_code']);
                }
                $data['type_name'] = $type_info['name'];
                $data['code_name'] = $type_info['code'];
                $data['sid'] = $info['sid'];
                $stageInfo = $stageModel->getStage($data['sid']);
                $data['stage_name'] = $stageInfo['name'];
                $data['icon'] = $stageInfo['icon'];
                if($info['price_type']==1){
                    $data['price'] = '免费';
                    $data['price_count']=1;
                }else{
                    $price = $eventModel->getPrice($info['id']);
                    $data['price'] = $price[0]['unit_price'];
                    $data['price_count']=count($price);
                }
                $collectModel = new CollectModel();
                $data['is_collect'] = $collectModel->hasData(10,$info['id'],$uid);
                $list[] = $data;
            }
        }
        return $list;
    }
    public function getUserList($uid,$size){
        $stmt = $this->db->prepare("select uid,type from user where status = 1 and is_show = 1 and uid!=:uid and uid NOT IN (select f_uid from
        follow where uid=:uid and status=1) and DATE_SUB(CURDATE(), INTERVAL 60 DAY) <= DATE(`login_time`) and uid in (select uid from user_tag where status = 1 ) ORDER BY RAND() limit :size");
        $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
        $stmt->execute();
        $data = $stmt->fetchALL(PDO::FETCH_ASSOC);
        if($data){
            foreach($data as $k =>$v){
                $userModel = new UserModel();
                $userInfo = $userModel->getUserData($v['uid'],$uid);
                $data[$k]['avatar'] = $userInfo['avatar'];
                $data[$k]['nick_name'] = $userInfo['nick_name'];
                $data[$k]['fans_num'] = $userInfo['fans_num'];
                $data[$k]['att_num'] = $userInfo['att_num'];
                $data[$k]['relation'] = $userInfo['relation'];
                $data[$k]['user_address'] = $userInfo['province'];
                $data[$k]['intro'] = $userInfo['intro'];
                $home_cover = $userModel->getUserInfoByUid($userInfo['uid']);
                $data[$k]['cover']  = $home_cover['home_cover'] ? Common::show_img($home_cover['home_cover'],4,775,450): PUBLIC_DOMAIN.'default_app_home.jpg';
                $tagModel = new TagModel();
                $data[$k]['tag'] = $tagModel->getRelation(1,$v['uid']);
            }
        }
        return $data;
    }

    //3.7版本首页专栏-人物专题
    public function getPersonSpecial($uid){
        $stmt = $this->db->prepare("select uid,nick_name,avatar,type from user where uid = :uid");//172460代代号1162837
        $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $followModel = new FollowModel();
        $result['att_num'] = $followModel->getAttNum($result['uid']);
        $result['fans_num'] = $followModel->getFansNum($result['uid']);
        switch($result['type']){
            case 1:
                $result['ico_type'] = 'angel literacy';
                break;
            case 2:
                $result['ico_type'] = 'angel first';
                break;
            case 3:
                $result['ico_type'] = 'angel second';
                break;
            case 4:
                $result['ico_type'] = 'angel third';
                break;
            case 5:
                $result['ico_type'] = 'angel fourth';
                break;
            default:
                $result['ico_type'] = '';
        }
        return $result;
    }

    //3.7版本首页专栏-武术专题
    public function getMartialSpecialList($sidStr){
        $stmt = $this->db->prepare("select sid,uid,name,intro,icon,cover,topic_num,user_num from stage where sid in($sidStr) order by sid desc");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    //优秀图贴
    public function getGoodTopicForIndex($size,$uid=0,$token,$version){
        $stmt = $this->db->prepare("select tid as id,add_time,title,description,img,sort from topic_push
        where status=1 AND sort < 5 order by rand() limit :size");
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data = array();
        if($result){
            $topicModel = new TopicModel();
            $stageModel = new StageModel();
            $likeModel = new LikeModel();
            foreach($result as $key=>$val){
                $data[$key] = $topicModel->getTopicRedisById($val['id']);
                $img_arr = Common::pregMatchImg($data[$key]['content']);
                $data[$key]['img_src'] = $img_arr ? $img_arr[3] : array();
                $data[$key]['title'] = $val['title'] ? $val['title'] : $data[$key]['title'];
                $data[$key]['lng'] = '';
                $data[$key]['lat'] ='';
                $data[$key]['city_name'] = '';
                $data[$key]['push_type'] =4;
                if(isset($val['img']) && $val['img']){
                    $img = $val['img'];
                    $app_img = $val['img'];
                }else{
                    if($data[$key]['img_src']){
                        $show_img = explode('/',$data[$key]['img_src'][0]);
                        $img =$show_img[3].'?imageMogr2/thumbnail/!300x160r/gravity/North/crop/300x160';
                        $app_img = $show_img[3];
                    }else{
                        $img = '';
                        $app_img ='';
                    }
                }
                $data[$key]['is_like'] = $likeModel->hasData(4,$val['id'],$uid);
                $data[$key]['push_img'] = $img;
                $data[$key]['app_img'] = $app_img;
                $data[$key]['add_time'] = Common::show_time($data[$key]['add_time']);
                $data[$key]['url'] = $token ? I_DOMAIN.'/t/'.$val['id'].'?token='.$token.'&version='.$version :I_DOMAIN.'/t/'.$val['id'].'?version='.$version;
                $stageInfo = $stageModel->getBasicStageBySid($data[$key]['sid']);
                $data[$key]['stage_name'] = $stageInfo['name'];
            }
        }
        return $data;
    }
    //查询专栏
    public function getColumnList($type,$size){
        $redisKey = 'app:index:zl';
        $result = $this->redis->get($redisKey);
        if($result) {
            $result = json_decode($result,true);
        }else{
            $stmt = $this->db->prepare("select * from app_column where type=:type and status = 1 order by sort asc ,id desc limit :size ");
            $stmt->bindValue ( ':type' ,  $type ,  PDO :: PARAM_INT );
            $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->redis->set($redisKey,json_encode($result));
        }
        return $result;
    }

    //根据id查询专栏信息
    public function getColumnById($id){
        $stmt = $this->db->prepare("select id, name, intro, content, cate, cover, url,status from app_column where id=:id ");
        $stmt->bindValue ( ':id' ,  $id ,  PDO :: PARAM_INT );
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    //根据类型查询往期回顾专栏
    public function getReviewListByCate($cate,$id){
        $stmt = $this->db->prepare("select id, name, intro, content, cate, review_cover, cover, url from app_column
        where status=1 and is_review=1 and cate=:cate and id!=:id order by sort asc ,add_time desc ");
        $stmt->bindValue ( ':cate' ,  $cate ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':id' ,  $id ,  PDO :: PARAM_INT );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //根据url查询专栏信息
    public function getColumnByUrl($url){
        $stmt = $this->db->prepare("select id, name, intro, content, cate, cover, url,status from app_column where url=:url and status = 1 ");
        $stmt->bindValue ( ':url' ,  $url ,  PDO :: PARAM_INT );
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}