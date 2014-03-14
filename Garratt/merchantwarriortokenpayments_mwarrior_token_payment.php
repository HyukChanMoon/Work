<?php



class MerchantWarriorTokenPayments_Mwarrior_Token_Payment extends Shop_PaymentType

{

	

	public function get_info()

	{

		return array(

			'name'=>'Merchant Warrior Server-To-Server Integration with Token Payments',

			'description'=>'Record customer credit card details to be charged at a later date'

		);

	}

	

	/**

	* Validates configuration data before it is saved to database

	* Use host object field_error method to report about errors in data:

	* $host_obj->field_error('max_weight', 'Max weight should not be less than Min weight');

	* @param $host_obj ActiveRecord object containing configuration fields values

	*/

	public function validate_config_on_save($host_obj)

	{



	}

	

	/**

	* @param $host_obj ActiveRecord object to add fields to

	* @param string $context Form context. In preview mode its value is 'preview'

	*/

	public function build_config_ui($host_obj, $context = null)

	{



		$host_obj->add_field('test_mode', 'Sandbox Mode')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Use the Merchant Warrior Test Environment to try out Website Payments.', 'above');



		if ($context !== 'preview')

		{

			$host_obj->add_field('merchant_id', 'Merchant UUID')->tab('Configuration')->renderAs(frm_text)->comment('Include your Merchant Warrior UUID number here.', 'above')->validation()->fn('trim')->required('Please provide Merchant UUID.');

			$host_obj->add_field('merchant_apikey', 'Merchant API Key')->tab('Configuration')->renderAs(frm_text)->comment('Include your Merchant Warrior API Key number here.', 'above')->validation()->fn('trim')->required('Please provide Merchant API Key.');								

			$host_obj->add_field('merchant_passphrase', 'Merchant API Pass Phrase')->tab('Configuration')->renderAs(frm_text)->comment('Include your Merchant Warrior API Pass Phrase number here.', 'above')->validation()->fn('trim')->required('Please provide Merchant API Pass Phrase.');

		}



		//$host_obj->add_field('transaction_type', 'Transaction Type')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of credit card transaction you want to perform.', 'above');

		$host_obj->add_field('order_status', 'Order Status')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above', true);

	}

   

	public function get_order_status_options($current_key_value = -1)

	{

		if ($current_key_value == -1)

			return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');



		return Shop_OrderStatus::create()->find($current_key_value)->name;

	}

	

		

	public function process_payment_form($data, $host_obj, $order, $back_end = false)

	{

		/*

		* Validate input data

		*/

		$validation = new Phpr_Validation();

		$validation->add('FIRSTNAME', 'Cardholder first name')->fn('trim')->required('Please specify a cardholder first name.');

		$validation->add('LASTNAME', 'Cardholder last name')->fn('trim')->required('Please specify a cardholder last name.');

		$validation->add('EXPDATE_MONTH', 'Expiration month')->fn('trim')->required('Please specify a card expiration month.')->regexp('/^[0-9]*$/', 'Credit card expiration month can contain only digits.');

		$validation->add('EXPDATE_YEAR', 'Expiration year')->fn('trim')->required('Please specify a card expiration year.')->regexp('/^[0-9]*$/', 'Credit card expiration year can contain only digits.');

		$validation->add('PHONE', 'Phone number')->fn('trim')->required('Please specify a phone number.');



		$validation->add('ACCT', 'Credit card number')->fn('trim')->required('Please specify a credit card number.')->regexp('/^[0-9]*$/', 'Please specify a valid credit card number. Credit card number can contain only digits.');

		$validation->add('CVV2', 'CVV2')->fn('trim')->required('Please specify CVV2 value.')->regexp('/^[0-9]*$/', 'Please specify a CVV2 number. CVV2 can contain only digits.');



		try

		{

			if (!$validation->validate($data))

				$validation->throwException();

		} catch (Exception $ex)

		{

			$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), array(), null);

