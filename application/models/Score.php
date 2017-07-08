<?php

class ScoreModel{
    private $db;
    private $redis;
    public $score_arr = array(
        'system'=>0,//系统
        'checkin'=>1,//签到
        'bounty'=>2,//打赏
        'login'=>3,//登录
        'mood'=>4,//心境
        'blog'=>5,//日志(公开)
        'topic'=>6,//帖子
        'photo'=>7,//上传照片到自定义相册
        'comment'=>8,//评论
        'like'=>9,//喜欢
        'daily'=>10,//每日任务
        'freshman'=>11,//新手任务
        'achieve'=>12,//领取成就
        'event'=>13,//活动
        'info'=>14,//信息
        'pray'=>15,//拜佛
        'reward'=>16,//悬赏
        'memorial'=>17,//缅怀
        'exchange'=>18,//福报值兑换
        'stagegoods'=>24,//购买商品
        'stagegoodssb'=>25,//商品购买失败退还
    );
    public $exp_arr = array(
        'login'=>10,
        'mood'=>1,
        'blog'=>5,
        'topic'=>5,
        'photo'=>3,
        'comment'=>1,
        'like'=>1,
        'info'=>10
    );
    public function __construct() {
        $this->db = DB::getInstance();
        $this->redis = CRedis::getInstance();
    }

    public function getTypeName($score_type,$obj_id=0){
        switch($score_type){
            case 0:
                $name = '系统';
                break;
            case 1:
                $name = '签到';
                break;
            case 2:
                $name = '打赏';
                break;
            case 3:
                $name = '登录';
                break;
            case 4:
                $name = '发心境';
                break;
            case 5:
                $name = '发表日志(公开)';
                break;
            case 6:
                $name = '发表帖子';
                break;
            case 7:
                $name = '上传照片(自定义相册)';
                break;
            case 8:
                $name = '评论';
                break;
            case 9:
                $name = '被喜欢';
                break;
            case 10:
                $name = '每日任务';
                break;
            case 11:
                $name = '新手任务';
                break;
            case 12:
                $name = '领取成就';
                break;
            case 13:
                $name = '活动';
                break;
            case 14:
                $name = '信息';
                break;
            case 15:
                $name = '拜佛';
                break;
            case 16:
                $name = '悬赏布告';
                break;
            case 17:
                $name = '缅怀';
                break;
            case 18:
                $name = '福报值兑换';
                break;
            case 19:
                $name = '驿站签到';
                break;
            case 24:
                $name = '购买商品';
                break;
            case 25:
                $name = '商品购买失败退还';
                break;
            default:
                $name = '意外之喜';
                break;
        }
        return $name;
    }

