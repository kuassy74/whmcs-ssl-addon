<?php

namespace MGModule\RealtimeRegisterSsl\eServices\provisioning;

use Exception;

class GetCertificate {

    /**
     *
     * @var \MGModule\RealtimeRegisterSsl\eModels\whmcs\service\SSL
     */
    private $ssl;

    /**
     * 
     * @param \MGModule\RealtimeRegisterSsl\eModels\whmcs\service\SSL $ssl
     */
    function __construct(\ MGModule\RealtimeRegisterSsl\eModels\whmcs\service\SSL $ssl) {
        $this->ssl = $ssl;
    }

    public function run() {
        try {
            $this->GetCertificate();
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return 'success';
    }

    public function GetCertificate() {
        $orderStatus = \MGModule\RealtimeRegisterSsl\eProviders\ApiProvider::getInstance()->getApi()->getOrderStatus($this->ssl->remoteid);
        $this->ssl->setOrderStatus($orderStatus['status']);
        $this->ssl->save();

        if ($orderStatus['status'] !== 'active') {
            throw new Exception('Certificate is not ready to download');
        }
        $this->ssl->setCrt($orderStatus['crt_code']);
        $this->ssl->setCa($orderStatus['ca_code']);
        $this->ssl->save();
    }
    
    public static function runBySslId($id) {
        try {
            $ssl        = new \MGModule\RealtimeRegisterSsl\eRepository\whmcs\service\SSL();
            $sslService = $ssl->getByServiceId($id);
            if (is_null($sslService)) {
                throw new Exception('Create has not been initialized');
            }
            $getCertificate = new GetCertificate($sslService);
            return $getCertificate->run();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
