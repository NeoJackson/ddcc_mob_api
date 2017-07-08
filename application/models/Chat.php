<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 15-1-15
 * Time: 下午2:42
 */
class ChatModel
{
    private $db;
    private $redis;
    private static $user_msg_name = 'im_info:im_offline_msg:user_msg_list';
    private static $stage_msg_name = 'im_info:im_offline_msg:stage_msg_list';
    private static $chatIMuser_msg_name = 'chatIMuser';
    private static $chatIMstage_msg_name = 'chatIMstage';

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }

    //设置最近聊天的驿站和用户
    public function setLastChat($uid, $add_time, $arr)
    {
        $key = "u:chat:" . $uid;
        if ($this->redis->zSize($key) > 20) {
            $this->redis->zRemRangeByRank($key, 0, 0);
        }
        $this->redis->zAdd($key, $add_time, json_encode($arr));
    }

    //清除和某个用户的未读数量
    public function clearUserUnRead($from_uid, $to_uid)
    {
        if (!$from_uid || !$to_uid) {
            return false;
        }
        $sql = "update chat_msg set is_read = 1,update_time=:update_time where from_uid = :from_uid and to_uid = :to_uid and is_read = 0";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':from_uid' => $from_uid,
            ':to_uid' => $to_uid,
            ':update_time' => date('Y-m-d H:i:s'),
        );
        $stmt->execute($array);
    }

    //获取内容
    public function getContent($id)
    {
        if (!$id) {
            return false;
        }
        $stmt = $this->db->prepare("select type,content,add_time from chat_content where id = :id");
        $array = array(
            ':id' => $id,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            if ($result['type'] == 0) {
                $result['content'] = Common::deleteHtml($result['content']);
            }
        }
        return $result;
    }

    //获取会话列表
    public function getChatGroupList($uid)
    {
        $sql = "select type,gid from chat c where uid = :uid and status = 1 order by message_time desc";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //获取和某个人的未读消息条数
    public function getUserUnReadNum($from_uid, $to_uid)
    {
        if (!$from_uid || !$to_uid) {
            return false;
        }
        $sql = "select count(*) as num from chat_msg where from_uid = :from_uid and to_uid = :to_uid and is_read = 0 and to_status <2 ";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':from_uid' => $from_uid,
            ':to_uid' => $to_uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //获取和某个驿站的未读消息条数
    public function getStageUnReadNum($sid, $to_uid)
    {
        if (!$sid || !$to_uid) {
            return false;
        }
        $message_list = $this->getMsg('stage', $to_uid);
        $message_list = array_map("self::json_map", $message_list);
        $num = 0;
        foreach ($message_list as $v) {
            $data = (array)$v;
            $stage_data = (array)$data['stage'];
            if ($stage_data['sid'] == $sid) {
                $num++;
            }
        }
        return $num;
    }

    //根据消息获取内容
    public function getContentByMessage($id)
    {
        $message = $this->getMessage($id);
        if ($message) {
            $content = $this->getContent($message['content_id']);
            return $content;
        }
        return false;
    }

    //获取未读的消息的总条数
    public function getUnReadNum($to_uid)
    {
        if (!$to_uid) {
            return false;
        }
        //把当前用户屏蔽的对话所有未读数清除
        $this->delStageMessageRemind($to_uid);
        $this->delUserMessageRemind($to_uid);
        $userUnReadNum = $this->getAllUserUnReadNum($to_uid);
        $stageUnReadNum = $this->getAllStageUnReadNum($to_uid);
        return $userUnReadNum + $stageUnReadNum;
    }

    //获取未读私聊总条数
    public function getAllUserUnReadNum($to_uid)
    {
        if (!$to_uid) {
            return false;
        }
        $sql = "select count(*) as num from chat_msg where to_uid=:to_uid and is_read = 0 and status < 2 AND from_uid IN
        (SELECT gid FROM chat WHERE uid =:to_uid AND message_type =1)";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':to_uid' => $to_uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    public function getAllStageUnReadNum($to_uid)
    {
        $message_list = $this->getMsg('stage', $to_uid);
        $message_list = array_map("self::json_map", $message_list);
        return count($message_list);
    }

    private static function json_map($value)
    {
        return json_decode($value);
    }

    //添加用户消息
    public function addUserMessage($from_uid, $to_uid, $type, $content, $userArray = '', $share_type = '', $isSocket = 1, $isRedis = 1)
    {
        $content_id = $this->addContent($type, $content);
        if (!$content_id) {
            return false;
        }
        $time = date('Y-m-d H:i:s');
        $this->addChat($from_uid, $to_uid, 1, $time);
        $this->addChat($to_uid, $from_uid, 1, $time);
        $message_type = $this->getMessageType($from_uid, $to_uid);
        $id = $this->addChatMsg($from_uid, $to_uid, $content_id);
        if (!$id) {
            return false;
        }
        $accept = $message_type['message_type'] == 1 ? true : false;
        if ($isRedis == 1) {
            $this->say($id, $userArray, $content, $type, $accept, $time, $to_uid, $share_type);
        }
        $new_message = array(
            'type' => 'say',
            'from_client' => $userArray,
            'message_id' => $id,
            'message' => $content,
            'message_type' => $type,
            'accept' => $accept,
            'time' => $time,
            'share_type' => $share_type
        );
        if ($isSocket == 1) {
            $this->pushChat(array('0' => array('uid' => $to_uid, 'message_type' => $message_type['message_type'])), $new_message);
        }
        return $id;
    }

    //添加内容
    protected function addContent($type, $content)
    {
        $stmt = $this->db->prepare("insert into chat_content (type,content)values(:type,:content)");
        $array = array(
            ":type" => $type,
            ":content" => $content
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        return $id;
    }

    //获取最新的用户消息
    public function getUserMessage($from_uid, $to_uid, $last_id)
    {
        $this->clearUserUnRead($from_uid, $to_uid);
        $stmt = $this->db->prepare("select id,from_uid,to_uid,content_id,add_time from chat_msg where ((from_uid = :from_uid and to_uid = :to_uid  and status = 0) or (from_uid = :to_uid and to_uid = :from_uid and to_status = 0)) and id > :last_id order by id limit 0,10");
        $array = array(
            ':from_uid' => $from_uid,
            ':to_uid' => $to_uid,
            ':last_id' => $last_id
        );
        $stmt->execute($array);
        $result = $stmt->fetchALL(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $key => $val) {
                $content = $this->getContent($val['content_id']);
                $result[$key]['type'] = $content['type'];
                $result[$key]['content'] = $content['content'];
                $result[$key]['add_time'] = Common::show_time($val['add_time']);
                $result[$key]['add_unix_time'] = strtotime($val['add_time']);
                $this->delMsg('user', $to_uid, $val['id']);
            }
        }
        return $result;
    }

    //获取某个用户的聊天记录
    public function getLastUserMessage($from_uid, $to_uid, $last_id, $offset, $size)
    {
        $this->clearUserUnRead($from_uid, $to_uid);
        if ($last_id > 0) {
            $stmt = $this->db->prepare("select id,from_uid,content_id,add_time from chat_msg where ((from_uid = :from_uid and to_uid = :to_uid and status <2) or (from_uid = :to_uid and to_uid = :from_uid and to_status <2)) and id < :last_id order by id desc limit :offset,:size");
            $stmt->bindValue(':last_id', $last_id, PDO::PARAM_INT);
        } else {
            $stmt = $this->db->prepare("select id,from_uid,content_id,add_time from chat_msg where ((from_uid = :from_uid and to_uid = :to_uid and status <2) or (from_uid = :to_uid and to_uid = :from_uid and to_status <2)) order by id desc limit :offset,:size");
        }
        $stmt->bindValue(':from_uid', $from_uid, PDO::PARAM_INT);
        $stmt->bindValue(':to_uid', $to_uid, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchALL(PDO::FETCH_ASSOC);
        if ($result) {
            $result = array_reverse($result);
            foreach ($result as $key => $val) {
                $content = $this->getContent($val['content_id']);
                $result[$key]['type'] = $content['type'];
                $result[$key]['content'] = $content['content'];
                $result[$key]['add_time'] = Common::show_time($val['add_time']);
                $result[$key]['add_unix_time'] = strtotime($val['add_time']);
                $this->delMsg('user', $to_uid, $val['id']);
            }
        }
        return $result;
    }

    //添加驿站消息
    public function addStageMessage($from_uid, $sid, $type, $content, $userArray = '', $isSocket = 1, $isRedis = 1)
    {
        $content_id = $this->addContent($type, $content);
        if (!$content_id) {
            return false;
        }
        $sql = 'insert into chat_stage (sid,uid,content_id) values (:sid,:uid,:content_id)';
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':sid' => $sid,
            ':uid' => $from_uid,
            ':content_id' => $content_id
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        if (!$id) {
            return false;
        }
        $stageModel = new StageModel();
        $data = $stageModel->getBasicStageBySid($sid);
        $this->updateStageChat($sid);
        $uidArray = $this->getStageChatList($sid, $from_uid);
        $stage['sid'] = $data['sid'];
        $stage['name'] = $data['name'];
        $stage['intro'] = $data['intro'];
        $stage['icon'] = $data['icon'];
        if ($isRedis == 1) {
            $this->stage_say($id, $userArray, $stage, $content, $type, $uidArray);
        }
        $time = date('Y-m-d H:i:s');
        $new_message = array(
            'type' => 'stage_say',
            'from_client' => $userArray,
            'message_id' => $id,
            'stage' => $stage,
            'message' => $content,
            'message_type' => $type,
            'time' => $time
        );
        if ($isSocket == 1) {
            $this->pushChat($uidArray, $new_message);
        }
        return $id;
    }

    //驿站聊天放redis
    public function stage_say($id, $userArray, $stage, $content, $type, $userList)
    {
        $time = date('Y-m-d H:i:s');
        $new_message = array(
            'type' => 'stage_say',
            'from_client' => $userArray,
            'message_id' => $id,
            'stage' => $stage,
            'message' => $content,
            'message_type' => $type,
            'time' => $time
        );
        foreach ($userList as $val) {
            if ($val['message_type'] == 1) {
                $new_message['accept'] = true;
            } else {
                $new_message['accept'] = false;
            }
            $this->addStageMsg($val['uid'], $new_message);
        }
    }

    //获取最新的驿站消息
    public function getStageMessage($uid, $sid, $last_id)
    {
        $stmt = $this->db->prepare("select id,uid,content_id,add_time from chat_stage where id NOT IN
        (SELECT message_id FROM chat_stage_del WHERE uid =:uid AND sid =:sid ) and sid = :sid  and id > :last_id order by id limit 0,10");
        $array = array(
            ':sid' => $sid,
            ':uid' => $uid,
            ':last_id' => $last_id
        );
        $stmt->execute($array);
        $result = $stmt->fetchALL(PDO::FETCH_ASSOC);
        if ($result) {
            $userModel = new UserModel();
            foreach ($result as $key => $val) {
                $content = $this->getContent($val['content_id']);
                $result[$key]['type'] = $content['type'];
                $result[$key]['from_uid'] = $val['uid'];
                $result[$key]['content'] = $content['content'];
                $result[$key]['add_time'] = Common::show_time($val['add_time']);
                $result[$key]['add_unix_time'] = strtotime($val['add_time']);
                $result[$key]['user'] = $userModel->getUserData($val['uid'], $uid);
                $this->delMsg('stage', $uid, $val['id']);
            }
        }
        return $result;
    }

    //查询某个驿站的正常对话列表
    public function getStageChatList($sid, $uid)
    {
        $sql = "SELECT uid,message_type FROM chat WHERE TYPE = 2 AND STATUS = 1  AND uid!=:uid and gid=:sid ";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':uid' => $uid,
            ':sid' => $sid
        );
        $stmt->execute($array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //删除驿站消息插入中间表
    public function  delStageMsgToAdd($uid, $sid)
    {
        $sql = "INSERT INTO chat_stage_del(uid,sid,message_id)
                 SELECT $uid,sid,id FROM chat_stage WHERE sid =:sid AND id NOT IN (SELECT message_id FROM chat_stage_del WHERE uid =:uid AND sid =:sid )";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':uid' => $uid,
            ':sid' => $sid
        );
        $stmt->execute($array);
        $message_ids = $this->getDelMessageId($uid, $sid);
        foreach ($message_ids as $val) {
            $this->delMsg('stage', $uid, $val['id']);
        }
        $this->addChat($uid, $sid, 2, date('Y-m-d H:i:s', time()), 0);
        return true;
    }

    //根据sid uid 查询用户删除的message_id
    public function getDelMessageId($uid, $sid)
    {
        $sql = "select message_id as id from chat_stage_del where uid =:uid and sid=:sid";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':uid' => $uid,
            ':sid' => $sid
        );
        $stmt->execute($array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //获取某个驿站的聊天记录
    public function getLastStageMessage($sid, $uid, $last_id, $offset, $size)
    {
        if ($last_id > 0) {
            $stmt = $this->db->prepare("select id,uid as from_uid,content_id,add_time from chat_stage where id NOT IN
            (SELECT message_id FROM chat_stage_del WHERE uid =:uid AND sid =:sid ) and sid =:sid  and id < :last_id order by id desc limit :offset,:size");
            $stmt->bindValue(':last_id', $last_id, PDO::PARAM_INT);
        } else {
            $stmt = $this->db->prepare("select id,uid as from_uid,content_id,add_time from chat_stage where id NOT IN
            (SELECT message_id FROM chat_stage_del WHERE uid =:uid AND sid =:sid ) and sid =:sid order by id desc limit :offset,:size");
        }
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchALL(PDO::FETCH_ASSOC);
        if ($result) {
            $result = array_reverse($result);
            $userModel = new UserModel();
            foreach ($result as $key => $val) {
                $content = $this->getContent($val['content_id']);
                $result[$key]['type'] = $content['type'];
                $result[$key]['content'] = $content['content'];
                $result[$key]['add_time'] = Common::show_time($val['add_time']);
                $result[$key]['add_unix_time'] = strtotime($val['add_time']);
                $result[$key]['user'] = $userModel->getUserData($val['from_uid'], $uid);
            }
        }
        $message_list = $this->getMsg('stage', $uid);
        $message_list = array_map("self::json_map", $message_list);
        foreach ($message_list as $val) {
            $data = (array)$val;
            $stage_data = (array)$data['stage'];
            if ($stage_data['sid'] == $sid) {
                $this->delMsg('stage', $uid, $val['message_id']);
            }
        }
        return $result;
    }

    //根据sid,uid查询用户在该驿站还能看到哪些消息
    public function getIdBySidAndUid($sid)
    {
        $sql = "SELECT id FROM chat_stage WHERE sid =:sid";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':sid' => $sid
        );
        $stmt->execute($array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //消息屏蔽
    public function closeMessage($uid, $type, $gid, $message_type)
    {
        $sql = "update chat set message_type = :message_type where uid = :uid and type = :type and gid = :gid";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':message_type' => $message_type,
            ':uid' => $uid,
            ':type' => $type,
            ':gid' => $gid
        );
        $stmt->execute($array);
    }

    //消息是否屏蔽
    public function messageIsClose($uid, $type, $gid)
    {
        $stmt = $this->db->prepare("select message_type from chat where uid = :uid and type = :type and gid = :gid");
        $array = array(
            ':uid' => $uid,
            ':type' => $type,
            ':gid' => $gid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['message_type'];
    }

    //重置对话
    public function addChat($uid, $to_uid, $type, $time, $status = 1)
    {
        $sql = "insert into chat (uid,type,gid,message_time,status) value (:uid,:type,:gid,:message_time,:status) on duplicate key update
        message_time=:message_time,status=:status";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':uid' => $uid,
            ':type' => $type,
            ':gid' => $to_uid,
            ':message_time' => $time,
            ':status' => $status,
        );
        $stmt->execute($array);
    }

    //重置驿站对话信息
    public function updateStageChat($sid)
    {
        $sql = "update chat set status = 1,message_time=:time where gid =:sid and type = 2 and status < 2";
        $stmt = $this->db->prepare($sql);
        $time = date('Y-m-d H:i:s', time());
        $array = array(
            ':sid' => $sid,
            ':time' => $time
        );
        $stmt->execute($array);
    }

    //查询是否屏蔽
    public function getMessageType($uid, $to_uid)
    {
        $sql = "select message_type from chat where uid = :uid and gid = :gid and type = 1";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':uid' => $to_uid,
            ':gid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    //添加私聊消息
    public function addChatMsg($from_uid, $to_uid, $content_id)
    {
        $sql = "insert into chat_msg (from_uid,to_uid,content_id) value (:from_uid,:to_uid,:content_id) on duplicate key update content_id = :content_id,status=0";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':from_uid' => $from_uid,
            ':to_uid' => $to_uid,
            ':content_id' => $content_id
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        return $id;
    }

    //私信放入redis
    public function say($message_id, $userArray, $content, $type, $not_accept, $time, $to_uid, $share_type)
    {
        $new_message = array(
            'type' => 'say',
            'from_client' => $userArray,
            'message_id' => $message_id,
            'message' => $content,
            'message_type' => $type,
            'accept' => $not_accept,
            'time' => $time,
            'share_type' => $share_type
        );
        $this->addUserMsg($to_uid, $new_message);
    }

    public function addUserMsg($uid, $message)
    {
        $this->addMsg('user', $uid, $message);
    }

    public function addStageMsg($uid, $message)
    {
        $this->addMsg('stage', $uid, $message);
    }

    public function addMsg($type, $uid, $message)
    {
        $key = $this->getKey($type, $uid);
        $this->redis->hset($key, $message['message_id'], json_encode($message));
    }

    public function delMsg($type, $uid, $message_id)
    {
        $key = $this->getKey($type, $uid);
        $this->redis->hdel($key, $message_id);
    }

    public function getMsg($type, $uid)
    {
        $key = $this->getKey($type, $uid);
        $msg_list = $this->redis->hvals($key);
        return $msg_list;
    }

    public function getKey($type, $id)
    {
        switch ($type) {
            case 'user':
                $key = self::$user_msg_name . ':' . $id;
                break;
            case 'stage':
                $key = self::$stage_msg_name . ':' . $id;
                break;
            case 'chatIMuser':
                $key = self::$chatIMuser_msg_name . ':' . $id;
                break;
            case 'chatIMstage':
                $key = self::$chatIMstage_msg_name . ':' . $id;
                break;
        }
        return $key;
    }

    public function pushChat($uidArray, $messageArray)
    {
        $client = stream_socket_client(PUSH_DOMAIN);
        if (!$client) exit("can not connect");
        if ($uidArray) {
            foreach ($uidArray as $v) {
                if ($v['message_type'] == 1) {
                    $messageArray['accept'] = true;
                } else {
                    $messageArray['accept'] = false;
                }
                $message = array(
                    'type' => 'server_action',
                    'uid' => $v['uid'],
                    'message' => $messageArray
                );
                fwrite($client, json_encode($message) . "\n");
            }
        }
    }

    //查询用户屏蔽的群聊删除相关redis数据
    public function delStageMessageRemind($uid)
    {
        $sql = 'select id from chat_stage where sid in (select gid from chat where status=1 and message_type=2 and type=2 and uid=:uid)';
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $v) {
                $this->delMsg('stage', $uid, $v['id']);
            }
        }
    }

    //查询私聊删除redis数据和更新数据库数据
    public function delUserMessageRemind($uid)
    {
        $stmt = $this->db->prepare("select id from chat_msg where from_uid in (select gid from chat where message_type =2 and type=1 and uid=:uid)");
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $val) {
                $this->delMsg('user', $uid, $val['id']);
            }
        }
        $this->updateUserMessageIsRead($uid);
    }

    //把已经屏蔽的用户对话消息更新为已读
    public function updateUserMessageIsRead($uid)
    {
        $stmt_select = $this->db->prepare("select gid from chat where message_type =2 and type=1 and uid=:uid");
        $array = array(
            ':uid' => $uid
        );
        $stmt_select->execute($array);
        $result = $stmt_select->fetchAll(PDO::FETCH_ASSOC);
        $array = array();
        if ($result) {
            array_push($array, date('Y-m-d H:i:s'));
            foreach ($result as $val) {
                array_push($array, $val['gid']);
            }
            $place_holders = implode(',', array_fill(0, count($result), '?'));
            $sql = "update chat_msg set is_read = 1,update_time=? where from_uid in ($place_holders)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($array);
        }
    }

    //根据分组里的uid gid type 去相对应的表查询最新的一条content_id
    public function getContentId($from_uid, $to_uid, $type)
    {
        if ($type == 1) {
            $sql = "select id,content_id from chat_msg where (from_uid =:from_uid and to_uid=:to_uid and status < 2) or
             (from_uid =:to_uid and to_uid=:from_uid and to_status < 2)  order by id desc limit 1";
            $stmt = $this->db->prepare($sql);
            $array = array(
                ':from_uid' => $from_uid,
                ':to_uid' => $to_uid
            );
        }
        if ($type == 2) {
            $sql = "select id,content_id from chat_stage where sid =:sid and id not in(select message_id from chat_stage_del
            where uid=:uid) order by id desc limit 1";
            $stmt = $this->db->prepare($sql);
            $array = array(
                ':sid' => $from_uid,
                ':uid' => $to_uid
            );
        }
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    // 根据uid去Redis里取融云IM平台的用户token值
    public function getUidToken($uid)
    {
        if (!$uid) {
            return false;
        }
        $res = $this->getMsg('chatIMuser', $uid);
        return $res ? json_decode($res[0], true) : array();
    }

    // 将获取到融云IM平台的用户Token值存入Redis及数据表更新
    public function addUidToken($uid, $message)
    {
        if (!$uid || !$message) {
            return false;
        }
        // 存入Redis
        // $this->addMsg('chatIMuser', $uid, $message);

        // 存入更新到user表中
        $sql = "insert into user (uid,token) value (:uid,:token) on duplicate key update token=:token";
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':token' => $message['rongCloudToken'],
            ':uid' => $uid
        );
        $stmt->execute($array);
    }

    // 添加融云IM过来的消息到用户或驿站的聊天记录表
    public function addChatMsgOrStage($fromUserId, $toUserId, $contentId, $cloudId, $channelType)
    {
        if (!$fromUserId || !$toUserId || !$contentId || !$cloudId || !$channelType) {
            return false;
        }
        if ($channelType == 1) {
            $sql = "insert into chat_msg (from_uid,to_uid,content_id,cloud_id) value (:from_uid,:to_uid,:content_id,:cloud_id) on duplicate key update content_id = :content_id,status=0";
        } elseif ($channelType == 2) {
            $sql = 'insert into chat_stage (sid,uid,content_id,cloud_id) values (:to_uid,:from_uid,:content_id,:cloud_id)';
        }
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':from_uid' => $fromUserId,
            ':to_uid' => $toUserId,
            ':content_id' => $contentId,
            ':cloud_id' => $cloudId,
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        return $id;
    }

    // 添加通过融云IM过来的用户和驿站消息
    public function addUserOrStageMessage($fromUserId, $toUserId, $msgType, $content, $channelType, $cloudId)
    {
        $fromUserId = $fromUserId ? (int)$fromUserId : 0;
        $toUserId = $toUserId ? (int)$toUserId : 0;
        $msgType = $msgType ? (int)$msgType : 0; // 消息类型
        $content = $content ? $content : '';
        $channelType = $channelType ? $channelType : 1; // 会话类型
        $msgTime = date('Y-m-d H:i:s', time()); // 消息产生的时间
        $cloudId = $cloudId ? $cloudId : ''; // 融云IM产生的消息ID

        // 1、添加内容   2、添加聊天会话记录  3、重置聊天会话    4、写入redis缓存

        // 1、添加内容
        $contentId = $this->addContent($msgType, $content);
        if (!$contentId) {
            return false;
        }

        // 2、添加聊天会话记录
        $chatMsgId = $this->addChatMsgOrStage($fromUserId, $toUserId, $contentId, $cloudId, $channelType);
        if (!$chatMsgId) {
            return false;
        }

        // 3、重置聊天会话
        if ($channelType == 1) {
            $this->addChat($fromUserId, $toUserId, $channelType, $msgTime); // 重置用户聊天会话
            $this->addChat($toUserId, $fromUserId, $channelType, $msgTime);
        } elseif ($channelType == 2) {
            $this->updateStageChat($toUserId); // 重置驿站聊天会话
        }

        // 4、写入redis缓存
        /*
        $maskType = $this->getMessageType($fromUserId, $toUserId);
        $accept = $maskType['message_type'] == 1 ? true : false;
        if ($channelType == 1) {
            $this->say($chatMsgId, '', $content, $msgType, $accept, $msgTime, $toUserId, '');
        } elseif ($channelType == 2) {
            $stageModel = new StageModel();
            $data = $stageModel->getBasicStageBySid($toUserId);
            $this->updateStageChat($toUserId);
            $uidArray = $this->getStageChatList($toUserId, $fromUserId);
            $stage['sid'] = $data['sid'];
            $stage['name'] = $data['name'];
            $stage['intro'] = $data['intro'];
            $stage['icon'] = $data['icon'];
            $this->stage_say($chatMsgId, '', $stage, $content, $msgType, $uidArray);
        }
        */
    }

    // 根据融云IM历史最后一条聊天消息ID获取离线消息漫游数据
    public function getRoamMsg($fromUserId, $toUserId, $channelType, $lastContentId, $page = 1, $size = 30)
    {
        if (!$fromUserId || !$toUserId || !$channelType) {
            return false;
        }
        $page = ($page - 1) * $size;
        $time = date('Y-m-d H:i:s', time());
        if ($channelType == 1) {
            if ($lastContentId == 0) {
                $sql="SELECT c.*,m.from_uid as fromId,m.to_uid as toId,m.content_id,m.cloud_id FROM chat_content c,chat_msg m WHERE c.id=m.content_id AND m.add_time < '$time' AND m.from_uid=:fromUserId AND m.to_uid=:toUserId AND m.`STATUS` < 2 AND m.to_status < 2 AND c.id IN(SELECT content_id FROM chat_msg WHERE add_time<'$time' AND from_uid=:fromUserId AND to_uid=:toUserId AND STATUS<2 AND to_status<2) ORDER BY c.id DESC LIMIT $page,$size";
            } else {
                if ($lastContentId < 0) {
                    return false;
                }
                $sql="SELECT c.*,m.from_uid as fromId,m.to_uid as toId,m.content_id,m.cloud_id FROM chat_content c,chat_msg m WHERE c.id=m.content_id AND m.cloud_id < '$lastContentId' AND m.from_uid=:fromUserId AND m.to_uid=:toUserId AND m.`STATUS` < 2 AND m.to_status < 2 AND c.id IN(SELECT content_id FROM chat_msg WHERE cloud_id='$lastContentId' AND from_uid=:fromUserId AND to_uid=:toUserId AND STATUS<2 AND to_status<2) ORDER BY c.id DESC LIMIT $page,$size";
            }
        } elseif ($channelType == 2) {
            if ($lastContentId == 0) {
                $sql="SELECT c.*,m.uid as fromId,m.sid as toId,m.content_id,m.cloud_id FROM chat_content c,chat_stage m WHERE c.id=m.content_id AND m.add_time < '$time' AND m.uid=:fromUserId AND m.sid=:toUserId AND m.`STATUS` < 2 AND c.id IN(SELECT content_id FROM chat_stage WHERE add_time<'$time' AND uid=:fromUserId AND sid=:toUserId AND STATUS<2) ORDER BY c.id DESC LIMIT $page,$size";
            } else {
                if ($lastContentId < 0) {
                    return false;
                }
                $sql="SELECT c.*,m.uid as fromId,m.sid as toId,m.content_id,m.cloud_id FROM chat_content c,chat_stage m WHERE c.id=m.content_id AND m.cloud_id < '$lastContentId' AND m.uid=:fromUserId AND m.sid=:toUserId AND m.`STATUS` < 2 AND c.id IN(SELECT content_id FROM chat_stage WHERE cloud_id='$lastContentId' AND uid=:fromUserId AND sid=:toUserId AND STATUS<2) ORDER BY c.id DESC LIMIT $page,$size";
            }
        }
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':fromUserId' => $fromUserId,
            ':toUserId' => $toUserId,
        );
        $stmt->execute($array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // 同步现有所有正常的驿站及驿站所有成员的数据到融云IM并创建群
    public function syncStageChat()
    {
        // set_time_limit(8600);
        $sqlSid = "SELECT sid FROM stage WHERE STATUS<2 limit 50";
        $stmt = $this->db->prepare($sqlSid);
        $stmt->execute();
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $chatIM = new RongCloudIM();
        $userData = array();
        foreach ($res as $key => $value) {
            // $sqlData = 'SELECT sid,uid,(SELECT name FROM stage WHERE STATUS<2 AND sid=' . $value['sid'] . ') AS stageName FROM stage_user WHERE STATUS<2 AND sid=' . $value['sid'];
            // echo $sqlData.'<br />';
            // $sqlData = 'SELECT sid,uid,(SELECT name FROM stage WHERE STATUS<2 AND sid=10) AS stageName FROM stage_user WHERE STATUS<2 AND sid=10';
            $sqlData = 'SELECT sid,uid,(SELECT NAME FROM stage WHERE STATUS<2 AND sid=1945) AS stageName FROM stage_user WHERE STATUS<2 AND sid=1945';
            $stmt2 = $this->db->prepare($sqlData);
            $stmt2->execute();
            $uidData[$key] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            foreach ($uidData[$key] as $index => $item) {
                // $chatIM->group()->join($item['uid'], $item['sid'], $item['stageName']);
                $userData[$key][$index] = $item['uid'];
                foreach ($userData as $k => $v) {
                    // 将当前用户加入融云IM群组里
                    // $chatIM->group()->join($v, $item['sid'], $item['stageName']);
                    // sleep(5);
                    $resJion = $chatIM->group()->join($v, $item['sid'], $item['stageName']);
                    $resJion = json_decode($resJion, true);
                    if (200 != $resJion['code']) {
                        Common::echoAjaxJson(9, '用户加入融云IM群组失败');
                    }
                }
            }
            $userData = array();
        }
        /*$resQueryUser = $chatIM->group()->queryUser(10);
        $resQueryUser = json_decode($resQueryUser, true);
        echo count($resQueryUser['users']);
        exit;*/
        return $uidData;
    }

}



























