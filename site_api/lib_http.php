<?php
namespace Jutainet\Http;

use \ArrayObject;

class Controller {
  protected $container;

  public function __construct($container = null) {
    $container = new ArrayObject([], ArrayObject::ARRAY_AS_PROPS);
    $container->config = $GLOBALS['config'];
    $this->container = $container;
  }

  public function __get($property) {
    return $this->$property ?? $this->container->$property ?? null;
  }

  public static function dispatch(string $mod = '') {
    $request = Request::getInstance();
    $response = Response::getInstance();

    $action = filter_var($_GET['a'] ?? '', FILTER_SANITIZE_STRING);
    $class = $mod ? ucfirst($mod) . 'Controller' : 'BaseController';
    $method = strtolower($_SERVER['REQUEST_METHOD']) . str_replace('_', '', ucwords($action, '_'));

    if (class_exists($class)) {
      $controller = new $class;

      if (method_exists($controller, $method)) {
        $controller->$method($request, $response);
      } else {
        $controller->index($request, $response);
      }
    }
    return $response->respond();
  }

  public function index(Request $request, Response $response) {
    return $response->withJson(['msg' => "you see 'hello, world!'"], 200);
  }

  final protected function isCsrfValid() {
    $csrftoken_ret = csrf_action_check();
    if ($csrftoken_ret['code'] != 1) {
      die($csrftoken_ret['messages']);
    }
  }

  protected function getGlobalConfig() {
    return $GLOBALS['config'];
  }
}

class Request {
  private static $instance = null;
  private $query_params = [];
  private $parsed_body = [];

  private function __construct() {
    foreach ($_GET as $k => $v) {
      $this->query_params[$k] = (is_numeric($v)) ? (is_int($v)) ? intval($v) : floatval($v) : (is_string($v)) ? filter_var($v, FILTER_SANITIZE_STRING) : $v;
    }

    foreach ($_POST as $k => $v) {
      $this->parsed_body[$k] = (is_numeric($v)) ? (is_int($v)) ? intval($v) : floatval($v) : (is_string($v)) ? filter_var($v, FILTER_SANITIZE_STRING) : $v;
    }
  }

  public static function getInstance() {
    if (is_null(self::$instance)) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  public function getQueryParams() {
    return self::$instance->query_params;
  }

  public function getParsedBody() {
    return self::$instance->parsed_body;
  }

  public function getParam(string $key) {
    $params = $this->getQueryParams() + $this->getParsedBody();
    return $params[$key] ?? '';
  }
}

class Response {
  private $status_code = 200;
  private $headers = [];
  private $body = '';
  private static $instance;

  private function __construct() {
    ob_start();
  }

  public static function getInstance() {
    if (is_null(self::$instance)) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  public function withHeader($header, $value) {
    self::$instance->headers[$header] = [$value];
    return $this;
  }

  public function withStatus($status_code) {
    self::$instance->status_code = $status_code;
    return $this;
  }

  public function withRedirect($url, $status_code) {
    self::$instance->headers = [];
    $this->withHeader('Location', $url);
    $this->respond();
    exit;
  }

  public function respond() {
    if (!headers_sent()) {
      foreach ($this->getHeaders() as $name => $values) {
        $first = stripos($name, 'Set-Cookie') === 0 ? false : true;
        foreach ($values as $value) {
          header(sprintf('%s: %s', $name, $value), $first);
          $first = false;
        }
      }

      header(sprintf(
        'HTTP/%s %s',
        $this->getProtocolVersion(),
        $this->getStatusCode()
      ), true, $this->getStatusCode());
    }

    echo self::$instance->body;

    ob_flush();
    ob_end_clean();
    flush();
  }

  public function getHeaders() {
    return self::$instance->headers;
  }

  public function getStatusCode() {
    return self::$instance->status_code;
  }

  public function getBody() {
    return self::$instance->body;
  }

  public function withRaw(string $body) {
    self::$instance->body = $body;
    return $this;
  }

  public function withJson(?array $data, int $status_code = null, int $encode_options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) {
    if (!is_null($status_code)) {
      self::$instance->status_code = $status_code;
    }
    $this->withHeader('Content-Type', 'application/json');
    self::$instance->body = json_encode($data, $encode_options);
    return $this;
  }

  public function getProtocolVersion() {
    return '1.1';
  }
}
