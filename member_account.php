<?php
// ----------------------------------------------------------------------------
// Features:    後台--會員帳號細節列表
// File Name:    member_account.php
// Author:        Barkley
// Related:        index.php
// Log:
// 2016.11.24 update
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

// var_dump(in_array($_SESSION['agent']->account,$su['superuser']));

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
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
$extend_head = '';
$extend_js = '';
$function_title = $tr['detail info'];
$page_title = '';
$indextitle_content = '<span class="glyphicon glyphicon-cog"></span>' . $tr['set up'];
$indexbody_content = '';
$paneltitle_content = '<span class="glyphicon glyphicon-list" aria-hidden="true"></span>' . $tr['Query results'];

$panelbody_content = '';
// ----------------------------------------------------------------------------
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' . $tr['Members and Agents'] . '</a></li>
    <li><a href="member.php">' . $tr['Member inquiry'] . '</a></li>
  <li class="active">' . $function_title . '</li>
</ol>';
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 此程式功能說明：
// 以使用者帳號為主軸 , 管理者可以操作各種動作。
// ----------------------------------------------------------------------------

// 只有管理員才可以使用這個功能
if (isset($_GET['a'])) {
    // (1) 依據帳號，顯示條列會員資訊

    $account_id = filter_var($_GET['a'], FILTER_SANITIZE_NUMBER_INT);
    if (!is_numeric($account_id)) {
        $logger = $tr['The user ID is error']; // 使用者输入的帐号不正确！
        die($logger);
    }

    if ( ($system_mode == 'developer') || ($config['hostname'] == 'GPKDEMO') || ($config['hostname'] == 'DEMO') || in_array($_SESSION['agent']->account, $su['superuser']) ) {
        // 這裡什麼事情都不用做
    } else {
        if ($account_id < 10000) {
            $logger = $tr['this member or administrator cannot display detailed information'];
            die($logger);
        }
    }
} else {
    die('NO ID ERROR!!');
}

