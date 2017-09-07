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
class ShopgateCartHelper
{
    /**
     * @param string | int $combinationId - property combination ID, e.g. Size L & Color Red will have one ID
     * @param string | int $itemNumber
     * @param string | int $languageId
     *
     * @return array
     */
    public function getPropertyCombinations($combinationId, $itemNumber, $languageId)
    {
        $combinations = array();
        $qry
                      = "
                        SELECT
                            `ppc`.`products_properties_combis_id`,
                            `pd`.`properties_name`,
                            `pvd`.`values_name`
                        FROM `" . TABLE_PRODUCTS_PROPERTIES_COMBIS_VALUES . "` AS `ppcv`
                            INNER JOIN `" . TABLE_PRODUCTS_PROPERTIES_COMBIS . "` AS `ppc` ON (`ppcv`.`products_properties_combis_id` = `ppc`.`products_properties_combis_id` AND `ppc`.`products_id` = '$itemNumber')
                            INNER JOIN `" . TABLE_PROPERTIES_VALUES . "` AS `pv` ON(`ppcv`.`properties_values_id` = `pv`.`properties_values_id`)
                            INNER JOIN `" . TABLE_PROPERTIES_VALUES_DESCRIPTION . "` AS `pvd` ON(`ppcv`.`properties_values_id` = `pvd`.`properties_values_id` AND `pvd`.`language_id` = '$languageId')
                            INNER JOIN `" . TABLE_PROPERTIES . "` AS `p` ON(`pv`.`properties_id` = `p`.`properties_id`)
                            INNER JOIN `" . TABLE_PROPERTIES_DESCRIPTION . "` AS `pd` ON(`pv`.`properties_id` = `pd`.`properties_id`  AND `pd`.`language_id` = '$languageId')
                        WHERE `ppcv`.`products_properties_combis_id` = '$combinationId'
                        ORDER BY `p`.`sort_order`, `pv`.`sort_order`
                    ;";

        $qResult = xtc_db_query($qry);

        if (xtc_db_num_rows($qResult) > 0) {
            while ($combinations[] = xtc_db_fetch_array($qResult)) {
                //intentionally blank
            }
        }

        return array_filter($combinations);
    }

    /**
     * Combination setting, by default this is 0, but if
     * rewritten, it takes priority over how stock is calculated
     * for product
     *
     * @param string | int $itemNumber - product ID
     *
     * @return string | false
     */
    public function getPropertyStockSetting($itemNumber)
    {
        $qry
            = "SELECT `pr`.`use_properties_combis_quantity`
                FROM `" . TABLE_PRODUCTS . "` AS `pr`
                WHERE `pr`.`products_id` = '$itemNumber';";

        $qResult = xtc_db_query($qry);
        $result  = xtc_db_fetch_array($qResult);

        return isset($result['use_properties_combis_quantity'])
            ? $result['use_properties_combis_quantity']
            : false;
    }

    /**
     * Retrieve values on whether to reduce the main parent product Qty
     * or the property Qty. There are global configurations that are
     * honored if 0 setting is passed, else the global configuration
     * is ignored and we use special per product settings.
     *
     * @param string $combisQtySetting
     *
     * @return array - (bool|null)
     */
    public function getStockReductionSettings($combisQtySetting)
    {
        $updateItemsStock = $updatePropertiesStock = null;
        switch ($combisQtySetting) {
            case '0': // default stock setting (considers the STOCK_CHECK & ATTRIBUTE_STOCK_CHECK constant)
                if (STOCK_CHECK === 'false') {
                    $updateItemsStock      = false;
                    $updatePropertiesStock = false;
                } else {
                    // If attribute stock is enabled, do not update item stock as well
                    $updateItemsStock      = ATTRIBUTE_STOCK_CHECK === 'false';
                    $updatePropertiesStock = ATTRIBUTE_STOCK_CHECK === 'true';
                }
                break;
            case '1': // products stock (ignores the STOCK_CHECK constant)
                $updateItemsStock      = true;
                $updatePropertiesStock = false;
                break;
            case '2': // combi stock
                $updateItemsStock      = false;
                $updatePropertiesStock = true;
                break;
            case '3': // no check at all
                $updateItemsStock      = false;
                $updatePropertiesStock = false;
                break;
            default:
                break;
        }

        return array($updateItemsStock, $updatePropertiesStock);
    }

    /**
     * @param string | int - $combinationId
     * @param int $reduceBy - amount to decrement the property Qty by
     */
    public function reducePropertyStockQty($combinationId, $reduceBy = 1)
    {
        $deductPropertyQty = array(
            'combi_quantity' => 'combi_quantity - ' . $reduceBy,
        );
        xtc_db_perform(
            TABLE_PRODUCTS_PROPERTIES_COMBIS,
            $deductPropertyQty,
            'update',
            "products_properties_combis_id = '$combinationId'",
            'db_link',
            false
        );
    }
}
