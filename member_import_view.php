<!--member_import_view.php -->
<?php use_layout("template/beadmin.tmpl.php"); ?>

<!-- begin of extend_head -->
<?php begin_section('extend_head'); ?>
<!-- Jquery UI js+css  -->
<script src="in/jquery-ui.js"></script>
<link rel="stylesheet"  href="in/jquery-ui.css" >
<!-- Jquery blockUI js  -->
<script src="./in/jquery.blockUI.js"></script>
<!-- jquery datetimepicker js+css -->
<link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
<!-- Datatables js+css  -->
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
<!-- 自訂css -->
<link rel="stylesheet" type="text/css" href="ui/style_seting.css">

<?php end_section(); ?>
<!-- end of extend_head -->


<!-- begin of page_title -->
<?php begin_section('page_title'); ?>
<ol class="breadcrumb">
  <li><a href="home.php"><?php echo $tr['Home']; ?></a></li>
  <li><a href="#"><?php echo $tr['webmaster']; ?></a></li>
  <li class="active"><?php echo $function_title; ?></li>
</ol>
<?php end_section(); ?>
<!-- end of page_title -->

<!-- 主要內容  title -->
<!-- begin of paneltitle_content -->
<?php begin_section('paneltitle_content'); ?>
<i class="fas fa-user-lock"></i><?php echo $tr['Member import management']; ?>
<div id="csv"  style="float:right;margin-bottom:auto"></div>
<?php end_section(); ?>
<!-- end of paneltitle_content -->

<!-- 主要內容 content -->
<!-- begin of panelbody_content -->
<?php begin_section('panelbody_content'); ?>
<!-- <span class="glyphicon glyphicon-search" aria-hidden="true"></span><?php echo $tr['Search criteria']; ?> -->
<div>
  <div class="alert alert-success"><text> <?php echo $tr['Note that the following are site account restrictions, if they do not match, they will not be imported']; ?>。<br>1. <?php echo $tr['3 to 12 characters'];?><br>2. <?php echo $tr['A-z begins with a number and forces the account to be all lowercase'];?><br> </text></div>
  <form id="csv-submit-form" class="form-inline" method="post">
    <div class="input-group input-group-sm mr-1 my-1">
      <div class="input-group-addon"><?php echo $tr['excel'];?></div>
      <div class="input-group-addon">
        <input type="file" class="form-control" name="csv">
      </div>
    </div>
    <button class="btn btn-primary js-upload-csv mr-1 my-1"><?php echo $tr['upload'];?></button>
    <a type="button" class="btn btn-info import-confirm" href="member_import_action.php?a=import_template"><?php echo $tr['Sample file download'];?></a>
    <div id='confirm-btn' class='ml-auto'>
      <button class="btn btn-success import-confirm" onclick="confirmimport(); return false;"><?php echo $tr['import'];?></button>
      <button class="btn btn-danger import-confirm" onclick="clearimport(); return false;"><?php echo $tr['clear'];?></button>
    </div>
  </form>
</div>
<div class="modal fade bs-example-modal-lg" id="preview_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="myModalLabel"><?php echo $tr['Import status notification'];?></h4>
      </div>
      <div id="preview_result" class="modal-body">

     </div>
     <div class="modal-footer">
       <button type="button" class="btn btn-info" data-dismiss="modal" aria-label="Close"><?php echo $tr['confirm'];?></button>
     </div>
    </div>
  </div>
</div>
<hr>


<div id="inquiry_result_area">
<table id="show_list"  class="display" cellspacing="0" width="100%" >
</table>
</div>
<br>
<?php end_section(); ?>
<!-- end of panelbody_content -->



<!-- begin of extend_js -->
<?php begin_section('extend_js'); ?>
<script type="text/javascript" language="javascript">
function paginateScroll() { // auto scroll to top of page
  $("html, body").animate({
     scrollTop: 0
  }, 100);
}


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
    资料处理中...<img width="30px" height="30px" src="ui/loading.gif" />
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
       $('#preview_result').html(csvProccessingTmpl(100));
       return;
    }
    $('#preview_result').html(progressTmpl(Percentage));
  }
}

function clearimport() {
  $.ajax({
    type:'GET',
    url: 'member_import_action.php?a=clear_import',

    success:function(result){
      $('#preview_result').html(successTmpl(JSON.parse(result).message));
      $("#show_list").DataTable().ajax.reload();
      $('#preview_modal').modal({backdrop: 'static', keyboard: false});
    },

    error: function(res){
      // console.log(res.responseJSON);
      if(res.status == 406) {
        $('#preview_result').html(errorTmpl(res.responseJSON.message));
      } else if(res.status == 413) {
        $('#preview_result').html(errorTmpl('档案过大'));
      } else {
        $('#preview_result').html(errorTmpl('错误'));
      }
      $('#preview_modal').modal({backdrop: 'static', keyboard: false});
    }
  });
}

