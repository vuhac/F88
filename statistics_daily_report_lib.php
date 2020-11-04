<?php
// ----------------------------------------
// Features:    每日營收日結報表--專用函式庫 (前台,後台.使用同個程式碼計算, 後續如果有新增娛樂城統計才會正確)
// File Name:    statistics_daily_report_lib.php
// Author:        Barkley
// 前台的相關資訊:
// betrecord_deltail.php 投注明細加總計算使用島此函示
// 後台的相關資訊:
// DB table:  root_statisticsdailyreport  每日營收日結報表
// Related:   每日營收報表, 搭配的程式功能說明
// statistics_daily_immediately.php    後台 - 即時統計 - 每日營收日結報表, 要修改下面的程式增加項目的時候，需要先使用這只程式即時測試函式並驗證。
// statistics_daily_report.php         後台 - 每日營收日結報表(讀取已生成資料庫頁面), 透過 php system 功能呼叫 statistics_daily_output_cmd.php 執行, 主要都從這個程式開始呼叫。
// statistics_daily_report_lib.php     後台 - 每日營收日結報表 - 專用函式庫(計算資料使用函式, 每個統計項目的公式都放這裡)
// statistics_daily_report_action.php  後台 - 每日營收日結報表動作程式 - 透過此程式呼叫 php system command 功能, 及其他後續擴充功能.
// statistics_daily_output_cmd.php     後台 - 每日營收日結報表(命令列模式, 主要用來排程生成日報表)
// command example: /usr/bin/php70 /home/testgpk2demo/web/begpk2/statistics_daily_report_output_cmd.php run 2017-02-26
// Log:
// 2017.2.27 改寫,原本的即時計算移除.以資料庫為主,排程定時統計。
// ----------------------------------------------------------------------------

