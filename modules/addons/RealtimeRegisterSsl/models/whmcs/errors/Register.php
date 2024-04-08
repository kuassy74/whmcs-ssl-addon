<?php

namespace MGModule\RealtimeRegisterSsl\models\whmcs\errors;
use MGModule\RealtimeRegisterSsl as main;

/**
 * Register Error in WHMCS Module Log
 *
 * @author Michal Czech <michael@modulesgarden.com>
 * @SuppressWarnings(PHPMD)
 */
class Register extends main\mgLibs\models\Orm {
    /**
     * Register Exception in WHMCS Module Log
     * 
     * @author Michal Czech <michael@modulesgarden.com>
     * @param Exception $ex
     */
    static function register($ex){
        $token = 'Unknow Token';

        if(method_exists($ex, 'getToken'))
        {
            $token = $ex->getToken();
        }

        $debug = print_r($ex,true);

        \logModuleCall("MGError",__NAMESPACE__,array(
                    'message' => $ex->getMessage()
                    ,'code'   => $ex->getCode()
                    ,'token'  => $token
                ),  $debug,0,0);
    }
}
