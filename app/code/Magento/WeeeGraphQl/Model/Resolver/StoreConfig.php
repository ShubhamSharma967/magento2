<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\WeeeGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Weee\Helper\Data;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Weee\Model\Tax as WeeeDisplayConfig;
use Magento\Framework\Pricing\Render;

class StoreConfig implements ResolverInterface
{
    /**
     * @var string
     */
    private static $weeeDisplaySettingsNone = 'NONE';

    /**
     * @var array
     */
    private static $weeeDisplaySettings =  [
        WeeeDisplayConfig::DISPLAY_INCL => 'INCLUDING_FPT',
        WeeeDisplayConfig::DISPLAY_INCL_DESCR => 'INCLUDING_FPT_AND_FPT_DESCRIPTION',
        WeeeDisplayConfig::DISPLAY_EXCL_DESCR_INCL => 'EXCLUDING_FPT_INCLUDING_FPT_AND_FPT_DESCRIPTION',
        WeeeDisplayConfig::DISPLAY_EXCL => 'EXCLUDING_FPT'
    ];

    /**
     * @var Data
     */
    private $weeeHelper;

    /**
     * @var TaxHelper
     */
    private $taxHelper;

    /**
     * @var array
     */
    private $computedFptSettings = [];

    /**
     * @param Data $weeeHelper
     * @param TaxHelper $taxHelper
     */
    public function __construct(Data $weeeHelper, TaxHelper $taxHelper)
    {
        $this->weeeHelper = $weeeHelper;
        $this->taxHelper = $taxHelper;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (empty($this->computedFptSettings)) {
            /** @var StoreInterface $store */
            $store = $context->getExtensionAttributes()->getStore();
            $storeId = (int)$store->getId();

            $this->computedFptSettings = [
                'product_fixed_product_tax_display_setting' => self::$weeeDisplaySettingsNone,
                'category_fixed_product_tax_display_setting' => self::$weeeDisplaySettingsNone,
                'sales_fixed_product_tax_display_setting' => self::$weeeDisplaySettingsNone,
            ];
            if ($this->weeeHelper->isEnabled($store)) {
                $productFptDisplay = $this->getWeeDisplaySettingsByZone(Render::ZONE_ITEM_VIEW, $storeId);
                $categoryFptDisplay = $this->getWeeDisplaySettingsByZone(Render::ZONE_ITEM_LIST, $storeId);
                $salesModulesFptDisplay = $this->getWeeDisplaySettingsByZone(Render::ZONE_SALES, $storeId);

                $this->computedFptSettings = [
                    'product_fixed_product_tax_display_setting' => self::$weeeDisplaySettings[$productFptDisplay] ??
                        self::$weeeDisplaySettingsNone,
                    'category_fixed_product_tax_display_setting' => self::$weeeDisplaySettings[$categoryFptDisplay] ??
                        self::$weeeDisplaySettingsNone,
                    'sales_fixed_product_tax_display_setting' => self::$weeeDisplaySettings[$salesModulesFptDisplay] ??
                        self::$weeeDisplaySettingsNone,
                ];
            }
        }

        return $this->computedFptSettings[$info->fieldName] ?? null;
    }

    /**
     * Get the weee system display setting
     *
     * @param string $zone
     * @param string $storeId
     * @return string
     */
    private function getWeeDisplaySettingsByZone(string $zone, int $storeId): int
    {
        return (int) $this->weeeHelper->typeOfDisplay(
            null,
            $zone,
            $storeId
        );
    }
}
