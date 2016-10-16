<?php
/**
 *          Hook(Frame\Hook.class.php)
 *
 *    功　　能：钩子类
 *
 *    作　　者：李康
 *    完成时间：2015/08/27
 *
 */
namespace Core;

class Hook{
    static $tags = array();//标签数组，是一个二维数组，键名就是标签名，键值就是添加到这个标签上的行为集合数组
    /**
     * 启动钩子进行监听
     * @param unknown $tag
     * @param string $params
     */
    static public function listen($tag, &$params=null){
        if(isset(self::$tags[$tag])){
            foreach(self::$tags[$tag] as $name){
                $method = $tag;
                if('Behavior' == strpos($name, -8)){
                    $class  = $name;
                    $method = 'run';
                }else{
                    $class = "plugins\\{$name}\\{$name}.Plugin";
                }

                $obj    = new $class();
                if($obj->$method($params) == false){
                    return ;
                }
            }
        }
    }
    /**
     * 为指定标签添加行为
     * @param unknown $tag
     * @param unknown $name
     */
    static public function add($tag,$name){
        if(!isset(self::$tags[$tag])){
            self::$tags[$tag] = array();
        }
        if(is_array($name)){
            self::$tags[$tag] = array_merge(self::$tags[$tag],$name);
        }else{
            self::$tags[$tag] = $name;
        }
    }
    /**
     * 导入行为标签
     * @param array  $data    要导入的行为
     * @param string $overlay 是否覆盖
     */
    static public function import($data, $overlay=true){
        if($overlay){
            self::$tags = array_merge(self::$tags,$data);
        }else{//追加
            foreach($data as $tag=>$val){
                if(!isset(self::$tags[$tag])){
                    self::$tags[$tag] = array();
                }
                if(empty($val['_overlay'])){
                    self::$tags[$tag] = array_merge(self::$tags[$tag],$val);
                }else{
                    unset($val['_overlay']);
                    self::$tags[$tag] = $val;
                }
            }
        }
    }
}


