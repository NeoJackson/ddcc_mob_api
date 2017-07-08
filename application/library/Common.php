<?php if (!defined('APPLICATION_PATH')) exit('No direct script access allowed');

/**
 * 公用基础类
 */
class Common
{

    /**
     * 返回统一的json数据格式
     * @param int $status ：状态码
     * @param string $message : 提示信息
     * @param mixed $data : 对象，数组，字符串等数据
     * @return string
     */
    public static function echoAjaxJson($status, $message = '', $data = '')
    {
        header("Access-Control-Allow-Origin:http://m.91ddcc.com");
        $result['status'] = $status;
        $result['message'] = $message;
        $result['data'] = $data;
        echo json_encode($result);
        exit();
    }

    public static function auth($data){
        $appkey = 'app';
        $appsecret = 'fc7173644746d546e8650b187ff7b9c3';
        $time = time();
        $appsecret = md5(md5($appsecret).$time);
        $data['appkey'] = $appkey;
        $data['appsecret'] = $appsecret;
        $data['apptime'] = $time;
        $data['auth'] = md5(http_build_query($data));
        return $data;
    }
    /**
     * 模拟提交参数，支持https提交 可用于各类api请求
     * @param string $url ： 提交的地址
     * @param array $data :POST数组
     * @param string $method : POST/GET，默认GET方式
     * @return mixed
     */
    public static function http($url, $data = '', $method = 'GET')
    {
        $data = self::auth($data);
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
            if ($data != '') {
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data)); // Post提交的数据包
            }
        }
        curl_setopt($curl, CURLOPT_TIMEOUT, 2); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $tmpInfo = curl_exec($curl); // 执行操作
        curl_close($curl); // 关闭CURL会话
        return $tmpInfo; // 返回数据
    }
    //发心境不可输入英文，但内链可以
    public static function contentReplace($content){
        $reg = '/((http\:\/\/)?(www|sns|m|d|dsns|dm|dd|tsns|tm|td|psns|pm|pd)\.91ddcc\.com([\w\?\.=#&:\/]*)?)/';
        $content = strtolower($content);
        preg_match_all($reg,$content,$matches);
        $content = preg_replace($reg,'<链接>',$content);
        if(preg_match('/[A-Za-z]{1,}/',$content)) {
            return false;
        }
        preg_match_all('/<链接>/',$content,$links);
        foreach($links[0] as $k=>$v) {
            $content = preg_replace('/<链接>/',$matches[0][$k],$content,1);
        }
        return $content;
    }

    //连续空格处理单一空格
    public static function contentSpace($content){
        $tag_pattern = "/\s+/";
        $content = preg_replace($tag_pattern,' ',$content);
        return $content;
    }

    //php获取当前访问的完整url地址
    function getCurUrl(){
        $url='http://';
        if(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']=='on'){
            $url='https://';
        }
        if($_SERVER['SERVER_PORT']!='80'){
            $url.=$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].$_SERVER['REQUEST_URI'];
        }else{
            $url.=$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
        }
        return $url;
    }

    /**
     * 获取UTF-8字符串的长度
     * @param string $string ： utf-8编码的字符串
     * @return int
     */
    public static function utf8_strlen($string = null)
    {
        // 将字符串分解为单元
        preg_match_all("/./us", $string, $match);
        // 返回单元个数
        return count($match[0]);
    }
    /*
     * @ name 重定向到指定页面
     */
    public static function redirect($url){
        header("Location:".$url);
        exit();
    }
    /*
     * @name 字符串截取函数
     */
    public static function msubstr($str, $start=0, $length, $charset="utf-8", $suffix=true)
    {
        if(function_exists("mb_substr")){
            $num = mb_strlen($str,$charset);
            if($num > $length && $suffix){
                return mb_substr($str, $start, $length, $charset)."...";
            }
            else
                return mb_substr($str, $start, $length, $charset);
        }
        elseif(function_exists('iconv_substr')) {
            $num = mb_strlen($str,$charset);
            if($num > $length && $suffix){
                return iconv_substr($str,$start,$length,$charset)."...";
            }
            else
                return iconv_substr($str,$start,$length,$charset);
        }
        $re['utf-8']   = "/[x01-x7f]|[xc2-xdf][x80-xbf]|[xe0-xef][x80-xbf]{2}|[xf0-xff][x80-xbf]{3}/";
        $re['gb2312'] = "/[x01-x7f]|[xb0-xf7][xa0-xfe]/";
        $re['gbk']    = "/[x01-x7f]|[x81-xfe][x40-xfe]/";
        $re['big5']   = "/[x01-x7f]|[x81-xfe]([x40-x7e]|xa1-xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("",array_slice($match[0], $start, $length));
        if($suffix) return $slice."…";
        return $slice;
    }

    /*
     * @name 将内容中的表情标签转换成表情图片显示
     * type 0 需要转表情  1 不需要转表情
     */
    public static function showEmoticon($content,$type){
        if($type==0){
            $emoticon = array("[微笑]", "[撇嘴]", "[大爱]", "[发呆]", "[流泪]", "[害羞]", "[闭嘴]", "[睡]", "[大哭]", "[尴尬]", "[发怒]", "[调皮]", "[呲牙]", "[惊讶]", "[难过]", "[冷汗]", "[抓狂]", "[呕吐]", "[偷笑]", "[可爱]", "[白眼]", "[傲慢]", "[饥饿]", "[困]", "[惊恐]", "[流汗]", "[憨笑]", "[大兵]", "[奋斗]", "[合掌]", "[疑问]", "[嘘]", "[晕]", "[折磨]", "[飞吻]", "[敲打]", "[再见]", "[擦汗]", "[抠鼻]", "[糗大了]", "[坏笑]", "[左哼哼]", "[右哼哼]", "[哈欠]", "[鄙视]", "[委屈]", "[快哭了]", "[阴险]", "[亲亲]", "[吓]", "[可怜]", "[拥抱]", "[月亮]", "[太阳]", "[鼓掌]", "[示爱]", "[爱情]", "[玫瑰]", "[西瓜]", "[咖啡]", "[饭]", "[爱心]", "[强]", "[弱]", "[握手]", "[胜利]", "[抱拳]", "[挑衅]", "[好]", "[不]","[拱手]", "[不行]", "[不要]", "[得意]", "[行]", "[合十]", "[来]", "[抬举]", "[喜爱]", "[耶]", "[赞]",
                "[三哥愁]", "[三哥不屑]", "[三哥大哭]", "[三哥害羞]", "[三哥坏笑]", "[三哥惊吓]", "[三哥困]", "[三哥乐]", "[三哥怒]", "[三哥亲]", "[三哥色]", "[三哥喜]", "[三哥晕]", "[三哥抓狂]",
                "[三哥鬼脸]","[三哥拍手]","[三哥汗]","[易姐愁]", "[易姐不屑]", "[易姐大哭]", "[易姐害羞]", "[易姐坏笑]", "[易姐惊吓]", "[易姐困]", "[易姐乐]", "[易姐怒]", "[易姐亲]", "[易姐色]",
                "[易姐喜]", "[易姐晕]", "[易姐汗]","[易姐抓狂]","[易姐拍手]","[易姐鬼脸]","[小花愁]", "[小花不屑]", "[小花大哭]", "[小花害羞]", "[小花坏笑]", "[小花惊吓]", "[小花困]", "[小花乐]",
                "[小花怒]", "[小花亲]", "[小花色]", "[小花喜]", "[小花汗]", "[小花晕]","[小花抓狂]","[小花拍手]","[小花鬼脸]","[小子愁]", "[小子不屑]", "[小子大哭]", "[小子害羞]", "[小子坏笑]",
                "[小子惊吓]", "[小子困]", "[小子乐]", "[小子怒]", "[小子亲]", "[小子色]", "[小子喜]", "[小子拍手]", "[小子晕]","[小子汗]","[小子抓狂]","[小子鬼脸]");
            $img = array('<img src="'.PUBLIC_DOMAIN.'qq_1.gif"  width="24" height="24"  >','<img src="'.PUBLIC_DOMAIN.'qq_2.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_3.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_4.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_5.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_6.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_7.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_8.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_9.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_10.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_11.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_12.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_13.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_14.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_15.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_16.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_17.gif" width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_18.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_19.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_20.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_21.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_22.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_23.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_24.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_25.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_26.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_27.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_28.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_29.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_30.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_31.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_32.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_33.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_34.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_35.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_36.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_37.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_38.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_39.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_40.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_41.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_42.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_43.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_44.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_45.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_46.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_47.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_48.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_49.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_50.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_51.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_52.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_53.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_54.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_55.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_56.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_57.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_58.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_59.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_60.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_61.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_62.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_63.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_64.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_65.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_66.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_67.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_68.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_69.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'qq_70.gif"  width="24" height="24" >','<img src="'.PUBLIC_DOMAIN.'yijia_fo_0.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_fo_1.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_fo_2.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_fo_3.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_fo_4.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_fo_5.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_fo_6.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_fo_7.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_fo_8.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_fo_9.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_fo_10.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_0.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_1.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_2.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_3.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_4.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_5.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_6.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_7.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_8.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_9.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_10.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_11.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_12.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_13.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_14.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_15.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_san_16.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_0.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_1.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_2.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_3.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_4.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_5.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_6.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_7.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_8.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_9.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_10.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_11.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_12.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_13.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_14.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_15.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_yi_16.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_0.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_1.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_2.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_3.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_4.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_5.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_6.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_7.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_8.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_9.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_10.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_11.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_12.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_13.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_14.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_15.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_hua_16.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_zi_0.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_zi_1.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_zi_2.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_zi_3.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_zi_4.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_zi_5.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_zi_6.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_zi_7.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_zi_8.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_zi_9.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_zi_10.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_zi_11.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_zi_12.gif"  width="30" height="30" >','<img src="'.PUBLIC_DOMAIN.'yijia_zi_13.gif" width="30" height="30">','<img src="'.PUBLIC_DOMAIN.'yijia_zi_14.gif" width="30" height="30">','<img src="'.PUBLIC_DOMAIN.'yijia_zi_15.gif" width="30" height="30">','<img src="'.PUBLIC_DOMAIN.'yijia_zi_16.gif" width="30" height="30">');
            $content = str_replace($emoticon,$img,$content);
            $ids = $search_arr = $replace_arr = array();
            preg_match_all('/@(\d+)@/',$content,$ids);
            if(isset($ids[1])){
                $userModel = new UserModel();
                foreach($ids[1] as $val){
                    $user = $userModel->getUserData($val);
                    if($user!=(object)array()){
                        $search_arr[] = '@'.$user['uid'].'@';
                        $replace_arr[] = '@'.$user['nick_name'];
                    }
                }
                $content = str_replace($search_arr,$replace_arr,$content);
            }
        }elseif($type ==1){
            $ids = $search_arr = $replace_arr = array();
            preg_match_all('/@(\d+)@/',$content,$ids);
            if(isset($ids[1])){
                $userModel = new UserModel();
                foreach($ids[1] as $val){
                    $user = $userModel->getUserData($val);
                    if($user!=(object)array()){
                        $search_arr[] = '@'.$user['uid'].'@';
                        $replace_arr[] = '<uid>'.$user['uid'].'/'.$user['nick_name'].'</uid>';
                    }
                    //$replace_arr[] = '@'.$user['nick_name'];
                }
                $content = str_replace($search_arr,$replace_arr,$content);
            }
        }
        return $content;
    }


    public static function isLogin($data,$type=''){
        if(!isset($data['token'])||!$data['token']){
            Common::echoAjaxJson(403,'未登录');
        }
        $tokenModel = new TokenModel();
        $data = $tokenModel->verifyToken($data['token']);
        if(!$data){
            Common::echoAjaxJson(403,'未登录');
        }
        $userModel = new UserModel();
        if($type){
            $u_info = $userModel->getUserStatusByUid($data['uid']);
            if($u_info['status']==2){
                Common::echoAjaxJson(808,'由于您的违规操作，帐号已经被禁用，如有问题请直接联系客服：13012888193');
            }
            $time = time();
            if($u_info['en_talk_time']&& $time < strtotime($u_info['en_talk_time'])){
                $modify_time = strtotime($u_info['en_talk_time']);
                $c_time = $modify_time - $time;
                if($c_time>3600*24){
                    $day = round($c_time/(3600*24));
                    $hours = floor(($c_time-(3600*24*$day))/3600);
                    Common::echoAjaxJson(808,'系统检查到您的帐号存在操作风险，禁言还剩'.$day.'天'.$hours.'小时。');
                }elseif($c_time<3600*24&&$c_time>3600){
                    $hours = floor($c_time/3600);
                    Common::echoAjaxJson(808,'系统检查到您的帐号存在操作风险，禁言还剩'.$hours.'小时。');
                }elseif($c_time<3600){
                    $minute = floor($c_time/60);
                    Common::echoAjaxJson(808,'系统检查到您的帐号存在操作风险，禁言还剩'.$minute.'分钟。');
                }
            }
        }
        $userInfo = $userModel->getUserData($data['uid']);
        $userRcImToken = $userModel->getTokenRcIm($data['uid']);
        if($userRcImToken){
            $userInfo['rcToken'] = $userRcImToken['token'];
        }else{
            $userInfo['rcToken'] = '';
        }
        return $userInfo;
    }
    //APP接口测试
    public static function verify($parameters,$uri){
        if(DOMAIN_PARAMETER=='d'||DOMAIN_PARAMETER=='t'){
            $auth_data = $parameters;
            $auth_data['appkey'] = 'ddcc_iOS';
            $auth_data['appsecret'] = 'e756938fa19af5304b8481a471af31a1';
            $auth_data['time'] = time();
            ksort($auth_data,SORT_STRING);
            $new_string = http_build_query($auth_data);
            $auth = md5(md5($new_string).$auth_data['time']);
            $post_data = $parameters;
            $post_data['appkey'] = $auth_data['appkey'];
            $post_data['time'] = $auth_data['time'];
            $post_data['auth'] = $auth;
            echo self::http(I_DOMAIN.$uri,$post_data,'POST');
        }
    }
    //正则匹配所有标签中的样式
    public static function replaceStyle($content){
        preg_match_all('/<[img|IMG].*?src=[\'|\"(.+?)\"].*?[\/]?>/im', $content, $imgList);
        $imgNewList = array();
        $imgList = isset($imgList[0]) && $imgList[0] ? $imgList[0] : array();
        if($imgList){
            foreach($imgList as $val){
                $data = str_replace("style", "data-style", $val);
                $data = str_replace("width", "data-width", $data);
                $data = str_replace("height", "data-height", $data);
                $imgNewList[] = $data;
            }
            $content = str_replace($imgList,$imgNewList,$content);
        }
        return $content;
    }
//    public static function auth($data){
//        $app = array(
//            'ddcc_iOS' => 'e756938fa19af5304b8481a471af31a1',
//            'ddcc_Android' => '5188db90fca227e4448451d814efd4fb',
//        );
//        if(!isset($data['appkey']) || !isset($data['time']) || !isset($data['auth'])){
//            Common::echoAjaxJson(201,'Authorization failed');
//        }
//        $appkey = $data['appkey'];
//        $time = $data['time'];
//        $auth = $data['auth'];
//        if(!isset($app[$appkey])){
//            Common::echoAjaxJson(202,'Authorization failed');
//        }
//        if($time+60 < time()){
//            Common::echoAjaxJson(203,'Authorization failed');
//        }
//        $data['appsecret'] = $app[$appkey];
//        unset($data['auth']);
//        ksort($data,SORT_STRING);
//        if(md5(md5(http_build_query($data)).$time) !=  $auth){
//            Common::echoAjaxJson(204,'Authorization failed');
//        }
//    }
    public static function getPwdType($pwd){
        $score=0;
        $pwd_type=0;
        if(strlen($pwd) <4 ){
            $score+= 5;
        }else if(strlen($pwd)<7){
            $score+= 10;
        }else{
            $score+= 25;
        }
        preg_match_all("/[0-9]/",$pwd,$numCount);
        $num =count($numCount[0]);
        if($num>0&&$num<3){
            $score+=10;
        }else if($num>2){
            $score+=20;
        }
        preg_match_all("/[a-z]/",$pwd,$charUpCount);
        preg_match_all("/[A-Z]/",$pwd,$charLowCount);
        $charUp = count($charUpCount[0]);
        $charLow = count($charLowCount[0]);
        if(($charUp>0&&$charLow==0)||($charUp==0&&$charLow>0)){
            $score+=10;
        }else if($charUp>0&&$charLow>0){
            $score+=20;
        }
        preg_match_all("/[!@#\$%\^&\*%\(\)_\+=]/",$pwd,$markCount);
        $mark = count($markCount[0]);
        if($mark==1){
            $score+=10;
        }else if($mark>1){
            $score+=25;
        }
        preg_match_all("/[a-zA-Z]/",$pwd,$charCount);
        $char = count($charCount[0]);
        if($num>0&&$char>0){
            $score+=2;
        }

        if($num>0&&$char>0&&$mark>0){
            $score+=3;
        }

        if($num>0&&$charUp>0&&$charLow>0&&$mark>0){
            $score+=5;
        }
        if($score< 33){
            $pwd_type = 1;
        }else if($score>33&&$score<=66){
            $pwd_type = 2;
        }else if($score>= 66){
            $pwd_type = 3;
        }
        return $pwd_type;
    }
    /*
    * @name 日期时间处理
    * $type 0默认全站样式 1特别情况
    */
    public static function show_time($show_time,$type=0){
        if(strlen($show_time) == 19){
            $show_time = strtotime($show_time);
        }
        $dur = time() - $show_time;
        if($dur < 60){
            return '刚刚';
        }else{
            if($dur < 3600){
                return floor($dur/60).'分钟前';
            }else{
                $current_time = date("Y-m-d",time());
                $date = date("Y-m-d",$show_time);
                if($current_time == $date){
                    if($type==0){
                        return "今天 ".date("H:i",$show_time);
                    }elseif($type==1){
                        return date("H:i",$show_time);
                    }
                }else{
                    $current_year = date("Y",time());
                    $year = date("Y",$show_time);
                    if($current_year == $year){//一年内
                        if($type==0){
                            return date("m-d H:i",$show_time);
                        }elseif($type==1){
                            return date("m-d",$show_time);
                        }
                    }else{
                        if($type==0){
                            return date("Y-m-d H:i",$show_time);
                        }elseif($type==1){
                            return date("Y-m-d",$show_time);
                        }
                    }
                }
            }
        }

    }

    /*
 * @name 日期时间处理
 * $type 0默认全站样式 1特别情况
 */
    public static function app_show_time($show_time,$type=0){
        if(strlen($show_time) == 19){
            $show_time = strtotime($show_time);
        }
        $dur = time() - $show_time;
        if($dur < 60){
            return '刚刚';
        }else{
            if($dur < 3600){
                return floor($dur/60).'分钟前';
            }else{
                $current_time = date("Y-m-d",time());
                $date = date("Y-m-d",$show_time);
                if($current_time == $date){
                    if($type==0){
                        $h = floor($dur/(60*60));
                        return $h.'小时前 ';
                    }elseif($type==1){
                        return date("H:i",$show_time);
                    }
                }else{
                    $current_year = date("Y",time());
                    $year = date("Y",$show_time);
                    if($current_year == $year){//一年内
                        if($type==0){
                            if($dur < 60 * 60 * 24 * 31){
                                $d = floor($dur/(60*60*24));
                                $d = $d ? $d : 1;
                                return $d.'天前';
                            }else{
                                return date("m-d H:i",$show_time);
                            }
                        }elseif($type==1){
                            return date("m-d",$show_time);
                        }
                    }else{
                        if($type==0){
                            return date("Y-m-d H:i",$show_time);
                        }elseif($type==1){
                            return date("Y-m-d",$show_time);
                        }
                    }
                }
            }
        }

    }

    /**
     * 显示图片
     * @param $img
     * @param int $type 1 用户头像 2 驿站头像 3相册封面 4 其他图片 5帖子日志抓取图片
     * @param int $width
     * @param int $height
     * @return string
     */
    public static function show_img($img,$type=1,$width=0,$height=0,$imageView=0){
        $suffix = '';
        $connect = ( false === strpos($img,'?') ) ? '?' : '&';
        if($width > 0 && $height > 0){
            if($type==5 || $type==4 ||  $imageView == 10){
                //高级裁剪，缩放后左右居中取中裁剪
                //$suffix = "${connect}imageMogr2/thumbnail/!${width}x${height}r/gravity/Center/crop/${width}x${height}";
                $suffix = "${connect}imageView2/".$imageView."/w/$width/h/$height";
            }else{
                $suffix = "${connect}imageView2/".$imageView."/w/$width/h/$height";
            }
        }
        if($img){
            if($type==5 && !strstr($img,IMG_DOMAIN)){
                return $img;exit;
            }
            $img = str_replace(IMG_DOMAIN,'',$img);
            return IMG_DOMAIN.$img.$suffix;
        }
        if($type == 1){
            return PUBLIC_DOMAIN.'default_avatar.jpg';
        }
        return PUBLIC_DOMAIN.'default_avatar.jpg';
    }

    public static function show_type($type){
        $type_arr = array(
            1=>'心境',
            2=>'照片',
            3=>'日志',
            4=>'帖子'
        );
        return $type_arr[$type];
    }

    public static function birthdayToAge($birthday){
        $age = date('Y', time()) - date('Y', strtotime($birthday)) - 1;
        if (date('m', time()) == date('m', strtotime($birthday))){
            if (date('d', time()) > date('d', strtotime($birthday))){
                $age++;
            }
        }elseif (date('m', time()) > date('m', strtotime($birthday))){
            $age++;
        }
        return $age;
    }

    //根据经验值获得用户等级信息
    public static function getUserLevel($exp){
        $levelInfo = array(
            array('id'=>1,'name'=>'正品初级','exp'=>0,'stage_count'=>1),
            array('id'=>2,'name'=>'正品中级','exp'=>100,'stage_count'=>1),
            array('id'=>3,'name'=>'正品高级','exp'=>300,'stage_count'=>1),
            array('id'=>4,'name'=>'尚品初级','exp'=>900,'stage_count'=>2),
            array('id'=>5,'name'=>'尚品中级','exp'=>2700,'stage_count'=>2),
            array('id'=>6,'name'=>'尚品高级','exp'=>8100,'stage_count'=>2),
            array('id'=>7,'name'=>'尊品初级','exp'=>24300,'stage_count'=>3),
            array('id'=>8,'name'=>'尊品中级','exp'=>72900,'stage_count'=>3),
            array('id'=>9,'name'=>'尊品高级','exp'=>218700,'stage_count'=>3),
        );
        $data = array();
        $exp = (int)$exp;
        for($i = 0;$i < count($levelInfo);$i ++){
            if($i < (count($levelInfo) - 1) && $exp >= (int)$levelInfo[$i]['exp'] && $exp < (int)$levelInfo[$i + 1]['exp']){
                $data['level_id'] = $levelInfo[$i]['id'];
                $data['next_difference_exp'] = (int)$levelInfo[$i+1]['exp'] - $exp;//离下一级别经验差值
                $data['next_exp'] = (int)$levelInfo[$i+1]['exp']; //下一级别经验值
                $data['prev_exp'] = (int)$levelInfo[$i]['exp'];//上一级别经验值
                $data['level_name'] = $levelInfo[$i]['name']; //文品
                $data['stage_count'] = $levelInfo[$i]['stage_count'];
                $data['exp_percent'] = floor(($exp / $data['next_exp']) * 100);
                break;
            }else if($i == (count($levelInfo) - 1)){
                $data['level_id'] = $levelInfo[$i]['id'];
                $data['next_difference_exp'] = 0;
                $data['next_exp'] = (int)$levelInfo[$i]['exp'];
                $data['prev_exp'] = (int)$levelInfo[$i]['exp'];
                $data['level_name'] = $levelInfo[$i]['name'];
                $data['stage_count'] = $levelInfo[$i]['stage_count'];
                $data['exp_percent'] = 100;
            }
        }
        return $data;
    }

    public static function atUser($uid,$content){
        preg_match_all('/@([^@\s\\pP]*)/u',$content,$matches);
        $search_arr = $replace_arr = $at_arr = array();
        if($matches[1]){
            $userModel = new UserModel();
            foreach($matches[1] as $val){
                if($val){
                    $user = $userModel->getUserByNickName($val);
                    if($user){
                        $search_arr[] = "@".$user['nick_name'];
                        $replace_arr[] = "@".$user['uid']."@";
                        $at_arr[] = $user['uid'];
                        $userModel->addAtUser($uid,$at_arr);
                    }
                }
            }
        }
        return array(str_replace($search_arr,$replace_arr,$content),array_unique($at_arr));
    }

    /**
     * 过滤HTML代码空格,回车换行符的函数
     */
    public static function deleteHtml($str){
        $str = trim($str);
        $str = strip_tags($str,"");
        $str = strtr($str, array('　'=>''));
        $str = str_replace(array("\r\n", "\r", "\n","\t","&nbsp;"," "), "", $str);
        return $str;
    }

    /**
     * 过滤HTML代码空格,回车换行符的函数
     */
    public static function deleteStageHtml($str){
        $str = trim($str);
        $str = strip_tags($str,"");
        $str = str_replace(array("\r\n", "\r", "\n","\t","&nbsp;"," "), "", $str);
        return $str;
    }


    // type = 0 过滤外链，自己网站地址加上链接
    // type = 1 给所有网址加上链接
    public static function linkReplace($content,$type=0) {
        //提取替换出所有的IMG标签（统一标记<{img}>）
        /*preg_match_all('/<img[^>]+>/im', $content, $imgList);*/
        preg_match_all('/<[img|IMG].*?src=[\'|\"(.+?)\"].*?[\/]?>/im', $content, $imgList);
        $imgList = $imgList[0];
        /*$str = preg_replace('/<img[^>]+>/im', '<{img}>', $content);*/
        $str = preg_replace('/<[img|IMG].*?src=[\'|\"(.+?)\"].*?[\/]?>/im', '<{img}>', $content);
        preg_match_all('/<embed[^>]*>(<\/embed>)?/',$str,$embedList);
        $embedList = $embedList[0];
        $str = preg_replace('/<embed[^>]*>(<\/embed>)?/','<{embed}>',$str);

        /***/
        preg_match_all('/<span class="editor-video-data"[^>]*>(<\/span>)?/', $str, $divList);
        $divList = $divList[0];
        $str = preg_replace('/<span class="editor-video-data"[^>]*>(<\/span>)?/', "<{editor-video-data}>", $str);
        /**/
        //提取替换出所有A标签（统一标记<{link}>）
        preg_match_all('/<a[^>]*?href=".*?"[^>]*?>.*?<\/a>/i', $str, $linkList);
        $linkList = $linkList[0];
        $str = preg_replace('/<a[^>]*?href=".*?"[^>]*?>.*?<\/a>/i', '<{link}>', $str);

        $url_reg = '/((http|ftp|https)\:\/\/)?([\w-]+\.)+[\w-]+(\/[\w\?\.=#&:\/]*)?/';
        preg_match_all($url_reg,$str,$matches);
        $arrLen = count($matches[0]);
        $str = preg_replace($url_reg, '<{url}>', $str);
        $domain_reg = '/((http\:\/\/)?([a-zA-Z]{1,10})\.91(ddcc|DDCC)\.(com|COM)([\w\?\.=#&:\/]*)?)/';
        if($type == 0){
            for($i = 0; $i < $arrLen; $i ++) {
                if(filter_var($matches[0][$i],FILTER_VALIDATE_URL)) {
                    if(!preg_match($domain_reg,$matches[0][$i])) {
                        $matches[0][$i] = '******';
                    }
                }
                if(preg_match($domain_reg,$matches[0][$i])) {
                    if(preg_match('/^http\:\/\//',$matches[0][$i])) {
                        $matches[0][$i] = '<a class="blue" onclick="event.stopPropagation?event.stopPropagation():event.cancelBubble=true;" href="'.$matches[0][$i].'">'.$matches[0][$i].'</a>';
                    } else {
                        $matches[0][$i] = '<a class="blue" onclick="event.stopPropagation?event.stopPropagation():event.cancelBubble=true;" href="http://'.$matches[0][$i].'">'.$matches[0][$i].'</a>';
                    }
                }
                $str = preg_replace('/<{url}>/', $matches[0][$i], $str, 1);
            }
        }else if($type == 1){
            for($i = 0; $i < $arrLen; $i ++) {
                if(preg_match('/^http\:\/\//',$matches[0][$i])) {
                    $matches[0][$i] = '<a class="blue" onclick="event.stopPropagation?event.stopPropagation():event.cancelBubble=true;" href="'.$matches[0][$i].'">'.$matches[0][$i].'</a>';
                } else {
                    $matches[0][$i] = '<a class="blue" onclick="event.stopPropagation?event.stopPropagation():event.cancelBubble=true;" href="http://'.$matches[0][$i].'">'.$matches[0][$i].'</a>';
                }
                $str = preg_replace('/<{url}>/', $matches[0][$i], $str, 1);
            }
        }

        //还原A统一标记为原来的A标签
        $arrLen = count($linkList);
        for($i = 0; $i < $arrLen; $i++) {
            preg_match($domain_reg,$linkList[$i],$matches);
            if(!$matches) {
                $linkList[$i] = preg_replace('/<a[^>]*?href=".*?"[^>]*?>(.*?)<\/a>/i', '$1', $linkList[$i]);
                if(filter_var($linkList[$i],FILTER_VALIDATE_URL)) {
                    if(!preg_match($domain_reg,$linkList[$i])) {
                        $linkList[$i] = '******';
                    }
                }
                if(preg_match($domain_reg,$linkList[$i])) {
                    if(preg_match('/^http\:\/\//',$linkList[$i])) {
                        $linkList[$i] = '<a class="blue" onclick="event.stopPropagation?event.stopPropagation():event.cancelBubble=true;" href="'.$linkList[$i].'">'.$linkList[$i].'</a>';
                    } else {
                        $linkList[$i] = '<a class="blue" onclick="event.stopPropagation?event.stopPropagation():event.cancelBubble=true;" href="http://'.$linkList[$i].'">'.$linkList[$i].'</a>';
                    }
                }
            }
            $str = preg_replace('/<{link}>/', $linkList[$i], $str, 1);
        }

        //还原IMG统一标记为原来的IMG标签
        $arrLen2 = count($imgList);
        for($i = 0; $i < $arrLen2; $i++) {
            $str = preg_replace('/<{img}>/', $imgList[$i], $str, 1);
        }
        //还原embed统一标记为原来的A标签
        $arrLen1 = count($embedList);
        for($i = 0; $i < $arrLen1; $i ++) {
            $str = preg_replace('/<{embed}>/', $embedList[$i], $str, 1);
        }
        //还原<p class='editor-video-data' 的充溢标记为原来的A标签
        $arrLen3 = count($divList);
        for($i = 0; $i < $arrLen3; $i ++) {
            $str = preg_replace("/<{editor-video-data}>/", $divList[$i], $str, 1);
        }
        return $str;
    }

    //正则匹配抓取内容里图片
    public static function pregMatchImg($content){
        $preg = "/<img(.*?\s)src=(\'|\")(http.*?)(\'|\")(.*?[\/]?)>/i";
        preg_match_all($preg,$content,$img_arr);
        $new_img = array();
        foreach($img_arr[3] as $key=>$val){
            $is_exist_img = strstr($val,PUBLIC_DOMAIN);//判断是否包含表情图片地址
            if(!$is_exist_img){
                $new_img[] = $img_arr[3][$key];
            }
        }
        $img_arr[3] = $new_img;
        return $img_arr;
    }

    //处理内容里图片
    public static function setContentImg($content){
        $img_arr = self::pregMatchImg($content);
        if($img_arr){
            $new_img_arr = array();
            $image = new Image();
            foreach($img_arr[3] as $key=>$val){
                $is_exist = strstr($val,IMG_DOMAIN);//判断图片地址是否包含自己的图片地址
                $is_exist_img = strstr($val,PUBLIC_DOMAIN);//判断是否包含表情图片地址
                if(!$is_exist && !$is_exist_img){
                    $img = $image->fetch($val);
                    if($img){
                        $new_img_arr[$key] = $img;
                    }else{
                        unset($img_arr[3][$key]);
                    }
                }else{
                    unset($img_arr[3][$key]);
                }
            }
            if($img_arr[3]){
                $content = str_replace($img_arr[3],$new_img_arr,$content);
            }
        }
        return $content;
    }

    //判断是否存在非法词
    public static function badWord($word){
        if(!$word){
            return false;
        }
        if(strstr(trim($word),'代代') || strstr(trim($word),'子午道') || strstr(trim($word),'才府')){
            return true;
        }
        $sphinx = new SphinxClient();
        $config = Yaf_Registry::get("config");
        $host = $config->search->index->host;
        $port = $config->search->index->port;
        $sphinx->SetServer($host, $port);
        $sphinx->SetConnectTimeout(1);
        $word_arr = $sphinx->BuildKeywords($word,'ddcc',false);
        $hasBadWord = false;
        if($word_arr){
            $wordModel = new WordModel();
            foreach($word_arr as $key=>$val){
                $hasBadWord = $wordModel->hasWord($val['tokenized']);
                if($hasBadWord){
                    break;
                }
            }
        }
        return $hasBadWord;
    }
    //把全角字符串转换为半角字符串函数
    public static function angleToHalf($str){
        $arr = array('０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
            '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
            'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E',
            'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
            'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O',
            'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
            'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y',
            'Ｚ' => 'Z', 'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd',
            'ｅ' => 'e', 'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i',
            'ｊ' => 'j', 'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n',
            'ｏ' => 'o', 'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's',
            'ｔ' => 't', 'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x',
            'ｙ' => 'y', 'ｚ' => 'z',
            // '（' => '(', '）' => ')', '〔' => '[', '〕' => ']', '【' => '[',
            // '】' => ']', '〖' => '[', '〗' => ']', '“' => '[', '”' => ']',
            // '‘' => '[', '’' => ']', '｛' => '{', '｝' => '}', '《' => '<', '》' => '>',
            // '％' => '%', '＋' => '+', '—' => '-', '－' => '-', '～' => '~',
            //'：' => ':', '。' => '.', '、' => ',', '，' => '.', '、' => '.',
            //'；' => ',', '？' => '?', '！' => '!', '…' => '-', '‖' => '|',
            //'”' => '"', '’' => '`', '‘' => '`', '｜' => '|', '〃' => '"',
            '　' => ' ');
        return strtr($str, $arr);
    }
    //根据当前时间戳获取本周时间戳
    public static function getWeekDate(){
        $year = date("Y");
        $month = date("m");
        $day = date('w');
        $nowMonthDay = date("t");
        $firstday = date('d') - $day;
        if(substr($firstday,0,1) == "-"){
            $firstMonth = $month - 1;
            $lastMonthDay = date("t",$firstMonth);
            $firstday = $lastMonthDay - substr($firstday,1);
            $time_start = date('ymd',strtotime($year."-".$firstMonth."-".$firstday));
        }else{
            $time_start = date('ymd',strtotime($year."-".$month."-".$firstday));
        }
        $lastday = date('d') + (7 - $day);
        if($lastday > $nowMonthDay){
            $lastday = $lastday - $nowMonthDay;
            $lastMonth = $month + 1;
            $time_end = date('ymd',strtotime($year."-".$lastMonth."-".$lastday));
        }else{
            $time_end = date('ymd',strtotime($year."-".$month."-".$lastday));
        }
        $time = array();
        for($i=$time_start;$i<=$time_end;$i++){
          $time[] = $i;
        }
        return $time;
    }
    //根据当前时间戳获取本月时间戳
    public static function getMonthDate(){
        $year = date("Y");
        $month = date("m");
        $allday = date("t");
        $time_start = date('ymd',strtotime($year."-".$month."-1"));
        $time_end = date('ymd',strtotime($year."-".$month."-".$allday));
        $time = array();
        for($i=$time_start;$i<=$time_end;$i++){
            $time[] = $i;
        }
        return $time;
    }
    //百度坐标转换成GPS坐标 $lnglat = '121.437518,31.224665';
     public static function FromBaiduToGpsXY($lnglat){
        $lnglat = explode(',',$lnglat);
        list($x,$y) = $lnglat;
        $Baidu_Server = ";to=4&x={$x}&y={$y}";
        $result = @file_get_contents($Baidu_Server);
        $json = json_decode($result);
        if($json->error == 0) {
            $bx = base64_decode($json->x);
            $by = base64_decode($json->y);
            $GPS_x = 2 * $x - $bx;
            $GPS_y = 2 * $y - $by;
            return $GPS_x.','.$GPS_y;//经度,纬度
        } else return $lnglat;
    }
    public static function fn_rad($d) {
        return $d * pi() / 180.0;
    }
    // 坐标2点间算法
    public static function P2PDistance($latlng1,$latlng2) {
        // 纬度1,经度1 ~ 纬度2,经度2
        $latlng1 = explode(',',$latlng1);
        $latlng2 = explode(',',$latlng2);
        list($lat1,$lng1) = $latlng1;
        list($lat2,$lng2) = $latlng2;
        $EARTH_RADIUS = 6378.137;
        $radLat1 = Common::fn_rad($lat1);
        $radLat2 = Common::fn_rad($lat2);
        $a = $radLat1 - $radLat2;
        $b = Common::fn_rad($lng1) - Common::fn_rad($lng2);
        $s = 2 * asin(sqrt(pow(sin($a/2),2) + cos($radLat1)*cos($radLat2)*pow(sin($b/2),2)));
        $s = $s * $EARTH_RADIUS;
        $s = round($s * 10000) / 10000;
        return number_format($s,3);
    }
    //显示距离
    public static function showRange($obj){
        $data = $obj ;
        if($data>=900){
            $range = ceil($obj/1000);
            if($range>0){
                return $range.'公里以内';
            }
        }elseif($data<100){
             return '100米以内';
        }elseif($data>=100&&$data<200){
            return '200米以内';
        }elseif($data>=200&&$data<300){
            return '300米以内';
        }elseif($data>=300&&$data<400){
            return '400米以内';
        }elseif($data>=400&&$data<500){
            return '500米以内';
        }elseif($data>=500&&$data<600){
            return '600米以内';
        }elseif($data>=600&&$data<700){
            return '700米以内';
        }elseif($data>=700&&$data<800){
            return '800米以内';
        }elseif($data>=800&&$data<900){
            return '900米以内';
        }
    }
   //二维数组按某个元素的值排序
    public static function array2sort($a,$sort,$d='') {
        $num=count($a);
        if(!$d){
            for($i=0;$i<$num;$i++){
                for($j=0;$j<$num-1;$j++){
                    if($a[$j][$sort] > $a[$j+1][$sort]){
                        foreach ($a[$j] as $key=>$temp){
                            $t=$a[$j+1][$key];
                            $a[$j+1][$key]=$a[$j][$key];
                            $a[$j][$key]=$t;
                        }
                    }
                }
            }
        }
        else{
            for($i=0;$i<$num;$i++){
                for($j=0;$j<$num-1;$j++){
                    if($a[$j][$sort] < $a[$j+1][$sort]){
                        foreach ($a[$j] as $key=>$temp){
                            $t=$a[$j+1][$key];
                            $a[$j+1][$key]=$a[$j][$key];
                            $a[$j][$key]=$t;
                        }
                    }
                }
            }
        }
        return $a;
    }
    //接口执行数据执行
    public static function appLog($name,$time,$version = ''){
        if(DOMAIN_PARAMETER=='d'||DOMAIN_PARAMETER=='t'){
            $token = isset($_POST['token'])?$_POST['token']:'';
            $time = round(microtime(true)-$time,3);
            $appInterface = new AppInterfaceModel();
            $appInterface->verify($name,$time,$token,$version);
        }

    }

    //将多维数组转化成一维数组
    public static function arrayMultiToSingle($array,$field) {
        foreach($array as $key=>$val){
            $array[$key] = $val[$field];
        }
        return $array;
    }

    /**
     * 判断是否是链接
     */
    public static function isUrl($content){
        $reg = '/((http|ftp|https)\:\/\/)?([\w-]+\.)+[\w-]+(\/[\w\?\.=#&:\/]*)?/';
        return preg_match_all($reg, $content, $matches);
    }
    //二维数组去重复
    public static function unique_arr($array2D,$stkeep=false,$ndformat=true)
    {
        // 判断是否保留一级数组键 (一级数组键可以为非数字)
        if($stkeep) $stArr = array_keys($array2D);

        // 判断是否保留二级数组键 (所有二级数组键必须相同)
        if($ndformat) $ndArr = array_keys(end($array2D));

        //降维,也可以用implode,将一维数组转换为用逗号连接的字符串
        foreach ($array2D as $v){
            $v = join(",",$v);
            $temp[] = $v;
        }

        //去掉重复的字符串,也就是重复的一维数组
        $temp = array_unique($temp);

        //再将拆开的数组重新组装
        foreach ($temp as $k => $v)
        {
            if($stkeep) $k = $stArr[$k];
            if($ndformat)
            {
                $tempArr = explode(",",$v);
                foreach($tempArr as $ndkey => $ndval) $output[$k][$ndArr[$ndkey]] = $ndval;
            }
            else $output[$k] = explode(",",$v);
        }

        return $output;
    }
    //根据搜索半径和经纬度查询搜索范围
    public static function getRange($lng,$lat){
        $distance = 1000;//搜索范围 单位km
        $radius = 6378.137;//地球半径
        $dlng =  2 * asin(sin($distance / (2 * $radius)) / cos(deg2rad($lat)));
        $dlng = rad2deg($dlng);
        $dlat = $distance/$radius;
        $dlat = rad2deg($dlat);
        //计算实际搜索的四边形的四个边界范围
        $data['lng_left'] = round($lng-$dlng,6);
        $data['lng_right'] = round($lng+$dlng,6);
        $data['lat_top'] = round($lat+$dlat,6);
        $data['lat_bottom']= round($lat - $dlat,6);
        return $data;
    }
    /*
     * 外链检查
     */
    public static function checkUrl($content){
        $content = strtolower($content);
        $rst = false;
        $ddccReg = '@[\w\-_]+\.91ddcc\.com@';

        //链接正则
        $reg = '/((http|ftp|https)\:\/\/)?([\w-]+\.)+[\w-]+(\/[\w\?\.=#&:\/]*)?/';
        if(preg_match_all($reg, $content, $matches)){
            $urls = $matches[0];
            unset($matches);
            for( $i=0, $count = count($urls); $i < $count; $i++ ){
                $url = $urls[$i];
                if( (!preg_match($ddccReg, $url) )){
                    $rst = $url;
                    break;
                }
            }
        }
        return $rst;
    }

    //根据hash值全出实际距离
    public static function getDistance($lat1,$lng1,$lat2,$lng2){
        //地球半径
        $R = 6378137;
        //将角度转为狐度
        $radLat1 = deg2rad($lat1);
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);
        //结果
        $s = acos(cos($radLat1)*cos($radLat2)*cos($radLng1-$radLng2)+sin($radLat1)*sin($radLat2))*$R;
        //精度
        //$s = round($s* 10000)/10000;
        return $s;
    }

    //推送ios设备消息
    public static function pushMessageToIos ($user_id){
        $api_key = '23tCV4KG5W0UvvWxuABrLE6i';
        $secret_key = 'SFSQF00BkQvp7321KnGCxzVL4Y5bjz13';
        $channel = new Channel ( $api_key, $secret_key ) ;

        //注意百度push服务对ios dev版与ios release版采用不同的域名.
        //如果是dev版请修改push服务器域名"https://channel.iospush.api.duapp.com"
        //Release版则使用默认域名,无须修改。修改域名使用setHost接口
        $channel->setHost("https://channel.iospush.api.duapp.com");

        $push_type = 1; //推送单播消息
        $optional[Channel::USER_ID] = $user_id; //如果推送单播消息，需要指定user

        //指定发到ios设备
        $optional[Channel::DEVICE_TYPE] = 4;

        //指定消息类型为通知
        $optional[Channel::MESSAGE_TYPE] = 1;


        //如果ios应用当前部署状态为开发状态，指定DEPLOY_STATUS为1，默认是生产状态，值为2.
        //旧版本曾采用连接不同的host区分部署状态，仍然支持。
        $optional[Channel::DEPLOY_STATUS] = 1;

        //通知类型的内容必须按指定内容发送，示例如下：
        $message = '{
        "aps":{
            "alert":"msg from baidu push",
            "sound":"",
            "badge":1
            }
        }';

        $message_key = "msg_key";
        $ret = $channel->pushMessage ( $push_type, $message, $message_key, $optional ) ;
        if ( false === $ret )
        {
            print_r( 'WRONG, ' . __FUNCTION__ . ' ERROR!!!!!' ) ;
            print_r ( 'ERROR NUMBER: ' . $channel->errno ( ) ) ;
            print_r ( 'ERROR MESSAGE: ' . $channel->errmsg ( ) ) ;
            print_r ( 'REQUEST ID: ' . $channel->getRequestId ( ) );
        }
        else
        {
            print_r ( 'SUCC, ' . __FUNCTION__ . ' OK!!!!!' ) ;
            print_r ( 'result: ' . print_r ( $ret, true ) ) ;
        }
    }

    //推送android消息
    public static function pushMessageToAndroid ( $messages, $message_keys)
    {
        $api_key = '23tCV4KG5W0UvvWxuABrLE6i';
        $secret_key = 'SFSQF00BkQvp7321KnGCxzVL4Y5bjz13';
        $channel = new Channel ($api_key, $secret_key) ;

        //推送单播消息，必须指定user_id或者user_id+channel_id
        $push_type = 1;
        $user_id = 'xxx';
        $channel_id = 'xxx';
        $optional[Channel::USER_ID] = $user_id;
        $optional[Channel::CHANNEL_ID] = $channel_id;
        $message = 'Hello World';
        $message_key = 'msg_key';
        $ret = $channel->pushMessage ( $push_type, $message, $message_key, $optional );

        //推送通知，必须指定MESSAGE_TYPE为1
        $optional[Channel::MESSAGE_TYPE] = 1;
        //通知必须按以下格式指定
        $message = '{
                    "title": "title",
                    "description": "description"
                    }';
        $message_key = "msg_key";
        $ret = $channel->pushMessage ( $push_type, $message, $message_key, $optional );

        //推送消息到一群人，按tag推送,必须指定tag_name
        $push_type = 2;
        $tag_name = 'xx';
        $optional[Channel::TAG_NAME] = $tag_name;
        $ret = $channel->pushMessage($push_type, $messages, $message_keys, $optional);

        //推送消息到某个应用下的所有人，不用指定user_id, channel_id, tag_name
        $push_type = 3;
        $ret = $channel->pushMessage($push_type, $messages, $message_keys);

        //检查返回值
        if ( false === $ret )
        {
            echo ( 'WRONG, ' . __FUNCTION__ . ' ERROR!!!!\n' );
            echo ( 'ERROR NUMBER: ' . $channel->errno ( ) . '\n' );
            echo ( 'ERROR MESSAGE: ' . $channel->errmsg ( ) . '\n' );
            echo ( 'REQUEST ID: ' . $channel->getRequestId ( ) . '\n' );
        }
        else
        {
            echo ( 'SUCC, ' . __FUNCTION__ . ' OK!!!!!'. '\n' );
            echo ( 'result: ' . print_r ( $ret, true ) . '\n' );
        }
    }


    //支付宝移动接口服务端生成签名串代码
    public static function getAlipaySign($ali){
        $ali = self::argSort($ali);
        $str = '';
        foreach($ali as $key=>$val){
            if($key == 'sign_type' || $key == 'sign'){
                continue;
            }else{
                if($str == ''){
                    $str = $key.'='.'"'.$val.'"';
                }else{
                    $str = $str.'&'.$key.'='.'"'.$val.'"';
                }
            }
        }
        $sign = urlencode(self::sign($str));
        $str = $str.'&sign='.'"'.$sign.'"'.'&sign_type='.'"'.$ali['sign_type'].'"';//传给支付宝接口的数据
        return array('str'=>$str,'sign'=>$sign);
    }
   public static function argSort($para) {
        ksort($para);
        reset($para);
        return $para;
    }

    //RSA签名
    public static function sign($data) {
        //读取私钥文件
        $priKey = file_get_contents(APPLICATION_PATH.'/application/library/key/rsa_private_key.pem');//私钥文件路径
        //转换为openssl密钥，必须是没有经过pkcs8转换的私钥
        $res = openssl_get_privatekey($priKey);
        //调用openssl内置签名方法，生成签名$sign
        openssl_sign($data, $sign, $res);
        //释放资源
        openssl_free_key($res);
        //base64编码
        $sign = base64_encode($sign);
        return $sign;
    }
    //支付宝验证签名
    public static function rsaVerify($prestr, $sign) {
        $sign = base64_decode($sign);
        $public_key= file_get_contents(APPLICATION_PATH.'/application/library/key/rsa_public_key.pem');
        $pkeyid = openssl_get_publickey($public_key);
        if($pkeyid) {
             $verify = openssl_verify($prestr, $sign, $pkeyid);
             openssl_free_key($pkeyid);
        }
        if($verify == 1){
                    return 'yes';
        }else{
                    return 'no';
        }
     }
    //微信获取预付单号码
    public static function getWxPrepayid($data){
        $xml = '<?xml version="1.0"?>
                <xml>
                   <appid>'.$data['appid'].'</appid>
                   <body>'.$data['body'].'</body>
                   <mch_id>'.$data['mch_id'].'</mch_id>
                   <nonce_str>'.$data['nonce_str'].'</nonce_str>
                   <notify_url>'.$data['notify_url'].'</notify_url>
                   <out_trade_no>'.$data['out_trade_no'].'</out_trade_no>
                   <spbill_create_ip>'.$data['spbill_create_ip'].'</spbill_create_ip>
                   <sign>'.$data['sign'].'</sign>
                   <total_fee>'.$data['total_fee'].'</total_fee>
                   <trade_type>'.$data['trade_type'].'</trade_type>
                </xml>';
        $apiUrl='https://api.mch.weixin.qq.com/pay/unifiedorder';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $apiUrl);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return self::xmlToArray($output);
    }
    //微信平台查询订单
    public static function getWxOrder($data){
        $xml = '<?xml version="1.0"?>
                <xml>
                   <appid>'.$data['appid'].'</appid>
                   <mch_id>'.$data['mch_id'].'</mch_id>
                   <nonce_str>'.$data['nonce_str'].'</nonce_str>
                   <out_trade_no>'.$data['out_trade_no'].'</out_trade_no>
                   <sign>'.$data['sign'].'</sign>
                </xml>';
        $apiUrl='https://api.mch.weixin.qq.com/pay/orderquery';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $apiUrl);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return self::xmlToArray($output);
    }
    //将XML转为array
    public static function xmlToArray($xml){
       $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
       return $array_data;
    }
    //支付宝外部api
    public static function aliApi($data){
        $url='https://openapi.alipay.com/gateway.do';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_REFERER, $url);   //构造来路
        curl_setopt($ch, CURLOPT_POST, true);
