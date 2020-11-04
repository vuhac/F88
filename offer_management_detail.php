<?php
// ----------------------------------------------------------------------------
// Features:	優惠管理優惠詳細
// File Name:	offer_management_detail.php
// Author:    Neil
// Related:
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

require_once dirname(__FILE__) ."/lib_promotional_management.php";
// 搜尋開始時間、結束時間函式
require_once dirname(__FILE__) ."/deposit_withdrawal_company_audit_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND  in_array($_SESSION['agent']->account,$su['superuser'])) {
//   //如果是superuser，不做權限判斷
//   $disable_var = '';
// } else {
//   $admin_pchk = admin_power_chk('offer_management_detail', 'htm');
//   if($admin_pchk['option']['add_edit_offer']=='1' ){
//       $disable_var='';
//   }else{
//       $disable_var = 'disabled';
//   }
// }


// var_dump($disable_var);die();

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 擴充 head 內的 css or js
$extend_head = '';
// 放在結尾的 js
$extend_js = '';
$function_title = $tr['promotion Offer Editor'];
$page_title = '';
$indextitle_content = '<span class="glyphicon glyphicon-search" aria-hidden="true"></span>' . $tr['Search criteria'];
$indexbody_content = '';
$backBtn = '<a href="offer_management.php" type="button" class="btn btn-outline-secondary btn-sm back_btn"><i class="fas fa-reply mr-1"></i>'.$tr['return'].'</a>';
$paneltitle_content = '';
$paneltitle_content .= '<span class="glyphicon glyphicon-list" aria-hidden="true"></span>' . $tr['Query results'];
$paneltitle_content .= $backBtn;
$panelbody_content = '';


// 功能標題，放在標題列及meta $tr['promotion Offer Editor'] = '行銷優惠管理';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置 $tr['Home'] = '首頁'; $tr['profit and promotion'] = '營收與行銷';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['profit and promotion'].'</a></li>
  <li><a href="offer_management.php">'.$tr['promotion Offer Editor'].'</a></li>
  <li class="active">'.$tr['Preferential management details'].'</li>
</ol>';
// ----------------------------------------------------------------------------
// datepicker
$search_time = time_convert();
// var_dump($search_time);die();
if(!isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  header('Location:./home.php');
  die();
}
if (isset($_POST['status_filter']) and $_POST['status_filter'] != null) {
	switch ($_POST['status_filter']){
	case 'on':
		$get_status_filter = 'on';
    $status_filter_query = " AND classification_status = 1 ";
    $classification_query = " AND status = 1 ";
	  break;
	case 'off':
		$get_status_filter = 'off';
    $status_filter_query = " AND classification_status = 0 ";
    $classification_query = " AND status = 0 ";
	  break;

	default:
		$get_status_filter = 'on';
    $status_filter_query = " AND classification_status = 1 ";
    $classification_query = " AND status = 1 ";
	}
}else{
	$get_status_filter = 'on';
  $status_filter_query = " AND classification_status = 1 ";
  $classification_query = " AND status = 1 ";
}

$extend_head = <<<HTML
<!-- jquery datetimepicker js+css -->
<link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
HTML;

$extend_head .= "
<style>
.selectstyle input[type=radio], 
.selectstyle input[type=checkbox]
{
		display:none;
}

.selectstyle input[type=radio] + label{
    position: absolute;
    top:6px;
    left:5px;
}
.selectstyle input[type=radio] + label, .selectstyle input[type=checkbox] + label {    
    min-height: 30px;
    text-align: center;
    box-sizing: border-box;
    border:1px #6c757d solid;
    color: #6c757d;
    background-color: transparent;
    border-radius: 0.25rem;
    transition: all 0.2s;
    font-size: .5em;
    word-wrap: break-word;
    word-break: break-all;
    display: flex;
    align-items: center;
    justify-content: center;
}
.selectstyle input[type=radio] + label{
    width: 60px;
}

.selectstyle input[type=checkbox] + label {
    width: 100%;
}
.selectstyle input[type=radio] + label:hover, .selectstyle input[type=checkbox] + label:hover{
    border-color: #007bffa8;
    background-color: #007bffa8;
    color: #fff;
}
.selectstyle input[type=radio]:checked + label, .selectstyle input[type=checkbox]:checked + label{
    border-color: #007bff;
    background-color: #007bff;
    color: #fff;
}

// .selectstyle input[type=radio]:checked + label ~ div input[type=checkbox]:checked + label{
//   border-color: #6c757d;
//   background: none;
//   color: #6c757d;
// }

.dataTables_length{
  margin-top: 10px;
}
.material-switch > input[type=\"checkbox\"] {
    visibility:hidden;
}
.material-switch > label {
    cursor: pointer;
    height: 0px;
    position: relative;
    width: 0px;
}
.material-switch > label::before {
    background: rgb(0, 0, 0);
    box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
    border-radius: 8px;
    content: '';
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
    content: '';
    height: 16px;
    left: -4px;
    margin-top: -8px;
    margin-left: -18px;
    position: absolute;
    top: 0px;
    transition: all 0.3s ease-in-out;
    width: 16px;
}
.material-switch > input[type=\"checkbox\"]:checked + label::before {
    background: inherit;
    opacity: 0.5;
}
.material-switch > input[type=\"checkbox\"]:checked + label::after {
    background: inherit;
    left: 20px;
}
a.disabled {
   pointer-events: none;
   cursor: default;
}
.ck-button {
  margin:0px;
  overflow:auto;
  float:left;
  width: 33.33%;

  min-height: 44px;
}
.ck-button:hover {
    border-color: #007bffaa;
    background-color: #007bffaa;
    color: #fff;
}

.ck-button label {
    float:left;
    width: 100%;
    height: 100%;
    margin-bottom:0;
    background-color: transparent;
    transition: all 0.2s;

    position: relative;
}

.ck-button label span {
    text-align:center;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    position: absolute;
    width: 100%;
    height: 100%;    
    /* line-height: 38px; */

}

.ck-button label input {
    position:absolute;
    z-index: -5;
}

.ck-button input:checked + span {
    border-color: #007bff;
    background-color: #007bff;
    color: #fff;
}
.ck-button:nth-child(3n+2) label{
    border:1px solid #D0D0D0;
    border-top-style: none;
    border-bottom-style: none;
}
</style>
";

$show_list_html = '';

$status_html = '';
$search_list_name = '';
$tab_listedit = '';

$domain_id = $_GET['di'] ?? '';
$subdomain_id = $_GET['sdi'] ?? '';

$domain = get_desktop_mobile_domain($domain_id, $subdomain_id);
// var_dump($_SESSION);die();
if (!$domain['status']) {
  echo '<script>alert("'.$domain['result'].'");location.href="/offer_management.php";</script>';
}

$domain_id = $domain['result']['domain_id'];
$subdomain_id = $domain['result']['subdomain_id'];
$desktop_domain = $domain['result']['desktop'];
$mobile_domain = $domain['result']['mobile'];

