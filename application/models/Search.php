<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 14-8-12
 * Time: 下午4:26
 */
class SearchModel{
    private $index = 'ddcc';
    private $user_index = 'user';
    private $league_index = 'league';

    //获取数据 1心境 3日志 4帖子 7服务 8商品
    public function getContent($type,$keyword,$offset,$limit,$uid){
        if(!$keyword){
            return array(
                'size' => 0,
                'list'=> array(),
                'total_found' => 0,
            );
        }
        //添加到关键词表
        if($uid){
            $keywordModel = new KeywordModel();
            $keywordModel->add($uid,$type,$keyword);
        }
        $sphinx = new SphinxClient();
        $config = Yaf_Registry::get("config");
        $host = $config->search->index->host;
        $port = $config->search->index->port;
        $sphinx->SetServer($host, $port);
        $sphinx->SetConnectTimeout(1);
        $sphinx->SetArrayResult(true);
        $type_arr = array();
        if($type == 1){//心境-暂无此需求
            $type_arr = array(1);
            $sphinx->SetFilter('status',array(0,1));
        }elseif($type == 3){//日志-暂无此需求
            $type_arr = array(3);
            $sphinx->SetFilter('status',array(0,1));
        }elseif($type == 4){//帖子
            $type_arr = array(4);
            $sphinx->SetFilter('status',array(0,1));
        }elseif($type == 7){//服务
            $type_arr = array(7);
            $sphinx->SetFilter('status',array(0,1));
        }elseif($type == 8){//商品
            $type_arr = array(8);
            $sphinx->SetFilter('status',array(0,1));
        }
        $sphinx->SetFilter('is_public',array(1));
        $sphinx->SetFilter('is_del',array(0));
        $sphinx->SetFilter('type',$type_arr);
        $sphinx->SetLimits($offset,$limit);
        $sphinx->SetSortMode(SPH_SORT_EXPR,"@weight");
        $sphinx->SetFieldWeights(array(
            'title'=>10,
            'content'=>1
        ));
        $res = $sphinx->Query($keyword, $this->index);
        $size = $total_found = 0;
        $list = array();
        if($res && $res['total'] > 0){
            $size = $res['total'];
            $list = isset($res['matches'])?$res['matches']:array();
            $total_found = $res['total_found'];
        }
        return array(
            'size' => $size,
            'list'=> $list,
            'total_found' => $total_found,
        );
    }
    //获取数据  5驿站 6用户
    public function getUserAndStage($type,$keyword,$offset,$limit,$uid){
        if(!$keyword){
            return array(
                'size' => 0,
                'list'=> array(),
                'total_found' => 0,
            );
        }
        //添加到关键词表
        if($uid){
            $keywordModel = new KeywordModel();
            $keywordModel->add($uid,$type,$keyword);
        }
        $sphinx = new SphinxClient();
        $config = Yaf_Registry::get("config");
        $host = $config->search->user->host;
        $port = $config->search->user->port;
        $sphinx->SetServer($host, $port);
        $sphinx->SetConnectTimeout(1);
        $sphinx->SetArrayResult(true);
        $type_arr = array();
        if($type == 5){//驿站
            $type_arr = array(5);
            $sphinx->SetFilter('status',array(1));
        }elseif($type == 6){//用户
            $type_arr = array(6);
            $sphinx->SetFilter('status',array(0,1));
        }
        $sphinx->SetFilter('type',$type_arr);
        $sphinx->SetLimits($offset,$limit);
        $sphinx->SetSortMode(SPH_SORT_EXPR,"@weight");
        $res = $sphinx->Query($keyword, $this->user_index);
        $size = $total_found = 0;
        $list = array();
        if($res && $res['total'] > 0){
            $size = $res['total'];
            $list = isset($res['matches'])?$res['matches']:array();
            $total_found = $res['total_found'];
        }
        return array(
            'size' => $size,
            'list'=> $list,
            'total_found' => $total_found,
        );
    }

    //获取推广联盟搜索数据  1服务 2商品 $order 排序类型  0默认空按照权重排序  1、综合排序（按设置佣金时间排序） 2、佣金金额由高到低排序  3、30天引入订单金额由高到低  4、30天支出奖金金额由高到低
    //分类筛选 $cate_id 0默认代表全部 服务分类：1活动 3培训 8演出  商品分类（数据库动态存储）
    public function getLeague($type,$order,$cate_id,$keyword,$offset,$limit,$uid){
        if(!$keyword){
            return array(
                'size' => 0,
                'list'=> array(),
                'total_found' => 0,
            );
        }
        //添加到关键词表
        if($uid){
            $keywordModel = new KeywordModel();
            $keywordModel->add($uid,$type,$keyword);
        }
        $sphinx = new SphinxClient();
        $config = Yaf_Registry::get("config");
        $host = $config->search->index->host;
        $port = $config->search->index->port;
        $sphinx->SetServer($host, $port);
        $sphinx->SetConnectTimeout(1);
        $sphinx->SetArrayResult(true);
        $type_arr = array();
        if($type == 1){//服务
            $type_arr = array(1);
            $sphinx->SetFilter('status',array(0,1));
        }elseif($type == 2){//商品
            $type_arr = array(2);
            $sphinx->SetFilter('status',array(0,1));
        }
        $sphinx->SetFilter('type',$type_arr);
        if($cate_id){
            $sphinx->SetFilter('cate_id',array($cate_id));
        }
        $sphinx->SetLimits($offset,$limit);
        if($order==1){
            $sphinx->SetSortMode(SPH_SORT_EXTENDED,"commission_time desc,obj_id desc");//综合排序（按设置佣金时间排序）
        }elseif($order==2){
            $sphinx->SetSortMode(SPH_SORT_EXTENDED,"commission desc,obj_id desc");//佣金金额由高到低排序
        }elseif($order==3){
            $sphinx->SetSortMode(SPH_SORT_EXTENDED,"orders_statistics desc,obj_id desc");//30天引入订单金额由高到低
        }elseif($order==4){
            $sphinx->SetSortMode(SPH_SORT_EXTENDED,"orders_commission_statistics desc,obj_id desc");//30天支出奖金金额由高到低
        }else{
            $sphinx->SetSortMode(SPH_SORT_EXPR,"@weight");//默认按照权重排序
        }
        $res = $sphinx->Query($keyword, $this->league_index);
        $size = $total_found = 0;
        $list = array();
        if($res && $res['total'] > 0){
            $size = $res['total'];
            $list = isset($res['matches'])?$res['matches']:array();
            $total_found = $res['total_found'];
        }
        return array(
            'size' => $size,
            'list'=> $list,
            'total_found' => $total_found,
        );
    }
}