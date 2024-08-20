<?php

namespace MGModule\RealtimeRegisterSsl\eServices;

use Illuminate\Database\Capsule\Manager as Capsule;
use MGModule\RealtimeRegisterSsl\eHelpers\Whmcs;
use MGModule\RealtimeRegisterSsl\eRepository\RealtimeRegisterSsl\KeyToIdMapping;
use MGModule\RealtimeRegisterSsl\eRepository\RealtimeRegisterSsl\ProductsPrices;
use MGModule\RealtimeRegisterSsl\models\productPrice\Repository as ApiProductPriceRepo;

class ConfigurableOptionService
{
    public static function getForProduct($productId, $name = 'sans_count')
    {
        return Capsule::table('tblproductconfiggroups')
            ->select(['tblproductconfigoptions.id', 'tblproductconfiggroups.id as groupid'])
            ->join('tblproductconfigoptions', 'tblproductconfigoptions.gid', '=', 'tblproductconfiggroups.id')
            ->where(
                'tblproductconfiggroups.description',
                '=',
                'Auto generated by module - RealtimeRegisterSSL #' . $productId
            )
            ->where('tblproductconfigoptions.optionname', 'LIKE', $name . '%')
            ->first();
    }

    public static function getForProductWildcard($productId)
    {
        return Capsule::table('tblproductconfiggroups')
            ->select(['tblproductconfigoptions.id', 'tblproductconfiggroups.id as groupid'])
            ->join('tblproductconfigoptions', 'tblproductconfigoptions.gid', '=', 'tblproductconfiggroups.id')
            ->where(
                'tblproductconfiggroups.description',
                '=',
                'Auto generated by module - RealtimeRegisterSSL #' . $productId
            )
            ->where('tblproductconfigoptions.optionname', 'LIKE', 'sans_wildcard_count%')
            ->first();
    }

    public static function getForProductPeriod($productId)
    {
        return Capsule::table('tblproductconfiggroups')
            ->select(['tblproductconfigoptions.id', 'tblproductconfiggroups.id as groupid'])
            ->join('tblproductconfigoptions', 'tblproductconfigoptions.gid', '=', 'tblproductconfiggroups.id')
            ->where(
                'tblproductconfiggroups.description',
                '=',
                'Auto generated by module - RealtimeRegisterSSL #' . $productId
            )
            ->where('tblproductconfigoptions.optionname', 'LIKE', 'years%')
            ->first();
    }

    public static function unassignFromProduct($productId, $name)
    {
        $optionGroup = [
            'name' => 'RealtimeRegisterSsl - ' . $name,
            'description' => 'Auto generated by module - RealtimeRegisterSSL #' . $productId
        ];

        $optionGroup = Capsule::table('tblproductconfiggroups')
            ->select('id')
            ->where('name', '=', $optionGroup['name'])
            ->where('description', '=', $optionGroup['description'])
            ->first();

        $optionLink = [
            'gid' => $optionGroup->id,
            'pid' => $productId
        ];

        Capsule::table('tblproductconfiglinks')
            ->where('gid', '=', $optionLink['gid'])
            ->where('pid', '=', $optionLink['pid'])
            ->delete();
    }

    public static function assignToProduct($productId, $name)
    {
        $optionGroup = [
            'name' => 'RealtimeRegisterSsl - ' . $name,
            'description' => 'Auto generated by module - RealtimeRegisterSSL #' . $productId
        ];

        $optionGroupResult = Capsule::table('tblproductconfiggroups')
            ->select('id')
            ->where('name', '=', $optionGroup['name'])
            ->where('description', '=', $optionGroup['description'])
            ->first();

        if ($optionGroupResult === null) {
            self::createForProduct($productId, $name);
            $optionGroupResult = self::getForProduct($productId);
        }

        $count = Capsule::table('tblproductconfiglinks')
            ->where('gid', '=', $optionGroupResult->id)
            ->where('pid', '=', $productId)
            ->count();

        if (!$count) {
            $optionLink = [
                'gid' => $optionGroupResult->id,
                'pid' => $productId
            ];
            Capsule::table('tblproductconfiglinks')->insert($optionLink);
        }
    }

