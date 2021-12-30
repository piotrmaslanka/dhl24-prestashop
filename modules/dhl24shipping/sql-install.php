<?php

	// Init
	$sql = array();
    $date_add = $date_upd = date('y-m-d H:i:s');

	// Add columns to order table
	$sql[] = 'ALTER TABLE `'._DB_PREFIX_.'orders` ADD COLUMN dhl_lp varchar(255) default NULL;';
	$sql[] = 'ALTER TABLE `'._DB_PREFIX_.'orders` ADD COLUMN dhl_shipment_id int(11) default NULL;';
    $sql[] = "INSERT INTO "._DB_PREFIX_."configuration(name,date_add,date_upd) VALUES('DHL24_LOGIN','$date_add','$date_upd');";
    $sql[] = "INSERT INTO "._DB_PREFIX_."configuration(name,date_add,date_upd) VALUES('DHL24_PASSWORD','$date_add','$date_upd');";

?>
