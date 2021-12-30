<?php
/*
*  2012 3e Internet Software House
*
*  @author 	 3e Internet Software House <rafal.zielinski@3e.pl>
*  @version  1.0
*  @license  Publikowane na zasadach licencji GNU General Public License
*/

if (!defined('_CAN_LOAD_FILES_'))
    exit;

define('CONFIGURATION_LOGIN','DHL24_LOGIN');
define('CONFIGURATION_PASSWORD','DHL24_PASSWORD');

class Dhl24Shipping extends Module
{
    private $_validate = true;
    private $_html = '';

    public function __construct()
    {
        global $cookie;

        $this->name = 'dhl24shipping';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0p1.4m';
        $this->author = 'Piotr Maślanka, 3e Internet Software House';
        $this->need_instance = 0;

        $this->displayName = $this->l('DHL24 Shipping');
        $this->description = $this->l('Offer your customers, different delivery methods with UPS');

        parent::__construct();
    }

    public function install()
    {

        // Install SQL
        include(dirname(__FILE__).'/sql-install.php');
        foreach ($sql as $s)
            if (!Db::getInstance()->Execute($s))
                return false;

        return parent::install();
    }

    public function uninstall()
    {

        // Uninstall SQL
        include(dirname(__FILE__).'/sql-uninstall.php');
        foreach ($sql as $s)
            if (!Db::getInstance()->Execute($s))
                return false;


        // Uninstall Module
        if (!parent::uninstall())
            return false;

        return true;
    }

    private function _postProcess()
    {

    	if (isset($_POST['submitConfoData'])) {
    		Configuration::updateValue('dhl24shipping_e_city', Tools::getValue('e_ship_city'));
    		Configuration::updateValue('dhl24shipping_e_cemail', Tools::getValue('e_ship_cemail'));
    		Configuration::updateValue('dhl24shipping_e_cperson', Tools::getValue('e_ship_cperson'));
    		Configuration::updateValue('dhl24shipping_e_cphone', Tools::getValue('e_ship_cphone'));
    		Configuration::updateValue('dhl24shipping_e_house_number', Tools::getValue('e_ship_house_number'));
    		Configuration::updateValue('dhl24shipping_e_apart_number', Tools::getValue('e_ship_apart_number'));
    	    Configuration::updateValue('dhl24shipping_e_name', Tools::getValue('e_ship_name'));
    		Configuration::updateValue('dhl24shipping_e_postal', Tools::getValue('e_ship_postal'));
    	    Configuration::updateValue('dhl24shipping_e_street', Tools::getValue('e_ship_street'));

    	    Configuration::updateValue('dhl24shipping_e_webapi_login', Tools::getValue('e_webapi_login'));
    	    Configuration::updateValue('dhl24shipping_e_webapi_password', Tools::getValue('e_webapi_password'));
    	    Configuration::updateValue('dhl24shipping_e_webapi_sap', Tools::getValue('e_webapi_sap'));    	    	
    	}
    	
    	
        if (isset($_POST['submitDHL24LoginData']) && $_POST['submitDHL24LoginData'] == 'Zapisz')
        {

                $login = $_POST['dhl24Login'];
                $password1 = $_POST['dhl24Password1'];
                $password2 = $_POST['dhl24Password2'];

                if( ($this->_validate = $this->validate($login,$password1,$password2)) )
                {
                    $pasword_md5 = md5($password1);
                    $date_upd = date('y-m-d H:i:s');

                    $conf_login = CONFIGURATION_LOGIN;
                    $conf_password = CONFIGURATION_PASSWORD;

                    $sqlUpdateLogin = "UPDATE "._DB_PREFIX_."configuration set value = '$login',date_upd = '$date_upd' where name = '$conf_login'";
                    $sqlUpdatePassword = "UPDATE "._DB_PREFIX_."configuration set value = '$pasword_md5',date_upd = '$date_upd' where name = '$conf_password'";

                    if(Db::getInstance()->Execute( $sqlUpdateLogin ) && Db::getInstance()->Execute( $sqlUpdatePassword ))
                                     $this->_html .= $this->displayConfirmation( $this->l('Your login data has been successfully saved!') );
                    else  $this->_html .= $this->displayError( $this->l('Error: Cannot save login data!') );

                }

        }
    }

    private function validate($login,$password1,$password2){

        $errors = '';
        $validate = true;

        if( empty($login) || empty($password1)|| empty($password2) )
        {
            $errors .= $this->displayError($this->l('Error: Not all fields are filled!'));
            $validate = false;
        }


//	Not so much "must be email" for test web api - ~~henrietta
//        if ( !empty($login) && !preg_match('/^[a-zA-Z0-9.\-_]+@[a-zA-Z0-9\-.]+\.[a-zA-Z]{2,4}$/', $login)) {
//            $errors .= $this->displayError($this->l('Error: Login must have e-mail format!'));
//            $validate = false;
//       }

        if($password1 !== $password2)
        {
            $errors .= $this->displayError($this->l('Error: Passwords doesn\'t match!'));
            $validate = false;
        }

        $this->_html .= $errors;
        return $validate;
    }