$offer_data = get_promotion_bydomain($desktop_domain, $mobile_domain);

// 優惠管理內有該domain、優惠活動
// if ($offer_data['status'] AND ($offer_data['result'][1]->desktop_domain == $_SERVER['HTTP_HOST'] OR $offer_data['result'][1]->mobile_domain == $_SERVER['HTTP_HOST'])) {
if ($offer_data['status']) {

  global $disable_var;
  // $tabs = '';
  // $tab_contents = '';
  // $col_name = '';

  // 表格欄位名稱 $tr['Enabled'] = '啟用'; $tr['Sort'] = '排序';$tr['name'] = '名稱';$tr['Sales time'] = '上架時間';$tr['display'] = '顯示';$tr['function'] = '功能';
  $table_colname_html = <<<HTML
  <tr>
    <th class="info text-center">{$tr['Sort']}</th>
    <th class="info text-center">{$tr['name']}</th>
    <th class="info text-center">{$tr['classification of promotion']}</th>
    <th class="info text-center">{$tr['Starting time']}</th>
    <th class="info text-center">{$tr['End time']}</th>
    <th class="info text-center">{$tr['Enabled']}</th>
    <th class="info text-center">{$tr['display']}</th>
    <th class="info text-center">{$tr['function']}</th>
  </tr>
HTML;

  // -------------------------------------------------------------
  // modal
  // 啟用/不啟用tab
	$review_status = ['on'=>'啟用','off'=>'不啟用'];

	foreach ($review_status as $status_key => $status_value){
    $active_status_filter_btn = ($status_key == 'on') ? 'success':'default';
    $isactive = ($status_key == 'on') ? 'active': '';

    $status_html.=<<<HTML
      <button class="btn btn-{$active_status_filter_btn} btn-sm {$isactive}" href="#status_filter={$status_key}_tabs" aria-controls="nav-home" aria-selected="true" role="tab" id="status_{$status_key}">{$status_value}</a>
HTML;
  }

  // 啟用/不啟用的分類，預設撈啟用資料
  $promotions_sql = switch_tab($desktop_domain,$mobile_domain,$status_filter_query);
  $classification_sql = classification_sql($desktop_domain,$mobile_domain,$classification_query);
  $tab_listedit = switch_tab_html_o($promotions_sql,$classification_sql);

  // ---------------------------------------------------------------------------------------
  // 搜尋區分類
  // 分類只會顯示classification_status == 1(on)
  // 只要classification_status == 0 ，活動狀態=1也不會顯示
  $classification = get_promotion_classification_bydomain($desktop_domain, $mobile_domain);

  if($classification['status'] == true){
    foreach ($classification['result'] as $k=>$v) {
      // $class[$v->sort] = $v->classification;
      // 優惠名稱
      $name = $v->name;
      // 分類
      $category = $v->classification;
  
      // 搜尋框的分類項目
      $search_list_name .= '
      <div class="col-4 col-md-12 col-lg-6 col-xl-4 px-1">
        <input class="form-check-input" type="checkbox" name="classificationtype" id="classificationtype_'.$name.'" value="'.$category.'">
        <label class="form-check-label" for="classificationtype_'.$name.'">'.$category.'</label>
      </div>';
    }
  }else{
    $search_list_name .=<<<HTML
    <p class="w-100 text-center mb-0">暂无开启的分类</p>
HTML;
  }
  // ------------------------------------------------------------------------------------------------
  // $basedata = json_encode($class); 

  $show_list_html .= <<<HTML
    <div class="alert alert-success">
      <p class="mb-1">{$tr['desktop domain name']} {$desktop_domain}</p>
      <p class="mb-0">{$tr['mobile domain name']} {$mobile_domain}</p>
    </div>
HTML;

  $menu_list_html = <<<HTML
    <a class="btn btn-primary mr-2 mb-2 text-white ml-auto"  data-toggle="modal" data-target="#exampleModal">{$tr['Preferential classification management']}</a>
    <a class="btn btn-primary mr-1 mb-2 text-white" href="activity_management.php" role="button" >{$tr['prmotional code']}</a>
    <a class="btn btn-success {$disable_var} mr-2 mb-2" href="./promotional_editor.php?di={$domain_id}&sdi={$subdomain_id}" role="button"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span>&nbsp;{$tr['Add promotions']}</a>
HTML;

// $menu_list_html = <<<HTML
// <a class="btn btn-success {$disable_var}  mr-2 mb-2" href="./promotional_editor.php?di={$domain_id}&sdi={$subdomain_id}" role="button"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span>&nbsp;{$tr['Add promotions']}</a>
// <a class="btn btn-info mr-1  mr-2 mb-2" href="http://{$desktop_domain}/promotions.php" role="button" target="view_window"><span class="glyphicon glyphicon-eye-open" aria-hidden="true"></span>&nbsp;{$tr['offer preview']}</a>
// <a class="btn btn-primary mr-1  mb-2" href="./offer_management.php" role="button" >{$tr['back tp offer management']}</a>
// HTML;

  $show_list_html .= <<<HTML
    <div class="d-flex">
      {$menu_list_html}
    </div> 
HTML;

// $show_list_html .= <<<HTML
//   <nav>
//     <div class="nav nav-tabs" id="nav-tab" role="tablist">
//       {$tabs}
//       <div class="d-flex ml-auto">
//         {$menu_list_html}
//       </div>      
//     </div>
//   </nav>
// HTML;


  $show_list_html .= <<<HTML
  <div class="tab-pane fade show" role="tabpanel" aria-labelledby="nav-home-tab">
    <table id="show_list" class="display" cellspacing="0" width="100%">
      <thead>
        {$table_colname_html}
      </thead>
      <tbody>
      </tbody>
    </table>
  </div>
HTML;

// $show_list_html .= <<<HTML
// <div class="tab-content" id="nav-tabContent">
// <br>
// {$tab_contents}
// </div>
// HTML;

//   $extend_js = <<<HTML
//   <script>
//   $(document).ready(function() {
//     // datatable
//     var query = get_parameter();
//     $("#show_list").DataTable({
//       "bProcessing": true,
//       "bServerSide": true,
//       "bRetrieve": true,
//       "searching": false,
//       "aaSorting": [[ 0, "desc" ]],
//       "oLanguage": {
//         "sSearch": "{$tr['search'] }",//"搜索:",
//         "sEmptyTable": "{$tr['no data']}",//"目前没有资料!",
//         "sLengthMenu": "{$tr['each page']}_MENU_{$tr['Count']}",//"每页显示 _MENU_ 笔",
//         "sZeroRecords": "{$tr['no data']}",//"没有匹配结果",
//         "sInfo": "{$tr['Display']} _START_ {$tr['to']} _END_ {$tr['result']},{$tr['total']} _TOTAL_ {$tr['item']}",//"显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
//         "sInfoEmpty": "{$tr['no data']}",//"目前没有资料",
//         "sInfoFiltered": "({$tr['from']} _MAX_ {$tr['filtering in data']})"//"(由 _MAX_ 项结果过滤)"
//       },
//       "ajax":{
//         "url":"offer_management_action.php"+query
//       },
//       "columnDefs":[
//         { className: "dt-center","targets": [0,1,2,3,4,5,6,7]}
//       ],  
//       "columns":[
//         {"data":"id"},
//         {"data":"name"},
//         {"data":"classification"},
//         {"data":"effecttime"},
//         {"data":"endtime"},
//         {"data":"status"},
//         {"data":"show"},
//         {"data":"icon"}
//       ]
//     })
//   });

//   function get_parameter(){
//     var domain = '{$desktop_domain}';
//     var sub = '{$mobile_domain}';
//     var domain_id = '{$_GET['di']}';
//     var sub_id = '{$_GET['sdi']}';

//     var url = '?a=init_query&domain_name='+domain+'&domain_id='+domain_id+'&sub_name='+sub+'&sub_id='+sub_id;
//     return url;
//   }

//   function getnowtime(){
//     var NowDate = moment().tz('America/St_Thomas').format('YYYY-MM-DD')+' 23:59';
//     return NowDate;
//   }
//   // 本日、昨日、本周、上周、上個月button
//   function settimerange(sdate,edate,text){
//     $("#query_date_start_datepicker").val(sdate);
//     $("#query_date_end_datepicker").val(edate);

//     //更換顯示到選單外 20200525新增
//     // console.log(sdate);
//     // console.log(edate);
//     var currentonclick = $('.'+text+'').attr('onclick');
//     var currenttext = $('.'+text+'').text();

//     //first change
//     $('.application .first').removeClass('week month');
//     $('.application .first').attr('onclick',currentonclick);
//     $('.application .first').text(currenttext);   
//   }
  
//   //modal新增分類 存檔按鈕
//   function saveaddcategory(){
//     //all input val
//     var inputlength = $('.offer_input_list input').length;  
//     //檢查空input
//     const inputarrayval = [];
//     for (var i = 0; i < inputlength; i++) {
//       var inputid = $('.offer_input_list div').eq(i).attr('id');
      
//       //抓出是哪個input未填寫
//       if( $('#'+inputid+' input').val() == '' ){
//         $('#'+inputid+' input').addClass('alert alert-danger mb-0');
//       }else{
//         $('#'+inputid+' input').removeClass('alert alert-danger mb-0');
//         inputarrayval.push({
//           id: inputid,
//           val: $('#'+inputid+' input').val()
//         });
//       }              
//     }            
//     //抓出是否有input未寫
//     if ( inputarrayval.length < inputlength ) {
//       alert("您有尚未填写的栏位!");
//     }else{
//       alert("送出");
//     }
//   }

//   //modal新增分類 移除按鈕
//   function delinput(id) {
//     var inputval = $(id).find('input').val();
//     if ( inputval == '' ){
//         id.remove();
//     }else if ( inputval != '' ){
//       if (confirm("确定删除?")) {
//         id.remove();
//       }     
//     }
//   }

//   // modal新增分類 ，按儲存
//   function add_new_category(){
//     var add_cat_name = $("input[name='add_input_category']").map(function(){
//       return $(this).val();
//     }).get();
//     // console.log(add_cat_name);
//     var time = '{$search_time['current_date']}';
//     var domain_name = '{$desktop_domain}';
//     var sub_name = '{$mobile_domain}';

//     $.get("offer_management_action.php?a=add_category",{
//       add_cat_name : add_cat_name,
//       time: time,
//       domain_name:domain_name,
//       sub_name:sub_name
//     },
//     function(){
//       window.location.reload();
//     })
//   }
  
//   //modal新增分類 關閉前的存檔詢問
//   function promptsave(e){
//     if (confirm("是否先存档?")) {
//       //存檔
//       e.preventDefault();

//       // 新增分類存檔
//       add_new_category();
//     } else {
//       //不存檔就關閉編輯模式 
//       backedit();
//     }
//   }
 
//   //modal新增分類 關閉頁面回到編輯分類
//   function backedit(){
//     //不存檔就關閉編輯模式 
//     $('.edit_html').removeClass('d-none');      
//     $('.edit_btn').removeClass('d-none');
//     $('.add_html').addClass('d-none');
//     $('.add_btn').addClass('d-none');
//     $('.back_edit_btn').addClass('d-none');
//     //讓關閉按鈕也有專屬
//     $('#exampleModal').removeClass('add_close');
//     //移除 新增input 清除空格
//     $('.offer_input_list div').remove();
//     //加回原本的
//     var inputhtml = `
//       <div class="add_category_list" id="inputval_1">
//         <input type="text" class="form-control" placeholder="分類名稱" name="add_input_category">
//       </div>
//     `;
//     $('.offer_input_list').append(inputhtml);
      
//   }

//   $(document).ready(function(){

//     //分類按鈕效果
//     $('input[name="classificationtype"]').on('change',function(e){

//       var typelengthchecked = $('input[name="classificationtype"]:checked').length;
//       var typelength = $('input[name="classificationtype"]').length;
//       // console.log("選取數量 :"+typelengthchecked);
//       // console.log("總分類數量 :"+typelength);
//       //當選擇相同數量 = 分類  全選啟動
//       if ( typelengthchecked == typelength ){
//         //$('input[name="classificationall"]').prop('checked',true);
//         $('input[name="classificationall"]').click();
//         $('input[name="classificationtype"]').prop('checked',false);
//       }else if ( typelengthchecked == 0){
//         $('input[name="classificationall"]').prop('checked',true);
//       }else{
//         $('input[name="classificationall"]').prop('checked',false);
//       } 

//       //如果選項只有一個
//       if ( typelength == 1){
//         $('input[name="classificationtype"]').prop('checked',true);
//         $('input[name="classificationall"]').prop('checked',false);
//       } 
//     });

//     //全部選取
//     $('input[name=classificationall]').on('change',function(e){
//       $('input[name="classificationtype"]').prop('checked',false);
//     });

//     // datetimepicker
//     $("#query_date_start_datepicker").datetimepicker({
//         showButtonPanel: true,
//         changeMonth: true,
//         changeYear: true,
//         timepicker: true,
//         format: "Y-m-d H:i",
//         step:1
//     });
//     $("#query_date_end_datepicker").datetimepicker({
//         showButtonPanel: true,
//         changeMonth: true,
//         changeYear: true,
//         timepicker: true,
//         format: "Y-m-d H:i",
//         step:1
//     });

//     // ---------------------------------------------------
//     // 優惠分類管理
//     //modal 啟用樣式
//     $('.all_enabled button').click(function(){
//       if (confirm("是否先存档?") == true) {
//         //存檔
//         // window.location.reload();
//         $('.all_enabled button').addClass('btn-default');
//         $('.all_enabled button').removeClass('btn-success');
//         $(this).removeClass('btn-default');
//         $(this).addClass('btn-success');

//       } else {
//         $('.all_enabled button').addClass('btn-default');
//         $('.all_enabled button').removeClass('btn-success');
//         $(this).removeClass('btn-default');
//         $(this).addClass('btn-success');
//       }
//     });

   
//     //點新增分類先詢問是否存檔
//     $('.add_html_go').click(function(){    
//       if (confirm("是否先存档?")) {
//         //存檔
//         $('.edit_html').addClass('d-none');      
//         $('.edit_btn').addClass('d-none');
//         $('.add_html').removeClass('d-none');
//         $('.add_btn').removeClass('d-none');
//         $('.back_edit_btn').removeClass('d-none');

//       } else {
//         //不存檔就關閉編輯模式
//         $('.edit_html').addClass('d-none');      
//         $('.edit_btn').addClass('d-none');
//         $('.add_html').removeClass('d-none');
//         $('.add_btn').removeClass('d-none');
//         $('.back_edit_btn').removeClass('d-none');
//       }
//     });

//     $('#exampleModal').on('hide.bs.modal', function (e) {
//       promptsave(e);
//     })

//     //modal新增分類 回到編輯頁面
//     $('.back_edit_btn').click(function(e){    
//       promptsave(e);
//     });

//   //modal新增分類 添加新的欄位 只能有五個
//   $('.add_category_btn').click(function(){
//     var inputlimit = 5;
//     var inputlength = $('.offer_input_list input').length;
//     //console.log(inputlength);
//     if ( inputlength < inputlimit ) {

//       //建立array
//       const inputarray = [];
//       for (var i = 0; i < inputlimit; i++) {
//           inputarray.push({
//               id: 'inputval_'+ parseInt(i + 1),
//               status: ''
//           })
//       }
//       //console.log(inputarray);
//       //尋找與添加status
//       for (var i = 0; i < inputlength; i++) {
//           var inputid = $('.offer_input_list div').eq(i).attr('id');
//           var result = $.map(inputarray, function(item, index) {
//             return item.id;
//           }).indexOf(inputid);                    
//           inputarray[result].status = true;       
//           //console.log(inputid);   
//       } 
//       //找到目前不在場上status
//       var filteredNum = inputarray.filter(function(item, index) {
//         return item.status != true;
//       });
//       //console.log(filteredNum);
//       var delbtn = `<button type="button" class="btn btn-danger btn-sm offer_del_input" onclick="delinput(`+filteredNum[0].id+`)"><i class="fas fa-minus"></i></button>`;
//       var inputhtml = `<div class="position-relative" id="`+filteredNum[0].id+`"><input type="text" class="form-control mt-2" placeholder="{$tr['classification name']}" name="add_input_category">`+delbtn+`</div>`;

//       $('.offer_input_list').append(inputhtml);
//       //console.log(filteredNum);
//     }else{
//       alert("一次最多补充五项分类");
//     }
//   });

// });

//   // 分類名稱、狀態自動儲存
//   $(document).on('change',".row_category",function(){
//     var origin_cat_name = $(this).attr('id');
//     var new_cat_name = $('#categoryedit_'+origin_cat_name).find('input').val();

//     if(new_cat_name == ''){
//       var edit_cat_name = origin_cat_name;
//     }else{
//       var edit_cat_name = new_cat_name;
//     }
//     // console.log(origin_cat_name);
//     // console.log(edit_cat_name);

//     var switch_status = $('#categoryopen_'+origin_cat_name);
//     if($(switch_status).prop('checked')){
//       var cat_switch = 1; // open
//     } else{
//       var cat_switch = 0; // close
//     };
//     // console.log(cat_switch);

//     var domain_name = '{$desktop_domain}';
//     var sub_name = '{$mobile_domain}';

//     $.post("offer_management_action.php?a=edit_category",{
//         origin_cat_name:origin_cat_name,
//         edit_cat_name:edit_cat_name,
//         cat_switch:cat_switch,
//         domain_name: domain_name,
//         sub_name: sub_name
//       },
//       function(response){
//         // console.log(result);
//         var parse = JSON.parse(response);

//         if(parse.status == 'success'){
//           alert('分类:'+ origin_cat_name + '。编辑成功!');
//           window.location.reload();
//         }else{
//           alert(parse.msg);
//         }

//       }
//     )
//   });

//   // 新增分類
//   $("#add_cat").click(function(){
//     var add_cat_name = $("input[name='add_input_category']").map(function(){
//       return $(this).val();
//     }).get();
//     // console.log(add_cat_name);

//     var time = '{$search_time['current_date']}';
//     var domain_name = '{$desktop_domain}';
//     var sub_name = '{$mobile_domain}';

//     $.get("offer_management_action.php?a=add_category",{
//       add_cat_name : add_cat_name,
//       time: time,
//       domain_name:domain_name,
//       sub_name:sub_name
//     },
//     function(response){
//       var parse = JSON.parse(response);
//       if(parse.status == 'success'){
//           alert(parse.msg);
//           window.location.reload();
//         }else{
//           alert(parse.msg);
//         }

//     })
//   });

//   // 分類啟用tab
//   $("#status_on").click(function(){
//     var domain_name = '{$desktop_domain}';
//     var sub_name = '{$mobile_domain}';
//     var status_filter = 'on';

//     $.get("offer_management_action.php?a=switch_tab",{
//       domain_name:domain_name,
//       sub_name:sub_name,
//       status_filter:status_filter},
//       function(result){
//         $("#tab_listedit").html(result);
//       }
//     )
//   })

//   // 分類不啟用tab
//   $("#status_off").click(function(){
//     var domain_name = '{$desktop_domain}';
//     var sub_name = '{$mobile_domain}';
//     // var status_filter = '{$get_status_filter}';
//     var status_filter = 'off';

//     $.get("offer_management_action.php?a=switch_tab",{
//       domain_name:domain_name,
//       sub_name:sub_name,
//       status_filter:status_filter},
//       function(result){
//         $("#tab_listedit").html(result);
//         // console.log(result);
//       }
//     )
//   })
//   // ----------------------------------------

//   // datatable 優惠刪除
//   $(document).on('click','.delete_btn', function() {
//     // 使用 ajax 送出 post
//     var id = $(this).val();

//     if(id != '') {
//       if(confirm('{$tr['OK to delete']}?') == true) {
//         $.ajax ({
//           url: 'offer_management_action.php?a=delete',
//           type: 'POST',
//           data: ({
//             id: id
//           }),
//           success: function(response){
//             $('#preview_result').html(response);
//           },
//           error: function (error) {
//             $('#preview_result').html(error);
//           },
//         });
//       }
//     }else{
//       alert('{$tr['Illegal test']}');
//     }
//   });
//   // datatable 優惠啟用狀態
//   $(document).on('change','.checkbox_switch', function() {
//     // 使用 ajax 送出 post
//     var id = $(this).val();

//     if(id != '') {

//       if($('#offer_isopen'+id).prop('checked')) {
//         var is_open = 1;
//       }else{
//         var is_open = 0;
//       }

//       $.ajax ({
//         url: 'offer_management_action.php?a=edit_status',
//         type: 'POST',
//         data: ({
//           id: id,
//           is_open: is_open
//         }),
//         success: function(response){
//           $('#preview_result').html(response);
//         },
//         error: function (error) {
//           $('#preview_result').html(error);
//         },
//       });

//     }else{
//       alert('{$tr['Illegal test']}');
//     }
//   });
  
//   $(function () {
//     $('[data-toggle="popover"]').popover();

//     //modal新增分類  儲存按鈕
//     $('.add_btn').click(function(){
//       saveaddcategory();
//     });    
//   });
  
//   // 搜尋
//   $("#submit_to_inquiry").click(function(){
//     // 名稱
//     var name = $("#myInput").val(); 
//     // 開始上架時間
//     var s_date = $("#query_date_start_datepicker").val();
//     // 結束上架時間
//     var e_date = $("#query_date_end_datepicker").val();
//     // 狀態
//     if (
//        ($('input[name=status_sel]').length - $('input[name=status_sel]:checked').length) == 0 ||
//        ($('input[name=status_sel]').length - $('input[name=status_sel]:checked').length) == $('input[name=status_sel]').length )
//     {
//       var status_query  = "";
//     }else{
//       var status_query  = "";
//       $("input:checkbox:checked[name='status_sel']").each( function(){
//         status_query=status_query+"&status_query[]="+$(this).val();
//       });
//     }
//     var domain = '{$desktop_domain}';
//     var sub = '{$mobile_domain}';
//     var domain_id = '{$_GET['di']}';
//     var sub_id = '{$_GET['sdi']}';

//     // 分類(全選)
//     var all = $("#classificationall:checked").val();
//     // 單一分類
//     // var category = $("input[name='classificationtype']:checked").val();
//     var category  = "";
//     var category_name  = "";
//       $("input:checkbox:checked[name='classificationtype']").each( function(){
//         category=category+"&cat_query[]="+$(this).val();
//         // if (!category_name){
//         //   category_name=$(this).val();
//         //   }else{
//         //     category_name=category_name+' / '+$(this).val();
//         // }
//       });

//     // console.log(category)
//     var url = "&name="+name+"&s_date="+s_date+"&e_date="+e_date+"&cat_query="+category+"&cat_all="+all+"&domain_id="+domain_id+"&domain_name="+domain+"&sub_id="+sub_id+"&sub_name="+sub+"&status_query="+status_query;
//     // console.log(url);
 
//     $("#show_list").DataTable()
//       .ajax.url("offer_management_action.php?a=search_query"+url)
//       .load();
//   })
  
//   </script>
//    <style>
//     /* // 暫時放置 */
//     .del_input{
//       right: 0;
//       top: 1px;
//     }
//     .offer_del_input{
//       position: absolute;
//       top: 1px;
//       right: 0;
//       z-index: 5;
//     }
//     button.close:focus{
//       outline: none;
//     }
//   </style>
// HTML;
} else {
  $show_list_html = <<<HTML
  <a class="btn btn btn-primary" href="./promotional_editor.php?di={$domain_id}&sdi={$subdomain_id}" role="button" style="display:inline-block;float: right;"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span>&nbsp;{$tr['Add promotions']}</a>
  <a class="btn btn btn-primary text-white ml-auto" data-toggle="modal" data-target="#exampleModal">{$tr['edit']}{$tr['classification']}</a>
  <a class="btn btn-primary mr-1 text-white" href="./offer_management.php" role="button" style="display:inline-block;float: right;">{$tr['back tp offer management']}</a>
  <br><br>
  <div class="alert alert-danger" role="alert">
  {$tr['no data,please add']}
  </div>
HTML;
}


