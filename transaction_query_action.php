<?php
// ----------------------------------------------------------------------------
// Features: 交易紀錄查詢
// File Name:	transcation_query_action.php
// Author: Neil
// Related:   
// Log:
// 2020.08.06 Bug #4409 VIP站後台，交易紀錄查詢 > 詳細 > 遊戲幣派彩、現金轉遊戲幣 > 無交易單號 Letter
// 1. 派彩時，交易紀錄明細隱藏交易單號欄位
// 2. 現金轉遊戲幣時，交易紀錄明細隱藏交易單號及派彩欄位
// ----------------------------------------------------------------------------
session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib_common.php";

require_once dirname(__FILE__) . "/lib_transaction_query.php";
// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------
// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

global $page_config;

if(!isset($_SESSION['agent']) || $_SESSION['agent']->therole != 'R') {
  echo json_encode(['status' => 'permissionError', 'result' => '不合法的帳號權限']);
  die();
}

if (isset($_GET['_'])) {
  $secho = $_GET['_'];
} else {
  $secho = '1';
}

$passbookConfig = getPassbookConfig();

$postData = json_decode($_POST['data']);
$validateResult = validateData($postData);

$requirement = combineSelectRequirement($validateResult);

$sql = [];
$grade = '(\''.implode('\',\'', $validateResult['grade']).'\')';
foreach ($validateResult['passbook'] as $v) {
  // $table[$v] 拿不到回傳error
  $sql[] = combineSelecteSql($v, $passbookConfig[$v]['table'], $passbookConfig[$v]['account'], $grade).' AND '.$requirement;
}

$datatableInitSQL = "(".implode(" ) UNION ( ", $sql).")". 'ORDER BY trans_time DESC';
// echo($datatableInitSQL);die();

$datatableInitData = runSQLall($datatableInitSQL);

if (empty($datatableInitData[0])) {
  echo json_encode(['status' => 'fail', 'result' => '查无资料!']);
  die();
}

$count = $datatableInitData[0];
unset($datatableInitData[0]);

$data = combineDataTableInitData($datatableInitData);

$datatableOutput = [
  "sEcho" => intval($secho),
  "iTotalRecords" => intval($page_config['datatables_pagelength']),
  "iTotalDisplayRecords" => intval($count),
  "data" => $data
];

$sumSQL = combineDepositWithdrawalSumSql($datatableInitSQL);
$sumData = runSQLall($sumSQL);

if (empty($sumData[0])) {
  echo json_encode(['status' => 'fail', 'result' => '查无加总资料!']);
  die();
}

$sumDataOutput = [
  'deposit_sum' => '$'.number_format($sumData[1]->totaldeposit, 2, '.', ','),
  'withdrawal_sum' => '$'.number_format($sumData[1]->totalwithdrawal, 2, '.', ','),
  'total' => '$'.number_format(($sumData[1]->totaldeposit - $sumData[1]->totalwithdrawal), 2, '.', ','),
];

$output = [
  'datatable' => $datatableOutput,
  'sum' => $sumDataOutput,
  'downloadUrl' => 'transaction_query_csv_download_action.php?csv='.jwtenc('transaction_query', $validateResult)
];

echo json_encode(['status' => 'success', 'result' => $output]);


function validateData($post)
{
  $input = [];

  $numFilter = [
    'depositLower',
    'depositUpper',
    'withdrawalLower',
    'withdrawalUpper',
    'realCash'
  ];

  foreach ($post as $k => $v) {
    if ($k == 'transactionType' || $k == 'passbook' || $k == 'grade') {
      if (!count($v)) {
        continue;
      }

      ${$k} = ($k != 'grade') ? filter_var_array($v, FILTER_SANITIZE_STRING) : filter_var_array($v, FILTER_SANITIZE_NUMBER_INT);
    } else {
      ${$k} = (!in_array($k, $numFilter)) ? filter_var($v, FILTER_SANITIZE_STRING) : filter_var($v, FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION);
    }

    if (${$k} == '' || !${$k}) {
      unset(${$k});
      continue;
    }

    if ($k == 'startDate') {
      ${$k} = ${$k}.'';
      ${$k} = gmdate('Y-m-d H:i:s.u', strtotime(${$k}.'-04') + 8 * 3600) . '+08:00';
    }

    if ($k == 'endDate') {
      ${$k} = ${$k}.'';
      ${$k} = gmdate('Y-m-d H:i:s.u', strtotime(${$k}.'-04') + 8 * 3600) . '+08:00';
    }

    $input[$k] = ${$k};
  }

  return $input;
}

