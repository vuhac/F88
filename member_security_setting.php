<?php
// ----------------------------------------------------------------------------
// Features:  後台-- 安全設定
// File Name: member_security_setting.php
// Author:    yaoyuan
// Related:   member_security_setting.php,  member_security_setting_view.php , member_security_setting_action.php
// DB Table:  root_member_authentication
// Log:
// ----------------------------------------------------------------------------


session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/lib_view.php";
require_once dirname(__FILE__) ."/in/PHPGangsta/GoogleAuthenticator.php";


// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// 宣告兩階段驗證物件
$ga = new PHPGangsta_GoogleAuthenticator();


if(isset($_REQUEST['i'])){
	if($_REQUEST['i']!=$_SESSION['agent']->id) {
		echo '<script>alert("非本人，则无权限设定此功能!");history.go(-1);</script>';die();
	}
	$id_query = filter_var($_REQUEST['i'], FILTER_SANITIZE_STRING);
}else{
		echo '<script>alert("无身份识别，请洽客服人员!");history.go(-1);</script>';die();
}

if (isset($_POST['behavior'])){$action = filter_var($_POST['behavior'], FILTER_SANITIZE_STRING);}
if (isset($_POST['twofa_question'])) {//2FA問題
	$twofa_question = filter_var($_POST['twofa_question'],FILTER_SANITIZE_STRING);
	// $twofa_question = filter_var(json_decode($_POST['twofa_question'],true), FILTER_SANITIZE_STRING);
}
if (isset($_POST['twofa_ans'])) {		//2fa啟用答案
	$twofa_ans = filter_var($_POST['twofa_ans'], FILTER_SANITIZE_STRING);
	// $twofa_ans = filter_var(json_decode($_POST['twofa_ans'],true), FILTER_SANITIZE_STRING);
}  
if (isset($_POST['verify_code'])) {$verify_code = filter_var($_POST['verify_code'], FILTER_SANITIZE_STRING);} //驗證碼
if (isset($_POST['secret_id'])) {$secret_id = filter_var($_POST['secret_id'], FILTER_SANITIZE_STRING);} //金鑰
if (isset($_POST['twofa_disable_ans'])) {//2fa停用答案
	$twofa_disable_ans = filter_var($_POST['twofa_disable_ans'], FILTER_SANITIZE_STRING);
	// $twofa_disable_ans = filter_var(json_decode($_POST['twofa_disable_ans'],true), FILTER_SANITIZE_STRING);
} 


//白名單變數過濾 
if (isset($_POST['whitelist_data'])) {
	// csrf驗證
	$csrftoken_ret = csrf_action_check();
	if ($csrftoken_ret['code'] != 1) {die($csrftoken_ret['messages']);}
	// 解開白名單json，並過濾陣列
	$decode_whitelist_data=json_decode($_POST['whitelist_data'],true);
	$whitelist_data_array = filter_var_array($decode_whitelist_data, FILTER_SANITIZE_STRING);
	// var_dump($decode_whitelist_data);die();
}


// 停用驗證問題
$disable_question=[
	1=>'你少年时代最好的朋友叫什么名字？',
	2=>'你的第一只宠物叫什么名字？',
	3=>'你学会做的第一道菜是什么？',
	4=>'你第一次去电影院看的是哪一部电影？',
	5=>'你第一次坐飞机是去哪里？',
	6=>'你上小学时最喜欢的老师姓什么？',
];

// 顯示各安全設定，裡按鈕顯示狀態
$show_set_button_html=[
	0=>'<button class="btn btn-secondary btn-xs" role="button">'.$tr['unsetting'].'</button>',
	1=>'<button class="btn btn-success btn-xs" role="button">'.$tr['setted'].'</button>'
];

$show_checked_ary=[0=>'',1=>'checked'];

// 如果有安全設定過，取狀態值，否則給0
$member_auth_result=sql_member_authentication($id_query);
// var_dump($member_auth_result);die();
$whitelist_content='';$whitelist_ip_ary=[];

