<?php
/**
 *          Parse(Frame\Driver\Template\Parse.class.php)
 *
 *    功　　能：模板解析引擎类，负责处理用户视图模板标签的内置解析引擎
 *
 *    作　　者：李康
 *    完成时间：2015/08/14
 *
 */
namespace Frame\Driver\Template;

class Parse{
    private $config = array();
    protected $tags   =  array(
        // 标签定义： attr 属性列表 close 是否闭合（0 或者1 默认1） alias 标签别名 level 嵌套层次
        'foreach'   =>  array('attr'=>'name,item,key'),
        'if'        =>  array('attr'=>'condition'),
        'elseif'    =>  array('attr'=>'condition','close'=>0),
        'else'      =>  array('attr'=>'','close'=>0),
        'switch'    =>  array('attr'=>'name','level'=>2),
        'case'      =>  array('attr'=>'value,break'),
        'default'   =>  array('attr'=>'','close'=>0),
        'eq'        =>  array('attr'=>'name,value'),
        'neq'       =>  array('attr'=>'name,value'),
        'gt'        =>  array('attr'=>'name,value'),
        'lt'        =>  array('attr'=>'name,value'),
        'egt'       =>  array('attr'=>'name,value'),
        'elt'       =>  array('attr'=>'name,value'),
        'heq'       =>  array('attr'=>'name,value'),
        'nheq'      =>  array('attr'=>'name,value'),
        'import'    =>  array('attr'=>'file,href,type,value,basepath','close'=>0,'alias'=>'load,css,js'),
    	'for'       =>  array('attr'=>'start,end,name,comparison,step', 'level'=>3),
        );
    protected $comparison = array(' nheq '=>' !== ',' heq '=>' === ',' neq '=>' != ',' eq '=>' == ',' egt '=>' >= ',' gt '=>' > ',' elt '=>' <= ',' lt '=>' < ');
    
