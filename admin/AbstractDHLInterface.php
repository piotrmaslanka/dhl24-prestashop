<?php

/*  3e Software & Interactive House Agency - http://www.3e.pl
 *
 *  Plik jest częścią pakietu Integracji e-sklepu z serwisem DHL Zamów Kuriera.
 *  Uwaga: Nie zaleca się wprowadzania jakichkolwiek modyfikacji w skrypcie.
 *
 * 	Skrypt zawiera:
 * 	- abstrakcyjną klasę AbstractDHLInterface
 *  - implementacje metod wykorzystywanych w procesie przetwarzania żądań modułu
 *  - metody abstrakcyjne implementowane w klasie dziedziczącej (DHLInterface)
 *
 *  @autor Rafał Zieliński <rafal.zielinski@3e.pl> 
 *  @version 1.0
 *	2012-06-26
 *
 *  Publikowane na zasadach licencji GNU General Public License
 *  
 *  Poprawione przez Piotr "Henrietta" Maślanka 2013
 */
include_once('../modules/dhl24shipping/DHL24_webapi_client.php');

define('DHL_PROTOCOL', 'https://');
define('DHL_DOMAIN', 'testowy.dhl24.com.pl');

define('FAILURE', 'false');

function cse($k) { return str_replace('-', '', $k); }

abstract class AbstractDHLInterface {

    protected $authorization = true;
    protected $encode_md5 = false;
    protected $password = '';
    protected $login = '';
    private $sid = '';
    protected $param = array();
    private $errors = array();

    protected abstract function saveOrderData($lp, $p_id, $o_id);

    protected abstract function DBConnect();

    protected abstract function getOrderData($o_id,$token);

    static public abstract function renderDHLView($lp, $sip, $oid, $lang = 'PL');

    protected function Init() {
        $this->param = $_REQUEST;
    }

    /*
     *   Funkcja główna przetwarzająca żądanie otrzymane z e-sklepu oraz/lub z serwisu DHL.
     *
     *   function processRequest
     */

    public function processRequest() {

        $this->validate();

        switch ($this->param['scenario']) {
            case 'update':
                $this->saveOrderData($this->param['lp'], $this->param['pid'], $this->param['oid']);
                
                if(!empty($_GET) and $_GET['zamow'] == '1') $location_url = DHL_PROTOCOL . DHL_DOMAIN . '/zamowienie/utworz?t='.$this->param['pid'];
                else $location_url = DHL_PROTOCOL . DHL_DOMAIN . '/przesylka/lista';
                    
                Header('Location: '.$location_url);
                exit();
                break;

            case 'shipment':

            	if ($this->param['section'] == 'shipping') {            	
	            	$pd = $this->getOrderData($this->param['oid'],$this->param['token']);
	            	
	            	$d24i = new DHL24_webapi_client();
	            	
	            	// Czy za pobraniem?
	            	$q = Db::getInstance()->ExecuteS('SELECT module FROM '._DB_PREFIX_.'orders WHERE id_order='.pSQL($this->param['oid']));
	            	if (empty($q)) throw new Exception('Invalid order');            	
	            	$czy_za_pobraniem = !(strpos($q[0]['module'], 'cashondelivery') === false);	// modul platnosci
	            	
	            	$r = $d24i->createShipments($pd['ODB_MIEJSCOWOSC'], $pd['ODB_EMAIL'], $pd['ODB_NAZWA'],
	            			cse($pd['ODB_TELEFON']), $pd['ODB_NUMER_DOMU'], $pd['ODB_NUMER_LOKALU'],
		            		$pd['ODB_NAZWA'], cse($pd['ODB_KOD_POCZTOWY']), $pd['ODB_ULICA'],
	            			$czy_za_pobraniem, '', 'Towar');	 
	            	 
	            	$shipment_id = $r->createShipmentsResult->item->shipmentId;
	
	            	Db::getInstance()->Execute('UPDATE '._DB_PREFIX_.'orders SET dhl_shipment_id=1, dhl_lp="'.$shipment_id.'" WHERE id_order='.pSQL($this->param['oid']));
	            	
	            	header('Location: index.php?tab=AdminOrders&token='.$_GET['token']);
            	}
            	
            	if ($this->param['section'] == 'print') {
	            	$q = Db::getInstance()->ExecuteS('SELECT dhl_lp FROM '._DB_PREFIX_.'orders WHERE id_order='.pSQL($this->param['oid']));
	            	if (empty($q)) throw new Exception('Invalid order');
	            	if ($q[0]['dhl_lp'] == NULL) throw new Exception('Not DHL-ed');

	            	$d24i = new DHL24_webapi_client();
	            	$labs = $d24i->getLabels($q[0]['dhl_lp']);
	            	
	            	header('Content-Type: application/pdf');
	            	header('Content-Disposition: attachment; filename="'.$labs->getLabelsResult->item->labelName.'"');
	            	echo base64_decode($labs->getLabelsResult->item->labelData);	            
            	}
        }
    }

