<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 線上支付訂單詳細
// File Name:	depositing_company_audit_review.php
// Author:		Barkley
// Related:		對應後台 depositing_company_audit.php
// Log:
// ----------------------------------------------------------------------------
// 對應資料表：root_deposit_onlinepay_summons 線上支付訂單詳細
// 相關的檔案：
//
//
// 2017.8.29 update

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
// 功能標題，放在標題列及meta $tr['Online payment order details']          = '線上支付訂單詳細';
$function_title 		= $tr['Online payment order details'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置 $tr['Home'] = '首頁';  $tr['Account Management'] = '帳務管理'; $tr['Online payment dashboard'] = '線上支付看板';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['Account Management'].'</a></li>
  <li><a href="depositing_onlinepay_audit.php">'.$tr['Online payment dashboard'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  // 使用者所在的時區，sql 依據所在時區顯示 time
  // -------------------------------------
  if(isset($_SESSION['agent']->timezone) AND $_SESSION['agent']->timezone != NULL) {
    $tz = $_SESSION['agent']->timezone;
  }else{
    $tz = '+08';
  }
  // 轉換時區所要用的 sql timezone 參數
  $tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."'";
  $tzone = runSQLALL($tzsql);

  if($tzone[0]==1){
    $tzonename = $tzone[1]->name;
  }else{
    $tzonename = 'posix/Etc/GMT-8';
  }

  $merchant_order_id = $_GET['m'];

  // checkout order id to avoid sql injection
  if(!filter_var($merchant_order_id, FILTER_VALIDATE_INT)) {
    die('invalid order id');
  }

  // 搜寻 root_deposit_review 單筆資料
  $depositing_onlinepay_summon_sql = "
  SELECT *, to_char((transfertime AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as transfertime_tz
  FROM root_deposit_onlinepay_summons
  WHERE id = '$merchant_order_id'
  ";
 // var_dump($depositing_onlinepay_summon_sql);
  $depositing_summon_result = runSQLALL($depositing_onlinepay_summon_sql);
 // var_dump($depositing_summon_result);

  // 搜寻 root_member 單筆資料
  $depositing_root_member_sql = "SELECT * FROM root_member WHERE account = '".$depositing_summon_result[1]->account."' ";
//  var_dump($depositing_root_member_sql);
  $depositing_root_member_result = runSQLALL($depositing_root_member_sql);
//  var_dump($depositing_root_member_result);

  $show_list_tbody_html = '';
  // $tr['cash flow']= '金流訂單';$tr['Corresponding cash flow orders']= '對應的金流訂單';
  $show_list_tbody_html .= '
  <tr>
    <td><strong>'.$tr['cash flow'].'ID</strong></td>
    <td>' . $depositing_summon_result[1]->merchantorderid . '</td>
    <td>'.$tr['Corresponding cash flow orders'].'</td>
  </tr>
  ';
  // $tr['Member Account'] = '會員帳號';
  $show_list_tbody_html .= '
  <tr>
    <td><strong>'.$tr['Account'].'</strong></td>
    <td>
      <a href="member_account.php?a=' . $depositing_root_member_result[1]->id . '">' . $depositing_summon_result[1]->account . '</a>
    </td>
    <td>' . '' . '</td>
  </tr>
  ';
  // $tr['deposits']= '存入金額';
  $show_list_tbody_html .= '
  <tr>
    <td><strong>'.$tr['deposits'].'</strong></td>
    <td>' . $depositing_summon_result[1]->amount . '</td>
    <td>' . '' . '</td>
  </tr>
  ';
  // $tr['Cash flow costs']= '金流成本'; $tr['This cash flow cost of the order']= '此訂單金流成本';
  $show_list_tbody_html .= '
  <tr>
    <td><strong>'.$tr['Cash flow costs'].'</strong></td>
    <td>' . $depositing_summon_result[1]->cashfee_amount . '</td>
    <td>'.$tr['This cash flow cost of the order'].'</td>
  </tr>
  ';
  // $tr['Deposit time']= '存入時間';
  $show_list_tbody_html .= '
  <tr>
    <td><strong>'.$tr['Deposit time'].'</strong></td>
    <td>' .  $depositing_summon_result[1]->transfertime . '</td>
    <td>' . '' . '</td>
  </tr>
  ';
  // $tr['Third-party payment service providers'] = '第三方支付服務商';
  $show_list_tbody_html .= '
  <tr>
    <td><strong>'.$tr['Third-party payment service providers'].'</strong></td>
    <td>' . $depositing_summon_result[1]->onlinepay_company . '</td>
    <td>' . '' . '</td>
  </tr>
  ';

  // $tr['Handler'] = '處理人員';
  $show_list_tbody_html .= '
  <tr>
    <td><strong>'.$tr['Handler'].'</strong></td>
    <td>' . $depositing_summon_result[1]->processingaccount . '</td>
    <td>' . '' . '</td>
  </tr>
  ';

  switch($depositing_summon_result[1]->status){
    case 0:
      // $tr['review_agent_status_0'] = '入款失敗';
      $deposit_status = '<span class="label label-warning">'.$tr['review_agent_status_0'].'</span>';
      break;
    case 1://$tr['Incoming payment manual confirmation']  = '入款手動確認';
      $deposit_status = '<span class="label label-success">'.$tr['Incoming payment manual confirmation'].'</span>';
      break;
    case 2://$tr['Automatic confirmation']= '自動確認';
      $deposit_status = '<span class="label label-primary">'.$tr['Automatic confirmation'].'</span>';
      break;
    case 3://$tr['Automatic confirmation']= '自動確認';
      $deposit_status = '<span class="label label-info">'.$tr['Incoming payment manual cancel'].'</span>';
      break;
    default:
      // $tr['review_agent_status_n'] = '尚未入款'; $tr['Check order status']= '檢查訂單狀態';
      $deposit_status = '<span class="label label-default">'.$tr['review_agent_status_n'].'</span> <button id="agreen_ok" class="btn btn-warning btn-sm pull-right" role="button">'.$tr['Check order status'].'</button>';
  }

  if(is_null($depositing_summon_result[1]->status)) {
    $deposit_status = '
    <span class="label label-default">'.$tr['review_agent_status_n'].'</span>
    <button
      id="agreen_ok"
      class="btn btn-warning btn-sm pull-right js-check-payment-status"
      data-deposit-onlinepay-gateway="' . $depositing_summon_result[1]->onlinepay_company . '"
      data-deposit-onlinepay-summon-id="' . $depositing_summon_result[1]->merchantorderid . '"
      data-deposit-onlinepay-amt="' . $depositing_summon_result[1]->amount . '"
      role="button"
    >
      '.$tr['Check order status'].'
    </button>
    ';
  }
  // $tr['Processing status']= '處理狀態';
  $show_list_tbody_html .= '
  <tr>
  <td><strong>'.$tr['Processing status'].'</strong></td>
  <td id="deposit_status">' . $deposit_status . '</td>
  <td id="deposit_status_msg">' . '' . '</td>
  </tr>
  ';




  // 返回上一页 $tr['go back to the last page'] = '返回上一頁';
  $show_list_return_html = '<p align="right"><a href="depositing_onlinepay_audit.php" class="btn btn-success btn-sm active" role="button">'.$tr['go back to the last page'].'</a></p>';

  // 欄位標題 $tr['field'] = '欄位'; $tr['content'] = '內容'; $tr['Remark'] = '備註';
  $show_list_thead_html = '
  <tr>
    <th>'.$tr['field'].'</th>
    <th>'.$tr['content'].'</th>
    <th>'.$tr['Remark'].'</th>
  </tr>
  ';

  // 以表格方式呈現
  $show_list_html = '
  <table class="table">
    <thead>
    '.$show_list_thead_html.'
    </thead>
    <tbody>
    '.$show_list_tbody_html.'
    </tbody>
  </table>
  ';

  // 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
  <div class="row">
		<div class="col-12 col-md-12">
    '.$show_list_html.'
		</div>
	</div>
	<hr>
  '.$show_list_return_html.'
	<div class="row">
		<div id="preview_result"></div>
	</div>
	';

}else{
	// 沒有登入的顯示提示俊息 $tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';
	$show_html  = $tr['only management and login mamber'];

	// 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">
	  <div class="col-12 col-md-12">
	  '.$show_html.'
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

$onlinepay_summon_json = json_encode(
  [
    'account' => (string) $depositing_summon_result[1]->account,
    'changetime' => (string) $depositing_summon_result[1]->changetime,
    'amount' => (string) $depositing_summon_result[1]->amount,
    'merchantorderid' => (string) $depositing_summon_result[1]->merchantorderid,
    'onlinepay_company' => (string) $depositing_summon_result[1]->onlinepay_company,
  ]
);

$extend_js .= <<<HTML
<script type="text/javascript" language="javascript">

window.onlinepay_summon = $onlinepay_summon_json;

$(document).ready(function() {
  // check payment status
  $(".js-check-payment-status").on("click", function(e){
    e.preventDefault();
    console.log( $(e.target).data("deposit-onlinepay-summon-id") );
    var gateway = $(e.target).data("deposit-onlinepay-gateway");
    var onlinepayId = $(e.target).data("deposit-onlinepay-summon-id");
    var amt = $(e.target).data("deposit-onlinepay-amt");

    $.post("depositing_onlinepay_audit_action.php?a=check_payment_status", { id: onlinepayId, gateway: gateway, amt: amt})
      .done(function(data){

        $(e.target).hide()
        console.log(data)
        var data_obj = $.parseJSON(data)
        $("#deposit_status_msg").html(data_obj.r.message)

        for(var dom_id in data_obj.ui_html) {
          var _dom = $("#" + dom_id);
          if (_dom.size() !== 1) {
            continue;
          }
          _dom.append(data_obj.ui_html[dom_id]);
        }
        // location.reload();
       })
      .fail(function(data){
        console.log(data)
        // location.reload();
      });

  });
});
</script>
HTML;

$extend_head = <<<HTML
<script>
// manual confirm payment status
function manual_confirm()
  {
    console.log( onlinepay_summon );
    var gateway = onlinepay_summon.onlinepay_company;
    var onlinepayId = onlinepay_summon.merchantorderid;
    var amt = onlinepay_summon.amount;

    $.post("depositing_onlinepay_audit_action.php?a=manual_confirm", { id: onlinepayId, gateway: gateway, amt: amt})
      .done(function(data){
        console.log(data)
        alert($.parseJSON(data).r.message)
        location.reload();
       })
      .fail(function(data){
        console.log(data)
        location.reload();
      });
  }
  // manual cancel payment status
  function manual_cancel()
  {
    console.log( onlinepay_summon );
    var gateway = onlinepay_summon.onlinepay_company;
    var onlinepayId = onlinepay_summon.merchantorderid;
    var amt = onlinepay_summon.amount;

    $.post("depositing_onlinepay_audit_action.php?a=manual_cancel", { id: onlinepayId, gateway: gateway, amt: amt})
      .done(function(data){
        console.log(data)
        alert($.parseJSON(data).r.message)
        location.reload();
       })
      .fail(function(data){
        console.log(data)
        location.reload();
      });
  }
</script>
HTML;


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
