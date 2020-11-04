<?php
// ----------------------------------------------------------------------------
// Features:  后台 -- ui设定
// File Name: 
// Author:     orange
// Related: uisetting_action
// DB Table:
// Log:
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
//----------------------------------------------------------------------------------------------
// 目录
// 关于我文案
// 合作伙伴
// 入款资讯
// 
//----------------------------------------------------------------------------------------------
//----------------------------------------------------------------------------------------------
// 关于我文案
//----------------------------------------------------------------------------------------------
$now_lang = $_SESSION['lang'];

$content_txt = array(
  "aboutus" => ($ui_data['copy']['aboutus'][$now_lang])?? "",
  "contactus" => ($ui_data['copy']['contactus'][$now_lang])?? "",
  "howtodeposit" => ($ui_data['copy']['howtodeposit'][$now_lang])?? "",
  "howtowithdraw" => ($ui_data['copy']['howtowithdraw'][$now_lang])?? "",
  "agent_instruction" => ($ui_data['copy']['agent_instruction'][$now_lang])?? "",
  "fastpay" => ($ui_data['copy']['fastpay'][$now_lang])?? "",
  "companypay" => ($ui_data['copy']['companypay'][$now_lang])?? ""
);

$content_tit =array(
  "aboutus" => $tr['About us'],
  "contactus" => $tr['Contact US'],
  "howtodeposit" =>$tr['How to deposit'],
  "howtowithdraw" => $tr['How Withdrawal'],
  "agent_instruction" => $tr['agent_instruction'],//'代理商说明'
  "fastpay" => $tr['onlinepay'],
  "companypay" => $tr['company deposits']
);

$language_selector =array(
  "zh-cn" => '简体中文',
  "zh-tw" => '繁体中文',
  "en-us" => 'English',
  "vi-vn" => 'Việt Nam',
  "id-id" => 'Indonesia',
  "th-th" => 'ไทย',
  "ja-jp" => '日本語'
);

$lang_dropdown ='';
//$_SESSION['lang'] 目前语系
foreach ($language_selector as $key => $value) {
  $lang_dropdown .='<div class="dropdown-item" data-lang="'.$key.'">'.$value.'</div>';
}

$lang_dropdown =<<<HTML
  <div class="lang_dropdown">
    <button class="btn btn-secondary dropdown-toggle mb-3 lang-btn" type="button" id="lang-toggle" data-lang="{$now_lang}" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
      {$language_selector[$now_lang]}
    </button>
    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
      {$lang_dropdown}
    </div>
  </div>
HTML;

$content_table='';
$content_table_js='';

$content_tab =<<<HTML
  <ul class="nav nav-tabs card-header-tabs" id="tabforcontent" role="tablist">
HTML;

foreach ($content_txt as $key => $value) {
  $act = '';
if($key == 'aboutus')
  $act = ' active';

$content_tab.=<<<HTML
    <li class="nav-item">
      <a class="nav-link{$act}" id="{$key}-tab" data-toggle="tab" href="#tab_{$key}" role="tab" aria-controls="{$key}">{$content_tit[$key]}</a>
    </li>
HTML;
}
$content_tab.='</ul>';

$content_table.=<<<HTML
<div class="tab-content content" id="content">
HTML;

foreach ($content_txt as $key => $value) {
  $act = '';
if($key == 'aboutus')
  $act = ' show active';
$content_table.=<<<HTML
  <div class="tab-pane fade{$act}" id="tab_{$key}" role="tabpanel" aria-labelledby="{$key}-tab">
    {$lang_dropdown}
    <form id="form_{$key}">
      <textarea class="site-copy-editor" placeholder="{$tr['Please enter a copy']}'{$content_tit[$key]}'" name="editor_{$key}">
      {$value}
      </textarea>
      <input type="button" name="editor_{$key}" data-lang="{$now_lang}" class="mt-2 mx-1 save btn btn-success btn-sm" value="{$tr['save setting']}">
    </form>
  </div>
HTML;
$content_table_js.=<<<HTML
CKEDITOR.replace('editor_{$key}',{
    customConfig: 'uisetting_config.js'
  });
HTML;
}

$content_table.=<<<HTML
  </div>
HTML;

//----------------------------------------------------------------------------------------------
// 合约管理
//----------------------------------------------------------------------------------------------
$terms_txt = array(
  "member" => ($ui_data['copy']['member'][$now_lang])?? "",
  "privacy" => ($ui_data['copy']['privacy'][$now_lang])?? "",
  "partner" => ($ui_data['copy']['partner'][$now_lang])?? ""
);
$terms_tit =array(
  "member" => $tr['membership terms'],
  "privacy" => $tr['Personal data and privacy protection policy'],
  "partner" => $tr['cooperation agreement']
);
$terms_table='';

