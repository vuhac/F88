#!/usr/bin/php70
<?php
// ----------------------------------------------------------------------------
// Features:    後台-- 反水計算及反水派送 -- 獨立排程執行
// File Name:    cron/preferential_payout_cmd.php
// Author:        Barkley,Fix by Ian
// Related:   DB root_favorable(會員反水設定及打碼設定)
//                             preferential_calculation.php
//                             preferential_calculation_action.php
// Desc: 由每日報表，統計投注額後，依據設定比例 1% ~ 3% ，發放反水給予會員。
// 反水可以轉帳到代幣帳戶代幣帳戶可以設定稽核，也可以轉帳到現金帳戶
// Log:
// ----------------------------------------------------------------------------
// How to run ?
// usage command line : /usr/bin/php70 preferential_payout_cmd.php test/run 2017-06-06 statustoken worker
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// session_start();

$stats_showdata_count = 0;
$stats_insert_count = 0;
$stats_update_count = 0;

// ----------------------------------------------------------------------------

require_once dirname(__FILE__) . "/config.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

require_once dirname(__FILE__) . "/lib_proccessing.php";

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

// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

function payout($preferential)
{
    global $current_date, $bonusstatus, $worker_account;

    $b['id'] = $preferential->id;
    $b['member_id'] = $preferential->member_id;
    $b['member_account'] = $preferential->member_account;
    $b['member_parent_id'] = $preferential->member_parent_id;
    $b['member_therole'] = $preferential->member_therole;
    $b['dailydate'] = $preferential->dailydate;
    $b['favorablerate_level'] = $preferential->favorablerate_level;
    $b['mg_totalwager'] = $preferential->mg_totalwager;
    $b['mg_favorable_rate'] = $preferential->mg_favorable_rate;
    $b['mg_favorablerate_amount'] = $preferential->mg_favorablerate_amount;
    $b['all_bets_amount'] = $preferential->all_bets_amount;
    $b['all_favorablerate_amount'] = $preferential->all_favorablerate_amount;
    $b['favorable_limit'] = $preferential->favorable_limit;
    $b['favorable_audit'] = $preferential->favorable_audit;
    $b['all_favorablerate_beensent_amount'] = $preferential->all_favorablerate_beensent_amount;
    $b['all_favorablerate_difference_amount'] = $preferential->all_favorablerate_difference_amount;
    $b['self_favorable'] = $preferential->self_favorable;
    $b['self_favorable_beensent_amount'] = $preferential->self_favorable_beensent_amount;
    $b['self_favorable_difference_amount'] = $preferential->self_favorable_difference_amount;

    $b['agent_favorable_beensent_amount'] = $b['all_favorablerate_beensent_amount'] - $b['self_favorable_beensent_amount'];
    $b['agent_favorable_difference_amount'] = $b['all_favorablerate_difference_amount'] - $b['self_favorable_difference_amount'];

    $givemoneytime = $current_date;
    $receivedeadlinetime = date("Y-m-d H:i:s", strtotime('+1 month', strtotime($current_date)));

    $prizecategories = preg_replace('/([^A-Za-z0-9])/ui', '', $b['dailydate']) . '自身反水';
    $prizecategories_agent = preg_replace('/([^A-Za-z0-9])/ui', '', $b['dailydate']) . '投注佣金';

    $summary = $prizecategories;
    $summary_agent = $prizecategories_agent;

    // 由 稽核倍數 來計算 稽核值
    if ($bonusstatus['audit_calculate_type'] == 'audit_ratio') {
        $audit_amount = round($b['self_favorable_difference_amount'] * $bonusstatus['audit_ratio'], 2);
        $agent_audit_amount = round($b['agent_favorable_difference_amount'] * $bonusstatus['audit_ratio'], 2);
    } else {
        $audit_amount = $bonusstatus['audit_amount'];
        $agent_audit_amount = $bonusstatus['audit_amount'];
    }

    // 判斷獎金是以加盟金還是現金發放，如是現金則需設定稽核，加盟金則不用
    if ($bonusstatus['bonus_type'] == 'token') {
        $bonusstatus['bonus_cash'] = '0';
        $bonusstatus['bonus_token'] = $b['self_favorable_difference_amount'];
        $bonusstatus['bonus_cash_agent'] = '0';
        $bonusstatus['bonus_token_agent'] = $b['agent_favorable_difference_amount'];
    } elseif ($bonusstatus['bonus_type'] == 'cash') {
        $bonusstatus['bonus_cash'] = $b['self_favorable_difference_amount'];
        $bonusstatus['bonus_token'] = '0';
        $bonusstatus['bonus_cash_agent'] = $b['agent_favorable_difference_amount'];
        $bonusstatus['bonus_token_agent'] = '0';
        $bonusstatus['audit_type'] = 'freeaudit';
        $audit_amount = '0';
        $agent_audit_amount = '0';
    } else {
        die('Error(500):獎金類別錯誤！！');
    }

    // agent preferential
    if ($b['member_therole'] == 'A' && $b['agent_favorable_difference_amount'] > 0) {
        // 新增到 root_receivemoney
        $insert_sql = <<<SQL
		INSERT INTO root_receivemoney (
			member_id,
			member_account,
			gcash_balance,
			gtoken_balance,
			givemoneytime,
			receivedeadlinetime,
			prizecategories,
			auditmode,
			auditmodeamount,
			summary,
			transaction_category,
			givemoney_member_account,
			last_modify_member_account,
			status,
			updatetime
		) VALUES (
			'{$b['member_id']}',
			'{$b['member_account']}',
			'{$bonusstatus['bonus_cash_agent']}',
			'{$bonusstatus['bonus_token_agent']}',
			'$givemoneytime',
			'$receivedeadlinetime',
			'$prizecategories_agent',
			'{$bonusstatus['audit_type']}',
			'{$agent_audit_amount}',
			'$summary_agent',
			'tokenfavorable',
			'$worker_account',
			'$worker_account',
			'{$bonusstatus['bonus_status']}',
			now()
		);
SQL;

        $insert_result = runSQLall($insert_sql);
    }

    if ($b['self_favorable_difference_amount'] > 0) {

        // 新增到 root_receivemoney
        $insert_sql = <<<SQL
		INSERT INTO root_receivemoney (
			member_id,
			member_account,
			gcash_balance,
			gtoken_balance,
			givemoneytime,
			receivedeadlinetime,
			prizecategories,
			auditmode,
			auditmodeamount,
			summary,
			transaction_category,
			givemoney_member_account,
			last_modify_member_account,
			status,
			updatetime
		) VALUES (
			'{$b['member_id']}',
			'{$b['member_account']}',
			'{$bonusstatus['bonus_cash']}',
			'{$bonusstatus['bonus_token']}',
			'$givemoneytime',
			'$receivedeadlinetime',
			'$prizecategories',
			'{$bonusstatus['audit_type']}',
			'{$audit_amount}',
			'$summary',
			'tokenpreferential',
			'$worker_account',
			'$worker_account',
			'{$bonusstatus['bonus_status']}',
			now()
		);
SQL;

        $insert_result = runSQLall($insert_sql);
    }

    // 更新 反水 記錄
    $update_sql = <<<SQL
	UPDATE root_statisticsdailypreferential
		SET
			updatetime = now(),
		 	all_favorablerate_beensent_amount = '{$b['all_favorablerate_amount']}',
		 	all_favorablerate_difference_amount = '0',
			self_favorable_beensent_amount = '{$b['self_favorable']}',
			self_favorable_difference_amount = '0'
	WHERE id = '{$b['id']}';
SQL;

    $update_sql_result = runSQLall($update_sql);
}

