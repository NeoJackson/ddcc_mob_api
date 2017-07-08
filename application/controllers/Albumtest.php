<?php
class AlbumtestController extends Yaf_Controller_Abstract
{
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 15-6-2
 * Time: 下午3:45
 */
    //新建相册
    public function addAction(){
        $parameters = array(
            'token' =>'4ae9ccd076ccb00e20fe97827c6a96a4',
            'name' => '新建92',
            'intro' => '来看看到',
            'is_public'=>0
        );
        Common::verify($parameters, '/album/add');
    }
    //删除相册
    public function delAlbumAction(){
        $parameters = array(
            'token' =>'7420cafb38c40d50e3791e9df793fa02',
            'id' => 131998
        );
        Common::verify($parameters, '/album/delAlbum');
    }
    //照片列表
    public function photoListAction(){
        $parameters = array(
            'token' =>'f3969c6fc40a776d5c8ba460de46ef88',
            'id' => 131497,
            'last_time'=>'2015-07-24 17:24:48',
           // 'size'=>5
        );
        Common::verify($parameters, '/album/photoList');
    }
    //照片列表
    public function albumListAction(){
        $parameters = array(
            'token' =>'246c22c5ee565a06bf23b56a5bbb11ff',
            'uid' => '20254',
        );
        Common::verify($parameters, '/album/albumList');
    }
    //照片详情
    public function photoAction(){
        $parameters = array(
            'token' =>'d0becdc7e9401a06e2ccb4be112e9672',
            'id' => 63034,
        );
        Common::verify($parameters, '/album/photo');
    }
    //修改相册
    public function modifyAction(){
        $parameters = array(
            'token' =>'7420cafb38c40d50e3791e9df793fa02',
            'id' => 105767,
            'name'=>'老孙测试修改',
            'intro'=>'一二三四五六七八九十一二三四五六七八九十一二三四五六七八九十一二三四五六七八九十一二三四五六七八九十一二三四五六七八九十一二三四五六七八九十'
        );
        Common::verify($parameters, '/album/modify');
    }
    //修改相册
    public function delPhotoAction(){
        $parameters = array(
            'token' =>'dc99b1dd7d38af158e9a5b7c06961d47',
            'ids' => '67459'
        );
        Common::verify($parameters, '/album/delPhoto');
    }
    //设置封面
    public function setCoverAction(){
        $parameters = array(
            'token' =>'7420cafb38c40d50e3791e9df793fa02',
            'id' => '50279'
        );
        Common::verify($parameters, '/album/setCover');
    }
    //修改照片描述
    public function modifyPhotoIntroAction(){
        $parameters = array(
            'token' =>'7420cafb38c40d50e3791e9df793fa02',
            'ids' => '50279&50280',
            'intro'=>'周周'
        );
        Common::verify($parameters, '/album/modifyPhotoIntro');
    }
    public function addPhotoAction(){
        $parameters = array(
            'token' =>'7420cafb38c40d50e3791e9df793fa02',
            'album_id'=>132000,
            'img_array'=>json_encode(array(
                    '0'=> '14333135104865.png')
           ),
           'intro_array'=>json_encode(array(
                '0'=> '14333135104865.png')
           )
        );
        Common::verify($parameters, '/album/addPhoto');
    }
}