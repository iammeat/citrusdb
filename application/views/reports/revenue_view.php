<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>
<h3><?php echo lang('revenuereport')?></h3>

<?php
// intialize totals
$total = 0;
$taxtotal = 0;
?>

<FORM ACTION="<?php echo $this->url_prefix?>/index.php/reports/revenue" METHOD="POST">
<?php echo lang('foritemsbilledduringthisperiod')?><p>
<table>
<?php echo lang('from')?>: <input type=text name="day1" value="<?php echo $day1?>"> - 
<?php echo lang('to')?>: <input type=text name="day2" value="<?php echo $day2?>">

<td><b><?php echo lang('organizationname')?></b></td>
<td><select name="organization_id">
<option value="all"><?php echo lang('showall')?></option>
<?php
foreach ($orglist as $myresult) {
	$myid = $myresult['id'];
	$myorg = $myresult['org_name'];
	echo "<option value=\"$myid\">$myorg</option>";
}
?>
</select></td><tr>

</td><tr> 
<td></td><td><br><input type=submit name="<?php echo lang('submit')?>" value="submit"></td>
</table>
</form>
<table><td><?php echo lang('service')?></td>
<td><?php echo lang('organizationname')?></td>
<td><?php echo lang('category')?></td>
<td><?php echo lang('billed')?></td>
<td><?php echo lang('paid')?></td><td></td>
<tr>
<?php
foreach ($servicerevenue AS $myresult) 
{
	$category_total = $myresult['CategoryTotal'];
	$category_billed = $myresult['CategoryBilled'];
	$service_description = $myresult['service_description'];
	$service_category = $myresult['service_category'];
	$count = $myresult['ServiceCount'];
	$org_name = $myresult['g_org_name'];
	echo "<td>$service_description</td><td>$org_name</td>
		<td>$service_category</td>
		<td>$category_billed</td><td>$category_total </td><td>($count)</td><tr>";

	// add this to the total
	$total = $total + $category_total;
}
?>

</table>
<p><table><td><?php echo lang('credit')?></td>
<td><?php echo lang('organizationname')?></td>
<td><?php echo lang('billed')?></td>
<td><?php echo lang('paid')?></td><td></td><tr>

<?php
foreach ($creditrevenue AS $myresult) 
{
	$category_total = $myresult['CategoryTotal'];
	$category_billed = $myresult['CategoryBilled'];
	$count = $myresult['ServiceCount'];
	$credit_description = $myresult['credit_description'];
	$org_name = $myresult['g_org_name'];
	echo "<td>$credit_description</td><td>$org_name</td><td>$category_billed</td>".
		"<td>$category_total</td><td>($count)</td><tr>";
}
?>

</table>
<p><table><td><?php echo lang('refund')?></td>
<td><?php echo lang('organizationname')?></td>
<td><?php echo lang('category')?></td>
<td><?php echo lang('refund')?></td><td></td><tr>

<?php
foreach ($refundrevenue AS $myresult) 
{
	$category_total = $myresult['CategoryTotal'];
	$service_category = $myresult['service_category'];
	$count = $myresult['ServiceCount'];
	$org_name = $myresult['g_org_name'];
	$service_description = $myresult['service_description'];
	echo "<td>$service_description</td><td>$org_name</td><td>$service_category</td>".
		"<td>$category_total</td><td>($count)</td><tr>";
	
	// subtract this from the total
	$total = $total - $category_total;
}
?>

<p><table><td><?php echo lang('discount')?></td>
<td><?php echo lang('name')?></td>
<td><?php echo lang('amount')?></td><tr>

<?php
foreach ($discountrevenue AS $myresult) 
{
	$invoice_number = $myresult['invoice_number'];    
	$date = humandate($myresult['creation_date'], $lang);    
	$name = $myresult['name'];
	$company = $myresult['company'];    
	$amount = $myresult['billing_amount'];    
	echo "<td>$date ($invoice_number)</td><td>$name $company</td><td>$amount</td><td></td><tr>";
	
	// subtract this from the total
	$total = $total - $category_total;
}
?>

</table>  

<?php
echo "<h3>".lang('total')." ".$total."</h3>";
?>

<hr>

<p><table><td><?php echo lang('tax')?></td>
<td><?php echo lang('billed')?></td>
<td><?php echo lang('paid')?></td><td></td><tr>
	
<?php
foreach ($taxrevenue AS $myresult) 
{
	$category_total = $myresult['CategoryTotal'];
	$category_billed = $myresult['CategoryBilled'];
	$count = $myresult['ServiceCount'];
	$tax_description = $myresult['tax_description'];
	echo "<td>$tax_description</td><td>$category_billed</td><td>$category_total</td><td>($count)</td><tr>";

	// add this to the taxtotal
	$taxtotal = $taxtotal + $category_total;
}
?>
</table>  

<p><table><td><?php echo lang('tax')." ".lang('refund');?></td>
<td><?php echo lang('organizationname')?></td>
<td><?php echo lang('category')?></td>
<td><?php echo lang('refund')?></td><td></td><tr>

<?php
foreach ($taxrefunds AS $myresult) 
{
	$category_total = $myresult['CategoryTotal'];
	$tax_description = $myresult['tax_description'];
	$count = $myresult['ServiceCount'];
	echo "<td>$tax_description</td><td></td><td>".lang('tax')."</td>".
		"<td>$category_total</td><td>($count)</td><tr>";

	// subtract this from the taxtotal
	$taxtotal = $taxtotal - $category_total;
}
?>
</table>

<?php
echo "<h3>".lang('tax')." ".lang('total')." ".$taxtotal."</h3>";
?>
</body>
</html>
