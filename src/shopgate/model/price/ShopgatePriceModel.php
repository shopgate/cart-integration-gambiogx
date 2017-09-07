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
class ShopgatePriceModel extends Shopgate_Model_Catalog_Price
{
    private $exchangeRate;

    /**
     * @var ShopgateConfigGambioGx $config
     */
    private $config;

    /**
     * @var int
     */
    private $languageId;

    /**
     * @var ShopgateLogger
     */
    private $log;

    /**
     * @param ShopgateConfigGambioGx $config
     * @param int                    $languageId
     * @param int                    $exchangeRate
     */
    public function __construct(ShopgateConfigGambioGx $config, $languageId, $exchangeRate)
    {
        parent::__construct();
        $this->exchangeRate = $exchangeRate;
        $this->config       = $config;
        $this->languageId   = $languageId;
        $this->log          = ShopgateLogger::getInstance();
    }

    /**
     * read the max price data to customer groups from the database
     *
     * @return mixed
     */
    public function getCustomerGroupMaxPriceDiscount()
    {
        $this->log("execute SQL customer group ...", ShopgateLogger::LOGTYPE_DEBUG);

        $result = array(
            'customerGroupMaxDiscount'           => "",
            'customerGroupMaxDiscountAttributes' => "",
        );

        // get customer-group first
        $qry = "SELECT"
            . " status.customers_status_name,"
            . " status.customers_status_discount,"
            . " status.customers_status_discount_attributes"
            . " FROM " . TABLE_CUSTOMERS_STATUS . " AS status"
            . " WHERE status.customers_status_id = " . DEFAULT_CUSTOMERS_STATUS_ID
            . " AND status.language_id = " . $this->languageId
            . ";";

        // Check if the customer group exists (ignore if not)
        $queryResult = xtc_db_query($qry);
        if ($queryResult) {
            $customerGroupResult = xtc_db_fetch_array($queryResult);
            if (!empty($customerGroupResult) && isset($customerGroupResult['customers_status_discount'])) {
                $result['customerGroupMaxDiscount']           = $customerGroupResult['customers_status_discount'];
                $result['customerGroupMaxDiscountAttributes'] =
                    $customerGroupResult['customers_status_discount_attributes']
                        ? true
                        : false;
            }
        }

        return $result;
    }

    /**
     * calculate the price to an product regarding special prices etc.
     *
     * @param array  $item
     * @param double $tax_rate
     * @param float  $price
     * @param float  $oldPrice
     * @param float  $productDiscount
     * @param bool   $withCustomerGroup
     * @param bool   $withTax
     */
    public function getPriceToItem(
        $item,
        $tax_rate,
        &$price,
        &$oldPrice,
        &$productDiscount,
        $withCustomerGroup = true,
        $withTax = true
    ) {
        $customerGroupMaxPriceDiscount = 0;
        if ($withCustomerGroup) {
            $result                        = $this->getCustomerGroupMaxPriceDiscount();
            $customerGroupMaxPriceDiscount = $result['customerGroupMaxDiscount'];
        }

        $price    = $item["products_price"];
        $oldPrice = '';

        // Special offers for a Customer group
        $pOffers = $this->getPersonalOffersPrice($item);
        if (!empty($pOffers) && round($pOffers, 2) > 0) {
            $price = $pOffers;
            // Gambio also tells the old price if the offer is lower than the actual price for the customer group
            if ($pOffers < $item["products_price"]) {
                $oldPrice = $item["products_price"];
            }
        }

        // General special offer or customer group price reduction
        if (!empty($item["specials_new_products_price"])) {
            if (STOCK_CHECK == 'true' && STOCK_ALLOW_CHECKOUT == 'false') {
                if ($item["specials_quantity"] > 0) {
                    // Nur wenn die quantity > 0 ist dann specialprice setzen,
                    // ansonsten normalen Preis mit normalem Stock
                    $item["products_quantity"] =
                        $item["specials_quantity"] > $item["products_quantity"]
                            ? $item["products_quantity"]
                            : $item["specials_quantity"];
                }
            }
            // setting specialprice
            $oldPrice = $item["products_price"];
            $price    = $item["specials_new_products_price"];

            $orderInfos['is_special_price'] = 1;
        } elseif (!empty($customerGroupMaxPriceDiscount) && round($customerGroupMaxPriceDiscount, 2) > 0
            && !empty($item['products_discount_allowed'])
            && round($item['products_discount_allowed'], 2) > 0
        ) {
            $productDiscount = round($item['products_discount_allowed'], 2);

            // Limit discount to the customer groups maximum discount
            if (round($customerGroupMaxPriceDiscount, 2) < $productDiscount) {
                $productDiscount = round($customerGroupMaxPriceDiscount, 2);
            }

            // IMPORTANT NOTICE: GambioGX shoppingsystem takes the higher price value between the offers and the default price,
            // when a discount price is set and then subtracts the discount set for the article
            if ($item["products_price"] > $price) {
                $price = $item["products_price"];
            }
            $oldPrice = $price;
            if ($oldPrice < $item['products_price']) {
                $oldPrice = $item['products_price'];
            }

            // Reduce price to the discounted price
            $price = $this->getDiscountPrice($price, $productDiscount);
        }
        $price *= $this->exchangeRate;
        if ($withTax) {
            $price = $price * (1 + ($tax_rate / 100));
        }

        if (!empty($oldPrice)) {
            $oldPrice = $oldPrice * $this->exchangeRate;
            if ($withTax) {
                $oldPrice = $oldPrice * (1 + ($tax_rate / 100));
            }
        }
    }

    /**
     * Takes a price value and a discount percent value and returns the new discounted price
     *
     * @param float $price
     * @param float $discountPercent
     *
     * @return float
     */
    public function getDiscountPrice($price, $discountPercent)
    {
        $discountedPrice = $price * (1 - $discountPercent / 100);

        return $discountedPrice;
    }

    /**
     * read the offer price from the database regarding the customer group
     *
     * @param mixed[] $product
     *
     * @return float
     */
    private function getPersonalOffersPrice($product)
    {
        $this->log("execute _getPersonalOffersPrice() ...", ShopgateLogger::LOGTYPE_DEBUG);

        $customerStatusId = DEFAULT_CUSTOMERS_STATUS_ID;
        if (empty($customerStatusId)) {
            return false;
        }

        $qry = "SELECT * FROM " . TABLE_PERSONAL_OFFERS_BY . "$customerStatusId
        WHERE products_id = '" . $product["products_id"] . "'
        AND quantity = 1";

        $qry = xtc_db_query($qry);
        if (!$qry) {
            return false;
        }

        $specialOffer = xtc_db_fetch_array($qry);

        return floatval($specialOffer["personal_offer"]);
    }
}
