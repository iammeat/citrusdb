<?php
// Copyright (C) 2002-2006  Paul Yasi <paul@citrusdb.org>
// Read the README file for more information
/*----------------------------------------------------------------------------*/
// Check for authorized accesss
/*----------------------------------------------------------------------------*/
if(constant("INDEX_CITRUS") <> 1){
	echo "You must be logged in to run this.  Goodbye.";
	exit;	
}

if (!defined("INDEX_CITRUS")) {
	echo "You must be logged in to run this.  Goodbye.";
        exit;
}

// include the billing functions
//include('include/billing.inc.php');

//GET Variables
$invoiceid = $base->input['invoiceid'];

require('./include/fpdf.php');
$pdf = new FPDF();

// print the invoice
$pdf = outputinvoice($DB, $invoiceid, $lang, "pdf", $pdf);

$pdf->Output();

echo "printing pdf";

