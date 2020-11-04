<?php
// ----------------------------------------------------------------------------
// Features: 子網域管理Datatable初始化
// File Name:	subdomain_management_init_action.php
// Author: Neil
// Related:
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/lib_subdomain_management.php";


if(!isset($_SESSION['agent']) || !in_array($_SESSION['agent']->account,$su['ops'])) {
  header('Location:./home.php');
  die();
}

if(isset($_GET['_'])) {
  $secho = $_GET['_'];
} else {
  $secho = '1';
}

$open = [
  0 => $tr['close domain'],
  1 => $tr['open domain'],
];

if ($_GET['a'] == 'init') {
  $tableData = [];

  $subDomainSetting = getAllDomainSetting();

  $count = 0;
  foreach ($subDomainSetting as $v) {
    $subDomainListContent = '';

    $tableData[$count]['id'] = $v->id;
    $tableData[$count]['admain'] = $v->domainname;
    $tableData[$count]['admainStatus'] = $open[$v->open];

    $configData = json_decode($v->configdata);
    foreach ($configData as $k => $setting) {
      if ($v->open != 2) {
        $websiteName = (isset($setting->websiteName))? $setting->websiteName:$tr['not setting name yet'];
        $subDomainListContent .= <<<HTML
        <tr>
          <td class="text-center">{$websiteName}</td>
          <td class="text-center">{$setting->style->desktop->suburl}</td>
          <td class="text-center">{$setting->style->mobile->suburl}</td>
          <td class="text-center">{$open[$setting->open]}</td>
          <td class="text-center">
            <button type="button" class="btn btn-danger delSubadmain" title="{$tr['delete']}" value="{$v->id}_{$k}"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></button>
            <a href="./subdomain_management_detail.php?i={$v->id}&sid={$k}" title="{$tr['edit']}" class="btn btn-primary"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>
            <a href="./uisetting_view.php?i={$v->id}&sid={$k}" title="{$tr['front website management']}" class="btn btn-primary"><span class="fas fa-palette" aria-hidden="true"></span></a>
          </td>
        </tr>
HTML;
      }
    }

    $tableData[$count]['operate'] = '
    <a href="./subdomain_management_create.php?i='.$v->id.'" title="'.$tr['add'].'" class="btn btn-success"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span></a>
    <button type="button" class="btn btn-danger delDomain" title="'.$tr['delete'].'" value="'.$v->id.'"><span class="glyphicon glyphicon-minus" aria-hidden="true"></span></button>
    <a href="./uisetting_view.php?i='.$v->id.'" title="'.$tr['copywriting management'].'" class="btn btn-primary">'.$tr['copywriting management'].'</a>
    ';
    $tableData[$count]['subadmain'] = combineModalHtml($v->id, $subDomainListContent);

    $count++;
  }

  $data = [
    "sEcho" => intval($secho),
    "iTotalRecords" => intval($page_config['datatables_pagelength']),
    "iTotalDisplayRecords" => intval($count),
    "data" => $tableData
  ];

  echo json_encode($data);
}

function combineModalHtml($id, $colhtml)
{
  global $tr;
  $subDomainListTable = combineSubDomainTableHtml($colhtml);

  $html = <<<HTML
  <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#{$id}modal">{$tr['subdomain list']}</button>

  <div class="modal fade bd-example-modal-lg" id="{$id}modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">{$tr['subdomain list']}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          {$subDomainListTable}
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">{$tr['close the window']}</button>
        </div>

      </div>
    </div>
  </div>
HTML;

  return $html;
}

function combineSubDomainTableHtml($colhtml)
{
  global $tr;
  $html = <<<HTML
  <table class="table table-striped">
    <thead>
      <tr>
        <th scope="col" class="text-center" rowspan="2">{$tr['subdmoain name']}</th>
        <th scope="col" class="text-center" colspan="2">{$tr['subdomain']}</th>
        <th scope="col" class="text-center" rowspan="2">{$tr['subdomain status']}</th>
        <th scope="col" class="text-center" rowspan="2">{$tr['operation']}</th>
      </tr>
      <tr>
        <th scope="col" class="text-center">{$tr['desktop']}</th>
        <th scope="col" class="text-center">{$tr['mobile']}</th>
      </tr>
    </thead>
    <tbody>
      {$colhtml}
    </tbody>
  </table>
HTML;

  return $html;
}

function getAllSubDomainList($subDomainSetting)
{
  $count = 0;

  foreach ($subDomainSetting as $v) {

    $configData = json_decode($v->configdata);

    $data[$count]['id'] = $v->id;
    $data[$count]['admain'] = $v->domainname;

    $list = [];
    foreach ($configData as $subdomain => $setting) {
      $correspond_site = $setting->correspond_site;

      $setting['subdomain'] = $subdomain;
      $configData->$correspond_site['subdomain'] = $correspond_site;

      $list[] = [
        'desktop' => $setting,
        'mobile' => $configData->$correspond_site
      ];

      unset($configData->$correspond_site);
    }

    $data[$count]['subadmain'] = $list;

    $count++;
  }

  return $data;
}
