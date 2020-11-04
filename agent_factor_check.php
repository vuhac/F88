<?php
// ----------------------------------------------------------------------------
// Features:	二階段驗證
// File Name:	agent_factor_check.php
// Author:		Mavis
// Related:   agent_login_action.php,agent_login_lib.php
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/in/PHPGangsta/GoogleAuthenticator.php";
// IP、2FA函式庫
require_once dirname(__FILE__) ."/agent_login_lib.php";
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title 		= $tr['two-factor authentication'];
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
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------


if(isset($_GET['ref']) AND $_GET['ref'] != null){
  
  $login_icon_html = '';
  // ref組成方式:
  // 帳號base64_encode + fingertracker、date sha1

  // fingertracker、date sha1
  $to_decode = filter_var($_GET['ref'],FILTER_SANITIZE_STRING);
  // explode 帳號 
  $explode_account = explode("_",$to_decode);
  // 把帳號decode
  $decode_account = base64_decode($explode_account[0]);

  $csrftoken_valid = sha1(date('d_H').$_SESSION['fingertracker']);

  $get_all_agent_id_sql = get_all_agent_id();
  // 避免ref參數被亂改，除非user 知道會員帳號而且知道該帳號2FA有開啟，但驗證碼的帳戶如果不對，還是無法進入home.php
  // 導回登入頁面
  if(array_key_exists($decode_account,$get_all_agent_id_sql)){

    $agent_id = $get_all_agent_id_sql[$decode_account]['id'];
    // 找2階段驗證是否有資料
    $to_check_factor_isset = check_security_setting($agent_id);
    $to_check_factor_isset_result = runSQLall($to_check_factor_isset);

    // 如果2階段驗證有資料，而且ref的參數跟$csrftoken_valid 依樣
    if($to_check_factor_isset_result[1]->two_fa_status == '1' AND $csrftoken_valid == $explode_account[1]){
        $login_icon_html = $login_icon_html.'
        <div class="card">
          <div class="card-body">
            <h4 class="card-title text-dark text-center">'.$tr['two-factor authentication'].'</h4>
            <div class="jumbotron">
              <h6 class="card-subtitle mb-2 text-muted text-center">'.$tr['Please enter the verification code provided by the mobile device'].'</h6>
              <div class="col-12">
                <input type="text" name="verify_code" class="form-control">
              </div>
            </div>
            <ul style="list-style-type:none">
              <li class="text-danger">'.$tr['The verification code will be updated automatically in about 1 and a half minutes.'].'</li>
              <li class="text-danger">'.$tr['If you have any questions, please contact customer service.'].'</li>
            </ul>
            <br>
            <button type="button" id="clear_all_data" class="btn btn-secondary" onclick="javascript:location.href=\'agent_login.php\'">'.$tr['go back to the last page'].'</button>
            <button type="button" id="confrim_factor_id" class="btn btn-primary">'.$tr['confirm'].'</button>
          </div>
        </div>
    ';
    }else{
      header('Location:./home.php');
      die();
    }

  }else{
    header('Location:./home.php');
    die();
  }

}else{
  header('Location:./home.php');
  die();
}

// js
$extend_js =<<<HTML
  <script type="text/javascript" language="javascript" class="init">
  $(document).ready(function() {
    // 確認
    $("#confrim_factor_id").on('click',function(){
      // 驗證碼
      var verify_code = $("input[name=verify_code]").val();
      // 帳號
      var agent_account = '$decode_account';
      // 2fa 檢查
      $.post('agent_login_action.php?a=factor_check',{
          verify_code : verify_code, 
          agent_account: agent_account
			  },
        function(result){
          $('#preview_status').html(result);
        }
			);
    })
  });

  // 按下 enter 後,等於 click 登入按鍵
		$(function() {
			 $(document).keyup(function(e) {
				switch(e.which) {
					case 13: // enter key
						$("#confrim_factor_id").trigger("click");
					break;
				}
		  });
		});
    
  </script>
HTML;


// 排版顯示
$container_content ='
    <br><br><br><br><br>
    <div class="row">
      <div class="col-12 col-md-4"></div>
      <div class="col-12 col-md-4">
        '.$login_icon_html.$extend_js.'
      </div>
    <div class="col-12 col-md-4"></div>
    </div>
    <div id="preview_status"></div>
';


// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------
// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] 		= $tr['host_descript'];
$tmpl['html_meta_author']	 				= $tr['host_author'];
$tmpl['html_meta_title'] 					= $function_title.'-'.$tr['host_name'];

// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']							= $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']								= $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] 			= $config['hostname'].$tr['Office Manager System'];
// 主要內容 -- content
$tmpl['panelbody_content']				= $container_content;


// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/login.tmpl.php");

?>