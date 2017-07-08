<?php

class Image
{
    public function upload($tmp_filename, $filename)
    {
        $path_parts = pathinfo($filename);
        $suffix = strtolower($path_parts['extension']);
        if (!in_array($suffix, array('jpg', 'jpeg', 'png', 'gif'))) {
            return -1;
        }
        if (!file_exists($tmp_filename)) {
            return -2;
        }
        if (filesize($tmp_filename) > 5 * 1024 * 1024) {
            return -3;
        }
        $date = date("Ymd");
        $upload_path = dirname(dirname(APPLICATION_PATH)) . "/upload/" . $date;
        $img_path = "/upload/" . $date;

        if (!is_dir($upload_path)) {
            mkdir($upload_path);
            chmod($upload_path,0777);
        }

        $new_img = time() . mt_rand(1000, 9999) . "." . $suffix;
        $new_file_path = $upload_path . "/" . $new_img;
        $new_img_path = $img_path . "/" . $new_img;

        if (!$this->resize($tmp_filename,$new_file_path)) {
            return -4;
        }

        list($width, $height) = getimagesize($new_file_path);
        return array('width' => $width, 'height' => $height, 'path' => $new_img_path);
    }

    public function resize($filename, $new_filename)
    {
        list($src_width, $src_height) = getimagesize($filename);
        if($src_width > 1200){
            $dst_width = 1200;//压缩后图片的宽度
            $dst_height = intval($dst_width * $src_height / $src_width);//等比缩放图片高度
        }else{
            move_uploaded_file($filename,$new_filename);
            return $new_filename;
        }
        $path_parts = pathinfo($new_filename);
        $suffix = strtolower($path_parts['extension']);
        $temp = imagecreatetruecolor($dst_width, $dst_height);
        switch ($suffix) {
            case 'png':
                $source = @imagecreatefrompng($filename);
                if (!$source) {
                    return false;
                }
                ImageCopyResampled($temp, $source, 0, 0, 0, 0, $dst_width, $dst_height, $src_width, $src_height);
                $resize_status = imagepng($temp, $new_filename);
                break;
            case 'gif':
                $source = @imagecreatefromgif($filename);
                if (!$source) {
                    return false;
                }
                ImageCopyResampled($temp, $source, 0, 0, 0, 0, $dst_width, $dst_height, $src_width, $src_height);
                $resize_status = imagegif($temp, $new_filename);
                break;
            default:
                $source = @imagecreatefromjpeg($filename);
                if (!$source) {
                    return false;
                }
                ImageCopyResampled($temp, $source, 0, 0, 0, 0, $dst_width, $dst_height, $src_width, $src_height);
                $resize_status = imagejpeg($temp, $new_filename);
                break;
        }
        imagedestroy($temp);
        imagedestroy($source);
        if (!$resize_status) {
            return false;
        }
        return $new_filename;
    }

    /**
     * @param 裁剪图片
     * @param $x
     * @param $y
     * @param $dist_width
     * @param $dist_height
     * @param $width
     * @param $height
     * @param $resize_width
     * @param $resize_height
     * @return int|string
     */
    public function cut($file_name, $x, $y, $dst_width, $dst_height, $width, $height, $resize_width, $resize_height)
    {
        $filename = dirname(dirname(APPLICATION_PATH)) . $file_name;
        if (!file_exists($filename)) {
            $filename = $this->getImgFile($file_name);
            if(!$filename || !file_exists($filename))
                return -1;
        }
        list($src_width, $src_height) = getimagesize($filename);

        $x = $x * $src_width / $resize_width;
        $y = $y * $src_height / $resize_height;
        $src_width = $width * $src_width / $resize_width;
        $src_height = $height * $src_height / $resize_height;

        $path_parts = pathinfo($filename);
        $suffix = strtolower($path_parts['extension']);
        $new_filename = $path_parts ['dirname'] . "/" . $path_parts['filename'] . "_avatar." . $suffix;

        $thumb = imagecreatetruecolor($dst_width, $dst_height);
        switch ($suffix) {
            case 'png':
                $source = @imagecreatefrompng($filename);
                if (!$source) {
                    return -2;
                }
                ImageCopyResampled($thumb, $source, 0, 0, $x, $y, $dst_width, $dst_height, $src_width, $src_height);
                $cut_status = imagepng($thumb, $new_filename);
                break;
            case 'gif':
                $source = @imagecreatefromgif($filename);
                if (!$source) {
                    return -2;
                }
                ImageCopyResampled($thumb, $source, 0, 0, $x, $y, $dst_width, $dst_height, $src_width, $src_height);
                $cut_status = imagegif($thumb, $new_filename);
                break;
            default:
                $source = @imagecreatefromjpeg($filename);
                if (!$source) {
                    return -2;
                }
                ImageCopyResampled($thumb, $source, 0, 0, $x, $y, $dst_width, $dst_height, $src_width, $src_height);
                $cut_status = imagejpeg($thumb, $new_filename);
                break;
        }
        imagedestroy($thumb);
        imagedestroy($source);
        if (!$cut_status) {
            return -2;
        }
        return $new_filename;
    }

