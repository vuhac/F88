<?php
// ----------------------------------------------------------------------------
// Features:  建立測試訂單
// File Name: deposit_action.php
// Author:    Damocels
// Related:   site_api_config.php
// Info:
//    後台以 config.php 的指定帳號來建立測試訂單
//    並不會實際入帳到該帳號的錢包內
// ----------------------------------------------------------------------------
@session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";

require_once __DIR__ . '/site_api/lib_api.php';

// 線上金流 pay api
use Onlinepay\SDK\PaymentGateway;

// 可補上安全驗證與參數過濾
if ( !isset($_SESSION['agent']) )
{
    die('未登录');
}

// main
Controller::dispatch('deposit', $config);

// lib
class DepositController extends Controller
{
    private $config;

    function __construct( $config )
    {
        $this->config = $config;
    }

    public function postGetPayLink(Request $request, Response $response)
    {
        global $stationmail;
        global $config;
        // 預設帳號的電話、Email、信用卡資訊(暫時留白，因為資料表內沒有該欄位)
        $phone = '';
        $email = '';
        $credit_card_no = '';

        // 使用測試帳號建立測試訂單
        // 判斷是否建立測試訂單，用於判斷支付方式是否正常運作 ($config['generate_test_order']必須前後台的config.php內都有)
        $this->isCsrfValid();
        // !empty(array_diff_key($this->config['generate_test_order'], ['account' => '', 'nickname' => ''])) and die('尚未設定建立訂單的測試帳號！');
        // isset($this->config['generate_test_order']) and list('account' => $account, 'nickname' => $name) = $this->config['generate_test_order'];

        if ( empty($stationmail['sendto_system_cs']) ) {
            die('尚未設定建立訂單的測試帳號！');
        } else {
            list('account' => $account, 'nickname' => $name) = ['account' => $stationmail['sendto_system_cs'], 'nickname' => $stationmail['sendto_system_cs']];
        }

        $onlinepayGateway = new Onlinepay\SDK\PaymentGateway($this->config['gpk2_pay']);
        $agent_info = $onlinepayGateway->getAgentInfo(); // 代理資訊
        $currency_info = $agent_info->data->currency_info->codename; // 代理資訊當前使用幣別，用來帶出支付方式的最小交易金額
        $payservice = ( is_null($request->getParam('payservice')) || empty($request->getParam('payservice')) ? '' : $request->getParam('payservice') ); // 付款方式
        $provider = ( is_null($request->getParam('provider')) || empty($request->getParam('provider')) ? '' : $request->getParam('provider') ); // 金流商
        ${'options[bank_swift_code]'} = $request->getParam('bank');

        /**
         * https://proj.jutainet.com/issues/4304
         *
         * 允許設定金額是後來添加，原先是以固定金額(滿足最小額)的測試訂單實作
         * TODO 可用支付商添加限額範圍資訊，用以會員等級限額設定
         */
        $amount = $request->getParam('amount');
        /* $amount = 100; // 預設值100
        // 以當前"支付方式"與"使用幣別"取得最小交易金額
        // 儲值金額，棄用前台傳來的交易金額
        $service_list = $onlinepayGateway->getServiceList(); // 支付方式列表
        foreach ($service_list->data as $key=>$val) { // 遍歷支付方式列表，找到目前使用的支付方式
            if ($val->codename == $payservice) {
                // 判斷當前使用幣別是否有設定最小交易金額
                if ( isset($val->allowed_amount->$currency_info->min) && !is_null($val->allowed_amount->$currency_info->min) && !empty($val->allowed_amount->$currency_info->min) ) {
                    $amount = $val->allowed_amount->$currency_info->min;
                }
                break;
            }
        } */

        // todo: 這參數金流之後會用到 desktop/mobile
        $device = 'desktop'; // 瀏覽裝置

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 || getenv('HTTP_X_FORWARDED_PROTO') === 'https') ? "https://" : "http://";

        $website_domainname = str_replace($_SERVER['DOCUMENT_ROOT'], $_SERVER['HTTP_HOST'], __DIR__);

        // 初始化參數
        if( $_SERVER['HTTP_HOST'] == 'damocles.jutainet.com' ){
            $return_url = $protocol.$_SERVER['HTTP_HOST'].'/begpk2dev/deposit_return_msg.php';
            $server_url = $protocol.$_SERVER['HTTP_HOST'].'/begpk2dev/site_api/gateway.php';
        }
        else{
            $return_url = $protocol.$_SERVER['HTTP_HOST'].'/deposit_return_msg.php';
            $server_url = $protocol.$_SERVER['HTTP_HOST'].'/site_api/gateway.php';
        }

