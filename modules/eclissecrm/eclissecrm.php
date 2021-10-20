<?php
/**
* 2007-2021 PrestaShop
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Eclissecrm extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'eclissecrm';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'thuydtshop@gmail.com';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Eclisse CRM');
        $this->description = $this->l('The module is used to connect to a CRM system that manages quotations from prestashop cart');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        // for enable module or not
        Configuration::updateValue('ECLISSECRM_LIVE_MODE', false);
        Configuration::updateValue('ECLISSECRM_API_ENDPOINT', '');
        Configuration::updateValue('ECLISSECRM_API_KEY', '');
        Configuration::deleteByName('ECLISSECRM_LOGIN_REQUIRED', true);
        Configuration::deleteByName('ECLISSECRM_GROUP_REQUIRED', false);

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('displayShoppingCartFooter');
    }

    public function uninstall()
    {
        Configuration::deleteByName('ECLISSECRM_LIVE_MODE');
        Configuration::deleteByName('ECLISSECRM_API_ENDPOINT');
        Configuration::deleteByName('ECLISSECRM_API_KEY');
        Configuration::deleteByName('ECLISSECRM_LOGIN_REQUIRED', true);
        Configuration::deleteByName('ECLISSECRM_GROUP_REQUIRED', false);

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitEclissecrmModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitEclissecrmModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enabled'),
                        'name' => 'ECLISSECRM_LIVE_MODE',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                        'hint' => $this->l('Enable or disable module')
                    ),
                    array(
                       'type' => 'text', 
                       'label' => $this->l('API Endpoint'),
                       'name' => 'ECLISSECRM_API_ENDPOINT',
                       'desc' => $this->l('Please enter api enpoint for post data')
                    ),
                    array(
                       'type' => 'text', 
                       'label' => $this->l('API Key'),
                       'name' => 'ECLISSECRM_API_KEY',
                       'hint' => $this->l('You should use secrect key for endpoint api.')
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Logged require'),
                        'name' => 'ECLISSECRM_LOGIN_REQUIRED',
                        'is_bool' => true,
                        'desc' => $this->l('Enable for require customer login. If you disable, the module will allow for both of logged user and guess'),
                        'hint' => $this->l('Require a user to be logged'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        )
                    ),
                    array(
                        'type' => 'group',
                        'label' => $this->l('Customer group access'),
                        'name' => 'ECLISSECRM_GROUP_REQUIRED',
                        'values' => Group::getGroups($this->context->language->id),
                        'hint' => $this->l('Mark the groups that are allowed access to Send CRM feature.')
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save')
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues($is_show = true)
    {
        $return = array(
            'ECLISSECRM_LIVE_MODE' => Configuration::get('ECLISSECRM_LIVE_MODE', true),
            'ECLISSECRM_API_ENDPOINT' => Configuration::get('ECLISSECRM_API_ENDPOINT', ''),
            'ECLISSECRM_API_KEY' => Configuration::get('ECLISSECRM_API_KEY', ''),
            'ECLISSECRM_LOGIN_REQUIRED' => Configuration::get('ECLISSECRM_LOGIN_REQUIRED', true),
            //'ECLISSECRM_GROUP_REQUIRED' => $ECLISSECRM_GROUP_REQUIRED,
        );

        if ($is_show) {
            $groups = Configuration::get('ECLISSECRM_GROUP_REQUIRED', false);
            if ($groups && !empty($groups)) {
                $groups = explode(',', $groups);
                if ($groups) {
                    foreach ($groups as $group) {
                        if (!isset($return[$group])) $return[ 'groupBox_'.$group ] = $group;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues(false);

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

        Configuration::updateValue( 'ECLISSECRM_GROUP_REQUIRED', implode(',', Tools::getValue('groupBox')) );
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $controller_name = Tools::getValue('controller');

        if ($controller_name == 'order') {
            $this->context->controller->addCSS($this->_path.'views/css/front.css');
            $this->context->controller->addJS($this->_path.'views/js/front.js');
        }
    }

    public function hookDisplayShoppingCartFooter($params)
    {
        $logged_required = Configuration::get('ECLISSECRM_LOGIN_REQUIRED', true);
        if ($logged_required && !$this->context->customer->isLogged(true)) {
            return '';
        }

        $groups_required_str = Configuration::get('ECLISSECRM_GROUP_REQUIRED', false);
        $groups_required = [];
        if ($groups_required_str && !empty($groups_required_str)) {
            $groups_required = explode(',', $groups_required_str);
        }
        if ($groups_required) {
            $customer_groups = Customer::getGroupsStatic($this->context->customer->id);
        }
        $test_group = array_intersect($groups_required, $customer_groups);
        if (count($test_group) < 1) {
            return '';
        }

        $enable_config = Configuration::get('ECLISSECRM_LIVE_MODE', false);

        $cart = $this->context->cart;
        if (!$enable_config || !isset($cart->id) || count($cart->getProducts()) < 1) return '';

        $ajax_url_send_to_crm = $this->context->link->getModuleLink('eclissecrm', 'SendCRM');
        $this->context->smarty->assign('ajax_url_send_to_crm', $ajax_url_send_to_crm);

        return $this->display(__FILE__, 'views/templates/hook/shoppingcart.tpl'); 
    }
}
