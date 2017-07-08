<?php

/**
 * @name AdModel
 * @desc Ad数据获取类, 可以访问数据库，文件，其它系统等
 * @author {&$AUTHOR&}
 */
class KeywordModel
{
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    public function getLast($uid)
    {
        $stmt = $this->db->prepare("select id,word from search_keyword where uid=:uid and status=1 order by update_time desc limit 0,10");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add($uid, $type, $word)
    {
        $stmt = $this->db->prepare("insert into search_keyword (uid,type,word) values (:uid,:type,:word) on duplicate key update count = count+1,update_time=:update_time");
        $array = array(
            ':uid' => $uid,
            ':type' => $type,
            ':word' => $word,
            ':update_time' => date("Y-m-d H:i:s"),
        );
        $stmt->execute($array);
        return true;
    }

    public function getHot()
    {
        $stmt = $this->db->prepare("SELECT t.content as word FROM tag AS t LEFT JOIN tag_cate AS tc
                                    ON t.cate_id = tc.id
                                    WHERE tc.type = 7 AND tc.status = 1 AND t.status = 1 limit 0,10");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //清除某个用户的历史搜索
    public function delHistory($uid, $id)
    {
        $fields = $id ? 'id=' . $id : 'uid=' . $uid;
        $stmt = $this->db->prepare("update search_keyword set status = 2 where $fields ");
        $stmt->execute();
        $rs = $stmt->rowCount();
        return $rs;
    }

    // 根据当前用户$uid获取当前用户历史查询过的关键词列表
    public function getHistoryKeywords($uid, $type)
    {
        $stmt = $this->db->prepare('select id,type,word from search_history_keyword where uid=:uid and type=:type and status=1 order by add_time desc limit 0,10');
        $array = array(
            ':uid' => $uid,
            ':type' => $type,
        );
        $stmt->execute($array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 获取当前类型的热门搜索关键词列表
    public function getSearchHotKeyword($type)
    {
        $stmt = $this->db->prepare('select type,word from search_hot_keyword where status=1 and type=:type order by sort asc limit 0,10');
        $array = array(
            ':type' => $type,
        );
        $stmt->execute($array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // 根据当前用户$uid清除当前用户的所有历史搜索
    public function clearHistoryKeyword($uid, $type)
    {
        $stmt = $this->db->prepare('update search_history_keyword set status = 0,update_time=:update_time where uid=:uid and type=:type');
        $array = array(
            ':uid' => $uid,
            ':type' => $type,
            ':update_time' => date('Y-m-d H:i:s', time()),
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        return $rs;
    }


    // 当前用户点击搜索时添加相应类型的关键词到历史关键词表中
    public function addHistoryKeyword($uid, $type, $keyword)
    {
        $stmt = $this->db->prepare('insert into search_history_keyword (uid,type,word) values(:uid,:type,:word) on duplicate key update self_count=self_count+1,status=1,add_time=:add_time');
        $array = array(
            ':uid' => $uid,
            ':type' => $type,
            ':word' => $keyword,
            ':add_time' => date('Y-m-d H:i:s', time()),
        );
        $stmt->execute($array);
        return true;
    }


}