function insert_statistics_daily_report_data($member_id, $member_parent_id, $member_account, $therole, $dailydate, stdClass $data)
{
    $realcash_info_json_string = json_encode($data->realcash_info);
    $betlog_detail_json_string = json_encode($data->betlog_detail);
    $transaction_detail_json_string = json_encode($data->transaction_detail);

    $tokendeposit = $data->gcash_tokendeposit + $data->gtoken_tokendeposit;
    $tokenfavorable = $data->gcash_tokenfavorable + $data->gtoken_tokenfavorable;
    $tokenpreferential = $data->gcash_tokenpreferential + $data->gtoken_tokenpreferential;
    $tokenpay = $data->gcash_tokenpay + $data->gtoken_tokenpay;
    $tokengcash = $data->gcash_tokengcash + $data->gtoken_tokengcash;
    $tokenrecycling = $data->gcash_tokenrecycling + $data->gtoken_tokenrecycling;
    $tokenadministrationfees = $data->gcash_tokenadministrationfees + $data->gtoken_tokenadministrationfees;
    $tokenadministration = $data->gcash_tokenadministration + $data->gtoken_tokenadministration;
    $apitokenwithdrawal = $data->gtoken_apitokenwithdrawal;
    $apitokendeposit = $data->gtoken_apitokendeposit;

    $cashdeposit = $data->gcash_cashdeposit + $data->gtoken_cashdeposit;
    $company_deposits = $data->gcash_company_deposits + $data->gtoken_company_deposits;
    $payonlinedeposit = $data->gcash_payonlinedeposit + $data->gtoken_payonlinedeposit;
    $cashtransfer = $data->gcash_cashtransfer + $data->gtoken_cashtransfer;
    $cashwithdrawal = $data->gcash_cashwithdrawal + $data->gtoken_cashwithdrawal;
    $cashgtoken = $data->gcash_cashgtoken + $data->gtoken_cashgtoken;
    $cashadministrationfees = $data->gcash_cashadministrationfees + $data->gtoken_cashadministrationfees;
    $apicashwithdrawal = $data->gcash_apicashwithdrawal;
    $apicashdeposit = $data->gcash_apicashdeposit;

    $statisticsdailyreport_check = "
    INSERT INTO root_statisticsdailyreport (
      member_id,
      member_therole,
      member_account,
      member_parent_id,
      dailydate,
      updatetime,

      agency_commission,
      tokendeposit,
      tokenfavorable,
      tokenpreferential,
      tokenpay,
      tokengcash,
      tokenrecycling,

      cashdeposit,
      company_deposits,
      payonlinedeposit,
      cashtransfer,
      cashwithdrawal,
      cashgtoken,

      notes,
      all_bets,
      all_wins,
      all_profitlost,
      all_count,

      cashadministrationfees,
      tokenadministrationfees,
      tokenadministration,
      apicashwithdrawal,
      apicashdeposit,
      apitokenwithdrawal,
      apitokendeposit,

      ec_sales,
      ec_cost,
      ec_profitlost,
      ec_count,

      member_gcash,
      member_gtoken,
      member_commission,
      member_prefer,
      member_bonus,

      realcash_info,
      betlog_detail,
      transaction_detail
    )
    VALUES (
      '" . $member_id . "',
      '" . $therole . "',
      '" . $member_account . "',
      '" . $member_parent_id . "',
      '" . $dailydate . "',
      now(),
      '" . $data->agent_review_reult . "',
      '" . $tokendeposit . "',
      '" . $tokenfavorable . "',
      '" . $tokenpreferential . "',
      '" . $tokenpay . "',
      '" . $tokengcash . "',
      '" . $tokenrecycling . "',
      '" . $cashdeposit . "',
      '" . $company_deposits . "',
      '" . $payonlinedeposit . "',
      '" . $cashtransfer . "',
      '" . $cashwithdrawal . "',
      '" . $cashgtoken . "',
      '',
      '" . $data->casino_all_bets . "',
      '" . $data->casino_all_wins . "',
      '" . $data->casino_all_profitlost . "',
      '" . $data->casino_all_count . "',

      '" . $cashadministrationfees . "',
      '" . $tokenadministrationfees . "',
      '" . $tokenadministration . "',

      '" . $apicashwithdrawal . "',
      '" . $apicashdeposit . "',
      '" . $apitokenwithdrawal . "',
      '" . $apitokendeposit . "',

      '" . $data->ec_sales . "',
      '" . $data->ec_cost . "',
      '" . $data->ec_profitlost . "',
      '" . $data->ec_count . "',

      '" . $data->member_gcash . "',
      '" . $data->member_gtoken . "',
      '" . $data->member_commission . "',
      '" . $data->member_prefer . "',
      '" . $data->member_bonus . "',
      '" . $realcash_info_json_string . "',
      '" . $betlog_detail_json_string . "',
      '" . $transaction_detail_json_string . "'
    )
  ON CONFLICT ON CONSTRAINT root_statisticsdailyreport_member_account_dailydate
  DO
  ";

    $statisticsdailyreport_check .= <<<SQL
  UPDATE
    SET updatetime = now(),
    agency_commission = '$data->agent_review_reult',
    tokendeposit = '$tokendeposit',
    tokenfavorable = '$tokenfavorable',
    tokenpreferential = '$tokenpreferential',
    tokenpay = '$tokenpay',
    tokengcash = '$tokengcash',
    tokenrecycling = '$tokenrecycling',
    cashdeposit = '$cashdeposit',
    company_deposits = '$company_deposits',
    payonlinedeposit = '$payonlinedeposit',
    cashtransfer = '$cashtransfer',
    cashwithdrawal = '$cashwithdrawal',
    cashgtoken = '$cashgtoken',
    all_bets = '$data->casino_all_bets',
    all_wins = '$data->casino_all_wins',
    all_profitlost = '$data->casino_all_profitlost',
    all_count = '$data->casino_all_count',

    cashadministrationfees  = '$cashadministrationfees',
    tokenadministrationfees = '$tokenadministrationfees',
    tokenadministration     = '$tokenadministration',

    apicashwithdrawal       = '$apicashwithdrawal',
    apicashdeposit          = '$apicashdeposit',
    apitokenwithdrawal      = '$apitokenwithdrawal',
    apitokendeposit         = '$apitokendeposit',

    ec_sales = '$data->ec_sales',
    ec_cost = '$data->ec_cost',
    ec_profitlost = '$data->ec_profitlost',
    ec_count = '$data->ec_count',

    member_gcash = '$data->member_gcash',
    member_gtoken = '$data->member_gtoken',
    member_commission = '$data->member_commission',
    member_prefer = '$data->member_prefer',
    member_bonus = '$data->member_bonus',

    realcash_info = '$realcash_info_json_string',
    betlog_detail = '$betlog_detail_json_string',
    transaction_detail = '$transaction_detail_json_string'
  ;
SQL;

    return $statisticsdailyreport_check;
}

