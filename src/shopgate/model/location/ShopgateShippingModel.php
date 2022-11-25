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
class ShopgateShippingModel
{
    /**
     * read all shipping module data from database
     *
     * @return mixed
     */
    public function getShippingCountriesFromConstants()
    {
        if ($this->useLegacyConfigTable()) {
            $shippingQuery = "SELECT c.configuration_value AS 'countries' 
               FROM " . TABLE_CONFIGURATION . " AS c 
               WHERE c.configuration_key LIKE 'MODULE_SHIPPING_%_COUNTRIES_%' AND c.configuration_value != ''";
        } else {
            $shippingQuery = "SELECT c.`value` AS 'countries' 
               FROM " . TABLE_CONFIGURATION . " AS c 
               WHERE c.`key` LIKE 'MODULE_SHIPPING_%_COUNTRIES_%' AND c.`value` != ''";
        }

        return xtc_db_query($shippingQuery);
    }

    /**
     * read the shipping configuration data from database regarding shipping class name
     *
     * @param $className
     *
     * @return array
     */
    public function getShippingConfigurationValuesByClassName($className)
    {
        if ($this->useLegacyConfigTable()) {
            $query = "SELECT c.configuration_key, c.configuration_value FROM "
                . TABLE_CONFIGURATION . " AS c WHERE configuration_key like \"MODULE_SHIPPING_"
                . strtoupper($className) . "%\" ;";
        } else {
            $query = "SELECT c.`key` AS configuration_key, c.`value` AS configuration_value FROM "
                . TABLE_CONFIGURATION . " AS c WHERE configuration_key like \"MODULE_SHIPPING_"
                . strtoupper($className) . "%\" ;";
        }
        $result         = xtc_db_query($query);
        $shippingConfig = array();

        while ($config = xtc_db_fetch_array($result)) {
            $shippingConfig[$config["configuration_key"]] = $config["configuration_value"];
        }

        return $shippingConfig;
    }

    private function useLegacyConfigTable()
    {
        return TABLE_CONFIGURATION !== 'gx_configurations';
    }
}
