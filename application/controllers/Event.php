<?php
class EventController extends Yaf_Controller_Abstract{
    public $eventTypeClass = array(
        1 => 'btn_green', 3 => 'btn_bluer', 6 => 'btn_orange', 7 => 'btn_orange' , 8 => 'btn_orange'
    );
    public $eventTypeClassSmall = array(
        1 => 'service-active', 3 => 'service-train', 6 => 'service-exhibition', 7 => 'service-show' , 8 => 'service-show'
    );
    //订单号首字母类型  1:活动 3:培训  6.展览 7.演出 8.展演
    public $orderType = array(
        1 =>'H',3=>'T',6=>'Z',7=>'Y',8=>'Z'
    );
    public $event_type = array(
        1 =>'活动',3=>'培训',6=>'展览',7=>'演出',8=>'展演'
    );
    public function init(){
        $this->startTime = microtime(true);
    }
    /**
     * 服务信息详情页面
     */
    public function indexAction(){
        $id =  $this->getRequest()->get('id');
        $version = $this->getRequest()->get('version') ? $this->getRequest()->get('version') : APP_VERSION;
        $token = $this->getRequest()->get('token');
        $sp = $this->getRequest()->get('sp') ? $this->getRequest()->get('sp') : '' ;
        $uid = 0;
        if($token){
            $user=Common::isLogin(array('token'=>$token));
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid=$user['uid'];
            $this->getView()->user = $user;
        }
        $eventModel = new EventModel();
        $feedModel = new FeedModel();
        $visitModel = new VisitModel();
        $likeModel = new LikeModel();
        $stageModel = new StageModel();
        $collectModel = new CollectModel();
        $eventInfo = $eventModel->getEventRedisById($id);
        $eventInfo['is_collect'] = $collectModel->hasData(10,$id,$uid);
        if(!$eventInfo || $eventInfo['status'] > 1){
            Common::redirect(I_DOMAIN.'/index/error?type=2');
        }
        $eventInfo['content'] = Common::linkReplace(Common::replaceStyle(str_replace("\n","<br>",$eventInfo['content'])));
        $price_info= $eventModel->getPrice($id);
        $eventInfo['price_info'] = $price_info;
        $eventInfo['price_count'] = count($price_info);
        $eventInfo['min_price'] = $price_info[0]['unit_price'];
        if(count($price_info)>1){
            $max_price = end($price_info);
            $eventInfo['max_price'] = $max_price['unit_price'];
        }
        $visitModel->addVisitNum('event',$id);//添加浏览数
        if($sp){
            $sp_str = base64_decode(base64_decode($sp));
            $sp_arr = explode('-',$sp_str);
            $sp_id = $sp_arr[1];
            $visitModel->addSpVisitNum($sp_arr[0],$sp_id);
        }
        $commentList = $feedModel->getCommentList($uid,10,$id,1,3,1);
        $is_like = $likeModel->hasData(10,$id,$uid);
        $eventInfo['is_like'] = isset($is_like) && $is_like ? 1 : 0;
        $likeList = $likeModel->likeList($id,10,1,6,$uid);
        if($eventInfo['type']==1){
            $data = $eventModel->getBusinessEventType($eventInfo['type_code']);//获取活动分类内容
        }else{
            $data = Common::eventType($eventInfo['type']);
        }
        $eventInfo['type_name'] = $data['name'];
        $eventInfo['code_name'] = $data['code'];

        $stageInfo = $stageModel->getBasicStageBySidForHtml($eventInfo['sid']);
        foreach($eventInfo['fields_info'] as $k=>$v){
            $f_array=array('0'=>'一','1'=>'二','2'=>'三','3'=>'四','4'=>'五','5'=>'六','6'=>'七');
            if(date('Y-m-d',strtotime($v['start_time']))==date('Y-m-d',strtotime($v['end_time']))){
                $eventInfo['fields_info'][$k]['show_time'] = '场次'.$f_array[$k].' '.date('m-d H:i',strtotime($v['start_time'])).'至'.date('H:i',strtotime($v['end_time'])).'';
            }else{
                $eventInfo['fields_info'][$k]['show_time'] = '场次'.$f_array[$k].' '.date('m-d H:i',strtotime($v['start_time'])).'至'.date('m-d H:i',strtotime($v['end_time'])).'';
            }
        }
        $agioArr = explode('&',$eventInfo['agio']);
        if($agioArr&&in_array($agioArr[0],array(1,2))){
            $eventInfo['agio_type'] = $agioArr[0];
        }else{
            $eventInfo['agio_type'] = 0;
        }
        if($eventInfo['price_type']==1){
            $partakeList = $eventModel->getPartakeForHtml($id,3);
        }else{
            $partakeList = $eventModel->getOrdersForHtml($id,3);
        }
        $eventInfo['button_type'] = $eventModel->getAddTypeByUid($id,$uid);
        $this->getView()->is_join = $stageModel->joinStageRole($eventInfo['sid'],$uid);//当前用户是否加入该驿站及加入驿站信息
        $this->getView()->is_collect = $collectModel->hasData(10,$id,$uid);
        $this->getView()->fields_list = $eventInfo['fields_info'];
        $this->getView()->commentList = $commentList;
        $this->getView()->partakeList = $partakeList;
        $this->getView()->like_list = $likeList;
        $this->getView()->eventTypeClass = $this->eventTypeClass;
        $this->getView()->eventTypeClassSmall = $this->eventTypeClassSmall;
        $this->getView()->more_event = $eventModel->getListForHtml(4,$id,$version,$token,$eventInfo['sid']);//看了又看（本驿站）
        $this->getView()->like_event = $eventModel->getListForHtml(4,$id,$version,$token,$eventInfo['sid'],2);//看了又看（非本驿站）
        $this->getView()->stageImg = PUBLIC_DOMAIN.'default_active.png';
        $this->getView()->stageInfo = $stageInfo;
        $this->getView()->eventInfo = $eventInfo;
        $this->getView()->page_title = '驿站活动';
        $this->getView()->description = $eventInfo['title'];
        $this->getView()->version = $version;
        $this->getView()->type = 10;
        $this->getView()->obj_id = $eventInfo['id'];
        $this->getView()->token = $token;
        $this->getView()->app_css = 'topic';
        $this->getView()->sp = $sp;
        $this->display("detail");
    }
    /**
     * 获取活动分类
     */
    public function getTypeAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $eventModel = new EventModel();
        $list = $eventModel->getEventTypeList();
        Common::appLog('event/getType',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$list ? $list : array());
    }
    //删除商家服务信息
    public function delEventAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $id = $this->getRequest()->getPost("id");//删除的活动id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2,'没有选择要删除的活动');
        }
        $eventModel = new EventModel();
        $info = $eventModel->getEvent($id,4);
        $stageModel = new StageModel();
        $num = $stageModel->verifyBusiness($info['sid'],$user['uid']);
        if($num==0){
            Common::echoAjaxJson(3,'你没有删除的权限');
        }
        $rs = $eventModel->delEvent($id,$user['uid'],$info['sid']);
        if($rs==0){
            Common::echoAjaxJson(4, "删除失败");
        }
        Common::appLog('event/delEvent',$this->startTime,$version);
        Common::echoAjaxJson(1, "删除成功");
    }
    //管理中心各分类下的活动列表
    public function getEventListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $stageModel = new StageModel();
        $sid = $stageModel->getSidByUid($user['uid']);
        $type = $this->getRequest()->get('type');//类型
        $page = $this->getRequest()->get('page');//页码
        $size = 20;//显示条数
        $last_time = $this->getRequest()->getPost('last_time');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $stageModel = new StageModel();
        $num = $stageModel->verifyBusiness($sid['sid'],$user['uid']);
        if(!$sid){
            Common::echoAjaxJson(2,'没有选择驿站');
        }
        if($num==0){
            Common::echoAjaxJson(3,'你没有管理该驿站的权限');
        }
        $eventModel = new EventModel();
        if($page){
            $data = $eventModel->getEventList($sid['sid'],$type,$page,(int)$size,$_POST['token'],$version);
        }else{
            $data = $eventModel->getEventListByLastTime($sid['sid'],$type,$last_time,(int)$size,$_POST['token'],$version);
        }
        Common::appLog('event/getEventList',$this->startTime,$version);
        Common::echoAjaxJson(1, '获取成功',$data ? $data : array());
    }
    //获取报名选项信息
    public function getPartakeOptionAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        Common::appLog('event/getPartakeOption',$this->startTime);
        $eventModel = new EventModel();
        $data = $eventModel->getPartakeOption();
        Common::appLog('event/getPartakeOption',$this->startTime,$version);
        Common::echoAjaxJson(1, '获取成功',$data ? $data : array());
    }

    //获取某一服务信息下的报名选项
    public function getPartakeOptionByEidAction(){
        $data['token'] = $this->getRequest()->get('token');
        $eid = $this->getRequest()->getPost('eid');//服务信息id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $eventModel = new EventModel();
        if(!$eid){
            Common::echoAjaxJson(2, "服务id为空");
        }
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(4, "非法登录用户");
            }
            $uid=$user['uid'];
        }
        $eventInfo = $eventModel->getEvent($eid);
        $list['option'] = $eventModel->getPartakeOptionByEid($eid);
        $fields_info = $eventModel->getEventFields($eid);
        $f_array=array('0'=>'一','1'=>'二','2'=>'三','3'=>'四','4'=>'五','5'=>'六','6'=>'七');
        foreach($fields_info as $k=>$v){
            $feedModel= new FeedModel();
            $modifyUser = $feedModel ->modifyIsPartake($uid,$v['id']);
            if(date('Y-m-d',strtotime($v['start_time']))==date('Y-m-d',strtotime($v['end_time']))){
                $fields_info[$k]['show_time'] = '场次'.$f_array[$k].'： '.date('m-d H:i',strtotime($v['start_time'])).'至'.date('H:i',strtotime($v['end_time'])).'';
            }else{
                $fields_info[$k]['show_time'] = '场次'.$f_array[$k].'： '.date('m-d H:i',strtotime($v['start_time'])).'至'.date('m-d H:i',strtotime($v['end_time'])).'';
            }
            if($eventInfo['price_type']==2){
                if(date('Y-m-d H:i:s',time())>=$v['partake_end_time']){
                    $fields_info[$k]['is_add']=0;
                }else{
                    $fields_info[$k]['is_add']=1;
                }
            }else{
                $price = $eventModel->getPriceByFid($v['id']);
                if(date('Y-m-d H:i:s',time())>=$v['partake_end_time']||$modifyUser||$price[0]['max_partake']>0&&$price[0]['stock_num']==0){
                    $fields_info[$k]['is_add']=0;
                }else{
                    $fields_info[$k]['is_add']=1;
                }
            }

        }
        $list['fields_info'] = $fields_info;
        Common::appLog('event/getPartakeOptionByEid',$this->startTime,$version);
        Common::echoAjaxJson(1, '获取成功',$list ? $list : array());
    }
    //用户报名
    public function addPartakeAction(){
        $data['token'] = $this->getRequest()->get('token');
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data,1);
            if(!$user){
                Common::echoAjaxJson(19, "非法登录用户");
            }
            $uid=$user['uid'];
            $this->getView()->user = $user;
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $id = $this->getRequest()->getPost('id');//服务id
        $name = $this->getRequest()->getPost('name');//用户姓名
        $mobile = $this->getRequest()->getPost('mobile');//用户手机
        $origin = $this->getRequest()->getPost('origin');//来源1.PCweb 2.移动web 3.IOS 4.Android
        $p_options = $this->getRequest()->getPost('p_options');//报名选项id '&'拼接
        $p_values = $this->getRequest()->getPost('p_values');//填写的内容 '&'拼接
        $check_code = $this->getRequest()->getPost('check_code');
        $nick_name = $this->getRequest()->getPost('nick_name');
        $f_id = $this->getRequest()->getPost('f_id');//场次id
        $eventModel = new EventModel();
        $info = $eventModel->getEvent($id);
        if(!$f_id){
            Common::echoAjaxJson(23,'场次id为空');
        }
        $p_end_time = $eventModel->getEndPartakeTime($id);
        if($info['status']>1){
            Common::echoAjaxJson(20,'对不起，该服务已不存在');
        }
        if(date('Y-m-d H:i:s',time()) >=$p_end_time[0]['partake_end_time']){
            Common::echoAjaxJson(22,'对不起，该服务报名已结束');
        }
        if(!$data['token']){
            $userModel = new UserModel();
            $rs = $userModel->nickNameIsExist($nick_name);
            if($rs == -1){
                Common::echoAjaxJson(11,'昵称不能为空');
            }
            if(!preg_match('/^[\x{4e00}-\x{9fa5}]{2,8}$/u',$nick_name)){
                Common::echoAjaxJson(12,'昵称必须为2-8个中文字符');
            }
            if(Common::badWord($nick_name)){
                Common::echoAjaxJson(13,'昵称含有敏感词');
            }
            if($rs > 0){
                Common::echoAjaxJson(14,'此昵称太受欢迎，已有人抢了');
            }
            if(!$check_code){
                Common::echoAjaxJson(15,'请输入验证码');
            }
            if(!preg_match('/^\d{6}$/',$check_code)){
                Common::echoAjaxJson(16,'验证码格式错误');
            }
            $smsModel = new SmsModel();
            $sms_info = $smsModel->getSmsCode($mobile,8);
            if($sms_info['code'] != $check_code){
                Common::echoAjaxJson(17,'输入的验证码不正确');
            }
            $smsModel->updateSmsCodeExpireTime($sms_info['id']);
            $pwd = substr($mobile,5);
            $pwd_type['pwd_type'] =Common::getPwdType($pwd);//密码强度
            $pwd_type=$pwd_type['pwd_type'];
            $uid = $userModel->addUser($mobile,$nick_name,'',$pwd,$pwd_type,2,1);
            if(!$uid){
                Common::echoAjaxJson(18, '注册失败');
            }
            $userModel->addUserInfo($uid,$origin,1,1,'传承传统文化是每一个人的使命！',$mobile);
            $userModel->updateUserLogin($uid);
            $tokenModel = new TokenModel();
            $token = $tokenModel->hasToken($uid);
        }
        $feedModel = new FeedModel();
        $nameId = $eventModel->getOptionId('姓名');
        $mobileId = $eventModel->getOptionId('手机号');
        if(!$id){
            Common::echoAjaxJson(2,'服务id不能为空');
        }
        $info = $eventModel->getEvent($id);
        if(!$info){
            Common::echoAjaxJson(3,'该活动不存在');
        }

        if(!$mobile){
            Common::echoAjaxJson(4,'报名时需填写您的手机号码');
        }
        if(!preg_match('/^1[0-9]{10}$/',$mobile)){
            Common::echoAjaxJson(5,'请输入正确的手机号');
        }
        $modifyUser = $feedModel->modifyIsPartake($uid,$f_id);
        if($modifyUser >0){
            Common::echoAjaxJson(6,'对不起，您已报名参加了该场次');
        }
        $modifyMobile = $feedModel->modifyMobileNew($f_id,$mobileId,$mobile);
        if($modifyMobile > 0){
            Common::echoAjaxJson(7,'对不起，您填写的手机号已报名参加了该活动');
        }
        if(!$name){
            Common::echoAjaxJson(8,'报名时需填写您的姓名');
        }
        if(!preg_match('/^[\x{4e00}-\x{9fa5}]{2,8}$/u',$name)){
            Common::echoAjaxJson(9,'姓名为2-8个中文字');
        }
        $array1=array(
            array('oid'=>$nameId,'content'=> $name),
            array('oid'=>$mobileId,'content'=> $mobile)
        );

        if($p_options&&$p_values){
            $p_optionsArr = explode('&',$p_options);
            $p_valuesArr = explode('&',$p_values);
            $array2 = array();
            foreach($p_optionsArr as $k=> $v){
                $array2[$k]['oid'] = $v;
                foreach($p_valuesArr as $k1=> $v1){
                    $info = $eventModel->getPartakeOptionByIdAndEid($id,$v);
                    if($k==$k1&&$info['must']==1 && $info['oid']==$v){
                        $v1_len = mb_strlen($v1,'utf-8');
                        if($v1_len < 1 || $v1_len > 100){
                            Common::echoAjaxJson(10,'字符数在1~100之间');
                        }
                    }
                    if($k==$k1){
                        $array2[$k]['content'] = $v1 ? $v1 : '';
                    }
                }
            }
            $pArr = array_merge_recursive($array1,$array2);
        }else{
            $pArr = $array1;
        }
        $rs = $eventModel->addPartakeInfo($id,$uid,$f_id,$pArr,$origin);
        Common::addNoticeAndSmsForEvent(1,'',$rs);
        Common::appLog('event/addPartake',$this->startTime,$version);
        if(!$data['token']){
            Common::echoAjaxJson(1,'报名成功',$token);
        }else{
            Common::echoAjaxJson(1,'报名成功');
        }

    }
    //报名验证手机号
    public function modifyMoibleAction(){
        $data['token'] = $this->getRequest()->get('token');
        $mobile = $this->getRequest()->getPost('mobile');//用户手机
        $reg_country_code = $this->getRequest()->getPost('reg_country_code') ? $this->getRequest()->getPost('reg_country_code') : '+86';//手机注册国家区号
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $id = $this->getRequest()->getPost('id');//场次id
        if(!$id){
            Common::echoAjaxJson(6,'场次id不能为空');
        }
        if(!$mobile){
            Common::echoAjaxJson(2,'请输入手机号');
        }
        if(!preg_match('/^1[0-9]{10}$/',$mobile)){
            Common::echoAjaxJson(3,'请输入正确的手机号');
        }
        $eventModel = new EventModel();
        $mobileId = $eventModel->getOptionId('手机号');
        $feedModel=new FeedModel();
        $modifyMobile = $feedModel->modifyMobileNew($id,$mobileId,$mobile);
        if($modifyMobile > 0){
            Common::echoAjaxJson(5,'对不起，您填写的手机号已报名参加了该活动');
        }
        if(!$data['token']){
            $userModel = new UserModel();
            $ret = $userModel->isBindNameUsed($mobile);
            if($ret){
                Common::echoAjaxJson(4,'您已是才府用户，请登录后报名');
            }
            $list =array();
            $list[]=$mobile;
            $rs = $userModel->userNameIsExist($list,$reg_country_code);
            if($rs){
                Common::echoAjaxJson(4,'您已是才府用户，请登录后报名');
            }
        }
        Common::appLog('event/modifyMoible',$this->startTime,$version);
        Common::echoAjaxJson(1,'注册报名');
    }
    //发送报名注册验证码
    public function sendPartakeSmsCodeAction(){
        $user_name = strtolower($this->getRequest()->getPost('user_name'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!preg_match('/^1[0-9]{10}$/',$user_name)){
            Common::echoAjaxJson(2,'请输入正确的11位手机号');
        }
        $smsModel = new SmsModel();
        $status = $smsModel->addSmsCode(0,$user_name,8);
        if($status == -1){
            Common::echoAjaxJson(3,'验证码类型不正确');
        }else if($status == -2){
            Common::echoAjaxJson(4,'24小时内发送的短信超出次数');
        }else if($status == -3){
            Common::echoAjaxJson(5,'短信发送太频繁');
        }else if($status == -4){
            Common::echoAjaxJson(6,'短信发送失败，请重新点击发送');
        }
        Common::appLog('event/sendPartakeSmsCode',$this->startTime);
        Common::echoAjaxJson(1,'验证码发送成功',$version);
    }

    //用户的电子票
    public function myTicketsAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $id = $this->getRequest()->getPost('id');//服务id
        $f_id = $this->getRequest()->getPost('f_id');//场次id
        if(!$id){
            Common::echoAjaxJson(2,'服务id不能为空');
        }
        $eventModel = new EventModel();
        $stageModel = new StageModel();
        $eventInfo = $eventModel->getEvent($id,5);
        $partake = $eventModel->getPartakeInfo($f_id,$user['uid']);
        $fields_info = $eventModel->getFieldsInfo($f_id);
        if(!$partake){
            Common::echoAjaxJson(3,'对不起，您没有报名过该活动');
        }
        if(!in_array($partake['status'],array(0,1))){
            Common::echoAjaxJson(4,'对不起，您的报名已被取消');
        }
        $data['id'] = $id;
        $data['title'] = $eventInfo['title'];
        $data['event_address'] = $eventInfo['event_address'];
        $data['start_time'] = $fields_info['start_time'];
        $data['p_number'] = $partake['p_number'];
        $stageInfo = $stageModel->getBasicStageBySid($eventInfo['sid']);
        $data['stage_name'] = $stageInfo['name'];
        $data['qrcodeImg'] =PHPQRCode::getPartakePHPQRCode($f_id,$user['uid']);
        $partakeInfo = $eventModel->getPartake($partake['id']);
        $nameId = $eventModel->getOptionId('姓名');
        foreach($partakeInfo as $v){
            if($nameId ==$v['oid']){
                $data['name'] =$v['content'];
            }
        }
        Common::appLog('event/myTickets',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$data);
    }
    //获取报名列表
    public function getPartakeListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $id = $this->getRequest()->get('id');//信息id
        $last_id = $this->getRequest()->get('last_id');
        $size = intval($this->getRequest()->get('size'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $size = $size ? $size : 5;
        $last_id = $last_id ? $last_id : 0;
        $eventModel = new EventModel();
        if(!$id){
            Common::echoAjaxJson(2, "服务id为空");
        }
        $info = $eventModel->getEvent($id);
        if(!$info || $info['status'] > 1){
            Common::echoAjaxJson(3, "该服务已不存在");
        }
        $partakeList = $eventModel->partakeList($id,$last_id,$size);
        Common::appLog('event/getPartakeList',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$partakeList);
    }
    //我的电子票
    public function myTicketListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $is_check = intval($this->getRequest()->get('is_check')); //0未使用 1使用 2全部
        $page = intval($this->getRequest()->get('page'));
        $size = ($this->getRequest()->get('size')&&$this->getRequest()->get('size')==20) ? $this->getRequest()->get('size') : 20;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $is_check = $is_check ? $is_check : 0;
        $page = $page ? $page : 1;
        $eventModel = new EventModel();
        $list = $eventModel->getPartakeListByUid($user['uid'],$is_check,$page,(int)$size,$version,$_POST['token']);
        Common::appLog('event/myTicketList',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$list ? $list : array());
    }
    //关闭报名
    public function closePartakeAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $id = $this->getRequest()->getPost('id');//id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $eventModel = new EventModel();
        $info = $eventModel->getEvent($id);
        if(!$id){
            Common::echoAjaxJson(2, "服务id为空");
        }
        if(!$info){
            Common::echoAjaxJson(3, "该服务已不存在");
        }
        $partake_end_time = date('Y-m-d H:i',time()-60);
        $eventModel->updatePartakeEndTime($partake_end_time,$id);
        Common::appLog('event/closePartake',$this->startTime,$version);
        Common::echoAjaxJson(1, "关闭成功",$partake_end_time);
    }
    //筛选服务信息条件
    public function getEventConditionAction(){
        $data['token'] = $this->getRequest()->getPost('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $condition = array();
        $eventModel = new EventModel();
        $code_list = $eventModel->getEventTypeList();
        foreach($code_list as $k=>$v){
            $list[$k]['id'] = $v['id'];
            $list[$k]['name'] = $v['name'];
        }
        $condition['level_first'] = array(array('type'=>'1','name'=>'活动','small_type'=>$list),array('type'=>'3','name'=>'培训','small_type'=>array()),array('type'=>'8','name'=>'展演','small_type'=>array()));
        $condition['level_second'] = Common::returnCity();
        $condition['level_third'] = array(array('sort'=>'不限'),array('sort'=>'最新'),array('sort'=>'热门'));
        Common::appLog('event/getEventCondition',$this->startTime);
        Common::echoAjaxJson(1, '获取成功',$condition,$version);
    }
    //筛选服务信息
    public function getListByConditionAction(){
        $data['token'] = $this->getRequest()->getPost('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') :APP_VERSION;//版本名
        $type = $this->getRequest()->getPost('type');//分类
        $id = $this->getRequest()->getPost('id');//活动分类下的小分类id  0为活动分类下的全部信息
        $city = $this->getRequest()->getPost('city');//城市
        $sort = $this->getRequest()->getPost('sort');//智能排序
        $size = ($this->getRequest()->getPost("size")&&$this->getRequest()->get('size')==20) ? $this->getRequest()->get('size') : 20; //条数
        $page = $this->getRequest()->getPost("page") ? $this->getRequest()->getPost("page") : 1; //页数
        $type = $type ? $type : '';
        if(in_array($type,array(6,7))){
            Common::echoAjaxJson(1, '获取成功',array());
        }
        $city_id = Common::getIdByCity($city);
        $sort = $sort&&$sort!='不限' ? $sort : '';
        $eventModel = new EventModel();
        $list = $eventModel->getListByCondition($type,$id,$city_id,$sort,$page,(int)$size,$data['token'],$version);
        Common::appLog('event/getListByCondition',$this->startTime,$version);
        Common::echoAjaxJson(1, '获取成功',$list ? $list : array());
    }
    //3.5发布
    public function addEventAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(26, "非法登录用户");
        }
        $uid = $user['uid'];
        $agio_info = '';
        $fields_num = 7;
        $eventModel = new EventModel();
        $stageModel = new StageModel();
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $cover = $this->getRequest()->getPost("cover");//封面
        $type= $this->getRequest()->getPost("type");//帖子类型 1:活动  3:培训  6:展览 7:演出 8:展演
        $type_code = $this->getRequest()->getPost("metid");
        $town = $this->getRequest()->getPost('town_id');//城市三级联动 三级id
        $address = $this->getRequest()->getPost("address");//活动地址
        $lng = $this->getRequest()->getPost("lng");
        $lat = $this->getRequest()->getPost("lat");
        $origin=$this->getRequest()->getPost("origin");//1.PCweb 2.移动web 3.IOS 4.Android
        $agio = $this->getRequest()->getPost('agio');//优惠信息
        $price_type= $this->getRequest()->getPost('price_type');//1.免费 2.收费
        $fields_info = $this->getRequest()->getPost('fields_info');//场次信息
        $content = $this->getRequest()->getPost("content");//内容
        $title = $this->getRequest()->getPost("title");//标题
        $img = $this->getRequest()->getPost("img");//帖子内容图片 多个 & 拼接
        if(!$cover){
            Common::echoAjaxJson(2, "请上传封面");
        }
        if($type==1){
            if(!$type_code){
                Common::echoAjaxJson(3, "请选择内容分类");
            }
        }
        if(in_array($type,array(6,7))){
            $type = 8;
        }
        if(!$town){
            Common::echoAjaxJson(4, '城市id不能为空');
        }
        if(!$lng||!$lat){
            Common::echoAjaxJson(5, "请在地图上设置坐标，以便用户更好的查找");
        }
        if(!$address){
            Common::echoAjaxJson(6, "请填写活动具体地址");
        }
        if(!$origin){
            Common::echoAjaxJson(7, "请标明来源");
        }
        if($agio){
            $agioArr = explode('&',$agio);
            if($agioArr[0]==1){
                $agio_info = '满'.$agioArr[1].'元减'.$agioArr[2].'元';
            }
            if($agioArr[0]==2){
                if($agioArr[0]>100||$agioArr[0]<=0){
                    Common::echoAjaxJson(8, "请输入正确的折扣");
                }
                $agio_info = $agioArr[1]/10.0.'折';
            }
        }else{
            $agio_info = '';
        }
        $fields_info = json_decode($fields_info,true);
        if(count($fields_info)>$fields_num){
            Common::echoAjaxJson(9, "一个服务只能设置7个场次");
        }
        foreach($fields_info as $k=>$v){
            if(!$v['partake_end_time']){
                Common::echoAjaxJson(10, "第".($k+1)."个场次请设置截止时间");
            }
            if($v['partake_end_time']>$v['end_time']){
                Common::echoAjaxJson(11,"第".($k+1)."个场次截止时间必须在活动结束前");
            }
            if(!$v['start_time']){
                Common::echoAjaxJson(12, "第".($k+1)."个场次请设置开始时间");
            }
            if(!$v['end_time']){
                Common::echoAjaxJson(13, "第".($k+1)."个场次请设置结束时间");
            }
            if($price_type==2){
                foreach($v['price_info'] as $v1){
                    if(!preg_match('/^[0-9]+(.[0-9]{1,2})?$/',$v1['price'])){
                        Common::echoAjaxJson(14,"第".($k+1)."个场次金额的格式不正确");
                    }
                    if($v1['max_partake']===''){
                        Common::echoAjaxJson(15, "第".($k+1)."个场次请填写人数限制");
                    }
                }
            }
        }
        $sid = $stageModel->getSidByUid($uid);
        if(!$sid){
            Common::echoAjaxJson(16,'你没有发布的权限');
        }
        if($sid['is_pay']==0){
            Common::echoAjaxJson(31,'请联系小管家开通支付功能，联系电话：13012888193');
        }
        if($title===''){
            Common::echoAjaxJson(17, "请输入标题");
        }
        if(preg_match('/[A-Za-z]{1,}/',$title)){
            Common::echoAjaxJson(18,'标题不能包含英文字符');
        }
        $title_len = mb_strlen($title,'utf-8');
        if($title_len < 1 || $title_len > 30){
            Common::echoAjaxJson(19,'请输入1-30个中文作为标题');
        }
        $title_rs = $eventModel->titleIsExist($title,$sid['sid']);
        if($title_rs > 0){
            Common::echoAjaxJson(20,'对不起，您发的服务信息标题在该驿站已存在');
        }
        if($content===''){
            Common::echoAjaxJson(21, "请填写内容");
        }
        $security = new Security();
        $content = $security->xss_clean($content);
        //帖子内容里是否有图片
        if($img){
            $imgArr = explode('&',$img);
            foreach($imgArr as $v){
                $content = str_replace($v,'<img src="http://img.91ddcc.com/'.$v.'">',$content);
            }
        }
        $content_len = mb_strlen($content,'utf-8');
        if($content_len>4294967295){
            Common::echoAjaxJson(22, "内容过长");
        }
        $addressModel = new AddressModel();
        $parent = $addressModel->cityParent($town);
        $province = $parent['pid'];
        $city = $parent['id'];
        $formData = array(
            'uid'=>$uid,
            'sid'=>$sid['sid'],
            'title'=>$title,
            'cover'=>$cover,
            'content'=>$content,
            'type'=>$type,
            'type_code'=>$type_code,
            'address'=>$address,
            'lng'=>$lng,
            'lat'=>$lat,
            'province'=>$province,
            'city'=>$city,
            'town'=>$town,
            'origin'=>$origin,
            'price_type'=>$price_type,
            'agio'=>$agio,
            'agio_info'=>$agio_info,
            'version'=>$version,
            'priceInfoArr'=>$fields_info
        );
        $rs = $eventModel->add($formData);
        if($rs == 0){
            Common::echoAjaxJson(23, "发表失败");
        }
        if($rs == -1){
            Common::echoAjaxJson(24, '抱歉！该驿站已被关闭，由于该驿站违反了社区的相关规定');
        }
        if($rs == -2){
            Common::echoAjaxJson(25, '请您先加入该驿站');
        }
        if($price_type==1){
            $poids = (string)$this->getRequest()->getPost("poids");//报名选项 '&'拼接
            $pomust = (string)$this->getRequest()->getPost("pomust");//是否必填 '&'拼接
            if(($poids && $pomust)||($poids && $pomust==='0')){
                $poidsArr = explode('&',$poids);
                $pomustArr = explode('&',$pomust);
                $pArr = array();
                foreach($poidsArr as $k=> $v){
                    $pArr[$k]['id'] = $v;

                }
                foreach($pomustArr as $k=> $v1){
                    $pArr[$k]['must'] = $v1;
                }
                $eventModel->addPartakeBind($rs,$pArr);
            }
        }
        Common::http(OPEN_DOMAIN.'/stageapi/modifyRedisNewTopicNum',array('sid'=>$sid['sid'],'uid'=>$uid,'num'=>1),'POST');
        $eventModel->addEventViewTime($rs);//把发帖时间储存到redis
        Common::appLog('event/addEvent',$this->startTime,$version);
        $stage_info = $stageModel->getBasicStageBySid($sid['sid']);
        if($version>='3.6'){
            Common::echoAjaxJson(1, "发布成功",array('url'=>I_DOMAIN.'/e/'.$rs.'?token='.$_POST['token'].'&version='.$version,'sid'=>$sid['sid'],'is_sp_agreement'=>$stage_info['is_sp_agreement'],'is_extend'=>$stage_info['is_extend']));
        }else{
            Common::echoAjaxJson(1, "发布成功",I_DOMAIN.'/e/'.$rs.'?token='.$_POST['token'].'&version='.$version);
        }
    }
    public function updateEventAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(22, "非法登录用户");
        }
        $pArr = array();$fields_num = 7;
        $id = $this->getRequest()->getPost('id');//id
        $title = trim($this->getRequest()->getPost("title"));//标题
        $cover = $this->getRequest()->getPost("cover");//封面
        $content = $this->getRequest()->getPost("content");//内容
        $img = $this->getRequest()->getPost("img");//帖子内容图片 多个 & 拼接
        $address = $this->getRequest()->getPost("address");//活动地址
        $type = $this->getRequest()->getPost("type");
        $type_code = $this->getRequest()->getPost("metid");
        $lng = $this->getRequest()->getPost("lng");
        $lat = $this->getRequest()->getPost("lat");
        $fields_info = $this->getRequest()->getPost('fields_info');//场次信息
        $agio = $this->getRequest()->getPost('agio');//优惠信息
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $eventModel = new EventModel();
        if(!$id){
            Common::echoAjaxJson(2, "服务id为空");
        }

        $info=$eventModel->getInfo($id,$user['uid']);
        if(!$info){
            Common::echoAjaxJson(3, "该服务已不存在");
        }
        if($title===''){
            Common::echoAjaxJson(4, "请输入标题");
        }
        if(preg_match('/[A-Za-z]{1,}/',$title)){
            Common::echoAjaxJson(5,'标题不能包含英文字符');
        }
        $title_len = mb_strlen($title,'utf-8');
        if($title_len < 1 || $title_len > 30){
            Common::echoAjaxJson(6,'请输入1-30个字作为标题');
        }
        $title_rs = $eventModel->titleIsExist($title,$info['sid'],$id);
        if($title_rs > 0){
            Common::echoAjaxJson(7,'对不起，您发的服务信息标题在该驿站已存在');
        }
        if(!$cover){
            Common::echoAjaxJson(8, "请上传封面");
        }
        if(!$content){
            Common::echoAjaxJson(9, "请填写内容");
        }
        $security = new Security();
        $content = $security->xss_clean($content);
        //帖子内容里是否有图片
        if($img){
            $imgArr = explode('&',$img);
            foreach($imgArr as $v){
                $content = str_replace($v,'<img src="http://img.91ddcc.com/'.$v.'">',$content);
            }
        }
        $content_len = mb_strlen($content,'utf-8');
        if($content_len>4294967295){
            Common::echoAjaxJson(10, "内容过长");
        }
        if(!$address){
            Common::echoAjaxJson(11, "请填写活动具体地址");
        }
        if(!$lng||!$lat){
            Common::echoAjaxJson(12, "请在地图上设置坐标，以便用户更好的查找");
        }
        $town = $this->getRequest()->getPost('town_id');//城市三级联动 三级id
        if(!$town){
            Common::echoAjaxJson(13, '城市id不能为空');
        }
        if($agio){
            $agioArr = explode('&',$agio);
            if($agioArr[0]==1){
                $agio_info = '满'.$agioArr[1].'元减'.$agioArr[2].'元';
            }
            if($agioArr[0]==2){
                if($agioArr[0]>100||$agioArr[0]<=0){
                    Common::echoAjaxJson(14, "请输入正确的折扣");
                }
                $agio_info = $agioArr[1]/10.0.'折';
            }
        }else{
            $agio_info = '';
        }
        $addressModel = new AddressModel();
        $parent = $addressModel->cityParent($town);
        $province = $parent['pid'];
        $city = $parent['id'];
        if($info['price_type']==1){
            $poids = (string)$this->getRequest()->getPost("poids");//报名选项 '&'拼接
            $pomust = (string)$this->getRequest()->getPost("pomust");//是否必填 '&'拼接
            if(($poids && $pomust)||($poids && $pomust==='0')){
                $poidsArr = explode('&',$poids);
                $pomustArr = explode('&',$pomust);
                foreach($poidsArr as $k=> $v){
                    $pArr[$k]['id'] = $v;
                }
                foreach($pomustArr as $k=> $v1){
                    $pArr[$k]['must'] = $v1;
                }
            }
        }
        $fields_info = json_decode($fields_info,true);
        if(count($fields_info)>$fields_num){
            Common::echoAjaxJson(15, "一个服务只能设置7个场次");
        }
        foreach($fields_info as $k=>$v){
            if(!$v['partake_end_time']){
                Common::echoAjaxJson(16, "第".($k+1)."个场次请设置截止时间");
            }
            if($v['partake_end_time']>$v['end_time']){
                Common::echoAjaxJson(17,"第".($k+1)."个场次截止时间必须在活动结束前");
            }
            if(!$v['start_time']){
                Common::echoAjaxJson(18, "第".($k+1)."个场次请设置开始时间");
            }
            if(!$v['end_time']){
                Common::echoAjaxJson(19, "第".($k+1)."个场次请设置结束时间");
            }
            if($info['price_type']==2){
                foreach($v['price_info'] as $v1){
                    if(!preg_match('/^[0-9]+(.[0-9]{1,2})?$/',$v1['price'])){
                        Common::echoAjaxJson(20,"第".($k+1)."个场次金额的格式不正确");
                    }
                    if($v1['max_partake']===''){
                        Common::echoAjaxJson(21, "第".($k+1)."个场次请填写人数限制");
                    }
                }
            }
        }
        $data = array(
            'id'=>$id,
            'title'=>$title,
            'cover'=>$cover,
            'content'=>$content,
            'type'=>$type,
            'type_code'=>$type_code,
            'address'=>$address,
            'lng'=>$lng,
            'lat'=>$lat,
            'province'=>$province,
            'city'=>$city,
            'town'=>$town,
            'agio'=>$agio,
            'agio_info'=>$agio_info,
            'pArr'=>$pArr,
            'version'=>$version,
            'priceInfoArr'=>$fields_info
        );
        $eventModel->updateEvent($data);
        Common::appLog('event/updateEvent',$this->startTime,$version);
        Common::echoAjaxJson(1, "修改成功");
    }
    //提交订单
    public function addOrderAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(10, "非法登录用户");
        }
        $uid = $user['uid'];
        $max_num = 20;
        $id = $this->getRequest()->getPost('id');//服务信息id
        $f_id = $this->getRequest()->getPost('f_id');//场次id
        $unit_price = $this->getRequest()->getPost('unit_price');//单价
        $price_id = $this->getRequest()->getPost('price_id');//单价主键
        $phone= $this->getRequest()->getPost('phone');//用户下单手机号码
        $num = $this->getRequest()->getPost('num');//购买票数
        $is_agio = $this->getRequest()->getPost('is_agio');//是否选择优惠信息 0没有优惠 1有优惠
        $agio_price = $this->getRequest()->getPost('agio_price');//折扣金额
        $totals = $this->getRequest()->getPost('totals');//订单总额
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $sp = $this->getRequest()->getPost("sp") ? $this->getRequest()->getPost("sp") :'';//分享有奖信息
        if(!$id){
            Common::echoAjaxJson(2,'服务id为空');
        }
        $eventModel = new EventModel();
        $stageModel = new StageModel();
        if(!$f_id){
            Common::echoAjaxJson(13,'场次id为空');
        }
        $info=$eventModel->getEventRedisById($id);
        $stageInfo = $stageModel->getStage($info['sid']);
        if($stageInfo['is_pay']==0){
            Common::echoAjaxJson(12, "对不起，此活动暂时不支持购买，如需帮助请致电13012888193");
        }
        if(!$info||$info['status']>1){
            Common::echoAjaxJson(3, "该服务已不存在");
        }
        if(!$unit_price){
            Common::echoAjaxJson(4, "没有选择单价");
        }
        if(!$num){
            Common::echoAjaxJson(5, "票数不能为0");
        }
        $is_add_num = $eventModel->getNumByUidAndFid($uid,$f_id);
        if(($is_add_num+$num) > $max_num){
            Common::echoAjaxJson(6, "对不起，一个用户只能限购".$max_num."张票");
        }
        $stock_num = $eventModel->getStockNum($price_id);//查库存
        if(!$stock_num){
            Common::echoAjaxJson(7, "对不起，该票价已卖完");
        }
        if($num>$stock_num){
            Common::echoAjaxJson(8, "对不起，该票价只剩".$stock_num."张票");
        }
        if($totals==0||$totals=='0.00'){
            Common::echoAjaxJson(11, "对不起,订单总价不能为0");
        }
        //生成订单号 16位
        $time = time();
        $order_num = '0001';
        $last_order_id = $eventModel->getLastOrderIdByType($info['type']);
        if(!$last_order_id||(int)substr($last_order_id,1,10)!=$time){
            $order_id = $this->orderType[$info['type']].$time.$order_num;
        }else{
            $last = (int)substr($last_order_id,1,15);
            $new = (int)$last+1;
            $order_id = $this->orderType[$info['type']].$new;
        }
        $unit_price = $eventModel->getUnitPrice($price_id);
        $price_totals = $unit_price*$num;
        if($info['agio']){
            $agioArr = explode('&',$info['agio']);
            if($agioArr[0]==2){
                $p_totals = $price_totals*$agioArr[1]/100;
                $agio_price = $price_totals-$p_totals;
            }elseif($agioArr[0]==1){
                  if($price_totals>=$agioArr[1]){
                      $p_totals = $price_totals-$agioArr[2];
                      $agio_price = $price_totals-$p_totals;
                  }else{
                      $p_totals = $price_totals;
                  }
            }else{
                $p_totals = $price_totals;
            }
        }else{
            $p_totals = $price_totals;
        }

        $rs = $eventModel->addOrder($id,$info['sid'],$info['type'],$order_id,$uid,$phone,$unit_price,$num,$is_agio,$info['agio_info'],$agio_price,$totals,date('Y-m-d H:i:s',$time),$f_id,$sp);
        if(!$rs){
            Common::echoAjaxJson(9, "添加失败");
        }
        //减库存
        $eventModel->updateStocknum($price_id,$num,1);
        Common::appLog('event/addOrder',$this->startTime,$version);
        Common::echoAjaxJson(1,'添加成功',array('id'=>$rs,'order_id'=>$order_id,'num'=>$num,'price_totals'=> sprintf("%.2f", $p_totals),));
    }
    //我买到的--我下单的订单列表
    public function getMyOrderListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $is_check = '';
        $order_status = $this->getRequest()->getPost('order_status');//订单状态 0全部 1待付款 2未使用
        if($order_status==2){
            $is_check=0;
        }

        $page = (int)$this->getRequest()->get('page') ? (int)$this->getRequest()->get('page') : 1;
        $size = 20;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $eventModel = new EventModel();
        $list = $eventModel->getMyOrderList($user['uid'],$order_status,$page,$size,$is_check);
        Common::appLog('event/getMyOrderList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list ? $list : array());
    }
    //我卖出的--商家查看自己驿站下的服务信息购票列表
    public function getSellOrderListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $stageModel = new StageModel();
        $sid = $stageModel->getSidByUid($user['uid']);
        $is_check = '';
        $order_status = $this->getRequest()->getPost('order_status');//订单状态 0全部 1待付款 2未使用
        if($order_status==2){
            $is_check=0;
        }
        $page = (int)$this->getRequest()->get('page') ? (int)$this->getRequest()->get('page') : 1;
        $size = ((int)$this->getRequest()->get('size')&&(int)$this->getRequest()->get('size')==20) ? (int)$this->getRequest()->get('size') : 20;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $eventModel = new EventModel();
        $list = $eventModel->getSellOrderList($sid['sid'],$order_status,$page,$size,$is_check);
        Common::appLog('event/getSellOrderList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list ? $list : array());
    }
    //订单详情
    public function orderInfoAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $id = $this->getRequest()->getPost('id');//订单id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2, "订单id为空");
        }
        $eventModel = new EventModel();
        $eventModel->addEventQrCode($id);
        $info = $eventModel->orderInfo($id,$_POST['token'],$version);
        if(!$info){
            Common::echoAjaxJson(3, "对不起,该订单已不存在!");
        }
        Common::appLog('event/orderInfo',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$info);
    }
    //服务详情下单监听调用信息
    public function getInfoForHtmlAction(){
        $id =  $this->getRequest()->get('id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $data['token'] = $this->getRequest()->get('token');
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $eventModel = new EventModel();
        $info = $eventModel->getInfoForHtml($id);
        $is_add_num = $eventModel->getNumByUidAndEid($uid,$id);
        $info['is_add_num'] = 20-$is_add_num;
        if(!$info){
            Common::echoAjaxJson(2, "对不起,该服务已不存在");
        }
        Common::appLog('event/getInfoForHtml',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$info);
    }
    //获取报名列表
    public function getPartakeListBySidAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $last_id = $this->getRequest()->get('last_id');
        $size = intval($this->getRequest()->get('size'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $stageModel = new StageModel();
        $sid = $stageModel->getSidByUid($user['uid']);
        $size = $size ? $size : 5;
        $last_id = $last_id ? $last_id : 0;
        $eventModel = new EventModel();
        $partakeList = $eventModel->partakeListBySid($sid['sid'],$last_id,$size,$_POST['token'],$version);
        Common::appLog('event/getPartakeList',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$partakeList);
    }
    public function viewEventAction(){
        $id = $this->getRequest()->getPost('id');//id
        $token = $this->getRequest()->getPost('token');//用户登录token
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2, "id为空");
        }
        $eventModel = new EventModel();
        $info =$eventModel->getEvent($id);
        $url=I_DOMAIN.'/e/'.$id.'?sid='.$info['sid'].'';
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $url=I_DOMAIN.'/e/'.$id.'?token='.$token.'&sid='.$info['sid'].'&version='.$version.'&replyuid='.$info['uid'].'';
        }
        Common::appLog('event/viewEvent',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$url);
    }
    //服务信息置顶加精
    public function topAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(11, "非法登录用户");
        }
        $uid = $user['uid'];
        $eid = (int) $this->getRequest()->getPost('id')? (int) $this->getRequest()->getPost('id') : (int) $this->getRequest()->getPost('eid');
        $type = (int) $this->getRequest()->getPost('type');
        $data = array(
            'uid' => $uid,
            'eid' => $eid,
            'type'=>$type,
        );
        $rst = Common::http(OPEN_DOMAIN."/stageapi/topEvent", $data, "POST");
        echo $rst;
    }
    //服务信息取消置顶加精
    public function cancelTopAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(11, "非法登录用户");
        }
        $uid = $user['uid'];
        $eid = (int) $this->getRequest()->getPost('id')? (int) $this->getRequest()->getPost('id') : (int) $this->getRequest()->getPost('eid');
        $type = (int)$this->getRequest()->getPost('type');
        $data = array(
            'uid' => $uid,
            'eid' => $eid,
            'type'=> $type,
        );
        $rst = Common::http(OPEN_DOMAIN."/stageapi/cancelTopEvent", $data, "POST");
        echo $rst;
    }
    public function getInfoAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $id = $this->getRequest()->getPost('id');//id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2, "id为空");
        }
        $eventModel = new EventModel();
        $data = $eventModel->getInfo($id,$user['uid']);
        if(!$data){
            Common::echoAjaxJson(3, "该服务已不存在");
        }
        foreach($data['fields_info'] as$k=> $v){
            foreach($v['price_info'] as $k1=>$v1){
                 $data['fields_info'][$k]['price_info'][$k1]['max_partake'] = $v1['stock_num'];
            }
        }
        Common::appLog('event/getInfo',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$data);
    }
    //结束报名
    public function endPartakeAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $uid = $user['uid'];
        $id = $this->getRequest()->getPost('id');//id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2, "服务id为空");
        }
        $eventModel = new EventModel();
        $data = $eventModel->getInfo($id,$uid);
        if(!$data){
            Common::echoAjaxJson(3, "该服务已不存在");
        }
        $eventModel->updatePartakeEndTime(date('Y-m-d H:i:s',time()),$id);
        Common::appLog('event/endPartake',$this->startTime,$version);
        Common::echoAjaxJson(1,"修改成功");
    }
    //服务信息预览
    public function previewAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(26, "非法登录用户");
        }
        $uid = $user['uid'];
        $agio_info = '';$fields_num = 7;
        $eventModel = new EventModel();
        $stageModel = new StageModel();
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $cover = $this->getRequest()->getPost("cover");//封面
        $type= $this->getRequest()->getPost("type");//帖子类型 1:活动  3:培训  6:展览 7:演出
        $type_code = $this->getRequest()->getPost("metid");
        $town = $this->getRequest()->getPost('town_id');//城市三级联动 三级id
        $address = $this->getRequest()->getPost("address");//活动地址
        $lng = $this->getRequest()->getPost("lng");
        $lat = $this->getRequest()->getPost("lat");
        $origin=$this->getRequest()->getPost("origin");//1.PCweb 2.移动web 3.IOS 4.Android
        $agio = $this->getRequest()->getPost('agio');//优惠信息
        $price_type= $this->getRequest()->getPost('price_type');//1.免费 2.收费
        $fields_info = $this->getRequest()->getPost('fields_info');//场次信息
        $content = $this->getRequest()->getPost("content");//内容
        $title = $this->getRequest()->getPost("title");//标题
        $img = $this->getRequest()->getPost("img");//帖子内容图片 多个 & 拼接
        if(!$cover){
            Common::echoAjaxJson(2, "请上传封面");
        }
        if($type==1){
            if(!$type_code){
                Common::echoAjaxJson(3, "请选择内容分类");
            }
        }
        if(!$town){
            Common::echoAjaxJson(4, '城市id不能为空');
        }
        if(!$lng||!$lat){
            Common::echoAjaxJson(5, "请在地图上设置坐标，以便用户更好的查找");
        }
        if(!$address){
            Common::echoAjaxJson(6, "请填写活动具体地址");
        }
        if(!$origin){
            Common::echoAjaxJson(7, "请标明来源");
        }
        if($agio){
            $agioArr = explode('&',$agio);
            if($agioArr[0]==1){
                $agio_info = '满'.$agioArr[1].'立减'.$agioArr[2].'元';
            }
            if($agioArr[0]==2){
                if($agioArr[0]>100||$agioArr[0]<=0){
                    Common::echoAjaxJson(8, "请输入正确的折扣");
                }
                $agio_info = $agioArr[1]/10.0.'折';
            }
        }else{
            $agio_info = '';
        }
        $fields_info = json_decode($fields_info,true);
        if(count($fields_info)>$fields_num){
            Common::echoAjaxJson(9, "一个服务只能设置7个场次");
        }
        foreach($fields_info as $k=>$v){
            if(!$v['partake_end_time']){
                Common::echoAjaxJson(10, "第".($k+1)."个场次请设置截止时间");
            }
            if($v['partake_end_time']>$v['end_time']){
                Common::echoAjaxJson(11,"第".($k+1)."个场次截止时间必须在活动结束前");
            }
            if(!$v['start_time']){
                Common::echoAjaxJson(12, "第".($k+1)."个场次请设置开始时间");
            }
            if(!$v['end_time']){
                Common::echoAjaxJson(13, "第".($k+1)."个场次请设置结束时间");
            }
            if($price_type==2){
                foreach($v['price_info'] as $v1){
                    if(!preg_match('/^[0-9]+(.[0-9]{1,2})?$/',$v1['price'])){
                        Common::echoAjaxJson(14,"第".($k+1)."个场次金额的格式不正确");
                    }
                    if($v1['max_partake']===''){
                        Common::echoAjaxJson(15, "第".($k+1)."个场次请填写人数限制");
                    }
                    $v1['price_mark'] = $v1['price_mark'] ? $v1['price_mark'] :'票价';
                }
            }
            $f_array=array('0'=>'一','1'=>'二','2'=>'三','3'=>'四','4'=>'五','5'=>'六','6'=>'七');
            if(date('Y-m-d',strtotime($v['start_time']))==date('Y-m-d',strtotime($v['end_time']))){
                $fields_info[$k]['show_time'] = '场次'.$f_array[$k].' '.date('m-d H:i',strtotime($v['start_time'])).'至'.date('H:i',strtotime($v['end_time'])).'';
            }else{
                $fields_info[$k]['show_time'] = '场次'.$f_array[$k].' '.date('m-d H:i',strtotime($v['start_time'])).'至'.date('m-d H:i',strtotime($v['end_time'])).'';
            }


        }
        $sid = $stageModel->getSidByUid($uid);
        if(!$sid){
            Common::echoAjaxJson(16,'你没有发布的权限');
        }
        if($sid['is_pay']==0){
            Common::echoAjaxJson(31,'请联系小管家开通支付功能，联系电话：13012888193');
        }
        if($title===''){
            Common::echoAjaxJson(17, "请输入标题");
        }
        if(preg_match('/[A-Za-z]{1,}/',$title)){
            Common::echoAjaxJson(18,'标题不能包含英文字符');
        }
        $title_len = mb_strlen($title,'utf-8');
        if($title_len < 1 || $title_len > 30){
            Common::echoAjaxJson(19,'请输入1-30个中文作为标题');
        }
        $title_rs = $eventModel->titleIsExist($title,$sid['sid']);
