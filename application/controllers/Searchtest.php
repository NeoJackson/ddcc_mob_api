<?php

class SearchtestController extends Yaf_Controller_Abstract
{

    // 获取当前用户的历史查询关键词列表和热门关键字列表
    public function getKeywordsByTypeAction()
    {
        $parameters = array(
            'token' => 'b5020e32cfa57d10074a4dc790b4caf6',
            // 'keyword' => '你好',
            'keywordType' => '8'
        );
        Common::verify($parameters, '/search/getKeywordsByType');
    }

    // 一键清除当前用户的所有历史查询关键词记录
    public function clearHistoryKeywordAction()
    {
        $parameters = array(
            'token' => 'b5020e32cfa57d10074a4dc790b4caf6',
            'type' => 8,
        );
        Common::verify($parameters, '/search/clearHistoryKeyword');
    }

    // 搜索所有类型数据
    public function getAllListAction()
    {
        $parameters = array(
            'token' => 'f976fc55b6eaaabe139c4eca59f1ab02',
            'keyword' => '雷锋3',
        );
        Common::verify($parameters, '/search/getAllList');
    }

    // 搜索帖子数据  请传递关键词的类型 0全部 1用户 2心境 3帖子 4商品 5活动 6驿站 7用户、驿站
    public function topicAction()
    {
        $parameters = array(
            'token' => 'f976fc55b6eaaabe139c4eca59f1ab02',
            'keyword' => '雷锋5',
            'page' => '1',
            'size' => '10',
            'type' => 3,
        );
        Common::verify($parameters, '/search/topic');
    }

    // 搜索驿站数据  请传递关键词的类型 0全部 1用户 2心境 3帖子 4商品 5活动 6驿站 7用户、驿站
    public function stageAction()
    {
        $parameters = array(
            'token' => 'f976fc55b6eaaabe139c4eca59f1ab02',
            'keyword' => '何',
            'page' => '1',
            'size' => '10',
            'type' => 6,
        );
        Common::verify($parameters, '/search/stage');
    }

    // 搜索用户数据  请传递关键词的类型 0全部 1用户 2心境 3帖子 4商品 5活动 6驿站 7用户、驿站
    public function userAction()
    {
        $parameters = array(
            'token' => 'f976fc55b6eaaabe139c4eca59f1ab02',
            //'keyword'  => '534537086',
            // 'keyword'  => '何才子',
            'keyword' => '',
            'page' => '1',
            'size' => '10',
            'type' => 1,
        );
        Common::verify($parameters, '/search/user');
    }

    // 活动搜索数据  请传递关键词的类型 0全部 1用户 2心境 3帖子 4商品 5活动 6驿站 7用户、驿站
    public function eventAction()
    {
        $parameters = array(
            'token' => 'f976fc55b6eaaabe139c4eca59f1ab02',
            'keyword' => '何',
            'page' => '1',
            'size' => '10',
            'type' => 5,
        );
        Common::verify($parameters, '/search/event');
    }

    // 商品搜索数据  请传递关键词的类型 0全部 1用户 2心境 3帖子 4商品 5活动 6驿站 7用户、驿站
    public function goodsAction()
    {
        $parameters = array(
            'token' => 'f976fc55b6eaaabe139c4eca59f1ab02',
            'keyword' => '何',
            'page' => '1',
            'size' => '10',
            'type' => 4,
        );
        Common::verify($parameters, '/search/goods');
    }

    // 分享联盟搜索全部数据接口
    public function getAllShareAllianceAction()
    {
        $parameters = array(
            'token' => 'b5020e32cfa57d10074a4dc790b4caf6',
            'keyword' => '点夺柘城村柘城村柘城',
            'keywordType' => 8,
        );
        Common::verify($parameters, '/search/getAllShareAlliance');
    }

    // 分享联盟搜索商品数据接口
    public function getGoodsShareAllicanceAction()
    {
        $parameters = array(
            'token' => 'b5020e32cfa57d10074a4dc790b4caf6',
            'keywordType' => 9,
            'keyword' => '数据库添加关键词哈哈俣',
            'cateId' => 1,
            'sortId' => 0,
            'page' => 1,
        );
        Common::verify($parameters, '/search/getGoodsShareAllicance');
    }

    // 分享联盟搜索活动数据接口
    public function getEventShareAllicanceAction()
    {
        $parameters = array(
            'token' => 'b5020e32cfa57d10074a4dc790b4caf6',
            'keywordType' => 10,
            'keyword' => '回家在枯村要',
            'typeId' => '',
            'sortId' => 1,
            'page' => 1,
        );
        Common::verify($parameters, '/search/getEventShareAllicance');
    }

}
