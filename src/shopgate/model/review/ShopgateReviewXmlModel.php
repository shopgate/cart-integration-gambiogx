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
class ShopgateReviewXmlModel extends ShopgateReviewModel
{
    public function setUid()
    {
        parent::setUid($this->item['reviews_id']);
    }

    public function setItemUid()
    {
        parent::setItemUid($this->item['products_id']);
    }

    public function setScore()
    {
        parent::setScore($this->buildScore($this->item['reviews_rating']));
    }

    public function setReviewerName()
    {
        parent::setReviewerName($this->item['customers_name']);
    }

    public function setDate()
    {
        parent::setDate($this->buildDate($this->item['date_added']));
    }

    public function setTitle()
    {
        parent::setTitle($this->buildTitle(''));
    }

    public function setText()
    {
        parent::setText($this->item['reviews_text']);
    }
}