function insert_statistics_daily_report_detail_data($member_id, $member_parent_id, $member_account, $therole, $dailydate, stdClass $data)
{
    $betlog_counts_json = json_encode($data->betlog_counts);
    $statisticsdailyreport_detail_sql = "
    INSERT INTO root_statisticsdailyreport_detail (
      id,
      member_id,
      member_therole,
      member_account,
      member_parent_id,
      dailydate,
      updatetime,
      betlog_counts
    )
    VALUES (
      (SELECT id from root_statisticsdailyreport WHERE member_account = '" . $member_account . "' AND dailydate = '" . $dailydate . "'),
      '" . $member_id . "',
      '" . $therole . "',
      '" . $member_account . "',
      '" . $member_parent_id . "',
      '" . $dailydate . "',
      now(),
      '" . $betlog_counts_json . "'
    )
  ON CONFLICT ON CONSTRAINT root_statisticsdailyreport_detail_member_account_dailydate
  DO
  ";

    $statisticsdailyreport_detail_sql .= <<<SQL
  UPDATE
    SET
      updatetime = now(),
      betlog_counts = '$betlog_counts_json'
  ;
SQL;

    return $statisticsdailyreport_detail_sql;
}

// get casino game categories and display name
function get_casino_game_categories_displayname()
{
    $casino_game_sql = <<<SQL
  SELECT
    casinoid,
    display_name,
    game_flatform_list
  FROM casino_list
  ORDER BY id
  ;
SQL;

    $casino_game_category_result = runSQLall($casino_game_sql);
    $casino_game_categories = [];

    foreach ($casino_game_category_result as $index => $casino_category) {
        if ($index == 0) {
            continue;
        }

        $casino_game_categories[strtolower($casino_category->casinoid)] = json_decode($casino_category->game_flatform_list, true);
        $casino_game_displayname[strtolower($casino_category->casinoid)] = $casino_category->display_name;
    }
    // var_dump($casino_game_categories);

    $casino_game_categories_displayname = array( 'categories' => $casino_game_categories, 'displayname' => $casino_game_displayname);

    return $casino_game_categories_displayname;
}

// -----------------------------------------
// 代理商審查的函式 -- 取得本日 $today_date 貢獻的傭金金額
// -----------------------------------------
function agent_review($today_date)
{

    $sql = "SELECT * FROM root_agent_review WHERE status = 1" .
        "AND processingtime >= '$today_date 00:00:00-04' AND processingtime < '$today_date 24:00:00-04'
  ;";

    // var_dump($sql);
    $result_sql = runSQLall($sql);
    unset($result_sql[0]);

    $r = [];

    foreach ($result_sql as $agent_review) {
        $r[$agent_review->account] = [
            'amount' => $agent_review->amount,
            'commissioned' => $agent_review->commissioned,
            'applicationtime' => $agent_review->applicationtime,
        ];
    }

    return ($r);
}
// -----------------------------------------

// get bettingrecords from remix table
function get_bettingrecords($today_date, array $casino_accounts)
{

    if (empty($casino_accounts)) {
        return [];
    }

    // 東部標準時（Eastern Standard Time；EST；UTC-5；R區）, 時間為 -05 才是正確的時間，夏令時間 -06 不列入計算。以美東時間每日為計算單位。
    $receivetime_begin = gmdate('Y-m-d H:i:s.u', strtotime($today_date . ' 00:00:00 -04') + 8 * 3600) . '+08:00';
    $receivetime_end = gmdate('Y-m-d H:i:s.u', strtotime($today_date . ' 23:59:59 -04') + 8 * 3600) . '+08:00';

    $casino_account_values_string = "('" . implode($casino_accounts, "'), ('") . "')";

    // casino category summary
    $bettingrecords_casino_category_sql = <<<SQL
    SELECT
      COUNT(casino_account) as bettingrecord_count,
      casino_account,
      casinoid as casino,
      favorable_category as category,
      SUM(betvalid) as bets,
      SUM(betresult) as wins
    FROM betrecordsremix
    INNER JOIN (
      VALUES $casino_account_values_string
    ) vals(v)
    ON (casino_account = v)
    WHERE receivetime BETWEEN '$receivetime_begin' AND '$receivetime_end'
      AND status = 1
    GROUP BY casino_account, casinoid, favorable_category;
SQL;

    $bettingrecords_result = runSQLall_betlog($bettingrecords_casino_category_sql);
    unset($bettingrecords_result[0]);

    $betting_records = [];

    foreach ($bettingrecords_result as $record) {

        $betting_records[$record->casino_account][$record->casino][$record->category] = [
            'count' => $record->bettingrecord_count,
            'bets' => $record->bets,
            'wins' => ($record->bets + $record->wins),
            'profitlost' => (-$record->wins),
        ];

    }

    return $betting_records;
}

