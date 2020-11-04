<?php
// ----------------------------------------------------------------------------
// Features:	即時稽核計算 lib
// File Name:	token_auditorial_lib.php
// Author:		
// Related:   對應即時稽核功能
// Log:
//
// ----------------------------------------------------------------------------

require_once dirname(__FILE__) ."/config.php";
// 即時稽核 lib
require_once dirname(__FILE__) ."/token_auditorial_lib.php";


// ----------------------
// Main
// ----------------------
if(isset($_SERVER['HTTP_USER_AGENT']) OR isset($_SERVER['SERVER_NAME'])) {
  die('禁止使用網頁呼叫，來源錯誤，請使用命令列執行。');
}


if(isset($argv[1])) {

  $member_data = $argv[1] == 'ALL' ? get_all_member_data() : get_one_member_data($argv[1], 'account');

  if ($member_data[0] < 1) {
    echo "---------------------------------------\n";
    echo "查無會員資料\n";
    echo "---------------------------------------\n";
    die();
  }

  for ($i=1; $i <= $member_data[0]; $i++) {

    // $auditorial_result = get_auditorial_data($member_data[$i]);
    $auditorial_result = get_auditreport_calculate_sql($member_data[$i]);

    // $update_insert_msg = "會員 : ".$member_data[$i]->account." 無稽核資訊需要更新，更新至第(".$i."/".$member_data[0].")位會員\n";
    // $update_insert_msg = "會員 : ".$member_data[$i]->account." 無稽核資訊需要更新，更新至第(".$i."/30)位會員\n";
    $transaction_sql = '';
    if ($auditorial_result) {

      if (!isset($auditorial_result['sql'])) {
        echo "---------------------------------------\n";
        echo "無可執行的 SQL\n";
        echo "---------------------------------------\n";
        die();
      }

      if (!isset($auditorial_result['count'])) {
        echo "---------------------------------------\n";
        echo "資料筆數錯誤\n";
        echo "---------------------------------------\n";
        die();
      }

      $transaction_sql = 'BEGIN;';
      $transaction_sql = $transaction_sql.$auditorial_result['sql'];
      $transaction_sql = $transaction_sql.'COMMIT;';

      $update_insert_msg = "正在新增 / 更新會員 : ".$member_data[$i]->account." 稽核資訊，共 ".$auditorial_result['count']." 筆資料，更新至第(".$i."/".$member_data[0].")位會員\n";
      // $update_insert_msg = "正在新增 / 更新會員 : ".$member_data[$i]->account." 稽核資訊，共 ".$auditorial_result['count']." 筆資料，更新至第(".$i."/30)位會員\n";
      echo "---------------------------------------\n";
      echo $update_insert_msg;
      echo "---------------------------------------\n";

      $transaction_result = runSQLtransactions($transaction_sql);

      $program_end_time =  microtime(true);
      $program_time = round($program_end_time - $program_start_time, 3);

      $error_msg = "會員 : ".$member_data[$i]->account." 稽核資訊，共 ".$auditorial_result['count']." 筆資料，新增 / 更新成功, 花費時間: ".$program_time."秒\n";

      if (!$transaction_result) {
        $error_msg = "會員 : ".$member_data[$i]->account." 稽核資訊，共 ".$auditorial_result['count']." 筆資料，新增 / 更新失敗, 花費時間: ".$program_time."秒\n";
      }

      echo "---------------------------------------\n";
      echo $error_msg;
      echo "---------------------------------------\n";
      
    } else {
      $program_end_time =  microtime(true);
      $program_time = round($program_end_time - $program_start_time, 3);

      $update_insert_msg = "會員 : ".$member_data[$i]->account." 無稽核資訊需要更新，更新至第(".$i."/".$member_data[0].")位會員, 花費時間: ".$program_time."秒\n";

      echo "---------------------------------------\n";
      echo $update_insert_msg;
      echo "---------------------------------------\n";
    }

  }

  $program_end_time =  microtime(true);
  $program_time = round($program_end_time - $program_start_time, 3);

  echo "---------------------------------------\n";
  echo "共 ".$member_data[0]." 位會員稽核資訊新增 / 更新完畢, 花費時間: ".$program_time."秒\n";
  echo "---------------------------------------\n";

} else {
  echo "Command: $argv[0] account  \n" ;
  echo "Example: $argv[0] ALL  # 針對所有會員產生預先稽核資料\n" ;
  echo "Example: $argv[0] aaa  # 針對帳號aaa產生預先稽核資料\n" ;
  die();
}

?>