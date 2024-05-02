<?php

namespace MGModule\RealtimeRegisterSsl\eServices\provisioning;

use Exception;
use MGModule\RealtimeRegisterSsl\eProviders\ApiProvider;

class TerminateAccount
{
    private $p;

    function __construct(&$params)
    {
        $this->p = &$params;
    }

    public function run()
    {
        try {
            $this->terminateAccount();
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
        return 'success';
    }

    private function terminateAccount()
    {
        $ssl = new \MGModule\RealtimeRegisterSsl\eRepository\whmcs\service\SSL();
        $serviceSSL = $ssl->getByServiceId($this->p['serviceid']);
        
        if (is_null($serviceSSL)) {
            throw new Exception('Create has not been initialized.');
        }
        
        if (empty($serviceSSL->remoteid)) {
            $serviceSSL->delete();
            return;
        }
       
        $reason = 'Order canceled for non-payment.'; 
        ApiProvider::getInstance()->getApi()->cancelSSLOrder($serviceSSL->remoteid, $reason);
        $serviceSSL->delete();
    }
}
