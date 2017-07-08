<?php

class TempleModel
{
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }
    //获取全部佛像数据
    public function getPrayGodList() {
        $stmt = $this->db->prepare("SELECT id,name,introduction,type,small_picpath,big_picpath FROM pray_god WHERE type = 0 AND status <= 1 ORDER BY sort ASC");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    //获取贡品数据
    public function getPrayOfferingList($id) {
        if(!$id){
            $stmt = $this->db->prepare("SELECT id,name,picpath,dffective_time,type,title,score FROM pray_offering WHERE type = 0 AND status = 1 ORDER BY sort DESC");
            $stmt->execute();
        }else{
            $stmt = $this->db->prepare("SELECT id,name,picpath,dffective_time,type,title,score FROM pray_offering WHERE type = 0 AND status = 1 AND id=:id ORDER BY sort DESC");
            $array = array(
                ':id' => $id,
            );
            $stmt->execute($array);
        }
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    /**
     * 根据uid和id查询贡品记录
     * @param $uid 用户id
     * @param $id 佛像id
     */
    public function getPrayNoteList($uid, $id) {
        $stmt = $this->db->prepare("SELECT po.id,po.name,po.title,pn.add_time,po.dffective_time FROM pray_note as pn
				LEFT JOIN pray_offering as po ON pn.offering_id = po.id
				LEFT JOIN pray_god as pg ON pg.id = pn.god_id
				WHERE pn.uid =:uid AND pn.god_id =:id AND pn.add_time + (po.dffective_time*3600) >= UNIX_TIMESTAMP(SYSDATE())
				AND pg.status <= 1 AND po.status <= 1");
        $array = array(
            ':uid' => $uid,
            ':id' => $id,
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    /*
     * 根据uid查询拜佛记录
     * @param $uid 用户id
     */
    public function getPrayWishByUid($uid, $start, $size) {
        $stmt = $this->db->prepare("SELECT pw.content,pw.add_time,pg.name FROM pray_wish as pw
				LEFT JOIN pray_god as pg ON pg.id = pw.god_id
				WHERE pw.uid =:uid AND pw.status <= 1 ORDER BY pw.add_time DESC LIMIT :start,:size");
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    /*
     *获取祈福滚动信息
     */
    public function getPrayWishList() {
        $stmt = $this->db->prepare("SELECT pg.id,pw.content,pw.add_time,pg.name,pw.uid FROM pray_wish AS pw
				LEFT JOIN pray_god AS pg ON pg.id = pw.god_id WHERE pw.status <= 1 ORDER BY pw.add_time DESC LIMIT 40");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    /**
     * 根据uid查询拜佛记录总数
     * @param $uid
     * @return mixed
     */
    public function prayWishCountByUid($uid) {
        $stmt = $this->db->prepare("SELECT COUNT(1) AS num FROM pray_wish WHERE uid =:uid AND status < 2");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    /*
    * ajax查询某一用户在某一个佛上的某一个物品是否在有效时间内
    */
    public function isHavePrayNote($uid, $godId,$offeringId) {
        $stmt = $this->db->prepare("SELECT COUNT(1) AS num FROM pray_note AS pn
                LEFT JOIN pray_god AS pg ON pn.god_id = pg.id
                LEFT JOIN pray_offering  AS po ON po.id = pn.offering_id
                WHERE pn.uid =:uid AND pg.id =:godId AND po.id=:offeringId AND pn.add_time + (po.dffective_time*3600) >= UNIX_TIMESTAMP(SYSDATE())");
        $array = array(
            ':uid' => $uid,
            ':godId' => $godId,
            ':offeringId' => $offeringId,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    public function addPrayNote($godId, $offeringId,$uid,$time,$score) {
        $stmt = $this->db->prepare("INSERT INTO pray_note SET god_id =:god_id,offering_id=:offering_id,uid =:uid,add_time =:time,score=:score");
        $array = array(
            ':god_id' => $godId,
            ':offering_id' => $offeringId,
            ':uid' => $uid,
            ':time' => $time,
            ':score' => $score,
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        return 1;
    }
    /*
     * ajax查询某一用户在某一个佛上在有效时间内有无祭拜物品
     */
    public function getNumByUidAndGodId($uid,$godId){
        $stmt = $this->db->prepare("SELECT COUNT(1) AS num FROM pray_note AS pn
                LEFT JOIN pray_god AS pg ON pn.god_id = pg.id
                LEFT JOIN pray_offering  AS po ON po.id = pn.offering_id
                WHERE pn.uid =:uid AND pg.id =:godId AND pn.add_time + (po.dffective_time*3600) >= UNIX_TIMESTAMP(SYSDATE())");
        $array = array(
            ':uid' => $uid,
            ':godId' => $godId,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    //添加祈福
    public function addPrayWish($uid, $godId, $content,$is_sync) {
        $stmt = $this->db->prepare("INSERT INTO pray_wish SET uid =:uid,god_id=:god_id,content =:content,is_sync=:is_sync");
        $array = array(
            ':uid' => $uid,
            ':god_id' => $godId,
            ':content' => $content,
            ':is_sync' => $is_sync,
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        if(!$id){
            return 0;
        }
        if($is_sync ==1){
            $feedModel = new FeedModel();
            $feedModel->add(2,$uid, 'pray', $id);
        }
        return 1;
    }

    //获取祈福信息
    public function getPrayWishById($id)
    {
        $stmt = $this->db->prepare("select id,uid,god_id,content,add_time,status,is_sync from pray_wish where id = :id and status<2");
        $array = array(
            ':id' => $id,
        );
        $stmt->execute($array);
        $prayWish = $stmt->fetch(PDO::FETCH_ASSOC);
        if($prayWish) {
            $userModel = new UserModel();
            $god_info = $this->getGodById($prayWish['god_id']);
            $prayWish['name'] = $god_info['name'];
            $prayWish['pic'] = $god_info['small_picpath'];
            $prayWish['intro'] = Common::msubstr($god_info['introduction'],0,65,'UTF-8');
            $prayWish['content'] = Common::linkReplace($prayWish['content']);
            $prayWish['user'] = $userModel->getUserData($prayWish['uid']);
        }
        return $prayWish;
    }

    //根据祈福对象id查询佛像信息
    public function getGodById($id){
        $stmt = $this->db->prepare("select name,small_picpath,introduction from pray_god where id = :id and status<2");
        $array = array(
            ':id' => $id,
        );
        $stmt->execute($array);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}