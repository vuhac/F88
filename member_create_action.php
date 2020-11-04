<?php
// ----------------------------------------------------------------------------
// Features:	管理端的會員查詢
// File Name:	member_create_action.php
// Author:		Barkley
// Related:   對應 member_create.php
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";


//var_dump($_SESSION);
//var_dump($_POST);
//var_dump($_GET);
// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// $tr['Illegal test'] = '(x)不合法的測試。';
// ----------------------------------------------------------------------
// 參數測試, 因為有時候是非登入者所以不判斷 session
if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    echo '<script>location.replace("index.php")</script>';
    die($tr['Illegal test']);
}
// ----------------------------------------------------------------------

// $tr['CSRF TOKEN error'] = 'CSRF TOKEN 錯誤';
// ----------------------------------------------------------------------
// 檢查所帶入的 CSRF token 是否正確 , 需要有登入才可以
if(isset($_POST['csrftoken']) AND isset($_SESSION['csrftoken_valid']) AND isset($_SESSION['agent'])) {
  $csrftoken_valid = sha1($_POST['csrftoken'].$_SESSION['agent']->salt);
  //var_dump($_POST['csrftoken']);
  //var_dump($csrftoken_valid);
  //var_dump($_SESSION['csrftoken_valid']);
  if(isset($_SESSION['csrftoken_valid']) AND isset($_POST['csrftoken']) AND  $csrftoken_valid == $_SESSION['csrftoken_valid'] ) {
    //echo 'CSRF TOKEN 正確';
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
// ----------------------------------------------------------------------

// ------------------
// 檢查會員欄位資料是否正確
// use: memberaccount_create_check(account_create_input)
// ------------------
function memberaccount_create_check($account_create_input) {

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
// ------------------

// ------------------
// 檢查代理商欄位資料是否正確
// use: agentaccount_create_check($account_create_input)
// ------------------
function agentaccount_create_check($account_create_input) {

	Global $tr;

	// 有資料才作業
	if(!is_null($account_create_input)) {
    //var_dump($account_create_input);
    $account_create_input = filter_var($account_create_input, FILTER_SANITIZE_STRING);
		// 限制帳號只能為 a-z 0-9 等文字
		$re = "/^[a-z][a-z0-9]{2,11}/s";
		$match_result = preg_match($re, $account_create_input, $matches);
    //var_dump($match_result);
    //var_dump($matches);
		if(empty($matches) OR $matches[0] != $account_create_input ) {
			// $tr['Invalid Account Info1'] = '帳號不合法，帳號只能為 a-z 0-9 等文字組成。且第一個字母需為英文字母。長度 3~12 個字元。';
      $account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>'.$tr['Invalid Account Info1'].'</div>';
			$account_check_return['code'] = 3;
		}elseif(strlen($account_create_input) >= 3 AND strlen($account_create_input) <= 12 ){
			// 可以使用的帳號合法, check 是否有重複. 只有 代理商身份才可以加入
			$sql = "SELECT * FROM root_member WHERE (therole = 'A' OR therole = 'R') AND account = '".$account_create_input."' ;";
			//var_dump($sql);
			$r = runSQLALL($sql);
      //var_dump($r);
			// 如果有帳號存在, 才可以新增帳號
      // $tr['This account is an administrator account and can not be used'] = '此帳號為管理員帳號，不可以使用。';
			if($r[0] == 1) {
        if($r[1]->status == 1 AND $r[1]->therole == 'A') {
          //此代理商 A 存在,且狀態可以使用 查詢代理商
  				$account_check_return['text'] = '<div class="alert alert-success" role="alert"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span>'.$tr['Agent Ckeck'].'<a href="member_account.php?a='.$r[1]->id.'" target="_NEW">'.$r[1]->account.'</a></div>';
  				$account_check_return['code'] 		= 1;
  				$account_check_return['account'] 	= $account_create_input;
  				$account_check_return['id'] 		= $r[1]->id;
        }elseif($r[1]->status == 1 AND $r[1]->therole == 'R') {
  				$account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>'.$tr['This account is an administrator account and can not be used'].'<a href="member_account.php?a='.$r[1]->id.'" target="_NEW">'.$r[1]->account.'</a></div>';
  				$account_check_return['code'] 		= 9;
  				$account_check_return['account'] 	= $account_create_input;
  				$account_check_return['id'] 		= $r[1]->id;
        }else{
          // $tr['account frozen']='此帳號被停用或是凍結，檢查帳號';
          $account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>'.$tr['account frozen'].' <a href="member_account.php?a='.$r[1]->id.'" target="_NEW">'.$r[1]->account.'</a></div>';
  				$account_check_return['code'] 		= 8;
  				$account_check_return['account'] 	= $account_create_input;
  				$account_check_return['id'] 		= $r[1]->id;
        }
			}else{
				//此代理商不存在。
				$account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>'.$tr['None of Agent'].'</div>';
				$account_check_return['code'] = 2;
			}
		}else{
      $account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>帐号太短或太长(Account is too short or too long)</div>';
  		$account_check_return['code'] = 0;
    }

	}else{
		$account_check_return['text'] = '...';
		$account_check_return['code'] = 0;
	}

	return($account_check_return);
}

// ----------------------------------------------------------------------
// 以上為檢查的 function
// ----------------------------------------------------------------------











// ----------------------------------
// MAIN 動作為會員 action , 加上 CSRF 判斷防止
// ----------------------------------
if($action == 'member_create' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
// ----------------------------------------------------------------------------
// Member_Create.php 建立會員帳號
// ----------------------------------------------------------------------------

  // ------------------
  // 檢查 會員帳號欄位 , 必要欄位式否有填，及是否按下建立帳號的按鈕。
  // ------------------
  if(isset($_POST['submit_to_member_create']) AND $_POST['submit_to_member_create'] == 'admincreateaccount'
  AND (isset($_POST['memberaccount_create_input']) AND $_POST['memberaccount_create_input'] != NULL)
  AND (isset($_POST['agent_account_input']) AND $_POST['agent_account_input'] != NULL) ) {

  	// ------------------
  	// echo '建立帳號';
  	// ------------------
  	$check_memberaccount = memberaccount_create_check($_POST['memberaccount_create_input']);
    //var_dump($check_memberaccount);
  	$check_agentaccount = agentaccount_create_check($_POST['agent_account_input']);
    //var_dump($check_agentaccount);

  	// 會員帳號、代理商正確, 將資料加入資料庫內
  	if($check_memberaccount['code'] == 1 and $check_agentaccount['code'] == 1) {
  		// echo '會員帳號、代理商正確. ';
  		// 預設密碼 12345678
      $default_password= $system_config['withdrawal_default_password'];

  		$user['memberaccount'] 		= $check_memberaccount['account'];
  		$user['agentaccount'] 		= $check_agentaccount['account'];
  		$user['parent_id']			= $check_agentaccount['id'];
  		$user['therole']			= 'M';	// 會員
  		$user['default_password'] 	= sha1($default_password);
  		$user['withdrawalspassword']= sha1($default_password);
  		$user['realname_input'] 	= filter_var($_POST['realname_input'], FILTER_SANITIZE_STRING);
  		$user['mobilenumber_input'] = filter_var($_POST['mobilenumber_input'], FILTER_SANITIZE_STRING);
  		$user['sex_input'] 			= filter_var($_POST['sex_input'], FILTER_SANITIZE_NUMBER_INT);
      $user['email_input'] 		= filter_var($_POST['email_input'], FILTER_SANITIZE_EMAIL);
      if($_POST['email_input']!==''&& !filter_var($_POST['email_input'], FILTER_VALIDATE_EMAIL)){
        die('<div class="alert alert-info" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>e-mail格式错误，请重新输入</div>');
      }
  		$user['birthday_input'] 	= filter_var($_POST['birthday_input'], FILTER_SANITIZE_STRING);
  		$user['wechat_input'] 		= filter_var($_POST['wechat_input'], FILTER_SANITIZE_STRING);
  		$user['qq_input'] 			= filter_var($_POST['qq_input'], FILTER_SANITIZE_STRING);
  		$user['notes_input'] 		= filter_var($_POST['notes_input'], FILTER_SANITIZE_STRING);
  		$user['timezone'] 			= '+08';
  		$user['lang']				          = 'zh-cn';
  		$user['status']				        = '1';
      $user['favorablerule']				= filter_var($_POST['favorable_select'], FILTER_SANITIZE_STRING);
      $user['grade']				        = filter_var($_POST['member_grade_select'], FILTER_SANITIZE_NUMBER_INT);
      $user['commissionrule']       = '預設佣金設定';

      $user['recommendedcode'] = sha1($user['memberaccount']);

      //var_dump($user);



      // 新增會員的 SQL , 需要斷行否則 atom 語法檢查會不一樣。
  		$sql = 'INSERT INTO "root_member" ("account", "nickname", "realname", "passwd", "changetime", "mobilenumber", "email", "lang", "status", "therole", "parent_id", "notes", "sex", "birthday", "wechat", "qq", "bankaccount", "bankname", "bankprovince", "bankcounty"
  , "withdrawalspassword", "timezone", "favorablerule", "lastlogin", "lastseclogin", "lastbetting", "lastsecbetting", "grade", "enrollmentdate", "permission", "recommendedcode", "commissionrule") ';
  		$sql = $sql." VALUES ('".$user['memberaccount']."', '".$user['memberaccount']."', '".$user['realname_input']."', '".$user['default_password']."', now(), '".$user['mobilenumber_input']."', '".$user['email_input']."'
      , '".$user['lang']."', '".$user['status']."', '".$user['therole']."', '".$user['parent_id']."', '".$user['notes_input']."'
      , '".$user['sex_input']."', '".$user['birthday_input']."', '".$user['wechat_input']."', '".$user['qq_input']."', NULL, NULL, NULL, NULL, '".$user['withdrawalspassword']."', '".$user['timezone']."'
      , '".$user['favorablerule']."', NULL, NULL, NULL, NULL, '".$user['grade']."', now(), NULL, '".$user['recommendedcode']."', '".$user['commissionrule']."'); ";

      //echo $sql;
  		$insertresult = runSQL($sql);

  		if($insertresult == 1) {
        // 建立完成後，還需要再次建立會員的 wallets 才算完成。如果有帳號沒有建立錢包的，會出現問題 sql error。
        // 所以在登入系統時候，會檢查是否已經有錢包。如果沒有錢包就馬上幫會員建立錢包。
        // 先取得剛剛建立的帳號 ID
        $user_sql = "SELECT * FROM root_member WHERE root_member.account = '".$user['memberaccount']."';";
        $user_result = runSQLall($user_sql);
        // var_dump($user_result[1]->id);die();

        if($user_result[0] == 1) {
      		// 存在，建立帳號 wallets in  root_member_wallets
          // 沒有資料，建立初始資料。
      		$member_wallets_addaccount_sql = "INSERT INTO root_member_wallets (id, changetime, gcash_balance, gtoken_balance) VALUES ('".$user_result[1]->id."', 'now()', '0', '0');";
      		$wallets_result = runSQL($member_wallets_addaccount_sql);
          if($wallets_result == 1){
            $r['code'] = '1';
            	//管理員建立, 帳號完成
      			$r['messages'] = $tr['Administrator established'].' '.$user['memberaccount'].' '.$tr['Account is completed'].' '.$tr['default password'].': ['.$default_password.']';
          }else{
            $r['code'] = '2';
            	//建立會員時的錢包出錯，請聯絡開發人員。
      			$r['messages'] = $tr['wallet error '];
          }
        }else{
          $r['code'] = '3';
          //找不到剛剛建立的使用者，請聯絡開發人員。
          $r['messages'] = $tr['can not find previous account'].$user['memberaccount'];
        }
  		}else{
        $r['code'] = '4';
        //建立帳號時，發生了失敗。請聯絡開發人員。
        $r['messages'] = $tr['create account error'].$user['memberaccount'];
      }
  	}else{
      $r['code'] = '6';
      //代理商不正確，或是帳號不正確。
      $r['messages'] = $tr['agent error'];
    }

    $logger = $r['messages'];
    memberlog2db($_SESSION['agent']->account, 'member create', 'notice', "$logger");
    if($r['code'] == 1) {
      // 提示，並 reload page
      //echo $logger;
      echo '<div class="alert alert-success" role="alert">'.$logger.'</div>';
			// echo('<script> alert("管理员无任何角色权限!"); history.go(-1); </script>');
      echo '<script>alert("'.$logger.'");location.href = "member_account.php?a='.$user_result[1]->id.'";</script>';
    }else{
      echo $logger;
    }

  }else{
    echo '<div class="alert alert-info" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>請填入必要的 * 欄位</div>';
  }



// ----------------------------------------------------------------------------
}elseif($action == 'member_check') {
  // ------------------
  // 檢查 會員帳號欄位
  // ------------------
  if(isset($_POST['memberaccount_create_input']) AND $_POST['memberaccount_create_input'] != NULL AND empty($_POST['submit_to_member_create'])) {
    $memberaccount_create_check_return = memberaccount_create_check($_POST['memberaccount_create_input']);
    //var_dump($memberaccount_create_check_return);
    echo $memberaccount_create_check_return['text'];
  }else{
    echo '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>'.$tr['Empty Insert']	.'</div>';
  }


// ----------------------------------------------------------------------------
}elseif($action == 'agent_check' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // ------------------
  // 檢查代理商帳號欄位
  // -----------------
  if(isset($_POST['agent_account_input']) AND $_POST['agent_account_input'] != NULL ) {
    $agentaccount_create_check_return = agentaccount_create_check($_POST['agent_account_input']);
    //var_dump($agentaccount_create_check_return);
    echo $agentaccount_create_check_return['text'];
  }else{
    echo '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>'.$tr['Empty Insert']	.'</div>';
  }


// ----------------------------------------------------------------------------
}elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
    //var_dump($_POST);

}



?>
