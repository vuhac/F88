<?php
// ----------------------------------------------------------------------------
// Features:	點數管理
// File Name:	cash_management.php
// Author:		snow
// Related:		
// 對應: cash_management_action.php
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once __DIR__ . '/lib_message.php';

require_once dirname(__FILE__) ."/system_config.php";

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
$function_title 		= $tr['system point management'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';

$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] .'</a></li>
  <li><a href="#">' . $tr['webmaster'] . '</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';

// 只有站長或維運也就是 $su['superuser'] 才有權限使用此頁
if(!($_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['superuser']))) {
  header('Location:./home.php');
  die();
}
$cash_html = '<div class="col-md-12 tab">
<ul class="nav nav-tabs">
    <li class="active"><a href="" target="_self">'.$tr['point management'].'</a></li>
    <li><a href="cash_issue_record.php" target="_self">'.$tr['publication record'].'</a></li>
</ul></div><br><br>';

// 查看gcashcashier餘額
$sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.id = 2;";
$r = runSQLall($sql);
$gcash_blance 	= '$'.number_format(round( $r[1]->gcash_balance,2),2);
$gcash_balance_t = $r[1]->gcash_balance;


// 查看gtokencashier餘額
$sql = "SELECT gtoken_balance FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.id = 3;";
$r= runSQLall($sql);
$gtoken_blance 	= '$'.number_format(round( $r[1]->gtoken_balance,2),2);
$gtoken_balance_t = $r[1]->gtoken_balance;

// 擴充 head 內的 css or js
$extend_head				= $extend_head.'<!-- Jquery UI js+css  -->
                        <script src="in/jquery-ui.js"></script>
                        <link rel="stylesheet"  href="in/jquery-ui.css" >
                        <!-- jquery datetimepicker js+css -->
                        <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
                        <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
                        <!-- Datatables js+css  -->
                        <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
                        <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
                        <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
                        ';

$indexbody_content = <<<HTML



<table id="show_list" class="display" width="100%">
  <thead>
    <tr>
      <th>{$tr['Account'] }</th>
      <th>{$tr['name']}</th>
      <th>{$tr['Balance']}</th>
      <th>{$tr['operation']}</th>      
    </tr>
  </thead>
  <tbody>
    <tr>
        <td>gcashcashier</td>
        <td>{$tr['gcash cashier']}</td>
        <td><label id="gcash_blance">$gcash_blance</label></td>        
        <td>
        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#operModal" data-view="现金出纳" data-whatever="gcashcashier" title="{$tr['publication']}" value="4"><span class="glyphicon glyphicon-plus" aria-hidden="true">{$tr['publication']}</span></button>
        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#operModal" data-view="现金出纳" data-whatever="gcashcashier" title="{$tr['reversal']}" value="3"><span class="glyphicon glyphicon-minus" aria-hidden="true">{$tr['reversal']}</span></button>               
        </td>        
        
    </tr>
    <tr>
        <td>gtokencashier</td>
        <td>{$tr['gtoken cashier']}</td>
        <td><label id="gtoken_blance">$gtoken_blance</label></td>
        <td>
        <button type="button" class="btn btn-success"  data-toggle="modal" data-target="#operModal" data-view="代币出纳" data-whatever="gtokencashier" title="{$tr['publication']}" value="2"><span class="glyphicon glyphicon-plus" aria-hidden="true">{$tr['publication']}</span></button>
        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#operModal" data-view="代币出纳" data-whatever="gtokencashier" title="{$tr['reversal']}" value="1"><span class="glyphicon glyphicon-minus" aria-hidden="true">{$tr['reversal']}</span></button>                
        </td>
    </tr>
  </tbody>
  <tfoot>
    <tr>
      <th>{$tr['Account']}</th>
      <th>{$tr['name']}</th>
      <th>{$tr['Balance']}</th>
      <th>{$tr['operation']}</th>
    </tr>
  </tfoot>
</table>




