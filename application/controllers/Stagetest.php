<?php

class StagetestController extends Yaf_Controller_Abstract
{
    //获取我加入的驿站列表
    public function joinListAction()
    {
        $parameters = array(
            'token' => 'f4b32c7db5b5171fe38a7f7153b7ec09',
            'type' => 2
        );
        Common::verify($parameters, '/stage/joinList');
    }

    //测试添加商户驿站接口
    public function addBusinessAction()
    {
        $parameters = array(
            'token' => '48f765b2287e44f7b16646914ca4beed',
            'name' => '老孙测试我的的',
            'cate_id' => '15',
            'type' => 1,
            'origin' => '3',
            'license_number' => '123456789012345',
            'identity_img' => '14207802169963.png',
            'license_img' => '14207804147451.png',
            'address' => '上海顾村公园',
            'lng' => '121.38596',
            'lat' => '31.348727',
            'contacts' => '路人甲',
            'mobile' => '15951829621',
        );
        Common::verify($parameters, '/stage/addBusiness');
    }

    //获取商家驿站状态
    public function getStatusAction()
    {
        $parameters = array(
            'token' => '5298600e41e657cfb82b8273dc7d1086',
        );
        Common::verify($parameters, '/stage/getStatus');
    }

    //完善商家驿站资料接口
    public function modifyBusinessAction()
    {
        $parameters = array(
            'token' => 'e550250ce7c5494e46c73ebf4fa7ef33',
            'icon' => '14472390059007.jpg',
            'sid' => '1159',
            'intro' => '还记得是个复合接口的风格梵蒂冈发撒点粉',
            'type' => 1

        );
        Common::verify($parameters, '/stage/modifyBusiness');
    }

    //测试获取驿站分类接口
    public function getCultureCateAction()
    {
        $parameters = array(
            'token' => 'f4b32c7db5b5171fe38a7f7153b7ec09'
        );
        Common::verify($parameters, '/stage/getCultureCate');
    }

    //测试获取驿站分类接口
    public function getAllCultureCateAction()
    {
        $parameters = array(
            'token' => 'f4b32c7db5b5171fe38a7f7153b7ec09'
        );
        Common::verify($parameters, '/stage/getAllCultureCate');
    }


    //测试用户加入驿站接口
    public function joinAction()
    {
        $parameters = array(
            'token' => '697e5928067ec1aeb481865074a9a10e',  // uid=12476
            'sid' => '793'
        );
        Common::verify($parameters, '/stage/join');
    }

    //测试用户退出驿站接口
    public function exitAction()
    {
        $parameters = array(
            'token' => '697e5928067ec1aeb481865074a9a10e',
            'sid' => '793'
        );
        Common::verify($parameters, '/stage/exit');
    }

    public function exitCheckAction()
    {
        $parameters = array(
            'token' => '048e25761b804ab794516fc841ad70ef',
            'sid' => '1379'
        );
        Common::verify($parameters, '/stage/exitCheck');
    }

    //获取商家驿站信息接口
    public function getInfoAction()
    {
        $parameters = array(
            'token' => '8bda20b5ebb50b4c1448da2a959ccf46',
        );
        Common::verify($parameters, '/stage/getInfo');
    }

    //修改商家驿站审核信息
    public function updateBusinessAction()
    {
        $parameters = array(
            'token' => '516bb6113141811b4fcb1ab3c1fb9ea7',
            'sid' => '810',
            'name' => '我的小学',
            'cate_id' => '2',
            'license_number' => '123456789012345',
            'license_img' => '14317626028672.jpg',
            'identity_img' => '14317626032093.jpg',
            'address' => '世纪大道1090号',
            'lng' => '121.520334',
            'lat' => '31.230624',
            'contacts' => '张三',
            'mobile' => '13611111111',
        );
        Common::verify($parameters, '/stage/updateBusiness');
    }

    //设置商家驿站角色成员
    public function setMerchantMemberRoleAction()
    {
        $parameters = array(
            'token' => 'f4b32c7db5b5171fe38a7f7153b7ec09',
            'sid' => 556,
            'uid' => 3480,
            'role' => 2
        );
        Common::verify($parameters, '/stage/setMerchantMemberRole');
    }

