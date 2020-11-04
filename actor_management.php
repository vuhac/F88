<?php
// ----------------------------------------------------------------------------
// Features:	後台--系統管理/角色管理
// File Name:	actor_management.php
// Author:		yaoyuan
// Editor:    Damocles
// Related:
//    actor_management_view.php  actor_management_action.php
//    DB table: site_actor_permission
//    actor_management：建立角色，並選擇檔案
// Log:
// ----------------------------------------------------------------------------

@session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";

// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";

// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// 傳到view所需引入函式
require_once dirname(__FILE__) ."/lib_view.php";
require_once dirname(__FILE__) ."/actor_management_lib.php";
// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// 測試模式 (2019/09/27 By Damocles)
if( (!isset($_SESSION['debug_mode'])) || (!$_SESSION['debug_mode']) ){
    // 只有維運角色可以瀏覽此頁面(站長、客服都不可以)
    if( (!$is_ops) || ( ($_SESSION['agent']->therole != 'R') || (!in_array($_SESSION['agent']->account, $su['ops'])) ) ){
        $logger = $tr['You have no permission to manage the role function'];
        echo <<<HTML
            <script>
                alert("{$logger}");
                history.go(-1);
            </script>
        HTML;
        exit();
    }
}


/* if( isset($_SESSION['account_management']['search_detail']->account) || isset($_SESSION['account_management']['search_detail']->account_status) ){
    unset( $_SESSION['account_management']['search_detail']->account );
    unset( $_SESSION['account_management']['search_detail']->account_status );
} */

// render view
$function_title = $tr['role managment'];

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_title']= $function_title.'-'.$tr['host_name'];

return render(
    __DIR__ . '/actor_management_view.php',
    compact(
        'function_title',
        'su'
    )
);
