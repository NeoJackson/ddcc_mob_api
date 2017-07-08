<?php
class TopicController extends Yaf_Controller_Abstract{
    public function init(){
        $this->startTime = microtime(true);
    }

    /**
     * 帖子详情页面
     */
    public function indexAction(){
        $id =  $this->getRequest()->get('tid');
        $version = $this->getRequest()->get("version") ? $this->getRequest()->get("version") : APP_VERSION;
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
        $topicModel = new TopicModel();
        $visitModel = new VisitModel();
        $feedModel = new FeedModel();
        $stageModel = new StageModel();
        if(!$id){
            Common::redirect(I_DOMAIN.'/index/error?type=2');
        }
        $topicInfo = $topicModel->getTopicDetailById($id,$uid);
        if(!$topicInfo||$topicInfo['status']>1){
            Common::redirect(I_DOMAIN.'/index/error?type=2');
        }
        $topicInfo['content'] = str_replace("\n","<br>",$topicInfo['content']);//APP发布换行\n处理
        $topicInfo['content'] = Common::linkReplace(Common::replaceStyle($topicInfo['content']));
        $topicInfo['view_num'] = $visitModel->addVisitNum('topic',$id);//添加浏览数
        $commentList = $feedModel->getCommentList($uid,4,$id,1,3,1);
        $likeModel = new LikeModel();
        $is_like = $likeModel->hasData(4,$id,$uid);
        $collectModel = new CollectModel();
        $topicInfo['is_collect'] = $collectModel->hasData(4,$id,$uid);
        $likeList = $likeModel->likeList($id,4,1,6,$uid);
        $topicInfo['is_like'] = isset($is_like) && $is_like ? 1 : 0;
        $messageModel = new MessageModel();
        $rewardList = $messageModel->getRewardList(4,$id,1,3);
        $this->getView()->is_join = $stageModel->joinStageRole($topicInfo['sid'],$uid);//当前用户是否加入该驿站及加入驿站信息
        $this->getView()->token = $data['token'];
        $this->getView()->rewardList = $rewardList;
        $this->getView()->commentList = $commentList;
        $this->getView()->like_list = $likeList;
        $this->getView()->topicInfo = $topicInfo;
        $this->getView()->type = 4;
        $this->getView()->obj_id = $topicInfo['id'];
        $this->getView()->page_title = $topicInfo['title'];
        $this->getView()->description = $topicInfo['title'];
        $this->getView()->newList = $topicModel->getRecommendTopicList($id,0,6,$uid,$data['token'],$version);
        $this->getView()->version = $version;
        $this->getView()->app_css = 'topic';
        $this->display("detail");

    }
    /*
     * 发布普通帖子
     */
    public function addTopicAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(14, "非法登录用户");
        }
        $sid = $this->getRequest()->getPost('sid');//驿站id
        $title = trim($this->getRequest()->getPost("title"));//帖子标题
        $content = $this->getRequest()->getPost("content");//帖子内容
        $type = $this->getRequest()->getPost("type");//类型 0为原创，1为转载 2才府独家
        $origin = $this->getRequest()->getPost("origin");//帖子来源 1.PCweb 2.移动web 3.IOS 4.Android
        $img = $this->getRequest()->getPost("img");//帖子内容图片 多个 & 拼接
        $content = Common::showEmoticon($content,0);
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$sid){
            Common::echoAjaxJson(2, "请选择帖子所属驿站");
        }
        $topicModel  = new TopicModel();
        $stageModel = new StageModel();
        $stageAuthority = $stageModel->getAuthority($sid);//查询权限
        $stageInfo = $stageModel->getBasicStageBySid($sid);
        if(!$stageInfo||$stageInfo['status']>1){
            Common::echoAjaxJson(16, "该驿站不存在");
        }
        if($stageInfo&&$stageInfo['status']==0){
            Common::echoAjaxJson(17, "该驿站正在审核中");
        }
        $role = $stageModel->joinStageRole($sid,$user['uid']);//查询驿站角色信息
        if($stageAuthority==2&&isset($role['role'])&&!in_array($role['role'],array(1,2))){
            Common::echoAjaxJson(15, "驿长未开启发帖功能");
        }
        if($img){
            $imgArr = explode('&',$img);
            foreach($imgArr as $v){
                $content = str_replace($v,'<img src="http://img.91ddcc.com/'.$v.'">',$content);
            }
        }
        if($title===''){
            Common::echoAjaxJson(3, "请输入帖子标题");
        }
        if(preg_match('/[A-Za-z]{1,}/',$title)){
            Common::echoAjaxJson(4,'帖子标题不能包含英文字符');
        }
        $title_len = mb_strlen($title,'utf-8');
        if($title_len < 1 || $title_len > 30){
            Common::echoAjaxJson(5,'请输入1-30个中文作为帖子标题');
        }
        $title_rs = $topicModel->titleIsExist($title);
        if($title_rs > 0){
            Common::echoAjaxJson(6,'您发表的帖子站内已有相同标题，未避免重复请修改后再发表');
        }
        if(!$content){
            Common::echoAjaxJson(7, "请填写帖子内容");
        }
        $content_len = mb_strlen($content,'utf-8');
        if($content_len>4294967295){
            Common::echoAjaxJson(8, "帖子内容过长");
        }
        if(!in_array($type,array(0,1,2)) || $type == ''){
            Common::echoAjaxJson(9,'请选择原创或转载或才府独家');
        }
        $rs = $topicModel->save($sid,$title,$content,$type,$origin,$user['uid']);
        if($rs == -1){
        Common::echoAjaxJson(10, '该驿站已不存在');
        }
        if($rs == -2){
            Common::echoAjaxJson(11, '请您先加入该驿站');
        }
        if($rs == -3){
            Common::echoAjaxJson(12, "您加入驿站的申请正在审核中");
        }
        if($rs == 0){
            Common::echoAjaxJson(13, '发表帖子失败');
        }
        Common::http(OPEN_DOMAIN.'/stageapi/modifyRedisNewTopicNum',array('sid'=>$sid,'uid'=>$user['uid'],'num'=>1),'POST');
        $stageModel->addTopicViewTime($rs);//把发帖时间储存到redis
        Common::appLog('topic/addTopic',$this->startTime,$version);
        Common::echoAjaxJson(1, '发表帖子成功',$rs);
    }
    /**
     * 修改普通帖子
     */
    public function modifyTopicAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(13, "非法登录用户");
        }
        $topicModel = new TopicModel();
        $tid = $this->getRequest()->getPost('tid');
        $title = trim($this->getRequest()->getPost("title"));
        $content = $this->getRequest()->getPost("content");
        $content = Common::showEmoticon($content,0);
        $type = $this->getRequest()->getPost("type");
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $img = $this->getRequest()->getPost("img");//帖子内容图片 多个 & 拼接
        if(!$tid){
            Common::echoAjaxJson(2, "修改的帖子不能为空");
        }
        if(!$title){
            Common::echoAjaxJson(3, "请输入帖子标题");
        }
        if(preg_match('/[A-Za-z]{1,}/',$title)){
            Common::echoAjaxJson(4,'帖子标题不能包含英文字符');
        }
        $title_len = mb_strlen($title,'utf-8');
        if($title_len < 2 || $title_len > 30){
            Common::echoAjaxJson(5,'请输入2-30个字作为帖子标题');
        }
        $title_rs = $topicModel->titleIsExist($title,$tid);
        if($title_rs > 0){
            Common::echoAjaxJson(6,'您发表的帖子站内已有相同标题，未避免重复请修改后再发表');
        }
        if(!$content){
            Common::echoAjaxJson(7, "帖子内容不能为空");
        }
        if(!in_array($type,array(0,1,2)) || $type == ''){
            Common::echoAjaxJson(8,'请选择原创或转载');
        }
        if($img){
            $imgArr = explode('&',$img);
            foreach($imgArr as $v){
                $content = str_replace($v,'<img src="http://img.91ddcc.com/'.$v.'">',$content);
            }
        }
        $rs = $topicModel->update($tid,$title,$content,$type,$user['uid']);
        if($rs == -1){
            Common::echoAjaxJson(9, '该帖子已不存在');
        }
        if($rs == -2){
            Common::echoAjaxJson(10, '请您先加入该驿站');
        }
        if($rs == -4){
            Common::echoAjaxJson(11, '您没有权限编辑该帖子');
        }
        if($rs == 0){
            Common::echoAjaxJson(12, '修改帖子失败');
        }
        Common::appLog('topic/modifyTopic',$this->startTime,$version);
        Common::echoAjaxJson(1, '修改帖子成功',$tid);
    }
    /**
     * 删除帖子
     */
    public function delTopicAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(8, "非法登录用户");
        }
        $tid = (int) $this->getRequest()->getPost('id') ? (int) $this->getRequest()->getPost('id') :(int) $this->getRequest()->getPost('tid');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        echo Common::http(OPEN_DOMAIN.'/stageapi/deleteTopic',array('uid'=>$user['uid'],'tid'=>$tid),'POST');
        Common::appLog('topic/delTopic',$this->startTime,$version);
    }

    //获取普通帖子信息
    public function getTopicInfoAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $tid = $this->getRequest()->getPost('tid');//帖子id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$tid){
            Common::echoAjaxJson(2, '帖子id为空');
        }
        $topicModel = new TopicModel();
        $info = $topicModel->getTopicById($tid);
        Common::appLog('topic/getTopicInfo',$this->startTime,$version);
        Common::echoAjaxJson(1, '获取成功',$info ? $info : (object)array());
    }
    //用户浏览普通帖子
    public function viewTopicAction(){
        $id = $this->getRequest()->getPost('tid');//id
        $token = $this->getRequest()->getPost('token');//用户登录token
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2, "id为空");
        }
        $topicModel = new TopicModel();
        $info = $topicModel->getTopicById($id);
        $url=I_DOMAIN.'/t/'.$id.'?sid='.$info['sid'].'';
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $url=I_DOMAIN.'/t/'.$id.'?token='.$token.'&sid='.$info['sid'].'&version='.$version.'&replyuid='.$info['uid'].'';
        }
        Common::appLog('topic/viewTopic',$this->startTime,$version);
        Common::echoAjaxJson(1, '获取成功',$url);
    }

    //帖子置顶公告精华
    public function topAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(11, "非法登录用户");
        }
        $uid = $user['uid'];
        $tid = (int) $this->getRequest()->getPost('id') ? (int) $this->getRequest()->getPost('id') :(int) $this->getRequest()->getPost('tid');
        $type = (int) $this->getRequest()->getPost('type');
        $data = array(
            'uid' => $uid,
            'tid' => $tid,
            'type' => $type,
        );
        $rst = Common::http(OPEN_DOMAIN."/stageapi/topTopic", $data, "POST");
        echo $rst;
    }
    /**
     * 取消置顶和取消精华,取消公告
     */
    public function cancelTopAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(10, "非法登录用户");
        }
        $uid = $user['uid'];
        $tid = (int) $this->getRequest()->getPost('id') ? (int) $this->getRequest()->getPost('id') :(int) $this->getRequest()->getPost('tid');
        $type = $this->getRequest()->getPost('type');

        $data = array(
            'uid' => $uid,
            'tid' => $tid,
            'type' => $type,
        );
        $rst = Common::http(OPEN_DOMAIN."/stageapi/cancelTopTopic", $data, "POST");
        echo $rst;
    }

    /**
     * 帖子审核
     */
    public function checkTopicAction() {
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(8, "非法登录用户");
        }
        $uid = $user['uid'];
        $tid = (int) $this->getRequest()->getPost('tid');
        $status = (int) $this->getRequest()->getPost('status');
        $content = '与驿站主题不符';
        if(!$tid){
            Common::echoAjaxJson(2, '帖子不能为空');
        }
        if(!in_array($status,array(1,2,4))) {
            Common::echoAjaxJson(3, '审核状态不正确');
        }
        $topicModel = new TopicModel();
        $rs = $topicModel->checkTopic($tid,$uid,$status,$content);
        if($rs == -1){
            Common::echoAjaxJson(4, '该帖子已不存在');
        }
        if($rs == -2){
            Common::echoAjaxJson(5, "请您先加入该驿站");
        }
        if($rs == -3){
            Common::echoAjaxJson(6, '您加入驿站的申请正在审核中');
        }
        if($rs == -4){
            Common::echoAjaxJson(7, '您没有权限管理帖子');
        }
        if($rs == 0){
            Common::echoAjaxJson(8, '管理帖子失败');
        }
        if($rs == -5){
            Common::echoAjaxJson(1, '管理帖子成功');
        }
        Common::echoAjaxJson(1, '管理帖子成功');
    }
    //广场精帖
    public function goodTopicListAction(){
        $data['token'] = $this->getRequest()->get('token');
        $uid =0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(1, "非法登录用户");
            }
            $uid = $user['uid'];
        }

        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $page = intval($this->getRequest()->getPost('page'));
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==20) ? $this->getRequest()->getPost('size') : 20;
        $page = $page ? $page : 1;
        $start = ($page-1)* $size;
        $topicModel = new TopicModel();
        $list = $topicModel->getGoodTopicList($start,$size,$uid,$_POST['token'],$version);//优秀图贴
        Common::appLog('topic/goodTopicList',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$list ? $list : array());
    }
    public function previewAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(14, "非法登录用户");
        }
        $sid = $this->getRequest()->getPost('sid');//驿站id
        $title = trim($this->getRequest()->getPost("title"));//帖子标题
        $content = $this->getRequest()->getPost("content");//帖子内容
        $type = $this->getRequest()->getPost("type");//类型 0为原创，1为转载 2才府独家
        $img = $this->getRequest()->getPost("img");//帖子内容图片 多个 & 拼接
        $address = $this->getRequest()->getPost('address');//地址
        $content = Common::showEmoticon($content,0);
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$sid){
            Common::echoAjaxJson(2, "请选择帖子所属驿站");
        }
        $stageModel = new StageModel();
        $userModel = new UserModel();
        $stageAuthority = $stageModel->getAuthority($sid);//查询权限
        $role = $stageModel->joinStageRole($sid,$user['uid']);//查询驿站角色信息
        if($stageAuthority==2&&isset($role['role'])&&!in_array($role['role'],array(1,2))){
            Common::echoAjaxJson(15, "对不起，驿长已经关闭发布帖子权限");
        }
        if($img){
            $imgArr = explode('&',$img);
            foreach($imgArr as $v){
                $content = str_replace($v,'<img src="http://img.91ddcc.com/'.$v.'">',$content);
            }
        }
        if($title===''){
            Common::echoAjaxJson(3, "请输入帖子标题");
        }
        if(preg_match('/[A-Za-z]{1,}/',$title)){
            Common::echoAjaxJson(4,'帖子标题不能包含英文字符');
        }
        $title_len = mb_strlen($title,'utf-8');
        if($title_len < 1 || $title_len > 30){
            Common::echoAjaxJson(5,'请输入1-30个中文作为帖子标题');
        }
        if(!$content){
            Common::echoAjaxJson(7, "请填写帖子内容");
        }
        $content_len = mb_strlen($content,'utf-8');
        if($content_len>4294967295){
            Common::echoAjaxJson(8, "帖子内容过长");
        }
        if(!in_array($type,array(0,1,2)) || $type == ''){
            Common::echoAjaxJson(9,'请选择原创或转载或才府独家');
        }
        $stageInfo = $stageModel->getBasicStageBySid($sid);
        $userInfo = $userModel->getUserData($user['uid']);
        $data = array(
            'title'=>$title,
            'content'=>str_replace("\n","<br>",$content),
            'add_time'=>date('Y-m-d H:i:s',time()),
            'type'=>$type,
            'ico_type'=>$userInfo['ico_type'],
            'nick_name'=>$userInfo['nick_name'],
            'avatar'=>$userInfo['avatar'],
            'att_num'=>$userInfo['att_num'],
            'fans_num'=>$userInfo['fans_num'],
            'view_num'=>0,
            'stageName'=>$stageInfo['name'],
            'topic_address'=>$address,
        );
        $commonModel = new CommonModel();
        $rs = $commonModel->addPreview(1,json_encode($data));
        Common::appLog('topic/preview',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",I_DOMAIN.'/common/preview?id='.$rs);
    }
}