    /**
     * 上传图片到图片服务
     * @param $filename
     * @return mixed
     */
    public function uploadToServer($filename)
    {
        require_once('qiniu/io.php');
        require_once('qiniu/rs.php');
        require_once('qiniu/rsf.php');
        require_once('qiniu/resumable_io.php');
        $path_parts = pathinfo($filename);
        $key = $path_parts['filename'] . '.' . $path_parts['extension'];
        $bucket = 'daidai';
        $file = $filename;
        $accessKey = 'wy755ZY-8nr3OuMuSxaYjLCrJ6Ni5xrPoQGOqLK7';
        $secretKey = 'ByS3r0NoHSroQRMpHff_P7o3Z7OEfraIh_clct25';
        Qiniu_setKeys($accessKey, $secretKey);
        $putPolicy = new Qiniu_RS_PutPolicy($bucket);
        $upToken = $putPolicy->Token(null);
        $putExtra = new Qiniu_Rio_PutExtra($bucket);
        list($ret, $err) = Qiniu_Rio_PutFile($upToken, $key, $file, $putExtra);
        if (!$ret) {
            return false;
        }
        return $ret['key'];
    }

    //获取qiniu图片到本地
    public function getImgFile($img_url){
        $curl = curl_init($img_url);
        $dir = dirname(dirname(APPLICATION_PATH)) . "/upload/" . date("Ymd");
        if (!is_dir($dir)) {
            mkdir($dir);
            chmod($dir,0777);
        }

        if(strpos($img_url,"http://img.qiniudn.com/") === false )
            $filename = $dir . "/" . time() . mt_rand(1000, 9999) . ".jpg";
        else
            $filename = $dir . "/" . str_replace('http://img.qiniudn.com/','',$img_url);

        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_BINARYTRANSFER, 1);
        $imageData = curl_exec($curl);
        if(!imagecreatefromstring($imageData)){
            return false;
        }
        curl_close($curl);
        if (!$imageData) {
            return false;
        }
        $tp = @fopen($filename, 'w');
        fwrite($tp, $imageData);
        fclose($tp);
        return  $filename;
    }

    //抓取图片并上传到服务器
    public function fetch($img_url, $domain = IMG_DOMAIN)
    {
        $curl = curl_init($img_url);
        $dir = dirname(dirname(APPLICATION_PATH)) . "/upload/" . date("Ymd");
        if (!is_dir($dir)) {
            mkdir($dir);
            chmod($dir,0777);
        }
        $filename = $dir . "/" . time() . mt_rand(1000, 9999) . ".jpg";
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_BINARYTRANSFER, 1);
        $imageData = curl_exec($curl);
        if (!$imageData) {
            return false;
        }
        if(!imagecreatefromstring($imageData)){
            return false;
        }
        curl_close($curl);
        $tp = @fopen($filename, 'w');
        fwrite($tp, $imageData);
        fclose($tp);
        $img_name = $this->uploadToServer($filename);
        if (!$img_name) {
            return false;
        }
        return $domain . $img_name;
    }

}

?>