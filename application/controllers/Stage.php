<?php

class StageController extends Yaf_Controller_Abstract
{
    public function init()
    {
        $this->startTime = microtime(true);
    }
    /***********驿站源生***************/

    //驿站信息
    public function stageManageAction()
    {
        $uid = 0;
        $data['token'] = $this->getRequest()->getPost('token');
        $sid = $this->getRequest()->getPost('sid');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if ($data['token']) {
            $user = Common::isLogin($data);
            if (!$user) {
                Common::echoAjaxJson(5, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        if (!$sid) {
            Common::echoAjaxJson(2, '驿站id为空');
        }
        $stageModel = new StageModel();
        $stageByRedisData = $stageModel->getStageRedisById($sid);
        if (!$stageByRedisData) {
            Common::echoAjaxJson(3, '驿站不存在');
        }
        if (!$stageByRedisData['status'] > 1) {
            Common::echoAjaxJson(4, '抱歉！该驿站已被关闭，由于该驿站违反了社区的相关规定');
        }
        $info['sid'] = $stageByRedisData['sid'];
        $info['name'] = $stageByRedisData['name'];
        $info['intro'] = $stageByRedisData['intro'];
        $info['icon'] = $stageByRedisData['icon'];
        $info['user_num'] = $stageByRedisData['user_num'];
        $info['add_time'] = $stageByRedisData['add_time'];
        $info['type'] = $stageByRedisData['type'];
        $info['authority'] = $stageByRedisData['authority'];
        $cate_info = $stageModel->getCultureCateById($stageByRedisData['cate_id']);
        $info['cate_id'] = $cate_info['id'];
        $info['cate_name'] = $cate_info['name'];
        $info['mobile'] = $stageByRedisData['mobile'];
        $info['icon'] = $stageByRedisData['icon'] ? $stageByRedisData['icon'] : '';
        $info['stage_address'] = $stageByRedisData['stage_address'] ? $stageByRedisData['stage_address'] : '';
        $info['lat'] = $stageByRedisData['lat'] ? $stageByRedisData['lat'] : '';
        $info['lng'] = $stageByRedisData['lng'] ? $stageByRedisData['lng'] : '';
        $tagModel = new TagModel();
        $tagList = $tagModel->getRelation(2, $sid);
        if ($tagList) {
            foreach ($tagList as $k => $v) {
                $tagList[$k]['id'] = $v['tag_id'];
            }
        }
        $info['tag'] = $tagList;
        $info['status'] = $stageByRedisData['status'];
        $info['is_join'] = $stageModel->getJoinStage($sid, $uid);
        $info['user']['uid'] = $stageByRedisData['user']['uid'];
        $info['user']['did'] = $stageByRedisData['user']['did'];
        $info['user']['nick_name'] = $stageByRedisData['user']['nick_name'];
        $info['user']['type'] = $stageByRedisData['user']['type'];
        $info['user']['sex'] = $stageByRedisData['user']['sex'];
        $info['user']['avatar'] = Common::show_img($stageByRedisData['user']['avatar'], 1, 160, 160);
        if ($stageByRedisData['type'] == 2) {
            $coverList = $stageModel->getBusinessCover($sid);
            $busniessInfo = $stageModel->getBusiness($sid);
            $info['shop_hours'] = $busniessInfo['shop_hours'];
            $info['tel'] = $busniessInfo['tel'];
            // $info['province']['name'] = $stageByRedisData['province']['name'];
            // $info['province']['id'] = $stageByRedisData['province'];
            // $info['city']['name'] = $stageByRedisData['city']['name'];
            // $info['city']['id'] = $stageByRedisData['city'];
            // $info['town']['name'] = $stageByRedisData['town']['name'];
            // $info['town']['id'] = $stageByRedisData['town'];
            if ($coverList) {
                foreach ($coverList as $k => $v) {
                    $info['cover'][$k] = IMG_DOMAIN . $v['path'];
                    $info['show_cover'][$k] = Common::show_img($v['path'], 1, 540, 540);
                    $info['show_img'][$k] = $v['path'];
                }
            } else {
                $info['cover'] = array();
                $info['show_cover'] = array();
                $info['show_img'] = array();
            }
        } else {
            $info['cover'] = array();
            $info['show_cover'] = array();
            $info['show_img'] = array();
        }


        Common::appLog('stage/stageManage', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $info);
    }

    //驿站成员列表
    public function stageMemberAction()
    {
        $data['token'] = $this->getRequest()->getPost('token');
        $sid = $this->getRequest()->getPost('sid');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $uid = 0;
        if ($data['token']) {
            $user = Common::isLogin($data);
            if (!$user) {
                Common::echoAjaxJson(5, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        if (!$sid) {
            Common::echoAjaxJson(2, '驿站id为空');
        }
        $stageModel = new StageModel();
        $userModel = new UserModel();
        $stage = $stageModel->getStageById($sid);
        if (!$stage) {
            Common::echoAjaxJson(3, '驿站不存在');
        }
        if (!$stage['status'] > 1) {
            Common::echoAjaxJson(4, '抱歉！该驿站已被关闭，由于该驿站违反了社区的相关规定');
        }
        $create = $userModel->getUserData($stage['uid'], $uid);
        $list['stage_role'] = $stageModel->joinStageRole($sid, $uid);
        $list['stage_role'] = $list['stage_role'] ? $list['stage_role'] : (object)array();
        $list['create_list']['uid'] = $create['uid'];
        $list['create_list']['did'] = $create['did'];
        $list['create_list']['nick_name'] = $create['nick_name'];
        $list['create_list']['type'] = $create['type'];
        $list['create_list']['avatar'] = Common::show_img($create['avatar'], 160, 160);
        $list['admin_list'] = $stageModel->getAdminListBySid($sid, $uid);//驿站驿管列表
        $list['member_list'] = $stageModel->getMemberListBySid($sid, 1, 20, $uid);//驿站成员

        Common::appLog('stage/stageMember', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $list);
    }

    //驿站成员加载
    public function stageMemberMoreAction()
    {
        $data['token'] = $this->getRequest()->getPost('token');
        $sid = $this->getRequest()->getPost('sid');
        $page = intval($this->getRequest()->getPost('page'));
        $size = intval($this->getRequest()->getPost('size'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $uid = 0;
        if ($data['token']) {
            $user = Common::isLogin($data);
            if (!$user) {
                Common::echoAjaxJson(5, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        if (!$sid) {
            Common::echoAjaxJson(2, '驿站id为空');
        }
        $stageModel = new StageModel();
        $stage = $stageModel->getStageById($sid);
        if (!$stage) {
            Common::echoAjaxJson(3, '驿站不存在');
        }
        if (!$stage['status'] > 1) {
            Common::echoAjaxJson(4, '抱歉！该驿站已被关闭，由于该驿站违反了社区的相关规定');
        }
        $page = $page ? $page : 2;
        $size = $size ? $size : 10;
        $list['member_list'] = $stageModel->getMemberListBySid($sid, $page, $size, $uid);//驿站成员
        Common::appLog('stage/stageMemberMore', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $list);
    }

    //驿站封面上传
    public function setCoverAction()
    {
        $user = Common::isLogin($_POST, 1);
        if (!$user) {
            Common::echoAjaxJson(7, "非法登录用户");
        }
        $uid = $user['uid'];
        $sid = $this->getRequest()->getPost('sid');
        $cover = $this->getRequest()->getPost('cover');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if (!$sid) {
            Common::echoAjaxJson(2, "驿站id为空");
        }
        if (!$cover) {
            Common::echoAjaxJson(3, "图片不能为空");
        }
        $stageModel = new StageModel();
        $stageInfo = $stageModel->getBasicStageBySid($sid);
        if (!$stageInfo && $stageInfo['status'] > 1) {
            Common::echoAjaxJson(4, "抱歉！该驿站已被关闭，由于该驿站违反了社区的相关规定");
        }
        $role = $stageModel->joinStageRole($sid, $uid);
        if (!$role || $role['role'] != 1) {
            Common::echoAjaxJson(5, "您没有权限设置驿站封面");
        }
        $rs = $stageModel->setCover($sid, $cover);
        if (!$rs) {
            Common::echoAjaxJson(6, "设置封面失败");
        }

        Common::appLog('stage/setCover', $this->startTime, $version);
        Common::echoAjaxJson(1, "设置封面成功");
    }

    /*
     * 删除驿站成员(迁移)
     */
    public function delMemberAction()
    {
        $user = Common::isLogin($_POST, 1);
        if (!$user) {
            Common::echoAjaxJson(10, "非法登录用户");
        }
        $s_uid = $user['uid'];
        $sid = $this->getRequest()->getPost('sid');
        $uid = $this->getRequest()->getPost("uid");
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if (!$sid) {
            Common::echoAjaxJson(2, '成员所属驿站不能为空');
        }
        if (!$uid) {
            Common::echoAjaxJson(3, '删除的成员不能为空');
        }
        $stageModel = new StageModel();
        $rs = $stageModel->delMember($sid, $uid, $s_uid);
        if ($rs == -1) {
            Common::echoAjaxJson(4, '该驿站已不存在');
        }
        if ($rs == -2) {
            Common::echoAjaxJson(5, '请您先加入该驿站');
        }
        if ($rs == -3) {
            Common::echoAjaxJson(6, "您加入驿站的申请正在审核中");
        }
        if ($rs == -4) {
            Common::echoAjaxJson(7, '您没有权限删除该成员');
        }
        if ($rs == -5) {
            Common::echoAjaxJson(8, '该成员已退出该驿站');
        }
        if ($rs == 0) {
            Common::echoAjaxJson(9, '删除成员失败');
        }
        $stage_info = $stageModel->getBasicStageBySid($sid);
        $roleInfo = $stageModel->joinStageRole($sid, $s_uid);
        $noticeModel = new NoticeModel();
        $roleName = (1 == $roleInfo['role']) ? '驿长' : '驿管';
        $content = '十分抱歉，您被<a class="blue" target="_blank" href="/s/' . $sid . '">' . $stage_info['name'] . '</a>的' . $roleName . '从此驿站中移除，您可以再次加入。';
        $noticeModel->addNotice($uid, $content);
        Common::appLog('stage/delMember', $this->startTime, $version);
        Common::echoAjaxJson(1, '删除成员成功', $stage_info['user_num']);
    }
    /***********驿站源生结束***************/
    /**
     * 获取创建商家驿站分类信息
     */
    public function getCultureCateAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $stageModel = new StageModel();
        $list = $stageModel->getCultureCateList(1);
        Common::appLog('stage/getCultureCate', $this->startTime, $version);
        Common::echoAjaxJson(1, "获取成功", $list);
    }

    //创建驿站条件
    public function getCondition($uid, $sid = '')
    {
        $stageModel = new StageModel();
        $userModel = new UserModel();
        $user = $userModel->getUserByUid($uid);
        $user_info = $userModel->getUserInfoByUid($user['uid']);
        if ($user['reg_type'] == 1) {
            if ($user['status'] == 0) {
                Common::echoAjaxJson(26, '您还未激活注册邮件，暂时无法创建驿站。');
            }
        }
        if (in_array($user['reg_type'], array(1, 3))) {
            if (!$user_info['bind_name']) {
                Common::echoAjaxJson(40, '您还未绑定手机号');
            }
        }
        $create_num = $stageModel->getCreateStageNum($uid);
        $create_check_num = $stageModel->getCreateStageCheckNum($uid);
        $check_error_num = $stageModel->getCreateStageCheckErrorNum($uid, $sid);
        if ($create_check_num) {
            Common::echoAjaxJson(20, '审核中不能创建新驿站');
        }
        if ($check_error_num) {
            Common::echoAjaxJson(21, '您有审核未通过的驿站，不能创建新驿站');
        }
        if ($create_num > 0) {
            Common::echoAjaxJson(22, "您当前已经创建了驿站，不可再继续创建。");
        }
        /* $exp_info = Common::getUserLevel($user_info['exp']);
         if($exp_info['level_id']<=3){//普通用户1、2、3等级创建1个驿站
             if($create_num>0){
                 Common::echoAjaxJson(22, "根据目前等级只能创建1个驿站");
             }
         }elseif($exp_info['level_id']>3 && $exp_info['level_id']<=6){//普通用户4、5、6等级创建2个驿站
             if($create_num>=2){
                 Common::echoAjaxJson(23, "根据目前等级只能创建2个驿站");
             }
         }elseif($exp_info['level_id']>6){//普通用户6、7、8等级创建3个驿站
             if($create_num>=3){
                 Common::echoAjaxJson(24, "根据目前等级只能创建3个驿站");
             }
         }*/
    }

    //创建驿站条件
    public function getConditionAction()
    {
        $user = Common::isLogin($_POST, 1);
        if (!$user) {
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $uid = $user['uid'];
        $stageModel = new StageModel();
        $userModel = new UserModel();
        $user = $userModel->getUserByUid($uid);
        $user_info = $userModel->getUserInfoByUid($uid);
        if ($user['reg_type'] == 1) {
            if ($user['status'] == 0) {
                Common::echoAjaxJson(26, '您还未激活注册邮件，暂时无法创建驿站。');
            }
        }
        if (in_array($user['reg_type'], array(1, 3))) {
            if (!$user_info['bind_name']) {
                Common::echoAjaxJson(40, '您还未绑定手机号');
            }
        }
        $create_num = $stageModel->getCreateStageNum($uid);
        $create_check_num = $stageModel->getCreateStageCheckNum($uid);
        $check_error_num = $stageModel->getCreateStageCheckErrorNum($uid, '');

        if ($create_check_num) {
            Common::echoAjaxJson(20, '审核中不能创建新驿站');
        }
        if ($check_error_num) {
            Common::echoAjaxJson(21, '您有审核未通过的驿站，不能创建新驿站');
        }


        if ($create_num > 0) {
            Common::echoAjaxJson(22, "您当前已经创建了驿站，不可再继续创建。");
        }
        /*$exp_info = Common::getUserLevel($user_info['exp']);
        if($exp_info['level_id']<=3){//普通用户1、2、3等级创建1个驿站
            if($create_num>0){
                Common::echoAjaxJson(22, "根据目前等级只能创建1个驿站");
            }
        }elseif($exp_info['level_id']>3 && $exp_info['level_id']<=6){//普通用户4、5、6等级创建2个驿站
            if($create_num>=2){
                Common::echoAjaxJson(23, "根据目前等级只能创建2个驿站");
            }
        }elseif($exp_info['level_id']>6){//普通用户6、7、8等级创建3个驿站
            if($create_num>=3){
                Common::echoAjaxJson(24, "根据目前等级只能创建3个驿站");
            }
        }*/
        Common::appLog('stage/getCondition', $this->startTime, $version);
        Common::echoAjaxJson(1, "可以创建");
    }

    //用户删除商家驿站图片
    public function updateBusinessCoverAction()
    {
        $user = Common::isLogin($_POST, 1);
        if (!$user) {
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $stageModel = new StageModel();
        $sid = $stageModel->getSidByUid($user['uid']);
        $num = $stageModel->verifyBusiness($sid['sid'], $user['uid']);
        if ($num == 0) {
            Common::echoAjaxJson(2, '你没有管理该驿站的权限');
        }
        $cover = $this->getRequest()->getPost('cover');//驿站图片
        if (!$cover) {
            Common::echoAjaxJson(3, '图片未选择');
        }
        $rs = $stageModel->updateBusinessCover($sid['sid'], $user['uid'], $cover);
        if (!$rs) {
            Common::echoAjaxJson(4, '修改失败');
        }
        Common::appLog('stage/updateBusinessCover', $this->startTime, $version);
        Common::echoAjaxJson(1, '修改成功');
    }

    /*
     * 用户加入驿站
     */
    public function joinAction()
    {
        $user = Common::isLogin($_POST, 1);
        if (!$user) {
            Common::echoAjaxJson(9, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $sid = $this->getRequest()->getPost("sid");
        $msgId = $this->getRequest()->getPost("msgId");
        echo Common::http(OPEN_DOMAIN . '/stageapi/joinStage', array('uid' => $user['uid'], 'sid' => $sid, $msgId), 'POST');
        Common::appLog('stage/join', $this->startTime, $version);
    }

    public function refuseJoinAction()
    {
        $user = Common::isLogin($_POST, 1);
        if (!$user) {
            Common::echoAjaxJson(9, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $msgId = $this->getRequest()->getPost("msgId");
        echo Common::http(OPEN_DOMAIN . '/stageapi/refuseJoinStage', array('uid' => $user['uid'], $msgId), 'POST');
        Common::appLog('stage/refuseJoin', $this->startTime, $version);
    }

    /*
     * 用户退出驿站
     */
    public function exitAction()
    {
        $user = Common::isLogin($_POST, 1);
        if (!$user) {
            Common::echoAjaxJson(9, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $sid = $this->getRequest()->getPost("sid");
        echo Common::http(OPEN_DOMAIN . '/stageapi/exitStage', array('uid' => $user['uid'], 'sid' => $sid), 'POST');
        Common::appLog('stage/exit', $this->startTime, $version);
    }

    /*
     * 用户退出审核中驿站
     */
    public function exitCheckAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(9, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $sid = $this->getRequest()->getPost("sid");
        echo Common::http(OPEN_DOMAIN . '/stageapi/exitCheckStage', array('uid' => $user['uid'], 'sid' => $sid), 'POST');
        Common::appLog('stage/exitCheck', $this->startTime, $version);
    }

    //设置驿站成员角色
    public function setBusinessMemberRoleAction()
    {
        $user = Common::isLogin($_POST, 1);
        if (!$user) {
            Common::echoAjaxJson(11, "非法登录用户");
        }
        $sid = $this->getRequest()->getPost('sid');//驿站id
        $uid = $this->getRequest()->getPost('uid');//设置用户的id
        $role = $this->getRequest()->getPost('role');//角色role 2.驿管 3.成员
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $num = 3;//该驿站下驿管上限
        $m_num = 100;//管理的驿站上限
        if (!$sid) {
            Common::echoAjaxJson(2, "所属驿站不能为空");
        }
        if (!$uid) {
            Common::echoAjaxJson(3, "请选择要设置的用户");
        }
        if (!$role || !in_array($role, array(2, 3))) {
            Common::echoAjaxJson(4, "用户角色不正确");
        }
        $stageModel = new StageModel();
        $rs = $stageModel->setMemberRole($sid, $uid, $role, $num, $user['uid'], $m_num);
        if ($rs == -1) {
            Common::echoAjaxJson(5, "该驿站已不存在");
        }
        if ($rs == -2) {
            Common::echoAjaxJson(6, "您没有权限设置该成员");
        }
        if ($rs == -3) {
            Common::echoAjaxJson(7, "该用户加入驿站数已达200上限");
        }
        if ($rs == -4) {
            Common::echoAjaxJson(8, "驿管最多只能设置" . $num . "个");
        }
        if ($rs == -5) {
            Common::echoAjaxJson(9, "每个用户管理的驿站不能超过" . $m_num);
        }
        $message = "设为驿管";
        if ($role == 2) {
            $message = "设为驿管";
        } else if ($role == 3) {
            $message = "取消驿管";
        }
        if ($rs == 0) {
            Common::echoAjaxJson(10, $message . "失败");
        }
        $stage_info = $stageModel->getBasicStageBySid($sid);
        $noticeModel = new NoticeModel();
        if ($role == 2) {
            $content = '您被<a class="blue" target="_blank" href="/s/' . $sid . '">' . $stage_info['name'] . '</a>的驿长设置成为本站的驿管，可以针对此驿站进行成员和内容的管理。若您想取消驿管功能，请进入驿站管理中心的成员管理进行取消。<a href="/stagemanager/index?sid=' . $sid . '">进入管理中心>></a>';
        } else if ($role == 3) {
            $content = '十分抱歉，您被<a class="blue" target="_blank" href="/s/' . $sid . '">' . $stage_info['name'] . '</a>的驿长取消了驿管身份。若有任何疑问，请联系<a class="blue" target="_blank" href="/s/' . $sid . '">' . $stage_info['name'] . '</a>的驿长。';
        }
        $noticeModel->addNotice($uid, $content);
        Common::appLog('stage/setBusinessMemberRole', $this->startTime, $version);
        Common::echoAjaxJson(1, $message . "成功", $stage_info['user_num']);

    }

    //获取用户的驿站列表
    public function getStageListAction()
    {
        $token = $this->getRequest()->get('token');
        $user['uid'] = 0;
        if ($token) {
            $user = Common::isLogin($_POST);
            if (!$user) {
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $s_uid = $this->getRequest()->getPost('s_uid');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if ($s_uid) {
            $uid = $s_uid;
            $flag = 0;
        } else {
            $uid = $user['uid'];
            $flag = 1;
        }
        $list = array();
        $stageModel = new StageModel();
        $visitModel = new VisitModel();
        $list['creatList'] = $stageModel->getStageList($uid, 1, $flag);
        foreach ($list['creatList'] as $k => $v) {
            $list['creatList'][$k]['icon'] = Common::show_img($v['icon'], 4, 200, 200);
            $t_num = $stageModel->getRedisNewTopicNum($v['sid'], $uid);
            $list['creatList'][$k]['new_num'] = $t_num ? $t_num : 0;
            $list['creatList'][$k]['new_topic'] = $stageModel->getNewBySid($v['sid']);
            $isUpgrade = $stageModel->isUpgradeNew($v['sid']);
            if ($isUpgrade > 0) {
                $list['creatList'][$k]['is_new'] = 1;
            } else {
                $list['creatList'][$k]['is_new'] = 0;
            }
            $list['creatList'][$k]['is_join'] = $stageModel->getJoinStage($v['sid'], $uid);
            $list['creatList'][$k]['viewNum'] = $visitModel->getStagePV($v['sid']);
        }
        $list['manageList'] = $stageModel->getStageList($uid, 2, $flag);
        foreach ($list['manageList'] as $k => $v) {
            $list['manageList'][$k]['icon'] = Common::show_img($v['icon'], 4, 200, 200);
            $t_num = $stageModel->getRedisNewTopicNum($v['sid'], $uid);
            $list['manageList'][$k]['new_num'] = $t_num ? $t_num : 0;
            $list['manageList'][$k]['is_join'] = $stageModel->getJoinStage($v['sid'], $uid);
            $list['manageList'][$k]['viewNum'] = $visitModel->getStagePV($v['sid']);
            $list['manageList'][$k]['new_topic'] = $stageModel->getNewBySid($v['sid']);
        }
        $list['joinList'] = $stageModel->getStageList($uid, 3, $flag);
        foreach ($list['joinList'] as $k => $v) {
            $list['joinList'][$k]['icon'] = Common::show_img($v['icon'], 4, 200, 200);
            $t_num = $stageModel->getRedisNewTopicNum($v['sid'], $uid);
            $list['joinList'][$k]['new_num'] = $t_num ? $t_num : 0;
            $list['joinList'][$k]['new_topic'] = $stageModel->getNewBySid($v['sid']);
            $list['joinList'][$k]['is_join'] = $stageModel->getJoinStage($v['sid'], $uid);
            $list['joinList'][$k]['viewNum'] = $visitModel->getStagePV($v['sid']);
        }
        if ($version >= '3.7') {
            $userModel = new UserModel();
            $followModel = new FollowModel();
            $userInfo = $userModel->getUserData($uid, $user['uid']);
            $user_info['uid'] = $userInfo['uid'];
            $user_info['did'] = $userInfo['did'];
            $user_info['nick_name'] = $userInfo['nick_name'];
            $user_info['avatar'] = $userInfo['avatar'];
            $user_info['self'] = $userInfo['self'];
            $user_info['ico_type'] = $userInfo['ico_type'];
            $user_info['relation'] = $userInfo['relation'];
            $user_info['type'] = $userInfo['type'];
            $user_info['sex'] = $userInfo['sex'];
            $user_info['sex'] = $userInfo['sex'];
            $user_info['fans_num'] = $userInfo['fans_num'];
            $user_info['att_num'] = $userInfo['att_num'];
            $u_info = $userModel->getUserByUid($uid);
            $user_info['qrcode_img'] = $u_info['qrcode_img'];
            $user_info['intro'] = $userInfo['intro'];
            if ($userInfo['type'] > 1) {
                $indexModel = new IndexModel();
                $info = $indexModel->getAngelInfoByUid($userInfo['uid']);
                $user_info['intro'] = $info['info'];
            }
            $home_cover = $userModel->getUserInfoByUid($userInfo['uid']);
            $user_info['avatar'] = Common::show_img($userInfo['avatar'], 1, 200, 200);
            $user_info['original_avatar'] = $userInfo['avatar'];
            if ($user['uid'] == $userInfo['uid']) {
                $user_info['home_cover'] = $home_cover['home_cover'] ? IMG_DOMAIN . $home_cover['home_cover'] : PUBLIC_DOMAIN . 'default_app_cover.jpg';
            } else {
                $user_info['home_cover'] = $home_cover['home_cover'] ? IMG_DOMAIN . $home_cover['home_cover'] : PUBLIC_DOMAIN . 'default_app_home.jpg';
                $g_id = $followModel->getGroupByUid($user['uid'], $userInfo['uid']);
                $user_info['in_group_id'] = $g_id['group_id'];
            }
            $list['user_info'] = $user_info;
        }
        Common::appLog('stage/getStageList', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $list ? $list : (object)array());
    }

    //根据分类id查询驿站列表
    public function getStageListByCateIdAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $cate_id = $this->getRequest()->getPost('cate_id');
        $page = $this->getRequest()->getPost('page');
        $size = intval($this->getRequest()->getPost('size'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $page = $page ? $page : 1;
        $size = $size ? $size : 10;
        $stageModel = new StageModel();
        $num = $stageModel->getStageNumByCateId($cate_id);
        $pageNum = ceil($num / $size);
        if ($page > $pageNum && $page > 1) {
            Common::echoAjaxJson(2, "全部加载完毕");
        }
        $list = $stageModel->getStageByCateId($cate_id, $page, $size, $user['uid']);
        foreach ($list as $k => $v) {
            $list[$k]['icon'] = Common::show_img($v['icon'], 4, 100, 100);
            $list[$k]['is_join'] = $stageModel->getJoinStage($v['sid'], $user['uid']);
        }
        Common::appLog('stage/getStageListByCateId', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $list ? $list : array());
    }

    //获取驿站标签下的商家驿站
    public function getBusinessByTagIdAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $page = intval($this->getRequest()->getPost('page'));//页码
        $size = intval($this->getRequest()->getPost('size'));//条数
        $tag_id = intval($this->getRequest()->getPost('tag_id'));//标签id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if (!$tag_id) {
            Common::echoAjaxJson(2, "您没有选择标签");
        }
        $page = $page ? $page : 1;
        $size = $size ? $size : 10;
        $stageModel = new StageModel();
        $total = $stageModel->getBusinessNumByTagId($tag_id);
        $pageNum = ceil($total / $size);
        if ($page > $pageNum && $page > 1) {
            Common::echoAjaxJson(3, "全部加载完毕");
        }
        $start = ($page - 1) * $size;
        $sids = $stageModel->getBusinessByTagId($tag_id, $start, $size);
        $list = array();
        foreach ($sids as $v) {
            $list[] = $stageModel->getBasicStageBySid($v['sid']);
        }
        if ($list) {
            foreach ($list as $k => $v) {
                $list[$k]['icon'] = Common::show_img($v['icon'], 4, 100, 100);
                $list[$k]['is_join'] = $stageModel->getJoinStage($v['sid'], $user['uid']);
            }
        }
        Common::appLog('stage/getBusinessByTagId', $this->startTime, $version);
        Common::echoAjaxJson(1, "获取成功", $list ? $list : array());
    }

    //获取喜欢记录列表界面
    public function loginMemberAction()
    {
        $data['token'] = $this->getRequest()->get('token');
        $uid = 0;
        if ($data['token']) {
            $user = Common::isLogin($data);
            if (!$user) {
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
            $this->getView()->token = $data['token'];
            $this->getView()->user = $user;
        }
        $sid = $this->getRequest()->get('sid');
        $stageModel = new StageModel();
        $user_list = $stageModel->getListByLoginTime($sid, 0, 13);
        $followModel = new FollowModel();//是否已关注
        foreach ($user_list as $k => $v) {
            $user_list[$k]['relation'] = $followModel->getRelation($uid, $v['uid']);
        }
        $this->getView()->user_list = $user_list;
        $this->getView()->sid = $sid;
        $this->getView()->token = $data['token'];
        $this->getView()->memberNum = $stageModel->getNumByLoginTime($sid);
        $this->display("loginMember");
    }

    public function loginMemberMoreAction()
    {
        $page = intval($this->getRequest()->get('page'));
        $size = intval($this->getRequest()->get('size'));
        $sid = intval($this->getRequest()->get('sid'));
        $data['token'] = $this->getRequest()->get('token');
        $uid = '';
        if ($data['token']) {
            $user = Common::isLogin($data);
            if (!$user) {
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
            $this->getView()->user = $user;
        }
        $stageModel = new StageModel();
        $user_list = $stageModel->getListByLoginTime($sid, ($page - 1) * $size, $size);
        $followModel = new FollowModel();//是否已关注
        foreach ($user_list as $k => $v) {
            $user_list[$k]['relation'] = $followModel->getRelation($uid, $v['uid']);
        }
        $this->getView()->user_list = $user_list;
        $this->getView()->memberNum = $stageModel->getNumByLoginTime($sid);
        Common::echoAjaxJson(1, '获取成功', $this->render('loginMemberList'));
    }

    //验票--报名凭证
    public function updateTicketsAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(6, "非法登录用户");
        }
        $uid = $user['uid'];
        $url = $this->getRequest()->getPost('url');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $strArr = explode('=', $url);
        $ids = array();
        foreach ($strArr as $v) {
            if (preg_match('/\d+/', $v, $arr)) {
                $ids[] = $arr[0];
            }
        }
        if (!$ids) {
            Common::echoAjaxJson(7, '抱歉，这个不是验票凭证');
        }
        $id = $ids[0];//订单主键id
        $sid = $ids[1];//驿站id
        $eid = $ids[2];//服务信息id
        $f_id = $ids[3];//场次id
        $type = $ids[4];//1报名 2支付
        $stageModel = new StageModel();
        $eventModel = new EventModel();
        $num = $stageModel->verifyBusiness($sid, $uid);
        if (!$num) {
            Common::echoAjaxJson(2, '你没有验票的权限');
        }
        if ($type != 1) {
            Common::echoAjaxJson(10, '请至【我的订单】-【我卖出的】-【我发布的收费活动】进行验票');
        }
        if (!$id || !$sid || !$eid || !$f_id || !$type) {
            Common::echoAjaxJson(7, '抱歉，这个不是验票凭证');
        }
        $eventInfo = $eventModel->getEvent($eid);
        $fields_info = $eventModel->getFieldsInfo($f_id);
        if ($eventInfo['status'] > 1 || $fields_info['partake_end_time'] >= $fields_info['end_time'] || date('Y-m-d H:i', time()) >= $fields_info['end_time']) {
            Common::echoAjaxJson(3, '抱歉，该凭证已失效');
        }
        $pInfo = $eventModel->getPartakeOptionById($id);
        if ($pInfo['status'] > 1) {
            Common::echoAjaxJson(3, '抱歉，该凭证已失效');
        }
        if ($pInfo['is_check'] == 1) {
            Common::echoAjaxJson(9, '此票已使用');
        }
        $rs = $eventModel->updateTickets($id);
        if (!$rs) {
            Common::echoAjaxJson(4, '验票失败');
        }
        Common::appLog('stage/updateTickets', $this->startTime, $version);
        Common::echoAjaxJson(1, '验票成功');
    }

    //验票--支付凭证
    public function updateOrderTicketsAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(6, "非法登录用户");
        }
        $uid = $user['uid'];
        $url = $this->getRequest()->getPost('url');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $strArr = explode('=', $url);
        $ids = array();
        foreach ($strArr as $v) {
            if (preg_match('/\d+/', $v, $arr)) {
                $ids[] = $arr[0];
            }
        }
        if (!$ids) {
            Common::echoAjaxJson(7, '抱歉，这个不是验票凭证', (object)array());
        }
        $id = $ids[0];//订单主键id
        $sid = $ids[1];//驿站id
        $eid = $ids[2];//服务信息id
        $f_id = $ids[3];//场次id
        $type = $ids[4];//1报名 2支付
        if (!$id || !$sid || !$eid || !$f_id || !$type) {
            Common::echoAjaxJson(7, '抱歉，这个不是验票凭证', (object)array());
        }
        $stageModel = new StageModel();
        $eventModel = new EventModel();
        $num = $stageModel->verifyBusiness($sid, $uid);
        if (!$num) {
            Common::echoAjaxJson(2, '你没有验票的权限', (object)array());
        }
        if ($type != 2) {
            Common::echoAjaxJson(10, '请至【我的订单】-【我卖出的】-【我发布的免费活动】进行验票', (object)array());
        }
        $qrcodeInfo = $eventModel->getQrcodeById($id);
        $orderInfo = $eventModel->orderInfoById($qrcodeInfo['o_id']);
        if (!$orderInfo || $orderInfo['order_status'] != 2) {
            Common::echoAjaxJson(3, '抱歉，该订单已不存在', array());
        }
        if ($qrcodeInfo['is_check'] == 1) {
            Common::echoAjaxJson(9, '此票已使用', (object)array());
        }
        $rs = $eventModel->updateOrderTickets($id);
        if (!$rs) {
            Common::echoAjaxJson(4, '验票失败', (object)array());
        }
        $eventInfo = $eventModel->getEvent($orderInfo['eid']);
        $fields_info = $eventModel->getFieldsInfo($f_id);
        $eventModel->setUnUse($qrcodeInfo['o_id']);
        $data = array(
            'num' => $orderInfo['num'],
            'title' => $eventInfo['title'],
            'start_time' => date('Y.m.d', strtotime($fields_info['start_time'])),
            'end_time' => date('Y.m.d', strtotime($fields_info['end_time']))
        );
        Common::appLog('stage/updateOrderTickets', $this->startTime, $version);
        Common::echoAjaxJson(1, '验票成功', $data);
    }

    //创建驿站
    public function addStageAction()
    {
        $user = Common::isLogin($_POST, 1);
        if (!$user) {
            Common::echoAjaxJson(15, "非法登录用户");
        }
        $stageModel = new StageModel();
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $origin = $this->getRequest()->getPost("origin");//来源 1.PCweb 2.移动web 3.IOS 4.Android
        $cate_id = $this->getRequest()->getPost("cate_id");
        $name = $this->getRequest()->getPost("name");
        $intro = $this->getRequest()->getPost("intro");
        $tag_arr = $this->getRequest()->getPost('tag_arr');//标签id
        $mobile = $this->getRequest()->getPost("mobile");
        $agree = $this->getRequest()->getPost('agree');
        $permission = $this->getRequest()->getPost('permission');
        $town = $this->getRequest()->getPost("town_id");
        $lng = $this->getRequest()->getPost("lng");
        $lat = $this->getRequest()->getPost("lat");
        $address = $this->getRequest()->getPost("address");
        $type = $this->getRequest()->getPost("type") ? $this->getRequest()->getPost("type") : 1;//1.文化 2服务
        $this->getCondition($uid);//创建驿站条件
        $is_pay = 0;
        if (!$town) {
            Common::echoAjaxJson(27, "三级城市id为空");
        }
        if (!$lng || !$lat) {
            Common::echoAjaxJson(28, "经纬度为空");
        }
        if (!$address) {
            Common::echoAjaxJson(29, "驿站地址为空");
        }
        $addressModel = new AddressModel();
        $area_info = $addressModel->cityParent($town);
        $city = $area_info['id'];
        $province = $area_info['pid'];
        $stage_num = $stageModel->getCreateBussinessNum($uid);//用户是否创建过服务驿站
        if ($stage_num) {
            Common::echoAjaxJson(30, '您已创建过服务驿站');
        }
        $name = trim($name);
        if (!$name) {
            Common::echoAjaxJson(2, "请输入驿站名称");
        }
        if (preg_match('/[A-Za-z]{1,}/', $name)) {
            Common::echoAjaxJson(3, '驿站名称不能包含英文字符');
        }
        $name_len = mb_strlen($name, 'utf-8');
        if ($name_len < 2 || $name_len > 10) {
            Common::echoAjaxJson(4, '请输入2-10个中文字作为驿站名称');
        }
        if (Common::badWord($name)) {
            Common::echoAjaxJson(5, '驿站名称含有敏感词');
        }
        $name_rs = $stageModel->stageNameIsExist($name);
        if ($name_rs > 0) {
            Common::echoAjaxJson(6, '驿站名称已经存在');
        }
        if (!$cate_id) {
            Common::echoAjaxJson(7, "请选择驿站分类");
        }
        if (!$mobile) {
            Common::echoAjaxJson(8, '请填写手机号');
        }
        if (!preg_match('/^1[0-9]{10}$/', $mobile)) {
            Common::echoAjaxJson(9, '请输入正确手机号格式');
        }
        if ($intro == '') {
            Common::echoAjaxJson(10, "驿站介绍不能为空");
        }
        if (!$tag_arr) {
            Common::echoAjaxJson(16, '请选择驿站标签');
        }
        $tag = explode('&', $tag_arr);
        if (count($tag) > 6) {
            Common::echoAjaxJson(17, '驿站标签不能超过6个');
        }
        if (!$origin) {
            Common::echoAjaxJson(18, '请标明来源');
        }
        $intro_len = mb_strlen($intro, 'utf-8');
        if ($intro_len < 10 || $intro_len > 3000) {
            Common::echoAjaxJson(11, '请输入10-3000个字符作为驿站介绍');
        }
        //驿站介绍内容处理
        $security = new Security();
        $intro = $security->xss_clean($intro);
        if (!$permission) {
            Common::echoAjaxJson(12, '未设置成员加入权限');
        }
        if (!$agree) {
            Common::echoAjaxJson(13, '请阅读并同意创建驿站相关文件');
        }
        if ($type == 2) {
            $contacts = $this->getRequest()->getPost("contacts");
            $service_type = $this->getRequest()->getPost("service_type");//1.企业 2.个人
            $license_img = $this->getRequest()->getPost("license_img");//营业执照图片
            $identity_img = $this->getRequest()->getPost("identity_img");//身份证照片
            $business_scope = $this->getRequest()->getPost("business_scope");//经营范围
            $tel = $this->getRequest()->getPost('tel');//营业电话
            $shop_hours = $this->getRequest()->getPost('shop_hours');//营业时间
            $bank = $this->getRequest()->getPost('bank');//银行名称
            $bank_no = $this->getRequest()->getPost('bank_no');//银行卡号
            $email = $this->getRequest()->getPost('email');//邮箱
            if ($contacts === '') {
                Common::echoAjaxJson(39, '请填写开户名');
            }
            $contacts_len = mb_strlen($contacts, 'utf-8');
            if ($contacts_len < 2 || $contacts_len > 50) {
                Common::echoAjaxJson(45, '请输入2-50个字符开户名');
            }
            if (!$email) {
                Common::echoAjaxJson(46, '邮箱不能为空');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 50) {
                Common::echoAjaxJson(47, '请输入正确的邮箱地址');
            }
            if (!$bank) {
                Common::echoAjaxJson(31, '请填写银行名称');
            }
            if (preg_match('/[A-Za-z]{1,}/', $bank)) {
                Common::echoAjaxJson(40, '银行名称不能包含英文字符');
            }
            $bank_len = mb_strlen($bank, 'utf-8');
            if ($bank_len > 20) {
                Common::echoAjaxJson(41, '请输入20个中文字内的银行名称');
            }
            if (!$bank_no) {
                Common::echoAjaxJson(42, '请输入银行卡号');
            }
            if (strlen($bank_no) > 39) {
                Common::echoAjaxJson(43, '请输入正确的银行卡号');
            }
            if ($service_type == 1) {
                if (!$license_img) {
                    Common::echoAjaxJson(32, '请上传营业执照图片');
                }
            }
            if (!$identity_img) {
                Common::echoAjaxJson(33, '请上传身份证照片');
            }
            if (!$business_scope) {
                Common::echoAjaxJson(34, '请填写经营范围');
            }
            $len = mb_strlen($business_scope, 'utf-8');
            if ($len < 2 || $len > 100) {
                Common::echoAjaxJson(35, '请输入2-100个字内的经营范围');
            }
            if ($tel === '') {
                Common::echoAjaxJson(36, '请填写营业电话');
            }
            if (!preg_match('/^(0?1[0-9]{10})$|^((0(10|2[1-9]|[3-9]\d{2}))?-?[1-9]\d{6,7})$/', $tel)) {
                Common::echoAjaxJson(37, '请填写正确的营业电话');
            }
            /*if($shop_hours===''){
                Common::echoAjaxJson(38,'请填写营业时间');
            }*/
        }
        $data = array(
            'cate_id' => $cate_id,
            'name' => $name,
            'intro' => $intro,
            'mobile' => $mobile,
            'uid' => $uid,
            'origin' => $origin,
            'tag_type' => 2,//绑定标签类型
            'tag' => $tag,
            'permission' => $permission,
            'type' => $type,
            'province' => $province,
            'city' => $city,
            'town' => $town,
            'lng' => $lng,
            'lat' => $lat,
            'stage_address' => $address,
            'is_pay' => $is_pay
        );
        $rs = $stageModel->saveStage($data);
        if ($rs < 1) {
            Common::echoAjaxJson(14, '提交失败');
        }
        if ($rs && $type == 2) {
            $b_data = array(
                'sid' => $rs,
                'type' => $service_type,
                'identity_img' => $identity_img,
                'license_img' => $license_img,
                'bank' => $bank,
                'bank_no' => $bank_no,
                'email' => $email,
                'contacts' => $contacts,
                'tel' => $tel,
                'shop_hours' => $shop_hours,
                'business_scope' => $business_scope
            );
            $stageModel->addBusinessInfo($b_data);
        }
        Common::appLog('stage/addStage', $this->startTime, $version);
        Common::echoAjaxJson(1, "提交成功,我们将在1个工作日内完成审核", $rs);
    }

    //驿站开通服务功能表单提交
    public function upgradeStageAction()
    {
        $user = Common::isLogin($_POST, 1);
        if (!$user) {
            Common::echoAjaxJson(31, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $sid = $this->getRequest()->getPost("sid");//驿站id
        $name = $this->getRequest()->getPost("name");//驿站名称
        $type = $this->getRequest()->getPost("service_type");//驿站类型 1:商家 2:个人
        $cate_id = $this->getRequest()->getPost("cate_id");//驿站分类
        $identity_img = $this->getRequest()->getPost("identity_img");//身份证照片
        $address = $this->getRequest()->getPost("address");//商家地址
        $lng = $this->getRequest()->getPost("lng");//经度
        $lat = $this->getRequest()->getPost("lat");//纬度
        $contacts = $this->getRequest()->getPost("contacts");//
        $icon = $this->getRequest()->getPost('icon');//驿站图标
        //$cover = $this->getRequest()->getPost('cover');//驿站图片
        $intro = $this->getRequest()->getPost('intro');//驿站简介
        $tel = $this->getRequest()->getPost('tel');//电话号码 区号-电话号码
        $shop_hours = $this->getRequest()->getPost('shop_hours');//营业时间
        $license_img = $this->getRequest()->getPost("license_img");//营业执照图片
        $bank = $this->getRequest()->getPost("bank");//银行名称
        $bank_no = $this->getRequest()->getPost("bank_no");//银行卡号
        $email = $this->getRequest()->getPost("email");
        $business_scope = $this->getRequest()->getPost("business_scope");//经营范围
        $mobile = $this->getRequest()->getPost("mobile");
        $tag_arr = $this->getRequest()->getPost('tag_arr');//标签id
        $stageModel = new StageModel();
        $stage_num = $stageModel->getCreateBussinessNum($uid);//用户是否创建过服务驿站
        if ($stage_num > 0) {
            Common::echoAjaxJson(2, "您已经有一个服务驿站，不能升级");
        }
        $upgrade = $stageModel->isUpgrade($uid);
        if ($upgrade > 0) {
            Common::echoAjaxJson(3, "服务驿站正在审核，不能再次申请");
        }
        $create_num = $stageModel->getCreateNum($user['uid']);
        if ($create_num < 1) {
            Common::echoAjaxJson(4, "您没有文化驿站可以升级");
        }
        if (!$sid) {
            Common::echoAjaxJson(5, "开通服务的驿站id为空！");
        }
        $num = $stageModel->verifyBusiness($sid, $uid);
        if (!$num) {
            Common::echoAjaxJson(32, '你没有开通服务的权限');
        }
        $stageInfo = $stageModel->getBasicStageBySid($sid);
        if (!$stageInfo) {
            Common::echoAjaxJson(33, "抱歉！该驿站已被关闭，由于该驿站违反了社区的相关规定");
        }
        $name = trim($name);
        if ($name === '') {
            Common::echoAjaxJson(6, "请输入驿站名称");
        }
        if (preg_match('/[A-Za-z]{1,}/', $name)) {
            Common::echoAjaxJson(7, '驿站名称不能包含英文字符');
        }
        $name_len = mb_strlen($name, 'utf-8');
        if ($name_len < 2 || $name_len > 10) {
            Common::echoAjaxJson(8, '请输入2-10个中文字作为驿站名称');
        }
        if (Common::badWord($name)) {
            Common::echoAjaxJson(9, '驿站名称含有敏感词');
        }
        $name_rs = $stageModel->stageNameIsExist($name, $sid);
        if ($name_rs > 0) {
            Common::echoAjaxJson(10, '驿站名称已经存在');
        }
        if (!$cate_id) {
            Common::echoAjaxJson(11, "请选择驿站分类");
        }
        if (!$type || !in_array($type, array(1, 2))) {
            Common::echoAjaxJson(12, "请选择驿站类型");
        }
        if ($type && $type == 1) {
            if (!$license_img) {
                Common::echoAjaxJson(13, '请上传营业执照图片');
            }
        }
        if (!$identity_img) {
            Common::echoAjaxJson(14, '请上传身份证照片');
        }
        if ($address === '') {
            Common::echoAjaxJson(15, '请输入详细的商家地址');
        }
        if (!$lng || !$lat) {
            Common::echoAjaxJson(16, '请标注您的坐标，以便用户能更好的找到您');
        }
        $town = $this->getRequest()->getPost('town_id');//城市三级联动 三级id
        if (!$town) {
            Common::echoAjaxJson(17, '城市id不能为空');
        }

        if ($contacts === '') {
            Common::echoAjaxJson(18, '请填写开户名');
        }
        $contacts_len = mb_strlen($contacts, 'utf-8');
        if ($contacts_len < 2 || $contacts_len > 50) {
            Common::echoAjaxJson(45, '请输入2-50个字符开户名');
        }
        /*if(!$icon){
            Common::echoAjaxJson(20,'没有上传驿站图标');
        }
        if(!$cover){
            Common::echoAjaxJson(24,'请上传至少一张驿站形象或者宣传图片');
        }
        $cover = explode('&',$cover);
        if(count($cover)>4){
            Common::echoAjaxJson(21,'驿站图片只能上传4张');
        }*/
        if ($intro === '') {
            Common::echoAjaxJson(22, "请输入驿站简介");
        }
        $intro_len = mb_strlen($intro, 'utf-8');
        if ($intro_len < 10 || $intro_len > 3000) {
            Common::echoAjaxJson(23, '请输入10-3000个字作为驿站简介');
        }
        if ($tel === '') {
            Common::echoAjaxJson(24, '请输入正确的手机号或座机号');
        }
        if (!preg_match('/^(0?1[0-9]{10})$|^((0(10|2[1-9]|[3-9]\d{2}))?-?[1-9]\d{6,7})$/', $tel)) {
            Common::echoAjaxJson(25, '请输入正确的手机号或座机号');
        }
        /*if($shop_hours===''){
            Common::echoAjaxJson(26,'请填写营业时间');
        }*/
        if (!$bank) {
            Common::echoAjaxJson(34, '请填写银行名称');
        }
        if (preg_match('/[A-Za-z]{1,}/', $bank)) {
            Common::echoAjaxJson(35, '银行名称不能包含英文字符');
        }
        $bank_len = mb_strlen($bank, 'utf-8');
        if ($bank_len > 20) {
            Common::echoAjaxJson(36, '请输入20个中文字内的银行名称');
        }
        if (!$bank_no) {
            Common::echoAjaxJson(37, '请输入银行卡号');
        }
        if (strlen($bank_no) > 39) {
            Common::echoAjaxJson(38, '请输入正确的银行卡号');
        }
        if (!$email) {
            Common::echoAjaxJson(39, '邮箱不能为空');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 50) {
            Common::echoAjaxJson(40, '请输入正确的邮箱地址');
        }
        if (!$business_scope) {
            Common::echoAjaxJson(28, '请填写经营范围');
        }
        $len = mb_strlen($business_scope, 'utf-8');
        if ($len < 2 || $len > 100) {
            Common::echoAjaxJson(29, '请输入2-100个字内的经营范围');
        }
        if (!$tag_arr) {
            Common::echoAjaxJson(30, '请选择驿站标签');
        }
        $tag = explode('&', $tag_arr);
        if (count($tag) > 6) {
            Common::echoAjaxJson(31, '驿站标签不能超过6个');
        }
        $data = array(
            'cate_id' => $cate_id,
            'name' => $name,
            'uid' => $uid,
            'type' => $type,
            'identity_img' => $identity_img,
            'license_img' => $license_img,
            'bank' => $bank,
            'bank_no' => $bank_no,
            'email' => $email,
            'address' => $address,
            'lng' => $lng,
            'lat' => $lat,
            'town' => $town,
            'contacts' => $contacts,
            'intro' => $intro,
            'tel' => $tel,
            'shop_hours' => $shop_hours,
            'business_scope' => $business_scope,
            'sid' => $sid,
            'mobile' => $mobile,
            'tag' => $tag
        );
        $rs = $stageModel->upgradeBusiness($data);
        if ($rs == -1) {
            Common::echoAjaxJson(30, '开通失败');
        }
        PHPQRCode::getStagePHPQRCode($sid, true);
        Common::appLog('stage/upgradeStage', $this->startTime, $version);
        Common::echoAjaxJson(1, '提交成功,我们将在1个工作日内完成审核');
    }

    //判断驿站状态
    public function getStatusNewAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $uid = $user['uid'];
        $stageModel = new StageModel();
        $list = $stageModel->getStageList($uid, 1, 1);
        if (!$list) {
            Common::echoAjaxJson(2, '用户没有创建过驿站', array());
        }
        $stage_num = $stageModel->getCreateBussinessNum($uid);//用户是否创建过服务驿站
        $check_num = $stageModel->isUpgrade($uid);
        foreach ($list as $k => $v) {
            $stageList[$k]['sid'] = $v['sid'];
            $stageList[$k]['name'] = $v['name'];
            $stageList[$k]['intro'] = $v['intro'];
            $stageList[$k]['cate_id'] = $v['cate_id'];
            $cate_name = $stageModel->getCultureCateById($v['cate_id']);
            $stageList[$k]['cate_name'] = $cate_name['name'];
            $stageList[$k]['cover'] = IMG_DOMAIN . $v['cover'];
            $stageList[$k]['icon'] = IMG_DOMAIN . $v['icon'];
            $stageList[$k]['type'] = $v['type'];
            $stageList[$k]['status'] = $v['status'];
            $stageList[$k]['icon'] = IMG_DOMAIN . $v['icon'];
            $stageList[$k]['user_num'] = $v['user_num'];
            $stageList[$k]['topic_num'] = $v['topic_num'];
            if ($stage_num > 0 || $check_num > 0) {
                $stageList[$k]['has_server'] = 1;
            } else {
                $stageList[$k]['has_server'] = 0;
            }
        }
        Common::appLog('stage/getStatusNew', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $stageList);
    }

    public function getTagListAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $type_cate = (int)$this->getRequest()->getPost('type_cate');
        if (!$type_cate) {
            Common::echoAjaxJson(2, "文化分类不能为空");
        }
        $tagModel = new TagModel();
        $list = $tagModel->listTag(2, $type_cate);
        Common::appLog('stage/getTagList', $this->startTime, $version);
        Common::echoAjaxJson(1, "获取成功", $list);
    }

    //管理帖子
    public function topicListAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $sid = intval($this->getRequest()->getPost('sid'));
        $page = intval($this->getRequest()->getPost('page'));
        $size = intval($this->getRequest()->getPost('size'));
        $list = array();
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $stageModel = new StageModel();
        if (!$sid) {
            Common::echoAjaxJson(2, "驿站id为空");
        }
        $stageInfo = $stageModel->getBasicStageBySid($sid);
        if (!$stageInfo && $stageInfo['status'] > 1) {
            Common::echoAjaxJson(3, "抱歉！该驿站已被关闭，由于该驿站违反了社区的相关规定");
        }
        $page = $page ? $page : 2;
        $size = $size ? $size : 10;
        $list['list'] = $stageModel->getListBySid($sid, 'topic', 0, $page, $size, $_POST['token'], $version);
        Common::appLog('stage/topicList', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $list ? $list : array());
    }

    public function getUpdateInfoAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $sid = intval($this->getRequest()->getPost('sid'));
        $type = $this->getRequest()->getPost('type') ? $this->getRequest()->getPost('type') : 1;//1.创建  2升级
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $stageModel = new StageModel();
        $tagModel = new TagModel();
        $stageInfo = $stageModel->getStageRedisById($sid, $type);
        $stageTag = $tagModel->getRelation(2, $sid);//驿站标签
        $stage_tag = $tag_list = array();
        foreach ($stageTag as $k => $v) {
            $stage_tag[$k]['id'] = $v['tag_id'];
            $stage_tag[$k]['content'] = $v['content'];
        }
        $stageInfo['stage_tag'] = $stage_tag;
        $tagList = $tagModel->listTag(2, $stageInfo['cate_id']);
        foreach ($tagList as $k => $v) {
            foreach ($stage_tag as $v1) {
                if ($v1['id'] == $v['id']) {
                    unset($tagList[$k]);
                }
            }
            $tag_list[] = $v;
        }
        $snsCheckModel = new SnsCheckModel();
        $operation_type = 2;
        if ($stageInfo['type'] == 1 && $type == 1) {
            $operation_type = 2;
        } elseif ($stageInfo['type'] == 2 && $type == 1) {
            $operation_type = 56;
        } elseif ($type == 2) {
            $operation_type = 59;
        }
        $stageInfo['reason'] = $snsCheckModel->get($sid, $operation_type, 0);
        $stageInfo['tag_list'] = $tag_list;


        Common::appLog('stage/getUpdateInfo', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $stageInfo);
    }

    /*
     * 更新驿站信息
     */
    public function updateStageAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(17, "非法登录用户");
        }
        $uid = $user['uid'];
        $sid = $this->getRequest()->getPost('sid');
        $stageModel = new StageModel();
        $cate_id = $this->getRequest()->getPost("cate_id");
        $type = $this->getRequest()->getPost("type");//1文化 2服务
        $name = $this->getRequest()->getPost("name");
        $intro = $this->getRequest()->getPost("intro");
        $tag_arr = $this->getRequest()->getPost('tag_arr');
        $mobile = $this->getRequest()->getPost("mobile");
        $agree = $this->getRequest()->getPost('agree');
        $lng = $this->getRequest()->getPost('lng');
        $lat = $this->getRequest()->getPost('lat');
        $address = $this->getRequest()->getPost('address');
        $town = $this->getRequest()->getPost('town_id');
        $permission = $this->getRequest()->getPost('permission');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if (!$cate_id) {
            Common::echoAjaxJson(2, "请选择驿站分类");
        }
        $name = trim($name);
        if (!$name) {
            Common::echoAjaxJson(3, "请输入驿站名称");
        }
        if (preg_match('/[A-Za-z]{1,}/', $name)) {
            Common::echoAjaxJson(4, '驿站名称不能包含英文字符');
        }
        $name_len = mb_strlen($name, 'utf-8');
        if ($name_len < 2 || $name_len > 10) {
            Common::echoAjaxJson(5, '请输入2-10个中文字作为驿站名称');
        }
        if (Common::badWord($name)) {
            Common::echoAjaxJson(6, '驿站名称含有敏感词');
        }
        $name_rs = $stageModel->stageNameIsExist($name, $sid);
        if ($name_rs > 0) {
            Common::echoAjaxJson(7, '驿站名称已经存在');
        }
        if ($intro == '') {
            Common::echoAjaxJson(8, "驿站介绍不能为空");
        }

        $intro_len = mb_strlen(strip_tags($intro), 'utf-8');
        if ($intro_len < 10 || $intro_len > 3000) {
            Common::echoAjaxJson(9, '请输入10-3000个中文作为驿站介绍');
        }
        //驿站介绍内容处理
        $security = new Security();
        $intro = $security->xss_clean($intro);
        if (!$tag_arr) {
            Common::echoAjaxJson(10, '请选择驿站标签');
        }
        $tag = explode('&', $tag_arr);
        if (count($tag) > 6) {
            Common::echoAjaxJson(11, '驿站标签不能超过6个');
        }
        if (!$mobile) {
            Common::echoAjaxJson(12, '请填写手机号');
        }
        if (!preg_match('/^1[0-9]{10}$/', $mobile)) {
            Common::echoAjaxJson(13, '请输入正确手机号格式');
        }
        if (!$agree) {
            Common::echoAjaxJson(14, '请阅读并同意创建驿站相关文件');
        }
        if (!$permission) {
            Common::echoAjaxJson(15, '未设置成员加入权限');
        }
        if (!$town) {
            Common::echoAjaxJson(18, "三级城市id为空");
        }
        if (!$lng || !$lat) {
            Common::echoAjaxJson(19, "经纬度为空");
        }
        if (!$address) {
            Common::echoAjaxJson(20, "驿站地址为空");
        }
        $addressModel = new AddressModel();
        $area_info = $addressModel->cityParent($town);
        $city = $area_info['id'];
        $province = $area_info['pid'];
        $service_type = $this->getRequest()->getPost("service_type");//1.企业 2.个人
        if ($service_type) {
            $contacts = $this->getRequest()->getPost("contacts");
            $license_img = $this->getRequest()->getPost("license_img");//营业执照图片
            $identity_img = $this->getRequest()->getPost("identity_img");//身份证照片
            $business_scope = $this->getRequest()->getPost("business_scope");//经营范围
            $tel = $this->getRequest()->getPost('tel');//营业电话
            $shop_hours = $this->getRequest()->getPost('shop_hours');//营业时间
            $bank = $this->getRequest()->getPost('bank');//银行名称
            $bank_no = $this->getRequest()->getPost('bank_no');//银行卡号
            $email = $this->getRequest()->getPost('email');//邮箱
            if ($contacts === '') {
                Common::echoAjaxJson(21, '请填写开户名');
            }
            $contacts_len = mb_strlen($contacts, 'utf-8');
            if ($contacts_len < 2 || $contacts_len > 50) {
                Common::echoAjaxJson(45, '请输入2-50个字符开户名');
            }
            if (!$email) {
                Common::echoAjaxJson(24, '邮箱不能为空');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 50) {
                Common::echoAjaxJson(25, '请输入正确的邮箱地址');
            }
            if (!$bank) {
                Common::echoAjaxJson(26, '请填写银行名称');
            }
            if (preg_match('/[A-Za-z]{1,}/', $bank)) {
                Common::echoAjaxJson(27, '银行名称不能包含英文字符');
            }
            $bank_len = mb_strlen($bank, 'utf-8');
            if ($bank_len > 20) {
                Common::echoAjaxJson(28, '请输入20个中文字内的银行名称');
            }
            if (!$bank_no) {
                Common::echoAjaxJson(29, '请输入银行卡号');
            }
            if (strlen($bank_no) > 39) {
                Common::echoAjaxJson(30, '请输入正确的银行卡号');
            }
            if ($service_type == 1) {
                if (!$license_img) {
                    Common::echoAjaxJson(31, '请上传营业执照图片');
                }
            }
            if (!$identity_img) {
                Common::echoAjaxJson(32, '请上传身份证照片');
            }
            if (!$business_scope) {
                Common::echoAjaxJson(33, '请填写经营范围');
            }
            $len = mb_strlen($business_scope, 'utf-8');
            if ($len > 100) {
                Common::echoAjaxJson(34, '请填写100个字内的经营范围');
            }
            if ($tel === '') {
                Common::echoAjaxJson(35, '请填写营业电话');
            }
            if (!preg_match('/^(0?1[0-9]{10})$|^((0(10|2[1-9]|[3-9]\d{2}))?-?[1-9]\d{6,7})$/', $tel)) {
                Common::echoAjaxJson(36, '请填写正确的营业电话');
            }
        }
        $stageInfo = $stageModel->getBasicStageBySid($sid);
        if ($stageInfo['type'] == 1 && !$service_type) {
            //普通驿站修改资料审核
            $stageCheck = 0;
        } elseif ($stageInfo['type'] == 1 && $service_type) {
            //普通驿站开通服务升级
            $stageCheck = 1;
        } elseif ($stageInfo['type'] == 2) {
            //服务驿站修改资料审核
            $stageCheck = 2;
        }
        $data = array(
            'cate_id' => $cate_id,
            'name' => $name,
            'intro' => $intro,
            'mobile' => $mobile,
            'uid' => $uid,
            'tag' => $tag,
            'permission' => $permission,
            'type' => $type,
            'province' => $province,
            'city' => $city,
            'town' => $town,
            'lng' => $lng,
            'lat' => $lat,
            'stage_address' => $address,
            'sid' => $sid,
            'stageCheck' => $stageCheck,
            'stage_status' => $stageInfo['status']
        );
        $rs = $stageModel->updateStage($data);
        if ($rs < 1) {
            Common::echoAjaxJson(16, '重新编辑驿站失败');
        }
        if ($rs && $service_type) {
            $stageModel->updateBusinessInfo($sid, $service_type, $identity_img, $license_img, $bank, $bank_no, $email, $contacts, $tel, $shop_hours, $business_scope);
        }
        Common::appLog('stage/updateStage', $this->startTime, $version);
        Common::echoAjaxJson(1, '提交成功');
    }

    /*****************2.5 驿站条件筛选********************/
    public function getStageConditionAction()
    {
        $data['token'] = $this->getRequest()->get('token');
        if ($data['token']) {
            $user = Common::isLogin($data);
            if (!$user) {
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $type = $this->getRequest()->getPost("type");
        $list = array();
        $condition = array();
        if (in_array($type, array(0, 1, 2))) {
            $stageModel = new StageModel();
            $tagModel = new TagModel();
            $cate_list = $stageModel->getCultureCateList();
            foreach ($cate_list as $k => $v) {
                $list[$k]['id'] = $v['id'];
                $list[$k]['name'] = $v['name'];
                $tag = $tagModel->listTag(2, $v['id']);
                foreach ($tag as $k1 => $v1) {
                    $list[$k]['tag'][$k1]['tag_id'] = $v1['id'];
                    $list[$k]['tag'][$k1]['name'] = $v1['content'];
                }
            }
            $condition['level_first'] = $list;
            $condition['level_second'] = Common::returnCity();
            $condition['level_third'] = array(array('sort' => '不限'), array('sort' => '最新'), array('sort' => '活跃'), array('sort' => '内容最多'), array('sort' => '成员最多'));
        } elseif ($type == 3) {
            $condition['level_second'] = Common::returnCity();
            $condition['level_third'] = array(array('sort' => '不限'), array('sort' => '最新'), array('sort' => '活跃'), array('sort' => '内容最多'), array('sort' => '成员最多'));
        }
        Common::appLog('stage/getStageCondition', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $condition);
    }

    public function getListByConditionAction()
    {
        $data['token'] = $this->getRequest()->get('token');
        if ($data['token']) {
            $user = Common::isLogin($data);
            if (!$user) {
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $type = $this->getRequest()->getPost("type");//筛选驿站的类型 1文化 2服务 3场馆 0全部
        $id = $this->getRequest()->getPost("id");//大类id
        $tag_id = $this->getRequest()->getPost("tag_id"); //大类下的标签id
        $city = $this->getRequest()->getPost("city"); //城市
        $sort = $this->getRequest()->getPost("sort"); //排序
        $size = ($this->getRequest()->getPost("size") && $this->getRequest()->getPost("size") == 20) ? $this->getRequest()->getPost("size") : 20; //条数
        $page = intval($this->getRequest()->getPost("page")); //页数
        $page = $page ? $page : 1;
        if (in_array($type, array(0, 1, 2))) {
            $type = 0;
        }
        $city_id = Common::getIdByCity($city);
        $sort = $sort && $sort != '不限' ? $sort : '';
        $stageModel = new StageModel();
        $list = $stageModel->getList($type, $id, $tag_id, $city_id, $sort, $page, (int)$size);
        Common::appLog('stage/getListByCondition', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $list ? $list : array());

    }

    /******** 2.5.1 ********/
    public function updateStageNewAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(23, "非法登录用户");
        }
        $uid = $user['uid'];
        $sid = $this->getRequest()->getPost('sid');
        $stageModel = new StageModel();
        $stageInfo = $stageModel->getBasicStageBySid($sid, 3);
        if ($stageInfo['status'] > 1) {
            Common::echoAjaxJson(2, "抱歉！该驿站已被关闭，由于该驿站违反了社区的相关规定");
        }
        $icon = $this->getRequest()->getPost("icon");
        $name = $this->getRequest()->getPost("name");
        $intro = $this->getRequest()->getPost("intro");
        $tag_arr = $this->getRequest()->getPost('tag_arr');
        $type = $this->getRequest()->getPost('type');
        $authority = $this->getRequest()->getPost('authority') ? $this->getRequest()->getPost('authority') : 1;
        $address = $this->getRequest()->getPost('address');
        $lng = $this->getRequest()->getPost('lng');
        $lat = $this->getRequest()->getPost('lat');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $name = trim($name);
        if (!$icon) {
            Common::echoAjaxJson(3, "驿站图片不能为空");
        }
        if (!$name) {
            Common::echoAjaxJson(4, "请输入驿站名称");
        }
        if (preg_match('/[A-Za-z]{1,}/', $name)) {
            Common::echoAjaxJson(5, '驿站名称不能包含英文字符');
        }
        $name_len = mb_strlen($name, 'utf-8');
        if ($name_len < 2 || $name_len > 10) {
            Common::echoAjaxJson(6, '请输入2-10个中文字作为驿站名称');
        }
        if (Common::badWord($name)) {
            Common::echoAjaxJson(7, '驿站名称含有敏感词');
        }
        $name_rs = $stageModel->stageNameIsExist($name, $sid);
        if ($name_rs > 0) {
            Common::echoAjaxJson(8, '驿站名称已经存在');
        }
        if ($intro == '') {
            Common::echoAjaxJson(9, "驿站介绍不能为空");
        }

        $intro_len = mb_strlen($intro, 'utf-8');
        if ($intro_len < 10 || $intro_len > 3000) {
            Common::echoAjaxJson(10, '请输入10-3000个字符作为驿站介绍');
        }
        //驿站介绍内容处理
        $security = new Security();
        $intro = $security->xss_clean($intro);
        if (!$tag_arr) {
            Common::echoAjaxJson(11, '请选择驿站标签');
        }
        $tag = explode('&', $tag_arr);
        if (count($tag) > 6) {
            Common::echoAjaxJson(12, '驿站标签不能超过6个');
        }
        $lng = $lng ? $lng : $stageInfo['lng'];
        $lat = $lat ? $lat : $stageInfo['lat'];
        $address = $address ? $address : $stageInfo['stage_address'];
        if ($type == 1) {
            $mobile = $this->getRequest()->getPost("mobile");
            if (!$mobile) {
                Common::echoAjaxJson(13, '请填写手机号');
            }
            if (!preg_match('/^1[0-9]{10}$/', $mobile)) {
                Common::echoAjaxJson(14, '请输入正确手机号格式');
            }
            $rs = $stageModel->updateStageNew($sid, $icon, $name, $intro, $mobile, $uid, $tag, $authority, $address, $lng, $lat);
        } elseif ($type == 2) {
            $cover = $this->getRequest()->getPost('cover');//驿站图片数组
            $tel = $this->getRequest()->getPost('tel');//电话号码 区号-电话号码
            $shop_hours = $this->getRequest()->getPost('shop_hours');//营业时间
            $town_id = $this->getRequest()->getPost('town_id');//城市联动三级id
            if (!$cover) {
                Common::echoAjaxJson(15, '至少上传1张图片');
            }
            $cover = explode('&', $cover);
            if (count($cover) > 4) {
                Common::echoAjaxJson(16, '驿站图片只能上传4张');
            }
            if ($tel === '') {
                Common::echoAjaxJson(17, '请输入正确的手机号或座机号');
            }
            if (!preg_match('/^(0?1[0-9]{10})$|^((0(10|2[1-9]|[3-9]\d{2}))?-?[1-9]\d{6,7})$/', $tel)) {
                Common::echoAjaxJson(18, '请输入正确的手机号或座机号');
            }
            /*if($shop_hours===''){
                Common::echoAjaxJson(19,'请填写营业时间');
            }*/
            if (!$town_id) {
                Common::echoAjaxJson(20, '您选择的城市不正确');
            }
            if (!$address) {
                Common::echoAjaxJson(21, '详细地址不能为空');
            }
            if (!$lng || !$lat) {
                Common::echoAjaxJson(22, '请选择地址坐标');
            }
            $rs = $stageModel->updateBusinessNew($sid, $icon, $name, $intro, $uid, $town_id, $tag, $authority, $cover, $tel, $shop_hours, $address, $lng, $lat);
        }
        //只要用户修改了名称、简介后需要提交后台审核
        if (isset($rs) && $rs == 2) {
            Common::echoAjaxJson(24, '您修改的资料正在审核中');
        }
        Common::appLog('stage/updateStageNew', $this->startTime, $version);
        Common::echoAjaxJson(1, '提交成功');
    }
    /*************3.0*******************/
    //3.0驿站主页
    public function stageIndexAction()
    {
        $uid = 0;
        $list = array();
        $sid = intval($this->getRequest()->getPost('sid'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $type = $this->getRequest()->get('type');//0全部 1帖子 2服务信息 3精
        if (!$sid) {
            Common::echoAjaxJson(2, "驿站id为空");
        }
        $data['token'] = $this->getRequest()->get('token');
        if ($data['token']) {
            $user = Common::isLogin($data);
            if (!$user) {
                Common::echoAjaxJson(5, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $stageModel = new StageModel();
        $visitModel = new VisitModel();
        if (!$sid) {
            Common::echoAjaxJson(2, "驿站id为空");
        }
        $stageInfo = $stageModel->getBasicStageBySid($sid);
        if (!$stageInfo || $stageInfo['status'] > 1) {
            Common::echoAjaxJson(3, "抱歉！该驿站已被关闭，由于该驿站违反了社区的相关规定");
        }
        $isUpgrade = $stageModel->isUpgradeNew($sid);
        if ($isUpgrade > 0) {
            Common::echoAjaxJson(4, "抱歉！该驿站正在升级中,请耐心等待");
        }
        if ($stageInfo['type'] == 1 && $type === '') {
            $type = 1;
        } elseif ($stageInfo['type'] == 2 && $type === '') {
            $type = 0;
        }
        $visitModel->addStagePV($sid);
        if ($uid && $uid != $stageInfo['uid']) {
            $visitModel->addStageVisit($sid, $uid);
        }
        $stageMangerModel = new StageManagerModel();
        $stagegoodsModel = new StagegoodsModel();
        $stageMangerModel->addView($sid);
        $visitModel->addVisitNum('stage', $sid);
        $list['sid'] = $stageInfo['sid'];
        $list['name'] = $stageInfo['name'];
        $list['type'] = $stageInfo['type'];
        $list['icon'] = Common::show_img($stageInfo['icon'], 4, 160, 160);
        $list['authority'] = $stageInfo['authority'];
        if ($stageInfo['qrcode_img']) {
            $list['qrcode_img'] = IMG_DOMAIN . $stageInfo['qrcode_img'];
        } else {
            $list['qrcode_img'] = '';
        }
        $list['cover'] = $stageInfo['cover'] ? IMG_DOMAIN . $stageInfo['cover'] : PUBLIC_DOMAIN . 'default_stage_cover.jpg';
        $list['user_num'] = $stageInfo['user_num'];
        $list['topic_num'] = $stageInfo['topic_num'];
        $list['is_join'] = $stageModel->getJoinStage($sid, $uid);
        $list['stage_role'] = $stageModel->joinStageRole($sid, $uid);
        $list['stage_role'] = $list['stage_role'] ? $list['stage_role'] : (object)array();
        $list['member'] = $stageModel->getListByLoginTime($sid, 0, 9);
        $list['goods'] = $stagegoodsModel->getReCommendGoods($sid, $data['token'], $version);
        $list['list'] = $stageModel->getTopicAndEventList($sid, 1, 10, $data['token'], $version, $uid, $type);
        $joinInfo = $stageModel->getJoinStage($sid, $uid);
        if ($uid && $joinInfo) {
            $t_num = $stageModel->getRedisNewTopicNum($sid, $uid);
            $stageModel->modifyRedisNewTopicNumTotals($uid, (-$t_num));
            $stageModel->resetRedisNewTopicNum($sid, $uid);
        }
        Common::appLog('stage/stageIndex', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $list);
    }

    //3.0主页列表加载
    public function stageIndexMoreAction()
    {
        $uid = 0;
        $list = array();
        $sid = intval($this->getRequest()->getPost('sid'));
        $type = $this->getRequest()->get('type');//0全部 1帖子 2服务信息 3精
        if (!$sid) {
            Common::echoAjaxJson(2, "驿站id为空");
        }
        $data['token'] = $this->getRequest()->get('token');
        if ($data['token']) {
            $user = Common::isLogin($data);
            if (!$user) {
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        if (!in_array($type, array(0, 1, 2, 3))) {
            Common::echoAjaxJson(4, "切换类型错误");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $page = intval($this->getRequest()->getPost('page'));
        $size = ($this->getRequest()->getPost('size') && $this->getRequest()->getPost('size') == 10) ? $this->getRequest()->getPost('size') : 10;
        if (!$page || $page < 2) {
            $page = 2;
        }
        $stageModel = new StageModel();
        $list['list'] = $stageModel->getTopicAndEventList($sid, $page, (int)$size, $data['token'], $version, $uid, $type);
        Common::appLog('stage/stageIndexMore', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $list);
    }

    //驿站修改发布权限
    public function updateAuthorityAction()
    {
        $user = Common::isLogin($_POST, 1);
        if (!$user) {
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $uid = $user['uid'];
        $sid = intval($this->getRequest()->getPost('sid'));
        $authority = intval($this->getRequest()->getPost('authority'));//1.允许 2.禁止
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if (!$sid) {
            Common::echoAjaxJson(2, "驿站id为空");
        }
        //查看用户驿站角色
        $stageModel = new StageModel();
        $role = $stageModel->joinStageRole($sid, $uid);
        if (!$role || $role['role'] != 1) {
            Common::echoAjaxJson(3, "您没有权限管理驿站");
        }
        $rs = $stageModel->updateAuthority($sid, $authority);
        if (!$rs) {
            Common::echoAjaxJson(4, "设置失败");
        }
        Common::appLog('stage/updateAuthority', $this->startTime, $version);
        Common::echoAjaxJson(1, '设置成功');
    }

    //驿站群聊消息页面数据获取
    public function getStageMessageViewAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $uid = $user['uid'];
        $sid = intval($this->getRequest()->getPost('sid'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if (!$sid) {
            Common::echoAjaxJson(2, "驿站id为空");
        }
        $stageModel = new StageModel();
        $userModel = new UserModel();
        $stage = $stageModel->getStageById($sid);
        if (!$stage) {
            Common::echoAjaxJson(3, '驿站不存在');
        }
        if (!$stage['status'] > 1) {
            Common::echoAjaxJson(4, '抱歉！该驿站已被关闭，由于该驿站违反了社区的相关规定');
        }
        $create = $userModel->getUserData($stage['uid'], $uid);
        $list['create_list']['uid'] = $create['uid'];
        $list['create_list']['did'] = $create['did'];
        $list['create_list']['nick_name'] = $create['nick_name'];
        $list['create_list']['type'] = $create['type'];
        $list['create_list']['avatar'] = Common::show_img($create['avatar'], 160, 160);
        $list['admin_list'] = $stageModel->getAdminListBySid($sid, $uid);//驿站驿管列表
        $list['member_list'] = $stageModel->getMemberListBySid($sid, 1, 10, $uid);//驿站成员
        $list['user_num'] = $stage['user_num'];
        $list['name'] = $stage['name'];
        $list['qrcode_img'] = $stage['qrcode_img'];
        $list['icon'] = $stage['icon'];
        /*$list['notice'] = $stage['notice'] ? $stage['notice'] : '';
        $time = date("Y-m-d");
        $first=1;
        $w=date('w',strtotime($time));
        $week_start=date('Y-m-d',strtotime("$time -".($w ? $w - $first : 6).' days')).' 00:00:00';
        $week_end=date('Y-m-d',strtotime("$week_start +6 days")).' 23:59:59';
        if($stage['notice_time']>=$week_start&&$stage['notice_time']<=$week_end){
            $list['is_add_notice'] =0;
        }else{
            $list['is_add_notice'] =1;
        }*/
        $chatModel = new ChatModel();
        $rs = $chatModel->messageIsClose($uid, 2, $sid);
        if (!$rs) {
            $rs = 1;
        }
        $list['message_type'] = $rs;
        $list['stage_role'] = $stageModel->joinStageRole($sid, $uid) ? $stageModel->joinStageRole($sid, $uid) : array();
        Common::appLog('stage/getStageMessageView', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $list);
    }

    //获取驿站支付权限
    public function getStageIsPayAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $sid = intval($this->getRequest()->getPost('sid'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if (!$sid) {
            Common::echoAjaxJson(2, '驿站sid为空');
        }
        $stageModel = new StageModel();
        $info = $stageModel->getStageIsPay($sid);
        if (!$info) {
            Common::echoAjaxJson(3, '驿站不存在');
        }
        if ($info['is_pay'] == 0) {
            Common::echoAjaxJson(4, '对不起，您在才府暂未开通支付功能，请联系您的商务经理签署《支付协议》或致电客服：13012888193');
        }
        Common::appLog('stage/getStageIsPay', $this->startTime, $version);
        Common::echoAjaxJson(1, '已开通');
    }

    //驿站所有成员列表
    public function getStageUserListAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $sid = intval($this->getRequest()->getPost('sid'));
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if (!$sid) {
            Common::echoAjaxJson(2, "驿站id为空");
        }
        $stageModel = new StageModel();
        $list = $stageModel->getStageUserList($sid, $user['uid']);
        foreach ($list as $k => $v) {
            $data[$k]['uid'] = $v['uid'];
            $data[$k]['did'] = $v['did'];
            $data[$k]['nick_name'] = $v['nick_name'];
            $data[$k]['type'] = $v['type'];
            $data[$k]['avatar'] = Common::show_img($v['avatar'], 1, 160, 160);
        }
        Common::appLog('stage/getStageUserList', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $data);
    }

    //驿站主页商品更多数据
    public function getGoodsListAction()
    {
        $sid = intval($this->getRequest()->getPost('sid'));
        if (!$sid) {
            Common::echoAjaxJson(2, "驿站id为空");
        }
        $data['token'] = $this->getRequest()->get('token');
        if ($data['token']) {
            $user = Common::isLogin($data);
            if (!$user) {
                Common::echoAjaxJson(3, "非法登录用户");
            }
        }
        $type = $this->getRequest()->get('type');//0全部 1现金 2换购 3福报值
        $page = intval($this->getRequest()->get('page')) ? intval($this->getRequest()->get('page')) : 1;
        $size = ($this->getRequest()->get('size') && $this->getRequest()->get('size') == 10) ? $this->getRequest()->get('size') : 10;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $stagegoodsModel = new StagegoodsModel();
        $list = $stagegoodsModel->getGoodsList($sid, $type, $page, (int)$size, $version, $data['token']);
        Common::appLog('stage/getGoodsList', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $list);
    }

    //用户能发布帖子的驿站
    public function isAddStageListAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') : APP_VERSION;//版本号
        $stageModel = new StageModel();
        $list = $stageModel->isAddStageList($uid, 1, 300);
        Common::appLog('stage/isAddStageList', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $list ? $list : array());
    }
    /*******3.5*********/
    //获取用户能创建的驿站数
    public function getIsAddNumAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') : APP_VERSION;//版本号
        $stageModel = new StageModel();
        $userModel = new UserModel();
        $stage_num = $stageModel->getCreateBussinessNum($uid);//用户是否创建过服务驿站
        $serviceNum = $stage_num ? 1 : 0;
        $user_info = $userModel->getUserInfoByUid($user['uid']);
        $exp_info = Common::getUserLevel($user_info['exp']);
        /*if($exp_info['level_id']<=3){
            $total =1;
        }elseif($exp_info['level_id']>3 && $exp_info['level_id']<=6){
            $total =2;
        }elseif($exp_info['level_id']>6){
            $total =3;
        }*/
        $total = 1;
        Common::appLog('stage/getIsAddNum', $this->startTime, $version);
        Common::echoAjaxJson(1, "获取成功", array('total' => $total, 'service' => (1 - $serviceNum), 'level' => $exp_info['level_id']));
    }

    public function getStageViewAction()
    {
        $data['token'] = $this->getRequest()->get('token');
        $uid = 0;
        if ($data['token']) {
            $user = Common::isLogin($data);
            if (!$user) {
                Common::echoAjaxJson(2, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') : APP_VERSION;//版本号
        $stagegoodsModel = new StagegoodsModel();
        $stageModel = new StageModel();
        $indexModel = new IndexModel();
        $tagModel = new TagModel();
        $list['goods'] = $stagegoodsModel->getHotGoods($version, $data['token']);
        $list['my_stage_num'] = $stageModel->getManageStageNum($uid, 2);
        $list['join_stage_num'] = $stageModel->getManageStageNum($uid, 3);
        $list['good_stage'] = $stageModel->getGoodStage($uid);
        $list['tag'] = $tagModel->getAppStageTag();
        $list['culture_cate'] = $stageModel->getCultureCateList();//分类
        $list['good_topic'] = $indexModel->getGoodTopicList(1, 4, $uid, $data['token'], $version, 0);
        Common::appLog('stage/getStageView', $this->startTime, $version);
        Common::echoAjaxJson(1, "获取成功", $list);
    }

    //设置群公告
    public function setStageNoticeAction()
    {
        $user = Common::isLogin($_POST, 1);
        if (!$user) {
            Common::echoAjaxJson(8, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') : APP_VERSION;//版本号
        $notice = $this->getRequest()->getPost('notice');//公告内容
        $sid = $this->getRequest()->getPost('sid');
        $stageModel = new StageModel();
        $role = $stageModel->joinStageRole($sid, $uid);
        if (!$role || $role['role'] > 2) {
            Common::echoAjaxJson(7, "您没有权限发布驿站公告");
        }
        if (!$sid) {
            Common::echoAjaxJson(2, "驿站sid为空");
        }
        if (!$notice) {
            Common::echoAjaxJson(3, "请输入驿站公告");
        }
        $notice_len = mb_strlen($notice, 'utf-8');
        if ($notice_len < 2 || $notice_len > 200) {
            Common::echoAjaxJson(4, '请输入2-200字的驿站公告');
        }
        if (preg_match('/[A-Za-z]{1,}/', $notice)) {
            Common::echoAjaxJson(5, '驿站公告不能包含英文字符');
        }

        $rs = $stageModel->setStageNotice($notice, $sid);
        if (!$rs) {
            Common::echoAjaxJson(6, '发布失败');
        }
        Common::appLog('stage/setStageNotice', $this->startTime, $version);
        Common::echoAjaxJson(1, "发布后所有用户将收到此公告，一周内只可发布一次公告");
    }

    //驿站开通服务功能表单提交
    public function upgradeStageNewAction()
    {
        $user = Common::isLogin($_POST, 1);
        if (!$user) {
            Common::echoAjaxJson(34, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $sid = $this->getRequest()->getPost("sid");//驿站id
        $name = $this->getRequest()->getPost("name");//驿站名称
        $type = $this->getRequest()->getPost("type");//驿站类型 1:商家 2:个人
        $cate_id = $this->getRequest()->getPost("cate_id");//驿站分类
        $identity_img = $this->getRequest()->getPost("identity_img");//身份证照片
        $address = $this->getRequest()->getPost("address");//商家地址
        $lng = $this->getRequest()->getPost("lng");//经度
        $lat = $this->getRequest()->getPost("lat");//纬度
        $contacts = $this->getRequest()->getPost("contacts");//
        $mobile = $this->getRequest()->getPost("mobile");//联系手机
        $icon = $this->getRequest()->getPost('icon');//驿站图标
        $cover = $this->getRequest()->getPost('cover');//驿站图片
        $intro = $this->getRequest()->getPost('intro');//驿站简介
        $tel = $this->getRequest()->getPost('tel');//电话号码 区号-电话号码
        $shop_hours = $this->getRequest()->getPost('shop_hours');//营业时间
        $license_number = $license_img = '';
        $stageModel = new StageModel();
        $stage_num = $stageModel->getCreateBussinessNum($uid);//用户是否创建过服务驿站
        if (!$sid) {
            Common::echoAjaxJson(5, "开通服务的驿站id为空！");
        }
        $num = $stageModel->verifyBusiness($sid, $uid);
        if (!$num) {
            Common::echoAjaxJson(33, '你没有开通服务的权限');
        }
        $stageInfo = $stageModel->getBasicStageBySid($sid);
        if (!$stageInfo) {
            Common::echoAjaxJson(6, "抱歉！该驿站已被关闭，由于该驿站违反了社区的相关规定");
        }
        $name = trim($name);
        if ($name === '') {
            Common::echoAjaxJson(7, "请输入驿站名称");
        }
        if (preg_match('/[A-Za-z]{1,}/', $name)) {
            Common::echoAjaxJson(8, '驿站名称不能包含英文字符');
        }
        $name_len = mb_strlen($name, 'utf-8');
        if ($name_len < 2 || $name_len > 10) {
            Common::echoAjaxJson(9, '请输入2-10个中文字作为驿站名称');
        }
        if (Common::badWord($name)) {
            Common::echoAjaxJson(10, '驿站名称含有敏感词');
        }
        $name_rs = $stageModel->stageNameIsExist($name, $sid);
        if ($name_rs > 0) {
            Common::echoAjaxJson(11, '驿站名称已经存在');
        }
        if (!$cate_id) {
            Common::echoAjaxJson(12, "请选择驿站分类");
        }
        if (!$type || !in_array($type, array(1, 2))) {
            Common::echoAjaxJson(13, "请选择驿站类型");
        }
        if ($type && $type == 1) {
            $license_number = $this->getRequest()->getPost("license_number");//营业执照号
            $license_img = $this->getRequest()->getPost("license_img");//营业执照图片
            if ($license_number === '') {
                Common::echoAjaxJson(14, '请填写营业执照号');
            }
            if (!preg_match('/^([1-9]d*|0){1,30}$/', $license_number)) {
                Common::echoAjaxJson(15, '营业执照号只能输入1-30位数字');
            }
            if (!$license_img) {
                Common::echoAjaxJson(16, '请上传营业执照图片');
            }
        }
        if (!$identity_img) {
            Common::echoAjaxJson(17, '请上传身份证照片');
        }
        if ($address === '') {
            Common::echoAjaxJson(18, '请输入详细的商家地址');
        }
        if (!$lng || !$lat) {
            Common::echoAjaxJson(19, '请标注您的坐标，以便用户能更好的找到您');
        }
        $town = $this->getRequest()->getPost('town_id');//城市三级联动 三级id
        if (!$town) {
            Common::echoAjaxJson(35, '城市id不能为空');
        }
        if ($contacts === '') {
            Common::echoAjaxJson(20, '请填写开户名');
        }
        $contacts_len = mb_strlen($contacts, 'utf-8');
        if ($contacts_len < 2 || $contacts_len > 50) {
            Common::echoAjaxJson(45, '请输入2-50个字符开户名');
        }
        if (!preg_match('/^1[0-9]{10}$/', $mobile)) {
            Common::echoAjaxJson(22, '请输入正确的11位有效手机号');
        }
        if (!$icon) {
            Common::echoAjaxJson(23, '没有上传驿站图标');
        }
        if (!$cover) {
            Common::echoAjaxJson(24, '请上传至少一张驿站形象或者宣传图片');
        }
        $cover = explode('&', $cover);
        if (count($cover) > 4) {
            Common::echoAjaxJson(25, '驿站图片只能上传4张');
        }
        if ($intro === '') {
            Common::echoAjaxJson(26, "请输入驿站简介");
        }
        $intro_len = mb_strlen($intro, 'utf-8');
        if ($intro_len < 10 || $intro_len > 3000) {
            Common::echoAjaxJson(28, '请输入10-3000个字作为驿站简介');
        }
        if ($tel === '') {
            Common::echoAjaxJson(29, '请输入正确的手机号或座机号');
        }
        if (!preg_match('/^(0?1[0-9]{10})$|^((0(10|2[1-9]|[3-9]\d{2}))?-?[1-9]\d{6,7})$/', $tel)) {
            Common::echoAjaxJson(30, '请输入正确的手机号或座机号');
        }
        $data = array(
            'cate_id' => $cate_id,
            'name' => $name,
            'uid' => $uid,
            'type' => $type,
            'identity_img' => $identity_img,
            'license_img' => $license_img,
            'bank' => '',
            'bank_no' => '',
            'email' => '',
            'address' => $address,
            'lng' => $lng,
            'lat' => $lat,
            'town' => $town,
            'contacts' => $contacts,
            'intro' => $intro,
            'tel' => $tel,
            'shop_hours' => $shop_hours,
            'business_scope' => '',
            'sid' => $sid,
            'mobile' => $mobile
        );
        $rs = $stageModel->upgradeBusiness($data);
        if ($rs == -1) {
            Common::echoAjaxJson(32, '开通失败');
        }
        PHPQRCode::getStagePHPQRCode($sid, true);
        Common::appLog('stage/upgradeStageNew', $this->startTime, $version);
        Common::echoAjaxJson(1, '申请开通成功，请耐心等待审核结果');
    }

    //3.5驿站主页
    public function getIndexAction()
    {
        $sid = intval($this->getRequest()->getPost('sid'));
        $data['token'] = $this->getRequest()->get('token');
        $type = $this->getRequest()->get('type');//0全部 1帖子 2服务信息 3.商品
        $uid = 0;
        $is_self = 0;
        $list = array();
        if ($data['token']) {
            $user = Common::isLogin($data);
            if (!$user) {
                Common::echoAjaxJson(4, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $stageModel = new StageModel();
        $visitModel = new VisitModel();
        $stageMangerModel = new StageManagerModel();
        if (!$sid) {
            Common::echoAjaxJson(2, "驿站id为空");
        }
        $stageInfo = $stageModel->getInfoForIndex($sid);
        if (!$stageInfo || $stageInfo['status'] > 1) {
            Common::echoAjaxJson(3, "抱歉！该驿站已被关闭，由于该驿站违反了社区的相关规定");
        }
        if ($stageInfo['type'] == 1) {
            $type = 1;
        }
        if ($stageInfo['uid'] == $uid) {
            $is_self = 1;
        }
        $visitModel->addStagePV($sid);
        if ($uid && $uid != $stageInfo['uid']) {
            $visitModel->addStageVisit($sid, $uid);
        }
        $stageMangerModel->addView($sid);
        $visitModel->addVisitNum('stage', $sid);
        $stageInfo['is_join'] = $stageModel->getJoinStage($sid, $uid);
        $stageInfo['stage_role'] = $stageModel->joinStageRole($sid, $uid) ? $stageModel->joinStageRole($sid, $uid) : (object)array();
        $stageInfo['member'] = $stageModel->getListByLoginTime($sid, 0, 7);
        $stageInfo['notice'] = $stageModel->getNoticeTopic($sid, $version, $data['token']) ? $stageModel->getNoticeTopic($sid, $version, $data['token']) : array();
        //不是自己的驿站
        if ($stageInfo['uid'] != $uid) {
            $stageInfo['stage_status'] = 0;//不显示
        }
        //该驿站是未审核的服务驿站
        if ($stageInfo['type'] == 2 && $stageInfo['status'] < 2 && $stageInfo['is_new'] == 0) {
            $stageInfo['stage_status'] = 0;
        }
        //该驿站是服务驿站
        if ($stageInfo['type'] == 2 && $stageInfo['status'] == 1) {
            $stageInfo['stage_status'] = 0;
        }
        $data_1 = $stageModel->upgrageInfo($uid, 0);//用户升级中的服务驿站
        $data_2 = $stageModel->upgrageInfo($uid, 2);//用户升级不通过的服务驿站
        //该驿站是服务驿站
        $busniess_num = $stageModel->getBusinessNum($uid);
        if ($stageInfo['type'] == 2 && $stageInfo['status'] == 1) {
            $stageInfo['stage_status'] = 0;
        } elseif ($data_1 && !$data_2 && $stageInfo['type'] == 1 && !$busniess_num) {//有升级中 无审核不通过
            foreach ($data_1 as $v) {
                if ($v['sid'] == $sid) {
                    $stageInfo['stage_status'] = 2;//审核中
                } else {
                    $stageInfo['stage_status'] = 0;
                }
            }
        } elseif ($data_2 && $data_1 && $stageInfo['type'] == 1 && !$busniess_num) { //有升级中 有审核不通过
            foreach ($data_2 as $v) {
                if ($v['sid'] == $sid) {
                    $stageInfo['stage_status'] = 3;//我知道了
                } else {
                    $stageInfo['stage_status'] = 0;
                }
            }
        } elseif ($data_2 && !$data_1 && $stageInfo['type'] == 1 && !$busniess_num) { //无升级中 有审核不通过
            foreach ($data_2 as $v) {
                if ($v['sid'] == $sid) {
                    $stageInfo['stage_status'] = 3;//我知道了
                } else {
                    $stageInfo['stage_status'] = 1;//去提交
                }
            }
        } elseif ($stageInfo['type'] == 1 && !$data_1 && !$data_2 && !$busniess_num) {//文化驿站 无升级中 无审核不通过

            $stageInfo['stage_status'] = 1;//去提交
        } else {
            $stageInfo['stage_status'] = 0;
        }
        $stageInfo['list'] = $stageModel->getIndexList($sid, 1, 10, $data['token'], $version, $uid, $type, $is_self);
        $joinInfo = $stageModel->getJoinStage($sid, $uid);
        if ($uid && $joinInfo) {
            $t_num = $stageModel->getRedisNewTopicNum($sid, $uid);
            $stageModel->modifyRedisNewTopicNumTotals($uid, (-$t_num));
            $stageModel->resetRedisNewTopicNum($sid, $uid);
        }
        $snsCheckModel = new SnsCheckModel();
        $stageInfo['reason'] = $snsCheckModel->get($sid, 59, 0);
        Common::appLog('stage/stageIndex', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $stageInfo);
    }

    public function getIndexMoreAction()
    {
        $sid = intval($this->getRequest()->getPost('sid'));
        $type = $this->getRequest()->get('type');//0全部 1帖子 2服务信息 3.商品
        $data['token'] = $this->getRequest()->get('token');
        $uid = 0;
        $is_self = 0;
        $list = array();
        if ($data['token']) {
            $user = Common::isLogin($data);
            if (!$user) {
                Common::echoAjaxJson(4, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $page = intval($this->getRequest()->getPost('page'));
        $size = ($this->getRequest()->getPost('size') && $this->getRequest()->getPost('size') == 10) ? $this->getRequest()->getPost('size') : 10;
        if (!$sid) {
            Common::echoAjaxJson(2, "驿站sid为空");
        }
        $stageModel = new StageModel();
        $stageInfo = $stageModel->getInfoForIndex($sid);
        if (!$stageInfo) {
            Common::echoAjaxJson(3, "抱歉！该驿站已被关闭，由于该驿站违反了社区的相关规定");
        }
        if ($stageInfo['type'] == 1) {
            $type = 1;
        }
        if ($stageInfo['uid'] == $uid) {
            $is_self = 1;
        }
        $list['list'] = $stageModel->getIndexList($sid, $page, (int)$size, $data['token'], $version, $uid, $type, $is_self);
        Common::appLog('stage/stageIndexMore', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $list);
    }

    public function getIndexTopAction()
    {
        $sid = intval($this->getRequest()->getPost('sid'));
        $data['token'] = $this->getRequest()->get('token');
        $uid = 0;
        if ($data['token']) {
            $user = Common::isLogin($data);
            if (!$user) {
                Common::echoAjaxJson(4, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if (!$sid) {
            Common::echoAjaxJson(2, "驿站sid为空");
        }
        $stageModel = new StageModel();
        $stageInfo = $stageModel->getInfoForIndex($sid);
        if (!$stageInfo) {
            Common::echoAjaxJson(3, "抱歉！该驿站已被关闭，由于该驿站违反了社区的相关规定");
        }
        $stageInfo['is_join'] = $stageModel->getJoinStage($sid, $uid);
        $stageInfo['stage_role'] = $stageModel->joinStageRole($sid, $uid) ? $stageModel->joinStageRole($sid, $uid) : (object)array();
        $stageInfo['member'] = $stageModel->getListByLoginTime($sid, 0, 7);
        $stageInfo['notice'] = $stageModel->getNoticeTopic($sid, $version, $data['token']) ? $stageModel->getNoticeTopic($sid, $version, $data['token']) : array();
        //不是自己的驿站
        if ($stageInfo['uid'] != $uid) {
            $stageInfo['stage_status'] = 0;//不显示
        }
        //该驿站是未审核的服务驿站
        if ($stageInfo['type'] == 2 && $stageInfo['status'] < 2 && $stageInfo['is_new'] == 0) {
            $stageInfo['stage_status'] = 0;
        }
        //该驿站是服务驿站
        $busniess_num = $stageModel->getBusinessNum($uid);
        $data_1 = $stageModel->upgrageInfo($uid, 0);//用户升级中的服务驿站(审核中)
        $data_2 = $stageModel->upgrageInfo($uid, 2);//用户升级不通过的服务驿站
        if ($stageInfo['type'] == 2 && $stageInfo['status'] == 1) {
            $stageInfo['stage_status'] = 0;
        } elseif ($data_1 && !$data_2 && $stageInfo['type'] == 1 && !$busniess_num) {//有升级中 无审核不通过
            foreach ($data_1 as $v) {
                if ($v['sid'] == $sid) {
                    $stageInfo['stage_status'] = 2;//审核中
                } else {
                    $stageInfo['stage_status'] = 0;
                }
            }
        } elseif ($data_2 && $data_1 && $stageInfo['type'] == 1 && !$busniess_num) { //有升级中 有审核不通过
            foreach ($data_2 as $v) {
                if ($v['sid'] == $sid) {
                    $stageInfo['stage_status'] = 3;//我知道了
                } else {
                    $stageInfo['stage_status'] = 0;
                }
            }
        } elseif ($data_2 && !$data_1 && $stageInfo['type'] == 1 && !$busniess_num) { //无升级中 有审核不通过
            foreach ($data_2 as $v) {
                if ($v['sid'] == $sid) {
                    $stageInfo['stage_status'] = 3;//我知道了
                } else {
                    $stageInfo['stage_status'] = 1;//去提交
                }
            }
        } elseif ($stageInfo['type'] == 1 && !$data_1 && !$data_2 && !$busniess_num) {//文化驿站 无升级中 无审核不通过

            $stageInfo['stage_status'] = 1;//去提交
        } else {
            $stageInfo['stage_status'] = 0;
        }
        $snsCheckModel = new SnsCheckModel();
        $stageInfo['reason'] = $snsCheckModel->get($sid, 59, 0);
        Common::appLog('stage/getIndexTop', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $stageInfo);
    }

    public function getTicketsInfoAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(6, "非法登录用户", (object)array());
        }
        $uid = $user['uid'];
        $url = $this->getRequest()->getPost('url');
        $check_type = $this->getRequest()->getPost("check_type");//1报名 2订单
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $strArr = explode('=', $url);
        $ids = array();
        foreach ($strArr as $v) {
            if (preg_match('/\d+/', $v, $arr)) {
                $ids[] = $arr[0];
            }
        }
        if (!$ids) {
            Common::echoAjaxJson(7, '抱歉，这个不是验票凭证', (object)array());
        }
        $id = $ids[0];//订单主键id
        $sid = $ids[1];//驿站id
        $eid = $ids[2];//服务信息id
        $f_id = $ids[3];//场次id
        $type = $ids[4];//1报名 2支付
        if (!$id || !$sid || !$eid || !$f_id || !$type) {
            Common::echoAjaxJson(7, '抱歉，这个不是验票凭证', (object)array());
        }
        $stageModel = new StageModel();
        $eventModel = new EventModel();
        $num = $stageModel->verifyBusiness($sid, $uid);
        if (!$num) {
            Common::echoAjaxJson(2, '你没有验票的权限', (object)array());
        }
        if ($check_type == 1 && $type == 2) {
            Common::echoAjaxJson(10, '请至【我的订单】-【我卖出的】-【我发布的收费活动】进行验票', (object)array());
        }
        if ($check_type == 2 && $type == 1) {
            Common::echoAjaxJson(10, '请至【我的订单】-【我卖出的】-【我发布的免费活动】进行验票', (object)array());
        }
        $fields_info = $eventModel->getFieldsInfo($f_id);
        if ($fields_info['status'] == 0 || $fields_info['partake_end_time'] >= $fields_info['end_time'] || date('Y-m-d H:i', time()) >= $fields_info['end_time']) {
            Common::echoAjaxJson(3, '抱歉，该凭证已失效', (object)array());
        }
        if ($type == 1) {
            $pInfo = $eventModel->getPartakeInfoById($id);
            if ($pInfo['status'] > 1) {
                Common::echoAjaxJson(3, '抱歉，该凭证已失效', (object)array());
            }
            if ($pInfo['is_check'] == 1) {
                Common::echoAjaxJson(9, '此票已使用', (object)array());
            }
            $info['add_time'] = date('Y-m-d H:i', strtotime($pInfo['add_time']));
        }
        if ($type == 2) {
            $qrcodeInfo = $eventModel->getQrcodeById($id);
            $orderInfo = $eventModel->orderInfoById($qrcodeInfo['o_id']);
            if (!$orderInfo || $orderInfo['order_status'] != 2) {
                Common::echoAjaxJson(4, '抱歉，该订单已不存在', (object)array());
            }
            if ($qrcodeInfo['is_check'] == 1) {
                Common::echoAjaxJson(9, '此票已使用', (object)array());
            }
            $info['add_time'] = date('Y-m-d H:i', strtotime($orderInfo['add_time']));
        }
        $eventInfo = $eventModel->getEvent($eid);
        $info['title'] = $eventInfo['title'];
        $info['start_time'] = date('Y-m-d H:i', strtotime($fields_info['start_time']));
        Common::appLog('stage/getTicketsInfo', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $info);
    }

    //才府首页点击驿站
    public function indexStageViewAction()
    {
        $data['token'] = $this->getRequest()->get('token');
        $uid = 0;
        if ($data['token']) {
            $user = Common::isLogin($data);
            if (!$user) {
                Common::echoAjaxJson(2, "非法登录用户");
            }
            $uid = $user['uid'];
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $list = array();
        $stageModel = new StageModel();
        $type = $stageModel->getCultureCateList();
        if ($type) {
            foreach ($type as $k => $v) {
                $list[$k]['cate_id'] = $v['id'];
                $list[$k]['cate_name'] = $v['name'];
                $list[$k]['cover'] = $v['cover'];
                $stage_list = $stageModel->indexStageViewList($v['id'], $uid);
                $list[$k]['stage_list'] = $stage_list ? $stage_list : array();
            }
        }
        Common::appLog('stage/indexStageView', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $list);
    }

    public function isJoinAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $uid = $user['uid'];
        $sid = $this->getRequest()->getPost('sid');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if (!$sid) {
            Common::echoAjaxJson(2, "驿站id为空");
        }
        $stageModel = new StageModel();
        $is_join = $stageModel->isJoinStage($sid, $uid);
        Common::appLog('stage/isJoin', $this->startTime, $version);
        Common::echoAjaxJson(1, '获取成功', $is_join);
    }

    //获取分享有奖管理数据
    public function getSetCommissionListAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->get('version') : APP_VERSION;
        $size = 20; //条数
        $page = $this->getRequest()->getPost("page") ? $this->getRequest()->getPost("page") : 1; //页数
        $type = $this->getRequest()->getPost('type');//10活动 12商品
        $stageModel = new StageModel();
        $list = $stageModel->getSetCommissionList($uid, $type, $page, $size, $_POST['token'], $version);
        Common::appLog('stage/getSetCommissionList', $this->startTime, $version);
        Common::echoAjaxJson(1, "获取成功", $list ? $list : array());

    }

    //获取能设置的佣金的数据
    public function getIsSetCommissionListAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->get('version') : APP_VERSION;
        $size = 20; //条数
        $page = $this->getRequest()->getPost("page") ? $this->getRequest()->getPost("page") : 1; //页数
        $type = $this->getRequest()->getPost('type');//10活动 12商品
        $stageModel = new StageModel();
        $list = $stageModel->getIsSetCommissionList($uid, $type, $page, $size, $_POST['token'], $version);
        Common::appLog('stage/getIsSetCommissionList', $this->startTime, $version);
        Common::echoAjaxJson(1, "获取成功", $list ? $list : array());
    }

    //驿站管理中心
    public function stageManagerAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $uid = $user['uid'];
        $sid = $this->getRequest()->getPost('sid');
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->get('version') : APP_VERSION;
        $stageModel = new StageModel();
        $stagegoodsModel = new StagegoodsModel();
        if ($sid) {
            $stage_info = $stageModel->getBasicStageBySid($sid);
            $data['sid'] = $sid;
            $data['stage_name'] = $stage_info['name'];
            $data['icon'] = $stage_info['icon'];
            $data['cover'] = $stage_info['cover'];
            $data['type'] = $stage_info['type'];
            $data['is_extend'] = $stage_info['is_extend'];
            $data['no_pay'] = $stagegoodsModel->getSellOrderListNum($sid, 1);//待付款
            $data['no_send'] = $stagegoodsModel->getSellOrderListNum($sid, 2);//待发货
            $data['is_send'] = 0;
            $data['user_num'] = $stage_info['user_num'];
            $data['topic_num'] = $stage_info['topic_num'];
            $data['qrcode_img'] = $stage_info['qrcode_img'];
            $data['is_sp_agreement'] = $stage_info['is_sp_agreement'];
            $data['stage_role'] = $stageModel->joinStageRole($sid, $uid) ? $stageModel->joinStageRole($sid, $uid) : (object)array();
            Common::echoAjaxJson(1, "进入驿站管理", $data);
        } else {
            $creat_p_1 = $stageModel->getCreatePstatus($uid, 1);//是否有创建成功的普通驿站
            $creat_p_0 = $stageModel->getCreatePstatus($uid, 0);//是否有审核中的普通驿站
            $creat_p_2 = $stageModel->getCreatePstatus($uid, 2);//是否有审核不通过的普通驿站
            $creat_f = $stageModel->getCreateBstatus($uid);//是否创建过服务驿站及状态
            //未创建驿站
            if (!$creat_p_1 && !$creat_p_0 && !$creat_p_2 && !$creat_f) {
                Common::echoAjaxJson(5, "您还没有服务驿站，创建服务驿站，可以进入卖家中心");
            }
            //已成功创建【服务 驿站】
            if ($creat_f && $creat_f['status'] == 1) {
                $s_id = $stageModel->getSidByUid($uid);
                $stage_info = $stageModel->getBasicStageBySid($s_id['sid']);
                $data['sid'] = $s_id['sid'];
                $data['stage_name'] = $stage_info['name'];
                $data['icon'] = $stage_info['icon'];
                $data['cover'] = $stage_info['cover'];
                $data['type'] = $stage_info['type'];
                $data['is_extend'] = $stage_info['is_extend'];
                $data['no_pay'] = $stagegoodsModel->getSellOrderListNum($s_id['sid'], 1);//待付款
                $data['no_send'] = $stagegoodsModel->getSellOrderListNum($s_id['sid'], 2);//待发货
                $data['is_send'] = 0;
                $data['user_num'] = $stage_info['user_num'];
                $data['topic_num'] = $stage_info['topic_num'];
                $data['qrcode_img'] = $stage_info['qrcode_img'];
                $data['is_sp_agreement'] = $stage_info['is_sp_agreement'];
                $data['stage_role'] = $stageModel->joinStageRole($s_id['sid'], $uid) ? $stageModel->joinStageRole($s_id['sid'], $uid) : (object)array();
                Common::echoAjaxJson(1, "可以进入卖家中心", $data);
            }
            //【服务驿站】正在 审核中
            if ($creat_f && $creat_f['status'] == 0) {
                Common::echoAjaxJson(7, "您的服务驿站正在审核中，审核通过后，可进入卖家中心，请您耐心等待。");
            }
            //【服务驿站】创建 未通过
            if ($creat_f && $creat_f['status'] == 2) {
                Common::echoAjaxJson(8, "您所创建的服务驿站" . $creat_f['name'] . "审核未通过，审核通过后，可进入卖家中心", $creat_f['sid']);
            }
            //已成功创建【普通 驿站】
            if (!$creat_f && $creat_p_1) {
                Common::echoAjaxJson(9, "您的驿站未开通服务功能，提交资料，升级为服务驿站，方可进入卖家中心");
            }
            //【普通 驿站】开通服务功能但审核不通过
//            if(!$creat_f&&$creat_p_1&&$creat_p_3){
//                //Common::echoAjaxJson(9, "您的驿站未开通服务功能，提交资料，升级为服务驿站，可拥有发".$messgae."权限。");
//            }
            //【普通驿站】创建 未通过
            if (!$creat_f && !$creat_p_1 && !$creat_p_0 && $creat_p_2) {
                Common::echoAjaxJson(10, "您创建的普通驿站审核未通过。 审核通过后，才能开通服务功能，方可进入卖家中心");
            }
            //【普通驿站】正在 审核中
            if (!$creat_f && !$creat_p_1 && $creat_p_0) {
                Common::echoAjaxJson(11, "您的普通驿站正在审核中，普通驿站审核通过后，才能开通服务功能，进入卖家中心");
            }
        }
    }

    //驿站同意推广协议
    public function spAgreementAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $uid = $user['uid'];
        $stageModel = new StageModel();
        $sid = $stageModel->getSidByUid($uid);
        $rs = $stageModel->spAgreement($sid['sid']);
        if (!$rs) {
            Common::echoAjaxJson(2, "操作失败");
        }
        Common::echoAjaxJson(1, "操作成功");
    }

    //驿站分享明细列表
    public function getStageSpListAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->get('version') : APP_VERSION;
        $type = $this->getRequest()->getPost('type') ? $this->getRequest()->getPost('type') : 0;//0全部 10活动 12商品
        $page = $this->getRequest()->getPost('page') ? $this->getRequest()->getPost('page') : 1;
        $size = 10;
        $stageModel = new StageModel();
        $stagegoodsModel = new StagegoodsModel();
        $eventModel = new EventModel();
        $sid = $stageModel->getSidByUid($uid);
        $goods_totals = $stagegoodsModel->getGoodsSpCommissionTotals($sid['sid']);
        $event_totals = $eventModel->getEventSpCommissionTotals($sid['sid']);
        $list['price_totals'] = $goods_totals['price_totals'] + $event_totals['price_totals'];
        $list['commission_totals'] = $goods_totals['commission_totals'] + $event_totals['commission_totals'];
        $list['list'] = $stageModel->getStageSpList($sid['sid'], $page, $size, $type);
        Common::appLog('stage/getStageSpList', $this->startTime, $version);
        Common::echoAjaxJson(1, "获取成功", $list);
    }

    //驿站下某一个商品或者活动分享明细列表
    public function getOneSpListAction()
    {
        $user = Common::isLogin($_POST);
        if (!$user) {
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->get('version') : APP_VERSION;
        $type = $this->getRequest()->getPost('type') ? $this->getRequest()->getPost('type') : 0;//0全部 10活动 12商品
        $page = $this->getRequest()->getPost('page') ? $this->getRequest()->getPost('page') : 1;
        $obj_id = $this->getRequest()->getPost('obj_id');
        $size = 10;
        if (!$obj_id) {
            Common::echoAjaxJson(2, "对象id为空");
        }
        if (!$type || !in_array($type, array(10, 12))) {
            Common::echoAjaxJson(3, "类型为空");
        }
        $stageModel = new StageModel();
        $sid = $stageModel->getSidByUid($uid);
        $list = $stageModel->getOneSpList($sid, $obj_id, $page, $size, $type, $_POST['token'], $version);
        Common::appLog('stage/getOneSpList', $this->startTime, $version);
        Common::echoAjaxJson(1, "获取成功", $list);
    }

}
