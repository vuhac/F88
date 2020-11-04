<?php
// ----------------------------------------------------------------------------
// Features: 後台 -- 未定義頁面管理
// File Name: page_management_action.php
// Author: Damocles
// Related: page_management_*.php
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

$data_cloumn = [
    'num',
    'site_page.page_name',
    'site_functions.function_title',
    'site_page_group.group_description',
    'site_page.page_description',
    'edit'
]; // 用來排序回傳的欄位資料

// 參數處理
$draw = ( isset($_GET['draw']) ? (int)$_GET['draw'] : 1 );
$search = '';
$function_search = '';
if ( isset($_GET['search']['value']) && !empty($_GET['search']['value']) ) {
    $attr = json_decode($_GET['search']['value']);
    $search = $attr->form_search;
    $function_search = $attr->function_search;
}
$start = ( isset($_GET['start']) ? (string)$_GET['start'] : 0 );
$length = ( isset($_GET['length']) ? (string)$_GET['length'] : 10 );
$order_column = ( isset($_GET['order'][0]['column']) ? (string)$data_cloumn[ $_GET['order'][0]['column'] ] : (string)$data_cloumn[1] );
$order_dir = ( isset($_GET['order'][0]['dir']) ? (string)$_GET['order'][0]['dir'] : 'desc' );

// 取得符合條件的總數
$row_count = queryPages($search, $function_search, 0, NULL)[0];

// 取得資料
$data = queryPages($search, $function_search, $start, $length, $order_column, $order_dir);
unset($data[0]);

// 對資料做加工(加上序號、編輯按鈕、重組)
$num = $start;
$result = [];
foreach ($data as $key=>$val) {
    $num++;
    $page_name = explode(".", $val->page_name)[0];
    array_push($result, [
        'num' => $num,
        'page_name' => $val->page_name,
        'function_name' => $val->function_name,
        'function_title' => $val->function_title,
        'group_name' => $val->group_name,
        'page_description' => ( empty($val->page_description) ? '----' : $val->page_description ),
        'edit' => <<<HTML
            <a href="page_management_detail.php?page_name={$page_name}&function_name={$val->function_name}">
                <button class="btn btn-primary">
                    <span class="glyphicon glyphicon-pencil" aria-hidden="true"></span>
                </button>
            </a>
        HTML
    ]);
}

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $row_count,
    'recordsFiltered' => $row_count,
    'data' => $result
], JSON_PRETTY_PRINT);

?>