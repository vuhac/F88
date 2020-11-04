<?php
// ----------------------------------------------------------------------------
// Features: 後台 -- 未定義頁面管理
// File Name: page_management_detail.php
// Author: Damocles
// Related: page_management_detail*.php
// Log:
// ----------------------------------------------------------------------------
session_start();
// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
require_once dirname(__FILE__) ."/lib_view.php";
require_once dirname(__FILE__) ."/page_management_lib.php";
// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// 以檔案名稱與所屬function名稱來搜尋 (一個檔案可能被多個function使用，所以要指定function)
if ( isset($_GET['page_name']) && isset($_GET['function_name']) ) {
    // 取得指定page的資料
    $page_name = (string)$_GET['page_name'];
    $function_name = (string)$_GET['function_name'];
    $page_data = queryPage($page_name, $function_name);

    if ($page_data[0] == 1) {
        $page_data = $page_data[1];
        // echo '<pre>', var_dump($page_data), '</pre>'; exit();
        $function_datas = queryFunctions();
        return render(
            'page_management_detail_view.php', compact('page_data', 'function_datas')
        );
    } else { // 沒有找到page
        die( <<<HTML
            <script>
                alert('No Found Function Name, Please try again later.');
                history.go(-1);
            </script>
        HTML );
    }
} else { // 沒有指定頁面參數
    die( <<<HTML
        <script>
            alert('No Page Name Or Function Name Input, Please try again later.');
            location.replace('page_management.php');
        </script>
    HTML );
}
?>