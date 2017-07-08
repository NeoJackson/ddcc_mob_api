<?php
$search = new PDO('mysql:host=127.0.0.1;port=9306', 'root', '');
$user_search = new PDO('mysql:host=127.0.0.1;port=9307', 'root', '');
$db = new PDO('mysql:host=192.168.2.111;dbname=ddcc_sns_dev','daidaisns','1cT13Q6W7sc0U9He');

$index_time = '2010-11-07 00:00:00';

//从索引记录表获取上次索引的时间
$sql = "select index_time from search_index where server_id=1 and type=1 order by id desc limit 1";
$stmt = $db->query($sql);
$rs = $stmt->fetch(PDO::FETCH_ASSOC);
if($rs){
    $index_time = $rs['index_time'];
}

//插入新的索引记录
$sql = "insert into search_index(status,server_id,type) values (1,1,1) on duplicate key update status = 1,index_time='".date('Y-m-d H:i:s')."'";
$db->exec($sql);

//查询心境
$sql = "select id,content,is_public,status from mood where update_time > '".$index_time."'";
$stmt = $db->query($sql);
$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
updateIndex($search,$user_search,$rs,1);

//查询日志
$sql = "select id,title,content,is_public,status from blog where update_time > '".$index_time."'";
$stmt = $db->query($sql);
$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
updateIndex($search,$user_search,$rs,3);

//查询帖子
$sql = "select id,title,content,status from topic where update_time > '".$index_time."'";
$stmt = $db->query($sql);
$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
updateIndex($search,$user_search,$rs,4);

//查询驿站
$sql = "select sid as id,name as title,status from stage where update_time > '".$index_time."'";
$stmt = $db->query($sql);
$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
updateIndex($search,$user_search,$rs,5);

//查询用户
$sql = "select uid as id,nick_name as title,status from user where update_time > '".$index_time."'";
$stmt = $db->query($sql);
$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
updateIndex($search,$user_search,$rs,6);

//查询服务信息
$sql = "select id,title,content,status from event where update_time > '".$index_time."'";
$stmt = $db->query($sql);
$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
updateIndex($search,$user_search,$rs,7);

//查询商品信息
$sql = "select id,name as title,intro as content,status,end_time from stage_goods where update_time > '".$index_time."'";
$stmt = $db->query($sql);
$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
updateIndex($search,$user_search,$rs,8);

//更新索引
function updateIndex(&$search,&$user_search,$list,$type){
    if($list){
        foreach($list as $val){
            $data = getDataByType($type,$val);
            if(!$data){
                continue;
            }
            //更新用户、驿站索引
            if(in_array($type,array(5,6))){
                $sql = "delete from user where type = ".$type." and obj_id = ".$data['obj_id'];
                $user_search->exec($sql);
                if(($type==5&&$val['status']==1)||($type==6&&$val['status']<2)){
                    $sql = "insert into user(id,type,obj_id,`status`,name) values (".$data['id'].",".$type.",".$data['obj_id'].",".$data['status'].",'".strip_tags($data['title'])."')";
                    $user_search->exec($sql);
                }
            }else{
                $sql = "delete from ddcc where type = ".$type." and obj_id = ".$data['obj_id'];
                $search->exec($sql);
                if(($type==1&&$val['status']<2&&$val['is_public']==2)||($type==3&&$val['status']<2&&$val['is_public']==1)
                    ||(in_array($type,array(4,7))&&$val['status']<2)||($type==8&&$val['status']<2&&strtotime($val['end_time'])>time())){
                    $sql = "insert into ddcc(id,type,obj_id,`status`,is_del,is_public,title,content) values (".$data['id'].",".$type.",".$data['obj_id'].",".$data['status'].",0,".$data['is_public'].",'".strip_tags($data['title'])."','".strip_tags($data['content'])."')";
                    $search->exec($sql);
                }
            }
        }
    }
}

function getDataByType($type,$rs){
    $data = array();
    $data['type'] = $type;
    $data['obj_id'] = $rs['id'];
    $data['id'] = $data['type'] . $data['obj_id'];
    switch ($type) {
        case 1:
            $data['title'] = '';
            $data['content'] = $rs['content'];
            $data['status'] = $rs['status'];
            $data['is_public'] = $rs['is_public'];
            break;
        case 3:
            $data['title'] = $rs['title'];
            $data['content'] = $rs['content'];
            $data['status'] = $rs['status'];
            $data['is_public'] = $rs['is_public'];
            break;
        case 4:
            $data['title'] = $rs['title'];
            $data['content'] = $rs['content'];
            $data['status'] = $rs['status'];
            $data['is_public'] = 1;
            break;
        case 5:
            $data['title'] = $rs['title'];
            $data['status'] = $rs['status'];
            break;
        case 6:
            $data['title'] = $rs['title'];
            $data['status'] = $rs['status'];
            break;
        case 7:
            $data['title'] = $rs['title'];
            $data['content'] = $rs['content'];
            $data['status'] = $rs['status'];
            $data['is_public'] = 1;
            break;
        case 8:
            $data['title'] = $rs['title'];
            $data['content'] = $rs['content'];
            $data['status'] = $rs['status'];
            $data['is_public'] = 1;
            $data['end_time'] = $rs['end_time'];//判断使用
            break;
    }
    return $data;
}
