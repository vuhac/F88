<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 線上支付商戶管理 , 顯示詳細線上支付商戶資訊
// File Name:	deposit_onlinepayment_config_detail.php
// Author:		Yuan
// Related:   對應 deposit_onlinepayment_config.php 各項線上支付商戶資訊管理
// DB Table:
// Log:
// ----------------------------------------------------------------------------

//-----------------------------------------------------------------------------
// 將修改、新增表格 寫在最後面 依據帶入的動作不同 填入不同的變數
// 支付名稱
// $deposit_onlinepayment_payname
// 支付商
// $payment_service_option
// 商店代號
// $deposit_onlinepayment_merchantid
// 商店名稱
// $deposit_onlinepayment_merchantname
// 商户HashIV
// $deposit_onlinepayment_hashiv
// 商户HashKey
// $deposit_onlinepayment_hashkey
// 单次存款上限
// $deposit_onlinepayment_singledepositlimits
// 累積总存款上限
// $deposit_onlinepayment_depositlimits
// 會員等級
// $edit_gradename_checkbox_option
// 手續費(%)
// $deposit_onlinepayment_cashfeerate
// 狀態
// $edit_status_select_option
// 其他線上支付資訊
// $deposit_onlinepayment_notes
// 額外需求 ex(隱藏起來的 id 資訊等)
// $extend_deposit_onlinepayment_form
// dynamic view javascript
// $payment_vendor_onchange_js
// ajax javascript
// $submit_to_save_js
//	[選填]轉出收款帳號資訊
//$deposit_onlinepayment_receiptaccount ='';
//	[選填]轉出收款銀行資訊
//$deposit_onlinepayment_receiptbank = '';
//[選填]轉出款帳號名稱
//$deposit_onlinepayment_receiptname = '';


