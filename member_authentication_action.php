<?php
// ----------------------------------------------------------------------------
// Features:    後台--站長工具--帳號驗證管理
// File Name:    member_authentication_action.php
// Author:        yaoyuan
// Related:
//    系統主程式：member_authentication.php
//    主程式樣版：member_authentication_view.php
//    主程式action：member_authentication_action.php
//    DB table: root_member_authentication
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib_common.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// ---------------------------------------------------------------
// check date format
// ---------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}
// -----------------------------------------

function convert_to_fuzzy_time($times)
{
    global $tr;
    date_default_timezone_set('America/St_Thomas');
    $unix     = strtotime($times);
    $now      = time();
    $diff_sec = $now - $unix;

    if ($diff_sec < 60) {$time = $diff_sec;$unit = $tr['Seconds ago'];
    } elseif ($diff_sec < 3600)     {$time = $diff_sec / 60;$unit = $tr['minutes ago'];
    } elseif ($diff_sec < 86400)    {$time = $diff_sec / 3600;$unit = $tr['hours ago'];
    } elseif ($diff_sec < 2764800)  {$time = $diff_sec / 86400;$unit = $tr['days ago'];
    } elseif ($diff_sec < 31536000) {$time = $diff_sec / 2592000;$unit = $tr['months ago'];
    } else                          {$time = $diff_sec / 31536000;$unit = $tr['years ago'];}
    return (int) $time . $unit;
}

// 產生查詢條件
function query_str($query_sql_array)
{
    global $tr, $su;
    $query_top      = 0;
    $show_query_sql = '';
    //檢查管理員帳號存在
    if (isset($query_sql_array['account']) and $query_sql_array['account'] != null and trim($query_sql_array['account']) != '') {
       
        $account_query_sql = <<<SQL
        SELECT au.id, mb.account FROM root_member_authentication as au
        LEFT JOIN root_member as mb
        ON au.id = mb.id
        WHERE account LIKE '%{$query_sql_array['account']}%'
        AND au.id NOT IN('1','2','3');
SQL;
        $account_query_result = runSQL($account_query_sql);
        if (isset($account_query_result) and $account_query_result >= 1) {
            $show_query_sql .= 'AND account like \'%' . $query_sql_array['account'] . '%\' ';
            $query_top = 1;
        } else { 
            $logger = '无此帐号验证资讯!';
        }
    }
        
    //檢查角色名稱或代碼
    if (isset($query_sql_array['id']) and $query_sql_array['id'] != null and trim($query_sql_array['id']) != '') {
        // if($query_top == 1){$show_query_sql = $show_query_sql.' AND ';}
        $id_query_sql =<<<SQL
            SELECT * FROM root_member_authentication 
            WHERE id='{$query_sql_array['id']}'
            AND id NOT IN('1','2','3');
SQL;
        $id_query_sql_result = runSQLall($id_query_sql);
        if(isset($id_query_sql_result) and $id_query_sql_result >= 1){
            $show_query_sql .= ' AND au.id=\''.$query_sql_array['id'].'\'';
            $query_top = 1;
        }else { 
            $logger = '无此帐户id!';
        }
    }

    // 是前台會員、代理商或是後台管理員
    // 有勾選+帳號
    if(isset($query_sql_array['role']) AND $query_sql_array['role'] != null AND isset($query_sql_array['account']) AND $query_sql_array['account'] != null and trim($query_sql_array['account']) != ''){

        $account_query_sql = <<<SQL
        SELECT au.id, mb.account FROM root_member_authentication as au
        LEFT JOIN root_member as mb
        ON au.id = mb.id
        WHERE account LIKE '%{$query_sql_array['account']}%'
        AND au.id NOT IN('1','2','3');
SQL;
        $account_query_result = runSQL($account_query_sql);
        if(isset($account_query_result) AND $account_query_result >= 1){
            $implode_acc = implode("','",$query_sql_array['role']);

            $sql = <<<SQL
            AND account like '%{$query_sql_array['account']}%'
            AND mb.therole in ('{$implode_acc}')
SQL;    
            $show_query_sql = $sql;
            $query_top = 1;
        }
    }elseif(isset($query_sql_array['role']) AND $query_sql_array['role'] != null AND !isset($query_sql_array['account'])){
        // 只有勾選
        $implode_acc = implode("','",$query_sql_array['role']);
        $sql = <<<SQL
        AND mb.therole in ('{$implode_acc}')
SQL;
        $show_query_sql = $sql;
        $query_top = 1;
    }
    
    if ($query_top == 1 and !isset($logger)) {
        $return_sql = $show_query_sql;
    } elseif (isset($logger)) {
        $return_sql['logger'] = $logger;
    } else {
        $return_sql = '';
    }
    // var_dump($return_sql);die();
    return $return_sql;
}

