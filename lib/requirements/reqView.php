<?php
/** 
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later. 
 *  
 * @filesource	reqView.php,v
 * @package 	  TestLink
 * @author 		  Martin Havlat
 * @copyright 	2008-2012, TestLink community 
 * @link 		    http://www.teamst.org/index.php
 *
 * Screen to view content of requirement.
 *
 * @internal revisions
 * @since 2.0
 *
 */
require_once('../../config.inc.php');
require_once('common.php');
require_once('requirements.inc.php');
require_once('users.inc.php');
testlinkInitPage($db);

$templateCfg = templateConfiguration();

$args = init_args($db);
checkRights($db,$_SESSION['currentUser'],$args);

$gui = initialize_gui($db,$args);
$smarty = new TLSmarty();

$tproject_mgr = new testproject($db);
$prefix = $tproject_mgr->getTestCasePrefix($args->tproject_id);

$gui->direct_link = $_SESSION['basehref'] . 'linkto.php?tprojectPrefix=' . 
                    urlencode($prefix) . '&item=req&id=' . urlencode($gui->req['req_doc_id']);
		
$smarty->assign('gui',$gui);
$smarty->display($templateCfg->template_dir . 'reqViewVersions.tpl');

/**
 *
 */
function init_args(&$dbHandler)
{
	$_REQUEST=strings_stripSlashes($_REQUEST);
	$iParams = array("requirement_id" => array(tlInputParameter::INT_N),
	                 "req_version_id" => array(tlInputParameter::INT_N),
	                 "tproject_id" => array(tlInputParameter::INT_N),
	                 "req_spec_id" => array(tlInputParameter::INT_N),
			             "showReqSpecTitle" => array(tlInputParameter::INT_N),
			             "refreshTree" => array(tlInputParameter::INT_N),
			             "relation_add_result_msg" => array(tlInputParameter::STRING_N));	
		
	$args = new stdClass();
	R_PARAMS($iParams,$args);

	$args->req_id = $args->requirement_id;
	$args->refreshTree = intval($args->refreshTree);
	
	$args->tproject_name = '';
  if($args->tproject_id > 0)
  {
  	$treeMgr = new tree($dbHandler);
  	$dummy = $treeMgr->get_node_hierarchy_info($args->tproject_id);
  	$args->tproject_name = $dummy['name'];
  }

  $args->user = $_SESSION['currentUser'];
	$args->userID = $args->user->dbID;
	
  return $args;
}

/**
 * 
 *
 */
function initialize_gui(&$dbHandler,$argsObj)
{
  $tproject_mgr = new testproject($dbHandler);
  $req_mgr = new requirement_mgr($dbHandler);
  $commandMgr = new reqCommands($dbHandler);

  $gui = $commandMgr->initGuiBean();
  $gui->refreshTree = $argsObj->refreshTree;
  $gui->req_cfg = config_get('req_cfg');
  $gui->tproject_id = $argsObj->tproject_id;
  $gui->tproject_name = $argsObj->tproject_name;


  $gui->grants = new stdClass();
  $gui->grants->req_mgmt = $argsObj->user->hasRight($dbHandler,"mgt_modify_req",$argsObj->tproject_id);

  // IMPORTANT NOTICE
  // We can arrive here after following operation
  // 1. user click on left pane tree on REQ SPEC NODE
  // 2. on right pane screen user click on CREATE button on Requirements Operations
  // 3. on new screen user CLICK CANCEL
  // In this situation $argsObj->req_id is 0 OR EMPTY
  $gui->req_id = $argsObj->req_id;

	if( $gui->req_id <= 0)
	{
		// Quick Exit
		// is not too clear why do not need to add lib/requirements/ on target
		$target = "reqSpecView.php?tproject_id={$gui->tproject_id}" .
				  "&req_spec_id={$argsObj->req_spec_id}"; 
		header("Location: {$target}");
	  exit();
	}	


	// everything is fine - standard processing    
  $gui->tcasePrefix = $tproject_mgr->getTestCasePrefix($argsObj->tproject_id);
  $gui->glueChar = config_get('testcase_cfg')->glue_character;
  $gui->pieceSep = config_get('gui_title_separator_1');
    
    
        
  /* if wanted, show only the given version */
  $gui->version_option = ($argsObj->req_version_id) ? $argsObj->req_version_id : requirement_mgr::ALL_VERSIONS;
      
  $gui->req_versions = $req_mgr->get_by_id($gui->req_id, $gui->version_option);
  $gui->req_has_history = count($req_mgr->get_history($gui->req_id, array('output' => 'array'))) > 1; 
  
  $gui->req = current($gui->req_versions);
  $gui->req_coverage = $req_mgr->get_coverage($gui->req_id);
  
  
  // This seems weird but is done to adapt template than can display multiple
  // requirements. This logic has been borrowed from test case versions management
  $gui->current_version[0] = array($gui->req);
	
	$gui->cfields_current_version[0] = $req_mgr->html_table_of_custom_field_values($gui->req_id,$gui->req['version_id'],
																				                                         $argsObj->tproject_id);

	// Now CF for other Versions
 	$gui->other_versions[0] = null;
 	$gui->cfields_other_versions[] = null;
 	if( count($gui->req_versions) > 1 )
 	{
 		$gui->other_versions[0] = array_slice($gui->req_versions,1);
 		$loop2do = count($gui->other_versions[0]);
 		for($qdx=0; $qdx < $loop2do; $qdx++)
		{
			$target_version = $gui->other_versions[0][$qdx]['version_id'];
			$gui->cfields_other_versions[0][$qdx]= 
				$req_mgr->html_table_of_custom_field_values($gui->req_id,$target_version,$argsObj->tproject_id);
		}
 	}
	
    $gui->show_title = false;
    $gui->main_descr = lang_get('req') . $gui->pieceSep .  $gui->req['title'];
    
    $gui->showReqSpecTitle = $argsObj->showReqSpecTitle;
    if($gui->showReqSpecTitle)
    {
        $gui->parent_descr = lang_get('req_spec_short') . $gui->pieceSep . $gui->req['req_spec_title'];
    }
    
   	$gui->attachments[$gui->req_id] = $req_mgr->getAttachmentInfos($gui->req_id);
    
    $gui->attachmentTableName = $req_mgr->getAttachmentTableName();
    $gui->reqStatus = init_labels($gui->req_cfg->status_labels);
    $gui->reqTypeDomain = init_labels($gui->req_cfg->type_labels);

    // added req relations for BUGID 1748
    $gui->req_relations = FALSE;
    $gui->req_relation_select = FALSE;
    $gui->testproject_select = FALSE;
    $gui->req_add_result_msg = isset($argsObj->relation_add_result_msg) ? 
    							             $argsObj->relation_add_result_msg : "";
    
    if ($gui->req_cfg->relations->enable) 
    {
    	$gui->req_relations = $req_mgr->get_relations($gui->req_id);
    	$gui->req_relation_select = $req_mgr->init_relation_type_select();
    	if ($gui->req_cfg->relations->interproject_linking) 
    	{
    		$gui->testproject_select = initTestprojectSelect($argsObj->userID, $argsObj->tproject_id,$tproject_mgr);
    	}
    }
    
    return $gui;
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


/**
 * Initializes the select field for the testprojects.
 * 
 * @return array $htmlSelect array with info, needed to create testproject select box on template
 */
function initTestprojectSelect($userID, $tprojectID, &$tprojectMgr) 
{
	$testprojects = $tprojectMgr->get_accessible_for_user($userID);
	$htmlSelect = array('items' => $testprojects, 'selected' => $tprojectID);
	return $htmlSelect;
}
?>