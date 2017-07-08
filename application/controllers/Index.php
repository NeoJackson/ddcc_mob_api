<?php
class IndexController extends Yaf_Controller_Abstract {
    public $eventType = array(
        1 => '活动', 2 => '推广', 3 => '培训', 4 => '商品', 5 => '投票' ,6 =>'展览' ,7 => '演出',8=>'展演'
    );
    public function init(){
        $this->startTime = microtime(true);
    }
    //APP 首页文化天使
    public function indexAngelAction(){
        $token = $this->getRequest()->getPost('token');//用户登录token
        $page = (int)$this->getRequest()->getPost('page');
        $size = ((int)$this->getRequest()->getPost('size') && (int)$this->getRequest()->getPost('size') ==20) ? (int)$this->getRequest()->getPost('size') : 20;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $total = 500;
        $uid = 0;
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $page = $page ? $page : 1;
        $pageNum = ceil($total/$size);
        if($page>$pageNum&&$page>1){
            Common::echoAjaxJson(2, "全部加载完毕");
        }
        $indexModel = new IndexModel();
        $data= $indexModel->getListByLoginTime($uid,$page,(int)$size);
        Common::appLog('index/indexAngel',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$data?$data :array());
    }
    //跳转到错误界面
    public function errorAction() {
        $this->getView()->type = $this->getRequest()->get('type');
        $this->display("error");
    }
    //首页精帖加载
    public function goodListAction(){
        $token = $this->getRequest()->getPost('token');//用户登录token
        $uid = 0;
        $list = array();
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $page = intval($this->getRequest()->getPost('page'));
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==20) ? $this->getRequest()->getPost('size') : 20;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $indexModel = new IndexModel();
        $page = $page ? $page : 1;
        $data = $indexModel->hotEventPush($page,(int)$size,$uid,$token,$version);//精帖
        $list['list'] = $data ? $data :array();
        Common::appLog('index/goodList',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$list);
    }
    //3.5版本首页
    public function indexAction(){
        $token = $this->getRequest()->getPost('token');//用户登录token
        $uid = 0;
        $list = array();
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') : APP_VERSION;//版本名
        $indexModel = new IndexModel();
        $banner = $indexModel->indexBanner('app_ad_index',$token);//banner图
        $list['banner'] = $banner ? $banner : array();
        $talkModel = new TalkModel();
        $list['talk']=$talkModel->recommendTalkAndTopic(4,$version,$token);
        $data = $indexModel->hotEventPush(1,4,$uid,$token,$version,1);//精选
        $eventModel = new EventModel();
        if($data){
            foreach($data as $k=>$v){
                $list['list'][$k]['id'] = $v['id'];
                $list['list'][$k]['title'] = $v['title'];
                $list['list'][$k]['url'] = $v['url'];
                $list['list'][$k]['lng'] = $v['lng'];
                $list['list'][$k]['lat'] = $v['lat'];
                $list['list'][$k]['city_name'] = $v['city_name'];
                $list['list'][$k]['push_img'] = IMG_DOMAIN.$v['push_img'];
                if($v['push_type']==10&&$v['type']!=1){
                    $list['list'][$k]['type_name'] = $this->eventType[$v['type']];
                }elseif($v['push_type']==10&&$v['type']==1){

                    $type_info = $eventModel->getBusinessEventType($v['type_code']);
                    $list['list'][$k]['type_name'] = $type_info['name'];
                }
                $e_time = $eventModel->getEndTime($v['id']);//結束时间
                $time = date('Y-m-d H:i:s');
                if($e_time){
                    if($e_time[0]['end_time']<=$time){
                        //当前时间小于活动结束时间
                        $list['list'][$k]['start_type'] = 3;//活动结束
                    }else{
                        $list['list'][$k]['start_type'] = 2;//可以报名
                    }
                }
            }
        }else{
            $list['list'] = array();
        }
        $topic = $indexModel->getGoodTopicList(0,4,$uid,$token,$version,0);//广告(暂为帖子)
        $list['ads']['id'] = $topic[0]['id'];
        $list['ads']['title'] = $topic[0]['title'];
        $list['ads']['img_src'] = $topic[0]['img_src'];
        $list['ads']['lng'] = $topic[0]['lng'];
        $list['ads']['lat'] = $topic[0]['lat'];
        $list['ads']['city_name'] = $topic[0]['city_name'];
        $list['ads']['sid'] = $topic[0]['sid'];
        $list['ads']['stage_name'] = $topic[0]['stage_name'];
        $list['ads']['url'] = $token ? I_DOMAIN.'/t/'.$topic[0]['id'].'?token='.$token.'&version='.$version.'' :I_DOMAIN.'/t/'.$topic[0]['id'].'?version='.$version.'';
        $stage = $indexModel->stagePush(1,6,$version);
        $list['stage'] =$stage ? $stage : array();
        $angel = $indexModel->getIndexAngel($uid,1,6);//文化天使
        $list['angel'] = $angel ? $angel : array();
        Common::appLog('index/index',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    //3.7首页
    public function indexViewAction(){
        $token = $this->getRequest()->getPost('token');//用户登录token
        $uid = 0;
        $list = array();
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') : APP_VERSION;//版本名
        $indexModel = new IndexModel();
        $list['banner'] = $indexModel->indexBanner('app_ad_index',$token) ? $indexModel->indexBanner('app_ad_index',$token) :array();//banner图
        $talkModel = new TalkModel();
        $list['talk']=$talkModel->recommendTalkAndTopic(4,$version,$token);//热门互动
        //专栏
        $list['column'] =$indexModel->getColumnList(1,3);
        //发现美文
        $topic = $indexModel->getGoodTopicForIndex(1,$uid,$token,$version,0);
        $visitModel = new VisitModel();
        $likeModel = new LikeModel();
        $stagegoodsModel = new StagegoodsModel();
        foreach($topic as $k => $v){
            $list['good_topic'][$k]['id'] = $v['id'];
            $list['good_topic'][$k]['title'] = $v['title'];
            $list['good_topic'][$k]['url'] = $v['url'];
            $list['good_topic'][$k]['push_img'] = $v['app_img'];
            $likeList = $likeModel->likeList($v['id'],4,1,6,$uid);
            $list['good_topic'][$k]['like_num'] = $likeList['size'];
            $list['good_topic'][$k]['is_like'] = $v['is_like'];
            $list['good_topic'][$k]['view_num'] = $visitModel->getVisitNum('topic',$v['id']);
        }
        //好物
        $list['goods'] = $stagegoodsModel->goodsForIndex(1,20,$version,$token,1);
        //文化生活
        $list['event'] = $indexModel->eventPush(1,20,$uid,$token,$version);//精选
        Common::appLog('index/indexView',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    //首页遇见知己
    public function getUserListAction(){
        $token = $this->getRequest()->getPost('token');//用户登录token
        $uid = 0;
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') : APP_VERSION;
        $indexModel = new IndexModel();
        $list = $indexModel->getUserList($uid,50);
        Common::appLog('index/getUserList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    //3.7版本首页专栏-人物专题
    public function personSpecialAction(){
        $data['token'] = $this->getRequest()->get('token');
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
            $this->getView()->user = $user;
        }
        $indexModel = new IndexModel();
        if(ENVIRONMENT_VAR=='product' || ENVIRONMENT_VAR=='preview'){
            $uid = '172460';
        }else{
            $uid = '3183';
        }
        $this->getView()->personSpecial = $indexModel->getPersonSpecial($uid);//172460代代号1162837
        $this->getView()->app_css = 'special';
        $this->getView()->page_title = '收藏女王沈伟媛';
        $this->getView()->token = $data['token'];
        $this->display("personSpecial");
    }

    //3.7版本首页专栏-昆曲专题('2284','365','1450','2379')   ('4','11','20','8')
    public function operaSpecialAction(){
        $data['token'] = $this->getRequest()->get('token');
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
            $this->getView()->user = $user;
        }
        $indexModel = new IndexModel();
        $sidStr = "'2284','365','1450','2379'";
        $this->getView()->operaSpecialList = $indexModel->getMartialSpecialList($sidStr);
        $this->getView()->app_css = 'special';
        $this->getView()->page_title = '探索昆曲';
        $this->display("operaSpecial");
    }

    //3.7版本首页专栏-武术专题('3394','2805','412','1977')   ('1553','24','19','21')
    public function martialSpecialAction(){
        $data['token'] = $this->getRequest()->get('token');
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
            $this->getView()->user = $user;
        }
        $indexModel = new IndexModel();
        $sidStr = "'3394','2805','412','1977'";
        $this->getView()->martialSpecialList = $indexModel->getMartialSpecialList($sidStr);
        $this->getView()->app_css = 'special';
        $this->getView()->page_title = '中华武术';
        $this->display("martialSpecial");
    }

    //3.7.2版本专栏人物张真左一
    public function personRealityAction(){
        $data['token'] = $this->getRequest()->get('token');
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
            $this->getView()->user = $user;
        }
        $indexModel = new IndexModel();
        if(ENVIRONMENT_VAR=='product' || ENVIRONMENT_VAR=='preview'){
            $uid = '114697';
        }else{
            $uid = '3183';
        }
        $this->getView()->personSpecial = $indexModel->getPersonSpecial($uid);//114697
        $this->getView()->app_css = 'specialColumn';
        $this->getView()->page_title = '文化先锋 张真';
        $this->getView()->token = $data['token'];
        $this->display("personReality");
    }

    //3.7.2版本专栏人物韩生又一
    public function personArtAction(){
        $data['token'] = $this->getRequest()->get('token');
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
            $this->getView()->user = $user;
        }
        $indexModel = new IndexModel();
        if(ENVIRONMENT_VAR=='product' || ENVIRONMENT_VAR=='preview'){
            $uid = '164755';
            $tid = '110098';
        }else{
            $uid = '3183';
            $tid = '11';
        }
        $this->getView()->personSpecial = $indexModel->getPersonSpecial($uid);//172460代代号1162837
        $this->getView()->app_css = 'specialColumn';
        $this->getView()->page_title = '文化先锋 韩生';
        $this->getView()->tid = $tid;
        $this->getView()->token = $data['token'];
        $this->display("personArt");
    }

    //3.7.2版本专栏人物又二驿站
    public function healthStageAction(){
        $data['token'] = $this->getRequest()->get('token');
        $data['type'] = $this->getRequest()->get('type') ? $this->getRequest()->get('type') :"";
        if(!$data['type']){

        }
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
            $this->getView()->user = $user;
        }
        $indexModel = new IndexModel();
        if(ENVIRONMENT_VAR=='product' || ENVIRONMENT_VAR=='preview'){
            $sids = "'3104','818','2633'";
        }else{
            $sids = "'1553','24','19'";
        }
        $this->getView()->martialSpecialList = $indexModel->getMartialSpecialList($sids);
        $this->getView()->app_css = 'specialColumn';
        $this->getView()->page_title = '中医养生';
        $this->getView()->token = $data['token'];
        $this->display("healthStage");
    }
    //3.8版本banner专栏(中华游学营)
    public function bannerColumnAction(){
        $data['token'] = $this->getRequest()->get('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $this->getView()->user = $user;
        }
        if(ENVIRONMENT_VAR=='product' || ENVIRONMENT_VAR=='preview'){
            $sid = "3916";
        }else{
            $sid = "204";
        }
        $this->getView()->sid = $sid;
        $this->getView()->page_title = '中华游学营';
        $this->getView()->app_css = 'specialColumn';
        $this->display("bannerColumn");
    }

    //APP首页专栏，商城专栏等等
    public function columnAction(){
        $data['token'] = $this->getRequest()->get('token');
        $id = $this->getRequest()->get('id');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $this->getView()->user = $user;
        }
        $indexModel = new IndexModel();
        $columnInfo = $indexModel->getColumnById($id);
        $cate = $columnInfo ? $columnInfo['cate'] : '';
        $this->getView()->columnInfo = $columnInfo;
        $this->getView()->app_css = 'column';
        $this->getView()->page_title = isset($columnInfo['name']) && $columnInfo['name'] ? $columnInfo['name'] : '';
        $this->getView()->reviewList = $indexModel->getReviewListByCate($cate,$id);
        $this->display("columnInfo");
    }
}