        $data = compact(
            'account',
            'payservice',
            'amount',
            'name',
            'device',
            'return_url',
            'provider',
            'server_url',
            'phone',
            'email',
            'credit_card_no',
            'options[bank_swift_code]'
        );

        $protalsetting = runSQLall_prepared(<<<SQL
            SELECT *
            FROM "root_protalsetting"
            WHERE ("setttingname" = :settingname) AND
                  ("status" = 1)
        SQL, [':settingname' => 'default']);

        array_walk($protalsetting, function (&$v, $k) use (&$protalsetting) {
            $protalsetting[$v->name] = $v;
            if (is_numeric($k)) {
                unset($protalsetting[$k]);
            }
        },);

        try {
            // 執行建立訂單
            $apiResponse = $onlinepayGateway->postDeposit($data);
            $apiStatus = $apiResponse->status;
            $apiData = $apiResponse->data;

            // 判斷執行後的Response Status
            if( isset($apiStatus->code) && isset($apiStatus->message) ){
                if( $apiStatus->code != 0 ){
                    return $response->withRaw(<<<HTML
                        <script>
                            alert("订单请求失败({$apiStatus->code}:{$apiStatus->message})，如果错误持续发生，请联系客服为您服务");
                            history.go(-1);
                        </script>
                    HTML);
                }
            }
            // 沒有接收到訂單狀態代號 & 訊息
            else{
                return $response->withRaw(<<<HTML
                    <script>
                        alert("订单请求失败，如果错误持续发生，请联系客服为您服务");
                        history.go(-1);
                    </script>
                HTML);
            }

            // 判斷建立訂單後，是否有回傳所需資訊，沒有的話直接跳錯誤訊息
            if( !isset($apiData->order_no) || !isset($apiData->account) || !isset($apiData->amount) || !isset($apiData->description) ){
                return $response->withRaw(<<<HTML
                    <script>
                        alert("订单请求失败，如果错误持续发生，请联系客服为您服务");
                        history.go(-1);
                    </script>
                HTML);
            }

            // 取出會員等級設定
            // $grade_sql = <<<SQL
            //     SELECT * FROM root_member_grade WHERE status = 1 AND id = (SELECT grade FROM root_member WHERE account = :account);
            // SQL;
            // $member_grade_config_detail = runSQLall_prepared($grade_sql, ['account' => $apiData->account])[0];
            // $fee = round($data['amount'] * $member_grade_config_detail->pointcardfee_member_rate / 100, 2);
            $fee = 0;

            // 紀錄訂單資訊
            $apiDepositOrder = new ApiDepositOrder;
            $apiDepositOrder->custom_transaction_id = $apiData->order_no;
            $apiDepositOrder->account = $apiData->account;
            $apiDepositOrder->amount = $apiData->amount;
            $apiDepositOrder->currency_type = $protalsetting['member_deposit_currency']->value;
            $apiDepositOrder->request_time = $apiDepositOrder->requestTime();
            $apiDepositOrder->transaction_time = $apiDepositOrder->requestTime();
            $apiDepositOrder->transactioninfo_json = $apiData;
            $apiDepositOrder->title = $apiData->description;
            $apiDepositOrder->status = 0;

            // 取得ip
            $apiDepositOrder->agent_ip = '127.0.0.1'; // 預設值
            $server_ip_attr = ['REMOTE_ADDR', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR'];
            array_walk($server_ip_attr, function($val, $key) use ($apiDepositOrder) {
                if( isset($_SERVER[$val]) && !empty($_SERVER[$val]) ){
                    $apiDepositOrder->agent_ip = $_SERVER[$val];
                }
            }); // end array_walk

            $apiDepositOrder->script_name = filter_var($_SERVER['SCRIPT_NAME'], FILTER_SANITIZE_MAGIC_QUOTES) ?? '';
            $apiDepositOrder->site_account_name = $this->config['gpk2_pay']['apiKey'];
            $apiDepositOrder->api_transaction_fee = $fee;
            $apiDepositOrder->add();
        }
        catch (\Exception $e) {
            return $response->withRaw(<<<HTML
                <script>
                    console.log('{$e->getMessage()}');
                    alert("订单请求失败，如果错误持续发生，请联系客服为您服务");
                    history.go(-1);
                </script>
            HTML);
        }

        $response->withJson([
        'status' => [
            'code' => 200,
            'message' => '订单链接取得成功',
            'timestamp' => time()
        ],
        'data' => $apiData
        ]);

        return $response->withHeader('Location', $apiData->pay_url);
    }