    private function _displayConfigurationForm()
    {

        $data_login = Db::getInstance()->getRow("SELECT value,name FROM "._DB_PREFIX_."configuration WHERE name = '".CONFIGURATION_LOGIN."'");
        $data_password = Db::getInstance()->getRow("SELECT value,name FROM "._DB_PREFIX_."configuration WHERE name = '".CONFIGURATION_PASSWORD."'");

        $isInserted = $data_login['value'] != null && $data_password['value'] != null;
        $actionUpdate = isset($_POST['DHL24Action']) && $_POST['DHL24Action'] == 'Change' ? true : false;

        $loginValue = isset( $_POST['dhl24Login'] ) ? $_POST['dhl24Login'] : $data_login['value'] ;

        $this->_html .= '
        <link rel="stylesheet" type="text/css" href="../css/DHL24/dhl24_module.css"/>
		<fieldset class="width3">
            <p>
                <b>'.$this->l('This module will fasten your shipment process').'.</b><br/>'.$this->l('Filling below form with your DHL24 login data will make your life much easier, you will order currier without login process to DHL24').'
            </p>
        </fieldset>
        <br />
        <fieldset class="width3"><legend>'.$this->l('Login data to DHL24 service').'</legend>
            <form id="dhl24LoginForm" action="'.$_SERVER['REQUEST_URI'].'" method="post">
                <center>
                    <div style="text-align: left;">';

                        if(!$isInserted || $actionUpdate || !$this->_validate )
                        {
                            $this->_html .= '
                            <div class="dhl24_form_mrg4px"><label>'.$this->l('Login').':</label> <input type="text" name="dhl24Login" value="'.( $isInserted ? $loginValue : ( isset($_POST['dhl24Login']) ? $_POST['dhl24Login'] : '' ) ).'" /></div>
                            <div class="dhl24_form_mrg4px"><label>'.$this->l('Password').':</label> <input type="password" name="dhl24Password1" value="" /></div>
                            <div><label>'.$this->l('Repeat password').':</label> <input type="password" name="dhl24Password2" value="" /></div>';
                        }
                        else{
                            $this->_html .= '
                            <div class="dhl24_form_mrg4px"><label>'.$this->l('Login').':</label> <span class="dhl24_statictext">'.$data_login['value'].'</span></div>
                            <div class="dhl24_form_mrg4px"><label>'.$this->l('Password').':</label> <span class="dhl24_statictext">'.$data_password['value'].'</span></div>';
                        }

        $this->_html .= '</div>
                </center>
                <br />
                <center>
                    '.( $isInserted && ($actionUpdate || !$this->_validate) ? '<input type="button" onClick="location.href=\''.$_SERVER['REQUEST_URI'].'\'" class="button" value="Cancel"/>' : '' ).'
                    <input type="hidden" name="DHL24Action" value="'.( $isInserted && (!$actionUpdate && $this->_validate) ? 'Change' : 'Save' ).'" />
                    <input type="submit" class="button" name="submitDHL24LoginData" value="'.( $isInserted && (!$actionUpdate && $this->_validate) ? $this->l('Change') : $this->l('Save')).'" />
                </center>
            </form>
        </fieldset>';
        
        $this->_html .= '<fieldset><form action="'.$_SERVER['REQUEST_URI'].'" method="post"><center>
        		Dane wysyłającego:
<div class="dhl24_form_mrg4px"><label>Miasto:</label> <input type="text" name="e_ship_city" value="'.Configuration::get('dhl24shipping_e_city').'" /></div>
<div class="dhl24_form_mrg4px"><label>E-mail kontaktowy:</label> <input type="text" name="e_ship_cemail" value="'.Configuration::get('dhl24shipping_e_cemail').'" /></div>
<div class="dhl24_form_mrg4px"><label>Osoba kontaktowa:</label> <input type="text" name="e_ship_cperson" value="'.Configuration::get('dhl24shipping_e_cperson').'" /></div>
<div class="dhl24_form_mrg4px"><label>Telefon kontaktowy:</label> <input type="text" name="e_ship_cphone" value="'.Configuration::get('dhl24shipping_e_cphone').'" /></div>
<div class="dhl24_form_mrg4px"><label>Nr budynku:</label> <input type="text" name="e_ship_house_number" value="'.Configuration::get('dhl24shipping_e_house_number').'" /></div>
<div class="dhl24_form_mrg4px"><label>Nr lokalu:</label> <input type="text" name="e_ship_apart_number" value="'.Configuration::get('dhl24shipping_e_apart_number').'" /></div>
<div class="dhl24_form_mrg4px"><label>Nazwa:</label> <input type="text" name="e_ship_name" value="'.Configuration::get('dhl24shipping_e_name').'" /></div>
<div class="dhl24_form_mrg4px"><label>Kod pocztowy:</label> <input type="text" name="e_ship_postal" value="'.Configuration::get('dhl24shipping_e_postal').'" /></div>
<div class="dhl24_form_mrg4px"><label>Ulica:</label> <input type="text" name="e_ship_street" value="'.Configuration::get('dhl24shipping_e_street').'" /></div>
<div class="dhl24_form_mrg4px"><label>Login WebAPI:</label> <input type="text" name="e_webapi_login" value="'.Configuration::get('dhl24shipping_e_webapi_login').'" /></div>
<div class="dhl24_form_mrg4px"><label>Hasło WebAPI:</label> <input type="text" name="e_webapi_password" value="'.Configuration::get('dhl24shipping_e_webapi_password').'" /></div>
<div class="dhl24_form_mrg4px"><label>Nr SAP:</label> <input type="text" name="e_webapi_sap" value="'.Configuration::get('dhl24shipping_e_webapi_sap').'" /></div>
<input type="submit" class="button" name="submitConfoData" value="Zapisz"></center></form></fieldset>';

    }

    private function _displayForm()
    {
        $this->_displayConfigurationForm();
    }

    public function getContent()
    {
        $this->_html .= '<h2>'.$this->displayName.'</h2>';

        if (!empty($_POST))
            $this->_html .= $this->_postProcess();
        $this->_displayForm();

        return $this->_html;
    }

}

