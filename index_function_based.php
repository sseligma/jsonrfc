<?php


$jsonParams = null;
$rfcParams = array();
$errors = array();
$jsonResults = null;
$config = array();

// Check api key
if (check_api_key($errors)) {
	
	if (parse_rfc_params($rfcParams,$errors)) {
		get_config($config);
		if (call_rfc($config,$rfcParams,$jsonResults,$errors)) {
			echo $jsonResults;
		}
		
	}	

	if (count($errors)) {
		$results = array();
		$results['error'] = true;
		$results['errorMessage'] = array();
		foreach($errors as $error) {
			array_push($results['errorMessage'],array("TYPE" => "E", "MESSAGE" => $error));
		}
		$jsonResults = json_encode($results);
		echo $jsonResults;
	}
	
}

function check_api_key(&$errors) {
	$api_key = null;

	if (isset($_POST['api_key'])) {
		$api_key = $_POST['api_key'];
	}
	
	if ($api_key != getenv('SAP_API_KEY')) {
		$errors[] = "Invalid Api Key";
		return false;
	}

	return true;	
}

function parse_rfc_params(&$rfcParams,&$errors) {
	if (isset($_POST['jsonParams'])) {
		$jsonParams = $_POST['jsonParams'];
		$rfcParams = json_decode($jsonParams,true);
		if ($rfcParams == NULL) {
			$errors[] = "Invalid json";
			return false;
		}
		else {
			if (isset($rfcParams['rfcName'])) {
				$rfcParams['rfcName'] = strtoupper($rfcParams['rfcName']);
				return true;
			}
			else {
				$errors[] = "No RFC specified";
				return false;
			}
		}
	}
	else {
		$errors[] = "Json params missing";		
		return false;
	}	
}

function get_config(&$config) {
	if (isset($_POST['x509'])) {
		$x509 = $_POST['x509'];
	}
	// else get the x509 cert from the server environment variables
	else {
		$x509 = $_SERVER['SSL_CLIENT_CERT'];
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
	
	$config = array(
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

function call_rfc(&$config,&$rfcParams,&$jsonResults,&$errors) {
	$params = array(); // array of params to send to the rfc
	
	if (isset($rfcParams['input'])) {
		$input = $rfcParams['input'];
		foreach($input as $key => $value) {
			if (is_object($value)) {
				$value = (array)$value;
			}
			$params[$key] = $value;
		}
		
	}
		
	if (isset($rfcParams['tables'])) {
		$tables = $rfcParams['tables'];
		foreach($tables as $key => $value) {
			$params[$key] = $value;
		}
		
	}
	
	// we must have a valid connection
	try {
		$conn = new sapnwrfc($config);
		$fds = $conn->function_lookup($rfcParams['rfcName']);
		$results = $fds->invoke($params);
		trim_array($results);
	
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
	
		$jsonResults = json_encode($data);
		$conn->close();
		return true;
	}
	catch (sapnwrfcConnectionException $e) {
		$message = $e->getMessage();
		$errors[] = $message;
		return false;
	}
	
}

function trim_array(&$array) {
	$keys = array_keys($array);
	for($i = 0; $i < count($keys); $i++) {
		$k = $keys[$i];
		if (is_array($array[$k])) {
			trim_array($array[$k]);
		}
		else {
			$array[$k] = trim($array[$k]);
		}
	}
}
?>