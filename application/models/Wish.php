<?php

/**
 * Created by PhpStorm.
 * User: lichen
 * Date: 14-9-18
 * Time: 上午9:36
 */
class WishModel
{
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    //插入许愿信息
    public function add($uid,$content,$is_sync)
    {
        if (!$uid) {
            return false;
        }
        $stmt = $this->db->prepare("insert into wish(uid,add_time,content,is_open,status,is_sync)
        values (:uid,:add_time,:content,1,0,:is_sync)");
        $array = array(
            ':uid' => $uid,
            ':add_time' => date('Y-m-d H:i:s',time()),
            ':content' => $content,
            ':is_sync'=> $is_sync

        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        if(!$id){
            return 0;
        }
        if($is_sync ==1){
            $feedModel = new FeedModel();
            $feedModel->add(2,$uid, 'wish', $id);
        }
        return 1;
    }

    //获取许愿信息
    public function getWishById($id)
    {
        $stmt = $this->db->prepare("select id,uid,content,status,add_time from wish where id = :id and status<2");
        $array = array(
            ':id' => $id,
        );
        $stmt->execute($array);
        $wish = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($wish) {
            $userModel = new UserModel();
            $wish['content'] = Common::linkReplace($wish['content']);
            $wish['user'] = $userModel->getUserData($wish['uid']);
        }
        return $wish;
    }
    /**
     * 获得全部许愿记录
     */
    public function getAll($start, $size, $uid = 0)
    {
        if ($uid) {
            $stmt = $this->db->prepare("SELECT id,uid,add_time,content,(SELECT COUNT(1) AS num FROM wish_like AS wl WHERE wl.wish_id = w.id )AS like_num,is_sync
		    FROM wish AS w  WHERE status < 2 AND uid=:uid AND is_open = 1 ORDER BY add_time DESC LIMIT :start,:size");
            $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        } else {
            $stmt = $this->db->prepare("SELECT id,uid,add_time,content,(SELECT COUNT(1) AS num FROM wish_like AS wl WHERE wl.wish_id = w.id )AS like_num,is_sync
		    FROM wish AS w WHERE status < 2 AND is_open = 1  ORDER BY add_time DESC LIMIT :start,:size");
        }
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 获得所有许愿记录的数量
     */
    public function countByUid($uid)
    {
        if ($uid) {
            $stmt = $this->db->prepare("SELECT count(1) as num FROM wish  WHERE status<2 AND is_open = 1 AND uid =:uid");
            $array = array(
                ':uid' => $uid,
            );
            $stmt->execute($array);
        }else {
            $stmt = $this->db->prepare("SELECT count(1) as num FROM wish WHERE status<2 AND is_open = 1");
            $stmt->execute();
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /**
     * 验证用户是否对该许愿记录点赞
     */
    public function checkLike($wish_id, $uid)
    {
        $stmt = $this->db->prepare("SELECT count(1) as num FROM wish_like WHERE wish_id =:wish_id AND uid =:uid");
        $array = array(
            ':wish_id' => $wish_id,
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    /*
     * 获取某条许愿记录的所有点赞用户uid
     */
    public function getLikeUid($wish_id){
        $stmt = $this->db->prepare("SELECT uid FROM wish_like WHERE wish_id =:wish_id AND like_status = 1 ORDER BY like_id DESC");
        $array = array(
            ':wish_id' => $wish_id,
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 点赞
     */
    public function like($id)
    {
        $stmt = $this->db->prepare("UPDATE wish SET like_num=like_num+1 WHERE id =:id");
        $array = array(
            ':id' => $id,
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        return 1;
    }

    /**
     * 添加许愿记录关联
     */
    public function addWishAssociated($wish_id, $uid, $like_status)
    {
        $stmt = $this->db->prepare("INSERT INTO wish_like SET wish_id =:wish_id,uid =:uid,like_status =:like_status");
        $array = array(
            ':wish_id' => $wish_id,
            ':uid' => $uid,
            ':like_status' => $like_status,
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if ($count < 1) {
            return 0;
        }
        return 1;
    }

    public function updateWishStatus($id,$uid)
    {
        $stmt = $this->db->prepare("update wish set status = 4 where id = :id and uid = :uid");
        $array = array(
            ':id' => $id,
            ':uid' => $uid
        );
        $stmt->execute($array);
        return $stmt->rowCount();
    }

}

