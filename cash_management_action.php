<?php
// ----------------------------------------------------------------------------
// Features:	點數沖銷與發行動作處理
// File Name:	cash_management_action.php
// Author:		snow
// Related:		
// 對應: cash_management.php
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 自訂函式庫

// 只有站長或維運也就是 $su['superuser'] 才有權限使用此頁
if(!($_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['superuser']))) {
  //header('Location:./home.php');
  die();
}
// datatable 回傳驗證用參數，收到後不處理直接跟資料一起回傳給 datatable 做驗證
if(isset($_GET['_'])) {
  $secho = $_GET['_'];  
} else {
  $secho = '1';  
}


// 接收並過濾非法字元
if(isset($_GET['a'])) {
  $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH); 

}else{
  die('(x)不合法的測試');
}

$errormsg='';               //錯誤訊息
$succrmsg='交易成功';        //成功訊息
$memberwallets_sql='';      

if($action=='issue'){
  //將post過來的資料接收下來
  $data=[
    $account = filter_var($_POST['account'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH),
    $type = filter_var($_POST['type'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH),
    $amount = filter_var($_POST['amount'], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION),
    $account_balance = filter_var($_POST['balance'], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION),
    $passwd = filter_var($_POST['passwd'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH)
  ];
  //var_dump($data);                                                            
  //exit();
  $a = $_SESSION['agent']->id;                                                              //操作者的id
  $sqlStr = "SELECT account FROM root_member WHERE id='".$a."'";
  $oper = runSQLall($sqlStr);
  $operator = $oper[1]->account;                                                            //撈出操作者的帳號
  $checkOperat = checkOperat($a,$passwd);                                                   //檢查是否為操作者本人  
  $balance = OperateBalance($type,$account_balance,$amount);                                //存放運算本次操作餘額
  //當異動金額介於0~10000000間、且操作者密碼正確，才能寫入資料庫。若超出範圍就會跳錯誤訊息。
  if($amount>=1 AND $amount<=10000000){  
    if($checkOperat=='1'){
      $status='1';                                                                        //status=1 成功     
      $issue_sql = InsertIssueSQL($account,$type,$amount,$balance,$operator,$status);     //寫入 root_cashissue 的 SQL
      $memberwallets_sql=UpdateBalance($type,$balance,$amount);                           //寫入 root_member_wallets 的 SQL
      $transaction_sql = 'BEGIN;'                                                         //寫入 兩者的 SQL
      .$issue_sql      
      .$memberwallets_sql
      .'COMMIT;';
      $transaction_result = runSQLtransactions($transaction_sql);                         //用runSQLtransactions來執行
      if (!$transaction_result) {
        $error_msg = '操作失败，金额 : '.$amount.'，余额：'.$account_balance;
        echo '<script>alert("'.$error_msg.'");window.location.reload();</script>';        
        die();
      }
        $succrmsg = '操作成功，金额 : '.$amount.'，余额：'.$balance;
        echo '<script>alert("'.$succrmsg.'");window.location.reload();</script>';
        return;
        
    }else{

      $errormsg = '密码输入错误!';  
      echo '<script>alert("'.$errormsg.'");window.location.reload();</script>';      
      $status = '2';                                                                     //status=2 失敗
      $balance = $account_balance;                                                       //操作失敗時，不會進行運算，餘額不變
      $issue_sql = InsertIssueSQL($account,$type,$amount,$balance,$operator,$status);    //將操作失敗的紀錄也寫進資料庫
      $rs_issue_sql = runSQLall($issue_sql);

      // return($errormsg);
    }
  }else{

    $errormsg = '请输入1~10000000的金额';  
    echo '<script>alert("'.$errormsg.'");</script>';
       
    // return($errormsg);    
  }
}else{
  $errormsg = '操作失败请重新操作!';   
  echo '<script>alert("'.$errormsg.'");window.location.reload();</script>';      
  $status = '2';                                                                         //status=2 失敗
  $balance = $account_balance;                                                           //操作失敗時，不會進行運算，餘額不變
  $issue_sql = InsertIssueSQL($account,$type,$amount,$balance,$operator,$status);        //將操作失敗的紀錄也寫進資料庫
  $rs_issue_sql = runSQLall($issue_sql);
  // return($errormsg);

  return;
}


// ----------------------------------------------------------------------------
// 檢查操作者的密碼是否與本人相符
// checkOperat=1 密碼正確
// checkOperat=2 密碼錯誤(跳錯誤訊息、reload)
// ----------------------------------------------------------------------------
function checkOperat($check_id,$check_pd)
{
  $sqlStr="SELECT passwd FROM root_member WHERE id='".$check_id."'";
  $check_row = runSQLall($sqlStr);  
  $acc_pd=$check_row[1]->passwd;                                                        //資料庫中該操作者的密碼
  $check_acc_pd=sha1($check_pd);                                                        //使用者輸入的密碼 
  if($acc_pd==$check_acc_pd){
    $checkOperat='1';
    return $checkOperat;
  }else{    
    $checkOperat='0';
    //exit();
    return $checkOperat;
  }
}

// ----------------------------------------------------------------------------
// 判斷 $type 來計算當下餘額
// type : 1=遊戲幣沖銷 2=遊戲幣發行 3=現金沖銷 4=現金發行
// ----------------------------------------------------------------------------
function OperateBalance($type,$account_balance,$amount){
  switch($type){
  case 1:    
    $balance = $account_balance-$amount; 
    break;
  case 2:    
    $balance = $account_balance+$amount;
    break;
  case 3:    
    $balance = $account_balance-$amount;
    break;
  case 4:    
    $balance = $account_balance+$amount;
    break;
  default:    
    $balance ='';
  }
  return $balance;
}

// ----------------------------------------------------------------------------
// 寫入資料庫 root_cashissue
// 將資料填入 $issue_data 中，並且組合欲寫入資料庫的SQL語法
// ----------------------------------------------------------------------------
function InsertIssueSQL($account,$type,$amount,$balance,$operator,$status){
  $issue_data=[
    'account'=>$account,
    'type'=>$type,
    'amount'=>$amount,
    'balance'=>$balance,
    'operator'=>$operator,
    'status'=>$status
  ];  
  $issue_sql=<<<SQL
  INSERT INTO root_cashissue
  (
      "changetime", "account","type","amount", "balance","operator","status"
  )VALUES(
      now(),'{$issue_data['account']}','{$issue_data['type']}','{$issue_data['amount']}','{$issue_data['balance']}','{$issue_data['operator']}','{$issue_data['status']}'
  );
SQL;
return $issue_sql;
}

// ----------------------------------------------------------------------------
// 判斷 $type 來組合寫入會員錢包的SQL
// type : 1=遊戲幣沖銷 2=遊戲幣發行 3=現金沖銷 4=現金發行
// $gcash_memberwallets_sql   為更新會員錢包中 gcashcashier 的 gcash_balance
// $gtoken_memberwallets_sql  為更新會員錢包中 gtokencashier 的 gtoken_balance
// 根據 $type 來填入 $memberwallets_sql 中
// 最後讓 $memberwallets_sql 與 $issue_sql 透過 runSQLtransactions來執行
// ----------------------------------------------------------------------------
function UpdateBalance($type,$balance,$amount){  
  // 更新 gcashcashier 餘額
  $gcash_memberwallets_sql = <<<SQL
  UPDATE root_member_wallets 
  SET changetime = now(), 
      gcash_balance = $balance 
  WHERE id = 2;
SQL;
  // 更新 gtokencashier 餘額
  $gtoken_memberwallets_sql = <<<SQL
  UPDATE root_member_wallets 
  SET changetime = now(), 
      gtoken_balance = $balance 
  WHERE id = 3;
SQL;
  switch($type){
  case 1:    
    $memberwallets_sql = $gtoken_memberwallets_sql;
    break;
  case 2:    
    $memberwallets_sql = $gtoken_memberwallets_sql;
    break;
  case 3:    
    $memberwallets_sql = $gcash_memberwallets_sql;
    break;
  case 4:    
    $memberwallets_sql = $gcash_memberwallets_sql;
    break;
  default:    
    $memberwallets_sql ='';
  }
  return $memberwallets_sql;
}



?>


