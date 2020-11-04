# README

## Install

+ install through composer

 Add repositories info into your composer.json,
 and use termianl: `composer require jutainet/onlinepay-sdk`

```json
    "repositories": [
        {
            "type": "vcs",
            "url": "git@gitlabproject.jutainet.com:JutainetWebb/onlinepay-sdk.git"
        }
    ]
```

+ install without composer

Copy `src/PaymentGateway.php` to your project.

> requrie file before using

```php
<?php
require_once __DIR__ . '/path/to/PaymentGateway.php';
# your code here
```

## How to use


```php
<?php
// example.php
$config = [
    'apiToken' => '3b82d6ab64777db035058d5923b9ff409e2075fe86becb0b62c18a9b147c6042eab621c9f417f13f92f7dcf82844c8db9bfbc76833c222dad14dc82eda268788',
    'apiKey' => '708dfbb8c5a2c5dc1880f7a97c628ee2',
    'defaultApiEntry' => 'http://pay.test/api',
];

$onlinepayGateway = new Onlinepay\SDK\PaymentGateway($config);

// 取得存款連結並跳轉
$keys = ['payservice', 'account', 'amount', 'name'];
$values = ['accountpay', 'kt120000001876', '500.50', 'kt1webbdemo'];
$data = array_combine($keys, $values);
$result = $onlinepayGateway->postDeposit($data);
header('Location: ' . $result->data->pay_url);
exit();
```
