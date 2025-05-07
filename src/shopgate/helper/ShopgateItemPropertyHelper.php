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

/**
 * Class ShopgateItemPropertyHelper
 */
class ShopgateItemPropertyHelper
{
    /** @var ShopgateDatabaseHelper */
    private $databaseHelper;

    /** @var int */
    protected $languageId;

    /** @var ShopgateConfigGambioGx */
    protected $shopgateConfiguration;

    /**
     * @param ShopgateDatabaseHelper $shopgateDatabaseHelper
     * @param ShopgateConfigGambioGx $shopgateConfiguration
     * @param int                    $languageId
     */
    public function __construct(
        ShopgateDatabaseHelper $shopgateDatabaseHelper,
        ShopgateConfigGambioGx $shopgateConfiguration,
        $languageId
    ) {
        $this->databaseHelper        = $shopgateDatabaseHelper;
        $this->shopgateConfiguration = $shopgateConfiguration;
        $this->languageId            = $languageId;
    }

    /**
     * @param array $product
     *
     * @return string
     */
    public function buildCsvProperties(array $product)
    {
        $properties = array();
        foreach ($this->buildProperties($product) as $property) {
            if (!$property->getValues()) {
                continue;
            }
            $properties[] =
                $property->getName() . '=>' . implode(
                    ', ',
                    $this->accumulatePropertyValues($property->getValues())
                );
        }

        return implode("||", $properties);
    }

    /**
     * @param array $product
     *
     * @return Shopgate_Model_Catalog_Property[]
     */
    public function buildXmlProperties(array $product)
    {
        $properties = $this->buildProperties($product, true);

        $propertyModels = array();
        foreach ($properties as $property) {
            $propertyValues = $property->getValues();

            if (!$propertyValues) {
                continue;
            }

            if (count($propertyValues) == 1) {
                $propertyValue = current($propertyValues);

                $propertyModel = new Shopgate_Model_Catalog_Property();
                $propertyModel->setLabel($property->getName());
                $propertyModel->setUid($propertyValue->getId());
                $propertyModel->setValue($propertyValue->getName());
            } else {
                $propertyModel = $this->assemblePropertyValues($property);
            }

            $propertyModels[] = $propertyModel;
        }

        return $propertyModels;
    }

    /**
     * Read property data from database and prepare the data structure
     * depending on the "$buildDataForXml" parameter
     *
     * @param array $product
     *
     * @param bool  $withoutFsk18Property
     *
     * @return ShopgateItemPropertyModel[]
     */
    public function buildProperties($product, $withoutFsk18Property = false)
    {
        $properties = array();
        if (!$withoutFsk18Property && $fsk18Property = $this->getFsk18Property($product)) {
            $properties[] = $fsk18Property;
        }

        return array_merge($properties, $this->getFilterProperties($product));
    }

    /**
     * @param array $product
     *
     * @return ShopgateItemPropertyModel
     */
    private function getFsk18Property($product)
    {
        if (!empty($product["products_fsk18"]) && $product["products_fsk18"] == 1) {
            $propertyName = preg_replace("/\:/", "", TEXT_FSK18);
            $fskProperty  = new ShopgateItemPropertyModel($propertyName);
            $fskProperty->addValue(new ShopgateItemPropertyValueModel(0, TEXT_YES));

            return $fskProperty;
        }

        return null;
    }

    /**
     * @param ShopgateItemPropertyValueModel[] $propertyValues
     *
     * @return array
     */
    private function accumulatePropertyValues(array $propertyValues)
    {
        $simplePropertyValue = array();
        foreach ($propertyValues as $propertyValue) {
            $simplePropertyValue[] = $propertyValue->getName();
        }

        return $simplePropertyValue;
    }

