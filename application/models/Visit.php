<?php

class VisitModel
{
    private $redis;
    public $s_info = 's:info:'; //驿站总的信息前缀key值  哈希
    public $dailyViewKey = 's:view:';
    const USER_VISIT_STAGE_PREFIX_KEY = 'u:s:visit:'; //用户访问驿站的浏览数统计 有序集合
    public $s_visit = 's:visit:'; //驿站每天的访客数前缀key值 有序集合
    public $s_visits = 's:visits:'; //驿站每天的访客数前缀key值 有序集合
    public $u_s_visit = 'u:s:visit:'; //用户访问驿站的浏览数统计 有序集合


    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }

    /*
    * @最近访问列表和总访问数
    * $id 对应类别的id 比如相册则是相册的id 如果是访问人的主页在是被访问用户的id
    * $type 类别，比如相册 个人主页 驿站 日志等
    */
    public function add($type, $id, $uid)
    {
        $visit_key = $type . ':visit:' . $id;
        $key = $type . ':info:' . $id;
        $add_time = time();
        $this->redis->zAdd($visit_key, $add_time, $uid);
        $this->redis->hIncrBy($key, 'visit_num', 1);
        $size = $this->redis->zSize($visit_key);
        $max_size = 200;
        if ($size > $max_size) {
            $this->redis->zRemRangeByRank($visit_key, 0, $size - $max_size);
        }
    }

    public function addVisitNum($type, $id,$num=1)
    {
        $key = $type . ':info:' . $id;
        return $this->redis->hIncrBy($key, 'visit_num', $num);
    }

    //redis 添加浏览记录
    public function addVisit($type, $id, $date='')
    {
        $key = $type . ':' . $id . ':view';
        if(!$date) $date = date("ymd");
        return $this->redis->zIncrBy($key,1,$date);
    }
   //redis 根据时间获取浏览数
    public function getVisitByDate($type,$id,$date)
    {
        $key = $type . ':' . $id . ':view';
        if(!is_array($date)){
            return false;
        }
        $multi = $this->redis->multi();
        foreach($date as $val){
            $multi->zScore($key,$val);
        }
        return $multi->exec();
    }
   //获取浏览数
    public function getVisitNum($type, $id)
    {
        $key = $type . ':info:' . $id;
        return $this->redis->hGet($key, 'visit_num');
    }
    private function getVisit($type,$id,$start,$end){
        $visit_key = $type.':visit:'.$id;
        if( $type == 'stage' ){
            $visit_key = 's:visits:'.$id;
        }
        return $this->redis->zRevRange($visit_key, $start, $end, true);
    }

    public function addStage($sid, $uid)
    {
        $visit_key = 'stage:visit:' . $uid;
        $add_time = time();
        $this->redis->zAdd($visit_key, $add_time, $sid);
        $size = $this->redis->zSize($visit_key);
        $max_size = 8;
        if ($size > $max_size) {
            $this->redis->zRemRangeByRank($visit_key, 0, $size - $max_size - 1);
        }
    }

    public function getStageList($uid, $start = 0, $end = 8)
    {
        $arr = $this->getVisit('stage', $uid, $start, $end);
        $list = array();
        if ($arr) {
            $stageModel = new StageModel();
            foreach ($arr as $k => $v) {
                $stage = $stageModel->getBasicStageBySid($k);
                if ($stage) {
                    $stage['visit_time'] = date("Y-m-d H:i:s", $v);
                    $list[] = $stage;
                }
            }
        }
        return $list;
    }

    //获取访问列表,包括用户信息
    public function getList($type, $id, $start = 0, $end = -1)
    {
        $arr = $this->getVisit($type, $id, $start, $end);
        $list = array();
        if ($arr) {
            $userModel = new UserModel();
            foreach ($arr as $k => $v) {
                $user = $userModel->getUserData($k);
                $user['visit_time'] = Common::show_time($v);
                $list[] = $user;
            }
        }
        $home_key = $type . ':info:' . $id;
        $size = $this->redis->hGet($home_key, 'visit_num');
        return array(
            'list' => $list,
            'size' => $size
        );
    }
    /*
    * 获取驿站总浏览量
    */
    public function getStagePV( $sid ){
        $key = $this->s_info . $sid;
        return $this->redis->hGet( $key, 'view_num');
    }
    /**
     * 获取用户常去的驿站
     * @param $uid 用户ID
     * @param int $num 获取数量
     * @return array
     */
    public function getUserOftenVisitStage($uid,$num = 4) {
        $key = self::USER_VISIT_STAGE_PREFIX_KEY.$uid;
        $rs = $this->redis->zRevRange($key,0,10);
        $stageModel = new StageModel();
        $list = array();
        foreach($rs as $v) {
            $stageInfo = $stageModel->getBasicStageBySid($v,1);
            if($stageInfo){
                $list[] = $stageInfo;
            }
        }
        return array_slice($list, 0, $num);
    }
    public function addStagePV( $sid ){
        //总浏览量
        $hKey = $this->s_info . $sid;
        $this->redis->hIncrBy($hKey,'view_num',1);//驿站总浏览量

        //每日浏览数
        $this->redis->zIncrBy($this->dailyViewKey.$sid, 1, date('ymd'));  //今日浏览数增1

    }
    public function addStageVisit($sid,$uid) {
        $visitKey = $this->s_visit . $sid;

        //用户常去的驿站
        $key = $this->u_s_visit . $uid;
        $rs = $this->redis->zRange($key,0,-1);
        $time = strtotime('-15 day');
        foreach($rs as $v) {
            $vKey = $this->s_visits.$v;
            $t = $this->redis->zScore($vKey,$uid);
            if($t < $time) {
                $this->redis->zRem($key,$v);
            }
        }
        $this->redis->zIncrBy($key,1,$sid);

        //驿站访客
        $add_time = time();
        $visit_key = 's:visits:'.$sid;

        $score = $this->redis->zScore($visit_key,$uid);
        $score = $score ? $score : 0;
        $date = date('Y-m-d',$score);
        $today = date('Y-m-d');
        if($date != $today) {
            $hKey = $this->s_info.$sid;
            $this->redis->hIncrBy($hKey,'visit_num',1);
            $this->redis->zIncrBy($visitKey,1,date('ymd'));
        }
        $this->redis->zAdd($visit_key,$add_time,$uid);
    }
    public function addSpVisitNum($uid,$sp_id){
        $zKey = 'user:sp_visit'.$uid;
        $this->redis->zIncrBy($zKey,1,date('ymd'));
        $spKey = 'sp:sp_visit'.$sp_id;
        $this->redis->zIncrBy($spKey,1,date('ymd'));
        $stmt = $this->db->prepare("update share_promote set visit_num=visit_num+1 where id=:id");
        $array=array(
            ':id'=>$sp_id
        );
        $stmt->execute($array);
    }
    /**
     * 获取用户某天分享链接点击量
     * @param $uid
     * @param $date 'ymd' => '141224'
     * @return mixed
     */
    public function getSpVisitByDateAndUid($uid,$date) {
        $zKey = 'user:sp_visit'.$uid;
        $score = $this->redis->zScore($zKey,date("ymd",strtotime($date)));
        return $score;
    }
    /**
     * 获取某个分享链接某天点击量
     * @param $sp_id
     * @param $date 'ymd' => '141224'
     * @return mixed
     */
    public function getSpVisitByDateAndSpId($sp_id,$date) {
        $spKey = 'sp:sp_visit'.$sp_id;
        $score = $this->redis->zScore($spKey,date("ymd",strtotime($date)));
        return $score;
    }
}