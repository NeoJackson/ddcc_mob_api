<?php
//common.php配置包含:define('ENVIRONMENT', 'development');define('ENVIRONMENT_VAR', 'development');
include "/data/site/public_config/i.91ddcc.com/conf/common.php";
define('STATIC_FILE_VERSION',2016070602);
define('DOMAIN','91ddcc.com');
define('IMG_DOMAIN', 'http://img.'.DOMAIN.'/');
define('PUBLIC_DOMAIN', 'http://pub.'.DOMAIN.'/');
define('FM_DOMAIN','http://fm.'.DOMAIN);
define('WX_APPID','wx85b5863370bd19d0');
define('WX_MCHID','1250368801');
define('WX_KEY','daidaichuanchengchenyajuan151165');
define('ALI_PARTNER','2088221759813277');
define('ALI_SERVICE','mobile.securitypay.pay');
define('ALI_SELLERID','ddcc@91ddcc.com');
define('ALI_APPID','2016051901420910');
define('WX_PAY_DOMAIN','http://sns.91ddcc.com');
define('OPEN_DOMAIN','http://open.'.DOMAIN);
define('VIDEO_DOMAIN','http://video.'.DOMAIN.'/');
define('JPUSH_APPKEY','a3554f97f95a1355989729c3');
define('JPUSH_MASTERSECRET','7f788d5da7f1de0132cccfd8');
define('APP_VERSION','3.8');
//以下域名需要根据环境进行区分
if(ENVIRONMENT_VAR=='development'){//开发环境
    define('JPUSH_TYPE','false');
    define('DOMAIN_PARAMETER','d');
    define('PUSH_DOMAIN','tcp://192.168.2.113:7273');//聊天IP配置
    define('SERIAL_TIME','2017-04-18 00:00:00');//推广联盟记录时间
}elseif(ENVIRONMENT_VAR=='testing'){//测试环境
    define('JPUSH_TYPE','false');
    define('DOMAIN_PARAMETER','t');
    define('PUSH_DOMAIN','tcp://112.124.29.125:7273');//聊天IP配置
    define('SERIAL_TIME','2017-04-18 00:00:00');//推广联盟记录时间
}elseif(ENVIRONMENT_VAR=='preview'){//预发布环境
    define('JPUSH_TYPE','true');
    define('DOMAIN_PARAMETER','p');
    define('PUSH_DOMAIN','tcp://120.55.148.183:7273');//聊天IP配置
    define('SERIAL_TIME','2017-06-01 00:00:00');//推广联盟记录时间
}elseif(ENVIRONMENT_VAR=='product'){//生产真实环境
    define('JPUSH_TYPE','true');
    define('DOMAIN_PARAMETER','');
    define('PUSH_DOMAIN','tcp://120.55.148.183:7273');//聊天IP配置
    define('SERIAL_TIME','2017-06-01 00:00:00');//推广联盟记录时间
}

define('M_DOMAIN','http://'.DOMAIN_PARAMETER.'m.'.DOMAIN);
define('I_DOMAIN','http://'.DOMAIN_PARAMETER.'i.'.DOMAIN);
define('STATIC_DOMAIN','http://'.DOMAIN_PARAMETER.'static.91ddcc.cn/i');
define('D_DOMAIN','http://'.DOMAIN_PARAMETER.'d.'.DOMAIN);
define('SNS_DOMAIN','http://'.DOMAIN_PARAMETER.'sns.'.DOMAIN);

define("APPLICATION_PATH",  dirname(dirname(__FILE__)));
define("VIEWS_PATH", APPLICATION_PATH."/application/views");
//$application = new Yaf_Application('/data/site/public_config/i.91ddcc.com/conf/application.ini');
$application = new Yaf_Application(APPLICATION_PATH . "/conf/application.ini");
$application->bootstrap()->run();