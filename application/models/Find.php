<?php
class FindModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }
    //福报值排行
    public function scoreRank($start,$size){
        $sql = 'select * from ( SELECT @rownum:=@rownum+1 AS rownum,f.*
                                   FROM (SELECT @rownum:=0) r,
                                        (SELECT i.score,i.exp,u.nick_name,u.avatar,u.did,u.uid
                                            FROM user_info i,user u
                                            WHERE i.uid=u.uid
                                             AND   u.uid  NOT IN (8931,8934,8935,8937,8938,8951,11520)
                                            ORDER BY i.score DESC,u.uid limit :start,:size
                                         ) AS f
                                 ) tmp  ';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    //驿站排行
    public function stageTopicRank($start,$size){
        $sql = "SELECT sid ,icon AS cover ,name ,type,(topic_num+event_num) AS num FROM stage WHERE STATUS < 2 ORDER BY num DESC limit :start,:size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
     //商家活动浏览数排行
    public function eventRank($start,$size){
        $sql = "select * from ( SELECT f.*
                                   FROM (SELECT @rownum:=0) r,
                                           (select id,title,cover,type,view_num,add_time
                                            from event
                                            where status <2
                                            order by view_num desc,add_time desc
                                            limit :start,:size
                                            ) AS f
                                     ) tmp";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    //用户活跃排行
    public function activeRank(){
        $sql = 'select * from ( SELECT @rownum:=@rownum+1 AS rownum, f.*
                                   FROM (SELECT @rownum:=0) r,
                                   (SELECT t.num, i.exp, u.nick_name, u.avatar,u.did,u.uid
                                    FROM (SELECT uid, active_days as num
                                            FROM mission_online
                                            WHERE uid  NOT IN (8931,8934,8935,8937,8938,8951,11520)
                                            AND uid IN (SELECT uid FROM user WHERE STATUS=1)
                                            GROUP BY uid
                                            ORDER BY num DESC limit 9
                                            ) t,
                                          user_info i, user u
                                    WHERE i.uid = u.uid
                                    AND   t.uid = u.uid
                                    ORDER BY num DESC,uid) AS f
                                 ) tmp';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $ret = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $ret;
    }
}