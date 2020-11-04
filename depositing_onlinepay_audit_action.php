<?php
// ----------------------------------------------------------------------------
// Features:	線上支付看板 - depositing_onlinepay_audit.php 的處理
// File Name:	depositing_onlinepay_audit_action.php
// Author:		Barkley
// Related:
// Log:
// ----------------------------------------------------------------------------
// 對應資料表：root_deposit_onlinepay_summons 線上支付訂單傳票
// 相關的檔案：
//
//
// 2017.8.29 update

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
}else{
    // $tr['Illegal test'] = '(x)不合法的測試。';
    die( $tr['Illegal test']);
}
// var_dump($_SESSION);
//var_dump($_POST);
// var_dump($_GET);

function get_onlinepay_summon_unsettled($merchantorderid, $amount) {
  $sql = "SELECT *  FROM root_deposit_onlinepay_summons WHERE merchantorderid = :merchantorderid AND amount = :amount AND status IS NULL;";

  $result = runSQLall_prepared($sql, ['merchantorderid' => $merchantorderid, 'amount' => $amount], '', '', 'r');

  if (!$result) {
    throw new Exception(" $merchantorderid 的纪录不存在");
  } elseif (count($result) > 1) {
    throw new Exception("存在多笔相同 $merchantorderid 的纪录，请检查数据库");
  }

  return $result[0];
}


// ----------------------------------------------------------------------------------
// 這組設定預計設定為全域變數，讓所有的程式都可以參考這個會員等級。
// 取得會員等級資料 , 並且把會員等級轉換為對應的陣列
// ----------------------------------------------------------------------------------
//$start_memory = memory_get_usage();
$grade_sql = "SELECT * FROM root_member_grade WHERE status = '1';";
$member_grade_result = runSQLall($grade_sql);
if($member_grade_result[0] > 0) {
  for($i=1;$i<=$member_grade_result[0];$i++) {
    $member_grade[$member_grade_result[$i]->id] = $member_grade_result[$i];
    //$member_grade[$member_grade_result[$i]->gradename] = $member_grade[$member_grade_result[$i]->id];
  }
}else{
  $member_grade = NULL;
  // $tr['No membership grade information'] = '沒有會員等級資料，請聯絡客服人員處理。';
  $logger = $tr['No membership grade information'] ;
  die($logger);
}
//var_dump($member_grade_result);
//var_dump($member_grade);
//echo memory_get_usage() - $start_memory;
// ----------------------------------------------------------------------------------

// [處理]NULL=尚未入款0=入款失敗1=入款手動確認2=自動確認
// $tr['review_agent_status_n'] = '尚未入款';
// $tr['review_agent_status_0'] = '入款失敗';
// $tr['review_agent_status_1'] = '入款成功手動確認';
// $tr['review_agent_status_2'] = '入款成功';
// $tr['review_agent_status_3'] = '其他狀況';
$review_agent_status[NULL] = $tr['review_agent_status_n'];
$review_agent_status[0]    = $tr['review_agent_status_0'];
$review_agent_status[1]    = $tr['review_agent_status_1'];
$review_agent_status[2]    = $tr['review_agent_status_2'];
$review_agent_status[3]    = $tr['review_agent_status_3'];