    //商家驿站统计
    public function statisticsAction()
    {
        $parameters = array(
            'token' => 'f4b32c7db5b5171fe38a7f7153b7ec09',
            'sid' => 564,
            'type' => 2
        );
        Common::verify($parameters, '/stage/statistics');
    }

    //用户的驿站列表
    public function getStageListByCateIdAction()
    {
        $parameters = array(
            'token' => 'f4b32c7db5b5171fe38a7f7153b7ec09',
            'cate_id' => 2
        );
        Common::verify($parameters, '/stage/getStageListByCateId');
    }

    //最近访问
    public function visitorAction()
    {
        $parameters = array(
            'token' => 'cf316c5508e1bc3c6b0d865bebaf1585',
        );
        Common::verify($parameters, '/stage/visitor');
    }

    //我的驿站--动态(优秀图贴)
    public function goodTopicListAction()
    {
        $parameters = array(
            'token' => '7420cafb38c40d50e3791e9df793fa02',
            'last_id' => 0
        );
        Common::verify($parameters, '/stage/goodTopicList');
    }

    //推荐驿站列表
    public function recommendStageListAction()
    {
        $parameters = array(
            'token' => '57d47f59d979b6d919c788bff81432cc',
            'size' => 100
        );
        Common::verify($parameters, '/stage/recommendStageList');
    }

    //用户删除驿站图片
    public function updateBusinessCoverAction()
    {
        $parameters = array(
            'token' => '25bf67b91ce56abc17112178224f3da4',
            'cover' => "http://img.91ddcc.com/14383221317306.jpg?imageView2/2/w/540/h/540",
        );
        Common::verify($parameters, '/stage/updateBusinessCover');
    }

    //获取推荐驿站标签
    public function getPushStageTagAction()
    {
        $parameters = array(
            'token' => 'f4b32c7db5b5171fe38a7f7153b7ec09',
        );
        Common::verify($parameters, '/stage/getPushStageTag');
    }

    //获取标签下的商家驿站
    public function getBusinessByTagIdAction()
    {
        $parameters = array(
            'token' => '32d2d3c3270089974d39b61c75237571',
            'tag_id' => '139'
        );
        Common::verify($parameters, '/stage/getBusinessByTagId');
    }

    //最近访问
    public function viewStageAction()
    {
        $parameters = array(
            'token' => 'b7e7e0541d6aeafb3e4315b82e1ab31b',
            'sid' => '564'
        );
        Common::verify($parameters, '/stage/viewStage');
    }

    //最近访问
    public function updateStatusAction()
    {
        $parameters = array(
            'token' => 'ff810602ed429521b62905c04a562356'
        );
        Common::verify($parameters, '/stage/updateStatus');
    }

    //驿站发表留言
    public function addStageMessageAction()
    {
        $parameters = array(
            'token' => '54d7ed519214c24e93463c39585106bc',
            'sid' => 595,
            'content' => '好好红啊'
        );
        Common::verify($parameters, '/stage/addStageMessage');
    }

    //驿站发表留言
    public function deleteStageMessageAction()
    {
        $parameters = array(
            'token' => '54d7ed519214c24e93463c39585106bc',
            'sid' => 59,
            'id' => 598
        );
        Common::verify($parameters, '/stage/deleteStageMessage');
    }

    //驿站留言列表
    public function getMessageListAction()
    {
        $parameters = array(
            'token' => '54d7ed519214c24e93463c39585106bc',
            'sid' => 595
        );
        Common::verify($parameters, '/stage/getMessageList');
    }

    //驿站信息
    public function stageManageAction()
    {
        $parameters = array(
            //'token' =>'2fe0998ae0696e49372a60261daaf3c3',
            'sid' => 1372
        );
        Common::verify($parameters, '/stage/stageManage');
    }

    //驿站成员
    public function stageMemberAction()
    {
        $parameters = array(
            'token' => '9f2823873c4cec9e56ec8002d1dd1262',
            'sid' => 1379
        );
        Common::verify($parameters, '/stage/stageMember');
    }

    //驿站成员加载
    public function stageMemberMoreAction()
    {
        $parameters = array(
            'token' => '54d7ed519214c24e93463c39585106bc',
            'sid' => 11,
            'size' => 50
        );
        Common::verify($parameters, '/stage/stageMemberMore');
    }

