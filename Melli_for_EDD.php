<?php

/*
  Plugin Name: Bank Melli Iran EDD gateway
  Version: 1.5.2
  Description:  Sadad payment gateway for Easy digital downloads(switch: 1).
  Plugin URI: http://ham3da.ir/
  Author: Javad Ahshamian
  Author URI: https://ham3da.ir/
  License: GPLv2
  Tested up to: 4.9.4
  Text Domain: ex_lang
  Domain Path: /lang
 */

if (!defined('ABSPATH'))
{
    die("Access Denied");
}

add_action('plugins_loaded', 'edd_sadad1_load_textdomain');

function edd_sadad1_load_textdomain()
{
    load_plugin_textdomain('ex_lang', false, basename(dirname(__FILE__)) . '/lang');
}

add_action('admin_menu', 'bmi_setMenu');

function bmi_setMenu()
{

    add_menu_page(__('Sadad for EDD', 'ex_lang'), __('Sadad for EDD', 'ex_lang'), 'activate_plugins', "melli_bank_gate", 'bmi_load_inteface', plugin_dir_url(__FILE__) . '/images/icon.png');
    add_submenu_page("melli_bank_gate", __('About', 'ex_lang'), __('About', 'ex_lang'), 'activate_plugins', "melli_bank_gate_about", "bmi_load_about");
    add_submenu_page("melli_bank_gate", __('Newsletters', 'ex_lang'), __('Newsletters', 'ex_lang'), 'activate_plugins', "melli_bank_gate_news", "bmi_load_news");
}

function bmi_load_inteface()
{
    include dirname(__file__) . "/melli.php";
}

function bmi_load_about()
{
    include dirname(__file__) . "/about.php";
}

function bmi_load_news()
{
    include dirname(__file__) . "/news.php";
}

/////------------------------------------------------
function edd_bmi_rial($formatted, $currency, $price)
{

    return $price . __(' Rial', 'ex_lang');
}

add_filter('edd_rial_currency_filter_after', 'edd_bmi_rial', 10, 3);

/////------------------------------------------------
function bmi_add_gateway($gateways)
{
    $gateways['melli_gate'] = array('admin_label' => __('Sadad Payment Gateway', 'ex_lang'), 'checkout_label' => __('Sadad Payment Gateway', 'ex_lang'));
    return $gateways;
}

add_filter('edd_payment_gateways', 'bmi_add_gateway');

function bmi_cc_form()
{
    do_action('bmi_cc_form_action');
}

