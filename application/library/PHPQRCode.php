<?php
/**
 * Created by PhpStorm.
 * User: zgh
 * Date: 14-12-22
 * Time: 下午5:00
 */
include "phpqrcode/qrlib.php";
class PHPQRCode {

    /**
     * 通过类库生成二维码
     * @param string $url 要生成二维码的URL
     * @param string $dir 生成的二维码保存的文件目录
     * @param string $filename 生成的二维码文件名
     * @param array $options 生成的二维码的参数设置
     * level QR_ECLEVEL_L, QR_ECLEVEL_M, QR_ECLEVEL_Q or QR_ECLEVEL_H
     * size  pixel size, multiplier for each 'virtual' pixel
     * margin code margin (silent zone) in 'virtual' pixels
     * logo logo图片
     * w	尺寸大小	w=数值（像素），例如：w=300
     * logoMargin logo margin 外边距
     * bg_r 二维码颜色RGB  R值 0-255
     * bg_g 二维码颜色RGB  G值 0-255
     * bg_b 二维码颜色RGB  B值 0-255
     * @return int 0 生成文件失败 1 生成文件成功
     */
    public static function createPHPQRCode($url,$dir,$filename,$options = array()) {
        $defaults = array('level'=>QR_ECLEVEL_H,'size'=>3,'margin'=>1,'logo'=>'','w'=>100,'logoMargin'=>0,'bg_r'=>0,'bg_g'=>0,'bg_b'=>0);
        $options = array_merge($defaults,$options);
        QRCode::png($url,$dir.'/'.$filename,$options['level'],$options['size'],$options['margin']);
        $QR = imagecreatefrompng($dir.'/'.$filename);
        $QR_width = imagesx($QR);
        $QR_height = imagesy($QR);
        $new_QR = imagecreatetruecolor($options['w'],$options['w']);
        imagecopyresampled($new_QR,$QR,0,0,0,0,$options['w'],$options['w'],$QR_width,$QR_height);
        if($options['bg_r'] || $options['bg_g'] || $options['bg_b']) {
            imagefilter($new_QR,IMG_FILTER_COLORIZE,$options['bg_r'],$options['bg_g'],$options['bg_b']);
        }
        if(isset($options['logo']) && $options['logo']) {
            $logo = imagecreatefromstring(file_get_contents($options['logo']));
            if($logo){
                $logo_width = imagesx($logo);
                $logo_height = imagesy($logo);
                $logo_qr_width = $options['w'] / 4;
                $scale = $logo_width/$logo_qr_width;
                $logo_qr_height = $logo_height/$scale;
                if(isset($options['logoMargin']) && $options['logoMargin']) {
                    $new_logo_width = $logo_qr_width+2*$options['logoMargin'];
                    $new_logo_height = $logo_qr_height+2*$options['logoMargin'];
                    $new_logo = imagecreatetruecolor($new_logo_width,$new_logo_height);
                    imagefilter($new_logo,IMG_FILTER_COLORIZE,255,255,255);
                    imagecopyresampled($new_logo,$logo,$options['logoMargin'],$options['logoMargin'],0,0,$logo_qr_width,$logo_qr_height,$logo_width,$logo_height);
                    $from_width = ($options['w'] - $new_logo_width) / 2;
                    imagecopyresampled($new_QR, $new_logo, $from_width, $from_width, 0, 0, $new_logo_width,$new_logo_height, $new_logo_width, $new_logo_height);
                } else {
                    $from_width = ($options['w'] - $logo_qr_width) / 2;
                    imagecopyresampled($new_QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,$logo_qr_height, $logo_width, $logo_height);
                }
            }
        }
        $success = imagepng($new_QR, $dir.'/'.$filename);
        return $success?1:0;
    }

