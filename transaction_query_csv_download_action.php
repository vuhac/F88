<?php
// ----------------------------------------------------------------------------
// Features: 交易紀錄查詢csv下載
// File Name:	transaction_query_csv_download_action.php
// Author: Neil
// Related:   
// Log:
// ----------------------------------------------------------------------------
session_start();

require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";
// 報表匯出函式庫
require_once dirname(__FILE__) ."/lib_file.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib_common.php";

require_once dirname(__FILE__) . "/lib_transaction_query.php";

// xlsx
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------
// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

if(!isset($_SESSION['agent']) || $_SESSION['agent']->therole != 'R') {
  header('Location:./home.php');
  die();
}

$validateResult = get_object_vars(jwtdec('transaction_query', $_GET['csv']));

$passbookConfig = getPassbookConfig();

$requirement = combineSelectRequirement($validateResult);

$sql = [];
$grade = '(\''.implode('\',\'', $validateResult['grade']).'\')';
foreach ($validateResult['passbook'] as $v) {
  // $table[$v] 拿不到回傳error
  $sql[] = combineSelecteSql($v, $passbookConfig[$v]['table'], $passbookConfig[$v]['account'], $grade).' AND '.$requirement;
}

$datatableInitSQL = "(".implode(" ) UNION ( ", $sql).")". 'ORDER BY trans_time DESC';
$datatableInitData = runSQLall($datatableInitSQL);

if (empty($datatableInitData[0])) {
  echo json_encode(['status' => 'fail', 'result' => '資料查詢失敗']);
  die();
}

// unset($datatableInitData[0]);

data_toxlsx($datatableInitData);

// 2019/11/22 csv->xlsx
function data_toxlsx($data){
  global $tr;
  global $passbookConfig;
  global $transaction_category;

  $realcashStrList = [
    0 => $tr['n'],
    1 => $tr['y'],
    2 => $tr['n']
  ];

  if($data[0] >= 1){
    $k = $c = 1;
    // 欄位名稱
    $xls_transaction_query[0][$c++] = $tr['Transaction number'];
    $xls_transaction_query[0][$c++] = $tr['Transaction order number'];
    $xls_transaction_query[0][$c++] = $tr['memebr name'];
    $xls_transaction_query[0][$c++] = $tr['Trading Hours'];
    $xls_transaction_query[0][$c++] = $tr['deposit amount'];
    $xls_transaction_query[0][$c++] = $tr['withdrawal amount'];
    $xls_transaction_query[0][$c++] = $tr['Payout'];
    $xls_transaction_query[0][$c++] = $tr['current balance'];
    $xls_transaction_query[0][$c++] = $tr['Transaction Category'];
    $xls_transaction_query[0][$c++] = $tr['Transfer to account'];
    $xls_transaction_query[0][$c++] = $tr['operator'];
    $xls_transaction_query[0][$c++] = $tr['Actual deposit'];
    $xls_transaction_query[0][$c++] = $tr['wallet'];

    for($i = 1;$i <= $data[0];$i++){
      $c = 1;

      $realcashStr = (!array_key_exists($data[$i]->realcash, $realcashStrList)) ? $tr['The real withdrawal error'] : $realcashStrList[$data[$i]->realcash];
      $walletStr = $passbookConfig[$data[$i]->type]['str'];
      $transactionCategoryStr = (in_array($data[$i]->transaction_category, array_keys($transaction_category))) ? $transaction_category[$data[$i]->transaction_category] : $tr['Transaction type error, please contact the system staff'];

      $xls_transaction_query[$i][$c++] = $data[$i]->trans_id;
      $xls_transaction_query[$i][$c++] = $data[$i]->transaction_id;
      $xls_transaction_query[$i][$c++] = $data[$i]->source_transferaccount;
      $xls_transaction_query[$i][$c++] = $data[$i]->trans_time;
      $xls_transaction_query[$i][$c++] = $data[$i]->deposit;
      $xls_transaction_query[$i][$c++] = $data[$i]->withdrawal;
      $xls_transaction_query[$i][$c++] = $data[$i]->payout;
      $xls_transaction_query[$i][$c++] = $data[$i]->balance;

      $xls_transaction_query[$i][$c++] = $transactionCategoryStr;
      $xls_transaction_query[$i][$c++] = $data[$i]->destination_transferaccount;
      $xls_transaction_query[$i][$c++] = $data[$i]->operator;
      $xls_transaction_query[$i][$c++] = $realcashStr;
      $xls_transaction_query[$i][$c++] = $walletStr;
      
      $k++;
    };
  }else{
    echo '<script>alert("无交易纪录");</script>';die();
  };

  // 清除快取防亂碼
  ob_end_clean();

  $spredsheet = new Spreadsheet();

  $myworksheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spredsheet, '交易纪录');

  // Attach the "My Data" worksheet as the first worksheet in the Spreadsheet object
  $spredsheet->addSheet($myworksheet, 0);

  // 總表索引標籤開始寫入資料
  $sheet = $spredsheet->setActiveSheetIndex(0);
  // 寫入資料陣列
  $sheet->fromArray($xls_transaction_query,NULL,'A1',true);

  // 自動欄寬
  $worksheet = $spredsheet->getActiveSheet();

  foreach (range('A', $worksheet->getHighestColumn()) as $column) {
    $spredsheet->getActiveSheet()->getColumnDimension($column)->setAutoSize(true);
  };

  // xlsx
  $file_name = 'transaction_'.date("Y-m-d_His");
  
  flush();

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment;filename="'.$file_name.'.xlsx"');
  header('Cache-Control: max-age=0');

  // 直接匯出，不存於disk
  $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spredsheet, 'Xlsx');
  $writer->save('php://output');

  die();
}

