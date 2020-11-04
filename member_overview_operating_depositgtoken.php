<?php
// ----------------------------------------------------------------------------
// Features:    後台 -- 人工存款GTOKEN, 管理員可以針對會員進行代幣儲值的動作
// File Name:    member_depositgtoken.php
// Author:        Barkley
// Related:
// Permission: 只有站長或是客服才可以執行
// Log:
// 2016.11.20 v0.2
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

//var_dump($_SESSION);

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title         = $tr['member_depositgtoken'];
// 擴充 head 內的 css or js
$extend_head                = '';
// 放在結尾的 js
$extend_js                    = '';
// body 內的主要內容
$panelbody_content = '';
// ----------------------------------------------------------------------------
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="member_overview.php">' . $tr['member overview'] . '</a></li>
  <li class="active">功能操作</li>
</ol>';
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 此程式功能說明：
// 以使用者帳號為主軸 , 管理者可以操作各種動作。

// var_dump($_SESSION);

// -----------------------
// (1) 判斷帳號是否為管理員 root 帳號
// -----------------------

// 計算使用者當日gtoken存款總額(member_depositgtoken.php、member_depositgtoken_action.php)
function calculate_deposit_total( $account ){
    $current_date = gmdate('Y-m-d',time()+ -4*3600).' 12:00 +08';
    $manual_deposit_sql=<<<SQL
    SELECT coalesce(sum(withdrawal),0) as sum FROM root_member_gtokenpassbook
    WHERE operator               = '{$account}'
    AND   transaction_category   = 'tokendeposit'
    AND   source_transferaccount = 'gtokencashier'
    AND   transaction_time       >='{$current_date}'
SQL;
		$manual_deposit_sql_result = runSQLall($manual_deposit_sql);
    return round($manual_deposit_sql_result[1]->sum,0);
} // end calculate_deposit_total
// GTOKEN 提出的轉入帳戶。
// $gtoken_cashier_account = 'gtoken_cashier';

// 功能說明文字
$page_desc    = '<div class="form-group row"><div class="alert alert-success w-75" role="alert">
* 人工存入游戏币（GTOKEN）功能：此为管理员或允许的客服人员进行人工存入游戏币的工作管理员可以给与游戏币给任何帐户。<br>
* 预设是以管理员身份，使用出纳帐号'.$gtoken_cashier_account.' 转帐给指定的帐户。一般帐户之间，不能互转游戏币。<br>
* 通常可以使用在发放代币优惠，代币反水，或是入款游戏币使用，这些项目提款到现金（GCASH）时需要被稽核。
</div></div>';

// 功能及操作的說明
$deposit_body_content = $page_desc;

// --------------------------------------
// 使用特殊帳號，來執行入款及存款的功能紀錄
// --------------------------------------

// 只有管裡員，使用出納的錢。其他用自己的錢
if($_SESSION['agent']->therole == 'R') {
    $disabled_var = 'disabled';
    // 如果是管理員，預設為 出納帳號 $gcash_cashier_account
    $source_transferaccount_input_default = $gtoken_cashier_account;
}
else{
    // 否則預設為登入的代理商本身
    $disabled_var = 'disabled';
    $source_transferaccount_input_default = $_SESSION['agent']->account;
}

// 检视特定会员 id 的设定
isset($_GET['a']) OR die('NO ID ERROR!!');
$account_id = isset($_GET['a']) ? filter_var($_GET['a'], FILTER_SANITIZE_NUMBER_INT) : null;
is_numeric($account_id) OR die($logger = $tr['The user ID is error']);

// get use member data
$sql = "SELECT * FROM root_member WHERE root_member.id = :account_id;";
$r = runSQLALL_prepared($sql, $values = ['account_id' => $account_id]);
// 正常只能有一个帐号, 并取得正常的资料。
count($r) == 1 OR die($debug_msg = '资料库系统有问题，请联络开发人员处理。');
$user = $r[0];
// echo '<pre>', var_dump($user->feedbackinfo), '</pre>';  exit();