$terms_tab =<<<HTML
  <ul class="nav nav-tabs card-header-tabs" id="tabforterms" role="tablist">
HTML;

foreach ($terms_txt as $key => $value) {
  $act = '';
if($key == 'member')
  $act = ' active';

$terms_tab.=<<<HTML
    <li class="nav-item">
      <a class="nav-link{$act}" id="{$key}-tab" data-toggle="tab" href="#tab_{$key}" role="tab" aria-controls="{$key}">{$terms_tit[$key]}</a>
    </li>
HTML;
}
$terms_tab.='</ul>';

$terms_table.=<<<HTML
<div class="tab-content content" id="terms_content">
HTML;

foreach ($terms_txt as $key => $value) {
  $act = '';
if($key == 'member')
  $act = ' show active';
$terms_table.=<<<HTML
  <div class="tab-pane fade{$act}" id="tab_{$key}" role="tabpanel" aria-labelledby="{$key}-tab">
    {$lang_dropdown} 
    <form id="form_{$key}">
      <textarea class="terms-copy-editor" placeholder="{$tr['Please enter a copy']}'{$terms_tit[$key]}'" name="editor_{$key}">
      {$value}
      </textarea>
      <input type="button" name="editor_{$key}" data-lang="{$now_lang}" class="mt-2 mx-1 save btn btn-success btn-sm" value="{$tr['save setting']}">
    </form>
  </div>
HTML;
$content_table_js.=<<<HTML
CKEDITOR.replace('editor_{$key}',{
    customConfig: 'uisetting_config.js'
  });
HTML;
}
$terms_table.=<<<HTML
  </div>
HTML;