//        curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
//        curl_setopt($ch,CURLOPT_SSLCERT,APPLICATION_PATH.'/application/library/key/rsa_public_key.pem');
//        curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
//        curl_setopt($ch,CURLOPT_SSLCERT,APPLICATION_PATH.'/application/library/key/rsa_private_key.pem');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $handles = curl_exec($ch);
        curl_close($ch);
        return $handles;
    }
    //支付宝退款
    public static function aliRefund($url){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return json_decode($output,true);
    }
    //微信退款
    public static function wxRefund($data){
        $xml = '<?xml version="1.0"?>
                <xml>
                   <appid>'.$data['appid'].'</appid>
                   <mch_id>'.$data['mch_id'].'</mch_id>
                   <nonce_str>'.$data['nonce_str'].'</nonce_str>
                   <op_user_id>'.$data['op_user_id'].'</op_user_id>
                   <out_refund_no>'.$data['out_refund_no'].'</out_refund_no>
                   <out_trade_no>'.$data['out_trade_no'].'</out_trade_no>
                   <refund_fee>'.$data['refund_fee'].'</refund_fee>
                   <total_fee>'.$data['total_fee'].'</total_fee>
                   <transaction_id></transaction_id>
                   <sign>'.$data['sign'].'</sign>
                </xml>';
        $apiUrl='https://api.mch.weixin.qq.com/secapi/pay/refund';
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$apiUrl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLCERT,APPLICATION_PATH.'/application/library/WXKey/apiclient_cert.pem');
        curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLKEY,APPLICATION_PATH.'/application/library/WXKey/apiclient_key.pem');
        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$xml);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return self::xmlToArray($output);
    }
    //城市筛选返回
    public static function returnCity(){
        return array(
            array('city'=>'全国'),
            array('city'=>'北京'),
            array('city'=>'上海'),
            array('city'=>'广州'),
            array('city'=>'深圳'),
            array('city'=>'成都'),
            array('city'=>'重庆'),
            array('city'=>'陕西'),
            array('city'=>'浙江'),
            array('city'=>'安徽'),
            array('city'=>'江苏'),
            array('city'=>'河北')
        );
    }
    //城市筛选处理返回id
    public static  function getIdByCity($city){
        switch($city){
            case '全国':
                return '';
                break;
            case '北京':
                return '110000';
                break;
            case '上海':
                return '310000';
                break;
            case '广州':
                return '440100';
                break;
            case '深圳':
                return '440300';
                break;
            case '成都':
                return '510100';
                break;
            case '重庆':
                return '500000';
                break;
            case '陕西':
                return '610000';
                break;
            case '浙江':
                return '330000';
                break;
            case '安徽':
                return '340000';
                break;
            case '江苏':
                return '320000';
                break;
            case '河北':
                return '130000';
                break;
        }
    }
    public static function getEventStartTime($eid){
        $eventModel = new EventModel();
        $rs = $eventModel->getEventStartTime($eid);
        $time_str='';
        if($rs){
            $time_str=implode(',',$rs);
            $time_str=$time_str.'开始';
        }
        return $time_str;
    }

    /*
     * 驿站商品发送系统通知和短信通知
     *
     */
    public static function addNoticeAndSmsForGoods($type,$obj_id){
        $noticeModel = new NoticeModel();
        $userModel =new UserModel();
        $addressModel = new AddressModel();
        $stagegoodsModel = new StagegoodsModel();
        $stageModel = new StageModel();
        $orderInfo = $stagegoodsModel->orderInfoByOrderId($obj_id);
        $goods_info = $stagegoodsModel->getGoodsRedisById($orderInfo['goods_id']);
        $addressInfo = $addressModel->getUserShipping($orderInfo['uid'],$orderInfo['address_id']);
        $stageInfo = $stageModel->getBasicStageBySid($goods_info['sid']);
        switch($type){
            case '1'://商品--提醒卖家发货
                $userInfo = $userModel->getUserData($orderInfo['uid']);
                $notice_content = ''.$userInfo['nick_name'].'购买了您的商品【<span class="blue"><a href="http://sns.91ddcc.com/g/'.$orderInfo['goods_id'].'">'.$goods_info['name'].'</a></span>】，您可以发货啦（打开我界面>我卖出的>待发货页签进行发货）。如有任何问题，欢迎随时和 @8931@ 进行沟通，感谢您对才府的支持！';
                $noticeModel->addNotice($goods_info['uid'],$notice_content);
                $sms_content = ''.$userInfo['nick_name'].'购买了您的商品['.$goods_info['name'].']，您可以发货啦（打开我界面>我卖出的>待发货页签进行发货）。如有任何问题，欢迎联系才府小管家，感谢您对才府的支持！';
                Sms::send($stageInfo['mobile'], $sms_content);
                break;
            case '2'://商品--发货提醒买家
                $notice_content = '您购买的【<span class="blue"><a href="http://sns.91ddcc.com/g/'.$orderInfo['goods_id'].'">'.$goods_info['name'].'</a></span>】卖家已经发货（打开我界面>我买到的>已发货页签进行查看），请注意查收。如有任何问题，欢迎随时和 @8931@ 进行沟通，感谢您对才府的支持！';
                $noticeModel->addNotice($orderInfo['uid'],$notice_content);
                $sms_content = '您购买的['.$goods_info['name'].']卖家已经发货（打开我界面>我买到的>已发货页签进行查看），请注意查收。如有任何问题，欢迎联系才府小管家，感谢您对才府的支持！';
                Sms::send($addressInfo['phone'], $sms_content);
                break;
            case '3'://商品--买家确认收货提醒卖家
                $userInfo = $userModel->getUserData($orderInfo['uid']);
                $notice_content =  '您卖出的【<span class="blue"><a href="http://sns.91ddcc.com/g/'.$orderInfo['goods_id'].'">'.$goods_info['name'].'</a></span>】，买家'.$userInfo['nick_name'].'已经确认收货啦（打开我界面>我卖出的>已完成页签进行查看）。如有任何问题，欢迎随时和 @8931@ 进行沟通，感谢您对才府的支持！';
                $noticeModel->addNotice($goods_info['uid'],$notice_content);
                $sms_content = '您卖出的['.$goods_info['name'].']，买家'.$userInfo['nick_name'].'已经确认收货啦（打开我界面>我卖出的>已完成页签进行查看）。如有任何问题，欢迎联系才府小管家，感谢您对才府的支持！';
                Sms::send($stageInfo['mobile'], $sms_content);
                break;
            case '4'://商品--延迟收货提醒卖家
                $userInfo = $userModel->getUserData($orderInfo['uid']);
                $notice_content =  ''.$userInfo['nick_name'].'对商品【<span class="blue"><a href="http://sns.91ddcc.com/g/'.$orderInfo['goods_id'].'">'.$goods_info['name'].'</a></span>】进行了延迟收货操作，（打开我界面>我卖出的>已发货页签查看详情）。如有任何问题，欢迎随时和 @8931@ 进行沟通，感谢您对才府的支持！';
                $noticeModel->addNotice($goods_info['uid'],$notice_content);
                $sms_content = ''.$userInfo['nick_name'].'对商品['.$goods_info['name'].']进行了延迟收货操作，（打开我界面>我卖出的>已发货页签查看详情）。如有任何问题，欢迎联系才府小管家，感谢您对才府的支持！';
                Sms::send($stageInfo['mobile'], $sms_content);
                break;
            case '5'://商品--还有2天将自动收货提醒买家
                $notice_content =  '您购买了【<span class="blue"><a href="http://sns.91ddcc.com/g/'.$orderInfo['goods_id'].'">'.$goods_info['name'].'</a></span>】，该商品还有2天将自动确认收货。如有任何问题，欢迎随时和 @8931@ 进行沟通，感谢您对才府的支持！';
                $noticeModel->addNotice($orderInfo['uid'],$notice_content);
                $sms_content = '您购买了['.$goods_info['name'].']，该商品还有2天将自动确认收货。如有任何问题，欢迎联系才府小管家，感谢您对才府的支持！';
                Sms::send($addressInfo['phone'], $sms_content);
                break;
        }
    }
    //服务信息系统通知
    public static function addNoticeAndSmsForEvent($type,$order_id='',$p_info_id=''){
        $event_type = array(
            1 =>'活动',3=>'培训',6=>'展览',7=>'演出',8=>'展演'
        );
        $noticeModel = new NoticeModel();
        $userModel =new UserModel();
        $eventModel = new EventModel();
        $stageModel = new StageModel();
        if($order_id){
            $orderInfo = $eventModel->getInfoByOrderId($order_id);
            $eventInfo = $eventModel->getEvent($orderInfo['eid']);
            $stageInfo = $stageModel->getBasicStageBySid($orderInfo['sid']);
            $userInfo = $userModel->getUserData($orderInfo['uid']);
            $user_phone = $orderInfo['phone'];
            $n_uid = $orderInfo['uid'];
        }
        if($p_info_id){
            $p_info = $eventModel->getPartakeInfoById($p_info_id);
            $eventInfo = $eventModel->getEvent($p_info['eid']);
            $stageInfo = $stageModel->getBasicStageBySid($eventInfo['sid']);
            $userInfo = $userModel->getUserData($p_info['uid']);
            $n_uid = $p_info['uid'];
        }
        $nick_name = $userInfo['nick_name'];
        $e_type = $event_type[$eventInfo['type']];
        $stage_mobile = $stageInfo['mobile'];
        $eid = $eventInfo['id'];
        $title = $eventInfo['title'];
        $e_uid = $eventInfo['uid'];


        switch($type){
            case '1'://服务信息有人报名提醒卖家
                $notice_content =  ''.$nick_name.'报名了您的'.$e_type.'【<span class="blue"><a href="'.SNS_DOMAIN.'/e/'.$eid.'">'.$title.'</a></span>】，您可以登陆才府查看订单详情（打开我界面>我卖出的）。如有任何问题，欢迎随时和 @8931@进行沟通，感谢您对才府的支持！';
                $noticeModel->addNotice($e_uid,$notice_content);
                $sms_content = ''.$nick_name.'报名了您的'.$e_type.'['.$title.']，您可以登陆才府查看订单详情（打开我界面>我卖出的）。如有任何问题，欢迎联系才府小管家，感谢您对才府的支持！';
                Sms::send($stage_mobile, $sms_content);
                break;
            case '2'://服务信息即将开始提醒买家  系统通知 （场次、uid去重） 短信（手机号、场次去重）
                $notice_content =  '您报名的【'.$title.'】，该'.$e_type.'还有1天开始，请及时参加，如有任何问题，欢迎随时和 @8931@ 进行沟通，感谢您对才府的支持！';
                $noticeModel->addNotice($n_uid,$notice_content);
                $sms_content = '您报名的['.$title.']，该'.$e_type.'还有1天开始，请及时参加，如有任何问题，欢迎联系才府小管家，感谢您对才府的支持！';
                Sms::send($user_phone, $sms_content);
                break;
        }
    }
    //用户系统通知
    public static function addNoticeAndSmsForUser($type,$data){
        $noticeModel = new NoticeModel();
        switch($type){
            case '1'://用户入驻才府 推送系统通知 友盟推送
                $notice_content =  '您已入驻才府'.$data['time'].'周年啦，正因为有您的陪伴，才府才会茁壮成长，才府全体工作人员感谢您的支持。';
                $noticeModel->addNotice($data['uid'],$notice_content);
                $push_content = '您已入驻才府'.$data['time'].'周年啦，正因为有您的陪伴，才府才会茁壮成长，才府全体工作人员感谢您的支持。';
                Common::http(OPEN_DOMAIN.'/pushapi/send',array('uid'=>$data['uid'],'message'=>$push_content),'POST');
                break;
            case '2'://用户生日 推送系统通知，友盟推送
                $notice_content =  '今天是您的生日，才府全体工作人员祝您生日快乐，幸福安康。';
                $noticeModel->addNotice($data['uid'],$notice_content);
                $push_content = '今天是您的生日，才府全体工作人员祝您生日快乐，幸福安康。';
                Common::http(OPEN_DOMAIN.'/pushapi/send',array('uid'=>$data['uid'],'message'=>$push_content),'POST');
                break;
        }
    }
    public static function eventType($type){
        switch($type){
            case '1':
                return array('name'=>'活动','code'=>'hd');
                break;
            case '3':
                return array('name'=>'培训','code'=>'px');
                break;
            case '6':
                return array('name'=>'展览','code'=>'zl');
                break;
            case '7':
                return array('name'=>'演出','code'=>'yc');
                break;
            case '8':
                return array('name'=>'展演','code'=>'zy');
                break;
        }
    }
    //发布，修改,删除 根据type获取redis的key
    public static function getRedisKey($type){
        switch($type){
            case '1'://心境
                return 'info:mood:';
                break;
            case '4':
                return 'info:topic:';
                break;
            case '5'://驿站
                return 'info:stage:';
                break;
            case '10':
                return 'info:event:';
                break;
            case '12':
                return 'info:stage_goods:';
                break;
        }
    }
}
