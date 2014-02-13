<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 * This script is distributed under the GNU General Public License 2 or later. 
 *
 * @filesource	keywordsEdit.php
 * @package 	TestLink
 * @copyright 	2005,2011 TestLink community 
 * @link 		http://www.teamst.org/index.php
 *
 * allows users to manage keywords. 
 *
 *
**/
require_once("../../config.inc.php");
require_once("common.php");
require_once("csv.inc.php");
require_once("xml.inc.php");
testlinkInitPage($db);

$smarty = new TLSmarty();
$templateCfg = templateConfiguration();
$tprojectMgr = new testproject($db);

$op = new stdClass();
$op->status = 0;

$args = init_args($db);
checkRights($db,$_SESSION['currentUser'],$args);

$gui = new stdClass();
$gui->tproject_id = $args->tproject_id;
$gui->canManage = $_SESSION['currentUser']->hasRight($db,"mgt_modify_key",$args->tproject_id);
$gui->mgt_view_events = $_SESSION['currentUser']->hasRight($db,"mgt_view_events",$args->tproject_id);

$gui->user_feedback = '';
$gui->notes = $args->notes;
$gui->name = $args->keyword;
$gui->keyword = $args->keyword;
$gui->keywordID = $args->keyword_id;


$action = $args->doAction;
switch ($action)
{
	case "do_create":
	case "do_update":
	case "do_delete":
		if (!$gui->canManage)
		{
			break;
		}
			
	case "edit":
	case "create":
		$op = $action($smarty,$args,$gui,$tprojectMgr);
	break;
}

$templateResource = $templateCfg->default_template;
if($op->status > 0)
{
	$templateResource = $op->template;
}
else
{
	$gui->user_feedback = getKeywordErrorMessage($op->status);
}

$gui->keywords = null;
if ($templateResource != $templateCfg->default_template)
{
	// I'm going to return to screen that display all keywords
	$gui->keywords = $tprojectMgr->getKeywords($args->tproject_id);
}

// new dBug($gui);

$smarty->assign('gui',$gui);
$smarty->display($templateCfg->template_dir . $templateResource);


/**
 * @return object returns the arguments for the page
 */
function init_args(&$dbHandler)
{
	$args = new stdClass();
	$_REQUEST=strings_stripSlashes($_REQUEST);
	$source = sizeof($_POST) ? "POST" : "GET";
	$iParams = array( "doAction" => array($source,tlInputParameter::STRING_N,0,50),
					  "id" => array($source, tlInputParameter::INT_N),
					  "keyword" => array($source, tlInputParameter::STRING_N,0,100),
					  "notes" => array($source, tlInputParameter::STRING_N),
					  "tproject_id" => array($source, tlInputParameter::INT_N));
		
	$pParams = I_PARAMS($iParams);
	$args = new stdClass();
	$args->doAction = $pParams["doAction"];
	$args->keyword_id = $pParams["id"];
	$args->keyword = $pParams["keyword"];
	$args->notes = $pParams["notes"];
	$args->tproject_name = '';
	if( ($args->tproject_id = $pParams["tproject_id"]) >0 )
	{
		$treeMgr = new tree($dbHandler);
		$dummy = $treeMgr->get_node_hierarchy_info($args->tproject_id);
		$args->tproject_name = $dummy['name'];	
	}


	return $args;
}

/*
 *	initialize variables to launch user interface (smarty template)
 *	to get information to accomplish create task.
*/
function create(&$smarty,&$argsObj,&$guiObj)
{
	$guiObj->submit_button_action = 'do_create';
	$guiObj->submit_button_label = lang_get('btn_save');
	$guiObj->main_descr = lang_get('keyword_management');
	$guiObj->action_descr = lang_get('create_keyword');

	$ret = new stdClass();
	$ret->template = 'keywordsEdit.tpl';
	$ret->status = 1;
	return $ret;
}


