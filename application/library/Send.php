<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 15-3-31
 * Time: 上午9:39
 */
//include "notification/android/AndroidBroadcast.php";
//include "notification/android/AndroidFilecast.php";
//include "notification/android/AndroidGroupcast.php";
//include "notification/android/AndroidBroadcast.php";
//include "notification/android/AndroidUnicast.php";
//include "notification/ios/IOSBroadcast.php";
//include "notification/ios/IOSFilecast.php";
//include "notification/ios/IOSGroupcast.php";
//include "notification/ios/IOSUnicast.php";
//include "notification/ios/IOSCustomizedcast.php";

require_once(dirname(__FILE__) . '/' . 'notification/android/AndroidBroadcast.php');
require_once(dirname(__FILE__) . '/' . 'notification/android/AndroidFilecast.php');
require_once(dirname(__FILE__) . '/' . 'notification/android/AndroidGroupcast.php');
require_once(dirname(__FILE__) . '/' . 'notification/android/AndroidUnicast.php');
require_once(dirname(__FILE__) . '/' . 'notification/android/AndroidCustomizedcast.php');
require_once(dirname(__FILE__) . '/' . 'notification/ios/IOSBroadcast.php');
require_once(dirname(__FILE__) . '/' . 'notification/ios/IOSFilecast.php');
require_once(dirname(__FILE__) . '/' . 'notification/ios/IOSGroupcast.php');
require_once(dirname(__FILE__) . '/' . 'notification/ios/IOSUnicast.php');
require_once(dirname(__FILE__) . '/' . 'notification/ios/IOSCustomizedcast.php');

class Send {
    protected $androidAppkey              = '5514bd55fd98c5771c0002f9';
    protected $androidAppMasterSecret     = 'l1awv1zprnifipw7ahi7ifrz1nk0fm5p';
    protected $iosAppkey                  = '546c7ec6fd98c57e06005d90';
    protected $iosAppMasterSecret         = '9tlg2rijzhlcl8fgcyv0lznhuy0jtjb6';
    protected   $timestamp        = NULL;
    protected $validation_token = NULL;

    function __construct($key, $secret) {
        $this->appkey = $key;
        $this->appMasterSecret = $secret;
        $this->timestamp = strval(time());
    }