//代理占比 如果是會員則不可以設定代理占比
if ($user->therole != 'A'){
    $user_therole = '';
  }else {
    $user_therole = '
    <a class="nav-link" href="member_overview_operating_agent_setting.php?a='.$user->id.'" id="agentssetting-tab" role="tab" aria-controls="agentssetting" aria-selected="false">
			'.$tr['agent ratio setting'].'
    </a>
    ';
  }

// 查詢來源帳號的餘額
$sql = <<<SQL
    SELECT *
    FROM root_member
    JOIN root_member_wallets ON (root_member.id=root_member_wallets.id)
    WHERE (root_member.account = '{$source_transferaccount_input_default}');
SQL;
$g = runSQLALL($sql);
if( $g[0] == 1 ) {
    $source_transferaccount_html  = '目前余额&nbsp;'.money_format('%i', $g[1]->gtoken_balance);;
}
else{
    $source_transferaccount_html  = '';
}

// GCASH 額度來源帳號
$deposit_body_content .= <<<HTML
<div class="form-group row">
    <label class="col-sm-2 col-form-label"><span class="text-danger" aria-hidden="true">*</span>游戏币来源帐号</label>
    <div class="col-sm-7">
        <input type="text" class="form-control" id="source_transferaccount_input" placeholder="" value="{$source_transferaccount_input_default}" {$disabled_var}>
    </div>
    <div class="col-sm-3"><div id="source_transferaccount_result">{$source_transferaccount_html}</div></div>
</div>
HTML;

// 預設轉帳的目的帳號
if( isset($_GET['a']) ){
    $destination_transferaccount_id = filter_var($_GET['a'],FILTER_SANITIZE_STRING);
	$destsql = <<<SQL
		SELECT *
		FROM root_member
		WHERE (status = '1') AND
		      (id = '{$destination_transferaccount_id}');
	SQL;
	$destination_transferaccount_result = runSQLall($destsql);
    $destination_transferaccount_input_default = $destination_transferaccount_result[1]->account;
}
else{
    $destination_transferaccount_id = '';
    $destination_transferaccount_input_default = '';
}

// -----------------------------------------------------------------------------
// 如果有傳入 gcash 和 notes 變數的話,就接收成為預設值
// 通常是來自於管理員發放獎金, 參考用的連結。
if(isset($_GET['gcash'])) {
    $gcash_ref = round($_GET['gcash'],2);
}
else{
    $gcash_ref = NULL;
}
if(isset($_GET['notes'])) {
    $notes_ref = filter_var($_GET['notes'],FILTER_SANITIZE_STRING);
}
else{
    $notes_ref = NULL;
}
// -----------------------------------------------------------------------------

// 存入帐号
$deposit_body_content .= <<<HTML
<div class="form-group row">
    <label class="col-sm-2 col-form-label"><span class="text-danger" aria-hidden="true">*</span>存入帐号</label>
    <div class="col-sm-7">
        <input type="text" class="form-control" id="destination_transferaccount_input" value="{$destination_transferaccount_input_default}" placeholder="ex: mtchang" required>
    </div>
    <div class="col-md-3"><div id="destination_transferaccount_result"></div></div>
</div>
HTML;

