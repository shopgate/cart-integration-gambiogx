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
class ShopgateInstallHelper
{
    /**
     * salt to create hash. This hash identifies the shop
     */
    const SHOPGATE_CALLBACK_SALT = "SHOPGATE_CALLBACK_ggx2";
    /**
     * defines the shopsystem (predefined by sg)
     */
    const SHOPGATE_CALLBACK_SHOP_TYPE = 55;
    /**
     * url to the sg api controller. calling the action log_api (live)
     */
    const SHOPGATE_CALLBACK_REQUEST_URL = 'https://api.shopgate.com/log';
    /**
     * database configuration key
     */
    const SHOPGATE_CALLBACK_DATABASE_CONFIG_KEY = "MODULE_PAYMENT_SHOPGATE_CALLBACK_IDENT";
    /**
     * file where the ident hash will be stored
     */
    const SHOPGATE_CALLBACK_HASH_FILE = "/sg_identity.php";
    /**
     * default currency configuration key
     */
    const SHOPGATE_CALLBACK_DEFAULT_CURRENCY_KEY = "DEFAULT_CURRENCY";
    /**
     * default email configuration key
     */
    const SHOPGATE_CALLBACK_DEFAULT_EMAIL_KEY = "CONTACT_US_EMAIL_ADDRESS";
    /**
     * default contact name configuration key
     */
    const SHOPGATE_CALLBACK_DEFAULT_CONTACT_NAME_KEY = "CONTACT_US_NAME";
    /**
     * default store name configuration key
     */
    const SHOPGATE_CALLBACK_DEFAULT_STORE_NAME_KEY = "STORE_NAME";
    /**
     * default store address configuration key
     */
    const SHOPGATE_CALLBACK_DEFAULT_STORE_NAME_ADDRESS_KEY = "STORE_NAME_ADDRESS";

    /**
     * C-tor
     */
    public function __construct()
    {
    }

    /**
     * send information about the store to sg
     */
    public function sendData()
    {
        $shopHolderInformation = $this->getStoreHolderInformation();
        $postData              = array(
            'action'              => 'interface_install',
            'uid'                 => $this->getUid(),
            'url'                 => $this->getUrl(),
            'name'                => $shopHolderInformation['store_name'],
            'plugin_version'      => $this->getPluginVersion(),
            'shopping_system_id'  => $this->getShopSystemId(),
            'contact_name'        => $shopHolderInformation['contact_name'],
            'contact_phone'       => $shopHolderInformation['store_phone'],
            'contact_email'       => $shopHolderInformation['contact_email'],
            'stats_items'         => $this->getProductCount(),
            'stats_categories'    => $this->getCategoryCount(),
            'stats_orders'        => $this->getOrderAmount($this->getDate()),
            'stats_acs'           => $this->getAcs(),
            'stats_currency'      => $this->getDefaultCurrency(),
            'stats_unique_visits' => '',
            'stats_mobile_visits' => '',
        );

        $this->sendPostRequest($postData);
    }

    /**
     * get an unique hash to identify the shop
     *
     * @return string
     */
    private function getUid()
    {
        $hashFile = realpath(dirname(__FILE__)) . self::SHOPGATE_CALLBACK_HASH_FILE;

        if (file_exists($hashFile)) {
            $content = file_get_contents($hashFile);
            preg_match("/([a-z0-9]{32})/", $content, $result);
            if (is_array($result)) {
                return (count($result) > 1)
                    ? $result[1]
                    : $result[0];
            }
        }

        if ($this->useLegacyConfigTable()) {
            $keyQuery = 'SELECT c.configuration_value AS val FROM ' . TABLE_CONFIGURATION . ' AS c
                         WHERE c.configuration_key = "' . self::SHOPGATE_CALLBACK_DATABASE_CONFIG_KEY . '" LIMIT 1;';
        } else {
            $keyQuery = 'SELECT c.`value` AS val FROM ' . TABLE_CONFIGURATION . ' AS c
                         WHERE c.`key` = "' . self::SHOPGATE_CALLBACK_DATABASE_CONFIG_KEY . '" LIMIT 1;';
        }
        $result   = xtc_db_query($keyQuery);
        $row      = xtc_db_fetch_array($result);

        if (!empty($row) && $row['val'] && $row['val'] != 0) {
            return $row['val'];
        }

        try {
            $tokenQuery = 'SELECT c.gm_value as token FROM gm_configuration as c WHERE c.gm_key = "SECURE_TOKEN" LIMIT 1;';
            $result     = xtc_db_query($tokenQuery);
            $row        = xtc_db_fetch_array($result);
        } catch (Exception $e) {
            $row = [];
        }

        if (!empty($row['token']) && $row['token'] != 0) {
            $saltedHash = md5($row['token'] . self::SHOPGATE_CALLBACK_SALT);
            $content    = "<?php //" . $saltedHash;
        } else {
            $httpserver = null;
            if (defined('HTTP_SERVER')) {
                $httpserver = HTTP_SERVER;
            } elseif (isset($_SERVER) && !empty($_SERVER['SERVER_NAME'])) {
                $httpserver = $_SERVER['SERVER_NAME'];
            }

            if (!empty($httpserver) && defined('DIR_WS_CATALOG')) {
                $url        = preg_replace('/^www\./', '', preg_replace('#^https?://#', '', trim($httpserver, '/')));
                $uri        = trim(DIR_WS_CATALOG, '/');
                $hashString = $url . '/' . $uri;
            } else {
                $stoherHolderInfo = $this->getStoreHolderInformation();
                $hashString       = $stoherHolderInfo['contact_email'];
            }

            $saltedHash = md5($hashString . self::SHOPGATE_CALLBACK_SALT);
            $content    = "<?php //" . $saltedHash;
        }

        if (file_put_contents($hashFile, $content) === false) { /*error*/
        }

        if (!(TABLE_CONFIGURATION === 'gx_configurations')) {
            $updateKeyQuery = 'UPDATE ' . TABLE_CONFIGURATION . ' AS c  SET c.configuration_value ="' . $saltedHash
                . '" WHERE c.configuration_key = "'
                . self::SHOPGATE_CALLBACK_DATABASE_CONFIG_KEY . '";';
        } else {
            $updateKeyQuery = 'UPDATE ' . TABLE_CONFIGURATION . ' AS c  SET c.`value` ="' . $saltedHash
                . '" WHERE c.`key` = "'
                . self::SHOPGATE_CALLBACK_DATABASE_CONFIG_KEY . '";';
        }


        xtc_db_query($updateKeyQuery);

        return $saltedHash;
    }

