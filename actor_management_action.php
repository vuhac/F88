<?php
// ----------------------------------------------------------------------------
// Features:	後台--系統管理/角色管理
// File Name:	actor_management_action.php
// Author: yaoyuan
// Editor: Damocles
// Related:
//    actor_management_view.php  actor_management_action.php
//    DB table:
// Log:
// ----------------------------------------------------------------------------

@session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";

// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";

// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// 自訂函式庫
require_once dirname(__FILE__) . "/lib_common.php";

require_once dirname(__FILE__) . "/actor_management_lib.php";
// ----------------------------------------------------------------------------

// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// 判斷是否有權限操作
if( update_premission( $_SESSION["agent"]->account, $su ) ){
    $data_cloumn = [
        'id', // orderable false
        'site_functions.function_name',
        'site_functions.function_title',
        'site_page_group.group_description',
        'function_public',
        'function_status',
        'function_maintain_status',
        'updated_at'
    ]; // 用來排序回傳的欄位資料

    // 有傳來篩選條件
    if( isset($_POST['search']) && !empty($_POST['search']['value']) ){
        $post_search_data = json_decode( $_POST['search']['value'] );

        // 所查詢的帳號狀態跟前次查詢不一樣時，要重置查詢資料顯示起始點。
        if(
            (isset($_SESSION['function_management']['search_detail']->function_status) && ($_SESSION['function_management']['search_detail']->function_status != $post_search_data->function_status)) ||
            (isset($_SESSION['function_management']['search_detail']->function_name) && ($_SESSION['function_management']['search_detail']->function_name != $post_search_data->function_name))
          ){
            $_POST['start'] = 0;
        }

        $_SESSION['function_management']['search_detail'] = $post_search_data;
    }

    // 查詢所有function資料
    $query_function_datas = <<<SQL
        SELECT "site_functions"."function_name",
               "site_page_group"."group_description",
               "site_functions"."function_title",
               "site_functions"."function_description",
               "site_functions"."function_public",
               "site_functions"."function_status",
               "site_functions"."function_maintain_status",
               "site_functions"."updated_at"
        FROM "site_functions"
        LEFT JOIN "site_page_group"
        ON ("site_functions"."group_name" = "site_page_group"."group_name")
    SQL;

    // 列出所有的帳號資料，並且使用它總計數量，用以回傳前端DataTable的頁數
    $all_function_count = runSQLall($query_function_datas, 0);

    // function name搜尋
    if( isset($post_search_data->function_name) && !empty($post_search_data->function_name) ){
        $query_function_datas .= <<<SQL
            WHERE ("site_functions"."function_name" LIKE '%{$post_search_data->function_name}%')
            OR ("site_page_group"."group_description" LIKE '%{$post_search_data->function_name}%')
            OR ("site_functions"."function_title" LIKE '%{$post_search_data->function_name}%')
            OR ("site_functions"."function_description" LIKE '%{$post_search_data->function_name}%')
        SQL;
    }

    // function status搜尋
    if( isset($post_search_data->function_status) && ($post_search_data->function_status != 'all') ){
        if( isset($post_search_data->function_name) && !empty($post_search_data->function_name) ){
            $query_function_datas .= <<<SQL
                AND (site_functions.function_status = '{$post_search_data->function_status}')
            SQL;
        }
        else{
            $query_function_datas .= <<<SQL
                WHERE (function_status = '{$post_search_data->function_status}')
            SQL;
        }
    }

    // 列出所有符合搜尋條件的function資料，並且使用它總計數量，用以回傳前端DataTable的頁數
    $recordsFiltered = runSQLall( $query_function_datas, 0 );

    // 取得該頁數，應該顯示的資料，用以回傳前端DataTable的資料顯示
    $query_function_datas .= <<<SQL
        ORDER BY {$data_cloumn[ $_POST["order"][0]["column"] ]} {$_POST["order"][0]["dir"]}
        LIMIT {$_POST['length']} OFFSET {$_POST['start']};
    SQL;
    $result_query_function_data = runSQLall( $query_function_datas, 0 );
    unset($result_query_function_data[0]); // 所有帳號資料
    $result_data = [];

    // 遍歷上述所取得的資料，修正顯示資料內容。
    foreach( $result_query_function_data as $key=>$val ){
        $data = [
            'id' => $key, // $key這邊從1開始
            'function_code' => $val->function_name,
            'function_name' => $val->function_title,
            'function_group_name' => $val->group_description,
            'public_status' => ( ($val->function_public) ? ( isset($tr['open state-public']) ? $tr['open state-public'] : 'public' ) : ( (!$val->function_public) ? ( isset($tr['open state-protected']) ? $tr['open state-protected'] : 'protected' ) : '' ) ),
            'status' => ( ($val->function_status) ? ( isset($tr['enable']) ? $tr['enable'] : 'enabled' ) : ( ($val->function_status == false) ? ( isset($tr['disable']) ? $tr['disable'] : 'disabled' ) : '' ) ),
            'maintain_status'=>( ($val->function_maintain_status) ? ( isset($tr['page status-open']) ? $tr['page status-open'] : 'open' ) : ( ($val->function_maintain_status == false) ? ( isset($tr['page status-close']) ? $tr['page status-close'] : 'close' ) : '' ) ),
            'updated_at' => date("Y/m/d h:i:s", strtotime($val->updated_at)),
            'operation' => <<<HTML
                <button onclick="location.href='actor_management_editor.php?function_code={$val->function_name}'" class="btn btn-primary">
                    <span class="glyphicon glyphicon-pencil" aria-hidden="true"></span>
                </button>
            HTML
        ];
        array_push($result_data, $data);
    } // end foreach

    $result = [
        'draw' => ($_POST['draw'] ?? 1),
        'recordsTotal' => $all_function_count[0],
        'recordsFiltered' => $recordsFiltered,
        'data' => $result_data
    ];
    echo json_encode($result, JSON_PRETTY_PRINT);
}
else{
    die('No Premission To Request Data');
}

?>