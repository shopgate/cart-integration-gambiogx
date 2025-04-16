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

include_once DIR_FS_CATALOG . '/shopgate/gambiogx/shopgate_config.php';
include_once DIR_FS_CATALOG . '/shopgate/gambiogx/includes/modules/payment/ShopgateInstallHelper.php';
include_once(DIR_FS_CATALOG . '/shopgate/gambiogx/ShopgateTools.php');
include_once(DIR_FS_CATALOG . '/shopgate/model/customer/ShopgateCustomerModel.php');

/**
 * Class shopgate
 */
class shopgate
{
    /**
     * @var string
     */
    public $code;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $description;

    /**
     * @var bool
     */
    public $enabled;

    /**
     * @var int
     */
    public $sort_order;

    /**
     *
     */
    public function __construct()
    {
        $this->code        = 'shopgate';
        $this->title       = MODULE_PAYMENT_SHOPGATE_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_SHOPGATE_TEXT_DESCRIPTION;
        $this->enabled     = false;
        $this->sort_order  = 88457;
    }

    /**
     *
     */
    public function mobile_payment()
    {
        $this->code        = 'shopgate';
        $this->title       = MODULE_PAYMENT_SHOPGATE_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_SHOPGATE_TEXT_DESCRIPTION;
        $this->enabled     = false;
        $this->sort_order  = 88457;
    }

    /**
     *
     */
    public function update_status()
    {
    }

    /**
     * @return bool
     */
    public function javascript_validation()
    {
        return false;
    }

    /**
     * @return array
     */
    public function selection()
    {
        return array('id' => $this->code, 'module' => $this->title, 'description' => $this->info);
    }

    /**
     * @return bool
     */
    public function pre_confirmation_check()
    {
        return false;
    }

    /**
     * @return array
     */
    public function confirmation()
    {
        return array('title' => MODULE_PAYMENT_SHOPGATE_TEXT_DESCRIPTION);
    }

    /**
     * @return bool
     */
    public function process_button()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function before_process()
    {
        return false;
    }

    /**
     *
     */
    public function after_process()
    {
        global $insert_id;
        if ($this->order_status) {
            xtc_db_query(
                "UPDATE " . TABLE_ORDERS . " SET orders_status='" . $this->order_status . "' WHERE orders_id='"
                . $insert_id . "'"
            );
        }
    }

    /**
     * @return bool
     */
    public function get_error()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function check()
    {
        if (!isset($this->_check)) {
            if ($this->useLegacyConfigTable()) {
                $qry = "select `configuration_value` from " . TABLE_CONFIGURATION
                    . " where `configuration_key` = 'MODULE_PAYMENT_SHOPGATE_STATUS'";
            } else {
                $qry = "select `value` from " . TABLE_CONFIGURATION
                    . " where `key` = 'configuration/MODULE_PAYMENT_SHOPGATE_STATUS'";
            }

            $check_query  = xtc_db_query($qry);
            $this->_check = xtc_db_num_rows($check_query);
        }

        return $this->_check;
    }

    /**
     * change the link texts which were predefined by Gambio
     *
     * @param $languageCode
     * @param $constantName
     * @param $text
     */
    public function updateDatabaseTexts($languageCode, $constantName, $text)
    {
        if ($this->tableExist("language_sections") && $this->tableExist("language_section_phrases")) {
            $result = xtc_db_query(
                'SELECT lfc.language_section_id
                        FROM language_sections g
                        JOIN ' . TABLE_LANGUAGES . ' AS l ON l.languages_id = g.language_id
                        JOIN language_section_phrases AS lfc ON lfc.language_section_id = g.language_section_id
                        WHERE g.section_name LIKE "%SHOPGATE%"
                        AND l.code = "' . $languageCode . '"
                        AND lfc.phrase_name
                        LIKE "%' . $constantName . '%"'
            );
            if (!empty($result)) {
                $row = xtc_db_fetch_array($result);
                if (!empty($row["language_section_id"])) {
                    $updateQuery =
                        'update language_section_phrases SET language_section_phrases.phrase_value = "' . $text . '"
                                where language_section_phrases.language_section_id = ' . $row["language_section_id"] .
                        ' AND language_section_phrases.phrase_name LIKE "%' . $constantName . '%"';
                    xtc_db_query($updateQuery);
                }
            }
        } elseif ($this->tableExist("gm_lang_files_content")) {
            $result = xtc_db_query(
                'SELECT lfc.lang_files_content_id FROM gm_lang_files g
                    JOIN ' . TABLE_LANGUAGES . ' AS l ON l.languages_id = g.language_id
                    JOIN gm_lang_files_content AS lfc ON lfc.lang_files_id = g.lang_files_id
                    WHERE g.file_path LIKE "%SHOPGATE%" AND l.code = "' . $languageCode
                . '" AND lfc.constant_name LIKE "%' . $constantName . '%"'
            );

            if (!empty($result)) {
                $row = xtc_db_fetch_array($result);
                if (!empty($row["lang_files_content_id"])) {
                    $updateQuery = 'update gm_lang_files_content SET gm_lang_files_content.constant_value = "' . $text . '"
                                where gm_lang_files_content.lang_files_content_id = ' . $row["lang_files_content_id"];
                    xtc_db_query($updateQuery);
                }
            }
        }
    }

