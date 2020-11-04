<?php
// ----------------------------------------------------------------------------
// Features:  後台 -- ui設定
// File Name: 
// Author:     orange
// Related: uisetting_action
// DB Table:
// Log:
// ----------------------------------------------------------------------------

//----------------------------------------------------------------------------------------------
// 目錄
// 1.theme配色
// 
//----------------------------------------------------------------------------------------------

//----------------------------------------------------------------------------------------------
// theme配色
//----------------------------------------------------------------------------------------------
$color = array(
  "switch"=> ($ui_data['template_themes']['switch'])? "checked":"",
  "main_color"=> (isset($ui_data['template_themes']['main_color']))? substr($ui_data['template_themes']['main_color'],1):"ffffff",
  "sub_color"=> (isset($ui_data['template_themes']['sub_color']))? substr($ui_data['template_themes']['sub_color'],1):"ffffff",
  "font_color"=> (isset($ui_data['template_themes']['font_color']))? substr($ui_data['template_themes']['font_color'],1):"ffffff"
);
$color_m = array(
  "switch"=> ($ui_data['template_themes_m']['switch'])? "checked":"",
  "main_color"=> (isset($ui_data['template_themes_m']['main_color']))? substr($ui_data['template_themes_m']['main_color'],1):"ffffff",
  "sub_color"=> (isset($ui_data['template_themes_m']['sub_color']))? substr($ui_data['template_themes_m']['sub_color'],1):"ffffff",
  "font_color"=> (isset($ui_data['template_themes_m']['font_color']))? substr($ui_data['template_themes_m']['font_color'],1):"ffffff"
);
$theme_ver=array('color','color_m');

foreach ($theme_ver as $key => $value) {

$themes_color[$key]=<<<HTML
<form id="form_{$value}" class="themes_color" style="width: 275px;">
  <div class="mb-3">
    <span class="mr-1">
      <span class="glyphicon glyphicon-info-sign mr-2" data-toggle="tooltip" data-placement="top"  title="{$tr['template theme switch alert']}"></span>
    {$tr['Enabled state']}：
    </span>
    <span class="material-switch pull-left">
        <input class="themes_switch checkbox_switch" type="checkbox" {${$value}['switch']}>
        <label for="themes_switch" class="label-success"></label>
    </span>    
  </div>
  <div>
    <span class="glyphicon glyphicon-info-sign mr-2" data-toggle="tooltip" data-placement="top"  title="{$tr['template theme tips']}"></span>
  </div>
  <div class="input-group mb-3">
    <div class="input-group-prepend">
      <span class="input-group-text">{$tr['Main color']}</span>
    </div>
    <input type="text" name="main_color" value="{${$value}['main_color']}" class="form-control c-pick" disabled="disabled"/>
    <span class="border ml-3 color_view" style="background-color:#{${$value}['main_color']}; width: 33px;height: 33px;" class="border ml-3"></span>
  </div>
  <div class="input-group mb-3">
    <div class="input-group-prepend">
      <span class="input-group-text">{$tr['Sub color']}</span>
    </div>
    <input type="text" name="sub_color" value="{${$value}['sub_color']}" class="form-control c-pick" disabled="disabled"/>
    <span class="border ml-3 color_view" style="background-color:#{${$value}['sub_color']}; width: 33px;height: 33px;" class="border ml-3"></span>
  </div>
  <div class="input-group mb-3">
    <div class="input-group-prepend">
      <span class="input-group-text">{$tr['Text color']}</span>
    </div>
    <input type="text" name="font_color" value="{${$value}['font_color']}" class="form-control c-pick" disabled="disabled"/>
    <span class="border ml-3 color_view" style="background-color:#{${$value}['font_color']}; width: 33px;height: 33px;" class="border ml-3"></span>
  </div>
  <div class="input-group mb-3">
    <input type="button" id="theme_{$value}" class="mt-2 save btn btn-success btn-sm" value="{$tr['save setting']}">
  </div>
</form>
HTML;

}


$deposit_js=<<<HTML
<link href="in/jquery.colorpicker/jquery.colorpicker.css" rel="stylesheet" /> 
<script src="in/jquery.colorpicker/jquery.colorpicker.js" type="text/javascript"></script>
<script type="text/javascript">
$(document).ready(function() {
  $(".c-pick").colorpicker({closeOnEscape:false,closeOnOutside:false});  
});

//開關
$(document).on('click', '.themes_color .material-switch', function(){
  var checked =$(this).find(".themes_switch").prop("checked");
  $(this).find(".themes_switch").prop("checked",!checked);
});

$('.themes_color .c-pick').each(function(index){
  $(this).change(function(){
    $(this).next('.color_view').css("background-color","#"+$(this).val());
  });
});
$('.themes_color').on('click','.color_view',function(){
  $(this).prev('.c-pick').colorpicker('open');
});
//儲存色碼時最前方會加一個字元避免被當作number處理
$('.themes_color').on('click','.save',function(){
  var theme_device = $(this).attr("id");
  var theme_form = $(this).parents("form").attr("id");
  var color=[];
  $('#'+theme_form+' .c-pick').each(function(index){
    color.push('c'+$(this).val());
  });
  var checked = $('#'+theme_form+' .material-switch').find(".themes_switch").prop("checked");
  if(theme_device == 'theme_color')
    var index = ["template_themes"];
  else
    var index = ["template_themes_m"];
  var data = {
    "switch": checked ? 1 : 0,
    "main_color": color[0],
    "sub_color": color[1],
    "font_color": color[2]
  }
  json_send('update',index,data);
});
</script>
<style type="text/css">
.color_view{
  cursor: pointer;
}
.themes_color .c-pick{
  border-top-right-radius: .25rem;
  border-bottom-right-radius: .25rem;
}
</style>
HTML;

//-------------------------------------------------------------------------------------------
//card區塊分區塊 並放入templ
//-------------------------------------------------------------------------------------------
$card_templ='';
$card_content =array(
  0=>array('<i class="fas fa-palette mr-2"></i>'.$tr['desktop template edit'],$themes_color[0]),
  1=>array('<i class="fas fa-palette mr-2"></i>'.$tr['mobile template edit'],$themes_color[1])
);
for ($i=0; $i < count($card_content); $i++) { 
  $card_templ.=<<<HTML
    <div class="card mt-3 mb-4">
      <h5 class="card-header">
        {$card_content[$i][0]}
      </h5>
      <div class="card-body">
        {$card_content[$i][1]}
      </div>
    </div>
HTML;

}

//將html放入content
$uisetting_view_template = '';
$uisetting_view_template .=$card_templ;
$extend_js .=$deposit_js;

?>