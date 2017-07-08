<?php
/**
 * @name SampleModel
 * @desc sample数据获取类, 可以访问数据库，文件，其它系统等
 * @author {&$AUTHOR&}
 */
class UserModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }
    /**
     * 获取默认头像列表
     * @return mixed
     */
    public function getAvatarList($type=0){
        $condition = $type?' and type=:type':'';
        $stmt = $this->db->prepare("select id,type,name,path from avatar where status = 1 $condition");
        if($type){
            $stmt->bindValue(':type', $type, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addUser($user_name,$nick_name,$avatar,$pwd,$pwd_type,$reg_type,$status=0,$reg_country_code){
        if($avatar==''){
            $avatarList = $this->getAvatarList(3);//获取默认头像列表类型3
            if($avatarList){
                $key = array_rand($avatarList);
                $avatar = $avatarList[$key]['path'];
            }
        }
        $salt = md5(mt_rand(10000000,99999999));
        $pwd = $this->generatePassword($pwd,$salt);
        $did = $this->getDid();
        $time = date("Y-m-d H:i:s");
        $stmt = $this->db->prepare("insert into user (did,user_name,nick_name,pwd,pwd_type,salt,reg_type,reg_country_code,status,add_time,update_time,avatar) values (:did,:user_name,:nick_name,:pwd,:pwd_type,:salt,:reg_type,:reg_country_code,:status,:add_time,:update_time,:avatar)");
        $array = array(
            ':did' => $did,
            ':user_name' => $user_name,
            ':nick_name' => $nick_name,
            ':pwd' => $pwd,
            ':pwd_type' => $pwd_type,
            ':salt' => $salt,
            ':reg_type' => $reg_type,
            ':reg_country_code'=>$reg_country_code,
            ':status' => $status,
            ':add_time' => $time,
            ':update_time' => $time,
            ':avatar' => $avatar,
        );
        $stmt->execute($array);
        $uid = $this->db->lastInsertId();
        if(!$uid){
            return 0;
        }
        if($reg_type == 3){
            $stmt_user_info = $this->db->prepare("insert into user_info (uid) values (:uid)");
            $array_info = array(
                ":uid" => $uid
            );
            $stmt_user_info->execute($array_info);
        }
        //初始化关注分组-特别关注、天使、文化人、家人、同事、同学、朋友
        $stmt_group = $this->db->prepare("insert into `group` (uid,name,add_time,is_default,status,type) values ($uid,'默认分组','".$time."',1,1,1),
        ($uid,'特别关注','".$time."',0,1,1),($uid,'天使','".$time."',0,1,1),($uid,'文化人','".$time."',0,1,1),($uid,'家人','".$time."',0,1,1),
        ($uid,'同事','".$time."',0,1,1),($uid,'同学','".$time."',0,1,1),($uid,'朋友','".$time."',0,1,1)");
        $stmt_group->execute();
        //初始化用户数到队列
        $this->initRegToList($uid);
        return $uid;
    }
    //注册时添加注册用户信息
    public function addUserInfo($uid,$origin,$birthday_type,$birthday_status,$intro,$user_name){
        $stmt_user_info = $this->db->prepare("insert into user_info (uid,origin,location_province_id,location_city_id,location_town_id,hometown_province_id,hometown_city_id,hometown_town_id,sex,birthday_type,birthday_status,intro,mobile,update_time)
        values (:uid,:origin,:location_province_id,:location_city_id,:location_town_id,:hometown_province_id,:hometown_city_id,:hometown_town_id,:sex,:birthday_type,:birthday_status,:intro,:user_name,:update_time)");
        $array = array(
            ':uid' => $uid,
            ':origin' => $origin,
            ':location_province_id' => 0,
            ':location_city_id' => 0,
            ':location_town_id' => 0,
            ':hometown_province_id' => 0,
            ':hometown_city_id' => 0,
            ':hometown_town_id' => 0,
            ':sex' => 0,
            ':birthday_type' => $birthday_type,
            ':birthday_status' => $birthday_status,
            ':intro' => $intro,
            ':user_name' => $user_name,
            ':update_time' => date("Y-m-d H:i:s"),
        );
        $stmt_user_info->execute($array);
    }
    //注册后用户初始化放到队列
    public function initRegToList($uid){
        $key = "init:user:reg";
        $this->redis->rPush($key,(int)$uid);
    }

    //注册后初始化操作
    public function initUser($uid,$avatar){
        if(!$uid || !$avatar){
            return false;
        }
        $user = $this->getUserByUid($uid);
        if(!$user){
            return false;
        }
        $reg_type = $user['reg_type'];
        $user_name = $user['user_name'];
        if($reg_type == 1){
            //发送注册邮件
            $emailModel = new EmailModel();
            $code = md5(md5(mt_rand(100000,999999)).$user_name);
            $emailModel->addCode($user_name,1,$code);
            $emailModel->regVerify($user,$code);
        }
        //初始化注册信息扩展表
        $homeDressData = $this->getHomeDressData();
        $dress_key = array_rand($homeDressData['package']);
        $stmt_user_info = $this->db->prepare("update user_info set home_background = :home_background,home_flash = :home_flash,home_color = :home_color where uid = :uid");
        $array_user_info = array(
            ':home_background'=>$homeDressData['package'][$dress_key]['img'],
            ':home_flash'=>$homeDressData['package'][$dress_key]['flash'],
            ':home_color'=>$homeDressData['package'][$dress_key]['color'],
            ':uid'=>$uid,
        );
        $stmt_user_info->execute($array_user_info);
        $time = date("Y-m-d H:i:s");
        //初始化用户相册表
        $stmt_album = $this->db->prepare("insert into album(uid,name,type,is_public,status,add_time)values($uid,'头像相册',1,1,1,'".$time."'),($uid,'默认相册',2,1,1,'".$time."'),($uid,'心境相册',3,1,1,'".$time."')");
        $stmt_album->execute();
        //上传头像到头像相册
        $albumModel = new AlbumModel();
        $album_id = $albumModel->getInitAlbumId($uid,1);
        $img[0] = $avatar;
        $intro[0] = '';
        $add_time = date("Y-m-d H:i:s");
        $ret = $albumModel->getAlbumImages($uid,$album_id,$avatar);
        if(!$ret){
            $albumModel->addPhoto($uid,$album_id,$img,$intro,$add_time,1);
        }
        $this->addHistoryAvatar($uid,$avatar);
        //初始化关注
       // $this->bestUser($uid);//关注优质用户
        //初始化心境
//        $moodModel = new MoodModel();
//        $userWelcomeModel = new UserWelcomeModel();
//        $welcomeNum = $userWelcomeModel->getNum();
//        $limit = $uid % $welcomeNum;
//        $welcome = $userWelcomeModel->getWord($limit);
//        $moodModel->add($uid,$welcome);
        //初始化系统消息
        $content = '您已经成为代代传承传统文化大平台的用户，您的代代号是'.$user['did'].'，请您尽快完善个人资料。莫愁前路无知己，天下谁人不识君，我们建议您使用真实头像。感谢您对代代传承的支持。有问题请联系@8931@。';
        $noticeModel = new NoticeModel();
        $noticeModel->addNotice($uid,$content);
    }

    public function bestUser($uid){
        $followModel = new FollowModel();
        $followModel->add($uid,8931);
        $followModel->add($uid,8934);
        $followModel->add($uid,8935);
        $followModel->add($uid,8937);
        $followModel->add($uid,8938);
        $followModel->add($uid,8951);
        $followModel->add($uid,11520);
        $stmt = $this->db->prepare("SELECT uid FROM user_follow where status = 0");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            $best_users = array();
            foreach($result as $val){
                $best_users[] = $val['uid'];
            }
            //随机10个放数组$rand_user里
            $rand = array_rand($best_users,10);
            foreach($rand as $val){
                $followModel->add($uid,$best_users[$val]);//关注优质用户
            }
        }
    }
    //邀请信息初始化
    public function initInviteUser($uid,$invite_uid){
        //插入user_invite
        $stmt = $this->db->prepare("insert into user_invite (invite_uid,new_uid,add_time) values (:invite_uid,:new_uid,:add_time)");
        $array = array(
            ":invite_uid" => $invite_uid,
            ":new_uid" => $uid,
            ":add_time" => date("Y-m-d H:i:s")
        );
        $stmt->execute($array);
        //uid和invite_uid互相关注
        $followModel = new FollowModel();
        $followModel->add($uid,$invite_uid);
        $followModel->add($invite_uid,$uid);
    }

    public function generatePassword($pwd,$salt){
        return md5($salt . $pwd);
    }

    public function nickNameIsExist($nick_name,$uid=0){
        if(!$nick_name){
            return -1;
        }
        if($uid==0){
            $stmt = $this->db->prepare("select count(uid) as num from user where nick_name=:nick_name");
            $array = array(
                ':nick_name' => $nick_name,
            );
        }else{
            $stmt = $this->db->prepare("select count(uid) as num from user where nick_name=:nick_name and uid != :uid");
            $array = array(
                ':nick_name' => $nick_name,
                ':uid' => $uid,
            );
        }
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);;
        return $result['num'];
    }

    public function userNameIsExist($user_name,$reg_country_code){
        if(!$user_name){
            return -1;
        }
        $place_holders  =  implode ( ',' ,  array_fill ( 0 ,  count ( $user_name ),  '?' ));
        $stmt = $this->db->prepare("select uid,user_name from user where user_name IN($place_holders) and reg_country_code= $reg_country_code");
        $stmt->execute($user_name);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    public function userNameIsExistForBind($user_name,$reg_country_code){
        if(!$user_name){
            return -1;
        }
        $stmt = $this->db->prepare("select count(uid) as num from user where user_name=:user_name and reg_country_code=:reg_country_code");
        $array = array(
            ':user_name' => $user_name,
            ':reg_country_code'=>$reg_country_code
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    public function mobileIsExist($user_name){
        if(!$user_name){
            return -1;
        }
        $stmt = $this->db->prepare("select count(uid) as num from user where user_name=:user_name");
        $array = array(
            ':user_name' => $user_name
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    //是否被绑定（账号安全）
    public function isBindNameUsed($bind_name,$country_code=''){
        $fields = $country_code ? 'and bind_country_code='.$country_code.'' :'';
        $sql = "SELECT uid FROM user_info WHERE bind_name = :bind_name and bind_status = 1 $fields";
        $stmt = $this->db->prepare($sql);
        $array = array(':bind_name' => $bind_name);
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['uid'] : false;
    }
    //绑定手机号or邮箱
    public function addBind($uid,$bind_name,$country_code,$flag='email'){
        $bind_status = ($flag == 'email') ? 0 : 1;
        $sql = "UPDATE user_info
                SET bind_name = :bind_name,
                    bind_status = $bind_status,
                    bind_country_code=:bind_country_code
                    update_time = :update_time
                WHERE uid = :uid";
        $stmt = $this->db->prepare($sql);
        $array = array(':uid'=>$uid, ':bind_name' => $bind_name, ':bind_country_code'=>$country_code,':update_time'=>date('Y-m-d H:i:s'));
        $stmt->execute($array);
        return $stmt->rowCount();
    }

    /**
     * 修改注册邮箱
     * @param $uid
     * @param $user_name
     * @return int
     */
    public function modifyUserName($uid,$user_name){
        if(!$uid || !$user_name){
            return -1;
        }
        $stmt = $this->db->prepare("update user set user_name = :user_name where uid = :uid");
        $array = array(
            ':user_name' => $user_name,
            ':uid' => $uid,
        );
        $stmt ->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return -2;
        }
        $this->clearUserData($uid);
        return 1;
    }


    /**
     * 根据UID获取用户信息
     * @param $user_name
     * @return string
     */
    public function getUserByUid($uid){
        $stmt = $this->db->prepare("select * from user where uid=:uid");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function getUserInfoByUid($uid){
        $stmt = $this->db->prepare("select * from user_info where uid=:uid");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result){
           $result['intro'] = $result['intro']?$result['intro']:'传承传统文化是每一个人的使命！';
        }
        return $result;
    }

    /**
     * 根据用户名获取用户信息
     * @param $user_name
     * @return string
     */
    public function getUserByUserName($user_name,$country_code =''){
        $stmt = $this->db->prepare("select * from user where user_name=:user_name and reg_country_code=:reg_country_code");
        $array = array(
            ':user_name' => $user_name,
            ':reg_country_code'=>$country_code
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 根据代代号获取用户信息
     */
    public function getUserByDid($did){
        $stmt = $this->db->prepare("select * from user where did=:did");
        $array = array(
            ':did' => $did,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    /**
     * 根据昵称获取用户信息（兼容之前通过昵称登录）
     */
    public function getUserByNickName($nick_name){
        $stmt = $this->db->prepare("select * from user where nick_name=:nick_name");
        $array = array(
            ':nick_name' => $nick_name,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }


    /*
     * @name 修改用户密码
     * @param $uid
     * @param $pwd
     * @return bool
     * */
    public function resetPwd($uid,$pwd){
        $user = $this->getUserByUid($uid);
        if(!$user){
            return -1;
        }
        $pwd = $this -> generatePassword($pwd,$user['salt']);
        $stmt = $this->db->prepare("update user set pwd = :pwd,update_time = :update_time where uid = :uid");
        $array = array(
            ':pwd' => $pwd,
            ':update_time' => date("Y-m-d H:i:s"),
            ':uid' => $uid,
        );
        $stmt ->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return -2;
        }
        return 1;
    }

    public function updateUserLogin($uid){
        $stmt = $this->db->prepare("update user set login_time = :login_time,login_ip = :login_ip where uid = :uid");
        $array = array(
            ':login_time' => date("Y-m-d H:i:s"),
            ':login_ip' => $_SERVER["REMOTE_ADDR"],
            ':uid' => $uid
        );
        $stmt->execute($array);
        //发放福报值和经验
        $scoreModel = new ScoreModel();
        $scoreModel->add($uid,0,'login',0);
        //书房初始化队列
        $this->initFeedToList($uid);
        return $stmt->rowCount();
    }
    public function openSet($uid){
        $stmt = $this->db->prepare("update user set login_time = :login_time,login_ip = :login_ip where uid = :uid");
        $array = array(
            ':login_time' => date("Y-m-d H:i:s"),
            ':login_ip' => $_SERVER["REMOTE_ADDR"],
            ':uid' => $uid
        );
        $stmt->execute($array);
        return $stmt->rowCount();
    }
    private function initFeedToList($uid){
        $key = "init:user:feed";
        $this->redis->rPush($key,(int)$uid);
    }

    public function updateUserConfirmStatus($user_name){
        $stmt = $this->db->prepare("update user set update_time = :update_time,status = 1 where user_name = :user_name");
        $array = array(
            ':update_time' => date("Y-m-d H:i:s"),
            ':user_name' => $user_name
        );
        $stmt->execute($array);
        return $stmt->rowCount();
    }

    public function getDid(){
        $stmt = $this->db->prepare("select did from user order by did desc limit 1");
        $stmt->execute();
        $result = $stmt->fetch();
        $did = isset($result[0])?$result[0]:100000;
        $did = $did>100000?$did:100000;
        $did++;
        return $this->checkDid($did);
    }

    /**
     * 生成代代号
     * @param $did
     * @return mixed
     */
    public function checkDid($did){
        if(!preg_match('/\d*?(\d)\1{3}\d*?/',$did) && !preg_match('/(123|234|345|456|567|678|789)$/',$did) && !preg_match('/\d*?(\d)\1(\d)\2$/',$did) && !preg_match('/\d*?(\d{2,3})\1$/',$did)){
            return $did;
        }
        $did++;
        return $this->checkDid($did);
    }

    /*
     * 修改用户昵称
     */
    public function modifyNickName($uid,$nick_name){
        $user_info = $this->getUserByUid($uid);
        if(!$user_info){
            return 0;
        }
        if($user_info['nick_name'] == $nick_name){
            return -3;
        }
        $rs = $this->nickNameIsExist($nick_name,$uid);
        if($rs != 0){
            return -1;
        }
        $stmt = $this->db->prepare("update user set nick_name=:nick_name,update_time =:update_time where uid=:uid");
        $array = array(
            ':nick_name'=>$nick_name,
            ':uid'=>$uid,
            ':update_time' => date("Y-m-d H:i:s"),
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return -2;
        }
        $this->clearUserData($uid);
        return 1;
    }

    /*
     * @name 修改用户简介
     */
    public function modifyUserIntro($uid,$intro){
        $user_info = $this->getUserInfoByUid($uid);
        if(!$user_info){
            return 0;
        }

        $stmt =  $this->db->prepare("update user_info set intro=:intro,update_time =:update_time where uid =:uid");
        $array = array(
            ':uid'=>$uid,
            ':intro'=>$intro,
            ':update_time' => date("Y-m-d H:i:s"),
        );
        $stmt->execute($array);
        $this->clearUserData($uid);
        return 1;
    }

    /*
     * @name 修改用户信息
     */
    public function modifyUserInfo($uid,$sex,$birthday_type,$birthday_status,$year,$month,$day,$location_town_id,$hometown_town_id,$intro,$nick_name,$avatar,$home_cover,$app_cover){
        $location_city_id=$hometown_city_id=$location_province_id=$hometown_province_id='';
        $user_info = $this->getUserInfoByUid($uid);
        if(!$user_info){
            return 0;
        }
        $update_time = date("Y-m-d H:i:s");
        if($sex){
            $stmt =  $this->db->prepare("update user_info set sex=:sex where uid =:uid");
            $array = array(
                ':sex'=>$sex,
                ':uid'=>$uid
            );
        }
        if($year&&$month&&$day){
            $stmt =  $this->db->prepare("update user_info set birthday_type=:birthday_type,birthday_status=:birthday_status,year=:year,month=:month,day=:day where uid =:uid");
            $array = array(
                ':birthday_type'=>$birthday_type,
                ':birthday_status'=>$birthday_status,
                ':year'=>$year,
                ':month'=>$month,
                ':day'=>$day,
                ':uid'=>$uid
            );
        }
        $addressModel = new AddressModel();
        if($location_town_id){
            $location_rs = $addressModel->cityParent($location_town_id);
            if(!$location_rs){
                return -1;
            }
            $location_city_id = $location_rs['id'];
            $location_rs_province = $addressModel->cityParent($location_city_id);
            if(!$location_rs_province){
                return -3;
            }
            $location_province_id = $location_rs_province['id'];
            $stmt =  $this->db->prepare("update user_info set location_province_id=:location_province_id,location_city_id=:location_city_id,location_town_id=:location_town_id where uid =:uid");
            $array = array(
                ':location_province_id'=>$location_province_id,
                ':location_city_id'=>$location_city_id,
                ':location_town_id'=>$location_town_id,
                ':uid'=>$uid
            );
        }
        if($hometown_town_id){
            $hometown_rs = $addressModel->cityParent($hometown_town_id);
            if(!$hometown_rs){
                return -2;
            }
            $hometown_city_id = $hometown_rs['id'];
            $hometown_rs_province = $addressModel->cityParent($hometown_city_id);
            if(!$hometown_rs_province){
                return -4;
            }
            $hometown_province_id = $hometown_rs_province['id'];
            $stmt =  $this->db->prepare("update user_info set hometown_province_id=:hometown_province_id,hometown_city_id=:hometown_city_id,hometown_town_id=:hometown_town_id where uid =:uid");
            $array = array(
                ':hometown_province_id'=>$hometown_province_id,
                ':hometown_city_id'=>$hometown_city_id,
                ':hometown_town_id'=>$hometown_town_id,
                ':uid'=>$uid
            );
        }
        if($intro){
            $stmt =  $this->db->prepare("update user_info set intro=:intro,update_time =:update_time where uid =:uid");
            $array = array(
                ':intro'=>$intro,
                ':uid'=>$uid,
                ':update_time' => $update_time,
            );
        }
        if($nick_name){
            $stmt =  $this->db->prepare("update user set nick_name=:nick_name,update_time =:update_time where uid =:uid");
            $array = array(
                ':nick_name'=>$nick_name,
                ':uid'=>$uid,
                ':update_time' => $update_time,
            );
        }
        if($avatar){
            $stmt =  $this->db->prepare("update user set avatar=:avatar where uid =:uid");
            $array = array(
                ':avatar'=>$avatar,
                ':uid'=>$uid
            );
        }
        if($home_cover){
            $stmt =  $this->db->prepare("update user_info set home_cover=:home_cover where uid =:uid");
            $array = array(
                ':home_cover'=>$home_cover,
                ':uid'=>$uid
            );
        }
        if($app_cover){
            $stmt =  $this->db->prepare("update user_info set app_cover=:app_cover where uid =:uid");
            $array = array(
                ':app_cover'=>$app_cover,
                ':uid'=>$uid
            );
        }
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if($rs<1){
            return -5;
        }
        $this->clearUserData($uid);
        return 1;
    }
    /*
    * @name 用户信息完善
    */
    public function perfectUserInfo($uid,$sex,$location_town_id,$hometown_town_id,$intro,$nick_name=''){
        if(!in_array($sex,array(1,2))){
            return -6;
        }
        $user_info = $this->getUserInfoByUid($uid);
        if(!$user_info){
            return 0;
        }
        $addressModel = new AddressModel();
        $location_rs = $addressModel->cityParent($location_town_id);
        if(!$location_rs){
            return -1;
        }
        $hometown_rs = $addressModel->cityParent($hometown_town_id);
        if(!$hometown_rs){
            return -2;
        }
        $location_city_id = $location_rs['id'];
        $hometown_city_id = $hometown_rs['id'];
        $location_rs_province = $addressModel->cityParent($location_city_id);
        if(!$location_rs_province){
            return -3;
        }
        $location_province_id = $location_rs_province['id'];
        $hometown_rs_province = $addressModel->cityParent($hometown_city_id);
        if(!$hometown_rs_province){
            return -4;
        }
        $hometown_province_id = $hometown_rs_province['id'];
        $stmt =  $this->db->prepare("update user_info set location_province_id=:location_province_id,location_city_id=:location_city_id,location_town_id=:location_town_id,hometown_province_id=:hometown_province_id,hometown_city_id=:hometown_city_id,hometown_town_id=:hometown_town_id,sex=:sex,intro=:intro,update_time =:update_time where uid =:uid");
        $array = array(
            ':uid'=>$uid,
            ':sex'=>$sex,
            ':location_town_id'=>$location_town_id,
            ':hometown_town_id'=>$hometown_town_id,
            ':location_city_id'=>$location_city_id,
            ':hometown_city_id'=>$hometown_city_id,
            ':location_province_id'=>$location_province_id,
            ':hometown_province_id'=>$hometown_province_id,
            ':intro'=>$intro,
            ':update_time' => date("Y-m-d H:i:s"),
        );
        $rs = $stmt->execute($array);
        if($nick_name){
            $stmt_user = $this->db->prepare("update user set nick_name=:nick_name,update_time =:update_time where uid=:uid");
            $array_user = array(
                ':uid'=>$uid,
                ':nick_name'=>$nick_name,
                ':update_time' => date("Y-m-d H:i:s"),
            );
            $stmt_user->execute($array_user);
        }
        if(!$rs){
            return -5;
        }
        $this->clearUserData($uid);
        return 1;
    }
    /*
     * 设置用户头像
     */
    public function setAvatar($uid,$avatar){
        $user_info= $this -> getUserByUid($uid);
        if(!$user_info){
            return -1;
        }
        $stmt =  $this->db->prepare("update user set avatar=:avatar where uid=:uid");
        $array = array(
            ':uid' => $uid,
            ':avatar' => $avatar,
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        if($rs<1){
            return 0;
        }
        $this->clearUserData($uid);
        return 1;
    }

    private function getUserKey($uid){
        return "u:info:".$uid;
    }

    public function addHistoryAvatar($uid,$avatar,$album_id=''){
        if($album_id){
            $sql = 'select * from album_photo where album_id = :album_id and img = :img';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array('album_id'=>$album_id,'img'=>$avatar));
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if($result)
                return false;
        }
        $sql = "insert into user_avatar(uid,avatar,add_time) values (:uid,:avatar,:add_time)";
        $stmt = $this->db->prepare($sql);
        $arr = array(
            ':uid' => $uid,
            ':avatar' => $avatar,
            ':add_time' => date("Y-m-d H:i:s"),
        );
        $stmt->execute($arr);
        return $this->db->lastInsertId();
    }

    public function getHistoryAvatar($uid){
        $sql = "select uid,avatar from user_avatar where uid = :uid order by id desc limit 5";
        $stmt = $this->db->prepare($sql);
        $arr = array(
            ':uid' => $uid
        );
        $stmt->execute($arr);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }




    /**
     * 获取用户常用信息
     * @param $uid       要获取的用户的uid
     * @param $from_uid  发起动作的用户的uid
     * @return bool
     */
    public function getUserData($uid,$from_uid=0){
        if(!$uid){
            return (object)array();
        }
        $uKey = $this->getUserKey($uid);
        $userData = $this->redis->hGetAll($uKey);
        if(!$userData || !isset($userData['uid'])){
            $user = $this->getUserByUid($uid);
            if(!$user){
                return (object)array();
            }
            $userInfo = $this->getUserInfoByUid($uid);
            $userData['uid'] = $uid;
            $userData['did'] = $user['did'];
            $userData['user_name'] = $user['user_name'];
            $userData['type'] = $user['type'];
            $userData['reg_type'] = $user['reg_type'];
            $userData['nick_name'] = $user['nick_name'] ? $user['nick_name'] : '未知用户';
            $userData['status'] = $user['status'];
            $userData['avatar'] = $user['avatar'];
            $userData['sex'] = $userInfo['sex'];
            $userData['add_time'] = $user['add_time'];
            $userData['birthday_status'] = $userInfo['birthday_status'];
            $addressModel = new AddressModel();
            $userData['province'] = '';
            $userData['city'] = '';
            $userData['town'] = '';
            if($userInfo['location_province_id']){
                $userData['province'] = $addressModel->getNameById($userInfo['location_province_id']);
            }
            if($userInfo['location_city_id']){
                $userData['city'] = $addressModel->getNameById($userInfo['location_city_id']);
            }
            if($userInfo['location_town_id']){
                $userData['town'] = $addressModel->getNameById($userInfo['location_town_id']);
            }
            $userData['score'] = $userInfo['score'];
            $userData['exp'] = $userInfo['exp'];
            $userData['intro'] = $userInfo['intro']?$userInfo['intro']:'传承传统文化是每一个人的使命！';
            $followModel = new FollowModel();
            $userData['att_num'] = $followModel->getAttNum($uid);
            $userData['fans_num'] = $followModel->getFansNum($uid);
            $stageModel = new StageModel();
            $userData['stage_num'] = $stageModel->getStageNum($uid);
            $topicModel = new TopicModel();
            $userData['topic_num'] = $topicModel->getTopicNum($uid);
            $userData['topic_reply_num'] = $topicModel->getTopicReplyNum($uid);
            $this->redis->hMSet($uKey,$userData);
            $this->redis->expire($uKey,3600*12);
        }
        $userData['relation'] = 0;
        $userData['remark'] = '';
        $userData['name'] = '';
        $userData['self'] = 0;
        if($from_uid){
            if($uid == $from_uid){
                $userData['self'] =  1;
            }
            $followModel = new FollowModel();
            $userData['relation'] = $followModel->getRelation($from_uid,$uid);
            if($userData['relation'] == 1 || $userData['relation'] == 2){
                $userData['remark'] = $followModel->getRemark($from_uid,$uid);
            }else{
                $userData['remark'] = '';
            }
        }
        if($userData['nick_name']){
            $nick_name = $userData['nick_name'];
            $userData['nick_name'] = $userData['remark']?$userData['remark']:$nick_name;
            $userData['name'] = $userData['remark']?$nick_name:"";
        }
        switch($userData['type']){
            case 1:
                $userData['ico_type'] = 'angel literacy';
                break;
            case 2:
                $userData['ico_type'] = 'angel first';
                break;
            case 3:
                $userData['ico_type'] = 'angel second';
                break;
            case 4:
                $userData['ico_type'] = 'angel third';
                break;
            case 5:
                $userData['ico_type'] = 'angel fourth';
                break;
            default:
                $userData['ico_type'] = '';
        }
        return $userData;
    }

    //获取用户app信息
    public function getAppUserInfo($uid){
        $stmt = $this->db->prepare('SELECT id,uid,lng,lat,add_time,update_time FROM user_app_info WHERE uid =:uid');
        $arr = array(
            ':uid' => $uid
        );
        $stmt->execute($arr);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    //修改个人主页封面
    public function updateAppHomeCover($uid,$cover){
        $stmt = $this->db->prepare("update user_info set home_cover=:home_cover,update_time=:update_time where uid=:uid");
        $array = array(
            ':home_cover'=>$cover,
            ':update_time'=>date("Y-m-d H:i:s"),
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $count  =  $stmt->rowCount();
        if($count<1){
            return 0;
        }
        return 1;
    }
    //设置用户坐标
    public function setUserCoordinate($uid,$lng,$lat){
        $stmt= $this->db->prepare("INSERT INTO user_app_info(uid,lng,lat) VALUES (:uid,:lng,:lat);");
        $array = array(
            ':uid'=>$uid,
            ':lng'=>$lng,
            ':lat'=>$lat,
        );
        $stmt -> execute($array);
        $lastId = $this->db->lastInsertId();
        if($lastId<1){
            return 0;
        }
        return $lastId;
    }
    //修改用户坐标
    public function updateUserCoordinate($id,$lng,$lat){
        $stmt = $this->db->prepare("update user_app_info set lng=:lng,lat=:lat,update_time=:update_time where id=:id");
        $array = array(
            ':lng'=>$lng,
            ':lat'=>$lat,
            ':update_time'=>date("Y-m-d H:i:s"),
            ':id'=>$id
        );
        $stmt->execute($array);
        $count  =  $stmt->rowCount();
        if($count<1){
            return 0;
        }
        return 1;
    }
    public function clearUserData($uid){
        $uKey = $this->getUserKey($uid);
        $this->redis->del($uKey);
    }

    /*
     * @name 用户中心修改密码
     * @param $uid
     * @param $old_pwd
     * @param $pwd
     * @return
     */
    public function modifyPwd($uid,$old_pwd,$pwd,$pwd_type){
        //判断用户是否存在
        $user_info= $this -> getUserByUid($uid);
        if(!$user_info){
            return -1;
        }
        //判断用户的旧密码是否正确
        $old_pwd = $this->generatePassword($old_pwd,$user_info['salt']);
        if($old_pwd != $user_info['pwd']){
            return -2;
        }
        $new_pwd = $this->generatePassword($pwd,$user_info['salt']);
        if($new_pwd == $old_pwd){
            return -3;
        }
        $stmt = $this->db->prepare("update user set pwd=:pwd,pwd_type=:pwd_type where uid=:uid");
        $array = array(
            ':pwd'=>$new_pwd,
            ':pwd_type'=>$pwd_type,
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $count  =  $stmt->rowCount();
        if($count<1){
            return 0;
        }
        return 1;
    }

    /*
     * @name 第三方登录token
     */
    public function getToken($type,$access_token,$openid){
        $stmt = $this->db->prepare("select uid,access_token from user_bind where type = :type and access_token=:access_token and openid=:openid");
        $array = array(
            ':type'=>$type,
            ':access_token'=>$access_token,
            ':openid'=>$openid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 设置token
     * @param $type         1新浪 2腾讯QQ
     * @param $openid       token[uid]
     * @param $access_token access_token
     * @param int $uid
     * @param int $flag     0默认 1注册 2绑定
     * @return int
     */
    public function setToken($type,$openid,$access_token,$uid=0,$flag=0){
        if($uid){
            $sql = "insert into user_bind (type,access_token,openid,uid,add_time,flag)
                    values (:type,:access_token,:openid,:uid,:add_time,:flag)
                    on duplicate key update access_token = :access_token,uid=:uid,flag=:flag";
            $stmt_open= $this->db->prepare($sql);
            $array = array(
                ':type'=>$type,
                ':access_token'=>$access_token,
                ':openid'=>$openid,
                ':uid'=>$uid,
                ':add_time'=>date("Y-m-d H:i:s"),
                ':flag'=>$flag
            );
        }else{
            $sql = "insert into user_bind (type,access_token,openid,add_time,flag)
                    values (:type,:access_token,:openid,:add_time,:flag)
                    on duplicate key update access_token = :access_token,flag=:flag";
            $stmt_open= $this->db->prepare($sql);
            $array = array(
                ':type'=>$type,
                ':access_token'=>$access_token,
                ':openid'=>$openid,
                ':add_time'=>date("Y-m-d H:i:s"),
                ':flag'=>$flag
            );
        }

        $stmt_open -> execute($array);
        $count  =  $stmt_open -> rowCount ();
        if($count<1){
            return 0;
        }
        return 1;
    }
    /*
     * @name 第三方帐号绑定用户
     */
    public function bindUser($uid,$type,$openid){
        $stmt_update = $this->db->prepare("update user_bind set uid=:uid where type = :type and openid = :openid");
        $array = array(
            ':uid' =>$uid,
            ':type' => $type,
            ':openid' => $openid
        );
        $stmt_update->execute($array);
        return 1;
    }

    //添加第三方帐号的用户
    public function addBindUser($type,$nick_name,$openid,$pwd,$pwd_type,$intro,$sex){
        $stmt = $this->db->prepare("select uid from user_bind where type = :type and openid = :openid");
        $array = array(
            ':type'=>$type,
            ':openid'=>$openid,
        );
        $stmt->execute($array);
        $user_bind = $stmt->fetch(PDO::FETCH_ASSOC);
        //判断是否已经绑定
        if($user_bind['uid']){
            return false;
        }
        //添加新用户
        $avatarList = $this->getAvatarList(3);//获取默认头像列表类型3
        if($avatarList){
            $key = array_rand($avatarList);
            $avatar = $avatarList[$key]['path'];
        }
        $uid = $this->addUser('',$nick_name,$avatar,$pwd,$pwd_type,3,1,'');
        $update_time = date("Y-m-d H:i:s");
        //更新头像
        if($avatar){
            $stmt = $this->db->prepare("update user set avatar = :avatar,update_time =:update_time where uid = :uid");
            $array = array(
                ':avatar'=>$avatar,
                ':uid'=>$uid,
                ':update_time' => $update_time,
            );
            $stmt->execute($array);
        }
        //更新简介
        if($intro){
            $stmt = $this->db->prepare("update user_info set intro = :intro,update_time =:update_time where uid = :uid");
            $array = array(
                ':intro'=>$intro,
                ':uid'=>$uid,
                ':update_time' => $update_time,
            );
            $stmt->execute($array);
        }
        //更新sex
        if($sex){
            $stmt = $this->db->prepare("update user_info set sex=:sex,update_time =:update_time where uid = :uid");
            $array = array(
                ':sex'=>$sex,
                ':uid'=>$uid,
                ':update_time' => $update_time,
            );
            $stmt->execute($array);
        }
        return $uid;
    }

    public function isBind($type,$openid){
        $stmt = $this->db->prepare("select uid from user_bind where type = :type and openid = :openid");
        $array = array(
            ':type'=>$type,
            ':openid'=>$openid,
        );
        $stmt->execute($array);
        $user_bind = $stmt->fetch(PDO::FETCH_ASSOC);
        //判断是否已经绑定
        if(!$user_bind['uid']){
            return 0;
        }
        return $user_bind['uid'];
    }

    //更新书房
    public function updateHome($uid,$home_background,$home_color,$home_img,$home_flash){
        if($home_background && $home_color){
            $stmt = $this->db->prepare("update user_info set home_background = :home_background,home_color = :home_color,home_flash = '',home_img = '' where uid = :uid");
            $array = array(
                ':home_background'=>$home_background,
                ':home_color'=>$home_color,
                ':uid'=>$uid,
            );
        }elseif($home_img){
            $stmt = $this->db->prepare("update user_info set home_img = :home_img where uid = :uid");
            $array = array(
                ':home_img'=>$home_img,
                ':uid'=>$uid,
            );
        }elseif($home_flash){
            $stmt = $this->db->prepare("update user_info set home_flash = :home_flash where uid = :uid");
            $array = array(
                ':home_flash'=>$home_flash,
                ':uid'=>$uid,
            );
        }else{
            return false;
        }
        $this->clearUserData($uid);
        $stmt->execute($array);
        return 1;
    }

    public function getHomeDressData(){
        return array(
            'package' => array(
                array('id'=>0,'img'=>'home_package_1.jpg','flash'=>'','color'=>'#aad2de'),
                array('id'=>1,'img'=>'home_package_2.jpg','flash'=>'','color'=>'#88bcd2'),
                array('id'=>2,'img'=>'home_package_3.jpg','flash'=>'','color'=>'#206574'),
                array('id'=>3,'img'=>'home_package_4.jpg','flash'=>'','color'=>'#cae3e7'),
                array('id'=>4,'img'=>'home_package_5.jpg','flash'=>'','color'=>'#7fa9a5'),
                array('id'=>5,'img'=>'home_package_6.jpg','flash'=>'','color'=>'#c5e8e1'),
                array('id'=>6,'img'=>'home_package_7.jpg','flash'=>'','color'=>'#0a2747'),
                array('id'=>7,'img'=>'home_package_8.jpg','flash'=>'','color'=>'#add9e6'),
                array('id'=>8,'img'=>'home_package_9.jpg','flash'=>'','color'=>'#c4e5ee'),
                array('id'=>9,'img'=>'home_package_10.jpg','flash'=>'','color'=>'#ece5d3'),
                array('id'=>10,'img'=>'home_package_11.jpg','flash'=>'','color'=>'#c4dae8'),
                array('id'=>11,'img'=>'home_package_12.jpg','flash'=>'','color'=>'#e6e1cd'),
                array('id'=>12,'img'=>'home_package_13.jpg','flash'=>'','color'=>'#aedbf0'),
                array('id'=>13,'img'=>'home_package_14.jpg','flash'=>'','color'=>'#4f85cf'),
                array('id'=>14,'img'=>'home_package_15.jpg','flash'=>'','color'=>'#052653'),
            ),
            'cover' => array(
                array('id'=>0,'img'=>'home_cover_1.jpg'),
                array('id'=>1,'img'=>'home_cover_2.jpg'),
                array('id'=>2,'img'=>'home_cover_3.jpg'),
                array('id'=>3,'img'=>'home_cover_4.jpg'),
                array('id'=>4,'img'=>'home_cover_5.jpg'),
                array('id'=>5,'img'=>'home_cover_6.jpg'),
                array('id'=>6,'img'=>'home_cover_7.jpg'),
                array('id'=>7,'img'=>'home_cover_8.jpg'),
                array('id'=>8,'img'=>'home_cover_9.jpg')
            ),
            'flash' => array(
                array('id'=>0,'img'=>'home_flash_1.jpg','flash'=>'home_flash_1.swf'),
                array('id'=>1,'img'=>'home_flash_2.jpg','flash'=>'home_flash_2.swf'),
                array('id'=>2,'img'=>'home_flash_3.jpg','flash'=>'home_flash_3.swf'),
                array('id'=>3,'img'=>'home_flash_4.jpg','flash'=>'home_flash_4.swf'),
                array('id'=>4,'img'=>'home_flash_5.jpg','flash'=>'home_flash_5.swf'),
                array('id'=>5,'img'=>'home_flash_6.jpg','flash'=>'home_flash_6.swf'),
                array('id'=>6,'img'=>'home_flash_7.jpg','flash'=>'home_flash_7.swf'),
                array('id'=>7,'img'=>'home_flash_8.jpg','flash'=>'home_flash_8.swf'),
                array('id'=>8,'img'=>'home_flash_9.jpg','flash'=>'home_flash_9.swf'),
                array('id'=>9,'img'=>'home_flash_10.jpg','flash'=>'home_flash_10.swf'),
                array('id'=>10,'img'=>'home_flash_11.jpg','flash'=>'home_flash_11.swf'),
                array('id'=>11,'img'=>'home_flash_12.jpg','flash'=>'home_flash_12.swf'),
            ));
    }

    public function isOnline($uid){
        if(!$uid){
            return false;
        }
        $onlineKey = 'u:online:'.$uid;
        if(!$this->redis->exists($onlineKey)){
            return false;
        }
        return true;
    }

    public function setOnline($uid){
        if(!$uid){
            return false;
        }
        $onlineKey = 'u:online:'.$uid;
        if($this->redis->exists($onlineKey)){
            $this->redis->expire($onlineKey,60);
            $last_time = $this->redis->get($onlineKey);
            $now_time = time();
            if(($now_time - $last_time > 60) && ($now_time - $last_time < 600)){
                //发放福报值和经验
                $scoreModel = new ScoreModel();
                $scoreModel->add($uid,0,'login',0);
                $this->updateOnlineTime($uid,$now_time - $last_time);
                $this->redis->set($onlineKey,$now_time);
            }
        }else{
            $this->redis->setex($onlineKey, 60, time());
        }
    }

    public function updateOnlineTime($uid,$time){
        $stmt = $this->db->prepare("update user set online = online+:time where uid = :uid");
        $array = array(
            ':time' => $time,
            ':uid' => $uid
        );
        $stmt->execute($array);
        $this->updateMissionOnline($uid,$time);
    }

    /**
     * @更新用户活跃天数
     * (大于2小时算一天，当天挂线N小时也是一天)
     * @param $uid  用户id
     * @param $time 用户在线时间(秒)
     * @author wuzb
     */
    public function updateMissionOnline($uid,$time){
        $today = date('Y-m-d');
        $stmt = $this->db->prepare('select * from mission_online where uid = '.$uid);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!isset($row['add_time'])){
            //从未记录过
            $stmt = $this->db->prepare('insert into mission_online (uid,online,add_time) values (:uid,:online,:today)');
            $stmt->execute(array( ':online' => $time, ':today'=> $today, ':uid' => $uid ));
        }else{
            //今日有更新过
            if($row['add_time'] == $today){
                if($row['a_update_time'] != $today){
                    if($row['online'] >= 7200){
                        //在线>=2小时&&活跃天数没追加
                        $stmt = $this->db->prepare('update mission_online set active_days=active_days+1,a_update_time=:today where uid=:uid');
                        $stmt->execute(array( ':today'=> $today, ':uid' => $uid ));
                    }else{
                        //在线<2小时
                        $stmt = $this->db->prepare("update mission_online set online = online+:online where uid = :uid");
                        $stmt->execute(array( ':online' => $time, ':uid' => $uid ));
                    }
                }
            }else{
                //今日无记录（数据是以前更新的）
                if($row['online'] >= 7200){
                    $stmt = $this->db->prepare("update mission_online set online = :online, add_time = :today where uid = :uid");
                }else{
                    $stmt = $this->db->prepare("update mission_online set online = online+:online, add_time = :today where uid = :uid");
                }
                $stmt->execute(array( ':online' => $time, ':today'=> $today, ':uid' => $uid ));
            }
        }

    }

    public function getTopByScore($offset,$limit){
        $stmt = $this->db->prepare("select uid,score from user_info order by score desc limit $offset,$limit");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //客服中心官方公用主页用户信息
    public function getServiceUserList(){
        $stmt = $this->db->prepare("select uid from user where did in(1003906,1003912,1003909,1003910,1003913,1003926)");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            foreach($result as $key=>$val){
                $userModel = new UserModel();
                $result[$key] = $userModel->getUserData($val['uid']);
            }
        }
        return $result;
    }
    //附近的人
    public function vicinityUser($lat,$lng,$uid,$page,$size,$type=''){
        $fields = $type ? 'and uid NOT IN (select f_uid from follow where uid=:uid and status=1)' : '';
        $start =($page - 1)*$size;
        $sTime = date("Y-m-d 00:00:00", strtotime("-10 day"));
        $stmt = $this->db->prepare("select distinct uid,lat,lng,update_time,get_distance(:lat,:lng,lat,lng) as distance from user_app_info
                    where lng is not null and lat is not null  and uid !=:uid
                    and uid in (select uid from user) $fields and update_time>:update_time order by distance asc limit :start,:size");
        $stmt->bindValue(':lat', $lat, PDO::PARAM_INT);
        $stmt->bindValue(':lng', $lng, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':update_time', $sTime, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    //用户添加意见
    public function addAdvice($uid,$content,$email){
        $stmt = $this->db->prepare("insert into user_advice (uid,content,email) values (:uid,:content,:email);");
        $array=array(
            ':uid'=>$uid,
            ':content'=>$content,
            ':email'=>$email,
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        if(!$id){
            return 0;
        }
        return $id;
    }
    //验证APP版本是否为最新版本
    public function verifyAppVersion($type,$version){
        if($type==1){
            $fields = 'and name=:version';
        }else{
            $fields = 'and version=:version';
        }
        $stmt = $this->db->prepare("select name,version,status,url from app_version where type=:type $fields ");
        $array=array(
            ':type'=>$type,
            ':version'=>$version
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs;
    }
    //查询版本是否存在
    public function getVersion($type,$version){
        if($type==1){
            $fields = 'and name=:version';
        }else{
            $fields = 'and version=:version';
        }
        $stmt = $this->db->prepare("select count(id) as num from app_version where type=:type $fields ");
        $array=array(
            ':type'=>$type,
            ':version'=>$version
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }
    //获取最新的版本
    public function getNewVersion($type){
        $stmt = $this->db->prepare("select id,status,name,url from app_version where type=:type and status = 0 ORDER BY add_time DESC limit 1");
        $array=array(
            ':type'=>$type
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs;
    }
    //获取最新的版本更新信息
    public function getNewVersionInfo($id){
        $stmt = $this->db->prepare("select id,vid,content,status,add_time from app_version_content where vid=:vid and status =1 ORDER BY add_time");
        $array=array(
            ':vid'=>$id
        );
        $stmt->execute($array);
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rs;
    }
    //获取文化天使或者文化导师认证信息
    public function getInfo($uid='',$id_card='',$phone=''){
        if($uid){
            $array = array('uid' => $uid);
            $sql = "select * from user_angel where uid = :uid and uid in (select uid from `user` where type > 1) order by add_time desc limit 1";
        }
        if($id_card){
            $array = array('id_card' => $id_card);
            $sql = "select * from user_angel where id_card = :id_card and uid in (select uid from `user` where type > 1) order by add_time desc limit 1";
        }
        if($phone){
            $array = array('phone' => $phone);
            $sql = "select * from user_angel where phone = :phone and uid in (select uid from `user` where type > 1) order by add_time desc limit 1";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($array);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    //获取推荐头像数据
    public function getRecommendAvatar(){
        $stmt = $this->db->prepare("select * from avatar where status = 1");
        $stmt->execute();
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rs;
    }
    //添加用户@过的人(集合的数量要限制)
    public function addAtUser($uid,$arr){
        if(!$uid || !$arr){
            return false;
        }
        $uAtKey = "u:at:".$uid;
        $is_pop = false;
        if($this->redis->sSize($uAtKey) > 10){
            $is_pop = true;
        }
        if($arr){
            foreach($arr as $val){
                if(!$this->redis->sRem($uAtKey,$val) && $is_pop){
                    $this->redis->sPop($uAtKey);
                }
            }
            foreach($arr as $val){
                $this->redis->sAdd($uAtKey,$val);
            }
        }
    }
    public function addUserLogin($uid,$token){
        $uKey = 'u:login:'.$uid;
        $this->redis->set($uKey,$token);
    }
    public function clearUserLogin($uid){
        $uKey = 'u:login:'.$uid;
        $this->redis->del($uKey);
    }
    public function getUserLogin($uid){
        $uKey = 'u:login:'.$uid;
        return $this->redis->get($uKey);
    }

    public function getUserAppInfo(){
        $stmt = $this->db->prepare("select id,lng,lat from user_app_info");
        $stmt->execute();
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rs;
    }
    //个人资料页默认封面
    public function getDefaultCover(){
        $stmt = $this->db->prepare("select id,name,path,add_time from user_cover where type = 3 and status = 1 order by sort asc");
        $stmt->execute();
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rs;
    }

    //更新用户头像二维码
    public function updateUserQRCodeImg($uid,$QRCodeImg){
        $stmt = $this->db->prepare('update user set qrcode_img = :qrcode_img , update_time =:update_time where uid=:uid');
        $array = array(
            ':qrcode_img' => $QRCodeImg,
            ':uid' => $uid,
            ':update_time' => date("Y-m-d H:i:s"),
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count<1){
            return 0;
        }
        return 1;
    }
    public function setUserTagType($uid){
        $key="app:userTag:".$uid;
        if(!$this->redis->exists($key)){
            $this->redis->hSet($key,$uid,time());
        }
    }
    public function getUserTagType($uid){
        $key="app:userTag:".$uid;
        if($this->redis->exists($key)){
            return 1;
        }
        return 0;
    }
    public function initSendForUser(){
        $b_array = array('1'=>'01','2'=>'02','3'=>'03','4'=>'04','5'=>'05','6'=>'06','7'=>'07','8'=>'08','9'=>'09');
        //查询当天生日的用户
        $b_stmt = $this->db->prepare("select uid,month,day from user_info where year!='' and month !='' and day != '' ");
        $b_stmt->execute();
        $b_rs = $b_stmt->fetchAll(PDO::FETCH_ASSOC);
        $month = date('m',time());
        $day = date('d',time());
        if($b_rs){
            foreach($b_rs as $v){
                if(strlen($v['month'])==1){
                    $v['month'] = $b_array[$v['month']];
                }
                if(strlen($v['day'])==1){
                    $v['day'] = $b_array[$v['day']];
                }
                if($month==$v['month']&&$day==$v['day']){
                    $data['uid']=$v['uid'];
                    Common::addNoticeAndSmsForUser(2,$data);
                }
            }
        }
        //查询满足入驻条件的用户
        $login_stmt = $this->db->prepare("select uid,add_time from user where status < 2 ");
        $login_stmt->execute();
        $rs = $login_stmt->fetchAll(PDO::FETCH_ASSOC);
        if($rs){
            foreach($rs as $v){
                if(date('Y-m-d H:i',strtotime($v['add_time']))==date("Y-m-d H:i",strtotime("-100 day"))){
                    $data['uid']=$v['uid'];
                    $data['time'] = '100天';
                    Common::addNoticeAndSmsForUser(1,$data);
                }
                if(date('m-d',strtotime($v['add_time']))==date("m-d",time())&&date('Y',strtotime($v['add_time']))<date("Y",time())){
                    $data['uid']=$v['uid'];
                    $data['time'] =''.date("Y",time())-date('Y',strtotime($v['add_time'])).'年';
                    Common::addNoticeAndSmsForUser(1,$data);
                }
            }
        }
    }
    public function getUserStatusByUid($uid){
        $stmt = $this->db->prepare("select status,en_talk_time from user where uid=:uid");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    //获取封面id
    public function getCoverId($cover){
        $stmt = $this->db->prepare("select id from user_cover where path=:path");
        $array = array(
            ':path' => $cover,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['id'];
    }
    //根据uid查询文化天使信息
    public function getAngelInfoByUid($uid){
        $stmt = $this->db->prepare("SELECT uid,real_name,real_photo,info FROM user_angel WHERE uid=:uid and uid in (select uid from `user` where type >1) ");
        $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
        $stmt->execute();
        $list = $stmt->fetch(PDO::FETCH_ASSOC);
        return $list;
    }
    //用户是否绑定过手机（账号安全）
    public function isBindMobile($uid){
        $sql = "SELECT uid,bind_name FROM user_info WHERE uid = :uid";
        $stmt = $this->db->prepare($sql);
        $array = array(':uid' => $uid);
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    //更换绑定
    public function updateBind($bind_name,$country_code,$uid){
        $sql = "UPDATE user_info SET bind_name =:bind_name,bind_country_code=:bind_country_code, update_time = :update_time,bind_status=1
                WHERE uid = :uid";
        $stmt = $this->db->prepare($sql);
        $array = array(':bind_name'=>$bind_name,':uid'=>$uid, ':bind_country_code'=>$country_code,':update_time'=>date('Y-m-d H:i:s'));
        $stmt->execute($array);
        return $stmt->rowCount();
    }
    //根据用户uid查询该用户的手机识别号
    public function getRegIdByUid($uid){
        $sql = 'select * from app_registrationid where uid = :uid and status = 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':uid'=>$uid));
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rs;
    }
    /**
     * 根据用户名获取用户信息
     * @param $user_name
     * @return string
     */
    public function getUserByLogin($user_name,$country_code){
        $stmt = $this->db->prepare("select * from user where user_name=:user_name and reg_country_code=:reg_country_code");
        $array = array(
            ':user_name' => $user_name,
            ':reg_country_code'=>$country_code
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    public function getEmailByLogin($user_name){
        $stmt = $this->db->prepare("select * from user where user_name=:user_name and reg_type = 1 ");
        $array = array(
            ':user_name' => $user_name
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    //通讯录好友是否为才府用户
    public function getMobileBookFriend($uid,$user_array){
        $id_array = array();
        foreach($user_array as $k=> $v){
            array_push($id_array,$v['mobile']);
        }
        foreach($user_array as $k=> $v){
            array_push($id_array,$v['mobile']);
        }
        $place_holders  =  implode ( ',' ,  array_fill ( 0 ,  count ( $user_array ),  '?' ));
        $place_holder  =  implode ( ',' ,  array_fill ( 0 ,  count ( $user_array ),  '?' ));
        $stmt = $this->db->prepare ("select uid,user_name,reg_country_code from `user` where user_name in($place_holders) or CONCAT(reg_country_code,user_name) in ($place_holder)" );
        $stmt->execute($id_array);
        $attList =  $stmt->fetchAll(PDO::FETCH_ASSOC);
        $attPhoneList = array();
        $attCountryList = array();
        $attPhoneUidList = array();
        $attCountryUidList = array();
        if($attList){
            foreach($attList as $v){
                array_push($attPhoneList,$v['user_name']);
                array_push($attCountryList,$v['reg_country_code'].$v['user_name']);
                $attPhoneUidList[$v['user_name']] = $v['uid'];
                $attCountryUidList[$v['reg_country_code'].$v['user_name']] = $v['uid'];
            }
        }
        foreach($user_array as $k=> $v){
            if(in_array($v['mobile'],$attPhoneList) || in_array($v['mobile'],$attCountryList)){
                if(isset($attPhoneUidList[$v['mobile']])){
                    $uidNew = $attPhoneUidList[$v['mobile']];
                }else{
                    $uidNew = $attCountryUidList[$v['mobile']];
                }
                $user_array[$k]['user'] = $this->getUserData($uidNew,$uid);
                $mood_album_id = $this->getMoodAlbumIdByUid($uidNew);
                $mood_images = $this->getNewMoodPhoto($mood_album_id['id']);
                $user_array[$k]['user']['mood_images'] = $mood_images ? $mood_images : array();
            }else{
                $user_array[$k]['user'] = (object)array();
            }
        }
        return $user_array;
    }
    public function getUidByUserName($user_name){
        $stmt = $this->db->prepare("select uid from `user` where user_name=:user_name");
        $array = array(
            ':user_name' => $user_name
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['uid'];
    }
    public function getUidByUserNameAndCountryCode($user_name){
        $stmt = $this->db->prepare(" select uid from `user` where user_name=:user_name or CONCAT(reg_country_code,user_name)=:user_name");
        $array = array(
            ':user_name' => $user_name
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['uid'];
    }
    public function getFindUser($uid,$size){
        $uids = $this->getUserList($uid,$size);
        $tagModel = new TagModel();
        if($uids){
            foreach($uids as $k=>$v){
                $user_info = $this->getUserData($v['uid'],$uid);
                $uids[$k]['did'] = $user_info['did'];
                $uids[$k]['nick_name'] = $user_info['nick_name'];
                $uids[$k]['type'] = $user_info['type'];
                $uids[$k]['avatar'] = Common::show_img($user_info['avatar'],4,200,200);
                $uids[$k]['att_num'] = $user_info['att_num'];
                $uids[$k]['fans_num'] = $user_info['fans_num'];
                $uids[$k]['relation'] = $user_info['relation'];
                $uids[$k]['self'] = $user_info['self'];
                $tag_id = $this->isAlikeTag($uid,$v['uid']);
                if($tag_id){
                    $uids[$k]['is_alike_tag'] = 1;//有相同的标签
                    $tag_info = $tagModel->getTagById($tag_id['tag_id']);
                    $uids[$k]['tag_intro'] = $tag_info ? '来自你感兴趣的'.$tag_info['content'].'标签 ' : '';
                }else{
                    $uids[$k]['is_alike_tag'] = 0;//没有相同的标签
                    $u_tag = $this->getNewTagByUid($v['uid']);
                    $tag_info = $tagModel->getTagById($u_tag['tag_id']);
                    $uids[$k]['tag_intro'] = $tag_info ? '来自'.$tag_info['content'].'标签 ' : '';
                }
                $mood_album_id = $this->getMoodAlbumIdByUid($v['uid']);
                $mood_images = $this->getNewMoodPhoto($mood_album_id['id']);
                $uids[$k]['mood_images'] = $mood_images ? $mood_images : array();
            }
        }
        return $uids;
    }
    public function getUserList($uid,$size){
        $stmt = $this->db->prepare("select uid from user where status = 1 and is_show = 1 and uid!=:uid and uid NOT IN (select f_uid from
        follow where uid=:uid and status=1) and DATE_SUB(CURDATE(), INTERVAL 60 DAY) <= DATE(`login_time`) and uid in (select uid from user_tag where status = 1 ) ORDER BY RAND() limit :size");
        $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
        $stmt->bindValue ( ':size' ,  $size ,  PDO :: PARAM_INT );
        $stmt->execute();
        $data = $stmt->fetchALL(PDO::FETCH_ASSOC);
        return $data;
    }
    //某一个用户是否与登录用户有相同的标签
    public function isAlikeTag($uid,$from_uid){
        $stmt = $this->db->prepare("SELECT tag_id FROM user_tag WHERE tag_id IN (SELECT tag_id FROM user_tag WHERE uid = :uid AND STATUS = 1 ) AND uid =:from_uid AND STATUS = 1 limit 1");
        $array = array(
            ':uid' => $uid,
            ':from_uid'=>$from_uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ;
    }
    //获取用户最新的一个标签
    public function getNewTagByUid($uid){
        $stmt = $this->db->prepare("SELECT tag_id FROM user_tag WHERE  uid =:uid AND STATUS = 1 order by add_time desc limit 1");
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ;
    }
    //查询用户心境相册id
    public function getMoodAlbumIdByUid($uid){
        $stmt = $this->db->prepare("SELECT id FROM album WHERE  uid =:uid and type =3");
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ;
    }
    //查询心境相册最新3张图片
    public function getNewMoodPhoto($album_id){
        $stmt = $this->db->prepare("SELECT id,img FROM album_photo WHERE album_id =:album_id and status< 2 order by id desc limit 3");
        $array = array(
            ':album_id' => $album_id
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            foreach($result as $k=>$v){
                $result[$k]['img'] = IMG_DOMAIN.$v['img'];
            }
        }
        return $result ;
    }
    //添加第三方帐号的用户
    public function addBindUserForOpen($type,$nick_name,$openid,$pwd,$pwd_type,$sex,$origin,$avatar,$user_name,$reg_country_code){
        $stmt = $this->db->prepare("select uid from user_bind where type = :type and openid = :openid");
        $array = array(
            ':type'=>$type,
            ':openid'=>$openid,
        );
        $stmt->execute($array);
        $user_bind = $stmt->fetch(PDO::FETCH_ASSOC);
        //判断是否已经绑定
        if($user_bind['uid']){
            return false;
        }
        $uid = $this->addUser('',$nick_name,$avatar,$pwd,$pwd_type,3,1,'');
        $update_time = date("Y-m-d H:i:s");
        //更新头像
        if($avatar){
            $stmt = $this->db->prepare("update user set avatar = :avatar,update_time =:update_time where uid = :uid");
            $array = array(
                ':avatar'=>$avatar,
                ':uid'=>$uid,
                ':update_time' => $update_time,
            );
            $stmt->execute($array);
        }
        //更新user_info
        $stmt = $this->db->prepare("update user_info set origin=:origin,sex=:sex,bind_name=:bind_name,bind_status=:bind_status,bind_country_code=:bind_country_code,update_time =:update_time where uid = :uid");
        $array = array(
            ':origin'=>$origin,
            ':sex'=>$sex,
            ':bind_name'=>$user_name,
            ':bind_status'=>1,
            ':bind_country_code'=>$reg_country_code,
            ':uid'=>$uid,
            ':update_time' => $update_time,
        );
        $stmt->execute($array);
        return $uid;
    }
    public function commissionIndex($uid,$token,$version){
        $list = $good_ids =$eids = array();
        $stagegoodsModel = new StagegoodsModel();
        $stageModel = new StageModel();
        $eventModel = new EventModel();
        $addressModel = new AddressModel();
        $stmt_goods = $this->db->prepare("SELECT id,MAX(commission_rate) AS commission_rate FROM stage_goods WHERE TYPE < 3 and is_commission =1 and status < 2 GROUP BY sid ORDER BY commission_rate DESC LIMIT 50");
        $stmt_goods->execute();
        $rs_goods = $stmt_goods->fetchAll(PDO::FETCH_ASSOC);
        if($rs_goods&&count($rs_goods)>3){
            $goods_key = array_rand($rs_goods, 3);
            foreach($goods_key as $v){
                $good_ids[] = $rs_goods[$v];
            }
        }else{
            $good_ids = $rs_goods;
        }
        if(!$rs_goods){
            $list['goods'] = array();
        }
        foreach($good_ids as $k=>$v){
            $goods_info = $stagegoodsModel->getGoodsRedisById($v['id']);
            $list['goods'][$k]['id'] = $v['id'];
            $list['goods'][$k]['cover'] = IMG_DOMAIN.$goods_info['cover'];
            $list['goods'][$k]['name'] = $goods_info['name'];
            $list['goods'][$k]['price'] = $goods_info['price'];
            $list['goods'][$k]['commission_rate'] = $goods_info['commission_rate'];
            $list['goods'][$k]['commission'] = $goods_info['commission'];
            $stage_info = $stageModel->getStage($goods_info['sid']);
            $list['goods'][$k]['stage_name'] = $stage_info['name'];
            $list['goods'][$k]['url'] = $token ? I_DOMAIN.'/g/'.$v['id'].'?token='.$token.'&version='.$version:I_DOMAIN.'/g/'.$v['id'].'?version='.$version;
            $list['goods'][$k]['type'] = 12;
        }
        $stmt_event = $this->db->prepare("SELECT id,MAX(commission_rate) AS commission_rate FROM event WHERE price_type =2 and is_commission =1 and status < 2 GROUP BY sid ORDER BY commission_rate DESC LIMIT 50");
        $stmt_event->execute();
        $rs_event = $stmt_event->fetchAll(PDO::FETCH_ASSOC);
        if($rs_event&&count($rs_event)>3){
            $event_key = array_rand($rs_event, 3);
            foreach($event_key as $v){
                $eids[] = $rs_event[$v];
            }
        }else{
            $eids = $rs_event;
        }
        if(!$rs_event){
            $list['event'] = array();
        }
        foreach($eids as $k=>$v){
            $event_info = $eventModel->getEventRedisById($v['id']);
            $list['event'][$k]['id'] = $v['id'];
            $list['event'][$k]['title'] = $event_info['title'];
            $list['event'][$k]['cover'] = Common::show_img($event_info['cover'],4,360,270);
            $list['event'][$k]['commission_rate'] = $event_info['commission_rate'];
            $min_price = $event_info['price_list'][0]['unit_price'];
            $list['event'][$k]['price'] = $min_price;
            if(count($event_info['price_list'])>1){
                $max_price = end($event_info['price_list']);
                $max_price = $max_price['unit_price'];
                $list['event'][$k]['price'] = $min_price.'-'.$max_price;
            }
            $list['event'][$k]['commission'] = $event_info['commission'];
            $list['event'][$k]['show_start_time'] = Common::getEventStartTime($v['id']);
            if($event_info['type']==1){
                $data = $eventModel->getBusinessEventType($event_info['type_code']);//获取活动分类内容
            }else{
                $data = Common::eventType($event_info['type']);
            }
            $list['event'][$k]['type_name'] = $data['name'];
            $list['event'][$k]['code_name'] = $data['code'];
            $province_name = $addressModel->getNameById($event_info['province']);
            $city_name = $addressModel->getNameById($event_info['city']);
            $town_name = $addressModel->getNameById($event_info['town']);
            if($province_name==$city_name){
                $address_name = $city_name.$town_name;
            }else{
                $address_name = $province_name.$city_name;
            }
            $list['event'][$k]['event_address']  = $address_name;
            $stage_info = $stageModel->getStage($event_info['sid']);
            $list['event'][$k]['stage_name'] = $stage_info['name'];
            $list['event'][$k]['url'] = $token ? I_DOMAIN.'/e/'.$v['id'].'?token='.$token.'&version='.$version:I_DOMAIN.'/e/'.$v['id'].'?version='.$version;
            $list['event'][$k]['type'] = 10;
        }
        $list['total_profit'] =$this->getMoneyBagByUid($uid);
        return $list;
    }
    //用户分享链接操作数量入库 type 1 点击量  2订单量
    public function addUserSpToLog($date){
        $visitModel = new VisitModel();
        $stmt = $this->db->prepare("SELECT DISTINCT(uid) AS uid FROM share_promote");
        $stmt->execute();
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($rs){
            foreach($rs as $v){
                //点击量
                $num = $visitModel->getSpVisitByDateAndUid($v['uid'],$date);
                $insert_visit = $this->db->prepare('insert into log_user_sp_count (uid,num,type,add_time) values (:uid,:num,:type,:add_time)');
                $insert_visit->execute(array(':uid'=>$v['uid'],'num'=>$num ? $num : 0,':type'=>1,':add_time'=>$date));
                $sp_list = $this->getSpListByUid($v['uid']);//用户分享记录
                $orders_num = $orders_totals = $commission= 0;
                //订单量 订单金额
                foreach($sp_list as $val){
                    if($val['type']==10){
                        $stmt_orders = $this->db->prepare("SELECT id,totals,commission FROM event_orders WHERE add_time>:time and order_status = 2 and  sp_id=:id ");
                        $array = array(
                            ':time'=>$date,
                            ':id' => $val['id']
                        );
                        $stmt_orders->execute($array);
                        $orders = $stmt_orders->fetch(PDO::FETCH_ASSOC);
                        if($orders){
                            $orders_num += count($orders);
                            foreach($orders as $t){
                                $orders_totals +=$t['totals'];
                                $commission +=$t['commission'];
                            }
                        }
                    }
                    if($val['type']==12){
                        $stmt_orders = $this->db->prepare("SELECT id,price_totals,commission FROM stage_goods_orders WHERE add_time>:time and order_status = 2 and sp_id=:id");
                        $array = array(
                            ':time'=>$date,
                            ':id' => $val['id']
                        );
                        $stmt_orders->execute($array);
                        $orders = $stmt_orders->fetch(PDO::FETCH_ASSOC);
                        if($orders){
                            $orders_num += count($orders);
                            foreach($orders as $t){
                                $orders_totals +=$t['price_totals'];
                                $commission +=$t['commission'];
                            }
                        }
                    }
                }
                $insert_num = $this->db->prepare('insert into log_user_sp_count (uid,num,type,add_time) values (:uid,:num,:type,:add_time)');
                $insert_num->execute(array(':uid'=>$v['uid'],'num'=>$orders_num ? $orders_num : 0,':type'=>2,':add_time'=>$date));
                $insert_price = $this->db->prepare('insert into log_user_sp_count (uid,num,type,add_time) values (:uid,:num,:type,:add_time)');
                $insert_price->execute(array(':uid'=>$v['uid'],'num'=>$orders_totals ? $orders_totals : 0,':type'=>3,':add_time'=>$date));
                $insert_commission= $this->db->prepare('insert into log_user_sp_count (uid,num,type,add_time) values (:uid,:num,:type,:add_time)');
                $insert_commission->execute(array(':uid'=>$v['uid'],'num'=>$commission ? $commission : 0,':type'=>4,':add_time'=>$date));
            }
        }
    }
    //用户钱袋修改金额
    public function addMoneyBagByUid($money,$uid,$type='+'){
        $stmt = $this->db->prepare("UPDATE `user` SET money_bag = (money_bag $type $money) WHERE uid =:uid");
        $array = array(
            ':uid'=>$uid,
        );
        $stmt->execute($array);
    }
    //用户未到帐收益修改金额
    public function addUnUseMoneyByUid($money,$uid,$type='+'){
        $stmt = $this->db->prepare("UPDATE `user` SET un_use_money = (un_use_money $type $money) WHERE uid =:uid");
        $array = array(
            ':uid'=>$uid,
        );
        $stmt->execute($array);
    }
    //获取用户钱袋金额
    public function getMoneyBagByUid($uid){
        $stmt = $this->db->prepare("select money_bag from `user` WHERE uid =:uid");
        $array = array(
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['money_bag'];
    }
    //根据uid查询用户分享记录
    public function getSpListByUid($uid){
        $stmt = $this->db->prepare("select id,uid,type,obj_id from `share_promote` WHERE uid =:uid");
        $array = array(
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    //获取用户分享报表某个时间端的总数
    public function getUserSpLogInfo($uid,$type,$day_type){
        $total =0;
        if($day_type==1){
            $date = date('Y-m-d',strtotime('-1 day'));
            $fields = ' and add_time='.$date.'';
        }elseif($day_type==7){
            $date = date('Y-m-d',strtotime('-7 day'));
            $fields = ' and add_time>'.$date.'';
        }elseif($day_type==30){
            $date = date('Y-m-d',strtotime('-30 day'));
            $fields = ' and add_time>'.$date.'';
        }
        $stmt = $this->db->prepare("select num from `log_user_sp_count` WHERE type=:type and uid=:uid $fields");
        $array = array(
            ':type'=>$type,
            ':uid'=>$uid
        );
        $stmt->execute($array);
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($rs){
            foreach($rs as $v){
                $total+=$v['num'];
            }
        }
        return $total;
    }
    //用户收藏数量
    public function collectNum($uid){
        $stmt = $this->db->prepare("select count(*) as num from collect where uid =:uid and status = 1");
        $stmt->bindValue ( ':uid' ,  $uid ,  PDO :: PARAM_INT );
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        return $count['num'];
    }
    //根据不同时间条件获取用户提现次数
    public function getUserWithdrawNum($uid,$date){
        $stmt = $this->db->prepare("SELECT COUNT(*) AS num FROM user_withdraw WHERE add_time >:date AND STATUS < 2 and uid=:uid");
        $array = array(
            ':date'=>$date,
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        return $count['num'];
    }
    //获取用户当日已提款金额
    public function getUserWithdrawMoney($uid,$date){
        $stmt = $this->db->prepare("SELECT SUM(money) AS totals FROM user_withdraw WHERE add_time >:date AND STATUS < 2 AND uid=:uid");
        $array = array(
            ':date'=>$date,
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['totals'];
    }
    //提现记录入库
    public function addWithDraw($serial_number,$money,$poundage,$type,$name,$account_number,$bank,$uid,$mobile,$country_code){
        $stmt = $this->db->prepare("insert into user_withdraw (serial_number,uid,money,poundage,type,name,account_number,bank,mobile,country_code) values (:serial_number,:uid,:money,:poundage,:type,:name,:account_number,:bank,:mobile,:country_code);");
        $array=array(
            ':serial_number'=>$serial_number,
            ':uid'=>$uid,
            ':money'=>$money,
            ':poundage'=>$poundage,
            ':type'=>$type,
            ':name'=>$name,
            ':account_number'=>$account_number,
            ':bank'=>$bank ? $bank : '',
            ':mobile'=>$mobile,
            ':country_code'=>$country_code
        );
        $stmt->execute($array);
        $id = $this->db->lastInsertId();
        if(!$id){
            return 0;
        }
        //入流水
        $this->addUserSerial($id,1,$uid);
        //扣除钱袋金额
        $this->addMoneyBagByUid($money,$uid,'-');
        return $id;
    }
    //获取手机用户注册信息
    public function getRegInfoByUid($uid){
        $stmt = $this->db->prepare("SELECT user_name,reg_country_code FROM user WHERE reg_type = 2 AND uid=:uid");
        $array = array(
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs;
    }
    //获取用户提现银行卡列表
    public function getUserBank($uid){
        $stmt = $this->db->prepare("SELECT * FROM user_bank WHERE  uid=:uid and status = 1 order by id desc");
        $array = array(
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rs;
    }
    //获取一条提现信息
    public function getOneWithDrawInfo($id){
        $stmt = $this->db->prepare("SELECT * FROM user_withdraw WHERE id=:id");
        $array = array(
            ':id'=>$id,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs;
    }
    //添加用户明细
    public function addUserSerial($obj_id,$type,$uid){
        $stmt = $this->db->prepare("insert into user_serial (obj_id,type,uid) values (:obj_id,:type,:uid);");
        $array=array(
            ':obj_id'=>$obj_id,
            ':type'=>$type,
            ':uid'=>$uid
        );
        $stmt->execute($array);
        $this->db->lastInsertId();
    }
    //根据id获取某一条分享记录
    public function getSpInfoById($id){
        $stmt = $this->db->prepare("SELECT * FROM share_promote WHERE id=:id");
        $array = array(
            ':id'=>$id,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs;
    }
    //获取用户流水明细
    public function getUserSerialList($uid,$type,$last_id,$size){
        $fields = $last_id ? ' and id <'.$last_id.'' : '';
        if(!$type){
            $type_fields = '';
        }elseif($type==1){
            $type_fields = ' and type in(2,3)';
        }elseif($type==2){
            $type_fields = ' and type in(4,5)';
        }elseif($type==3){
            $type_fields = ' and type =1';
        }
        if($type !=4){
            $stmt = $this->db->prepare("SELECT * FROM user_serial WHERE uid=:uid $fields $type_fields order by id desc limit :size");
        }else{
            $stmt = $this->db->prepare("SELECT * FROM user_serial WHERE (TYPE IN (3,5) AND obj_id IN (SELECT id FROM event_orders where order_status= 1) OR TYPE IN(2,4) AND obj_id IN(SELECT id FROM stage_goods_orders where order_status in (1,2,6))) AND uid =:uid $fields  ORDER BY id desc LIMIT :size");
        }
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        if($rs){
            foreach($rs as $k=> $v){
                $list[$k]['add_time'] = $v['add_time'];
                //提现
                if($v['type']==1){
                    $info = $this->getOneWithDrawInfo($v['obj_id']);
                    $list[$k]['id'] = $v['id'];
                    $list[$k]['info_type'] = $v['type'];
                    $list[$k]['status'] = $info['status'];
                    $list[$k]['money'] = $info['money'];
                    $list[$k]['type_name'] = '提现';
                    $list[$k]['code_name'] = '';
                    $list[$k]['serial_name'] ='';
                }
                //商品订单
                if($v['type']==2||$v['type']==4){
                    $stagegoodsModel = new StagegoodsModel();
                    $info = $stagegoodsModel->getOrderInfoById($v['obj_id']);
                    $goods_info = $stagegoodsModel->getGoodsRedisById($info['goods_id']);
                    $list[$k]['id'] = $v['id'];
                    $list[$k]['info_type'] = $v['type'];
                    $list[$k]['status'] = $info['order_status'];
                    if($v['type']==2){
                        $list[$k]['money'] = $info['fact_totals'];
                    }else{
                        $list[$k]['money'] = $info['commission'];
                    }
                    $list[$k]['type_name'] = '商品';
                    $list[$k]['code_name'] = '';
                    $list[$k]['serial_name'] = $goods_info['name'];
                }
                //活动订单
                if($v['type']==3||$v['type']==5){
                    $eventModel = new EventModel();
                    $info = $eventModel->orderInfoById($v['obj_id']);
                    $list[$k]['id'] = $v['id'];
                    $list[$k]['info_type'] = $v['type'];
                    $list[$k]['status'] = $info['order_status'];
                    if($v['type']==2){
                        $list[$k]['money'] = $info['fact_totals'];
                    }else{
                        $list[$k]['money'] = $info['commission'];
                    }
                    $event_info = $eventModel->getEventRedisById($info['eid']);
                    $event_type = Common::eventType($event_info['type']);
                    $list[$k]['type_name'] = $event_type['name'];
                    $list[$k]['code_name'] = $event_type['code'];
                    $list[$k]['serial_name'] = $event_info['title'];
                }
            }
        }
        return $list;
    }
    //获取用户未到账金额
    public function getUnUseMoneyByUid($uid){
        $stmt = $this->db->prepare("select un_use_money from `user` WHERE uid =:uid");
        $array = array(
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['un_use_money'];
    }
    //获取一条提现信息
    public function getOneSerialInfo($id){
        $stmt = $this->db->prepare("SELECT * FROM user_serial WHERE id=:id");
        $array = array(
            ':id'=>$id,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs;
    }
    public function getUserUnUserMoneyList($uid,$last_id,$size){
        $fields = $last_id ? ' and id <'.$last_id.'' : '';
        $sql = "SELECT * FROM user_serial WHERE (TYPE IN (3,5) AND obj_id IN (SELECT id FROM event_orders where order_status= 1) OR TYPE IN(2,4) AND obj_id IN(SELECT id FROM stage_goods_orders where order_status in (1,2,6))) AND uid =:uid $fields  ORDER BY id desc LIMIT :size";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $list = array();
        if($rs){
            foreach($rs as $k=> $v){
                $list[$k]['add_time'] = $v['add_time'];
                //商品订单
                if($v['type']==2||$v['type']==4){
                    $stagegoodsModel = new StagegoodsModel();
                    $info = $stagegoodsModel->getOrderInfoById($v['obj_id']);
                    $goods_info = $stagegoodsModel->getGoodsRedisById($info['goods_id']);
                    $list[$k]['id'] = $v['id'];
                    $list[$k]['info_type'] = $v['type'];
                    $list[$k]['status'] = $info['status'];
                    if($v['type']==2){
                        $list[$k]['money'] = $info['fact_totals'];
                    }else{
                        $list[$k]['money'] = $info['commission'];
                    }
                    $list[$k]['type_name'] = '商品';
                    $list[$k]['code_name'] = '';
                    $list[$k]['serial_name'] = $goods_info['name'];
                }
                //活动订单
                if($v['type']==3||$v['type']==5){
                    $eventModel = new EventModel();
                    $info = $eventModel->orderInfoById($v['obj_id']);
                    $list[$k]['id'] = $v['id'];
                    $list[$k]['info_type'] = $v['type'];
                    $list[$k]['status'] = $info['status'];
                    if($v['type']==2){
                        $list[$k]['money'] = $info['fact_totals'];
                    }else{
                        $list[$k]['money'] = $info['commission'];
                    }
                    $event_info = $eventModel->getEventRedisById($info['eid']);
                    $event_type = Common::eventType($event_info['type']);
                    $list[$k]['type_name'] = $event_type['name'];
                    $list[$k]['code_name'] = $event_type['code'];
                    $list[$k]['serial_name'] = $event_info['title'];
                }
            }
        }
        return $list;
    }

    // 根据用户uid获取当前用户的融云IM的token值
    public function getTokenRcIm($uid){
        if(!$uid){
            return false;
        }
        $stmt = $this->db->prepare("select token from user where uid=:uid");
        $array = array(
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    //用户用户提现银行卡
    public function delUserBank($id){
        $stmt = $this->db->prepare("UPDATE user_bank SET status = 0  WHERE id =:id");
        $array = array(
            ':id'=>$id,
        );
        $stmt->execute($array);
    }
}

