<?php

if (isset($_GET['do'])) {

	include (dirname(__FILE__) . '/../../config/config.inc.php');
	include (dirname(__FILE__) . '/../../header.php');
	include (dirname(__FILE__) . '/payir.php');

	$payir = new payir;

	if ($_GET['do'] == 'payment') {

		$payir->do_payment($cart);

	} else {

		if (isset($_GET['id']) && isset($_GET['amount']) && isset($_POST['status']) && isset($_POST['transId']) && isset($_POST['factorNumber'])&& isset($_GET['currency_id'])&& isset($_GET['iso_code'])) {

			$order  = htmlspecialchars($_GET['id']);
			$amount = htmlspecialchars($_GET['amount']);
			$currency_id = $_GET['currency_id'];
			$currency_iso_code = $_GET['iso_code'];
			
			$cookie = new Cookie('order');
			$cookie = $cookie->hash;
			

			if (isset($cookie) && $cookie) {

				$hash = md5($order . $amount . Configuration::get('payir_hash'));

				
				if ($hash == $cookie) {

					
					$status        = htmlspecialchars($_POST['status']);
					$trans_id      = htmlspecialchars($_POST['transId']);
					$factor_number = htmlspecialchars($_POST['factorNumber']);
					$message       = htmlspecialchars($_POST['message']);

					if (isset($status) && $status == 1) {

						$api_key = Configuration::get('payir_api');
						

						$params = array (

							'api'     => $api_key,
							'transId' => $trans_id
						);

						$result = $payir->common('https://pay.ir/payment/verify', $params);
						

						if ($result && isset($result->status) && $result->status == 1) {

							$card_number = isset($_POST['cardNumber']) ? htmlspecialchars($_POST['cardNumber']) : 'Null';


							if ($amount == $result->amount) {

								$customer = new Customer((int)$cart->id_customer);
								$currency = $context->currency;

								$message = 'تراکنش شماره ' . $trans_id . ' با موفقیت انجام شد. شماره کارت پرداخت کننده ' . $card_number;
								
								if ($currency_iso_code != 'IRR'){
									$amount = $amount / 10;
								}

								$payir->validateOrder((int)$order, _PS_OS_PAYMENT_, $amount, $payir->displayName, $message, array(), (int)$currency->id, false, $customer->secure_key);
								
							
								Tools::redirect('history.php');

							} else {

								echo $payir->error('رقم تراكنش با رقم پرداخت شده مطابقت ندارد');
							}

						} else {

							$message = 'در ارتباط با وب سرویس Pay.ir و بررسی تراکنش خطایی رخ داده است';
							$message = isset($result->errorMessage) ? $result->errorMessage : $message;

							echo $payir->error($message);
						}
						
					} else {

						$message = $message ? $message : 'تراكنش با خطا مواجه شد و یا توسط پرداخت کننده کنسل شده است';

						echo $payir->error($message);
					}

				} else {

					echo $payir->error('الگو رمزگذاری تراکنش غیر معتبر است');
				}

			} else {

				echo $payir->error('سفارش یافت نشد و یا نشست پرداخت منقضی شده است');
			}

		} else {

			echo $payir->error('اطلاعات ارسال شده مربوط به تایید تراکنش ناقص و یا غیر معتبر است');
		}
	}

	include (dirname(__FILE__) . '/../../footer.php');

} else {

	die('Something wrong');
}
