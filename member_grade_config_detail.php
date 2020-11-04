<?php
// ----------------------------------------------------------------------------
// Features:	后台-- 会员等级管理 , 显示详细会员等级资讯
// File Name:	member_grade_config_detail.php
// Author:		Yuan
// Related:   对应 member_grade_config.php 各项会员等级相关资讯管理
// DB Table:  root_member_grade
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主机及资料库设定
require_once dirname(__FILE__) ."/config.php";
// 支援多国语系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自订函式库
require_once dirname(__FILE__) ."/lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步纪录该 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 检查权限是否合法，允许就会放行。否则中止。
agent_permission();
// ----------------------------------------------------------------------------


if(isset($_GET['a']) AND $_SESSION['agent']->therole == 'R') {
  $member_grade_id = filter_var($_GET['a'], FILTER_SANITIZE_NUMBER_INT);
//  var_dump($_GET);
} else {
  die('(x)不合法的测试');
}


// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化变数
// 功能标题，放在标题列及meta
$function_title 		= $tr['member grade details'];
// 扩充 head 内的 css or js
$extend_head				= '';
// 放在结尾的 js
$extend_js					= '';
// body 内的主要内容
$indexbody_content	= '';
// 目前所在位置 - 配合选单位置让使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['homepage'].'</a></li>
  <li><a href="#">'.$tr['System Management'].'</a></li>
  <li><a href="member_grade_config.php">'.$tr['Member level management'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------
function get_select_html_withdrawal($allow)
{
    global $tr;
    $open_selected     = '';
    $maintain_selected = '';
    $close_selected    = '';
    switch ($allow->status) {
        case '1':
            $open_selected = 'selected';
            break;
        case '2':
            $maintain_selected = 'selected';
            break;
        default:
            $close_selected = 'selected';
            break;
    }

    // <option value="2" ' . $maintain_selected . '>维护</option>
    $html = '
  <div class="form-group">
    <select class="form-control" style="width:auto;" id="' . $allow->id . '">
      <option value="0" ' . $close_selected . '>'.$tr['off'].'</option>
      <option value="1" ' . $open_selected . '>'.$tr['Enabled'].'</option>
    </select>
  </div>
  ';

    return $html;
}



function get_select_html_fee_method($fee_method){
  global $tr;
  $open_selected     = '';
  $close_selected    = '';
  switch ($fee_method->status) {
    case '3':
      $open_selected = 'selected';
      break;
    default:
      $close_selected = 'selected';
      break;
  }

  $html = '
    <div class="form-group">
      <select class="form-control" style="width:auto;" id="' . $fee_method->id . '">
        <option value="1" ' . $close_selected . '>'.$tr['off'].'</option>
        <option value="3" ' . $open_selected . '>'.$tr['Enabled'].'</option>
      </select>
    </div>
  ';

  return $html;
}


function get_select_html($allow)
{
  global $tr;
  $open_selected = '';
  $maintain_selected = '';
  $close_selected = '';
  switch ($allow->status) {
    case '1':
      $open_selected = 'selected';
      break;
    case '2':
      $maintain_selected = 'selected';
      break;
    default:
      $close_selected = 'selected';
      break;
  }

  $html = '
  <div class="form-group">
    <select class="form-control" style="width:auto;" id="'.$allow->id.'">
      <option value="0" '.$close_selected.'>'.$tr['off'].'</option>
      <option value="1" '.$open_selected.'>'.$tr['Enabled'].'</option>
      <option value="2" '.$maintain_selected.'>'.$tr['Maintenance'].'</option>
    </select>
  </div>
  ';

  return $html;
}

function get_input_html($input_content, $isdisabled = '')
{
  $html = '
  <input type="'.$input_content->type.'" class="form-control" placeholder="'.$input_content->placeholder.'" aria-describedby="basic-addon1" id="'.$input_content->id.'" value="'.$input_content->value.'" '.$isdisabled.'>
  ';

  return $html;
}

function get_checkbox_switch_html($switch_content, $isdisabled = '')
{
  $is_enable = ($switch_content->value == 0) ? '' : 'checked';

  if (!empty($isdisabled)) {
    $is_enable = $isdisabled;
  }

  $html = '
  <div class="col-12 col-md-12 status-offer-switch pull-left">
    <input id="'.$switch_content->id.'" name="'.$switch_content->id.'" class="checkbox_switch" value="0" type="checkbox" '.$is_enable.'/>
    <label for="'.$switch_content->id.'" class="label-success"></label>
  </div>
  ';

  return $html;
}

// 公司入款设定
function get_deposit_setting_html($html_content)
{
  $html = '
  <tr>
    <td>'.$html_content->colname.'</td>
    <td>
      <table class="table table-bordered">
        <tr class="active text-center">
          <td>'.$html_content->table_colname->is_enable.'</td>
          <td class="info">'.$html_content->table_colname->lower.'</td>
          <td class="info">'.$html_content->table_colname->upper.'</td>
        </tr>
        <tr>
          <td>
            '.get_select_html($html_content->allow).'
          </td>
          <td>
            '.get_input_html($html_content->lower_inputcontent).'
          </td>
          <td>
            '.get_input_html($html_content->upper_inputcontent).'
          </td>
        </tr>
      </table>
    </td>
    <td></td>
  </tr>
  ';

  return $html;
}

