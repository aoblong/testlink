<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later.
 *
 * @filesource	reqEdit.php
 * @author 		  Martin Havlat
 *
 * Screen to view existing requirements within a req. specification.
 *
 * @internal revisions
 * @since 2.0
 *  
**/
require_once("../../config.inc.php");
require_once("common.php");
require_once("users.inc.php");
require_once('requirements.inc.php');
require_once("xml.inc.php");
require_once("configCheck.php");
require_once("web_editor.php");

$editorCfg = getWebEditorCfg('requirement');
require_once(require_web_editor($editorCfg['type']));

testlinkInitPage($db);

$templateCfg = templateConfiguration();
$commandMgr = new reqCommands($db);

$args = init_args($db);
checkRights($db,$_SESSION['currentUser'],$args);
$gui = initialize_gui($db,$args,$commandMgr);
$pFn = $args->doAction;

$op = null;
if(method_exists($commandMgr,$pFn))
{
	$op = $commandMgr->$pFn($args,$_REQUEST);
}
renderGui($args,$gui,$op,$templateCfg,$editorCfg,$db);


/**
 * init_args
 *
 */
function init_args(&$dbHandler)
{
	// take care of proper escaping when magic_quotes_gpc is enabled
	$_REQUEST=strings_stripSlashes($_REQUEST);

	$iParams = array("requirement_id" => array(tlInputParameter::INT_N),
					         "req_spec_id" => array(tlInputParameter::INT_N),
					         "containerID" => array(tlInputParameter::INT_N),
					         "reqDocId" => array(tlInputParameter::STRING_N,0,64), 
					         "req_title" => array(tlInputParameter::STRING_N,0,100),
					         "scope" => array(tlInputParameter::STRING_N),
					         "reqStatus" => array(tlInputParameter::STRING_N,0,1),
					         "reqType" => array(tlInputParameter::STRING_N,0,1),
					         "countReq" => array(tlInputParameter::INT_N),
					         "expected_coverage" => array(tlInputParameter::INT_N),
					         "doAction" => array(tlInputParameter::STRING_N,0,20),
					         "req_id_cbox" => array(tlInputParameter::ARRAY_INT),
			 		         "itemSet" => array(tlInputParameter::ARRAY_INT),
					         "testcase_count" => array(tlInputParameter::ARRAY_INT),
        					 "req_version_id" => array(tlInputParameter::INT_N),
        					 "copy_testcase_assignment" => array(tlInputParameter::CB_BOOL),
        					 "relation_id" => array(tlInputParameter::INT_N),
        					 "relation_source_req_id" => array(tlInputParameter::INT_N),
        					 "relation_type" => array(tlInputParameter::STRING_N),
        					 "relation_destination_req_doc_id" => array(tlInputParameter::STRING_N,0,64),
        					 "relation_destination_testproject_id" => array(tlInputParameter::INT_N),
        					 "save_rev" => array(tlInputParameter::INT_N),
        					 "do_save" => array(tlInputParameter::INT_N),
        					 "tproject_id" => array(tlInputParameter::INT_N),
        					 "log_message" => array(tlInputParameter::STRING_N));
		
	$args = new stdClass();
	R_PARAMS($iParams,$args);


	$args->req_id = $args->requirement_id;
	$args->title = $args->req_title;
	$args->arrReqIds = $args->req_id_cbox;

	$args->basehref = $_SESSION['basehref'];
	
	$args->tproject_name = '';
	if($args->tproject_id > 0)
	{
		$treeMgr = new tree($dbHandler);
		$dummy = $treeMgr->get_node_hierarchy_info($args->tproject_id);
		$args->tproject_name = $dummy['name'];
	}
	
	$args->user_id = isset($_SESSION['userID']) ? $_SESSION['userID'] : 0;
  $args->user = $_SESSION['currentUser'];
  
	// to avoid database errors with null value
	if (!is_numeric($args->expected_coverage)) {
		$args->expected_coverage = 0;
	}
	
  $uk = 'setting_refresh_tree_on_action';
  $args->refreshTree = testproject::getUserChoice($args->tproject_id,array('reqTreeRefreshOnAction'));
	$args->stay_here = isset($_REQUEST['stay_here']) ? 1 : 0;
	
	return $args;
}

/**
 * 
 *
 */
