<?php

/*  3e Software & Interactive House Agency - http://www.3e.pl
 *
 *  Plik jest częścią pakietu Integracji e-sklepu z serwisem DHL Zamów Kuriera.
 *  Skrypt jest odpowiedzialny za wywołanie głównej metody processRequest()
 * 	obsługującej żądania oraz komunikującją e-sklep'u z serwisem DHL Zamów kuriera.
 *
 *  @autor Rafał Zieliński <rafal.zielinski@3e.pl> 
 *  @version 1.0
 *  2011-07-12
 *
 *  Publikowane na zasadach licencji GNU General Public License
 */

require( 'DHLInterface.php' );

try {
    $interafce = new DHLInterface();
    $interafce->processRequest();
} catch (Exception $e) {
    echo $e->getMessage();
}
?>