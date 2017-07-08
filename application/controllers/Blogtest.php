<?php
class BlogtestController extends Yaf_Controller_Abstract
{
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 15-6-2
 * Time: 下午3:45
 */
public function viewBlogAction(){
    $parameters = array(
        'token' =>'4b01e047ccd215c1848080ec02474d92',
        'id' =>'14360'
    );
    Common::verify($parameters, '/blog/viewBlog');
 }
}