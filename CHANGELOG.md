# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Fixed
- customer login, when a guest account exists with the same email address
- customer registration, when a guest account exists with the same email address

## [2.9.54] - 2018-03-14
### Fixed
- fixed path to shopgate cart integration sdk config folder

### Changed
- changed the class with which payment fees are added to the order_totals db table from ot_shipping to ot_payment

## [2.9.53] - 2017-11-22
### Fixed
- incompatibility with PHP 7.1.x, which caused missing attributes on order import
- wrong total class for cash on delivery order fees, now using ot_cod_fee
- stock update when orders contains both a simple product and a product with a property
- Shopgate plugin can now find product images that are stored in the info_images directory

### Added
- check for Gambio version so order weight total is only added when the Gambio version supports this

### Changed
- uses Shopgate Cart Integration SDK 2.9.71

## [2.9.52] - 2017-11-06
### Added
- logic to load legacy configuration to avoid update issues

### Fixed
- sort order of products within categories with show sub products setting
- coupon being given for deactivated customer group discount
- total weight order now added to desktop order

### Changed
- Shopgate Cart Integration SDK version to 2.9.70

## 2.9.51
### Changed
- migrated Shopgate integration for OpenCart to GitHub

### Fixed
- improve shipping cost calculation for cart product quantity > 1

## 2.9.50
- Corrected issue that caused tier prices of child products with additional price to be understated

## 2.9.49
- removed wrong tier prices on child products
- add image export for product properties

## 2.9.48
- Rewritten check_stock, so the stocks are calculated correctly for all types of products
- Fixed check_cart stock calculation to work correctly for all types of products
- Changed stock calculation in product export to work the same way as in check_cart and check_stock
- adjusted tax totals in orders with coupons

## 2.9.47
- fixed 'orders_date_finished' value in order import
- now supporting the reuse of email addresses for guest orders in Gambio 3.4
- the shipping time for imported order products is now respecting product property shipping times if used

## 2.9.46
- mail text content is now created using the correct translation strings on order import
- fixed bug exporting the EAN of product property combinations

## 2.9.45
- fixed product numbers in cart validation response

## 2.9.44
- fixed product sort order in item export
- fixed filter properties export

## 2.9.43
- fixed issue with missing products and options in item export
- fixed a bug in customer's order history that would cause unavailable product images in the apps
- fixed bug with property quantity deductions

## 2.9.42
- fixed issue with missing products in item export

## 2.9.41
- fixed issue with calculating customer group discounts

## 2.9.40
- uses Shopgate Library 2.9.58
- fixed shipping taxes in order import

## 2.9.39
- now only exporting active categories

## 2.9.38
- adapted changes of the new GambioGX path structure into the plugin
- fixed prices in export of products with attributes and tier prices

## 2.9.37
- fixed issue with product item number for order items in order import

## 2.9.36
- added support for the "display additional address field" (since Gambio GX3 3.1.1.0)
- added support for the "seperate street and house number" (since Gambio GX3 3.1.1.0)
- fixed option value sort order to be considered correctly
- fixed export of tax classes that contain special characters
- uses Shopgate Library 2.9.50
- added support to a category inheriting sub-category products
- fixed synchronisation of cancelled orders to shopgate

## 2.9.35
- fixed a bug in base price export
- fixed a bug in plugin configuration for versions 3.1.0.0 and higher

## 2.9.34
- now setting product shipping time in order import

## 2.9.33
- improved validation of special prices
- uses Shopgate Library 2.9.44

## 2.9.32
- fixed a bug when exporting products with multiple GambioGX "properties" that have the same name
- fixed bug for including Debugger
- fixed some minor issues

## 2.9.31
- prepaid orders are now imported with payment method 'moneyorder' if it's installed and 'eustandardtransfer' is not installed
- added images to child (attribute) product export
- fixed bug with jsHeader printed when shop is not active (e.g. shop_is_active = 0)
- uses Shopgate Library 2.9.42

## 2.9.30
- improved compatibility with GambioGX 2.0.10
- order confirmation mail can now be sent through the shop system
- fixed a bug in exporting option data in the external order info

