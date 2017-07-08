<?php
class BlogController extends Yaf_Controller_Abstract {
    public function init(){
        $this->startTime = microtime(true);
    }
    public function indexAction(){
        $id =  $this->getRequest()->get('id');
        $data['token'] = $this->getRequest()->get('token');
        $version = $this->getRequest()->get('version') ? $this->getRequest()->get('version') : APP_VERSION;
        $uid = 0;
        if($data['token']){
            $user=Common::isLogin($data);
            if(!$user){
                Common::echoAjaxJson(3, "非法登录用户");
            }
            $uid=$user['uid'];
            $this->getView()->user = $user;
        }
        if(!$id){
            Common::redirect(I_DOMAIN.'/index/error?type=2');
        }
        $visitModel = new VisitModel();
        $blogModel = new BlogModel();
        $feedModel = new FeedModel();
        $collectModel = new CollectModel();
        $blog = $blogModel->get($id,2,$uid);//日志详情信息
        if(!$blog){
            Common::redirect(I_DOMAIN.'/index/error?type=2');
        }
        $blog['content'] = Common::linkReplace(Common::replaceStyle($blog['content']));
        $blog['view_num'] = $visitModel->addVisitNum('blog',$id);//添加浏览数
        $img_arr = Common::pregMatchImg($blog['content']);
        if($img_arr[3]){
            $stageImg = Common::show_img($img_arr[3][0],4,100,100);
        }else{
            $stageImg = PUBLIC_DOMAIN.'defalut_share.png';
        }
        $messageModel = new MessageModel();
        $rewardList = $messageModel->getRewardList(3,$id,1,10);
        $likeModel = new LikeModel();
        $is_like = $likeModel->hasData(3,$id,$uid);
        if(isset($is_like) && $is_like){
            $likeList = $likeModel->likeList($id,3,1,4,$uid);
            $blog['is_like'] = 1;
            $likeList['is_like'] = 1;
        }else{
            $likeList = $likeModel->likeList($id,3,1,5,$uid);
            $blog['is_like'] = 0;
            $likeList['is_like'] = 0;
        }
        $eventModel = new EventModel();
        $this->getView()->token = $data['token'];
        $this->getView()->stageImg = $stageImg;
        $this->getView()->rewardList = $rewardList;
        $this->getView()->like_list = $likeList;
        $this->getView()->commentList = $feedModel->getCommentList($uid,3,$id,1,50);
        $this->getView()->is_collect = $collectModel->hasData(3,$id,$uid);
        $this->getView()->blog = $blog;
        $this->getView()->obj_id = $id;
        $this->getView()->type = 3;
        $this->getView()->uid = $blog['uid'];
        $this->getView()->is_like = $is_like;
        $this->getView()->page_title =$blog['title'];
        $this->getView()->description = $blog['title'];
        $this->getView()->newList = $eventModel->getListByAddtime(4,0);
        $this->getView()->version = $version;
        $this->display("detail");
    }
    //删除日志
    public function delAction(){
        $user = Common::isLogin($_POST,1);
        if(!$user){
            Common::echoAjaxJson(4, "非法登录用户");
        }
        $uid = $user['uid'];
        $id = (int)$this->getRequest()->getPost('id');
        if($id <= 0){
            Common::echoAjaxJson(2,'删除的日志不存在');
        }
        $blogModel = new BlogModel();
        $rs = $blogModel->del($uid,$id);
        if($rs == 0){
            Common::echoAjaxJson(3,'删除失败');
        }
        Common::echoAjaxJson(1,'删除成功');
    }
}