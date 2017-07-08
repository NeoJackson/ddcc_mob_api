<?php

class ChatController extends Yaf_Controller_Abstract
{

    protected $_user = '';
    protected $_token = '';
    protected $_version = '';
    protected $_startTime = '';

    public function init()
    {
        $this->startTime = microtime(true);
    }

    // 检查获取用户登入状态及版本号信息
    protected function checkLoginVersion()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(0, "非法登录用户");
        }
        $this->_user = $user;
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') : APP_VERSION;
        $this->_version = $version;
        $token = $this->getRequest()->getPost('token');//用户登录token
        $this->_token = $token;
    }

    //私信列表
    public function letterNewAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(3, "非法用户登录");
        }
        $uid = $user['uid'];
        $type = (int)$this->getRequest()->getPost('type');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if (!in_array($type, array(2, 3))) {//2是苹果 3是安卓
            Common::echoAjaxJson(2, "系统通知类型错误");
        }
        $noticeModel = new NoticeModel();
        $stageModel = new StageModel();
        $stage = $stageModel->getDynamicList($uid, 1, 1);
        if ($stage && $stage[0]['topic_list']) {
            $topic_title = $stage[0]['topic_list'][0]['title'];
        } else {
            $topic_title = '';
        }
        $array = array(
            'notice' => $noticeModel->getList($uid, $type, 0, 1, 0),
            'stage' => array(
                'new_num' => $stageModel->getRedisNewTopicNumTotals($uid),
                'stage_name' => $topic_title
            ),
        );
        Common::appLog('chat/letterNew', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $array);
    }

    /**
     * 删除某条私信
     */
    public function delMessageAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(4, "非法用户登录");
        }
        $id = $this->getRequest()->getPost('id');
        $rcId = $this->getRequest()->getPost('rcId');
        $type = $this->getRequest()->getPost('type');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        echo Common::http(OPEN_DOMAIN . "/chatapi/delMessage", array('uid' => $user['uid'], 'id' => $id, 'rcId' => $rcId, 'type' => $type), "POST");
        Common::appLog('chat/delMessage', $this->startTime, $version);
    }

    //删除和某个用户的私信
    public function delUserMessageAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(4, "非法用户登录");
        }
        $to_uid = (int)$this->getRequest()->getPost('uid');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        echo Common::http(OPEN_DOMAIN . "/chatapi/delUserMessage", array('uid' => $user['uid'], 'to_uid' => $to_uid), "POST");
        Common::appLog('chat/delUserMessage', $this->startTime, $version);
    }

    //删除和某个驿站的消息
    public function delStageMessageAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(4, "非法用户登录");
        }
        $uid = $user['uid'];
        $sid = (int)$this->getRequest()->getPost('sid');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if ($sid < 1) {
            Common::echoAjaxJson(2, '删除的驿站不能为空');
        }
        // 兼容融云 ID 2017/4/21
        echo Common::http(OPEN_DOMAIN . "/chatapi/delStageMessage", array('uid' => $uid, 'sid' => $sid), "POST");
        Common::appLog('chat/delStageMessage', $this->startTime, $version);
        Common::echoAjaxJson(1, "删除成功");
    }

    //屏蔽消息
    public function closeMessageAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $type = $this->getRequest()->getPost('type');//类型 1用户 2驿站
        $gid = $this->getRequest()->getPost('gid');//人或者驿站的ID
        $message_type = $this->getRequest()->getPost('message_type'); //屏蔽类型 1接收消息并提醒 2屏蔽提醒并接收消息
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if (!$type) {
            Common::echoAjaxJson(2, "类型为空");
        }
        if (!$gid) {
            Common::echoAjaxJson(3, "对象id为空");
        }
        if (!$message_type) {
            Common::echoAjaxJson(4, "屏蔽类型为空");
        }
        $chatModel = new ChatModel();
        $time = date('Y-m-d H:i:s');
        $chatModel->addChat($user['uid'], $gid, $type, $time);
        $chatModel->closeMessage($user['uid'], $type, $gid, $message_type);
        Common::appLog('chat/closeMessage', $this->startTime, $version);
        Common::echoAjaxJson(1, "设置成功");
    }

    //消息是否屏蔽
    public function messageIsCloseAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $type = $this->getRequest()->getPost('type');//类型 1用户 2驿站
        $gid = $this->getRequest()->getPost('gid');//人或者驿站的ID
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if (!$type) {
            Common::echoAjaxJson(2, "类型为空");
        }
        if (!$gid) {
            Common::echoAjaxJson(3, "对象id为空");
        }
        $chatModel = new ChatModel();
        $rs = $chatModel->messageIsClose($user['uid'], $type, $gid);
        if (!$rs) {
            $rs = 1;
        }
        Common::appLog('chat/messageIsClose', $this->startTime, $version);
        Common::echoAjaxJson(1, "获取成功", $rs);
    }

    //将私聊消息置为已读
    public function setIsReadUserMessageAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $from_uid = $this->getRequest()->getPost('from_uid');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $chatModel = new ChatModel();
        $rs = $chatModel->clearUserUnRead($from_uid, $user['uid']);
        if (!$rs) {
            Common::echoAjaxJson(2, "参数错误");
        }
        Common::appLog('chat/setIsReadUserMessage', $this->startTime, $version);
        Common::echoAjaxJson(1, "设置成功");
    }

    // 以下是整合第三方融云IM平台的相关接口封装

    // 从融云平台获取Token值
    public function getTokenAction()
    {
        $this->checkLoginVersion();

        $userId = $this->_user['uid'];
        if (!$userId) {
            Common::echoAjaxJson(3, '请传递用户ID');
        }
        // $tokenStatus = (int)$this->getRequest()->getPost('tokenStatus'); // 1用户toKen值还有效 2用户toKen值已无效

        // 1、通过用户id先走Redis数据库查询用户是否已经有token值，有就返回对应token值，没有就去融云平台获取
        $chatModel = new ChatModel();
        // $dataLists = $chatModel->getUidToken($userId);

        // 2、如果Redis里没有或者token值已经失效，就去融云IM平台获取token值
        $userName = $this->_user['nick_name'];
        if (!$userName) {
            Common::echoAjaxJson(4, '请传递用户userName');
        }
        $userPortraitUri = $this->_user['avatar'];
        if (!$userPortraitUri) {
            Common::echoAjaxJson(5, '请传递用户头像url地址');
        }
        // 去融云IM平台获取
        $chatIM = new RongCloudIM();
        $res = json_decode($chatIM->user()->getToken($userId, $userName, $userPortraitUri), true);
        if ($res['code'] != 200) {
            Common::echoAjaxJson(2, '获取融云IM平台的用户token值失败');
        }

        // 将拿到的Token值存入Redis、Mysql数据库中
        $message = array(
            'message_id' => $userId,
            'userId' => $userId,
            'userName' => $userName,
            'userPortraitUri' => $userPortraitUri,
            'rongCloudToken' => $res['token'],
        );
        $chatModel->addUidToken($userId, $message);
        $dataLists = $message;
        $this->_user['rcToken'] = $res['token'];

        Common::appLog('chat/getToken', $this->_startTime, $this->_version);
        Common::echoAjaxJson(1, '获取成功', $dataLists);
    }

    // 离线消息漫游接口
    public function getRoamMsgAction()
    {
        $this->checkLoginVersion();
        $userId = $this->_user['uid'];
        if (!$userId) {
            Common::echoAjaxJson(3, '请传递用户ID');
        }
        $channelType = (int)$this->getRequest()->getPost('channelType'); // 会话类型 1用户消息类型 2驿站消息类型
        if (!$channelType) {
            Common::echoAjaxJson(2, '请传递会话类型');
        }
        $toUidOrSid = (int)$this->getRequest()->getPost('toUidOrSid'); // 会话消息对象uid或sid
        if (!$toUidOrSid) {
            Common::echoAjaxJson(4, '请传递会话消息对象uid或sid');
        }
        $lastContentId = $this->getRequest()->getPost('lastContentId'); // 历史最后一条消息ID
        $lastContentId = $lastContentId ? $lastContentId : 0;
        if ($lastContentId<0) {
            Common::echoAjaxJson(5, '请传递历史最后一条消息ID');
        }
        $page = (int)$this->getRequest()->getPost('page'); // 页数
        $page = $page ? $page : 1;
        $size = (int)$this->getRequest()->getPost('size'); // 条数
        $size = $size ? $size : 30;
        // $queryTime = $this->getRequest()->getPost('queryTime'); // 查询时间
        $list = array();
        $chatModel = new ChatModel();
        $list['contentData'] = $chatModel->getRoamMsg($userId, $toUidOrSid, $channelType, $lastContentId, $page, $size);

        Common::appLog('chat/getRoamMsg', $this->_startTime, $this->_version);
        Common::echoAjaxJson(1, '消息接收成功', $list ? $list : (object)array());
    }

    // 同步现有所有正常的驿站及驿站所有成员的数据到融云IM并创建群
    public function syncStageChatAction()
    {
        $chatModel = new ChatModel();
        $list = $chatModel->syncStageChat();
        // Common::echoAjaxJson(1, '消息接收成功', $list ? $list : (object)array());
    }
}