$extend_js = <<<HTML
<script>
$(document).ready(function() {
  // datatable
  var query = get_parameter();
  $("#show_list").DataTable({
    "bProcessing": true,
    "bServerSide": true,
    "bRetrieve": true,
    "searching": false,
    "aaSorting": [[ 0, "desc" ]],
    "oLanguage": {
      "sSearch": "{$tr['search'] }",//"搜索:",
      "sEmptyTable": "{$tr['no data']}",//"目前没有资料!",
      "sLengthMenu": "{$tr['each page']}_MENU_{$tr['Count']}",//"每页显示 _MENU_ 笔",
      "sZeroRecords": "{$tr['no data']}",//"没有匹配结果",
      "sInfo": "{$tr['Display']} _START_ {$tr['to']} _END_ {$tr['result']},{$tr['total']} _TOTAL_ {$tr['item']}",//"显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
      "sInfoEmpty": "{$tr['no data']}",//"目前没有资料",
      "sInfoFiltered": "({$tr['from']} _MAX_ {$tr['filtering in data']})"//"(由 _MAX_ 项结果过滤)"
    },
    "ajax":{
      "url":"offer_management_action.php"+query
    },
    "columnDefs":[
      { className: "dt-center","targets": [0,1,2,3,4,5,6,7]}
    ],  
    "columns":[
      {"data":"id"},
      {"data":"name"},
      {"data":"classification"},
      {"data":"effecttime"},
      {"data":"endtime"},
      {"data":"status"},
      {"data":"show"},
      {"data":"icon"}
    ]
  })
});

