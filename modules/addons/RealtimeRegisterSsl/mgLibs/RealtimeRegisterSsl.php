<?php

namespace MGModule\RealtimeRegisterSsl\mgLibs;

use MGModule\RealtimeRegisterSsl\Configuration;
use WHMCS\Database\Capsule;

/**
 * Use any way you want. Free for all
 *
 * @version 1.0
 * */
error_reporting(E_ALL);
ini_set('display_errors', 'on');

define('DEBUG', false);

//define('DEBUG', false);

class RealtimeRegisterSsl
{
    protected string $apiUrl = 'https://api.yoursrs-ote.com';
    protected string $key;
    protected $lastStatus;
    protected $lastResponse;
    protected $lastRequest;
    protected $apiExceptions = true;
    protected $exceptionType;

    public function __construct($key = null)
    {
        $this->key = isset($key) ? $key : null;

        $this->setRealtimeRegisterSslApiException();
//        $this->setRealtimeRegisterSslException();
    }

    public function setRealtimeRegisterSslException()
    {
        $this->exceptionType = 'RealtimeRegisterSslException';
    }

    public function setRealtimeRegisterSslApiException()
    {
        $this->exceptionType = 'RealtimeRegisterSslApiException';
    }

    public function setNoneException()
    {
        $this->exceptionType = 'none';
    }

    public function turnOnApiExceptions()
    {
        $this->apiExceptions = true;
    }

    public function turnOffApiExceptions()
    {
        $this->apiExceptions = false;
    }

    public function auth($user, $pass)
    {
        $checkKey = Capsule::table('tblconfiguration')->where('setting', 'realtimeregisterssl_authkey')->first();
        if (!isset($checkKey->value) || empty($checkKey->value)) {
            $response = $this->call('/auth/', null , [
                'user' => $user,
                'pass' => $pass
            ]);

            if (!empty($response['key'])) {

                if (!isset($checkKey->value)) {
                    Capsule::table('tblconfiguration')->insert([
                        'setting' => 'realtimeregisterssl_authkey',
                        'value' => $response['key']
                    ]);
                } else if (empty($checkKey->value)) {
                    Capsule::table('tblconfiguration')->where('setting', 'realtimeregisterssl_authkey')->update([
                        'value' => $response['key']
                    ]);
                }

                $this->key = $response ['key'];
                return $response;
            }
            Capsule::table('tblconfiguration')->where('setting', 'realtimeregisterssl_authkey')->delete();
            return $response;
        } else {
            $this->key = $checkKey->value;
            return ['key' => $checkKey->value];
        }
    }

    public function addSslSan($orderId, $count, $single, $wildcard)
    {

        $postData['order_id'] = $orderId;
        $postData['count'] = $count;
        $postData['single_san_count'] = $single;
        $postData['wildcard_san_count'] = $wildcard;

        if (empty($postData['count'])) {
            unset($postData['count']);
        }
        if (empty($postData['single_san_count'])) {
            unset($postData['single_san_count']);
        }
        if (empty($postData['wildcard_san_count'])) {
            unset($postData['wildcard_san_count']);
        }

        return $this->call('/orders/add_ssl_san_order/', null, $postData);
    }

    public function cancelSSLOrder($orderId, $reason)
    {
        $postData ['order_id'] = $orderId;
        $postData ['reason'] = $reason;

        return $this->call('/orders/cancel_ssl_order/', null, $postData);
    }

    public function changeDcv($orderId, $data)
    {
        return $this->call('/orders/ssl/change_dcv/' . (int)$orderId, null, $data);
    }

    public function changeValidationMethod($orderId, $data)
    {
        return $this->call('/orders/ssl/change_validation_method/' . (int)$orderId, null, $data);
    }

    public function changeDomainValidationMethod($orderId, $data)
    {
        return $this->call('/orders/ssl/change_domains_validation_method/' . (int)$orderId, null, $data);
    }

    public function revalidate($orderId, $data)
    {
        return $this->call('/orders/ssl/revalidate/' . (int)$orderId, null, $data);
    }

    public function changeValidationEmail($orderId, $data)
    {
        return $this->call('/orders/ssl/change_validation_email/' . (int)$orderId, null, $data);
    }

    public function setKey($key)
    {
        if ($key) {
            $this->key = $key;
        }
    }

