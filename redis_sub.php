<?php
/**
 * 用來監聽redis push來的設息，並強制更新memcache內資料以即時更新相關設定
 * 需於前後台加cron job處理
 */
ini_set('default_socket_timeout', -1);
// 主機及資料庫設定
$_SERVER['HTTP_HOST']=gethostname();
$_SERVER['REMOTE_ADDR']='';
$_SERVER['SERVER_PORT']='';
$_SERVER['DOCUMENT_URI']='';
$config['site_style']='';

require_once dirname(__FILE__) ."/config.php";

$redis = new Redis();
// 第一个参数为redis服务器的ip,第二个为端口
try{
  #$redis->setOption(Redis::OPT_READ_TIMEOUT, -1);
  $redis->pconnect($redisdb['host'], 6379, 1);
  $redis->auth($redisdb['auth']);
  $redis->subscribe(array('memcache'), 'system_config_callback');
}catch(RedisException $e){
  echo gmdate('Y-m-d H:i:s',time())."\n";
  echo $e->getMessage()."\n";
}
