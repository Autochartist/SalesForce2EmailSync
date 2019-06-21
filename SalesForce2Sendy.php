<?php

define('__ROOT__', dirname(__FILE__));
require_once(__ROOT__.'/SendyPHP.php');


$lists = array( 
    "892ihGh1ynxfV0SXPIR5R7Dg" => "Company News", 
    "guw1GdZ38l13mv5qx3hQ892g" => "Monthly value-added", 
    "ficka0htKNqsSn892U10Fh0g" => "Webinars & Education", 
    "XoIgXQqKGKyjYFhb2EUWyQ" => "Product Updates"
);

$APIKEY = 'eYl2oczos9u9vvdW2s5T';
$URL = 'https://sendy.autochartist.com/sendy/';
$DEFAULTLISTID = '892ihGh1ynxfV0SXPIR5R7Dg';

$config = array(
    'api_key' => $APIKEY,
    'installation_url' => $URL,
    'list_id' => $DEFAULTLISTID
);

$raw = file_get_contents('contacts.json');
$contacts = json_decode($raw, true);


$sendy = new \SendyPHP\SendyPHP($config);
$res = $sendy->updateSalesForceContacts($contacts, $lists);

?>
