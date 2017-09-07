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
class ShopgateItemCartModel extends ShopgateObject
{
    private $languageId;

    /**
     * ShopgateItemCartModel constructor.
     *
     * @param $languageId
     */
    public function __construct($languageId)
    {
        $this->languageId = $languageId;
    }

    /**
     * if the current order item (product) is an child product the item number is
     * generated in the schema <productId>_<attributeId>
     *
     * this function returns the id, the product has in the shop system
     *
     * @param ShopgateOrderItem $product
     *
     * @return mixed
     */
    public function getProductsIdFromCartItem($product)
    {
        $id = $product->getParentItemNumber();
        if (empty($id)) {
            $info = json_decode($product->getInternalOrderInfo(), true);
            if (!empty($info) && isset($info["base_item_number"])) {
                $id = $info["base_item_number"];
            }
        }

        return !empty($id)
            ? $id
            : $product->getItemNumber();
    }

    /**
     * gather all uids from options of a product
     *
     * @param ShopgateOrderItem $product
     *
     * @return array
     */
    public function getCartItemOptionIds($product)
    {
        $optionIdArray = array();
        $options       = $product->getOptions();
        if (!empty($options)) {
            foreach ($options as $option) {
                $optionIdArray[] = $option->getValueNumber();
            }
        }

        return $optionIdArray;
    }

    /**
     * gather all uids from attributes of a product
     *
     * @param ShopgateOrderItem $product
     *
     * @return array
     */
    public function getCartItemAttributeIds($product)
    {
        $attributeIdArray = array();
        $orderInfo        = json_decode($product->getInternalOrderInfo(), true);

        if (empty($orderInfo)) {
            return $attributeIdArray;
        }

        foreach ($orderInfo as $info) {
            if (is_array($info)) {
                foreach ($info as $key => $value) {
                    $attributeIdArray[] = $key;
                }
            }
        }

        return $attributeIdArray;
    }

    /**
     * Takes a product with attributes and creates a unique key of option-value selections that are required to
     * identify it as one specific attribute. The attribute selection is a list of normalized keys.
     *
     * @param ShopgateOrderItem $product
     * @param string            $delimiter
     *
     * @return array
     */
    public function getCartItemAttributeSelection($product, $delimiter = '-')
    {
        $result    = array();
        $orderInfo = json_decode($product->getInternalOrderInfo(), true);

        if (empty($orderInfo)) {
            return $result;
        }

        foreach ($orderInfo as $key => $attribute) {
            if (preg_match('/^attribute_.*$/', $key)) {
                // there is only one element per attribute, so get the current and only "key"
                $selection = $attribute[key($attribute)];
                $result[]  = implode($delimiter, $selection);
            }
        }

        return $result;
    }

