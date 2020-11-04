<?php
// ----------------------------------------------------------------------------
// Features:  後台 -- ui設定
// File Name: 
// Author:     orange
// Related: uisetting_action
// DB Table:
// Log:
// ----------------------------------------------------------------------------
session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------
//有登入且為superuser通行
if(!isset($_SESSION['agent']) || !in_array($_SESSION['agent']->account, $su['superuser'])){
  header('Location:./home.php');
  die("请确认您的帐号权限");
}
// ----------------------------------------------------------------------------
//
$domain_data = runSQLall('SELECT * FROM site_subdomain_setting WHERE open = 1;');
$domain_list=array();
$domain_id_val=array();
foreach ($domain_data as $key => $value) {
  if($key == 0)
    continue;
  $subdomain_data=json_decode($value->configdata);
  $subdomain_list=array();
  foreach ($subdomain_data as $s_key => $s_value) {
    if($subdomain_data->$s_key->open == 1)
      if(isset($subdomain_data->$s_key->websiteName) AND $subdomain_data->$s_key->websiteName!='')
        $websiteName = $subdomain_data->$s_key->websiteName.'(%s)';
      else
        $websiteName = '%s';
      $websitePath = $subdomain_data->$s_key->style->desktop->suburl.'/'.$subdomain_data->$s_key->style->mobile->suburl;
      array_push($subdomain_list,array('sid'=>$s_key,'subdomainname'=>sprintf($websiteName,$websitePath)));
  }
  $domain_list[$value->domainname]=array('id'=>$value->id,'subdomain'=>$subdomain_list);
}
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title     =  $tr['f subdomain management'];
// 擴充 head 內的 css or js
$extend_head        = '';
// 放在結尾的 js
$extend_js          = '';
// body 內的主要內容
$indexbody_content  = '';
// 公告訊息
$messages = '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs =<<<HTML
<ol class="breadcrumb">
  <li><a href="home.php">{$tr['Home']}</a></li>
  <li><a href="#">{$tr['webmaster']}</a></li>
  <li class="active">{$function_title}</li>
</ol>
HTML;

// ----------------------------------------------------------------------------
//----------------------------------------------------------------------------------------------
//----- init ---------
if(isset($_GET['i']) AND isset($_GET['sid'])) {
  $domain = $_GET['i'];
  $sub_domain = $_GET['sid'];
  $init_js=<<<HTML
  <script type="text/javascript">
    var domain_id={$domain};
    var subdomain_id={$sub_domain};
    $('#i-'+domain_id).addClass('active show');
    $('#tab-'+domain_id).tab('show');
    $('#tab-'+domain_id+' #sid-'+subdomain_id).addClass('active show');
    $('#uisetting-index').tab('show');
  </script>
HTML;
  $extend_js .= $init_js;
}

//----------------------------------------------------------------------------------------------
$domain_list_html='';
foreach ($domain_list as $key=>$value) {
  $domain_list_html.=<<<HTML
  <li class="list-group-item p-0"><a class="nav-link" id="i-{$value['id']}" data-toggle="pill" href="#tab-{$value['id']}" role="tab" aria-controls="v-pills-home" aria-selected="true">{$key}</a>    
  </li>
HTML;
}

$subdomain_list_html='';
foreach ($domain_list as $key=>$value) {
  $subdomain_list_item_html='';
  //var_dump($value['subdomain']);
  foreach ($value['subdomain'] as $s_key => $s_value) {
    $subdomain_list_item_html.=<<<HTML
    <li class="list-group-item p-0"><a class="nav-link" id="sid-{$s_value['sid']}" data-toggle="pill" href="#uisetting-index" role="tab" aria-controls="v-pills-home" aria-selected="true">{$s_value['subdomainname']}</a></li>
HTML;
  }
  $subdomain_list_html.=<<<HTML
    <div class="card nav flex-column nav-pills tab-pane fade" id="tab-{$value['id']}" role="tabpanel" aria-labelledby="v-pills-home-tab">
    <div class="card-header text-center">
      {$tr['selected subdomain desktop mobile'] }
    </div>    
    <ul class="list-group list-group-flush" style="max-height: 680px;overflow-y: scroll;">
      <a href="uisetting_view.php?i={$value['id']}" class="btn btn-success"><i class="fas fa-sign-in-alt mr-2"></i>{$tr['maindomain ui setting']}</a>
      {$subdomain_list_item_html}
    </ul>
  </div>  
HTML;
}

