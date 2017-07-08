<?php
class EventtestController extends Yaf_Controller_Abstract
{
    //删除商家活动
    public function delEventAction(){
        $parameters = array(
            'token' =>'328c51358a58465dbee79b77d9656994',
            'id'=>2506
        );
        Common::verify($parameters, '/event/delEvent');
    }
    //设置字体
    public function setHtmlFontAction(){
        $parameters = array(
            'token' =>'5cc288ef7ab607b00d3e58a7516d6cc3',
            'font_type'=>'3'
        );
        Common::verify($parameters, '/event/setHtmlFont');

    }
    //发布商家活动
    public function addEventAction(){
        $fields_info = array(
            0=>array(
                'start_time'=>'2016-09-28 00:00:00',
                'end_time'=>'2016-12-30 00:00:00',
                'partake_end_time'=>'2016-11-29 00:00:00',
                'price_info'=>array(
                    0=>array('price'=>0,'price_mark'=>'','max_partake'=>10),
                ),
            ),
        );


//         [origin=4, version=null, token=3e910d3b9ae75d43182c38db04f56482, title=免费的, cover=14764302283176.jpg, content=<p>保护环境</p>14764302332701.jpg<p>
 //       半年不能那你给本宝宝巴巴爸爸吧巴巴爸爸吧给本宝宝VBGVB不不不叫姐姐
    //    </p>14764302344154.jpg, type=1, img=14764302332701.jpg&14764302344154.jpg, agio=null, address=中国上海市浦东新区世纪大道820号, lng=116.39886, lat=39.909611, poids=3&4, pomust=1&1, town_id=130503, price_type=1, fields_info=[{"end_time":"2016-10-31 15:28:00","partake_end_time":"2016-10-26 15:29:00","price_info":[{"max_partake":"60","price":"0","price_mark":""}],"start_time":"2016-10-21 15:28:00"}], metid=2]

        $parameters = array(
            'token' =>'a959a5e68551f6f5d0b8c4bcdd17c0e4',
            'title' => '推广活动444',
            'cover'=>'photo_09105_6829.jpg',
            'content' => '<p>哈哈那就</p>',
            'img'=>'',
            'type' => '6',
            'origin'=>4,
            'lng'=>'116.398993',
            'lat'=>'39.908448',
            'address'=>'中国上海市浦东新区世纪大道820号',
            'town_id'=>'140822',
            //'metid'=>'',
            'price_type'=>2,
            'fields_info'=>'[{"end_time":"2016-10-31 15:28:00","partake_end_time":"2016-10-26 15:29:00","price_info":[{"max_partake":"20","price":"90","price_mark":"11"}],"start_time":"2016-10-21 15:28:00"}]',
            //'poids'=>'3',
            //'pomust'=>'1'
        );
       Common::verify($parameters, '/event/addEvent');
    }
    public function updateEventAction(){

        $fields_info = array(
            0=>array(
                'start_time'=>'2017-09-28 00:00:00',
                'end_time'=>'2017-12-30 00:00:00',
                'partake_end_time'=>'2017-11-29 00:00:00',
                'price_info'=>array(
                    0=>array('price'=>11110,'price_mark'=>'','max_partake'=>6),
                ),
            ),
            1=>array(
                'start_time'=>'2017-09-28 00:00:00',
                'end_time'=>'2017-09-30 00:00:00',
                'partake_end_time'=>'2017-09-29 00:00:00',
                'price_info'=>array(
                    0=>array('price'=>30,'price_mark'=>'30','max_partake'=>3),
                ),
            ),
//            2=>array(
//                'start_time'=>'2017-09-28 00:00:00',
//                'end_time'=>'2017-09-30 00:00:00',
//                'partake_end_time'=>'2017-09-29 00:00:00',
//                'price_info'=>array(
//                    0=>array('price'=>40,'price_mark'=>'40 ','max_partake'=>60),
//                    1=>array('price'=>140,'price_mark'=>'140 ','max_partake'=>160),
//                ),
//            ),
        );
        $parameters = array(
            'token' =>'9692aa54406c447945a8252be3965cb7',
            'id' => '3002',
            'title' => '哈尔滨的雕',
            'cover'=>'photo_09105_6829.jpg',
            'content' => 'photo_09391_3546.jpg新的字段photo_09105_6829.jpg',
            'img'=>'photo_09391_3546.jpg&photo_09105_6829.jpg',
            'type' => 1,
            'origin'=>3,
            'lng'=>'121.452656',
            'lat'=>'31.330240',
            'address'=>'上海市宝山万达广场',
            'town_id'=>'310103',
            'metid'=>20,
            'price_type'=>2,
            'fields_info'=>json_encode($fields_info),
            'version'=>'3.7.1',
//            'poids'=>'1&2&3&4',
//            'pomust'=>'0&1&0&1'
        );


        Common::verify($parameters, '/event/updateEvent');
    }
    //测试获取商家帖子详情
    public function getEventNewAction(){
        $parameters = array(
            'token' =>'0950bbcad10dc40f9c6403a7f0599cd2',
            'id' => '2109'
        );
        Common::verify($parameters, '/event/getEventNew');
    }
    //测试用户浏览帖子
    public function viewEventAction(){
        $parameters = array(
            'token'=>'cbd952cb6bb76aed3a98fe90be033a6b',
            'id' => '96'
        );
        Common::verify($parameters, '/event/viewEvent');
    }
    //修改商家活动
    public function updateAction(){
        $parameters = array(
            'token' =>'092c57f5357755f50b836583071b63fb',
            'id' => '2205',
            'title' => '测试大数据报删11',
            'cover'=>'14567319074245.jpg',
            'content' => '测试活动帖子的详情',
            'is_close' =>'1',
            'address'=>'但好好的的回答111111',
            'lng'=>'121.487044',
            'lat'=>'31.271237',
            'end_time'=>'2016-08-01 09:33"',
            "town_id" => 220101,
            'version' => "2.6",
            'poids' => "3&4",
            'pomust' => "1&2",
            "partake_end_time" => "2016-07-23 09:33"
        );
/*
 * {
    address = "\U4e2d\U56fd\U5409\U6797\U7701\U957f\U6625\U5e02\U5357\U5173\U533a\U81ea\U5f3a\U8857\U9053\U957f\U6625\U5927\U8857635\U53f7";
    content = "<p>\U5728\U4f60\U9762\U524d\U518d\U6765\U70b9\U5b9e\U9645\U662f\U6211\U8981\U7684\U65e5\U4e2d\U4e24\U56fd\U5173\U7cfb\U53d1\U5c55\U7684\U54e5\Uff0c\U4f60\U7684\U4eba\U5bb6\U90fd\U6c5f\U5830\U7684\U4eba\U90fd\U6709\U81ea\U5df1\n</p><img src=\"http://img.91ddcc.com/14671640321651.jpg\"><p>\n</p>";
    cover = "14671640331288.jpg";
    "end_time" = "2016-08-01 09:33";
    id = 2185;
    "is_close" = 1;
    lat = "43.887161";
    lng = "125.325033";
    "partake_end_time" = "2016-07-23 09:33";
    poids = "";
    pomust = "";
    title = "\U5728\U4f60\U9762\U524d\U54ed\U514d\U8d39";
    token = 092c57f5357755f50b836583071b63fb;
    "town_id" = 220101;
    version = "2.6";
}*/

      Common::verify($parameters, '/event/update');
    }
    //管理中心--获取商家专贴列表
    public function getEventListAction(){
        $parameters = array(
            'token' =>'4ecc237cb404a740877c95391bd2f459',
            'type'=>0,
            'size'=>10,
            'last_time'=>0
        );
        Common::verify($parameters, '/event/getEventList');
    }
    public function getTypeAction(){
        $parameters = array(
            'token' =>'f4b32c7db5b5171fe38a7f7153b7ec09',
        );
        Common::verify($parameters, '/event/getType');
    }
    //发布新
    public function addEventNewAction(){
        $parameters = array(
            'token' =>'e1daf49bbace3d257135ac1a445cf73b',
            'title' => '老测444444444',
            'cover'=>'14495666892988.jpg',
            'content' => '哈哈哈',
            //'img'=>'photo_09391_3546.jpg&photo_09105_6829.jpg',
            'type' => '3',
            'is_recommend' =>'0',
            'origin'=>4,
            'lng'=>'121.520354',
            'lat'=>'31.230616',
            'address'=>'上海市宝山万达广场',
            'price'=>'123',
            'metid'=>0,
            'start_time'=>'2015-12-10 17:20:53',
            'end_time'=>'2016-01-25 05:20:53',
            'partake_start_time'=>'2015-12-07 14:29:15',
            'partake_end_time'=>'2015-12-11 14:29:15',
            'version'=>'2.5.1',
            'town_id'=>130101,
            'max_partake'=>20,
            //'poids'=>'1&2&3',
            //'pomust'=>'0&1&0'
        );
        Common::verify($parameters, '/event/addEventNew');
    }
    //获取报名选项信息
    public function getPartakeOptionAction(){
        $parameters = array(
            'token' =>'68591c34df8a90c13d2c3d9107b50c29'
        );
        Common::verify($parameters, '/event/getPartakeOption');
    }
    //获取报名选项信息
    public function getPartakeOptionByEidAction(){
        $parameters = array(
            'token' =>'58c5d6d148aadd86fec5865d852fcf4e',
            'eid'=>2943
        );
        Common::verify($parameters, '/event/getPartakeOptionByEid');
    }
    public function addPartakeAction(){
        $parameters = array(
            'token' =>'d76862575be15ee87aa6eb8851f46a4c',
            'name'=>'老孙二',
            //'nick_name'=>'孙哒哒',
            'mobile'=>12345619908,
            //'p_options'=>'5&8&9',
            //'p_values'=>'222&333&444',
            'id'=>2906,
            'origin'=>3,
            "f_id"=>2962,
        );
        Common::verify($parameters, '/event/addPartake');
    }
    //用户电子票
    public function myTicketsAction(){
        $parameters = array(
            'token' =>'7f7a1e42a8559e47c92b7f6f3cafd7ce',
            'id'=>2526,
            'f_id'=>2441,
            'version'=>'3.5'
        );
        Common::verify($parameters, '/event/myTickets');
    }
    public function getPartakeListAction(){
        $parameters = array(
            'token' =>'a0c0e7d1e0bdec7ff679f1c24aa43f3f',
            'id'=>1696,
            'last_id'=>0
        );
        Common::verify($parameters, '/event/getPartakeList');
    }
    public function myTicketListAction(){
        $parameters = array(
            'token' =>'986846f3f1d5cd261af481c3ee580837',
            'is_check'=>0,
            'page'=>1,
            'size'=>10,
            'version'=>'2.5'
        );
        Common::verify($parameters, '/event/myTicketList');
    }
    public function closePartakeAction(){
        $parameters = array(
            'token' =>'328c51358a58465dbee79b77d9656994',
            'id'=>'2521'
        );
        Common::verify($parameters, '/event/endPartake');
    }
    public function getEventConditionAction(){
        $parameters = array(
        );
        Common::verify($parameters, '/event/getEventCondition');
    }
    public function getListByConditionAction(){
        $parameters = array(
            'type'=>1,
            'city'=>'全国',
            'id'=>3,
            'sort'=>'',
            'page'=>1,
           // 'size'=>1
        );
        Common::verify($parameters, '/event/getListByCondition');
    }

