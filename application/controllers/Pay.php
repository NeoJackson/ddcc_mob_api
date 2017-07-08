<?php
class PayController extends Yaf_Controller_Abstract {
    public function init(){
        $this->startTime = microtime(true);
    }
    //退款原因
    public function getReasonAction(){
        $user = Common::isLogin($_POST);
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $array = array(
            '0' => '我不想买了',
            '1' => '我买了其他的',
            '2' => '不喜欢',
            '3' => '预约不到',
            '4' => '评价不好',
            '5' => '我现场买了',
            '6' => '其他原因'
        );
        Common::appLog('pay/getReason',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$array);
    }
    //获取签名
    public function getSignAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $order_id = $this->getRequest()->get('order_id');
        $type = intval($this->getRequest()->get('pay_type'));//1支付宝 2微信 3网银
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $eventModel = new EventModel();
        $orderInfo = $eventModel->orderInfoByOrderId($order_id);
        $time = time();
        if(!$orderInfo){
            Common::echoAjaxJson(2, "该订单已不存在!");
        }
        $order_status = $eventModel->orderInfoByOrderId($order_id);
        if($order_status['order_status']==2){
            Common::echoAjaxJson(4, "该订单已支付!");
        }
        if((strtotime($order_status['add_time'])+900)<$time){
            $eventModel->updateOrderStatus(5,$order_id);
            Common::echoAjaxJson(5, "该订单已失效!");
        }
        $eventInfo = $eventModel->getEvent($orderInfo['eid']);
        $url =  I_DOMAIN.'/e/'.$orderInfo['eid'].'?token='.$_POST['token'].'&version='.$version;
        if($type ==1){
            $data = array(
                'service' => ALI_SERVICE,
                'partner' => ALI_PARTNER,//合作者身份pid
                '_input_charset' => 'utf-8',
                'sign_type' => 'RSA',
                'sign' => '',
                'notify_url' => urlencode($url),//回调地址
                'out_trade_no' => $order_id,//商户网站唯一订单号
                'subject' => $eventInfo['title'],//商品名称
                'payment_type' => 1,//支付类型
                'seller_id' => ALI_SELLERID,//支付宝账号
                'total_fee' => $orderInfo['totals'],//总金额
                'body' => $eventInfo['summary'],//商品详情
            );
            $data = Common::getAlipaySign($data);
            $sign = $data['str'];
        }
        if($type ==2){
            $order_status = $eventModel->getWxOrderInfo($order_id,time());
            if(isset($order_status['trade_state'])&&$order_status['trade_state']=='SUCCESS'){
                Common::echoAjaxJson(4, "该订单已支付!");
            }
            $noncestr = strtoupper(md5($time));
            $totals = $orderInfo['totals']*100;
            $str = 'appid='.WX_APPID.'&body='.$eventInfo['title'].'&mch_id='.WX_MCHID.'&nonce_str='.$noncestr.'&notify_url=http://sns.91ddcc.com&out_trade_no='.$order_id.'&spbill_create_ip=101.81.130.25&total_fee='.$totals.'&trade_type=APP&key='.WX_KEY.'';
            $data = array(
                'appid'=>WX_APPID,
                'body'=>$eventInfo['title'],
                'mch_id'=>WX_MCHID,
                'nonce_str'=>$noncestr,
                'notify_url'=>WX_PAY_DOMAIN,
                'out_trade_no'=>$order_id,
                'spbill_create_ip'=>'101.81.130.25',
                'sign'=>strtoupper(md5($str)),
                'total_fee'=>$totals,
                'trade_type'=>'APP'
            );
            $prepay = Common::getWxPrepayid($data);
            $str1 = 'appid='.WX_APPID.'&noncestr='.$noncestr.'&package=Sign=WXPay&partnerid='.WX_MCHID.'&prepayid='.$prepay['prepay_id'].'&timestamp='.$time.'&key='.WX_KEY.'';
            $sign = array(
                'appid'=>WX_APPID,
                'partnerid'=>WX_MCHID,
                'prepayid'=>$prepay['prepay_id'],//预单号微信接口返回
                'noncestr'=>$noncestr,
                'timestamp'=>"".$time."",
                'package'=>'Sign=WXPay',
                'signType' => 'MD5',
                'sign'=>strtoupper(md5($str1))
            );
        }
        Common::appLog('pay/getSign',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$sign);
    }
    //支付成功后 订单修改接口
    public function updateOrdersAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $order_id = $this->getRequest()->get('order_id');
        $type = intval($this->getRequest()->get('pay_type'));//1支付宝 2微信 3网银
        $seller_id ='';//卖家付款账号
        $time = time();
        $pay_time = date('Y-m-d H:i:s',$time);
        $order_status = 2;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if($type==2){
            $seller_id = WX_MCHID;
        }
        if($type==1){
            $seller_id = ALI_SELLERID;
        }
        $eventModel = new EventModel();
        $eventModel->updateOrder($order_id,$type,$seller_id,$order_status,$pay_time);
        Common::appLog('pay/updateOrders',$this->startTime,$version);
        Common::echoAjaxJson(1, "修改成功");
    }
    //获取签名
    public function getSignForGoodsAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $order_id = $this->getRequest()->get('order_id');
        $type = intval($this->getRequest()->get('pay_type'));//1支付宝 2微信 3网银
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $stagegoodsModel = new StagegoodsModel();
        $orderInfo = $stagegoodsModel->orderInfoByOrderId($order_id);
        $time = time();
        if(!$orderInfo){
            Common::echoAjaxJson(2, "该订单已不存在!");
        }
        if($orderInfo['order_status']==2){
            Common::echoAjaxJson(4, "该订单已支付!");
        }
        if((strtotime($orderInfo['add_time'])+900)<$time){
            $stagegoodsModel->updateOrderStatus(5,$order_id);
            Common::echoAjaxJson(5, "该订单已失效!");
        }
        $goodInfo = $stagegoodsModel->getInfo($orderInfo['goods_id']);
        $url =  I_DOMAIN.'/g/'.$goodInfo['id'].'?token='.$_POST['token'].'&version='.$version;
        if($type ==1){
            $data = array(
                'service' => ALI_SERVICE,
                'partner' => ALI_PARTNER,//合作者身份pid
                '_input_charset' => 'utf-8',
                'sign_type' => 'RSA',
                'sign' => '',
                'notify_url' => urlencode($url),//回调地址
                'out_trade_no' => $order_id,//商户网站唯一订单号
                'subject' => $goodInfo['name'],//商品名称
                'payment_type' => 1,//支付类型
                'seller_id' => ALI_SELLERID,//支付宝账号
                'total_fee' => $orderInfo['price_totals'],//总金额
                'body' => $goodInfo['name'],//商品详情
            );
            $data = Common::getAlipaySign($data);
            $sign = $data['str'];
        }
        if($type ==2){
            $eventModel = new EventModel();
            $order_status = $eventModel->getWxOrderInfo($order_id,time());
            if(isset($order_status['trade_state'])&&$order_status['trade_state']=='SUCCESS'){
                Common::echoAjaxJson(4, "该订单已支付!");
            }
            $noncestr = strtoupper(md5($time));
            $totals = $orderInfo['price_totals']*100;
            $str = 'appid='.WX_APPID.'&body='.$goodInfo['name'].'&mch_id='.WX_MCHID.'&nonce_str='.$noncestr.'&notify_url=http://sns.91ddcc.com&out_trade_no='.$order_id.'&spbill_create_ip=101.81.130.25&total_fee='.$totals.'&trade_type=APP&key='.WX_KEY.'';
            $data = array(
                'appid'=>WX_APPID,
                'body'=>$goodInfo['name'],
                'mch_id'=>WX_MCHID,
                'nonce_str'=>$noncestr,
                'notify_url'=>WX_PAY_DOMAIN,
                'out_trade_no'=>$order_id,
                'spbill_create_ip'=>'101.81.130.25',
                'sign'=>strtoupper(md5($str)),
                'total_fee'=>$totals,
                'trade_type'=>'APP'
            );
            $prepay = Common::getWxPrepayid($data);
            $str1 = 'appid='.WX_APPID.'&noncestr='.$noncestr.'&package=Sign=WXPay&partnerid='.WX_MCHID.'&prepayid='.$prepay['prepay_id'].'&timestamp='.$time.'&key='.WX_KEY.'';
            $sign = array(
                'appid'=>WX_APPID,
                'partnerid'=>WX_MCHID,
                'prepayid'=>$prepay['prepay_id'],//预单号微信接口返回
                'noncestr'=>$noncestr,
                'timestamp'=>"".$time."",
                'package'=>'Sign=WXPay',
                'signType' => 'MD5',
                'sign'=>strtoupper(md5($str1))
            );
        }
        Common::appLog('pay/getSignForGoods',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$sign);
    }
    //支付成功后 订单修改接口
    public function updateGoodsOrdersAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $order_id = $this->getRequest()->get('order_id');
        $type = intval($this->getRequest()->get('pay_type'));//1支付宝 2微信 3网银
        //$buy_id = intval($this->getRequest()->get('buy_id'));//买家支付账号
        $seller_id ='';//卖家付款账号
        $time = time();
        $pay_time = date('Y-m-d H:i:s',$time);
        $order_status = 2;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if($type==2){
            $seller_id = WX_MCHID;
        }
        if($type==1){
            $seller_id = ALI_SELLERID;
        }
        $stagegoodModel = new StagegoodsModel();
        $stagegoodModel->updateOrder($order_id,$type,$seller_id,$order_status,$pay_time);
        //付款成功通知卖家发货
        $orderInfo = $stagegoodModel->orderInfoByOrderId($order_id);
        $goods_info = $stagegoodModel->getInfo($orderInfo['goods_id']);
        if($user['uid']!=$goods_info['uid']){
           Common::addNoticeAndSmsForGoods(1,$order_id);
        }
        Common::appLog('pay/updateGoodsOrders',$this->startTime,$version);
        Common::echoAjaxJson(1, "修改成功");
    }
}