    /**
     * install the module
     *
     * -- KEYS --:
     * MODULE_PAYMENT_SHOPGATE_STATUS - The state of the module ( true / false )
     * MODULE_PAYMENT_SHOPGATE_ALLOWED - Is the module allowed on frontend
     * MODULE_PAYMENT_SHOPGATE_ORDER_STATUS_ID - (DEPRECATED) keep it for old installations
     */
    public function install()
    {
        if (!defined('TABLE_ORDERS_SHOPGATE_ORDER')) {
            define('TABLE_ORDERS_SHOPGATE_ORDER', 'orders_shopgate_order');
        }

        if ($this->useLegacyConfigTable()) {
            $qry = "delete from " . TABLE_CONFIGURATION
                . " where configuration_key in ('MODULE_PAYMENT_SHOPGATE_STATUS', 'MODULE_PAYMENT_SHOPGATE_ALLOWED', 'MODULE_PAYMENT_SHOPGATE_ORDER_STATUS_ID')";
        } else {
            $qry = "delete from " . TABLE_CONFIGURATION
                . " where `key` in ('configuration/MODULE_PAYMENT_SHOPGATE_STATUS', 'configuration/MODULE_PAYMENT_SHOPGATE_ALLOWED', 'configuration/MODULE_PAYMENT_SHOPGATE_ORDER_STATUS_ID')";
        }
        xtc_db_query($qry);
        if ($this->useLegacyConfigTable()) {
            $qry = "insert into " . TABLE_CONFIGURATION
                 . " ( configuration_key, configuration_value,  configuration_group_id, sort_order, set_function, date_added) values ('MODULE_PAYMENT_SHOPGATE_STATUS', 'True', '6', '"
                 . $this->sort_order . "', 'gm_cfg_select_option(array(\'True\', \'False\'), ', now())";
        } else {
            $qry = "insert into " . TABLE_CONFIGURATION
                . " ( `key`, `value`, `sort_order`) values ('configuration/MODULE_PAYMENT_SHOPGATE_STATUS', 'True', '"
                 . $this->sort_order . "')";
        }
        xtc_db_query($qry);
        if ($this->useLegacyConfigTable()) {
            $qry = "insert into " . TABLE_CONFIGURATION
                . " ( configuration_key, configuration_value,  configuration_group_id, sort_order, date_added) values ('MODULE_PAYMENT_SHOPGATE_ALLOWED', '0',   '6', '"
                . $this->sort_order . "', now())";
        } else {
            $qry = "insert into " . TABLE_CONFIGURATION
                . " ( `key`, `value`, `sort_order`) values ('configuration/MODULE_PAYMENT_SHOPGATE_ALLOWED', '0', '"
                . $this->sort_order . "')";
        }
        xtc_db_query($qry);
        if ($this->useLegacyConfigTable()) {
            $qry = 'select configuration_key, configuration_value from ' . TABLE_CONFIGURATION . ' as c '
                 . 'where c.configuration_key = "' . ShopgateInstallHelper::SHOPGATE_CALLBACK_DATABASE_CONFIG_KEY . '"';
        } else {
            $qry = 'select `key`, `value` from ' . TABLE_CONFIGURATION . ' as c '
                 . 'where c.`key` = "' . ShopgateInstallHelper::SHOPGATE_CALLBACK_DATABASE_CONFIG_KEY . '"';
        }
        xtc_db_query($qry);
        $result = xtc_db_query($qry);
        $row    = xtc_db_fetch_array($result);

        if (empty($row)) {
            if ($this->useLegacyConfigTable()) {
                $qry = "insert into " . TABLE_CONFIGURATION
                    . " ( configuration_key, configuration_value,  configuration_group_id, sort_order, date_added) values ('"
                    . ShopgateInstallHelper::SHOPGATE_CALLBACK_DATABASE_CONFIG_KEY . "'  , '0',   '6', '"
                    . $this->sort_order . "', now())";
            } else {
                $qry = "insert into " . TABLE_CONFIGURATION
                    . " ( `key`, `value`, `sort_order`) values ('"
                    . ShopgateInstallHelper::SHOPGATE_CALLBACK_DATABASE_CONFIG_KEY . "'  , '0', '"
                    . $this->sort_order . "')";
            }
            xtc_db_query($qry);
        }
        $this->installTable();
        $this->updateDatabase();
        $this->grantAdminAccess();

        $gambioVersion  = ShopgateTools::getGambioVersion();
        $compareVersion = $gambioVersion['main_version'] . '.' . $gambioVersion['sub_version'];
        if (version_compare($compareVersion, '2.3', '<')) {
            $this->updateDatabaseTexts("en", "SHOPGATE_CONFIG", "Settings");
            $this->updateDatabaseTexts("de", "SHOPGATE_CONFIG", "Einstellungen");
        }

        $installHelper = new ShopgateInstallHelper();
        $installHelper->sendData();
    }

