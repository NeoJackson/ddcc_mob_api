<?php
$search = new PDO('mysql:host=127.0.0.1;port=9306', 'root', '');
$db = new PDO('mysql:host=192.168.2.111;dbname=ddcc_sns_dev','daidaisns','1cT13Q6W7sc0U9He');

$index_time = '2010-11-07 00:00:00';

//从索引记录表获取上次索引的时间
$sql = "select index_time from search_index where server_id=1 and type=2 order by id desc limit 1";
$stmt = $db->query($sql);
$rs = $stmt->fetch(PDO::FETCH_ASSOC);
if($rs){
    $index_time = $rs['index_time'];
}

//插入新的索引记录
$sql = "insert into search_index(status,server_id,type) values (1,1,2) on duplicate key update status = 1,index_time='".date('Y-m-d H:i:s')."'";
$db->exec($sql);

//查询服务信息
$sql = "select id,title,is_commission,type as cate_id,min_commission as commission,orders_statistics,orders_commission_statistics,status,commission_time,end_time from event
where update_time > '".$index_time."'";
$stmt = $db->query($sql);
$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
updateIndex($search,$rs,1);

//查询商品信息
$sql = "select id,name as title,is_commission,cate_id,min_commission as commission,orders_statistics,orders_commission_statistics,status,commission_time,end_time from
stage_goods where update_time > '".$index_time."'";
$stmt = $db->query($sql);
$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
if($rs){
    foreach($rs as $key=>$val){
        $sql = "select pid from stage_goods_cate where id=".$val['cate_id'];
        $stmt = $db->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $rs[$key]['cate_id'] = $result['pid'];
    }
}
updateIndex($search,$rs,2);

//更新索引
function updateIndex(&$search,$list,$type){
    if($list){
        foreach($list as $val){
            $data = getDataByType($type,$val);
            if(!$data){
                continue;
            }
            //更新服务、商品索引
            $sql = "delete from league where type = ".$type." and obj_id = ".$data['obj_id'];
            $search->exec($sql);
            if($val['is_commission']==1 && $val['status']<2 &&strtotime($val['end_time'])>time()){
                $sql = "insert into league(id,type,obj_id,`status`,is_del,is_commission,title,cate_id,commission,orders_statistics,orders_commission_statistics,commission_time) values
            (".$data['id'].",".$type.",".$data['obj_id'].",".$data['status'].",0,".$data['is_commission'].",'".strip_tags($data['title'])."',
            ".$data['cate_id'].",".$data['commission'].",".$data['orders_statistics'].",".$data['orders_commission_statistics'].",".$data['commission_time'].")";
                $search->exec($sql);
            }
        }
    }
}

function getDataByType($type,$rs){
    $data = array();
    $data['type'] = $type;
    $data['obj_id'] = $rs['id'];
    $data['id'] = $data['type'] . $data['obj_id'];
    $data['title'] = $rs['title'];
    $data['status'] = $rs['status'];
    $data['is_commission'] = $rs['is_commission'];
    $data['cate_id'] = $rs['cate_id'];
    $data['commission'] = $rs['commission'] ? $rs['commission'] : 0;
    $data['orders_statistics'] = $rs['orders_statistics'];
    $data['orders_commission_statistics'] = $rs['orders_commission_statistics'];
    $data['commission_time'] = strtotime($rs['commission_time']);
    $data['end_time'] = strtotime($rs['end_time']);//商品用来判断
    return $data;
}
