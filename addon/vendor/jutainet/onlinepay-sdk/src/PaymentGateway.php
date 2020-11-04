<?php
namespace Onlinepay\SDK;

use Exception;

class PaymentGateway
{
    /* errcode */
    const ERRCODE_SOMETHING_WRONG = 9999;
    const ERRCODE_API_EXCEPTION = 9996;

    const SYS_MODE_TEST = 'test';
    const SYS_MODE_PROD = 'release';

    const CANDIDATE_ENTRIES = [
        self::SYS_MODE_TEST => 'http://demo.shopeebuy.com/api',
        self::SYS_MODE_PROD => 'https://pay.shopeebuy.com/api',
    ];

    public $debug = false;
    private $defaultApiEntry = '';
    public $system_mode = self::SYS_MODE_TEST;
    private $apiToken = '';
    private $apiKey = '';
    private $apiPaths = [
        'service_detail' => '/provider/service',
        'service_list' => '/provider/service/list',
        'provider_list' => '/provider/list',
        'provider_detail' => '/provider',
        'deposit' => '/transaction/deposit',
        'tx_detail' => '/transaction',
        'tx_list' => '/transaction',
        'agent_info' => '/agent',
        'office_url' => '/agent/office_url',
        /* 交易統計 */
        'hour' => '/transaction/hourly_summary',
        'day' => '/transaction/daily_summary',
        'month' => '/transaction/monthly_summary',
        'year' => '/transaction/yearly_summary'
    ];
    public $lang = 'zh-cn';

    public function __construct(array $options = [])
    {
        $this->setApiEntry();

        array_walk($options, function ($val, $key) {
            if (\property_exists($this, $key)) {
                $this->$key = $val;
            }
        });
    }

    public function setApiEntry($api_entry = null)
    {
        $this->defaultApiEntry = $api_entry;
        if (!$this->defaultApiEntry) {
            $this->defaultApiEntry = self::CANDIDATE_ENTRIES[$this->system_mode] ?? '';
        }
    }

