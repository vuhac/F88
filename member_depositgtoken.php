<?php
// ----------------------------------------------------------------------------
// Features:    後台 -- 人工存款GTOKEN, 管理員可以針對會員進行代幣儲值的動作
// File Name:    member_depositgtoken.php
// Author:        Barkley
// Related:
// Permission: 只有站長或是客服才可以執行
// Log:
// 2016.11.20 v0.2
// 2020.08.05 Letter Bug #4383 VIP站後台，娛樂城反水計算 > 錢包凍結帳號 > 轉現金/轉遊戲幣選項 > 跳錯
//                   不過濾會員狀態
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// var_dump($_SESSION);

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
  <li><a href="#">' . $tr['Members and Agents'] . '</a></li>
  <li><a href="member.php">'.$tr['Member inquiry'].'</a></li>
  <li class="active">' . $function_title . '</li>
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
$page_desc    = '<div class="alert alert-success" role="alert">
* '.$tr['Manual deposit of GTOKEN function: This is for the administrator or allowed customer service to manually deposit GTOKEN. The administrator can give GTOKEN to any account.'].'<br>
* '.$tr['The default is as an administrator, to use the cashier account'].''.$gtoken_cashier_account.' '.$tr['transfer funds to the specified account. Game currency cannot be transferred between general accounts.'].'<br>
* '.$tr['It can usually be used to issue game currency discounts, game currency backwaters, or deposit game currency. These items need to be audited when withdrawing to cash (GCASH).'].'
</div>';

// 功能及操作的說明
$deposit_body_content = $page_desc;
$show_list_html = '';
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

// 查詢來源帳號的餘額
$sql = <<<SQL
    SELECT *
    FROM root_member
    JOIN root_member_wallets ON (root_member.id=root_member_wallets.id)
    WHERE (root_member.account = '{$source_transferaccount_input_default}');
SQL;
$g = runSQLALL($sql);
if( $g[0] == 1 ) {
    $source_transferaccount_html  = $tr['current balance'].'&nbsp;'.money_format('%i', $g[1]->gtoken_balance);;
}
else{
    $source_transferaccount_html  = '';
}



// GCASH 額度來源帳號
$show_list_html .= <<<HTML
<div class="row">
    <div class="col-12 col-md-2"><p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>GTOKEN {$tr['source account']}</p></div>
    <div class="col-12 col-md-4">
        <input type="text" class="form-control" id="source_transferaccount_input" placeholder="" value="{$source_transferaccount_input_default}" {$disabled_var}>
    </div>
    <div class="col-12 col-md-6"><div id="source_transferaccount_result">{$source_transferaccount_html}</div></div>
</div>
<br>
HTML;

