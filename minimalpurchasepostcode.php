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

if (!defined('_PS_VERSION_')) {
    exit;
}

class MinimalPurchasePostCode extends Module
{
    private $message = '';

    protected $config_form = false;
    protected $support_url = 'https://addons.prestashop.com/contact-form.php?id_product=47869';

    public function __construct()
    {
        $this->name = 'minimalpurchasepostcode';
        $this->tab = 'checkout';
        $this->version = '1.0.5';
        $this->author = 'Mathieu Thollet';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->module_key = '761116d5a9a1e3f97cba1ce82d8ba11c';

        parent::__construct();

        $this->displayName = $this->l('Minimal purchase price by postal code');
        $this->description = $this->l('Set a minimal purchase price amount deppending on the customer postcode.');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('header')
            && $this->registerHook('displayAfterCarrier');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        if ((bool)Tools::isSubmit('submitPurchase_minimum_postal_code')) {
            $this->postProcess();
        }
        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->smarty->assign('support_url', $this->support_url);
        $output = $this->message .
            $this->renderConfigForm() .
            $this->context->smarty->fetch($this->local_path . 'views/templates/admin/support.tpl')
        ;
        return $output;
    }

    /**
     * Rendering of configuration form
     * @return mixed
     */
    protected function renderConfigForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPurchase_minimum_postal_code';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        // Form values
        $helper->fields_value = array(
            'minimalpurchasepostcode_rules' => $this->convertRulesFromJsonToText(Configuration::get('MINIMALPURCHASEPOSTCODE_RULES')),
            'minimalpurchasepostcode_withtaxes' => Configuration::get('MINIMALPURCHASEPOSTCODE_WITHTAXES'),
            'minimalpurchasepostcode_skipforfreeshipping' => Configuration::get('MINIMALPURCHASEPOSTCODE_SKIPFORFREESHIPPING'),
        );
        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Structure of the configuration form
     * @return array
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Minimal purchase price by postal code'),
                    'icon' => 'icon-truck',
                ),
                'input' => array(
                    array(
                        'name' => 'minimalpurchasepostcode_rules',
                        'type' => 'textarea',
                        'label' => $this->l('Minimal purchase price rules by postcode'),
                        'desc' => $this->l('One rule per line, syntax : "PostalCode Price"') . '<br/>' .
                            $this->l('Wildcard character : "*"') . '<br/>' .
                            $this->l('Example :') . '<br/>' .
                            $this->l('69100 50') . '<br/>' .
                            $this->l('42* 70') . '<br/>' .
                            $this->l('...') . '<br/>' .
                            $this->l('If the customer postal code matches many rules, the last rule will be applied') . '<br/>',
                    ),
                    array(
                        'name' => 'minimalpurchasepostcode_withtaxes',
                        'type' => 'switch',
                        'label' => $this->l('With taxes'),
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'withtaxes_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'withtaxes_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    array(
                        'name' => 'minimalpurchasepostcode_skipforfreeshipping',
                        'type' => 'switch',
                        'label' => $this->l('Skip minimal purchase price for free shipping carriers'),
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'skipforfreeshipping_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'skipforfreeshipping_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'id' => 'submitSave',
                    'icon' => 'process-icon-save'
                ),
            ),
        );
    }

    /**
     * PostProcess
     */
    protected function postProcess()
    {
        if (Tools::isSubmit('submitPurchase_minimum_postal_code')) {
            $this->processSaveConfig();
        }
    }

    /**
     * Save settings
     */
    protected function processSaveConfig()
    {
        $error = false;
        $rulesText = Tools::getValue('minimalpurchasepostcode_rules');
        foreach (explode("\n", $rulesText) as $iRow => $row) {
            $row = trim($row);
            if ($row != '') {
                $rowArray = explode(' ', $row);
                if (count($rowArray) != 2) {
                    $message = $this->l('Incorrect format at row') . ' ' . $iRow . ' : ';
                    $message .= $this->l('2 values needed (postal code and price)') . ', ' . count($rowArray) . ' ' . $this->l('given.');
                    $this->setErrorMessage($message);
                    $error = true;
                } else if (!is_numeric($rowArray[1])) {
                    $message = $this->l('Incorrect format at row') . ' ' . $iRow . ' : ';
                    $message .= $this->l('Price should be numeric');
                    $this->setErrorMessage($message);
                    $error = true;
                }
            }
        }
        if (!$error) {
            $message = $this->l('The minimal purchase price rules by postcode have been saved.');
            $this->setSuccessMessage($message);
            Configuration::updateValue('MINIMALPURCHASEPOSTCODE_RULES', $this->convertRulesFromTextToJson($rulesText));
            Configuration::updateValue('MINIMALPURCHASEPOSTCODE_WITHTAXES', Tools::getValue('minimalpurchasepostcode_withtaxes'));
            Configuration::updateValue('MINIMALPURCHASEPOSTCODE_SKIPFORFREESHIPPING', Tools::getValue('minimalpurchasepostcode_skipforfreeshipping'));
        }
    }

    /**
     * Sets error message
     * @param $message
     */
    protected function setErrorMessage($message)
    {
        $this->context->smarty->assign('message', $message);
        $this->message .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/alert-danger.tpl');
    }

    /**
     * Sets success message
     * @param $message
     */
    protected function setSuccessMessage($message)
    {
        $this->context->smarty->assign('message', $message);
        $this->message .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/alert-success.tpl');
    }

    /**
     * Convert rules from text (admin config form) to json (database)
     * @param $rules
     * @return string
     */
    protected function convertRulesFromTextToJson($rulesText)
    {
        $rulesArray = array();
        foreach (explode("\n", $rulesText) as $row) {
            $row = trim($row);
            if ($row != '') {
                $rowArray = explode(' ', $row);
                $rulesArray[] = array(
                    'postcode' => $rowArray[0],
                    'minimalPurchase' => $rowArray[1],
                );
            }
        }
        return json_encode($rulesArray);
    }

    /**
     * Convert rules from json (database) to text (admin config form)
     * @param $rules
     * @return string
     */
    protected function convertRulesFromJsonToText($rulesJson)
    {
        $rulesArray = json_decode($rulesJson);
        $rulesText = '';
        if (is_array($rulesArray)) {
            foreach ($rulesArray as $rule) {
                if ($rulesText != '') {
                    $rulesText .= "\n";
                }
                $rulesText .= $rule->postcode . ' ' . $rule->minimalPurchase;
            }
        }
        return $rulesText;
    }

    /**
     * Hook to add front CSS
     * @param $params
     */
    public function hookHeader($params)
    {
        $this->context->controller->addCSS($this->local_path . 'views/css/front.css', 'all');
    }

    /**
     * Hook to display message after carriers list
     * @param $args
     */
    public function hookDisplayAfterCarrier(&$args)
    {
        if (isset($args['cart']) && $args['cart']->id_address_delivery != 0) {
            $cart = $args['cart'];
            $rules = json_decode(Configuration::get('MINIMALPURCHASEPOSTCODE_RULES'));
            $withTaxes = (bool)Configuration::get('MINIMALPURCHASEPOSTCODE_WITHTAXES');
            $address = new Address($cart->id_address_delivery);
            $minimalPurchase = 0;
            foreach ($rules as $rule) {
                if (fnmatch($rule->postcode, $address->postcode)) {
                    $currency = Currency::getCurrency((int)$cart->id_currency);
                    $minimalPurchase = Tools::convertPrice((float)$rule->minimalPurchase, $currency);
                }
            }
            $cartTotal = $cart->getOrderTotal($withTaxes, Cart::ONLY_PRODUCTS);
            if ($cartTotal < $minimalPurchase) {
                $message = $this->l('A minimum shopping cart total of %s is required to have other carriers available. Current cart total is %s.');
                $this->context->smarty->assign('message', sprintf($message, Tools::displayPrice($minimalPurchase, $currency), Tools::displayPrice($cartTotal, $currency)));
                return $this->context->smarty->fetch($this->local_path . 'views/templates/hook/display-after-carrier.tpl');
            }
        }
    }
}
