<?php
/*
*
* Author: Jeff Simons Decena @2013
*
*/

if (!defined('_PS_VERSION_'))
	exit;

class Paypal extends PaymentModule
{

	public function __construct()
	{
	$this->name = 'paypal';
	$this->tab = 'payments_gateways';
	$this->version = '0.1';
	$this->author = 'Jeff Simons Decena';
	$this->need_instance = 0;
	$this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.6');	

	parent::__construct();

	$this->displayName = $this->l('Paypal Module');
	$this->description = $this->l('Paypal configuration');

	$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

	if (!Configuration::get('PAYPAL'))      
	  $this->warning = $this->l('No name provided');
	}

	public function install()
	{
	  return parent::install() &&
	  	Configuration::updateValue('PAYPAL', 'PAYPAL') &&
        $this->registerHook('paymentReturn') &&
	  	$this->registerHook('payment');
	}	

	public function uninstall()
	{
	  return parent::uninstall() && 
	  	Configuration::deleteByName('PAYPAL');
	}

	public function getContent()
	{
	    $output = null;
	 
	    if (Tools::isSubmit('submit'.$this->name))
	    {
	        $my_module_name = strval(Tools::getValue('PAYPAL'));
	        if (!$my_module_name  || empty($my_module_name) || !Validate::isGenericName($my_module_name))
	            $output .= $this->displayError( $this->l('Invalid Configuration value') );
	        else
	        {
	            Configuration::updateValue('PAYPAL', $my_module_name);
                Configuration::updateValue('PP_EMAIL', Tools::getValue('PP_EMAIL'));
                Configuration::updateValue('PP_CLIENT_ID', Tools::getValue('PP_CLIENT_ID'));
                Configuration::updateValue('PP_SECRET', Tools::getValue('PP_SECRET'));
                Configuration::updateValue('PP_STATUS', Tools::getValue('PP_STATUS'));
	            $output .= $this->displayConfirmation($this->l('Settings updated'));
	        }
	    }
	    return $output.$this->displayForm();
	}

	public function displayForm()
	{
	    // Get default Language
	    $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
	     
	    // Init Fields form array
	    $fields_form[0]['form'] = array(
	        'legend' => array(
	            'title' => $this->l('Settings'),
	        ),
	        'input' => array(
	            array(
	                'type' => 'hidden',
	                'label' => $this->l('My module name'),
	                'name' => 'PAYPAL',
	                'size' => 20,
	                'required' => true
	            ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Paypal Email'),
                    'name' => 'PP_EMAIL',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Paypal Client ID'),
                    'name' => 'PP_CLIENT_ID',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Paypal Secret'),
                    'name' => 'PP_SECRET',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Paypal Status'),
                    'name' => 'PP_STATUS',
                    'size' => 20,
                    'required' => true,
                    'placeholder' => 'sandbox or live'
                )                
	        ),
	        'submit' => array(
	            'title' => $this->l('Save'),
	            'class' => 'button'
	        )
	    );
	     
	    $helper = new HelperForm();
	     
	    // Module, token and currentIndex
	    $helper->module = $this;
	    $helper->name_controller = $this->name;
	    $helper->token = Tools::getAdminTokenLite('AdminModules');
	    $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
	     
	    // Language
	    $helper->default_form_language = $default_lang;
	    $helper->allow_employee_form_lang = $default_lang;
	     
	    // Title and toolbar
	    $helper->title = $this->displayName;
	    $helper->show_toolbar = true;        // false -> remove toolbar
	    $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
	    $helper->submit_action = 'submit'.$this->name;
	    $helper->toolbar_btn = array(
	        'save' =>
	        array(
	            'desc' => $this->l('Save'),
	            'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
	            '&token='.Tools::getAdminTokenLite('AdminModules'),
	        ),
	        'back' => array(
	            'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
	            'desc' => $this->l('Back to list')
	        )
	    );
	     
	    // Load current value
	    $helper->fields_value['PAYPAL'] = Configuration::get('PAYPAL');
        $helper->fields_value['PP_EMAIL'] = Configuration::get('PP_EMAIL');
        $helper->fields_value['PP_CLIENT_ID'] = Configuration::get('PP_CLIENT_ID');
        $helper->fields_value['PP_SECRET'] = Configuration::get('PP_SECRET');
        $helper->fields_value['PP_STATUS'] = Configuration::get('PP_STATUS');
	     
	    return $helper->generateForm($fields_form);
	}

    public function hookPayment()
    {
        $this->smarty->assign(
            array(
                'action' => $this->context->link->getModuleLink('paypal', 'process')
            )
        );

        return $this->display(__FILE__, 'payment.tpl');
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active)
            return;

        $state = $params['objOrder']->getCurrentState();
        if (in_array($state, array(Configuration::get('PS_OS_PAYMENT'), Configuration::get('PS_OS_OUTOFSTOCK'), Configuration::get('PS_OS_OUTOFSTOCK_UNPAID'))))
        {
            $this->smarty->assign(array(
                'status' => 'ok',
            ));
            if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference))
                $this->smarty->assign('reference', $params['objOrder']->reference);
        }
        else
            $this->smarty->assign('status', 'failed');
        return $this->display(__FILE__, 'return.tpl');
    }    
}