    /**
     * return an array with store holder information
     *
     * @return array
     */
    private function getStoreHolderInformation()
    {
        if ($this->useLegacyConfigTable()) {
            $keyQuery = 'SELECT configuration_key, configuration_value FROM ' . TABLE_CONFIGURATION . ' as c
						 WHERE c.configuration_key = "' . self::SHOPGATE_CALLBACK_DEFAULT_EMAIL_KEY . '"
						 OR c.configuration_key    = "' . self::SHOPGATE_CALLBACK_DEFAULT_CONTACT_NAME_KEY . '" 
						 OR c.configuration_key    = "' . self::SHOPGATE_CALLBACK_DEFAULT_STORE_NAME_KEY . '"
						 OR c.configuration_key    = "' . self::SHOPGATE_CALLBACK_DEFAULT_STORE_NAME_ADDRESS_KEY . '";';
        } else {
            $keyQuery = 'SELECT `key` AS configuration_key, `value` AS configuration_value FROM ' . TABLE_CONFIGURATION . ' as c
						 WHERE configuration_key = "' . self::SHOPGATE_CALLBACK_DEFAULT_EMAIL_KEY . '"
						 OR configuration_key    = "' . self::SHOPGATE_CALLBACK_DEFAULT_CONTACT_NAME_KEY . '" 
						 OR configuration_key    = "' . self::SHOPGATE_CALLBACK_DEFAULT_STORE_NAME_KEY . '"
						 OR configuration_key    = "' . self::SHOPGATE_CALLBACK_DEFAULT_STORE_NAME_ADDRESS_KEY . '";';
        }
        $result                 = xtc_db_query($keyQuery);
        $storeHolderInformation = array();

        while ($row = xtc_db_fetch_array($result)) {
            if (array_key_exists('configuration_value', $row)) {
                if ($row['configuration_key'] == "CONTACT_US_EMAIL_ADDRESS") {
                    $storeHolderInformation['contact_email'] = $row['configuration_value'];
                }
                if ($row['configuration_key'] == "CONTACT_US_NAME") {
                    $storeHolderInformation['contact_name'] = $row['configuration_value'];
                }
                if ($row['configuration_key'] == "STORE_NAME") {
                    $storeHolderInformation['store_name'] = $row['configuration_value'];
                }
                if ($row['configuration_key'] == "STORE_NAME_ADDRESS") {
                    $storeHolderInformation['store_phone'] = $row['configuration_value'];
                }
            }
        }

        return $storeHolderInformation;
    }

    /**
     * return the complete url to the current shop
     *
     * @return string
     */
    private function getUrl()
    {
        if (function_exists('apache_request_headers')) {
            $header = apache_request_headers();
            $host   = ((!empty($header['Referer']))
                ? $header['Referer']
                : $header['Host']);

            return $host;
        } else {
            if (isset($_SERVER)) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
                    ? "https://"
                    : "http://";
                $host     = (!empty($_SERVER['HTTP_HOST']))
                    ? $_SERVER['HTTP_HOST']
                    : $_SERVER['HTTP_NAME'];
                $uri      = (!empty($_SERVER['REQUEST_URI']))
                    ? $_SERVER['REQUEST_URI']
                    : '';

                return ($protocol . $host . $uri);
            }
        }