    /**
     * 福报值和经验值发放
     * get_type 0扣除，1获得
     */
    public function add($uid,$from_uid,$type,$obj_id,$value=0,$get_type=0){
        if(!isset($this->score_arr[$type])){
            return 0;
        }
        $score_type = $this->score_arr[$type];
        if(!$score_type){
            return 0;
        }
        $exp = $max_num = $max_value = 0;
        switch($score_type){
            case 0:
                $get_type = 1;
                $max_num = 100000;
                $period = 'day';
                break;
            case 1:
                $get_type = 1;
                $value = mt_rand(10,50);
                $max_num = 1;
                $period = 'day';
                break;
            case 2:
                $get_type = 1;
                $max_value = 5000;
                $period = 'day';
                break;
            case 3:
                $get_type = 1;
                $value = 10;
                $max_num = 1;
                $period = 'day';
                break;
            case 4:
                $get_type = 1;
                $value = 1;
                $max_num = 10;
                $period = 'day';
                break;
            case 5:
                $get_type = 1;
                $value = 5;
                $max_num = 5;
                $period = 'day';
                break;
            case 6:
                $get_type = 1;
                $value = 5;
                $max_num = 5;
                $period = 'day';
                break;
            case 7:
                $get_type = 1;
                $value = 3;
                $max_num = 5;
                $period = 'day';
                break;
            case 8:
                $get_type = 1;
                $value = 1;
                $max_num = 20;
                $period = 'day';
                break;
            case 9:
                $get_type = 1;
                $value = 1;
                $max_num = 50;
                $period = 'day';
                break;
            case 10:
                $get_type = 1;
                $value = 100;
                $max_num = 1;
                $period = 'day';
                break;
            case 11:
                $get_type = 1;
                $value = 100;
                $max_num = 1;
                $period = 'all';
                break;
            case 12:
                $get_type = 1;
                $max_num = 1;
                $period = 'all';
                break;
            case 13:
                $get_type = 1;
                $value = 10;
                $max_num = 1;
                $period = 'day';
                break;
            case 14:
                $get_type = 1;
                $value = 10;
                $max_num = 1;
                $period = 'day';
                break;
            case 15:
                $get_type = 0;
                $max_num = 100000;
                $period = 'day';
                break;
            case 16:
                $max_num = 100000;
                $period = 'day';
                break;
            case 17:
                $get_type = 0;
                $max_num = 100000;
                $period = 'day';
                break;
            case 18:
                $get_type = 0;
                $max_num = 1000000;
                $period = 'day';
                break;
            case 24:
                $get_type = 0;
                $max_num = 1000000;
                $period = 'day';
                break;
            case 25:
                $get_type = 1;
                $max_num = 1000000;
                $period = 'day';
                break;
        }
        $userModel = new UserModel();
        if($get_type == 0){//代表是扣除，判断福报值是否够用
            $user_info = $userModel->getUserInfoByUid($uid);
            if($user_info['score'] < $value){
                return -1;
            }
        }
        if(isset($this->exp_arr[$type])){
            $exp = $this->exp_arr[$type];
        }
        //周期内累计次数限制
        if($max_num > 0){
            $score_num = $this->getScoreNum($uid,$score_type,$period,$obj_id);
            if($score_num < $max_num){
                if($get_type == 1){
                    $this->addScore($uid,$from_uid,$score_type,$obj_id,$value);
                }else{
                    $this->reduceScore($uid,$from_uid,$score_type,$obj_id,$value);
                }
            }else{
                //登录超过次数，不发经验值
                if($score_type == 3){
                    $exp = 0;
                }
                $value = 0;
            }
            if($value > 0 || $exp > 0){
                if($get_type == 1){
                    $this->addScoreAndExp($uid,$value,$exp);
                }else{
                    $this->reduceScoreAndExp($uid,$value,$exp);
                }
            }
        }
        //周期内累计值限制
        if($max_value){
            $score_value = $this->getScoreValue($uid,$score_type);
            if($score_value < $max_value){
                if($score_type == 2){
                    $this->reduceScore($uid,$from_uid,$score_type,$obj_id,$value);//打赏人减去福报值
                    $this->addScore($from_uid,$uid,$score_type,$obj_id,$value);//被打赏人加福报值
                }
            }
            if($value > 0 ){
                $this->updateScore($uid,$value,'-');
                $this->updateScore($from_uid,$value,'+');
            }
        }
        return $value;
    }