			throw $ex;

		}



		//Add card to Merchant Warrior

		if(isset($data['ONRECORD']) && $order->status->code == 'preorder')

		{

			$this->captureCCDetails($data, $host_obj, $order, $back_end, $validation);

		}

		else if($order->status->code != 'preorder')

		{

			$this->doStandardOrder($data, $host_obj, $order, $back_end, $validation);

		}

		//parent::process_payment_form($data, $host_obj, $order, $back_end);

	}

	

	protected function doStandardOrder($data, $host_obj, $order, $back_end, $validation)

	{

		$endpoint = $host_obj->test_mode ? 

			"https://base.merchantwarrior.com/post/" : 

			"https://api.merchantwarrior.com/post/";



		$fields = array();

		$response = null;

		$response_fields = array();

		$currency = Shop_CurrencySettings::get();



		try

		{

			$validation->fieldValues['EXPDATE_MONTH'] = (int)$validation->fieldValues['EXPDATE_MONTH'];

			$validation->fieldValues['EXPDATE_YEAR'] = (int)$validation->fieldValues['EXPDATE_YEAR'];



			$expMonth = $validation->fieldValues['EXPDATE_MONTH'] < 10 ? '0'.$validation->fieldValues['EXPDATE_MONTH'] : $validation->fieldValues['EXPDATE_MONTH'];

			$expYear = $validation->fieldValues['EXPDATE_YEAR'] > 2000 ? $validation->fieldValues['EXPDATE_YEAR'] - 2000 : $validation->fieldValues['EXPDATE_YEAR'];

			if ($expYear < 10)

				$expYear = '0'.$expYear;



			$userIp = Phpr::$request->getUserIp();



			$postData['method'] = 'processCard'; 

			$postData['merchantUUID'] = $host_obj->merchant_id; 

			$postData['apiKey'] = $host_obj->merchant_apikey; 

			$postData['transactionAmount'] = $order->total; 

			$postData['transactionCurrency'] = 'aud'; 

			$postData['transactionProduct'] = $order->id; 

			$postData['customerName'] = $order->billing_first_name.' '.$order->billing_last_name; 

			$postData['customerCountry'] = $order->billing_country->code; 

			$postData['customerState'] = $order->billing_state->code; 

			$postData['customerCity'] = $order->billing_city; 

			$postData['customerAddress'] = $order->billing_street_addr;

			$postData['customerPostCode'] = $order->billing_zip; 

			$postData['customerPhone'] = $validation->fieldValues['PHONE']; 

			$postData['customerEmail'] = $order->billing_email; 

			$postData['customerIP'] = $userIp; 

			$postData['paymentCardNumber'] = $validation->fieldValues['ACCT'];

			$postData['paymentCardName'] = $validation->fieldValues['FIRSTNAME'].' '.$validation->fieldValues['LASTNAME'];

			$postData['paymentCardExpiry'] = $expMonth.$expYear;

			$postData['hash'] = $this->calculateHash($postData, $host_obj->merchant_passphrase, $host_obj->merchant_id);



			$fields = $postData;



			/*

			 * Post request

			 */



			$response = $this->post_data($endpoint, $fields);



			/*

			 * Process result

			 */

			$response_fields = $this->parse_response($response);



			// Check for a valid response code

			if (!isset($response_fields['responseCode']))

			{

				throw new Phpr_ApplicationException("API Response did not contain a valid responseCode.");

			} 



			// Validate the response - the only successful code is 0

			$status = ((int)$response_fields['responseCode'] === 0) ? true : false;



			// Set an error message if the transaction failed

			if ($status === false)

			{

				throw new Phpr_ApplicationException("Hello Error: Payment Processor declined transaction: ".$response_fields['responseMessage']);

			}



			// DEBUG

			//throw new Phpr_ApplicationException(print_r($response_fields,true).strlen($response_fields['responseCode']));

			/*

			 * Successful payment. Set order status and mark it as paid.

			 */



			$this->log_payment_attempt($order, 'Successful payment', 1, $this->prepare_fields_log($fields), $this->prepare_fields_log($response_fields), $this->prepare_response_log($response));



			Shop_OrderStatusLog::create_record($host_obj->order_status, $order);

			$order->set_payment_processed();

		}

		catch (Exception $ex)

		{

			$fields = $this->prepare_fields_log($fields);



			$error_message = $ex->getMessage();

			if (isset($response_fields['messageText']))

				$error_message = strip_tags(str_replace('<br>', ' ', $response_fields['messageText']));



			$this->log_payment_attempt($order, $error_message, 0, $fields, $this->prepare_fields_log($response_fields), $this->prepare_response_log($response));



			if (!$back_end)

				throw new Phpr_ApplicationException($ex->getMessage());

			else

				throw new Phpr_ApplicationException($error_message);

		}

	}

	

	protected function captureCCDetails($data, $host_obj, $order, $back_end, $validation)

	{

		/*

		* Send request

		*/

	   @set_time_limit(3600);



	   $endpoint = $host_obj->test_mode ? 

		   "https://base.merchantwarrior.com/token/" : 

		   "https://api.merchantwarrior.com/token/";



	   $fields = array();

	   $response = null;

	   $response_fields = array();

	   $currency = Shop_CurrencySettings::get();



	   try

	   {

		   $validation->fieldValues['EXPDATE_MONTH'] = (int)$validation->fieldValues['EXPDATE_MONTH'];

		   $validation->fieldValues['EXPDATE_YEAR'] = (int)$validation->fieldValues['EXPDATE_YEAR'];



		   $expMonth = $validation->fieldValues['EXPDATE_MONTH'] < 10 ? '0'.$validation->fieldValues['EXPDATE_MONTH'] : $validation->fieldValues['EXPDATE_MONTH'];

		   $expYear = $validation->fieldValues['EXPDATE_YEAR'] > 2000 ? $validation->fieldValues['EXPDATE_YEAR'] - 2000 : $validation->fieldValues['EXPDATE_YEAR'];

		   if ($expYear < 10)

			   $expYear = '0'.$expYear;



		   $userIp = Phpr::$request->getUserIp();



		   $postData['method'] = 'addCard'; 

		   $postData['merchantUUID'] = $host_obj->merchant_id; 

		   $postData['apiKey'] = $host_obj->merchant_apikey; 

		   $postData['cardName'] = $validation->fieldValues['FIRSTNAME'].' '.$validation->fieldValues['LASTNAME'];

		   $postData['cardNumber'] = $validation->fieldValues['ACCT'];

		   $postData['cardExpiryMonth'] = $expMonth;

		   $postData['cardExpiryYear'] = $expYear;

		   //$postData['hash'] = $this->calculateHash($postData, $host_obj->merchant_passphrase, $host_obj->merchant_id);



		   $fields = $postData;



		   /*

			* Post request

			*/



		   $response = $this->post_data($endpoint, $fields);

		   

		   /*

			* Process result

			*/

		   $response_fields = $this->parse_response($response);

		   $customer = $order->customer;



		   // Check for a valid response code

		   if (!isset($response_fields['responseCode']))

		   {

			   throw new Phpr_ApplicationException("API Response for token payments did not contain a valid responseCode.");

		   } 



		   // Validate the response - the only successful code is 0

		   $status = ((int)$response_fields['responseCode'] === 0) ? true : false;



		   // Set an error message if the transaction failed

		   if ($status === false)

		   {

			   throw new Phpr_ApplicationException("Hello Error: Payment Processor declined transaction: ".$response_fields['responseMessage']);

		   }



		   $customer->x_merchantwarriortokenpayments_cardID = $response_fields['cardID'];

		   $customer->x_merchantwarriortokenpayments_cardKey = $response_fields['cardKey'];

		   $customer->x_merchantwarriortokenpayments_ivrCardID = $response_fields['ivrCardID'];

		   $customer->save();

		   

		   $order->x_merchantwarriortokenpayments_has_token = 1;

		   $order->save();



		   // DEBUG

		   //throw new Phpr_ApplicationException(print_r($response_fields,true).strlen($response_fields['responseCode']));				

		   $this->log_payment_attempt($order, 'Card added to Merchant Warrior', 0, $this->prepare_fields_log($fields), $this->prepare_fields_log($response_fields), $this->prepare_response_log($response));



		   Shop_OrderStatusLog::create_record($host_obj->order_status, $order);

		   $order->status_id = Shop_OrderStatus::get_by_code('preorderstored')->id;

		   $order->save();

		   //$order->set_payment_processed();

	   }

	   catch (Exception $ex)

	   {

		   $fields = $this->prepare_fields_log($fields);



		   $error_message = $ex->getMessage();

		   if (isset($response_fields['messageText']))

			   $error_message = strip_tags(str_replace('<br>', ' ', $response_fields['messageText']));



		   $this->log_payment_attempt($order, $error_message, 0, $fields, $this->prepare_fields_log($response_fields), $this->prepare_response_log($response));



		   if (!$back_end)

			   throw new Phpr_ApplicationException($ex->getMessage());

		   else

			   throw new Phpr_ApplicationException($error_message);

	   }

	}

	

	function calculateHash(array $postData = array(), $passphrase, $uuid)

	{

		// Check the amount param

		if (!isset($postData['transactionAmount']) || !strlen($postData['transactionAmount']))

		{

			throw new Phpr_ApplicationException("Missing or blank amount field in postData array.");

		}



		// Check the currency param

		if (!isset($postData['transactionCurrency']) || !strlen($postData['transactionCurrency']))

		{

			throw new Phpr_ApplicationException("Missing or blank currency field in postData array.");

		}



		// Generate & return the hash			

		return md5(strtolower($passphrase . $uuid . $postData['transactionAmount'] . $postData['transactionCurrency']));

	}	

	

	private function post_data($endpoint, $fields)

	{

		$poststring = $this->format_form_fields($fields);



		// Setup CURL defaults

		$curl = curl_init();	

		curl_setopt($curl, CURLOPT_TIMEOUT, 60);

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);

		curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);

		curl_setopt($curl, CURLOPT_FORBID_REUSE, true);

		curl_setopt($curl, CURLOPT_HEADER, false);

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);



		// Setup CURL params for this request

		curl_setopt($curl, CURLOPT_URL, $endpoint);

		curl_setopt($curl, CURLOPT_POST, true);

		curl_setopt($curl, CURLOPT_POSTFIELDS, $poststring);



		$response = curl_exec($curl);



		if (curl_errno($curl))

			throw new Phpr_ApplicationException( "Error connecting the payment gateway: ".curl_error($curl) );

		else

			curl_close($curl);



		return $response;

	}



	private function format_form_fields(&$fields)

	{

		$result = array();

		foreach($fields as $key=>$val)

			$result[] = urlencode($key)."=".urlencode($val); 



		return implode('&', $result);

	}

	

	private function parse_response($response)

	{

		// Parse the XML

		$doc = new DOMDocument();

		try 

		{

			$doc->loadXML($response);

		} catch (Exception $ex)

		{

			throw new Phpr_ApplicationException('Invalid payment gateway response.');

		}



		return Core_Xml::to_plain_array($doc, true);

	}

	

	private function prepare_fields_log($fields)

	{

		if (isset($fields['merchant_id']))

			unset($fields['merchant_id']);



		if (isset($fields['merchant_apikey']))

			unset($fields['merchant_apikey']);



		if (isset($fields['merchant_passphrase']))

			unset($fields['merchant_passphrase']);				



		if (isset($fields['trnCardNumber']))

			$fields['trnCardNumber'] = '...'.substr($fields['trnCardNumber'], -4);



		if (isset($fields['trnCardCvd']))

			unset($fields['trnCardCvd']);



		return $fields;

	}



	private function prepare_response_log($response)

	{

		return $response;

	}



}



?>