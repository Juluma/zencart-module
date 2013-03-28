<?php
/*
SVEAWEBPAY PAYMENT MODULE FOR ZenCart
-----------------------------------------------
Version 3.0
*/
define('MODULE_PAYMENT_SWPINTERNETBANK_TEXT_TITLE','SVEA Direct Bank');
define('MODULE_PAYMENT_SWPINTERNETBANK_TEXT_DESCRIPTION','SveaWebPay Direct Bank Hosted - ver 3.0');
define('MODULE_PAYMENT_SWPINTERNETBANK_HANDLING_APPLIES','Et admininistrationsgebyr p� %s vil blive f�jet til ordren ved kassen.');
define('ERROR_ALLOWED_CURRENCIES_NOT_DEFINED','En eller flere af de tilladte valutaer er ikke pr�ciserede. Dette skal ske, f�rend SweaWebPay Hosted Solution kan benyttes. Login p� dit admin panel og tjek, at alle valutaer er listede som tilladte i betalingsmodulet, og at de rigtige vekselkurser er indstillede korrekt.');
define('ERROR_DEFAULT_CURRENCY_NOT_ALLOWED','Standardvalutaen er ikke blandt de listede som tilladte. Login i dit admin panel og tjek, at standardvalutaen er p� listen over tilladte valutaer i betalingsmodulet.');  
define('ERROR_MESSAGE_PAYMENT_FAILED','Betaling mislykkedes.');  

define('ERROR_CODE_100','Indre systemfejl s�som at databaser er nede, ressourcer er ikke tilg�ngelige etc. kontakt integrator');
define('ERROR_CODE_105','Ugyldig transaktionsstatus');
define('ERROR_CODE_106','Fejl ved tredjepart f.eks. ved banken');
define('ERROR_CODE_107','Transaktion afvist af bank');
define('ERROR_CODE_108','Transaktion afbrudt');
define('ERROR_CODE_109','Transaktion ikke fundet ved banken');
define('ERROR_CODE_110','Ugyldigt transaktions-id');
define('ERROR_CODE_113','Betalingsmetode ikke konfigureret til forhandleren');
define('ERROR_CODE_114','Timeout hos banken');
define('ERROR_CODE_121','Kortet er udl�bet');
define('ERROR_CODE_124','Bel�bet overstiger kreditlimit');
define('ERROR_CODE_143','Kredit afsl�et af banken');

define('ERROR_CODE_DEFAULT', 'Fejl i behandling af betalingen. Oplys venligst kode ved henvendelse kundeservice. Fejlkode: ');
?>