// get summary from remix table
function bettingrecords_summary(array &$bettingrecords, array $game_accounts)
{

    $r = [
        'casino_summary' => [
            'casino_all_bets' => 0,
            'casino_all_wins' => 0,
            'casino_all_profitlost' => 0,
            'casino_all_count' => 0,
        ],
        'casino_category_summary' => [],
        'bet_count_summary' => [],
    ];

    // no account
    if (empty($game_accounts)) {
        return $r;
    }

    foreach ($game_accounts as $account) {

        if (!isset($bettingrecords[$account])) {
            continue;
        }

        foreach ($bettingrecords[$account] as $casino => $casino_summary) {

            foreach ($casino_summary as $category => $casino_category_summary) {
                $r['casino_summary']['casino_all_count'] += $casino_category_summary['count'];
                $r['casino_summary']['casino_all_bets'] += $casino_category_summary['bets'];
                $r['casino_summary']['casino_all_wins'] += $casino_category_summary['wins'];
                $r['casino_summary']['casino_all_profitlost'] += $casino_category_summary['profitlost'];

                // casino details
                $casino_prefix = strtolower($casino) . '_';
                $casino_count = $r['casino_summary'][$casino_prefix . 'count'] ?? 0;
                $casino_bet = $r['casino_summary'][$casino_prefix . 'bets'] ?? 0;
                $casino_wins = $r['casino_summary'][$casino_prefix . 'wins'] ?? 0;
                $casino_profitlost = $r['casino_summary'][$casino_prefix . 'profitlost'] ?? 0;

                $r['casino_summary'][$casino_prefix . 'count'] = $casino_count + $casino_category_summary['count'];
                $r['casino_summary'][$casino_prefix . 'bets'] = $casino_bet + $casino_category_summary['bets'];
                $r['casino_summary'][$casino_prefix . 'wins'] = $casino_wins + $casino_category_summary['wins'];
                $r['casino_summary'][$casino_prefix . 'profitlost'] = $casino_profitlost + $casino_category_summary['profitlost'];

                // category detials
                $casino_category_prefix = strtolower($casino) . '_' . $category . '_';
                $casino_category_bet = $r['casino_category_summary'][$casino_category_prefix . 'bets'] ?? 0;
                $casino_category_wins = $r['casino_category_summary'][$casino_category_prefix . 'wins'] ?? 0;
                $casino_category_profitlost = $r['casino_category_summary'][$casino_category_prefix . 'profitlost'] ?? 0;

                $r['casino_category_summary'][$casino_category_prefix . 'bets'] = $casino_category_bet + $casino_category_summary['bets'];
                $r['casino_category_summary'][$casino_category_prefix . 'wins'] = $casino_category_wins + $casino_category_summary['wins'];
                $r['casino_category_summary'][$casino_category_prefix . 'profitlost'] = $casino_category_profitlost + $casino_category_summary['profitlost'];

                $casino_category_count = $r['bet_count_summary'][$casino_category_prefix . 'count'] ?? 0;

                $r['casino_category_summary'][$casino_category_prefix . 'count'] = $casino_category_count + $casino_category_summary['count'];
                $r['bet_count_summary'][$casino_category_prefix . 'count'] = $casino_category_count + $casino_category_summary['count'];
            }

        }
    }

    return $r;
}