//        if($title_rs > 0){
//            Common::echoAjaxJson(20,'对不起，您发的服务信息标题在该驿站已存在');
//        }
        if($content===''){
            Common::echoAjaxJson(21, "请填写内容");
        }
        $security = new Security();
        $content = $security->xss_clean($content);
        //帖子内容里是否有图片
        if($img){
            $imgArr = explode('&',$img);
            foreach($imgArr as $v){
                $content = str_replace($v,'<img src="http://img.91ddcc.com/'.$v.'">',$content);
            }
        }
        $content_len = mb_strlen($content,'utf-8');
        if($content_len>4294967295){
            Common::echoAjaxJson(22, "内容过长");
        }
        $userModel = new UserModel();
        $agioArr = explode('&',$agio);
        if($agioArr&&$agioArr[0]!=3){
            $agio_type = $agioArr[0];
        }else{
            $agio_type = 0;
        }
        if($type==1){
            $eventType = $eventModel->getBusinessEventType($type_code);//获取活动分类内容
            $type_name = $eventType['name'];
        }else{
            $type_name = $this->event_type[$type];
        }
        foreach($fields_info as $k=>$v){
            foreach($v['price_info'] as $k1=>$v1){
                $price_list[]=$v1['price'];
            }
        }
        sort($price_list);
        $data = array(
            'uid'=>$uid,
            'sid'=>$sid['sid'],
            'title'=>$title,
            'cover'=>$cover,
            'content'=>str_replace("\n","<br>",$content),
            'type'=>$type,
            'type_code'=>$type_code,
            'event_address'=>$address,
            'price_type'=>$price_type,
            'agio'=>$agio,
            'agio_info'=>$agio_info,
            'priceInfoArr'=>$fields_info,
            'stageInfo'=>$stageModel->getBasicStageBySid($sid['sid']),
            'add_time'=>date('Y-m-d'),
            'eventTypeClass'=>$this->eventTypeClass,
            'view_num'=>0,
            'user_info'=>$userModel->getUserData($uid),
            'agio_type'=>$agio_type,
            'type_name'=>$type_name,
            'price_count'=>count($price_list),
            'min_price'=>$price_list[0],
            'max_price'=>end($price_list),
        );
        $commonModel = new CommonModel();
        $rs = $commonModel->addPreview(2,json_encode($data));
        Common::appLog('event/preview',$this->startTime,$version);
        if($version>='3.7'){
            Common::echoAjaxJson(1, "发布成功",array('url'=>I_DOMAIN.'/common/preview?id='.$rs));
        }else{
            Common::echoAjaxJson(1, "获取成功",I_DOMAIN.'/common/preview?id='.$rs);
        }
    }
    //服务订单删除
    public function delOrderAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(7, "非法登录用户");
        }
        $uid = $user['uid'];
        $id= $this->getRequest()->getPost("id");//订单主键id
        $type = $this->getRequest()->getPost("type");//4.买家删除 5.卖家删除
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2, "订单id为空");
        }
        $eventModel = new EventModel();
        $orderInfo = $eventModel->orderInfoById($id,1);
        $eventInfo = $eventModel->getEvent($orderInfo['eid']);
        if(!$orderInfo){
            Common::echoAjaxJson(3, "该订单不存在");
        }
        if($type==4&&$uid!=$orderInfo['uid']){
            Common::echoAjaxJson(4, "这不是您的订单，无法删除");
        }
        if($type==5&&$uid!=$eventInfo['uid']){
            Common::echoAjaxJson(5, "您不是卖家，无法删除");
        }
        if($orderInfo['status']==4&&$type==5||$orderInfo['status']==5&&$type==4){
            $type=6;
        }
        $rs = $eventModel->delOrder($id,$type);
        if(!$rs){
            Common::echoAjaxJson(6, "删除失败");
        }
        Common::appLog('event/delOrder',$this->startTime,$version);
        Common::echoAjaxJson(1, "删除成功");
    }
    //活动设置佣金
    public function setCommissionAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $uid = $user['uid'];
        $max_number = '0.5';
        $id_json = $this->getRequest()->getPost('id_json');
        $number = $this->getRequest()->getPost('number');
        $type = $this->getRequest()->getPost('type');//1 设置 2取消设置
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') :APP_VERSION;//版本名
        $is_commission=1;
        if(!$id_json){
            Common::echoAjaxJson(2, "设置的对象为空");
        }
        if($type==1&&!$number){
            Common::echoAjaxJson(3, "设置的佣金率为空");
        }
        if($type==1&&$number>$max_number){
            Common::echoAjaxJson(4, "设置的佣金率最大为".($max_number*100)."%");
        }
        if($type==2){
            $number ='';
            $is_commission=0;
        }
        $eventModel = new EventModel();
        $eventModel->setCommission($is_commission,$number,$uid,$id_json);
        Common::appLog('stagegoods/setCommission',$this->startTime,$version);
        Common::echoAjaxJson(1, '设置成功');
    }
    //活动 分享有奖筛选条件
    public function getCommissionConditionAction(){
        $data['token'] = $this->getRequest()->getPost('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $condition = array();
        $eventModel = new EventModel();
        $code_list = $eventModel->getEventTypeList();
        foreach($code_list as $k=>$v){
            $list[$k]['id'] = $v['id'];
            $list[$k]['name'] = $v['name'];
        }
        $condition['level_first'] = array(array('type'=>'1','name'=>'活动','small_type'=>array()),array('type'=>'3','name'=>'培训','small_type'=>array()),array('type'=>'8','name'=>'展演','small_type'=>array()));
        $condition['level_second'] = array(array('sort_id'=>'1','sort'=>'综合排序'),array('sort_id'=>'2','sort'=>'奖金由高到低'),array('sort_id'=>'3','sort'=>'30天引入订单由高到低'),array('sort_id'=>'4','sort'=>'30天支付累计支出佣金由高到低'));
        Common::appLog('event/getCommissionCondition',$this->startTime);
        Common::echoAjaxJson(1, '获取成功',$condition,$version);
    }
    //活动分享有奖筛选
    public function getCommissionByConditionAction(){
        $data['token'] = $this->getRequest()->getPost('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') :APP_VERSION;//版本名
        $type = $this->getRequest()->getPost('type');//分类
        $sort_id = intval($this->getRequest()->getPost('sort_id'));//排序  1.综合排序（按佣金发布时间排序）2.商品奖金由高到低排序 3.30天引入订单由高到低 4.30天支付累计支出佣金由高到低
        $size = 10; //条数
        $page = $this->getRequest()->getPost("page") ? $this->getRequest()->getPost("page") : 1; //页数
        $sort_id = $sort_id ? $sort_id :1;
        $eventModel = new EventModel();
        $list = $eventModel->getCommissionByCondition($type,$sort_id,$page,$size,$data['token'],$version);
        Common::appLog('event/getCommissionByCondition',$this->startTime,$version);
        Common::echoAjaxJson(1, '获取成功',$list ? $list : array());
    }
}