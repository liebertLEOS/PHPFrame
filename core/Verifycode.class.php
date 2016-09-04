<?php
namespace Frame;

class Verifycode{
    protected    $height      = '50';
    protected    $width       = '120';
    protected    $background  = '#e6f3ff';
    protected    $length      = 4;
    protected    $font;
    protected    $fontColor;
    protected    $fontSize    = 18;
    protected    $code;
    protected    $img;//图像内存区句柄 
    protected    $charset     = 'abcdefghkmnprstuvwyzABCDEFGHKLMNPRSTUVWYZ23456789';
    
    function __construct(){
        $this->font = FRAME_PATH.'Font\\elephant.ttf';
    }
    public function getCode(){
        return strtolower($this->code);
    }
    /**
     * 获得图像
     * @param int    $width       图像宽度
     * @param int    $height      图像高度
     * @param int    $length      显示的字符数量
     * @param int    $fontSize    字体大小
     * @param string $fontColor   字体颜色
     * @param string $background  背景色
     * @param string $charset     字符集
     */
    public function getImage($width='',$height='',$length='',$fontSize='',$fontColor='',$background='',$charset=''){
        if(is_numeric($width)){
            $this->width = $width;
        }
        if(is_numeric($height)){
            $this->height = $height;
        }
        if(is_numeric($length)){
            $this->length = $length;
        }
        if(is_numeric($fontSize)){
            $this->fontSize = $fontSize;
        }
        if(!empty($fontColor) && preg_match('/(^#[a-z0-9]{6}$)/im', $fontColor)){
            $this->fontColor = trim($fontColor);
        }
        if(!empty($background) && preg_match('/(^#[a-z0-9]{6}$)/im', $background)){
            $this->background = trim($background);
        }
        if(!empty($charset)){
            $this->charset = trim($charset);
        }
        
        $code = $this->createCode();
        $this->img = imagecreatetruecolor($this->width, $this->height);
        //设置文本颜色
        if(!$this->fontColor){
            $this->fontColor = imagecolorallocate($this->img, rand(0, 156), rand(0, 156), rand(0, 156));
        }else{
            $this->fontColor = imagecolorallocate($this->img, hexdec(substr($this->fontColor, 1,2)), hexdec(substr($this->fontColor, 3,2)), hexdec(substr($this->fontColor, 5,2)));
        }
        //设置背景色
        $background = imagecolorallocate($this->img, hexdec(substr($this->background, 1,2)), hexdec(substr($this->background, 3,2)), hexdec(substr($this->background, 5,2)));
        //Draw a filled rectangle
        imagefilledrectangle($this->img, 0, $this->height, $this->width, 0, $background);
        //在图像上绘制验证码文本
        $this->createText();
        //绘制干扰线
        $this->createLine();
        //将绘制好的的验证码图片输出到客户端
        $this->_output();
    }
    /**
     * 生成随机验证码
     */
    protected function createCode(){
        $code      = '';
        $randomLen = strlen($this->charset) - 1;
        for($i=0;$i<$this->length;$i++){
            $code     .= $this->charset[rand(1, $randomLen)];
        }
        $this->code = $code; 
    }
    /**
     * 在图像区生成验证码文本内容
     */
    protected function createText(){
        $x = $this->width / $this->length;//每个字符的宽度
        for($i=0;$i<$this->length;$i++){
            imagettftext($this->img, $this->fontSize, rand(-60, 60), $x*$i+rand(5, 10), $this->height/1.4, $this->fontColor, $this->font, $this->code[$i]);
        }
    }
    protected function createLine(){
        imagesetthickness($this->img,3);
        $x      = $this->fontSize * 2 + rand(-5,5);
        $width  = $this->width/2.66 + rand(3,10);
        $height = $this->fontSize * 2.14;
        if(rand(0,100)%2 == 0){
            $start = rand(0,66);
            $ypos  = $this->height/2 - rand(10,30);
        }else{
            $start = rand(180,246);
            $ypos  = $this->height/2 + rand(10,30);
        }
        $end = $start + rand(75,110);
        imagearc($this->img, $this->width * .25, $ypos, $width, $height, $start, $end, $this->fontColor);
        
        if (rand(1, 75) % 2 == 0) {
            $start = rand(45, 111);
            $ypos = $this->height / 2 - rand(10, 30);
        } else {
            $start = rand(200, 250);
            $ypos = $this->height / 2 + rand(10, 30);
        }
        
        $end = $start + rand(75, 100);
        
        imagearc($this->img, $this->width * .75, $ypos, $width, $height, $start, $end, $this->fontColor);
    }
    private function _output(){
        ob_clean();//清空输出缓存区
        header("content-type:image/png\r\n");
        imagepng($this->img);
        imagedestroy($this->img);
    }
}