    // 回傳可用支付方式
    public function getGetServices(Request $request, Response $response)
    {
        $onlinepayGateway = new Onlinepay\SDK\PaymentGateway($this->config['gpk2_pay']);
        $apiResult = $onlinepayGateway->getServiceList();
        $payservices = !$apiResult ? [] : $onlinepayGateway->getServiceList()->data;
        return $response->withJson($payservices);
    }
}


class Controller {
    protected $container;

    public function __construct($container = null) {
        $container = new ArrayObject([], ArrayObject::ARRAY_AS_PROPS);
        $container->config = $GLOBALS['config'];
        $this->container = $container;
    }

    public function __get($property){
        return $this->$property ?? $this->container->$property ?? null;
    }

    // 實例化 DepositContoller->postGetPayLink
    public static function dispatch(string $mod = '', $config){
        $request = Request::getInstance(); // var_dump($request); exit();
        $response = Response::getInstance(); // var_dump($response); exit();

        $action = filter_var($_GET['a'] ?? '', FILTER_SANITIZE_STRING); // get_pay_link
        $class = $mod ? ucfirst(strtolower($mod)) . 'Controller' : 'BaseController'; // ucfirst把第一個字變成大寫 // var_dump($class); exit(); // DepositContoller
        $method = strtolower($_SERVER['REQUEST_METHOD']) . str_replace('_', '', ucwords($action, '_')); // var_dump($method); exit(); // postGetPayLink

        // var_dump( class_exists($class) ); exit(); // true
        if ( class_exists($class) ) {
            if( $class == 'DepositController' ){
                $controller = new $class($config);
            }
            else{
                $controller = new $class;
            }

            if( method_exists($controller, $method) ){
                $controller->$method($request, $response);
            }
            else{
                $controller->index($request, $response);
            }
        }
        return $response->respond();
    } // end dispatch

    public function index(Request $request, Response $response) {
        return $response->withJson(['msg' => "you see 'hello, world!'"], 200);
    } // end index

    final protected function isCsrfValid() {
        $csrftoken_ret = csrf_action_check();
        if ($csrftoken_ret['code'] != 1) {
            die($csrftoken_ret['messages']);
        }
    } // end isCsrfValid

    protected function getGlobalConfig() {
        return $GLOBALS['config'];
    }
} // end class Controller

class Request {
    private static $instance = null;
    private $query_params = [];
    private $parsed_body = [];

    private function __construct() {
        foreach ($_GET as $k => $v) {
            $this->query_params[$k] = (is_numeric($v)) ? (is_int($v)) ? intval($v) : floatval($v) : (is_string($v)) ? filter_var($v, FILTER_SANITIZE_STRING) : $v;
        } // end foreach
        // var_dump( $this->query_params ); die(); // get_pay_link

        foreach ($_POST as $k => $v) {
            $this->parsed_body[$k] = (is_numeric($v)) ? (is_int($v)) ? intval($v) : floatval($v) : (is_string($v)) ? filter_var($v, FILTER_SANITIZE_STRING) : $v;
        } // end foreach
        // var_dump( $this->parsed_body ); die();
        // array(3) { ["payservice"]=> string(13) "ddm_qrcodepay" ["amount"]=> string(3) "100" ["csrftoken"]=> string(217) "eyJSRU1PVEVfQUREUiI6IjEwLjIyLjExNC4xMjIiLCJQSFBfU0VMRiI6IlwvZ3BrMmRldlwvZGVwb3NpdC5waHAiLCJkYXRhIjpudWxsLCJmaW5nZXJ0cmFja2VyIjoiZGE2NmRhODU0MDNlY2QyODA2NWZkN2YzZmRlNGQwZjQifQ==_ac8a3dd5af2a3df2dafb1d4214712f8117f10a07" }
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

    public function getParam(string $key)
    {
        $params = $this->getQueryParams() + $this->getParsedBody();
        return $params[$key] ?? '';
    }
} // end class Request

class Response {
    private $status_code = 200;
    private $headers = [];
    private $body = '';
    private static $instance;

    private function __construct() {
        ob_start();
    } // end __construct

    public static function getInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }
        return self::$instance;
    } // end getInstance

    public function withHeader($header, $value) {
        self::$instance->headers[$header] = [$value];
        // var_dump(self::$instance->headers);
        return $this;
    }

    public function withStatus($status_code) {
        self::$instance->status_code = $status_code;
        return $this;
    }

    public function withRedirect($url, $status_code)
    {
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

        // Status
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

    public function withJson(array $data, int $status_code = null, int $encode_options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) {
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
} // end class Response