// API入款设定
function get_apideposit_setting_html($html_content)
{
  $html = '
  <tr>
    <td>'.$html_content->colname.'</td>
    <td>
      <table class="table table-bordered">
        <tr class="active text-center">
          <td>'.$html_content->table_colname->is_enable.'</td>
          <td class="info">'.$html_content->table_colname->lower.'</td>
          <td class="info">'.$html_content->table_colname->upper.'</td>
          <td class="info">'.$html_content->table_colname->fee.'</td>
        </tr>
        <tr>
          <td>
            '.get_select_html($html_content->allow).'
          </td>
          <td>
            '.get_input_html($html_content->lower_inputcontent).'
          </td>
          <td>
            '.get_input_html($html_content->upper_inputcontent).'
          </td>
          <td>
            <div class="input-group">
              '.get_input_html($html_content->fee_inputcontent).'
              <span class="input-group-addon" id="basic-addon1">%</span>
            </div>
          </td>
        </tr>
      </table>
    </td>
    <td></td>
  </tr>
  ';

  return $html;
}

// 取款设定
function get_withdrawal_setting_html($html_content)
{
  global $tr;
  $html = '
  <tr>
    <td>'.$html_content->colname.'</td>
    <td>
      <div class="row">
        <div class="col-12 col-md-7">
          '.get_upper_lower_input_html($html_content->upper_lower_input).'
        </div>
      </div>
      <br>
      <table class="table table-bordered">
        <tr class="active text-center">
          <td>'.$html_content->table_colname->is_enable.'</td>
          <td>'.$html_content->table_colname->fee_is_enable.'</td>
          <td>'.$html_content->table_colname->fee.'</td>
          <td class="info">'.$html_content->table_colname->fee_upper.'</td>
        </tr>
        <tr>
          <td>
            '.get_select_html_withdrawal($html_content->allow).'
          </td>
          <td>
            '.get_select_html_fee_method($html_content->fee_method).'
          </td>
          <td>
            <div class="input-group">
              '.get_input_html($html_content->fee_inputcontent).'
              <span class="input-group-addon" id="basic-addon1">%</span>
            </div>
          </td>
          <td>
            '.get_input_html($html_content->fee_upper_inputcontent).'
          </td>
        </tr>
      </table>
    </td>
    <td>
      1. '.$tr['Only upper and lower limits can be entered for positive integers.'] .'<br>  
      2. 手续费收取方式: 关闭=免手续费, 启用=每次收取    
    </td>
  </tr>
  ';
  /*
  2. '.$tr['X withdrawals within X hours free of charge. X and Y can only enter positive integers.'].'
  '.get_withdrawalfee_method_html($html_content->fee_method).'
  */

  return $html;
}

// 取款上下限限额
function get_upper_lower_input_html($upper_lower_input_content)
{
  global $tr;
  $html = '
  <div class="input-group">
    <input type="'.$upper_lower_input_content->type.'" class="form-control" placeholder="'.$tr['withdrawal Lower limit'].'" aria-describedby="basic-addon1" id="'.$upper_lower_input_content->lower->id.'" value="'.$upper_lower_input_content->lower->value.'">
    <span class="input-group-addon" id="basic-addon1">~</span>
    <input type="'.$upper_lower_input_content->type.'" class="form-control" placeholder="'.$tr['withdrawal Upper limit'].'" aria-describedby="basic-addon1" id="'.$upper_lower_input_content->upper->id.'" value="'.$upper_lower_input_content->upper->value.'">
  </div>
  ';

  return $html;
}
/*
// 手续费收取方式
function get_withdrawalfee_method_html($radio_content)
{
  global $tr;
  $everytime_checked = '';
  $noneed_checked = '';
  $time_checked = '';
  switch ($radio_content->status) {
    case '1':
      $noneed_checked = 'checked';
      break;
    case '2':
      $time_checked = 'checked';
      break;
    default:
      $everytime_checked = 'checked';
      break;
  }

  $html = '
  <div class="radio">
    <label>
      <input type="radio" class="'.$radio_content->radio_class.'" name="'.$radio_content->radio_class.'" value="3" '.$everytime_checked.'>
      '.$tr['Each charge'].'
    </label>
  </div>
  <div class="radio">
    <label>
      <input type="radio" class="'.$radio_content->radio_class.'" name="'.$radio_content->radio_class.'" value="1" '.$noneed_checked.'>
      '.$tr['Free of fee'].'
    </label>
  </div>
  ';
  /*
    <div class="radio">
    <label>
      <input type="radio" class="'.$radio_content->radio_class.'" name="'.$radio_content->radio_class.'" value="2" '.$time_checked.'>
        <div class="row">
          <div class="col-12 col-md-8">
            <div class="input-group">
              <input type="text" class="form-control" placeholder="" aria-describedby="basic-addon1" id="'.$radio_content->free_times_input->hour->id.'" value="'.$radio_content->free_times_input->hour->value.'">
              <span class="input-group-addon" id="basic-addon1">'.$tr['Take money within hours'].'</span>
              <input type="text" class="form-control" placeholder="" aria-describedby="basic-addon1" id="'.$radio_content->free_times_input->free_count->id.'" value="'.$radio_content->free_times_input->free_count->value.'">
              <span class="input-group-addon" id="basic-addon1">'.$tr['Free of charge'].'</span>
            </div>
          </div>
        </div>

    </label>
  </div>
  */

  //return $html;
//}

// 取款帐号限制
function get_withdrawal_accountlimit_html($html_content)
{
  global $tr;
  // 取款帐号限制

  $html = '
  <tr>
    <td>'.$html_content->colname.'</td>
    <td>
      <div class="row">
        <div class="col-12 col-md-7">
          <div class="input-group">
            '.get_input_html($html_content->inputcontent).'
            <span class="input-group-addon" id="basic-addon1">'.$tr['minutes for withdrawals one time'].'</span>
          </div>
        </div>
      </div>
    </td>
    <td>'.$tr['deposit settings'].'</td>
  </tr>
  ';

  return $html;
}

