<?php
class CenterController extends Yaf_Controller_Abstract {
    public $eventType = array(
        1 => '活动', 3 => '培训', 6=>'展览',7=>'演出',8=>'展演'
    );
    public function init(){
        $this->startTime = microtime(true);
    }
    //我的知己
    public function friendAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $sid  =$this->getRequest()->getPost("sid");
        $followModel = new FollowModel();
        $list = $followModel->getFriendList($user['uid'],$sid,0,5000);
        Common::appLog('center/friend',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list['list'] ?$list['list']:array());
    }
    //我的收藏
    public function getCollectListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $page = (int)$this->getRequest()->get('page');
        $size = (int)$this->getRequest()->get('size');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $list = array();
        $page = $page ? $page : 1;
        $size = $size ? $size : 10;
        $start = ($page-1)*$size;
        $collectModel = new CollectModel();
        $feedModel = new FeedModel();
        $feeds = $collectModel->getFeedList($user['uid'],$start,$size,0);
        $data = $feedModel->getCollectData($feeds['list'],0,$user['uid']);
        foreach($data as $k=>$v){
            if($v['feed_type']==4||$v['feed_type']==10){
                $list[$k]['id'] = $v['id'];
                $list[$k]['sid'] = $v['sid'];
                $list[$k]['title'] = $v['title'];
                $list[$k]['summary'] = $v['summary'];
                if($v['feed_type']==4){
                    $list[$k]['url'] =  I_DOMAIN.'/t/'.$v['id'].'?token='.$_POST['token'].'&version='.$version;
                }elseif($v['feed_type']==10){
                    $list[$k]['url'] =  I_DOMAIN.'/e/'.$v['id'].'?token='.$_POST['token'].'&version='.$version;
                }
                if($v['feed_type']==4&&$v['img_src']){
                    $list[$k]['cover'] = Common::show_img($v['img_src'][0],4,160,160);
                }elseif($v['feed_type']==10&&$v['cover']){
                    $list[$k]['cover'] = Common::show_img($v['cover'],4,160,160);
                }else{
                    $list[$k]['cover'] = '';
                }
                $list[$k]['feed_type'] = $v['feed_type'];
                if($v['feed_type']==4){
                    $list[$k]['type_name'] = '帖子';
                }
                if($v['feed_type']==10){
                    $list[$k]['type_name'] = $this->eventType[$v['type']];
                }
                $list[$k]['add_time'] = $v['add_time'];
                $list[$k]['nick_name'] = $v['user']['nick_name'];
                $list[$k]['type'] = $v['user']['type'];
                $list[$k]['avatar'] = Common::show_img($v['user']['avatar'],1,160,160);
            }elseif($v['feed_type']==9){
                $moodModel = new MoodModel();
                $shareInfo = $moodModel->getShare($v['id'],$user['uid'],0,2,$version,$_POST['token']);
                if($shareInfo['type']==1&&$shareInfo['share_data']){
                    if($shareInfo['share_data']['img']){
                        $list[$k]['cover'] = Common::show_img($shareInfo['share_data']['img'][0]['img'],4,160,160);
                    }else{
                        $list[$k]['cover'] = '';
                    }
                }
                if($shareInfo['type']==4&&$shareInfo['share_data']){
                    if(isset($shareInfo['share_data']['img_src'][0])){
                        $list[$k]['cover'] = Common::show_img($shareInfo['share_data']['img_src'][0],4,160,160);
                    }else{
                        $list[$k]['cover'] = '';
                    }
                }
                if($shareInfo['type']==10&&$shareInfo['share_data']){
                    if($shareInfo['share_data']['cover']){
                        $list[$k]['cover'] = Common::show_img($shareInfo['share_data']['cover'],4,160,160);
                    }else{
                        $list[$k]['cover'] = '';
                    }
                }
                $list[$k]['id'] = $v['id'];
                $list[$k]['title'] = $v['content'];
                $list[$k]['type'] = $v['type'];
                $list[$k]['feed_type'] = $v['feed_type'];
                $list[$k]['add_time'] = $v['add_time'];
                $list[$k]['nick_name'] = $v['user']['nick_name'];
                $list[$k]['type'] = $v['user']['type'];
                $list[$k]['avatar'] = Common::show_img($v['user']['avatar'],1,160,160);
            }elseif($v['feed_type']==3){
                $list[$k]['id'] = $v['id'];
                $list[$k]['title'] = $v['title'];
                $list[$k]['feed_type'] = $v['feed_type'];
                $list[$k]['add_time'] = $v['add_time'];
                $list[$k]['nick_name'] = $v['user']['nick_name'];
                $list[$k]['type'] = $v['user']['type'];
                $list[$k]['avatar'] = Common::show_img($v['user']['avatar'],1,160,160);
                $list[$k]['url'] =  I_DOMAIN.'/b/'.$v['id'].'?token='.$_POST['token'].'&version='.$version;
            }elseif($v['feed_type']==1){
                $list[$k]['id'] = $v['id'];
                $list[$k]['title'] = $v['content'];
                if($v['img']){
                    $list[$k]['cover'] = Common::show_img($v['img'][0]['img'],4,160,160);
                }else{
                    $list[$k]['cover'] = '';
                }
                $list[$k]['feed_type'] = $v['feed_type'];
                $list[$k]['add_time'] = $v['add_time'];
                $list[$k]['nick_name'] = $v['user']['nick_name'];
                $list[$k]['type'] = $v['user']['type'];
                $list[$k]['avatar'] = Common::show_img($v['user']['avatar'],1,160,160);
            }elseif($v['feed_type']==2){
                $list[$k]['id'] = $v['photo']['id'];
                $list[$k]['title'] = $v['photo']['intro'];
                $list[$k]['cover'] = Common::show_img($v['photo']['album_img'],4,160,160);
                $list[$k]['feed_type'] = $v['feed_type'];
                $list[$k]['add_time'] = $v['add_time'];
                $list[$k]['nick_name'] = $v['user']['nick_name'];
                $list[$k]['type'] = $v['user']['type'];
                $list[$k]['avatar'] = Common::show_img($v['user']['avatar'],1,160,160);
            }elseif($v['feed_type']==12){
                $list[$k]['id'] = $v['id'];
                $list[$k]['title'] = $v['name'];
                $list[$k]['feed_type'] = $v['feed_type'];
                $list[$k]['add_time'] = $v['add_time'];
                $list[$k]['nick_name'] = $v['user']['nick_name'];
                $list[$k]['type'] = $v['user']['type'];
                $list[$k]['avatar'] = Common::show_img($v['user']['avatar'],1,160,160);
                $list[$k]['url'] =  I_DOMAIN.'/g/'.$v['id'].'?token='.$_POST['token'].'&version='.$version;
                $list[$k]['cover'] = Common::show_img($v['cover'],4,160,160);
            }else{
                $list[$k]['id'] = $v['id'];
                $list[$k]['title'] = $v['title'];
                $list[$k]['feed_type'] = $v['feed_type'];
                $list[$k]['add_time'] = $v['add_time'];
                $list[$k]['nick_name'] = $v['user']['nick_name'];
                $list[$k]['type'] = $v['user']['type'];
                $list[$k]['avatar'] = Common::show_img($v['user']['avatar'],1,160,160);
            }
        }
        Common::appLog('center/getCollectList',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$list ? $list : array());
    }
    //用户关注
    public function getAttListAction(){
        $data['token'] = $this->getRequest()->get('token');
        $user['uid'] = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $uid = $user['uid'];
        $to_uid = $this->getRequest()->get('uid');//查看用户的uid
        $page = intval($this->getRequest()->getPost('page'));//页码
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==20)?$this->getRequest()->getPost('size'):20;//每页显示条数
        $last_time = $this->getRequest()->getPost('last_time');//每页显示条数
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$to_uid){
            $to_uid = $user['uid'];
        }
        $followModel = new FollowModel();
        if($page){
            $start = ($page-1)*$size;
            $list = $followModel->getAttList($to_uid,$start,(int)$size,$uid);
        }else{
            $list = $followModel->getAttListByLastTime($to_uid,$last_time,(int)$size,$uid);
        }
        Common::appLog('center/getAttList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list ? $list :array());
    }
    //用户粉丝
    public function getFansListAction(){
        $data['token'] = $this->getRequest()->get('token');
        $user['uid'] = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $uid = $user['uid'];
        $to_uid = $this->getRequest()->get('uid');//查看用户的uid
        $page = intval($this->getRequest()->getPost('page'));//页码
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==20)?$this->getRequest()->getPost('size'):20;//每页显示条数
        $last_time = $this->getRequest()->getPost('last_time');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$to_uid){
            $to_uid = $user['uid'];
        }
        $followModel = new FollowModel();
        if($page){
            $start = ($page-1)*$size;
            $list = $followModel->getFansList($to_uid,$start,(int)$size,$uid);
        }else{
            $list = $followModel->getFansListByLastTime($to_uid,$last_time,(int)$size,$uid);
        }
        $followModel->updateIsRead($uid);
        Common::appLog('center/getFansList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list ? $list :array());
    }
    //用户添加意见
    public function addAdviceAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(6, "非法登录用户");
        }
        $content = $this->getRequest()->getPost('content');//意见内容
        $email = $this->getRequest()->getPost('email');//邮箱
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$content){
            Common::echoAjaxJson(2, "意见内容为空");
        }
        $content_len = mb_strlen($content,'utf-8');
        if($content_len>4294967295){
            Common::echoAjaxJson(3, "内容过长");
        }
        if($email){
            if(!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 50){
                Common::echoAjaxJson(4,'请输入正确的邮箱地址');
            }
        }else{
            $email = '';
        }
        $userModel = new UserModel();
        $rs = $userModel->addAdvice($user['uid'],$content,$email);
        if(!$rs){
            Common::echoAjaxJson(5,'提交失败');
        }
        Common::appLog('center/addAdvice',$this->startTime,$version);
        Common::echoAjaxJson(1,'提交成功,您的意见我们会尽快采纳');
    }

    //获取喜欢记录列表界面
    public function likeListAction(){
        $data['token'] = $this->getRequest()->get('token');
        $uid = '';
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid=$user['uid'];
            $this->getView()->token = $data['token'];
            $this->getView()->user = $user;
        }
        $type = $this->getRequest()->get('type');
        $obj_id = $this->getRequest()->get('obj_id');
        $likeModel = new LikeModel();
        $this->getView()->user_list = $likeModel->getAllLikeList($obj_id,$type,$uid);
        $this->display("likeList");
    }
    //获取参与记录列表界面
    public function partakeListAction(){
        $data['token'] = $this->getRequest()->get('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $this->getView()->token = $data['token'];
            $this->getView()->user = $user;
        }
        $type = $this->getRequest()->get('event_type');
        $id = $this->getRequest()->get('id');
        $eventModel = new EventModel();
        $list = $eventModel->partakeListByEid($id,0,10);
        foreach($list as $v){
            $userList[] = $v['user_info'];
        }
        $list['size'] = $eventModel->partakeNumByEid($id);
        $this->getView()->user_list = $userList;
        $this->display("likeList");
    }
    //获取打赏记录列表界面
    public function rewardListAction(){
        $data['token'] = $this->getRequest()->get('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $this->getView()->token = $data['token'];
            $this->getView()->user = $user;
        }
        $type = $this->getRequest()->get('type');
        $obj_id = $this->getRequest()->get('obj_id');
        $messageModel = new MessageModel();
        $rs = $messageModel->getRewardList($type,$obj_id,1,200);
        $this->getView()->user_list = $rs['list'];
        $this->display("rewardList");
    }
    //3.8以下版本点赞
    public function addLikeAction(){
        $user = Common::isLogin($_POST,1);
        if (!$user) {
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $id = (int)$this->getRequest()->getPost('id');
        $type = (int)$this->getRequest()->getPost('type');
        $token = $this->getRequest()->getPost('token');
        $origin = $this->getRequest()->getPost('origin');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $rs = Common::http(OPEN_DOMAIN . '/feedapi/addLike', array('uid' => $user['uid'], 'id' => $id, 'type' => $type), 'POST');
        $rs = json_decode($rs, true);
        if ($rs['status'] == 2) {
            Common::echoAjaxJson(2, "喜欢的内容类型不正确");
        }
        if ($rs['status'] == 3) {
            Common::echoAjaxJson(3, "喜欢的内容不存在");
        }
        if ($rs['status'] == 4) {
            Common::echoAjaxJson(4, "请勿重复喜欢");
        }
        $likeModel = new LikeModel();
        $data = $likeModel->likeList($id, $type, 1, 6, $user['uid']);
        $data['is_like'] = 1;
        $data['feed_type'] = $type;
        $data['id'] = $id;
        $this->getView()->like_list = $data;
        $this->getView()->token = $token;
        if ($origin == 1) {//服务信息、帖子、商品
            $html_str = $this->render("likeUserList");
        } else {
            $data = $likeModel->likeList($id, $type, 1, 5, 0);
            foreach ($data['list'] as $v) {
                if ($v['uid'] == $user['uid']) {
                    $html_str = $v;
                }
            }
            $html_str['avatar'] = Common::show_img($html_str['avatar'], 1, 160, 160);
        }
        $feedModel = new FeedModel();
        $objInfo = $feedModel->getDataUidByTypeAndId($type, $id);
        if ($user['uid'] != $objInfo['uid']){
            $feedModel->initJpush($objInfo['uid'], '才府文化圈有一条新点赞');
        }
        Common::appLog('feed/addLike',$this->startTime,$version);
        if($origin==1){
            Common::echoAjaxJson(1,"喜欢成功",array('html'=>$html_str,'size'=>$data['size']));
        }else{
            Common::echoAjaxJson(1,"喜欢成功",$html_str);
        }
    }
    //3.8版本点赞
    public function likeAction()
    {
        $user = Common::isLogin($_POST, 1);
        if (!$user) {
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $id = (int)$this->getRequest()->getPost('id');
        $type = (int)$this->getRequest()->getPost('type');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $likeModel = new LikeModel();
        $likeModel->initLike($user['uid'], $id, $type,'才府文化圈有一条新点赞');
        Common::appLog('feed/addLike', $this->startTime, $version);
        Common::echoAjaxJson(1, "喜欢成功");
    }

    //我-设置中新手引导图
    public function guideAction(){
        $origin = (int)$this->getRequest()->get('origin') ? (int)$this->getRequest()->get('origin') : 3;
        $this->getView()->origin = $origin;
        $this->display("guide");
    }
    //我的收藏--服务
    public function getCollectListNewAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $page = (int)$this->getRequest()->get('page') ? (int)$this->getRequest()->get('page') : 1;
        $size = ($this->getRequest()->get('size')&&$this->getRequest()->get('size')==20) ? (int)$this->getRequest()->get('size') : 20;
        $type = $this->getRequest()->getPost('type');//分类
        $id = $this->getRequest()->getPost('id');//活动分类下的小分类id  0为活动分类下的全部信息
        $city = $this->getRequest()->getPost('city');//城市
        $sort = $this->getRequest()->getPost('sort');//智能排序 默认 最近开始 最晚开始
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $city_id = Common::getIdByCity($city);
        $sort = $sort ? $sort : '默认';
        $collectModel = new CollectModel();
        $list = $collectModel->getCollectList($type,$id,$city_id,$sort,$page,(int)$size,$user['uid'],$_POST['token'],$version);
        Common::appLog('center/getCollectListNew',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$list ? $list : array());
    }
    //我的收藏--服务 筛选条件
    public function getCollectConditionAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $condition = array();
        $eventModel = new EventModel();
        $code_list = $eventModel->getEventTypeList();
        foreach($code_list as $k=>$v){
            $list[$k]['id'] = $v['id'];
            $list[$k]['name'] = $v['name'];
        }
        $condition['level_first'] = array(array('type'=>'1','name'=>'活动','small_type'=>$list),array('type'=>'3','name'=>'培训','small_type'=>array()),array('type'=>'6','name'=>'展览','small_type'=>array()),array('type'=>'7','name'=>'演出','small_type'=>array()));
        $condition['level_second'] = Common::returnCity();
        $condition['level_third'] = array(array('sort'=>'默认'),array('sort'=>'最近开始'),array('sort'=>'最晚开始'));
        Common::appLog('center/getCollectCondition',$this->startTime,$version);
        Common::echoAjaxJson(1, '获取成功',$condition);
    }
}