function get_parameter(){
  var domain = '{$desktop_domain}';
  var sub = '{$mobile_domain}';
  var domain_id = '{$_GET['di']}';
  var sub_id = '{$_GET['sdi']}';

  var url = '?a=init_query&domain_name='+domain+'&domain_id='+domain_id+'&sub_name='+sub+'&sub_id='+sub_id;
  return url;
}

function getnowtime(){
  var NowDate = moment().tz('America/St_Thomas').format('YYYY-MM-DD')+' 23:59';
  return NowDate;
}
// 本日、昨日、本周、上周、上個月button
function settimerange(sdate,edate,text){
  $("#query_date_start_datepicker").val(sdate);
  $("#query_date_end_datepicker").val(edate);

  //更換顯示到選單外 20200525新增
  // console.log(sdate);
  // console.log(edate);
  var currentonclick = $('.'+text+'').attr('onclick');
  var currenttext = $('.'+text+'').text();

  //first change
  $('.application .first').removeClass('week month');
  $('.application .first').attr('onclick',currentonclick);
  $('.application .first').text(currenttext);   
}

//modal新增分類 存檔按鈕
function saveaddcategory(){
  //all input val
  var inputlength = $('.offer_input_list input').length;  
  //檢查空input
  const inputarrayval = [];
  for (var i = 0; i < inputlength; i++) {
    var inputid = $('.offer_input_list div').eq(i).attr('id');
    
    //抓出是哪個input未填寫
    if( $('#'+inputid+' input').val() == '' ){
      $('#'+inputid+' input').addClass('alert alert-danger mb-0');
    }else{
      $('#'+inputid+' input').removeClass('alert alert-danger mb-0');
      inputarrayval.push({
        id: inputid,
        val: $('#'+inputid+' input').val()
      });
    }              
  }            
  //抓出是否有input未寫
  if ( inputarrayval.length < inputlength ) {
    alert("您有尚未填写的栏位!");
  }else{
    alert("送出");
  }
}