// 优惠设定-入款优惠
function get_deposit_preferential_html($coldata, $table_colname, $colname)
{
  foreach ($coldata as $key => $value) {
    $str = explode('_', $key);

    $arr[end($str)] = (object)[
      'id' => $key,
      'value' => $value,
      'type' => 'number',
      'placeholder' => ''
    ];
  }
  $data = (object)$arr;

  $html = '
  <tr>
    <td>'.$colname.'</td>
    <td>
      <table class="table table-bordered">
        <tr class="active text-center">
          <td>'.$table_colname->is_enable.'</td>
          <td>'.$table_colname->amount.'</td>
          <td>'.$table_colname->rate.'</td>
          <td class="info">'.$table_colname->times.'</td>
          <td class="info">'.$table_colname->upper.'</td>
        </tr>
        <tr>
          <td>
            '.get_checkbox_switch_html($data->enable, 'disabled').'
          </td>
          <td>
            '.get_input_html($data->amount, 'disabled').'
          </td>
          <td>
            '.get_input_html($data->rate, 'disabled').'
          </td>
          <td>
            '.get_input_html($data->times, 'disabled').'
          </td>
          <td>
            '.get_input_html($data->upper, 'disabled').'
          </td>
        </tr>
      </table>
    </td>
    <td>come soon</td>
  </tr>
  ';

  unset($arr);

  return $html;
}


/*
  *抓取預設會員等級設定資訊
*/
function default_member_grade_setting(){
  $sql = "SELECT * FROM root_member_grade WHERE id='1';";
  $result = runSQLall($sql);

  return $result[1];
}


