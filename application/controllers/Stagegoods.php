<?php
class StagegoodsController extends Yaf_Controller_Abstract{
    public function init(){
        $this->startTime = microtime(true);
    }
    //详情页
    public function indexAction(){
        $id =  $this->getRequest()->get('id');
        $sp = $this->getRequest()->get('sp') ? $this->getRequest()->get('sp') : '' ;
        $version = $this->getRequest()->get("version") ? $this->getRequest()->get("version") : APP_VERSION;
        $data['token'] = $this->getRequest()->get('token');
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid=$user['uid'];
            $this->getView()->user = $user;
        }
        $stageGoodsModel = new StagegoodsModel();
        $visitModel = new VisitModel();
        $stageModel = new StageModel();
        $feedModel = new FeedModel();
        $likeModel = new LikeModel();
        $collectModel = new CollectModel();
        $goodsInfo = $stageGoodsModel->getGoodsDetailById($id);
        $goodsInfo['is_collect'] = $collectModel->hasData(12,$id,$uid);
        if(!$goodsInfo || $goodsInfo['status'] > 1){
            Common::redirect(I_DOMAIN.'/index/error?type=2');
        }
        if($sp){
            $sp_str = base64_decode(base64_decode($sp));
            $sp_arr = explode('-',$sp_str);
            $visitModel->addSpVisitNum($sp_arr[0],$sp_arr[1]);
        }
        $goodsInfo['intro'] = Common::linkReplace(Common::replaceStyle(str_replace("\n","<br>",$goodsInfo['intro'])));
        $visitModel->addVisitNum('stagegoods',$id);
        $goodsInfo['view_num'] = $visitModel->getVisitNum('stagegoods',$id);//添加浏览数
        $goodsInfo['is_like'] = $likeModel->hasData(12,$id,$uid);
        $this->getView()->is_join = $stageModel->joinStageRole($goodsInfo['sid'],$uid);//当前用户是否加入该驿站及加入驿站信息
        $this->getView()->moreGoods  = $stageGoodsModel->getGoodsForHtml(4,$id,$version,$data['token'],$goodsInfo['sid']);//看了又看（本驿站）
        $this->getView()->lookGoods  = $stageGoodsModel->getGoodsForHtml(4,$id,$version,$data['token'],$goodsInfo['sid'],2);//看了又看（非本驿站）`
        $this->getView()->stageInfo = $stageModel->getBasicStageBySid($goodsInfo['sid']);//驿站信息
        $this->getView()->goodsInfo  = $goodsInfo;
        $this->getView()->commentList = $feedModel->getCommentList($uid,12,$id,1,3,1);
        $this->getView()->buyList = $stageGoodsModel->getBuyListForHtml($id,3);//购买记录
        $this->getView()->like_list = $likeModel->likeList($id,12,1,6,$uid);//喜欢列表
        $this->getView()->version = $version;
        $this->getView()->token = $data['token'];
        $this->getView()->type = 12;
        $this->getView()->obj_id = $goodsInfo['id'];
        $this->getView()->page_title  = '商品详情';
        $this->getView()->app_css = 'topic';
        $this->getView()->sp = $sp;
        $this->display("detail");
    }
    //发布
    public function addAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(22, "非法登录用户");
        }
        $uid = $user['uid'];
        $name = trim($this->getRequest()->getPost("name"));//商品名称
        $cover = $this->getRequest()->getPost("cover");//封面
        $intro = $this->getRequest()->getPost("intro");//内容
        $type= $this->getRequest()->getPost("type");//类型 1金额 2金额+福报值 3福报值
        $num = $this->getRequest()->getPost("num");//数量
        $origin=$this->getRequest()->getPost("origin");//1.PCweb 2.移动web 3.IOS 4.Android
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $start_time =$this->getRequest()->getPost("start_time");
        $cate_id =$this->getRequest()->getPost("cate_id");
        $city =$this->getRequest()->getPost("city_id");
        $intro_img = $this->getRequest()->getPost("intro_img");//帖子内容图片 多个 & 拼接
        $price =(string)$this->getRequest()->getPost("price");//金额
        $score = (string)$this->getRequest()->getPost("score");
        $stageModel = new StageModel();
        $sid = $stageModel->getSidByUid($uid);
        if(!$sid){
            Common::echoAjaxJson(2,'你没有发布的权限');
        }
        if($sid['is_pay']==0){
            Common::echoAjaxJson(31,'请联系小管家开通支付功能，联系电话：13012888193');
        }
        if(!$cate_id){
            Common::echoAjaxJson(23, "商品分类id为空");
        }
        if(!$city){
            Common::echoAjaxJson(24, "城市id为空");
        }
        $addressModel = new AddressModel();
        $parent = $addressModel->parent($city);
        $province = $parent['pid'];
        $security = new Security();
        $intro = $security->xss_clean($intro);
        if($intro_img){
            $intro_imgArr = explode('&',$intro_img);
            foreach($intro_imgArr as $v){
                $intro = str_replace($v,'<img src="http://img.91ddcc.com/'.$v.'">',$intro);
            }
        }
        if(!$cover){
            Common::echoAjaxJson(3,'请上传商品封面');
        }
        $cover_array = explode('&',$cover);
        if(count($cover_array)>5||count($cover_array)<1){
            Common::echoAjaxJson(32,'请上传1~5张商品封面');
        }
        if(!$name){
            Common::echoAjaxJson(4,'请输入商品名称');
        }
        if(preg_match('/[A-Za-z]{1,}/',$name)){
            Common::echoAjaxJson(5,'标题不能包含英文字符');
        }
        $name_len = mb_strlen($name,'utf-8');
        if($name_len < 1 || $name_len > 30){
            Common::echoAjaxJson(6,'请输入1-30个中文作为商品名称');
        }
        if($intro===''){
            Common::echoAjaxJson(7, "请填写商品详情");
        }
        if(!$type||!in_array($type,array(1,2,3))){
            Common::echoAjaxJson(9, "请至少选择一种购买方式");
        }
        if(in_array($type,array(1,2))){
            if(!$price){
                Common::echoAjaxJson(10, "请输入销售价格");
            }
            if(!preg_match('/^[0-9]{1,8}+(.[0-9]{1,2})?$/',$price)){
                Common::echoAjaxJson(11,'人民币上限为一千万，小数点后最多保存两位。');
            }
        }
        if(in_array($type,array(2,3))){
            if(!$score){
                Common::echoAjaxJson(12,'请输入福报值数额');
            }
            if($score>10000000){
                Common::echoAjaxJson(13,'福报值最大值不能超过一千万');
            }
        }
        $score = ($type==1)?0:$score;
        $price = ($type==3)?0:$price;
        if(!$num){
            Common::echoAjaxJson(14, "请输入正确的商品库存");
        }
        if($num>10000000){
            Common::echoAjaxJson(15,'库存最大值不能超过一千万');
        }
        if(!$start_time){
            Common::echoAjaxJson(17, "请选择商品上架时间");
        }
        $data = array(
            'name'=>$name,
            'uid' => $uid,
            'sid' => $sid['sid'],
            'origin'=>$origin,
            'cover'=>$cover_array[0],
            'intro' => $intro,
            'type' => $type,
            'price' => $price,
            'score' => $score,
            'num' => $num,
            'start_time'=>$start_time,
            'end_time'=>date('Y-m-d H:i',strtotime('+100 year',strtotime($start_time))),
            'is_img'=>1,
            'imgArr'=>$cover_array,
            'cate_id'=>$cate_id ? $cate_id : 0,
            'province'=>$province,
            'city'=>$city
        );
        $stagegoodsModel = new StagegoodsModel();
        $rs = $stagegoodsModel->add($data);
        if($rs == 0){
            Common::echoAjaxJson(19, "发表失败");
        }
        if($rs == -1){
            Common::echoAjaxJson(20, '抱歉！该驿站已被关闭，由于该驿站违反了社区的相关规定');
        }
        if($rs == -2){
            Common::echoAjaxJson(21, '请您先加入该驿站');
        }
        $stage_info = $stageModel->getBasicStageBySid($sid['sid']);
        Common::appLog('stagegoods/add',$this->startTime,$version);
        Common::echoAjaxJson(1, "发布成功",array('url'=>I_DOMAIN.'/g/'.$rs.'?token='.$_POST['token'].'&version='.$version,'sid'=>$sid['sid'],'is_sp_agreement'=>$stage_info['is_sp_agreement'],'is_extend'=>$stage_info['is_extend']));
    }
    //修改
    public function updateAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(24, "非法登录用户");
        }
        $uid = $user['uid'];
        $stageModel = new StageModel();
        $stagegoodsModel = new StagegoodsModel();
        $sid = $stageModel->getSidByUid($uid);
        if(!$sid){
            Common::echoAjaxJson(2,'你没有修改的权限');
        }
        $goods_id = $this->getRequest()->getPost("id");//商品id
        if(!$goods_id){
            Common::echoAjaxJson(3,'商品id为空');
        }
        $rs = $stagegoodsModel->getStartType($goods_id);//获取上下架状态
        if($rs == -1){
            Common::echoAjaxJson(4,'该商品已不存在');
        }
        $start_time =$this->getRequest()->getPost("start_time");
        $is_recommend = $this->getRequest()->getPost("is_recommend");//是否推荐
        $name = trim($this->getRequest()->getPost("name"));//商品名称
        $cover = $this->getRequest()->getPost("cover");//封面
        $intro = $this->getRequest()->getPost("intro");//内容
        $type= $this->getRequest()->getPost("type");//类型 1金额 2金额+福报值 3福报值
        $num = $this->getRequest()->getPost("num");//数量 3.7以下版本为商品总数 3.7及以上版本为库存数
        $img = $this->getRequest()->getPost("img");//图片 多个 & 拼接
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $price =(string)$this->getRequest()->getPost("price");//金额
        $score = (string)$this->getRequest()->getPost("score");
        $difference = (string)$this->getRequest()->getPost("difference");//库存差值
        $cate_id =$this->getRequest()->getPost("cate_id");
        $city =$this->getRequest()->getPost("city_id");
        $intro_img = $this->getRequest()->getPost("intro_img");//帖子内容图片 多个 & 拼接
        if(!$cate_id){
            Common::echoAjaxJson(25, "商品分类id为空");
        }
        if(!$city){
            Common::echoAjaxJson(26, "城市id为空");
        }
        $addressModel = new AddressModel();
        $parent = $addressModel->parent($city);
        $province = $parent['pid'];
        $security = new Security();
        $intro = $security->xss_clean($intro);
        if($intro_img){
            $intro_imgArr = explode('&',$intro_img);
            foreach($intro_imgArr as $v){
                $intro = str_replace($v,'<img src="http://img.91ddcc.com/'.$v.'">',$intro);
            }
        }
        $stockInfo = $stagegoodsModel->getStockNum($goods_id);
        $new_stock = $stockInfo['stock_num'] +$difference;
        if($new_stock<0){
            $stock_num = 0;
            $totals = $stockInfo['num'] - $stockInfo['stock_num'];
        }else{
            $stock_num = $new_stock;
            $totals = $stockInfo['num']+$difference;
        }
        if(!$cover){
            Common::echoAjaxJson(7,'请上传商品封面');
        }
        $cover_array = explode('&',$cover);
        if(count($cover_array)>5||count($cover_array)<1){
            Common::echoAjaxJson(32,'请上传1~5张商品封面');
        }
        if(!$name){
            Common::echoAjaxJson(8,'请输入商品名称');
        }
        if(preg_match('/[A-Za-z]{1,}/',$name)){
            Common::echoAjaxJson(9,'标题不能包含英文字符');
        }
        $name_len = mb_strlen($name,'utf-8');
        if($name_len < 1 || $name_len > 30){
            Common::echoAjaxJson(10,'请输入1-30个中文作为商品名称');
        }
        if($intro===''){
            Common::echoAjaxJson(11, "请填写商品详情");
        }
        if(!$type||!in_array($type,array(1,2,3))){
            Common::echoAjaxJson(13, "请至少选择一种购买方式");
        }
        if(in_array($type,array(1,2))){
            if(!$price){
                Common::echoAjaxJson(14, "请输入销售价格");
            }
            if(!preg_match('/^[0-9]{1,8}+(.[0-9]{1,2})?$/',$price)){
                Common::echoAjaxJson(15,'人民币上限为一千万，小数点后最多保存两位。');
            }
        }
        if(in_array($type,array(2,3))){
            if(!$score){
                Common::echoAjaxJson(16,'请输入福报值数额');
            }
            if($score>10000000){
                Common::echoAjaxJson(17,'福报值最大值不能超过一千万');
            }
        }
        $score = ($type==1)?0:$score;
        $price = ($type==3)?0:$price;
        if($num>10000000){
            Common::echoAjaxJson(19,'库存最大值不能超过一千万');
        }
        if(!$start_time){
            Common::echoAjaxJson(20, "请选择商品上架时间");
        }
        $data = array(
            'name'=>$name,
            'uid' => $uid,
            'sid' => $sid['sid'],
            'cover'=>$cover_array[0],
            'intro' => $intro,
            'type' => $type,
            'price' => $price,
            'score' => $score,
            'num' => $totals,
            'stock_num'=>$stock_num,
            'start_time'=>$start_time,
            'end_time'=>date('Y-m-d H:i',strtotime('+100 year',strtotime($start_time))),
            'is_img'=>$img ? 1 : 0,
            'is_recommend'=>$is_recommend,
            'imgArr'=>$cover_array,
            'cate_id'=>$cate_id ? $cate_id : 0,
            'province'=>$province,
            'city'=>$city,
            'id'=>$goods_id
        );
        $result = $stagegoodsModel->update($data);
        if(!$result){
            Common::echoAjaxJson(23, "修改失败");
        }
        Common::appLog('stagegoods/update',$this->startTime,$version);
        Common::echoAjaxJson(1, "修改成功");
    }
    //管理中心--获取某一个商品信息
    public function getInfoAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $goods_id = $this->getRequest()->getPost("id");//商品id
        if(!$goods_id){
            Common::echoAjaxJson(2,'商品id为空');
        }
        $stagegoodsModel = new StagegoodsModel();
        $info = $stagegoodsModel->getInfo($goods_id);
        if($info == -1){
            Common::echoAjaxJson(3,'该商品已不存在');
        }
        $r_num = $stagegoodsModel->getRecommendNum($info['sid']);//驿站商品推荐总数
        $info['stock_recommend'] = (3-$r_num);
        $info['url']= I_DOMAIN.'/g/'.$goods_id.'?token='.$_POST['token'].'&version='.$version.'';
        Common::appLog('stagegoods/getInfo',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$info);
    }
    //管理中心--某个驿站下的商品列表
    public function getGoodsListBySidAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $sid = $this->getRequest()->getPost("sid");//驿站id
        $start_type = $this->getRequest()->getPost("start_type");// 0全部 1待售 2上架 3下架
        $page = $this->getRequest()->getPost('page') ? $this->getRequest()->getPost('page') :1;
        $size = ($this->getRequest()->getPost('size')&&$this->getRequest()->getPost('size')==20) ? $this->getRequest()->getPost('size') : 20;
        $stagegoodsModel = new StagegoodsModel();
        $list = $stagegoodsModel->getGoodsListBySid($sid,$start_type,(int)$page,(int)$size,$version,$_POST['token']);
        Common::appLog('stagegoods/getGoodsListBySid',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$list ? $list : array());
    }
    //下单页获取数据
    public function getInfoForAddOrderAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(8, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $goods_id = $this->getRequest()->getPost("id");//商品id
        if(!$goods_id){
            Common::echoAjaxJson(2, "商品id为空");
        }
        $stagegoodsModel = new StagegoodsModel();
        $info = $stagegoodsModel->getInfo($goods_id);
        if(!$info||$info['status']>1){
            Common::echoAjaxJson(3, "该商品已不存在");
        }
        if($info['start_type']==3){
            Common::echoAjaxJson(4, "该商品已下架");
        }
        if($info['stock_num']<1){
            Common::echoAjaxJson(5, "对不起，商品已售完");
        }
        if($info['start_type']==1){
            Common::echoAjaxJson(6, "该商品还未上架");
        }
        $addressModel = new AddressModel();
        $stageModel = new StageModel();
        $default_shipping =$addressModel->getDefaultShipping($uid);
        if(!$default_shipping){
            $data['default_shipping'] = (object)array();
        }else{
            $data['default_shipping']['address_id'] = $default_shipping[0]['address_id'];
            $data['default_shipping']['consignee_name'] = $default_shipping[0]['consignee_name'];
            $data['default_shipping']['detail_address'] = $default_shipping[0]['detail_address'];
            $data['default_shipping']['phone'] = $default_shipping[0]['phone'];
            $data['default_shipping']['province_name'] = $default_shipping[0]['province_name'];
            $data['default_shipping']['city_name'] = $default_shipping[0]['city_name'];
            $data['default_shipping']['town_name'] = $default_shipping[0]['town_name'];
        }
        $stageInfo = $stageModel->getBasicStageBySid($info['sid']);
        if($stageInfo['status']>1){
            Common::echoAjaxJson(7, "该驿站涉嫌违规，已不存在");
        }
        $data['stage']['sid'] = $stageInfo['sid'];
        $data['stage']['name'] = $stageInfo['name'];
        $data['stage']['icon'] = IMG_DOMAIN.$stageInfo['icon'];
        $data['info']['id'] = $info['id'];
        $data['info']['name'] = $info['name'];
        $data['info']['cover'] = IMG_DOMAIN.$info['cover'];
        $data['info']['type'] = $info['type'];
        $data['info']['price'] = $info['price'];
        $data['info']['score'] = $info['score'];
        $data['info']['stock_num'] = $info['stock_num'];
        $data['info']['start_time'] = $info['start_time'];
        $data['info']['end_time'] = $info['end_time'];
        $data['user_info']['uid'] = $user['uid'];
        $data['user_info']['score'] = $user['score'];
        Common::appLog('stagegoods/getInfoForAddOrder',$this->startTime,$version);
        Common::echoAjaxJson(1, "获取成功",$data);
    }
    //下架或者删除
    public function delOrEndAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(7, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $uid = $user['uid'];
        $stageModel = new StageModel();
        $stagegoodsModel = new StagegoodsModel();
        $sid = $stageModel->getSidByUid($uid);
        if(!$sid){
            Common::echoAjaxJson(2,'你没有修改的权限');
        }
        $goods_id = $this->getRequest()->getPost("id");//商品id
        if(!$goods_id){
            Common::echoAjaxJson(3,'商品id为空');
        }
        $rs = $stagegoodsModel->getStartType($goods_id);//获取上下架状态
        if($rs == -1){
            Common::echoAjaxJson(4,'该商品已不存在');
        }
        $type = $this->getRequest()->getPost("type") ? $this->getRequest()->getPost("type") :'del';
        if($type=='del'){
            $message = '删除商品';
        }
        if($type=='end'){
            $message = '商品下架';
        }
        $result = $stagegoodsModel->delOrEnd($goods_id,$type);
        if($result==-1){
            Common::echoAjaxJson(6,'该商品有未完成订单，不可删除');
        }
        if(!$result){
            Common::echoAjaxJson(5,''.$message.'失败');
        }
        Common::appLog('stagegoods/delOrEnd',$this->startTime,$version);
        Common::echoAjaxJson(1,''.$message.'成功');
    }
    //添加订单
    public function addOrderAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(13, "非法登录用户");
        }
        $uid = $user['uid'];
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $goods_id = $this->getRequest()->getPost("id");//商品id
        $address_id = $this->getRequest()->getPost("address_id");//收货地址id
        $num = $this->getRequest()->getPost("num");//下单数量
        $price_totals = $this->getRequest()->getPost("price_totals") ? $this->getRequest()->getPost("price_totals") : 0;//金额总计
        $score_totals = $this->getRequest()->getPost("score_totals") ? $this->getRequest()->getPost("score_totals") : 0;//福报值总计
        $message = $this->getRequest()->getPost("message");//给驿长留言
        $sp = $this->getRequest()->getPost("sp") ? $this->getRequest()->getPost("sp") :'';//分享有奖信息
        if(!$goods_id){
            Common::echoAjaxJson(2, "商品id为空");
        }
        $stagegoodsModel = new StagegoodsModel();
        $info = $stagegoodsModel->getInfo($goods_id);
        if(!$info||$info['status']>1){
            Common::echoAjaxJson(3, "该商品已不存在");
        }
        if($info['start_type']==3){
            Common::echoAjaxJson(4, "该商品已下架");
        }
        if($info['stock_num']<1){
            Common::echoAjaxJson(5, "对不起，商品已售完");
        }
        if($info['start_type']==1){
            Common::echoAjaxJson(6, "该商品还未上架");
        }
        if(!$address_id){
            Common::echoAjaxJson(7, "收货地址id为空");
        }
        if(!$num){
            Common::echoAjaxJson(8, "购买数量不能为空");
        }
        if($num >$info['stock_num']){
            Common::echoAjaxJson(9, "购买数量不能超出库存数");
        }
        if($message){
            $security = new Security();
            $message = $security->xss_clean($message);
            $message_len = mb_strlen($message,'utf-8');
            if($message_len>100){
                Common::echoAjaxJson(10, "给驿长留言不能超过100字");
            }
        }
        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfoByUid($user['uid']);
        if($score_totals>$userInfo['score']){
            Common::echoAjaxJson(11, "对不起,您的福报值不够");
        }
        //生成订单号 16位
        $time = time();
        $order_num = '0001';
        $last_order_id = $stagegoodsModel->getLastOrderId();
        if(!$last_order_id||(int)substr($last_order_id,1,10)!=$time){
            $order_id = 'S'.$time.$order_num;
        }else{
            $last = (int)substr($last_order_id,1,15);
            $new = (int)$last+1;
            $order_id = 'S'.$new;
        }
        $data=array(
            'goods_id'=>$goods_id,
            'sid'=>$info['sid'],
            'order_id'=>$order_id,
            'uid'=>$uid,
            'num'=>$num,
            'price_totals'=>$price_totals,
            'score_totals'=>$score_totals,
            'address_id'=>$address_id,
            'message'=>$message ? $message :'',
            'sp'=>$sp ? $sp : ''
        );
        $rs = $stagegoodsModel->addOrder($data);
        if(!$rs){
            Common::echoAjaxJson(12, "添加失败");
        }
        if($score_totals){
            $scoreModel = new ScoreModel();
            if($info['type']==3){
                $stagegoodsModel->updateOrderStatus(2,$order_id);
                $stagegoodsModel->updatePayTime($order_id);
                Common::addNoticeAndSmsForGoods(1,$order_id);
            }
            //插入福报值信息
            $scoreModel->add($uid,0,'stagegoods',$goods_id,$score_totals);
        }
        $p_totals = $num*$info['price'];
        $s_totals = $num*$info['score'];
        Common::appLog('stagegoods/addOrder',$this->startTime,$version);
        Common::echoAjaxJson(1,'添加成功',array('id'=>$rs,'order_id'=>$order_id,'num'=>$num,'price_totals'=> sprintf("%.2f", $p_totals),'score_totals'=>$s_totals));
    }
    //添加快递单号
    public function addCourierNumberAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $img = $this->getRequest()->getPost("img");
        $order_id = $this->getRequest()->getPost("order_id");
        $logistics_number = $this->getRequest()->getPost("logistics_number");//快递单号
        $logistics_company = $this->getRequest()->getPost("logistics_company");//快递公司
        $logistics_type = $this->getRequest()->getPost("logistics_type");//快递公司type
        $logistics_tel = $this->getRequest()->getPost("logistics_tel");//快递公司tel
        if(!$order_id){
            Common::echoAjaxJson(2, "订单号为空");
        }
        $stagegoodsModel = new StagegoodsModel();
        if($version<'3.7.2'){
            if(!$img){
                Common::echoAjaxJson(3, "快递单为空");
            }
            $rs = $stagegoodsModel->addCourierImg($img,$order_id);
        }else{
            if(!$logistics_number||!$logistics_company||!$logistics_type){
                Common::echoAjaxJson(3, "快递单号（公司）为空");
            }
//            $logistics_company_len = mb_strlen($logistics_company,'utf-8');
//            if($logistics_company_len < 1 || $logistics_company_len > 10){
//                Common::echoAjaxJson(6,'请填写1-10个字的物流公司名称');
//            }
            $rs = $stagegoodsModel->addLogisticsNumber($logistics_number,$logistics_company,$logistics_type,$logistics_tel,$order_id);
        }
        if(!$rs){
            Common::echoAjaxJson(4, "添加失败");
        }
        //发货系统通知
        $orderInfo = $stagegoodsModel->getOrderInfoByOrderId($order_id);
        if($user['uid']!=$orderInfo['uid']){
            Common::addNoticeAndSmsForGoods(2,$order_id);
        }
        Common::appLog('stagegoods/addCourierNumber',$this->startTime,$version);
        Common::echoAjaxJson(1,'发货成功');
    }
    //发货
    public function updateCourierImgAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $img = $this->getRequest()->getPost("img");
        $order_id = $this->getRequest()->getPost("order_id");
        $logistics_number = $this->getRequest()->getPost("logistics_number");//快递单号
        $logistics_company = $this->getRequest()->getPost("logistics_company");//快递公司
        $logistics_type = $this->getRequest()->getPost("logistics_type");//快递公司type
        $logistics_tel = $this->getRequest()->getPost("logistics_tel");//快递公司tel
        if(!$order_id){
            Common::echoAjaxJson(2, "订单号为空");
        }
        $stagegoodsModel = new StagegoodsModel();
        if($version<'3.7.2'){
            if(!$img){
                Common::echoAjaxJson(3, "快递单为空");
            }
            $rs = $stagegoodsModel->updateCourierImg($img,$order_id);
        }else{
            if(!$logistics_number||!$logistics_company||!$logistics_type){
                Common::echoAjaxJson(3, "快递单号（公司）为空");
            }
//            $logistics_company_len = mb_strlen($logistics_company,'utf-8');
//            if($logistics_company_len < 1 || $logistics_company_len > 10){
//                Common::echoAjaxJson(6,'请填写1-10个字的物流公司名称');
//            }
            $rs = $stagegoodsModel->updateLogisticsNumber($logistics_number,$logistics_company,$logistics_type,$logistics_tel,$order_id);
        }
        if(!$rs){
            Common::echoAjaxJson(4, "添加失败");
        }
        Common::appLog('stagegoods/updateCourierImg',$this->startTime,$version);
        Common::echoAjaxJson(1,'发货成功');
    }
    //订单详情
    public function orderInfoAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $id = $this->getRequest()->getPost("id");//订单id
        if(!$id){
            Common::echoAjaxJson(2, "订单id为空");
        }
        $stagegoodModel = new StagegoodsModel();
        $data = $stagegoodModel->getOrderInfo($id,$_POST['token'],$version);
        if(!$data){
            Common::echoAjaxJson(3, "订单不存在");
        }
        Common::appLog('stagegoods/orderInfo',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$data);
    }
    //我买到的--我下单的订单列表
    public function getMyOrderListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $uid = $user['uid'];
        $order_status = $this->getRequest()->getPost('order_status');//订单状态 1待付款 2待发货 6已发货 7已完成
        $page = (int)$this->getRequest()->get('page') ? (int)$this->getRequest()->get('page') : 1;
        $size = ((int)$this->getRequest()->get('size') &&(int)$this->getRequest()->get('size')==20) ? (int)$this->getRequest()->get('size') : 20;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $stagegoodsModel = new StagegoodsModel();
        $list = $stagegoodsModel->getMyOrderList($uid,$order_status,$page,$size);
        Common::appLog('stagegoods/getMyOrderList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    //我卖出的
    public function getSellOrderListAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $stageModel = new StageModel();
        $sid = $stageModel->getSidByUid($user['uid']);
        $order_status = $this->getRequest()->getPost('order_status');//订单状态 1待付款 2待发货 6已发货 7已完成
        $page = (int)$this->getRequest()->get('page') ? (int)$this->getRequest()->get('page') : 1;
        $size = ((int)$this->getRequest()->get('size') &&(int)$this->getRequest()->get('size')==20) ? (int)$this->getRequest()->get('size') : 20;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $stagegoodsModel = new StagegoodsModel();
        $list = $stagegoodsModel->getSellOrderList($sid['sid'],$order_status,$page,$size);
        Common::appLog('stagegoods/getSellOrderList',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    //延迟收货
    public function setDeferAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $id = $this->getRequest()->getPost("id");//订单id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2, "订单id为空");
        }
        $stagegoodModel = new StagegoodsModel();
        $rs = $stagegoodModel->setDefer($id,$_POST['token'],$version);
        if($rs==-1){
            Common::echoAjaxJson(3, "订单不存在");
        }
        if(!$rs){
            Common::echoAjaxJson(4, "延迟收货失败");
        }
        //延迟收货系统通知
        $orderInfo = $stagegoodModel->orderInfoById($id);
        $goods_info = $stagegoodModel->getInfo($orderInfo['goods_id']);
        if($user['uid']!=$goods_info['uid']){
            Common::addNoticeAndSmsForGoods(4,$orderInfo['order_id']);
        }
        Common::appLog('stagegoods/setDefer',$this->startTime,$version);
        Common::echoAjaxJson(1, "延迟收货成功");
    }
    //确认收货
    public function setTakeTimeAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(5, "非法登录用户");
        }
        $id = $this->getRequest()->getPost("id");//订单id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2, "订单id为空");
        }
        $stagegoodModel = new StagegoodsModel();
        $rs = $stagegoodModel->setTakeTime($id,$_POST['token'],$version);
        if($rs==-1){
            Common::echoAjaxJson(3, "订单不存在");
        }
        if(!$rs){
            Common::echoAjaxJson(4, "确认收货失败");
        }
        //确认收货系统通知
        $orderInfo = $stagegoodModel->orderInfoById($id);
        $goods_info = $stagegoodModel->getInfo($orderInfo['goods_id']);
        if($user['uid']!=$goods_info['uid']){
            Common::addNoticeAndSmsForGoods(3,$orderInfo['order_id']);
        }
        Common::appLog('stagegoods/setTakeTime',$this->startTime,$version);
        Common::echoAjaxJson(1, "确认收货成功");
    }
    public function previewAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(22, "非法登录用户");
        }
        $uid = $user['uid'];
        $name = trim($this->getRequest()->getPost("name"));//商品名称
        $cover = $this->getRequest()->getPost("cover");//封面
        $intro = $this->getRequest()->getPost("intro");//内容
        $type= $this->getRequest()->getPost("type");//类型 1金额 2金额+福报值 3福报值
        $num = $this->getRequest()->getPost("num");//数量
        $img = $this->getRequest()->getPost("img");//图片 多个 & 拼接
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $start_time =$this->getRequest()->getPost("start_time");
        $price =(string)$this->getRequest()->getPost("price");//金额
        $score = (string)$this->getRequest()->getPost("score");
        $stageModel = new StageModel();
        $addressModel = new AddressModel();
        $userModel = new UserModel();
        $sid = $stageModel->getSidByUid($uid);
        $cate_id = '';$city = '';$province='';
        if(!$sid){
            Common::echoAjaxJson(2,'你没有发布的权限');
        }
        if($sid['is_pay']==0){
            Common::echoAjaxJson(31,'请联系小管家开通支付功能，联系电话：13012888193');
        }
        $cate_id =$this->getRequest()->getPost("cate_id");
        $city =$this->getRequest()->getPost("city_id");
        $intro_img = $this->getRequest()->getPost("intro_img");//帖子内容图片 多个 & 拼接
        if(!$cate_id){
            Common::echoAjaxJson(23, "商品分类id为空");
        }
        if(!$city){
            Common::echoAjaxJson(24, "城市id为空");
        }
        $parent = $addressModel->parent($city);
        $province = $parent['pid'];
        $security = new Security();
        $intro = $security->xss_clean($intro);
        if($intro_img){
            $intro_imgArr = explode('&',$intro_img);
            foreach($intro_imgArr as $v){
                $intro = str_replace($v,'<img src="http://img.91ddcc.com/'.$v.'">',$intro);
            }
        }
        if(!$cover){
            Common::echoAjaxJson(3,'请上传商品封面');
        }
        if(!$name){
            Common::echoAjaxJson(4,'请输入商品名称');
        }
        if(preg_match('/[A-Za-z]{1,}/',$name)){
            Common::echoAjaxJson(5,'标题不能包含英文字符');
        }
        $name_len = mb_strlen($name,'utf-8');
        if($name_len < 1 || $name_len > 30){
            Common::echoAjaxJson(6,'请输入1-30个中文作为商品名称');
        }
        if($intro===''){
            Common::echoAjaxJson(7, "请填写商品详情");
        }
        if(!$type||!in_array($type,array(1,2,3))){
            Common::echoAjaxJson(9, "请至少选择一种购买方式");
        }
        if(in_array($type,array(1,2))){
            if(!$price){
                Common::echoAjaxJson(10, "请输入销售价格");
            }
            if(!preg_match('/^[0-9]{1,8}+(.[0-9]{1,2})?$/',$price)){
                Common::echoAjaxJson(11,'人民币上限为一千万，小数点后最多保存两位。');
            }
        }
        if(in_array($type,array(2,3))){
            if(!$score){
                Common::echoAjaxJson(12,'请输入福报值数额');
            }
            if($score>10000000){
                Common::echoAjaxJson(13,'福报值最大值不能超过一千万');
            }
        }
        $score = ($type==1)?0:$score;
        $price = ($type==3)?0:$price;
        if($num>10000000){
            Common::echoAjaxJson(15,'库存最大值不能超过一千万');
        }
        if(!$start_time){
            Common::echoAjaxJson(16, "请选择商品上架时间");
        }
        $stageInfo = $stageModel->getBasicStageBySid($sid['sid']);

        $province_name = $province? $addressModel->getNameById($province) : '';
        $city_name = $city ? $addressModel->getNameById($city) : '';
        $data = array(
            'cover'=>$cover,
            'add_time'=>date('Y-m-d H:i:s',time()),
            'price'=>$price,
            'score'=>$score,
            'type'=>$type,
            'stock_num'=>$num,
            'intro'=>str_replace("\n","<br>",$intro),
            'name'=>$name,
            'stageInfo'=>$stageInfo,
            'province'=>$province,
            'city'=>$city,
            'goodsImages'=>explode('&',$img),
            'cate_id'=>$cate_id,
            'address_name'=>$province_name.' '.$city_name,
            'stageUser'=>$userModel->getUserData($stageInfo['uid']),
        );
        $commonModel = new CommonModel();
        $rs = $commonModel->addPreview(3,json_encode($data));
        Common::appLog('stagegoods/preview',$this->startTime,$version);
        if($version>='3.7'){
            Common::echoAjaxJson(1, "发布成功",array('url'=>I_DOMAIN.'/common/preview?id='.$rs));
        }else{
            Common::echoAjaxJson(1, "获取成功",I_DOMAIN.'/common/preview?id='.$rs);
        }

    }
    //商品分类
    public function getGoodsCateAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $stagegoodsModel = new StagegoodsModel();
        $data = $stagegoodsModel->getCate();
        Common::appLog('stagegoods/getGoodsCate',$this->startTime,$version);
        Common::echoAjaxJson(1, '获取成功',$data);
    }

    public function getGoodsBuyInfoAction(){
        $user = Common::isLogin($_POST);
        if(!$user){
            Common::echoAjaxJson(3, "非法登录用户");
        }
        $order_id = $this->getRequest()->getPost("order_id");//订单id
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$order_id){
            Common::echoAjaxJson(2, "商品id为空");
        }
        $stagegoodsModel = new StagegoodsModel();
        $order_info = $stagegoodsModel->getOrderInfoByOrderId($order_id);
        $goods_info = $stagegoodsModel->getInfo($order_info['goods_id']);
        $list['info']['id'] = $order_info['id'];
        $list['info']['goods_id'] = $order_info['goods_id'];
        $list['info']['order_id'] = $order_id;
        $list['info']['name'] = $goods_info['name'];
        $list['info']['sid'] = $goods_info['sid'];
        $list['info']['cover'] = $goods_info['cover'];
        $list['info']['num'] = $order_info['num'];
        $list['info']['price_totals'] = $order_info['price_totals'];
        $list['info']['score_totals'] = $order_info['score_totals'];
        $list['list'] = $stagegoodsModel->getRcommendGoods($order_info['goods_id'],$version,$_POST['token']);
        Common::appLog('stagegoods/getGoodsBuyInfo',$this->startTime,$version);
        Common::echoAjaxJson(1, '获取成功',$list);
    }
    //商品置顶加精
    public function topAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(11, "非法登录用户");
        }
        $uid = $user['uid'];
        $goods_id = (int) $this->getRequest()->getPost('goods_id');
        if(!$goods_id){
            $goods_id = (int) $this->getRequest()->getPost('id');
        }
        $type = (int) $this->getRequest()->getPost('type');
        $data = array(
            'uid' => $uid,
            'goods_id' => $goods_id,
            'type'=>$type,
        );
        $rst = Common::http(OPEN_DOMAIN."/stageapi/topStageGoods", $data, "POST");
        echo $rst;
    }
    //商品取消置顶加精
    public function cancelTopAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(11, "非法登录用户");
        }
        $uid = $user['uid'];
        $goods_id = (int) $this->getRequest()->getPost('goods_id');
        if(!$goods_id){
            $goods_id = (int) $this->getRequest()->getPost('id');
        }
        $type = $this->getRequest()->getPost('type');
        $data = array(
            'uid' => $uid,
            'goods_id' => $goods_id,
            'type'=>$type,
        );
        $rst = Common::http(OPEN_DOMAIN."/stageapi/cancelTopStageGoods", $data, "POST");
        echo $rst;
    }

    public function delOrderAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(7, "非法登录用户");
        }
        $uid = $user['uid'];
        $id= $this->getRequest()->getPost("id");//订单主键id
        $type = $this->getRequest()->getPost("type");//4.买家删除 5.卖家删除
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if(!$id){
            Common::echoAjaxJson(2, "订单id为空");
        }
        $stagegoodModel = new StagegoodsModel();
        $orderInfo = $stagegoodModel->orderInfoById($id,1);
        $goodsInfo = $stagegoodModel->getInfo($orderInfo['goods_id']);
        if(!$orderInfo){
            Common::echoAjaxJson(3, "该订单不存在");
        }
        if($type==4&&$uid!=$orderInfo['uid']){
            Common::echoAjaxJson(4, "这不是您的订单，无法删除");
        }
        if($type==5&&$uid!=$goodsInfo['uid']){
            Common::echoAjaxJson(5, "您不是卖家，无法删除");
        }
        if($orderInfo['status']==4&&$type==5||$orderInfo['status']==5&&$type==4){
            $type=6;
        }
        $rs = $stagegoodModel->delOrder($id,$type);
        if(!$rs){
            Common::echoAjaxJson(6, "删除失败");
        }
        Common::appLog('stagegoods/delOrder',$this->startTime,$version);
        Common::echoAjaxJson(1, "删除成功");
    }
    //筛选商品条件
    public function getGoodsConditionAction(){
        $data['token'] = $this->getRequest()->getPost('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $stagegoodsModel = new StagegoodsModel();
        $data = $stagegoodsModel->getCate();
        $condition['level_first'] = $data;
        $condition['level_second'] = Common::returnCity();
        $condition['level_third'] = array(array('sort'=>'综合排序'),array('sort'=>'价格由高到低'),array('sort'=>'价格由低到高'));
        Common::appLog('stagegoods/getGoodsCondition',$this->startTime);
        Common::echoAjaxJson(1, '获取成功',$condition,$version);
    }
    //筛选商品信息
    public function getListByConditionNewAction(){
        $data['token'] = $this->getRequest()->getPost('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') :APP_VERSION;//版本名
        $big_cate_id =  $this->getRequest()->getPost('big_cate_id');//分类大分类
        $cate_id = $this->getRequest()->getPost('cate_id');//商品小分类id
        $city = $this->getRequest()->getPost('city');//城市
        $sort = $this->getRequest()->getPost('sort');//智能排序
        $size = ($this->getRequest()->getPost("size")&&$this->getRequest()->get('size')==20) ? $this->getRequest()->get('size') : 20; //条数
        $page = $this->getRequest()->getPost("page") ? $this->getRequest()->getPost("page") : 1; //页数
        $city_id = Common::getIdByCity($city);
        $sort = $sort&&$sort!='不限' ? $sort : '';
        $stagegoodsModel  = new StagegoodsModel();
        $list = $stagegoodsModel->getListByConditionNew($big_cate_id,$cate_id,$city_id,$sort,$page,(int)$size,$data['token'],$version);
        Common::appLog('stagegoods/getListByConditionNew',$this->startTime,$version);
        Common::echoAjaxJson(1, '获取成功',$list ? $list : array());
    }
    //获取物流信息
    public function getLogisticsAction(){
        $data['token'] = $this->getRequest()->getPost('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $id = $this->getRequest()->getPost('id');//订单主键id
        if(!$id){
            Common::echoAjaxJson(3, "订单为空");
        }
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') :APP_VERSION;//版本名
        $stagegoodsModel = new StagegoodsModel();
        $orderInfo = $stagegoodsModel->getLogisticsInfo($id);
        $orderInfo['logistics_type']  = $orderInfo['logistics_type'] ? $orderInfo['logistics_type'] : "auto";
        if($orderInfo['logistics_number']){
            $info = Api::getLogistics($orderInfo['logistics_number'],$orderInfo['logistics_type'] );
        }
        $info['result']['number'] = $orderInfo['logistics_number'];
        $info['result']['name'] = $orderInfo['logistics_company'];
        $info['result']['cover'] = $orderInfo['cover'];
        $info['result']['tel'] = $orderInfo['logistics_tel'];
        Common::appLog('stagegoods/getLogistics',$this->startTime,$version);
        Common::echoAjaxJson(1, '获取成功',$info['result']);
    }
    //商品设置佣金
    public function setCommissionAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $max_number = '0.5';
        $uid = $user['uid'];
        $id_json = $this->getRequest()->getPost('id_json');
        $number = $this->getRequest()->getPost('number');
        $type = $this->getRequest()->getPost('type');//1 设置 2取消设置
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') :APP_VERSION;//版本名
        $is_commission=1;
        if(!$id_json){
            Common::echoAjaxJson(2, "设置的对象为空");
        }
        if($type==1&&!$number){
            Common::echoAjaxJson(3, "设置的佣金率为空");
        }
        if($type==1&&$number>$max_number){
            Common::echoAjaxJson(4, "设置的佣金率最大为".($max_number*100)."%");
        }
        if($type==2){
            $number ='';
            $is_commission=0;
        }
        $stagegoodsModel = new StagegoodsModel();
        $stagegoodsModel->setCommission($is_commission,$number,$uid,$id_json);
        Common::appLog('stagegoods/setCommission',$this->startTime,$version);
        Common::echoAjaxJson(1, '设置成功');
    }
    //商品 分享有奖筛选条件
    public function getCommissionConditionAction(){
        $data['token'] = $this->getRequest()->getPost('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $stagegoodsModel = new StagegoodsModel();
        $data = $stagegoodsModel->getBigCate();
        $condition['level_first'] = $data;
        $condition['level_second'] = array(array('sort_id'=>'1','sort'=>'综合排序'),array('sort_id'=>'2','sort'=>'奖金由高到低'),array('sort_id'=>'3','sort'=>'30天引入订单由高到低'),array('sort_id'=>'4','sort'=>'30天支付累计支出佣金由高到低'));
        Common::appLog('stagegoods/getCommissionCondition',$this->startTime);
        Common::echoAjaxJson(1, '获取成功',$condition,$version);
    }
    //筛选商品分享有奖信息
    public function getCommissionByConditionAction(){
        $data['token'] = $this->getRequest()->getPost('token');
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $version = $this->getRequest()->getPost('version') ? $this->getRequest()->getPost('version') :APP_VERSION;//版本名
        $big_cate_id =  intval($this->getRequest()->getPost('big_cate_id'));//分类大分类
        $sort_id = intval($this->getRequest()->getPost('sort_id'));//排序  1.综合排序（按佣金发布时间排序）2.商品奖金由高到低排序 3.30天引入订单由高到低 4.30天支付累计支出佣金由高到低
        $size = 10;//条数
        $page = $this->getRequest()->getPost("page") ? $this->getRequest()->getPost("page") : 1; //页数
        $sort_id = $sort_id ? $sort_id :1;
        $stagegoodsModel  = new StagegoodsModel();
        $list = $stagegoodsModel->getCommissionByCondition($big_cate_id,$sort_id,$page,$size,$data['token'],$version);
        Common::appLog('stagegoods/getCommissionByCondition',$this->startTime,$version);
        Common::echoAjaxJson(1, '获取成功',$list);
    }
}