<?php

namespace MGModule\RealtimeRegisterSsl\eServices\provisioning;

use Illuminate\Database\Capsule\Manager as Capsule;
use Exception;
use MGModule\RealtimeRegisterSsl\eProviders\ApiProvider;
use MGModule\RealtimeRegisterSsl\eRepository\RealtimeRegisterSsl\Products;
use MGModule\RealtimeRegisterSsl\eServices\FlashService;
use MGModule\RealtimeRegisterSsl\mgLibs\Lang;

class SSLStepTwo {

    // allow *.domain.com as SAN for products
    const PRODUCTS_WITH_ADDITIONAL_SAN_VALIDATION = array(139, 100, 99, 63, 25, 24);
    
    private $p;
    private $errors = [];
    private $additional_san_validation = array(139, 100, 99, 63, 25, 24);
    private $csrDecode = []; 
    
    function __construct(&$params) {
        
        $service = new \MGModule\RealtimeRegisterSsl\models\whmcs\service\Service($params['serviceid']);
        $product = new \MGModule\RealtimeRegisterSsl\models\whmcs\product\Product($service->productID);
        
        $productssl = false;
        $checkTable = Capsule::schema()->hasTable(Products::MGFW_REALTIMEREGISTERSSL_PRODUCT_BRAND);
        if($checkTable)
        {
            if (Capsule::schema()->hasColumn(Products::MGFW_REALTIMEREGISTERSSL_PRODUCT_BRAND, 'data'))
            {
                $productsslDB = Capsule::table(Products::MGFW_REALTIMEREGISTERSSL_PRODUCT_BRAND)->where('pid', $product->configuration()->text_name)->first();
                if(isset($productsslDB->data))
                {
                    $productssl['product'] = json_decode($productsslDB->data, true); 
                }
            }
        }
        if(!$productssl)
        {
            $productssl = ApiProvider::getInstance()->getApi(false)->getProductDetails($params['configoption1']);
        }
        
        if(isset($productssl['product_san_wildcard']) && $productssl['product_san_wildcard'] == 'yes')
        {
            $this->additional_san_validation[] = $params['configoption1']; 
        }
          
        $this->p = &$params;
    }