    //驿站首页
    public function indexAction()
    {
        $parameters = array(
            'token' => 'cbdc467d29836ec5f94773b29d34b5e3',
            'sid' => 1379,
            'version' => '3.0'
        );
        Common::verify($parameters, '/stage/index');
    }

    //驿站首页加载
    public function indexMoreAction()
    {
        $parameters = array(
            'sid' => 564,
            'page' => 2,
            'size' => 10
        );
        Common::verify($parameters, '/stage/indexMore');
    }

    //设置驿站封面
    public function setCoverAction()
    {
        $parameters = array(
            'token' => 'f4b32c7db5b5171fe38a7f7153b7ec09',
            'sid' => 564,
            'cover' => '14214005966201.jpg'
        );
        Common::verify($parameters, '/stage/setCover');
    }

    //设置驿站封面
    public function updateIconAction()
    {
        $parameters = array(
            'token' => '445c49f09d58e0d6b0dbbe40918acdb9',
            'sid' => 52,
            'icon' => '14214005966201.jpg'
        );
        Common::verify($parameters, '/stage/updateIcon');
    }

    //驿站服务

    public function serviceAction()
    {
        $parameters = array(
            'token' => '54d7ed519214c24e93463c39585106bc',
            'sid' => 564,
            'page' => 1,
            'size' => 10
        );
        Common::verify($parameters, '/stage/service');
    }

    public function goodListAction()
    {
        $parameters = array(
            'token' => '54d7ed519214c24e93463c39585106bc',
            'sid' => 329,
            'page' => 1,
            'size' => 10
        );
        Common::verify($parameters, '/stage/goodList');
    }

    public function setBusinessMemberRoleAction()
    {
        $parameters = array(
            'token' => 'a5178f22283797c0d477c66b300315d6',
            'sid' => 740,
            'role' => 2,
            'uid' => 71
        );
        Common::verify($parameters, '/stage/setBusinessMemberRole');
    }

    public function delMemberAction()
    {
        $parameters = array(
            'token' => '7420cafb38c40d50e3791e9df793fa02',
            'sid' => 204,
            'uid' => 71
        );
        Common::verify($parameters, '/stage/delMember');
    }

    public function getStageByAddTimeAction()
    {
        $parameters = array(
            'token' => '7420cafb38c40d50e3791e9df793fa02'
        );
        Common::verify($parameters, '/stage/getStageByAddTime');
    }

    public function getCultureStageListAction()
    {
        $parameters = array(
            'token' => '267a632db89e543fb2f0266995754d73'
        );
        Common::verify($parameters, '/stage/getCultureStageList');
    }

    //验票
    public function updateTicketsAction()
    {
        $parameters = array(
            'token' => '68591c34df8a90c13d2c3d9107b50c29',
            'url' => 'partake?u=3480&s=204&e=1265'
        );
        Common::verify($parameters, '/stage/updateTickets');
    }

    public function updateOrderTicketsAction()
    {
        $parameters = array(
            'token' => '94024656c4f8c41c93216ce62f7c1126',
            'url' => 'order?id=67&s=204&e=2052'
        );
        Common::verify($parameters, '/stage/updateOrderTickets');
    }

    //驿站条件
    public function getConditionAction()
    {
        $parameters = array(
            'token' => '8c06be063315a064c24208e48ca9504d'
        );
        Common::verify($parameters, '/stage/getCondition');
    }

    //驿站条件
    public function upgradeConditionAction()
    {
        $parameters = array(
            'token' => 'e2846b9aa3e53433b42ccdf50394a7ae'
        );
        Common::verify($parameters, '/stage/upgradeCondition');
    }