/*
 *	initialize variables to launch user interface (smarty template)
 *  to get information to accomplish edit task.
*/
function edit(&$smarty,&$argsObj,&$guiObj,&$tproject_mgr)
{
	$guiObj->submit_button_action = 'do_update';
	$guiObj->submit_button_label = lang_get('btn_save');
	$guiObj->main_descr = lang_get('keyword_management');
	$guiObj->action_descr = lang_get('edit_keyword');

	$ret = new stdClass();
	$ret->template = 'keywordsEdit.tpl';
	$ret->status = 1;

	$keyword = $tproject_mgr->getKeyword($argsObj->keyword_id);
	if ($keyword)
	{
		$guiObj->keyword = $argsObj->keyword = $keyword->name;
		$guiObj->notes = $argsObj->notes = $keyword->notes;
		$guiObj->action_descr .= TITLE_SEP . $guiObj->keyword;
	}

	return $ret;
}

/*
 * Creates the keyword
 */
function do_create(&$smarty,&$args,&$guiObj,&$tproject_mgr)
{
	$guiObj->submit_button_action = 'do_create';
	$guiObj->submit_button_label = lang_get('btn_save');
	$guiObj->main_descr = lang_get('keyword_management');
	$guiObj->action_descr = lang_get('create_keyword');

	$op = $tproject_mgr->addKeyword($args->tproject_id,$args->keyword,$args->notes);
	$ret = new stdClass();
	$ret->template = 'keywordsView.tpl';
	$ret->status = $op['status'];
	return $ret;
}

/*
 * Updates the keyword
*/
function do_update(&$smarty,&$argsObj,&$guiObj,&$tproject_mgr)
{
	$guiObj->submit_button_action = 'do_update';
	$guiObj->submit_button_label = lang_get('btn_save');
	$guiObj->main_descr = lang_get('keyword_management');
	$guiObj->action_descr = lang_get('edit_keyword');

	$keyword = $tproject_mgr->getKeyword($argsObj->keyword_id);
	if ($keyword)
	{
		$guiObj->action_descr .= TITLE_SEP . $keyword->name;
	}
	
	$ret = new stdClass();
	$ret->template = 'keywordsView.tpl';
	$ret->status = $tproject_mgr->updateKeyword($argsObj->tproject_id,$argsObj->keyword_id,
										  		$argsObj->keyword,$argsObj->notes);

//	new dBug($ret);
	return $ret;
}

/*
 * Deletes the keyword 
*/
function do_delete(&$smarty,&$args,&$guiObj,&$tproject_mgr)
{
	$dummy = $tproject_mgr->get_by_id($args->tproject_id);
	$main_descr = lang_get('testproject') . TITLE_SEP . $args->tproject_name;

	$guiObj->submit_button_action = 'do_update';
	$guiObj->submit_button_label = lang_get('btn_save');
	$guiObj->main_descr = lang_get('keyword_management');
	$guiObj->action_descr = lang_get('delete_keyword');

	$ret = new stdClass();
	$ret->template = 'keywordsView.tpl';
	$ret->status = $tproject_mgr->deleteKeyword($args->keyword_id);

	return $ret;
}


function getKeywordErrorMessage($code)
{
	switch($code)
	{
		case tlKeyword::E_NAMENOTALLOWED:
			$msg = lang_get('keywords_char_not_allowed'); 
			break;

		case tlKeyword::E_NAMELENGTH:
			$msg = lang_get('empty_keyword_no');
			break;

		case tlKeyword::E_DBERROR:
		case ERROR: 
			$msg = lang_get('kw_update_fails');
			break;

		case tlKeyword::E_NAMEALREADYEXISTS:
			$msg = lang_get('keyword_already_exists');
			break;

		default:
			$msg = 'ok';
  }
  return $msg;
}

/**
 * checkRights
 *
 */
function checkRights(&$db,&$userObj,$argsObj)
{
	$env['tproject_id'] = isset($argsObj->tproject_id) ? $argsObj->tproject_id : 0;
	$env['tplan_id'] = isset($argsObj->tplan_id) ? $argsObj->tplan_id : 0;
	checkSecurityClearance($db,$userObj,$env,array('mgt_modify_key','mgt_view_key'),'and');
}
?>