// echo '<pre>', var_dump($config['currency_sign']), '</pre>'; exit(); // CNY
// 判斷所登入帳號是否為 config.php 內的 superuser，是的話不做存入總額限制。
if( isset($_SESSION['agent']->account) && in_array($_SESSION['agent']->account, $su['superuser']) ){
    $deposit_body_content .= <<<HTML
        <div class="form-group row">
            <label class="col-sm-2 col-form-label"><span class="text-danger" aria-hidden="true">*</span>存款金额</label>
            <div class="col-sm-7">
                <input type="number" value="{$gcash_ref}" class="form-control" id="balance_input" min="1" step="100" max="1000000" placeholder="" required>
            </div>
        </div>
        <hr>
    HTML;
}
else{
    // 存款金额
    $deposit_total = calculate_deposit_total($_SESSION['agent']->account); // 計算使用者當日存款總額
    $account_setting = query_account_setting('account', $_SESSION['agent']->account); // 所登入帳號的設定值
    $gtoken_input_max = ''; // 單次現金存款限額
    $gtoken_input_daily_max = ''; // 當天現金存款限額

    if( $account_setting[0] == 0 ){
        insert_account_setting($_SESSION['agent']->account);
        $account_setting = query_account_setting('account', $_SESSION['agent']->account);
    }
    $gtoken_input_max = $account_setting[1]->gtoken_input_max;
    $gtoken_input_daily_max = $account_setting[1]->gtoken_input_daily_max;
    $placeholder = ($tr['single deposit limit'] ?? 'single deposit limit').' $'.$gtoken_input_max.'，'.($tr['deposit limit for the day'] ?? 'deposit limit for the day').' $'.$gtoken_input_daily_max;
    $current_deposit_balance = ($gtoken_input_daily_max - $deposit_total);
    $deposit_body_content .= <<<HTML
        <div class="form-group row">
            <label class="col-sm-2 col-form-label"><span class="text-danger" aria-hidden="true">*</span>存款金额</label>
            <div class="col-sm-7">
                <input type="number" value="{$gcash_ref}" class="form-control" id="balance_input" min="1" step="100" max="1000000" placeholder="{$placeholder}" required>
            </div>
            <div class="col-md-3">
                <div id="balance_result">{$tr['current deposit balance']} $ {$current_deposit_balance}</div>
            </div>
        </div>
        <hr>
    HTML;
}

// 稽核方式 select , 免稽核無須輸入金額
// FreeAudit免稽核 DepositAudit存款稽核 ShippingAudit優惠稽核
// 存款及優惠稽核，需要輸入稽核金額
$deposit_body_content        = $deposit_body_content.'
<div class="form-group row align-items-center">
    <label class="col-sm-2 col-form-label pt-2"><span class="text-danger" aria-hidden="true">*</span>稽核方式</span></label>
    <div class="col-sm-7">
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" id="auditmode_select_1" name="auditmode_select" value="freeaudit" checked>
            <label class="form-check-label" for="auditmode_select_1">'.$tr['freeaudit'].'</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" id="auditmode_select_2" name="auditmode_select" value="depositaudit">
            <label class="form-check-label" for="auditmode_select_2">存款稽核</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" id="auditmode_select_3" name="auditmode_select" value="shippingaudit">
            <label class="form-check-label" for="auditmode_select_3">优惠稽核</label>
        </div>
        <p class="text-secondary my-2">
            <span class="text-danger">*</span>
            無論選擇哪項，请直接输入此笔存款，提款时需要稽核的金额。
            <div id="auditmode_select_result"></div>
        </p>
        <input type="number" class="form-control" id="auditmode_input" min="1" step="100" max="1000000" placeholder="请输入欲稽核的金额 ex:100" value="0" placeholder="" disabled>
    </div>
</div>
';

// 控制金額輸入欄位，稽核方式 select , 免稽核無須輸入金額， 存款及優惠稽核，需要輸入稽核金額。
$auditmode_select_js = "
<script>
    $('#auditmode_select_1').click(function(){
      $('#auditmode_input').prop('disabled', true);
    });
    $('#auditmode_select_2, #auditmode_select_3').click(function(){
      $('#auditmode_input').prop('disabled', false);
    });
</script>
";

$deposit_body_content        = $deposit_body_content.$auditmode_select_js;

// system_config.php

