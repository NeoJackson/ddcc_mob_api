<?php

class EventModel
{
    private $db;
    private $redis;

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
        $this->contentRedis = CRedis::getContentInstance();

    }

    /**
     * 发表服务信息
     */
    public function add($data)
    {
        $stageModel = new StageModel();
        $security = new Security();
        $stage_num = $stageModel->stageIsExist($data['sid']);
        if ($stage_num == 0) {
            return -1;
        }
        $join_info = $stageModel->getJoinStage($data['sid'], $data['uid']);
        if (!$join_info) {
            return -2;
        }
        $content = $security->xss_clean($data['content']);
        $content = strtr($content, array('<embed' => '<embed wmode="opaque"'));
        $summary = Common::msubstr(Common::deleteHtml($content), 0, 120, 'UTF-8', false);
        $stmt = $this->db->prepare("insert into event (uid,sid,title,summary,cover,content,type,type_code,address,lng,lat,province,city,town,origin,price_type,agio,agio_info) values (:uid,:sid,:title,:summary,:cover,:content,:type,:type_code,:address,:lng,:lat,:province,:city,:town,:origin,:price_type,:agio,:agio_info)");
        // print_r($data['type_code']);exit;
        $array = array(
            ':uid' => $data['uid'],
            ':sid' => $data['sid'],
            ':title' => $data['title'],
            ':summary' => $summary,
            ':cover' => $data['cover'],
            ':content' => $content,
            ':type' => $data['type'],
            ':type_code' => $data['type_code'],
            ':address' => $data['address'],
            ':lng' => $data['lng'],
            ':lat' => $data['lat'],
            ':province' => $data['province'],
            ':city' => $data['city'],
            ':town' => $data['town'],
            ':price_type' => $data['price_type'],
            ':origin' => $data['origin'],
            ':agio' => $data['agio'],
            ':agio_info' => $data['agio_info'],
        );
        //  try{
        $stmt->execute($array);
//        } catch (PDOException $e) {
//            Common::echoAjaxJson(500,"内容包含非法字符，请重新编辑");
//        }
        $id = $this->db->lastInsertId();
        if (!$id) {
            Common::echoAjaxJson(600, "发的太快，休息一下吧");
        }
        //添加场次
        foreach ($data['priceInfoArr'] as $v) {
            $fields_id = $this->addEventFields($id, $v['start_time'], $v['end_time'], $v['partake_end_time']);
            $this->addEventPrice($v['price_info'], $id, $fields_id);
        }
        //场次添加完毕把开始时间、结束时间、报名截止时间更新到主表
        $this->updateEventTime($id);
        Common::http(OPEN_DOMAIN . "/common/addFeed", array('scope' => 1, 'uid' => $data['uid'], 'type' => 'event', "id" => $id, "time" => time()), "POST");
        $this->updateEventNum($data['sid'], 1);//更新驿站活动数
        return $id;
    }

    //把开始时间、结束时间、报名截止时间更新到主表
    public function updateEventTime($eid)
    {
        $stmt = $this->db->prepare("update event e set start_time = (select start_time from event_fields where eid=:eid order by start_time asc limit 1),
        end_time = (select end_time from event_fields where eid=:eid order by end_time desc limit 1),
        partake_end_time = (select partake_end_time from event_fields where eid=:eid order by partake_end_time desc limit 1)
        where id = :eid");
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
    }

    //更新驿站服务信息数
    public function updateEventNum($sid, $num)
    {
        $stmt = $this->db->prepare("update stage set event_num = event_num +$num , last_topic_time = :last_topic_time,update_time=:update_time where sid=:sid");
        $array = array(
            ':last_topic_time' => date('Y-m-d H:i:s'),
            ':update_time' => date('Y-m-d H:i:s'),
            ':sid' => $sid
        );
        $stmt->execute($array);
    }

    /**
     * 获取服务信息
     */
    public function getEvent($id, $status = 0, $uid = 0)
    {
        $status_fields = $status ? '' : 'and status<2';
        //查询活动信息
        $stmt = $this->db->prepare("select id,uid,sid,title,summary,cover,content,is_top,is_good,is_notice,type,type_code,address as event_address,lng,lat,is_recommend,is_close,last_comment_uid,last_comment_time,comment_num,repeat_num,view_num,like_num,reward_num,partake_num,share_num,collect_num,max_partake,origin,price_type,agio,agio_info,start_time,end_time,partake_end_time,status,add_time,update_time,type_code,province,city,town from event where id=:id $status_fields");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $result['type_info'] = $this->getBusinessEventType($result['type_code']);
            $stageModel = new StageModel();
            $userModel = new UserModel();
            $stageInfo = $stageModel->getBasicStageBySid($result['sid']);
            $userInfo = $userModel->getUserData($result['uid'], $uid);
            $result['total_num'] = 0;
            $result['stage_name'] = $stageInfo['name'];
            $result['did'] = $userInfo['did'];
            $result['nick_name'] = $userInfo['nick_name'];
            $result['avatar'] = $userInfo['avatar'];
            $result['ico_type'] = $userInfo['ico_type'];
            //$result['event_attr'] = $result_info;
            $img_arr = Common::pregMatchImg($result['content']);
            if ($img_arr) {
                $result['img_src'] = $img_arr[3];
            } else {
                $result['img_src'] = array();
            }
        }
        return $result;
    }

    //文化圈动态--获取服务信息
    public function getFeedEventInfo($id)
    {
        //查询活动信息
        $data = $this->getEventRedisById($id);
        $result = array();
        if ($data['status'] < 2) {
            $result['id'] = $data['id'];
            $result['sid'] = $data['sid'];
            $result['uid'] = $data['uid'];
            $result['title'] = $data['title'];
            $result['cover'] = $data['cover'];
            $result['type'] = $data['type'];
            $result['lng'] = $data['lng'];
            $result['lat'] = $data['lat'];
            $result['like_num'] = $data['like_num'];
            $result['status'] = $data['status'];
            $result['type_code'] = $data['type_code'];
            $result['province'] = $data['province'];
            $result['city'] = $data['city'];
            $result['price_type'] = $data['price_type'];
            $result['comment_num'] = $data['comment_num'];
            $stageModel = new StageModel();
            $stageInfo = $stageModel->getBasicStageBySid($data['sid']);
            $result['stage_name'] = $stageInfo['name'];
            $result['show_start_time'] = Common::getEventStartTime($id);
            $result['cover'] = Common::show_img($data['cover'], 4, 1000, 600);
            $addressModel = new AddressModel();
            $province_name = $data['province'] ? $addressModel->getNameById($data['province']) : '';
            $city_name = $data['city'] ? $addressModel->getNameById($data['city']) : '';
            $result['address_name'] = $province_name . $city_name;
            $result['feed_type'] = 10;
            if ($result['type'] == 1) {
                $type_info = $this->getBusinessEventType($result['type_code']);
            } else {
                $type_info = Common::eventType($result['type']);
            }
            $result['type_name'] = $type_info['name'];
            $result['code_name'] = $type_info['code'];
            if ($data['price_type'] == 1) {
                $result['price'] = '免费';
                $result['price_count'] = 1;
            } else {
                $result['price'] = isset($data['price_list'][0]['unit_price']) ? $data['price_list'][0]['unit_price'] : '';
                $result['price_count'] = count($data['price_list']);
            }
        }

        return $result;
    }

    //获取活动的基本信息
    public function getBasicEvent($id)
    {
        $stmt = $this->db->prepare("select id,uid,sid,title,cover,price_type,type,type_code,summary,address,lng,lat,province,city,comment_num,repeat_num,view_num,like_num,reward_num,partake_num,collect_num,share_num,start_time,end_time,add_time,type_code from event where id=:id and status<2");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $result['type_info'] = $this->getBusinessEventType($result['type_code']);
        }
        return $result;
    }

    /**
     * 修改服务基本信息
     */
    public function modifyEvent($id, $title, $cover, $content, $address, $lng, $lat, $province, $city, $town, $is_close, $max_partake, $attrArr, $event_partake_bind = array(), $version)
    {
        if ($version < '2.5.1') {
            $commonModel = new CommonModel();
            $province = $commonModel->getCity($province);
            $city = $commonModel->getCity($city);
            $town = $commonModel->getCity($town);
        }
        $security = new Security();
        $content = $security->xss_clean($content);
        $content = strtr($content, array('<embed' => '<embed wmode="opaque"'));
        $summary = Common::msubstr(Common::deleteHtml($content), 0, 120, 'UTF-8', false);
        $stmt = $this->db->prepare("update event set title=:title,summary=:summary,cover=:cover,content=:content,address=:address,lng=:lng,lat=:lat,province=:province,city=:city,town=:town,is_close=:is_close,max_partake=:max_partake,update_time=:update_time where id=:id");
        $array = array(
            ':title' => $title,
            ':cover' => $cover,
            ':summary' => $summary,
            ':content' => $content,
            ':address' => $address,
            ':lng' => $lng,
            ':lat' => $lat,
            ':province' => $province,
            ':city' => $city,
            ':town' => $town,
            ':is_close' => $is_close,
            ':max_partake' => $max_partake,
            ':update_time' => date('Y-m-d H:i:s'),
            ':id' => $id
        );
        try {
            $stmt->execute($array);
        } catch (PDOException $e) {
            Common::echoAjaxJson(500, "内容包含非法字符，请重新编辑");
        }
        $count = $stmt->rowCount();
        if ($attrArr) {
            $this->modifyEventAttr($attrArr, $id);
        }
        if ($count < 1) {
            return 0;
        }
        $this->updatePartakeBind($id, $event_partake_bind);
        return 1;
    }
    /**
     * 修改服务基本信息
     */
    //$id,$title,$cover,$content,$address,$lng,$lat,$province,$city,$town,$is_close,$end_time,$partake_end_time,$event_partake_bind=array()
    public function update($data)
    {
        $security = new Security();
        $content = $security->xss_clean($data['content']);
        $content = strtr($content, array('<embed' => '<embed wmode="opaque"'));
        $summary = Common::msubstr(Common::deleteHtml($content), 0, 120, 'UTF-8', false);
        $stmt = $this->db->prepare("update event set title=:title,summary=:summary,cover=:cover,content=:content,address=:address,lng=:lng,lat=:lat,province=:province,city=:city,town=:town,is_close=:is_close,update_time=:update_time where id=:id");
        $array = array(
            ':title' => $data['title'],
            ':cover' => $data['cover'],
            ':summary' => $summary,
            ':content' => $content,
            ':address' => $data['address'],
            ':lng' => $data['lng'],
            ':lat' => $data['lat'],
            ':province' => $data['province'],
            ':city' => $data['city'],
            ':town' => $data['town'],
            ':is_close' => $data['is_close'],
            ':update_time' => date('Y-m-d H:i:s'),
            ':id' => $data['id']
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        $stmt_fields = $this->db->prepare("update event_fields set end_time=:end_time,partake_end_time=:partake_end_time where eid=:eid");
        $array_fields = array(
            ':end_time' => $data['end_time'],
            ':partake_end_time' => $data['partake_end_time'],
            ':eid' => $data['id']
        );
        $stmt_fields->execute($array_fields);
        $this->updatePartakeBind($data['id'], $data['pArr']);
        return 1;
    }

    public function updateEvent($data)
    {
        $security = new Security();
        $content = $security->xss_clean($data['content']);
        $content = strtr($content, array('<embed' => '<embed wmode="opaque"'));
        $summary = Common::msubstr(Common::deleteHtml($content), 0, 120, 'UTF-8', false);
        $stmt = $this->db->prepare("update event set title=:title,summary=:summary,cover=:cover,content=:content,type=:type,type_code=:type_code,address=:address,lng=:lng,lat=:lat,province=:province,city=:city,town=:town,agio=:agio,agio_info=:agio_info,update_time=:update_time where id=:id");
        $array = array(
            ':title' => $data['title'],
            ':cover' => $data['cover'],
            ':summary' => $summary,
            ':content' => $content,
            ':type' => $data['type'],
            ':type_code' => $data['type_code'],
            ':address' => $data['address'],
            ':lng' => $data['lng'],
            ':lat' => $data['lat'],
            ':province' => $data['province'],
            ':city' => $data['city'],
            ':town' => $data['town'],
            ':agio' => $data['agio'],
            ':agio_info' => $data['agio_info'],
            ':update_time' => date('Y-m-d H:i:s'),
            ':id' => $data['id']
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        $this->updateEventFields($data['id'], $data['priceInfoArr']);
        $this->updatePartakeBind($data['id'], $data['pArr']);
        //场次更新完毕把开始时间、结束时间、报名截止时间更新到主表
        $this->updateEventTime($data['id']);
        //删除原缓存
        $this->contentRedis->del(Common::getRedisKey(10) . $data['id']);
        return 1;
    }

    //修改服务信息报名截止时间
    public function updatePartakeEndTime($partake_end_time, $id)
    {
        $stmt = $this->db->prepare("update event_fields set partake_end_time=:partake_end_time where eid=:eid and partake_end_time >:partake_end_time");
        $array = array(
            ':partake_end_time' => $partake_end_time,
            ':eid' => $id
        );
        $stmt->execute($array);
    }

    //获取活动分类
    public function getEventTypeList()
    {
        $stmt = $this->db->prepare("SELECT id,name FROM business_event_type WHERE status = 0");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //根据大分类获取列表
    public function getEventList($sid, $type, $page, $size, $token = '', $version)
    {
        $start = ($page - 1) * $size;
        $field = isset($type) && $type ? 'and type=' . $type : '';
        $sid_field = isset($sid) && $sid ? 'and sid=' . $sid : '';
        $stmt = $this->db->prepare("SELECT id,title,type,cover,type_code,is_top,is_good,price_type,add_time FROM event WHERE STATUS < 2 $sid_field $field ORDER BY add_time DESC limit :start,:size");
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['url'] = $token ? I_DOMAIN . '/e/' . $v['id'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/e/' . $v['id'] . '?version=' . $version . '';
                $result[$k]['cover'] = Common::show_img($v['cover'], 4, 400, 300);
                if ($v['type'] == 1) {
                    $type_info = $this->getBusinessEventType($v['type_code']);
                } else {
                    $type_info = Common::eventType($v['type']);
                }
                $result[$k]['type_name'] = $type_info['name'];
                $result[$k]['code_name'] = $type_info['code'];
                if ($v['price_type'] == 1) {
                    $num = $this->getPartakeCount($v['id']);
                } else {
                    $num = $this->getOrdersCount($v['id']);
                }
                $result[$k]['partake_num']['sell_num'] = $num;
                $result[$k]['partake_end_time'] = $this->getEventPartakeEndTime($v['id']);
                $p_time = $this->getEndPartakeTime($v['id']);//报名结束时间
                $e_time = $this->getEndTime($v['id']);//結束时间
                $time = date('Y-m-d H:i:s');
                if ($p_time && $e_time) {
                    if ($p_time[0]['partake_end_time'] <= $time && $e_time[0]['end_time'] > $time) {
                        //当前时间小于报名截止时间 大于活动结束时间
                        $result[$k]['start_type'] = 3;//报名截止
                    } elseif ($e_time[0]['end_time'] <= $time) {
                        //当前时间小于活动结束时间
                        $result[$k]['start_type'] = 3;//活动结束
                    } else {
                        $result[$k]['start_type'] = 2;//可以报名
                    }
                } else {
                    $result[$k]['start_type'] = 3;//活动结束
                }

            }
        }
        return $result;
    }

    public function getEventListByLastTime($sid, $type, $last_time, $size, $token = '', $version)
    {
        $field = isset($type) && $type ? 'and type=' . $type : '';
        $sid_field = isset($sid) && $sid ? 'and sid=' . $sid : '';
        if ($last_time) {
            $stmt = $this->db->prepare("SELECT id,title,type,cover,type_code,is_top,is_good,price_type,add_time FROM event WHERE STATUS < 2 and add_time < :last_time $sid_field $field ORDER BY add_time DESC limit :size");
            $stmt->bindValue(':last_time', $last_time, PDO::PARAM_STR);
        } else {
            $stmt = $this->db->prepare("SELECT id,title,type,cover,type_code,is_top,is_good,price_type,add_time FROM event WHERE STATUS < 2 $sid_field $field ORDER BY add_time DESC limit :size");
        }
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['url'] = $token ? I_DOMAIN . '/e/' . $v['id'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/e/' . $v['id'] . '?version=' . $version . '';
                $result[$k]['cover'] = Common::show_img($v['cover'], 4, 400, 300);
                if ($v['type'] == 1) {
                    $type_info = $this->getBusinessEventType($v['type_code']);
                } else {
                    $type_info = Common::eventType($v['type']);
                }
                $result[$k]['type_name'] = $type_info['name'];
                $result[$k]['code_name'] = $type_info['code'];
                $result[$k]['partake_end_time'] = $this->getEventPartakeEndTime($v['id']);
                $p_time = $this->getEndPartakeTime($v['id']);//报名结束时间
                $e_time = $this->getEndTime($v['id']);//結束时间
                $time = date('Y-m-d H:i:s');
                if ($v['price_type'] == 1) {
                    $num = $this->getPartakeCount($v['id']);
                } else {
                    $num = $this->getOrdersCount($v['id']);
                }
                $result[$k]['partake_num']['sell_num'] = $num;
                if ($p_time && $e_time) {
                    if ($p_time[0]['partake_end_time'] <= $time && $e_time[0]['end_time'] > $time) {
                        //当前时间小于报名截止时间 大于活动结束时间
                        $result[$k]['start_type'] = 3;//报名截止
                    } elseif ($e_time[0]['end_time'] <= $time) {
                        //当前时间小于活动结束时间
                        $result[$k]['start_type'] = 3;//活动结束
                    } else {
                        $result[$k]['start_type'] = 2;//可以报名
                    }
                } else {
                    $result[$k]['start_type'] = 3;//活动结束
                }
            }
        }
        return $result;
    }

    //获取某个活动字段的值
    public function getEventValueById($eid, $attr_name)
    {
        $stmt = $this->db->prepare("select id, eid, uid, attr_name, attr_value, remark, add_time, update_time from event_attr where eid =:eid and attr_name=:attr_name");
        $array = array(
            ':eid' => $eid,
            ':attr_name' => $attr_name,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['attr_value'];
    }

    //获取用户参与的商家活动
    public function myPartakeList($uid, $page, $size, $is_close)
    {
        $start = ($page - 1) * $size;
        $stmt = $this->db->prepare("SELECT id,uid,eid,p_number,is_check,status FROM event_partake_info WHERE uid=:uid AND is_check =:is_close and
        STATUS < 2 and eid in(select id from event where status < 2) order by add_time desc limit :start,:size");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':is_close', $is_close, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //获取用户参与的商家活动总数
    public function myPartakeNum($uid)
    {
        $sql = "SELECT COUNT(DISTINCT(mt.id)) AS num FROM business_statistics AS ms
                LEFT JOIN event AS mt
                ON ms.eid=mt.id
                WHERE ms.uid =:uid AND mt.status <2 AND ms.status =0";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //删除商家活动
    public function delEvent($id, $uid, $sid)
    {
        $sql = "update event set status = 4, update_time = :update_time where id = :id ";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':id' => $id,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        $feedModel = new FeedModel();
        //$feedModel->delPartakeEvent($id);//删除用户参与信息
        $this->updateAppTopic($id);
        $commonModel = new CommonModel();
        $commonModel->updateRelationByObjId(10, $id, 4);//删除相对应的评论、喜欢、打赏等相关信息
        $feedModel->del($uid, 'event', $id);//删除动态信息
        $feedModel->delStage($sid, 'event', $id);//删除动态信息
        $sql = "update app_topic set status = 1, update_time = :update_time where tid = :id ";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':id' => $id,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $this->updateAppTopic($id);
        $this->updateEventNum($sid, -1);//更新驿站活动数
        Common::http(OPEN_DOMAIN . '/stageapi/modifyRedisNewTopicNum', array('sid' => $sid, 'uid' => $uid, 'num' => -1), 'POST');
        //$stageModel = new StageModel();
        //$stageModel->modifyRedisNewTopicNum($sid,-1);//删除redis中新帖子的数量
        return 1;
    }

    //将推荐的商家活动删除
    public function updateAppTopic($id)
    {
        $sql = "update app_topic set status = 0, update_time = :update_time where tid = :id and type = 1 ";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':id' => $id,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
    }

    //统计某个商家活动下的参与用户
    public function partakeListByEid($eid, $start, $size)
    {
        $sql = "SELECT * FROM event_partake_info WHERE eid=:eid AND STATUS < 2 order by add_time desc limit :start,:size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':eid', $eid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $userModel = new UserModel();
                $result[$k]['user_info'] = $userModel->getUserData($v['uid']);
            }
        }
        return $result;
    }

    //统计某个报名活动下的参与数
    public function partakeNumByEid($eid, $is_check = 0)
    {
        $is_check = $is_check ? 'and is_check=1' : '';
        $sql = "SELECT COUNT(*) AS num  FROM event_partake_info WHERE eid=:eid and status < 2 $is_check";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return 0;
        }
        return $result['num'];
    }

    //统计某个商家驿站下的所有活动下参与用户总数
    public function partakeNumBySid($sid, $is_check = 0)
    {
        $is_check = $is_check ? 'and is_check=1' : '';
        $sql = "SELECT COUNT(*) AS num  FROM event_partake_info WHERE eid in (select id from event where sid =:sid) and status < 2 $is_check";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return 0;
        }
        return $result['num'];
    }

    //获取活动内容分类信息
    public function getBusinessEventType($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM business_event_type WHERE id =:id ");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //用户参与某个活动的记录
    public function getVoteByUidAndEid($uid, $eid, $status = 0)
    {
        $status = $status ? 'and status < 2' : '';
        $stmt = $this->db->prepare("SELECT * FROM event_partake_info WHERE uid =:uid AND eid =:eid $status");
        $array = array(
            ':uid' => $uid,
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function getPartakeInfoById($id, $status = 0)
    {
        $status = $status ? 'and status < 2' : '';
        $stmt = $this->db->prepare("SELECT * FROM event_partake_info WHERE id=:id $status");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //修改活动关闭状态 id 活动id
    public function updateEventCloseType($id, $is_close)
    {
        $sql = "update event set is_close =:is_close, update_time = :update_time where id = :id ";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':is_close' => $is_close,
            ':update_time' => date('Y-m-d H:i:s'),
            ':id' => $id
        );
        $stmt->execute($array);
    }

    //根据发布时间查询最新的服务信息
    public function getListByAddtime($limit, $id)
    {
        $stmt = $this->db->prepare("SELECT id,title,type,cover FROM event WHERE STATUS = 1 and id !=:id order by add_time desc LIMIT :limit");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //根据发布时间查询最新的服务信息
    public function getListForHtml($limit, $id, $version, $token, $sid, $flag = 1)
    {
        if ($flag == 1) {
            $fields = 'and sid=:sid';
        } else {
            $fields = 'and sid!=:sid';
        }
        $stmt = $this->db->prepare("SELECT id FROM event WHERE id IN (SELECT eid FROM event_fields WHERE end_time>'" . date('Y-m-d H:i:s') . "' ) and id!=:id and status < 2 $fields ORDER BY RAND() LIMIT :limit");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $info = $this->getEventRedisById($v['id']);
                $result[$k]['title'] = $info['title'];
                $result[$k]['cover'] = Common::show_img($info['cover'], 4, 720, 540);
                if ($info['type'] == 1) {
                    $data = $this->getBusinessEventType($info['type_code']);//获取活动分类内容
                } else {
                    $data = Common::eventType($info['type']);
                }
                $result[$k]['type_name'] = $data['name'];
                $result[$k]['type'] = $info['type'];
                $result[$k]['url'] = $token ? I_DOMAIN . '/e/' . $v['id'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/e/' . $v['id'] . '?version=' . $version . '';
                $addressModel = new AddressModel();
                $province_name = $info['province'] ? $addressModel->getNameById($info['province']) : '';
                $city_name = $info['city'] ? $addressModel->getNameById($info['city']) : '';
                $result[$k]['address_name'] = $province_name . $city_name;
                $result[$k]['fields_list'] = $info['fields_info'];
                $result[$k]['price_type'] = $info['price_type'];
                $price_info = $this->getPrice($v['id']);
                $result[$k]['price_count'] = count($price_info);
                $result[$k]['min_price'] = isset($price_info[0]['unit_price']) ? $price_info[0]['unit_price'] : '0';
                if (count($price_info) > 1) {
                    $max_price = end($price_info);
                    $result[$k]['max_price'] = $max_price['unit_price'];
                }
            }
        }
        return $result;
    }

    //验票
    public function updateTickets($id)
    {
        $sql = "update event_partake_info set is_check =:is_check, update_time = :update_time where id=:id ";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':is_check' => 1,
            ':update_time' => date('Y-m-d H:i:s'),
            ':id' => $id
        );

        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        return 1;
    }

    //查询报名凭证
    public function getTickets($uid, $eid)
    {
        $stmt = $this->db->prepare("SELECT id FROM event_partake_info WHERE uid=:uid and eid = :eid ");
        $array = array(
            ':uid' => $uid,
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //添加报名选项
    public function addPartakeBind($eid, $arr)
    {
        foreach ($arr as $v) {
            $stmt = $this->db->prepare("insert into event_partake_bind (eid,oid,must,status) values (:eid,:oid,:must,:status)");
            $array = array(
                ':eid' => $eid,
                ':oid' => $v['id'],
                ':must' => $v['must'],
                ':status' => 0
            );
            $stmt->execute($array);
        }
    }

    //获取报名选项
    public function getPartakeOption()
    {
        $stmt = $this->db->prepare("SELECT id,name FROM event_partake_option WHERE type=0 and status = 1 ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //同一驿站验证标题
    public function titleIsExist($title, $sid, $id = '')
    {
        if (!$id) {
            $stmt = $this->db->prepare("select count(id) as num from event where replace(title,' ','')=replace(:title,' ','') and sid=:sid and status<2");
            $array = array(
                ':title' => $title,
                ':sid' => $sid,
            );
        } else {
            $stmt = $this->db->prepare("select count(id) as num from event where replace(title,' ','')=replace(:title,' ','') and sid=:sid and status<2 and id!=:id");
            $array = array(
                ':title' => $title,
                ':sid' => $sid,
                ':id' => $id
            );
        }
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);;
        return $result['num'];
    }

    //根据服务信息id 获取该信息下的报名选项
    public function getPartakeOptionByEid($eid)
    {
        $stmt = $this->db->prepare("SELECT eid,oid,must FROM event_partake_bind WHERE eid=:eid and status < 2 ");
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$result) {
            return array();
        }
        foreach ($result as $k => $v) {
            $result[$k]['name'] = $this->getPartakeOptionById($v['oid']);
        }
        return $result;
    }

    //获取服务信息某一个报名选项的信息
    public function getPartakeOptionByIdAndEid($eid, $oid)
    {
        $stmt = $this->db->prepare("SELECT eid,oid,must FROM event_partake_bind WHERE eid=:eid and oid =:oid ");
        $array = array(
            ':eid' => $eid,
            ':oid' => $oid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //根据id获取报名选项
    public function getPartakeOptionById($id)
    {
        $stmt = $this->db->prepare("SELECT name FROM event_partake_option WHERE id=:id and status = 1 ");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['name'];
    }

    //添加报名基本信息
    public function addPartakeInfo($eid, $uid, $f_id, $arr, $origin)
    {
        $stmt = $this->db->prepare("insert into event_partake_info (eid,uid,f_id,p_number,origin) values (:eid,:uid,:f_id,:p_number,:origin) on duplicate key update uid=:uid,f_id=:f_id,p_number=:p_number,origin=:origin,status=0");
        $array = array(
            ':eid' => $eid,
            ':uid' => $uid,
            ':f_id' => $f_id,
            ':p_number' => 'D' . time() . mt_rand(100, 999),
            ':origin' => $origin
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        $this->addPartake($id, $arr);
        $priceInfo = $this->getPriceByFid($f_id);
        if ($priceInfo[0]['max_partake'] != 0) {
            $this->updateStocknum($priceInfo[0]['id'], 1, 1);
        }
        $feedModel = new FeedModel();
        $feedModel->updatePartakeNum($eid);
        return $id;
    }

    //查询一个报名信息的报名填写项目
    function getCountEventPartake($p_info_id)
    {
        $stmt = $this->db->prepare('select id from event_partake where p_info_id=:p_info_id  order by oid asc');
        $array = array(
            ':p_info_id' => $p_info_id
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            $result['num'] = count($result);
        }
        return $result;
    }

    //添加报名选项
    public function addPartake($p_info_id, $arr)
    {
        $old_Partake_bind = $this->getCountEventPartake($p_info_id);//原本添加时加入的行数
        if (isset($old_Partake_bind['num']) && $old_Partake_bind['num']) {
            if ($arr) {
                $bind_count = count($arr);//新修改是传过来的行数
                if ($bind_count > $old_Partake_bind['num']) {//重新报名添加的信息大于原来的条数时
                    foreach ($arr as $key => $val) {
                        if ($key < $old_Partake_bind['num']) {
                            $stmt = $this->db->prepare("update event_partake set oid=:oid,content=:content,status=:status,update_time=:update_time
                                                            where  p_info_id=:p_info_id and id=:id ");
                            $array = array(
                                ':p_info_id' => $p_info_id,
                                ':id' => $old_Partake_bind[$key]['id'],
                                ':oid' => $val['oid'],
                                ':content' => $val['content'],
                                ':status' => '0',
                                ':update_time' => date('Y-m-d H:i:s'),
                            );
                            $stmt->execute($array);
                        } else {
                            $stmt = $this->db->prepare("insert into event_partake (p_info_id,oid,content) values (:p_info_id,:oid,:content)");
                            $array = array(
                                ':p_info_id' => $p_info_id,
                                ':oid' => $val['oid'],
                                ':content' => $val['content']
                            );
                            $stmt->execute($array);
                        }
                    }
                } else {//当修改的小于或等于原来的条数时
                    foreach ($arr as $k => $v) {
                        $stmt = $this->db->prepare("update event_partake set oid=:oid,content=:content,status=:status,update_time=:update_time
                                                            where  p_info_id=:p_info_id and id=:id");
                        $array = array(
                            ':p_info_id' => $p_info_id,
                            ':id' => $old_Partake_bind[$k]['id'],
                            ':oid' => $v['oid'],
                            ':content' => $v['content'],
                            ':status' => '0',
                            ':update_time' => date('Y-m-d H:i:s'),
                        );
                        $stmt->execute($array);
                    }
                    if ($bind_count < $old_Partake_bind['num']) {
                        $stmt = $this->db->prepare("update event_partake set status=:status,update_time=:update_time
                                                            where  p_info_id=:p_info_id and id >:ids");
                        $array = array(
                            ':p_info_id' => $p_info_id,
                            ':ids' => $old_Partake_bind[$bind_count - 1]['id'],
                            ':status' => '4',
                            ':update_time' => date('Y-m-d H:i:s'),
                        );
                        $stmt->execute($array);
                    }
                }
            }
        } else {
            foreach ($arr as $v) {
                $stmt = $this->db->prepare("insert into event_partake (p_info_id,oid,content) values (:p_info_id,:oid,:content)");
                $array = array(
                    ':p_info_id' => $p_info_id,
                    ':oid' => $v['oid'],
                    ':content' => $v['content']
                );
                $stmt->execute($array);
            }
        }
    }

    //获取某个选项id
    public function getOptionId($name)
    {
        $stmt = $this->db->prepare("SELECT id FROM event_partake_option WHERE name=:name ");
        $array = array(
            ':name' => $name
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['id'];
    }

    //用户报名的基本信息
    public function getPartakeInfo($f_id, $uid)
    {
        $stmt = $this->db->prepare("SELECT * FROM event_partake_info WHERE f_id=:f_id and uid =:uid ");
        $array = array(
            ':f_id' => $f_id,
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //用户报名的基本信息
    public function getPartakeInfoOld($eid, $uid)
    {
        $stmt = $this->db->prepare("SELECT * FROM event_partake_info WHERE eid=:eid and uid =:uid ");
        $array = array(
            ':eid' => $eid,
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //获取报名详细信息
    public function getPartake($p_info_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM event_partake WHERE p_info_id =:p_info_id and status < 2");
        $array = array(
            ':p_info_id' => $p_info_id
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    /*
     * 修改活动报名显示列表
     * */
    public function updatePartakeBind($eid, $event_partake_bind = array())
    {
        $old_bind = $this->getCountActivityApply($eid);//原本添加时加入的行数
        if ($event_partake_bind) {
            $bind_count = count($event_partake_bind);//新修改是传过来的行数
            if ($bind_count > $old_bind['num']) {//当修改的大于原来的条数时
                foreach ($event_partake_bind as $key => $val) {
                    if ($key < $old_bind['num']) {
                        $stmt = $this->db->prepare("update event_partake_bind set oid=:oid,must=:must,status=:status,update_time=:update_time
                                                        where  eid=:eid and id=:id ");
                        $array = array(
                            ':eid' => $eid,
                            ':id' => $old_bind[$key]['id'],
                            ':oid' => $val['id'],
                            ':must' => $val['must'],
                            ':status' => '0',
                            ':update_time' => date('Y-m-d H:i:s'),
                        );
                        $stmt->execute($array);
                    } else {
                        $stmt = $this->db->prepare("insert into event_partake_bind (eid,oid,must,add_time) values (:eid,:oid,:must,:add_time)");
                        $array = array(
                            ':eid' => $eid,
                            ':oid' => $val['id'],
                            ':must' => $val['must'],
                            ':add_time' => date('Y-m-d H:i:s'),
                        );
                        $stmt->execute($array);
                    }
                }
            } else {//当修改的小于或等于原来的条数时
                foreach ($event_partake_bind as $key => $val) {
                    $stmt = $this->db->prepare("update event_partake_bind set oid=:oid,must=:must,status=:status,update_time=:update_time
                                                        where  eid=:eid and id=:id ");
                    $array = array(
                        ':eid' => $eid,
                        ':id' => $old_bind[$key]['id'],
                        ':oid' => $val['id'],
                        ':must' => $val['must'],
                        ':status' => '0',
                        ':update_time' => date('Y-m-d H:i:s'),
                    );
                    $stmt->execute($array);
                }
                if ($bind_count < $old_bind['num']) {
                    $stmt = $this->db->prepare("update event_partake_bind set status=:status,update_time=:update_time
                                                            where  eid=:eid and id >:ids ");
                    $array = array(
                        ':eid' => $eid,
                        ':ids' => $old_bind[$bind_count - 1]['id'],
                        ':status' => '4',
                        ':update_time' => date('Y-m-d H:i:s'),
                    );
                    $stmt->execute($array);
                }
            }
        } else {
            $stmt = $this->db->prepare("update event_partake_bind set status=:status,update_time=:update_time where  eid=:eid ");
            $array = array(
                ':eid' => $eid,
                ':status' => '4',
                ':update_time' => date('Y-m-d H:i:s'),
            );
            $stmt->execute($array);
        }
    }

    /*
     * 修改活动场次
     */
    public function updateEventFields($eid, $priceInfoArr = array())
    {
        $old_Fields = $this->getCountFields($eid);//原本添加时加入的行数
        if ($priceInfoArr) {
            $fields_count = count($priceInfoArr);//新修改是传过来的行数
            if ($fields_count > $old_Fields['num']) {//当修改的大于原来的条数时
                foreach ($priceInfoArr as $key => $val) {
                    if ($key < $old_Fields['num']) {
                        $stmt = $this->db->prepare("update event_fields set start_time=:start_time,end_time=:end_time,partake_end_time=:partake_end_time,update_time=:update_time where  eid=:eid and id=:id ");
                        $array = array(
                            ':eid' => $eid,
                            ':id' => $old_Fields[$key]['id'],
                            ':start_time' => $val['start_time'],
                            ':end_time' => $val['end_time'],
                            ':partake_end_time' => $val['partake_end_time'],
                            ':update_time' => date('Y-m-d H:i:s'),
                        );
                        $stmt->execute($array);
                        $this->updateEventPrice($eid, $old_Fields[$key]['id'], $val['price_info']);
                    } else {
                        $stmt = $this->db->prepare("insert into event_fields (eid,start_time,end_time,partake_end_time) values (:eid,:start_time,:end_time,:partake_end_time)");
                        $array = array(
                            ':eid' => $eid,
                            ':start_time' => $val['start_time'],
                            ':end_time' => $val['end_time'],
                            ':partake_end_time' => $val['partake_end_time']
                        );
                        $stmt->execute($array);
                        $f_id = $this->db->lastInsertId();
                        $this->updateEventPrice($eid, $f_id, $val['price_info']);
                    }
                }
            } else {//当修改的小于或等于原来的条数时
                foreach ($priceInfoArr as $key => $val) {
                    $stmt = $this->db->prepare("update event_fields set start_time=:start_time,end_time=:end_time,partake_end_time=:partake_end_time,update_time=:update_time where  eid=:eid and id=:id ");
                    $array = array(
                        ':eid' => $eid,
                        ':id' => $old_Fields[$key]['id'],
                        ':start_time' => $val['start_time'],
                        ':end_time' => $val['end_time'],
                        ':partake_end_time' => $val['partake_end_time'],
                        ':update_time' => date('Y-m-d H:i:s'),
                    );
                    $stmt->execute($array);
                    $this->updateEventPrice($eid, $old_Fields[$key]['id'], $val['price_info']);
                }
                if ($fields_count < $old_Fields['num']) {
                    $stmt = $this->db->prepare("update event_fields set status=:status,update_time=:update_time where  eid=:eid and id >:ids ");
                    $array = array(
                        ':eid' => $eid,
                        ':ids' => $old_Fields[$fields_count - 1]['id'],
                        ':status' => '0',
                        ':update_time' => date('Y-m-d H:i:s'),
                    );
                    $stmt->execute($array);
                }
            }
        } else {
            $stmt = $this->db->prepare("update event_fields set status=:status,update_time=:update_time where  eid=:eid ");
            $array = array(
                ':eid' => $eid,
                ':status' => '0',
                ':update_time' => date('Y-m-d H:i:s'),
            );
            $stmt->execute($array);
            echo 5;
            exit;
        }
    }

    //修改某个场次的价格信息
    public function updateEventPrice($eid, $f_id, $data = array())
    {
        $old_price = $this->getCountPrice($f_id);//原本添加时加入的行数
        if ($data) {
            $price_count = count($data);//新修改是传过来的行数
            if ($price_count > $old_price['num']) {//当修改的大于原来的条数时
                foreach ($data as $key => $val) {
                    if ($key < $old_price['num']) {
                        $stmt = $this->db->prepare("update event_price set unit_price=:unit_price,mark=:mark,update_time=:update_time,num=:num,stock_num=:stock_num,status=0 where id=:id ");
                        $array = array(
                            ':id' => $old_price[$key]['id'],
                            ':unit_price' => $val['price'],
                            ':mark' => $val['price_mark'],
                            ':num' => $old_price[$key]['num'] + $val['max_partake'] - $old_price[$key]['stock_num'],
                            ':stock_num' => $val['max_partake'],
                            ':update_time' => date('Y-m-d H:i:s')
                        );
                        $stmt->execute($array);
                    } else {
                        $stmt = $this->db->prepare("insert into event_price (eid,f_id,unit_price,mark,num,stock_num) values (:eid,:f_id,:unit_price,:mark,:num,:stock_num)");
                        $array = array(
                            ':eid' => $eid,
                            ':f_id' => $f_id,
                            ':unit_price' => $val['price'],
                            ':mark' => $val['price_mark'],
                            ':num' => $val['max_partake'],
                            ':stock_num' => $val['max_partake']
                        );
                        $stmt->execute($array);
                    }
                }
            } else {//当修改的小于或等于原来的条数时
                foreach ($data as $key => $val) {
                    $stmt = $this->db->prepare("update event_price set unit_price=:unit_price,mark=:mark,update_time=:update_time,num=:num,stock_num=:stock_num,status=0 where id=:id ");
                    $array = array(
                        ':id' => $old_price[$key]['id'],
                        ':unit_price' => $val['price'],
                        ':mark' => $val['price_mark'],
                        ':num' => $old_price[$key]['num'] + $val['max_partake'] - $old_price[$key]['stock_num'],
                        ':stock_num' => $val['max_partake'],
                        ':update_time' => date('Y-m-d H:i:s'),
                    );
                    $stmt->execute($array);
                }
                if ($price_count < $old_price['num']) {
                    $stmt = $this->db->prepare("update event_price set status=:status,update_time=:update_time,update_time=:update_time where  eid=:eid and id >:ids ");
                    $array = array(
                        ':eid' => $eid,
                        ':ids' => $old_price[$price_count - 1]['id'],
                        ':status' => '4',
                        ':update_time' => date('Y-m-d H:i:s'),
                    );
                    $stmt->execute($array);
                }
            }
        } else {
            $stmt = $this->db->prepare("update event_price set status=:status,update_time=:update_time where  f_id=:f_id ");
            $array = array(
                ':f_id' => $f_id,
                ':status' => '0',
                ':update_time' => date('Y-m-d H:i:s'),
            );
            $stmt->execute($array);
        }
    }

    public function getCountPrice($f_id)
    {
        $stmt = $this->db->prepare('select id,num,stock_num from event_price where f_id=:f_id order by id asc');
        $array = array(
            ':f_id' => $f_id
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            $result['num'] = count($result);
        } else {
            $result['num'] = 0;
        }
        return $result;
    }

    //获取报名显示列表
    public function getCountActivityApply($eid)
    {
        $stmt = $this->db->prepare('select id from event_partake_bind where eid=:eid  order by id asc');
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            $result['num'] = count($result);
        } else {
            $result['num'] = 0;
        }
        return $result;
    }

    //报名列表
    public function partakeList($eid, $last_id = 0, $size)
    {
        $fields = $last_id ? 'and id <' . $last_id : '';
        $sql = "SELECT id,uid,is_check,add_time FROM event_partake_info WHERE eid=:eid AND STATUS < 2 $fields order by add_time desc limit :size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':eid', $eid, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $userModel = new UserModel();
                $result[$k]['add_time'] = Common::app_show_time($v['add_time']);
                $result[$k]['user_info'] = $userModel->getUserData($v['uid']);
                $result[$k]['partake_info'] = $this->getPartake($v['id']);
                if ($result[$k]['partake_info']) {
                    foreach ($result[$k]['partake_info'] as $k1 => $v1) {
                        $result[$k]['partake_info'][$k1]['content'] = isset($v1['content']) ? $v1['content'] : '';
                        $result[$k]['partake_info'][$k1]['name'] = $this->getPartakeOptionById($v1['oid']);
                        if ($result[$k]['partake_info'][$k1]['name'] == '手机号') {
                            $result[$k]['mobile'] = $result[$k]['partake_info'][$k1]['content'];
                        }
                        if ($result[$k]['partake_info'][$k1]['name'] == '姓名') {
                            $result[$k]['real_name'] = $result[$k]['partake_info'][$k1]['content'];
                        }
                    }
                }
                $result[$k]['total'] = $this->partakeNumByEid($eid);
                $result[$k]['updateTickets'] = $this->partakeNumByEid($eid, 1);
            }
        }
        return $result;
    }

    public function getPartakeListByUid($uid, $is_check, $page, $size, $version, $token)
    {
        if ($is_check != 2) {
            $fields = 'AND is_check =:is_check';
        } else {
            $fields = '';
        }
        $start = ($page - 1) * $size;
        $sql = "SELECT * FROM event_partake_info WHERE uid=:uid $fields  and  STATUS < 2 and eid in(select id from event ) order by add_time desc limit :start,:size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        if ($is_check != 2) {
            $stmt->bindValue(':is_check', $is_check, PDO::PARAM_INT);
        }
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        if ($result) {
            foreach ($result as $k => $v) {
                $eventInfo = $this->getEvent($v['eid'], 1);
                $fields_info = $this->getFieldsInfo($v['f_id']);
                $list[$k]['id'] = $eventInfo['id'];
                $list[$k]['f_id'] = $v['f_id'];
                $list[$k]['title'] = $eventInfo['title'];
                $list[$k]['event_address'] = $eventInfo['event_address'];
                $list[$k]['lat'] = $eventInfo['lat'];
                $list[$k]['lng'] = $eventInfo['lng'];
                $list[$k]['start_time'] = $fields_info['start_time'];
                $list[$k]['type'] = $eventInfo['type'];
                $list[$k]['is_check'] = $v['is_check'];
                $list[$k]['url'] = I_DOMAIN . '/e/' . $v['eid'] . '?token=' . $token . '&version=' . $version;
                if (date('Y-m-d H:i', time()) >= $fields_info['end_time']) {
                    $list[$k]['is_use'] = 0;
                } else {
                    $list[$k]['is_use'] = 1;
                }
                if ($eventInfo['type'] == 1) {
                    $data = $this->getBusinessEventType($eventInfo['type_code']);//获取活动分类内容
                } else {
                    $data = Common::eventType($eventInfo['type']);
                }
                $list[$k]['type_name'] = $data['name'];
                $list[$k]['code_name'] = $data['code'];
            }
        }
        return $list;
    }

    //服务信息筛选
    public function getListByCondition($type = '', $id = '', $city = '', $sort = '', $page, $size, $token = '', $version)
    {
        $start = ($page - 1) * $size;
        $conditionSort = 'order by id desc';
        $conditionType = $type ? ' and type=:type' : '';
        $conditionId = $id ? ' and type_code=:id' : '';
        $conditionCity = $city ? ' and (province=:city or city=:city)' : '';
        if (!$sort || $sort == '最新') {
            $conditionSort = ' order by add_time desc';
        } elseif ($sort == '热门') {
            $conditionSort = ' order by partake_num desc';
        }
        if (!$sort) {
            $sql = "SELECT id as eid,
                         CASE
                            WHEN DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i') < DATE_FORMAT(end_time,'%Y-%m-%d %H:%i') THEN 3
                            WHEN DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i') >=DATE_FORMAT(end_time,'%Y-%m-%d %H:%i') THEN 1
                         END AS event_order
                FROM event WHERE status<2 AND start_time IS NOT NULL AND end_time IS NOT NULL $conditionType $conditionId $conditionCity
                ORDER BY event_order DESC,id DESC limit :start,:size";
        } else {
            $sql = "select id as eid from event where status < 2 AND start_time IS NOT NULL AND end_time IS NOT NULL $conditionType $conditionId $conditionCity $conditionSort limit :start,:size";

        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        if ($type) {
            $stmt->bindValue(':type', $type, PDO::PARAM_INT);
        }
        if ($id) {
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        }
        if ($city) {
            $stmt->bindValue(':city', $city, PDO::PARAM_INT);
        }
        if ($sort == '已结束') {
            $stmt->bindValue(':end_time', date('Y-m-d H:i', time()), PDO::PARAM_STR);
        }
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        $time = date('Y-m-d H:i:s', time());
        if ($result) {
            foreach ($result as $k => $v) {
                $eventInfo = $this->getEventRedisById($v['eid']);
                $list[$k]['id'] = $v['eid'];
                $list[$k]['title'] = $eventInfo['title'];
                $list[$k]['cover'] = Common::show_img($eventInfo['cover'], 4, 360, 270);
                $list[$k]['max_partake'] = $eventInfo['max_partake'] ? $eventInfo['max_partake'] : '0';
                $list[$k]['type'] = $eventInfo['type'];
                $list[$k]['lng'] = $eventInfo['lng'];
                $list[$k]['lat'] = $eventInfo['lat'];
                $addressModel = new AddressModel();
                $province_name = $addressModel->getNameById($eventInfo['province']);
                $city_name = $addressModel->getNameById($eventInfo['city']);
                $town_name = $addressModel->getNameById($eventInfo['town']);
                if ($province_name == $city_name) {
                    $address_name = $city_name . $town_name;
                } else {
                    $address_name = $province_name . $city_name;
                }
                $list[$k]['event_address'] = $address_name;
                $e_time = $this->getEndTime($v['eid']);//結束时间
                if ($e_time) {
                    if ($e_time[0]['end_time'] <= $time) {
                        //当前时间小于活动结束时间
                        $list[$k]['start_type'] = 3;//活动结束
                    } else {
                        $list[$k]['start_type'] = 2;//可以报名
                    }
                }
                $list[$k]['url'] = $token ? I_DOMAIN . '/e/' . $v['eid'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/e/' . $v['eid'] . '?version=' . $version . '';
                if ($eventInfo['type'] == 1) {
                    $data = $this->getBusinessEventType($eventInfo['type_code']);//获取活动分类内容
                } else {
                    $data = Common::eventType($eventInfo['type']);
                }
                $list[$k]['type_name'] = $data['name'];
                $list[$k]['code_name'] = $data['code'];
                $list[$k]['show_time'] = date('m.d', strtotime($eventInfo['start_time'])) . '-' . date('m.d', strtotime($eventInfo['end_time']));
                $list[$k]['show_start_time'] = Common::getEventStartTime($v['eid']);
                if ($eventInfo['price_type'] == 1) {
                    $list[$k]['price'] = '免费';
                    $list[$k]['price_count'] = 1;
                } else {
                    $min_price = isset($eventInfo['price_list'][0]) ? $eventInfo['price_list'][0]['unit_price'] : "";
                    $list[$k]['price'] = $min_price;
                    $list[$k]['price_count'] = count($eventInfo['price_list']);
                }
            }
        }
        return $list;
    }

    //把发布服务时间存储到redis-服务于添加浏览数基数
    public function addEventViewTime($tid)
    {
        $key = "event:view:time";
        $this->redis->hSet($key, $tid, time());
    }

    //将服务信息的价格储存到表中
    public function addEventPrice($arr, $eid, $field_id)
    {
        foreach ($arr as $v) {
            $stmt = $this->db->prepare("insert into event_price (eid,f_id,unit_price,mark,num,stock_num) values (:eid,:f_id,:unit_price,:mark,:num,:stock_num)");
            $array = array(
                ':eid' => $eid,
                ':f_id' => $field_id,
                ':unit_price' => $v['price'],
                ':mark' => $v['price_mark'] ? $v['price_mark'] : '票价',
                ':num' => $v['max_partake'],
                ':stock_num' => $v['max_partake'],
            );
            $stmt->execute($array);
        }
    }

    //获取某个服务信息的价格
    public function getPrice($eid)
    {
        $stmt = $this->db->prepare("SELECT id,eid,unit_price,mark,num,stock_num FROM event_price WHERE eid=:eid and status < 2 order by (unit_price+0) asc ");
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //根据uid eid 验证用户购票数
    public function getNumByUidAndEid($uid, $eid)
    {
        $stmt = $this->db->prepare("SELECT num FROM event_orders WHERE uid = :uid AND eid = :eid AND order_status < 2  AND STATUS < 2 ");
        $array = array(
            ':uid' => $uid,
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetchALL(PDO::FETCH_ASSOC);
        $num = 0;
        if ($result) {
            foreach ($result as $v) {
                $num += $v['num'];
            }
        }
        return $num;
    }

    //根据eid和单价 查找库存
    public function getStockNum($price_id)
    {
        $stmt = $this->db->prepare("SELECT stock_num FROM event_price WHERE id =:id ");
        $array = array(
            ':id' => $price_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['stock_num'];
    }

    //根据类型查询上一个订单号
    public function getLastOrderIdByType($type)
    {
        $stmt = $this->db->prepare("SELECT order_id FROM event_orders WHERE e_type =:type ORDER BY add_time DESC LIMIT 1");
        $array = array(
            ':type' => $type
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['order_id'];
    }

    //添加订单数据
    public function addOrder($eid, $sid, $type, $order_id, $uid, $phone, $unit_price, $num, $is_agio, $agio_info, $agio_price, $totals, $time, $f_id, $sp)
    {
        $sp_id = '';
        $commission = $service_charge = $fact_totals = 0;
        if ($sp) {
            $sp_str = base64_decode(base64_decode($sp));
            $sp_arr = explode('-', $sp_str);
            $sp_id = $sp_arr[1];
            $stageModel = new StageModel();
            $sp_info = $stageModel->getCommissionRateById($sp_id);
            if ($sp_info && $sp_info['uid'] != $uid) {
                $commission = round($totals * $sp_info['commission_rate'], 2);
            } else {
                $sp_id = '';
            }
        }
        $service_charge = round($totals*0.03,2);
        $fact_totals = $totals -$commission-$service_charge;
        $stmt = $this->db->prepare("insert into event_orders (eid,sid,f_id,e_type,order_id,uid,phone,unit_price,num,un_use,is_agio,agio_info,agio_price,totals,order_status,sp_id,commission,service_charge,fact_totals,add_time,update_time) values (:eid,:sid,:f_id,:e_type,:order_id,:uid,:phone,:unit_price,:num,:un_use,:is_agio,:agio_info,:agio_price,:totals,:order_status,:sp_id,:commission,:service_charge,:fact_totals,:add_time,:update_time)");
        $array = array(
            ':eid' => $eid,
            ':sid' => $sid,
            ':f_id' => $f_id,
            ':e_type' => $type,
            ':order_id' => $order_id,
            ':uid' => $uid,
            ':phone' => $phone,
            ':unit_price' => $unit_price,
            ':num' => $num,
            ':un_use' => $num,
            ':is_agio' => $is_agio,
            ':agio_info' => $agio_info,
            ':agio_price' => $agio_price,
            ':totals' => $totals,
            ':order_status' => 1,
            ':sp_id' => $sp_id,
            ':commission' => $commission,
            ':service_charge'=>$service_charge,
            ':fact_totals'=>$fact_totals,
            ':add_time' => $time,
            ':update_time' => $time
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        if(time()>strtotime(SERIAL_TIME)){
            if ($id && $sp && $commission) {
                $userModel = new UserModel();
                //分享推广入用户流水
                $userModel->addUserSerial($id,5,$sp_info['uid']);
                //用户未到账收益添加
                $userModel->addUnUseMoneyByUid($commission, $sp_info['uid']);
            }
        }
        return $id;
    }

    //我买到的列表
    public function getMyOrderList($uid, $order_status, $page, $size, $is_check)
    {
        if (!$is_check && $order_status == 2) {
            $time = date('Y-m-d H:i:s', time());
            $fields_check = 'and is_check=0 and un_use>0 and eid in(select eid from event_fields where end_time >"' . $time . '")';
        } else {
            $fields_check = '';
        }
        $start = ($page - 1) * $size;
        $fields = $order_status ? 'and order_status =' . $order_status . '' : 'and order_status in(1,2,5)';
        $sql = "select id,eid,f_id,order_id,unit_price,agio_price,num,un_use,totals,is_check,order_status,add_time from event_orders where uid = :uid and status in(0,1,5) $fields $fields_check order by add_time desc limit :start,:size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $data = $this->getEventRedisById($v['eid']);
                $fieldsInfo = $this->getFieldsInfo($v['f_id']);
                $result[$k]['title'] = $data['title'];
                $result[$k]['cover'] = IMG_DOMAIN . $data['cover'];
                $result[$k]['end_time'] = $fieldsInfo['end_time'] ? $fieldsInfo['end_time'] : '';
                if ($data['type'] != 1) {
                    $type_name = Common::eventType($data['type']);
                } else {
                    $type_name = $this->getBusinessEventType($data['type_code']);
                }
                $result[$k]['type_name'] = $type_name['name'];
                $result[$k]['code_name'] = $type_name['code'];
                $qrcode = $this->getQrcode($v['id']);
                if (count($qrcode) != $v['num']) {
                    $code_num = 1;
                } else {
                    $code_num = $v['num'];
                }

                $no_is_checkd = $v['un_use'];
                $is_checkd = $v['num'] - $v['un_use'];
                if (date('Y-m-d H:i:s', time()) < $fieldsInfo['end_time'] && $no_is_checkd > 0) {//活动未结束，有一张未使用 显示未使用
                    $result[$k]['is_check'] = 0;
                } elseif (date('Y-m-d H:i:s', time()) < $fieldsInfo['end_time'] && $no_is_checkd == 0) {//活动未结束，全部已使用，显示已使用
                    $result[$k]['is_check'] = 1;
                } elseif (date('Y-m-d H:i:s', time()) > $fieldsInfo['end_time'] && $no_is_checkd == $code_num) {//活动已结束，全部未使用，显示已过期
                    $result[$k]['is_check'] = 2;
                } elseif (date('Y-m-d H:i:s', time()) > $fieldsInfo['end_time'] && $is_checkd > 0) {//活动已结束，有一张已使用，显示已使用
                    $result[$k]['is_check'] = 1;
                }
                $result[$k]['check_num'] = $this->getCheckNum($v['id']) ? $this->getCheckNum($v['id']) : 0;
            }
        }
        return $result;
    }

    //我卖出的列表
    public function getSellOrderList($sid, $order_status, $page, $size, $is_check)
    {
        if (!$is_check && $order_status == 2) {
            $time = date('Y-m-d H:i:s', time());
            $fields_check = 'and is_check=0 and eid in(select eid from event_fields where end_time >"' . $time . '")';
        } else {
            $fields_check = '';
        }
        $start = ($page - 1) * $size;
        $fields = $order_status ? 'and order_status =' . $order_status . '' : 'and order_status in (1,2,5)';
        $sql = "select id,eid,f_id,order_id,uid,unit_price,agio_price,num,un_use,totals,order_status,is_check,refund_time,add_time from event_orders where sid = :sid and status in(0,1,4) $fields $fields_check order by add_time desc limit :start,:size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $data = $this->getEventRedisById($v['eid']);
                $fieldsInfo = $this->getFieldsInfo($v['f_id']);
                $result[$k]['title'] = $data['title'];
                $result[$k]['refund_time'] = $v['refund_time'] ? date('Y-m-d H:i', strtotime($v['refund_time'])) : '';
                $result[$k]['cover'] = IMG_DOMAIN . $data['cover'];
                $result[$k]['end_time'] = $fieldsInfo['end_time'] ? $fieldsInfo['end_time'] : '';
                $result[$k]['add_time'] = date('Y-m-d H:i', strtotime($v['add_time']));
                if ($data['type'] != 1) {
                    $type_name = Common::eventType($data['type']);
                } else {
                    $type_name = $this->getBusinessEventType($data['type_code']);
                }
                $result[$k]['type_name'] = $type_name['name'];
                $result[$k]['code_name'] = $type_name['code'];
                $qrcode = $this->getQrcode($v['id']);
                if (count($qrcode) != $v['num']) {
                    $code_num = 1;
                } else {
                    $code_num = $v['num'];
                }

                $no_is_checkd = $v['un_use'];
                $is_checkd = $v['num'] - $v['un_use'];
                if (date('Y-m-d H:i:s', time()) < $fieldsInfo['end_time'] && $no_is_checkd > 0) {//活动未结束，有一张未使用 显示未使用
                    $result[$k]['is_check'] = 0;
                } elseif (date('Y-m-d H:i:s', time()) < $fieldsInfo['end_time'] && $no_is_checkd == 0) {//活动未结束，全部已使用，显示已使用
                    $result[$k]['is_check'] = 1;
                } elseif (date('Y-m-d H:i:s', time()) > $fieldsInfo['end_time'] && $no_is_checkd == $code_num) {//活动已结束，全部未使用，显示已过期
                    $result[$k]['is_check'] = 2;
                } elseif (date('Y-m-d H:i:s', time()) > $fieldsInfo['end_time'] && $is_checkd > 0) {//活动已结束，有一张已使用，显示已使用
                    $result[$k]['is_check'] = 1;
                }
                $userModel = new UserModel();
                $userInfo = $userModel->getUserData($v['uid']);
                $result[$k]['nick_name'] = $userInfo['nick_name'];
                $result[$k]['check_num'] = $this->getCheckNum($v['id']) ? $this->getCheckNum($v['id']) : 0;
            }
        }
        return $result;
    }

    //订单详情
    public function orderInfo($id, $token, $version)
    {
        $stmt = $this->db->prepare("SELECT id,eid,order_id,f_id,uid,phone,unit_price,num,order_status,is_check,reason,agio_info,agio_price,totals,pay_type,pay_time,refund_time,add_time FROM event_orders WHERE id =:id  ");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return false;
        }
        $eventInfo = $this->getEventRedisById($result['eid']);
        $fieldsInfo = $this->getFieldsInfo($result['f_id']);
        $userModel = new UserModel();
        $stageModel = new StageModel();
        $stageInfo = $stageModel->getBasicStageBySid($eventInfo['sid']);
        $userInfo = $userModel->getUserData($result['uid']);
        $result['nick_name'] = $userInfo['nick_name'];
        $qrcode = $this->getQrcode($result['id']);
        foreach ($qrcode as $k => $v) {
            if ($result['order_status'] == 2 && date('Y-m-d H:i:s', time()) > $fieldsInfo['end_time'] && $v['is_check'] == 0) {
                $qrcode[$k]['is_check'] = 2;
            }
        }
        if ($version < '3.7') {
            if ($result['order_status'] == 2) {
                $result['qrcodeImg'] = $qrcode[0]['qrcodeImg'];
                $result['p_number'] = $qrcode[0]['p_number'];
                $result['is_check'] = $qrcode[0]['is_check'];
            } else {
                $result['qrcodeImg'] = '';
            }
        } else {
            if ($result['order_status'] == 2) {
                $result['qrcode'] = $qrcode;
            } else {
                $result['qrcode'] = array();
            }
        }
        $result['buy_uid'] = $result['uid'];
        $result['sell_uid'] = $eventInfo['uid'];
        $result['custom_tel'] = '13012888193';
        $result['check_num'] = $this->getCheckNum($result['id']);
        $result['end_num'] = $this->getEndNum($result['id']);
        $result['custom_tel'] = '13012888193';
        $result['sell_tel'] = $stageInfo['businessInfo']['tel'];
        $result['buy_tel'] = $result['phone'];
        if ($eventInfo['type'] != 1) {
            $type_name = Common::eventType($eventInfo['type']);
        } else {
            $type_name = $this->getBusinessEventType($eventInfo['type_code']);
        }
        $result['type_name'] = $type_name['name'];
        $result['code_name'] = $type_name['code'];
        if ($result['order_status'] == 2 && date('Y-m-d H:i:s', time()) > $fieldsInfo['end_time']) {
            $result['is_check'] = 2;
        }
        $result['agio_price'] = $result['agio_price'] ? $result['agio_price'] : '';
        $result['title'] = $eventInfo['title'];
        $result['cover'] = IMG_DOMAIN . $eventInfo['cover'];
        $result['event_address'] = $eventInfo['event_address'];
        $result['start_time'] = $fieldsInfo['start_time'];
        $result['end_time'] = $fieldsInfo['end_time'];
        $result['url'] = I_DOMAIN . '/e/' . $result['eid'] . '?token=' . $token . '&version=' . $version;
        $result['pay_type'] = $result['pay_type'] ? $result['pay_type'] : '';
        $result['refund_time'] = $result['refund_time'] ? $result['refund_time'] : '';
        $result['reason'] = $result['reason'] ? $result['reason'] : '其他原因';
        $result['stage_name'] = $stageInfo['name'];
        if (date('Y-m-d', strtotime($fieldsInfo['start_time'])) == date('Y-m-d', strtotime($fieldsInfo['end_time']))) {
            $result['show_time'] = date('m-d H:i', strtotime($fieldsInfo['start_time'])) . '至' . date('H:i', strtotime($fieldsInfo['end_time'])) . '';
        } else {
            $result['show_time'] = date('m-d H:i', strtotime($fieldsInfo['start_time'])) . '至' . date('m-d H:i', strtotime($fieldsInfo['end_time'])) . '';
        }
        return $result;
    }

    public function orderInfoById($id, $status = '')
    {
        $fields = $status ? '' : ' and status < 2';
        $stmt = $this->db->prepare("SELECT * FROM event_orders WHERE id =:id $fields");
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

    //服务详情h5转下单监听
    public function getInfoForHtml($id)
    {
        $result = $this->getEventRedisById($id);
        $result['price'] = $result['price_list'];
        $result['agio'] = $result['agio'] ? $result['agio'] : '';
        return $result;
    }

    //驿站下的报名列表
    public function partakeListBySid($sid, $last_id = 0, $size, $token, $version)
    {
        $fields = $last_id ? 'and id <' . $last_id : '';
        $sql = "SELECT id,uid,eid,is_check,add_time FROM event_partake_info WHERE eid in (select id from event where sid =:sid) AND STATUS < 2 $fields order by add_time desc limit :size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $userModel = new UserModel();
                $eventInfo = $this->getEventRedisById($v['eid']);
                $result[$k]['title'] = $eventInfo['title'];
                $result[$k]['url'] = I_DOMAIN . '/e/' . $v['eid'] . '?token=' . $token . '&version=' . $version . '';
                $result[$k]['add_time'] = Common::app_show_time($v['add_time']);
                $result[$k]['user_info'] = $userModel->getUserData($v['uid']);
                $result[$k]['partake_info'] = $this->getPartake($v['id']);
                if ($result[$k]['partake_info']) {
                    foreach ($result[$k]['partake_info'] as $k1 => $v1) {
                        $result[$k]['partake_info'][$k1]['content'] = isset($v1['content']) ? $v1['content'] : '';
                        $result[$k]['partake_info'][$k1]['name'] = $this->getPartakeOptionById($v1['oid']);
                        if ($result[$k]['partake_info'][$k1]['name'] == '手机号') {
                            $result[$k]['mobile'] = $result[$k]['partake_info'][$k1]['content'];
                        }
                        if ($result[$k]['partake_info'][$k1]['name'] == '姓名') {
                            $result[$k]['real_name'] = $result[$k]['partake_info'][$k1]['content'];
                        }
                    }
                }
                $result[$k]['total'] = $this->partakeNumBySid($sid);
                $result[$k]['updateTickets'] = $this->partakeNumBySid($sid, 1);
            }
        }
        return $result;
    }

    //根据order_id 获取订单详情
    public function orderInfoByOrderId($order_id)
    {
        $stmt = $this->db->prepare("SELECT id,eid,order_id,uid,phone,unit_price,num,order_status,reason,agio_price,totals,pay_type,p_number,qrcodeImg,is_check,pay_time,refund_time,add_time FROM event_orders WHERE order_id =:order_id and status < 2 ");
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

    //修改订单支付状态,进入明细
    public function updateOrder($order_id, $type, $seller_id, $order_status, $pay_time){
        $stmt = $this->db->prepare("update event_orders set seller_id=:seller_id,pay_type=:type,order_status=:order_status,pay_time=:pay_time where order_id=:order_id ");
        $array = array(
            ':seller_id' => $seller_id,
            ':type' => $type,
            ':order_status' => $order_status,
            ':pay_time' => $pay_time ? $pay_time : NULL,
            ':order_id' => $order_id
        );
        $stmt->execute($array);
        if(time()>strtotime(SERIAL_TIME)&&$order_status==2){
            $userModel = new UserModel();
            $order_info = $this->getInfoByOrderId($order_id);
            $event_info = $this->getEventRedisById($order_info['eid']);
            //商家入明细
            $userModel->addUserSerial($order_info['id'],3,$event_info['uid']);
            //商家用户钱袋增加
            $userModel->addMoneyBagByUid($order_info['fact_totals'],$event_info['uid']);
            if($order_info['sp_id']){
                $sp_info = $userModel->getSpInfoById($order_info['sp_id']);
                //用户未到账减去
                $userModel->addUnUseMoneyByUid($order_info['commission'],$sp_info['uid'],'-');
                //用户钱袋增加
                $userModel->addMoneyBagByUid($order_info['commission'],$sp_info['uid']);
            }
        }
    }

    //获取某个订单的支付状态
    public function getOrderStatusByOrderId($order_id)
    {
        $stmt = $this->db->prepare("SELECT order_status FROM event_orders WHERE order_id =:order_id");
        $array = array(
            ':order_id' => $order_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['order_status'];
    }

    //获取订单表中某种状态的所有订单
    public function getOrderList($pay_type = '', $order_status)
    {
        $fields = $pay_type ? 'and pay_type=' . $pay_type . '' : '';
        $stmt = $this->db->prepare("SELECT id,order_id FROM event_orders WHERE order_status =:order_status $fields and status< 2");
        $array = array(
            ':order_status' => $order_status
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //脚本：查询微信平台订单状态 并更新至数据库
    public function initWxOrder()
    {
        $time = time();
        $arr = $this->getOrderList('', 1);
        if ($arr) {
            foreach ($arr as $v) {
                $wx_order = $this->getWxOrderInfo($v['order_id'], $time);
                if (isset($wx_order['trade_state']) && $wx_order['trade_state'] == 'SUCCESS') {
                    $this->updateOrder($v['order_id'], 2, WX_MCHID, 2, date('Y-m-d H:i:s', $time));
                }
                if (isset($wx_order['trade_state']) && $wx_order['trade_state'] == 'NOTPAY') {
                    $this->updateOrder($v['order_id'], 2, WX_MCHID, 1, '');
                }
            }
            //发送短信，系统通知
        }
        $stagegoodsModel = new StagegoodsModel();
        $goods_arr = $stagegoodsModel->getOrderList('', 1);
        if ($goods_arr) {
            foreach ($goods_arr as $v1) {
                $goods_wx_order = $this->getWxOrderInfo($v1['order_id'], $time);
                if (isset($goods_wx_order['trade_state']) && $goods_wx_order['trade_state'] == 'SUCCESS') {
                    $stagegoodsModel->updateOrder($v1['order_id'],2, WX_MCHID,2, date('Y-m-d H:i:s', $time));
                    //付款成功通知卖家发货
                    $orderInfo = $stagegoodsModel->orderInfoByOrderId($v1['order_id']);
                    $goods_info = $stagegoodsModel->getInfo($orderInfo['goods_id']);
                    if ($orderInfo['uid'] != $goods_info['uid']) {
                        Common::addNoticeAndSmsForGoods(1, $orderInfo['order_id']);
                    }
                }
                if (isset($goods_wx_order['trade_state']) && $goods_wx_order['trade_state'] == 'NOTPAY') {
                    $stagegoodsModel->updateOrder($v1['order_id'], 2, WX_MCHID, 1, '');
                }
            }
        }
    }

    public function addEventQrCode($id)
    {
        $stmt = $this->db->prepare("SELECT id,order_id,eid,uid,num FROM event_orders WHERE id NOT IN (SELECT o_id FROM event_orders_qrcode) AND order_status = 2 AND STATUS<2 and id=:id");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $add_time = date('Y-m-d H:i:s');
            for ($i = 1; $i <= $result['num']; $i++) {
                $p_number = mt_rand(1000, 9999) . ' ' . mt_rand(1000, 9999) . ' ' . mt_rand(1000, 9999) . ' ' . mt_rand(1000, 9999);
                $this->addPnumber($result['id'], $p_number, $add_time);
            }
            $eventInfo = $this->getEvent($result['eid']);
            if ($eventInfo['uid'] != $result['uid']) {
                Common::addNoticeAndSmsForEvent(1, $result['order_id'], '');
            }
        }
    }

    public function is_qrcode($o_id)
    {
        $stmt = $this->db->prepare("SELECT count(*) as num FROM event_orders_qrcode WHERE o_id =:o_id");
        $array = array(
            ':o_id' => $o_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];

    }

    //脚本：查询微信平台退款订单状态 并更新至数据库
    public function initWxOrderRefund()
    {
        $arr = $this->getOrderList(2, 4);
        if (!$arr) {
            return false;
        }
        foreach ($arr as $v) {
            $time = time();
            $wx_order = $this->getWxOrderInfo($v['order_id'], $time);
            if ($wx_order['trade_state'] == 'REFUND') {
                $this->updateOrderStatus(3, $v['order_id']);
            }
        }
    }

    //更新订单支付状态
    public function updateOrderStatus($order_status, $order_id)
    {
        $stmt = $this->db->prepare("update event_orders set order_status=:order_status where order_id=:order_id ");
        $array = array(
            ':order_status' => $order_status,
            ':order_id' => $order_id
        );
        $stmt->execute($array);
    }

    public function getWxOrderInfo($order_id, $time)
    {
        $noncestr = strtoupper(md5($time));
        $str = 'appid=' . WX_APPID . '&mch_id=' . WX_MCHID . '&nonce_str=' . $noncestr . '&out_trade_no=' . $order_id . '&key=' . WX_KEY . '';
        $data = array(
            'appid' => WX_APPID,
            'mch_id' => WX_MCHID,
            'nonce_str' => $noncestr,
            'out_trade_no' => $order_id,
            'sign' => strtoupper(md5($str))
        );
        return Common::getWxOrder($data);
    }

    //修改订单退款状态
    public function updateOrderRefund($order_id, $order_status, $refund_time, $reason)
    {
        $stmt = $this->db->prepare("update event_orders set order_status=:order_status,refund_time=:refund_time,reason=:reason where order_id=:order_id ");
        $array = array(
            ':order_status' => $order_status,
            ':refund_time' => $refund_time,
            ':reason' => $reason,
            ':order_id' => $order_id
        );
        $stmt->execute($array);
    }

    public function updatOrderQRCodeImg($qrcodeImg, $id)
    {
        $stmt = $this->db->prepare("update event_orders set qrcodeImg=:qrcodeImg where id=:id ");
        $array = array(
            ':qrcodeImg' => $qrcodeImg,
            ':id' => $id
        );
        $stmt->execute($array);
    }

    //修改订单二维码
    public function updatOrderQRCodeImgNew($qrcodeImg, $id)
    {
        $stmt = $this->db->prepare("update event_orders_qrcode set qrcodeImg=:qrcodeImg where id=:id ");
        $array = array(
            ':qrcodeImg' => $qrcodeImg,
            ':id' => $id
        );
        $stmt->execute($array);
    }

    //修改订单二维码
    public function updatPartakeQRCodeImg($qrcodeImg, $id)
    {
        $stmt = $this->db->prepare("update event_partake_info set qrcodeImg=:qrcodeImg where id=:id ");
        $array = array(
            ':qrcodeImg' => $qrcodeImg,
            ':id' => $id
        );
        $stmt->execute($array);
    }

    //修改订单验证状态
    public function updateOrderTickets($id)
    {
        $sql = "update event_orders_qrcode set is_check=1 where id=:id";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $rowCount = $stmt->rowCount();
        return $rowCount;
    }

    //获取某个服务信息下的订单
    public function getOrderListByEid($eid, $start, $size)
    {
        $sql = "SELECT * FROM event_orders WHERE order_status =2 and status< 2 and eid=:eid order by pay_time desc limit :start,:size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':eid', $eid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $userModel = new UserModel();
                $result[$k]['user_info'] = $userModel->getUserData($v['uid']);
            }
        }
        return $result;
    }

    //获取某个服务信息下的订单数量
    public function getOrderNumByEid($eid)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS num FROM event_orders WHERE eid = :eid AND order_status=2 AND STATUS < 2 ");
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //脚本跑失效订单并改变状态
    public function initOrderStatus()
    {
        $time = time();
        //查询所有未支付订单
        $stmt = $this->db->prepare("SELECT * FROM event_orders WHERE order_status=1 AND STATUS < 2 and p_number is null ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                //判断时间 15分钟
                if ((strtotime($v['add_time']) + 900) < $time) {
                    $this->updateOrderStatus(5, $v['order_id']);
                    $stmt_price = $this->db->prepare("update event_price set stock_num = stock_num +:num where f_id=:f_id and unit_price=:unit_price ");
                    $array_price = array(
                        ':num' => $v['num'],
                        ':f_id' => $v['f_id'],
                        ':unit_price' => $v['unit_price'],
                    );
                    $stmt_price->execute($array_price);
                    if($time>strtotime(SERIAL_TIME)){
                        $userModel = new UserModel();
                        if($v['sp_id']){
                            $sp_info = $userModel->getSpInfoById($v['sp_id']);
                            //用户未到账减去
                            $userModel->addUnUseMoneyByUid($v['commission'],$sp_info['uid'],'-');
                        }
                    }
                }
            }
        }
    }

    //改变库存
    public function updateStocknum($id, $num, $type = '')
    {
        $fields = $type ? 'stock_num=stock_num-' . $num . '' : 'stock_num=stock_num+' . $num . '';
        $stmt = $this->db->prepare("update event_price set $fields where id=:id ");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
    }

    //添加场次
    public function addEventFields($eid, $start_time, $end_time, $partake_end_time)
    {
        $stmt = $this->db->prepare("insert into event_fields (eid,start_time,end_time,partake_end_time) values (:eid,:start_time,:end_time,:partake_end_time)");
        $array = array(
            ':eid' => $eid,
            ':start_time' => $start_time,
            ':end_time' => $end_time,
            ':partake_end_time' => $partake_end_time
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        return $id;
    }

    //获取场次
    public function getEventFields($eid)
    {
        $stmt = $this->db->prepare("select id,start_time,end_time,partake_end_time from event_fields where eid=:eid and status =1");
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //根据场次id获取服务价格信息
    public function getPriceByFid($f_id)
    {
        $stmt = $this->db->prepare("select id,unit_price as price,mark as price_mark,num as max_partake,stock_num from event_price where f_id=:f_id and status< 2");
        $array = array(
            ':f_id' => $f_id
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $k => $v) {
            if ($v['price'] == 0) {
                $result[$k]['price'] = '免费';
            }
        }
        return $result;
    }

    //修改获取信息
    public function getInfo($id, $uid)
    {
        $f_array = array('0' => '一', '1' => '二', '2' => '三', '3' => '四', '4' => '五', '5' => '六', '6' => '七');
        $stmt = $this->db->prepare("select id,title,cover,content,type,type_code,address,lng,lat,is_recommend,is_close,price_type,agio,agio_info,status,province,city,town,sid from event where id=:id and status < 2");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $fields_info = $this->getEventFields($id);
            $total_stock = 0;
            foreach ($fields_info as $k => $v) {
                $price = $this->getPriceByFid($v['id']);
                $fields_info[$k]['is_add_num'] = (int)(20 - $this->getNumByUidAndFid($uid, $v['id']));
                $fields_info[$k]['price_info'] = $price;
                foreach ($price as $v1) {
                    $total_stock += $v1['stock_num'];
                }
                if (date('Y-m-d', strtotime($v['start_time'])) == date('Y-m-d', strtotime($v['end_time']))) {
                    $fields_info[$k]['show_time'] = '场次' . $f_array[$k] . '： ' . date('m-d H:i', strtotime($v['start_time'])) . '至' . date('H:i', strtotime($v['end_time'])) . '';
                } else {
                    $fields_info[$k]['show_time'] = '场次' . $f_array[$k] . '： ' . date('m-d H:i', strtotime($v['start_time'])) . '至' . date('m-d H:i', strtotime($v['end_time'])) . '';
                }
                if (date('Y-m-d H:i:s', time()) >= $v['partake_end_time'] || $total_stock == 0) {
                    $fields_info[$k]['is_add'] = 0;
                } else {
                    $fields_info[$k]['is_add'] = 1;
                }
            }
            $result['agio'] = $result['agio'] ? $result['agio'] : "";
            $result['fields_info'] = $fields_info;
            if ($result['price_type'] == 1) {
                $result['partake_option'] = $this->getPartakeOptionByEid($id);
            }
            $addressModel = new AddressModel();
            $province_name = $result['province'] ? $addressModel->getNameById($result['province']) : '';
            $city_name = $result['city'] ? $addressModel->getNameById($result['city']) : '';
            $town_name = $result['town'] ? $addressModel->getNameById($result['town']) : '';
            $result['address_name'] = $province_name . ' ' . $city_name . ' ' . $town_name;
            if ($result['type'] == 1) {
                $type_name = $this->getBusinessEventType($result['type_code']);
            } else {
                $type_name = Common::eventType($result['type']);
            }
            $result['type_name'] = $type_name['name'];
            $result['code_name'] = $type_name['code'];
        }
        return $result;
    }

    public function getCountFields($eid)
    {
        $stmt = $this->db->prepare('select id from event_fields where eid=:eid and status=1  order by id asc');
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            $result['num'] = count($result);
        } else {
            $result['num'] = 0;
        }
        return $result;
    }

    //根据uid f_id 验证用户购票数
    public function getNumByUidAndFid($uid, $fid)
    {
        $stmt = $this->db->prepare("SELECT num FROM event_orders WHERE uid = :uid AND f_id = :f_id AND order_status < 2  AND STATUS < 2 ");
        $array = array(
            ':uid' => $uid,
            ':f_id' => $fid
        );
        $stmt->execute($array);
        $result = $stmt->fetchALL(PDO::FETCH_ASSOC);
        $num = 0;
        if ($result) {
            foreach ($result as $v) {
                $num += $v['num'];
            }
        }
        return $num;
    }

    public function getFieldsInfo($f_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM event_fields WHERE id=:id ");
        $array = array(
            ':id' => $f_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function getEventPartakeEndTime($eid)
    {
        //$time = date('Y-m-d H:i:s');  and partake_end_time >'".$time."'
        $stmt = $this->db->prepare("SELECT DISTINCT
                                    CASE
                                        WHEN DATE_FORMAT(NOW(), '%Y') = DATE_FORMAT(partake_end_time,'%Y') THEN DATE_FORMAT(partake_end_time,'%m月%d日 %H:%i')
                                        ELSE DATE_FORMAT(partake_end_time,'%y年%m月%d日 %H:%i')
                                    END AS partake_end_time_new FROM event_fields WHERE eid =:eid and partake_end_time >'" . date('Y-m-d H:i:s') . "' and status = 1  ORDER BY partake_end_time  ASC LIMIT 1 ");
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            $stmt = $this->db->prepare("SELECT DISTINCT
                                    CASE
                                        WHEN DATE_FORMAT(NOW(), '%Y') = DATE_FORMAT(partake_end_time,'%Y') THEN DATE_FORMAT(partake_end_time,'%m月%d日 %H:%i')
                                        ELSE DATE_FORMAT(partake_end_time,'%y年%m月%d日 %H:%i')
                                    END AS partake_end_time_new FROM event_fields WHERE eid =:eid and partake_end_time <'" . date('Y-m-d H:i:s') . "' and status = 1  ORDER BY partake_end_time desc LIMIT 1 ");
            $array = array(
                ':eid' => $eid
            );
            $stmt->execute($array);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return $result['partake_end_time_new'];
    }

    public function getPartakeNum($eid, $type)
    {
        $stmt = $this->db->prepare("SELECT num FROM event_price WHERE eid=:eid and status < 2 ");
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data['num'] = 0;
        $data['sell_num'] = 0;
        if ($result) {
            foreach ($result as $v) {
                if (!$v || $v == NULL) {
                    $data['num'] = '无限制';
                } else {
                    $data['num'] += $v['num'];
                }
            }
        }
        if ($type == 1) {
            $stmt_option = $this->db->prepare("SELECT count(id) as num FROM event_partake_info WHERE eid=:eid and status < 2 ");
            $array_option = array(
                ':eid' => $eid
            );
            $stmt_option->execute($array_option);
            $result_option = $stmt->fetch(PDO::FETCH_ASSOC);
            $data['sell_num'] = $result_option['num'];
        } else {
            $stmt_order = $this->db->prepare("SELECT num FROM event_orders WHERE eid=:eid and order_status < 2 ");
            $array_order = array(
                ':eid' => $eid
            );
            $stmt_order->execute($array_order);
            $result_order = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$result_order) {
                $data['sell_num'] = 0;
            } else {
                foreach ($result_order as $v) {
                    $data['sell_num'] += $v['num'];
                }
            }
        }
        if (!$data['sell_num']) {
            $data['sell_num'] = 0;
        }
        return $data;
    }

    public function getPartakeForHtml($eid, $size)
    {
        $stmt = $this->db->prepare("SELECT DISTINCT(uid) FROM event_partake_info WHERE eid =:eid AND STATUS < 2 order by id desc LIMIT :size");
        $stmt->bindValue(':eid', $eid, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $userModel = new UserModel();
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['num'] = $this->getPartakeNumForHtml($eid, $v['uid']);
                $result[$k]['add_time'] = $this->getPartakeNewTimeForHtml($eid, $v['uid']);
                $result[$k]['user_info'] = $userModel->getUserData($v['uid']);
            }
        }
        $num = $this->getPartakeCount($eid);
        return $data = array(
            'list' => $result,
            'size' => $num
        );
    }

    //服务信息h5用户最新报名时间（免费）
    public function getPartakeNewTimeForHtml($eid, $uid)
    {
        $stmt = $this->db->prepare("SELECT add_time FROM event_partake_info WHERE eid =:eid and uid=:uid AND STATUS < 2 order by add_time desc limit 1 ");
        $stmt->bindValue(':eid', $eid, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return Common::app_show_time($result['add_time']);
    }

    //服务信息h5用户最新报名时间（免费）
    public function getPartakeNumForHtml($eid, $uid)
    {
        $stmt = $this->db->prepare("SELECT count(id) as num FROM event_partake_info WHERE eid =:eid and uid=:uid AND STATUS < 2");
        $stmt->bindValue(':eid', $eid, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //服务信息h5用户最新报名时间（收费）
    public function getOrderNumForHtml($eid, $uid)
    {
        $stmt = $this->db->prepare("SELECT count(id) as num FROM event_orders WHERE eid =:eid and uid=:uid AND STATUS < 2 and order_status<3");
        $stmt->bindValue(':eid', $eid, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //报名统计已售票数量
    public function getPartakeCount($eid)
    {
        $stmt = $this->db->prepare("select count(distinct(uid)) as num from event_partake_info where eid = :eid and status < 2");
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //付费统计已售数量
    public function getOrdersCount($id)
    {
        $stmt = $this->db->prepare("select count(distinct(uid)) as num from event_orders  where eid =:id and order_status=2 and status < 2");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    public function getOrdersForHtml($eid, $size)
    {
        $stmt = $this->db->prepare("SELECT DISTINCT(uid) FROM event_orders  WHERE eid =:eid and order_status =2 and status < 2  order by id desc limit :size");
        $stmt->bindValue(':eid', $eid, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $userModel = new UserModel();
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['num'] = $this->getOrderNumForHtml($eid, $v['uid']);
                $result[$k]['add_time'] = $this->getEndOrderTime($eid, $v['uid']);
                $result[$k]['user_info'] = $userModel->getUserData($v['uid']);
            }
        }
        return $data = array(
            'list' => $result,
            'size' => $this->getOrdersCount($eid)
        );
    }

    //获取用户最后下单时间
    public function getEndOrderTime($eid, $uid)
    {
        $stmt = $this->db->prepare("SELECT add_time FROM event_orders  WHERE eid =:eid and uid=:uid and order_status<3 and status < 2 order by add_time desc limit 1");
        $array = array(
            ':eid' => $eid,
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return Common::app_show_time($result['add_time']);
    }

    //获取某个服务信息的价格
    public function getEndPartakeTime($eid)
    {
        $stmt = $this->db->prepare("SELECT partake_end_time FROM event_fields WHERE eid=:eid and status =1 order by partake_end_time desc");
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function getEndTime($eid)
    {
        $stmt = $this->db->prepare("SELECT end_time FROM event_fields WHERE eid=:eid and status =1 order by end_time desc ");
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function getEventStartTime($id)
    {
        $stmt = $this->db->prepare("SELECT DISTINCT
                                    CASE
                                        WHEN DATE_FORMAT(NOW(), '%Y') = DATE_FORMAT(start_time,'%Y') THEN DATE_FORMAT(start_time,'%m月%d日')
                                        ELSE DATE_FORMAT(start_time,'%y年%m月%d日')
                                    END AS start_time_new FROM event_fields WHERE eid =:eid ORDER BY start_time");
        $array = array(
            ':eid' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data = array();
        if ($result) {
            foreach ($result as $v) {
                $data[] = $v['start_time_new'];
            }
        }
        return $data;
    }

    //查看用户免费活动报名场次数量
    public function getUserPartakeNumByEid($uid, $eid)
    {
        $stmt = $this->db->prepare("SELECT count(*) as num FROM event_partake_info WHERE eid=:eid and uid=:uid and status< 2 ");
        $array = array(
            ':eid' => $eid,
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    public function getPartakeNumByEid($eid)
    {
        $stmt = $this->db->prepare("SELECT count(*) as num FROM event_partake_info WHERE eid=:eid and status< 2 ");
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //查看付费活动报名数量
    public function getOrderNumTotalsByEid($eid)
    {
        $stmt = $this->db->prepare("SELECT SUM(num) AS totals FROM event_orders WHERE eid =:eid AND order_status < 3 and status < 2 ");
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['totals'];
    }

    //查看付费活动报名数量
    public function getUserOrderNumByEid($eid, $uid)
    {
        $stmt = $this->db->prepare("SELECT SUM(num) AS totals FROM event_orders WHERE eid =:eid AND uid=:uid AND order_status < 3 and status < 2 ");
        $array = array(
            ':eid' => $eid,
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['totals'];
    }

    //活动总人数限制
    public function getEventMaxPartake($eid)
    {
        $stmt = $this->db->prepare("SELECT SUM(num) AS totals FROM event_price WHERE eid =:eid AND status < 2 ");
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['totals'];
    }

    //服务信息未结束场次的总库存
    public function getUnEndPartakeTotals($eid)
    {
        $stmt = $this->db->prepare("SELECT SUM(stock_num) AS totals FROM event_price WHERE f_id IN (SELECT id FROM event_fields WHERE eid =:eid AND STATUS =1 AND partake_end_time >'" . date('Y-m-d H:i:s') . "') AND STATUS < 2 ");
        $array = array(
            ':eid' => $eid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['totals'];
    }

    public function delOrder($id, $status)
    {
        $stmt = $this->db->prepare("update event_orders set status=:status where id=:id ");
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

    //根据订单号查询订单信息
    public function getInfoByOrderId($order_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM event_orders WHERE order_id=:order_id ");
        $array = array(
            ':order_id' => $order_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //服务信息电子票号,二维码插入
    public function addPnumber($id, $p_number, $add_time)
    {
        $stmt = $this->db->prepare("insert into event_orders_qrcode (o_id,p_number,add_time) values (:o_id,:p_number,:add_time)");
        $array = array(
            ':o_id' => $id,
            ':p_number' => $p_number,
            ':add_time' => $add_time
        );
        $stmt->execute($array);
        $rs = $this->db->lastInsertId();
        PHPQRCode::getOrderPHPQRCodeNew($rs, $id);
    }

    //查询二维码
    public function getQrcode($id)
    {
        $stmt = $this->db->prepare("SELECT qrcodeImg,p_number,is_check,update_time as check_time FROM event_orders_qrcode WHERE o_id=:o_id ");
        $array = array(
            ':o_id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //3.5版本修改订单电子票号
    public function updatePnumber($p_number, $id)
    {
        $stmt = $this->db->prepare("update event_orders set p_number=:p_number where id=:id ");
        $array = array(
            ':p_number' => $p_number,
            ':id' => $id,
        );
        $stmt->execute($array);
    }

    //查询单个二维码
    public function getQrcodeById($id)
    {
        $stmt = $this->db->prepare("SELECT o_id,p_number,qrcodeImg,is_check FROM event_orders_qrcode WHERE id=:id ");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //获取已使用的数量
    public function getCheckNum($o_id)
    {
        $stmt = $this->db->prepare("SELECT count(*) as num FROM event_orders_qrcode WHERE o_id=:o_id and is_check=1 ");
        $array = array(
            ':o_id' => $o_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    public function getEndNum($o_id)
    {
        $time = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("SELECT COUNT(*) AS num FROM event_orders_qrcode WHERE o_id=:o_id AND is_check=0 AND o_id IN (SELECT id FROM event_orders WHERE f_id IN(SELECT id FROM event_fields WHERE end_time <=:time) ) ");
        $array = array(
            ':o_id' => $o_id,
            ':time' => $time
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //查询某一个单价
    public function getUnitPrice($price_id)
    {
        $stmt = $this->db->prepare("SELECT unit_price FROM event_price WHERE id =:id ");
        $array = array(
            ':id' => $price_id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['unit_price'];
    }

    /*
     * 用户进入H5详情页 报名按钮状态判断返回
     *
     */
    public function getAddTypeByUid($id, $uid)
    {
        /*
         *  1.A B用户都没有报名任何一个场次，活动已结束-----A B用户按钮显示活动结束
            2.A B用户都没有报名任何一个场次，报名已结束-----A B用户按钮显示报名结束
            3.A B用户有其中一个场次未报名，且该场次名额未满，活动未结束，报名未结束 ---- A B用户按钮显示立即报名
            4.A用户已所有场次报名, B用户未在任何一个场次报名，活动结束 ----A已报名  B活动结束
            5.A用户已所有场次报名，B用户未在任何一个场次报名，报名已结束 -----A 已报名 B 报名结束
            6.A用户已所有场次报名，B用户在其中任何一个场次报名，报名已结束 -----A 已报名 B 已报名
            7.A B 用户都没有在任何一个场次报名，人数满 ----- A B 名额已满
            8.A用户在任意场次已报名，B用户没有任何一个场次报名，名额已满 -----A 已报名 B名额已满
            9.A用户在任意场次已报名，B用户没有任何一个场次报名，名额未满 -----A 立即报名 B立即报名
            10.A用户在任意场次已报名(该场次名额已满)，B用户没有任何一个场次报名 -----A 已报名 B立即报名

        return $type  0.立即报名 1.活动结束 2.报名结束 3.已报名 4.名额已满
         */

        $info = $this->getEvent($id);//服务信息
        $fields_list = $this->getEventFields($id);//场次信息
        $pTime = $this->getEndPartakeTime($id);
        $eTime = $this->getEndTime($id);
        $p_time = strtotime($pTime[0]['partake_end_time']);//服务最晚报名结束时间
        $e_time = strtotime($eTime[0]['end_time']);//服务最晚活动結束时间
        $priceList = $this->getPrice($id);//活动费用信息列表
        $unEndTotals = $this->getUnEndPartakeTotals($id);//未结束场次的未报名总数
        $time = time();
        $type = 0;
        $is_unlimited = 0;            //是否有无限制场次
        $user_totals = 140;
        foreach ($priceList as $v) {
            if ($v['num'] == 0) {
                $is_unlimited = 1;//有无限制
                break;
            }
        }
        if ($info['price_type'] == 1) {//免费服务
            $userNumByEid = $this->getUserPartakeNumByEid($uid, $id);//用户是否在某一个免费服务下有报名
            $p_totals = $this->getPartakeNumByEid($id);//免费活动总人数限制
            if ($time >= $e_time && $userNumByEid == 0) {//用户未报名，活动已结束
                $type = 1;
            } elseif ($time >= $p_time && $time < $e_time && $userNumByEid == 0) {//用户未报名，报名已结束，活动未结束
                $type = 2;
            } elseif ($userNumByEid && ($time >= $e_time || $time >= $p_time && $time < $e_time)) {//用户已报过名活动已结束或者报名已结束
                $type = 3;
            } elseif ($userNumByEid && $unEndTotals && $userNumByEid != count($fields_list) && $time < $p_time) {//用户已报过名，没有在所有场次报名，报名还未结束
                $type = 0;
            } elseif ($userNumByEid && $userNumByEid == count($fields_list) && $time < $p_time) {//用户已报过名，没有在所有场次报名，报名还未结束
                $type = 3;
            } elseif ($userNumByEid == 0 && $unEndTotals == 0 && $time < $p_time && $is_unlimited == 0) {//用户未报名，名额满,报名未结束
                $type = 4;
            } elseif ($userNumByEid && $unEndTotals == 0) {//用户已报名,名额满，报名未结束
                $type = 3;
            }
        } else {//收费服务
            $userNumByEid = $this->getUserOrderNumByEid($id, $uid);//用户在某一收费服务下的报名数
            $m_totals = $this->getEventMaxPartake($id);//收费活动总人数限制
            if ($userNumByEid && ($time >= $e_time || $time >= $p_time && $time < $e_time)) {//用户已报名，活动结束或者报名结束
                $type = 3;
            } elseif ($userNumByEid == 0 && $time >= $e_time) {//用户未报名，活动结束
                $type = 1;
            } elseif ($userNumByEid == 0 && $time >= $p_time && $time < $e_time) {//用户未报名，报名结束
                $type = 2;
            } elseif ($userNumByEid == 0 && $unEndTotals == 0 && $time < $p_time) {//用户未报名，名额满,报名未结束
                $type = 4;
            } elseif ($userNumByEid && $unEndTotals == 0 && $time < $p_time) {//用户已报名,名额满，报名未结束
                $type = 3;
            } elseif ($userNumByEid && $unEndTotals && $time < $p_time && ($userNumByEid < $user_totals || $userNumByEid < $m_totals)) {//用户已报名,名额未满，报名未结束，用户未到限制
                $type = 0;
            } elseif ($userNumByEid && $unEndTotals && $time < $p_time && ($userNumByEid == $user_totals || $userNumByEid == $m_totals)) {//用户已报名,名额未满，报名未结束，用户已到限制
                $type = 3;
            }
        }
        return $type;
    }

    public function setUnUse($id)
    {
        $stmt_un_use = $this->db->prepare("update event_orders set un_use=un_use-1 where id=:id ");
        $array_un_use = array(
            ':id' => $id
        );
        $stmt_un_use->execute($array_un_use);
    }

    //用户报名服务，服务即将开始系统通知，短信提醒
    public function noticeAndSms()
    {
        //查询符合条件的付费报名
        $stmt = $this->db->prepare("SELECT a.id,a.order_id,a.f_id,a.uid,a.is_notice,a.phone,b.start_time
                                     FROM event_orders a
                                     LEFT JOIN event_fields b
                                     ON a.f_id = b.id
                                     where a.is_notice = 0
                                     GROUP BY a.f_id,a.uid,a.phone ORDER BY id ASC");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                if (date('Y-m-d H', strtotime($v['start_time'])) == date("Y-m-d H", strtotime("+1 day"))) {
                    $stmt = $this->db->prepare("update event_orders set is_notice = 1 where f_id=:f_id and uid=:uid ");
                    $array = array(
                        ':f_id' => $v['f_id'],
                        ':uid' => $v['uid']
                    );
                    $stmt->execute($array);
                    Common::addNoticeAndSmsForEvent(2, $v['order_id'], '');
                }
            }
        }
        //查询所有的免费报名信息
        $mobileId = $this->getOptionId('手机号');
        $stmt_p = $this->db->prepare("SELECT a.id,a.uid,a.f_id,a.is_notice,b.start_time,c.content AS phone
                                        FROM event_partake_info a
                                        LEFT JOIN event_fields b
                                        ON a.f_id = b.id
                                        LEFT JOIN event_partake c
                                        ON a.id = c.p_info_id
                                        WHERE a.is_notice = 0 AND c.oid =" . $mobileId . "
                                        GROUP BY a.f_id,a.uid,phone
                                        ORDER BY id desc");
        $stmt_p->execute();
        $result_p = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result_p) {
            foreach ($result_p as $v) {
                if (date('Y-m-d H', strtotime($v['start_time'])) == date("Y-m-d H", strtotime("+1 day"))) {
                    $stmt = $this->db->prepare("update event_partake_info set is_notice = 1 where f_id=:f_id and uid=:uid ");
                    $array = array(
                        ':f_id' => $v['f_id'],
                        ':uid' => $v['uid']
                    );
                    $stmt->execute($array);
                    Common::addNoticeAndSmsForEvent(2, '', $v['id']);
                }
            }
        }
    }

    public function setBuyOrderStatus()
    {
        $stmt = $this->db->prepare("SELECT * FROM event_orders WHERE order_status=5 AND pay_time!='' ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $stmt_u = $this->db->prepare("update event_orders set order_status = 2 where id=:id ");
                $array = array(
                    ':id' => $v['id']
                );
                $stmt_u->execute($array);
                if(time()>strtotime(SERIAL_TIME)){
                    $userModel = new UserModel();
                    $event_info = $this->getEventRedisById($v['eid']);
                    //商家用户钱袋增加
                    $userModel->addMoneyBagByUid($v['fact_totals'],$event_info['uid']);
                    if($v['sp_id']){
                        $sp_info = $userModel->getSpInfoById($v['sp_id']);
                        //用户钱袋增加
                        $userModel->addMoneyBagByUid($v['commission'],$sp_info['uid']);
                    }
                }
            }
        }
    }

    //获取缓存中的服务信息数据
    public function getEventRedisById($id)
    {
        $redisKey = Common::getRedisKey(10) . $id;
        $result = $this->contentRedis->get($redisKey);
        if ($result) {
            $result = json_decode($result, true);
        } else {
            $stmt = $this->db->prepare("select * from event where id=:id");
            $array = array(
                ':id' => $id
            );
            $stmt->execute($array);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $result['event_address'] = $result['address'];
                $fields_info = $this->getEventFields($id);
                foreach ($fields_info as $k => $v) {
                    $fields_info[$k]['price_info'] = $this->getPriceByFid($v['id']);
                }
                $result['fields_info'] = $fields_info;
                $result['price_list'] = $this->getPrice($id);
            }
            $this->contentRedis->set($redisKey, json_encode($result));
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
            foreach ($ids as $eid) {
                $info = $this->getEventRedisById($eid);
                if($info['status']<2){
                    if($info['commission_time']){
                        $commission_time = $info['commission_time'];
                        $commission_update_time = $time;
                    }else{
                        $commission_time = $time;
                        $commission_update_time = $time;
                    }
                    if ($is_commission) {
                        $min_price = $info['price_list'][0]['unit_price'];
                        $commission = round($min_price * $number, 2);
                        $min_commission = $commission;
                        $max_commission = '';
                        if (count($info['price_list']) > 1) {
                            $max_price = end($info['price_list']);
                            $commission = round($min_price * $number, 2) . '-' . round($max_price['unit_price'] * $number, 2);
                            $max_commission = round($max_price['unit_price'] * $number, 2);
                        }
                    } else {
                        $commission = '';
                        $min_commission = '';
                        $max_commission = '';
                    }
                    $stmt = $this->db->prepare("update event set uid=:uid,is_commission =:is_commission,commission_rate=:commission_rate,commission=:commission,commission_time=:commission_time,commission_update_time=:commission_update_time,min_commission=:min_commission,max_commission=:max_commission,update_time=:update_time where id=:id ");
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
                        ':id' => $eid
                    );
                    $stmt->execute($array);
                    if ($is_commission) {
                        $set_id = $commonModel->userSetCommissionRate(10, $eid, $number, $uid);
                    } else {
                        $set_id = 0;
                    }
                    $last_id = $commonModel->getUserSetLastId($set_id, 10, $eid);
                    if ($last_id) {
                        $commonModel->updateCommissionTime($last_id, $use_time);
                    }
                    $this->contentRedis->del(Common::getRedisKey(10) . $eid);
                }
            }
        }
    }

    //商品分享筛选
    public function getCommissionByCondition($type = '', $sort_id, $page, $size, $token = '', $version)
    {
        $start = ($page - 1) * $size;
        $conditionSort = '';
        $conditionType = $type ? ' and type=' . $type . '' : '';
        if ($sort_id == 1) {
            $conditionSort = 'order by commission_time desc,id desc';
        } elseif ($sort_id == 2) {
            $conditionSort = 'order by (min_commission+0) desc,id desc';
        } elseif ($sort_id == 3) {
            $conditionSort = 'order by orders_statistics desc,id desc';
        } elseif ($sort_id == 4) {
            $conditionSort = 'order by (orders_commission_statistics+0) desc,id desc';
        }
        $sql = "select id from event where status < 2 and is_commission=1  $conditionType  $conditionSort limit :start,:size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        $addressModel = new AddressModel();
        $stageModel = new StageModel();
        foreach ($result as $k => $v) {
            $event_info = $this->getEventRedisById($v['id']);
            $list[$k]['id'] = $v['id'];
            $list[$k]['title'] = $event_info['title'];
            $list[$k]['cover'] = Common::show_img($event_info['cover'], 4, 360, 270);
            $list[$k]['commission_rate'] = $event_info['commission_rate'];
            $min_price = $event_info['price_list'][0]['unit_price'];
            $list[$k]['price'] = $min_price;
            if (count($event_info['price_list']) > 1) {
                $max_price = end($event_info['price_list']);
                $max_price = $max_price['unit_price'];
                $list[$k]['price'] = $min_price . '-' . $max_price;
            }
            $list[$k]['commission'] = $event_info['commission'];
            $list[$k]['show_start_time'] = Common::getEventStartTime($v['id']);
            if ($event_info['type'] == 1) {
                $data = $this->getBusinessEventType($event_info['type_code']);//获取活动分类内容
            } else {
                $data = Common::eventType($event_info['type']);
            }
            $list[$k]['type_name'] = $data['name'];
            $list[$k]['code_name'] = $data['code'];
            $province_name = $addressModel->getNameById($event_info['province']);
            $city_name = $addressModel->getNameById($event_info['city']);
            $town_name = $addressModel->getNameById($event_info['town']);
            if ($province_name == $city_name) {
                $address_name = $city_name . $town_name;
            } else {
                $address_name = $province_name . $city_name;
            }
            $list[$k]['event_address'] = $address_name;
            $stage_info = $stageModel->getStage($event_info['sid']);
            $list[$k]['stage_name'] = $stage_info['name'];
            $list[$k]['url'] = $token ? I_DOMAIN . '/e/' . $v['id'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/e/' . $v['id'] . '?version=' . $version . '';
        }
        return $list;
    }

    public function getStageEventById($objId, $version, $token = '')
    {
        $sql = "select id from event where id=:objId";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':objId', $objId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $list = array();
        $addressModel = new AddressModel();
        $stageModel = new StageModel();
        $event_info = $this->getEventRedisById($result['id']);
        $list['id'] = $result['id'];
        $list['title'] = $event_info['title'];
        $list['cover'] = Common::show_img($event_info['cover'], 4, 360, 270);
        $list['commission_rate'] = $event_info['commission_rate'];
        $min_price = $event_info['price_list'][0]['unit_price'];
        $list['price'] = $min_price;
        if (count($event_info['price_list']) > 1) {
            $max_price = end($event_info['price_list']);
            $max_price = $max_price['unit_price'];
            $list['price'] = $min_price . '-' . $max_price;
        }
        $list['commission'] = $event_info['commission'];
        $list['show_start_time'] = Common::getEventStartTime($result['id']);
        if ($event_info['type'] == 1) {
            $data = $this->getBusinessEventType($event_info['type_code']);//获取活动分类内容
        } else {
            $data = Common::eventType($event_info['type']);
        }
        $list['type_name'] = $data['name'];
        $list['code_name'] = $data['code'];
        $province_name = $addressModel->getNameById($event_info['province']);
        $city_name = $addressModel->getNameById($event_info['city']);
        $town_name = $addressModel->getNameById($event_info['town']);
        if ($province_name == $city_name) {
            $address_name = $city_name . $town_name;
        } else {
            $address_name = $province_name . $city_name;
        }
        $list['event_address'] = $address_name;
        $stage_info = $stageModel->getStage($event_info['sid']);
        $list['stage_name'] = $stage_info['name'];
        $list['url'] = $token ? I_DOMAIN . '/e/' . $result['id'] . '?token=' . $token . '&version=' . $version . '' : I_DOMAIN . '/e/' . $result['id'] . '?version=' . $version . '';

        return $list;
    }

    //商品更新统计30天引入订单量和引入订单金额
    public function updateStatistics()
    {
        $time = date("Y-m-d H:m:s", strtotime("-1 month"));
        $stmt = $this->db->prepare("SELECT DISTINCT(obj_id) AS eid FROM user_set_commission_rate WHERE TYPE = 10 ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $orders_num = $totals = $commission = 0;
        if ($result) {
            foreach ($result as $v) {
                $stmt_orders = $this->db->prepare("SELECT id,totals,commission FROM event_orders WHERE add_time>:time and order_status = 2 and eid =:id ");
                $array = array(
                    ':time' => $time,
                    ':id' => $v['eid']
                );
                $stmt_orders->execute($array);
                $orders = $stmt_orders->fetch(PDO::FETCH_ASSOC);
                if ($orders) {
                    $orders_num += count($orders);
                    foreach ($orders as $val) {
                        $totals += $val['totals'];
                        $commission += $val['commission'];
                    }
                }
                $stmt = $this->db->prepare("update event set orders_statistics=:num,orders_prices_statistics =:totals,orders_commission_statistics=:commission,update_time=:update_time where id=:id");
                $array = array(
                    ':num' => $orders_num,
                    ':totals' => $totals,
                    ':commission' => $commission,
                    ':update_time' => date('Y-m-d H:i:s'),
                    ':id' => $v['eid']
                );
                $stmt->execute($array);

            }
        }
    }
    //获取驿站下活动分享支出总额和分享交易订单总额/某一个活动
    public function getEventSpCommissionTotals($sid,$eid=''){
        $fields = $eid ? ' and eid ='.$eid.'' : '';
        $stmt = $this->db->prepare("select totals,commission from event_orders where sid=:sid and sp_id!='' and order_status = 2 $fields");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data['price_totals'] = $data['commission_totals'] = 0;
        if($rs){
            foreach($rs as $v){
                $data['price_totals']+=(int)$v['totals'];
                $data['commission_totals'] +=(int)$v['commission'];
            }
        }
        return $data;
    }
}
