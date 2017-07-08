<?php
/**
 * @name TagModel
 * @desc Tag数据获取类, 可以访问数据库，文件，其它系统等
 * @author {&$AUTHOR&}
 */
class TagModel {
    private $db;
    private $type_arr = array(1,2,3,4,5,6,7);
    public function __construct() {
        $this->db = DB::getInstance();
    }

    private  function getTableByType($type){
        if(!$type || !in_array($type,$this->type_arr)){
            return false;
        }
        switch($type){
            case 1:
                $table = 'user_tag';
                $field = 'uid';
                break;
            case 2:
                $table = 'stage_tag';
                $field = 'sid';
                break;
            case 3:
                $table = 'blog_tag';
                $field = 'bid';
                break;
            case 4:
                $table = 'topic_tag';
                $field = 'tid';
                break;
            case 5:
                $table = 'info_tag';
                $field = 'iid';
                break;
            case 6:
                $table = 'reward_user_tag';
                $field = 'uid';
                break;
            case 7:
                $table = 'reward_ask_tag';
                $field = 'ask_id';
                break;
        }
        return array($table,$field);
    }

    /**
     * 根据标签分类id,标签内容查询id
     */
    public function getTagByContent($cate_id,$content){
        $stmt = $this->db->prepare("select id from tag where cate_id = :cate_id and content = :content and status = 1");
        $array = array(
            ':cate_id' => $cate_id,
            ':content' => $content
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 根据标签主键id查询标签信息
     */
    public function getTagById($id){
        $stmt = $this->db->prepare("select id,content,cate_id from tag where id = :id and status = 1");
        $array = array(
            ':id' => $id
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 根据类型，代代文化分类id查询标签分类id
     */
    public function getCateIdByType($type,$type_cate){

        $stmt_s = $this->db->prepare("select id from tag_cate where type = :type and type_cate = :type_cate and status = 1");
        $array = array(
            ':type' => $type,
            ':type_cate' => $type_cate
        );
        $stmt_s->execute($array);
        $result = $stmt_s->fetch(PDO::FETCH_ASSOC);
        return $result['id'];
    }

    /**
     * 根据cate_id查询标签列表
     */
    public function getListByCateId($cate_id,$is_recommend){
        $stmt_s = $this->db->prepare("select id,content from tag where cate_id = :cate_id and is_recommend = :is_recommend and status = 1 order by sort");
        $array = array(
            ':cate_id' => $cate_id,
            ':is_recommend' => $is_recommend,
        );
        $stmt_s->execute($array);
        $result = $stmt_s->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 公用标签添加
     * content 标签内容  type 标签类型  type_cate 代代文化分类id
     */
    public function addTag($content,$type,$type_cate){
        $cate_id = $this->getCateIdByType($type,$type_cate);
        if(!$cate_id){
            return 0;
        }
        $result = $this->getTagByContent($cate_id,$content);
        if($result){
            return $result['id'];
        }
        $stmt_add = $this->db->prepare("insert into tag (cate_id,content,add_time)values (:cate_id,:content,:add_time) on duplicate key update status = 1");
        $array_add = array(
            ':cate_id' => $cate_id,
            ':content' => $content,
            ':add_time' => date('Y-m-d H:i:s')
        );
        $stmt_add->execute($array_add);
        return $this->db->lastInsertId();
    }

    /**
     * 保存标签关联关系
     * type 标签类型  relation_id 关系标签id  tag_arr 标签数组
     */
    public function saveRelation($type,$relation_id,$tag_arr){
        list($table,$field) = $this->getTableByType($type);
        $old_bind=$this->getCountTag($table,$field,$relation_id);
        if(isset($old_bind['num'])&&$old_bind['num']){
            if($tag_arr){
                $bind_count=count($tag_arr);//新修改是传过来的行数
                if($bind_count > $old_bind['num']){//当修改的大于原来的条数时
                    foreach($tag_arr as $key =>$val){
                        if($key < $old_bind['num']){
                            $stmt = $this->db->prepare("update $table set tag_id=:tag_id,status=:status,add_time=:add_time
                                                            where  $field=:field and id=:id ");
                            $array = array(
                                ':id' =>$old_bind[$key]['id'],
                                ":field" => $relation_id,
                                ':tag_id' => $val,
                                ':status' => '1',
                                ':add_time' => date('Y-m-d H:i:s'),
                            );
                            $stmt->execute($array);
                        }else{
                            $stmt_add = $this->db->prepare("insert into $table ($field,tag_id,add_time) values (:field,:tag_id,:add_time)");
                            $array = array(
                                ":field" => $relation_id,
                                ':tag_id' => $val,
                                ':add_time' => date('Y-m-d H:i:s')
                            );
                            $stmt_add->execute($array);
                        }
                    }
                }else{//当修改的小于或等于原来的条数时
                    foreach($tag_arr as $key =>$val){
                        $stmt = $this->db->prepare("update $table set tag_id=:tag_id,status=:status,add_time=:add_time
                                                            where  $field=:field and id=:id ");
                        $array = array(
                            ':id' =>$old_bind[$key]['id'],
                            ":field" => $relation_id,
                            ':tag_id' => $val,
                            ':status' => '1',
                            ':add_time' => date('Y-m-d H:i:s'),
                        );
                        $stmt->execute($array);
                    }
                    if($bind_count < $old_bind['num']){
                        $stmt = $this->db->prepare("update $table set status=:status,add_time=:add_time
                                                            where  $field=:field and id >:ids");
                        $array = array(
                            ":field" => $relation_id,
                            ':ids' =>$old_bind[$bind_count-1]['id'],
                            ':status' => '0',
                            ':add_time' => date('Y-m-d H:i:s'),
                        );
                        $stmt->execute($array);
                    }
                }
            }
        }else{
            foreach($tag_arr as $value){
                if($value){
                    $stmt_add = $this->db->prepare("insert into $table ($field,tag_id,add_time) values (:field,:tag_id,:add_time)");
                    $array = array(
                        ":field" => $relation_id,
                        ':tag_id' => $value,
                        ':add_time' => date('Y-m-d H:i:s')
                    );
                    $stmt_add->execute($array);
                }
            }
        }
        return 1;
    }
    //获取报名显示列表
    public function getCountTag($table,$field,$relation_id){
        $stmt = $this->db->prepare("select id from $table where $field=:field  order by id asc");
        $array = array(
            ':field' => $relation_id
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $result['num']=count($result);
        }
        return $result;

    }
    /**
     * 删除标签关联关系
     */
    public function delRelation($type,$id){
        list($table,) = $this->getTableByType($type);
        $array = array(
            ':id' => $id,
        );
        $stmt = $this->db->prepare("update $table set status = 0 where id = :id");
        $stmt->execute($array);
        $rs1 = $stmt->rowCount();
        if($rs1 < 1){
            return -1;
        }
        return 1;
    }
    /*
     * 删除该驿站的所有标签
     */
    public function deleteStageTagBySid($sid){
        $array = array(
            ':sid' => $sid,
        );
        $stmt = $this->db->prepare("update stage_tag set status = 0 where sid = :sid");
        $stmt->execute($array);
        $rs1 = $stmt->rowCount();
        if($rs1 < 1){
            return -1;
        }
        return 1;
    }
    /*
     * 选择官方推荐的标签列表
     */
    public function listTag($type,$type_cate=0){
        $cate_id = $this->getCateIdByType($type,$type_cate);
        $result = $this->getListByCateId($cate_id,1);
        return $result;
    }
    /*
     * 选择官方推荐的标签列表
     */
    public function getListByUserPush($type,$type_cate=0){
        $cate_id = $this->getCateIdByType($type,$type_cate);
        $stmt_s = $this->db->prepare("select id,content from tag where cate_id = :cate_id and push_type = :push_type and status = 1 order by sort");
        $array = array(
            ':cate_id' => $cate_id,
            ':push_type' => 1,
        );
        $stmt_s->execute($array);
        $result = $stmt_s->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    //选择官方推荐的标签列表只查询两个字的
    public function getRecommendTagList($type,$type_cate=0){
        $cate_id = $this->getCateIdByType($type,$type_cate);
        $stmt_s = $this->db->prepare("select id,content from tag where cate_id = :cate_id and is_recommend = 1 and status = 1
        and char_length(content)=2 order by sort");
        $array = array(
            ':cate_id' => $cate_id
        );
        $stmt_s->execute($array);
        $result = $stmt_s->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    /*
     * 用户我的标签
     */
    public function getRelation($type,$id){
        list($table,$field) = $this->getTableByType($type);
        $stmt = $this->db->prepare("SELECT ut.tag_id,t.content,t.cate_id  FROM $table AS ut
                                    LEFT JOIN tag AS t
                                    ON ut.tag_id = t.id
                                    WHERE ut.$field = :$field AND ut.status = 1 AND t.status = 1 ORDER BY ut.id asc");
        $array = array(
            ":$field" => $id,
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    //获取推荐到发现的标签
    public function getAppStageTag(){
        $stmt = $this->db->prepare("select tag_id from app_stage_tag where sid = 0 and status = 1 and tag_id in (select id from tag where status = 1) order by add_time desc limit 8");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        if($result){
          foreach($result as $k=>$v){
              $list[$k] = $this->getTagById($v['tag_id']);
          }
        }
        return $list;
    }
}