// 类型
// 只有人工存款這個項目，才有實際存提的問題。 realcash_input = 1 or is realcash_input = 0
$deposit_body_content        = $deposit_body_content.'
<div class="form-group row">
    <label class="col-sm-2 col-form-label"><span class="text-danger" aria-hidden="true">*</span>类型</label>
    <div class="col-sm-7">
        <select id="summary_input" name="summary_input"  class="form-control">
          <option value="tokendeposit">'.$transaction_category['tokendeposit'].'&nbsp;</option>
          <option value="tokenfavorable">'.$transaction_category['tokenfavorable'].'&nbsp;</option>
          <option value="tokenpreferential">'.$transaction_category['tokenpreferential'].'&nbsp;</option>
          <option value="tokenpay">'.$transaction_category['tokenpay'].'&nbsp;</option>
        </select>
    </div>
    <div class="col-sm-3">
        <input type="checkbox" id="realcash_input" name="realcash_input" value="1">
        <label id="realcash_desc" class="form-check-label" for="realcash_input">实际存提</label>
    </div>
</div>
';

// 當選擇 代幣存款 時，實際存提可以勾選。 其他類別，此項目不可勾選。
$summary_select_js = "
<script>
    $('#summary_input').click(function(){
        var summary_var = $('#summary_input').val();
        console.log(summary_var);
        if( summary_var == 'tokendeposit') {
            $('#realcash_input').prop('disabled', false);
            $('#realcash_desc').removeClass('btn btn-default btn-xs');
            $('#realcash_desc').removeClass('btn btn-default btn-xs disabled');
            $('#realcash_desc').addClass('btn btn-default btn-xs');
        }else{
            $('#realcash_desc').removeClass('btn btn-default btn-xs');
            $('#realcash_desc').removeClass('btn btn-default btn-xs disabled');
            $('#realcash_desc').addClass('btn btn-default btn-xs disabled');
            $('#realcash_input').prop('checked', false);
            $('#realcash_input').prop('disabled', true);
        }
    });
</script>
";

// 前台摘要
$deposit_body_content .= <<<HTML
<div class="form-group row">
    <label class="col-sm-2 col-form-label">
        前台摘要
        <i class="fas fa-info-circle text-secondary" data-toggle="tooltip" data-placement="bottom" title="显示于前台会员端的交易记录明細"></i>
    </label>
    <div class="col-sm-7">
        <textarea class="form-control" id="front_system_note" placeholder="可填入摘要说明"></textarea>
    </div>
    <div class="col-sm-7"><div id="front_system_note_result"></div></div>
</div>
HTML;

// 备注
$deposit_body_content        = $deposit_body_content.$summary_select_js.'
<div class="form-group row">
    <label class="col-sm-2 col-form-label">备注</label>
    <div class="col-sm-7">
        <textarea class="form-control" id="system_note_input" placeholder="备注或是说明">'.$notes_ref.'</textarea>
    </div>
    <div class="col-sm-7"><div id="system_note_result"></div></div>
</div>
';

// 管理員密码
$deposit_body_content        = $deposit_body_content.'
<div class="form-group row">
    <label class="col-sm-2 col-form-label"><span class="text-danger">*</span>管理员密码</label>
    <div class="col-sm-7">
        <input type="password" class="form-control" id="password_input" placeholder="Password"  required>
    </div>
    <div class="col-sm-7"><div id="password_result"></div></div>
</div>
<br>
';

$deposit_body_content        = $deposit_body_content.'
<div class="form-group row">
    <div class="col-sm-2"></div>
    <div class="col-sm-7 d-flex">
        <button id="submit_to_memberdeposit" class="btn btn-success w-75" type="button">转帐</button>
        <button id="submit_to_memberdeposit_cancel" class="btn bg-light border ml-auto text-muted clear_btn" type="button">取消</button>
    </div>
</div>
';

// 建立帳號後,回應訊息的地方。
$deposit_body_content        = $deposit_body_content.'
<div class="row">
    <div class="col-12 col-md-1"></div>
    <div class="col-12 col-md-6">
        <div id="submit_to_memberdeposit_result"></div>
    </div>
    <div class="col-12 col-md-4"></div>

</div>
';

