<?php

// Init
$sql = array();
$sql[] = 'ALTER TABLE `'._DB_PREFIX_.'orders` DROP COLUMN dhl_lp;';
$sql[] = 'ALTER TABLE `'._DB_PREFIX_.'orders` DROP COLUMN dhl_shipment_id;';
$sql[] = "DELETE FROM `"._DB_PREFIX_."configuration` WHERE name='DHL24_LOGIN';";
$sql[] = "DELETE FROM `"._DB_PREFIX_."configuration` WHERE name='DHL24_PASSWORD';";

?>
