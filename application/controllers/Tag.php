<?php

class TagController extends Yaf_Controller_Abstract
{
    private $type_arr = array(1,2,3,4,5);
    public function init()
    {
        $this->startTime = microtime(true);
    }

    /**
     * 公用标签添加
     * content 标签内容  type 标签类型  type_cate 代代文化分类id
     */
    public function addAction()
    {
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(7, "非法登录用户");
        }
        $content = $this->getRequest()->getPost('content');
        $type = (int)$this->getRequest()->getPost('type');
        $type_cate = (int)$this->getRequest()->getPost('type_cate');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$type || !in_array($type,$this->type_arr)){
            Common::echoAjaxJson(2, "标签类型不正确");
        }
        if($type == 2 && $type_cate < 1){
            Common::echoAjaxJson(3, "请输入文化分类");
        }
        if($type == 5 && $type_cate < 1){
            Common::echoAjaxJson(3,"信息分类不能为空");
        }
        if(!$type_cate){
            $type_cate = 0;
        }
        if (!trim($content)){
            Common::echoAjaxJson(4, "标签不能为空");
        }
        if (mb_strlen($content, 'utf-8') > 7) {
            Common::echoAjaxJson(5, '标签长度不能超过7个字符');
        }
        $tagModel = new TagModel();
        $rs = $tagModel->addTag($content,$type,$type_cate);
        if ($rs < 1) {
            Common::echoAjaxJson(6, "标签添加失败");
        }
        Common::appLog('tag/add',$this->startTime,$version);
        Common::echoAjaxJson(1, '标签添加成功', $rs);
    }

    /**
     * 保存标签关联关系
     * type 标签类型  tag标签id
     */
    public function saveRelationAction()
    {
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(7, "非法登录用户");
        }
        $uid = $user['uid'];
        $type = $this->getRequest()->getPost('type');
        $relation_id = $this->getRequest()->getPost('id');
        $tag = $this->getRequest()->getPost('tag');//多个用&连接
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $tag_arr = explode('&',$tag);
        if($type == 1){
            $relation_id = $uid;
        }
        if(!$type || !in_array($type,$this->type_arr)){
            Common::echoAjaxJson(2, "标签类型不正确");
        }
        if($type != 1 && !$relation_id){
            Common::echoAjaxJson(3, "标签关联关系id不能为空");
        }
        if (!$tag_arr){
            Common::echoAjaxJson(4, "至少保留一个标签");
        }
        if (count($tag_arr) > 6) {
            Common::echoAjaxJson(5, '标签不能超过6个');
        }
        $tagModel = new TagModel();
        $tag_arr = array_unique($tag_arr);
        $rs = $tagModel->saveRelation($type,$relation_id,$tag_arr);
        if ($rs == 0) {
            Common::echoAjaxJson(6, '保存标签失败');
        }
        Common::appLog('tag/saveRelation',$this->startTime,$version);
        Common::echoAjaxJson(1, '保存标签成功');
    }

    /**
     *删除关联关系标签
     */
    public function delRelationAction()
    {
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $id = $this->getRequest()->getPost('id');
        $type = (int)$this->getRequest()->getPost('type');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2, "删除的标签不能为空");
        }
        if(!$type || !in_array($type,$this->type_arr)){
            Common::echoAjaxJson(3, "标签类型不正确");
        }
        $tagModel = new TagModel();
        $rs = $tagModel->delRelation($type,$id);
        if ($rs == -1) {
            Common::echoAjaxJson(4, "删除数据失败");
        }
        Common::appLog('tag/delRelation',$this->startTime,$version);
        Common::echoAjaxJson(1, '标签删除成功');
    }

    /**
     *根据标签类型id和代代文化分类id查询官方推荐标签
     */
    public function getTagListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $type = (int)$this->getRequest()->getPost('type');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$type || !in_array($type,$this->type_arr)){
            Common::echoAjaxJson(2, "标签类型不正确");
        }
        $tagModel = new TagModel();
        $list = $tagModel->listTag($type,0);
        Common::appLog('tag/getTagList',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$list ? $list : array());
    }
    //APP兴趣弹层标签
    public function getListByUserPushAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $tagModel = new TagModel();
        $list = $tagModel->getListByUserPush(1,0);
        Common::appLog('tag/getListByUserPush',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$list ? $list : array());
    }
}