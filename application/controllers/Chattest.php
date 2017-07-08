<?php

class ChattestController extends Yaf_Controller_Abstract
{
    //添加私信
    public function addMessageAction()
    {
        $parameters = array(
            'token' => 'c986f7160bd1a1e86d18cc30523ed927',
            'uid' => 3480,
            'type' => 0,
            //'content'=>'14279703002471.jpg'
            'content' => '“代代传承11哈”'
        );
        Common::verify($parameters, '/chat/addMessage');
    }

    //驿站群聊
    public function addStageMessageAction()
    {
        $parameters = array(
            'token' => '54d7ed519214c24e93463c39585106bc',
            'sid' => 595,
            'type' => 0,
            //'content'=>'14279703002471.jpg'
            'content' => '“代代传承”'
        );
        Common::verify($parameters, '/chat/addStageMessage');
    }

    //删除私信
    public function delMessageAction()
    {
        $parameters = array(
            'token' => '7b0fef41d1e0a1d80fed07ba4dc8b882',
            'id' => 326045
        );
        Common::verify($parameters, '/chat/delMessage');
    }

    //删除驿站消息
    public function delStageMessageAction()
    {
        $parameters = array(
            'token' => '54d7ed519214c24e93463c39585106bc',
            'sid' => 595
        );
        Common::verify($parameters, '/chat/delStageMessage');
    }

    //轮询用户消息
    public function getUserMessageAction()
    {
        $parameters = array(
            'token' => '45483d261e05a5475acda8f0212eaad4',
            'uid' => 12476,
            'last_id' => '405866'
        );
        Common::verify($parameters, '/chat/getUserMessage');
    }

    //轮询驿站消息
    public function getStageMessageAction()
    {
        $parameters = array(
            'token' => 'aba58659c61ec973c2e8f0302321445f',
            'sid' => 595,
            'last_id' => 419000
        );
        Common::verify($parameters, '/chat/getStageMessage');
    }

    //获取某个用户的消息列表
    public function getLastUserMessageAction()
    {
        $parameters = array(
            'token' => '54d7ed519214c24e93463c39585106bc',
            'uid' => 3568,
            'last_id' => 0
        );
        Common::verify($parameters, '/chat/getLastUserMessage');
    }

    //私信列表
    public function letterNewAction()
    {
        $parameters = array(
            'token' => 'cbdc467d29836ec5f94773b29d34b5e3',
            'type' => 2,
            'version' => '3.0'
        );
        Common::verify($parameters, '/chat/letterNew');
    }

    //私信列表
    public function getLastStageMessageAction()
    {
        $parameters = array(
            'token' => 'aba58659c61ec973c2e8f0302321445f',
            'last_id' => 0,
            'sid' => 595

        );
        Common::verify($parameters, '/chat/getLastStageMessage');
    }

    public function messageIsCloseAction()
    {
        $parameters = array(
            'token' => '4b50c2dc22a9b055786bc5bcbc12729b',
            'gid' => 8002,
            'type' => 1

        );
        Common::verify($parameters, '/chat/messageIsClose');
    }


    // 以下是整合第三方融云IM平台的相关接口封装

    //从融云平台获取Token值
    public function getTokenAction()
    {
        $parameters = array(
            'token' => 'ccb976bfb2228001460b950dd5dae4f3',
        );
        Common::verify($parameters, '/chat/getToken');
    }

    // 离线消息漫游接口
    public function getRoamMsgAction()
    {
        $parameters = array(
            'token' => 'ccb976bfb2228001460b950dd5dae4f3', // uid=13859  测驿站
            'channelType' => 2,
            'lastContentId' => '', // cloud_id=789
            'toUidOrSid' => 1382, // sid=1382
             /*'token' => '9017b8e644af28ca54f0a237770c675f', // uid=13858  测用户
             'channelType' => 1,
             'lastContentId' => '', // cloud_id=123
             'toUidOrSid' => 10758, // uid=10758*/
        );
        Common::verify($parameters, '/chat/getRoamMsg');
    }


}
