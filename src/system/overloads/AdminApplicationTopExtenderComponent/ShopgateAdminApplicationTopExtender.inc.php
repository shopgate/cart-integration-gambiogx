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
	function proceed()
	{
		parent::proceed();

        /******** SHOPGATE **********/
		if(defined('MODULE_PAYMENT_INSTALLED') && strpos(MODULE_PAYMENT_INSTALLED, 'shopgate.php') !== false)
		{
			$t_lang_file_path = DIR_FS_CATALOG . '/shopgate/gambiogx/lang/' . basename($_SESSION['language']) . '/admin/' . basename($_SESSION['language']) . '.php';
			if(file_exists($t_lang_file_path))
			{
				include_once($t_lang_file_path);
			}
		}
		
        define('FILENAME_SHOPGATE', 'shopgate.php');
        define("TABLE_SHOPGATE_ORDERS", "orders_shopgate_order");
        /******** SHOPGATE **********/
	}
}