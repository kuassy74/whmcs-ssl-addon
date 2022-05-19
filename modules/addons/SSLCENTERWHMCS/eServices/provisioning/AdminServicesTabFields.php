<?php

namespace MGModule\SSLCENTERWHMCS\eServices\provisioning;

use Exception;

class AdminServicesTabFields {

    private $p;

    function __construct(&$params) {
        $this->p = &$params;
    }

    public function run() {
        try {
            return $this->adminServicesTabFields();
        } catch (Exception $ex) {
            return [];
        }
        return [];
    }

    private function adminServicesTabFields() {
        $return = [];
        $return['JS/HTML'] = \MGModule\SSLCENTERWHMCS\eServices\ScriptService::getAdminServiceScript($this->getServiceVars());
        return array_merge($return, $this->getCertificateDetails());
    }
    
    private function getCertificateDetails() {
        
        try {
            $ssl        = new \MGModule\SSLCENTERWHMCS\eRepository\whmcs\service\SSL();
            $sslService = $ssl->getByServiceId($this->p['serviceid']);
            if (is_null($sslService)) {
                throw new Exception('Create has not been initialized');
            }
            
            if ($sslService->status === 'Awaiting Configuration') {
                return ['Configuration Status' => 'Awaiting Configuration'];
            }
            
            if(empty($sslService->remoteid)) {
                throw new Exception('Order id not exist');
            }
            
            $return = [];
            $return['SSLCenter API Order ID'] = $sslService->remoteid;

            $orderDetails = (array)$sslService->configdata;
            
            if(!$orderDetails['domain'])
            {
                $configDataUpdate = new \MGModule\SSLCENTERWHMCS\eServices\provisioning\UpdateConfigData($sslService);
                $orderStatus = $configDataUpdate->run();
                
                $sslService = $ssl->getByServiceId($this->p['serviceid']);    
                $orderDetails = (array)$sslService->configdata;
            }
 
            $return['Cron Synchronized'] = isset($orderDetails['synchronized']) && !empty($orderDetails['synchronized']) ? $orderDetails['synchronized'] : 'Not synchronized';
            $return['Comodo Order ID'] = $orderDetails['partner_order_id']?:"-"; 
            $return['Configuration Status'] = $sslService->status;  
            $return['Domain'] = $orderDetails['domain'];
            $return['Order Status'] = ucfirst($orderDetails['ssl_status']);   
            if(isset($orderDetails['approver_method']->email) && !empty($orderDetails['approver_method']->email))
            {
                $return['Approver email'] = $orderDetails['approver_method']->email;
            }
            $return['Order Status Description'] = $orderDetails['order_status_description'] ? : '-';  
            
            if($orderDetails['ssl_status'] == 'active') {                
                $return['Valid From'] = $orderDetails['valid_from'];
                $return['Expires'] = $orderDetails['valid_till'];
            }
       
            foreach ($orderDetails['san_details'] as $key => $san) {
                $return['SAN ' . ($key + 1)] = sprintf('%s / %s', $san->san_name, $san->status_description);
            }

            return $return;
            
        } catch (Exception $ex) {
            return ['SSLCenter Error' => $ex->getMessage()];
        }
    }

    private function getServiceVars() {
        global $CONFIG;
        
        $includedSans = (int) $this->p[ConfigOptions::PRODUCT_INCLUDED_SANS];
        $boughtSans   = (int) $this->p['configoptions'][ConfigOptions::OPTION_SANS_COUNT];
        $sansLimit = $boughtSans;

        $includedSansWildcard = (int) $this->p[ConfigOptions::PRODUCT_INCLUDED_SANS_WILDCARD];
        $boughtSansWildcard   = (int) $this->p['configoptions'][ConfigOptions::OPTION_SANS_WILDCARD_COUNT];
        $sansLimitWildcard = $boughtSansWildcard;
        
        require dirname(dirname(dirname(dirname(dirname(__DIR__))))).DIRECTORY_SEPARATOR.'configuration.php';

        $adminpath = 'admin';
        if(isset($customadminpath))
        {
            $adminpath = $customadminpath;
        }
         
        return [
            'serviceid' => $this->p['serviceid'],
            'email'     => $this->p['clientsdetails']['email'],
            'userid'    => $this->p['userid'],
            'sansLimit' => $sansLimit,
            'sansLimitWildcard' => $sansLimitWildcard,
            'adminpath' => $adminpath,
            'version'   => substr($CONFIG['Version'],0,1)
        ];
    }
}
