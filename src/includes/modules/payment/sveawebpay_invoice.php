<?php

/*
  HOSTED SVEAWEBPAY PAYMENT MODULE FOR ZEN CART
  -----------------------------------------------
  Version 4.0 - Zen Cart

  Kristian Grossman-Madsen, Shaho Ghobadi
 */

// Include Svea php integration package files
require_once(DIR_FS_CATALOG . 'svea/Includes.php');  // use new php integration package for v4
require_once(DIR_FS_CATALOG . 'sveawebpay_config.php');                  // sveaConfig inplementation

class sveawebpay_invoice {

    function sveawebpay_invoice() {
        global $order;

        $this->code = 'sveawebpay_invoice';
        $this->version = 4;

        $_SESSION['SWP_CODE'] = $this->code;

        $this->title = MODULE_PAYMENT_SWPINVOICE_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_SWPINVOICE_TEXT_DESCRIPTION;
        $this->enabled = ((MODULE_PAYMENT_SWPINVOICE_STATUS == 'True') ? true : false);
        $this->sort_order = MODULE_PAYMENT_SWPINVOICE_SORT_ORDER;
        $this->sveawebpay_url = MODULE_PAYMENT_SWPINVOICE_URL;
        $this->handling_fee = MODULE_PAYMENT_SWPINVOICE_HANDLING_FEE;
        $this->default_currency = MODULE_PAYMENT_SWPINVOICE_DEFAULT_CURRENCY;
        $this->allowed_currencies = explode(',', MODULE_PAYMENT_SWPINVOICE_ALLOWED_CURRENCIES);
        $this->display_images = ((MODULE_PAYMENT_SWPINVOICE_IMAGES == 'True') ? true : false);
        $this->ignore_list = explode(',', MODULE_PAYMENT_SWPINVOICE_IGNORE);
        if ((int)MODULE_PAYMENT_SWPINVOICE_ORDER_STATUS_ID > 0)
            $this->order_status = MODULE_PAYMENT_SWPINVOICE_ORDER_STATUS_ID;
        if (is_object($order))
            $this->update_status();
    }