function gtoken_summary($today_date, array $member_accounts)
{

    if (empty($member_accounts)) {
        return [];
    }

    $account_values_string = "('" . implode($member_accounts, "'), ('") . "')";

    $gtoken_sql = <<<SQL
  SELECT
    source_transferaccount,
    transaction_category,
    realcash,
    SUM(withdrawal) as withdrawal_sum,
    SUM(deposit) as deposit_sum,
    COUNT(withdrawal) as withdrawal_count
  FROM root_member_gtokenpassbook
  INNER JOIN (
    VALUES $account_values_string
  ) vals(v)
  ON (source_transferaccount = v)
  WHERE transaction_time BETWEEN :begin_time AND :end_time
  GROUP BY transaction_category, source_transferaccount, realcash
  ;
SQL;

    $gtoken_result = runSQLall_prepared(
        $gtoken_sql,
        ['begin_time' => "$today_date 00:00:00-04", 'end_time' => "$today_date 24:00:00-04"],
        '',
        0,
        'r'
    );

    $r = [];

    foreach ($gtoken_result as $gtoken_summary) {

        $r[$gtoken_summary->source_transferaccount][$gtoken_summary->transaction_category][(int) ($gtoken_summary->realcash)] = [
            'withdrawal' => $gtoken_summary->withdrawal_sum,
            'deposit' => $gtoken_summary->deposit_sum,
            'withdrawal_count' => $gtoken_summary->withdrawal_count,
            'balance' => $gtoken_summary->deposit_sum - $gtoken_summary->withdrawal_sum,
        ];

        $withdrawal_summary = $r[$gtoken_summary->source_transferaccount]['summary']['withdrawal'] ?? 0;
        $deposit_summary = $r[$gtoken_summary->source_transferaccount]['summary']['deposit'] ?? 0;
        $withdrawal_count_summary = $r[$gtoken_summary->source_transferaccount]['summary']['withdrawal_count'] ?? 0;
        $balance_summary = $r[$gtoken_summary->source_transferaccount]['summary']['balance'] ?? 0;

        $r[$gtoken_summary->source_transferaccount]['summary'] = [
            'withdrawal' => $withdrawal_summary + $gtoken_summary->withdrawal_sum,
            'deposit' => $deposit_summary + $gtoken_summary->deposit_sum,
            'withdrawal_count' => $withdrawal_count_summary + $gtoken_summary->withdrawal_count,
            'balance' => $balance_summary + $gtoken_summary->deposit_sum - $gtoken_summary->withdrawal_sum,
        ];

    }

    return $r;
}

function gcash_summary($today_date, array $member_accounts)
{
    if (empty($member_accounts)) {
        return [];
    }

    $account_values_string = "('" . implode($member_accounts, "'), ('") . "')";

    $gcash_sql = <<<SQL
  SELECT
    source_transferaccount,
    transaction_category,
    realcash,
    SUM(withdrawal) as withdrawal_sum,
    SUM(deposit) as deposit_sum,
    COUNT(withdrawal) as withdrawal_count
  FROM root_member_gcashpassbook
  INNER JOIN (
    VALUES $account_values_string
  ) vals(v)
  ON (source_transferaccount = v)
  WHERE transaction_time BETWEEN :begin_time AND :end_time
  GROUP BY transaction_category, source_transferaccount, realcash
  ;
SQL;

    $gcash_result = runSQLall_prepared(
        $gcash_sql,
        ['begin_time' => "$today_date 00:00:00-04", 'end_time' => "$today_date 24:00:00-04"],
        '',
        0,
        'r'
    );

    $r = [];

    foreach ($gcash_result as $gcash_summary) {

        $r[$gcash_summary->source_transferaccount][$gcash_summary->transaction_category][(int) ($gcash_summary->realcash)] = [
            'withdrawal' => $gcash_summary->withdrawal_sum,
            'deposit' => $gcash_summary->deposit_sum,
            'withdrawal_count' => $gcash_summary->withdrawal_count,
            'balance' => $gcash_summary->deposit_sum - $gcash_summary->withdrawal_sum,
        ];

        $withdrawal_summary = $r[$gcash_summary->source_transferaccount]['summary']['withdrawal'] ?? 0;
        $deposit_summary = $r[$gcash_summary->source_transferaccount]['summary']['deposit'] ?? 0;
        $withdrawal_count_summary = $r[$gcash_summary->source_transferaccount]['summary']['withdrawal_count'] ?? 0;
        $balance_summary = $r[$gcash_summary->source_transferaccount]['summary']['balance'] ?? 0;

        $r[$gcash_summary->source_transferaccount]['summary'] = [
            'withdrawal' => $withdrawal_summary + $gcash_summary->withdrawal_sum,
            'deposit' => $deposit_summary + $gcash_summary->deposit_sum,
            'withdrawal_count' => $withdrawal_count_summary + $gcash_summary->withdrawal_count,
            'balance' => $balance_summary + $gcash_summary->deposit_sum - $gcash_summary->withdrawal_sum,
        ];

    }

    return $r;
}

