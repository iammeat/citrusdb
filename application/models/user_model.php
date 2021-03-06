<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*----------------------------------------------------------------------------*/
// Includes Code Contributed by Matthew Veno and Bryan Nielsen
// Copyright (C) 2005-2011  Paul Yasi (paul at citrusdb.org)
// Read the README file for more information
/*----------------------------------------------------------------------------*/

class User_Model extends CI_Model {	

	var $LOGGED_IN=false;

	//base-2 logarithm of the iteration count for password stretching
	var $hash_cost_log2 = 8;

	// do we require the hashes to be portable to older systems (less secure)?
	// bcrypt hashes have '$2a$' header
	// des ext hashes have '_'
	// portable md5 hashes have '$P$' header
	var $hash_portable = FALSE;

	/*--------------------------------------------------------------------*/
	// Constructor - initialize class
	/*--------------------------------------------------------------------*/
	function __construct() {
		//clear it out in case someone sets it in the URL or something
		parent::__construct();
		unset($LOGGED_IN);

		// load the PasswordHash library
		$config = array (
				'iteration_count_log2' => '8', 
				'portable_hashes' => 'FALSE'
				);
		$this->load->library('PasswordHash', $config);    
	}


	/*
	 * ---------------------------------------------------------------------------
	 *  Check the privileges for this user, whether they have admin or manage etc.
	 * ---------------------------------------------------------------------------
	 */
	function user_privileges($username)
	{
		$query = "SELECT admin,manager,email,screenname,email_notify,screenname_notify ".
			"FROM user WHERE username = ? LIMIT 1";
		$result = $this->db->query($query, array($username));

		return $result->row_array();
	}

	/*--------------------------------------------------------------------*/
	// check for too many login falures
	/*--------------------------------------------------------------------*/
	function checkfailures($ipaddress) 
	{
		$query = "SELECT * FROM login_failures WHERE ip = ? ".
			"AND DATE(logintime) = CURRENT_DATE";
		$result = $this->db->query($query, array($ipaddress));

		$attempts = $result->num_rows();

		if ($attempts > 5) 
		{
			return TRUE;
		} 
		else 
		{
			return FALSE;
		}
	}

	/*--------------------------------------------------------------------*/
	// Authenticate the user
	/*--------------------------------------------------------------------*/
	function user_login($user_name, $password, $ipaddress)
    {
		$ldap_enable = $this->config->item('ldap_enable');
		$ldap_host = $this->config->item('ldap_host');
		$ldap_dn = $this->config->item('ldap_dn');
		$ldap_protocol_version = $this->config->item('ldap_protocol_version');
		$ldap_uid_field = $this->config->item('ldap_uid_field');

		if (!$user_name || !$password)
        {
			//Missing user name or password
			return false;
		}
        else
        {
            // this is a giant mess TODO move to it's own method
			$user_name=strtolower($user_name);
			/* start of ldap mod */
			if( $ldap_enable )
            {
				$con = ldap_connect($ldap_host); 
				if( $con )
                { 
					ldap_set_option($con, LDAP_OPT_PROTOCOL_VERSION, $ldap_protocol_version); 
					$findWhat = array ($ldap_uid_field); 
					$findFilter = "(".$ldap_uid_field."=".$user_name.")"; 
					$sr = ldap_search($con, $ldap_dn, $findFilter, $findWhat); 
					if( $sr )
                    { 
						$records = ldap_get_entries($con, $sr); 
						if ($records["count"] != "1")
                        { 
							// user not found 
                            $this->loginfailure($user_name, $ipaddress);
							return false; 
						} 
						else
                        {
							if (ldap_bind($con, $records[0]["dn"], $password) === false) { 
								// LDAP password match failed 
                                $this->loginfailure($user_name, $ipaddress);
								return false; 
							} 
							else
                            {
								// LDAP login successful, now get user info
								$sql="SELECT * FROM user WHERE username = ? ";
								$result = $this->db->query($sql, array($user_name));

								if (!$result ||  $result->num_rows() < 1)
                                {
                                    // track login failures to stop them trying forever
									$this->loginfailure($user_name, $ipaddress);
									return false;
								}
							} 
						} 
					} 
					else
                    { 
						// LDAP search failed 
                        return false; 
					} 
				} 
				else
                { 
					// LDAP Connect Failed!
					return false; 
				}
			}
			else
            {
				// standard authentication method

                // get the row that holds the user
				$result = $this->db->get_where('user',
                                               array('username' => $user_name), 1, 0);
				$myresult = $result->row();
				$checkhash = $myresult->password;

				// check the password with the phpass checkpassword function
				$passwordmatch = $this->passwordhash->CheckPassword($password, $checkhash);

				// bcrypt hashes have '$2a$' header
				// des ext hashes have '_' header
				// portable md5 hashes have '$P$' header
				// the old md5 passwords that should be upgraded have no header
				$bcrypt_h = substr($checkhash, 0, 4);
				$desext_h = substr($checkhash, 0, 1);
				$portmd5_h = substr($checkhash, 0, 3);

				if (($bcrypt_h != '$2a$') AND ($desext_h != '_') AND ($portmd5_h != '$P$'))
                {
					// the password is an old md5 hash and must be upgraded to the new type
					// authenticate the old md5 password
					$passwordhashed = md5($password);
					if ($passwordhashed == $checkhash)
                    {
						// upgrade it to the newer phpass password format
						$passwordmatch = 1;
						$newhash = $this->passwordhash->HashPassword($password);
						if (strlen($newhash) < 20)
                        {
							//Failed to hash new password
							return false;
						}

						$sql="UPDATE user SET password = ? ".
							"WHERE username = ? LIMIT 1";
						$passresult=$this->db->query($sql,
                                                     array($newhash, $user_name))
                            or die ("Query Failed");

					}
                    else
                    {
                        // old md5 password does not match either
						$passwordmatch = 0;
					}

				}

				// check the normal passwords are valid
				if (!$result ||  $result->num_rows() < 1 || !$passwordmatch) {

					// User not found or password incorrect $user_name $password
					// keep track of login failures to stop them trying forever
					$this->loginfailure($user_name, $ipaddress);

					return false;
				}
			}
			/* end of ldap mod */
			{
                // Success, You Are Now Logged In
				$this->user_set_tokens($user_name);
				$this->loginsuccess($user_name, $ipaddress);
                return true;
			}
		}
	}
    
