<?php use_layout( "template/member_tml.php" ) ?>

<!-- 页首 CSS 与 JS -->
<?php begin_section('extend_head') ?>
<style type="text/css">
  .show_query_option {
  padding-top: 3px;
  padding-bottom: 3px;
   }
  .show_pageinfo {
  padding-top: 3px;
  padding-bottom: 3px;
  }
</style>

<link rel="stylesheet" type="text/css" href="<?php echo $cdnfullurl_js ?? $config['cdn_baseurl'] . '/in/' ?>datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
<script type="text/javascript" language="javascript" src="<?php echo $cdnfullurl_js ?? $config['cdn_baseurl'] . '/in/' ?>datetimepicker/jquery.datetimepicker.full.min.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo $cdnfullurl_js ?? $config['cdn_baseurl'] . '/in/' ?>datatables/css/jquery.dataTables.min.css">
<script type="text/javascript" language="javascript" src="<?php echo $cdnfullurl_js ?? $config['cdn_baseurl'] . '/in/' ?>datatables/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" language="javascript" src="<?php echo $cdnfullurl_js ?? $config['cdn_baseurl'] . '/in/' ?>datatables/js/dataTables.bootstrap.min.js"></script>

<script type="text/javascript" language="javascript" class="init">
  $(document).ready(function() {
    $("#transaction_list").DataTable( {
    "dom": '<ftlip>',
    "search": true,
    "paging":   true,
    "ordering": true,
    "info":     true,
        "order": [[ 1, "asc" ]],
        "pageLength": 30
    } );
  });

  $('#search_agents').keyup(function(){
				tl_tabke.search($(this).val()).draw();
			});	
</script>

<script>
  window.userinfo = <?=$query_userinfo?>;
  // 保留当前值
  $(function(){
    $('.preferential_dispatch').on('focusin', function() {
    var _ = $(this);
    $(this).attr('data-current', _.val());
    });
    $('.dividend_dispatch').on('focusin', function() {
    var _ = $(this);
    $(this).attr('data-current', _.val());
    });
  });

  // 修改反水 ajax
  function select_preferentialratio_chang(uid, event){
    // console.log(event);
    var e = event.target;
    var change_value_div = e.options[e.selectedIndex].value;
    var preferentialratio = e.options[e.selectedIndex].text;
    // var csrftoken = '$csrftoken';
    var uaccount = e.dataset.account;
    var confirm_mesg = "确定更新帐号 "+uaccount+" 的反水分佣比例为 "+preferentialratio+"？(送出后不可还原为未设定)";

    ////
    var req_setting = {
    "url": "agents_setting_action.php",
    "method": "PUT",
    "headers": {
      "content-type": "application/x-www-form-urlencoded",
      "cache-control": "no-cache",
    },
    "data": {
      "action": "update_preferential",
      // "u_id": "1038",
      // "value": "30"
      "u_id": uid,
      "value": change_value_div
    }
    };

    // var pass = is_valid('preferential', {"className": "preferential_dispatch"});
    var pass = true;

    if(pass && confirm(confirm_mesg)) {
    var oldValue = e.dataset.current;
    $.ajax(req_setting).done(function(response) {
      // console.log(response);
      alert(response.message.description);
      $("#preview_result").html(response.message.description);
      if (change_value_div == '') $('[data-bind="p_agent_'+uid+'"]').html('0 %');
      else $('[data-bind="p_agent_'+uid+'"]').html(window.userinfo.preferential.allocable * 100 - change_value_div + ' %');
    }).fail(function(error) {
      // console.log(error.responseJSON);
      e.value = oldValue;
      alert(error.responseJSON.message.description);
    });
    }else{
    e.value = e.dataset.current
    // console.log("取消更新反水比例")
    return false;
    }
  }

  // 修改分佣 ajax
  function select_dividendratio_chang(uid, event){
    var e = event.target;
    var change_value_div = e.options[e.selectedIndex].value;
    var dividendratio = e.options[e.selectedIndex].text;
    // var csrftoken = '$csrftoken';
    var uaccount = e.dataset.account;
    var confirm_mesg = "确定更新帐号 "+uaccount+" 的佣金分佣比例为 "+dividendratio+"？(送出后不可还原为未设定)";

    ////
    var req_setting = {
    "url": "agents_setting_action.php",
    "method": "PUT",
    "headers": {
      "content-type": "application/x-www-form-urlencoded",
      "cache-control": "no-cache",
    },
    "data": {
      "action": "update_dividend",
      // "u_id": "1038",
      // "value": "30"
      "u_id": uid,
      "value": change_value_div
    }
    };

    // var pass = is_valid('dividend', {"className": "dividend_dispatch"});
    var pass = true;

    if(pass && confirm(confirm_mesg)) {
    var oldValue = e.dataset.current;
    $.ajax(req_setting).done(function(response) {
      // console.log(response);
      alert(response.message.description);
      $("#preview_result").html(response.message.description);
      if (change_value_div == '') $('[data-bind="d_agent_'+uid+'"]').html('0 %');
      else $('[data-bind="d_agent_'+uid+'"]').html(window.userinfo.dividend.allocable * 100 - change_value_div + ' %');
    }).fail(function(error) {
      // console.log(error.responseJSON);
      e.value = oldValue;
      alert(error.responseJSON.message.description);
    });
    }else{
    e.value = e.dataset.current
    // console.log("取消更新损益比例")
    return false;
    }
  }

  // 验证分派总和，以及自身剩下(实拿)
  // input 为要验证的动作, 必要变数
  function is_valid(type, params) {
    switch (type) {
    case 'preferential':
      var _obj = $('.' + params.className);

      var child_values = [];
      for (let index = 0; index < _obj.length; index++) {
      child_values.push(parseInt(_obj[index].value));
      }
      // console.log(child_values);
      var sum = child_values.reduce((a, b) => a + b, 0);
      var allocable = Math.floor(window.userinfo.feedbackinfo.preferential.allocable * 100);
      // console.log(sum);
      if(sum > allocable) {
      alert('分配给下线的反水总和('+ sum +'%)不得超过自身获得反水比例('+allocable+'%)!');
      return false
      }
      window.userinfo.feedbackinfo.preferential.occupied = Math.min(
      window.userinfo.feedbackinfo.preferential.occupied,
      window.userinfo.feedbackinfo.preferential.allocable - (sum / 100)
      );

      return true;
      break;

    case 'dividend':
      var _obj = $('.' + params.className);

      var child_values = [];
      for (let index = 0; index < _obj.length; index++) {
      child_values.push(parseInt(_obj[index].value));
      }
      // console.log(child_values);
      var sum = child_values.reduce((a, b) => a + b, 0);
      var allocable = Math.floor(window.userinfo.feedbackinfo.dividend.allocable * 100);
      // console.log(sum);
      if(sum > allocable) {
      alert('分配给下线的佣金总和('+ sum +'%)不得超过自身获得佣金比例('+allocable+'%)!');
      return false
      }
      window.userinfo.feedbackinfo.dividend.occupied = Math.min(
      window.userinfo.feedbackinfo.dividend.occupied,
      window.userinfo.feedbackinfo.dividend.allocable - (sum / 100)
      );

      return true;
      break;

    default:
      return false
      break;
    }
  }