    //创建文化驿站
    public function addStageAction()
    {
        $parameters = array(
            'token' => '7133f4332a1d1c002a9ec3153528535c',
            'name' => '老孙绿色',
            'cate_id' => 5,
            'intro' => '小竹测试更改服务类型小竹测试更改服务类型小竹测试更改服务类型小竹测试更改服务类型小竹测试更改服务类型小竹测试更改服务类型小竹测试更改服务类型小竹测试更改服务类型小竹测试更改服务类型',
            'mobile' => '15951829621',
            'agree' => 1,
            'permission' => 1,
            "tag_arr" => "1491&1333",
            'origin' => 3,
            'version' => '3.5',
            'lng' => '121.520353',
            'lat' => '31.238368',
            'address' => '我哪知道',
            'town_id' => 330903,
            //'service_type'=>1,
            'type' => 1,
//            'identity_img'=>'14298457134867.jpg',
//            'license_img'=>'14298457134867.jpg',
//            'bank'=>'中国银行',
//            'bank_no'=>'6217000000000000',
//            'contacts'=>'西门庆',
//            'tel'=>'021-50677816',
//            'shop_hours'=>'全天24小时',
//            'business_scope'=>'瓜子，花生，啤酒，火腿肠，方便面',
//            'email'=>'123@163.com'


        );
        Common::verify($parameters, '/stage/addStage');
    }

    //开通服务表单提交
    public function upgradeStageNewAction()
    {
        $parameters = array(
            'token' => 'd81fd5d02fb5c0722725d51ae1e3499e',
            'sid' => 1899,
            'name' => '腰器三',
            'type' => '2',
            'cate_id' => '4',
            //'license_number' => '123456789012345',
            'identity_img' => '14537772695332.jpg',
            //'license_img' => '14537772695332.jpg',
            'address' => '中国四川省成都市武侯区芳草街道蓝天路3-5号',
            'lng' => '104.052566',
            'lat' => '30.630568',
            'contacts' => '路人甲',
            'mobile' => '18995863123',
            'icon' => '14537721233909.jpg',
            'cover' => '14537773908394.jpg',
            'intro' => '经纬度换算地址经纬度换算地址经纬度换算地址经纬度换算地址经纬度换算地址经纬度换算地址经纬度换算地址经纬度换算地址经纬度换算地址经纬度换算地址',
            'tel' => '12124242',
            'shop_hours' => '22589',
            //'version'=>'2.5.1'
        );
        Common::verify($parameters, '/stage/upgradeStageNew');
    }

    //驿站状态
    public function getStatusNewAction()
    {
        $parameters = array(
            'token' => 'dbe64dc2185f4996bbba9914e7a9c9a6'
        );
        Common::verify($parameters, '/stage/getStatusNew');
    }

    //驿站分类下的标签列表
    public function getTagListAction()
    {
        $parameters = array(
            'token' => '1bc65c6f5c78d27360e4f4c8011dec41',
            'type_cate' => 15
        );
        Common::verify($parameters, '/stage/getTagList');
    }

    //文化驿站重新编辑获取信息
    public function getUpdateInfoAction()
    {
        $parameters = array(
            'token' => 'fe6b5ebdb1257c06dcefa5c47fa1f7d1',
            'type' => 1,
            'sid' => 1372
        );
        Common::verify($parameters, '/stage/getUpdateInfo');
    }

    public function getStageConditionAction()
    {
        $parameters = array(
            'token' => '9ea2df9b8d1ea03b14f2323a87eae85d',
            'version' => '2.5.1',
            'type' => 3
        );
        Common::verify($parameters, '/stage/getStageCondition');
    }

    public function getListByConditionAction()
    {
        $parameters = array(
            'token' => '9a405bf3bfc9faa564fb3ef6f5e93448',
            'id' => 3,
            'type' => 0,
            //'tag_id'=>1227,
            'city' => '全国',
            'sort' => '不限',
            'version' => '3.5'
        );
        Common::verify($parameters, '/stage/getListByCondition');
    }

    public function manageAction()
    {
        $parameters = array(
            'token' => '27de5ec9cda62a12fb9332f169b7d3ac',
            'id' => 1275
        );
        Common::verify($parameters, '/stage/manage');
    }

    public function updateStageNewAction()
    {
        $parameters = array(
            'token' => '9f2823873c4cec9e56ec8002d1dd1262',
            'sid' => 1372,
            'tel' => '027-4722222',
            'type' => 2,
            'version' => '2.5.1',
            'name' => '笨在在在在',
            'icon' => '145622222222.jpg',
            'intro' => '周周周周财周财周要枯周在在在',
            'tag_arr' => '2729&1234&1232&1253&2222222',
            "town_id" => 330101,
            'shop_hours' => '6666222222222',
            'address' => "中国浙江省杭州市下城区朝晖街道潮王路1101222222号",
            'cover' => "14562194183072222222.jpg&14565404828165222222.jpg&14565404825418222222.jpg",
            'lat' => "30.288481222222",
            'lng' => "120.15257222222"
        );
        Common::verify($parameters, '/stage/updateStageNew');
    }

