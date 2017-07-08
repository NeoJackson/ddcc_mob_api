<?php

/**
 * @name StageModel
 * @desc 驿站留言model层
 * @author {&$AUTHOR&}
 */
class StageMessageModel
{
    private $db;
    private $redis;

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }

    /*
     * 发表驿站留言
     */
    public function addStageMessage($sid, $uid, $content)
    {
        list($content, $atArray) = Common::atUser($uid, $content);
        $sql = 'insert into stage_message(sid,uid,content)  select :sid,:uid,:content from dual
                where  not exists (select * from stage_message where sid = :sid and UNIX_TIMESTAMP() - 10 < UNIX_TIMESTAMP(add_time))';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':sid' => $sid, ':uid' => $uid, ':content' => $content));
        $insertId = $this->db->lastInsertId();
        if (!$insertId) {
            Common::echoAjaxJson(600, "发的太快，休息一下吧");
        }
        $feedModel = new FeedModel();
        if ($atArray) {
            $feedModel->mentionUser(2, $uid, $insertId, $atArray);
        }
        $stageMessage = $this->getStageMessageById($insertId);
        return $stageMessage;
    }
    /*
     *根据驿站ID取得该驿站留言
     * @
     */
    public function getStageMessageBySid($sid)
    {
        $stmt = $this->db->prepare("SELECT id,uid,content,add_time FROM stage_message WHERE  sid = :sid");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    /*
     * 驿站留言列表
     */
    public function stageMessageListByLast($sid,$last_id, $size,$uid=0)
    {

        if($last_id > 0){
            $stmt = $this->db->prepare("select id,uid,sid,content,add_time from stage_message where sid=:sid and status <2  and id < :last_id order by add_time desc limit :size");
            $stmt->bindValue(':last_id', $last_id, PDO::PARAM_INT);
        }else{
            $stmt = $this->db->prepare("select id,uid,sid,content,add_time from stage_message where sid=:sid and status <2 order by add_time desc, id limit :size");
        }
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $userModel = new UserModel();
            foreach($result as $key=>$val){
                $content = Common::linkReplace($val['content']);
                $result[$key]['content'] = Common::showEmoticon($content,0);
                $user = $userModel->getUserData($val['uid'],$uid);
                $result[$key]['did'] = $user['did'];
                $result[$key]['nick_name'] = $user['nick_name'];
                $result[$key]['avatar'] = $user['avatar'];
            }
        }
        return $result;
    }

    public function stageMessageListByPage($sid,$page, $size,$uid=0)
    {
        $start=($page-1)*$size;
        $stmt = $this->db->prepare("select id,uid,sid,content,add_time from stage_message where sid=:sid and status <2 order by add_time desc, id limit :start, :size");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $userModel = new UserModel();
            foreach($result as $key=>$val){
                $con = Common::linkReplace($val['content']);
                $result[$key]['content'] = Common::showEmoticon($con,0);
                $user = $userModel->getUserData($val['uid'],$uid);
                $result[$key]['did'] = $user['did'];
                $result[$key]['nick_name'] = $user['nick_name'];
                $result[$key]['avatar'] = Common::show_img($user['avatar'],1,160,160);
            }
        }
        return $result;
    }

    /*
     *根据留言得到留言信息
     */
    public function getStageMessageById($id)
    {
        $stmt = $this->db->prepare("SELECT id,uid,sid,content,status,add_time FROM stage_message WHERE id = :id and status < 2");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['content'] = Common::showEmoticon($result['content'],0);
        return $result;
    }

    /*
     * 驿站留言条数
     */
    public function countMessageBySid($sid)
    {
        $stmt = $this->db->prepare("SELECT count(id) as num FROM stage_message WHERE sid=:sid  and status < 2");
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /**
     * 判断该留言是否存在
     */
    public function stageMessageIsExist($id,$status=2)
    {
        $stmt = $this->db->prepare("select count(id) as num from stage_message where id=:id and status<:status");
        $array = array(
            ':id' => $id,
            ':status' => $status,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /*
     * 删除留言
     */
    public function updateStageMessageById($id,$status=2)
    {
        $message_num = $this->stageMessageIsExist($id,$status);
        if ($message_num == 0) {
            return -1;
        }
        $stmt = $this->db->prepare("update stage_message set status=4 where id=:id ");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $commonModel = new CommonModel();
        $commonModel->updateRelationByObjId(11,$id,4);
        return 1;
    }

    /*
     * 获取比此id新的评论条数
     */
    public function getNewNumById( $sid, $id ){
        $num = 0;
        if( $sid && $id ){
            $sql = 'SELECT count(*) FROM stage_message WHERE status<2 AND sid=:sid AND id>:id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(
                array(
                    'sid' => $sid,
                    'id' => $id,
                )
            );
            $num = $stmt->fetch(PDO::FETCH_COLUMN);
        }
        return $num;
    }
}