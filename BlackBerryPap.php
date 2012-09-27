<?php
class BlackBerryPap {
	protected $_appId;
	protected $_password;
	protected $_contentProviderId;
	protected $_environment;

	public function __construct($appId, $password) {
		$this->_appId = $appId;
		$this->_password = $password;
	}

	public function setAppId($appId) {
		$this->_appId = $appId;
		return $this;
	}

	public function getAppId() {
		return $this->_appId;
	}

	public function setPassword($password) {
		$this->_password = $password;
		return $this;
	}

	public function getPassword() {
		return $this->_password;
	}

	public function setContentProviderId($contentProviderId) {
		$this->_contentProviderId = $contentProviderId;
		return $this;
	}
	public function getContentProviderId() {
		return $this->_contentProviderId;
	}
	
	public function setEnvironment($environment) {
		$this->_environment = $environment;
		return $this;
	}
	
	public function getEnvironment() {
		return $this->_environment;
	}

	public function push(BlackBerryMessage $m) {
		$ch = curl_init();	
		if ($this->_environment == 'dev')
			curl_setopt($ch, CURLOPT_URL,
					"https://pushapi.eval.blackberry.com/mss/PD_pushRequest");
		else if ($this->_environment == 'prod')	
			curl_setopt($ch, CURLOPT_URL,
					"https://cp" . $this->_contentProviderId
							. ".pushapi.na.blackberry.com/mss/PD_pushRequest");
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_USERAGENT, "PHP BB Push Server/1.0");
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD,
				$this->_appId . ':' . $this->_password);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $m->getPushMessage($this->_appId));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER,
				array(
						"Content-Type: multipart/related; boundary=mPsbVQo0a68eIL3OAxnm; type=application/xml",
						"Accept: text/html, image/gif, image/jpeg, *; q=.2, */*; q=.2",
						"Connection: keep-alive"));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		
		$response = curl_exec($ch);
		curl_close($ch);

		return new BlackBerryResponse($response);
	}

}

class BlackBerryMessage {
	protected $_to = array();
	protected $_id;
	protected $_message;
	protected $_delivery;

	public function __construct($message, $id = null, $delivery = null) {
		$this->_message = $message;
		$this->_id = ($id) ? $id : microtime();
		if ($delivery) {
			$this->_delivery = (is_int($delivery)) ? $delivery
					: strtotime($delivery);
		} else {
			$this->_delivery = strtotime("+5 minutes");
		}
	}

	public function addTo($email) {
		$this->_to[] = $email;
	}

	public function getPushMessage($appId) {
		$addresses = '';
		foreach ($this->_to as $addr) {
			$addresses .= '<address address-value="' . $addr . '"/>';
		}

		$xml = '--mPsbVQo0a68eIL3OAxnm' . "\r\n"
				. 'Content-Type: application/xml; charset=UTF-8' . "\r\n\r\n"
				. '<?xml version="1.0"?>
            <!DOCTYPE pap PUBLIC "-//WAPFORUM//DTD PAP 2.1//EN" "http://www.openmobilealliance.org/tech/DTD/pap_2.1.dtd">
            <pap>
            <push-message push-id="' . $this->_id
				. '" deliver-before-timestamp="'
				. gmdate('Y-m-d\TH:i:s\Z', $this->_delivery)
				. '" source-reference="' . $appId . '">' . $addresses
				. '<quality-of-service delivery-method="unconfirmed"/>
            </push-message>
            </pap>' . "\r\n" . '--mPsbVQo0a68eIL3OAxnm' . "\r\n"
				. 'Content-Type: text/plain' . "\r\n" . 'Push-Message-ID: '
				. $this->_id . "\r\n\r\n" . urlencode($this->_message) . "\r\n"
				. '--mPsbVQo0a68eIL3OAxnm--' . "\n\r";

		return $xml;
	}
}

class BlackBerryResponse {
	protected $_id;
	protected $_replyTime;
	protected $_responseCode;
	protected $_responseDesc;
	protected $_isError = false;
	protected $_errorCode;
	protected $_errorStr;

	public function __construct($response) {
		$p = xml_parser_create();
		xml_parse_into_struct($p, $response, $vals);
		$err = xml_get_error_code($p);
		if ($err > 0) {
			$this->_isError = true;
			$this->_errorCode = $err;
			$this->_errorStr = xml_error_string($err);
		} else {
			$this->_replyTime = $vals[1]['attributes']['REPLY-TIME'];
			$this->_responseCode = $vals[2]['attributes']['CODE'];
			$this->_responseDesc = $vals[2]['attributes']['DESC'];
			$this->_id = $vals[1]['attributes']['PUSH-ID'];
		}
		xml_parser_free($p);
	}

	public function getId() {
		return $this->_id;
	}

	public function getReplyTime() {
		return $this->_replyTime;
	}

	public function getResponseCode() {
		return $this->_responseCode;
	}

	public function getResponseDesc() {
		return $this->_responseDesc;
	}

	public function isError() {
		return $this->_isError;
	}

	public function getErrorCode() {
		return $this->_errorCode;
	}

	public function getErrorString() {
		return $this->_errorStr;
	}

}
