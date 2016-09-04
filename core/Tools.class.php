<?php
namespace Frame;

class Tools{
    
    /*
     * 类实例数组
     * 公共对象只需要实例化一次即可，为了防止实例化多次，提高运行效率
     * 实例化后的对象在此K-V数组中统一注册，在需要的时候进行索引即可
     * 可大大提高效率，减少内存的分配
     * */
    public static $_instance = array();
    
    /*
     * 获取实例
     * */
    public static function  instance($class){
        //如果该类未注册，需要进行实例化并注册
        if(!isset(self::$_instance[$class])){
            if(class_exists($class)){
                self::$_instance[$class] = new $class();
            }else{
                E('该类不存在：'.$class);
            }
        }
        return self::$_instance[$class];
    }
}