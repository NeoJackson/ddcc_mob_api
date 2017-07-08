<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 16-7-29
 * Time: 上午11:21
 */
class StagegoodsModel
{
    private $db;
    private $redis;

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
        $this->contentRedis = CRedis::getContentInstance();
    }

    //发布
    public function  add($data)
    {
        $stageModel = new StageModel();
        $stage_num = $stageModel->stageIsExist($data['sid']);
        if ($stage_num == 0) {
            return -1;
        }
        $join_info = $stageModel->getJoinStage($data['sid'], $data['uid']);
        if (!$join_info) {
            return -2;
        }
        $stmt = $this->db->prepare("insert into stage_goods (name,sid,uid,origin,cover,intro,cate_id,is_img,type,price,score,num,stock_num,start_time,end_time,province,city) values (:name,:sid,:uid,:origin,:cover,:intro,:cate_id,:is_img,:type,:price,:score,:num,:stock_num,:start_time,:end_time,:province,:city)");
        $array = array(
            ':name' => $data['name'],
            ':sid' => $data['sid'],
            ':uid' => $data['uid'],
            ':origin' => $data['origin'],
            ':cover' => $data['cover'],
            ':intro' => $data['intro'],
            ':cate_id' => $data['cate_id'],
            ':is_img' => $data['is_img'],
            ':type' => $data['type'],
            ':price' => $data['price'],
            ':score' => $data['score'],
            ':num' => $data['num'],
            ':stock_num' => $data['num'],
            ':start_time' => $data['start_time'],
            ':end_time' => $data['end_time'],
            ':province' => $data['province'],
            ':city' => $data['city']
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        if (!$id) {
            Common::echoAjaxJson(600, "发的太快，休息一下吧");
        }
        if ($data['is_img']) {
            $this->addImages($id, $data['imgArr']);
        }
        Common::http(OPEN_DOMAIN . "/common/addFeed", array('scope' => 1, 'uid' => $data['uid'], 'type' => 'stage_goods', "id" => $id, "time" => time()), "POST");
        $this->updateGoodsNum($data['sid'], 1);//更新驿站商品数
        $this->getGoodsRedisById($id);
        return $id;
    }

    //添加图片
    public function addImages($id, $imgArr)
    {
        foreach ($imgArr as $v) {
            if ($v) {
                $stmt = $this->db->prepare("insert into stage_goods_images (goods_id,img) values (:goods_id,:img)");
                $array = array(
                    ':goods_id' => $id,
                    ':img' => $v,
                );
                $stmt->execute($array);
            }
        }
    }

    //修改
    public function update($data)
    {
        $stmt = $this->db->prepare("update stage_goods set name=:name,cover=:cover,intro=:intro,is_img=:is_img,type=:type,price=:price,score=:score,num=:num,stock_num=:stock_num,is_recommend=:is_recommend,start_time=:start_time,end_time=:end_time,update_time=:update_time,cate_id=:cate_id,province=:province,city=:city where id=:id");
        $array = array(
            ':name' => $data['name'],
            ':cover' => $data['cover'],
            ':intro' => $data['intro'],
            ':is_img' => $data['is_img'],
            ':type' => $data['type'],
            ':price' => $data['price'],
            ':score' => $data['score'],
            ':num' => $data['num'],
            ':stock_num' => $data['stock_num'],
            ':is_recommend' => $data['is_recommend'],
            ':start_time' => $data['start_time'],
            ':end_time' => $data['end_time'],
            ':update_time' => date('Y-m-d H:i:s'),
            ':cate_id' => $data['cate_id'],
            ':province' => $data['province'],
            ':city' => $data['city'],
            ':id' => $data['id']
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        $this->updateImages($data['id'], $data['imgArr']);
        //删除原缓存
        $this->contentRedis->del(Common::getRedisKey(12) . $data['id']);
        return 1;
    }

    //修改图片
    public function updateImages($id, $imgArr)
    {
        $stmt = $this->db->prepare("UPDATE stage_goods_images SET status = 4,update_time=:update_time WHERE goods_id=:goods_id");
        $array = array(
            ':update_time' => date('Y-m-d H:i:s'),
            ':goods_id' => $id
        );
        $stmt->execute($array);
        if ($imgArr) {
            $this->addImages($id, $imgArr);
        }
    }

    //3.7.1添加快递单号
    public function addCourierImg($img, $order_id)
    {
        $time = date('Y-m-d H:i:s');
        $modify_time = date('Y-m-d H:i:s', strtotime('+15 day'));
        $stmt = $this->db->prepare("update stage_goods_orders set courier_img=:courier_img,send_time=:send_time,modify_time=:modify_time,update_time=:update_time where order_id=:order_id");
        $array = array(
            ':courier_img' => $img,
            ':send_time' => $time,
            ':modify_time' => $modify_time,
            ':update_time' => $time,
            ':order_id' => $order_id
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        $this->updateOrderStatus(6, $order_id);
        return 1;
    }

    //3.7.2添加快递单号
    public function addLogisticsNumber($logistics_number, $logistics_company, $logistics_type, $logistics_tel, $order_id)
    {
        $time = date('Y-m-d H:i:s');
        $modify_time = date('Y-m-d H:i:s', strtotime('+15 day'));
        $stmt = $this->db->prepare("update stage_goods_orders set logistics_number=:logistics_number,logistics_company=:logistics_company,logistics_type=:logistics_type,logistics_tel=:logistics_tel,send_time=:send_time,modify_time=:modify_time,update_time=:update_time where order_id=:order_id");
        $array = array(
            ':logistics_number' => $logistics_number,
            ':logistics_company' => $logistics_company,
            ':logistics_type' => $logistics_type,
            ':logistics_tel' => $logistics_tel,
            ':send_time' => $time,
            ':modify_time' => $modify_time,
            ':update_time' => $time,
            ':order_id' => $order_id
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        $this->updateOrderStatus(6, $order_id);
        return 1;
    }

    //3.7.1修改快递单号
    public function updateCourierImg($img, $order_id)
    {
        $time = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("update stage_goods_orders set courier_img=:courier_img,update_time=:update_time where order_id=:order_id");
        $array = array(
            ':courier_img' => $img,
            ':update_time' => $time,
            ':order_id' => $order_id
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        return 1;
    }

    //3.7.2修改快递单号
    public function updateLogisticsNumber($logistics_number, $logistics_company, $logistics_type, $logistics_tel, $order_id)
    {
        $time = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("update stage_goods_orders set logistics_number=:logistics_number,logistics_company=:logistics_company,logistics_type=:logistics_type,logistics_tel=:logistics_tel,update_time=:update_time where order_id=:order_id");
        $array = array(
            ':logistics_number' => $logistics_number,
            ':logistics_company' => $logistics_company,
            ':logistics_type' => $logistics_type,
            ':logistics_tel' => $logistics_tel,
            ':update_time' => $time,
            ':order_id' => $order_id
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        return 1;
    }

    //更新驿站商品数
    public function updateGoodsNum($sid, $num)
    {
        $time = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("update stage set goods_num = goods_num +$num , last_topic_time = :last_topic_time,update_time=:update_time where sid=:sid");
        $array = array(
            ':last_topic_time' => $time,
            ':update_time' => $time,
            ':sid' => $sid
        );
        $stmt->execute($array);
    }

    //获取商品的上下架状态
    public function getStartType($goods_id)
    {
        $stmt = $this->db->prepare("select start_time from stage_goods where id=:id and status < 2");
        $array = array(
            ':id' => $goods_id,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return -1;
        }
        $time = date('Y-m-d H:i:s');
        if ($result['start_time'] > $time) {
            return 1;//未上架
        }
        return 2;//已上架
    }

    //获取驿站商品推荐数
    public function getRecommendNum($sid)
    {
        $stmt = $this->db->prepare("select count(*) as num from stage_goods where sid=:sid and status < 2 and is_recommend =1");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //获取驿站商品推荐状态
    public function getIsRecommend($goods_id)
    {
        $stmt = $this->db->prepare("select is_recommend from stage_goods where id=:id ");
        $array = array(
            ':id' => $goods_id,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['is_recommend'];
    }

    //根据id 查找总数和库存数
    public function getStockNum($goods_id)
    {
        $stmt = $this->db->prepare("SELECT num,stock_num FROM stage_goods WHERE id =:id ");
        $array = array(
            ':id' => $goods_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //获取商品信息
    public function getInfo($goods_id)
    {
        $result = $this->getGoodsRedisById($goods_id);
        if (!$result || $result['status'] > 1) {
            return -1;
        }
        $result['img'] = array();
        if ($result['is_img'] == 1) {
            $result['img'] = $this->getImages($goods_id);
        }
        $time = date("Y-m-d H:i:s");
        if ($result['start_time'] > $time && !$result['reason']) {
            $result['start_type'] = 1;
        } elseif ($result['start_time'] <= $time && $result['end_time'] > $time) {
            $result['start_type'] = 2;
        } elseif ($result['end_time'] <= $time && !$result['reason']) {
            $result['start_type'] = 3;
        } else {
            $result['start_type'] = 3;
        }
        if (in_array($result['start_type'], array(3, 4)) && $result['is_recommend'] == 1) {
            $result['is_recommend'] = 0;
            $this->updateRecommend($goods_id);
        }
        $result['cate_id'] = $result['cate_id'] ? $result['cate_id'] : '';
        $result['province'] = $result['province'] ? $result['province'] : '';
        $result['city'] = $result['city'] ? $result['city'] : '';
        $addressModel = new AddressModel();
        $province_name = $result['province'] ? $addressModel->getNameById($result['province']) : '';
        $city_name = $result['city'] ? $addressModel->getNameById($result['city']) : '';
        $result['address_name'] = $province_name . ' ' . $city_name;
        $type_name = $this->getCateInfo($result['cate_id']);
        $result['type_name'] = $type_name['name'] ? $type_name['name'] : '';
        return $result;
    }

    public function getCateInfo($cate_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM stage_goods_cate WHERE id =:cate_id");
        $array = array(
            ':cate_id' => $cate_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //获取图片
    public function getImages($goods_id)
    {
        $stmt = $this->db->prepare("SELECT id,img FROM stage_goods_images WHERE goods_id =:goods_id and status < 2 ");
        $array = array(
            ':goods_id' => $goods_id
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //获取驿站推荐商品
    public function getReCommendGoods($sid, $token = '', $version)
    {
        $time = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("SELECT id,name,cover,type FROM stage_goods WHERE sid =:sid and status < 2  and end_time > '" . $time . "' and is_recommend = 1 ");
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['cover'] = IMG_DOMAIN . $v['cover'];
                $result[$k]['url'] = $token ? I_DOMAIN . '/g/' . $v['id'] . '?token=' . $token . '&version=' . $version : I_DOMAIN . '/g/' . $v['id'] . '?version=' . $version;

            }
        }
        return $result;
    }

    //发现页驿站商品数据
    public function getPushGoods($size, $version, $token = '')
    {
        $time = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("SELECT goods_id FROM stage_goods_push WHERE goods_id in (select id from stage_goods where status < 2  and end_time > '" . $time . "') and status =1 order by sort desc,add_time desc limit :size");
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $list = array();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $list[] = $this->getInfoByFindView($v['goods_id'], $version, $token);
            }
        }
        return $list;
    }

    //发现页驿站商品信息
    public function getInfoByFindView($goods_id, $version, $token = '')
    {
        $stmt = $this->db->prepare("SELECT id,name,cover,sid FROM stage_goods WHERE id =:id ");
        $array = array(
            ':id' => $goods_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stageModel = new StageModel();
        $stageInfo = $stageModel->getBasicStageBySid($result['sid']);
        $result['stage_name'] = $stageInfo['name'];
        $result['url'] = $token ? I_DOMAIN . '/g/' . $result['id'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/g/' . $result['id'] . '?version=' . $version . '';
        return $result;
    }

    //发现精品推荐加载数据
    public function goodsRecommendMore($page, $size, $type = 0, $version, $token = '', $push_type = '')
    {
        $start = ($page - 1) * $size;
        $time = date('Y-m-d H:i:s');
        if ($type == 0) {
            $fields = '';
        } else {
            $fields = 'and type = ' . $type . '';
        }
        if (!$push_type) {
            $push_fields = 'type=1 and';
        } else {
            $push_fields = 'type=' . $push_type . ' and';
        }
        $stmt = $this->db->prepare("SELECT goods_id as id,name FROM stage_goods_push WHERE $push_fields goods_id IN (SELECT id FROM stage_goods WHERE STATUS < 2 AND end_time > '" . $time . "' $fields ) AND STATUS = 1 ORDER BY sort asc LIMIT :start,:size");
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stageModel = new StageModel();
        $stagegoodsModel = new StagegoodsModel();
        if ($result) {
            foreach ($result as $k => $v) {
                $stmt_info = $this->db->prepare("SELECT sid,name,cover,type,price,score FROM stage_goods WHERE id =:id ");
                $array = array(
                    ':id' => $v['id']
                );
                $stmt_info->execute($array);
                $result_info = $stmt_info->fetch(PDO::FETCH_ASSOC);
                $result[$k]['sid'] = $result_info['sid'];
                $result[$k]['name'] = $v['name'] ? $v['name'] : $result_info['name'];
                $result[$k]['cover'] = $result_info['cover'];
                $result[$k]['type'] = $result_info['type'];
                $result[$k]['price'] = $result_info['price'];
                $result[$k]['score'] = $result_info['score'];
                $stageInfo = $stageModel->getBasicStageBySid($result_info['sid']);
                $result[$k]['stage_name'] = $stageInfo['name'];
                $result[$k]['url'] = $token ? I_DOMAIN . '/g/' . $v['id'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/g/' . $v['id'] . '?version=' . $version . '';
                $result[$k]['sell_num'] = $stagegoodsModel->getSellNum($v['id']);
            }
        }
        return $result;
    }

    public function getGoodsListBySid($sid, $start_type = 0, $page, $size, $version, $token)
    {
        $start = ($page - 1) * $size;
        $time = date('Y-m-d H:i:s');
        if ($start_type == 0) {
            $fields = " order by id desc";
        } elseif ($start_type == 1) {
            $fields = "and start_time > '" . $time . "' and stock_num >0 order by id desc";
        } elseif ($start_type == 2) {
            $fields = "and start_time <= '" . $time . "' and end_time > '" . $time . "' and stock_num >0 order by id desc";
        } elseif ($start_type == 3) {
            $fields = "and end_time <= '" . $time . "' or stock_num <1 and sid=:sid and status< 2 and reason is null order by end_time desc";
        }
        $stmt = $this->db->prepare("SELECT id,sid,name,cover,type,price,score,reason,num,stock_num,start_time,end_time,is_top,is_good FROM stage_goods WHERE status<2 and sid=:sid and reason is null $fields  limit :start,:size");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['reason'] = $v['reason'] ? $v['reason'] : "";
                $result[$k]['residue_num'] = $this->getSellNum($v['id']);
                $result[$k]['url'] = I_DOMAIN . '/g/' . $v['id'] . '?token=' . $token . '&version=' . $version;
            }
        }
        return $result;
    }

    public function getSellNum($id)
    {
        $stmt = $this->db->prepare("SELECT SUM(num) AS totals FROM stage_goods_orders WHERE goods_id =:goods_id AND order_status in(1,2,6,7) and status < 2 ");
        $array = array(
            ':goods_id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['totals'] ? $result['totals'] : 0;
    }

    public function delOrEnd($id, $type)
    {
        $time = date('Y-m-d H:i:s');
        if ($type == 'del') {
            $num = $this->getIsOver($id);
            if ($num > 0) {
                return -1;
            } else {
                $stmt = $this->db->prepare("update stage_goods set status =4,is_top=0,is_good=0,is_notice=0,update_time= '" . $time . "' where id=:id");
            }
        }
        if ($type == 'end') {
            $stmt = $this->db->prepare("update stage_goods set end_time = '" . $time . "',update_time= '" . $time . "' where id=:id");
        }
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        $this->contentRedis->del(Common::getRedisKey(12) . $id);
        return 1;
    }

    //查询某个商品是否有未完成订单
    public function getIsOver($goods_id)
    {
        $stmt = $this->db->prepare("SELECT count(*) as num FROM stage_goods_orders where goods_id =:goods_id and status < 2 and order_status in(1,2,6)");
        $array = array(
            ':goods_id' => $goods_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //获取上个订单号
    public function getLastOrderId()
    {
        $stmt = $this->db->prepare("SELECT order_id FROM stage_goods_orders ORDER BY add_time DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['order_id'];
    }

    //添加订单
    public function addOrder($data){
        $sp_id = '';
        $commission = $service_charge = $fact_totals = 0;
        if ($data['sp']) {
            $sp_str = base64_decode(base64_decode($data['sp']));
            $sp_arr = explode('-', $sp_str);
            $sp_id = $sp_arr[1];
            $stageModel = new StageModel();
            $sp_info = $stageModel->getCommissionRateById($sp_id);
            if ($sp_info && $sp_info['uid'] != $data['uid']) {
                $commission = round($data['price_totals'] * $sp_info['commission_rate'], 2);
            } else {
                $sp_id = '';
            }
        }
        if($data['price_totals']){
            $service_charge = round($data['price_totals']*0.03,2);
            $fact_totals = $data['price_totals'] -$commission-$service_charge;
        }
        $stmt = $this->db->prepare("insert into stage_goods_orders (goods_id,sid,order_id,uid,num,price_totals,score_totals,address_id,message,sp_id,commission,service_charge,fact_totals) values (:goods_id,:sid,:order_id,:uid,:num,:price_totals,:score_totals,:address_id,:message,:sp_id,:commission,:service_charge,:fact_totals)");
        $array = array(
            ':goods_id' => $data['goods_id'],
            ':sid' => $data['sid'],
            ':order_id' => $data['order_id'],
            ':uid' => $data['uid'],
            ':num' => $data['num'],
            ':price_totals' => $data['price_totals'],
            ':score_totals' => $data['score_totals'],
            ':address_id' => $data['address_id'],
            ':message' => $data['message'],
            ':sp_id' => $sp_id,
            ':commission' => $commission,
            ':service_charge'=>$service_charge,
            ':fact_totals'=>$fact_totals
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        if (!$id) {
            Common::echoAjaxJson(12, "添加订单失败");
        }
        if(time()>strtotime(SERIAL_TIME)){
            if ($id && $data['sp'] && $commission) {
                $userModel = new UserModel();
                //分享推广入用户流水
                $userModel->addUserSerial($id,4,$sp_info['uid']);
                //用户未到账收益添加
                $userModel->addUnUseMoneyByUid($commission, $sp_info['uid']);
            }
        }
        //修改库存
        $this->setStockNum($data['goods_id'], $data['num'], '-');
        return $id;
    }

    //修改订单状态
    public function updateOrderStatus($order_status, $order_id, $seller_id = '')
    {
        $stmt = $this->db->prepare("update stage_goods_orders set order_status=:order_status,seller_id=:seller_id,update_time=:update_time where order_id=:order_id");
        $array = array(
            ':order_status' => $order_status,
            ':seller_id' => $seller_id,
            ':update_time' => date('Y-m-d H:i:s'),
            ':order_id' => $order_id
        );
        $stmt->execute($array);
//        $count = $stmt->rowCount();
//        if ($count < 1) {
//            return 0;
//        }
//        return 1;

    }

    //修改订单支付时间
    public function updatePayTime($order_id, $pay_type = 4)
    {
        $stmt = $this->db->prepare("update stage_goods_orders set pay_time=:pay_time,pay_type=:pay_type where order_id=:order_id");
        $array = array(
            ':pay_time' => date('Y-m-d H:i:s'),
            ':pay_type' => $pay_type,
            ':order_id' => $order_id
        );
        $stmt->execute($array);
    }

    //修改库存
    public function setStockNum($goods_id, $num, $type)
    {
        $stmt = $this->db->prepare("update stage_goods set stock_num=stock_num $type $num,update_time=:update_time where id=:id");
        $array = array(
            ':update_time' => date('Y-m-d H:i:s'),
            ':id' => $goods_id
        );
        $stmt->execute($array);
    }

    public function orderInfoByOrderId($order_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM stage_goods_orders WHERE order_id =:order_id and status < 2 ");
        $array = array(
            ':order_id' => $order_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return false;
        }
        return $result;
    }

    //脚本跑失效订单并改变状态
    public function initOrderStatus()
    {
        $time = time();
        //查询所有未支付订单
        $stmt = $this->db->prepare("SELECT * FROM stage_goods_orders WHERE order_status=1 AND STATUS < 2 ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                //判断时间 15分钟
                if ((strtotime($v['add_time']) + 900) < $time) {
                    $this->updateOrderStatus(5, $v['order_id']);
                    $this->setStockNum($v['goods_id'], $v['num'], '+');
                    if ($v['score_totals']) {
                        $scoreModel = new ScoreModel();
                        $scoreModel->add($v['uid'], 0, 'stagegoodssb', $v['goods_id'], $v['score_totals'], 1);
                    }
                    if($time>strtotime(SERIAL_TIME)){
                        $userModel = new UserModel();
                        if($v['sp_id']){
                            $sp_info = $userModel->getSpInfoById($v['sp_id']);
                            if($sp_info){
                                //用户未到账减去
                                $userModel->addUnUseMoneyByUid($v['commission'],$sp_info['uid'],'-');
                            }
                        }
                    }
                }
            }
        }
    }

    //获取订单表中某种状态的所有订单
    public function getOrderList($pay_type = '', $order_status)
    {
        $fields = $pay_type ? 'and pay_type=' . $pay_type . '' : '';
        $stmt = $this->db->prepare("SELECT order_id FROM stage_goods_orders WHERE order_status =:order_status $fields and status< 2");
        $array = array(
            ':order_status' => $order_status
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //修改订单支付状态
    public function updateOrder($order_id, $type, $seller_id, $order_status, $pay_time)
    {
        $stmt = $this->db->prepare("update stage_goods_orders set seller_id=:seller_id,pay_type=:type,order_status=:order_status,pay_time=:pay_time where order_id=:order_id ");
        $array = array(
            ':seller_id' => $seller_id,
            ':type' => $type,
            ':order_status' => $order_status,
            ':pay_time' => $pay_time,
            ':order_id' => $order_id
        );
        $stmt->execute($array);
        if(time()>strtotime(SERIAL_TIME)&&$order_status==2){
            $userModel = new UserModel();
            $order_info = $this->getOrderInfoByOrderId($order_id);
            $goods_info = $this->getGoodsRedisById($order_info['goods_id']);
            //商家入明细
            $userModel->addUserSerial($order_info['id'],2,$goods_info['uid']);
            //商家用户未到帐增加
            $userModel->addUnUseMoneyByUid($order_info['fact_totals'],$goods_info['uid']);
        }
    }

    public function getOrderInfoByOrderId($order_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM stage_goods_orders WHERE order_id =:order_id ");
        $array = array(
            ':order_id' => $order_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //获取订单信息
    public function getOrderInfo($id, $token, $version)
    {
        $stmt = $this->db->prepare("SELECT id,goods_id,uid,order_id,num,address_id,price_totals,score_totals,message,pay_type,order_status,courier_img,pay_time,send_time,add_time,is_defer,logistics_number,logistics_company,logistics_type,logistics_tel,modify_time,sp_id,commission FROM stage_goods_orders WHERE id =:id and status< 2");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $result['pay_type'] = $result['pay_type'] ? $result['pay_type'] : '';
            $result['courier_img'] = $result['courier_img'] ? $result['courier_img'] : '';
            $result['pay_time'] = $result['pay_time'] ? $result['pay_time'] : '';
            $result['send_time'] = $result['send_time'] ? $result['send_time'] : '';
            $addressModel = new AddressModel();
            $userModel = new UserModel();
            $stageModel = new StageModel();
            $result['shipping'] = $addressModel->getShippingById($result['uid'], $result['address_id']);
            $goodsInfo = $this->getGoodsRedisById($result['goods_id']);
            $stageInfo = $stageModel->getBasicStageBySid($goodsInfo['sid']);
            $result['buy_uid'] = $result['uid'];
            $result['sell_uid'] = $goodsInfo['uid'];
            $result['custom_tel'] = '13012888193';
            $result['sell_tel'] = $stageInfo['businessInfo']['tel'];
            $result['buy_tel'] = $result['shipping'][0]['phone'];
            $result['sid'] = $stageInfo['sid'];
            $result['stage_name'] = $stageInfo['name'];
            $result['goods_info']['id'] = $goodsInfo['id'];
            $result['goods_info']['name'] = $goodsInfo['name'];
            $result['goods_info']['cover'] = IMG_DOMAIN . $goodsInfo['cover'];
            $result['goods_info']['type'] = $goodsInfo['type'];
            $result['goods_info']['price'] = $goodsInfo['price'];
            $result['goods_info']['score'] = $goodsInfo['score'];
            $result['goods_info']['sid'] = $goodsInfo['sid'];
            $result['goods_info']['url'] = I_DOMAIN . '/g/' . $goodsInfo['id'] . '?token=' . $token . '&version=' . $version;
            $userInfo = $userModel->getUserData($result['uid']);
            $result['user_info']['uid'] = $userInfo['uid'];
            $result['user_info']['nick_name'] = $userInfo['nick_name'];
            if ($result['logistics_number']) {
                $result['logistics_type'] = $result['logistics_type'] ? $result['logistics_type'] : "auto";
                $logistics_info = Api::getLogistics($result['logistics_number'], $result['logistics_type']);
                $result['logistics_info'] = isset($logistics_info['result']['list']) ? $logistics_info['result']['list'][0] : (object)array();
            } else {
                $result['logistics_info'] = (object)array();
            }
            //Receiving countdown收货倒计时
            if ($result['order_status'] == 6) {
                $now = time();
                $modify_time = strtotime($result['modify_time']);
                $c_time = $modify_time - $now;
                if ($c_time > 3600 * 24) {
                    $day = round($c_time / (3600 * 24));
                    $hours = floor(($c_time - (3600 * 24 * $day)) / 3600);
                    $result['receiving_countdown'] = '还剩' . $day . '天' . $hours . '小时自动确认收货';
                } elseif ($c_time < 3600 * 24 && $c_time > 3600) {
                    $hours = floor($c_time / 3600);
                    $result['receiving_countdown'] = '还剩' . $hours . '小时自动确认收货';
                } elseif ($c_time < 3600) {
                    $minute = floor($c_time / 60);
                    $result['receiving_countdown'] = '还剩' . $minute . '分钟自动确认收货';
                }
            } else {
                $result['receiving_countdown'] = '';
            }
        }
        return $result;
    }

    //我买到的
    public function getMyOrderList($uid, $order_status, $page, $size)
    {
        $start = ($page - 1) * $size;
        if (!$order_status) {
            $fields = 'and order_status in(1,2,5,6,7) order by id';
        } elseif ($order_status == 1) {
            $fields = 'and order_status in(1,5) order by order_status ASC,id ASC';
        } elseif ($order_status == 2) {
            $fields = 'and order_status =' . $order_status . ' order by pay_time ';
        } elseif ($order_status == 6) {
            $fields = 'and order_status =' . $order_status . ' order by send_time ';
        } elseif ($order_status == 7) {
            $fields = 'and order_status =' . $order_status . ' order by take_time desc ';
        }
        $sql = "select id,order_id,goods_id,sid,num,price_totals,score_totals,courier_img,order_status,is_defer,logistics_company,logistics_number from stage_goods_orders where uid = :uid and status in(0,1,5) $fields  limit :start,:size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['courier_img'] = $v['courier_img'] ? $v['courier_img'] : '';
                $goodsInfo = $this->getGoodsRedisById($v['goods_id']);
                $result[$k]['goods_info']['id'] = $goodsInfo['id'];
                $result[$k]['goods_info']['name'] = $goodsInfo['name'];
                $result[$k]['goods_info']['type'] = $goodsInfo['type'];
                $result[$k]['goods_info']['price'] = $goodsInfo['price'];
                $result[$k]['goods_info']['score'] = $goodsInfo['score'];
                $result[$k]['goods_info']['cover'] = IMG_DOMAIN . $goodsInfo['cover'];
                $stageModel = new StageModel();
                $stageInfo = $stageModel->getStage($v['sid']);
                $result[$k]['stage_info']['sid'] = $stageInfo['sid'];
                $result[$k]['stage_info']['name'] = $stageInfo['name'];
            }
        }
        return $result;
    }

    //我卖出的
    public function getSellOrderList($sid, $order_status, $page, $size)
    {
        $start = ($page - 1) * $size;
        if (!$order_status) {
            $fields = 'and order_status in(1,2,5,6,7) order by id desc';
        } elseif ($order_status == 1) {
            $fields = 'and order_status in(1,5) order by id desc';
        } elseif ($order_status == 2) {
            $fields = 'and order_status =' . $order_status . ' order by pay_time';
        } elseif ($order_status == 6) {
            $fields = 'and order_status =' . $order_status . ' order by send_time desc';
        } elseif ($order_status == 7) {
            $fields = 'and order_status =' . $order_status . ' order by take_time desc';
        }
        $sql = "select id,order_id,goods_id,uid,address_id,num,price_totals,score_totals,courier_img,order_status,add_time,logistics_number,logistics_company,logistics_type,logistics_tel from stage_goods_orders where sid = :sid and status in(0,1,4) $fields limit :start,:size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['courier_img'] = $v['courier_img'] ? $v['courier_img'] : '';
                $goodsInfo = $this->getGoodsRedisById($v['goods_id']);
                $result[$k]['goods_info']['id'] = $goodsInfo['id'];
                $result[$k]['goods_info']['name'] = $goodsInfo['name'];
                $result[$k]['goods_info']['type'] = $goodsInfo['type'];
                $result[$k]['goods_info']['price'] = $goodsInfo['price'];
                $result[$k]['goods_info']['score'] = $goodsInfo['score'];
                $result[$k]['goods_info']['cover'] = IMG_DOMAIN . $goodsInfo['cover'];
                $addressModel = new AddressModel();
                $shipping = $addressModel->getShippingById($v['uid'], $v['address_id']);
                if ($shipping) {
                    $result[$k]['shipping'] = $shipping[0];
                } else {
                    $result[$k]['shipping'] = (object)array();
                }
                $userModel = new UserModel();
                $userInfo = $userModel->getUserData($v['uid']);
                $result[$k]['user_info']['uid'] = $userInfo['uid'];
                $result[$k]['user_info']['nick_name'] = $userInfo['nick_name'];
            }
        }
        return $result;
    }

    //驿站主页商品点击更多
    public function getGoodsList($sid, $type = 0, $page, $size, $version, $token = '')
    {
        $time = date('Y-m-d H:i:s');
        $start = ($page - 1) * $size;
        $fields = $type ? 'and type =' . $type . '' : '';
        $sql = "select id,name,cover,type,price,score from stage_goods where sid = :sid and status< 2  and end_time > '" . $time . "' $fields order by add_time desc limit :start,:size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['cover'] = IMG_DOMAIN . $v['cover'];
                $result[$k]['url'] = $token ? I_DOMAIN . '/g/' . $v['id'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/g/' . $v['id'] . '?version=' . $version . '';
            }
        }
        return $result;
    }

    //延迟收货
    public function setDefer($id, $token, $version)
    {
        $info = $this->getOrderInfo($id, $token, $version);
        if (!$info) {
            return -1;
        }
        $modify_time = date('Y-m-d H:i:s', strtotime('+7 day', strtotime($info['modify_time'])));
        $stmt = $this->db->prepare("update stage_goods_orders set is_defer=1,modify_time=:modify_time where id=:id ");
        $array = array(
            ':modify_time' => $modify_time,
            ':id' => $id
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        return 1;
    }

    //确认收货
    public function setTakeTime($id, $token, $version)
    {
        $info = $this->getOrderInfo($id, $token, $version);
        if (!$info) {
            return -1;
        }
        $stmt = $this->db->prepare("update stage_goods_orders set take_time=:take_time,order_status=7 where id=:id ");
        $array = array(
            ':take_time' => date('Y-m-d H:i:s'),
            ':id' => $id
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        if(time()>strtotime(SERIAL_TIME)){
            $userModel = new UserModel();
            $order_info = $this->getOrderInfoByOrderId($info['order_id']);
            $goods_info = $this->getGoodsRedisById($order_info['goods_id']);
            //商家用户钱袋增加
            $userModel->addMoneyBagByUid($order_info['fact_totals'],$goods_info['uid']);
            //商家用户未到帐减少
            $userModel->addUnUseMoneyByUid($order_info['fact_totals'],$goods_info['uid'],'-');
            if($order_info['sp_id']){
                $sp_info = $userModel->getSpInfoById($info['sp_id']);
                //分享用户钱袋增加增加
                $userModel->addMoneyBagByUid($order_info['commission'], $sp_info['uid']);
                //分享用户未到账减少
                $userModel->addUnUseMoneyByUid($order_info['commission'], $sp_info['uid'],'-');
            }
        }
        return 1;
    }

    //脚本--自动收货
    public function initGoodsOrder()
    {
        $time = date('Y-m-d H:i');
        //查询符合条件的订单
        $stmt = $this->db->prepare("SELECT * FROM stage_goods_orders WHERE status< 2 and order_status=6");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $goodsInfo = $this->getInfo($v['goods_id']);
                if ($v['is_notice'] == 0 && date('Y-m-d H:i', strtotime($v['modify_time'])) < date("Y-m-d H:i", strtotime("+2 day"))) {
                    $stmt = $this->db->prepare("update stage_goods_orders set is_notice = 1 where id=:id ");
                    $array = array(
                        ':id' => $v['id']
                    );
                    $stmt->execute($array);
                    if ($goodsInfo['uid'] != $v['uid']) {
                        Common::addNoticeAndSmsForGoods(5, $v['order_id']);
                    }
                }
                if (date('Y-m-d H:i', strtotime($v['modify_time'])) < $time) {
                    $stmt = $this->db->prepare("update stage_goods_orders set take_time=:take_time,order_status=7 where id=:id ");
                    $array = array(
                        ':take_time' => date('Y-m-d H:i:s'),
                        ':id' => $v['id']
                    );
                    $stmt->execute($array);
                    if ($goodsInfo['uid'] != $v['uid']) {
                        Common::addNoticeAndSmsForGoods(4, $v['order_id']);
                    }
                    if(time()>strtotime(SERIAL_TIME)){
                        $userModel = new UserModel();
                        $goods_info = $this->getGoodsRedisById($v['goods_id']);
                        //商家用户钱袋增加
                        $userModel->addMoneyBagByUid($v['fact_totals'],$goods_info['uid']);
                        //商家用户未到帐减少
                        $userModel->addUnUseMoneyByUid($v['fact_totals'],$goods_info['uid'],'-');
                        if($v['sp_id']){
                            $sp_info = $userModel->getSpInfoById($v['sp_id']);
                            //分享用户钱袋增加增加
                            $userModel->addMoneyBagByUid($v['commission'], $sp_info['uid']);
                            //分享用户未到账减少
                            $userModel->addUnUseMoneyByUid($v['commission'], $sp_info['uid'],'-');
                        }
                    }
                }
            }
        }
    }

    public function updateRecommend($goods_id)
    {
        $stmt = $this->db->prepare("update stage_goods set is_recommend=0 where id=:id ");
        $array = array(
            ':id' => $goods_id
        );
        $stmt->execute($array);
    }

    public function getHotGoods($version, $token)
    {
        $time = date('Y-m-d H:i:s', time());
//        $sql = "SELECT a.* FROM (SELECT SUM(num) AS totals,goods_id FROM stage_goods_orders  WHERE order_status IN (1,2,6,7) AND STATUS< 2
//AND goods_id IN (SELECT id FROM stage_goods WHERE STATUS<2)
//GROUP BY goods_id ORDER BY totals DESC LIMIT 30)a ORDER BY RAND() LIMIT 5";
        $sql = "SELECT id as goods_id from stage_goods where status < 2 and DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i')>=DATE_FORMAT(start_time,'%Y-%m-%d %H:%i') AND DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i')<DATE_FORMAT(end_time,'%Y-%m-%d %H:%i') AND stock_num>0 ORDER BY RAND() LIMIT 5";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        if ($result) {
            foreach ($result as $k => $v) {
                $goodsInfo = $this->getInfo($v['goods_id']);
                $list[$k]['id'] = $goodsInfo['id'];
                $list[$k]['name'] = $goodsInfo['name'];
                $list[$k]['cover'] = $goodsInfo['cover'];
                $list[$k]['type'] = $goodsInfo['type'];
                $list[$k]['price'] = $goodsInfo['price'];
                $list[$k]['score'] = $goodsInfo['score'];
                $list[$k]['url'] = $token ? I_DOMAIN . '/g/' . $v['goods_id'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/g/' . $v['goods_id'] . '?version=' . $version . '';
                $list[$k]['city_name'] = $this->getCityAddress($goodsInfo['province'], $goodsInfo['city']);
            }
        }
        return $list;
    }

    public function getRcommendGoods($goods_id, $version, $token, $size = 5)
    {
        $time = date('Y-m-d H:i:s', time());
        $sql = "select id,name,sid,cover,type,price,score,(num-stock_num) AS sell_num,province,city,view_num from stage_goods where status< 2 and start_time <= '" . $time . "' and end_time > '" . $time . "' and  stock_num>0 and id !='" . $goods_id . "'  ORDER BY RAND() limit :size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            $stageModel = new StageModel();
            $visitModel = new VisitModel();
            foreach ($result as $k => $v) {
                $result[$k]['url'] = $token ? I_DOMAIN . '/g/' . $v['id'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/g/' . $v['id'] . '?version=' . $version . '';
                $result[$k]['address_name'] = $this->getCityAddress($v['province'], $v['city']);
                $stageInfo = $stageModel->getStage($v['sid']);
                $result[$k]['stage_name'] = $stageInfo['name'];
                $visitNum = $visitModel->getVisitNum('stagegoods', $v['id']);
                $result[$k]['view_num'] = $visitNum ? $visitNum : 0;
            }
        }
        return $result;
    }

    public function getCate()
    {
        $sql = "select id,name,class_name from stage_goods_cate where status=1 and pid=0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $sql_next = "select id,name,class_name from stage_goods_cate where status=1 and pid=" . $v['id'] . "";
                $stmt_next = $this->db->prepare($sql_next);
                $stmt_next->execute();
                $result[$k]['second'] = $stmt_next->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        return $result;
    }

    public function getBigCate()
    {
        $sql = "select id,name,class_name from stage_goods_cate where status=1 and pid=0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function getCityAddress($province, $city)
    {
        $addressModel = new AddressModel();
        $province_name = $addressModel->getNameById($province);
        $city_name = $addressModel->getNameById($city);
        if ($province_name && $city_name) {
            return $province_name . ' ' . $city_name;
        } else {
            return '';
        }
    }

    public function orderInfoById($id, $status = '')
    {
        $fields = $status ? '' : ' and status < 2';
        $stmt = $this->db->prepare("SELECT * FROM stage_goods_orders WHERE id =:id $fields ");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return false;
        }
        return $result;
    }

    public function delOrder($id, $status)
    {
        $stmt = $this->db->prepare("update stage_goods_orders set status=:status where id=:id ");
        $array = array(
            ':id' => $id,
            ':status' => $status
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        return 1;
    }

    public function getFeedGoodsInfo($goods_id)
    {
        $info = $this->getGoodsRedisById($goods_id);
        $stageModel = new StageModel();
        $stageInfo = $stageModel->getBasicStageBySid($info['sid']);
        $info['stage_name'] = $stageInfo['name'];
        $img_arr = Common::pregMatchImg($info['intro']);
        $info['img_src'][0] = IMG_DOMAIN . $info['cover'];
        $addressModel = new AddressModel();
        $province_name = $info['province'] ? $addressModel->getNameById($info['province']) : '';
        $city_name = $info['city'] ? $addressModel->getNameById($info['city']) : '';
        $info['address_name'] = $province_name . $city_name;
        foreach ($img_arr[3] as $k => $v) {
            $info['img_src'][$k + 1] = $v;
        }
        unset($info['intro']);
        if ($info['status'] < 2) {
            return $info;
        } else {
            return array();
        }
    }

    public function getListByConditionNew($big_cate_id, $cate_id, $city = '', $sort = '', $page, $size, $token = '', $version)
    {
        $start = ($page - 1) * $size;
        $conditionCate = $cate_id ? ' and cate_id =:cate_id' : '';
        if ($big_cate_id) {
            $s_ids = implode(',', $this->getSmallCateByPid($big_cate_id));
            $conditionBigCate = ' and cate_id in(' . $s_ids . ')';
        } else {
            $conditionBigCate = '';
        }
        $conditionSort = 'order by id desc';
        $conditionCity = $city ? ' and (province=:city or city=:city)' : '';
        if (!$sort || $sort == '综合排序') {
            $conditionSort = ' order by add_time desc,id desc';
        } elseif ($sort == '销量优先') {
            $conditionSort = ' order by sell_num desc,id desc';
        } elseif ($sort == '价格由高到低') {
            $conditionSort = ' order by price desc,score desc,id desc';
        } elseif ($sort == '价格由低到高') {
            $conditionSort = ' order by type asc,price asc,score asc,id desc';
        }
        $sql = "select id as goods_id,(num-stock_num) as sell_num from stage_goods where status < 2 and is_show=1 and DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i')>=DATE_FORMAT(start_time,'%Y-%m-%d %H:%i') AND DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i')<DATE_FORMAT(end_time,'%Y-%m-%d %H:%i') AND stock_num>0 $conditionBigCate $conditionCate $conditionCity $conditionSort limit :start,:size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        if ($cate_id) {
            $stmt->bindValue(':cate_id', $cate_id, PDO::PARAM_INT);
        }
        if ($city) {
            $stmt->bindValue(':city', $city, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        if ($result) {
            foreach ($result as $k => $v) {
                $stageModel = new StageModel();
                $goodsInfo = $this->getInfo($v['goods_id']);
                $list[$k]['id'] = $v['goods_id'];
                $list[$k]['name'] = $goodsInfo['name'];
                $list[$k]['cover'] = Common::show_img($goodsInfo['cover'], 4, 720, 720);
                $list[$k]['type'] = $goodsInfo['type'];
                $list[$k]['price'] = $goodsInfo['price'];
                $list[$k]['score'] = $goodsInfo['score'];
                $stageInfo = $stageModel->getBasicStageBySid($goodsInfo['sid']);
                $list[$k]['sid'] = $goodsInfo['sid'];
                $list[$k]['stage_name'] = $stageInfo['name'];
                $list[$k]['sell_num'] = $v['sell_num'];
                $list[$k]['url'] = $token ? I_DOMAIN . '/g/' . $v['goods_id'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/g/' . $v['goods_id'] . '?version=' . $version . '';
            }
        }
        return $list;
    }

    //更新商品大分类获取小分类
    public function getSmallCateByPid($pid)
    {
        $stmt = $this->db->prepare("SELECT id FROM stage_goods_cate WHERE pid =:pid and status=1 ");
        $array = array(
            ':pid' => $pid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ids = array();
        if ($result) {
            foreach ($result as $v) {
                $ids[] = $v['id'];
            }
        }
        return $ids;
    }

    //我买到的商品订单数量
    public function getMyOrderListNum($uid, $order_status)
    {
        if ($order_status == 1) {
            $fields = 'and order_status =1 order by order_status ASC,id ASC';
        } elseif ($order_status == 2) {
            $fields = 'and order_status =' . $order_status . ' order by pay_time ';
        } elseif ($order_status == 6) {
            $fields = 'and order_status =' . $order_status . ' order by send_time ';
        } elseif ($order_status == 7) {
            $fields = 'and order_status =' . $order_status . ' order by take_time desc ';
        }
        $sql = "select count(id) num from stage_goods_orders where uid = :uid and status in(0,1,5) $fields";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //我卖出的商品订单数量
    public function getSellOrderListNum($sid, $order_status)
    {
        if ($order_status == 1) {
            $fields = 'and order_status =1 order by id desc';
        } elseif ($order_status == 2) {
            $fields = 'and order_status =' . $order_status . ' order by pay_time';
        } elseif ($order_status == 6) {
            $fields = 'and order_status =' . $order_status . ' order by send_time desc';
        } elseif ($order_status == 7) {
            $fields = 'and order_status =' . $order_status . ' order by take_time desc';
        }
        $sql = "select count(id) num from stage_goods_orders where sid = :sid and status in(0,1,4) $fields";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //商品详情页获取商品详情信息
    public function getGoodsDetailById($id)
    {
        $result = $this->getGoodsRedisById($id);
        if (!$result || $result['status'] > 1) {
            return 0;
        }
        $time = date("Y-m-d H:i:s");
        if ($result['start_time'] > $time && !$result['reason']) {
            $result['start_type'] = 1;
        } elseif ($result['start_time'] <= $time && $result['end_time'] > $time) {
            $result['start_type'] = 2;
        } elseif ($result['end_time'] <= $time && !$result['reason']) {
            $result['start_type'] = 3;
        } else {
            $result['start_type'] = 4;
        }
        if (in_array($result['start_type'], array(3, 4)) && $result['is_recommend'] == 1) {
            $result['is_recommend'] = 0;
            $this->updateRecommend($id);
        }
        $result['province'] = $result['province'] ? $result['province'] : '';
        $result['city'] = $result['city'] ? $result['city'] : '';
        $result['sell_num'] = $this->goodsSellNum($id);
        $addressModel = new AddressModel();
        $province_name = $result['province'] ? $addressModel->getNameById($result['province']) : '';
        $city_name = $result['city'] ? $addressModel->getNameById($result['city']) : '';
        $result['address_name'] = $province_name . $city_name;
        return $result;
    }

    //商品详情页买家购买记录
    public function getBuyListForHtml($goods_id, $size)
    {
        $stmt = $this->db->prepare("SELECT uid,SUM(num) AS num FROM stage_goods_orders WHERE STATUS<2 AND goods_id=:goods_id AND order_status IN(2,6,7) GROUP BY uid   ORDER BY id DESC LIMIT :size ");
        $stmt->bindValue(':goods_id', $goods_id, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $userModel = new UserModel();
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['add_time'] = $this->getBuyNewTimeForHtml($goods_id, $v['uid']);
                $result[$k]['user_info'] = $userModel->getUserData($v['uid']);
            }
        }
        $num = $this->getBuyCount($goods_id);
        return $data = array(
            'list' => $result,
            'size' => $num
        );
    }

    public function getBuyNumForHtml($goods_id, $uid)
    {
        $stmt = $this->db->prepare("SELECT count(id) as num FROM stage_goods_orders WHERE goods_id =:goods_id and uid=:uid AND STATUS < 2 AND order_status IN(2,6,7)");
        $stmt->bindValue(':goods_id', $goods_id, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    public function getBuyNewTimeForHtml($goods_id, $uid)
    {
        $stmt = $this->db->prepare("SELECT add_time FROM stage_goods_orders WHERE goods_id =:goods_id and uid=:uid AND STATUS < 2 AND order_status IN(2,6,7) order by add_time desc limit 1");
        $stmt->bindValue(':goods_id', $goods_id, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return Common::app_show_time($result['add_time']);
    }

    public function goodsSellNum($goods_id)
    {
        $stmt = $this->db->prepare("SELECT SUM(num) as num FROM stage_goods_orders WHERE goods_id =:goods_id  AND STATUS < 2 AND order_status IN(2,6,7)");
        $stmt->bindValue(':goods_id', $goods_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    public function getBuyCount($goods_id)
    {
        $stmt = $this->db->prepare("select count(distinct(uid)) as num from stage_goods_orders where goods_id = :goods_id and status < 2 AND order_status IN(2,6,7)");
        $array = array(
            ':goods_id' => $goods_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];

    }

    public function getGoodsForHtml($size, $goods_id, $version, $token, $sid, $flag = 1)
    {
        if ($flag == 1) {
            $fields = 'and sid =:sid';
        } else {
            $fields = 'and sid !=:sid';
        }
        $time = date('Y-m-d H:i:s', time());
        $sql = "select id,name,sid,cover,type,price,score,province,city,view_num from stage_goods where status< 2 and start_time <= '" . $time . "' and end_time > '" . $time . "' and  stock_num>0 and id !='" . $goods_id . "' $fields  ORDER BY RAND() limit :size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            $stageModel = new StageModel();
            foreach ($result as $k => $v) {
                $result[$k]['url'] = $token ? I_DOMAIN . '/g/' . $v['id'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/g/' . $v['id'] . '?version=' . $version . '';
                $result[$k]['address_name'] = $this->getCityAddress($v['province'], $v['city']);
                $stageInfo = $stageModel->getStage($v['sid']);
                $result[$k]['stage_name'] = $stageInfo['name'];
                $result[$k]['sell_num'] = $this->getSellNum($v['id']);
            }
        }
        return $result;
    }

    public function setBuyOrderStatus()
    {
        $stmt = $this->db->prepare("SELECT * FROM stage_goods_orders WHERE order_status=5 AND pay_time!='' ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $stmt_u = $this->db->prepare("update stage_goods_orders set order_status = 2 where id=:id ");
                $array = array(
                    ':id' => $v['id']
                );
                $stmt_u->execute($array);
                if(time()>strtotime(SERIAL_TIME)){
                    $userModel = new UserModel();
                    $goods_info = $this->getGoodsRedisById($v['goods_id']);
                    //商家未到账增加
                    $userModel->addUnUseMoneyByUid($v['fact_totals'],$goods_info['uid']);
                    if($v['sp_id']){
                        $sp_info = $userModel->getSpInfoById($v['sp_id']);
                        //用户未到账增加
                        $userModel->addUnUseMoneyByUid($v['commission'],$sp_info['uid']);
                    }
                }
            }
        }
    }

    public function goodsForIndex($page, $size, $version, $token = '', $push_type = '')
    {
        $start = ($page - 1) * $size;
        $time = date('Y-m-d H:i:s');
        if (!$push_type) {
            $push_fields = 'type=1 and';
        } else {
            $push_fields = 'type=' . $push_type . ' and';
        }
        $stmt = $this->db->prepare("SELECT id,name FROM (SELECT goods_id AS id,NAME FROM  stage_goods_push WHERE $push_fields goods_id IN (SELECT id FROM stage_goods WHERE STATUS < 2 AND end_time > '" . $time . "') AND STATUS = 1 ORDER BY sort ASC LIMIT :start,:size) a ORDER BY RAND() LIMIT 3");
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stageModel = new StageModel();
        $stagegoodsModel = new StagegoodsModel();
        if ($result) {
            foreach ($result as $k => $v) {
                $result_info = $stagegoodsModel->getGoodsRedisById($v['id']);
                $result[$k]['sid'] = $result_info['sid'];
                $result[$k]['cover'] = $result_info['cover'];
                $result[$k]['type'] = $result_info['type'];
                $result[$k]['price'] = $result_info['price'];
                $result[$k]['score'] = $result_info['score'];
                $stageInfo = $stageModel->getBasicStageBySid($result_info['sid']);
                $result[$k]['stage_name'] = $stageInfo['name'];
                $result[$k]['url'] = $token ? I_DOMAIN . '/g/' . $v['id'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/g/' . $v['id'] . '?version=' . $version . '';
                $result[$k]['sell_num'] = $stagegoodsModel->getSellNum($v['id']);
            }
        }
        return $result;
    }

    //获取缓存中的商品数据
    public function getGoodsRedisById($id)
    {
        $redisKey = Common::getRedisKey(12) . $id;
        $result = $this->contentRedis->get($redisKey);
        if ($result) {
            $result = json_decode($result, true);
        } else {
            $stmt = $this->db->prepare("select * from stage_goods where id=:id");
            $array = array(
                ':id' => $id
            );
            $stmt->execute($array);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->contentRedis->set($redisKey, json_encode($result));
        }
        return $result;
    }

    //获取订单信息
    public function getLogisticsInfo($id)
    {
        $stmt = $this->db->prepare("SELECT id,goods_id,courier_img,logistics_number,logistics_company,logistics_type,logistics_tel FROM stage_goods_orders WHERE id =:id");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $goodsInfo = $this->getGoodsRedisById($result['goods_id']);
            $result['cover'] = $goodsInfo['cover'];
        }
        return $result;
    }

    //设置商品佣金（支持批量）
    public function setCommission($is_commission, $number, $uid, $id_json)
    {
        $ids = json_decode($id_json, true);
        $time = date('Y-m-d H:i:s');
        $use_time = date("Y-m-d H:i:s", strtotime("+5 day"));
        $commonModel = new CommonModel();
        if ($ids) {
            foreach ($ids as $goods_id) {
                $info = $this->getGoodsRedisById($goods_id);
                if($info['status']<2){
                    if($info['commission_time']){
                        $commission_time = $info['commission_time'];
                        $commission_update_time = $time;
                    }else{
                        $commission_time = $time;
                        $commission_update_time = $time;
                    }
                    if ($is_commission) {
                        $commission = round($info['price'] * $number, 2);
                        $min_commission = $commission;
                        $max_commission = '';
                    } else {
                        $commission = '';
                        $min_commission = '';
                        $max_commission = '';
                    }
                    $stmt = $this->db->prepare("update stage_goods set uid=:uid,is_commission =:is_commission,commission_rate=:commission_rate,commission=:commission,min_commission =:min_commission,max_commission=:max_commission,commission_time=:commission_time,commission_update_time=:commission_update_time,update_time=:update_time where id=:id ");
                    $array = array(
                        ':uid' => $uid,
                        ':is_commission' => $is_commission,
                        ':commission_rate' => $number,
                        ':commission' => $commission,
                        ':commission_time' => $commission_time,
                        ':commission_update_time'=>$commission_update_time,
                        ':min_commission' => $min_commission,
                        ':max_commission' => $max_commission,
                        ':update_time' => date('Y-m-d H:i:s'),
                        ':id' => $goods_id
                    );
                    $stmt->execute($array);
                    if ($is_commission) {
                        $set_id = $commonModel->userSetCommissionRate(12, $goods_id, $number, $uid);
                    } else {
                        $set_id = 0;
                    }
                    $last_id = $commonModel->getUserSetLastId($set_id, 12, $goods_id);
                    if ($last_id) {
                        $commonModel->updateCommissionTime($last_id, $use_time);
                    }
                    $this->contentRedis->del(Common::getRedisKey(12) . $goods_id);
                }
            }
        }
    }

    //商品分享筛选
    public function getCommissionByCondition($big_cate_id = '', $sort_id, $page, $size, $token = '', $version)
    {
        $start = ($page - 1) * $size;
        $conditionBigCate = '';
        $conditionSort = '';
        if ($big_cate_id) {
            $s_ids = implode(',', $this->getSmallCateByPid($big_cate_id));
            $conditionBigCate = ' and cate_id in(' . $s_ids . ')';
        }
        if ($sort_id == 1) {
            $conditionSort = 'order by commission_time desc,id desc';
        } elseif ($sort_id == 2) {
            $conditionSort = 'order by (commission+0) desc,id desc';
        } elseif ($sort_id == 3) {
            $conditionSort = 'order by orders_statistics desc,id desc';
        } elseif ($sort_id == 4) {
            $conditionSort = 'order by (orders_commission_statistics+0) desc,id desc';
        }
        $sql = "select id from stage_goods where status < 2 and is_commission=1  $conditionBigCate  $conditionSort limit :start,:size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        $stageModel = new StageModel();
        foreach ($result as $k => $v) {
            $goods_info = $this->getGoodsRedisById($v['id']);
            $list[$k]['id'] = $v['id'];
            $list[$k]['cover'] = IMG_DOMAIN . $goods_info['cover'];
            $list[$k]['name'] = $goods_info['name'];
            $list[$k]['price'] = $goods_info['price'];
            $list[$k]['commission_rate'] = $goods_info['commission_rate'];
            $list[$k]['commission'] = $goods_info['commission'];
            $stage_info = $stageModel->getStage($goods_info['sid']);
            $list[$k]['stage_name'] = $stage_info['name'];
            $list[$k]['url'] = $token ? I_DOMAIN . '/g/' . $v['id'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/g/' . $v['id'] . '?version=' . $version . '';
        }
        return $list;
    }

    public function getStageGoodsById($objId, $version, $token = '')
    {
        $sql = "select id from stage_goods where id=:objId";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':objId', $objId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $list = array();
        $stageModel = new StageModel();
        $goods_info = $this->getGoodsRedisById($result['id']);
        $list['id'] = $result['id'];
        $list['cover'] = IMG_DOMAIN . $goods_info['cover'];
        $list['name'] = $goods_info['name'];
        $list['price'] = $goods_info['price'];
        $list['commission_rate'] = $goods_info['commission_rate'];
        $list['commission'] = $goods_info['commission'];
        $stage_info = $stageModel->getStage($goods_info['sid']);
        $list['stage_name'] = $stage_info['name'];
        $list['url'] = $token ? I_DOMAIN . '/g/' . $result['id'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/g/' . $result['id'] . '?version=' . $version . '';
        return $list;
    }

    //商品更新统计30天引入订单量和引入订单金额
    public function updateStatistics()
    {
        $time = date("Y-m-d H:m:s", strtotime("-1 month"));
        $stmt = $this->db->prepare("SELECT DISTINCT(obj_id) AS goods_id FROM user_set_commission_rate WHERE TYPE = 12 ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $orders_num = $totals = $commission = 0;
        if ($result) {
            foreach ($result as $v) {
                $stmt_orders = $this->db->prepare("SELECT id,price_totals,commission FROM stage_goods_orders WHERE add_time>:time and order_status = 2 and goods_id =:id ");
                $array = array(
                    ':time' => $time,
                    ':id' => $v['goods_id']
                );
                $stmt_orders->execute($array);
                $orders = $stmt_orders->fetch(PDO::FETCH_ASSOC);
                if ($orders) {
                    $orders_num += count($orders);
                    foreach ($orders as $val) {
                        $totals += $val['price_totals'];
                        $commission += $v['commission'];
                    }
                }
                $stmt = $this->db->prepare("update stage_goods set orders_statistics=:num,orders_prices_statistics =:totals,orders_commission_statistics=:commission,update_time=:update_time where id=:id");
                $array = array(
                    ':num' => $orders_num,
                    ':totals' => $totals,
                    ':commission' => $commission,
                    ':update_time' => date('Y-m-d H:i:s'),
                    ':id' => $v['goods_id']
                );
                $stmt->execute($array);

            }
        }
    }
    public function getOrderInfoById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM stage_goods_orders WHERE id =:id ");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    //获取驿站下商品分享支出总额和分享交易订单总额/某一个商品
    public function getGoodsSpCommissionTotals($sid,$goods_id=''){
        $fields = $goods_id ? ' and goods_id ='.$goods_id.'' : '';
        $stmt = $this->db->prepare("select price_totals,commission from stage_goods_orders where sid=:sid and sp_id!='' and order_status = 7 $fields");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data['price_totals'] = $data['commission_totals'] = 0;
        if($rs){
            foreach($rs as $v){
                $data['price_totals']+=(int)$v['price_totals'];
                $data['commission_totals'] +=(int)$v['commission'];
            }
        }
        return $data;
    }
}
