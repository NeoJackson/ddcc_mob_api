<?php
class CommonController extends Yaf_Controller_Abstract {
    public $like_type_arr = array(1,2,3,4,9,10,12);//1心境 2图片 3日志 4普通帖子 9分享 10服务信息 12商品
    private $comment_type_arr = array(1,2,3,4,9,10,12);
    private $reward_type_arr = array(1,3,4,9);
    public function init(){
        $this->startTime = microtime(true);
    }
    //获取当前登录用户信息
    public function getUserAction(){
        $user = Common::isLogin($_POST);
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $user['avatar'] = Common::show_img($user['avatar'],1,200,200);
        $tagModel = new TagModel();
        $tagList = $tagModel->getRelation(1,$user['uid']);
        if($tagList){
            $user['is_tag'] = 1;
        }else{
            $user['is_tag'] = 0;
        }
        $feedModel = new FeedModel();
        $userModel = new UserModel();
        $feeds = $feedModel->getUserList($user['uid'],0,1);
        $home_cover = $userModel->getUserInfoByUid($user['uid']);
        $user['feed_count'] = $feeds['size'];
        $user['home_cover'] = $home_cover['home_cover'] ? IMG_DOMAIN.$home_cover['home_cover'] : PUBLIC_DOMAIN.'default_feed_cover.jpg';
        Common::appLog('common/getUser',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$user ?$user : (object)array());
    }
    /**
     * 上传图片接口
     */
    public function uploadAction(){
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!isset($_FILES['Filedata'])){
            Common::echoAjaxJson(2,"图片上传失败");
        }
        $image = new Image();
        $tmp_file = $_FILES['Filedata']['tmp_name'];
        $filename = $_FILES['Filedata']['name'];
        $rs = $image->upload($tmp_file,$filename);
        if($rs == -1){
            Common::echoAjaxJson(3,"图片格式不正确，请上传jpg,jpeg,png,gif格式的图片");
        }else if($rs == -2){
            Common::echoAjaxJson(4,"图片上传失败");
        }else if($rs == -3){
            Common::echoAjaxJson(5,"图片大小不能超过5M");
        }else if($rs == -4){
            Common::echoAjaxJson(6,"图片上传失败");
        }
        $img_name = $image->uploadToServer(dirname(dirname(APPLICATION_PATH)).$rs['path']);
        if(!$img_name){
            Common::echoAjaxJson(7,"图片上传失败");
        }
        Common::appLog('common/upload',$this->startTime,$version);
        Common::echoAjaxJson(1,"图片上传成功",$img_name);
    }
    public function uploadNewAction(){
        if(!isset($_FILES['Filedata'])){
            Common::echoAjaxJson(2,"图片上传失败");
        }
        $image = new Image();
        $tmp_file = $_FILES['Filedata']['tmp_name'];
        $filename = $_FILES['Filedata']['name'];
        $sort = $this->getRequest()->getPost('sort');//图片顺序
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $rs = $image->upload($tmp_file,$filename);
        if($rs == -1){
            Common::echoAjaxJson(3,"图片格式不正确，请上传jpg,jpeg,png,gif格式的图片");
        }else if($rs == -2){
            Common::echoAjaxJson(4,"图片上传失败");
        }else if($rs == -3){
            Common::echoAjaxJson(5,"图片大小不能超过5M");
        }else if($rs == -4){
            Common::echoAjaxJson(6,"图片上传失败");
        }
        $img_name = $image->uploadToServer(dirname(dirname(APPLICATION_PATH)).$rs['path']);
        if(!$img_name){
            Common::echoAjaxJson(7,"图片上传失败");
        }
        Common::appLog('common/uploadNew',$this->startTime,$version);
        Common::echoAjaxJson(1,"图片上传成功",array('img'=>$img_name,'sort'=>$sort));
    }
    /*
     * @name 用户签到
     */
    public function userCheckinAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $rs = Common::http(OPEN_DOMAIN.'/checkinapi/homeCheckIn',array('uid'=>$user['uid']),'POST');
        $rs = json_decode($rs,true);
        $checkinModel = new CheckinModel();
        $series = $checkinModel->getUserCheckIn($user['uid']);
        $userModel = new UserModel();
        $data['series'] = $series[0];
        $userInfo = $userModel->getUserData($user['uid']);
        $data['user_info']['score'] = $userInfo['score'];
        if($rs['status'] == 2){
            $data['score'] = $rs['data']['score'];
            Common::echoAjaxJson(2,'今天已经签到',$data);
        }
        if($rs['status'] == 3){
            Common::echoAjaxJson(3,'签到失败');
        }
        if($rs['status'] ==1){
            $data['score'] = $rs['data'];
        }
        Common::appLog('common/userCheckin',$this->startTime,$version);
        Common::echoAjaxJson(1,'签到成功',$data);
    }

    //获取喜欢记录列表
    public function likeListAction(){
        $data['token'] = $this->getRequest()->get('token');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $uid = '';
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $type = $this->getRequest()->get('type');//类型
        $obj_id = $this->getRequest()->get('obj_id');//对象id
        $page = intval($this->getRequest()->get('page'));
        $size = ($this->getRequest()->get('size')&&$this->getRequest()->get('size')==20) ? $this->getRequest()->get('size') :20;
        $userModel =  new UserModel();
        $page = $page ? $page : 1;
        if(!in_array($type,$this->like_type_arr) || $type == ''){
            Common::echoAjaxJson(2,'喜欢类型不正确');
        }
        if(!$obj_id){
            Common::echoAjaxJson(3,'喜欢对象不能为空');
        }
        $likeModel = new LikeModel();
        $rs = $likeModel->getLikeList($obj_id,$type,$page,(int)$size);
        if($rs){
            foreach($rs as $k=> $v){
                $userInfo = $userModel->getUserData($v['uid'],$uid);
                $rs[$k]['nick_name'] = $userInfo['nick_name'];
                $rs[$k]['type'] = $userInfo['type'];
                $rs[$k]['intro'] = $userInfo['intro'];
                $rs[$k]['att_num'] = $userInfo['att_num'];
                $rs[$k]['fans_num'] = $userInfo['fans_num'];
                $rs[$k]['self'] = isset($userInfo['self'])?$userInfo['self']:0;
                $rs[$k]['relation'] = $userInfo['relation'];
                $rs[$k]['sex'] = $userInfo['sex'];
                $rs[$k]['avatar'] = Common::show_img($userInfo['avatar'],1,160,160);
            }
        }
        Common::appLog('common/likeList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$rs?$rs : (object)array());
    }
    //获取报名记录列表
    public function partakeListAction(){
        $data['token'] = $this->getRequest()->get('token');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $uid = '';
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $type = $this->getRequest()->get('type');//类型
        $obj_id = $this->getRequest()->get('obj_id');//对象id
        $price_type = $this->getRequest()->get('price_type');
        $page = intval($this->getRequest()->get('page'));
        $size = ($this->getRequest()->get('size')&&$this->getRequest()->get('size')==10) ? $this->getRequest()->get('size') : 10;
        $userModel =  new UserModel();
        $page = $page ? $page : 1;
        $rs = array();
        if(!in_array($type,array(1,2,3,4,5,6,7)) || $type == ''){
            Common::echoAjaxJson(2,'类型不正确');
        }
        if(!$obj_id){
            Common::echoAjaxJson(3,'参与对象不能为空');
        }
        $eventModel = new EventModel();
        $start = ($page-1)*$size;
        if($price_type==1){
            $rs = $eventModel->partakeListByEid($obj_id,$start,(int)$size);
        }elseif($price_type==2){
            $rs = $eventModel->getOrderListByEid($obj_id,$start,(int)$size);
        }
        $list = array();
        if($rs){
            foreach($rs as $k=> $v){
                $userInfo = $userModel->getUserData($v['uid'],$uid);
                $list[$k]['uid'] = $v['uid'];
                $list[$k]['status'] = $v['status'];
                $list[$k]['add_time'] = Common::show_time($v['add_time']);
                $list[$k]['nick_name'] = $userInfo['nick_name'];
                $list[$k]['real_name'] = isset($v['name']) ? $v['name'] : "";
                $list[$k]['intro'] = $userInfo['intro'];
                $list[$k]['att_num'] = $userInfo['att_num'];
                $list[$k]['fans_num'] = $userInfo['fans_num'];
                $list[$k]['self'] = isset($userInfo['self'])?$userInfo['self']:0;
                $list[$k]['relation'] = $userInfo['relation'];
                $list[$k]['sex'] = $userInfo['sex'];
                $list[$k]['avatar'] = Common::show_img($userInfo['avatar'],1,160,160);
                $list[$k]['ico_type'] = $userInfo['ico_type'];
                $list[$k]['total'] = $eventModel->partakeNumByEid($obj_id,0);
                $list[$k]['updateTickets'] = $eventModel->partakeNumByEid($obj_id,2);
            }
        }
        Common::appLog('common/partakeList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list?$list :array());
    }
    //打赏判断权限
    public function conditionAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(8, "非法登录用户");
        }
        $uid = $user['uid'];
        $obj_id = (int)$this->getRequest()->getPost('obj_id');
        $type = (int)$this->getRequest()->getPost('type');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $feedModel = new FeedModel();
        $userModel = new UserModel();
        $scoreModel = new ScoreModel();
        $obj_info = $feedModel->getDataByTypeAndId($type,$obj_id);
        if(!$obj_id){
            Common::echoAjaxJson(4,"被打赏对象不能为空");
        }
        if(!$obj_info){
            Common::echoAjaxJson(5,"被打赏对象已不存在");
        }
        if($obj_info){
            $obj_user = $userModel->getUserByUid($obj_info['uid']);
        }
        if($obj_user['status']>1){
            Common::echoAjaxJson(6,"被打赏用户已不存在");
        }
        if($obj_info['uid'] == $uid){
            Common::echoAjaxJson(7,"自己不能打赏自己");
        }
        $user_info = $userModel->getUserInfoByUid($uid);
        if($user_info['score']<200){
            Common::echoAjaxJson(3,"您的福报值不足200，不能打赏哦");
        }
        if($user_info['score']<=0){
            Common::echoAjaxJson(9,"您没有福报值，不能打赏哦");
        }
        $score_value = $scoreModel->getScoreValue($uid,2);
        if($score_value>=5000){
            Common::echoAjaxJson(3,"今日5000限额已经打赏完了，明天再来打赏吧");
        }
        $value = 5000-(int)$score_value;
        $array = array('score' => $user_info['score'],'value' => $value);
        Common::appLog('common/condition',$this->startTime,$version);
        Common::echoAjaxJson(1,"满足打赏条件",$array);
    }
    //打赏列表
    public function rewardListAction(){
        $data['token'] = $this->getRequest()->get('token');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(4, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $type = $this->getRequest()->get('type');//类型
        $obj_id = $this->getRequest()->get('obj_id');//对象id
        $page = intval($this->getRequest()->get('page'));
        $size = ($this->getRequest()->get('size')&&$this->getRequest()->get('size')==20) ? $this->getRequest()->get('size') : 20;
        $last_time = $this->getRequest()->get('last_time');
        if(!in_array($type,$this->like_type_arr) || $type == ''){
            Common::echoAjaxJson(2,'打赏类型不正确');
        }
        if(!$obj_id){
            Common::echoAjaxJson(3,'打赏对象不能为空');
        }
        $messageModel = new MessageModel();
        if($page){
            $rewardList = $messageModel->getRewardList($type,$obj_id,$page,(int)$size,$uid);
        }else{
            $rewardList = $messageModel->getRewardListByLastTime($type,$obj_id,$last_time,(int)$size,$uid);
        }

        if($rewardList){
            foreach($rewardList['list'] as $k=>$v){
                $rewardList['list'][$k]['user']['avatar'] = Common::show_img($v['user']['avatar'],1,160,160);
            }
        }
        Common::appLog('common/rewardList',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$rewardList ? $rewardList : (object)array());
    }
    //打赏功能
    public function rewardAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(13, "非法登录用户");
        }
        $uid = $user['uid'];
        $obj_id = (int)$this->getRequest()->getPost('obj_id');
        $type = (int)$this->getRequest()->getPost('type');
        $score_value = (int)$this->getRequest()->getPost('score_value');
        $content = $this->getRequest()->getPost('content');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if($score_value < 0){
            Common::echoAjaxJson(2,"打赏福报值不可小于0");
        }
        $userModel = new UserModel();
        $scoreModel = new ScoreModel();
        $feedModel = new FeedModel();
        $messageModel = new MessageModel();
        $moodModel = new MoodModel();
        $user_info = $userModel->getUserInfoByUid($uid);
        $obj_info = $feedModel->getDataByTypeAndId($type,$obj_id);
        $obj_uid = $obj_user = 0;
        if($obj_info){
            $obj_uid = $obj_info['uid'];
            $obj_user = $userModel->getUserByUid($obj_info['uid']);
        }
        if($user_info['score']<200){
            Common::echoAjaxJson(3,"您的福报值不足200，不能打赏哦");
        }
        if($user_info['score']<=0){
            Common::echoAjaxJson(17,"您没有福报值，不能打赏哦");
        }
        if($user_info['score']<$score_value){
            Common::echoAjaxJson(4,"您的福报值不够打赏哟");
        }
        $value = $scoreModel->getScoreValue($uid,2);//统计用户已经打赏的福报值
        $new_value = 5000-(int)$value;
        if($value>=5000){
            Common::echoAjaxJson(5,"今日5000限额已经打赏完了，明天再来打赏吧");
        }
        if($score_value>$new_value){
            Common::echoAjaxJson(6,"今日打赏已超额");
        }
        if(!$obj_id){
            Common::echoAjaxJson(7,"被打赏对象不能为空");
        }
        if(!$obj_info){
            Common::echoAjaxJson(8,"被打赏对象已不存在");
        }
        if($obj_user['status']>1){
            Common::echoAjaxJson(9,"被打赏用户已不存在");
        }
        if($obj_uid == $uid){
            Common::echoAjaxJson(10,"自己不能打赏自己");
        }
        if($type==10||$type==12){
            Common::echoAjaxJson(18,"服务信息暂时不能打赏");
        }
        if(!$type || !in_array($type,array(1,3,4,9,10,12))){
            Common::echoAjaxJson(11,"被打赏类型不能为空");
        }
        if(!$score_value){
            Common::echoAjaxJson(12,"打赏福报值不能为空");
        }
        if(!$content){
            Common::echoAjaxJson(13,"打赏理由不能为空");
        }
        if(preg_match('/[A-Za-z]{1,}/',$content)){
            Common::echoAjaxJson(14,'打赏理由不能包含英文字符');
        }
        if(Common::utf8_strlen($content) > 140){
            Common::echoAjaxJson(15,'打赏理由为1-140个字符');
        }
        $rs = $messageModel->addReward($uid,$obj_uid,$obj_id,$type,$content,$score_value);//插入打赏推送消息
        $scoreModel->add($uid,$obj_uid,'bounty',$obj_id,$score_value);//插入福报值信息
        $moodModel->updateRewardNum($obj_id,$type);//更新心境、日志、帖子等打赏数
        if($rs == 0){
            Common::echoAjaxJson(16,"打赏失败");
        }
        if($user['uid']!=$obj_uid){
            $feedModel->initJpushReward($obj_uid, '您收到了一份新打赏');
        }
        Common::appLog('common/reward',$this->startTime,$version);
        Common::echoAjaxJson(1,"打赏成功");
    }
    //设置用户坐标
    public function setUserCoordinateAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $uid = $user['uid'];
        $lng = $this->getRequest()->getPost('lng');//经度
        $lat = $this->getRequest()->getPost('lat');//纬度
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$lng||!$lat){
            Common::echoAjaxJson(2, "您没有开启GPS定位功能");
        }
        $userModel = new UserModel();
        $data = $userModel->getAppUserInfo($uid);
        if(!$data){
            $rs = $userModel->setUserCoordinate($uid,$lng,$lat);
        }else{
            $rs = $userModel->updateUserCoordinate($data['id'],$lng,$lat);
        }
        if(!$rs){
            Common::echoAjaxJson(3, "设置坐标失败");
        }
        Common::appLog('common/setUserCoordinate',$this->startTime,$version);
        Common::echoAjaxJson(1, "设置坐标成功");
    }
    //生成二维码
    public function getQrCodeAction(){
        $obj_id = $this->getRequest()->get('obj_id');//二维码对象id
        $type = $this->getRequest()->get('type');//二维码对象类型 1用户 2驿站 3 帖子 4活动 5日志 6报名凭证 7支付凭证
        $size = $this->getRequest()->get('size');// 大小 1~10
        $p_uid = $this->getRequest()->get('p_uid');
        if($size > 10){
            $size = 10;
        }
        switch($type){
            case 1://用户
                $userModel = new UserModel();
                $user = $userModel->getUserByUid($obj_id);
                PHPQRCode::getUserQRCode($user['did'],IMG_DOMAIN.$user['avatar']);
                break;
            case 2://驿站
                $stageModel = new StageModel();
                $stageInfo = $stageModel->getStage($obj_id);
                PHPQRCode::getStageQRCode($obj_id,IMG_DOMAIN.$stageInfo['icon']);
                break;
            case 3://帖子
                PHPQRCode::getTopicQRCode($obj_id,$size);
                break;
            case 4://活动
                PHPQRCode::getEventQRCode($obj_id,$size);
                break;
            case 5://日志
                PHPQRCode::getBlogQRCode($obj_id,$size);
                break;
            case 6://报名凭证
                $eventModel = new EventModel();
                $info = $eventModel->getEvent($obj_id);
                PHPQRCode::getPartakeQRCode($p_uid,$info['sid'],$obj_id,$size);
                break;

        }
    }

    //用户、驿站二位码生成图片传到七牛
    public function qRCodeAction(){
        $obj_id = (int)$this->getRequest()->getPost('obj_id');
        $type = (int)$this->getRequest()->getPost('type');//1用户头像 2驿站图标
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!in_array($type,array(1,2))){
            Common::echoAjaxJson(2,'类型不能为空');
        }
        if(!$obj_id){
            Common::echoAjaxJson(3,'二维码对象不能为空');
        }
        if($type==1){
            $img = PHPQRCode::getUserPHPQRCode($obj_id,false);
        }elseif($type==2){
            $img = PHPQRCode::getStagePHPQRCode($obj_id,false);
        }
        Common::appLog('common/qRCode',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$img);
    }
    //我的等级
    public function myLevelAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $url=I_DOMAIN.'/common/level?token='.$_POST['token'].'';
        Common::appLog('common/myLevel',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$url);
    }
    //我的等级
    public function levelAction(){
        $data['token'] = $this->getRequest()->get('token');
        $uid = '';
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
            $this->getView()->user = $user;
        }
        $missionModel = new MissionModel();
        $this->getView()->exp_info = Common::getUserLevel($user['exp']);
        $this->getView()->active_days = $missionModel->activeDays($uid);
        $this->display("level");
    }

    //查询是否是最新版本号
    public function verifyVersionAction(){
        $data['token'] = $this->getRequest()->get('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(6, "非法登录用户");
            }
        }
        $type = $this->getRequest()->getPost('type');//1.IOS 2.安卓
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$type || !in_array($type,array(1,2))){
            Common::echoAjaxJson(2, "查询的类型不正确");
        }
        if(!$version){
            Common::echoAjaxJson(3, "版本号不能为空");
        }
        $userModel = new UserModel();
        $num = $userModel->getVersion($type,$version);
        if(!$num){
            Common::echoAjaxJson(4, "版本号不正确");
        }
        $rs = $userModel->verifyAppVersion($type,$version);
        if($rs['status']==0){
            Common::echoAjaxJson(5, "最新版本");
        }else{
            $url = $userModel->getNewVersion($type);
        }
        Common::appLog('common/verifyVersion',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$url['url']);
    }
    //查询是否是最新版本号
    public function verifyVersionNewAction(){
        $data['token'] = $this->getRequest()->get('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(6, "非法登录用户");
            }
        }
        $type = $this->getRequest()->getPost('type');//1.IOS 2.安卓
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$type || !in_array($type,array(1,2))){
            Common::echoAjaxJson(2, "查询的类型不正确");
        }
        if(!$version){
            Common::echoAjaxJson(3, "版本号不能为空");
        }
        $userModel = new UserModel();
        $num = $userModel->getVersion($type,$version);
        if(!$num){
            Common::echoAjaxJson(4, "版本号不正确");
        }
        $rs = $userModel->verifyAppVersion($type,$version);
        if($rs['status']==0){
            Common::echoAjaxJson(5, "最新版本");
        }else{
            $url = $userModel->getNewVersion($type);
            $info = $userModel->getNewVersionInfo($url['id']);
        }
        Common::appLog('common/verifyVersionNew',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",array('url'=>$url['url'],'name'=>$url['name'],'info'=>$info));
    }
    //对外分享
    public function foreignShareAction(){
        $type = $this->getRequest()->getPost('type');//99.个人文化圈(书房)3.日志 4.帖子 5.驿站 10.活动 1.心境 2.照片 7.对外活动 8.html静态页面 12商品 13话题 14商城 15专栏
        $id = $this->getRequest()->getPost('id');//对象id
        $data['token'] = $this->getRequest()->getPost('token');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if($type ==7||$type==8||$type==15){
            $url = $this->getRequest()->getPost('url');
            $title = $this->getRequest()->getPost('title') ?$this->getRequest()->getPost('title') :"中华游学营进行中";
            if(!$url){
                Common::echoAjaxJson(6, "分享对象不能为空");
            }
        }
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data,1);
            if(!$user){
                Common::echoAjaxJson(5, "非法登录用户");
            }
            $uid=$user['uid'];
        }
        if(!$type ||!in_array($type,array(1,2,3,4,5,7,8,10,12,13,14,15,99))){
            Common::echoAjaxJson(2, "对象不正确");
        }
        if(!in_array($type,array(7,8,14,15))&&!$id){
            Common::echoAjaxJson(3, "id不能为空");
        }
        $collectModel = new CollectModel();
        $userModel = new UserModel();
        $data = array();
        if($type ==4){
           $topicModel = new TopicModel();
           $topicInfo = $topicModel->getTopicById($id);
           if($topicInfo['status']<2){
               $data['id'] = $topicInfo['id'];
               $data['title'] = $topicInfo['title'];
               $data['description'] = $topicInfo['summary'] ? Common::deleteHtml($topicInfo['summary']) : '我发现了一个不错的帖子，快来看看吧！';
               $data['sid'] = $topicInfo['sid'];
               $img_arr = Common::pregMatchImg($topicInfo['content']);
               if($img_arr[3]){
                   $stageImg = Common::show_img($img_arr[3][0],4,100,100);
               }else{
                   $stageImg = PUBLIC_DOMAIN.'defalut_share.png';
               }
               $data['img'] = $stageImg;
               $data['is_collect'] = $collectModel->hasData($type,$id,$uid);
               $data['url'] = SNS_DOMAIN.'/t/'.$data['id'];
           }else{
               Common::echoAjaxJson(4, "被分享的内容不存在");
           }
        }elseif($type ==5){
            $stageModel = new StageModel();
            $stageInfo = $stageModel->getBasicStageBySid($id);
            if($stageInfo['status']<2){
            $data['id'] = $stageInfo['sid'];
            $data['title'] = $stageInfo['name'].'驿站';
            $data['description'] = '我在才府发现了一个好驿站，快来加入吧！';
            $data['sid'] = $stageInfo['sid'];
            $data['img'] = Common::show_img($stageInfo['icon'],4,100,100);
            $data['is_collect'] = 0;
            $data['url'] = SNS_DOMAIN.'/s/'.$data['id'];
            }else{
                Common::echoAjaxJson(4, "被分享的内容不存在");
            }
        }elseif($type==10){
            $eventModel = new EventModel();
            $commonModel = new CommonModel();
            $eventInfo = $eventModel->getEventRedisById($id);
            if($eventInfo['status']<2){
                $data['id'] = $eventInfo['id'];
                $data['title'] = $eventInfo['title'];
                $data['description'] = $eventInfo['summary'] ? $eventInfo['summary'] : '我发现了一个不错的活动，快来看看吧！';
                $data['sid'] = $eventInfo['sid'];
                $data['img'] = Common::show_img($eventInfo['cover'],4,100,100);
                $data['is_collect'] = $collectModel->hasData($type,$id,$uid);
                if(isset($eventInfo['is_commission'])&&$eventInfo['is_commission']==1&&$eventInfo['uid']!=$uid&&$uid){
                    $set_id = $commonModel->getUserSetLastId(0,10,$id);
                    $sp_id = $commonModel->addSharePromote(10,$id,$uid,$eventInfo['commission_rate'],$set_id);
                    $data['url'] = SNS_DOMAIN.'/e/'.$id.'&sp='.base64_encode(base64_encode(''.$uid.'-'.$sp_id.'-'.$type.'-'.($eventInfo['commission_rate']*100).'-'.$id.'-'.$set_id.''));
                    $data['commission'] = $eventInfo['commission'];
                }else{
                    $data['url'] = SNS_DOMAIN.'/e/'.$id;
                }

                $price_info= $eventModel->getPrice($id);
                $data['price_count'] = count($price_info);
                $data['min_price'] = $price_info[0]['unit_price'];
                if($eventInfo['type']==1){
                    $type_info = $eventModel->getBusinessEventType($eventInfo['type_code']);
                }else{
                    $type_info = Common::eventType($eventInfo['type']);
                }
                $data['type_name'] = $type_info['name'];
                $data['code_name'] = $type_info['code'];
            }else{
                Common::echoAjaxJson(4, "被分享的内容不存在");
            }
        }elseif($type ==99){
            $userInfo = $userModel->getUserData($id);
            if($userInfo['type']>1){
                $indexModel = new IndexModel();
                $info = $indexModel->getAngelInfoByUid($userInfo['uid']);
                $userInfo['intro'] = $info['info'];
            }
            if($userInfo['did']){
                $data['id'] = $userInfo['did'];
                $data['title'] = $userInfo['nick_name'].'的主页';
                $data['description'] = $userInfo['intro'] ? $userInfo['intro'] :'传承传统文化是每个人的使命！';
                $data['sid'] = 0;
                $data['img'] = Common::show_img($userInfo['avatar'],4,100,100);
                $data['is_collect'] = $collectModel->hasData($type,$id,$uid);
                $data['url'] = M_DOMAIN.'/'.$userInfo['did'].'';
            }else{
                Common::echoAjaxJson(4, "被分享的内容不存在");
            }
        }elseif($type ==3){
            $blogModel = new BlogModel();
            $BlogInfo = $blogModel->getBlogById($id);
            if($BlogInfo['status']<2){
                $data['id'] = $BlogInfo['id'];
                $data['title'] = $BlogInfo['title'];
                $data['description'] = $BlogInfo['content'] ? $BlogInfo['content'] : Common::deleteHtml($BlogInfo['title']);
                $data['sid'] = '0';
                $img_arr = Common::pregMatchImg($BlogInfo['content']);
                if($img_arr[3]){
                    $stageImg = Common::show_img($img_arr[3][0],4,100,100);
                }else{
                    $stageImg = PUBLIC_DOMAIN.'defalut_share.png';
                }
                $data['img'] = $stageImg;
                $data['is_collect'] = $collectModel->hasData($type,$id,$uid);
                $data['url'] = SNS_DOMAIN.'/b/'.$data['id'];
            }else{

                Common::echoAjaxJson(4, "被分享的内容不存在");
            }
        }elseif($type==1){
            $moodModel = new MoodModel();
            $mood = $moodModel->getMood($id);
            if($mood){
                $moodInfo = $moodModel->getExtInfo($mood);
                $data['id'] = $mood['id'];
                $userInfo = $userModel->getUserData($mood['uid']);
                $data['title'] = $userInfo['nick_name'].'的心境';
                if(!$mood['content']||$mood['content']=='分享图片'){
                     $mood['content'] = '我发现了一个不错的心境，快来看看吧！';
                }
                $data['description'] = $mood['content'];
                $data['is_video'] = $mood['is_video'];
                $data['sid'] = '0';
                if($mood['is_img']==1){
                    $data['img'] = Common::show_img($moodInfo['img'][0]['img'],4,100,100);
                }elseif($mood['is_video']==2){
                    $data['img'] = Common::show_img($moodInfo['video_img'],4,100,100);
                }else{
                    $data['img'] = Common::show_img($userInfo['avatar'],4,100,100);
                }
                $data['is_collect'] = $collectModel->hasData($type,$id,$uid);
                $data['url'] = SNS_DOMAIN.'/m/'.$data['id'];
            }else{
                Common::echoAjaxJson(4, "被分享的内容不存在");
            }
        }elseif($type==2){
            $albumModel = new AlbumModel();
            $photoInfo = $albumModel->getPhotoById($id);
            if($photoInfo){
                $userInfo = $userModel->getUserData($photoInfo['uid']);
                $data['id'] = $photoInfo['id'];
                $data['title'] = $userInfo['nick_name'].'的才府照片';
                $data['description'] = '发现了一张不错的图片，快来看看吧';
                $data['sid'] = '0';
                $data['img'] = Common::show_img($photoInfo['album_img'],1,100,100);
                $data['is_collect'] = $collectModel->hasData($type,$id,$uid);
                $data['url'] = SNS_DOMAIN.'/album/photo?id='.$id;
            }else{
                Common::echoAjaxJson(4, "被分享的内容不存在");
            }

        }elseif($type==7){
            $data['id'] = '0';
            $data['title'] = $title;
            $data['description'] = $url;
            $data['sid'] = '0';
            $data['img'] = PUBLIC_DOMAIN.'defalut_share.png';
            $data['is_collect'] = '0';
            $data['url'] = $url;
        }elseif($type==8){
            $data['id'] = '0';
            $data['title'] = $title;
            $data['description'] = $url;
            $data['sid'] = '0';
            $data['img'] = PUBLIC_DOMAIN.'defalut_share.png';
            $data['is_collect'] = '0';
            $data['url'] = $url;
        }elseif($type==12){
            //佣金分享加密规则：uid-分享id-类型-佣金比例-obj_id-set_id
            $stagegoodsModel = new StagegoodsModel();
            $commonModel = new CommonModel();
            $info = $stagegoodsModel->getGoodsRedisById($id);
            if($info&&$info['status'] < 2){
                $data['id'] = $info['id'];
                $data['title'] = $info['name'];
                $data['description'] = Common::deleteHtml($info['intro']) ?Common::deleteHtml($info['intro']) :'我发现了一个不错的商品，快来看看吧';
                $data['sid'] = $info['sid'];
                $data['img'] = Common::show_img($info['cover'],1,100,100);
                $data['is_collect'] = $collectModel->hasData($type,$id,$uid);
                if(isset($info['is_commission'])&&$info['is_commission']==1&&$info['uid']!=$uid&&$uid){
                    $set_id = $commonModel->getUserSetLastId(0,12,$id);
                    $sp_id = $commonModel->addSharePromote(12,$id,$uid,$info['commission_rate'],$set_id);
                    $data['url'] = SNS_DOMAIN.'/g/'.$id.'&sp='.base64_encode(base64_encode(''.$uid.'-'.$sp_id.'-'.$type.'-'.($info['commission_rate']*100).'-'.$id.'-'.$set_id.''));
                    $data['commission'] = $info['commission'];
                }else{
                    $data['url'] = SNS_DOMAIN.'/g/'.$id;
                }
                $data['price'] = $info['price'] ? $info['price'] : '';
                $data['score'] = $info['score'] ? $info['score'] : '';
            }else{
                Common::echoAjaxJson(4, "被分享的内容不存在");
            }
        }elseif($type==13){
            $talkModel = new TalkModel();
            $info = $talkModel->getInfo($id);
            if($info&&$info['status'] < 2){
                $data['id'] = $info['id'];
                $data['title'] = $info['keyword'];
                $data['description'] = '一起来讨论话题#'.$info['keyword'].'#吧';
                $data['sid'] = 0;
                $data['img'] = PUBLIC_DOMAIN.'defalut_share.png';
                $data['is_collect'] = '0';
                $data['url'] = SNS_DOMAIN.'/k/'.$info['keyword'];
            }else{
                Common::echoAjaxJson(4, "被分享的内容不存在");
            }
        }elseif($type==14){
            $data['id'] = '0';
            $data['title'] = '才府商城';
            $data['description'] = '属于您的中式生活商城，生活家居、文房雅器、古玩收藏等丰富商品。才府商城-守护你的中国风情怀。';
            $data['sid'] = '0';
            $data['img'] = PUBLIC_DOMAIN.'defalut_share.png';
            $data['is_collect'] = '0';
            $data['url'] = M_DOMAIN.'/mall';

        }elseif($type==15){
            $indexModel = new IndexModel();
            $info = $indexModel->getColumnByUrl($url);
            if($info&&$info['status']==1){
                $data['id'] = $id;
                $data['title'] = $info['name'];
                $data['description'] = Common::deleteHtml($info['intro']) ?Common::deleteHtml($info['intro']) :'我发现了一个不错的专栏，快来看看吧';
                $data['sid'] = '0';
                $data['img'] = Common::show_img($info['cover'],1,100,100);
                $data['is_collect'] = '0';
                $url_array = explode('/',$info['url']);
                $count = count($url_array);
                $data['url']  = M_DOMAIN.'/'.$url_array[$count-2].'/'.$url_array[$count-1];
            }else{
                Common::echoAjaxJson(4, "被分享的内容不存在");
            }
        }
        Common::appLog('common/foreignShare',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$data);
    }
    //将用户登陆状态放入redis
    public function setLoginRedisAction(){
        $token = $this->getRequest()->get('token');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if($token){
            $user = Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(4, "非法登录用户");
            }
            $uid = $user['uid'];
            $this->getView()->user = $user;
            $userModel = new UserModel();
            $userModel->addUserLogin($uid,$_POST['token']);
        }
        Common::appLog('common/setLoginRedis',$this->startTime,$version);
        Common::echoAjaxJson(1,"设置成功");
    }

    //小红点滑动清除
    public function updateIsReadAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $type = $this->getRequest()->getPost('type');//对象id
        $to_uid = $this->getRequest()->getPost('to_uid');//一对一聊天时 需上传
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $commonModel = new CommonModel();
        $commonModel->updateIsRead($type,$user['uid'],$to_uid);
        Common::appLog('common/updateIsRead',$this->startTime,$version);
        Common::echoAjaxJson(1,"设置成功");
    }
    //获取用户签到状态
    public function isCheckInAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $checkInModel = new CheckinModel();
        $num = $checkInModel->record($user['uid'],date('Y-m-d'));
        if($num>0){
            Common::echoAjaxJson(2, "今天已签到");
        }
        Common::echoAjaxJson(1, "今天未签到");
    }
    //获取地址
    public function getAddressAction(){
        $token = $this->getRequest()->getPost('token');//用户登录token
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $type = $this->getRequest()->getPost("type");
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $addressModel = new AddressModel();
        $list = $addressModel->getAddressList();
        if($list){
          foreach($list as $k=>$v){
              $list[$k]['level2'] = $addressModel->getListByPid($v['id']);
              if(!$type){
                  foreach($list[$k]['level2'] as $k1=>$v1){
                      $list[$k]['level2'][$k1]['level3'] = $addressModel->getListByPid($v1['id']);
                  }
              }
          }
        }
        Common::appLog('common/getAddress',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$list);
    }

    //支付需要帮助H5界面
    public function questionAction(){
        $this->display("question");
    }

    //用户服务条款（注册帐号-《用户服务条款》）
    public function userServiceClauseAction(){
        $this->display("userServiceClause");
    }

    //开通驿站声明（创建普通驿站-《开通驿站声明》）
    public function stageStatementAction(){
        $this->display("stageStatement");
    }

    //开通驿站服务协议（创建服务驿站-《开通驿站服务协议》）
    public function serviceAgreementAction(){
        $this->display("serviceAgreement");
    }

    //驿站支付协议（创建服务驿站-《驿站支付协议》）
    public function stagePayAgreementAction(){
        $this->display("stagePayAgreement");
    }

    //获取评论列表
    public function getCommentListAction(){
        $token = $this->getRequest()->get('token');
        $uid = 0;
        if($token){
            $user = Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(4, "非法登录用户");
            }
            $uid = $user['uid'];
            $this->getView()->user = $user;
        }

        $type = $this->getRequest()->get('type');
        $obj_id = $this->getRequest()->get('obj_id');
        $version = $this->getRequest()->get('version');
        $page = intval($this->getRequest()->get('page'));
        $size = intval($this->getRequest()->get('size'));
        $size = $size ? $size : 3;
        if(!in_array($type,$this->comment_type_arr) || $type == ''){
            Common::echoAjaxJson(2,'评论类型不正确');
        }
        if(!$obj_id){
            Common::echoAjaxJson(3,'评论对象不能为空');
        }
        $feedModel = new FeedModel();
        $rs = $feedModel->getCommentList($uid,$type,$obj_id,$page,$size,1);
        $this->getView()->commentList = $rs;
        $this->getView()->token = $token;
        $this->getView()->obj_id = $obj_id;
        $this->getView()->type = $type;
        $this->getView()->version = $version;
        $html_str = $this->render("comment");
        Common::echoAjaxJson(1,"加载成功",array('html'=>$html_str,'size'=>$rs['size']));
    }

    //获取打赏列表
    public function getRewardListAction(){
        $token = $this->getRequest()->get('token');
        $uid = 0;
        if($token){
            $user = Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(4, "非法登录用户");
            }
            $uid = $user['uid'];
            $this->getView()->user = $user;
        }
        $type = $this->getRequest()->get('type');
        $obj_id = $this->getRequest()->get('obj_id');
        $page = intval($this->getRequest()->get('page'));
        $size = intval($this->getRequest()->get('size'));
        $size = $size ? $size : 4;
        if(!in_array($type,$this->reward_type_arr)){
            Common::echoAjaxJson(2,'打赏类型不正确');
        }
        if(!$obj_id){
            Common::echoAjaxJson(3,'打赏对象不能为空');
        }
        $messageModel = new MessageModel();
        $rs = $messageModel->getRewardList($type,$obj_id,$page,$size,$uid);
        $this->getView()->rewardList = $rs;
        $this->getView()->token = $token;
        $this->getView()->obj_id = $obj_id;
        $html_str = $this->render("rewardList");
        Common::echoAjaxJson(1,"加载成功",$html_str);
    }
    public function previewAction(){
        $id = $this->getRequest()->get('id');
        $commonModel = new CommonModel();
        $data = $commonModel->getPreview($id);
        if($data){
            $this->getView()->app_css = 'topic';
            if($data['type']==1){
                $this->getView()->topicInfo = json_decode($data['content'],true);
                $this->display("topicPreview");
            }elseif($data['type']==2){
                $this->getView()->eventInfo = json_decode($data['content'],true);
                $this->getView()->eventTypeClass =$eventTypeClass = array(
                    1 => 'btn_green', 3 => 'btn_bluer', 6 => 'btn_orange', 7 => 'btn_orange' , 8 => 'btn_orange'
                );
                $this->display("eventPreview");
            }elseif($data['type']==3){
                $this->getView()->goodsInfo = json_decode($data['content'],true);
                $this->display("stagegoodsPreview");
            }
        }
    }
    public function serviceAction(){
        $version = $this->getRequest()->get("version") ? $this->getRequest()->get("version") : APP_VERSION;
        $this->getView()->version = $version;
        $this->display("service");
    }
    public function commentListAction(){
        $id = $this->getRequest()->get('id');
        $type = $this->getRequest()->get('type');
        $data['token'] = $this->getRequest()->get('token');
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid=$user['uid'];
            $this->getView()->user = $user;
        }
        $version = $this->getRequest()->get("version") ? $this->getRequest()->get("version") : APP_VERSION;
        $feedModel = new FeedModel();
        $commentList = $feedModel->getCommentList($uid,$type,$id,1,500,1);
        $this->getView()->token = $data['token'];
        $this->getView()->commentList = $commentList;
        $this->getView()->version = $version;
        $this->getView()->app_css = 'topic';
        $this->display("commentList");
    }
    //1可以强更 2屏蔽
    public function appUpdateTypeAction(){
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        $rs = $userModel->verifyAppVersion(1,$version);
        if(!$rs){
            Common::echoAjaxJson(2,"屏蔽更新");
        }
        $data = array();
        if($rs&&$rs['status']==0){
            Common::echoAjaxJson(5, "最新版本");
        }else{
            $url = $userModel->getNewVersion(1);
            $info = $userModel->getNewVersionInfo($url['id']);
            foreach($info as $v){
                $data[] = $v['content'];
            }
        }
        Common::appLog('common/appUpdateType',$this->startTime,$version);
        if(DOMAIN_PARAMETER=='d'||DOMAIN_PARAMETER=='t'){
            Common::echoAjaxJson(2,"屏蔽更新",$data);
        }else{
            Common::echoAjaxJson(1,"可以更新",$data);
        }
    }
    public function eventPartakeListAction(){
        $id =  $this->getRequest()->get('id');
        $price_type =  $this->getRequest()->get('price_type');
        $version = $this->getRequest()->get('version') ? $this->getRequest()->get('version') : APP_VERSION;
        $data['token'] = $this->getRequest()->get('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $this->getView()->user = $user;
        }
        $eventModel = new EventModel();
        if($price_type==1){
            $partakeList = $eventModel->getPartakeForHtml($id,500);
        }else{
            $partakeList = $eventModel->getOrdersForHtml($id,500);
        }
        $this->getView()->partakeList = $partakeList;
        $this->getView()->app_css = 'topic';
        $this->display("eventPartakeList");
    }
    public function goodsBuyListAction(){
        $id =  $this->getRequest()->get('id');
        $version = $this->getRequest()->get('version') ? $this->getRequest()->get('version') : APP_VERSION;
        $data['token'] = $this->getRequest()->get('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $this->getView()->user = $user;
        }
        $stagegoodsModel = new StagegoodsModel();
        $buyList = $stagegoodsModel->getBuyListForHtml($id,500);
        $this->getView()->buyList = $buyList;
        $this->getView()->app_css = 'topic';
        $this->display("goodsBuyList");
    }
    //获取物流公司
    public function getLogisticsCompanyAction(){
        $data['token'] = $this->getRequest()->get('token');
        $version = $this->getRequest()->get('version') ? $this->getRequest()->get('version') : APP_VERSION;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $list = array(
            array(
                'group'=>'常用快递',
                'list'=>array(
                    array('name'=>'EMS','type'=>'EMS','tel'=>'40080-11183','img'=>PUBLIC_DOMAIN.'EMS.png'),
                    array('name'=>'申通','type'=>'STO','tel'=>'95543','img'=>PUBLIC_DOMAIN.'STO.png'),
                    array('name'=>'圆通','type'=>'YTO','tel'=>'021-69777999','img'=>PUBLIC_DOMAIN.'YTO.png'),
                    array('name'=>'中通','type'=>'ZTO','tel'=>'95311','img'=>PUBLIC_DOMAIN.'ZTO.png'),
                    array('name'=>'宅急送','type'=>'ZJS','tel'=>' 400-6789-000','img'=>PUBLIC_DOMAIN.'ZJS.png'),
                    array('name'=>'韵达','type'=>'YUNDA','tel'=>'95546','img'=>PUBLIC_DOMAIN.'YUNDA.png'),
                    array('name'=>'天天','type'=>'TTKDEX','tel'=>'4001-888-888','img'=>PUBLIC_DOMAIN.'TTKDEX.png'),
                    array('name'=>'顺丰','type'=>'SFEXPRESS','tel'=>'95338','img'=>PUBLIC_DOMAIN.'SFEXPRESS.png'),
                    array('name'=>'德邦','type'=>'DEPPON','tel'=>'95353','img'=>PUBLIC_DOMAIN.'DEPPON.png'),
                    array('name'=>'邮政平邮','type'=>'CHINAPOST','tel'=>'11185','img'=>PUBLIC_DOMAIN.'CHINAPOST.png'),
                )
            ),
            array(
                'group'=>'A',
                'list'=>array(
                    array('name'=>'Aramex','type'=>'ARAMEX','tel'=>'400-631-8388','img'=>PUBLIC_DOMAIN.'ARAMEX.png'),
                    array('name'=>'安捷','type'=>'ANJELEX','tel'=>'400-619-7776','img'=>PUBLIC_DOMAIN.'ANJELEX.png'),
                    array('name'=>'安信达','type'=>'ANXINDA','tel'=>'021-54224681','img'=>PUBLIC_DOMAIN.'ANXINDA.png'),
                    array('name'=>'AAE','type'=>'AAEWEB','tel'=>'400-6100-400','img'=>PUBLIC_DOMAIN.'AAE.png'),
                    array('name'=>'安能快递','type'=>'ANEEX','tel'=>'','img'=>PUBLIC_DOMAIN.'ANEEX.png'),
                    array('name'=>'安能','type'=>'ANE','tel'=>'40010-40088','img'=>PUBLIC_DOMAIN.'ANE.png'),
                )
            ),
            array(
                'group'=>'B',
                'list'=>array(
                    array('name'=>'百福东方','type'=>'EES','tel'=>'400-706-0609','img'=>PUBLIC_DOMAIN.'EES.png'),
                    array('name'=>'百世快运','type'=>'BSKY','tel'=>'','img'=>PUBLIC_DOMAIN.'BSKY.png'),
                    array('name'=>'百世快递','type'=>'HTKY','tel'=>'4009565656','img'=>PUBLIC_DOMAIN.'HTKY.png'),
                )
            ),
            array(
                'group'=>'C',
                'list'=>array(
                    array('name'=>'程光','type'=>'FLYWAYEX','tel'=>'0064-09-2759536','img'=>PUBLIC_DOMAIN.'FLYWAYEX.png'),
                )
            ),
            array(
                'group'=>'D',
                'list'=>array(
                    array('name'=>'德邦','type'=>'DEPPON','tel'=>'95353','img'=>PUBLIC_DOMAIN.'DEPPON.png'),
                    array('name'=>'DHL','type'=>'DHL','tel'=>'800-810-8000','img'=>PUBLIC_DOMAIN.'DHL.png'),
                    array('name'=>'大田','type'=>'DTW','tel'=>'400-626-1166','img'=>PUBLIC_DOMAIN.'DTW.png'),
                    array('name'=>'D速','type'=>'DEXP','tel'=>'0531-88636363','img'=>PUBLIC_DOMAIN.'DEXP.png'),
                )
            ),
            array(
                'group'=>'E',
                'list'=>array(
                    array('name'=>'EWE','type'=>'EWE','tel'=>'1300-09-6655','img'=>PUBLIC_DOMAIN.'EWE.png'),
                )
            ),
            array(
                'group'=>'F',
                'list'=>array(
                    array('name'=>'FedEx国际','type'=>'FEDEXIN','tel'=>'800-988-1888','img'=>PUBLIC_DOMAIN.'FedEx.png'),
                    array('name'=>'富腾达','type'=>'FTD','tel'=>'0064-09-4432342','img'=>PUBLIC_DOMAIN.'FTD.png'),
                    array('name'=>'FedEx','type'=>'FEDEX','tel'=>'800-988-1888','img'=>PUBLIC_DOMAIN.'FedEx.png'),
                    array('name'=>'凤凰','type'=>'PHOENIXEXP','tel'=>'010-85826200','img'=>PUBLIC_DOMAIN.'PHOENIXEXP.png'),
                    array('name'=>'飞洋','type'=>'GCE','tel'=>'010-87785733','img'=>PUBLIC_DOMAIN.'GCE.png'),
                )
            ),
            array(
                'group'=>'G',
                'list'=>array(
                    array('name'=>'共速达','type'=>'GSD','tel'=>'400-111-0005','img'=>PUBLIC_DOMAIN.'GSD.png'),
                    array('name'=>'能达','type'=>'ND56','tel'=>'400-6886-765','img'=>PUBLIC_DOMAIN.'ND56.png'),
                    array('name'=>'国通','type'=>'GTO','tel'=>'4001-111-123','img'=>PUBLIC_DOMAIN.'GTO.png'),
                )
            ),
            array(
                'group'=>'H',
                'list'=>array(
                    array('name'=>'恒路','type'=>'HENGLU','tel'=>'400-182-6666','img'=>PUBLIC_DOMAIN.'HENGLU.png'),
                    array('name'=>'华企','type'=>'HQKY','tel'=>'400-626-2356','img'=>PUBLIC_DOMAIN.'HQKY.png'),
                    array('name'=>'鸿远','type'=>'HYE','tel'=>'','img'=>PUBLIC_DOMAIN.'HYE.png'),
                    array('name'=>'黑狗','type'=>'BLACKDOG','tel'=>'400-106-1234','img'=>PUBLIC_DOMAIN.'BLACKDOG.png'),
                )
            ),
            array(
                'group'=>'J',
                'list'=>array(
                    array('name'=>'嘉里物流','type'=>'KERRY','tel'=>'852-2410-3600','img'=>PUBLIC_DOMAIN.'KERRY.png'),
                    array('name'=>'佳吉','type'=>'JIAJI','tel'=>'400-820-5566','img'=>PUBLIC_DOMAIN.'JIAJI.png'),
                    array('name'=>'佳怡','type'=>'JIAYI','tel'=>'400-631-9999','img'=>PUBLIC_DOMAIN.'JIAYI.png'),
                    array('name'=>'京广','type'=>'KKE','tel'=>'852-23329918','img'=>PUBLIC_DOMAIN.'KKE.png'),
                    array('name'=>'加运美','type'=>'TMS','tel'=>'0769-85515555','img'=>PUBLIC_DOMAIN.'TMS.png'),
                    array('name'=>'急先达','type'=>'JOUST','tel'=>'400-694-1256','img'=>PUBLIC_DOMAIN.'JOUST.png'),
                    array('name'=>'晋越','type'=>'PEWKEE','tel'=>'','img'=>PUBLIC_DOMAIN.'PEWKEE.png'),
                    array('name'=>'京东','type'=>'JD','tel'=>'','img'=>PUBLIC_DOMAIN.'JD.png'),
                )
            ),
            array(
                'group'=>'K',
                'list'=>array(
                    array('name'=>'跨越','type'=>'KYEXPRESS','tel'=>'','img'=>PUBLIC_DOMAIN.'KYEXPRESS.png'),
                    array('name'=>'快捷','type'=>'FASTEXPRESS','tel'=>'4008-333-666','img'=>PUBLIC_DOMAIN.'FASTEXPRESS.png'),
                )
            ),
            array(
                'group'=>'L',
                'list'=>array(
                    array('name'=>'联昊通','type'=>'LTS','tel'=>'400-888-8887','img'=>PUBLIC_DOMAIN.'LTS.png'),
                    array('name'=>'龙邦','type'=>'LBEX','tel'=>'021-59218889','img'=>PUBLIC_DOMAIN.'LBEX.png'),
                )
            ),
            array(
                'group'=>'M',
                'list'=>array(
                    array('name'=>'民航','type'=>'CAE','tel'=>'4008-17-4008','img'=>PUBLIC_DOMAIN.'CAE.png'),
                )
            ),
            array(
                'group'=>'P',
                'list'=>array(
                    array('name'=>'平安快递','type'=>'EFSPOST','tel'=>'0773-2315320','img'=>PUBLIC_DOMAIN.'EFSPOST.png'),
                    array('name'=>'配思航宇','type'=>'PEISI','tel'=>'010-65489928','img'=>PUBLIC_DOMAIN.'PEISI.png'),
                )
            ),
            array(
                'group'=>'Q',
                'list'=>array(
                    array('name'=>'全峰','type'=>'QFKD','tel'=>'4001-000-001','img'=>PUBLIC_DOMAIN.'QFKD.png'),
                    array('name'=>'全一','type'=>'APEX','tel'=>'400-663-1111','img'=>PUBLIC_DOMAIN.'APEX.png'),
                    array('name'=>'全晨','type'=>'QCKD','tel'=>'0769-82026703','img'=>PUBLIC_DOMAIN.'QCKD.png'),
                )
            ),
            array(
                'group'=>'R',
                'list'=>array(
                    array('name'=>'如风达','type'=>'RFD','tel'=>'400-010-6660','img'=>PUBLIC_DOMAIN.'RFD.png'),
                )
            ),
            array(
                'group'=>'S',
                'list'=>array(
                    array('name'=>'盛辉','type'=>'SHENGHUI','tel'=>'400-822-2222','img'=>PUBLIC_DOMAIN.'SHENGHUI.png'),
                    array('name'=>'苏宁','type'=>'SUNING','tel'=>'95315','img'=>PUBLIC_DOMAIN.'SUNING.png'),
                    array('name'=>'三态','type'=>'SFC','tel'=>'400-881-8106','img'=>PUBLIC_DOMAIN.'SFC.png'),
                    array('name'=>'盛丰','type'=>'SFWL','tel'=>'4008-556688','img'=>PUBLIC_DOMAIN.'SFWL.png'),
                    array('name'=>'速尔','type'=>'SURE','tel'=>'400-158-9888','img'=>PUBLIC_DOMAIN.'SURE.png'),
                )
            ),
            array(
                'group'=>'T',
                'list'=>array(
                    array('name'=>'天地华宇','type'=>'HOAU','tel'=>'400-808-6666','img'=>PUBLIC_DOMAIN.'HOAU.png'),
                    array('name'=>'TNT','type'=>'TNT','tel'=>'800-820-9868','img'=>PUBLIC_DOMAIN.'TNT.png'),
                )
            ),
            array(
                'group'=>'U',
                'list'=>array(
                    array('name'=>'UPS','type'=>'UPS','tel'=>'400-820-8388','img'=>PUBLIC_DOMAIN.'UPS.png'),
                )
            ),
            array(
                'group'=>'W',
                'list'=>array(
                    array('name'=>'万庚','type'=>'VANGEN','tel'=>'','img'=>PUBLIC_DOMAIN.'VANGEN.png'),
                    array('name'=>'万家物流','type'=>'WANJIA','tel'=>'4001-156-561','img'=>PUBLIC_DOMAIN.'WANJIA.png'),
                    array('name'=>'文捷航空','type'=>'GZWENJIE','tel'=>'020-36680069','img'=>PUBLIC_DOMAIN.'GZWENJIE.png'),
                    array('name'=>'万象','type'=>'EWINSHINE','tel'=>'400-820-8088','img'=>PUBLIC_DOMAIN.'EWINSHINE.png'),
                )
            ),
            array(
                'group'=>'X',
                'list'=>array(
                    array('name'=>'信丰','type'=>'XFEXPRESS','tel'=>'4008-306-333','img'=>PUBLIC_DOMAIN.'XFEXPRESS.png'),
                    array('name'=>'新邦','type'=>'XBWL','tel'=>'4008-000-222','img'=>PUBLIC_DOMAIN.'XBWL.png'),
                )
            ),
            array(
                'group'=>'Y',
                'list'=>array(
                    array('name'=>'宜送','type'=>'YIEXPRESS','tel'=>'','img'=>PUBLIC_DOMAIN.'YIEXPRESS.png'),
                    array('name'=>'优速','type'=>'UC56','tel'=>'400-1111-119','img'=>PUBLIC_DOMAIN.'UC56.png'),
                    array('name'=>'易达通','type'=>'QEXPRESS','tel'=>'0064-09-8388681','img'=>PUBLIC_DOMAIN.'QEXPRESS.png'),
                    array('name'=>'运通','type'=>'YTEXPRESS','tel'=>'0769-81156999','img'=>PUBLIC_DOMAIN.'YTEXPRESS.png'),
                    array('name'=>'越丰','type'=>'YFEXPRESS','tel'=>'(852) 2390 9969 ','img'=>PUBLIC_DOMAIN.'YFEXPRESS.png'),
                    array('name'=>'亚风','type'=>'BROADASIA','tel'=>'4001-000-002','img'=>PUBLIC_DOMAIN.'BROADASIA.png'),
                    array('name'=>'远成','type'=>'YCGWL','tel'=>'400-820-1646','img'=>PUBLIC_DOMAIN.'YCGWL.png'),
                    array('name'=>'原飞航','type'=>'YFHEX','tel'=>'0755-29778899','img'=>PUBLIC_DOMAIN.'YFHEX.png'),
                    array('name'=>'源安达','type'=>'YADEX','tel'=>'0769-85021875','img'=>PUBLIC_DOMAIN.'YADEX.png'),
                )
            ),
            array(
                'group'=>'Z',
                'list'=>array(
                    array('name'=>'中铁物流','type'=>'ZTKY','tel'=>'400-000-5566','img'=>PUBLIC_DOMAIN.'ZTKY.png'),
                    array('name'=>'中邮','type'=>'CNPL','tel'=>'11183','img'=>PUBLIC_DOMAIN.'CNPL.png'),
                    array('name'=>'中铁快运','type'=>'CRE','tel'=>'95572','img'=>PUBLIC_DOMAIN.'CRE.png'),
                    array('name'=>'中国东方','type'=>'COE','tel'=>'755-83575000','img'=>PUBLIC_DOMAIN.'COE.png'),
                )
            ),
        );
        Common::appLog('common/getLogisticsCompany',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$list);
    }

    //获取通讯录好友
    public function getMobileBookFriendAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->get('version') : APP_VERSION;
        $user_json = $this->getRequest()->getPost('user_json');
        $user_array = json_decode($user_json,true);
        $userModel = new UserModel();
        $data = $userModel->getMobileBookFriend($user['uid'],$user_array);
        Common::appLog('common/getMobileBookFriend',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$data);
    }
    //获取佣金率
    public function getCommissionRateAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->get('version') : APP_VERSION;
        $array = array(
            array('name'=>'1%','number'=>'0.01'),array('name'=>'2%','number'=>'0.02'),array('name'=>'3%','number'=>'0.03'),array('name'=>'4%','number'=>'0.04'),
            array('name'=>'5%','number'=>'0.05'),array('name'=>'6%','number'=>'0.06'),array('name'=>'7%','number'=>'0.07'),array('name'=>'8%','number'=>'0.08'),
            array('name'=>'9%','number'=>'0.09'),array('name'=>'10%','number'=>'0.1'),array('name'=>'11%','number'=>'0.11'),array('name'=>'12%','number'=>'0.12'),
            array('name'=>'13%','number'=>'0.13'),array('name'=>'14%','number'=>'0.14'),array('name'=>'15%','number'=>'0.15'),array('name'=>'16%','number'=>'0.16'),
            array('name'=>'17%','number'=>'0.17'),array('name'=>'18%','number'=>'0.18'),array('name'=>'19%','number'=>'0.19'),array('name'=>'20%','number'=>'0.2'),
            array('name'=>'21%','number'=>'0.21'),array('name'=>'22%','number'=>'0.22'),array('name'=>'23%','number'=>'0.23'),array('name'=>'24%','number'=>'0.24'),
            array('name'=>'25%','number'=>'0.25'),array('name'=>'26%','number'=>'0.26'),array('name'=>'27%','number'=>'0.27'),array('name'=>'28%','number'=>'0.28'),
            array('name'=>'29%','number'=>'0.29'),array('name'=>'30%','number'=>'0.3'),array('name'=>'31%','number'=>'0.31'),array('name'=>'32%','number'=>'0.32'),
            array('name'=>'33%','number'=>'0.33'),array('name'=>'34%','number'=>'0.34'),array('name'=>'35%','number'=>'0.35'),array('name'=>'36%','number'=>'0.36'),
            array('name'=>'37%','number'=>'0.37'),array('name'=>'38%','number'=>'0.38'),array('name'=>'39%','number'=>'0.39'),array('name'=>'40%','number'=>'0.4'),
            array('name'=>'41%','number'=>'0.41'),array('name'=>'42%','number'=>'0.42'),array('name'=>'43%','number'=>'0.43'),array('name'=>'44%','number'=>'0.44'),
            array('name'=>'45%','number'=>'0.45'),array('name'=>'46%','number'=>'0.46'),array('name'=>'47%','number'=>'0.47'),array('name'=>'48%','number'=>'0.48'),
            array('name'=>'49%','number'=>'0.49'),array('name'=>'50%','number'=>'0.5'),
        );
        Common::appLog('common/getCommissionRate',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$array);
    }
    //分享有奖介绍
    public function spIntroduceAction(){
        $this->display("spIntroduce");
    }
    //分享有奖协议
    public function spAgreementAction(){
        $data['token'] = $this->getRequest()->get('token');
        $this->getView()->token = $data['token'];
        $this->display("spAgreement");
    }

    //推广联盟-用户分享有奖规则
    public function userSharePrizeRuleAction(){
        $this->display("userSharePrizeRule");
    }

    //推广联盟-卖家分享有奖协议
    public function sellerShareAgreementAction(){
        $this->display("sellerShareAgreement");
    }
}