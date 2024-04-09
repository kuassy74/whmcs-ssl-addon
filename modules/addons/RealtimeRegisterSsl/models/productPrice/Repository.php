<?php

namespace MGModule\RealtimeRegisterSsl\models\productPrice;

use Illuminate\Database\Capsule\Manager as Capsule;


/**
 * Description of repository
 *
 * @author Michal Czech <michael@modulesgarden.com>
 */
class Repository extends \MGModule\RealtimeRegisterSsl\mgLibs\models\Repository
{
    public $tableName = 'mgfw_REALTIMEREGISTERSSL_api_product_prices';

    public function getModelClass()
    {
        return __NAMESPACE__ . '\ProductPrice';
    }
    
    /**
     *
     * @return ProductPrices[]
     */
    public function get() {
        return parent::get();
    }

    /**
     *
     * @return ProductPrices
     */
    public function fetchOne() {
        return parent::fetchOne();
    }

    public function onlyApiProductID($id)
    {
        $this->_filters['api_product_id'] = $id;
        return $this;
    }
    
    public function onlyPeriod($period)
    {
        $this->_filters['period'] = $period;
        return $this;
    }
    
    public function createApiProductsPricesTable()
    {
        if (!Capsule::schema()->hasTable($this->tableName))
        {
            Capsule::schema()->create($this->tableName, function($table)
            {
                $table->increments('id');
                $table->string('api_product_id');
                $table->string('price');
                $table->string('period');
            });
        }
    }

    public function updateApiProductsPricesTable()
    {
        if (!Capsule::schema()->hasTable($this->tableName))
        {
            Capsule::schema()->create($this->tableName, function($table)
            {
                $table->increments('id');
                $table->string('api_product_id');
                $table->string('price');
                $table->string('period');
            });
        }
    }

    public function dropApiProductsPricesTable()
    {
        if (Capsule::schema()->hasTable($this->tableName))
        {
            Capsule::schema()->dropIfExists($this->tableName);
        }
    }
}
