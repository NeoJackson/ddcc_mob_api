<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 16-8-1
 * Time: 上午10:45
 */
class AddressController extends Yaf_Controller_Abstract {
    public function init(){
        $this->startTime = microtime(true);
    }
    /*
 * @name 增加用户收货地址
 */
    public function addShippingAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(16, "非法登录用户");
        }
        $uid = $user['uid'];
        $consignee_name = $this->getRequest()->getPost('consignee_name'); //收货人姓名
        $town_id = $this->getRequest()->getPost('town_id');
        $detail_address = $this->getRequest()->getPost('detail_address'); //详细地址
        $phone = $this->getRequest()->getPost('phone'); //联系电话
        $is_default = $this->getRequest()->getPost('is_default'); //设置默认地址 0普通 1为默认
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;//版本号
        if(!$consignee_name){
            Common::echoAjaxJson(3,'请输入收货人姓名');
        }
        $consignee_name_num = mb_strlen($consignee_name,'utf-8');
        if($consignee_name_num <= 1 || $consignee_name_num > 6){
            Common::echoAjaxJson(4,'收货人姓名必须2-6个字符');
        }
        $security = new Security();
        $consignee_name = $security->xss_clean($consignee_name);
        if(!$town_id){
            Common::echoAjaxJson(5,'选择地区不完整');
        }
        if(!$detail_address){
            Common::echoAjaxJson(6,'请输入详细地址');
        }
        $detail_address_num = mb_strlen($detail_address,'utf-8');
        if($detail_address_num < 5 || $detail_address_num > 40){
            Common::echoAjaxJson(7,'详细地址必须5-40个字符');
        }
        $detail_address = $security->xss_clean($detail_address);

        if(!$phone){
            Common::echoAjaxJson(8,'请输入手机号码');
        }
        if(!preg_match('/^1[0-9]{10}$/',$phone)){
            Common::echoAjaxJson(9,'请输入正确的11位手机号');
        }
        $is_default_arr = array(0,1);
        if(!in_array($is_default,$is_default_arr)){
            Common::echoAjaxJson(10,'只能是默认或不是默认地址');
        }

        $addressModel = new AddressModel();
        $rs = $addressModel->addShipping($uid,$consignee_name,$town_id,$detail_address,$phone,$is_default);
        if($rs == -1){
            Common::echoAjaxJson(11,'用户不存在');
        }
        if($rs == -2){
            Common::echoAjaxJson(12,'没有找到对应城市');
        }
        if($rs == -3){
            Common::echoAjaxJson(13,'没有找到对应的省份');
        }
        if($rs == -4){
            Common::echoAjaxJson(14,'收货地址不能超过10个');
        }
        if($rs == 0){
            Common::echoAjaxJson(15,'增加收货地址失败');
        }
        Common::appLog('address/addShipping',$this->startTime,$version);
        Common::echoAjaxJson(1,'增加收货地址成功',$rs);
    }
    /*
     * @name 删除收货地址
     */
    public function delShippingAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $uid = $user['uid'];
        $address_id = $this->getRequest()->getPost('address_id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") :APP_VERSION;//版本号
        if(!$address_id){
            Common::echoAjaxJson(2,'收货地址ID为空');
        }
        $addressModel = new AddressModel();
        $rs = $addressModel->delShipping($uid,$address_id);
        if($rs == -1){
            Common::echoAjaxJson(3,'用户不存在');
        }
        if($rs == 0){
            Common::echoAjaxJson(4,'删除收货地址失败');
        }
        //$addressModel->setLastDefault($uid);
        Common::appLog('address/delShipping',$this->startTime,$version);
        Common::echoAjaxJson(1,'删除收货地址成功');
    }
    /*
     * @name 收货地址设为默认
     */
    public function setDefaultShippingAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $uid = $user['uid'];
        $address_id = $this->getRequest()->getPost('address_id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") :APP_VERSION;//版本号
        if(!$address_id){
            Common::echoAjaxJson(2,'收货地址ID为空');
        }
        $addressModel = new AddressModel();
        $rs = $addressModel->setDefaultShipping($uid,$address_id);
        if($rs == -1){
            Common::echoAjaxJson(3,'用户不存在');
        }
        if($rs == 0){
            Common::echoAjaxJson(4,'设置默认收货地址失败');
        }
        Common::appLog('address/setDefaultShipping',$this->startTime,$version);
        Common::echoAjaxJson(1,'设置默认收货地址成功');
    }
    //获取某一条收货地址
    public function getShippingAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $uid = $user['uid'];
        $address_id = (int)$this->getRequest()->getPost('address_id');
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") :APP_VERSION;//版本号
        if(!$address_id){
            Common::echoAjaxJson(2,'请指明收货地址',array());
        }
        $addressModel = new AddressModel();
        $shipping = $addressModel->getShippingById($uid,$address_id);
        if(!$shipping){
            Common::echoAjaxJson(3,'系统没有匹配到您的地址',array());
        }
        Common::appLog('address/getShipping',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$shipping);
    }
    //用户收货地址列表
    public function getShippingListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") :APP_VERSION;//版本号
        $addressModel = new AddressModel();
        $list = $addressModel->listShipping($uid);
        Common::appLog('address/getShippingList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    //修改收货地址
    public function modifyShippingAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(16, "非法登录用户");
        }
        $uid = $user['uid'];
        $address_id = $this->getRequest()->getPost('address_id');
        $consignee_name = $this->getRequest()->getPost('consignee_name'); //收货人姓名
        $town_id = $this->getRequest()->getPost('town_id');
        $detail_address = $this->getRequest()->getPost('detail_address'); //详细地址
        $phone = $this->getRequest()->getPost('phone'); //联系电话
        $is_default = $this->getRequest()->getPost('is_default'); //设置默认地址 0未普通 1为默认
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") :APP_VERSION;//版本号
        if(!$address_id){
            Common::echoAjaxJson(2,'请选择需要修改的地址');
        }
        if(!$consignee_name){
            Common::echoAjaxJson(3,'请输入收货人姓名');
        }
        $consignee_name_num = mb_strlen($consignee_name,'utf-8');
        if($consignee_name_num <= 1 || $consignee_name_num > 6){
            Common::echoAjaxJson(4,'收货人姓名为2-6个字符');
        }
        $security = new Security();
        $consignee_name = $security->xss_clean($consignee_name);
        if(!$town_id){
            Common::echoAjaxJson(5,'选择地址不完整');
        }
        if(!$detail_address){
            Common::echoAjaxJson(6,'请输入详细地址');
        }
        $detail_address_num = mb_strlen($detail_address,'utf-8');
        if($detail_address_num < 5 || $detail_address_num > 40){
            Common::echoAjaxJson(7,'详细地址为5-40个字符');
        }
        $detail_address = $security->xss_clean($detail_address);
        if(!$phone){
            Common::echoAjaxJson(8,'请输入手机号码');
        }
        if(!preg_match('/^1[0-9]{10}$/',$phone)){
            Common::echoAjaxJson(9,'请输入正确的手机号');
        }
        $is_default_arr = array(0,1);
        if(!in_array($is_default,$is_default_arr)){
            Common::echoAjaxJson(10,'只能是默认或不是默认地址');
        }

        $addressModel = new AddressModel();
        $rs = $addressModel -> modifyShipping($uid,$address_id,$consignee_name,$town_id,$detail_address,$phone,$is_default);
        if($rs == -1){
            Common::echoAjaxJson(12,'用户不存在');
        }
        if($rs == -2){
            Common::echoAjaxJson(13,'没有找到对应城市');
        }
        if($rs == -3){
            Common::echoAjaxJson(14,'没有找到对应的省份');
        }
        if($rs == 0){
            Common::echoAjaxJson(15,'修改收货地址失败');
        }
        Common::appLog('address/modifyShipping',$this->startTime,$version);
        Common::echoAjaxJson(1,'修改收货地址成功');
    }
    //获取用户默认收货地址
    public function getDefaultShippingAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") :APP_VERSION;//版本号
        $addressModel = new AddressModel();
        $rs = $addressModel->getDefaultShipping($uid);
        if(!$rs){
            Common::echoAjaxJson(2, "您没有设置默认收货地址");
        }
        Common::appLog('address/getDefaultShipping',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$rs);
    }
}