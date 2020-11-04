<?php
// ----------------------------------------------------------------------------
// Features:	反水轉帳動作的處理
// File Name:	preferential_calculation_action.php
// Author:		Barkley
// Related:   對應 preferential_calculation.php
// Log:
// 2017.5.29 配合反水計算與發放功能處理的動作
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/lib_file.php";

require_once dirname(__FILE__) ."/lib_proccessing.php";

require_once dirname(__FILE__) ."/radiationbonus_organization_lib.php";


// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------


if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    die('(x)不合法的测试');
}

if(isset($_GET['k'])) {
  $logfile_sha = $_GET['k'];
}


// 取得 today date get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
if(isset($_GET['current_datepicker']) && validateDate($_GET['current_datepicker'], 'Y-m-d')) {
  // 格式正確
  $current_datepicker = $_GET['current_datepicker'];
}else{
  // php 格式的 2017-02-24
  // 轉換為美東的時間 date
  $date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
  date_timezone_set($date, timezone_open('America/St_Thomas'));
  $current_datepicker = date_format($date, 'Y-m-d');
}
// ---------------------------------------------------------------


// var_dump($_SESSION);
//var_dump($_POST);
// var_dump($_GET);


// ----------------------------------
// 動作為會員 action
// ----------------------------------
if($action == 'calculate_franchise_bonus') {

  $date_begin = $_POST['date_begin'] ?? '2018-04-01';
  $date_end = $_POST['date_end'] ?? '2018-04-30';

  $sql = <<<SQL
  SELECT
    member_account,
    account AS parent_account,
    MAX(member_parent_id) AS member_parent_id,
    SUM(franchise_fee) AS franchise_fee,
    SUM(franchise_bonus) AS franchise_bonus
  FROM radiationbonus_organization
  JOIN root_member ON root_member.id = radiationbonus_organization.member_parent_id
  WHERE dailydate_begin BETWEEN :date_begin AND :date_end
  GROUP BY member_account, account
SQL;

  $result = runSQLall_prepared($sql, [':date_begin' => $date_begin, ':date_end' => $date_end], null, 0, 'r');

  $r = [
    'summary' => [
      'date_range' => $date_begin . '~' . $date_end,
      'total_count' => count($result),
      'total_franchise_bonus' => 0,
    ],
    'summary_csv_string' => '',
    'summary_file_name' => '代理加盟金-' . $date_begin . '~' . $date_end . '.csv',
  ];

  $csv_string_generator = new CSVStringGenerator;

  $csv_string_generator->begin();

  $csv_header = [
    '会员帐号',
    '上线帐号',
    '代理加盟金',
    '总代理加盟奖金',
  ];

  $csv_string_generator->writeRow($csv_header);

  $total_franchise_bonus = 0;
  foreach ($result as $row) {
    $total_franchise_bonus += $row->franchise_bonus;

    $csv_string_generator->writeRow([
      $row->member_account,
      $row->parent_account,
      $row->franchise_fee,
      $row->franchise_bonus,
    ]);
  }

  $csv_string_generator->writeRow($csv_header);

  $r['summary']['total_franchise_bonus'] = $total_franchise_bonus;
  $r['summary_csv_string'] = $csv_string_generator->end();

  echo json_encode($r);

}elseif($action == 'download_detail_csv'){

  $date_begin = $_POST['date_begin'] ?? '2018-04-01';
  $date_end = $_POST['date_end'] ?? '2018-04-30';

  $sql = <<<SQL
  SELECT
    radiationbonus_organization.dailydate_begin AS date,
    radiationbonus_organization.member_account,
    franchise_bonus_source->>'from_account' AS from_account,
    SUM( (franchise_bonus_source->>'franchise_fee') :: numeric(20,2) ) AS franchise_fee,
    SUM( (franchise_bonus_source->>'from_franchise_bonus_proportion') :: numeric(20,2) ) AS franchise_bonus_proportion,
    SUM( (franchise_bonus_source->>'from_franchise_bonus') :: numeric(20,2) ) AS franchise_bonus
  FROM radiationbonus_organization, json_array_elements(radiationbonus_organization.franchise_bonus_source_list :: json) AS franchise_bonus_source
  WHERE radiationbonus_organization.dailydate_begin BETWEEN :date_begin AND :date_end
    AND radiationbonus_organization.franchise_bonus != '0'
  GROUP BY radiationbonus_organization.dailydate_begin, radiationbonus_organization.member_account, franchise_bonus_source->>'from_account'
SQL;

  $franchise_bonus_detail = runSQLall_prepared($sql, [':date_begin' => $date_begin, ':date_end' => $date_end], null, 0, 'r');

  $csv_string_generator = new CSVStringGenerator;

  $csv_string_generator->begin();

  $csv_header = [
    '加盟帐号',
    '加盟奖金帐号',
    '日期',
    '代理加盟金',
    '加盟奖金比率',
    '加盟奖金',
  ];

  $csv_string_generator->writeRow($csv_header);

  foreach ($franchise_bonus_detail as $row) {
    $csv_string_generator->writeRow([
      $row->from_account,
      $row->member_account,
      $row->date,
      $row->franchise_fee,
      $row->franchise_bonus_proportion * 100 . '%',
      $row->franchise_bonus,
    ]);
  }

  $csv_string_generator->writeRow($csv_header);

  echo json_encode([
    'file_name' => '代理加盟金明細-' . $date_begin . '~' . $date_end . '.csv',
    'csv_string' => $csv_string_generator->end(),
  ]);

}elseif($action == 'cmdrun' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
// -----------------------------------------------------------------------------
  if(isset($_GET['d']) AND validateDate($_GET['d'], 'Y-m-d')){
    $dailydate    = $_GET['d'];
    $file_key = sha1('franchise'.$dailydate);
    $reload_file = dirname(__FILE__) .'/tmp_dl/franchise_'.$file_key.'.tmp';

    if(file_exists($reload_file)) {
      die('請勿重覆操作');
    }else{
      $command   = $config['PHPCLI'].' radiationbonus_organization_cmd.php run '.$dailydate.' web > '.$reload_file.' &';
      // echo nl2br($command);

      // dispatch command and show loading view
      dispatch_proccessing(
        $command,
        '更新中...',
        $_SERVER['PHP_SELF'].'?a=franchise_update_reload&k='.$file_key,
        $reload_file
      );
    }
  }else{
    $output_html  = '日期格式有問題，請確定有且格式正確，需要為 YYYY-MM-DD 的格式';
    echo nl2br($output_html);
    echo '<p align="center"><input onclick="window.close();" value="關閉視窗" type="button"><p>';
  }

// -----------------------------------------------------------------------------
}elseif($action == 'franchise_update_reload' AND isset($logfile_sha) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
    $reload_file = dirname(__FILE__) .'/tmp_dl/franchise_'.$logfile_sha.'.tmp';
    if(file_exists($reload_file)) {
      echo file_get_contents($reload_file);
    }else{
      die('(x)不合法的測試');
    }
}elseif($action == 'franchise_del' AND isset($logfile_sha) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
    $reload_file = dirname(__FILE__) .'/tmp_dl/franchise_'.$logfile_sha.'.tmp';
    if(file_exists($reload_file)) {
      unlink($reload_file);
    }else{
      die('(x)不合法的測試');
    }
}else{
  $logger = '(x) 只有管理員或有權限的會員才可以使用。';
  echo $logger;
}

?>