function combineDataTableInitData($data)
{
  global $tr;
  global $transaction_category;

  $initDatas = [];

  foreach ($data as $k => $v) {
    $modalHtml = combineDataTableDetailModalHtml($v);
    $transactionCategoryStr = (in_array($v->transaction_category, array_keys($transaction_category))) ? $transaction_category[$v->transaction_category] : $tr['Transaction type error, please contact the system staff'];

    $cashBalance = ($v->type == 'cash') ? $v->balance : '';
    $tokenBalance = ($v->type == 'token') ? $v->balance : '';

    $initDatas [] = [
      'id' => $k,
      'trans_id' => $v->trans_id,
      'account' => '<a href="member_account.php?a='.$v->source_transferaccount_id.'" target="_BLANK" data-role=\" button\" title="连至会员详细页面">'.$v->source_transferaccount.'</a>',
      'transtime' => $v->trans_time,
      'transaction_category' => $transactionCategoryStr,
      'deposit' => '$'.$v->deposit,
      'withdrawal' => '$'.$v->withdrawal,
      // 'balance' => $v->balance,
      'token_balance' => $tokenBalance,
      'cash_balance' => $cashBalance,
      'payout' => $v->payout,
      'detail_trans' => $modalHtml
    ];
  }

  return $initDatas;
}

function combineDataTableDetailModalHtml($data)
{
  global $tr;
  // 交易類別
  global $transaction_category;
  global $passbookConfig;

  $realcashIsShow = '';

  $realcashStrList = [
    0 => $tr['n'],
    1 => $tr['y'],
    2 => $tr['n']
  ];

  $howLongAgoStr = convertToFuzzyTime($data->trans_time);
  if ($data->transaction_category != 'tokenpay') {

    $realcashStr = (!array_key_exists($data->realcash, $realcashStrList)) ? $tr['The real withdrawal error'] : $realcashStrList[$data->realcash];

    $realcashIsShow = <<<HTML
    <tr>
      <th scope="row">{$tr['Actual deposit']}</th>
      <td>{$realcashStr}</td>
    </tr>
HTML;
  }

  $transactionCategoryStr = (in_array($data->transaction_category, array_keys($transaction_category))) ? $transaction_category[$data->transaction_category] : $tr['Transaction type error, please contact the system staff'];

  $html = <<<HTML
  <button type="button" class="btn btn-info btn-xs pull-right modal-btn" data-toggle="modal" data-target="#{$data->type}{$data->trans_id}">{$tr['detail']}</button>
  
  <div class="modal fade detailModal" id="{$data->type}{$data->trans_id}" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
        <h2 class="modal-title" id="myModalLabel">{$tr['transaction details']}</h2>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>                            
        </div>

        <div class="modal-body modal-body-align">
          <table class="table table-striped">
            <tbody>
              <tr>
                <th scope="row">{$tr['Transaction number']}</th>
                <td>{$data->trans_id}</td>
              </tr>
              <!--
              <tr>
                <th scope="row">{$tr['Transaction order number']}</th>
                <td>{$data->transaction_id}</td>
              </tr>
              -->
              <tr>
                <th scope="row">{$tr['account']}</th>
                <td>{$data->source_transferaccount}</td>
              </tr>
              <tr>
                <th scope="row">{$tr['Trading Hours']}</th>
                <td>{$data->trans_time} - {$howLongAgoStr} (EDT)</td>
              </tr>
              <tr>
                <th scope="row">{$tr['deposit amount']}</th>
                <td> $ {$data->deposit}</td>
              </tr>
              <tr>
                <th scope="row">{$tr['withdrawal amount']}</th>
                <td> $ {$data->withdrawal}</td>
              </tr>
HTML;
	if ($data->transaction_category == 'tokenpay') {
		$html .= <<<HTML
              <tr>
                <th scope="row">{$tr['Payout']}</th>
                <td> $ {$data->payout}</td>
              </tr>
HTML;
}
	$html .= <<<HTML
              <tr>
                <th scope="row"> {$tr['current balance']} </th>
                <td> $ {$data->balance}</td>
              </tr>

		      <tr>
                <th scope="row">{$tr['Transaction Category']}</th>
                <td>{$transactionCategoryStr}</td>
              </tr>
              <tr>
                <th scope="row">{$tr['Summary']}</th>
                <td>{$data->summary}</td>
              </tr>
              <tr>
                <th scope="row">{$tr['Transfer to account']}</th>
                <td>{$data->destination_transferaccount}</td>
              </tr>
              <tr>
                <th scope="row">{$tr['operator']}</th>
                <td>{$data->operator}</td>
              </tr>
                {$realcashIsShow}
              <tr>
                <th scope="row">{$tr['wallet']}</th>
                <td>{$passbookConfig[$data->type]['str']}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">{$tr['off']}</button>
        </div>
      </div>
    </div>
  </div>
HTML;

  return $html;
}