    /*
     * {
    address = "\U4e2d\U56fd\U5317\U4eac\U5e02\U897f\U57ce\U533a\U897f\U957f\U5b89\U8857\U8857\U9053";
    content = "<p>\U5728\U4e8e\U4f60\U8bf4\U4e86\U4e00\U4e2a\U5f88\U957f\U7684\U58eb\U997f[\U62a0\U9f3b][\U62a0\U9f3b][\U62a0\U9f3b][\U62a0\U9f3b][\U64e6\U6c57][\U518d\U89c1]\n</p>14660432829404.jpg<p>\n</p>";
    cover = "14660432873633.jpg";
    "end_time" = "2016-08-16 10:13";
    feeArray =     (
                {
            Index = 0;
            "max_partake" = 654;
            price = 45569;
            "price_mark" = "\U5728\U4e00\U8d77\U65f6\U4ee3";
        }
    );
    img = "14660432829404.jpg";
    lat = "39.906637";
    lng = "116.474486";
    "max_partake" = 654;
    origin = 3;
    "partake_end_time" = "2016-07-31 10:13";
    price = 45569;
    "price_mark" = "\U5728\U4e00\U8d77\U65f6\U4ee3";
    "start_time" = "2016-07-09 10:13";
    title = "\U6f14\U51fa\U65e0\U4f18\U60e0";
    token = 4b1acc653fa53af0a605c91d8074fbcb;
    "town_id" = 820146;
    type = 7;
    version = "2.6";
*/
    public function addAction(){
        $parameters = array(
            'token' =>'a959a5e68551f6f5d0b8c4bcdd17c0e4',
            'title' => '推广',
            'cover'=>'14662317667704.jpg',
            'content' => '<p>继续吹吧！！！！</p>14662315645193.jpg14662315643795.jpg',
            'img'=>'14662315645193.jpg&14662315643795.jpg&14662317673081.jpg&14662317682880.jp',
            'type' => '3',
            'origin'=>4,
            'lng'=>'116.474486',
            'lat'=>'39.9066376',
            'address'=>'上海市宝山万达广场',
            'price'=>'30&20',
            'price_mark'=>'222&123',
            'agio'=>'1&180.88&20.99',
            'metid'=>11,
            'start_time'=>'2016-06-17 15: 50: 00',
            'end_time'=>'2016-06-28 15: 50: 00',
            'partake_end_time'=>'2016-06-20 15: 50: 00',
            'version'=>'2.6',
            'town_id'=>610116,
            'max_partake'=>'50&50',
//            'poids'=>'3&4&5&6&7&9&10&11&15&16',
//            'pomust'=>'0&1&0&1&1&0&0&0&0&0'
        );

        Common::verify($parameters, '/event/add');
    }
    public function sendOrderSmsCodeAction(){
        $parameters = array(
            'user_name' =>17301650402
        );
        Common::verify($parameters, '/event/sendOrderSmsCode');
    }
    public function verifyOrderSmsCodeAction(){
        $parameters = array(
            'user_name' =>17301650402,
            'check_code' =>742031,
            'id'=>2019
        );
        Common::verify($parameters, '/event/verifyOrderSmsCode');
    }
    public function addOrderAction(){
        $parameters = array(
            'token' =>'22d25ab5e6811e5f85a40621e90b3265',
            'id' =>2918,
            'unit_price' =>"1000",
            'phone'=>'13781220329',
            'num'=>10,
            'is_agio'=>'0',
            'agio_price'=>'',
            'totals'=>'220',
            "price_id" => 3220,
            "f_id"=>2976,
            'sp'=>'TVRReU1ESXRNamcxTFRFd0xURXlMVEk1TVRndE16VTM='
        );
        Common::verify($parameters, '/event/addOrder');
    }
    //我买到的
    public function getMyOrderListAction(){
        $parameters = array(
            'token' =>'8275d0b0a7c7b7f1e2928fab3f8877c3',
            'order_status'=>2 ,
        );
        Common::verify($parameters, '/event/getMyOrderList');
    }
    //我买到的
    public function getSellOrderListAction(){
        $parameters = array(
            'token' =>'69ce80ac6242b63921d148619eef1fba',
        );
        Common::verify($parameters, '/event/getSellOrderList');
    }
    //订单详情
    public function orderInfoAction(){
        $parameters = array(
            'token' =>'aefd44c7af718e86208c6245e4e3cd92',
            'id'=>983
        );
        Common::verify($parameters, '/event/orderInfo');
    }
    public function getInfoForHtmlAction(){
        $parameters = array(
            'token' =>'21260392946beee33c695a1ad7491586',
            'id'=>2032
        );
        Common::verify($parameters, '/event/getInfoForHtml');
    }
    public function getPartakeListBySidAction(){
        $parameters = array(
            'token' =>'1dd828a092f7c517e2ec81aebf78a354',
            'last_id'=>0
        );
        Common::verify($parameters, '/event/getPartakeListBySid');
    }
    public function getInfoAction(){
        $parameters = array(
            'token' =>'328c51358a58465dbee79b77d9656994',
            'id'=>2924
        );
        Common::verify($parameters, '/event/getInfo');
    }
    public function cancelTopAction(){
        $parameters = array(
            'token'=>'328c51358a58465dbee79b77d9656994',
            'eid' => '2527',
            'type'=>1
        );
        Common::verify($parameters, '/event/cancelTop');
    }
    public function previewAction(){
        $parameters = array(
            'token' =>'3e910d3b9ae75d43182c38db04f56482',
            'title' => '测试预览',
            'cover'=>'photo_09105_6829.jpg',
            'content' => '覺得説的就是的數據庫和是大家開會十大科技和開始打瞌睡都是的收款计划',
            'img'=>'',
            'type' => '6',
            'origin'=>4,
            'lng'=>'116.398993',
            'lat'=>'39.908448',
            'address'=>'中国上海市浦东新区世纪大道820号',
            'town_id'=>'140822',
            //'metid'=>'',
            'price_type'=>2,
            'fields_info'=>'[{"end_time":"2016-10-31 15:28:00","partake_end_time":"2016-10-26 15:29:00","price_info":[{"max_partake":"120","price":"210","price_mark":"11"},{"max_partake":"20","price":"110","price_mark":"11"},{"max_partake":"20","price":"2110","price_mark":"11"}],"start_time":"2016-10-21 15:28:00"}]',
            //'poids'=>'3',
            //'pomust'=>'1'
        );
        Common::verify($parameters, '/event/preview');
    }
    public function setCommissionAction(){
        $parameters = array(
            'token'=>'f13c9c7e6f6beb272186990216bf3b73',
            'number' => '0.19',
            'type'=>1,
            'id_json'=>json_encode(array('3157')),
        );
        Common::verify($parameters, '/event/setCommission');
    }
    public function getCommissionConditionAction(){
        $parameters = array(
            'token'=>'09be2e56910df90ad4bb5adadf153c52'
        );
        Common::verify($parameters, '/event/getCommissionCondition');
    }
    public function getCommissionByConditionAction(){
        $parameters = array(
            'type'=>1,
            'sort_id'=>0
        );
        Common::verify($parameters, '/event/getCommissionByCondition');
    }
}