<?php
class FeedtestController extends Yaf_Controller_Abstract
{
    //我的参与
    public function myPartakeListAction(){
        $parameters = array(
            'token' =>'68591c34df8a90c13d2c3d9107b50c29',
            'is_close'=>0,
//            'lng'=>'121.5269831',
//            'lat'=>'31.236281',
            'page'=>1,
            'size'=>5
        );
        Common::verify($parameters, '/feed/myPartakeList');
    }
    //用户参与商家活动
    public function addPartakeEventNewAction(){
        $parameters = array(
            'token' =>'5cc288ef7ab607b00d3e58a7516d6cc3',
            'id' =>'929',
            'mobile'=>'13611111111',
            'name'=>'哈哈'
        );
        Common::verify($parameters, '/feed/addPartakeEventNew');
    }
    //删除评论接口
    public function delCommentAction(){
        $parameters = array(
            'token' =>'655661e36792bbc8b1e58855d23d491f',
            'id' =>'235315',
        );
        Common::verify($parameters, '/feed/delComment');
    }
    //添加收藏
    public function addCollectAction(){
        $parameters = array(
            'token' =>'a508f379af540c7bf6affed1a914d96c',
            'type' =>10,
            'id'=>'1501',
        );
        Common::verify($parameters, '/feed/addCollect');
    }
    //取消收藏
    public function delCollectAction(){
        $parameters = array(
            'token' =>'f4b32c7db5b5171fe38a7f7153b7ec09',
            'type' =>5,
            'id'=>'34',
        );
        Common::verify($parameters, '/feed/delCollect');
    }
    //删除动态
    public function delFeedAction(){
        $parameters = array(
            'token' =>'df7d4a969a575fd64a905e464c3e7153',
            'type' =>2,
            'id'=>'134300',
            'last_time'=>'1483769460'
        );
        Common::verify($parameters, '/feed/delFeed');
    }
    //定时请求最新动态
    public function getNewFeedAction(){
        $parameters = array(
            'token' =>'7420cafb38c40d50e3791e9df793fa02',
        );
        Common::verify($parameters, '/feed/getNewFeed');
    }

    //添加分享
    public function addShareAction(){
        $parameters = array(
            'token' =>'cf3bb5fa8d561fe9671ce066aafd724b',
            'content'=>'@李晨 ',
            'share_type'=>1,
            'share_id'=>47022,
            'shared_id'=>2985

        );
        Common::verify($parameters, '/feed/addShare');
    }
    public function getListNewAction(){
        $parameters = array(
            'token' =>'0eea7ef1df905e515c778070460b0d72',
            'size'=>10,
            'last' => 0,
            'version'=>'3.5'
        );
        Common::verify($parameters, '/feed/getListNew');
    }
    //最新动态数量
    public function getLastNumNewAction(){
        $parameters = array(
            'token' =>'f490d8bdce84d80e422d2d90736bd721',
            'last'=>0
        );
        Common::verify($parameters, '/feed/getLastNumNew');
    }
    public function getUserListNewAction(){
        $parameters = array(
            'token' =>'ea073e94c48c6ce94217916807aaf570',
            'size'=>10,
            'uid'=>13463,
            'last'=>0,
            'version'=>'4.0'
        );
        Common::verify($parameters, '/feed/getUserListNew');
    }
    //获取最新心境
    public function getNewMoodAction(){
        $parameters = array(
            'token' =>'a1a271b917706fc009a0838ba1087b40',
            'last_id'=>41913
        );
        Common::verify($parameters, '/feed/getNewMood');
    }
    //添加评论接口
    public function addCommentAction(){
        $parameters = array(
            'token' =>'c986f7160bd1a1e86d18cc30523ed927',
            'type' =>'12',
            'obj_id'=>'1',
            'reply_uid'=>'3480',
            'reply_id'=>'237214',
            'content'=>'[大爱]'
        );
        Common::verify($parameters, '/feed/addComment');
    }
    //评论列表
    public function getCommentListAction(){
        $parameters = array(
            'token' =>'f3969c6fc40a776d5c8ba460de46ef88',
            'type' =>'10',
            'obj_id'=>'76',
            'size'=>1,
            'last_time'=>'2015-02-11 16:02:30'
        );
        Common::verify($parameters, '/feed/getCommentList');
    }

    //取消报名
    public function updatePartakeEventAction(){
        $parameters = array(
            'token' =>'ac7bcef6c44ca238e8c9cef2ccb8a40e',
            'id'=>'717',
        );
        Common::verify($parameters, '/feed/updatePartakeEvent');
    }
    public function getDynamicCommentListAction(){
        $parameters = array(
            'token' =>'83704f4ff48dadb13023d83563bdabac',
            'id'=>'4090',
            'type'=>9
        );
        Common::verify($parameters, '/feed/getDynamicCommentList');
    }
    public function getListAction(){
        $parameters = array(
            'token' =>'92abf1c5a02b9623608fc8d3a1834a9c',
            'last'=>'0',
            'version'=>'3.7.2',

        );
        Common::verify($parameters, '/feed/getList');
    }
    public function getUserListAction(){
        $parameters = array(
            'token'=>'0a906ef81685815f6090f9652a2e236e',
            'size'=>10,
            'uid'=>13463,
            'last'=>0,
            'version'=>'3.7.2'
        );
        Common::verify($parameters, '/feed/getUserList');
    }
}