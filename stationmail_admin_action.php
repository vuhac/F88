<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 站內信件管理，主要針對會員，客服回應訊息。及訊息查詢。針對 stationmail_admin.php 的動作執行對應行為。
// File Name:	stationmail_admin_action.php
// Author:		Yuan
// Related:  服務 stationmail_admin.php檔案
// Table:       root_stationmail
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/stationmail_lib.php";

if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
}else{
    die('(x)不合法的測試');
}
// var_dump($_SESSION);
// var_dump($_POST);
// var_dump($_GET);

if($action == 'stationmail_send' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
    // var_dump($_POST);

    // 信件內容
    $send_message_text = filter_var($_POST['send_message_text'], FILTER_SANITIZE_STRING);
    // 主旨
    $send_subject_text = filter_var($_POST['send_subject_text'], FILTER_SANITIZE_STRING);
    // 收件者
    $sendto_system_cs = filter_var($_POST['sendto_system'], FILTER_SANITIZE_STRING);
    // 寄件者預設客服帳號
    $sendfrom = $stationmail['sendto_system_cs'];


    // 呼叫寄信 function
    $send_mail_result = (object)send_mail($sendto_system_cs, $sendfrom, $send_subject_text, $send_message_text);

    if ($send_mail_result->status) {
        echo '<script>alert("'.$send_mail_result->result.'");location.reload();</script>';
    } else {
        echo '<script>alert("'.$send_mail_result->result.'");</script>';
    }

// ----------------------------------------------------------------------------
}elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
    var_dump($_POST);

}



?>