// --------------------------------------------------------
// 不合法的訊息
// --------------------------------------------------------
$deposit_body_content_stop = '
<div class="row">
    <div class="col-12 col-md-1"></div>
    <div class="col-12 col-md-6">
        <p>'.$_SESSION['agent']->account.'你好，人工存入游戏币功能，限定管理员，代理商或帐务员才可以操作。请先登入系统。</p>
    </div>
    <div class="col-12 col-md-4"></div>

</div>
';

// --------------------------------------------------------
// 只有 root 才可以存入 , 不是 root 則顯示錯誤訊息提式。
// --------------------------------------------------------
if($_SESSION['agent']->therole == 'R' ) {
    $panelbody_content        = $deposit_body_content;
}else{
    $panelbody_content        = $deposit_body_content_stop;
}

// --------------------------------------
// (1) 處理上面的表單, JS 動作 , 必要欄位按下 key 透過 jquery 送出 post data 到 url 位址
// (2) 最下面送出，送出後將整各表單透過 post data 送到後面處理。
// --------------------------------------
//  必要欄位 對象 account 帳號 check, 目標帳號不可以為來源帳號。
$agent_memberdeposit_js = "
$('#destination_transferaccount_input').click(function(){
    var destination_transferaccount_input = $('#destination_transferaccount_input').val();
    var source_transferaccount_input = $('#source_transferaccount_input').val();
    $.post('member_depositgtoken_action.php?a=member_depositgtoken_check',
        {
            source_transferaccount_input: source_transferaccount_input,
            destination_transferaccount_input: destination_transferaccount_input
        },
        function(result){
            $('#destination_transferaccount_result').html(result);}
    );
});
";

// 轉帳 post data send, 整理所有必要欄位，及選項欄位的資料，送出
$agent_memberdeposit_js = $agent_memberdeposit_js."
$('#submit_to_memberdeposit').click(function(){
    var submit_to_memberdeposit_input = 'admin_memberdeposit';
    var source_transferaccount_input = $('#source_transferaccount_input').val();
    var destination_transferaccount_input = $('#destination_transferaccount_input').val();
    var balance_input = $('#balance_input').val();
    var auditmode_select_input = $('input[name=".'"auditmode_select"'."]:checked').val();
    var auditmode_input = $('#auditmode_input').val();
    var transaction_category_input = $('#summary_input').val();
    var summary_input = $('#summary_input option:selected' ).text();
    var system_note_input = $('#system_note_input').val();
    var front_system_note = $('#front_system_note').val();

    if($('#realcash_input').is(':checked'))
    {
      var realcash_input = '1';
    }    else{
        var realcash_input = '0';
    }

    if(jQuery.trim(source_transferaccount_input) == '' || jQuery.trim(balance_input) == '' || jQuery.trim($('#password_input').val()) == ''){
        alert('请将底下所有*栏位资讯填入');
    }else{
        var password_input  = $().crypt({method:'sha1', source:$('#password_input').val()});
        $('#submit_to_memberdeposit').attr('disabled', 'disabled');

        if(confirm('确定要进行转帐的操作?')) {
            $.post('member_depositgtoken_action.php?a=member_depositgtoken',
                {
                    submit_to_memberdeposit_input: submit_to_memberdeposit_input,
                    destination_transferaccount_input: destination_transferaccount_input,
                    source_transferaccount_input: source_transferaccount_input,
                    balance_input: balance_input,
                    password_input: password_input,
                    auditmode_select_input: auditmode_select_input,
                    auditmode_input: auditmode_input,
                    summary_input: summary_input,
                    transaction_category_input: transaction_category_input,
                    system_note_input: system_note_input,
                    front_system_note:front_system_note,
                    realcash_input: realcash_input
                },
                function(result){
                    $('#submit_to_memberdeposit_result').html(result);}
            );
        }else{
                window.location.reload();
        }

  }
});
";