<div class="modal fade" id="operModal" tabindex="-1" role="dialog" aria-labelledby="operModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="operModalLabel">進行操作</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>      
      <div class="modal-body">
        <form autocomplete="new-password">
          <div id="return_message" class="alert alert-success">
          <br>
          </div>
          <div class="form-group">
            <label for="recipient-name" class="col-form-label">{$tr['Account'] }:</label>
            <input type="hidden" id="issue_type" value="">
            <input type="hidden" id="issue_gtoken" value="">
            <input type="hidden" id="issue_gcash" value="">
            <input type="text" class="form-control" id="recipient_name" readonly="value">            
          </div>
          <div class="form-group">
            <label for="message-text" class="col-form-label">{$tr['amount']}:</label>
            <input type="number" class="form-control" id="recipient_cash" placeholder="ex:100.0" autocomplete="new-password">            
          </div>
          <div class="form-group">
            <label for="message-text" class="col-form-label">{$tr['your pwd']}:</label>
            <input type="password" class="form-control" id="recipient_pd" autocomplete="new-password">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">{$tr['Cancel']}</button>
        <button type="button" class="btn btn-primary"id="submit_issue">{$tr['confirm']}</button>
      </div>
    </div>
  </div>
</div>


<div class="row">
  <div id="issue_result"  type="hidden"></div>
</div>

HTML;

$extend_head = '
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
';

$extend_js .= <<<JS
<script>
$(document).ready(function() {
  $('#show_list').DataTable({
    "searching": false,
    "paging":false,
    "ordering":false,
    "info":false,
  });
  // 點沖銷/發行Button顯示彈跳視窗
  $('#operModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget)                       // 觸發按鈕
    var issue_account = button.data('whatever')               // 從data-* 屬性提取訊息(存放接收者帳號)
    var opr = button.text()
    //var recipient = button.val()//提取button的value
    var modal = $(this)
    // modal.find('.modal-title').text(opr + '操作') 
    modal.find('.modal-title').text(opr +' ' +'{$tr['operation']}')             // 彈跳視窗的標題
    modal.find('#recipient_name').val(issue_account)          // 隱藏欄位:接收者
    modal.find('#issue_gtoken').val($gtoken_balance_t)        // 隱藏欄位:當前餘額 token
    modal.find('#issue_gcash').val($gcash_balance_t)          // 隱藏欄位:當前餘額 cash
    modal.find('#issue_type').val(button.val())               // 方式
    
    var issue_account_name = button.data('view')              // 從data-* 屬性提取訊息(存放接收者中文名稱)
    // var msg = '* 此功能会对系统上的 '+issue_account_name+' '+issue_account+' 來'+opr+'指定金额的点数。<br> * 请输入1~10000000的金额。';
    var msg = '* {$tr['This feature will be on the system']} '+issue_account_name+' '+issue_account +opr+'{$tr['The number of points for the specified amount.']}<br> * {$tr['Please enter an amount from 1 to 10000000.']}';
    modal.find('#return_message').html(msg)                   // 印出描述文字
    
  });

  // 點確認後
  $('#submit_issue').click(function(e){    
    var account = $('#recipient_name').val();                         // 接收者帳號     
    var type = $('#issue_type').val();                                // 方式
    var amount = $('#recipient_cash').val();                          // 金額異動
    if(amount=='')  amount='0'
    var account_balance = '';                                         // 當下餘額
    if(type==1 || type==2)  account_balance=$('#issue_gtoken').val(); 
    else if(type==3 || type==4)  account_balance=$('#issue_gcash').val();    
    else account_balance='0';    
    var passwd = $('#recipient_pd').val();                            // 密碼
   
    $.post('cash_management_action.php?a=issue',
				{ account: account,
					type: type,
          amount: Number(amount),
          balance: Number(account_balance),
          passwd: passwd
        },
				function(result){          
					$('#issue_result').html(result);               
        });

  });

});
 
</script>
JS;


$cash_html .= $indexbody_content;


// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description']    = $tr['host_descript'];
$tmpl['html_meta_author']         = $tr['host_author'];
$tmpl['html_meta_title']          = $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']               = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']              = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']                = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content']       = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']        = $cash_html;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");