<?php
class FeedController extends Yaf_Controller_Abstract {
    private $comment_type_arr = array(1,2,3,4,9,10,12);
    public function init(){
        $this->startTime = microtime(true);
    }
    //时间处理
    public function timeContent($time){
        if(date('Y-m-d',$time) == date("Y-m-d")){
            $new_date = '今天';
        }elseif(date('Y-m-d',$time) == date('Y-m-d',strtotime('-1 day'))){
            $new_date = '昨天';
        }else{
            $new_date = date('d',$time).' '.date('m',$time).'月';
        }
        return $new_date;
    }
    //分享
    public function addShareAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(6, "非法登录用户");
        }
        $content = ltrim($this->getRequest()->getPost('content'));
        $share_type = (int)$this->getRequest()->getPost('share_type');
        $share_id = (int)$this->getRequest()->getPost('share_id');
        $shared_id = (int)$this->getRequest()->getPost('shared_id');//分享的分享id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        echo Common::http(OPEN_DOMAIN.'/feedapi/addShare',array('uid'=>$user['uid'],'content'=>$content,'share_type'=>$share_type,'share_id'=>$share_id,'shared_id'=>$shared_id),'POST');
        Common::appLog('feed/addShare',$this->startTime,$version);
    }

    //添加收藏
    public function addCollectAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $id = (int)$this->getRequest()->getPost('id');
        $type = (int)$this->getRequest()->getPost('type');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $collectModel = new CollectModel();
        $rs = $collectModel->add($type,$id,$user['uid']);
        if($rs == -1){
            Common::echoAjaxJson(2,"收藏的内容类型不正确");
        }
        if($rs == -2){
            Common::echoAjaxJson(3,"收藏的内容不存在");
        }
        if($rs == -3){
            Common::echoAjaxJson(4,"请勿重复收藏");
        }
        Common::appLog('feed/addCollect',$this->startTime,$version);
        Common::echoAjaxJson(1,"收藏成功");
    }
    //取消收藏
    public function delCollectAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $type = (int)$this->getRequest()->getPost('type');
        $id = (int)$this->getRequest()->getPost('id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $collectModel = new CollectModel();
        $rs = $collectModel->del($type,$id,$user['uid']);
        if($rs == 0){
            Common::echoAjaxJson(2,"您还没有收藏该内容");
        }
        Common::appLog('feed/delCollect',$this->startTime,$version);
        Common::echoAjaxJson(1,"取消成功");
    }

    /**
     * 添加评论信息
     */
    public function addCommentAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(10, "非法登录用户");
        }
        $uid = $user['uid'];
        $type = $this->getRequest()->getPost('type');
        $obj_id = $this->getRequest()->getPost('obj_id');
        $reply_uid = $this->getRequest()->getPost('reply_uid');
        $content = $this->getRequest()->getPost('content');
        $reply_id = $this->getRequest()->getPost('reply_id');
        $is_share = $this->getRequest()->getPost('is_share');
        $share_type = $this->getRequest()->getPost('share_type');
        $share_id = $this->getRequest()->getPost('share_id');
        $shared_id = (int)$this->getRequest()->getPost('shared_id');//分享的分享id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $message = $reply_id?'回复':'评论';
        //回复评论 根据reply_id 去查询type和obj_id
        $feedModel = new FeedModel();
        if($reply_id){
            $c_info = $feedModel->getCommentInfo($reply_id);
            $type = $c_info['type'];
            $obj_id = $c_info['obj_id'];
        }
        if(!in_array($type,$this->comment_type_arr) || $type == ''){
            Common::echoAjaxJson(2,'评论类型不正确');
        }
        if(!$obj_id){
            Common::echoAjaxJson(3,'评论对象不能为空');
        }
        if(!$reply_uid){
            Common::echoAjaxJson(4,$message.'的用户不能为空');
        }
        if(strlen(ltrim($content)) == 0){
            Common::echoAjaxJson(5,$message.'内容不能为空');
        }
        $security = new Security();
        $content = $security->xss_clean($content);
        $content = strip_tags($content,"");
        $content = Common::angleToHalf($content);
        $content = Common::contentReplace($content);
        if($content === false){
            Common::echoAjaxJson(6,'评论内容不能包含英文字符');
        }
        if(Common::utf8_strlen(ltrim($content)) > 200){
            Common::echoAjaxJson(7,$message.'内容为1-200个字符');
        }
        $reply_id = $reply_id?$reply_id:0;
        if(!$reply_id){
            $reply_id = 0;
        }
        $data = array();
        if($is_share){
            switch($share_type){
                case 1:
                    $moodModel = new MoodModel();
                    $data = $moodModel->get($share_id);
                    break;
                case 4:
                    $topicModel = new TopicModel();
                    $data = $topicModel->getBasicTopicById($share_id);
                    break;
                case 10:
                    $eventModel = new EventModel();
                    $data = $eventModel->getEvent($share_id);
                    break;
                case 12:
                    $stagegoodsModel  = new StagegoodsModel();
                    $data = $stagegoodsModel->getInfo($share_id);
                    break;
            }
            if(!$data){
                Common::echoAjaxJson(10,'被分享的内容不存在');
            }
        }
        $rs = $feedModel->addComment($type,$obj_id,$uid,$content,$reply_uid,$reply_id,$is_share,$share_type,$share_id,$shared_id,isset($data['uid'])?$data['uid']:0);
        if($type==4){
            $stageMangerModel=new StageManagerModel();
            $topicModel = new TopicModel();
            $data = $topicModel->getBasicTopicById($obj_id);
            $stageMangerModel->addCommentNum($data['sid']);
        }
        if($rs == -1){
            Common::echoAjaxJson(8,'该评论对象已不存在');
        }
        if($rs == 0){
            Common::echoAjaxJson(9,$message.'失败');
        }
        $feedModel = new FeedModel();
        $list = $feedModel->getCommentList($user['uid'],$type,$obj_id,1,10);
        $obj_info = $feedModel->getDataUidByTypeAndId($type,$obj_id,0);
        $push_message = '才府文化圈有一条新评论';
        if($obj_info['uid']==$reply_uid&&$uid!=$obj_info['uid']){
            $feedModel->initJpushComment($obj_info['uid'],$push_message);
        }elseif($obj_info['uid']!=$reply_uid&&$uid!=$obj_info['uid']&&$uid!=$reply_uid){
            $feedModel->initJpushComment($obj_info['uid'],$push_message);
            $feedModel->initJpushComment($reply_uid,$push_message);
        }
        Common::appLog('feed/addComment',$this->startTime,$version);
        Common::echoAjaxJson(1,$message.'成功',$list);
    }
    /**
     * 获取对应对象的评论列表
     */
    public function getCommentListAction(){
        $token = $this->getRequest()->get('token');
        $uid = 0;
        if($token){
            $user = Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(4, "非法登录用户");
            }
            $uid = $user['uid'];
        }

        $type = $this->getRequest()->get('type');
        $obj_id = $this->getRequest()->get('obj_id');
        $page = intval($this->getRequest()->get('page'));
        $size = intval($this->getRequest()->get('size'));
        $flag = intval($this->getRequest()->get('flag'));
        $last_time = $this->getRequest()->get('last_time');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $size = $size ? $size : 5;
        if(!in_array($type,$this->comment_type_arr) || $type == ''){
            Common::echoAjaxJson(2,'评论类型不正确');
        }
        if(!$obj_id){
            Common::echoAjaxJson(3,'评论对象不能为空');
        }
        $feedModel = new FeedModel();
        if($page){
            $rs = $feedModel->getCommentList($uid,$type,$obj_id,$page,$size,$flag);
        }else{
            $rs = $feedModel->getCommentListByLastTime($uid,$type,$obj_id,$last_time,$size,$flag);
        }
        Common::appLog('feed/getCommentList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$rs ? $rs :(object)array());
    }

    /**
     * 删除对应对象的评论
     */
    public function delCommentAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(6, "非法登录用户");
        }
        $id = $this->getRequest()->getPost('id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2,'删除的评论不能为空');
        }
        $feedModel = new FeedModel();
        $rs = $feedModel->delComment($id,$user['uid']);
        if($rs == -1){
            Common::echoAjaxJson(3,'该评论已不存在');
        }
        if($rs == -2){
            Common::echoAjaxJson(4,'您没有权限删除该评论');
        }
        if($rs == 0){
            Common::echoAjaxJson(5,'删除失败');
        }
        Common::appLog('feed/delComment',$this->startTime,$version);
        Common::echoAjaxJson(1,'删除成功');
    }
    /*
     * 获取最新动态数量
     */
    public function getLastNumAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $last_time = (int)$this->getRequest()->get('last_time');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$last_time){
            $last_time = time()-30;
        }
        $feedModel = new FeedModel();
        $list = $feedModel->getLastAppNum($user['uid'],$last_time);
        $avatar  = '';
        $lastTime = '';
        if($list>0){
            $feeds = $feedModel->getAttAppList($user['uid'],0,1);
            $appData = $feedModel->getAppData($feeds['list'],$user['uid'],$version,$_POST['token']);
            $lastTime = $appData[0]['update_time'];
            $avatar =Common::show_img($appData[0]['user']['avatar'],1,160,160);
        }
        $data = array(
            'new_num' => $list,
            'avatar'=>$avatar,
            'last_time'=>$lastTime
        );
        Common::appLog('feed/getLastNum',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$data);
    }
    /*
     * 获取最新动态数量
     */
    public function getLastNumNewAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $last_time = (int)$this->getRequest()->get('last_time');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$last_time){
            $last_time = time()-30;
        }
        $feedModel = new FeedModel();
        $list = $feedModel->getLastAppNumNew($user['uid'],$last_time);
        $avatar  = '';
        $lastTime = '';
        if($list>0){
            $feeds = $feedModel->getAttList($user['uid'],0,1);
            $appData = $feedModel->getData('follow',$feeds['list'],0,$user['uid'],0);
            $lastTime = $appData[0]['last_time'];
            $avatar =Common::show_img($appData[0]['user']['avatar'],1,160,160);
        }
        $data = array(
            'new_num' => $list,
            'avatar'=>$avatar,
            'last_time'=>$lastTime
        );
        Common::appLog('feed/getLastNumNew',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$data);
    }

    //删除书房动态
    public function delFeedAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $type = (int)$this->getRequest()->getPost('type');
        $id = (int)$this->getRequest()->getPost('id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $add_time = (int)$this->getRequest()->getPost('last_time');
        echo Common::http(OPEN_DOMAIN.'/common/delFeed',array('uid'=>$user['uid'],'type'=>$type,'id'=>$id,'add_time'=>$add_time),'POST');
    }
    //动态评论列表主次评查询列表
    public function getDynamicCommentListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $uid = $user['uid'];
        $type = (int)$this->getRequest()->get('type');
        $obj_id = (int)$this->getRequest()->get('id');
        $size = intval($this->getRequest()->get('size'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$size || $size<1){
            $size = 5;
        }
        if(!in_array($type,$this->comment_type_arr) || $type == ''){
            Common::echoAjaxJson(2,'评论类型不正确');
        }
        if(!$obj_id){
            Common::echoAjaxJson(3,'评论对象不能为空');
        }
        $feedModel = new FeedModel();
        //先组合主次评
        $new_list = $feedModel->getCommentListById($type,$obj_id,$size);
        //再查询评论内容
        if($new_list){
            $userModel = new UserModel();
            foreach($new_list as $key=>$val){
                $content = Common::linkReplace($val['content']);
                $new_list[$key]['content'] = Common::showEmoticon($content,0);
                $new_list[$key]['add_time'] = Common::show_time($val['add_time']);
                $new_list[$key]['user'] = $userModel->getUserData($val['uid'],$uid);
                if($new_list[$key]['reply_id']){
                    $new_list[$key]['reply_user'] = $userModel->getUserData($val['reply_uid'],$uid);
                }
                $new_list[$key]['is_delete'] = $feedModel->isDeleteComment($uid,$type,$obj_id,$val);
            }
        }
        Common::appLog('feed/getDynamicCommentList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',array('comment_list'=>$new_list));
    }
    //3.7文化圈
    public function getListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $last = intval($this->getRequest()->getPost('last'));
        $size = 10;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $feedModel = new FeedModel();
        $userModel = new UserModel();
        $user_info =$userModel->getUserData($user['uid']);
        $feeds = $feedModel->getAttList($user['uid'],$last,$size);
        $list = array();
        $is_feed=1;
        if($version>'3.7.1'){
            if($feeds['size']==0){//用户文化圈动态数=0，取推荐用户的心境数据
                $list = $feedModel->getMoodList($user['uid'],$last,$size);
                $feeds['size'] = $feedModel->getMoodNum($user['uid']);
                $is_feed =0;
            }else{
                if($feeds['list']){
                    $list = $feedModel->getData('follow',$feeds['list'],0,$user['uid'],1,$version,$_POST['token']);
                }
            }
        }else{
            if($feeds['list']){
                $list = $feedModel->getData('follow',$feeds['list'],0,$user['uid'],1,$version,$_POST['token']);
            }
        }
        $home_cover = $userModel->getUserInfoByUid($user_info['uid']);
        $user_info['avatar'] = Common::show_img($user_info['avatar'],1,200,200);
        $user_info['home_cover'] = $home_cover['home_cover'] ? IMG_DOMAIN.$home_cover['home_cover'] : PUBLIC_DOMAIN.'default_feed_cover.jpg';
        $user_info['home_cover_id'] = $home_cover['home_cover'] ? $userModel->getCoverId($home_cover['home_cover']) : '';
        $data = array(
            'is_feed'=>$is_feed,
            'list'=>$list,
            'size'=>$feeds['size'],
            'user_info'=>$user_info
        );
        Common::appLog('feed/getList',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$data);
    }
    public function getUnFollowAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $last = intval($this->getRequest()->getPost('last'));
        $size = 10;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $feedModel = new FeedModel();
        $userModel = new UserModel();
        $user_info =$userModel->getUserData($user['uid']);
        $list = $feedModel->getMoodList($user['uid'],$last,$size);
        $feeds['size'] = $feedModel->getMoodNum($user['uid']);
        $is_feed =0;
        $home_cover = $userModel->getUserInfoByUid($user_info['uid']);
        $user_info['avatar'] = Common::show_img($user_info['avatar'],1,200,200);
        $user_info['home_cover'] = $home_cover['home_cover'] ? IMG_DOMAIN.$home_cover['home_cover'] : PUBLIC_DOMAIN.'default_feed_cover.jpg';
        $user_info['home_cover_id'] = $home_cover['home_cover'] ? $userModel->getCoverId($home_cover['home_cover']) : '';
        $data = array(
            'is_feed'=>$is_feed,
            'list'=>$list,
            'size'=>$feeds['size'],
            'user_info'=>$user_info
        );
        Common::appLog('feed/getUnFollow',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$data);
    }
    //3.7个人文化圈
    public function getUserListAction(){
        $token = $this->getRequest()->getPost('token');
        $user['uid'] = 0;
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
        }
        $uid  = intval($this->getRequest()->getPost('uid'));
        $last = intval($this->getRequest()->getPost('last'));
        $size = 20;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $feedModel = new FeedModel();
        $userModel = new UserModel();
        $followModel = new FollowModel();
        $userData = $userModel->getUserByUid($uid);
        if($uid == $user['uid']){
            $flag = 1;
        }else{
            $flag = 0;
        }
        if(!$userData){
            Common::echoAjaxJson(909, "该用户不存在");
        }else{
            $feeds = $feedModel->getUserList($uid,$last,$size);
            $list =$feedModel->getData('user',$feeds['list'],0,$user['uid'],$flag,$version,$token);
            $userInfo =$userModel->getUserData($uid,$user['uid']);
            $user_info['uid'] = $userInfo['uid'];
            $user_info['did'] = $userInfo['did'];
            $user_info['nick_name'] = $userInfo['nick_name'];
            $user_info['avatar'] = $userInfo['avatar'];
            $user_info['self'] = $userInfo['self'];
            $user_info['ico_type'] = $userInfo['ico_type'];
            $user_info['relation'] = $userInfo['relation'];
            $user_info['type'] = $userInfo['type'];
            $user_info['fans_num'] = $userInfo['fans_num'];
            $user_info['att_num'] = $userInfo['att_num'];
            $user_info['sex'] = $userInfo['sex'];
            $user_info['intro'] = $userInfo['intro'];
            $u_info = $userModel->getUserByUid($uid);
            $user_info['qrcode_img'] = $u_info['qrcode_img'];
            if($userInfo['type']>1){
                $indexModel = new IndexModel();
                $info = $indexModel->getAngelInfoByUid($userInfo['uid']);
                $user_info['intro'] = $info['info'];
            }
            $home_cover = $userModel->getUserInfoByUid($userInfo['uid']);
            $user_info['avatar'] = Common::show_img($userInfo['avatar'],1,200,200);
            $user_info['original_avatar'] = $userInfo['avatar'];
            if($user['uid'] ==$userInfo['uid']){
                $user_info['home_cover'] = $home_cover['home_cover'] ? IMG_DOMAIN.$home_cover['home_cover']: PUBLIC_DOMAIN.'default_app_cover.jpg';
                $user_info['home_cover_id'] = $home_cover['home_cover'] ? $userModel->getCoverId($home_cover['home_cover']) : '';
            }else{
                $user_info['home_cover'] = $home_cover['home_cover'] ? IMG_DOMAIN.$home_cover['home_cover']: PUBLIC_DOMAIN.'default_app_home.jpg';
                $user_info['home_cover_id'] = $home_cover['home_cover'] ? $userModel->getCoverId($home_cover['home_cover']) : '';;
                $g_id = $followModel->getGroupByUid($user['uid'],$userInfo['uid']);
                $user_info['in_group_id'] = $g_id['group_id'];
            }
            $data = array(
                'list'=>$list,
                'size'=>$feeds['size'],
                'user_info'=>$user_info
            );
            //访客记录
            if($user['uid'] != $uid){
                $visitModel = new VisitModel();
                $visitModel->add('home',$uid,$user['uid']);
                $visitModel->add('user',$user['uid'],$uid);
            }
        }
        Common::appLog('feed/getUserList',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$data);
    }

}