$content_js ='<script src="in\ckeditor\ckeditor.js"></script>
<script>CKEDITOR.dtd.$removeEmpty[\'span\'] = false;</script>'.<<<HTML
<script type="text/javascript">
if ( CKEDITOR.env.ie && CKEDITOR.env.version < 9 )
  CKEDITOR.tools.enableHtml5Elements( document );
  CKEDITOR.config.height = 300;
  CKEDITOR.config.width = 'auto';
  {$content_table_js}
$( document ).ready(function() {
  $("#view_copy .content-card").each(function(){
    var cardname = $(this).attr("id");
    //选择语系刷新内容
    $('#view_copy #'+cardname+' .lang_dropdown').on('click','.dropdown-item',function(){
      var lang = $(this).attr("data-lang");
      var langtxt = $(this).text();
      $('#'+cardname+' #lang-toggle').text(langtxt);
      $('#view_copy #'+cardname+' .save').attr('data-lang',lang);
      json_get(function(data){
        $.each(CKEDITOR.instances,function(index, value) {
          if(value.element.getAttribute('class') == cardname+'-editor'){
            var index_at = index.slice(7);            
            if(typeof data['copy'][index_at][lang] != 'undefined')
              var settext = data['copy'][index_at][lang];
            else
              var settext = '';
            value.setData(settext);
          }
          
        });
      });
    });
    //储存相对应语系资料
    $("#view_copy #"+cardname).on('click','.save',function(){
      //console.log(CKEDITOR.instances);  
      var keyname = $(this).attr("name");
      var lang = $(this).attr("data-lang");
      var index_at = keyname.slice(7)
      var data = new Object();
      var textarea = CKEDITOR.instances[keyname].getData();
      data[lang] = textarea;
      var index = ['copy',index_at];
      json_send('trigger',index,data);
    });

  });    
});
</script>
HTML;
//----------------------------------------------------------------------------------------------
// 入款资讯
//----------------------------------------------------------------------------------------------
$deposit_info=<<<HTML
<div>
  <button type="button" name="new-deposit" class="my-2 mx-1 edit-deposit btn btn-success btn-sm"  data-toggle="modal" data-target="#new-deposit" data-backdrop="static">
  {$tr['Add a deposit link']}</button>
</div>
<table id="deposit" class="table border table-hover">
    <thead class="thead-light">
      <tr>
        <th clas="col-1">{$tr['Drag sort']}</th>
        <th>{$tr['Payment Types']}</th>
        <th>{$tr['Discount copy']}</th>
      </tr>
    </thead>
    <tfoot class="thead-light">
      <tr>
        <th>{$tr['Drag sort']}</th>
        <th>{$tr['Payment Types']}</th>
        <th>{$tr['Discount copy']}</th>
      </tr>
    </tfoot>
    <tbody id="deposit_table">
HTML;

$deposit_table_item='';
$deposit_table_item_content=array(
"companypay"=> $tr['company deposits'],
"fastpay"=> $tr['onlinepay'] 
);
if(!isset($ui_data['deposit']['sort'])|| count($ui_data['deposit']['sort'])==0){
  $ui_data['deposit']['sort']=["companypay","fastpay"];  
}
$tick=1;
/*<button type="button" name="{$value}" class="edit btn btn-primary btn-sm" onclick="openedit(this,'static')">{$tr['edit']}</button>*/
foreach ($ui_data['deposit']['sort'] as $key => $value) {
  if(isset($value,$deposit_table_item_content[$value])){
    $deposit_table_item.=<<<HTML
    <tr>
      <td>{$tick}</td>
      <td class="deposit_title" name="{$value}">$deposit_table_item_content[$value]</td>
      <td>      
      </td>
    </tr>
HTML;
}
  else{
    $deposit_table_item.=<<<HTML
      <tr>
        <td>{$tick}</td>
        <td class="deposit_title" name="{$value}">{$ui_data['deposit'][$value]["title"]}</td>
        <td>
        <button type="button" name="{$value}" class="edit btn btn-primary btn-sm" onclick="openedit(this,'new')">{$tr['edit']}</button>
        <button type="button" name="{$value}" class="edit btn btn-danger btn-sm" onclick="deletedeposit(this)">{$tr['delete']}</button>
        </td>
    </tr>
HTML;
  }
  $tick++;
}

$deposit_info.=$deposit_table_item.<<<HTML
    </tbody>
</table>

<!-- Modal -->
<div class="modal fade" id="edit-deposit" tabindex="-1" role="dialog" aria-labelledby="edit-deposit" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{$tr['edit']}</h5>
      </div>
      <div class="modal-body">
        <form>
          <div class="form-group mb-3 mr-1 edittarget">              
          </div>
          <div class="input-group mb-3 mr-1">
              <label class="mx-1">{$tr['Discount copy']}：</label>
              <textarea rows="4" cols="50" class="form-control" name="edit-deposit-content" required></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">        
        <button type="button" class="btn btn-success" onclick="editdeposit('save')">{$tr['Save']}</button>
        <button type="button" class="btn btn-danger" onclick="editdeposit('close')">{$tr['close without save']}</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="new-deposit" tabindex="-1" role="dialog" aria-labelledby="new-deposit" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{$tr['Add a deposit link']}</h5>
      </div>
      <div class="modal-body">
        <form>
          <div class="form-group mb-3 mr-1">
              <label class="mx-1">{$tr['Payment Types']}：</label>
              <input type="text" id="new-deposit-title" class="form-control">
          </div>
          <div class="form-group mb-3 mr-1">
              <label class="mx-1"><span class="glyphicon glyphicon-info-sign mr-2" data-toggle="tooltip" data-placement="top"  title="{$tr['included']}http(s)://"></span>{$tr['Payment link']}：</label>
              <input type="text" id="new-deposit-link" class="form-control">
          </div>
          <div class="input-group mb-3 mr-1">
              <label class="mx-1">{$tr['Discount copy']}：</label>
              <textarea rows="4" cols="50" class="form-control" name="new-deposit-content" required></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">        
        <button type="button" class="btn btn-success" onclick="newdeposit('save')">{$tr['Save']}</button>
        <button type="button" class="btn btn-danger" onclick="newdeposit('close')">{$tr['close without save']}</button>
      </div>
    </div>
  </div>
</div>

HTML;

$deposit_js=<<<HTML
<script type="text/javascript">
if ( CKEDITOR.env.ie && CKEDITOR.env.version < 9 )
CKEDITOR.tools.enableHtml5Elements( document );

CKEDITOR.replace('edit-deposit-content',{
  height:300,
  width:'100%',
  customConfig: 'uisetting_config.js'
});

CKEDITOR.replace('new-deposit-content',{
  height:300,
  width:'100%',
  customConfig: 'uisetting_config.js'
});

//table排序整理
function savesort(table,op_alert=0){
    var index = ["deposit","sort"];
    var data = [];
    $(table).children().each(function(index) {
      $(this).find('td').first().html(index + 1);
      data.push($(this).find('.deposit_title').attr('name'));
    });
    json_send('update',index,data,null,op_alert);
}

$( "#view_copy #deposit_table" ).sortable( {
  update: function( event, ui ) {
    savesort(this,1);
  }
});

//开启编辑视窗
function openedit(btn,type){
  var btnname = $(btn).attr("name");
    var targetname = $("#view_copy .deposit_title[name='"+btnname+"']").text();
  if(type == 'static'){    
    var target = '<label class="mx-1" id="edittarget" data-type="static" name="'+btnname+'">'+targetname+'</label>';
    json_get(function(data){   
      if(typeof data['deposit'][btnname] != 'undefined' )   
        CKEDITOR.instances['edit-deposit-content'].setData(data['deposit'][btnname]);
    });
    target='<label class="mx-1">{$tr['Payment Types']}：</label>'+target;
    $('#view_copy #edit-deposit .edittarget').html(target);
  }
  else{        
    json_get(function(data){
      var target = '<input type="text" id="edittarget" data-type="new" name="'+btnname+'" class="form-control mb-3" value="'+targetname+'"></div>\
      <div class="form-group mb-3 mr-1"><label class="mx-1"><span class="glyphicon glyphicon-info-sign mr-2" data-toggle="tooltip" data-placement="top"  title="{$tr['included']}http(s)://"></span>{$tr['Payment link']}：</label><input type="text" id="new-deposit-link" class="form-control" value="'+data['deposit'][btnname]["link"]+'">';
      CKEDITOR.instances['edit-deposit-content'].setData(data['deposit'][btnname]["content"]);
      target='<label class="mx-1">{$tr['Payment Types']}：</label>'+target;
      $('#view_copy #edit-deposit .edittarget').html(target);
      $('[data-toggle="tooltip"]').tooltip();
    });
  }

  $('#edit-deposit').modal({
    backdrop:'static'
  });
};

//新增动作
function newdeposit(act){
  switch (act) {
    case 'save':
      if($("#new-deposit-title").val()==""){
        alert("{$tr['Payment link warning']}");
        return false;
      }
      var index = ["deposit"];
      var data = new Object();
      var deposit_name = "no"+ Math.floor(Math.random()*1000)+1;      
      data[deposit_name] = {
        "title":"",
        "content":"",
        "link":""
      };
      json_send('trigger',index,data,function(){
        var contentindex = ["deposit",deposit_name];
        var contentdata = {
          "title": $("#new-deposit-title").val(),
          "content":CKEDITOR.instances['new-deposit-content'].getData(),
          "link": $("#new-deposit-link").val()
        };

        var order = $('#deposit_table tr:last-child td:first-child').html();
        order=parseInt(order)+1;
        var table ='<tr class="ui-sortable-handle">\
        <td>'+order+'</td>\
        <td class="deposit_title" name="'+deposit_name+'">'+contentdata["title"]+'</td>\
        <td>\
        <button type="button" name="'+deposit_name+'" class="edit btn btn-primary btn-sm" onclick="openedit(this,\'new\')">{$tr['edit']}</button>\
        <button type="button" name="'+deposit_name+'" class="edit btn btn-danger btn-sm" onclick="deletedeposit(this)">{$tr['delete']}</button></td>\
      </tr>';
        $("#deposit_table").append(table);
        savesort("#view_copy #deposit_table");
        json_send('trigger',contentindex,contentdata,function(){  
          $('#new-deposit form').get()[0].reset();      
          CKEDITOR.instances['new-deposit-content'].setData('');
          $("#new-deposit").modal('hide');
        },0);
      });

      break;    
    default:
     var close = confirm("{$tr['close without save']}?");
     if (close==true){
        $("#new-deposit form").get()[0].reset();
        CKEDITOR.instances['new-deposit-content'].setData('');
        $("#new-deposit").modal('hide');
      }
      break;
  }  
}

function editdeposit(act){
  switch (act) {
    case 'save':
      var type = $('#view_copy #edit-deposit #edittarget').attr("data-type");
      var deposit_name = $('#view_copy #edit-deposit #edittarget').attr("name");
      if(type == "static"){        
        var index = ["deposit"];
        var data = new Object();      
        data[deposit_name] = CKEDITOR.instances['edit-deposit-content'].getData();
      }
      else{
         var index = ["deposit",deposit_name];
         var data = {
          "title" : $('#view_copy #edit-deposit #edittarget').val(),
          "content" : CKEDITOR.instances['edit-deposit-content'].getData(),
          "link" : $('#view_copy #edit-deposit #new-deposit-link').val()
         }
      }
      json_send('trigger',index,data,function(){        
        CKEDITOR.instances['edit-deposit-content'].setData('');
        if(type != "static")
          $('#view_copy #deposit_table .deposit_title[name="'+deposit_name+'"]').text(data.title);
        $("#edit-deposit").modal('hide');

      });
      break;    
    default:
     var close = confirm("{$tr['close without save']}?");
     if (close==true){
        $("#edit-deposit form").get()[0].reset();
        CKEDITOR.instances['edit-deposit-content'].setData('');
        $("#edit-deposit").modal('hide');
      }
      break;
  }  
}

function deletedeposit(btn){
  var btnname = $(btn).attr("name");
  var delete_index = ["deposit",btnname]
  json_send('delete',delete_index,[],function(){
    $('#view_copy #deposit_table .deposit_title[name="'+btnname+'"]').parent("tr").remove();
    savesort("#view_copy #deposit_table");
  });

}
$(function () {
  $('[data-toggle="tooltip"]').tooltip()
})
</script>
<style type="text/css">
  .ui-sortable-handle td:nth-child(1),.ui-sortable-handle td:nth-child(2){
    cursor: move;
  }
</style>
HTML;

//-------------------------------------------------------------------------------------------
//card区块分区块 并放入templ
//-------------------------------------------------------------------------------------------
$card_templ='';
$card_content =array(
  0=>array('<h5><i class="far fa-edit mr-2"></i>'.$tr['website content'].'</h5>'.$content_tab,$content_table,'site-copy'),
  1=>array('<h5><i class="far fa-edit mr-2"></i>'.$tr['terms content'].' <button type="button" data-target="#howtouse_content" data-toggle="modal" class="float-right btn btn-secondary btn-sm rounded-circle mx-2"><i class="fas fa-question fa-xs"></i></button></h5>'.$terms_tab,$terms_table,'terms-copy'),
  2=>array('<h5><i class="far fa-edit mr-2"></i>'.$tr['payment content'].' <button type="button" data-target="#howtouisetting_deposit" data-toggle="modal" class="float-right btn btn-secondary btn-sm rounded-circle mx-2"><i class="fas fa-question fa-xs"></i></button></h5>  ',$deposit_info,'deposit-copy')
);
if($view_mode=='maindomain'){
  unset($card_content[2]);
  $uisetting_view_copy_alert =<<<HTML
<div class="alert alert-info mt-2" role="alert">
  {$tr['content setting tips 1']}
</div>
HTML;
}else{
  $uisetting_view_copy_alert =<<<HTML
<div class="alert alert-info mt-2" role="alert">
  {$tr['content setting tips 2']}
</div>
HTML;
}
for ($i=0; $i < count($card_content); $i++) { 
  $card_templ.=<<<HTML
    <div id="{$card_content[$i][2]}" class="content-card card mt-3 mb-4">
      <div class="card-header">
        {$card_content[$i][0]}
      </div>
      <div class="card-body">
        {$card_content[$i][1]}
      </div>
    </div>
HTML;
}

//将html放入content
$uisetting_view_copy = '';
$uisetting_view_copy .= $uisetting_view_copy_alert.$card_templ.<<<HTML
<div class="modal fade" id="howtouisetting_deposit" tabindex="-1" role="dialog"  aria-labelledby="myLargeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{$tr['description']}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info" role="alert">
          <p class="m-0"> 1. {$tr['payment content setting tips 1']}<a href="member_grade_config.php">"{$tr['member grade']}"</a></p>
        </div>
        <div class="alert alert-info" role="alert">
          <p class="m-0"> 2. {$tr['payment content setting tips 2']}</p>
        </div>
        <div class="w-100">
        <img class="w-100" style="height: auto;" src="in/component/preview/howtouse_deposit.jpg" alt="">            
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="howtouse_content" tabindex="-1" role="dialog"  aria-labelledby="myLargeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{$tr['description']}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info" role="alert">
          <p class="m-0">{$tr['If the copy is not set (blank), the default template will be automatically applied.']}</p>
        </div>
        <div class="w-100">
        <img class="w-100" style="height: auto;" src="in/component/preview/howtouse_content.jpg" alt="">            
        </div>
      </div>
    </div>
  </div>
</div>
HTML;

if($view_mode=='maindomain'){
  $extend_js .=$content_js;
}else{
  $extend_js .=$content_js.$deposit_js;
}

?>