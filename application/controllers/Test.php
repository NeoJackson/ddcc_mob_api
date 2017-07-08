<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 15-8-18
 * Time: 下午4:39
 */
class TestController extends Yaf_Controller_Abstract
{
    public function indexAction()
    {
        $feedModel = new FeedModel();
        $feeds = $feedModel->getUserAppList('6516', 0, 20);
        $list = $feedModel->getData('user', $feeds['list'], 0, 0);
        echo "<pre>";
        print_r($list);
    }

    public function qingFengAction()
    {
        $userModel = new UserModel();
        $user_info = $userModel->getUserData('3568');
        $home_cover = $userModel->getUserInfoByUid($user_info['uid']);
        $user_info['home_cover'] = $home_cover['home_cover'] ? IMG_DOMAIN . $home_cover['home_cover'] : PUBLIC_DOMAIN . 'default_app_cover.jpg';
        echo "<pre>";
        print_r($user_info);
        echo '*******下面是封面图*******';
        $rs = $userModel->getDefaultCover();
        echo "<pre>";
        print_r($rs);

    }

    public function stageCityAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("select sid,lat,lng from business");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $addressInfo = Api::getCity($v['lat'], $v['lng']);
                $province = $addressInfo['result']['addressComponent']['province'] ? str_replace('市', '', $addressInfo['result']['addressComponent']['province']) : '';
                $city = $addressInfo['result']['addressComponent']['city'] ? str_replace('市', '', $addressInfo['result']['addressComponent']['city']) : '';
                $town = $addressInfo['result']['addressComponent']['district'] ? $addressInfo['result']['addressComponent']['district'] : '';
                if ($province && $city && $town) {
                    $commonModel = new CommonModel();
                    $commonModel->modifyAddress($province, $city, $town);
                    $province_id = $commonModel->getCity($province, 1);
                    $city_id = $commonModel->getCity($city, 2);
                    $town_id = $commonModel->getCity($town, 3);
                    $stmt_city = $this->db->prepare("update stage set province=:province, city = :city,town=:town where sid=:sid ");
                    $array = array(
                        ':province' => $province_id,
                        ':city' => $city_id,
                        ':town' => $town_id,
                        ':sid' => $v['sid']
                    );
                    $stmt_city->execute($array);
                }
            }
        }
    }

    public function eventCityAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("select id,lat,lng from event");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $addressInfo = Api::getCity($v['lat'], $v['lng']);
                $province = $addressInfo['result']['addressComponent']['province'] ? str_replace('市', '', $addressInfo['result']['addressComponent']['province']) : '';
                $city = $addressInfo['result']['addressComponent']['city'] ? str_replace('市', '', $addressInfo['result']['addressComponent']['city']) : '';
                $town = $addressInfo['result']['addressComponent']['district'] ? $addressInfo['result']['addressComponent']['district'] : '';
                if ($province && $city && $town) {
                    $commonModel = new CommonModel();
                    $commonModel->modifyAddress($province, $city, $town);
                    $province_id = $commonModel->getCity($province, 1);
                    $city_id = $commonModel->getCity($city, 2);
                    $town_id = $commonModel->getCity($town, 3);
                    $stmt_city = $this->db->prepare("update event set province=:province, city = :city,town=:town where id=:id ");
                    $array = array(
                        ':province' => $province_id,
                        ':city' => $city_id,
                        ':town' => $town_id,
                        ':id' => $v['id']
                    );
                    $stmt_city->execute($array);
                }
            }
        }
    }

    //处理地址联动数据
    public function setStageCityAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("select sid,lat,lng from business");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $addressInfo = Api::getCity($v['lat'], $v['lng']);
                $province = $addressInfo['result']['addressComponent']['province'] ? $addressInfo['result']['addressComponent']['province'] : '';
                $city = $addressInfo['result']['addressComponent']['city'] ? $addressInfo['result']['addressComponent']['city'] : '';
                $town = $addressInfo['result']['addressComponent']['district'] ? $addressInfo['result']['addressComponent']['district'] : '';
                if ($town && $city && $province) {
                    $stmt_province = $this->db->prepare("SELECT id FROM address WHERE REPLACE(NAME,'　','') =:province");
                    $array = array(
                        ':province' => $province
                    );
                    $stmt_province->execute($array);
                    $province_id = $stmt_province->fetch(PDO::FETCH_ASSOC);

                    $stmt_city = $this->db->prepare("SELECT id FROM address WHERE REPLACE(NAME,'　','') =:city");
                    $array = array(
                        ':city' => $city
                    );
                    $stmt_city->execute($array);
                    $city_id = $stmt_city->fetch(PDO::FETCH_ASSOC);

                    $stmt_town = $this->db->prepare("SELECT id FROM address WHERE REPLACE(NAME,'　','') =:town");
                    $array = array(
                        ':town' => $town
                    );
                    $stmt_town->execute($array);
                    $town_id = $stmt_town->fetch(PDO::FETCH_ASSOC);

                    $stmt_city = $this->db->prepare("update stage set province=:province, city = :city,town=:town where sid=:sid ");
                    $array = array(
                        ':province' => $province_id['id'],
                        ':city' => $city_id['id'],
                        ':town' => $town_id['id'],
                        ':sid' => $v['sid']
                    );
                    $stmt_city->execute($array);
                }
            }
        }
    }

    //处理地址联动数据
    public function setEventCityAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("select id,lat,lng from event");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $addressInfo = Api::getCity($v['lat'], $v['lng']);
                $province = $addressInfo['result']['addressComponent']['province'] ? $addressInfo['result']['addressComponent']['province'] : '';
                $city = $addressInfo['result']['addressComponent']['city'] ? $addressInfo['result']['addressComponent']['city'] : '';
                $town = $addressInfo['result']['addressComponent']['district'] ? $addressInfo['result']['addressComponent']['district'] : '';
                if ($town && $city && $province) {
                    $stmt_province = $this->db->prepare("SELECT id FROM address WHERE REPLACE(NAME,'　','') =:province");
                    $array = array(
                        ':province' => $province
                    );
                    $stmt_province->execute($array);
                    $province_id = $stmt_province->fetch(PDO::FETCH_ASSOC);

                    $stmt_city = $this->db->prepare("SELECT id FROM address WHERE REPLACE(NAME,'　','') =:city");
                    $array = array(
                        ':city' => $city
                    );
                    $stmt_city->execute($array);
                    $city_id = $stmt_city->fetch(PDO::FETCH_ASSOC);

                    $stmt_town = $this->db->prepare("SELECT id FROM address WHERE REPLACE(NAME,'　','') =:town");
                    $array = array(
                        ':town' => $town
                    );
                    $stmt_town->execute($array);
                    $town_id = $stmt_town->fetch(PDO::FETCH_ASSOC);

                    $stmt_city = $this->db->prepare("update event set province=:province, city = :city,town=:town where id=:id ");
                    $array = array(
                        ':province' => $province_id['id'],
                        ':city' => $city_id['id'],
                        ':town' => $town_id['id'],
                        ':id' => $v['id']
                    );
                    $stmt_city->execute($array);
                }
            }
        }
    }

    public function getStageAddressAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("select sid,lat,lng from business order by id desc limit 50");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $addressInfo = Api::getCity($v['lat'], $v['lng']);
                $result[$k]['address'] = $addressInfo['result']['formatted_address'];
            }
        }
        print_r("<pre>");
        print_r($result);
    }

    public function getEventAddressAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("select id,lat,lng from event order by id desc limit 50");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $addressInfo = Api::getCity($v['lat'], $v['lng']);
                $result[$k]['address'] = $addressInfo['result']['formatted_address'];
            }
        }
        print_r("<pre>");
        print_r($result);
    }

    //把服务信息放入队列，处理浏览数基数
    public function addEventVisitNumAction()
    {
        $id = (int)$this->getRequest()->get('id');
        $eventModel = new EventModel();
        $eventModel->addEventViewTime($id);
    }

    //查询下用户动态数据
    public function testDataAction()
    {
        $uid = 163846;
        $feedModel = new FeedModel();
        $feeds = $feedModel->getUserList($uid, 0, -1);
        print_r("<pre>");
        print_r($feeds);
    }

    //测试友盟推送
    public function testAction()
    {
        $uid = (int)$this->getRequest()->get('uid');
        $badge = 25;
        $content = '您增加了一位新粉丝';
        $tokenModel = new TokenModel();
        $info = $tokenModel->getDeviceTokensByUid($uid);
        print_r($info);
        if ($info) {
            foreach ($info as $v) {
                if ($v['origin'] == 3) {
                    $result = Send::sendIOSUnicast($v['device_tokens'], $content, $badge);
                    print_r($result);
                }
            }
        }
    }

    //将event_attr中的start_time end_time 迁移至event表
    public function setEventTimeAction()
    {
        $eventModel = new EventModel();
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("select id from event");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $start_time = $eventModel->getEventValueById($v['id'], 'start_time');
                $end_time = $eventModel->getEventValueById($v['id'], 'end_time');
                $partake_end_time = $eventModel->getEventValueById($v['id'], 'partake_end_time');
                if (!$partake_end_time) {
                    $partake_end_time = $end_time;
                }
                $stmt_time = $this->db->prepare("update event set start_time=:start_time, end_time = :end_time,partake_end_time=:partake_end_time where id=:id ");
                $array = array(
                    ':start_time' => $start_time,
                    ':end_time' => $end_time,
                    ':partake_end_time' => $partake_end_time,
                    ':id' => $v['id']
                );
                $stmt_time->execute($array);
            }
        }
    }

    //将event_attr中的price 迁移至event_price表
    public function setEventPriceAction()
    {
        $eventModel = new EventModel();
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("select id,max_partake from event where id not in (select eid from event_price)");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $price = $eventModel->getEventValueById($v['id'], 'price');
                if ($v) {
                    $stmt = $this->db->prepare("insert into event_price (eid,unit_price,mark,num,stock_num) values (:eid,:unit_price,:mark,:num,:stock_num)");
                    $array = array(
                        ':eid' => $v['id'],
                        ':unit_price' => $price ? $price : 0,
                        ':mark' => '',
                        ':num' => $v['max_partake'],
                        ':stock_num' => $v['max_partake'],
                    );
                    $stmt->execute($array);
                }
            }
        }
    }
    //支付宝线上测试退款
