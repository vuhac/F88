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
// uisetting函式庫
require_once dirname(__FILE__) ."/lib_uisetting.php";

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
  die();
}

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 擴充 head 內的 css or js
$extend_head        = '';
// 放在結尾的 js
$extend_js          = '';
// body 內的主要內容
$indexbody_content  = '';
// 公告訊息
$messages = '';
$maindomain_data=null;
function data_filter(&$input){
  foreach ($input as $key => $value) {
      if(is_array($input[$key])){
          data_filter($input[$key]);
      }else{
          $input[$key] = urldecode($value);
        }
    }
}
// ----------------------------------------------------------------------------
//GET行為呼叫
if(isset($_GET['i']) AND isset($_GET['sid'])) {  
//子網域前台管理
    $domain = $_GET['i'];
    $sub_domain = $_GET['sid'];
    $init = uisetting_init($domain,$sub_domain);    
    if($init==false){
      die($tr['Wrong operation']);
    }else{
      $component_id = $init['id'];
      $ui_data = json_decode($init['data'],true);
      data_filter($ui_data);
      $maindomain_data = json_decode($init['maindomain_data'],true);
      data_filter($maindomain_data);
      $nowat = $init['site'];
    }
// 功能標題，放在標題列及meta
$function_title     =  $tr['front subdomain management'];
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs =<<<HTML
<ol class="breadcrumb">
  <li><a href="home.php">{$tr['Home']}</a></li>
  <li><a href="#">{$tr['webmaster']}</a></li>
  <li><a href="./uisetting_management.php?i={$domain}&sid={$sub_domain}">{$tr['f subdomain management']}</a></li>
  <li class="active">{$function_title}</li>
</ol>
HTML;
  $view_mode = 'subdomain';
}elseif(isset($_GET['i']) AND !isset($_GET['sid'])){
// 主網域前台管理
    $domain = $_GET['i'];
    $init = maindomain_uisetting_init($domain);
    if($init==false){
      die($tr['Wrong operation']);
    }else{
      $component_id = $init['id'];
      $ui_data = json_decode($init['data'],true);
      data_filter($ui_data);
      $nowat = $init['site'];
    }
// 功能標題，放在標題列及meta
$function_title     =  $tr['maindomain ui setting'];
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs =<<<HTML
<ol class="breadcrumb">
  <li><a href="home.php">{$tr['Home']}</a></li>
  <li><a href="#">{$tr['webmaster']}</a></li>
  <li><a href="./uisetting_management.php">{$tr['f subdomain management']}</a></li>
  <li class="active">{$tr['maindomain ui setting']}</li>
</ol>
HTML;
  $view_mode = 'maindomain';
}else{
  die($tr['Wrong operation']);
}
// ----------------------------------------------------------------------------

