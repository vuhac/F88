<?php
require_once __DIR__ . "/../vendor/autoload.php";

use PHPUnit\Framework\TestCase;
use Onlinepay\SDK\PaymentGateway;

class PaymentGatewateTest extends TestCase
{
    private static $apiConfig = [
        'agent01' => [
            'apiToken' => '038535ec7e3d39262beb1d1e00070d785a9ce7f475071da6945370d4496de36908d67390bbd40685b2f6fd5c25e07ecef5ef517aa9c8392790b6e59f76e59d30',
            'apiKey' => '823eabc9c17e5c2f29935ac208c820e6',
            'defaultApiEntry' => 'http://pay.test/api',
            'debug' => false,
        ],
        'agent01_developer' => [
            'apiToken' => '645e056ab315d45a0875860e43b50321e2e8a453a836eb23e831771ba4a0de7fdd223f8ced36233d59bd93b037fd78bce3e5b6aef06514f1af52e072129db7e0',
            'apiKey' => '05638f481550f98085f0f91e1edcad0c',
            'defaultApiEntry' => 'http://demo.shopeebuy.com/api',
            'debug' => true,
        ],
    ];

    private static $paymentGateway;

    public static function setUpBeforeClass(): void
    {
        self::$paymentGateway = new PaymentGateway(self::$apiConfig['agent01_developer']);
    }

    public static function tearDownAfterClass(): void
    {
        self::$paymentGateway = null;
    }

    public function testGetServiceList()
    {
        $result = self::$paymentGateway->getServiceList();
        $debug_msg = sprintf("\033[31;44m %s \033[0m", $result->status->message);
        $this->assertEquals($result->status->code, '0', $debug_msg);
        $debug_msg = sprintf("\033[31;44m %s \033[0m", 'data is not array.');
        $this->assertIsIterable($result->data, $debug_msg);
    }

    public function testGetServiceDetail()
    {

        $list = self::$paymentGateway->getServiceList()->data;

        while ($service = array_pop($list)) {
            $service_name = $service->codename;
            $result = self::$paymentGateway->getServiceDetail($service_name);
            $debug_msg = sprintf("\033[31;44m %s \033[0m", $result->status->message);
            $this->assertEquals($result->status->code, '0', $debug_msg);
            $debug_msg = sprintf("\033[31;44m %s \033[0m", 'data is not object.');
            $this->assertIsObject($result->data, $debug_msg);
        }
    }

    /**
     * @dataProvider depositProvider
     */
    public function testPostDeposit($data)
    {
        $result = self::$paymentGateway->postDeposit($data);
        $debug_msg = sprintf("\033[31;44m %s \033[0m", $result->status->message);
        $this->assertEquals($result->status->code, '0', $debug_msg);
        $debug_msg = sprintf("\033[31;44m %s \033[0m", 'pay_url not exists.');
        $this->assertObjectHasAttribute('pay_url', $result->data, $debug_msg);
    }

    public function depositProvider()
    {
        $case = [];
        // 可測試各種方式、各種金額的訂單
        $case[] = [
            'pingpp' => [
                'payservice' => 'wechat_wappay',
                'account' => 'kt120000001876',
                'amount' => '125',
                'name' => 'kt1webbdemo',
                'sign' => '{{sign}}',
                'device' => 'desktop',
                'provider' => 'pingpp',
                'currency' => 'CNY',
                'return_url' => 'https://jutainetwebb.jutainet.com/gpk2dev/mo_translog.php?param1=123&param2=456',
                'server_url' => 'https://www.google.com/?param1=123&param2=456',
                'email' => ''
            ],
        ];
        $case[] = [
            'linepay' => [
                'payservice' => 'accountpay',
                'account' => 'kt120000001876',
                'amount' => '10',
                'name' => 'kt1webbdemo',
                'sign' => '{{sign}}',
                'device' => 'desktop',
                'provider' => 'linepay',
                'currency' => 'TWD',
                'return_url' => '{{return_url}}',
                'server_url' => '{{server_url}}',
                'email' => ''
            ],
        ];
        $case[] = [
            'RAND_PROVIDER' => [
                'payservice' => 'wechat_wappay',
                'account' => 'kt120000001876',
                'amount' => '125',
                'name' => 'kt1webbdemo',
                'sign' => '{{sign}}',
                'device' => 'desktop',
                // 'provider' => '',
                'currency' => 'CNY',
                'return_url' => '{{return_url}}',
                'server_url' => '{{server_url}}',
                'email' => ''
            ],
        ];

        return [$case[0]];
    }

