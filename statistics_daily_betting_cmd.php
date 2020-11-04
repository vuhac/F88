#!/usr/bin/php70
<?php
// ----------------------------------------------------------------------------
// Features:    後台-- 透過程式定時分析投注單內的資訊 -- 預設每 10 分鐘
// File Name:   statistics_daily_betting_cmd.php
// Author:      Webb Lu
// Related:
// Desc: 透過程式定時每 10 分鐘分析投注單內的資訊
// Log:
//
// ----------------------------------------------------------------------------
// How to run ?
// usage command line :
//  /usr/bin/php70 statistics_daily_betting_cmd.php run|test time_interval date time
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// session_start();

$stats_insert_count = 0;
$stats_update_count = 0;

// ----------------------------------------------------------------------------

require_once dirname(__FILE__) ."/config.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 投注紀錄檔 DB config 及 runSQLall_DB2 lib -- 搭配日結報表函式庫使用
require_once dirname(__FILE__) ."/config_betlog.php";

// set memory limit
ini_set('memory_limit', '200M');

// 確保這個 script 執行不會因為 user abort 而中斷!!
// Ignore user aborts and allow the script to run forever
ignore_user_abort(true);
// disable php time limit , 60*60 = 3600sec = 1hr
set_time_limit(7200);

// 程式 debug 開關, 0 = off , 1= on
$debug = 0;

// API 每次送出的最大數據筆數
// 用於進行帳號批次 LOCK 時使用
$api_limit = 1000;

