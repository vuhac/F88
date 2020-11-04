<?php
// ----------------------------------------------------------------------------
// Features:	後台--會員操作記錄
// File Name:	member_betlog_action.php
// Author:		snowiant@gmail.com
// Related:
//    member_betlog.php member_betlog_lib.php
//    DB table: root_memberlog
//    member_betlog_action：有收到 member_betlog.php 透過ajax 傳來的  _GET 時會將 _GET
//        取得的值進行驗證，並檢查是否為可查詢對象，如果是就直接丟入 $query_sql_array 中再
//        引用 member_betlog_lib.php 中的涵式 show_member_betloginfo() 並將返回
//        的資料放入 table 中給 datatable 處理，再以 ajax 丟給 member_betlog.php 來顯示。
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
require_once dirname(__FILE__) ."/lib_common.php";
require_once dirname(__FILE__) ."/actor_management_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// var_dump($_REQUEST);die();
// var_dump($_SESSION);
// var_dump($_POST);
// var_dump($_GET);
// die();


if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    // echo '<script>location.replace("index.php")</script>';
    die($tr['Illegal test']);
}
// ----------------------------------------------------------------------
// 檢查所帶入的 CSRF token 是否正確 , 需要有登入才可以

if(isset($_POST['csrftoken']) AND isset($_SESSION['csrftoken_valid']) AND isset($_SESSION['agent'])) {
  $csrftoken_valid = sha1($_POST['csrftoken'].$_SESSION['agent']->salt);
  // var_dump($_POST['csrftoken']);
  // var_dump($csrftoken_valid);
  // var_dump($_SESSION['csrftoken_valid']);
  if(isset($_SESSION['csrftoken_valid']) AND isset($_POST['csrftoken']) AND  $csrftoken_valid == $_SESSION['csrftoken_valid'] ) {
    // echo 'CSRF TOKEN 正確';
  }else{
    echo $tr['CSRF TOKEN error'];
    echo '<script>location.replace("index.php")</script>';
    die();
  }
}else{
  // $tr['Please log in first'] = '請先登入系統';
  echo $tr['Please log in first'];
  echo '<script>location.replace("index.php")</script>';
  die();
}

// 檢查會員欄位資料是否正確
// --------------------------
function memberaccount_create_check($account_create_input) {
// var_dump($account_create_input);die();
  //function 宣告要存取外部變數
  Global $tr;

  // 有資料才作業
  if(!is_null($account_create_input)) {

    $account_create_input = filter_var($account_create_input, FILTER_SANITIZE_STRING);
    // 限制帳號只能為 a-z 0-9 等文字
    $re = "/^[a-z][a-z0-9]{2,11}/s";
    $match_result = preg_match($re, $account_create_input, $matches);
    //var_dump($match_result);
    //var_dump($matches);
    // $tr['Invalid Account Info1'] = '帳號不合法，帳號只能為 a-z 0-9 等文字組成。且第一個字母需為英文字母。長度 3~12 個字元。';
    if(empty($matches) OR $matches[0] != $account_create_input ) {
      //帳號不合法，帳號只能為 a-z A-Z 0-9 等文字組成。且第一個字母需為英文字母。長度 3 個字元以上。
      $account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>'.$tr['Invalid Account Info1'].'</div>';
      //$account_check_return['text'] = '帳號不合法，帳號只能為 a-z 0-9 等文字組成。且第一個字母需為英文字母。長度 3~12 個字元。';
      $account_check_return['code'] = 3;
    }else{
      // 可以使用的帳號, check 是否有重複
      $sql = "SELECT * FROM root_member WHERE account = '".$account_create_input."';";
      $r = runSQLall($sql);
      // 如果有帳號存在, 就是此帳號不合法
      if($r[0] >= 1) {
        //帳號不可使用。
        $account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>'.$tr['Account Duplication'].'</div>';
        //$account_check_return['text'] = $tr['Account Duplication'];
        $account_check_return['code'] = 2;
        // var_dump($r);
      }else{
        //帳號可使用。
        $account_check_return['text'] = '<div class="alert alert-success" role="alert"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span>'.$tr['Account Check Ok'].'</div>';
        //$account_check_return['text'] = $tr['Account Check Ok'];
        $account_check_return['code'] = 1;
        $account_check_return['account'] = $account_create_input;
      }
    }

  }else{
    //  沒有資料
    $account_check_return['text'] = '...';
    $account_check_return['code'] = 0;
  }

  return($account_check_return);
}


