<?php
// ----------------------------------------------------------------------------
// Features:	後台--GTOKEN即時稽核資料表
// File Name:	token_auditorial.php
// Author:		Yuan
// Related:   對應 member_account.php 即時稽核連結功能
// Log:
//
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 即時稽核 lib
require_once dirname(__FILE__) ."/token_auditorial_lib.php";

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
$function_title 		= $tr['Instant audit'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';

// ----------------------------------------------------------------------------
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' . $tr['Members and Agents'] . '</a></li>
  <li><a href="member.php">'.$tr['Member inquiry'].'</a></li>
  <li class="active">' . $function_title . '</li>
</ol>';


function auditorial_html($auditorial_details)
{
  global $auditmode_select;

  $html = '';

  foreach ($auditorial_details as $k => $v) {
    if ($v['audit_status'] == '1') {
      $audit_amount = '(无)';
      $is_audit_message = '<span class="glyphicon glyphicon-ok text-success" aria-hidden="true"></span>&nbsp;('.$v['audit_amount'].')';
    } else {
      $audit_amount = ($v['audit_method'] == 'shippingaudit') ? '$ '.$v['offer_deduction_amount'] : '$ '.$v['withdrawal_fee'];
      $is_audit_message = '<span class="glyphicon glyphicon-remove text-danger" aria-hidden="true"></span>&nbsp;('.$v['afterdeposit_bet'].' / '.$v['audit_amount'].')';
    }

    $howlongago = get_howlongago($v['deposit_time1']);

    $audit_method = $auditmode_select[$v['audit_method']];

    $sdate = gmdate('Y-m-d H:i',strtotime($v['deposit_time1']) + -4*3600);
    $edate = gmdate('Y-m-d H:i',strtotime($v['deposit_time2']) + -4*3600);

    $deposit_time = gmdate('Y-m-d H:i',strtotime($v['deposit_time1']) + -4*3600);

    $html .= <<<HTML
    <tr>
      <td class="gid" name="gid">{$v['gtoken_id']}</td>
      <td>
      <a class="btn btn-default" href="./member_betlog.php?a={$v['member_account']}&sdate={$sdate}&edate={$edate}" role="button">{$deposit_time}</a>
      
      <!-- 原版 -->
      <!-- <a class="btn btn-default" href="./member_betlog.php?a={$v['member_account']}&sdate={$sdate}&edate={$edate}" role="button">{$v['deposit_time1']}</a> -->

      <!-- {$v['deposit_time1']} -->
      <p class="text-muted">({$howlongago})</p>
      </td>
      <td class="text-center">$ {$v['deposit_amount']}</td>
      <td>{$audit_method}</td>
      <td>{$is_audit_message}</td>
      <td class="text-center">{$audit_amount}</td>
    </tr>
HTML;
  }

  return $html;
}


// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  $close_edit_btn = '';

  $id = filter_var($_GET['a'], FILTER_SANITIZE_STRING);

  $member_data = get_one_member_data($id, 'id');

   // 登入的使用者需為有效會員
  if ($member_data[0] == 1) {
  // if ($member_game_account_sql_result[0] == 1 AND $member_game_account_sql_result[1]->status == '1') {

    // 即時稽核相關資訊
    // $auditorial_data = get_auditorial_data($member_data[1]);
    $back_btn = <<<HTML
    <button type="button" class="btn btn-default" id="back_btn"  onclick="history.back()">{$tr['back to previous page']}</button>
HTML;

    if (isset($_GET['w']) && !empty($_GET['w'])) {
      $back_btn .= <<<HTML
      <a class="btn btn-default" href="./withdrawalgtoken_company_audit_review.php?id={$_GET['w']}" id="back_tokenreview_btn" role="button">回游戏币明细</a>
HTML;
    }

    if (isset($_GET['i']) && !empty($_GET['i'])) {
      $auditorial_data['auditorial_details'] = get_old_auditorial_data($member_data[1], $_GET['i']);
    } else {
      $auditorial_data = get_auditorial_data($member_data[1]);
      $close_edit_btn = <<<HTML
      <button type="button" class="btn btn-default" id="clear_audit">清除稽核</button>
      <a class="btn btn-default" href="./token_auditorial_edit.php?a={$id}" role="button">{$tr['Modify audit']}</a>
      <br><br>
HTML;
    }
  
    if (isset($auditorial_data['withdraw_data']) && $auditorial_data['withdraw_data'] != null) {
      $withdraw_lasttime = $auditorial_data['withdraw_data']->processing_time;
      $withdraw_lasttime_howlongago = get_howlongago($withdraw_lasttime);
      $withdraw_amount = money_format('%i', $auditorial_data['withdraw_data']->amount);

      $gtokenpassbook_balance_sql = "SELECT balance FROM root_member_gtokenpassbook WHERE destination_transferaccount = '$gtoken_cashier_account' AND source_transferaccount = '".$member_data[1]->account."' AND to_char((transaction_time AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') = '".$auditorial_data['withdraw_data']->processing_time."' ORDER BY id DESC";
      $gtokenpassbook_balance_sql_result = runSQLall($gtokenpassbook_balance_sql);

      $withdraw_balance = $gtokenpassbook_balance_sql_result[0] >= 1 ? money_format('%i', $gtokenpassbook_balance_sql_result[1]->balance) : '取款餘額查詢失敗';

    } else {
      $withdraw_lasttime = '-';
      $withdraw_lasttime_howlongago = '';
      $withdraw_amount = money_format('%i', 0);
      $withdraw_balance = money_format('%i', 0);
    }

    // 提示使用者會員提款資料
    $member_withdraw_tips_html = '
    <div class="row">
      <div class="col-12 col-md-2" >
        <td><strong>查询会员帐号 : </strong></td>
      </div>
      <div class="col-12 col-md-10">
        '.$member_data[1]->account.'
      </div>
    </div>
    <br>
    <div class="row">
      <div class="col-12 col-md-2">
        <td><strong>最后取款时间 : </strong></td>
      </div>
      <div class="col-12 col-md-10">
        '.$withdraw_lasttime.' <small class="text-muted">('.$withdraw_lasttime_howlongago.')</small>
      </div>
    </div>
    <br>
    <div class="row">
      <div class="col-12 col-md-2">
        <td><strong>最后取款金额 : </strong></td>
      </div>
      <div class="col-12 col-md-10">
        '.$withdraw_amount.'
      </div>
    </div>
    <br>
    <div class="row">
      <div class="col-12 col-md-2">
        <td><strong>最后取款余额 : </strong></td>
      </div>
      <div class="col-12 col-md-10">
        '.$withdraw_balance.'
      </div>
    </div>
    <br>
    <div class="row">
      <div class="col-12 col-md-2">
        <td><strong>存款明细如下表 : </strong></td>
      </div>
    </div>
    <br>
    ';


    if ($auditorial_data['auditorial_details'] != null) {

      $show_listrow_html = auditorial_html($auditorial_data['auditorial_details']);

      // 表格欄位名稱
      $table_colname_html = '
      <tr>
        <th>单号</th>
        <th>时间</th>
        <th>存款金额</th>
        <th>稽核方式</th>
        <th>存款后投注额 / 目标打码量</th>
        <th>稽核金额</th>
      </tr>
      ';

        /*
          <th>存款類別</th>
          <th>稽核金額</th>
          <th>稽核方式</th>
          <th>存款後投注量</th>
        */

      // enable sort table
      $sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';

      // 列出資料, 主表格架構
      $show_list_html = $member_withdraw_tips_html;
      $show_list_html .= $back_btn.$close_edit_btn.'
      <table id="show_list" class="table table-striped" cellspacing="0" width="100%">
      <thead>
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

      // 參考使用 datatables 顯示
      // https://datatables.net/examples/styling/bootstrap.html 
      $extend_head = $extend_head.'
      <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
      <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
      <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
      ';

      // DATA tables jquery plugging -- 要放在 head 內 不可以放 body
      $extend_head = $extend_head.'
      <script type="text/javascript" language="javascript" class="init">
        $(document).ready(function() {
          $("#show_list").DataTable( {
              "paging":   true,
              "ordering": true,
              "info":     true,
              "order": [[ 1, "desc" ]],
              "pageLength": 30
          } );
        } )
      </script>
      ';

      // 即時編輯工具 ref: https://vitalets.github.io/x-editable/docs.html#gettingstarted
      $extend_head = $extend_head.'
      <!-- x-editable (bootstrap version) -->
      <link href="in/bootstrap3-editable/css/bootstrap-editable.css" rel="stylesheet"/>
      <script src="in/bootstrap3-editable/js/bootstrap-editable.min.js"></script>
      ';

      $extend_js = "
      <script>
        $('#clear_audit').click(function () {
          var gid = $.param($('.gid').map(function() {
            return {
                name: $(this).attr('name'),
                value: $(this).text().trim()
            };
          }));

          var message = '此动作无法复原，确定要清除所有稽核？';
          if(confirm(message) == true){
            $.post('token_auditorial_action.php',
              {
                gid: gid
              },
              function(result){
                $('#preview_result').html(result);}
            );
          }else{
            window.location.reload();
          }
        });
      </script>";


      // 切成 1 欄版面
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


    } else {
      $show_transaction_list_html  = '
      <div id="preview_area" class="alert alert-info" role="alert">
      无任何稽核详细资讯。
      </div>';

      // 切成 1 欄版面
      $indexbody_content = $member_withdraw_tips_html;
      $indexbody_content = $indexbody_content.'
      <div class="row">
        <div class="col-12 col-md-12">
        '.$show_transaction_list_html.'
        </div>
      </div>
      <br>
      <div class="row">
        <div id="preview_result"></div>
      </div>
      ';
    }

  } else {
    // 沒有登入的顯示提示俊息
    $show_transaction_list_html  = '(x) 查询的会员帐号错误或无效，请确认后重新输入。';

    // 切成 1 欄版面
    $indexbody_content = '';
    $indexbody_content = $indexbody_content.'
    <div class="row">
      <div class="col-12 col-md-12">
      '.$show_transaction_list_html.'
      </div>
    </div>
    <br>
    <div class="row">
      <div id="preview_result"></div>
    </div>
    ';
  }
} else {
  // 沒有登入的顯示提示俊息
  $show_transaction_list_html  = '(x) 只有管理员或有权限的会员才可以登入观看。';

  // 切成 1 欄版面
  $indexbody_content = '';
  $indexbody_content = $indexbody_content.'
	<div class="row">
	  <div class="col-12 col-md-12">
	  '.$show_transaction_list_html.'
	  </div>
	</div>
	<br>
	<div class="row">
		<div id="preview_result"></div>
	</div>
	';
}

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