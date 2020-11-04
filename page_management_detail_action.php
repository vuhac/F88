<?php
// ----------------------------------------------------------------------------
// Features: 後台 -- 未定義頁面管理
// File Name: page_management_detail_action.php
// Author: Damocles
// Related: page_management_detail_*.php
// Log:
// ----------------------------------------------------------------------------
// 自訂函式庫

@session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";

// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";

// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// 自訂函式庫
require_once dirname(__FILE__) . "/lib_common.php";

require_once dirname(__FILE__) ."/page_management_lib.php";
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

$required_columns = [
    'page_name',
    'function_name',
    'page_description'
];
foreach ($required_columns as $val) { // 檢查必填欄位是否都有傳值
    if ( !isset($_POST[$val]) ) { // 缺少必填參數
        echo json_encode([
            'status' => 'fail',
            'title' => 'Missing Required Attribute, Please try again later.',
            'content' => ''
        ]);
    }
}
$page_data = [
    'page_name' => (string)$_POST['page_name'],
    'function_name' => [
        'old' => (string)$_POST['function_name']['old'],
        'new' => (string)$_POST['function_name']['new']
    ], 'page_description'=> (string)trim($_POST['page_description'])
];
$update_row_couunt = updatePage($page_data);
echo json_encode([
    'status' => ( ($update_row_couunt == 1) ? 'success' : 'fail' ),
    'title' => ( ($update_row_couunt == 1) ? 'Update Successed' : 'Update Failed, Please try again later.' ),
    'content' => ''
]);
// echo '<pre>', var_dump( $page_data ), '</pre>'; exit();
?>