    /*
     * 	Walidacja otrzymanych parametrów z e-sklepu lub serwisu DHL Zamów Kuriera.
     *
     *   function validate
     */

    protected function validate() {

        $result = true;

        if (is_null($this->param['scenario']) || empty($this->param['scenario'])) {
            $this->addError('Brak parametru <i>scenario</i>');
            throw new Exception($this->getErrors());
        }

        switch ($this->param['scenario']) {
            case 'shipment':

                if (is_null($this->param['section']) || empty($this->param['section'])) {
                    $this->addError('Brak parametru <i>section</i>.');
                    $result = false;
                } else {

                    switch ($this->param['section']) {
                        case 'shipping': $param = 'oid';
                            break;
                        case 'details': $param = 'pid';
                            break;
                        case 'print': $param = 'oid';
                            break;
                    }

                    if (is_null($this->param[$param]) || empty($this->param[$param])) {
                        $this->addError('Brak parametru <i>' . $param . '</i>.');
                        $result = false;
                    }

                    if (!is_null($this->param['pid'])) {
                        if (!is_numeric($this->param['pid'])) {
                            $this->addError('Wartość parametr <i>pid</i> musi być liczbą.');
                            $result = false;
                        }
                    }
                }

                if ($this->authorization) {
                    if (empty($this->login)) {
                        $this->addError('Brak <i>loginu</i>.');
                        $result = false;
                    }

                    if (empty($this->password)) {
                        $this->addError('Brak <i>hasła</i>.');
                        $this->addError('Dane logowania należy skonfigurować w zakładce <i>Moduły -> Wysyłka i Logistyka -> DHL24 Zamów Kuriera -> Konfiguruj</i>');
                        $result = false;
                    }
//	Ogarnijcie ten login
//                    if (!preg_match('/^[a-zA-Z0-9.\-_]+@[a-zA-Z0-9\-.]+\.[a-zA-Z]{2,4}$/', $this->login) && !empty($this->login)) {
//                        $this->addError('Zły format <i>loginu</i>. <b>(Prawidłowy format: adres e-mail)</b>.');
//                       $result = false;
//                   }
                }
                break;
            case 'update':

                $params = array('pid', 'oid');

                foreach ($params as $param) {
                    if (empty($this->param[$param])) {
                        $this->addError('Brak parametru <i>' . $param . '</i>.');
                        $result = false;
                    }
                }

                if (!empty($this->param['lp'])) {
                    if (!is_numeric($this->param['lp'])) {
                        $this->addError('Błędny format numeru listu przewozowego - musi składać się z samych cyfr.');
                        $result = false;
                    }

                    if (strlen($this->param['lp']) != 11) {
                        $this->addError('Numer listu przewozowego musi składać się z 11 cyfr.');
                        $result = false;
                    }
                }

                if (!empty($this->param['pid'])) {
                    if (!is_numeric($this->param['pid'])) {
                        $this->addError('Wartość parametr <i>pid</i> musi być liczbą.');
                        $result = false;
                    }
                }
                break;
        }

        if (!$result) {
            throw new Exception($this->getErrors());
        }
    }

