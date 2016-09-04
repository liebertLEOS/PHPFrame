<?php
/**
 *          Template(Frame\Driver\Template\Template.class.php)
 *
 *    功　　能：模板类，负责处理用户视图模板接口
 *
 *    作　　者：李康
 *    完成时间：2015/08/14
 *
 */
namespace Frame\Driver\Template;
use Frame\Tools;

class Template{
    //模板变量
    protected  $tVar = array();
    
    // 当前模板文件
    protected   $templateFile    =   '';
    protected   $compilerFile    =   '';
 
    /**
     * 检查模板是否存在
     * param  $templateFile   模板文件名
     * return string          合法的模板文件全名
     * */
    protected function checkCache($templateFile){
        $module = strtolower(MODULE_NAME);
        //得到模板文件名 如果用户未定义视图目录将启用默认视图
        $view = defined(C('VIEW_PATH'))?C('VIEW_PATH'):APP_PATH.$module.'\\'.C('DEFAULT_V_LAYER');
        //如果未指定模板，则使用默认模板
        if('' == $templateFile){
            $this->templateFile = $view.'\\'.CONTROLLER_NAME.'.'.C('TMPL_TEMPLATE_SUFFIX');
            $this->compilerFile = RUNTIME_PATH.MODULE_NAME.'\\'.md5(CONTROLLER_NAME).CONTROLLER_NAME.'.'.C('TMPL_COMPILER_SUFFIX');
        }else{
            $this->templateFile = $view.'\\'.$templateFile.'.'.C('TMPL_TEMPLATE_SUFFIX');
            $this->compilerFile = RUNTIME_PATH.MODULE_NAME.'\\'.md5($templateFile).$templateFile.'.'.C('TMPL_COMPILER_SUFFIX');
        }
        
        
        if(is_file($this->compilerFile) && is_file($this->templateFile) && filemtime($this->compilerFile) > filemtime($this->templateFile) ){
            return true;
        }
        return false;
    }
    /*
     * 调用解析类Parse解析标签
     * */
    protected function parse($templateContent){
        if(empty($templateContent)) return '';//递归结束条件
        
        $parse = Tools::instance('Frame\\Driver\\Template\\Parse');
        $parse->parse($templateContent);//调用解析类Parse解析方法parse
        
        return $templateContent;
    }
    /*
     * 编译模板，并输出编译后的文件至硬盘上
     * param string $templateContent    模板内容
     * */
    protected function compiler($templateContent){
        $templateContent = $this->parse($templateContent);
        // 优化生成的php代码
        $templateContent = str_replace('?><?php','',$templateContent);
        file_put_contents($this->compilerFile, $templateContent);
    }
    /*
     * 渲染函数
     * @param string $compilerFile  编译后的文件名
     * */
    protected function render($compilerFile){
        ob_start();            //开启页面缓存
        ob_implicit_flush(0);  //刷新缓存区
        if(!is_file($compilerFile)){
            E('编译文件不存在，无法完成渲染：'.$compilerFile);
        }
        include $compilerFile; //加载编译后的文件
        return ob_get_clean(); //获取缓存区输出的内容，并情况缓存区
    }
    /*
     * 获取HTML文档
     * @param string $templateFile      模板文件名
     * @param string $templateContent   模板文件内容，模板存储至数据库中时采取直接编译模板内容的方式效果更好
     * */
    public function fetch($templateFile, $templateVar, $templateContent=''){  
        $this->tVar = $templateVar;
        //检查编译文件情况，决定是否需要编译
        if(!$this->checkCache($templateFile)){
            if(empty($templateContent)){
                //使用本地模板文件时需要检查模板文件是否存在
                if(!is_file($this->templateFile)) E('模板文件不存在：'.$this->templateFile);
                $templateContent = file_get_contents($this->templateFile);
            }
            $this->compiler($templateContent);
        }
        //渲染编译文件
        $content = $this->render($this->compilerFile);
        return $content;
    }


}