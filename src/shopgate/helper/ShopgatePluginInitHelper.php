<?php

/**
 * This file is part of the Shopgate integration for GambioGX
 *
 * Copyright Shopgate Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Shopgate Inc, 804 Congress Ave, Austin, Texas 78701 <interfaces@shopgate.com>
 * @copyright Shopgate Inc
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, Version 2.0
 */
class ShopgatePluginInitHelper
{
    /**
     * @var ShopgateConfigGambioGx
     */
    protected $config;

    /**
     * @var ShopgateLogger
     */
    private $log;

    /**
     * @throws ShopgateLibraryException
     */
    public function __construct()
    {
        require_once(DIR_FS_CATALOG . 'shopgate/gambiogx/shopgate_config.php');
        $this->config = new ShopgateConfigGambioGx();
        if (!isset($_REQUEST['shop_number'])) {
            $this->config->loadFile();
        } else {
            $this->config->loadByShopNumber($_REQUEST['shop_number']);
        }
        $this->log = ShopgateLogger::getInstance();
    }

    /**
     * @return ShopgateConfigGambioGx
     */
    public function getShopgateConfig()
    {
        return $this->config;
    }

    /**
     * initialize database constants
     */
    public function initDatabaseConstants()
    {
        if (!defined('TABLE_PRODUCTS_PROPERTIES_COMBIS')) {
            define('TABLE_PRODUCTS_PROPERTIES_COMBIS', 'products_properties_combis');
        }
        if (!defined('TABLE_PRODUCTS_PROPERTIES_COMBIS_VALUES')) {
            define('TABLE_PRODUCTS_PROPERTIES_COMBIS_VALUES', 'products_properties_combis_values');
        }
        if (!defined('TABLE_PROPERTIES_VALUES')) {
            define('TABLE_PROPERTIES_VALUES', 'properties_values');
        }
        if (!defined('TABLE_PROPERTIES_VALUES_DESCRIPTION')) {
            define('TABLE_PROPERTIES_VALUES_DESCRIPTION', 'properties_values_description');
        }
        if (!defined('TABLE_PROPERTIES')) {
            define('TABLE_PROPERTIES', 'properties');
        }
        if (!defined('TABLE_PROPERTIES_DESCRIPTION')) {
            define('TABLE_PROPERTIES_DESCRIPTION', 'properties_description');
        }
        if (!defined('TABLE_ORDERS_PRODUCTS_PROPERTIES')) {
            define('TABLE_ORDERS_PRODUCTS_PROPERTIES', 'orders_products_properties');
        }
        if (!defined("TABLE_ITEM_CODES")) {
            define("TABLE_ITEM_CODES", "products_item_codes");
        }
    }

    /**
     * initialize shopgate database constants
     */
    public function initShopgateDatabaseConstants()
    {
        if (!defined('TABLE_ORDERS_SHOPGATE_ORDER')) {
            define('TABLE_ORDERS_SHOPGATE_ORDER', 'orders_shopgate_order');
        }
    }

    /**
     * include language files for translation
     *
     * @param $language
     */
    public function includeShopgateLanguageFile($language)
    {
        $languageFile = DIR_FS_CATALOG . '/shopgate/gambiogx/lang/' . $language . '/modules/payment/shopgate.php';
        if (file_exists($languageFile)) {
            require_once($languageFile);
        }
    }

    /**
     * include the shop systems language files for translation
     *
     * @param $language
     */
    public function includeLanguageFiles($language, $gambioVersion)
    {
        if (!defined('DIR_FS_LANGUAGES')) {
            define('DIR_FS_LANGUAGES', rtrim(DIR_FS_CATALOG, '/') . '/lang/');
        }
        // load additional Shopgate admin language files
        $langFiles = array(
            'admin/categories.php',
            'admin/content_manager.php',
            'modules/order_total/ot_coupon.php',
        );

        $gambioCompareVersion = $gambioVersion['main_version'] . '.' . $gambioVersion['sub_version'];
        if (version_compare($gambioCompareVersion, '2.1', '>=')) {
            $languageTextManager = MainFactory::create_object('LanguageTextManager', array(), true);
        }

        include_once rtrim(DIR_FS_CATALOG, '/') . "/shopgate/gambiogx/lang/$language/admin/$language.php";

        foreach ($langFiles as $langFile) {
            if (version_compare($gambioCompareVersion, '2.1', '>=')) {
                /** @var LanguageTextManager $languageTextManager */
                $languageTextManager->init_from_lang_file('lang/' . $language . '/' . $langFile);
            } else {
                $toInclude = rtrim(DIR_FS_LANGUAGES, '/') . "/$language/$langFile";

                if (file_exists($toInclude)) {
                    include_once $toInclude;
                } else {
                    $this->log->log(ShopgateLogger::LOGTYPE_DEBUG, "File {$toInclude} not found.");
                }
            }
        }
    }

    /**
     * read the country data from database
     *
     * @return string
     */
    public function getShopCountryIdFromDatabase()
    {
        // fetch country
        $qry    = "SELECT * FROM `" . TABLE_COUNTRIES . "` WHERE UPPER(countries_iso_code_2) = UPPER('"
            . $this->config->getCountry() . "')";
        $result = xtc_db_query($qry);
        $qry    = xtc_db_fetch_array($result);

        return !empty($qry['countries_id'])
            ? $qry['countries_id']
            : 'DE';
    }

    /**
     * read the language information from database
     *
     * @return mixed
     */
    public function getShopLanguageFromDatabase()
    {
        // fetch language
        $qry    =
            "SELECT * FROM `" . TABLE_LANGUAGES . "` WHERE UPPER(code) = UPPER('" . $this->config->getLanguage() . "')";
        $result = xtc_db_query($qry);
        $row    = xtc_db_fetch_array($result);

        return $row;
    }

    /**
     * read language by iso code
     *
     * @param $isoCode
     *
     * @return mixed
     * @throws ShopgateLibraryException
     */
    public static function getLanguageIdByIsoCode($isoCode)
    {
        $isoCodeParts = explode('_', $isoCode);
        $isoCode      = isset($isoCodeParts[0])
            ? $isoCodeParts[0]
            : $isoCode;

        $qry        = "SELECT * FROM `" . TABLE_LANGUAGES . "` WHERE UPPER(code) = UPPER('" . $isoCode . "')";
        $result     = xtc_db_query($qry);
        $resultItem = xtc_db_fetch_array($result);

        if (!isset($resultItem['languages_id'])) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::UNKNOWN_ERROR_CODE, 'Invalid iso code given : ' . $isoCode
            );
        } else {
            return $resultItem['languages_id'];
        }
    }

    /**
     * read currency data from database
     *
     * @return mixed
     */
    public function getShopCurrencyFromDatabase()
    {
        $qry    = "SELECT * FROM `" . TABLE_CURRENCIES . "` WHERE UPPER(code) = UPPER('" . $this->config->getCurrency()
            . "')";
        $result = xtc_db_query($qry);

        return xtc_db_fetch_array($result);
    }

    /**
     * check Shopgate database table version and do an update (uninstall/reinstall the plugin)
     */
    public function checkShopgateTable()
    {
        // check if the shopgate table is on
        if ($this->config->getShopgateTableVersion() != SHOPGATE_PLUGIN_VERSION) {
            // reinstall the payment module
            require_once(DIR_FS_CATALOG . 'shopgate/gambiogx/includes/modules/payment/shopgate.php');
            $shopgatePaymentModule = new shopgate();
            if ($shopgatePaymentModule->check()) {
                $shopgatePaymentModule->remove();
            }
            $shopgatePaymentModule->install();
        }
    }
}
