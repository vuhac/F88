<?php
// ----------------------------------------------------------------------------
// Features:	管理端的會員查詢
// File Name:	agent_member_action.php
// Author:		Barkley
// Related:   對應 member.php  member_create.php
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    die('(x)不合法的測試');
}

// var_dump($_SESSION);
//var_dump($_POST);
//var_dump($_GET);

// ----------------------------------
// 動作為會員 action
// ----------------------------------
if($action == 'member_inquiry' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
// ----------------------------------------------------------------------------
// member.php 查詢會員資訊列表
// ----------------------------------------------------------------------------
	// var_dump($_POST);
	$return_string = '';

	// 有資料才查詢，沒資料就傳回空。
	if(isset($_POST['account'])) {

		$account = filter_var($_POST['account'], FILTER_SANITIZE_STRING);


    // $tr['agent'] = '加盟聯營股東';$tr['agent'] = '代理商';
    // 身份用途是來展示
		$theroleicon['R'] = '<a href="#" title="管理員"><span class="glyphicon glyphicon-king" aria-hidden="true">管理員</a>';
		$theroleicon['A'] = '<a href="#" title="'.$tr['agent'].'"><span class="glyphicon glyphicon-knight" aria-hidden="true">'.$tr['agent'].'</a>';
		$theroleicon['M'] = '<a href="#" title="會員"><span class="glyphicon glyphicon-user" aria-hidden="true">會員</a>';
		$theroleicon['T'] = '<a href="#" title="試用帳號"><span class="glyphicon glyphicon-sunglasses" aria-hidden="true">試用帳號</a>';

    // 狀態中文描述
    $status_desc['0'] = '<span class="label label-danger">停用</span>';
    $status_desc['1'] = '<span class="label label-success">有效</span>';
    $status_desc['2'] = '<span class="label label-warning">錢包凍結</span>';

    // member grade 會員等級的名稱
    // -------------------------------------
    $grade_sql = "SELECT * FROM root_member_grade WHERE status = 1;";
    $graderesult = runSQLALL($grade_sql);
    // var_dump($graderesult);
    if($graderesult[0] >= 1) {
      for($i=1;$i<=$graderesult[0];$i++){
        $gradelist[$graderesult[$i]->id] = $graderesult[$i];
      }
      // var_dump($gradelist);
    }else{
      $gradelist = NULL;
    }
    // -------------------------------------


    // 使用者所在的時區，sql 依據所在時區顯示 time
    // -------------------------------------
    if(isset($_SESSION['agent']->timezone) AND $_SESSION['agent']->timezone != NULL) {
      $tz = $_SESSION['agent']->timezone;
    }else{
      $tz = '+08';
    }
		// 轉換時區所要用的 sql timezone 參數
    $tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."'";
    $tzone = runSQLALL($tzsql);
    // var_dump($tzone);
    if($tzone[0]==1){
      $tzonename = $tzone[1]->name;
    }else{
      $tzonename = 'posix/Etc/GMT-8';
    }
		// to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD') as enrollmentdate_tz


		// 沒有帳號, 使用者按下 Enter 預設查詢本人的下線
		if($account == '') {
			// 查詢本人底下的會員
			$account_id = $_SESSION['agent']->id;
			//$sql = "SELECT * FROM root_member WHERE  parent_id = '".$account_id."';";
			// $sql = "SELECT id, account, realname, therole, status, enrollmentdate, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD') as enrollmentdate_tz, grade FROM root_member WHERE  parent_id = '".$account_id."';";
			$sql = "SELECT *,enrollmentdate, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD') as enrollmentdate_tz FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE   parent_id = '".$account_id."';";
		}else{
			// 查詢會員及代理商身份，且條件符合的
			// $sql = "SELECT * FROM root_member WHERE  account = '".$account."';";
      // $sql = "SELECT id, account, realname, therole, status, enrollmentdate, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD') as enrollmentdate_tz, grade FROM root_member WHERE  account = '".$account."';";
			$sql = "SELECT *,enrollmentdate, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD') as enrollmentdate_tz FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE   account = '".$account."';";
		}
    // -------------------------------------

    // var_dump($sql);
		$r = runSQLALL($sql);
		// var_dump($r);

		$data_table_row = '';

		// 有資料才顯示
		if($r[0] >= 1){
      // ------------------------ loop ------------------------
			for($i=1;$i<=$r[0];$i++) {

	      // 取得指定帳戶餘額 -- 上面的 sql inner join 兩個 table 所以不用這段了
    	  // $user_balance = get_gpk2_member_wallet_account_balance($r[$i]->id, $r[$i]->account);
        // $wallets = get_member_wallets($r[$i]->id);
        // var_dump($wallets);
        //if($wallets['error'] != 0) {
          // 錯誤發生停止程式
        //  die($wallets['errormessages']);
        //}

        // 判斷會員等級參數值，是否存在
        if($r[$i]->grade >=0 AND $r[$i]->grade <= 1000) {
          $grade_desc = $gradelist[$r[$i]->grade]->gradename;
        }else{
          $grade_desc = '未設定';
        }

        // gcash 為現金, gtoken 為代幣
      	// casino 的錢包, 使用免轉錢包時候 gtoken_lock 會 設定為所在的 casino ，只有取回錢包時才會清除 lock to null。
      	if($r[$i]->gtoken_lock == null) {
      		// $gtoken_lock_html = '代幣未使用';
          $gtoken_lock_html = '';
      	}else{
      		$gtoken_lock_html = '<button type="button" class="btn btn-primary btn-sm">'.$r[$i]->gtoken_lock.'</button>';
      	}

        $alert_balance = 1000;
        // gcash 大於 1 元時, change color
        if($r[$i]->gcash_balance >= $alert_balance ) {
          $gcash_balance_html = '<button type="button" class="btn btn-warning btn-sm"><span class="glyphicon glyphicon-yen"></span>'.$r[$i]->gcash_balance.'</button>';
        }elseif($r[$i]->gcash_balance >= 1 ) {
          $gcash_balance_html = '<button type="button" class="btn btn-info btn-sm"><span class="glyphicon glyphicon-yen"></span>'.$r[$i]->gcash_balance.'</button>';
        }else{
          $gcash_balance_html = '<button type="button" class="btn btn-light btn-sm"><span class="glyphicon glyphicon-yen"></span>'.$r[$i]->gcash_balance.'</button>';
        }

        // gtoken 大於 1 元時, change color
        if($r[$i]->gtoken_balance >= $alert_balance ) {
          $gtoken_balance_html = '<button type="button" class="btn btn-warning btn-sm"><span class="glyphicon glyphicon-bitcoin"></span>'.$r[$i]->gtoken_balance.'</button>';
        }elseif($r[$i]->gtoken_balance >= 1 ) {
          $gtoken_balance_html = '<button type="button" class="btn btn-info btn-sm"><span class="glyphicon glyphicon-bitcoin"></span>'.$r[$i]->gtoken_balance.'</button>';
        }else{
          $gtoken_balance_html = '<button type="button" class="btn btn-light btn-sm"><span class="glyphicon glyphicon-bitcoin"></span>'.$r[$i]->gtoken_balance.'</button>';
        }


				$data_table_row = $data_table_row.'
				<tr>
					<td>'.$r[$i]->id.'</td>
					<td><a href="member_account.php?a='.$r[$i]->id.'">'.$r[$i]->account.'</a></td>
					<td>'.$r[$i]->realname.'</td>
					<td><a href="#" title="RAW&nbsp;'.$r[$i]->enrollmentdate.'">'.$r[$i]->enrollmentdate_tz.'</a></td>
          <td>'.$theroleicon[$r[$i]->therole].'</td>
          <td>'.$status_desc[$r[$i]->status].'</td>
					<td>'.$grade_desc.'</td>
					<td>'.$gcash_balance_html.'</td>
          <td>'.$gtoken_balance_html.'</td>
          <td>'.$gtoken_lock_html.'</td>
				</tr>
				';

			}
      // ------------------------ loop ------------------------

			// table
			$return_string = '
			<table class="table table-striped">
			<tr class="info">
				<td>'.$tr['ID'].'</td>
				<td>'.$tr['Account'].'</td>
				<td>'.$tr['realname'].'</td>
				<td>'.$tr['Enrollment date'].'('.$tz.')</td>
        <td>'.$tr['The Role'].'</td>
        <td>'.$tr['State'].'</td>
				<td>'.$tr['Member Level'].'</td>
				<td>'.$tr['Cash Balance'].'</td>
				<td>'.$tr['Token Balance'].'</td>
        <td>'.$tr['Token Lock'].'</td>
			</tr>
			'.$data_table_row.'
			</table>
			';

		// end if
		}else{
			$return_string = '查询条件下无任何资料';
		}


	}else{
		$return_string = 'NO POST DATA';

	}

	// 輸出
	echo $return_string;




}elseif($action == 'member_create' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
// ----------------------------------------------------------------------------
// Member_Create.php 建立會員帳號
// ----------------------------------------------------------------------------
	// var_dump($_POST);

	// ------------------
	// 檢查會員欄位資料是否正確
	// use: memberaccount_create_check(account_create_input)
	// ------------------
	function memberaccount_create_check($account_create_input) {

		// 有資料才作業
		if($account_create_input != NULL ) {

			$account_create_input = filter_var($account_create_input, FILTER_SANITIZE_STRING);

			// 限制帳號只能為 a-z A-Z 0-9 _ 等文字符號
			$re = "/^[a-zA-Z][a-z-A-Z_0-9]{2,19}/s";
			preg_match($re, $account_create_input, $matches);
			if($matches == NULL) {
				$account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>帳號不合法，帳號只能為 a-z A-Z 0-9 _ 等文字符號組成。且第一個字母需為英文字母。長度 3 個字元以上。</div>';
					$account_check_return['code'] = 3;
			}else{
				// 可以使用的帳號, check 是否有重複
				$sql = "SELECT * FROM root_member WHERE account = '".$account_create_input."';";
				$r = runSQLALL($sql);
				// 如果有帳號存在, 就是此帳號不合法
				if($r[0] >= 1) {
					$account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>帳號不可使用。</div>';
					$account_check_return['code'] = 2;
					// var_dump($r);
				}else{
					$account_check_return['text'] = '<div class="alert alert-success" role="alert"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span>帳號可使用。</div>';
					$account_check_return['code'] = 1;
					$account_check_return['account'] = $account_create_input;
				}
			}

		}else{
			$account_check_return['text'] = '';
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

		// 有資料才作業
		if($account_create_input != NULL ) {

			$account_create_input = filter_var($account_create_input, FILTER_SANITIZE_STRING);
			// var_dump($_POST);
			// 限制帳號只能為 a-z A-Z 0-9 _ 等文字符號
			$re = "/^[a-zA-Z][a-z-A-Z_0-9]{2,19}/s";
			preg_match($re, $account_create_input, $matches);
			if($matches == NULL) {
				$account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>帳號不合法，帳號只能為 a-z A-Z 0-9 _ 等文字符號組成。且第一個字母需為英文字母。長度 2 個字元以上。</div>';
					$account_check_return['code'] = 3;
			}else{
				// 可以使用的帳號, check 是否有重複. 只有 代理商身份才可以加入 <
				$sql = "SELECT * FROM root_member WHERE (therole = 'A' OR therole = 'R') AND account = '".$account_create_input."';";
				//var_dump($sql);
				$r = runSQLALL($sql);
	      		// var_dump($r);
				// 如果有帳號存在, 才可以新增帳號
				if($r[0] == 1) {
					$account_check_return['text'] = '<div class="alert alert-success" role="alert"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span>此代理商存在,查詢代理商 <a href="member_account.php?a='.$r[1]->id.'" target="_NEW">'.$r[1]->account.'</a></div>';
					$account_check_return['code'] 		= 1;
					$account_check_return['account'] 	= $account_create_input;
					$account_check_return['id'] 		= $r[1]->id;
				}else{
					$account_check_return['text'] = '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>此代理商不存在。</div>';
					$account_check_return['code'] = 2;
				}
			}


		}else{
			$account_check_return['text'] = '';
			$account_check_return['code'] = 0;
		}

		return($account_check_return);
	}
	// ------------------

		// ------------------
		// 檢查 會員帳號欄位 , 必要欄位式否有填，及是否按下建立帳號的按鈕。
		// ------------------
	if(isset($_POST['submit_to_member_create']) AND $_POST['submit_to_member_create'] == 'admincreateaccount' AND (isset($_POST['memberaccount_create_input']) AND $_POST['memberaccount_create_input'] != NULL) AND (isset($_POST['agentaccount_create_input']) AND $_POST['agentaccount_create_input'] != NULL) ) {
		// ------------------
		// echo '建立帳號';
		// ------------------
		$check_memberaccount = memberaccount_create_check($_POST['memberaccount_create_input']);
		$check_agentaccount = agentaccount_create_check($_POST['agentaccount_create_input']);

		// 會員帳號、代理商正確, 將資料加入資料庫內
		if($check_memberaccount['code'] == 1 and $check_agentaccount['code'] == 1) {
			echo '會員帳號、代理商正確. ';
			// 預設密碼 12345678
			$user['memberaccount'] 		= $check_memberaccount['account'];
			$user['agentaccount'] 		= $check_agentaccount['account'];
			$user['parent_id']			= $check_agentaccount['id'];
			$user['therole']			= 'M';	// 會員
			$user['default_password'] 	= sha1('12345678');
			$user['withdrawalspassword']= sha1('12345678');
			$user['realname_input'] 	= filter_var($_POST['realname_input'], FILTER_SANITIZE_STRING);
			$user['mobilenumber_input'] = filter_var($_POST['mobilenumber_input'], FILTER_SANITIZE_STRING);
			$user['sex_input'] 			= filter_var($_POST['sex_input'], FILTER_SANITIZE_NUMBER_INT);
			$user['email_input'] 		= filter_var($_POST['email_input'], FILTER_SANITIZE_EMAIL);
			$user['birthday_input'] 	= filter_var($_POST['birthday_input'], FILTER_SANITIZE_STRING);
			$user['wechat_input'] 		= filter_var($_POST['wechat_input'], FILTER_SANITIZE_STRING);
			$user['qq_input'] 			= filter_var($_POST['qq_input'], FILTER_SANITIZE_NUMBER_INT);
			$user['notes_input'] 		= filter_var($_POST['notes_input'], FILTER_SANITIZE_STRING);
			$user['timezone'] 			= 'Asia/Hong_Kong';
			$user['lang']				= 'zh-cn';
			$user['status']				= '1';

			// var_dump($user);
			$sql = 'INSERT INTO "root_member" ("account", "nickname", "realname", "passwd", "changetime", "creditcurrency", "mobilenumber", "email", "lang", "status", "therole", "parent_id", "notes", "sex", "birthday", "wechat", "qq", "bankaccount", "bankname", "bankprovince", "bankcounty", "withdrawalspassword", "timezone", "bonusrule", "lastlogin", "lastseclogin", "lastbetting", "lastsecbetting", "grade", "enrollmentdate", "messages")';
			$sql = $sql."
			VALUES ('".$user['memberaccount']."', NULL, '".$user['realname_input']."', '".$user['default_password']."', 'now()', '".$config['currency_sign']."', '".$user['mobilenumber_input']."', '".$user['email_input']."', '".$user['lang']."', '".$user['status']."', '".$user['therole']."', '".$user['parent_id']."', '".$user['notes_input']."', '".$user['sex_input']."', '".$user['birthday_input']."', '".$user['wechat_input']."', '".$user['qq_input']."', NULL, NULL, NULL, NULL, '".$user['withdrawalspassword']."', '".$user['timezone']."', NULL, NULL, NULL, NULL, NULL, NULL, 'now()', NULL);";
			// echo $sql;
			$insertresult = runSQL($sql);
			if($insertresult == 1) {
				$logger = '管理員建立,'.$user['memberaccount'].','.'帳號完成';
        		memberlog2db($_SESSION['agent']->account, 'member create', 'notice', "$logger");
        		// 提示，並 reload page
        		echo $logger.'<script>alert("'.$logger.'");window.location.reload();</script>';
			}
		}


		// var_dump($_POST);

	}elseif(isset($_POST['memberaccount_create_input']) AND $_POST['memberaccount_create_input'] != NULL) {
		// ------------------
		// 檢查 會員帳號欄位
		// ------------------

		$memberaccount_create_check_return = memberaccount_create_check($_POST['memberaccount_create_input']);
		echo $memberaccount_create_check_return['text'];

	}elseif(isset($_POST['agentaccount_create_input']) AND $_POST['agentaccount_create_input'] != NULL) {
		// ------------------
		// 檢查代理商帳號欄位
		// ------------------

		$agentaccount_create_check_return = agentaccount_create_check($_POST['agentaccount_create_input']);
		echo $agentaccount_create_check_return['text'];

	}else{
		echo '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>請填入資料</div>';
	}


// ----------------------------------------------------------------------------
}elseif($action == 'memberdeposit' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
// ----------------------------------------------------------------------------
// 人工存入功能，只有代理商能幫他的第一代下線增加額度，管理員可以幫代理商做此動作。管理員可以賦予代理商儲值額度。
// ----------------------------------------------------------------------------
// 1. 管理員R 可以轉帳給所有的 代理商A , 管理員本身也具有代理商身份。
// 2. 管理員R 可以幫忙，代理商轉錢給會員 -- todo
// 3. 檢查帳號是否為登入者的下線，如果是下線的話就可以轉帳

	// var_dump($_POST);
	$submit_to_memberdeposit_input = filter_var($_POST['submit_to_memberdeposit_input'], FILTER_SANITIZE_STRING);
  //$submit_to_memberdeposit_input = 'admin_memberdeposit';

  // 取得動作選項
	if($submit_to_memberdeposit_input == 'check_account_memberdeposit') {
    // 檢查會員帳號
		$destination_transferaccount_input = filter_var($_POST['destination_transferaccount_input'], FILTER_SANITIZE_STRING);
		// 查詢會員及代理商身份，且條件符合的
		$sql = "SELECT * FROM root_member WHERE  account = '".$destination_transferaccount_input."';";
		$r = runSQLALL($sql);

		if($r[0] == 1) {
			// 如果登入者身份，和會員的 parent_id 身份一樣的話，就可以進行轉帳的動作。(就是直屬代理與會員關係--一代)
			if($_SESSION['agent']->id == $r[1]->parent_id) {
				echo '直屬代理與會員關係成立，可以進行轉帳。';
				//var_dump($r[1]->parent_id);
				//var_dump($_SESSION['agent']->id);
			}else{
				echo '直屬代理與會員關係不成立，不可以轉帳。';
				$sql2 = "SELECT * FROM root_member WHERE  id = '".$r[1]->parent_id."';";
				$r2 = runSQLALL($sql2);
				if($r2[0] == 1) {
					echo '此帳號的代理商為'.$r2[1]->account.'。';
					// var_dump($r2);
				}
			}
		}else{
			echo '無此帳號';
		}

  }elseif($submit_to_memberdeposit_input == 'admin_memberdeposit') {
    //
	// 檢查所有欄位後，進行轉帳的動作。
    //
	// var_dump($_POST);

	// 轉帳操作人員，只能是管理員或是會員的上線使用者.
    $member_id                   = $_SESSION['agent']->id;
    // 娛樂城代號
    $casino                      = 'gpk2';
    // 來源轉帳帳號
    $source_transferaccount      = filter_var($_POST['source_transferaccount_input'], FILTER_SANITIZE_STRING);
    // 目的轉帳帳號
    $destination_transferaccount = filter_var($_POST['destination_transferaccount_input'], FILTER_SANITIZE_STRING);
    // 轉帳金額，需要依據會員等級限制每日可轉帳總額。
    $transaction_money           = filter_var($_POST['balance_input'], FILTER_SANITIZE_NUMBER_INT);
    // 摘要資訊
    $summary                     = filter_var($_POST['summary_input'], FILTER_SANITIZE_STRING);
    // 實際存提
    $realcash                    = filter_var($_POST['realcash_input'], FILTER_SANITIZE_NUMBER_INT);
    // 稽核模式，三種：免稽核、存款稽核、優惠存款稽核
    $auditmode_select            = filter_var($_POST['auditmode_select_input'], FILTER_SANITIZE_STRING);
    // 稽核金額
    $auditmode_amount            = filter_var($_POST['auditmode_input'], FILTER_SANITIZE_NUMBER_INT);
    // 來源帳號的密碼驗證，驗證後才可以存款
    $password_verify_sha1        = filter_var($_POST['password_input'], FILTER_SANITIZE_STRING);
    // 系統轉帳文字資訊
    $system_note_input           = filter_var($_POST['system_note_input'], FILTER_SANITIZE_STRING);

	$gpk2_memberdeposit_transfer_result = gpk2_memberdeposit_transfer($member_id, $source_transferaccount, $destination_transferaccount, $transaction_money , $summary, $realcash, $auditmode_select, $auditmode_amount, $password_verify_sha1, $system_note_input );
	if($gpk2_memberdeposit_transfer_result == 1) {
		// 轉帳成功
		$logger = $source_transferaccount.','.$summary.','.$destination_transferaccount.','.$transaction_money.'success.';
		memberlog2db($_SESSION['agent']->account, 'member deposit', 'notice', "$logger");
		// 提示，並 reload page
		echo $logger.'<script>alert("'.$logger.'");window.location.reload();</script>';
	}else{
		// 轉帳失敗
		$logger = $source_transferaccount.','.$summary.','.$destination_transferaccount.','.$transaction_money.'false.';
		memberlog2db($_SESSION['agent']->account, 'member deposit', 'notice', "$logger");
		// 提示，並 reload page
		echo $logger.'<script>alert("'.$logger.'");window.location.reload();</script>';
	}

	}else{
    // nothing
	}

// ----------------------------------------------------------------------------
}elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
    var_dump($_POST);

}



?>
