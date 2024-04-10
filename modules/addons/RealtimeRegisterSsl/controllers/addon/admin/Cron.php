<?php

namespace MGModule\RealtimeRegisterSsl\controllers\addon\admin;

use MGModule\RealtimeRegisterSsl\eHelpers\Whmcs;
use MGModule\RealtimeRegisterSsl\eProviders\ApiProvider;
use MGModule\RealtimeRegisterSsl\eRepository\RealtimeRegisterSsl\Products;
use MGModule\RealtimeRegisterSsl\eRepository\whmcs\service\SSL;
use MGModule\RealtimeRegisterSsl\eServices\EmailTemplateService;
use MGModule\RealtimeRegisterSsl\eServices\provisioning\ConfigOptions as C;
use Illuminate\Database\Capsule\Manager as Capsule;
use MGModule\RealtimeRegisterSsl\mgLibs\process\AbstractController;
use WHMCS\Service\Service;

class Cron extends AbstractController
{
    private $sslRepo = null;

    public function indexCRON($input, $vars = array())
    {

        $updatedServices = [];

        $this->sslRepo = new SSL();

        //get all completed ssl orders
        $sslOrders = $this->getSSLOrders();

        foreach ($sslOrders as $sslService)
        {
            $serviceID = $sslService->serviceid;

            if(!isset($sslService->remoteid) || empty($sslService->remoteid))
            {
                continue;
            }

            if($sslService->status != 'Awaiting Configuration')
            {
                $configdata = json_decode($sslService->configdata, true);
                if(isset($configdata['domain']) && !empty($configdata['domain']))
                {
                    Capsule::table('tblhosting')->where('id', $serviceID)->update(['domain' => $configdata['domain']]);
                }
            }

            //if service is synchronized skip it
            if ($this->checkIfSynchronized($serviceID))
                continue;

            //set ssl certificate as synchronized
            $this->setSSLServiceAsSynchronized($serviceID);

            try{
                $order = ApiProvider::getInstance()->getApi()->getOrderStatus($sslService->remoteid);
            } catch (\Exception $e) {
                continue;
            }

            $service = (array)Capsule::table('tblhosting')->where('id', $serviceID)->first();
            $product = (array)Capsule::table('tblproducts')->where('servertype', 'RealtimeRegisterSsl')->where('id', $service['packageid'])->first();

            if(isset($product['configoption7']) && !empty($product['configoption7']) && $service['billingcycle'] == 'One Time')
            {
                Capsule::table('tblhosting')->where('id', $serviceID)->update(array('termination_date' => $order['valid_till']));
            }

            if ($order['status'] == 'expired' || $order['status'] == 'cancelled')
            {
                $this->setSSLServiceAsTerminated($serviceID);
                $updatedServices[] = $serviceID;
            }

            //if certificate is active

            if ($order['status'] == 'active')
            {
                //update whmcs service next due date
                $newNextDueDate = $order['valid_till'];
                if(!empty($order['end_date']))
                {
                    $newNextDueDate = $order['end_date'];
                }

                //set ssl certificate as terminated if expired
                if (strtotime($order['valid_till']) < strtotime(date('Y-m-d')))
                {
                    $this->setSSLServiceAsTerminated($serviceID);
                }

                //if service is montlhy, one time, free skip it
                if ($this->checkServiceBillingPeriod($serviceID))
                    continue;

                $this->updateServiceNextDueDate($serviceID, $newNextDueDate);

                $updatedServices[] = $serviceID;
            }
        }
        echo 'Synchronization completed.';
        echo '<br />Number of synchronized services: ' . count($updatedServices);

        Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Synchronization completed. Number of synchronized services: " . count($updatedServices));

        return array();
    }