    /**
     * Próba wyciągnięcia nru lokalu i nru domu z adresu
     * 
     * @author Henrietta
     * @param array $data Tablica z danym odbiorcy
     */
    
    protected function annotateOrderData($data) {
    	
    	$ulica = trim($data['ODB_ULICA']);
    	    	
    	if (substr($ulica, -1) == ',') $ulica = substr($ulica, 0, strlen($ulica)-1); // jesli na koncu jest "," to go utnij
    	 
    	$odb_numer_domu = null;
    	$odb_numer_lokalu = null;
    	$matches = array();

    	// regexp: Spacja - potem nr domu (z literkami) - potem slash - potem nr lokalu (z literkami) - potem spacja albo koniec
    	if (preg_match('/\s+[a-zA-Z0-9]+\/[a-zA-Z0-9]+(\s|$)/', $ulica, $matches)) {
    		list($odb_numer_domu, $odb_numer_lokalu) = explode('/', trim($matches[0]), 2);
    		$ulica = str_replace($matches[0], '', $ulica);
    		
    	// regexp: Spacja - potem nr domu (z literkami) - potem spacja albo koniec
    	} elseif (preg_match('/\s+[a-zA-Z0-9]+(\s|$)/', $ulica, $matches)) {
    		$odb_numer_domu = trim($matches[0]);
    		$ulica = str_replace($matches[0], '', $ulica);    		
    	}
    	
    	if ($odb_numer_domu != null) $data['ODB_NUMER_DOMU'] = (int)$odb_numer_domu;
    	if ($odb_numer_lokalu != null) $data['ODB_NUMER_LOKALU'] = (int)$odb_numer_lokalu;
    	
       	
    	$data['ODB_ULICA'] = $ulica;
    	
    	return $data;
    }
    
    /* 	Walidacja danych odbiorcy.
     *
     *   function validateOrderData
     *   @param array $data Tablica z danymi odbiorcy
     *   
     */

    protected function validateOrderData($data) {

        $result = true;

        $fields = array(
            'ORDER_ID' => array(),
            'ODB_NAZWA' => array('length' => 60),
            'ODB_KRAJ' => array('length' => 2),
            'ODB_MIEJSCOWOSC' => array('length' => 60),
            'ODB_KOD_POCZTOWY' => array('length' => 6, 'format' => '/^[0-9]{2}-[0-9]{3}$/'),
            'ODB_ULICA' => array('length' => 60),
            'ODB_TELEFON' => array('length' => 20, 'format' => '/^(\+)?[0-9\-\s]{1,}$/'),
        		// poprawka do ODB_TELEFON - dodano opcjonalny + ~henrietta
            'ODB_EMAIL' => array('length' => 60, 'format' => '/^[a-zA-Z0-9.\-_]+@[a-zA-Z0-9\-.]+\.[a-zA-Z]{2,4}$/'),        		        	
        );
        
        foreach ($fields as $fname => $field) {
        	$data[$name] = trim($data[$name]);
            if (is_null($data[$fname]) && ($fname != 'ODB_TELEFON')) {	// zniesiono wymaganie telefonu ~henrietta
                $this->addError('Brak pola <i>' . $fname . '</i> wśród danych odbiorcy.');
                $result = false;
            } else {
                foreach ($field as $validator => $value) {
                    switch ($validator) {
                        case 'length':
                            if (strlen((string) $data[$fname]) > $value) {
                                $this->addError('Zbyt długa wartość pola <i>' . $fname . '</i> - <b>max. długość ' . $value . ' znaków</b>');
                                $result = false;
                            }
                            break;
                        case 'format':
                        	if (($fname == 'ODB_TELEFON') && (strlen($data[$fname]) == 0)) break;  // zniesiono wymaganie telefonu ~henrietta                       	
                            if (!preg_match($value, $data[$fname])) {
                                $this->addError('Błędny format pola <i>' . $fname . '</i>');
                                $result = false;
                            }
                            break;
                    }
                }
            }
        }
        
        if (!$result) {
            throw new Exception($this->getErrors());
        }
    }