    /**
     * 获取驿站二维码
     * @param $sid 驿站ID 如:298
     */
    public static function getStageQRCode($sid,$icon='') {
        self::createQRCode(SNS_DOMAIN.'/s/'.$sid,array('logo'=>$icon,'w'=>400,'logoMargin'=>5));
    }
    /**
     * 获取用户二维码
     * @param $uid 用户ID
     * @param $did 代代号
     * @param string $avatar 头像
     * @param bool $isChange 默认是false 不需要更改文件 true 需要更改文件
     * @return string 图片地址 http://sns.91ddcc.com/images/qrcode/u98/298.png

     */
    public static function getUserQRCode($did,$avatar='') {
        self::createQRCode(SNS_DOMAIN.'/u/'.$did,array('logo'=>$avatar,'w'=>400,'logoMargin'=>5));
    }
    public static function getTopicQRCode($tid,$size) {
        QRCode::png(SNS_DOMAIN.'/t/'.$tid,false,QR_ECLEVEL_H,$size,1);
    }
    public static function getEventQRCode($eid,$size) {
        QRCode::png(SNS_DOMAIN.'/e/'.$eid,false,QR_ECLEVEL_H,$size,1);
    }
    //获取日志二维码
    public static function getBlogQRCode($bid) {
        QRCode::png(SNS_DOMAIN.'/b/'.$bid,false,QR_ECLEVEL_H,3,1);
    }
    //获取用户报名凭证
    public static function getPartakeQRCode($uid,$sid,$eid){
        QRCode::png(SNS_DOMAIN.'/'.base64_encode('partake?u='.$uid.'&s='.$sid.'&e='.$eid),false,QR_ECLEVEL_H,3,1);
        //self::createQRCode(SNS_DOMAIN.'/partake?u='.$uid.'&s='.$sid.'&e='.$eid,array('w'=>400,'logoMargin'=>5));
    }
    //获取用户支付
    public static function getOrderQRCode($order_id,$eid){
        QRCode::png(SNS_DOMAIN.'/'.base64_encode('order?order_id='.$order_id.'&e='.$eid),false,QR_ECLEVEL_H,3,1);
        //self::createQRCode(SNS_DOMAIN.'/partake?u='.$uid.'&s='.$sid.'&e='.$eid,array('w'=>400,'logoMargin'=>5));
    }
    /**
     * 通过类库生成二维码
     * @param string $url 要生成二维码的URL
     * @param string $dir 生成的二维码保存的文件目录
     * @param string $filename 生成的二维码文件名
     * @param array $options 生成的二维码的参数设置
     * level QR_ECLEVEL_L, QR_ECLEVEL_M, QR_ECLEVEL_Q or QR_ECLEVEL_H
     * size  pixel size, multiplier for each 'virtual' pixel
     * margin code margin (silent zone) in 'virtual' pixels
     * logo logo图片
     * w	尺寸大小	w=数值（像素），例如：w=300
     * logoMargin logo margin 外边距
     * bg_r 二维码颜色RGB  R值 0-255
     * bg_g 二维码颜色RGB  G值 0-255
     * bg_b 二维码颜色RGB  B值 0-255
     * @return int 0 生成文件失败 1 生成文件成功
     */
    public static function createQRCode($url,$options = array()) {
        header('Content-type: image/png');
        $dir = STATIC_DOMAIN.'/images/qrcode';
        $filename = time().'.png';
        $defaults = array('level'=>QR_ECLEVEL_H,'size'=>3,'margin'=>1,'logo'=>'','w'=>100,'logoMargin'=>0,'bg_r'=>10,'bg_g'=>50,'bg_b'=>70);
        $options = array_merge($defaults,$options);
        QRCode::png($url,$dir.'/'.$filename,$options['level'],$options['size'],$options['margin']);
        $QR = imagecreatefrompng($dir.'/'.$filename);
        $QR_width = imagesx($QR);
        $QR_height = imagesy($QR);
        $new_QR = imagecreatetruecolor($options['w'],$options['w']);
        imagecopyresampled($new_QR,$QR,0,0,0,0,$options['w'],$options['w'],$QR_width,$QR_height);
        if($options['bg_r'] || $options['bg_g'] || $options['bg_b']) {
            imagefilter($new_QR,IMG_FILTER_COLORIZE,$options['bg_r'],$options['bg_g'],$options['bg_b']);
        }
        if(isset($options['logo']) && $options['logo']) {
            $logo = imagecreatefromstring(file_get_contents($options['logo']));
            $logo_width = imagesx($logo);
            $logo_height = imagesy($logo);
            $logo_qr_width = $options['w'] / 4;
            $scale = $logo_width/$logo_qr_width;
            $logo_qr_height = $logo_height/$scale;
            if(isset($options['logoMargin']) && $options['logoMargin']) {
                $new_logo_width = $logo_qr_width+2*$options['logoMargin'];
                $new_logo_height = $logo_qr_height+2*$options['logoMargin'];
                $new_logo = imagecreatetruecolor($new_logo_width,$new_logo_height);
                imagefilter($new_logo,IMG_FILTER_COLORIZE,255,255,255);
                imagecopyresampled($new_logo,$logo,$options['logoMargin'],$options['logoMargin'],0,0,$logo_qr_width,$logo_qr_height,$logo_width,$logo_height);
                $from_width = ($options['w'] - $new_logo_width) / 2;
                imagecopyresampled($new_QR, $new_logo, $from_width, $from_width, 0, 0, $new_logo_width,$new_logo_height, $new_logo_width, $new_logo_height);
            } else {
                $from_width = ($options['w'] - $logo_qr_width) / 2;
                imagecopyresampled($new_QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,$logo_qr_height, $logo_width, $logo_height);
            }
        }
        imagepng($new_QR);
        imagedestroy($new_QR);
        unlink($dir.'/'.$filename);
    }