//    public function aliRefundOnlineAction(){
//        $time = time();
//        $biz_content  = json_encode(array(
//            'out_trade_no'=>'H14658973990001',
//            'refund_amount'=>'0.01',
//            'refund_reason'=>'正常退款'
//        ));
//        $str = 'app_id='.ALI_APPID.'&biz_content='.$biz_content.'&charset=utf-8&method=alipay.trade.refund&sign_type=RSA&timestamp='.date('Y-m-d H:i:s',$time).'&version=1.0';
//        $sign = Common::sign($str);
//        $rs = Common::rsaVerify($str,$sign);
//        if($rs=='no'){
//            Common::echoAjaxJson(6, "支付宝验签失败");
//        }
//        $data = array(
//            'app_id'=>ALI_APPID,
//            'biz_content'=>$biz_content,
//            'charset'=>'utf-8',
//            'method'=>'alipay.trade.refund',
//            'timestamp'=>date('Y-m-d H:i:s',$time),
//            'sign_type'=>'RSA',
//            'sign'=> $sign,
//            'version'=>'1.0'
//        );
//        $rs = Common::aliApi($data);
//        print_r($rs);
//        echo '***************';
//        $str = 'app_id='.ALI_APPID.'&biz_content='.$biz_content.'&charset=utf-8&method=alipay.trade.refund&sign_type=RSA&sign='.$sign.'&version=1.0&timestamp='.date('Y-m-d H:i:s',$time).'';
//        $rs1 = Common::aliApi($str);
//        print_r($rs1);
//
//    }

    /** 二进制流生成文件
     * $_POST 无法解释二进制流，需要用到 $GLOBALS['HTTP_RAW_POST_DATA'] 或 php://input
     * $GLOBALS['HTTP_RAW_POST_DATA'] 和 php://input 都不能用于 enctype=multipart/form-data
     * @param    String $file 要生成的文件路径
     * @return   boolean
     */
    public function binary_to_file()
    {
        $file = $this->getRequest()->get('file');
        $content = $GLOBALS['HTTP_RAW_POST_DATA'];  // 需要php.ini设置
        if (empty($content)) {
            $content = file_get_contents('php://input');    // 不需要php.ini设置，内存压力小
        }
        $ret = file_put_contents($file, $content, true);
        return $ret;
    }

    //服务驿站数据并入stage主表
    public function setStageAddressAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("select sid,lng,lat,address from business where sid in (select sid from stage where lng is null)");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "总数：" . count($result) . "<br>";
        if ($result) {
            foreach ($result as $v) {
                $stmt_time = $this->db->prepare("update stage set lng=:lng, lat = :lat,stage_address=:stage_address where sid=:sid ");
                $array = array(
                    ':lng' => $v['lng'],
                    ':lat' => $v['lat'],
                    ':stage_address' => $v['address'],
                    ':sid' => $v['sid']
                );
                $stmt_time->execute($array);
            }
        }
        echo "执行结束";
    }

    //服务插入场次表-付费
    public function setEventFieldsAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("select id,start_time,end_time,partake_end_time from event WHERE id NOT IN (SELECT eid FROM event_fields)");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $stmt = $this->db->prepare("insert into event_fields (eid,start_time,end_time,partake_end_time) values (:eid,:start_time,:end_time,:partake_end_time)");
                $array = array(
                    ':start_time' => $v['start_time'],
                    ':end_time' => $v['end_time'],
                    ':partake_end_time' => $v['partake_end_time'],
                    ':eid' => $v['id']
                );
                $stmt->execute($array);
                $id = $this->db->lastInsertId();
                $stmt_f_id = $this->db->prepare("update event_price set f_id=:id where eid=:eid ");
                $array_f_id = array(
                    ':id' => $id,
                    ':eid' => $v['id']
                );
                $stmt_f_id->execute($array_f_id);
                $stmt_order = $this->db->prepare("update event_orders set f_id=:id where eid=:eid ");
                $array_order = array(
                    ':id' => $id,
                    ':eid' => $v['id']
                );
                $stmt_order->execute($array_order);
            }
        }
        echo "执行结束" . count($result);
    }

    //报名表插入场次表
    public function setEventPartakeInfoAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("SELECT id,eid FROM event_fields WHERE eid IN (SELECT id FROM event WHERE price_type = 1)");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $v) {
            $stmt_f_id = $this->db->prepare("update event_partake_info set f_id=:id where eid=:eid ");
            $array_f_id = array(
                ':id' => $v['id'],
                ':eid' => $v['eid']
            );
            $stmt_f_id->execute($array_f_id);
        }
        echo "执行结束" . count($result);
    }

    //修改报名二维码
    public function setPartakeQrcodeAction()
    {
        $id = $this->getRequest()->get('id');
        $size = 100;
        $id = $id ? $id : 0;
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("SELECT * FROM event_partake_info where id >:id order by id asc limit :size");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $v) {
            PHPQRCode::getPartakePHPQRCode($v['f_id'], $v['uid']);
        }
        echo "执行结束" . count($result) . "id：" . isset($result[count($result) - 1]['id']) ? $result[count($result) - 1]['id'] : 0;
    }

    //修改订单二维码
    public function setOrdersQrcodeAction()
    {
        $this->db = DB::getInstance();
        $stmt_u = $this->db->prepare("update event_orders set qrcodeImg=''");
        $stmt_u->execute();
        $stmt = $this->db->prepare("SELECT * FROM event_orders where order_status = 2 ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $v) {
            PHPQRCode::getOrderPHPQRCode($v['id']);
        }
        echo "执行结束" . count($result);
    }

    //心境处理地址
    public function setMoodAddressAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("SELECT id,lat,lng,mood_address FROM mood where mood_address !='' and locate('·',mood_address)=0 order by id desc");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            $testModel = new TestModel();
            foreach ($result as $val) {
                $addressInfo = Api::getCity($val['lat'], $val['lng']);
                //print_r($addressInfo);print_r("<br>");
                if ($addressInfo['status'] == 0) {
                    $addressInfo = $addressInfo['result'];
                    if (isset($addressInfo['city']) && $addressInfo['city']) {
                        $address = str_replace('市', '', $addressInfo['city']) . " · " . $val['mood_address'];
                        if ($val['id'] && $address) {
                            $testModel->updateMoodAddress($val['id'], $address);
                            echo $address . "<br>";
                        }
                    }
                }
            }
        }
    }

    //修改心境地址
    public function updateAddressAction()
    {
        $testModel = new TestModel();
        $testModel->updateAddress();
    }

    //服务订单二维码处理
    public function setEventOrderQrcodeAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("SELECT id,p_number,qrcodeImg,is_check,add_time,num,update_time FROM event_orders where p_number is not null and qrcodeImg !='' ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $stmt = $this->db->prepare("insert into event_orders_qrcode (o_id,p_number,is_check,add_time,update_time) values (:o_id,:p_number,:is_check,:add_time,:update_time)");
                $array = array(
                    ':o_id' => $v['id'],
                    ':p_number' => $v['p_number'],
                    ':is_check' => $v['is_check'],
                    ':add_time' => $v['add_time'],
                    ':update_time' => $v['update_time']
                );
                $stmt->execute($array);
                $rs = $this->db->lastInsertId();
                PHPQRCode::getOrderPHPQRCodeNew($rs, $v['id']);
            }
        }
    }

    //服务订单验票数处理
    public function setEventOrderUnUseAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("SELECT id,add_time,num FROM event_orders where order_status = 2");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $qr_stmt = $this->db->prepare("SELECT id,is_check FROM event_orders_qrcode where o_id =:o_id");
                $array = array(
                    ':o_id' => $v['id'],
                );
                $qr_stmt->execute($array);
                $qr_result = $qr_stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt_order = $this->db->prepare("update event_orders set un_use=:un_use where id=:id ");
                $array_order = array(
                    ':id' => $v['id'],
                    ':un_use' => $qr_result ? count($qr_result) : $v['num'],
                );
                $stmt_order->execute($array_order);
                if ($qr_result) {
                    foreach ($qr_result as $qr) {
                        if ($qr['is_check'] == 1) {
                            $stmt_un_use = $this->db->prepare("update event_orders set un_use=un_use-1 where id=:id ");
                            $array_un_use = array(
                                ':id' => $v['id']
                            );
                            $stmt_un_use->execute($array_un_use);
                        }
                    }
                }
            }
        }
    }

    //脚本处理商品封面表到stage_goods_images
    public function setGoodsCoverAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("SELECT id,cover FROM stage_goods WHERE id NOT IN (SELECT goods_id FROM stage_goods_images)");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $stmt = $this->db->prepare("insert into stage_goods_images (goods_id,img,status) values (:goods_id,:img,:status)");
                $array = array(
                    ':goods_id' => $v['id'],
                    ':img' => $v['cover'],
                    ':status' => 0,
                );
                $stmt->execute($array);
                $this->db->lastInsertId();
            }
        }
    }

    public function aaAction()
    {
        $id = $this->getRequest()->get('id');
        $type = $this->getRequest()->get('type');
        $this->db = DB::getInstance();
        //$this->redis = CRedis::getInstance();
        $this->contentRedis = CRedis::getContentInstance();
        $redisKey = Common::getRedisKey($type) . $id;
//        $result = array('1','2','3','4');
//        $this->redis->set($redisKey,json_encode($result));
        $mood = $this->contentRedis->get($redisKey);
        // echo $redisKey;
//        if($mood) {
//            $mood = json_decode($mood,true);
//        }
//        print_r($mood);
        //$this->redis->del(Common::getRedisKey(10).'3029');
//        $id = 3030;
//        for($i=73;$i<=3075;$i++){
//            $redisKey = 'info:event:'.$i;
//            $this->redis->del($redisKey);
//            $i++;
//        }
        echo $redisKey;

        if ($mood) {
            $mood = json_decode($mood, true);
        }
        print_r($mood);
//        $now = time();
//        $modify_time = strtotime('2017-03-17 12:00:00');
//        $c_time = $modify_time - $now;
//        if($c_time>3600*24){
//            $day = round($c_time/(3600*24));
//            $hours = floor(($c_time-(3600*24))/3600);
//            echo '还剩'.$day.'天'.$hours.'小时自动确认收货';
//        }elseif($c_time<3600*24&&$c_time>3600){
//            $hours = floor($c_time/3600);
//            echo '还剩'.$hours.'小时自动确认收货';
//        }elseif($c_time<3600){
//            $minute = floor($c_time/60);
//            echo '还剩'.$minute.'分钟自动确认收货';
//        }
    }

    public function emailAction()
    {
        $user_name = 'sunzc00001@163.com';
        $nick_name = '老孙邮箱注册一';
        $pwd = '999999';
        if (!filter_var($user_name, FILTER_VALIDATE_EMAIL) || strlen($user_name) > 50) {
            Common::echoAjaxJson(2, '请输入正确的邮箱地址');
        }
        $userModel = new UserModel();
        $userBindModel = new UserBindModel();
        $ret = $userBindModel->isBindNameUsed($user_name);
        if ($ret) {
            Common::echoAjaxJson(3, "此邮箱已被绑定，无法注册");
        }
        if (!preg_match('/^[\x{4e00}-\x{9fa5}]{2,8}$/u', $nick_name)) {
            Common::echoAjaxJson(4, '昵称为2-8个中文');
        }
        $rs = $userModel->nickNameIsExist($nick_name);
        if ($rs > 0) {
            Common::echoAjaxJson(5, '此昵称太受欢迎，已有人抢了');
        }
        if (Common::badWord($nick_name)) {
            Common::echoAjaxJson(6, '昵称含有敏感词');
        }
        if (!preg_match('/^[^\s]{6,16}$/', $pwd)) {
            Common::echoAjaxJson(7, '密码为6-16位数字、字母或常用符号区分大小写');
        }
        $uid = $userModel->addUser($user_name, $nick_name, '', $pwd, 1, 1, 0);
        if (!$uid) {
            Common::echoAjaxJson(10, '注册失败');
        }
        Common::echoAjaxJson(1, '注册成功');
    }

    public function setOneMoodImgAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("SELECT id,COUNT(id) AS num FROM mood_images WHERE width IS NULL OR height IS NULL GROUP BY mood_id");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                if ($v['num'] == 1) {
                    $stmt_img = $this->db->prepare("update mood_images set width=200,height=200 where id=:id ");
                    $array_img = array(
                        ':id' => $v['id']
                    );
                    $stmt_img->execute($array_img);
                }
            }
        }
    }

    //小管家账号粉丝数
    public function setFansNumAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare(" SELECT uid FROM follow WHERE f_uid = '8931' AND STATUS=1 AND uid NOT IN(SELECT uid FROM `user`)");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $stmt_f = $this->db->prepare("update follow set status = 0 where uid =:uid and f_uid='8931'");
                $array_f = array(
                    ':uid' => $v['uid']
                );
                $stmt_f->execute($array_f);
            }
        }
    }

    public function sendAndroidUserAction()
    {
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("select user_name from user where uid in(select uid from user_info where origin = 4) and reg_type = 2");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $sms_content = '尊敬的才府家人您好：由于第三方授权系统更新，导致部分用户无法正常使用才府，请无法正常使用才府的用户重新下载更新，非常抱歉给您带来了不便，感谢您的支持。';
                Sms::send($v['user_name'], $sms_content);
            }
        }
    }

    public function wuliuAction()
    {
        print_r(Api::getLogistics('3957730057943', 'YUNDA'));
        //print_r(Api::getLogisticsCompany());
    }

    public function redisMoodAction()
    {

    }

    //处理帖子img_json
    public function imgJsonTopicAction()
    {
        $id = $this->getRequest()->get('last');
        $size = $this->getRequest()->get('size') ? $this->getRequest()->get('size') : 2000;
        $fields = $id ? 'and id > ' . $id . '' : '';
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
        $stmt = $this->db->prepare("select * from topic where 1=1 $fields order by id asc limit $size");
        $stmt->execute();
        $topic = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $testModel = new TestModel();
        foreach ($topic as $v) {
            $img_arr = Common::pregMatchImg($v['content']);
            if (isset($img_arr[3]) && $img_arr[3]) {
                $img_json = json_encode(array_slice($img_arr[3], 0, 3, true));
            } else {
                $img_json = '';
            }
            $testModel->fetchUpdateContent($v['id'], $img_json);
        }
        echo "执行结束id：" . isset($topic[count($topic) - 1]['id']) ? $topic[count($topic) - 1]['id'] : 0;
    }

    //处理活动img_json
    public function imgJsonEventAction()
    {

    }

    //删除活动redis数据
    public function delRedisEventAction()
    {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
        $this->contentRedis = CRedis::getContentInstance();
        $stmt = $this->db->prepare("select id from event ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $v) {
            $redisKey = Common::getRedisKey(10) . $v['id'];
            $this->redis->del($redisKey);
            $this->contentRedis->del($redisKey);
        }
    }

    //删除商品redis数据
    public function delRedisGoodsAction()
    {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
        $this->contentRedis = CRedis::getContentInstance();
        $stmt = $this->db->prepare("select id from stage_goods ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $v) {
            $redisKey = Common::getRedisKey(12) . $v['id'];
            $this->redis->del($redisKey);
            $this->contentRedis->del($redisKey);
        }
    }

    //删除帖子redis数据
    public function delRedisTopicAction()
    {
        $id = $this->getRequest()->get('last');
        $size = $this->getRequest()->get('size') ? $this->getRequest()->get('size') : 5000;
        $fields = $id ? 'and id > ' . $id . '' : '';
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
        $this->contentRedis = CRedis::getContentInstance();
        $stmt = $this->db->prepare("select id from topic where 1=1 $fields limit $size ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $v) {
            $redisKey = Common::getRedisKey(4) . $v['id'];
            $this->redis->del($redisKey);
            $this->contentRedis->del($redisKey);
        }
        echo "执行结束id：" . isset($result[count($result) - 1]['id']) ? $result[count($result) - 1]['id'] : 0;
    }

    //删除帖子redis数据
    public function delRedisMoodAction()
    {
        $id = $this->getRequest()->get('last');
        $size = $this->getRequest()->get('size') ? $this->getRequest()->get('size') : 5000;
        $fields = $id ? 'and id > ' . $id . '' : '';
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
        $this->contentRedis = CRedis::getContentInstance();
        $stmt = $this->db->prepare("select id from mood where 1=1 $fields limit $size ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $v) {
            $redisKey = Common::getRedisKey(1) . $v['id'];
            $this->redis->del($redisKey);
            $this->contentRedis->del($redisKey);
        }
        echo "执行结束id：" . isset($result[count($result) - 1]['id']) ? $result[count($result) - 1]['id'] : 0;
    }

    public function setUserAction()
    {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
        $stmt = $this->db->prepare("SELECT uid FROM `user` WHERE uid NOT IN (SELECT uid FROM `group` WHERE type = 1)");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $time = date("Y-m-d H:i:s");
        if ($result) {
            foreach ($result as $v) {
                $uid = $v['uid'];
                $stmt_group = $this->db->prepare("insert into `group` (uid,name,add_time,is_default,status,type) values ($uid,'默认分组','" . $time . "',1,1,1),
        ($uid,'特别关注','" . $time . "',0,1,1),($uid,'天使','" . $time . "',0,1,1),($uid,'文化人','" . $time . "',0,1,1),($uid,'家人','" . $time . "',0,1,1),
        ($uid,'同事','" . $time . "',0,1,1),($uid,'同学','" . $time . "',0,1,1),($uid,'朋友','" . $time . "',0,1,1)");
                $stmt_group->execute();
            }
        }
        $stmt_album = $this->db->prepare("SELECT uid FROM `user` WHERE uid NOT IN (SELECT uid FROM album WHERE TYPE !=0)");
        $stmt_album->execute();
        $album = $stmt_album->fetchAll(PDO::FETCH_ASSOC);
        if ($album) {
            foreach ($album as $v) {
                $a_uid = $v['uid'];
                $stmt_album_add = $this->db->prepare("insert into album(uid,name,type,is_public,status,add_time)values($a_uid,'头像相册',1,1,1,'" . $time . "'),($a_uid,'默认相册',2,1,1,'" . $time . "'),($a_uid,'心境相册',3,1,1,'" . $time . "')");
                $stmt_album_add->execute();
            }
        }
        $stmt_info = $this->db->prepare("SELECT uid FROM `user` WHERE uid NOT IN (SELECT uid FROM user_info)");
        $stmt_info->execute();
        $u_info = $stmt_info->fetchAll(PDO::FETCH_ASSOC);
        if ($u_info) {
            foreach ($u_info as $v) {
                $u_uid = $v['uid'];
                $stmt_user_info_add = $this->db->prepare("insert into user_info (uid) values (:uid)");
                $array_info = array(
                    ":uid" => $u_uid
                );
                $stmt_user_info_add->execute($array_info);
                $homeDressData = array(
                    'package' => array(
                        array('id' => 19, 'img' => 'home_package_20.jpg', 'flash' => '', 'color' => '#ecdfd0'),//2017新做套装1
                        array('id' => 22, 'img' => 'home_package_23.jpg', 'flash' => '', 'color' => '#b80411'),//2017新做套装2
                        array('id' => 15, 'img' => 'home_package_16.jpg', 'flash' => '', 'color' => '#cee1f0'),
                        array('id' => 16, 'img' => 'home_package_17.jpg', 'flash' => '', 'color' => '#121538'),
                        array('id' => 17, 'img' => 'home_package_18.jpg', 'flash' => '', 'color' => '#ece9da'),
                        array('id' => 18, 'img' => 'home_package_19.jpg', 'flash' => '', 'color' => '#f6faf9'),
                        //array('id'=>20,'img'=>'home_package_21.jpg','flash'=>'','color'=>'#efe1c6'),
                        array('id' => 21, 'img' => 'home_package_22.jpg', 'flash' => '', 'color' => '#fbdcb0'),
                        array('id' => 0, 'img' => 'home_package_1.jpg', 'flash' => '', 'color' => '#aad2de'),
                        array('id' => 1, 'img' => 'home_package_2.jpg', 'flash' => '', 'color' => '#88bcd2'),
                        array('id' => 2, 'img' => 'home_package_3.jpg', 'flash' => '', 'color' => '#206574'),
                        array('id' => 3, 'img' => 'home_package_4.jpg', 'flash' => '', 'color' => '#cae3e7'),
                        array('id' => 4, 'img' => 'home_package_5.jpg', 'flash' => '', 'color' => '#7fa9a5'),
                        array('id' => 5, 'img' => 'home_package_6.jpg', 'flash' => '', 'color' => '#c5e8e1'),
                        array('id' => 6, 'img' => 'home_package_7.jpg', 'flash' => '', 'color' => '#0a2747'),
                        array('id' => 7, 'img' => 'home_package_8.jpg', 'flash' => '', 'color' => '#add9e6'),
                        array('id' => 8, 'img' => 'home_package_9.jpg', 'flash' => '', 'color' => '#c4e5ee'),
                        array('id' => 9, 'img' => 'home_package_10.jpg', 'flash' => '', 'color' => '#ece5d3'),
                        array('id' => 10, 'img' => 'home_package_11.jpg', 'flash' => '', 'color' => '#c4dae8'),
                        array('id' => 11, 'img' => 'home_package_12.jpg', 'flash' => '', 'color' => '#e6e1cd'),
                        array('id' => 12, 'img' => 'home_package_13.jpg', 'flash' => '', 'color' => '#aedbf0'),
                        array('id' => 13, 'img' => 'home_package_14.jpg', 'flash' => '', 'color' => '#4f85cf'),
                        array('id' => 14, 'img' => 'home_package_15.jpg', 'flash' => '', 'color' => '#052653'),

                    ),
                    'cover' => array(
                        array('id' => 9, 'img' => 'home_cover_10.jpg'),//2017新做封面1
                        array('id' => 10, 'img' => 'home_cover_11.jpg'),//2017新做封面2
                        array('id' => 11, 'img' => 'home_cover_12.jpg'),//2017新做封面3
                        array('id' => 14, 'img' => 'home_cover_15.jpg'),//2016新做封面4
                        array('id' => 12, 'img' => 'home_cover_13.jpg'),
                        array('id' => 13, 'img' => 'home_cover_14.jpg'),
                        array('id' => 0, 'img' => 'home_cover_1.jpg'),
                        array('id' => 1, 'img' => 'home_cover_2.jpg'),
                        array('id' => 2, 'img' => 'home_cover_3.jpg'),
                        array('id' => 3, 'img' => 'home_cover_4.jpg'),
                        array('id' => 4, 'img' => 'home_cover_5.jpg'),
                        array('id' => 5, 'img' => 'home_cover_6.jpg'),
                        array('id' => 6, 'img' => 'home_cover_7.jpg'),
                        array('id' => 7, 'img' => 'home_cover_8.jpg'),
                        array('id' => 8, 'img' => 'home_cover_9.jpg'),

                    ),
                    'flash' => array(
                        array('id' => 12, 'img' => 'home_flash_13.jpg', 'flash' => 'home_flash_13.swf'),
                        array('id' => 13, 'img' => 'home_flash_14.jpg', 'flash' => 'home_flash_14.swf'),
                        array('id' => 14, 'img' => 'home_flash_15.jpg', 'flash' => 'home_flash_15.swf'),
                        array('id' => 1, 'img' => 'home_flash_2.jpg', 'flash' => 'home_flash_2.swf'),
                        array('id' => 0, 'img' => 'home_flash_1.jpg', 'flash' => 'home_flash_1.swf'),
                        array('id' => 2, 'img' => 'home_flash_3.jpg', 'flash' => 'home_flash_3.swf'),
                        array('id' => 3, 'img' => 'home_flash_4.jpg', 'flash' => 'home_flash_4.swf'),
                        array('id' => 4, 'img' => 'home_flash_5.jpg', 'flash' => 'home_flash_5.swf'),
                        array('id' => 5, 'img' => 'home_flash_6.jpg', 'flash' => 'home_flash_6.swf'),
                        array('id' => 6, 'img' => 'home_flash_7.jpg', 'flash' => 'home_flash_7.swf'),
                        array('id' => 7, 'img' => 'home_flash_8.jpg', 'flash' => 'home_flash_8.swf'),
                        array('id' => 8, 'img' => 'home_flash_9.jpg', 'flash' => 'home_flash_9.swf'),
                        array('id' => 9, 'img' => 'home_flash_10.jpg', 'flash' => 'home_flash_10.swf'),
                        array('id' => 10, 'img' => 'home_flash_11.jpg', 'flash' => 'home_flash_11.swf'),
                        array('id' => 11, 'img' => 'home_flash_12.jpg', 'flash' => 'home_flash_12.swf'),
                    ));
                $dress_key = array_rand($homeDressData['package']);
                $stmt_user_info = $this->db->prepare("update user_info set home_background = :home_background,home_flash = :home_flash,home_color = :home_color where uid = :uid");
                $array_user_info = array(
                    ':home_background' => $homeDressData['package'][$dress_key]['img'],
                    ':home_flash' => $homeDressData['package'][$dress_key]['flash'],
                    ':home_color' => $homeDressData['package'][$dress_key]['color'],
                    ':uid' => $u_uid,
                );
                $stmt_user_info->execute($array_user_info);
            }
        }
    }

    public function setUserQrcodeAction()
    {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
        $stmt = $this->db->prepare("SELECT uid FROM `user` WHERE avatar LIKE '%http%'");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $stmt_f = $this->db->prepare("update `user` set qrcode_img = '' where uid =:uid");
                $array_f = array(
                    ':uid' => $v['uid']
                );
                $stmt_f->execute($array_f);
                PHPQRCode::getUserPHPQRCode($v['uid'], false);
            }
        }
    }

    public function wxOrderAction()
    {
        $id = $this->getRequest()->get('id');
        $this->db = DB::getInstance();
        $stmt = $this->db->prepare("update event_orders set order_status=5,pay_time= NULL where id =:id");
        $stmt->bindValue(':id', $id, PDO :: PARAM_INT);
        $stmt->execute();
        echo $stmt->rowCount();
    }

    public function getTidAction()
    {
        $key = "init:topic:img";
        $i = 60;
        while ($i) {
            $this->redis = CRedis::getInstance();
            $id = $this->redis->lPop($key);
            print_r($id);
            $i--;
        }
    }

    public function timeAction()
    {
        echo time();
        echo '<br/>';
        echo date('Y-m-d H:i:s', 1491326362);
    }

    //测试推广联盟搜索
    public function searchAction()
    {
        $searchModel = new SearchModel();
        $res = $searchModel->getLeague(2, 0, 0, '做', 0, 10, 3183);
        print_r("<pre>");
        print_r($res);
    }

    public function test1Action()
    {
        echo PHP_VERSION;
    }
}