// 預設轉帳的目的帳號
if( isset($_GET['a']) ){
    $destination_transferaccount_id = filter_var($_GET['a'],FILTER_SANITIZE_STRING);
	$destsql = <<<SQL
		SELECT *
		FROM root_member
		WHERE (id = '{$destination_transferaccount_id}');
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
$show_list_html .= <<<HTML
<div class="row">
    <div class="col-12 col-md-2"><p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>{$tr['Deposit account']}</p></div>
    <div class="col-12 col-md-4">
        <input type="text" class="form-control" id="destination_transferaccount_input" value="{$destination_transferaccount_input_default}" placeholder="ex: mtchang" required>
    </div>
    <div class="col-12 col-md-6"><div id="destination_transferaccount_result"></div></div>
</div>
<br>
HTML;

// echo '<pre>', var_dump($config['currency_sign']), '</pre>'; exit(); // CNY
// 判斷所登入帳號是否為 config.php 內的 superuser，是的話不做存入總額限制。
if( isset($_SESSION['agent']->account) && in_array($_SESSION['agent']->account, $su['superuser']) ){
    $show_list_html .= <<<HTML
        <div class="row">
            <div class="col-12 col-md-2"><p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>{$tr['deposit amount']}</p></div>
            <div class="col-12 col-md-4">
                <input type="number" value="{$gcash_ref}" class="form-control" id="balance_input" min="1" step="100" max="1000000" placeholder="" required>
            </div>
        </div>
        <br>
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
    $show_list_html .= <<<HTML
        <div class="row">
            <div class="col-12 col-md-2"><p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>{$tr['deposit amount']}</p></div>
            <div class="col-12 col-md-4">
                <input type="number" value="{$gcash_ref}" class="form-control" id="balance_input" min="1" step="100" max="1000000" placeholder="{$placeholder}" required>
            </div>
            <div class="col-12 col-md-6">
                <div id="balance_result">{$tr['current deposit balance']} $ {$current_deposit_balance}</div>
            </div>
        </div>
        <br>
        <hr>
    HTML;
}


// 稽核方式 select , 免稽核無須輸入金額
// FreeAudit免稽核 DepositAudit存款稽核 ShippingAudit優惠稽核
// 存款及優惠稽核，需要輸入稽核金額
$show_list_html        = $show_list_html.'
<div class="row">
    <div class="col-12 col-md-2"><p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>'.$tr['audit method'].'</p></div>
    <div class="col-12 col-md-4">
        <div>
        <span><input type="radio" id="auditmode_select_1" name="auditmode_select" value="freeaudit" checked>
'.$tr['freeaudit'].'</span>
        <span><input type="radio" id="auditmode_select_2" name="auditmode_select" value="depositaudit">'.$tr['Deposit audit'].'</span>
        <span><input type="radio" id="auditmode_select_3" name="auditmode_select" value="shippingaudit">'.$tr['Preferential deposit audit'].'</span>
        </div>
        <div><br><input type="number" class="form-control" id="auditmode_input" min="1" step="100" max="1000000" placeholder="请输入欲稽核的金额 ex:100" value="0" placeholder="" disabled>

        </div>
    </div>
    <div class="col-12 col-md-6">
        '.$tr['No matter which option you choose, please directly enter this deposit and the amount to be audited during withdrawal.'].'
        <div id="auditmode_select_result"></div>
    </div>
</div>

<br>
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

$show_list_html        = $show_list_html.$auditmode_select_js;

// system_config.php



// 类型
// 只有人工存款這個項目，才有實際存提的問題。 realcash_input = 1 or is realcash_input = 0
$show_list_html        = $show_list_html.'
<div class="row">
    <div class="col-12 col-md-2">
    <p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>'.$tr['type'].'</p></div>
        <div class="col-12 col-md-4">
            <select id="summary_input" name="summary_input"  class="form-control">
              <option value="tokendeposit">'.$transaction_category['tokendeposit'].'&nbsp;</option>
              <option value="tokenfavorable">'.$transaction_category['tokenfavorable'].'&nbsp;</option>
              <option value="tokenpreferential">'.$transaction_category['tokenpreferential'].'&nbsp;</option>
              <option value="tokenpay">'.$transaction_category['tokenpay'].'&nbsp;</option>
            </select>
        </div>
    <div class="col-12 col-md-6">
        <span id="realcash_desc" class="btn btn-default btn-xs"><input type="checkbox" id="realcash_input" name="realcash_input" value="1">'.$tr['Actual deposit'].'</span>
    </div>
</div><br>
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
$show_list_html .= <<<HTML
<div class="row">
    <div class="col-12 col-md-2">
        <p class="text-right">{$tr['Summary']}
            <i class="fas fa-info-circle text-primary" data-toggle="tooltip" data-placement="bottom" title="显示于前台会员端的交易记录明細"></i>
        </p>
    </div>
    <div class="col-12 col-md-4">
        <textarea class="form-control validate[maxSize[500]]" maxlength="500" id="front_system_note" placeholder="{$tr['Summary']}({$tr['max']}500{$tr['word']})"></textarea>
    </div>
    <div class="col-12 col-md-6"><div id="front_system_note_result"></div></div>
</div>
<br>
HTML;

// 备注
$show_list_html        = $show_list_html.$summary_select_js.'
<div class="row">
    <div class="col-12 col-md-2"><p class="text-right">'.$tr['note'].'</p></div>
    <div class="col-12 col-md-4">
        <textarea class="form-control validate[maxSize[500]]" maxlength="500" id="system_note_input" placeholder="'.$tr['note'].'('.$tr['max'].'500'.$tr['word'].')">'.$notes_ref.'</textarea>
    </div>
    <div class="col-12 col-md-6"><div id="system_note_result"></div></div>
</div>
<br>
';


// 管理員密码
$show_list_html        = $show_list_html.'
<div class="row">
    <div class="col-12 col-md-2"><p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>'.$tr['passwd'].'</p></div>
    <div class="col-12 col-md-4">
        <input type="password" class="form-control" id="password_input" placeholder="Password"  required>
    </div>
    <div class="col-12 col-md-6"><div id="password_result"></div></div>
</div>
<br>
';



$show_list_html        = $show_list_html.'
<div class="row">
    <div class="col-12 col-md-2"></div>
    <div class="col-12 col-md-2">
        <button id="submit_to_memberdeposit" class="btn btn-success btn-block" type="submit">'.$tr['Submit'].'</button>
    </div>
    <div class="col-12 col-md-2">
        <button id="submit_to_memberdeposit_cancel" class="btn btn-default btn-block" type="submit">'.$tr['Cancel'].'</button>
    </div>
    <div class="col-12 col-md-6"></div>
</div><br>
';

$form_list='';
  
    $form_list = $form_list.'
      <form class="form-horizontald" role="form" id="preferential_form">
        '.$deposit_body_content.'
        '.$show_list_html.'
      </form>
    ';

    $deposit_body_content =     $form_list;
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
    if(confirm('确定要取消编辑吗')==true){
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
// JS 開頭
$extend_head = $extend_head. <<<HTML
    <script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
    <script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
    <link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>

    <script type="text/javascript" language="javascript" class="init">
        $(document).ready(function () {
            $("#preferential_form").validationEngine();
        });
    </script>
HTML; 
// JS 放在檔尾巴
$extend_js                = $extend_js.$agent_memberdeposit_js_html;
// --------------------------------------
// jquery post ajax send end.
// --------------------------------------





// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description']         = $tr['host_descript'];
$tmpl['html_meta_author']                     = $tr['host_author'];
$tmpl['html_meta_title']                     = $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']                                = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']                            = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']                                = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content']             = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']                = $panelbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
//include("template/member.tmpl.php");
include("template/beadmin.tmpl.php");



?>
