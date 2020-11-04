<?php
// ----------------------------------------------------------------------------
// Features:	後台--子帳號管理/管理員管理
// File Name:	admin_management.php
// Author:		yaoyuan
// Editor: Damocles
// Related:
//    系統主程式：admin_management.php
//    主程式樣版：admin_management_view.php
//    主程式action：admin_management_action.php
//    新增管理員：admin_management_add.php
//    修改管理員：admin_management_edit.php
//    DB table: root_member
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

// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();

// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();

// 測試模式 (2019/09/27 By Damocles)
// echo '<pre>',var_dump( $_SESSION['debug_mode'] ), '</pre>'; exit();
if( !isset($_SESSION['debug_mode']) || ($_SESSION['debug_mode']==false) ){
    if( !( isset($_SESSION['agent']) && ($_SESSION['agent']->therole=='R') && in_array($_SESSION['agent']->account, $su['superuser']) ) ){
        $logger = $tr['You do not have permission to view the sub-account function!'];
        echo <<<HTML
            <script>
                alert('{$logger}');
                history.go(-1);
            </script>';
        HTML;
        die();
    }
}

// 切換子帳號管理與權限管理之間，要重置彼此當時的搜尋紀錄。
/* if( isset($_SESSION['function_management']['search_detail']->function_name) || isset($_SESSION['function_management']['search_detail']->function_status) ){
    unset( $_SESSION['function_management']['search_detail']->function_name );
    unset( $_SESSION['function_management']['search_detail']->function_status );
} */

// render view
$function_title = $tr['sub-account management'];

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_title'] = $function_title.'-'.$tr['host_name'];

return render(
    __DIR__.'/admin_management_view.php', compact(
        'function_title',
        'su'
    )
);
?>