//modal新增分類 移除按鈕
function delinput(id) {
  var inputval = $(id).find('input').val();
  if ( inputval == '' ){
      id.remove();
  }else if ( inputval != '' ){
    if (confirm("确定删除?")) {
      id.remove();
    }     
  }
}

// modal新增分類 ，按儲存
function add_new_category(){
  var add_cat_name = $("input[name='add_input_category']").map(function(){
    return $(this).val();
  }).get();
  // console.log(add_cat_name);
  var time = '{$search_time['currenttime']}';
  var domain_name = '{$desktop_domain}';
  var sub_name = '{$mobile_domain}';

  $.get("offer_management_action.php?a=add_category",{
    add_cat_name : add_cat_name,
    time: time,
    domain_name:domain_name,
    sub_name:sub_name
  },
  function(){
    window.location.reload();
  })
}

//modal新增分類 關閉前的存檔詢問
function promptsave(e){
  if (confirm("是否先存档?")) {
    //存檔
    e.preventDefault();

    // 新增分類存檔
    add_new_category();
  } else {
    //不存檔就關閉編輯模式 
    backedit();
  }
}

//modal新增分類 關閉頁面回到編輯分類
function backedit(){
  //不存檔就關閉編輯模式 
  $('.edit_html').removeClass('d-none');      
  $('.edit_btn').removeClass('d-none');
  $('.add_html').addClass('d-none');
  $('.add_btn').addClass('d-none');
  $('.back_edit_btn').addClass('d-none');
  //讓關閉按鈕也有專屬
  $('#exampleModal').removeClass('add_close');
  //移除 新增input 清除空格
  $('.offer_input_list div').remove();
  //加回原本的
  var inputhtml = `
    <div class="add_category_list" id="inputval_1">
      <input type="text" class="form-control" placeholder="分類名稱" name="add_input_category">
    </div>
  `;
  $('.offer_input_list').append(inputhtml);
    
}

