<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later.
 *
 * @filesource	reqSpecView.php
 * @author 		  Martin Havlat
 *
 * Screen to view existing requirements within a req. specification.
 *
 * @internal revisions
 *
**/
require_once("../../config.inc.php");
require_once("common.php");
require_once("users.inc.php");
require_once('requirements.inc.php');
require_once("configCheck.php");
testlinkInitPage($db);

$templateCfg = templateConfiguration();
$args = init_args($db);
checkRights($db,$_SESSION['currentUser'],$args);

$gui = initialize_gui($db,$args);

$smarty = new TLSmarty();
$smarty->assign('gui',$gui);
$smarty->display($templateCfg->template_dir . $templateCfg->default_template);


/**
 *
 */
function init_args(&$dbHandler)
{
	$iParams = array("req_spec_id" => array(tlInputParameter::INT_N),
					         "refreshTree" => array(tlInputParameter::INT_N),
					         "tproject_id" => array(tlInputParameter::INT_N));

  $args = new stdClass();
  R_PARAMS($iParams,$args);

	$args->refreshTree = intval($args->refreshTree);
	$args->tproject_name = '';
	if($args->tproject_id > 0)
	{
		$treeMgr = new tree($dbHandler);
		$dummy = $treeMgr->get_node_hierarchy_info($args->tproject_id);
		$args->tproject_name = $dummy['name'];    
	}
  $args->user = $_SESSION['currentUser'];  
  return $args;
}

/**
 * 
 *
 */
function initialize_gui(&$dbHandler,&$argsObj)
{
	$req_spec_mgr = new requirement_spec_mgr($dbHandler);
	$tproject_mgr = new testproject($dbHandler);
	$commandMgr = new reqSpecCommands($dbHandler);
	
  $gui = $commandMgr->initGuiBean();
  $gui->refreshTree = $argsObj->refreshTree;
	$gui->req_spec_cfg = config_get('req_spec_cfg');
	$gui->req_cfg = config_get('req_cfg');
	
	$gui->external_req_management = ($gui->req_cfg->external_req_management == ENABLED) ? 1 : 0;
	
	$gui->grants = new stdClass();
	$gui->grants->req_mgmt = $argsObj->user->hasRight($dbHandler,"mgt_modify_req",$argsObj->tproject_id);

	$gui->req_spec = $req_spec_mgr->get_by_id($argsObj->req_spec_id);
	$gui->revCount = $req_spec_mgr->getRevisionsCount($argsObj->req_spec_id);
	$gui->req_spec_id = $argsObj->req_spec_id;
	$gui->parentID = $argsObj->req_spec_id;
	$gui->req_spec_revision_id = $gui->req_spec['revision_id'];
	$gui->name = $gui->req_spec['title'];
	
	$gui->tproject_id = $argsObj->tproject_id;
	$gui->tproject_name = $argsObj->tproject_name;
	
	$gui->main_descr = lang_get('req_spec_short') . config_get('gui_title_separator_1') . 
	                   "[{$gui->req_spec['doc_id']}] :: " .$gui->req_spec['title'];

	$gui->refresh_tree = 'no';
	$gui->cfields = $req_spec_mgr->html_table_of_custom_field_values($argsObj->req_spec_id,$gui->req_spec_revision_id,
																	                                 $argsObj->tproject_id);
	$gui->attachments = $req_spec_mgr->getAttachmentInfos($argsObj->req_spec_id);
	$gui->requirements_count = $req_spec_mgr->get_requirements_count($argsObj->req_spec_id);
	
	$gui->reqSpecTypeDomain = init_labels($gui->req_spec_cfg->type_labels);

	$prefix = $tproject_mgr->getTestCasePrefix($argsObj->tproject_id);
	$gui->direct_link = $_SESSION['basehref'] . 'linkto.php?tprojectPrefix=' . urlencode($prefix) . 
	                    '&item=reqspec&id=' . urlencode($gui->req_spec['doc_id']);

  $gui->actions = initializeActions($gui);
  return $gui;
}


function initializeActions($guiObj)
{
	$module = $_SESSION['basehref'] . "lib/requirements/";
	$context = "tproject_id=$guiObj->tproject_id&req_spec_id=$guiObj->req_spec_id";

	$actions = new stdClass();
	$actions->req_import = $module . "reqImport.php?doAction=import&$context";
	$actions->req_export = $module . "reqExport.php?doAction=export&$context";
	$actions->req_edit = $module . "reqEdit.php?doAction=create&$context";
	$actions->req_reorder = $module . "reqEdit.php?doAction=reorder&$context";
	$actions->req_create_tc = $module . "reqEdit.php?doAction=createTestCases&$context";

	$actions->req_spec_new = $module . "reqSpecEdit.php?doAction=createChild" .
						  "&tproject_id=$guiObj->tproject_id&reqParentID=$guiObj->req_spec_id";

	$actions->req_spec_copy = $module . "reqSpecEdit.php?doAction=copy&$context";
	
	$actions->req_spec_copy_req = $module . "reqSpecEdit.php?doAction=copyRequirements&$context";
						  
	$actions->req_spec_import = $gui->actions->req_import . "&scope=branch";
	$actions->req_spec_export = $gui->actions->req_export . "&scope=branch";
  return $actions;
}

/**
 * checkRights
 *
 */
function checkRights(&$db,&$userObj,$argsObj)
{
	$env['tproject_id'] = isset($argsObj->tproject_id) ? $argsObj->tproject_id : 0;
	$env['tplan_id'] = isset($argsObj->tplan_id) ? $argsObj->tplan_id : 0;
	checkSecurityClearance($db,$userObj,$env,array('mgt_view_req'),'and');
}
?>