<?php
// ----------------------------------------------------------------------------
// Features: 發行紀錄Datatable初始化
// File Name:	cash_issue_record_init_action.php
// Author: snow
// Related:   
// 對應: cash_issue_record.php
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// 使用function member_gcash_transfer_sql($data)
require_once dirname(__FILE__) ."/gcash_lib.php";
// 使用function member_gtoken_transfer_sql($data)
require_once dirname(__FILE__) ."/gtoken_lib.php";

// 只有站長或維運也就是 $su['superuser'] 才有權限使用此頁
if(!($_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['superuser']))) {
  header('Location:./home.php');
  die();
}
if(isset($_GET['a'])) {
  $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
  // echo '<script>location.replace("index.php")</script>';
  die($tr['Illegal test']);
}

// datatable 回傳驗證用參數，收到後不處理直接跟資料一起回傳給 datatable 做驗證
if(isset($_GET['_'])) {
  $secho = $_GET['_'];  
} else {
  $secho = '1';  
}



if ($_GET['a'] == 'init') {
  $tableData = [];

  $CashIssueRecord = getCashIssueRecord();
  //var_dump($CashIssueRecord);

  // 將資料塞入表格中
  $count = 0;
  // 當 $CashIssueRecord 有資料時才會執行下面，若沒有if這一行會一直 loading 出現 error
  if (is_array($CashIssueRecord) || is_object($CashIssueRecord)){                                                           
    foreach ($CashIssueRecord as $v) {        
      $tableData[$count]['id'] = $v->id;                                                                                    // 沖銷/發行紀錄ID    
      $tableData[$count]['changetime'] = gmdate('Y-m-d H:i:s', strtotime($v->changetime) + -4 * 3600);                      // 沖銷/發行時間(美東時間)    
      $tableData[$count]['operator'] = $v->operator;                                                                        // 操作者
      $tableData[$count]['amount'] = '$'.number_format(round($v->amount,2),2);                                              // 金額異動
      $tableData[$count]['balance'] =  '$'.number_format(round($v->balance,2),2);                                           // 當下餘額
    
      // 判斷 account 的值來決定要輸出甚麼名字
      // gcashcashier = 现金出纳    gtokencashier = 代币出纳
      $account=$v->account;
      if($account=='gcashcashier'){
        $tableData[$count]['account'] = $tr['gcash cashier']; // 现金出纳
      }else if($account=='gtokencashier'){
      $tableData[$count]['account'] = $tr['gtoken cashier']; // 代币出纳
      }else{
        $tableData[$count]['account'] = '';
      } 

      // 判斷 type 的值來決定要輸出甚麼文字
      // 1=游戏币冲销 2=游戏币发行 3=现金冲销 4=现金发行    
      $type=$v->type;
      if($type==1){  
        $tableData[$count]['type'] = $tr['gtoken reversal'];// 游戏币冲销
      }else if($type==2){
        $tableData[$count]['type'] = $tr['gtoken publication']; // 游戏币发行
      }else if($type==3){
        $tableData[$count]['type'] = $tr['cash reversal']; // 现金冲销
      }else if($type==4){  
        $tableData[$count]['type'] = $tr['cash publication']; // 现金发行
      }else{  
        $tableData[$count]['type'] = '';
      }    


      // 判斷 status 的值來決定要輸出甚麼文字
      // 沖銷/發行操作是否成功 1=成功 2=失敗
      $status=$v->status;
      if($status==1){  
        $tableData[$count]['status'] = $tr['Success.'];
      }else if($status==2){
        $tableData[$count]['status'] = $tr['fail'];
      }else{  
        $tableData[$count]['status'] = '';
      } 
      $count++;
    }
  }

  $data = [
    "sEcho" => intval($secho),
    "iTotalRecords" => intval($page_config['datatables_pagelength']),
    "iTotalDisplayRecords" => intval($count),
    "data" => $tableData
  ];


  echo json_encode($data);
}else if($action=='summary'){

  $sum_ty=getSumGroup();  
  // var_dump($sum_ty);

  $count=1;
  // $sumdata=[];
  // 先建立陣列sum1~4都先給值"$0"，若從資料庫撈回來算好的值就直接在foreach的時候覆蓋，這樣其他類至少有值$0，才可寫入datatable
  $sumdata=[
    "sum1" => "$0.00",
    "sum2" => "$0.00",
    "sum3" => "$0.00",
    "sum4" => "$0.00"
   ];

 if (is_array($sum_ty) || is_object($sum_ty)){                                                           
    foreach ($sum_ty as $v) {     
      $a= $v->type;
      $sumdata['sum'.$a] = '$'.number_format(round($v->sum,2),2);                 
      //$count++;
    }
    // $sumdata=[$sumdata];
  }
  // var_dump($sumdata);    
  $data = array(
    "sEcho" =>  intval($secho),
    "iTotalRecords" => intval($page_config['datatables_pagelength']),
    "iTotalDisplayRecords" => intval($count),
    "data" => [$sumdata],
  );
  echo json_encode($data);
}else{
  $data = array(
    "sEcho" => 0,
    "iTotalRecords" => 0,
    "iTotalDisplayRecords" => 0,
    "data" => '',
  );
  echo json_encode($data);
}



// ----------------------------------------------------------------------------
// 從資料庫中讀取資料
// ----------------------------------------------------------------------------
function getCashIssueRecord()
{
  $sql = <<<SQL
  SELECT 
  "id","account","operator","type","amount","balance","status","changetime"
  FROM root_cashissue  
  ORDER BY changetime
  DESC limit 10
SQL;
  $result = runSQLall($sql);
  //var_dump($result);
  if (empty($result[0])) {
    return false;
  }
  unset($result[0]);
  return $result;
}

 
// ----------------------------------------------------------------------------
// 利用 type 來區分並計算 amount 總和
// ----------------------------------------------------------------------------
function getSumGroup()
{
  $sum_sql = <<<SQL
  SELECT SUM(amount), type
  FROM root_cashissue
  where status = '1'
  GROUP BY type
SQL;
  $sum_ty=runSQLall($sum_sql);
  
  if (empty($sum_ty[0])){
    return false;
  }
  unset($sum_ty[0]);
  return $sum_ty;
}

?>