        return '';
    }

    /**
     * return the amount of all categories in the shop system
     *
     * @param bool $ignoreDeactivated if true the deactivated categories will be ignored
     *
     * @return int
     */
    private function getCategoryCount($ignoreDeactivated = false)
    {
        $query = "SELECT count(*) AS cnt FROM " . TABLE_CATEGORIES . " AS c ";

        if ($ignoreDeactivated) {
            $query .= "WHERE c.categories_status != 0";
        }

        $result = xtc_db_query($query);
        $row    = xtc_db_fetch_array($result);

        return $row['cnt'];
    }

    /**
     * return the amount of all orders in the shop system
     *
     * @param string $beginDate in format Y-m-d H:i:s
     *
     * @return int
     */
    private function getOrderAmount($beginDate = null)
    {
        if (is_null($beginDate)) {
            $beginDate = 'now()';
        }
        $query  =
            "SELECT count(*) as cnt FROM " . TABLE_ORDERS . " WHERE date_purchased BETWEEN '{$beginDate}' AND now()";
        $result = xtc_db_query($query);
        $row    = xtc_db_fetch_array($result);

        return $row['cnt'];
    }

    /**
     * return the get Average cart score (acs)
     *
     * @return double
     */
    public function getAcs()
    {
        $query
                = "SELECT AVG(
							(SELECT SUM(customers_basket.final_price) FROM " . TABLE_CUSTOMERS_BASKET . ") 
							DIV 
							(SELECT COUNT( DISTINCT customers_id) FROM " . TABLE_CUSTOMERS_BASKET . ")
							) as acs";
        $result = xtc_db_query($query);
        $row    = xtc_db_fetch_array($result);
        if (!empty($row)) {
            return (array_key_exists('acs', $row))
                ? $row['acs']
                : '';
        }

        return '';
    }

    /**
     * @return null
     */
    public function getUniqueVisits()
    {
        return null;
    }

    /**
     * @return null
     */
    public function getMobileVisits()
    {
        return null;
    }

    /**
     * returns the plugin version
     *
     * @return int
     */
    private function getPluginVersion()
    {
        return SHOPGATE_CALLBACK_PLUGIN_VERSION;
    }

    /**
     * return the product count
     *
     * @param bool $ignoreDeactivated if true ignores the inactive products
     *
     * @return int
     */
    private function getProductCount($ignoreDeactivated = false)
    {
        $query = "SELECT count(*) as cnt FROM " . TABLE_PRODUCTS . " AS p ";

        if ($ignoreDeactivated) {
            $query .= "WHERE p.products_status != 0";
        }

        $result = xtc_db_query($query);
        $row    = xtc_db_fetch_array($result);

        return $row['cnt'];
    }

    /**
     * returns the shop system code defined by shopgate
     *
     * @return mixed
     */
    private function getShopsystemId()
    {
        return self::SHOPGATE_CALLBACK_SHOP_TYPE;
    }

    /**
     * returns the default currency of the shop
     *
     * @return string
     */
    private function getDefaultCurrency()
    {
        if ($this->useLegacyConfigTable()) {
            $query = 'SELECT configuration_value AS currency FROM ' . TABLE_CONFIGURATION . ' AS c
                      WHERE c.configuration_key = "' . self::SHOPGATE_CALLBACK_DEFAULT_CURRENCY_KEY . '"';
        } else {
            $query = 'SELECT `value` AS currency FROM ' . TABLE_CONFIGURATION . ' AS c
                      WHERE c.`key` = "' . self::SHOPGATE_CALLBACK_DEFAULT_CURRENCY_KEY . '"';
        }
        $result = xtc_db_query($query);
        $row    = xtc_db_fetch_array($result);

        return $row['currency'];
    }

    ############################
    ##### HELPER FUNCTIONS #####
    ############################
    /**
     * returns the date minus the committed period
     *
     * @param string $interval
     *
     * @return date
     */
    private function getDate($interval = "-1 months")
    {
        return date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s") . $interval));
    }

    /**
     * send an curl Post request to shopgate
     *
     * @param $data array with post data
     *
     * @return bool return true if post was successful false if not
     */
    private function sendPostRequest($data)
    {
        $query = http_build_query($data);
        $curl  = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::SHOPGATE_CALLBACK_REQUEST_URL);
        curl_setopt($curl, CURLOPT_POST, count($data));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        if (!($result = curl_exec($curl))) {
            $error = curl_error($curl);
            $errNo = curl_errno($curl);

            return false;
        }

        curl_close($curl);

        return true;
    }

    private function useLegacyConfigTable()
    {
        return !(TABLE_CONFIGURATION === 'gx_configurations');
    }
}
