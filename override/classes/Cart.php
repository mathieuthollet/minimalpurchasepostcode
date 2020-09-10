<?php
/**
 * 2007-2020 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

class Cart extends CartCore
{
    /**
     * Filtre les transporteurs selon les réglages de "minimal purchase postcode"
     * @param Country|null $default_country
     * @param bool $flush
     */
    public function getDeliveryOptionList(Country $default_country = null, $flush = false)
    {
        $list = parent::getDeliveryOptionList($default_country, $flush);
        if ($this->id_address_delivery != 0) {
            $rules = json_decode(Configuration::get('MINIMALPURCHASEPOSTCODE_RULES'));
            $skipForFreeShipping = (bool)Configuration::get('MINIMALPURCHASEPOSTCODE_SKIPFORFREESHIPPING');
            $withTaxes = (bool)Configuration::get('MINIMALPURCHASEPOSTCODE_WITHTAXES');
            $address = new Address($this->id_address_delivery);
            $minimalPurchase = 0;
            foreach ($rules as $rule) {
                if (fnmatch($rule->postcode, $address->postcode)) {
                    $currency = Currency::getCurrency((int)$this->id_currency);
                    $minimalPurchase = Tools::convertPrice((float)$rule->minimalPurchase, $currency);
                }
            }
            $cartTotal = $this->getOrderTotal($withTaxes, Cart::ONLY_PRODUCTS);
            foreach ($list as &$adresse) {
                foreach ($adresse as $id_option => &$option) {
                    $carrier = reset($option['carrier_list'])['instance'];
                    if (!($carrier->is_free && $option['is_free']) || !$skipForFreeShipping) {
                        if ($cartTotal < $minimalPurchase) {
                            unset($adresse[$id_option]);
                        }
                    }
                }
            }
        }
        return $list;
    }
}
