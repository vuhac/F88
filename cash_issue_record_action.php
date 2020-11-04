<?php
// ----------------------------------------------------------------------------
// Features: 發行紀錄_查詢動作
// File Name:	cash_issue_record_action.php
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

// 只有站長或維運也就是 $_SESSION['superuser'] 才有權限使用此頁
if(!($_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['superuser']))) {
  header('Location:./home.php');
  die();
}
if(isset($_GET['a'])) {
  $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
  die($tr['Illegal test']);
}

if(isset($_GET['_'])) {
  $secho = $_GET['_'];
} else {
  $secho = '1';
}





if ($_GET['a'] == 'query_summary') {
  $logger='';     //錯誤訊息  
  // 將get到的資料存入 $query_str_arr 陣列中，並判斷格式是否正確
  $query_str_arr = array();
  $query_str_arr=getquerydata($query_str_arr);
  // var_dump($query_str_arr);
  
  // 當陣列中的開始日期或結束日期為空值 就顯示錯誤訊息
  if($query_str_arr['issue_validdatepicker_start'] =='' AND $query_str_arr['issue_validdatepicker_end'] ==''){     
    $logger = '查詢错误!';  
    $return_arr = array('logger' => $logger);
    echo json_encode($return_arr);
  // 當陣列中的開始日期>結束日期 就顯示錯誤訊息
  }else if($query_str_arr['issue_validdatepicker_start'] > $query_str_arr['issue_validdatepicker_end']){    
    $logger = '开始日期不可大于结束日期!';
    $return_arr = array('logger' => $logger);
    echo json_encode($return_arr);    
  }else{   
    $sql_str = sqlquery_str($query_str_arr); 
    $issue_sql = "SELECT changetime FROM root_cashissue WHERE ".$sql_str." GROUP BY changetime ORDER BY changetime;";
    $issue_query=runSQLall($issue_sql);  
    // var_dump($issue_query);

    // 檢查撈回來的資料，若沒有資料就顯示錯誤訊息
    if($issue_query[0] >= 1) {
      $final_issue_query_sql="SELECT * FROM root_cashissue WHERE ".$sql_str." ORDER BY changetime;";
      $final_issue_query=runSQLall($final_issue_query_sql);
      // var_dump($final_issue_query);

      // 檢查有 $final_issue_query[0] 是否為空值，若為空值就unset
      if (empty($final_issue_query[0]))
        return false;
      unset($final_issue_query[0]);   

      // 準備開始塞資料進陣列
      $tableData=[];
      $count=0;
      if (is_array($final_issue_query) || is_object($final_issue_query)){                                                           
        foreach ($final_issue_query as $v) {      
          $tableData[$count]['id'] = $v->id;                                                                                    // 沖銷/發行紀錄ID    
          $tableData[$count]['changetime'] = gmdate('Y-m-d H:i:s', strtotime($v->changetime) + -4 * 3600);                      // 沖銷/發行時間(美東時間)    
          $tableData[$count]['operator'] = $v->operator;                                                                        // 操作者
          $tableData[$count]['amount'] = '$'.number_format(round($v->amount,2),2);                                              // 金額異動
          $tableData[$count]['balance'] =  '$'.number_format(round($v->balance,2),2);                                           // 當下餘額
        
          // 判斷 account 的值來決定要輸出甚麼名字
          // gcashcashier = 现金出纳    gtokencashier = 代币出纳
          $account=$v->account;
          if($account=='gcashcashier'){
            $tableData[$count]['account'] = '现金出纳';
          }else if($account=='gtokencashier'){
          $tableData[$count]['account'] = '代币出纳';
          }else{
            $tableData[$count]['account'] = '';
          } 

          // 判斷 type 的值來決定要輸出甚麼文字
          // 1=游戏币冲销 2=游戏币发行 3=现金冲销 4=现金发行    
          $type=$v->type;
          if($type==1){  
            $tableData[$count]['type'] = '游戏币冲销';
          }else if($type==2){
            $tableData[$count]['type'] = '游戏币发行';
          }else if($type==3){
            $tableData[$count]['type'] = '现金冲销';
          }else if($type==4){  
            $tableData[$count]['type'] = '现金发行';
          }else{  
            $tableData[$count]['type'] = '';
          }    


          // 判斷 status 的值來決定要輸出甚麼文字
          // 沖銷/發行操作是否成功 1=成功 2=失敗
          $status=$v->status;
          if($status==1){  
            $tableData[$count]['status'] = '成功';
          }else if($status==2){
            $tableData[$count]['status'] = '失敗';
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
    }else{
      $logger = '查无结果!';          
      $return_arr = array('logger' => $logger);
      echo json_encode($return_arr);
    }
    
    
  }  
  
}else if ($_GET['a'] == 'sum_amount') {
  
  $query_str_arr = array();
  $query_str_arr=getquerydata($query_str_arr);
  // var_dump($query_str_arr);
  $sum_sql = sqlquery_str($query_str_arr); 
  $sum_ty=getSumGroup($sum_sql);  
  // var_dump($sum_ty);
   $count=1;
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

}


// ----------------------------------------------------------------------------
// 組合查詢條件(WHERE...)
// EX: changetime >= 2018-09-10 AND changetime <= 2018-09-12
// ----------------------------------------------------------------------------

function sqlquery_str($query_str_arr){
  //var_dump($query_str_arr);
  $and_chk = 0;
  $return_sqlstr = ''; 
  
  if(isset($query_str_arr['cash_account']) AND $query_str_arr['cash_account'] != ''){
    if($and_chk == 1){
      $return_sqlstr = $return_sqlstr.' AND ';
    }
    $return_sqlstr = $return_sqlstr.'account = \''.$query_str_arr['cash_account'].'\'';
    $and_chk = 1;
  }
  if(isset($query_str_arr['cash_type']) AND $query_str_arr['cash_type'] != ''){
    if($and_chk == 1){
      $return_sqlstr = $return_sqlstr.' AND ';
    }
    $return_sqlstr = $return_sqlstr.'type = \''.$query_str_arr['cash_type'].'\'';
    $and_chk = 1;
  }
  if(isset($query_str_arr['cash_status']) AND $query_str_arr['cash_status'] != ''){
    if($and_chk == 1){
      $return_sqlstr = $return_sqlstr.' AND ';
    }
    $return_sqlstr = $return_sqlstr.'status = \''.$query_str_arr['cash_status'].'\'';
    $and_chk = 1;
  }
  
  if(isset($query_str_arr['issue_validdatepicker_start']) AND $query_str_arr['issue_validdatepicker_start'] != '') {
    if($and_chk == 1){
      $return_sqlstr = $return_sqlstr.' AND ';
    }
    $return_sqlstr = $return_sqlstr.'changetime >= \''.$query_str_arr['issue_validdatepicker_start'].'\'';
    $and_chk = 1;
  }
  if(isset($query_str_arr['issue_validdatepicker_end']) AND $query_str_arr['issue_validdatepicker_end'] != '') {
    if($and_chk == 1){
      $return_sqlstr = $return_sqlstr.' AND ';
    }
    $return_sqlstr = $return_sqlstr.'changetime <= \''.$query_str_arr['issue_validdatepicker_end'].'\'';
    $and_chk = 1;
  }
  
  return $return_sqlstr;
}


// ----------------------------------------------------------------------------
// 檢查日期格式
// ----------------------------------------------------------------------------

function validateDate($date, $format = 'Y-m-d H:i:s') {
  $d = DateTime::createFromFormat($format, $date);
  return $d && $d->format($format) == $date;
}



// ----------------------------------------------------------------------------
// 將資料接收下來
// 判斷接收者是誰，並給其id 2=gcashcashier  3=gtokencashier
// ----------------------------------------------------------------------------

function getquerydata($query_str_arr){
  if(isset($_GET['cash_account']))
    $query_str_arr['cash_account'] = filter_var($_GET['cash_account'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
  else
  $query_str_arr['cash_account'] = '';
  
  if(isset($_GET['cash_type']))
    $query_str_arr['cash_type'] = filter_var($_GET['cash_type'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
  else
    $query_str_arr['cash_type'] = '';

  if(isset($_GET['cash_status']))
    $query_str_arr['cash_status'] = filter_var($_GET['cash_status'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
  else
    $query_str_arr['cash_status'] = '';

  if(isset($_GET['issue_validdatepicker_start']) AND validateDate($_GET['issue_validdatepicker_start'], 'Y-m-d')) {
    $query_str_arr['issue_validdatepicker_start'] = gmdate('Y-m-d H:i:s.u',strtotime($_GET['issue_validdatepicker_start'].'00:00:00 -04')+8*3600).'+08:00';
  }else{
    $query_str_arr['issue_validdatepicker_start'] = '';
  }
  if(isset($_GET['issue_validdatepicker_end']) AND  validateDate($_GET['issue_validdatepicker_end'], 'Y-m-d')) {
    $query_str_arr['issue_validdatepicker_end'] = gmdate('Y-m-d H:i:s.u',strtotime($_GET['issue_validdatepicker_end'].'23:59:59 -04')+8*3600).'+08:00';
  }else{
    $query_str_arr['issue_validdatepicker_end'] = '';
  }
  return $query_str_arr;
}

// ----------------------------------------------------------------------------
// 利用 type 來區分並計算 amount 總和
// ----------------------------------------------------------------------------
function getSumGroup($sql_str)
{  
  $sum_sql = <<<SQL
  SELECT SUM(amount), type
  FROM root_cashissue
  WHERE $sql_str AND status = '1'
  GROUP BY type
SQL;
  //var_dump($sum_sql);
  $sum_ty=runSQLall($sum_sql);
  
  if (empty($sum_ty[0])){
    return false;
  }
  unset($sum_ty[0]);
  return $sum_ty;
}


?>
