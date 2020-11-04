<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 會員等級管理-新增會員等級
// File Name:	add_member_grade.php
// Author:		Neil
// Related:   對應 member_grade_config.php 新增會員等級
// DB Table:
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

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
$function_title 		= ''.$tr['Add membership level'].'';
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['homepage'].'</a></li>
  <li><a href="#">'.$tr['System Management'].'</a></li>
  <li><a href="member_grade_config.php">'.$tr['Member level management'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  $show_list_html = '';

  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  表格內容 html 組合 start
  // -----------------------------------------------------------------------------------------------------------------------------------------------

  $show_list_html = $show_list_html.'
  <tr class="success">
    <td></td>
    <td class="text-center">
      <h4><strong>'.$tr['General Settings'].'</strong></h4>
    </td>
    <td></td>
  </tr>
  ';

  $show_list_html = $show_list_html.'
  <tr>
    <td>'.$tr['Member Rank Name'].'</td>
    <td>
      <div class="row">
        <div class="col-12 col-md-7">
          <input type="text" class="form-control validate[maxSize[50]]" maxlength="50" id="gradename" placeholder="'.$tr['Please fill in the name of the grade.'].'('.$tr['max'].'50'.$tr['word'].')">
        </div>
      </div>
    </td>
    <td></td>
  </tr>
  ';

  $show_list_html = $show_list_html.'
  <tr>
    <td>'.$tr['Level Status'].'</td>
    <td>
      <div class="row">
        <div class="col-12 col-md-4">
          <table class="table table-bordered">
            <tr class="active text-center">
              <td>'.$tr['Enabled or not'].'</td>
              <td>'.$tr['level setting'].'</td>
            </tr>

            <tr>
              <td>
                <div class="col-12 col-md-12 status-offer-switch pull-left">
                  <input id="status" name="status" class="checkbox_switch" value="0" type="checkbox"/>
                  <label for="status" class="label-success"></label>
                </div>
              </td>
              <td>
                <div class="form-group">
                  <select class="form-control" style="width:auto;" id="grade_alert_status">
                    <option value="normal">'.$tr['grade normal'].'</option>
                    <option value="primary">'.$tr['grade primary'].'</option>
                    <option value="warning">'.$tr['grade warning'].'</option>
                    <option value="danger">'.$tr['grade danger'].'</option>
                  </select>
                </div>
              </td>
            </tr>

          </table>
        </div>
      </div>
    </td>
    <td></td>
  </tr>
  ';

  $show_list_html = $show_list_html.'
  <tr>
    <td>'.$tr['Franchise to cash deposit audit ratio'].'</td>
    <td>
      <div class="row">
        <div class="col-12 col-md-7">
          <div class="input-group">
            <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="deposit_rate">
            <span class="input-group-addon" id="basic-addon1">%</span>
          </div>
        </div>
      </div>
    </td>
    <td>'.$tr['Only positive integers can be entered.'].'</td>
  </tr>
  ';

  $show_list_html = $show_list_html.'
  <tr>
    <td>'.$tr['Remark'].'</td>
    <td>
      <textarea class="form-control validate[maxSize[500]]" rows="5" maxlength="500" id="notes" placeholder="('.$tr['max'].'500'.$tr['word'].')"></textarea>
    </td>
    <td></td>
  </tr>
  ';

  $show_list_html = $show_list_html.'
  <tr class="success">
    <td></td>
    <td class="text-center">
      <h4><strong>'.$tr['deposit settings'].'</strong></h4>
    </td>
    <td></td>
  </tr>
  ';

  $show_list_html = $show_list_html.'
  <tr>
    <td>'.$tr['company deposit value'].'</td>
    <td>
      <table class="table table-bordered">
        <tr class="active text-center">
          <td>'.$tr['Enabled or not'].'</td>
          <td class="info">'.$tr['lower limit'].'</td>
          <td class="info">'.$tr['ceiling'].'</td>
        </tr>
        <tr>
          <td>
            <div class="form-group">
              <select class="form-control" style="width:auto;" id="deposit_allow">
                <option value="0">'.$tr['off'].'</option>
                <option value="1">'.$tr['Enabled'].'</option>
                <option value="2">'.$tr['Maintenance'].'</option>
              </select>
            </div>
          </td>
          <td>
            <input type="number" class="form-control" placeholder="'.$tr['Company Deposit Limit'].'" aria-describedby="basic-addon1" id="depositlimits_lower">
          </td>
          <td>
            <input type="number" class="form-control" placeholder="'.$tr['company cap limit'].'" aria-describedby="basic-addon1" id="depositlimits_upper">
          </td>
        </tr>

      </table>
    </td>
    <td></td>
  </tr>
  ';

  $show_list_html = $show_list_html.'
  <tr>
    <td>'.$tr['online payment stored value'].'</td>
    <td>
      <table class="table table-bordered">
        <tr class="active text-center">
          <td>'.$tr['Enabled or not'].'</td>
          <td class="info">'.$tr['lower limit'].'</td>
          <td class="info">'.$tr['ceiling'].'</td>
          <td class="info">'.$tr['Fee'].'</td>
        </tr>

        <tr>
          <td>
            <div class="form-group">
              <select class="form-control" style="width:auto;" id="apifastpay_allow">
                <option value="0">'.$tr['off'].'</option>
                <option value="1">'.$tr['Enabled'].'</option>
                <option value="2">'.$tr['Maintenance'].'</option>
              </select>
            </div>
          </td>
          <td>
            <input type="number" class="form-control" placeholder="'.$tr['Online Payment Limit'].'" aria-describedby="basic-addon1" id="apifastpaylimits_lower">
          </td>
          <td>
            <input type="number" class="form-control" placeholder="'.$tr['Online Payment Caps'].'" aria-describedby="basic-addon1" id="apifastpaylimits_upper">
          </td>
          <td>
            <div class="input-group">
              <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="apifastpayfee_member_rate" value="0">
              <span class="input-group-addon" id="basic-addon1">%</span>
            </div>
          </td>
        </tr>

      </table>
    </td>
    <td></td>
  </tr>
  ';
  // $tr['Point card payment stored value'] = '點卡支付儲值';
  // $tr['Enabled or not'] = '是否啟用';
  // $tr['lower limit'] = '限額下限';
  // $tr['ceiling'] = '限額上限';
  // $tr['off'] = '關閉';
  // $tr['Enabled'] = '啟用';
  // $tr['Maintenance'] = '維護';
  // $tr['card to pay the lower limit'] = '點卡支付限額下限';
  // $tr['card payment limit'] = '點卡支付限額上限';
  // $show_list_html = $show_list_html.'
  // <tr>
  //   <td>'.$tr['Point card payment stored value'].'</td>
  //   <td>
  //     <table class="table table-bordered">
  //       <tr class="active text-center">
  //         <td>'.$tr['Enabled or not'].'</td>
  //         <td class="info">'.$tr['lower limit'].'</td>
  //         <td class="info">'.$tr['ceiling'].'</td>
  //       </tr>

  //       <tr>
  //         <td>
  //           <div class="form-group">
  //             <select class="form-control" style="width:auto;" id="pointcard_allow">
  //               <option value="0">'.$tr['off'].'</option>
  //               <option value="1">'.$tr['Enabled'].'</option>
  //               <option value="2">'.$tr['Maintenance'].'</option>
  //             </select>
  //           </div>
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="'.$tr['card to pay the lower limit'].'" aria-describedby="basic-addon1" id="pointcard_limits_lower">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="'.$tr['card payment limit'].'" aria-describedby="basic-addon1" id="pointcard_limits_upper">
  //         </td>
  //       </tr>
  //     </table>
  //   </td>
  //   <td></td>
  // </tr>
  // ';
  // $tr['Point Card Payment Fees'] = '點卡支付手續費';
  // $tr['Enabled or not'] = '是否啟用';
  // $tr['cost ratio'] = '費用比例';
  // $tr['Affiliate'] = '會員負擔';
  // $show_list_html = $show_list_html.'
  // <tr>
  //   <td>'.$tr['Point Card Payment Fees'].'</td>
  //   <td>
  //     <div class="row">
  //       <div class="col-12 col-md-7">
  //         <table class="table table-bordered">
  //           <tr class="active text-center">
  //             <td>'.$tr['Enabled or not'].'</td>
  //             <td>'.$tr['cost ratio'].'</td>
  //           </tr>

  //           <tr>
  //             <td>
  //               <div class="col-12 col-md-12 other-switch pull-left">
  //                 <input id="pointcardfee_member_rate_enable" name="pointcardfee_member_rate_enable" class="checkbox_switch" value="0" type="checkbox"/>
  //                 <label for="pointcardfee_member_rate_enable" class="label-success"></label>
  //               </div>
  //             </td>
  //             <td>
  //               <div class="input-group">
  //                 <input type="number" class="form-control" placeholder="'.$tr['Affiliate'].'" aria-describedby="basic-addon1" id="pointcardfee_member_rate">
  //                 <span class="input-group-addon" id="basic-addon1">%</span>
  //               </div>
  //             </td>
  //           </tr>

  //         </table>
  //       </div>
  //     </div>

  //   </td>
  //   <td></td>
  // </tr>
  // ';

  // $tr['Withdrawal set'] = '取款設定';
  $show_list_html = $show_list_html.'
  <tr class="success">
    <td></td>
    <td class="text-center">
      <h4><strong>'.$tr['Withdrawal set'].'</strong></h4>
    </td>
    <td></td>
  </tr>
  ';

  $show_list_html = $show_list_html.'
  <tr>
    <td>'.$tr['Join the gold withdrawal set'].'</td>
    <td>

      <div class="row">
        <div class="col-12 col-md-7">
          <div class="input-group">
            <input type="number" class="form-control" placeholder="'.$tr['withdrawal Lower limit'].'" aria-describedby="basic-addon1" id="withdrawallimits_cash_lower">
            <span class="input-group-addon" id="basic-addon1">~</span>
            <input type="number" class="form-control" placeholder="'.$tr['withdrawal Upper limit'].'" aria-describedby="basic-addon1" id="withdrawallimits_cash_upper">
          </div>
        </div>
      </div>

      <br>

      <table class="table table-bordered">
        <tr class="active text-center">
          <td>'.$tr['Enabled or not'].'</td>
          <td>手续费收取方式</td>
          <td>'.$tr['Fee'].'</td>
          <td class="info">'.$tr['Fee limit'].'</td>
        </tr>

        <tr>
          <td>
            <div class="form-group">
              <select class="form-control" style="width:auto;" id="withdrawalcash_allow">
                <option value="0">'.$tr['off'].'</option>
                <option value="1">'.$tr['Enabled'].'</option>
              </select>
            </div>
          </td>
          <td>
            <div class="form-group">
              <select class="form-control" style="width:auto;" id="withdrawalfee_method_cash">
                <option value="1">'.$tr['off'].'</option>
                <option value="3">'.$tr['Enabled'].'</option>
              </select>
            </div>
          </td>
          <td>
            <div class="input-group">
              <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="withdrawalfee_cash">
              <span class="input-group-addon" id="basic-addon1">%</span>
            </div>
          </td>
          <td>
            <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="withdrawalfee_max_cash">
          </td>
        </tr>

      </table>
    </td>
    <td>
      1. '.$tr['Only upper and lower limits can be entered for positive integers.'].'<br>
      2. 手续费收取方式: 关闭=免手续费, 启用=每次收取
    </td>
  </tr>
  ';
  /*
  <div class="radio">
        <label>
          <input type="radio" class="withdrawalfee_method_cash" name="withdrawalfee_method_cash" value="3" checked>
          '.$tr['Each charge'].'
        </label>
      </div>
      <div class="radio">
  <label>
    <input type="radio" class="withdrawalfee_method_cash" name="withdrawalfee_method_cash" value="2">
      <div class="row">
        <div class="col-12 col-md-8">
          <div class="input-group">
            <input type="text" class="form-control" placeholder="" aria-describedby="basic-addon1" id="withdrawalfee_free_hour_cash">
            <span class="input-group-addon" id="basic-addon1">'.$tr['Take money within hours'].'</span>
            <input type="text" class="form-control" placeholder="" aria-describedby="basic-addon1" id="withdrawalfee_free_times_cash">
            <span class="input-group-addon" id="basic-addon1">'.$tr['Free of charge'].'</span>
          </div>
        </div>
      </div>
      <div class="radio">
        <label>
          <input type="radio" class="withdrawalfee_method_cash" name="withdrawalfee_method_cash" value="1">'.$tr['Free of fee'].'
        </label>
      </div>
  
  */
  //2. '.$tr['X withdrawals within X hours free of charge. X and Y can only enter positive integers.'].'
  $show_list_html = $show_list_html.'
  <tr>
    <td>'.$tr['Cash withdrawal settings'].'</td>
    <td>

      <div class="row">
        <div class="col-12 col-md-7">
          <div class="input-group">
            <input type="number" class="form-control" placeholder="'.$tr['withdrawal Lower limit'].'" aria-describedby="basic-addon1" id="withdrawallimits_lower">
            <span class="input-group-addon" id="basic-addon1">~</span>
            <input type="number" class="form-control" placeholder="'.$tr['withdrawal Upper limit'].'" aria-describedby="basic-addon1" id="withdrawallimits_upper">
          </div>
        </div>
      </div>

      <br>

      <table class="table table-bordered">
        <tr class="active text-center">
          <td>'.$tr['Enabled or not'].'</td>
          <td>手续费收取方式</td>
          <td>'.$tr['Fee'].'</td>
          <td class="info">'.$tr['Fee limit'].'</td>
        </tr>

        <tr>
          <td>
            <div class="form-group">
              <select class="form-control" style="width:auto;" id="withdrawal_allow">
                <option value="0">'.$tr['off'].'</option>
                <option value="1">'.$tr['Enabled'].'</option>
              </select>
            </div>
          </td>
          <td>
            <div class="form-group">
              <select class="form-control" style="width:auto;" id="withdrawalfee_method">
                <option value="1">'.$tr['off'].'</option>
                <option value="3">'.$tr['Enabled'].'</option>
              </select>
            </div>
          </td>
          <td>
            <div class="input-group">
              <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="withdrawalfee">
              <span class="input-group-addon" id="basic-addon1">%</span>
            </div>
          </td>
          <td>
            <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="withdrawalfee_max">
          </td>
        </tr>

      </table>
    </td>
    <td>
      1. '.$tr['Only upper and lower limits can be entered for positive integers.'].'<br> 
      2. 手续费收取方式: 关闭=免手续费, 启用=每次收取
    </td>
  </tr>
  ';
  /*
   <div class="radio">
        <label>
          <input type="radio" class="withdrawalfee_method" name="withdrawalfee_method" value="3" checked>
          '.$tr['Each charge'].'
        </label>
      </div>
      <div class="radio">
  <label>
    <input type="radio" class="withdrawalfee_method" name="withdrawalfee_method" value="2">
      <div class="row">
        <div class="col-12 col-md-8">
          <div class="input-group">
            <input type="text" class="form-control" placeholder="" aria-describedby="basic-addon1" id="withdrawalfee_free_hour">
            <span class="input-group-addon" id="basic-addon1">'.$tr['Take money within hours'].'</span>
            <input type="text" class="form-control" placeholder="" aria-describedby="basic-addon1" id="withdrawalfee_free_times">
            <span class="input-group-addon" id="basic-addon1">'.$tr['Free of charge'].'</span>
          </div>
        </div>
      </div>
      <div class="radio">
        <label>
          <input type="radio" class="withdrawalfee_method" name="withdrawalfee_method" value="1">'.$tr['Free of fee'].'
        </label>
      </div>
  
  */
  //2. '.$tr['X withdrawals within X hours free of charge. X and Y can only enter positive integers.'].'
  $show_list_html = $show_list_html.'
  <tr>
    <td>'.$tr['Affiliate withdrawal limits account'].'</td>
    <td>

      <div class="row">
        <div class="col-12 col-md-7">
          <div class="input-group">
            <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="withdrawal_limitstime_gcash">
            <span class="input-group-addon" id="basic-addon1">'.$tr['minutes for withdrawals one time'].'</span>
          </div>
        </div>
      </div>

    </td>
    <td>'.$tr['Only positive integers can be entered.'].'</td>
  </tr>
  ';

  $show_list_html = $show_list_html.'
  <tr>
    <td>'.$tr['cash withdrawal limit account'].'</td>
    <td>

      <div class="row">
        <div class="col-12 col-md-7">
          <div class="input-group">
            <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="withdrawal_limitstime_gtoken">
            <span class="input-group-addon" id="basic-addon1">'.$tr['minutes for withdrawals one time'].'</span>
          </div>
        </div>
      </div>

    </td>
    <td>'.$tr['Only positive integers can be entered.'].'</td>
  </tr>
  ';

  $show_list_html = $show_list_html.'
  <tr>
    <td>'.$tr['cash withdrawal auditing administrative costs ratio'].'</td>
    <td>
      <div class="row">
        <div class="col-12 col-md-7">
          <div class="input-group">
            <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="administrative_cost_ratio">
            <span class="input-group-addon" id="basic-addon1">%</span>
          </div>
        </div>
      </div>
    </td>
    <td>'.$tr['audit but the fees charged.'].'</td>
  </tr>
  ';

  // $show_list_html = $show_list_html.'
  // <tr class="success">
  //   <td></td>
  //   <td class="text-center">
  //     <h4><strong>'.$tr['Offer Setting'].'</strong></h4>
  //   </td>
  //   <td></td>
  // </tr>
  // ';
  // $tr['deposit amount'] = '存款金額';
  // $show_list_html = $show_list_html.'
  // <tr>
  //   <td>'.$tr['First Deposit Value Company Deposit Offer'].'</td>
  //   <td>
  //     <table class="table table-bordered">
  //       <tr class="active text-center">
  //         <td>'.$tr['Enabled or not'].'</td>
  //         <td>'.$tr['deposit amount'].'</td>
  //         <td>'.$tr['Preferential ratio'].'</td>
  //         <td class="info">'.$tr['audit multiple'].'</td>
  //         <td class="info">'.$tr['Bonus Limit'].'</td>
  //       </tr>

  //       <tr>
  //         <td>
  //           <div class="col-12 col-md-12 status-offer-switch pull-left">
  //             <input id="activity_first_deposit_enable" name="activity_first_deposit_enable" class="checkbox_switch" value="0" type="checkbox"/>
  //             <label for="activity_first_deposit_enable" class="label-success"></label>
  //           </div>
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_first_deposit_amount">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_first_deposit_rate">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_first_deposit_times">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_first_deposit_upper">
  //         </td>
  //       </tr>

  //     </table>
  //   </td>
  //   <td></td>
  // </tr>
  // ';
  // $tr['Payment on first stored value line'] = '首次儲值線上支付優惠';
  // $show_list_html = $show_list_html.'
  // <tr>
  //   <td>'.$tr['Payment on first stored value line'].'</td>
  //   <td>
  //     <table class="table table-bordered">
  //       <tr class="active text-center">
  //         <td>'.$tr['Enabled or not'].'</td>
  //         <td>'.$tr['deposit amount'].'</td>
  //         <td>'.$tr['Preferential ratio'].'</td>
  //         <td class="info">'.$tr['audit multiple'].'</td>
  //         <td class="info">'.$tr['Bonus Limit'].'</td>
  //       </tr>

  //       <tr>
  //         <td>
  //           <div class="col-12 col-md-12 status-offer-switch pull-left">
  //             <input id="activity_first_onlinepayment_enable" name="activity_first_onlinepayment_enable" class="checkbox_switch" value="0" type="checkbox"/>
  //             <label for="activity_first_onlinepayment_enable" class="label-success"></label>
  //           </div>
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_first_onlinepayment_amount">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_first_onlinepayment_rate">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_first_onlinepayment_times">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_first_onlinepayment_upper">
  //         </td>
  //       </tr>

  //     </table>
  //   </td>
  //   <td></td>
  // </tr>
  // ';
  // $tr['deposit amount'] = '存款金額';
  // $tr['company deposit discount'] = '公司入款優惠';
  // $show_list_html = $show_list_html.'
  // <tr>
  //   <td>'.$tr['company deposit discount'].'</td>
  //   <td>
  //     <table class="table table-bordered">
  //       <tr class="active text-center">
  //         <td>'.$tr['Enabled or not'].'</td>
  //         <td>'.$tr['deposit amount'].'</td>
  //         <td>'.$tr['Preferential ratio'].'</td>
  //         <td class="info">'.$tr['audit multiple'].'</td>
  //         <td class="info">'.$tr['Bonus Limit'].'</td>
  //       </tr>
  //       <tr>
  //         <td>
  //           <div class="col-12 col-md-12 status-offer-switch pull-left">
  //             <input id="activity_deposit_preferential_enable" name="activity_deposit_preferential_enable" class="checkbox_switch" value="0" type="checkbox"/>
  //             <label for="activity_deposit_preferential_enable" class="label-success"></label>
  //           </div>
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_deposit_preferential_amount">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_deposit_preferential_rate">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_deposit_preferential_times">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_deposit_preferential_upper">
  //         </td>
  //       </tr>

  //     </table>
  //   </td>
  //   <td></td>
  // </tr>
  // ';
  // $tr['Online Payment Discount'] = '線上支付優惠';
  // $tr['days'] = '天數';
  // $show_list_html = $show_list_html.'
  // <tr>
  //   <td>'.$tr['Online Payment Discount'].'</td>
  //   <td>
  //     <table class="table table-bordered">
  //       <tr class="active text-center">
  //         <td>'.$tr['Enabled or not'].'</td>
  //         <td>'.$tr['deposit amount'].'</td>
  //         <td>'.$tr['Preferential ratio'].'</td>
  //         <td class="info">'.$tr['audit multiple'].'</td>
  //         <td class="info">'.$tr['Bonus Limit'].'</td>
  //       </tr>

  //       <tr>
  //         <td>
  //           <div class="col-12 col-md-12 status-offer-switch pull-left">
  //             <input id="activity_onlinepayment_preferential_enable" name="activity_onlinepayment_preferential_enable" class="checkbox_switch" value="0" type="checkbox"/>
  //             <label for="activity_onlinepayment_preferential_enable" class="label-success"></label>
  //           </div>
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_onlinepayment_preferential_amount">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_onlinepayment_preferential_rate">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_onlinepayment_preferential_times">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="activity_onlinepayment_preferential_upper">
  //         </td>
  //       </tr>

  //     </table>
  //   </td>
  //   <td></td>
  // </tr>
  // ';

  // $show_list_html = $show_list_html.'
  // <tr>
  //   <td>'.$tr['register send bonus'].'</td>
  //   <td>
  //     <table class="table table-bordered">
  //       <tr class="active text-center">
  //         <td>'.$tr['Enabled or not'].'</td>
  //         <td>'.$tr['Pipe Added'].'</td>
  //         <td class="info">'.$tr['gift amount'].'</td>
  //         <td class="info">'.$tr['audit amount'].'</td>
  //       </tr>

  //       <tr>
  //         <td>
  //           <div class="col-12 col-md-12 other-switch pull-left">
  //             <input id="activity_register_preferential_enable" name="activity_register_preferential_enable" class="checkbox_switch" value="0" type="checkbox"/>
  //             <label for="activity_register_preferential_enable" class="label-success"></label>
  //           </div>
  //         </td>
  //         <td>
  //           <div class="col-12 col-md-12 other-switch pull-left">
  //             <input id="activity_register_preferential_adminadd" name="activity_register_preferential_adminadd" class="checkbox_switch" value="0" type="checkbox"/>
  //             <label for="activity_register_preferential_adminadd" class="label-success"></label>
  //           </div>
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" id="activity_register_preferential_amount">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" id="activity_register_preferential_audited">
  //         </td>
  //       </tr>

  //     </table>
  //   </td>
  //   <td></td>
  // </tr>
  // ';
  // $tr['Added'] = ''.$tr['gift amount'].'';
  // $tr['continuous on-line discount'] = '連續上線優惠';
  // $tr['days'] = '天數';
  // $show_list_html = $show_list_html.'
  // <tr>
  //   <td>'.$tr['continuous on-line discount'].'</td>
  //   <td>
  //     <table class="table table-bordered">
  //       <tr class="active text-center">
  //         <td>'.$tr['Enabled or not'].'</td>
  //         <td class="info">'.$tr['days'].'</td>
  //         <td class="info">'.$tr['gift amount'].'</td>
  //         <td class="info">'.$tr['audit multiple'].'</td>
  //       </tr>

  //       <tr>
  //         <td>
  //           <div class="col-12 col-md-12 status-offer-switch pull-left">
  //             <input id="activity_daily_checkin_enable" name="activity_daily_checkin_enable" class="checkbox_switch" value="0" type="checkbox"/>
  //             <label for="activity_daily_checkin_enable" class="label-success"></label>
  //           </div>
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" id="activity_daily_checkin_days">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" id="activity_daily_checkin_amount">
  //         </td>
  //         <td>
  //           <input type="number" class="form-control" placeholder="" id="activity_daily_checkin_rate">
  //         </td>
  //       </tr>

  //     </table>
  //   </td>
  //   <td></td>
  // </tr>
  // ';

  $btn_html = '
  <p align="right">
    <button id="submit_add_grade_data" class="btn btn-success">'.$tr['Save'].'</button>
    <button id="submit_change_member_data" class="btn btn-danger" onclick="javascript:location.href=\'member_grade_config.php\'">'.$tr['Cancel'].'</button>
  </p>';


  $extend_js = $extend_js."
  <script>
  $(document).ready(function() {
    $('#submit_add_grade_data').click(function(){
      // $('#submit_add_grade_data').attr('disabled', 'disabled');

      // 一般設定
      var gradename = $('#gradename').val();
      var grade_alert_status = $('#grade_alert_status').val();
      // var status = $('#status').val();
      var status = $('#status').prop('checked');
      var deposit_rate = $('#deposit_rate').val();
      var notes = $('#notes').val();


      // 存款設定
      var deposit_allow = $('#deposit_allow').val();
      var depositlimits_upper = $('#depositlimits_upper').val();
      var depositlimits_lower = $('#depositlimits_lower').val();

      var apifastpay_allow = $('#apifastpay_allow').val();
      var apifastpaylimits_upper = $('#apifastpaylimits_upper').val();
      var apifastpaylimits_lower = $('#apifastpaylimits_lower').val();
      var apifastpayfee_member_rate = $('#apifastpayfee_member_rate').val();

      // var pointcard_allow = $('#pointcard_allow').val();
      // var pointcard_limits_upper = $('#pointcard_limits_upper').val();
      // var pointcard_limits_lower = $('#pointcard_limits_lower').val();

      // var pointcardfee_member_rate_enable = $('#pointcardfee_member_rate_enable').val();
      // var pointcardfee_member_rate_enable = $('#pointcardfee_member_rate_enable').prop('checked');
      // var pointcardfee_member_rate = $('#pointcardfee_member_rate').val();


      // 取款設定
      var withdrawallimits_cash_upper = $('#withdrawallimits_cash_upper').val();
      var withdrawallimits_cash_lower = $('#withdrawallimits_cash_lower').val();
      var withdrawalcash_allow = $('#withdrawalcash_allow').val();
      var withdrawalfee_cash = $('#withdrawalfee_cash').val();
      var withdrawalfee_max_cash = $('#withdrawalfee_max_cash').val();
      var withdrawalfee_method_cash = $('#withdrawalfee_method_cash').val();
      // var withdrawalfee_method_cash = $('input[class=withdrawalfee_method_cash]:checked').val();
      var withdrawalfee_free_hour_cash = $('#withdrawalfee_free_hour_cash').val();
      var withdrawalfee_free_times_cash = $('#withdrawalfee_free_times_cash').val();

      var withdrawallimits_upper = $('#withdrawallimits_upper').val();
      var withdrawallimits_lower = $('#withdrawallimits_lower').val();
      var withdrawal_allow = $('#withdrawal_allow').val();
      var withdrawalfee = $('#withdrawalfee').val();
      var withdrawalfee_max = $('#withdrawalfee_max').val();
      var withdrawalfee_method = $('#withdrawalfee_method').val();
      // var withdrawalfee_method = $('input[class=withdrawalfee_method]:checked').val();
      var withdrawalfee_free_hour = $('#withdrawalfee_free_hour').val();
      var withdrawalfee_free_times = $('#withdrawalfee_free_times').val();

      var withdrawal_limitstime_gcash = $('#withdrawal_limitstime_gcash').val();
      var withdrawal_limitstime_gtoken = $('#withdrawal_limitstime_gtoken').val();
      var administrative_cost_ratio = $('#administrative_cost_ratio').val();


      // 優惠設定
      // var activity_first_deposit_enable = $('#activity_first_deposit_enable').val();
      // var activity_first_deposit_enable = $('#activity_first_deposit_enable').prop('checked');
      // var activity_first_deposit_amount = $('#activity_first_deposit_amount').val();
      // var activity_first_deposit_rate = $('#activity_first_deposit_rate').val();
      // var activity_first_deposit_times = $('#activity_first_deposit_times').val();
      // var activity_first_deposit_upper = $('#activity_first_deposit_upper').val();

      // var activity_first_onlinepayment_enable = $('#activity_first_onlinepayment_enable').val();
      // var activity_first_onlinepayment_enable = $('#activity_first_onlinepayment_enable').prop('checked');
      // var activity_first_onlinepayment_amount = $('#activity_first_onlinepayment_amount').val();
      // var activity_first_onlinepayment_rate = $('#activity_first_onlinepayment_rate').val();
      // var activity_first_onlinepayment_times = $('#activity_first_onlinepayment_times').val();
      // var activity_first_onlinepayment_upper = $('#activity_first_onlinepayment_upper').val();

      // var activity_deposit_preferential_enable = $('#activity_deposit_preferential_enable').val();
      // var activity_deposit_preferential_enable = $('#activity_deposit_preferential_enable').prop('checked');
      // var activity_deposit_preferential_amount = $('#activity_deposit_preferential_amount').val();
      // var activity_deposit_preferential_rate = $('#activity_deposit_preferential_rate').val();
      // var activity_deposit_preferential_times = $('#activity_deposit_preferential_times').val();
      // var activity_deposit_preferential_upper = $('#activity_deposit_preferential_upper').val();

      // var activity_onlinepayment_preferential_enable = $('#activity_onlinepayment_preferential_enable').val();
      // var activity_onlinepayment_preferential_enable = $('#activity_onlinepayment_preferential_enable').prop('checked');
      // var activity_onlinepayment_preferential_amount = $('#activity_onlinepayment_preferential_amount').val();
      // var activity_onlinepayment_preferential_rate = $('#activity_onlinepayment_preferential_rate').val();
      // var activity_onlinepayment_preferential_times = $('#activity_onlinepayment_preferential_times').val();
      // var activity_onlinepayment_preferential_upper = $('#activity_onlinepayment_preferential_upper').val();

      // var activity_register_preferential_enable = $('#activity_register_preferential_enable').val();
      var activity_register_preferential_enable = $('#activity_register_preferential_enable').prop('checked');
      // var activity_register_preferential_adminadd = $('#activity_register_preferential_adminadd').val();
      var activity_register_preferential_adminadd = $('#activity_register_preferential_adminadd').prop('checked');
      //var activity_register_preferential_amount = $('#activity_register_preferential_amount').val();
      //var activity_register_preferential_audited = $('#activity_register_preferential_audited').val();

      // var activity_daily_checkin_enable = $('#activity_daily_checkin_enable').val();
      // var activity_daily_checkin_enable = $('#activity_daily_checkin_enable').prop('checked');
      // var activity_daily_checkin_days = $('#activity_daily_checkin_days').val();
      // var activity_daily_checkin_amount = $('#activity_daily_checkin_amount').val();
      // var activity_daily_checkin_rate = $('#activity_daily_checkin_rate').val();

      // 等級狀態是否啟用
      if(status) {
        var status_value = 1;
      } else {
        var status_value = 0;
      }

      // 點卡支付手續費是否啟用
      // if(pointcardfee_member_rate_enable) {
      //   var pointcardfee_member_rate_enable_value = 1;
      // } else {
      //   var pointcardfee_member_rate_enable_value = 0;
      // }

      // 首次儲值公司入款優惠是否啟用
      // if(activity_first_deposit_enable) {
      //   var activity_first_deposit_enable_value = 1;
      // } else {
      //   var activity_first_deposit_enable_value = 0;
      // }

      // 首次儲值線上支付優惠是否啟用
      // if(activity_first_onlinepayment_enable) {
      //   var activity_first_onlinepayment_enable_value = 1;
      // } else {
      //   var activity_first_onlinepayment_enable_value = 0;
      // }

      // 公司入款優惠是否啟用
      // if(activity_deposit_preferential_enable) {
      //   var activity_deposit_preferential_enable_value = 1;
      // } else {
      //   var activity_deposit_preferential_enable_value = 0;
      // }

      // 線上支付優惠是否啟用
      // if(activity_onlinepayment_preferential_enable) {
      //   var activity_onlinepayment_preferential_enable_value = 1;
      // } else {
      //   var activity_onlinepayment_preferential_enable_value = 0;
      // }

      // 註冊送彩金是否啟用
      if(activity_register_preferential_enable) {
        var activity_register_preferential_enable_value = 1;
      } else {
        var activity_register_preferential_enable_value = 0;
      }

      // 註冊送彩金管端新增是否啟用
      if(activity_register_preferential_adminadd) {
        var activity_register_preferential_adminadd_value = 1;
      } else {
        var activity_register_preferential_adminadd_value = 0;
      }

      // 連續上線優惠是否啟用
      // if(activity_daily_checkin_enable) {
      //   var activity_daily_checkin_enable_value = 1;
      // } else {
      //   var activity_daily_checkin_enable_value = 0;
      // }

      if(confirm('".$tr['Do you save the settings']."') == true){
        $.post('add_member_grade_action.php?a=add_member_grade_setting',
          {
            gradename: gradename,
            grade_alert_status: grade_alert_status,
            status: status_value,
            deposit_rate: deposit_rate,
            notes: notes,


            deposit_allow: deposit_allow,
            depositlimits_upper: depositlimits_upper,
            depositlimits_lower: depositlimits_lower,
            apifastpay_allow,
            apifastpaylimits_upper,
            apifastpaylimits_lower,
            apifastpayfee_member_rate,
            // pointcard_allow: pointcard_allow,
            // pointcard_limits_upper: pointcard_limits_upper,
            // pointcard_limits_lower: pointcard_limits_lower,
            // pointcardfee_member_rate_enable: pointcardfee_member_rate_enable_value,
            // pointcardfee_member_rate: pointcardfee_member_rate,


            withdrawallimits_cash_upper: withdrawallimits_cash_upper,
            withdrawallimits_cash_lower: withdrawallimits_cash_lower,
            withdrawalcash_allow: withdrawalcash_allow,
            withdrawalfee_cash: withdrawalfee_cash,
            withdrawalfee_max_cash: withdrawalfee_max_cash,
            withdrawalfee_method_cash: withdrawalfee_method_cash,
            //withdrawalfee_free_hour_cash: withdrawalfee_free_hour_cash,
            //withdrawalfee_free_times_cash: withdrawalfee_free_times_cash,

            withdrawallimits_upper: withdrawallimits_upper,
            withdrawallimits_lower: withdrawallimits_lower,
            withdrawal_allow: withdrawal_allow,
            withdrawalfee: withdrawalfee,
            withdrawalfee_max: withdrawalfee_max,
            withdrawalfee_method: withdrawalfee_method,
            //withdrawalfee_free_hour: withdrawalfee_free_hour,
            //withdrawalfee_free_times: withdrawalfee_free_times,

            withdrawal_limitstime_gcash: withdrawal_limitstime_gcash,
            withdrawal_limitstime_gtoken: withdrawal_limitstime_gtoken,
            administrative_cost_ratio: administrative_cost_ratio,


            // activity_first_deposit_enable: activity_first_deposit_enable_value,
            // activity_first_deposit_amount: activity_first_deposit_amount,
            // activity_first_deposit_rate: activity_first_deposit_rate,
            // activity_first_deposit_times: activity_first_deposit_times,
            // activity_first_deposit_upper: activity_first_deposit_upper,

            // activity_first_onlinepayment_enable: activity_first_onlinepayment_enable_value,
            // activity_first_onlinepayment_amount: activity_first_onlinepayment_amount,
            // activity_first_onlinepayment_rate: activity_first_onlinepayment_rate,
            // activity_first_onlinepayment_times: activity_first_onlinepayment_times,
            // activity_first_onlinepayment_upper: activity_first_onlinepayment_upper,

            // activity_deposit_preferential_enable: activity_deposit_preferential_enable_value,
            // activity_deposit_preferential_amount: activity_deposit_preferential_amount,
            // activity_deposit_preferential_rate: activity_deposit_preferential_rate,
            // activity_deposit_preferential_times: activity_deposit_preferential_times,
            // activity_deposit_preferential_upper: activity_deposit_preferential_upper,

            // activity_onlinepayment_preferential_enable: activity_onlinepayment_preferential_enable_value,
            // activity_onlinepayment_preferential_amount: activity_onlinepayment_preferential_amount,
            // activity_onlinepayment_preferential_rate: activity_onlinepayment_preferential_rate,
            // activity_onlinepayment_preferential_times: activity_onlinepayment_preferential_times,
            // activity_onlinepayment_preferential_upper: activity_onlinepayment_preferential_upper,

            activity_register_preferential_enable: activity_register_preferential_enable_value,
            activity_register_preferential_adminadd: activity_register_preferential_adminadd_value,
            //activity_register_preferential_amount: activity_register_preferential_amount,
            //activity_register_preferential_audited: activity_register_preferential_audited

            // activity_daily_checkin_enable: activity_daily_checkin_enable_value,
            // activity_daily_checkin_days: activity_daily_checkin_days,
            // activity_daily_checkin_amount: activity_daily_checkin_amount,
            // activity_daily_checkin_rate: activity_daily_checkin_rate

          },
          function(result){
            $('#preview_result').html(result);
          }
        )
      } else {
        window.location.reload();
      }
    });
  });
  </script>
  ";


  // 切成 1 欄版面
  $indexbody_content = '
  <form id="grade_form">
  <table class="table table-hover">
    <thead>
    <th width="15%" class="text-center">'.$tr['field'].'</th>
    <th width="60%" class="text-center">'.$tr['content'].'</th>
    <th width="25%" class="text-center">'.$tr['description / action'].'</th>
    </thead>
    '.$show_list_html.'
  </table>
  </form>
  <hr>
  '.$btn_html.'
  <br>
  <div class="row">
    <div id="preview_result"></div>
  </div>
  ';


  // 將 checkbox 堆疊成 switch 的 css
  $extend_head = $extend_head. <<<HTML
    <script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
    <script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
    <link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>

    <script type="text/javascript" language="javascript" class="init">
        $(document).ready(function () {
            $("#grade_form").validationEngine();
        });
    </script>
