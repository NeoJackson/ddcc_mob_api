<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 15-7-27
 * Time: 下午5:32
 */
class TalkController extends Yaf_Controller_Abstract{
    public function init(){
        $this->startTime = microtime(true);
    }
    //话题页面
    public function indexAction(){
        $data['token'] = $this->getRequest()->get('token');
        $user['uid']=0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
        }
        $keyword = $this->getRequest()->getPost('keyword');
        $follow = $this->getRequest()->getPost('follow'); //1 好友在说 默认为空 大家在说
        $page =  intval($this->getRequest()->getPost('page'));//页数
        $size = 20;//条数
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : '3.6';
        $talkModel = new TalkModel();
        $userModel = new UserModel();
        $stageModel = new StageModel();
        $talkInfo = $talkModel->getTalkInfo($keyword);
        if(!$talkInfo){
            Common::echoAjaxJson(2, "该话题不存在");
        }
        $follow = $follow ? $follow : 0;
        $page = $page ? $page : 1;
        $data = $talkModel->getTalkLists($talkInfo['id'],$follow,$user['uid'],$page,(int)$size,0,$version);
        $data['talkInfo'] = $talkInfo;
        $talkManage = $talkModel->getTalkManager($talkInfo['id'],$user['uid']);
        if($talkManage){
            $userInfo = $userModel->getUserData($talkManage['uid'],$user['uid']);
            $talkManage['nick_name'] = $userInfo['nick_name'];
            $talkManage['avatar'] = Common::show_img($userInfo['avatar'],1,160,160);

        }
        $data['talkManage'] = $talkManage ? $talkManage : (object)array();
        if($data['list']){
            foreach($data['list'] as $k=>$v){
                $data['list'][$k]['content'] = Common::showEmoticon($v['content'],1);
                $userInfo = $userModel->getUserData($v['uid'],$user['uid']);
                $angelInfo = $userModel->getInfo($userInfo['uid']);
                $userInfo['angel_info'] = isset($angelInfo['info']) ? $angelInfo['info'] :'';
                $data['list'][$k]['user'] = $userInfo;
                $b_num = $stageModel->getSidByUid($userInfo['uid']);
                if($b_num){
                    $data['list'][$k]['user']['is_business']['num'] =1;
                    $data['list'][$k]['user']['is_business']['sid'] =$b_num['sid'];
                }else{
                    $data['list'][$k]['user']['is_business']['num'] =0;
                    $data['list'][$k]['user']['is_business']['sid'] ='';
                }
                if(isset($v['img'])){
                    foreach($v['img'] as $k1=>$v1){
                        $data['list'][$k]['img'][$k1]['img'] = IMG_DOMAIN.$v1['img'];
                        $data['list'][$k]['img'][$k1]['show_img'] = Common::show_img($v1['img'],0,450,450);
                    }
                }
                if(isset($v['comment_list'])){
                    foreach($v['comment_list'] as $k2=>$v2){
                        $data['list'][$k]['comment_list'][$k2]['user']['avatar'] = Common::show_img($v2['user']['avatar'],1,160,160);
                    }
                }
                if(isset($v['like_list']['list'])){
                    foreach($v['like_list']['list'] as $k3=>$v3){
                        $data['list'][$k]['like_list']['list'][$k3]['avatar'] = Common::show_img($v3['avatar'],1,160,160);
                    }
                }
            }
        }
        $visitModel = new VisitModel();
        $visitModel->add('talk',$talkInfo['id'],'');
        $talkModel->updateViewNum($talkInfo['id']);
        Common::appLog('talk/index',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$data);
    }
    //话题列表
    public function getListAction(){
        $data['token'] = $this->getRequest()->get('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $last_id = intval($this->getRequest()->getPost('last_id'));
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==20)?$this->getRequest()->getPost('size'):20;
        $last_id = $last_id ? $last_id : 0;
        $talkModel = new TalkModel();
        $list = $talkModel->getList($last_id,(int)$size);
        Common::appLog('talk/getList',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$list ? $list : array());
    }
}