    public function setUrl($url)
    {
        $this->apiUrl = $url;
    }

    /**
     * @see https://dm.realtimeregister.com/docs/api/ssl/decocdecsr
     *
     * @param string $csr
     * @return mixed
     * @throws RealtimeRegisterApiException
     * @throws RealtimeRegisterException
     */
    public function decodeCSR(string $csr)
    {
        if ($csr) {
            $postData ['csr'] = $csr;
        }

        return $this->call('/v2/ssl/decodecsr', null, $postData);
    }

    public function getWebServers($type)
    {
        return $this->call('/tools/webservers/' . (int)$type, null);
    }

    public function getDomainAlternative($csr = null)
    {
        $postData['csr'] = $csr;

        return $this->call('/tools/domain/alternative/', null, $postData);
    }

    public function getDomainEmails($domain)
    {
        if ($domain) {
            $postData ['domain'] = $domain;
        }

        return $this->call('/tools/domain/emails/', null, $postData);
    }

    public function getDomainEmailsForGeotrust($domain)
    {
        if ($domain) {
            $postData ['domain'] = $domain;
        }

        return $this->call('/tools/domain/emails/geotrust', null, $postData);
    }

    /**
     * @see https://dm.realtimeregister.com/docs/api/customers/pricelist
     *
     * @return mixed
     * @throws RealtimeRegisterApiException
     * @throws RealtimeRegisterException
     */
    public function getAllProductPrices()
    {
        $apiKeyRecord = \Illuminate\Database\Capsule\Manager::table(
            'mgfw_REALTIMEREGISTERSSL_api_configuration'
        )->first();
        return $this->call('/v2/customers/' . $this->getCustomer($apiKeyRecord->api_login) . '/pricelist', null);
    }

    public function getAllProducts()
    {
        return $this->call('/products/', null);
    }

    public function getProduct($productId)
    {
        return $this->call('/products/ssl/' . $productId, null);
    }

    /**
     * https://dm.realtimeregister.com/docs/api/ssl/products/list
     *
     * @param int $offset
     * @param int $limit
     * @return mixed
     * @throws RealtimeRegisterApiException
     * @throws RealtimeRegisterException
     */
    public function getProducts(int $offset = 0, int $limit = 10)
    {
        return $this->call('/v2/ssl/products', ['offset' => $offset, 'limit' => $limit]);
    }

    public function getProductDetails($productId)
    {
        return $this->call('/products/details/' . $productId, null);
    }

    public function getProductPrice($productId)
    {
        return $this->call('/products/price/' . $productId, null);
    }

    public function getUserAgreement($productId)
    {
        return $this->call('/products/agreement/' . $productId, null);
    }

    public function getAccountBalance()
    {
        return $this->call('/account/balance/', null);
    }

    public function getAccountDetails()
    {
        return $this->call('/account/', null);
    }

    public function getTotalOrders()
    {
        return $this->call('/account/total_orders/', null);
    }

    public function getAllInvoices()
    {
        return $this->call('/account/invoices/', null);
    }

    public function getUnpaidInvoices()
    {
        return $this->call('/account/invoices/unpaid/', null);
    }

    public function getTotalTransactions()
    {
        return $this->call('/account/total_transactions/', null);
    }

    public function addSSLOrder1($data)
    {
        return $this->call('/orders/add_ssl_order1/', null, $data);
    }

    public function addSSLOrder($data)
    {
        return $this->call('/orders/add_ssl_order/', null, $data);
    }

    public function addSSLRenewOrder($data)
    {
        return $this->call('/orders/add_ssl_renew_order/', null, $data);
    }

    public function reIssueOrder($orderId, $data)
    {
        return $this->call('/orders/ssl/reissue/' . (int)$orderId, null, $data);
    }

    public function activateSSLOrder($orderId)
    {
        return $this->call('/orders/ssl/activate/' . (int)$orderId, null);
    }

    public function addSandboxAccount($data)
    {
        return $this->call('/accounts/sandbox/add/', null, $data);
    }

    public function getOrderStatus($orderId)
    {
        return $this->call('/orders/status/' . (int)$orderId, null);
    }

    public function getOrderStatuses($ordersId)
    {
        return $this->call('/orders/statuses/', null, $ordersId);
    }

    public function comodoClaimFreeEV($orderId, $data)
    {
        return $this->call('/orders/ssl/comodo_claim_free_ev/' . (int)$orderId, null, $data);
    }

