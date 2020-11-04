<?php
// --------------------------
// Features:	 收集產生瀏覽器的指紋碼，及遠端的 remote IP
// File Name: fingerprintsession.php
// Author:	 Barkley
// Related:   需要 jquery and fingerprintjs 的檔案
// ref: http://blog.jangmt.com/2017/03/audio-fingerprinting-web-tracking.html
// ref: http://blog.jangmt.com/2017/03/canvas-fingerprinting.html
// --------------------------
// Usage:
//  2017.3.10
//  將這個 code 嵌入你的每一個 php 程式內，當使用者瀏覽你的網頁時，會有三個 session 值寫入。
/*
// 錯誤的案例
array (size=3)
  'fingertracker' => string 'error' (length=5)
  'fingertracker_remote_addr' => string '114.33.201.242' (length=14)
  'fingertracker_server_var' => string '{"UNIQUE_ID":"WMJQUVTDmHnMyapJVHOrkQAA

// 正確的案例
array (size=3)
  'fingertracker' => string '1280356980' (length=10)
  'fingertracker_remote_addr' => string '114.33.201.242' (length=14)
  'fingertracker_server_var' => string '{"UNIQUE_
*/

// 錯誤的寫入，fingertracker 會有 error 的字串
// 目前已知問題： key 驗證依據時間產生，有可能會在 59-00 秒轉換分鐘時失敗，失敗關閉瀏覽器重新連線就可以。
// 中間最長會有 60 秒的時間，讓使用者放入自己的驗證碼。可以縮短，但目前還不需要改寫。
// --------------------------
session_start();


// 只會紀錄一次，除非重開 browser 但是重開後，同 broswer finger print 指紋碼是一樣的。
//var_dump($_SESSION);

if(isset($_SESSION['fingertracker']) AND $_SESSION['fingertracker'] == NULL AND isset($_SESSION['fingertracker_remote_addr']) AND isset($_SESSION['fingertracker_remote_addr'])) {
// no script register
	//echo 'only IP. NO javascript';
	$_SESSION['javascript_status'] = false;
	//var_dump($_SESSION);

}elseif(isset($_SESSION['fingertracker']) AND $_SESSION['fingertracker'] != NULL AND isset($_SESSION['fingertracker_remote_addr']) AND isset($_SESSION['fingertracker_remote_addr'])) {
	//echo 'Finger and IP BOTH';
	$_SESSION['javascript_status'] = true;
	//var_dump($_SESSION);

}else{
	// 第一次登入 , 紀錄 fingerprint == NULL
	$_SESSION['fingertracker'] = NULL;
	// 第一次登入 , 紀錄 $_SERVER['HTTP_X_FORWARDED_FOR']
	$_SESSION['fingertracker_remote_addr'] = explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])['0'];
	$_SESSION['fingertracker_server_var'] = json_encode($_SERVER);

	// 設定 key , 避免被使用者強行注入，透過手動增加, 一分鐘後失效。
	$key = gmdate('Y-m-d H:i A');
	$key_sha1 = sha1($key);
	//var_dump($key);

	// <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
	$html = '
  <script src="in/fingerprint.js"></script>
	<script src="in/jquery/jquery.min.js"></script>

	<div id="fingertracker"></div>
	<script>
	$(document).ready(function(){
		var fp = new Fingerprint();
		var fingertracker = fp.get();
		var key_sha1	= "'.$key_sha1.'";

		$.post("fingerprintsession_action.php", {fingertracker: fingertracker, k: key_sha1 }, function(result){
				$("#fingertracker").html(result);
		});

	})
	</script>
	<noscript>Your browser does not support JavaScript!</noscript>
	';

	echo $html;
	//var_dump($_SESSION);
}
//var_dump($_SESSION);


?>
