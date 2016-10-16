<?php
/*
 * C方法
 * 获取和设置配置变量
 * param $name     变量名
 * param $value    默认值
 * */
function C($name=null, $value=null, $default=null){
    //全局静态变量，存储配置变量数组，系统的一个实例中只有一个
    static $_config = array();
    //为空时返回全部
    if(empty($name)){
        return $_config;
    }
    if(is_string($name)){
        //二维数组检测
        if(strpos($name, '.')){
            $name = explode('.', $name);
            $name[0] = strtolower($name[0]);
            if(is_null($value)){
                return isset($_config[$name[0]][$name[1]])?$_config[$name[0]][$name[1]]:$default;
            }
            $_config[$name[0]][$name[1]] = $value;
        }else{
            $name = strtolower($name);
            if(is_null($value)){
                return isset($_config[$name])?$_config[$name]:$default;
            }
            $_config[$name] = $value;
        }
    }
    if(is_array($name)){
        //键名全部转换为小写
        $_config = array_merge($_config, array_change_key_case($name));
        return;
    }
    return null;
}
/*
 * 异常处理
 * */
function E($str){
    header('Content-Type:text/html; charset=utf-8');
    exit($str);
}
/*
 * 实例化一个模型
 */
function M($name='', $tablePrefix='', $connection=''){
    static $_model  = array();
    $class = 'Core\\Model';
    $id           =   (is_array($connection)?implode('',$connection):$connection).$tablePrefix . $name;
    if(!isset($_model[$id])){
        $_model[$id] = new $class($name,$tablePrefix,$connection);
    }
    return $_model[$id];
}
function parse_name($name, $type=0){
    if($type){
        return ucfirst(preg_replace_callback('/_([a-zA-Z])/', function($matches){return strtoupper($matches[1]);}, $name));
    }else{
        return strtolower(trim(preg_replace('/[A-Z]/', '_\\0', $name),'_'));
    }
}
/**
 * XML编码
 * @param mixed $data 数据
 * @param string $root 根节点名
 * @param string $item 数字索引的子节点名
 * @param string $attr 根节点属性
 * @param string $id   数字索引子节点key转换的属性名
 * @param string $encoding 数据编码
 * @return string
 */
function xml_encode($data, $root='root', $item='item', $attr='', $id='id', $encoding='utf-8'){
    if(is_array($attr)){
        $_attr = array();
        foreach ($attr as $key=>$val){
            $_attr[] = "{$key}=\"{$val}\"";
        }
        $attr = implode(' ', $_attr);
    }
    $attr   = trim($attr);
    $attr   = empty($attr) ? '' : " {$attr}";
    $xml    = "<?xml version=\"1.0\" encoding=\"{$encoding}\"?>";
    $xml   .= "<{$root}{$attr}>";
    $xml   .= data_to_xml($data, $item, $id);
    $xml   .= "</{$root}>";
    return $xml;
}
/**
 * 数据XML编码
 * @param mixed  $data 数据
 * @param string $item 数字索引时的节点名称
 * @param string $id   数字索引key转换为的属性名
 * @return string
 */
function data_to_xml($data, $item='item', $id='id') {
    $xml = $attr = '';
    foreach ($data as $key => $val) {
        if(is_numeric($key)){
            $id && $attr = " {$id}=\"{$key}\"";
            $key  = $item;
        }
        $xml    .=  "<{$key}{$attr}>";
        $xml    .=  (is_array($val) || is_object($val)) ? data_to_xml($val, $item, $id) : $val;
        $xml    .=  "</{$key}>";
    }
    return $xml;
}
    
    