$(document).ready(function(){

  //分類按鈕效果
  $('input[name="classificationtype"]').on('change',function(e){

    var typelengthchecked = $('input[name="classificationtype"]:checked').length;
    var typelength = $('input[name="classificationtype"]').length;
    // console.log("選取數量 :"+typelengthchecked);
    // console.log("總分類數量 :"+typelength);
    //當選擇相同數量 = 分類  全選啟動
    if ( typelengthchecked == typelength ){
      //$('input[name="classificationall"]').prop('checked',true);
      $('input[name="classificationall"]').click();
      $('input[name="classificationtype"]').prop('checked',false);
    }else if ( typelengthchecked == 0){
      $('input[name="classificationall"]').prop('checked',true);
    }else{
      $('input[name="classificationall"]').prop('checked',false);
    } 

    //如果選項只有一個
    if ( typelength == 1){
      $('input[name="classificationtype"]').prop('checked',true);
      $('input[name="classificationall"]').prop('checked',false);
    } 
  });

  //全部選取
  $('input[name=classificationall]').on('change',function(e){
    $('input[name="classificationtype"]').prop('checked',false);
  });

  // datetimepicker
  $("#query_date_start_datepicker").datetimepicker({
      showButtonPanel: true,
      changeMonth: true,
      changeYear: true,
      timepicker: true,
      format: "Y-m-d H:i",
      step:1
  });
  $("#query_date_end_datepicker").datetimepicker({
      showButtonPanel: true,
      changeMonth: true,
      changeYear: true,
      timepicker: true,
      format: "Y-m-d H:i",
      step:1
  });

  // ---------------------------------------------------
  // 優惠分類管理
  //modal 啟用樣式
  $('.all_enabled button').click(function(){
    if (confirm("是否先存档?") == true) {
      //存檔
      // window.location.reload();
      $('.all_enabled button').addClass('btn-default');
      $('.all_enabled button').removeClass('btn-success');
      $(this).removeClass('btn-default');
      $(this).addClass('btn-success');

    } else {
      $('.all_enabled button').addClass('btn-default');
      $('.all_enabled button').removeClass('btn-success');
      $(this).removeClass('btn-default');
      $(this).addClass('btn-success');
    }
  });

 
  //點新增分類先詢問是否存檔
  $('.add_html_go').click(function(){    
    if (confirm("是否先存档?")) {
      //存檔
      $('.edit_html').addClass('d-none');      
      $('.edit_btn').addClass('d-none');
      $('.add_html').removeClass('d-none');
      $('.add_btn').removeClass('d-none');
      $('.back_edit_btn').removeClass('d-none');

    } else {
      //不存檔就關閉編輯模式
      $('.edit_html').addClass('d-none');      
      $('.edit_btn').addClass('d-none');
      $('.add_html').removeClass('d-none');
      $('.add_btn').removeClass('d-none');
      $('.back_edit_btn').removeClass('d-none');
    }
  });

  $('#exampleModal').on('hide.bs.modal', function (e) {
    promptsave(e);
  })

  //modal新增分類 回到編輯頁面
  $('.back_edit_btn').click(function(e){    
    promptsave(e);
  });

//modal新增分類 添加新的欄位 只能有五個
$('.add_category_btn').click(function(){
  var inputlimit = 5;
  var inputlength = $('.offer_input_list input').length;
  //console.log(inputlength);
  if ( inputlength < inputlimit ) {

    //建立array
    const inputarray = [];
    for (var i = 0; i < inputlimit; i++) {
        inputarray.push({
            id: 'inputval_'+ parseInt(i + 1),
            status: ''
        })
    }
    //console.log(inputarray);
    //尋找與添加status
    for (var i = 0; i < inputlength; i++) {
        var inputid = $('.offer_input_list div').eq(i).attr('id');
        var result = $.map(inputarray, function(item, index) {
          return item.id;
        }).indexOf(inputid);                    
        inputarray[result].status = true;       
        //console.log(inputid);   
    } 
    //找到目前不在場上status
    var filteredNum = inputarray.filter(function(item, index) {
      return item.status != true;
    });
    //console.log(filteredNum);
    var delbtn = `<button type="button" class="btn btn-danger btn-sm offer_del_input" onclick="delinput(`+filteredNum[0].id+`)"><i class="fas fa-minus"></i></button>`;
    var inputhtml = `<div class="position-relative" id="`+filteredNum[0].id+`"><input type="text" class="form-control mt-2" placeholder="{$tr['classification name']}" name="add_input_category">`+delbtn+`</div>`;

    $('.offer_input_list').append(inputhtml);
    //console.log(filteredNum);
  }else{
    alert("一次最多补充五项分类");
  }
});

});

