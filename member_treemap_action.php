<?php
// ----------------------------------------------------------------------------
// Features: 後台--
// File Name: member_treemap_action.php
// Author: Unknow
// Editor: Damocles
// Last Edited Date: 2020/05/14
// Related:
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 專用處理會員樹的函式
require_once dirname(__FILE__) ."/member_treemap_lib.php";


// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();

// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();


// 過往時間計算function
// ref:http://qiita.com/wgkoro@github/items/eee4e6854535d62ca55b
function convert_to_fuzzy_time($times)
{
    date_default_timezone_set('America/St_Thomas');
    $unix   = strtotime($times);
    $now    = time();
    $diff_sec   = $now - $unix;

    if ($diff_sec < 60) {
        $time   = $diff_sec;
        $unit   = "秒前";
    } else if ($diff_sec < 3600) {
        $time   = $diff_sec/60;
        $unit   = "分前";
    } else if($diff_sec < 86400) {
        $time   = $diff_sec/3600;
        $unit   = "小時前";
    } else if($diff_sec < 2764800) {
        $time   = $diff_sec/86400;
        $unit   = "天前";
    } else if($diff_sec < 31536000) {
        $time   = $diff_sec/2592000;
        $unit   = "个月前";
    } else {
        $time   = $diff_sec/31536000;
        $unit   = "年前";
    }

    return (int)$time .$unit;
}


// GET / POST 傳值處理
if ( isset($_GET['id']) && ($_GET['id'] != NULL) && (trim($_GET['id']) != '') ) {
    $query['id'] = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
} else {
    $query['id'] = NULL;
    die("No parameter 'id'.");
}


// datatable server process 分頁處理及驗證參數
// 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
if ( isset($_GET['length']) && ($_GET['length'] != NULL) && (trim($_GET['length']) != '') ) {
    $current_per_size = filter_var($_GET['length'], FILTER_VALIDATE_INT);
} else {
    $current_per_size = $page_config['datatables_pagelength'];
    $current_per_size = 500;
}

// 起始頁面, 搭配 current_per_size 決定起始點位置
if ( isset($_GET['start']) && ($_GET['start'] != NULL) && (trim($_GET['start']) != '') ) {
    $current_page_no = filter_var($_GET['start'],FILTER_VALIDATE_INT);
} else {
    $current_page_no = 0;
}

// datatable 回傳驗證用參數，收到後不處理直接跟資料一起回傳給 datatable 做驗證
if ( isset($_GET['_']) ) {
    $secho = $_GET['_'];
} else {
    $secho = '1';
}


