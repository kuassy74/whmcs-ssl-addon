<?php

namespace MGModule\RealtimeRegisterSsl\eHelpers;

use MGModule\RealtimeRegisterSsl\eServices\provisioning\ConfigOptions as C;

class Commission
{

    public static function getCommissionValue($vars)
    {
        $return       = [];
        //load module products
        $products     = array();
        $productModel = new \MGModule\RealtimeRegisterSsl\models\productConfiguration\Repository();
        
        $client = NULL;
        if(isset($_SESSION['uid']))
            $client = $_SESSION['uid'];
        
        if(isset($vars['client']))
            $client = $vars['client'];

        $clientCurrency = getCurrency($client);
        
        //get Realtime Register Ssl all products
        foreach ($productModel->getModuleProducts() as $product)
        {
            if ($product->id == $vars['pid'])
            {
                $commission = NULL;
                //skip free products
                if($product->paytype == 'free')
                    return $commission;
                
                if ((float) $product->{C::COMMISSION} > 0)
                {
                    $commission = (float) $product->{C::COMMISSION};
                }
                if ($client != NULL)
                {
                    $commissionRepo = new \MGModule\RealtimeRegisterSsl\models\userCommission\Repository();
                    $commissionRepo->onlyClientID($client);
                    $commissionRepo->onlyProductID($product->id);
                    $rules          = $commissionRepo->get();

                    if (!empty($rules = $commissionRepo->get()))
                    {
                        $commission = $rules[0]->getCommission();
                    }
                }
            }
        }

        return $commission;
    }
}
