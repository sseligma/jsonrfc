<?php

include('JsonRfc.php');

// Allowed delimiters for concatening multiple fields to a label
$delimiters = array (',',' ','&');
 
$jsonParams = $_POST['jsonParams'];

$rfcResults = JsonRfc::execute($jsonParams,null,JsonRfc::$FORMAT_ARRAY);

$lookupParams = json_decode($jsonParams,true);

$lookupTable = $lookupParams['lookupTable'];
$label = $lookupParams['label'];
$value = $lookupParams['value'];

$results = array();

foreach($rfcResults['tables'][$lookupTable] as $row) {
	if (is_array($label)) {
		$labelString = "";
		foreach($label as $l) {
			if (in_array($l,$delimiters)) {
				$labelString = $labelString . $l;
			}
			else {
				$labelString = $labelString . $row[$l];
			}
		}	
	}
	else {
		$labelString = $row[$label];
	}
	array_push($results,array('value' => $row[$value], 'label' => $labelString));
}

echo json_encode($results);
?>