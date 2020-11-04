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

function payout($profitloss)
{
    global $current_date, $bonusstatus, $worker_account;

    
    $b['id']             = $profitloss->id;
    $b['agent_id']       = $profitloss->agent_id;
    $b['agent_account']  = $profitloss->agent_account;
    $b['agent_therole']  = $profitloss->agent_therole;
    $b['commission']     = $profitloss->commission;
    $b['is_payout']      = $profitloss->is_payout;
    $b['transaction_id'] = $profitloss->transaction_id;
    $b['start_date']     = $profitloss->start_date;
    $b['end_date']       = $profitloss->end_date;
    $b['updatetime']     = $profitloss->updatetime;
    $b['payout_date']    = $profitloss->payout_date;

    $givemoneytime = $current_date;
    $givemoneytime_est = gmdate('Y-m-d H:i:s', strtotime($givemoneytime)+-4 * 3600);

    
    $receivedeadlinetime = date("Y-m-d H:i:s", strtotime('+1 month', strtotime($current_date)));
    
    $prizecategories = preg_replace('/([^A-Za-z0-9])/ui', '', $b['start_date']) . '-' . preg_replace('/([^A-Za-z0-9])/ui', '', $b['end_date']) . '存款投注佣金';
    
    $summary = $prizecategories;
    // var_dump($prizecategories);die();

    // 由 稽核倍數 來計算 稽核值
    if ($bonusstatus['audit_calculate_type'] == 'audit_ratio') {
        $audit_amount = round($b['commission'] * $bonusstatus['audit_ratio'], 2);
    } else {
        $audit_amount = $bonusstatus['audit_amount'];
    }

    // 判斷獎金是以加盟金還是現金發放，如是現金則需設定稽核，加盟金則不用
    if ($bonusstatus['bonus_type'] == 'token') {
        $bonusstatus['bonus_cash'] = '0';
        $bonusstatus['bonus_token'] = $b['commission'];
    } elseif ($bonusstatus['bonus_type'] == 'cash') {
        $bonusstatus['bonus_cash'] = $b['commission'];
        $bonusstatus['bonus_token'] = '0';
        $bonusstatus['audit_type'] = 'freeaudit';
        $audit_amount = '0';
    } else {
        die('Error(500):彩金類別錯誤！！');
    }

    // 佣金為負的判斷
    // if ($b['member_therole'] == 'A' && $b['commission'] < 0) {
    //     die('Error(1903081515):佣金不得為負！！');
    //     return;
    // }

    
    // 佣金為正, 發放到彩金池
    if ($b['agent_therole'] == 'A' && $b['commission'] > 0) {
        // var_dump($b,$bonusstatus,$worker_account);die();
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
			'{$b['agent_id']}',
			'{$b['agent_account']}',
			'{$bonusstatus['bonus_cash']}',
			'{$bonusstatus['bonus_token']}',
			'$givemoneytime',
			'$receivedeadlinetime',
			'$prizecategories',
			'{$bonusstatus['audit_type']}',
			'$audit_amount',
			'$summary',
			'agent_commission',
			'$worker_account',
			'$worker_account',
			'{$bonusstatus['bonus_status']}',
			now()
		);
SQL;
        $insert_result = runSQLall($insert_sql);

        // 寫入memberlogtodb
        $msg         = '存款投注佣金计算，发送佣金至彩金池。计算区间：' . preg_replace('/([^A-Za-z0-9])/ui', '', $b['start_date']) . '~' . preg_replace('/([^A-Za-z0-9])/ui', '', $b['end_date']) . '。帳號：' . $b['agent_account'] . '。現金：' . $bonusstatus['bonus_cash'] . '元。遊戲幣：' . $bonusstatus['bonus_token'] . '元。發放時間：' . $givemoneytime_est . '。'; //客服
        $msg_log     = $msg . '彩金狀態：' . $bonusstatus['bonus_status'].'。'; //RD
        $sub_service = 'agent_depositbet_calculation';
        memberlogtodb($worker_account, 'marketing', 'notice', $msg, $b['agent_account'], "$msg_log", 'b', $sub_service);

    }

    // 更新 佣金總表
    $update_sql = <<<SQL
	UPDATE root_commission_depositbet_summary
		SET
			updatetime = now(),
			payout_date = now(),
			is_payout = 'true'
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