    public function getOrderInvoice($orderId)
    {
        return $this->call('/orders/invoice/' . (int)$orderId, null);
    }

    public function getUnpaidOrders()
    {
        return $this->call('/orders/list/unpaid/', null);
    }

    public function resendEmail($orderId)
    {
        return $this->call('/orders/ssl/resend_validation_email/' . (int)$orderId, null);
    }

    public function resendValidationEmail($orderId)
    {
        return $this->call('/orders/ssl/resend_validation_email/' . (int)$orderId, null);
    }

    public function getCSR($data)
    {
        return $this->call('/tools/csr/get/', null, $data);
    }

    public function generateCSR($data)
    {
        return $this->call('/tools/csr/generate/', null, $data);
    }

    protected function call($uri, $getData = [], $postData = [], $forcePost = false, $isFile = false)
    {

        $this->lastRequest = [
            'uri' => $uri,
            'post' => $postData,
        ];

        $url = $this->apiUrl . $uri;
        if (!empty($getData)) {
            foreach ($getData as $key => $value) {
                $url .= (strpos($url, '?') !== false ? '&' : '?') . urlencode($key) . '=' . rawurlencode($value);
            }
        }

        $post = !empty($postData) || $forcePost ? true : false;
        $c = curl_init($url);
        if ($post) {
            curl_setopt($c, CURLOPT_POST, true);
        }

        $queryData = '';
        if (!empty($postData)) {
            $queryData = $isFile ? $postData : http_build_query($postData);
            curl_setopt($c, CURLOPT_POSTFIELDS, $queryData);
        }

        $configuration = new Configuration();

        $headers = [];
        $headers[] = 'user_agent: realtime-register-ssl-whmcs/' . $configuration->version;
        $headers[] = 'Authorization: ApiKey ' . $this->key;

        curl_setopt($c, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($c, CURLINFO_HEADER_OUT, true);
        curl_setopt($c, CURLOPT_HEADER, true);

        $data = curl_exec($c);
        $info = curl_getinfo($c);
        $result = substr($data, $info['header_size']);

        if (DEBUG) {
            echo "<pre><br><br>";
            echo "===============\n";
            echo __FILE__ . "\n";
            echo "===============\n\n";
            echo "url = " . $url . "\n\n";
            echo "queryData = " . urldecode($queryData) . "\n\n";
            echo "getData = \n";
            print_r($getData) . "\n\n";
            echo "postData = \n";
            print_r($postData) . "\n\n";
            echo "result Realtime Register SSL API = \n";
            print_r(json_decode($result, true));
            echo "</pre>";
        }

        logModuleCall(
            'RealtimeRegisterSsl',
            'Time: ' . $info['total_time'] . ' ' . $uri,
            $info['request_header'] . $queryData,
            $data
        );

        if ($result === false) {
            throw new RealtimeRegisterException(curl_error($c));
        }

        $status = curl_getinfo($c, CURLINFO_HTTP_CODE);

        if ($status == '403') {
            Capsule::table('tblconfiguration')->where('setting', 'realtimeregisterssl_authkey')->delete();
        }

        curl_close($c);
        $this->lastStatus = $status;
        $this->lastResponse = json_decode($result, true);

        if (is_null($this->lastResponse)) {
            throw new RealtimeRegisterApiException('Invalid Response from API');
        }

        if (
            $this->lastResponse['error'] === true
            && $this->apiExceptions
            && $this->exceptionType === 'RealtimeRegisterSslException'
        ) {
            throw new RealtimeRegisterException($this->lastResponse['description']);
        }

        if (
            $this->lastResponse['error'] === true
            && $this->apiExceptions
            && $this->exceptionType === 'RealtimeRegisterSslApiException'
        ) {
            throw new RealtimeRegisterApiException($this->lastResponse['description']);
        }

        return $this->lastResponse;
    }

    public function getLastStatus()
    {
        return $this->lastStatus;
    }

    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    public function getLastRequest()
    {
        return $this->lastRequest;
    }

    private function getCustomer(string $apiKey)
    {
        $tmp = base64_decode($apiKey);
        return explode('/', $tmp)[0];
    }
}

class RealtimeRegisterException extends \Exception
{

}

class RealtimeRegisterApiException extends \Exception
{

}
