<?php
/* --------------------------------------------------------------
   ShopgateApplicationTopExtender.inc.php 2014-07-10 gm
   Gambio GmbH
   http://www.gambio.de
   Copyright (c) 2014 Gambio GmbH
   Released under the GNU General Public License (Version 2)
   [http://www.gnu.org/licenses/gpl-2.0.html]
   --------------------------------------------------------------
*/

class ShopgateAdminApplicationTopExtender extends ShopgateAdminApplicationTopExtender_parent
{
    public function proceed()
    {
        parent::proceed();

        $t_lang_file_path = DIR_FS_CATALOG . 'shopgate/gambiogx/lang/' . basename($_SESSION['language']) . '/admin/shopgate.php';
        if (file_exists($t_lang_file_path)) {
            include_once($t_lang_file_path);
        }
        $t_lang_file_path = DIR_FS_CATALOG . 'shopgate/gambiogx/lang/' . basename($_SESSION['language']) . '/modules/payment/shopgate.php';
        if (file_exists($t_lang_file_path)) {
            include_once($t_lang_file_path);
        }

        define('FILENAME_SHOPGATE', 'shopgate.php');
        define("TABLE_SHOPGATE_ORDERS", "orders_shopgate_order");
        // Define TABLE_ADMIN_ACCESS if it's not already defined by Gambio core
        if (!defined('TABLE_ADMIN_ACCESS')) {
            define('TABLE_ADMIN_ACCESS', 'admin_access');
        }
    }
}