function confirmimport(){
  show_text = '确定汇入会员资料？'
  if(confirm(show_text)){
    $.ajax({
      type:'GET',
      url: 'member_import_action.php?a=import_confirm',
      xhr: function() {
        var myXhr = $.ajaxSettings.xhr();
        if(myXhr.upload){
          myXhr.upload.addEventListener('progress', progressUpdate, false);
        }
        return myXhr;
      },

      success:function(data){
        console.log(data);
        $('#preview_result').html(successTmpl(JSON.parse(data).message));
        $("#show_list").DataTable().ajax.reload();
        $('#preview_modal').modal({backdrop: 'static', keyboard: false});
      },

      error: function(res){
        // console.log(res);
        if(res.status == 406) {
          $('#preview_result').html(errorTmpl(JSON.parse(res.responseText).message));
        } else if(res.status == 413) {
          $('#preview_result').html(errorTmpl('档案过大'));
        } else {
          $('#preview_result').html(errorTmpl('错误'));
        }
        $('#preview_modal').modal({backdrop: 'static', keyboard: false});
      }
    });
  }
}

$(function() {

  $("#show_list")
    .on('xhr.dt', function (e, settings, json, xhr) {
            var dt_Totaldate = json.recordsFiltered
            // console.log( dt_Totaldate );
            if(dt_Totaldate > 0){
              $("#confirm-btn").show()
            }else{
              $("#confirm-btn").hide()
            }
      })
    .DataTable({
      "bProcessing": true,
      "bServerSide": true,
      "bRetrieve": true,
      "searching": false,
      "order": [[ 0, "asc" ]],
      "dom": 'frtip',
      // "drawCallback": confirm_btn(this),
      "ajax": {
        "url": "member_import_action.php?a=query_log",
        "async": true,
      },
      "oLanguage": {
        "sSearch": "游戏或类别:",
        "sEmptyTable": "<?php echo $tr['no data'];?>",//"目前没有资料!",
        "sLengthMenu": "<?php echo $tr['each page'];?>_MENU_<?php echo $tr['Count'];?>",//"每页显示 _MENU_ 笔",
        "sZeroRecords": "<?php echo $tr['no data'];?>",//"目前没有资料",
        "sInfo": "<?php echo $tr['now at'];?> _PAGE_，<?php echo $tr['total'];?> _PAGES_ <?php echo $tr['page'];?>",//"目前在第 _PAGE_ 页，共 _PAGES_ 页",
        //"sInfoEmpty": "<?php echo $tr['no data'];?>",//"目前没有资料",
        "sInfoFiltered": "<?php echo $tr['from'];?>_TOTAL_<?php echo $tr['filtering in data'];?>"//"(从 _TOTAL_ 笔资料中过滤)"
      },
      "columns": [
        { "data": "account", "title": "<?php echo $tr['Account'];?>" },
        { "data": "therole", "title": "<?php echo $tr['The Role'];?>" },
        { "data": "agent", "title": "<?php echo $tr['agent'];?>" },
        { "data": "enrollmentdate", "title": "<?php echo $tr['admission time'];?>" },
        { "data": "mobilenumber", "title": "<?php echo $tr['Cell Phone'];?>" },
        { "data": "sex", "title": "<?php echo $tr['Gender'];?>" },
        { "data": "email", "title": "Email" },
        { "data": "wechat", "title": "<?php echo $tr['WeChat Number'];?>" },
        { "data": "gtoken_balance", "title": "<?php echo $tr['Balance'];?>" }
        ]
      } );


    $('#csv-submit-form').submit(function(e){
      e.preventDefault();

      var formData = new FormData();
      formData.append('csv', $( 'input[name=csv]' )[0].files[0] );
      // console.log(formData);

      $.ajax({
        type:'POST',
        url: 'member_import_action.php?a=member_import',
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
          $('#preview_result').html(successTmpl(data.message));
          $("#show_list").DataTable().ajax.reload(null, false);
          $('#preview_modal').modal({backdrop: 'static', keyboard: false});
        },

        error: function(res){
          // console.log(res.responseJSON);
          if(res.status == 406) {
            $('#preview_result').html(errorTmpl(res.responseJSON.message));
          } else if(res.status == 413) {
            $('#preview_result').html(errorTmpl('档案过大'));
          } else {
            $('#preview_result').html(errorTmpl('错误'));
          }
          $('#preview_modal').modal({backdrop: 'static', keyboard: false});
        }
      });
    });

});

</script>
<?php end_section(); ?>
<!-- end of extend_js