// ----------------------------------
// 動作為會員登入檢查, 只有 Root 可以維護。
// ----------------------------------
if($action == 'depositing_onlinepay_audit_data' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
// 處理前台的曲球,回傳資料給 table 顯示
// 目前線上支付的狀態, 加上一些索引
// ref: http://datatables.club/ Datatables中文网


  // 起始的紀錄
  if(isset($_POST['start'])) {
    $irecord_start    = $_POST['start'];
  }else{
    $irecord_start    = 0;
  }

  // 每頁的數量
  if(isset($_POST['length'])) {
    $irecord_length    = $_POST['length'];
  }else{
    $irecord_length    = 25;
  }
  //var_dump($irecord_start);
  //var_dump($irecord_length);
  // $irecord_start = 0;
  // $irecord_length = 10;

  // 取得全部紀錄的數量
  $list_count_sql = "
  SELECT * , to_char((transfertime AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as transfertime_tz
  FROM root_deposit_onlinepay_summons
  LEFT JOIN (select id as member_id, account, grade from root_member) as tiny_member
  ON tiny_member.account = root_deposit_onlinepay_summons.account
  ORDER BY transfertime DESC;
  ";
  // 取得全部紀錄的數量
  $iTotalRecords = runSQL($list_count_sql);

  // 分段取得 線上支付看板 的資料
  $list_sql = "
  SELECT * , to_char((transfertime AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as transfertime_tz
  FROM root_deposit_onlinepay_summons
  LEFT JOIN (select id as member_id, account, grade from root_member) as tiny_member
  ON tiny_member.account = root_deposit_onlinepay_summons.account
  ORDER BY transfertime DESC OFFSET $irecord_start LIMIT $irecord_length ;
  ";


  $draw = $_POST['draw'];
  //var_dump($list_sql);
  $list = runSQLALL($list_sql);
  //var_dump($list);
  if($list[0] > 0) {
      // 對應前台送出的 draw
      $data_array['draw'] = intval($draw) + 1;
      $data_array['recordsTotal'] = $iTotalRecords;
      $data_array['recordsFiltered'] = $iTotalRecords;
      // $data_array['iTotalDisplayRecords'] = $iTotalDisplayRecords;

      // $data_array['record_start'] =  intval($irecord_start);
      // $data_array['record_length'] = intval($irecord_length);

      $data_row = array();
      $data_array['data'] = array();
      //$dt_row['debug'] = $_POST;

      for($i=1;$i<=$list[0];$i++){
        // 来实现对表格数据的自动绑定
        $dt_row['DT_RowId'] = 'row_' . ($i + $irecord_start);
        $dr_row['DT_RowClass'] = 'rowclass';
        $dt_row['DT_RowData']['pkey'] = $i + $irecord_start;
        $data_row = $dt_row;

        // 訂單號,$tr['View purchase order details'] = '觀看入款訂單詳細資料';
        $data_row['id'] = '<a class="btn btn-default btn-xs"  role="button" target="_SELF" href="depositing_onlinepay_audit_review.php?m='.$list[$i]->id.'" title="'.$tr['View purchase order details'] .'">'.$list[$i]->id.'</a>';
        // 會員帳號查驗連結,$tr['Check membership details'] = '檢查會員的詳細資料';
        $data_row['member_check'] = '<a href="member_account.php?a='.$list[$i]->member_id.'" target="_BLANK" title="'.$tr['Check membership details'].'">'.$list[$i]->account.'</a>';
        // 會員等級
        $data_row['member_level'] = '<a href="member_grade_config_detail.php?a='.$list[$i]->grade.'" class="btn btn-info btn-xs" role="button">'.$member_grade[$list[$i]->grade]->gradename.'</a>';
        // 金額 加上  ＄ 格式, 小數點 2 位 number_format
        $data_row['amount'] = '$'.number_format(round($list[$i]->amount, 2),2);
        // 手續費 加上  ＄ 格式, 小數點 2 位 number_format
        $data_row['cashfee_amount'] = '$'.number_format(round($list[$i]->cashfee_amount, 2),2);
        // 資料狀態
        // 審查的選項 -- 依據不同顏色區隔
        //$status_button_color = 'default';
        if(is_null($list[$i]->status)) {
          $status_button_color = 'warning';
        }elseif($list[$i]->status == 1) {
          $status_button_color = 'primary';
        }elseif($list[$i]->status == 2) {
          $status_button_color = 'success';
        }elseif($list[$i]->status == 0) {
          $status_button_color = 'danger';
        }elseif($list[$i]->status == 3) {
          $status_button_color = 'info';
        }else{
          die('status DATA ERROR');
        }

        $is_not_paid = is_null($list[$i]->status);
        $btn_js_class = $is_not_paid ? ' js-check-payment-status ' : '';
        //
        $btn_check_payment_status_attributes = 'data-deposit-onlinepay-summon-id="' . $list[$i]->id . '"' ;
        // $tr['Deposit Check'] = '入款檢查';
        $btn_title = $is_not_paid ? $tr['Deposit Check'] : '';

        // 資料狀態
        $data_row['status'] = '<a href="#" class="btn btn-'.$status_button_color.' btn-xs ' . $btn_js_class . '" title="' . $btn_title . '"' . $btn_check_payment_status_attributes . '>'.$review_agent_status[$list[$i]->status].'</a>';
        // 轉換誠 -5 timezone 的時間
        $data_row['transfertime_tz'] = $list[$i]->transfertime_tz;
        //支付公司商戶號 , onlinepaymentid,$tr['View merchant number settings'] = '觀看商戶號的設定';
        $data_row['deposit_method'] = '<a href="deposit_onlinepayment_config_detail.php?i='.$list[$i]->onlinepaymentid.'" title="'.$tr['View merchant number settings'].'">'.$list[$i]->onlinepay_company.'('.$list[$i]->onlinepaymentid.')</a>';
        // 處理操作人員帳號
        $data_row['processingaccount'] = $list[$i]->processingaccount;
        // 使用者裝置地理資訊,--$tr['Query user IP record'] = '查詢使用者IP紀錄';$tr['Query user FingerPrinter record'] = '查詢使用者FingerPrinter紀錄';
        $data_row['device_info'] = '<a href="member_log.php?ip='.$list[$i]->ip.'" title="'.$tr['Query user IP record'].'">'.$list[$i]->ip.'</a><br><a href="member_log.php?fp='.$list[$i]->fingerprinting.'" title="'.$tr['Query user FingerPrinter record'].'">'.$list[$i]->fingerprinting.'</a>';
        // 塞入陣列
        //$data_array['data'] = array($data_row);
        array_push($data_array['data'], $data_row);
      }

      // 輸出給前台的data table 使用
      // https://datatables.net/examples/data_sources/server_side.html ajax 格式
      // https://datatables.net/manual/server-side#Sent-parameters 格式
  }else{
      // 沒有資料
      $data_array = array(
  			"draw" => intval($draw),
  			"recordsTotal" => 0,
  			"recordsFiltered" => 0,
  			"data" => ''
      );
  }

  //var_dump($data_array);
  echo json_encode($data_array);

} elseif ($action == 'check_payment_status') {
  // var_dump($_POST);

  if($_POST['gateway'] == 'spgateway') {
    require_once __DIR__ . '/pay/spgateway/checkmerchantorderno.php';

    if (isset( $_POST['id']) && isset($_POST['amt'])) {
   	 // 商店訂單編號
   	 $MerchantOrderNo = $_POST['id'];
   	 // 訂單金額 -- 取整數
   	 //$Amt 					= '108';
   	 $Amt = $_POST['amt'];
   	 //$Amt 					= $_GET['a'];

      // array_keys($r) is ['status', 'message']
   	 $r = checkPaymentStatus($MerchantOrderNo, $Amt);

      $type = 'auto';
      echo json_encode(compact('type', 'r'));
    }
  } else {
    $type = 'manual';
    $r = ['status' => null, 'message' => '此金流商尚未提供自动冲帐<br>请手动处理'];
    // ui_html: id => html_string, 會 append 上去
    $ui_html = [
      'deposit_status' => '
        <button
          id="manual_confirm_btn"
          class="btn btn-success btn-sm pull-right"
          role="button"
          onclick="manual_confirm(this)"
        >手动同意</button>
        <button
          id="manual_cancel_btn"
          class="btn btn-alert btn-sm pull-right"
          role="button"
          onclick="manual_cancel(this)"
        >手动取消</button>
      '
    ];
    echo json_encode(compact('type', 'r', 'ui_html'));
  }

} elseif ($action == 'manual_confirm') {
  require_once __DIR__ . '/pay/common/lib_deposition.php';

  if (isset( $_POST['id']) && isset($_POST['amt'])) {
    // 商店訂單編號
    $merchant_order_no = $_POST['id'];
    // 訂單金額 -- 取整數
    $amt = $_POST['amt'];
    $type = 'manual';
    $r['status'] = 1;
    $r['message'] = '订单'.$merchant_order_no.'手动存款确认';

    try {
      $onlinepay_summon = get_onlinepay_summon_unsettled($merchant_order_no, $amt);
    } catch (Exception $e) {
      $r['status'] = null;
      $r['message'] = $e->getMessage();
      die(json_encode(compact('type', 'r')));
    }

    // 更新傳票狀態
    $sql = "UPDATE root_deposit_onlinepay_summons SET status = 1, processingaccount = :processingaccount, processingtime = now() WHERE merchantorderid = :merchantorderid AND amount = :amt AND status IS NULL";
    $runSQLres = runSQLall_prepared($sql, ['merchantorderid' => $merchant_order_no, 'amt' => $amt, 'processingaccount' => $_SESSION['agent']->account]);

    // 執行轉帳動作
    $error = payonlinedeposit(
      $onlinepay_summon->account,
      $onlinepay_summon->amount,
      $onlinepay_summon->merchantorderid,
      $onlinepay_summon->onlinepay_company
    );

		$r['message'] = '出納存款轉帳到帳號'.$onlinepay_summon->account.'成功,金額:'.$onlinepay_summon->amount.'訂單:'.$onlinepay_summon->merchantorderid.',金流:'.$onlinepay_summon->onlinepay_company;

    echo json_encode(compact('type', 'r'));


  }


} elseif ($action == 'manual_cancel') {

  // 商店訂單編號
  $merchant_order_no = $_POST['id'];
  // 訂單金額 -- 取整數
  $amt = $_POST['amt'];
  $type = 'manual';

  try {
    $onlinepay_summon = get_onlinepay_summon_unsettled($merchant_order_no, $amt);
  } catch (Exception $e) {
    $r['status'] = null;
    $r['message'] = $e->getMessage();
    die(json_encode(compact('type', 'r')));
  }

  $r['status'] = 1;
  $r['message'] = '订单'.$merchant_order_no.'手动存款取消';

	$sql = "UPDATE root_deposit_onlinepay_summons SET status = 3, processingaccount = :processingaccount, processingtime = now() WHERE merchantorderid = :merchantorderid AND amount = :amt AND status IS NULL";
    $runSQLres = runSQLall_prepared($sql, ['merchantorderid' => $merchant_order_no, 'amt' => $amt, 'processingaccount' => $_SESSION['agent']->account]);

  echo json_encode(compact('type', 'r'));


} elseif($action == 'test'){
  var_dump($_POST);

}
?>
