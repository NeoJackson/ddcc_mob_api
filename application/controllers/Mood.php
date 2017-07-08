<?php
class MoodController extends Yaf_Controller_Abstract {

    public function init(){
        $this->startTime = microtime(true);
    }
    //心境详情
    public function infoAction(){
        $data['token'] = $this->getRequest()->get('token');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid=$user['uid'];
        }
        $id = (int)$this->getRequest()->getPost('id');//心境id
        if(!$id ||$id < 0){
            Common::echoAjaxJson(2, "心境id不能为空");
        }
        $moodModel = new MoodModel();
        $feedModel = new FeedModel();
        $userModel = new UserModel();
        $stageModel = new StageModel();
        $data= $moodModel->getMood($id);
        if(!$data){
            Common::echoAjaxJson(4, "该心境已被删除");
        }
        if($data['uid']!=$uid&&$data['is_public']==1){
           Common::echoAjaxJson(5, "该心境为用户私密心境，您无法查看");
        }
        $info =$moodModel->getExtInfo($data);
        $info['content'] = Common::showEmoticon($info['content'],1);
        $info['add_time'] = Common::show_time($info['add_time']);
        $info['user_info'] = $userModel->getUserData($data['uid'],$uid);
        $info['user_info']['avatar'] = Common::show_img($info['user_info']['avatar'],1,160,160);
        $angelInfo = $userModel->getInfo($data['uid']);
        $info['user_info']['angel_info'] = isset($angelInfo['info']) ? $angelInfo['info'] :'';
        $b_num = $stageModel->getSidByUid($data['uid']);
        if($b_num){
            $info['user_info']['is_business']['num'] =1;
            $info['user_info']['is_business']['sid'] =$b_num['sid'];
        }else{
            $info['user_info']['is_business']['num'] =0;
            $info['user_info']['is_business']['sid'] ='';
        }
        $info['feed_type'] =1;
        $info['commentList'] = $feedModel->getCommentList($uid,1,$id,1,50);
        if($info['commentList'] ){
            foreach($info['commentList']['list'] as $k=>$v){
                $info['commentList']['list'][$k]['user']['avatar'] = Common::show_img($v['user']['avatar'],1,160,160);
            }
        }
        $likeModel = new LikeModel();
        $is_like = $likeModel->hasData(1,$id,$uid);
        $info['likeList'] = $likeModel->likeList($id,1,1,200,0);
        if(isset($is_like) && $is_like){
            $info['is_like'] = 1;
        }else{
            $info['is_like'] = 0;
        }
        if($info['likeList'] ){
            foreach($info['likeList']['list'] as $k=>$v){
                $info['likeList']['list'][$k]['avatar'] = Common::show_img($v['avatar'],1,160,160);
            }
        }
        $messageModel = new MessageModel();
        $info['rewardList'] = $messageModel->getRewardList(1,$id,1,10,$uid);
        if($info['rewardList'] ){
            foreach($info['rewardList']['list'] as $k=>$v){
                $info['rewardList']['list'][$k]['user']['avatar'] = Common::show_img($v['user']['avatar'],1,160,160);
            }
        }
        Common::appLog('mood/info',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$info);
    }
    //分享详情
    public function shareInfoAction(){
        $data['token'] = $this->getRequest()->get('token');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(4, "非法登录用户");
            }
            $uid=$user['uid'];
        }
        $id = $this->getRequest()->get('hid');//分享id
        if(!$id){
            Common::echoAjaxJson(2, "分享id不能为空");
        }
        $moodModel = new MoodModel();
        $userModel = new UserModel();
        $stageModel = new StageModel();
        $share = $moodModel->getShare($id,$uid,0,2,$version,$_POST['token']);//分享详情信息
        if(!isset($share['id']) || !$share['id']){
            Common::echoAjaxJson(3, "该分享不存在");
        }
        if($share['share_data']){
            if($share['type']==4||$share['type']==10&&isset($share['share_data']['img_src'])){
                foreach($share['share_data']['img_src'] as $k=>$v){
                    $share['share_data']['img_src'][$k] = Common::show_img($v,4,120,120);
                    $share['share_data']['showImg'][$k] = $v;
                }
            }
        }else{
            $share['share_data'] = (object)array();
        }
        $likeModel = new LikeModel();
        $is_like = $likeModel->hasData(9,$id,$uid);
        $share['likeList'] = $likeModel->likeList($id,9,1,9,0);
        if(isset($is_like) && $is_like){
            $share['is_like'] = 1;
        }else{
            $share['is_like'] = 0;
        }
        if($share['likeList'] ){
            foreach($share['likeList']['list'] as $k=>$v){
                $share['likeList']['list'][$k]['avatar'] = Common::show_img($v['avatar'],1,160,160);
            }
        }
        $messageModel = new MessageModel();
        $share['rewardList'] = $messageModel->getRewardList(9,$id,1,10);
        if($share['rewardList'] ){
            foreach($share['rewardList']['list'] as $k=>$v){
                $share['rewardList']['list'][$k]['user']['avatar'] = Common::show_img($v['user']['avatar'],1,160,160);
            }
        }
        $feedModel = new FeedModel();
        $share['feed_type'] = 9;
        $share['commentList'] = $feedModel->getCommentList($uid,9,$id,1,50);
        if($share['commentList'] ){
            foreach($share['commentList']['list'] as $k=>$v){
                $share['commentList']['list'][$k]['user']['avatar'] = Common::show_img($v['user']['avatar'],1,160,160);
            }
        }
        $share['add_time'] = Common::show_time($share['add_time']);
        $share['user'] = $userModel->getUserData($share['uid'],$uid);
        $angelInfo = $userModel->getInfo($share['uid']);
        $share['user']['angel_info'] = isset($angelInfo['info']) ? $angelInfo['info'] :'';
        $b_num = $stageModel->getSidByUid($share['uid']);
        if($b_num){
            $share['user']['is_business']['num'] =1;
            $share['user']['is_business']['sid'] =$b_num['sid'];
        }else{
            $share['user']['is_business']['num'] =0;
            $share['user']['is_business']['sid'] ='';
        }
        Common::appLog('mood/shareInfo',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$share);
    }
    /*
     * 删除心境
     */
    public function delMoodAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $id = (int)$this->getRequest()->getPost('id');//心境id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if($id <= 0){
            Common::echoAjaxJson(2,'删除的心境不能为空');
        }
        $moodModel = new MoodModel();
        $rs = $moodModel->del($user['uid'],$id);
        if($rs == 0){
            Common::echoAjaxJson(3,'删除失败');
        }
        Common::appLog('mood/delMood',$this->startTime,$version);
        Common::echoAjaxJson(1,'删除成功');
    }

    //获取心境列表
    public function getMoodListAction(){
        $data['token'] = $this->getRequest()->get('token');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $last_id = intval($this->getRequest()->get('last_id'));
        $size = ($this->getRequest()->get('size')&&$this->getRequest()->get('size')==10) ? $this->getRequest()->get('size') : 10;
        if($last_id<0){
            $last_id = 0;
        }
        $data = array();
        $moodModel = new MoodModel();
        $list = $moodModel->getMoodList($last_id,(int)$size,$uid);
        Common::appLog('mood/getMoodList',$this->startTime,$version);
        $data['mood_list'] = $list ? $list : array();
        Common::echoAjaxJson(1, "获取成功",$data);
    }
    //轮询心境数量
    public function getNewMoodAction() {
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $lastTime = $this->getRequest()->getPost('last');
        if(!$lastTime){
            $lastTime = time();
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $moodModel = new MoodModel();
        $data = $moodModel->hasNewMood($lastTime,$user['uid']);
        Common::appLog('mood/getNewMood',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$data['num']);
    }
    public function getNewMoodNewAction() {
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $lastTime = $this->getRequest()->getPost('last');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $moodModel = new MoodModel();
        $data = $moodModel->hasNewMood($lastTime,$user['uid']);
        Common::appLog('mood/getNewMoodNew',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$data);
    }
    //未关注用户的心境
    public function getListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $last_id = intval($this->getRequest()->get('last_id'));
        $size = intval($this->getRequest()->get('size'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $last_id = $last_id ? $last_id : 0;
        $moodModel = new MoodModel();
        $size = $size ? $size : 5;
        $data = $moodModel->getMoodList($last_id,$size,$user['uid']);
        Common::appLog('mood/getList',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$data ? $data : array());
    }
    //增加心境
    public function addMoodNewAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(7, "非法登录用户");
        }
        $uid = $user['uid'];
        $content = ltrim($this->getRequest()->getPost('content'));
        $is_img = intval($this->getRequest()->getPost('is_img'));//0无图片 1有图片
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version'): APP_VERSION;
        $is_address = $is_video = 0;
        $lng = $this->getRequest()->getPost('lng');//经度
        $lat = $this->getRequest()->getPost('lat');//纬度
        $address = $this->getRequest()->getPost('address');//地址
        $origin = $this->getRequest()->getPost('origin');//来源 1.PC 2.移动web 3.IOS 4.Android
        $is_public = $this->getRequest()->getPost('is_public') ?$this->getRequest()->getPost('is_public') : 2;
        $origin = $origin ? $origin : 1;
        $video_name = $this->getRequest()->getPost('video_name');
        $video_img = $this->getRequest()->getPost('video_img');
        $client_id = $this->getRequest()->getPost('client_id');
        if($video_name){
            $is_video=2;
        }
        if($lng&&$lat&&$address){
            $is_address = 1;
        }
        $content = Common::angleToHalf($content);
        if(strlen($content) == 0){
            Common::echoAjaxJson(2,'心境内容不能为空');
        }
        $moodModel = new MoodModel();
        $mood = $moodModel->getNewMoodByUid($uid);
        $dur = time() - strtotime($mood['add_time']);
        if($is_img!=1&&$is_video!=2&&Common::contentSpace($content) === Common::contentSpace($mood['content']) && $dur<=600){
            Common::echoAjaxJson(3,'您发送的心境与上一次的心境重复，请10分钟之后重试');
        }
        $content = Common::contentReplace($content);
        if($content === false){
            Common::echoAjaxJson(4,'文字内容不能包含英文字符');
        }
        $security = new Security();
        $content = $security->xss_clean($content);
        $userLength = $moodModel->getMoodLength($uid);
        if(Common::utf8_strlen(ltrim($content)) > $userLength){
            Common::echoAjaxJson(5,'心境最多只能'.$userLength.'字');
        }
        $rs ='';
        if($client_id){
            $rs= $moodModel->getMoodByClientIdAndUid($client_id,$uid);
        }
        if(!$rs){
            $rs = $moodModel->addNew($uid,$content,$is_img,$is_address,$is_public,$origin,$lat,$lng,$address,$is_video,$video_name,$video_img,$version,$client_id);
            if(!$rs){
                Common::echoAjaxJson(6,'发表失败');
            }
            $img = $this->getRequest()->getPost('img');
            $sort = $this->getRequest()->getPost('sort');
            if($img){
                $imgArray = explode('&',$img);
            }else{
                $imgArray=array();
            }
            if($sort){
                $sortArray = explode('&',$sort);
            }else{
                $sortArray=array();
            }
            if(count($imgArray)>9){
                Common::echoAjaxJson(8,'心境图片不能超过9张');
            }
            $moodModel->addImages($user['uid'],$rs,$imgArray,$sortArray);
            $moodModel->updateMood($rs);
        }
        if($is_public==2){
            Common::http(OPEN_DOMAIN."/common/addFeed",array('scope'=>1,'uid'=>$uid,'type'=>'mood',"id"=>$rs,"time"=>time()),"POST");
        }
        Common::appLog('mood/addMoodNew',$this->startTime,$version);
        $info = $moodModel->get($rs,0,1);
        $mood_num = $moodModel->getMoodNum($uid);
        if($mood_num==1){
            $is_first = 'yes';
        }else{
            $is_first = 'no';
        }
        $userModel = new UserModel();
        $stageModel = new StageModel();
        $likeModel = new LikeModel();
        $user= $userModel->getUserData($uid,$uid);
        $info['add_time'] = Common::app_show_time($info['add_time']);
        $info['user']['uid'] = $user['uid'];
        $info['user']['did'] = $user['did'];
        $info['user']['nick_name'] = $user['nick_name'];
        $info['user']['avatar'] = $user['avatar'];
        $info['user']['self'] = $user['self'];
        $info['user']['ico_type'] = $user['ico_type'];
        $info['user']['relation'] = $user['relation'];
        $angelInfo = $userModel->getInfo($uid);
        $info['user']['angel_info'] = isset($angelInfo['info']) ? $angelInfo['info'] :'';
        $b_num = $stageModel->getSidByUid($user['uid']);
        if($b_num){
            $info['user']['is_business']['num'] =1;
            $info['user']['is_business']['sid'] =$b_num['sid'];
        }else{
            $info['user']['is_business']['num'] =0;
            $info['user']['is_business']['sid'] ='';
        }
        $info['user']['type'] = $user['type'];
        $info['is_like'] = 0;
        $info['feed_type'] = 1;
        $info['is_collect'] = 0;
        $info['like_list'] = $likeModel->likeList($rs,1,1,10,$uid);
        $info['comment_list'] = array();
        $messageModel = new MessageModel();
        $reward_list = $messageModel->getRewardList(1,$rs,1,11);
        $info['reward_list'] = $reward_list;
        Common::echoAjaxJson(1,'发布成功',array('id'=>$rs,'is_first'=>$is_first,'info'=>$info));

    }
    //心境图片插入
    public function addImagesAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $mood_id = intval($this->getRequest()->getPost('mood_id'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$mood_id){
            Common::echoAjaxJson(5,'心境id为空');
        }
        $moodModel = new MoodModel();
        $info = $moodModel->get($mood_id,5,1);
        if(!$info){
            Common::echoAjaxJson(7,'心境不存在');
        }
        if(in_array($info,array(0,1))){
            Common::echoAjaxJson(6,'心境已绑定图片');
        }
        $img = $this->getRequest()->getPost('img');
        $sort = $this->getRequest()->getPost('sort');
        if($img){
            $imgArray = explode('&',$img);
        }else{
            $imgArray=array();
        }
        if($sort){
            $sortArray = explode('&',$sort);
        }else{
            $sortArray=array();
        }
        if(count($imgArray)>9){
            Common::echoAjaxJson(2,'心境图片不能超过9张');
        }
        $moodModel->addImages($user['uid'],$mood_id,$imgArray,$sortArray);
        $rs = $moodModel->updateMood($mood_id);
        if(!$rs){
            Common::echoAjaxJson(3,'添加失败');
        }
        Common::appLog('mood/addImages',$this->startTime,$version);
        Common::echoAjaxJson(1,'添加成功');
    }
    //获取用户发布心境字数
    public function getMoodLengthAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $moodModel = new MoodModel();
        $length = $moodModel->getMoodLength($user['uid']);
        Common::appLog('mood/getMoodLength',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$length ? $length : 200 );
    }
    //心境广场数据
    public function moodSquareAction(){
        $data['token'] = $this->getRequest()->get('token');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $last_id = intval($this->getRequest()->getPost('last_id'));
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==10) ? $this->getRequest()->getPost('size') : 10;
        if($last_id<0){
            $last_id = 0;
        }
        $moodModel = new MoodModel();
        $list = $moodModel->getListForFeed($last_id,(int)$size,$uid);
        Common::appLog('mood/moodSquare',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$list ? $list : (object)array());
    }
    public function modifyMoodAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(7, "非法登录用户");
        }
        $uid = $user['uid'];
        $content = ltrim($this->getRequest()->getPost('content'));
        $is_img = intval($this->getRequest()->getPost('is_img'));//0无图片 1有图片
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version'): APP_VERSION;
        $is_video = 0;
        $content = Common::angleToHalf($content);
        if(strlen($content) == 0){
            Common::echoAjaxJson(2,'心境内容不能为空');
        }
        $moodModel = new MoodModel();
        $mood = $moodModel->getNewMoodByUid($uid);
        $dur = time() - strtotime($mood['add_time']);
        if($is_img!=1&&$is_video!=2&&Common::contentSpace($content) === Common::contentSpace($mood['content']) && $dur<=600){
            Common::echoAjaxJson(3,'您发送的心境与上一次的心境重复，请10分钟之后重试');
        }
        $content = Common::contentReplace($content);
        if($content === false){
            Common::echoAjaxJson(4,'文字内容不能包含英文字符');
        }
        $security = new Security();
        $content = $security->xss_clean($content);
        $userLength = $moodModel->getMoodLength($uid);
        if(Common::utf8_strlen(ltrim($content)) > $userLength){
            Common::echoAjaxJson(5,'心境最多只能'.$userLength.'字');
        }
        Common::echoAjaxJson(1,'心境可以发');
    }
}