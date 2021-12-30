<?php

/*  3e Software & Interactive House Agency - http://www.3e.pl
 *
 *  Plik jest częścią pakietu Integracji e-sklepu z serwisem DHL Zamów Kuriera.
 *
 * 	Skrypt zawiera klasę DHLInterface oraz implementacje metod dziedziczonych po
 * 	klasie AbstractDHLInterface.
 *
 * 	Uwaga: Metody w tym pliku przedstawiają przykładową implementację, należy je
 *  zmodyfikować pod względem własnego e-sklep'u, połączenia bazy danych etc.
 *
 *  @autor Rafał Zieliński <rafal.zielinski@3e.pl> 
 *  @version 1.0
 *	2012-06-26
 *
 *  Publikowane na zasadach licencji GNU General Public License
 */

require_once( 'AbstractDHLInterface.php' );
include_once('..'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config.inc.php'); // Konfiguracja PRESTASHOP

// Etykiety
define('DHL_PRINT_PL', 'Drukuj list przewozowy');
define('DHL_DETAILS_PL', 'Zobacz szczegóły');
define('DHL_PRINT_EN', 'Print consigament note');
define('DHL_DETAILS_EN', 'Details');

// Ścieżki do plików
define('DHL_IMG_PATH', '../img/admin/DHL24');
define('DHL_CSS_PATH', '../css/DHL24/dhl24_style.css');

class DHLInterface extends AbstractDHLInterface {

    protected $login = '';
    protected $password = '';

    /*
     *   Konfiguracja:
     *
     *   $authorization - określa czy jest wymagana autoryzacja.
     *   $encode_md5 - określa czy hasło ma być zakodowane funkcją md5.
     */
    private $db;

    public function __construct() {
        $this->Init();
        $this->db = $this->DBConnect();

        $this->login = $this->getPSLogin();
        $this->password = $this->getPSPassword();
    }

    /*
     *   Połączenie z bazą danych.
     *
     *   function DBConnect
     */

    protected function DBConnect() {
        return Db::getInstance(_PS_USE_SQL_SLAVE_);
    }

    /*
     *   Zapis danych utworzonego listu przewozowego w serwisie DHL.
     *
     *   function saveOrderData
     * 	@param $lp Numer listu przewozowego
     * 	@param $p_id Identyfikator przesyłki w serwisie DHL Zamów Kuriera
     * 	@param $o_id Identyfikator zamówienia w sklepie
     */

    protected function saveOrderData($lp, $p_id, $o_id) {

        $fields = array("dhl_shipment_id = $p_id");
        if(!empty($lp)) $fields[] = "dhl_lp = $lp";

        $this->db->Execute("UPDATE "._DB_PREFIX_."orders SET ".implode(',',$fields)." WHERE id_order = $o_id");
    }

    /*
     *   Pobieranie danych odbiorcy zamówienia.
     *
     *   function getOrderData
     * 	@param $o_id Identyfikator zamówienia w sklepie
     * 	@return array
     */

    protected function getOrderData($o_id,$token) {

    	// dano pelne pierwsze imie zamiast skrotowca ~henrietta
        $order = $this->db->GetRow($sql = "select
			o.`id_order` as ORDER_ID,
			CONCAT(a.`company`, ' ', a.`firstname`, ' ', a.`lastname`) as ODB_NAZWA,
			ct.`iso_code` as ODB_KRAJ,
			a.`city` as ODB_MIEJSCOWOSC,
			a.`postcode` as ODB_KOD_POCZTOWY,
			case when a.`address1` is null or a.`address1` = ''
                   then a.`address2`
                   else CONCAT(a.`address1`, ', ', a.`address2`)
            end as ODB_ULICA,
			case when a.`phone_mobile` is null or a.`phone_mobile` = ''
                   then a.`phone`
                   else a.`phone_mobile`
            end as ODB_TELEFON,
			cu.`email` as ODB_EMAIL
			
			from `"._DB_PREFIX_."orders` o
			LEFT JOIN `"._DB_PREFIX_."customer` cu ON (o.`id_customer` = cu.`id_customer`)
			LEFT JOIN `"._DB_PREFIX_."address` a ON (o.`id_address_delivery` = a.`id_address` and a.`id_customer` = o.`id_customer`)
			LEFT JOIN `"._DB_PREFIX_."country` ct ON (a.`id_country` = ct.`id_country`)
			where o.id_order=$o_id");

        $order['ODB_KOD_POCZTOWY'] = $this->postCodeFormat($order['ODB_KOD_POCZTOWY']);
//        $order['STORE_BACK_URL'] = urlencode($this->getHomeUrl() . '/index.php?tab=AdminOrders&amp;token='.$token);
//        $order['SAVE_URL'] = $this->getHomeUrl() . '/DHLController.php';
        $order['ODB_NAZWA'] = trim( $order['ODB_NAZWA'] );
        $order['ODB_NAZWA'] = substr( $order['ODB_NAZWA'], 0, 60 );

        $this->validateOrderData($order);
        
        $order = $this->annotateOrderData($order);		// ~henrietta

        return $order;
    }

    private function postCodeFormat($postCode){

        if(strlen($postCode) == 5 && is_numeric($postCode)){

            $part1 = substr($postCode,0,2);
            $part2 = substr($postCode,2,5);

            $postCode = $part1.'-'.$part2;
        }

        return $postCode;
    }

    /*
     *   Funkcja wstawiajaca referencję do pliku css
     *
     *   function includeCSSFile
     */

    static public function includeCSSFile() {
        echo '<link rel="stylesheet" type="text/css" href="' . DHL_CSS_PATH . '"/>';
    }

    /*
     *   Generowanie opcji / przycisku DHL.
     *
     *   function renderListView
     *   @param string $lp Numer listu przewozowego
     *   @param int $s_id Identyfikator utworzonego listu przewozowego (wartość pola 'dhl_shipment_id' w bazie danych')
     *   @param int $o_id Identyfikator zamówienia w sklepie
     *   @param string $lang Identyfikator języka
     *   @return string
     */

    static public function renderDHLView($lp, $s_id, $o_id, $lang = 'PL') {
        // Opcje DHL24
    }

    /*
    *   Generowanie opcji / przycisku DHL.
    *
    *   function renderListViewWithToken
    *   @param string $lp Numer listu przewozowego
    *   @param int $s_id Identyfikator utworzonego listu przewozowego (wartość pola 'dhl_shipment_id' w bazie danych')
    *   @param int $o_id Identyfikator zamówienia w sklepie
    *   @param string $token Identyfikator sesji wyświetlania panelu
    *   @param string $lang Identyfikator języka
    */
    static public function renderDHLViewWithToken($lp, $s_id, $o_id, $token, $lang = 'PL') {

        $labels = get_defined_constants();
        $view = '';

        if ($s_id != NULL) {

            $view .= '
              <div class="dhl_interface_listbox dhl_button">';
//                  <a href="DHLController.php?scenario=shipment&section=details&pid=' . $s_id . '&token=' .$token. '" class="dhl_interface_liststyle" target="_blank">&raquo; ' .$labels['DHL_DETAILS_'.$lang]. '</a><br/>';

            if($lp != NULL){
            $view .= '
                  <a href="DHLController.php?scenario=shipment&section=print&oid=' . $o_id . '&token=' .$token. '" class="dhl_interface_liststyle" target="_blank">&raquo; ' .$labels['DHL_PRINT_'.$lang]. '</a>';
            }
            else{
                $view .= '<label class="dhl_label">&raquo; Brak listu przewozowego</label>';
            }

            $view .= '</div>';

        } else {

            $view .= '
              <div class="dhl_button">
                  <a href="DHLController.php?scenario=shipment&section=shipping&oid=' . $o_id. '&token=' .$token. '" target="_blank"><img class="dhl_interface_button" src="' .DHL_IMG_PATH.'/DHL_button_'.$lang. '.png" /></a>
              </div>';

        }

        echo $view;

    }

    private function getPSLogin(){
        $data = $this->db->getRow("SELECT value FROM "._DB_PREFIX_."configuration WHERE name = 'DHL24_LOGIN'");
        return $data['value'];
    }

    private function getPSPassword(){
        $data = $this->db->getRow("SELECT value FROM "._DB_PREFIX_."configuration WHERE name = 'DHL24_PASSWORD'");
        return $data['value'];
    }

}

?>