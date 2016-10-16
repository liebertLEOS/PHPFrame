<?php
/**
 *          Page(Frame\Page.class.php)
 *     
 *    功　　能：生成分页HTML代码
 *    
 *    作　　者：李康
 *    完成时间：2015/08/25
 * 
 */
namespace Core;

class Page{
    
    private $numPerPage;    //每页显示的数量
    private $href;          //<a>标签href跳转属性
    private $totalRows;     //记录总数
    private $totalPages;    //总页数
    private $intervals;     //需要显示的当前页与首页或末页之间的页数量

    private $currentPage;   //当前页
    private $html = array(
        'prev'  =>'',
        'next'  =>'',
        'format' =>'%UP_PAGE% %FIRST% %LINK_PAGE% %END% %DOWN_PAGE%'
    );
    
    public function __construct($totalRows,$numPerPage,$href,$intervals=''){
        $this->totalRows     = $totalRows;
        $this->numPerPage    = isset($numPerPage)? $numPerPage : 10;
        $this->href          = isset($href)? $href : '';
        $this->intervals     = empty($intervals)? 2 : $intervals;
        $this->html['prev']  = C('PAGE_PREV_TEXT') ? C('PAGE_PREV_TEXT')  : 'Prev';
        $this->html['next']  = C('PAGE_NEXT_TEXT') ? C('PAGE_NEXT_TEXT')  : 'Next';
    }
    /**
     * 输出分页html代码
     * @param unknown $currentPage
     * @return void|mixed
     */
    public function show($currentPage){
        $this->currentPage = $currentPage;
        if(0==$this->totalRows || 0==$this->numPerPage) return;
        $this->totalPages = ceil($this->totalRows/$this->numPerPage);
        
        if(!empty($this->totalPages) && $currentPage>$this->totalPages ){
            $this->currentPage = $this->totalPages;
        }
        //上一页
        $pagePrev = $this->currentPage - 1;
        $htmlPrev = $pagePrev > 0 ? '<li class="prev"><a href="'.$this->href.$pagePrev.'">'.$this->html['prev'].'</a></li>':'';
        //下一页
        $pageNext = $this->currentPage + 1;
        $htmlNext = ($pageNext <= $this->totalPages)?'<li class="next"><a href="'.$this->href.$pageNext.'">'.$this->html['next'].'</a></li>':'';
        //首页
        $htmlFirst = '';
        if(($this->currentPage - $this->intervals) > 1){
           $htmlFirst = '<li class="first"><a href="'.$this->href.'1">1</a></li><li>····</li>';
        }
        //末页
        $htmlLast = '';
        if(($this->currentPage + $this->intervals) < $this->totalPages){
            $htmlLast = '<li>····</li><li class="end"><a href="'.$this->href.$this->totalPages.'">'.$this->totalPages.'</a></li>';
        }
        //中间页
        $htmlInterval = '';
        //当前页左边部分
        for($i=1;$i<=$this->intervals;$i++){
            $page = $this->currentPage - $i;
            if(($page)<=0) break;
            $htmlInterval = '<li><a href="'.$this->href.$page.'">'.$page.'</a></li>'.$htmlInterval;
        }
        //当前页
        $htmlInterval .= '<li class="disabled"><a>'.$this->currentPage.'</a></li>';
        //当前页右边部分
        for($i = 1;$i<=$this->intervals;$i++){
            $page = $this->currentPage + $i;
            if(($page>$this->totalPages)) break;
            $htmlInterval .= '<li><a href="'.$this->href.$page.'">'.$page.'</a></li>';
        }
        //格式化输出分页HTML
        $page_str = str_replace(
            array('%UP_PAGE%', '%DOWN_PAGE%', '%FIRST%', '%LINK_PAGE%', '%END%'),
            array($htmlPrev, $htmlNext, $htmlFirst, $htmlInterval, $htmlLast),
            $this->html['format']);
        return '<ul class="pager">'.$page_str.'</ul>';
    }
}
