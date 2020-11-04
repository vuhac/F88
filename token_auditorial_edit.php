<?php
// ----------------------------------------------------------------------------
// Features:	後台--稽核金額修改
// File Name:	token_auditorial_edit.php
// Author:		Yuan
// Related:   對應 token_auditorial.php 修改稽核連結功能
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
$function_title 		= $tr['Modify audit'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';


function auditorial_html($auditorial_details)
{
  global $auditmode_select;

  $html = '';

  foreach ($auditorial_details as $k => $v) {
    if ($v['audit_status'] == '1') {
      $is_audit_message = '<span class="glyphicon glyphicon-ok text-success" aria-hidden="true"></span>&nbsp;('.$v['audit_amount'].')';
    } else {
      $is_audit_message = '<span class="glyphicon glyphicon-remove text-danger" aria-hidden="true"></span>&nbsp;('.$v['afterdeposit_bet'].' / '.$v['audit_amount'].')';
    }

    if ($v['audit_method'] == 'shippingaudit') {
      $offer_audit_input = '<input type="number" step="0.01" min="0" class="form-control" id="'.$v['gtoken_id'].'_offer" value="'.$v['offer_deduction_amount'].'">';
      $deposit_audit_input = '-';
    } else {
      $offer_audit_input = '-';
      $deposit_audit_input = '<input type="number" step="0.01" min="0" class="form-control" id="'.$v['gtoken_id'].'_deposit" value="'.$v['withdrawal_fee'].'">';
    }

    $howlongago = get_howlongago($v['deposit_time1']);

    $html .= <<<HTML
    <tr>
      <td class="edit_auditorial" name="data">{$v['gtoken_id']}</td>
      <td>{$v['deposit_time1']}<p class="text-muted">({$howlongago})</p></td>
      <td class="text-center">$ {$v['deposit_amount']}</td>
      <td>{$is_audit_message}</td>
      <td>{$offer_audit_input}</td>
      <td>{$deposit_audit_input}</td>
    </tr>
HTML;
  }

  return $html;
}


// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  $id = filter_var($_GET['a'], FILTER_SANITIZE_STRING);

  $member_data = get_one_member_data($id, 'id');

   // 登入的使用者需為有效會員
  if ($member_data[0] == 1) {

    // 即時稽核相關資訊
    $auditorial_data = get_auditorial_data($member_data[1]);

    if ($auditorial_data['auditorial_details'] != null) {

      $show_listrow_html = auditorial_html($auditorial_data['auditorial_details']);

      // 表格欄位名稱
      $table_colname_html = '
      <tr>
        <th>单号</th>
        <th>时间</th>
        <th>存款金额</th>
        <th>存款后投注额 / 目标打码量</th>
        <th>优惠扣除</th>
        <th>行政费用</th>
      </tr>
      ';

      // enable sort table
      $sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';

      // 列出資料, 主表格架構
      $show_list_html = '
      <table class="table table-bordered">
        <thead>
          '.$table_colname_html.'
        </thead>
        <tbody>
          '.$show_listrow_html.'
        </tbody>
      </table>
      <br>
      <button type="button" class="btn btn-default" id="update_audit">更新</button>
      <a class="btn btn-default" href="./token_auditorial.php?a='.$id.'" role="button">取消</a>
      ';

      $extend_js = "
      <script>
      $('#update_audit').click(function () {
        var data = $.param($('.edit_auditorial').map(function() {
          return {
              name: $(this).attr('name'),
              value: $(this).text().trim()
          };
        }));

        var obj = {};

        var id_list = data.split('&');

        id_list.forEach(function(e) {
          var id = e.split('data=');

          var offer = $('#'+id[1]+'_offer').val();

          var deposit = $('#'+id[1]+'_deposit').val();

          obj[id[1]] = {deposit : deposit, offer : offer};

        });

        var message = '确定要更新？';
        if(confirm(message) == true) {
          $.post('token_auditorial_edit_action.php',
            {
              obj: obj
            },
            function(result) {
              $('#preview_result').html(result);
            }
          );
        } else {
          window.location.reload();
        }
      });
      </script>";


      // 切成 1 欄版面
      $indexbody_content = '
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
$tmpl['page_title']								= '<h2><strong>'.$function_title.'</strong></h2><hr>';
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