$uisetting_index=<<<HTML
<div class="card nav flex-column nav-pills tab-pane fade" id="uisetting-index" role="tabpanel" aria-labelledby="v-pills-home-tab">
  <div class="card-header text-center">
      {$tr['front website management']}
  </div>   
  <div class="uisetting-link row mx-1 my-2 card-body">
   <div class="w-100 border rounded px-2 pt-2 mb-2">
      <h5>{$tr['Layout setting']}</h5>
     <button onclick="gouisetting('view_menu')" class="btn btn-primary btn-block mb-2">{$tr['enter']}</button>
   </div>

   <div class="w-100 border rounded px-2 pt-2 mb-2">
      <h5>{$tr['copywriting management']}</h5>
     <button onclick="gouisetting('view_copy')" class="btn btn-primary btn-block mb-2">{$tr['enter']}</button>
   </div>

   <div class="w-100 border rounded px-2 pt-2 mb-2">
      <h5>{$tr['banner carousel ad']}</h5>
     <button onclick="gouisetting('view_carousel')" class="btn btn-primary btn-block mb-2">{$tr['enter']}</button>
   </div>

   <div class="w-100 border rounded px-2 pt-2 mb-2">
      <h5>{$tr['floating ad setting']}</h5>
     <button onclick="gouisetting('view_component')" class="btn btn-primary btn-block mb-2">{$tr['enter']}</button>
   </div>

   <div class="w-100 border rounded px-2 pt-2 mb-2">
      <h5>{$tr['template management']}</h5>
     <button onclick="gouisetting('view_template')" class="btn btn-primary btn-block mb-2">{$tr['enter']}</button>
   </div>

  </div>
</div>
HTML;

$contents=<<<HTML
  <div class="row">
    <div class="col-2 pr-0">
      <div class="card nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
        <div class="card-header text-center">
        {$tr['selected domain']}
        </div>
        <ul id="tab-domain" class="list-group list-group-flush" style="max-height: 680px;overflow-y: scroll;">
        {$domain_list_html}
        </ul>
      </div>
    </div>
    <div class="col-4">
      <div class="tab-content" id="tab-subdomain">
        {$subdomain_list_html}
      </div>
    </div>
    <div class="col-6 pl-0">
      <div class="tab-content" id="v-pills-tabContent">
        {$uisetting_index}
      </div>
    </div>
  </div>
HTML;

$indexbody_content .=$contents;
$extend_js .=<<<HTML
<script type="text/javascript">
  function gouisetting(config){
    var sid=$('#tab-subdomain .card.active .nav-link.active').attr('id');
    if(typeof sid == 'undefined'){
      alert('请选择子网域');
      return false;
    }
    var getcode=$('#tab-domain .nav-link.active').attr('id')+'&'+ sid;
    getcode=getcode.replace(/-/g, "=");
    location.href='uisetting_view.php?'+getcode+'&p='+config;
  }
</script>
<style type="text/css">
  .panel-body{
    min-height: 591px;
  }
  .uisetting-link{
    border: 0px solid #dee2e6;
    opacity: 0.9;
    transition: all 0.3s;
  }
  .uisetting-link:hover{
    cursor: pointer;
    /*border: 1px solid #17a2b8;*/
    opacity: 1;
  }
</style>
HTML;
// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description']    = $tr['host_descript'];
$tmpl['html_meta_author']         = $tr['host_author'];
$tmpl['html_meta_title']          = $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']               = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']              = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']                = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content']       = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']        = $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");

?>