    /*
     * 	Funkcja generuje adres głównego katalogu e-sklepu zawierającego skrypty DHL.
     *
     *   function getHomreUrl
     *   @return string
     */

    protected function getHomeUrl() {

        $url_array = explode('/', $_SERVER['PHP_SELF']);
        array_pop($url_array);

        return 'http://' . $_SERVER['HTTP_HOST'] . implode('/', $url_array);
    }

    /*
     *   Logowanie uzytkownika oraz przekazywanie danych do serwisu DHL Zamów Kuriera.
     *
     *   function login
     *   @param array $user user authorization data
     *   @return string logged user session id
     */

    private function login() {
        $url = DHL_PROTOCOL . DHL_DOMAIN . '/uzytkownik/loginEstore';

        $P[] = "username=" . $this->login;
        $P[] = "password=" . ($this->encode_md5 ? md5($this->password) : $this->password);

        if (!is_null($this->param['oid'])) {
            $order = $this->getOrderData($this->param['oid'],$this->param['token']);
            var_dump($order);
//            die();
            foreach ($order as $k => $v)
                $P[] = "estore_data[" . $k . "]=" . $v;
        }


        $user_agent = "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0; DHL OLB Interface)";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        if (count($P)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, join("&", $P));
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    /*
     * 	Funkcja przetwarza parametry otrzymane w tablicy $params w ciąg znaków.
     *
     *   function renderParamList
     *   @param array $params
     *   @return string
     */

    private function renderParamList($params) {
        $paramList = array();
		
        foreach ($params as $name => $value) {
            $paramList[] = $name . '=' . $value;
        }
		
        return '?'.implode('&',$paramList);
    }

    /*
     * 	Funkcja zwraca listę parametrów w postaci ciągu znaków.
     *
     *   function getUrlParams
     *   @return string
     */

    private function getUrlParams() {

        switch ($this->param['section']) {
            case 'details':
                $params = array('param' => 'id', 'pid' => $this->param['pid'], 'url' => 'przesylka/szczegoly');
                break;
            case 'print':
                $params = array('param' => 'id', 'pid' => $this->param['pid'], 'url' => 'przesylka/drukuj');
                break;
            case 'shipping':
                $params = array('param' => 'null', 'pid' => 'null', 'url' => 'przesylka/utworz');
                break;

            default: $params = array();
        }

        $params = array_merge(array('section' => $this->param['section'],'sid' => $this->sid,'version' => '1.1.0'), $params);
		
        return $this->renderParamList($params);
    }

    /*
     * 	Funkcja zwraca informacje o wszystkich błędach, które wystąpiły podczas walidacji.
     *
     *   function getErrors
     *   @return string
     */

    private function getErrors() {

        $errors = '';

        if (!empty($this->errors)) {
            $errors = ($this->param['scenario'] == 'update' ? '<b>Wystąpiły błędy podczas zapisu danych w e-sklepie:</b>' : '<b>Wystąpiły błędy w aplikacji:</b>');
            $errors .= '<ul>';
            $errors .= implode('', $this->errors);
            $errors .= '</ul>';
        }

        return $errors;
    }

    /*
     * 	Dodawanie informacji o błędzie do tablicy $errors.
     *
     *   function addError
     *   @param string $err_message
     */

    private function addError($err_message) {
        $this->errors[] = '<li>' . $err_message . '</li>';
    }

}

?>