// combineCsvDownloadData($datatableInitData);

// function combineCsvDownloadData($data)
// {
//   global $tr;
//   global $passbookConfig;
//   global $transaction_category;

//   $csvTitle = [];
//   $csvContent = [];
//   $realcashStrList = [
//     0 => $tr['n'],
//     1 => $tr['y'],
//     2 => $tr['n']
//   ];

//   $csvKey = sha1('root_transaction_query');

//   $csvTitle[$csvKey] = [
//     $tr['Transaction number'] ,
//     $tr['Transaction order number'],
//     $tr['memebr name'],
//     $tr['Trading Hours'],
//     $tr['deposit amount'],
//     $tr['withdrawal amount'],
//     $tr['Payout'],
//     $tr['current balance'],
//     $tr['Transaction Category'],
//     $tr['Transfer to account'],
//     $tr['operator'],
//     $tr['Actual deposit'],
//     $tr['wallet']
//   ];

//   // $csvTitle[$csvKey] = [
//   //   '交易序号',
//   //   '交易单号',
//   //   '会员名称',
//   //   '交易时间(美东时间)',
//   //   '存款金额',
//   //   '提款金额',
//   //   '派彩',
//   //   '当下余额',
//   //   '交易类别',
//   //   '转入帐号',
//   //   '操作人员',
//   //   '实际存提',
//   //   '钱包'
//   // ];

//   foreach ($data as $k => $v) {
//     $realcashStr = (!array_key_exists($v->realcash, $realcashStrList)) ? $tr['The real withdrawal error'] : $realcashStrList[$v->realcash];
//     $walletStr = $passbookConfig[$v->type]['str'];
//     $transactionCategoryStr = (in_array($v->transaction_category, array_keys($transaction_category))) ? $transaction_category[$v->transaction_category] : $tr['Transaction type error, please contact the system staff'];

//     $csvContent[$csvKey][$k] = [
//       $v->trans_id,
//       $v->transaction_id,
//       $v->source_transferaccount,
//       $v->trans_time,
//       $v->deposit,
//       $v->withdrawal,
//       $v->payout,
//       $v->balance,
//       $transactionCategoryStr,
//       $v->destination_transferaccount,
//       $v->operator,
//       $realcashStr,
//       $walletStr
//     ];
//   }

//   $filename = 'transaction_' . date("Y-m-d_His") . '.csv';

//   $csv_stream = new CSVStream($filename);
//   $csv_stream->begin();

//   foreach ($csvTitle as $title) {
//     $csv_stream->writeRow($title);
//   }

//   foreach ($csvContent as $wline) {
//     foreach ($wline as $line) {
//       $csv_stream->writeRow($line);
//     }
//   }

//   $csv_stream->end();
// }
