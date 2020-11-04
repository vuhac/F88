<?php
// ----------------------------------------------------------------------------
// Features :	後台 -- message LIB
// File Name: lib_message.php
// Author   : Dright
// Log      :
// ----------------------------------------------------------------------------
// 功能說明
// classes:
//

require_once __DIR__ . '/config.php';

$available_channel_group = [
  'error',
  'warning',
];


function mqtt_send($channel, $data) {
  global $config;

  if(!class_exists('Mosquitto\Client')) return;

  $client = new Mosquitto\Client();

  if(!empty($config['mqtt_username']) && !empty($config['mqtt_password'])) {
    $client->setCredentials($config['mqtt_username'], $config['mqtt_password']);
  }

  try {
    $client->connect($config['mqtt_host'], $config['mqtt_port'], 5);

  } catch(Exception $e) {
    var_dump($e);

    return;
  }
  $client->loop();

  if($data instanceof Message) {
    $message = $data->toJson();
  } else {
    $message = $data;
  }

  $msg_id = $client->publish($channel, $message, 1, 0);
  $client->loop();

  $client->disconnect();
  unset($client);
}

function get_message_reciever_url($plateform = 'backstage', $subscribe_channel) {
  global $config;

  $prefix = get_channel_prefix($plateform);
  $channel = $prefix . '/' . get_hashed_channel($subscribe_channel) . '/~';
  if(empty($subscribe_channel)) {
    $channel = $prefix . '/~';
  }

  return $config['mqtt_message_reciever_host'] . '/?channel=' . $channel;
}

function get_hashed_channel($channel) {
  global $config;

  if($config['mqtt_channel_hash'] ?? false) {
    return md5($channel . '-' . date('Y-m-d') . $config['mqtt_channel_hash_salt']);
  }

  return $channel;
}

function get_channel_prefix($plateform = 'front') {
  global $config;
  return get_hashed_channel($config['projectid'] . '-' . $plateform);
}

function get_message_channel($plateform = 'backstage', $channel) {
  $prefix = get_channel_prefix($plateform);
  return $prefix . '/' . get_hashed_channel($channel);
}


class Message
{
  public $title;
  public $message;
  public $url;
  public $delay;
  public $type;

  function __construct()
  {
    $this->title = '';
    $this->message = '';
    $this->url = '';
    $this->delay = 0;
    $this->type = 'info';
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setMessage($message) {
    $this->message = $message;
    return $this;
  }

  public function setUrl($url) {
    $this->url = $url;
    return $this;
  }

  public function setDelay($delay) {
    $this->delay = $delay;
    return $this;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function toJson() {
    return json_encode($this);
  }
}

?>
