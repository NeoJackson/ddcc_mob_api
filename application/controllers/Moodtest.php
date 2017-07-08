<?php
class MoodtestController extends Yaf_Controller_Abstract
{
//发布心境
    public function addMoodAction(){
        $parameters = array(
            'token' =>'41cb39448fb18d60afad97d673d7f63e',
            'content'=>'#啊啊啊#是哈哈真的2121111',
            'img'=>'14298457134867.jpg&14298457134867.jpg',
            'sort'=>'1&2'
        );
        Common::verify($parameters, '/mood/addMood');
    }
    //删除心境
    public function delMoodAction(){
        $parameters = array(
            'token' =>'d099caa3d11b7ec571380fb30b03ca66',
            'id'=>2421,
        );
        Common::verify($parameters, '/mood/delMood');
    }
    //心境详情
    public function infoAction(){
        $parameters = array(
            'token' =>'',
            'id'=>61446,
        );
        Common::verify($parameters, '/mood/info');
    }
    //分享详情
    public function shareInfoAction(){
        $parameters = array(
            'token' =>'0efbbbfe6b0e6dabd7ddbfe6a32b09cb',
            'hid'=>5802,
        );
        Common::verify($parameters, '/mood/shareInfo');
    }
    public function getMoodListAction(){
        $parameters = array(
            'token' =>'2b789bb518d55046497be98c9b85e163',
            "last_id" => 0,
            'size' => 10
        );
        Common::verify($parameters, '/mood/moodSquare');
    }
    public function getNewMoodAction(){
        $parameters = array(
            'token' =>'7c9904be43d0b3b10194e09dc8c277f7',
            "last" => '',
            'size' => 3
        );
        Common::verify($parameters, '/mood/getNewMood');
    }
    public function getListAction(){
        $parameters = array(
            'token' =>'47c15120742c773abf81a3db4b6c808f',
            "last_id" => 0,
            'size' => 5
        );
        Common::verify($parameters, '/mood/getList');
    }

    public function addMoodNewAction(){
        $parameters = array(
            'token' =>'351321b2877f242a8563d4c77c2ae596',
            "content" => '分享图片',
            //'video_name' => "F1plLHjbI0bLXiBIwIkcPfcHrT31Z",
            'is_img' =>1,
            'address'=>'山西省大同市南郊区S206',
            'lng'=>'113.221143',
            'lat'=>'40.048256',
            'origin'=>4,
            'img'=>'mood_image_1491832808767.jpg&mood_image_1491832808804.jpg&mood_image_1491832808733.jpg&mood_image_1491832808785.jpg&mood_image_1491832808824.jpg&mood_image_1491832808681.jpg&mood_image_1491832808848.jpg&mood_image_1491832808855.jpg&mood_image_1491832808707.jpg
',
            'sort'=>'4&6&3&5&7&1&8&9&2
',
            'version'=>'3.7.3',
            'client_id'=>'14918932808376',
            'is_public'=>1
        );
        Common::verify($parameters, '/mood/addMoodNew');
    }

    public function addImagesAction(){
        $parameters = array(
            'token' =>'7e32982f6464d93242761d31501b702a',
            "img" => '14714873366800.jpg&14714873369482.jpg&14714873364087.jpg&14714873382887.jpg&14714873381446.jpg&14714874713639.jpg&14714873404503.jpg&14714873411149.jpg&14714873429999.jpg',
            'version' => "3.0",
            'sort'=>'1&2&3&4&5&6&7&8&9',
            'mood_id'=>56863
        );
        Common::verify($parameters, '/mood/addImages');
    }
    public function getMoodLengthAction(){
        $parameters = array(
            'token' =>'7e32982f6464d93242761d31501b702a',
        );
        Common::verify($parameters, '/mood/getMoodLength');
    }
}