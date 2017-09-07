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

if (!defined('DIR_FS_CATALOG')) {
    define('DIR_FS_CATALOG', dirname(__FILE__) . '/../../../');
}

require_once dirname(__FILE__) . '/../src/shopgate/vendor/autoload.php';
require_once dirname(__FILE__) . '/../src/shopgate/model/item/ShopgateItemModel.php';
require_once dirname(__FILE__) . '/../src/shopgate/model/item/ShopgateItemCartModel.php';
require_once dirname(__FILE__) . '/../src/shopgate/model/item/ShopgateItemXmlModel.php';
require_once dirname(__FILE__) . '/../src/shopgate/model/location/ShopgateLocationModel.php';
require_once dirname(__FILE__) . '/../src/shopgate/helper/ShopgateCustomizerSetHelper.php';
require_once dirname(__FILE__) . '/../src/shopgate/gambiogx/shopgate_config.php';
require_once dirname(__FILE__) . '/../src/shopgate/helper/ShopgateCartHelper.php';