    /*--------------------------------------------------------------------*/
	// keep track of failed login attempts from IP addresses
	/*--------------------------------------------------------------------*/
	function loginfailure($user_name, $ipaddress) {

		$query="INSERT INTO login_failures(ip,logintime) ".
			"VALUES (?,CURRENT_TIMESTAMP)";
		$result=$this->db->query($query, array($ipaddress)) or die ("Log Insert Failed");

		$this->log_model->activity($user_name,0,'login','dashboard',0,'failure',$ipaddress);

	}

	/*--------------------------------------------------------------------*/
	// keep track of login success
	/*--------------------------------------------------------------------*/  
	function loginsuccess($user_name, $ipaddress) {
        $this->log_model->activity($user_name,0,'login','dashboard',0,
            'success',$ipaddress);
	}

	/*--------------------------------------------------------------------*/
	// Set the cookie tokens
	/*--------------------------------------------------------------------*/
	function user_set_tokens($user_name_in) {
		global $hidden_hash_var,$user_name,$id_hash;
		if (!$user_name_in) {
			$feedback .=  ' ERROR - User Name Missing When Setting Tokens ';
			return false;
		}
		$user_name=strtolower($user_name_in);
		$id_hash= md5($user_name.$hidden_hash_var);

		setcookie('user_name',$user_name,(time()+36000),'/','',0);
		setcookie('id_hash',$id_hash,(time()+36000),'/','',0);
	}


	/*--------------------------------------------------------------------*/
	// Change the password for the user
	/*--------------------------------------------------------------------*/
	function user_change_password ($new_password1,$new_password2,
			$change_user_name,$old_password) 
	{
		// load the PasswordHash library
		//require_once('application/libraries/PasswordHash.php');  	

		//new passwords present and match?
		if ($new_password1 && ($new_password1==$new_password2)) 
		{
			if ($change_user_name && $old_password) 
			{
				//lower case everything
				$change_user_name=strtolower($change_user_name);

				// check that old password is valid
				//$hasher = new PasswordHash($this->hash_cost_log2, $this->hash_portable);

				$sql="SELECT password FROM user WHERE username = ? LIMIT 1";
				$result=$this->db->query($sql, array($change_user_name)) or die ("Query Failed");
				$mypassresult = $result->row_array();
				$checkhash = $mypassresult['password'];

				if (!$result || $result->num_rows() < 1 
						|| !$this->passwordhash->CheckPassword($old_password, $checkhash)) 
				{
					$feedback = " User not found or bad password";
					return $feedback;	  
				} 
				else 
				{
					$newhash = $this->passwordhash->HashPassword($new_password1);
					if (strlen($newhash) < 20) 
					{
						$feedback = "Failed to hash new password";
						return $feedback;
					}

					$sql="UPDATE user SET password = ? ".
						"WHERE username = ?";
					$result=$this->db->query($sql, array($newhash, $change_user_name)) or die ("Query Failed");
					$feedback = ' Password Changed ';
					return $feedback;
				}
			} 
			else 
			{
				$feedback = ' Must Provide User Name And Old Password ';
				return $feedback;
			}
		} 
		else 
		{
			$feedback = ' New Passwords Must Match ';
			return $feedback;
		}
	} // end user_change_password function