    /**
     * read product data from database
     *
     * @param ShopgateOrderItem $item
     *
     * @return array|bool|mixed
     * @throws ShopgateLibraryException
     */
    public function getCartItemFromDatabase(ShopgateOrderItem $item)
    {
        $query = sprintf(
            "SELECT
                p.*,
                sp.specials_new_products_price
                FROM %s AS p
                LEFT JOIN %s AS sp ON sp.products_id = p.products_id
                WHERE p.products_id = %s",
            TABLE_PRODUCTS,
            TABLE_SPECIALS,
            $this->getProductsIdFromCartItem($item)
        );

        $result = xtc_db_query($query);

        if (!$result) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR,
                "Shopgate Plugin - Error checking for table \"" . TABLE_PRODUCTS . "\".",
                true
            );
        }

        return xtc_db_fetch_array($result);
    }

    /**
     * Reads all products and specials in given cart from database
     *
     * @param ShopgateCart $cart
     *
     * @return array
     * @throws ShopgateLibraryException
     */
    public function getCartItemsFromDatabase(ShopgateCart $cart)
    {
        $result       = array();
        $cartProducts = $cart->getItems();

        $itemIds = array();
        foreach ($cartProducts as $product) {
            $productsId           = $this->getProductsIdFromCartItem($product);
            $itemIds[$productsId] = $productsId;
        }

        $itemQuantityQuery = "
            SELECT 
                `p`.*,
                `sp`.`specials_new_products_price`
            FROM `" . TABLE_PRODUCTS . "` AS `p`
                LEFT JOIN `" . TABLE_SPECIALS . "` AS `sp` ON `sp`.`products_id` = `p`.`products_id`
            WHERE `p`.`products_id` IN (" . implode(",", $itemIds) . ");
        ";

        $qResult = xtc_db_query($itemQuantityQuery);
        if (!$qResult) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR,
                'Shopgate Plugin - Error reading items from tables "'
                . TABLE_PRODUCTS . '" and "' . TABLE_SPECIALS . '".',
                true
            );
        }

        while ($item = xtc_db_fetch_array($qResult)) {
            $result[$item['products_id']] = $item;
        }

        return $result;
    }

    /**
     * read all option data to an product from database and
     * create an ShopgateOrderItemOption object with the data
     *
     * @param $sgProduct
     * @param $orderInfos
     * @param $tax_rate
     *
     * @return array
     * @throws ShopgateLibraryException
     */
    public function getCartItemOptions(&$sgProduct, $orderInfos, $tax_rate)
    {
        $infos            = json_decode($orderInfos, true);
        $price            = 0;
        $weight           = 0;
        $resultAttributes = array();
        if (is_array($infos)) {
            foreach ($infos as $key => $attributes) {
                if (strpos($key, "attribute_") !== false) {
                    foreach ($attributes as $attributeId => $attribute) {
                        $attributeQuery
                            = "SELECT
                                    po.products_options_id AS 'option_number',
                                    po.products_options_name AS 'name',
                                    pov.products_options_values_id AS 'value_number',
                                    pov.products_options_values_name AS 'value',
                                    pa.weight_prefix,
                                    pa.options_values_weight,
                                    pa.options_values_price AS 'price',
                                    pa.price_prefix AS 'prefix'
                                FROM " . TABLE_PRODUCTS . " AS p
                                LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa ON (pa.products_id=p.products_id)
                                LEFT JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " AS pov ON (pov.products_options_values_id = pa.options_values_id AND pov.language_id={$this->languageId})
                                LEFT JOIN " . TABLE_PRODUCTS_OPTIONS . " AS po ON (po.products_options_id = pa.options_id AND po.language_id = {$this->languageId})
                                WHERE pov.products_options_values_name != 'TEXTFELD' 
                                AND po.products_options_id = {$attribute['options_id']} 
                                AND pov.products_options_values_id = {$attribute['options_values_id']} AND pa.products_attributes_id = {$attributeId};";

                        $attributeResult = xtc_db_query($attributeQuery);

                        if (!$attributeResult) {
                            throw new ShopgateLibraryException(
                                ShopgateLibraryException::PLUGIN_DATABASE_ERROR,
                                "Shopgate Plugin - Error checking for table \"" . TABLE_PRODUCTS . "\".", true
                            );
                        }

                        while ($attrRow = xtc_db_fetch_array($attributeResult)) {
                            $sgvariation = new ShopgateOrderItemOption();
                            $sgvariation->setName($attrRow["name"]);
                            $sgvariation->setValue($attrRow["value"]);
                            $sgvariation->setValueNumber($attrRow["value_number"]);
                            $sgvariation->setOptionNumber($attrRow["option_number"]);
                            $resultAttributes[] = $sgvariation;
                            $price              += ($attrRow["prefix"] == "-")
                                ? ($attrRow["price"] * (-1))
                                : $attrRow["price"];
                            $weight             += ($attrRow["weight_prefix"] == "-")
                                ? ($attrRow["options_values_weight"] * (-1))
                                : $attrRow["options_values_weight"];
                        }
                    }
                }
            }
        }
        if ($sgProduct instanceof ShopgateCartItem) {
            $sgProduct->setUnitAmount($sgProduct->getUnitAmount() + $price);
            $sgProduct->setUnitAmountWithTax($sgProduct->getUnitAmountWithTax() + ($price * (1 + ($tax_rate / 100))));
        }

        return $resultAttributes;
    }

    /**
     * calculate the weight to an product regarding the weight of options
     *
     * @param $products
     *
     * @return mixed
     */
    public function getProductsWeight($products)
    {
        $calculatedWeight = 0;
        foreach ($products as $product) {
            /**
             * @var $product ShopgateOrderItem
             */

            $weight       = 0;
            $optionIds    = $this->getCartItemOptionIds($product);
            $attributeIds = $this->getCartItemAttributeIds($product);
            $pId          = $this->getProductsIdFromCartItem($product);

            if (count($optionIds) != 0 || count($attributeIds) != 0) {
                // calculate the additional attribute/option  weight
                $query = "SELECT SUM(CONCAT(weight_prefix, options_values_weight)) AS weight FROM "
                    . TABLE_PRODUCTS_ATTRIBUTES . " AS pa WHERE ";

                $conditions = array();
                if (count($optionIds) > 0) {
                    $conditions[] =
                        " (pa.products_id = {$pId} AND pa.options_values_id IN (" . implode(",", $optionIds) . ")) ";
                }
                if (count($attributeIds) > 0) {
                    $conditions[] =
                        " (pa.products_id = {$pId} AND pa.products_attributes_id IN (" . implode(",", $attributeIds)
                        . ")) ";
                }

                $query        .= implode(' OR ', $conditions);
                $fetchedQuery = xtc_db_query($query);
                $result       = xtc_db_fetch_array($fetchedQuery);
                $weight       += $result["weight"] * $product->getQuantity();
            }

            if (!empty($pId)) {
                // calculate the "base" product weight
                $query  = xtc_db_query(
                    "select products_weight from " . TABLE_PRODUCTS . " AS p where p.products_id = {$pId}"
                );
                $result = xtc_db_fetch_array($query);

                $weight += $result["products_weight"] * $product->getQuantity();
            }

            $calculatedWeight += $weight;
        }

        return $calculatedWeight;
    }
}
