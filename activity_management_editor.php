<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 新增和編輯活動
// File Name:	activity_management_editor.php
// Author:    Mavis
// Related:
// DB Table: root_promotion_activity,root_promotion_code
// Log:
// ----------------------------------------------------------------------------
session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 文字檔
require_once dirname(__FILE__) ."/activity_management_editor_lib.php";

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
$function_title 		= $tr['activity management'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs =<<<HTML
<ol class="breadcrumb">
  <li><a href="home.php">{$tr['Home']}</a></li>
  <li><a href="#">{$tr['profit and promotion']}</a></li>
  <li><a href="activity_management.php">{$tr['prmotional code']}</a></li>
  <li class="active">{$function_title}</li>
</ol>
HTML;
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------------------------
// 編輯
// ----------------------------------------------------------------------------------------------
if(isset($_GET['a']) && filter_var($_GET['a'], FILTER_VALIDATE_INT ) && $_SESSION['agent']->therole == 'R') {
  	$id = filter_var($_GET['a'],FILTER_VALIDATE_INT);

  	$activity_sql =<<<SQL
		SELECT * , 
			to_char((effecttime AT TIME ZONE '{$tzonename}'),'YYYY/MM/DD') AS effecttime, 
			to_char((endtime AT TIME ZONE '{$tzonename}'),'YYYY/MM/DD') AS endtime,
			promocode_req->'reg_member_time',
			promocode_req->'desposit_amount',
			promocode_req->'betting_amount'
		FROM root_promotion_activity 
		WHERE id = '{$id}'
SQL;

  	$result = runSQLall($activity_sql);

  	if($result[0] >= 1) {
      $domain_list_html = '';
      $act_sub_name='';
      $ratio_html = '';
      $amount_html = '';
      $user_account_type='';

    	for($i=1;$i<=$result[0];$i++){
        // 是否啟用
        if($result[$i]->activity_status == '1'){
          $isshow_status = 'checked';
        } elseif($result[$i]->activity_status == '0') {
          $isshow_status = '';
        }
        
        if(isset($result[$i]->promocode_req) && ($result[$i]->promocode_req != NULL)){
          $promo = $result[$i]->promocode_req;
          $promo_decode = json_decode($promo,true);
          $user_type = $promo_decode['user_therole'];

          if($user_type == 'A'){
            $user_account_type .=<<<HTML
               <input type="radio" class="account_type" name="user_therole" value="A" title="代理商包含会员身分" checked>{$tr['only for agent'] }
HTML;
          }else{
            $user_account_type .=<<<HTML
              <input type="radio" class="account_type" name="user_therole" value="A" title="代理商包含会员身分">{$tr['only for agent'] }
HTML;
          }

          if($user_type == 'M'){
            $user_account_type .=<<<HTML
            <input type="radio" class="account_type" name="user_therole" value="M" checked>{$tr['members can participate']}
HTML;
          }else{
            $user_account_type .=<<<HTML
            <input type="radio" class="account_type" name="user_therole" value="M">{$tr['members can participate']}
HTML;
          }
        
        }

        if($result[$i]->bouns_number != ''){
          $act_bounsnumber = 'disabled';
        }elseif($result[$i]->bouns_number == ''){
          $act_bounsnumber = '';
        }

      // 子網域
        foreach($search_domainname[$result[$i]->activity_domain] as $subdomain_name){
          if($subdomain_name == $result[$i]->activity_subdomain){
            $selected = 'selected';
          }else{
            $selected = '';
          }
          $act_sub_name .= '<option class="sub_domain" value="'.$subdomain_name.'" '.$selected.'>'.$subdomain_name.'</option>';
        }
    
        $promotion_activity['id'] = $id;
        $promotion_activity['activity_id'] = $result[$i]->activity_id; // 活動代碼
        $promotion_activity['the_activity_name'] = $result[$i]->activity_name; // 活動名稱
        $promotion_activity['the_activity_status'] = $isshow_status; // 活動狀態
        $promotion_activity['the_activity_desc'] = htmlspecialchars_decode($result[$i]->activity_desc); // 活動說明
        $promotion_activity['effecttime'] = $result[$i]->effecttime; // 開始時間
        $promotion_activity['endtime'] = $result[$i]->endtime; // 結束時間
        $promotion_activity['activity_domain'] = $result[$i]->activity_domain; //此活動要顯示在哪個domain
        $promotion_activity['activity_subdomain'] = $result[$i]->activity_subdomain; // 子網域
        $promotion_activity['bouns_number'] = $result[$i]->bouns_number; // 優惠碼數量(產生多少組優惠碼)
        $promotion_activity['amount'] = $result[$i]->bouns_amount; // 金額
        $promotion_activity['total_amount'] = $promotion_activity['bouns_number'] * $promotion_activity['amount'] ; // 總金額
        $promotion_activity['classification'] = $result[$i]->bouns_classification; // 獎金類別(遊戲幣，現金)
        $promotion_activity['bonus_auditclass'] = $result[$i]->bonus_auditclass; // 稽核方式
        $promotion_activity['bonus_audit'] = $result[$i]->bonus_audit; //稽核值
        $promotion_activity['audit_classification'] = $result[$i]->audit_classification; // 稽核類別(稽核倍數，稽核金額)
        $promotion_activity['promocode_req'] = $promo_decode; // 條件jsonb
        $promotion_activity['note'] = htmlspecialchars_decode($result[$i]->note); // 備註
        // $promotion_activity['show_frontpromotion'] = $result[$i]->show_frontpromotion; // 優惠管理對應活動  暫時保留

        // 遊戲幣稽核倍数
        if($promotion_activity['audit_classification'] == 'audit_ratio'){
          $show_audit_ratio_check = 'checked';
          $show_audit_ratio_html_disable = '';
          $show_audit_value = $promotion_activity['bonus_audit'];
        }else{
          $show_audit_ratio_check = '';
          $show_audit_ratio_html_disable = 'disabled';
          $show_audit_value = '0';
        }
        // 稽核金額
        if($promotion_activity['audit_classification'] == 'audit_amount'){
          $show_audit_amount_check = 'checked';
          $show_audit_amount_disable = '';
          $show_audit_amount_value = $promotion_activity['bonus_audit'];
        }else{
          $show_audit_amount_check = '';
          $show_audit_amount_disable = 'disabled';
          $show_audit_amount_value = '0';
        }
       
        $ratio_check =<<<HTML
          <input type="radio" name="audit_calculate_type"  value="audit_ratio" onclick="audit_switch()" {$show_audit_ratio_check}>{$tr['gtoken audit multiple']}
HTML;
        $ratio_html =<<<HTML
          <input type="number" class="form-control" name="audit_ratio" id="audit_ratio" placeholder="例:0.3" step="0.1" value="{$show_audit_value}" min="0" {$show_audit_ratio_html_disable}>
HTML;
        $amount_check =<<<HTML
         <input type="radio" name="audit_calculate_type" value="audit_amount" onclick="audit_switch()" {$show_audit_amount_check}>{$tr['audit amount'] }
HTML;
        $amount_html =<<<HTML
        <input type="number" class="form-control" name="audit_amount"  id="audit_amount" placeholder="例:100" value="{$show_audit_amount_value}" min="0" {$show_audit_amount_disable}>
HTML;
         // 免稽核
         if($promotion_activity['bonus_auditclass'] == 'freeaudit'){
          $freeaudit_check = 'disabled';
          $freeaudit_value_html = 'disabled';
          $freeaudit_value = '0';

          $ratio_check =<<<HTML
            <input type="radio" name="audit_calculate_type"  value="audit_ratio" onclick="audit_switch()" {$freeaudit_check}>{$tr['gtoken audit multiple']}
HTML;
          $amount_check =<<<HTML
            <input type="radio" name="audit_calculate_type" value="audit_amount" onclick="audit_switch()" {$freeaudit_check}>{$tr['audit amount'] }
HTML;
        }
      }
    }

} else{
	// 新增
	$promo_encode = ''; // 條件
	$act_bounsnumber = ''; // 優惠碼數量
  $act_sub_name = '';
  $user_account_type = '';

 	// 稽核倍數
    $ratio_check =<<<HTML
  		<input type="radio" name="audit_calculate_type"  value="audit_ratio" onclick="audit_switch()" checked="check">{$tr['gtoken audit multiple']}
HTML;
    $ratio_html=<<<HTML
    	<input type="number" class="form-control" name="audit_ratio" id="audit_ratio" placeholder="例:0.3" step="0.1" value="0" min="0" onclick="audit_switch()">
HTML;

	// 稽核金額
	$amount_check =<<<HTML
		<input type="radio" name="audit_calculate_type" value="audit_amount" onclick="audit_switch()">{$tr['audit amount'] }
HTML;
    $amount_html = <<<HTML
    	<input type="number" class="form-control" name="audit_amount"  id="audit_amount" placeholder="例:100" value="0" min="0" onclick="audit_switch()">  
HTML;

	//子網域 預設
	if($search_domainname['main_domain'][0] == 'gpk17.com'){
		$act_sub_name .= '<option class="sub_domain" value="demo/mdemo" selected>demo/mdemo</option>';
		$act_sub_name .= '<option class="sub_domain" value="dev/mdev">dev/mdev</option>';
	}

  // 帳戶類型
    $user_account_type .=<<<HTML
      <input type="radio" class="account_type" name="user_therole" value="A">{$tr['only for agent'] }
HTML;
    $user_account_type .=<<<HTML
      <input type="radio" class="account_type" name="user_therole" value="M" checked>{$tr['members can participate']}
HTML;

    // 新增
    $promotion_activity['id'] = ''; 
    $promotion_activity['activity_id'] = ''; // 活動代碼(英文小寫，4碼)
    $promotion_activity['the_activity_name'] = ''; // 活動名稱
    $promotion_activity['the_activity_status'] = ''; // 活動狀態 1=啟用0=關閉
    $promotion_activity['the_activity_desc'] = ''; // 活動說明
    $promotion_activity['effecttime'] = ''; //活動有效時間
    $promotion_activity['endtime'] = ''; // 活動結束時間
    $promotion_activity['activity_domain'] = ''; // 活動domain
    $promotion_activity['activity_subdomain']  = ''; //子網域
    $promotion_activity['bouns_number'] = 10; // 優惠碼數量(產生多少組優惠碼)
    $promotion_activity['amount'] = 15; // 金額
    $promotion_activity['classification'] = ''; //獎金類別(遊戲幣，現金)
    $promotion_activity['total_amount'] = ''; // 總金額
    $promotion_activity['bonus_auditclass'] = ''; // 稽核方式
    $promotion_activity['bonus_audit'] = 0; //稽核值
    $promotion_activity['promocode_req'] = ''; // 條件jsonb
    $promotion_activity['note'] = '' ; // 備註
    $promotion_activity['audit_classification'] = ''; //稽核類別(稽核倍數，稽核金額)
    // $promotion_activity['show_frontpromotion'] = ''; // 優惠管理對應活動 暫時保留

}

if($_SESSION['agent']->therole == 'R'){
	$extend_head=<<<HTML
      <link rel="stylesheet" type="text/css" href="in/datetimepicker/jquery.datetimepicker.css"/>
      <!-- 引用 datetimepicker -->
  		<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script> 
HTML;

  // -----------------------------------------------------------------
  // 新增
  // -----------------------------------------------------------------
  // 活動名稱
  $show_list_html =<<<HTML

  <div class="row">

    <div class="col-12 col-md-12">
    <span class="label label-primary">
      <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>{$tr['information']}
    </span>
    <hr>
    </div>
  </div>

  <div class="row">
    <div class="col-1"></div>
    <div class="col-3"><p class="text-left">* {$tr['activity name']}</p></div>
      <div class="col-4">
        <input type="text" class="form-control validate[maxSize[50]]" maxlength="50" id="name" placeholder="{$tr['register to get bonus']}({$tr['max']}50{$tr['word']})" value="{$promotion_activity['the_activity_name']}">
      </div>
      <div class="col-3 text-right">* {$tr['required']}</div>
  </div>
  <br>

  <div class="row">
    <div class="col-1"></div>
    <div class="col-3"><p class="text-left">{$tr['description']}</p></div>
      <div class="col-4">
        <textarea placeholder="{$tr['activity description detail']}({$tr['max']}80{$tr['word']})" class="validate[maxSize[80]]" id="desc" value="{$promotion_activity['the_activity_desc']}" maxlength="80" style="width:360px;height:100px;">{$promotion_activity['the_activity_desc']}</textarea>
      </div>
  </div>
  <br>

  <!-- 預設 今天日期  -->
  <div class="row">
    <div class="col-1"></div>
    <div class="col-3"><p class="text-left">* {$tr['activity time']}<span class="glyphicon glyphicon-info-sign" title="{$tr['activity time description']}"></span></p></div>
    <div class="col-4">
      <div class="input-group">
        <input type="text" class="form-control" placeholder="{$tr['effective immediately']}" aria-describedby="basic-addon1" id="start_day" value="{$promotion_activity['effecttime']}">
        <span class="input-group-addon" id="basic-addon1">~</span>
        <input type="text" class="form-control" placeholder="{$tr['3 months']}" aria-describedby="basic-addon1" id="end_day" value="{$promotion_activity['endtime']}">
      </div>
    </div>
  </div>
  <br>
  
  <div class="row">
    <div class="col-1"></div>
    <div class="col-3"><p class="text-left">* {$tr['number of promotion code']}<span class="glyphicon glyphicon-info-sign" title="{$tr['the number of coupons cannot be modified']}"></span></p></div>
      <div class="col-4">
        <input type="number" class="form-control" id="promo_number" placeholder="例:20" min="1" value="{$promotion_activity['bouns_number']}" onchange="auto_calc_total()" {$act_bounsnumber}>
      </div>
  </div>
  <br>

 <div class="row">
    <div class="col-1"></div>
    <div class="col-3"><p class="text-left">* {$tr['each promotion amount']}</p></div>
      <div class="col-4">
        <input type="number" class="form-control" id="amount_number" placeholder="例:5" min="1" value="{$promotion_activity['amount']}" onchange="auto_calc_total()">
      </div>
  </div>
  <br>

  <div class="row">
    <div class="col-1"></div>
    <div class="col-3"><p class="text-left">{$tr['total amount']}</p></div>
      <div class="col-4">
        <input type="number" class="form-control" id="total_amount" value="{$promotion_activity['total_amount']}" disabled>
      </div>
  </div>
  <br>
HTML;
	// 網域
	$result = search_domainname();
	$domain_list_html = '';
  	foreach($result['main_domain'] as $domainvalue){
  
		if($domainvalue == $promotion_activity['activity_domain'] ){
			$domain_list_html .=<<<HTML
			<option class="domain" id="{$domainvalue}" value="{$domainvalue}" selected>{$domainvalue}</option>
HTML;
		}else{
			$domain_list_html.=<<<HTML
			<option class="domain" id="{$domainvalue}" value="{$domainvalue}">{$domainvalue}</option>
HTML;
    	}
  	}

    
    $show_list_html .=<<<HTML
    <div class="row">
      <div class="col-1"></div>
      <div class="col-3"><p class="text-left">* {$tr['domain']}</p></div><!-- 此优惠活动显示于 -->
        <div class="col-4">
          <select id="select_domain" name="main_domain_name" style="width:360px">
            {$domain_list_html}
          </select>
        </div>
    </div>
    <br>
    <div class="row">
      <div class="col-1"></div>
      <div class="col-3"><p class="text-left">* {$tr['subdomain']}</p></div>
        <div class="col-4">
          <select id="select_subdomain" name="sub_domain_name" style="width:360px">
              {$act_sub_name}
          </select>
        </div>
    </div>
    <br>

HTML;

  // 獎金類別
  $discount_coin = '';
  foreach ($coin_classification as $key => $value) {
    if($key == $promotion_activity['classification']){
        $discount_coin.='<option value="'.$key.'" selected>'.$value.'</option>';
     }else{
        $discount_coin.='<option value="'.$key.'">'.$value.'</option>';
     }
   }
  $show_list_html .=<<<HTML
    <div class="row">
      <div class="col-1"></div>
      <div class="col-3"><p class="text-left">{$tr['bonus category']}</p></div>
        <div class="col-4">
          <select id="bonus_category" style="width:360px" onchange="audit_setting()">
            {$discount_coin}
          </select>
        </div>
    </div>
    <br>
HTML;

  // 稽核
  $select_audit = '';
  foreach ($audit_mode as $key => $value) {
    if($key == $promotion_activity['bonus_auditclass']){
      $select_audit.='<option value="'.$key.'" selected>'.$value.'</option>';
    }else{
      $select_audit.='<option value="'.$key.'">'.$value.'</option>';
    }   
  };

  $show_list_html .=<<<HTML
    <div class="row">
      <div class="col-1"></div>
      <div class="col-3"><p class="text-left">{$tr['audit method']}</p></div>
        <div class="col-4">
          <select id="select_audit" style="width:360px" onchange="radio_check()">
            {$select_audit}
          </select>
        </div>
    </div>

    <br>
    <div class="row">
      <div class="col-1"></div>
      <div class="col-3">
	  	  {$ratio_check}
      </div>
      <div class="col-4">
      	{$ratio_html}
      </div>
    </div>

    <br>
    <div class="row">
      <div class="col-1"></div>
        <div class="col-3">
			    {$amount_check}
        </div>
        <div class="col-4">
        	{$amount_html}
        </div>
    </div>
    <br>

  <!-- 是否啟用 status -->
  <div class="row">
    <div class="col-1"></div>
    <div class="col-3"><p class="text-left">{$tr['Enabled or not']}</p></div>
    <div class="col-4 material-switch">
      <input id="activity_status_open" name="activity_status_open" class="checkbox_switch" value="0" type="checkbox" {$promotion_activity['the_activity_status'] }/>
      <label for="activity_status_open" class="label-success"></label>
    </div>
  </div>
  <br>

  <div class="row">
    <div class="col-12 col-md-12">
    <span class="label label-primary">
      <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>{$tr['activity condition']}
    </span>
    <hr>
    </div>
  </div>
HTML;
 
  // 條件
  $first_req ='';
  $other_reqs = '';
  $member_req = '';
  // 使用者重複，不能領取
  // IP重複而且沒有瀏覽器指紋，不能領取
  foreach ($ip_fpuser_req as $user_key => $user_value) {
      $first_req .= '
        <div class="input-group-prepend text-muted">
          <label>
            <i class="fas fa-lock mr-2"  id="'.$user_key.'"></i>'.$user_value.'
          </label>
        </div>';
  };

  // 帳號適用時間
  // 實際存款金額超過
  // 有效投注超過
  $match = 0;
  $c = 0;
  foreach ($other_req as $key => $value) {
    if($match == $c){
       $comment ='
       <button type="button" class="btn btn-xs pull-right modal-btn" data-toggle="modal" data-target="#des_act">'.$tr['description'].'</button>
       <div class="modal fade" id="des_act" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="true">
         <div class="modal-dialog" role="document">
           <div class="modal-content">
             <div class="modal-header">
               <h2 class="modal-title" id="myModalLabel">'.$tr['description'].'</h2>
                 <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>              
             </div>
         
             <div class="modal-body">
               <table class="table table-striped">
                 <tbody class="text-left">
                   <tr>
                   '.$tr['sign up for hours to receive the favorable'].'<br>
                   <br>
                  '.$tr['if user account register hours'].'
                   </tr>
                 </tbody>
               </table>
             </div>
             <div class="modal-footer">
               <button type="button" class="btn btn-default" data-dismiss="modal">'.$tr['off'].'</button>
             </div>
           </div>
         </div>
       </div>
      ';

    }else{
      $comment ='';
    }
    if(isset($promo_decode)){
      foreach($promo_decode as $main => $sub){
          if($key == $main){
            $other_reqs.='
              <div class="col-1"></div>
              <div class="col-3"><p class="text-left">'.$value.$comment.'</p></div>
              <div class="col-4"> 
                  <input type="number" class="form-control others" value="'.$sub.'" id="'.$key.'" name="others_req" min="0" >
                  <br>
              </div>
              <div class="col-4"></div>';
          }
          $c++;
      }
    }else{
      $other_reqs.='
        <div class="col-1"></div>
        <div class="col-3"><p class="text-left">'.$value.$comment.'</span></p></div>
        <div class="col-4"> 
            <input type="number" class="form-control others" value="0" id="'.$key.'" name="others_req" min="0">
            <br>
        </div>
        <div class="col-4"></div>';
        $c++;
    }  
  }

  $show_list_html .=<<<HTML
  <div class="row">
    <div class="col-1"></div>
      <div class="col-3"><p class="text-left">{$tr['user condition']}</p></div>
        <div class="col-4">
          {$first_req}
        </div>
  </div>
  <div class="row">
    <div class="col-1"></div>
      <div class="col-3"><p class="text-left">{$tr['Account Type']}</p></div>
        <div class="col-4">
        {$user_account_type}
          <!-- <input type="radio" class="account_type" name="user_therole" value="A">代理商可以参加 -->
          <!-- <input type="radio" class="account_type" name="user_therole" value="M" checked>会员可以参加 -->
        </div>
  </div>
  <div class="row">
    {$other_reqs}
  </div>
  
	  <div class="row">
      <div class="col-1"></div>
	    <div class="col-12 col-md-3"><p class="text-left">{$tr['Note']}</p></div>
	      <div class="col-12 col-md-4">
	        <textarea id="activity_note" placeholder="" value="{$promotion_activity['note']}" style="width:360px;height:100px;">{$promotion_activity['note']}</textarea>
	      </div>
	    <div class="col-12 col-md-7"></div>
	  </div>
	  <br>
HTML;

  $show_list_html = $show_list_html.'
  <div class="row">
    <div class="col-12 col-md-10">
      <p class="text-right">
        <button id="submit_to_edit" class="btn btn-success"><span class="glyphicon glyphicon-floppy-saved" aria-hidden="true"></span>&nbsp;'.$tr['Save'].'</button>
        <button id="remove_to_edit" class="btn btn-danger" onclick="javascript:location.href=\'activity_management.php\'"><span class="glyphicon glyphicon-floppy-remove" aria-hidden="true"></span>&nbsp;'.$tr['Cancel'].'</button>
      </p>
    </div>
  </div>
  ';

  $form_list='';
  
    $form_list = $form_list.'
      <form class="form-horizontald" role="form" id="activity_form">
        '.$show_list_html.'
      </form>
    ';
    $show_list_html = $form_list;


  // date 選擇器 https://jqueryui.com/datepicker/
  // http://api.jqueryui.com/datepicker/
  // 14 - 100 歲為年齡範圍， 25-55 為主流客戶。
  $dateyearrange_start  = date("Y/m/d");
  $dateyearrange_end    = date("Y") + 50;
  $datedefauleyear    = date("Y/m/d");

  	// JS 開頭
$extend_head = $extend_head. <<<HTML
<script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
<script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
<link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>

<script type="text/javascript" language="javascript" class="init">
  $(document).ready(function () {
    $("#activity_form").validationEngine();
  });
</script>
HTML; 

  $extend_js =<<<HTML
  <script>
 
	// 網域
	$('#select_domain').on('click',function(){
	
		var sub = $('select[name="main_domain_name"] :selected').val();
		
		// 子網域
		$.ajax({
			url: 'activity_management_editor_action.php?a=select_subdomain',
			type: 'POST',
			data:({
				sub: sub
			}),
			success: function(response){
				$('#select_subdomain').html(response);
			},
			error: function(error){
				$('#select_subdomain').html(error);
			}
		})
	})

  // 暫時保留
	// 子網域改變，優惠管理活動也要變
	// $('#select_subdomain').on('click',function(){
	// 	var sub = $('select[name="sub_domain_name"] :selected').val();
	// 	$.ajax({
	// 		url: 'activity_management_editor_action.php?a=select_promotionmanagement',
	// 		type: 'POST',
	// 		data:({
	// 			sub: sub
	// 		}),
	// 		success: function(response){
	// 			$('#select_front').html(response);
	// 		},
	// 		error: function(error){
	// 			$('#select_front').html(error);
	// 		}
	// 	})
	// })

	// 篩選獎金類別
	function audit_setting(){
		var select_audit = $('#bonus_category').val();
		
		// 選遊戲弊
		if(select_audit == 'gtoken'){
			$("#select_audit").prop('disabled',false);
			$("#audit_ratio").prop('disabled',false); // 稽核倍数
			$("#audit_amount").prop('disabled',false); // 稽核金額
			$("[name=audit_calculate_type]").prop('disabled',false); // radio 稽核倍数 稽核金額 

			radio_check();
		}else{
			$("#select_audit").prop('disabled',true); // 稽核方式
			$("#audit_ratio").prop('disabled',true); // 稽核倍数
			$("#audit_ratio").prop('value', '0');
			$("#audit_amount").prop('disabled',true); // 稽核金額
			$("#audit_amount").prop('value', '0');
			$("[name=audit_calculate_type]").prop('disabled',true); // radio 稽核倍数 稽核金額 
		}
	}

	// 選稽核倍數 或 稽核金額
	function audit_switch(){
		var audit_type = $('input[name=audit_calculate_type]:checked').val(); // radio選取 
    
		if(audit_type == 'audit_ratio'){
			// 稽核倍數 audit_ratio
			$("#audit_ratio").prop('disabled',false);
			$("#audit_amount").prop('disabled',true);
			$("#audit_amount").prop('value',0);
    }else{
			// 稽核金額 audit_amount
			$("#audit_amount").prop('disabled',false);
			$("#audit_ratio").prop('disabled',true);
			$("#audit_ratio").prop('value',0);
		}
	}

	// 稽核方式
	function radio_check(){
		var audit_type = $("#select_audit").val();
		// var audit_select_type = $('input[name=audit_calculate_type]:checked').val(); // radio選取

		// switch (audit_select_type) {
    //   case 'audit_amount': // 稽核金額
		// 		$("#audit_amount").prop('disabled', false);
		// 		$("#audit_ratio").prop('disabled', true);
		// 		$("#audit_ratio").prop('value', '0');
		// 		break;

    //   case 'audit_ratio': // 稽核倍數
		// 		$("#audit_amount").prop('disabled', true);
		// 		$("#audit").prop('value', '0');
		// 		$("#audit_ratio").prop('disabled', false);
		// 		break;
		// };

		// 免稽核
		if(audit_type == "freeaudit") {
			$("#audit_amount").prop("disabled", true); // 稽核金額
			$("#audit_amount").prop("value", "0");
			$("#audit_ratio").prop("disabled", true); // 稽核倍數
			$("#audit_ratio").prop("value", "0");
			$("[name=audit_calculate_type]").prop('disabled',true); // radio
		} else {
			$("#audit_amount").prop("disabled", false);
			$("#audit_ratio").prop("disabled", false);
			$("[name=audit_calculate_type]").prop('disabled',false);
		}
	}

  	// 總金額
	function auto_calc_total(){
		var prom_number = $("#promo_number").val();
		var amoney = $("#amount_number").val();
		var show_total = prom_number * amoney;
		var total = $('#total_amount').val(show_total);
  };

  </script>
HTML;

  $extend_js = $extend_js."
  <script>

  // for select day
  $('#start_day').datetimepicker({
    defaultDate: '".$datedefauleyear."',
    minDate: '".$dateyearrange_start."',
    maxDate: '".$dateyearrange_end."/01/01',
    timepicker: true,
    format: 'Y/m/d H:i',
    lang: 'en'
  });
  $('#end_day').datetimepicker({
    defaultDate: '".$datedefauleyear."',
    minDate: '".$dateyearrange_start."',
    maxDate: '".$dateyearrange_end."/01/01',
    timepicker: true,
    defaultTime: '23:59', 
    format: 'Y/m/d H:i',
    lang: 'en'
  });

	// 儲存
  $('#submit_to_edit').click(function(e){
  	e.preventDefault();
    
    var id = '".$promotion_activity['id']."';
	  var name = $('#name').val(); // 名稱
    var desc = $('#desc').val(); // 說明
	  var sdate= $('#start_day').val(); // 開始日期
	  var edate = $('#end_day').val(); // 結束日期
    var promo_number = $('#promo_number').val(); // 優惠碼數量
    var money = $('#amount_number').val(); // 獎金
    var classification = $('#bonus_category').val(); // 獎金類別

    var domain = $('#select_domain').val();  // 網域
    var sub = $('#select_subdomain').val(); // 子網域
	  var select_audit = $('#select_audit').val(); // 稽核方式
	  // var promotion_manage = $('#select_front').val(); // 優惠管理對應活動

    var audit_type = $('input[name=audit_calculate_type]:checked').val(); // radio選取
    var note = $('#activity_note').val(); // 備註
    var account_type = $('input:radio:checked[name=user_therole]').val(); // 帳戶類型

    if(audit_type == 'audit_ratio'){
      // 稽核倍數
      var audit_value = $('#audit_ratio').val(); // 稽核倍數的值
      audit_value = Math.round(audit_value*100)/100 // 四捨五入到第二位
    }else{
      // 稽核金額
      var audit_value = $('#audit_amount').val();
    }
    // 子網域沒選
    if(sub == ''){
      alert('请选择欲显示的子网域');
    }
    // 其他條件
    var others = $('input[name=others_req]').map(function(){
      return $(this).val();
    }).get();
    
    // status
    if($('#activity_status_open').prop('checked')){
      var activity_status_open = 1; // open
    } else{
      var activity_status_open = 0; // close
    };

    if(money<0) {
      alert('优惠码奖金{$tr['cannot be negative']}')
    } else if(promo_number<0){
      alert('{$tr['number of promotion code']}{$tr['cannot be negative']}')
    } else if(audit_value<0){
      alert('{$tr['audit amount']},{$tr['audit multiple']}{$tr['cannot be negative']}')
    } else {
      $.ajax({
        url: 'activity_management_editor_action.php?a=edit_activity',
          type: 'POST',
          data: ({
            id: id,
            name: name,
            desc: desc,
            sdate: sdate,
            edate: edate,
            promo_number: promo_number,
            money: money,
            classification: classification,
            sub : sub,
            domain: domain,
            // promotion_manage: promotion_manage,
            activity_status_open : activity_status_open,
            note: note,
            audit_type : audit_type,
            audit_value: audit_value,
            select_audit: select_audit,
            account_type: account_type,
            others: others
          }),  
          success: function(response){
            $('#preview_result').html(response);
          },
          error: function(error){
            $('#preview_result').html(error);
          }
      })
    }  
  });
  </script>
  ";
}else{
  $show_list_html = "(x) {$tr['Wrong operation']}";
};

  // 切成 1 欄版面
  $indexbody_content = <<<HTML
  <div class="row">

  <!-- <div class="col-12 col-md-1"> </div> -->
    <div class="col-12 col-md-12">
        {$show_list_html}
    </div>
  </div>
  <br>
  <div class="row">
    <div id="preview_result"></div>
  </div>
HTML;

  // 將 checkbox 堆疊成 switch 的 css
  $extend_head = $extend_head. "
  <style>

  .material-switch > input[type=\"checkbox\"] {
      visibility:hidden;
  }

  .material-switch > label {
      cursor: pointer;
      height: 0px;
      position: relative;
      width: 40px;
  }

  .material-switch > label::before {
      background: rgb(0, 0, 0);
      box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
      border-radius: 8px;
      content: '';
      height: 16px;
      margin-top: -8px;
      margin-left: -18px;
      position:absolute;
      opacity: 0.3;
      transition: all 0.4s ease-in-out;
      width: 30px;
  }
  .material-switch > label::after {
      background: rgb(255, 255, 255);
      border-radius: 16px;
      box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
      content: '';
      height: 16px;
      left: -4px;
      margin-top: -8px;
      margin-left: -18px;
      position: absolute;
      top: 0px;
      transition: all 0.3s ease-in-out;
      width: 16px;
  }
  .material-switch > input[type=\"checkbox\"]:checked + label::before {
      background: inherit;
      opacity: 0.5;
  }
  .material-switch > input[type=\"checkbox\"]:checked + label::after {
      background: inherit;
      left: 20px;
  }

  </style>
  ";
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