// 有登入，且身份为管理员 R 才可以使用这个功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  $member_grade_list_sql = "SELECT * FROM root_member_grade WHERE id = '$member_grade_id' ORDER BY id LIMIT 1;";
  // var_dump($member_grade_list_sql);
  $member_grade_list_sql_result = runSQLall($member_grade_list_sql);
  // var_dump($member_grade_list_sql_result);

  //
  $default_member_grade = default_member_grade_setting();

  $show_list_html = '';

  if($member_grade_list_sql_result[0] >= 1) {

    $member_grade_list = $member_grade_list_sql_result[1];
    // var_dump($member_grade_list);

    // -----------------------------------------------------------------------------------------------------------------------------------------------
    //  表格内容 html 组合 start
    // -----------------------------------------------------------------------------------------------------------------------------------------------


    $show_list_html = $show_list_html.'
    <tr class="success">
      <td>'.$tr['General Settings'].'</td>
      <td class="text-center">
        <h4><strong></strong></h4>
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
            <input type="text" class="form-control  validate[maxSize[50]]" maxlength="50" id="gradename" placeholder="请填入等级名称。('.$tr['max'].'50'.$tr['word'].')" value="'.$member_grade_list->gradename.'">
          </div>
        </div>
      </td>
      <td></td>
    </tr>
    ';

    // 判断等级状态是否启用
    if ($member_grade_list->status == '1') {
      $status_checked = 'checked';
    } else {
      $status_checked = '';
    }

    // 判断等级状态的等级设定
    $normal_selected = '';
    $primary_selected = '';
    $warning_selected = '';
    $danger_selected = '';
    $default_selected = '';
    switch ($member_grade_list->grade_alert_status) {
      case 'normal':
        $normal_selected = 'selected';
        break;
      case 'primary':
        $primary_selected = 'selected';
        break;
      case 'warning':
        $warning_selected = 'selected';
        break;
      case 'danger':
        $danger_selected = 'selected';
        break;
      default:
        $default_selected = 'selected';
        break;
    }

    if($member_grade_id == $default_member_grade->id){
      $show_list_html = $show_list_html.'
        <tr>
          <td>'.$tr['Level Status'].'</td>
          <td>
            <p class="text-danger">此为预设会员等级设定，不可调整等级状态</p>
            <div class="row">
              <div class="col-12 col-md-4">
                <table class="table table-bordered" style="display:none">
                  <tr class="active text-center">
                    <td>'.$tr['enabled'].'</td>
                    <td>'.$tr['level setting'].'</td>
                  </tr>

                  <tr>
                    <td>
                      <div class="col-12 col-md-12 status-offer-switch pull-left">
                        <input id="status" name="status" class="checkbox_switch" value="0" type="checkbox" '.$status_checked.'/>
                        <label for="status" class="label-success"></label>
                      </div>
                    </td>
                    <td>
                      <div class="form-group" style="display:none">
                        <select class="form-control" style="width:auto;" id="grade_alert_status">
                          <option value="default" '.$default_selected.'>'.$tr['grade default'].'</option>
                          <option value="normal" '.$normal_selected.'>'.$tr['grade normal'].'</option>
                          <option value="primary" '.$primary_selected.'>'.$tr['grade primary'].'</option>
                          <option value="warning" '.$warning_selected.'>'.$tr['grade warning'].'</option>
                          <option value="danger" '.$danger_selected.'>'.$tr['grade danger'].'</option>
                        </select>
                      </div>
                    </td>
                  </tr>

                </table>
              </div>
              </div>
          </td>
        </tr>
      ';
    }else{
    $show_list_html = $show_list_html.'
    <tr>
      <td>'.$tr['Level Status'].'</td>
      <td>
        <div class="row">
          <div class="col-12 col-md-4">
            <table class="table table-bordered">
              <tr class="active text-center">
                <td>'.$tr['enabled'].'</td>
                <td>'.$tr['level setting'].'</td>
              </tr>

              <tr>
                <td>
                  <div class="col-12 col-md-12 status-offer-switch pull-left">
                    <input id="status" name="status" class="checkbox_switch" value="0" type="checkbox" '.$status_checked.'/>
                    <label for="status" class="label-success"></label>
                  </div>
                </td>
                <td>
                  <div class="form-group">
                    <select class="form-control" style="width:auto;" id="grade_alert_status">
                      <option value="normal" '.$normal_selected.'>'.$tr['grade normal'].'</option>
                      <option value="primary" '.$primary_selected.'>'.$tr['grade primary'].'</option>
                      <option value="warning" '.$warning_selected.'>'.$tr['grade warning'].'</option>
                      <option value="danger" '.$danger_selected.'>'.$tr['grade danger'].'</option>
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
    }
    $show_list_html = $show_list_html.'
    <tr>
      <td>'.$tr['Franchise to cash deposit audit ratio'].'</td>
      <td>
        <div class="row">
          <div class="col-12 col-md-7">
            <div class="input-group">
              <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="deposit_rate" value="'.$member_grade_list->deposit_rate.'">
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
        <textarea class="form-control  validate[maxSize[500]]" rows="5" maxlength="500" id="notes" placeholder="(最多500字符)">'.$member_grade_list->notes.'</textarea>
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

    $deposit_table_colname = (object)[
      'is_enable' => $tr['enabled'],//'是否启用',
      'lower' => $tr['lower limit'],//'限额下限',
      'upper' => $tr['ceiling'],//'限额上限'
    ];

    $deposit_company_setting = (object)[
      'colname' => $tr['company deposit value'],//'公司入款储值',
      'table_colname' => $deposit_table_colname,
      'allow' => (object)[
        'id' => 'deposit_allow',
        'status' => $member_grade_list->deposit_allow,
      ],
      'lower_inputcontent' => (object)[
        'id' => 'depositlimits_lower',
        'value' => $member_grade_list->depositlimits_lower,
        'type' => 'number',
        'placeholder' => $tr['Company Deposit Limit'],//'公司入款限额下限'
      ],
      'upper_inputcontent' => (object)[
        'id' => 'depositlimits_upper',
        'value' => $member_grade_list->depositlimits_upper,
        'type' => 'number',
        'placeholder' => $tr['company cap limit'],//'公司入款限额上限'
      ]
    ];
    $show_list_html = $show_list_html.get_deposit_setting_html($deposit_company_setting);


    // $onlinepayment_setting = (object)[
    //   'colname' => '线上支付储值',
    //   'table_colname' => $deposit_table_colname,
    //   'allow' => (object)[
    //     'id' => 'onlinepayment_allow',
    //     'status' => $member_grade_list->onlinepayment_allow,
    //   ],
    //   'lower_inputcontent' => (object)[
    //     'id' => 'onlinepaymentlimits_lower',
    //     'value' => $member_grade_list->onlinepaymentlimits_lower,
    //     'type' => 'number',
    //     'placeholder' => '线上支付限额下限'
    //   ],
    //   'upper_inputcontent' => (object)[
    //     'id' => 'onlinepaymentlimits_upper',
    //     'value' => $member_grade_list->onlinepaymentlimits_upper,
    //     'type' => 'number',
    //     'placeholder' => '线上支付限额上限'
    //   ]
    // ];
    // $show_list_html = $show_list_html.get_deposit_setting_html($onlinepayment_setting);


    // $pointcard_setting = (object)[
    //   'colname' => '点卡支付储值',
    //   'table_colname' => $deposit_table_colname,
    //   'allow' => (object)[
    //     'id' => 'pointcard_allow',
    //     'status' => $member_grade_list->pointcard_allow,
    //   ],
    //   'lower_inputcontent' => (object)[
    //     'id' => 'pointcard_limits_lower',
    //     'value' => $member_grade_list->pointcard_limits_lower,
    //     'type' => 'number',
    //     'placeholder' => '点卡支付限额下限'
    //   ],
    //   'upper_inputcontent' => (object)[
    //     'id' => 'pointcard_limits_upper',
    //     'value' => $member_grade_list->pointcard_limits_upper,
    //     'type' => 'number',
    //     'placeholder' => '点卡支付限额上限'
    //   ]
    // ];
    // $show_list_html = $show_list_html.get_deposit_setting_html($pointcard_setting);


    // 判断点卡支付手续费是否启用
    // if ($member_grade_list->pointcardfee_member_rate_enable == '1') {
    //   $pointcardfee_member_rate_checked = 'checked';
    // } else {
    //   $pointcardfee_member_rate_checked = '';
    // }

    // $show_list_html = $show_list_html.'
    // <tr>
    //   <td>点卡支付手续费</td>
    //   <td>
    //     <div class="row">
    //       <div class="col-12 col-md-7">
    //         <table class="table table-bordered">
    //           <tr class="active text-center">
    //             <td width="10%">是否启用</td>
    //             <td>费用比例</td>
    //           </tr>
    //           <tr>
    //             <td>
    //               <div class="col-12 col-md-12 status-offer-switch pull-left">
    //                 <input id="pointcardfee_member_rate_enable" name="pointcardfee_member_rate_enable" class="checkbox_switch" value="0" type="checkbox" '.$pointcardfee_member_rate_checked.'/>
    //                 <label for="pointcardfee_member_rate_enable" class="label-success"></label>
    //               </div>
    //             </td>
    //             <td>
    //               <div class="input-group">
    //                 <input type="number" class="form-control" placeholder="会员负担" aria-describedby="basic-addon1" id="pointcardfee_member_rate" value="'.$member_grade_list->pointcardfee_member_rate.'">
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

    $apideposit_table_colname = clone $deposit_table_colname;
    $apideposit_table_colname->fee = $tr['Fee'];
    $apifastpay_setting = (object)[
      'colname' => $tr['online payment stored value'],//'线上支付储值',
      'table_colname' => $apideposit_table_colname,
      'allow' => (object)[
        'id' => 'apifastpay_allow',
        'status' => $member_grade_list->apifastpay_allow,
      ],
      'lower_inputcontent' => (object)[
        'id' => 'apifastpaylimits_lower',
        'value' => $member_grade_list->apifastpaylimits_lower,
        'type' => 'number',
        'placeholder' => $tr['Online Payment Limit'],//'线上支付限额下限'
      ],
      'upper_inputcontent' => (object)[
        'id' => 'apifastpaylimits_upper',
        'value' => $member_grade_list->apifastpaylimits_upper,
        'type' => 'number',
        'placeholder' => $tr['Online Payment Caps'],//'线上支付限额上限'
      ],
      'fee_inputcontent' => (object) [
        // 點卡廢棄; 暫時先使用點卡欄位
        'id' => 'apifastpayfee_member_rate',
        'value' => $member_grade_list->pointcardfee_member_rate,
        'type' => 'number',
        'placeholder' => ''
      ]
    ];
    $show_list_html = $show_list_html.get_apideposit_setting_html($apifastpay_setting);


    $show_list_html = $show_list_html.'
    <tr class="success">
      <td></td>
      <td class="text-center">
        <h4><strong>'.$tr['Withdrawal set'].'</strong></h4>
      </td>
      <td></td>
    </tr>
    ';

    $withdrawal_table_colname = (object)[
      'is_enable' => $tr['enabled'],//'是否启用',
      'fee' => $tr['Fee'] ,//'手续费',
      'fee_upper' => $tr['Fee limit'],//'手续费上限'
      'fee_is_enable' => '手续费收取方式'
    ];

    $cash_withdrawal_setting = (object)[
      'colname' => $tr['Join the gold withdrawal set'],//'现金取款设定',
      'table_colname' => $withdrawal_table_colname,
      'allow' => (object)[
        'id' => 'withdrawalcash_allow',
        'status' => $member_grade_list->withdrawalcash_allow,
      ],
      'upper_lower_input' => (object)[
        'type' => 'number',
        'lower' => (object)[
          'id' => 'withdrawallimits_cash_lower',
          'value' => $member_grade_list->withdrawallimits_cash_lower
        ],
        'upper' => (object)[
          'id' => 'withdrawallimits_cash_upper',
          'value' => $member_grade_list->withdrawallimits_cash_upper
        ]
      ],
      'fee_inputcontent' => (object)[
        'id' => 'withdrawalfee_cash',
        'value' => $member_grade_list->withdrawalfee_cash,
        'type' => 'number',
        'placeholder' => ''
      ],
      'fee_upper_inputcontent' => (object)[
        'id' => 'withdrawalfee_max_cash',
        'value' => $member_grade_list->withdrawalfee_max_cash,
        'type' => 'number',
        'placeholder' => ''
      ],
      'fee_method' => (object)[
        'id' => 'withdrawalfee_method_cash',
        'free_times_input' => (object)[
          'hour' => (object)[
            'id' => 'withdrawalfee_free_hour_cash',
            'value' => $member_grade_list->withdrawalfee_free_hour_cash
          ],
          'free_count' => (object)[
            'id' => 'withdrawalfee_free_times_cash',
            'value' => $member_grade_list->withdrawalfee_free_times_cash
          ]
        ],
        'status' => $member_grade_list->withdrawalfee_method_cash
      ],
    ];

    $show_list_html = $show_list_html.get_withdrawal_setting_html($cash_withdrawal_setting);


    $token_withdrawal_setting = (object)[
      'colname' => $tr['Cash withdrawal settings'],//'游戏币取款设定',
      'table_colname' => $withdrawal_table_colname,
      'allow' => (object)[
        'id' => 'withdrawal_allow',
        'status' => $member_grade_list->withdrawal_allow,
      ],
      'fee_method_status' => $member_grade_list->withdrawalfee_method,
      'upper_lower_input' => (object)[
        'type' => 'number',
        'lower' => (object)[
          'id' => 'withdrawallimits_lower',
          'value' => $member_grade_list->withdrawallimits_lower
        ],
        'upper' => (object)[
          'id' => 'withdrawallimits_upper',
          'value' => $member_grade_list->withdrawallimits_upper
        ]
      ],
      'fee_inputcontent' => (object)[
        'id' => 'withdrawalfee',
        'value' => $member_grade_list->withdrawalfee,
        'type' => 'number',
        'placeholder' => ''
      ],
      'fee_upper_inputcontent' => (object)[
        'id' => 'withdrawalfee_max',
        'value' => $member_grade_list->withdrawalfee_max,
        'type' => 'number',
        'placeholder' => ''
      ],
      'fee_method' => (object)[
        'id' => 'withdrawalfee_method',
        'free_times_input' => (object)[
          'hour' => (object)[
            'id' => 'withdrawalfee_free_hour',
            'value' => $member_grade_list->withdrawalfee_free_hour
          ],
          'free_count' => (object)[
            'id' => 'withdrawalfee_free_times',
            'value' => $member_grade_list->withdrawalfee_free_times
          ]
        ],
        'status' => $member_grade_list->withdrawalfee_method
      ],
    ];
    $show_list_html = $show_list_html.get_withdrawal_setting_html($token_withdrawal_setting);


    $withdrawal_accountlimit_setting = (object)[
      'colname' => $tr['Affiliate withdrawal limits account'],//'现金取款限制帐号',
      'inputcontent' => (object)[
        'id' => 'withdrawal_limitstime_gcash',
        'value' => $member_grade_list->withdrawal_limitstime_gcash,
        'type' => 'number',
        'placeholder' => ''
      ]
    ];
    $show_list_html = $show_list_html.get_withdrawal_accountlimit_html($withdrawal_accountlimit_setting);


    $withdrawal_accountlimit_setting = (object)[
      'colname' => $tr['cash withdrawal limit account'],//'游戏币取款限制帐号',
      'inputcontent' => (object)[
        'id' => 'withdrawal_limitstime_gtoken',
        'value' => $member_grade_list->withdrawal_limitstime_gtoken,
        'type' => 'number',
        'placeholder' => ''
      ]
    ];
    $show_list_html = $show_list_html.get_withdrawal_accountlimit_html($withdrawal_accountlimit_setting);

    $show_list_html = $show_list_html.'
    <tr>
      <td>'.$tr['cash withdrawal auditing administrative costs ratio'].'</td>
      <td>
        <div class="row">
          <div class="col-12 col-md-7">
            <div class="input-group">
              <input type="number" class="form-control" placeholder="" aria-describedby="basic-addon1" id="administrative_cost_ratio" value="'.$member_grade_list->administrative_cost_ratio.'">
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


    $deposit_table_colname = (object)[
      'is_enable' => $tr['Enabled or not'],//'是否启用',
      'amount' => $tr['deposit amount'],//'存款金额',
      'rate' => $tr['Preferential ratio'],//'优惠比例',
      'times' => $tr['audit multiple'],//'稽核倍数',
      'upper' => $tr['Bonus Limit'],//'优惠上限'
    ];

    // $activity_first_deposit_arr = json_decode($member_grade_list->activity_first_deposit, false);
    // $show_list_html = $show_list_html.get_deposit_preferential_html($activity_first_deposit_arr, $deposit_table_colname, '首次储值公司入款优惠');


    // $activity_first_onlinepayment_arr = json_decode($member_grade_list->activity_first_onlinepayment, false);
    // $show_list_html = $show_list_html.get_deposit_preferential_html($activity_first_onlinepayment_arr, $deposit_table_colname, '首次储值线上支付优惠');


    // $activity_deposit_preferential_arr = json_decode($member_grade_list->activity_deposit_preferential, true);
    // $show_list_html = $show_list_html.get_deposit_preferential_html($activity_deposit_preferential_arr, $deposit_table_colname, '公司入款优惠');


    // $activity_onlinepayment_preferential_arr = json_decode($member_grade_list->activity_onlinepayment_preferential, true);
    // $show_list_html = $show_list_html.get_deposit_preferential_html($activity_onlinepayment_preferential_arr, $deposit_table_colname, '线上支付优惠');


    $activity_register_preferential_arr = json_decode($member_grade_list->activity_register_preferential, true);

    // 判断注册送彩金优惠是否启用
    if ($activity_register_preferential_arr['activity_register_preferential_enable'] == '1') {
      $activity_register_preferential_checked = 'checked';
    } else {
      $activity_register_preferential_checked = '';
    }

    // 判断注册送彩金优惠管端新增是否启用
    if ($activity_register_preferential_arr['activity_register_preferential_adminadd'] == '1') {
      $activity_register_preferential_adminadd_checked = 'checked';
    } else {
      $activity_register_preferential_adminadd_checked = '';
    }


//     $show_list_html = $show_list_html.'
//     <tr>
//       <td>'.$tr['register send bonus'].'</td>
//       <td>
//         <table class="table table-bordered">
//           <tr class="active text-center">
//             <td width="10%">'.$tr['Enabled or not'].'</td>
//             <td width="10%">'.$tr['Pipe Added'].'</td>
//             <td class="info">'.$tr['gift amount'].'</td>
//             <td class="info">'.$tr['audit amount'].'</td>
//           </tr>

//           <tr>
//             <td>
//               <div class="col-12 col-md-12 status-offer-switch pull-left">
//                 <input id="activity_register_preferential_enable" name="activity_register_preferential_enable" class="checkbox_switch" value="0" type="checkbox" '.$activity_register_preferential_checked.'/>
//                 <label for="activity_register_preferential_enable" class="label-success"></label>
//               </div>
//             </td>
//             <td>
//               <div class="col-12 col-md-12 status-offer-switch pull-left">
//                 <input id="activity_register_preferential_adminadd" name="activity_register_preferential_adminadd" class="checkbox_switch" value="0" type="checkbox" '.$activity_register_preferential_adminadd_checked.'/>
//                 <label for="activity_register_preferential_adminadd" class="label-success"></label>
//               </div>
//             </td>
//             <td>
//               <input type="number" class="form-control" placeholder="" id="activity_register_preferential_amount" value="'.$activity_register_preferential_arr['activity_register_preferential_amount'].'">
//             </td>
//             <td>
//               <input type="number" class="form-control" placeholder="" id="activity_register_preferential_audited" value="'.$activity_register_preferential_arr['activity_register_preferential_audited'].'">
//             </td>
//           </tr>

//         </table>
//       </td>
//       <td></td>
//     </tr>
//     ';

//     $activity_daily_checkin_arr = json_decode($member_grade_list->activity_daily_checkin, true);

//     // 判断注册送彩金优惠是否启用
//     if ($activity_daily_checkin_arr['activity_daily_checkin_enable'] == '1') {
//       $activity_daily_checkin_checked = 'checked';
//     } else {
//       $activity_daily_checkin_checked = '';
//     }

//     $show_list_html = $show_list_html.'
//     <tr>
//       <td>连续上线优惠</td>
//       <td>
//         <table class="table table-bordered">
//           <tr class="active text-center">
//             <td>是否启用</td>
//             <td class="info">天数</td>
//             <td class="info">赠送金额</td>
//             <td class="info">稽核倍数</td>
//           </tr>

//           <tr>
//             <td>
//               <div class="col-12 col-md-12 status-offer-switch pull-left">
//                 <input id="activity_daily_checkin_enable" name="activity_daily_checkin_enable" class="checkbox_switch" value="0" type="checkbox" '.$activity_daily_checkin_checked.' disabled/>
//                 <label for="activity_daily_checkin_enable" class="label-success"></label>
//               </div>
//             </td>
//             <td>
//               <input type="number" class="form-control" placeholder="" id="activity_daily_checkin_days" value="'.$activity_daily_checkin_arr['activity_daily_checkin_days'].'" disabled>
//             </td>
//             <td>
//               <input type="number" class="form-control" placeholder="" id="activity_daily_checkin_amount" value="'.$activity_daily_checkin_arr['activity_daily_checkin_amount'].'" disabled>
//             </td>
//             <td>
//               <input type="number" class="form-control" placeholder="" id="activity_daily_checkin_rate" value="'.$activity_daily_checkin_arr['activity_daily_checkin_rate'].'" disabled>
//             </td>
//           </tr>

//         </table>
//       </td>
//       <td>come soon</td>
//     </tr>
//     ';


    $btn_html = '
    <p align="right">
      <button id="submit_add_grade_data" class="btn btn-success">'.$tr['Save'].'</button>
      <button id="submit_change_member_data" class="btn btn-danger" onclick="javascript:location.href=\'member_grade_config.php\'">'.$tr['Cancel'].'</button>
    </p>';

/*
var withdrawalfee_free_hour_cash = $('#withdrawalfee_free_hour_cash').val();
var withdrawalfee_free_times_cash = $('#withdrawalfee_free_times_cash').val();
var withdrawalfee_free_hour = $('#withdrawalfee_free_hour').val();
var withdrawalfee_free_times = $('#withdrawalfee_free_times').val();
withdrawalfee_free_hour_cash: withdrawalfee_free_hour_cash,
withdrawalfee_free_times_cash: withdrawalfee_free_times_cash,
withdrawalfee_free_hour: withdrawalfee_free_hour,
withdrawalfee_free_times: withdrawalfee_free_times,
*/
    $extend_js = $extend_js."
    <script>
    $(document).ready(function() {
      
      // 預設会员等级設定 状态更改为无法设置为停用 
      var id = '".$member_grade_id."';
      if( id == '1'){
        $('#status').change(function(){
          if($('#status').prop('checked') == '0'){
            alert('此会员等级设定为预设会员等级设定，无法设置为停用');
            $('#status').prop('checked',true);
          }
        });
      }

      $('#submit_add_grade_data').click(function(){
        // $('#submit_add_grade_data').attr('disabled', 'disabled');

        var id = '".$member_grade_id."';

        // 一般设定
        var gradename = $('#gradename').val();
        var grade_alert_status = $('#grade_alert_status').val();
        // var status = $('#status').val();
        var status = $('#status').prop('checked');
        var deposit_rate = $('#deposit_rate').val();
        var notes = $('#notes').val();


        // 存款设定
        var deposit_allow = $('#deposit_allow').val();
        var depositlimits_upper = $('#depositlimits_upper').val();
        var depositlimits_lower = $('#depositlimits_lower').val();

        var onlinepayment_allow = $('#onlinepayment_allow').val();
        var onlinepaymentlimits_upper = $('#onlinepaymentlimits_upper').val();
        var onlinepaymentlimits_lower = $('#onlinepaymentlimits_lower').val();

        // var pointcard_allow = $('#pointcard_allow').val();
        // var pointcard_limits_upper = $('#pointcard_limits_upper').val();
        // var pointcard_limits_lower = $('#pointcard_limits_lower').val();

        // var pointcardfee_member_rate_enable = $('#pointcardfee_member_rate_enable').val();
        // var pointcardfee_member_rate_enable = $('#pointcardfee_member_rate_enable').prop('checked');
        // var pointcardfee_member_rate = $('#pointcardfee_member_rate').val();

        var apifastpay_allow = $('#apifastpay_allow').val();
        var apifastpaylimits_upper = $('#apifastpaylimits_upper').val();
        var apifastpaylimits_lower = $('#apifastpaylimits_lower').val();
        var apifastpayfee_member_rate = $('#apifastpayfee_member_rate').val();


        // 取款设定
        var withdrawallimits_cash_upper = $('#withdrawallimits_cash_upper').val();
        var withdrawallimits_cash_lower = $('#withdrawallimits_cash_lower').val();
        var withdrawalcash_allow = $('#withdrawalcash_allow').val();
        var withdrawalfee_cash = $('#withdrawalfee_cash').val();
        var withdrawalfee_max_cash = $('#withdrawalfee_max_cash').val();
        var withdrawalfee_method_cash = $('#withdrawalfee_method_cash').val();
        // var withdrawalfee_method_cash = $('input[class=withdrawalfee_method_cash]:checked').val();

        var withdrawallimits_upper = $('#withdrawallimits_upper').val();
        var withdrawallimits_lower = $('#withdrawallimits_lower').val();
        var withdrawal_allow = $('#withdrawal_allow').val();
        var withdrawalfee = $('#withdrawalfee').val();
        var withdrawalfee_max = $('#withdrawalfee_max').val();
        var withdrawalfee_method = $('#withdrawalfee_method').val();
        // var withdrawalfee_method = $('input[class=withdrawalfee_method]:checked').val();

        var withdrawal_limitstime_gcash = $('#withdrawal_limitstime_gcash').val();
        var withdrawal_limitstime_gtoken = $('#withdrawal_limitstime_gtoken').val();
        var administrative_cost_ratio = $('#administrative_cost_ratio').val();


        // 优惠设定
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
        var activity_register_preferential_amount = $('#activity_register_preferential_amount').val();
        var activity_register_preferential_audited = $('#activity_register_preferential_audited').val();

        // var activity_daily_checkin_enable = $('#activity_daily_checkin_enable').val();
        // var activity_daily_checkin_enable = $('#activity_daily_checkin_enable').prop('checked');
        // var activity_daily_checkin_days = $('#activity_daily_checkin_days').val();
        // var activity_daily_checkin_amount = $('#activity_daily_checkin_amount').val();
        // var activity_daily_checkin_rate = $('#activity_daily_checkin_rate').val();

        // 等级状态是否启用
        if(status) {
          var status_value = 1;
        } else {
          var status_value = 0;
        }

        // 点卡支付手续费是否启用
        // if(pointcardfee_member_rate_enable) {
        //   var pointcardfee_member_rate_enable_value = 1;
        // } else {
        //   var pointcardfee_member_rate_enable_value = 0;
        // }

        // 首次储值公司存款优惠是否启用
        // if(activity_first_deposit_enable) {
        //   var activity_first_deposit_enable_value = 1;
        // } else {
        //   var activity_first_deposit_enable_value = 0;
        // }

        // 首次储值线上支付优惠是否启用
        // if(activity_first_onlinepayment_enable) {
        //   var activity_first_onlinepayment_enable_value = 1;
        // } else {
        //   var activity_first_onlinepayment_enable_value = 0;
        // }

        // 公司存款优惠是否启用
        // if(activity_deposit_preferential_enable) {
        //   var activity_deposit_preferential_enable_value = 1;
        // } else {
        //   var activity_deposit_preferential_enable_value = 0;
        // }

        // 线上支付优惠是否启用
        // if(activity_onlinepayment_preferential_enable) {
        //   var activity_onlinepayment_preferential_enable_value = 1;
        // } else {
        //   var activity_onlinepayment_preferential_enable_value = 0;
        // }

        // 注册送彩金是否启用
        if(activity_register_preferential_enable) {
          var activity_register_preferential_enable_value = 1;
        } else {
          var activity_register_preferential_enable_value = 0;
        }

        // 注册送彩金管端新增是否启用
        if(activity_register_preferential_adminadd) {
          var activity_register_preferential_adminadd_value = 1;
        } else {
          var activity_register_preferential_adminadd_value = 0;
        }

        // 连续上线优惠是否启用
        // if(activity_daily_checkin_enable) {
        //   var activity_daily_checkin_enable_value = 1;
        // } else {
        //   var activity_daily_checkin_enable_value = 0;
        // }

        if(confirm('确定是否储存设定？') == true){
          $.post('member_grade_config_detail_action.php?a=edit_member_grade_data',
            {
              id: id,

              gradename: gradename,
              grade_alert_status: grade_alert_status,
              status: status_value,
              deposit_rate: deposit_rate,
              notes: notes,

              deposit_allow: deposit_allow,
              depositlimits_upper: depositlimits_upper,
              depositlimits_lower: depositlimits_lower,
              onlinepayment_allow: onlinepayment_allow,
              onlinepaymentlimits_upper: onlinepaymentlimits_upper,
              onlinepaymentlimits_lower: onlinepaymentlimits_lower,
              // pointcard_allow: pointcard_allow,
              // pointcard_limits_upper: pointcard_limits_upper,
              // pointcard_limits_lower: pointcard_limits_lower,
              // pointcardfee_member_rate_enable: pointcardfee_member_rate_enable_value,
              // pointcardfee_member_rate: pointcardfee_member_rate,
              apifastpay_allow: apifastpay_allow,
              apifastpaylimits_upper: apifastpaylimits_upper,
              apifastpaylimits_lower: apifastpaylimits_lower,
              apifastpayfee_member_rate,

              withdrawallimits_cash_upper: withdrawallimits_cash_upper,
              withdrawallimits_cash_lower: withdrawallimits_cash_lower,
              withdrawalcash_allow: withdrawalcash_allow,
              withdrawalfee_cash: withdrawalfee_cash,
              withdrawalfee_max_cash: withdrawalfee_max_cash,
              withdrawalfee_method_cash: withdrawalfee_method_cash,              

              withdrawallimits_upper: withdrawallimits_upper,
              withdrawallimits_lower: withdrawallimits_lower,
              withdrawal_allow: withdrawal_allow,
              withdrawalfee: withdrawalfee,
              withdrawalfee_max: withdrawalfee_max,
              withdrawalfee_method: withdrawalfee_method,              

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
              activity_register_preferential_amount: activity_register_preferential_amount,
              activity_register_preferential_audited: activity_register_preferential_audited

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


    // 切成 1 栏版面
    $indexbody_content = '
    <div id="preview_area" class="alert alert-info" role="alert">
    * '.$tr['The input box corresponding to the blue background limits the input integer.'].'
    </div>
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

    // 将 checkbox 堆叠成 switch 的 css
    $extend_head = $extend_head. "
    <style>

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
    //  html 组合 end
    // -----------------------------------------------------------------------------------------------------------------------------------------------


  }else {
    // 查询不到该笔会员等级的提示
    $show_transaction_list_html  = '(x) 错误的尝试。';

    // 切成 1 栏版面
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

} else {
  // 没有登入的显示提示俊息
  $show_transaction_list_html  = '(x) 只有管​​理员或有权限的会员才可以登入观看。';

  // 切成 1 栏版面
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
// 准备填入的内容
// ----------------------------------------------------------------------------

// 将内容塞到 html meta 的关键字, SEO 加强使用
$tmpl['html_meta_description'] 		= $tr['host_descript'];
$tmpl['html_meta_author']	 				= $tr['host_author'];
$tmpl['html_meta_title'] 					= $function_title.'-'.$tr['host_name'];

// 页面大标题
$tmpl['page_title']								= $menu_breadcrumbs;
// 扩充再 head 的内容 可以是 js or css
$tmpl['extend_head']							= $extend_head;
// 扩充于档案末端的 Javascript
$tmpl['extend_js']								= $extend_js;
// 主要内容 -- title
$tmpl['paneltitle_content'] 			= '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要内容 -- content
$tmpl['panelbody_content']				= $indexbody_content;

// ----------------------------------------------------------------------------
// 填入内容结束。底下为页面的样板。以变数型态塞入变数显示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");