    /* 發起一筆存款交易 */
    public function postDeposit(array $data): object
    {
        if (!($data['provider'] ?? '')) {
            /* 補上支付服務提供者參數(provider) */
            $services = $this->getServiceList()->data;
            $service = array_filter($services, function ($service) use ($data) {
                return $service->codename == $data['payservice'];
            });
            $service = array_shift($service);

            if (count($service->available_payment_methods) < 1) {
                throw new \Exception('available payment method not exist.');
            }

            $method_idx = array_rand($service->available_payment_methods);
            $provider = $service->available_payment_methods[$method_idx]->payment;
            $data['provider'] = $provider;
        }

        $data = $this->appendSign($data);

        $url = $this->getApiUri('deposit', $data);

        $curl_options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
        ];
        $curl_result = json_decode($this->execCurl($curl_options)['body']);
        return $curl_result;
    }

    /* 查詢交易統計 */
    public function getTxSummary($mode = 'hour'): object
    {
        $data = $this->appendSign();
        !in_array($mode, ['hour', 'day', 'month', 'year']) and $mode = 'hour';
        $url = $this->getApiUri($mode, $data);
        $curl_options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ];
        $curl_result = json_decode($this->execCurl($curl_options)['body']);
        return $curl_result;
    }

    /* 查詢區間交易內容，預設是當日 */
    public function getTxList(array $data = []): object
    {
        $dateYmd = date('Y-m-d');
        $data += [
            'start_time' => "$dateYmd 00:00:00",
            'end_time' => "$dateYmd 23:59:59",
        ];

        $data = $this->appendSign($data);
        $url = $this->getApiUri('tx_list', $data);
        $curl_options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ];
        $curl_result = json_decode($this->execCurl($curl_options)['body']);
        return $curl_result;
    }

    /* 查詢一筆交易詳情 */
    public function getTxDetail(string $tx_id): object
    {
        $data = $this->appendSign();
        $url = $this->getApiUri('tx_detail', $data, "/$tx_id");
        $curl_options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ];
        $curl_result = json_decode($this->execCurl($curl_options)['body']);
        return $curl_result;
    }

    /* 取得支付服務列表 */
    public function getServiceList(): object
    {
        $data = $this->appendSign(['lang' => $this->lang]);
        $url = $this->getApiUri('service_list', $data);
        $curl_options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ];
        $curl_result = json_decode($this->execCurl($curl_options)['body']);
        return $curl_result;
    }

    /* 取得支付服務詳情 */
    public function getServiceDetail($service_name = ''): object
    {
        $data = $this->appendSign(['lang' => $this->lang]);
        $url = $this->getApiUri('service_detail', $data, "/$service_name");
        $curl_options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ];
        $curl_result = json_decode($this->execCurl($curl_options)['body']);
        return $curl_result;
    }

    /* 查詢代理資訊 */
    public function getAgentInfo(): object
    {
        $data = $this->appendSign();
        $url = $this->getApiUri('agent_info', $data);
        $curl_options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ];
        $curl_result = json_decode($this->execCurl($curl_options)['body']);
        return $curl_result;
    }

    public function getOfficeUrl(): object
    {
        $data = $this->appendSign();
        $url = $this->getApiUri('office_url', $data);
        $curl_options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ];
        $curl_result = json_decode($this->execCurl($curl_options)['body']);
        return $curl_result;
    }

    /**
     * 查詢金流商列表
     *
     * @param array $data :state[all|available(default)] :lang[i18n; default is tied to agent]
     * @return object $apiData
     */
    public function getProviderList(array $data = []): object
    {
        $data = $this->appendSign($data);
        $url = $this->getApiUri('provider_list', $data);
        $curl_options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ];
        $curl_result = json_decode($this->execCurl($curl_options)['body']);
        return $curl_result;
    }

    public function appendSign(array $params = []): array
    {
        $params['sign'] = $this->getSign($params);
        return $params;
    }

    public function getApiUri(string $pathkey, array $data = [], string $custom_path = ''): string
    {
        $uri = $this->defaultApiEntry;
        $uri .= $this->apiPaths[$pathkey] . $custom_path;
        $query = \http_build_query($data);
        $uri .= "?$query";
        return $uri;
    }

    private function getSign(array $params = []): string
    {
        $params = array_map(
            function ($value) {
                return is_null($value) ? '' : $value;
            },
            $params
        );
        unset($params['sign']);
        ksort($params);
        $sign = md5(urldecode(http_build_query($params)) . $this->apiKey);
        return $sign;
    }

    public function execCurl(array $options = [])
    {
        $options += [
            CURLOPT_URL => $this->defaultApiEntry,
            CURLOPT_HEADER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => [
                "Content-Type: multipart/form-data",
                "Authorization: {$this->apiToken}",
            ],
            CURLINFO_HEADER_OUT => true,
        ];

        if (!$options[CURLOPT_URL]) {
            throw new \RangeException('set the curl url first!');
        }

        $ch = \curl_init();
        \curl_setopt_array($ch, $options);

        $response = \curl_exec($ch);
        $errno = \curl_errno($ch);
        $err = \curl_error($ch);
        $httpcode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        $parsedBody = \json_decode($body);

        if ($errno or $httpcode != 200) {
            throw new \Exception('unexpected curl result! ' . curl_error($ch) . "\n" . $response);
        }

        $debug_fmt = "%s (%s): %s";
        $this->debug
        /* and error_log(sprintf($debug_fmt, __METHOD__, date('c'), json_encode($options))) */
        and error_log(sprintf($debug_fmt, __METHOD__, date('c'), $response))
        ;

        \curl_close($ch);

        return compact('httpcode', 'body');
    }

    public function checkApiResult($apiResult)
    {
        $exception = new Exception(json_encode($apiResult), self::ERRCODE_API_EXCEPTION);
        $api_status = $apiResult->status ?? null;
        if (!$apiResult or !isset($api_status)) {
            throw new Exception("Invalid JSON format", self::ERRCODE_SOMETHING_WRONG, $exception);
        }

        if ($apiResult->status->code !== 0) {
            throw new Exception($apiResult->status->message, $apiResult->status->message, $exception);
        }
        return true;
    }
}
