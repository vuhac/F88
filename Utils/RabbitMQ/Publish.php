<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require_once __DIR__ . '/../../config.php';

/*
使用方法

$mq = Publish::getInstance();
$mq->fanoutAdd('my_exchange', $data);
$mq->directAdd('my_queue', 'my_exchange', $data);
*/

class Publish
{
  private static $instance = null;
  private $connection;
  private $channel;
  private $message;

  private function __construct()
  {
      global $config;

      $this->connection = new AMQPStreamConnection(
          $config['rebbit_host'],
          $config['rebbit_port'],
          $config['rebbit_user'],
          $config['rebbit_password'],
          $config['rebbit_vhost']
      );

      $this->channel = $this->connection->channel();
  }

  public function __destruct()
  {
    $this->channel->close();
    $this->connection->close();

    self::$instance = null;
  }

  public static function getInstance()
  {
      if (!self::$instance instanceof self) {
          self::$instance = new self();
      }

      return self::$instance;
  }

  /**
   * fanout notify
   *
   * @param string $exchange
   * @param array $data
   * @return array
   */
  public function fanoutNotify($exchange, $data)
  {
    try {
      $this->channel->exchange_declare($exchange, 'fanout', false, true, false);

      list($queueName, , ) = $this->channel->queue_declare("", false, true, true, true);
      $this->channel->queue_bind($queueName, $exchange);

      // $this->message->setBody(json_encode($data));
      $this->message = new AMQPMessage(json_encode($data));
      $this->channel->basic_publish($this->message, $exchange);
    } catch (Exception $e) {
      return ['status' => false, 'msg' => $e->getMessage()];
    }

    return ['status' => true, 'msg' => 'success'];
  }

  /**
   * direct notify
   *
   * @param string $queueName
   * @param string $exchange
   * @param array $data
   * @return void
   */
  public function directNotify($queueName, $exchange, $data)
  {
    try {
      $this->channel->exchange_declare($exchange, 'direct', false, true, false);

      $this->channel->queue_declare($queueName, false, true, false, false);
      $this->channel->queue_bind($queueName, $exchange, $queueName);

      $this->message = new AMQPMessage(json_encode($data));
      $this->channel->basic_publish($this->message, $exchange, $queueName);
    } catch (Exception $e) {
      return ['status' => false, 'msg' => $e->getMessage()];
    }

    return ['status' => true, 'msg' => 'success'];
  }

  private function closeMQ()
  {
    $this->channel->close();
    $this->connection->close();

    self::$instance = null;
  }

  private function getMQConfig()
  {
    return config('mq');
  }
}
