<?php

/* * ********************************************************************
 * DiscountCenter product developed. (2015-11-17)
 * *
 *
 *  CREATED BY MODULESGARDEN       ->       http://modulesgarden.com
 *  CONTACT                        ->       contact@modulesgarden.com
 *
 *
 * This software is furnished under a license and may be used and copied
 * only  in  accordance  with  the  terms  of such  license and with the
 * inclusion of the above copyright notice.  This software  or any other
 * copies thereof may not be provided or otherwise made available to any
 * other person.  No title to and  ownership of the  software is  hereby
 * transferred.
 *
 *
 * ******************************************************************** */

namespace MGModule\RealtimeRegisterSsl\models\whmcs\emails;

use \MGModule\RealtimeRegisterSsl\mgLibs\MySQL\PdoWrapper;

/**
 * Description of Repository
 *
 * @author Pawel Kopec <pawelk@modulesgarden.com>
 */
class Templates extends \MGModule\RealtimeRegisterSsl\mgLibs\models\Repository
{
    public function getModelClass()
    {
        return __NAMESPACE__ . '\Template';
    }

    /**
     *
     * @return Template[]
     */
    public function get()
    {
        return parent::get();
    }

    /**
     *
     * @return Template
     */
    public function fetchOne()
    {
        return parent::fetchOne();
    }

    /**
     *
     * @return \MGModule\RealtimeRegisterSsl\models\whmcs\emails\Templates
     */
    public function onlyGeneral()
    {
        $this->_filters['type'] = "general";
        return $this;
    }

    /**
     *
     * @return \MGModule\RealtimeRegisterSsl\models\whmcs\emails\Templates
     */
    public function onlyAdmin()
    {
        $this->_filters['type'] = "admin";
        return $this;
    }

    /**
     *
     * @param string $name
     * @return \MGModule\RealtimeRegisterSsl\models\whmcs\emails\Templates
     */
    public function onlyName($name)
    {
        $this->_filters['name'] = PdoWrapper::realEscapeString($name);
        return $this;
    }
}
