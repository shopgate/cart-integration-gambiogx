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
class ShopgateReviewModel extends Shopgate_Model_Catalog_Review
{
    /**
     * @var int
     */
    protected $languageId;

    public function __construct($languageId)
    {
        $this->languageId = $languageId;
    }

    /**
     * return the review data from database
     *
     * @param int $limit
     * @param int $offset
     * @param     $uids
     *
     * @return array
     */
    public function getReviewData($limit = 10, $offset = 0, $uids)
    {
        $sql
            = "
        SELECT
            r.reviews_id,
            r.products_id,
            r.customers_name,
            r.reviews_rating,
            r.date_added,
            rd.reviews_text
        FROM
        " . TABLE_REVIEWS . " as r
        INNER JOIN
        " . TABLE_REVIEWS_DESCRIPTION . " as rd ON r.reviews_id = rd.reviews_id
        WHERE rd.languages_id = '" . $this->languageId . "' " . (!empty($uids)
                ? " AND r.reviews_id IN ('" .
                implode("','", $uids) . "') "
                : "") .
            "ORDER BY r.products_id, r.reviews_id ASC" . (!empty($limit)
                ? " LIMIT " . $offset . "," . $limit
                : "");

        $this->log("reviews query:" . $sql, ShopgateLogger::LOGTYPE_DEBUG);

        $query   = xtc_db_query($sql);
        $reviews = array();

        while ($entry = xtc_db_fetch_array($query)) {
            $reviews[] = $entry;
        }

        return $reviews;
    }

    /**
     * calculates shopgate score from shop score
     *
     * @param int $shopScore
     *
     * @return int
     */
    public function buildScore($shopScore)
    {
        return intval($shopScore * 2);
    }

    /**
     * returns a Shopgate review title from review text
     *
     * @param string $text
     *
     * @return string
     */
    public function buildTitle($text)
    {
        return substr($text, 0, 20) . "";
    }

    /**
     * returns a Shopgate time string
     *
     * @param string $date
     *
     * @return string
     */
    public function buildDate($date)
    {
        return empty($date)
            ? ""
            : strftime("%Y-%m-%d", strtotime($date));
    }
}
