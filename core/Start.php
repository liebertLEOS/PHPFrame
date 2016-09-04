<?php
/**
 *          Mysqli(Frame\Start.php)
 *
 *    功　　能：系统启动文件
 *
 *    作　　者：李康
 *    完成时间：2015/08/14
 *    修　　改：2015/09/01   修复了默认控制器不存在，自动创建默认控制器文件
 *
 */
//加载配置文件
require_once 'function.php';
require_once 'Tools.class.php';

use Frame\Tools;
use Frame\Hook;

//定义系统根目录
defined('SYS_PATH')           or define('SYS_PATH', substr(dirname(__FILE__).'\\', 0, -6));
//定义应用名称
defined('APP_NAME')           or define('APP_NAME', 'App');
//定义应用存放目录
defined('APP_PATH')           or define('APP_PATH', SYS_PATH.APP_NAME.'\\');
//定义框架库目录
defined('FRAME_PATH')         or define('FRAME_PATH',SYS_PATH.'Frame\\');
//定义框架库配置目录
defined('CONF_PATH')          or define('CONF_PATH',FRAME_PATH.'Conf\\');
//定义视图目录,也就是模板存放目录
defined('TPL_PATH')           or define('TPL_PATH',SYS_PATH.'APP\\View\\');
//定义缓存目录
defined('RUNTIME_PATH')       or define('RUNTIME_PATH', SYS_PATH.'Runtime\\');
//定义html文件缓存的目录
defined('HTML_PATH')          or define('HTML_PATH', RUNTIME_PATH.'\\html');
//定义html文件的后缀
defined('HTML_FILE_SUFFIX')   or define('HTML_FILE_SUFFIX', 'html');

//开启会话
//session_start();
// 自动加载类库
spl_autoload_register('autoload');
function autoload($class){
    $name = strstr($class, '\\', true);
    //如果是系统框架(Frame)下类文件，否则加载应用目录(APP_PATH)下的类文件
    if(in_array($name, array('Frame')) || is_dir(SYS_PATH.$name)){
        $class = SYS_PATH.$class.'.class.php';
    }else {
        $class = APP_PATH.$class.'.class.php';
    }
    if(!is_file($class)){
        E('类文件不存在:'.$class);
    }
    require_once $class;
}

//加载系统配置变量
if(file_exists(CONF_PATH.'config.php')){
    C(include CONF_PATH.'config.php');
}
//加载系统模式配置
if(file_exists(CONF_PATH.'Mode\\common.php')){
    C(include CONF_PATH.'Mode\\common.php');
}
//加载模式行为
$tags = C('tags');
if(isset($tags)){
    Hook::import($tags);
}
//判断缓存目录是否创建
if(!is_dir(RUNTIME_PATH)){
    //创建缓存目录
    mkdir(RUNTIME_PATH, 0700);
}
//判断是否加载默认模块
$module     = isset($_GET['m'])? $_GET['m']:C('DEFAULT_MODULE');
//判断控制器
$controller = ucfirst(isset($_GET['c'])? $_GET['c']:C('DEFAULT_CONTROLLER'));
//判断函数
$function   = isset($_GET['f'])? $_GET['f']:C('DEFAULT_FUNCTION');

//判断模块是否存在
if(!file_exists(APP_PATH.$module)){
    //判断默认模块是否为默认模块
    if(C('DEFAULT_MODULE') != $module)
        E('访问的模块不存在：'.APP_PATH.APP_PATH.$module);
    /*
     * 如果应用目录不存在，需要初始化创建
     * 支持自动部署
     * */
    if(!is_dir(APP_PATH)){
        //创建应用目录
        mkdir(APP_PATH, 0700);
        //创建公共文件目录
        mkdir(APP_PATH.'\\Common', 0700);
        //创建配置文件目录
        mkdir(APP_PATH.'\\Conf', 0700);
    }
    /*
     * 默认模块不存在，支持自动创建机制
     * */
    //创建默认模块目录
    mkdir(APP_PATH.C('DEFAULT_MODULE'), 0700);
    //创建控制器目录
    mkdir(APP_PATH.C('DEFAULT_MODULE').'\\'.C('DEFAULT_C_LAYER'), 0700);
    //创建视图目录
    mkdir(APP_PATH.C('DEFAULT_MODULE').'\\'.C('DEFAULT_V_LAYER'), 0700);
    //创建模型目录
    mkdir(APP_PATH.C('DEFAULT_MODULE').'\\'.C('DEFAULT_M_LAYER'), 0700);
    //创建行为目录
    mkdir(APP_PATH.C('DEFAULT_MODULE').'\\'.C('DEFAULT_B_LAYER'), 0700);
    //创建默认控制器文件
    $file=APP_PATH.C('DEFAULT_MODULE').'\\'.C('DEFAULT_C_LAYER').'\\'.C('DEFAULT_CONTROLLER').'Controller.class.php';
    $str = '<?php
namespace '.C("DEFAULT_MODULE").'\\'.C("DEFAULT_C_LAYER").';
use Frame\Controller;

class IndexController extends Controller{
    public function index(){
        echo "Welcome to LEOS Studio! ";
    }
}
?>';
    file_put_contents($file, $str);//创建默认控制器文件
    
}
//模块缓存目录不存在时创建
if(!is_dir(RUNTIME_PATH.$module)){
    mkdir(RUNTIME_PATH.$module, 0700);
}
/* *****************************************************************************
 * 加载应用配置变量
 * ****************************************************************************/
if(file_exists(APP_PATH.'\\Conf\\config.php')){
    C(include APP_PATH.'\\Conf\\config.php');
}
/* *****************************************************************************
 * 判断控制器是否存在
 * ****************************************************************************/
$c = APP_PATH.$module.'\\'.C('DEFAULT_C_LAYER').'\\'.$controller.'Controller.class.php';
if(!file_exists($c)){
    E('访问的控制器不存在：'.$c);
}

/* *****************************************************************************
 * 判断控制器下是否存在该方法
 * ****************************************************************************/
$obj = Tools::instance($module.'\\'.C('DEFAULT_C_LAYER').'\\'.$controller.'Controller');
if(!method_exists($obj, $function)){
    E('该控制器下不存在此方法：public function '.$function);
}

define('MODULE_NAME',$module);//传递全局模块目录
define('CONTROLLER_NAME',$controller);//传递全局控制器名

$obj->$function();//运行此方法