    /**
     * @param array $product
     *
     * @return string
     */
    public function getFilterPropertiesSql(array $product)
    {
        return "
            SELECT fd.feature_name, fvd.feature_value_id, fvd.feature_value_id, fvd.feature_value_text, fd.feature_id 
            FROM feature_set_to_products   AS fs2p
            JOIN feature_set_values        AS fsv  ON fs2p.feature_set_id  = fsv.feature_set_id
            JOIN feature_value_description AS fvd  ON fsv.feature_value_id = fvd.feature_value_id
            JOIN feature_value             AS fv   ON fvd.feature_value_id = fv.feature_value_id
            JOIN feature_description       AS fd   ON fv.feature_id        = fd.feature_id
            WHERE fs2p.products_id    = {$product['products_id']}
            AND fd.language_id        = {$this->languageId}
            AND fvd.language_id       = {$this->languageId}
            AND fd.feature_name IN {$this->getExportFiltersAsString()}
            ORDER BY fv.sort_order
        ";
    }

    /**
     * @param array $product
     *
     * @return string
     */
    public function getLegacyFilterPropertiesSql(array $product)
    {
        return "
                SELECT fvd.feature_value_id, fd.feature_id, fv.sort_order, fd.feature_name, fvd.feature_value_text
                FROM products_feature_value    AS pfv
                JOIN feature_value             AS fv  ON fv.feature_value_id  = pfv.feature_value_id
                JOIN feature_description       AS fd  ON fd.feature_id        = fv.feature_id
                JOIN feature_value_description AS fvd ON fvd.feature_value_id = pfv.feature_value_id
                WHERE pfv.products_id = {$product['products_id']}
                AND fd.language_id    = {$this->languageId}
                AND fvd.language_id   = {$this->languageId}
                AND fd.feature_name IN {$this->getExportFiltersAsString()}
                ORDER BY fv.sort_order
        ";
    }

    /**
     * @return bool
     */
    public function usesLegacyFilter()
    {
        $requiredTables = array(
            'products_feature_value',
            'feature_value',
            'feature_description',
            'feature_value_description',
        );

        return $this->databaseHelper->checkTables($requiredTables);
    }

    /**
     * @return bool
     */
    public function usesFilter()
    {
        $requiredDatabaseTables = array(
            'feature',
            'feature_description',
            'feature_index',
            'feature_set',
            'feature_set_to_products',
            'feature_set_values',
            'feature_value',
            'feature_value_description',
        );

        return $this->databaseHelper->checkTables($requiredDatabaseTables);
    }

    /**
     * @return string
     */
    protected function getExportFiltersAsString()
    {
        return "('" . implode("', '", (array)$this->shopgateConfiguration->getExportFiltersAsProperties()) . "')";
    }

    /**
     * @param array $product
     *
     * @return array
     */
    private function getFilterProperties($product)
    {
        $properties = array();

        $sqlStatement = null;
        switch (true) {
            case $this->usesFilter():
                $sqlStatement = $this->getFilterPropertiesSql($product);
                break;
            case $this->usesLegacyFilter():
                $sqlStatement = $this->getLegacyFilterPropertiesSql($product);
                break;
            default:
                return array();
        }

        $filters = array();
        $dbQuery = xtc_db_query($sqlStatement);
        while ($filter = xtc_db_fetch_array($dbQuery)) {
            if (!isset($filters[$filter['feature_name']])) {
                $filters[$filter['feature_name']] = array();
            }

            $filters[$filter['feature_name']][] = $filter;
        }

        foreach ($filters as $filterName => $filterValues) {
            $property = new ShopgateItemPropertyModel($filterName);
            foreach ($filterValues as $filterValue) {
                $propertyValue = new ShopgateItemPropertyValueModel(
                    $filterValue['feature_value_id'],
                    $filterValue['feature_value_text']
                );
                $property->addValue($propertyValue);
            }
            $properties[] = $property;
        }

        return $properties;
    }

    /**
     * @param ShopgateItemPropertyModel $shopgateItemPropertyModel
     *
     * @return Shopgate_Model_Catalog_Property
     */
    public function assemblePropertyValues(ShopgateItemPropertyModel $shopgateItemPropertyModel)
    {
        $catalogProperty = new Shopgate_Model_Catalog_Property();
        $catalogProperty->setLabel($shopgateItemPropertyModel->getName());

        $propertyValues = array();
        foreach ($shopgateItemPropertyModel->getValues() as $shopgateItemPropertyValueModel) {
            $propertyValues[] = $shopgateItemPropertyValueModel->getName();
        }

        $catalogProperty->setValue(implode(', ', $propertyValues));

        return $catalogProperty;
    }
}
