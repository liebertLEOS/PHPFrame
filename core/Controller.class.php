<?php
/**
 *          Controller(Frame\Controller.class.php)
 *
 *    功　　能：用户控制器类，用户业务逻辑控制器基础类
 *
 *    作　　者：李康
 *    完成时间：2015/08/14
 *    修　　改：2015/08/26 增加了ajaxReturn，处理客户端的异步请求
 *
 */
namespace Core;

use Core\Tools;

/*
 * Frame框架控制器基类，接口
 * */

abstract class Controller
{
    /*
     * 视图类对象 用于加载模板，渲染模板
     * */
    protected $view = null;
    /*
     * 控制器参数数组
     * */
    protected $config = array();

    /*
     * 重写控制器构造函数
     * 此处通过构造注入的方式注入视图类实例
     * */
    public function __construct()
    {
        $this->view = Tools::instance('Core\View');//实例化视图类View
        $this->_initialize();
    }

    protected function _initialize()
    {

    }

    /*
     * 控制器默认入口方法
     * */
    public function index()
    {
    }

    /*
     * 调用指定模板进行输出
     * param string $templateFile     模板文件名
     * param string $templateContent  模板内容，如果制定了模板内容，将使用此
     *                                模板进行渲染，这对模板文件存储在数据库
     *                                中的方式来说比较有用
     * */
    public function display($templateFile = '', $templateContent = '')
    {
        $this->view->display($templateFile, $templateContent);
    }

    /*
     * 获取页面输出的HTML内容
     * param string $templateFile     模板文件名
     * param string $templateContent  模板内容，如果制定了模板内容，将使用此
     *                                模板进行渲染，这对模板文件存储在数据库
     *                                中的方式来说比较有用
     * */
    public function fetch($templateFile = '', $templateContent = '')
    {
        $this->view->fetch($templateFile, $templateContent);
    }

    /*
     * 生成静态HTML文件，存储在本地磁盘上
     * param $htmlFile                 html文件名
     * param $htmlPath                 html文件路径
     * param $templateFile            用于渲染的模板文件
     * */
    public function buildHtml($htmlFile = '', $htmlPath = '', $templateFile = '')
    {
        $content = $this->fetch($templateFile);
        $htmlPath = !empty($htmlPath) ? $htmlPath : HTML_PATH;
        $htmlFile = $htmlPath . $htmlFile . HTML_FILE_SUFFIX;
        file_put_contents($htmlFile, $content);
        return $content;
    }

    /*
     * 模板变量赋值函数
     * param $name 模板变量名
     * paran $val  模板变量值
     * */
    public function assign($name, $val = '')
    {
        $this->view->assign($name, $val);
    }

    /*
     * 获取模板变量的值
     * param $name 模板变量名
     * */
    public function get($name = '')
    {
        return $this->view->get($name);
    }

    /*
     * ajax返回数据
     * */
    public function ajaxReturn($data, $type = '')
    {
        if (empty($type)) $type = C('DEFAULT_AJAX_DATA_TYPE');
        switch (strtoupper($type)) {
            case 'XML':
                header('Content-Type:text/xml; charset=utf-8');
                exit(xml_encode($data));
            case 'JSON':
                header('Content-Type:application/json; charset=utf-8');
                exit(json_encode($data));
        }
    }


}
