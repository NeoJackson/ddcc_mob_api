<?php
/**
 * @name RecommendModel
 * @desc 推荐数据读取使用，猜你喜欢
 * 优先使用相似数据+补充，没有相似数据使用热推
 * $intersection = array_intersect($fruit1, $fruit2, $fruit3);  交集
 * $intersection = array_intersect_assoc($fruit1, $fruit2, $fruit3); 考虑数组的key

 * @author wuzb
 */
class RecommendModel {
    private $db;
    private $redis;

    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }
    /**
     * 相似用户（基于关系推荐）
     * @param int $page
     * @param int $size 每页条数
     * @param $puid     个人主页uid
     * @param $uid      登陆者session uid
     * @return array
     */
    public function similarF($page=1, $size=6, $puid, $uid=0,$guess=0){
        $and = '';
        if($uid){
            $and = ' and ruid NOT IN (select f_uid from follow where uid=:uid and status=1) ';
        }
        $sql_count = "select count(ruid) as num from recommend_follow_user
                      where status > 0 and uid = :puid $and";
        $stmt = $this->db->prepare($sql_count);
        $arr = array(':puid'=>$puid);
        if($uid){
            $arr['uid'] = $uid;
        }
        $stmt->execute($arr);
        $ret = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = $ret['num'];
        if(!$total && !$guess){
            return array('page'=>$page,'total'=>$total,'size'=>$size,'list'=>array());
        }
        if($guess && !$total){
            return false;
        }
        $union = '';
        if($guess && $total){
            $total = ($ret['num'] > 2000) ? 2000 : $ret['num'];
            if($total < 2000){
                $limit = 2000 - $total;
                $union = " union (select uid,null from user_info
                              where uid NOT IN (select f_uid from follow where uid=:uid and status=1)
                              and uid <> :uid
                              and uid NOT IN (select uid from recommend_follow_user where status > 0)
                              order by score desc limit $limit) ";
                $total = 2000;
            }
        }

        $start = ($page - 1) * $size;
        $sql = "select ruid as uid,weight from recommend_follow_user
                where status > 0 and uid = :puid $and $union
                order by weight desc limit $start , $size";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($arr);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $userModel = new UserModel();
        $followModel = new FollowModel();
        foreach ($result as $key => $val) {
            $result[$key] = $userModel->getUserData($val['uid']);
            $result[$key]['uid'] = $val['uid'];
            $result[$key]['relation'] = $followModel->getRelation($uid,$val['uid']);
        }
        return array('page'=>$page,'total'=>$total,'size'=>$size,'list'=>$result);
    }
    //推荐热度用户(书房 - 个人中心 - 猜你喜欢)
    public function getHotUser($page=1, $size=6, $uid){
        $sql_count = "select count(uid) as num from recommend_hot_user
                    where status > 0
                    and uid NOT IN (select f_uid from follow where uid=:uid and status=1)";
        $stmt = $this->db->prepare($sql_count);
        $stmt->execute(array(':uid' => $uid ));
        $ret = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = ($ret['num'] > 2000) ? 2000 : $ret['num'];
        $union = '';
        if($total < 2000){
            $limit = 2000 - $total;
            $union = " union (select uid , null from user_info
                              where uid NOT IN (select f_uid from follow where uid=:uid and status=1)
                              and uid <> :uid
                              and uid NOT IN (select uid from recommend_hot_user where status > 0)
                              order by score desc limit $limit) ";
            $total = 2000;
        }
        $start = ($page - 1) * $size;
        $sql = "select uid , weight from recommend_hot_user where status > 0
                and uid NOT IN (select f_uid from follow where uid=:uid and status=1)
                and uid <> :uid  $union
                order by weight desc limit $start , $size";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':uid' => $uid));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $userModel = new UserModel();
        $followModel = new FollowModel();
        foreach ($result as $key => $val) {
            $result[$key] = $userModel->getUserData($val['uid']);
            $result[$key]['uid'] = $val['uid'];
            $result[$key]['relation'] = $followModel->getRelation($uid,$val['uid']);
        }
        return array('page'=>$page,'total'=>$total,'size'=>$size,'list'=>$result);
    }
}