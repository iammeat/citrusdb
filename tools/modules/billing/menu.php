<?php
$exportcc_url = "$ssl_url_prefix" . "index.php?load=exportcc&type=tools";
$refundcc_url = "$ssl_url_prefix" . "index.php?load=refundcc&type=tools";
$importcc_url = "index.php?load=importcc&type=tools";

echo "<div class=toolblock style=\"height: 200px;\">
<b>$l_billing</b>
<br>

<div class=icon>
<a href=# onclick = \"popupPage('index.php?load=billing&tooltype=module&type=tools&importnew=on'); return false;\">
<img border=0 src=\"images/icons/citrus_importnew.png\"><br>
$l_importnewaccounts</a></div>

<div class=icon>
<a href=# onclick = \"popupPage('$exportcc_url'); return false;\">
<img border=0 src=\"images/icons/citrus_creditcards.png\"><br>
$l_exportcreditcards</a></div>

<div class=icon>
 <a href=# onclick = \"popupPage('$importcc_url'); return false;\">
<img border=0 src=\"images/icons/citrus_creditcards.png\"><br>
$l_importcreditcards</a></div>

<div class=icon>
 <a href=# onclick = \"popupPage('index.php?load=billing&tooltype=module&type=tools&importccupdates=on'); return false;\">
<img border=0 src=\"images/icons/citrus_creditcards.png\"><br>
$l_importccupdates</a></div>

<div class=icon>
 <a href=# onclick = \"popupPage('index.php?load=invoice&type=tools'); return false;\">
<img border=0 src=\"images/icons/citrus_print.png\"><br>
$l_printinvoices</a></div>

<div class=icon>
 <a href=# onclick = \"popupPage('index.php?load=billing&tooltype=module&type=tools&einvoice=on'); return false;\">
<img border=0 src=\"images/icons/citrus_email.png\"><br>
$l_emailinvoices</a></div>

<div class=icon>
<a href=# onclick = \"popupPage('index.php?load=payment&type=tools'); return false;\">
<img border=0 src=\"images/icons/citrus_money.png\"><br>
$l_enterpayments</a></div>

<div class=icon>
<a href=# onclick = \"popupPage('$refundcc_url'); return false;\">
<img border=0 src=\"images/icons/citrus_creditcards.png\"><br>
$l_refund</a></div>

<!--
<div class=icon>
<a href=# onclick = \"popupPage('index.php?load=reminder&type=tools'); return false;\">
<img border=0 src=\"images/icons/citrus_reminders.png\"><br>
$l_sendreminders</a></div>
-->

<!--
<div class=icon>
 <a href=# onclick = \"popupPage('index.php?load=invmaint&type=tools'); return false;\">
<img border=0 src=\"images/icons/citrus_invmaint.png\"><br>
$l_invoicemaintenance</a></div>
-->

</div>
";
?>

