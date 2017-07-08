<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 15-8-18
 * Time: 下午4:41
 */

class TestModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
    }

    public function updateMoodAddress($id,$mood_address){
        $array = array(
            ':id'=>$id,
            ':mood_address'=>$mood_address,
        );
        $stmt = $this->db->prepare("update mood set mood_address = :mood_address where id=:id ");
        $stmt->execute($array);
    }

    public function updateAddress(){
        $stmt = $this->db->prepare("UPDATE mood SET mood_address = REPLACE(mood_address, '·', ' · ')  WHERE mood_address !='' AND LOCATE('·',mood_address)>0");
        $stmt->execute();
    }

    //抓取图片后更新内容
    public function fetchUpdateContent($id,$img_json=''){
        $stmt = $this->db->prepare("update topic set img_json=:img_json where id = :id");
        $array = array(
            ':id' => $id,
            ':img_json' => $img_json
        );
        $stmt->execute($array);
    }
}