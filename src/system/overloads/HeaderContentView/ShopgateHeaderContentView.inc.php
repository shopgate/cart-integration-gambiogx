<?php
/* --------------------------------------------------------------
  ShopgateHeaderContentView.inc.php 2014-07-21 gm
  Gambio GmbH
  http://www.gambio.de
  Copyright (c) 2014 Gambio GmbH
  Released under the GNU General Public License (Version 2)
  [http://www.gnu.org/licenses/gpl-2.0.html]
  --------------------------------------------------------------
 */

class ShopgateHeaderContentView extends ShopgateHeaderContentView_parent
{
	public function get_modules_html(&$p_html_array)
	{
		parent::get_modules_html($p_html_array);

		if(defined('MODULE_PAYMENT_INSTALLED') && strpos(MODULE_PAYMENT_INSTALLED, 'shopgate.php') !== false)
		{
			$t_uninitialized_array = $this->get_uninitialized_variables(array('languages_id'));

			if(empty($t_uninitialized_array))
			{
				/******** SHOPGATE **********/
				include(DIR_FS_CATALOG . '/shopgate/gambiogx/includes/header.php');
				$p_html_array['head']['top'] .= $shopgateJsHeader;
				$p_html_array['body']['top'] .= $shopgateMobileHeader;
				/******** SHOPGATE **********/
			}
			else
			{
				trigger_error("Variable(s) " . implode(', ', $t_uninitialized_array) . " do(es) not exist in class " . get_class($this) . " or is/are null", E_USER_ERROR);
			}
		}
		
		return $p_html_array;
	}
}