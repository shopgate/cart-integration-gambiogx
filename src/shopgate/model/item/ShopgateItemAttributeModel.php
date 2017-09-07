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
class ShopgateItemAttributeModel extends ShopgateObject
{
    /**
     * read products attrubute data from database
     *
     * @param int    $productsId
     * @param bool   $normalizeKey
     * @param string $normalizationDelimiter
     *
     * @return array
     *
     * @throws ShopgateLibraryException
     */
    public function getAttributesFromDatabase($productsId, $normalizeKey = false, $normalizationDelimiter = '-')
    {
        $attributes = array();

        $query = sprintf(
            "
            SELECT
                *
            FROM %s AS a
                WHERE a.products_id = %s",
            TABLE_PRODUCTS_ATTRIBUTES,
            $productsId
        );

        $result = xtc_db_query($query);

        if (!$result) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR,
                "Shopgate Plugin - Error checking for table \"" . TABLE_PRODUCTS_ATTRIBUTES . "\".",
                true
            );
        }

        // structure the data in a way so it can be accessed in an easy fashion
        while ($row = xtc_db_fetch_array($result)) {
            $key = $row['products_attributes_id'];
            if ($normalizeKey) {
                $key = implode($normalizationDelimiter, array($row['options_id'], $row['options_values_id']));
            }
            $attributes[$key] = $row;
        }

        return $attributes;
    }

    /**
     * @param int $productsId
     *
     * @return array
     *
     * @throws ShopgateLibraryException
     */
    public function getPropertyCombinationsFromDatabase($productsId)
    {
        $attributes = array();

        $query = sprintf(
            "
            SELECT
                *
            FROM %s AS a
                WHERE a.products_id = %s",
            TABLE_PRODUCTS_PROPERTIES_COMBIS,
            $productsId
        );

        $result = xtc_db_query($query);

        if (!$result) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR,
                "Shopgate Plugin - Error checking for table \"" . TABLE_PRODUCTS_PROPERTIES_COMBIS . "\".",
                true
            );
        }

        // structure the data in a way so it can be accessed in an easy fashion
        while ($row = xtc_db_fetch_array($result)) {
            $attributes[$row['products_properties_combis_id']] = $row;
        }

        return $attributes;
    }
}
