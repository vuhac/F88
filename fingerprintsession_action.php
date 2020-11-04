<?php
// --------------------------
// Features:	 收集產生瀏覽器的指紋碼，及遠端的 remote IP
// File Name: fingerprintsession_action.php
// Author:	   Barkley
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

//var_dump($_POST);

if(isset($_POST['k'])) {
	$key_sha1 = $_POST['k'];
}else{
	$key_sha1 = NULL;
}
if(isset($_POST['fingertracker'])) {
	$fingertracker = $_POST['fingertracker'];
}else{
	$fingertracker = NULL;
}
//var_dump($fingertracker);

// 使用 key 來驗證，使用者的行為是否合法
$key_valid = gmdate('Y-m-d H:i A');;
$key_valid_sha1 = sha1($key_valid);

// 如果 key 正確的話，才更新 fingertracker 資訊
if($key_sha1 == $key_valid_sha1) {
	// 註冊指紋資訊
	$_SESSION['fingertracker'] = $fingertracker;
	$_SESSION['fingertracker_remote_addr'] = explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])['0'];
	//$_SESSION['fingertracker_server_var'] = json_encode($_SERVER);
}else{
	// ERROR
	$_SESSION['fingertracker'] = 'error';
	$_SESSION['fingertracker_remote_addr'] = explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])['0'];
	//$_SESSION['fingertracker_server_var'] = json_encode($_SERVER);
}


//var_dump($_SESSION);
?>