// 取得今天的日期,以台北時區為主
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
        echo "command [run] YYYY-MM-DD YYYY-MM-DD statustoken worker \n";
        die('no datetime');
    }
    if (isset($argv[3]) and validateDate($argv[3], 'Y-m-d')) {
        //如果有的話且格式正確, 取得日期. 沒有的話中止
        $current_datepicker_end = $argv[3];
    } else {
        // command 動作 時間
        echo "command [run] YYYY-MM-DD YYYY-MM-DD statustoken worker \n";
        die('no datetime');
    }
    if (isset($argv[4])) {
        $bonusstatus = get_object_vars(jwtdec('sendpayoutpool', $argv[4]));
    } else {
        // command 動作 時間
        echo "command [run] YYYY-MM-DD YYYY-MM-DD statustoken worker \n";
        die('no statustoken');
    }
    if (isset($argv[5]) and filter_var($argv[5], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH)) {
        $worker_account = filter_var($argv[5], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    } else {
        // command 動作 時間
        echo "command [run] YYYY-MM-DD YYYY-MM-DD statustoken worker \n";
        die('no worker account');
    }
    $argv_check = $argv[1];
    $current_datepicker_gmt = gmdate('Y-m-d H:i:s.u', strtotime($current_datepicker . '23:59:59 -04') + 8 * 3600) . '+08:00';
} else {
    // command 動作 時間
    echo "command [run] YYYY-MM-DD YYYY-MM-DD statustoken worker \n";
    die('no run');
}

if (isset($argv[6]) and $argv[6] == 'web') {

    $web_check = 1;
    $file_key = sha1('sendpayoutpool' . $current_datepicker . $current_datepicker_end);
    $reload_file = dirname(__FILE__) . '/tmp_dl/payoutpool_' . $file_key . '.tmp';

} elseif (isset($argv[6]) and $argv[6] == 'sql') {
    if (isset($argv[6]) and filter_var($argv[6], FILTER_VALIDATE_INT)) {
        $web_check = 2;
        $updatelog_id = filter_var($argv[6], FILTER_VALIDATE_INT);
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
        'round 1. 發放代理佣金 - 更新中...',
        $reload_file
    );
} elseif ($web_check == 2) {
    $updatlog_note = 'round 1. 發放代理佣金 - 更新中';
    $updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \'0\', note = \'' . $updatlog_note . '\' WHERE id = \'' . $updatelog_id . '\';';
    if ($argv_check == 'test') {
        echo $updatelog_sql;
    } elseif ($argv_check == 'run') {
        $updatelog_result = runSQLall($updatelog_sql);
    }
} else {
    echo "round 1. 發放代理佣金 - 開始\n";
}

$profitloss_sql = <<<SQL
	SELECT *
		FROM root_commission_depositbet_summary
		WHERE start_date = :dailydate
			AND end_date = :end_date
		ORDER BY agent_id ASC;
SQL;
$profitloss_list = runSQLall_prepared($profitloss_sql, [':dailydate' => $current_datepicker, ':end_date' => $current_datepicker_end]);
// var_dump($profitloss_list);die();

// 處理進度 % , 用來顯示紀錄進度。
$percentage_current = 0;

// 判斷 root_member count 數量大於 1
if (count($profitloss_list) >= 1) {
    // 以會員為主要 key 依序列出每個會員的貢獻金額
    foreach ($profitloss_list as $i => $profitloss) {

        payout($profitloss);

        $stats_insert_count++;
        $stats_update_count++;

        // ------- bonus update log ------------------------
        // 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
        $percentage_html = round($i / count($profitloss_list), 2) * 100;
        $process_record_html = $i / count($profitloss_list);
        $process_times_html = round((microtime(true) - $program_start_time), 3);
        $counting_r = $percentage_html % 5;

        if ($web_check == 1 and $counting_r == 0) {

            notify_proccessing_progress(
                'round 1. 發放代理佣金 - 更新中...',
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
  统计显示的资料 =  $stats_showdata_count ,\n
  统计此时间区间插入(Insert)的会员资料 =  $stats_insert_count ,\n
  统计此时间区间更新(Update)的会员资料 =  $stats_update_count";

// 算累積花費時間
$program_end_time = microtime(true);
$program_time = $program_end_time - $program_start_time;
$logger = $run_report_result . "\n累积花费时间: " . $program_time . " \n";

if ($web_check == 1) {

    notify_proccessing_complete(
        nl2br($logger) . '<br><br>',
        'agent_depositbet_calculation_action.php?a=profitloss_del&k=' . $file_key,
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
