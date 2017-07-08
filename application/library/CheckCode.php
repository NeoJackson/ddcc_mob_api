<?php
/**
 * Created by PhpStorm.
 * User: ddcc
 * Date: 14-5-27
 * Time: 下午7:22
 */

class CheckCode {
    function getCheckcodeNum() {
        $str = "23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVW";
        $code = '';
        for($i = 0; $i < 4; $i ++) {
            $code .= $str{mt_rand(0, strlen($str) - 1)};
        }
        return $code;
    }

    function echoCheckcodePic($code = '', $num = 0, $size = 20, $width = 0, $height = 0) {
        ! $width && $width = $num * $size * 4 / 5 + 5;
        ! $height && $height = $size + 10;
        $im = imagecreatetruecolor ( $width, $height );
        $back_color = imagecolorallocate ( $im, 235, 236, 237 );
        $boer_color = imagecolorallocate ( $im, 118, 151, 199 );

        imagefilledrectangle ( $im, 0, 0, $width, $height, $back_color );
        imagerectangle ( $im, 0, 0, $width - 1, $height - 1, $boer_color );

        $font = dirname ( __FILE__ ) . "/font/VeraSansBold.ttf";
        $strx = rand ( 3, 7 );
        for($i = 0; $i < 4; $i ++) {
            $strpos = rand ( 18, 25 );
            imagefttext ( $im, $size, rand ( - 5, 5 ), $strx, $strpos, imagecolorallocate ( $im, mt_rand ( 0, 200 ), mt_rand ( 0, 120 ), mt_rand ( 0, 120 ) ), $font, substr ( $code, $i, 1 ) );
            $strx += rand ( 13, 16 );
        }
        header("Cache-Control: max-age=1, s-maxage=1, no-cache, must-revalidate");
        header("Content-type: image/jpeg");
        imagepng($im);
        imagedestroy($im);
    }
}