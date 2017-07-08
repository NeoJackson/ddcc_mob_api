<?php

class SearchController extends Yaf_Controller_Abstract
{
    protected $_user = '';
    protected $_token = '';
    protected $_version = '';
    protected $startTime = '';
    private $type_arr = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10);// 搜索类型 0全部 1用户（暂无用） 2心境（暂无用） 3帖子（暂无用） 4商品 5活动 6驿站（暂无用） 7用户、驿站  8全部(分享联盟) 9商品(分享联盟) 10活动(分享联盟)

    public function init()
    {
        $this->startTime = microtime(true);
    }

    // 检查获取用户登入状态及版本号信息
    protected function checkLoginVersion()
    {
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') : APP_VERSION;
        $this->_version = $version;
        $token = $this->getRequest()->getPost('token');//用户登录token
        $uid = 0;
        if ($token) {
            $user = Common::isLogin($_POST);
            if (!$user) {
                Common::echoAjaxJson(0, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $this->_user = $uid;
        $this->_token = $token;
    }

    // 获取当前用户的历史查询关键词列表和热门关键字列表
    public function getKeywordsByTypeAction()
    {
        $this->checkLoginVersion();
        if ($this->_version < 3.8) {
            $keywordType = (int)$this->getRequest()->getPost('type');
        } else {
            $keywordType = (int)$this->getRequest()->getPost('keywordType');
        }
        if (!in_array($keywordType, $this->type_arr)) {
            Common::echoAjaxJson(2, '类型不正确');
        }
        $keyword = trim($this->getRequest()->getPost('keyword'));
        $keyword = Common::msubstr($keyword, 0, 30, 'utf-8', false);
        $keywordModel = new KeywordModel();
        if (!empty($this->_token) && !empty($keyword)) {
            $keywordModel->addHistoryKeyword($this->_user, $keywordType, $keyword);
        }
        $list = array();
        $list['historyKeywords'] = $keywordModel->getHistoryKeywords($this->_user, $keywordType);
        if (!in_array($keywordType, array(8, 9, 10))) {  // 关键词类型 8全部(分享联盟) 9商品(分享联盟) 10活动(分享联盟) 不需要有热门关键词记录
            $list['hotKeywords'] = $keywordModel->getSearchHotKeyword($keywordType);
        }

        Common::appLog('search/getKeywordsByType', $this->startTime, $this->_version);
        Common::echoAjaxJson(1, '获取成功', $list ? $list : (object)array());
    }

    // 一键清除当前用户的所有历史查询关键词记录
    public function clearHistoryKeywordAction()
    {
        $this->checkLoginVersion();
        $type = $this->getRequest()->getPost('type');
        if (!in_array($type, $this->type_arr)) {
            Common::echoAjaxJson(2, '类型不正确');
        }
        $keywordModel = new KeywordModel();
        $rs = $keywordModel->clearHistoryKeyword($this->_user, $type);
        if (!$rs) {
            Common::echoAjaxJson(3, "清除失败");
        }
        Common::appLog('search/getHistoryKeyword', $this->startTime, $this->_version);
        Common::echoAjaxJson(1, "清除成功");
    }

    // 以下是获取以某个类型关键词的数据方法
    // $list['topic'] = $list['stage'] = $list['user'] = $list['event'] = $list['goods']
    // @搜索所有类型数据
    public function getAllListAction()
    {
        $this->checkLoginVersion();
        $keyword = trim($this->getRequest()->getPost('keyword'));
        $keyword = Common::msubstr($keyword, 0, 30, 'utf-8', false);
        if ($keyword == '') {
            Common::echoAjaxJson(2, "请输入关键词");
        }
        $type = $this->getRequest()->getPost('type') ? (int)$this->getRequest()->getPost('type') : 0;
        $keywordModel = new KeywordModel();
        if (!empty($this->_token) && !empty($keyword)) {
            $keywordModel->addHistoryKeyword($this->_user, $type, $keyword);
        }
        $list['topic'] = $list['stage'] = $list['user'] = $list['event'] = $list['goods'] = array();
        $searchModel = new SearchModel();

        // 获得帖子相关数据 type=4代表帖子
        $res_topic = $searchModel->getContent(4, $keyword, 0, 3, $this->_user);
        if ($res_topic['size'] > 0) {
            $topicModel = new TopicModel();
            foreach ($res_topic['list'] as $k => $val) {
                $topicInfo = $topicModel->getBasicTopicById($val['attrs']['obj_id']);
                if ($topicInfo) {
                    $topic['id'] = $topicInfo['id'];
                    $topic['title'] = $topicInfo['title'];
                    $topic['summary'] = $topicInfo['summary'] ? $topicInfo['summary'] : '点击查看帖子详情';
                    $topic['url'] = I_DOMAIN . '/t/' . $topicInfo['id'] . '?token=' . $this->_token . '&version=' . $this->_version;
                    if ($topicInfo['img_src']) {
                        $topic['cover'] = Common::show_img($topicInfo['img_src'][0], 4, 400, 400);
                    } else {
                        $topic['cover'] = '';
                    }
                    $list['topic'][$k] = $topic;
                }
            }
        }

        // 获得驿站相关数据 type=5代表驿站
        $res_stage = $searchModel->getUserAndStage(5, $keyword, 0, 3, $this->_user);
        if ($res_stage['size'] > 0) {
            $stageModel = new StageModel();
            foreach ($res_stage['list'] as $k => $val) {
                $stageInfo = $stageModel->getBasicStageBySid($val['attrs']['obj_id']);
                if ($stageInfo) {
                    $stage['sid'] = $stageInfo['sid'];
                    $stage['sid'] = $stageInfo['sid'];
                    $stage['name'] = $stageInfo['name'];
                    $stage['type'] = $stageInfo['type'];
                    $stage['intro'] = $stageInfo['intro'];
                    $stage['user_num'] = $stageInfo['user_num'];
                    $stage['topic_num'] = $stageInfo['topic_num'];
                    $stage['status'] = $stageInfo['status'];
                    $stage['icon'] = Common::show_img($stageInfo['icon'], 4, 400, 400);
                    $list['stage'][$k] = $stage;
                }
            }
        }

        // 获得用户相关数据 type=6代表用户
        $userModel = new UserModel();
        if (preg_match('/^[1-9]\d*$/', $keyword)) {
            $userUid = $userModel->getUserByDid($keyword);
            if ($userUid) {
                $userInfo = $userModel->getUserData($userUid['uid'], $this->_user);
                if (in_array($userInfo['type'], array(2, 3, 4, 5))) {
                    $angelInfo = $userModel->getInfo($userUid['uid']);
                    $list['user'][0]['intro'] = $angelInfo['info'];
                } else {
                    $list['user'][0]['intro'] = $userInfo['intro'];
                }
                $list['user'][0]['uid'] = $userInfo['uid'];
                $list['user'][0]['did'] = $userInfo['did'];
                $list['user'][0]['nick_name'] = $userInfo['nick_name'];
                $list['user'][0]['type'] = $userInfo['type'];
                $list['user'][0]['avatar'] = Common::show_img($userInfo['avatar'], 1, 400, 400);
                $list['user'][0]['att_num'] = $userInfo['att_num'];
                $list['user'][0]['fans_num'] = $userInfo['fans_num'];
            }
        }
        $res_user = $searchModel->getUserAndStage(6, $keyword, 0, 3, $this->_user);
        $userList = array();
        if ($res_user['size'] > 0) {
            foreach ($res_user['list'] as $val) {
                $userInfo = $userModel->getUserData($val['attrs']['obj_id'], $this->_user);
                if ($userInfo != (object)array()) {
                    $userList[] = $userInfo;
                }
            }
            foreach ($userList as $k => $v) {
                $list['user'][$k]['uid'] = $v['uid'];
                $list['user'][$k]['did'] = $v['did'];
                $list['user'][$k]['nick_name'] = $v['nick_name'];
                $list['user'][$k]['type'] = $v['type'];
                if (in_array($v['type'], array(2, 3, 4, 5))) {
                    $angelInfo = $userModel->getInfo($v['uid']);
                    $list['user'][$k]['intro'] = $angelInfo['info'];
                } else {
                    $list['user'][$k]['intro'] = $v['intro'];
                }
                $list['user'][$k]['avatar'] = Common::show_img($v['avatar'], 1, 400, 400);
                $list['user'][$k]['att_num'] = $v['att_num'];
                $list['user'][$k]['fans_num'] = $v['fans_num'];
            }
        }

        // 获得服务相关数据 type=7代表服务
        $res_event = $searchModel->getContent(7, $keyword, 0, 3, $this->_user);
        if ($res_event['size'] > 0) {
            $eventModel = new EventModel();
            $addressModel = new AddressModel();
            foreach ($res_event['list'] as $k => $val) {
                $eventInfo = $eventModel->getEventRedisById($val['attrs']['obj_id']);
                $event['id'] = $eventInfo['id'];
                $event['title'] = $eventInfo['title'];
                $event['cover'] = Common::show_img($eventInfo['cover'], 4, 720, 540);
                $event['max_partake'] = $eventInfo['max_partake'] ? $eventInfo['max_partake'] : '0';
                $event['type'] = $eventInfo['type'];
                $event['lng'] = $eventInfo['lng'];
                $event['lat'] = $eventInfo['lat'];
                $province_name = $addressModel->getNameById($eventInfo['province']);
                $city_name = $addressModel->getNameById($eventInfo['city']);
                $town_name = $addressModel->getNameById($eventInfo['town']);
                if ($province_name == $city_name) {
                    $address_name = $city_name . $town_name;
                } else {
                    $address_name = $province_name . $city_name;
                }
                $event['event_address'] = $address_name;
                $e_time = $eventModel->getEndTime($eventInfo['id']);//結束时间
                $time = date('Y-m-d H:i:s');
                if ($e_time) {
                    if ($e_time[0]['end_time'] <= $time) {
                        //当前时间小于活动结束时间
                        $event['start_type'] = 3;//活动结束
                    } else {
                        $event['start_type'] = 2;//可以报名
                    }
                }
                $event['url'] = $this->_token ? I_DOMAIN . '/e/' . $eventInfo['id'] . '?token=' . $this->_token . '&version=' . $this->_version . '' : I_DOMAIN . '/e/' . $eventInfo['id'] . '?version=' . $this->_version . '';
                if ($eventInfo['type'] == 1) {
                    $data = $eventModel->getBusinessEventType($eventInfo['type_code']);//获取活动分类内容
                } else {
                    $data = Common::eventType($eventInfo['type']);
                }
                $event['type_name'] = $data['name'];
                $event['code_name'] = $data['code'];
                $fields_info = $eventModel->getEventFields($eventInfo['id']);
                if ($this->_version < '3.5') {
                    $event['start_time'] = $fields_info[0]['start_time'];
                    $event['end_time'] = $fields_info[0]['end_time'];
                }
                $event['show_time'] = date('m.d', strtotime($eventInfo['start_time'])) . '-' . date('m.d', strtotime($eventInfo['end_time']));
                $event['show_start_time'] = Common::getEventStartTime($eventInfo['id']);
                $price_info = $eventModel->getPrice($eventInfo['id']);
                if ($eventInfo['price_type'] == 1) {
                    $event['price'] = '免费';
                    $event['price_count'] = 1;
                } else {
                    if (count($price_info) > 1) {
                        $min_price = $price_info[0]['unit_price'];
                        $event['price'] = $min_price;
                        $event['price_count'] = count($price_info);
                    } else {
                        $event['price'] = $price_info ? $price_info[0]['unit_price'] : '免费';
                        $event['price_count'] = 1;
                    }
                    if ($event['price'] == 0) {
                        $event['price'] = '免费';
                        $event['price_count'] = 1;
                    }
                }
                $list['event'][$k] = $event;
            }
        }

        // 获得商品相关数据 type=8代表商品
        $res_goods = $searchModel->getContent(8, $keyword, 0, 3, $this->_user);
        if ($res_goods['size'] > 0) {
            $stagegoodsModel = new StagegoodsModel();
            $stageModel = new StageModel();
            $addressModel = new AddressModel();
            foreach ($res_goods['list'] as $k => $val) {
                $goodsInfo = $stagegoodsModel->getGoodsRedisById($val['attrs']['obj_id']);
                $goods['id'] = $goodsInfo['id'];
                $goods['name'] = $goodsInfo['name'];
                $goods['cover'] = Common::show_img($goodsInfo['cover'], 4, 720, 720);
                $goods['type'] = $goodsInfo['type'];
                $goods['price'] = $goodsInfo['price'];
                $goods['score'] = $goodsInfo['score'];
                $stageInfo = $stageModel->getBasicStageBySid($goodsInfo['sid']);
                // $goods['sid'] = $goodsInfo['sid'];
                $goods['stage_name'] = $stageInfo['name'];
                $province_name = $goodsInfo['province'] ? $addressModel->getNameById($goodsInfo['province']) : '';
                $city_name = $goodsInfo['city'] ? $addressModel->getNameById($goodsInfo['city']) : '';
                if ($province_name || $city_name) {
                    $goods['address_name'] = $province_name . ' ' . $city_name;
                } else {
                    $goods['address_name'] = '';
                }

                $goods['url'] = $this->_token ? I_DOMAIN . '/g/' . $goodsInfo['id'] . '?token=' . $this->_token . '&version=' . $this->_version . '' : I_DOMAIN . '/g/' . $goodsInfo['id'] . '?version=' . $this->_version . '';
                $list['goods'][$k] = $goods;
            }
        }

        Common::appLog('search/getAllList', $this->startTime, $this->_version);
        Common::echoAjaxJson(1, "获取成功", $list);
    }

    // @搜索帖子数据
    public function topicAction()
    {
        $this->checkLoginVersion();
        $keyword = trim($this->getRequest()->getPost('keyword'));
        $keyword = Common::msubstr($keyword, 0, 30, 'utf-8', false);
        if ($keyword == '') {
            Common::echoAjaxJson(2, "请输入关键词");
        }
        $type = (int)$this->getRequest()->getPost('type');
        if (!in_array($type, array(0, 3))) {
            Common::echoAjaxJson(4, "请传递正确关键词类型");
        }
        $page = (int)$this->getRequest()->getPost('page');
        $page = $page ? $page : 1;
        $size = (int)$this->getRequest()->getPost('size');
        $size = $size ? $size : 10;
        $keywordModel = new KeywordModel();
        if ($page == 1) {
            if (!empty($this->_token) && !empty($keyword)) {
                $keywordModel->addHistoryKeyword($this->_user, $type, $keyword);
            }
        }
        $searchModel = new SearchModel();
        $res = $searchModel->getContent(4, $keyword, ($page - 1) * $size, $size, $this->_user);
        $list = $topic = array();
        if ($res['size'] > 0) {
            $topicModel = new TopicModel();
            $userModel = new UserModel();
            foreach ($res['list'] as $k => $val) {
                $topicInfo = $topicModel->getBasicTopicById($val['attrs']['obj_id']);
                if ($topicInfo) {
                    $list['id'] = $topicInfo['id'];
                    $list['sid'] = $topicInfo['sid'];
                    $list['title'] = $topicInfo['title'];
                    $list['summary'] = $topicInfo['summary'] ? $topicInfo['summary'] : '点击查看帖子详情';
                    $list['view_num'] = $topicInfo['view_num'];
                    $list['like_num'] = $topicInfo['like_num'];
                    $list['add_time'] = $topicInfo['add_time'];
                    $list['status'] = $topicInfo['status'];
                    $list['url'] = I_DOMAIN . '/t/' . $topicInfo['id'] . '?token=' . $this->_token . '&version=' . $this->_version;
                    $userInfo = $userModel->getUserData($topicInfo['uid']);
                    $list['nick_name'] = $userInfo['nick_name'];
                    if ($topicInfo['img_src']) {
                        $list['cover'] = Common::show_img($topicInfo['img_src'][0], 4, 400, 400);
                        $list['cover_count'] = count($topicInfo['img_src']);
                    } else {
                        $list['cover'] = '';
                        $list['cover_count'] = 0;
                    }
                    $topic['topic'][] = $list;
                }
            }
        }
        Common::appLog('search/topic', $this->startTime, $this->_version);
        Common::echoAjaxJson(1, "获取成功", $topic ? $topic : (object)array());
    }

    // @搜索驿站数据
    public function stageAction()
    {
        $this->checkLoginVersion();
        $type = (int)$this->getRequest()->getPost('type');
        if (!in_array($type, array(0, 6, 7))) {
            Common::echoAjaxJson(4, "请传递正确的关键词类型");
        }
        $keyword = trim($this->getRequest()->getPost('keyword'));
        $keyword = Common::msubstr($keyword, 0, 30, 'utf-8', false);
        if ($keyword == '') {
            Common::echoAjaxJson(2, "请输入关键词");
        }
        $page = (int)$this->getRequest()->getPost('page');
        $page = $page ? $page : 1;
        $size = (int)$this->getRequest()->getPost('size');
        $size = $size ? $size : 10;
        $keywordModel = new KeywordModel();
        if ($page == 1) {
            if (!empty($this->_token) && !empty($keyword)) {
                $keywordModel->addHistoryKeyword($this->_user, $type, $keyword);
            }
        }
        $searchModel = new SearchModel();
        $res = $searchModel->getUserAndStage(5, $keyword, ($page - 1) * $size, $size, $this->_user);
        $list = $stage = array();
        if ($res['size'] > 0) {
            $stageModel = new StageModel();
            foreach ($res['list'] as $k => $val) {
                $stageInfo = $stageModel->getBasicStageBySid($val['attrs']['obj_id']);
                if ($stageInfo) {
                    $list['sid'] = $stageInfo['sid'];
                    $list['name'] = $stageInfo['name'];
                    $list['intro'] = $stageInfo['intro'];
                    $list['type'] = $stageInfo['type'];
                    $list['intro'] = $stageInfo['intro'];
                    $list['user_num'] = $stageInfo['user_num'];
                    $list['topic_num'] = $stageInfo['topic_num'];
                    $list['status'] = $stageInfo['status'];
                    $list['is_join'] = $stageModel->getJoinStage($stageInfo['sid'], $this->_user);
                    $list['icon'] = Common::show_img($stageInfo['icon'], 4, 400, 400);
                    $stage['stage'][] = $list;
                }
            }
        }
        Common::appLog('search/stage', $this->startTime, $this->_version);
        Common::echoAjaxJson(1, "获取成功", $stage ? $stage : (object)array());
    }

    // @搜索用户数据
    public function userAction()
    {
        $this->checkLoginVersion();
        $type = (int)$this->getRequest()->getPost('type');
        if (!in_array($type, array(0, 1, 7))) {
            Common::echoAjaxJson(4, "请传递正确的关键词类型");
        }
        $keyword = trim($this->getRequest()->getPost('keyword')) ? trim($this->getRequest()->getPost('keyword')) : '';
        $keyword = Common::msubstr($keyword, 0, 30, 'utf-8', false);
        if ($keyword == '') {
            Common::echoAjaxJson(2, "请输入关键词");
        }
        $page = (int)$this->getRequest()->getPost('page');
        $page = $page ? $page : 1;
        $size = (int)$this->getRequest()->getPost('size');
        $size = $size ? $size : 10;
        $userList = $list = array();
        $userModel = new UserModel();
        $keywordModel = new KeywordModel();
        if (preg_match('/^[1-9]\d*$/', $keyword)) {
            if ($page == 1) {
                $user = $userModel->getUserByDid($keyword);
                if ($user) {
                    $userInfo = $userModel->getUserData($user['uid'], $this->_user);
                    if (in_array($userInfo['type'], array(2, 3, 4, 5))) {
                        $angelInfo = $userModel->getInfo($user['uid']);
                        $list['user'][0]['intro'] = $angelInfo['info'];
                    } else {
                        $list['user'][0]['intro'] = $userInfo['intro'];
                    }
                    $list['user'][0]['uid'] = $userInfo['uid'];
                    $list['user'][0]['did'] = $userInfo['did'];
                    $list['user'][0]['nick_name'] = $userInfo['nick_name'];
                    $list['user'][0]['type'] = $userInfo['type'];
                    $list['user'][0]['avatar'] = Common::show_img($userInfo['avatar'], 1, 400, 400);
                    $list['user'][0]['att_num'] = $userInfo['att_num'];
                    $list['user'][0]['fans_num'] = $userInfo['fans_num'];
                }
                if (!empty($this->_token) && !empty($keyword)) {
                    $keywordModel->addHistoryKeyword($this->_user, $type, $keyword);
                }
                Common::appLog('search/user', $this->startTime, $this->_version);
                Common::echoAjaxJson(1, "获取成功", $list ? $list : (object)array());
            }
        }
        if ($page == 1) {
            if (!empty($this->_token) && !empty($keyword)) {
                $keywordModel->addHistoryKeyword($this->_user, $type, $keyword);
            }
        }
        $searchModel = new SearchModel();
        $res = $searchModel->getUserAndStage(6, $keyword, ($page - 1) * $size, $size, $this->_user);
        if ($res['size'] > 0) {
            foreach ($res['list'] as $k => $val) {
                $userInfo = $userModel->getUserData($val['attrs']['obj_id'], $this->_user);
                if ($userInfo != (object)array()) {
                    $userList['uid'] = $userInfo['uid'];
                    $userList['did'] = $userInfo['did'];
                    $userList['nick_name'] = $userInfo['nick_name'];
                    $userList['type'] = $userInfo['type'];
                    if (in_array($userInfo['type'], array(2, 3, 4, 5))) {
                        $angelInfo = $userModel->getInfo($userInfo['uid']);
                        $userList['intro'] = $angelInfo['info'];
                    } else {
                        $userList['intro'] = $userInfo['intro'];
                    }
                    $userList['avatar'] = Common::show_img($userInfo['avatar'], 1, 160, 160);
                    $userList['att_num'] = $userInfo['att_num'];
                    $userList['fans_num'] = $userInfo['fans_num'];
                    $list['user'][] = $userList;
                }
            }
        }
        Common::appLog('search/user', $this->startTime, $this->_version);
        Common::echoAjaxJson(1, "获取成功", $list ? $list : (object)array());
    }

    // @搜索活动数据
    public function eventAction()
    {
        $this->checkLoginVersion();
        $keyword = trim($this->getRequest()->getPost('keyword'));
        $keyword = Common::msubstr($keyword, 0, 30, 'utf-8', false);
        if ($keyword == '') {
            Common::echoAjaxJson(2, "请输入关键词");
        }
        $type = (int)$this->getRequest()->getPost('type');
        if (!in_array($type, array(0, 5))) {
            Common::echoAjaxJson(4, "请传递正确的关键词类型");
        }
        $page = (int)$this->getRequest()->getPost('page');
        $page = $page ? $page : 1;
        $size = (int)$this->getRequest()->getPost('size');
        $size = $size ? $size : 10;
        $keywordModel = new KeywordModel();
        if ($page == 1) {
            if (!empty($this->_token) && !empty($keyword)) {
                $keywordModel->addHistoryKeyword($this->_user, $type, $keyword);
            }
        }
        // 获得服务相关数据 type=7代表服务
        $searchModel = new SearchModel();
        $res_event = $searchModel->getContent(7, $keyword, ($page - 1) * $size, $size, $this->_user);
        $list = array();
        if ($res_event['size'] > 0) {
            $eventModel = new EventModel();
            $addressModel = new AddressModel();
            foreach ($res_event['list'] as $k => $val) {
                $eventInfo = $eventModel->getEventRedisById($val['attrs']['obj_id']);
                $event['id'] = $eventInfo['id'];
                $event['title'] = $eventInfo['title'];
                $event['cover'] = Common::show_img($eventInfo['cover'], 4, 720, 540);
                $event['max_partake'] = $eventInfo['max_partake'] ? $eventInfo['max_partake'] : '0';
                $event['type'] = $eventInfo['type'];
                $event['lng'] = $eventInfo['lng'];
                $event['lat'] = $eventInfo['lat'];
                $province_name = $addressModel->getNameById($eventInfo['province']);
                $city_name = $addressModel->getNameById($eventInfo['city']);
                $town_name = $addressModel->getNameById($eventInfo['town']);
                if ($province_name == $city_name) {
                    $address_name = $city_name . $town_name;
                } else {
                    $address_name = $province_name . $city_name;
                }
                $event['event_address'] = $address_name;
                $e_time = $eventModel->getEndTime($eventInfo['id']);//結束时间
                $time = date('Y-m-d H:i:s');
                if ($e_time) {
                    if ($e_time[0]['end_time'] <= $time) {
                        //当前时间小于活动结束时间
                        $event['start_type'] = 3;//活动结束
                    } else {
                        $event['start_type'] = 2;//可以报名
                    }
                }
                $event['url'] = $this->_token ? I_DOMAIN . '/e/' . $eventInfo['id'] . '?token=' . $this->_token . '&version=' . $this->_version . '' : I_DOMAIN . '/e/'
                    . $eventInfo['id']
                    . '?version=' . $this->_version . '';
                if ($eventInfo['type'] == 1) {
                    $data = $eventModel->getBusinessEventType($eventInfo['type_code']);//获取活动分类内容
                } else {
                    $data = Common::eventType($eventInfo['type']);
                }
                $event['type_name'] = $data['name'];
                $event['code_name'] = $data['code'];
                $fields_info = $eventModel->getEventFields($eventInfo['id']);
                if ($this->_version < '3.5') {
                    $event['start_time'] = $fields_info[0]['start_time'];
                    $event['end_time'] = $fields_info[0]['end_time'];
                }
                $event['show_time'] = date('m.d', strtotime($eventInfo['start_time'])) . '-' . date('m.d', strtotime($eventInfo['end_time']));
                $event['show_start_time'] = Common::getEventStartTime($eventInfo['id']);
                $price_info = $eventModel->getPrice($eventInfo['id']);
                if ($eventInfo['price_type'] == 1) {
                    $event['price'] = '免费';
                    $event['price_count'] = 1;
                } else {
                    if (count($price_info) > 1) {
                        $min_price = $price_info[0]['unit_price'];
                        $event['price'] = $min_price;
                        $event['price_count'] = count($price_info);
                    } else {
                        $event['price'] = $price_info ? $price_info[0]['unit_price'] : '免费';
                        $event['price_count'] = 1;
                    }
                    if ($event['price'] == 0) {
                        $event['price'] = '免费';
                        $event['price_count'] = 1;
                    }
                }
                $list['event'][] = $event;
            }
        }
        Common::appLog('search/event', $this->startTime, $this->_version);
        Common::echoAjaxJson(1, "获取成功", $list ? $list : (object)array());
    }

    // @搜索商品数据
    public function goodsAction()
    {
        $this->checkLoginVersion();
        $type = (int)$this->getRequest()->getPost('type');
        if (!in_array($type, array(0, 4))) {
            Common::echoAjaxJson(4, "请传递正确的关键词类型");
        }
        $keyword = trim($this->getRequest()->getPost('keyword'));
        $keyword = Common::msubstr($keyword, 0, 30, 'utf-8', false);
        if ($keyword == '') {
            Common::echoAjaxJson(2, "请输入关键词");
        }
        $page = (int)$this->getRequest()->getPost('page');
        $page = $page ? $page : 1;
        $size = (int)$this->getRequest()->getPost('size');
        $size = $size ? $size : 10;
        $keywordModel = new KeywordModel();
        if ($page == 1) {
            if (!empty($this->_token) && !empty($keyword)) {
                $keywordModel->addHistoryKeyword($this->_user, $type, $keyword);
            }
        }
        // 获得商品相关数据 type=8代表商品
        $list = array();
        $searchModel = new SearchModel();
        $res_goods = $searchModel->getContent(8, $keyword, ($page - 1) * $size, $size, $this->_user);
        if ($res_goods['size'] > 0) {
            $stagegoodsModel = new StagegoodsModel();
            $stageModel = new StageModel();
            $addressModel = new AddressModel();
            foreach ($res_goods['list'] as $val) {
                $goodsInfo = $stagegoodsModel->getGoodsRedisById($val['attrs']['obj_id']);
                $goods['id'] = $goodsInfo['id'];
                $goods['name'] = $goodsInfo['name'];
                $goods['cover'] = Common::show_img($goodsInfo['cover'], 4, 720, 720);
                $goods['type'] = $goodsInfo['type'];
                $goods['price'] = $goodsInfo['price'];
                $goods['score'] = $goodsInfo['score'];
                $stageInfo = $stageModel->getBasicStageBySid($goodsInfo['sid']);
                // $goods['sid'] = $goodsInfo['sid'];
                $goods['stage_name'] = $stageInfo['name'];
                $province_name = $goodsInfo['province'] ? $addressModel->getNameById($goodsInfo['province']) : '';
                $city_name = $goodsInfo['city'] ? $addressModel->getNameById($goodsInfo['city']) : '';
                if ($province_name || $city_name) {
                    $goods['address_name'] = $province_name . ' ' . $city_name;
                } else {
                    $goods['address_name'] = '';
                }
                $goods['url'] = $this->_token ? I_DOMAIN . '/g/' . $goodsInfo['id'] . '?token=' . $this->_token . '&version=' . $this->_version . '' : I_DOMAIN . '/g/'
                    . $goodsInfo['id'] . '?version=' . $this->_version . '';
                $list['goods'][] = $goods;
            }
        }
        Common::appLog('search/goods', $this->startTime, $this->_version);
        Common::echoAjaxJson(1, "获取成功", $list ? $list : (object)array());
    }
    // @心境搜索数据

    //热门搜索，搜索历史--老版本
    public function getHotAndHistoryAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $keywordModel = new KeywordModel();
        $list['history'] = $keywordModel->getLast($user['uid']);
        $list['hot'] = $keywordModel->getHot();
        Common::appLog('search/getHotAndHistory', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $list ? $list : (object)array());
    }

    // 分享联盟搜索全部数据接口
    public function getAllShareAllianceAction()
    {
        $this->checkLoginVersion();
        $keywordType = (int)$this->getRequest()->getPost('keywordType'); // 关键词类型 8全部(分享联盟) 9商品(分享联盟) 10活动(分享联盟)
        if (!in_array($keywordType, array(8))) {
            Common::echoAjaxJson(3, "请传递正确的关键词类型");
        }
        $keywordType = $keywordType ? $keywordType : 8;
        $keyword = trim($this->getRequest()->getPost('keyword'));
        $keyword = Common::msubstr($keyword, 0, 30, 'utf-8', false);
        if ($keyword == '') {
            Common::echoAjaxJson(2, "请输入关键词");
        }
        $keywordModel = new KeywordModel();
        // 登录状态下才记录用户搜索过的关键词
        if (!empty($this->_token) && !empty($keyword)) {
            $keywordModel->addHistoryKeyword($this->_user, $keywordType, $keyword);
        }
        $list = $res = array();
        $searchModel = new SearchModel();

        // 获得服务相关数据 type=1 服务活动(分享联盟)
        $res_events = $searchModel->getLeague(1, 0, 0, $keyword, 0, 3, $this->_user);
        if ($res_events['size'] > 0) {
            $eventModel = new EventModel();
            foreach ($res_events['list'] as $key => $value) {
                $res[$key] = $eventModel->getStageEventById($value['attrs']['obj_id'], $this->_version, $this->_token);
            }
            $list['stageEvent'] = $res;
        }

        // 获得商品相关数据 type=2 商品(分享联盟)
        $res_goods = $searchModel->getLeague(2, 0, 0, $keyword, 0, 3, $this->_user);
        if ($res_goods['size'] > 0) {
            $stagegoodsModel = new StagegoodsModel();
            foreach ($res_goods['list'] as $key => $value) {
                $res[$key] = $stagegoodsModel->getStageGoodsById($value['attrs']['obj_id'], $this->_version, $this->_token);
                $list['stageGoods'] = $res;
            }
        }

        Common::appLog('search/getAllShareAlliance', $this->startTime, $this->_version);
        Common::echoAjaxJson(1, "获取成功", $list ? $list : (object)array());

    }

    // 分享联盟搜索商品数据接口
    public function getGoodsShareAllicanceAction()
    {
        $this->checkLoginVersion();
        $keywordType = intval($this->getRequest()->getPost('keywordType'));// 关键词类型 8全部(分享联盟) 9商品(分享联盟) 10活动(分享联盟)
        if (!in_array($keywordType, array(9))) {
            Common::echoAjaxJson(3, "请传递正确的关键词类型");
        }
        $keywordType = $keywordType ? $keywordType : 9;
        $keyword = trim($this->getRequest()->getPost('keyword'));
        $keyword = Common::msubstr($keyword, 0, 30, 'utf-8', false);
        if ($keyword == '') {
            Common::echoAjaxJson(2, "请输入关键词");
        }
        $cateId = (int)$this->getRequest()->getPost('cateId'); // 商品分类
        $cateId = $cateId ? $cateId : 0;
        $sortId = (int)$this->getRequest()->getPost('sortId'); // 排序类型  0默认空按照权重排序  1、综合排序（按设置佣金时间排序） 2、佣金金额由高到低排序  3、30天引入订单金额由高到低  4、30天支出奖金金额由高到低
        $sortId = $sortId ? $sortId : 0;
        $size = 10; //条数
        $page = $this->getRequest()->getPost("page") ? intval($this->getRequest()->getPost("page")) : 1; //页数

        $keywordModel = new KeywordModel();
        // 登录状态下才记录用户搜索过的关键词
        if (!empty($this->_token) && !empty($keyword)) {
            $keywordModel->addHistoryKeyword($this->_user, $keywordType, $keyword);
        }
        $list = $res = array();

        $searchModel = new SearchModel();
        // 获得商品相关数据 type=2 商品(分享联盟)
        $res_goods = $searchModel->getLeague(2, $sortId, $cateId, $keyword, ($page - 1) * $size, $size, $this->_user);
        if ($res_goods['size'] > 0) {
            $stagegoodsModel = new StagegoodsModel();
            foreach ($res_goods['list'] as $key => $value) {
                $res[$key] = $stagegoodsModel->getStageGoodsById($value['attrs']['obj_id'], $this->_version, $this->_token);
            }
            $list['stageGoods'] = $res;
        }

        Common::appLog('search/getGoodsShareAllicance', $this->startTime, $this->_version);
        Common::echoAjaxJson(1, "获取成功", $list ? $list : (object)array());

    }

    // 分享联盟搜索活动数据接口
    public function getEventShareAllicanceAction()
    {
        $this->checkLoginVersion();
        $keywordType = intval($this->getRequest()->getPost('keywordType'));// 关键词类型 8全部(分享联盟) 9商品(分享联盟) 10活动(分享联盟)
        if (!in_array($keywordType, array(10))) {
            Common::echoAjaxJson(3, "请传递正确的关键词类型");
        }
        $keywordType = $keywordType ? $keywordType : 10;
        $keyword = trim($this->getRequest()->getPost('keyword'));
        $keyword = Common::msubstr($keyword, 0, 30, 'utf-8', false);
        if ($keyword == '') {
            Common::echoAjaxJson(2, "请输入关键词");
        }
        $typeId = intval($this->getRequest()->getPost('typeId')); // 服务分类：1活动 3培训 8展演
        $typeId = $typeId ? $typeId : 0;
        $sortId = intval($this->getRequest()->getPost('sortId'));// 排序类型  0默认空按照权重排序  1、综合排序（按设置佣金时间排序） 2、佣金金额由高到低排序  3、30天引入订单金额由高到低  4、30天支出奖金金额由高到低
        $sortId = $sortId ? $sortId : 0;
        $size = 10; //条数
        $page = $this->getRequest()->getPost("page") ? intval($this->getRequest()->getPost("page")) : 1; //页数

        $keywordModel = new KeywordModel();
        // 登录状态下才记录用户搜索过的关键词
        if (!empty($this->_token) && !empty($keyword)) {
            $keywordModel->addHistoryKeyword($this->_user, $keywordType, $keyword);
        }
        $list = $res = array();

        $searchModel = new SearchModel();
        // 获得服务相关数据 type=1 服务活动(分享联盟)
        $res_events = $searchModel->getLeague(1, $sortId, $typeId, $keyword, ($page - 1) * $size, $size, $this->_user);
        if ($res_events['size'] > 0) {
            $eventModel = new EventModel();
            foreach ($res_events['list'] as $key => $value) {
                $res[$key] = $eventModel->getStageEventById($value['attrs']['obj_id'], $this->_version, $this->_token);
            }
            $list['stageEvent'] = $res;
        }

        Common::appLog('search/getEventShareAllicance', $this->startTime, $this->_version);
        Common::echoAjaxJson(1, "获取成功", $list ? $list : (object)array());
    }
}
