<?php
class Audio
{
    /**
     * 上传音频
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
        $bucket = 'ddcc-audio';
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

    public function upload($tmp_filename,$filename){
        $path_parts  =  pathinfo ($filename);
        $suffix = strtolower($path_parts['extension']);
        if(!file_exists($tmp_filename)){
            return -1;
        }
        if(filesize($tmp_filename) > 100*1024*1024){
            return -2;
        }
        $date = date("Ymd");
        $upload_path = dirname(dirname(UPLOADPATH))."/upload/".$date;
        $audio_path = "/upload/".$date;

        if(!is_dir($upload_path)){
            mkdir($upload_path,0777);
        }

        $new_audio = time().mt_rand(1000,9999).".".$suffix;
        $new_file_path = $upload_path."/".$new_audio;
        $new_audio_path = $audio_path."/".$new_audio;

        $upload_status = move_uploaded_file ( $tmp_filename ,  $new_file_path);
        if(!$upload_status){
            return -3;
        }
        return $new_audio_path;
    }
}