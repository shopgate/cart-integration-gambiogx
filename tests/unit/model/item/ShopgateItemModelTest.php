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
class ShopgateItemModelTest extends \PHPUnit\Framework\TestCase
{
    /** @var ShopgateItemModel */
    protected $subjectUnderTest;

    /**
     * Initializing main test class including mocked dependencies
     */
    public function setUp(): void
    {
        $dbHelperMock = $this->getMockBuilder('ShopgateDatabaseHelper')
            ->disableOriginalConstructor()
            ->getMock();

        $itemPropertyHelperMock = $this->getMockBuilder('ShopgateItemPropertyHelper')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $itemCustomerSetHelperMock = $this->getMockBuilder('ShopgateCustomizerSetHelper')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $shopgateConfigMock = $this->getMockBuilder('ShopgateConfigGambioGx')
            ->disableOriginalConstructor()
            ->getMock();

        $currency = array(
            'decimal_places'  => '',
            'decimal_point'   => '',
            'thousands_point' => '',
            'symbol_left'     => '',
            'symbol_right'    => '',
        );

        $this->subjectUnderTest = new ShopgateItemModel(
            $dbHelperMock,
            $itemPropertyHelperMock,
            $itemCustomerSetHelperMock,
            $shopgateConfigMock,
            1,
            $currency,
            1,
            1,
            1
        );
    }

    /**
     * @param array $expectedNames
     * @param array $dataSet
     *
     * @covers       ShopgateItemModel::getInputFieldsFromGxCustomizerSets
     * @dataProvider customizerSetProvider
     */
    public function testGxCustomizerSetInputNames($expectedNames, $dataSet)
    {
        $result = $this->subjectUnderTest->getInputFieldsFromGxCustomizerSets($dataSet);
        $result = array_pop($result);

        foreach ($expectedNames as $index => $name) {
            $this->assertEquals($name, $result["input_field_" . ($index + 1) . "_label"]);
        }
    }

    /**
     * Set up fake customer set(s)
     *
     * @return array
     */
    public function customizerSetProvider()
    {
        $multipleCustomizerSet = array(
            '5' => array(
                '5' => array(
                    array(
                        'surfaces_groups_name'      => 'Name & Nummer',
                        'surfaces_groups_id'        => 5,
                        'surfaces_id'               => 5,
                        'surfaces_width'            => 350,
                        'surfaces_height'           => 110,
                        'surfaces_name'             => 'Falls mit Druck: Bitte Name und Nummer eingeben',
                        'elements_id'               => 3,
                        'elements_groups_id'        => 3,
                        'elements_position_x'       => 10,
                        'elements_position_y'       => 75,
                        'elements_position_height'  => 22,
                        'elements_height'           => 22,
                        'elements_position_width'   => 200,
                        'elements_width'            => 200,
                        'elements_position_z_index' => 0,
                        'elements_show_name'        => 1,
                        'elements_group_type'       => 'text_input',
                        'elements_group_name'       => '',
                        'elements_values_id'        => 60,
                        'elements_values_name'      => 'Nummer (max. 2 Ziffern)',
                        'elements_values_value'     => '',
                    ),
                    array(
                        'surfaces_groups_name'      => 'Name & Nummer',
                        'surfaces_groups_id'        => 5,
                        'surfaces_id'               => 5,
                        'surfaces_width'            => 350,
                        'surfaces_height'           => 110,
                        'surfaces_name'             => 'Falls mit Druck: Bitte Name und Nummer eingeben',
                        'elements_id'               => 2,
                        'elements_groups_id'        => 2,
                        'elements_position_x'       => 10,
                        'elements_position_y'       => 25,
                        'elements_position_height'  => 22,
                        'elements_height'           => 22,
                        'elements_position_width'   => 200,
                        'elements_width'            => 200,
                        'elements_position_z_index' => 0,
                        'elements_show_name'        => 1,
                        'elements_group_type'       => 'text_input',
                        'elements_group_name'       => '',
                        'elements_values_id'        => 64,
                        'elements_values_name'      => 'Name (max. 10 Stellen)',
                        'elements_values_value'     => '',
                    ),
                ),
            ),
        );

        $singleCustomerSet = array('10' => array('11' => array($multipleCustomizerSet['5']['5'][0])));

        return array(
            'Multiple Inputs' => array(
                array(' NUMMER (MAX. 2 ZIFFERN)', ' NAME (MAX. 10 STELLEN)'),
                $multipleCustomizerSet,
            ),
            'Single Input'    => array(
                array(' FALLS MIT DRUCK: BITTE NAME UND NUMMER EINGEBEN'),
                $singleCustomerSet,
            ),
        );
    }
}
