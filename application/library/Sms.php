<?php
/**
 * Created by PhpStorm.
 * User: ddcc
 * Date: 14-5-29
 * Time: 下午7:53
 */
class Sms{
    public static function send($mobile, $msg){
        $post_data = array();
        $post_data['account'] = iconv('GB2312', 'GB2312',"vip-ddcc");
        $post_data['pswd'] = iconv('GB2312', 'GB2312',"Tch123456");
        $post_data['mobile'] =$mobile;
        $post_data['msg']=mb_convert_encoding("$msg",'UTF-8', 'auto');
        $url='http://222.73.117.156/msg/HttpBatchSendSM?';
        $o="";
        foreach ($post_data as $k=>$v)
        {
            $o.= ($k=='msg')?"$k=".rawurlencode($v)."&":"$k=".urlencode($v)."&";
        }
        $post_data=$o.'needstatus=false';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $return_str = curl_exec($ch);
        curl_close($ch);
        /*// 发送URL
        $url = "http://122.144.130.35/Port/default.ashx?method=SendSms";
        // 用户名
        $username = "sdk_daidai";
        // 密码
        $password = "chuandao";
        // 指定发送手机号
        $phonelist = $mobile;
        // 发送字符串
        $data = 'username=' . $username . '&password=' . $password . '&phonelist=' . $phonelist . '&msg=' . rawurlencode($msg.'【代代传承】');

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $return_str = curl_exec($curl);
        curl_close($curl);*/
        return $return_str;
    }
    public static function sendToOverseas($mobile,$msg){
        $param='un=I9028715&pw=BIoFt7NnwE1a77&sm='.urlencode($msg).'&da='.$mobile.'&rd=15&rf=1&tf=3';
        $url='http://222.73.117.140:8044/mt?'.$param;//单发接口
        $ch=curl_init();
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_URL,$url);
        $result=curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}