## 2.9.29
- added fallback name for tax export: rate is used as name if tax description is empty
- fixed issue in add_order: article number is now the SKU of the child instead of the parent product

## 2.9.28
- improved weight and base price calculation by honoring attributes
- fixed a PHP warning issued when exporting products that have no child products

## 2.9.27
- implemented new feature: validation of coupon codes
- fixed missing children, based on properties, in xml export
- fixed issue on order detail page of shopgate orders

## 2.9.26
- fixed missing SKUs for child products in the product xml export
- now exporting not available prices as zero

## 2.9.25
- improved detection and skipping of invalid information in the item export
- fixed encoding issue in export of tax settings

## 2.9.24
- fixed rounding issue in product export
- fixed a bug in exporting products with less than 50 variations

## 2.9.23
- fixed a bug in exporting SKUs for child products in the export of products
- fixed a bug that occured with huge numbers of variations for one product in the export of products

## 2.9.22
- bug fixed in exporting the category ids for products
- price on request is supported now
- bug fixed in exporting gx customizer input fields
- bug fixed in tax export

## 2.9.21
- fix check_cart shipping methods and oder import
- fixed bug in instantiation GSMESEOBoost
- column check functionality implemented
- now export product sort order based on the category settings
- fixed bug in category export
- fixed bug in csv product export
- added configuration to define display names for payment methods on order import

## 2.9.20
- implemented tier price support
- fixed wrong mapping in tax export
- fixed a bug with attribute stock levels
- bug in generating the product's deep link fixed
- bug in reading zone/country data to shop fixed

## 2.9.19
- implemented XML export for products
- mobile guest accounts are from now on imported as guest accounts in Gambio
- uses Shopgate Library 2.9.32

## 2.9.18
- added redirect of desktop search urls to mobile shop
- uses Shopgate Library 2.9.28
- fixed issue in ShopgateModelLoader

## 2.9.17
- set_settings: added possibility to set disabled category IDs for redirecting
- implemented the XML export of categories and reviews
- custom fields are fully supported now
- added fetching order history for a customer

## 2.9.16
- add_order: added custom fields for order and addresses

## 2.9.15
- add_order: article number was not always inserted correctly
- the shipping method is now displayed in the order detail view

## 2.9.14
- fixed a bug in the reduction of stock quantities on incoming Shopgate orders
- fixed a bug which caused that the Shopgate plugin menu wasn't visible in shop backend

## 2.9.13
- fixed a bug in exporting product filters
- added support for the "gambioultra" shipping method

## 2.9.12
- product filters can now be exported as properties

## 2.9.11
- established compatibility with GambioGX 2.3

## 2.9.10
- Fixed check_cart (stock quantity) and notice for non-existing variants
- CheckCart - optimize item validation
- fixed issue in customer specific special price calculation
- fixed issue with mobile redirect for categories
- fixed issue in response of the shipping methods
- A new registered customer will now have the correct registration date
- uses Shopgate Library 2.9.12
- fixed issue with paypal orders
- fixed encoding issue on creating new user accounts
- fixed issue in stock check function for shopping cart

## 2.9.9
- Bug in constant name for the Shopgate order database table fixed

## 2.9.8
- Bug in installation fixed

## 2.9.7
- products with a stock of zero will be exported too

## 2.9.6
- options can be exported as text field
- Shopgate Connect uses the customers birth date now
- removed compatibility issues in the redirect of product detail pages for Gambio 2.1.x
- bug in option price calculation fixed
- uses Shopgate Library 2.9.7

## 2.9.5
- it is not possible do set the default redirect in the Shopgate plugin settings anymore
- compatible with Gambio Version 2.1.x

## 2.9.4
- reworked the Shopgate plugin menu
- if the stock of a product falls below the predefined level, the merchant is now notified via email.
- bug in matching guest user accounts to orders fixed
- bug in export GX-Customizer fixed

## 2.9.3
- Bug in free shipping calculation fixed

## 2.9.2
- Wrong license header changed

