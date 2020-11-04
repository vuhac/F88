<?php
// ----------------------------------------------------------------------------
// Features:    管理端的會員查詢
// File Name:    member_action.php
// Author:        Barkley
// Related:   對應 member.php
// Log:
// 2019.03.27 新增匯出功能 Letter
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

require_once dirname(__FILE__) . "/lib_proccessing.php";

require_once dirname(__FILE__) . "/lib_file.php";

if (isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
} else {//$tr['Illegal test'] = '(x)不合法的測試。';
    die($tr['Illegal test']);
}

$debug = 0;

// var_dump($_SESSION);
// var_dump($_POST);die();
// var_dump($_GET);die();

/**
 * 更新會員等級.反水設定.佣金設定.上層推薦人.下線會員拆佣比
 *
 * @param [type] $id - 會員 id
 * @param [type] $column_name - DB 列名
 * @param [type] $column_value - 要更新的值
 * @param [type] $success_msg - 更新成功的訊息
 * @param [type] $failed_msg - 更新失敗的訊息
 * @return void
 */
function update_setting($id, $column_name, $column_value, $success_msg, $failed_msg)
{
    global $tr;
    if ($column_value != '' AND $id != '') {
        $update_sql = "UPDATE root_member SET ".$column_name." = '" . $column_value . "' WHERE id = '" . $id . "';";
        $update_sql_result = runSQL($update_sql);

        if ($update_sql_result == 1) {
            // 更新成功
            $logger = $success_msg;
            echo '<script>alert("' . $logger . '");location.reload();</script>';
        } else {
            // 更新失敗
            $logger = $failed_msg;
            echo '<script>alert("' . $logger . '");location.reload();</script>';
        }

    } else {
        // 錯誤嘗試
        // 因送過來的值不合法 $tr['Wrong attempt'] = '(x)錯誤的嘗試。';
        $logger = $tr['Wrong attempt'];
        echo '<script>alert("' . $logger . '");;</script>';
    }
}

// ------------------
// 檢查代理商欄位資料是否正確
// use: agentaccount_create_check($account_create_input)
// ------------------
/**
 * 檢查代理商資料是否正確
 *
 * @param [type] $account_input - 代理商帳號
 * @return string
 */
 function agentaccount_check($account_input) {
     Global $tr;

     $account_input = filter_var($account_input, FILTER_SANITIZE_STRING);
     //var_dump($account_input);

     // 有資料才作業
     if(!is_null($account_input)) {
         // 可以使用的帳號合法, check 是否有重複. 只有代理商及root身份才可以加入
         $sql = "SELECT * FROM root_member WHERE (therole = 'A' OR therole = 'R') AND account = '".$account_input."' ;";
         //var_dump($sql);
         $r = runSQLALL($sql);
         //var_dump($r);
         // 如果有帳號存在, 才可以新增帳號
         if($r[0] == 1) {
             if($r[1]->status == 1 AND ($r[1]->therole == 'A' OR $r[1]->therole == 'R')) {
                 //此代理商 A 存在,且狀態可以使用 查詢代理商
                 $account_check_return['text'] = '<div class="text-success">'.$tr['Agent Ckeck'].'<a href="member_account.php?a='.$r[1]->id.'" target="_NEW">'.$r[1]->account.'</a></div>';
                 $account_check_return['code']         = 1;
                 $account_check_return['account']     = $account_input;
                 $account_check_return['id']         = $r[1]->id;
                 $account_check_return['parent_id'] = $r[1]->parent_id;
             } else {//$tr['account frozen']='此帳號被停用或是凍結，檢查帳號';
                 $account_check_return['text'] = '<div class="text-danger">'.$tr['account frozen'].' <a href="member_account.php?a='.$r[1]->id.'" target="_NEW">'.$r[1]->account.'</a></div>';
                 $account_check_return['code']         = 8;
                 $account_check_return['account']     = $account_input;
                 $account_check_return['id']         = $r[1]->id;
                 $account_check_return['parent_id'] = $r[1]->parent_id;
             }
         } else {
             //此代理商不存在。
             $account_check_return['text'] = '<div class="text-danger">'.$tr['None of Agent'].'</div>';
             $account_check_return['code'] = 2;
         }

     } else {//$tr['Please enter the correct upper referral account'] = '請輸入正確上層推薦人帳號。';
         $account_check_return['text'] = '<div class="text-danger">'.$tr['Please enter the correct upper referral account'].'</div>';
         $account_check_return['code'] = 0;
     }

     return $account_check_return;
}

function get_range_condition_sqlwhere($arr)
{
    $sqlwhere = '';
    if ($arr['range_start_value'] != '' AND $arr['range_end_value'] == '') {
        $sqlwhere = $arr['range_start_col']." >= '".$arr['range_start_value']."' AND ";
    } elseif ($arr['range_end_value'] != '' AND $arr['range_start_value'] == '') {
        $sqlwhere = $arr['range_end_col']." <= '".$arr['range_end_value']."' AND ";
    } elseif ($arr['range_start_value'] != '' AND $arr['range_end_value'] != '') {
        if ($arr['range_end_value'] > $arr['range_start_value']) {
            $sqlwhere = $arr['range_start_col']." >= '".$arr['range_start_value']."' AND ".$arr['range_start_col']." <= '".$arr['range_end_value']."' AND ";
        } else {
            echo "<script>alert('{$arr['error_msg']}');</script>";
            die();
        }
    }
    return $sqlwhere;
}

function get_precise_fuzzy_search_sqlwhere($search_method, $col_value, $col_name)
{
    $sqlwhere = '';
    if ($col_value != '' AND $search_method != '') {
        if ($search_method == 'precise_search') {
            $sqlwhere = "root_member.".$col_name." = '" . $col_value . "' AND ";
        } elseif ($search_method == 'fuzzy_search') {
            $sqlwhere = "root_member.".$col_name." like '%" . $col_value . "%' AND ";
        }
    }

    return $sqlwhere;
}


/**
 * 匯出 excel 檔案
 *
 * @param array $result
 * @param array $selectColumns
 *
 * @throws \PhpOffice\PhpSpreadsheet\Exception
 * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
 */
function exportExcel(array $result, array $selectColumns){
     global $tr,$protalsetting;
     $regular_selectColumns = regular_selectColumns_array($selectColumns);
    if ($result[0] > 0) {
        // 清快取
        ob_end_clean();
        $fileName = 'members_export_'. date("YmdHis") .'.xlsx';
        $filePath = dirname(__FILE__) .'/'. $fileName;
        $fileType = 'Xlsx';
        // 標題列
        $title = [
            $tr['ID'],
            $tr['Account'],
//            $tr['nickname'], // 3
            $tr['realname'],
//            $tr['passwd'], // 5
//            $tr['changetime'], // 6
//            $tr['creditcurrency'], // 7
            $tr['mobilenumber'],
            $tr['Email'],
//            $tr['lang'], // 10
            $tr['status'],
            $tr['therole'],
            $tr['parent_id'],
//            $tr['notes'], // 14
            $tr['sex'],
            $tr['birthday'],
            $protalsetting["custom_sns_rservice_1"]??$tr['sns1'],
            $protalsetting["custom_sns_rservice_2"]??$tr['sns2'],
            $tr['bankaccount'],
//            $tr['bankname'], // 20
//            $tr['bankprovince'], // 21
//            $tr['bankcounty'], // 22
//            $tr['withdrawalspassword'], // 23
//            $tr['timezone'], // 24
            $tr['favorablerule'],
            $tr['lastlogin'],
//            $tr['lastseclogin'], // 27
            $tr['lastbetting'],
//            $tr['lastsecbetting'], // 29
            $tr['grade'],
            $tr['enrollmentdate'],
            $tr['registerfingerprinting'],
            $tr['registerip'],
//            $tr['recommendedcode'], // 34
//            $tr['salt'], // 35
//            $tr['commissionrule'], //36
//            $tr['permission'], // 37
//            $tr['feedbackinfo'], // 38
//            $tr['becomeagentdate'], // 39
//            $tr['allow_login_passwordchg'], // 40
//            $tr['first_deposite_date'], // 41
//            $tr['gcash_log_exist'], //42
            $tr['gcash_balance'],
            $tr['gtoken_balance'],
//            $tr['gtoken_lock'], // 45
//            $tr['auto_gtoken'], // 46
//            $tr['auto_min_gtoken'], // 47
//            $tr['auto_once_gotken'], //48
            $tr['casino_accounts'], // 49
//            $tr['recivemoney_count'], // 50
//            $tr['enrollmentdate_tz'], // 51
        ];

        // 要移除欄位
        $removeCols = array(
            'nickname', 'passwd', 'changetime', 'creditcurrency', 'lang',
            'notes', 'bankname', 'bankprovince', 'bankcounty', 'withdrawalspassword',
            'timezone', 'lastseclogin', 'lastsecbetting', 'recommendedcode', 'salt',
            'commissionrule', 'permission', 'feedbackinfo', 'becomeagentdate', 'allow_login_passwordchg',
            'first_deposite_date', 'gcash_log_exist', 'gtoken_lock', 'auto_gtoken',
            'auto_min_gtoken', 'auto_once_gotken', 'casino_accounts', 'recivemoney_count', 'enrollmentdate_tz'
        );

        // 複製新的資料
        $dataArr = array();
        for ($i = 1; $i <= $result[0]; $i++) {
            $dataArr[$i-1] = get_object_vars($result[$i]);
        }

        // 複製娛樂城帳號資料
        $casinoAccountArr = array();
        if (in_array('casino_accounts', $regular_selectColumns)) {
            for ($i = 0; $i < $result[0]; $i++) {
                $casinoAccountArr[$i] = json_decode($dataArr[$i]['casino_accounts']);
            }
        }

        // 處理標題欄位
        $selectTitles = array();
        $titleIndex = 0;
        foreach ($regular_selectColumns as $key => $col) {
            switch ($col) {
                case 'wechat':
                    $selectTitles[$titleIndex] = $protalsetting["custom_sns_rservice_1"]??$tr['sns1'];
                    break;
                case 'qq':
                    $selectTitles[$titleIndex] = $protalsetting["custom_sns_rservice_2"]??$tr['sns2'];
                    break;
                default:
                    $selectTitles[$titleIndex] = $tr[$col];
                    break;
            }
            $titleIndex++;
        }

        // 匯出選擇的欄位
        for ($i = 0; $i < $result[0]; $i++) {
            // 移除不需要的欄位
            foreach ($removeCols as $key => $col) {
                unset($dataArr[$i][$removeCols[$key]]);
            }
            // 留下要匯出欄位
            foreach ($dataArr[$i] as $key => $col) {
                if (!in_array($key, $regular_selectColumns)) {
                    unset($dataArr[$i][$key]);
                }
            }
        }

        // 轉 excel
        $excelStream = new exportExcel($fileName, $filePath, $fileType);
        $excelStream->dataToExcel($selectTitles, $dataArr, $casinoAccountArr);
        return;
    }
}