	/*--------------------------------------------------------------------*/
	// Change the password for by an administrator
	/*--------------------------------------------------------------------*/
	function admin_change_password ($id, $new_password1,$new_password2) 
	{
		//new passwords present and match?
		if ($new_password1 && ($new_password1==$new_password2)) 
		{
			$sql="SELECT password FROM user WHERE id = ? LIMIT 1";
			$result=$this->db->query($sql, array($id)) or die ("Query Failed");
			$mypassresult = $result->row_array();
			$checkhash = $mypassresult['password'];

			if (!$result || $result->num_rows() < 1) 
			{
				$feedback = " User not found";
				return $feedback;	  
			} 
			else 
			{
				$newhash = $this->passwordhash->HashPassword($new_password1);
				if (strlen($newhash) < 20) 
				{
					$feedback = "Failed to hash new password";
					return $feedback;
				}

				$sql="UPDATE user SET password= ? WHERE id = ?";
				$result=$this->db->query($sql, array($newhash, $id)) or die ("Query Failed");
				$feedback = ' Password Changed ';
				return $feedback;
			}
		} 
		else 
		{
			$feedback = ' New Passwords Must Match ';
			return $feedback;
		}
	} // end admin_change_password function


	/*--------------------------------------------------------------------*/
	// Register a new user
	/*--------------------------------------------------------------------*/
	function user_register($user_name,$password1,$password2,$real_name,$admin,$manager) 
	{
		// initialize the feedback array
		$feedback = array(
				'message' => "",
				'status' => FALSE
				);

		// load the PasswordHash library
		//require_once('application/libraries/PasswordHash.php');    

		$ldap_enable = $this->config->item('ldap_enable');

		//all vars present and passwords match?
		if ($user_name && ($ldap_enable || ($password1 && $password1==$password2))) 
		{
			//name is valid?
			if ($this->account_namevalid($user_name)) 
			{
				$user_name=strtolower($user_name);

				//does the name exist in the database?
				$sql="SELECT * FROM user WHERE username = ?";
				$result=$this->db->query($sql, array($user_name));
				if ($result && $result->num_rows() > 0) 
				{
					$feedback['message'] =  ' ERROR - USER NAME EXISTS ';
					$feedback['status'] = FALSE;
					return $feedback;
				} 
				else 
				{
					// make a new password hash	  
					//$hasher = new PasswordHash($this->hash_cost_log2, $this->hash_portable);
					$hash = $this->passwordhash->HashPassword($password1);
					if (strlen($hash) < 20 ) 
					{
						// hash length always greater than 20, if not then something went wrong
						$feedback['message'] = "Failed to hash new password";
						$feedback['status'] = FALSE;
						return $feedback;
					}
					unset ($hasher);

					// then insert it into the database
					$sql="INSERT INTO user (username,real_name,password,admin,manager) ".
						"VALUES (?,?,?,?,?)";
					$result=$this->db->query($sql, array($user_name, $real_name, $hash, $admin, $manager))
						or die ("Insert Query Failed");
					if (!$result) 
					{
						$feedback['message'] = ' ERROR - '.db_error();
						$feedback['status'] = FALSE;
						return $feedback;
					} 
					else 
					{
						$feedback['message'] = ' Successfully Registered. ';
						$feedback['status'] = TRUE;
						return $feedback;
					}
				}
			} 
			else 
			{
				$feedback['message'] =  ' Account Name or Password Invalid ';
				$feedback['status'] = FALSE;
				return $feedback;
			}
		} 
		else 
		{
			$feedback['message'] =  ' ERROR - Must Fill In User Name, and Matching Passwords ';
			$feedback['status'] = FALSE;
			return $feedback;
		}
	}


	/*--------------------------------------------------------------------*/
	// Get their real name
	/*--------------------------------------------------------------------*/
	function user_getrealname($username) {
		$query = "SELECT * FROM user WHERE username = ?";
		$result = $this->db->query($query, array($username)) or die ("Query Failed");
		$myresult = $result->row_array();
		$real_name = $myresult['real_name'];
		return $real_name;
	}


	/*--------------------------------------------------------------------*/
	// Get their username
	/*--------------------------------------------------------------------*/
	function user_getname() {
		if ($this->user_isloggedin()) {
			return $_COOKIE['user_name'];
		} else {
			//look up the user some day when we need it
			return ' ERROR - Not Logged In ';
		}
	}