    public function __construct(){
        $this->config['taglib_begin'] = C('TAGLIB_BEGIN');
        $this->config['taglib_end']   = C('TAGLIB_END');
    }
    /**
     * 分析XML属性
     * @access private
     * @param  string $attrs  XML属性字符串
     * @return array  属性'K-V'数组
     */
    protected function parseXmlAttrs($attrs) {
        $xml        =   '<tpl><tag '.$attrs.' /></tpl>';
        $xml        =   simplexml_load_string($xml);
        if(!$xml)
            E('_XML_TAG_ERROR_');
        $xml        =   (array)($xml->tag->attributes());
        $array      =   array_change_key_case($xml['@attributes']);
        return $array;
    }
    /*
     * 解析XML标签
     * @param string $name    标签名  根据标签名得到解析方法名
     * @param string $attr    标签属性字符串
     * @param string $content 待解析的内容
     * */
    public  function parseXMLTag($name, $attr,$content){
        $attr    =	str_replace('\"','\'',$attr);
        $parse   = '_'.$name;
        //$content = trim($content);
        $tags    = $this->parseXmlAttrs($attr);
        return $this->$parse($tags,$content);
    }
    /**
     * 解析条件表达式
     * @access public
     * @param string $condition 表达式标签内容
     * @return array
     */
    public function parseCondition($condition) {
        $condition = str_ireplace(array_keys($this->comparison),array_values($this->comparison),$condition);
        $condition = preg_replace('/\$(\w+):(\w+)\s/is','$\\1->\\2 ',$condition);
        // 自动判断数组或对象 只支持二维
        $condition  =   preg_replace('/\$(\w+)\.(\w+)\s/is','(is_array($\\1)?$\\1["\\2"]:$\\1->\\2) ',$condition);
        if(false !== strpos($condition, '$Think'))
            $condition      =   preg_replace_callback('/(\$Think.*?)\s/is', array($this, 'parseThinkVar'), $condition);
        return $condition;
    }
    /**
     * 自动识别构建变量
     * @access public
     * @param string $name 变量描述
     * @return string
     */
    public function autoBuildVar($name) {
        if('LEOS.' == substr($name,0,6)){
            return $this->parseLEOSVar($name);
        }elseif(strpos($name,'.')) {
            $vars = explode('.',$name);
            $var  =  array_shift($vars);
            $name = 'is_array($'.$var.')?$'.$var.'["'.$vars[0].'"]:$'.$var.'->'.$vars[0];// 自动判断数组或对象 只支持二维
        }elseif(!defined($name)) {
            $name = 'isset($this->tVar["'.$name.'"]) ? $this->tVar["'.$name.'"] :null';
        }
        return $name;
    }
    /**
     * 用于标签属性里面的特殊模板变量解析
     * 格式 以 LEOS. 打头的变量属于特殊模板变量
     * @access public
     * @param string $varStr  变量字符串
     * @return string
     */
    public function parseLEOSVar($varStr){
        if(is_array($varStr)){//用于正则替换回调函数
            $varStr = $varStr[1];
        }
        $vars       = explode('.',$varStr);
        $vars[1]    = strtoupper(trim($vars[1]));
        $parseStr   = '';
        if(count($vars)>=3){
            $vars[2] = trim($vars[2]);
            switch($vars[1]){
                case 'SERVER':    $parseStr = '$_SERVER[\''.$vars[2].'\']';break;
                case 'GET':         $parseStr = '$_GET[\''.$vars[2].'\']';break;
                case 'POST':       $parseStr = '$_POST[\''.$vars[2].'\']';break;
                case 'COOKIE':
                    if(isset($vars[3])) {
                        $parseStr = '$_COOKIE[\''.$vars[2].'\'][\''.$vars[3].'\']';
                    }elseif(C('COOKIE_PREFIX')){
                        $parseStr = '$_COOKIE[\''.C('COOKIE_PREFIX').$vars[2].'\']';
                    }else{
                        $parseStr = '$_COOKIE[\''.$vars[2].'\']';
                    }
                    break;
                case 'SESSION':
                    if(isset($vars[3])) {
                        $parseStr = '$_SESSION[\''.$vars[2].'\'][\''.$vars[3].'\']';
                    }elseif(C('SESSION_PREFIX')){
                        $parseStr = '$_SESSION[\''.C('SESSION_PREFIX').'\'][\''.$vars[2].'\']';
                    }else{
                        $parseStr = '$_SESSION[\''.$vars[2].'\']';
                    }
                    break;
                case 'ENV':         $parseStr = '$_ENV[\''.$vars[2].'\']';break;
                case 'REQUEST':  $parseStr = '$_REQUEST[\''.$vars[2].'\']';break;
                case 'CONST':     $parseStr = strtoupper($vars[2]);break;
                case 'LANG':       $parseStr = 'L("'.$vars[2].'")';break;
                case 'CONFIG':    $parseStr = 'C("'.$vars[2].'")';break;
            }
        }else if(count($vars)==2){
            switch($vars[1]){
                case 'NOW':       $parseStr = "date('Y-m-d g:i a',time())";break;
                case 'VERSION':  $parseStr = 'THINK_VERSION';break;
                case 'TEMPLATE':$parseStr = 'C("TEMPLATE_NAME")';break;
                case 'LDELIM':    $parseStr = 'C("TMPL_L_DELIM")';break;
                case 'RDELIM':    $parseStr = 'C("TMPL_R_DELIM")';break;
                default:  if(defined($vars[1])) $parseStr = $vars[1];
            }
        }
        return $parseStr;
    }
    /*
     * 解析标签
     * @param  $templateContent  待解析内容引用
     * */
    public function parse(&$templateContent){
        $begin = $this->config['taglib_begin'];
        $end   = $this->config['taglib_end'];
        $that = $this;
        //解析标签库标签
        foreach($this->tags as $name => $val){
            //判断标签是否存在属性来生成相应的正则
            $attr = empty($val['attr'])? '(\s*?)':'\s([^'.$end.']*)';
            //判断是否是闭合标签 0：不是闭合   1：是闭合标签   默认是闭合标签
            $iscloseTag = isset($val['close'])?$val['close']:true;
            if($iscloseTag){
                $patterns = '/'.$begin.$name.$attr.$end.'(.*?)'.$begin.'\/'.$name.'\s*?'.$end.'/is';
            }else{
                $patterns = '/'.$begin.$name.$attr.'\/(\s*?)'.$end.'/is';
            }
            $templateContent = preg_replace_callback($patterns, function($matches) use($name, $that){
                return $that->parseXMLTag($name, $matches[1],$matches[2]);
            }, $templateContent);
            
        }
        //解析普通变量标签 {$user.name}
        $templateContent = preg_replace_callback('/\{\$([\w.]+)\}/is', array($this,'parseVar'), $templateContent);
        return $templateContent;
    }
    public function parseVar($tagVar){
        $var       = $tagVar[1];
        $parseStr  = '<?php echo ';
        $parseStr .= $this->autoBuildVar($var).';';
        $parseStr .= ' ?>';
        return $parseStr;
    }
    /**
     * foreach标签解析 循环输出数据集
     * @access public
     * @param array $tag 标签属性
     * @param string $content  标签内容
     * @return string|void
     */
    public function _foreach($tag,$content) {
        $name       =   $tag['name'];
        $item       =   $tag['item'];
        $key        =   !empty($tag['key'])?$tag['key']:'key';
        $name       =   $this->autoBuildVar($name);
        $parseStr   =   '<?php if(is_array('.$name.')){ foreach('.$name.' as $'.$key.'=>$'.$item.'){ ?>';
        $parseStr  .=   $this->parse($content);
        $parseStr  .=   '<?php } } ?>';
    
        if(!empty($parseStr)) {
            return $parseStr;
        }
        return ;
    }
    /**
     * if标签解析
     * 格式：
     * <if condition=" $a eq 1" >
     * <elseif condition="$a eq 2" />
     * <else />
     * </if>
     * 表达式支持 eq neq gt egt lt elt == > >= < <= or and || &&
     * @access public
     * @param array $tag 标签属性
     * @param string $content  标签内容
     * @return string
     */
    public function _if($tag,$content) {
        $condition  =   $this->parseCondition($tag['condition']);
        $parseStr   =   '<?php if('.$condition.'){ ?>'.$content.'<?php } ?>';
        return $parseStr;
    }

