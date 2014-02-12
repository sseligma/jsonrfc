<?php

include('JsonRfcLookup.php');

$jsonParams = $_POST['jsonParams'];

$results = JsonRfcLookup::lookup($jsonParams);
echo json_encode($results);
?>