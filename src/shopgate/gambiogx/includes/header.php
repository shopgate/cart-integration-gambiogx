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

if (function_exists("sgIsHomepage") == false) {
    function sgIsHomepage()
    {
        $scriptName = explode('/', $_SERVER['SCRIPT_NAME']);
        $scriptName = end($scriptName);

        if ($scriptName != 'index.php') {
            return false;
        }

        return true;
    }
}

$shopgateMobileHeader = '';// compatibility to older versions
$shopgateJsHeader     = '';

if (defined('MODULE_PAYMENT_SHOPGATE_STATUS') && MODULE_PAYMENT_SHOPGATE_STATUS === 'True') {
    if (file_exists(DIR_FS_CATALOG . 'shopgate/vendor/autoload.php')) {
        include_once DIR_FS_CATALOG . 'shopgate/vendor/autoload.php';
    }

    include_once DIR_FS_CATALOG . 'shopgate/gambiogx/shopgate_config.php';

    try {
        $shopgateCurrentLanguage = isset($_SESSION['language_code'])
            ? strtolower($_SESSION['language_code'])
            : 'de';
        $shopgateHeaderConfig    = new ShopgateConfigGambioGx();
        $shopgateHeaderConfig->loadByLanguage($shopgateCurrentLanguage);

        if ($shopgateHeaderConfig->checkUseGlobalFor($shopgateCurrentLanguage)) {
            $shopgateRedirectThisLanguage = in_array(
                $shopgateCurrentLanguage,
                $shopgateHeaderConfig->getRedirectLanguages()
            );
        } else {
            $shopgateRedirectThisLanguage = true;
        }

        if ($shopgateRedirectThisLanguage) {
            // SEO modules fix (for Commerce:SEO and others): if session variable was set, SEO did a redirect and most likely cut off our GET parameter
            // => reconstruct here, then unset the session variable
            if (!empty($_SESSION['shopgate_redirect'])) {
                $_GET['shopgate_redirect'] = 1;
                unset($_SESSION['shopgate_redirect']);
            }

            // instantiate and set up redirect class
            $shopgateBuilder    = new ShopgateBuilder($shopgateHeaderConfig);
            $shopgateRedirector = $shopgateBuilder->buildMobileRedirect($_SERVER['HTTP_USER_AGENT'], $_GET, $_COOKIE);

            ##################
            # redirect logic #
            ##################
            //from Version 2.1 $product was changed into $this->coo_product
            $product = (!empty($product))
                ? $product
                : $this->coo_product;

            if (($product instanceof product) && $product->isProduct && !empty($product->pID)) {
                $shopgateJsHeader = $shopgateRedirector->buildScriptItem($product->pID);
            } elseif (!empty($current_category_id) || !empty($GLOBALS['current_category_id'])) {
                if (empty($current_category_id) && !empty($GLOBALS['current_category_id'])) {
                    // This works for Gambio Version 2.1.x and 2.2.x
                    $current_category_id = $GLOBALS['current_category_id'];
                }

                if (is_array($shopgateHeaderConfig->getDisabledRedirectCategoryIds())
                    && in_array($current_category_id, $shopgateHeaderConfig->getDisabledRedirectCategoryIds())) {
                    $shopgateJsHeader = '';
                    $shopgateRedirector->supressRedirectTechniques(true, true);
                } else {
                    $shopgateJsHeader = $shopgateRedirector->buildScriptCategory($current_category_id);
                }
            } elseif (isset($_GET['keywords'])) {
                $shopgateJsHeader = $shopgateRedirector->buildScriptSearch($_GET['keywords']);
            } elseif (isset($_GET['manu'], $_GET['manufacturers_id'])) {
                $manExistResult   =
                    xtc_db_query(
                        "SELECT manufacturers_name FROM " . TABLE_MANUFACTURERS
                        . " WHERE manufacturers_id = {$_GET['manufacturers_id']};"
                    );
                $manufacturer     = xtc_db_fetch_array($manExistResult);
                $shopgateJsHeader = $shopgateRedirector->buildScriptBrand($manufacturer['manufacturers_name']);
            } elseif (sgIsHomepage()) {
                $shopgateJsHeader = $shopgateRedirector->buildScriptShop();
            } else {
                $shopgateJsHeader = $shopgateRedirector->buildScriptDefault();
            }
        }
    } catch (ShopgateLibraryException $e) {
    }
}
