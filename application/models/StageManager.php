<?php
/**
 * Created by PhpStorm.
 * User: zgh
 * Date: 14-12-24
 * Time: 下午3:26
 */

class StageManagerModel {
    private $db;
    private $redis;
    const STAGE_VIEW_PREFIX_KEY = 's:view:'; //驿站浏览数前缀key值  有序集合
    const STAGE_INFO_PREFIX_KEY = 's:info:'; //驿站信息前缀key值  哈希
    const STAGE_VISIT_PREFIX_KEY = 's:visit:'; //驿站访客数前缀key值 有序集合
    const STAGE_VISITS_PREFIX_KEY = 's:visits:'; //驿站访客记录前缀key值 有序集合
    const STAGE_COMMENT_PREFIX_KEY = 's:comment:'; //驿站评论数前缀key值 有序集合
    const USER_VISIT_STAGE_PREFIX_KEY = 'u:s:visit:'; //用户访问驿站的浏览数统计 有序集合

    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }

    /**
     * 获取统计列表
     * @param $sid 驿站ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return mixed
     */
    public function getStatisticsList($sid,$startDate = '',$endDate = '') {
        $sql = 'select id,sid,num,type,add_time from log_stage_count where sid = :sid and status = 1';
        if($startDate) {
            $sql .= ' and add_time >= :sDate ';
        }
        if($endDate) {
            $sql .= ' and add_time <= :eDate ';
        }
        $sql .= ' group by add_time,type order by add_time desc ';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid',$sid,PDO::PARAM_INT);
        if($startDate) {
            $stmt->bindValue(':sDate',$startDate,PDO::PARAM_STR);
        }
        if($endDate) {
            $stmt->bindValue(':eDate',$endDate,PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 获取数据条数
     * @param $sid
     * @param string $startDate
     * @param string $endDate
     * @return int
     */
    public function getLogCount($sid,$startDate = '',$endDate = '') {
        $sql = 'select count(distinct add_time) as num from log_stage_count where sid = :sid and status = 1';
        if($startDate) {
            $sql .= ' and add_time >= :sDate';
        }
        if($endDate) {
            $sql .= ' and add_time <= :eDate';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid',$sid,PDO::PARAM_INT);
        if($startDate) {
            $stmt->bindValue(':sDate',$startDate,PDO::PARAM_STR);
        }
        if($endDate) {
            $stmt->bindValue(':eDate',$endDate,PDO::PARAM_STR);
        }
        $stmt->execute();
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs ? $rs['num'] : 0;
    }

    /**
     * 获取驿站列表
     * @param $last
     * @param $start
     * @param $limit
     * @return mixed
     */
    public function getStageList($last,$start,$limit) {
        $stmt = $this->db->prepare('select sid from stage where sid > :sid order by sid asc limit :start,:limit');
        $stmt->bindValue(':sid',$last,PDO::PARAM_INT);
        $stmt->bindValue(':start',$start,PDO::PARAM_INT);
        $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 添加日志
     * @param $sid 驿站ID
     * @param $type 驿站日志统计类型 1:浏览数 2访客数 3新增成员数 4成员签到数 5新增帖子数 6新增评论数 7新增共享数 8新增照片数 9新增留言数
     * @param $date 日志日期 date('Y-m-d')
     */
    public function addLog($sid,$type,$date) {
        $insert = $this->db->prepare('insert into log_stage_count(sid,num,type,add_time,status) values(:sid,0,:type,:add_time,0)');
        $insert->execute(array(':sid'=>$sid,':type'=>$type,':add_time'=>$date));
    }

    /**
     * 获取未完成的初始化的日志数据
     * @param $limit
     * @return mixed
     */
    public function getUnfinishedLogData($limit) {
        $stmt = $this->db->prepare('select id,sid,type,add_time from log_stage_count where status = 0 order by id asc limit :limit');
        $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 更新日志数据
     * @param $id
     * @param $num
     */
    public function updateLogDataById($id,$num) {
        $stmt = $this->db->prepare('update log_stage_count set num = :num,status = 1 where id = :id');
        $stmt->execute(array(':num'=>$num,':id'=>$id));
    }

    /**
     * 判断日志是否存在
     * @param $sid 驿站ID
     * @param $type 驿站日志统计类型 1:浏览数 2访客数 3新增成员数 4成员签到数 5新增帖子数 6新增评论数 7新增共享数 8新增照片数 9新增留言数
     * @param $date 日志日期 date('Y-m-d')
     * @return int
     */
    public function logIsExists($sid,$type,$date) {
        $stmt = $this->db->prepare('select count(1) as c from log_stage_count where sid = :sid and type = :type and add_time = :add_time');
        $stmt->execute(array(':sid'=>$sid,':type'=>$type,':add_time'=>$date));
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs ? $rs['c'] : 0;
    }

    /**
     * 获取某天的日志数据
     * @param $sid 驿站ID
     * @param $type 驿站日志统计类型 1:浏览数 2访客数 3新增成员数 4成员签到数 5新增帖子数 6新增评论数 7新增共享数 8新增照片数 9新增留言数
     * @param $date 日志日期 date('Y-m-d')
     * @return mixed
     */
    public function getLogDataByDate($sid,$type,$date) {
        $stmt = $this->db->prepare('select id,sid,num,type,add_time from log_stage_count where sid = :sid and type = :type and add_time = :add_time');
        $stmt->execute(array(':sid'=>$sid,':type'=>$type,':add_time'=>$date));
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs;
    }

    /**
     * 添加驿站浏览数
     * @param $sid 驿站ID
     */
    public function addView($sid) {
        $zKey = self::STAGE_VIEW_PREFIX_KEY.$sid;
        $this->redis->zIncrBy($zKey,1,date('ymd'));  //今日浏览数增1
        $hKey = self::STAGE_INFO_PREFIX_KEY.$sid;
        $this->redis->hIncrBy($hKey,'view_num',1); //驿站浏览总数增1
    }

    /**
     * 获取驿站某天浏览数
     * @param $sid 驿站ID
     * @param $date 'ymd' => '141224'
     * @return mixed
     */
    public function getViewByDate($sid,$date) {
        $zKey = self::STAGE_VIEW_PREFIX_KEY.$sid;
        $score = $this->redis->zScore($zKey,$date);
        if(!$score) {
            $rs = $this->getLogDataByDate($sid,1,date('Y-m-d',strtotime($date)));
            $score = $rs ? $rs['num'] : 0;
        }
        return $score;
    }

    /**
     * 添加访问数
     * @param $sid 驿站ID
     * @param $uid 访客ID
     */
    public function addVisit($sid,$uid) {
        $visitKey = self::STAGE_VISIT_PREFIX_KEY.$sid;
        $visitsKey = self::STAGE_VISITS_PREFIX_KEY.$sid;
        $score = $this->redis->zScore($visitsKey,$uid);
        $score = $score ? $score : 0;
        $date = date('Y-m-d',$score);
        $today = date('Y-m-d');
        if($date != $today) {
            $this->redis->zIncrBy($visitKey,1,date('ymd'));
            $hKey = self::STAGE_INFO_PREFIX_KEY.$sid;
            $this->redis->hIncrBy($hKey,'visit_num',1);
        }
        $this->redis->zAdd($visitsKey,time(),$uid);
        $key = self::USER_VISIT_STAGE_PREFIX_KEY.$uid;
        $rs = $this->redis->zRange($key,0,-1);
        $time = strtotime('-15 day');
        foreach($rs as $v) {
            $vKey = self::STAGE_VISITS_PREFIX_KEY.$v;
            $t = $this->redis->zScore($vKey,$uid);
            if($t < $time) {
                $this->redis->zRem($key,$sid);
            }
        }
        $this->redis->zIncrBy($key,1,$sid);
    }


    /**
     * 驿站访客列表
     * @param int $sid      驿站id
     * @param int $page     当前页
     * @param int $size     每页条数
     * @return array
     */
    public function getVisitor($sid,$page=1,$size=5,$uid_t=0){
        $key = self::STAGE_VISITS_PREFIX_KEY.$sid;
        $total = $this->redis->zSize($key);
        $start = ($page-1)*$size;
        $end = $start + $size - 1;
        $ret = $this->redis->zRevRange($key, $start, $end,true);
        $userModel = new UserModel();
        $userData = array();
        foreach($ret as $uid => $time){
            $user = $userModel->getUserData($uid,$uid_t);
            $user['visit_time'] = $time;
            $userData[] = $user;
        }
        return array('total'=>$total, 'list'=>$userData);
    }

    /**
     * 获取用户常去的驿站
     * @param $uid 用户ID
     * @param int $num 获取数量
     * @return array
     */
    public function getUserOftenVisitStage($uid,$num = 4) {
        $key = self::USER_VISIT_STAGE_PREFIX_KEY.$uid;
        $rs = $this->redis->zRevRange($key,0,$num);
        $stageModel = new StageModel();
        $list = array();
        foreach($rs as $v) {
            $list[] = $stageModel->getBasicStageBySid($v);
        }
        return $list;
    }

    /**
     * 获取驿站某天访客数量
     * @param $sid 驿站ID
     * @param $date 'ymd' => '141224'
     * @return mixed
     */
    public function getVisitByDate($sid,$date) {
        $zKey = self::STAGE_VISIT_PREFIX_KEY.$sid;
        $score = $this->redis->zScore($zKey,$date);
        if(!$score) {
            $rs = $this->getLogDataByDate($sid,2,date('Y-m-d',strtotime($date)));
            $score = $rs ? $rs['num'] : 0;
        }
        return $score;
    }

    /**
     * 根据时间获取驿站某天的新增成员数
     * @param $sid 驿站ID
     * @param $date date('Y-m-d')
     * @return int
     * @desc 驿站如含权限需审核通过  则add_time为审核通过的时间  审核通过的时候需更新add_time
     */
    public function getNewMemberTotalByDate($sid,$date) {
        $stmt = $this->db->prepare('select count(uid) as total from stage_user where sid = :sid and status = 1 and date(add_time) = :date');
        $stmt->execute(array(':sid'=>$sid,':date'=>$date));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['total'] : 0;
    }

    /**
     * 根据时间获取某天的驿站签到人数
     * @param $sid 驿站ID
     * @param $date 签到日期 date('Y-m-d')
     * @return int
     */
    public function getCheckInTotalByDate($sid,$date) {
        $stmt = $this->db->prepare('select count(uid) as total from stage_checkin where sid = :sid and date(add_time) = :add_time');
        $stmt->execute(array(
            ':sid'=>$sid,
            ':add_time'=>$date
        ));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['total'] : 0;
    }

    /**
     * 添加驿站评论数
     * @param $sid 驿站ID
     * @desc 对帖子进行评论时需调用
     */
    public function addCommentNum($sid) {
        $zKey = self::STAGE_COMMENT_PREFIX_KEY.$sid;
        $this->redis->zIncrBy($zKey,1,date('ymd'));  //今日评论数增1
        $hKey = self::STAGE_INFO_PREFIX_KEY.$sid;
        $this->redis->hIncrBy($hKey,'comment_num',1); //驿站评论总数增1
    }

    /**
     * 获取驿站某天的评论数
     * @param $sid 驿站ID
     * @param $date date('ymd')
     * @return int
     */
    public function getCommentNumByDate($sid,$date) {
        $zKey = self::STAGE_COMMENT_PREFIX_KEY.$sid;
        $score = $this->redis->zScore($zKey,$date);
        if(!$score) {
            $rs = $this->getLogDataByDate($sid,6,date('Y-m-d',strtotime($date)));
            $score = $rs ? $rs['num'] : 0;
        }
        return $score;
    }

    /**
     * 根据时间获取某天驿站新增帖子数
     * @param $sid 驿站ID
     * @param $date 帖子发表日期 date('Y-m-d')
     * @return int
     */
    public function getNewTopicToTalByDate($sid,$date) {
        $stmt = $this->db->prepare('select count(1) as total from topic where sid = :sid and date(add_time) = :add_time and status < 2');
        $stmt->execute(array(':sid'=>$sid,':add_time'=>$date));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['total'] : 0;
    }

    /**
     * 获取驿站某天留言数
     * @param $sid 驿站ID
     * @param $date 时间 date('ymd')
     * @return int
     */
    public function getMessageTotalByDate($sid,$date) {
        $stmt = $this->db->prepare('select count(1) as total from stage_message where sid = :sid and date(add_time) = :add_time and status < 2');
        $stmt->execute(array(':sid'=>$sid,':add_time'=>$date));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['total'] : 0;
    }

    /**
     * 获取某个时间段的驿站某种数量
     * @param $type 获取类型 1新增用户 2签到  3新增帖子 4新增留言 5浏览数 6访客数 7评论数
     * @param $sid 驿站ID
     * @param string $startDate 开始时间 date('Y-m-d H:i:s')
     * @param string $endDate 结束时间 date('Y-m-d H:i:s')
     * @return int
     */
    public function getTotalByType($type, $sid , $startDate = '' , $endDate = '') {
        $hKey = self::STAGE_INFO_PREFIX_KEY.$sid;
        $sql = '';
        switch($type) {
            case 1:
                $sql .= 'select count(1) as total from stage_user where sid = :sid and status = 1';
                break;
            case 2:
                $sql .= 'select count(1) as total from stage_checkin where sid = :sid';
                break;
            case 3:
                $sql .= 'select count(1) as total from topic where sid = :sid and status < 2';
                break;
            case 4:
                $sql .= 'select count(1) as total from stage_message where sid = :sid and status < 2';
                break;
            case 5:
                $rs = $this->redis->hGet($hKey,'view_num');
                return $rs?$rs:0;
            case 6:
                $rs = $this->redis->hGet($hKey,'visit_num');
                return $rs?$rs:0;
            case 7:
                $rs = $this->redis->hGet($hKey,'comment_num');
                return $rs?$rs:0;
            default:
                return 0;
        }
        if($startDate) {
            $sql .= ' and add_time > :sDate ';
        }
        if($endDate) {
            $sql .= ' and add_time < :eDate ';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid',$sid,PDO::PARAM_INT);
        if($startDate) {
            $stmt->bindValue(':sDate',$startDate,PDO::PARAM_STR);
        }
        if($endDate) {
            $stmt->bindValue(':eDate',$endDate,PDO::PARAM_STR);
        }
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['total'] : 0;
    }

    /**
     * 获取驿站的近30天统计数据
     * @param $sid 驿站ID
     * @param $type 统计类型 1:浏览数 2访客数 3新增成员数 4成员签到数 5新增帖子数 6新增评论数 7新增共享数 8新增照片数 9新增留言数
     * @return mixed
     */
    public function listLogCount($sid,$type) {
        switch($type) {
            case 1:
                $zKey = self::STAGE_VIEW_PREFIX_KEY.$sid;
                $views = $this->redis->zRange($zKey,0,-1,true);
                foreach($views as $k=>$v) {
                    if($k == date('ymd')) {
                        continue;
                    }
                    $d = date('Y-m-d',strtotime($k));
                    $isExists = $this->logIsExists($sid,$type,$d);
                    if(!$isExists){
                        $insert = $this->db->prepare('insert into log_stage_count(sid,num,type,add_time) values(:sid,:num,:type,:add_time)');
                        $insert->execute(array(':sid'=>$sid,':num'=>$v,':type'=>$type,':add_time'=>$d));
                        $this->redis->zRem($zKey,$k);
                    }
                }
                break;
            case 2:
                $zKey = self::STAGE_VISIT_PREFIX_KEY.$sid;
                $visits = $this->redis->zRange($zKey,0,-1,true);
                foreach($visits as $k=>$v) {
                    if($k == date('ymd')) {
                        continue;
                    }
                    $d = date('Y-m-d',strtotime($k));
                    $isExists = $this->logIsExists($sid,$type,$d);
                    if(!$isExists){
                        $insert = $this->db->prepare('insert into log_stage_count(sid,num,type,add_time) values(:sid,:num,:type,:add_time)');
                        $insert->execute(array(':sid'=>$sid,':num'=>$v,':type'=>$type,':add_time'=>$d));
                        $this->redis->zRem($zKey,$k);
                    }
                }
                break;
            case 3:
                break;
            case 4:
                break;
            case 5:
                break;
            case 6:
                $zKey = self::STAGE_COMMENT_PREFIX_KEY.$sid;
                $comments = $this->redis->zRange($zKey,0,-1,true);
                foreach($comments as $k=>$v) {
                    if($k == date('ymd')) {
                        continue;
                    }
                    $d = date('Y-m-d',strtotime($k));
                    $isExists = $this->logIsExists($sid,$type,$d);
                    if(!$isExists){
                        $insert = $this->db->prepare('insert into log_stage_count(sid,num,type,add_time) values(:sid,:num,:type,:add_time)');
                        $insert->execute(array(':sid'=>$sid,':num'=>$v,':type'=>$type,':add_time'=>$d));
                        $this->redis->zRem($zKey,$k);
                    }
                }
                break;
            case 7:
                break;
            case 8:
                break;
            case 9:
                break;
            default:
                break;
        }
        $sql = 'SELECT id,sid,num,`type`,add_time FROM log_stage_count WHERE TO_DAYS(add_time) BETWEEN TO_DAYS(NOW()) - 19
                AND TO_DAYS(NOW()) + 1 AND sid = :sid and `type` = :type ORDER BY add_time ASC ';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':sid'=>$sid,':type'=>$type));
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rs;
    }

    /**
     * 更新驿站基本信息
     */
    public function updateBaseStage($sid,$intro,$mobile){
        $stageModle  = new StageModel();
        $stage_num = $stageModle->stageIsExist($sid);
        if($stage_num == 0){
            return -1;
        }
        $stmt = $this->db->prepare("update stage set mobile=:mobile,intro=:intro,
        update_time=:update_time where sid=:sid");
        $array = array(
            ':mobile' => $mobile,
            ':intro' => $intro,
            ':sid' => $sid,
            'update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if($rs < 1){
            return 0;
        }
        $stageModle->clearStageData($sid);//清除缓存里驿站信息
        return 1;
    }

    /**
     * 更新驿站高级信息
     */
    public function updateAdvanceStage($sid, $reviewPer, $permission){
        $stmt = $this->db->prepare("update stage set review_permission=:review_permission, permission=:permission,
        update_time=:update_time where sid=:sid");
        $arr = array(
            ':review_permission' => $reviewPer,
            ':permission' => $permission,
            ':sid' => $sid,
            'update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($arr);
        $rst = $stmt->rowCount();
        return $rst == 0 ? false : true;
    }

    /**
     * 保存驿站帖子分类信息
     */
    public function saveCate($uid, $sid, $name, $num){
        $cate_num = $this->getTopicCateNumBySid($sid);
        if($cate_num >= $num){
            return -2;
        }
        $cate_all_num = $this->getAllTopicCateNumBySid($sid);
        $sort = $cate_all_num+1;
        $stmt = $this->db->prepare("insert into topic_cate (sid,name,sort,uid,add_time) values (:sid,:name,:sort,:uid,:add_time)
        on duplicate key update name = :name, status = 1, sort = :sort, add_time = :add_time ");
        $array = array(
            ':sid' => $sid,
            ':name' => $name,
            ':uid' => $uid,
            ':sort' => $sort,
            ':add_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        if($id<1){
            return 0;
        }
        return $id;
    }

    /**
     * 移动帖子分类
     */
    public function moveCate($ids,$uid,$sid){
        foreach($ids as $key=>$val){
            if($val){
                $sort = $key+1;
                $stmt = $this->db->prepare("update topic_cate set sort = :sort where id = :id");
                $array = array(
                    ':sort' => $sort,
                    ':id' => $val
                );
                $stmt->execute($array);
            }
        }
        return 1;
    }

    /**
     * 删除驿站帖子分类信息
     */
    public function delCate($id){
        $stmt = $this->db->prepare("update topic_cate set status=0 where id = :id");
        $array = array(
            ':id' => $id,
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        $stmt = $this->db->prepare("update topic set cate_id=0 where cate_id = :cate_id");
        $array = array(
            ':cate_id' => $id,
        );
        $stmt->execute($array);
        return 1;
    }


    /**
     * 当前用户是否加入该驿站及加入驿站信息
     */
    public function isJoinStage($sid,$uid){
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
     * 更新驿站帖子分类信息
     */
    public function updateCate($id,$name){
        $stmt = $this->db->prepare("update topic_cate set name=:name,update_time=:update_time where id = :id");
        $array = array(
            ':id' => $id,
            ':name' => $name,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        return 1;
    }

    /**
     * 统计该驿站下的未删除的帖子分类
     */
    public function getTopicCateNumBySid($sid){
        $stmt_select = $this->db->prepare("select count(id) as num from topic_cate where sid =:sid and status=1");
        $array = array(
            ':sid' => $sid,
        );
        $stmt_select->execute($array);
        $result = $stmt_select->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /**
     * 统计该驿站下的所有帖子分类
     */
    public function getAllTopicCateNumBySid($sid){
        $stmt_select = $this->db->prepare("select count(id) as num from topic_cate where sid =:sid");
        $array = array(
            ':sid' => $sid,
        );
        $stmt_select->execute($array);
        $result = $stmt_select->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /*
     * 驿站评论权限修改
     */
    public function modifyReviewPermisson($sid, $reviewPermission){
        $rst = false;
        if ($sid && $reviewPermission){
            $sql = 'update stage set review_permission=:review_permission,update_time=:update_time where sid=:sid';
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
            $stmt->bindValue(':review_permission', $reviewPermission, PDO::PARAM_INT);
            $stmt->bindValue(':update_time', date('Y-m-d H:i:s'), PDO::PARAM_STR);
            $stmt->execute();
            $rst = $stmt->rowCount();
        }
        return $rst == 0 ? false : true;
    }

    /*
     * 加入驿站权限修改
     */
    public function modifyPermission($sid, $permission){
        $rst = false;
        if ($sid && $permission){
            $sql = 'update stage set permission=:permission,update_time=:update_time where sid=:sid';
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
            $stmt->bindValue(':permission', $permission, PDO::PARAM_INT);
            $stmt->bindValue(':update_time', date('Y-m-d H:i:s'), PDO::PARAM_STR);
            $stmt->execute();
            $rst = $stmt->rowCount();
        }
        return $rst == 0 ? false : true;
    }

    /*
     * 驿站权限修改后 把stage_user表中未审核的转变成审核通过
     */
    public function setUserStatus($sid){
        $rst = false;
        if ( $sid ){
            $sql = 'update stage_user set status=1,update_time=:update_time where sid=:sid AND status=0';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                ':sid' => $sid,
                ':update_time' => date('Y-m-d H:i:s')
            ));
            $rst = $stmt->rowCount();
        }
        return $rst == 0 ? false : true;
    }

    /*
     * 转移驿站
     */
    public function updateStageOwner($sid, $souUserId, $dstUserId){
        if($sid && $dstUserId && $souUserId){

            //更新stage表
            $sql = 'UPDATE stage SET uid=:uid WHERE sid=:sid';
            $stmtZero = $this->db->prepare($sql);
            $stmtZero->execute(array(
                ':uid' => $dstUserId,
                ':sid' => $sid,
            ));
            $stepZero = $stmtZero->rowCount();

            //更新stage_user表
            $stepOne = $this->setUserOwner($sid, $dstUserId);

            $stepTwo = $this->delStageMember($sid, $souUserId);

            if($stepZero && $stepOne && $stepTwo ==1 ){
                return true;
            }
        }
    }

    public function delStageMember($sid, $uid){
        if($sid && $uid){
            //更新stage_user表
            $sql = 'UPDATE stage_user SET role=3,status=3 WHERE sid=:sid AND uid=:uid';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                ':sid' => $sid,
                ':uid' => $uid,
            ));
            $rst = $stmt->rowCount();
            return $rst;
        }
    }

    public function setUserOwner($sid, $uid){
        //更新stage_user表
        if($sid && $uid){

            //判定stage_user表此用户是否存在
            $sql = 'SELECT id FROM  stage_user WHERE sid=:sid AND uid=:uid';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                ':sid' => $sid,
                ':uid' => $uid,
            ));
            $rstZero = $stmt->fetch(PDO::FETCH_COLUMN);
            if($rstZero){
                $sql = 'UPDATE stage_user SET role=1,status=1,update_time=:update_time WHERE sid=:sid AND uid=:uid';
                $stmt = $this->db->prepare($sql);
                $stmt->execute(array(
                    ':sid' => $sid,
                    ':uid' => $uid,
                    ':update_time' => date('Y-m-d H:i:s'),
                ));
                $rst = $stmt->rowCount();
                return $rst;
            }else{
                //stage_user表不存在则新增记录
                $sql = 'INSERT stage_user (sid, uid, role, status, add_time) VALUES (:sid, :uid, :role, :status, :add_time)';
                $stmt = $this->db->prepare($sql);
                $stmt->execute(array(
                    ':sid' => $sid,
                    ':uid' => $uid,
                    ':role' => 1,
                    ':status' => 1,
                    ':add_time' => date('Y-m-d H:i:s'),
                ));
                $rst = $stmt->rowCount();
                return $rst;
            }
        }
    }

    public function getUserAdminStageNum($uid){
        $total = 0;
        if($uid){
            $sql = 'SELECT count(id) AS cnt FROM stage_user WHERE uid=:uid AND role in (1,2) AND status=1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array('uid' => $uid));
            $total = $stmt->fetch(PDO::FETCH_COLUMN);
        }
        return $total;
    }

    public function userValidation($uid){
        if($uid){
            $sql = 'SELECT count(uid) FROM user WHERE status=1 AND uid=:uid';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array('uid' => $uid));
            $rst = $stmt->fetch(PDO::FETCH_COLUMN);
            return ($rst) ? true : false;
        }
    }

}