    public function run() {
        try {
            $this->SSLStepTwo();
            
        } catch (Exception $ex) {            
            return ['error' => $ex->getMessage()]; 
        }
        
        if (!empty($this->errors)) { 
            return ['error' => $this->errorsToWhmcsError()];
        }
        /*if(!isset($this->p['fields']['sans_domains']) || $this->p['fields']['sans_domains'] == '') {            
            $this->redirectToStepThree();                    
        }*/
        
        $service = new \MGModule\RealtimeRegisterSsl\models\whmcs\service\Service($this->p['serviceid']);
        $product = new \MGModule\RealtimeRegisterSsl\models\whmcs\product\Product($service->productID);
        
        $productssl = false;
        $checkTable = Capsule::schema()->hasTable(Products::MGFW_REALTIMEREGISTERSSL_PRODUCT_BRAND);
        if($checkTable)
        {
            if (Capsule::schema()->hasColumn(Products::MGFW_REALTIMEREGISTERSSL_PRODUCT_BRAND, 'data'))
            {
                $productsslDB = Capsule::table(Products::MGFW_REALTIMEREGISTERSSL_PRODUCT_BRAND)->where('pid', $product->configuration()->text_name)->first();
                if(isset($productsslDB->data))
                {
                    $productssl['product'] = json_decode($productsslDB->data, true); 
                }
            }
        }
        if(!$productssl)
        {
            $productssl = ApiProvider::getInstance()->getApi(false)->getProduct($product->configuration()->text_name);
        }

        $ValidationMethods = ['email', 'dns', 'http', 'https'];        
        if(!$productssl['product']['dcv_email'])
        {
            $ValidationMethods = array_diff($ValidationMethods, ['email']);
        }
        if(!$productssl['product']['dcv_dns'])
        {
            $ValidationMethods = array_diff($ValidationMethods, ['dns']);
        }
        if(!$productssl['product']['dcv_http'])
        {
            $ValidationMethods = array_diff($ValidationMethods, ['http']);
        }
        if(!$productssl['product']['dcv_https'])
        {
            $ValidationMethods = array_diff($ValidationMethods, ['https']);
        }
        
        if($product->configuration()->text_name == '144') 
        {
            $ValidationMethods = array_diff($ValidationMethods, ['email']);
            $ValidationMethods = array_diff($ValidationMethods, ['dns']);
        }

        if(empty($this->csrDecode))
        {
            $this->csrDecode   = ApiProvider::getInstance()->getApi(false)->decodeCSR(trim(rtrim($_POST['csr'])));
        }
        $decodedCSR = $this->csrDecode;
        $_SESSION['csrDecode'] = $decodedCSR;
        $step2js = new SSLStepTwoJS($this->p);
        $mainDomain       = $decodedCSR['csrResult']['CN'];
        
        if(empty($mainDomain))
        {
            $mainDomain = $decodedCSR['csrResult']['dnsName(s)'][0];
        }
        
        $domains = $mainDomain . PHP_EOL . $_POST['fields']['sans_domains'];
        $sansDomains = \MGModule\RealtimeRegisterSsl\eHelpers\SansDomains::parseDomains(strtolower($domains));
        $approveremails = $step2js->fetchApprovalEmailsForSansDomains($sansDomains);
        
        $_SESSION['approveremails'] = $approveremails;
        
        $apiConf = (new \MGModule\RealtimeRegisterSsl\models\apiConfiguration\Repository())->get();
        if($apiConf->email_whois)
        {
            foreach($approveremails as $domainkey => $approveremail_domain)
            {
                foreach($approveremail_domain as $emailkey => $email)
                {
                    if (strpos($email, 'admin@') === false && 
                        strpos($email, 'administrator@') === false && 
                        strpos($email, 'hostmaster@') === false && 
                        strpos($email, 'postmaster@') === false && 
                        strpos($email, 'webmaster@') === false) 
                    {
                        unset($approveremails[$domainkey][$emailkey]);
                    }
                }
            }
        }
        
        return [
            'approveremails' => 'loading...', 
            'approveremails2' => $approveremails, 
            'approvalmethods' => $ValidationMethods,
            'brand' => $productssl['product']['brand']
        ];
    }
    public function setPrivateKey($privKey) {
        $this->p['privateKey'] = $privKey;
    }
    private function redirectToStepThree() {
        $tokenInput = generate_token();
        preg_match("/value=\"(.*)\\\"/", $tokenInput, $match);
        $token = $match[1];
        
        ob_clean();   
        header('Location: configuressl.php?cert='. $_GET['cert'] . '&step=3&token=' . $token);
        die();
    }
    private function SSLStepTwo() {
        \MGModule\RealtimeRegisterSsl\eRepository\whmcs\service\SSLTemplorary::getInstance()->setByParams($this->p);
        
        $this->storeFieldsAutoFill();        
        $this->validateSansDomains();
        $this->validateSansDomainsWildcard();
        $this->validateFields();
        if($this->p['configoption1'] != '144')
        {
            $this->validateCSR();
        }
        if(isset($this->p['privateKey']) && $this->p['privateKey'] != null) {            
            $privKey = decrypt($this->p['privateKey']);
            $GenerateSCR = new \MGModule\RealtimeRegisterSsl\eServices\provisioning\GenerateCSR($this->p, $_POST);
            $GenerateSCR->savePrivateKeyToDatabase($this->p['serviceid'], $privKey);  
        }
      
    }
    
    private function validateSansDomainsWildcard() {
        $sansDomainsWildcard = $this->p['fields']['wildcard_san'];
        $sansDomainsWildcard = \MGModule\RealtimeRegisterSsl\eHelpers\SansDomains::parseDomains($sansDomainsWildcard);
        
        foreach($sansDomainsWildcard as $domain)
        {
            $check = substr($domain, 0,2);
            if($check != '*.')
            {
                throw new Exception('SAN\'s Wildcard are incorrect');
            }
            $domaincheck = \MGModule\RealtimeRegisterSsl\eHelpers\Domains::validateDomain(substr($domain, 2));
            if($domaincheck !== true)
            {
                throw new Exception('SAN\'s Wildcard are incorrect');
            }
        }
   
        $includedSans = (int) $this->p[ConfigOptions::PRODUCT_INCLUDED_SANS_WILDCARD];
        $boughtSans   = (int) $this->p['configoptions']['sans_wildcard_count'];
        
        $sansLimit = $includedSans + $boughtSans;
        if (count($sansDomainsWildcard) > $sansLimit) {
            throw new Exception(Lang::T('sanLimitExceededWildcard'));
        }
    }
    