// datatable server process 用資料讀取
if ( isset($_SESSION['agent']) && ($_SESSION['agent']->therole == 'R') ) {
    // 列出所有的會員資料及人數 SQL
    // 處理 datatables 傳來的排序需求
    $sql_order = 'ORDER BY id ASC'; // 預設值
    if ( isset($_GET['order'][0]) && ($_GET['order'][0]['column'] != '') ) {
        $sql_order_dir = ( ( strtolower($_GET['order'][0]['dir']) == 'asc' ) ? 'ASC' : 'DESC' );

        $order_column = [
            0 => 'id',
            2 => 'account',
            3 => 'enrollmentdate',
            4 => 'nickname',
            6 => 'depth',
            7 => 'parent_id_count'
        ];
        if ( isset($_GET['order'][0]['column']) && isset($order_column[ $_GET['order'][0]['column'] ]) ) {
            $order = $order_column[ $_GET['order'][0]['column'] ];
            $sql_order = "ORDER BY {$order} {$sql_order_dir}";
        }
    }

    // 遞迴查詢的 SQL , 有深度的參數
    $agent_id = $query['id'];
    $depth = 4;
    $sql_tmp = <<<SQL
        WITH RECURSIVE "upperlayer"("id", "parent_id", "account", "therole", "enrollmentdate", "nickname", "favorablerule", "grade", "favorablerule", "feedbackinfo", "status", "depth") AS (
            SELECT "id", "parent_id", "account", "therole", "enrollmentdate", "nickname", "favorablerule", "grade", "favorablerule", "feedbackinfo", "status", 1
            FROM "root_member"
            WHERE ("parent_id" = {$agent_id})
            UNION ALL
            SELECT "p"."id", "p"."parent_id", "p"."account", "p"."therole", "p"."enrollmentdate", "p"."nickname", "p"."favorablerule", "p"."grade", "p"."favorablerule", "p"."feedbackinfo", "p"."status", "u"."depth"+1
            FROM "root_member" AS "p"
            INNER JOIN "upperlayer" AS "u"
            ON ("u"."id" = "p"."parent_id")
            WHERE ("u"."depth" < {$depth})
        ), "agent_tree_reuslt" AS (
            SELECT *
            FROM "upperlayer" AS "agent_tree"
            LEFT JOIN (
                SELECT  "parent_id", count("parent_id") as "parent_id_count"
                FROM "root_member"
                GROUP BY "parent_id"
            ) AS "agent_user_count"
            ON ("agent_tree"."id" = "agent_user_count"."parent_id")
            ORDER BY "agent_tree"."parent_id", "agent_tree"."depth", "agent_tree"."id"
        )
        SELECT *
        FROM "agent_tree_reuslt" {$sql_order}
    SQL;
    $userlist_sql = $sql_tmp.';'; // 算資料總數
    $userlist_count = runSQL($userlist_sql, 0, 'r');


    // 分頁處理機制
    $page['all_records'] = $userlist_count; // 所有紀錄數量
    $page['per_size'] = $current_per_size; // 每頁顯示多少
    $page['no'] = $current_page_no; // 目前所在頁數


    // 取出資料
    $userlist_sql = <<<SQL
        {$sql_tmp}
        OFFSET {$page['no']}
        LIMIT {$page['per_size']};
    SQL;
    $userlist = runSQLall($userlist_sql, 0, 'r');


    // 存放列表的 html -- 表格 row -- tables DATA
    $show_listrow_html = '';

    // 判斷 root_member count 數量大於 1
    if ($userlist[0] >= 1) {
        // 以會員為主要 key 依序列出每個會員的貢獻金額
        for ($i=1; $i<=$userlist[0]; $i++) {
            $count = $page['no'] + $i;
            $b['id'] = $userlist[$i]->id;

            if ($userlist[$i]->therole == "M") {
                $b['therole'] = '会员';
            } else if ($userlist[$i]->therole == "A") {
                $b['therole'] = '代理商';
            } else if ($userlist[$i]->therole == "R") {
                $b['therole'] = '管理员';
            } else {
                $b['therole'] = '未定义身分';
            }

            $b['account'] = $userlist[$i]->account;
            $b['enrollmentdate'] = date("Y-m-d H:m:s", strtotime($userlist[$i]->enrollmentdate)).'(约'.convert_to_fuzzy_time($userlist[$i]->enrollmentdate).')';
            $b['nickname'] = $userlist[$i]->nickname;

            if ($userlist[$i]->status == '1') {
                $b['status'] = '启用';
            } else if ($userlist[$i]->status == '0') {
                $b['status'] = '停用';
            } else if ($userlist[$i]->status == '2') {
                $b['status'] = '钱包冻结';
            } else if ($userlist[$i]->status == '3') {
                $b['status'] = '暂时封锁';
            } else if ($userlist[$i]->status == '4') {
                $b['status'] = '测试帐号';
            } else {
                $b['status'] = '未定义状态';
            }

            $b['depth'] = $userlist[$i]->depth;
            $b['parent_id_count'] = $userlist[$i]->parent_id_count;

            // 顯示的表格資料內容
            $show_listrow_array[] = array(
                'id' => $b['id'],
                'therole' => $b['therole'],
                'account' => $b['account'],
                'enrollmentdate' => $b['enrollmentdate'],
                'nickname' => $b['nickname'],
                'status' => $b['status'],
                'depth' => $b['depth'],
                'parent_id_count' => $b['parent_id_count']);
        }

        $output = array(
            "sEcho" => intval($secho),
            "iTotalRecords" => intval($page['per_size']),
            "iTotalDisplayRecords" => intval($page['all_records']),
            "data" => $show_listrow_array
        );
    } else {
        // NO member
        $output = array(
            "sEcho" => 0,
            "iTotalRecords" => 0,
            "iTotalDisplayRecords" => 0,
            "data" => ''
        );
    }
} else {
    // NO member
    $output = array(
        "sEcho" => 0,
        "iTotalRecords" => 0,
        "iTotalDisplayRecords" => 0,
        "data" => ''
    );
}

echo json_encode($output);

?>
