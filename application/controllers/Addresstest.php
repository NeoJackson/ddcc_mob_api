<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 16-8-1
 * Time: 上午10:45
 */
class AddresstestController extends Yaf_Controller_Abstract{

    public function addShippingAction(){
        $parameters = array(
            'token' =>'16275521ceefd087f02d4e7a117a2be7',
            'consignee_name' => '是否默认',
            'detail_address' => '1234343434',
            'phone'=>'13613613613',
            'is_default'=>0,
            'town_id'=>'820769'
        );
        Common::verify($parameters, '/address/addShipping');
    }
    public function delShippingAction(){
        $parameters = array(
            'token' =>'16275521ceefd087f02d4e7a117a2be7',
            'address_id' => '171',
        );
        Common::verify($parameters, '/address/delShipping');
    }
    public function setDefaultShippingAction(){
        $parameters = array(
            'token' =>'16275521ceefd087f02d4e7a117a2be7',
            'address_id' => '172',
        );
        Common::verify($parameters, '/address/setDefaultShipping');
    }
    public function getShippingAction(){
        $parameters = array(
            'token' =>'16275521ceefd087f02d4e7a117a2be7',
            'address_id' => '172',
        );
        Common::verify($parameters, '/address/getShipping');
    }
    public function getShippingListAction(){
        $parameters = array(
            'token' =>'16275521ceefd087f02d4e7a117a2be7',
        );
        Common::verify($parameters, '/address/getShippingList');
    }

    public function modifyShippingAction(){
        $parameters = array(
            'token' =>'16275521ceefd087f02d4e7a117a2be7',
            'address_id'=>'172',
            'consignee_name' => '老孙改',
            'detail_address' => '浦东南路855号世界广场9楼D座',
            'phone'=>'13613613623',
            'is_default'=>0,
            'town_id'=>'820769'
        );
        Common::verify($parameters, '/address/modifyShipping');
    }
    public function getDefaultShippingAction(){
        $parameters = array(
            'token' =>'16275521ceefd087f02d4e7a117a2be7',
        );
        Common::verify($parameters, '/address/getDefaultShipping');
    }
}