    /**
     * remove the shopgate module
     */
    public function remove()
    {
        // MODULE_PAYMENT_SHOPGATE_ORDER_STATUS_ID - Keep this on removing for old installation

        if ($this->useLegacyConfigTable()) {
            $qry = "delete from " . TABLE_CONFIGURATION
                . " where configuration_key in ('MODULE_PAYMENT_SHOPGATE_STATUS', 'MODULE_PAYMENT_SHOPGATE_ALLOWED', 'MODULE_PAYMENT_SHOPGATE_ORDER_STATUS_ID')";
        } else {
            $qry = "delete from " . TABLE_CONFIGURATION
                . " where `key` in ('configuration/MODULE_PAYMENT_SHOPGATE_STATUS', 'configuration/MODULE_PAYMENT_SHOPGATE_ALLOWED', 'MODULE_PAYMENT_SHOPGATE_ORDER_STATUS_ID')";
        }
        xtc_db_query($qry);
        if ($this->checkColumn("shopgate", TABLE_ADMIN_ACCESS)) {
            xtc_db_query("alter table " . TABLE_ADMIN_ACCESS . " DROP COLUMN shopgate");
        }
    }

    /**
     * Keep the array empty to disable all configuration options
     *
     * @return multitype:
     */
    public function keys()
    {
        if ($this->useLegacyConfigTable()) {
            return array('MODULE_PAYMENT_SHOPGATE_STATUS');
        }

        return array('configuration/MODULE_PAYMENT_SHOPGATE_STATUS');
    }

    /**
     * set grant access to shopgate configuration
     * to the current user and main administrator
     */
    private function grantAdminAccess()
    {
        if ($this->checkColumn("shopgate", TABLE_ADMIN_ACCESS)) {
            // Create column shopgate in admin_access...
            xtc_db_query("alter table " . TABLE_ADMIN_ACCESS . " ADD shopgate INT( 1 ) NOT NULL");

            // ... grant access to to shopgate for main administrator
            xtc_db_query("update " . TABLE_ADMIN_ACCESS . " SET shopgate=1 where customers_id=1 LIMIT 1");

            if (!empty($_SESSION['customer_id']) && $_SESSION['customer_id'] != 1) {
                // grant access also to current user
                xtc_db_query(
                    "update " . TABLE_ADMIN_ACCESS . " SET shopgate = 1 where customers_id='" . $_SESSION['customer_id']
                    . "' LIMIT 1"
                );
            }

            xtc_db_query("update " . TABLE_ADMIN_ACCESS . " SET shopgate = 5 where customers_id = 'groups'");
        }
    }

