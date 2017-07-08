<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 14-6-10
 * Time: 上午10:03
 */

class Api{
    public static function weiboUrl(){
        include_once('API/weibo/config.php');
        include_once('API/weibo/saetv2.ex.class.php');
        $o = new SaeTOAuthV2( WB_AKEY , WB_SKEY );
        return $code_url = $o->getAuthorizeURL( WB_CALLBACK_URL );
    }

    public static function weiboCallback(){
        include_once('API/weibo/config.php');
        include_once('API/weibo/saetv2.ex.class.php');
        $o = new SaeTOAuthV2( WB_AKEY , WB_SKEY );
        $token = '';
        if (isset($_REQUEST['code'])) {
            $keys = array();
            $keys['code'] = $_REQUEST['code'];
            $keys['redirect_uri'] = WB_CALLBACK_URL;
            try {
                $token = $o->getAccessToken( 'code', $keys ) ;
            } catch (OAuthException $e) {

            }
        }
        if($token){
            return $token;
        }else{
            return 0;
        }
    }

    public static function getWeiboUser($reg=false){
        include_once( 'API/weibo/config.php' );
        include_once( 'API/weibo/saetv2.ex.class.php' );
        $c = new SaeTClientV2( WB_AKEY , WB_SKEY , $_SESSION['token']['access_token'] );
        $uid_get = $c->get_uid();
        $uid = $uid_get['uid'];
        $user_message = $c->show_user_by_id( $uid);//根据ID获取用户等基本信息
        if($reg){
            $c->update('打造中国传统文化优秀平台 ，共建全球华人精神家园 . http://sns.91ddcc.com 才府家园欢迎您的加入，为优秀的传统文化添砖加瓦！');
        }
        return $user_message;
    }

    public static function qqCallBack(){
        require_once("API/qq/qqConnectAPI.php");
        $qc = new QC();
        $token = '';
        $access_token = $qc->qq_callback();
        if($access_token){
            $token['access_token'] = $access_token;
            $token['uid'] = $qc->get_openid();
        }
        return $token;
    }

    public static function qqLogin(){
        require_once("API/qq/qqConnectAPI.php");
        $qc = new QC();
        $qc->qq_login();
    }

    public static function getQQUser(){
        require_once("API/qq/qqConnectAPI.php");
        $qc = new QC();
        $arr = $qc->get_user_info();
        $arr['openid']=$qc->get_openid();
        return $arr;
    }


    public static  function shortenSinaUrl($long_url){
        return 'http://t.cn/zWHICRh';
        include_once('API/weibo/config.php');
        $apiUrl='https://api.weibo.com/2/short_url/shorten.json?source='.WB_AKEY.'&url_long='.$long_url;
        $curlObj = curl_init();
        curl_setopt($curlObj, CURLOPT_URL, $apiUrl);
        curl_setopt($curlObj, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlObj, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curlObj, CURLOPT_HEADER, 0);
        curl_setopt($curlObj, CURLOPT_HTTPHEADER, array('Content-type:application/json'));
        $response = curl_exec($curlObj);
        curl_close($curlObj);
        $json = json_decode($response);
        print_r($json);
        return $json->urls[0]->url_short;
    }

    function expandSinaUrl($short_url){
        include_once('API/weibo/config.php');
        $apiUrl='https://api.weibo.com/2/short_url/expand.json?source='.WB_AKEY.'&url_short='.$short_url;
        $curlObj = curl_init();
        curl_setopt($curlObj, CURLOPT_URL, $apiUrl);
        curl_setopt($curlObj, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlObj, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curlObj, CURLOPT_HEADER, 0);
        curl_setopt($curlObj, CURLOPT_HTTPHEADER, array('Content-type:application/json'));
        $response = curl_exec($curlObj);
        curl_close($curlObj);
        $json = json_decode($response);
        return $json->urls[0]->url_long;
    }
    //根据坐标获取城市
    public static function getCity($lat,$lng){
        $apiUrl = 'http://api.jisuapi.com/geoconvert/coord2addr?lat='.$lat.'&lng='.$lng.'&type=baidu&appkey=4e4f82e520b55e2a';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $apiUrl);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $apiUrl);
        $return_str = curl_exec($curl);
        curl_close($curl);
        return json_decode($return_str,true);
    }

    //物流信息查询
    public static function getLogistics($number,$type='auto'){
        $apiUrl = 'http://api.jisuapi.com/express/query?appkey=6f320cc1e3e5b4cc&type='.$type.'&number='.$number.'';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $apiUrl);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $apiUrl);
        $return_str = curl_exec($curl);
        curl_close($curl);
        return json_decode($return_str,true);
    }
    //物流公司查询
    public static function getLogisticsCompany(){
        $apiUrl = 'http://api.jisuapi.com/express/type?appkey=6f320cc1e3e5b4cc';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $apiUrl);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $apiUrl);
        $return_str = curl_exec($curl);
        curl_close($curl);
        return json_decode($return_str,true);
    }
}