<?php
class UserController extends Yaf_Controller_Abstract {
    public function init(){
        $this->startTime = microtime(true);
    }
    /**
     * 设置头像页面
     */
    public function getAvatarListAction(){
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        $list = $userModel->getAvatarList();
        Common::appLog('user/getAvatarList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    /*
     * 上传用户头像
     */
    public function uploadAvatarAction(){
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $image = new Image();
        $tmp_file = $_FILES['avatar']['tmp_name'];
        $filename = $_FILES['avatar']['name'];
        $rs = $image->upload($tmp_file,$filename);
        if($rs == -1){
            Common::echoAjaxJson(2,"头像格式不正确，请上传jpg,jpeg,png,gif格式的头像");
        }else if($rs == -2){
            Common::echoAjaxJson(3,"头像上传失败");
        }else if($rs == -3){
            Common::echoAjaxJson(4,"头像大小不能超过5M");
        }else if($rs == -4){
            Common::echoAjaxJson(5,"头像上传失败");
        }
        Common::appLog('user/uploadAvatar',$this->startTime,$version);
        Common::echoAjaxJson(1,"头像上传成功",$rs);
    }
    /*
     * @name 用户修改密码
     */
    public function modifyPwdAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(12, "非法登录用户");
        }
        $old_pwd = $this->getRequest()->getPost('old_pwd');//旧密码
        $pwd = $this->getRequest()->getPost('pwd');
        $confirm_pwd = $this->getRequest()->getPost('confirm_pwd');//确认新密码
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        if(!$old_pwd){
            if($user['reg_type'] != 3){
                Common::echoAjaxJson(10,'旧密码不能为空');
            }else{
                $old_pwd = '';
                $userInfo = $userModel->getUserByUid($user['uid']);
                $pass = $userModel->generatePassword('',$userInfo['salt']);
                if($pass != $userInfo['pwd']){
                    Common::echoAjaxJson(11,'您设置过密码，请输入原密码');
                }
            }
        }
        if(!$pwd){
            Common::echoAjaxJson(3,'新密码不能为空');
        }
        if(!preg_match('/^[\w!@#$%?\^&\*\(\)_]{6,16}$/',$pwd)){
            Common::echoAjaxJson(4,'请输入6-16位数字、字母或常用符号区分大小写');
        }
        if($pwd != $confirm_pwd){
            Common::echoAjaxJson(5,'两次密码不一致');
        }
        $pwd_type['pwd_type'] =Common::getPwdType($pwd);//密码强度
        $pwd_type=$pwd_type['pwd_type'];
        $rs = $userModel->modifyPwd($user['uid'],$old_pwd,$pwd,$pwd_type);
        if($rs == -1){
            Common::echoAjaxJson(6,'没有找到此用户');
        }
        if($rs == -2){
            Common::echoAjaxJson(7,'旧密码不正确');
        }
        if($rs == -3){
            Common::echoAjaxJson(8,'新密码和旧密码相同');
        }
        if($rs == 0){
            Common::echoAjaxJson(9,'密码修改失败');
        }
        if($user['reg_type'] == 3){
            $userModel = new UserModel();
            $userInfo = $userModel->getUserByUid($user['uid']);
            $pass = $userModel->generatePassword('',$userInfo['salt']);
            if($pass == $userInfo['pwd']){
                Common::echoAjaxJson(1,'密码修改成功');
            }
        }
        Common::appLog('user/modifyPwd',$this->startTime,$version);
        Common::echoAjaxJson(1,'密码修改成功');
    }
    //设置个人主页封面
    public function setHomeCoverAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $cover = $this->getRequest()->getPost('cover');//个人主页封面图
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        $rs = $userModel->updateAppHomeCover($user['uid'],$cover);
        if(!$rs){
            Common::echoAjaxJson(2,'封面设置失败');
        }
        Common::appLog('user/setHomeCover',$this->startTime,$version);
        Common::echoAjaxJson(1,'封面设置成功',$cover);
    }
    public function getBasicInfoByDidAction(){
        $data['token'] = $this->getRequest()->get('token');//用户登录token
        $user['uid'] = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
        }
        $did = $this->getRequest()->get('did');//代代号
        if(!$did){
            Common::echoAjaxJson(2, "代代号为空");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        $d = $userModel->getUserByDid($did);
        $userInfo = $userModel->getUserInfoByUid($d['uid']);
        $addressModel = new AddressModel();
        $followModel = new FollowModel();
        if($userInfo){
            $userInfo['location_province_name'] = $addressModel->getNameById($userInfo['location_province_id']);
            $userInfo['location_city_name'] = $addressModel->getNameById($userInfo['location_city_id']);
            $userInfo['location_town_name'] = $addressModel->getNameById($userInfo['location_town_id']);
            $userInfo['hometown_province_name'] = $addressModel->getNameById($userInfo['hometown_province_id']);
            $userInfo['hometown_city_name'] = $addressModel->getNameById($userInfo['hometown_city_id']);
            $userInfo['hometown_town_name'] = $addressModel->getNameById($userInfo['hometown_town_id']);
            if($userInfo['birthday_type'] == 1&&$userInfo['year']&&$userInfo['month']&&$userInfo['day']){
                $userInfo['birthday'] = $userInfo['year'].'年'.$userInfo['month'].'月'.$userInfo['day'].'日';
                $userInfo['birthday_val'] = $userInfo['year'].'-'.$userInfo['month'].'-'.$userInfo['day'];
            }elseif($userInfo['birthday_type'] == 2&&$userInfo['year']&&$userInfo['month']&&$userInfo['day']){
                if($userInfo['year'] && $userInfo['month'] && $userInfo['day']){
                    $lunar = new Lunar();
                    $date = $lunar->convertSolarToLunar($userInfo['year'],$userInfo['month'],$userInfo['day']);
                    $userInfo['birthday'] = $date[3].'年('.$date[0].') '.$date[1].' '.$date[2];
                    $userInfo['birthday_val'] = $date[0].'-'.$date[4].'-'.$date[5];
                }
                $userInfo['birthday'] ='';
                $userInfo['birthday_val'] = '';
            }
            $u =  $userModel->getUserData($d['uid'],$user['uid']);
            $userInfo['did'] = $u['did'];
            $userInfo['nick_name'] = $u['nick_name'];
            $userInfo['name'] = $u['name'];
            $userInfo['relation'] = $u['relation'];
            $userInfo['avatar'] = Common::show_img($u['avatar'],1,160,160);
            $userInfo['att_num'] = $u['att_num'];
            $userInfo['fans_num'] = $u['fans_num'];
            $stageModel = new StageModel();
            if($data['token'] &&$user['uid']==$d['uid']){
                $userInfo['stage_num'] = $u['stage_num'];
            }else{
                $userInfo['stage_num'] = $stageModel->getStageOther($d['uid']);
            }
            $u_info = $userModel->getUserByUid($d['uid']);
            if($u_info['qrcode_img']){
                $userInfo['qrcode_img'] = IMG_DOMAIN.$u_info['qrcode_img'];
            }else{
                $userInfo['qrcode_img'] = '';
            }

            $albumModel = new AlbumModel();
            $userInfo['album_num'] = $albumModel->getNum($u['uid'],$user['uid']);
            $userInfo['type'] = $u['type'];
            $userInfo['add_time'] = $u['add_time'];
            $userInfo['angel_info'] = '';
            if(in_array($u['type'],array(2,3,4,5))){
                $angelInfo = $userModel->getInfo($u['uid']);
                $userInfo['angel_info'] = $angelInfo['info'];
            }
            $tagModel = new TagModel();
            $tag_type = 1;
            $tagList = $tagModel->getRelation($tag_type,$u['uid']);
            $tagNewList = array();
            foreach($tagList as $key=>$val){
                $tagNewList[$key]['id'] = $val['tag_id'];
                $tagNewList[$key]['content'] = $val['content'];
            }
            if($tagNewList){
                $userInfo['tag'] = $tagNewList;
            }else{
                $userInfo['tag'] = array();
            }
            $userInfo['expInfo']=Common::getUserLevel($userInfo['exp']);
            if(isset($user['uid'])&&$user['uid']==$u['uid']){
                $feedModel = new FeedModel();
                $feeds = $feedModel->getUserList($u['uid'],0,1);
                $userInfo['feed_count'] = $feeds['size'];
                $userInfo['self'] =  1;
            }elseif(isset($user['uid'])&&$user['uid']!=$u['uid']){
                $feedModel = new FeedModel();
                $followModel = new FollowModel();
                $feeds = $feedModel->getUserList($u['uid'],0,1);
                $userInfo['feed_count'] = $feeds['size'];
                $userInfo['self'] =  0;
                $userInfo['relation'] = $followModel->getRelation($user['uid'],$u['uid']);
            }else{
                $feedModel = new FeedModel();
                $feeds = $feedModel->getUserList($u['uid'],0,1);
                $userInfo['feed_count'] = $feeds['size'];
                $userInfo['self'] =  0;
            }
            if($data['token'] &&$user['uid']==$d['uid']){
                $userInfo['home_cover'] = $userInfo['home_cover'] ? IMG_DOMAIN.$userInfo['home_cover'] : PUBLIC_DOMAIN.'default_app_cover.jpg';
            }else{
                $userInfo['home_cover'] = $userInfo['home_cover'] ? IMG_DOMAIN.$userInfo['home_cover'] : PUBLIC_DOMAIN.'default_app_home.jpg';
                $g_id = $followModel->getGroupByUid($user['uid'],$d['uid']);
                $userInfo['in_group_id'] = $g_id['group_id'];
            }
        }
        Common::appLog('user/getBasicInfoByDid',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$userInfo ? $userInfo :(object)array());
    }
    //个人基本资料
    public function getBasicInfoAction(){
        $token = $this->getRequest()->getPost('token');//用户token
        $user['uid'] = 0;
        $uid = $this->getRequest()->getPost('uid');//查看用户的uid
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
        }
        if(!$uid){
            Common::echoAjaxJson(2, "用户错误");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        $stageModel  =  new StageModel();
        $userInfo = $userModel->getUserInfoByUid($uid);
        $addressModel = new AddressModel();
        $userInfo['location_province_name'] = $addressModel->getNameById($userInfo['location_province_id']);
        $userInfo['location_city_name'] = $addressModel->getNameById($userInfo['location_city_id']);
        $userInfo['location_town_name'] = $addressModel->getNameById($userInfo['location_town_id']);
        $userInfo['hometown_province_name'] = $addressModel->getNameById($userInfo['hometown_province_id']);
        $userInfo['hometown_city_name'] = $addressModel->getNameById($userInfo['hometown_city_id']);
        $userInfo['hometown_town_name'] = $addressModel->getNameById($userInfo['hometown_town_id']);
        if($userInfo['birthday_type'] == 1&&$userInfo['year']&&$userInfo['month']&&$userInfo['day']){
            $userInfo['birthday'] = $userInfo['year'].'年'.$userInfo['month'].'月'.$userInfo['day'].'日';
            $userInfo['birthday_val'] = $userInfo['year'].'-'.$userInfo['month'].'-'.$userInfo['day'];
        }elseif($userInfo['birthday_type'] == 2&&$userInfo['year']&&$userInfo['month']&&$userInfo['day']){
            if($userInfo['year'] && $userInfo['month'] && $userInfo['day']){
                $lunar = new Lunar();
                $date = $lunar->convertSolarToLunar($userInfo['year'],$userInfo['month'],$userInfo['day']);
                $userInfo['birthday'] = $date[3].'年('.$date[0].') '.$date[1].' '.$date[2];
                $userInfo['birthday_val'] = $date[0].'-'.$date[4].'-'.$date[5];
            }
        }else{
            $userInfo['birthday'] ='';
            $userInfo['birthday_val'] = '';
        }
        $u =  $userModel->getUserData($uid,$user['uid']);
        $userInfo['did'] = $u['did'];
        $userInfo['nick_name'] = $u['nick_name'];
        $userInfo['name'] = $u['name'];
        $userInfo['relation'] = $u['relation'];
        $userInfo['avatar'] = Common::show_img($u['avatar'],1,200,200);
        $userInfo['original_avatar'] = $u['avatar'];
        $u_info = $userModel->getUserByUid($uid);
        if($u_info['qrcode_img']){
            $userInfo['qrcode_img'] = IMG_DOMAIN.$u_info['qrcode_img'];
        }else{
            $userInfo['qrcode_img'] = '';
        }
        $userInfo['att_num'] = $u['att_num'];
        $userInfo['fans_num'] = $u['fans_num'];
        $albumModel = new AlbumModel();
        if($token&&$user['uid']==$uid){
            $userInfo['stage_num'] = $u['stage_num'];
            $userInfo['album_num'] = $albumModel->getNum($uid);
        }else{
            $userInfo['stage_num'] = $stageModel->getStageOther($uid);
            $userInfo['album_num'] = $albumModel->getNum($uid,$user['uid']);
        }
        $userInfo['type'] = $u['type'];
        $userInfo['add_time'] = $u['add_time'];
        $userInfo['angel_info'] = '';
        if(in_array($u['type'],array(2,3,4,5))){
          $angelInfo = $userModel->getInfo($uid);
          $userInfo['angel_info'] = $angelInfo['info'];
          //$userInfo['intro'] = $angelInfo['info'];
        }
        $tagModel = new TagModel();
        $tag_type = 1;
        $tagList = $tagModel->getRelation($tag_type,$uid);
        $tagNewList = array();
        foreach($tagList as $key=>$val){
            $tagNewList[$key]['id'] = $val['tag_id'];
            $tagNewList[$key]['content'] = $val['content'];
        }
        if($tagNewList){
            $userInfo['tag'] = $tagNewList;
        }else{
            $userInfo['tag'] = array();
        }

        $userInfo['expInfo']=Common::getUserLevel($userInfo['exp']);
        if(isset($user['uid'])&&$user['uid']==$uid){
            $feedModel = new FeedModel();
            $feeds = $feedModel->getUserList($user['uid'],0,1);
            $userInfo['feed_count'] = $feeds['size'];
            $userInfo['self'] =  1;
        }elseif(isset($user['uid'])&&$user['uid']!=$uid){
            $feedModel = new FeedModel();
            $followModel = new FollowModel();
            $feeds = $feedModel->getUserList($uid,0,1);
            $userInfo['feed_count'] = $feeds['size'];
            $userInfo['self'] =  0;
            $userInfo['relation'] = $followModel->getRelation($user['uid'],$uid);
            $followModel = new FollowModel();
            $g_id = $followModel->getGroupByUid($user['uid'],$uid);
            $userInfo['in_group_id'] = $g_id['group_id'];
        }else{
            $feedModel = new FeedModel();
            $feeds = $feedModel->getUserList($uid,0,1);
            $userInfo['feed_count'] = $feeds['size'];
            $userInfo['self'] =  0;
        }
        if($token&&$user['uid']==$uid){
            $userInfo['home_cover'] = $userInfo['home_cover'] ? IMG_DOMAIN.$userInfo['home_cover'] : PUBLIC_DOMAIN.'default_app_cover.jpg';
        }else{
            $userInfo['home_cover'] = $userInfo['home_cover'] ? IMG_DOMAIN.$userInfo['home_cover'] : PUBLIC_DOMAIN.'default_app_home.jpg';

        }
        Common::appLog('user/getBasicInfo',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$userInfo ? $userInfo :(object)array());
    }
    //修改个人基本信息
    public function updateBasicInfoAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(21, "非法登录用户");
        }
        $type = $this->getRequest()->getPost('type');//修改的类型 1:背景图 2昵称 3简介 4头像 5性别 6家乡 7现居地 8生日,9个人资料页背景图
        $home_cover = $this->getRequest()->getPost('home_cover');//背景图
        $nick_name = $this->getRequest()->getPost('nick_name');//昵称
        $intro = $this->getRequest()->getPost('intro');//简介
        $avatar = $this->getRequest()->getPost('avatar');//头像
        $sex = $this->getRequest()->getPost('sex');//性别
        $hometown_town_id = $this->getRequest()->getPost('hometown_town_id');//家乡地区id
        $location_town_id = $this->getRequest()->getPost('location_town_id');//现居地地区id
        $birthday_type = $this->getRequest()->getPost('birthday_type');//1阴历 2阳历
        $birthday_status = $this->getRequest()->getPost('birthday_status');//1所有人可见 2我关注的人 3知己 4仅自己
        $app_cover = $this->getRequest()->getPost('app_cover');//个人资料页背景图
        $year = $this->getRequest()->getPost('year');
        $month = $this->getRequest()->getPost('month');
        $day = $this->getRequest()->getPost('day');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        if($type == 1){
            if(!$home_cover){
                Common::echoAjaxJson(2, "背景图片错误");
            }
        }elseif($type == 2){
            $rs = $userModel->nickNameIsExist($nick_name,$user['uid']);
            if($rs == -1){
                Common::echoAjaxJson(3,'昵称不能为空');
            }
            if(!preg_match('/^[\x{4e00}-\x{9fa5}0-9]{2,8}$/u',$nick_name)){
                Common::echoAjaxJson(4,'昵称为2-8位的中文或中文加数字。');
            }
            if(preg_match('/^[0-9]*$/',$nick_name)){
                Common::echoAjaxJson(3,'昵称为2-8位的中文或中文加数字。');
            }
            if(Common::badWord($nick_name)){
                Common::echoAjaxJson(5,'昵称含有敏感词');
            }
            if($rs > 0){
                Common::echoAjaxJson(6,'此昵称太受欢迎，已有人抢了');
            }
        }elseif($type==3){
            if(!$intro){
               $intro = '传承传统文化是每一个人的使命！';
            }
            $intro = Common::angleToHalf($intro);
            if(preg_match('/[A-Za-z]{1,}/',$intro)){
                Common::echoAjaxJson(7,'用户简介不能包含英文字符');
            }
            $intro_num = mb_strlen($intro,'utf-8');
            if($intro_num > 70){
                Common::echoAjaxJson(8,'您输入的的简介超出指定长度');
            }
            $security = new Security();
            $intro = $security->xss_clean($intro);
        }elseif($type==4){
            if(!$avatar){
                Common::echoAjaxJson(9, "用户头像错误");
            }
        }elseif($type ==5){
            if(!$sex||!in_array($sex,array(1,2))){
                Common::echoAjaxJson(10, "用户性别错误");
            }
        }elseif($type ==6){
            if(!$hometown_town_id){
                Common::echoAjaxJson(11, "选择的家乡不正确");
            }
        }elseif($type==7){
            if(!$location_town_id){
                Common::echoAjaxJson(12, "选择的现居地不正确");
            }
        }elseif($type==8){
            if(!$birthday_status||!in_array($birthday_status,array(1,2,3,4))){
                Common::echoAjaxJson(13, "生日权限不正确");
            }
            if(!$birthday_type||!in_array($birthday_type,array(1,2))){
                Common::echoAjaxJson(14, "生日类型不正确");
            }
            if(!$year || !$month || !$day){
                Common::echoAjaxJson(15, "生日不正确");
            }
            if($birthday_type == 2){
                $lunar = new Lunar();
                $date = $lunar->convertLunarToSolar($year,$month,$day);
                list($year,$month,$day) = $date;
            }
        }elseif($type == 9){
            if(!$app_cover){
                Common::echoAjaxJson(22, "背景图片错误");
            }
        }
        $rs = $userModel->modifyUserInfo($user['uid'],$sex,$birthday_type,$birthday_status,$year,$month,$day,$location_town_id,$hometown_town_id,$intro,$nick_name,$avatar,$home_cover,$app_cover);
        if($rs == 0){
            Common::echoAjaxJson(16,'没有找到用户');
        }
        if($rs == -1){
            Common::echoAjaxJson(17,'现居地城市不存在');
        }
        if($rs == -2){
            Common::echoAjaxJson(18,'家乡城市不存在');
        }
        if($rs == -3){
            Common::echoAjaxJson(19,'现居省不存在');
        }
        if($rs == -4){
            Common::echoAjaxJson(20,'家乡省不存在');
        }
        if($type==4){
            //添加用户头像到头像相册
            $albumModel = new AlbumModel();
            $album_id = $albumModel->getInitAlbumId($user['uid'],1);
            $photo[0] = $avatar;
            $intro[0] = '';
            $add_time = date("Y-m-d H:i:s");
            $ret = $albumModel->getAlbumImages($user['uid'],$album_id,$avatar);
            if(!$ret){
                $albumModel->addPhoto($user['uid'],$album_id,$photo,$intro,$add_time,1);
            }
            $userModel->addHistoryAvatar($user['uid'],$avatar);
        }
        PHPQRCode::getUserPHPQRCode($user['uid'],true);
        Common::appLog('user/updateBasicInfo',$this->startTime,$version);
        Common::echoAjaxJson(1,'基本资料修改成功');
    }

    //用户推荐头像表
    public function recommendAvatarAction(){
        $token = $this->getRequest()->getPost('token');//用户token
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        $avatar = $userModel->getRecommendAvatar();
        foreach($avatar as $k=>$v){
            $avatar[$k]['img'] = $v['path'];
            $avatar[$k]['path'] = Common::show_img($v['path'],1,200,200);
        }
        Common::appLog('user/recommendAvatar',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$avatar ? $avatar : array());
    }
    //验证第三方用户是否绑定过
     public function isBindAction(){
         $user = Common::isLogin($_POST);
         if(!$user){
             Common::echoAjaxJson(3, "非法登录用户");
         }
         $data = array();
         $userModel = new UserModel();
         $userInfo = $userModel->getUserByUid($user['uid']);
         if($userInfo['reg_type'] == 3){
             $info = $userModel->getUserInfoByUid($user['uid']);
             $pwd = $userModel->generatePassword('',$userInfo['salt']);
             if($info['bind_name']&&$pwd == $userInfo['pwd']){
                 $data['bind']=1;
                 $data['set_pwd']=0;
             }
             if($info['bind_name']&&$pwd != $userInfo['pwd']){
                 $data['bind']=1;
                 $data['set_pwd']=1;
             }
             if(!$info['bind_name']&&$pwd == $userInfo['pwd']){
                 $data['bind']=0;
                 $data['set_pwd']=0;
             }
             if(!$info['bind_name']&&$pwd != $userInfo['pwd']){
                 $data['bind']=0;
                 $data['set_pwd']=1;
             }
             Common::echoAjaxJson(1, "获取成功",$data);
         }
         Common::echoAjaxJson(2, "不是第三方用户",(object)array());
     }
    /**
     *  绑定手机发送短信验证
     */
    public function sendSmsCodeAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(8, "非法登录用户");
        }
        $mobile = strtolower($this->getRequest()->getPost('mobile'));
        $bind_type=$this->getRequest()->getPost('bind_type') ? $this->getRequest()->getPost('bind_type') : 1;
        $country_code = $this->getRequest()->getPost('country_code') ? $this->getRequest()->getPost('country_code') : '+86';//手机注册国家区号
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!preg_match('/^[0-9]{5,20}$/',$mobile)){
            Common::echoAjaxJson(9,'请输入正确手机号格式');
        }

        if($bind_type==1){
            $userModel = new UserModel();
            $rs = $userModel->getUserByUserName($mobile,$country_code);
            if($rs){
                Common::echoAjaxJson(6,'此手机号已被注册');
            }
            $ret = $userModel->isBindNameUsed($mobile,$country_code);
            if($ret){
                Common::echoAjaxJson(7,'此手机号已被绑定');
            }
            $type = 6;
        }else{
            $type = 11;
        }
        $smsModel = new SmsModel();
        if($country_code =='+86'){
            $sms_type=1;
        }else{
            $sms_type=2;
        }
        $status = $smsModel->addSmsCode($user['uid'],$mobile,$type,$sms_type,$country_code);
        if($status == -1){
            Common::echoAjaxJson(2,'验证码类型不正确');
        }else if($status == -2){
            Common::echoAjaxJson(3,'24小时内发送的短信超出次数');
        }else if($status == -3){
            Common::echoAjaxJson(4,'短信发送太频繁');
        }else if($status == -4){
            Common::echoAjaxJson(5,'短信发送失败，请重新点击发送');
        }
        Common::appLog('user/sendSmsCode',$this->startTime,$version);
        Common::echoAjaxJson(1,'验证码发送成功');
    }
    //绑定手机
    public function bindMobileAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(10, "非法登录用户");
        }
        $uid = $user['uid'];
        $mobile = $this->getRequest()->getPost('mobile');
        $code = $this->getRequest()->getPost('code');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $country_code = $this->getRequest()->getPost('country_code') ? $this->getRequest()->getPost('country_code') : '+86';//国家区号
        if(!preg_match('/^[0-9]{5,20}$/',$mobile)){
            Common::echoAjaxJson(7,'请输入正确手机号格式');
        }
        if(!preg_match('/^\d{6}$/',$code)){
            Common::echoAjaxJson(3,'验证码格式不正确');
        }
        $smsModel = new SmsModel();
        $userModel = new UserModel();
        $sms_info = $smsModel->getSmsCode($country_code.$mobile,6);
        if($sms_info['code'] != $code){
            Common::echoAjaxJson(4,'验证码不正确');
        }
        $is_bind = $userModel->isBindMobile($uid);
        if(!$is_bind){
            $ret = $userModel->addBind($uid,$mobile,$country_code,'mobile');
        }else{
            $ret = $userModel->updateBind($mobile,$country_code,$uid);
        }
        Common::appLog('user/bindMobile',$this->startTime,$version);
        if($ret){
            Common::echoAjaxJson(1,'绑定成功');
        }else{
            Common::echoAjaxJson(2,'绑定失败');
        }
    }
    //获取个人资料页的默认封面
    public function getDefaultCoverAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        $rs = $userModel->getDefaultCover();
        Common::appLog('user/getDefaultCover',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$rs ? $rs :array());
    }
    //用户新粉丝页面
    public function newFansAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $uid = $user['uid'];
        $page = intval($this->getRequest()->getPost('page'));
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==20) ? $this->getRequest()->getPost('size') : 20;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $page = $page ? $page : 1;
        $followModel = new FollowModel();
        $indexModel = new IndexModel();
        $list = array();
        $list['fans_list'] = $followModel->getNewFans($uid);
        $list['angel_list'] = $indexModel ->getAngelByLoginTime($uid,$page,(int)$size);
        Common::appLog('user/newFans',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    /***********3.0***************/
    //验证手机号能否绑定
    public function mobileIsBindAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(6, "非法登录用户");
        }
        $userModel = new UserModel();
        $userBindModel = new UserBindModel();
        $bind_name = intval($this->getRequest()->getPost('mobile'));
        $country_code = $this->getRequest()->getPost('country_code') ? $this->getRequest()->getPost('country_code') : '+86';//国家区号
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$bind_name){
            Common::echoAjaxJson(2,'请输入手机号');
        }
        if(!preg_match('/^[0-9]{5,20}$/',$bind_name)){
            Common::echoAjaxJson(3,'请输入正确手机号格式');
        }
        $rs = $userModel->userNameIsExistForBind($bind_name,$country_code);
        if($rs > 0){
            Common::echoAjaxJson(4,'此手机号已被注册');
        }
        $ret = $userBindModel->isBindNameUsed($bind_name,$country_code);
        if($ret  &&  $ret != $user['uid']){
            Common::echoAjaxJson(5,'此手机号已被绑定');
        }
        Common::appLog('user/mobileIsBind',$this->startTime,$version);
        Common::echoAjaxJson(1,'此手机号可以绑定');
    }
    //验证用户能否绑定手机 （共3种情况 能绑定 已绑定 不能绑定）
    public function getUserBindTypeAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if($user['reg_type']==2){
            Common::echoAjaxJson(2, "对不起，您是手机注册用户，不能再次绑定手机号码");
        }
        //查询用户是否已绑定过手机号码
        $userModel = new UserModel();
        $info = $userModel->getUserInfoByUid($user['uid']);
        if($info['bind_name']){
            if($version<'3.7.2'){
                Common::echoAjaxJson(3, "该用户已绑定",$info['bind_name']);
            }else{
                Common::echoAjaxJson(3, "该用户已绑定",array('bind_name'=>$info['bind_name'],'country_code'=>$info['bind_country_code'] ? $info['bind_country_code'] : '+86'));
            }
        }
        Common::appLog('user/getUserBindType',$this->startTime,$version);
        Common::echoAjaxJson(1,'该用户还未绑定');
    }
    //个人资料页－－用户心境列表
    public function getMoodListAction(){
        $data['token'] = $this->getRequest()->get('token');
        $user['uid'] = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $uid = $this->getRequest()->getPost('uid');
        $last_id = $this->getRequest()->getPost('last_id') ? $this->getRequest()->getPost('last_id') : 0;
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==10) ? $this->getRequest()->getPost('size') : 10;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $flag = ($user['uid']==$uid) ? 0 : 1;
        if($flag==0){
            $uid = $user['uid'];
        }
        $moodModel = new MoodModel();
        $list = $moodModel->getMoodListByUid($last_id,(int)$size,$flag,$uid);
        Common::appLog('user/getMoodList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    //猜你喜欢
    public function guessYouLikeAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $size = intval($this->getRequest()->getPost('size'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $page = rand(1,60);
        if(!$size||$size<1){
            $size =10;
        }
        if($size > 50){
            $size = 50;
        }
        $uid = $user['uid'];
        $userModel = new UserModel();
        $user = $userModel->getUserData($uid);
        if(!$user){
            Common::echoAjaxJson(2,"没有找到此用户");
        }
        if($user['status']==2){
            Common::echoAjaxJson(3,"此用户已被禁止使用");
        }
        $guess = 1;
        $recommendModel = new RecommendModel();
        $data = $recommendModel->similarF($page,$size,$uid, $uid,$guess);
        if(!$data){
            $data = $recommendModel->getHotUser($page,$size,$uid);
        }
        foreach($data['list'] as $k=>$v){
            $data['list'][$k]['avatar'] = Common::show_img($v['avatar'],1,160,160);
        }
        Common::appLog('user/guessYouLike',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$data ? $data : (object)array());
    }
    //APP'+'号 用户发布内容
    public function userAddTypeAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') :APP_VERSION;
        $stageModel = new StageModel();
        $service_sid = $stageModel->getSidByUid($uid);//用户是否有服务驿站
        $culture_sid = $stageModel->getCultureSidByUid($uid);//用户是否有能发帖的驿站
        Common::appLog('user/userAddType',$this->startTime,$version);
        if($service_sid['sid']){
            Common::echoAjaxJson(1, "该用户能发布帖子,服务,商品",$service_sid['sid']);
        }
        if(!$service_sid['sid']&&$culture_sid>0){
            Common::echoAjaxJson(3, "该用户能发布帖子");
        }
        if(!$service_sid['sid']&&$culture_sid==0){
            Common::echoAjaxJson(4, "该用户不能发布帖子,服务,商品");
        }
    }
    //获取才府应用
    public function getAppApplyAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        Common::appLog('user/getAppApply',$this->startTime,$version);
        $indexModel = new IndexModel();
        $data = $indexModel->indexBanner('app_apply',$_POST['token']);
        Common::appLog('user/getAppApply',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$data);
    }
    //获取用户买到的和卖出的订单数量
    public function getUserGoodsOrderNumAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $type = $this->getRequest()->getPost("type");//1.我买到的 2.我卖出的
        $stagegoodsModel = new StagegoodsModel();
        if($type==1){
            $data['no_pay'] = $stagegoodsModel->getMyOrderListNum($uid,1);//待付款
            $data['no_send'] = $stagegoodsModel->getMyOrderListNum($uid,2);//待发货
            $data['is_send'] = $stagegoodsModel->getMyOrderListNum($uid,6);//已发货
        }elseif($type==2){
            $stageModel = new StageModel();
            $sid = $stageModel->getSidByUid($uid);
            $data['no_pay'] = $stagegoodsModel->getSellOrderListNum($sid['sid'],1);//待付款
            $data['no_send'] = $stagegoodsModel->getSellOrderListNum($sid['sid'],2);//待发货
            $data['is_send'] = 0;
        }
        Common::appLog('user/getUserGoodsOrderNum',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$data);
    }
    //判断用户是否能发布商品,服务,帖子
    public function isAddForUserAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(12, "非法登录用户");
        }
        $uid = $user['uid'];
        $type = $this->getRequest()->getPost("type");//1帖子 2服务 3商品
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $stageModel = new StageModel();
        if(!$type){
            Common::echoAjaxJson(2, "判断的类型不正确");
        }
        if($type==1){
            $addTopic_num = $stageModel->getCultureSidByUid($uid);//用户是否有能发帖的驿站
            if($addTopic_num>0){
                Common::echoAjaxJson(3, "可以发帖");
            }
            Common::echoAjaxJson(4, "加入驿站后，拥有发帖权限");
        }elseif($type==2||$type==3){
            if($type==2){
                $messgae = '活动';
            }elseif($type==3){
                $messgae = '商品';
            }
            $creat_p_1  = $stageModel->getCreatePstatus($uid,1);//是否有创建成功的普通驿站
            $creat_p_0  = $stageModel->getCreatePstatus($uid,0);//是否有审核中的普通驿站
            $creat_p_2  = $stageModel->getCreatePstatus($uid,2);//是否有审核不通过的普通驿站
            $creat_f = $stageModel->getCreateBstatus($uid);//是否创建过服务驿站及状态
            //未创建驿站
            if(!$creat_p_1&&!$creat_p_0&&!$creat_p_2&&!$creat_f){
                Common::echoAjaxJson(5, "创建服务驿站，可立即发布".$messgae."");
            }
            //已成功创建【服务 驿站】
            if($creat_f&&$creat_f['status']==1){
                $sid = $stageModel->getSidByUid($uid);
                Common::echoAjaxJson(6, "可以发布".$messgae."",$sid['sid']);
            }
            //【服务驿站】正在 审核中
            if($creat_f&&$creat_f['status']==0){
                Common::echoAjaxJson(7, "您的服务驿站正在审核中，审核通过后，将拥有发".$messgae."权限，请您耐心等待");
            }
            //【服务驿站】创建 未通过
            if($creat_f&&$creat_f['status']==2){
                Common::echoAjaxJson(8, "您所创建的服务驿站".$creat_f['name']."审核未通过， 审核通过后，将拥有发".$messgae."权限",$creat_f['sid']);
            }
            //已成功创建【普通 驿站】
            if(!$creat_f&&$creat_p_1){
                Common::echoAjaxJson(9, "您的驿站未开通服务功能，提交资料，升级为服务驿站，可拥有发".$messgae."权限。");
            }
            //【普通 驿站】开通服务功能但审核不通过
//            if(!$creat_f&&$creat_p_1&&$creat_p_3){
//                //Common::echoAjaxJson(9, "您的驿站未开通服务功能，提交资料，升级为服务驿站，可拥有发".$messgae."权限。");
//            }
            //【普通驿站】创建 未通过
            if(!$creat_f&&!$creat_p_1&&!$creat_p_0&&$creat_p_2){
                Common::echoAjaxJson(10, "您创建的普通驿站审核未通过。 审核通过后，才能开通服务功能，发布".$messgae."。");
            }
            //【普通驿站】正在 审核中
            if(!$creat_f&&!$creat_p_1&&$creat_p_0){
                Common::echoAjaxJson(11, "您的普通驿站正在审核中，普通驿站 审核通过后，才能开通服务功能，发布".$messgae."。");
            }
        }
    }
    //更换绑定手机原手机号验证
    public function modifySmsCodeAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $mobile = $this->getRequest()->getPost('mobile');
        $code = $this->getRequest()->getPost('code');
        $country_code = $this->getRequest()->getPost('country_code') ? $this->getRequest()->getPost('country_code') : '+86';//国家区号
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!preg_match('/^[0-9]{5,20}$/',$mobile)){
            Common::echoAjaxJson(3,'请输入正确手机号');
        }
        if(!preg_match('/^\d{6}$/',$code)){
            Common::echoAjaxJson(4,'验证码格式不正确');
        }
        $smsModel = new SmsModel();
        $sms_info = $smsModel->getSmsCode($country_code.$mobile,11);
        Common::appLog('user/modifySmsCode',$this->startTime,$version);
        if($sms_info['code'] != $code){
            Common::echoAjaxJson(2,'验证码不正确');
        }
            Common::echoAjaxJson(1,'验证码正确');
    }
    //分享有奖首页数据
    public function commissionIndexAction(){
        $token = $this->getRequest()->getPost('token');//用户登录token
        $uid = 0;
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        $list = $userModel->commissionIndex($uid,$token,$version);
        Common::appLog('user/commissionIndex',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    //用户是否能提现
    public function isWithDrawAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $uid = $user['uid'];
        $commonModel = new CommonModel();
        //提现成功为基础
        $day_max_num = 3;//每日提现次数
        $month_max_num = 5;//每月提现数次
        $min_money = $commonModel->getPlatformRuleByType(1) ? $commonModel->getPlatformRuleByType(1) : 100;//最小提现金额
        $max_money = 20000;//单笔提现金额
        $day_max_money = 50000;//每日最大提现金额
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        $user = $userModel->getUserByUid($uid);
        $user_info = $userModel->getUserInfoByUid($uid);
        if(in_array($user['reg_type'],array(1,3))){
            if(!$user_info['bind_name']){
                Common::echoAjaxJson(6,'您还未绑定手机号,无法提现');
            }
        }
        $money_bag = $userModel->getMoneyBagByUid($uid);
        if($money_bag<$min_money){
            Common::echoAjaxJson(2,'金额到达'.$min_money.'元后才可申请提现');
        }
        $today = date('Y-m-d').'00:00:00';
        $month = date('Y-m-01', strtotime(date("Y-m-d")));
        $today_num = $userModel->getUserWithdrawNum($uid,$today);
        if($today_num>=$day_max_num){
            Common::echoAjaxJson(3,'每日最多能提现'.$day_max_num.'次');
        }
        $month_num = $userModel->getUserWithdrawNum($uid,$month);
        if($month_num>=$month_max_num){
            Common::echoAjaxJson(4,'每月最多能提现'.$month_max_num.'次');
        }
        $is_withdraw = $userModel->getUserWithdrawMoney($uid,$today);
        if(($day_max_money-$is_withdraw)<=0){
            Common::echoAjaxJson(6,'每日最大提现金额为'.$day_max_money.'元');
        }
        $info['money_bag'] = $userModel->getMoneyBagByUid($uid);//钱袋
        //查询用户注册手机号
        $reg_info = $userModel->getRegInfoByUid($uid);
        if($reg_info){
            $info['mobile'] = $reg_info['user_name'];
            $info['country_code'] = $reg_info['reg_country_code'];
        }else{
            //查询用户绑定手机号码
            $userModel = new UserModel();
            $bind_info = $userModel->getUserInfoByUid($uid);
            $info['mobile'] = $bind_info['bind_name'];

            $info['country_code'] = $bind_info['bind_country_code'] ? $bind_info['bind_country_code'] : '+86';

        }
        $info['min_money'] = $min_money;
        $info['ali_pay'] =  array('请输入正确的姓名和支付宝账号',
            '每人每天最多可提现3次',
            '每人每月最多可提现5次，单天最高可提现5万，单笔最高可提现2万',
            '我们将在您申请后的第2天处理，节假日顺延');
        $info['bank_pay'] = array('请输入正确的开户名和银行卡号',
            '每人每天最多可提现3次',
            '每人每月最多可提现5次，单天最高可提现5万，单笔最高可提现2万',
            '1000元及以下手续费1元，1000元以上手续费3元',
            '我们将在您申请后的第2天处理，节假日顺延');
        Common::appLog('user/isWithDraw',$this->startTime,$version);
        Common::echoAjaxJson(1,'可以提现',$info);
    }
    //用户分享有奖报表
    public function getUserSpStatisticsAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $uid = $user['uid'];
        $userModel = new UserModel();
        $info['money_bag'] = $userModel->getMoneyBagByUid($uid);//钱袋
        $info['yesterday']['visit_num']        = $userModel->getUserSpLogInfo($uid,1,1);
        $info['yesterday']['orders_num']       = $userModel->getUserSpLogInfo($uid,2,1);
        $info['yesterday']['orders_price']     = $userModel->getUserSpLogInfo($uid,3,1);
        $info['yesterday']['unuse_price']      = $userModel->getUserSpLogInfo($uid,4,1);
        $info['seventh_day']['visit_num']      = $userModel->getUserSpLogInfo($uid,1,7);
        $info['seventh_day']['orders_num']     = $userModel->getUserSpLogInfo($uid,2,7);
        $info['seventh_day']['orders_price']   = $userModel->getUserSpLogInfo($uid,3,7);
        $info['seventh_day']['unuse_price']    = $userModel->getUserSpLogInfo($uid,4,7);
        $info['thirtieth_day']['visit_num']    = $userModel->getUserSpLogInfo($uid,1,30);
        $info['thirtieth_day']['orders_num']   = $userModel->getUserSpLogInfo($uid,2,30);
        $info['thirtieth_day']['orders_price'] = $userModel->getUserSpLogInfo($uid,3,30);
        $info['thirtieth_day']['unuse_price']  = $userModel->getUserSpLogInfo($uid,4,30);
        Common::appLog('user/getUserSpStatistics',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$info);
    }
    //我界面 获取数据
    public function getInfoAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $uid = $user['uid'];
        $userModel = new UserModel();
        $followModel = new FollowModel();
        $indexModel = new IndexModel();
        $info = $userModel->getUserData($uid);
        $user_info['uid'] = $uid;
        $user_info['did'] = $info['did'];
        $user_info['nick_name'] = $info['nick_name'];
        $user_info['avatar'] = $info['avatar'];
        $user_info['type'] = $info['type'];
        $user_info['sex'] = $info['sex'];
        $user_info['score'] = $info['score'];
        $user_info['friend_num'] = $followModel->getFriendNum($uid);
        $collectModel = new CollectModel();
        $feeds = $collectModel->getFeedList($user['uid'],0,10,0);
        $user_info['collect_num'] = $feeds['size'];
        $user_info['money_bag'] = $userModel->getMoneyBagByUid($uid);//钱袋
        $user_info['commission'] = '最高50%奖金';
        $app_list = $indexModel->indexBanner('app_apply',$_POST['token']);
        if($app_list){
            foreach($app_list as $k=>$v){
                $user_info['app_apply'][$k]['name'] = $v['name'];
                $user_info['app_apply'][$k]['url'] = $v['url'];
                $user_info['app_apply'][$k]['img'] = $v['img'];
            }
        }else{
            $user_info['app_apply'] = array();
        }
        Common::appLog('user/getInfo',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$user_info);
    }
    //发送提现验证码
    public function sendWithDrawSmsCodeAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(7, "非法登录用户");
        }
        $mobile = strtolower($this->getRequest()->getPost('mobile'));
        $country_code = $this->getRequest()->getPost('country_code') ? $this->getRequest()->getPost('country_code') : '+86';//手机注册国家区号
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!preg_match('/^[0-9]{5,20}$/',$mobile)){
            Common::echoAjaxJson(6,'请输入正确手机号格式');
        }
        $smsModel = new SmsModel();
        if($country_code =='+86'){
            $sms_type=1;
        }else{
            $sms_type=2;
        }
        $status = $smsModel->addSmsCode($user['uid'],$mobile,12,$sms_type,$country_code);
        if($status == -1){
            Common::echoAjaxJson(2,'验证码类型不正确');
        }else if($status == -2){
            Common::echoAjaxJson(3,'24小时内发送的短信超出次数');
        }else if($status == -3){
            Common::echoAjaxJson(4,'短信发送太频繁');
        }else if($status == -4){
            Common::echoAjaxJson(5,'短信发送失败，请重新点击发送');
        }
        Common::appLog('user/sendWithDrawSmsCode',$this->startTime,$version);
        Common::echoAjaxJson(1,'验证码发送成功');
    }
    //用户申请提现
    public function userWithDrawAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(20, "非法登录用户");
        }
        $uid = $user['uid'];
        $commonModel = new CommonModel();
        $day_max_num = 3;//每日提现次数
        $month_max_num = 5;//每月提现数次
        $min_money = $commonModel->getPlatformRuleByType(1) ? $commonModel->getPlatformRuleByType(1) : 100;//最小提现金额
        $max_money = 20000;//单笔提现金额
        $day_max_money = 50000;//每日最大提现金额
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $money = $this->getRequest()->getPost("money");//提款金额
        $type = $this->getRequest()->getPost("type");//提款方式 1支付宝 2银行卡
        $name = $this->getRequest()->getPost("name");//提款人姓名
        $account_number = $this->getRequest()->getPost("account_number");//提款账号
        $bank = $this->getRequest()->getPost("bank");//开户行
        $mobile = $this->getRequest()->getPost('mobile');
        $code = $this->getRequest()->getPost('code');
        $country_code = $this->getRequest()->getPost('country_code') ? $this->getRequest()->getPost('country_code') : '+86';//国家区号
        $userModel = new UserModel();
        $poundage = 0;//手续费
        if(!$money){
            Common::echoAjaxJson(5,'请输入提现金额');
        }
        if($money>$max_money){
            Common::echoAjaxJson(6,'单笔提款金额不能超过'.$max_money.'元');
        }
        $money_bag = $userModel->getMoneyBagByUid($uid);
        if($money_bag<$min_money){
            Common::echoAjaxJson(2,'金额到达'.$min_money.'元后才可申请提现');
        }
        $today = date('Y-m-d').'00:00:00';
        $month = date('Y-m-01', strtotime(date("Y-m-d")));
        $today_num = $userModel->getUserWithdrawNum($uid,$today);
        if($today_num>=$day_max_num){
            Common::echoAjaxJson(3,'每日最多能提现'.$day_max_num.'次');
        }
        $month_num = $userModel->getUserWithdrawNum($uid,$month);
        if($month_num>=$month_max_num){
            Common::echoAjaxJson(4,'每月最多能提现'.$month_max_num.'次');
        }
        $is_withdraw = $userModel->getUserWithdrawMoney($uid,$today);
        if(($day_max_money-$is_withdraw)<=0){
            Common::echoAjaxJson(7,'每日最大提现金额为'.$day_max_money.'元，您今日还能提现'.($day_max_money-$is_withdraw).'元');
        }
        if(!$type){
            Common::echoAjaxJson(8,'请选择提款方式');
        }
        if($type==1){
            if(!$name){
                Common::echoAjaxJson(9,'请输入姓名');
            }
            if(!preg_match('/^[\x{4e00}-\x{9fa5}]{1,10}$/u',$name)){
                Common::echoAjaxJson(10,'姓名必须为10个字以内的中文字符');
            }
            if(!$account_number){
                Common::echoAjaxJson(11,'请填写支付宝账户');
            }
            if(strlen($account_number)>40){
                Common::echoAjaxJson(15,'请输入正确的支付宝帐户');
            }
        }elseif($type==2){
            if(!$name){
                Common::echoAjaxJson(9,'请输入开户名');
            }
            if(!preg_match('/^[\x{4e00}-\x{9fa5}]{1,10}$/u',$name)){
                Common::echoAjaxJson(10,'开户名必须为10个字以内的中文字符');
            }
            if(!$account_number){
                Common::echoAjaxJson(11,'请输入银行卡号');
            }
            if(!$bank){
                Common::echoAjaxJson(12,'请输入开户行');
            }
            if(preg_match('/[A-Za-z]{1,}/',$bank)){
                Common::echoAjaxJson(13,'开户行不能包含英文字符');
            }
            $bank_len = mb_strlen($bank,'utf-8');
            if($bank_len > 20){
                Common::echoAjaxJson(14,'请输入20个中文字内的银行名称');
            }
            if(strlen($account_number)>39){
                Common::echoAjaxJson(15,'请输入正确的银行卡号');
            }
            if($money>1000){
                $poundage=3;
            }else{
                $poundage=1;
            }
            $money = $money - $poundage;
        }
        if(!preg_match('/^[0-9]{5,20}$/',$mobile)){
            Common::echoAjaxJson(16,'请输入正确手机号');
        }
        if(!preg_match('/^\d{6}$/',$code)){
            Common::echoAjaxJson(17,'验证码格式不正确');
        }
        $smsModel = new SmsModel();
        $sms_info = $smsModel->getSmsCode($country_code.$mobile,12);
        if($sms_info['code'] != $code){
            Common::echoAjaxJson(18,'验证码不正确');
        }
        $serial_number = rand(1000,9999).rand(1000,9999).rand(1000,9999);
        //申请入库
        $rs = $userModel->addWithDraw($serial_number,$money,$poundage,$type,$name,$account_number,$bank,$uid,$mobile,$country_code);
        if(!$rs){
            Common::echoAjaxJson(19,'提交失败');
        }
        //扣除用户钱袋金额
        Common::appLog('user/userWithDraw',$this->startTime,$version);
        Common::echoAjaxJson(1,'提交成功，我们将在1-3个工作日内进行审核');
    }
    //提现页面获取数据
    public function getUserWithDrawInfoAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        $info['money_bag'] = $userModel->getMoneyBagByUid($uid);//钱袋
        //查询用户注册手机号
        $reg_info = $userModel->getRegInfoByUid($uid);
        if($reg_info){
            $info['mobile'] = $reg_info['user_name'];
            $info['country_code'] = $reg_info['reg_country_code'];
        }else{
            //查询用户绑定手机号码
            $userModel = new UserModel();
            $bind_info = $userModel->getUserInfoByUid($uid);
            $info['mobile'] = $bind_info['bind_name'];
            $info['country_code'] = $bind_info['bind_country_code'];
        }
        Common::appLog('user/getUserWithDrawInfo',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$info);
    }
    //用户银行卡提现获取银行卡数据
    public function getUserBankAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $userModel = new UserModel();
        $list = $userModel->getUserBank($uid);
        Common::appLog('user/getUserBank',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    //用户一条明细信息
    public function getOneSerialInfoAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $id = $this->getRequest()->getPost("id");
        $info_type = $this->getRequest()->getPost("info_type");
        if(!$id){
            Common::echoAjaxJson(2, "id为空");
        }
        if(!$info_type){
            Common::echoAjaxJson(2, "查看类型为空");
        }
        $userModel = new UserModel();
        $eventModel = new EventModel();
        $stagegoodsModel = new StagegoodsModel();
        $serial_info = $userModel->getOneSerialInfo($id);
        if($info_type==1){
            $info = $userModel->getOneWithDrawInfo($serial_info['obj_id']);
            $info['fact_totals'] = $info['money'];
            $info['money'] = $info['money']+$info['poundage'];
            $info['bank'] = $info['bank'] ? $info['bank'] :'';
        }elseif($info_type==2||$info_type==4){
            $order_info = $stagegoodsModel->orderInfoById($serial_info['obj_id']);
            $goods_info = $stagegoodsModel->getGoodsRedisById($order_info['goods_id']);
            $info['serial_number'] = $order_info['order_id'];
            $info['add_time'] = $order_info['add_time'];
            $info['update_time'] = $order_info['update_time'];
            $info['status'] = $order_info['order_status'];
            $info['pay_type'] = $order_info['pay_type'] ?$order_info['pay_type']:'';
            $info['money'] = $order_info['price_totals'];
            $info['commission'] = $order_info['commission'];
            $info['serial_name'] = $goods_info['name'];
            $info['service_charge'] = $order_info['service_charge'];
            $info['fact_totals'] = $order_info['fact_totals'];
            $info['order_key_id'] = $order_info['id'];
            if($order_info['sp_id']){
                 $sp_info = $userModel->getSpInfoById($order_info['sp_id']);
                 $info['commission_rate'] = ($sp_info['commission_rate']*100).'%';
            }

        }elseif($info_type==3||$info_type==5){
            $order_info = $eventModel->orderInfoById($serial_info['obj_id']);
            $event_info = $eventModel->getEventRedisById($order_info['eid']);
            $info['serial_number'] = $order_info['order_id'];
            $info['add_time'] = $order_info['add_time'];
            $info['update_time'] = $order_info['update_time'];
            $info['status'] = $order_info['order_status'];
            $info['pay_type'] = $order_info['pay_type'] ?$order_info['pay_type']:'';
            $info['money'] = $order_info['totals'];
            $info['commission'] = $order_info['commission'];
            $info['serial_name'] = $event_info['title'];
            $info['service_charge'] = $order_info['service_charge'];
            $info['fact_totals'] = $order_info['fact_totals'];
            if($order_info['sp_id']){
                $sp_info = $userModel->getSpInfoById($order_info['sp_id']);
                $info['commission_rate'] = ($sp_info['commission_rate']*100).'%';
            }
            $info['order_key_id'] = $order_info['id'];
        }
        $info['id'] = $id;
        $info['info_type'] = $info_type;
        Common::appLog('user/getSerialInfo',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$info);
    }
    //获取用户流水明细
    public function getUserSerialListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $last_id = $this->getRequest()->getPost("last_id") ? $this->getRequest()->getPost("last_id") : 0;
        $type = $this->getRequest()->getPost("type") ? $this->getRequest()->getPost("type") : 0;//0全部明细 1驿站订单 2分享有奖 3提现 4未到账收益
        $size = 10;
        $userModel = new UserModel();
        $list['money_bag'] = $userModel->getMoneyBagByUid($uid);
        $list['un_use_money'] = $userModel->getUnUseMoneyByUid($uid);
        $list['list'] = $userModel->getUserSerialList($uid,$type,$last_id,$size);
        Common::appLog('user/getUserSerialList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    //用户未到账列表
    /*public function getUserUnUserMoneyListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $last_id = $this->getRequest()->getPost("last_id") ? $this->getRequest()->getPost("last_id") : 0;
        $page = $this->getRequest()->getPost("page");
        $size = 10;
        $userModel = new UserModel();
        $list['list'] = $userModel->getUserUnUserMoneyList($uid,$last_id,$size);
        Common::appLog('user/getUserUnUserMoneyList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }*/
    //用户银行卡提现获取银行卡数据
    public function delUserBankAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $uid = $user['uid'];
        $id = $this->getRequest()->getPost("id");//银行卡id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(3, "id为空");
        }
        $userModel = new UserModel();
        $userModel->delUserBank($id);
        Common::appLog('user/delUserBank',$this->startTime,$version);
        Common::echoAjaxJson(1,'删除成功');
    }
}