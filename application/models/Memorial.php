<?php

/**
 * Created by PhpStorm.
 * User: lichen
 * Date: 14-9-27
 * Time: 上午10:43
 */
class MemorialModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
    }

    //插入纪念馆祭拜留言记录
    public function add($id,$m_id,$uid,$name,$type,$content,$intro){
        if(!$id || !$uid){
            return false;
        }
        $stmt = $this->db->prepare("insert into memorial (id,m_id,uid,name,type,content,intro,add_time)values (:id,:m_id,:uid,:name,:type,:content,:intro,:add_time)");
        $array = array(
            ':id' => $id,
            ':m_id' => $m_id,
            ':uid' => $uid,
            ':name' => $name,
            ':type' => $type,
            ':content' => $content,
            ':intro' => $intro,
            ':add_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        $feedModel = new FeedModel();
        $feedModel->add(2,$uid,'memorial',$id);
        return 1;
    }

    //获取纪念馆祭拜留言记录
    public function getMemorialById($id){
        $stmt = $this->db->prepare("select id,m_id,uid,name,type,content,intro,status,add_time from memorial where id = :id and status<2");
        $array = array(
            ':id' => $id,
        );
        $stmt->execute($array);
        $memorial = $stmt->fetch(PDO::FETCH_ASSOC);
        if($memorial){
            $userModel = new UserModel();
            $memorial['content'] = Common::linkReplace($memorial['content']);
            $memorial['intro'] = Common::linkReplace($memorial['intro']);
            $memorial['intro'] = Common::msubstr($memorial['intro'],0,65,'UTF-8');
            $memorial['user'] = $userModel->getUserData($memorial['uid']);
        }
        return $memorial;
    }

    //删除纪念馆祭拜留言记录
    public function del($uid,$id){
        if(!$uid || !$id){
            return false;
        }
        $stmt = $this->db->prepare("update memorial set status = 4, update_time = :update_time where uid = :uid and id = :id ");
        $array = array(
            ':uid' => $uid,
            ':id' => $id,
            ':update_time' => date('Y-m-d H:i:s')
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        $feedModel = new FeedModel();
        $feedModel->del($uid,'memorial',$id);//删除动态信息
        return 1;
    }

}

