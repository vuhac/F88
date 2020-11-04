<?php
// ----------------------------------------------------------------------------
// Features :	後台 -- view render utils
// File Name: lib_view.php
// Author   : Dright
// Related  :
// Log      :
// ----------------------------------------------------------------------------
// 功能說明
// utility functions to render page
//
// functions to render:
//  1. render($view, $render_data)
//
// functions use in views:
//  1. use_layout($layout)
//  2. begin_section($section_name)
//  3. end_section()
//  4. include_partial($partial, $partial_data)
//
// example:
// agent_profitloss_calculation_detail.php
// agent_profitloss_calculation_detail.view.php
//

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";


/**
 * sections in template
 * @var array
 */
$tmpl = [
  'html_meta_description' => $tr['host_descript'],
  'html_meta_author'	 		=> $tr['host_author'],
  'html_meta_title' 			=> '',
  'extend_head'				    => '',
  'extend_js'					    => '',
  'page_title' 					  => '',
  'paneltitle_content'    => '',
  'panelbody_content'     => '',
];

/**
 * use by helper functions
 * @var string
 */
$lib_view_cur_layout = '';

/**
 * use by helper functions
 * @var string
 */
$lib_view_cur_section_name = '';



/**
 *  helper functions used in view
 *
 * example: agencyarea_summary_preferential_detail.view.php
 */

// set layout. ex: admin.tmpl.php
function use_layout($layout) {
  $GLOBALS['lib_view_cur_layout'] = $layout;
}

// begin of section
function begin_section($section_name) {
  $GLOBALS['lib_view_cur_section_name'] = $section_name;
  ob_start();
}

// end of section
function end_section() {
  global $tmpl;
  global $lib_view_cur_section_name;

  if(empty($lib_view_cur_section_name)) return;

  $tmpl[$lib_view_cur_section_name] = ob_get_clean();
  $GLOBALS['lib_view_cur_section_name'] = '';
}

// include partial view
function include_partial($partial, $partial_data = []) {
  global $config;
  global $tr;

  extract($partial_data);

  ob_start();
  include $partial;
  ob_end_flush();
}

/**
 *  end of helper functions
 */



/**
 * render by view
 * @param  [string] $view      [file name of view]
 * @param  array  $render_data [data passing to view]
 * @param  string  $lighten_mode html|javascript|all
 * @return view                [render result]
 */
function render($view, $render_data = [], string $lighten_mode = '') {
  global $config;
  global $tr;
  global $tmpl;
  global $lib_view_cur_layout;

  extract($render_data);

  ob_start();
  include $view;

  if(! empty($lib_view_cur_layout)) {

    ob_clean();
    include $lib_view_cur_layout;
    $lib_view_cur_layout = '';
  }

  $output = ob_get_contents();
  ob_clean();

  !empty($lighten_mode) and strip($lighten_mode, $output);
  echo $output;
}

/**
 * strip string with regex
 *
 * @param string $mode
 * @param string $output
 * @return void
 */
function strip(string $mode, string &$output)
{
  $patterns = [
    'html' => '/<!--[^\[](.*?)-->/u',
    'javascript' => '/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\')\/\/.*))/u'
  ];
  $pattern = $patterns[$mode] ?? '';
  switch ($mode) {
    case 'html':
      $output = preg_replace($pattern, '', $output);
      break;
    case 'javascript':
      $output = preg_replace($pattern, '', $output);
      break;
    case 'all':
      foreach ($patterns as $mode => $pattern) {
        $output = preg_replace($pattern, '', $output);
      }
    break;
  }
}

?>
