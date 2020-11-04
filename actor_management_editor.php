<?php
// -----------------------------------
// Features: 角色管理 - 編輯角色
// File Name: actor_management_opt_editor.php
// Author: Mavis
// Editor: Damocles
// Related:
// DB Table:
// Log:
// -----------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";

// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";

// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// 文字檔
// 傳到view所需引入函式
require_once dirname(__FILE__) ."/lib_view.php";
require_once dirname(__FILE__) ."/actor_management_lib.php";


// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------


// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// debug mode
if ( !isset($_SESSION['debug_mode']) || ($_SESSION['debug_mode'] == false) ) {
    if (!$is_ops) {
        die(<<<HTML
            <script>
                alert("{$tr['You have no permission to edit the role function']}");
            </script>
        HTML);
    }
}


// 判斷是否有權限操作
if ( update_premission( $_SESSION["agent"]->account, $su ) ) {
    // 有傳入function code才可以查詢該function data
    if ( isset($_GET['function_code']) ) {
        $function_name = (string)$_GET['function_code'];
        // 判斷該function是否存在
        if ( isExsitFunction($function_name) ) {
            // 取得該function與其所屬檔案的資料
            $function_datas = queryFunction($function_name);
            if ($function_datas[0] != 1) {
                die('function is not exist.');
            } else {
                $function_datas = $function_datas[1];
            }

            // 裝載function參數
            $function_data = [
                'function_name' => $function_datas->function_name,
                'group_description' => $function_datas->group_description,
                'function_title' => $function_datas->function_title,
                'function_description' => $function_datas->function_description,
                'function_public' => $function_datas->function_public,
                'function_status' => $function_datas->function_status,
                'function_maintain_status' => $function_datas->function_maintain_status
            ];

            // 查詢並裝載該function底下所屬page
            $function_pages = [];
            $pages_belong_function = queryPagesByFunctionName($function_name);
            if ($pages_belong_function[0] > 0) {
                unset($pages_belong_function[0]);
                foreach ($pages_belong_function as $val) {
                    if ( isset($val->page_name) && !empty($val->page_name) && ($val->page_name != null) ) {
                        array_push($function_pages, [
                            'page_name' => $val->page_name
                        ]);
                    }
                }
            }

            // 功能標題，放在標題列及meta
            $function_title = $tr['editor the actor'];

            return render(
                __DIR__ . '/actor_management_editor_view.php',
                compact(
                    'function_title',
                    'function_data',
                    'function_pages'
                )
            );
        } else {
            die('function is not exist.');
        }
    } else {
        die('undefined function code');
    }
} else {
    die('No Premission To Request Data');
}

?>