function get_ec_order_summary($today_date, array $ec_accounts)
{
    if (empty($ec_accounts)) {
        return [];
    }

    global $stats_config;
    // 資料庫依據不同的條件變換資料庫檔案
    // $mg_bettingrecords_tables = 'test_mg_bettingrecords';
    $ec_records_tables = $stats_config['ec_bettingrecords_tables'];

    $account_values_string = "( '" . implode($ec_accounts, "'), ('") . "' )";

    $sql = <<<SQL
  SELECT
    ec_account,
    SUM(product_price_subtotal) as ec_sales,
    SUM(product_cost_subtotal) as ec_cost,
    COUNT(product_price_subtotal) as ec_count
  FROM $ec_records_tables
  INNER JOIN (
    VALUES $account_values_string
  ) vals(v)
  ON (ec_account = v)
  WHERE date_added BETWEEN '$today_date 00:00:00-04' AND '$today_date 24:00:00-04'
  GROUP BY ec_account;
SQL;
    // var_dump($sql);

    $result = runSQLall_betlog($sql, 0, 'EC');
    unset($result[0]);

    $r = [];

    foreach ($result as $record) {
        $r[$record->ec_account] = [
            'ec_count' => $record->ec_count,
            'ec_sales' => $record->ec_sales,
            'ec_cost' => $record->ec_cost,
            'ec_profitlost' => $record->ec_sales - $record->ec_cost,
        ];
    }

    // var_dump($r);
    return $r;
}

function receivemoney_summary($today_date, array $member_accounts)
{
    if (empty($member_accounts)) {
        return [];
    }

    $account_values_string = "( '" . implode($member_accounts, "'), ('") . "' )";

    $sql = <<<SQL
  SELECT member_account, transaction_category, SUM(gcash_balance) as gcash_sum, SUM(gtoken_balance) as gtoken_sum
  FROM root_receivemoney
  INNER JOIN (
    VALUES $account_values_string
  ) vals(v)
  ON (member_account = v)
  WHERE receivetime IS NULL
    AND status = 1
    AND givemoneytime BETWEEN :begin_time AND :end_time
  GROUP BY transaction_category, member_account
  ;
SQL;

    $result = runSQLall_prepared(
        $sql,
        ['begin_time' => "$today_date 00:00:00-04", 'end_time' => "$today_date 24:00:00-04"],
        '',
        0,
        'r'
    );

    $r = [];

    foreach ($result as $index => $group) {
        $r[$group->member_account][$group->transaction_category] = $group->gcash_sum + $group->gtoken_sum;
    }

    return $r;
}
// ----------------------------------------------------

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

/**
 * For testing
 * @param  [type] $date [description]
 * @return [type]       [description]
 */
