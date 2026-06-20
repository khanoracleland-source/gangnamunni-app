<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
$_GET["treatment_id"] = "1";
$_GET["hospital_id"] = "8093";
$_GET["format"] = "chart";
include __DIR__ . "/api_treatment_price_history.php";