    public function notifyCRON($input, $vars = array())
    {
        //get renewal settings
        $apiConf                      = (new \MGModule\RealtimeRegisterSsl\models\apiConfiguration\Repository())->get();
        $auto_renew_invoice_one_time  = (bool) $apiConf->auto_renew_invoice_one_time;
        $auto_renew_invoice_reccuring = (bool) $apiConf->auto_renew_invoice_reccuring;
        $renew_new_order              = (bool) $apiConf->renew_new_order;
        //get saved amount days to generate invoice (one time & reccuring)
        $renew_invoice_days_one_time  = $apiConf->renew_invoice_days_one_time;
        $renew_invoice_days_reccuring = $apiConf->renew_invoice_days_reccuring;

        $send_expiration_notification_reccuring = (bool) $apiConf->send_expiration_notification_reccuring;
        $send_expiration_notification_one_time  = (bool) $apiConf->send_expiration_notification_one_time;

        $this->sslRepo = new SSL();

        //get all completed ssl orders
        $sslOrders       = $this->getSSLOrders();

        $synchServicesId = [];
        foreach($sslOrders as $row)
        {
            $config = json_decode($row->configdata);
            if (isset($config->synchronized))
            {
                $synchServicesId[] = $row->serviceid;
            }
            else
            {
                $serviceonetime = Service::where('id', $row->serviceid)->where('billingcycle', 'One Time')->first();
                if(isset($serviceonetime->id))
                {
                    $synchServicesId[] = $serviceonetime->id;
                }
            }
        }

        if(!empty($synchServicesId))
        {
            $services = Service::whereIn('id', $synchServicesId)->get();
        }
        else
        {
            $services = [];
        }

        $emailSendsCount = 0;
        $emailSendsCountReissue = 0;

        $packageLists = [];
        $serviceIDs   = [];

        foreach ($synchServicesId as $serviceid)
        {
            $srv = Capsule::table('tblhosting')->where('id', $serviceid)->first();

            //get days left to expire from WHMCS
            $daysLeft         = $this->checkOrderExpireDate($srv->nextduedate);
            $daysReissue         = $this->checkReissueDate($srv->id);

            //if service is One Time and nextduedate is setted as 0000-00-00 get valid_till from Realtime Register Ssl API

            if ($srv->billingcycle == 'One Time')
            {
                $sslOrder = Capsule::table('tblsslorders')->where('serviceid', $srv->id)->first();

                if(isset($sslOrder->remoteid) && !empty($sslOrder->remoteid)) {

                    $order    = ApiProvider::getInstance()->getApi()->getOrderStatus($sslOrder->remoteid);
                    $daysLeft = $this->checkOrderExpireDate($order['valid_till']);

                }
            }

            $product = Capsule::table('tblproducts')->where('id', $srv->packageid)->first();

            if($srv->domainstatus == 'Active' && $daysReissue == '30' && $product->configoption2 > 12)
            {
                // send email
                $emailSendsCountReissue += $this->sendReissueNotfiyEmail($srv->id);
            }

            //service was synchronized, so we can base on nextduedate, that should be the same as valid_till
            //$daysLeft = 90;
            if ($daysLeft >= 0)
            {
                if ($srv->billingcycle == 'One Time' && $send_expiration_notification_one_time || $srv->billingcycle != 'One Time' && $send_expiration_notification_reccuring)
                    $emailSendsCount += $this->sendExpireNotfiyEmail($srv->id, $daysLeft);
            }

            $savedRenewDays = $renew_invoice_days_reccuring;
            if ($srv->billingcycle == 'One Time')
            {
                $savedRenewDays = $renew_invoice_days_one_time;
            }
            //if it is proper amount of days before expiry, we create invoice
            if ($daysLeft == (int) $savedRenewDays)
            {
                if ($srv->billingcycle == 'One Time' && $auto_renew_invoice_one_time || $srv->billingcycle != 'One Time' && $auto_renew_invoice_reccuring)
                {
                    $packageLists[$srv->packageid][] = $srv;
                    $serviceIDs[]                    = $srv->id;
                }
            }
        }

        if(!$renew_new_order)
        {
            $invoicesCreatedCount = $this->createAutoInvoice($packageLists, $serviceIDs);

            $invoices = Capsule::table('tblinvoices')->where('status', 'Payment Pending')->get();
            foreach($invoices as $invoice)
            {
                $itemsInvoice = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoice->id)->where('description', 'LIKE', '%- Renewal')->get();

                if(!empty($itemsInvoice))
                {
                    $sslInvoice = Capsule::table(\MGModule\RealtimeRegisterSsl\eHelpers\Invoice::INVOICE_INFOS_TABLE_NAME)->where('invoice_id', $invoice->id)->first();

                    $serviceid = $sslInvoice->service_id;

                    $sslInfo = $this->getSSLOrders($serviceid)[0];
                    $sslOrder    = ApiProvider::getInstance()->getApi()->getOrderStatus($sslInfo->remoteid);

                    $today = (string)date('Y-m-d');

                    if($sslOrder['valid_till'] == $today)
                    {
                        Capsule::table('tblinvoices')->where('id', $invoice->id)->update(array('status' => 'Cancelled'));
                    }
                }
            }
        }

