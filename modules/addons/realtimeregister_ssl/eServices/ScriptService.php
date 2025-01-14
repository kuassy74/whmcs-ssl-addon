<?php

namespace MGModule\RealtimeRegisterSsl\eServices;

use MGModule\RealtimeRegisterSsl\models\apiConfiguration\Repository;
use MGModule\RealtimeRegisterSsl\models\whmcs\clients\Client;
use MGModule\RealtimeRegisterSsl\models\whmcs\service\Service;

class ScriptService
{
    public const WEB_SERVER = 'scripts/webServerType';
    public const SAN_EMAILS = 'scripts/sanApprovals';
    public const ADMIN_SERVICE = 'scripts/adminService';
    public const AUTO_FILL = 'scripts/autoFill';
    public const PRIVATE_KEY_FILL = 'scripts/privateKeyFill';
    public const ORDER_TYPE_FILL = 'scripts/orderTypeFill';
    public const OPTION_ERROR = 'scripts/configOptionsError';
    public const STEP_ONE_BASE = 'scripts/stepOneBase';
    public const ORDER_TYPE = 'scripts/orderType';
    public const GENERATE_CSR_MODAL = 'scripts/generateCsrModal';

    public static function getWebServerTypeScript()
    {
        return TemplateService::buildTemplate(self::WEB_SERVER);
    }
    
    public static function getStepOneBaseScript($brand, $domains = [])
    {
        $apiConf = (new Repository())->get();
        $auto_install_panel = $apiConf->auto_install_panel;
        return TemplateService::buildTemplate(
            self::STEP_ONE_BASE,
            ['brand' => json_encode($brand), 'domains' => $domains, 'auto_install_panel' => $auto_install_panel]
        );
    }
    public static function getOrderTypeScript($orderTypes, $fillVarsJSON)
    {
        return TemplateService::buildTemplate(self::ORDER_TYPE,[
            'fillVars' => addslashes($fillVarsJSON),
            'orderTypes' => json_encode($orderTypes)
        ]);
    }
    public static function getGenerateCsrModalScript(
        $serviceId,
        $fillVarsJSON,
        $countriesForGenerateCsrForm,
        $vars = []
    ) {
        $apiConf = (new Repository())->get();
        $profile_data_csr = $apiConf->profile_data_csr;

        $csrWithData = false;
        $csrData = [];
        if ($profile_data_csr) {
            $service = new Service($serviceId);
            $client = new Client($service->clientID);

            $csrData['country'] = $client->getCountry();
            $csrData['state'] = $client->getState();
            $csrData['locality'] = $client->getCity();
            $csrData['organization'] = $client->companyname ?: $client->getFullName();
            $csrData['org_unit'] = 'IT';
            $csrData['common_name'] = $service->domain;
            $csrData['email'] = $client->email;

            $csrWithData = true;
        }

        return TemplateService::buildTemplate(self::GENERATE_CSR_MODAL, [
            'fillVars' => addslashes($fillVarsJSON),
            'countries'=> json_encode($countriesForGenerateCsrForm),
            'vars' => $vars,
            'csrWithData' => $csrWithData,
            'csrData' => $csrData
        ]);
    }

    public static function getAutoFillPrivateKeyField($privateKey)
    {
        return TemplateService::buildTemplate(self::PRIVATE_KEY_FILL, ['privateKey' => $privateKey]);
    }

    public static function getAutoFillOrderTypeField($orderType)
    {
        return TemplateService::buildTemplate(self::ORDER_TYPE_FILL, ['orderType' => $orderType]);
    }
    public static function getAutoFillFieldsScript($fillVarsJSON)
    {
        return TemplateService::buildTemplate(self::AUTO_FILL, ['fillVars' => addslashes($fillVarsJSON)]);
    }

    public static function getSanEmailsScript(
        $apiSanEmailsJSON,
        $fillVarsJSON = null,
        $brand = null,
        $disabledValidationMethods = []
    ) {
        return TemplateService::buildTemplate(
            self::SAN_EMAILS,
            [
                'sanEmails' => addslashes($apiSanEmailsJSON),
                'fillVars' => addslashes($fillVarsJSON),
                'brand' => addslashes($brand),
                'disabledValidationMethods' => $disabledValidationMethods
            ]
        );
    }

    public static function getAdminServiceScript($vars)
    {
        return TemplateService::buildTemplate(self::ADMIN_SERVICE, $vars);
    }
    
    public static function getConfigOptionErrorScript($error)
    {
        return TemplateService::buildTemplate(self::OPTION_ERROR, ['error' => $error]);
    }
}
