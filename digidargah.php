<?php

/*
* Plugin Name: DigiDargah crypto payment gateway for WHMCS
* Description: <a href="https://digidargah.com">DigiDargah</a> crypto payment gateway for WHMCS.
* Version: 1.1
* developer: Hanif Zekri Astaneh
* Author: DigiDargah.com
* Author URI: https://digidargah.com
* Author Email: info@digidargah.com
* Text Domain: DigiDargah_WHMCS_payment_module
* WC tested up to: 8.8
* copyright (C) 2020 DigiDargah
* license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 or later
*/

use WHMCS\Database\Capsule;

$invoice_id = intval($_GET['invoiceid']);

if ($invoice_id > 0) {

    require_once __DIR__ . '/../../init.php';
    require_once __DIR__ . '/../../includes/gatewayfunctions.php';
    require_once __DIR__ . '/../../includes/invoicefunctions.php';

    $gatewayParams = getGatewayVariables('digidargah');
    if (!$gatewayParams['type']) die('ماژول غیرفعال است.');
	
	$user_id = $_SESSION['uid'];
	$api_key = $gatewayParams['api_key'];
	$pay_currency = $gatewayParams['pay_currency'];
	
	$action = $_GET['action'];
	$currency = $_GET['currency'];

	if ($action == 'pay' and $invoice_id > 0 and $user_id > 0) {

        $invoice = Capsule::table('tblinvoices')->where('id', $invoice_id)->where('status', 'Unpaid')->where('userid', $user_id)->first();
        
		if (!$invoice)
			die("متاسفانه این فاکتور وجود ندارد و یا متعلق به شما نیست. در صورتی که تصور می کنید مشکلی بوجود آمده است با پشتیبانی مکاتبه نمایید.");

        $user = Capsule::table('tblclients')->where('id', $user_id)->first();
        
        $amount = $invoice->total;
        /* Remove Added Slash In Version 7 Or Above */
        $systemurl = rtrim($gatewayParams['systemurl'], '/') . '/';

        $params = array('api_key' => $api_key,
						'amount_value' => $amount,
						'amount_currency' => strtolower($currency),
						'pay_currency' => $pay_currency,
            			'order_id' => $invoice_id,
            			'respond_type' => 'link',
            			'callback' => $systemurl . 'modules/gateways/digidargah.php?action=confirm&invoiceid=' . $invoice_id);
						
		$options = array( 'http' => array('method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'timeout' => 10, 'content' => http_build_query($params)), 'ssl' => array('verify_peer' => false, 'verify_peer_name' => false));
		
		$url = 'https://digidargah.com/action/ws/request_create';
		$response = file_get_contents($url, false, stream_context_create($options));
		$response = json_decode($response);
		
		if ($response->status != 'success')
			echo '<p> درگاه پرداخت با خطا مواجه شد. <br> پاسخ درگاه : ' . $response->respond .'</p>';
		else {
			$is_Updated = Capsule::table('tblinvoices')->where('id', $invoice_id)->update(['notes' => $response->request_id]);
            if ($is_Updated == 1) header('Location: ' . $response->respond);
            if ($is_Updated == 0) die('پایگاه داده با خطا مواجه شد. لطفا مجددا تلاش نمایید و در صورت عدم رفع مشکل، با پشتیبانی مکاتبه نمایید.');
        }
    }
	
    if ($action == 'confirm' and $invoice_id > 0 and $user_id > 0){
		
		$invoice = Capsule::table('tblinvoices')->where('id', $invoice_id)->where('status', 'Unpaid')->first();
        
		if (!$invoice)
			die("متاسفانه این فاکتور وجود ندارد. در صورتی که تصور می کنید مشکلی بوجود آمده است با پشتیبانی مکاتبه نمایید.");
			
        $checkGateway = checkCbInvoiceID($invoice_id, $gatewayParams['name']);
        if (!$checkGateway) die("برای پرداخت این فاکتور، درگاه دیگری انتخاب شده است.");
		
		$params = array('api_key' => $api_key,
						'order_id' => $invoice_id,
						'request_id' => $invoice->notes);
		
		$options = array( 'http' => array('method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'timeout' => 10, 'content' => http_build_query($params)), 'ssl' => array('verify_peer' => false, 'verify_peer_name' => false));
		
		$url = 'https://digidargah.com/action/ws/request_status';
		$result = file_get_contents($url, false, stream_context_create($options));
		$response = json_decode($result);
		
		if ($response->status != 'success') {
			
			logTransaction($gatewayParams['name'], ["GET" => $_GET, "POST" => $_POST, "result" => $result], 'Failure');
			
			$message = digidargah_get_filled_message($gatewayParams['failed_massage'], $invoice->notes, $invoice_id);
            Capsule::table('tblinvoices')->where('id', $invoice_id)->update(['notes' => $message . '<br> پاسخ درگاه : ' . $response->respond]);
			
            header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $invoice_id);
		
		} else {
		
			$verify_status = empty($response->status) ? NULL : $response->status;
			$verify_request_id = empty($response->request_id) ? NULL : $response->request_id;
			$verify_amount = empty($response->amount_value) ? NULL : $response->amount_value;
			
			$amount = $invoice->total;
			
			if (number_format($verify_amount, 5) != number_format($amount, 5)) {
				$message = digidargah_get_filled_message($gatewayParams['failed_massage'], $verify_request_id, $invoice_id);
				$message .= '<br> متاسفانه در روند تایید تراکنش خطایی رخ داده است. لطفا مجددا تلاش نمایید و یا در صورت نیاز با پشتیبانی مکاتبه نمایید.';
				
				logTransaction($gatewayParams['name'], ["GET" => $_GET, "POST" => $_POST, "result" => $result], 'Failure');
				Capsule::table('tblinvoices')->where('id', $invoice_id)->update(['notes' => $message]);
				header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $invoice_id);
			
			} else {
				addInvoicePayment($invoice_id, $verify_request_id, $amount, 0, $gatewayParams['paymentmethod']);
				$message = digidargah_get_filled_message($gatewayParams['success_massage'], $verify_request_id, $invoice_id);
				logTransaction($gatewayParams['name'], ["GET" => $_GET, "POST" => $_POST, "result" => $result], 'Success');
				Capsule::table('tblinvoices')->where('id', $invoice_id)->update(['notes' => $message]);
				header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $invoice_id);
			}
		
		}
	}
}

function digidargah_MetaData(){
    return array(
        'DisplayName' => 'درگاه پرداخت رمز ارزی دیجی درگاه',
        'APIVersion' => '1.1',
    );
}

function digidargah_config(){
    return [
        "FriendlyName" => [
            "Type" => 'System',
            "Value" => 'دیجی درگاه',
        ],
        "api_key" => [
            "FriendlyName" => 'کلید API',
            "Type" => 'text',
			"Value" => '',
			"Description" => 'برای ایجاد کلید API لطفا به آدرس رو به رو مراجعه نمایید. <a href="https://digidargah.com/cryptosite" target="_blank">https://digidargah.com/cryptosite</a>'
        ],
        "pay_currency" => [
            "FriendlyName" => 'ارزهای قابل انتخاب',
            "Type" => 'text',
			"Value" => '',
			"Description" => 'به صورت پیش فرض کاربر امکان پرداخت از طریق تمامی <a href="https://digidargah.com/cryptosite" target="_blank"> ارزهای فعال </a> در درگاه را دارد اما در صورتی که تمایل دارید مشتری را محدود به پرداخت از طریق یک یا چند ارز خاص کنید، می توانید از طریق این متغییر نام ارز و یا ارزها را اعلام نمایید. در صورت تمایل به اعلام بیش از یک ارز، آنها را توسط خط تیره ( dash ) از هم جدا کنید.'
        ],
        "success_massage" => [
            "FriendlyName" => 'پیام پرداخت موفق',
            "Type" => 'textarea',
            "Value" => 'پرداخت شما با موفقیت انجام شد. <br><br> شماره فاکتور : {invoice_id} <br> کد رهگیری درگاه پرداخت : {request_id}',
            "Description" => 'از طریق این فیلد می توانید متن پیامی را که می خواهید بعد از پرداخت موفق به کاربر نمایش داده شود تنظیم نمایید. همچنین می توانید از عبارت های کلیدی {invoice_id} برای نمایش شماره فاکتور و {request_id} برای نمایش کد رهگیری دیجی درگاه استفاده نمایید.'
        ],
        "failed_massage" => [
            "FriendlyName" => 'پیام پرداخت ناموفق',
            "Type" => 'textarea',
            "Value" => 'پرداخت شما با موفقیت انجام نشد. <br><br> شماره فاکتور : {invoice_id} <br> کد رهگیری درگاه پرداخت : {request_id}',
            "Description" => 'از طریق این فیلد می توانید متن پیامی را که می خواهید بعد از پرداخت ناموفق به کاربر نمایش داده شود تنظیم نمایید. همچنین می توانید از عبارت های کلیدی {invoice_id} برای نمایش شماره فاکتور و {request_id} برای نمایش کد رهگیری دیجی درگاه استفاده نمایید.'
        ]
    ];
}

function digidargah_link($params){
	if ($_SESSION['uid'] <= 0)
		$htmlOutput .= '<a href="/clientarea.php" class="btn btn-success btn-sm" id="btnPayNow" value="Submit"> برای پرداخت لطفا وارد حساب کاربری تان شوید </a>';
	else {
		$htmlOutput = '<form method="get" action="modules/gateways/digidargah.php">';
		$htmlOutput .= '<input type="hidden" name="action" value="pay">';
		$htmlOutput .= '<input type="hidden" name="invoiceid" value="' . $params['invoiceid'] . '">';
		$htmlOutput .= '<input type="hidden" name="currency" value="' . $params['currency'] . '">';
		$htmlOutput .= '<button type="submit" class="btn btn-success btn-sm" id="btnPayNow" value="Submit"> پرداخت فاکتور </button>';
		$htmlOutput .= '</form>';
	}
    return $htmlOutput;
}

function digidargah_get_filled_message($massage, $request_id, $invoice_id){
    return str_replace(["{request_id}", "{invoice_id}"], [$request_id, $invoice_id], $massage);
}

?>