function renderGui(&$argsObj,$guiObj,$opObj,$templateCfg,$editorCfg,&$dbHandler)
{
    $smartyObj = new TLSmarty();
    $renderType = 'none';
    
    // @TODO document
    $actionOperation = array('create' => 'doCreate', 'edit' => 'doUpdate',
                             'doDelete' => '', 'doReorder' => '', 'reorder' => '',
                             'createTestCases' => 'doCreateTestCases',
                             'doCreateTestCases' => 'doCreateTestCases',
                             'doCreate' => 'doCreate', 'doUpdate' => 'doUpdate',
                             'copy' => 'doCopy', 'doCopy' => 'doCopy',
                             'doCreateVersion' => 'doCreateVersion','doCreateRevision' => 'doCreateRevision',
                             'doDeleteVersion' => '', 'doFreezeVersion' => 'doFreezeVersion',
                             'doAddRelation' => 'doAddRelation', 'doDeleteRelation' => 'doDeleteRelation');

    $owebEditor = web_editor('scope',$argsObj->basehref,$editorCfg) ;
	switch($argsObj->doAction)
    {
        case "edit":
        case "doCreate":
        $owebEditor->Value = $argsObj->scope;
        break;

        default:
		if($opObj->suggest_revision || $opObj->prompt_for_log) 
		{
			$owebEditor->Value = $argsObj->scope;
		}
		else
		{
    	$owebEditor->Value = getItemTemplateContents('requirement_template',$owebEditor->InstanceName, 
    	                                             $argsObj->scope);
        }
        break;
    }

	$guiObj->askForRevision = $opObj->suggest_revision ? 1 : 0;
	$guiObj->askForLog = $opObj->prompt_for_log ? 1 : 0;


	$guiObj->scope = $owebEditor->CreateHTML();
    $guiObj->editorType = $editorCfg['type'];
      
    switch($argsObj->doAction) 
    {
       	case "doDelete":
       		$guiObj->refreshTree = 1;  // has to be forced
    	break;
    
       	case "doCreate":
       		$guiObj->refreshTree = $argsObj->refreshTree;
    	break;

      	case "doUpdate":
      	// IMPORTANT NOTICE
      	// we do not set tree refresh here, because on this situation
      	// tree update has to be done when reqView page is called.
      	// If we ask for tree refresh here we are going to do double refresh (useless and time consuming)
    	break;
    }

	    
    switch($argsObj->doAction)
    {
        case "edit":
        case "create":
        case "reorder":
        case "doDelete":
        case "doReorder":
        case "createTestCases":
        case "doCreateTestCases":
		case "doCreate":
		case "doFreezeVersion":
      	case "doUpdate":
        case "copy":
        case "doCopy":
        case "doCreateVersion":
        case "doDeleteVersion":
        case "doAddRelation":
        case "doDeleteRelation":
        case "doCreateRevision":
            $renderType = 'template';
            $key2loop = get_object_vars($opObj);
            foreach($key2loop as $key => $value)
            {
                $guiObj->$key = $value;
            }
			// exceptions
			$guiObj->askForRevision = $opObj->suggest_revision ? 1 : 0;
			$guiObj->askForLog = $opObj->prompt_for_log ? 1 : 0;
            $guiObj->operation = $actionOperation[$argsObj->doAction];
            
            $tplDir = (!isset($opObj->template_dir)  || is_null($opObj->template_dir)) ? $templateCfg->template_dir : $opObj->template_dir;
            $tpl = is_null($opObj->template) ? $templateCfg->default_template : $opObj->template;
            
            $pos = strpos($tpl, '.php');
           	if($pos === false)
           	{
                $tpl = $tplDir . $tpl;      
            }
            else
            {
                $renderType = 'redirect';
            } 
        break;
    }
    
    $req_mgr = new requirement_mgr($dbHandler);
    $guiObj->last_doc_id = $req_mgr->get_last_doc_id_for_testproject($argsObj->tproject_id);
	$guiObj->doAction = $argsObj->doAction;

    switch($renderType)
    {
        case 'template':
        	$smartyObj->assign('gui',$guiObj);
		    $smartyObj->display($tpl);
        	break;  
 
        case 'redirect':
		      header("Location: {$tpl}");
	  		  exit();
        break;

        default:
       	break;
    }

}

/**
 * 
 *
 */
function initialize_gui(&$dbHandler,&$argsObj,&$commandMgr)
{
  $req_spec_mgr = new requirement_spec_mgr($dbHandler);

  $gui = $commandMgr->initGuiBean();
  $gui->req_cfg = config_get('req_cfg');
  $gui->user_feedback = null;
  $gui->action_descr = null;
  $gui->main_descr = lang_get('req_spec_short');
	$gui->preSelectedType = TL_REQ_TYPE_USE_CASE;
	$gui->stay_here = $argsObj->stay_here;
    
  $gui->tproject_id = $argsObj->tproject_id;
  $gui->req_spec_id = $argsObj->req_spec_id;
	$gui->req_version_id = $argsObj->req_version_id;
  $gui->requirement_id = $argsObj->requirement_id;
	if ($argsObj->req_spec_id)
	{
		$gui->requirements_count = $req_spec_mgr->get_requirements_count($gui->req_spec_id);
		$gui->req_spec = $req_spec_mgr->get_by_id($gui->req_spec_id);
	}
  if (isset($gui->req_spec))
  {
     	$gui->main_descr .= config_get('gui_title_separator_1') . $gui->req_spec['title'];
  }

  $gui->grants = new stdClass();
  $gui->grants->req_mgmt = $argsObj->user->hasRight($dbHandler,"mgt_modify_req",$argsObj->tproject_id);
	$gui->grants->mgt_view_events = $argsObj->user->hasRight($dbHandler,"mgt_view_events");
	
	$module = $_SESSION['basehref'] . 'lib/requirements/';
	$context = "tproject_id={$gui->tproject_id}&requirement_id={$gui->requirement_id}" .
			       "&req_spec_id={$gui->req_spec_id}";

	$gui->actions = new stdClass();
	$gui->actions->req_view = $module . "reqView.php?{$context}"; 

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
	checkSecurityClearance($db,$userObj,$env,array('mgt_view_req','mgt_modify_req'),'and');
}
?>