    /**
     * 获取驿站二维码 新接口 上传到七牛
     * @param $sid
     */
    public static function getStagePHPQRCode($sid,$isChange = false) {
        $stageModel = new StageModel();
        $stage = $stageModel->getStage($sid);
        if(!$stage['qrcode_img'] || $isChange){
            $date = date("Ymd");
            $dir = dirname(dirname(APPLICATION_PATH)) . "/upload/" . $date;
            $imgPath = "/upload/" . $date;
            if (!is_dir($dir)) {
                mkdir($dir);
                chmod($dir,0777);
            }
            $filename = time() . mt_rand(1000, 9999) . '.png';
            $newImgPath = $imgPath . "/" . $filename;
            $logo = IMG_DOMAIN.$stage['icon'].'?imageView2/2/w/200/h/200';
            $result = self::createPHPQRCode(SNS_DOMAIN.'/s/'.$sid,$dir,$filename,array('level'=>QR_ECLEVEL_H,'size'=>3,'margin'=>1,'logo'=>$logo,'w'=>400,'logoMargin'=>2));
            if($result){
                $image = new Image();
                $imgName = $image->uploadToServer(dirname(dirname(APPLICATION_PATH)).$newImgPath);
                if($imgName){
                    $stageModel->updateStageQRCodeImg($sid,$imgName);
                }
                return IMG_DOMAIN.$imgName;
            }
        }else{
            return IMG_DOMAIN.$stage['qrcode_img'];
        }
    }
    /**
     * 获取用户二维码
     * @param $uid 用户ID 新接口 上传到七牛
     */
    public static function getUserPHPQRCode($uid,$isChange = false) {
        $userModel = new UserModel();
        $user = $userModel->getUserByUid($uid);
        if(!$user['qrcode_img'] || $isChange){
            $date = date("Ymd");
            $dir = dirname(dirname(APPLICATION_PATH)) . "/upload/" . $date;
            $imgPath = "/upload/" . $date;
            if (!is_dir($dir)) {
                mkdir($dir);
                chmod($dir,0777);
            }
            $filename = time() . mt_rand(1000, 9999) . '.png';
            $newImgPath = $imgPath . "/" . $filename;
            $array =explode('/',$user['avatar']);
            $end = end($array);
            $avatar = IMG_DOMAIN.$end.'?imageView2/2/w/200/h/200';
            $result = PHPQRCode::createPHPQRCode(SNS_DOMAIN.'/u/'.$user['did'],$dir,$filename,
                array('level'=>QR_ECLEVEL_H,'size'=>3,'margin'=>1,'logo'=>$avatar,'w'=>400,'logoMargin'=>2));
            if($result){
                $image = new Image();
                $imgName = $image->uploadToServer(dirname(dirname(APPLICATION_PATH)).$newImgPath);
                if($imgName){
                    $userModel->updateUserQRCodeImg($uid,$imgName);
                }
                return IMG_DOMAIN.$imgName;
            }
        }else{
            return IMG_DOMAIN.$user['qrcode_img'];
        }
    }

