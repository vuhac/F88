#!/usr/bin/php70
<?php
// ----------------------------------------------------------------------------
// Features:    後台-- 反水計算及反水派送 -- 獨立排程執行--預設1小時
// File Name:   cron/realtime_reward.com.php
// Author:      yaoyuan
// Editor:      Damocles
// Related DB :root_protalsetting    ->時時反水設定值。
//             root_statisticsbetting->依照日期區間，撈十分鐘報表。
//             root_realtime_reward  ->時時反水資料。
//             root_receivemoney     ->反水打入彩金池db。

// Desc: 1.依照時時反水設定值，按時間區間去撈十分鐘報表。
//       2.統計使用者區間投注量，是否達標。成功予以計算反水值。
//       3.若反水值大於反水上限，則以上限金額為主。
//       4.反水金額依設定可得打入彩金池。
// usage command line : /usr/bin/php70 realtime_reward.cmd.php test/run 2017-06-06
// ----------------------------------------------------------------------------

session_start();

$stats_showdata_count = 0;
$stats_insert_count = 0;
$stats_update_count = 0;

// ----------------------------------------------------------------------------

require_once dirname(__FILE__) ."/config.php";

// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/lib_proccessing.php";

// 時時反水函式庫
require_once dirname(__FILE__) . "/realtime_reward_lib.php";

// set memory limit
ini_set('memory_limit', '400M');

// 確保這個 script 執行不會因為 user abort 而中斷!!
// Ignore user aborts and allow the script to run forever
ignore_user_abort(true);
// disable php time limit , 60*60 = 3600sec = 1hr
set_time_limit(86400);

// At start of script
$time_start = microtime(true);
$origin_memory_usage = memory_get_usage();

// 程式 debug 開關, 0 = off , 1= on , 2 ~11 等級細節內容不同
$debug = 0;


// -----------------------------------------------------------------
// 安全控管, 如果是 web 執行就立即中斷, 只允許 command 執行此程式。
// -----------------------------------------------------------------
// 如果 HTTP_USER_AGENT OR SERVER_NAME 存在, 表示是直接透過網頁呼叫程式, 拒絕這樣的呼叫
if ( isset($_SERVER['HTTP_USER_AGENT']) || isset($_SERVER['SERVER_NAME']) ) {
    die('禁止使用網頁呼叫，來源錯誤，請使用命令列執行。');
}

if ( !isset($argv[1]) || (($argv[1] != 'test') && ($argv[1] != 'run')) ) {
    // command 動作 時間
    echo "Command: [test|run] [web|sql] updatelog_id start_date(Y-m-d) start_time(H:i:s)\n\n";
    echo "Example1: php realtime_reward_cmd.php run web 0 \n\n";
    echo "Example2: php realtime_reward_cmd.php run sql 0 2019-04-10 10:00:00 2019-04-11 09:00:00 \n\n";
    echo "上述以算出 4/10 10時至11時，以一小時為間隔之時時反水值\n";
    echo "
        [test|run] 以外的值出現此幫助訊息，test 不更新資料表; run 為實際更新
        [web|sql] web 寫入 tmp, sql 保留 web socket
        updatelog_id 前一參數為 sql 時生效\n\n
        start_date: Y-m-d 開始美東 date
        start_time: H:i:s 開始美東 time
        end_date: Y-m-d   結束美東 date
        end_time: H:i:s   結束美東 time";
    die();
}

// 目前時間為美東時間
$current_time = gmdate('Y-m-d H:i:s', time()+(-4 * 3600));

// 判斷 $argv[4] & $argv[5]
if ( isset($argv[4]) && validateDate($argv[4], 'Y-m-d') && isset($argv[5]) && validateDate($argv[5], 'H:i:s') ) {
    $start_date_calculate = $current_time; // 預設值
    $receive_start_datetime = $argv[4] . ' ' . $argv[5];
    if (strtotime($receive_start_datetime) <= strtotime($current_time)) {
        $start_date_calculate = $receive_start_datetime;
    } else {
        die('start date is future datetime!');
    }
} else if ( isset($argv[4]) && !validateDate($argv[4], 'Y-m-d') ) {
    die('error start date format(Y-m-d) !');
} else if ( isset($argv[5]) && !validateDate($argv[5], 'H:i:s') ) {
    die('error start time format(H:i:s) !');
} else { // 預設當下(美東時區)
    $start_date_calculate = date("Y-m-d H:i:s", strtotime("-5 hour"));
}