// ref: http://crazy.molerat.net/learner/cpuroom/net/reading.php?filename=100052121100.dov
// ref: http://stackoverflow.com/questions/4104158/jquery-keypress-left-right-navigation
// 必要欄位處理：按下 a-z or 0-9 or enter 後,等於 click 檢查是否存在該帳號.
$agent_memberdeposit_keypress_js = "
$(function() {
    $('#destination_transferaccount_input').keyup(function(e) {
        // all key
        if(e.keyCode >= 65 || e.keyCode <= 90) {
            $('#destination_transferaccount_input').trigger('click');
        }
    });

});

//按取消，則彈出視窗，確定是否離開
$('#submit_to_memberdeposit_cancel').click(function(){
    if(confirm('確定要取消編輯嗎')==true){
      document.location.href=\"member_account.php?a=".$destination_transferaccount_id."\";
    }
});

";

// 必要欄位 處理的 js
$agent_memberdeposit_js_html = "
<script>
    $(document).ready(function() {
        ".$agent_memberdeposit_js."
    });
    ".$agent_memberdeposit_keypress_js."
</script>
";

// JS 放在檔尾巴
$extend_js                = $extend_js.$agent_memberdeposit_js_html;
// --------------------------------------
// jquery post ajax send end.
// --------------------------------------

//load 動畫
$load_animate="<div class='load_datatble_animate'><img src='./ui/loading.gif'></div>";

$indexbody_content = <<<HTML
{$load_animate}
<ul class="nav nav-tabs mt-3" id="memberoverviewTab" role="tablist">
  <li class="nav-item">
    <a class="nav-link active" id="bethistory-tab" href="#" role="tab" aria-controls="bethistory" aria-selected="true">
        {$tr['Manual deposit GTOKEN']}
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="member_overview_operating_withdrawalgtoken.php?a=$user->id" id="transactionrecord-tab" role="tab" aria-controls="transactionrecord" aria-selected="false">
        {$tr['Manual withdraw GTOKEN']}
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="member_overview_operating_depositgcash.php?a=$user->id" id="loginhistory-tab" role="tab" aria-controls="loginhistory" aria-selected="false">
		 	{$tr['Manual deposit GCASH']}
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="member_overview_operating_withdrawalgcash.php?a=$user->id" id="auditrecord-tab" role="tab" aria-controls="auditrecord" aria-selected="false">
			{$tr['Manual withdraw GCASH']}
    </a>
  </li>
  <li class="nav-item">
    $user_therole
  </li>
</ul>

<!-- tab內容 -->
<div class="tab-content tab_p_overview" id="overviewoperating">
    <div class="row my-3">
        <div class="col-12 col-md-8 mx-auto">
            <div class="tab-pane fade show active" id="bethistory" role="tabpanel" aria-labelledby="bethistory-tab">  
                <!-- 存入遊戲幣 -->
                    {$deposit_body_content}
            </div>

            <div class="tab-pane fade" id="transactionrecord" role="tabpanel" aria-labelledby="transactionrecord-tab">
                <!-- 提出遊戲幣 -->	
                    
            </div>

            <div class="tab-pane fade" id="loginhistory" role="tabpanel" aria-labelledby="loginhistory-tab">
            <!--  存入現金 -->
                
            </div>

            <div class="tab-pane fade" id="auditrecord" role="tabpanel" aria-labelledby="auditrecord-tab">
            <!--  提出現金 -->
                
            </div>

                <div class="tab-pane fade" id="agentssetting" role="tabpanel" aria-labelledby="agentssetting-tab">
            <!--  代理占比設定 -->
                
            </div>
        </div>
    </div>
</div>
HTML;