/**
 * 調整首行標題欄位
 *
 * @param array $selectColumns

 */
function regular_selectColumns_array(array $selectColumns){
    $index = 0;//init index
    $tmp_selectColumns = array();
    $regular_selectColumns = array();
    $title_array = array(
        'id','account','realname','mobilenumber','email',
        'status','therole','parent_id','sex','birthday',
        'wechat','qq','bankaccount','favorablerule','lastlogin',
        'lastbetting','grade','enrollmentdate','registerfingerprinting',
        'registerip','gcash_balance','gtoken_balance','casino_accounts'
    );

    //將被選取的欄位存入array
    foreach ($selectColumns as $col) {
        $tmp_selectColumns[array_search($col,$title_array)] = $col;
    }

    ksort($tmp_selectColumns);//排序標題欄位

    //將排序完順序的標題資料依序排序以符合處理形式
    foreach ($tmp_selectColumns as $cols) {
        $regular_selectColumns[$index] = $cols;
        $index++;
    }


    return $regular_selectColumns;
}

/**
 * 訪問 redisdb;
 * 預設 2 為前台 redisdb, 見 config_orig.php
 * 這邊注意: 前台使用的 redisdb 可能會變動
 */
function connect_website_redis($db = 2){
    global $redisdb;
    !is_int($db) and die('No select RedisDB or Invalid RedisDB #');

    $redis = new Redis();
    // 2 秒 timeout/fail
    !$redis->pconnect($redisdb['host'], 6379, 2) and die('Redisdb Connection Failed');
    // auth fail
    !$redis->auth($redisdb['auth']) and    die('Redisdb authentication failed');
    // 選擇 DB , member 使用者自訂的 session 放在 db 2 ($redisdb['db'] 替代)
    $redis->select($db);
    // echo "Server is running: ".$redis->ping();
    return $redis;
}


function unlock_users()
{
    /*
     直接找出 狀態是封鎖且已經可以解鎖的 會員，更新其狀態
     狀態是封鎖且已經可以解鎖的: WHERE status = '3' AND lastlogin + '50 minutes' < now()
     UPDATE "root_member" SET status='1' WHERE status = '3' AND lastlogin + '50 minutes' < now()
    */
    global $protalsetting;
    $redis_conn = connect_website_redis();
    $accounts = runSQLall("SELECT account FROM root_member WHERE status = '3'");
    $unjail_accounts = [];

    for ($i = 1; $i <= $accounts[0]; $i++) {
        $key_name = date("Ymd").$accounts[$i]->account;
        if (!$redis_conn->exists($key_name)) {
            continue;
        }

        $error_time_est = $redis_conn->hGet($key_name, 'error_time');
        $unlocked_time_est_ts = strtotime("$error_time_est + {$protalsetting['account_lock_time']} mins");
        $now_est_ts = strtotime('now -12 hours');

        if ($unlocked_time_est_ts <= $now_est_ts) {
            $unjail_accounts[] = $accounts[$i]->account;
        }
    }

    if ($unjail_accounts) {
        $unjail_accounts_str = implode("', '", $unjail_accounts);
        $update_sql = "UPDATE root_member SET status = '1' WHERE status = '3' AND account IN ('$unjail_accounts_str')";
        runSQLall($update_sql);
    }
}


