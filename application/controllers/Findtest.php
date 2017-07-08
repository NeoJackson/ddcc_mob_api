<?php
class FindtestController extends Yaf_Controller_Abstract
{

    //附近的人
    public function vicinityUserAction(){
        $parameters = array(
            'token' =>'641e33e1cc87b063db0e65c7e9b8bb7c',
            'page'=>1,
            'size'=>10
        );
        Common::verify($parameters, '/find/vicinityUser');
    }
    //附近的商家驿站
    public function vicinityStageAction(){
        $parameters = array(
            'token' =>'d34d49417f9599beb2fa019e8138a421',
            'page'=>1,
            'size'=>10
        );
        Common::verify($parameters, '/find/vicinityStage');
    }
    //附近的商家活动
    public function vicinityEventAction(){
        $parameters = array(
            'token'=>'d34d49417f9599beb2fa019e8138a421',
        );
        Common::verify($parameters, '/find/vicinityEvent');
    }
    //活跃天数排行
    public function activeRankAction(){
        $parameters = array(
            'page'=>1
        );
        Common::verify($parameters, '/find/activeRank');
    }
    //商家帖子浏览排行
    public function merchantTopicRankAction(){
        $parameters = array(
            'size'=>10,
            'page'=>1
        );
        Common::verify($parameters, '/find/merchantTopicRank');
    }
    //福报值排行
    public function scoreRankAction(){
        $parameters = array(
            'size'=>10,
            'page'=>1
        );
        Common::verify($parameters, '/find/scoreRank');
    }
    //驿站帖子数排行
    public function stageRankAction(){
        $parameters = array(
            'size'=>10,
            'page'=>1
        );
        Common::verify($parameters, '/find/stageRank');
    }
    //标签寻友
    public function seekFriendAction(){
        $parameters = array(
            'token' =>'d34d49417f9599beb2fa019e8138a421',
            'tag_id'=>90,
            'size'=>10,
            'page'=>1
        );
        Common::verify($parameters, '/find/seekFriend');
    }
    //分类和标签
    public function getCultureCateAndTagAction(){
        $parameters = array(
            'token' =>'d34d49417f9599beb2fa019e8138a421'
        );
        Common::verify($parameters, '/find/getCultureCateAndTag');
    }
    //获取应用
    public function getApplyAction(){
        $parameters = array(
            'token' =>'d34d49417f9599beb2fa019e8138a421'
        );
        Common::verify($parameters, '/find/getApply');
    }
    //获取官方推荐的标签
    public function getAction(){
        $parameters = array(
            'token' =>'1ee880fc5b6c1541f815f221a6c9cfb1',
        );
        Common::verify($parameters, '/find/get');
    }
    public function goodsRecommendMoreAction(){
        $parameters = array(
            'type' =>'1',
        );
        Common::verify($parameters, '/find/goodsRecommendMore');
    }
    public function getFindUserAction(){
        $parameters = array(
            'token' =>'cd6b31cb559109160469860b17ee6dde',
        );
        Common::verify($parameters, '/find/getFindUser');
    }
}