$extend_js = $extend_js.<<<HTML
<!-- 參考使用 datatables 顯示 -->
<!-- https://datatables.net/examples/styling/bootstrap.html -->
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
<link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
<style>
  /* 需要改CSS 名稱以面控制所有 */
  #transaction_list_paginate{
    display: flex;
    margin-top: 10px;
  }
  #transaction_list_paginate .pagination{
    margin-left: auto;			
	}
	#transaction_list_wrapper{
		margin-left: 0;
		margin-right: 0;
		padding-left: 0;
		padding-right: 0;
	}
  /* 清除按鈕 */
  .clear_btn{
	  width: 20%;
	}
	#transaction_list .bg-primary{
		background-color: #e0ecf9b8!important;
	}
  /* 外部Datatable search border-color */
  #search_agents{
	border-color: #ced4da!important;
	padding: .2rem .75rem;
  }
  #transaction_list_filter{
	  display:none;
  }
  #transaction_list_length {
    margin-top: 10px;
    padding-top: 0.25em;
  }
  .tab_p_overview{
		padding: 15px;
  }
</style>
<script>
		$(document).ready(function() {	
			// locationhash http:// #id
			// split  http:// #id
			var locationtab = location.hash;
			var locationsplit = locationtab.split('_');

			var locationhash = locationsplit[0];
			var locationsearch = location.search;

			if( locationhash != '' ){				
				// tab button show from http:// #id 
				$('#memberoverviewTab a[href="'+locationhash+'"]').tab('show');					
			}
			//tab button has show close load animate
			$('#memberoverviewTab a[href="'+locationhash+'"]').on('shown.bs.tab', function (e) {
				$('.load_datatble_animate').fadeOut();
			});
			//if locationhash = null or locationhash = First one tab , close load animate 
			if( locationhash == '' || locationhash == '#bethistory' ){
				$('.load_datatble_animate').hide();
			}
            
			//if locationtab = reward_link  load animate hide()
			if( locationtab == '#reward_link' ){	
				$('.load_datatble_animate').hide();
			}
			if( locationtab == '#commission_link' ){	
				$('.load_datatble_animate').hide();
			}
				
			$('[data-toggle="popover"]').popover();
			$('[data-toggle="tooltip"]').tooltip();
			//up open box
			$('.telescopic_btn').click(function(){
				var closeheight = $(this).next().hasClass('closeheight');				
				if( closeheight == false ){
					$(this).next().slideUp();
					$(this).next().addClass('closeheight');
				}else {
					$(this).next().slideDown();
					$(this).next().removeClass('closeheight');
				}
			});
					
		var tl_tabke =	$("#transaction_list").DataTable( {
				// "paging":   true,
				// "ordering": true,
				// "info":     true,
				// "order": [[ 1, "asc" ]],
				// "pageLength": 30,
				// 假資料
				"dom": '<ftlip>',
				"ajax": "https://shiuanlin.jutainet.com/json/historyfour.php",
				"columns": [
					{ "data": "id"},
					{ "data": "account",
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '<a href="member_account.php?a=10041" target="_blank">'+ oData.account +'</a>';
							$(nTd).html(html);
						}
					},
					{ "data": "therole","class": "text-center",
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var data = oData.therole;
							var member_text = "{$tr['member']}";
							var agent_text = "{$tr['Identity Agent']}";
							if ( data == member_text ) {
							var html = '<span class="glyphicon glyphicon-user text-primary" title="'+ member_text +'"></span>';
							}else if ( data == agent_text ) {
							var html = '<span class="glyphicon glyphicon-knight text-primary" title="'+ agent_text +'"></span>';
							}else{
							var html = data;
							}
							$(nTd).html(html);
						}
					},
					{ "data": "enrollmentdate"},
					{ "data": "status"},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					}
				]
			});

			$('#search_agents').keyup(function(){
				tl_tabke.search($(this).val()).draw();
			});			

		});
	</script>
HTML;
// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author'] = $tr['host_author'];
$tmpl['html_meta_title'] = $tr['member overview'] . '-' . $tr['host_name'];

// 頁面大標題
$tmpl['page_title'] = $menu_breadcrumbs;
// 主要內容 -- title
$tmpl['paneltitle_content'] = $destination_transferaccount_input_default.'功能操作';
// 主要內容 -- content
$tmpl['panelbody_content'] = $indexbody_content;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;
// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include "template/member_tml.php";

?>
