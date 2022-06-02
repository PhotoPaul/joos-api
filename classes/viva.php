<?

class Viva {
	function __construct(){
		$this->authentication = VIVA_MERCHANT_ID.':'.VIVA_API_KEY;
	}

	function vivaCreateOrder($params){
        global $prod;
        if(!$prod) {
            // Dev
			global $appUrl;
            return new AjaxResponse($appUrl);
        }

        $messageForTheCustomer = urlencode($params->orderModel->CustomerTrns);
        $postargs = 'SourceCode=1899&Amount='.urlencode($params->orderModel->Amount*100).'&RequestLang='.$params->orderModel->RequestLang.'&DisableIVR=true&DisableCash=true&CustomerTrns='.$messageForTheCustomer.'&PaymentTimeOut=7776000';

		$session = curl_init("https://www.vivapayments.com/api/orders");
		curl_setopt($session, CURLOPT_POST, true);
		curl_setopt($session, CURLOPT_POSTFIELDS, $postargs);
		curl_setopt($session, CURLOPT_HEADER, true);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($session, CURLOPT_USERPWD, $this->authentication);
		curl_setopt($session, CURLOPT_SSL_CIPHER_LIST, 'TLSv1');

		$response = curl_exec($session);
		$header_size = curl_getinfo($session, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $header_size);
		$body = substr($response, $header_size);
		curl_close($session);

		$retCreateOrder = json_decode($body);
		if(!isset($retCreateOrder) || $retCreateOrder->ErrorCode != 0){
			return new AjaxResponse(null, false, 'json_decode failed');
		} else {
			return new AjaxResponse("https://www.vivapayments.com/web/newtransaction.aspx?ref=".$retCreateOrder->OrderCode);
			// header("Location:https://www.vivapayments.com/web/newtransaction.aspx?ref=".$retCreateOrder->OrderCode);
		}
	}
}

?>