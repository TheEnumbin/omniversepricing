<?php
/**
 * 2007-2022 PrestaShop
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
 *  @copyright 2007-2022 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;

class Omniversepricing extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'omniversepricing';
        $this->version = '1.1.1';
        $this->tab = 'pricing_promotion';
        $this->author = 'TheEnumbin';
        $this->need_instance = 0;
        $this->module_key = '9b8f5f1cfb8a9b1479c52f965758b88f';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('OmniversePricing');
        $this->description = $this->l('This is the module you need to make your PrestaShop Pricing Compatible for EU Omnibus Directive');
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        $date = date('Y-m-d');
        Configuration::updateValue('OMNIVERSEPRICING_STABLE_VERSION', $this->version);
        Configuration::updateValue('OMNIVERSEPRICING_TEXT', 'Lowest price within 30 days before promotion {{omni_price}} ({{omni_percent}})');
        Configuration::updateValue('OMNIVERSEPRICING_SHOW_IF_CURRENT', true);
        Configuration::updateValue('OMNIVERSEPRICING_PRICE_WITH_TAX', false);
        Configuration::updateValue('OMNIVERSEPRICING_PRECENT_INDICATOR', true);
        Configuration::updateValue('OMNIVERSEPRICING_SYNC_START', 1);
        Configuration::updateValue('OMNIVERSEPRICING_SYNC_END', 20);
        Configuration::updateValue('OMNIVERSEPRICING_STOP_RECORD', false);
        Configuration::updateValue('OMNIVERSEPRICING_AUTO_DELETE_OLD', false);
        Configuration::updateValue('OMNIVERSEPRICING_NOTICE_STYLE', 'mixed');
        Configuration::updateValue('OMNIVERSEPRICING_HISTORY_FUNC', 'manual');
        Configuration::updateValue('OMNIVERSEPRICING_POSITION', 'after_price');
        Configuration::updateValue('OMNIVERSEPRICING_BACK_COLOR', '#b3a700');
        Configuration::updateValue('OMNIVERSEPRICING_FONT_COLOR', '#ffffff');
        Configuration::updateValue('OMNIVERSEPRICING_DELETE_DATE', $date);

        $languages = Language::getLanguages(false);

        foreach ($languages as $lang) {
            Configuration::updateValue('OMNIVERSEPRICING_TEXT_' . $lang['id_lang'], 'Lowest price within 30 days before promotion {{omni_price}} ({{omni_percent}})');
        }
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminAjaxOmniverse';
        $tab->name = [];

        foreach ($languages as $lang) {
            $tab->name[$lang['id_lang']] = 'Omniverse Ajax';
        }
        $tab->id_parent = -1;
        $tab->module = $this->name;
        $tab->add();
        include _PS_MODULE_DIR_ . $this->name . '/sql/install.php';

        return parent::install()
        && $this->registerHook('header')
        && $this->registerHook('actionProductUpdate')
        && $this->registerHook('actionObjectSpecificPriceAddAfter')
        && $this->registerHook('actionObjectSpecificPriceUpdateAfter')
        && $this->registerHook('displayBackOfficeHeader')
        && $this->registerHook('displayAdminProductsExtra')
        && $this->registerHook('displayOmniverseNotice')
        && $this->registerHook('displayProductPriceBlock');
    }

    /**
     * This methos is called when uninstalling the module.
     */
    public function uninstall()
    {
        include _PS_MODULE_DIR_ . $this->name . '/sql/uninstall.php';
        $languages = Language::getLanguages(false);

        foreach ($languages as $lang) {
            Configuration::deleteByName('OMNIVERSEPRICING_TEXT_' . $lang['id_lang']);
        }

        Configuration::deleteByName('OMNIVERSEPRICING_TEXT');
        Configuration::deleteByName('OMNIVERSEPRICING_SHOW_IF_CURRENT');
        Configuration::deleteByName('OMNIVERSEPRICING_PRICE_WITH_TAX');
        Configuration::deleteByName('OMNIVERSEPRICING_PRECENT_INDICATOR');
        Configuration::deleteByName('OMNIVERSEPRICING_SYNC_START');
        Configuration::deleteByName('OMNIVERSEPRICING_SYNC_END');
        Configuration::deleteByName('OMNIVERSEPRICING_STOP_RECORD');
        Configuration::deleteByName('OMNIVERSEPRICING_AUTO_DELETE_OLD');
        Configuration::deleteByName('OMNIVERSEPRICING_POSITION');
        Configuration::deleteByName('OMNIVERSEPRICING_BACK_COLOR');
        Configuration::deleteByName('OMNIVERSEPRICING_FONT_COLOR');
        Configuration::deleteByName('OMNIVERSEPRICING_HISTORY_FUNC');
        Configuration::deleteByName('OMNIVERSEPRICING_NOTICE_PAGE');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $html = '';
        if (((bool) Tools::isSubmit('submitOmniversepricingModule')) == true) {
            $html .= $this->postProcess();
        }

        $advertise = $this->advertise_template();

        return $html . $this->renderForm() . $advertise;
    }

    protected function advertise_template()
    {
        // Fetch and render the template file
        $this->context->smarty->assign('module_dir', $this->_path);
        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/advertise_template.tpl');
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
        $helper->submit_action = 'submitOmniversepricingModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        $cron_url = $this->context->link->getModuleLink('omniversepricing', 'sync');
        $tabs = [
            'general' => $this->l('General'),
            'content_tab' => $this->l('Content'),
            'design_tab' => $this->l('Design'),
            'action_tab' => $this->l('Action'),
        ];

        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('How to Keep Price History?'),
                        'name' => 'OMNIVERSEPRICING_HISTORY_FUNC',
                        'options' => [
                            'query' => [
                                [
                                    'id' => 'manual',
                                    'name' => $this->l('Manual Sync - One click syncing'),
                                ],
                                [
                                    'id' => 'w_change',
                                    'name' => $this->l('Automated when product price changes or discount added/updated'),
                                ],
                                [
                                    'id' => 'w_cron',
                                    'name' => $this->l('Automated with Cron'),
                                ],
                                [
                                    'id' => 'w_hook',
                                    'name' => $this->l('Automated with Hook'),
                                ],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'tab' => 'general',
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Show Notice On'),
                        'name' => 'OMNIVERSEPRICING_NOTICE_PAGE',
                        'options' => [
                            'query' => [
                                [
                                    'id' => 'all_pages',
                                    'name' => $this->l('All Pages'),
                                ],
                                [
                                    'id' => 'single',
                                    'name' => $this->l('Single Product Page'),
                                ],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'tab' => 'general',
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Show Notice For'),
                        'name' => 'OMNIVERSEPRICING_SHOW_ON',
                        'options' => [
                            'query' => [
                                [
                                    'id' => 'all_prds',
                                    'name' => $this->l('All Products'),
                                ],
                                [
                                    'id' => 'discounted',
                                    'name' => $this->l('Only Discounted Products'),
                                ],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'tab' => 'general',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Price With/Without Tax'),
                        'name' => 'OMNIVERSEPRICING_PRICE_WITH_TAX',
                        'values' => [
                            [
                                'id' => 'including',
                                'value' => true,
                                'label' => $this->l('With Tax'),
                            ],
                            [
                                'id' => 'excluding',
                                'value' => false,
                                'label' => $this->l('Without Tax'),
                            ],
                        ],
                        'tab' => 'general',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Show notice if current price is the lowest.'),
                        'name' => 'OMNIVERSEPRICING_SHOW_IF_CURRENT',
                        'values' => [
                            [
                                'id' => 'yes',
                                'value' => true,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'no',
                                'value' => false,
                                'label' => $this->l('No'),
                            ],
                        ],
                        'tab' => 'general',
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Select Notice Text Style'),
                        'name' => 'OMNIVERSEPRICING_NOTICE_STYLE',
                        'options' => [
                            'query' => [
                                [
                                    'id' => 'before_after',
                                    'name' => $this->l('Notice Text _ Price'),
                                ],
                                [
                                    'id' => 'after_before',
                                    'name' => $this->l('Price _ Notice Text'),
                                ],
                                [
                                    'id' => 'mixed',
                                    'name' => $this->l('Mixed'),
                                ],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'tab' => 'general',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Automatically delete 30 days older data'),
                        'name' => 'OMNIVERSEPRICING_AUTO_DELETE_OLD',
                        'values' => [
                            [
                                'id' => 'yes',
                                'value' => true,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'no',
                                'value' => false,
                                'label' => $this->l('No'),
                            ],
                        ],
                        'tab' => 'general',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Notice text. Use {{omni_price}} for price and {{omni_percent}} for percentage.
                         Example: Lowest price within 30 days before promotion {{omni_price}} ({{omni_percent}})'),
                        'name' => 'OMNIVERSEPRICING_TEXT',
                        'label' => $this->l('Omni Directive Text'),
                        'tab' => 'content_tab',
                        'lang' => true,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Use Increase/Decrease Indicator'),
                        'desc' => $this->l('Indicates how much increased or decreased from the previous price.'),
                        'name' => 'OMNIVERSEPRICING_PRECENT_INDICATOR',
                        'values' => [
                            [
                                'id' => 'including',
                                'value' => true,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'excluding',
                                'value' => false,
                                'label' => $this->l('No'),
                            ],
                        ],
                        'tab' => 'general',
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Select Notice Position'),
                        'name' => 'OMNIVERSEPRICING_POSITION',
                        'options' => [
                            'query' => [
                                [
                                    'id' => 'after_price',
                                    'name' => $this->l('After Price'),
                                ],
                                [
                                    'id' => 'old_price',
                                    'name' => $this->l('Before Old Price'),
                                ],
                                [
                                    'id' => 'footer_product',
                                    'name' => $this->l('Footer Product'),
                                ],
                                [
                                    'id' => 'product_bts',
                                    'name' => $this->l('After Product Buttons'),
                                ],
                                [
                                    'id' => 'with_custom_hook',
                                    'name' => $this->l('With Custom Hook'),
                                ],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'tab' => 'content_tab',
                    ],
                    [
                        'type' => 'html',
                        'label' => $this->l('Use This Hook to Show Notice'),
                        'name' => 'OMNIVERSEPRICING_CUSTOM_HOOK',
                        'html_content' => "<div class=\"omni-custom-hook\">{hook h='displayOmniverseNotice' product=\$product}</div>",
                        'tab' => 'content_tab',
                        'desc' => $this->l('This will work when you select the Notice Position to With Custom Hook'),
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Background Color'),
                        'name' => 'OMNIVERSEPRICING_BACK_COLOR',
                        'tab' => 'design_tab',
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Text Color'),
                        'name' => 'OMNIVERSEPRICING_FONT_COLOR',
                        'tab' => 'design_tab',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Put your font size like "12px"'),
                        'name' => 'OMNIVERSEPRICING_FONT_SIZE',
                        'label' => $this->l('Font Size'),
                        'tab' => 'content_tab',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Put your padding like "6px"'),
                        'name' => 'OMNIVERSEPRICING_PADDING',
                        'label' => $this->l('Padding'),
                        'tab' => 'content_tab',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Stop Recording Price History'),
                        'name' => 'OMNIVERSEPRICING_STOP_RECORD',
                        'values' => [
                            [
                                'id' => 'yes',
                                'value' => true,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'no',
                                'value' => false,
                                'label' => $this->l('No'),
                            ],
                        ],
                        'tab' => 'action_tab',
                    ],
                    [
                        'type' => 'html',
                        'label' => '',
                        'name' => 'OMNIVERSEPRICING_FEAT_DESCRIPTION',
                        'html_content' => '<div>This feature is important for <strong>Italian Progressive Sale Rule</strong>. It will stop recording price history during campaigns that changes price frequently.</div>',
                        'tab' => 'action_tab',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Reset Price History'),
                        'name' => 'OMNIVERSEPRICING_RESET_HISTORY',
                        'values' => [
                            [
                                'id' => 'yes',
                                'value' => true,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'no',
                                'value' => false,
                                'label' => $this->l('No'),
                            ],
                        ],
                        'tab' => 'action_tab',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Delete Data Before 30 Days?'),
                        'name' => 'OMNIVERSEPRICING_DELETE_OLD',
                        'values' => [
                            [
                                'id' => 'yes',
                                'value' => true,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'no',
                                'value' => false,
                                'label' => $this->l('No'),
                            ],
                        ],
                        'tab' => 'action_tab',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Put the product id to start sync from.'),
                        'name' => 'OMNIVERSEPRICING_SYNC_START',
                        'label' => $this->l('Sync Start'),
                        'id' => 'omni_sync_start',
                        'tab' => 'action_tab',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Put the product id to end sync at. (Put same Product Id as "Sync Start" to sync a single product)'),
                        'name' => 'OMNIVERSEPRICING_SYNC_END',
                        'label' => $this->l('Sync End'),
                        'id' => 'omni_sync_end',
                        'tab' => 'action_tab',
                    ],
                    [
                        'type' => 'html',
                        'label' => $this->l('Sync Products Now'),
                        'name' => 'OMNIVERSEPRICING_SYNC_PRODUCTS',
                        'html_content' => '<div><button id="omni_sync_bt" class="btn btn-default" type="button">' . $this->l('Sync Now!!!') . '<img class="omni-sync-loader" src="' . $this->_path . 'views/img/loader.gif" alt="this slowpoke moves"  width="25" /></button></div>',
                        'tab' => 'action_tab',
                        'desc' => $this->l('This will run sync only for this shop. For other stores you need to go to that shop context.'),
                    ],
                    [
                        'type' => 'html',
                        'label' => $this->l('Cron URL'),
                        'name' => 'OMNIVERSEPRICING_CRON_URL',
                        'html_content' => '<div class="input-group"><div class="form-control-plaintext"><a class="d-block" href="#">' . $cron_url . '</a></div></div>',
                        'tab' => 'action_tab',
                        'desc' => $this->l('This url will run Cron job for this shop. Change shop context to get cron url for separate shops.'),
                    ],
                ],
                'tabs' => $tabs,
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $ret_arr = [
            'OMNIVERSEPRICING_SHOW_ON' => Configuration::get('OMNIVERSEPRICING_SHOW_ON', 'discounted'),
            'OMNIVERSEPRICING_NOTICE_PAGE' => Configuration::get('OMNIVERSEPRICING_NOTICE_PAGE', 'single'),
            'OMNIVERSEPRICING_NOTICE_STYLE' => Configuration::get('OMNIVERSEPRICING_NOTICE_STYLE', 'before_after'),
            'OMNIVERSEPRICING_HISTORY_FUNC' => Configuration::get('OMNIVERSEPRICING_HISTORY_FUNC', 'manual'),
            'OMNIVERSEPRICING_SHOW_IF_CURRENT' => Configuration::get('OMNIVERSEPRICING_SHOW_IF_CURRENT', true),
            'OMNIVERSEPRICING_PRICE_WITH_TAX' => Configuration::get('OMNIVERSEPRICING_PRICE_WITH_TAX', false),
            'OMNIVERSEPRICING_PRECENT_INDICATOR' => Configuration::get('OMNIVERSEPRICING_PRECENT_INDICATOR', false),
            'OMNIVERSEPRICING_STOP_RECORD' => Configuration::get('OMNIVERSEPRICING_STOP_RECORD', false),
            'OMNIVERSEPRICING_SYNC_START' => Configuration::get('OMNIVERSEPRICING_SYNC_START', 1),
            'OMNIVERSEPRICING_SYNC_END' => Configuration::get('OMNIVERSEPRICING_SYNC_END', 20),
            'OMNIVERSEPRICING_AUTO_DELETE_OLD' => Configuration::get('OMNIVERSEPRICING_AUTO_DELETE_OLD', false),
            'OMNIVERSEPRICING_POSITION' => Configuration::get('OMNIVERSEPRICING_POSITION', 'after_price'),
            'OMNIVERSEPRICING_BACK_COLOR' => Configuration::get('OMNIVERSEPRICING_BACK_COLOR', '#b3a700'),
            'OMNIVERSEPRICING_FONT_COLOR' => Configuration::get('OMNIVERSEPRICING_FONT_COLOR', '#ffffff'),
            'OMNIVERSEPRICING_FONT_SIZE' => Configuration::get('OMNIVERSEPRICING_FONT_SIZE', '12px'),
            'OMNIVERSEPRICING_PADDING' => Configuration::get('OMNIVERSEPRICING_PADDING', '6px'),
            'OMNIVERSEPRICING_DELETE_OLD' => false,
            'OMNIVERSEPRICING_RESET_HISTORY' => false,
        ];

        $languages = Language::getLanguages(false);

        foreach ($languages as $lang) {
            $ret_arr['OMNIVERSEPRICING_TEXT'][$lang['id_lang']] = Configuration::get('OMNIVERSEPRICING_TEXT_' . $lang['id_lang'], 'Lowest price within 30 days before promotion');
        }

        return $ret_arr;
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $isdemo = false;

        if ($isdemo) {
            return $this->displayError($this->l('Changes are not saved because you are in Demo Mode!!!'));
        }
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            if ($key == 'OMNIVERSEPRICING_POSITION') {
                if (Tools::getValue($key) == 'footer_product') {
                    if (!$this->isRegisteredInHook('displayFooterProduct')) {
                        $this->registerHook('displayFooterProduct');
                    }
                    $this->unregisterHook('displayProductButtons');
                    $this->unregisterHook('displayProductPriceBlock');
                } elseif (Tools::getValue($key) == 'product_bts') {
                    if (!$this->isRegisteredInHook('displayProductButtons')) {
                        $this->registerHook('displayProductButtons');
                    }
                    $this->unregisterHook('displayFooterProduct');
                    $this->unregisterHook('displayProductPriceBlock');
                } elseif (Tools::getValue($key) == 'with_custom_hook') {
                    if (!$this->isRegisteredInHook('displayOmniverseNotice')) {
                        $this->registerHook('displayOmniverseNotice');
                    }
                    $this->unregisterHook('displayFooterProduct');
                    $this->unregisterHook('displayProductButtons');
                    $this->unregisterHook('displayProductPriceBlock');
                } else {
                    if (!$this->isRegisteredInHook('displayProductPriceBlock')) {
                        $this->registerHook('displayProductPriceBlock');
                    }
                    $this->unregisterHook('displayFooterProduct');
                    $this->unregisterHook('displayProductButtons');
                }
            } elseif ($key == 'OMNIVERSEPRICING_TEXT') {
                $languages = Language::getLanguages(false);

                foreach ($languages as $lang) {
                    Configuration::updateValue($key . '_' . $lang['id_lang'], Tools::getValue($key . '_' . $lang['id_lang']));
                }
            } elseif ($key == 'OMNIVERSEPRICING_DELETE_OLD') {
                if (Tools::getValue($key)) {
                    $date = date('Y-m-d');
                    $date_range = date('Y-m-d', strtotime('-31 days'));
                    Db::getInstance()->execute(
                        'DELETE FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc
                        WHERE oc.date < "' . $date_range . '"'
                    );
                    Configuration::updateValue('OMNIVERSEPRICING_DELETE_DATE', $date);
                }
            } elseif ($key == 'OMNIVERSEPRICING_RESET_HISTORY') {
                if (Tools::getValue($key)) {
                    Db::getInstance()->execute(
                        'TRUNCATE `' . _DB_PREFIX_ . 'omniversepricing_products`'
                    );
                }
            }

            Configuration::updateValue($key, Tools::getValue($key));
        }

        $omniversepricing_back_color = Configuration::get('OMNIVERSEPRICING_BACK_COLOR', '#b3a700');
        $omniversepricing_font_color = Configuration::get('OMNIVERSEPRICING_FONT_COLOR', '#ffffff');
        $omniversepricing_font_size = Configuration::get('OMNIVERSEPRICING_FONT_SIZE', '12px');
        $omniversepricing_padding = Configuration::get('OMNIVERSEPRICING_PADDING', '6px');
        $gen_css = '.omniversepricing-notice{
                        padding: ' . $omniversepricing_padding . ' !important;
                        font-size: ' . $omniversepricing_font_size . ' !important;
                        color: ' . $omniversepricing_font_color . ' !important;
                        background: ' . $omniversepricing_back_color . ' !important;
                    }';

        file_put_contents(_PS_MODULE_DIR_ . $this->name . '/views/css/front_generated.css', $gen_css);
        $this->_clearCache('*');
    }

    public function getProductCount($shopId)
    {
        $sql = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product p
            INNER JOIN ' . _DB_PREFIX_ . 'product_shop ps ON p.id_product = ps.id_product
            WHERE ps.id_shop = ' . (int) $shopId . ' AND p.active = 1';
        $count = Db::getInstance()->getValue($sql);

        return $count;
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        $this->context->controller->addJS($this->_path . 'views/js/admin.js');
        $lang_id = $this->context->language->id;
        $shop_id = $this->context->shop->id;
        Media::addJsDef([
            'omniversepricing_ajax_url' => $this->context->link->getAdminLink('AdminAjaxOmniverse'),
            'omniversepricing_shop_id' => $shop_id,
            'omniversepricing_lang_id' => $lang_id,
            'omniversepricing_total_products' => $this->getProductCount($shop_id),
        ]);
        $omni_auto_del = Configuration::get('OMNIVERSEPRICING_AUTO_DELETE_OLD', false);

        if ($omni_auto_del) {
            $date = date('Y-m-d');
            $date_range = date('Y-m-d', strtotime('-31 days'));
            $omniversepricing_delete_date = Configuration::get('OMNIVERSEPRICING_DELETE_DATE');
            if ($omniversepricing_delete_date == $date_range) {
                Db::getInstance()->execute(
                    'DELETE FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc
                    WHERE oc.date < "' . $date_range . '"'
                );
                Configuration::updateValue('OMNIVERSEPRICING_DELETE_DATE', $date);
            }
        }
    }

    /**
     * Shows Price History List in Admin Product Page
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        $id_product = $params['id_product'];

        $lang_id = $this->context->language->id;
        $shop_id = $this->context->shop->id;

        $results = Db::getInstance()->executeS(
            'SELECT *
            FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc
            WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . ' AND oc.`product_id` = ' . (int) $id_product . ' ORDER BY date DESC',
            true
        );
        $omniverse_prices = [];
        $priceFormatter = new PriceFormatter();

        foreach ($results as $result) {
            $omniverse_prices[$result['id_omniversepricing']]['id'] = $result['id_omniversepricing'];
            $omniverse_prices[$result['id_omniversepricing']]['date'] = $result['date'];
            $omniverse_prices[$result['id_omniversepricing']]['price'] = $priceFormatter->format($result['price']);
            $omniverse_prices[$result['id_omniversepricing']]['promotext'] = 'Normal Price';
            if ($result['promo']) {
                $omniverse_prices[$result['id_omniversepricing']]['promotext'] = 'Promotional Price';
            }
        }
        $languages = Language::getLanguages(false);
        $this->context->smarty->assign([
            'omniverse_prices' => $omniverse_prices,
            'omniverse_prd_id' => $id_product,
            'omniverse_langs' => $languages,
            'omniverse_curr_lang' => $lang_id,
        ]);
        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/price_history.tpl');

        return $output;
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addCSS($this->_path . '/views/css/front_generated.css');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    public function hookDisplayOmniverseNotice($params)
    {
        $product = $params['product'];
        $omniversepricing_price = $this->omniversepricing_init($product);

        if ($omniversepricing_price) {
            $show_on = Configuration::get('OMNIVERSEPRICING_SHOW_ON', 'discounted');
            if (!$product->has_discount && $show_on == 'discounted') {
                return;
            }
            $this->omniversepricing_show_notice($omniversepricing_price);
        }
    }

    public function hookActionProductUpdate($params)
    {
        $omni_stop = Configuration::get('OMNIVERSEPRICING_STOP_RECORD', false);
        $history_func = Configuration::get('OMNIVERSEPRICING_HISTORY_FUNC', 'manual');
        if (!$omni_stop) {
            if ($history_func == 'w_change') {
                $product = new Product($params['id_product']);
                $attributes = $this->omniGetProductAttributesInfo($product->id);
                $omni_tax_include = Configuration::get('OMNIVERSEPRICING_PRICE_WITH_TAX', false);
                if ($omni_tax_include) {
                    $omni_tax_include = true;
                } else {
                    $omni_tax_include = false;
                }
                if (isset($attributes) && !empty($attributes)) {
                    foreach ($attributes as $attribute) {
                        $id_attr = $attribute['id_product_attribute'];
                        $prd_arr['id_product_attribute'] = $id_attr;
                        $price_amount = Product::getPriceStatic(
                            (int) $product->id,
                            $omni_tax_include
                        );
                        $existing = $this->omniversepricing_check_existance($product->id, $price_amount, $id_attr);
                        if (empty($existing)) {
                            $this->omniversepricing_insert_data($prd_arr, $product, $price_amount, $omni_tax_include);
                        }
                    }
                } else {
                    $id_attr = 0;
                    $prd_arr['id_product_attribute'] = $id_attr;
                    $price_amount = Product::getPriceStatic(
                        (int) $product->id,
                        $omni_tax_include
                    );
                    $existing = $this->omniversepricing_check_existance($product->id, $price_amount, $id_attr);
                    if (empty($existing)) {
                        $this->omniversepricing_insert_data($prd_arr, $product, $price_amount, $omni_tax_include);
                    }
                }
            }
        }
    }

    public function hookActionObjectSpecificPriceAddAfter($params)
    {
        $omni_stop = Configuration::get('OMNIVERSEPRICING_STOP_RECORD', false);
        $history_func = Configuration::get('OMNIVERSEPRICING_HISTORY_FUNC', 'manual');

        if (!$omni_stop) {
            if ($history_func == 'w_change') {
                $product = new Product($params['object']->id_product);
                $attributes = $this->omniGetProductAttributesInfo($product->id);
                $omni_tax_include = Configuration::get('OMNIVERSEPRICING_PRICE_WITH_TAX', false);
                if ($omni_tax_include) {
                    $omni_tax_include = true;
                } else {
                    $omni_tax_include = false;
                }
                if (isset($attributes) && !empty($attributes)) {
                    foreach ($attributes as $attribute) {
                        $id_attr = $attribute['id_product_attribute'];
                        $prd_arr['id_product_attribute'] = $id_attr;
                        $price_amount = Product::getPriceStatic(
                            (int) $product->id,
                            $omni_tax_include
                        );
                        $existing = $this->omniversepricing_check_existance($product->id, $price_amount, $id_attr);
                        if (empty($existing)) {
                            $this->omniversepricing_insert_data($prd_arr, $product, $price_amount, $omni_tax_include, $params['object']);
                        }
                    }
                } else {
                    $id_attr = 0;
                    $prd_arr['id_product_attribute'] = $id_attr;
                    $price_amount = Product::getPriceStatic(
                        (int) $product->id,
                        $omni_tax_include
                    );
                    $existing = $this->omniversepricing_check_existance($product->id, $price_amount, $id_attr);
                    if (empty($existing)) {
                        $this->omniversepricing_insert_data($prd_arr, $product, $price_amount, $omni_tax_include, $params['object']);
                    }
                }
            }
        }
    }

    public function hookActionObjectSpecificPriceUpdateAfter($params)
    {
        $omni_stop = Configuration::get('OMNIVERSEPRICING_STOP_RECORD', false);
        $history_func = Configuration::get('OMNIVERSEPRICING_HISTORY_FUNC', 'manual');

        if (!$omni_stop) {
            if ($history_func == 'w_change') {
                $product = new Product($params['object']->id_product);
                $attributes = $this->omniGetProductAttributesInfo($product->id);
                $omni_tax_include = Configuration::get('OMNIVERSEPRICING_PRICE_WITH_TAX', false);
                if ($omni_tax_include) {
                    $omni_tax_include = true;
                } else {
                    $omni_tax_include = false;
                }
                if (isset($attributes) && !empty($attributes)) {
                    foreach ($attributes as $attribute) {
                        $id_attr = $attribute['id_product_attribute'];
                        $prd_arr['id_product_attribute'] = $id_attr;
                        $price_amount = Product::getPriceStatic(
                            (int) $product->id,
                            $omni_tax_include
                        );
                        $existing = $this->omniversepricing_check_existance($product->id, $price_amount, $id_attr);
                        if (empty($existing)) {
                            $this->omniversepricing_insert_data($prd_arr, $product, $price_amount, $omni_tax_include, $params['object']);
                        }
                    }
                } else {
                    $id_attr = 0;
                    $prd_arr['id_product_attribute'] = $id_attr;
                    $price_amount = Product::getPriceStatic(
                        (int) $product->id,
                        $omni_tax_include
                    );
                    $existing = $this->omniversepricing_check_existance($product->id, $price_amount, $id_attr);
                    if (empty($existing)) {
                        $this->omniversepricing_insert_data($prd_arr, $product, $price_amount, $omni_tax_include, $params['object']);
                    }
                }
            }
        }
    }

    /**
     * Call back function for the  hook DisplayProductPriceBlock
     */
    public function hookDisplayProductPriceBlock($params)
    {
        $controller = Tools::getValue('controller');
        $notice_page = Configuration::get('OMNIVERSEPRICING_NOTICE_PAGE', 'single');

        if ($controller == 'product') {
            $omniversepricing_pos = Configuration::get('OMNIVERSEPRICING_POSITION', 'after_price');

            if ($params['type'] == $omniversepricing_pos) {
                $product = $params['product'];
                $omniversepricing_price = $this->omniversepricing_init($product);

                if ($omniversepricing_price) {
                    $show_on = Configuration::get('OMNIVERSEPRICING_SHOW_ON', 'discounted');
                    if (!$product->has_discount && $show_on == 'discounted') {
                        return;
                    }
                    $this->omniversepricing_show_notice($omniversepricing_price);
                }
            }
        } else {
            if ($notice_page == 'single' && $controller != 'product') {
                return;
            }
            if ($params['type'] == 'unit_price') {
                $product = $params['product'];
                $omniversepricing_price = $this->omniversepricing_init($product);

                if ($omniversepricing_price) {
                    $show_on = Configuration::get('OMNIVERSEPRICING_SHOW_ON', 'discounted');

                    if (!$product->has_discount && $show_on == 'discounted') {
                        return;
                    }
                    $this->omniversepricing_show_notice($omniversepricing_price);
                }
            }
        }
    }

    /**
     * Call back function for the  hook DisplayFooterProduct
     */
    public function hookDisplayFooterProduct($params)
    {
        $product = $params['product'];
        $omniversepricing_price = $this->omniversepricing_init($product);

        if ($omniversepricing_price) {
            $this->omniversepricing_show_notice($omniversepricing_price);
        }
    }

    /**
     * Call back function for the  hook DisplayProductButtons
     */
    public function hookDisplayProductButtons($params)
    {
        $product = $params['product'];
        $omniversepricing_price = $this->omniversepricing_init($product);
        if ($omniversepricing_price) {
            $this->omniversepricing_show_notice($omniversepricing_price);
        }
    }

    /**
     * Returns the Omnibus Price if poduct has promotion
     */
    private function omniversepricing_init($product)
    {
        $controller = Tools::getValue('controller');
        $history_func = Configuration::get('OMNIVERSEPRICING_HISTORY_FUNC', 'manual');
        $notice_page = Configuration::get('OMNIVERSEPRICING_NOTICE_PAGE', 'single');
        $omni_tax_include = Configuration::get('OMNIVERSEPRICING_PRICE_WITH_TAX', false);
        $percent_indicator = Configuration::get('OMNIVERSEPRICING_PRECENT_INDICATOR', false);
        $product_obj = new Product($product['id_product'], true, $this->context->language->id);

        if ($notice_page == 'single' && $controller != 'product') {
            return;
        }
        if ($omni_tax_include) {
            $omni_tax_include = true;
        } else {
            $omni_tax_include = false;
        }
        $price_amount = Product::getPriceStatic(
            (int) $product_obj->id,
            $omni_tax_include,
            $product['id_product_attribute']
        );

        if ($history_func == 'w_hook') {
            $existing = $this->omniversepricing_check_existance($product_obj->id, $price_amount, $product['id_product_attribute']);
            $omni_stop = Configuration::get('OMNIVERSEPRICING_STOP_RECORD', false);
            if (!$omni_stop) {
                if (empty($existing)) {
                    $this->omniversepricing_insert_data($product, $product_obj, $price_amount, $omni_tax_include);
                }
            }
        }

        $omniverse_price = $this->omniversepricing_get_price($product_obj->id, $price_amount, $product['id_product_attribute']);
        $priceFormatter = new PriceFormatter();
        if ($omniverse_price) {
            $omniversepricinge_formatted_price = $priceFormatter->format($omniverse_price);
            if ($omniverse_price > $price_amount) {
                $omniversepricinge_percentage_amount = ceil((($omniverse_price - $price_amount) / $omniverse_price) * 100);

                if ($percent_indicator) {
                    $omniversepricinge_percentage = '-' . $omniversepricinge_percentage_amount . '%';
                } else {
                    $omniversepricinge_percentage = $omniversepricinge_percentage_amount . '%';
                }
            } elseif ($omniverse_price < $price_amount) {
                $omniversepricinge_percentage_amount = ceil((($price_amount - $omniverse_price) / $price_amount) * 100);

                if ($percent_indicator) {
                    $omniversepricinge_percentage = '+' . $omniversepricinge_percentage_amount . '%';
                } else {
                    $omniversepricinge_percentage = $omniversepricinge_percentage_amount . '%';
                }
            } else {
                $omniversepricinge_percentage = '0%';
            }
            $return_arr['omni_price'] = $omniversepricinge_formatted_price;
            $return_arr['omni_percent'] = $omniversepricinge_percentage;
            return $return_arr;
        } else {
            $omni_if_current = Configuration::get('OMNIVERSEPRICING_SHOW_IF_CURRENT', true);
            if ($omni_if_current) {
                $omniversepricinge_percentage = '0%';
                $return_arr['omni_price'] = $priceFormatter->format($price_amount);
                $return_arr['omni_percent'] = $omniversepricinge_percentage;
                return $return_arr;
            }
            return false;
        }
        return false;
    }

    /**
     * Check if price is alredy available for the product
     */
    private function omniversepricing_check_existance($prd_id, $price, $id_attr = 0)
    {
        $stable_v = Configuration::get('OMNIVERSEPRICING_STABLE_VERSION');
        $lang_id = $this->context->language->id;
        $shop_id = $this->context->shop->id;
        $attr_q = '';
        $curre_q = '';
        $countr_q = '';
        $group_q = '';

        if ($id_attr) {
            $attr_q = ' AND oc.`id_product_attribute` = ' . (int) $id_attr;
        }

        if ($stable_v && version_compare($stable_v, '1.0.2', '>')) {
            $curr_id = $this->context->currency->id;
            $curre_q = ' AND oc.`id_currency` = ' . (int) $curr_id;

            $country_id = $this->context->country->id;
            $countr_q = ' AND oc.`id_country` = ' . (int) $country_id;

            $customer = $this->context->customer;
            if ($customer instanceof Customer && $customer->isLogged()) {
                $groups = $customer->getGroups();
                $id_group = implode(', ', $groups);
            } elseif ($customer instanceof Customer && $customer->isLogged(true)) {
                $id_group = (int) Configuration::get('PS_GUEST_GROUP');
            } else {
                $id_group = (int) Configuration::get('PS_UNIDENTIFIED_GROUP');
            }
            $group_q = ' AND oc.`id_group` IN (0,' . $id_group . ')';
        }
        $results = Db::getInstance()->executeS(
            'SELECT *
            FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc
            WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . '
            AND oc.`product_id` = ' . (int) $prd_id . ' AND oc.`price` = ' . $price . $attr_q . $curre_q . $countr_q . $group_q
        );

        return $results;
    }

    /**
     * Insert the minimum price to the table
     */
    private function omniversepricing_insert_data($prd, $prd_obj, $price, $with_tax = false, $specific_price = null)
    {
        if ($price == 0) {
            return;
        }
        $stable_v = Configuration::get('OMNIVERSEPRICING_STABLE_VERSION');
        $lang_id = $this->context->language->id;
        $shop_id = $this->context->shop->id;
        $date = date('Y-m-d');
        $promo = 0;
        $prd_id = $prd_obj->id;
        $id_attr = $prd['id_product_attribute'];
        $curr_id = $this->context->currency->id;
        $country_id = $this->context->country->id;
        $customer = $this->context->customer;

        if (isset($prd_obj->has_discount) && $prd_obj->has_discount) {
            $promo = 1;
        }
        if ($stable_v && version_compare($stable_v, '1.0.2', '>')) {
            if ($specific_price != null) {
                $curr_id = $specific_price->id_currency;
                $country_id = $specific_price->id_country;
                $customer = $specific_price->id_customer;
                $id_group = $specific_price->id_group;
                $promo = 1;
            } else {
                if (isset($prd_obj->specificPrice['id_group'])) {
                    $id_group = $prd_obj->specificPrice['id_group'];
                } else {
                    $id_group = 0;
                }
            }
            $result = Db::getInstance()->insert('omniversepricing_products', [
                'product_id' => (int) $prd_id,
                'id_product_attribute' => $id_attr,
                'id_country' => $country_id,
                'id_currency' => $curr_id,
                'id_group' => $id_group,
                'price' => $price,
                'promo' => $promo,
                'date' => $date,
                'shop_id' => (int) $shop_id,
                'lang_id' => (int) $lang_id,
            ]);
        } else {
            $result = Db::getInstance()->insert('omniversepricing_products', [
                'product_id' => (int) $prd_id,
                'id_product_attribute' => $id_attr,
                'price' => $price,
                'promo' => $promo,
                'date' => $date,
                'shop_id' => (int) $shop_id,
                'lang_id' => (int) $lang_id,
            ]);
        }
    }

    /**
     * Gets the minimum price within 30 days.
     */
    private function omniversepricing_get_price($id, $price_amount, $id_attr = 0)
    {
        $stable_v = Configuration::get('OMNIVERSEPRICING_STABLE_VERSION');
        $lang_id = $this->context->language->id;
        $shop_id = $this->context->shop->id;
        $attr_q = '';
        $curre_q = '';
        $countr_q = '';
        $group_q = '';
        if ($id_attr) {
            $attr_q = ' AND oc.`id_product_attribute` = ' . (int) $id_attr;
        }
        if ($stable_v && version_compare($stable_v, '1.0.2', '>')) {
            $curr_id = $this->context->currency->id;
            $curre_q = ' oc2.`id_currency` = ' . (int) $curr_id;
            $country_id = $this->context->country->id;
            $countr_q = ' OR oc2.`id_country` = ' . (int) $country_id;
            $customer = $this->context->customer;
            if ($customer instanceof Customer && $customer->isLogged()) {
                $groups = $customer->getGroups();
                $id_group = implode(', ', $groups);
            } elseif ($customer instanceof Customer && $customer->isLogged(true)) {
                $id_group = (int) Configuration::get('PS_GUEST_GROUP');
            } else {
                $id_group = (int) Configuration::get('PS_UNIDENTIFIED_GROUP');
            }

            $group_q = ' OR oc2.`id_group` IN (' . $id_group . ')';

            $inner_q = 'SELECT oc2.id_omniversepricing FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc2 
                       WHERE ' . $curre_q . $countr_q . $group_q;
        }
        $date = date('Y-m-d');
        $date_range = date('Y-m-d', strtotime('-31 days'));
        $q_1 = 'SELECT MIN(price) as ' . $this->name . '_price FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc 
        WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . '
        AND oc.`product_id` = ' . (int) $id . ' AND oc.date > "' . $date_range . '" AND oc.price != "' . $price_amount . '"' . $attr_q . ' AND oc.id_omniversepricing IN (' . $inner_q . ') ';
        $q_2 = 'SELECT MIN(price) as ' . $this->name . '_price FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc 
        WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . '
        AND oc.`product_id` = ' . (int) $id . ' AND oc.date > "' . $date_range . '" AND oc.price != "' . $price_amount . '"' . $attr_q . ' AND oc.`id_currency` = 0 AND oc.`id_country` = 0';
        $result = Db::getInstance()->executeS($q_1 . ' UNION ' . $q_2);

        if (isset($result)) {
            if (isset($result[0][$this->name . '_price']) && $result[0][$this->name . '_price'] != null) {
                return $result[0][$this->name . '_price'];
            } else {
                if (isset($result[1][$this->name . '_price']) && $result[1][$this->name . '_price'] != null) {
                    return $result[1][$this->name . '_price'];
                }
            }
        }

        return false;
    }

    /**
     * Shows the notice
     */
    private function omniversepricing_show_notice($price_data)
    {
        $lang_id = $this->context->language->id;
        $omniversepricing_text = Configuration::get('OMNIVERSEPRICING_TEXT_' . $lang_id, 'Lowest price within 30 days before promotion.');
        $omniversepricing_text_style = Configuration::get('OMNIVERSEPRICING_NOTICE_STYLE', 'before_after');
        $price = $price_data['omni_price'];
        $omni_percentage = $price_data['omni_percent'];
        if ($omniversepricing_text_style == 'mixed') {
            $omniversepricing_text = str_replace('{{omni_price}}', $price, $omniversepricing_text);
            $omniversepricing_text = str_replace('{{omni_percent}}', $omni_percentage, $omniversepricing_text);
        }
        $this->context->smarty->assign([
            'omniversepricing_text' => $omniversepricing_text,
            'omniversepricing_text_style' => $omniversepricing_text_style,
            'omniversepricing_price' => $price,
        ]);
        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/front/omni_front.tpl');
        echo $output;
    }

    private function omniGetProductAttributesInfo($id_product, $shop_only = false)
    {
        return Db::getInstance()->executeS('
        SELECT pa.id_product_attribute, pa.price
        FROM `' . _DB_PREFIX_ . 'product_attribute` pa' .
        ($shop_only ? Shop::addSqlAssociation('product_attribute', 'pa') : '') . '
        WHERE pa.`id_product` = ' . (int) $id_product);
    }
}
