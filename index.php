<?php

Class JsonRfc {
	private static $errors = array();
	private static $jsonParams = null;
	private static $rfcParams = array();
	private static $jsonResults = null;
	private static $config = array();
	private static $format = null; // supported formats are 'json' and 'array' 
	
	private static $FORMAT_JSON = 0;
	private static $FORMAT_ARRAY = 1;
	
	private static function check_api_key() {
		$api_key = null;
	
		if (isset($_POST['api_key'])) {
			$api_key = $_POST['api_key'];
		}
	
		if ($api_key != getenv('SAP_API_KEY')) {
			JsonRfc::$errors[] = "Invalid Api Key";
			return false;
		}
	
		return true;
	}

	private static function get_config($x509) {
		if ($x509 == null) {
			if (isset($_POST['x509'])) {
				$x509 = $_POST['x509'];
			}
			// else get the x509 cert from the server environment variables
			else {
				$x509 = $_SERVER['SSL_CLIENT_CERT'];
			}
		}	
		if (isset($_POST['sapSystemId'])) {
			$sapSystemId = $_POST['sapSystemId'];
		}
		else {
			$sapSystemId = getenv('SAP_R3_DEFAULT');
		}
	
		$trace = getenv("SAP_TRACE"); // defaults is 0, specify a higher trace value to provide more debugging info
	
		$start_marker = "-----BEGIN CERTIFICATE-----";
		$end_marker  = "-----END CERTIFICATE-----";
	
		$x509 = str_replace($start_marker,"",$x509);
		$x509 = str_replace($end_marker,"",$x509);
	
		JsonRfc::$config = array(
				'mshost' => getenv($sapSystemId . "_MSHOST"),
				'sysnr' => getenv($sapSystemId . "_SYSNR"),
				'r3name'=> getenv($sapSystemId . "_R3NAME"),
				'group'=> getenv($sapSystemId . "_GROUP"),
				'client' => getenv($sapSystemId . "_CLIENT"),
				'X509CERT' => $x509,
				'lang' => getenv("SAP_LANG"),
				'snc_partnername'=> getenv($sapSystemId . "_SNC_PARTNERNAME"),
				'snc_mode'=> getenv("SAP_MODE"),
				'snc_qop'=> getenv("SAP_QOP"),
				'snc_lib'=>getenv("SAP_SNC_LIB"),
				'trace' => $trace,
				'toupper'=> getenv("SAP_TOUPPER")
		);
	}
	
	private static function parse_rfc_params($params) {
		if ($params) {
			JsonRfc::$jsonParams = $params;
		}
		elseif (isset($_POST['jsonParams'])) {
			JsonRfc::$jsonParams = $_POST['jsonParams'];
		}
		if (JsonRfc::$jsonParams) {
			JsonRfc::$rfcParams = json_decode(JsonRfc::$jsonParams,true);
			if (JsonRfc::$rfcParams == NULL) {
				JsonRfc::$errors[] = "Invalid json";
				return false;
			}
			else {
				if (isset(JsonRfc::$rfcParams['rfcName'])) {
					JsonRfc::$rfcParams['rfcName'] = strtoupper(JsonRfc::$rfcParams['rfcName']);
					return true;
				}
				else {
					JsonRfc::$errors[] = "No RFC specified";
					return false;
				}
			}
		}
		else {
			JsonRfc::$errors[] = "Json params missing";
			return false;
		}
	}
	
	private static function trim_array(&$array) {
		$keys = array_keys($array);
		for($i = 0; $i < count($keys); $i++) {
			$k = $keys[$i];
			if (is_array($array[$k])) {
				JsonRfc::trim_array($array[$k]);
			}
			else {
				$array[$k] = trim($array[$k]);
			}
		}
	}
	
	private static function call_rfc() {
		$params = array(); // array of params to send to the rfc
	
		if (isset(JsonRfc::$rfcParams['input'])) {
			$input = JsonRfc::$rfcParams['input'];
			foreach($input as $key => $value) {
				if (is_object($value)) {
					$value = (array)$value;
				}
				$params[$key] = $value;
			}
	
		}
	
		if (isset(JsonRfc::$rfcParams['tables'])) {
			$tables = $rfcParams['tables'];
			foreach($tables as $key => $value) {
				$params[$key] = $value;
			}
	
		}
	
		// we must have a valid connection
		try {
			$conn = new sapnwrfc(JsonRfc::$config);
			$fds = $conn->function_lookup(JsonRfc::$rfcParams['rfcName']);
			$results = $fds->invoke($params);
			JsonRfc::trim_array($results);
	
			// format the data to match the format from jsonrfc
			$data = array();
			$data['rfcOut'] = null;
			$data['tables'] = array();
			foreach($results as $key => $value) {
				$meta = $fds->$key;
				$type = $meta['type'];
				//echo "$key = $type";
				switch ($type) {
					case "RFCTYPE_STRUCTURE":
						$data['rfcOut'][$key] = $value;
						break;
	
					case "RFCTYPE_TABLE":
						$data['tables'][$key] = $value;
						break;
	
					default:
						$data['rfcOut'][$key] = $value;
						break;
				}
			}
	
			JsonRfc::$jsonResults = json_encode($data);
			$conn->close();
			return true;
		}
		catch (sapnwrfcConnectionException $e) {
			$message = $e->getMessage();
			JsonRfc::$errors[] = $message;
			return false;
		}
	
	}
	
	public static function execute($params=null,$x509=null,$format=0) {
		// if $params are provides, they can either be a JSON string or an array from a decoded jsonstring 
		// if $params are not provided, the execute method will try to get the JsonParams string from post
		if (JsonRfc::check_api_key()) {
			if (JsonRfc::parse_rfc_params($params)) {
				JsonRfc::get_config($x509);
				if (JsonRfc::call_rfc()) {
					return JsonRfc::$jsonResults;
				}
			
			}
		}
		
	}
}

echo JsonRfc::execute();

?>