HTML; 
  $extend_head = $extend_head. "
  <style>

  .other-switch > input[type=\"checkbox\"] {
      visibility:hidden;
  }

  .other-switch > label {
      cursor: pointer;
      height: 0px;
      position: relative;
      width: 40px;
  }

  .other-switch > label::before {
      background: rgb(0, 0, 0);
      box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
      border-radius: 8px;
      content: '';
      height: 16px;
      margin-top: 0px;
      margin-left: 0px;
      position:absolute;
      opacity: 0.3;
      transition: all 0.4s ease-in-out;
      width: 30px;
  }
  .other-switch > label::after {
      background: rgb(255, 255, 255);
      border-radius: 16px;
      box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
      content: '';
      height: 16px;
      left: 0px;
      margin-top: 0px;
      margin-left: 0px;
      position: absolute;
      top: 0px;
      transition: all 0.3s ease-in-out;
      width: 16px;
  }
  .other-switch > input[type=\"checkbox\"]:checked + label::before {
      background: inherit;
      opacity: 0.5;
  }
  .other-switch > input[type=\"checkbox\"]:checked + label::after {
      background: inherit;
      left: 16px;
  }



  .status-offer-switch > input[type=\"checkbox\"] {
      visibility:hidden;
  }

  .status-offer-switch > label {
      cursor: pointer;
      height: 0px;
      position: relative;
      width: 40px;
  }

  .status-offer-switch > label::before {
      background: rgb(0, 0, 0);
      box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
      border-radius: 8px;
      content: '';
      height: 16px;
      margin-top: -20px;
      margin-left: 5px;
      position:absolute;
      opacity: 0.3;
      transition: all 0.4s ease-in-out;
      width: 30px;
  }
  .status-offer-switch > label::after {
      background: rgb(255, 255, 255);
      border-radius: 16px;
      box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
      content: '';
      height: 16px;
      left: 0px;
      margin-top: -20px;
      margin-left: 5px;
      position: absolute;
      top: 0px;
      transition: all 0.3s ease-in-out;
      width: 16px;
  }
  .status-offer-switch > input[type=\"checkbox\"]:checked + label::before {
      background: inherit;
      opacity: 0.5;
  }
  .status-offer-switch > input[type=\"checkbox\"]:checked + label::after {
      background: inherit;
      left: 16px;
  }
  </style>
  ";


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  html 組合 end
  // -----------------------------------------------------------------------------------------------------------------------------------------------


} else {
  // 沒有登入的顯示提示俊息
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
