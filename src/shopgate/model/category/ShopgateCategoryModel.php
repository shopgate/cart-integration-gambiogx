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
class ShopgateCategoryModel extends Shopgate_Model_Catalog_Category
{
    /**
     * @var ShopgateConfigGambioGx $config
     */
    private $config;

    /**
     * @var
     */
    private $languageId;

    /**
     * @var GMSEOBoost_ORIGIN
     */
    private $gmSEOBoost;

    /**
     * @var
     */
    private $gmSEOBoostCategoriesEnabled;

    /**
     * instance of the singleton ShopgateLogger class
     *
     * @var ShopgateLogger $log
     */
    private $log;

    /**
     * represents a default row with all needed category fields
     *
     * @var
     */
    private $defaultCategoryRow;

    /**
     * ShopgateCategoryModel constructor.
     *
     * @param ShopgateConfigGambioGx $config
     * @param                        $languageId
     */
    public function __construct(ShopgateConfigGambioGx $config, $languageId)
    {
        $this->config     = $config;
        $this->languageId = $languageId;
        $this->log        = ShopgateLogger::getInstance();
    }

    /**
     * @param mixed $defaultCategoryRow
     */
    public function setDefaultCategoryRow($defaultCategoryRow)
    {
        $this->defaultCategoryRow = $defaultCategoryRow;
    }

    /**
     * generates a list of categories
     *
     * @param int   $limit
     * @param int   $offset
     * @param array $uids
     *
     * @return array
     */
    public function getAllCategories($limit = 0, $offset = 0, $uids = array())
    {
        $categories = array();
        $this->setSeoBoost();
        $hasWhere = false;

        $qry = "SELECT DISTINCT
                    c.categories_id,
                    c.parent_id,
                    c.categories_image,
                    c.categories_status,
                    c.sort_order,
                    cd.categories_name
                FROM " . TABLE_CATEGORIES . " c
                INNER JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd
                    ON (c.categories_status = 1
                    AND c.categories_id = cd.categories_id
                    AND cd.language_id = $this->languageId)";

        if (GROUP_CHECK == "true") {
            $qry      .= " WHERE c.group_permission_" . DEFAULT_CUSTOMERS_STATUS_ID . " = 1";
            $hasWhere = true;
        }

        if (!empty($uids)) {
            $qry .= (!$hasWhere)
                ? " WHERE "
                : " AND ";
            $qry .= "c.categories_id IN (" . implode(',', $uids) . ")";
        }

        $qry .= " ORDER BY c.categories_id ASC ";

        $qry .= (!empty($limit)
            ? " LIMIT " . $offset . "," . $limit
            : "");

        $result = xtc_db_query($qry);

        while ($item = xtc_db_fetch_array($result)) {
            $row = $this->defaultCategoryRow;

            $row["category_number"] = $item["categories_id"];
            $row["parent_id"]       = (empty($item["parent_id"]) || ($item['parent_id'] == $item['categories_id']))
                ? ""
                : $item["parent_id"];
            $row["category_name"]   =
                htmlentities($item["categories_name"], ENT_NOQUOTES, $this->config->getEncoding());

            if (!empty($item["categories_image"])) {
                $row["url_image"] =
                    HTTP_SERVER . DIR_WS_CATALOG . DIR_WS_IMAGES . "categories" . DS . $item["categories_image"];
            }

            if (!empty($item["sort_order"]) || ((string)$item['sort_order'] === '0')) {
                // reversed means the contrary to ordering system in shopgate - order_index is a priority system - high number = top position
                // so just taking over the values means reversing the order
                $row["order_index"] = ($this->config->getReverseCategoriesSortOrder())
                    ? $row["order_index"] = $item["sort_order"]
                    : $row["order_index"] = $this->getMaxSortOrder() - $item["sort_order"];
            }

            $row["is_active"] = $item["categories_status"];

            $row["url_deeplink"]
                = rtrim(HTTP_SERVER, '/') . DIR_WS_CATALOG .
                (($this->gmSEOBoostCategoriesEnabled)
                    ? $this->gmSEOBoost->get_boosted_category_url($item["categories_id"], $this->languageId)
                    : "index.php?cat=c" . $item["categories_id"]);

            $categories[] = $row;
        }

        return $categories;
    }

    /**
     * fill class vars with seo information for categories
     */
    private function setSeoBoost()
    {
        $this->gmSEOBoost                  = new GMSEOBoost();
        $this->gmSEOBoostCategoriesEnabled = false;
        if (file_exists(DIR_FS_CATALOG . '.htaccess') && function_exists('gm_get_conf')) {
            $this->gmSEOBoostCategoriesEnabled = gm_get_conf('GM_SEO_BOOST_CATEGORIES')
                ? 1
                : 0;
        }
    }

    /**
     * returns the maximum sort order value
     *
     * @return int
     */
    private function getMaxSortOrder()
    {
        $maxOrder = 0;
        if (!$this->config->getReverseCategoriesSortOrder()) {
            $qry      = "SELECT MAX( sort_order ) sort_order FROM `" . TABLE_CATEGORIES . "`";
            $result   = xtc_db_query($qry);
            $maxOrder = xtc_db_fetch_array($result);
            $maxOrder = $maxOrder["sort_order"] + 1;
        }

        return $maxOrder;
    }
}
