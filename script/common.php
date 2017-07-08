<?php
if ($argc < 2) {
    echo "least two parameter";
    exit;
}

include "/data/site/public_config/i.91ddcc.com/conf/common.php";

define('DOMAIN', '91ddcc.com');
define('IMG_DOMAIN', 'http://img.91ddcc.com/');
define('PUBLIC_DOMAIN', 'http://pub.91ddcc.com/');
define('FM_DOMAIN', 'http://fm.' . DOMAIN);
define('WX_APPID','wx85b5863370bd19d0');
define('WX_MCHID','1250368801');
define('WX_KEY','daidaichuanchengchenyajuan151165');
define('ALI_PARTNER','2088221759813277');
define('ALI_SERVICE','mobile.securitypay.pay');
define('ALI_SELLERID','ddcc@91ddcc.com');
define('ALI_APPID','2016051901420910');

define("APPLICATION_PATH", dirname(dirname(__FILE__)));
$application = new Yaf_Application(APPLICATION_PATH . "/conf/application.ini");
$application->bootstrap()->execute($argv[1]);

/*function initUserReg()
{
    $key = "init:user:appreg";
    $userModel = new UserModel();
    $i = 60;
    while ($i) {
        $redis = CRedis::getInstance();
        $data = $redis->lPop($key);
        if ($data) {
            $arr = json_decode($data);
            if(isset($arr[0]) && isset($arr[1])){
                $userModel->initUser($arr[0],$arr[1]);
            }
        }
        $i--;
        sleep(1);
    }
}*/
//查询微信平台订单状态 并更新至数据库
function initWxOrder(){
    $eventModel = new EventModel();
    $eventModel->initWxOrder();
}
//查询微信平台退款订单的退款状态，并更新至数据库
/*function initWxOrderRefund(){
    $eventModel = new EventModel();
    $eventModel->initWxOrderRefund();
}*/
//查询订单失效状态并更新数据库
function initOrderStatus(){
    $eventModel = new EventModel();
    $eventModel->initOrderStatus();//服务信息失效订单
    //服务信息失效的支付订单 改变订单状态为已支付
    $eventModel->setBuyOrderStatus();
    $stagegoodsModel = new StagegoodsModel();
    $stagegoodsModel->initOrderStatus();//商品失效订单
    //商品失效的支付订单 改变订单状态为已支付
    $stagegoodsModel->setBuyOrderStatus();

}
//查询商品订单自动收货，并更新数据
function initGoodsOrder(){
    $stagegoodsModel = new StagegoodsModel();
    $stagegoodsModel->initGoodsOrder();
    //服务活动即将开始提醒
    $eventModel = new EventModel();
    $eventModel->noticeAndSms();
}
//每天早上9点 用户推送
function initSendForUser(){
    $userModel = new UserModel();
    $userModel->initSendForUser();
}
//每天定时脚本--夜里1点钟执行
function addUserSpToLog() {
    $date = date('Y-m-d',strtotime('-1 day'));
    $userModel = new UserModel();
    $userModel->addUserSpToLog($date);
    //30天引入订单量，引入订单金额
    //商品
    $stagegoodsModel = new StagegoodsModel();
    $stagegoodsModel->updateStatistics();
    //服务
    $eventModel = new EventModel();
    $eventModel->updateStatistics();

}
