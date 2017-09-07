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
class ShopgateLocationModel extends ShopgateObject
{
    /**
     * @var ShopgateConfigGambioGx $config
     */
    private $config;

    /**
     * @param ShopgateConfigGambioGx $config
     */
    public function __construct(ShopgateConfigGambioGx $config)
    {
        $this->config = $config;
    }

    /**
     * read the zone data from database regarding the zone id and country id
     *
     * @param $zoneId
     * @param $countryId
     *
     * @return array
     * @throws ShopgateLibraryException
     */
    public function getZonesByZoneIdAndCountryId($zoneId, $countryId)
    {
        $query
            = "SELECT
                        c.countries_iso_code_2 AS 'country',
                        z.zone_code AS 'code'
                    FROM `" . TABLE_ZONES_TO_GEO_ZONES . "` AS zt
                        JOIN `" . TABLE_ZONES . "` as z on z.zone_country_id = zt.zone_country_id
                        JOIN `" . TABLE_COUNTRIES . "` AS c on c.countries_id = z.zone_country_id
                    WHERE zt.geo_zone_id = {$zoneId} AND z.zone_country_id = {$countryId}";

        $result = xtc_db_query($query);
        if (!$result) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR,
                "Shopgate Plugin - Error while reading zones to geo zones from tabel  \"" . TABLE_ZONES_TO_GEO_ZONES . "\": {$query}.",
                true
            );
        }
        $stateArray = array();
        while ($state = xtc_db_fetch_array($result)) {
            foreach ($state as &$sta) {
                $sta = $this->stringToUtf8($sta, $this->config->getEncoding());
            }

            $stateArray[] = ShopgateXtcMapper::getShopgateStateCode($state["country"], $state["code"]);
        }

        return $stateArray;
    }

    /**
     * read the zone data from database regarding the zone code
     *
     * @param $country
     * @param $resultArr
     *
     * @throws ShopgateLibraryException
     */
    public function getZonesByCountryIsoCode2($country, &$resultArr)
    {
        $query
                = "SELECT
                                c.countries_iso_code_2 AS 'country',
                                z.zone_code AS 'code'
                            FROM countries AS c
                                JOIN zones AS z on z.zone_country_id = c.countries_id
                            WHERE c.countries_iso_code_2 = '{$country}'";
        $states = xtc_db_query($query);
        if (!$states) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR,
                "Shopgate Plugin - Error checking for table \"" . TABLE_PRODUCTS . "\".",
                true
            );
        }
        $stateArray = array();
        while ($tmpStates = xtc_db_fetch_array($states)) {
            foreach ($tmpStates as &$tmpState) {
                $tmpState = $this->stringToUtf8($tmpState, $this->config->getEncoding());
            }
            $country      = $tmpStates["country"];
            $stateArray[] = ShopgateXtcMapper::getShopgateStateCode($tmpStates["country"], $tmpStates["code"]);
        }
        $resultArr[$country] = array(
            "country" => $country,
            "state"   => $stateArray,
        );
    }

    /**
     * get all tax classes from database
     *
     * @return array
     * @throws ShopgateLibraryException
     */
    public function getTaxClasses()
    {
        $taxClasses = array();
        $query      = "SELECT tc.tax_class_id AS id,tc.tax_class_title AS `key`  FROM tax_class AS tc ";
        $result     = xtc_db_query($query);

        if (!$result) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR, "no tax class found: " .
                (function_exists('mysqli_error')
                    ? mysqli_error()
                    : mysql_error()), true
            );
        } else {
            while ($taxClassArray = xtc_db_fetch_array($result)) {
                foreach ($taxClassArray as &$taxValue) {
                    $taxValue = $this->stringToUtf8($taxValue, $this->config->getEncoding());
                }
                $taxClasses[] = $taxClassArray;
            }
        }

        return $taxClasses;
    }

    /**
     * get tax rates from database
     *
     * @return array
     * @throws ShopgateLibraryException
     */
    public function getRawTaxRates()
    {
        $query = "SELECT tr.tax_rates_id, tr.tax_description, tr.tax_rate, tr.tax_priority, "
            . "c.countries_iso_code_2, z.zone_code, tc.tax_class_id, tc.tax_class_title "
            . "FROM `" . TABLE_TAX_RATES . "` AS tr "
            . "JOIN `" . TABLE_GEO_ZONES . "` AS gz ON tr.tax_zone_id = gz.geo_zone_id "
            . "JOIN `" . TABLE_ZONES_TO_GEO_ZONES . "` AS ztgz ON gz.geo_zone_id = ztgz.geo_zone_id "
            . "JOIN `" . TABLE_COUNTRIES . "` AS c ON ztgz.zone_country_id = c.countries_id "
            . "LEFT OUTER JOIN `" . TABLE_ZONES . "` AS z ON ztgz.zone_id = z.zone_id "
            . // zone (aka state) might not be mapped, rate applies for whole country in that case
            "JOIN `" . TABLE_TAX_CLASS . "` tc ON tr.tax_class_id = tc.tax_class_id;";

        $result = xtc_db_query($query);
        if (!$result) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR,
                "no tax rates found: " . (function_exists('mysqli_error')
                    ? mysqli_error()
                    : mysql_error()), true
            );
        }

        $taxRates = array();
        while ($row = xtc_db_fetch_array($result)) {
            $taxRates[] = $row;
        }

        return $taxRates;
    }

    /**
     * @return array Array('tax_rates' => .., 'tax_rules' => ...)
     * @throws ShopgateLibraryException
     */
    public function getTaxRatesAndTaxRules()
    {
        // Tax rates are pretty much a combination of tax rules and tax rates in osCommerce. So we're using them to generate both:
        $oscTaxRates = $this->getRawTaxRates();
        $taxRates    = array();
        $taxRules    = array();
        foreach ($oscTaxRates as $oscTaxRate) {
            // build and append tax rate
            $taxRateIdentifier = (empty($oscTaxRate['countries_iso_code_2'])
                    ? 'GLOBAL'
                    : $oscTaxRate['countries_iso_code_2']) .
                (empty($oscTaxRate['zone_code'])
                    ? ''
                    : '-' . $oscTaxRate['zone_code']) .
                '-' . $oscTaxRate['tax_rates_id'];
            $displayName       = empty($oscTaxRate['tax_description'])
                ? round($oscTaxRate['tax_rate'], 2) . "%"
                : $oscTaxRate['tax_description'];
            $taxRates[]        = array(
                'id'            => $taxRateIdentifier,
                'key'           => $taxRateIdentifier,
                'display_name'  => $displayName,
                'tax_percent'   => $oscTaxRate['tax_rate'],
                'country'       => $oscTaxRate['countries_iso_code_2'],
                'state'         => (!empty($oscTaxRate['countries_iso_code_2']) && !empty($oscTaxRate['zone_code']))
                    ? ShopgateXtcMapper::getShopgateStateCode(
                        $oscTaxRate['countries_iso_code_2'],
                        $oscTaxRate['zone_code']
                    )
                    : '',
                'zip_code_type' => 'all',
            );

            // build and append tax rule
            if (!empty($taxRules[$oscTaxRate['tax_rates_id']])) {
                $taxRules[$oscTaxRate['tax_rates_id']]['tax_rates'][] = array(
                    // one rate per rule (since rates are in fact also rules) in osCommerce
                    'id'  => $taxRateIdentifier,
                    'key' => $taxRateIdentifier,
                );
            } else {
                $taxRules[$oscTaxRate['tax_rates_id']] = array(
                    'id'                   => $oscTaxRate['tax_rates_id'],
                    'name'                 => $displayName,
                    'priority'             => $oscTaxRate['tax_priority'],
                    'product_tax_classes'  => array(
                        array(
                            'id'  => $oscTaxRate['tax_class_id'],
                            'key' => $oscTaxRate['tax_class_title'],
                        ),
                    ),
                    'customer_tax_classes' => array(
                        array(
                            'id'  => 1,
                            'key' => 'default',
                        ),
                    ),
                    // no customer tax classes in osCommerce
                    'tax_rates'            => array(
                        array(
                            'id'  => $taxRateIdentifier,
                            'key' => $taxRateIdentifier,
                        ),
                    ),
                );
            }
        }

        return array(
            'tax_rates' => $taxRates,
            'tax_rules' => $taxRules,
        );
    }

    /**
     * read the country id from database by iso-2 code
     *
     * @param $name
     *
     * @return mixed
     */
    public function getCountryByIso2Name($name)
    {
        $query         =
            "SELECT c.* FROM " . TABLE_COUNTRIES . " AS c WHERE c.countries_iso_code_2 = \"{$name}\"";
        $result        = xtc_db_query($query);
        $CountryResult = xtc_db_fetch_array($result);

        return $CountryResult;
    }

    /**
     * read the zone data from database by the zone country id
     *
     * @param $zoneCountryId
     *
     * @return array
     */
    public function getZoneByCountryId($zoneCountryId)
    {
        $query         =
            "SELECT * FROM `" . TABLE_ZONES_TO_GEO_ZONES . "` WHERE zone_country_id = '" . $zoneCountryId
            . "' ORDER BY zone_id";
        $result        = xtc_db_query($query);
        $CountryResult = xtc_db_fetch_array($result);

        return $CountryResult;
    }

    /**
     * read the tax class title from database by the tax value
     *
     * @param $taxValue
     *
     * @return string
     */
    public function getTaxClassByValue($taxValue)
    {
        $query          = "SELECT tc.tax_class_title AS title FROM " . TABLE_TAX_RATES . " AS tr
                    JOIN " . TABLE_TAX_CLASS . " AS tc ON tc.tax_class_id = tr.tax_class_id
                    WHERE tr.tax_rate = {$taxValue}";
        $result         = xtc_db_query($query);
        $taxClassResult = xtc_db_fetch_array($result);

        return $taxClassResult["title"];
    }

    /**
     * read the zone id from database by the zone country id
     *
     * @param $zoneCountryId
     *
     * @return mixed
     */
    public function getZoneId($zoneCountryId)
    {
        $query         =
            "SELECT zone_id FROM `" . TABLE_ZONES_TO_GEO_ZONES . "` WHERE geo_zone_id = '" . MODULE_SHIPPING_FLAT_ZONE
            . "' AND zone_country_id = '"
            . $zoneCountryId . "' ORDER BY zone_id";
        $result        = xtc_db_query($query);
        $CountryResult = xtc_db_fetch_array($result);

        return $CountryResult["zone_id"];
    }

    /**
     * read the tax class title from database by the tax class id
     *
     * @param $id
     *
     * @return null
     */
    public function getTaxClassById($id)
    {
        if (empty($id)) {
            return null;
        }
        $query     =
            "SELECT tc.tax_class_title AS title FROM " . TABLE_TAX_CLASS . " AS tc WHERE tc.tax_class_id = {$id}";
        $result    = xtc_db_query($query);
        $taxResult = xtc_db_fetch_array($result);

        return $taxResult["title"];
    }

    /**
     * get the tax rate to a product depending on the ggx shop system version
     *
     * @param int   $productsTaxClassId
     * @param bool  $check
     * @param array $ggxVersion
     * @param int   $countryId
     * @param int   $zoneId
     *
     * @return null|int
     */
    public function getTaxRateToProduct($productsTaxClassId, $check = true, $ggxVersion, $countryId, $zoneId)
    {
        $tax_rate = null;
        if ($check) {
            if ($ggxVersion['main_version'] > 2
                || ($ggxVersion['main_version'] == 2
                    && (($ggxVersion['sub_version'] > 0
                        || $ggxVersion['revision'] >= 10)))
            ) {
                // Add up taxes since the value is later expected to have taxes included
                $tax_rate = xtc_get_tax_rate($productsTaxClassId, $countryId, $zoneId);
            }

            return $tax_rate;
        }

        return xtc_get_tax_rate($productsTaxClassId, $countryId, $zoneId);
    }
}
