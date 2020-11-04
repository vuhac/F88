
<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 優惠編輯器
// File Name:	promotional_editor.php
// Author:    Neil
// Related:
// DB Table:  root_promotional_list
// Log:
// ----------------------------------------------------------------------------
session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/lib_promotional_management.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------
// if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND  in_array($_SESSION['agent']->account,$su['superuser'])) {
//   //如果是superuser，不做權限判斷
// }else{
//   $admin_pchk = admin_power_chk('offer_management_detail', 'htm');
//   if (!($admin_pchk['option']['add_edit_offer'] == '1')) {
//     echo ('<script> alert("您没有编辑权限!"); history.go(-1); </script>');
//     die();
//   }
// }


// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta $tr['Promotions Management Editor'] = '優惠管理編輯';
$function_title 		= $tr['Promotions Management Editor'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置 $tr['Home'] = '首頁'; $tr['profit and promotion'] = '營收與行銷';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['profit and promotion'].'</a></li>
  <li><a href="offer_management.php">'.$tr['promotion Offer Editor'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

function get_default_promotional_data()
{
  // 預設時間
  $today = gmdate('Y/m/d',time() + '-4' * 3600).' 00:00';
  //$year = date('Y/m/d', strtotime("$today +10 year"));

  $arr = [
    'id' => '',
    'processingaccount' => '',
    'name' => '',
    'effecttime' => $today,
    'endtime' => '',
    'status' => '',
    'classification' => '',
    'mobile_show' => '',
    'desktop_show' => '',
    'seq' => '',
    'bannerurl_effect' => '',
    'bannerurl_end' => '',
    'content' => '',
    'show_promotion_activity' => ''
  ];

  return $arr;
}

function combination_input_html($htmlcontent){
  $html = <<<HTML
  <div class="row">
  	<div class="col-12 col-md-2"><p>{$htmlcontent['title']}</p></div>
  	<div class="col-12 col-md-7">
  		<input type="text" class="form-control" id="{$htmlcontent['id']}" placeholder="{$htmlcontent['placeholder']}" value="{$htmlcontent['value']}">
  	</div>
  </div>
  <br>
HTML;
  return $html;
}

//分類
function combination_select_html($htmlcontent){
  global $tr;
 
  $select_html = '';
  $selected_html = '';
  $html = '';
  // echo '<pre>', var_dump($htmlcontent["category"]), '</pre>';
  // die();
  if(is_array($htmlcontent["category"])){

    foreach($htmlcontent["category"] as $key=>$val){

      if(isset($val->classification)){
        $catt = $val->classification;
      }elseif(isset($val->classification_name)){
        $catt = $val->classification_name;
      }
      $select_html .= <<<HTML
        <option value="{$catt}">$catt</option>
HTML;
  
  //     $select_html .= <<<HTML
  //       <option value="{$val->classification}">$val->classification</option>
  // HTML;
    }
    
      if ($htmlcontent['value'] == ''){
        // 新增
        $selected_html = <<<HTML
        <option>请选择</option>
HTML;
      }else{
        // 編輯
        $selected_html = <<<HTML
        <option class="form-control" SELECTED>{$htmlcontent['value']}</option>
HTML;
      }
      // var_dump($selected_html);die();
  
      $html .= <<<HTML
        <div class="row">
          <div class="col-12 col-md-2">
            <p><span class="text-danger">*</span>{$htmlcontent['title']}</p>
          </div>
          <div class="col-12 col-md-7 d-flex">
            <select class="form-control w-95" id="{$htmlcontent['id']}">
              {$selected_html}
              {$select_html}
            </select>   
            <button class="btn btn-primary btn-sm ml-2" data-toggle="modal" data-target="#exampleModal">{$tr['add classification']}</button>    
          </div>
        </div>
        <br>
HTML;
  }else{
    $html .= <<<HTML
        <div class="row">
          <div class="col-12 col-md-2">
            <p><span class="text-danger">*</span>{$htmlcontent['title']}</p>
          </div>
          <div class="col-12 col-md-7 d-flex">
            <select class="form-control w-95" id="{$htmlcontent['id']}">
              <option>暂无优惠</option>
            </select>   
            <button class="btn btn-primary btn-sm ml-2" data-toggle="modal" data-target="#exampleModal">{$tr['add classification']}</button>    
          </div>
        </div>
        <br>
HTML;
  }
  
  return $html;
}


function combination_upload_img_html($htmlcontent)
{
  global $tr;
  global $config;
  if($htmlcontent['value']==''){
$html = <<<HTML
  <div class="row">
    <div class="col-12 col-md-2"><p>{$htmlcontent['title']}</p></div>
    <div class="col-12 col-md-7">
      <div class="row border py-3 m-2 m-md-0">
        <div class="col-auto">
        <div class="dropdown">
          <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            {$tr['options']}
          </button>
          <div class="dropdown-menu"  id="v-pills-tab" role="tablist" aria-labelledby="dropdownMenuButton">
            <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
              <a class="dropdown-item active" data-group="{$htmlcontent['id']}" data-toggle="pill" href="#v-pills-{$htmlcontent['id']}-upload" role="tab" aria-selected="true">{$tr['upload image']}</a>
              <a class="dropdown-item" data-group="{$htmlcontent['id']}" data-toggle="pill" href="#v-pills-{$htmlcontent['id']}-url" role="tab" aria-selected="false">{$tr['image url']}</a>
          </div>
          </div>
        </div>

        </div>
        <div class="col">
          <div class="tab-content" id="v-pills-tabContent">
            <div class="tab-pane fade show active" id="v-pills-{$htmlcontent['id']}-upload" role="tabpanel">
                {$tr['upload image only 2mb'] }
              <input type="file" name="file" accept="image/*" class="form-control" id="upload_{$htmlcontent['id']}" >
            </div>
            <div class="tab-pane fade" id="v-pills-{$htmlcontent['id']}-url" role="tabpanel">
             {$tr['image url']}
              <input type="text" class="form-control" id="text_{$htmlcontent['id']}" >
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <br>
HTML;
  }else{
  $html = <<<HTML
  <div class="row">
    <div class="col-12 col-md-2"><p class="text-left">{$htmlcontent['title']}</p></div>
    <div class="col-12 col-md-7">
      <img onerror="this.src='in/component/common/error.png'" class="mb-3" style="max-width: 100%;max-height: 100px;" src="{$htmlcontent['value']}">
      <div class="row border py-3 m-2 m-md-0">
        <div class="col-auto">
          <div class="dropdown">
            <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              {$tr['options']}
            </button>
            <div class="dropdown-menu"  id="v-pills-tab" role="tablist" aria-labelledby="dropdownMenuButton">
              <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                <a class="dropdown-item active" data-group="{$htmlcontent['id']}" data-toggle="pill" href="#v-pills-{$htmlcontent['id']}-upload" role="tab" aria-selected="true">{$tr['upload image']}</a>
                <a class="dropdown-item" data-group="{$htmlcontent['id']}" data-toggle="pill" href="#v-pills-{$htmlcontent['id']}-url" role="tab" aria-selected="false">{$tr['image url']}</a>
              </div>
            </div>
          </div>
        </div>
        <div class="col">
          <div class="tab-content" id="v-pills-tabContent">
            <div class="tab-pane fade show active" id="v-pills-{$htmlcontent['id']}-upload" role="tabpanel">
                {$tr['upload image only 2mb']}
              <input type="file" name="file" accept="image/*" class="form-control" id="upload_{$htmlcontent['id']}" >
            </div>
            <div class="tab-pane fade" id="v-pills-{$htmlcontent['id']}-url" role="tabpanel">
              {$tr['image url']}
              <input type="text" class="form-control" id="text_{$htmlcontent['id']}" >
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <br>
HTML;
  }
  return $html;
}

if(!isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  header('Location:./home.php');
  die();
}

$id = (isset($_GET['i'])) ? filter_var($_GET['i'], FILTER_SANITIZE_NUMBER_INT) : '';
if (empty($id)) {
  // 新增
  $promotional_data['status'] = true;
  $promotional_data['result'] = (object)get_default_promotional_data();

  $a = 'new';
} else {
  // 編輯
  $promotional_data = get_designatedid_promotional($id);

  $a = 'edit';

  if (!$promotional_data['status']) {
    //$tr['Promotions query failed'] = '優惠查詢失敗。';
    echo '<script>alert("'.$tr['Promotions query failed'].'");location.href="./offer_management.php";</script>';
  }
}

$domain_id = $_GET['di'] ?? '';
$subdomain_id = $_GET['sdi'] ?? '';

$domain = get_desktop_mobile_domain($domain_id, $subdomain_id);

if (!$domain['status']) {
  echo '<script>alert("'.$domain['result'].'");location.href="./offer_management.php";</script>';
  die();
}

// 有登入，且身份為管理員 R 才可以使用這個功能。
if($promotional_data['status']) {
  // var_dump($a);die();
// -----------------------------------------------------------------------------------------------------------------------------------------------
//  html 組合 start
// -----------------------------------------------------------------------------------------------------------------------------------------------
//$tr['discount'] = '優惠';$tr['Bonus'] = '反水';$tr['discount'] = '優惠';$tr['name'] = '名稱';$tr['Please input promotion name'] = '請填入優惠名稱。';

  $show_list_html = '';

  $domain_id = $domain['result']['domain_id'];
  $subdomain_id = $domain['result']['subdomain_id'];
  $desktop_domain = $domain['result']['desktop'];
  $mobile_domain = $domain['result']['mobile'];

  // 分類
  // $promotional_classification = get_promotion_classification_bydomain($desktop_domain, $mobile_domain);

  // $promotional_classification = get_classification_by_domain($desktop_domain, $mobile_domain);

  $status_filter_query = " AND classification_status = 1 ";
  $classification_query = " AND status = 1 ";
  $promotions_sql = switch_tab($desktop_domain, $mobile_domain,$status_filter_query);
  // 專放分類
  $classification_sql = classification_sql($desktop_domain, $mobile_domain,$classification_query);

  $promotional_classification = get_classification_by_domain($promotions_sql, $classification_sql);
  // echo '<pre>', var_dump($promotional_classification['result']), '</pre>';
  // die();

  $promotional_classification_html = <<<HTML
  <div class="alert alert-success">
    <p class="mb-2">{$tr['desktop domain name']} {$desktop_domain}</p>
    <p class="mb-0">{$tr['mobile domain name']} {$mobile_domain}</p>
  </div>
HTML;

//   if ($promotional_classification['status']) {
//     $promotional_classification_html .= combination_classification_html($promotional_classification['result']);
//   } else {
//     $promotional_classification_html .= <<<HTML
//     <form>
//       <div class="form-group">
//         <label class="control-label">{$tr['created a discounted category']} </label>
//         <span class="label label-danger">{$promotional_classification['result']}</span>
//       </div>
//     </form>
// HTML;
//   }

  // 優惠名稱
  $show_list_html = combination_input_html(array('title' => $tr['discount'].$tr['name'], 'id' => 'offer_name', 'placeholder' => $tr['Please input promotion name'], 'value' => $promotional_data['result']->name));

  // 分類
  $show_list_html .= combination_select_html(array('title' => $tr['classification'], 'id' => 'offer_classification', 'placeholder' => $tr['please enter classification of promotion'] , 'value' => $promotional_data['result']->classification,'category'=> $promotional_classification['result']));

  //$tr['promotion starts picture'] = '優惠開始圖片';$tr['Please input promotion image URL'] = '請填入優惠圖片網址。';
  $show_list_html .= combination_upload_img_html(array('title' => $tr['promotion starts picture'], 'id' => 'offer_start_img', 'placeholder' => $tr['Please input promotion image URL'], 'value' => $promotional_data['result']->bannerurl_effect));

  //$tr['promotion ends  picture'] = '優惠結束圖片';
  $show_list_html .= combination_upload_img_html(array('title' => $tr['promotion ends picture'], 'id' => 'offer_end_img', 'placeholder' => $tr['Please input promotion image URL'], 'value' => $promotional_data['result']->bannerurl_end));

  //$tr['Sort'] = '排序';$tr['Please fill in the discount order'] = '請填入優惠排序。';
  // $show_list_html .= combination_input_html(array('title' => $tr['Sort'], 'id' => 'offer_order', 'placeholder' => $tr['Please fill in the discount order'], 'value' => $promotional_data['result']->seq));


  // 預設 今天日期 和 隔天  $tr['Sales time'] = '上架時間';
  $show_list_html	.= '
  <div class="row">
  	<div class="col-12 col-md-2"><p>'.$tr['Sales time'].'</p></div>
  	<div class="col-12 col-md-7">
  		<div class="input-group">
        <input type="text" class="form-control" placeholder="start" aria-describedby="basic-addon1" id="start_day" value="'.$promotional_data['result']->effecttime.'">
        <span class="input-group-addon" id="basic-addon1">~</span>
        <input type="text" class="form-control" placeholder="三个月" aria-describedby="basic-addon1" id="end_day" value="'.$promotional_data['result']->endtime.'">
      </div>
  	</div>
  	<div class="col-12 col-md-7"><div id="offer_name_result"></div></div>
  </div>
  <br>
  ';

  // 下拉式選單 活動優惠碼管理
  // $show_list_html .= combination_select_html(array('title'=> '優惠管理對應活動','id'=>'select_activity','value' => $promotional_data['result']->show_promotion_activity));

  // 下拉式選單 活動優惠碼管理
  $promotion_activity = get_promotion_activity();
  $front_promo = '';

  foreach($promotion_activity as $frontkey){
    $promotion['id'] = $frontkey->id;
    $promotion['activity_id'] = $frontkey->activity_id;
    $promotion['activity_name'] = $frontkey->activity_name;
    $promotion['activity_domain'] = $frontkey->activity_domain;
    $promotion['activity_subdomain'] = $frontkey->activity_subdomain;
    $remove_desktop = array_unique(explode('.',$promotion['activity_domain'])); // 移除重複'.'

    $first = $remove_desktop[0]; // play
    $second = $remove_desktop[1]; // com

    // 子網域
    $find_sub = str_replace('/',' ',$promotion['activity_subdomain']); // 移除子網域的 '/'
    $sub_explode = explode(' ',$find_sub);
    $sub_desktop_name = $sub_explode[0]; // desktop

    $merge_website = ['desktop' => $sub_desktop_name.'.'.$first.'.'.$second]; // 桌机版域名

    // 如果優惠碼管理的活動網域 == 優惠管理的網域
      if($merge_website['desktop'] == $desktop_domain){
        if($promotional_data['result']->show_promotion_activity == $promotion['id']){
          $show ='selected';
        }else{
          $show = '';
        }
        $front_promo .=<<<HTML
          <option class="promotion_activity" value="{$promotion['id']}" {$show}>{$promotion['activity_name']}</option>
HTML;
      }
  }


  $show_list_html .= <<<HTML
    <div class="row">
    <div class="col-12 col-md-2"><p>{$tr['link to promotion code']}<span class="glyphicon glyphicon-info-sign" title="如果选择与活动优惠码做对应连结，请自行注意两边上架时间。"></span></p></div>
      <div class="col-12 col-md-7">
        <select id="select_activity" name="select_promotions" class="form-control">
          <option class="promotion_activity" value="0">---</option>
          {$front_promo}
        </select>
      </div>
    </div>
    <br>
HTML;

  $isopen = '';
  if ($promotional_data['result']->status == '1') {
    $isopen = 'checked';
  }

  //$tr['Enabled or not'] = '是否啟用';
  $show_list_html	.= '
  <div class="row">
  	<div class="col-12 col-md-2"><p>'.$tr['Enabled or not'].'</p></div>
  	<div class="col-12 col-md-7 material-switch">
  		<input id="offer_isopen" name="offer_isopen" class="checkbox_switch" value="0" type="checkbox" '.$isopen.'/>
      <label for="offer_isopen" class="label-success"></label>
  	</div>
  	<div class="col-12 col-md-7"><div id="offer_name_result"></div></div>
  </div>
  <br>
  ';

  $desktop_show = '';
  if ($promotional_data['result']->desktop_show == '1') {
    $desktop_show = 'checked';
  }

  $mobile_show = '';
  if ($promotional_data['result']->mobile_show == '1') {
    $mobile_show = 'checked';
  }

  //$tr['Is it displayed'] = '是否顯示';$tr['PC version'] = '電腦版';  $tr['Mobile version'] = '手機版';
  $show_list_html	.= '
  <div class="row">
  	<div class="col-12 col-md-2"><p>'.$tr['Is it displayed'].'</p></div>
  	<div class="col-12 col-md-7 material-switch">
  		<input id="offer_pc_isshow" name="offer_pc_isshow" class="checkbox_switch" value="0" type="checkbox" '.$desktop_show.'/>
      <label for="offer_pc_isshow" class="label-success"></label>'.$tr['PC version'].'
      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
      <input id="offer_mobile_isshow" name="offer_mobile_isshow" class="checkbox_switch" value="0" type="checkbox" '.$mobile_show.'/>
      <label for="offer_mobile_isshow" class="label-success"></label>'.$tr['Mobile version'].'
  	</div>
  	<div class="col-12 col-md-5"><div id="offer_name_result"></div></div>
  </div>
  <br>
  ';

  // ref. doc: http://xdsoft.net/jqplugins/datetimepicker/
  // 取得日期的 jquery datetime picker -- for birthday
  $extend_head = $extend_head.'<link rel="stylesheet" type="text/css" href="in/datetimepicker/jquery.datetimepicker.css"/>';
  $extend_js = $extend_js.'<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>';

  // date 選擇器 https://jqueryui.com/datepicker/
  // http://api.jqueryui.com/datepicker/
  // 14 - 100 歲為年齡範圍， 25-55 為主流客戶。
  $dateyearrange_start 	= (new DateTime())->modify('-100 Year')->format("Y/m/d");
  // $dateyearrange_end    = date("Y/m/d")+50;
  $dateyearrange_end 		= (new DateTime())->modify('+50 Year')->format("Y/m/d");
  $datedefauleyear		= (new DateTime())->format("Y/m/d");

  $current_date = gmdate('Y-m-d H:i',time() + -4*3600);

  $extend_js = $extend_js."
  <script>

  // for select day
  $('#start_day,#end_day').datetimepicker({
    defaultDate: '".$datedefauleyear."',
    minDate: '".$dateyearrange_start."',
    maxDate: '".$dateyearrange_end."',
    timepicker: true,
    format: 'Y/m/d H:i:s',
    lang: 'en'
  });

  /*
  function checkStartday() {
    if($('#start_day').val()){
      var d = new Date($('#start_day').val());
      d.setYear(d.getFullYear()+10);
      return d;
    }
    return false;
  }

  $('#start_day').datetimepicker({
    defaultDate: '".$datedefauleyear."/01/01',
    format: 'Y/m/d H:i',
    timepicker: true,
    lang: 'en',
    onShow:function(ct){
      this.setOptions({
        minDate: '".$dateyearrange_start."' // $('#end_day').val()?$('#end_day').val():false
      })
    }
  });

  $('#end_day').datetimepicker({
    defaultDate: '".$datedefauleyear."/01/01',
    format: 'Y/m/d H:i',
    timepicker: true,
    lang: 'en',
    onShow:function(ct){
      this.setOptions({
        minDate: checkStartday()
      })
    }
  });
  */

  // 新增分類
  $('#save').click(function(){
    var add_cat_name = $('#add_input_category').val();
    var time = '".$current_date."';
    var domain_name = '".$desktop_domain."';
    var sub_name = '".$mobile_domain."';

    $.post(\"promotional_editor_action.php?a=add_category\",{
      add_cat_name : add_cat_name,
      time: time,
      domain_name :domain_name,
      sub_name: sub_name
    },
    function(response){
      var parse = JSON.parse(response);
      if(parse.logger == true){
        alert('新增成功');
        window.location.reload();
      }else{
        alert('新增失败');
      }
    }) 
  });
  </script>
  ";


  // 編輯的內文
  // $editor_content_value = '<h1>Hello world!</h1>';
  $editor_content_value = htmlspecialchars_decode($promotional_data['result']->content);

  // 引入 ckeditor editor $tr['promotion content'] = '優惠內容';
  $show_list_html = $show_list_html.'

  <div class="row">
  	<div class="col-12"><p>'.$tr['promotion content'].'</p></div>
  	<div class="col-12 material-switch">
  		<main>
      <div class="adjoined-bottom">
        <div class="grid-container">
          <div class="grid-width-100">
            <div id="editor">
              '.$editor_content_value.'
            </div>
          </div>
        </div>
      </div>
      </main>
      <input type="hidden" id="editor_data_id" value="1122">
  	</div>
  	<div class="col-12"><div id="offer_name_result"></div></div>
  </div>
  <br>
  ';

  //$tr['Save'] = '儲存';   $tr['Cancel'] = '取消';
  $show_list_html = $show_list_html.'

  <div class="row">
  	<div class="col-12">
      <p class="text-right">
        <button id="submit_to_edit" class="btn btn-success">&nbsp;'.$tr['Save'].'</button>
        <a class="btn btn-secondary text-white" role="button" id="remove_to_edit">&nbsp;'.$tr['Cancel'].'</a>
        <!-- <a class="btn btn-danger" href="./offer_management.php" role="button" id="remove_to_edit">&nbsp;'.$tr['Cancel'].'</a> -->
      </p>
    </div>
  </div>
  ';

  //全選、全不選 js
  $extend_js = $extend_js.'
  <script src="in\ckeditor180712\ckeditor.js"></script>
  <!--<script src="in\ckeditor\ckeditor.js"></script> -->
  ';

  // 引用 ckeditor sdk http://sdk.ckeditor.com/index.html
  $extend_js = $extend_js."
  <script>

  if ( CKEDITOR.env.ie && CKEDITOR.env.version < 9 )
  CKEDITOR.tools.enableHtml5Elements( document );

  // The trick to keep the editor in the sample quite small
  // unless user specified own height.
  CKEDITOR.config.height = 150;
  CKEDITOR.config.width = 'auto';

  var texteditor = ( function() {
    var wysiwygareaAvailable = isWysiwygareaAvailable(),
      isBBCodeBuiltIn = !!CKEDITOR.plugins.get( 'bbcode' );

    return function() {
      var editorElement = CKEDITOR.document.getById( 'editor' );

      // Depending on the wysiwygare plugin availability initialize classic or inline editor.
      if ( wysiwygareaAvailable ) {
        CKEDITOR.replace( 'editor' );
      } else {
        editorElement.setAttribute( 'contenteditable', 'true' );
        CKEDITOR.inline( 'editor' );

        // TODO we can consider displaying some info box that
        // without wysiwygarea the classic editor may not work.
      }
    };

    function isWysiwygareaAvailable() {
      // If in development mode, then the wysiwygarea must be available.
      // Split REV into two strings so builder does not replace it :D.
      if ( CKEDITOR.revision == ( '%RE' + 'V%' ) ) {
        return true;
      }

      return !!CKEDITOR.plugins.get( 'wysiwygarea' );
    }
  } )();

  // 啟動
  texteditor();


  //按取消，則彈出視窗，確定是否離開
  $('#remove_to_edit').click(function(){
      if(confirm('确定要取消编辑吗')==true){
        document.location.href=\"offer_management.php\";
        // console.log('123');
      }
  });

  </script>

  ";

  // 送出資料到
  $extend_js = $extend_js."
  <script>
  $(document).ready(function() {
    //清空資料格(上傳)
    $('a[data-group=\"offer_start_img\"]').on('show.bs.tab', function (e) {
      $($(e.relatedTarget).attr('href')).find('input').val(''); // previous active tab
    })
    $('a[data-group=\"offer_end_img\"]').on('show.bs.tab', function (e) {
      $($(e.relatedTarget).attr('href')).find('input').val(''); // previous active tab
    })

    $('#submit_to_edit').click(function(){
      var editor_data =  CKEDITOR.instances.editor.getData();
      // if ( CKEDITOR.instances.editor.getData() == '' ){
      //   alert( 'There is no data available.' );
      // }

      var editor_data_id  = $('#editor_data_id').val();

      var offer_id = '".$promotional_data['result']->id."';
      var offer_name = $('#offer_name').val();

      var offer_classification = $('#offer_classification').val();
      // console.log(offer_classification);

      if(typeof $('#upload_offer_start_img')[0].files[0] == 'undefined'){
        var upload_offer_start_img = $('#text_offer_start_img').val();
      }
      else{
        var upload_offer_start_img = $('#upload_offer_start_img')[0].files[0];
      }
      if(typeof $('#upload_offer_end_img')[0].files[0] == 'undefined'){
        var upload_offer_end_img = $('#text_offer_end_img').val();
      }
      else{
        var upload_offer_end_img = $('#upload_offer_end_img')[0].files[0];
      }
      var offer_start_img = '".$promotional_data['result']->bannerurl_effect."';
      var offer_end_img = '".$promotional_data['result']->bannerurl_end."';
      // var offer_order = $('#offer_order').val();
      var start_day = $('#start_day').val();
      var end_day = $('#end_day').val();
      var domain_id = '".$domain_id."';
      var subdomain_id = '".$subdomain_id."';

      var select_activity = $('#select_activity').val(); // 活動優惠碼管理id

      if($('#offer_isopen').prop('checked')) {
        var offer_isopen = 1;
      }else{
        var offer_isopen = 0;
      }

      if($('#offer_pc_isshow').prop('checked')) {
        var offer_pc_isshow = 1;
      }else{
        var offer_pc_isshow = 0;
      }

      if($('#offer_mobile_isshow').prop('checked')) {
        var offer_mobile_isshow = 1;
      }else{
        var offer_mobile_isshow = 0;
      }

      var formData = new FormData();
      formData.append('editor_data', editor_data);
      formData.append('editor_data_id', editor_data_id);
      formData.append('offer_id', offer_id);
      formData.append('offer_name', offer_name);
      formData.append('offer_classification', offer_classification);
      formData.append('offer_start_img', offer_start_img);
      formData.append('upload_offer_start_img', upload_offer_start_img);
      formData.append('offer_end_img', offer_end_img);
      formData.append('upload_offer_end_img', upload_offer_end_img);
      formData.append('start_day', start_day);
      formData.append('end_day', end_day);
      formData.append('offer_isopen', offer_isopen);
      formData.append('offer_pc_isshow', offer_pc_isshow);
      formData.append('offer_mobile_isshow', offer_mobile_isshow);
      formData.append('domain_id', domain_id);
      formData.append('subdomain_id', subdomain_id);
      formData.append('select_activity',select_activity);

      if(jQuery.trim(offer_name) != '' && jQuery.trim(editor_data) != '' &&  jQuery.trim(offer_classification) != '请选择') {
        $('body').append('<div id=\"progress_bar\" style=\"width:100%;position: fixed;top: 47%;text-align: center;background-color: rgba(225, 225, 225, 0.3);\"><img width=\"40px\" height=\"40px\" src=\"./ui/loading_hourglass.gif\">请稍后...</div>');
        $.ajax({
          type: 'POST',
          url : 'promotional_editor_action.php?a=edit_offer',
          data : formData,
          cache:false,
          contentType: false,
          processData: false,
          success : function(result){
            $('#progress_bar').remove();
            $('#preview_result').html(result);
          },
          error: function(res){
            $('#progress_bar').remove();
            if(res.status == 413) {
              alert('".$tr['The file is too large (more than 2MB)']."');
            }
            else{
              alert('上传功能发生问题！请改用url方式添加优惠图片');
            }
          }
        });

      } else {
        alert('".$tr['Please confirm promotion name, start picture, end picture, sort and preferential content are correctly']."');
      }
    });

  })
  </script>
  ";

//$tr['Please confirm promotion name, start picture, end picture, sort and preferential content are correctly'] = '請確認優惠名稱、開始圖片、結束圖片、排序及優惠內容是否正確填入。';


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  沒有任何要求 列出系統中所有的入款帳戶資訊 END
  // -----------------------------------------------------------------------------------------------------------------------------------------------

  //都沒有以上的動作 顯示錯誤訊息 $tr['Wrong operation'] = '錯誤的操作';
} else {
  $show_list_html  = '(x) '.$tr['Wrong operation'];
}

  // 切成 1 欄版面
  $indexbody_content = '';
  $indexbody_content = $indexbody_content.'
	<div class="row justify-content-center">
    <div class="col-10 promotional_input">
    '.$promotional_classification_html.'
    <br>
		'.$show_list_html.'
		</div>
	</div>
	<br>
	<div class="row">
		<div id="preview_result"></div>
  </div>
  
  <!-- Modal -->
  <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">'.$tr['add classification'].'</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
         <input type="text" class="form-control" placeholder="'.$tr['classification name'].'" id="add_input_category">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-success" id="save">'.$tr['add'].'</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">'.$tr['off'].'</button>          
        </div>
      </div>
    </div>
  </div>  
  ';


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
// -----------------------------------------------------------------------------------------------------------------------------------------------
//  html 組合 end
// -----------------------------------------------------------------------------------------------------------------------------------------------

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