</script>

<script>
  $(function() {
    // 一级代理商的分佣设定 select
    $('._1st_agent_setting')
    .one('focusin', function() {
    var _ = $(this);
    // console.log(_.val());
    $(this).attr('data-current', _.val());
    })
    .on('change', function(e) {
      // console.log(e);
      // console.log(e.delegateTarget.value);
      // console.log(e.currentTarget.value);
      // console.log(e.target.value);
      // console.log(e.target.dataset);
      // console.log(e.target.type);

      var ajax_setting = {
        "url": "agents_setting_action.php",
        "method": "PUT",
        "headers": {
        "content-type": "application/x-www-form-urlencoded",
        "cache-control": "no-cache",
        },
        "data": {
        "action": 'update_1st_agent_setting',
        "type_of_setting": e.target.dataset.type,
        "attr": e.target.dataset.action,
        "value": e.target.value,
        "u_id": e.target.dataset.agentid
        }
      };

      var confirm_msg;
      if (e.target.type == 'select-one') {
        confirm_msg = '[一级代理商设定]\n确定修改'+e.target.dataset.description+'为 '+e.target[e.target.selectedIndex].text+' 吗？\n此操作会重新生成所有下线代理商的默认值';
      } else if (e.target.type == 'radio') {
        confirm_msg = '[一级代理商设定] 确定修改'+e.target.dataset.description+'为 '+e.target.dataset.value+' 吗？';
      }

      if(confirm(confirm_msg)) {
        var oldValue = e.target.dataset.current;
          $.ajax(ajax_setting).done(function(response) {
          // console.log(response);
          window.userinfo.feedbackinfo = response.message.params.self_feedbackinfo;
          $('.page_allocable_preferential').html(Math.floor(window.userinfo.feedbackinfo.preferential.allocable * 100) + '%');
          $('.page_allocable_dividend').html(Math.floor(window.userinfo.feedbackinfo.dividend.allocable * 100) + '%');
          alert(response.message.description);
          // $("#preview_result").html(response.message.description);
          location.reload();
        }).fail(function(error) {
          // console.log(error.responseJSON);
          e.target.value = oldValue;
          alert(error.responseJSON.message.description);
        });
      }else{
        e.target.value = e.target.dataset.current;
        alert("取消更新一级代理商设定值")
        if (e.target.type == 'radio') location.reload();
        return false;
      }
    });

    // 套用特定預設值：反水
    var defaultPreferentialConfig = [
      {
        title: "自订",
        value: '',
        self_ratio: 0,
        child_occupied_min: 0,
        child_occupied_max: 0,
        last_occupied: 0
      },
      {
        title: "设置 1",
        value: 1,
        self_ratio: 5,
        child_occupied_min: 10,
        child_occupied_max: 20,
        last_occupied: 10
      },
      {
        title: "设置 2",
        value: 2,
        self_ratio: 8,
        child_occupied_min: 30,
        child_occupied_max: 48,
        last_occupied: 15
      },
      {
        title: "设置 3",
        value: 3,
        self_ratio: 18,
        child_occupied_min: 15,
        child_occupied_max: 26,
        last_occupied: 22
      },
    ];

    var $preferentialSwitcher = $('select[data-action="set_1st_agent_config"][data-type="preferential"]')
    var $preferentialSelfRatio = $('select[data-action="self_ratio"][data-type="preferential"]')
    var $preferentialChildOccupiedMin = $('select[data-action="child_occupied.min"][data-type="preferential"]')
    var $preferentialChildOccupiedMax = $('select[data-action="child_occupied.max"][data-type="preferential"]')
    var $preferentialLastOccupied = $('select[data-action="last_occupied"][data-type="preferential"]')
    var newCfgPreferential = function () {
      return {
        self_ratio: $preferentialSelfRatio.val(),
        child_occupied_min: $preferentialChildOccupiedMin.val(),
        child_occupied_max: $preferentialChildOccupiedMax.val(),
        last_occupied: $preferentialLastOccupied.val()
      }
    }
    var currentCfgPreferential = defaultPreferentialConfig.filter(function(item) {
        return item.self_ratio == newCfgPreferential().self_ratio &&
        item.child_occupied_min == newCfgPreferential().child_occupied_min &&
        item.child_occupied_max == newCfgPreferential().child_occupied_max &&
        item.last_occupied == newCfgPreferential().last_occupied
      }).pop() || newCfgPreferential()

      console.log(currentCfgPreferential)
    var isPreferentailConfigSetApplied = true

    $preferentialSwitcher.html(function () {
      let options = ''
      for (let index = 0; index < defaultPreferentialConfig.length; index++) {
        const element = defaultPreferentialConfig[index];
        options += `<option value="${element.value}">${element.title}</option>`
      }
      return options
    })
    .val( currentCfgPreferential.value || '' )
    $('.cfg_group_preferential').on('change', function(e) {
      confirm_preferential_config.disabled = false
      preferential_cfg_hint.innerHTML = "设置值有变更，变更完毕后请点击按钮确认修改"
      preferential_cfg_hint.classList.remove("alert-info")
      preferential_cfg_hint.classList.add("alert-warning")
    })

    $('[data-action="set_1st_agent_config"][data-type="preferential"]').on('change', function(e) {
      let switchTo = $(e.target).val()
      focusCfg = defaultPreferentialConfig.filter(function(item) {
        return item.value == switchTo
      }).pop() || newCfgPreferential()

      $preferentialSelfRatio.val(focusCfg.self_ratio)
      $preferentialChildOccupiedMin.val(focusCfg.child_occupied_min)
      $preferentialChildOccupiedMax.val(focusCfg.child_occupied_max)
      $preferentialLastOccupied.val(focusCfg.last_occupied)
    });

    // 确认更改一级代理商反水
    $('#confirm_preferential_config').on('click', function (e) {
      update_1st_agent_group_rebuild_tree(e, newCfgPreferential())
      confirm_preferential_config.disabled = true
      preferential_cfg_hint.innerHTML = "设置值已套用，页面重新加载"
      preferential_cfg_hint.classList.remove("alert-warning")
      preferential_cfg_hint.classList.add("alert-success")
    })
    //************************************************************************************* */

    // 套用特定預設值：分佣
    var defaultDividendConfig = [
      {
        title: "自订",
        value: '',
        child_occupied_min: 0,
        child_occupied_max: 0,
        last_occupied: 0
      },
      {
        title: "设置 1",
        value: 1,
        child_occupied_min: 10,
        child_occupied_max: 20,
        last_occupied: 10
      },
      {
        title: "设置 2",
        value: 2,
        child_occupied_min: 30,
        child_occupied_max: 48,
        last_occupied: 15
      },
      {
        title: "设置 3",
        value: 3,
        child_occupied_min: 15,
        child_occupied_max: 26,
        last_occupied: 22
      },
    ];

    var $dividendSwitcher = $('select[data-action="set_1st_agent_config"][data-type="dividend"]')
    var $dividendSelfRatio = $('select[data-action="self_ratio"][data-type="dividend"]')
    var $dividendChildOccupiedMin = $('select[data-action="child_occupied.min"][data-type="dividend"]')
    var $dividendChildOccupiedMax = $('select[data-action="child_occupied.max"][data-type="dividend"]')
    var $dividendLastOccupied = $('select[data-action="last_occupied"][data-type="dividend"]')
    var newCfgDividend = function () {
      return {
        self_ratio: $dividendSelfRatio.val(),
        child_occupied_min: $dividendChildOccupiedMin.val(),
        child_occupied_max: $dividendChildOccupiedMax.val(),
        last_occupied: $dividendLastOccupied.val()
      }
    }
    var currentCfgDividend = defaultDividendConfig.filter(function(item) {
        return item.self_ratio == newCfgDividend().self_ratio &&
        item.child_occupied_min == newCfgDividend().child_occupied_min &&
        item.child_occupied_max == newCfgDividend().child_occupied_max &&
        item.last_occupied == newCfgDividend().last_occupied
      }).pop() || newCfgDividend()

      console.log(currentCfgDividend)

    $dividendSwitcher.html(function () {
      let options = ''
      for (let index = 0; index < defaultDividendConfig.length; index++) {
        const element = defaultDividendConfig[index];
        options += `<option value="${element.value}">${element.title}</option>`
      }
      return options
    })
    .val( currentCfgDividend.value || '' )
    $('.cfg_group_dividend').on('change', function(e) {
      confirm_dividend_config.disabled = false
      dividend_cfg_hint.innerHTML = "设置值有变更，变更完毕后请点击按钮确认修改"
      dividend_cfg_hint.classList.remove("alert-info")
      dividend_cfg_hint.classList.add("alert-warning")
    })

    $('[data-action="set_1st_agent_config"][data-type="dividend"]').on('change', function(e) {
      let switchTo = $(e.target).val()
      focusCfg = defaultDividendConfig.filter(function(item) {
        return item.value == switchTo
      }).pop() || newCfgDividend()

      $dividendSelfRatio.val(focusCfg.self_ratio)
      $dividendChildOccupiedMin.val(focusCfg.child_occupied_min)
      $dividendChildOccupiedMax.val(focusCfg.child_occupied_max)
      $dividendLastOccupied.val(focusCfg.last_occupied)
    });

    // 确认更改一级代理商反水
    $('#confirm_dividend_config').on('click', function (e) {
      update_1st_agent_group_rebuild_tree(e, newCfgDividend())
      confirm_dividend_config.disabled = true
      dividend_cfg_hint.innerHTML = "设置值已套用，页面重新加载"
      dividend_cfg_hint.classList.remove("alert-warning")
      dividend_cfg_hint.classList.add("alert-success")
    })

    function update_1st_agent_group_rebuild_tree(e, $cfg_group) {
      var ajax_setting = {
        "url": "agents_setting_action.php",
        "method": "PUT",
        "headers": {
        "content-type": "application/x-www-form-urlencoded",
        "cache-control": "no-cache",
        },
        "data": {
        "action": 'update_1st_agent_group_rebuild_tree',
        "type_of_setting": e.target.dataset.type,
        "cfg_group": $cfg_group,
        "u_id": e.target.dataset.agentid
        }
      };

      var confirm_msg;
      confirm_msg = '[一级代理商设定]\n确定修改吗？\n此操作会重新生成所有下线代理商的默认值';

      if(confirm(confirm_msg)) {
          $.ajax(ajax_setting).done(function(response) {
          console.log(response);
          alert(response.message.description);
          location.reload();
        }).fail(function(error) {
          console.log(error.responseJSON);
          alert(error.responseJSON.message.description);
          location.reload();
        });
      } else {
        e.target.value = e.target.dataset.current;
        alert("取消更新一级代理商设定值")
        return false;
      }
    }
  })
</script>
<?php end_section() ?>

<!-- position info -->
<?php begin_section('page_title') ?>
<?php echo $menu_breadcrumbs ?>
<?php end_section() ?>

<!-- main title -->
<?php begin_section('paneltitle_content')  ?>
<?php echo $function_title ?>
<?php end_section() ?>

<!-- main content -->
<?php begin_section('panelbody_content') ?>
<?php echo $indexbody_content ?>
<?php end_section() ?>

<!-- main content -->
<?php begin_section('extend_js') ?>
<?php echo $extend_js ?>
<script>
  $(function() {
    $('[data-toggle="tooltip"]').tooltip();
    $('[data-toggle="popover"]').popover({ container: 'body' });
  });
</script>
<?php end_section() ?>