    public function testGetTxList()
    {
        $result = self::$paymentGateway->getTxList();
        $debug_msg = sprintf("\033[31;44m %s \033[0m", $result->status->message);
        $this->assertEquals($result->status->code, '0', $debug_msg);
        $debug_msg = sprintf("\033[31;44m %s \033[0m", 'data is not array.');
        $this->assertIsIterable($result->data, $debug_msg);
    }

    public function testGetTxDetail()
    {
        $tx_id = 'test12321_88d4a169-130e-44a6-b423-e21a29ff0290';
        $result = self::$paymentGateway->getTxDetail($tx_id);
        $debug_msg = sprintf("\033[31;44m %s \033[0m", $result->status->message);

        $this->assertContains($result->status->code, [0, 3]);
        return;

        // 有查到訂單
        $this->assertEquals($result->status->code, '0', $debug_msg);
        $debug_msg = sprintf("\033[31;44m %s \033[0m", 'data is not object.');
        $this->assertIsObject($result->data, $debug_msg);
        $this->assertObjectHasAttribute('order_no', $result->data);
        $this->assertEquals($result->data->order_no, 'test12321_88d4a169-130e-44a6-b423-e21a29ff0290');
    }

    public function testGetAgentInfo()
    {
        $result = self::$paymentGateway->getAgentInfo();
        $debug_msg = sprintf("\033[31;44m %s \033[0m", $result->status->message);
        $this->assertEquals($result->status->code, '0', $debug_msg);
        $this->assertObjectHasAttribute('account', $result->data);
        $this->assertObjectHasAttribute('parent_account', $result->data);
        $this->assertObjectHasAttribute('agent_code', $result->data);
        // $this->assertObjectHasAttribute('balance', $result->data);
        // $this->assertObjectHasAttribute('status', $result->data);
        $this->assertObjectHasAttribute('created_at', $result->data);
        $this->assertObjectHasAttribute('expired_at', $result->data);
        $this->assertObjectHasAttribute('single_deposit_limits', $result->data);
        $this->assertObjectHasAttribute('deposit_limits', $result->data);
        $this->assertObjectHasAttribute('deposit_alerts', $result->data);
        $this->assertObjectHasAttribute('currency_info', $result->data);
    }

    public function testGetOfficeUrl()
    {
        $result = self::$paymentGateway->getOfficeUrl();
        $debug_msg = sprintf("\033[31;44m %s \033[0m", $result->status->message);
        $this->assertEquals($result->status->code, '0', $debug_msg);
        $this->assertObjectHasAttribute('data', $result);
        $this->assertIsString($result->data);
    }

    public function testGetProviderList()
    {
        $result = self::$paymentGateway->getProviderList();
        $debug_msg = sprintf("\033[31;44m %s \033[0m", $result->status->message);
        $this->assertEquals($result->status->code, '0', $debug_msg);
        $debug_msg = sprintf("\033[31;44m %s \033[0m", 'data is not array.');
        $this->assertIsIterable($result->data, $debug_msg);

        if (count($result->data) > 0) {
            $provider = array_pop($result->data);
            $this->assertObjectHasAttribute('title', $provider);
            $this->assertObjectHasAttribute('currency', $provider);
            $this->assertObjectHasAttribute('codename', $provider);
            $this->assertObjectHasAttribute('hint', $provider);
            $this->assertObjectHasAttribute('has_setting', $provider);
        }
    }
}