## 2.9.1
- some database fields selected by shopsystem version

## 2.9.0
- uses Shopgate Library 2.9.3

## 2.8.9
- the use of an function removed which is only in new php versions available

## 2.8.8
- bug in live stock check fixed

## 2.8.7
- including language files bug fixed

## 2.8.6
- loading of the Shopgate models more efficient designed
- bug in requesting the shipping methods fixed

## 2.8.5
- Gambio language Manager removed

## 2.8.4
- check if gambio core files exist before using them

## 2.8.3
- bug in database check fixed
- bug creating a guest user fixed

## 2.8.2
- requested settings will be encoded in the right way
- predefined table constants will be checked now

## 2.8.1
- Shopgate models implemented
- product availability can be requested
- shoppingcart products can be verified
- valid shippingmethods were returned in the right way
- valid shipping countries can be requested
- valid user registration countries can be requested
- input fields will be considered while order import
- gambio language manager removed
- bux in creating guest user fixed
- uses Shopgate Library 2.8.9

## 2.8.0
- tax rules can be requested now
- uses Shopgate Library 2.8.3
- Bug in Shopgate Connect fixed

## 2.6.7
- bug fixed which accured when gzip compression is enabled

## 2.6.6
- shopgate config set variable

## 2.6.5
- wrong named variable changed

## 2.6.4
- bug in shipping price calculation fixed

## 2.6.3
- shipping price will now be exported as net

## 2.6.2
- Shipping methods can be selected

## 2.6.1
- plugin configuration request extended

## 2.6.0
- uses Shopgate Library 2.6.6

## 2.5.5
- implemented version check for Gambio shops

## 2.5.4
- plugin ping function extended
- uses Shopgate Library 2.5.6
- request Shopgate plugin propertiess

## 2.5.3
- bug in redirect header fixed

## 2.5.2
- bug in plugin installation fixed

## 2.5.1
- bug solved with missing functions

## 2.5.0
- uses Shopgate Library 2.5.3

## 2.4.9
- plugin installation optimized

## 2.4.8
- fixed problem with user ids

## 2.4.7
- uses Shopgate Library 2.4.12

## 2.4.6
- the tax order import process has been improved by enhancing the calculation precision

## 2.4.5
- bug in shopgate.xml fixed

## 2.4.4
- added head comment (license) into plugin files

## 2.4.3
- Bug in register_customer fixed

## 2.4.2
- implemented register_customer
- Uses Shopgate Library Version 2.4.6
- Problem solved which accured when the shopsystem has gZip compression activated

## 2.4.1
- fixed problem with user ids
- fixed MySQL memory issue with export of categories

## 2.4.0
- uses Shopgate Library 2.4.0

## 2.3.7
- Output buffer will be deleted to prevent error which caused through linebreaks/spaces

## 2.3.6
- Iframes in product descriptions wount be removed by now.

## 2.3.5
- Its now possible to export any amount of Productattributes as Shopgate-option.

## 2.3.4
- fixed special prices deactiviation issue on order import with special prices

## 2.3.3
- fixed issue with "Direct Access is not allowed" on API calls

## 2.3.2
- on exporting products, the entries of the content manager are included to the products description. This feature can be activated in the Shopgate-Settings.

## 2.3.1
- the formula for calculating the products properties has been updated, to match the prices on newer gambio versions as well

## 2.3.0
- fixed issue in the import of orders with customer group discount

## 2.1.44
- Only home page, product detail pages and category pages are always redirected to the mobile web site from now on. There's a new setting for specifying whether or not other pages should also be redirected.
- fixed issue with item csv export. Items with more than 10 options are ignored, since they would break the export file
- additional article shipping costs are exported
- uses Shopgate Library 2.3.1

## 2.1.43
- the "price on request" products are now exported correctly on attributes with price prefix set
- fixed issue in install script

## 2.1.42
- it's now possible to reduce the category depth to a maximum value upon request, where products deeper will be assigned to the deepest exported parent
- a problem has been solved, that happened to cause warnings while csv exports and order imports on servers that has set its error level to strict
- uses Shopgate Library 2.1.29