    /********3.0***********/
    public function stageIndexAction()
    {
        $parameters = array(
            'token' => 'e04b53fc7e040c1c7de1ad332e090e20',
            'sid' => 1591,
            'page' => 1,
            'size' => 10,
            'type' => 2
        );
        Common::verify($parameters, '/stage/stageIndex');
    }

    //用户的驿站列表
    public function getStageListAction()
    {
        $parameters = array(
            'token' => '9f2823873c4cec9e56ec8002d1dd1262',
            'version' => '3.7'
        );
        Common::verify($parameters, '/stage/getStageList');
    }

    public function updateAuthorityAction()
    {
        $parameters = array(
            'token' => '8d9b1b9ddbde8652cf4482f0ca14e91e',
            'authority' => '2',
            'sid' => 204
        );
        Common::verify($parameters, '/stage/updateAuthority');
    }

    public function getStageMessageViewAction()
    {
        $parameters = array(
            'token' => '9f2823873c4cec9e56ec8002d1dd1262',
            'sid' => 204
        );
        Common::verify($parameters, '/stage/getStageMessageView');
    }

    public function stageIndexChangeAction()
    {
        $parameters = array(
            'token' => '8d9b1b9ddbde8652cf4482f0ca14e91e',
            'sid' => 204,
            'type' => 4
        );
        Common::verify($parameters, '/stage/stageIndexChange');
    }

    public function getStageIsPayAction()
    {
        $parameters = array(
            'token' => '8d9b1b9ddbde8652cf4482f0ca14e91e',
            'sid' => 204
        );
        Common::verify($parameters, '/stage/getStageIsPay');
    }

    public function getStageUserListAction()
    {
        $parameters = array(
            'token' => '8d9b1b9ddbde8652cf4482f0ca14e91e',
            'sid' => 204
        );
        Common::verify($parameters, '/stage/getStageUserList');
    }

    public function getGoodsListAction()
    {
        $parameters = array(
            'token' => '8d9b1b9ddbde8652cf4482f0ca14e91e',
            'sid' => 204
        );
        Common::verify($parameters, '/stage/getGoodsList');
    }

    public function isAddStageListAction()
    {
        $parameters = array(
            'token' => '6b2efd9caf648937feddea83f1ec29f1',
            'version' => '3.5',
        );
        Common::verify($parameters, '/stage/isAddStageList');
    }

    public function getIsAddNumAction()
    {
        $parameters = array(
            'token' => 'b34c030564abbd509011ba8654bd641d'
        );
        Common::verify($parameters, '/stage/getIsAddNum');
    }

    public function getStageViewAction()
    {
        $parameters = array(
            'token' => 'fad97816b25a73a533b08cc853b0edd1'
        );
        Common::verify($parameters, '/stage/getStageView');
    }

    public function upgradeStageAction()
    {
        $parameters = array(
            'token' => 'd0f7054ce4db339f74cb46c58d62c38d',
            'name' => '教师伍的撒',
            'cate_id' => '5',
            'intro' => '银行升级银行升级银行升级银行升级银行升级银行升级银行升级银行升级银行升级银行升级银行升级银行升级',
            'mobile' => '15951829621',
            'agree' => 1,
            'permission' => 1,
            "tag_arr" => "1491&1333",
            'origin' => 3,
            'version' => '3.5',
            'lng' => '121.520353',
            'lat' => '31.238368',
            'address' => '我哪知道',
            'town_id' => 330903,
            'service_type' => 1,
            'identity_img' => '14298457134867.jpg',
            'license_img' => '14298457134867.jpg',
            'bank' => '沙茶银行',
            'bank_no' => '1236 5478 9654 1236 9874 1256 32',
            'contacts' => '西门庆',
            'tel' => '021-50677816',
            'shop_hours' => '全天24小时',
            'business_scope' => '瓜子，花生，啤酒，火腿肠，方便面',
            'sid' => 1903,
            'email' => '123456@163.com'
            //'icon'=>'14078404697938.jpg'
        );
        Common::verify($parameters, '/stage/upgradeStage');
    }