if(isset($_GET['p'])){
  $extend_js .=<<<HTML
  <script>
    $('#main_tab a[href="#{$_GET['p']}"]').tab('show');
  </script>
HTML;
}
$extend_js .='
<script>
var cdnurl = "in/component/";
var upload_cdnurl = "'.$config["cdn_login"]["url"].$config["cdn_login"]["base_path"].'upload/uisetting/";
$(function () {
  $(\'[data-toggle="tooltip"]\').tooltip()
})
</script>
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
';
$extend_js .= '<link type="text/css" rel="stylesheet" href="in/component/component.css">';
$extend_js .= "<script src='in/component/component_be.js'></script>";
$extend_js .=<<<HTML
  <script type="text/javascript">
    //json的取得、更新、覆蓋、刪除function
    function json_send(act,index,data,callback,op_alert=1){
      $.ajax({
        url:'uisetting_action.php?act='+act+'&cid={$component_id}',
        method:'POST',
        data:{
          index:index,
          data:data
        },
        success:function(res){ 
            // console.log(res);
            if(op_alert != 0)
              alert(res);
            typeof callback === 'function' && callback();
        }
     });
  };   
  function json_get(callback){
    $.ajax({
        url:'uisetting_action.php?act=get&cid={$component_id}',
        method:'GET',
        success:function(res){
          var data=JSON.parse(res);
          typeof callback === 'function' && callback(data);
        }
    });
  };
  </script>
HTML;

//----------------------------------------------------------------------------------------------  
// 各個頁面

if($view_mode=='maindomain'){
  // require_once dirname(__FILE__) ."/uisetting_view_menu.php";
  require_once dirname(__FILE__) ."/uisetting_view_copy.php";
  require_once dirname(__FILE__) ."/uisetting_view_carousel.php";
  require_once dirname(__FILE__) ."/uisetting_view_component.php";
}else{
  require_once dirname(__FILE__) ."/uisetting_view_menu.php";
  require_once dirname(__FILE__) ."/uisetting_view_copy.php";
  require_once dirname(__FILE__) ."/uisetting_view_carousel.php";
  require_once dirname(__FILE__) ."/uisetting_view_component.php";
  require_once dirname(__FILE__) ."/uisetting_view_template.php";  
}
//----------------------------------------------------------------------------------------------
// 元件設定
//----------------------------------------------------------------------------------------------
if($view_mode=='maindomain'){
$view_list=<<<HTML
<ul class="nav nav-tabs" id="main_tab" role="tablist">
  <li class="nav-item">
    <a class="nav-link active" id="profile-tab" data-toggle="tab" href="#view_copy" role="tab" aria-controls="profile" aria-selected="true"">{$tr['copywriting management']}</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="contact-tab" data-toggle="tab" href="#view_carousel" role="tab" aria-controls="contact" aria-selected="false">{$tr['banner carousel ad']}</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="contact-tab" data-toggle="tab" href="#view_component" role="tab" aria-controls="contact" aria-selected="false">{$tr['floating ad setting']}</a>
  </li>
</ul>
<div class="tab-content" id="myTabContent">
  <div class="tab-pane fade show active" id="view_copy" role="tabpanel" aria-labelledby="profile-tab">{$uisetting_view_copy}</div>
  <div class="tab-pane fade" id="view_carousel" role="tabpanel" aria-labelledby="contact-tab">{$uisetting_view_carousel}</div>
  <div class="tab-pane fade" id="view_component" role="tabpanel" aria-labelledby="contact-tab">{$uisetting_view_component}</div>
</div>
HTML;
$extend_js .=<<<HTML
<script>
$(document).ready(function() {  
  json_get(function(data){
    init_carousel_table(data)
  })
})
</script>
HTML;
}else{
$view_list=<<<HTML
<ul class="nav nav-tabs" id="main_tab" role="tablist">
  <li class="nav-item">
    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#view_menu" role="tab" aria-controls="home" aria-selected="true">{$tr['Layout setting']}</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="profile-tab" data-toggle="tab" href="#view_copy" role="tab" aria-controls="profile" aria-selected="false">{$tr['copywriting management']}</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="contact-tab" data-toggle="tab" href="#view_carousel" role="tab" aria-controls="contact" aria-selected="false">{$tr['banner carousel ad']}</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="contact-tab" data-toggle="tab" href="#view_component" role="tab" aria-controls="contact" aria-selected="false">{$tr['floating ad setting']}</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="contact-tab" data-toggle="tab" href="#view_template" role="tab" aria-controls="contact" aria-selected="false">{$tr['template management']}</a>
  </li>
</ul>
<div class="tab-content" id="myTabContent">
  <div class="tab-pane fade show active" id="view_menu" role="tabpanel" aria-labelledby="home-tab">{$uisetting_view_menu}</div>
  <div class="tab-pane fade" id="view_copy" role="tabpanel" aria-labelledby="profile-tab">{$uisetting_view_copy}</div>
  <div class="tab-pane fade" id="view_carousel" role="tabpanel" aria-labelledby="contact-tab">{$uisetting_view_carousel}</div>
  <div class="tab-pane fade" id="view_component" role="tabpanel" aria-labelledby="contact-tab">{$uisetting_view_component}</div>
  <div class="tab-pane fade" id="view_template" role="tabpanel" aria-labelledby="contact-tab">{$uisetting_view_template}</div>
</div>
HTML;
$extend_js .=<<<HTML
<script>
$(document).ready(function() {  
  json_get(function(data){
    init_morelink_table(data);
    init_carousel_table(data);
    init_shorturl(data)
    init_highlight(data)
    init_footerlogo(data)
  })
})
</script>
HTML;
}
//-------------------------------------------------------------------------------------------
//放入templ
//-------------------------------------------------------------------------------------------
//開頭標題
$pagetitle=<<<HTML
<div class="alert alert-primary" role="alert">
  {$tr['now at']}：{$nowat}  <a class="float-right" href="./uisetting_management.php">({$tr['wrong reselected']})</a>
</div>
HTML;

$sid = (isset($sub_domain))? '&sid='.$sub_domain:'';

//將html放入content
$indexbody_content .=$pagetitle.$view_list.<<<HTML
<div class="row">
  <div class="col-12">
    <button type="button" class="btn btn-primary btn-block" onclick="location.href='./uisetting_management.php?i={$domain}{$sid}';"><i class="fas fa-reply mr-2"></i>{$tr['return']} {$tr['template management directory']}</button>
  </div>
</div>
HTML;
$extend_js .='
<style type="text/css">
.card-header-tabs {
    margin-right: -.625rem;
    margin-bottom: -.75rem;
    margin-left: -.625rem;
    border-bottom: 0;
}
.material-switch > input[type="checkbox"] {
      visibility:hidden;
}
.material-switch > label {
      cursor: pointer;
      height: 0px;
      position: relative;
}
.material-switch > label::before {
      background: rgb(0, 0, 0);
      box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
      border-radius: 8px;
      content: "";
      height: 16px;
      margin-top: -8px;
      margin-left: -18px;
      position:absolute;
      opacity: 0.3;
      transition: all 0.4s ease-in-out;
      width: 30px;
}
.material-switch > label::after {
      background: rgb(255, 255, 255);
      border-radius: 16px;
      box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
      content: "";
      height: 16px;
      left: -4px;
      margin-top: -8px;
      margin-left: -18px;
      position: absolute;
      top: 0px;
      transition: all 0.3s ease-in-out;
      width: 16px;
}
.material-switch > input[type="checkbox"]:checked + label::before {
      background: inherit;
      opacity: 0.5;
}
.material-switch > input[type="checkbox"]:checked + label::after {
      background: inherit;
      left: 20px;
}
</style>
';

$extend_head = $extend_head . <<<HTML
    <script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
    <script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
    <link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>

    <script type="text/javascript" language="javascript" class="init">
        $(document).ready(function () {
            $(".ad_form").validationEngine();
        });
    </script>
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