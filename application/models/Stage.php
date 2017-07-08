<?php

/**
 * @name StageModel
 * @desc Stage数据获取类, 可以访问数据库，文件，其它系统等
 * @author {&$AUTHOR&}
 */
class StageModel
{
    private $db;
    private $redis;
    private $contentRedis;
    public $coverNum = 10;
    public $event_type_name = array('1' => '活动', '3' => '培训', '6' => '展览', '7' => '演出');

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
        $this->contentRedis = CRedis::getContentInstance();
    }

    /**
     * 查询代代文化分类列表
     */
    public function getCultureCateList($field = '')
    {
        if ($field) {
            $field = "and id<>1";
        }
        $stmt = $this->db->prepare("select id,name,class_name,cover from culture_cate where status=1 $field order by sort,id");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['cover'] = $v['cover'] ? IMG_DOMAIN . $v['cover'] : '';
                $result[$k]['stage_num'] = $this->getNumByCateId($v['id']);
            }
        }
        return $result;
    }

    /**
     * 根据代代文化分类id查询信息
     */
    public function getCultureCateById($id)
    {
        $stmt = $this->db->prepare("select id,name,class_name from culture_cate where id=:id");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 判断驿站名是否已存在
     */
    public function stageNameIsExist($name, $sid = '')
    {
        if (!$sid) {
            $stmt = $this->db->prepare("select count(sid) as num from stage where name=:name and status<2");
            $array = array(
                ':name' => $name,
            );
        } else {
            $stmt = $this->db->prepare("select count(sid) as num from stage where name=:name and sid!=:sid and status<2");
            $array = array(
                ':name' => $name,
                ':sid' => $sid,
            );
        }
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);;
        return $result['num'];
    }

    public function getCreateStageNum($uid)
    {
        $stmt = $this->db->prepare("select count(sid) as num from stage where uid=:uid AND status < 3");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return 0;
        }
        return $result['num'];
    }

    public function getCreateBussinessNum($uid)
    {
        $stmt = $this->db->prepare("select sid from stage where uid=:uid and type =2  AND status < 3");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return 0;
        }
        return $result['sid'];
    }

    /**
     * 保存创建商家驿站信息
     */
    public function addBusiness($cate_id, $name, $uid, $type, $license_number, $identity_img, $license_img, $address, $lng, $lat, $contacts, $mobile)
    {
        $icon = $this->getCoverIconRandom($cate_id, 0);
        $stmt = $this->db->prepare("insert into stage (cate_id,name,uid,type,icon,add_time)
        values (:cate_id,:name,:uid,:type,:icon,:add_time)");
        $array = array(
            ':cate_id' => $cate_id,
            ':name' => $name,
            ':uid' => $uid,
            ':type' => 2,
            ':icon' => $icon,
            ':add_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $sid = $this->db->lastInsertId();
        if ($sid < 1) {
            return 0;
        } else {
            $this->insertStageUser($uid, $sid, 1, 1);
            $this->addBusinessInfo($sid, $type, $license_number, $identity_img, $license_img, $address, $lng, $lat, $contacts, $mobile, 0);
            $userModel = new UserModel();
            $userModel->clearUserData($uid);
        }
        return $sid;
    }

    //升级驿站保存商家信息
    public function upgradeBusiness($data)
    {

        $addressModel = new AddressModel();
        $parent = $addressModel->cityParent($data['town']);
        $province = $parent['pid'];
        $city = $parent['id'];
        $rs = $this->modifyUpgrade($data['sid']);
        $rs_business = $this->getNumByBusiness($data['sid']);
        if ($rs_business) {
            $this->updateBusinessInfo($data['sid'], $data['type'], $data['identity_img'], $data['license_img'], $data['bank'], $data['bank_no'], $data['email'], $data['contacts'], $data['tel'], $data['shop_hours'], $data['business_scope']);
        } else {
            $array = array(
                'sid' => $data['sid'],
                'type' => $data['type'],
                'identity_img' => $data['identity_img'],
                'license_img' => $data['license_img'],
                'bank' => $data['bank'],
                'bank_no' => $data['bank_no'],
                'email' => $data['email'],
                'contacts' => $data['contacts'],
                'tel' => $data['tel'],
                'shop_hours' => $data['shop_hours'],
                'business_scope' => $data['business_scope']
            );
            $this->addBusinessInfo($array);
        }
        $this->modifyStage($data['sid'], $province, $city, $data['town'], $data['lng'], $data['lat'], $data['address'], $data['uid'], $data['mobile']);
        $tagModel = new TagModel();
        $tagModel->saveRelation(2, $data['sid'], $data['tag']);
        if (!$rs) {
            $rs_id = $this->addUpgrade($data['cate_id'], $data['name'], $data['sid'], $data['uid'], $data['intro']);
            $userModel = new UserModel();
            $userModel->clearUserData($data['uid']);
            return $rs_id;
        } else {
            $id = $this->getStageCheck($data['sid']);
            $this->updateStageCheck($data['cate_id'], $data['name'], $data['intro'], $id);
        }
        return 1;
    }

    //获取stage_check 表的主键
    public function getStageCheck($sid)
    {
        $stmt = $this->db->prepare("select * from stage_check where sid=:sid and status < 3 and type=1");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return 0;
        }
        return $result['id'];
    }

    //文化驿站是否在升级
    public function isUpgradeNew($sid)
    {
        $stmt = $this->db->prepare("select count(*) as num from stage_check where sid=:sid and status =0 and type=1");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return 0;
        }
        return $result['num'];
    }

    //查询该驿站是否升过级
    public function modifyUpgrade($sid)
    {
        $stmt = $this->db->prepare("select * from stage_check where sid=:sid and status < 3 and type=1");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return 0;
        }
        return $result['sid'];
    }

    //查询该驿站是否在business里面有数据
    public function getNumByBusiness($sid)
    {
        $stmt = $this->db->prepare("select count(sid) as num from business where sid=:sid ");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //插入升级驿站信息
    public function addUpgrade($cate_id, $name, $sid, $uid, $intro)
    {
        $stmt = $this->db->prepare("insert into stage_check (sid,uid,cate_id,name,type,intro,add_time)
        values (:sid,:uid,:cate_id,:name,1,:intro,:add_time)");
        $array = array(
            ':sid' => $sid,
            ':uid' => $uid,
            ':cate_id' => $cate_id,
            ':name' => $name,
            ':intro' => $intro,
            ':add_time' => date('Y-m-d H:i:s'),
        );
        $stmt->execute($array);
        $this->clearStageData($sid);//清除缓存里驿站信息
        return $this->db->lastInsertId();
    }

    //修改升级驿站信息
    public function updateStageCheck($cate_id, $name, $intro, $id)
    {
        $stmt = $this->db->prepare("UPDATE stage_check set status=:status,cate_id=:cate_id,name=:name,intro=:intro,update_time=:update_time where id=:id");
        $array = array(
            ':status' => 0,
            ':cate_id' => $cate_id,
            ':name' => $name,
            ':intro' => $intro,
            ':update_time' => date('Y-m-d H:i:s'),
            ':id' => $id
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if ($rs < 1) {
            return 0;
        }
        return 1;
    }

    //升级驿站修改驿站基本信息
    public function upgradeStage($sid)
    {
        $stmt = $this->db->prepare("UPDATE stage set status=:status,type=:type,update_time=:update_time where sid=:sid");
        $array = array(
            ':status' => 0,
            ':update_time' => date('Y-m-d H:i:s'),
            ':type' => 2,
            ':sid' => $sid
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if ($rs < 1) {
            return 0;
        }
        $this->clearStageData($sid);//清除缓存里驿站信息
        return 1;
    }

    //创建商家驿站时插入商家基本信息
    public function addBusinessInfo($data)
    {
        $stmt = $this->db->prepare("insert into business (sid,type,identity_img,license_img,bank,bank_no,email,contacts,tel,shop_hours,business_scope)
        values (:sid,:type,:identity_img,:license_img,:bank,:bank_no,:email,:contacts,:tel,:shop_hours,:business_scope)");
        $array = array(
            ':sid' => $data['sid'],
            ':type' => $data['type'],
            ':identity_img' => $data['identity_img'],
            ':license_img' => $data['license_img'],
            ':bank' => $data['bank'],
            ':bank_no' => $data['bank_no'],
            ':email' => $data['email'],
            ':contacts' => $data['contacts'],
            ':tel' => $data['tel'],
            ':shop_hours' => $data['shop_hours'],
            ':business_scope' => $data['business_scope']
        );
        $stmt->execute($array);
        $this->db->lastInsertId();
        $this->clearStageData($data['sid']);//清除缓存里驿站信息
    }

    //修改商家驿站审核基本信息
    public function updateBusiness($sid, $type, $name, $cate_id, $identity_img, $license_number, $license_img, $address, $lng, $lat, $province, $city, $town, $contacts, $mobile)
    {
        if ($province && $city && $town) {
            $commonModel = new CommonModel();
            $commonModel->modifyAddress($province, $city, $town);
            $province_id = $commonModel->getCity($province, 1);
            $city_id = $commonModel->getCity($city, 2);
            $town_id = $commonModel->getCity($town, 3);
        } else {
            $province_id = '';
            $city_id = '';
            $town_id = '';
        }
        $stmt = $this->db->prepare("UPDATE stage set name =:name,cate_id=:cate_id,province=:province,city=:city,town=:town,status=:status,update_time=:update_time where sid=:sid");
        $array = array(
            ':name' => $name,
            ':cate_id' => $cate_id,
            ':province' => $province_id,
            ':city' => $city_id,
            ':town' => $town_id,
            ':status' => 0,
            ':update_time' => date('Y-m-d H:i:s'),
            ':sid' => $sid
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if ($rs < 1) {
            return 0;
        }
        $this->updateBusinessInfo($sid, $type, $license_number, $identity_img, $license_img, $address, $lng, $lat, $contacts, $mobile);
        return 1;
    }

    //修改商家驿站审核具体信息
    public function updateBusinessInfo($sid, $type, $identity_img, $license_img, $bank, $bank_no, $email, $contacts, $tel, $shop_hours, $business_scope)
    {
        $stmt = $this->db->prepare("UPDATE business set type=:type,identity_img=:identity_img,license_img=:license_img,bank=:bank,bank_no=:bank_no,email=:email,contacts=:contacts,tel=:tel,shop_hours=:shop_hours,business_scope=:business_scope,is_perfect =0,update_time=:update_time where sid=:sid ");
        $array = array(
            ':type' => $type,
            ':identity_img' => $identity_img,
            ':license_img' => $license_img,
            ':bank' => $bank,
            ':bank_no' => $bank_no,
            ':email' => $email,
            ':contacts' => $contacts,
            ':tel' => $tel,
            ':shop_hours' => $shop_hours,
            ':business_scope' => $business_scope,
            ':update_time' => date('Y-m-d H:i:s'),
            ':sid' => $sid
        );
        $stmt->execute($array);
        $this->clearStageData($sid);//清除缓存里驿站信息
    }

    //根据uid和sid 验证当前是否有管理驿站的权限
    public function verifyBusiness($sid, $uid)
    {
        $stmt = $this->db->prepare("SELECT COUNT(sid) AS num  FROM stage_user WHERE sid=:sid AND uid =:uid AND role = 1 AND STATUS = 1;");
        $array = array(
            ':sid' => $sid,
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //校验数据库商家图片+上传的图片是否超过上限
    public function verifyBusinessCoverNum($sid, $arrayCount)
    {
        $stmt = $this->db->prepare("SELECT COUNT(sid) AS num  FROM business_cover WHERE sid=:sid AND type=1 AND status =1;");
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $coverNum = $this->coverNum;
        if ($result['num'] + $arrayCount > $coverNum) {
            return false;
        }
        return true;
    }

    //更新商家驿站基本信息
    public function modifyStage($sid, $province_id, $city_id, $town_id, $lng, $lat, $address, $uid, $mobile)
    {
        $stmt = $this->db->prepare("update stage set mobile=:mobile,province=:province,city=:city,town=:town,lng=:lng,lat=:lat,stage_address=:stage_address,update_time=:update_time,status=0,type=2,is_new=1 where sid=:sid");
        $array = array(
            ':mobile' => $mobile,
            ':province' => $province_id,
            ':city' => $city_id,
            ':town' => $town_id,
            ':lng' => $lng,
            ':lat' => $lat,
            ':stage_address' => $address,
            ':update_time' => date('Y-m-d H:i:s'),
            ':sid' => $sid
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if ($rs < 1) {
            return 0;
        }
        //$this->setBusinessCover($sid,$uid,$cover);
        $this->modifyPerfech($sid);//修改完善状态
        $this->clearStageData($sid);//清除缓存里驿站信息
        return 1;
    }

    //修改驿站信息
    public function updateInfo($sid, $intro, $icon, $cover, $tel, $shop_hours, $uid, $type)
    {
        //$intro = Common::deleteHtml($intro);
        $stmt = $this->db->prepare("update stage set intro=:intro,icon=:icon,update_time=:update_time where sid=:sid");
        $array = array(
            ':intro' => $intro,
            ':icon' => $icon,
            ':update_time' => date('Y-m-d H:i:s'),
            ':sid' => $sid
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if ($rs < 1) {
            return 0;
        }
        if ($type == 2) {
            $this->modifyBusiness($sid, $tel, $shop_hours);
            $this->setBusinessCover($sid, $uid, $cover);
        }
        $this->clearStageData($sid);//清除缓存里驿站信息
        return 1;
    }

    //获取驿站icon
    public function getBusinessIcon($sid)
    {
        $stmt = $this->db->prepare("SELECT * FROM business_cover WHERE sid=:sid AND type = 0 and status = 1");
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //更新商家驿站详细信息
    public function modifyBusiness($sid, $tel, $shop_hours)
    {
        $stmt = $this->db->prepare("update business set tel=:tel,shop_hours=:shop_hours,is_perfect=:is_perfect,update_time=:update_time where sid=:sid");
        $array = array(
            ':tel' => $tel,
            ':shop_hours' => $shop_hours,
            ':is_perfect' => 1,
            ':update_time' => date('Y-m-d H:i:s'),
            ':sid' => $sid
        );
        $stmt->execute($array);
        $stmt->rowCount();
        $this->clearStageData($sid);//清除缓存里驿站信息
    }

    //插入商家驿站图标
    public function setBusinessIcon($sid, $uid, $icon)
    {
        $stmt_inco = $this->db->prepare("insert into business_cover (sid,uid,path,type,status) values (:sid,:uid,:path,0,1)");
        $array_icon = array(
            ':sid' => $sid,
            ':uid' => $uid,
            ':path' => $icon
        );
        $stmt_inco->execute($array_icon);
        $this->clearStageData($sid);//清除缓存里驿站信息
    }

    //插入商家驿站图片
    public function setBusinessCover($sid, $uid, $cover = array())
    {
        $stmt = $this->db->prepare("UPDATE business_cover SET status = 0,update_time=:update_time WHERE sid=:sid");
        $array = array(
            ':update_time' => date('Y-m-d H:i:s'),
            ':sid' => $sid
        );
        $stmt->execute($array);
        if ($cover) {
            foreach ($cover as $v) {
                $stmt_cover = $this->db->prepare("insert into business_cover (sid,uid,path,status) values (:sid,:uid,:path,1)");
                $array_cover = array(
                    ':sid' => $sid,
                    ':uid' => $uid,
                    ':path' => $v
                );
                $stmt_cover->execute($array_cover);
            }
        }
        $this->clearStageData($sid);//清除缓存里驿站信息
    }

    //更新商家驿站icon
    public function updateBusinessIcon($sid, $uid, $icon, $id)
    {
        $stmt = $this->db->prepare("UPDATE business_cover SET path=:path,uid=:uid,update_time=:update_time WHERE sid=:sid AND id=:id");
        $array = array(
            ':path' => $icon,
            ':uid' => $uid,
            ':update_time' => date('Y-m-d H:i:s'),
            ':sid' => $sid,
            ':id' => $id
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if ($rs < 1) {
            return 0;
        }
        $this->clearStageData($sid);//清除缓存里驿站信息
        return 1;
    }

    //删除商家驿站图片
    public function updateBusinessCover($sid, $uid, $cover)
    {
        $stmt = $this->db->prepare("UPDATE business_cover SET uid=:uid,status = 0,update_time=:update_time WHERE sid=:sid AND path=:path");
        $array = array(
            ':uid' => $uid,
            ':update_time' => date('Y-m-d H:i:s'),
            ':sid' => $sid,
            ':path' => $cover
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if ($rs < 1) {
            return 0;
        }
        $this->clearStageData($sid);//清除缓存里驿站信息
        return 1;

    }

    /**
     * 更新驿站基本信息
     */
    public function updateStage($data)
    {
        if ($data['stage_status'] == 1) {
            $is_stage_check = $this->getStageCheckByType($data['sid'], $data['stageCheck']);
            if ($is_stage_check) {
                $stmt = $this->db->prepare("update stage_check set cate_id=:cate_id,name=:name,intro=:intro,status=0 where id=:id");
                $array = array(
                    ':cate_id' => $data['cate_id'],
                    ':name' => $data['name'],
                    ':intro' => $data['intro'],
                    ':id' => $is_stage_check,
                );
                $stmt->execute($array);
            } else {
                $stmt = $this->db->prepare("insert into stage_check (sid,uid,cate_id,name,type,intro,add_time)
        values (:sid,:uid,:cate_id,:name,:type,:intro,:add_time)");
                $array = array(
                    ':sid' => $data['sid'],
                    ':uid' => $data['uid'],
                    ':cate_id' => $data['cate_id'],
                    ':name' => $data['name'],
                    ':type' => $data['stageCheck'],
                    ':intro' => $data['intro'],
                    ':add_time' => date('Y-m-d H:i:s'),
                );
                $stmt->execute($array);
            }
            //$intro = Common::deleteHtml($intro);
            $stmt_stage = $this->db->prepare("update stage set status=:status,permission=:permission,uid=:uid,mobile=:mobile,update_time=:update_time,add_time=:add_time,lng=:lng,lat=:lat,stage_address=:stage_address,province=:province,city=:city,town=:town where sid=:sid ");
            $array_stage = array(
                ':status' => 0,
                ':mobile' => $data['mobile'],
                ':uid' => $data['uid'],
                ':permission' => $data['permission'],
                ':update_time' => date('Y-m-d H:i:s'),
                ':add_time' => date('Y-m-d H:i:s'),
                ':lng' => $data['lng'],
                ':lat' => $data['lat'],
                ':stage_address' => $data['stage_address'],
                ':province' => $data['province'],
                ':city' => $data['city'],
                ':town' => $data['town'],
                ':sid' => intval($data['sid'])
            );
            $stmt_stage->execute($array_stage);
            $rs = $stmt_stage->rowCount();
            if ($rs < 1) {
                return 0;
            }
        }
        if ($data['stage_status'] == 2) {
            $stmt_stage = $this->db->prepare("update stage set name=:name,intro=:intro,status=:status,permission=:permission,uid=:uid,mobile=:mobile,cate_id=:cate_id,update_time=:update_time,add_time=:add_time,lng=:lng,lat=:lat,stage_address=:stage_address,province=:province,city=:city,town=:town where sid=:sid ");
            $array_stage = array(
                ':name' => $data['name'],
                ':intro' => $data['intro'],
                ':status' => 0,
                ':mobile' => $data['mobile'],
                ':cate_id' => $data['cate_id'],
                ':uid' => $data['uid'],
                ':permission' => $data['permission'],
                ':update_time' => date('Y-m-d H:i:s'),
                ':add_time' => date('Y-m-d H:i:s'),
                ':lng' => $data['lng'],
                ':lat' => $data['lat'],
                ':stage_address' => $data['stage_address'],
                ':province' => $data['province'],
                ':city' => $data['city'],
                ':town' => $data['town'],
                ':sid' => intval($data['sid'])
            );
            $stmt_stage->execute($array_stage);
            $rs = $stmt_stage->rowCount();
            if ($rs < 1) {
                return 0;
            }
        }
        $tagModel = new TagModel();
        $tagModel->saveRelation(2, $data['sid'], $data['tag']);
        $this->clearStageData($data['sid']);//清除缓存里驿站信息
        return 1;
    }

    /*
     * 清空缓存里驿站信息
     */
    public function clearStageData($sid)
    {
        $sKey = "stage:info:" . $sid;
        $this->redis->del($sKey);
        $this->contentRedis->del(Common::getRedisKey(5) . $sid);
    }

    /*
     * 缓存驿站信息
     */
    public function getStageData($sid)
    {
        if ($sid < 1) {
            return false;
        }
        $sKey = "stage:info:" . $sid;
        $stageData = $this->redis->hGetAll($sKey);
        if (!$stageData || !isset($stageData['user_num'])) {
            if ($stageData = $this->getBasicStageBySid($sid)) {
                $this->redis->hMSet($sKey, $stageData);
            }
        }
        return $stageData;
    }

    /*
     * 加入驿站,不发动态
     */
    public function insertStageUser($uid, $sid, $role, $status)
    {
        $array = array(
            ':sid' => $sid,
            ':uid' => $uid,
            ':role' => $role,
            ':status' => $status,
            ':add_time' => date('Y-m-d H:i:s')
        );
        $stmt = $this->db->prepare("insert into stage_user (uid,sid,role,status,add_time) values (:uid,:sid,:role,:status,:add_time)
        on duplicate key update role = :role,status = :status,add_time = :add_time");
        $stmt->execute($array);
        return 1;
    }

    /**
     * 判断该驿站是否存在
     */
    public function stageIsExist($sid)
    {
        $stmt = $this->db->prepare("select count(sid) as num from stage where sid=:sid and status<2");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /**
     * 用户加入该驿站
     */
    public function joinStage($sid, $uid, $role, $num, $status = 1)
    {
        $stage_num = $this->stageIsExist($sid);
        if ($stage_num == 0) {
            return -1;
        }
        $is_join = $this->isJoinStage($sid, $uid);
        if ($is_join) {
            return -2;
        }
        $join_stage_num = $this->getManageStageNum($uid, 3);
        if ($join_stage_num >= $num) {
            return -3;
        }
        $stage_info = $this->getBasicStageBySid($sid);
        if ($stage_info['permission'] == 1) {
            $status = 1;
        } else if ($stage_info['permission'] == 2) {
            $status = 0;
        }
        $stmt = $this->db->prepare("insert into stage_user (uid,sid,role,status,add_time) values (:uid,:sid,:role,:status,:add_time)
        on duplicate key update role = :role,status = :status,add_time = :add_time");
        $array = array(
            ':sid' => $sid,
            ':uid' => $uid,
            ':role' => $role,
            ':status' => $status,
            ':add_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $userModel = new UserModel();
        if ($stage_info['permission'] == 1) {
            $this->updateUserCount($sid);//权限自由加入，更新驿站成员数，同时清除缓存里驿站信息
        }
        $userModel->clearUserData($uid);//权限自由加入，清除缓存里用户信息（更新用户驿站总数）
        if ($stage_info['permission'] == 2) {
            return 2;
        }
        //添加加入驿站到动态
        //Common::http(OPEN_DOMAIN."/common/addFeed",array('scope'=>1,'uid'=>$uid,'type'=>'stage',"id"=>$sid,"time"=>time()),"POST");
        //该用户加入该驿站默认关注驿长
        $followModel = new FollowModel();
        if ($uid != $stage_info['uid']) {
            $isFollow = $followModel->isFollow($uid, $stage_info['uid']);
            if (!$isFollow) {
                $count = $followModel->getFollowNum($uid);
                if ($count < 1500) {
                    $followModel->add($uid, $stage_info['uid']);
                }
            }
        }
        return 1;
    }

    /**
     * 用户退出驿站
     */
    public function exitStage($sid, $uid)
    {
        $stage_num = $this->stageIsExist($sid);
        if ($stage_num == 0) {
            return -1;
        }
        $is_join = $this->isJoinStage($sid, $uid);
        if (!$is_join) {
            return -2;
        }
        if ($is_join['role'] == 1) {
            return -3;
        }
        $stmt = $this->db->prepare("update stage_user set status = 2 , update_time = :update_time where sid=:sid and uid=:uid");
        $array = array(
            ':sid' => $sid,
            ':uid' => $uid,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        $userModel = new UserModel();
        $this->updateUserCount($sid);//更新驿站成员数，同时清除缓存里驿站信息
        $userModel->clearUserData($uid);//清除缓存里用户信息（更新用户驿站总数）
        return 1;
    }

    /**
     * 当前用户是否加入该驿站及加入驿站信息
     */
    public function isJoinStage($sid, $uid)
    {
        $stmt_select = $this->db->prepare("select id,sid,uid,role from stage_user where uid=:uid and sid =:sid and status=1");
        $array = array(
            ':sid' => $sid,
            ':uid' => $uid,
        );
        $stmt_select->execute($array);
        $result = $stmt_select->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return 0;
        }
        return 1;
    }

    /**
     * 当前用户加入驿站信息
     */
    public function joinStageRole($sid, $uid)
    {
        $stmt_select = $this->db->prepare("select id,sid,uid,role from stage_user where uid=:uid and sid =:sid and status=1");
        $array = array(
            ':sid' => $sid,
            ':uid' => $uid,
        );
        $stmt_select->execute($array);
        $result = $stmt_select->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 根据驿站id查询驿站信息（H5用）
     */
    public function getStageById($sid, $uid = 0)
    {
        $stmt = $this->db->prepare("select sid,name,intro,cate_id,mobile,uid,cover,icon,qrcode_img,user_num,topic_num,height,permission,add_time,last_topic_time,type,status,province,city,town,authority,lng,lat,stage_address from stage where sid=:sid");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            if (!$result['height']) {
                $result['height'] = 0;
            }
            $result['intro'] = Common::deleteStageHtml($result['intro']);
            $culture_cate_info = $this->getCultureCateById($result['cate_id']);
            $result['culture_cate_name'] = $culture_cate_info['name'];
            $result['province'] = $result['province'] ? $result['province'] : '';
            $result['city'] = $result['city'] ? $result['city'] : '';
            $result['town'] = $result['town'] ? $result['town'] : '';
            $result['lng'] = $result['lng'] ? $result['lng'] : '';
            $result['lat'] = $result['lat'] ? $result['lat'] : '';
            /* $result['notice'] = $result['notice'] ? $result['notice'] : '';
             $result['notice_time'] = $result['notice_time'] ? $result['notice_time'] : '';*/
            $addressModel = new AddressModel();
            $result['province_name'] = $result['province'] ? $addressModel->getNameById($result['province']) : '';
            $result['city_name'] = $result['city'] ? $addressModel->getNameById($result['city']) : '';
            $result['town_name'] = $result['town'] ? $addressModel->getNameById($result['town']) : '';
            $result['stage_address'] = $result['stage_address'] ? $result['stage_address'] : '';
            $userModel = new UserModel();
            $result['user'] = $userModel->getUserData($result['uid'], $uid);
            $result['cover'] = $result['cover'] ? $result['cover'] : '';

        }
        return $result;
    }

    public function getCheckInfo($sid, $type)
    {
        $stmt = $this->db->prepare("select * from stage_check where sid=:sid and type=:type");
        $array = array(
            ':sid' => $sid,
            ':type' => $type
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function getStageInfoById($sid, $type = 1)
    {
        $stmt = $this->db->prepare("select sid,uid,name,intro,cate_id,mobile,icon,permission,type,province,city,town,authority,lng,lat,stage_address,status,is_new from stage where sid=:sid");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            if ($result['is_new'] == 0) {
                $result['intro'] = Common::deleteStageHtml($result['intro']);
            } else {
                $checkInfo = $this->getCheckInfo($sid, 1);
                $result['intro'] = Common::deleteStageHtml($checkInfo['intro']);
                $result['name'] = $checkInfo['name'];
            }
            $culture_cate_info = $this->getCultureCateById($result['cate_id']);
            $result['culture_cate_name'] = $culture_cate_info['name'];
            $result['province'] = $result['province'] ? $result['province'] : '';
            $result['city'] = $result['city'] ? $result['city'] : '';
            $result['town'] = $result['town'] ? $result['town'] : '';
            $result['lng'] = $result['lng'] ? $result['lng'] : '';
            $result['lat'] = $result['lat'] ? $result['lat'] : '';
            $addressModel = new AddressModel();
            $result['province_name'] = $result['province'] ? $addressModel->getNameById($result['province']) : '';
            $result['city_name'] = $result['city'] ? $addressModel->getNameById($result['city']) : '';
            $result['town_name'] = $result['town'] ? $addressModel->getNameById($result['town']) : '';
            $result['stage_address'] = $result['stage_address'] ? $result['stage_address'] : '';
            if ($result['type'] == 2 || $type == 2) {
                $businessInfo = $this->getBusinessInfo($sid);
                $result['service_type'] = $businessInfo['service_type'];
                $result['identity_img'] = $businessInfo['identity_img'];
                $result['license_img'] = $businessInfo['license_img'] ? $businessInfo['license_img'] : "";
                $result['contacts'] = $businessInfo['contacts'];
                $result['bank'] = $businessInfo['bank'] ? $businessInfo['bank'] : "";
                $result['bank_no'] = $businessInfo['bank_no'] ? $businessInfo['bank_no'] : "";
                $result['email'] = $businessInfo['email'] ?: "";
                $result['shop_hours'] = $businessInfo['shop_hours'];
                $result['tel'] = $businessInfo['tel'];
                $result['business_scope'] = $businessInfo['business_scope'] ? $businessInfo['business_scope'] : "";
            }
        }
        return $result;
    }

    /**
     * 根据驿站id查询驿站基本信息
     */
    public function getBasicStageBySid($sid, $status = 3)
    {
        $condition = $status == 1 ? ' and status=1' : ' and status<5';
        $stmt = $this->db->prepare("select sid,uid,name,intro,cate_id,mobile,uid,cover,icon,qrcode_img,user_num,topic_num,event_num,height,permission,authority,status,type,add_time,province,city,town,is_new,lng,lat,stage_address,is_extend,is_sp_agreement from stage where sid=:sid $condition");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $result['intro'] = Common::deleteStageHtml($result['intro']);
        }
        $result['businessInfo'] = $this->getBusiness($sid);
        $result['imgs'] = $this->getBusinessCover($sid);
        return $result;
    }

    public function getBasicStageBySidForHtml($sid, $status = 3)
    {
        $condition = $status == 1 ? ' and status=1' : ' and status<5';
        $stmt = $this->db->prepare("select sid,uid,name,intro,cate_id,mobile,uid,cover,icon,qrcode_img,user_num,topic_num,event_num,height,permission,authority,status,type,add_time,province,city,town from stage where sid=:sid $condition");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $result['intro'] = Common::deleteStageHtml($result['intro']);
        }
        $result['businessInfo'] = $this->getBusiness($sid);
        $result['imgs'] = $this->getBusinessCover($sid);
        return $result;
    }

    //商家驿站封面
    public function getBusinessCover($sid)
    {
        $stmt = $this->db->prepare("SELECT * FROM business_cover  WHERE sid=:sid and status = 1 ");
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 查询商家驿站信息
     */
    public function getBusiness($sid)
    {
        $stmt = $this->db->prepare("SELECT license_number,identity_img,type,license_img,contacts,tel,shop_hours FROM business  WHERE sid=:sid ");
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return (object)array();
        }
        return $result;
    }

    /**
     * 查询商家驿站信息
     */
    public function getBusinessInfo($sid)
    {
        $stmt = $this->db->prepare("SELECT identity_img,type as service_type,license_img,contacts,mobile,tel,shop_hours,business_scope,email,bank,bank_no FROM business  WHERE sid=:sid ");
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 随机该分类下获得官方推荐的驿站封面和驿站图标
     */
    public function getCoverIconRandom($cate_id, $type)
    {
        $stmt = $this->db->prepare("select id,cate_id,path from stage_cover where cate_id=:cate_id
        and type = :type and status=1 order by rand() limit 1");
        $array = array(
            ':cate_id' => $cate_id,
            ':type' => $type,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['path'];
    }

    /**
     * 更新驿站表中的驿站成员数目
     */
    public function updateUserCount($sid)
    {
        $sql = "update stage set user_num = (SELECT COUNT(1) FROM stage_user WHERE sid= :sid AND status=1) where sid = :sid";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        $this->clearStageData($sid);//清除缓存里驿站信息
        return 1;
    }

    /**
     * 统计我的驿站数量
     */
    public function getStageNum($uid)
    {
        $stmt = $this->db->prepare("select sum(num) from(select count(id) as num from stage_user where uid=:uid and role=1 and status=1 and
        sid in (select sid from stage where status<3) union all select count(id) as num from stage_user where uid=:uid and role=2 and status=1
        and sid in (select sid from stage where (type=1 and status=1) or (type=2 and status<2)) union all select count(id) as num from stage_user where uid=:uid and role=3
        and status<2 and sid in (select sid from stage where (type=1 and status=1) or (type=2 and status<2))) t");
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_COLUMN);
        return $result;
    }

    /*
     * 不包括审核中和审核未通过的驿站
     */
    public function getStageOther($uid)
    {
        $sql = 'select count(id) as num from stage_user where uid=:uid and status<2 and sid in (select sid from stage where (type=1 and status=1) or (type=2 and status<2))';
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_COLUMN);
        return $result;
    }

    /**
     * 当前用户加入驿站信息（加入和审核中）
     */
    public function getJoinStage($sid, $uid)
    {
        $stmt_select = $this->db->prepare("select id,sid,uid,role,status from stage_user where uid=:uid and sid =:sid and status<2");
        $array = array(
            ':sid' => $sid,
            ':uid' => $uid,
        );
        $stmt_select->execute($array);
        $result = $stmt_select->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return 0;
        }
        if ($result['status'] == 1) {
            return 1;
        }
        if ($result['status'] == 0) {
            return 2;
        }
    }

    //获取驿站状态
    public function getStatus($sid)
    {
        $stmt = $this->db->prepare("select status,type from stage where sid =:sid ");
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $rs = $this->getIsPerfect($sid);
        if ($result) {
            $result['is_new'] = 1;
            $result['is_perfect'] = $rs['is_perfect'];
        }
        return $result;
    }

    public function getIsPerfect($sid)
    {
        $stmt = $this->db->prepare("select is_perfect from business where sid =:sid ");
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //修改驿站完善状态
    public function modifyPerfech($sid)
    {
        $stmt = $this->db->prepare("update business set is_perfect=1, update_time = :update_time where sid=:sid ");
        $array = array(
            ':sid' => $sid,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $stmt->rowCount();
        $this->clearStageData($sid);//清除缓存里驿站信息
    }

    /**
     * 发帖时修改redis中新活动的数量
     * @param $sid
     * @param int $num
     */
    public function modifyRedisNewEventNum($sid, $num = 1)
    {
        $key = 's:nevent:' . $sid;
        if ($this->redis->exists($key)) {
            $fields = $this->redis->hKeys($key);
            foreach ($fields as $field) {
                $this->redis->hIncrBy($key, $field, $num);
            }
        } else {
            $users = $this->getStageOwnerAndAdmin($sid);
            foreach ($users as $v) {
                $this->redis->hSetNx($key, $v['uid'], $num);  //不存在默认设为1  因为刚发好帖子
            }
        }
    }

    /**
     * 获取驿站的驿站主和管理员
     * @param $sid
     * @return mixed
     */
    public function getStageOwnerAndAdmin($sid)
    {
        $sql = 'select uid from stage_user where sid = :sid and status = 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':sid' => $sid));
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rs;
    }

    /**
     * 设置商家驿站成员角色
     */
    public function setMemberRole($sid, $uid, $role, $num, $s_uid, $m_num)
    {
        $stage_num = $this->stageIsExist($sid);
        if ($stage_num == 0) {
            return -1;
        }
        $s_is_join = $this->joinStageRole($sid, $s_uid);
        if (!$s_is_join || $s_is_join['role'] != 1) {
            return -2;
        }
        $is_join = $this->isJoinStage($sid, $uid);
        if (!$is_join) {
            $rs = $this->joinStage($sid, $uid, 3, 200);
            if ($rs == -3) {
                return -3;
            }
        }
        if ($role == 2) {
            $admin_num = $this->getAdminNumBySid($sid);
            if ($admin_num >= $num) {
                return -4;
            }
            $manage_num = $this->getManageStageNum($uid, 2);
            if ($manage_num >= $m_num) {
                return -5;
            }
        }
        $stmt = $this->db->prepare("update stage_user set role=:role,add_time = :add_time, update_time = :update_time where sid=:sid and uid=:uid");
        $array = array(
            ':role' => $role,
            ':sid' => $sid,
            ':uid' => $uid,
            ':add_time' => date('Y-m-d H:i:s'),
            ':update_time' => date('Y-m-d H:i:s'),
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        $this->clearStageData($sid);//清除缓存里驿站信息
        return 1;
    }

    /**
     * 获得该驿站下驿管数量
     */
    public function getAdminNumBySid($sid)
    {
        $stmt = $this->db->prepare("SELECT count(id) as num FROM stage_user WHERE sid=:sid and role=2 and status=1");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /**
     * 统计我管理的驿站数量、我加入的驿站数量
     */
    public function getManageStageNum($uid, $role = 2)
    {
        $field = '';
        if ($role == 2) {
            $field = 'and role<=2';
            $stage_field = 'status < 3';
        } else if ($role == 3) {
            $field = 'and role=3';
            $stage_field = 'status < 2';
        }
        $stmt = $this->db->prepare("select count(id) as num from stage_user where uid=:uid and status<2  $field and sid in(select sid from stage where $stage_field)");
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /**
     * 用户加入的驿站列表
     */
    public function getJoinStageList($uid, $s_uid, $type, $page, $size)
    {
        $fields = '';

        if ($type) {
            $fields = ' and type =' . $type . '';
        }
        $start = ($page - 1) * $size;
        $stmt = $this->db->prepare("select sid from stage_user where status=1 and uid=:uid and sid in(select sid from stage where status<2 $fields)
        order by add_time desc limit :start,:size");
        $stmt->bindValue(':uid', $s_uid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $key => $val) {
            $result[$key] = $this->getBasicStageBySid($val['sid']);
            $result[$key]['name'] = Common::msubstr($result[$key]['name'], 0, 8, 'UTF-8', false);
            $result[$key]['join_info'] = $this->getJoinStage($val['sid'], $uid);
        }
        return $result;
    }

    //用户的驿站列表 (创建、管理、加入)
    public function getStageList($uid, $role = 3, $flag)
    {
        $fields = 'role = ' . $role . ' and';
        if ($flag == 1 && $role == 1) {
            $stmt = $this->db->prepare("select sid from stage_user where $fields status< 2 and uid=:uid and sid in(select sid from stage where status< 3)
        order by add_time desc");
        } elseif ($flag == 1 && $role > 1 || $flag == 0) {
            $stmt = $this->db->prepare("SELECT sid FROM stage WHERE ((TYPE=1 AND STATUS=1) OR (TYPE=2 AND STATUS<2)) AND sid IN(SELECT sid FROM stage_user WHERE $fields STATUS< 2 AND uid=:uid) ORDER BY last_topic_time DESC,add_time DESC ");
        }
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $sids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        foreach ($sids as $v) {
            $list[] = $this->getBasicStageBySid($v['sid']);
        }
        return $list;
    }

    //用户创建的文化驿站列表
    public function getCultureStageList($uid)
    {
        $stmt = $this->db->prepare("select sid from stage_user where role = 1 and status=1 and uid=:uid and sid in(select sid from stage where status=1 and type=1)
        order by add_time desc");
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $sids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        foreach ($sids as $v) {
            $list[] = $this->getBasicStageBySid($v['sid']);
        }
        return $list;
    }

    //驿站订阅(加入驿站)统计 type 1日 2周 3月
    public function joinStatistics($sid, $type)
    {
        $field = '';
        if ($type == 1) {
            $field = 'DAY(add_time) = DAY(CURDATE()) AND MONTH(add_time) = MONTH(CURDATE()) AND WEEK(add_time) = WEEK(CURDATE()) AND YEAR(add_time) = YEAR(CURDATE()) AND';
        } elseif ($type == 2) {
            $field = 'MONTH(add_time) = MONTH(CURDATE()) AND WEEK(add_time) = WEEK(CURDATE()) AND YEAR(add_time) = YEAR(CURDATE()) AND';
        } elseif ($type == 3) {
            $field = 'MONTH(add_time) = MONTH(CURDATE()) AND YEAR(add_time) = YEAR(CURDATE()) AND';
        }
        $stmt = $this->db->prepare("SELECT COUNT(id) as num FROM stage_user WHERE $field sid =:sid AND STATUS < 2 AND role> 1");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result['num']) {
            return 0;
        }
        return $result['num'];
    }

    //商家驿站下活动参与人数 type 1日 2周 3月
    public function partakeNum($sid, $type)
    {
        $field = '';
        if ($type == 1) {
            $field = 'DAY(add_time) = DAY(CURDATE()) AND MONTH(add_time) = MONTH(CURDATE()) AND WEEK(add_time) = WEEK(CURDATE()) AND YEAR(add_time) = YEAR(CURDATE()) AND';
        } elseif ($type == 2) {
            $field = 'MONTH(add_time) = MONTH(CURDATE()) AND WEEK(add_time) = WEEK(CURDATE()) AND YEAR(add_time) = YEAR(CURDATE()) AND';
        } elseif ($type == 3) {
            $field = 'MONTH(add_time) = MONTH(CURDATE()) AND YEAR(add_time) = YEAR(CURDATE()) AND';
        }
        $stmt = $this->db->prepare("SELECT SUM(partake_num) AS num FROM event WHERE $field sid =:sid AND STATUS < 2");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result['num']) {
            return 0;
        }
        return $result['num'];
    }

    //商家驿站的普通帖子分享数
    public function topicShareNum($sid, $type)
    {
        $field = '';
        if ($type == 1) {
            $field = 'DAY(add_time) = DAY(CURDATE()) AND MONTH(add_time) = MONTH(CURDATE()) AND WEEK(add_time) = WEEK(CURDATE()) AND YEAR(add_time) = YEAR(CURDATE()) AND';
        } elseif ($type == 2) {
            $field = 'MONTH(add_time) = MONTH(CURDATE()) AND WEEK(add_time) = WEEK(CURDATE()) AND YEAR(add_time) = YEAR(CURDATE()) AND';
        } elseif ($type == 3) {
            $field = 'MONTH(add_time) = MONTH(CURDATE()) AND YEAR(add_time) = YEAR(CURDATE()) AND';
        }
        $stmt = $this->db->prepare("SELECT COUNT(id) AS num FROM `share` WHERE obj_id IN(
                                     SELECT t.id FROM stage AS s LEFT JOIN topic AS t
                                    ON s.sid = t.sid
                                    WHERE s.sid =:sid AND s.status < 2 AND t.status < 2)
                                    AND $field TYPE = 4");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result['num']) {
            return 0;
        }
        return $result['num'];
    }

    //商家驿站的商家活动分享数
    public function eventShareNum($sid, $type)
    {
        $field = '';
        if ($type == 1) {
            $field = 'DAY(add_time) = DAY(CURDATE()) AND MONTH(add_time) = MONTH(CURDATE()) AND WEEK(add_time) = WEEK(CURDATE()) AND YEAR(add_time) = YEAR(CURDATE()) AND';
        } elseif ($type == 2) {
            $field = 'MONTH(add_time) = MONTH(CURDATE()) AND WEEK(add_time) = WEEK(CURDATE()) AND YEAR(add_time) = YEAR(CURDATE()) AND';
        } elseif ($type == 3) {
            $field = 'MONTH(add_time) = MONTH(CURDATE()) AND YEAR(add_time) = YEAR(CURDATE()) AND';
        }
        $stmt = $this->db->prepare("SELECT COUNT(id) AS num FROM `share` WHERE obj_id IN(
                                     SELECT e.id FROM stage AS s LEFT JOIN event AS e
                                    ON s.sid = e.sid
                                    WHERE s.sid =:sid AND s.status < 2 AND e.status < 2)
                                    AND $field TYPE = 10");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result['num']) {
            return 0;
        }
        return $result['num'];
    }

    //商家驿站下的普通帖子id
    public function getTidBySid($sid)
    {
        $stmt = $this->db->prepare("SELECT id FROM topic WHERE sid =:sid AND STATUS < 2");
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //商家驿站下的活动id
    public function getEidBySid($sid)
    {
        $stmt = $this->db->prepare("SELECT id FROM event WHERE sid =:sid AND STATUS < 2");
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //用户加入的驿站 type  1普通驿站 2商家驿站
    public function userJoinStageByType($uid, $type)
    {
        $stmt = $this->db->prepare("SELECT s.sid FROM stage_user AS su
                LEFT JOIN stage AS s
                ON su.sid = s.sid
                WHERE su.status < 2 AND su.uid =:uid AND s.status < 2 AND s.type=:type");
        $array = array(
            ':uid' => $uid,
            ':type' => $type
        );
        $stmt->execute($array);
        $sids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($sids) {
            foreach ($sids as $v) {
                $sid[] = $v['sid'];
            }
            $sid = implode(',', $sid);
            return $sid;
        }
        return false;
    }

    //根据分类id 查询驿站列表
    public function getStageByCateId($cate_id, $page, $size)
    {
        $start = ($page - 1) * $size;
        $stmt = $this->db->prepare("SELECT * FROM stage WHERE STATUS =1 AND cate_id =:cate_id ORDER BY last_topic_time DESC limit :start,:size");
        $stmt->bindValue(':cate_id', $cate_id, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $sids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        foreach ($sids as $k => $v) {
            $list[$k] = $this->getBasicStageBySid($v['sid']);
        }
        return $list;
    }

    //根据分类id 查询驿站总数
    public function getStageNumByCateId($cate_id)
    {
        $stmt = $this->db->prepare("SELECT count(sid) as num FROM stage WHERE STATUS = 1 AND cate_id =:cate_id");
        $stmt->bindValue(':cate_id', $cate_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result['num']) {
            return 0;
        }
        return $result['num'];
    }

    //根据发帖时间查询驿站
    public function getStageByLastTopicTime($uid, $start, $size)
    {
        $stmt_sid = $this->db->prepare("SELECT sid FROM stage_user WHERE STATUS< 2 AND uid=$uid AND sid IN(SELECT sid FROM stage WHERE STATUS=1)");
        $stmt_sid->execute();
        $stmt_sid = $stmt_sid->fetchAll(PDO::FETCH_ASSOC);
        $fields = '';
        $sid = array();
        if ($stmt_sid) {
            foreach ($stmt_sid as $v) {
                $sid[] = $v['sid'];
            }
            $fields = 'AND sid NOT IN(' . implode(',', $sid) . ')';
        }
        $stmt = $this->db->prepare("SELECT * FROM stage WHERE STATUS =1 $fields ORDER BY last_topic_time DESC LIMIT $start,$size");
        $stmt->execute();
        $sids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        foreach ($sids as $k => $v) {
            $list[$k] = $this->getBasicStageBySid($v['sid']);
            $list[$k]['is_join'] = $this->getJoinStage($v['sid'], $uid);
        }
        return $list;
    }

    //附近的商家驿站
    public function vicinityBusiness($lat, $lng, $page, $size)
    {
        $start = ($page - 1) * $size;
        $stmt = $this->db->prepare("SELECT sid,get_distance(:lat,:lng,lat,lng) as distance FROM business  WHERE sid IN(SELECT sid FROM stage WHERE TYPE = 2 AND STATUS =1) order by distance asc limit :start,:size");
        $stmt->bindValue(':lat', $lat, PDO::PARAM_INT);
        $stmt->bindValue(':lng', $lng, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //根据uid获取用户创建的商家驿站id
    public function getSidByUid($uid)
    {
        $stmt = $this->db->prepare("SELECT sid,is_pay from stage where status = 1 and type = 2 and uid = :uid");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //根据uid获取用户创建的商家驿站id
    public function getBusinessNum($uid)
    {
        $stmt = $this->db->prepare("SELECT sid from stage where status in(0,1,2) and type = 2 and uid = :uid");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //根据驿站标签获取驿站
    public function getBusinessByTagId($tag_id, $start, $size)
    {
        $stmt = $this->db->prepare("SELECT st.sid FROM stage AS s LEFT JOIN app_stage_tag AS st
                                    ON s.sid = st.sid WHERE s.status < 2 AND st.status = 1 AND st.tag_id =:tag_id order by s.last_topic_time desc LIMIT :start,:size");
        $stmt->bindValue(':tag_id', $tag_id, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //根据驿站标签获取商家驿站总数
    public function getBusinessNumByTagId($tag_id)
    {
        $stmt = $this->db->prepare("SELECT COUNT(st.sid) AS num FROM stage AS s LEFT JOIN app_stage_tag AS st
                                    ON s.sid = st.sid WHERE s.status < 2 AND st.status = 1 AND st.tag_id =:tag_id");
        $stmt->bindValue(':tag_id', $tag_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result['num']) {
            return 0;
        }
        return $result['num'];
    }

    //获取推荐的驿站标签
    public function getPushStageTag()
    {
        $stmt = $this->db->prepare("SELECT id,name as content FROM app_tag
                                    WHERE status = 1 and is_recommend = 1  ORDER BY sort asc ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 查询该驿站的驿管列表
     */
    public function getAdminListBySid($sid, $uid = 0)
    {
        $stmt = $this->db->prepare("SELECT uid FROM stage_user WHERE sid=:sid and role=2 and status=1 order by add_time");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $key => $val) {
                $userModel = new UserModel();
                $user_info = $userModel->getUserData($val['uid'], $uid);
                $result[$key]['did'] = $user_info['did'];
                $result[$key]['nick_name'] = $user_info['nick_name'];
                $result[$key]['type'] = $user_info['type'];
                $result[$key]['avatar'] = Common::show_img($user_info['avatar'], 1, 200, 200);
            }
        }
        return $result;
    }

    /**
     * 查询该驿站的成员列表
     */
    public function getMemberListBySid($sid, $page, $size, $uid = 0)
    {
        $start = ($page - 1) * $size;
        $stmt = $this->db->prepare("SELECT uid FROM stage_user WHERE sid=:sid and role=3 and status=1 order by add_time desc,id limit :start,:size");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            $userModel = new UserModel();
            foreach ($result as $key => $val) {
                $user_info = $userModel->getUserData($val['uid'], $uid);
                $result[$key]['did'] = $user_info['did'];
                $result[$key]['nick_name'] = $user_info['nick_name'];
                $result[$key]['type'] = $user_info['type'];
                $result[$key]['avatar'] = Common::show_img($user_info['avatar'], 1, 200, 200);
            }
        }
        return $result;
    }

    /**
     * 统计该驿站下成员数目
     */
    public function getMemberNumBySid($sid)
    {
        $stmt = $this->db->prepare("SELECT count(id) as num FROM stage_user WHERE sid=:sid and role=3 and status=1");
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /**
     * 重置新帖子数量
     * @param $sid
     * @param $uid
     */
    public function resetRedisNewTopicNum($sid, $uid)
    {
        $key = 's:topic_event:' . $sid;
        if ($this->redis->hExists($key, $uid)) {
            $this->redis->hSet($key, $uid, 0);
        }
    }

    /**
     * 重置新活动数量
     * @param $sid
     * @param $uid
     */
    public function resetRedisNewEventNum($sid, $uid)
    {
        $key = 's:nevent:' . $sid;
        if ($this->redis->hExists($key, $uid)) {
            $this->redis->hSet($key, $uid, 0);
        }
    }

    /**
     * 获取redis中新帖子的数量
     * @param $sid
     * @param $uid
     * @return int
     */
    public function getRedisNewTopicNum($sid, $uid)
    {
        $key = 's:topic_event:' . $sid;
        if ($this->redis->exists($key)) {
            return $this->redis->hGet($key, $uid);
        }
        return 0;
    }

    /**
     * 获取redis中新活动的数量
     * @param $sid
     * @param $uid
     * @return int
     */
    public function getRedisNewEventNum($sid, $uid)
    {
        $key = 's:nevent:' . $sid;
        if ($this->redis->exists($key)) {
            return $this->redis->hGet($key, $uid);
        }
        return 0;
    }

    //按驿站成员登录时间获取列表
    public function getListByLoginTime($sid, $start, $size)
    {
        $stmt = $this->db->prepare("SELECT st.uid FROM stage_user AS st
                                    LEFT JOIN user AS u
                                    ON st.uid = u.uid
                                    WHERE st.status = 1 AND st.sid =:sid ORDER BY u.login_time DESC LIMIT :start,:size");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $userList = array();
        if ($result) {
            $userModel = new UserModel();
            foreach ($result as $k => $val) {
                $userInfo = $userModel->getUserData($val['uid']);
                $userInfo['avatar'] = Common::show_img($userInfo['avatar'], 1, 160, 160);
                $userList[$k]['uid'] = $userInfo['uid'];
                $userList[$k]['did'] = $userInfo['did'];
                $userList[$k]['nick_name'] = $userInfo['nick_name'];
                $userList[$k]['type'] = $userInfo['type'];
                $userList[$k]['avatar'] = Common::show_img($userInfo['avatar'], 1, 160, 160);

            }
        }
        return $userList;
    }

    //按驿站成员登录时间获取列表
    public function getNumByLoginTime($sid)
    {
        $stmt = $this->db->prepare("SELECT COUNT(st.uid) as num FROM stage_user AS st
                                    LEFT JOIN user AS u
                                    ON st.uid = u.uid
                                    WHERE st.status = 1 AND u.status < 2 AND st.sid =:sid ORDER BY u.login_time DESC");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //将审核不通过的商家驿站status状态改为4
    public function updateStageStatus($sid)
    {
        $stmt = $this->db->prepare("update stage set status=4 where sid=:sid");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        $this->clearStageData($sid);//清除缓存里驿站信息
        return 1;
    }

    /**
     * 当前用户加入驿站信息（加入和审核中）
     */
    public function joinStageInfo($sid, $uid)
    {
        $stmt_select = $this->db->prepare("select id,sid,uid,role,status from stage_user where uid=:uid and sid =:sid and status<2");
        $array = array(
            ':sid' => $sid,
            ':uid' => $uid,
        );
        $stmt_select->execute($array);
        $result = $stmt_select->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function modifyRedisNewTopicNumTotals($uid, $num)
    {
        $user_key = 's:topic_event_totals:' . $uid;
        $this->redis->hIncrBy($user_key, $uid, $num);
    }

    /*
    * 储存发帖时间到redis
    */
    public function addTopicViewTime($tid)
    {
        $key = "topic:view:time";
        $this->redis->hSet($key, $tid, time());
    }

    /**
     * 获取驿站的所有成员
     * @param $sid
     * @return mixed
     */
    public function getStageUser($sid)
    {
        $sql = 'select uid from stage_user where sid = :sid and status = 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':sid' => $sid));
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rs;
    }

    /**
     * 当前驿站是否正常
     */
    public function stageIsRight($sid)
    {
        $stmt = $this->db->prepare("select count(sid) as num from stage where sid=:sid and status = 1");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //修改驿站封面
    public function setCover($sid, $cover)
    {
        $stmt = $this->db->prepare("update stage set cover=:cover where sid=:sid");
        $array = array(
            ':cover' => $cover,
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        $this->clearStageData($sid);//清除缓存里驿站信息
        return 1;
    }

    //修改驿站封面
    public function updateIcon($sid, $icon)
    {
        $stmt = $this->db->prepare("update stage set icon=:icon where sid=:sid");
        $array = array(
            ':icon' => $icon,
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        $this->clearStageData($sid);//清除缓存里驿站信息
        return 1;
    }

    //根据驿站id和type获取对应列表
    public function getListBySid($sid, $cate, $is_good = 0, $page, $size, $token, $version, $uid = 0)
    {
        $start = ($page - 1) * $size;
        $condition = $is_good ? ' and is_good=1' : '';
        if ($cate == 'event') {
            $stmt = $this->db->prepare("SELECT id,add_time,last_comment_time,sid,2 AS c_type,type,is_notice,is_top,is_good FROM event
                WHERE STATUS < 2 AND sid=:sid
                ORDER BY is_notice DESC,is_top DESC,last_comment_time DESC limit :start,:size");
        } elseif ($cate == 'topic') {
            $stmt = $this->db->prepare("SELECT id,add_time,last_comment_time,sid,1 AS c_type,type,is_recommend AS is_notice,is_top,is_good
                FROM topic WHERE STATUS < 2 AND sid=:sid $condition
                ORDER BY is_notice DESC,is_top DESC,last_comment_time DESC limit :start,:size");
        }
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        $topicModel = new TopicModel();
        $userModel = new UserModel();
        $eventModel = new EventModel();
        if ($result) {
            foreach ($result as $k => $v) {
                if ($cate == 'topic') {
                    $topicInfo = $topicModel->getBasicTopicById($v['id']);
                    $list[$k]['id'] = $topicInfo['id'];
                    $list[$k]['title'] = $topicInfo['title'];
                    $list[$k]['summary'] = $topicInfo['summary'] ? Common::deleteHtml($topicInfo['summary']) : '该帖暂无文字描述，您可以先了解才府：才府是中国传统文化的交流学习平台,致力于通过善的商业模式,让更多传承和传播优秀传统文化的人实现社会价值和经济效益。';
                    $list[$k]['is_top'] = $topicInfo['is_top'];
                    $list[$k]['is_good'] = $topicInfo['is_good'];
                    $list[$k]['is_recommend'] = $topicInfo['is_recommend'];
                    $list[$k]['view_num'] = $topicInfo['view_num'];
                    $list[$k]['add_time'] = $topicInfo['add_time'];
                    if ($topicInfo['img_src']) {
                        foreach ($topicInfo['img_src'] as $k1 => $v1) {
                            $list[$k]['img'][$k1] = Common::show_img($v1, 4, 540, 540);
                        }
                    } else {
                        $list[$k]['img'] = array();
                    }
                    $list[$k]['url'] = $token ? I_DOMAIN . '/t/' . $v['id'] . '?token=' . $token . '&version=' . $version : I_DOMAIN . '/t/' . $v['id'] . '?version=' . $version;
                    $userInfo = $userModel->getUserData($topicInfo['uid'], $uid);
                    $list[$k]['user']['uid'] = $userInfo['uid'];
                    $list[$k]['user']['did'] = $userInfo['did'];
                    $list[$k]['user']['nick_name'] = $userInfo['nick_name'];
                }
                if ($cate == 'event') {
                    $eventInfo = $eventModel->getEvent($v['id']);
                    $list[$k]['id'] = $eventInfo['id'];
                    $list[$k]['title'] = $eventInfo['title'];
                    $list[$k]['type'] = $eventInfo['type'];
                    if ($eventInfo['type'] == 1) {
                        $type_info = $eventModel->getBusinessEventType($eventInfo['type_code']);
                        $list[$k]['type_name'] = $type_info ? $type_info['name'] : '综合';
                    } else {
                        $list[$k]['type_name'] = $this->event_type_name[$eventInfo['type']];
                    }
                    $list[$k]['max_partake'] = $eventInfo['max_partake'];
                    $list[$k]['summary'] = $eventInfo['summary'] ? $eventInfo['summary'] : '该帖暂无文字描述，您可以先了解才府：才府是中国传统文化的交流学习平台,致力于通过善的商业模式,让更多传承和传播优秀传统文化的人实现社会价值和经济效益。';
                    $list[$k]['event_address'] = $eventInfo['event_address'] ? $eventInfo['event_address'] : '';
                    $list[$k]['lng'] = $eventInfo['lng'] ? $eventInfo['lng'] : '';
                    $list[$k]['lat'] = $eventInfo['lat'] ? $eventInfo['lat'] : '';
                    $list[$k]['cover'] = Common::show_img($eventInfo['cover'], 4, 720, 540);
                    $list[$k]['start_time'] = $eventInfo['start_time'];
                    $list[$k]['end_time'] = $eventInfo['end_time'];
                    $list[$k]['url'] = $token ? I_DOMAIN . '/e/' . $v['id'] . '?token=' . $token . '&version=' . $version : I_DOMAIN . '/e/' . $v['id'] . '?version=' . $version;
                    $userInfo = $userModel->getUserData($eventInfo['uid'], $uid);
                    $list[$k]['user']['uid'] = $userInfo['uid'];
                    $list[$k]['user']['did'] = $userInfo['did'];
                    $list[$k]['user']['nick_name'] = $userInfo['nick_name'];
                }
            }
        }
        return $list;
    }

    /**
     * 删除驿站成员
     */
    public function delMember($sid, $uid, $s_uid)
    {
        $stage_num = $this->stageIsRight($sid);
        if ($stage_num == 0) {
            return -1;
        }
        $join_info = $this->getJoinStage($sid, $s_uid);
        if (!$join_info) {
            return -2;
        }
        if ($join_info == 2) {
            return -3;
        }
        $roleInfo = $this->joinStageRole($sid, $s_uid);
        if (!in_array($roleInfo['role'], array(1, 2))) {
            return -4;
        }
        $is_join = $this->isJoinStage($sid, $s_uid);
        if (!$is_join) {
            return -5;
        }
        $stmt = $this->db->prepare("update stage_user set status=3 where sid=:sid and uid=:uid");
        $array = array(
            ':sid' => $sid,
            ':uid' => $uid
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        $userModel = new UserModel();
        $this->updateUserCount($sid);//更新驿站成员数，同时清除缓存里驿站信息
        $userModel->clearUserData($uid);//清除缓存里用户信息（更新用户驿站总数）
        $this->initChatStageJoin($uid, $sid, 4);//删除成员对话更新状态
        return 1;
    }

    //驿站加入、退出、删除放入队列处理
    public function initChatStageJoin($uid, $sid, $type)
    {
        $key = "init:chatStage:uid";
        $this->redis->lPush($key, json_encode(array('uid' => $uid, 'sid' => $sid, 'type' => $type)));
    }

    /**
     * 获得驿站列表-根据最新发帖时间排序
     */
    public function getDynamicList($uid, $page, $size, $cate_id = '')
    {
        if ($size > 50) {
            $size = 50;
        }
        $start = ($page - 1) * $size;
        if ($cate_id) {
            $stmt = $this->db->prepare("select sid from stage where cate_id = :cate_id and status=1 and sid in (select sid from stage_user where uid =:uid and status = 1) order by last_topic_time desc,sid limit :start,:size");
            $stmt->bindValue(':cate_id', $cate_id, PDO::PARAM_INT);
        } else {
            $stmt = $this->db->prepare("select sid from stage where status=1 and sid in (select sid from stage_user where uid =:uid and status = 1) order by last_topic_time desc,sid limit :start,:size");
        }
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            $topicModel = new TopicModel();
            foreach ($result as $key => $val) {
                $result[$key] = $this->getBasicStageBySid($val['sid']);
                $result[$key]['topic_list'] = $topicModel->getNewTopicListBySid($val['sid'], 1, $uid);
            }
        }
        return $result;
    }

    /*
     * @判断用户是否有升级的商家驿站
     */
    public function isUpgrade($uid)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS num FROM stage_check  WHERE uid=:uid and status=0 and type=1");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    public function getStageCheckInfo($uid)
    {
        $stmt = $this->db->prepare("SELECT * FROM stage_check  WHERE uid=:uid and type=1 and status <2");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 当前用户待审核的驿站数
     */
    public function getCreateStageCheckNum($uid)
    {
        $stmt = $this->db->prepare("select count(sid) as num from stage where uid=:uid and status=0 and type = 1");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /**
     * 当前用户审核未通过的驿站数
     */
    public function getCreateStageCheckErrorNum($uid, $sid)
    {
        $stmt = $this->db->prepare("select count(sid) as num from stage where uid=:uid and status=2 and sid!=:sid");
        $array = array(
            ':uid' => $uid,
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /**
     * 当前用户审核通过的驿站数
     */
    public function getCreateNum($uid)
    {
        $stmt = $this->db->prepare("select count(sid) as num from stage where uid=:uid and status=1");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /**
     * 获取驿站的所有成员
     * @param $sid
     * @return mixed
     */
    public function getUser($sid, $page, $size)
    {
        $start = ($page - 1) * $size;
        $sql = 'select uid from stage_user where sid = :sid and status = 1 limit :start,:size';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rs;
    }

    //根据标签查找用户最多的驿站（标签绑驿站）
    public function getStageByTagId($tag_id, $limit, $page, $size)
    {
        $stmt = $this->db->prepare("SELECT sid FROM stage WHERE sid IN (SELECT sid FROM app_stage_tag WHERE tag_id =:tag_id AND status = 1) AND status = 1 ORDER BY user_num DESC LIMIT :limit");
        $stmt->bindValue(':tag_id', $tag_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$result) {
            return -1;
        }
        foreach ($result as $v) {
            $uids[] = $this->getUser($v['sid'], $page, $size);
        }
        $userModel = new UserModel();
        foreach ($uids as $k => $v) {
            foreach ($v as $v1) {
                $list[$k][] = $userModel->getUserData($v1['uid']);
            }
        }
        return $list;
    }

    //首页驿站基本信息查询
    public function getIndexStageInfo($sid)
    {
        $stmt = $this->db->prepare("select sid,name,type,cate_id,intro,topic_num,user_num,province,city,icon,add_time,update_time from stage where sid=:sid and status=1");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $result['intro'] = Common::deleteStageHtml($result['intro']);
        }
        return $result;
    }

    //首页场馆信息
    public function getSiteInfo($sid, $lat, $lng)
    {
        $stmt = $this->db->prepare("SELECT sid,get_distance(:lat,:lng,lat,lng) as distance,address as stage_address FROM business  WHERE sid =:sid");
        $stmt->bindValue(':lat', $lat, PDO::PARAM_INT);
        $stmt->bindValue(':lng', $lng, PDO::PARAM_INT);
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    //驿站信息
    public function getStage($sid)
    {
        $stmt = $this->db->prepare('select sid,name,icon,topic_num,user_num,qrcode_img,is_pay from stage where sid=:sid');
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //更新驿站图标二维码
    public function updateStageQRCodeImg($sid, $QRCodeImg)
    {
        $stmt = $this->db->prepare('update stage set qrcode_img = :qrcode_img , update_time =:update_time where sid=:sid');
        $array = array(
            ':qrcode_img' => $QRCodeImg,
            ':sid' => $sid,
            ':update_time' => date("Y-m-d H:i:s"),
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        return 1;
    }

    //服务驿站列表
    public function getBusinessList($last_id = 0, $size)
    {
        $fields = $last_id ? ' and sid <' . $last_id : '';
        $stmt = $this->db->prepare("select sid from stage where status=1 and type = 2 $fields order by sid desc limit :size");
        $stmt->bindValue(':size', $size, PDO :: PARAM_INT);
        $stmt->execute();
        $rs = $stmt->fetchALL(PDO::FETCH_ASSOC);
        //print_r($rs);exit;
        if ($rs) {
            foreach ($rs as $val) {
                $data[] = $this->getIndexStageInfo($val['sid']);
            }
        } else {
            $data = array();
        }
        return $data;
    }

    /**
     * 保存创建驿站信息
     */
    public function saveStage($data)
    {
        //随机获得该分类下官方推荐的驿站封面和驿站图标
        $icon = $this->getCoverIconRandom($data['cate_id'], 0);
        $cover = $this->getCoverIconRandom($data['cate_id'], 1);
        $stmt = $this->db->prepare("insert into stage (cate_id,name,intro,mobile,uid,cover,icon,permission,type,is_pay,origin,is_new,province,city,town,lng,lat,stage_address,add_time)
        values (:cate_id,:name,:intro,:mobile,:uid,:cover,:icon,:permission,:type,:is_pay,:origin,0,:province,:city,:town,:lng,:lat,:stage_address,:add_time)");
        $array = array(
            ':cate_id' => $data['cate_id'],
            ':name' => $data['name'],
            ':intro' => $data['intro'],
            ':mobile' => $data['mobile'],
            ':uid' => $data['uid'],
            ':cover' => $cover,
            ':icon' => $icon,
            ':permission' => $data['permission'],
            ':type' => $data['type'],
            ':is_pay' => $data['is_pay'],
            ':origin' => $data['origin'],
            ':province' => $data['province'],
            ':city' => $data['city'],
            ':town' => $data['town'],
            ':lng' => $data['lng'],
            ':lat' => $data['lat'],
            ':stage_address' => $data['stage_address'],
            ':add_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $sid = $this->db->lastInsertId();
        if ($sid < 1) {
            return 0;
        }
        // 创建驿站后就加入融云IM与之对应的群组
        (new RongCloudIM())->group()->join($data['uid'], $sid, $data['name']);
        $tagModel = new TagModel();
        $tagModel->saveRelation($data['tag_type'], $sid, $data['tag']);
        $this->insertStageUser($data['uid'], $sid, 1, 1);
        $userModel = new UserModel();
        $userModel->clearUserData($data['uid']);
        return $sid;
    }

    //驿站筛选 type 1服务 2文化 3场馆 0 全部
    public function getList($type, $id = '', $tag_id = '', $city = '', $sort = '', $page, $size)
    {
        $start = ($page - 1) * $size;
        $conditionCity = $city ? ' and (province=:city or city=:city)' : '';
        $conditionId = $id ? ' and cate_id=:id' : '';
        $conditionType = $type ? ' and type=:type' : '';
        $conditionTad = $tag_id ? ' and sid in (select sid from stage_tag where tag_id=:tag_id and status =1)' : '';
        $conditionSid = '';
        $conditionSort = '';
        if (!$sort) {
            $conditionSort = 'order by last_topic_time desc,add_time desc';
        } elseif ($sort == '最新') {
            $conditionSort = 'order by add_time desc';
        } elseif ($sort == '成员最多') {
            $conditionSort = 'order by user_num desc';
        } elseif ($sort == '内容最多') {
            $conditionSort = 'order by (topic_num + event_num) desc,add_time desc';
        }
        if ($type == 3) {
            if (!$sort) {
                $sql = "SELECT sid FROM app_stage WHERE STATUS=1 AND TYPE = 1 AND sid IN (SELECT sid FROM stage WHERE STATUS =1 AND TYPE = 2 $conditionCity $conditionSort) ORDER BY sort ASC,add_time DESC LIMIT :start,:size";
            } else {
                $sql = "SELECT sid FROM stage WHERE status =1 and type =2 AND sid IN (SELECT sid FROM app_stage WHERE STATUS =1) $conditionCity $conditionId $conditionTad $conditionSid $conditionSort LIMIT :start,:size";
            }
        } else {
            $sql = "SELECT sid FROM stage WHERE status =1 $conditionType $conditionCity $conditionId $conditionTad $conditionSid $conditionSort LIMIT :start,:size";
        }
        if ($sort == '活跃') {
            if (in_array($type, array(1, 2))) {
                $conditionType = $type ? ' and s.type=:type' : '';
            } else {
                $conditionType = ' and s.type=2';
            }
            if ($type != 3) {
                $conditionSid = $tag_id ? ' and s.sid in (select sid from stage_tag where tag_id=:tag_id and status =1)' : '';
            }
            $conditionId = $id ? ' and s.cate_id=:id' : '';
            $conditionCity = $city ? ' and (s.province=:city or s.city=:city)' : '';
            $conditionTopicNum = '(SELECT COUNT(*) FROM topic WHERE STATUS<2 AND sid=s.sid AND add_time>=:add_time)';
            $sql = "SELECT sid,$conditionTopicNum AS num FROM stage s WHERE s.status=1 $conditionType $conditionId $conditionCity $conditionSid AND $conditionTopicNum >0 ORDER BY num DESC,add_time desc LIMIT :start,:size";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        if (in_array($type, array(1, 2))) {
            $stmt->bindValue(':type', $type, PDO::PARAM_INT);
        }
        if ($id) {
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        }
        if ($city) {
            $stmt->bindValue(':city', $city, PDO::PARAM_INT);
        }
        if ($tag_id) {
            $stmt->bindValue(':tag_id', $tag_id, PDO::PARAM_INT);
        }
        if ($sort == '活跃') {
            $add_time = (string)date('Y-m-d', (time() - 3600 * 24 * 60)) . ' 00:00:00';
            $stmt->bindValue(':add_time', $add_time, PDO::PARAM_STR);
        }
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        if ($result) {
            foreach ($result as $k => $v) {
                $stageInfo = $this->getBasicStageBySid($v['sid']);
                $list[$k]['sid'] = $v['sid'];
                $list[$k]['name'] = $stageInfo['name'];
                $list[$k]['type'] = $stageInfo['type'];
                $list[$k]['icon'] = $stageInfo['icon'] ? IMG_DOMAIN . $stageInfo['icon'] : '';
                $list[$k]['intro'] = Common::deleteStageHtml($stageInfo['intro']);
                $list[$k]['user_num'] = $stageInfo['user_num'];
                $list[$k]['topic_num'] = $stageInfo['topic_num'];
                $cateInfo = $this->getCultureCateById($stageInfo['cate_id']);
                $list[$k]['cate_name'] = $cateInfo['name'];
                $addressModel = new AddressModel();
                $province_name = $addressModel->getNameById($stageInfo['province']);
                $city_name = $addressModel->getNameById($stageInfo['city']);
                $town_name = $addressModel->getNameById($stageInfo['town']);
                if ($province_name == $city_name) {
                    $address_name = $province_name . $town_name;
                } else {
                    $address_name = $province_name . $city_name . $town_name;
                }
                $list[$k]['stage_address'] = $address_name;
                $list[$k]['lng'] = $stageInfo['lng'];
                $list[$k]['lat'] = $stageInfo['lat'];
            }
        }
        return $list;
    }

    /**
     * 更新驿站基本信息
     */
    public function updateStageNew($sid, $icon, $name, $intro, $mobile, $uid, $tag_arr, $authority, $address, $lng, $lat)
    {
        $oldInfo = $this->getBasicStageBySid($sid);
        $intro = Common::deleteHtml($intro);
        $status = 1;
        if ($oldInfo['name'] != $name || $oldInfo['intro'] != $intro) {
            $this->addStageCheck($sid, 0, $uid, $name, $intro, $oldInfo['cate_id']);
            $status = 2;
        }
        $this->updateBaseStage($sid, $icon, $mobile, $authority, $address, $lng, $lat);
        $tagModel = new TagModel();
        $tagModel->saveRelation(2, $sid, $tag_arr);
        $this->clearStageData($sid);//清除缓存里驿站信息
        PHPQRCode::getStagePHPQRCode($sid, true);
        return $status;
    }

    public function updateBusinessNew($sid, $icon, $name, $intro, $uid, $town_id, $tag_arr, $authority, $cover, $tel, $shop_hours, $address, $lng, $lat)
    {
        $addressModel = new AddressModel();
        $area_info = $addressModel->cityParent($town_id);
        $city_id = $area_info['id'];
        $province_id = $area_info['pid'];
        $oldInfo = $this->getBasicStageBySid($sid);
        $intro = Common::deleteStageHtml($intro);
        $status = 1;
        if ($oldInfo['name'] != $name || $oldInfo['intro'] != $intro) {
            $this->addStageCheck($sid, 2, $uid, $name, $intro, $oldInfo['cate_id']);
            $status = 2;
        }
        $stmt = $this->db->prepare("update stage set icon=:icon,province=:province,city=:city,town=:town,authority=:authority,stage_address=:stage_address,lng=:lng,lat=:lat,update_time=:update_time where sid=:sid ");
        $array = array(
            ':icon' => $icon,
            ':province' => $province_id,
            ':city' => $city_id,
            ':town' => $town_id,
            ':authority' => $authority,
            ':stage_address' => $address,
            ':lng' => $lng,
            ':lat' => $lat,
            ':update_time' => date('Y-m-d H:i:s'),
            ':sid' => intval($sid)
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if ($rs < 1) {
            return 0;
        }
        $this->setBusinessCover($sid, $uid, $cover);
        $this->modifyBusinessNew($sid, $tel, $shop_hours);
        $tagModel = new TagModel();
        $tagModel->saveRelation(2, $sid, $tag_arr);
        $this->clearStageData($sid);//清除缓存里驿站信息
        PHPQRCode::getStagePHPQRCode($sid, true);
        return $status;
    }

    //更新商家驿站详细信息
    public function modifyBusinessNew($sid, $tel, $shop_hours)
    {
        $stmt = $this->db->prepare("update business set tel=:tel,shop_hours=:shop_hours,is_perfect=:is_perfect,update_time=:update_time where sid=:sid");
        $array = array(
            ':tel' => $tel,
            ':shop_hours' => $shop_hours,
            ':is_perfect' => 1,
            ':update_time' => date('Y-m-d H:i:s'),
            ':sid' => $sid
        );
        $stmt->execute($array);
        $stmt->rowCount();
        $this->clearStageData($sid);//清除缓存里驿站信息
    }

    //用户修改驿站资料插入驿站审核表
    public function addStageCheck($sid, $type, $uid, $name, $intro, $cate_id)
    {
        $stmt = $this->db->prepare("insert into stage_check (sid,uid,name,cate_id,type,intro,add_time) values (:sid,:uid,:name,:cate_id,:type,:intro,:add_time)
        on duplicate key update status = 0,name=:name,cate_id=:cate_id,type=:type,intro=:intro,add_time=:add_time");
        $array = array(
            ':sid' => $sid,
            ':uid' => $uid,
            ':name' => $name,
            ':intro' => $intro,
            ':cate_id' => $cate_id,
            ':type' => $type,
            ':add_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        return $stmt->rowCount();
    }

    /**
     * 更新驿站基本信息
     */
    public function updateBaseStage($sid, $icon, $mobile, $authority, $address, $lng, $lat)
    {
        $stmt = $this->db->prepare("update stage set icon=:icon,mobile=:mobile,authority=:authority,stage_address=:stage_address,lng=:lng,lat=:lat,update_time=:update_time where sid=:sid");
        $array = array(
            ':icon' => $icon,
            ':mobile' => $mobile,
            ':authority' => $authority,
            ':stage_address' => $address,
            ':lng' => $lng,
            ':lat' => $lat,
            ':sid' => $sid,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if ($rs < 1) {
            return 0;
        }
        $this->clearStageData($sid);//清除缓存里驿站信息
        return 1;
    }
    /************3.0****************/
    //驿站主页帖子+服务信息组合列表
    public function getTopicAndEventList($sid, $page, $size, $token, $version, $uid = 0, $type = 0)
    {
        $start = ($page - 1) * $size;
        if (!$type) {
            $sql = 'SELECT id,4 AS type,is_top,is_recommend AS is_notice,is_good,last_comment_time,add_time,sid FROM topic WHERE sid =:sid AND STATUS < 2
                    UNION ALL
                SELECT id,10 AS type,is_top,is_notice,is_good,last_comment_time,add_time,sid FROM event WHERE sid =:sid AND STATUS < 2
                ORDER BY is_notice DESC,is_top DESC,last_comment_time DESC,add_time desc LIMIT :start,:size';
        } elseif ($type == 1) {
            $sql = 'SELECT id,4 AS type,is_top,is_recommend AS is_notice,is_good,last_comment_time,add_time,sid FROM topic WHERE sid =:sid AND STATUS < 2 ORDER BY is_notice DESC,is_top DESC,last_comment_time DESC,add_time desc LIMIT :start,:size';
        } elseif ($type == 2) {
            $sql = 'SELECT id,10 AS type,is_top,is_notice,is_good,last_comment_time,add_time,sid FROM event WHERE sid =:sid AND STATUS < 2
                ORDER BY is_notice DESC,is_top DESC,last_comment_time DESC,add_time desc LIMIT :start,:size';
        } elseif ($type == 3) {
            $sql = 'SELECT id,4 AS type,is_top,is_recommend AS is_notice,is_good,last_comment_time,add_time,sid FROM topic WHERE sid =:sid AND STATUS < 2 and is_good =1 ORDER BY is_notice DESC,is_top DESC,last_comment_time DESC,add_time desc LIMIT :start,:size';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        $topicModel = new TopicModel();
        $userModel = new UserModel();
        $eventModel = new EventModel();
        if ($result) {
            foreach ($result as $k => $v) {
                if ($v['type'] == 4) {
                    $topicInfo = $topicModel->getBasicTopicById($v['id']);
                    $list[$k]['id'] = $topicInfo['id'];
                    $list[$k]['title'] = $topicInfo['title'];
                    $list[$k]['type_name'] = '帖子';
                    $list[$k]['summary'] = $topicInfo['summary'] ? Common::msubstr(Common::deleteHtml($topicInfo['summary']), 0, 120, 'UTF-8', false) : '该帖暂无文字描述，您可以先了解才府：才府是中国传统文化的交流学习平台,致力于通过善的商业模式,让更多传承和传播优秀传统文化的人实现社会价值和经济效益。';
                    $list[$k]['is_top'] = $topicInfo['is_top'];
                    $list[$k]['is_good'] = $topicInfo['is_good'];
                    $list[$k]['is_recommend'] = $topicInfo['is_recommend'];
                    $list[$k]['view_num'] = $topicInfo['view_num'];
                    $list[$k]['add_time'] = $topicInfo['add_time'];
                    $list[$k]['lng'] = '';
                    $list[$k]['lat'] = '';
                    if ($topicInfo['img_src']) {
                        foreach ($topicInfo['img_src'] as $k1 => $v1) {
                            $list[$k]['img'][$k1] = Common::show_img($v1, 4, 540, 540);
                        }
                    } else {
                        $list[$k]['img'] = array();
                    }
                    $list[$k]['url'] = $token ? I_DOMAIN . '/t/' . $v['id'] . '?token=' . $token . '&version=' . $version : I_DOMAIN . '/t/' . $v['id'] . '?version=' . $version;
                    $userInfo = $userModel->getUserData($topicInfo['uid'], $uid);
                    $list[$k]['user']['uid'] = $userInfo['uid'];
                    $list[$k]['user']['did'] = $userInfo['did'];
                    $list[$k]['user']['nick_name'] = $userInfo['nick_name'];
                } elseif ($v['type'] == 10) {
                    $eventInfo = $eventModel->getEvent($v['id']);
                    $list[$k]['id'] = $eventInfo['id'];
                    $list[$k]['title'] = $eventInfo['title'];
                    if ($eventInfo['type'] == 1) {
                        $type_info = $eventModel->getBusinessEventType($eventInfo['type_code']);
                        $list[$k]['type_name'] = $type_info ? $type_info['name'] : '综合';
                    } else {
                        $list[$k]['type_name'] = $this->event_type_name[$eventInfo['type']];
                    }
                    $list[$k]['is_top'] = $v['is_top'];
                    $list[$k]['is_good'] = $v['is_good'];
                    $list[$k]['is_recommend'] = $v['is_notice'];
                    $list[$k]['max_partake'] = $eventInfo['max_partake'];
                    $list[$k]['summary'] = $eventInfo['summary'] ? Common::msubstr(Common::deleteHtml($eventInfo['summary']), 0, 120, 'UTF-8', false) : '该帖暂无文字描述，您可以先了解才府：才府是中国传统文化的交流学习平台,致力于通过善的商业模式,让更多传承和传播优秀传统文化的人实现社会价值和经济效益。';
                    $list[$k]['event_address'] = $eventInfo['event_address'] ? $eventInfo['event_address'] : '';
                    $list[$k]['cover'] = Common::show_img($eventInfo['cover'], 4, 720, 540);
                    $list[$k]['start_time'] = date('Y-m-d H:i', strtotime($eventInfo['start_time']));
                    $list[$k]['end_time'] = date('Y-m-d H:i', strtotime($eventInfo['end_time']));
                    $list[$k]['lng'] = $eventInfo['lng'];
                    $list[$k]['lat'] = $eventInfo['lat'];
                    $list[$k]['url'] = $token ? I_DOMAIN . '/e/' . $v['id'] . '?token=' . $token . '&version=' . $version : I_DOMAIN . '/e/' . $v['id'] . '?version=' . $version;
                    $userInfo = $userModel->getUserData($eventInfo['uid'], $uid);
                    $list[$k]['user']['uid'] = $userInfo['uid'];
                    $list[$k]['user']['did'] = $userInfo['did'];
                    $list[$k]['user']['nick_name'] = $userInfo['nick_name'];
                }
            }
        }
        return $list;
    }

    //获取某驿站下的最新帖子/服务信息的标题
    public function getNewBySid($sid)
    {
        $sql = 'SELECT id,4 AS type,add_time FROM topic WHERE sid =:sid AND STATUS < 2
                    UNION ALL
                SELECT id,10 AS type,add_time FROM event WHERE sid =:sid AND STATUS < 2
                ORDER BY add_time desc LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            if ($result['type'] == 4) {
                $topicModel = new TopicModel();
                $topicInfo = $topicModel->getBasicTopicById($result['id']);
                return $topicInfo['title'];
            }
            if ($result['type'] == 10) {
                $eventModel = new EventModel();
                $eventInfo = $eventModel->getBasicEvent($result['id']);
                return $eventInfo['title'];
            }
        }
        return '';
    }

    //驿站帖子发布权限修改
    public function updateAuthority($sid, $authority)
    {
        $stmt = $this->db->prepare("update stage set authority=:authority,update_time=:update_time where sid=:sid");
        $array = array(
            ':authority' => $authority,
            ':sid' => $sid,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if ($rs < 1) {
            return 0;
        }
        $this->clearStageData($sid);//清除缓存里驿站信息
        return 1;
    }

    //查询驿站发布权限
    public function getAuthority($sid)
    {
        $stmt = $this->db->prepare('select authority from stage where sid=:sid');
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['authority'];
    }

    //查询驿站支付权限
    public function getStageIsPay($sid)
    {
        $stmt = $this->db->prepare('select is_pay from stage where sid=:sid and status < 2 and type =2');
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function getStageUserList($sid, $uid)
    {
        $uids = $this->getStageUser($sid);
        $list = array();
        if ($uids) {
            $userModel = new UserModel();
            foreach ($uids as $v) {
                $list[] = $userModel->getUserData($v['uid'], $uid);
            }
        }
        return $list;
    }

    /**
     * 获取redis中新帖子的数量（用户总数）
     * @param $sid
     * @param $uid
     * @return int
     */
    public function getRedisNewTopicNumTotals($uid)
    {
        $key = 's:topic_event_totals:' . $uid;
        if ($this->redis->exists($key)) {
            return $this->redis->hGet($key, $uid);
        }
        return 0;
    }

    public function getCultureSidByUid($uid)
    {
        $stmt_data = $this->db->prepare("SELECT COUNT(sid) as num FROM stage_user WHERE STATUS = 1 AND role =1 AND uid =:uid AND sid IN (SELECT sid FROM stage WHERE STATUS =1 )");
        $array_data = array(
            ':uid' => $uid,
        );

        $stmt_data->execute($array_data);
        $result_data = $stmt_data->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("SELECT COUNT(sid) as num FROM stage_user WHERE STATUS = 1 AND role >1 AND uid =:uid AND sid IN (SELECT sid FROM stage WHERE STATUS < 2 AND authority = 1 )
");
        $array = array(
            ':uid' => $uid,
        );

        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'] + $result_data['num'];
    }

    public function isAddStageList($uid)
    {
        $stmt = $this->db->prepare("SELECT sid FROM stage_user WHERE STATUS =1 AND uid =:uid and role=1 AND sid IN (SELECT sid FROM stage WHERE STATUS =1)");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt_data = $this->db->prepare("SELECT sid FROM stage_user WHERE STATUS =1 AND uid =:uid and role>1 AND sid IN (SELECT sid FROM stage WHERE STATUS =1 and authority =1)");
        $stmt_data->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt_data->execute();
        $result_data = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_merge($result, $result_data);
        foreach ($ids as $k => $v) {
            $info = $this->getBasicStageBySid($v['sid']);
            $result[$k]['sid'] = $v['sid'];
            $result[$k]['name'] = $info['name'];
            $result[$k]['type'] = $info['type'];
            $result[$k]['intro'] = $info['intro'];
            $result[$k]['icon'] = IMG_DOMAIN . $info['icon'];
        }
        return $result;
    }

    public function getNumByCateId($cate_id)
    {
        $stmt = $this->db->prepare("SELECT COUNT(sid) as num FROM stage WHERE STATUS < 2 AND cate_id=:cate_id");
        $array = array(
            ':cate_id' => $cate_id,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);;
        return $result['num'];
    }

    public function getGoodStage($uid)
    {
        $sql = "SELECT COUNT(id) AS num ,sid FROM topic WHERE add_time >= DATE_SUB( CURRENT_DATE() , INTERVAL 3 MONTH )  AND sid NOT IN (SELECT sid FROM stage_user WHERE uid =:uid AND STATUS < 2) and sid in(select sid from stage where status < 2) GROUP BY sid  ORDER BY num DESC LIMIT 30;";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $info = $this->getBasicStageBySid($v['sid']);
                $result[$k]['name'] = $info['name'];
                $result[$k]['type'] = $info['type'];
                $result[$k]['intro'] = $info['intro'];
                $result[$k]['icon'] = IMG_DOMAIN . $info['icon'];
                $result[$k]['is_join'] = '0';
                $result[$k]['permission'] = $info['permission'];
            }
        }
        return $result;
    }

    //设置驿站公告
    public function setStageNotice($notice, $sid)
    {
        $stmt = $this->db->prepare("update stage set notice=:notice,notice_time=:notice_time where sid=:sid");
        $array = array(
            ':notice' => $notice,
            ':sid' => $sid,
            ':notice_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if ($rs < 1) {
            return 0;
        }
        $this->clearStageData($sid);//清除缓存里驿站信息
        return 1;
    }

    public function getInfoForIndex($sid)
    {
        $stmt = $this->db->prepare("select sid,uid,name,cover,icon,qrcode_img,user_num,topic_num,permission,authority,status,type,is_new,is_extend from stage where sid=:sid and status < 2");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['qrcode_img'] = PHPQRCode::getStagePHPQRCode($sid);
        return $result;
    }

    public function getNoticeTopic($sid, $version, $token)
    {
        $stmt = $this->db->prepare("select id,title from topic where sid=:sid and status < 2 and is_recommend =1");
        $array = array(
            ':sid' => $sid,
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['url'] = $token ? I_DOMAIN . '/t/' . $v['id'] . '?token=' . $token . '&version=' . $version : I_DOMAIN . '/t/' . $v['id'] . '?version=' . $version;

            }
        }
        return $result;
    }

    //获去用户驿站升级审核信息
    public function upgrageInfo($uid, $status)
    {
        $stmt = $this->db->prepare("SELECT sid FROM stage_check  WHERE uid=:uid and status=:status and type=1 and sid in(select sid from stage where status < 2)");
        $array = array(
            ':uid' => $uid,
            ':status' => $status
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    //驿站主页帖子+服务信息组合列表 is_self 是否自己的驿站 0不是自己---(在售商品) 1自己(全部商品)
    public function getIndexList($sid, $page, $size, $token, $version, $uid = 0, $type = 0, $is_self = 0)
    {
        $start = ($page - 1) * $size;
        $time = date('Y-m-d H:i:s');
        if (!$is_self) {
            $fields = ' and end_time >"' . $time . '" and stock_num>0';
        } else {
            $fields = '';
        }
        if (!$type) {
            $sql = "SELECT id,4 AS type,is_top,is_good,last_comment_time,add_time,sid FROM topic WHERE sid =:sid AND STATUS < 2 and is_recommend = 0
                    UNION ALL
                    SELECT id,10 AS type,is_top,is_good,last_comment_time,add_time,sid FROM event WHERE sid =:sid AND STATUS < 2
                    UNION ALL
                    SELECT id,12 AS type,is_top,is_good,last_comment_time,add_time,sid FROM stage_goods WHERE sid =:sid AND STATUS < 2 $fields
                ORDER BY is_top DESC,last_comment_time DESC,add_time desc LIMIT :start,:size";
        } elseif ($type == 1) {
            $sql = "SELECT id,4 AS type,is_top,is_good,last_comment_time,add_time,sid FROM topic WHERE sid =:sid AND STATUS < 2 and is_recommend = 0 ORDER BY is_top DESC,last_comment_time DESC,add_time desc LIMIT :start,:size";
        } elseif ($type == 2) {
            $sql = "SELECT id,10 AS type,is_top,is_good,last_comment_time,add_time,sid FROM event WHERE sid =:sid AND STATUS < 2
                ORDER BY is_top DESC,last_comment_time DESC,add_time desc LIMIT :start,:size";
        } elseif ($type == 3) {
            $sql = "SELECT id,12 AS type,is_top,is_good,last_comment_time,add_time,sid FROM stage_goods WHERE sid =:sid AND STATUS < 2 $fields  ORDER BY is_top DESC,last_comment_time DESC,add_time desc LIMIT :start,:size";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        $topicModel = new TopicModel();
        $userModel = new UserModel();
        $eventModel = new EventModel();
        $stagegoodsModel = new StagegoodsModel();
        $visitModel = new VisitModel();
        $stageModel = new StageModel();
        if ($result) {
            foreach ($result as $k => $v) {
                if ($v['type'] == 4) {
                    $topicInfo = $topicModel->getBasicTopicById($v['id']);
                    $list[$k]['id'] = $topicInfo['id'];
                    $list[$k]['title'] = $topicInfo['title'];
                    $list[$k]['type_name'] = '帖子';
                    $list[$k]['summary'] = $topicInfo['summary'] ? Common::msubstr(Common::deleteHtml($topicInfo['summary']), 0, 120, 'UTF-8', false) : '该帖暂无文字描述，您可以先了解才府：才府是中国传统文化的交流学习平台,致力于通过善的商业模式,让更多传承和传播优秀传统文化的人实现社会价值和经济效益。';
                    $list[$k]['is_top'] = $topicInfo['is_top'];
                    $list[$k]['is_good'] = $topicInfo['is_good'];
                    $list[$k]['view_num'] = $topicInfo['view_num'];
                    $list[$k]['add_time'] = date('m-d H:i', strtotime($topicInfo['add_time']));
                    if ($topicInfo['img_src']) {
                        foreach ($topicInfo['img_src'] as $k1 => $v1) {
                            $list[$k]['img'][$k1] = Common::show_img($v1, 4, 540, 540);
                            if ($k1 > 1) {
                                break;
                            }
                        }
                    } else {
                        $list[$k]['img'] = array();
                    }
                    $list[$k]['url'] = $token ? I_DOMAIN . '/t/' . $v['id'] . '?token=' . $token . '&version=' . $version : I_DOMAIN . '/t/' . $v['id'] . '?version=' . $version;
                    $userInfo = $userModel->getUserData($topicInfo['uid'], $uid);
                    $list[$k]['user']['uid'] = $userInfo['uid'];
                    $list[$k]['user']['did'] = $userInfo['did'];
                    $list[$k]['user']['nick_name'] = $userInfo['nick_name'];
                } elseif ($v['type'] == 10) {
                    $eventInfo = $eventModel->getEvent($v['id']);
                    $list[$k]['id'] = $eventInfo['id'];
                    $list[$k]['title'] = $eventInfo['title'];
                    if ($eventInfo['type'] == 1) {
                        $type_info = $eventModel->getBusinessEventType($eventInfo['type_code']);
                    } else {
                        $type_info = Common::eventType($eventInfo['type']);
                    }
                    $list[$k]['type_name'] = $type_info['name'];
                    $list[$k]['code_name'] = $type_info['code'];
                    $list[$k]['is_top'] = $v['is_top'];
                    $list[$k]['is_good'] = $v['is_good'];
                    $list[$k]['view_num'] = $visitModel->getVisitNum('event', $v['id']) ? $visitModel->getVisitNum('event', $v['id']) : 0;
                    $list[$k]['address_name'] = $stagegoodsModel->getCityAddress($eventInfo['province'], $eventInfo['city']);
                    $list[$k]['cover'] = Common::show_img($eventInfo['cover'], 4, 720, 540);
                    $eventInfo['price_type'] = $eventInfo['price_type'] ? $eventInfo['price_type'] : 1;
                    if ($eventInfo['price_type'] == 1) {
                        $list[$k]['price'] = '免费';
                    } else {
                        $price_info = $eventModel->getPrice($v['id']);
                        if (count($price_info) > 1) {
                            $max_price = end($price_info);
                            $list[$k]['price'] = $price_info[0]['unit_price'] . '-' . $max_price['unit_price'];
                        } else {
                            $list[$k]['price'] = isset($price_info[0]['unit_price']) ? $price_info[0]['unit_price'] : 0;
                        }
                    }
                    $fields_info = $eventModel->getEventFields($v['id']);
                    if ($fields_info) {
                        foreach ($fields_info as $k1 => $v1) {
                            if (date('Y-m-d', strtotime($v1['start_time'])) == date('Y-m-d', strtotime($v1['end_time']))) {
                                $fields_info[$k1]['show_time'] = date('m.d H:i', strtotime($v1['start_time'])) . '-' . date('H:i', strtotime($v1['end_time'])) . '';
                            } else {
                                $fields_info[$k1]['show_time'] = date('m.d H:i', strtotime($v1['start_time'])) . '-' . date('m-d H:i', strtotime($v1['end_time'])) . '';
                            }
                        }
                    }
                    $e_time = $eventModel->getEndTime($v['id']);//結束时间
                    $time = date('Y-m-d H:i:s');
                    if ($e_time) {
                        if ($e_time[0]['end_time'] <= $time) {
                            //当前时间小于活动结束时间
                            $list[$k]['start_type'] = 3;//活动结束
                        } else {
                            $list[$k]['start_type'] = 2;//可以报名
                        }
                    }
                    $list[$k]['fields_info'] = $fields_info;
                    $list[$k]['lng'] = $eventInfo['lng'];
                    $list[$k]['lat'] = $eventInfo['lat'];
                    $list[$k]['show_start_time'] = Common::getEventStartTime($eventInfo['id']);
                    $list[$k]['url'] = $token ? I_DOMAIN . '/e/' . $v['id'] . '?token=' . $token . '&version=' . $version : I_DOMAIN . '/e/' . $v['id'] . '?version=' . $version;
                } elseif ($v['type'] == 12) {
                    $goodsInfo = $stagegoodsModel->getInfo($v['id']);
                    $list[$k]['id'] = $goodsInfo['id'];
                    $list[$k]['name'] = $goodsInfo['name'];
                    $list[$k]['type_name'] = '商品';
                    $stageInfo = $stageModel->getStage($goodsInfo['sid']);
                    $list[$k]['sid'] = $goodsInfo['sid'];
                    $list[$k]['stage_name'] = $stageInfo['name'];
                    $list[$k]['type'] = $goodsInfo['type'];
                    $list[$k]['price'] = $goodsInfo['price'];
                    $list[$k]['score'] = $goodsInfo['score'];
                    $list[$k]['is_top'] = $goodsInfo['is_top'];
                    $list[$k]['is_good'] = $goodsInfo['is_good'];
                    $list[$k]['cover'] = IMG_DOMAIN . $goodsInfo['cover'];
                    $list[$k]['address_name'] = $goodsInfo['address_name'];
                    $list[$k]['sell_num'] = $stagegoodsModel->getSellNum($v['id']);
                    $list[$k]['view_num'] = $visitModel->getVisitNum('stagegoods', $v['id']) ? $visitModel->getVisitNum('stagegoods', $v['id']) : 0;
                    $list[$k]['url'] = $token ? I_DOMAIN . '/g/' . $v['id'] . '?token=' . $token . '&version=' . $version : I_DOMAIN . '/g/' . $v['id'] . '?version=' . $version;
                    $list[$k]['start_type'] = $goodsInfo['start_type'];
                }
            }
        }
        return $list;
    }

    //获取stage_check 表的主键
    public function getStageCheckByType($sid, $type)
    {
        $stmt = $this->db->prepare("select * from stage_check where sid=:sid and type=:type");
        $array = array(
            ':sid' => $sid,
            ':type' => $type
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return 0;
        }
        return $result['id'];
    }

    //驿站分类下的每个分类随机3条用户没有加入的驿站数据
    public function indexStageViewList($cate_id, $uid)
    {
        $stmt = $this->db->prepare("SELECT sid FROM stage WHERE STATUS =1 AND sid NOT IN (SELECT sid FROM stage_user WHERE STATUS < 2 AND uid =:uid ) AND cate_id =:cate_id and topic_num >20 ORDER BY RAND() LIMIT 3");
        $array = array(
            ':uid' => $uid,
            ':cate_id' => $cate_id
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $stageInfo = $this->getBasicStageBySid($v['sid'], 1);
                $result[$k]['name'] = $stageInfo['name'];
                $result[$k]['user_num'] = $stageInfo['user_num'];
                $result[$k]['topic_num'] = $stageInfo['topic_num'];
                $result[$k]['intro'] = $stageInfo['intro'];
                $result[$k]['icon'] = $stageInfo['icon'];
                $result[$k]['type'] = $stageInfo['type'];
            }
        }
        return $result;
    }

    //用户是否有服务驿站
    public function getCreateBstatus($uid)
    {
        $stmt = $this->db->prepare("select sid,name,status from stage where uid=:uid and type =2  AND status < 3");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //用户是否有服务驿站
    public function getCreatePstatus($uid, $status)
    {
        $stmt = $this->db->prepare("select sid,name from stage where uid=:uid and type =1  AND status=:status");
        $array = array(
            ':uid' => $uid,
            ':status' => $status
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function getSetCommissionList($uid, $type, $page, $size, $token, $version)
    {
        $sid = $this->getSidByUid($uid);
        $start = ($page - 1) * $size;
        $time = date('Y-m-d H:i:s');
        $stagegoodsModel = new StagegoodsModel();
        $eventModel = new EventModel();
        if ($type == 10) {
            $stmt = $this->db->prepare("select id,title,cover,commission_rate,commission from event where sid=:sid and is_commission =1 and end_time > '" . $time . "' order by commission_time desc limit :start,:size");
            $stmt->bindValue(':sid', $sid['sid'], PDO::PARAM_INT);
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);
            $stmt->bindValue(':size', $size, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($result) {
                foreach ($result as $k => $v) {
                    $result[$k]['cover'] = Common::show_img($v['cover'], 4, 360, 270);
                    $result[$k]['commission_rate'] = ($v['commission_rate'] * 100) . '%';
                    $result[$k]['number'] = $v['commission_rate'];
                    $result[$k]['commission'] = $v['commission'];
                    $price_list = $eventModel->getPrice($v['id']);
                    $result[$k]['min_price'] = $price_list[0]['unit_price'];
                    $result[$k]['max_price'] = '';
                    if (count($price_list) > 1) {
                        $max_price = end($price_list);
                        $result[$k]['max_price'] = $max_price['unit_price'];
                    }
                    $result[$k]['url'] = I_DOMAIN . '/e/' . $v['id'] . '?token=' . $token . '&version=' . $version;
                }
            }
        } elseif ($type == 12) {
            $stmt = $this->db->prepare("select id from stage_goods where sid=:sid and is_commission =1 and end_time > '" . $time . "' and stock_num >0 order by commission_time desc limit :start,:size");
            $stmt->bindValue(':sid', $sid['sid'], PDO::PARAM_INT);
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);
            $stmt->bindValue(':size', $size, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($result) {
                foreach ($result as $k => $v) {
                    $info = $stagegoodsModel->getGoodsRedisById($v['id']);
                    $result[$k]['name'] = $info['name'];
                    $result[$k]['cover'] = IMG_DOMAIN . $info['cover'];
                    $result[$k]['commission_rate'] = ($info['commission_rate'] * 100) . '%';
                    $result[$k]['number'] = $info['commission_rate'];
                    $result[$k]['commission'] = $info['commission'];
                    $result[$k]['min_price'] = $info['price'];
                    $result[$k]['max_price'] = '';
                    $result[$k]['url'] = I_DOMAIN . '/g/' . $v['id'] . '?token=' . $token . '&version=' . $version;
                }
            }
        }
        return $result;
    }

    public function getIsSetCommissionList($uid, $type, $page, $size, $token, $version)
    {
        $sid = $this->getSidByUid($uid);
        $start = ($page - 1) * $size;
        $time = date('Y-m-d H:i:s');
        if ($type == 10) {
            $stmt = $this->db->prepare("select id,title,cover from event where sid=:sid and is_commission =0 and end_time > '" . $time . "' and price_type = 2 order by id desc limit :start,:size");
            $stmt->bindValue(':sid', $sid['sid'], PDO::PARAM_INT);
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);
            $stmt->bindValue(':size', $size, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($result) {
                $eventModel = new EventModel();
                foreach ($result as $k => $v) {
                    $result[$k]['cover'] = Common::show_img($v['cover'], 4, 360, 270);
                    $price_list = $eventModel->getPrice($v['id']);
                    $min_price = $price_list[0]['unit_price'];
                    $result[$k]['price'] = $min_price;
                    $result[$k]['min_price'] = $min_price;
                    $result[$k]['max_price'] = '';
                    if (count($price_list) > 1) {
                        $max_price = end($price_list);
                        $max_price = $max_price['unit_price'];
                        $result[$k]['price'] = $min_price . '-' . $max_price;
                        $result[$k]['max_price'] = $max_price;
                    }
                    $result[$k]['url'] = I_DOMAIN . '/e/' . $v['id'] . '?token=' . $token . '&version=' . $version;
                }
            }
        } elseif ($type == 12) {
            $stmt = $this->db->prepare("select id,name,cover,price from stage_goods where sid=:sid and is_commission =0 and end_time > '" . $time . "' and stock_num >0 and type < 3 order by id desc limit :start,:size");
            $stmt->bindValue(':sid', $sid['sid'], PDO::PARAM_INT);
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);
            $stmt->bindValue(':size', $size, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($result) {
                foreach ($result as $k => $v) {
                    $result[$k]['cover'] = IMG_DOMAIN . $v['cover'];
                    $result[$k]['min_price'] = $v['price'];
                    $result[$k]['max_price'] = '';
                    $result[$k]['url'] = I_DOMAIN . '/g/' . $v['id'] . '?token=' . $token . '&version=' . $version;
                }
            }
        }
        return $result;
    }

    //根据sp_id 获取用户设置的佣金率
    public function getCommissionRateById($id)
    {
        $stmt = $this->db->prepare("select * from share_promote where id=:id");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    // 插入更新用户加入驿站群聊
    public function userJoinStage($userid, $groupSid, $status)
    {
        if (!$userid || !$groupSid || !$status) {
            return false;
        }
        $sql = "insert into stage_user (uid,sid) values(:uid,:sid) on duplicate key update status=:status,add_time=:add_time";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':uid' => $userid,
            ':sid' => $groupSid,
            ':status' => $status,
            ':add_time' => date('Y-m-d H:i:s', time()),
        );
        $stmt->execute($array);
    }

    // 当用户选择退出驿站里，更新用户在stage_user表中的状态
    public function updateChatByStageStatus($userId, $groupId, $status = 1)
    {
        if (!$userId || !$groupId) {
            return false;
        }
        $sql = "update stage_user set status=:status,add_time=:add_time where uid=:uid and sid=:sid and status<3";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':status' => $status,
            ':add_time' => date('Y-m-d H:i:s', time()),
            ':uid' => $userId,
            ':sid' => $groupId,
        );
        $stmt->execute($array);
    }

    public function spAgreement($sid)
    {
        $stmt = $this->db->prepare("update stage set is_sp_agreement=:is_sp_agreement,update_time=:update_time where sid=:sid");
        $array = array(
            ':is_sp_agreement' => 1,
            ':sid' => $sid,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if ($rs < 1) {
            return 0;
        }
        return 1;
    }

    //驿站分享明细列表
    public function getStageSpList($sid, $page, $size, $type = 0)
    {
        $start = ($page - 1) * $size;
        if (!$type) {
            $sql = "SELECT id,10 AS type,sp_id,add_time,totals as price_totals,commission,eid as obj_id FROM event_orders WHERE sid =:sid AND order_status = 2 and sp_id!=''
                    UNION ALL
                    SELECT id,12 AS type,sp_id,add_time,price_totals,commission,goods_id as obj_id FROM stage_goods_orders WHERE sid =:sid AND order_status = 7 and sp_id!=''
                    ORDER BY add_time desc LIMIT :start,:size";
        } elseif ($type == 10) {
            $sql = "SELECT id,10 AS type,sp_id,add_time,totals as price_totals,commission,eid as obj_id  FROM event_orders WHERE sid =:sid AND order_status = 2 and sp_id!='' ORDER BY add_time desc LIMIT :start,:size";
        } elseif ($type == 12) {
            $sql = "SELECT id,12 AS type,sp_id,add_time,price_totals,commission,goods_id as obj_id  FROM stage_goods_orders WHERE sid =:sid AND order_status = 7 and
sp_id!='' ORDER BY add_time desc LIMIT :start,:size";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $eventModel = new EventModel();
        $stagegoodsModel = new StagegoodsModel();
        $userModel = new UserModel();
        if ($result) {
            foreach ($result as $k => $v) {
                $sp_info = $userModel->getSpInfoById($v['sp_id']);
                if ($sp_info) {
                    $user_info = $userModel->getUserData($sp_info['uid']);
                    $result[$k]['avatar'] = $user_info['avatar'];
                    $result[$k]['nick_name'] = $user_info['nick_name'];
                    if ($v['type'] == 10) {
                        $event_info = $eventModel->getEventRedisById($v['obj_id']);
                        $result[$k]['obj_name'] = $event_info['title'];
                    } elseif ($v['type'] == 12) {
                        $goods_info = $stagegoodsModel->getGoodsRedisById($v['obj_id']);
                        $result[$k]['obj_name'] = $goods_info['name'];
                    }
                }
            }
        }
        return $result;
    }

    //驿站分享明细列表
    public function getOneSpList($sid, $obj_id, $page, $size, $type, $token, $version)
    {
        $list = array();
        $stagegoodsModel = new StagegoodsModel();
        $eventModel = new EventModel();
        $userModel = new UserModel();
        $start = ($page - 1) * $size;
        if ($type == 10) {
            $event_totals = $eventModel->getEventSpCommissionTotals($sid['sid'], $obj_id);
            $list['price_totals'] = $event_totals['price_totals'];
            $list['commission_totals'] = $event_totals['commission_totals'];
            $sql = "SELECT id,10 AS type,sp_id,add_time,totals as price_totals,commission,eid as obj_id  FROM event_orders WHERE eid =:eid AND order_status = 2 and sp_id!='' ORDER BY add_time desc LIMIT :start,:size";
        } elseif ($type == 12) {
            $goods_totals = $stagegoodsModel->getGoodsSpCommissionTotals($sid['sid'], $obj_id);
            $list['price_totals'] = $goods_totals['price_totals'];
            $list['commission_totals'] = $goods_totals['commission_totals'];
            $sql = "SELECT id,12 AS type,sp_id,add_time,price_totals,commission,goods_id as obj_id  FROM stage_goods_orders WHERE goods_id =:goods_id AND order_status = 7 and sp_id!='' ORDER BY add_time desc LIMIT :start,:size";
        }
        $stmt = $this->db->prepare($sql);
        if ($type == 10) {
            $stmt->bindValue(':eid', $obj_id, PDO::PARAM_INT);
        } elseif ($type == 12) {
            $stmt->bindValue(':goods_id', $obj_id, PDO::PARAM_INT);
        }
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $k => $v) {
                $sp_info = $userModel->getSpInfoById($v['sp_id']);
                $user_info = $userModel->getUserData($sp_info['uid']);
                $result[$k]['avatar'] = $user_info['avatar'];
                $result[$k]['nick_name'] = $user_info['nick_name'];
                if ($v['type'] == 10) {
                    $event_info = $eventModel->getEventRedisById($v['obj_id']);
                    $list['obj_name'] = $event_info['title'];
                    $list['obj_cover'] = $event_info['cover'];
                    $list['url'] = I_DOMAIN . '/e/' . $v['obj_id'] . '?token=' . $token . '&version=' . $version;
                    $min_price = $event_info['price_list'][0]['unit_price'];
                    $list['obj_price'] = $min_price;
                    if (count($event_info['price_list']) > 1) {
                        $max_price = end($event_info['price_list']);
                        $max_price = $max_price['unit_price'];
                        $result[$k]['obj_price'] = $min_price . '-' . $max_price;
                    }
                    $result[$k]['obj_name'] = $event_info['title'];
                } elseif ($v['type'] == 12) {
                    $goods_info = $stagegoodsModel->getGoodsRedisById($v['obj_id']);
                    $list['obj_name'] = $goods_info['name'];
                    $list['obj_cover'] = $goods_info['cover'];
                    $list['url'] = I_DOMAIN . '/g/' . $v['obj_id'] . '?token=' . $token . '&version=' . $version;
                    $list['obj_price'] = $goods_info['price'];
                    $result[$k]['obj_name'] = $goods_info['name'];

                }
            }
        }
        $list['list'] = $result;
        return $list;
    }

    //获取缓存中的驿站信息数据
    /*
     * @param $sid 驿站id
     * @param $type 1创建驿站 2升级驿站
     *
     */
    public function getStageRedisById($sid, $type = 1)
    {
        $redisKey = Common::getRedisKey(5) . $sid;
        $result = $this->contentRedis->get($redisKey);
        // $result = null;
        if ($result) {
            $result = json_decode($result, true);
        } else {
            $stmt = $this->db->prepare("select * from stage where sid=:sid");
            $array = array(
                ':sid' => $sid
            );
            $stmt->execute($array);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                // 初始化一下stage表中原本数据，不然数据不准确
                $result['cover'] = $result['is_join'] = null;
                // 驿站信息数据
                $cate_info = $this->getCultureCateById($result['cate_id']);
                $result['cate_id'] = $cate_info['id'];
                $result['cate_name'] = $cate_info['name'];
                $tagModel = new TagModel();
                $tagList = $tagModel->getRelation(2, $sid);
                if ($tagList) {
                    foreach ($tagList as $k => $v) {
                        $tagList[$k]['id'] = $v['tag_id'];
                    }
                }
                $result['tag'] = $tagList;
                $result['is_join'] = $this->getJoinStage($sid, $result['uid']);
                $stage = $this->getStageById($sid, $result['uid']);
                $result['user']['uid'] = $stage['user']['uid'];
                $result['user']['did'] = $stage['user']['did'];
                $result['user']['nick_name'] = $stage['user']['nick_name'];
                $result['user']['type'] = $stage['user']['type'];
                $result['user']['sex'] = $stage['user']['sex'];
                $result['user']['avatar'] = Common::show_img($stage['user']['avatar'], 1, 160, 160);
                if ($stage['type'] == 2) {
                    $busniessInfo = $this->getBusiness($sid);
                    $result['shop_hours'] = $busniessInfo['shop_hours'];
                    $result['tel'] = $busniessInfo['tel'];
                    $coverList = $this->getBusinessCover($sid);
                    if ($coverList) {
                        foreach ($coverList as $k => $v) {
                            $result['cover'][$k] = IMG_DOMAIN . $v['path'];
                            $result['show_cover'][$k] = Common::show_img($v['path'], 1, 540, 540);
                            $result['show_img'][$k] = $v['path'];
                        }
                    } else {
                        $result['cover'] = array();
                        $result['show_cover'] = array();
                        $result['show_img'] = array();
                    }
                } else {
                    $result['cover'] = array();
                    $result['show_cover'] = array();
                    $result['show_img'] = array();
                }

                // 更新驿站信息

                // 以更新审核后的name、intro为准
                $result['name'] = $stage['name'];
                $result['intro'] = $stage['intro'];

                $stageTag = $tagModel->getRelation(2, $sid);//驿站标签
                $stage_tag = $tag_list = array();
                foreach ($stageTag as $k => $v) {
                    $stage_tag[$k]['id'] = $v['tag_id'];
                    $stage_tag[$k]['content'] = $v['content'];
                }
                $result['stage_tag'] = $stage_tag;
                $tagList = $tagModel->listTag(2, $result['cate_id']);
                foreach ($tagList as $k => $v) {
                    foreach ($stage_tag as $v1) {
                        if ($v1['id'] == $v['id']) {
                            unset($tagList[$k]);
                        }
                    }
                    $tag_list[] = $v;
                }
                $snsCheckModel = new SnsCheckModel();
                $operation_type = 2;
                if ($result['type'] == 1 && $type == 1) {
                    $operation_type = 2;
                } elseif ($result['type'] == 2 && $type == 1) {
                    $operation_type = 56;
                } elseif ($type == 2) {
                    $operation_type = 59;
                }
                $result['reason'] = $snsCheckModel->get($sid, $operation_type, 0);
                $result['tag_list'] = $tag_list;
                // 以下是$stageModel->getStageInfoById($sid, $type);里的数据字段
                $culture_cate_info = $this->getCultureCateById($result['cate_id']);
                $result['culture_cate_name'] = $culture_cate_info['name'];
                $result['lng'] = $result['lng'] ? $stage['lng'] : '';
                $result['lat'] = $result['lat'] ? $stage['lat'] : '';
                $addressModel = new AddressModel();
                $result['province_name'] = $stage['province'] ? $addressModel->getNameById($stage['province']) : '';
                $result['city_name'] = $stage['city'] ? $addressModel->getNameById($stage['city']) : '';
                $result['town_name'] = $stage['town'] ? $addressModel->getNameById($stage['town']) : '';
                $result['stage_address'] = $result['stage_address'] ? $result['stage_address'] : '';
                if ($result['type'] == 2 || $type == 2) {
                    $businessInfo = $this->getBusinessInfo($sid);
                    $result['service_type'] = $businessInfo['service_type'];
                    $result['identity_img'] = $businessInfo['identity_img'];
                    $result['license_img'] = $businessInfo['license_img'] ? $businessInfo['license_img'] : "";
                    $result['contacts'] = $businessInfo['contacts'];
                    $result['bank'] = $businessInfo['bank'] ? $businessInfo['bank'] : "";
                    $result['bank_no'] = $businessInfo['bank_no'] ? $businessInfo['bank_no'] : "";
                    $result['email'] = $businessInfo['email'] ?: "";
                    $result['shop_hours'] = $businessInfo['shop_hours'];
                    $result['tel'] = $businessInfo['tel'];
                    $result['business_scope'] = $businessInfo['business_scope'] ? $businessInfo['business_scope'] : "";
                }
            }
            $this->contentRedis->set($redisKey, json_encode($result));
        }
        return $result;
    }


}
