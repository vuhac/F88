<?php
// ----------------------------------------------------------------------------
// Features: 下載特定日期的會員存取款 CSV
// File Name: transcation_statistics.php
// Author: Webb Lu
// Related:
//    transcation_statistics_action.php
//    DB table:
//
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once __DIR__ . "/lib_view.php";
// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------

// --------------------------------------------------------------------------
// 取得 get 傳來的變數
// --------------------------------------------------------------------------

$transaction_categories = $transaction_category;
// $tx_statistics_cols = (new TransactionStatisticDetail)->csvDefinition;
$tx_statistics_cols = [
  'account' => ['title' => $tr['account'], 'mapping' => 'account'],
  'therole' => [
    'title' => $tr['therole'],
    'mapping' => '',
    'render' => function ($value) {
      if ($value == 'A') {
        $string = '代理商';
      } elseif ($value == 'M') {
        $string = '会员';
      }
      return $string;
    },
  ],

  'company_deposits_count' => ['title' => $tr['number of company deposits']],
  'company_deposits_amount' => ['title' => $tr['Company deposit amount']],
  'api_deposits_count' => [
    'title' => $tr['number of onlinepay'],
    'sum_of' => ['apicashdeposit', 'apitokendeposit']
  ],
  'api_deposits_amount' => [
    'title' => $tr['onlinepay amount'],
    'sum_of' => ['apicashdeposit', 'apitokendeposit']
  ],
  // 'cashtransfer_count' => [ 'title' => '现金转帐次数' ],
  // 'cashtransfer_amount' => [ 'title' => '现金转帐额' ],
  // 'cashadministrationfees_count' => [ 'title' => '现金取款行政费次数' ],
  'cashadministrationfees_amount' => ['title' => $tr['Gcash withdrawal administrative fee']],
  'cashwithdrawal_count' => ['title' => $tr['Gcash withdrawals']],
  'cashwithdrawal_amount' => ['title' => $tr['Gcash withdrawal amount']],
  'apicashwithdrawal_count' => ['title' => $tr['api Gcash withdrawal amount times']],
  'apicashwithdrawal_amount' => ['title' => $tr['api Gcash withdrawal amount']],
  // 'cashgtoken_count' => [ 'title' => '现金转游戏币次数' ],
  // 'cashgtoken_amount' => [ 'title' => '现金转游戏币额' ],
  'tokendeposit_count' => ['title' => $tr['Gtoken deposits']],
  'tokendeposit_amount' => ['title' => $tr['Gtoken deposit amount']],
  // 'tokenfavorable_count' => [ 'title' => '游戏币优惠次数' ],
  'tokenfavorable_amount' => ['title' => $tr['Gtoken discount']],
  // 'tokenpreferential_count' => [ 'title' => '游戏币反水次数' ],
  'tokenpreferential_amount' => ['title' => $tr['Gtoken amount']],
  // 'tokenpay_count' => [ 'title' => '游戏币派彩次数' ],
  'tokenpay_amount' => ['title' => $tr['Gtoken payout amount']],
  'tokengcash_amount' => ['title' => $tr['Gtoken withdrawal amount (transfer gcash)']],
  'tokenadministrationfees_count' => ['title' => $tr['Gtoken withdrawals times']],
  'tokenadministrationfees_amount' => ['title' => $tr['Cash withdrawal fee']],
  'deposit_summary' => ['title' => $tr['Total amount of deposit']],
  'withdrawal_summary' => ['title' => $tr['sum of withdrawal']],
  'diff_summary' => ['title' => $tr['Total access difference']],
];

(strcasecmp($config['website_type'], 'casino') == 0) and $tx_statistics_cols = array_diff_key($tx_statistics_cols, [
  // 移除 EC
  'apicashwithdrawal_count' => ['title' => 'api现金取款次数'],
  'apicashwithdrawal_amount' => ['title' => 'api现金取款额'],
]);

render(
  preg_replace('/^(.+)\.php$/ui', "$1.view.php", __FILE__),
  compact('transaction_categories', 'tx_statistics_cols'),
  'all'
);
?>
