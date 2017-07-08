<?php
class PaytestController extends Yaf_Controller_Abstract{
    public function getReasonAction(){
        $parameters = array(
            'token' =>'f3284a4e5c9cb5c380f295fb61816b91'
        );
        Common::verify($parameters, '/pay/getReason');
    }
    //获取签名
    public function getSignAction(){
        $parameters = array(
            'token' =>'050e12fefbaa9edcc1791c122a89cf17',
            'order_id'=>'Z14668205310001',
            'pay_type'=>2
        );
        Common::verify($parameters, '/pay/getSign');
    }
    public function updateOrdersAction(){
        $parameters = array(
            'token' =>'ae6dde69cba6102c9ae5862dc2ca73ce',
            'order_id'=>'T14937925960001',
            'pay_type'=>2
        );
        Common::verify($parameters, '/pay/updateOrders');
    }

    public function initWxOrderAction(){
        $eventModel = new EventModel();
        $eventModel->initWxOrder();
    }
    function initOrderStatusAction(){
        $stagegoodsModel = new StagegoodsModel();
        $stagegoodsModel->initOrderStatus();//商品失效订单
//        $eventModel = new EventModel();
//        $eventModel->initOrderStatus();
    }
    public function refundAction(){
        $parameters = array(
            'token' =>'f3284a4e5c9cb5c380f295fb61816b91',
            'order_id'=>'Z14665802880001',
            'pay_type'=>1,
            'reason'=>'测试退款'
        );
        Common::verify($parameters, '/pay/refund');
    }

    //获取签名
    public function getSignForGoodsAction(){
        $parameters = array(
            'token' =>'16275521ceefd087f02d4e7a117a2be7',
            'order_id'=>'S14707059020001',
            'pay_type'=>2
        );
        Common::verify($parameters, '/pay/getSignForGoods');
    }

}