// 判斷 $argv[6] & $argv[7]
if ( isset($argv[6]) && validateDate($argv[6], 'Y-m-d') && isset($argv[7]) && validateDate($argv[7], 'H:i:s') ) {
    $end_date_calculate = $current_time;
    $receive_end_datetime = $argv[6] . ' ' . $argv[7];
    if (strtotime($receive_end_datetime) <= strtotime($current_time)) {
        $end_date_calculate = $receive_end_datetime;
    } else {
        die('end date is future datetime!');
    }
} else if ( isset($argv[6]) && !validateDate($argv[6], 'Y-m-d') ) {
    die('error end date format(Y-m-d) !');
} else if ( isset($argv[7]) && !validateDate($argv[7], 'H:i:s') ) {
    die('error end time format(H:i:s) !');
} else { // 預設當下(美東時區)
    $end_date_calculate = date("Y-m-d H:i:s", strtotime("-4 hour"));
}

// 判斷 $argv[2]
if ( isset($argv[2]) && ($argv[2] == 'web') ) {
    $file_key = sha1('reward' . $start_date_calculate.$end_date_calculate);
    $reload_file = dirname(__FILE__) . '/tmp_dl/reward_' . $file_key . '.tmp';
    $del_log_url = 'realtime_reward_action.php?a=reward_del&k=' . $file_key;
    $progressMonitor = new WebProgressMonitor($reload_file, $del_log_url);
} else {
    $progressMonitor = new TerminalProgressMonitor;
}

// ----------------------------------------------------------------------------
// round 1. 新增或更新會員反水資料
// ----------------------------------------------------------------------------
$progressMonitor->notifyProccessingStart('新增或更新会员时时反水资料 - 更新中...');

// 計算時時返水的時間區間
$date_interval = datetime_range($start_date_calculate, $end_date_calculate);
if ( count($date_interval) > 1 ) {
    unset($date_interval[0]); // 移除總計欄位
} else {
    die("無時間區間可以計算.\n");
}

$progressMonitor->setTotalProgressStep(count($date_interval));

// 撈出時時反水全站設定值
$sets = realtime_reward_protalsetting();

if ( count($sets) == 0 ) {
    die("無法取得全站設定值\n");
}
/*
    ["realtime_reward_time_frequency"]=>
    string(2) "60"
    ["realtime_reward_audit_amount_ratio"]=>
    string(3) "300"
    ["realtime_reward_audit_type"]=>
    string(11) "audit_ratio"
    ["realtime_reward_audit_name"]=>
    string(12) "depositaudit"
    ["realtime_reward_bonus_status"]=>
    string(1) "2"
    ["realtime_reward_bonus_type"]=>
    string(6) "gtoken"
    ["realtime_reward_payout_sw"]=>
    string(2) "on"
    ["realtime_reward_switch"]=>
    string(2) "on"
*/

