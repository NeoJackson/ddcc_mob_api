<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 14-10-14
 * Time: 下午1:34
 */

class HomeController extends Yaf_Controller_Abstract {
    public function init(){
        $this->startTime = microtime(true);
    }

    //ajax获取个人动态列表
    public function listAction(){
        $param['token'] = $this->getRequest()->get('token');
        $param['did'] = $this->getRequest()->get('did');
        if($param['token']){
            $user=Common::isLogin($param);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $this->getView()->token = $param['token'];
            $this->getView()->user = $user;
        }
        $last = intval($this->getRequest()->get('last'));
        $size = intval($this->getRequest()->get('size'));
        if(!$size||$size< 1){
            $size = 10;
        }
        if($size > 50){
            $size = 50;
        }
        if($last == 0){
            Common::echoAjaxJson(1,"获取成功",array('list'=>array('date'=>'','html'=>''), 'last'=>$last));
        }
        $feedModel = new FeedModel();
        $userModel = new UserModel();
        $home_user = $userModel->getUserByDid($param['did']);
        $feeds = $feedModel->getUserAppList($home_user['uid'],$last,$size);
        $data = array(
            'list' => $feedModel->getData('user',$feeds['list'],0,$user['uid']),
            'size' => $feeds['size']
        );
        $feedList = array();
        foreach($data['list'] as $val){
            $this->getView()->dynamic = $val;
            $new_date = $this->timeContent($val['last_time']);
            $feedList[] = array('date'=>$new_date,'html'=>$this->render("dynamic"));
        }
        $last = isset($data['list']) && count($data['list'])>0?$data['list'][count($data['list'])-1]['last_time']:0;
        Common::echoAjaxJson(1,"获取成功",array('list'=>$feedList, 'last'=>$last));
    }
    //时间处理
    public function timeContent($time){
        if(date('Y-m-d',$time) == date("Y-m-d")){
            $new_date = '<span class="big-text">今天</span>';
        }elseif(date('Y-m-d',$time) == date('Y-m-d',strtotime('-1 day'))){
            $new_date = '<span class="big-text">昨天</span>';
        }else{
            $new_date = '<span class="big-text">'.date('d',$time).'</span><span class="small-text">'.date('m',$time).'月</span>';
        }
        return $new_date;
    }

    //用户二维码H5
    public function qrCodeAction(){
        $id = $this->getRequest()->get('id');//二维码对象id
        $type = $this->getRequest()->get('type');//1.用户 2.驿站
        $this->getView()->type = $type;
        $userModel = new UserModel();
        $stageModel = new StageModel();
        if($type==1){
            $user = $userModel->getUserByUid($id);
            $this->getView()->user = $user;
            $this->getView()->qrCodeImg = PHPQRCode::getUserPHPQRCode($id,false);//二维码
        }elseif($type==2){
            $stageInfo = $stageModel->getStage($id);
            $this->getView()->stageInfo = $stageInfo;
            $this->getView()->qrCodeImg = PHPQRCode::getStagePHPQRCode($id,false);//二维码
        }
        $this->display("qrCode");

    }

}