// ----------------------------------
// 動作為會員 action
// ----------------------------------
if ($action == 'member_inquiry' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
    unlock_users();
// ----------------------------------------------------------------------------
    // member.php 查詢會員資訊列表
    // ----------------------------------------------------------------------------
    // var_dump($_POST);
    $return_string = '';
    $sql_where = '';

    // 使用者所在的時區，sql 依據所在時區顯示 time
    // -------------------------------------
    $tz = (isset($_SESSION['agent']->timezone) AND $_SESSION['agent']->timezone != NULL) ? $_SESSION['agent']->timezone : '+08';

    // 轉換時區所要用的 sql timezone 參數
    $tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '" . $tz . "'";
    $tzone = runSQLALL($tzsql);
    $tzonename = $tzone[0] == 1 ? $tzone[1]->name : 'posix/Etc/GMT-8';

    // 帳號
    $account = filter_var($_POST['account'], FILTER_SANITIZE_STRING);

    if($account != ''){
        $sql_where = $sql_where . "root_member.account like '%" . strtolower($account) . "%' AND ";
    }

    $acc_type = filter_var($_POST['acc_type'], FILTER_SANITIZE_STRING);
    if ($acc_type != '' && ($acc_type == 'acc_type=A' || $acc_type == 'acc_type=M')) {
        $sql_where .= "root_member.therole = '" . str_replace('acc_type=','',$acc_type) ."' AND ";
    }

    $register_date_start_time = filter_var($_POST['register_date_start_time'], FILTER_SANITIZE_STRING);
    $register_date_end_time = filter_var($_POST['register_date_end_time'], FILTER_SANITIZE_STRING);
    $arr = [
        'range_start_col'=>"to_char((root_member.enrollmentdate AT TIME ZONE '$tzonename'),'YYYY/MM/DD')",
        'range_end_col'=>"to_char((root_member.enrollmentdate AT TIME ZONE '$tzonename'),'YYYY/MM/DD')",
        'range_start_value'=>$register_date_start_time,
        'range_end_value'=>$register_date_end_time,
        'error_msg'=>$tr['The entry date can not be less than the start time']
    ];
    $sql_where = $sql_where.get_range_condition_sqlwhere($arr);


    $account_cash_balance_lower = round(filter_var($_POST['account_cash_balance_lower'], FILTER_SANITIZE_STRING), 2);
    $account_cash_balance_upper = round(filter_var($_POST['account_cash_balance_upper'], FILTER_SANITIZE_STRING), 2);
    $arr = [
        'range_start_col'=>"root_member_wallets.gcash_balance",
        'range_end_col'=>"root_member_wallets.gcash_balance",
        'range_start_value'=>$account_cash_balance_lower,
        'range_end_value'=>$account_cash_balance_upper,
        'error_msg'=>$tr['Account cap amount can not be less than the minimum amount of account balance']
    ];
    $sql_where = $sql_where.get_range_condition_sqlwhere($arr);


    $account_token_balance_lower = round(filter_var($_POST['account_token_balance_lower'], FILTER_SANITIZE_STRING), 2);
    $account_token_balance_upper = round(filter_var($_POST['account_token_balance_upper'], FILTER_SANITIZE_STRING), 2);
    $arr = [
        'range_start_col'=>"root_member_wallets.gtoken_balance",
        'range_end_col'=>"root_member_wallets.gtoken_balance",
        'range_start_value'=>$account_token_balance_lower,
        'range_end_value'=>$account_token_balance_upper,
        'error_msg'=>$tr['Account cash balance can not be less than your account cash balance']
    ];
    $sql_where = $sql_where.get_range_condition_sqlwhere($arr);


    $mamber_status_select = filter_var($_POST['mamber_status_select'], FILTER_SANITIZE_STRING);
    if ($mamber_status_select != '') {
        // if ($mamber_status_select >= 0 AND $mamber_status_select <= 2) {
            $sql_where = $sql_where . "root_member.status = '" . $mamber_status_select . "' AND ";
        // }
    }


    $member_grade_list = filter_var($_POST['select_member_grade'], FILTER_SANITIZE_STRING);
    if ($member_grade_list != '') {
        // $member_grade_list = $_POST['select_member_grade'];
        $member_grade_list = str_replace('member_grade_checkbox=', '', $member_grade_list);
        $member_grade_list = explode("&", $member_grade_list);

        $member_grade_sql_where = '';
        for ($i = 0; $i < count($member_grade_list); $i++) {
            $member_grade_sql_where = $member_grade_sql_where . "root_member.grade = '" . $member_grade_list[$i] . "' OR ";
        }
        // 去除最後一個OR
        $member_grade_sql_where = substr($member_grade_sql_where, 0, strlen(' OR ') * -1);
        $sql_where = $sql_where . '(' . $member_grade_sql_where . ') AND ';
    }


    $favorable_select = filter_var($_POST['favorable_select'], FILTER_SANITIZE_STRING);
    if ($favorable_select != '') {
        $sql_where = $sql_where . "root_member.favorablerule = '" . $favorable_select . "' AND ";
    }


    $agent_account = filter_var($_POST['agent_account'], FILTER_SANITIZE_STRING);
    if ($agent_account != '') {
        $agent_id = "SELECT id FROM root_member WHERE account = '" . $agent_account . "' AND therole = 'A';";
        $agent_id_result = runSQLALL($agent_id);

        if ($agent_id_result[0] == 1) {
            $sql_where = $sql_where . "root_member.parent_id = '" . $agent_id_result[1]->id . "' AND ";
        }
    }


    $registerip = filter_var($_POST['registerip'], FILTER_SANITIZE_STRING);
    if ($registerip != '') {
        $sql_where = $sql_where . "root_member.registerip = '" . $registerip . "' AND ";
    }


    $registerfingerprinting = filter_var($_POST['registerfingerprinting'], FILTER_SANITIZE_STRING);
    if ($registerfingerprinting != '') {
        $sql_where = $sql_where . "root_member.registerfingerprinting = '" . $registerfingerprinting . "' AND ";
    }

    // 進階搜尋

    $real_name = filter_var($_POST['real_name'], FILTER_SANITIZE_STRING);
    $real_name_search = filter_var($_POST['real_name_search'], FILTER_SANITIZE_STRING);
    $sql_where = $sql_where.get_precise_fuzzy_search_sqlwhere($real_name_search, $real_name, 'realname');


    $mobile_number = filter_var($_POST['mobile_number'], FILTER_SANITIZE_STRING);
    $mobile_number_search = filter_var($_POST['mobile_number_search'], FILTER_SANITIZE_STRING);
    $sql_where = $sql_where.get_precise_fuzzy_search_sqlwhere($mobile_number_search, $mobile_number, 'mobilenumber');


    $sex_select = filter_var($_POST['sex_select'], FILTER_SANITIZE_STRING);
    if ($sex_select != '') {
        $sql_where = $sql_where . "root_member.sex = '" . $sex_select . "' AND ";
    }


    $email = filter_var($_POST['email'], FILTER_SANITIZE_STRING);
    $email_search = filter_var($_POST['email_search'], FILTER_SANITIZE_STRING);
    $sql_where = $sql_where.get_precise_fuzzy_search_sqlwhere($email_search, $email, 'email');


    $birthday_start_date = filter_var($_POST['birthday_start_date'], FILTER_SANITIZE_STRING);
    $birthday_end_date = filter_var($_POST['birthday_end_date'], FILTER_SANITIZE_STRING);
    $birthday_start_date = str_replace('/', '', $birthday_start_date);
    $birthday_end_date = str_replace('/', '', $birthday_end_date);
    $arr = [
        'range_start_col'=>"root_member.birthday",
        'range_end_col'=>"root_member.birthday",
        'range_start_value'=>$birthday_start_date,
        'range_end_value'=>$birthday_end_date,
        'error_msg'=>$tr['Birthday end time can not be less than the start time']
    ];
    $sql_where = $sql_where.get_range_condition_sqlwhere($arr);


    $wechat = filter_var($_POST['wechat'], FILTER_SANITIZE_STRING);
    $wechat_search = filter_var($_POST['wechat_search'], FILTER_SANITIZE_STRING);
    $sql_where = $sql_where.get_precise_fuzzy_search_sqlwhere($wechat_search, $wechat, 'wechat');


    $qq = filter_var($_POST['qq'], FILTER_SANITIZE_STRING);
    $qq_search = filter_var($_POST['qq_search'], FILTER_SANITIZE_STRING);
    $sql_where = $sql_where.get_precise_fuzzy_search_sqlwhere($qq_search, $qq, 'qq');


    $bank_account = filter_var($_POST['bank_account'], FILTER_SANITIZE_STRING);
    $bank_account_search = filter_var($_POST['bank_account_search'], FILTER_SANITIZE_STRING);
    $sql_where = $sql_where.get_precise_fuzzy_search_sqlwhere($bank_account_search, $bank_account, 'bankaccount');


    $last_betting_start_date = filter_var($_POST['last_betting_start_date'], FILTER_SANITIZE_STRING);
    $last_betting_end_date = filter_var($_POST['last_betting_end_date'], FILTER_SANITIZE_STRING);
    $casino_transferrecords_sql_where = '';
    $arr = [
        'range_start_col'=>"to_char((root_member_casino_transferrecords.occurtime AT TIME ZONE '$tzonename'),'YYYY/MM/DD')",
        'range_end_col'=>"to_char((root_member_casino_transferrecords.occurtime AT TIME ZONE '$tzonename'),'YYYY/MM/DD')",
        'range_start_value'=>$last_betting_start_date,
        'range_end_value'=>$last_betting_end_date,
        'error_msg'=>$tr['The last betting end time can not be less than the start time']
    ];
    $casino_transferrecords_sql_where = get_range_condition_sqlwhere($arr);

    if ($casino_transferrecords_sql_where != '') {
        $casino_transferrecords_sql = "SELECT account FROM root_member JOIN root_member_casino_transferrecords ON root_member.id = root_member_casino_transferrecords.memberid WHERE " . $casino_transferrecords_sql_where;
        $casino_transferrecords_sql = substr($casino_transferrecords_sql, 0, strlen(' AND ') * -1);
    } else {
        $casino_transferrecords_sql = '';
    }


    $last_login_start_date = filter_var($_POST['last_login_start_date'], FILTER_SANITIZE_STRING);
    $last_login_end_date = filter_var($_POST['last_login_end_date'], FILTER_SANITIZE_STRING);
    $memberlog_sql_where = '';
    $arr = [
        'range_start_col'=>"to_char((root_memberlog.occurtime AT TIME ZONE '$tzonename'),'YYYY/MM/DD')",
        'range_end_col'=>"to_char((root_memberlog.occurtime AT TIME ZONE '$tzonename'),'YYYY/MM/DD')",
        'range_start_value'=>$last_login_start_date,
        'range_end_value'=>$last_login_end_date,
        'error_msg'=>$tr['The last login end time can not be less than the start time']
    ];
    $memberlog_sql_where = get_range_condition_sqlwhere($arr);

    if ($memberlog_sql_where != '') {
        $memberlog_sql = "SELECT account FROM root_member JOIN root_memberlog ON root_member.account = root_memberlog.who WHERE " . $memberlog_sql_where;
        $memberlog_sql = substr($memberlog_sql, 0, strlen(' AND ') * -1);
    } else {
        $memberlog_sql = '';
    }


    // 根據不同條件組合 sql
    if ($sql_where != '') {
        $sql_where = substr($sql_where, 0, strlen(' AND ') * -1);
        $sql_where = $sql_where . " AND root_member.therole != 'R' AND root_member.id > 10000";

        if ($memberlog_sql == '' AND $casino_transferrecords_sql == '') {
            $sql = "SELECT *,enrollmentdate, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as enrollmentdate_tz FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE " . $sql_where . ";";
        } elseif ($memberlog_sql != '' AND $casino_transferrecords_sql == '') {
            $sql = "
            SELECT *,enrollmentdate, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as enrollmentdate_tz
            FROM (" . $memberlog_sql . " GROUP BY account) AS betting_login_account_intersecting
            JOIN root_member
            ON root_member.account = betting_login_account_intersecting .account
            JOIN root_member_wallets
            ON root_member.id = root_member_wallets.id
            WHERE " . $sql_where . ";
            ";
        } elseif ($memberlog_sql == '' AND $casino_transferrecords_sql != '') {
            $sql = "
            SELECT *,enrollmentdate, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as enrollmentdate_tz
            FROM (" . $casino_transferrecords_sql . " GROUP BY account) AS betting_login_account_intersecting
            JOIN root_member
            ON root_member.account = betting_login_account_intersecting .account
            JOIN root_member_wallets
            ON root_member.id = root_member_wallets.id
            WHERE " . $sql_where . ";
            ";
        } else {
            $sql = "
            SELECT *,enrollmentdate, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as enrollmentdate_tz
            FROM (" . $memberlog_sql . " INTERSECT " . $casino_transferrecords_sql . " GROUP BY account) AS betting_login_account_intersecting
            JOIN root_member
            ON root_member.account = betting_login_account_intersecting .account
            JOIN root_member_wallets
            ON root_member.id = root_member_wallets.id
            WHERE " . $sql_where . ";
            ";
        }

    } else {

        if ($memberlog_sql != '' OR $casino_transferrecords_sql != '') {
            if ($memberlog_sql != '' AND $casino_transferrecords_sql == '') {
                $sql = "
                SELECT *,enrollmentdate, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as enrollmentdate_tz
                FROM (" . $memberlog_sql . " GROUP BY account) AS betting_login_account_intersecting
                JOIN root_member
                ON root_member.account = betting_login_account_intersecting .account
                JOIN root_member_wallets
                ON root_member.id = root_member_wallets.id
                WHERE root_member.therole != 'R';";
            } elseif ($memberlog_sql == '' AND $casino_transferrecords_sql != '') {
                $sql = "
                SELECT *,enrollmentdate, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as enrollmentdate_tz
                FROM (" . $casino_transferrecords_sql . " GROUP BY account) AS betting_login_account_intersecting
                JOIN root_member
                ON root_member.account = betting_login_account_intersecting .account
                JOIN root_member_wallets
                ON root_member.id = root_member_wallets.id
                WHERE root_member.therole != 'R';";
            } else {
                $sql = "
                SELECT *,enrollmentdate, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as enrollmentdate_tz
                FROM (" . $memberlog_sql . " INTERSECT " . $casino_transferrecords_sql . " GROUP BY account) AS betting_login_account_intersecting
                JOIN root_member
                ON root_member.account = betting_login_account_intersecting .account
                JOIN root_member_wallets
                ON root_member.id = root_member_wallets.id
                WHERE root_member.therole != 'R';";
            }

        } else {
            $sql = "SELECT *,enrollmentdate, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as enrollmentdate_tz FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE (enrollmentdate > (now() - interval '90 days')) ORDER BY enrollmentdate_tz DESC LIMIT 100;";
        }
    }
    // echo ($sql);die();
    $r = runSQLALL($sql);

    if (isset($_POST['excel']) AND isset($_POST['isQuery'])) {
        if ($_POST['excel'] == 'excel' AND $_POST['isQuery'] == '1') {
            try {
                $selectColsStr = $_POST['selectCols'];
                $selectCols = explode(',', $selectColsStr);
                //用來判斷是否檔案已經下載
                setcookie("memberdown","false",time()+1800);
                $order_by_id_sql = str_replace(';',' ORDER BY root_member.id DESC;',$sql);
                $order_by_id_r = runSQLALL($order_by_id_sql);
                exportExcel($order_by_id_r, $selectCols);
            } catch (Exception $e) {
                echo "<script>alert({$tr['export excel fail']})</script>";
            } finally {
                return;
            }
        }
        return;
    } else {
        // 有資料才顯示
        if ($r[0] >= 1) {
            // var_dump($r);

            // 身份用途是來展示
            //管理員$tr['Identity Management']
            $theroleicon['R'] = '<div title="' . $tr['Identity Management Title'] . '"><span class="glyphicon glyphicon-king" aria-hidden="true"></div>';
            //代理商$tr['Identity Agent']
            $theroleicon['A'] = '<div title="' . $tr['Identity Agent Title'] . '"><span class="glyphicon glyphicon-knight" aria-hidden="true"></div>';
            //會員$tr['Identity Member']
            $theroleicon['M'] = '<div title="' . $tr['Identity Member Title'] . '"><span class="glyphicon glyphicon-user" aria-hidden="true"></div>';
            //試用帳號$tr['Identity Trial Account']
            $theroleicon['T'] = '<div title="' . $tr['Identity Trial Account Title'] . '"><span class="glyphicon glyphicon-sunglasses" aria-hidden="true"></div>';

            // 狀態中文描述
            //停用
            $status_desc['0'] = '<span class="label label-danger">' . $tr['Wallet Disable'] . '</span>';
            //有效
            $status_desc['1'] = '<span class="label label-success">' . $tr['Wallet Valid'] . '</span>';
            //錢包凍結
            $status_desc['2'] = '<span class="label label-warning">' . $tr['Wallet Freeze'] . '</span>';
            // 帳號暫時封鎖
            $status_desc['3'] = '<span class="label label-danger">' . $tr['blocked'] . '</span>';
            // 帳號審核中
            $status_desc['4'] = '<span class="label label-danger">' . $tr['auditing'] . '</span>';

            // member grade 會員等級的名稱
            // -------------------------------------
            $grade_sql = "SELECT * FROM root_member_grade;";
            $graderesult = runSQLALL($grade_sql);
            // var_dump($graderesult);
            if ($graderesult[0] >= 1) {
                for ($i = 1; $i <= $graderesult[0]; $i++) {
                    $gradelist[$graderesult[$i]->id] = $graderesult[$i];
                }
                // var_dump($gradelist);
            } else {
                $gradelist = NULL;
            }
            // -------------------------------------

            // ------------------------ loop ------------------------
            $data_table_row = '';
            for ($i = 1; $i <= $r[0]; $i++) {

                // 判斷會員等級參數值，是否存在
                if (isset($gradelist[$r[$i]->grade]->gradename) AND ($r[$i]->grade >= 0 AND $r[$i]->grade <= 1000)) {
                  if($gradelist[$r[$i]->grade]->status == '1'){
                    $grade_desc = $gradelist[$r[$i]->grade]->gradename;
                  }else{
                    $grade_desc = '<strike>'.$gradelist[$r[$i]->grade]->gradename.'</strike>';
                  }
                } else {
                    $grade_desc = 'basic';
                }

                // isset($gradelist[$r[$i]->grade]->status) and var_dump($gradelist[$r[$i]->grade]->status); die;
                // $r[$i]->enrollmentdate_tz
                //暫時移除<div title="RAW&nbsp;' . $r[$i]->enrollmentdate . '">' .  gmdate('Y-m-d H:i',strtotime($r[$i]->enrollmentdate) + -4*3600) . '</div>
                $data_table_row = $data_table_row . '
            <tr>
                <td>' . $r[$i]->id . '</td>
                <td><a href="member_account.php?a=' . $r[$i]->id . '">' . $r[$i]->account . '</a></td>
                <td>' . $r[$i]->realname . '</td>
                <td>' .  gmdate('Y-m-d H:i',strtotime($r[$i]->enrollmentdate) + -4*3600) . '</td>
                <td>' . $theroleicon[$r[$i]->therole] . '</td>
                <td>' . $status_desc[$r[$i]->status] . '</td>
                <td>' . $grade_desc . '</td>
                <td align="right">$' . $r[$i]->gcash_balance . '</td>
                <td align="right">$' . $r[$i]->gtoken_balance . '</td>
            </tr>
            ';

            }
            // ------------------------ loop ------------------------

        //<td>' . $tr['Enrollment date'] . '(' . $tz . ')</td>
            $table_colname_html = '
        <tr>
            <td>' . $tr['ID'] . '</td>
            <td>' . $tr['Account'] . '</td>
            <td>' . $tr['realname'] . '</td>
            <td>' . $tr['Enrollment date'] . '</td>
            <td>' . $tr['The Role'] . '</td>
            <td>' . $tr['State'] . '</td>
            <td>' . $tr['Member Level'] . '</td>
            <td>' . $tr['Cash Balance'] . '</td>
            <td>' . $tr['Token Balance'] . '</td>
        </tr>
        ';
            $sorttablecss = ' id="show_list"  class="display compact" cellspacing="0" width="100%" ';
            $return_string = '
        <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
        <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
        <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>

        <script>
            $(document).ready(function() {
                $("#show_list").DataTable( {
                    "searching": false,
                    "aaSorting": [[ 3, "desc" ]],
                    "columnDefs": [ {
                    "targets": 0,
                    } ]
                });

                // 加上進階匯出
                $("#excelBtn").attr("data-toggle","modal").attr("data-target","#adv_export");


                // 全選按鈕反應
                $("#export_select_all").on("click", function() {
                    if($("#export_select_all").prop("checked")) {
                        $(".export_item").prop("checked", true);
                    } else {
                        $(".export_item:not(\'.required\')").prop("checked", false);
                    }
                });
                $(".export_item").on("click", function() {
                    if(!$(this).prop("checked")){
                        $("#export_select_all").prop("checked", false);
                    }
                    var allExport = $(".export_item").length;
                    var checkedExport = $(".export_item:checked").length;
                    if (allExport == checkedExport) {
                        $("#export_select_all").prop("checked", true);
                    }
                })
            });
        </script>

        <table ' . $sorttablecss . '>
        <thead>
        ' . $table_colname_html . '
        </thead>
        <tfoot>
        ' . $table_colname_html . '
        </tfoot>
        <tbody>
        ' . $data_table_row . '
        </tbody>
        </table>
        ';




        } else {
            // $tr['There is no information under the query conditions'] = '查詢條件下無任何資料。';
            $return_string = $tr['There is no information under the query conditions'];
        }

        // 輸出
        echo $return_string;
    }

} elseif ($action == 'member_create' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
// ----------------------------------------------------------------------------
    // Member_Create.php 建立會員帳號
    // ----------------------------------------------------------------------------
    // var_dump($_POST);

    // ------------------
    // 檢查會員欄位資料是否正確
    // use: memberaccount_create_check(account_create_input)
    // ------------------
    /*yaoyuan20171017目前沒看到memberaccount_create_check這個函數，在member.php沒有呼叫
    function memberaccount_create_check($account_create_input) {

        // 有資料才作業
        if ($account_create_input != NULL) {

            $account_create_input = filter_var($account_create_input, FILTER_SANITIZE_STRING);

            // 限制帳號只能為 a-z A-Z 0-9 _ 等文字符號
            $re = "/^[a-zA-Z][a-z-A-Z_0-9]{2,19}/s";
            preg_match($re, $account_create_input, $matches);
            if ($matches == NULL) {
                // $tr['Account is not legal. Account number can only be a-z A-Z 0-9 _ and other text symbols. And the first letter must be an English letter. Length of 3 characters or more'] = '帳號不合法，帳號只能為 a-z A-Z 0-9 _ 等文字符號組成。且第一個字母需為英文字母。長度 3 個字元以上。';
                // $account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>' . $tr['Account is not legal. Account number can only be a-z A-Z 0-9 _ and other text symbols. And the first letter must be an English letter. Length of 3 characters or more'] . '</div>';
                $account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>帳號不合法，帳號只能為 a-z A-Z 0-9 _ 等文字符號組成。且第一個字母需為英文字母。長度 3 個字元以上。</div>';
                $account_check_return['code'] = 3;
            } else {
                // 可以使用的帳號, check 是否有重複
                $sql = "SELECT * FROM root_member WHERE account = '" . $account_create_input . "';";
                $r = runSQLALL($sql);
                // 如果有帳號存在, 就是此帳號不合法
                if ($r[0] >= 1) {
                    $account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>帳號不可使用。</div>';
                    $account_check_return['code'] = 2;
                    // var_dump($r);
                } else {
                    $account_check_return['text'] = '<div class="alert alert-success" role="alert"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span>帳號可使用。</div>';
                    $account_check_return['code'] = 1;
                    $account_check_return['account'] = $account_create_input;
                }
            }

        } else {
            $account_check_return['text'] = '';
            $account_check_return['code'] = 0;
        }

        return ($account_check_return);
    }*/
    // ------------------

    // ------------------
    // 檢查代理商欄位資料是否正確
    // use: agentaccount_create_check($account_create_input)
    // ------------------
    /*yaoyuan20171017目前沒看到agentaccount_create_check這個函數，在member.php沒有呼叫
    function agentaccount_create_check($account_create_input) {

        // 有資料才作業
        if ($account_create_input != NULL) {

            $account_create_input = filter_var($account_create_input, FILTER_SANITIZE_STRING);
            // var_dump($_POST);
            // 限制帳號只能為 a-z A-Z 0-9 _ 等文字符號
            $re = "/^[a-zA-Z][a-z-A-Z_0-9]{2,19}/s";
            preg_match($re, $account_create_input, $matches);
            if ($matches == NULL) {
                $account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>帳號不合法，帳號只能為 a-z A-Z 0-9 _ 等文字符號組成。且第一個字母需為英文字母。長度 2 個字元以上。</div>';
                $account_check_return['code'] = 3;
            } else {
                // 可以使用的帳號, check 是否有重複. 只有 代理商身份才可以加入 <
                $sql = "SELECT * FROM root_member WHERE therole = 'R' AND account = '" . $account_create_input . "';";
                //var_dump($sql);
                $r = runSQLALL($sql);
                // var_dump($r);
                // 如果有帳號存在, 才可以新增帳號
                if ($r[0] == 1) {
                    $account_check_return['text'] = '<div class="alert alert-success" role="alert"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span>此代理商存在,查詢代理商 <a href="member_account.php?a=' . $r[1]->id . '" target="_NEW">' . $r[1]->account . '</a></div>';
                    $account_check_return['code'] = 1;
                    $account_check_return['account'] = $account_create_input;
                    $account_check_return['id'] = $r[1]->id;
                } else {
                    $account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>此代理商不存在。</div>';
                    $account_check_return['code'] = 2;
                }
            }

        } else {
            $account_check_return['text'] = '';
            $account_check_return['code'] = 0;
        }

        return ($account_check_return);
    }
    */

    // ------------------
    // 檢查 會員帳號欄位 , 必要欄位式否有填，及是否按下建立帳號的按鈕。
    // ------------------
    if (isset($_POST['submit_to_member_create']) AND $_POST['submit_to_member_create'] == 'admincreateaccount' AND (isset($_POST['memberaccount_create_input']) AND $_POST['memberaccount_create_input'] != NULL) AND (isset($_POST['agentaccount_create_input']) AND $_POST['agentaccount_create_input'] != NULL)) {
        // ------------------
        // echo '建立帳號';
        // ------------------
        $check_memberaccount = memberaccount_create_check($_POST['memberaccount_create_input']);
        $check_agentaccount = agentaccount_create_check($_POST['agentaccount_create_input']);

        // 會員帳號、代理商正確, 將資料加入資料庫內
        //$tr['current account agent'] = '會員帳號、代理商正確.';
        if ($check_memberaccount['code'] == 1 and $check_agentaccount['code'] == 1) {
            echo $tr['current account agent'];
            // 預設密碼 12345678
            $user['memberaccount'] = $check_memberaccount['account'];
            $user['agentaccount'] = $check_agentaccount['account'];
            $user['parent_id'] = $check_agentaccount['id'];
            $user['therole'] = 'M'; // 會員
            $user['default_password'] = sha1('12345678');
            $user['withdrawalspassword'] = sha1('12345678');
            $user['realname_input'] = filter_var($_POST['realname_input'], FILTER_SANITIZE_STRING);
            $user['mobilenumber_input'] = filter_var($_POST['mobilenumber_input'], FILTER_SANITIZE_STRING);
            $user['sex_input'] = filter_var($_POST['sex_input'], FILTER_SANITIZE_NUMBER_INT);
            $user['email_input'] = filter_var($_POST['email_input'], FILTER_SANITIZE_EMAIL);
            $user['birthday_input'] = filter_var($_POST['birthday_input'], FILTER_SANITIZE_STRING);
            $user['wechat_input'] = filter_var($_POST['wechat_input'], FILTER_SANITIZE_STRING);
            $user['qq_input'] = filter_var($_POST['qq_input'], FILTER_SANITIZE_NUMBER_INT);
            $user['notes_input'] = filter_var($_POST['notes_input'], FILTER_SANITIZE_STRING);
            $user['timezone'] = 'Asia/Hong_Kong';
            $user['lang'] = 'zh-cn';
            $user['status'] = '1';

            // var_dump($user);
            $sql = 'INSERT INTO "root_member" ("account", "nickname", "realname", "passwd", "changetime", "creditcurrency", "mobilenumber", "email", "lang", "status", "therole", "parent_id", "notes",
                 "sex", "birthday", "wechat", "qq", "bankaccount", "bankname", "bankprovince", "bankcounty", "withdrawalspassword", "timezone", "bonusrule", "lastlogin", "lastseclogin", "lastbetting", "lastsecbetting", "grade", "enrollmentdate", "grade", "favorablerule") ';
            $sql = $sql . "    VALUES ('" . $user['memberaccount'] . "', NULL, '" . $user['realname_input'] . "', '" . $user['default_password'] . "', 'now()', '" . $config['currency_sign']  . "', '" .  $user['mobilenumber_input'] . "'
            , '" . $user['email_input'] . "', '" . $user['lang'] . "', '" . $user['status'] . "', '" . $user['therole'] . "', '" . $user['parent_id'] . "', '" . $user['notes_input'] . "'
            , '" . $user['sex_input'] . "', '" . $user['birthday_input'] . "', '" . $user['wechat_input'] . "', '" . $user['qq_input'] . "', NULL, NULL, NULL, NULL, '" . $user['withdrawalspassword'] . "', '" . $user['timezone'] . "', NULL, NULL, NULL, NULL, NULL, NULL, 'now()', '1', 'basic');";
            // echo $sql;
            //$tr['Administrator established'] = '管理員建立,';$tr['Account is completed'] = '帳號完成';
            $insertresult = runSQL($sql);
            if ($insertresult == 1) {
                $logger = $tr['Administrator established'].$user['memberaccount'] . ',' . $tr['Account is completed'];
                memberlog2db($_SESSION['agent']->account, 'member create', 'notice', "$logger");
                // 提示，並 reload page
                echo $logger . '<script>alert("' . $logger . '");window.location.reload();</script>';
            }
        }

        // var_dump($_POST);

    } elseif (isset($_POST['memberaccount_create_input']) AND $_POST['memberaccount_create_input'] != NULL) {
        // ------------------
        // 檢查 會員帳號欄位
        // ------------------

        $memberaccount_create_check_return = memberaccount_create_check($_POST['memberaccount_create_input']);
        echo $memberaccount_create_check_return['text'];

    } elseif (isset($_POST['agentaccount_create_input']) AND $_POST['agentaccount_create_input'] != NULL) {
        // ------------------
        // 檢查代理商帳號欄位
        // ------------------

        $agentaccount_create_check_return = agentaccount_create_check($_POST['agentaccount_create_input']);
        echo $agentaccount_create_check_return['text'];

    } else {
        echo '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>請填入資料</div>';
    }

