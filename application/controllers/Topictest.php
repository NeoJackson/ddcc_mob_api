<?php
class TopictestController extends Yaf_Controller_Abstract
{
    //我发布的帖子
    public function myTopicAction(){
        $parameters = array(
            'token' =>'7c9904be43d0b3b10194e09dc8c277f7',
            'page'=>1,
            'size'=>10
        );
        Common::verify($parameters, '/topic/myTopic');
    }
    //我回复的帖子+活动
    public function myReplyAction(){
        $parameters = array(
            'token' =>'7420cafb38c40d50e3791e9df793fa02',
            'page'=>1,
            'size'=>2
        );
        Common::verify($parameters, '/topic/myReply');
    }
    //删除一般帖子
    public function delTopicAction(){
        $parameters = array(
            'token' =>'d099caa3d11b7ec571380fb30b03ca66',
            'tid'=>'26087'
        );
        Common::verify($parameters, '/topic/delTopic');
    }
    //修改一般帖子
    public function modifyTopicAction(){
        $parameters = array(
            'token' =>'f4b32c7db5b5171fe38a7f7153b7ec09',
            'tid'=>'25145',
            'title'=>'回家睡觉说到叫我大哥货时间的话',
            'content'=>'哈现在在修辞手法佛挡杀佛 奋斗发哈',
            'type'=>'1',
        );
        Common::verify($parameters, '/topic/modifyTopic');
    }
    //获取一般帖子信息
    public function getTopicInfoAction(){
        $parameters = array(
            'token' =>'f4b32c7db5b5171fe38a7f7153b7ec09',
            'tid'=>'25145',
        );
        Common::verify($parameters, '/topic/getTopicInfo');
    }
    //用户浏览一般帖子
    public function viewTopicAction(){
        $parameters = array(
            'tid'=>'25201',
            'version'=>1.3
        );
        Common::verify($parameters, '/topic/viewTopic');
    }
    //获取商家帖子标签
    public function getMerchantTagAction(){
        $parameters = array(
            'token' =>'f4b32c7db5b5171fe38a7f7153b7ec09'
        );
        Common::verify($parameters, '/topic/getMerchantTag');
    }
    //获取商家帖子内容分类
    public function getMerchantEventTypeAction(){
        $parameters = array(
            'token' =>'f4b32c7db5b5171fe38a7f7153b7ec09'
        );
        Common::verify($parameters, '/topic/getMerchantEventType');
    }
    //商家发布一般帖子
    public function addTopicAction(){
        $parameters = array(
            'token' =>'b6385ab93cc94ecf82c5f210d9ad0e7f',
            'sid'=>'204',
            'title'=>'老孙23331111',
            'content'=>'老孙测试缓存帖子老孙测试缓存帖子老孙测14329691284389.jpg试缓存帖子老孙测试缓存帖子老14329691284180.jpg孙测试缓存帖子老孙测试缓存帖子老孙测试缓存帖子',
            'type'=>'1',
            'origin'=>3,
            'address'=>'山西省大同市南郊区S206',
            'lng'=>'113.221143',
            'lat'=>'40.048256',
            'img'=>'14329691284180.jpg&14329691284389.jpg',
        );
        Common::verify($parameters, '/topic/addTopic');
    }
    //精帖
    public function goodTopicListAction(){
        $parameters = array(
            'token' =>'69fb50d56e7b06f7c5a07d5a06a3c93f',
            'size'=>2
        );
        Common::verify($parameters, '/topic/goodTopicList');
    }
    public function topAction(){
        $parameters = array(
            'token' =>'25bf67b91ce56abc17112178224f3da4',
            'tid'=>27760,
            'type'=>1
        );
        Common::verify($parameters, '/topic/top');
    }

    public function previewAction(){
        $parameters = array(
            'token' =>'e04b53fc7e040c1c7de1ad332e090e20',
            'sid'=>'332',
            'title'=>'老孙测试带图帖子预览',
            'content'=>'老孙自己的驿站测试发布权14745286286031.jpg限老孙自己的驿站测试发布14329691274347.jpg权限老孙自己的驿站测试发布权限老孙自己的驿站测试发布权限老孙自己的驿站测试发布权限',
            'type'=>'1',
            'origin'=>3,
            //'address'=>'山西省大同市南郊区S206',
            //'lng'=>'113.221143',
            //'lat'=>'40.048256',
            'img'=>'14745286286031.jpg',
        );
        Common::verify($parameters, '/topic/preview');
    }

    public function cancelTopAction(){
        $parameters = array(
            'token' =>'371608a3a2c9a769a45384eb6b9a2e6a',
            'tid'=>113633,
            'type'=>1
        );
        Common::verify($parameters, '/topic/cancelTop');
    }
}