$insert_count = 0;
$payout_insert_count = 0;
foreach ($date_interval as $exec_time) {
    // 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
    $progressMonitor->forwardProgress();
    $progressMonitor->notifyProccessingProgress('處理中...');

    $current_time = $exec_time;
    // 結束時間前，那一小時的時間區間：ex: $current_time="2019-12-18 04:23:47" $start_datetime_interval=2019-12-18 04:23:47 $end_datetime_interval="2019-12-18 04:00:00"
    $start_datetime_interval = date('Y-m-d H:i:s', strtotime("$current_time") - (strtotime("$current_time") % ($sets['realtime_reward_time_frequency'] * 60)) - ($sets['realtime_reward_time_frequency'] * 60));
    $end_datetime_interval = date('Y-m-d H:i:s', strtotime("$current_time") - (strtotime("$current_time") % ($sets['realtime_reward_time_frequency'] * 60)));
    // echo '<pre>', var_dump($start_datetime_interval, $end_datetime_interval), '</pre>'; exit();


    // 轉成台灣時區
    $start_datetime_interval_taiwan_timezone = gmdate('Y-m-d H:i:s', strtotime("$start_datetime_interval -04")+(8*3600)).' +08:00';
    $end_datetime_interval_taiwan_timezone = gmdate('Y-m-d H:i:s', strtotime("$end_datetime_interval -04")+(8*3600)).' +08:00';
    // echo '<pre>', var_dump($start_datetime_interval_taiwan_timezone, $end_datetime_interval_taiwan_timezone), '</pre>'; exit();


    // 解決時時反水重覆發放。目前找不到原因，但db的確有相同二筆紀錄，而且生成時間不超過一秒。
    // 要計算反水前，先依日期區間，判斷是否有資料存在
    $reward_data_exist = reward_data_exist($start_datetime_interval_taiwan_timezone, $end_datetime_interval_taiwan_timezone);


    // 假如db已存在資料，且是cmd模式，那該筆時時反水區間，則不計算
    if ( ($argv[2] == 'sql') && $reward_data_exist ) {
        echo "{$start_datetime_interval_taiwan_timezone} ~ {$end_datetime_interval_taiwan_timezone} 已有反水資料，故不重新計算反水值，且不打到彩金池!\n";

        // 寫入memberlogtodb
        $msg = "{$start_datetime_interval_taiwan_timezone} ~ {$end_datetime_interval_taiwan_timezone}，已有時時反水資料，故不重新計算反水值。"; //客服
        $msg_log = $msg; //RD
        $sub_service = 'realtime_reward';
        memberlogtodb('jigcs', 'marketing', 'error', $msg, 'jigcs', "$msg_log", 'b', $sub_service);
        continue;
    }

    // 從10分鐘報表(root_statisticsbetting)取出投注資料並關連到root_member
    // 輸出會員帳號、會員id、角色、返水設定、總投注、總損益 debug=2->result  debug=1->sql
    // $debug = 2; // 是否開啟測試mode
    $member_id_bets_profitloss = member_id_bets_profitloss($start_datetime_interval, $end_datetime_interval, $debug); // 美東時間
    // echo '<pre>', var_dump($member_id_bets_profitloss), '</pre>'; exit();

    if ($debug == 2) { // 是否啟用偵錯模式
        var_dump($member_id_bets_profitloss);
        die();
    }

    if ($member_id_bets_profitloss == null) { // 沒有注單紀錄，跳下一圈
        echo "{$start_datetime_interval} ~ {$end_datetime_interval}(美東時間) 無返水資料可統計 !\n";
        continue;
    }


    // 整合 各會員帳號 在 各娛樂城 的 各遊戲分類 的各遊戲的投注金額
    /* $bet_data = [
        'id' => [ // 會員id
            'account' => '', // 會員帳號
            'therole' => '', // 角色身分
            'favorable_rule' => '', // 返水分類
            'favorable_wager'=> '', // 返水標準金額
            'favorable_upperlimit' => '', // 返水上限金額
            'favorable_rate' => '', // 返水比率
            'favorable_group_name' => '', // 返水群組名稱
            'reach_bet_amount' => '', // 是否達到返水標準
            'reward_amonut' => '', // 返水總金額(不受返水上限影響的金額)
            'real_reward_amount' => '', // 實際返水金額
            'bet_sum' => '', // 有效投注總金額
            'profit_sum' => '', // 投注損益總額
            'bet_detail' => [ // 注單內容
                'casino_id' => [ // 娛樂城id
                    'category' => [ // 遊戲分類
                        'game_name'=> [ // 遊戲名稱
                            'betvalid' => '', // 有效投注
                            'betprofit' => '', // 投注損益
                            'reward_amount' => '' // 返水金額(計算完總額後，再依比例分配，四捨五入到小數點第二位)
                        ]
                    ]
                ]
            ]
        ]
    ]; */
    $bet_data = [];
    foreach ($member_id_bets_profitloss as $key_bet=>$val_bet) {
        // 判斷會員id是否已經有紀錄
        if ( !isset($bet_data[ $val_bet->member_id ]) ) { // 未有該會員的資料
            $bet_data[ $val_bet->member_id ] = [
                'account' => $val_bet->member_account, // 會員帳號
                'therole' => $val_bet->therole, // 角色身分
                'favorable_rule' => $val_bet->favorablerule, // 返水分類
                'favorable_wager'=> null, // 返水標準金額(稍後以總投注金額比對返水標準金額時會再寫入)
                'favorable_upperlimit' => null, // 返水上限金額(稍後以總投注金額比對返水標準金額時會再寫入)
                'favorable_rate' => null, // 返水比率(稍後以總投注金額比對返水標準金額時會再寫入)
                'favorable_group_name' =>  null, // 返水群組名稱(稍後以總投注金額比對返水標準金額時會再寫入)
                'reach_bet_amount' => false, // 是否達到返水標準
                'reward_amonut' => 0, // 返水總金額(不受返水上限影響的金額)
                'real_reward_amount' => 0, // 實際返水金額
                'bet_sum' => 0, // 有效投注總金額
                'profit_sum' => 0, // 投注損益總額
                'bet_detail' => []
            ];
        }

        // 整合注單以遊戲分類統計的資料
        if (count($val_bet->favorable_game_name) > 0) {
            foreach ($val_bet->favorable_game_name as $key=>$val) {
                if ( !isset($bet_data[ $val_bet->member_id ]['bet_detail'][ $val->casinoid ][ $val->category ][ $val->game_name ]) ) { // 判斷 "娛樂城->遊戲分類->遊戲名稱" 是否已經初始化
                    $bet_data[ $val_bet->member_id ]['bet_detail'][ $val->casinoid ][ $val->category ][ $val->game_name ] = [
                        'betvalid' => $val->betvalid, // 有效投注
                        'betprofit' => $val->betprofit, // 投注損益
                        'reward_amount' => 0 // 返水金額(計算完總額後，再依比例分配，四捨五入到小數點第二位)
                    ];
                } else {
                    $bet_data[ $val_bet->member_id ]['bet_detail'][ $val->casinoid ][ $val->category ][ $val->game_name ]['betvalid'] += $val->betvalid; // 有效投注
                    $bet_data[ $val_bet->member_id ]['bet_detail'][ $val->casinoid ][ $val->category ][ $val->game_name ]['betprofit'] += $val->betprofit; // 投注損益
                }

                $bet_data[ $val_bet->member_id ]['bet_sum'] += $val->betvalid; // 有效投注
                $bet_data[ $val_bet->member_id ]['profit_sum'] += $val->betprofit; // 投注損益
            }
        }
    }
    // echo '<pre>', var_dump($bet_data), '</pre>'; exit(); // **** 測試使用 ****


    // 取得返水標準
    $favorable_rules = queryFavorableRules();
    if ($favorable_rules === null) {
        die("尚未設定返水標準(root_favorable)！");
    }
    // echo '<pre>', var_dump($favorable_rules), '</pre>'; exit(); // **** 測試使用 ****


    foreach ($bet_data as $key=>$val) {
        // === 彙總 會員注單紀錄 與 返水標準 ===
        // 判斷該名會員所設定的返水類別是否存在，不存在則跳脫執行
        $member_favorable_rule = $val['favorable_rule']; // 該名會員的返水類別
        if ( isset($favorable_rules[ $member_favorable_rule ]) ) {
            $bet_sum = $val['bet_sum']; // 有效投注總額
            $bet_fit_rule_wager = null; // 打碼量
            $bet_fit_rule_upperlimit = null; // 返水上限
            $bet_fit_rule_rate = null; // 返水比
            $bet_fit_rule_group_name = null; // 返水等級名稱
            $reach_bet_amount = false; // 是否達成返水打碼量

            // 遍例該類返水分類，以注單(有效投注)金額，比對出最大返水級距
            foreach ($favorable_rules[ $member_favorable_rule ] as $key_rule=>$val_rule) {
                $rule_amount_wager = $key_rule; // 返水設定的打碼量
                if ($bet_fit_rule_wager === null) {
                    if ($bet_sum >= $rule_amount_wager) { // 有效投注總額 >= 返水設定的打碼量

                        $bet_fit_rule_wager = $rule_amount_wager; // 紀錄打碼量
                        $bet_fit_rule_upperlimit = $val_rule->upperlimit; // 紀錄返水上限
                        $bet_fit_rule_rate = $val_rule->favorablerate; // 紀錄返水比
                        $bet_fit_rule_group_name = $val_rule->group_name; // 紀錄返水等級名稱
                        $reach_bet_amount = true; // 紀錄達成返水打碼量

                        // print("bet_fit_rule_wager：{$bet_fit_rule_wager}\n");
                        // print("bet_fit_rule_upperlimit：{$bet_fit_rule_upperlimit}\n");
                        // var_dump($bet_fit_rule_rate);
                        // print("bet_fit_rule_group_name：{$bet_fit_rule_group_name}\n");
                    }
                } else {
                    if ( ($rule_amount_wager > $bet_fit_rule_wager) && ($bet_sum >= $rule_amount_wager) ) { // (返水設定的打碼量 > 當前的打碼量) && (有效投注總額 >= 返水設定的打碼量)
                        $bet_fit_rule_wager = $rule_amount_wager; // 覆寫打碼量
                        $bet_fit_rule_upperlimit = $val_rule->upperlimit; // 覆寫返水上限
                        $bet_fit_rule_rate = $val_rule->favorablerate; // 覆寫返水比
                        $bet_fit_rule_group_name = $val_rule->group_name; // 覆寫返水等級名稱
                        $reach_bet_amount = true; // 紀錄達成返水打碼量
                    }
                }
            }

            // 回寫返水設定
            $bet_data[ $key ]['favorable_wager'] = $bet_fit_rule_wager;
            $bet_data[ $key ]['favorable_upperlimit'] = $bet_fit_rule_upperlimit;
            $bet_data[ $key ]['favorable_rate'] = $bet_fit_rule_rate;
            $bet_data[ $key ]['favorable_group_name'] = $bet_fit_rule_group_name;
            $bet_data[ $key ]['reach_bet_amount'] = $reach_bet_amount;
            // echo '<pre>', var_dump($bet_data[ $key ]), '</pre>'; exit(); // **** 測試使用 ****
        } else { // 會員設定的返水分類不存在，跳脫執行並印出會員帳號、返水類別(這邊顯示項目可以修改)
            die("
                返水分類未設定\n
                會員帳號：{$val->account}\n
                返水類別：{$val->favorable_rule}\n
            ");
        }
        // echo '<pre>', var_dump($bet_data[ $key ]), '</pre>'; exit(); // **** 測試使用 ****

        // === 計算返水金額、各遊戲的返水金額、返水金額上限
        if ( $bet_data[ $key ]['reach_bet_amount'] ) { // 判斷是否有達成返水打碼量
            // 遍例各注單的娛樂城->遊戲分類
            $sum_favorable_amount = 0; // 累計返水金額
            foreach ($bet_data[ $key ]['bet_detail'] as $key_bet_detail=>$val_bet_detail) { // 娛樂城
                // $key_bet_detail; // 注單的娛樂城
                foreach ($val_bet_detail as $key_category=>$val_category) { // 遊戲分類
                    // $key_category; // 注單的遊戲分類
                    // 判斷返水設定是否有該娛樂城->遊戲分類的資料
                    if ( !isset($bet_data[ $key ]['favorable_rate'][ $key_bet_detail ]->$key_category) ) { // 返水設定沒有該遊戲分類的資料
                        // die("返水設定({$bet_data[ $key ]['favorable_rule']} 打碼量{$bet_data[ $key ]['favorable_wager']})沒有娛樂城({$key_bet_detail})的遊戲分類({$key_category})資料\n");
                        unset( $bet_data[ $key ]['bet_detail'][ $key_bet_detail ][ $key_category ] );
                    } else {
                        $favorable_game_category_rate = $bet_data[ $key ]['favorable_rate'][ $key_bet_detail ]->$key_category; // 遊戲分類的返水設定比率
                        foreach ($val_category as $key_game=>$val_game) { // 注單的遊戲
                            // $key_game // 注單的遊戲
                            $bet_data[ $key ]['bet_detail'][ $key_bet_detail ][ $key_category ][ $key_game ]['favorable_rate'] = $favorable_game_category_rate; // 把該遊戲分類的返水比記錄到遊戲資料底下
                            $bet_data[ $key ]['bet_detail'][ $key_bet_detail ][ $key_category ][ $key_game ]['reward_amount'] = ($bet_data[ $key ]['bet_detail'][ $key_bet_detail ][ $key_category ][ $key_game ]['betvalid'] * $bet_data[ $key ]['bet_detail'][ $key_bet_detail ][ $key_category ][ $key_game ]['favorable_rate']); // 計算該遊戲注單的返水金額
                            $sum_favorable_amount += $bet_data[ $key ]['bet_detail'][ $key_bet_detail ][ $key_category ][ $key_game ]['reward_amount'];
                        }
                    }
                }
            }
            $bet_data[ $key ]['reward_amonut'] = $sum_favorable_amount; // 寫回累計返水金額
            // echo '<pre>', var_dump($bet_data[ $key ]), '</pre>'; exit(); // **** 測試使用 ****

            // 判斷累計返水金額是否超過返水設定上限
            if ($bet_data[ $key ]['reward_amonut'] > $bet_data[ $key ]['favorable_upperlimit']) { // 累計返水金額 超過返水設定最大限額，故實際收到的返水金額為上限金額，這邊還要回朔計算各遊戲的返水金額
                $bet_data[ $key ]['real_reward_amount'] = $bet_data[ $key ]['favorable_upperlimit'];

                // 回朔各遊戲的返水金額計算
                $difference_amount = ($bet_data[ $key ]['reward_amonut'] - $bet_data[ $key ]['favorable_upperlimit']); // 因返水定上限而被扣除的差額
                foreach ($bet_data[ $key ]['bet_detail'] as $key_bet_detail=>$val_bet_detail) { // 娛樂城
                    // $key_bet_detail; // 注單的娛樂城
                    foreach ($val_bet_detail as $key_category=>$val_category) { // 遊戲分類
                        // $key_category; // 注單的遊戲分類
                        foreach ($val_category as $key_game=>$val_game) { // 注單的遊戲
                            // 重要:計算公式 = 該遊戲的返水金額 - ((該遊戲的返水金額 / 總返水金額) * 因返水定上限而被扣除的差額)
                            // 四捨五入((該遊戲的返水金額 / 總返水金額) * 因返水定上限而被扣除的差額)
                            $favorable_deduction_ratio = round(($bet_data[ $key ]['bet_detail'][ $key_bet_detail ][ $key_category ][ $key_game ]['reward_amount'] / $bet_data[ $key ]['reward_amonut']) * $difference_amount, 2);
                            $bet_data[ $key ]['bet_detail'][ $key_bet_detail ][ $key_category ][ $key_game ]['reward_amount'] -= $favorable_deduction_ratio;
                            $bet_data[ $key ]['bet_detail'][ $key_bet_detail ][ $key_category ][ $key_game ]['description'] = "因為返水設定上限而依返水比例扣除{$favorable_deduction_ratio}";
                        }
                    }
                }
            } else { // 累計返水金額 沒有超過返水設定最大限額，故實際收到的返水金額為累計返水金額
                $bet_data[ $key ]['real_reward_amount'] = $bet_data[ $key ]['reward_amonut'];
            }
        }
    }
    // echo '<pre>', var_dump($bet_data), '</pre>'; exit(); // **** 測試使用 ****


    // init sql execor
    $batched_sql_executor = new BatchedSqlExecutor(100);
    // echo '<pre>', var_dump($sets["realtime_reward_switch"]), '</pre>'; exit(); // **** 測試使用 ****
    if ($sets["realtime_reward_switch"] == 'on') {
        $payout_sw = ( ($sets["realtime_reward_payout_sw"] == 'on') ? 't' : 'f' );
        // 時時返水交易單號，預設以 (im)20180515_useraccount_亂數3碼 為單號，其中 w:代表提款/d:代表存款/md:後台人工存款/mw:後台人工提款/c:佣金計算
        $im_transaction_id = 'im'.date("YmdHis").str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);

        // 刪除舊有返水資料-記得要復原
        del_realtime_reward_data($start_datetime_interval_taiwan_timezone, $end_datetime_interval_taiwan_timezone);

        // 寫入時時返水資料表
        foreach ($bet_data as $key=>$val) {
            $batched_sql_executor->push(
                insertRealtimeRewardSql([
                    'member_id' => $key, // 會員ID
                    'member_account' => $val['account'], // 會員帳號
                    'member_therole' => $val['therole'], // 會員角色
                    'favorable_level' => $val['favorable_rule'], // 返水等級
                    'favorable_bet_level' => $val['favorable_wager'], // 返水打碼量等級
                    'reach_bet_amount' => ( ($val['reach_bet_amount']) ? 't' : 'f' ), // 達成打碼量,是:t 否:f
                    'reward_amount' => $val['reward_amonut'], // 返水金額
                    'real_reward_amount' => $val['real_reward_amount'], // 因返水上限，實際收到返水
                    'favorable_upperlimit' => $val['favorable_upperlimit'], // 返水上限
                    'favorable_group_name' => $val['favorable_group_name'], // 返水顯示名稱
                    'bet_sum' => $val['bet_sum'], // 總投注
                    'profit_sum' => $val['profit_sum'], // 總損益
                    'notes' => json_encode($val['favorable_rate']), // 說明備註
                    'start_date' => "$start_datetime_interval -04", // 開始時間
                    'end_date' => "$end_datetime_interval -04", // 結束時間
                    'updatetime' => 'now()', // 更新時間
                    'details' => json_encode($val['bet_detail']), // 原本為"遊戲分類投注計算"，現在是"遊戲投注記算"
                    'casino_sum' => "null", // 已停用-娛樂城投注加總
                    'is_payout' => $payout_sw, // 是否打到彩金池,是:t 否:f
                    'transaction_id' => $im_transaction_id // 交易序號
                ])
            );
            $insert_count++;
        }
        $batched_sql_executor->execute();
        unset($bet_data); // 釋放記憶體空間

        // 打到彩金池
        if ($sets["realtime_reward_payout_sw"] == 'on') {
            // 以交易單號判斷是否有此次新增的時時返水紀錄
            $get_realtime_reward = get_realtime_reward($im_transaction_id);
            if ($get_realtime_reward[0] == 0) {
                echo "沒有時時返水資料可以打入彩金池！\n";
                continue;
            } else {
                // 以交易單號更新時時返水打入彩金池時間
                $payout_date_update_sql = <<<SQL
                    UPDATE "root_realtime_reward"
                        SET "payout_date" = 'now()'
                    WHERE ("transaction_id" = '{$im_transaction_id}');
                SQL;
                runSQL($payout_date_update_sql);
            }
            unset($get_realtime_reward[0]);

            // 寫入彩金池資料表
            $now_datetime = gmdate("Y-m-d H:i:s", time() + (8 * 3600)).' +08:00'; // 台灣目前時間
            $receive_deadline_time = gmdate("Y-m-d H:i:s", strtotime('+1 month', time() + (8 * 3600))).' +08:00'; // 時時返水領取時限
            $prizecategories = "{$get_realtime_reward[1]->start_date_ast} {$get_realtime_reward[1]->start_time_ast}-{$get_realtime_reward[1]->end_time_ast}时时反水";
            $summary = "{$get_realtime_reward[1]->start_date_ast} {$get_realtime_reward[1]->start_time_ast} ~ {$get_realtime_reward[1]->end_time_ast}期间时时反水";

            foreach ($get_realtime_reward as $member_data) {
                // 由 稽核倍數 來計算 稽核金額
                if ($sets['realtime_reward_audit_type'] == 'audit_ratio') { // 比例
                    $audit_amount = round($member_data->real_reward_amount * $sets['realtime_reward_audit_amount_ratio'], 2);
                } else { // 指定金額
                    $audit_amount = $sets['realtime_reward_audit_amount_ratio'];
                }
                /*
                    echo '<pre>', var_dump(
                        $sets['realtime_reward_audit_type'],
                        $sets['realtime_reward_audit_amount_ratio'],
                        $audit_amount
                    ), '</pre>'; exit();
                */

                // 判斷獎金是以gcash or gtoken發放，如是gtoken則需設定稽核，gcash則不用
                switch ($sets['realtime_reward_bonus_type']) {
                    case 'gtoken':
                        $sets['bonus_cash']  = '0';
                        $sets['bonus_token'] = $member_data->real_reward_amount;
                        break;
                    case 'gcash':
                        $sets['bonus_cash'] = $member_data->real_reward_amount;
                        $sets['bonus_token'] = '0';
                        $sets['realtime_reward_audit_name'] = 'freeaudit';
                        $audit_amount = '0';
                        break;
                    default:
                        die('Error(500):獎金類別錯誤！！');
                }

                $batched_sql_executor->push(
                    insert_receivemoney_sql([
                        'member_id'                  => $member_data->member_id,
                        'member_account'             => $member_data->member_account,
                        'gcash_balance'              => $sets['bonus_cash'],
                        'gtoken_balance'             => $sets['bonus_token'],
                        'givemoneytime'              => $now_datetime,
                        'receivedeadlinetime'        => $receive_deadline_time,
                        'prizecategories'            => $prizecategories,
                        'updatetime'                 => $now_datetime,
                        'auditmode'                  => $sets['realtime_reward_audit_name'],
                        'auditmodeamount'            => $audit_amount,
                        'summary'                    => $summary,
                        'transaction_category'       => 'tokenpreferential',
                        'system_note'                => '时时反水交易号码:'.$member_data->transaction_id,
                        'reconciliation_reference'   => $member_data->transaction_id,
                        'givemoney_member_account'   => 'jigcs',
                        'status'                     => $sets['realtime_reward_bonus_status'],
                        'last_modify_member_account' => 'jigcs',
                    ])
                );

                $payout_insert_count++;
            }
            $batched_sql_executor->execute();
            // 釋放打入彩金池資料
            unset($get_realtime_reward);
        }
        // 寫入memberlogtodb
        $msg         = '現在時間：' . gmdate('Y-m-d H:i:s', time()+8 * 3600) . '。' . "\n".'时时反水区间：'.$start_datetime_interval_taiwan_timezone . '~' . $end_datetime_interval_taiwan_timezone . '。'."\n".'交易批號：' . $im_transaction_id . '。'."\n".'時時反水紀錄已更新' . $insert_count . '筆。' . "\n".'彩金池紀錄已新增' . $payout_insert_count . '筆。'; //客服
        $msg_log     = $msg; //RD
        $sub_service = 'realtime_reward';
        memberlogtodb('jigcs', 'marketing', 'notice', $msg, 'jigcs', "$msg_log", 'b', $sub_service);
    }
}
// echo 'Total execution time in seconds: ' . round((microtime(true) - $time_start), 3) . " sec\n";
// echo 'memmory usage: ' . round((memory_get_usage() - $origin_memory_usage) / (1024 * 1024), 3) . " MB.\n";

// 算累積花費時間
$program_end_time = microtime(true);
$program_time     = round($program_end_time - $program_start_time, 3);

// $output_html = "\n花費時間: " . $program_time . "秒\n";
$output_html = '現在時間：' . gmdate('Y-m-d H:i:s', time()+8 * 3600) . ' ' . "\n";
// $output_html = $output_html .'最後計算區間:'.$start_datetime_interval_taiwan_timezone . ' ～ ' . $end_datetime_interval_taiwan_timezone . ' ' . "\n";
$output_html = $output_html . '時時反水紀錄已更新' . $insert_count . '筆' . "\n";
$output_html = $output_html . '彩金池紀錄已新增' . $payout_insert_count . '筆' . "\n";


// echo($output_html);
$progressMonitor->notifyProccessingComplete($output_html);

?>