## 2.1.41
- the country and state-zone is now set correctly for guest customers while importing an order without Shopgate-Connect

## 2.1.40
- the products price status is now considered while exporting products

## 2.1.39
- an issue has been fixed that appeared to prevent the reduction of the stock levels for products

## 2.1.38
- it is now possible to choose a combination of the products description and the short description on the Shopgate settings page

## 2.1.37
- the stock of the products attributes is now exported correctly

## 2.1.36
- the Gambio feature "GX-Customizer" is now considered when exporting products to be able to include the inserted input-fields on the mobile website
- Shopgate orders are no more marked as bold permanently after the orders import

## 2.1.35
- fixed a problem, that occured while changing the orders status and the backend laugage at the same time.

## 2.1.34
- fixed an issue with DreamRobot and shipping costs
- moved functionality for handling of global and language dependend configurations to Shopgate Library
- fixed a bug in saving of log files
- uses Shopgate Library 2.1.26

## 2.1.33
- on executing the Shopgate Plugin, the database table structure is checked for the compatibility with the actual plugin, so it can be updated if neccessary
- an issue in coherence with the mobile redirect has been solved

## 2.1.32
- a method that has been mistakenly defined twice has been reduced to one definition

## 2.1.31
- the orders status "Shipping blocked (Shopgate)" can now be recognized while installing the Shopgate payment module even if its name has been changed, as long as it is marked with the keyword "Shopgate" (without quoutes)
- uses Shopgate Library 2.1.25

## 2.1.30
- it is now possible to export products using the product attributes as well as products using the products properties as variation type as long as every product uses only one variation type at the same time
- the tax rates are now displayed correctly when importing orders where non integral taxe rates are used
- the "customer status check" setting now affects the export of items and categories to skip products and categories based on the price group defined by the shopgate configuration
- the Shopgate configuration file can now be saved without any problems, even if false formatted GET parameters are appended on navigation to the Shopgate configuration page
- on GambioGX 1 versions the Shopgate manu is now activated correctly after installing the Shopgate payment module

## 2.1.29
- the customers data while importing orders is now taken from the shop customer data, instead of the customer data, given by the addOrder request
- the missing optional additional address data is now added to the street if set, when importing orders and customers
- added support for the older products structure to avoid false order imports after updating to a newer shopgate plugin

## 2.1.28
- fixed an issue with older versions of gambio

## 2.1.27
- method updateOrder() doesn't throw an exception anymore if payment is done after shipping and shipping was not blocked by Shopgate.
- fixed issue in products export for GambioGX2 versions before 2.0.7

## 2.1.26
- SEO optimized links are now exported if set up for products and categories
- uses Shopgate Library 2.1.24

## 2.1.25
- the plugin now considers both backend settings for stock level checking
- revised configuration interface
- it's now possible to select multiple languages for the Mobile Redirect
- added global configuration settings
- fixed issue with stock options

## 2.1.24
- preorder products are now exported as such
- packing units are now calculated and exported correctly on attribute and property products
- uses Shopgate Library 2.1.23

## 2.1.23
- product properties are now exported correctly

## 2.1.22
- fixed issue with mobile redirect on not configured languages

## 2.1.21
- fixed issue with DreamRobot
- uses Shopgate Library 2.1.22

## 2.1.20
- it is now possible to use amount interval products by exporting a special multipack product containing the interval amount of the products
- fixed an issue with products discount allowed values where product prices was exportet wrong in some cases
- uses Shopgate Library 2.1.21

## 2.1.19
- uses Shopgate Library 2.1.20
- configuration fields "mobile Website" / "shop is active" removed
- js header output in <head> HTML tag
- <link rel="alternate" ...> HTML tag output in <head>
- fixed an issue that caused categories to be in the exactly reversed sort order
- a setting has been added for shops that use the category sort order in an inverted way to display the categories
- the sort order of products can now also be inverted by setting in the extended shopgate settings

