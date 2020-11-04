<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 公司入款審核頁面
// File Name:	depositing_company_audit_review.php
// Author:		Barkley
// Related:		對應後台 depositing_company_audit.php
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

if(isset($_GET['id']) and filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $depositing_company_review_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
}else{//$tr['Illegal test'] = '(x)不合法的測試。';
    die($tr['Illegal test']);
}
// var_dump($depositing_company_review_id);

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
// 功能標題，放在標題列及meta //$tr['Company deposit approval page'] = '公司入款審核頁面';
$function_title 		= $tr['Company deposit approval page'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置 $tr['homepage']= '首頁';$tr['Account Management'] = '帳務管理';$tr['Depositing audit company'] = '公司入款審核';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['homepage'].'</a></li>
  <li><a href="#">'.$tr['Account Management'].'</a></li>
  <li><a href="depositing_company_audit.php">'.$tr['Depositing audit company'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($depositing_company_review_id) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {


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

  if($tzone[0]==1){
    $tzonename = $tzone[1]->name;
  }else{
    $tzonename = 'posix/Etc/GMT-8';
  }

  // 搜寻 root_deposit_review 單筆資料
  $depositing_company_sql = "
  SELECT *, to_char((transfertime AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as transfertime_tz
  FROM root_deposit_review
  WHERE id = '$depositing_company_review_id'
  ";

  //var_dump($depositing_company_sql);
  $depositing_company_result = runSQLALL($depositing_company_sql);
  // var_dump($depositing_company_result);die();
  if($depositing_company_result[0] == 0){
    $logger = '查无此公司存款资讯！' ;
    echo $logger;die();
  }

  // 搜寻 root_member 單筆資料
  $depositing_root_member_sql = "SELECT * FROM root_member WHERE account = '".$depositing_company_result[1]->account."' ";
//  var_dump($depositing_root_member_sql);
  $depositing_root_member_result = runSQLALL($depositing_root_member_sql);
//  var_dump($depositing_root_member_result);

  // 產生會員選擇入款的銀行的資料
  $bank_data_sql = "SELECT * FROM root_deposit_company WHERE status = '1' AND id = '".$depositing_company_result[1]->depositcompanyid."' ORDER BY id LIMIT 1;";
//  var_dump($bank_data_sql);
  $bank_data_result = runSQLall($bank_data_sql);
//  var_dump($bank_data_result);

  if($depositing_company_result[0] == 1 AND $depositing_root_member_result[0] == 1){
    if ($bank_data_result[0] >= 1) {
      //分析存款方式↓
      // switch($depositing_company_result[1]->type){
      //   case 'ATM'://$tr['ATM'] = 'ATM自動櫃員機';
      //     $deposit_method = '<span class="label label-info">'.$tr['ATM'].'</span>';
      //     break;
      //   case 'ATMcash'://$tr['ATM cash deposit'] = 'ATM現金入款';
      //     $deposit_method = '<span class="label label-info">'.$tr['ATM cash deposit'].'</span>';
      //     break;
      //   case 'Alipay'://$tr['alipay'] = '支付寶';
      //     $deposit_method = '<span class="label label-info">'.$tr['alipay'].'</span>';
      //     break;
      //   case 'Bankcounters'://$tr['Bank Counter'] = '銀行櫃檯';
      //     $deposit_method = '<span class="label label-info">'.$tr['Bank Counter'].'</span>';
      //     break;
      //   case 'Mobilebanktransfer'://$tr['Mobile Banking Transfer'] = '手機銀行轉帳';
      //     $deposit_method = '<span class="label label-info">'.$tr['Mobile Banking Transfer'].'</span>';
      //     break;
      //   case 'Onlinebanktransfer'://$tr['Internet Banking Transfer'] = '網銀轉帳';
      //     $deposit_method = '<span class="label label-info">'.$tr['Internet Banking Transfer'].'</span>';
      //     break;
      //   default://$tr['other_preferential'] = '其他優惠';
      //     $deposit_method = '<span class="label label-info"'.$tr['other_preferential'].'</span>';
      //     break;
      // }
      $deposit_type = explode('_', $depositing_company_result[1]->type)[0];
      switch($deposit_type){
        case 'bank':
          $deposit_method = '<span class="label label-info">'.$tr['banking transfer'].'</span>';
          break;
        case 'wechat':
          $deposit_method = '<span class="label label-info">'.$tr['scan code payment'].'</span>';
          break;
        case 'virtualmoney':
          $deposit_method = '<span class="label label-info">'.$tr['virtual money payment'].'</span>';
          break;
        default:
          $deposit_method = '<span class="label label-info">'.$tr['error payment'].'</span>';
          break;
      }

      // 會員帳號查驗連結 $tr['Check membership details'] = '檢查會員的詳細資料';
      $member_check_html = '<a href="member_account.php?a='.$depositing_root_member_result[1]->id.'" target="_BLANK" title="'.$tr['Check membership details'].'">'.$depositing_company_result[1]->account.'</a>';

      // 判斷審核的狀態
      if($depositing_company_result[1]->status == 2){
        // $depositing_status_html = "
        // <a href=\"depositing_company_audit_action.php?a=depositing_company_audit_submit&id=".$depositing_company_result[1]->id."\" class=\"btn btn-success btn-sm active\" role=\"button\">同意</a>
        // <a href=\"depositing_company_audit_action.php?a=depositing_company_audit_cancel&id=".$depositing_company_result[1]->id."\" class=\"btn btn-danger btn-sm active\" role=\"button\">取消</a>
        // ";// $tr['agree'] = '同意';// $tr['Cancel'] = '取消';
        $depositing_status_html = "
        <button id=\"agreen_ok\" class=\"btn btn-success btn-sm active\" role=\"button\">".$tr['agree']."</button>
        <button id=\"agreen_cancel\"class=\"btn btn-danger btn-sm active\" role=\"button\">".$tr['disagree']."</button>
        ";
      }else if($depositing_company_result[1]->status == 1){// $tr['Approved'] = '已审核通过';
        $depositing_status_html = "
        <label class=\"label label-warning role=\"label\">".$tr['Approved']."</label>
        ";
      }else if($depositing_company_result[1]->status == 3){// $tr['Approved'] = '上鎖中';
        $depositing_status_html = "
        <label class=\"label label-danger role=\"label\"><span class=\"glyphicon glyphicon-lock\"><span></label>
        ";
      }else{ //$tr['application reject'] = '审核退回';
        $depositing_status_html = "
        <label class=\"label label-danger role=\"label\">".$tr['application reject']."</label>
        ";
      }

      // 列出資料, 主表格架構
      $show_list_tbody_html = '';

      // 会员帐号  // $tr['Member Account'] = '會員帳號';
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['Account'].'</strong></td>
        <td>'.$member_check_html.'</td>
        <td></td>
      </tr>
      ';

      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['Transaction order number'].'</strong></td>
        <td>'.$depositing_company_result[1]->transaction_id.'</td>
        <td></td>
      </tr>
      ';

      // 存入金额 // $tr['deposit amount'] = '存款金額';
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['deposit amount'].'</strong></td>
        <td>￥'.$depositing_company_result[1]->amount.'</td>
        <td></td>
      </tr>
      ';

      if ($deposit_type == 'virtualmoney') {
        $show_list_tbody_html .= '
        <tr>
          <td><strong>虚拟货币转帐金额</strong></td>
          <td>'.$bank_data_result[1]->cryptocurrency.' '.$depositing_company_result[1]->cryptocurrency_amount.'</td>
          <td></td>
        </tr>
        <tr>
          <td><strong>汇率</strong></td>
          <td>￥'.$depositing_company_result[1]->current_exchangerate.'</td>
          <td>提交存款审核时的汇率</td>
        </tr>
        ';
      }

      // 存款人姓名 $tr['depositor name'] = '存款人姓名';
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['depositor name'].'</strong></td>
        <td>'.$depositing_company_result[1]->depositoraccountname.'</td>
        <td></td>
      </tr>
      ';

      // 存款方式 $tr['Deposit method'] = '存款方式';
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['Deposit method'].'</strong></td>
        <td>'.$deposit_method.'</td>
        <td></td>
      </tr>
      ';

      // 申请时间 $tr['application time'] = '申請時間';
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['application time'].'</strong></td>
        <td>'.(gmdate('Y-m-d H:i:s', strtotime($depositing_company_result[1]->changetime) + -4 * 3600)).'</td>
        <td></td>
      </tr>
      ';

      // 入款时间 $tr['Deposit time'] = '入款時間';
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['Deposit time'].'</strong></td>
        <td>'.$depositing_company_result[1]->transfertime_tz.'</td>
        <td></td>
      </tr>
      ';

      // 銀行名稱 $tr['collation bank name'] = '對款銀行名稱';
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['Reconciliation service name'].'</strong></td>
        <td>'.$bank_data_result[1]->companyname.'</td>
        <td></td>
      </tr>
      ';

      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['For account name/ID'].'</strong></td>
        <td>'.$bank_data_result[1]->accountname.'</td>
        <td></td>
      </tr>
      ';

      // 銀行帳號 $tr['collated bank account'] = '對款銀行帳號';
      $accountnumber = ($bank_data_result[1]->type != 'bank') ? '<img id="" src="'.$bank_data_result[1]->accountnumber.'" height="100" width="100">' : $bank_data_result[1]->accountnumber;
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['Reconciliation account / receipt code'].'</strong></td>
        <td>'.$accountnumber.'</td>
        <td></td>
      </tr>
      ';

      // 開戶行所在地 $tr['collated the location of the bank open account'] = '對款銀行開戶行所在地';
      if ($bank_data_result[1]->type == 'bank') {
        $show_list_tbody_html = $show_list_tbody_html.'
        <tr>
          <td><strong>'.$tr['collated the location of the bank open account'].'</strong></td>
          <td>'.$bank_data_result[1]->accountarea.'</td>
          <td></td>
        </tr>
        ';
      }

      // 申請人前台銀行帳戶
      // 申請人前台銀行
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['bankname'].'</strong></td>
        <td>'.$depositing_root_member_result[1]->bankname.'</td>
        <td></td>
      </tr>
      ';

      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['Bank account name'].'</strong></td>
        <td>'.$depositing_root_member_result[1]->bankaccount.'</td>
        <td></td>
      </tr>
      ';


      /*
      銀行名稱:&nbsp;'.$depositing_company_result[1]->companyname.$info_html.'<br>
      銀行帳號:&nbsp;'.$depositing_company_result[1]->accountnumber.$info_html.' <br>
      開戶行所在地:&nbsp;'.$depositing_company_result[1]->accountarea.$info_html.' <br>
      */
      $member_remittance_info_html = '<p>
      '.$depositing_company_result[1]->reconciliation_notes.'<br>
      </p>';
      // 會員匯款帳號對帳資訊 $tr['Member remittance account reconciliation information'] = '會員匯款帳號對帳資訊';
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['account reconciliation information'].'</strong></td>
        <td>'.$member_remittance_info_html.'</td>
        <td></td>
      </tr>
      ';

      // 第三方支付存款人名稱 $tr['Depositor Name'] = '存款人名稱'; $tr['TenPay/Wechat/Alipay-Nickname/Depositor'] = '財付通/微信/支付寶-暱稱/存款人';
      // $show_list_tbody_html = $show_list_tbody_html.'
      // <tr>
      //   <td><strong>'.$tr['Depositor Name'].'</strong></td>
      //   <td>'.$depositing_company_result[1]->accountname.'</td>
      //   <td>'.$tr['TenPay/Wechat/Alipay-Nickname/Depositor'].'/ QQ</td>
      // </tr>
      // ';
      $sns1 = $protalsetting["custom_sns_rservice_1"]??$tr['sns1'];
      $sns2 = $protalsetting["custom_sns_rservice_2"]??$tr['sns2'];

      $contactuser_html = '<p>
      '.$tr['Cell Phone'].': '.$depositing_company_result[1]->mobilenumber.'<br>
      '.$sns1.': '.$depositing_company_result[1]->wechat.'<br>
      '.$tr['email'].': '.$depositing_company_result[1]->email.'<br>
      '.$sns2.': '.$depositing_company_result[1]->qq.'<br>
      </p>';
      // 聯絡方式 $tr['contact method'] = '聯絡方式';
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['contact method'].'</strong></td>
        <td>'.$contactuser_html.'</td>
        <td></td>
      </tr>
      ';
      // $tr['Browser fingerprint'] = '瀏覽器指紋'; $tr['Find out the records in the system'] = '找出曾經在系統內的紀錄';$tr['Query the IP address may be the address'] = '查詢IP來源可能地址位置';
      $geoinfo_html = '<p>
      '.$tr['Browser fingerprint'].': <a href="#" title="TODO:'.$tr['Find out the records in the system'].'" target="_BLANK">'.$depositing_company_result[1]->fingerprinting.'</a><br>
      IP: <a href="http://freeapi.ipip.net/'.$depositing_company_result[1]->applicationip.'" target="_BLANK" title="'.$tr['Query the IP address may be the address'].'">'.$depositing_company_result[1]->applicationip.'</a><br>
      </p>';
      // 地理位置及瀏覽器指紋資訊 $tr['Geographic location and browser fingerprint'] = '地理位置及瀏覽器指紋'; $tr['User Geographic Device Information submitted by withdrawal'] = '提款提交的使用者地理裝置資訊';
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['Geographic location and browser fingerprint'].'</strong></td>
        <td>'.$geoinfo_html.'</td>
        <td>'.$tr['User Geographic Device Information submitted by withdrawal'].'</td>
      </tr>
      ';

      // 對帳處理人員帳號 $tr['Account processing staff account'] = '對帳處理人員帳號';
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['Account processing staff account'].'</strong></td>
        <td>'.$depositing_company_result[1]->processingaccount.'</td>
        <td></td>
      </tr>
      ';

      if(isset($depositing_company_result[1]->processingtime) AND $depositing_company_result[1]->processingtime != null){
        $processingtime = gmdate('Y-m-d H:i:s',strtotime($depositing_company_result[1]->processingtime)-4*3600);
      }else{
        $processingtime = '';
      }
      // 對帳完成的時間 $tr['Reconciliation completed time'] = '對帳完成的時間';
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['Processing time'].'</strong></td>
        <td>'.$processingtime.'</td>
        <td></td>
      </tr>
      ';



      // 处理资讯紀錄 $tr['update'] = '更新';
      $notes_form_html = '
      <div class="form-group">
        <form class="form-horizontald" role="form" id="note">
          <textarea class="form-control validate[maxSize[500]]" rows="5" maxlength="500" id="notes_common" placeholder="('.$tr['max'].'500'.$tr['word'].')">'.$depositing_company_result[1]->notes.'</textarea>
        </form>
        <button type="button" class="btn btn-default btn-sm mt-2" id="notes_common_update">'.$tr['update'].'</button>
      </div>
      ';
      // 处理资讯紀錄  $tr['Processing information record'] = '處理資訊紀錄';
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['Processing information record'].'</strong></td>
        <td colspan="2">'.$notes_form_html.'</td>
      </tr>
      ';
      //$tr['system will automatically transfer the amount'] = '  * 同意，立即更新此紀錄，系統會自動轉入存款金額給客戶。<br>   * 不同意，立即更新此紀錄，設定為審核退回。<br>';
      $submit_desc_html = '<p>'.$tr['system will automatically transfer the amount'].'
      <p>';
      // 审核状态 $tr['Approval Status'] = '審核狀態';
      $show_list_tbody_html = $show_list_tbody_html.'
      <tr>
        <td><strong>'.$tr['Approval Status'].'</strong></td>
        <td>'.$depositing_status_html.'</td>
        <td>'.$submit_desc_html.'</td>
      </tr>
      ';

      // 返回上一页 $tr['go back to the last page'] = '返回上一頁';
      $show_list_return_html = '<p align="right"><a href="depositing_company_audit.php" class="btn btn-success btn-sm active" role="button">'.$tr['go back to the last page'].'</a></p>';

      // 欄位標題 $tr['field'] = '欄位'; $tr['content'] = '內容'; $tr['Remark'] = '備註';
      $show_list_thead_html = '
      <tr>
        <th>'.$tr['field'].'</th>
        <th>'.$tr['content'].'</th>
        <th>'.$tr['Remark'].'</th>
      </tr>
      ';

      // 以表格方式呈現
      $show_list_html = '
      <table class="table">
        <thead>
        '.$show_list_thead_html.'
        </thead>
        <tbody>
        '.$show_list_tbody_html.'
        </tbody>
      </table>
      ';
    } else {//$tr['No company into the account information'] = '(x)查無公司入款帳戶資訊，請至公司入款帳戶管理確認是否開啟。';
      $logger = $tr['No company into the account information'] ;
      echo $logger;die();
    }
  }else{//$tr['This order number has been processed so far, do not re-operate.'] = '目前此訂單號已處理過，請勿重新操作處理。';
    $logger = $tr['This order number has been processed so far, do not re-operate.'] ;
    echo $logger;die();
  }



  // 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
  <div class="row">
		<div class="col-12 col-md-12">
    '.$show_list_html.'
		</div>
	</div>
	<hr>
  '.$show_list_return_html.'
	<div class="row">
		<div id="preview_result"></div>
	</div>
	';
}else{
  // 沒有登入的顯示提示俊息  $tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';
	$show_transaction_list_html  = $tr['only management and login mamber'];

	// 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">
	  <div class="col-12 col-md-12">
	  '.$show_transaction_list_html.'
	  </div>
	</div>
	<br>
	<div class="row">
		<div id="preview_result"></div>
	</div>
	';
}

  // 審核狀態按鈕JS $tr['Whether to confirm the audit consent'] = '是否確認審核同意';$tr['Determine whether to update processing information'] = '確定是否更新處理資訊';
  $audit_js = "
  $(document).ready(function(){
    $('#agreen_ok').click(function(){
      $('#agreen_ok').attr('disabled', 'disabled');
      var r = confirm('".$tr['Whether to confirm the audit consent']."?');
      var id = ".$depositing_company_review_id.";
      if(r == true){
        $.post('depositing_company_audit_action.php?a=depositing_company_audit_submit',
          {
            depositing_id: id
          },
          function(result){
            $('#preview_result').html(result);
          }
        )
      }
      window.location.reload();
    });

    $('#agreen_cancel').click(function(){
      $('#agreen_cancel').attr('disabled', 'disabled');
      var r = confirm('".$tr['Whether to cancel the audit consent']."?');
      var id = ".$depositing_company_review_id.";
      if(r == true){
        $.post('depositing_company_audit_action.php?a=depositing_company_audit_cancel',
          {
            depositing_id: id
          },
          function(result){
            $('#preview_result').html(result);
          }
        )
      }
      window.location.reload();
    });

    // 更新 notes
    $('#notes_common_update').click(function(){
      $('#notes_common_update').attr('disabled', 'disabled');
      var r = confirm('".$tr['Determine whether to update processing information']."?');
      var id = ".$depositing_company_review_id.";
      var notes = $('#notes_common').val();
      if(r == true){
        $.post('depositing_company_audit_action.php?a=notes_common_update',
          {
            depositing_company_review_id: id,
            depositing_company_notes: notes
          },
          function(result){
            $('#preview_result').html(result);
          }
        )
      }
      window.location.reload();
    });
  });
  ";
  $extend_js = $extend_js."
  <script>
  ".$audit_js."
  </script>
  ";

	// JS 開頭
  $extend_head = $extend_head. <<<HTML
  <script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
  <script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
  <link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>

  <script type="text/javascript" language="javascript" class="init">
  $(document).ready(function () {
    $("#note").validationEngine();
  });
</script>
HTML;  

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] 		= $tr['host_descript'];
$tmpl['html_meta_author']	 				= $tr['host_author'];
$tmpl['html_meta_title'] 					= $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']								= $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']							= $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']								= $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] 			= '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']				= $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");

?>
