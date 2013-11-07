<?php
/*
SVEA PAYMENT MODULE FOR ZenCart
-----------------------------------------------
Version 4.0
*/
define('MODULE_PAYMENT_SWPINVOICE_TEXT_TITLE','Svea Rechnung');
define('MODULE_PAYMENT_SWPINVOICE_TEXT_DESCRIPTION','Svea Rechnung - version 4.0');
define('MODULE_PAYMENT_SWPINVOICE_HANDLING_APPLIES','%s Bearbeitungsgeb�hr hinzukommen');

define('ERROR_ALLOWED_CURRENCIES_NOT_DEFINED','One or more of the allowed currencies are not defined. This must be enabled in order to use the SweaWebPay Hosted Solution. Log in to your admin panel, and ensure that all currencies listed as allowed in the payment module exists, and that the correct exchange rates are set.');
define('ERROR_DEFAULT_CURRENCY_NOT_ALLOWED','The default currency is not among those listed as allowed. Log in to your admin panel, and ensure that the default currency is in the allowed list in the payment module.');  
define('ERROR_MESSAGE_PAYMENT_FAILED','Payment Failed.');  

//Eu error codes
efine('ERROR_CODE_20000','Auftrag geschlossen');
define('ERROR_CODE_20001','Auftrag abgelehnt');
define('ERROR_CODE_20002','Fehler im Auftrag');
define('ERROR_CODE_20003','Auftrag abgelaufen');
define('ERROR_CODE_20004','Auftrag existiert nicht');
define('ERROR_CODE_20005','Auftragart diskrepanz');
define('ERROR_CODE_20006','Die Summe geltene alle Auftragzeilen k�nnen nicht null oder negativ sein');
define('ERROR_CODE_20013','Auftrag ist anh�ngig');

define('ERROR_CODE_30000','Die Bonit�tspr�fung ist abgelehnt');
define('ERROR_CODE_30001','Die Kunde ist blockiert oder zeigt ungew�hnliche Verhalten');
define('ERROR_CODE_30002','Basiert der Bonit�tspr�fung ist der Verlangen abgelehnt');
define('ERROR_CODE_30003','Die Kunde k�nnte nicht durch Bonit�tspr�fung gefunden werden');

define('ERROR_CODE_40000','Die Kunde ist nicht gefunden');
define('ERROR_CODE_40001','Die bereitgestellte Landskode ist nicht unterst�tz');
define('ERROR_CODE_40002','Ung�ltige Kundeninformationen');
define('ERROR_CODE_40004','Keiner Adresse geltene dieser Kunde gefunden');

define('ERROR_CODE_50000','Die Kunde ist nicht f�r dieser Verfahren zugelassen');

//invoice specific
define('ERROR_CODE_24000','Die Rechnungssumme �berschreitet die genehmigte Summe');

// used in payment credentials form
define('FORM_TEXT_INVOICE_ADDRESS','Rechnungsadresse');
define('FORM_TEXT_INVOICE_FEE','Bearbeitungsgeb�hr:');
define('FORM_TEXT_COMPANY','Gesch�ft');
define('FORM_TEXT_PRIVATE','Privat');
define('FORM_TEXT_GET_ADDRESS','Adresse bekommen');

define('FORM_TEXT_SS_NO','Sozialversicherungsnummer:');
define('FORM_TEXT_INITIALS','Initialen');                                
define('FORM_TEXT_BIRTHDATE','Geburtsdatum (YYYYMMDD)');              
define('FORM_TEXT_VATNO','MwSt');

define('ERROR_CODE_DEFAULT','Svea Fehler: ');

?>