    //获取当天打赏的记录
    public function getScoreValue($uid,$type){
        $stmt = $this->db->prepare("select sum(value) as num from score where uid = :uid and get_type = 0 and type = :type and DATE_FORMAT(add_time,'%Y-%m-%d') = :date");
        $array = array(
            ':uid' => $uid,
            ':type' => $type,
            ':date' => date("Y-m-d"),
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }

    //根据打赏值增加和减少用户福报值
    public function updateScore($uid,$score,$type){
        $stmt = $this->db->prepare("update user_info set score=score $type $score where uid = :uid");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $userModel = new UserModel();
        $userModel->clearUserData($uid);
    }

    //获取当天的发放记录
    public function getScoreNum($uid,$type,$period='day',$obj_id=0){
        if($period == 'day'){
            $stmt = $this->db->prepare("select count(*) as num from score where uid = :uid and type = :type and DATE_FORMAT(add_time,'%Y-%m-%d') = :date");
            $array = array(
                ':uid' => $uid,
                ':type' => $type,
                ':date' => date("Y-m-d"),
            );
        }else{
            if($obj_id && $type == 12){
                $stmt = $this->db->prepare("select count(*) as num from score where uid = :uid and type = :type and obj_id = :obj_id");
                $array = array(
                    ':uid' => $uid,
                    ':type' => $type,
                    ':obj_id' =>$obj_id,
                );
            }else{
                $stmt = $this->db->prepare("select count(*) as num from score where uid = :uid and type = :type");
                $array = array(
                    ':uid' => $uid,
                    ':type' => $type,
                );
            }
        }
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }

    //发放福报值
    public function addScore($uid,$from_uid,$type,$obj_id,$value){
        $stmt = $this->db->prepare("insert into score (uid,from_uid,type,obj_id,value) values (:uid,:from_uid,:type,:obj_id,:value)");
        $array = array(
            ':uid' => $uid,
            ':from_uid' => $from_uid,
            ':type' => $type,
            ':obj_id' => $obj_id,
            ':value' => $value
        );
        $stmt->execute($array);
    }

    //发放福报值
    public function reduceScore($uid,$from_uid,$type,$obj_id,$value){
        $stmt = $this->db->prepare("insert into score (uid,from_uid,type,get_type,obj_id,value) values (:uid,:from_uid,:type,:get_type,:obj_id,:value)");
        $array = array(
            ':uid' => $uid,
            ':from_uid' => $from_uid,
            ':type' => $type,
            ':get_type' => 0,
            ':obj_id' => $obj_id,
            ':value' => $value
        );
        $stmt->execute($array);
    }

    //增加用户福报值,增加经验值数量
    public function addScoreAndExp($uid,$score,$exp){
        $stmt = $this->db->prepare("update user_info set score=score+$score,exp=exp+$exp where uid = :uid");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $userModel = new UserModel();
        $userModel->clearUserData($uid);
    }

    //减少用户福报值,增加经验值数量
    public function reduceScoreAndExp($uid,$score,$exp){
        $stmt = $this->db->prepare("update user_info set score=score-$score,exp=exp+$exp where uid = :uid");
        $array = array(
            ':uid' => $uid,
        );
        $stmt->execute($array);
        $userModel = new UserModel();
        $userModel->clearUserData($uid);
    }

    //获取用户当天获得福报值记录
    public function getScoreNumByDate($uid,$date){
        $stmt = $this->db->prepare("select sum(value) as num from score where uid = :uid and DATE_FORMAT(add_time,'%Y-%m-%d') = :date and get_type = 1");
        $array = array(
            ':uid' => $uid,
            ':date' => $date,
        );
        $stmt->execute($array);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //获得用户福报值记录总数
    public function getRecordNum($uid){
        $stmt = $this->db->prepare('select count(id) as num from score where uid = :uid ');
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['num'];
    }

    //获得用户福报值记录
    public function getRecordList($uid,$page,$size){
        $start = ($page-1)*$size;
        $stmt = $this->db->prepare('select * from score where uid = :uid order by add_time desc limit :start,:size');
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            foreach($result as $key=>$val){
                if($val['type'] == 12){
                    $stmt = $this->db->prepare('select name from mission_achieve where id = :obj_id');
                    $stmt->execute(array(':obj_id'=>$val['obj_id']));
                    $ret = $stmt->fetch(PDO::FETCH_ASSOC);
                    $result[$key]['name'] = $ret['name'];
                }else{
                    $result[$key]['name'] = $this->getTypeName($val['type']);
                }
            }
        }
        return $result;
    }
    //获得用户福报值记录
    public function getRecordListByLastTime($uid,$last_time,$size){
        if($last_time){
            $stmt = $this->db->prepare('select * from score where uid = :uid and add_time < :last_time order by add_time desc limit :size');
            $stmt->bindValue(':last_time', $last_time, PDO::PARAM_INT);
        }else{
            $stmt = $this->db->prepare('select * from score where uid = :uid order by add_time desc limit :size');
        }
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($result){
            foreach($result as $key=>$val){
                if($val['type'] == 12){
                    $stmt = $this->db->prepare('select name from mission_achieve where id = :obj_id');
                    $stmt->execute(array(':obj_id'=>$val['obj_id']));
                    $ret = $stmt->fetch(PDO::FETCH_ASSOC);
                    $result[$key]['name'] = $ret['name'];
                }else{
                    $result[$key]['name'] = $this->getTypeName($val['type']);
                }
            }
        }
        return $result;
    }
}