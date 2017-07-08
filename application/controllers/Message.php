<?php
class MessageController extends Yaf_Controller_Abstract{
    public function init(){
        $this->startTime = microtime(true);
    }
    //消息中心
    public function messageAction(){
        $token = $this->getRequest()->getPost('token');//用户登录token
        $uid = 0;
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        //获取最新评论
        $messageModel = new MessageModel();
        $comment = $messageModel->getList($uid,1);
        $c_num=$messageModel->getUnReadNum($uid,1);
        $comment['num'] = $c_num['num'];
        //获取最新打赏
        $reward = $messageModel->getList($uid,4);
        $r_num = $messageModel->getUnReadNum($uid,3);
        $reward['num'] = $r_num['num'];
        //获取最新提到的我
        $mention = $messageModel->getList($uid,2);
        $m_num = $messageModel->getUnReadNum($uid,2);
        $mention['num'] = $m_num['num'];
        //获取最新喜欢
        $likeModel = new LikeModel();
        $like = $messageModel->getList($uid,3);
        $l_num = $likeModel->getUnReadNum($uid);
        $like['num'] =$l_num['num'];
        $data = array(
          'comment'=>$comment,
          'reward'=>$reward,
          'mention'=>$mention,
          'like'=>$like
        );
        Common::appLog('message/message',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$data);
    }
    public function commentNewAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $uid = $user['uid'];
        $page = intval($this->getRequest()->getPost('page'));
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==10) ? $this->getRequest()->getPost('size') : 10;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $messageModel = new MessageModel();
        $commentList = $messageModel->getCommentPushList($uid,$page,(int)$size,$_POST['token'],$version);
        Common::appLog('message/commentNew',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$commentList ? $commentList : (object)array());
    }
    //获取未读消息的总数
    public function getNumNewAction(){
        $token = $this->getRequest()->getPost('token');//用户登录token
        $uid = 0;
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $type = $this->getRequest()->getPost('type');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        //设置用户在线时间
        $userModel->setOnline($user['uid']);
        $messageModel = new MessageModel();
        $likeModel = new LikeModel();
        $noticeModel = new NoticeModel();
        $stageModel = new StageModel();
        $followModel = new FollowModel();
        $comment_num = $messageModel->getUnReadNum($uid,1);
        $mention_num = $messageModel->getUnReadNum($uid,2);
        $reward_num = $messageModel->getUnReadNum($uid,3);
        $like_num = $likeModel->getUnReadNum($uid);
        $notice_num = $noticeModel->getUnReadNum($uid,$type);
        $s_num = $stageModel->getRedisNewTopicNumTotals($uid);
//        $notice_num['num'] = $notice_num['num']+$s_num;
        $fans_num = $followModel->getUnReadNum($uid);
        $unread_num = $comment_num['num']+$mention_num['num']+$like_num['num']+$notice_num['num']+$fans_num['num']+$reward_num['num'];
        $data['unread_num'] = $unread_num;//未读信息总数
        $data['comment_num'] = $comment_num;//未读评论
        $data['like_num'] = $like_num;//未读喜欢
        $data['mention_num'] = $mention_num;//未读提到我的
        $data['notice_num'] = $notice_num;//系统通知
        $data['reward_num'] = $reward_num;//打赏通知
        $data['fans_num'] = $fans_num;//新粉丝的数量
        $data['stage_num'] = $s_num;//驿站动态数量
        $userInfo = $userModel->getUserData($uid);
        $data['fans_total'] = $userInfo['fans_num'];//粉丝总数
        Common::appLog('message/getNumNew',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$data);
    }
    //通知
    public function noticeAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $uid = $user['uid'];
        $page = intval($this->getRequest()->getPost('page'));
        $size = intval($this->getRequest()->getPost('size'));
        $type = intval($this->getRequest()->getPost('type'));//2IOS版系统通知 3app安卓版系统
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $page = $page ? $page : 1;
        $size = $size ? $size : 10;
        if(!in_array($type,array(2,3))){
            Common::echoAjaxJson(2, "通知类型错误");
        }
        $start = ($page-1)*$size;
        $noticeModel = new NoticeModel();
        $noticeList = $noticeModel->getPushList($uid,$type,$start,$size);
        Common::appLog('message/notice',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$noticeList ? $noticeList : (object)array());
    }
    //提到我的
    public function mentionAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $page = intval($this->getRequest()->getPost('page'));
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==10) ? $this->getRequest()->getPost('size') : 10;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $page = $page ? $page : 1;
        $messageModel = new MessageModel();
        $list = $messageModel->getMentionPushList($user['uid'],$page,(int)$size,$_POST['token'],$version);
        Common::appLog('message/mention',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list ? $list : (object)array());
    }
    //喜欢我的列表
    public function likeAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $page = intval($this->getRequest()->getPost('page'));
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==10) ? $this->getRequest()->getPost('size') : 10;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $page = $page ? $page : 1;
        $likeModel = new LikeModel();
        $start = ($page-1)*$size;
        $list = $likeModel->getLikeMeList($user['uid'],$start,(int)$size,1,$_POST['token'],$version);
        Common::appLog('message/like',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list ? $list : (object)array());
    }
    //打赏我的列表
    public function rewardAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $page = intval($this->getRequest()->getPost('page'));
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==10) ? $this->getRequest()->getPost('size') : 10;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $page = $page ? $page : 1;
        $messageModel = new MessageModel();
        $list = $messageModel->getRewardMeList($user['uid'],$page,(int)$size,$_POST['token'],$version);
        foreach($list['list'] as $k=> $v){
            if(!$v['obj_info']){
                $list['list'][$k]['obj_info'] = (object)array();
            }
        }
        Common::appLog('message/reward',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list ? $list : (object)array());
    }
    //删除评论推送消息
    public function delCommentAction(){
        $user=Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $uid = $user['uid'];
        $id = $this->getRequest()->getPost('id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2,'删除的消息不能为空');
        }
        $messageModel = new MessageModel();
        $rs = $messageModel->delCommentPush($uid,$id);
        if($rs == 0){
            Common::echoAjaxJson(3,'删除消息失败');
        }
        Common::appLog('message/delComment',$this->startTime,$version);
        Common::echoAjaxJson(1,'删除消息成功');
    }
    //删除打赏消息
    public function delRewardAction(){
        $user=Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(6, "非法登录用户");
        }
        $uid = $user['uid'];
        $id = $this->getRequest()->getPost('id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2,'删除的消息不能为空');
        }
        $messageModel = new MessageModel();
        $rs = $messageModel->delRewardPush($id,$uid);
        if($rs == -1){
            Common::echoAjaxJson(3,'该条消息已不存在');
        }
        if($rs == -2){
            Common::echoAjaxJson(4,'您没有权限删除该条消息');
        }
        if($rs == 0){
            Common::echoAjaxJson(5,'删除消息失败');
        }
        Common::appLog('message/delReward',$this->startTime,$version);
        Common::echoAjaxJson(1,'删除消息成功');
    }
    //删除@提到我的推送信息
    public function delMentionAction(){
        $user=Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(6, "非法登录用户");
        }
        $uid = $user['uid'];
        $id = $this->getRequest()->getPost('id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2,'删除的消息不能为空');
        }
        $messageModel = new MessageModel();
        $rs = $messageModel->delMentionPush($uid,$id);
        if($rs == -1){
            Common::echoAjaxJson(3,'该条消息已不存在');
        }
        if($rs == -2){
            Common::echoAjaxJson(4,'您没有权限删除该条消息');
        }
        if($rs == 0){
            Common::echoAjaxJson(5,'删除消息失败');
        }
        Common::appLog('message/delMention',$this->startTime,$version);
        Common::echoAjaxJson(1,'删除消息成功');
    }
}