    public static function unassignFromProductWildcard($productId, $groupid)
    {
        $optionGroup = Capsule::table('tblproductconfiggroups')
            ->select('id')
            ->where('id', '=', $groupid)
            ->first();

        $optionLink = [
            'gid' => $optionGroup->id,
            'pid' => $productId
        ];

        Capsule::table('tblproductconfiglinks')
            ->where('gid', '=', $optionLink['gid'])
            ->where('pid', '=', $optionLink['pid'])
            ->delete();
    }

    public static function assignToProductWildcard($productId, $name, $groupid)
    {
        $optionGroupResult = Capsule::table('tblproductconfiggroups')
            ->select('id')
            ->where('id', '=', $groupid)
            ->first();

        if ($optionGroupResult === null) {
            self::createForProduct($productId, $name);
            $optionGroupResult = self::getForProductWildcard($productId);
        }

        $count = Capsule::table('tblproductconfiglinks')
            ->where('gid', '=', $optionGroupResult->id)
            ->where('pid', '=', $productId)
            ->count();

        if (!$count) {
            $optionLink = [
                'gid' => $optionGroupResult->id,
                'pid' => $productId
            ];
            Capsule::table('tblproductconfiglinks')->insert($optionLink);
        }
    }

    public static function createForProduct($productId, $apiProductId, $name, $apiProduct)
    {
        if (!is_null(self::getForProduct($productId))) {
            return null;
        }

        $optionGroupId = self::getOptionGroup($name, $productId);

        self::insertOptions($apiProductId, $apiProduct, [
            "optionGroupId" => $optionGroupId,
            "optionName" => provisioning\ConfigOptions::OPTION_SANS_COUNT . "|Additional Single domain SANs",
            "action" => "EXTRA_DOMAIN",
            "maximum" => $apiProduct->getMaxDomains() - $apiProduct->getIncludedDomains()
        ]);
    }

    public static function createForProductWildCard($productId, $apiProductId, $name, $apiProduct)
    {
        if (!is_null(self::getForProduct($productId, 'wildcard_sans_count'))) {
            return;
        }

        $optionGroupId = self::getOptionGroup($name, $productId);

        self::insertOptions($apiProductId, $apiProduct, [
            "optionGroupId" => $optionGroupId,
            "optionName" => provisioning\ConfigOptions::OPTION_SANS_WILDCARD_COUNT . "|Additional Wildcard domain SANs",
            "action" => "EXTRA_WILDCARD",
            "maximum" => $apiProduct->getMaxDomains() - $apiProduct->getIncludedDomains()
        ]);
    }

    public static function insertPeriods($productId, $apiProductId, $name, array $periods)
    {
        if (!is_null(self::getForProduct($productId, 'years'))) {
            return;
        }

        $optionGroupId = self::getOptionGroup($name, $productId);
        $option = [
            'gid' => $optionGroupId,
            'optionname' => "years|Years",
            'optiontype' => 1,
            'order' => 1,
            'hidden' => 0,
        ];
        $optionId = Capsule::table('tblproductconfigoptions')->insertGetId($option);

        sort($periods);

        $priceRepo = new ApiProductPriceRepo();

        // The prices are sometimes not imported yet, so we force an import when there is no data
        self::loadPrices($priceRepo, $apiProductId);

        foreach($periods as $i => $period) {
            $price = $priceRepo->onlyApiProductID(KeyToIdMapping::getIdByKey($apiProductId))
                    ->onlyPeriod($period)
                    ->onlyAction("REQUEST")
                    ->fetchOne()
                    ->price / 100;
            $optionsSub = [
                'configid' => $optionId,
                'optionname' => $period / 12 . ' years',
                'sortorder' => $i,
                'hidden' => 0,
            ];
            $optionSubId = Capsule::table('tblproductconfigoptionssub')->insertGetId($optionsSub);

            $optionSubPrice = [
                'type' => 'configoptions',
                'currency' => 'xxxx',
                'relid' => $optionSubId,
                'msetupfee' => '0.00',
                'qsetupfee' => '0.00',
                'ssetupfee' => '0.00',
                'asetupfee' => '0.00',
                'bsetupfee' => '0.00',
                'tsetupfee' => '0.00',
                'monthly' => $price,
                'quarterly' => '0.00',
                'semiannually' => '0.00',
                'annually' => '0.00',
                'biennially' => '0.00',
                'triennially' => '0.00',
            ];

            $productModel = new \MGModule\RealtimeRegisterSsl\models\productConfiguration\Repository();
            foreach ($productModel->getAllCurrencies() as $currency) {
                $optionSubPrice['currency'] = $currency->id;
                $optionSubId = Capsule::table('tblpricing')->insertGetId($optionSubPrice);
            }
        }
    }