// 分類名稱、狀態自動儲存
$(document).on('change',".row_category",function(){
  var origin_cat_name = $(this).attr('id');
  var new_cat_name = $('#categoryedit_'+origin_cat_name).find('input').val();

  if(new_cat_name == ''){
    var edit_cat_name = origin_cat_name;
  }else{
    var edit_cat_name = new_cat_name;
  }
  // console.log(origin_cat_name);
  // console.log(edit_cat_name);

  var switch_status = $('#categoryopen_'+origin_cat_name);
  if($(switch_status).prop('checked')){
    var cat_switch = 1; // open
  } else{
    var cat_switch = 0; // close
  };
  // console.log(cat_switch);

  var domain_name = '{$desktop_domain}';
  var sub_name = '{$mobile_domain}';

  $.post("offer_management_action.php?a=edit_category",{
      origin_cat_name:origin_cat_name,
      edit_cat_name:edit_cat_name,
      cat_switch:cat_switch,
      domain_name: domain_name,
      sub_name: sub_name
    },
    function(response){
      // console.log(result);
      var parse = JSON.parse(response);

      if(parse.status == 'success'){
        alert('分类:'+ origin_cat_name + '。编辑成功!');
        window.location.reload();
      }else{
        alert(parse.msg);
      }

    }
  )
});

// 新增分類
$("#add_cat").click(function(){
  var add_cat_name = $("input[name='add_input_category']").map(function(){
    return $(this).val();
  }).get();
  // console.log(add_cat_name);

  var time = '{$search_time['currenttime']}';
  var domain_name = '{$desktop_domain}';
  var sub_name = '{$mobile_domain}';

  $.get("offer_management_action.php?a=add_category",{
    add_cat_name : add_cat_name,
    time: time,
    domain_name:domain_name,
    sub_name:sub_name
  },
  function(response){
    var parse = JSON.parse(response);
    if(parse.status == 'success'){
        alert(parse.msg);
        window.location.reload();
      }else{
        alert(parse.msg);
      }

  })
});

// 分類啟用tab
$("#status_on").click(function(){
  var domain_name = '{$desktop_domain}';
  var sub_name = '{$mobile_domain}';
  var status_filter = 'on';

  $.get("offer_management_action.php?a=switch_tab",{
    domain_name:domain_name,
    sub_name:sub_name,
    status_filter:status_filter},
    function(result){
      $("#tab_listedit").html(result);
    }
  )
})

// 分類不啟用tab
$("#status_off").click(function(){
  var domain_name = '{$desktop_domain}';
  var sub_name = '{$mobile_domain}';
  // var status_filter = '{$get_status_filter}';
  var status_filter = 'off';

  $.get("offer_management_action.php?a=switch_tab",{
    domain_name:domain_name,
    sub_name:sub_name,
    status_filter:status_filter},
    function(result){
      $("#tab_listedit").html(result);
      // console.log(result);
    }
  )
})
// ----------------------------------------

// datatable 優惠刪除
$(document).on('click','.delete_btn', function() {
  // 使用 ajax 送出 post
  var id = $(this).val();

  if(id != '') {
    if(confirm('{$tr['OK to delete']}?') == true) {
      $.ajax ({
        url: 'offer_management_action.php?a=delete',
        type: 'POST',
        data: ({
          id: id
        }),
        success: function(response){
          $('#preview_result').html(response);
        },
        error: function (error) {
          $('#preview_result').html(error);
        },
      });
    }
  }else{
    alert('{$tr['Illegal test']}');
  }
});
// datatable 優惠啟用狀態
$(document).on('change','.checkbox_switch', function() {
  // 使用 ajax 送出 post
  var id = $(this).val();

  if(id != '') {

    if($('#offer_isopen'+id).prop('checked')) {
      var is_open = 1;
    }else{
      var is_open = 0;
    }

    $.ajax ({
      url: 'offer_management_action.php?a=edit_status',
      type: 'POST',
      data: ({
        id: id,
        is_open: is_open
      }),
      success: function(response){
        $('#preview_result').html(response);
      },
      error: function (error) {
        $('#preview_result').html(error);
      },
    });

  }else{
    alert('{$tr['Illegal test']}');
  }
});

$(function () {
  $('[data-toggle="popover"]').popover();

  //modal新增分類  儲存按鈕
  $('.add_btn').click(function(){
    saveaddcategory();
  });    
});

// 搜尋
$("#submit_to_inquiry").click(function(){
  // 名稱
  var name = $("#myInput").val(); 
  // 開始上架時間
  var s_date = $("#query_date_start_datepicker").val();
  // 結束上架時間
  var e_date = $("#query_date_end_datepicker").val();
  // 狀態
  if (
     ($('input[name=status_sel]').length - $('input[name=status_sel]:checked').length) == 0 ||
     ($('input[name=status_sel]').length - $('input[name=status_sel]:checked').length) == $('input[name=status_sel]').length )
  {
    var status_query  = "";
  }else{
    var status_query  = "";
    $("input:checkbox:checked[name='status_sel']").each( function(){
      status_query=status_query+"&status_query[]="+$(this).val();
    });
  }
  var domain = '{$desktop_domain}';
  var sub = '{$mobile_domain}';
  var domain_id = '{$_GET['di']}';
  var sub_id = '{$_GET['sdi']}';

  // 分類(全選)
  var all = $("#classificationall:checked").val();
  // 單一分類
  // var category = $("input[name='classificationtype']:checked").val();
  var category  = "";
  var category_name  = "";
    $("input:checkbox:checked[name='classificationtype']").each( function(){
      category=category+"&cat_query[]="+$(this).val();
      // if (!category_name){
      //   category_name=$(this).val();
      //   }else{
      //     category_name=category_name+' / '+$(this).val();
      // }
    });

  // console.log(category)
  var url = "&name="+name+"&s_date="+s_date+"&e_date="+e_date+"&cat_query="+category+"&cat_all="+all+"&domain_id="+domain_id+"&domain_name="+domain+"&sub_id="+sub_id+"&sub_name="+sub+"&status_query="+status_query;
  // console.log(url);

  $("#show_list").DataTable()
    .ajax.url("offer_management_action.php?a=search_query"+url)
    .load();
})

</script>
 <style>
  /* // 暫時放置 */
  .del_input{
    right: 0;
    top: 1px;
  }
  .offer_del_input{
    position: absolute;
    top: 1px;
    right: 0;
    z-index: 5;
  }
  button.close:focus{
    outline: none;
  }
</style>
HTML;

