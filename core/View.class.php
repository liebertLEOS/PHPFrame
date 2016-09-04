<?php
/**
 *          View(Frame\View.class.php)
 *
 *    功　　能：视图控制器类，主要为用户渲染模板提供接口
 *
 *    作　　者：李康
 *    完成时间：2015/08/14
 *
 */
namespace Frame;
use Frame\Tools;

class View{
    
    /*
     * 模板变量数组，主要存储模板赋值变量
     * */
    protected $tVar = array();
    
    /*
     * 调用指定模板进行输出
     * param string $templateFile     模板文件名
     * param string $templateContent  模板内容，如果制定了模板内容，将使用此
     *                                模板进行渲染，这对模板文件存储在数据库
     *                                中的方式来说比较有用
     * */
    public function display($templateFile='', $templateContent='', $contentType='', $charset=''){
        $content = $this->fetch($templateFile, $templateContent);
        $this->render($content, $contentType, $charset);
    }
    
    /*
     * 获取页面输出的HTML内容
     * param string $templateFile     模板文件名
     * param string $templateContent  模板内容，如果制定了模板内容，将使用此
     *                                模板进行渲染，这对模板文件存储在数据库
     *                                中的方式来说比较有用
     * param return 页面输出内容，为html文本
     * */
    public function fetch($templateFile='', $templateContent=''){
        //根据配置选择模板引擎，并实例化一个对象
        $template = Tools::instance('Frame\Driver\Template\\'.C('TMPL_ENGINE_TYPE'));
        //调用引擎，获取编译后的HTML文档
        $content = $template->fetch($templateFile, $this->tVar, $templateContent);
        //返回解析后的内容
        return $content;
    }
    /*
     * 模板变量赋值函数
     * param $name 模板变量名 数组或字符串
     * paran $val  模板变量值
     * */
    public function assign($name, $val=''){
        is_array($name)?$this->tVar = array_merge($this->tVar,$name):$this->tVar[$name] = $val;
    }
    
    /*
     * 获取模板变量的值
     * param $name 模板变量名
     * */
    public function get($name=''){
        if('' === $name){
            return $this->tVar;
        }
        return isset($this->tVar[$name])?$this->tVar[$name]:false;
    }
    
    /*
     * 向前台输出HTML文档
     * param $content    编译后的内容
     * */
    public function render($content, $contentType='', $charset=''){
        if(empty($charset))  $charset = C('DEFAULT_CHARSET');
        if(empty($contentType)) $contentType = C('TMPL_CONTENT_TYPE');
        // 网页字符编码
        header('Content-Type:'.$contentType.'; charset='.$charset);
        header('Cache-control: '.C('HTTP_CACHE_CONTROL'));  // 页面缓存控制
        header('X-Powered-By:LEOS');
        echo $content;        
    }
}