// ----------------------------------------------------------------------------
} elseif ($action == 'memberdeposit' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
// ----------------------------------------------------------------------------
    // 人工存入功能，只有代理商能幫他的第一代下線增加額度，管理員可以幫代理商做此動作。管理員可以賦予代理商儲值額度。
    // ----------------------------------------------------------------------------
    // 1. 管理員R 可以轉帳給所有的 代理商A , 管理員本身也具有代理商身份。
    // 2. 管理員R 可以幫忙，代理商轉錢給會員 -- todo
    // 3. 檢查帳號是否為登入者的下線，如果是下線的話就可以轉帳

    // var_dump($_POST);
    $submit_to_memberdeposit_input = filter_var($_POST['submit_to_memberdeposit_input'], FILTER_SANITIZE_STRING);
    //$submit_to_memberdeposit_input = 'admin_memberdeposit';

    // 取得動作選項
    if ($submit_to_memberdeposit_input == 'check_account_memberdeposit') {
        // 檢查會員帳號
        $destination_transferaccount_input = filter_var($_POST['destination_transferaccount_input'], FILTER_SANITIZE_STRING);
        // 查詢會員及代理商身份，且條件符合的
        $sql = "SELECT * FROM root_member WHERE  account = '" . $destination_transferaccount_input . "';";
        $r = runSQLALL($sql);

        if ($r[0] == 1) {
            // 如果登入者身份，和會員的 parent_id 身份一樣的話，就可以進行轉帳的動作。(就是直屬代理與會員關係--一代)
            if ($_SESSION['agent']->id == $r[1]->parent_id) {//$tr['Directly under the agency relationship between members and set up, you can make a transfer'] = '直屬代理與會員關係成立，可以進行轉帳。';
                echo $tr['Directly under the agency relationship between members and set up, you can make a transfer'];
                //var_dump($r[1]->parent_id);
                //var_dump($_SESSION['agent']->id);
            } else {//$tr['Directly under the agency relationship between members and does not hold, it can not transfer'] = '直屬代理與會員關係不成立，不可以轉帳。';
                echo $tr['Directly under the agency relationship between members and does not hold, it can not transfer'];
                $sql2 = "SELECT * FROM root_member WHERE  id = '" . $r[1]->parent_id . "';";
                $r2 = runSQLALL($sql2);
                if ($r2[0] == 1) {//$tr['This account agents'] = '此帳號的代理商為';
                    echo $tr['This account agents'] . $r2[1]->account . '。';
                    // var_dump($r2);
                }
            }
        } else {//$tr['No account'] = '無此帳號';
            echo $tr['No account'];
        }

    } elseif ($submit_to_memberdeposit_input == 'admin_memberdeposit') {
        //
        // 檢查所有欄位後，進行轉帳的動作。
        //
        // var_dump($_POST);

        // 轉帳操作人員，只能是管理員或是會員的上線使用者.
        $member_id = $_SESSION['agent']->id;
        // 娛樂城代號
        $casino = 'gpk2';
        // 來源轉帳帳號
        $source_transferaccount = filter_var($_POST['source_transferaccount_input'], FILTER_SANITIZE_STRING);
        // 目的轉帳帳號
        $destination_transferaccount = filter_var($_POST['destination_transferaccount_input'], FILTER_SANITIZE_STRING);
        // 轉帳金額，需要依據會員等級限制每日可轉帳總額。
        $transaction_money = filter_var($_POST['balance_input'], FILTER_SANITIZE_NUMBER_INT);
        // 摘要資訊
        $summary = filter_var($_POST['summary_input'], FILTER_SANITIZE_STRING);
        // 實際存提
        $realcash = filter_var($_POST['realcash_input'], FILTER_SANITIZE_NUMBER_INT);
        // 稽核模式，三種：免稽核、存款稽核、優惠存款稽核
        $auditmode_select = filter_var($_POST['auditmode_select_input'], FILTER_SANITIZE_STRING);
        // 稽核金額
        $auditmode_amount = filter_var($_POST['auditmode_input'], FILTER_SANITIZE_NUMBER_INT);
        // 來源帳號的密碼驗證，驗證後才可以存款
        $password_verify_sha1 = filter_var($_POST['password_input'], FILTER_SANITIZE_STRING);
        // 系統轉帳文字資訊
        $system_note_input = filter_var($_POST['system_note_input'], FILTER_SANITIZE_STRING);

        $gpk2_memberdeposit_transfer_result = gpk2_memberdeposit_transfer($member_id, $source_transferaccount, $destination_transferaccount, $transaction_money, $summary, $realcash, $auditmode_select, $auditmode_amount, $password_verify_sha1, $system_note_input);
        if ($gpk2_memberdeposit_transfer_result == 1) {
            // 轉帳成功
            $logger = $source_transferaccount . ',' . $summary . ',' . $destination_transferaccount . ',' . $transaction_money . 'success.';
            memberlog2db($_SESSION['agent']->account, 'member deposit', 'notice', "$logger");
            // 提示，並 reload page
            echo $logger . '<script>alert("' . $logger . '");window.location.reload();</script>';
        } else {
            // 轉帳失敗
            $logger = $source_transferaccount . ',' . $summary . ',' . $destination_transferaccount . ',' . $transaction_money . 'false.';
            memberlog2db($_SESSION['agent']->account, 'member deposit', 'notice', "$logger");
            // 提示，並 reload page
            echo $logger . '<script>alert("' . $logger . '");window.location.reload();</script>';
        }

    } else {
        // nothing
    }

// ---------------------------------------------------------------------------
} elseif ($action == 'change_member_password' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
// ----------------------------------------------------------------------------
    // 修改會員密碼
    // ----------------------------------------------------------------------------

    // var_dump($_POST);

    $pk = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
    $current_password = filter_var($_POST['current_password'], FILTER_SANITIZE_STRING);
    $change_password_valid1 = filter_var($_POST['change_password_valid1'], FILTER_SANITIZE_STRING);
    $change_password_valid2 = filter_var($_POST['change_password_valid2'], FILTER_SANITIZE_STRING);

    $memberid_sql = "SELECT id, account FROM root_member WHERE id = '" . $pk . "';";
    $memberid_sql_result = runSQLall($memberid_sql);

    // 如果兩個密碼一樣, 才進行修改。
    if ($memberid_sql_result[0] >= 1 AND $memberid_sql_result[1]->id == $pk AND $change_password_valid1 == $change_password_valid2) {
        $update_password_sql = "UPDATE root_member SET passwd = '" . $change_password_valid1 . "'  WHERE id = '" . $pk . "' AND passwd = '" . $current_password . "';";
        $update_password_sql_result = runSQL($update_password_sql);
        if ($update_password_sql_result == 1) {
            $logger = "Member id = $pk change password to $change_password_valid1 success.";
            memberlog2db($memberid_sql_result[1]->account, 'member', 'notice', "$logger");
            $logger = $tr['Member personal password modification is completed'];//$tr['Member personal password modification is completed'] = '會員個人密碼修改完成。';
            // echo $logger;
            echo '<script>alert("' . $logger . '");location.reload();</script>';
        } else {//$tr['member personal password is now incorrectly entered'] = '會員個人現在的密碼輸入錯誤。';
            $logger = $tr['member personal password is now incorrectly entered'];
            // echo $logger;
            echo '<script>alert("' . $logger . '");location.reload();</script>';
        }

    } else {//$tr['The new password, before and after the input is not the same, please re-enter'] = '新的密碼，前後輸入不一樣，請重新輸入。';
        $logger = $tr['The new password, before and after the input is not the same, please re-enter'];
        // echo $logger;
        echo '<script>alert("' . $logger . '");location.reload();</script>';
    }

// ----------------------------------------------------------------------------
} else if ($action == 'notes_common_update' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
// ----------------------------------------------------------------------------
    // 更新會員備註
    // ----------------------------------------------------------------------------

    // var_dump($_POST);

    $pk = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
    $notes = filter_var($_POST['notes'], FILTER_SANITIZE_STRING);

    $update_note_sql = "UPDATE root_member SET notes = '" . $notes . "' WHERE id = '" . $pk . "';";
    // var_dump($review_sql);
    $update_note_sql_result = runSQLtransactions($update_note_sql);
    // var_dump($update_note_sql_result);

    if ($update_note_sql_result == 1) {
        // 更新 notes $tr['Update processing information content article'] = '更新處理資訊內容文章';
        $logger = $tr['Update processing information content article'];
    } else {
        // 系統錯誤 $tr['Update unsuccessful error, please contact the maintenance staff'] = '更新未成功錯誤，請聯絡維護人員處理。';
        $logger = $tr['Update unsuccessful error, please contact the maintenance staff'];
    }
    echo '<script>alert("' . $logger . '");location.reload();</script>';die();

// ----------------------------------------------------------------------------
} elseif ($action == 'change_parent' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  //var_dump($_POST);
     $user_id = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);

     $check_agentaccount = agentaccount_check($_POST['parent']);

     if($check_agentaccount['code'] == 1) {
         if ($user_id != '') {
             $member_sql = "SELECT * FROM root_member WHERE id = '".$user_id."' and status = '1'; ";
             $member_sql_result = runSQLall($member_sql);

             if ($member_sql_result[0] == 1) {
                // 只有 $user_id 為會員身份, 才可以改變上層代理商, 因為代理商有用金關係, 移動有機會會出問題
                if($member_sql_result[1]->therole == 'M') {

                     if ($check_agentaccount['id'] != $member_sql_result[1]->id) {
                         if ($check_agentaccount['parent_id'] != $member_sql_result[1]->id) {
                             $column_name = 'parent_id';
                             $success_msg = $tr['upper reference to amend the completion'];//$tr['upper reference to amend the completion'] = '上層推薦人修改完成。';
                             $failed_msg = $tr['upper reference failed to modify'];//$tr['upper reference failed to modify'] = '上層推薦人修改失敗。';

                             update_setting($user_id, $column_name, $check_agentaccount['id'], $success_msg, $failed_msg);
                         } else {
                             //$tr['The relationship has been off the assembly line, Member'] = '已有上下線關係，會員'; $tr['Can not be a member'] = '不可為會員';
                             $logger = $tr['The relationship has been off the assembly line, Member'].$member_sql_result[1]->account.$tr['Can not be a member'].$check_agentaccount['account'].$tr['downline'].'。';
                             // echo '<script>alert("' . $logger . '");</script>';
                             echo '<div class="text-danger">'.$logger.'</div>';
                         }
                     } else {
                         // $tr['The top of the recommendation can not be for themselves'] = '上層推薦人不可為自己。';
                         $logger = $tr['The top of the recommendation can not be for themselves'];
                         // echo '<script>alert("' . $logger . '");</script>';
                         echo '<div class="text-danger">'.$logger.'</div>';
                     }

                }else{
                    //$tr['Only members who are active and enabled can change referrals'] = '只有會員身份且啟用中的使用者，才可以變更推薦人。';
                    $logger = $tr['Only members who are active and enabled can change referrals'];
                    // echo '<script>alert("' . $logger . '");</script>';
                    echo '<div class="text-danger">'.$logger.'</div>';
                }

             } else {
                 // $tr['Member information query error or the member has not been enabled'] = '會員資料查詢錯誤或是該會員尚未被啟用。';
                 $logger = $tr['Member information query error or the member has not been enabled'];
                 // echo '<script>alert("' . $logger . '");</script>';
                 echo '<div class="text-danger">'.$logger.'</div>';
             }
         } else {//$tr['Wrong attempt'] = '(x)錯誤的嘗試。';
             $logger = $tr['Wrong attempt'];
             // echo '<script>alert("' . $logger . '");</script>';
             echo '<div class="text-danger">'.$logger.'</div>';
         }
     } else {
         echo $check_agentaccount['text'];
     }



