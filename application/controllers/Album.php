<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 14-10-23
 * Time: 下午2:35
 */

class AlbumController extends Yaf_Controller_Abstract {
    private $nameLen = 25;
    private $introLen = 70;
    public function init(){
        $this->startTime = microtime(true);
    }
    //相册列表
    public function albumListAction(){
        $data['token'] = $this->getRequest()->get('token');
        $user['uid'] = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $uid = intval($this->getRequest()->getPost('uid'));//查看的用户
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;//版本号
        $albumModel = new AlbumModel();
        if(!$uid){
            $uid = $user['uid'];
        }
        $albumList = $albumModel->getListByUid($uid,$user['uid']);
        Common::appLog('album/albumList',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$albumList ? $albumList : array());
    }
    //获取照片列表
    public function photoListAction(){
        $token = $this->getRequest()->getPost('token');
        $uid = 0;
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $id = intval($this->getRequest()->getPost('id'));
        $page = intval($this->getRequest()->getPost('page'));
        $size = intval($this->getRequest()->getPost('size'));
        $last_time = $this->getRequest()->getPost('last_time');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;//版本号
        if(!$id){
            Common::echoAjaxJson(2,'访问的相册的不存在');
        }
        $albumModel = new AlbumModel();
        $likeModel = new LikeModel();
        $userModel = new UserModel();
        $album = $albumModel->getAlbumById($id);
        if(!$album){
            Common::echoAjaxJson(2,'访问的相册的不存在');
        }
        $size = $size ? $size : 24;
        if($page){
            $photo_list = $albumModel->getPhotoList($id,$page,$size);
        }else{
            $photo_list = $albumModel->getPhotoListByLastTime($id,$last_time,$size);
        }
        if($photo_list){
            foreach($photo_list as $k=>$v){
                $userInfo = $userModel->getUserData($v['uid']);
                $photo_list[$k]['nick_name'] = $userInfo['nick_name'];
                $photo_list[$k]['is_like'] = $likeModel->hasData(2,$v['id'],$uid);
                $photo_list[$k]['img'] = IMG_DOMAIN.$v['img'];
                $photo_list[$k]['show_img'] = Common::show_img($v['img'],4,360,360);
                $photo_list[$k]['update_time'] = $v['update_time'] ? $v['update_time'] : '';
            }
        }
        Common::appLog('album/photoList',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$photo_list ? $photo_list : array());
    }
    //新建相册
    public function addAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(10, "非法登录用户");
        }
        $uid = $user['uid'];
        $name = $this->getRequest()->getPost('name'); //相册名
        $is_public = $this->getRequest()->getPost('is_public'); //相册权限  0私密 1公开
        $intro = $this->getRequest()->getPost('intro');
        $security = new Security();
        $name = $security->xss_clean(trim($name));//相册名
        $intro = $security->xss_clean(trim($intro));//相册介绍
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;//版本号
        if($name===''){
            Common::echoAjaxJson(2,'请输入相册名称');
        }
        if(preg_match('/[A-Za-z]{1,}/',$name)){
            Common::echoAjaxJson(3,'相册名称不能包含英文字符');
        }
        if(mb_strlen($name,'utf-8') > $this->nameLen){
            Common::echoAjaxJson(4,'相册名称为1-25个字符');
        }
        if($intro && mb_strlen($intro,'utf-8') > $this->introLen){
            Common::echoAjaxJson(5,'相册描述为1-70个字符');
        }
        if(preg_match('/[A-Za-z]{1,}/',$intro)){
            Common::echoAjaxJson(6,'相册描述不能包含英文字符');
        }
        if(!in_array($is_public,array(0,1,2,3))){
            $is_public = 1;
        }
        $albumModel = new AlbumModel();
        $rs = $albumModel->add($uid,$name,$is_public,$intro);
        if($rs == -1){
            Common::echoAjaxJson(7,'相册名已存在');
        }
        if($rs == -2){
            Common::echoAjaxJson(8,'相册个数已达上限');
        }
        if($rs<1){
            Common::echoAjaxJson(9,'新建相册失败');
        }
        Common::appLog('album/add',$this->startTime,$version);
        Common::echoAjaxJson(1,'新建相册成功',$rs);
    }
    //删除相册
    public function delAlbumAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $uid = $user['uid'];
        $id = $this->getRequest()->getPost('id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;//版本号
        if(!$id){
            Common::echoAjaxJson(2,'相册id为空');
        }
        $albumModel = new AlbumModel();
        $rs = $albumModel->del($id,$uid);
        if($rs==-1){
            Common::echoAjaxJson(3,'没有找到对应相册');
        }
        if($rs==0){
            Common::echoAjaxJson(4,'删除失败');
        }
        Common::appLog('album/delAlbum',$this->startTime,$version);
        Common::echoAjaxJson(1,'删除成功');
    }
    /*
    * @name 相册上传照片
    */
    public function addPhotoAction(){
        $user=Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $uid = $user['uid'];
        $album_id = $this->getRequest()->getPost('album_id');
        $imgarray = $this->getRequest()->getPost('img_array');
        $introarray = $this->getRequest()->getPost('intro_array');
        $imgarray = json_decode($imgarray,true);
        $introarray = json_decode($introarray,true);
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;//版本号
        if(!$imgarray){
            Common::echoAjaxJson(2,'没有上传图片');
        }
        $albumModel = new AlbumModel();
        $album = $albumModel->getAlbumById($album_id,$uid);
        if(!$album){
            Common::echoAjaxJson(3,'该相册不存在');
        }
        $time = time();
        $rs = $albumModel->addPhoto($uid,$album_id,$imgarray,$introarray,date('Y-m-d H:i:s',$time),$album['is_public']);
        if($rs['status'] != 0){
            Common::echoAjaxJson(4,'相册最多可上传'.$albumModel->photoNum.'张照片，目前已超过'.$rs['status'].'张。',$rs);
        }
        Common::appLog('album/addPhoto',$this->startTime,$version);
        Common::echoAjaxJson(1,'图片上传成功');
    }
    //照片详情
    public function photoAction(){
        $token = $this->getRequest()->getPost('token');
        $uid = 0;
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $id = $this->getRequest()->getPost('id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;//版本号
        if(!$id){
            Common::echoAjaxJson(3, "id为空");
        }
        $albumModel = new AlbumModel();
        $likeModel = new LikeModel();
        $userModel = new UserModel();
        $photo = $albumModel->getPhotoById($id);
        $feedModel = new FeedModel();
        if(!$photo){
            Common::echoAjaxJson(4, "该照片已不存在");
        }
        $userInfo = $userModel->getUserData($photo['uid'],$uid);
        $photo['album_img'] = IMG_DOMAIN.$photo['album_img'];
        $photo['img'] = Common::show_img($photo['album_img'],4,100,100);
        $photo['add_time'] = Common::show_time($photo['add_time']);
        $photo['feed_type'] = 2;
        $photo['user']['uid'] = $userInfo['uid'];
        $photo['user']['did'] = $userInfo['did'];
        $photo['user']['nick_name'] = $userInfo['nick_name'];
        $photo['user']['avatar'] = Common::show_img($userInfo['avatar'],1,160,160);
        $photo['user']['type'] = $userInfo['type'];
        $angelInfo = $userModel->getInfo($userInfo['uid']);
        $photo['user']['angel_info'] = isset($angelInfo['info']) ? $angelInfo['info'] :'';
        $photo['commentList'] = $feedModel->getCommentList($uid,2,$id,1,50);
        if($photo['commentList'] ){
            foreach($photo['commentList']['list'] as $k=>$v){
                $photo['commentList']['list'][$k]['user']['avatar'] = Common::show_img($v['user']['avatar'],1,160,160);
            }
        }
        $photo['is_like'] = $likeModel->hasData(2,$id,$uid);
        $photo['likeList'] = $likeModel->likeList($id,2,1,9,0);
        if($photo['likeList'] ){
            foreach($photo['likeList']['list'] as $k=>$v){
                $photo['likeList']['list'][$k]['avatar'] = Common::show_img($v['avatar'],1,160,160);
            }
        }
        Common::appLog('album/photo',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$photo);
    }
    /*
     * 修改相册
     */
    public function modifyAction(){
        $user=Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(11, "非法登录用户");
        }
        $uid = $user['uid'];
        $id = $this->getRequest()->getPost('id');
        $name = $this->getRequest()->getPost('name'); //相册名
        $intro = $this->getRequest()->getPost('intro'); //相册介绍
        $is_public = $this->getRequest()->getPost('is_public'); //相册权限  0仅自己可见 1公开 2关注的人可见  3知己可见
        $security = new Security();
        $name = $security->xss_clean(trim(Common::angleToHalf($name)));//相册名
        $intro = $security->xss_clean(trim(Common::angleToHalf($intro)));//相册介绍
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;//版本号
        if(!$id){
            Common::echoAjaxJson(2,'请选择需要修改的相册');
        }
        if($name===''){
            Common::echoAjaxJson(3,'请输入相册名称');
        }
        if(preg_match('/[A-Za-z]{1,}/',$name)){
            Common::echoAjaxJson(4,'相册名称不能包含英文字符');
        }
        if(mb_strlen($name,'utf-8') > $this->nameLen){
            Common::echoAjaxJson(5,'相册名称为1-25个字符');
        }
        if($intro && mb_strlen($intro,'utf-8') > $this->introLen){
            Common::echoAjaxJson(6,'相册描述为1-70个字符');
        }
        if(preg_match('/[A-Za-z]{1,}/',$intro)){
            Common::echoAjaxJson(7,'相册描述不能包含英文字符');
        }
        if(!in_array($is_public,array(0,1,2,3))){
            $is_public = 1;
        }
        $albumModel = new AlbumModel();
        $rs = $albumModel->modify($id,$uid,$name,$is_public,$intro);
        if($rs == -1){
            Common::echoAjaxJson(8,'相册名称已存在');
        }
        if($rs == -2){
            Common::echoAjaxJson(9,'您没有修改该相册的权限');
        }
        if($rs == -3){
            Common::echoAjaxJson(10,'系统相册不能修改');
        }
        Common::appLog('album/modify',$this->startTime,$version);
        Common::echoAjaxJson(1,'修改成功',$id);
    }
    /*
     * @name 删除照片
     */
    public function delPhotoAction(){
        $user=Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $uid = $user['uid'];
        $ids = $this->getRequest()->getPost('ids');//删除的照片ids 用&拼接
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;//版本号
        if(!$ids){
            Common::echoAjaxJson(2,'没有指定删除的图片');
        }
        $id_array = explode('&',$ids);
        $albumModel = new AlbumModel();
        //先选取一个照片ID，查询相册的cover_id
        $album = $albumModel->getAlbumByPhotoId($id_array[0]);
        //判断cover_id是否在删除的照片数组，如果是，获取最新的一张照片的id和img，设置为封面
        if(in_array($album['cover_id'],$id_array)){
            $photo = $albumModel->getLastPhotoByAlbumId($album['id']);
            if($photo){
                $albumModel->setCover($album['id'],$photo['id'],$photo['img']);
            }
        }
        $rs = $albumModel->delPhoto($id_array,$uid,$album['id']);
        if($rs == 0){
            Common::echoAjaxJson(3,'删除失败');
        }
        $albumModel->updateAlbumNumById($album['id']);
        Common::appLog('album/delPhoto',$this->startTime,$version);
        Common::echoAjaxJson(1,'删除成功');
    }
    /*
    * @name 将相册中的照片设置为相册封面
    */
    public function setCoverAction(){
        $user=Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $uid = $user['uid'];
        $id = $this->getRequest()->getPost('id'); //图片id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;//版本号
        if(!$id){
            Common::echoAjaxJson(2,'没有指定图片id');
        }
        $albumModel = new AlbumModel();
        $album = $albumModel->getPhotoById($id);
        if($uid != $album['uid']){
            Common::echoAjaxJson(3,'您没有权限修改图片封面');
        }
        $rs = $albumModel->setCover($album['album_id'],$id,$album['album_img']);
        if($rs == 0){
            Common::echoAjaxJson(4,'设置失败');
        }
        Common::appLog('album/setCover',$this->startTime,$version);
        Common::echoAjaxJson(1,'设置成功',IMG_DOMAIN.$album['album_img']);
    }
    //批量修改照片描述
    public function modifyPhotoIntroAction(){
        $user=Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(6, "非法登录用户");
        }
        $uid = $user['uid'];
        $ids = $this->getRequest()->getPost('ids');//需要修改的照片id 多张&拼接
        $intro = $this->getRequest()->getPost('intro');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;//版本号
        if(!$ids){
            Common::echoAjaxJson(2,'没有指定修改的图片');
        }
        $id_array = explode('&',$ids);
        if($intro===''){
            Common::echoAjaxJson(3,'请输入照片描述');
        }
        if(preg_match('/[A-Za-z]{1,}/',$intro)){
            Common::echoAjaxJson(4,'照片描述不能包含英文字符');
        }
        $security = new Security();
        $intro = trim($security->xss_clean($intro));
        if(mb_strlen($intro,'utf-8') > 70){
            Common::echoAjaxJson(5,'照片描述为1-70个字符');
        }
        $albumModel = new AlbumModel();
        $albumModel->modifyPhotoIntro($id_array,$uid,$intro);
        Common::appLog('album/modifyPhotoIntro',$this->startTime,$version);
        Common::echoAjaxJson(1,'描述修改成功');
    }
    //判断相册状态
    public function getStatusAction(){
        $token = $this->getRequest()->getPost('token');
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
        }
        $id = intval($this->getRequest()->getPost('id'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;//版本号
        if(!$id){
            Common::echoAjaxJson(2,'访问的相册的不存在');
        }
        $albumModel = new AlbumModel();
        $album = $albumModel->getAlbumById($id);
        if(!$album){
            Common::echoAjaxJson(2,'访问的相册的不存在');
        }
        Common::appLog('album/getStatus',$this->startTime,$version);
        Common::echoAjaxJson(1,'相册正常');
    }
}