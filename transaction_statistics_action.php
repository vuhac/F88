<?php
// ----------------------------------------------------------------------------
// Features: 將特定日期的會員存取款匯出成 CSV
// File Name: transcation_statistics_action.php
// Author: Webb Lu
// Related:
//    transcation_statistics.php
//    DB table: root_member, root_member_gcashpassbook, root_member_gtokenpassbook
//
// Log:
// ----------------------------------------------------------------------------

session_start();
// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";
require_once dirname(__FILE__) . "/lib_file.php";

// xlsx
// use PhpOffice\PhpSpreadsheet\Spreadsheet;
// use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------
// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------
// -------------------------------------------------------------------------
// 本程式使用的 function
// -------------------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s') {
  $d = DateTime::createFromFormat($format, $date);
  return $d && $d->format($format) == $date;
}

// 產生 CSV 檔案，並回傳檔案的絕對路徑
function getCSVfilepath($csv_key_title, $csv_array, $filename = null) {
  $filename = $filename ?? sha1(time());
  $filename .= '.csv';
  $absfilename = __DIR__ . "/tmp_dl/$filename";
  $filehandle = fopen("$absfilename", "w");

  $filehandle != false or die('打開 csv 檔案時出錯!');

  // Windows下使用BOM来标记文本文件的编码方式, 否則 EXCEL 開啟這個檔案會是亂碼
  fwrite($filehandle, chr(0xEF) . chr(0xBB) . chr(0xBF));

  // 將資料輸出到檔案 -- Title
  // foreach ($csv_key_title as $wline) fputcsv($filehandle, $wline);
  fputcsv($filehandle, $csv_key_title);
  // 將資料輸出到檔案 -- data
  foreach ($csv_array as $wline) {
    fputcsv($filehandle, $wline);
    // foreach ($wline as $line) fputcsv($filehandle, $line);
  }
  // 將資料輸出到檔案 -- Title
  // foreach ($csv_key_title as $wline) fputcsv($filehandle, $wline);

  fclose($filehandle);

  // /path/to/tmp_dl/file.csv
  $common_path = str_replace(__DIR__, pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME), $absfilename);
  $common_path = preg_replace('#/+#', '/', $common_path);

  return ['abs_path' => $absfilename, 'common_path' => $common_path];
}

// -------------------------------------------------------------------------
// END function lib
// -------------------------------------------------------------------------
// -------------------------------------------------------------------------
// GET / POST 傳值處理
// -------------------------------------------------------------------------
// 過濾 GET 字串；檢測是否有指定 action 行為
// $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_URL);
$action = filter_input(INPUT_GET, 'a', FILTER_SANITIZE_URL);
$format = filter_input(INPUT_GET, 'format', FILTER_SANITIZE_STRIPPED) ?? 'json';
$today = gmdate('Y-m-d', strtotime('now -4 hours'));