    public function setStageNoticeAction()
    {
        $parameters = array(
            'token' => '95f94a1b1bdec8fce76e6664c4b49579',
            'sid' => '204',
            'notice' => '老孙测试这个驿站的公告好不好用哈哈啊哈'
        );
        Common::verify($parameters, '/stage/setStageNotice');
    }

    public function updateStageAction()
    {
        $parameters = array(
            'token' => '9f2823873c4cec9e56ec8002d1dd1262',
            'name' => '老测试类同要要要要',
            'cate_id' => 3,
            'intro' => '测试好鄙视还是黄浦区',
            'mobile' => '18700000024',
            'agree' => 1,
            'permission' => 1,
            "tag_arr" => "1226&1227",
            'version' => '3.5',
            'lng' => '39.651683',
            'lat' => '118.178605',
            'address' => '我哪知道ssssssssss',
            'town_id' => 130203,
            'service_type' => 1,
            'type' => 2,
            'identity_img' => '14750438337555.jpg',
            'license_img' => '14298457134867.jpg',
            'bank' => '中国银行',
            'bank_no' => '1478523699632584',
            'contacts' => '小宇宙',
            'tel' => '021-50677816',
            'shop_hours' => '全天',
            'business_scope' => '书画',
            'email' => '147896@163.com',
            // 'sid'=>1767
            'sid' => 1372
        );
        Common::verify($parameters, '/stage/updateStage');
    }

    public function getIndexTopAction()
    {
        $parameters = array(
            'token' => '0c5f509f3A7bcad1f9273b73059850028',
            'sid' => 1275
        );
        Common::verify($parameters, '/stage/getIndexTop');
    }

    public function getIndexMoreAction()
    {
        $parameters = array(
            'token' => '378d15a0e17c2fdfbef9a0156b61a57d',
            'sid' => 1275,
            'type' => 3,
            'page' => 1
        );
        Common::verify($parameters, '/stage/getIndex');
    }

    public function getIndexAction()
    {
        $parameters = array(
            'token' => 'e4638c9b1b5758dd82a91c7b2cac8b9a',
            'sid' => 1275
        );
        Common::verify($parameters, '/stage/getIndex');
    }

    public function getTicketsInfoAction()
    {
        $parameters = array(
            'token' => '34b57244f2caf5763d8b909d970f4ac8',
            'url' => 'id=775&s=740&e=2518f_id=2439&type=2',
            'check_type' => 1
        );
        Common::verify($parameters, '/stage/getTicketsInfo');
    }

    public function indexStageViewAction()
    {
        $parameters = array();
        Common::verify($parameters, '/stage/indexStageView');
    }

    //驿站条件
    public function topicListAction()
    {
        $parameters = array(
            'token' => '7c9904be43d0b3b10194e09dc8c277f7',
            'sid' => 204,
            'page' => 1
        );
        Common::verify($parameters, '/stage/topicList');
    }

    public function getSetCommissionListAction()
    {
        $parameters = array(
            'token' => '7a2afe4bca099b587b00202fc83c1eec',
            'type' => 10
        );
        Common::verify($parameters, '/stage/getSetCommissionList');
    }

    public function getIsSetCommissionListAction()
    {
        $parameters = array(
            'token' => '7a2afe4bca099b587b00202fc83c1eec',
            'type' => 10
        );
        Common::verify($parameters, '/stage/getIsSetCommissionList');
    }

    public function stageManagerAction()
    {
        $parameters = array(
            'token' => '7a2afe4bca099b587b00202fc83c1eec',
            'sid' => 1379
        );
        Common::verify($parameters, '/stage/stageManager');
    }

    public function getStageSpListAction()
    {
        $parameters = array(
            'token' => 'a5dd10374170a74438470f8998972e4f',
            'type' => 0
        );
        Common::verify($parameters, '/stage/getStageSpList');
    }

    public function getOneSpListAction()
    {
        $parameters = array(
            'token' => 'a5dd10374170a74438470f8998972e4f',
            'type' => 10,
            'obj_id' => 3157
        );
        Common::verify($parameters, '/stage/getOneSpList');
    }
}
