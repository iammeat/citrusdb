<html>
<body bgcolor="#ffffff">
<?php
echo "<h3>$l_edituser</h3>";
// Copyright (C) 2002-2005  Paul Yasi <paul@citrusdb.org>, read the README file for more information
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

//GET Variables
if (!isset($base->input['userid'])) { $base->input['userid'] = ""; }
if (!isset($base->input['username'])) { $base->input['username'] = ""; }
if (!isset($base->input['realname'])) { $base->input['realname'] = ""; }
if (!isset($base->input['admin'])) { $base->input['admin'] = ""; }
if (!isset($base->input['manager'])) { $base->input['manager'] = ""; }

$submit = $base->input['submit'];
$userid = $base->input['userid'];
$username = $base->input['username'];
$realname = $base->input['realname'];
$admin = $base->input['admin'];
$manager = $base->input['manager'];

// check that the user has admin privileges
$query = "SELECT * FROM user WHERE username='$user'";
$DB->SetFetchMode(ADODB_FETCH_ASSOC);
$result = $DB->Execute($query) or die ("$l_queryfailed");
$myresult = $result->fields;
if ($myresult['admin'] == 'n') {
        echo '$l_youmusthaveadmin<br>';
        exit;
}

if ($submit) {
	// save user information
      	$query = "UPDATE user 
        SET real_name = '$realname',
	username = '$username',
        admin = '$admin',
        manager = '$manager' 
	WHERE id = '$userid'";
        $result = $DB->Execute($query) or die ("$l_queryfailed");
        print "<h3>$l_changessaved</h3>";

}

        // get the variables out of the id 1 general configuration table
        $query = "SELECT * FROM user WHERE id = '$userid'";
	$DB->SetFetchMode(ADODB_FETCH_ASSOC);
        $result = $DB->Execute($query) or die ("$l_queryfailed");
        
	$myresult = $result->fields;
        $username = $myresult['username'];
        $password = $myresult['password'];
        $realname = $myresult['real_name'];
        $admin = $myresult['admin'];
        $manager = $myresult['manager'];

echo "<script language=\"JavaScript\" src=\"include/md5.js\"></script>
        <script language=\"JavaScript\" src=\"include/verify.js\"></script>
	[<a href=\"index.php?load=adminchangepass&type=tools&userid=$userid\">
	$l_changepassword</a>]
	<p>
	<FORM ACTION=\"index.php\" METHOD=\"GET\">
	<input type=hidden name=load value=edituser>
	<input type=hidden name=type value=tools>
        <B>$l_name:</B><BR>
        <INPUT TYPE=\"TEXT\" NAME=\"realname\" VALUE=\"$realname\" SIZE=\"20\" MAXLENGTH=\"65\">
        <P>
        <B>$l_username:</B><INPUT TYPE=\"TEXT\" NAME=\"username\" VALUE=\"$username\" SIZE=\"20\" MAXLENGTH=\"65\">
        <P>
        <b>$l_privileges:</b><br>
        <table>";
	
if ($admin == 'y') {
echo "<td>$l_admin</td><td><input type=\"radio\" name=admin value=\"y\" checked>$l_yes<input type=\"radio\" name=admin value=\"n\">$l_no<tr>";
} else {
echo "<td>$l_admin</td><td><input type=\"radio\" name=admin value=\"y\">$l_yes<input type=\"radio\" name=admin value=\"n\" checked>$l_no<tr>";
}

if ($manager == 'y') {
echo "<td>$l_manager</td><td><input type=\"radio\" name=manager value=\"y\" checked>$l_yes<input type=\"radio\" name=manager value=\"n\">$l_no<tr>";
} else {
echo "<td>$l_manager</td><td><input type=\"radio\" name=manager value=\"y\">$l_yes<input type=\"radio\" name=manager value=\"n\" checked>$l_no<tr>";
}


echo "</table>
        <p>
	<input type=hidden name=\"userid\" value=\"$userid\">
        <INPUT TYPE=\"SUBMIT\" NAME=\"submit\" VALUE=\"$l_savechanges\">
	</FORM>";
?>
</body>
</html>







