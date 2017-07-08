<?php
class HometestController extends Yaf_Controller_Abstract
{
    //增加关注
    public function viewUserFeedAction(){
        $parameters = array(
            'token' =>'388aec6739ea20615b4802bf8e579c87',
            'uid'=>3480
        );
        Common::verify($parameters, '/home/viewUserFeed');
    }

}