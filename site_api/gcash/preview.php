<?php
// ----------------------------------------------------------------------------
// Features:  前台 - 現金(GCASH) api preview
// File Name:	site_api/gcash/preview.php
// Author:		Dright
// Related:
// DB table:  root_site_api_account, root_gcash_order
// Log:
// ----------------------------------------------------------------------------
/*
主要操作的DB表格：
root_site_api_account  site api 帳號
root_gcash_order       gcash api 訂單紀錄

前台
site_api/gateway.php 所有site_api都由這裡進入。
site_api/gcash/preview_action.php 對應的action

*/
// ----------------------------------------------------------------------------



// 主機及資料庫設定
require_once __DIR__ . '/../../config.php';
// 自訂函式庫
require_once __DIR__ ."/../../lib.php";
// 支援多國語系
require_once __DIR__ ."/../../i18n/language.php";
require_once __DIR__ . '/../lib_api.php';

// 取得付款人的帳戶資訊
function get_payment_user_info($source_transferaccount) {
  // 取得轉帳來源帳號
  $source_acc_sql     = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '".$source_transferaccount."' AND root_member.status = '1';";
  $source_acc_result  = runSQLall($source_acc_sql);
  return $source_acc_result;
}

function check_api_data($api_data) {
  $key = get_api_account_key($api_data->api_account);
  return check_sign(get_object_vars($api_data), $key);
}

function logout_user() {
	global $redisdb;
  // ----------------------------------------------------------------------------
  // 會員登出，並清除 session - logout
  // ----------------------------------------------------------------------------
  	// echo '<img src="./ui/loading_spin.gif">';

  	// Transferout_Casino_MG2_balance() and Retrieve_Casino_MG2_balance()
  	// 執行完成後，把 $_SESSION['wallet_transfer'] 變數清除, 其他程式才可以進入。
  	// 避免連續性呼叫 Retrieve_Casino_MG2_balance() lib , 需要到 home.php and gamelobby.php 清除變數才可以。
  	if(isset($_SESSION['wallet_transfer'])) {
  		unset($_SESSION['wallet_transfer']);
  	}

  	// 登出註銷 redis server record
  	if(isset($_SESSION['member'])) {
  		// logout 紀錄到 DB
  		$logger = $_SESSION['member']->account.'登出帳號';
  		memberlog2db($_SESSION['member']->account,'logout','info', "$logger");

  		// del 所有的 same account session
  		// session_name().':'.session_id();
  		$value = $_SESSION['member']->account;
  		$sid = sha1($value).':'.session_id();
  		// var_dump($sid);
      runRedisDEL($sid, $redisdb['db']);
  	}

  	// 確認有沒有清空 session  , 沒有的話再 run 一次
  	if(isset($_SESSION)) {

  		// 重置会话中的所有变量
  		$_SESSION = array();

  		// 如果要清理的更彻底，那么同时删除会话 cookie
  		// 注意：这样不但销毁了会话中的数据，还同时销毁了会话本身
  		if (ini_get("session.use_cookies")) {
  				$params = session_get_cookie_params();
  				setcookie(session_name(), '', time() - 42000,
  						$params["path"], $params["domain"],
  						$params["secure"], $params["httponly"]
  				);
  		}

  		// 最后，销毁会话
  		@session_destroy();
  	}
}


// Main


if(!isset($_GET['t'])) {
  http_response_code(406);
  return;
}

// actions: preview, confirm
$action = 'preview';

if(isset($_GET['a'])) {
  $action = $_GET['a'];
}

$token = $_GET['t'];

// $api_data = [
//   'service' => 'gcash',
//   'api_account' => 'ec_test',
//   'order_title' => 'test',
//   'order_no' => 'ec_11111',
//   'payment_user' => 'jjj',
//   'amount' => '1',
//   'description' => 'just for testing',
//   'return_url' => '',
//   'notify_url' => '',
//   'sign' => '',
//   'timestamp' => '',
// ];
$api_data = jwtdec('123456', $token);

if(!check_api_data($api_data)) {
  http_response_code(406);
  return;
}