// get use member data
$sql = "SELECT *,enrollmentdate, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD  HH24:MI:SS') as enrollmentdate_tz FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.id = '" . $account_id . "';";
// $sql = "SELECT *, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as enrollmentdate_tz FROM root_member WHERE id = '".$account_id."';";
// var_dump($sql);
$r = runSQLALL($sql);
// var_dump($r);
// 正常只能有一個帳號, 並取得正常的資料。
if ($r[0] == 1) {
    // 取得 user account 全部資料
    $user = $r[1];
    // 上級代理商編號如果在一萬以內，則不可顯示
    if ($user->parent_id < 10000) {
        $user->parent_id = '0';
    }

    // 身份用途展示
    $theroleicon = [];
    $therole_icon_array = [
        'R' => [ // 管理員
            'class' => 'glyphicon-king',
            'tr_key' => 'Identity Management Title'
        ],
        'A' => [ // 代理商
            'class' => 'glyphicon-knight',
            'tr_key' => 'Identity Agent Title'
        ],
        'M' => [ // 會員
            'class' => 'glyphicon-user',
            'tr_key' => 'Identity Member Title'
        ],
        'T' => [ // 試用帳號
            'class' => 'glyphicon-sunglasses',
            'tr_key' => 'Identity Trial Account Title'
        ]
    ];
    foreach ($therole_icon_array as $key=>$val) {
        $theroleicon[$key] = <<<HTML
            <span class="glyphicon {$val['class']}" aria-hidden="true"></span>
            {$tr[$val['tr_key']]}
        HTML;
    }

    // 找出推薦人帳號 (Edited By Damocles In 2020/05/07)
    $parent_account_sql = <<<SQL
        SELECT "account"
        FROM "root_member"
        WHERE ("id" = '{$user->parent_id}')
        LIMIT 1;
    SQL;
    $parent_account_result = runSQLALL($parent_account_sql);

    $account_information_item = <<<HTML
        <tr>
            <th>{$tr['identity']}</th>
            <td>
                <p class="mb-0">{$theroleicon[$user->therole]}</p>
            </td>
        </tr>
    HTML;

    // 判斷是否有設定 $su['superuser']
    if ( isset($su['superuser']) && is_array($su['superuser']) ) {
        if ($parent_account_result[0] == 1) { // 有查詢到推薦人帳號
            // 如果推薦人帳號為Super群組內的帳號，則須遮蔽
            if ( in_array($parent_account_result[1]->account, $su['superuser']) ) { // 推薦人帳號在 $su['superuser']內
                $placeholder_msg = $tr['Current account is the top']; // 該帳號為頂層帳號
                $account_information_item .= <<<HTML
                    <tr>
                        <th>{$tr['upper agent number']}</th>
                        <td>
                            <span class="glyphicon glyphicon-info-sign"></span>
                            {$tr['Current account is the top']}
                        </td>
                    </tr>
                HTML;
            } else { // 推薦人帳號不在 $su['superuser']內
                $placeholder_msg = $tr['The current referral account is:'] . $parent_account_result[1]->account;
                $account_information_item .= <<<HTML
                    <tr>
                        <th>{$tr['upper agent number']}</th>
                        <td>
                            <button class="btn btn-link upper_agent_btn p-0 text-primary" type="button" value="{$user->parent_id}">
                                <a href="member_account.php?a={$user->parent_id}" >{$parent_account_result[1]->account}
                                    <i class="fas fa-external-link-alt ml-2"></i>
                                </a>
                            </button>
                        </td>
                    </tr>
                HTML;
            }
        } else { // 沒有查詢到推薦人帳號
            $placeholder_msg = $tr['Upper-level recommender account query error'];
            $account_information_item .= <<<HTML
                <tr>
                    <th>{$tr['upper agent number']}</th>
                    <td>
                        <span class="glyphicon glyphicon-info-sign"></span>
                        {$tr['Current account is the top']}
                    </td>
                </tr>
            HTML;
        }
    } else { // 沒有設定 $su['superuser']
        $placeholder_msg = $tr['Super user account is not defined'];
        $account_information_item .= <<<HTML
            <tr>
                <th>{$tr['upper agent number']}</th>
                <td>
                    <!-- 沒有設定 su['superuser'] -->
                    <span class="glyphicon glyphicon-info-sign" style="color: red;"></span>
                    <span style="color: red;">{$tr['Super user account is not defined']}</span>
                </td>
            </tr>
        HTML;
    }

    $user->email = ( (!empty($user->email)) ? trim($user->email) : '----' );
    $account_information_item .= <<<HTML
        <tr>
            <th>{$tr['Email']}</tH>
            <td>{$user->email}</td>
        </tr>
    HTML;



    // --------------------------------------------------------------------
    // 產生 csrf token , $csrftoken 需要透過這個傳遞到對應的 action page post 內
    $csrftoken = csrf_token_make();
    // ---------------------------------------------------
    // (1)帳號資訊 -- 依據 id 找出 parent_id 為這個 id 的 map tree  end
    // ---------------------------------------------------

    // 條列會員資訊
    $listuser_item = '';

    //代理占比 如果是會員則不可以設定代理占比
    if ($user->therole != 'A'){
      $user_therole = '';
    }else {
      $user_therole = '
      <a class="btn btn-secondary w-100 mb-2 text-white" href="agents_setting.php?a=' . $account_id . '"  role="button" title=" ' . $tr['Set agency commission'] . ' " value=""><i class="fas fa-link mr-2"></i> ' . $tr['Set agency commission'] . '</a>
      ';
    }

    $userid_treemap_html = '
    <a class="btn btn-secondary w-100 mb-2" href="member_treemap.php?id=' . $account_id . '" role="button" title="' . $tr['Member 4 generations of structural relations organization chart'] . '" value="member_treemap.php?id=' . $user->id . '"><i class="fas fa-link mr-2"></i>' . $tr['member relationship tree'] . '</a>
    <a class="btn btn-secondary w-100 mb-2" href="member_betlog.php?a=' . $user->account . '" target="_SELF" title="' . $tr['To query member betting data'] . ' " role="button"><i class="fas fa-link mr-2"></i>' . $tr['To query member betting data'] . '</a>
    '.$user_therole;

  //帳戶資訊 編輯按鈕 標題
  $account_information_title = '
  <div class="row">
    <div class="col-12 d-flex">
      <h5 class="font-weight-bold">' . $tr['account info'] . '
      <a href="member_edit.php?i=' . $account_id . '" title="' . $tr['edit'] . '" class="px-3"> <i class="fas fa-edit"></i> '.$tr['edit'].' </a>
      </h5>
    </div>
  </div>
  ';

    //帳戶資訊 內容
    // 目前身分 上層代理商編號 电子邮件


/*   $account_information_item = '
  <tr>
    <th>' . $tr['agent review identity'] . '</th>
    <td><p class="mb-0">' . $theroleicon[$user->therole] . '</p></td>
  </tr>
  <tr>
    <th>' . $tr['upper agent number'] . '</th>
    <td>
    <button class="btn btn-link upper_agent_btn p-0 text-primary" type="button" value="' . $user->parent_id . '">
    <a href="member_account.php?a='.$user->parent_id.'" >' . ($parent_account_result[1]->account ?? 'N/A') . ' <i class="fas fa-external-link-alt ml-2"></i></a></button>
    </td>
  </tr>
  <tr>
    <th>' . $tr['Email'] . '</td>
    <td>' . $user->email . '</td>
  </tr>
  '; */

  $sns1 = $protalsetting["custom_sns_rservice_1"]??$tr['sns1'];
    $sns2 = $protalsetting["custom_sns_rservice_2"]??$tr['sns2'];

  //帳戶資訊 內容
  // 手机 QQ 微信号
  $account_informationtwo_item = '
  <tr>
    <th>' . $tr['Cell Phone'] . '</th>
    <td>' . $user->mobilenumber . '</td>
  </tr>
  <tr>
    <th>'.$sns1.'</th>
    <td>' . $user->wechat . '</td>
  </tr>
  <tr>
    <th>' . $sns2 . '</th>
    <td>' . $user->qq . '</td>
  </tr>
  ';
  // --------------------------------------------------------------------------
  // 註冊資訊
  // --------------------------------------------------------------------------
  // $tr['Register ip'] = '註冊時ip：';
  // $tr['Register fingercode'] = '註冊時指紋碼：';
  // $tr['Register time'] = '註冊時間：';

  //註冊資訊 標題
  $sign_up_title = '
  <div class="row">
    <div class="col-12"><h5 class="font-weight-bold">' . $tr['Regist info'] . '</h5></div>
  </div>
  ';

  //註冊資訊 內容
  //注册时IP： 注册时指纹码
  $sign_up_item = '
  <tr>
    <th>' . $tr['Register ip'] . '</th>
    <td>' . $user->registerip . '</td>
  </tr>
  <tr>
    <th>' . $tr['Register fingercode'] . '</th>
    <td>' . $user->registerfingerprinting . '</td>
  </tr>
  ';

  //註冊資訊 內容
  //注册时间
  $sign_uptwo_item = '
  <tr>
    <th>' . $tr['Register time'] . '</th>
    <td>' . gmdate('Y-m-d H:i',strtotime($user->enrollmentdate_tz) + -4*3600) . '</td>
  </tr>
  ';

    // --------------------------------------------------------------------------
    // 登入資訊 內容
    // --------------------------------------------------------------------------

    //最后登入IP 最后登入指纹码： 最后登入时间 查询详细登入资讯
    //$tr['last login ip'] = '最後登入ip：';
    // $tr['last login fingercode'] = '最後登入指紋碼：';
    // $tr['last login time'] = '最後登入時間：';
    // $tr['Search IP'] = '查詢ip：';
    // $tr['record'] = '紀錄';
    // $tr['search fingercode'] = '查詢指紋碼 :';
    // $tr['Query details login info'] = '查詢詳細登入資訊 :';
    // $tr['Go to login info page'] = '前往登入資訊查詢 :';
    $show_member_log_sql    = "SELECT to_char((occurtime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') AS log_time, who AS log_account, sub_service, message, agent_ip AS log_ip, fingerprinting_id AS log_fingerprinting FROM root_memberlog WHERE who = '" . $user->account . "' AND sub_service = 'login' ORDER BY log_time DESC LIMIT 1;";
    $show_member_log_result = runSQLall($show_member_log_sql);
    if ($show_member_log_result[0] >= 1) {
            $member_log = $show_member_log_result[1];
      $sign_in_item = '
      <tr>
        <th >' . $tr['last login ip'] . '</th>
        <td><a href="member_log.php?ip=' . $member_log->log_ip . '" title="' . $tr['Search IP'] . '' . $member_log->log_ip . ' ' . $tr['record'] . '">' . $member_log->log_ip . '</a></td>
      </tr>
      <tr>
        <th>' . $tr['last login fingercode'] . '</th>
        <td><a href="member_log.php?fp=' . $member_log->log_fingerprinting . '" title="' . $tr['search fingercode'] . ' ' . $member_log->log_fingerprinting . ' ' . $tr['record'] . '">' . $member_log->log_fingerprinting . '</a></td>
      </tr>
      ';
      $sign_intwo_item = '
      <tr>
        <th >' . $tr['login account'] . '</th>
        <td><a href="member_log.php?a=' . $user->account . '" title="' . $tr['Go to login info page'] . '">' . $user->account . '</a></td>
      </tr>
      <tr>
        <th >' . $tr['last login time'] . '</th>
        <td>' . $member_log->log_time . '</td>
      </tr>
      ';
    } else {
      // $tr['have not login yet'] = '帳號建立後，尚未登入過。';
      $sign_in_item = '
      <span class="label label-danger">' . $tr['have not login yet'] . '</span>
      ';
      $sign_intwo_item = '';
        }

        //最後登入資訊 標題
    $sign_in_title = '
    <div class="row">
      <div class="col-12 d-flex">
        <h5 class="font-weight-bold">
                ' . $tr['login information'] . '
                </h5>
      </div>
    </div>
    ';

    // 前往會員帳號.ip.指紋碼log查詢 button 事件 js
    // 跳轉到會員帳號.ip.指紋碼log查詢頁面
    /*
    $extend_js = $extend_js . "
    <script>
    $('.select_member_log_btn').click(function(){
    window.location.href='member_log.php?a=" . $user->account . "';
    });
    </script>
    ";
    */

    // --------------------------------------------------------------------------
    // 銀行帳戶資訊
    // --------------------------------------------------------------------------

    //標題
    $bank_account_title = '
    <div class="row">
    <div class="col-12 d-flex">
      <h5 class="font-weight-bold">' . $tr['bank account information'] . '
      <a href="member_edit.php?i=' . $account_id . '" title="' . $tr['edit']  . '" class="px-3"> <i class="fas fa-edit"></i>' . $tr['edit']  . ' </a>
      </h5>
    </div>
    </div>
    ';

    //银行名称 银行号码
    $bank_account_item = '
    <tr>
      <th >' . $tr['bank name'] . '</th>
      <td>' . $user->bankname . '</td>
    </tr>
    <tr>
      <th>' . $tr['bank number'] . '</th>
      <td>' . $user->bankaccount . '</td>
    </tr>
    ';

    //银行省份 银行县市
    $bank_accounttwo_item = '
    <tr>
      <th >' . $tr['bank province'] . '</th>
      <td>' . $user->bankprovince . '</td>
    </tr>
    <tr>
      <th>' . $tr['bank city'] . '</th>
      <td>' . $user->bankcounty . '</td>
    </tr>
    ';

    // --------------------------------------------------------------------------
    // (3)取得指定帳戶餘額及存提款資訊
    // --------------------------------------------------------------------------

    // 使用者的帳戶餘額
    // gcash 為現金, gtoken 為代幣
    // casino 的錢包, 使用免轉錢包時候 gtoken_lock 會 設定為所在的 casino ，只有取回錢包時才會清除 lock to null。
    if ($user->gtoken_lock == null) {
      // $gtoken_lock_html = '代幣未使用';
      $gtoken_lock_html = '<p class="text-danger mb-2">' . $tr['coin do not use'] . '</p>';
    } else {
        $gtoken_lock_html = '<p class="mb-2">' . $user->gtoken_lock . '</p>';
    }

  // 現金大於 $alert_balance 時，也顯示不同的顏色。提醒管理員他的現金有大於 $alert_balance
  $alert_balance = 1000;
  // 現金 gcash 大於 1 元時, 顯示不同的顏色
  if ($user->gcash_balance >= $alert_balance) {
      $gcash_balance_html = '<h5 class="mb-3 text-danger"><span class="glyphicon glyphicon-usd"></span>' . $user->gcash_balance. '</h5>';
  } elseif ($user->gcash_balance >= 1) {
      $gcash_balance_html = '<h5 class="mb-3"><span class="glyphicon glyphicon-usd"></span>' . $user->gcash_balance . '</h5>';
  } else {
      $gcash_balance_html = '<h5 class="mb-3"><span class="glyphicon glyphicon-usd"></span>' . $user->gcash_balance . '</h5>';
  }

  // 代幣大於 $alert_balance 時，也顯示不同的顏色。提醒管理員他的代幣有大於 $alert_balance
  // 代幣 gtoken 大於 1 元時, change color
  if ($user->gtoken_balance >= $alert_balance) {
      $gtoken_balance_html = '<h5 class="mb-3 text-danger"><span class="glyphicon glyphicon-usd"></span>' . $user->gtoken_balance . '</h5>';
  } elseif ($user->gtoken_balance >= 1) {
      $gtoken_balance_html = '<h5 class="mb-3"><span class="glyphicon glyphicon-usd"></span>' . $user->gtoken_balance . '</h5>';
  } else {
      $gtoken_balance_html = '<h5 class="mb-3"><span class="glyphicon glyphicon-usd"></span>' . $user->gtoken_balance . '</h5>';
  }

  // 代幣存款sql
  $gtoken_deposit_sql = "SELECT SUM(deposit) AS deposit_sum, COUNT(deposit) AS deposit_count FROM root_member_gtokenpassbook WHERE transaction_category IN ('tokendeposit','apitokendeposit','company_deposits') AND source_transferaccount = '" . $user->account . "' AND destination_transferaccount = '" . $gtoken_cashier_account . "'AND realcash='1';";
  // var_dump($gtoken_deposit_sql);
  $gtoken_deposit_sql_result = runSQLall($gtoken_deposit_sql);
  // var_dump($gtoken_deposit_sql_result);
  // 代幣提款sql
  $gtoken_withdrawal_sql = "
SELECT SUM(amount_sum) amount_sum ,COUNT (transaction_id) amount_count
FROM (
  SELECT SUM(rtokeng.withdrawal) AS amount_sum,
         rtokeng.transaction_id AS transaction_id
  FROM root_withdraw_review rtokenr
  FULL JOIN  root_member_gtokenpassbook AS rtokeng
  ON rtokenr.transaction_id=rtokeng.transaction_id
  WHERE rtokenr.account = '" . $user->account . "'
  AND rtokeng.source_transferaccount = '" . $user->account . "'
  AND rtokeng.withdrawal > 0
  AND rtokenr.status = '1'
  AND rtokeng.realcash='1'
  GROUP BY rtokeng.transaction_id
  ) AS subquery ;";
  // echo($gtoken_withdrawal_sql);die();
  $gtoken_withdrawal_sql_result = runSQLall($gtoken_withdrawal_sql);
  // var_dump($gtoken_withdrawal_sql_result);

  // 現金存款sql，現金存款種類有：1.現金存款，2.現上支付，3.api線上支付 IN ('cashdeposit','payonlinedeposit','apicashdeposit')
  $gcash_deposit_sql = "SELECT SUM(deposit) AS deposit_sum, COUNT(deposit) AS deposit_count FROM root_member_gcashpassbook WHERE transaction_category IN ('cashdeposit','payonlinedeposit','apicashdeposit','company_deposits') AND source_transferaccount = '" . $user->account . "' AND destination_transferaccount = '" . $gcash_cashier_account . "' AND realcash='1';";
  // var_dump($gcash_deposit_sql);die();
  $gcash_deposit_sql_result = runSQLall($gcash_deposit_sql);
  // var_dump($gcash_deposit_sql_result);
  // 現金提款sql
  $gcash_withdrawal_sql = "
SELECT SUM(amount_sum) amount_sum ,COUNT (transaction_id) amount_count
FROM (
    SELECT SUM(rmg.withdrawal) AS amount_sum,
           rmg.transaction_id AS transaction_id
    from root_withdrawgcash_review AS rwr
    FULL join root_member_gcashpassbook AS rmg
    on rwr.transaction_id=rmg.transaction_id
    where rwr.account ='" . $user->account . "'
     and rmg.source_transferaccount = '" . $user->account . "'
     and rmg.withdrawal > 0
     and rwr.status = '1'
     and rmg.realcash='1'
    GROUP BY rmg.transaction_id
  ) AS subquery ;";

  // echo($gcash_withdrawal_sql);die();
  $gcash_withdrawal_sql_result = runSQLall($gcash_withdrawal_sql);
  // var_dump($gcash_withdrawal_sql_result);

  // 代幣存款次數及金額
  $gtoken_deposit_count             = $gtoken_deposit_sql_result[1]->deposit_count;
  $gtoken_deposit_count_amount      = $gtoken_deposit_sql_result[1]->deposit_sum;
  $gtoken_deposit_count_amount_html = money_format('%i', $gtoken_deposit_count_amount);
  // 代幣提款次數及金額
  $gtoken_withdrawal_count             = $gtoken_withdrawal_sql_result[1]->amount_count;
  $gtoken_withdrawal_count_amount      = $gtoken_withdrawal_sql_result[1]->amount_sum;
  $gtoken_withdrawal_count_amount_html = money_format('%i', $gtoken_withdrawal_count_amount);

  // 現金存款次數及金額
  $gcash_deposit_count             = $gcash_deposit_sql_result[1]->deposit_count;
  $gcash_deposit_count_amount      = $gcash_deposit_sql_result[1]->deposit_sum;
  $gcash_deposit_count_amount_html = money_format('%i', $gcash_deposit_count_amount);
  // 現金提款次數及金額
  $gcash_withdrawal_count             = $gcash_withdrawal_sql_result[1]->amount_count;
  $gcash_withdrawal_count_amount      = $gcash_withdrawal_sql_result[1]->amount_sum;
  $gcash_withdrawal_count_amount_html = money_format('%i', $gcash_withdrawal_count_amount);

  //提款次數 金額
  // $deposit_withdrawal_info_html = $tr['deposit number'].$deposit_count.$tr['deposit/withdraw number follow'].$deposit_count_amount_html.$tr['dollars'].
  // '；'.$tr['withdraw number'].$withdrawal_count.$tr['deposit/withdraw number follow'].$withdrawal_count_amount.$tr['dollars'];

  // 現金存提款次數.金額 html
  // $tr['Number of cash deposits'] = '加盟金存款次數';
  // $tr['Number of cash withdrawals'] = '加盟金提款次數';
  // $tr['times，summation'] = '次，共';
  $cash_deposit_withdrawal_info_html = '
<p class="mb-1">' . $tr['Number of GCASH deposits'] . $gcash_deposit_count . $tr['times，summation'] . $gcash_deposit_count_amount_html . $tr['dollars'] . '</p>
<p>' . $tr['Number of GCASH withdrawals'] . $gcash_withdrawal_count . $tr['times，summation'] . $gcash_withdrawal_count_amount_html . $tr['dollars'] . '</p>
';

  // 代幣存提款次數.金額 html
  $token_deposit_withdrawal_info_html = '
<p class="mb-1">' . $tr['Number of GTOKEN deposits'] . $gtoken_deposit_count . $tr['times，summation'] . $gtoken_deposit_count_amount_html . $tr['dollars'] . '</p>
<p>' . $tr['Number of GTOKEN withdrawals'] . $gtoken_withdrawal_count . $tr['times，summation'] . $gtoken_withdrawal_count_amount_html . $tr['dollars'] . '</p>
';

  // (加盟金)餘額及相關動作 html
  // $tr['Manual deposit GCASH'] = '人工存入加盟金';
  // $tr['Manual withdraw GCASH'] = '人工提出加盟金';
  // $tr['GCASH transactin history'] = '檢視加盟金帳戶交易紀錄';
  // $tr['Go to Manual deposit GCASH page'] = '前往人工存入加盟金頁面';
  // $tr['Go to Manual withdraw GCASH page'] = '前往人工提出加盟金頁面';
  // $tr['Go to GCASH transactin history page'] = '前往檢視加盟金帳戶交易紀錄頁面';

  // --------------------------------------------------------------

    // 目前状态为 -- 狀態中文描述
    //帳號停用
    // $status_desc['0'] = '<span class="label label-danger">'.$tr['account disable'].'</span>';
    //帳號有效
    // $status_desc['1'] = '<span class="label label-success">'.$tr['account valid'].'</span>';
    //錢包凍結
    // $status_desc['2'] = '<span class="label label-warning">'.$tr['account freeze'].'</span>';

    // 目前状态为
    // $listuser_item = $listuser_item.'<tr><td>'.$tr['current status'].'</td>';
    // $listuser_item = $listuser_item.'<td>'.$status_desc[$user->status].'</td>';
    // $listuser_item = $listuser_item.'<td>  </td></tr>';

    // GCASH(现金)余额

    $disable_option = '';
    $valid_option   = '';
    $freeze_option  = '';
    $blocked_option = '';
    $auditing_option = '';
    //暫時補
    switch ($user->status) {
        //停用
        case 0:
            $disable_option = 'selected';
            $before_status  = $tr['account disable'];
            break;
        //錢包凍結
        case 2:
            $freeze_option = 'selected';
            $before_status = $tr['account freeze status'];
            break;
        // 帳號暫時封鎖
        case 3:
           $blocked_option = 'selected';
           $before_status = $tr['blocked'];
           break;
        // 帳號審核中
        case 4:
            $auditing_option = 'selected';
            $before_status = $tr['auditing'];
            break;

        default:
            $valid_option  = 'selected';
            $before_status = $tr['account valid'];
            break;
    }

  //帳號有效  錢包凍結
  if ( $user->status == 1 || $user->status == 2 ) {
  //如果帳號不是有效就關閉 存入現金 提出現金 功能
  $user_balance_cash_link = '
  <a href="member_depositgcash.php?a=' . $user->id . '"  class="btn btn-secondary mb-2 text-white btn_load" title=' . $tr['Go to Manual deposit GCASH page'] . '><span class="glyphicon glyphicon-link mr-2" aria-hidden="true"></span>' . $tr['Manual deposit GCASH'] . '</a>
  <a href="member_withdrawalgcash.php?a=' . $user->id . '"  class="btn btn-secondary mb-2 text-white btn_load" title=' . $tr['Go to Manual withdraw GCASH page'] . '><span class="glyphicon glyphicon-link mr-2" aria-hidden="true"></span>' . $tr['Manual withdraw GCASH'] . '</a>
  ';
  }else{
    $user_balance_cash_link = '
    <button  class="btn btn-secondary mb-2 text-white" title=' . $tr['Go to Manual deposit GCASH page'] . ' disabled><span class="glyphicon glyphicon-link mr-2" aria-hidden="true"></span>' . $tr['Manual deposit GCASH'] . '</button>
    <button  class="btn btn-secondary mb-2 text-white" title=' . $tr['Go to Manual withdraw GCASH page'] . ' disabled><span class="glyphicon glyphicon-link mr-2" aria-hidden="true"></span>' . $tr['Manual withdraw GCASH'] . '</button>
    ';
  }

  //帳號有效
  if ( $user->status == 1) {
    // 帐户余额 標題提示 如果狀態不能使用則顯示提醒
  $alert_status_html = '';
  }else if ( $user->status == 2 ){
    //  錢包凍結
    $alert_status_html = '
    <div class="alert alert-danger d-flex alert_status p-1 ml-2" role="alert">
    '. $before_status .'
    </div>
    ';
  }else{
    //其他狀態
    $alert_status_html = '
    <div class="alert alert-danger d-flex alert_status p-1 ml-2" role="alert">
    '.$tr['this feature is currently unavailable for member status'].' ( ' . $before_status .' )
    </div>
    ';
  }

  // $tr['Go GCash transfer GToken set'] = '前往现金转游戏币设定';
  $user_balance_cash_html = '
  <tr>
  <th class="cashtoke_th">GCASH' . $tr['cash balances'] . '</th>
  <td>
    ' . $gcash_balance_html . '
    ' . $cash_deposit_withdrawal_info_html . '
    ' . $user_balance_cash_link . '
    <a href="member_transactiongcash.php?a=' . $user->id . '" class="btn btn-secondary mb-2 text-white btn_load"  title=' . $tr['Go to GCASH transactin history page'] . '><span class="glyphicon glyphicon-link mr-2" aria-hidden="true"></span>' . $tr['GCASH transactin history'] . '</a>
    <a href="member_gcash2gtoken.php?a=' . $user->id . '" class="btn btn-secondary mb-2 text-white btn_load"  title=' . $tr['Go GCash transfer GToken set'] . '><span class="glyphicon glyphicon-link mr-2" aria-hidden="true"></span>' . $tr['GCash transfer GToken set'] . '</a>
  </td>
</tr>';

  // (現幣)餘額及相關動作 html
  // $tr['Manual deposit GTOKEN'] = '人工存入現金';
  // $tr['Withdraw GTOKEN'] = '人工提出現金';
  // $tr['GTOKEN trans history'] = '檢視現金帳戶交易紀錄';
  // $tr['Go to Manual deposit GTOKEN page'] = '前往人工存入現金頁面';
  // $tr['Go to Manual withdraw GTOKEN page'] = '前往人工提出現金頁面';
  // $tr['Go to GTOKEN transaction history page'] = '前往檢視現金帳戶交易紀錄頁面';
  // $tr['Show GToken Audit'] = '檢視代幣即時稽核';
  // $tr['Go GToken Audit Page'] = '前往檢視代幣即時稽核';

  //GTOKEN(游戏币)余额
  //帳號有效 錢包凍結
  if ( $user->status == 1 || $user->status == 2) {
    $user_balance_token_link = '
    <a href="member_depositgtoken.php?a=' . $user->id . '" class="btn btn-secondary mb-2 text-white btn_load"  title=' . $tr['Go to Manual deposit GTOKEN page'] . '><span class="glyphicon glyphicon-link mr-2" aria-hidden="true"></span>' . $tr['Manual deposit GTOKEN'] . '</a>
    <a href="member_withdrawalgtoken.php?a=' . $user->id . '" class="btn btn-secondary mb-2 text-white btn_load"  title=' . $tr['Go to Manual withdraw GTOKEN page'] . '><span class="glyphicon glyphicon-link mr-2" aria-hidden="true"></span>' . $tr['Manual withdraw GTOKEN'] . '</a>
    <a href="token_auditorial.php?a=' . $user->id . '" class="btn btn-secondary mb-2 text-white btn_load"  title=' . $tr['Go GToken Audit Page'] . '><span class="glyphicon glyphicon-link mr-2" aria-hidden="true"></span>' . $tr['Show GToken Audit'] . '</a>
    ';
  }else{
    $user_balance_token_link = '
    <button class="btn btn-secondary mb-2 text-white"  title=' . $tr['Go to Manual deposit GTOKEN page'] . ' disabled><span class="glyphicon glyphicon-link mr-2" aria-hidden="true"></span>' . $tr['Manual deposit GTOKEN'] . '</button>
    <button class="btn btn-secondary mb-2 text-white"  title=' . $tr['Go to Manual withdraw GTOKEN page'] . ' disabled><span class="glyphicon glyphicon-link mr-2" aria-hidden="true"></span>' . $tr['Manual withdraw GTOKEN'] . '</button>
    <button class="btn btn-secondary mb-2 text-white"  title=' . $tr['Go GToken Audit Page'] . ' disabled><span class="glyphicon glyphicon-link mr-2" aria-hidden="true"></span>' . $tr['Show GToken Audit'] . '</button>
    ';
  }
  $user_balance_token_html = '
  <tr>
    <th class="cashtoke_th">GTOKEN' . $tr['token balances'] . '</th>
    <td>
      ' . $gtoken_balance_html . '
      '. $token_deposit_withdrawal_info_html .'
      ' . $user_balance_token_link . '
      <a href="member_transactiongtoken.php?a=' . $user->id . '" class="btn btn-secondary mb-2 text-white btn_load"  title=' . $tr['Go to GTOKEN transaction history page'] . '><span class="glyphicon glyphicon-link mr-2" aria-hidden="true"></span>' . $tr['GTOKEN transaction history'] . '</a>
     </td>
  </tr>
';

  // 檢視娛樂城中的錢包及其他動作 html
  // $tr['GCash transfer GToken set'] = '加盟金轉現金設定';
  // $tr['Transfer and recycle wallet'] = '檢視娛樂場中的錢包';
  // $tr['Go show casino wallet'] = '前往檢視娛樂城中的錢包';
  $user_balance_other_html = '
  <tr>
    <th  class="pr-5">' . $tr['token to casino'] . '</th>
    <td>
      ' . $gtoken_lock_html . '
      <a href="member_wallets.php?a=' . $user->id . '" class="btn btn-secondary btn_load mb-2 text-white"  title=' . $tr['Go show casino wallet'] . '><span class="glyphicon glyphicon-link mr-2" aria-hidden="true"></span>' . $tr['Transfer and recycle wallet'] . '</a>
    </td>
  </tr>';

  // $listuser_item = $listuser_item . '<td>' . $user_balance_html . '</td>';
  // $listuser_item = $listuser_item . '<td></td></tr>';

  // 帳戶餘額相關動作 button 事件 js
  // 跳轉對應動作頁面
  /*
  $extend_js = $extend_js . "
  <script>
  $('.account_balances_action_btn').click(function(){
  var url = $(this).val();
  window.location.href=url;
  });
  </script>
  ";
   */
  // --------------------------------------------------------------------------


    // --------------------------------------------------------------------------
    // 備註(管理用途，使用者、代理商無法看見)
    // --------------------------------------------------------------------------
    // if($_SESSION['agent']->therole == 'R'){
    //     $usernote_html = $user->notes;
    // }else{
    //     $usernote_html = 'XX';
    // }

    // 处理资讯紀錄
    // $tr['update'] = '更新';

    //備註標題
    $usernote_title = '
    <div class="row">
      <div class="col-12"><h5 class="font-weight-bold">' . $tr['note for manager'] . '</h5></div>
    </div>
    ';

    //備註內容
    // 備註(管理用途，使用者無法看見)
    // $tr['Notice for manager'] = '管理用途，使用者無法看見';
    $usernote_item = '
    <div class="row mb-4">
    <div class="col-12">
      <div class="form-group">
        <form class="form-horizontald" role="form" id="member_note">
          <textarea class="form-control validate[maxSize[500]]" rows="5" maxlength="500" id="notes_common" placeholder="('.$tr['max'].'500'.$tr['word'].')">' . $user->notes . '</textarea><br>
        </form>
        <button type="button" class="btn btn-success d-flex ml-auto" id="notes_common_update">' . $tr['update'] . '</button>
      </div>
      <div id="update_notes"></div>
    </div>
    </div>
    ';

    $extend_js = $extend_js . "
    <script>
    $(document).ready(function(){
      //按鈕跳頁 load
      $('.btn_load').click(function(){
        $(this).attr('style','opacity:0.8');
        $(this).append('<span class=\"fa-1x ml-2\"><i class=\"fas fa-spinner fa-spin\"></i></span>');
      });
    $('#notes_common_update').click(function(){
      $('#notes_common_update').attr('disabled', 'disabled');
      var r = confirm('". $tr['determine whether to update notes'] ."');
      var notes = $('#notes_common').val();
      if(r == true){
        $.post('member_action.php?a=notes_common_update',
          {
            pk: " . $user->id . ",
            notes: notes
          },
          function(result){
            $('#update_notes').html(result);
          }
        )
      }else{
        window.location.reload();
      }
    });

    });
    </script>
    ";

    $listuser_item = $listuser_item . '
      <tr>
        <td colspan="2" class="border-top-0">
          ' . $account_information_title . '
          <div class="row mb-4">
            <div class="col-12 col-lg-6">
              <table class="table border-bottom mb-0 mb-lg-3">
                <tbody>
                  ' . $account_information_item . '
                </tbody>
              </table>
            </div>
            <div class="col-12 col-lg-6 border_control">
              <table class="table border-bottom">
                <tbody>
                  ' . $account_informationtwo_item . '
                </tbody>
            </table>
            </div>
          </div>

          <div class="row">
            <div class="col-12  d-flex">
              <h5 class="font-weight-bold d-flex">
                ' . $tr['Account Balance'] . '
              <h5>
              ' . $alert_status_html .  '
            </div>
          </div>

          <div class="row">
            <div class="col-12 col-lg-6">
              <table class="table border-bottom mb-lg-0 border-bottom-0">
                <tbody>
                  ' . $user_balance_cash_html . '
                </tbody>
              </table>
            </div>
            <div class="col-12 col-lg-6">
              <table class="table border-bottom border-bottom-0">
                <tbody>
                ' . $user_balance_token_html . '
                </tbody>
            </table>
            </div>
          </div>

          <div class="row mb-4">
            <div class="col-12">
              <table class="table border-bottom cash_casiontable">
                <tbody>
                  ' . $user_balance_other_html . '
                </tbody>
              </table>
            </div>
          </div>

          ' . $bank_account_title  . '

        <div class="row mb-4">
          <div class="col-12 col-lg-6">
            <table class="table border-bottom mb-0 mb-lg-3">
              <tbody>
                ' . $bank_account_item . '
              </tbody>
            </table>
          </div>
          <div class="col-12 col-lg-6 border_control">
            <table class="table border-bottom">
              <tbody>
              ' . $bank_accounttwo_item . '
              </tbody>
          </table>
          </div>
        </div>

          ' . $sign_in_title . '

          <div class="row mb-4">
            <div class="col-12 col-lg-6">
              <table class="table border-bottom mb-0 mb-lg-3">
                <tbody>
                ' . $sign_intwo_item . '
                </tbody>
              </table>
            </div>
            <div class="col-12 col-lg-6 border_control">
              <table class="table border-bottom">
                <tbody>
                ' . $sign_in_item . '
                </tbody>
            </table>
            </div>
          </div>

          ' . $sign_up_title . '

          <div class="row mb-4">
            <div class="col-12 col-lg-6">
              <table class="table border-bottom mb-0 mb-lg-3">
                <tbody>
                  ' . $sign_up_item . '
                </tbody>
              </table>
            </div>
            <div class="col-12 col-lg-6 border_control">
              <table class="table border-bottom">
                <tbody>
                  ' . $sign_uptwo_item . '
                </tbody>
            </table>
            </div>
          </div>

          ' . $usernote_title . '
          ' . $usernote_item . '
        </td>
      </tr>
        ';

    // --------------------------------------------------------------------------
    // 會員變更上層推薦人
    // --------------------------------------------------------------------------

    // 只能變更會員, 不能變更代理的上一層. 以免發生問題
    if ($user->therole == "M") {
        $change_parent_disabled         = '';
        $submit_change_placeholder_html = '<a href="#" id="submit_change_placeholder" class="btn btn-success w-100 mt-2">'.$tr['Submit'].'</a>';
        $extend_js                      = $extend_js . "
    <script>
     $(document).ready(function() {
       $('#submit_change_placeholder').click(function() {

         var parent = $('#parent').val();
         var pk = '" . $user->id . "';

         var message = '" . $tr['Sure member'] . $user->account . $tr['Confirm change recommender']. $tr['Confirm change recommender'] ."' + parent + ' ？';

         if(jQuery.trim(parent) != '') {
           if(confirm(message)) {
             $.post('member_action.php?a=change_parent',
             {
               parent: parent,
               pk: pk
             },
             function(result) {
               $('#account_check_result').html(result);
             });
           }
         } else {
           alert('".$tr['Please enter the upper levels recommender to be modified'] ."');
         }
       });

       $('#parent').click(function() {
         var parent = $('#parent').val();

         $.post('member_action.php?a=agent_check',
         {
           parent: parent
         },
         function(result) {
           $('#account_check_result').html(result);
         });
       });
     });
    </script>
   ";

    } else {
        $change_parent_disabled = 'disabled';
        $submit_change_placeholder_html = '';
        $placeholder_msg = "{$placeholder_msg}&nbsp;&nbsp;({$tr['Only the membership can change the referrer']})";
    }
    $levelsrecommender_item = <<<HTML
        <div class="row">
            <div class="col-12">
                <label>{$tr['Member changes the upper levels recommender']}</label>
            </div>
            <div class="col-12">
                <div class="form-group" title="{$placeholder_msg}" {$change_parent_disabled}>
                    <input type="text" class="form-control" id="parent" placeholder="{$placeholder_msg}" {$change_parent_disabled}>
                    {$submit_change_placeholder_html}
                    <span id="account_check_result"></span>
                </div>
            </div>
        </div>
    HTML;

// --------------------------------------------------------------

    // 目前状态为 -- 狀態中文描述
    //帳號停用
    // $status_desc['0'] = '<span class="label label-danger">'.$tr['account disable'].'</span>';
    //帳號有效
    // $status_desc['1'] = '<span class="label label-success">'.$tr['account valid'].'</span>';
    //錢包凍結
    // $status_desc['2'] = '<span class="label label-warning">'.$tr['account freeze'].'</span>';

    // 目前状态为
    // $listuser_item = $listuser_item.'<tr><td>'.$tr['current status'].'</td>';
    // $listuser_item = $listuser_item.'<td>'.$status_desc[$user->status].'</td>';
    // $listuser_item = $listuser_item.'<td>  </td></tr>';

    // $disable_option = '';
    // $valid_option   = '';
    // $freeze_option  = '';
    // $blocked_option = '';
    // $auditing_option = '';
    // //暫時補
    // switch ($user->status) {
    //     //停用
    //     case 0:
    //         $disable_option = 'selected';
    //         $before_status  = $tr['account disable'];
    //         break;
    //     //錢包凍結
    //     case 2:
    //         $freeze_option = 'selected';
    //         $before_status = $tr['account freeze'];
    //         break;
    //     // 帳號暫時封鎖
    //     case 3:
    //        $blocked_option = 'selected';
    //        $before_status = $tr['blocked'];
    //        break;
    //     // 帳號審核中
    //     case 4:
    //         $auditing_option = 'selected';
    //         $before_status = $tr['auditing'];
    //         break;

    //     default:
    //         $valid_option  = 'selected';
    //         $before_status = $tr['account valid'];
    //         break;
    // }

    /*
    根據選單內容自動調整下拉選單長度
    參考: https://stackoverflow.com/questions/28835147/bootstrap-select-width-content-too-wide-when-resizing-window
     */
    if( $auditing_option != 'selected' ) {
      $auditing_disabled = '';
      $auditing_html = '';
    }else{
      $auditing_disabled = 'disabled';
      $auditing_html = '<option ' . $auditing_option . ' disabled>' . $tr['auditing'] . '</option>';
    }
    $current_status = '
    <div class="row">
      <div class="col-12"><label>' . $tr['current status'] . '</label></div>
      <div class="col-12 form-group">
        <select class="form-control" id="mamber_status_select" '.$auditing_disabled.'>
          <option ' . $disable_option . '>' . $tr['account disable'] . '</option>
          <option ' . $valid_option . '>' . $tr['account valid'] . '</option>
          <option ' . $freeze_option . '>' . $tr['account freeze'] . '</option>
          <option ' . $blocked_option . '>' . $tr['blocked'] . '</option>
          '.$auditing_html.'
        </select>
      </div>
    </div>
    ';

    /*
    如果選擇的狀態和 DB 目前狀態一樣就不做任何動作
    不多做這個判斷的話, 使用者只要點擊下拉選單就會觸動這個 js post 到 action
     */
    // $tr['Illegal test'] = '(x)不合法的測試。';
    // $tr['Sure member'] = '確定要將會員：';
    // $tr['Account status change'] = '帳號狀態變更為：';
    $extend_js = $extend_js . "
    <script>
        $(document).ready(function() {
            $('#mamber_status_select').change(function() {
                var status_name = $(this).val();
                var before_status = '" . $before_status . "';
                var pk = '" . $user->id . "';
                var message = ' " . $tr['Sure member'] . $user->account . $tr['Account status change'] . "  ' + status_name + ' ？';

                if(jQuery.trim(status_name) != '') {
                    if(before_status != status_name) {
                        if(confirm(message)) {
                            $.post('member_action.php?a=change_member_status',
                            {
                                status_name: status_name,
                                pk: pk
                            },
                            function(result) {
                                $('#preview_result').html(result);
              });
                        } else {
                            window.location.reload();
                        }
                    }
                } else {
                    alert('" . $tr['Illegal test'] . "');
                }
            });
        });
  </script>
    ";

    // member grade 會員等級的名稱
    // -------------------------------------
    $grade_sql   = "SELECT * FROM root_member_grade;";
    $graderesult = runSQLALL($grade_sql);
    // var_dump($graderesult);

    $member_grade_optine = '';
    $before_grade        = '';
    if ($graderesult[0] >= 1) {
        for ($i = 1; $i <= $graderesult[0]; $i++) {
            // $gradelist[$graderesult[$i]->id] = $graderesult[$i];
            if ($user->grade == $graderesult[$i]->id) {
                $before_grade        = $graderesult[$i]->gradename;

                //若該帳號會員等級為"關閉"狀態，則在選單中顯示(停用)
                $member_grade_optine = ($graderesult[$i]->status == '1')
                                    ?$member_grade_optine . '<option value="'.$graderesult[$i]->gradename.'" selected>' . $graderesult[$i]->gradename . '</option>'
                                    :$member_grade_optine . '<option value="'.$graderesult[$i]->gradename.'" selected>'.'('.$tr['n'].')' . $graderesult[$i]->gradename . '</option>';
            } else if($graderesult[$i]->status == '1'){
              $member_grade_optine = $member_grade_optine . '<option value="'.$graderesult[$i]->gradename.'">' . $graderesult[$i]->gradename . '</option>';
            }
        }
        // $gradelist[NULL] = $graderesult[1];
        // var_dump($gradelist);
    } else {
        // $tr['Member Level have not data in database'] = '會員等級資料表格尚未設定。';
        $logger = $tr['Member Level have not data in database'];
        die($logger);
    }
    // -------------------------------------

    // 会员等级
    $member_grade = '
    <div class="row">
      <div class="col-12"><label>' . $tr['member grade'] . '</label></div>
      <div class="col-12 form-group">
        <select class="form-control" id="mamber_grade_select">
          ' . $member_grade_optine . '
        </select>
      </div>
    </div>';
    // $tr['Sure member'] = '確定要將會員：';
    // $tr['Membership level change'] = ' 帳號等級變更為 : ';
    // $tr['Illegal test'] = '(x)不合法的測試。';
    $extend_js = $extend_js . "
    <script>
        $(document).ready(function() {
            $('#mamber_grade_select').click(function() {

                var grade_name = $(this).val();
                var before_grade = '" . $before_grade . "';
                var pk = '" . $user->id . "';
                var message = '" . $tr['Sure member'] . $user->account . $tr['Membership level change'] . "' + grade_name + ' ？';

                if(jQuery.trim(grade_name) != '') {
                    if(before_grade != grade_name) {
                        if(confirm(message)) {
                            $.post('member_action.php?a=change_mamber_grade',
                            {
                                grade_name: grade_name,
                                pk: pk
                            },
                            function(result) {
                                $('#preview_result').html(result);
                            });
                        } else {
                            window.location.reload();
                        }
                    }
                } else {
                    alert('" . $tr['Illegal test'] . "');
                }
            });
        });
  </script>
    ";

    $preferential_calculation__sql = "SELECT DISTINCT group_name, name, status FROM root_favorable WHERE deleted = '0';";
    // var_dump($preferential_calculation__sql);
    $preferential_calculation_sql_result = runSQLall($preferential_calculation__sql);
    // var_dump($preferential_calculation_sql_result);

    $member_preferential_calculation_optine = '';
    $before_preferential                    = '';
    if ($preferential_calculation_sql_result[0] >= 1) {
        for ($i = 1; $i <= $preferential_calculation_sql_result[0]; $i++) {
            // $preferential_calculation_namelist[$preferential_calculation_sql_result[$i]->id] = $preferential_calculation_sql_result[$i];
            if($preferential_calculation_sql_result[$i]->status == '0'){
              $del_html = '('.$tr['n'].')';
            }else{
              $del_html = '';
            }
            if ($user->favorablerule == $preferential_calculation_sql_result[$i]->name) {
                $before_preferential                    = $preferential_calculation_sql_result[$i]->name;
                $member_preferential_calculation_optine = $member_preferential_calculation_optine . '
                <option value="' . $preferential_calculation_sql_result[$i]->name . '" selected>' .$del_html. $preferential_calculation_sql_result[$i]->group_name.'</option>
                ';
            } else {
                $member_preferential_calculation_optine = $member_preferential_calculation_optine . '
                <option value="' . $preferential_calculation_sql_result[$i]->name . '">'.$del_html . $preferential_calculation_sql_result[$i]->group_name . '</option>
                ';
            }
        }
        // $gradelist[NULL] = $graderesult[1];
        // var_dump($gradelist);
    } else {
        // $tr['Member Level have not data in database'] = '會員等級資料表格尚未設定。';
        $logger = $tr['Member Level have not data in database'];
        die($logger);
    }
    // -------------------------------------

    // 反水设定为
    $favorable_html = '
    <div class="row">
    <div class="col-12"><label>' . $tr['bonus setting'] . '</label></div>
    <div class="col-12">
      <div class="form-group">
        <select class="form-control"  id="mamber_preferential_select">
          ' . $member_preferential_calculation_optine . '
        </select>
      </div>
    </div>
    </div>
    ';
    // $tr['Sure member'] = '確定要將會員：';
    // $tr['Membership bonus change'] = ' 帳號反水等級變更為 : ';
    // $tr['Illegal test'] = '(x)不合法的測試。';
    $extend_js = $extend_js . "
    <script>
    $(document).ready(function() {
        $('#mamber_preferential_select').click(function() {

            var preferential_name = $(this).val();
            var before_preferential = '" . $before_preferential . "';
            var pk = '" . $user->id . "';
            var message = '" . $tr['Sure member'] . $user->account . $tr['Membership bonus change'] . " ' + preferential_name + ' ？';

            if(jQuery.trim(preferential_name) != '') {
                if(before_preferential != preferential_name) {
                    if(confirm(message)) {
                        $.post('member_action.php?a=change_mamber_preferential_name',
                        {
                            preferential_name: preferential_name,
                            pk: pk
                        },
                        function(result) {
                            $('#preview_result').html(result);
                        });
                    } else {
                        window.location.reload();
                    }
                }
            } else {
                alert('" . $tr['Illegal test'] . "');
            }
        });
    });
  </script>
    ";

    $commission_sql = "SELECT DISTINCT group_name, name FROM root_commission WHERE deleted = '0';";
    // var_dump($commission_sql);
    $commission_sql_result = runSQLall($commission_sql);
    // var_dump($commission_sql_result);

    $commission_optine = '';
    $before_commission = '';
    if ($commission_sql_result[0] >= 1) {
        for ($i = 1; $i <= $commission_sql_result[0]; $i++) {
            // $preferential_calculation_namelist[$preferential_calculation_sql_result[$i]->id] = $preferential_calculation_sql_result[$i];
            if ($user->commissionrule == $commission_sql_result[$i]->name) {
                $before_commission = $commission_sql_result[$i]->name;
                $commission_optine = $commission_optine . '
                <option value="' . $commission_sql_result[$i]->name . '" selected>' . $commission_sql_result[$i]->group_name . '</option>
                ';
            } else {
                $commission_optine = $commission_optine . '
                <option value="' . $commission_sql_result[$i]->name . '">' . $commission_sql_result[$i]->group_name . '</option>
                ';
            }
        }
        // $gradelist[NULL] = $graderesult[1];
        // var_dump($gradelist);
    } else {
        $logger = $tr['commissions not set'];
        die($logger);
    }

    $commission_isdisabled = ($user->therole == 'M') ? 'disabled' : '';

    // 佣金設定為
    $commission_item = '
    <div class="row">
    <div class="col-12"><label>' . $tr['Commission setting'] . '</label></div>
    <div class="col-12 form-group">
        <select class="form-control"  id="commission_select" ' . $commission_isdisabled . '>
          ' . $commission_optine . '
        </select>
      </div>
    </div>
    ';

    $extend_js = $extend_js . "
    <script>
    $(document).ready(function() {
        $('#commission_select').click(function() {
            var commission_name = $(this).val();
            var before_commission = '" . $before_commission . "';
            var pk = '" . $user->id . "';

            var message = '" .$tr['Sure member'] . $user->account . $tr['account and commissions']."？';

            if(jQuery.trim(commission_name) != '') {
                if(before_commission != commission_name) {
                    if(confirm(message)) {
                        $.post('member_action.php?a=change_mamber_commission_name',
                        {
                            commission_name: commission_name,
                            pk: pk
                        },
                        function(result) {
                            $('#preview_result').html(result);
                        });
                    } else {
                        window.location.reload();
                    }
                }
            } else {
                alert('" . $tr['Illegal test'] . "');
            }
        });
    });
  </script>
    ";

    // --------------------------------------------------------------------------

    // --------------------------------------------------------------------------
    // 管理權限 -- 搭配管理用的函式. 由令開始視窗的程式設定
    // --------------------------------------------------------------------------
    //$userpermission_html = $user->permission;
    //var_dump($user->permission);
    // $userpermission_html = '<a class="btn btn-link" href="member_permission.php" role="button"><span class="glyphicon glyphicon-link" aria-hidden="true"></span>會員及加盟聯營股東管理權限設定</a>';
    /*
    $userpermission_html = '<a class="btn btn-link" href="member_permission.php" role="button"><span class="glyphicon glyphicon-link" aria-hidden="true"></span>會員及加盟聯營股東管理權限設定</a>';
    // 管理權限
    $listuser_item = $listuser_item . '
    <tr>
    <td>' . $tr['Administrator right'] . '</td>
    <td>' . $userpermission_html . '</td>
    </tr>
    ';
     */
    // $listuser_item = $listuser_item . '<td>' . $userpermission_html . '</td>';
    // $tr['TODO not complete'] = 'TODO尚未完成';
    // $listuser_item = $listuser_item . '<td></td></tr>';
    // --------------------------------------------------------------------------

    // --------------------------------------------------------------------------
    // 下1代會員及數量:  列出本身下面直接 第一線 會員數量 count , 及列出會員可以提供點擊查詢
    // --------------------------------------------------------------------------

    // --------------------------------------------------------------------------
    // 會員組織圖列表, 顯示代理商底下三代的人數。
    // $tree_string = '';

    // // $sql_M = "SELECT id,account,parent_id, therole FROM root_member WHERE parent_id = $user->id;";
    // $sql_M = "SELECT *,enrollmentdate, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD  HH24:MI:SS') as enrollmentdate_tz FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.parent_id = $user->id;";
    // $sql_M_result = runSQLALL($sql_M);
    // // var_dump($sql_M_result);
    // //有多少個會員
    // $tree_string = $tr['have'] . '<span class="badge">' . $sql_M_result[0] . '</span>' . $tr['member number'] . '<span class="glyphicon glyphicon-user" aria-hidden="true">。</span>';

    // $sql_A = "SELECT id,account,parent_id, therole FROM root_member WHERE therole = 'A' AND parent_id = $user->id;";
    // $sql_A_result = runSQLALL($sql_A);

    // //其中有多少個代理商
    // $tree_string = $tree_string . $tr['with how many'] . '<span class="badge">' . $sql_A_result[0] . '</span>' . $tr['agent number'] . '<span class="glyphicon glyphicon-knight" aria-hidden="true"></span>)';

    // // 代理商數量 $sql_A_result[0]
    // // 會員數量 $sql_M_result[0]

    // // --------------------------------------------------------------------------
    // // 目前會員的：第 1 代會員列表取出 - 下1代會員及數量
    // // --------------------------------------------------------------------------
    // // var_dump($sql_M_result);
    // if ($sql_M_result[0] >= 1) {
    //     // 狀態中文描述
    //     //停用=0 有效=1 錢包凍結=2
    //     $status_desc['0'] = '<span class="label label-danger">' . $tr['Wallet Disable'] . '</span>';
    //     $status_desc['1'] = '<span class="label label-success">' . $tr['Wallet Valid'] . '</span>';
    //     $status_desc['2'] = '<span class="label label-warning">' . $tr['Wallet Freeze'] . '</span>';

    //     $treedata_table_row = '';
    //     for ($i = 1; $i <= $sql_M_result[0]; $i++) {
    //         // 列表中顯示使用者的身份狀態
    //         //var_dump($M_value);
    //         if ($sql_M_result[$i]->therole == 'A') {
    //             $item_mark = '<a href="" title="Agent User"><span class="glyphicon glyphicon-knight" aria-hidden="true"></span></a>';
    //         } elseif ($sql_M_result[$i]->therole == 'M') {
    //             $item_mark = '<a href="" title="Member User"><span class="glyphicon glyphicon-user" aria-hidden="true"></span></a>';
    //         } elseif ($sql_M_result[$i]->therole == 'R') {
    //             $item_mark = '<a href="" title="Customer Service"><span class="glyphicon glyphicon-king" aria-hidden="true"></span></a>';
    //         } elseif ($sql_M_result[$i]->therole == 'T') {
    //             $item_mark = '<a href="" title="Trial User"><span class="glyphicon glyphicon-user" aria-hidden="true"></span></a>';
    //         } else {
    //             $item_mark = '<span class="glyphicon glyphicon-remove" aria-hidden="true"></span></a>';
    //             var_dump($sql_M_result[$i]->therole);
    //             die('下線使用者帳號有問題，請聯絡客服人員處理。');
    //         }

    //     // 显示选择比例的画面
    //         if ($sql_M_result[$i]->therole == 'A') {
    //             $commission_grade_select_option = '';
    //             for ($j=0.1; $j<=0.99; $j=$j+0.01) {
    //         $aff_up   = round((1-$j)*100,0);
    //         $aff_down = round($j*100,2);

    //                 if ($sql_M_result[$i]->dividendratio == (string)$j) {
    //                     $commission_grade_select_option = $commission_grade_select_option.'<option value="'.$j.'" id="'.$sql_M_result[$i]->id.'" selected>'.$aff_up.':'.$aff_down.'</option>';
    //                 } else {
    //                     $commission_grade_select_option = $commission_grade_select_option.'<option value="'.$j.'" id="'.$sql_M_result[$i]->id.'">'.$aff_up.':'.$aff_down.'</option>';
    //                 }
    //             }

    //             $option_html = '
    //             <div class="form-inline">
    //                 <div class="form-group">
    //                     <select class="form-control form-control-sm"  id="'.$sql_M_result[$i]->id.'">
    //                         '.$commission_grade_select_option.'
    //                     </select>
    //                 </div>
    //                 <button id="'.$sql_M_result[$i]->account.'" class="btn btn-success btn-sm commission_select_btn" value="'.$sql_M_result[$i]->id.'"><span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span>&nbsp;儲存</button>
    //             </div>
    //             ';
    //         } else {
    //             $option_html = '';
    //         }

    //         $treedata_table_row = $treedata_table_row . '
    //         <tr>
    //             <td>' . $sql_M_result[$i]->id . '</td>
    //             <td><a href="member_account.php?a=' . $sql_M_result[$i]->id . '">' . $sql_M_result[$i]->account . '</a></td>
    //             <td><a href="#" title="RAW&nbsp;' . $sql_M_result[$i]->enrollmentdate . '">' . $sql_M_result[$i]->enrollmentdate_tz . '</a></td>
    //             <td>' . $item_mark . '</td>
    //             <td>' . $status_desc[$sql_M_result[$i]->status] . '</td>
    //             <td><a href="member_transactiongcash.php?a=' . $sql_M_result[$i]->id . '" title="查詢會員 ' . $sql_M_result[$i]->account . ' 現金帳戶存摺">' . $sql_M_result[$i]->gcash_balance . '</a></td>
    //             <td><a href="member_transactiongtoken.php?a=' . $sql_M_result[$i]->id . '" title="查詢會員 ' . $sql_M_result[$i]->account . ' 代幣帳戶存摺">' . $sql_M_result[$i]->gtoken_balance . '</a></td>
    //             <td>'.$option_html.'</td>
    //             </tr>
    //         ';
    //     }

    //     // 下線會員列表的整齊一點
    //     $tree_table_html = '
    //         <table class="table table-striped">
    //         <tr class="info">
    //             <td>' . $tr['ID'] . '</td>
    //             <td>' . $tr['Account'] . '</td>
    //             <td>' . $tr['Enrollment date'] . '(' . $tz . ')</td>
    //             <td>' . $tr['The Role'] . '</td>
    //             <td>' . $tr['State'] . '</td>
    //             <td>' . $tr['Cash Balance'] . '</td>
    //             <td>' . $tr['Token Balance'] . '</td>
    //             <td>拆佣比(上層加盟联营股东:加盟联营股东)</td>
    //         </tr>
    //         ' . $treedata_table_row . '
    //         </table>
    //         ';

    //     // 处理加盟联营股东的佣金
    //         $extend_js = $extend_js . "
    //         <script>
    //         $(document).ready(function(){
    //             $('.commission_select_btn').click(function(){
    //                 var pk = $(this).val();
    //                 var acc = $(this).attr('id');
    //                 var commission_percen = $('#'+pk).val();

    //                 var r = confirm('確定是否更新?');
    //                 if(r == true) {
    //                     $.post('member_action.php?a=edit_commission_percen',
    //                         {
    //                             pk: pk,
    //                             acc: acc,
    //                             commission_percen: commission_percen
    //                         },
    //                         function(result){
    //                             $('#preview_result').html(result);
    //                         }
    //                     )
    //                 }
    //             });

    //         });
    //         </script>
    //         ";
    // } else {
    //     // var_dump($user);
    //     if ($user->therole == 'M') {
    //         //需要成為代理商，才能有下線會員。
    //         $tree_table_html = $tr['need agent to have menber'];
    //     } else {
    //         //代理商尚未有下一代會員。
    //         $tree_table_html = $tr['agent with no member'];
    //     }
    // }

    // // 第 1 代會員數量及組織圖
    // $listuser_item = $listuser_item . '
    // <tr>
    //     <td>' . $tr['next generation number'] . '</td>
    //     <td>' . $tree_table_html . '</td>
    // </tr>
    // ';

    // $listuser_item = $listuser_item . '<td>' . $tree_table_html . '</td>';
    // // 說明/動作        //更變代理商
    // $listuser_item = $listuser_item . '<td>'.$tr['change agent'].'</td></tr>';
    // --------------------------------------------------------------------------

    // --------------------------------------------------------------------------
    // 列表方式 欄位 內容 說明/動作
    // --------------------------------------------------------------------------
    $listuser_information = '
  <table class="table member_account_table">
        ' . $listuser_item . '
    </table>
    ';
    // --------------------------------------------------------------------------

    // --------------------------------------------------------------------
    // 條列會員資訊 , 整理成為要輸出的格式
    // --------------------------------------------------------------------
    // 1. title 2. content
    $panelbody_content = $panelbody_content . '
    <div class="row">
        <div class="col-12 col-md-12">
        ' . $listuser_information . '
        </div>
        <div class="col-12 col-md-6">
            <div id="preview_result"></div>
        </div>
    </div>
    ';
    // --------------------------------------------------------------------

} else {

    // ------------------------------------------------------------
    // 檢查 member id 是否存在 root_member 表格內，如果有那就是 wallets 不存在就馬上建立。
    // ------------------------------------------------------------
    /*
    $member_sql = "SELECT * FROM root_member WHERE id = '$account_id';";
    $member_result = runSQLall($member_sql);
    if ($member_result[0] == 1) {
    $userid = $member_result[1]->id;
    // 沒有資料，建立初始資料。
    $member_wallets_addaccount_sql = "INSERT INTO root_member_wallets (id, changetime, gcash_balance, gtoken_balance) VALUES ('" . $userid . "', 'now()', '0', '0');";
    // var_dump($member_wallets_addaccount_sql);
    $rwallets = runSQL($member_wallets_addaccount_sql);
    if ($rwallets == 1) {
    $logger = "$userid " . ',Create root_member_wallets account success!! ';
    //echo $logger;
    memberlog2db($_SESSION['agent']->account, 'member wallet', 'info', "$logger");
    $r['code'] = '1';
    $r['messages'] = $logger;
    } else {
    $logger = "$userid " . ',Create root_member_wallets account false!! ';
    // echo $logger;
    memberlog2db($_SESSION['agent']->account, 'member wallet', 'error', "$logger");
    $r['code'] = '2';
    $r['messages'] = $logger;
    }
    echo '<script>location.reload(true);</scrip>';
    } else {
    $debug_msg = '資料庫系統有問題，請聯絡開發人員處理。';
    die("$debug_msg");
    }
     */
    $debug_msg = $tr['db has problems, please contact the developer to deal with'] . '。';
    die("$debug_msg");
    // ------------------------------------------------------------
}

// end if agent login

  //功能設定改至左方欄位
  $indexbody_content = <<<HTML
  {$current_status}
  {$member_grade}
  {$favorable_html}
  {$commission_item}
  {$levelsrecommender_item}
  {$userid_treemap_html}
HTML;

    // JS 開頭
  $extend_head = $extend_head. <<<HTML
  <script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
  <script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
  <link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>

  <style>
    #member_note .notes_commonformError.parentFormmember_note.formError {
      width: 0 !important;
    }
    .formError .formErrorContent {
      right: 64px;
    }
  </style>

  <script type="text/javascript" language="javascript" class="init">
  $(document).ready(function () {
    $("#member_note").validationEngine();
  });
</script>
HTML;
// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author']      = $tr['host_author'];
$tmpl['html_meta_title']       = $function_title . '-' . $tr['host_name'];

// 頁面大標題
$tmpl['page_title'] = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;

// 兩欄分割--左邊
$tmpl['indextitle_content'] = $indextitle_content;
$tmpl['indexbody_content'] = $indexbody_content;
// 兩欄分割--右邊
$tmpl['paneltitle_content'] = $user->account . $function_title;
$tmpl['panelbody_content'] = $panelbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include "template/s2col.tmpl.php";

?>