// 查詢條件 - 上架時間
$time_content = <<<HTML
<div class="row">
    <div class="col-12 d-flex">
        <label>{$tr['Preferential effective date range']}</label>
        <div class="btn-group btn-group-sm ml-auto application" role="group" aria-label="Button group with nested dropdown">
            <button type="button" class="btn btn-secondary first">{$tr['grade default']}</button>
            <div class="btn-group btn-group-sm" role="group">
              <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              </button>
              <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
                <a class="dropdown-item week" onclick="settimerange('{$search_time['thisweekday']} 00:00', getnowtime() ,'week')">{$tr['This week']}</a>
                <a class="dropdown-item month" onclick="settimerange('{$search_time['thismonth']}-01 00:00',getnowtime(),'month')">{$tr['this month']}</a>
                <a class="dropdown-item today" onclick="settimerange('{$search_time['current']} 00:00', getnowtime(), 'today')">{$tr['Today']}</a>
                <a class="dropdown-item yesterday" onclick="settimerange('{$search_time['yesterday']} 00:00', '{$search_time['yesterday']} 23:59','yesterday' );">{$tr['yesterday']}</a>
                <a class="dropdown-item lastmonth" onclick="settimerange('{$search_time['lastmonth']}-01 00:00','{$search_time['lastmonth_e']} 23:59','lastmonth');">{$tr['last month']}</a>
              </div>
            </div>
          </div>
    </div>
    <div class="col-12 form-group rwd_doublerow_time">
        <div class="input-group">
          <div class=" input-group">
            <div class="input-group-prepend">
              <span class="input-group-text">起始</span>
            </div>
            <input type="text" class="form-control" name="sdate" id="query_date_start_datepicker" placeholder="ex:2017-01-20" value="{$search_time['default_min_date']}{$search_time['min']}">
          </div>

          <div class=" input-group">
          <div class="input-group-prepend">
            <span class="input-group-text">结束</span>
          </div>
            <input type="text" class="form-control" name="edate" id="query_date_end_datepicker" placeholder="ex:2017-01-20" value="{$search_time['current']}{$search_time['max']}">
          </div>              
        </div>
    </div>
</div>
HTML;

$indexbody_content = <<<HTML
<div class="row">
  <div class="col-12"><label>{$tr['name']}</label></div>
  <div class="col-12 form-group">
    <input type="text" class="form-control" id="myInput" placeholder="{$tr['name']}" value="">
  </div>
</div>
{$time_content}
<div class="row">
  <div class="col-12 form-group">
    <div class="card search_option_card">
      <div class="card-header">
        <p class="text-center font-weight-bold mb-0">{$tr['classification']}</p>
      </div>
      <div class="card-body">
        <div class="row selectstyle">
          <input type="radio" id="classificationall" name="classificationall" value="all" checked="">
          <label for="classificationall" class="d-flex justify-content-center align-items-center">{$tr['all']}</label>
          {$search_list_name}
        </div>        
      </div>
    </div>    
  </div>
</div>
<div clss="row">
  <div class="col-12">
    <div class="row border card-header">
      <h6 class="betlog_h6 mx-auto font-weight-bold mb-0">{$tr['State']}</h6>
    </div>
  </div>
  <div class="col-12">
    <div class="row border">
        <div class="ck-button">
    <label>
      <input type="checkbox" id="status_sel_2" name="status_sel" value="2">
      <span class="status_sel_2">{$tr['expired']}</span>
    </label>
  </div>    <div class="ck-button">
    <label>
      <input type="checkbox" id="status_sel_1" name="status_sel" value="1" checked="">
      <span class="status_sel_1">{$tr['Enabled']}</span>
    </label>
  </div>    <div class="ck-button">
    <label>
      <input type="checkbox" id="status_sel_0" name="status_sel" value="0">
      <span class="status_sel_0">不启用</span>
    </label>
  </div>
    </div>
  </div>
</div>
<hr>
<div class="row">
  <div class="col-12">
      <button id="submit_to_inquiry" class="btn btn-success btn-block" type="submit">{$tr['Inquiry']}</button>
  </div>
</div>
HTML;

//編輯分類HTML
$edit_html = <<<HTML
<div class="row d-flex align-items-center mb-3">
  <div class="col-12 d-flex">
    <p class="mb-0">目前显示：</p>
    <div class="btn-group all_enabled" role="group" align="right">
      {$status_html}
      <!-- <button class="btn btn-success btn-sm" role="button">{$tr['Enabled']}</a>
      <button class="btn btn-default btn-sm" role="button">不启用</a> -->
    </div>
    <button type="button" class="btn bg-transparent rounded-circle ml-2 px-2"  data-container="body" data-toggle="popover" data-placement="top" data-content="切换显示启用与不启用类别">
      <span class="glyphicon glyphicon-info-sign"></span>
    </button>
    <button class="btn btn-success btn-sm ml-auto add_html_go"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span>{$tr['add']}{$tr['classification']}</button>

  </div>
</div>

<div class="row mb-3">
    <div class="col-4">{$tr['name']}</div>
    <div class="col-5">修改名稱</div>
    <div class="col-3">{$tr['y']}/不啟用</div>
</div>

<div id="tab_listedit">{$tab_listedit}</div>

HTML;

//新增分類HTML
$add_html = <<<HTML
<div class="form-group">
  <div class="d-flex align-items-center mb-2">
      <label>
        {$tr['add classification']}        
      </label>
      <button type="button" class="btn btn-success add_category_btn btn-sm ml-auto">
        <span class="glyphicon glyphicon-plus" aria-hidden="true"></span>
      </button>
  </div>
</div>
<form class="offer_input_list">
    <div class="add_category_list" id="inputval_1">
      <input type="text" class="form-control" placeholder="{$tr['classification name']}" name="add_input_category">
    </div>
</form>
HTML;

// 切成 1 欄版面
$panelbody_content = <<<HTML
<div class="row">
  <div class="col-12">
    {$show_list_html}
  </div>
</div>
<!-- Modal -->
<div class="modal fade bd-example-modal-lg" id="exampleModal" tabindex="-1" role="dialog" data-backdrop="static" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">{$tr['Preferential classification management']}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="edit_html">
          {$edit_html}
        </div>
        <div class="add_html d-none">
          {$add_html}
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary back_edit_btn d-none"><i class="fas fa-reply mr-1"></i> {$tr['return']}{$tr['edit']}</button>
        <button type="button" class="btn btn-success add_btn d-none" data-title="{$tr['add classification']}" id="add_cat">{$tr['Save']}</button>
        <button type="button" class="btn btn-secondary close_btn" data-dismiss="modal">{$tr['off']}</button>
      </div>
    </div>
  </div>
</div>
<br>
<div class="row">
  <div id="preview_result"></div>
</div>
HTML;


// -----------------------------------------------------------------------------------------------------------------------------------------------
//  html 組合 end
// -----------------------------------------------------------------------------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] 		= $tr['host_descript'];
$tmpl['html_meta_author']	 				= $tr['host_author'];
$tmpl['html_meta_title'] 					= $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']								= $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']							= $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']								= $extend_js;
// 兩欄分割--左邊
$tmpl['indextitle_content'] = $indextitle_content;
$tmpl['indexbody_content'] = $indexbody_content;
// 兩欄分割--右邊
$tmpl['paneltitle_content'] = $paneltitle_content;
$tmpl['panelbody_content'] = $panelbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
//include("template/beadmin.tmpl.php");
include "template/s2col.tmpl.php";
?>