if ($member_auth_result[0]==0){
	$two_fa_status = $whitelis_status   = '<button class="btn btn-secondary btn-xs" role="button">"'.$tr['unsetting'].'"</button>';
	$twofa_checked = $whitelist_checked = '';
	$whitelist_content =<<< HTML
	<div class="d-flex mt-1 input-group">
		<input type="text" class="form-control white_listip validate[required,custom[ipv4]]"  placeholder="ex.192.168.1.1" value="" >

		<div class="input-group-append ml-2">
			<button type="button" class="btn btn-danger delete_btn" title="{$tr['delete']}">
				<span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
			</button>
		</div>
	</div>
HTML;
}else{
	$two_fa_status   = $show_set_button_html[$member_auth_result['1']->two_fa_status];
	$twofa_checked   = $show_checked_ary[$member_auth_result['1']->two_fa_status];
	
	$whitelis_status  = $show_set_button_html[$member_auth_result['1']->whitelis_status];
	$whitelist_checked = $show_checked_ary[$member_auth_result['1']->whitelis_status];

	// 將ip位址白名單，解json及生成輸入框
	if($member_auth_result[1]->whitelis_ip!=null){
		$whitelist_ip_ary= json_decode($member_auth_result[1]->whitelis_ip,true);
	
		foreach ($whitelist_ip_ary as $key => $value) {
			$whitelist_content.=<<<HTML
			<div class="d-flex mt-1 input-group">
				<input type="text" class="form-control white_listip validate[required,custom[ipv4]]"  placeholder="ex.192.168.1.1" value="{$value['ip']}" >
				<div class="input-group-append ml-2">
					<button type="button" class="btn btn-danger delete_btn" title="{$tr['delete']}">
						<span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
					</button>
				</div>
			</div>
	
HTML;
		}

	}

}

// 產生secret' => '6AEOSPKL3ZFQIKGU' ，  'qrCodeUrl'=>https://chart.googleapis.com/cha
$twofa_generate_data=generate_secret($ga,$id_query);
// $twofa_generate_data=generate_secret($ga);

// 執行動作
if(isset($action)){
	if($action == 'refresh' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
		$twofa_generate_data=generate_secret($ga,$id_query);
		// $twofa_generate_data=generate_secret($ga);
  		echo json_encode($twofa_generate_data);
		return;
	}elseif($action == 'twofa_enable' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){

		// 判斷是否有欄位為空
		if(($twofa_question=='') OR ($twofa_ans=='') OR ($verify_code=='') OR ($secret_id=='')){
			$error['logger']='红色星号为必填栏位!';
			echo json_encode($error);
			return;
		}
		$verify_result=verify_secret($secret_id, $verify_code,$ga);
		// 判斷驗證碼，正確：寫db，reload　；錯誤：提示驗證碼錯誤
		if($verify_result['check_result']){
			if(verify_member($id_query)==true){
				$error['logger'] = '使用者帐号不存在！ (错误代码：10802010937)';
				echo json_encode($error);
				return;
			}
			

			if($member_auth_result[0]==0){
				add_authentication($id_query,json_encode($twofa_question),$secret_id,json_encode($twofa_ans));
			}else{
				edit_authentication($id_query,json_encode($twofa_question),$secret_id,json_encode($twofa_ans));
			}
			$msg['success'] = '启用成功！';
			echo json_encode($msg);
			return;
 		
		}else{
			$error['logger'] = '验证码错误，请重新输入!';
			echo json_encode($error);
			return;
 		}
	}elseif($action == 'twofa_disable' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
		// 判斷是否有欄位為空
		if($twofa_disable_ans==''){
			$error['logger']='请填入当初设定答案，忘记请洽客服人员！';
			echo json_encode($error);
			return;
		}
		// 判斷密碼跟當初寫入db是否有一樣，若有一樣，則傳回成功訊息，若不一樣，則傳送錯誤回去
		if($twofa_disable_ans==json_decode($member_auth_result[1]->two_fa_ans,true)){
			edit_auth_status_disable($id_query);
			$msg['success'] = '停用成功！';
			echo json_encode($msg);
			return;
		}else{
			$error['logger'] = '答案錯誤，忘记请洽客服人员！!';
			echo json_encode($error);
			return;
		}
	}elseif($action == 'white_list_save' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
		if(verify_member($id_query)==true){
			$error['logger'] = '使用者帐号不存在！ (错误代码：10802011126)';
			echo json_encode($error);
			return;
		}

		$whitelis_ip_ary=[];$i=$j=0;

		foreach($whitelist_data_array['white_listip'] as $key=>$value){
			$whitelis_ip_ary[$i][$value['name']]=$value['value'];
			$i++;
		}

		if($member_auth_result[0]==0){
			add_whitelist($id_query,json_encode($whitelis_ip_ary));
		}else{
			edit_whitelist($id_query, json_encode($whitelis_ip_ary));
		}

		$msg['success'] = '白名单位址设定成功！';
		echo json_encode($msg);
		return;
	
	}elseif($action == 'white_list_disabled' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
		if (verify_member($id_query) == true) {
			$error['logger'] = '使用者帐号不存在！ (错误代码：10802011126)';
			echo json_encode($error);
			return;
		}
		diable_whitelist($id_query);
		$msg['success'] = '白名单位址停用成功！';
		echo json_encode($msg);
		return;
	}
}