// order data
$order_title = htmlentities($api_data->order_title);
$description = htmlentities($api_data->description);


// start of information_text
$information_text = <<<HTML
<div class="alert alert-info" role="alert">請確認訂單明細</div>
HTML;
// end of information_text

if( isset($_SESSION['member']) && $_SESSION['member']->account !== $api_data->payment_user  ) {
  $is_wrong_account = true;
  $action = 'wrong_user';
} else {
  $is_wrong_account = false;
}

$user_gcash_balance = get_payment_user_info($api_data->payment_user)[1]->gcash_balance;
$submit_button = ($api_data->amount <= $user_gcash_balance)? <<<HTML
    <input type="submit" form="confirm_gcash_order" value="付款" class="btn btn-primary btn-wg js-send-confirm"> 
HTML
: <<<HTML
    <input type="button" value="存款" class="btn btn-primary" onclick="window.open('https://{$config['website_domainname']}/deposit.php')"> 
HTML;


switch ($action) {
  case 'confirm':
    $confirm_form = <<<HTML
    <form name="confirm_gcash_order" id="confirm_gcash_order" action="https://{$config['website_domainname']}/site_api/gcash/preview_action.php?a=confirm" method="POST">
      <div class="row">
        <div class="col-12 col-md-2">
        </div>
        <div class="col-12 col-md-2">
          <p class="text-right">*取款密码</p>
        </div>
        <div class="col-12 col-md-6">
          <div class="form-group">
            <input type="password" class="form-control" name="withdrawal_password" placeholder="取款密码" onkeypress="if (event.keyCode == 13) {return false;}">
          </div>
        </div>
        <div class="col-12 col-md-2">
        </div>
      </div>

      <!-- hidden input fields  -->
      <input type="hidden" size="50" name="service" value="$api_data->service">
      <input type="hidden" size="50" name="api_account" value="$api_data->api_account">
      <input type="hidden" size="50" name="order_title" value="$order_title">
      <input type="hidden" size="50" name="order_no" value="$api_data->order_no">
      <input type="hidden" size="50" name="payment_user" value="$api_data->payment_user">
      <input type="hidden" size="50" name="amount" value="$api_data->amount">
      <input type="hidden" size="50" name="description" value="$description">
      <input type="hidden" size="50" name="return_url" value="$api_data->return_url">
      <input type="hidden" size="50" name="notify_url" value="$api_data->notify_url">
      <input type="hidden" size="50" name="sign" value="$api_data->sign">
      <input type="hidden" size="50" name="timestamp" value="$api_data->timestamp">
      <input type="hidden" size="50" name="order_detail_url" value="$api_data->order_detail_url">      
    </form>
 <div class="pull-right">    
    $submit_button
      <input type="button" value="刷新餘額" class="btn btn-warning" onclick="document.location.reload(true)">
      <input type="button" value="回上一頁" class="btn btn-default" onclick="location.href='$api_data->order_detail_url'">
  </div>
    <div id="show_result">
    </div>
    <script type="text/javascript">
      $(document).ready(function(){
        $('.js-send-confirm').click(function(e) {
          e.preventDefault();
          $('#show_result').html('處理中...');
          // 使用 ajax 送出 post
          var service      = $('input[name=service]').val();
          var api_account  = $('input[name=api_account]').val();
          var order_title  = $('input[name=order_title]').val();
          var order_no     = $('input[name=order_no]').val();
          var payment_user = $('input[name=payment_user]').val();
          var amount       = $('input[name=amount]').val();
          var description  = $('input[name=description]').val();
          var return_url   = $('input[name=return_url]').val();
          var notify_url   = $('input[name=notify_url]').val();
          var sign         = $('input[name=sign]').val();
          var timestamp    = $('input[name=timestamp]').val();
          var withdrawal_password = $().crypt({method:'sha1', source: $('input[name=withdrawal_password]').val() });
          var order_detail_url = $('input[name=order_detail_url]').val();


          // console.log(withdrawal_password);

          $.ajax ({
            url: "https://{$config['website_domainname']}/site_api/gcash/preview_action.php?a=confirm",
            type: "POST",
            data: {
              service: service,
              api_account: api_account,
              order_title: order_title,
              order_no: order_no,
              payment_user: payment_user,
              amount: amount,
              description: description,
              return_url: return_url,
              notify_url: notify_url,
              sign: sign,
              timestamp: timestamp,
              withdrawal_password: withdrawal_password,
              order_detail_url: order_detail_url
            }
          }).done(function(response_data){
            // console.log(response_data);
            //  $('#show_result').html(response_data);
            location.href = response_data.url;
          }).fail(function(errorinfo) {
            // console.log(errorinfo.responseJSON);
            var error_html = '<br><div class="alert alert-warning" role="alert">' + errorinfo.responseJSON.message + '</div>';
            $('#show_result').html(error_html);
          });
        });
      });
    </script>
HTML;

    break;

  default:
  $error_message = '';
  if($is_wrong_account) {
    $error_message = '<div class="alert alert-warning" role="alert">錯誤的付款帳號</div><br>';
    logout_user();
  }
$api_data->order_detail_url = $api_data->order_detail_url ?? $config['ec_protocol'] . '://' . $config['ec_host'] . '/index.php?route=checkout/cart';
  $confirm_form = <<<HTML
  <form id="confirm_gcash_order" name="confirm_gcash_order" action="https://{$config['website_domainname']}/site_api/gcash/preview_action.php?a=preview" method="POST">
    <!-- hidden input fields  -->
    $error_message
    <input type="hidden" size="50" name="t" value="$token">
  </form>
 <div class="pull-right">
  <input type="submit" form="confirm_gcash_order" value="確認" class="btn btn-primary btn-wg">
  <input type="button" onclick="location.href='$api_data->order_detail_url'" value="回上一頁" class="btn btn-default">
 </div> 
HTML;

    break;
}

