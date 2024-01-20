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
* Tested version up to: 8.8
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
		$desc = $user->firstname . ' ' . $user->lastname . ' ' . $user->phonenumber;
        
        $amount = $invoice->total;
        $systemurl = rtrim($gatewayParams['systemurl'], '/');

        $params = array(
			'api_key' => $api_key,
			'amount_value' => $amount,
			'amount_currency' => strtolower($currency),
			'pay_currency' => $pay_currency,
            'order_id' => $invoice_id,
			'desc' => $desc,
            'respond_type' => 'link',
            'callback' => $systemurl . 'modules/gateways/digidargah.php?action=confirm&invoiceid=' . $invoice_id
		);
		
		$url = 'https://digidargah.com/action/ws/request_create';
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);
				
		$result = json_decode($response);
		
		if ($result->status != 'success')
			echo '<p> درگاه پرداخت با خطا مواجه شد. <br> پاسخ درگاه : ' . $result->respond .'</p>';
		else {
			$is_Updated = Capsule::table('tblinvoices')->where('id', $invoice_id)->update(['notes' => $result->request_id]);
            if ($is_Updated == 1) header('Location: ' . $result->respond);
            if ($is_Updated == 0) die('پایگاه داده با خطا مواجه شد. لطفا مجددا تلاش نمایید و در صورت عدم رفع مشکل، با پشتیبانی مکاتبه نمایید.');
        }
    }
	
    if ($action == 'confirm' and $invoice_id > 0 and $user_id > 0){
		
		$invoice = Capsule::table('tblinvoices')->where('id', $invoice_id)->where('status', 'Unpaid')->first();
        
		if (!$invoice)
			die("متاسفانه این فاکتور وجود ندارد. در صورتی که تصور می کنید مشکلی بوجود آمده است با پشتیبانی مکاتبه نمایید.");
			
        $checkGateway = checkCbInvoiceID($invoice_id, $gatewayParams['name']);
        if (!$checkGateway) die("برای پرداخت این فاکتور، درگاه دیگری انتخاب شده است.");
		
		$params = array(
			'api_key' => $api_key,
			'order_id' => $invoice_id,
			'request_id' => $invoice->notes
		);
				
		$url = 'https://digidargah.com/action/ws/request_status';
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);
		
		$result = json_decode($response);
		
		if ($result->status != 'success') {
			
			logTransaction($gatewayParams['name'], ["GET" => $_GET, "POST" => $_POST, "result" => $response], 'Failure');
			
			$message = digidargah_get_filled_message($gatewayParams['failed_massage'], $invoice->notes, $invoice_id);
            Capsule::table('tblinvoices')->where('id', $invoice_id)->update(['notes' => $message . '<br> پاسخ درگاه : ' . $result->respond]);
			
            header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $invoice_id);
		
		} else {
		
			$verify_status = empty($result->status) ? NULL : $result->status;
			$verify_request_id = empty($result->request_id) ? NULL : $result->request_id;
			$verify_amount = empty($result->amount_value) ? NULL : $result->amount_value;
			
			$amount = $invoice->total;
			
			if (number_format($verify_amount, 5) != number_format($amount, 5)) {
				$message = digidargah_get_filled_message($gatewayParams['failed_massage'], $verify_request_id, $invoice_id);
				$message .= '<br> متاسفانه در روند تایید تراکنش خطایی رخ داده است. لطفا مجددا تلاش نمایید و یا در صورت نیاز با پشتیبانی مکاتبه نمایید.';
				
				logTransaction($gatewayParams['name'], ["GET" => $_GET, "POST" => $_POST, "result" => $response], 'Failure');
				Capsule::table('tblinvoices')->where('id', $invoice_id)->update(['notes' => $message]);
				header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $invoice_id);
			
			} else {
				addInvoicePayment($invoice_id, $verify_request_id, $amount, 0, $gatewayParams['paymentmethod']);
				$message = digidargah_get_filled_message($gatewayParams['success_massage'], $verify_request_id, $invoice_id);
				logTransaction($gatewayParams['name'], ["GET" => $_GET, "POST" => $_POST, "result" => $response], 'Success');
				Capsule::table('tblinvoices')->where('id', $invoice_id)->update(['notes' => $message]);
				header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $invoice_id);
			}
		
		}
	}
}

function digidargah_MetaData(){
    return array(
        'DisplayName' => 'پرداخت رمز ارزی دیجی درگاه',
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
			"Description" => 'برای ایجاد و دریافت کلید API لطفا به <a href="https://digidargah.com/cryptosite" target="_blank"> مجموعه دیجی درگاه </a> مراجعه نمایید.'
        ],
        "pay_currency" => [
            "FriendlyName" => 'ارزهای قابل انتخاب',
            "Type" => 'text',
			"Value" => '',
			"Description" => 'در صورتی که تمایل دارید مشتریان را محدود به پرداخت از طریق یک یا چند رمز ارز خاص کنید، می توانید از طریق این فیلد، نام رمزارزها را وارد نمایید. در صورت تمایل به ورود بیش از یک رمزارز، لطفا آنها را توسط خط تیره ( dash ) از هم جدا کنید. ( مثال : bitcoin-dogecoin-ethereum ) با خالی گذاشتن این فیلد، مشتریان امکان پرداخت از طریق تمامی رمزارزهای فعال در مجموعه دیجی درگاه را خواهند داشت.'
        ],
        "success_massage" => [
            "FriendlyName" => 'پیام پرداخت موفق',
            "Type" => 'textarea',
            "Value" => 'پرداخت شما با موفقیت انجام شد. <br><br> شماره فاکتور : {invoice_id} <br> کد رهگیری درگاه پرداخت : {request_id}',
            "Description" => 'از طریق این فیلد می توانید متن پیامی را که می خواهید بعد از پرداخت موفق به مشتری نمایش داده شود تنظیم نمایید. همچنین می توانید از عبارت های کلیدی {invoice_id} برای نمایش شماره فاکتور و {request_id} برای نمایش کد رهگیری دیجی درگاه استفاده نمایید.'
        ],
        "failed_massage" => [
            "FriendlyName" => 'پیام پرداخت ناموفق',
            "Type" => 'textarea',
            "Value" => 'متاسفانه پرداخت شما با موفقیت انجام نشد. <br><br> شماره فاکتور : {invoice_id} <br> کد رهگیری درگاه پرداخت : {request_id}',
            "Description" => 'از طریق این فیلد می توانید متن پیامی را که می خواهید بعد از پرداخت ناموفق به مشتری نمایش داده شود تنظیم نمایید. همچنین می توانید از عبارت های کلیدی {invoice_id} برای نمایش شماره فاکتور و {request_id} برای نمایش کد رهگیری دیجی درگاه استفاده نمایید.'
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
