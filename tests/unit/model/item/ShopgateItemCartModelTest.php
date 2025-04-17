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
class ShopgateItemCartModelTest extends \PHPUnit\Framework\TestCase
{
    /** @var ShopgateItemCartModel */
    protected $subjectUnderTest;

    /**
     * Initializing main test class
     */
    public function setUp(): void
    {
        $this->subjectUnderTest = new ShopgateItemCartModel(1);
    }

    /**
     * @param array  $expectedAttributeSelections
     * @param int    $expectedSelectionCount
     * @param array  $orderInfo
     * @param string $delimiter
     *
     * @covers       ShopgateItemCartModel::getCartItemAttributeSelection
     * @dataProvider attributeSelectionProvider
     */
    public function testGetCartItemAttributeSelection(
        $expectedAttributeSelections,
        $expectedSelectionCount,
        $orderInfo,
        $delimiter
    ) {
        $product = new ShopgateOrderItem();
        $product->setInternalOrderInfo(json_encode($orderInfo));

        $attributeSelections = $this->subjectUnderTest->getCartItemAttributeSelection($product, $delimiter);

        $this->assertCount($expectedSelectionCount, $attributeSelections);
        $this->assertEquals($expectedAttributeSelections, $attributeSelections);
    }

    /**
     * Set up fake attribute selections
     *
     * @return array
     */
    public function attributeSelectionProvider()
    {
        $delimiter = '-';

        $sampleSelection = array(
            'attribute_1'      => array('59682' => array('options_id' => 1, 'options_values_id' => 1)),
            'attribute_2'      => array('59684' => array('options_id' => 6, 'options_values_id' => 55)),
            'base_item_number' => 1466,
        );

        return array(
            'Empty OrderInfo' => array(array(), 0, array(), $delimiter),
            '2 attributes'    => array(
                array('1' . $delimiter . '1', '6' . $delimiter . '55'),
                2,
                $sampleSelection,
                $delimiter,
            ),
        );
    }
}
