<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 平台商公告
// File Name:	systemconfig_ann.php
// Author:    Barkley, Mavis
// Related:
// DB Table:
// Log:
// ----------------------------------------------------------------------------
// 限制管理員才可以進入
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
$function_title 		= $tr['e-business platform'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['maintenance'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------


if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND  in_array($_SESSION['agent']->account, $su['ops'])) {

  $extend_head				= $extend_head.'<!-- Jquery UI js+css  -->
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
                                  "pageLength": 30,
                              } );
                            } )
                          </script>
                          ';

  // sql依據使用者所在時區顯示time
  if (isset($_SESSION['agent'] ->timezone) AND $_SESSION['agent'] ->timezone != NULL) {
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

  // 取出系統中所有的公告資訊
  $site_announcement_id_seq = "SELECT id,name,title,content,status,to_char((effecttime AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as effecttime,to_char((endtime AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as endtime,operator FROM site_announcement WHERE status != 2 ORDER BY id LIMIT 100;";

  $site_announcement_id_seq_result = runSQLall($site_announcement_id_seq);
  // var_dump($site_announcement_id_seq_result);
  // die();
  
  // 表格欄位名稱
  $table_colname_html = '
    <tr>
      <th class="info text-center">'.$tr['ID'].'</th>
      <th class="info text-center">'.$tr['announcement title'].'</th>
      <th class="info text-center">'.$tr['announcement name'].'</th>
      <th class="info text-center">'.$tr['Starting time'].'</th>
      <th class="info text-center">'.$tr['End time'].'</th>
      <th class="info text-center">'.$tr['status'].'</th>
      <th class="info text-center">'.$tr['operator'].'</th>
      <th class="info text-center">'.$tr['function'].'</th>
  </tr>
  ';

  // 表格內容
  $show_listrow_html = '';
  $link_detail ='';

  if($site_announcement_id_seq_result[0] >= 1) {
    // loop 列出每一行資料
    for($i=1; $i<=$site_announcement_id_seq_result[0]; $i++) {

      $site_announcement_id = $site_announcement_id_seq_result[$i]->id;
      $site_announcement_name = mb_substr($site_announcement_id_seq_result[$i]->name,0,10);
      $site_announcement_title = mb_substr($site_announcement_id_seq_result[$i]->title,0,10);
      $site_announcement_fullname = $site_announcement_id_seq_result[$i]->name;
      $site_announcement_fulltitle = $site_announcement_id_seq_result[$i]->title;
      $site_announcement_status = $site_announcement_id_seq_result[$i]->status;
      $site_announcement_effecttime = $site_announcement_id_seq_result[$i]->effecttime;
      $site_announcement_endtime = $site_announcement_id_seq_result[$i]->endtime;
      $site_announcement_operator = $site_announcement_id_seq_result[$i]->operator;
      $site_announcement_content = htmlspecialchars_decode($site_announcement_id_seq_result[$i]->content);

      $site_announcement_isopen_switch = '';
      // 狀態
      if($site_announcement_status == '1') {
        $site_announcement_isopen_switch = 'checked';
      } elseif($site_announcement_status == '0') {
        $site_announcement_isopen_switch = '';
      }

      $link_detail =<<<HTML
        <button type="button" class="btn btn-primary btn-sm" title="{$tr['detail']}" data-toggle="modal" data-target="#{$site_announcement_id}"><i class="fas fa-eye"></i></button>
          <div class="modal fade" id="{$site_announcement_id}" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="true">
            <div class="modal-dialog" role="document">
              <div class="modal-content">
                <div class="modal-header">
                  <h2 class="modal-title" id="myModalLabel">{$tr['announcement']}</h2>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>              
                </div>

                <div class="modal-body">
                  <div class="card-body">
                    <h5 class="card-subtitle mb-2 text-muted border-bottom font-weight-bold">{$tr['announcement title']}:</h5>
                    <p class="card-text">{$site_announcement_fulltitle}</p>

                    <h5 class="card-subtitle mb-2 text-muted border-bottom font-weight-bold">{$tr['announcement name']}:</h5>
                    <p class="card-text">{$site_announcement_fullname}</p>
                    
                    <h5 class="card-subtitle mb-2 text-muted border-bottom font-weight-bold">{$tr['Starting time']}:</h5>
                    <p class="card-text">{$site_announcement_effecttime}</p>

                    <h5 class="card-subtitle mb-2 text-muted border-bottom font-weight-bold">{$tr['announcement content']}:</h5>
                    <p class="card-text">{$site_announcement_content}</p>

                  </div>        
                </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{$tr['off']}</button>
              </div>
            </div>
          </div>
        </div>
HTML;

      // 表格 row $tr['edit'] = '編輯';
      $show_listrow_html = $show_listrow_html.'
      <tr>
        <td class="text-center">'.$site_announcement_id.'</td>
        <td class="text-center">'.$site_announcement_title.'</td>
        <td class="text-center">'.$site_announcement_name.'</td>
        <td class="text-center">'.$site_announcement_effecttime.'</td>
        <td class="text-center">'.$site_announcement_endtime.'</td>
        <td class="text-center">
          <div class="col-12 material-switch pull-left">
            <input id="site_announcement_status_open'.$site_announcement_id.'" name="site_announcement_status_open'.$site_announcement_id.'" class="checkbox_switch" value="'.$site_announcement_id.'" type="checkbox" '.$site_announcement_isopen_switch.'/>
            <label for="site_announcement_status_open'.$site_announcement_id.'" class="label-success"></label>
          </div>
        </td>
        <td class="text-center">'.$site_announcement_operator.'</td>
        <td>
          <button type="button" class="btn btn-danger btn-sm delete_btn" value="'.$site_announcement_id.'" title="'.$tr['delete'].'"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></button>

          <a class="btn btn-primary btn-sm" href="systemconfig_ann_editor.php?a='.$site_announcement_id.'" role="button" title="'.$tr['edit'].'"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>
          '.$link_detail.'
        </td>
      </tr>
      ';
    }
  }

  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  html 組合 start
  // -----------------------------------------------------------------------------------------------------------------------------------------------


  // enable sort table 啟用可排序的表格
  $sorttablecss = ' id="systeminfo"  class="display" cellspacing="0" width="100%" ';
  // 列出資料
  $show_list_html = '
  <table '.$sorttablecss.'>
    <thead>
      <a class="btn btn-success" id="add_system_announcement" href="./systemconfig_ann_editor.php?a=add" role="button" style="float: right;"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span>&nbsp; '.$tr['add platform announcement'].' </a>
      <br>
      <br>
      '.$table_colname_html.'
    </thead>
    <tfoot>
      '.$table_colname_html.'
    </tfoot>
    <tbody>
      '.$show_listrow_html.'
    </tbody>
  </table>
  ';
  
  //刪除js 和 啟用開關js $tr['OK to delete'] = '確定要刪除'; $tr['Illegal test'] = '(x)不合法的測試。';
  $extend_js =<<<HTML
  <script>
  
  $(document).ready(function(){
     // 刪除
    $('#systeminfo').on('click', '.delete_btn', function() {
      // 使用 ajax 送出 post
      var id = $(this).val();

      if(id != '') {
        if(confirm('{$tr['OK to delete']}?') == true) {
          $.ajax ({
            url: 'systemconfig_ann_action.php?a=delete',
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
    
   // 修改狀態
    $('#systeminfo').on('click', '.checkbox_switch', function() {
      // 使用 ajax 送出 post
      var id = $(this).val();

      if(id != '') {

        if($('#site_announcement_status_open'+id).prop('checked')) {
          var status = 1;
        }else{
          var status = 0;
        }

        $.ajax ({
          url: 'systemconfig_ann_action.php?a=edit_status',
          type: 'POST',
          data: ({
            id: id,
            status: status
          }),
          success: function(response){
            // $('#preview_result').html(response);
            window.location.href="systemconfig_ann.php";
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
  </script>
HTML;


  // 切成 1 欄版面 $tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';
  $indexbody_content = '';
  $indexbody_content = $indexbody_content.'
  <div class="row">
    <div class="col-12 col-md-12">
    '.$show_list_html.'
    </div>
  </div>
  <br>
  <div class="row">
    <div id="preview_result"></div>
  </div>
  ';

} else{

  // 沒有登入權限的處理
  $indexbody_content = $indexbody_content.'
  <br>
  <div class="row">
	  <div class="col-12 col-md-12">
      <div class="alert alert-danger">
      此页面只允许特定帐号存取
      </div>
    </div>
  </div>
  ';

}

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