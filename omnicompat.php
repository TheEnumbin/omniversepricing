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

class Omnicompat extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'omnicompat';
        $this->version = '1.0.0';
        $this->author = 'TheEnumbin';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('OmniCompat');
        $this->description = $this->l('Omnibus Directive Pricing Compatibility Module for PrestaShop');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        $date = date('Y-m-d H:i:s');
        Configuration::updateValue('OMNICOMPAT_TEXT', 'Lowest price within 30 days before promotion.');
        Configuration::updateValue('OMNICOMPAT_SHOW_IF_CURRENT', true);
        Configuration::updateValue('OMNICOMPAT_POSITION', 'after_price');
        Configuration::updateValue('OMNICOMPAT_BACK_COLOR', '#b3a700');
        Configuration::updateValue('OMNICOMPAT_FONT_COLOR', '#ffffff');
        Configuration::updateValue('OMNICOMPAT_DELETE_DATE', $date);

        include _PS_MODULE_DIR_ . $this->name . '/sql/install.php';

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayAdminProductsExtra') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('displayProductPriceBlock');
    }

    public function uninstall()
    {
        Configuration::deleteByName('OMNICOMPAT_TEXT');
        Configuration::deleteByName('OMNICOMPAT_SHOW_IF_CURRENT');
        Configuration::deleteByName('OMNICOMPAT_POSITION');
        Configuration::deleteByName('OMNICOMPAT_BACK_COLOR');
        Configuration::deleteByName('OMNICOMPAT_FONT_COLOR');

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
        if (((bool)Tools::isSubmit('submitOmnicompatModule')) == true) {
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
        $helper->submit_action = 'submitOmnicompatModule';
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
                        'name' => 'OMNICOMPAT_TEXT',
                        'label' => $this->l('Omni Directive Text'),
                        'tab' => 'content_tab',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Show notice if current price is the lowest.'),
                        'name' => 'OMNICOMPAT_SHOW_IF_CURRENT',
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
                        'name' => 'OMNICOMPAT_POSITION',
                        'options' => [
                            'query' => [
                                [
                                    'id' => 'price',
                                    'name' => $this->l('Before Price'),
                                ],
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
                        'name' => 'OMNICOMPAT_BACK_COLOR',
                        'tab' => 'design_tab',
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Text Color'),
                        'name' => 'OMNICOMPAT_FONT_COLOR',
                        'tab' => 'design_tab',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Put your font size like "12px"'),
                        'name' => 'OMNICOMPAT_FONT_SIZE',
                        'label' => $this->l('Font Size'),
                        'tab' => 'content_tab',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Put your padding like "6px"'),
                        'name' => 'OMNICOMPAT_PADDING',
                        'label' => $this->l('Padding'),
                        'tab' => 'content_tab',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Delete Data Before 30 Days?'),
                        'name' => 'OMNICOMPAT_DELETE_OLD',
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
        return array(
            'OMNICOMPAT_TEXT' => Configuration::get('OMNICOMPAT_TEXT', 'Lowest price within 30 days before promotion.'),
            'OMNICOMPAT_SHOW_IF_CURRENT' => Configuration::get('OMNICOMPAT_SHOW_IF_CURRENT', true),
            'OMNICOMPAT_POSITION' => Configuration::get('OMNICOMPAT_POSITION', 'after_price'),
            'OMNICOMPAT_BACK_COLOR' => Configuration::get('OMNICOMPAT_BACK_COLOR', '#b3a700'),
            'OMNICOMPAT_FONT_COLOR' => Configuration::get('OMNICOMPAT_FONT_COLOR', '#ffffff'),
            'OMNICOMPAT_FONT_SIZE' => Configuration::get('OMNICOMPAT_FONT_SIZE', '12px'),
            'OMNICOMPAT_PADDING' => Configuration::get('OMNICOMPAT_PADDING', '6px'),
            'OMNICOMPAT_DELETE_OLD' => false,
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {

            if($key == 'OMNICOMPAT_POSITION'){

                if(Tools::getValue($key) == 'footer_product'){
                    $this->registerHook('displayFooterProduct');
                    $this->unregisterHook('displayProductButtons');
                    $this->unregisterHook('displayProductPriceBlock');
                }elseif(Tools::getValue($key) == 'product_bts'){
                    $this->registerHook('displayProductButtons');
                    $this->unregisterHook('displayFooterProduct');
                    $this->unregisterHook('displayProductPriceBlock');
                }else{
                    $this->registerHook('displayProductPriceBlock');
                    $this->unregisterHook('displayFooterProduct');
                    $this->unregisterHook('displayProductButtons');
                }
            }elseif($key == 'OMNICOMPAT_DELETE_OLD'){

                if(Tools::getValue($key)){
                    $date = date('Y-m-d H:i:s');
                    $date_range = date('Y-m-d H:i:s', strtotime('-31 days'));

                    Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'omnicompat_products` oc
                        WHERE oc.date < "' . $date_range . '"'
                    );
                    Configuration::updateValue('OMNICOMPAT_DELETE_DATE', $date);
                }
            }
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookDisplayBackOfficeHeader()
    {
        $date = date('Y-m-d H:i:s');
        $date_range = date('Y-m-d H:i:s', strtotime('-31 days'));
        $omnicompat_delete_date = Configuration::get('OMNICOMPAT_DELETE_DATE');
        if($omnicompat_delete_date == $date_range){
            Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'omnicompat_products` oc
                WHERE oc.date < "' . $date_range . '"'
            );
            Configuration::updateValue('OMNICOMPAT_DELETE_DATE', $date);
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    
    public function hookDisplayProductPriceBlock($params)
    {
        $product = $params['product'];
        $omnicompat_price = $this->omnicompat_init($product);

        if($omnicompat_price){
            $omnicompat_pos = Configuration::get('OMNICOMPAT_POSITION', 'after_price');
            if($params['type'] == $omnicompat_pos ){   
                $omni_if_current = Configuration::get('OMNICOMPAT_SHOW_IF_CURRENT', true);
    
                if($omni_if_current){
                    $this->omnicompat_show_notice($product->price);
                }
            }
        }
    }

    public function hookDisplayFooterProduct($params)
    {
        $product = $params['product'];
        $omnicompat_price = $this->omnicompat_init($product);

        if($omnicompat_price){ 
            $omni_if_current = Configuration::get('OMNICOMPAT_SHOW_IF_CURRENT', true);

            if($omni_if_current){
                $this->omnicompat_show_notice($product->price);
            }
        }
    }

    public function hookDisplayProductButtons($params)
    {
        $product = $params['product'];
        $omnicompat_price = $this->omnicompat_init($product);

        if($omnicompat_price){ 
            $omni_if_current = Configuration::get('OMNICOMPAT_SHOW_IF_CURRENT', true);

            if($omni_if_current){
                $this->omnicompat_show_notice($product->price);
            }
        }
    }

    private function omnicompat_init($product){

        $controller = Tools::getValue('controller');

        if($controller == 'product'){

            if($product->has_discount){
                $price_amount = $product->price_amount;
                $existing = $this->omnicompat_check_existance($product->id, $price_amount);
    
                if(empty($existing)){
                    $this->omnicompat_insert_data($product->id, $price_amount);   
                }
                $omnicompate_formatted_price = $this->context->getCurrentLocale()->formatPrice($this->omnicompat_get_price($product->id), $this->context->currency->iso_code);     
    
                return $omnicompate_formatted_price;
            }
        }

        return false;
    }
    
    private function omnicompat_check_existance($prd_id, $price){
        $lang_id = $this->context->language->id;
        $shop_id = $this->context->shop->id;

        $results = Db::getInstance()->executeS('SELECT *
            FROM `' . _DB_PREFIX_ . 'omnicompat_products` oc
            WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . '
            AND oc.`product_id` = ' . (int) $prd_id . ' AND oc.`price` = ' . $price
        );

        return $results;
    }

    private function omnicompat_insert_data($prd_id, $price){
        $lang_id = $this->context->language->id;
        $shop_id = $this->context->shop->id;
        $date = date('Y-m-d H:i:s');

        $result = Db::getInstance()->insert('omnicompat_products', [
            'product_id' => (int) $prd_id,
            'price' => $price,
            'date' => $date,
            'shop_id' => (int) $shop_id,
            'lang_id' => (int) $lang_id,
        ]);

    }

    private function omnicompat_get_price($id){
        $lang_id = $this->context->language->id;
        $shop_id = $this->context->shop->id;
        $date = date('Y-m-d H:i:s');
        $date_range = date('Y-m-d H:i:s', strtotime('-31 days'));
        $result = Db::getInstance()->getValue('SELECT MIN(price) as ' . $this->name . '_price FROM `' . _DB_PREFIX_ . 'omnicompat_products` oc 
        WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . '
        AND oc.`product_id` = ' . (int) $id . ' AND oc.date > "' . $date_range . '"');

        return $result;
    }

    private function omnicompat_show_notice($price){
        $omnicompat_text = Configuration::get('OMNICOMPAT_TEXT', 'Lowest price within 30 days before promotion.');
        $omnicompat_back_color = Configuration::get('OMNICOMPAT_BACK_COLOR', '#b3a700');
        $omnicompat_font_color = Configuration::get('OMNICOMPAT_FONT_COLOR', '#ffffff');
        $omnicompat_font_size = Configuration::get('OMNICOMPAT_FONT_SIZE', '12px');
        $omnicompat_padding = Configuration::get('OMNICOMPAT_PADDING', '6px');
        $this->context->smarty->assign([
            'omnicompat_text' => $omnicompat_text,
            'omnicompat_price' => $price,
            'omnicompat_back_color' => $omnicompat_back_color,
            'omnicompat_font_color' => $omnicompat_font_color,
            'omnicompat_font_size' => $omnicompat_font_size,
            'omnicompat_padding' => $omnicompat_padding,
        ]);
        $output = $this->context->smarty->fetch($this->local_path.'views/templates/front/omni_front.tpl');

        echo $output;
    }
}