// -----------------------------------------------------------------
// 安全控管, 如果是 web 執行就立即中斷, 只允許 command 執行此程式。
// -----------------------------------------------------------------
// 如果 HTTP_USER_AGENT OR SERVER_NAME 存在, 表示是直接透過網頁呼叫程式, 拒絕這樣的呼叫
if (isset($_SERVER['HTTP_USER_AGENT']) or isset($_SERVER['SERVER_NAME'])) {
    die('禁止使用網頁呼叫，來源錯誤，請使用命令列執行。');
}

// 取得今天的日期
// 轉換為美東的時間 date
$date = date_create(date('Y-m-d H:i:sP'), timezone_open('Asia/Taipei'));
date_timezone_set($date, timezone_open('Asia/Taipei'));
$current_date = date_format($date, 'Y-m-d H:i:sP');

// -----------------------------------------------------------------
// 命令列參數解析
// -----------------------------------------------------------------

// validate argv list
if (isset($argv[1]) and $argv[1] == 'run') {
    if (isset($argv[2]) and validateDate($argv[2], 'Y-m-d')) {
        //如果有的話且格式正確, 取得日期. 沒有的話中止
        $current_datepicker = $argv[2];
    } else {
        // command 動作 時間
        echo "command [run] YYYY-MM-DD statustoken worker \n";
        die('no datetime');
    }
    if (isset($argv[3])) {
        $bonusstatus = get_object_vars(jwtdec('preferentialpayout', $argv[3]));
        // var_dump($bonusstatus);
    } else {
        // command 動作 時間
        echo "command [run] YYYY-MM-DD statustoken worker \n";
        die('no statustoken');
    }
    if (isset($argv[4]) and filter_var($argv[4], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH)) {
        $worker_account = filter_var($argv[4], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    } else {
        // command 動作 時間
        echo "command [run] YYYY-MM-DD statustoken worker \n";
        die('no worker account');
    }
    $argv_check = $argv[1];
    $current_datepicker_gmt = gmdate('Y-m-d H:i:s.u', strtotime($current_datepicker . '23:59:59 -05') + 8 * 3600) . '+08:00';
} else {
    // command 動作 時間
    echo "command [run] YYYY-MM-DD statustoken worker \n";
    die('no run');
}

if (isset($argv[5]) and $argv[5] == 'web') {

    $web_check = 1;
    $file_key = sha1('preferentialpayout' . $current_datepicker);
    $reload_file = dirname(__FILE__) . '/tmp_dl/prefer_' . $file_key . '.tmp';

} elseif (isset($argv[5]) and $argv[5] == 'sql') {
    if (isset($argv[5]) and filter_var($argv[5], FILTER_VALIDATE_INT)) {
        $web_check = 2;
        $updatelog_id = filter_var($argv[5], FILTER_VALIDATE_INT);
        $updatelog_sql = "SELECT * FROM root_bonusupdatelog WHERE id ='$updatelog_id';";
        $updatelog_result = runSQL($updatelog_sql);
        if ($updatelog_result == 0) {
            die('No root_bonusupdatelog ID');
        }
    } else {
        die('No root_bonusupdatelog ID');
    }
} else {
    $web_check = 0;
}

$logger = '';

// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// round 1. 新增或更新發放會員反水資料
// ----------------------------------------------------------------------------

// start proccessing
if ($web_check == 1) {
    notify_proccessing_start(
        'round 1. 發放會員反水 - 更新中...',
        $reload_file
    );
} elseif ($web_check == 2) {
    $updatlog_note = 'round 1. 發放會員反水 - 更新中';
    $updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \'0\', note = \'' . $updatlog_note . '\' WHERE id = \'' . $updatelog_id . '\';';
    if ($argv_check == 'test') {
        echo $updatelog_sql;
    } elseif ($argv_check == 'run') {
        $updatelog_result = runSQLall($updatelog_sql);
    }
} else {
    echo "round 1. 發放會員反水 - 開始\n";
}

$preferential_sql = <<<SQL
	SELECT
		*,
		(CAST (all_favorablerate_amount_detail->>'self_favorable' AS NUMERIC)) AS self_favorable
		FROM root_statisticsdailypreferential
		WHERE dailydate = :dailydate
			AND all_favorablerate_difference_amount > '0'
		ORDER BY member_id ASC;
SQL;

$preferential_list = runSQLall_prepared($preferential_sql, [':dailydate' => $current_datepicker]);

// 處理進度 % , 用來顯示紀錄進度。
$percentage_current = 0;

// 判斷 root_member count 數量大於 1
if (count($preferential_list) >= 1) {
    // 以會員為主要 key 依序列出每個會員的貢獻金額
    foreach ($preferential_list as $i => $preferential) {

        payout($preferential);

        $stats_insert_count++;
        $stats_update_count++;

        // ------- bonus update log ------------------------
        // 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
        $percentage_html = round($i / count($preferential_list), 2) * 100;
        $process_record_html = $i / count($preferential_list);
        $process_times_html = round((microtime(true) - $program_start_time), 3);
        $counting_r = $percentage_html % 5;

        if ($web_check == 1 and $counting_r == 0) {

            notify_proccessing_progress(
                'round 1. 發放會員反水 - 更新中...',
                $percentage_html . ' %',
                $reload_file
            );

        } elseif ($web_check == 2 and $counting_r == 0) {

            $updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \'' . $percentage_html . '\', note = \'' . $updatlog_note . '\' WHERE id = \'' . $updatelog_id . '\';';
            if ($argv_check == 'test') {
                echo $updatelog_sql;
            } elseif ($argv_check == 'run') {
                $updatelog_result = runSQLall($updatelog_sql);
            }

        } elseif ($web_check == 0) {
            if ($percentage_html != $percentage_current) {
                if ($counting_r == 0) {
                    echo "\n目前處理 $current_datepicker 紀錄: $process_record_html ,執行進度: $percentage_html% ,花費時間: " . $process_times_html . "秒\n";
                } else {
                    echo $percentage_html . '% ';
                }
                $percentage_current = $percentage_html;
            }
        }
        // -------------------------------------------------
    }
}

// proccessing complete
$run_report_result = "
  統計顯示的資料 =  $stats_showdata_count ,\n
  統計此時間區間插入(Insert)的會員資料 =  $stats_insert_count ,\n
  統計此時間區間更新(Update)的會員資料 =  $stats_update_count";

// 算累積花費時間
$program_end_time = microtime(true);
$program_time = $program_end_time - $program_start_time;
$logger = $run_report_result . "\n累積花費時間: " . $program_time . " \n";

if ($web_check == 1) {

    notify_proccessing_complete(
        nl2br($logger) . '<br><br>',
        'preferential_calculation_action.php?a=prefer_del&k=' . $file_key,
        $reload_file
    );

} elseif ($web_check == 2) {
    $updatlog_note = nl2br($logger);
    $updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \'1000\', note = \'' . $updatlog_note . '\' WHERE id = \'' . $updatelog_id . '\';';
    if ($argv_check == 'test') {
        echo $updatelog_sql;
    } elseif ($argv_check == 'run') {
        $updatelog_result = runSQLall($updatelog_sql);
    }
} else {
    echo $logger;
}

// --------------------------------------------
// MAIN END
// --------------------------------------------

?>
