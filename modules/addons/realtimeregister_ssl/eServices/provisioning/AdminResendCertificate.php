<?php

declare(strict_types=1);

namespace MGModule\RealtimeRegisterSsl\eServices\provisioning;

use Exception;
use MGModule\RealtimeRegisterSsl\eRepository\whmcs\service\SSL;
use MGModule\RealtimeRegisterSsl\eServices\EmailTemplateService;
use MGModule\RealtimeRegisterSsl\models\apiConfiguration\Repository;

class AdminResendCertificate
{
    private $p;

    public function __construct(&$params)
    {
        $this->p = &$params;
    }

    public function run()
    {
        try {
            $this->adminResendCertificate();
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
        return 'success';
    }

    private function adminResendCertificate()
    {
        $ssl = new SSL();
        $serviceSSL = $ssl->getByServiceId($this->p['serviceid']);

        if (is_null($serviceSSL)) {
            throw new Exception('Create has not been initialized.');
        }

        if (empty($serviceSSL->remoteid)) {
            throw new Exception('Product not ordered in RealtimeRegisterSSL.');
        }

        if (empty($serviceSSL->getCa())) {
            throw new Exception('An error occurred. Certificate body is empty.');
        }
        $apiConf = (new Repository())->get();
        $sendCertificateTemplate = $apiConf->send_certificate_template;
        if ($sendCertificateTemplate == null) {
            sendMessage(EmailTemplateService::SEND_CERTIFICATE_TEMPLATE_ID, $this->p['serviceid'], [
                'ssl_certificate' => nl2br($serviceSSL->getCa()),
                'crt_code' => nl2br($serviceSSL->getCrt()),
            ]);
        } else {
            $templateName = EmailTemplateService::getTemplateName($sendCertificateTemplate);
            sendMessage($templateName, $this->p['serviceid'], [
                'ssl_certificate' => nl2br($serviceSSL->getCa()),
                'crt_code' => nl2br($serviceSSL->getCrt()),
            ]);
        }
    }
}
