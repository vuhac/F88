<?php
// ----------------------------------------------------------------------------
// Features:	反水明細。
// File Name:	preferential_calculation_detail.php
// Author:		Yuan
// Related:
// Log:
// ----------------------------------------------------------------------------
/*
DB Table :
  root_statisticsdailypreferential
*/

session_start();

// 主機及資料庫設定
require_once __DIR__ ."/config.php";
// 支援多國語系
require_once __DIR__ ."/i18n/language.php";
// 自訂函式庫
require_once __DIR__ ."/lib.php";

require_once __DIR__ ."/lib_view.php";

// var_dump($_SESSION);

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------


// validate request
if( ! (isset($_GET['member_account']) && isset($_GET['dailydate'])) ) {
  $member_account = $_SESSION['member']->account;
  $date = new \DateTime;
  $dailydate = date_format($date->modify('+1 day'), 'Y-m-d');
} else {
  $member_account = $_GET['member_account'];
  $dailydate = $_GET['dailydate'];
}

// validate dailydate format
try {
  $datetime = new \DateTime($dailydate);
  $dailydate = $datetime->format('Y-m-d');
} catch(\Exception $e) {
  $date = new \DateTime;
  $dailydate = date_format($date->modify('+1 day'), 'Y-m-d');
}

// get preferential_detail
$sql =<<<SQL
  SELECT *
    FROM root_statisticsdailypreferential as detail
    WHERE detail.member_account = :member_account AND detail.dailydate = :dailydate;
SQL;

$preferential_detail_result = runSQLall_prepared($sql, [':member_account' => $member_account, ':dailydate' => $dailydate]);


if(isset($preferential_detail_result[0]) ) {
  $preferential_detail = $preferential_detail_result[0];
} else {
  // id not exsited
  // render404();
  // die();

  $preferential_detail = (object)[
    'member_account' => $member_account,
    'dailydate' => $dailydate,
    'all_favorablerate_amount' => '-',
    'all_favorablerate_amount_detail' => '{}',
    'favorable_distribute' => '{}',
  ];
}

// get data and decode json

$preferential_detail->all_favorablerate_amount_detail = json_decode($preferential_detail->all_favorablerate_amount_detail, true);
$preferential_detail->favorable_distribute = json_decode($preferential_detail->favorable_distribute, true);

// 無來自下線的反水
$has_no_preferential_from_successor = (
  ! isset($preferential_detail->all_favorablerate_amount_detail['level_distribute'])
  || empty($preferential_detail->all_favorablerate_amount_detail['level_distribute'])
);

// 無自身反水
$has_no_level_distribute = (
  ! isset($preferential_detail->favorable_distribute['level_distribute'])
  || empty($preferential_detail->favorable_distribute['level_distribute'])
);

// 自身反水比
$self_favorablerate = 0;
if(isset($preferential_detail->all_favorablerate_amount_detail['self_favorablerate'])) {
  $self_favorablerate = $preferential_detail->all_favorablerate_amount_detail['self_favorablerate'];
}


// render view
$tmpl['html_meta_title'] = '代理商收入摘要-'.$tr['host_name'];
$tmpl['page_title'] = '代理商收入摘要-'.$tr['host_name'];

return render(
  __DIR__ . '/preferential_calculation_detail.view.php',
  compact(
    'preferential_detail',
    'has_no_preferential_from_successor',
    'has_no_level_distribute',
    'self_favorablerate'
  )
);