    /**
     * Install the shopgate order table
     */
    private function installTable()
    {
        xtc_db_query(
            "
            CREATE TABLE IF NOT EXISTS `" . TABLE_ORDERS_SHOPGATE_ORDER . "` (
                    `shopgate_order_id` INT(11) NOT NULL AUTO_INCREMENT,
                    `orders_id` INT(11) NOT NULL,
                    `shopgate_order_number` BIGINT(20) NOT NULL,
                    `shopgate_shop_number` BIGINT(20) UNSIGNED DEFAULT NULL,
                    `is_paid` tinyint(1) UNSIGNED DEFAULT NULL,
                    `is_cancelled` tinyint(1) UNSIGNED DEFAULT 0,
                    `is_shipping_blocked` tinyint(1) UNSIGNED DEFAULT NULL,
                    `payment_infos` TEXT NULL,
                    `is_sent_to_shopgate` tinyint(1) UNSIGNED DEFAULT NULL,
                    `modified` datetime DEFAULT NULL,
                    `created` datetime DEFAULT NULL,
                    PRIMARY KEY (`shopgate_order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
        );

        xtc_db_query(
            "
			CREATE TABLE IF NOT EXISTS `" . ShopgateCustomerModel::TABLE_CUSTOMERS_SHOPGATE_CUSTOMER . "` (
					`shopgate_customer_id` INT(11) NOT NULL AUTO_INCREMENT,
				`customer_id` INT(11) NOT NULL,
					`customer_token` VARCHAR(255) NULL,
					`modified` TIMESTAMP NULL DEFAULT NULL,
					`created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (`shopgate_customer_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8"
        );
    }

    /**
     * update existing database
     */
    private function updateDatabase()
    {
        if ($this->checkColumn('shopgate_shop_number')) {
            $qry = 'ALTER TABLE `' . TABLE_ORDERS_SHOPGATE_ORDER
                . '` ADD `shopgate_shop_number` BIGINT(20) UNSIGNED NULL AFTER `shopgate_order_number`;';
            xtc_db_query($qry);
        }

        if ($this->checkColumn('is_paid')) {
            $qry = 'ALTER TABLE  `' . TABLE_ORDERS_SHOPGATE_ORDER
                . '` ADD  `is_paid` TINYINT(1) UNSIGNED NULL AFTER `shopgate_shop_number`;';
            xtc_db_query($qry);
        }

        if ($this->checkColumn('is_cancelled')) {
            $qry = 'ALTER TABLE  `' . TABLE_ORDERS_SHOPGATE_ORDER
                . '` ADD  `is_cancelled` TINYINT(1) UNSIGNED DEFAULT 0 AFTER `is_paid`;';
            xtc_db_query($qry);
        }

        if ($this->checkColumn('is_shipping_blocked')) {
            $qry = 'ALTER TABLE `' . TABLE_ORDERS_SHOPGATE_ORDER
                . '` ADD  `is_shipping_blocked` TINYINT(1) UNSIGNED NULL AFTER  `is_paid`;';
            xtc_db_query($qry);
        }

        if ($this->checkColumn('payment_infos')) {
            $qry = 'ALTER TABLE `' . TABLE_ORDERS_SHOPGATE_ORDER
                . '` ADD  `payment_infos` TEXT NULL AFTER  `is_shipping_blocked`;';
            xtc_db_query($qry);
        }

        if ($this->checkColumn('is_sent_to_shopgate')) {
            $qry = 'ALTER TABLE `' . TABLE_ORDERS_SHOPGATE_ORDER
                . '` ADD  `is_sent_to_shopgate` TINYINT(1) UNSIGNED NULL AFTER `payment_infos`;';
            xtc_db_query($qry);
        }

        if ($this->checkColumn('modified')) {
            $qry = 'ALTER TABLE `' . TABLE_ORDERS_SHOPGATE_ORDER
                . '` ADD  `modified` DATETIME NULL AFTER `is_sent_to_shopgate`;';
            xtc_db_query($qry);
        }

        if ($this->checkColumn('created')) {
            $qry = 'ALTER TABLE `' . TABLE_ORDERS_SHOPGATE_ORDER . '` ADD  `created` DATETIME NULL AFTER `modified`;';
            xtc_db_query($qry);
        }

        $gambioVersion  = ShopgateTools::getGambioVersion();
        $compareVersion = $gambioVersion['main_version'] . '.' . $gambioVersion['sub_version'];
        if (version_compare($compareVersion, '2.3', '<')) {
            $this->updateDatabaseTexts("en", "SHOPGATE_CONFIG", "Settings");
            $this->updateDatabaseTexts("de", "SHOPGATE_CONFIG", "Einstellungen");
        }

        $languages = xtc_db_query('SELECT `languages_id`, `code` FROM `' . TABLE_LANGUAGES . '`;');
        if (empty($languages)) {
            echo MODULE_PAYMENT_SHOPGATE_ERROR_READING_LANGUAGES;

            return;
        }

        // load global configuration
        try {
            $config = new ShopgateConfigGambioGx();
            $config->loadFile();
        } catch (ShopgateLibraryException $e) {
            if (!($config instanceof ShopgateConfig)) {
                echo MODULE_PAYMENT_SHOPGATE_ERROR_LOADING_CONFIG;

                return;
            }
        }

        $languageCodes   = array();
        $configFieldList = array('language', 'redirect_languages');
        while ($language = xtc_db_fetch_array($languages)) {
            // collect language codes to enable redirect
            $languageCodes[] = $language['code'];

            switch ($language['code']) {
                case 'de':
                    $statusNameNew    = 'Versand blockiert (Shopgate)';
                    $statusNameSearch = '%shopgate%';
                    break;
                case 'en':
                    $statusNameNew    = 'Shipping blocked (Shopgate)';
                    $statusNameSearch = '%shopgate%';
                    break;
                default:
                    continue 2;
            }

            $result               = xtc_db_query(
                "SELECT `orders_status_id`, `orders_status_name` " .
                "FROM `" . TABLE_ORDERS_STATUS . "` " .
                "WHERE LOWER(`orders_status_name`) LIKE '" . xtc_db_input($statusNameSearch) . "' " .
                "AND `language_id` = " . xtc_db_input($language['languages_id']) . ";"
            );
            $checkShippingBlocked = xtc_db_fetch_array($result);

            if (!empty($checkShippingBlocked)) {
                $orderStatusShippingBlockedId = $checkShippingBlocked['orders_status_id'];
            } else {
                // if no orders_status_id has been determined yet and the status could not be found, create a new one
                if (!isset($orderStatusShippingBlockedId)) {
                    $result                       =
                        xtc_db_query("SELECT max(orders_status_id) AS orders_status_id FROM " . TABLE_ORDERS_STATUS);
                    $nextId                       = xtc_db_fetch_array($result);
                    $orderStatusShippingBlockedId = $nextId['orders_status_id'] + 1;
                }

                // insert the status into the database
                $result = xtc_db_query(
                    "INSERT INTO `" . TABLE_ORDERS_STATUS . "` " .
                    "(`orders_status_id`, `language_id`, `orders_status_name`) VALUES " .
                    "(" . xtc_db_input($orderStatusShippingBlockedId) . ", " . xtc_db_input($language['languages_id'])
                    . ", '" . xtc_db_input($statusNameNew) . "');"
                );
            }

            // set global order status id
            if ($language['code'] == DEFAULT_LANGUAGE) {
                $config->setOrderStatusShippingBlocked($orderStatusShippingBlockedId);
                $configFieldList[] = 'order_status_shipping_blocked';
            }
        }

        // get the actual definition of the plugin version
        if (!defined("SHOPGATE_PLUGIN_VERSION")) {
            require_once(DIR_FS_CATALOG . 'shopgate/plugin.php');
        }
        // shopgate table version equals to the SHOPGATE_PLUGIN_VERSION, save that version to the config file
        $config->setShopgateTableVersion(SHOPGATE_PLUGIN_VERSION);
        $configFieldList[] = 'shopgate_table_version';
        // save default language, order_status_id and redirect languages in the configuration
        try {
            $config->setLanguage(DEFAULT_LANGUAGE);
            $config->setRedirectLanguages($languageCodes);
            $config->saveFile($configFieldList);
        } catch (ShopgateLibraryException $e) {
            echo MODULE_PAYMENT_SHOPGATE_ERROR_SAVING_CONFIG;
        }
    }

    /**
     * Check if the column exists in the specified table
     *
     * @param string $columnName
     * @param string $table
     *
     * @return bool
     */
    private function checkColumn($columnName, $table = TABLE_ORDERS_SHOPGATE_ORDER)
    {
        $tables     = xtc_db_query("show tables");
        $tableExist = false;
        while ($tbls = xtc_db_fetch_array($tables)) {
            if (in_array($table, $tbls)) {
                $tableExist = true;
                break;
            }
        }

        if (!$tableExist) {
            return false;
        }

        $result = xtc_db_query("show columns from `{$table}`");
        $exists = false;

        while ($field = xtc_db_fetch_array($result)) {
            if ($field['Field'] == $columnName) {
                $exists = true;
                break;
            }
        }

        return $exists;
    }

    /**
     * @param $table
     *
     * @return bool
     */
    private function tableExist($table)
    {
        $tables = xtc_db_query("show tables");
        while ($tbls = xtc_db_fetch_array($tables)) {
            if (in_array($table, $tbls)) {
                return true;
            }
        }

        return false;
    }

    private function useLegacyConfigTable()
    {
        return TABLE_CONFIGURATION !== 'gx_configurations';
    }
}