if ($action === 'fetch') {
  $is_today = filter_input(INPUT_GET, 'is_today', FILTER_VALIDATE_BOOLEAN) and false;
  $offset = filter_input(INPUT_POST, 'start', FILTER_VALIDATE_INT) ?? 1;
  $per_page = filter_input(INPUT_POST, 'length', FILTER_VALIDATE_INT) ?? 15;
  $irecord_draw = filter_input(INPUT_POST, 'draw', FILTER_VALIDATE_INT);
  $searches = filter_input(INPUT_POST, 'custom_search', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
  $account = filter_var($searches['account'] ?? '', FILTER_SANITIZE_STRIPPED);
  $account_type = array_filter(explode(',', filter_var($searches['account_type'] ?? '', FILTER_SANITIZE_STRIPPED)));
  $agent = filter_var($searches['agent'] ?? '', FILTER_SANITIZE_STRIPPED);

  $sdate = filter_var($searches['sdate'] ?? $today, FILTER_SANITIZE_STRIPPED);
  $edate = filter_var($searches['edate'] ?? $today, FILTER_SANITIZE_STRIPPED);
  $need_filter = filter_var($searches['need_filter'] ?? true, FILTER_VALIDATE_BOOLEAN);

  $transaction_statistic_detail = new TransactionStatisticDetail;
  // !$is_today and validateDate($sdate, 'Y-m-d H:i') and $transaction_statistic_detail->setStart2EndPoint($sdate);
  // !$is_today and validateDate($edate, 'Y-m-d H:i') and $transaction_statistic_detail->setStart2EndPoint('', $edate);

  // 原版
  !$is_today and validateDate($sdate, 'Y-m-d') and $transaction_statistic_detail->setStart2EndPoint($sdate);
  !$is_today and validateDate($edate, 'Y-m-d') and $transaction_statistic_detail->setStart2EndPoint('', $edate);

  $csvData = $transaction_statistic_detail->getCsvData($need_filter);

  // WIP: 加入搜尋條件
  if (!empty($account)) {
    $csvData = array_filter($csvData, function ($row) use ($account) {return $row['account'] == $account;});
  } else if (!empty($agent)) {
    $acclist = [];
    $agent = runSQLall_prepared("SELECT account, id FROM root_member WHERE account = :agent", ['agent' => $agent])[0] ?? null;
    is_object($agent)
    and $childs = runSQLall_prepared("SELECT account FROM root_member WHERE parent_id = :agentid", ['agentid' => $agent->id])
    and $acclist = [$agent->account] + array_column($childs, 'account');
    $csvData = array_filter($csvData, function ($row) use ($acclist) {return in_array($row['account'], $acclist);});
  }

  $acctypes = array_filter(
    ['M' => $tr['M'], 'A' =>$tr['A']],
    function ($key) use ($account_type) {return in_array($key, $account_type);},
    ARRAY_FILTER_USE_KEY
  );

  $csvData = array_filter($csvData, function ($row) use ($acctypes) {return in_array($row['therole'], $acctypes);});
  /**
   * 過濾存取款統計為 0 的資料
   * 可能用戶沒有存取款但有派彩，所以 token 存簿中會有交易紀錄
   */
  $need_filter and $csvData = array_filter($csvData, function ($row) {
    $has_tx = false;
    foreach ($row as $attr => $value) {
      if (in_array($attr, ['account', 'member_id', 'parent_id', 'therole'])) {
        continue;
      }
      if ($value != 0) {
        $has_tx = true;
        break;
      }
    }
    return $has_tx;
  });

  switch ($format) {
  case 'xls':
  case 'xlsx':
    // csv轉excel
    $filename = "transaction_statistics_inteval_{$sdate}_to_{$edate}.csv";
    $file_path = dirname(__FILE__) . '/tmp_dl/' . $filename;

    $csv_stream = new CSVWriter($file_path);
    $csv_stream->begin();

    // 欄位標題
    $csv_stream->writeRow(array_map(function ($columnInfo) {
      return $columnInfo['title'];
    }, $transaction_statistic_detail->csvDefinition));

    $csv_stream->write($csvData);
    $csv_stream->end();

    $excel_stream = new csvtoexcel($filename, $file_path);
    $excel_stream->begin();
    break;
  case 'json':
    $csvData = array_values($csvData);
    $csvData = array_map(function ($row) {
      array_walk($row, function (&$attr) {
        !$attr and $attr = round($attr, 2);
      });
      return $row;
    },
      $csvData
    );

    $pagination = new ArrayPaginator($csvData, $per_page);
    $data = $pagination->offset($offset)->items();

    if ($pagination->count() > 0) {
      $accounts = implode("', '", array_column($data, 'account'));
      $accounts = "'$accounts'";
      $result = runSQLall_prepared("SELECT id, account, parent_id FROM root_member WHERE account IN ($accounts)");
      $membersinfo = [];
      foreach ($result as $k => $v) {
        $membersinfo[$v->account] = $v;
      }
      foreach ($data as &$row) {
        $row['member_id'] = $membersinfo[$row['account']]->id;
        $row['parent_id'] = $membersinfo[$row['account']]->parent_id;
      }
    }

    $response = [
      'draw' => $irecord_draw,
      'iTotalRecords' => $pagination->count(),
      'iTotalDisplayRecords' => $pagination->count('filtered'),
      'data' => $data,
    ];

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode($response);
    break;
  }
}
return null;

// 執行對應的 action
switch ($action) {
case 'getCSV':
  $need_filter = filter_input(INPUT_GET, 'need_filter', FILTER_VALIDATE_BOOLEAN);
  $sdate = filter_input(INPUT_GET, 'sdate', FILTER_SANITIZE_URL) ?? '';
  $edate = filter_input(INPUT_GET, 'edate', FILTER_SANITIZE_URL) ?? '';

  $transaction_statistic_detail = new TransactionStatisticDetail;
  !$is_today and validateDate($sdate, 'Y-m-d') and $transaction_statistic_detail->setStart2EndPoint($sdate);
  !$is_today and validateDate($edate, 'Y-m-d') and $transaction_statistic_detail->setStart2EndPoint('', $edate);

  // csv轉excel
  $filename = "transaction_statistics_inteval_{$sdate}_to_{$edate}.csv";
  $file_path = dirname(__FILE__) . '/tmp_dl/' . $filename;

  $csv_stream = new CSVWriter($file_path);
  $csv_stream->begin();

  // 欄位標題
  $csv_stream->writeRow(array_map(function ($columnInfo) {
    return $columnInfo['title'];
  }, $transaction_statistic_detail->csvDefinition));

  $csv_stream->write($transaction_statistic_detail->getCsvData($need_filter));
  $csv_stream->end();

  $excel_stream = new csvtoexcel($filename, $file_path);
  $excel_stream->begin();

  break;
case 'getDataTable':
  $current_page = filter_input(INPUT_POST, 'start', FILTER_VALIDATE_INT) ?? 1;
  $per_page = filter_input(INPUT_POST, 'length', FILTER_VALIDATE_INT) ?? 15;
  $irecord_draw = filter_input(INPUT_POST, 'draw', FILTER_VALIDATE_INT);
  $searches = $_POST['custom_search'];
  $account = filter_var($searches['account'] ?? '', FILTER_SANITIZE_STRIPPED);
  $account_type = explode(',', filter_var($searches['account_type'] ?? '', FILTER_SANITIZE_STRIPPED));
  $agent = filter_var($searches['agent'] ?? '', FILTER_SANITIZE_STRIPPED);

  $sdate = filter_var($searches['sdate'], FILTER_SANITIZE_STRIPPED);
  $edate = filter_var($searches['edate'], FILTER_SANITIZE_STRIPPED);
  $need_filter = filter_var($searches['need_filter'], FILTER_VALIDATE_BOOLEAN);

  $transaction_statistic_detail = new TransactionStatisticDetail;
  !$is_today and validateDate($sdate, 'Y-m-d') and $transaction_statistic_detail->setStart2EndPoint($sdate);
  !$is_today and validateDate($edate, 'Y-m-d') and $transaction_statistic_detail->setStart2EndPoint('', $edate);

  $csvData = array_values($transaction_statistic_detail->getCsvData());
  $csvData = array_map(function ($row) {
    array_walk($row, function (&$attr) {
      !$attr and $attr = round($attr, 2);
    });
    return $row;
  },
    $csvData
  );

  $pagination = new ArrayPaginator($csvData, $per_page, $current_page);

  // WIP: 加入搜尋條件
  !empty($account) and $pagination->filter(function ($row) use ($account) {return $row->account == $account;});
  !empty($agent) and $pagination->filter(function ($row) use ($account) {return $row->account == $account;});

  $response = [
    'draw' => $irecord_draw,
    'iTotalRecords' => $pagination->count(),
    'iTotalDisplayRecords' => $pagination->count('filtered'),
    'data' => $pagination->items(),
  ];

  http_response_code(200);
  header('Content-Type: application/json');
  echo json_encode($response);
  break;
}

/**
 * 這個 Class 和 10 分鐘 | 日報表的 TransactionDetail 不一樣，
 * 是直接從 passbook 中取得資料
 * 和日報表統計應該要對得起來
 *
 * 處理完的結構：交易類別->貨幣類別->實際存提，可以套用 TransactionDetail Class
 */
class TransactionStatisticDetail {
  private $timestamp;
  private $nowString;
  private $dailydate;
  private $dailydate_start;
  private $dailydate_end;
  private $timezone = 'America/St_Thomas';
  private $dateTime = null;
  private $transactionStatisticRaw;
  private $transactionStatisticData;
  protected $systemTranscationCategories = [];
  public $csvDefinition = [];

  // 預設
  public function __construct($timeString = null) {
    global $transaction_category;
    $this->systemTranscationCategories = &$transaction_category;

    $timeString = $timeString ?? 'now';
    $this->setCurrentTime($timeString);
    $this->setCsvColumn();
  }

  // 日期
  public function setStart2EndPoint($sdate = '', $edate = '') {
    if (!empty($sdate)) {
      $this->dailydate_start = $sdate;
    }

    if (!empty($edate)) {
      $this->dailydate_end = $edate;
    }

    $this->setTransactionStatisticRaw();
    $this->setTransactionStatisticData();
    $this->setCsvColumn();
  }

  private function setCurrentTime($timeString) {
    $this->dateTime = new DateTime($timeString);
    $this->dateTime->setTimezone(new DateTimeZone($this->timezone));
    $this->timestamp = $this->dateTime->getTimestamp();
    $this->nowString = $this->dateTime->format('Y-m-d H:i:s');
    $this->dailydate = $this->dateTime->format('Y-m-d');
    // 預設只撈取當日
    $this->setStart2EndPoint($this->dailydate, $this->dailydate);
  }

  private function setTransactionStatisticRaw() {
    global $gcash_cashier_account, $gtoken_cashier_account;

    $sql_stmt_gtokenincasino = '';

    if ($this->dailydate == $this->dailydate_start && $this->dailydate == $this->dailydate_end) {
      // 撈取當日時，則顯示錢包餘額
      // 取得娛樂城列表，以計算有多少遊戲幣在娛樂城內
      $casinoids = runSQLall_prepared("SELECT casinoid FROM casino_list", [], '', 0, 'r');
      array_walk($casinoids, function (&$val, $key) {
        $val = "(casino_accounts->'$val->casinoid'->>'balance')::float";
      });

      $sql_stmt_gtokenincasino = ',' . implode(' + ', $casinoids) . ' AS tokenincasino_balance';
    }

    // 日報只有算金額，沒有算次數
    // 算出來的資料可與日報做對照
    $get_passbook_sql = <<<SQL
            WITH "interval_gcashpassbook" AS (
                SELECT * FROM root_member_gcashpassbook
                WHERE
                    transaction_time AT TIME ZONE('AST') BETWEEN date(:sdate) AND date(:edate) + integer '1' AND
                    source_transferaccount != '{$gcash_cashier_account}'
            ),
            "interval_statistics_gcashpassbook" AS (
                SELECT id AS member_id, account, therole, tabA.*, tabB.* FROM root_member LEFT JOIN (
                    SELECT
                        source_transferaccount,
                        sum(withdrawal) as withdrawal_sum,
                        sum(deposit) as deposit_sum,
                        count(withdrawal) as transaction_count,
                        -- count(deposit) as deposit_count,
                        sum(deposit) - sum(withdrawal) as balance_sum,
                        transaction_category,
                        realcash,
                        'gcashpassbook'::text as src
                    FROM interval_gcashpassbook
                    GROUP BY transaction_category, source_transferaccount, realcash
                ) tabA ON root_member.account = tabA.source_transferaccount LEFT JOIN (
                    SELECT
                        id AS root_member_wallets_id,
                        gcash_balance AS cashinwallet_balance,
                        gtoken_balance AS tokeninwallet_balance
                        $sql_stmt_gtokenincasino
                    FROM root_member_wallets
                ) tabB ON root_member.id = tabB.root_member_wallets_id
                WHERE therole != 'R' ORDER BY account ASC
            ),
            "interval_gtokenpassbook" AS (
                SELECT * FROM root_member_gtokenpassbook
                WHERE
                    transaction_time AT TIME ZONE('AST') BETWEEN date(:sdate) AND date(:edate) + integer '1' AND
                    source_transferaccount != '{$gtoken_cashier_account}'
            ),
            "interval_statistics_gtokenpassbook" AS (
            SELECT id AS member_id, account, therole, tabA.*, tabB.* FROM root_member LEFT JOIN (
                SELECT
                    source_transferaccount,
                    sum(withdrawal) as withdrawal_sum,
                    sum(deposit) as deposit_sum,
                    count(withdrawal) as transaction_count,
                    -- count(deposit) as deposit_count,
                    sum(deposit) - sum(withdrawal) as balance_sum,
                    transaction_category,
                    realcash,
                    'gtokenpassbook'::text as src
                    FROM interval_gtokenpassbook
                GROUP BY transaction_category, source_transferaccount, realcash
                ) tabA ON root_member.account = tabA.source_transferaccount LEFT JOIN (
                    SELECT
                        id AS root_member_wallets_id,
                        gcash_balance AS cashinwallet_balance,
                        gtoken_balance AS tokeninwallet_balance
                        $sql_stmt_gtokenincasino
                    FROM root_member_wallets
                ) tabB ON root_member.id = tabB.root_member_wallets_id
                WHERE therole != 'R' ORDER BY account ASC
            )

            SELECT * FROM interval_statistics_gcashpassbook
            UNION ALL
            SELECT * FROM interval_statistics_gtokenpassbook;
SQL;

    $this->transactionStatisticRaw = runSQLall_prepared($get_passbook_sql, ['sdate' => $this->dailydate_start, 'edate' => $this->dailydate_end]);
    // var_dump($this->transactionStatisticRaw);die();

  }

  private function setTransactionStatisticData() {
    $result = [];
    foreach ($this->transactionStatisticRaw as $row) {
      if (!isset($result[$row->account])) {
        $result[$row->account] = [
          'account' => $row->account,
          'therole' => $row->therole,
          'cashinwallet_balance' => $row->cashinwallet_balance ?? 0,
          'tokeninwallet_balance' => $row->tokeninwallet_balance ?? 0,
          'tokenincasino_balance' => $row->tokenincasino_balance ?? 0,
          'transaction_detail' => null,
        ];
      }

      if (!isset($row->transaction_category) || !in_array($row->transaction_category, array_keys($this->systemTranscationCategories))) {
        continue;
      }

      $currencyType = str_replace('passbook', '', $row->src);

      if (!isset($result[$row->account]['transaction_detail'][$row->transaction_category][$currencyType])) {
        $result[$row->account]['transaction_detail'][$row->transaction_category][$currencyType] = ['realcash' => null, 'not_realcash' => null];
      }

      if ($row->realcash == 0) {
        $result[$row->account]['transaction_detail'][$row->transaction_category][$currencyType]['not_realcash'] += $row->balance_sum;
      } elseif ($row->realcash == 1) {
        $result[$row->account]['transaction_detail'][$row->transaction_category][$currencyType]['realcash'] += $row->balance_sum;
      }

      // 計算該 user 的存款次數
      if (!isset($result[$row->account]['transaction_time'])) {
        $result[$row->account]['transaction_time'] = (object) array_combine(
          array_keys($this->systemTranscationCategories),
          array_pad([], count($this->systemTranscationCategories), 0)
        );
      }
      $result[$row->account]['transaction_time']->{$row->transaction_category} += 1;
    }

    array_walk($result, function (&$row) {
      $row = (object) $row;
      if ($row->transaction_detail) {
        $row->transaction_detail = new TransactionDetail(json_encode($row->transaction_detail), $this->systemTranscationCategories);
      }
    });

    $this->transactionStatisticData = $result;
  }

  // 欄位名稱
  public function setCsvColumn($csvDefinition = null) {
    global $config;
    $this->csvDefinition = $csvDefinition ?? array(
      'account' => ['title' => '会员帐号', 'mapping' => 'account'],
      'therole' => [
        'title' => '会员等级',
        'mapping' => '',
        'render' => function ($value) {
          global $tr;
          if ($value == 'A') {
            $string = $tr['A'];
          } elseif ($value == 'M') {
            $string = $tr['M'];
          }
          return $string;
        },
      ],

      'company_deposits_count' => ['title' => '公司存款次数'],
      'company_deposits_amount' => ['title' => '公司存款额'],
      // payonlinedeposit 是舊的線上支付，已經停用
      // 'payonlinedeposit_count' => ['title' => '电子支付次數'],
      // 'payonlinedeposit_amount' => ['title' => '电子支付入款'],
      'api_deposits_count' => [
        'title' => '线上支付次數',
        'sum_of' => ['apicashdeposit', 'apitokendeposit'],
      ],
      'api_deposits_amount' => [
        'title' => '线上支付存款',
        'sum_of' => ['apicashdeposit', 'apitokendeposit'],
      ],
      // 'cashtransfer_count' => [ 'title' => '现金转帐次数' ],
      // 'cashtransfer_amount' => [ 'title' => '现金转帐额' ],
      // 'cashadministrationfees_count' => [ 'title' => '现金取款行政费次数' ],
      'cashadministrationfees_amount' => ['title' => '现金取款行政费'],
      'cashwithdrawal_count' => ['title' => '现金取款次数'],
      'cashwithdrawal_amount' => ['title' => '现金取款额'],
      'apicashwithdrawal_count' => ['title' => 'api现金取款次数'],
      'apicashwithdrawal_amount' => ['title' => 'api现金取款额'],
      // 'cashgtoken_count' => [ 'title' => '现金转游戏币次数' ],
      // 'cashgtoken_amount' => [ 'title' => '现金转游戏币额' ],
      'tokendeposit_count' => ['title' => '游戏币存款次数'],
      'tokendeposit_amount' => ['title' => '游戏币存款额'],
      // 'tokenfavorable_count' => [ 'title' => '游戏币优惠次数' ],
      'tokenfavorable_amount' => ['title' => '游戏币优惠额'],
      // 'tokenpreferential_count' => [ 'title' => '游戏币反水次数' ],
      'tokenpreferential_amount' => ['title' => '游戏币反水额'],
      // 'tokenpay_count' => [ 'title' => '游戏币派彩次数' ],
      'tokenpay_amount' => ['title' => '游戏币派彩额'],
      'tokengcash_amount' => ['title' => '游戏币取款额（转现金）'],
      'tokenadministrationfees_count' => ['title' => '游戏币取款次数'],
      'tokenadministrationfees_amount' => ['title' => '游戏币取款手续费'],
      'deposit_summary' => ['title' => '存款总计'],
      'withdrawal_summary' => ['title' => '取款总计'],
      'diff_summary' => ['title' => '存取差额总计'],
    );

    (strcasecmp($config['website_type'], 'casino') == 0) and $this->csvDefinition = array_diff_key($this->csvDefinition, [
      // 移除 EC
      'apicashwithdrawal_count' => ['title' => 'api现金取款次数'],
      'apicashwithdrawal_amount' => ['title' => 'api现金取款额'],
    ]);

    // 選今天會出現這些
    if ($this->dailydate == $this->dailydate_start && $this->dailydate == $this->dailydate_end) {
      $this->csvDefinition = array_merge(
        $this->csvDefinition,
        array(
          'cashinwallet_balance' => ['title' => '[钱包]现金'],
          'tokeninwallet_balance' => ['title' => '[钱包]站内游戏币'],
          'tokenincasino_balance' => ['title' => '[钱包]站外游戏币'],
        )
      );
    }
  }

  public function getCsvData($isFilterNull = false) {
    $data = $isFilterNull ? array_filter($this->transactionStatisticData, function ($row) {

      return $row->transaction_detail;
    }) : $this->transactionStatisticData;

    $result = [];
    foreach ($data as $memberAccount => $transactionRow) {
      $result[$memberAccount] = array_combine(
        array_keys($this->csvDefinition),
        array_pad([], count($this->csvDefinition), null)
      );

      foreach ($this->csvDefinition as $attribute => $columnInfo) {
        if (preg_match('/^.*_amount$/', $attribute)) {
          !isset($columnInfo['sum_of']) and $result[$memberAccount][$attribute] = $transactionRow->transaction_detail->{substr($attribute, 0, -7)} ?? null;
          if (isset($columnInfo['sum_of'])) {
            $amount = null;
            foreach ($columnInfo['sum_of'] as $category) {
              $amount += $transactionRow->transaction_detail->$category ?? null;
            }
            $result[$memberAccount][$attribute] = $amount;
          }
          continue;
        }

        if (preg_match('/^.*_count$/', $attribute)) {
          !isset($columnInfo['sum_of']) and $result[$memberAccount][$attribute] = $transactionRow->transaction_time->{substr($attribute, 0, -6)} ?? null;

          if (isset($columnInfo['sum_of'])) {
            $times = null;
            foreach ($columnInfo['sum_of'] as $category) {
              $times += $transactionRow->transaction_time->$category ?? null;
            }
            $result[$memberAccount][$attribute] = $times;
          }

          continue;
        }

        if ($attribute == 'deposit_summary' && !is_null($transactionRow->transaction_detail)) {
          $result[$memberAccount][$attribute] = $transactionRow->transaction_detail->getTotalDeposit();
          continue;
        }

        if ($attribute == 'withdrawal_summary' && !is_null($transactionRow->transaction_detail)) {
          $result[$memberAccount][$attribute] = $transactionRow->transaction_detail->getTotalWithdrawal();
          continue;
        }

        if ($attribute == 'diff_summary' && !is_null($transactionRow->transaction_detail)) {
          $result[$memberAccount][$attribute] = $transactionRow->transaction_detail->getTotalBalance();
          continue;
        }

        if (property_exists($transactionRow, $attribute)) {
          if (isset($columnInfo['render'])) {
            $result[$memberAccount][$attribute] = $columnInfo['render']($transactionRow->$attribute);
          } else {
            $result[$memberAccount][$attribute] = $transactionRow->$attribute;
          }
        }
      }
    }

    // 輸出excel的全部資料(陣列)
    // var_dump($result);die();

    return $result;
  }

  public function __get($property) {
    return property_exists($this, $property) ? $this->$property : null;
  }
}

class ArrayPaginator {
  protected $items;
  protected $totalItems;
  protected $perPage = 15;
  protected $firstPage = 1;
  protected $currentPage;
  protected $lastPage;
  protected $filters = [];
  protected $filteredItems;
  protected $offest = 0;

  function __construct(array $data, int $per_page = 15, int $current_page = 1) {
    $this->totalItems = $data;
    $this->perPage = $per_page;
    $this->goToPage($current_page);
  }

  public function count(?string $type = null) {
    $property = $type ? "{$type}Items" : "items";
    return count($this->$property);
  }

  public function currentPage() {
    return $this->currentPage;
  }

  public function firstItem() {
    return array_key_first($this->filteredItems);
  }

  public function items() {
    return $this->items;
  }

  public function lastItem() {
    return array_key_last($this->filteredItems);
  }

  public function lastPage() {
    return $this->lastPage;
  }

  public function perPage() {
    return $this->perPage;
  }

  public function total() {
    return $this->filteredItems;
  }

  public function filter(callable $callback, $flag = 0) {
    $this->filters[] = compact('callback', 'flag');
    return $this;
  }

  public function goToPage(int $current_page) {
    $this->currentPage = $current_page;
    $this->lastPage = intval($this->firstPage + floor(count($this->totalItems) / $this->perPage));
    $this->offest = $this->currentPage * $this->perPage;

    $filteredItems = $this->totalItems;
    foreach ($this->filters as $filter) {
      $filteredItems = array_filter($filteredItems, $filter['callback'], $filter['flag']);
    }
    $this->filteredItems = $filteredItems;

    $this->items = array_slice($this->filteredItems, $this->offest, $this->perPage);
    return $this;
  }

  public function offset(int $offset) {
    $current_page = ceil($offset / $this->perPage);
    $this->goToPage($current_page);
    return $this;
  }
}