	/*--------------------------------------------------------------------*/
	// Check if the account username they entered is valid
	/*--------------------------------------------------------------------*/
	function account_namevalid($name) {	
		global $feedback;
		// no spaces
		if (strrpos($name,' ') > 0) {
			$feedback .= " There cannot be any spaces in the login name. ";
			return false;
		}

		// must have at least one character
		if (strspn($name,"abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ") == 0) {
			$feedback .= "There must be at least one character.";
			return false;
		}

		// must contain all legal characters
		if (strspn($name,"abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_")
				!= strlen($name)) {
			$feedback .= " Illegal character in name. ";
			return false;
		}

		// min and max length
		if (strlen($name) < 2) {
			$feedback .= " Name is too short. It must be at least 2 characters. ";
			return false;
		}
		if (strlen($name) > 60) {
			$feedback .= "Name is too long. It must be less than 60 characters.";
			return false;
		}

		return true;
	}


	function list_groups()
	{
		// print the list of groups
		$query = "SELECT DISTINCT groupname FROM groups ORDER BY groupname";
		$result = $this->db->query($query) or die ("query failed");

		return $result->result_array();
	}


	function list_users()
	{
		// print the list of users
		$query = "SELECT username FROM user ORDER BY username";
		$result = $this->db->query($query) or die ("query failed");

		return $result->result_array();
	}

	function update_usernotifications($email, $screenname, $email_notify, $screenname_notify)
	{
		// save user information
		$query = "UPDATE user ".
			"SET email = '$email', ".
			"screenname = '$screenname', ".
			"email_notify = '$email_notify', ".
			"screenname_notify = '$screenname_notify' ".
			"WHERE username = '$this->user'";
		$result = $this->db->query($query, array($email, $screenname, $email_notify, $screenname_notify, $this->user))
			or die ("query failed");

	}

	function add_user_to_group($group_name, $user_name)
	{
		$query = "INSERT INTO groups (groupname,groupmember) VALUES (?,?)";
		$result = $this->db->query($query, array($group_name, $user_name)) or die ("add user to group query failed");
	}


	/*
	 * ------------------------------------------------------------------------
	 *  get all the info for this user
	 * ------------------------------------------------------------------------
	 */
	function get_user_info($userid)
	{
		$query = "SELECT * FROM user WHERE id = ?";
		$result = $this->db->query($query, array($userid)) or die ("query failed");
		return $result->row_array();
	}


	/*
	 * ------------------------------------------------------------------------
	 *  update the info for this user
	 * ------------------------------------------------------------------------
	 */
	function update_user_info($userid, $userinfo)
	{
		// using active record, yipee!
		$this->db->where('id', $userid);
		$this->db->update('user', $userinfo);
	}


	/*
	 * ------------------------------------------------------------------------
	 *  get the username for this user id
	 * ------------------------------------------------------------------------
	 */
	function get_username($uid)
	{
		$query = "SELECT username FROM user WHERE id = ?";
		$result = $this->db->query($query, array($uid)) or die ("select username query failed");
		$myresult = $result->row_array();
		return $myresult['username'];
	}


	/*
	 * ------------------------------------------------------------------------
	 *  delete the user
	 * ------------------------------------------------------------------------
	 */
	function delete_user($uid)
	{
		// delete the user with that ID
		$query = "DELETE FROM user WHERE id = ?";
		$result = $this->db->query($query, array($uid)) or die ("delete user id query failed");
	}


	/*
	 * ------------------------------------------------------------------------
	 *  delete the user from all groups they are a member of
	 * ------------------------------------------------------------------------
	 */
	function delete_username_from_groups($username)
	{
		$query = "DELETE FROM groups WHERE groupmember = ?"; 
		$result = $this->db->query($query, array($username)) or die ("delete group member query failed");
	}


	/*
	 * ------------------------------------------------------------------------
	 *  add this new member to a group
	 * ------------------------------------------------------------------------
	 */
	function add_to_group($groupname, $membername)
	{
		$query = "INSERT INTO groups (groupname,groupmember) VALUES (?,?)";
		$result = $this->db->query($query, array($groupname, $membername)) or die ("add to group query failed");
	}


	/*
	 * ------------------------------------------------------------------------
	 *  delete a member id from a group
	 * ------------------------------------------------------------------------
	 */
	function delete_group($gid)
	{
		$query = "DELETE FROM groups WHERE id = ?";
		$result = $this->db->query($query, array($gid)) or die ("delete group query failed");
	}


	/*
	 * ------------------------------------------------------------------------
	 *  get a list of groups this user belongs to
	 * ------------------------------------------------------------------------
	 */
	function user_groups($user)
	{
		$query = $this->db->get_where('groups', array('groupmember' => $user));
		return $query->result_array();
	}

} // end class

?>