add_filter('edd_melli_gate_cc_form', 'bmi_cc_form');
/////-------------------------------------------------
function bmi_process_payment($purchase_data)
{
    $site_title = get_bloginfo('name');
    $request_form1 = '<html dir=rtl>
<head>
<meta http-equiv="Content-Language" content="fa">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>' . $site_title . '</title>
</head>
<body><center>
<div  style= "border: 1px solid #CCC;font-family:Tahoma;font-size: 12; background-color:#fafafa; width:98%;">
<font color="black" size=4 px>
		<p/>
		<br/>' . __('Connecting to the payment Gateway...', 'ex_lang') . '<br>
		<br/>' . __('Please wait...', 'ex_lang') . '<br>
&nbsp;</font></div>
</center>';
    global $edd_options;

    $client = new SoapClient('https://sadad.shaparak.ir/services/MerchantUtility.asmx?wsdl');

    if ($client->fault)
    {
        edd_set_error('pay_00', __('An error occurred while connecting to the payment gateway[code: 00].', 'ex_lang'));
        edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
        exit();
    }



    $payment_data = array(
        'price' => $purchase_data['price'],
        'date' => $purchase_data['date'],
        'user_email' => $purchase_data['post_data']['edd_email'],
        'purchase_key' => $purchase_data['purchase_key'],
        'currency' => $edd_options['currency'],
        'downloads' => $purchase_data['downloads'],
        'cart_details' => $purchase_data['cart_details'],
        'user_info' => $purchase_data['user_info'],
        'status' => 'pending');
    $payment_id = edd_insert_payment($payment_data);

    $Melli_MerchantID = $edd_options['Melli_MerchantID'];
    $Melli_Terminal_ID = $edd_options['Melli_Terminal_ID'];
    $Melli_Password = $edd_options['Melli_Password'];

    if ($payment_id)
    {
        $orderId = time();
        $return = add_query_arg(array('gate' => 'melli_gate', 'pid' => $payment_id, 'orderId' => $orderId), get_permalink($edd_options['success_page']));
        $amount = $purchase_data['price'];
        $localDate = date("Ymd");
        $localTime = date("His");
        $additionalData = "Purchase key: " . $purchase_data['purchase_key'];

/////////////////PAY REQUEST PART/////////////////////////
        // Call the SOAP method

        $PayResult = $client->PaymentUtility($Melli_MerchantID, $amount, $orderId, $Melli_Password, $Melli_Terminal_ID, $return);

///************END of PAY REQUEST***************///
// Successfull Pay Request
        if (is_array($PayResult))
        {
            edd_update_payment_meta($payment_id, 'RequestKey', $PayResult['RequestKey']);
            $FormStr = $PayResult['PaymentUtilityResult'];
            //var_dump($PayResult);
            die($request_form1 . $FormStr .
                    '<script type="text/javascript">
                          document.getElementById("paymentUTLfrm").submit();
                          </script></form></body></html>');
        }
        else
        {

            edd_update_payment_status($payment_id, 'failed');
            edd_insert_payment_note($payment_id, __('Error connecting to payment gateway.[code: 02]', 'ex_lang'));
            edd_set_error('pay_02', __('Error connecting to payment gateway.[code: 02]', 'ex_lang'));
            edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
        }
    }
    else
    {
        edd_set_error('pay_01', __('Error creating payment, please try again', 'ex_lang'));
        edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
    }
}

add_action('edd_gateway_melli_gate', 'bmi_process_payment');

/////----------------------------------------------------
function bmi_verify()
{
    //error_reporting(0);
    global $edd_options;

    $Melli_MerchantID = $edd_options['Melli_MerchantID'];
    $Melli_Terminal_ID = $edd_options['Melli_Terminal_ID'];
    $Melli_Password = $edd_options['Melli_Password'];


    if (isset($_GET['gate']) && $_GET['gate'] == 'melli_gate' && isset($_GET["orderId"]) && isset($_GET["pid"]))
    {
        //$payment = $_SESSION['Melli_payment'];
        $res_id = intval($_GET["orderId"]); //orderid
        $payment_id = intval($_GET["pid"]); //payment_id
        
        $do_inquiry = false;
        $do_settle = false;
        $do_reversal = false;
        $do_publish = false;
        
        $RequestKey = edd_get_payment_meta($payment_id, 'RequestKey', true);


        //Connect to WebService

        $client = new SoapClient('https://sadad.shaparak.ir/services/MerchantUtility.asmx?wsdl');
        if ($client->fault)
        {
            edd_set_error('ver_03', __('Transaction unsuccessful. if the amount of your bank account has been reduced. Until the end of today, your account will be refunded[code: 03].', 'ex_lang'));
            edd_update_payment_status($payment_id, 'failed');
            edd_insert_payment_note($payment_id, __('Error in Bank Verification[code: 03]', 'ex_lang'));
            edd_send_back_to_checkout('?payment-mode=melli_gate');
        }
//////////////////VERIFY REQUEST///////////////////////
        if (!edd_is_test_mode())
        {
            // Call the SOAP method
            $payment_data = new EDD_Payment($payment_id);
            $price = $payment_data->total;
              
            $result = $client->CheckRequestStatusResult($res_id, $Melli_MerchantID, $Melli_Terminal_ID, $Melli_Password, $RequestKey, $price);
            $array = get_object_vars($result);
            
            $err_msg = bmi_CheckStatus($array['FailCode']);

            $ResponseCode = $array['ResponseCode'];
            $RefNo = $array['RefrenceNumber'];
            $AppStatusDescription = $array['AppStatusDescription'];
            $AppStatusCode = $array['AppStatusCode'];
            
                $do_reversal = false;
                $do_publish = true;
                
            if (intval($AppStatusCode) == 0 && $AppStatusDescription == "COMMIT")
            {
                $TraceNo = $array['TraceNo'];
                //واریز شد
                $do_reversal = false;
                $do_publish = true;
            }
            else
            {
                $do_reversal = true;
                $do_publish = false;
            }
        }
        else
        {
            //in test mode
            $do_reversal = true;
            $do_publish = false;
        }
///*************************END of VERIFY REQUEST**///
//////////////////REVERSAL REQUEST////////////////////
        if ($do_reversal == true)
        {

            edd_set_error('rev_04', __('Transaction unsuccessful. if the amount of your bank account has been reduced. Until the end of today, your account will be refunded[code: 04].', 'ex_lang'));
            edd_update_payment_status($payment_id, 'failed');
            edd_insert_payment_note($payment_id, 'R04:' . '<pre>' . $err_msg . '</pre>');
            edd_send_back_to_checkout('?payment-mode=melli_gate');
        }
        #echo $soapclient->debug_str;
///***************END of REVERSAL REQUEST*******************///
        if ($do_publish == true)
        {
            // Publish Payment
            $do_publish = false;
            edd_update_payment_status($payment_id, 'publish');
            edd_insert_payment_note($payment_id, __('Transaction Number:', 'ex_lang') . $TraceNo);
            //echo "<script type='text/javascript'>alert('کد تراکنش خرید بانک : ".$TraceNo."');</script>";
        }
    }
}

add_action('init', 'bmi_verify');

/////-----------------------------------------------
function bmi_add_settings($settings)
{
    $Melli_settings = array(
        array(
            'id' => 'Melli_settings',
            'name' => __('<b>Sadad Gateway Settings</b><br>Do not complete in the test mode', 'ex_lang'),
            'desc' => '',
            'type' => 'header'
        ),
        array(
            'id' => 'Melli_MerchantID',
            'name' => __('Merchant ID', 'ex_lang'), //شماره پذیرنده
            'desc' => '',
            'type' => 'text',
            'size' => 'medium'
        ),
        array(
            'id' => 'Melli_Terminal_ID',
            'name' => __('Terminal ID', 'ex_lang'), //شماره ترمینال
            'desc' => '',
            'type' => 'text',
            'size' => 'medium'
        ),
        array(
            'id' => 'Melli_Password',
            'name' => __('Password', 'ex_lang'), //رمز
            'desc' => '',
            'type' => 'text',
            'size' => 'medium'
        )
    );
    return array_merge($settings, $Melli_settings);
}

add_filter('edd_settings_gateways', 'bmi_add_settings');

/////-------------------------------------------------
function bmi_CheckStatus($ErrorCode)
{
    $tmess = "شرح خطا: ";
    $ErrorDesc = $ErrorCode;
    if ($ErrorCode == "0")
        $ErrorDesc = "بدون اشكال";
    if ($ErrorCode == "1")
        $ErrorDesc = "با بانک ملی تماس حا صل نمایید";
    else if ($ErrorCode == "12")
        $ErrorDesc = "تراکنش معتبر نمی باشد";
    else if ($ErrorCode == "13")
        $ErrorDesc = "مبلغ تراکنش معتبر نمی باشد";
    else if ($ErrorCode == "30")
        $ErrorDesc = "فرمت پيام دچار اشكال مي باشد";
    else if ($ErrorCode == "33")
        $ErrorDesc = "تاریخ استفاده کارت به پایان رسیده است";
    else if ($ErrorCode == "41")
        $ErrorDesc = "كارت مفقود مي باشد";
    else if ($ErrorCode == "43")
        $ErrorDesc = "كارت مسروقه است";
    else if ($ErrorCode == "51")
        $ErrorDesc = "موجودي حساب كافي نمي باشد";
    else if ($ErrorCode == "55")
        $ErrorDesc = "رمز وارده صحيح نمي باشد";
    else if ($ErrorCode == "56")
        $ErrorDesc = "شماره کارت یا CVV2  صحیح نمی باشد ";
    else if ($ErrorCode == "57")
        $ErrorDesc = "دارنده کارت مجاز به انجام این تراکنش نمی باشد";
    else if ($ErrorCode == "58")
        $ErrorDesc = "پذیرنده کارت مجاز به انجام این تراکنش نمی باشد";
    else if ($ErrorCode == "61")
        $ErrorDesc = "مبلغ تراکنش از حد مجاز بالاتر است";
    else if ($ErrorCode == "65")
        $ErrorDesc = "تعداد دفعات تراکنش از حد مجاز بیشتر است";
    else if ($ErrorCode == "75")
        $ErrorDesc = "ورود رمز دوم از حد مجاز گذشته است. رمز دوم جدید در خواست نمایید";
    else if ($ErrorCode == "79")
        $ErrorDesc = "شماره حساب نامعتبر است";
    else if ($ErrorCode == "80")
        $ErrorDesc = "تراكنش موفق عمل نكرده است";
    else if ($ErrorCode == "84")
        $ErrorDesc = "سوئيچ صادركننده فعال نيست";
    else if ($ErrorCode == "88")
        $ErrorDesc = "سيستم دچار اشكال شده است";
    else if ($ErrorCode == "90")
        $ErrorDesc = "ارتباط به طور موقت قطع می باشد";
    else if ($ErrorCode == "91")
        $ErrorDesc = "پاسخ در زمان تعیین شده بدست سیستم نرسیده است";
    else if ($ErrorCode == "-1")
        $ErrorDesc = "یکی از موارد مبلغ، شماره سفارش یا کلید اشتباه است";
    else if ($ErrorCode == "1003")
        $ErrorDesc = "اطلاعات پذیرنده اشتباه است";
    else if ($ErrorCode == "1004")
        $ErrorDesc = "پذیرنده موجود نیست";
    else if ($ErrorCode == "1006")
        $ErrorDesc = "خطای داخلی";
    else if ($ErrorCode == "1012")
        $ErrorDesc = "اطلاعات پذیرنده اشتباه است";
    else if ($ErrorCode == "1017")
        $ErrorDesc = "پاسخ خطا از سمت مرکز";
    else if ($ErrorCode == "1018")
        $ErrorDesc = "شماره کارت اشتباه است";
    else if ($ErrorCode == "1019")
        $ErrorDesc = "مبلغ بیش از حد مجاز است";
    else if ($ErrorCode == "9005")
        $ErrorDesc = "تراکنش ناموفق ( مبلغ به حساب دارنده کارت برگشت داده شده است)";
    else if ($ErrorCode == "9006")
        $ErrorDesc = "تراکنش ناتمام ( در صورت کسرموجودی مبلغ به حساب دارنده کارت برگشت داده می شود)";
    return $tmess . $ErrorDesc;
}
