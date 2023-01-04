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

class Omniversepricing extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'omniversepricing';
        $this->version = '1.0.0';
        $this->tab = 'pricing_promotion';
        $this->author = 'TheEnumbin';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('OmniversePricing');
        $this->description = $this->l('This is the module you need to make your PrestaShop Pricing Compatibile for Omnibus Directive');
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        $date = date('Y-m-d H:i:s');
        Configuration::updateValue('OMNIVERSEPRICING_TEXT', 'Lowest price within 30 days before promotion.');
        Configuration::updateValue('OMNIVERSEPRICING_SHOW_IF_CURRENT', true);
        Configuration::updateValue('OMNIVERSEPRICING_POSITION', 'after_price');
        Configuration::updateValue('OMNIVERSEPRICING_BACK_COLOR', '#b3a700');
        Configuration::updateValue('OMNIVERSEPRICING_FONT_COLOR', '#ffffff');
        Configuration::updateValue('OMNIVERSEPRICING_DELETE_DATE', $date);

        include _PS_MODULE_DIR_ . $this->name . '/sql/install.php';

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('displayProductPriceBlock');
    }

    /**
     * This methos is called when uninstalling the module.
     */
    public function uninstall()
    {
        include _PS_MODULE_DIR_ . $this->name . '/sql/uninstall.php';

        Configuration::deleteByName('OMNIVERSEPRICING_TEXT');
        Configuration::deleteByName('OMNIVERSEPRICING_SHOW_IF_CURRENT');
        Configuration::deleteByName('OMNIVERSEPRICING_POSITION');
        Configuration::deleteByName('OMNIVERSEPRICING_BACK_COLOR');
        Configuration::deleteByName('OMNIVERSEPRICING_FONT_COLOR');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        if (((bool) Tools::isSubmit('submitOmniversepricingModule')) == true) {
            $this->postProcess();
        }

        return $this->renderForm();
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
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Text to show where you show the lowest price in last 30 days.'),
                        'name' => 'OMNIVERSEPRICING_TEXT',
                        'label' => $this->l('Omni Directive Text'),
                        'tab' => 'content_tab',
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
                        'tab' => 'content_tab',
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
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'tab' => 'content_tab',
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
                ],
                'tabs' => [
                    'content_tab' => 'Content',
                    'design_tab' => 'Design',
                    'action_tab' => 'Action',
                ],
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
        return [
            'OMNIVERSEPRICING_TEXT' => Configuration::get('OMNIVERSEPRICING_TEXT', 'Lowest price within 30 days before promotion.'),
            'OMNIVERSEPRICING_SHOW_IF_CURRENT' => Configuration::get('OMNIVERSEPRICING_SHOW_IF_CURRENT', true),
            'OMNIVERSEPRICING_POSITION' => Configuration::get('OMNIVERSEPRICING_POSITION', 'after_price'),
            'OMNIVERSEPRICING_BACK_COLOR' => Configuration::get('OMNIVERSEPRICING_BACK_COLOR', '#b3a700'),
            'OMNIVERSEPRICING_FONT_COLOR' => Configuration::get('OMNIVERSEPRICING_FONT_COLOR', '#ffffff'),
            'OMNIVERSEPRICING_FONT_SIZE' => Configuration::get('OMNIVERSEPRICING_FONT_SIZE', '12px'),
            'OMNIVERSEPRICING_PADDING' => Configuration::get('OMNIVERSEPRICING_PADDING', '6px'),
            'OMNIVERSEPRICING_DELETE_OLD' => false,
        ];
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            if ($key == 'OMNIVERSEPRICING_POSITION') {
                if (Tools::getValue($key) == 'footer_product') {
                    $this->registerHook('displayFooterProduct');
                    $this->unregisterHook('displayProductButtons');
                    $this->unregisterHook('displayProductPriceBlock');
                } elseif (Tools::getValue($key) == 'product_bts') {
                    $this->registerHook('displayProductButtons');
                    $this->unregisterHook('displayFooterProduct');
                    $this->unregisterHook('displayProductPriceBlock');
                } else {
                    $this->registerHook('displayProductPriceBlock');
                    $this->unregisterHook('displayFooterProduct');
                    $this->unregisterHook('displayProductButtons');
                }
            } elseif ($key == 'OMNIVERSEPRICING_DELETE_OLD') {
                if (Tools::getValue($key)) {
                    $date = date('Y-m-d H:i:s');
                    $date_range = date('Y-m-d H:i:s', strtotime('-31 days'));

                    Db::getInstance()->execute(
                        'DELETE FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc
                        WHERE oc.date < "' . $date_range . '"'
                    );
                    Configuration::updateValue('OMNIVERSEPRICING_DELETE_DATE', $date);
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
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookDisplayBackOfficeHeader()
    {
        $date = date('Y-m-d H:i:s');
        $date_range = date('Y-m-d H:i:s', strtotime('-31 days'));
        $omniversepricing_delete_date = Configuration::get('OMNIVERSEPRICING_DELETE_DATE');

        if ($omniversepricing_delete_date == $date_range) {
            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc
                WHERE oc.date < "' . $date_range . '"'
            );
            Configuration::updateValue('OMNIVERSEPRICING_DELETE_DATE', $date);
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addCSS($this->_path . '/views/css/front_generated.css');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    /**
     * Call back function for the  hook DisplayProductPriceBlock
     */
    public function hookDisplayProductPriceBlock($params)
    {
        $product = $params['product'];
        $omniversepricing_price = $this->omniversepricing_init($product);

        if ($omniversepricing_price) {
            $omniversepricing_pos = Configuration::get('OMNIVERSEPRICING_POSITION', 'after_price');

            if ($params['type'] == $omniversepricing_pos) {
                $this->omniversepricing_show_notice($omniversepricing_price);
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

        if ($controller == 'product') {
            if ($product->has_discount) {
                $price_amount = $product->price_amount;
                $existing = $this->omniversepricing_check_existance($product->id, $price_amount);

                if (empty($existing)) {
                    $this->omniversepricing_insert_data($product->id, $price_amount);
                }
                $omniverse_price = $this->omniversepricing_get_price($product->id, $price_amount);

                if ($omniverse_price) {
                    $omniversepricinge_formatted_price = $this->context->getCurrentLocale()->formatPrice($omniverse_price, $this->context->currency->iso_code);

                    return $omniversepricinge_formatted_price;
                } else {
                    $omni_if_current = Configuration::get('OMNIVERSEPRICING_SHOW_IF_CURRENT', true);

                    if ($omni_if_current) {
                        return $product->price;
                    }

                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Check if price is alredy available for the product
     */
    private function omniversepricing_check_existance($prd_id, $price)
    {
        $lang_id = $this->context->language->id;
        $shop_id = $this->context->shop->id;

        $results = Db::getInstance()->executeS(
            'SELECT *
            FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc
            WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . '
            AND oc.`product_id` = ' . (int) $prd_id . ' AND oc.`price` = ' . $price
        );

        return $results;
    }

    /**
     * Insert the minimum price to the table
     */
    private function omniversepricing_insert_data($prd_id, $price)
    {
        $lang_id = $this->context->language->id;
        $shop_id = $this->context->shop->id;
        $date = date('Y-m-d H:i:s');

        $result = Db::getInstance()->insert('omniversepricing_products', [
            'product_id' => (int) $prd_id,
            'price' => $price,
            'date' => $date,
            'shop_id' => (int) $shop_id,
            'lang_id' => (int) $lang_id,
        ]);
    }

    /**
     * Gets the minimum price within 30 days.
     */
    private function omniversepricing_get_price($id, $price_amount)
    {
        $lang_id = $this->context->language->id;
        $shop_id = $this->context->shop->id;
        $date = date('Y-m-d H:i:s');
        $date_range = date('Y-m-d H:i:s', strtotime('-31 days'));
        $result = Db::getInstance()->getValue('SELECT MIN(price) as ' . $this->name . '_price FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc 
        WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . '
        AND oc.`product_id` = ' . (int) $id . ' AND oc.date > "' . $date_range . '" AND oc.price != "' . $price_amount . '"');

        return $result;
    }

    /**
     * Shows the notice
     */
    private function omniversepricing_show_notice($price)
    {
        $omniversepricing_text = Configuration::get('OMNIVERSEPRICING_TEXT', 'Lowest price within 30 days before promotion.');
        $this->context->smarty->assign([
            'omniversepricing_text' => $omniversepricing_text,
            'omniversepricing_price' => $price,
        ]);
        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/front/omni_front.tpl');

        echo $output;
    }
}