// start of form_html
$form_html = <<<HTML

<table class="table table-bordered">
  <caption>
    <strong>$api_data->order_title</strong>
  </caption>
  <!-- <thead>
    <tr class="info">
    </tr>
  </thead> -->
  <tbody>
    <tr>
      <td>單號</td>
      <td>$api_data->order_no</td>
    </tr>
    <tr>
      <td>付款帳號</td>
      <td>$api_data->payment_user <span class="label label-success" title="存款後請刷新頁面">加盟金 $ $user_gcash_balance</span></td>
    </tr>
    <tr>
      <td>交易金額</td>
      <td>$api_data->amount</td>
    </tr>
    <tr>
      <td>交易時間</td>
      <td>$api_data->timestamp</td>
    </tr>
    <tr>
      <td>訂單內容</td>
      <td>$description</td>
    </tr>

  </tbody>
</table>
$confirm_form

HTML;
// end of form_html


// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------
// 將內容塞到 html meta 的關鍵字, SEO 加強使用

// 初始化變數
// 功能標題，放在標題列及meta
$function_title = 'Gcash 訂單明細';
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
// start of indexbody_content
$indexbody_content = <<<HTML
<div class="row">
  <div class="col-12 col-sm-12">
    $information_text
    $form_html
  </div>
</div>
<div class="row">
  <div class="col-12 col-sm-12">
    <div id="preview_status"></div>
  </div>
</div>
HTML;
// end of indexbody_content

// 系統訊息選單
$messages 					= '';
// ----------------------------------------------------------------------------
// 導覽列
$navigational_hierarchy_html = '
<ul class="breadcrumb">
  <li><a href="home.php"><span class="glyphicon glyphicon-home"></span></a></li>
  <li class="active">'.$function_title.'</li>
</ul>
';

$tmpl['html_meta_description'] 		= $tr['host_descript'];
$tmpl['html_meta_author']	 				= $tr['host_author'];
$tmpl['html_meta_title'] 					= $function_title.'-'.$tr['host_name'];

// 系統訊息顯示
$tmpl['message']									= $messages;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']							= $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']								= $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] 			= $navigational_hierarchy_html;
// 主要內容 -- content
$tmpl['panelbody_content']				= $indexbody_content;


// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include($config['template_path']."template/login2page.tmpl.php");

?>