    private function validateSansDomains() {
        $sansDomains    = $this->p['fields']['sans_domains'];
        $sansDomains    = \MGModule\RealtimeRegisterSsl\eHelpers\SansDomains::parseDomains($sansDomains);
        
        $apiProductId     = $this->p[ConfigOptions::API_PRODUCT_ID];
        
        $invalidDomains = \MGModule\RealtimeRegisterSsl\eHelpers\Domains::getInvalidDomains($sansDomains, false);
             
        if($apiProductId != '144') {
            
            if (count($invalidDomains)) {
                throw new Exception(Lang::getInstance()->T('incorrectSans') . implode(', ', $invalidDomains));
            }
            
        } else {
            
            $iperror = false;
            foreach($sansDomains as $domainname)
            {
                if(!filter_var($domainname, FILTER_VALIDATE_IP)) {
                    $iperror = true;
                }
            }
            
            if (count($invalidDomains) && $iperror) {
                throw new Exception('SANs are incorrect');
            }
            
        }
        
        
        $includedSans = (int) $this->p[ConfigOptions::PRODUCT_INCLUDED_SANS];
        $boughtSans   = (int) $this->p['configoptions'][ConfigOptions::OPTION_SANS_COUNT];
        $sansLimit = $includedSans + $boughtSans;
        if (count($sansDomains) > $sansLimit) {
            throw new Exception(Lang::T('sanLimitExceeded'));
        }
    }

    private function validateCSR() {
        $csr = trim(rtrim($this->p['csr']));
        $this->csrDecode = ApiProvider::getInstance()->getApi(false)->decodeCSR($csr);
        $decodedCSR = $this->csrDecode;
        $_SESSION['csrDecode'] = $decodedCSR;
        $productssl = false;
        $checkTable = Capsule::schema()->hasTable(Products::MGFW_REALTIMEREGISTERSSL_PRODUCT_BRAND);
        if($checkTable)
        {
            if (Capsule::schema()->hasColumn(Products::MGFW_REALTIMEREGISTERSSL_PRODUCT_BRAND, 'data'))
            {
                $productsslDB = Capsule::table(Products::MGFW_REALTIMEREGISTERSSL_PRODUCT_BRAND)->where('pid', $this->p['configoption1'])->first();
                if(isset($productsslDB->data))
                {
                    $productssl['product'] = json_decode($productsslDB->data, true); 
                }
            }
        }
        
        if(!$productssl)
        {
            $productssl = ApiProvider::getInstance()->getApi(false)->getProduct($this->p['configoption1']);
        }
        
        if($productssl['product']['wildcard_enabled'])
        {
            if(strpos($decodedCSR['csrResult']['CN'], '*.') !== false || strpos($decodedCSR['csrResult']['dnsName(s)'][0], '*.') !== false)
            {
                return true;
            }
            else
            {
                if(isset($decodedCSR['csrResult']['errorMessage']))
                    throw new Exception($decodedCSR['csrResult']['errorMessage']);
                
                
                throw new Exception(Lang::T('incorrectCSR'));
            }
        }
        
        if(isset($decodeCSR['csrResult']['errorMessage'])) {
            
            if(isset($decodeCSR['csrResult']['CN']) && strpos($decodeCSR['csrResult']['CN'], '*.') !== false)
            {
                return true;
            }
            
            throw new Exception($decodeCSR['csrResult']['errorMessage']);
        }
    }
    
    private function validateFields() {
        if (empty(trim($this->p['jobtitle']))) {
            $this->errors[] = Lang::T('adminJobTitleMissing');
        }
        if (empty(trim($this->p['orgname']))) {
            $this->errors[] = Lang::T('organizationNameMissing');
        }
        if (empty(trim($this->p['fields']['order_type']))) {
            $this->errors[] = Lang::T('orderTypeMissing');
        }
    }
    
    private function storeFieldsAutoFill() {
        $fields = [];
        
        $a = ['servertype', 'csr', 'firstname', 'lastname', 'orgname',
            'jobtitle', 'email', 'address1', 'address2', 'city', 'state',
            'postcode', 'country', 'phonenumber','privateKey'];

        $b = [
            'order_type', 'sans_domains', 'org_name', 'org_division', 'org_lei', 'org_duns', 'org_addressline1',
            'org_city', 'org_country', 'org_fax', 'org_phone', 'org_postalcode', 'org_regions'
        ];
        
        
        foreach ($a as $value) {
            $fields[] = [
                'name' => $value,
                'value' => $this->p[$value]
            ];
        } 
        foreach ($b as $value) {
            
            if($value == 'fields[order_type]') {
                $fields[] = [
                    'name' => sprintf('%s', $value),
                    'value' => $this->p['fields']['order_type']
                ];
            } else {
                $fields[] = [
                    'name' => sprintf('fields[%s]', $value),
                    'value' => $this->p['fields'][$value]
                ];
            }
            
        }   

        FlashService::setFieldsMemory($_GET['cert'], $fields);
    }
    
    private function errorsToWhmcsError() {
        $i   = 0;
        $err = '';

        if (count($this->errors) === 1) {
            return $this->errors[0];
        }

        foreach ($this->errors as $error) {
            if ($i === 0) {
                $err .= $error . '</li>';
            } else {
                $err .= '<li>' . $error . '</li>';
            }
            $i++;
        }
        return $err;
    }
}
