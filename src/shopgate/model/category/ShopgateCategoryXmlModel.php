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
class ShopgateCategoryXmlModel extends ShopgateCategoryModel
{
    public function setUid()
    {
        parent::setUid($this->item['category_number']);
    }

    public function setSortOrder()
    {
        parent::setSortOrder($this->item['order_index']);
    }

    public function setParentUid()
    {
        parent::setParentUid($this->item["parent_id"]);
    }

    public function setIsActive()
    {
        parent::setIsActive($this->item['is_active']);
    }

    public function setName()
    {
        parent::setName($this->item['category_name']);
    }

    public function setDeeplink()
    {
        parent::setDeeplink($this->item['url_deeplink']);
    }

    public function setImage()
    {
        if ($this->item["url_image"]) {
            $image = new Shopgate_Model_Media_Image();
            $image->setUid(1);
            $image->setSortOrder(1);
            $image->setUrl($this->item["url_image"]);
            $image->setTitle($this->item["category_name"]);
            parent::setImage($image);
        }
    }
}
