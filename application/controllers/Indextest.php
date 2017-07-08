<?php
class IndextestController extends Yaf_Controller_Abstract
{
    //APP首页推荐数据接口
    public function indexAction(){
        $parameters = array(
            //'token' =>'da4fc07032010e34406fb829874c41fc'
        );
        Common::verify($parameters, '/index/index');
    }

    //APP首页精帖接口
    public function indexTopicAction(){
        $parameters = array(
            'token' =>'5ca4849f048c06bd805c85ee860e030d'
        );
        Common::verify($parameters, '/index/indexTopic');
    }
    //APP首页精帖更多接口
    public function indexTopicMoreAction(){
        $parameters = array(
            'token' =>'5ca4849f048c06bd805c85ee860e030d',
            'page'=>2,
            'size'=>5
        );
        Common::verify($parameters, '/index/indexTopicMore');
    }
    //APP首页文化天使接口
    public function indexAngelAction(){
        $parameters = array(
            'token' =>'',
            'page'=>1,
            'size'=>15
        );
        Common::verify($parameters, '/index/indexAngel');
    }

    //APP首页驿站接口
    public function indexStageAction(){
        $parameters = array(
            'token' =>'d965fca1579929d65f80e135ae9df9a2'
        );
        Common::verify($parameters, '/index/indexStage');
    }