    private static function getOptionGroup($name, $productId) : int {
        $optionGroupResult = Capsule::table('tblproductconfiggroups')
            ->select('id')
            ->where('name', '=', 'RealtimeRegisterSsl - ' . $name)
            ->where('description', '=', 'Auto generated by module - RealtimeRegisterSSL #' . $productId)
            ->first();

        if ($optionGroupResult != null) {
            return $optionGroupResult->id;
        }
        $optionGroup = [
            'name' => 'RealtimeRegisterSsl - ' . $name,
            'description' => 'Auto generated by module - RealtimeRegisterSSL #' . $productId
        ];
        $optionGroupId = Capsule::table('tblproductconfiggroups')->insertGetId($optionGroup);

        $optionLink = [
            'gid' => $optionGroupId,
            'pid' => $productId
        ];
        Capsule::table('tblproductconfiglinks')->insert($optionLink);
        return $optionGroupId;
    }

    private static function insertOptions($apiProductId, $apiProduct, array $options) {
        $periods = $apiProduct->getPeriods();

        sort($periods);

        $priceRepo = new ApiProductPriceRepo();

        // The prices are sometimes not imported yet, so we force an import when there is no data
        self::loadPrices($priceRepo, $apiProductId);

        foreach($periods as $i => $period) {
            $price = $priceRepo->onlyApiProductID(KeyToIdMapping::getIdByKey($apiProductId))
                    ->onlyPeriod($period)
                    ->onlyAction($options['action'])
                    ->fetchOne()
                    ->price / 100;
            $option = [
                'gid' => $options['optionGroupId'],
                'optionname' => $options['optionName'] .  " (" . $period / 12 . ' years)',
                'optiontype' => 4,
                'qtyminimum' => 0,
                'qtymaximum' => $options['maximum'],
                'order' => $i + count($periods),
                'hidden' => 0,
            ];
            $optionId = Capsule::table('tblproductconfigoptions')->insertGetId($option);

            $optionsSub = [
                'configid' => $optionId,
                'optionname' => 'SAN',
                'sortorder' => 0,
                'hidden' => 0,
            ];
            $optionSubId = Capsule::table('tblproductconfigoptionssub')->insertGetId($optionsSub);

            $optionSubPrice = [
                'type' => 'configoptions',
                'currency' => 'xxxx',
                'relid' => $optionSubId,
                'msetupfee' => '0.00',
                'qsetupfee' => '0.00',
                'ssetupfee' => '0.00',
                'asetupfee' => '0.00',
                'bsetupfee' => '0.00',
                'tsetupfee' => '0.00',
                'monthly' => $price,
                'quarterly' => '0.00',
                'semiannually' => '0.00',
                'annually' => '0.00',
                'biennially' => '0.00',
                'triennially' => '0.00',
            ];

            $productModel = new \MGModule\RealtimeRegisterSsl\models\productConfiguration\Repository();
            foreach ($productModel->getAllCurrencies() as $currency) {
                $optionSubPrice['currency'] = $currency->id;
                $optionSubId = Capsule::table('tblpricing')->insertGetId($optionSubPrice);
            }
        }
    }

    /**
     * @param ApiProductPriceRepo $priceRepo
     * @param $apiProductId
     * @return void
     */
    public static function loadPrices(ApiProductPriceRepo $priceRepo, $apiProductId): void
    {
        try {
            $priceRepo->onlyApiProductID(KeyToIdMapping::getIdByKey($apiProductId))->fetchOne();
        } catch (\Exception $e) {
            Whmcs::savelogActivityRealtimeRegisterSsl("Realtime Register SSL WHMCS: loaded prices because they weren't available.");

            $apiProductsPrices = ProductsPrices::getInstance();
            foreach ($apiProductsPrices->getAllProductsPrices() as $productPrice) {
                $productPrice->saveToDatabase();
            }
        }
    }
}