session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 專屬本頁的文字檔案
require_once dirname(__FILE__) ."/deposit_onlinepayment_config_lib.php";

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
// 功能標題，放在標題列及meta $tr['Online payment account maintenance'] = '線上支付商戶維護';
$function_title 		= $tr['Online payment account maintenance'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置 $tr['Home'] = '首頁';$tr['System Management'] = '系統管理';$tr['Online Payment Merchant Management'] = '線上支付商戶管理';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['System Management'].'</a></li>
  <li><a href="deposit_onlinepayment_config.php">'.$tr['Online Payment Merchant Management'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------


// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND isset($_GET['a'])) {


  //清空顯示表格
  $edit_column = '';

  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  表格內容 html 組合 start
  // -----------------------------------------------------------------------------------------------------------------------------------------------

//  $member_grade_id = filter_var($_POST['member_grade_id'], FILTER_SANITIZE_STRING);
//  $member_grade_gradename = filter_var($_POST['member_grade_gradename'], FILTER_SANITIZE_STRING);

  // 使用者所在的時區，sql 依據所在時區顯示 time
  if(isset($_SESSION['agent']->timezone) AND $_SESSION['agent']->timezone != NULL) {
    $tz = $_SESSION['agent']->timezone;
  } else {
    $tz = '+08';
  }
  // 轉換時區所要用的 sql timezone 參數
  $tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."'";
  $tzone = runSQLALL($tzsql);
  // var_dump($tzone);
  if($tzone[0]==1) {
    $tzonename = $tzone[1]->name;
  } else {
    $tzonename = 'posix/Etc/GMT-8';
  }

  //拆開傳入的動作  example edit_1&p=
  $action = explode("_",str_replace(' ','',$_GET['a']));
  $action[0] = preg_replace('/[^A-Za-z]/', '', $action[0]);

  //處理不同支付商表格顯示名稱不同
  if(isset($_GET['p'])){

    $action[0] = preg_replace('/[^A-Za-z\_]/', '', $action[0]);

      foreach ($payment_service as $index => $value) {
        if($_GET['p'] == $payment_service[$i]['code']){
          $payment_service_name_confirm = $payment_service[$i]['code'];
          break;
        }else{
          $payment_service_name_confirm = $payment_service[0]['code'];
        }
      }
  }else{
    $payment_service_name_confirm = $payment_service[0]['code'];
  }

  //避免網址一直疊加
  $payment_click_reload_url = 'deposit_onlinepayment_config_detail.php?a='.$_GET['a'];
  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  修改入款方式 start
  // -----------------------------------------------------------------------------------------------------------------------------------------------
    if($action[0] == 'edit' && filter_var($action[1], FILTER_VALIDATE_INT) != false){

      //清空顯示表格
      $edit_column = '';

      // 取出 DB 線上付商戶資訊
      $onlinepayment_search_sql = "SELECT * FROM root_deposit_onlinepayment WHERE id='".$action[1]."'";
      //  var_dump($onlinepayment_search_sql);
      $onlinepayment_search_sql_result = runSQLall($onlinepayment_search_sql);
      //var_dump($onlinepayment_search_sql_result);

      //只會有一筆紀錄否則就是出錯了
      if($onlinepayment_search_sql_result[0] == '1'){

        $deposit_onlinepayment_payname                    = $onlinepayment_search_sql_result[1]->payname;
        $deposit_onlinepayment_merchantid                 = $onlinepayment_search_sql_result[1]->merchantid;
        $deposit_onlinepayment_gradename                  = $onlinepayment_search_sql_result[1]->grade;
        $deposit_onlinepayment_status                     = $onlinepayment_search_sql_result[1]->status;
        $deposit_onlinepayment_cashfeerate                = $onlinepayment_search_sql_result[1]->cashfeerate;
        $deposit_onlinepayment_name                       = $onlinepayment_search_sql_result[1]->name;
        $deposit_onlinepayment_singledepositlimits        = $onlinepayment_search_sql_result[1]->singledepositlimits;
        $deposit_onlinepayment_depositlimits              = $onlinepayment_search_sql_result[1]->depositlimits;
        $deposit_onlinepayment_merchantname               = $onlinepayment_search_sql_result[1]->merchantname;
        $deposit_onlinepayment_notes                      = $onlinepayment_search_sql_result[1]->notes;
        $deposit_onlinepayment_hashiv                     = $onlinepayment_search_sql_result[1]->hashiv;
        $deposit_onlinepayment_hashkey                    = $onlinepayment_search_sql_result[1]->hashkey;
        $deposit_onlinepayment_receiptaccount             = $onlinepayment_search_sql_result[1]->receiptaccount;
        $deposit_onlinepayment_receiptbank                = $onlinepayment_search_sql_result[1]->receiptbank;
        $deposit_onlinepayment_receiptname                = $onlinepayment_search_sql_result[1]->receiptname;
        $deposit_onlinepayment_effectiveseconds           = $onlinepayment_search_sql_result[1]->effectiveseconds;


        // 如果沒有傳入則使用之前儲存的
        if(empty($_GET['p'])){
          $payment_service_name_confirm = $deposit_onlinepayment_name;
        }else{
          $deposit_onlinepayment_name = $_GET['p'];
        }

        //把抓出來的會員等級 json 轉成 array
        $deposit_onlinepayment_gradename = json_decode($deposit_onlinepayment_gradename, true);
        //var_dump($deposit_onlinepayment_gradename);

        //會員等級 使用下拉式選單
        $member_grade_sql = "SELECT * FROM root_member_grade";
        //  var_dump($onlinepayment_search_sql);
        $member_grade_sql_result = runSQLall($member_grade_sql);
        //var_dump($member_grade_sql_result);

        $edit_gradename_checkbox_option = '';
        //一定會有至少一個等級
        if($member_grade_sql_result[0] >=1){
          for($i=1;$i<=$member_grade_sql_result[0];$i++){
            if(isset($deposit_onlinepayment_gradename[$member_grade_sql_result[$i]->gradename])) {
              $edit_gradename_checkbox_option = $edit_gradename_checkbox_option.'
                  <label class="checkbox-inline">
                      <input class="form-check-input" name="edit_gradename" type="checkbox" value=\'"'.$member_grade_sql_result[$i]->gradename.'":"'.$member_grade_sql_result[$i]->id.'"\' checked>'.$member_grade_sql_result[$i]->gradename.'
                  </label>';
            }else{
              $edit_gradename_checkbox_option = $edit_gradename_checkbox_option.'
                  <label class="checkbox-inline">
                    <input class="form-check-input" name="edit_gradename" type="checkbox" value=\'"'.$member_grade_sql_result[$i]->gradename.'":"'.$member_grade_sql_result[$i]->id.'"\'>'.$member_grade_sql_result[$i]->gradename.'
                  </label>';
            }
          }


          $edit_status_select_option='';
          //狀態 下拉式選單
          for($i=0;$i<=2;$i++){
            if($deposit_onlinepayment_status == $i){
                $edit_status_select_option = $edit_status_select_option.'<option selected="selected" value='.$i.'>'.$select_status[$i].'</option>';
            }else{
              $edit_status_select_option = $edit_status_select_option.'<option value='.$i.'>'.$select_status[$i].'</option>';
            }
          }

          $payment_service_option='';
          //支付商 下拉式選單
          foreach ($payment_service as $index => $value) {
            if($deposit_onlinepayment_name == $payment_service[$index]['code']){
              $payment_service_option = $payment_service_option.'<option selected="selected" value='.$payment_service[$index]['code'].'>'.$payment_service[$index]['name'].'</option>';
            }else{
              $payment_service_option = $payment_service_option.'<option value='.$payment_service[$index]['code'].'>'.$payment_service[$index]['name'].'</option>';
            }
          }

          //隱藏id資訊 做修改查詢使用
          $extend_deposit_onlinepayment_form =
          '<input type="hidden" value="'.$action[1].'" id="edit_id">';

          //submit ajax $tr['Make sure all required fields are filled in'] = '請確保所有必填欄位都有填入!';
          $submit_to_save_js = "
          <script>
          $(document).ready(function(){

            function getall_checkbox() {
                 var allVals = [];
                 $('#edit_gradename_id :checked').each(function() {
                   allVals.push($(this).val());
                 });
                 return(allVals);
              }

              $('#edit_submit_to_save').click(function() {
                 // 使用 ajax 送出 post
                 var edit_payname                   = $('#edit_payname').val();
                 var edit_merchantid                = $('#edit_merchantid').val();
                 var edit_gradename                 = getall_checkbox();
                 var edit_cashfeerate               = $('#edit_cashfeerate').val();
                 var edit_status                    = $('#edit_status').val();
                 var edit_payment_service           = $('#edit_payment_service').val();
                 var edit_id                        = $('#edit_id').val();
                 var edit_merchantname              = $('#edit_merchantname').val();
                 var edit_hashiv                    = $('#edit_hashiv').val();
                 var edit_hashkey                   = $('#edit_hashkey').val();
                 var edit_singledepositlimits       = $('#edit_singledepositlimits').val();
                 var edit_depositlimits             = $('#edit_depositlimits').val();
                 var edit_notes                     = $('#edit_notes').val();
                 var edit_receiptaccount            = $('#edit_receiptaccount').val();
                 var edit_receiptbank               = $('#edit_receiptbank').val();
                 var edit_receiptname               = $('#edit_receiptname').val();
                 var edit_effectiveseconds          = $('#edit_effectiveseconds').val();

                 if( !edit_id || !edit_payname   || !edit_merchantid || edit_gradename.length == 0 ||!edit_cashfeerate || !edit_status  || !edit_payment_service  || !edit_merchantname  || !edit_hashiv  || !edit_hashkey ){
                      alert('".$tr['Make sure all required fields are filled in']."');
                    }else{
                      $.ajax ({
                        url: 'deposit_onlinepayment_config_detail_action.php?a=onlinepayment_edit_save',
                        type: 'POST',
                        data: ({
                          id: edit_id,
                          payname: edit_payname,
                          merchantid: edit_merchantid,
                          gradename: edit_gradename,
                          cashfeerate: edit_cashfeerate,
                          status: edit_status,
                          name: edit_payment_service,
                          merchantname: edit_merchantname,
                          hashiv: edit_hashiv,
                          hashkey: edit_hashkey,
                          singledepositlimits: edit_singledepositlimits,
                          depositlimits: edit_depositlimits,
                          notes: edit_notes,
                          receiptaccount: edit_receiptaccount,
                          receiptbank: edit_receiptbank,
                          receiptname: edit_receiptname,
                          effectiveseconds : edit_effectiveseconds
                        }),
                        success: function(response_data){
                          console.log(response_data);
                          $('#edit_show_result').html(response_data);
                        },
                        error: function (errorinfo) {
                          console.log(errorinfo);
                        },
                       });
                    }
              });
          });
          </script>";

        //會員等級出錯
        // $tr['Member level inquiry error, please confirm that there is already set membership level or contact customer service'] = '會員等級查詢出錯，請確認已經有設定會員等級或聯絡客服';
        }else{
          $edit_column = '<div class="alert alert-warning" role="alert">'.$tr['Member level inquiry error, please confirm that there is already set membership level or contact customer service'].'</div>';
        }

      }else{
        // $tr['Without this record, please contact customer service'] = '(x) 沒有此筆紀錄，請聯絡客服';
        $edit_column  = '<div class="alert alert-warning" role="alert">'.$tr['Without this record, please contact customer service'].'</div>';
      }
  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  修改入款方式 END
  // -----------------------------------------------------------------------------------------------------------------------------------------------

  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  新增入款方式 start
  // -----------------------------------------------------------------------------------------------------------------------------------------------
  }elseif($action[0] == 'add' AND $action[1] == base64_encode(date("Ymd"))){

      //清空顯示表格
      $edit_column = '';

      //會員等級 使用下拉式選單
      $member_grade_sql = "SELECT * FROM root_member_grade";
      //  var_dump($onlinepayment_search_sql);
      $member_grade_sql_result = runSQLall($member_grade_sql);
      //var_dump($member_grade_sql_result);

        $edit_gradename_checkbox_option = '';
        //一定會有至少一個等級
        if($member_grade_sql_result[0] >=1){
          for($i=1;$i<=$member_grade_sql_result[0];$i++){
              $edit_gradename_checkbox_option = $edit_gradename_checkbox_option.'
                  <label class="checkbox-inline">
                      <input class="form-check-input" name="edit_gradename" type="checkbox" value=\'"'.$member_grade_sql_result[$i]->gradename.'":"'.$member_grade_sql_result[$i]->id.'"\'>'.$member_grade_sql_result[$i]->gradename.'
                  </label>';
            }


          $edit_status_select_option='';
          //狀態 下拉式選單
          $edit_status_select_option = $edit_status_select_option.'<option selected="selected" value="1">'.$select_status[1].'</option>';
          $edit_status_select_option = $edit_status_select_option.'<option value=0>'.$select_status[0].'</option>';


          $payment_service_option='';
          //支付商 下拉式選單
          foreach ($payment_service as $index => $value) {
            if($payment_service_name_confirm == $payment_service[$index]['code']){
              $payment_service_option = $payment_service_option.'<option selected="selected" value='.$payment_service[$index]['code'].'>'.$payment_service[$index]['name'].'</option>';
            }else{
              $payment_service_option = $payment_service_option.'<option value='.$payment_service[$index]['code'].'>'.$payment_service[$index]['name'].'</option>';
            }
          }

          // 支付名稱
          $deposit_onlinepayment_payname = '';

          // 商店代號
          $deposit_onlinepayment_merchantid ='';
          // 商店名稱
          $deposit_onlinepayment_merchantname = '';
          // 商户HashIV
          $deposit_onlinepayment_hashiv = '';
          // 商户HashKey
          $deposit_onlinepayment_hashkey = '';
          // 单次存款上限
          $deposit_onlinepayment_singledepositlimits = '';
          // 累積总存款上限
          $deposit_onlinepayment_depositlimits = '';
          // 手續費(%)
          $deposit_onlinepayment_cashfeerate  = '';
          // 其他線上支付資訊
          $deposit_onlinepayment_notes  = '';
          // 額外需求 ex(隱藏起來的 id 資訊等)
          $extend_deposit_onlinepayment_form  = '';
          //	[選填]轉出收款帳號資訊
          $deposit_onlinepayment_receiptaccount ='';
          //	[選填]轉出收款銀行資訊
          $deposit_onlinepayment_receiptbank = '';
          // [選填]轉出款帳號名稱
          $deposit_onlinepayment_receiptname = '';
          // [選填]交易有效秒數
          $deposit_onlinepayment_effectiveseconds = '';

          //submit ajax
          $submit_to_save_js = "
          <script>
          $(document).ready(function(){

            function getall_checkbox() {
                 var allVals = [];
                 $('#edit_gradename_id :checked').each(function() {
                   allVals.push($(this).val());
                 });
                 return(allVals);
              }

              $('#edit_submit_to_save').click(function() {
                 // 使用 ajax 送出 post
                 var edit_payname                   = $('#edit_payname').val();
                 var edit_merchantid                = $('#edit_merchantid').val();
                 var edit_gradename                 = getall_checkbox();
                 var edit_cashfeerate               = $('#edit_cashfeerate').val();
                 var edit_status                    = $('#edit_status').val();
                 var edit_payment_service           = $('#edit_payment_service').val();
                 var edit_merchantname              = $('#edit_merchantname').val();
                 var edit_hashiv                    = $('#edit_hashiv').val();
                 var edit_hashkey                   = $('#edit_hashkey').val();
                 var edit_singledepositlimits       = $('#edit_singledepositlimits').val();
                 var edit_depositlimits             = $('#edit_depositlimits').val();
                 var edit_notes                     = $('#edit_notes').val();
                 var edit_receiptaccount            = $('#edit_receiptaccount').val();
                 var edit_receiptbank               = $('#edit_receiptbank').val();
                 var edit_receiptname               = $('#edit_receiptname').val();
                 var edit_effectiveseconds          = $('#edit_effectiveseconds').val();


                 if( !edit_payname   || !edit_merchantid || edit_gradename.length == 0 ||!edit_cashfeerate || !edit_status  || !edit_payment_service  || !edit_merchantname  || !edit_hashiv  || !edit_hashkey ){
                      alert('".$tr['Make sure all required fields are filled in']."');
                    }else{
                      $.ajax ({
                        url: 'deposit_onlinepayment_config_detail_action.php?a=onlinepayment_add_save',
                        type: 'POST',
                        data: ({
                          payname: edit_payname,
                          merchantid: edit_merchantid,
                          gradename: edit_gradename,
                          cashfeerate: edit_cashfeerate,
                          status: edit_status,
                          name: edit_payment_service,
                          merchantname: edit_merchantname,
                          hashiv: edit_hashiv,
                          hashkey: edit_hashkey,
                          singledepositlimits: edit_singledepositlimits,
                          depositlimits: edit_depositlimits,
                          notes: edit_notes,
                          receiptaccount: edit_receiptaccount,
                          receiptbank: edit_receiptbank,
                          receiptname: edit_receiptname,
                          effectiveseconds : edit_effectiveseconds
                        }),
                        success: function(response_data){
                          console.log(response_data);
                          $('#edit_show_result').html(response_data);
                        },
                        error: function (errorinfo) {
                          console.log(errorinfo);
                        },
                       });
                    }
              });
          });
          </script>";

        //會員等級出錯
        }else{
          // $tr['Member level inquiry error, please confirm that there is already set membership level or contact customer service'] = '(x) 會員等級查詢出錯，請確認已經有設定會員等級或聯絡客服';
          $edit_column = '<div class="alert alert-warning" role="alert">'.$tr['Member level inquiry error, please confirm that there is already set membership level or contact customer service'].'</div>';
        }
  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  新增入款方式 END
  // -----------------------------------------------------------------------------------------------------------------------------------------------

      //沒有傳入動作或是動作錯誤
      }else{
        // $tr['Without this record, please contact customer service'] = '(x) 沒有此筆紀錄，請聯絡客服';
          $edit_column = '<div class="alert alert-warning" role="alert">'.$tr['Without this record, please contact customer service'].'</div>';
      }

  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  組合html start
  // -----------------------------------------------------------------------------------------------------------------------------------------------

  if($edit_column == NULL){

    $payment_form_service_name = explode('_',$payment_service_name_confirm)[0];

    //表格 $tr['required field'] = '必塡欄位';
    $edit_column = '
    <button type="button" class="btn btn-danger">'.$tr['required field'].'</button>
    <hr>
    <div class="form-horizontal">
      <div class="row form-group">
        <label class="col-sm-2 control-label js-payname-label">'.$payment_form_name[$payment_form_service_name]['payname'].'</label>
        <div class="col-sm-10">
          <input type="text" class="form-control" id="edit_payname"  placeholder="'.$payment_form_name[$payment_form_service_name]['payname'].'" value="'.$deposit_onlinepayment_payname.'">
        </div>
      </div>
      <div class="row form-group">
        <label class="col-sm-2 control-label js-payment-service-label">'.$payment_form_name[$payment_form_service_name]['payment_service'].'</label>
        <div class="col-sm-10">
          <select class="form-control" id="edit_payment_service">
          '.$payment_service_option.'
          </select>
        </div>
      </div>';
    if(!empty($payment_form_name[$payment_form_service_name]['merchantid'])) {
      $edit_column .= '
      <div class="row form-group">
        <label class="col-sm-2 control-label js-merchantid-label">'.$payment_form_name[$payment_form_service_name]['merchantid'].'</label>
        <div class="col-sm-10">
          <input type="text" class="form-control" id="edit_merchantid" placeholder="'.$payment_form_name[$payment_form_service_name]['merchantid'].'" value="'.$deposit_onlinepayment_merchantid.'">
        </div>
      </div>';
    }

    if(!empty($payment_form_name[$payment_form_service_name]['merchantname'])) {
      $edit_column .= '
      <div class="row form-group">
        <label class="col-sm-2 control-label js-merchantname-label">'.$payment_form_name[$payment_form_service_name]['merchantname'].'</label>
        <div class="col-sm-10">
          <input type="text" class="form-control" id="edit_merchantname" placeholder="'.$payment_form_name[$payment_form_service_name]['merchantname'].'" value="'.$deposit_onlinepayment_merchantname.'">
        </div>
      </div>';
    }

    if(!empty($payment_form_name[$payment_form_service_name]['hashiv'])) {
      $edit_column .= '
      <div class="row form-group">
        <label class="col-sm-2 control-label js-hashiv-label">'.$payment_form_name[$payment_form_service_name]['hashiv'].'</label>
        <div class="col-sm-10">
          <textarea class="form-control" id="edit_hashiv" placeholder="'.$payment_form_name[$payment_form_service_name]['hashiv'].'" >'.$deposit_onlinepayment_hashiv.'
          </textarea>
        </div>
      </div>';
    }

    if(!empty($payment_form_name[$payment_form_service_name]['hashkey'])) {
      $edit_column .= '
      <div class="row form-group">
        <label class="col-sm-2 control-label js-hashkey-label">'.$payment_form_name[$payment_form_service_name]['hashkey'].'</label>
        <div class="col-sm-10">
          <textarea class="form-control" id="edit_hashkey" placeholder="'.$payment_form_name[$payment_form_service_name]['hashkey'].'">'.$deposit_onlinepayment_hashkey.'
          </textarea>
        </div>
      </div>';
    }
    // $tr['Optional field'] = '選塡欄位';$tr['There is no ceiling without filling'] = '不填則無上限';$tr['Default 900 seconds'] = '預設900秒';
    $edit_column .= '
      <div class="row form-group">
        <label class="col-sm-2 control-label js-gradename-id-label">'.$payment_form_name[$payment_form_service_name]['gradename_id'].'</label>
        <div class="col-sm-10">
          <div class="form-check form-check-inline" id="edit_gradename_id" >
              '.$edit_gradename_checkbox_option.'
          </div>
        </div>
      </div>
      <div class="row form-group">
        <label class="col-sm-2 control-label js-cashfeerate-label">'.$payment_form_name[$payment_form_service_name]['cashfeerate'].'</label>
        <div class="col-sm-10">
          <input type="text" class="form-control" id="edit_cashfeerate" placeholder="'.$payment_form_name[$payment_form_service_name]['cashfeerate'].'" value="'.$deposit_onlinepayment_cashfeerate.'">
        </div>
      </div>
      <div class="row form-group">
        <label class="col-sm-2 control-label js-status-label">'.$payment_form_name[$payment_form_service_name]['status'].'</label>
        <div class="col-sm-10">
          <select class="form-control" id="edit_status">
            '.$edit_status_select_option.'
          </select>
        </div>
      </div>
      <div class="row form-group">
        <button type="button" class="btn btn-warning">'.$tr['Optional field'].'</button>
      </div>
      <hr>
      <div class="row form-group">
        <label class="col-sm-2 control-label">'.$payment_form_name[$payment_form_service_name]['singledepositlimits'].'<br>'.$tr['There is no ceiling without filling'].'</label>
        <div class="col-sm-10">
          <input type="text" class="form-control" id="edit_singledepositlimits" placeholder="'.$payment_form_name[$payment_form_service_name]['singledepositlimits'].'" value="'.$deposit_onlinepayment_singledepositlimits.'">
        </div>
      </div>
      <div class="row form-group">
        <label class="col-sm-2 control-label">'.$payment_form_name[$payment_form_service_name]['depositlimits'].'<br>'.$tr['There is no ceiling without filling'].'</label>
        <div class="col-sm-10">
          <input type="text" class="form-control" id="edit_depositlimits" placeholder="'.$payment_form_name[$payment_form_service_name]['depositlimits'].'" value="'.$deposit_onlinepayment_depositlimits.'">
        </div>
      </div>
      <div class="row form-group">
        <label class="col-sm-2 control-label">'.$payment_form_name[$payment_form_service_name]['effectiveseconds'].'<br>(60-900，'.$tr['Default 900 seconds'].')</label>
        <div class="col-sm-10">
          <input type="text" class="form-control" id="edit_effectiveseconds" placeholder="'.$payment_form_name[$payment_form_service_name]['effectiveseconds'].'" value="'.$deposit_onlinepayment_effectiveseconds.'">
        </div>
      </div>
      <div class="row form-group">
        <label class="col-sm-2 control-label">'.$payment_form_name[$payment_form_service_name]['receiptaccount'].'</label>
        <div class="col-sm-10">
          <input type="text" class="form-control"  id="edit_receiptaccount" placeholder="'.$payment_form_name[$payment_form_service_name]['receiptaccount'].'" value="'.$deposit_onlinepayment_receiptaccount.'">
        </div>
      </div>
      <div class="row form-group">
        <label class="col-sm-2 control-label">'.$payment_form_name[$payment_form_service_name]['receiptbank'].'</label>
        <div class="col-sm-10">
          <input type="text" class="form-control"  id="edit_receiptbank" placeholder="'.$payment_form_name[$payment_form_service_name]['receiptbank'].'" value="'.$deposit_onlinepayment_receiptbank.'">
        </div>
      </div>
      <div class="row form-group">
        <label class="col-sm-2 control-label">'.$payment_form_name[$payment_form_service_name]['receiptname'].'</label>
        <div class="col-sm-10">
          <input type="text" class="form-control"  id="edit_receiptname" placeholder="'.$payment_form_name[$payment_form_service_name]['receiptname'].'" value="'.$deposit_onlinepayment_receiptname.'">
        </div>
      </div>
      <div class="row form-group">
        <label class="col-sm-2 control-label">'.$payment_form_name[$payment_form_service_name]['notes'].'</label>
        <div class="col-sm-10">
          <input type="text" class="form-control"  id="edit_notes" placeholder="'.$payment_form_name[$payment_form_service_name]['notes'].'" value="'.$deposit_onlinepayment_notes.'">
        </div>
      </div>
      '.$extend_deposit_onlinepayment_form.'
      <div class="row form-group">
        <div class="col-sm-offset-2 col-sm-10">
          <button id="edit_submit_to_save" class="btn btn-primary">'.$tr['Save'].'</button>
          <button onclick="javascript:location.href=\'deposit_onlinepayment_config.php\'" class="btn btn-default">'.$tr['return'].'</button>
        </div>
      </div>
      <div class="row form-group">
        <div class="col-sm-offset-2 col-sm-10">
          <div id="edit_show_result"></div>
        </div>
      </div>
    </div>
    ';
    // $tr['Save'] = '儲存';$tr['return'] = '返回';
    $submit_to_confirm_js = "
    <script>
    $(document).ready(function(){

        $('#confirm_payment').click(function() {
           // 使用 ajax 送出 post
           var edit_payment_service = $('#edit_payment_service').val();
           window.location.href = '".$payment_click_reload_url."' + '&p=' + edit_payment_service;
        });
    });
    </script>";

    //payment_form_name json string
    $payment_form_name_json_string = json_encode($payment_form_name);
    //payment_vendor_onchange_js
    $payment_vendor_onchange_js = "
    <script>
    var payment_form_name = " . $payment_form_name_json_string . ";
    function changeOrHideLabel(jqueryLabel, newLabelText) {
      if(newLabelText == '') {
        jqueryLabel.parent().hide();
        return;
      }
      jqueryLabel.parent().show();
      jqueryLabel.text(newLabelText);
    }
    $(document).ready(function(){
      $('#edit_payment_service').on('change', function(e){
        var payment_service_name = $(e.target).val().split('_')[0];
        // console.log(payment_form_name[payment_service_name]);

        changeOrHideLabel( $('.js-merchantid-label') ,payment_form_name[payment_service_name]['merchantid'] ) ;
        changeOrHideLabel( $('.js-merchantname-label'), payment_form_name[payment_service_name]['merchantname']);
        changeOrHideLabel( $('.js-hashiv-label'), payment_form_name[payment_service_name]['hashiv']);
        changeOrHideLabel( $('.js-hashkey-label'), payment_form_name[payment_service_name]['hashkey']);
        changeOrHideLabel( $('.js-gradename-id-label'), payment_form_name[payment_service_name]['gradename_id']);
        changeOrHideLabel( $('.js-cashfeerate-label'), payment_form_name[payment_service_name]['cashfeerate']);
        changeOrHideLabel( $('.js-status-label'), payment_form_name[payment_service_name]['status']);
      })
    });
    </script>
    ";

    $extend_js = $extend_js.$payment_vendor_onchange_js.$submit_to_confirm_js.$submit_to_save_js;
  }

  // html 組合顯示
  $show_list_html = '';
  $show_list_html = $show_list_html . '
  <div class="tab-content col-12 col-md-12">
  <br>
    <div role="tabpanel" class="tab-pane active col-12 col-md-12" id="inbox_View">
      <table id="inbox_transaction_list" class="table table-bordered" cellspacing="0" width="100%">
        <thead>
          '.$edit_column.'
        </thead>
        <tbody>
        </tbody>
      </table>
    </div>
  </div>
  ';



} else {
  // 沒有登入的顯示提示俊息
  // $tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';
  $show_list_html  = $tr['only management and login mamber'];
}

  // 切成 1 欄版面
  $indexbody_content = '';
  $indexbody_content = $indexbody_content.'
	<div class="row">
	  <div class="col-12 col-md-12">
	  '.$show_list_html.'
	  </div>
	</div>
	<br>
	<div class="row">
		<div id="preview_result"></div>
	</div>
	';


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