// ---------------------------------------------------------------------------
} elseif ($action == 'change_member_status' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

// var_dump($_POST);

    $user_id = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
    $status_name = filter_var($_POST['status_name'], FILTER_SANITIZE_STRING);

    if ($status_name == $tr['account disable']) {
        $status = '0';
    } elseif ($status_name == $tr['account valid']) {
        $status = '1';
    } elseif ($status_name == $tr['account freeze']) {
        $status = '2';
    } elseif ($status_name == $tr['blocked']) {
        $status = '3';
    } elseif ($status_name == $tr['auditing']) {
        $status = '4';
    } else {
        // $tr['Member level query error'] ='會員等級查詢錯誤。';
        $logger = $tr['Member level query error'];
        echo '<script>alert("' . $logger . '");location.reload();</script>';
    }

    if ($status != '') {
        $member_sql = "SELECT * FROM root_member WHERE id = '".$user_id."';";
        $member_sql_result = runSQLall($member_sql);

        if ($member_sql_result[0] == 1) {
            $column_name = 'status';
            // $tr['Member account status is modified'] = '會員帳號狀態修改完成。'; $tr['Member account status modification failed'] = '會員帳號狀態修改失敗。';
            $success_msg = $tr['Member account status is modified'];
            $failed_msg = $tr['Member account status modification failed'];

            update_setting($user_id, $column_name, $status, $success_msg, $failed_msg);
        } else {
            // $tr['Member information query error'] = '會員資料查詢錯誤。';
            $logger = $tr['Member information query error'];
            echo '<script>alert("' . $logger . '");</script>';
        }
    } else {
        // 錯誤嘗試 $tr['Wrong attempt'] = '(x)錯誤的嘗試。';
        $logger = $tr['Wrong attempt'];
        echo '<script>alert("' . $logger . '");location.reload();</script>';
    }

// ---------------------------------------------------------------------------
} elseif ($action == 'change_mamber_grade' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
    // var_dump($_POST);

    $user_id = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
    $grade_name = filter_var($_POST['grade_name'], FILTER_SANITIZE_STRING);

    if ($grade_name != '') {

        $grade_sql = "SELECT * FROM root_member_grade WHERE gradename = '" . $grade_name . "';";
        $grade_sql_result = runSQLALL($grade_sql);

        if ($grade_sql_result[0] == 1) {
            $member_sql = "SELECT * FROM root_member WHERE id = '".$user_id."';";
            $member_sql_result = runSQLall($member_sql);

            if ($member_sql_result[0] == 1) {
                $column_name = 'grade';
                // $tr['Member account level is modified'] = '會員帳號等級修改完成。'; $tr['Member account level modification failed'] = '會員帳號等級修改失敗。';
                $success_msg = $tr['Member account level is modified'];
                $failed_msg = $tr['Member account level modification failed'];

                update_setting($user_id, $column_name, $grade_sql_result[1]->id, $success_msg, $failed_msg);
            } else {
                // $tr['Member information query error'] = '會員資料查詢錯誤。';
                $logger = $tr['Member information query error'];
                echo '<script>alert("' . $logger . '");</script>';
            }
        } else {
            // $tr['there is no membership level information'] = '查無此會員等級資訊。';
            $logger = $tr['there is no membership level information'];
            echo '<script>alert("' . $logger . '");</script>';
        }

    } else {
        // 錯誤嘗試 $tr['Wrong attempt'] = '(x)錯誤的嘗試。';
        $logger = $tr['Wrong attempt'];
        echo '<script>alert("' . $logger . '");location.reload();</script>';
    }

// ---------------------------------------------------------------------------
} elseif ($action == 'change_mamber_preferential_name' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
    // var_dump($_POST);

    $user_id = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
    $preferentia_name = filter_var($_POST['preferential_name'], FILTER_SANITIZE_STRING);

    if ($preferentia_name != '') {
        $member_sql = "SELECT * FROM root_member WHERE id = '".$user_id."';";
        $member_sql_result = runSQLall($member_sql);

        if ($member_sql_result[0] == 1) {
            $column_name = 'favorablerule';
            // $tr['Member account bouns level is modified'] = '會員帳號反水等級修改完成。'; $tr['Member account bouns level changes failed'] = '會員帳號反水等級修改失敗。';
            $success_msg = $tr['Member account bouns level is modified'];
            $failed_msg = $tr['Member account bouns level changes failed'];

            update_setting($user_id, $column_name, $preferentia_name, $success_msg, $failed_msg);
        } else {
            // $tr['Member information query error'] = '會員資料查詢錯誤。';
            $logger = $tr['Member information query error'];
            echo '<script>alert("' . $logger . '");</script>';
        }
    } else {
        // 錯誤嘗試 $tr['Wrong attempt'] = '(x)錯誤的嘗試。';
        $logger = $tr['Wrong attempt'];
        echo '<script>alert("' . $logger . '");location.reload();</script>';
    }

// ---------------------------------------------------------------------------
} elseif ($action == 'change_mamber_commission_name' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
    // var_dump($_POST);

    $user_id = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
    $commission_name = filter_var($_POST['commission_name'], FILTER_SANITIZE_STRING);

    if ($commission_name != '') {
        $member_sql = "SELECT * FROM root_member WHERE id = '".$user_id."';";
        $member_sql_result = runSQLall($member_sql);

        if ($member_sql_result[0] == 1) {
            $column_name = 'commissionrule';
            // $tr['Member account commission set to complete the modification'] = '會員帳號佣金設定修改完成。';$tr['Member account commission set to amend the failure'] = '會員帳號佣金設定修改失敗。';
            $success_msg = $tr['Member account commission set to complete the modification'];
            $failed_msg = $tr['Member account commission set to amend the failure'];

            update_setting($user_id, $column_name, $commission_name, $success_msg, $failed_msg);
        } else {
            // $tr['Member information query error'] = '會員資料查詢錯誤。';
            $logger = $tr['Member information query error'];
            echo '<script>alert("' . $logger . '");</script>';
        }
    } else {
        // 錯誤嘗試 $tr['Wrong attempt'] = '(x)錯誤的嘗試。';
        $logger = $tr['Wrong attempt'];
        echo '<script>alert("' . $logger . '");location.reload();</script>';
    }

// ----------------------------------------------------------------------------
} elseif ($action == 'select_member_grade'  AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
    $member_grade_list = filter_var($_POST['select_member_grade'], FILTER_SANITIZE_STRING);

    if ($member_grade_list != '') {
        $grade_sql = "SELECT id, gradename FROM root_member_grade  ORDER BY id;";
        $grade_sql_result = runSQLall($grade_sql);

        if ($grade_sql_result[0] >= 1) {
            for ($i = 1; $i <= $grade_sql_result[0]; $i++) {
                $grade[$grade_sql_result[$i]->id] = $grade_sql_result[$i]->gradename;
            }

            // $member_grade_list = $_POST['select_member_grade'];
            $member_grade_list = str_replace('member_grade_checkbox=', '', $member_grade_list);
            $member_grade_list = explode("&", $member_grade_list);

            if ($member_grade_list[0] == '') {
                $return_string = '<p>'.$tr['Please select a member level'].'</p>';//$tr['Please select a member level'] = '請選擇會員等級';
            } else {

                switch (count($member_grade_list)) {
                case 1:
                    $return_string = '<p>' . $grade[$member_grade_list[0]] . '</p>';
                    break;
                case 2:
                    $return_string = '<p>' . $grade[$member_grade_list[0]] . '、' . $grade[$member_grade_list[1]] . '</p>';
                    break;
                case count($grade):
                $return_string = '<p>全选</p>';
                    break;
                default: //$tr['Total'] = '共';
                    $return_string = '<p>' . $grade[$member_grade_list[0]] . '、' . $grade[$member_grade_list[1]] . '......等'.$tr['total']. count($member_grade_list) . '個</p>';
                    break;
                }

            }

        } else {
            $return_string = '<p>'.$tr['Member level query error'].'</p>';//$tr['Member level query error'] = '會員等級查詢錯誤。';
        }

    } else {//$tr['Please select a member level'] = '請選擇會員等級';
        $return_string = '<p>'.$tr['Please select a member level'].'</p>';
    }

    // var_dump($id_num);

    echo $return_string;
// ----------------------------------------------------------------------------
// } elseif ($action == 'edit_commission_percen'  AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
//     // var_dump($_POST);
//     $id = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
//     $account = filter_var($_POST['acc'], FILTER_SANITIZE_STRING);
//     $commission_percen = filter_var($_POST['commission_percen'], FILTER_SANITIZE_STRING);

//     if ($id != '' AND $account != '' AND $commission_percen != '') {
//         $check_member_sql = "SELECT * FROM root_member WHERE id = '".$id."' AND account = '".$account."';";
//         $check_member_sql_result = runSQLall($check_member_sql);

//         if ($check_member_sql_result[0] == 1) {
//             if ($commission_percen >= 0.1 AND $commission_percen <= 0.9) {
//                 $column_name = 'dividendratio';
//                 $success_msg = '會員 '.$account.' 拆佣比修改完成。';
//                 $failed_msg = '會員 '.$account.' 拆佣比修改失敗。';
//                 update_setting($id, $column_name, $commission_percen, $success_msg, $failed_msg);

//             } else {
//                 $logger = "錯誤的拆佣比，請重新確認拆佣比是否正確。";
//                 echo '<script>alert("' . $logger . '");</script>';
//             }
//         } else {
//             $logger = "查無此會員或非下線會員。";
//             echo '<script>alert("' . $logger . '");</script>';
//         }
//     } else {
//         $logger = "送出的資料不合法";
//         echo '<script>alert("' . $logger . '");</script>';
//     }

// ----------------------------------------------------------------------------
// } elseif($action == 'agent_check' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
//     // var_dump($_POST['parent']);
//     if(isset($_POST['parent']) AND $_POST['parent'] != NULL ) {
//     $agentaccount_check_return = agentaccount_check($_POST['parent']);
//     //var_dump($agentaccount_create_check_return);
//     echo $agentaccount_check_return['text'];
//   }

// ----------------------------------------------------------------------------
} elseif ($action == 'excel') { // 匯出 EXCEL
    $idStr = filter_var($_GET['id'], FILTER_SANITIZE_STRING);
    $idStr = rtrim($idStr, ', ');
    $idArr = explode(',', $idStr);
    $sql = 'SELECT *, w.changetime AS "wchange" FROM root_member m LEFT JOIN root_member_wallets w ON m.id = w.id WHERE m.id IN (';
    for ($i = 0; $i < count($idArr); $i++) {
        if ($i == count($idArr) - 1) {
            $sql .= '\''. $idArr[$i] .'\');';
        } else {
            $sql .= '\''. $idArr[$i] .'\', ';
        }
    }
    $result = runSQLall($sql, $debug);
    if ($result[0] > 0) {
        // 清快取
        ob_end_clean();
        // 組 CSV
        $fileName = 'members_export_'. date("YmdHis") .'.csv';
        $filePath = dirname(__FILE__) . '/tmp_dl/members_export_'. date("YmdHis") .'.csv';
        $csvStream = new CSVWriter($filePath);
        $csvStream->begin();
        // 標題列
        $csvStream->writeRow([
            $tr['ID'],
            $tr['Account'],
            $tr['nickname'],
            $tr['changetime'],
            $tr['creditcurrency'],
            $tr['mobilenumber'],
            $tr['Email'],
            $tr['lang'],
            $tr['status'],
            $tr['therole'],
            $tr['parent_id'],
            $tr['notes'],
            $tr['sex'],
            $tr['birthday'],
            $protalsetting["custom_sns_rservice_1"]??$tr['sns1'],
            $protalsetting["custom_sns_rservice_2"]??$tr['sns2'],
            $tr['bankaccount'],
            $tr['bankname'],
            $tr['bankprovince'],
            $tr['bankcounty'],
            $tr['withdrawalspassword'],
            $tr['timezone'],
            $tr['favorablerule'],
            $tr['lastlogin'],
            $tr['lastseclogin'],
            $tr['lastbetting'],
            $tr['lastsecbetting'],
            $tr['grade'],
            $tr['enrollmentdate'],
            $tr['registerfingerprinting'],
            $tr['registerip'],
            $tr['recommendedcode'],
            $tr['salt'],
            $tr['commissionrule'],
            $tr['permission'],
            $tr['feedbackinfo'],
            $tr['becomeagentdate'],
            $tr['allow_login_passwordchg'],
            $tr['first_deposite_date'],
            $tr['wchange'],
            $tr['gcash_balance'],
            $tr['gtoken_balance'],
            $tr['gtoken_lock'],
            $tr['auto_gtoken'],
            $tr['auto_min_gtoken'],
            $tr['auto_once_gotken'],
            $tr['casino_accounts'],
            $tr['recivemoney_count']
        ]);
        // 資料
        for ($i = 1; $i <= count($result) - 1; $i++) {
            $csvStream->writeRow([
                $result[$i]->id, // ID 序號
                $result[$i]->account, // 會員帳號
                $result[$i]->nickname, // 暱稱
                $result[$i]->changetime, // 變動時間
                $result[$i]->creditcurrency, // 貨幣符號
                $result[$i]->mobilenumber, // 手機電話號碼
                $result[$i]->email, // Email
                $result[$i]->lang, // 使用者預設語言
                $result[$i]->status, // 帳號狀態
                $result[$i]->therole, // 身份
                $result[$i]->parent_id, // 上層
                $result[$i]->notes, // 使用者註記
                $result[$i]->sex, // 性別
                $result[$i]->birthday, // 生日
                $result[$i]->wechat, // wechat ID
                $result[$i]->qq, // qq ID
                $result[$i]->bankaccount, // 銀行號碼
                $result[$i]->bankname, // 銀行名稱
                $result[$i]->bankprovince, // 銀行省份
                $result[$i]->bankcounty, // 銀行縣市
                $result[$i]->withdrawalspassword, // 取款密碼
                $result[$i]->timezone, // 所在時區
                $result[$i]->favorablerule, // 反水设定
                $result[$i]->lastlogin, // 使用者最後登入時間
                $result[$i]->lastseclogin, // 最後第二次登入時間
                $result[$i]->lastbetting, // 最後登入投注網站時間
                $result[$i]->lastsecbetting, // 最後第二次登入投注網站時間
                $result[$i]->grade, // 會員入款、存款、稽核等級設定
                $result[$i]->enrollmentdate, // 註冊時間
                $result[$i]->registerfingerprinting, // 註冊的瀏覽器指紋
                $result[$i]->registerip, // 註冊的IP
                $result[$i]->recommendedcode, // 會員推薦碼
                $result[$i]->salt, // 傳遞資料時加密的salt
                $result[$i]->commissionrule, // 代理商佣金等級設定
                $result[$i]->permission, // 頁面權限設定JSON
                $result[$i]->feedbackinfo, // 反水、代理分紅的比例設定
                $result[$i]->becomeagentdate, // 成為代理的時間
                $result[$i]->allow_login_passwordchg, // 登入強制變更密碼開關
                $result[$i]->first_deposite_date, // 會員首充時間
                $result[$i]->wchange, // 會員錢包變動時間
                $result[$i]->gcash_balance, // gcash現金錢包
                $result[$i]->gtoken_balance, // gtoken代幣錢包
                $result[$i]->gtoken_lock, // 代幣gtoken在娛樂城
                $result[$i]->auto_gtoken, // 自動儲值
                $result[$i]->auto_min_gtoken, // 最低自動轉帳餘額
                $result[$i]->auto_once_gotken, // 每次儲值金額
                $result[$i]->casino_accounts, // 娛樂城錢包資訊
                $result[$i]->recivemoney_count // RG返點累計彩金池
            ]);
        }
        // 轉 excel
        $excelStream = new csvtoexcel($fileName, $filePath);
        $excelStream->begin();
        // 清除暫存檔案
        delete_upload_xls_tempfile($filePath);
        return;
    }
} elseif ($action == 'test') {
// ----------------------------------------------------------------------------
    // test developer
    // ----------------------------------------------------------------------------
    var_dump($_POST);
}


?>