$function_title = $tr['security setting'];
$tmpl['html_meta_title'] 	= $function_title.'-'.$tr['host_name'];
$tmpl['paneltitle_content'] = '<span class="glyphicon glyphicon-cog" aria-hidden="true"></span>'.$function_title;


// function start--------------------------------------------------
//　修改白名單資料
function diable_whitelist($id_query){
    $sql = <<<SQL
			UPDATE root_member_authentication
			SET changetime      = now(),
				whitelis_status = '0'
			WHERE id  			= '{$id_query}';
SQL;
	// var_dump(runsqlall($sql));die();
    return runsqlall($sql);
}


function edit_whitelist($id_query, $whitelis_ip_ary_jn){
	$sql = <<<SQL
			UPDATE root_member_authentication
			SET changetime      = now(),
				whitelis_status = '1',
				whitelis_ip     = '{$whitelis_ip_ary_jn}'
			WHERE id  			= '{$id_query}';
SQL;
	return runsqlall($sql);
}

//　新增白名單資料
function add_whitelist($id_query,$whitelis_ip_ary_jn){
	$sql = <<<SQL
		INSERT INTO root_member_authentication
		(id,changetime,	whitelis_status,whitelis_ip)
		VALUES ('{$id_query}',now(),'1','{$whitelis_ip_ary_jn}');
SQL;
	// echo($sql);die();
	return runsqlall($sql);
}


// 撈出使用者認證資料
function sql_member_authentication($id){
	$sql=<<<SQL
		SELECT * FROM root_member_authentication
		WHERE id='{$id}'
SQL;
return runsqlall($sql);
}

// 產生驗證金鑰及QR Code
function generate_secret($ga,$id_query){
// function generate_secret($ga){
	$secret    = $ga->createSecret();
	$qrCodeUrl = $ga->getQRCodeGoogleUrl($id_query, $secret);
	// $qrCodeUrl = $ga->getQRCodeGoogleUrl('Blog', $secret);
	$return['secret']    = $secret;
	$return['qrCodeUrl'] = $qrCodeUrl;
	return $return;
}

// 驗證金錀
function verify_secret($secret_id, $verify_code,$ga){
	$checkresult['check_result'] = $ga->verifyCode($secret_id, $verify_code, 2); // 2 = 2*30秒 时钟容差
	return $checkresult;
}

//　新增2階段驗證資料
function add_authentication($id_query,$twofa_question,$secret_id,$twofa_ans){
	$sql = <<<SQL
		INSERT INTO root_member_authentication
		(id,changetime,	two_fa_status,two_fa_question,two_fa_secret,two_fa_ans)
		VALUES ('{$id_query}',now(),'1','{$twofa_question}','{$secret_id}','{$twofa_ans}');
SQL;
	// echo($sql);die();
	return runsqlall($sql);
}

//　修改2階段驗證資料
function edit_authentication($id_query, $twofa_question, $secret_id, $twofa_ans){
	$sql = <<<SQL
		UPDATE root_member_authentication
		SET   changetime      = now(),
		      two_fa_status   = '1',
		      two_fa_question = '{$twofa_question}',
		      two_fa_secret   = '{$secret_id}',
		      two_fa_ans      = '{$twofa_ans}'
		WHERE id              = '{$id_query}';
SQL;
	// echo($sql);die();
	return runsqlall($sql);
}

//　停用時，修正2fa狀態
function edit_auth_status_disable($id_query){
	$sql = <<<SQL
		UPDATE root_member_authentication
		SET   changetime    = now(),
		      two_fa_status = '0'
		WHERE id            = '{$id_query}';
SQL;
	return runsqlall($sql);
}

// 看使用者帳號是否存在
function verify_member($id_query){
	$sql = <<<SQL
			SELECT id,account	
			FROM "root_member"
			WHERE id              = '{$id_query}';
SQL;
	$result= runsqlall($sql);
	if($result[0]=='0'){
		// 不存在帳號
		return true;
	}else{
		// 存在帳號 
		return false;
	}

}


// function END-----------------------------------------------------
return render(
	__DIR__ . '/'. pathinfo(__FILE__, PATHINFO_FILENAME) . '_view.php',
		compact(
			'function_title',
			'two_fa_status',
			'whitelis_status',
			'twofa_generate_data',
			'disable_question',
			'twofa_checked',
			'whitelist_checked',
			'member_auth_result',
			'csrftoken',
			'whitelist_content'

		)
);
// return render(
// 	__DIR__ . '/member_security_setting_view.php',
// 		compact(
// 			'function_title',
// 			'two_fa_status',
// 			'whitelis_status',
// 			'twofa_generate_data',
// 			'disable_question',
// 			'twofa_checked',
// 			'whitelist_checked',
// 			'member_auth_result',
// 			'csrftoken',
// 			'whitelist_content'

// 		)
// );

?>