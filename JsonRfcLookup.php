<?php

include('JsonRfc.php');

Class JsonRfcLookup extends JsonRfc {

	// Allowed delimiters for concatening multiple fields to a label
	private static $delimiters = array (',',' ','&');
	
	public static function lookup($jsonParams) {
		$rfcResults = JsonRfc::execute($jsonParams,null,JsonRfc::$FORMAT_ARRAY);
		if ($rfcResults['error']) {
			return null;
		}
		else {		
			$lookupParams = json_decode($jsonParams,true);
			$lookupTable = $lookupParams['lookupTable'];
			$label = $lookupParams['label'];
			$value = $lookupParams['value'];
			
			$results = array();
			
			foreach($rfcResults['tables'][$lookupTable] as $row) {
				if (is_array($label)) {
					$labelString = "";
					foreach($label as $l) {
						if (in_array($l,JsonRfcLookup::$delimiters)) {
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
			
			return $results;
		}
	}
	// end lookup
}

?>