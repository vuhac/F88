<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 活動優惠管理
// File Name:	activity_management.php
// Author:    Mavis
// Related:
// DB Table: root_promotion_activity
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


// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title 		= $tr['prmotional code'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs =<<<HTML
<ol class="breadcrumb">
  <li><a href="home.php">{$tr['Home']}</a></li>
  <li><a href="#">{$tr['profit and promotion']}</a></li>
  <li class="active">{$function_title}</li>
</ol>
HTML;
// ----------------------------------------------------------------------------

if($_SESSION['agent']->therole == 'R') {
    $extend_head=<<<HTML
		<!-- Jquery UI js+css  -->
        <script src="in/jquery-ui.js"></script>
        <link rel="stylesheet"  href="in/jquery-ui.css" >
        <!-- Jquery blockUI js  -->
        <script src="./in/jquery.blockUI.js"></script>
        <!-- Datatables js+css  -->
        <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
        <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
        <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
        <script type="text/javascript" language="javascript" class="init">
            $(document).ready(function() {
                $("#systeminfo").DataTable( {
                    "paging":   true,
                    "ordering": true,
                    "info":     true,
                    "searching": false,
                    "order": [[ 0, "desc" ]],
                    "pageLength": 30
                } );
            } )
        </script>
HTML;
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 || getenv('HTTP_X_FORWARDED_PROTO') === 'https') ? "https://" : "http://";

  // sql依據使用者所在時區顯示time
  if (isset($_SESSION['agent'] ->timezone) && $_SESSION['agent'] ->timezone != NULL) {
    $tz = $_SESSION['agent'] ->timezone;
  } else {
    $tz = '+08';
  }
  // 轉換時區所要用的 sql timezone 參數
  $tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."' ";
  $tzone = runSQLALL($tzsql);

  if ($tzone[0] == 1) {
    $tzonename = $tzone[1] ->name;
  } else {
    $tzonename = 'posix/Etc/GMT-8';
  }

  // 取得現在時間
  date_default_timezone_set($tzonename);
  $now = date('Y-m-d H:i:s');

  // 取得資料
    $select_sql =<<<SQL
    SELECT 
      *,
      to_char((effecttime AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as effecttime,
      to_char((endtime AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as endtime
      
     FROM root_promotion_activity
     WHERE activity_status != 2
SQL;
     $result_sql = runSQLall($select_sql);
   
  // 欄位名稱
  $table_colname=<<<HTML
    <tr>
      <th class="info text-center">{$tr['ID']}</th>
      <th class="info text-center">{$tr['activity name']}</th>
      <th class="info text-center">{$tr['domain']}</th>
      <th class="info text-center">{$tr['Starting time']}</th>
      <th class="info text-center">{$tr['End time']}</th>
      <th class="info text-center">{$tr['State']}</th>
      <th class="info text-center">{$tr['detail']}</th>
      <th class="info text-center">{$tr['operation']}</th>
    </tr>
HTML;

    $show_data_list_html ='';
    $show_detail = '';
    $link_detail = '';
    $note_html = '';

    // 前台網址
    $front_websitepath = '/promotion_activity.php?a=';

    if($result_sql[0]>=1 ){
      for($i=1;$i<=$result_sql[0];$i++){
        $id = $result_sql[$i]->id;
        $activity_id = $result_sql[$i]->activity_id;
        $activity_name = $result_sql[$i]->activity_name; // 活動名稱
        $act_status = $result_sql[$i]->activity_status; // 活動開關
        $desc = $result_sql[$i]->activity_desc; //活動說明
        $effecttime = $result_sql[$i]->effecttime; // 開始時間
        $endtime = $result_sql[$i]->endtime; // 結束時間
        $domain = $result_sql[$i]->activity_domain; // 網域
        $subdomain = $result_sql[$i]->activity_subdomain; // 子網域

        $find_sub = str_replace('/',' ',$subdomain); // 移除 '/'
        $desktop_sub = explode(" ",$find_sub);

        //$get_sub_item = strrchr($subdomain,"/"); // 從後面搜尋 '/'
        $mobile_sub = substr(strrchr($subdomain,"/"),1); // 取得去掉/後的第一個字母

        $promo_number = $result_sql[$i]->bouns_number; // 優惠碼數量
        $money = $result_sql[$i]->bouns_amount; // 金額

        $isopen_switch = '';
        // 狀態
        if($act_status == '1') {
          $isopen_switch = 'checked';
        } elseif($act_status == '0') {
          $isopen_switch = '';
        }

        // 優惠管理連結
        $note_html =<<<HTML
        <div class="alert alert-success" role="alert">
        * {$tr['paste activity link']} <a href="offer_management.php">{$tr['promotion Offer Editor']}</a>  {$tr['the offer and paste']}
        </div>
HTML;
        // 活動連結
        $link_detail =<<<HTML
        <button type="button" class="btn btn-xs pull-right modal-btn" data-toggle="modal" data-target="#{$id}">{$tr['detail']}</button>
          <div class="modal fade" id="{$id}" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="true">
            <div class="modal-dialog" role="document">
              <div class="modal-content">
                <div class="modal-header">
                  <h2 class="modal-title" id="myModalLabel">{$tr['description']}</h2>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>              
                </div>

                <div class="modal-body">
                  <table class="table table-striped">
                    <tbody class="text-left">
                      <tr>
                        <th scope="row">{$tr['activity name']}</th>
                        <td style="border:transparent;">{$activity_name}</td>
                      </tr>
                      <tr>
                        <th scope="row">{$tr['description']}</th>
                        <td style="border:transparent;">{$desc}</td>
                      </tr>
                       <tr>
                        <th scope="row">{$tr['number of promotion code']}</th>
                        <td style="border:transparent;">{$promo_number}</td>
                      </tr>
                       <tr>
                        <th scope="row">{$tr['each promotion amount']}</th>
                        <td style="border:transparent;">\${$money}</td>
                      </tr>
                        <th scope="row">{$tr['desktop link']}</th>
                        <td style="border:transparent;">{$protocol}{$desktop_sub[0]}.{$domain}{$front_websitepath}{$activity_id}</td>
                      </tr> 
                      <tr>
                        <th scope="row">{$tr['mobile link']}</th>
                        <td style="border:transparent;">{$protocol}{$mobile_sub}.{$domain}{$front_websitepath}{$activity_id}</td>
                      </tr>
                      
                    </tbody>
                  </table>
                </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{$tr['off']}</button>
              </div>
            </div>
          </div>
        </div>
HTML;

        $show_data_list_html .=<<<HTML
        <tr>
          <td class="text-center">{$id}</td>
          <td class="text-center w-25">{$activity_name}</td>
          <td class="text-center">{$domain}</td>
          <td class="text-center">{$effecttime}</td>
          <td class="text-center">{$endtime}</td>
          <td class="text-center">
            <div class="col-12 material-switch pull-left">
              <input id="activity_status_open{$id}" name="activity_status_open{$id}" class="checkbox_switch" value="{$id}" type="checkbox" {$isopen_switch}/>
              <label for="activity_status_open{$id}" class="label-success"></label>
            </div>
          </td>
          <td class="text-center">{$link_detail}</td>
          <td class="text-center">
            <button type="button" class="btn btn-danger btn-sm delete_btn" value="{$id}" title="{$tr['delete']}"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></button>

            <a class="btn btn-primary btn-sm" href="activity_management_editor.php?a={$id}" role="button" title="{$tr['edit']}"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>

            <a class="btn btn-primary btn-sm" href="activity_detail.php?s={$id}" role="button" title="{$tr['go to']} {$activity_name} {$tr['detail of promo code']}"><span class="glyphicon glyphicon-file" aria-hidden="true"></span></a>
          </td>
        </tr>
HTML;
      }
    }

  // 列出資料
  $show_list_html =<<<HTML
  {$note_html}
  <table id="systeminfo"  class="display" cellspacing="0" width="100%">
    <thead>
      <a class="btn btn-success" id="add_activity" href="./activity_management_editor.php" role="button" style="float: right;"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span>&nbsp; {$tr['add activity']} </a>
      <br>
      <br>
      {$table_colname}
    </thead>
    <tfoot>
      {$table_colname}
    </tfoot>
    <tbody>
      {$show_data_list_html}
    </tbody>
  </table>
HTML;

 $extend_js =<<<HTML
  <script>
  
  $(document).ready(function(){
     // 刪除
    $('#systeminfo').on('click', '.delete_btn', function() {
      // 使用 ajax 送出 post
      var id = $(this).val();
      //console.log(id);

      if(id != '') {
        if(confirm('{$tr['OK to delete']}?') == true) {
          $.ajax ({
            url: 'activity_management_editor_action.php?a=delete',
            type: 'POST',
            data: ({
              id: id
            }),
            success: function(response){
              //$('#preview_result').html(response);
              window.location.href="activity_management.php";
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
    
   // 修改狀態
    $('#systeminfo').on('click', '.checkbox_switch', function() {
      // 使用 ajax 送出 post
      var id = $(this).val();
      //console.log(id);

      if(id != '') {

        if($('#activity_status_open'+id).prop('checked')) {
          var activity_status_open = 1;
        }else{
          var activity_status_open = 0;
        }

        $.ajax ({
          url: 'activity_management_editor_action.php?a=edit_status',
          type: 'POST',
          data: ({
            id: id,
            activity_status_open: activity_status_open
          }),
          success: function(response){
            //$('#preview_result').html(response);
            window.location.href="activity_management.php";
          },
          error: function (error) {
            $('#preview_result').html(error);
          },
          
        });
      }else{
        alert('{$tr['Illegal test']}');
      }
    });

  });
// 按下 enter 後,等於 click 登入按鍵
$(function() {
    $(document).keydown(function(e) {
      if(!$('.modal').hasClass('show')){
        switch(e.which) {
            case 13: // enter key
                $("#submit_to_inquiry").trigger("click");
            break;
        }
      }
    });
})
  </script>
HTML;
}else{
  $show_list_html = "(x) {$tr['Wrong operation']}";
}

// 切成 1 欄版面
$indexbody_content =<<<HTML
<div class="row">
  <div class="col-12 col-md-12">
  {$show_list_html}
  </div>
</div>
<br>
<div class="row">
  <div id="preview_result"></div>
</div>
HTML;

// 將 checkbox 堆疊成 switch 的 css
$extend_head = $extend_head. "
<style>

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
</style>
";
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
// 主要內容 -- title
$tmpl['paneltitle_content'] 			= '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']				= $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");

?>