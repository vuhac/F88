<?php

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


?>
<style>
  ul {margin-left: 40px; padding-left: 0;}
  .glyphicon-info-sign {margin-right:0.5em;}
</style>
<!-- Modal -->
<div class="modal fade" tabindex="-1" role="dialog" aria-labelledby="category-modal-label" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title clearfix">
          <?php echo $tr['lottery import']; ?></h5>
          <button type="button" class="close pull-right" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>        
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-12">
            <div class="alert alert-info" role="alert">
              <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
              <?php echo $tr['notice']; ?>
              <ul>
                <li><?php echo $tr['Please do not issue game coins and cash at the same time, if necessary, please use separate files to import']; ?></li>
                <li><?php echo $tr['Do not set game coins and cash to negative numbers']; ?></li>
                <li><?php echo $tr['The bonus invalid time zone is East U.S. Time, please fill in carefully']; ?></li>
                <li><?php echo $tr['Do not set the bonus invalid time to expired time']; ?></li>
              </ul>
            </div>
            <form id="csv-submit-form" class="form-inline" method="post">
              <div class="input-group input-group-sm mr-1 my-1">
                <div class="input-group-addon">Excel</div>
                <div class="input-group-addon">
                  <input type="file" class="form-control" name="csv">
                </div>
              </div>
              <button class="btn btn-primary js-upload-csv mr-1 my-1"><?php echo $tr['upload']; ?></button>
              <a href="receivemoney_import_action.php?a=get_csv_template" class="btn btn-primary mr-1 my-1"><?php echo $tr['getting excel template']; ?></a>
            </form>
          </div>
          <div class="col-12" style="height:15px;"></div>
          <div id="csv-upload-progress" class="col-12"></div>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript">
function errorTmpl(message) {
  return `
    <div class="alert alert-warning text-center">
      ${message}
    </div>
  `
}

function successTmpl(message) {
  return `
    <div class="alert alert-success text-center">
      ${message}
    </div>
  `
}

function progressTmpl(percentage) {
  return `
  <div class="progress">
    <div class="progress-bar" role="progressbar" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100" style="min-width: 2em; width: ${percentage}%;">
      ${percentage}%
    </div>
  </div>
  `
}

function csvProccessingTmpl() {
  return `
  <h5 align="center">
    新增彩金中...<img width="30px" height="30px" src="ui/loading.gif" />
  </h5>
  `
}

function progressUpdate(e) {
  if(e.lengthComputable){
    var max = e.total;
    var current = e.loaded;

    var Percentage = Math.round( (current * 100)/max );
    // console.log('progress: ' + Percentage + '%');

    if(Percentage >= 100) {
       // process completed
       $('#csv-upload-progress').html(csvProccessingTmpl(100));
       return;
    }
    $('#csv-upload-progress').html(progressTmpl(Percentage));
  }
}

$(function(){
  $('#csv-submit-form').submit(function(e){
    e.preventDefault();

    var formData = new FormData();
    formData.append('csv', $( 'input[name=csv]' )[0].files[0] );
    // console.log(formData);

    $.ajax({
      type:'POST',
      url: 'receivemoney_import_action.php?a=upload_csv',
      data:formData,
      xhr: function() {
        var myXhr = $.ajaxSettings.xhr();
        if(myXhr.upload){
          myXhr.upload.addEventListener('progress', progressUpdate, false);
        }
        return myXhr;
      },
      cache:false,
      contentType: false,
      processData: false,

      success:function(data){
        // console.log(data);
        $('#csv-upload-progress').html(successTmpl(data.message));
      },

      error: function(res){
        // console.log(res.responseJSON);
        if(res.status == 406) {
          $('#csv-upload-progress').html(errorTmpl(res.responseJSON.message));
        } else if(res.status == 413) {
          $('#csv-upload-progress').html(errorTmpl('档案过大'));
        } else {
          $('#csv-upload-progress').html(errorTmpl('错误'));
        }
      }
    });

  });
});
</script>
