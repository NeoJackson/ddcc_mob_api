<?php

/**
 * @name FollowModel
 * @desc Follow数据获取类, 可以访问数据库，文件，其它系统等
 * @author {&$AUTHOR&}
 */
class FollowModel
{
    private $db;
    private $redis;
    public $max_att_num = 5000;

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }

    /**
     * 获取关注key值
     * @param $uid
     * @return bool|string
     */
    public function getAttKey($uid)
    {
        if (!$uid) {
            return false;
        }
        return "u:att:" . $uid;
    }


    /**
     * 获取粉丝key值
     * @param $uid
     * @return bool|string
     */
    public function getFansKey($uid)
    {
        if (!$uid) {
            return false;
        }
        return "u:fans:" . $uid;
    }

    /**
     * 获取好友key值
     * @param $uid
     * @return bool|string
     */
    public function getFriendKey($uid)
    {
        if (!$uid) {
            return false;
        }
        return "u:friend:" . $uid;
    }

    /**
     * 获取关注数量
     * @param $uid
     * @return mixed
     */
    public function getAttNum($uid)
    {
        $stmt = $this->db->prepare("select count(1) as num from follow where uid = :uid and status=1 and f_uid in(select uid from user)");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /**
     * 获取粉丝数量
     * @param $uid
     * @return mixed
     */
    public function getFansNum($uid)
    {
        $stmt = $this->db->prepare("select count(1) as num from follow where f_uid = :uid and status=1 and uid in(select uid from user)");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /**
     * 判断是否关注
     * @param $uid
     * @param $f_uid
     * @return mixed
     */
    public function isFollow($uid, $f_uid)
    {
        $stmt = $this->db->prepare("select id from follow where uid=:uid and f_uid=:f_uid AND status=1");
        $array = array(
            ':uid' => $uid,
            ':f_uid'=>$f_uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 获取用户关注的数量
     * @param $uid
     * @return mixed
     */
    public function getFollowNum($uid)
    {
        $stmt = $this->db->prepare("select count(1) as num from follow where uid=:uid AND status=1 and f_uid in(select uid from user)");
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /*
     * @name 加关注
     */
    public function add($uid, $f_uid, $add_time = '')
    {
        if ($add_time == '') {
            $add_time = date('Y-m-d H:i:s');
        }
        $userModel = new UserModel();
        $user_uid = $userModel->getUserByUid($uid);
        $user_f_uid = $userModel->getUserByUid($f_uid);
        if (!$user_uid) {
            return -1;
        }
        if (!$user_f_uid) {
            return -2;
        }
        $isFollow = $this->isFollow($uid, $f_uid);
        if ($isFollow) {
            return -3;
        }
        $count = $this->getFollowNum($uid);
        if ($count >= $this->max_att_num) {
            return -4;
        }
        if (!$this->addByRedis($uid, $f_uid)) {
            return -5;
        }
        $stmt = $this->db->prepare("insert into follow (uid,f_uid,add_time,status) values (:uid,:f_uid,:add_time,1) ON DUPLICATE KEY update status = 1,is_read = 0,add_time=:add_time,");
        $array = array(
            ':uid' => $uid,
            ':f_uid' => $f_uid,
            ':add_time' => $add_time,
        );
        $stmt->execute($array);
        //设置我关注的人的分组
        $this->setFollowGroup($uid,$f_uid);
        return $stmt->rowCount();
    }

    private function addByRedis($uid, $f_uid)
    {
        $add_time = time();
        if ($this->isFollow($f_uid, $uid)) {
            $myKey = $this->getFriendKey($uid);
            $friendKey = $this->getFriendKey($f_uid);
            $this->redis->hSet($myKey, $f_uid, $add_time);
            $this->redis->hSet($friendKey, $uid, $add_time);
        }
        $pipeline = $this->redis->multi();
        $attKey = $this->getAttKey($uid);
        $fansKey = $this->getFansKey($f_uid);
        $pipeline->hSet($attKey, $f_uid, $add_time);
        $pipeline->hSet($fansKey, $uid, $add_time);
        $pipeline->hIncrBy('u:info:' . $uid, 'att_num', 1);
        $pipeline->hIncrBy('u:info:' . $f_uid, 'fans_num', 1);
        $pipeline->exec();
        $feedModel = new FeedModel();
        $feedModel->clearFeeds($uid);
        return true;
    }

    /*
     * @name 取消关注
     */
    public function del($uid, $f_uid)
    {
        $isFollow = $this->isFollow($uid, $f_uid);
        if (!$isFollow) {
            return -1;
        }
        if (!$this->delByRedis($uid, $f_uid)) {
            return -2;
        }
        $stmt = $this->db->prepare("update follow set status = 0,is_read = 0 where status = 1 and uid=:uid and f_uid=:f_uid");
        $array = array(
            ':uid' => $uid,
            ':f_uid' => $f_uid,
        );
        $stmt->execute($array);
        $group = $this->getGroupByUid($uid,$f_uid);
        $this->reduceGroupNum($group['group_id']);
        $feedModel = new FeedModel();
        $feedModel->clearGroupFeeds($group['group_id']); //清除分组动态缓存
        return $stmt->rowCount();
    }

    private function delByRedis($uid, $f_uid)
    {
        if ($this->isFollow($f_uid, $uid)) {
            $myKey = $this->getFriendKey($uid);
            $friendKey = $this->getFriendKey($f_uid);
            $this->redis->hDel($myKey, $f_uid);
            $this->redis->hDel($friendKey, $uid);
        }
        $pipeline = $this->redis->multi();
        $attKey = $this->getAttKey($uid);
        $fansKey = $this->getFansKey($f_uid);
        $pipeline->hDel($attKey, $f_uid);
        $pipeline->hDel($fansKey, $uid);
        $pipeline->hIncrBy('u:info:' . $uid, 'att_num', -1);
        $pipeline->hIncrBy('u:info:' . $f_uid, 'fans_num', -1);
        $pipeline->exec();
        $feedModel = new FeedModel();
        $feedModel->clearFeeds($uid);
        return true;
    }

    /**
     * 获取两个用户之间的关系
     * @param $uid
     * @param $f_uid
     * @return int    0 没有关系 1 关注 2 好友 3粉丝
     */
    public function getRelation($uid, $f_uid)
    {
        $uFollow = $this->isFollow($uid, $f_uid);
        $fFollow = $this->isFollow($f_uid, $uid);
        if ($uFollow && $fFollow) {
            return 2;
        } elseif ($uFollow) {
            return 1;
        } elseif ($fFollow) {
            return 3;
        } else {
            return 0;
        }
    }


    public function getAttListByReids($uid, $group_id)
    {
        if ($group_id) {
            $list = $this->getUserByGroupId($uid, $group_id);
            $size = count($list);
        } else {
            $attKey = $this->getAttKey($uid);
            $size = $this->redis->hLen($attKey);
            $list = $this->redis->hGetAll($attKey);
        }
        return array('list' => $list, 'size' => $size);
    }

    //根据分组id和用户id查询关注的用户列表
    public function getUserByGroupId($uid, $group_id)
    {
        $stmt = $this->db->prepare("select f_uid from follow where uid = :uid and group_id=:group_id and status = 1 order by add_time desc,id desc");
        $array = array(
            ':uid' => $uid,
            ':group_id' => $group_id,
        );
        $stmt->execute($array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 获取关注列表
     * @param $uid
     * @return mixed
     */
    public function getAttList($to_uid, $start, $length,$uid)
    {
        if ($start < 0 || $length < 1) {
            return false;
        }
        $stmt = $this->db->prepare("select f_uid as uid,add_time from follow where uid=:uid and status = 1
                and f_uid in (select uid from user) order by add_time desc,id desc limit :start,:length");
        $stmt->bindValue ( ':uid' ,  $to_uid ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':length' ,  $length ,  PDO :: PARAM_INT );
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            foreach($result as $k =>$val){
                $userModel = new UserModel();
                $userInfo = $userModel->getUserData($val['uid'],$uid);
                $result[$k]['type'] = $userInfo['type'];
                $result[$k]['did'] = $userInfo['did'];
                $result[$k]['nick_name'] = $userInfo['nick_name'];
                $result[$k]['avatar'] = Common::show_img($userInfo['avatar'],1,160,160);
                $result[$k]['sex'] = $userInfo['sex'];
                $result[$k]['intro'] = $userInfo['intro'];
                if($userInfo['type']>1){
                    $indexModel = new IndexModel();
                    $info = $indexModel->getAngelInfoByUid($userInfo['uid']);
                    $result[$k]['intro'] = $info['info'];
                }
                $result[$k]['att_num'] = $userInfo['att_num'];
                $result[$k]['fans_num'] = $userInfo['fans_num'];
                $result[$k]['relation'] = $userInfo['relation'];
                $result[$k]['self'] = $userInfo['self'];
            }
        }
        return $result;
    }
    public function getAttListByLastTime($to_uid, $last_time, $length,$uid){
        if($last_time){
            $stmt = $this->db->prepare("select f_uid as uid,add_time from follow where uid=:uid and status = 1 and add_time < :last_time
                and f_uid in (select uid from user) order by add_time desc,id desc limit :length");
            $stmt->bindValue ( ':last_time' ,  $last_time ,  PDO :: PARAM_STR );
        }else{
            $stmt = $this->db->prepare("select f_uid as uid,add_time from follow where uid=:uid and status = 1
                and f_uid in (select uid from user) order by add_time desc,id desc limit :length");
        }
        $stmt->bindValue ( ':uid' ,  $to_uid ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':length' ,  $length ,  PDO :: PARAM_INT );
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            foreach($result as $k =>$val){
                $userModel = new UserModel();
                $userInfo = $userModel->getUserData($val['uid'],$uid);
                $result[$k]['type'] = $userInfo['type'];
                $result[$k]['did'] = $userInfo['did'];
                $result[$k]['nick_name'] = $userInfo['nick_name'];
                $result[$k]['avatar'] = Common::show_img($userInfo['avatar'],1,160,160);
                $result[$k]['sex'] = $userInfo['sex'];
                if($userInfo['type']>1){
                    $indexModel = new IndexModel();
                    $info = $indexModel->getAngelInfoByUid($userInfo['uid']);
                    $result[$k]['intro'] = $info['info'];
                }else{
                    $result[$k]['intro'] = $userInfo['intro'];
                }
                $result[$k]['att_num'] = $userInfo['att_num'];
                $result[$k]['fans_num'] = $userInfo['fans_num'];
                $result[$k]['relation'] = $userInfo['relation'];
                $result[$k]['self'] = $userInfo['self'];
            }
        }
        return $result;
    }

    //获取关注数量
    public function getUserAttNum($uid)
    {
        $stmt = $this->db->prepare("select count(1) as num from follow where uid=:uid and status = 1 and f_uid in(select uid from user)");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }

    //获得好友列表
    public function getFriendList($uid, $sid = 0,$start, $length)
    {
        if ($start < 0 || $length < 1) {
            return false;
        }
        $size = $this->getFriendNum($uid);
        if ($start > $size) {
            $start = 0;
        }
        $fields = $sid ? ' AND f.f_uid NOT IN (SELECT uid FROM stage_user WHERE STATUS <2 AND sid ='.$sid.' ) ' :'';
        $stmt = $this->db->prepare("select f.f_uid as uid from follow f where f.uid=:uid and f.status=1 and f.f_uid in (select uid from user) and
        (select count(1) from follow where uid=f.f_uid and f_uid=:uid and status=1)>0 $fields order by add_time desc,id desc limit :start,:length");
        $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':length' ,  $length ,  PDO :: PARAM_INT );
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $userModel = new UserModel();
            foreach($result as $k=> $v){
                $userInfo = $userModel->getUserData($v['uid']);
                $result[$k]['did'] = $userInfo['did'];
                $result[$k]['nick_name'] = $userInfo['nick_name'];
                $result[$k]['type'] = $userInfo['type'];
                $result[$k]['avatar'] = Common::show_img($userInfo['avatar'],1,160,160);
                $result[$k]['sex'] = $userInfo['sex'];
                $result[$k]['intro'] = $userInfo['intro'];
                $result[$k]['att_num'] = $userInfo['att_num'];
                $result[$k]['fans_num'] = $userInfo['fans_num'];
            }
        }
        return array('list' => $result, 'size' => $size);
    }

    //获取好友数量
    public function getFriendNum($uid)
    {
        $stmt = $this->db->prepare("select count(1) as num from follow f where uid=:uid and status = 1 and f.f_uid in (select uid from user)
        and (select count(1) from follow where uid=f.f_uid and f_uid=:uid and status=1)>0");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }

    /**
     * 获取粉丝列表
     * @param $uid
     * @return mixed
     */
    public function getFansList($to_uid, $start, $length,$uid){
        if ($start < 0 || $length < 1) {
            return false;
        }
        $stmt = $this->db->prepare("select uid,add_time from follow where f_uid=:f_uid and status = 1 and uid in (select uid from user)
        order by add_time desc,id desc limit :start,:length");
        $stmt->bindValue ( ':f_uid' ,  $to_uid ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':start' ,  $start ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':length' ,  $length ,  PDO :: PARAM_INT );
        $stmt->execute();
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($rs){
            foreach($rs as $k=>$val){
                $userModel = new UserModel();
                $followModel = new FollowModel();
                $userInfo = $userModel->getUserData($val['uid'],$uid);
                $rs[$k]['uid'] = $userInfo['uid'];
                $rs[$k]['type'] = $userInfo['type'];
                $rs[$k]['did'] = $userInfo['did'];
                $rs[$k]['nick_name'] = $userInfo['nick_name'];
                $rs[$k]['avatar'] = Common::show_img($userInfo['avatar'],1,160,160);
                $rs[$k]['sex'] = $userInfo['sex'];
                if($userInfo['type']>1){
                    $indexModel = new IndexModel();
                    $info = $indexModel->getAngelInfoByUid($userInfo['uid']);
                    $rs[$k]['intro'] = $info['info'];
                }else{
                    $rs[$k]['intro'] = $userInfo['intro'];
                }
                $rs[$k]['att_num'] = $userInfo['att_num'];
                $rs[$k]['fans_num'] = $userInfo['fans_num'];
                $rs[$k]['relation'] = $userInfo['relation'];
                $rs[$k]['self'] = $userInfo['self'];
                $is_read = $followModel->getFansListIsRead($val['uid'],$uid);
                $list[$k]['is_read'] = $is_read['is_read'];
            }
        }
        return $rs;
    }
    public function getFansListByLastTime($to_uid, $last_time, $length,$uid){
        if($last_time){
            $stmt = $this->db->prepare("select uid,add_time from follow where f_uid=:f_uid and status = 1 and add_time < :last_time and uid in (select uid from user)
        order by add_time desc,id desc limit :length");
            $stmt->bindValue ( ':last_time' ,  $last_time ,  PDO :: PARAM_STR );
        }else{
            $stmt = $this->db->prepare("select uid,add_time from follow where f_uid=:f_uid and status = 1 and uid in (select uid from user)
        order by add_time desc,id desc limit :length");
        }
        $stmt->bindValue ( ':f_uid' ,  $to_uid ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':length' ,  $length ,  PDO :: PARAM_INT );
        $stmt->execute();
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($rs){
            foreach($rs as $k=>$val){
                $userModel = new UserModel();
                $followModel = new FollowModel();
                $userInfo = $userModel->getUserData($val['uid'],$uid);
                $rs[$k]['uid'] = $userInfo['uid'];
                $rs[$k]['type'] = $userInfo['type'];
                $rs[$k]['did'] = $userInfo['did'];
                $rs[$k]['nick_name'] = $userInfo['nick_name'];
                $rs[$k]['avatar'] = Common::show_img($userInfo['avatar'],1,160,160);
                $rs[$k]['sex'] = $userInfo['sex'];
                $rs[$k]['intro'] = $userInfo['intro'];
                if($userInfo['type']>1){
                    $indexModel = new IndexModel();
                    $info = $indexModel->getAngelInfoByUid($userInfo['uid']);
                    $rs[$k]['intro'] = $info['info'];
                }
                $rs[$k]['att_num'] = $userInfo['att_num'];
                $rs[$k]['fans_num'] = $userInfo['fans_num'];
                $rs[$k]['relation'] = $userInfo['relation'];
                $rs[$k]['self'] = $userInfo['self'];
                $is_read = $followModel->getFansListIsRead($val['uid'],$uid);
                $list[$k]['is_read'] = $is_read['is_read'];
            }
        }
        return $rs;
    }
    //查看粉丝状态
    public function getFansListIsRead($uid,$f_uid){
        $stmt = $this->db->prepare("SELECT f_uid,is_read FROM follow where uid =:uid and f_uid = :f_uid");
        $array = array(
            ':uid' => $uid,
            ':f_uid' => $f_uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs;
    }

    //获取粉丝数量
    public function getUserFansNum($uid)
    {
        $stmt = $this->db->prepare("select count(1) as num from follow where f_uid=:f_uid and status = 1 and uid in (select uid from user)");
        $array = array(
            ':f_uid' => $uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }
    /*
     * @name 显示推荐总数
     */
    public function getUserNum($uid)
    {
        $stmt = $this->db->prepare("select count(*) as num from user where avatar != '' and status=1 and uid NOT IN (select f_uid from follow where uid=:uid and status=1) and uid <> :uid");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    /**
     * 设置我关注的人的分组
     * @param $uid
     * @param $f_uid
     */
    public function setFollowGroup($uid, $f_uid, $old_group_id = 0, $group_id = 0){
        $feedModel = new FeedModel();
        if (!$old_group_id && !$group_id) {
            $default_group = $this->getDefaultGroupByUid($uid);
            $group_id = $default_group['id'];
        }else{
            $this->reduceGroupNum($old_group_id);
            $feedModel->clearGroupFeeds($old_group_id);
        }
        $stmt = $this->db->prepare("update follow set group_id = :group_id  where uid = :uid and f_uid = :f_uid");
        $array = array(
            ':uid' => $uid,
            ':f_uid' => $f_uid,
            ':group_id' => $group_id,
        );
        $stmt->execute($array);
        $stmt = $this->db->prepare("update `group` set num = num + 1 where id = :id");
        $array = array(
            ':id' => $group_id,
        );
        $stmt->execute($array);
        $feedModel->clearGroupFeeds($group_id); //清除分组动态缓存
        return 1;
    }
    public function reduceGroupNum($group_id)
    {
        $stmt = $this->db->prepare("update `group` set num = num - 1 where id = :id ");
        $array = array(
            ':id' => $group_id,
        );
        $stmt->execute($array);
        return 1;
    }
    /*
     * @name 取用户未分组信息
     */
    public function getDefaultGroupByUid($uid)
    {
        $stmt = $this->db->prepare("select * from `group` where uid=:uid and status = 1 and is_default = 1");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function getGroupByUid($uid, $f_uid){
        $stmt = $this->db->prepare("select group_id from follow where uid=:uid and f_uid = :f_uid");
        $array = array(
            ':uid' => $uid,
            ':f_uid' => $f_uid,
        );
        $stmt->execute($array);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    /*
     * @name 根据分组取关注分组的人员信息
     */
    public function getGroupUserById($uid, $group_id, $page, $size, $sort = 1){
        $start = ($page-1)*$size;
        if ($sort == 1) {
            $stmt = $this->db->prepare("SELECT f_uid FROM follow WHERE uid=:uid AND group_id = :group_id and status = 1
            and f_uid in (select uid from user) ORDER BY add_time DESC,id desc limit $start,$size");
        } elseif ($sort == 2) {
            $stmt = $this->db->prepare("SELECT f.f_uid,(SELECT message_id FROM chat_group WHERE uid = f.f_uid AND gid = f.uid
            AND status = 0 ORDER BY add_time DESC LIMIT 1) AS message_id
            FROM follow f WHERE f.uid=:uid AND f.STATUS = 1 AND group_id = :group_id
            and f.f_uid in (select uid from user) ORDER BY message_id DESC,f.id DESC limit $start,$size");
        } elseif ($sort == 3) {
            $stmt = $this->db->prepare("SELECT f.f_uid,(SELECT COUNT(1) FROM follow WHERE f_uid =f.f_uid AND STATUS = 1) AS fans_num
            FROM follow f WHERE f.uid=:uid AND f.STATUS = 1 and group_id=:group_id
            and f.f_uid in (select uid from user) ORDER BY fans_num DESC,f.id DESC limit $start,$size");
        }
        $array = array(
            ':uid' => $uid,
            ':group_id' => $group_id,
        );
        $stmt->execute($array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * 获取新粉丝数量
     * @param $uid
     * @return mixed
     */
    public function getUnReadNum($uid)
    {
        $stmt = $this->db->prepare("select count(*) as num,max(add_time) as last_time from follow where f_uid=:uid  and status = 1 and is_read = 0");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        if($rs['num']==0){
            $stmt_time = $this->db->prepare("select add_time as last_time from follow where f_uid=:uid  and status = 1 and is_read = 1 order by add_time desc limit 1");
            $array = array(
                ':uid' => $uid,
            );
            $stmt_time->execute($array);
            $rs_time = $stmt_time->fetch(PDO::FETCH_ASSOC);
            $rs['last_time'] = $rs_time['last_time'];
        }
        return $rs;
    }

    /**
     * 更新为已读
     * @param $uid
     * @return bool
     */
    public function updateIsRead($uid)
    {
        $stmt = $this->db->prepare("update follow set is_read = 1 where f_uid=:uid  and status = 1 and is_read = 0");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        return true;
    }
    //书房动态用户默认分组
    public function getGroupList($uid)
    {
        $stmt = $this->db->prepare("select * from `group` where uid = :uid and STATUS < 2 ORDER BY is_default DESC,id desc");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRemark($uid, $f_uid)
    {
        $stmt = $this->db->prepare("select remark from follow where uid = :uid and f_uid = :f_uid and status = 1");
        $array = array(
            ':uid' => $uid,
            ':f_uid' => $f_uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['remark'];
    }
    //根据昵称搜索用户
    public function getUserByNickName($uid, $name, $type = 1)
    {
        if ($type == 1) {
            $stmt = $this->db->prepare("SELECT uid FROM `user` WHERE uid IN(SELECT f_uid FROM follow f WHERE uid=:uid AND STATUS=1) AND nick_name LIKE '" . $name . "%' ");
        } elseif ($type == 2) {
            $stmt = $this->db->prepare("SELECT uid FROM `user` WHERE uid IN(SELECT f.f_uid as uid FROM follow f WHERE f.uid=:uid AND f.status=1
            AND (SELECT COUNT(1) FROM follow WHERE uid=f.f_uid AND f_uid=:uid and status=1)>0) AND nick_name LIKE '" . $name . "%' ");
        } elseif ($type == 3) {
            $stmt = $this->db->prepare("SELECT uid FROM `user` WHERE uid IN(SELECT uid FROM follow f WHERE f_uid=:uid AND STATUS=1) AND nick_name LIKE '" . $name . "%' ");
        }
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    //获取新粉丝列表
    public function getNewFans($uid){
        $stmt = $this->db->prepare("select uid,add_time from follow where f_uid=:f_uid and status = 1 and is_read = 0 and uid in (select uid from user)
    order by add_time desc,id desc limit 10");
        $stmt->bindValue ( ':f_uid' ,  $uid ,  PDO :: PARAM_INT );
        $stmt->execute();
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($rs){
            foreach($rs as $k=>$val){
                $userModel = new UserModel();
                $userInfo = $userModel->getUserData($val['uid'],$uid);
                $rs[$k]['uid'] = $userInfo['uid'];
                $rs[$k]['type'] = $userInfo['type'];
                $rs[$k]['did'] = $userInfo['did'];
                $rs[$k]['nick_name'] = $userInfo['nick_name'];
                $rs[$k]['avatar'] = Common::show_img($userInfo['avatar'],1,320,320);
                $rs[$k]['sex'] = $userInfo['sex'];
                $rs[$k]['intro'] = $userInfo['intro'];
                if($userInfo['type']>1){
                    $indexModel = new IndexModel();
                    $info = $indexModel->getAngelInfoByUid($userInfo['uid']);
                    $rs[$k]['intro'] = $info['info'];
                }
                $rs[$k]['stage_num'] = $userInfo['stage_num'];
                $rs[$k]['att_num'] = $userInfo['att_num'];
                $rs[$k]['fans_num'] = $userInfo['fans_num'];
                $rs[$k]['relation'] = $userInfo['relation'];
                $new_num = $this->getUnReadNum($uid);
                $rs[$k]['new_num'] = $new_num['num'];
                $this->updateIsRead($uid);
            }
        }
        return $rs;
    }
    /*
 * @name 增加分组
 */
    public function addGroup($uid, $name){
        if ($this->getGroupNum($uid) >= 20) {
            return -1;
        }
        $isGroup = $this->isGroup($uid, $name); //判断分组名是否存在
        if ($isGroup > 0) {
            return -2;
        }
        $stmt = $this->db->prepare("insert into `group` (uid,name,add_time) values (:uid,:name,:add_time)");
        $array = array(
            ':uid' => $uid,
            ':name' => $name,
            ':add_time' => date("Y-m-d H:i:s"),
        );
        $stmt->execute($array);
        $lastId = $this->db->lastInsertId();
        if ($lastId < 1) {
            return 0;
        }
        return $lastId;
    }
    /*
 * @name 判断分组名是否存在
 */
    public function isGroup($uid, $name, $id = ''){
        if ($id == '') {
            $stmt = $this->db->prepare("select count(id) as num from `group` where uid=:uid and name = :name and (status = 0 or status = 1)");
        } else {
            $stmt = $this->db->prepare("select count(id) as num from `group` where uid=:uid and name = :name and (status = 0 or status = 1) and id <> $id");
        }
        $array = array(
            ':uid' => $uid,
            ':name' => $name,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }

    /*
 * @name 查询分组数
 */
    public function getGroupNum($uid){
        $stmt = $this->db->prepare("select count(id) as num from `group` where uid = :uid and status in (0,1)");
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }
    /*
     * @name 修改分组名称
     */
    public function modifyGroup($uid, $name, $id){
        $isId = $this->getGroupById($id); //判断是否有此信息
        if (!$isId) {
            return -1;
        }
        $isGroup = $this->isGroup($uid, $name, $id); //判断分组名是否存在
        if ($isGroup > 0) {
            return -2;
        }
        $stmt = $this->db->prepare("update `group` set name=:name where id=:id and status < 2");
        $array = array(
            ':name' => $name,
            ':id' => $id,
        );
        $stmt->execute($array);
        return 1;
    }
    /*
     * @name 根据分组的id取分组的信息
     */
    public function getGroupById($id){
        $stmt = $this->db->prepare("select * from `group` where id=:id and status < 2");
        $array = array(
            ':id' => $id,
        );
        $stmt->execute($array);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    /*
     * @name 删除分组
     */
    public function delGroup($uid, $id){
        $isId = $this->getGroupById($id); //判断是否有此信息
        if (!$isId) {
            return -1;
        }
        if ($isId['is_default'] == 1) { //判断分组是否可以删除
            return -2;
        }
        $stmt = $this->db->prepare("update `group` set status=4,num=:num where id=:id and (status = 0 or status = 1)");
        $array = array(
            ':num' => $isId['num'],
            ':id' => $id,
        );
        $stmt->execute($array);
        $stmt = $this->db->prepare("update `group` set num=num+:num where uid=:uid and (status = 0 or status = 1) and is_default = 1");
        $array = array(
            ':num' => $isId['num'],
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $this->modifyFollowGroup($uid, $id);
        $feedModel = new FeedModel();
        $feedModel->clearGroupFeeds($id); //清除分组动态
        return 1;
    }
    /*
 * @name 修改关注所属分组
 */
    public function modifyFollowGroup($uid, $group_id){
        $default = $this->getDefaultGroupByUid($uid);
        $stmt = $this->db->prepare("update follow set group_id = :default_group_id where uid = :uid and group_id = :group_id");
        $array = array(
            ':uid' => $uid,
            ':group_id' => $group_id,
            ':default_group_id' => $default['id'],
        );
        $stmt->execute($array);
        return 1;
    }
    //设置备注
    public function setRemark($uid, $f_uid, $remark){
        $stmt = $this->db->prepare("update follow set remark = :remark where uid = :uid and f_uid = :f_uid");
        $array = array(
            ':remark' => $remark,
            ':uid' => $uid,
            ':f_uid' => $f_uid,
        );
        $stmt->execute($array);
        return true;
    }
    /*
     * @name 加关注(多用户)

    public function addAll($uid, $uid_array, $add_time = '')
    {
        if ($add_time == '') {
            $add_time = date('Y-m-d H:i:s');
        }
        $userModel = new UserModel();
        $user_uid = $userModel->getUserByUid($uid);
        $user_f_uid = $userModel->getUserByUid($f_uid);
        if (!$user_uid) {
            return -1;
        }
        if (!$user_f_uid) {
            return -2;
        }
        $isFollow = $this->isFollow($uid, $f_uid);
        if ($isFollow) {
            return -3;
        }
        $count = $this->getFollowNum($uid);
        if ($count >= $this->max_att_num) {
            return -4;
        }
        if (!$this->addByRedis($uid, $f_uid)) {
            return -5;
        }
        $stmt = $this->db->prepare("insert into follow (uid,f_uid,add_time,status) values (:uid,:f_uid,:add_time,1) ON DUPLICATE KEY update status = 1,is_read = 0,add_time=:add_time");
        $array = array(
            ':uid' => $uid,
            ':f_uid' => $f_uid,
            ':add_time' => $add_time,
        );
        $stmt->execute($array);
        //设置我关注的人的分组
        $this->setFollowGroup($uid,$f_uid);
        return $stmt->rowCount();
    }
 */
}