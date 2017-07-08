<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 16-12-28
 * Time: 下午1:46
 */
class MallController extends Yaf_Controller_Abstract {
    public function init(){
        $this->startTime = microtime(true);
    }
    //商城首页
    public function indexAction(){
        $token = $this->getRequest()->getPost('token');//用户登录token
        $page = 1;
        $size = 10;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(2, "非法登录用户");
            }
        }
        $indexModel = new IndexModel();
        $stagegoodsModel = new StagegoodsModel();
        $list['banner'] = $indexModel->indexBanner('app_mall',$token) ? $indexModel->indexBanner('app_mall',$token) :array();//banner图
        $list['goods_cate'] = $stagegoodsModel->getBigCate();
        $list['column'] = array(
            '0'=>array('url'=>''.I_DOMAIN.'/mall/publicPraise?token='.$token),
            '1'=>array('url'=>''.I_DOMAIN.'/mall/sellGoods?token='.$token),
        );
        $list['goods'] = $stagegoodsModel->goodsRecommendMore($page,$size,0,$version,$token,4);//优品速递
        Common::appLog('mall/index',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }
    //首页加载更多
    public function indexMoreAction(){
        $token = $this->getRequest()->getPost('token');//用户登录token
        $page = intval($this->getRequest()->getPost('page'));
        $size = 10;
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        if($token){
            $user=Common::isLogin($_POST);
            if(!$user){
                Common::echoAjaxJson(3, "数据全部加载完毕");
            }
        }
        if($page>10){
            Common::echoAjaxJson(2, "非法登录用户");
        }
        if($page<2){
            $page=2;
        }
        $stagegoodsModel = new StagegoodsModel();
        $list['goods'] = $stagegoodsModel->goodsRecommendMore($page,$size,0,$version,$token,4);//优品速递
        Common::appLog('mall/indexMore',$this->startTime,$version);
        Common::echoAjaxJson(1,'获取成功',$list);
    }

    //商城首页-口碑清单专题
    public function publicPraiseAction(){
        $token = $this->getRequest()->get('token');//用户登录token
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $size = 50;
        $this->getView()->app_css = 'mall';
        $this->getView()->page_title = '口碑清单';
        $stagegoodsModel = new StagegoodsModel();
        $list = $stagegoodsModel->goodsRecommendMore(1,$size,0,$version,$token,2);
        $this->getView()->goods_list = $list;
        $this->display("publicPraise");
    }

    //商城首页-热销好物专题
    public function sellGoodsAction(){
        $token = $this->getRequest()->get('token');//用户登录token
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $size = 50;
        $this->getView()->app_css = 'mall';
        $this->getView()->page_title = '热销好物';
        $stagegoodsModel = new StagegoodsModel();
        $list = $stagegoodsModel->goodsRecommendMore(1,$size,0,$version,$token,3);
        $this->getView()->goods_list = $list;
        $this->display("sellGoods");
    }

    //商城首页-banner专题
    public function mallSpecialAction(){
        $token = $this->getRequest()->get('token');//用户登录token
        $version = $this->getRequest()->getPost("version") ? $this->getRequest()->getPost("version") : APP_VERSION;
        $size = 4;
        $this->getView()->app_css = 'mall';
        $this->getView()->page_title = '商城专题';
        $stagegoodsModel = new StagegoodsModel();
        $list = $stagegoodsModel->goodsRecommendMore(1,$size,0,$version,$token,5);
        $this->getView()->goods_list = $list;
        $this->display("mallSpecial");
    }
}