    function update_status() {
        global $db, $order, $currencies, $messageStack;

        // update internal currency
        $this->default_currency = MODULE_PAYMENT_SWPINVOICE_DEFAULT_CURRENCY;
        $this->allowed_currencies = explode(',', MODULE_PAYMENT_SWPINVOICE_ALLOWED_CURRENCIES);

        // do not use this module if any of the allowed currencies are not set in osCommerce
        foreach ($this->allowed_currencies as $currency) {
            if (!is_array($currencies->currencies[strtoupper($currency)])) {
                $this->enabled = false;
                $messageStack->add('header', ERROR_ALLOWED_CURRENCIES_NOT_DEFINED, 'error');
            }
        }

        // do not use this module if the default currency is not among the allowed
        if (!in_array($this->default_currency, $this->allowed_currencies)) {
            $this->enabled = false;
            $messageStack->add('header', ERROR_DEFAULT_CURRENCY_NOT_ALLOWED, 'error');
        }

        // do not use this module if the geograhical zone is set and we are not in it
        if (($this->enabled == true) && ((int) MODULE_PAYMENT_SWPINVOICE_ZONE > 0)) {
            $check_flag = false;
            $check_query = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_SWPINVOICE_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");

            while (!$check_query->EOF) {
                if ($check_query->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check_query->fields['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check_query->MoveNext();
            }

            if ($check_flag == false)
                $this->enabled = false;
        }
    }

    function javascript_validation() {
        return false;
    }

    /**
     * Method called when building the index.php?main_page=checkout_payment page.
     * Builds the input fields that pick up ssn, vatno et al used by the various Svea Payment Methods.
     *
     * @return array containing module id, name & input field array
     *
     */
    function selection() {
        global $order, $currencies;

        $fields = array();

        // add svea invoice image file
             $fields[] = array('title' => '<img src=images/Svea/SVEAINVOICEEU_'.$order->customer['country']['iso_code_2'].'.png />', 'field' => '');

        // catch and display error messages raised when i.e. payment request from before_process() below turns out not accepted
        if (isset($_REQUEST['payment_error']) && $_REQUEST['payment_error'] == 'sveawebpay_invoice') {
            $fields[] = array('title' => '<span style="color:red">' . $_SESSION['SWP_ERROR'] . '</span>', 'field' => '');
        }

        // insert svea js
        $sveaJs =   '<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>' .
                    '<script type="text/javascript" src="' . $this->web_root . 'includes/modules/payment/svea.js"></script>';
        $fields[] = array('title' => '', 'field' => $sveaJs);

        //
        // get required fields depending on customer country and payment method

        // customer country is taken from customer settings
        $customer_country = $order->customer['country']['iso_code_2'];

        // fill in all fields as required by customer country and payment method
        $sveaAddressDD = $sveaInitialsDiv = $sveaBirthDateDiv  = '';

        // get ssn & selects private/company for SE, NO, DK, FI
        if( ($customer_country == 'SE') ||     // e.g. == 'SE'
            ($customer_country == 'NO') ||
            ($customer_country == 'DK') )
        {
            // input text field for individual/company SSN
            $sveaSSN =          FORM_TEXT_SS_NO . '<br /><input type="text" name="sveaSSN" id="sveaSSN" maxlength="11" /><br />';
        }

        if( ($customer_country == 'FI') )
        {
           // input text field for individual/company SSN, without getAddresses hook
            $sveaSSNFI =        FORM_TEXT_SS_NO . '<br /><input type="text" name="sveaSSNFI" id="sveaSSNFI" maxlength="11" /><br />';
        }

        // radiobutton for choosing individual or organization
        $sveaIsCompanyField =
                            '<label><input type="radio" name="sveaIsCompany" value="false" checked>' . FORM_TEXT_PRIVATE . '</label>' .
                            '<label><input type="radio" name="sveaIsCompany" value="true">' . FORM_TEXT_COMPANY . '</label><br />';


        //
        // these are the countries we support getAddress in (getAddress also depends on sveaSSN being present)
        if( ($customer_country == 'SE') ||
            ($customer_country == 'NO') ||
            ($customer_country == 'DK') )
        {
            $sveaAddressDD =    '<br /><label for ="sveaAddressSelector" style="display:none">' . FORM_TEXT_INVOICE_ADDRESS . '</label><br />' .
                                '<select name="sveaAddressSelector" id="sveaAddressSelector" style="display:none"></select><br />';
        }

        //
        // if customer is located in Netherlands, get initials
        if( $customer_country == 'NL') {

            $sveaInitialsDiv =  '<div id="sveaInitials_div" >' .
                                    '<label for="sveaInitials">' . FORM_TEXT_INITIALS . '</label><br />' .
                                    '<input type="text" name="sveaInitials" id="sveaInitials" maxlength="5" />' .
                                '</div><br />';
        }

        //
        // if customer is located in Netherlands or DE, get birth date
        if( ($customer_country == 'NL') ||
            ($customer_country == 'DE') )
        {
            //Days, to 31
            $days = "";
            for($d = 1; $d <= 31; $d++){

                $val = $d;
                if($d < 10)
                    $val = "$d";

                $days .= "<option value='$val'>$d</option>";
            }
            $birthDay = "<select name='sveaBirthDay' id='sveaBirthDay'>$days</select>";

            //Months to 12
            $months = "";
            for($m = 1; $m <= 12; $m++){
                $val = $m;
                if($m < 10)
                    $val = "$m";

                $months .= "<option value='$val'>$m</option>";
            }
            $birthMonth = "<select name='sveaBirthMonth' id='sveaBirthMonth'>$months</select>";

            //Years from 1913 to 1996
            $years = '';
            for($y = 1913; $y <= 1996; $y++){
                $years .= "<option value='$y'>$y</option>";
            }
            $birthYear = "<select name='sveaBirthYear' id='sveaBirthYear'>$years</select>";

            $sveaBirthDateDiv = '<div id="sveaBirthDate_div" >' .
                                    //'<label for="sveaBirthDate">' . FORM_TEXT_BIRTHDATE . '</label><br />' .
                                    //'<input type="text" name="sveaBirthDate" id="sveaBirthDate" maxlength="8" />' .
                                    '<label for="sveaBirthYear">' . FORM_TEXT_BIRTHDATE . '</label><br />' .
                                    $birthYear . $birthMonth . $birthDay .  // TODO better default selected, date order conforms w/DE,NL standard?
                                '</div><br />';

            $sveaVatNoDiv = '<div id="sveaVatNo_div" hidden="true">' .
                                    '<label for="sveaVatNo" >' . FORM_TEXT_VATNO . '</label><br />' .
                                    '<input type="text" name="sveaVatNo" id="sveaVatNo" maxlength="14" />' .
                                '</div><br />';
        }

        //
        // add information about handling fee for invoice payment method
        if (isset($this->handling_fee) && $this->handling_fee > 0) {
            $paymentfee_cost = $this->handling_fee;

            // is the handling fee a percentage?
            if (substr($paymentfee_cost, -1) == '%')
                $fields[] = array(  'title' => sprintf(MODULE_PAYMENT_SWPINVOICE_HANDLING_APPLIES, $paymentfee_cost),
                                    'field' => $paymentfee_cost);

            // no, handling fee is a fixed amount
            else {
                if (DISPLAY_PRICE_WITH_TAX == "true" && $tax_class > 0) {
                    $tax_class = MODULE_ORDER_TOTAL_SWPHANDLING_TAX_CLASS;

                    // calculate tax based on deliver country? TODO use zen cart tax zone??
                    $paymentfee_tax =
                        $paymentfee_cost * zen_get_tax_rate($tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']) / 100;
                }
            }

            $sveaHandlingFee =
                '<br />' . sprintf( MODULE_PAYMENT_SWPINVOICE_HANDLING_APPLIES, $currencies->format($paymentfee_cost + $paymentfee_tax));
        }

        $sveaError = '<br /><span id="sveaSSN_error_invoice" style="color:red"></span>';
        if($order->billing['country']['iso_code_2'] == "SE" || $order->billing['country']['iso_code_2'] == "DK"){
             $sveaSubmitAddress = '<button id="sveaSubmitGetAddress" type="button">'.FORM_TEXT_GET_ADDRESS.'</button>';
        }

        // create and add the field to be shown by our js when we select SveaInvoice payment method
        $sveaField =    '<div id="sveaInvoiceField" style="display:none">' .
                            $sveaIsCompanyField .   //  SE, DK, NO
                            $sveaSSN .              //  SE, DK, NO
                            $sveaSSNFI .            //  FI, no getAddresses
                            $sveaSubmitAddress.
                            $sveaAddressDD .        //  SE, Dk, NO
                            $sveaInitialsDiv .      //  NL
                            $sveaBirthDateDiv .     //  NL, DE
                            $sveaVatNoDiv .         // NL, DE
                            $sveaHandlingFee .
                            // FI, NL, DE also uses customer address data from zencart
                        '</div>';

        $fields[] = array('title' => '', 'field' => '<br />' . $sveaField . $sveaError);

        $_SESSION["swp_order_info_pre_coupon"]  = serialize($order->info);  // store order info needed to reconstruct amount pre coupon later

        // return module fields to zencart
        return array(   'id' => $this->code,
                        'module' => $this->title,
                        'fields' => $fields );
    }

    function pre_confirmation_check() {
        return false;
    }

    function confirmation() {
        return false;
    }

    /** process_button() is called from tpl_checkout_confirmation.php in
     *  includes/templates/template_default/templates when we press the
     *  continue checkout button after having selected payment method and
     *  entered required payment method input.
     *
     *  Here we prepare to populate the order object by creating the
     *  WebPayItem::orderRow objects that make up the order.
     */
    function process_button() {

        global $db, $order, $order_totals, $language;

        //
        // handle postback of payment method info fields, if present
        $post_sveaSSN = isset($_POST['sveaSSN']) ? $_POST['sveaSSN'] : "swp_not_set" ;
        $post_sveaSSNFI = isset($_POST['sveaSSNFI']) ? $_POST['sveaSSNFI'] : "swp_not_set" ;
        $post_sveaIsCompany = isset($_POST['sveaIsCompany']) ? $_POST['sveaIsCompany'] : "swp_not_set" ;
        $post_sveaAddressSelector = isset($_POST['sveaAddressSelector']) ? $_POST['sveaAddressSelector'] : "swp_not_set";
        $post_sveaVatNo = isset($_POST['sveaVatNo']) ? $_POST['sveaVatNo'] : "swp_not_set";
        $post_sveaBirthDay = isset($_POST['sveaBirthDay']) ? $_POST['sveaBirthDay'] : "swp_not_set";
        $post_sveaBirthMonth = isset($_POST['sveaBirthMonth']) ? $_POST['sveaBirthMonth'] : "swp_not_set";
        $post_sveaBirthYear = isset($_POST['sveaBirthYear']) ? $_POST['sveaBirthYear'] : "swp_not_set";
        $post_sveaInitials = isset($_POST['sveaInitials']) ? $_POST['sveaInitials'] : "swp_not_set" ;

        // calculate the order number
        $new_order_rs = $db->Execute("select orders_id from " . TABLE_ORDERS . " order by orders_id desc limit 1");
        $new_order_field = $new_order_rs->fields;
        $client_order_number = ($new_order_field['orders_id'] + 1) . '-' . time();

        // localization parameters
        $user_country = $order->billing['country']['iso_code_2'];

        $user_language = $db->Execute("select code from " . TABLE_LANGUAGES . " where directory = '" . $language . "'");
        $user_language = $user_language->fields['code'];


        // switch to default currency if the customers currency is not supported
        $currency = $order->info['currency'];
        if (!in_array($currency, $this->allowed_currencies)) {
            $currency = $this->default_currency;
        }

        // Create and initialize order object, using either test or production configuration
        $sveaConfig = (MODULE_PAYMENT_SWPINVOICE_MODE === 'Test') ? new ZenCartSveaConfigTest() : new ZenCartSveaConfigProd();

        $swp_order = WebPay::createOrder( $sveaConfig )
            ->setCountryCode( $user_country )
            ->setCurrency($currency)                       //Required for card & direct payment and PayPage payment.
            ->setClientOrderNumber($client_order_number)   //Required for card & direct payment, PaymentMethod payment and PayPage payments
            ->setOrderDate(date('c'))                      //Required for synchronous payments
        ;

        //
        // for each item in cart, create WebPayItem::orderRow objects and add to order
        foreach ($order->products as $productId => $product) {

            $amount_ex_vat = floatval( $this->convert_to_currency(round($product['final_price'], 2), $currency) );

            $swp_order->addOrderRow(
                    WebPayItem::orderRow()
                            ->setQuantity($product['qty'])          //Required
                            ->setAmountExVat($amount_ex_vat)          //Optional, see info above
                            ->setVatPercent(intval($product['tax']))  //Optional, see info above
                            ->setDescription($product['name'])        //Optional
           );
        }

        //
        // handle order total modules
        // i.e shipping fee, handling fee items
        foreach ($order_totals as $ot_id => $order_total) {

            switch ($order_total['code']) {
                case in_array(  $order_total['code'],
                                $this->ignore_list):
                case 'ot_subtotal':
                case 'ot_total':
                case 'ot_tax':
                    // do nothing
                    break;

                //
                // if shipping fee, create WebPayItem::shippingFee object and add to order
                case 'ot_shipping':

                    // makes use of zencart $order-info[] shipping information to populate object
                    // shop shows prices including tax, take this into accord when calculating tax
                    if (DISPLAY_PRICE_WITH_TAX == 'false') {
                        $amountExVat = $order->info['shipping_cost'];
                        $amountIncVat = $order->info['shipping_cost'] + $order->info['shipping_tax'];
                    }
                    else {
                        $amountExVat = $order->info['shipping_cost'] - $order->info['shipping_tax'];
                        $amountIncVat = $order->info['shipping_cost'] ;
                    }

                    // add WebPayItem::shippingFee to swp_order object
                    $swp_order->addFee(
                            WebPayItem::shippingFee()
                                    ->setDescription($order->info['shipping_method'])
                                    ->setAmountExVat( $amountExVat )
                                    ->setAmountIncVat( $amountIncVat )
                    );
                    break;

                //
                // if handling fee applies, create WebPayItem::invoiceFee object and add to order
                case 'sveawebpay_handling_fee' :

                    // is the handling_fee module activated?
                    if (isset($this->handling_fee) && $this->handling_fee > 0) {

                        // handlingfee expressed as percentage?
                        if (substr($this->handling_fee, -1) == '%') {

                            // sum of products + shipping * handling_fee as percentage
                            $hf_percentage = floatval(substr($this->handling_fee, 0, -1));

                            $hf_price = ($order->info['subtotal'] + $order->info['shipping_cost']) * ($hf_percentage / 100.0);
                        }
                        // handlingfee expressed as absolute amount (incl. tax)
                        else {
                            $hf_price = $this->convert_to_currency(floatval($this->handling_fee), $currency);
                        }
                        $hf_taxrate =   zen_get_tax_rate(MODULE_ORDER_TOTAL_SWPHANDLING_TAX_CLASS,
                                        $order->delivery['country']['id'], $order->delivery['zone_id']);

                        // add WebPayItem::invoiceFee to swp_order object
                        $swp_order->addFee(
                                WebPayItem::invoiceFee()
                                        ->setName($order_total['title'])
                                        ->setDescription($order_total['text'])
                                        ->setAmountExVat($hf_price)
                                        ->setVatPercent($hf_taxrate)
                        );
                    }
                    break;

                case 'ot_coupon':
                   // zencart coupons are made out as either amount x.xx or a percentage y%.
                    // Both of these are calculated by zencart via the order total module ot_coupon.php and show up in the
                    // corresponding $order_totals[...]['value'] field.
                    //
                    // Depending on the module settings the value may differ, Svea assumes that the (zc 1.5.1) default settings
                    // are being used:
                    //
                    // admin/ot_coupon module setting -- include shipping: false, include tax: false, re-calculate tax: standard
                    //
                    // The value contains the total discount amount including tax iff configuration display prices with tax is set to true:
                    //
                    // admin/configuration setting -- display prices with tax: true => ot_coupon['value'] includes vat, if false, excludes vat
                    //
                    // Example:
                    // zc adds an ot_coupon with value of 20 for i.e. a 10% discount on an order of 100 +(25%) + 100 (+6%).
                    // This discount seems to be split in equal parts over the two order item vat rates:
                    // 90*1,25 + 90*1,06 = 112,5 + 95,4 = 207,90, to which the shipping fee of 4 (+25%) is added. The total is 212,90
                    // ot_coupon['value'] is 23,10 iff display prices incuding tax = true, else ot_coupon['value'] = 20
                    //
                    // We handle the coupons by adding a FixedDiscountRow for the amount, specified ex vat. The package
                    // handles the vat calculations.

                    // if display price with tax is not set, svea's package calculations match zencart's and we can use value right away
                    if (DISPLAY_PRICE_WITH_TAX == 'false') {
                        $swp_order->addDiscount(
                            WebPayItem::fixedDiscount()
                                ->setAmountExVat( $order_total['value'] ) // $amountExVat works iff display prices with tax = false in shop
                                ->setDescription( $order_total['title'] )
                        );
                    }
                    // we need to determine the order discount ex. vat if display prices with tax is set to true,
                    // the ot_coupon module calculate_deductions() method returns a value including tax. We try to
                    // reconstruct the amount using the stored order info and the order_totals entries
                    else {
                        $swp_order_info_pre_coupon = unserialize( $_SESSION["swp_order_info_pre_coupon"] );
                        $pre_coupon_subtotal_ex_tax = $swp_order_info_pre_coupon['subtotal'] - $swp_order_info_pre_coupon['tax'];

                        foreach( $order_totals as $key => $ot ) {
                            if( $ot['code'] === 'ot_subtotal' ) {
                                $order_totals_subtotal_ex_tax = $ot['value'];
                            }
                        }
                        foreach( $order_totals as $key => $ot ) {
                            if( $ot['code'] === 'ot_tax' ) {
                                $order_totals_subtotal_ex_tax -= $ot['value'];
                            }
                        }
                        foreach( $order_totals as $key => $ot ) {
                            if( $ot['code'] === 'ot_coupon' ) {
                                $order_totals_subtotal_ex_tax -= $ot['value'];
                            }
                        }

                        $value_from_subtotals = isset( $order_totals_subtotal_ex_tax ) ?
                                ($pre_coupon_subtotal_ex_tax - $order_totals_subtotal_ex_tax) : $order_total['value']; // 'value' fallback

                        // if display_price_with tax is set to true && the coupon was specified as a fixed amount
                        // zencart's math doesn't match svea's, so we force the discount to use the the shop's vat
                        $coupon = $db->Execute("select * from " . TABLE_COUPONS . " where coupon_id = '" . (int)$_SESSION['cc_id'] . "'");

                        // coupon_type is F for coupons specified with a fixed amount
                        if( $coupon->fields['coupon_type'] == 'F' ) {

                            // calculate the vatpercent from zencart's amount: discount vat/discount amount ex vat
                            $zencartDiscountVatPercent =
                                ($order_total['value'] - $coupon->fields['coupon_amount']) / $coupon->fields['coupon_amount'] *100;

                            // split $zencartDiscountVatPercent into allowed values
                            $taxRates = $this->getTaxRatesInOrder($swp_order);
                            $discountRows = $this->splitMeanToTwoTaxRates( $coupon->fields['coupon_amount'], $zencartDiscountVatPercent,
                                    $order_total['title'], $order_total['title'], $taxRates );

                            foreach($discountRows as $row) {
                                $swp_order = $swp_order->addDiscount( $row );
                            }

                        }
                        // if coupon specified as a percentage, or as a fixed amount and prices are ex vat.
                        else {
                            $swp_order->addDiscount(
                                WebPayItem::fixedDiscount()
                                    ->setAmountExVat( $value_from_subtotals )
                                    ->setDescription( $order_total['title'] )
                            );
                        }
                    }
                    break;

                // TODO default case not tested, lack of test case/data. ported from 3.0 zencart module
                // default case handles 'unknown' items from other plugins. Might cause problems.
                default:
                    $order_total_obj = $GLOBALS[$order_total['code']];
                    $tax_rate = zen_get_tax_rate($order_total_obj->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);

                    // if displayed WITH tax, REDUCE the value since it includes tax
                    if (DISPLAY_PRICE_WITH_TAX == 'true') {
                        $order_total['value'] = (strip_tags($order_total['value']) / ((100 + $tax_rate) / 100));
                    }

                    // write negative amounts as FixedDiscount with the given tax rate, write positive amounts as HandlingFee
                    if( $order_total['value'] < 0 ) {
                        $swp_order->addDiscount(
                            WebPayItem::fixedDiscount()
                                ->setAmountExVat( -1* $this->convert_to_currency(strip_tags($order_total['value']), $currency)) // given as positive amount
                                ->setVatPercent($tax_rate)  //Optional, see info above
                                ->setDescription($order_total['title'])        //Optional
                        );
                    }
                    else {
                        $swp_order->addFee(
                            WebPayItem::invoiceFee()
                                ->setAmountExVat($this->convert_to_currency(strip_tags($order_total['value']), $currency))
                                ->setVatPercent($tax_rate)  //Optional, see info above
                                ->setDescription($order_total['title'])        //Optional
                        );
                    }
                    break;
            }
        }

//        ->addCustomerDetails(
//            WebPayItem::companyCustomer()
//            ->setNationalIdNumber(2345234)      //Required in SE, NO, DK, FI
//            ->setVatNumber("NL2345234")         //Required in NL and DE
//            ->setCompanyName("TestCompagniet")  //Required in NL and DE
//            ->setStreetAddress("Gatan", 23)     //Required in NL and DE
//            ->setZipCode(9999)                  //Required in NL and DE
//            ->setLocality("Stan")               //Required in NL and DE
//            ->setEmail("test@svea.com")         //Optional but desirable
//            ->setIpAddress("123.123.123")       //Optional but desirable
//            ->setCoAddress("c/o Eriksson")      //Optional
//            ->setPhoneNumber(999999)            //Optional
//            ->setAddressSelector("7fd7768")     //Optional, string recieved from WebPay::getAddress() request
//        )
//

        //
        // Check if customer is company
        if( $post_sveaIsCompany === 'true'){

            // create company customer object
            $swp_customer = WebPayItem::companyCustomer();

            // set company name
            $swp_customer->setCompanyName( $order->billing['company'] );

            // set company SSN
            if( ($user_country == 'SE') ||
                ($user_country == 'NO') ||
                ($user_country == 'DK') )
            {
                $swp_customer->setNationalIdNumber( $post_sveaSSN );
            }
            if( ($user_country == 'FI') )
            {
                $swp_customer->setNationalIdNumber( $post_sveaSSNFI );
            }

            // set addressSelector from getAddresses
            if( ($user_country == 'SE') ||
                ($user_country == 'NO') ||
                ($user_country == 'DK') )
            {
                $swp_customer->setAddressSelector( $post_sveaAddressSelector );
            }

            // set vatNo
            if( ($user_country == 'NL') ||
                ($user_country == 'DE') )
            {
                $swp_customer->setVatNumber( $post_sveaVatNo );
            }

            //Split street address and house no
            $pattern ="/^(?:\s)*([0-9]*[A-ZÄÅÆÖØÜßäåæöøüa-z]*\s*[A-ZÄÅÆÖØÜßäåæöøüa-z]+)(?:\s*)([0-9]*\s*[A-ZÄÅÆÖØÜßäåæöøüa-z]*[^\s])?(?:\s)*$/";
            $myStreetAddress = Array();
            preg_match( $pattern, $order->billing['street_address'], $myStreetAddress  );
            if( !array_key_exists( 2, $myStreetAddress ) ) { $myStreetAddress[2] = ""; }  // TODO handle case Street w/o number in package?!

            // set common fields
            $swp_customer
                ->setStreetAddress( $myStreetAddress[1], $myStreetAddress[2] )
                ->setZipCode($order->billing['postcode'])
                ->setLocality($order->billing['city'])
                ->setEmail($order->customer['email_address'])
                ->setIpAddress($_SERVER['REMOTE_ADDR'])
                ->setCoAddress($order->billing['suburb'])                       // c/o address
                ->setPhoneNumber($order->customer['telephone']);

            // add customer to order
            $swp_order->addCustomerDetails($swp_customer);
        }

//        ->addCustomerDetails(
//            WebPayItem::individualCustomer()
//            ->setNationalIdNumber(194605092222) //Required for individual customers in SE, NO, DK, FI
//            ->setInitials("SB")                 //Required for individual customers in NL
//            ->setBirthDate(1923, 12, 20)        //Required for individual customers in NL and DE
//            ->setName("Tess", "Testson")        //Required for individual customers in NL and DE
//            ->setStreetAddress("Gatan", 23)     //Required in NL and DE
//            ->setZipCode(9999)                  //Required in NL and DE
//            ->setLocality("Stan")               //Required in NL and DE
//            ->setEmail("test@svea.com")         //Optional but desirable
//            ->setIpAddress("123.123.123")       //Optional but desirable
//            ->setCoAddress("c/o Eriksson")      //Optional
//            ->setPhoneNumber(999999)            //Optional
//        )

        //
        // customer is private individual
        else {

            // create individual customer object
            $swp_customer = WebPayItem::individualCustomer();

            // set individual customer name
            $swp_customer->setName( $order->billing['firstname'], $order->billing['lastname'] );

            // set individual customer SSN
            if( ($user_country == 'SE') ||
                ($user_country == 'NO') ||
                ($user_country == 'DK') )
            {
                $swp_customer->setNationalIdNumber( $post_sveaSSN );
            }
            if( ($user_country == 'FI') )
            {
                $swp_customer->setNationalIdNumber( $post_sveaSSNFI );
            }

            // set BirthDate if required
            if( ($user_country == 'NL') ||
                ($user_country == 'DE') )
            {
                $swp_customer->setBirthDate(intval($post_sveaBirthYear), intval($post_sveaBirthMonth), intval($post_sveaBirthDay));
            }

            // set initials if required
            if( ($user_country == 'NL') )
            {
                $swp_customer->setInitials($post_sveaInitials);  //TODO calculate from string
            }

            //Split street address and house no
            $pattern ="/^(?:\s)*([0-9]*[A-ZÄÅÆÖØÜßäåæöøüa-z]*\s*[A-ZÄÅÆÖØÜßäåæöøüa-z]+)(?:\s*)([0-9]*\s*[A-ZÄÅÆÖØÜßäåæöøüa-z]*[^\s])?(?:\s)*$/";
            $myStreetAddress = Array();
            preg_match( $pattern, $order->billing['street_address'], $myStreetAddress  );
            if( !array_key_exists( 2, $myStreetAddress ) ) { $myStreetAddress[2] = ""; }  // TODO move handling Street w/o number to package

            // set common fields
            $swp_customer
                ->setStreetAddress( $myStreetAddress[1], $myStreetAddress[2] )  // street, housenumber
                ->setZipCode($order->billing['postcode'])
                ->setLocality($order->billing['city'])
                ->setEmail($order->customer['email_address'])
                ->setIpAddress($_SERVER['REMOTE_ADDR'])
                ->setCoAddress($order->billing['suburb'])                       // c/o address
                ->setPhoneNumber($order->customer['telephone'])
            ;

            // add customer to order
            $swp_order->addCustomerDetails($swp_customer);
        }

        //
        // store our order object in session, to be retrieved in before_process()
        $_SESSION["swp_order"] = serialize($swp_order);

        //
        // we're done here
        return false;
    }

    /**
     * before_process is called from modules/checkout_process.
     * It instantiates and populates a WebPay::createOrder object
     * as well as sends the actual payment request
     */
    function before_process() {
        global $order;

        // retrieve order object set in process_button()
        $swp_order = unserialize($_SESSION["swp_order"]);

        // send payment request to svea, receive response
        $swp_response = $swp_order->useInvoicePayment()->doRequest();

        // payment request failed; handle this by redirecting w/result code as error message
        if ($swp_response->accepted === false) {

            $_SESSION['SWP_ERROR'] = $this->responseCodes($swp_response->resultcode,$swp_response->errormessage);

            $payment_error_return = 'payment_error=sveawebpay_invoice';
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, $payment_error_return)); // error handled in selection() above
        }

        //
        // payment request succeded, store response in session
        if ($swp_response->accepted == true) {

            if (isset($_SESSION['SWP_ERROR'])) {
                unset($_SESSION['SWP_ERROR']);
            }

            // set zencart billing address to invoice address from payment request response

            // is private individual?
            if( $swp_response->customerIdentity->customerType == "Individual") {
                $order->billing['firstname'] = $swp_response->customerIdentity->fullName; // workaround for zen_address_format not showing 'name' in order information view/
                $order->billing['lastname'] = "";
                $order->billing['company'] = "";
            }
            else {
                $order->billing['company'] = $swp_response->customerIdentity->fullName;
            }
            // TODO check default zencart CHARSET define (should equal used database collation, i.e. utf-8).
            // if not utf-8, must handle that when parsing swp_response (in utf-8) -- use utf8_decode(response-> ?)
            // also, check that php 5.3 and 5.4+ behaves the same in zen_output_string ( htmlspecialchars() defaults to utf-8 from 5.4)
            $order->billing['street_address'] =
                    $swp_response->customerIdentity->street . " " . $swp_response->customerIdentity->houseNumber;
            $order->billing['suburb'] =  $swp_response->customerIdentity->coAddress;
            $order->billing['city'] = $swp_response->customerIdentity->locality;
            $order->billing['postcode'] = $swp_response->customerIdentity->zipCode;
            $order->billing['state'] = '';  // "state" is not applicable in SWP countries

            $order->billing['country']['title'] =                                           // country name only needed for address
                    $this->getCountryName( $swp_response->customerIdentity->countryCode );

            // save the response object
            $_SESSION["swp_response"] = serialize($swp_response);
        }
    }

    //
    // if payment accepted, set addresses based on response, insert order into database
    function after_process() {
        global $insert_id, $order, $db;

        //
        // retrieve response object from before_process()
        $swp_response = unserialize($_SESSION["swp_response"]);

        $deliveryAccepted = 0;  // used to indicate customer notification status in zencart, see below
        // if autodeliver option set, deliver order
        if( MODULE_PAYMENT_SWPINVOICE_AUTODELIVER == "True" ) {

            $sveaConfig = (MODULE_PAYMENT_SWPINVOICE_MODE === 'Test') ? new ZenCartSveaConfigTest() : new ZenCartSveaConfigProd();

            $swp_deliverOrder = WebPay::deliverOrder( $sveaConfig );

            // this really exploits CreateOrderRow objects having public properties...
            $swp_order = unserialize($_SESSION["swp_order"]);

            // ~hack
            $swp_deliverOrder->orderRows = $swp_order->orderRows;
            $swp_deliverOrder->shippingFeeRows = $swp_order->shippingFeeRows;
            $swp_deliverOrder->invoiceFeeRows = $swp_order->invoiceFeeRows;
            $swp_deliverOrder->fixedDiscountRows = $swp_order->fixedDiscountRows;
            $swp_deliverOrder->relativeDiscountRows = $swp_order->relativeDiscountRows;
            $swp_deliverOrder->countryCode = $swp_order->countryCode;
            // /hack

            $swp_deliverOrder->setOrderId( $swp_response->sveaOrderId );
            $swp_deliverOrder->setInvoiceDistributionType( MODULE_PAYMENT_SWPINVOICE_DISTRIBUTIONTYPE );

            $swp_deliveryResponse = $swp_deliverOrder->deliverInvoiceOrder()->doRequest();

            // all is well?
            if($swp_deliveryResponse->accepted == 1){
                //update order status for delivered
                $deliveryAccepted = 1;              // customer is now notified
                $order->info['order_status'] = 3;   // Delivered [3]
            }
            // delivery failure
            else {
                // order will show up as if AutoDeliver set to false in shop order history, i.e. handled manually
            }
        }

        // set zencart order info using data from response object
        $order->info['SveaOrderId'] = $swp_response->sveaOrderId;
        $order->info['type'] = $swp_response->customerIdentity->customerType;

        // set zencart order securityNumber -- if request to webservice, use sveaOrderId, if hosted use transactionId
        $order->info['securityNumber'] = isset( $swp_response->sveaOrderId ) ? $swp_response->sveaOrderId : $swp_response->transactionId;

        // insert zencart order into database
        $sql_data_array = array('orders_id' => $insert_id,
            'orders_status_id' => $order->info['order_status'], // set to MODULE_PAYMENT_SWPINVOICE_ORDER_STATUS_ID in constructor
            'date_added' => 'now()',
            'customer_notified' => $deliveryAccepted, // 0 = unlocked icon in zc admin order view, 1 = checkmark icon.
            'comments' => 'Accepted by Svea ' . date("Y-m-d G:i:s") . ' Security Number #: ' . $order->info['securityNumber']);   //TODO localize
        zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

        // make sure order status shows up as "delivered" in admin orders list
        $db->Execute(   "update " . TABLE_ORDERS . "
                        set orders_status = '" . $order->info['order_status'] . "', last_modified = now()
                        where orders_id = '" . $insert_id . "'");

        // clean up our session variables set during checkout   //$SESSION[swp_*
        unset($_SESSION['swp_order']);
        unset($_SESSION['swp_response']);

        return false;
    }

    // sets error message to the GET error value
    function get_error() {
        return array('title' => ERROR_MESSAGE_PAYMENT_FAILED, 'error' => stripslashes(urldecode($_GET['swperror'])));
    }

    // standard check if installed function
    function check() {
        global $db;
        if (!isset($this->_check)) {
            $check_rs = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_SWPINVOICE_STATUS'");
            $this->_check = !$check_rs->EOF;
        }
        return $this->_check;
    }

    // insert configuration keys here
    function install() {
        global $db;
        $common = "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added";
        $db->Execute($common . ", set_function) values ('Enable Svea Invoice Module', 'MODULE_PAYMENT_SWPINVOICE_STATUS', 'True', 'Do you want to accept Svea payments?', '6', '0', now(), 'zen_cfg_select_option(array(\'True\', \'False\'), ')");
        $db->Execute($common . ") values ('Svea Username SV', 'MODULE_PAYMENT_SWPINVOICE_USERNAME_SV', '', 'Username for Svea Invoice Sweden', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Password SV', 'MODULE_PAYMENT_SWPINVOICE_PASSWORD_SV', '', 'Password for Svea Invoice Sweden', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Username NO', 'MODULE_PAYMENT_SWPINVOICE_USERNAME_NO', '', 'Username for Svea Invoice Norway', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Password NO', 'MODULE_PAYMENT_SWPINVOICE_PASSWORD_NO', '', 'Password for Svea Invoice Norway', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Username FI', 'MODULE_PAYMENT_SWPINVOICE_USERNAME_FI', '', 'Username for Svea Invoice Finland', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Password FI', 'MODULE_PAYMENT_SWPINVOICE_PASSWORD_FI', '', 'Password for Svea Invoice Finland', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Username DK', 'MODULE_PAYMENT_SWPINVOICE_USERNAME_DK', '', 'Username for Svea Invoice Denmark', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Password DK', 'MODULE_PAYMENT_SWPINVOICE_PASSWORD_DK', '', 'Password for Svea Invoice Denmark', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Username NL', 'MODULE_PAYMENT_SWPINVOICE_USERNAME_NL', '', 'Username for Svea Invoice Netherlands', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Password NL', 'MODULE_PAYMENT_SWPINVOICE_PASSWORD_NL', '', 'Password for Svea Invoice Netherlands', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Username DE', 'MODULE_PAYMENT_SWPINVOICE_USERNAME_DE', '', 'Username for Svea Invoice Germany', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Password DE', 'MODULE_PAYMENT_SWPINVOICE_PASSWORD_DE', '', 'Password for Svea Invoice Germany', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Client no SV', 'MODULE_PAYMENT_SWPINVOICE_CLIENTNO_SV', '', '', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Client no NO', 'MODULE_PAYMENT_SWPINVOICE_CLIENTNO_NO', '', '', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Client no FI', 'MODULE_PAYMENT_SWPINVOICE_CLIENTNO_FI', '', '', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Client no DK', 'MODULE_PAYMENT_SWPINVOICE_CLIENTNO_DK', '', '', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Client no NL', 'MODULE_PAYMENT_SWPINVOICE_CLIENTNO_NL', '', '', '6', '0', now())");
        $db->Execute($common . ") values ('Svea Client no DE', 'MODULE_PAYMENT_SWPINVOICE_CLIENTNO_DE', '', '', '6', '0', now())");
        $db->Execute($common . ", set_function) values ('Transaction Mode', 'MODULE_PAYMENT_SWPINVOICE_MODE', 'Test', 'Transaction mode used for processing orders. Production should be used for a live working cart. Test for testing.', '6', '0', now(), 'zen_cfg_select_option(array(\'Production\', \'Test\'), ')");
        $db->Execute($common . ") values ('Accepted Currencies', 'MODULE_PAYMENT_SWPINVOICE_ALLOWED_CURRENCIES','SEK,NOK,DKK,EUR', 'The accepted currencies, separated by commas.  These <b>MUST</b> exist within your currencies table, along with the correct exchange rates.','6','0',now())");
        $db->Execute($common . ", set_function) values ('Default Currency', 'MODULE_PAYMENT_SWPINVOICE_DEFAULT_CURRENCY', 'SEK', 'Default currency used, if the customer uses an unsupported currency it will be converted to this. This should also be in the supported currencies list.', '6', '0', now(), 'zen_cfg_select_option(array(\'SEK\',\'NOK\',\'DKK\',\'EUR\'), ')");
        $db->Execute($common . ", set_function, use_function) values ('Set Order Status', 'MODULE_PAYMENT_SWPINVOICE_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value (but see AutoDeliver option below).', '6', '0', now(), 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name')");
        $db->Execute($common . ", set_function) values ('Auto Deliver Order', 'MODULE_PAYMENT_SWPINVOICE_AUTODELIVER', 'False', 'Do you want to autodeliver order invoices? This will override the the Set Order Status setting above.', '6', '0', now(), 'zen_cfg_select_option(array(\'True\', \'False\'), ')");
        $db->Execute($common . ", set_function) values ('Invoice Distribution type', 'MODULE_PAYMENT_SWPINVOICE_DISTRIBUTIONTYPE', 'Post', 'Deliver orders per Post or Email? NOTE: This must match your Svea admin settings or invoices may be non-delivered. Ask your Svea handler if unsure.', '6', '0', now(), 'zen_cfg_select_option(array(\'Post\', \'Email\'), ')");
        $db->Execute($common . ") values ('Ignore OT list', 'MODULE_PAYMENT_SWPINVOICE_IGNORE','ot_pretotal', 'Ignore the following order total codes, separated by commas.','6','0',now())");
        $db->Execute($common . ", set_function, use_function) values ('Payment Zone', 'MODULE_PAYMENT_SWPINVOICE_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', now(), 'zen_cfg_pull_down_zone_classes(', 'zen_get_zone_class_title')");
        $db->Execute($common . ") values ('Sort order of display.', 'MODULE_PAYMENT_SWPINVOICE_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
    }

    // standard uninstall function
    function remove() {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    // must perfectly match keys inserted in install function
    function keys() {
        return array('MODULE_PAYMENT_SWPINVOICE_STATUS',
            'MODULE_PAYMENT_SWPINVOICE_USERNAME_SV',
            'MODULE_PAYMENT_SWPINVOICE_PASSWORD_SV',
            'MODULE_PAYMENT_SWPINVOICE_CLIENTNO_SV',
            'MODULE_PAYMENT_SWPINVOICE_USERNAME_NO',
            'MODULE_PAYMENT_SWPINVOICE_PASSWORD_NO',
            'MODULE_PAYMENT_SWPINVOICE_CLIENTNO_NO',
            'MODULE_PAYMENT_SWPINVOICE_USERNAME_FI',
            'MODULE_PAYMENT_SWPINVOICE_PASSWORD_FI',
            'MODULE_PAYMENT_SWPINVOICE_CLIENTNO_FI',
            'MODULE_PAYMENT_SWPINVOICE_USERNAME_DK',
            'MODULE_PAYMENT_SWPINVOICE_PASSWORD_DK',
            'MODULE_PAYMENT_SWPINVOICE_CLIENTNO_DK',
            'MODULE_PAYMENT_SWPINVOICE_USERNAME_NL',
            'MODULE_PAYMENT_SWPINVOICE_PASSWORD_NL',
            'MODULE_PAYMENT_SWPINVOICE_CLIENTNO_NL',
            'MODULE_PAYMENT_SWPINVOICE_USERNAME_DE',
            'MODULE_PAYMENT_SWPINVOICE_PASSWORD_DE',
            'MODULE_PAYMENT_SWPINVOICE_CLIENTNO_DE',
            'MODULE_PAYMENT_SWPINVOICE_MODE',
            'MODULE_PAYMENT_SWPINVOICE_ALLOWED_CURRENCIES',
            'MODULE_PAYMENT_SWPINVOICE_DEFAULT_CURRENCY',
            'MODULE_PAYMENT_SWPINVOICE_ORDER_STATUS_ID',
            'MODULE_PAYMENT_SWPINVOICE_AUTODELIVER',
            'MODULE_PAYMENT_SWPINVOICE_DISTRIBUTIONTYPE',
            'MODULE_PAYMENT_SWPINVOICE_IGNORE',
            'MODULE_PAYMENT_SWPINVOICE_ZONE',
            'MODULE_PAYMENT_SWPINVOICE_SORT_ORDER');
    }
  /**
   *
   * @global type $currencies
   * @param float $value amount to convert
   * @param string $currency as three-letter $iso3166 country code
   * @param boolean $no_number_format if true, don't convert the to i.e. Swedish decimal indicator (",")
   *    Having a non-standard decimal may cause i.e. number conversion with floatval() to truncate fractions.
   * @return type
   */
    function convert_to_currency($value, $currency, $no_number_format = true) {
        global $currencies;

        // item price is ALWAYS given in internal price from the products DB, so just multiply by currency rate from currency table
        $rounded_value = zen_round($value * $currencies->currencies[$currency]['value'], $currencies->currencies[$currency]['decimal_places']);

        return $no_number_format ? $rounded_value : number_format(  $rounded_value,
                                                                    $currencies->currencies[$currency]['decimal_places'],
                                                                    $currencies->currencies[$currency]['decimal_point'],
                                                                    $currencies->currencies[$currency]['thousands_point']);
    }


    //Error Responses
    function responseCodes($err,$msg = NULL) {
        switch ($err) {

            // EU error codes
            case "20000" :
                return ERROR_CODE_20000;
                break;
            case "20001" :
                return ERROR_CODE_20001;
                break;
            case "20002" :
                return ERROR_CODE_20002;
                break;
            case "20003" :
                return ERROR_CODE_20003;
                break;
            case "20004" :
                return ERROR_CODE_20004;
                break;
            case "20005" :
                return ERROR_CODE_20005;
                break;
            case "20006" :
                return ERROR_CODE_20006;
                break;
            case "20013" :
                return ERROR_CODE_20013;
                break;

            case "24000" :
                return ERROR_CODE_24000;
                break;

            case "30000" :
                return ERROR_CODE_30000;
                break;
            case "30001" :
                return ERROR_CODE_30001;
                break;
            case "30002" :
                return ERROR_CODE_30002;
                break;
            case "30003" :
                return ERROR_CODE_30003;
                break;

            case "40000" :
                return ERROR_CODE_40000;
                break;
            case "40001" :
                return ERROR_CODE_40001;
                break;
            case "40002" :
                return ERROR_CODE_40002;
                break;
            case "40004" :
                return ERROR_CODE_40004;
                break;

            case "50000" :
                return ERROR_CODE_50000;
                break;

            default :
                return ERROR_CODE_DEFAULT . " " . $err . " - " . $msg;     // $err here is the response->resultcode
                break;
        }
    }

    function getCountryName( $iso3166 ) {

        // countrynames from https://github.com/johannesl/Internationalization, thanks!
        $countrynames = array(
            "AF"=>"Afghanistan",
            "AX"=>"\xc3\x85land Islands",
            "AL"=>"Albania",
            "DZ"=>"Algeria",
            "AS"=>"American Samoa",
            "AD"=>"Andorra",
            "AO"=>"Angola",
            "AI"=>"Anguilla",
            "AQ"=>"Antarctica",
            "AG"=>"Antigua and Barbuda",
            "AR"=>"Argentina",
            "AM"=>"Armenia",
            "AW"=>"Aruba",
            "AU"=>"Australia",
            "AT"=>"Austria",
            "AZ"=>"Azerbaijan",
            "BS"=>"Bahamas",
            "BH"=>"Bahrain",
            "BD"=>"Bangladesh",
            "BB"=>"Barbados",
            "BY"=>"Belarus",
            "BE"=>"Belgium",
            "BZ"=>"Belize",
            "BJ"=>"Benin",
            "BM"=>"Bermuda",
            "BT"=>"Bhutan",
            "BO"=>"Bolivia, Plurinational State of",
            "BQ"=>"Bonaire, Sint Eustatius and Saba",
            "BA"=>"Bosnia and Herzegovina",
            "BW"=>"Botswana",
            "BV"=>"Bouvet Island",
            "BR"=>"Brazil",
            "IO"=>"British Indian Ocean Territory",
            "BN"=>"Brunei Darussalam",
            "BG"=>"Bulgaria",
            "BF"=>"Burkina Faso",
            "BI"=>"Burundi",
            "KH"=>"Cambodia",
            "CM"=>"Cameroon",
            "CA"=>"Canada",
            "CV"=>"Cape Verde",
            "KY"=>"Cayman Islands",
            "CF"=>"Central African Republic",
            "TD"=>"Chad",
            "CL"=>"Chile",
            "CN"=>"China",
            "CX"=>"Christmas Island",
            "CC"=>"Cocos (Keeling) Islands",
            "CO"=>"Colombia",
            "KM"=>"Comoros",
            "CG"=>"Congo",
            "CD"=>"Congo, The Democratic Republic of the",
            "CK"=>"Cook Islands",
            "CR"=>"Costa Rica",
            "CI"=>"C\xc3\xb4te d'Ivoire",
            "HR"=>"Croatia",
            "CU"=>"Cuba",
            "CW"=>"Cura\xc3\xa7ao",
            "CY"=>"Cyprus",
            "CZ"=>"Czech Republic",
            "DK"=>"Denmark",
            "DJ"=>"Djibouti",
            "DM"=>"Dominica",
            "DO"=>"Dominican Republic",
            "EC"=>"Ecuador",
            "EG"=>"Egypt",
            "SV"=>"El Salvador",
            "GQ"=>"Equatorial Guinea",
            "ER"=>"Eritrea",
            "EE"=>"Estonia",
            "ET"=>"Ethiopia",
            "FK"=>"Falkland Islands (Malvinas)",
            "FO"=>"Faroe Islands",
            "FJ"=>"Fiji",
            "FI"=>"Finland",
            "FR"=>"France",
            "GF"=>"French Guiana",
            "PF"=>"French Polynesia",
            "TF"=>"French Southern Territories",
            "GA"=>"Gabon",
            "GM"=>"Gambia",
            "GE"=>"Georgia",
            "DE"=>"Germany",
            "GH"=>"Ghana",
            "GI"=>"Gibraltar",
            "GR"=>"Greece",
            "GL"=>"Greenland",
            "GD"=>"Grenada",
            "GP"=>"Guadeloupe",
            "GU"=>"Guam",
            "GT"=>"Guatemala",
            "GG"=>"Guernsey",
            "GN"=>"Guinea",
            "GW"=>"Guinea-Bissau",
            "GY"=>"Guyana",
            "HT"=>"Haiti",
            "HM"=>"Heard Island and McDonald Islands",
            "VA"=>"Holy See (Vatican City State)",
            "HN"=>"Honduras",
            "HK"=>"Hong Kong",
            "HU"=>"Hungary",
            "IS"=>"Iceland",
            "IN"=>"India",
            "ID"=>"Indonesia",
            "IR"=>"Iran, Islamic Republic of",
            "IQ"=>"Iraq",
            "IE"=>"Ireland",
            "IM"=>"Isle of Man",
            "IL"=>"Israel",
            "IT"=>"Italy",
            "JM"=>"Jamaica",
            "JP"=>"Japan",
            "JE"=>"Jersey",
            "JO"=>"Jordan",
            "KZ"=>"Kazakhstan",
            "KE"=>"Kenya",
            "KI"=>"Kiribati",
            "KP"=>"Korea, Democratic People's Republic of",
            "KR"=>"Korea, Republic of",
            "KW"=>"Kuwait",
            "KG"=>"Kyrgyzstan",
            "LA"=>"Lao People's Democratic Republic",
            "LV"=>"Latvia",
            "LB"=>"Lebanon",
            "LS"=>"Lesotho",
            "LR"=>"Liberia",
            "LY"=>"Libya",
            "LI"=>"Liechtenstein",
            "LT"=>"Lithuania",
            "LU"=>"Luxembourg",
            "MO"=>"Macao",
            "MK"=>"Macedonia, The Former Yugoslav Republic of",
            "MG"=>"Madagascar",
            "MW"=>"Malawi",
            "MY"=>"Malaysia",
            "MV"=>"Maldives",
            "ML"=>"Mali",
            "MT"=>"Malta",
            "MH"=>"Marshall Islands",
            "MQ"=>"Martinique",
            "MR"=>"Mauritania",
            "MU"=>"Mauritius",
            "YT"=>"Mayotte",
            "MX"=>"Mexico",
            "FM"=>"Micronesia, Federated States of",
            "MD"=>"Moldova, Republic of",
            "MC"=>"Monaco",
            "MN"=>"Mongolia",
            "ME"=>"Montenegro",
            "MS"=>"Montserrat",
            "MA"=>"Morocco",
            "MZ"=>"Mozambique",
            "MM"=>"Myanmar",
            "NA"=>"Namibia",
            "NR"=>"Nauru",
            "NP"=>"Nepal",
            "NL"=>"Netherlands",
            "NC"=>"New Caledonia",
            "NZ"=>"New Zealand",
            "NI"=>"Nicaragua",
            "NE"=>"Niger",
            "NG"=>"Nigeria",
            "NU"=>"Niue",
            "NF"=>"Norfolk Island",
            "MP"=>"Northern Mariana Islands",
            "NO"=>"Norway",
            "OM"=>"Oman",
            "PK"=>"Pakistan",
            "PW"=>"Palau",
            "PS"=>"Palestine, State of",
            "PA"=>"Panama",
            "PG"=>"Papua New Guinea",
            "PY"=>"Paraguay",
            "PE"=>"Peru",
            "PH"=>"Philippines",
            "PN"=>"Pitcairn",
            "PL"=>"Poland",
            "PT"=>"Portugal",
            "PR"=>"Puerto Rico",
            "QA"=>"Qatar",
            "RE"=>"R\xc3\xa9union",
            "RO"=>"Romania",
            "RU"=>"Russian Federation",
            "RW"=>"Rwanda",
            "BL"=>"Saint Barth\xc3\xa9lemy",
            "SH"=>"Saint Helena, Ascension and Tristan Da Cunha",
            "KN"=>"Saint Kitts and Nevis",
            "LC"=>"Saint Lucia",
            "MF"=>"Saint Martin (French part)",
            "PM"=>"Saint Pierre and Miquelon",
            "VC"=>"Saint Vincent and the Grenadines",
            "WS"=>"Samoa",
            "SM"=>"San Marino",
            "ST"=>"Sao Tome and Principe",
            "SA"=>"Saudi Arabia",
            "SN"=>"Senegal",
            "RS"=>"Serbia",
            "SC"=>"Seychelles",
            "SL"=>"Sierra Leone",
            "SG"=>"Singapore",
            "SX"=>"Sint Maarten (Dutch part)",
            "SK"=>"Slovakia",
            "SI"=>"Slovenia",
            "SB"=>"Solomon Islands",
            "SO"=>"Somalia",
            "ZA"=>"South Africa",
            "GS"=>"South Georgia and the South Sandwich Islands",
            "SS"=>"South Sudan",
            "ES"=>"Spain",
            "LK"=>"Sri Lanka",
            "SD"=>"Sudan",
            "SR"=>"Suriname",
            "SJ"=>"Svalbard and Jan Mayen",
            "SZ"=>"Swaziland",
            "SE"=>"Sweden",
            "CH"=>"Switzerland",
            "SY"=>"Syrian Arab Republic",
            "TW"=>"Taiwan, Province of China",
            "TJ"=>"Tajikistan",
            "TZ"=>"Tanzania, United Republic of",
            "TH"=>"Thailand",
            "TL"=>"Timor-Leste",
            "TG"=>"Togo",
            "TK"=>"Tokelau",
            "TO"=>"Tonga",
            "TT"=>"Trinidad and Tobago",
            "TN"=>"Tunisia",
            "TR"=>"Turkey",
            "TM"=>"Turkmenistan",
            "TC"=>"Turks and Caicos Islands",
            "TV"=>"Tuvalu",
            "UG"=>"Uganda",
            "UA"=>"Ukraine",
            "AE"=>"United Arab Emirates",
            "GB"=>"United Kingdom",
            "US"=>"United States",
            "UM"=>"United States Minor Outlying Islands",
            "UY"=>"Uruguay",
            "UZ"=>"Uzbekistan",
            "VU"=>"Vanuatu",
            "VE"=>"Venezuela, Bolivarian Republic of",
            "VN"=>"Viet Nam",
            "VG"=>"Virgin Islands, British",
            "VI"=>"Virgin Islands, U.S.",
            "WF"=>"Wallis and Futuna",
            "EH"=>"Western Sahara",
            "YE"=>"Yemen",
            "ZM"=>"Zambia",
            "ZW"=>"Zimbabwe"
        );

        return( array_key_exists( $iso3166, $countrynames) ? $countrynames[$iso3166] : $iso3166 );
    }


    /**
     * TODO replace these with the one in php integration package Helper class in next release
     *
     * Takes a total discount value ex. vat, a mean tax rate & an array of allowed tax rates.
     * returns an array of FixedDiscount objects representing the discount split
     * over the allowed Tax Rates, defined using AmountExVat & VatPercent.
     *
     * Note: only supports two allowed tax rates for now.
     */
    private function splitMeanToTwoTaxRates( $discountAmountExVat, $discountMeanVat, $discountName, $discountDescription, $allowedTaxRates )
    {

        $fixedDiscounts = array();

        if( sizeof( $allowedTaxRates ) > 1 ) {

            // m = $discountMeanVat
            // r0 = allowedTaxRates[0]; r1 = allowedTaxRates[1]
            // m = a r0 + b r1 => m = a r0 + (1-a) r1 => m = (r0-r1) a + r1 => a = (m-r1)/(r0-r1)
            // d = $discountAmountExVat;
            // d = d (a+b) => 1 = a+b => b = 1-a

            $a = ($discountMeanVat - $allowedTaxRates[1]) / ( $allowedTaxRates[0] - $allowedTaxRates[1] );
            $b = 1 - $a;

            $discountA = WebPayItem::fixedDiscount()
                            ->setAmountExVat( Svea\Helper::bround(($discountAmountExVat * $a),2) )
                            ->setVatPercent( $allowedTaxRates[0] )
                            ->setName( isset( $discountName) ? $discountName : "" )
                            ->setDescription( (isset( $discountDescription) ? $discountDescription : "") . ' (' .$allowedTaxRates[0]. '%)' )
            ;

            $discountB = WebPayItem::fixedDiscount()
                            ->setAmountExVat( Svea\Helper::bround(($discountAmountExVat * $b),2) )
                            ->setVatPercent(  $allowedTaxRates[1] )
                            ->setName( isset( $discountName) ? $discountName : "" )
                            ->setDescription( (isset( $discountDescription) ? $discountDescription : "") . ' (' .$allowedTaxRates[1]. '%)' )
            ;

            $fixedDiscounts[] = $discountA;
            $fixedDiscounts[] = $discountB;
        }
        // single tax rate, so use shop supplied mean as vat rate
        else {
            $discountA = WebPayItem::fixedDiscount()
                ->setAmountExVat( Svea\Helper::bround(($discountAmountExVat),2) )
                ->setVatPercent( $allowedTaxRates[0] )
                ->setName( isset( $discountName) ? $discountName : "" )
                ->setDescription( (isset( $discountDescription) ? $discountDescription : "") )
            ;
            $fixedDiscounts[] = $discountA;
        }

        return $fixedDiscounts;
    }
    /**
     * TODO replace these with the one in php integration package Helper class in next release
     *
     * Takes a createOrderBuilder object, iterates over its orderRows, and
     * returns an array containing the distinct taxrates present in the order
     */
    private function getTaxRatesInOrder($order) {
        $taxRates = array();

        foreach( $order->orderRows as $orderRow ) {

            if( isset($orderRow->vatPercent) ) {
                $seenRate = $orderRow->vatPercent; //count
            }
            elseif( isset($orderRow->amountIncVat) && isset($orderRow->amountExVat) ) {
                $seenRate = Svea\Helper::bround( (($orderRow->amountIncVat - $orderRow->amountExVat) / $orderRow->amountExVat) ,2) *100;
            }

            if(isset($seenRate)) {
                isset($taxRates[$seenRate]) ? $taxRates[$seenRate] +=1 : $taxRates[$seenRate] =1;   // increase count of seen rate
            }
        }
        return array_keys($taxRates);   //we want the keys
    }

}
?>