// 查詢SQL
function sql_authentication_query()
{
    $sql_str = <<<SQL
    SELECT
        au.id,
        mb.account,
        "two_fa_status",
        "two_fa_question",
        "two_fa_ans",
        "two_fa_secret",
        whitelis_status,
        to_char((au.changetime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') AS changetime ,
        whitelis_ip
    FROM
    root_member_authentication as au
    LEFT JOIN root_member as mb
    ON au.id = mb.id
    WHERE au.id NOT IN('1','2','3')

    AND mb.therole != 'R'
SQL;
    return $sql_str;
}

function word_cut($string, $limit, $pad = "...")
{
    $len = mb_strlen($string, 'UTF-8');
    if ($len <= $limit) {
        return $string;
    }

    //先找出裁切後的字串有多少英文字
    $tmp_content = mb_substr($string, 0, $limit, 'UTF-8');
    preg_match_all('/(\w)/', $tmp_content, $match);
    $eng = count($match[1]);
    $add = round($eng / 2, 0);
    $limit += $add;
    $string = mb_substr($string, 0, $limit, 'UTF-8');
    return $string . $pad;
}

// 2階段認識圖示判斷
function twofa_icon_decide($status){
    if($status=='1'){
        return  '<span style="color: green;">
                    <i class="fab fa-google fa-2x"></i>
                </span>';
    }else{
        return  '<span class="fa-stack fa-lg">
                    <i class="fab fa-google fa-1x"></i>
                    <i class="fas fa-ban fa-stack-2x" style="color:Tomato"></i>
                </span>';
    }
}

// ip白名單圖示
function  white_list_icon($status){
    if($status=='1'){
        return  '<span style="color: green;">
                    <i class="fab fas fa-list fa-2x"></i>
                </span>';
    }else{
        return  '<span class="fa-stack fa-lg">
                    <i class="fab fas fa-list fa-1x"></i>
                    <i class="fas fa-ban fa-stack-2x" style="color:Tomato"></i>
                </span>';
    }
}

// 操作欄位顯示
function opt_icon_link($hyperlink){
    return '<button type="button" onclick="location.href=\'member_authentication_edit.php?a=admin_edit&id='.$hyperlink.'\'"
    title="编辑验证管理" class="btn btn-primary">
    <span class="glyphicon glyphicon-pencil" aria-hidden="true"></span>
    </button>';

}

// -------------------------------------------------------------------------
// END function lib
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// GET / POST 傳值處理
// -------------------------------------------------------------------------

// var_dump($_SESSION);
// var_dump($_POST);
// var_dump($_GET);
// die();
$query_chk = 0;
if (isset($_GET['get']) and $_GET['get'] != null and $_GET['get'] != '') {
    $action    = filter_var($_GET['get'], FILTER_SANITIZE_STRING);
    $query_chk = 1;
} else {
    die($tr['Illegal test']);
}

// 檢查查詢條件是否有提供管理員帳號
if (isset($_GET['account']) and $_GET['account'] != null and $_GET['account'] != '') {
    $query_sql_array['account'] = filter_var($_GET['account'], FILTER_SANITIZE_STRING);
    $query_chk                  = 1;
}

// 檢查查詢條件是否有提供角色名稱或代碼
if (isset($_GET['id']) and $_GET['id'] != null and $_GET['id'] != '') {
    $query_sql_array['id'] = filter_var($_GET['id'], FILTER_SANITIZE_STRING);
    $query_chk             = 1;
}
// role是前台的會員M/代理商A，還是後台的管理員R
if(isset($_GET['role']) AND $_GET['role'] != null AND $_GET['role'] != ''){
    $query_sql_array['role'] = filter_var_array($_GET['role'], FILTER_SANITIZE_STRING);
    $query_chk = 1;
}

// -------------------------------------------------------------------------
// datatable server process 分頁處理及驗證參數
// -------------------------------------------------------------------------
// 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
if (isset($_GET['length']) and $_GET['length'] != null AND $_GET['length']>1 ) {
    $current_per_size = filter_var($_GET['length'], FILTER_VALIDATE_INT);
} else {
    $current_per_size = $page_config['datatables_pagelength'];
    // $current_per_size = 10;
}

// 起始頁面, 搭配 current_per_size 決定起始點位置
if (isset($_GET['start']) and $_GET['start'] != null) {
    $current_page_no = filter_var($_GET['start'], FILTER_VALIDATE_INT);
} else {
    $current_page_no = 0;
}

// datatable 回傳驗證用參數，收到後不處理直接跟資料一起回傳給 datatable 做驗證
if (isset($_GET['_'])) {
    $secho = $_GET['_'];
} else {
    $secho = '1';
}
// -------------------------------------------------------------------------
// datatable server process 分頁處理及驗證參數  END
// -------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------
if ($action == 'query_auth' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R' and $query_chk == 1) {
    // var_dump($query_sql_array);die();
    // if (isset($query_sql_array)) {
    // 處理 datatables 傳來的排序需求

    if (isset($_GET['order'][0]) and $_GET['order'][0]['column'] != '') {
        if ($_GET['order'][0]['dir'] == 'asc') {$sql_order_dir = 'ASC';} else { $sql_order_dir = 'DESC';}
        if       ($_GET['order'][0]['column'] == 0) {$sql_order = 'ORDER BY "id" ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 1) {$sql_order = 'ORDER BY "account" ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 2) {$sql_order = 'ORDER BY "changetime" ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 3) {$sql_order = 'ORDER BY "two_fa_status" ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 4) {$sql_order = 'ORDER BY "whitelis_status" ' . $sql_order_dir;
        // }elseif($_GET['order'][0]['column'] == 5){$sql_order = 'ORDER BY "notes" ' . $sql_order_dir;
        // }elseif($_GET['order'][0]['column'] == 6){$sql_order = 'ORDER BY "betresult" '.$sql_order_dir;
        // }elseif($_GET['order'][0]['column'] == 8){$sql_order = 'ORDER BY "favorable_category" '.$sql_order_dir;
        // }elseif($_GET['order'][0]['column'] == 7){$sql_order = 'ORDER BY "status" '.$sql_order_dir;
        // }elseif($_GET['order'][0]['column'] == 8){$sql_order = 'ORDER BY "changetime" '.$sql_order_dir;
        } else { $sql_order = 'ORDER BY "changetime" DESC';}
    } else { $sql_order = 'ORDER BY "changetime" DESC';}

    // 取得查詢條件，如果沒有就帶空字串
    if(isset($query_sql_array)){
        $query_str = query_str($query_sql_array);
    }else{
        $query_str ='';
    }
    if (isset($query_str['logger'])) {
        $output = array(
            "sEcho"                => 0,
            "iTotalRecords"        => 0,
            "iTotalDisplayRecords" => 0,
            "data"                 => '',
        );
    } else {
        // 算資料總筆數
        $userlist_sql = sql_authentication_query() . $query_str . ";";
        // echo $userlist_sql;die();
        $userlist_count = runSQL($userlist_sql, 0);
        // var_dump($userlist_count); die();

        // -----------------------------------------------------------------------
        // 分頁處理機制
        // -----------------------------------------------------------------------
        // 所有紀錄數量
        $page['all_records'] = $userlist_count;
        // 每頁顯示多少
        $page['per_size'] = $current_per_size;
        // 目前所在頁數
        $page['no'] = $current_page_no;
        // var_dump($page);

        // 取出資料
        $user_auth_sql = sql_authentication_query() . $query_str . " " . $sql_order . ' OFFSET ' . $page['no'] . ' LIMIT ' . $page['per_size'] . ';';
        // echo $user_auth_sql;die();
        $user_auth_lists = runSQLall($user_auth_sql, 0);
        // die(var_dump($user_auth_lists));die();

        if ($user_auth_lists[0] >= 1) {
            for ($i = 1; $i <= $user_auth_lists[0]; $i++) {
     

                // 如果為$su['superuser']的成員，則不能進編輯頁面
                // $oper_disable = '';
                // $title_show   = $tr['edit'];
                // if (in_array($userlist[$i]->account, $su['superuser'])) {
                //     $oper_disable = 'disabled';
                //     $title_show   = '站长、维运、客服，不能编辑';
                // }
                $twofaicon=twofa_icon_decide($user_auth_lists[$i]->two_fa_status);
                $whitelisticons=white_list_icon($user_auth_lists[$i]->whitelis_status);
                $opt = opt_icon_link($user_auth_lists[$i]->id);               

                $count = $page['no'] + $i;

                $b['id']          = $count;
                $b['account']     = $user_auth_lists[$i]->account;
                $b['update_time'] = $user_auth_lists[$i]->changetime;
                $b['2fa']         = $twofaicon;
                $b['ipwhitelist'] = $whitelisticons;
                $b['opt']         = $opt;

                // 顯示的表格資料內容
                $show_listrow_array[] = array(
                    'id'          => $b['id'],
                    'account'     => $b['account'],
                    'update_time' => $b['update_time'],
                    '2fa'         => $b['2fa'],
                    'ipwhitelist' => $b['ipwhitelist'],
                    'opt'         => $b['opt']);

            }
            $output = array(
                "sEcho"                => intval($secho),
                "iTotalRecords"        => intval($page['per_size']),
                "iTotalDisplayRecords" => intval($page['all_records']),
                "data"                 => $show_listrow_array,
            );
            // --------------------------------------------------------------------
            // 表格資料 row list , end for loop
            // --------------------------------------------------------------------
        } else {
            // NO data
            $output = array(
                "sEcho"                => 0,
                "iTotalRecords"        => 0,
                "iTotalDisplayRecords" => 0,
                "data"                 => '',
            );
        }
    }
  
    echo json_encode($output);
  
} elseif ($action == 'test') {
    // ----------------------------------------------------------------------------
    // test developer test
    // ----------------------------------------------------------------------------
    var_dump($_POST);
} elseif ($action == 'query_auth' and $query_chk == 0) {
    $output = array(
        "sEcho"                => 0,
        "iTotalRecords"        => 0,
        "iTotalDisplayRecords" => 0,
        "data"                 => '',
    );
    echo json_encode($output);
} elseif (isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    $output = array(
        "sEcho"                => 0,
        "iTotalRecords"        => 0,
        "iTotalDisplayRecords" => 0,
        "data"                 => '',
    );

    echo json_encode($output);
}
// -----------------------------------------------------------------------
// MAIN END
// -----------------------------------------------------------------------