    function sendAndroidBroadcast() {
        try {
            $brocast = new AndroidBroadcast();
            $brocast->setAppMasterSecret($this->androidAppMasterSecret);
            $brocast->setPredefinedKeyValue("appkey",           $this->androidAppkey );
            $brocast->setPredefinedKeyValue("timestamp",        $this->timestamp);
            $brocast->setPredefinedKeyValue("ticker",           "Android broadcast ticker");
            $brocast->setPredefinedKeyValue("title",            "中文的title");
            $brocast->setPredefinedKeyValue("text",             "Android broadcast text");
            $brocast->setPredefinedKeyValue("after_open",       "go_app");
            // Set 'production_mode' to 'false' if it's a test device.
            // For how to register a test device, please see the developer doc.
            $brocast->setPredefinedKeyValue("production_mode", "true");
            // [optional]Set extra fields
            $brocast->setExtraField("test", "helloworld");
            print("Sending broadcast notification, please wait...\r\n");
            $brocast->send();
            print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
    }

public static function sendAndroidUnicast($device_tokens,$content) {
        try {
            $unicast = new AndroidUnicast();
            $unicast->setAppMasterSecret('l1awv1zprnifipw7ahi7ifrz1nk0fm5p');
            $unicast->setPredefinedKeyValue("appkey",           '5514bd55fd98c5771c0002f9');
            $unicast->setPredefinedKeyValue("timestamp",        strval(time()));
            // Set your device tokens here
            $unicast->setPredefinedKeyValue("device_tokens",    $device_tokens);
            $unicast->setPredefinedKeyValue("ticker",           "才府");
            $unicast->setPredefinedKeyValue("title",            "才府");
            $unicast->setPredefinedKeyValue("text",             $content);
            $unicast->setPredefinedKeyValue("after_open",       "go_app");
            // Set 'production_mode' to 'false' if it's a test device.
            // For how to register a test device, please see the developer doc.
            $unicast->setPredefinedKeyValue("production_mode", "false");
            // Set extra fields
            $unicast->setExtraField("test", "helloworld");
            //print("Sending unicast notification, please wait...\r\n");
            $unicast->send();
            //print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            $commonModel = new CommonModel();
            $commonModel->addUmengError($e->getMessage(),$device_tokens,4);
            //print("Caught exception: " . $e->getMessage());
        }
    }

    function sendAndroidFilecast() {
        try {
            $filecast = new AndroidFilecast();
            $filecast->setAppMasterSecret($this->androidAppMasterSecret);
            $filecast->setPredefinedKeyValue("appkey",           $this->androidAppkey);
            $filecast->setPredefinedKeyValue("timestamp",        $this->timestamp);
            $filecast->setPredefinedKeyValue("ticker",           "Android filecast ticker");
            $filecast->setPredefinedKeyValue("title",            "Android filecast title");
            $filecast->setPredefinedKeyValue("text",             "Android filecast text");
            $filecast->setPredefinedKeyValue("after_open",       "go_app");  //go to app
            print("Uploading file contents, please wait...\r\n");
            // Upload your device tokens, and use '\n' to split them if there are multiple tokens
            $filecast->uploadContents("aa"."\n"."bb");
            print("Sending filecast notification, please wait...\r\n");
            $filecast->send();
            print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
    }

    function sendAndroidGroupcast() {
        try {
            /*
              *  Construct the filter condition:
              *  "where":
              *	{
              *		"and":
              *		[
                *			{"tag":"test"},
                *			{"tag":"Test"}
              *		]
              *	}
              */
            $filter = 	array(
                "where" => 	array(
                    "and" 	=>  array(
                        array(
                            "tag" => "test"
                        ),
                        array(
                            "tag" => "Test"
                        )
                    )
                )
            );

            $groupcast = new AndroidGroupcast();
            $groupcast->setAppMasterSecret($this->androidAppMasterSecret);
            $groupcast->setPredefinedKeyValue("appkey",           $this->androidAppkey);
            $groupcast->setPredefinedKeyValue("timestamp",        $this->timestamp);
            // Set the filter condition
            $groupcast->setPredefinedKeyValue("filter",           $filter);
            $groupcast->setPredefinedKeyValue("ticker",           "Android groupcast ticker");
            $groupcast->setPredefinedKeyValue("title",            "Android groupcast title");
            $groupcast->setPredefinedKeyValue("text",             "Android groupcast text");
            $groupcast->setPredefinedKeyValue("after_open",       "go_app");
            // Set 'production_mode' to 'false' if it's a test device.
            // For how to register a test device, please see the developer doc.
            $groupcast->setPredefinedKeyValue("production_mode", "true");
            print("Sending groupcast notification, please wait...\r\n");
            $groupcast->send();
            print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
    }

    function sendAndroidCustomizedcast() {
        try {
            $customizedcast = new AndroidCustomizedcast();
            $customizedcast->setAppMasterSecret($this->androidAppMasterSecret);
            $customizedcast->setPredefinedKeyValue("appkey",           $this->androidAppkey);
            $customizedcast->setPredefinedKeyValue("timestamp",        $this->timestamp);
            // Set your alias here, and use comma to split them if there are multiple alias.
            // And if you have many alias, you can also upload a file containing these alias, then
            // use file_id to send customized notification.
            $customizedcast->setPredefinedKeyValue("alias",            "xx");
            // Set your alias_type here
            $customizedcast->setPredefinedKeyValue("alias_type",       "xx");
            $customizedcast->setPredefinedKeyValue("ticker",           "Android customizedcast ticker");
            $customizedcast->setPredefinedKeyValue("title",            "Android customizedcast title");
            $customizedcast->setPredefinedKeyValue("text",             "Android customizedcast text");
            $customizedcast->setPredefinedKeyValue("after_open",       "go_app");
            print("Sending customizedcast notification, please wait...\r\n");
            $customizedcast->send();
            print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
    }

    function sendIOSBroadcast() {
        try {
            $brocast = new IOSBroadcast();
            $brocast->setAppMasterSecret($this->iosAppMasterSecret);
            $brocast->setPredefinedKeyValue("appkey",           $this->iosAppkey);
            $brocast->setPredefinedKeyValue("timestamp",        $this->timestamp);

            $brocast->setPredefinedKeyValue("alert", "IOS 广播测试");
            $brocast->setPredefinedKeyValue("badge", 0);
            $brocast->setPredefinedKeyValue("sound", "chime");
            // Set 'production_mode' to 'true' if your app is under production mode
            $brocast->setPredefinedKeyValue("production_mode", "false");
            // Set customized fields
            $brocast->setCustomizedField("test", "helloworld");
            print("Sending broadcast notification, please wait...\r\n");
            $brocast->send();
            print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
    }

public static function sendIOSUnicast($device_tokens,$content,$badge) {

        try {
            $unicast = new IOSUnicast();
            $unicast->setAppMasterSecret('9tlg2rijzhlcl8fgcyv0lznhuy0jtjb6');
            $unicast->setPredefinedKeyValue("appkey",           '546c7ec6fd98c57e06005d90');
            $unicast->setPredefinedKeyValue("timestamp",        strval(time()));
            // Set your device tokens here
            $unicast->setPredefinedKeyValue("device_tokens",   $device_tokens);
            $unicast->setPredefinedKeyValue("alert", $content);
            $unicast->setPredefinedKeyValue("badge",(int)$badge);
            $unicast->setPredefinedKeyValue("sound", "chime");
            // Set 'production_mode' to 'true' if your app is under production mode
            $unicast->setPredefinedKeyValue("production_mode", 'true');
            // Set customized fields
            $unicast->setCustomizedField("test", "helloworld");
            //print("Sending unicast notification, please wait...\r\n");
            $result = $unicast->send();
            return $result;
            //print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            $commonModel = new CommonModel();
            $commonModel->addUmengError($e->getMessage(),$device_tokens,3);
            //print("Caught exception: " . $e->getMessage());
        }
    }

    function sendIOSFilecast() {
        try {
            $filecast = new IOSFilecast();
            $filecast->setAppMasterSecret($this->iosAppMasterSecret);
            $filecast->setPredefinedKeyValue("appkey",           $this->iosAppkey);
            $filecast->setPredefinedKeyValue("timestamp",        $this->timestamp);

            $filecast->setPredefinedKeyValue("alert", "IOS 文件播测试");
            $filecast->setPredefinedKeyValue("badge", 0);
            $filecast->setPredefinedKeyValue("sound", "chime");
            // Set 'production_mode' to 'true' if your app is under production mode
            $filecast->setPredefinedKeyValue("production_mode", "false");
            print("Uploading file contents, please wait...\r\n");
            // Upload your device tokens, and use '\n' to split them if there are multiple tokens
            $filecast->uploadContents("aa"."\n"."bb");
            print("Sending filecast notification, please wait...\r\n");
            $filecast->send();
            print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
    }

    function sendIOSGroupcast() {
        try {
            /*
              *  Construct the filter condition:
              *  "where":
              *	{
              *		"and":
              *		[
                *			{"tag":"iostest"}
              *		]
              *	}
              */
            $filter = 	array(
                "where" => 	array(
                    "and" 	=>  array(
                        array(
                            "tag" => "iostest"
                        )
                    )
                )
            );

            $groupcast = new IOSGroupcast();
            $groupcast->setAppMasterSecret($this->iosAppMasterSecret);
            $groupcast->setPredefinedKeyValue("appkey",           $this->iosAppkey);
            $groupcast->setPredefinedKeyValue("timestamp",        $this->timestamp);
            // Set the filter condition
            $groupcast->setPredefinedKeyValue("filter",           $filter);
            $groupcast->setPredefinedKeyValue("alert", "IOS 组播测试");
            $groupcast->setPredefinedKeyValue("badge", 0);
            $groupcast->setPredefinedKeyValue("sound", "chime");
            // Set 'production_mode' to 'true' if your app is under production mode
            $groupcast->setPredefinedKeyValue("production_mode", "false");
            print("Sending groupcast notification, please wait...\r\n");
            $groupcast->send();
            print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
    }

    function sendIOSCustomizedcast() {
        try {
            $customizedcast = new IOSCustomizedcast();
            $customizedcast->setAppMasterSecret($this->iosAppMasterSecret);
            $customizedcast->setPredefinedKeyValue("appkey",           $this->iosAppkey);
            $customizedcast->setPredefinedKeyValue("timestamp",        $this->timestamp);

            // Set your alias here, and use comma to split them if there are multiple alias.
            // And if you have many alias, you can also upload a file containing these alias, then
            // use file_id to send customized notification.
            $customizedcast->setPredefinedKeyValue("alias", "xx");
            // Set your alias_type here
            $customizedcast->setPredefinedKeyValue("alias_type", "xx");
            $customizedcast->setPredefinedKeyValue("alert", "IOS 个性化测试");
            $customizedcast->setPredefinedKeyValue("badge", 0);
            $customizedcast->setPredefinedKeyValue("sound", "chime");
            // Set 'production_mode' to 'true' if your app is under production mode
            $customizedcast->setPredefinedKeyValue("production_mode", "false");
            print("Sending customizedcast notification, please wait...\r\n");
            $customizedcast->send();
            print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
    }
}

// Set your appkey and master secret here
/*$demo = new Demo("your appkey", "your app master secret");
$demo->sendAndroidUnicast();
/* these methods are all available, just fill in some fields and do the test
 * $demo->sendAndroidBroadcast();
 * $demo->sendAndroidFilecast();
 * $demo->sendAndroidGroupcast();
 * $demo->sendAndroidCustomizedcast();
 *
 * $demo->sendIOSBroadcast();
 * $demo->sendIOSUnicast();
 * $demo->sendIOSFilecast();
 * $demo->sendIOSGroupcast();
 * $demo->sendIOSCustomizedcast();
 */
