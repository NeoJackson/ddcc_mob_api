<?php

class ReportController extends Yaf_Controller_Abstract
{
    public function init(){
        $this->reType = array('1','2','3','4','6','7','8','9','10','11','12','13','14');//举报类型:1心境 2照片 3日志 4帖子 6许愿 7缅怀祭拜留言 8拜佛祈福 9分享 10驿站留言 11评论  12 举报人
        $this->startTime = microtime(true);
    }
    /*
     * 显示举报分类
     */
    public function selectReportAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(6, "非法登录用户");
        }
        $uid = $user['uid'];
        $reType = intval($this->getRequest()->get('type'));//举报类型
        $reObjId = intval($this->getRequest()->getPost('obj_id'));//举报对象ID
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!in_array($reType,$this->reType)){
            Common::echoAjaxJson(2,'举报类型不正确');
        }
        if(!$reObjId || $reObjId < 1){
            Common::echoAjaxJson(3,'举报对象ID不能为空');
        }
        $report=new ReportModel();
        $selectReport=$report->selectReport($uid,$reType,$reObjId);
        if($selectReport == 0){
            Common::echoAjaxJson(4, '您已举报');
        }
        $ReportType=$report->seReportReason($uid);
        if(!$ReportType){
            Common::echoAjaxJson(5, '获取举报分类失败');
        }
        $ReportType['type']=$reType;
        $ReportType['odj_id']=$reObjId;
        Common::appLog('report/selectReport',$this->startTime,$version);
        Common::echoAjaxJson(1, '获取举报分类成功',$ReportType);
    }
    /*
     *提交举报表单
     */
    public function addReportAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(9, "非法登录用户");
        }
        $uid = $user['uid'];
        $reType = intval($this->getRequest()->get('type'));//举报类型
        $reObjId = intval($this->getRequest()->getPost('obj_id'));//举报对象ID
        $reRid  = intval($this->getRequest()->getPost('rid'));//举报原因分类ID
        $reDetails        = $this->getRequest()->getPost('details');//举报说明·
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!in_array($reType, $this->reType)){
            Common::echoAjaxJson(2,'举报类型不正确');
        }
        if(!$reObjId || $reObjId < 1){
            Common::echoAjaxJson(3,'举报对象ID不能为空');
        }
        if(!$reRid || $reRid < 1){
            Common::echoAjaxJson(4,'请选择举报原因');
        }
        if($reDetails){
            $security = new Security();
            $reDetails = $security->xss_clean($reDetails);
            $reDetails = Common::angleToHalf($reDetails);
            $reDetails = Common::contentSpace($reDetails);//连续空格处理单一空格
            if(preg_match('/[A-Za-z]{1,}/',$reDetails)){
                Common::echoAjaxJson(5,'具体说明不能含有英文字符');
            }
            $reDetailsNum = mb_strlen($reDetails,'utf-8');
            if($reDetailsNum > 200){
                Common::echoAjaxJson(6,'举报说明不能超过200个字符');
            }
        }
        $report=new ReportModel();
        $selectReport=$report->selectReport($uid,$reType,$reObjId);
        if($selectReport == 0){
            Common::echoAjaxJson(7, '您已举报');
        }
        $aid = $report->addReport($uid,$reType,$reObjId,$reRid,$reDetails);
        if($aid == 0){
            Common::echoAjaxJson(8, '举报失败');
        }
        Common::appLog('report/addReport',$this->startTime,$version);
        Common::echoAjaxJson(1, '举报成功，才府将尽快处理');
    }
}