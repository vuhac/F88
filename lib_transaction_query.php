<?php
// ----------------------------------------------------------------------------
// Features: 交易紀錄查詢 lib
// File Name:	lib_transaction_query.php
// Author: Neil
// Related:   
// Log:
// ----------------------------------------------------------------------------

function combineDepositWithdrawalSumSql($selectSQL)
{
  $sql = <<<SQL
  SELECT SUM("deposit") AS totalDeposit, 
          SUM("withdrawal") AS totalWithdrawal 
  FROM ({$selectSQL}) as sum_total;
SQL;

  return $sql;
}

function combineSelecteSql($passbook, $tableName, $cashierAccount, $grade)
{
  $sql=<<<SQL
    SELECT to_char((psbk.transaction_time AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as trans_time,
            psbk.id as trans_id,
            psbk.member_id as memberid,
            psbk.transaction_category,
            psbk.deposit,
            psbk.withdrawal,
            psbk.balance,
            psbk.source_transferaccount,
            psbk.destination_transferaccount,
            psbk.realcash,
            psbk.transaction_id as transaction_id,
            psbk.summary as summary,
            oprt.account as operator,
            srcid.id AS source_transferaccount_id,
            CASE WHEN psbk.transaction_category = 'tokenpay' THEN psbk.deposit-psbk.withdrawal END AS payout,
            '{$passbook}' AS type
    FROM {$tableName} as psbk 
    LEFT JOIN root_member as oprt ON psbk.member_id=oprt.id
    LEFT JOIN root_member as srcid ON psbk.source_transferaccount=srcid.account
    WHERE srcid.grade IN {$grade}
    AND source_transferaccount != '{$cashierAccount}'
SQL;

  return $sql;
}

function  combineSelectRequirement($input)
{
  $whereRequirements = [];

  $col = [
    'account' => 'source_transferaccount',
    'transactionId' => 'transaction_id',
    'startDate' => 'transaction_time',
    'endDate' => 'transaction_time',
    'depositLower' => 'deposit',
    'depositUpper' => 'deposit',
    'withdrawalLower' => 'withdrawal',
    'withdrawalUpper' => 'withdrawal',
    'realCash' => 'realcash',
    'transactionType' => 'transaction_category',
  ];

  unset($input['grade']);
  unset($input['passbook']);

  foreach ($input as $k => $v) {
    if ($k == 'startDate') {
      $whereRequirements[] = $col[$k].' >= \''.$v.'\'';
    } elseif ($k == 'endDate') {
      $whereRequirements[] = $col[$k].' <= \''.$v.'\'';
    } elseif ($k == 'depositLower' || $k == 'withdrawalLower') {
      $whereRequirements[] = $col[$k].' >= \''.$v.'\'';
    } elseif ($k == 'depositUpper' || $k == 'withdrawalUpper') {
      $whereRequirements[] = $col[$k].' <= \''.$v.'\'';
    // } elseif ($k == 'grade') {
    //   $whereRequirement[] = 'transaction_category IN (\''.implode('\',\'', $v).'\')';
    } elseif ($k == 'transactionType') {
      $transactionType = [];
      $allTransactionType = getTransactionType();
      foreach ($v as $type) {
        $transactionType = array_merge($transactionType, $allTransactionType[$type]);
      }

      $whereRequirements[] = 'transaction_category IN (\''.implode('\',\'', $transactionType).'\')';
    } else {
      $whereRequirements[] = $col[$k].' = \''.$v.'\'';
    }
  }

  return implode(' AND ', $whereRequirements);
}

function getTransactionType()
{
  $transactionType = [
    'manualDeposit' => [
      'cashdeposit',
      'tokendeposit'
    ],
    'manualWithdrawal' => [
      'cashwithdrawal',          //現金提款
      'reject_cashwithdrawal',   // 现金提款退回
      'tokengcash',              //游戏币转銀行
      'reject_tokengcash',       //游戏币转銀行退回
      'tokentogcashpoint',       //遊戲幣轉現金
      'reject_tokentogcashpoint' //遊戲幣轉現金退回
    ],
    'onlineDeposit' => [
      'apicashdeposit',
      'payonlinedeposit',
      'apitokendeposit'
    ],
    'onlineWithdrawals' => [
      'apitokenwithdrawal',
      'apicashwithdrawal'
    ],
    'companyDeposits' => [
      'company_deposits',
      'reject_company_deposits'
    ],
    'agencyCommission' => [
      'agent_commission'
    ],
    'agencyTransfer' => [
      'cashtransfer'
    ],
    'walletTransfer' => [
      'cashgtoken'
    ],
    'promotions' => [
      'tokenfavorable'
    ],
    'withdrawalAdministrationFee' => [
      'cashadministrationfees',
      'tokenadministrationfees'
    ],
    'payout' => [
      'tokenpay'
    ],
    'bouns' => [
      'tokenpreferential'
    ],
    'other' => [
      'tokenrecycling'
    ]
  ];

  return $transactionType;
}

function getPassbookConfig()
{
  $config = [
    'cash' => [
      'table' => 'root_member_gcashpassbook',
      'account' => 'gcashcashier',
      'str' => '现金'
    ],
    'token' => [
      'table' => 'root_member_gtokenpassbook',
      'account' => 'gtokencashier',
      'str' => '游戏币'
    ]
  ];

  return $config;
}

function convertToFuzzyTime($times)
{
  global $tr;
  date_default_timezone_set('America/St_Thomas');
  $unix = strtotime($times);
  $now = time();
  $diff_sec = $now - $unix;

  if ($diff_sec < 60) {
    $time = $diff_sec;
    $unit = $tr['Seconds ago'];
  } elseif ($diff_sec < 3600) {
    $time = $diff_sec / 60;
    $unit = $tr['minutes ago'];
  } elseif ($diff_sec < 86400) {
    $time = $diff_sec / 3600;
    $unit = $tr['hours ago'];

  } elseif ($diff_sec < 2764800) {
    $time = $diff_sec / 86400;
    $unit = $tr['days ago'];

  } elseif ($diff_sec < 31536000) {
    $time = $diff_sec / 2592000;
    $unit = $tr['months ago'];
  } else {
    $time = $diff_sec / 31536000;
    $unit = $tr['years ago'];
  }
  return (int)$time . $unit;
}