## 2.1.18
- duplicate product option value names are now automatically renamed while giving an index for each duplicate name
- fixed an issue with false price calculation for product attributes while exporting a currency using an exchange rate
- product properties are now exported on gambio gx 2.0.7 and higher if the variation type on the extended shopgate settings page is set to product properties
- uses Shopgate Library 2.1.18

## 2.1.17
- fixed issue of the DreamRobot request

## 2.1.16
- if the modul DreamRobot exists, the shopgate orders will be send to it

## 2.1.15
- the default charset is no longer set while creating the orders_shopgate_order table, because there seems to be a problem with some providers, while using this functionality
- mysql selects using join are now called explicit for cases where the keyword "JOIN" can not be used alone
- discounts for customer groups are now exported in the way the shoppingsystem implements it. The discount limit is also included for the export
- the shipping method for the orders import can now be selected on the shopgate-confuration page. The tax rate set for the shipping methods tax class will be used for calculating the shipping cost taxes on the orders detail page
- payment costs aren't added as article anymore
- uses Shopgate Library 2.1.17
- changed sort order for attribute images

## 2.1.14
- fixed an issue with wrong imported attribute images
- function ping returns now the shop version

## 2.1.13
- fixed an incompatiblity issue with older MySQL versions

## 2.1.12
- fixed issue for payment method paypal in older versions

## 2.1.11
- Directory structure updated
- uses Shopgate Library 2.1.11

## 2.1.10
- additional debug logging added
- uses Shopgate Library 2.1.9

## 2.1.9
- for orders the sum of net is also given
- uses Shopgate Library 2.1.8

## 2.1.8
- fixed error in export of item numbers for variations

## 2.1.7
- the comments for orders via Shopgate have been revised due to misconceptions in the past
- orders that are not blocked for shipping by Shopgaste are ''not'' approved for shipping either. When an order is placed with a merchant's payment method the transaction must be reviewed before shipping.
- uses Shopgate Library 2.1.7
- Paypal orders are imported correctly now
- item number export for one dimension variants

## 2.1.6
- fixed issues in products export
- uses Shopgate Library 2.1.6

## 2.1.5
- fixed error in payment module installation
- improved error display in configuration interface
- uses Shopgate Library 2.1.5
- fixed issue at push to afterbuy

## 2.1.4
- fixed an error concerning Shopgate Library
- uses Shopgate Library 2.1.3

## 2.1.3
- uses Shopgate Library 2.1.2
- fixed issue with attribute products. The original images of attribute products will be exported additionally.

## 2.1.2
- fixed issue with mobile redirect and activated Gambio SEO Boost

## 2.1.1
- fixed (multibyte) charset issues when converting html entities in category names
- uses Shopgate Library 2.1.1

## 2.1.0
- the installation routine of the payment module now adds a new send status called "Shipping blocked (Shopgate)"
- common bugfixes
- uses Shopgate Library 2.1.0

## 2.0.14
- Manual update of database tables is not neccessary anymore. This is now done automatically during installation of the Shopgate payment module.

## 2.0.13
- uses Shopgate Library 2.0.34
- fixed export of personal offer prices
- use short description for products if description is empty
- fixed PHP warning on reinstallation of the Shopgate payment module
- removed unused configuration settings from Shopgate payment module

## 2.0.12
- fixed (multibyte) charset issues when converting html entities in attributes
- uses Shopgate Library 2.0.31
- changed export of product variations
- added changelog.txt

## 2.0.11
- added changelog.txt
- uses Shopgate Library 2.0.26
- supports the "Redirect to tablets (yes/no)" setting
- supports remote cron jobs via Shopgate Plugin API
- remote cron job for synchronization of order status at Shopgate

[Unreleased]: https://github.com/shopgate/cart-integration-gambiogx/compare/2.9.54...HEAD
[2.9.54]: https://github.com/shopgate/cart-integration-gambiogx/compare/2.9.53...2.9.54
[2.9.53]: https://github.com/shopgate/cart-integration-gambiogx/compare/2.9.52...2.9.53
[2.9.52]: https://github.com/shopgate/cart-integration-gambiogx/compare/2.9.51...2.9.52
