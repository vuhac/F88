<?php
// ----------------------------------------------------------------------------
// Features: 後台 -- 未定義頁面管理
// File Name: page_management.php
// Author: Damocles
// Related: page_management_*.php
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

// 取得所有function的資料
$function_datas = queryFunctions();

return render(
    'page_management_view.php', compact('function_datas')
);
?>