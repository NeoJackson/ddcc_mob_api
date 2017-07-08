<?php
/**
 * Class AlbumModel
 */
class AlbumModel {
    private $db;
    private $redis;
    private $albumNum = 50;
    public  $photoNum = 200;
    private $typeArr = array(
                0 => '自定义相册',
                1 => '系统相册',
            );

    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }

    /*
     * @name 看指定用户相册名是否已经存在
     */
    private function getAlbumByName($name,$uid,$id=''){
        if($id == ''){
            $stmt = $this->db->prepare("select count(id) as num from album where name=:name and uid=:uid and status < 2");
            $array = array(
                ':name'=>$name,
                ':uid'=>$uid,
            );
        }else{
            $stmt = $this->db->prepare("select count(id) as num from album where name=:name and uid=:uid and id != :id and status < 2");
            $array = array(
                ':name'=>$name,
                ':uid'=>$uid,
                ':id'=>$id,
            );
        }

        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }
    /*
     * @name 创建相册
     */
    public function add($uid,$name,$is_public,$intro){
        $nameCount = $this->getAlbumByName($name,$uid);
        if($nameCount>0){
            return -1;
        }
        $num = $this->getNumCount($uid);
        if($num >= $this->albumNum){
            return -2;
        }
        $stmt = $this->db->prepare("insert into album (uid,name,is_public,intro,add_time) values (:uid,:name,:is_public,:intro,:add_time)");
        $array = array(
            ':uid'=>$uid,
            ':name'=>$name,
            ':is_public'=>$is_public,
            ':intro'=>$intro,
            ':add_time'=>date("Y-m-d H:i:s"),
        );
        $stmt->execute($array);
        return $this->db->lastInsertId();
    }

    public function getAlbumByPhotoId($id){
        $stmt = $this->db->prepare("select album_id,status from album_photo where id=:id and status < 2");
        $array = array(
            ':id'=>$id,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        $album_id = $rs['album_id'];
        return $this->getAlbumForFeed($album_id);
    }

    public function getLastPhotoByAlbumId($album_id){
        $stmt = $this->db->prepare("select id,img from album_photo where album_id=:album_id and status < 2 order by id desc limit 1");
        $array = array(
            ':album_id'=>$album_id,
        );
        $stmt->execute($array);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPhotoById($id){
        $stmt = $this->db->prepare("select id,album_id,img as album_img,intro,uid,is_recommend,status,add_time from album_photo where id=:id and status < 2");
        $array = array(
            ':id'=>$id,
        );
        $stmt->execute($array);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAlbumById($id,$uid=''){
        if($uid){
            $stmt = $this->db->prepare("select * from album where uid=:uid and id=:id and status < 2");
            $array = array(
                ':uid'=>$uid,
                ':id'=>$id,
            );
        }else{
            $stmt = $this->db->prepare("select * from album where id=:id and status < 2");
            $array = array(
                ':id'=>$id,
            );
        }
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result){
            $feedModel = new FeedModel();
            $result['comment_num'] = $feedModel->getAlbumCommentNum($result['id']);
        }
        return $result;
    }
    /*
     * @name 修改相册
     */
    public function modify($id,$uid,$name,$is_public,$intro){
        $nameCount = $this->getAlbumByName($name,$uid,$id);
        if($nameCount>0){
            return -1;
        }
        $albumInfo = $this->getAlbumById($id,$uid);
        if(!$albumInfo){
            return -2;
        }
        if(in_array($albumInfo['type'],array(1,2,3))){
            return -3;
        }
        $stmt = $this->db->prepare("update album set uid=:uid,name=:name,is_public=:is_public,intro=:intro,update_time=:update_time where id=:id");
        $array = array(
            ':id'=>$id,
            ':uid'=>$uid,
            ':name'=>$name,
            ':is_public'=>$is_public,
            ':intro'=>$intro,
            ':update_time'=>date("Y-m-d H:i:s"),
        );
        $stmt->execute($array);
        return 1;
    }
    /*
     * @name 根据相册的id显示相册信息
     */
    public function show($id,$uid){
        $getAlbumById = $this->getAlbumById($id);
        if(!$getAlbumById){
            return 0;
        }
        $isSelf = $this->self($id,$uid);
        if($isSelf == 0){
            return -1;
        }
        $stmt = $this->db->prepare("select * from album where status < 2 and id=:id");
        $array = array(
            ':id'=>$id,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs;
    }

    /*
     * @name 查看用户指定相册中照片的张数
     */
    public function getPhotoNum($album_id,$uid=''){
        if($uid){
            $stmt = $this->db->prepare("select count(id) as num from album_photo where uid=:uid and album_id=:album_id and status < 2");
            $array = array(
                ':uid' => $uid,
                ':album_id' => $album_id,
            );
        }else{
            $stmt = $this->db->prepare("select count(id) as num from album_photo where album_id=:album_id and status < 2");
            $array = array(
                ':album_id' => $album_id,
            );
        }
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }
    /*
     * @name 更新相册中的照片数
     */
    public function updateAlbumNumById($id){
        $photo_num = $this->getPhotoNum($id);
        $stmt = $this->db->prepare("update album set photo_num=:photo_num where id=:id and status < 2");
        $array = array(
            ':photo_num' => $photo_num,
            ':id' => $id,
        );
        return $stmt->execute($array);
    }
    /*
     * @name 删除相册对应的照片
     */
    public function delPhotos($uid,$album_id){
        $stmt = $this->db->prepare("update album_photo set status = 4 where album_id=:album_id and uid=:uid and status < 2");
        $array = array(
            ':album_id' => $album_id,
            ':uid' => $uid,
        );
        $stmt->execute($array);
    }

    /*
     * @name 删除相册
     */
    public function del($id,$uid){
        $albumInfo = $this->getAlbumById($id,$uid);
        if(!$albumInfo){
            return -1;
        }
        $stmt = $this->db->prepare("update album set status = 4 where id=:id and uid=:uid and status < 2");
        $array = array(
            ':id' => $id,
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $rs = $stmt->rowCount();
        $userPhoto = $this->getPhotoNum($id,$uid);
        if($userPhoto>0){
            $this->delPhotos($uid,$id);
            $photo_ids = $this->getPhotoByAlbumId($id);//查询该相册下的照片id
            $commonModel = new CommonModel();
            foreach($photo_ids as $val){
                $commonModel->updateRelationByObjId(2,$val['id'],4);//删除相对应的评论、喜欢、打赏等相关信息
            }
        }
        return $rs;
    }

    //根据相册id查询照片
    public function getPhotoByAlbumId($album_id){
        $stmt = $this->db->prepare("select id from album_photo where album_id=:album_id");
        $array = array(
            ':album_id'=>$album_id,
        );
        $stmt->execute($array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
     * @name 统计用户相册的个数
     */
    public function getNum($uid,$s_uid=0){
        $stmt = $this->db->prepare("select id,is_public from album where uid=:uid and status < 2");
        $array = array(
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $request = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($request){
            foreach($request as $key=>$val){
                //相册0仅自己可见 1公开 2关注的人可见  3知己可见
                $type_arr = array(
                    '1'=>'1','2'=>'2','3'=>'3','0'=>'4'
                );
                $display = $this->getDisplay($type_arr[(int)$val['is_public']],$uid,$s_uid);
                if(!$display){
                    unset($request[$key]);
                }
            }
        }
        return count($request);
    }
    //查询权限
    public function getDisplay($status,$uid,$s_uid){
        $display = 0;//不可见
        switch($status){
            case '1'://所有人可见
                $display = 1;
                break;
            case '2'://我关注的人可见
                if($uid == $s_uid){
                    $display = 1;
                }else{
                    $followModel = new FollowModel();
                    $ret = $followModel->isFollow($uid,$s_uid);
                    $display = $ret ? 1 : 0;
                }
                break;
            case '3'://知己可见
                if($uid == $s_uid){
                    $display = 1;
                }else{
                    $followModel = new FollowModel();
                    $ret1 = $followModel->isFollow($uid,$s_uid);
                    $ret2 = $followModel->isFollow($s_uid,$uid);
                    $display = ($ret1 && $ret2) ? 1 : 0;
                }
                break;
            case '4'://仅自己可见
                $display = 0;
                if($uid){
                    $display = ($uid == $s_uid) ? 1 : 0;
                }
                break;
        }
        return $display;
    }

    /*
      * @name 创建相册时统计用户自定义(全部)相册的个数
      */
    public function getNumCount($uid,$type=0){
        $type = $type ? $type : 0;
        $stmt = $this->db->prepare("select count(id) as num from album where uid=:uid and status < 2 and type =:type");
        $array = array(
            ':uid'=>$uid,
            ':type'=>$type
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    /*
     * @name 取相册封面
     */
    public function coverImg($id,$album_id){
        if($id == 0){
            $stmt = $this->db->prepare("select img from album_photo where album_id=:album_id order by id desc limit 1");
            $array = array(
                ':album_id'=>$album_id,
            );
        }else{
            $stmt = $this->db->prepare("select img from album_photo where id =:id");
            $array = array(
                ':id'=>$id,
            );
        }
        $stmt->execute($array);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$request['img']){
            $stmt = $this->db->prepare("select img from album_photo where album_id=:album_id order by id desc limit 1");
            $array = array(
                ':album_id'=>$album_id,
            );
            $stmt->execute($array);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return $request['img'];
    }
    /*
     * @name 用户中心相册列表显示(其他人访问时调用)
     */
    public function getListByUid($uid,$s_uid){
        $stmt = $this->db->prepare("select id,uid,name,type,is_public,cover_id,cover_img,photo_num,status,add_time,update_time,
        intro from album where uid=:uid and status < 2 order by type desc,add_time desc");
        $array = array(
            ':uid' => $uid
        );
        $stmt->execute($array);
        $request = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $arr = array();
        if($request){
            foreach($request as $key=>$val){
                if($val['cover_img']){
                    $request[$key]['cover_img'] = IMG_DOMAIN.$val['cover_img'];
                }else{
                    $request[$key]['cover_img'] = IMG_DOMAIN.'default_avatar.jpg';
                }
                //相册0仅自己可见 1公开 2关注的人可见  3知己可见
                $type_arr = array(
                    '1'=>'1','2'=>'2','3'=>'3','0'=>'4'
                );
                $display = 1;
                if($uid!=$s_uid){
                    $display = $this->getDisplay($type_arr[(int)$request[$key]['is_public']],$uid,$s_uid);
                }
                if($display){
                    if($val['type']==0){
                        $arr['custom'][] = $request[$key];
                    }else{
                        $arr['system'][] = $request[$key];
                    }
                }
            }
        }
        return $arr;
    }
    /*
     * @name 相册列表显示(其他人访问时调用)
     */
    public function getList($uid,$f_uid,$page,$size){
        $totalNumber = $this->getNum($f_uid,1);
        $totalPage=ceil($totalNumber/$size); //计算出总页数
        if($totalPage == 0){
            return 0;
        }
        if($page<1){
            $page = 1;
        }
        if($page>$totalPage){
            $page = $totalPage;
        }
        $start_count=($page-1)*$size;
        if($uid == $f_uid){
            $stmt = $this->db->prepare("select * from album where uid=:uid and status < 2 order by type asc,add_time desc limit :start_count,:size");
        }else{
            $stmt = $this->db->prepare("select * from album where uid=:uid and status < 2 and is_public!=3 order by type asc,add_time desc limit :start_count,:size");
        }
        $stmt->bindValue(':uid',$f_uid, PDO::PARAM_INT);
        $stmt->bindValue(':start_count',$start_count, PDO::PARAM_INT);
        $stmt->bindValue(':size',$size, PDO::PARAM_INT);
        $stmt->execute();
        $request = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($request){
            foreach($request as $k=>$v){
                $request[$k]['cover_img'] = $this->coverImg($v['cover_id'],$v['id']);
                $request[$k]['num'] = $this->getPhotoNum($v['id'],$f_uid);
            }
        }
        return $request;
    }
    /*
     * @name 取用户的默认相册
     */
    public function userDefault($uid,$type=0){
        $stmt = $this->db->prepare("select id from album where uid=:uid and status < 2 and type=:type");
        $array = array(
            ':uid' => $uid,
            ':type' => $type,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['id'];
    }

    /*
     * @name 修改相册描述
     * $id 相册id
     * $uid 用户id
     */
    public function modifyIntro($id,$uid,$intro){
        $getAlbumById = $this->getAlbumById($id);
        if(!$getAlbumById){
            return 0;
        }
        $isSelf = $this->self($id,$uid);
        if($isSelf == 0){
            return -1;
        }
        $stmt = $this->db->prepare("update album set intro =:intro where id =:id and status < 2");
        $array = array(
            ':intro'=>$intro,
            ':id'=>$id,
        );
        $stmt->execute($array);
        return 1;
    }
    /*
     * @name 增加照片
     * uid 用户id
     * album_id 相册id
     */
    public function addPhoto($uid,$album_id,$imgarray,$introarray,$add_time,$is_public){
        if(!$album_id){
            $album_id = $this->userDefault($uid);
        }
        $album = $this->getAlbumById($album_id);
        $photoNum = $this->photoNum;
        if($album['photo_num'] + count($imgarray) > $photoNum && $album['type'] == 0){
            $num = ($album['photo_num'] + count($imgarray)) - $photoNum;
            return array(
                'status'=>$num,
            );
        }
        foreach($imgarray as $k=> $v){
            $stmt = $this->db->prepare("insert into album_photo (album_id,img,intro,uid,add_time) values (:album_id,:img,:intro,:uid,:add_time)");
            $array = array(
                ':album_id' => $album_id,
                ':img' => $v,
                ':intro'=> $introarray[$k] ? $introarray[$k] : '',
                ':uid' => $uid,
                ':add_time' => $add_time,
            );
            $stmt->execute($array);
        }
        if($is_public == 1&& $album['type'] != 1){
            //添加到动态
            Common::http(OPEN_DOMAIN."/common/addFeed",array('scope'=>1,'uid'=>$uid,'type'=>'photo',"id"=>$album_id,"time"=>strtotime($add_time)),"POST");
            //发放福报值和经验
            $scoreModel = new ScoreModel();
            $scoreModel->add($uid,0,'photo',$album_id);
        }
        //更新封面和照片数量
        if($album['cover_id'] == 0){
            $photo = $this->getLastPhotoByAlbumId($album_id);
            if($photo){
                $this->setCover($album_id,$photo['id'],$photo['img']);
            }
        }
        $this->updateAlbumNumById($album_id);
        return array(
            'status'=>0
        );
    }

    /*
     * @name 删除照片
     * id_array 图片的id一维数组 $id_array  = array(39,40,41);
     * uid 登录用户的用户编号
     */
    public function delPhoto($id_array,$uid,$album_id){
        $ids = $id_array;
        $place_holders  =  implode ( ',' ,  array_fill ( 0 ,  count ( $id_array ),  '?' ));
        array_push($id_array,$uid);
        $stmt = $this->db->prepare ( "update album_photo set status = 4 where id in ( $place_holders ) and uid=?" );
        $stmt->execute($id_array);
        $count = $stmt->rowCount();
        if($count < 1){
            return 0;
        }
        if($ids){
            $commonModel = new CommonModel();
            foreach($ids as $val){
                $commonModel->updateRelationByObjId(2,$val,4);//删除相对应的评论、喜欢、打赏等相关信息

            }
        }
        $stmt_photo = $this->db->prepare("select distinct add_time from album_photo where id in ( $place_holders ) and uid=?");
        $stmt_photo->execute($id_array);
        $list =  $stmt_photo->fetchAll(PDO::FETCH_ASSOC);
        if($list){
            foreach($list as $v){
                $stmt_num = $this->db->prepare("SELECT COUNT(*) AS num FROM album_photo WHERE add_time = :add_time AND STATUS = 0 AND album_id = :album_id");
                $array = array(
                    ':add_time'=>$v['add_time'],
                    ':album_id'=>$album_id
                );
                $stmt_num->execute($array);
                $num =  $stmt_num->fetch(PDO::FETCH_ASSOC);
                if($num['num']==0){
                    $feedModel = new FeedModel();
                    $feedModel->del($uid,'photo',$album_id,strtotime($v['add_time']));
                }
            }
        }
        return $count;
    }

    /*
     * @name 设置图片为相册封面
     * id 图片的id
     * album_id 相册id
     * uid 登录用户的用户编号
     */
    public function setCover($album_id,$id,$cover_img){
            $stmt = $this->db->prepare("update album set cover_id=:cover_id,cover_img=:cover_img  where id=:album_id and status < 2");
            $array = array(
                ':cover_id'=>$id,
                ':cover_img'=>$cover_img,
                ':album_id'=>$album_id
            );
            return $stmt->execute($array);
    }

    /*
     * @name 判断访问的相册是否是自己的
     * $id 相册id
     * $f_uid 访问者的用户id
     */
    public function self($id,$uid){
        $stmt = $this->db->prepare("select uid from album where id=:id");
        $array = array(
            ':id'=>$id,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        if($uid == $rs['uid']){
            return 1;
        }
        return 0;
    }


    /*
     * @name 显示相册里面的照片
     */
    public function getPhotoList($id,$page,$size){
//        if($size > 300){
//            $size = 300;
//        }
        $totalNumber = $this->getPhotoNum($id);
        $totalPage=ceil($totalNumber/$size); //计算出总页数
        if($totalPage == 0){
            return 0;
        }
        if($page<1){
            $page = 1;
        }
        if($page>$totalPage){
            return 0;
        }
        $start_count=($page-1)*$size;
        $stmt = $this->db->prepare("select * from album_photo where album_id=:id and status < 2 order by add_time desc,id desc limit :start_count,:size");
        $stmt->bindValue(':id',$id, PDO::PARAM_INT);
        $stmt->bindValue(':start_count',$start_count, PDO::PARAM_INT);
        $stmt->bindValue(':size',$size, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getPhotoListByLastTime($id,$last_time,$size){
//        if($size > 300){
//            $size = 300;
//        }
        if($last_time){
            $stmt = $this->db->prepare("select * from album_photo where album_id=:id and status < 2 and add_time < :last_time order by add_time desc,id desc limit :size");
            $stmt->bindValue(':last_time',$last_time, PDO::PARAM_INT);
        }else{
            $stmt = $this->db->prepare("select * from album_photo where album_id=:id and status < 2 order by add_time desc,id desc limit :size");
        }
        $stmt->bindValue(':id',$id, PDO::PARAM_INT);
        $stmt->bindValue(':size',$size, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
     * 获取初始化相册
     */
    public function getInitAlbumId($uid,$type){
        if(!in_array($type,array(1,2,3))){
            return false;
        }
        $stmt = $this->db->prepare("select id from album where status < 2 and uid=:uid and type = :type");
        $array = array(
            ':uid'=>$uid,
            ':type'=>$type,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['id'];
    }
    //用于防止头像相册avatar重复
    public function getAlbumImages($uid,$album_id,$avatar){
        $sql = 'select * from album_photo
                where album_id = :album_id
                and uid = :uid and img = :img and status < 2';
        $stmt = $this->db->prepare($sql);
        $array = array(
            ':uid' => $uid,
            ':img'=> $avatar,
            ':album_id'=>$album_id
        );
        $stmt->execute($array);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    //批量修改照片描述
    public function modifyPhotoIntro($id_array,$uid,$intro){
        $place_holders  =  implode ( ',' , $id_array);
        $stmt = $this->db->prepare ( "update album_photo set intro =:intro where id in ( $place_holders ) and uid=:uid" );
        $stmt->bindValue(':intro', $intro, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->rowCount();
        return $count;
    }
    public function getPhotoByAlbumIdAndTime($album_id,$add_time){
        $stmt = $this->db->prepare("select * from album_photo where album_id=:album_id and add_time=:add_time and status < 2 order by id desc limit 0,9");
        $array = array(
            ':album_id'=>$album_id,
            ':add_time'=>$add_time,
        );
        $stmt->execute($array);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $num_stmt = $stmt = $this->db->prepare("select count(*) as num from album_photo where album_id=:album_id and add_time=:add_time and status < 2");
        $num_stmt->execute($array);
        $num = $num_stmt->fetch(PDO::FETCH_ASSOC);
        return array(
            'list'=>$list,
            'size'=>$num['num']
        );
    }
    public function getAlbumForFeed($id,$uid=''){
        if($uid){
            $stmt = $this->db->prepare("select id,uid,name,status,cover_id,photo_num from album where uid=:uid and id=:id and status < 2");
            $array = array(
                ':uid'=>$uid,
                ':id'=>$id,
            );
        }else{
            $stmt = $this->db->prepare("select id,uid,name,status,cover_id,photo_num from album where id=:id and status < 2");
            $array = array(
                ':id'=>$id,
            );
        }
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    public function getPhotoByAlbumIdForFeed($album_id,$add_time,$uid){
        $stmt = $this->db->prepare("select id,album_id,img,intro,like_num,comment_num,uid from album_photo where album_id=:album_id and add_time=:add_time and status < 2 order by id desc limit 0,9");
        $array = array(
            ':album_id'=>$album_id,
            ':add_time'=>$add_time,
        );
        $stmt->execute($array);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $likeModel = new LikeModel();
        if($list){
            foreach($list as $k=>$v){
                $list[$k]['is_like'] = $likeModel->hasData(2,$v['id'],$uid);
                $list[$k]['show_img'] = Common::show_img($v['img'],4,160,160);
            }
        }
        $num_stmt = $stmt = $this->db->prepare("select count(*) as num from album_photo where album_id=:album_id and add_time=:add_time and status < 2");
        $num_stmt->execute($array);
        $num = $num_stmt->fetch(PDO::FETCH_ASSOC);
        return array(
            'list'=>$list,
            'size'=>$num['num']
        );
    }
    //根据相册id和添加相片时间 删除相片
    public function delPhotoForFeed($album_id,$add_time){
        //查询相片id
        $stmt = $this->db->prepare("select id,uid from album_photo where album_id=:album_id and add_time=:add_time and status < 2 order by id desc limit 0,9");
        $array = array(
            ':album_id'=>$album_id,
            ':add_time'=>$add_time,
        );
        $stmt->execute($array);
        $ids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($ids){
            foreach($ids as $val){
                $this->delPhoto($val['id'],$val['uid'],$album_id);
            }
        }
    }
}