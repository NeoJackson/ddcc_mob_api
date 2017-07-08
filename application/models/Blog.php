<?php
/**
 * Class BlogModel
 * @author Wayne Chen
 */
class BlogModel {
    private $db;
    private $redis;
    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }
    //find usage
    /*
     * @name 获取用户的日志总数
     */
    public function getNum($uid,$is_public,$table='blog'){
        if($is_public == 1){
            $stmt = $this->db->prepare("select count(id) as num from $table where uid=:uid and is_public=1 and status < 2");
        }else{
            $stmt = $this->db->prepare("select count(id) as num from $table where uid=:uid and status < 3");
        }
        $array = array(
            ':uid'=>$uid,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }

    public function getBasicBlogById($id,$is_public=2,$uid=0, $status=2){
        $where_is_public = '';
        if($is_public == 0){
            $where_is_public = ' and is_public = 0';
        }elseif($is_public == 1){
            $where_is_public = ' and is_public = 1';
        }
        $stmt = $this->db->prepare("select id,uid,title,summary,view_num,comment_num,like_num,share_num,collect_num,reward_num,is_public,is_recommend,
        is_good,is_img,type,status,add_time from blog where status<:status and id=:id".$where_is_public);
        $array = array(
            ':id'=>$id,
            ':status'=>$status,
        );
        $stmt->execute($array);
        $blog = $stmt->fetch(PDO::FETCH_ASSOC);
        if($blog){
            $visitModel = new VisitModel();
            $blog['view_num'] = (int)$visitModel->getVisitNum('blog',$blog['id']);
            $blog['summary'] = Common::deleteHtml($blog['summary']);//过滤
            $blog['summary'] = Common::linkReplace($blog['summary']);//过滤外链
            if($uid){
                $likeModel = new LikeModel();
                $is_like = $likeModel->hasData(3,$blog['id'],$uid);
                $collectModel = new CollectModel();
                $is_collect = $collectModel->hasData(3,$blog['id'],$uid);
                $blog['is_like'] = $is_like ? $is_like : 0;
                $blog['is_collect'] = $is_collect ? $is_collect : 0;
            }
        }
        return $blog;
    }

    public function getBlogById($id,$is_public=2){
        $where_is_public = '';
        if($is_public == 0){
            $where_is_public = ' and is_public = 0';
        }elseif($is_public == 1){
            $where_is_public = ' and is_public = 1';
        }
        $stmt = $this->db->prepare("select * from blog where id=:id and status < 2".$where_is_public);
        $array = array(
            ':id'=>$id,
        );
        $stmt->execute($array);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /*
     * @name 根据日志的id取日志的信息
     */
    public function get($id,$status=2,$uid=0){
        $stmt = $this->db->prepare("select id, uid, type, title, summary, content, is_public, view_num, comment_num, like_num, share_num,
        collect_num, reward_num, has_draft, is_recommend, is_good, is_img, last_comment_time, seo_title, seo_keyword, seo_description,
        status, add_time, update_time from blog where id=:id and status < :status");
        $array = array(
            ':id'=>$id,
            ':status'=>$status,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result){
            $img_arr = Common::pregMatchImg($result['content']);
            if($img_arr){
                $result['img_src'] = $img_arr[3];
            }else{
                $result['img_src'] = array();
            }
            $userModel = new UserModel();
            $userInfo = $userModel->getUserData($result['uid'],$uid);
            $result['add_time'] = Common::show_time($result['add_time']);
            $result['did'] = $userInfo['did'];
            $result['nick_name'] = $userInfo['nick_name'];
            $result['avatar'] = $userInfo['avatar'];
            $result['ico_type'] = $userInfo['ico_type'];
            //获取插入的视频
            $char_length= is_int(strpos($result['content'],'<span class="editor-video-data"'));
            if($char_length){
                $img_arr =  $this->pregMatchVideo($result['content']);
                $rs_video = $this->modThVideo($img_arr['spa']);//读取的视频数组
                $result['content'] = str_replace($img_arr['emb'],$rs_video,$result['content']);
            }
        }
        return $result;
    }

    //正则匹配抓取内容里视频
    public function pregMatchVideo($content){
        $video_array=array();
        preg_match_all('/<span class="editor-video-data"[^>]*?>.*?<\/span>/i',$content,$img_arr);
        $video_array['spa']= $img_arr[0];
        preg_match_all('/<embed[^>]*>(<\/embed>)?/',$content,$embedList);
        $video_array['emb']= $embedList[0];
        return $video_array;
    }
    //视频替换
    public function modThVideo($video_arr){
        foreach($video_arr as $ks=> $a){
            preg_match_all('/data-img-large="(.*?)"/',$a,$matched);
            preg_match_all('/data-img-small="(.*?)"/',$a,$matched_small);
            preg_match_all('/data-v_url="(.*?)"/',$a,$data_v_url);
            if($matched[1][0]=='undefined'||$matched[1][0]==''){
                $img_url= $matched_small[1][0];
            }else{
                $img_url=$matched[1][0];
            }
            $href_url=$data_v_url[1][0];
            $count_video[$ks]='<span class="editor-video-data"  onclick="location.href='."'".$href_url."'".'"><i class="video-i"></i><img src="'.$img_url.'"></span>';

        }
        return $count_video;
    }
    /*
     * @name 删除日志
     */
    public function del($uid,$id){
        $stmt = $this->db->prepare("update blog set status=4 where uid=:uid and id=:id");
        $array = array(
            ':uid'=>$uid,
            ':id'=>$id,
        );
        $stmt->execute($array);
        $count = $stmt->rowCount();
        if($count < 1){
            return 0;
        }
        $feedModel = new FeedModel();
        $commonModel = new CommonModel();
        $commonModel->updateRelationByObjId(3,$id,4);//删除相对应的评论、喜欢、打赏等相关信息
        $feedModel->del($uid,'topic',$id);//删除动态信息
        return $count;
    }

}