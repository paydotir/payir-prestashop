<?php

if (defined('_PS_VERSION_') == FALSE) {

	die('This file cannot be accessed directly');
}

class payir extends PaymentModule {

	private $_html = '';
	private $_postErrors = array();

	public function __construct() {

		$this->name             = 'payir';
		$this->tab              = 'payments_gateways';
		$this->version          = '1.0';
		$this->author           = 'Pay.ir';
		$this->currencies       = TRUE;
		$this->currencies_mode  = 'radio';

		parent::__construct();

		$this->displayName      = 'Pay.ir Payment Module';
		$this->description      = 'Online Payment with Pay.ir';
		$this->confirmUninstall = 'Are you sure you want to delete your details?';

		if (!sizeof(Currency::checkPaymentCurrencies($this->id))) {

			$this->warning = 'No currency has been set for this module.';
		}

		$config = Configuration::getMultiple(array('payir_api'));

		if (!isset($config['payir_api'])) {

			$this->warning = 'You have to enter your Pay.ir API key key to use Pay.ir for your online payments.';
		}
	}

	public function install() {

		if (!parent::install() || !Configuration::updateValue('payir_api', '') || !Configuration::updateValue('payir_logo', '') || !Configuration::updateValue('payir_hash', $this->hash_key()) || !$this->registerHook('payment') || !$this->registerHook('paymentReturn')) {

			return FALSE;

		} else {

			return TRUE;
		}
	}

	public function uninstall() {

		if (!Configuration::deleteByName('payir_api') || !Configuration::deleteByName('payir_logo') || !Configuration::deleteByName('payir_hash') || !parent::uninstall()) {

			return FALSE;

		} else {

			return TRUE;
		}
	}

	public function hash_key() {

		$en = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');

		$one   = rand(1, 26);
		$two   = rand(1, 26);
		$three = rand(1, 26);

		return $hash = $en[$one] . rand(0, 9) . rand(0, 9) . $en[$two] . $en[$tree] . rand(0, 9) . rand(10, 99);
	}

	public function getContent() {

		if (Tools::isSubmit('payir_setting')) {

			Configuration::updateValue('payir_api', $_POST['payir_api']);
			Configuration::updateValue('payir_logo', $_POST['payir_logo']);

			$this->_html .= '<div class="conf confirm">' . 'Settings Updated' . '</div>';
		}

		$this->_generateForm();

		return $this->_html;
	}

	private function _generateForm() {

		$this->_html .= '<div align="center">';
		$this->_html .= '<form action="' . $_SERVER['REQUEST_URI'] . '" method="post">';
		$this->_html .= 'Enter Your API Key' . '<br/>';
		$this->_html .= '<input type="text" name="payir_api" value="' . Configuration::get('payir_api') . '" ><br/><br/>';
		$this->_html .= '<input type="submit" name="payir_setting" value="' . 'Save' . '" class="button" />';
		$this->_html .= '</form>';
		$this->_html .= '</div>';
	}
	

	public function do_payment($cart) {

		if (extension_loaded('curl')) {

			$server   = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__;
			$amount   = floatval(number_format($cart->getOrderTotal(true, 3), 2, '.', ''));
			$address  = new Address(intval($cart->id_address_invoice));
			$mobile   = isset($address->phone_mobile) ? $address->phone_mobile : NULL;
			$api_key  = Configuration::get('payir_api');
			$currency_id = $cart->id_currency;

			foreach(Currency::getCurrencies() as $key => $currency){
				if ($currency['id_currency'] == $currency_id){
					$currency_iso_code = $currency['iso_code'];
				}
			}
			
			if ($currency_iso_code != 'IRR'){
				$amount = $amount * 10;
			}

			$callback = $server . 'modules/payir/process.php?do=call_back&id=' . $cart->id . '&amount=' . $amount.'&currency_id='.$currency_id.'&iso_code='.$currency_iso_code;

		
			$params = array(

				'api'          => $api_key,
				'amount'       => $amount,
				'redirect'     => urlencode($callback),
				'mobile'       => $mobile,
				'factorNumber' => $cart->id,
				'description'  => 'پرداخت سفارش شماره ' . $cart->id,
			);
		
			$result = self::common('https://pay.ir/payment/send', $params);
		
			if ($result && isset($result->status) && $result->status == 1) {

				$cookie = new Cookie('order');
				$cookie->setExpire(time() + 20 * 60);
				$cookie->hash = md5($cart->id . $amount . Configuration::get('payir_hash'));
				$cookie->write();

				$gateway_url = 'https://pay.ir/payment/gateway/' . $result->transId;

				Tools::redirect($gateway_url);

			} else {

				$message = 'در ارتباط با وب سرویس Pay.ir خطایی رخ داده است';
				$message = isset($result->errorMessage) ? $result->errorMessage : $message;

				echo $this->error($message);
			}

		} else {

			echo $this->error('تابع cURL در سرور فعال نمی باشد');
		}
	}

	public function error($str) {

		return '<div class="alert error">' . $str . '</div>';
	}

	public function success($str) {

		echo '<div class="conf confirm">' . $str . '</div>';
	}

	public function hookPayment($params) {

		global $smarty;

		$smarty->assign('payir_logo', Configuration::get('payir_logo'));

		if ($this->active) {

			return $this->display(__FILE__, 'payir.tpl');
		}
	}

	public function hookPaymentReturn($params) {

		if ($this->active) {

			return NULL;
		}
	}

	public function common($url, $params)
	{
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

		$response = curl_exec($ch);
		$error    = curl_errno($ch);

		curl_close($ch);

		$output = $error ? FALSE : json_decode($response);

		return $output;
	}
}