    /**
     * 获取订单二维码
     * @param $uid 用户ID 新接口 上传到七牛
     */
    public static function getOrderPHPQRCode($id) {
        $eventModel = new EventModel();
        $orderInfo = $eventModel->orderInfoById($id);
        $eventInfo = $eventModel->getEvent($orderInfo['eid']);
        if(!$orderInfo['qrcodeImg']){
            $date = date("Ymd");
            $dir = dirname(dirname(APPLICATION_PATH)) . "/upload/" . $date;
            $imgPath = "/upload/" . $date;
            if (!is_dir($dir)) {
                mkdir($dir);
                chmod($dir,0777);
            }
            $filename = time() . mt_rand(1000, 9999) . '.png';
            $newImgPath = $imgPath . "/" . $filename;
            $result = self::createPHPQRCode(SNS_DOMAIN.'/'.base64_encode('order?id='.$id.'&s='.$eventInfo['sid'].'&e='.$orderInfo['eid'].'f_id='.$orderInfo['f_id'].'&type=2'),$dir,$filename, array('level'=>QR_ECLEVEL_H,'size'=>3,'margin'=>1,'w'=>400,'logoMargin'=>2));
            if($result){
                $image = new Image();
                $imgName = $image->uploadToServer(dirname(dirname(APPLICATION_PATH)).$newImgPath);
                if($imgName){
                    $eventModel->updatOrderQRCodeImg($imgName,$id);
                }
                return IMG_DOMAIN.$imgName;
            }
        }else{
            return IMG_DOMAIN.$orderInfo['qrcodeImg'];
        }
    }
    /**
     * 获取订单二维码
     * @param $uid 用户ID 新接口 上传到七牛
     */
    public static function getOrderPHPQRCodeNew($id,$o_id) {
        $eventModel = new EventModel();
        $qrcode = $eventModel->getQrcodeById($id);
        $orderInfo = $eventModel->orderInfoById($o_id);
        $eventInfo = $eventModel->getEvent($orderInfo['eid']);
        if(!$qrcode['qrcodeImg']){
            $date = date("Ymd");
            $dir = dirname(dirname(APPLICATION_PATH)) . "/upload/" . $date;
            $imgPath = "/upload/" . $date;
            if (!is_dir($dir)) {
                mkdir($dir);
                chmod($dir,0777);
            }
            $filename = time() . mt_rand(1000, 9999) . '.png';
            $newImgPath = $imgPath . "/" . $filename;
            $result = self::createPHPQRCode(SNS_DOMAIN.'/'.base64_encode('order?id='.$id.'&s='.$eventInfo['sid'].'&e='.$orderInfo['eid'].'f_id='.$orderInfo['f_id'].'&type=2'),$dir,$filename, array('level'=>QR_ECLEVEL_H,'size'=>3,'margin'=>1,'w'=>400,'logoMargin'=>2));
            if($result){
                $image = new Image();
                $imgName = $image->uploadToServer(dirname(dirname(APPLICATION_PATH)).$newImgPath);
                if($imgName){
                    $eventModel->updatOrderQRCodeImgNew($imgName,$id);
                }
                return IMG_DOMAIN.$imgName;
            }
        }else{
            return IMG_DOMAIN.$qrcode['qrcodeImg'];
        }
    }
    /**
     * 获取报名二维码
     * @param $uid 用户ID 新接口 上传到七牛
     */
    public static function getPartakePHPQRCode($f_id,$uid) {
        $eventModel = new EventModel();
        $partakeInfo = $eventModel->getPartakeInfo($f_id,$uid);
        $eventInfo = $eventModel->getEvent($partakeInfo['eid']);
        if(!$partakeInfo['qrcodeImg']){
            $date = date("Ymd");
            $dir = dirname(dirname(APPLICATION_PATH)) . "/upload/" . $date;
            $imgPath = "/upload/" . $date;
            if (!is_dir($dir)) {
                mkdir($dir);
                chmod($dir,0777);
            }
            $filename = time() . mt_rand(1000, 9999) . '.png';
            $newImgPath = $imgPath . "/" . $filename;
            $result = self::createPHPQRCode(SNS_DOMAIN.'/'.base64_encode('partake?id='.$partakeInfo['id'].'&s='.$eventInfo['sid'].'&e='.$partakeInfo['eid'].'&f_id='.$f_id.'&type=1'),$dir,$filename, array('level'=>QR_ECLEVEL_H,'size'=>3,'margin'=>1,'w'=>400,'logoMargin'=>2));
            if($result){
                $image = new Image();
                $imgName = $image->uploadToServer(dirname(dirname(APPLICATION_PATH)).$newImgPath);
                if($imgName){
                    $eventModel->updatPartakeQRCodeImg($imgName,$partakeInfo['id']);
                }
                return IMG_DOMAIN.$imgName;
            }
        }else{
            return IMG_DOMAIN.$partakeInfo['qrcodeImg'];
        }
    }
} 