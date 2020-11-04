<?php
// ----------------------------------------------------------------------------
// Features: 子網域管理lib
// File Name:	lib_subdomain_management.php
// Author: Neil
// Related:   
// Log:
// ----------------------------------------------------------------------------

// function getAllStyleSettingName()
// {
//   $sql = <<<SQL
//   SELECT id, name 
//   FROM site_stylesetting
//   ORDER BY id;
// SQL;

//   $result = runSQLall($sql);

//   if (empty($result[0])) {
//     return false;
//   }

//   unset($result[0]);

//   return $result;
// }

function getDomainSetting($id)
{
  $sql = <<<SQL
  SELECT * 
  FROM site_subdomain_setting
  WHERE id = '{$id}'
  AND open != '2'
  ORDER BY id;
SQL;

  $result = runSQLall($sql);

  return (empty($result[0])) ? false : $result[1];
}

function combineInsertSql($input, $configData)
{
  $sql = <<<SQL
  INSERT INTO site_subdomain_setting (
    domainname, configdata, note, open
  ) VALUES (
    '{$input['admainUrl']}', '{$configData}', '{$input['note']}', '{$input['admainStatus']}'
  );
SQL;

  return $sql;
}

function getAllDomainSetting()
{
  $sql = <<<SQL
  SELECT * 
  FROM site_subdomain_setting
  WHERE open != '2'
  ORDER BY id;
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  unset($result[0]);

  return $result;
}

function combineUpdateSql($id, $input, $configData)
{
  $sql = <<<SQL
  UPDATE site_subdomain_setting
  SET domainname = '{$input['admainUrl']}',
      configdata = '{$configData}',
      note = '{$input['note']}',
      open = '{$input['admainStatus']}'
  WHERE id = '{$id}';
SQL;

  return $sql;
}

function combineUpdateStatusSql($id, $configData = '')
{
  if ($configData == '') {
    $sql = <<<SQL
    UPDATE site_subdomain_setting
    SET open = '2'
    WHERE id = '{$id}';
SQL;
  } else {
    $sql = <<<SQL
    UPDATE site_subdomain_setting
    SET configdata = '{$configData}'
    WHERE id = '{$id}';
SQL;
  }

  return $sql;
}

function combineSubdomaimJson($input)
{
  $configData = [
    'open' => $input['subadmainStatus'],
    'style' => [
      'mobile' => [
        'suburl' => $input['mobileSubadmainName'],
        'site_type' => 'mobile',
        'themepath' => $input['mobileThemePath']
      ],
      'desktop' => [
        'suburl' => $input['desktopSubadmainName'],
        'site_type' => 'desktop',
        'themepath' => $input['desktopThemePath']
      ]
    ],
    'websiteName' => $input['websiteName'],
    'footer' => $input['websiteFooter'],
    'hostname' => $input['hostName'],
    'website_type' => $input['webType'],
    'default_agent' => $input['agent'],
    'google_analytics_id' => $input['googleID'],
    'companyName' => $input['companyName'],
    'companyShortName' => $input['companyShortName'],
    'component' => $input['component'],
    'companylogo' => $input['upload_logo_img'],
    'companyFavicon' => $input['upload_favicon_img']
  ];

  return $configData;
}
function themeList($device,$themepath=null){
  global $tr;
  $themelist_html='';
  $themelist['desktop']=[
    'gp01'=>"顶级商城",
    'gp02'=>"缤纷耀黑",
    'gp03'=>"碧蓝战神",
    'gp04'=>"奢华赌城",
    'gp05'=>"竞技擂台",
    'gp06'=>"荣耀之王",
    'gp07'=>"傲视云间",
    'gp08'=>"极简鹤红",
    'gp09'=>"极简墨绿",
    'gp10'=>"英雄独霸",
    'gp11'=>"低调奢华",
    'gp12'=>"闪耀萤绿",
    'gp13'=>"纸醉金迷",
  ];
  $themelist['mobile']=[
    'gp01m'=>"顶级商城",
    'gp02m'=>"缤纷耀黑",
    'gp03m'=>"碧蓝战神",
    'gp04m'=>"奢华赌城",
    'gp05m'=>"竞技擂台",
    'gp06m'=>"荣耀之王",
    'gp07m'=>"傲视云间",
    'gp08m'=>"极简鹤红",
    'gp09m'=>"极简墨绿",
    'gp10m'=>"英雄独霸",
    'gp11m'=>"低调奢华",
    'gp12m'=>"闪耀萤绿",
    'gp13m'=>"纸醉金迷", 
  ];
  //不存在列表之樣版
  if($themepath !=null && !array_key_exists($themepath, $themelist[$device])){
    $alert=<<<HTML
    <div class="alert alert-warning">
      {$tr['the website is applying customized templates, please contact customer service']}
    </div>
HTML;
    return ["status"=> "custom" ,"html"=>$alert];
  }
  foreach ($themelist[$device] as $key => $value) {
    if($themepath==$key)
      $themelist_html.='<option value="'.$key.'" selected>'.$value.'</option>';
    else
      $themelist_html.='<option value="'.$key.'">'.$value.'</option>';
  }  
  $themelist_html=<<<HTML
  <select class="form-control" id="{$device}ThemePath">
    {$themelist_html}
  </select>
HTML;
  return ["status"=> "default" ,"html"=>$themelist_html];
}