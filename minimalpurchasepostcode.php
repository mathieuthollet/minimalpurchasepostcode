<?php
/**
 * 2007-2019 PrestaShop
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
 * @copyright 2007-2019 PrestaShop SA
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
    protected $support_url = 'https://addons.prestashop.com/contact-form.php?id_product=30813';

    public function __construct()
    {
        $this->name = 'minimalpurchasepostcode';
        $this->tab = 'checkout';
        $this->version = '1.0.0';
        $this->author = 'Mathieu Thollet';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->module_key = '';

        parent::__construct();

        $this->displayName = $this->l('Minimal purchase price by postal code');
        $this->description = $this->l('Set a minimal purchase price amount deppending on the customer postcode.');
    }

    public function install()
    {
        return parent::install() && $this->registerHook('overrideMinimalPurchasePrice');
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
        $this->context->smarty->assign('cron_url', Tools::getProtocol(Tools::usingSecureMode()) . $_SERVER['HTTP_HOST'] . $this->getPathUri() . 'cron.php');
        $output = $this->message .
            $this->renderConfigForm() /*.
            $this->context->smarty->fetch($this->local_path . 'views/templates/admin/support.tpl')*/
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
            $message .= $this->l('The minimal purchase price rules by postcode have been saved.');
            $this->setSuccessMessage($message);
            Configuration::updateValue('MINIMALPURCHASEPOSTCODE_RULES', $this->convertRulesFromTextToJson($rulesText));
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
        foreach ($rulesArray as $rule) {
            if ($rulesText != '') {
                $rulesText .= "\n";
            }
            $rulesText .= $rule->postcode . ' ' . $rule->minimalPurchase;
        }
        return $rulesText;
    }

    /**
     * Hook to override the minimal purchase price
     * @param $args
     */
    public function hookOverrideMinimalPurchasePrice(&$args)
    {
        if (isset($args['cart']) && $args['cart']->id_address_delivery != 0) {
            $rules = json_decode(Configuration::get('MINIMALPURCHASEPOSTCODE_RULES'));
            $address = new Address($args['cart']->id_address_delivery);
            foreach ($rules as $rule) {
                if (fnmatch($rule->postcode, $address->postcode)) {
                    $currency = Currency::getCurrency((int)$args['cart']->id_currency);
                    $args['minimalPurchase'] = Tools::convertPrice((float)$rule->minimalPurchase, $currency);
                }
            }
        }
    }
}
