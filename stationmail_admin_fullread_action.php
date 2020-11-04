<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 針對 stationmail_admin_fullread.php 的動作執行對應行為。
// File Name:	stationmail_admin_fullread_action.php
// Author:		Yuan
// Related:   服務 stationmail_admin_fullread.php 檔案
// Table:       root_stationmail
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";


if(isset($_GET['a'])) {
    $action = $_GET['a'];
} else {
    die('(x)不合法的測試');
}
//var_dump($_SESSION);
// var_dump($_POST);
//var_dump($_GET);

if($action == 'delete_mail' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

//    var_dump($_POST);

    /**
     * 刪除信件
     *
     * status :
     * 0=del
     * 1=ok
     * null=no define
     */
    // 使用者預設客服帳號
    $user = 'gpk';

//    var_dump($_POST);
    $mail_id = filter_var($_POST['mail_id'], FILTER_SANITIZE_STRING);
    $mail_from = filter_var($_POST['mail_from'], FILTER_SANITIZE_STRING);
    $mail_to = filter_var($_POST['mail_to'], FILTER_SANITIZE_STRING);
    $mail_status = filter_var($_POST['status'], FILTER_SANITIZE_STRING);
    $who_call = filter_var($_POST['who_call'], FILTER_SANITIZE_STRING);

    /**
     * 檢查收件者是否為該使用者
     * 要一致才可以刪除信件
     */
    if ($mail_to == $user) {
        /**
                * 檢查該信件是否從收件匣或寄件備份被刪除
                * 寄件備份只有單一訊息
                * 主要是檢查收件匣
                */
        if ($mail_status == '0') {
            echo "<script>alert('該信件已不存在收件匣！');</script>";
        } else {
            /**

                        */
            $mailstatus_del_sql = ($who_call == 'inbox') ? "UPDATE root_stationmail SET status = '0' WHERE id = '$mail_id' AND msgto = '$mail_to' AND msgfrom = '$mail_from'" : "UPDATE root_stationmail SET status = '0' WHERE id = '$mail_id' AND msgfrom = '$mail_to' AND msgto  = '$mail_from'";
//            $mailstatus_del_sql = "UPDATE root_stationmail SET status = '0' WHERE id = '$mail_id' AND msgto = '$mail_to' AND msgfrom = '$mail_from'";
//        var_dump($mailstatus_del_sql);
            $mailstatus_del_sql_result = runSQLall($mailstatus_del_sql);
//        var_dump($mailstatus_del_sql_result);
            if ($mailstatus_del_sql_result['0'] > 0) {
//                $del_action = ($who_call == 'inbox') ? '<script>location.reload();</script>' : '<script>window.location.assign("stationmail_admin.php");</script>';
                $del_action = '<script>window.location.assign("stationmail_admin.php");</script>';
            } else {
                $del_action = "<script>alert('信件刪除失敗！');</script>";
            }
            echo $del_action;

        }
    } else {
        echo "<script>alert('信件刪除失敗！');</script>";
        die();
    }

} elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
    var_dump($_POST);
    echo 'ERROR';

}



?>