// ----------------------------------------------------------------------------
//管理員新增
// ----------------------------------
if($action == 'admin_create' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['superuser'])) {
  
  // 判斷角色是否重覆選取
  $duplicate= $admin['actor']=array();
  if(isset($_POST['input_name_actor']) AND $_POST['input_name_actor']!=NULL){
    $name_actor = filter_var_array($_POST['input_name_actor'], FILTER_SANITIZE_STRING);
    // var_dump($name_actor);die();
    foreach($name_actor as $key =>$value){
        if(in_array($permission_id_map_actorid[$value['value']],$duplicate)){
          echo '<div class="alert alert-warning" role="alert">'.$permission_id_map_actorname[$value['value']].'权限重复，请取消选取!</div>';
          echo '<script>alert("'.$permission_id_map_actorname[$value['value']].'权限重复，请取消选取!")</script>';
          die();
        }else{
          array_push($duplicate,$permission_id_map_actorid[$value['value']]);
          $admin['actor'][]=$value['value'];
        }
    }
  }

  // 判斷有option的角色是否重覆選取
  if (isset($_POST['select_name_actor']) and $_POST['select_name_actor'] != null) {
      $name_actor_sel = filter_var_array($_POST['select_name_actor'], FILTER_SANITIZE_STRING);
      // var_dump($name_actor_sel);die();
      foreach ($name_actor_sel as $key => $actor_sel) {
        // 如果角色序號有值才做
        if(!empty($actor_sel['value'])){
          if (in_array($permission_id_map_actorid[$actor_sel['value']], $duplicate)) {
              echo '<div class="alert alert-warning" role="alert">' . $permission_id_map_actorname[$actor_sel['value']] . '权限重复，请取消选取!</div>';
              echo '<script>alert("' . $permission_id_map_actorname[$actor_sel['value']] . '权限重复，请取消选取!")</script>';
              die();
          } else {
              array_push($duplicate, $permission_id_map_actorid[$actor_sel['value']]);
              $admin['actor'][] = $actor_sel['value'];
          }
        }
      }
  }

  // var_dump($admin['actor']);die();

  // 檢查會員帳號及必要欄位是否有填，及按下新增按鈕
  if(isset($_POST['submit_to_admin_create']) AND $_POST['submit_to_admin_create'] == 'is_admincreateaccount'
  AND (isset($_POST['admin_account_create_input']) AND $_POST['admin_account_create_input'] != NULL)
  AND (isset($_POST['status']) AND $_POST['status'] != NULL) 
  AND (isset($_POST['input_password_valid']) AND $_POST['input_password_valid'] != NULL) 
  AND (isset($_POST['confirm_password_valid']) AND $_POST['confirm_password_valid'] != NULL)){
      $input_password_valid = filter_var($_POST['input_password_valid'], FILTER_SANITIZE_STRING);
      $confirm_password_valid = filter_var($_POST['confirm_password_valid'], FILTER_SANITIZE_STRING);
      $check_adminaccount = memberaccount_create_check($_POST['admin_account_create_input']);
      // var_dump($check_adminaccount);die();
      if($check_adminaccount['code'] == 1 and $input_password_valid == $confirm_password_valid) {
          // ECHo('sadfadsfasdf1231');die();
          $admin['admin_account_create_input'] = $check_adminaccount['account'];
          $admin['admin_name']                 = filter_var($_POST['id_admin_name'], FILTER_SANITIZE_STRING);
          $admin['password']                   = filter_var($_POST['confirm_password_valid'], FILTER_SANITIZE_STRING);
          $admin['cell_phone']                 = filter_var($_POST['cell_phone'], FILTER_SANITIZE_STRING);
          $admin['email']                      = filter_var($_POST['email'], FILTER_SANITIZE_STRING);
          $admin['status']                     = filter_var($_POST['status'], FILTER_SANITIZE_STRING);
          $admin['note']                       = filter_var($_POST['note'], FILTER_SANITIZE_STRING);
          $admin['actor_json']                 = json_encode($admin['actor']);

          //id由500~1000，為管理員使用
          $max_id_sql=<<<SQL
                SELECT coalesce(max(id),0) as maxid
                FROM "root_member" 
                where id 
                BETWEEN 500 and 1000
SQL;
          $max_id_sql_result= runSQLall($max_id_sql);
          if($max_id_sql_result[1]->maxid==0){
            $admin['id'] =500;
          }else{
            $admin['id'] =$max_id_sql_result[1]->maxid+1;
          }

          // 新增管理員SQL
          $sql=<<<SQL
          INSERT INTO "root_member" (
            "id",                     "account",    "realname", "passwd",      "mobilenumber",
            "email",                  "status",     "therole",  "parent_id",   "notes",    
            "enrollmentdate",         "registerfingerprinting", "registerip",  "permission") 
            VALUES (
            '{$admin['id']}',   '{$admin['admin_account_create_input']}','{$admin['admin_name']}','{$admin['password']}','{$admin['cell_phone']}',
            '{$admin['email']}','{$admin['status']}',                    'R',                     '1',                   '{$admin['note']}',
            'now()',            '{$_SESSION['fingertracker']}',          '{$_SESSION['fingertracker_remote_addr']}','{$admin['actor_json']}')
SQL;
          //新增管理員，並回傳1成功
          $insertresult = runSQL($sql);

          if($insertresult == 1) {
            // 建立完成後，還需要再次建立會員的 wallets 才算完成。如果有帳號沒有建立錢包的，會出現問題 sql error。
            // 所以在登入系統時候，會檢查是否已經有錢包。如果沒有錢包就馬上幫會員建立錢包。
            // 先取得剛剛建立的帳號 ID
            $user_sql = "SELECT * FROM root_member WHERE root_member.account = '".$admin['admin_account_create_input']."';";
            $user_result = runSQLall($user_sql);

            if($user_result[0] == 1) {
              // 存在，建立帳號 wallets in  root_member_wallets
              // 沒有資料，建立初始資料。
              $member_wallets_addaccount_sql = "INSERT INTO root_member_wallets (id, changetime, gcash_balance, gtoken_balance) VALUES ('".$user_result[1]->id."', 'now()', '0', '0');";
              // var_dump($member_wallets_addaccount_sql);die();
              $wallets_result = runSQL($member_wallets_addaccount_sql);
              if($wallets_result == 1){
                $r['code'] = '1';
                  //管理員建立, 帳號完成
                $r['messages'] = $tr['Administrator established'].' '.$admin['admin_account_create_input'].' '.$tr['Account is completed'];
              }else{
                $r['code'] = '2';
                  //建立會員錢包出錯，請聯絡開發人員。
                $r['messages'] = $tr['wallet error '];
              }
            }else{
              $r['code'] = '3';
              //找不到剛剛建立的使用者，請聯絡開發人員。
              $r['messages'] = $tr['can not find previous account'].$admin['admin_account_create_input'];
            }
          }else{
            $r['code'] = '4';
            //建立帳號時，發生了失敗。請聯絡開發人員。
            $r['messages'] = $tr['create account error'].$admin['admin_account_create_input'];
          }

      }else{
        $r['code'] = '6';
        $r['messages'] = '帐号错误!';
      }
      $logger = $r['messages'];
      if($r['code'] == 1) {
        $msg=$r['messages'];
        $msg_log=$r['messages'];
        $sub_service='authority';
        memberlogtodb($_SESSION['agent']->account,'admin','notice',"$msg",$admin['admin_account_create_input'],"$msg_log",'b',$sub_service);
        echo '<div class="alert alert-success" role="alert">'.$logger.'</div>';
        echo '<script>alert("'.$logger.'");location.href="admin_management.php";</script>';
        echo "<script>$('#submit_to_admin_create').attr('disabled','disabled')</script>";
      }else{
        // var_dump($admin);die();
        $msg=$r['messages'];
        $msg_log=$r['messages'].'，會員帳號：'.$_POST['admin_account_create_input'];
        $sub_service='authority';
        memberlogtodb($_SESSION['agent']->account,'admin','error',"$msg",$_POST['admin_account_create_input'],"$msg_log",'b',$sub_service);
        echo '<div class="alert alert-warning" role="alert">'.$logger.'</div>';
        echo '<script>alert("'.$logger.'")</script>';
      }
  }else{
    echo '<div class="alert alert-info" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>'.$tr['Please fill in the necessary * field'].'</div>';
    echo '<script>alert("'.$tr['Please fill in the necessary * field'].'")</script>';
  }
}elseif($action == 'admin_check') {
  // 檢查 會員帳號欄位
  if(isset($_POST['admin_account_create_input']) AND $_POST['admin_account_create_input'] != NULL AND empty($_POST['submit_to_admin_create'])) {
    $admin_account_create_check_return = memberaccount_create_check($_POST['admin_account_create_input']);
    echo $admin_account_create_check_return['text'];
  }else{
    echo '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>'.$tr['Empty Insert'] .'</div>';
  }
}elseif($action == 'test') {
  // ----------------------------------------------------------------------------
  // test developer test
  // ----------------------------------------------------------------------------
  var_dump($_POST);
}elseif($action == 'query_actor' AND $query_chk == 0){
  $output = array(
    "sEcho" => 0,
    "iTotalRecords" => 0,
    "iTotalDisplayRecords" => 0,
    "data" => ''
  );
  // end member sql
  echo json_encode($output);
}elseif(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
}
// -----------------------------------------------------------------------
// MAIN END
// -----------------------------------------------------------------------

?>