// ----------------------------------
// 本程式使用的 function
// ----------------------------------
// -----------------------------------------------------------------------------
// 傳入指定日期和 Casion_ID, 輸出查詢到的指定統計時間
// -----------------------------------------------------------------------------
function stat_betting($current_datepicker, $chk_time_start, $chk_time_end) // 參數為美東時區
{
    // echo '<pre>', var_dump($current_datepicker, $chk_time_start, $chk_time_end), '</pre>'; exit();

    global $stats_config; // config內注單資料庫參數
    // echo '<pre>', var_dump($stats_config), '</pre>'; exit();

    // ################################################################################################################
    // 娛樂城的投注紀錄統計處理 , 10 分鐘內, 特定 casino 會員的投注資料, 投注量 account_betting 多少, 投注額 account_betvalid 多少 , 損益 account_profit 多少？
    // 這邊的 group 變成 by user 在區間內某個反水game分類的投注資料
    $stat_sql = <<<SQL
        -- 生成 10 分鐘區間內的暫存表
        WITH "interval_betrecordsremix" AS (
            SELECT "casino_account", -- 會員的游戲帳號
                   "casinoid", -- 娛樂城ID
                   "game_name", -- 遊戲名稱
                   "favorable_category", -- 返水分類
                   count("casino_account") as "account_betting_bycat", -- ??
                   sum("betvalid") as "account_betvalid_bycat", -- 有效投注金額
                   -sum("betresult") as "account_profit_bycat" -- 損益
            FROM "betrecordsremix"
            WHERE "receivetime" >= '{$chk_time_start}-04'
                AND "receivetime" < '{$chk_time_end}-04' -- 派彩時間(注單取得時間)
                AND "status" = '1' -- 已派彩
            GROUP BY "casino_account",
                     "game_name",
                     "casinoid",
                     "favorable_category"
            ORDER BY "casino_account",
                     "casinoid",
                     "favorable_category"
        )
        -- 輸出資料 = 表 1 (10 分鐘 page 所需資訊量 = #account * #casinoid) JOIN 表 2 (account 在每個遊戲分類的投注資訊)
        SELECT *
        FROM (
            SELECT "casino_account",
                   "casinoid",
                   sum("account_betting_bycat") as "account_betting",
                   sum("account_betvalid_bycat") as "account_betvalid",
                   sum("account_profit_bycat") as "account_profit"
            FROM "interval_betrecordsremix"
            GROUP BY "casino_account", "casinoid"
        ) AS "ACCOUNT_CASINO_TABLE"
        INNER JOIN ( -- 以遊戲名稱累計的有效投注額、損益金額
            SELECT "casino_account",
                   "casinoid",
                   array_to_json(array_agg("favorable_game_name")) AS "favorable_game_name_info"
            FROM (
                SELECT "casino_account",
                        "casinoid",
                        json_build_object(
                            'casinoid', -- 娛樂城id (json key)
                            "casinoid", -- 娛樂城id (json value)
                            'category', -- 遊戲分類 (json key)
                            "favorable_category", -- 遊戲分類 (json value)
                            'game_name', -- 遊戲名稱 (json key)
                            "game_name", -- 遊戲名稱 (json value)
                            'betvalid', -- 有效投注金額 (json key)
                            sum("account_betvalid_bycat"), -- 有效投注金額 (json value)
                            'betprofit', -- 損益金額 (json key)
                            sum("account_profit_bycat") -- 損益金額 (json value)
                        ) as "favorable_game_name"
                FROM "interval_betrecordsremix"
                GROUP BY "casino_account",
                            "game_name",
                            "casinoid",
                            "favorable_category"
                ORDER BY "casino_account",
                         "casinoid"
            ) AS "favorable_game_name_betinfo"
            GROUP BY "casino_account",
                    "casinoid"
        ) ACCOUNT_CASINO_GAME_TABLE2 USING(casino_account, casinoid)
        INNER JOIN ( -- 以遊戲分類累計的有效投注額、損益金額
            SELECT "casino_account",
                   "casinoid",
                   array_to_json(array_agg("favorable_category_info")) AS favorable_category
            FROM (
                SELECT "casino_account",
                       "casinoid",
                       json_build_object(
                            'betfavor',
                            "favorable_category",
                            'betvalid',
                            sum("account_betvalid_bycat"),
                            'betprofit',
                            sum("account_profit_bycat")
                        ) as "favorable_category_info"
                FROM "interval_betrecordsremix"
                GROUP BY "casino_account",
                         "casinoid",
                         "favorable_category"
            ) AS "favorable_category_betinfo"
            GROUP BY "casino_account",
                     "casinoid"
        ) ACCOUNT_CASINO_GAME_TABLE USING(casino_account, casinoid)
    SQL;

    // 取得混表資訊
    $stat_result = runSQLall_betlog($stat_sql, 0, $db_src='remix');
    // echo '<pre>', var_dump($stat_result), '</pre>'; exit();

    if ($stat_result[0] > 0) {
        unset($stat_result[0]); // 切除count索引
        // echo '<pre>', var_dump($stat_result), '</pre>'; exit();

        // 取得所有 casino_account
        $interval_accounts = [];
        foreach ($stat_result as $row) {
            $interval_accounts[$row->casinoid][] = $row->casino_account; // $interval_accounts[娛樂城id][有投注的帳號(s)]
        }
        // echo '<pre>', var_dump($interval_accounts), '</pre>'; exit();

        $stmt_member_format = <<<SQL
            SELECT "root_member_wallets"."id" AS "member_id",
                   "root_member"."account" AS "member_account",
                   "root_member"."parent_id" AS "member_parent_id",
                   "root_member"."therole" AS "member_therole",
                   "casino_accounts"->'%s'->>'account' AS "casino_account"
            FROM "root_member_wallets"
            INNER JOIN "root_member"
                ON ("root_member_wallets"."id" = "root_member"."id")
            WHERE "casino_accounts"->'%s'->>'account' IN (%s)
        SQL;

        foreach ($interval_accounts as $casino => $accounts) {
            $stmt[] = sprintf($stmt_member_format, $casino, $casino, "'" . implode("','", array_unique($accounts)) . "'");
        }
        $stmt_memberinfo = implode(' UNION ALL ', $stmt);
        $memberinfo = runSQLall($stmt_memberinfo);
        // echo '<pre>', var_dump($stmt_memberinfo, $memberinfo), '</pre>'; exit(); // test

        if($memberinfo[0] > 0) {
            unset($memberinfo[0]);

            $associative_members = [];
            foreach ($memberinfo as $row) {
                $associative_members[$row->casino_account] = $row;
            }
            // echo '<pre>', var_dump($associative_members), '</pre>'; exit(); // test
            // $associative_members = array_map(function($row) { return [$row->casino_account => $row];  }, $memberinfo);
        }

        $records = [];
        $count_member_not_exist = 0;
        foreach($stat_result as $_account_betting_info) {
            if( !isset($associative_members[$_account_betting_info->casino_account]) ) {
                $count_member_not_exist += 1;
                var_dump($_account_betting_info);
                continue;
            }

            $records[] = [
                'member_account'     => $associative_members[$_account_betting_info->casino_account]->member_account,
                'member_parent_id'   => $associative_members[$_account_betting_info->casino_account]->member_parent_id,
                'member_id'          => $associative_members[$_account_betting_info->casino_account]->member_id,
                'member_therole'     => $associative_members[$_account_betting_info->casino_account]->member_therole,
                'updatetime'         => 'now()',
                'dailydate'          => $current_datepicker,
                'dailytime_start'    => $chk_time_start,
                'dailytime_end'      => $chk_time_end,
                'account_betting'    => $_account_betting_info->account_betting,
                'account_betvalid'   => $_account_betting_info->account_betvalid,
                'account_profit'     => $_account_betting_info->account_profit,
                'casino_id'          => $_account_betting_info->casinoid,
                'favorable_category' => $_account_betting_info->favorable_category,
                'favorable_game_name'=> $_account_betting_info->favorable_game_name_info,
                'notes'              => ''
            ];
        }
    } else {
        $logger = "False, 娛樂城的投注紀錄統計處理{$chk_time_start} ~ {$chk_time_end}, 資料數量為{$stat_result[0]}";
        var_dump($logger);
        $records = false;
    }

    if ( isset($count_member_not_exist) ) {
        echo "\n{$count_member_not_exist} 筆無效 records\n";
    }

    return $records;
}
// -----------------------------------------------------------------------------

// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

// Preserve for cli --param=value
function arguments($argv)
{
    $ARG = [];
    foreach ($argv as $key => $value) {
        $val = $argv[$key];
        if (preg_match('/^[-]{1,2}([^=]+)=([^=]+)/', $val, $match)) $ARG[$match[1]] = $match[2];
    }
    return $ARG;
}

// ----------------------------------
// 本程式使用的 function END
// ----------------------------------

// -----------------------------------------------------------------
// 安全控管, 如果是 web 執行就立即中斷, 只允許 command 執行此程式。
// -----------------------------------------------------------------
// echo '<pre>', var_dump(PHP_SAPI), '</pre>'; exit();
if (PHP_SAPI != 'cli') {
    die('禁止使用網頁呼叫，來源錯誤，請使用命令列執行。');
}

// -----------------------------------------------------------------
// 命令列參數解析
// -----------------------------------------------------------------

// 取得今天的日期
// 轉換為美東的時間 date
$date_create = date_create(date('Y-m-d H:i:sP'), timezone_open('AST'));
// echo '<pre>', var_dump($date_create), '</pre>'; exit();

date_timezone_set($date_create, timezone_open('AST')); // 轉成美東(-04)時區
// echo '<pre>', var_dump( date_timezone_set($date_create, timezone_open('AST')) ), '</pre>'; exit();

$date = '';
$current_date = date_format($date_create, 'Y-m-d'); // 當前的美東時間date(-04)
// $argv[3] Y-m-d，且不能晚於現在時間(美東時區)
if ( !empty($argv[3]) ) {
    if ( validateDate($argv[3], 'Y-m-d') ) {
        if (strtotime($argv[3]) <= strtotime($current_date)) {
            $date = $argv[3];

            // 拆解日期為年、月、日，用來組成要更新的時間用
            $date_y = date('Y',strtotime($date));
            $date_m = date('m',strtotime($date));
            $date_d = date('d',strtotime($date));
        } else {
            die('parameter 3 can not be later than current date.');
        }
    } else { // 日期格式錯誤
        die('Wrong date formate.');
    }
} else { // 未接收到 $argv[3]
    die('Require parameter 3 as date(Y-m-d).');
}
// echo '<pre>', var_dump($date_y, $date_m, $date_d), '</pre>'; exit();


$time = '';
$current_time = date_format($date_create, 'H:i:s'); // 當前的美東時間time(-04)
// $argv[4] H:i:s，且不能晚於現在時間(美東時區)
if ( !empty($argv[4]) ) {
    if ( validateDate($argv[4], 'H:i:s') ) {
        if (strtotime($date.' '.$argv[4]) <= strtotime($current_time)) {
            $time = $argv[4];

            // 拆解日期為時、分，用來組成要更新的時間用
            $time_hour = date('H',strtotime($time));
            $time_min = date('i',strtotime($time));
        } else {
            die('parameter 4 can not be later than current datetime.');
        }
    } else { // 日期格式錯誤
        die('Wrong time formate.');
    }
} else { // 未接收到 $argv[4]
    die('Require parameter 4 as date(H:i:s).');
}
// echo '<pre>', var_dump($time, $time_hour, $time_min), '</pre>'; exit();


// 取得更新的時間區間，預設每 10 分鐘一次
if ( isset($argv[1]) && (($argv[1] == 'test') || ($argv[1] == 'run')) ) { // $argv[1]為執行方式
    $argv_check = $argv[1];
    $time_interval = 10; // 預設間隔為10分鐘
    if ( isset($argv[2]) && filter_var($argv[2], FILTER_VALIDATE_INT) ) { // $argv[2]為時間間隔
        $time_interval = filter_var($argv[2], FILTER_VALIDATE_INT); #default interval: 0/10/20/30
    }
    // echo '<pre>', var_dump($time_interval), '</pre>'; exit();
} else {
    // command 動作 時間
    echo "Command: [test|run] time_interval Y-m-d h:i:s [web|sql] updatelog_id force_update=[0|1]\n";
    echo "Example: run 10 2017-11-06 03:00:00 web 0 0\n";
    // echo "上述以 10 分鐘為間隔計算出\n";
    echo "
        [test|run] 以外的值出現此幫助訊息，test 不更新資料表; run 為實際更新
        time_interval 時間區間，單位為分鐘，預設 10 分，需小於 30
        Y-m-d 美東 date
        h:i:s 美東 time
        [web|sql] web 寫入 tmp, sql 保留 web socket
        updatelog_id 前一參數為 sql 時生效
        force_update: 0 為忽略已存在資料，1 為覆寫已存在資料\n\n";
    die();
}

// 計算起始及結束時間 (美東時間)
if ( ($time_interval <= 30) && ($time_interval != 0) ) { // 間隔時間<=30分鐘，且不為0
    $time_interval_str = '-'.$time_interval.' min';
    $chk_time_min = floor($time_min/$time_interval) * $time_interval;
    $chk_time_end = date('H:i:s', mktime($time_hour, $chk_time_min, '00')); // 最大檢查時間，例：間隔10分鐘，時間是2020-06-24 12:39:00，則這邊就是12:30
    $chk_time_start = date('H:i:s',mktime($time_hour, $chk_time_min-$time_interval, '00')); // 最小檢查時間
    $chk_datetime_end = date('Y-m-d H:i:s',mktime($time_hour, $chk_time_min, '00', $date_m, $date_d, $date_y)); // 2020-06-24 12:30:00
    $chk_datetime_start = date('Y-m-d H:i:s',mktime($time_hour, $chk_time_min-$time_interval, '00', $date_m, $date_d, $date_y)); // 2020-06-24 12:20:00
} else {
    $chk_time_end = date('H:i:s',mktime($time_hour, '00', '00')); // 2020-06-24 12:00:00
    $chk_time_start = date('H:i:s',mktime($time_hour-1, '00', '00')); // 2020-06-24 11:00:00
    $chk_datetime_end = date('Y-m-d H:i:s',mktime($time_hour, '00', '00', $date_m, $date_d, $date_y)); // 2020-06-24 12:00:00
    $chk_datetime_start = date('Y-m-d H:i:s',mktime($time_hour-1, '00', '00', $date_m, $date_d, $date_y)); // 2020-06-24 11:00:00
}
// echo '<pre>', var_dump($chk_time_start, $chk_time_end, $chk_datetime_start, $chk_datetime_end), '</pre>'; exit();

$current_datepicker = date('Y-m-d', strtotime($chk_datetime_start));


if ($argv_check == 'test') {
    var_dump(
        $date_y,
        $date_m,
        $date_d,
        $current_datepicker,
        $current_date,
        $chk_time_start,
        $chk_time_end,
        $chk_datetime_start,
        $chk_datetime_end
    );
}


if ( !empty($argv[5]) ) {
    switch ($argv[5]) {
        case 'web':
            $web_check = 1;
            $output_html = <<<HTML
                <p align="center">更新中...<img src="ui/loading.gif" /></p>
                <script>
                    setTimeout(function(){location.reload()},1000);
                </script>
            HTML;
            $reload_file = dirname(__FILE__).'/tmp_dl/statistics_daily_betting_update.tmp';
            file_put_contents($reload_file, $output_html);
            break;
        case 'sql': // Preverse for web socket
            if ( isset($argv[6]) && filter_var($argv[6], FILTER_VALIDATE_INT) ) {
                $web_check = 2;
                $updatelog_id = filter_var($argv[6], FILTER_VALIDATE_INT);
                $updatelog_sql = <<<SQL
                    SELECT *
                    FROM "root_bonusupdatelog"
                    WHERE "id" = '{$updatelog_id}';
                SQL;
                $updatelog_result = runSQL($updatelog_sql);
                if ($updatelog_result == 0) {
                    die('No root_bonusupdatelog ID');
                }
            } else {
                die('No root_bonusupdatelog ID');
            }
            break;
        default:
            $web_check = 0;
    }
} else {
    $web_check = 0;
}


$force_update = 0;
if ( isset($argv[7]) && filter_var($argv[7], FILTER_VALIDATE_INT) ) {
    $force_update = filter_var($argv[7], FILTER_VALIDATE_INT);
}
switch ($force_update) {
    case 0:
        $action_mode = 'Insert';
        break;
    case 1:
        $action_mode = 'Update';
        break;
    default:
        die('Undefined Parameter 7, 0:Update / 1:Insert');
}

$logger = '';
$logger_timerange = "{$chk_time_start} ~ {$chk_time_end}";


// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------
// ----------------------------------------------------------------------------
// round 1. 新增或更新會員於時間區間的資料
// ----------------------------------------------------------------------------
switch ($web_check) {
    case 1:
        $output_html = <<<HTML
            <p align="center">round 1. 新增或更新會員於時間{$logger_timerange}區間的資料 - 更新中...<img src="ui/loading.gif" /></p>
            <script>
                setTimeout(function(){location.reload()},1000);
            </script>
        HTML;
        file_put_contents($reload_file, $output_html);
        break;
    case 2:
        $updatlog_note = "round 1. 新增或更新會員於時間$logger_timerange區間的資料 - 更新中";
        $updatelog_sql = <<<SQL
            UPDATE "root_bonusupdatelog" SET
                "bonus_status" = '0',
                "note" = '{$updatlog_note}'
            WHERE "id" = '{$updatelog_id}';
        SQL;

        switch ($argv_check) { // 判斷是否要執行
            case 'test':
                echo $updatelog_sql;
                break;
            case 'run':
                $updatelog_result = runSQLall($updatelog_sql);
                break;
            default:
                // do nothing
        }
        break;
    default:
        echo "round 1. 新增或更新會員於時間 $logger_timerange 區間的資料 - 開始\n";
}


// ----------------------------------------------------------------------------
// 取得更新資料
// ----------------------------------------------------------------------------
$stats_affected_count = 0; // 統計受影響的資料筆數

// 查詢資料是否已經存在資料庫內, 存在update,不存在則insert
$check_stmt = <<<SQL
    SELECT count(*)
    FROM "root_statisticsbetting"
    WHERE "dailydate" = '{$current_datepicker}'
        AND "dailytime_start" = '{$chk_time_start}'
        AND "dailytime_end" = '{$chk_time_end}';
SQL;
$check_result = runSQLall($check_stmt);
// echo '<pre>', var_dump($check_result), '</pre>'; exit();
$records = stat_betting($current_datepicker, $chk_datetime_start, $chk_datetime_end); // 參數為美東時區
// echo '<pre>', var_dump($records, $current_datepicker, $chk_datetime_start, $chk_datetime_end), '</pre>'; exit();

// 區間已計算且不強制更新
if ( empty($records) ) {
  $logger = "False, {$action_mode} 統計的資料 {$current_datepicker}_{$chk_time_start}_{$chk_time_end}\n沒有任何 user 在各 casino 投注的資料。";

} else if( ($check_result[1]->count > 0) && !$force_update ) {
    $logger = "區間內有資料且不強制更新\n";
} else {
    // 生成 insert 與 update 用的語句
    $columns = implode(',', array_keys($records[0]));

    foreach ($records as $row) {
        $insert_sql = "INSERT INTO root_statisticsbetting ($columns) VALUES ";
        $_values = implode("','", $row);
        str_replace("'now()'", 'now()', $_values);

        $exclude_columns = ['member_parent_id', 'member_account', 'member_id', 'member_therole'];
        $get_set_string_func = function($column, $value) use ($exclude_columns) {
            if(!in_array($column, $exclude_columns)) return "$column = '$value'";
        };

        $set_string = implode(',', array_filter(array_map($get_set_string_func, array_keys($records[0]), $row)));

        $insert_or_update_sql = <<<SQL
            INSERT INTO root_statisticsbetting ($columns) VALUES ('$_values')
            ON CONFLICT ON CONSTRAINT root_statisticsbetting_memberid_dailydate_dailytime_casinoid
            DO
                UPDATE SET $set_string
            ;
        SQL;

        if ($argv_check == 'test') {
            echo "{$columns}, \n, {$_values}, \n, {$insert_or_update_sql}, \n";
        } else if($argv_check == 'run') {
            $runSQLrest = runSQLall($insert_or_update_sql);
            $stats_affected_count += $runSQLrest[0];
            // echo '<pre>', var_dump($stats_affected_count), '</pre>'; exit();
        }
    }


    if ($stats_affected_count > 0) {
        $logger = "Success, {$action_mode} 統計的資料 {$records[0]['dailydate']}_{$records[0]['dailytime_start']}_{$records[0]['dailytime_end']}\n";
    } else {
        $logger = "False, {$action_mode} 統計的資料 {$records[0]['dailydate']}_{$records[0]['dailytime_start']}_{$records[0]['dailytime_end']}\n";
    }
}
// --------------------------------------------
// MAIN END
// --------------------------------------------

// ----------------------------------------------------------------------------
// 統計結果
// ----------------------------------------------------------------------------
$run_report_result = "統計此時間區間插入(Insert)的資料 = {$stats_insert_count} ,\n統計此時間區間更新(Update) = {$stats_update_count}";

// 算累積花費時間
$program_end_time =  microtime(true);
$program_time = $program_end_time-$program_start_time;
$logger = "{$run_report_result}\n累積花費時間: {$program_time}\n";
if ($web_check == 1) {
    $logger_html = nl2br($logger).'<br><br><p align="center"><button type="button" onclick="window.close();">關閉視窗</button></p>';
    file_put_contents($reload_file,$logger_html);
} else if ($web_check == 2) {
    $updatlog_note = nl2br($logger);
    $updatelog_sql = <<<SQL
        UPDATE "root_bonusupdatelog"
        SET "bonus_status" = '1000',
            "note" = '{$updatlog_note}'
        WHERE "id" = '{$updatelog_id}';
    SQL;
    if ($argv_check == 'test') {
        echo $updatelog_sql;
    } else if ($argv_check == 'run') {
        $updatelog_result = runSQLall($updatelog_sql);
    }
} else {
    echo $logger;
}
// --------------------------------------------
// 統計結果 END
// --------------------------------------------
?>
