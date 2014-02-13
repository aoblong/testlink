<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 * This script is distributed under the GNU General Public License 2 or later. 
 *
 * @filesource	lostPassword.php
 * @internal    revisions
**/
require_once('config.inc.php');
require_once('common.php');
// require_once('users.inc.php');
require_once('email_api.php');
$templateCfg = templateConfiguration();

$args = init_args();
$gui = initializeGui();
$op = doDBConnect($db);
if ($op['status'] == 0)
{
	$smarty = new TLSmarty();
	$gui->title = lang_get('fatal_page_title');
	$gui->msg = $op['dbms_msg'];
	$smarty->display('fatal_error.tpl');
	exit();
}

if ($args->login != "" && !$gui->external_password_mgmt)
{
	$userID = tlUser::doesUserExist($db,$args->login);
	if (!$userID)
	{
		$gui->note = lang_get('bad_user');
	}
	else
	{
	  $user = new tlUser($userID);
		$result = $user->resetPassword($db);
		$gui->note = $result['msg'];
		if ($result['status'] >= tl::OK)
		{
		  if ($user->readFromDB($db) >= tl::OK)
		  {
		  		logAuditEvent(TLS("audit_pwd_reset_requested",$user->login),"PWD_RESET",$userID,"users");
			}
			redirect(TL_BASE_HREF ."login.php?note=lost");
			exit();
		}
		else if ($result['status'] == tlUser::E_EMAILLENGTH)
		{
			$gui->note = lang_get('mail_empty_address');
		}	
		else if ($note != "")
		{
			$gui->note = tlUser::getUserErrorMessage($result['status']);
		}	
	}
}

$smarty = new TLSmarty();
$smarty->assign('gui',$gui);
$smarty->display($templateCfg->default_template);


function init_args()
{
	$iParams = array("login" => array(tlInputParameter::STRING_N,0,30));
	
	$args = new stdClass();
  P_PARAMS($iParams,$args);
	return $args;
}

function initializeGui()
{
  $gui = new stdClass();
  $gui->external_password_mgmt = tlUser::isPasswordMgtExternal();
  $gui->page_title = lang_get('page_title_lost_passwd');
  $gui->note = lang_get('your_info_for_passwd');
  return $gui;
}
?>