function get_bettingrecords_from_statisticsbetting($date)
{
    $sql = <<<SQL
  SELECT
    member_account,
    casino_id as casino,
    category_detail->>'betfavor' as category,
    SUM( (category_detail->>'betvalid') :: numeric(20,2)) as bet,
    SUM( (category_detail->>'betprofit') :: numeric(20,2)) as profitlost
  FROM  root_statisticsbetting, json_array_elements (root_statisticsbetting.favorable_category :: json) category_detail
  WHERE dailydate = :date
  GROUP BY member_account, casino_id, category_detail->>'betfavor'
SQL;

    $result = runSQLall_prepared($sql, [':date' => $date]);

    $sql = <<<SQL
  SELECT
    member_account,
    casino_id as casino,
    SUM( account_betvalid ) as bet,
    SUM( account_profit ) as profitlost
  FROM  root_statisticsbetting
  WHERE dailydate = :date
  GROUP BY member_account, casino_id
SQL;

    $casino_result = runSQLall_prepared($sql, [':date' => $date]);

    $betting_records = [];
    $betting_summary = [];

    $casino_summary = [];

    foreach ($casino_result as $record) {
        $current_bet = $casino_summary[$record->casino]['bet'] ?? 0;
        $current_profitlost = $casino_summary[$record->casino]['profitlost'] ?? 0;

        $casino_summary[$record->casino]['bet'] = $current_bet + $record->bet;
        $casino_summary[$record->casino]['profitlost'] = $current_profitlost + $record->profitlost;
    }

    foreach ($result as $record) {
        $betting_records[$record->member_account][$record->casino][$record->category] = [
            'bet' => $record->bet,
            'profitlost' => $record->profitlost,
        ];

        $current_bet = $betting_summary[$record->casino][$record->category]['bet'] ?? 0;
        $current_profitlost = $betting_summary[$record->casino][$record->category]['profitlost'] ?? 0;

        $betting_summary[$record->casino][$record->category]['bet'] = $current_bet + $record->bet;
        $betting_summary[$record->casino][$record->category]['profitlost'] = $current_profitlost + $record->profitlost;

    }

    // print_r($betting_records['banana']);
    // print_r($betting_records['y0001']);

    print_r($betting_summary);
    print_r($casino_summary);
}

const HAVING_NONZERO_COND = <<<SQL
    sum(agency_commission) != 0 OR
    sum(mg_totalwager) != 0 OR
    sum(mg_totalpayout) != 0 OR
    sum(mg_profitlost) != 0 OR
    sum(mg_count) != 0 OR
    sum(pt_bets) != 0 OR
    sum(pt_wins) != 0 OR
    sum(pt_profitlost) != 0 OR
    sum(pt_jackpotbets) != 0 OR
    sum(pt_jackpotwins) != 0 OR
    sum(pt_jackpot_profitlost) != 0 OR
    sum(pt_count) != 0 OR
    sum(all_bets) != 0 OR
    sum(all_wins) != 0 OR
    sum(all_profitlost) != 0 OR
    sum(all_count) != 0 OR
    sum(cashdeposit) != 0 OR
    sum(payonlinedeposit) != 0 OR
    sum(apicashdeposit) != 0 OR
    sum(cashtransfer) != 0 OR
    sum(cashwithdrawal) != 0 OR
    sum(cashgtoken) != 0 OR
    sum(apitokendeposit) != 0 OR
    sum(tokendeposit) != 0 OR
    sum(tokenfavorable) != 0 OR
    sum(tokenpreferential) != 0 OR
    sum(tokenpay) != 0 OR
    sum(tokengcash) != 0 OR
    sum(tokenrecycling) != 0 OR
    sum(cashadministrationfees) != 0 OR
    sum(tokenadministrationfees) != 0 OR
    sum(tokenadministration) != 0 OR
    sum(company_deposits) != 0 OR
    sum(member_gcash) != 0 OR
    sum(member_gtoken) != 0
SQL;

const WHERE_NONZERO_COND = <<<SQL
    agency_commission != 0 OR
    mg_totalwager != 0 OR
    mg_totalpayout != 0 OR
    mg_profitlost != 0 OR
    mg_count != 0 OR
    pt_bets != 0 OR
    pt_wins != 0 OR
    pt_profitlost != 0 OR
    pt_jackpotbets != 0 OR
    pt_jackpotwins != 0 OR
    pt_jackpot_profitlost != 0 OR
    pt_count != 0 OR
    all_bets != 0 OR
    all_wins != 0 OR
    all_profitlost != 0 OR
    all_count != 0 OR
    cashdeposit != 0 OR
    payonlinedeposit != 0 OR
    apicashdeposit != 0 OR
    cashtransfer != 0 OR
    cashwithdrawal != 0 OR
    cashgtoken != 0 OR
    apitokendeposit != 0 OR
    tokendeposit != 0 OR
    tokenfavorable != 0 OR
    tokenpreferential != 0 OR
    tokenpay != 0 OR
    tokengcash != 0 OR
    tokenrecycling != 0 OR
    cashadministrationfees != 0 OR
    tokenadministrationfees != 0 OR
    tokenadministration != 0 OR
    company_deposits != 0 OR
    member_gcash != 0 OR
    member_gtoken != 0
SQL;
