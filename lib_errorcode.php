<?php
class ErrorCode {
  const SOMETHING_WRONG = 9999;
  const DB_EXCEPTION = 9998;
  const CURL_EXCEPTION = 9997;
  const API_EXCEPTION = 9996;

  const SUCCESS = 0;
  const AUTH_INVALID = 1;
  const SIGN_INVALID = 2;
  const BAD_PARAM = 3;
  const NOT_FOUND = 4;
  const TIMEOUT = 5;
  const IP_BLOCKED = 6;
  const UNAVAILABLE = 7;

  public static $errorMessages = [
    self::SOMETHING_WRONG => 'Something wrong.',
    self::DB_EXCEPTION => 'DB Exception.',

    self::SUCCESS => 'success',
    self::AUTH_INVALID => 'Authorization invalid.',
    self::SIGN_INVALID => 'Sign invalid.',
    self::BAD_PARAM => 'Bad parameters.',
    self::NOT_FOUND => 'Service not found.',
    self::TIMEOUT => 'Service timeout.',
    self::IP_BLOCKED => 'IP not allowed to access service.',
    self::UNAVAILABLE => 'Service not available',
  ];

  public static function formatData($api_code, $data, $custom_msg = '') {
    $message = self::getErrorMessage($api_code);
    !empty($custom_msg) and $message .= " $custom_msg";
    $format_data = [
      'data' => $data,
      'status' => ['code' => $api_code, 'message' => $message, 'timestamp' => time()],
    ];
    return $format_data;
  }

  public static function getErrorMessage($error_code) {
    return self::$errorMessages[$error_code] ?? self::$errorMessages[self::SOMETHING_WRONG];
  }
}
