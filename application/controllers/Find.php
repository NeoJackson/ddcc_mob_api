<?php
class FindController extends Yaf_Controller_Abstract {
    public function init(){
        $this->startTime = microtime(true);
    }
    //附近的人
    public function vicinityUserAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $page = intval($this->getRequest()->getPost('page'));
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==20) ? $this->getRequest()->getPost('size') : 20;
        $type = $this->getRequest()->getPost('type') ? $this->getRequest()->getPost('type') : '';
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $page = $page ? $page : 1;
        $userModel = new UserModel();
        $appInfo = $userModel->getAppUserInfo($user['uid']);
        $data = $userModel->vicinityUser($appInfo['lat'],$appInfo['lng'],$user['uid'],$page,(int)$size,$type);
        foreach($data as $k =>$v){
            $userInfo = $userModel->getUserData($v['uid'],$user['uid']);
            $data[$k]['uid'] = $userInfo['uid'];
            $data[$k]['did'] = $userInfo['did'];
            $data[$k]['nick_name'] = $userInfo['nick_name'];
            $data[$k]['sex'] = $userInfo['sex'];
            $data[$k]['type'] = $userInfo['type'];
            $data[$k]['intro'] = $userInfo['intro'];
            $data[$k]['relation'] = $userInfo['relation'];
            if($userInfo['type']>1){
                $indexModel = new IndexModel();
                $info = $indexModel->getAngelInfoByUid($userInfo['uid']);
                $data[$k]['intro'] = $info['info'];
            }
            $data[$k]['avatar'] = Common::show_img($userInfo['avatar'],1,160,160);
            $data[$k]['range_info'] = Common::showRange($data[$k]['distance']);
            $data[$k]['time'] = Common::app_show_time($v['update_time']);
            $data[$k]['unix_time'] = strtotime($v['update_time']);
        }
        Common::appLog('find/vicinityUser',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$data ? $data : array());
    }
    //附近的商家
    public function vicinityStageAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $page = intval($this->getRequest()->getPost('page'));
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==20) ?$this->getRequest()->getPost('size') : 20;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $page = $page ? $page : 1;
        $userModel = new UserModel();
        $appInfo = $userModel->getAppUserInfo($user['uid']);
        $stageModel = new StageModel();
        $sids = $stageModel->vicinityBusiness($appInfo['lat'],$appInfo['lng'],$page,(int)$size);
        $list = array();
        if($sids){
            foreach($sids as $k =>$v){
                $stageInfo =$stageModel->getBasicStageBySid($v['sid']);
                $list[$k]['sid'] = $stageInfo['sid'];
                $list[$k]['name'] = $stageInfo['name'];
                $typeInfo = $stageModel->getCultureCateById($stageInfo['cate_id']);
                $list[$k]['type_name'] = $typeInfo['name'];
                $list[$k]['icon'] = Common::show_img($stageInfo['icon'],4,100,100);
                $list[$k]['intro'] = $stageInfo['intro'];
                $list[$k]['user_num'] = $stageInfo['user_num'];
                $list[$k]['event_num'] = $stageInfo['event_num'];
                $list[$k]['range_info'] = Common::showRange($v['distance']);
            }
        }
        Common::appLog('find/vicinityBusiness',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list ? $list : array());
    }
    //2.3获取APP驿站推荐标签和驿站分类
    public function getCultureCateAndTagAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $list = array();
        $stageModel = new StageModel();
        $list['tag'] = $stageModel->getPushStageTag();//标签
        $list['culture_cate'] = $stageModel->getCultureCateList();//分类
        Common::appLog('find/getCultureCateAndTag',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    //获取应用图标
    public function getApplyAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $indexModel = new IndexModel();
        $list = $indexModel->indexBanner('app_apply',$_POST['token']);//banner图
        Common::appLog('find/getApply',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list ? $list : array());
    }
    //发现页面数据
    public function getAction(){
        $data['token'] = $this->getRequest()->get('token');
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $list = array();
        $tagModel = new TagModel();
        $indexModel = new IndexModel();
        $stageModel = new StageModel();
        if($version<'3.0'){
            $talkModel = new TalkModel();
            $list['talk']=$talkModel->recommendTalk();
        }else{
            $stagegoodsModel = new StagegoodsModel();
            $list['goods'] = $stagegoodsModel->getPushGoods(3,$version,$_POST['token']);
        }
        $list['app_apply'] = $indexModel->indexBanner('app_apply',$_POST['token']);//banner图
        $list['tag'] = $tagModel->getAppStageTag();
        $list['culture_cate'] = $stageModel->getCultureCateList();//分类
        $list['good_topic'] = $indexModel->getGoodTopicList(1,4,$uid,$data['token'],$version,0);//精选帖子
        Common::appLog('find/get',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    //发现精品推荐加载数据
    public function goodsRecommendMoreAction(){
        $data['token'] = $this->getRequest()->get('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $type = $this->getRequest()->getPost('type');//0全部 1现金 2换购 3福报值
        $page = intval($this->getRequest()->get('page')) ?intval($this->getRequest()->get('page')):1;
        $size = ($this->getRequest()->get('size')&&$this->getRequest()->get('size')==20) ? $this->getRequest()->get('size') : 20;
        $stagegoodsModel = new StagegoodsModel();
        $list = $stagegoodsModel->goodsRecommendMore($page,(int)$size,$type,$version,$data['token']);
        Common::appLog('find/goodsRecommendMore',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list ? $list : array());
    }
    //发现好友
    public function getFindUserAction(){
        $user=Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        $list = $userModel->getFindUser($user['uid'],30);
        Common::appLog('find/getFindUser',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
}