    public function biaoqingAction(){
        $parameters = array(
            'dai_data' =>array(
                array(
                    'code'=> '[三哥愁]',
                    'src'=> 'a_yijia_san_0.png',
                    'title'=> '三哥愁'
                ),
                array(
                    'code'=> '[三哥不屑]',
                    'src'=> 'a_yijia_san_1.png',
                    'title'=> '三哥不屑'
                ),
                array(
                    'code'=> '[三哥大哭]',
                    'src'=> 'a_yijia_san_2.png',
                    'title'=> '三哥大哭'
                ),
                array(
                    'code'=> '[三哥害羞]',
                    'src'=> 'a_yijia_san_3.png',
                    'title'=> '三哥害羞'
                ),
                array(
                    'code'=> '[三哥坏笑]',
                    'src'=> 'a_yijia_san_4.png',
                    'title'=> '三哥坏笑'
                ),
                array(
                    'code'=> '[三哥惊吓]',
                    'src'=> 'a_yijia_san_5.png',
                    'title'=> '三哥惊吓'
                ),
                array(
                    'code'=> '[三哥困]',
                    'src'=> 'a_yijia_san_6.png',
                    'title'=> '三哥困'
                ),
                array(
                    'code'=> '[三哥乐]',
                    'src'=> 'a_yijia_san_7.png',
                    'title'=> '三哥乐'
                ),
                array(
                    'code'=> '[三哥怒]',
                    'src'=> 'a_yijia_san_8.png',
                    'title'=> '三哥怒'
                ),
                array(
                    'code'=> '[三哥亲]',
                    'src'=> 'a_yijia_san_9.png',
                    'title'=> '三哥亲'
                ),
                array(
                    'code'=> '[三哥色]',
                    'src'=> 'a_yijia_san_10.png',
                    'title'=> '三哥色'
                ),
                array(
                    'code'=> '[三哥喜]',
                    'src'=> 'a_yijia_san_11.png',
                    'title'=> '三哥喜'
                ),
                array(
                    'code'=> '[三哥晕]',
                    'src'=> 'a_yijia_san_12.png',
                    'title'=> '三哥晕'
                ),
                array(
                    'code'=> '[三哥抓狂]',
                    'src'=> 'a_yijia_san_13.png',
                    'title'=> '三哥抓狂'
                ),
                array(
                    'code'=> '[三哥鬼脸]',
                    'src'=> 'a_yijia_san_14.png',
                    'title'=> '三哥鬼脸'
                ),
                array(
                    'code'=> '[三哥拍手]',
                    'src'=> 'a_yijia_san_15.png',
                    'title'=> '三哥拍手'
                ),
                array(
                    'code'=> '[三哥汗]',
                    'src'=> 'a_yijia_san_16.png',
                    'title'=> '三哥汗'
                ),
                array(
                    'code'=> '[易姐愁]',
                    'src'=> 'a_yijia_yi_0.png',
                    'title'=> '易姐愁'
                ),
                array(
                    'code'=> '[易姐不屑]',
                    'src'=> 'a_yijia_yi_1.png',
                    'title'=> '易姐不屑'
                ),
                array(
                    'code'=> '[易姐大哭]',
                    'src'=> 'a_yijia_yi_2.png',
                    'title'=> '易姐大哭'
                ),
                array(
                    'code'=> '[易姐害羞]',
                    'src'=> 'a_yijia_yi_3.png',
                    'title'=> '易姐害羞'
                ),
                array(
                    'code'=> '[易姐坏笑]',
                    'src'=> 'a_yijia_yi_4.png',
                    'title'=> '易姐坏笑'
                ),
                array(
                    'code'=> '[易姐惊吓]',
                    'src'=> 'a_yijia_yi_5.png',
                    'title'=> '易姐惊吓'
                ),
                array(
                    'code'=> '[易姐困]',
                    'src'=> 'a_yijia_yi_6.png',
                    'title'=> '易姐困'
                ),
                array(
                    'code'=> '[易姐乐]',
                    'src'=> 'a_yijia_yi_7.png',
                    'title'=> '易姐乐'
                ),
                array(
                    'code'=> '[易姐怒]',
                    'src'=> 'a_yijia_yi_8.png',
                    'title'=> '易姐怒'
                ),
                array(
                    'code'=> '[易姐亲]',
                    'src'=> 'a_yijia_yi_9.png',
                    'title'=> '易姐亲'
                ),
                array(
                    'code'=> '[易姐色]',
                    'src'=> 'a_yijia_yi_10.png',
                    'title'=> '易姐色'
                ),
                array(
                    'code'=> '[易姐喜]',
                    'src'=> 'a_yijia_yi_11.png',
                    'title'=> '易姐喜'
                ),
                array(
                    'code'=> '[易姐晕]',
                    'src'=> 'a_yijia_yi_12.png',
                    'title'=> '易姐晕'
                ),
                array(
                    'code'=> '[易姐汗]',
                    'src'=> 'a_yijia_yi_13.png',
                    'title'=> '易姐汗'
                ),
                array(
                    'code'=> '[易姐抓狂]',
                    'src'=> 'a_yijia_yi_14.png',
                    'title'=> '易姐抓狂'
                ),
                array(
                    'code'=> '[易姐拍手]',
                    'src'=> 'a_yijia_yi_15.png',
                    'title'=> '易姐拍手'
                ),
                array(
                    'code'=> '[易姐鬼脸]',
                    'src'=> 'a_yijia_yi_16.png',
                    'title'=> '易姐鬼脸'
                ),
                array(
                    'code'=> '[小花愁]',
                    'src'=> 'a_yijia_hua_0.png',
                    'title'=> '小花愁'
                ),
                array(
                    'code'=> '[小花不屑]',
                    'src'=> 'a_yijia_hua_1.png',
                    'title'=> '小花不屑'
                ),
                array(
                    'code'=> '[小花大哭]',
                    'src'=> 'a_yijia_hua_2.png',
                    'title'=> '小花大哭'
                ),
                array(
                    'code'=> '[小花害羞]',
                    'src'=> 'a_yijia_hua_3.png',
                    'title'=> '小花害羞'
                ),
                array(
                    'code'=> '[小花坏笑]',
                    'src'=> 'a_yijia_hua_4.png',
                    'title'=> '小花坏笑'
                ),
                array(
                    'code'=> '[小花惊吓]',
                    'src'=> 'a_yijia_hua_5.png',
                    'title'=> '小花惊吓'
                ),
                array(
                    'code'=> '[小花困]',
                    'src'=> 'a_yijia_hua_6.png',
                    'title'=> '小花困'
                ),
                array(
                    'code'=> '[小花乐]',
                    'src'=> 'a_yijia_hua_7.png',
                    'title'=> '小花乐'
                ),
                array(
                    'code'=> '[小花怒]',
                    'src'=> 'a_yijia_hua_8.png',
                    'title'=> '小花怒'
                ),
                array(
                    'code'=> '[小花亲]',
                    'src'=> 'a_yijia_hua_9.png',
                    'title'=> '小花亲'
                ),
                array(
                    'code'=> '[小花色]',
                    'src'=> 'a_yijia_hua_10.png',
                    'title'=> '小花色'
                ),
                array(
                    'code'=> '[小花喜]',
                    'src'=> 'a_yijia_hua_11.png',
                    'title'=> '小花喜'
                ),
                array(
                    'code'=> '[小花汗]',
                    'src'=> 'a_yijia_hua_12.png',
                    'title'=> '小花汗'
                ),
                array(
                    'code'=> '[小花晕]',
                    'src'=> 'a_yijia_hua_13.png',
                    'title'=> '小花晕'
                ),
                array(
                    'code'=> '[小花抓狂]',
                    'src'=> 'a_yijia_hua_14.png',
                    'title'=> '小花抓狂'
                ),
                array(
                    'code'=> '[小花拍手]',
                    'src'=> 'a_yijia_hua_15.png',
                    'title'=> '小花拍手'
                ),
                array(
                    'code'=> '[小花鬼脸]',
                    'src'=> 'a_yijia_hua_16.png',
                    'title'=> '小花鬼脸'
                ),
                array(
                    'code'=> '[小子愁]',
                    'src'=> 'a_yijia_zi_0.png',
                    'title'=> '小子愁'
                ),
                array(
                    'code'=> '[小子不屑]',
                    'src'=> 'a_yijia_zi_1.png',
                    'title'=> '小子不屑'
                ),
                array(
                    'code'=> '[小子大哭]',
                    'src'=> 'a_yijia_zi_2.png',
                    'title'=> '小子大哭'
                ),
                array(
                    'code'=> '[小子害羞]',
                    'src'=> 'a_yijia_zi_3.png',
                    'title'=> '小子害羞'
                ),
                array(
                    'code'=> '[小子坏笑]',
                    'src'=> 'a_yijia_zi_4.png',
                    'title'=> '小子坏笑'
                ),
                array(
                    'code'=> '[小子惊吓]',
                    'src'=> 'a_yijia_zi_5.png',
                    'title'=> '小子惊吓'
                ),
                array(
                    'code'=> '[小子困]',
                    'src'=> 'a_yijia_zi_6.png',
                    'title'=> '小子困'
                ),
                array(
                    'code'=> '[小子乐]',
                    'src'=> 'a_yijia_zi_7.png',
                    'title'=> '小子乐'
                ),
                array(
                    'code'=> '[小子怒]',
                    'src'=> 'a_yijia_zi_8.png',
                    'title'=> '小子怒'
                ),
                array(
                    'code'=> '[小子亲]',
                    'src'=> 'a_yijia_zi_9.png',
                    'title'=> '小子亲'
                ),
                array(
                    'code'=> '[小子色]',
                    'src'=> 'a_yijia_zi_10.png',
                    'title'=> '小子色'
                ),
                array(
                    'code'=> '[小子喜]',
                    'src'=> 'a_yijia_zi_11.png',
                    'title'=> '小子喜'
                ),
                array(
                    'code'=> '[小子拍手]',
                    'src'=> 'a_yijia_zi_12.png',
                    'title'=> '小子拍手'
                ),
                array(
                    'code'=> '[小子晕]',
                    'src'=> 'a_yijia_zi_13.png',
                    'title'=> '小子晕'
                ),
                array(
                    'code'=> '[小子汗]',
                    'src'=> 'a_yijia_zi_14.png',
                    'title'=> '小子汗'
                ),
                array(
                    'code'=> '[小子抓狂]',
                    'src'=> 'a_yijia_zi_15.png',
                    'title'=> '小子抓狂'
                ),
                array(
                    'code'=> '[小子鬼脸]',
                    'src'=> 'a_yijia_zi_16.png',
                    'title'=> '小子鬼脸'
                ),
            ),
//            'qq_data' =>array(
//                /**
//                 * Created by Administrator on 14-7-4.
//                 */
//                array('code'=>'[微笑]','src'=>'p_qq_1.gif','title'=>'微笑'),array('code'=>'[撇嘴]','src'=>'p_qq_2.gif','title'=>'撇嘴'),array('code'=>'[大爱]','src'=>'p_qq_3.gif','title'=>'大爱'),array('code'=>'[发呆]','src'=>'p_qq_4.gif','title'=>'发呆'),array('code'=>'[流泪]','src'=>'p_qq_5.gif','title'=>'流泪'),array('code'=>'[害羞]','src'=>'p_qq_6.gif','title'=>'害羞'),array('code'=>'[闭嘴]','src'=>'p_qq_7.gif','title'=>'闭嘴'),array('code'=>'[睡]','src'=>'p_qq_8.gif','title'=>'睡'),array('code'=>'[大哭]','src'=>'p_qq_9.gif','title'=>'大哭'),array('code'=>'[尴尬]','src'=>'p_qq_10.gif','title'=>'尴尬'),array('code'=>'[发怒]','src'=>'p_qq_11.gif','title'=>'发怒'),array('code'=>'[调皮]','src'=>'p_qq_12.gif','title'=>'调皮'),array('code'=>'[呲牙]','src'=>'p_qq_13.gif','title'=>'呲牙'),array('code'=>'[惊讶]','src'=>'p_qq_14.gif','title'=>'惊讶'),array('code'=>'[难过]','src'=>'p_qq_15.gif','title'=>'难过'),array('code'=>'[冷汗]','src'=>'p_qq_16.gif','title'=>'冷汗'),array('code'=>'[抓狂]','src'=>'p_qq_17.gif','title'=>'抓狂'),array('code'=>'[呕吐]','src'=>'p_qq_18.gif','title'=>'呕吐'),array('code'=>'[偷笑]','src'=>'p_qq_19.gif','title'=>'偷笑'),array('code'=>'[可爱]','src'=>'p_qq_20.gif','title'=>'可爱'),array('code'=>'[白眼]','src'=>'p_qq_21.gif','title'=>'白眼'),array('code'=>'[傲慢]','src'=>'p_qq_22.gif','title'=>'傲慢'),array('code'=>'[饥饿]','src'=>'p_qq_23.gif','title'=>'饥饿'),array('code'=>'[困]','src'=>'p_qq_24.gif','title'=>'困'),array('code'=>'[惊恐]','src'=>'p_qq_25.gif','title'=>'惊恐'),array('code'=>'[流汗]','src'=>'p_qq_26.gif','title'=>'流汗'),array('code'=>'[憨笑]','src'=>'p_qq_27.gif','title'=>'憨笑'),array('code'=>'[大兵]','src'=>'p_qq_28.gif','title'=>'大兵'),array('code'=>'[奋斗]','src'=>'p_qq_29.gif','title'=>'奋斗'),array('code'=>'[合掌]','src'=>'p_qq_30.gif','title'=>'合掌'),array('code'=>'[疑问]','src'=>'p_qq_31.gif','title'=>'疑问'),array('code'=>'[嘘]','src'=>'p_qq_32.gif','title'=>'嘘'),array('code'=>'[晕]','src'=>'p_qq_33.gif','title'=>'晕'),array('code'=>'[折磨]','src'=>'p_qq_34.gif','title'=>'折磨'),array('code'=>'[飞吻]','src'=>'p_qq_35.gif','title'=>'飞吻'),array('code'=>'[敲打]','src'=>'p_qq_36.gif','title'=>'敲打'),array('code'=>'[再见]','src'=>'p_qq_37.gif','title'=>'再见'),array('code'=>'[擦汗]','src'=>'p_qq_38.gif','title'=>'擦汗'),array('code'=>'[抠鼻]','src'=>'p_qq_39.gif','title'=>'抠鼻'),array('code'=>'[糗大了]','src'=>'p_qq_40.gif','title'=>'糗大了'),array('code'=>'[坏笑]','src'=>'p_qq_41.gif','title'=>'坏笑'),array('code'=>'[左哼哼]','src'=>'p_qq_42.gif','title'=>'左哼哼'),array('code'=>'[右哼哼]','src'=>'p_qq_43.gif','title'=>'右哼哼'),array('code'=>'[哈欠]','src'=>'p_qq_44.gif','title'=>'哈欠'),array('code'=>'[鄙视]','src'=>'p_qq_45.gif','title'=>'鄙视'),array('code'=>'[委屈]','src'=>'p_qq_46.gif','title'=>'委屈'),array('code'=>'[快哭了]','src'=>'p_qq_47.gif','title'=>'快哭了'),array('code'=>'[阴险]','src'=>'p_qq_48.gif','title'=>'阴险'),array('code'=>'[亲亲]','src'=>'p_qq_49.gif','title'=>'亲亲'),array('code'=>'[吓]','src'=>'p_qq_50.gif','title'=>'吓'),array('code'=>'[可怜]','src'=>'p_qq_51.gif','title'=>'可怜'),array('code'=>'[拥抱]','src'=>'p_qq_52.gif','title'=>'拥抱'),array('code'=>'[月亮]','src'=>'p_qq_53.gif','title'=>'月亮'),array('code'=>'[太阳]','src'=>'p_qq_54.gif','title'=>'太阳'),array('code'=>'[鼓掌]','src'=>'p_qq_55.gif','title'=>'鼓掌'),array('code'=>'[示爱]','src'=>'p_qq_56.gif','title'=>'示爱'),array('code'=>'[爱情]','src'=>'p_qq_57.gif','title'=>'爱情'),array('code'=>'[玫瑰]','src'=>'p_qq_58.gif','title'=>'玫瑰'),array('code'=>'[西瓜]','src'=>'p_qq_59.gif','title'=>'西瓜'),array('code'=>'[咖啡]','src'=>'p_qq_60.gif','title'=>'咖啡'),array('code'=>'[饭]','src'=>'p_qq_61.gif','title'=>'饭'),array('code'=>'[爱心]','src'=>'p_qq_62.gif','title'=>'爱心'),array('code'=>'[强]','src'=>'p_qq_63.gif','title'=>'强'),array('code'=>'[弱]','src'=>'p_qq_64.gif','title'=>'弱'),array('code'=>'[握手]','src'=>'p_qq_65.gif','title'=>'握手'),array('code'=>'[胜利]','src'=>'p_qq_66.gif','title'=>'胜利'),array('code'=>'[抱拳]','src'=>'p_qq_67.gif','title'=>'抱拳'),array('code'=>'[挑衅]','src'=>'p_qq_68.gif','title'=>'挑衅'),array('code'=>'[好]','src'=>'p_qq_69.gif','title'=>'好'),array('code'=>'[不]','src'=>'p_qq_70.gif','title'=>'不'),
//            ),
        );
        print_r(json_encode($parameters));exit;
        Common::verify($parameters, '/index/index');
    }
    //首页活动列表
    public function getEventListAction(){
        $parameters = array(
            'token' =>'a1a271b917706fc009a0838ba1087b40',
            'type'=>4,
            'page'=>1,
            'size'=>10,
            'lng'=>'121.526983',
            'lat'=>'31.236281'
        );
        Common::verify($parameters, '/index/getEventList');
    }
    //首页演出列表
    public function performListAction(){
        $parameters = array(
            'token' =>'a1a271b917706fc009a0838ba1087b40',
            'page'=>1,
            'size'=>1
        );
        Common::verify($parameters, '/index/performList');
    }
    //热门推荐列表
    public function hotPushListAction(){
        $parameters = array(
            'token' =>'862f39b54ba0aad8efb31f4621d13113',
            'page'=>'1',
            'size'=>5
        );
        Common::verify($parameters, '/index/hotPushList');
    }
    //首页文化天使换一组
    public function getIndexAngelAction(){
        $parameters = array(
            'token' =>'862f39b54ba0aad8efb31f4621d13113',
        );
        Common::verify($parameters, '/index/getIndexAngel');
    }
    //2.0首页
    public function indexViewAction(){
        $parameters = array(
            'token' =>'ca03473fe364d252c9ed1538f581280a',
        );
        Common::verify($parameters, '/index/indexView');
    }
    //O2O查看培训，场馆列表
    public function getBigTypeListAction(){
        $parameters = array(
            'token' =>'d967294ba4d5155e78556294f796e13b',
            'type' => 1
        );
        Common::verify($parameters, '/index/getBigTypeList');
    }
    //O2O查看展演列表
    public function getSmallTypeListAction(){
        $parameters = array(
            //'token' =>'54d7ed519214c24e93463c39585106bc',
            'code'=>'pj'
        );
        Common::verify($parameters, '/index/getSmallTypeList');
    }
    //2.0首页
    public function goodListAction(){
        $parameters = array(
            'token' =>'a5178f22283797c0d477c66b300315d6',
            'page' =>1,
            'size'=>10
        );
        Common::verify($parameters, '/index/goodList');
    }
    //场馆列表
    public function getSiteListAction(){
        $parameters = array(
            'token' =>'a5178f22283797c0d477c66b300315d6',
            'page' =>1,
            'size'=>10
        );
        Common::verify($parameters, '/index/getSiteList');
    }
    //首页服务驿站更多
    public function getBusinessListAction(){
        $parameters = array(
            'token' =>'a5178f22283797c0d477c66b300315d6',
            'last_id' =>1026,
            'size'=>10
        );
        Common::verify($parameters, '/index/getBusinessList');
    }
}