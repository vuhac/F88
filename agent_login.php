<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 系統後台 login 登入頁面，專門給代理商或管理人員使用的後台。
// File Name:	agent_login.php
// Author:		Barkley
// Related:
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// ----------------------------------------------------------------------------
// 特別的 CAPTCHA 為了註冊專屬的 CAPTCHA , 和系統原生的不一樣。
// ref: https://labs.abeautifulsite.net/simple-php-captcha/
require_once dirname(__FILE__) ."/in/phpcaptcha/simple-php-captcha.php";
$_SESSION['captcha'] = simple_php_captcha();
$_SESSION['captcha']['errorcount'] = 0;

//var_dump($_SESSION);
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title = $config['hostname'].$tr['Office Manager System'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// ----------------------------------------------------------------------------
// 程式碼從這裡下面開始編寫
// ----------------------------------------------------------------------------

// 綁定網域的功能 -- todo
// server_port 允許連線的 port , JSON 格式陣列
// http_host 允許的 domain name , JSON 格式陣列
// bypass  true跳過不檢查
// function domain_limits(server_port, http_host, bypass )

/*
// IP 白名單 -- 使用方式, 填入 NULL 代表不使用 , 放在 agent_permission() 就可以限制所有頁面.
$client_ip = explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])['0'];
$ipaddress_range_json = '{"upper":"10.22.0.0","lower":"10.22.255.255"}';
$allowip_json = '["114.33.201.242","140.117.72.20","59.127.186.209"]';
if(ip_limits($client_ip, $allowip_json, $ipaddress_range_json) == false) {
	echo 'Your IP is not allowed in the list';
	die();
}
*/



// 因為同網域使用的關係, 如果發現 $_SESSION['member'] 存在 session 內, 提示使用者清除後再登入 by mtchang 2017.9.3
if(isset($_SESSION['member'])) {
  // 點選清除 session
	$login_icon_html = '
		<div class="form-signin-icon">
			<span class="glyphicon glyphicon-off" onClick="location.href='."'agent_login_action.php?a=logout'".'"></span>
		</div>
	';
	$title_html = '<div align="center"><a href="agent_login_action.php?a=logout" class="btn btn-danger" role="button">'.$tr['Front desk, background at the same time in the same domain login'].'</a></div>';
	$login_block_html = '';
	$agent_login_js_html = '';
}else{
	// 沒有的話, 正常處理

	// GPK2 DEMO廳主管端系统
	$title_html = $config['hostname'].'&nbsp;'.$tr['Office Manager System'];;

	// 登入的大頭 , 點選清除 session
	$login_icon_html = '
		<div class="form-signin-icon">
			<span class="glyphicon glyphicon-knight" onClick="location.href='."'agent_login_action.php?a=logout'".'"></span></a>
		</div>
	';

	// 如果已經登入的話, 就顯示目前登入的使用者資訊，引導登入。
	if(!isset($_SESSION['agent'])) {


		$login_block_html = '
				<div class="form-group">
					<div class="input-group">
						<div class="input-group-addon"><span class="glyphicon glyphicon-user" aria-hidden="true"></span></div>
						<input type="text" class="form-control" id="account_input" placeholder="Account" required>
					</div>
				</div>

				<div class="form-group">
					<div class="input-group">
						<div class="input-group-addon"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span></div>
						<input type="password" id="password_input"  class="form-control" placeholder="Password" required>
					</div>
				</div>

				<div class="form-group">
					<div class="input-group">
						<div class="input-group-addon"><span class="glyphicon glyphicon-eye-close" aria-hidden="true"></span></div>
						<input type="text" class="form-control" id="captcha" placeholder="'.$tr['Verification'].'"  required>
					</div>
				</div>
			';

			// 登入 captcha
			$login_block_html = $login_block_html.'
				<div class="form-group" id="showlogin_captcha" >
					<span class= "showlogin_captcha"><img src="'.$_SESSION['captcha']['image_src'].'" id="showlogin_captcha_image" /></span>
					<a class="btn btn-primary btn-md" href="agent_login.php" role="button"><span id="captcha_refresh" class="glyphicon glyphicon-refresh" aria-hidden="true"></span></a>
				</div>
			';

			$login_block_html = $login_block_html.'
				<p><input type="checkbox" id="login_force" value="1">'.$tr['Delete existing users forced to sign.'].'</p>
				<div id="preview_status"></div>
				<p>	<button id="submit_to_login" class="btn btn-primary btn-block" type="submit">'.$tr['Login'].' </button></p>
				';

		// --------------------------------------
		// 登入的表單, JS 動作 , 按下 submit_to_login 透過 jquery 送出 post data 到 url 位址
		$agent_login_js = "
		$('#submit_to_login').click(function(){
			if($('#account_input').val()=='') {
				alert('请输入帐号')

			}else if($('#password_input').val()=='') {
				alert('请输入密码')
			}else if($('#captcha').val()=='') {
				alert('请输入验证码')
			}

			if($('#account_input').val()!='' && $('#password_input').val()!='' && $('#captcha').val()!='')
			{
				var account_input  = $('#account_input').val();
				var password_input  = $().crypt({method:'sha1', source:$('#password_input').val()});
				var captcha_input = $('#captcha').val();
				if($('#login_force').prop('checked')) {
					var login_force = 1;
				}else{
					var login_force = 0;
				}
				$.post('agent_login_action.php?a=login_check',
					{
						account: account_input, password: password_input, captcha: captcha_input, login_force: login_force
					},
					function(result){
						$('#preview_status').html(result);}
				);
			}
		});

		$('#captcha').focus(function () {
		   $('#showlogin_captcha').slideDown('slow');
		});
		";

		// 按下 enter 後,等於 click 登入按鍵
		$agent_login_keypress_js = '
		$(function() {
			 $(document).keyup(function(e) {
				switch(e.which) {
						case 13: // enter key
								$("#submit_to_login").trigger("click");
						break;
				}
		});
		});
		';

		// 加密的 jquery lib http://www.itsyndicate.ca/jquery/
		// 在登入用的 JS
		$agent_login_js_html = "
		<script>
			$(document).ready(function() {
				$('#showlogin_captcha').hide();
				".$agent_login_js."
			});
			".$agent_login_keypress_js."
		</script>
		";
		// --------------------------------------

	}else{
		// 你已经登入系统了，点选底下可以登出。
		$login_block_html = '
		<div class="form-group">
			<div class="input-group">
				<div class="input-group-addon"><span class="glyphicon glyphicon-user" aria-hidden="true"></span></div>
				<input type="text" class="form-control" id="account_input"  value="'.$_SESSION['agent']->account.'" placeholder="Account" disabled>
			</div>
		</div>

		<div class="form-group">
			<div class="input-group">
				<div class="input-group-addon"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span></div>
				<input type="password" id="password_input"  class="form-control" value="********" placeholder="Password" disabled>
			</div>
		</div>

		<div id="preview_status">'.$tr['You have logged into the system, click to log out.'].'</div>
		<p align="right">
			<a class="btn btn-light" href="home.php" >'.$tr['Login'].'</a>
			<a class="btn btn-danger" href="agent_login_action.php?a=logout" >'.$tr['Logout'].'</a>
		</p>
		';

		$agent_login_js_html = '';
	}
	// ----------------------------------------------------------------------------
}
// end of 檢查重複 session


// ----------------------------------------------------------------------------
// 帆布指紋偵測機制 , 可以識別訪客的瀏覽器唯一值。
// ref: http://blog.jangmt.com/2017/03/canvas-fingerprinting.html
// ----------------------------------------------------------------------------
$fingerprintsession_html = '<iframe name="print" frameborder="0" src="fingerprintsession.php" height="0px" width="100%" scrolling="no">
  <p>Your browser does not support iframes.</p>
</iframe>';
// 指紋偵測 iframe
// ----------------------------------------------------------------------------


// GPK2 DEMO廳主管端系统
$title = $config['hostname'].'&nbsp;'.$tr['Office Manager System'];;

// 排版顯示
$container_content = '
	<br><br><br>
	<div class="row">
		<div class="col-12 col-md-4"></div>
		<div class="col-12 col-md-4">
			'.$login_icon_html.'
			<div class="login_title_text">'.$title_html.'</div>
		</div>
		<div class="col-12 col-md-4"></div>
	</div>

	<div class="row">
		<div class="col-12 col-md-4"></div>
		<div class="col-12 col-md-4">
			'.$login_block_html.$agent_login_js_html.$fingerprintsession_html.'
		</div>
		<div class="col-12 col-md-4"></div>
	</div>
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