        echo 'Notifier completed.' . PHP_EOL;
        echo '<br />Number of emails send (expire): ' . $emailSendsCount . PHP_EOL;
        echo '<br />Number of emails send (reissue): ' . $emailSendsCountReissue . PHP_EOL;

        logActivity('Notifier completed. Number of emails send: '.$emailSendsCount, 0);

        if(!$renew_new_order)
        {
            echo '<br />Number of invoiced created: ' . $invoicesCreatedCount . PHP_EOL;
        }

        Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Notifier completed. Number of emails send: " . $emailSendsCount);

        if(!$renew_new_order)
        {
            Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Notifier completed. Number of invoiced created: " . $invoicesCreatedCount);
        }

        return array();
    }

    public function certificateSendCRON($input, $vars = array())
    {
        echo 'Certificate Sender started.' . PHP_EOL;
        Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Certificate Sender started.");

        $emailSendsCount = 0;
        $this->sslRepo   = new SSL();

        $services = new \MGModule\RealtimeRegisterSsl\models\whmcs\service\Repository();
        $services->onlyStatus(['Active']);

        $servicesArray = [];
        foreach ($services->get() as $service)
        {
            $apiOrders = null;
            $product   = $service->product();
            //check if product is Realtime Register Ssl
            if ($product->serverType != 'RealtimeRegisterSsl')
            {
                continue;
            }

            $SSLOrder = new \MGModule\RealtimeRegisterSsl\eModels\whmcs\service\SSL();
            $ssl      = $SSLOrder->getWhere(array('serviceid' => $service->id, 'userid' => $service->clientID))->first();

            if ($ssl == NULL || $ssl->remoteid == '')
            {
                continue;
            }
            $apiOrder = ApiProvider::getInstance()->getApi()->getOrderStatus($ssl->remoteid);
            if ($apiOrder['status'] !== 'active' || empty($apiOrder['ca_code']))
            {
                continue;
            }

            if ($this->checkIfCertificateSent($service->id))
                continue;

            $apiConf                  = (new \MGModule\RealtimeRegisterSsl\models\apiConfiguration\Repository())->get();
            $sendCertyficateTermplate = $apiConf->send_certificate_template;
            if ($sendCertyficateTermplate == NULL)
            {
                sendMessage(EmailTemplateService::SEND_CERTIFICATE_TEMPLATE_ID, $service->id, [
                    'ssl_certyficate' => nl2br($apiOrder['ca_code']),
                    'crt_code' => nl2br($apiOrder['crt_code']),
                ]);
            }
            else
            {
                $templateName = EmailTemplateService::getTemplateName($sendCertyficateTermplate);
                sendMessage($templateName, $service->id, [
                    'ssl_certyficate' => nl2br($apiOrder['ca_code']),
                    'crt_code' => nl2br($apiOrder['crt_code']),
                ]);
            }
            $this->setSSLCertificateAsSent($service->id);
            $emailSendsCount++;
        }

        echo 'Certificate Sender completed.' . PHP_EOL;
        echo '<br />The number of messages sent: ' . $emailSendsCount . PHP_EOL;

        Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Certificate Sender completed. The number of messages sent: " . $emailSendsCount);
        return array();
    }

    public function certificateDetailsUpdateCRON($input, $vars = array())
    {
        echo 'Certificate Details Updating.' . PHP_EOL;
        Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Certificate Details Updating started.");

        $this->sslRepo = new SSL();

        $checkTable = Capsule::schema()->hasTable(Products::MGFW_REALTIMEREGISTERSSL_PRODUCT_BRAND);
        if($checkTable === false)
        {
            Capsule::schema()->create(Products::MGFW_REALTIMEREGISTERSSL_PRODUCT_BRAND, function ($table) {
                $table->increments('id');
                $table->integer('pid');
                $table->string('brand');
                $table->text('data');
            });
        }

        if (!Capsule::schema()->hasColumn(Products::MGFW_REALTIMEREGISTERSSL_PRODUCT_BRAND, 'data'))
        {
            Capsule::schema()->table(Products::MGFW_REALTIMEREGISTERSSL_PRODUCT_BRAND, function($table)
            {
                $table->text('data');
            });
        }

        Capsule::table(Products::MGFW_REALTIMEREGISTERSSL_PRODUCT_BRAND)->truncate();

        $apiProducts = ApiProvider::getInstance()->getApi()->getProducts();

        foreach ($apiProducts['products'] as $apiProduct) {
            Capsule::table(Products::MGFW_REALTIMEREGISTERSSL_PRODUCT_BRAND)->insert(array(
                'pid' => $apiProduct['id'],
                'brand' => $apiProduct['brand'],
                'data' => json_encode($apiProduct)
            ));
        }

        $sslOrders = $this->getSSLOrders();

        foreach ($sslOrders as $sslService)
        {

            $sslService = \MGModule\RealtimeRegisterSsl\eModels\whmcs\service\SSL::hydrate(array($sslService))[0];

            $configDataUpdate = new \MGModule\RealtimeRegisterSsl\eServices\provisioning\UpdateConfigData($sslService);
            $configDataUpdate->run();
        }

        echo '<br/ >';
        echo 'Certificate Details Updating completed.' . PHP_EOL;
        Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Certificate Details Updating completed.");
        return array();
    }

    public function loadCertificateStatsCRON($input, $vars = array())
    {
        echo 'Certificate Stats Loader started.' . PHP_EOL;
        Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Certificate Stats Loader started.");

        $emailSendsCount = 0;
        $this->sslRepo   = new SSL();

        $services = new \MGModule\RealtimeRegisterSsl\models\whmcs\service\Repository();
        $services->onlyStatus(['Active', 'Suspended']);

        $servicesArray = [];
        foreach ($services->get() as $service)
        {
            $apiOrders = null;
            $product   = $service->product();
            //check if product is Realtime Register Ssl
            if ($product->serverType != 'RealtimeRegisterSsl')
            {
                continue;
            }

            $SSLOrder = new \MGModule\RealtimeRegisterSsl\eModels\whmcs\service\SSL();
            $ssl      = $SSLOrder->getWhere(array('serviceid' => $service->id, 'userid' => $service->clientID))->first();

            if ($ssl == NULL || $ssl->remoteid == '')
            {
                continue;
            }
            $apiOrder = ApiProvider::getInstance()->getApi()->getOrderStatus($ssl->remoteid);

            $this->setSSLCertificateValidTillDate($service->id, $apiOrder['valid_till']);
            $this->setSSLCertificateStatus($service->id, $apiOrder['status']);
        }
        echo '<br/ >';
        echo 'Certificate Stats Loader completed.' . PHP_EOL;
        Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Certificate Stats Loader completed.");
        return array();
    }

    public function updateProductPricesCRON($input, $vars = array())
    {
        echo 'Products Price Updater started.' . PHP_EOL;
        Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Products Price Updater started.");

        try
        {
            //get all products prices
            $apiProductsPrices = \MGModule\RealtimeRegisterSsl\eRepository\RealtimeRegisterSsl\ProductsPrices::getInstance();

            foreach ($apiProductsPrices->getAllProductsPrices() as $productPrice)
            {
                $productPrice->saveToDatabase();
            }

            $productModel = new \MGModule\RealtimeRegisterSsl\models\productConfiguration\Repository();
            //get RealtimeRegisterSsl all products
            $products     = $productModel->getModuleProducts();

            foreach ($products as $product)
            {
                //if auto price not enabled skip product
                if (!$product->{C::PRICE_AUTO_DOWNLOAD})
                    continue;


                //load saved api price
                $apiPrice = $productPrice->loadSavedPriceData($product->{C::API_PRODUCT_ID});
                //generate new price
                $this->generateNewPricesBasedOnAPI($product->pricing, $apiPrice);
            }
        }
        catch (\Exception $e)
        {
            Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS Products Price Updater Error: " . $e->getMessage());
        }

        echo '<br/ >';
        echo 'Products Price Updater completed.' . PHP_EOL;
        Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Products Price Updater completed.");
        return array();
    }
    private function checkOrdersStatus($sslorders, $processingOnly = false)
    {
        $cids = [];
        foreach ($sslorders as $sslService) {
            $cids[] = $sslService->remoteid;
        }
        try
        {

            $cids = implode(',', $cids);

            $configDataUpdate = new \MGModule\RealtimeRegisterSsl\eServices\provisioning\UpdateConfigs($cids, $processingOnly);
            $configDataUpdate->run();

        }
        catch (\Exception $e)
        {
            Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS Products Price Updater Error: " . $e->getMessage());
        }
    }
    public function dailyStatusCheckCRON($input, $vars = array())
    {
        echo 'Certificates (ssl status Completed) Data Updater started.' . PHP_EOL;
        $this->sslRepo = new SSL();
        $sslorders = Capsule::table('tblhosting')
        ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
        ->join('tblsslorders', 'tblsslorders.serviceid', '=', 'tblhosting.id')
        ->where('tblhosting.domainstatus', 'Active')
        ->whereIn('tblsslorders.status', ['Completed', 'Configuration Submitted'])
        ->get(['tblsslorders.*']);

        Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Certificates (ssl status Completed) Data Updater started.");

        $this->checkOrdersStatus($sslorders);

        echo '<br/ >';
        echo 'Certificates (ssl status Completed) Data Updater completed.' . PHP_EOL;
        Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Certificates (ssl status Completed) Data Updater completed.");
        return array();
    }
    public function processingOrdersCheckCRON($input, $vars = array())
    {
        echo 'Certificates (ssl status Processing) Data Updater started.' . PHP_EOL;
        $this->sslRepo = new SSL();
        $sslorders = Capsule::table('tblhosting')
        ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
        ->join('tblsslorders', 'tblsslorders.serviceid', '=', 'tblhosting.id')
        ->where('tblhosting.domainstatus', 'Active')
        ->where('tblsslorders.configdata', 'like', '%"ssl_status":"processing"%')
        ->get(['tblsslorders.*']);

        Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Certificates (ssl status Processing) Data Updater started.");

        $this->checkOrdersStatus($sslorders, true);

        echo '<br/ >';
        echo 'Certificates (ssl status Processing) Data Updater completed.' . PHP_EOL;
        Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Certificates (ssl status Processing) Data Updater completed.");
        return array();
    }
    private function generateNewPricesBasedOnAPI($currentPrices, $apiPrices)
    {
        $apiConf = (new \MGModule\RealtimeRegisterSsl\models\apiConfiguration\Repository())->get();
        $rate  = (float)$apiConf->rate;

        if(empty($rate))
        {
            $rate = 1;
        }

        foreach ($currentPrices as $price)
        {
            $currency = $price->currency;

            $monthly      = (in_array($price->monthly, array('-1.00'))) ? $price->monthly : $rate*$this->generateNewPrice('12', $apiPrices, $currency);
            $quarterly    = (in_array($price->quarterly, array('-1.00'))) ? $price->quarterly : $rate*$this->generateNewPrice('3', $apiPrices, $currency);
            $semiannually = (in_array($price->semiannually, array('-1.00'))) ? $price->semiannually : $rate*$this->generateNewPrice('6', $apiPrices, $currency);
            $annually     = (in_array($price->annually, array('-1.00'))) ? $price->annually : $rate*$this->generateNewPrice('12', $apiPrices, $currency);
            $biennially   = (in_array($price->biennially, array('-1.00'))) ? $price->biennially : $rate*$this->generateNewPrice('24', $apiPrices, $currency);
            $triennially  = (in_array($price->triennially, array('-1.00'))) ? $price->triennially : $rate*$this->generateNewPrice('36', $apiPrices, $currency);

            //save new pricing
            Capsule::table("tblpricing")
                    ->where("id", "=", $price->pricing_id)
                    ->where("type", "=", 'product')
                    ->where("relid", "=", $price->relid)
                    ->update(array(
                        'monthly'      => $monthly,
                        'quarterly'    => $quarterly,
                        'semiannually' => $semiannually,
                        'annually'     => $annually,
                        'biennially'   => $biennially,
                        'triennially'  => $triennially));
        }
    }

    private function generateNewPrice($period, $apiPrices, $priceCurrency)
    {
        /* if(in_array($price, array('-1.00', '0.00')))
          return $price; */
        $productModel = new \MGModule\RealtimeRegisterSsl\models\productConfiguration\Repository();
        //get all currenccies
        $currencies   = $productModel->getAllCurrencies();

        $newPrice = NULL;
        foreach ($currencies as $curr)
        {
            if ($priceCurrency != $curr->id)
                continue;

            foreach ($apiPrices as $apiPrice)
            {
                if ($apiPrice->period != $period)
                    continue;

                $price = $apiPrice->price;
            }

            if ($curr->default == '1')
            {
                $newPrice = $price;
            }
            else
            {   //exchange based on rate
                $newPrice = (float) $price * $curr->rate;
            }
        }

        return $newPrice;
    }

    private function getSSLOrders($serviceID = null)
    {
        $where = [
            'status' => 'Completed',
            'module' => 'RealtimeRegisterSsl'
        ];

        if ($serviceID != NULL)
            $where['serviceid'] = $serviceID;

        return $this->sslRepo->getBy($where, true);
    }

    private function updateServiceNextDueDate($serviceID, $date)
    {
        $service = Service::find($serviceID);
        if (!empty($service))
        {
            $createInvoiceDaysBefore = Capsule::table("tblconfiguration")->where('setting', 'CreateInvoiceDaysBefore')->first();
            $service->nextduedate = $date;
            $nextinvoicedate = date('Y-m-d', strtotime("-{$createInvoiceDaysBefore->value} day", strtotime($date)));
            $service->nextinvoicedate = $nextinvoicedate;
            $service->save();

            Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Service #$serviceID nextduedate set to ".$date." and nextinvoicedate to". $nextinvoicedate);
        }
    }

    private function setSSLServiceAsSynchronized($serviceID)
    {
        $sslService = $this->sslRepo->getByServiceId((int) $serviceID);
        $sslService->setConfigdataKey('synchronized', date('Y-m-d'));
        $sslService->save();
    }

    private function setSSLServiceAsTerminated($serviceID)
    {
        $service = Service::find($serviceID);
        if (!empty($service))
        {
            $service->status = 'terminated';
            $service->save();

            Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register Ssl WHMCS: Service #$serviceID set as Terminated");
        }
    }

    private function checkIfSynchronized($serviceID)
    {
        $result     = false;
        $sslService = $this->sslRepo->getByServiceId((int) $serviceID);

        $date = date('Y-m-d');
        $date = strtotime("-5 day", strtotime($date));

        if (strtotime($sslService->getConfigdataKey('synchronized')) > $date)
        {
            $result = true;
        }

        return $result;
    }

    public function checkIfCertificateSent($serviceID)
    {
        $result        = false;
        if ($this->sslRepo == NULL)
            $this->sslRepo = new SSL();

        $sslService = $this->sslRepo->getByServiceId((int) $serviceID);
        if ($sslService->getConfigdataKey('certificateSent'))
        {
            $result = true;
        }

        return $result;
    }

    public function setSSLCertificateAsSent($serviceID)
    {
        if ($this->sslRepo == NULL)
            $this->sslRepo = new SSL();
        $sslService    = $this->sslRepo->getByServiceId((int) $serviceID);
        $sslService->setConfigdataKey('certificateSent', true);
        $sslService->save();
    }

    private function setSSLCertificateValidTillDate($serviceID, $date)
    {
        $sslService = $this->sslRepo->getByServiceId((int) $serviceID);
        $sslService->setConfigdataKey('valid_till', $date);
        $sslService->save();
    }

    private function setSSLCertificateStatus($serviceID, $status)
    {
        $sslService = $this->sslRepo->getByServiceId((int) $serviceID);
        $sslService->setConfigdataKey('ssl_status', $status);
        $sslService->save();
    }

    private function checkServiceBillingPeriod($serviceID)
    {
        $skipPeriods = ['Monthly', 'One Time', 'Free Account'];
        $skip        = false;
        $service     = Service::find($serviceID);

        if (in_array($service->billingcycle, $skipPeriods) || $service == null)
        {
            $skip = true;
        }

        return $skip;
    }

    public function checkReissueDate($serviceid)
    {
        $sslOrder = Capsule::table('tblsslorders')->where('serviceid', $serviceid)->first();

        if(isset($sslOrder->configdata) && !empty($sslOrder->configdata)){

            $configdata = json_decode($sslOrder->configdata, true);

            if(isset($configdata['end_date']) && !empty($configdata['end_date']))
            {
                $now = strtotime(date('Y-m-d'));
                $end_date = strtotime($configdata['valid_till']);
                $datediff = $now - $end_date;

                $nextReissue = abs(round($datediff / (60 * 60 * 24)));
                return $nextReissue;
            }
        }
        return false;
    }

    public function checkOrderExpireDate($expireDate)
    {
        $expireDaysNotify = array_flip(array('90', '60', '30', '15', '10', '7', '3', '1', '0'));

        if (stripos($expireDate, ':') === false)
        {
            $expireDate .= ' 23:59:59';
        }
        $expire = new \DateTime($expireDate);
        $today  = new \DateTime();

        $diff = $expire->diff($today, false);
        if ($diff->invert == 0)
        {
            //if date from past
            return -1;
        }

        return isset($expireDaysNotify[$diff->days]) ? $diff->days : -1;
    }

    public function sendExpireNotfiyEmail($serviceId, $daysLeft)
    {
        $command = 'SendEmail';

        $postData = array(
            'id'          => $serviceId,
            'messagename' => EmailTemplateService::EXPIRATION_TEMPLATE_ID,
            'customvars'  => base64_encode(serialize(array("expireDaysLeft" => $daysLeft))),
        );

        $adminUserName = \MGModule\RealtimeRegisterSsl\eHelpers\Admin::getAdminUserName();

        $results = localAPI($command, $postData, $adminUserName);

        $resultSuccess = $results['result'] == 'success';
        if (!$resultSuccess)
        {
            Whmcs::savelogActivityRealtimeRegisterSsl('Realtime Register Ssl WHMCS Notifier: Error while sending customer notifications (service ' . $serviceId . '): ' . $results['message'], 0);
        }
        return $resultSuccess;
    }

    public function sendReissueNotfiyEmail($serviceId)
    {
        $command = 'SendEmail';

        $postData = array(
            'serviceid'          => $serviceId,
            'messagename' => EmailTemplateService::REISSUE_TEMPLATE_ID,
        );

        $adminUserName = \MGModule\RealtimeRegisterSsl\eHelpers\Admin::getAdminUserName();

        $results = localAPI($command, $postData, $adminUserName);

        $resultSuccess = $results['result'] == 'success';
        if (!$resultSuccess)
        {
            Whmcs::savelogActivityRealtimeRegisterSsl('Realtime Register Ssl WHMCS Notifier: Error while sending customer notifications (service ' . $serviceId . '): ' . $results['message'], 0);
        }
        return $resultSuccess;
    }

    public function createAutoInvoice($packages, $serviceIds, $jsonAction = false)
    {
        if (empty($packages))
        {
            return 0;
        }

        $products             = \WHMCS\Product\Product::whereIn('id', array_keys($packages))->get();
        $invoiceGenerator     = new \MGModule\RealtimeRegisterSsl\eHelpers\Invoice();
        $servicesAlreadyAdded = $invoiceGenerator->checkInvoiceAlreadyCreated($serviceIds);
        $getInvoiceID         = false;
        if ($jsonAction)
        {
            $getInvoiceID = true;
        }
        $invoiceCounter = 0;
        foreach ($products as $prod)
        {

            foreach ($packages[$prod->id] as $service)
            {
                //have product, service                
                if (isset($servicesAlreadyAdded[$service->id]))
                {
                    if ($jsonAction)
                    {
                        return array('invoiceID' => $invoiceGenerator->getLatestCreatedInvoiceInfo($service->id)['invoice_id']);
                    }
                    continue;
                }
                $invoiceCounter += $invoiceGenerator->createInvoice($service, $prod, $getInvoiceID);
            }
        }

        return $invoiceCounter;
    }
}