    /**
     * else标签解析
     * 格式：见if标签
     * @access public
     * @param array $tag 标签属性
     * @param string $content  标签内容
     * @return string
     */
    public function _elseif($tag,$content) {
        $condition  =   $this->parseCondition($tag['condition']);
        $parseStr   =   '<?php elseif('.$condition.'): ?>';
        return $parseStr;
    }

    /**
     * else标签解析
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function _else($tag) {
        $parseStr = '<?php else: ?>';
        return $parseStr;
    }
    /**
     * compare标签解析
     * 用于值的比较 支持 eq neq gt lt egt elt heq nheq 默认是eq
     * 格式： <compare name="" type="eq" value="" >content</compare>
     * @access public
     * @param array $tag 标签属性
     * @param string $content  标签内容
     * @return string
     */
    public function _compare($tag,$content,$type='eq') {
        $name       =   $tag['name'];
        $value      =   $tag['value'];
        $type       =   $this->parseCondition(' '.$type.' ');
        $varArray   =   explode('|',$name);
        $name       =   array_shift($varArray);
        $name       =   $this->autoBuildVar($name);
        if(count($varArray)>0)
            $name = $this->tpl->parseVarFunction($name,$varArray);
        if('$' == substr($value,0,1)) {
            $value  =  $this->autoBuildVar(substr($value,1));
        }else {
            $value  =   '"'.$value.'"';
        }
        $parseStr   =   '<?php if(('.$name.') '.$type.' '.$value.'){ ?>'.$content.'<?php } ?>';
        return $parseStr;
    }
    /* 
     * 等于
     * */
    public function _eq($tag,$content) {
        return $this->_compare($tag,$content,'eq');
    }
    /*
     * 不等于
     * */
    public function _neq($tag,$content) {
        return $this->_compare($tag,$content,'neq');
    }
    /**
     * 大于
     * @param  array   $tag
     * @param  string  $content
     * @return string
     */
    public function _gt($tag,$content) {
        return $this->_compare($tag,$content,'gt');
    }
    /**
     * 小于
     * @param unknown $tag
     * @param unknown $content
     * @return string
     */
    public function _lt($tag,$content) {
        return $this->_compare($tag,$content,'lt');
    }
    /**
     * 大于等于
     * @param unknown $tag
     * @param unknown $content
     * @return string
     */
    public function _egt($tag,$content) {
        return $this->_compare($tag,$content,'egt');
    }
    /**
     * 小于等于
     * @param unknown $tag
     * @param unknown $content
     * @return string
     */
    public function _elt($tag,$content) {
        return $this->_compare($tag,$content,'elt');
    }
    /**
     * 恒等于
     * @param unknown $tag
     * @param unknown $content
     * @return string
     */
    public function _heq($tag,$content) {
        return $this->_compare($tag,$content,'heq');
    }
    /**
     * 不恒等于
     * @param unknown $tag
     * @param unknown $content
     * @return string
     */
    public function _nheq($tag,$content) {
        return $this->_compare($tag,$content,'nheq');
    }
        
}
