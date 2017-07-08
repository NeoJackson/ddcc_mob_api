<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/3/30
 * Time: 14:28
 */
class RongCloudIM
{
    protected $_appKey = 'pwe86ga5p2wp6';
    protected $_appSecret = 'pds1syY5SLs8BK';
    protected $_format = 'json';
    //   protected $_jsonPath = "jsonsource/";

    /**
     * 参数初始化
     * @param $appKey
     * @param $appSecret
     * @param string $format
     */
    public function __construct() {
        require_once 'rongCloudOfIM/API/SendRequest.php';
        $this->SendRequest = new SendRequest($this->_appKey, $this->_appSecret, $this->_format);
    }

    public function User() {
        require_once 'rongCloudOfIM/API/methods/User.php';
        $User = new User($this->SendRequest);
        return $User;
    }

    public function Message() {
        require_once 'rongCloudOfIM/API/methods/Message.php';
        $Message = new Message($this->SendRequest);
        return $Message;
    }

    public function Wordfilter() {
        require_once 'rongCloudOfIM/API/methods/Wordfilter.php';
        $Wordfilter = new Wordfilter($this->SendRequest);
        return $Wordfilter;
    }

    public function Group() {
        require_once 'rongCloudOfIM/API/methods/Group.php';
        $Group = new Group($this->SendRequest);
        return $Group;
    }

    public function Chatroom() {
        require_once 'rongCloudOfIM/API/methods/Chatroom.php';
        $Chatroom = new Chatroom($this->SendRequest);
        return $Chatroom;
    }

    public function Push() {
        require_once 'rongCloudOfIM/API/methods/Push.php';
        $Push = new Push($this->SendRequest);
        return $Push;
    }

    public function SMS() {
        require_once 'rongCloudOfIM/API/methods/SMS.php';
        $SMS = new SMS($this->SendRequest);
        return $SMS;
    }
}
