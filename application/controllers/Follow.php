<?php
class FollowController extends Yaf_Controller_Abstract {
    public function init(){
        $this->startTime = microtime(true);
    }

    /*
     * 增加关注
     */
    public function addFollowAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(7, "非法登录用户");
        }
        $f_uid = $this->getRequest()->getPost('f_uid');//关注用户id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $rs= Common::http(OPEN_DOMAIN.'/followapi/add',array('uid'=>$user['uid'],'f_uid'=>$f_uid),'POST');
        $rs = json_decode($rs,true);
        if($rs['status'] == 2){
            Common::echoAjaxJson(2,'关注用户不存在');
        }if($rs['status'] == 3){
            Common::echoAjaxJson(3,'您不能关注自己');
        }if($rs['status'] == 4){
            Common::echoAjaxJson(4,'登录用户已过期，请重新登录');
        }if($rs['status'] == 5){
            Common::echoAjaxJson(5,'关注用户不存在');
        }if($rs['status'] == 6){
            Common::echoAjaxJson(6,'已经关注');
        }if($rs['status'] == 7){
            Common::echoAjaxJson(7,'关注失败');
        }if($rs['status'] == 8){
            Common::echoAjaxJson(8,'关注已达上限，请取消关注部分用户再关注新的用户');
        }if($rs['status'] == 9){
            Common::echoAjaxJson(9,'关注失败');
        }if($rs['status']==1){
            $followModel = new FollowModel();
            $getRelation = $followModel->getRelation($user['uid'],$f_uid);
            if ($user['uid'] != $f_uid){
                $feedModel = new FeedModel();
                $feedModel->initJpushFollow($f_uid, '您增加了一位新粉丝');
            }
            Common::appLog('follow/addFollow',$this->startTime,$version);
            Common::echoAjaxJson(1,'关注成功',$getRelation);
        }
    }
    /*
     * 取消关注
     */
    public function delFollowAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(7, "非法登录用户");
        }
        $f_uid = $this->getRequest()->getPost('f_uid');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        echo Common::http(OPEN_DOMAIN.'/followapi/del',array('uid'=>$user['uid'],'f_uid'=>$f_uid),'POST');
        Common::appLog('follow/delFollow',$this->startTime,$version);
    }

    /*
     * @name 移除粉丝
     */
    public function removeAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(7, "非法登录用户");
        }
        $uid = $user['uid'];
        $f_uid = $this->getRequest()->getPost('f_uid');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$f_uid){
            Common::echoAjaxJson(2,'移除的粉丝不存在');
        }
        if($uid == $f_uid){
            Common::echoAjaxJson(3,'不能移除自己');
        }
        $followModel = new FollowModel();
        $rs = $followModel->del($f_uid,$uid);
        if($rs == -1){
            Common::echoAjaxJson(4,'不能移除未关注的用户');
        }
        if($rs == -2){
            Common::echoAjaxJson(5,'移除粉丝失败');
        }
        if($rs == 0){
            Common::echoAjaxJson(6,'移除粉丝失败');
        }
        Common::appLog('follow/remove',$this->startTime,$version);
        Common::echoAjaxJson(1,'移除粉丝成功');
    }


    public function getGroupListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $followModel = new FollowModel();
        $groupList = $followModel->getGroupList($user['uid']);
        Common::appLog('follow/getGroupList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$groupList);
    }

    public function getGroupUserAction() {
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $groupId = $this->getRequest()->getPost('group_id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$groupId){
            Common::echoAjaxJson(2,'参数缺失');
        }
        $followModel = new FollowModel();
        $userList = $followModel->getGroupUserById($user['uid'],$groupId,0,5000);
        $fansList = array();
        if($userList){
            $userModel = new UserModel();
            foreach($userList as $val){
                $v_uid = $val['f_uid'];
                $fansList[$v_uid] = $userModel->getUserData($v_uid);
            }
        }
        Common::appLog('follow/getGroupUser',$this->startTime,$version);
        Common::echoAjaxJson(1,'',$fansList);
    }

    /*
     * @name 增加分组
     */
    public function addGroupAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(6, "非法登录用户");
        }
        $uid = $user['uid'];
        $name = $this->getRequest()->getPost('name');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$name){
            Common::echoAjaxJson(2,'请输入分组名');
        }
        $security = new Security();
        $name = $security->xss_clean($name);
        $name = strip_tags($name,"");
        $followModel = new FollowModel();
        $rs = $followModel->addGroup($uid,$name);
        if($rs == -1){
            Common::echoAjaxJson(3,'分组数量已经超过限制，请先删除后再添加');
        }
        if($rs == -2){
            Common::echoAjaxJson(4,'分组名已存在');
        }
        if($rs == 0){
            Common::echoAjaxJson(5,'增加分组失败');
        }
        Common::appLog('follow/addGroup',$this->startTime,$version);
        Common::echoAjaxJson(1,'增加分组成功',$rs);
    }
    /*
     * @name 修改分组
     */
    public function modifyGroupAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(6, "非法登录用户");
        }
        $uid = $user['uid'];
        $name = $this->getRequest()->getPost('name');
        $id = $this->getRequest()->getPost('id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$name){
            Common::echoAjaxJson(2,'请输入分组名');
        }
        if(!$id){
            Common::echoAjaxJson(3,'没有指定要修改的分组');
        }
        $security = new Security();
        $name = $security->xss_clean($name);
        $name = strip_tags($name,"");
        $followModel = new FollowModel();
        $rs = $followModel->modifyGroup($uid,$name,$id);
        if($rs == -1){
            Common::echoAjaxJson(4,'没有找到分组');
        }
        if($rs == -2){
            Common::echoAjaxJson(5,'分组名已存在');
        }
        Common::appLog('follow/modifyGroup',$this->startTime,$version);
        Common::echoAjaxJson(1,'修改分组成功');
    }
    /*
     * @name 删除分组
     */
    public function delGroupAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $uid = $user['uid'];
        $id = $this->getRequest()->getPost('id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2,'没有指定要删除的分组');
        }
        $followModel = new FollowModel();
        $rs = $followModel->delGroup($uid,$id);
        if($rs == -1){
            Common::echoAjaxJson(3,'没有找到分组');
        }
        if($rs == -2){
            Common::echoAjaxJson(4,'默认分组不能删除');
        }
        Common::appLog('follow/delGroup',$this->startTime,$version);
        Common::echoAjaxJson(1,'删除分组成功');
    }
    /**
     * 修改关注的人的分组
     */
    public function modifyGroupFollowAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $uid = $user['uid'];
        $f_uid = $this->getRequest()->getPost('f_uid');
        $group_id = $this->getRequest()->getPost('group_id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$f_uid){
            Common::echoAjaxJson(2,'没有传入关注的用户id');
        }
        if(!$group_id){
            Common::echoAjaxJson(3,'没有指定到分组');
        }
        $followModel = new FollowModel();
        $group = $followModel->getGroupByUid($uid,$f_uid);
        $old_group_id = $group['group_id'];
        $followModel->setFollowGroup($uid,$f_uid,$old_group_id,$group_id);
        Common::appLog('follow/modifyGroupFollow',$this->startTime,$version);
        Common::echoAjaxJson(1,'设置成功');
    }
    public function setRemarkAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $uid = $user['uid'];
        $f_uid = (int)$this->getRequest()->getPost('f_uid');
        $remark = trim($this->getRequest()->getPost('remark'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if($remark){
            if(!preg_match('/^[\x{4e00}-\x{9fa5}0-9]{2,8}$/u',$remark)){
                Common::echoAjaxJson(2,'请输入2-8个字的中文字符或数字');
            }
            if(Common::badWord($remark)){
                Common::echoAjaxJson(3,'备注含有敏感词');
            }
        }
        $followModel = new FollowModel();
        $followModel->setRemark($uid,$f_uid,$remark);
        Common::appLog('follow/setRemark',$this->startTime,$version);
        Common::echoAjaxJson(1,"设置成功");
    }
    //根据分组id和uid获取用户列表
    public function getGroupUserByIdAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $uid = $user['uid'];
        $group_id = intval($this->getRequest()->getPost('group_id'));//分组id
        $page = intval($this->getRequest()->getPost('page'));//页数
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==20) ? $this->getRequest()->getPost('size') : 20;//条数
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$group_id){
            Common::echoAjaxJson(2, "分组id为空");
        }
        $page = $page ? $page : 1;
        $followModel = new FollowModel();
        $list = $followModel->getGroupUserById($uid,$group_id,$page,(int)$size,1);
        if($list){
            foreach($list as $k =>$val){
                $userModel = new UserModel();
                $userInfo = $userModel->getUserData($val['f_uid'],$uid);
                $list[$k]['uid'] = $userInfo['uid'];
                $list[$k]['type'] = $userInfo['type'];
                $list[$k]['did'] = $userInfo['did'];
                $list[$k]['nick_name'] = $userInfo['nick_name'];
                $list[$k]['avatar'] = Common::show_img($userInfo['avatar'],1,160,160);
                $list[$k]['sex'] = $userInfo['sex'];
                $list[$k]['intro'] = $userInfo['intro'];
                $list[$k]['att_num'] = $userInfo['att_num'];
                $list[$k]['fans_num'] = $userInfo['fans_num'];
                $list[$k]['relation'] = $userInfo['relation'];
                $list[$k]['self'] = $userInfo['self'];
            }
        }
        Common::appLog('follow/getGroupUserById',$this->startTime,$version);
        Common::echoAjaxJson(1,"获取成功",$list);
    }

    //多用户关注
    public function addFollowAllAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(7, "非法登录用户");
        }
        $uid_json = $this->getRequest()->getPost('uid_json');//关注用户ids
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $followModel = new FollowModel();
        $uid_array = json_decode($uid_json,true);
        $followModel->addAll($user['uid'],$uid_array);
        Common::appLog('follow/addFollowAll',$this->startTime,$version);
        Common::echoAjaxJson(1,'关注成功');
    }

}