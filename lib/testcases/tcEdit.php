<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later.
 *
 * Test Case and Test Steps operations
 *
 * @filesource	tcEdit.php
 * @package 	  TestLink
 * @author 		  TestLink community
 * @copyright 	2007-2012, TestLink community 
 * @link 		    http://www.teamst.org/index.php
 *
 * @internal revisions
 * @since 2.0
 *
 **/
require_once("../../config.inc.php");
require_once("common.php");
require_once("opt_transfer.php");
require_once("web_editor.php");

$cfg = getCfg();
testlinkInitPage($db);
$optionTransferName = 'ot';

$tcase_mgr = new testcase($db);
$tproject_mgr = new testproject($db);
$tree_mgr = new tree($db);
$tsuite_mgr = new testsuite($db);

$args = init_args($db,$cfg,$tproject_mgr);
require_once(require_web_editor($cfg->webEditorCfg['type']));

$commandMgr = new testcaseCommands($db,$_SESSION['currentUser'],$args->tproject_id);
$commandMgr->setTemplateCfg(templateConfiguration());

$testCaseEditorKeys = array('summary' => 'summary','preconditions' => 'preconditions');
$oWebEditor = createWebEditors($args->basehref,$cfg->webEditorCfg,$testCaseEditorKeys);

$sqlResult = "";
$init_inputs = true; 
$show_newTC_form = 0;

$opt_cfg = initializeOptionTransferCfg($optionTransferName,$args,$tproject_mgr);
$gui = initializeGui($db,$args,$cfg,$tcase_mgr,$_SESSION['currentUser']);

$smarty = new TLSmarty();
$smarty->templateCfg = templateConfiguration('tcEdit');

$name_ok = 1;
$doRender = false;
$edit_steps = false;

$pfn = $args->doAction;

switch($args->doAction)
{
  case "doUpdate":
  case "doAdd2testplan":
  case "doCreateNewVersion":
    $op = $commandMgr->$pfn($args,$_REQUEST);
  break;
	
	case "edit":  
	case "create":  
	case "doCreate":  
    $oWebEditorKeys = array_keys($oWebEditor->cfg);
    $op = $commandMgr->$pfn($args,$oWebEditorKeys,$_REQUEST);
    $doRender = true;
  break;
    
	case "delete":  
	case "doDelete":  
  case "createStep":
  case "editStep":
  case "doCreateStep":
  case "doCopyStep":
  case "doUpdateStep":
  case "doDeleteStep":
  case "doReorderSteps":
  case "doInsertStep":
    $op = $commandMgr->$pfn($args,$_REQUEST);
    $edit_steps = true;
    $doRender = true;
  break;
}

if( $doRender )
{
	renderGui($args,$gui,$op,$commandMgr->getTemplateCfg(),$cfg,$edit_steps);
	exit();
}

if($args->delete_tc_version)
{
	$status_quo_map = $tcase_mgr->get_versions_status_quo($args->tcase_id);
	$exec_status_quo = $tcase_mgr->get_exec_status($args->tcase_id);
  $gui->delete_mode = 'single';
  
  // Delete single Test Case version did not work
  // We do not need to check here if test case version has already been
  // execute because "Delete this version" button is hidden in this case
  $gui->delete_enabled = 1;

  $msg = '';
	$sq = null;
	if(!is_null($exec_status_quo))
	{
		if(isset($exec_status_quo[$args->tcversion_id]))
		{
			$sq = array($args->tcversion_id => $exec_status_quo[$args->tcversion_id]);
		}	
	}

	if(intval($status_quo_map[$args->tcversion_id]['executed']))
	{
		$msg = lang_get('warning') . TITLE_SEP . lang_get('delete_linked_and_exec');
	}
	else if(intval($status_quo_map[$args->tcversion_id]['linked']))
	{
      	$msg = lang_get('warning') . TITLE_SEP . lang_get('delete_linked');
	}

	$tcinfo = $tcase_mgr->get_by_id($args->tcase_id,$args->tcversion_id);

	$gui->title = lang_get('title_del_tc') . 
	              TITLE_SEP_TYPE3 . lang_get('version') . " " . $tcinfo[0]['version'];
	$gui->testcase_name = $tcinfo[0]['name'];
	$gui->testcase_id = $args->tcase_id;
	$gui->tcversion_id = $args->tcversion_id;
	$gui->delete_message = $msg;
	$gui->exec_status_quo = $sq;
	$gui->refreshTree = 0;

  $smarty->assign('gui',$gui);
  $templateCfg = templateConfiguration('tcDelete');
  $smarty->display($templateCfg->template_dir . $templateCfg->default_template);
}
else if($args->move_copy_tc)
{
	// need to get the testproject for the test case
	// new dBug($args);
	// $tproject_id = $tcase_mgr->get_testproject($args->tcase_id);
	$the_tc_node = $tree_mgr->get_node_hierarchy_info($args->tcase_id);
	$tc_parent_id = $the_tc_node['parent_id'];
	$the_xx = $tproject_mgr->gen_combo_test_suites($args->tproject_id);

	$the_xx[$the_tc_node['parent_id']] .= ' (' . lang_get('current') . ')';
	$tc_info = $tcase_mgr->get_by_id($args->tcase_id);

	$container_qty = count($the_xx);
	$gui->move_enabled = 1;
	if($container_qty == 1)
	{
		// move operation is nonsense
		$gui->move_enabled = 0;
	}
    $gui->top_checked = 'checked=checked';
	$gui->bottom_checked = '';
	$gui->old_container = $the_tc_node['parent_id']; // original container
	$gui->array_container = $the_xx;
	$gui->testcase_id = $args->tcase_id;
	$gui->name = $tc_info[0]['name'];
	$gui->tproject_id = $args->tproject_id;

	$smarty->assign('gui', $gui);
  $templateCfg = templateConfiguration('tcMove');
  $smarty->display($templateCfg->template_dir . $templateCfg->default_template);
}
else if($args->do_move)
{
	$result = $tree_mgr->change_parent($args->tcase_id,$args->new_container_id);
  $tree_mgr->change_child_order($args->new_container_id,$args->tcase_id,
                                $args->target_position,$cfg->exclude_node_types);

  $gui->refreshTree = $args->refreshTree;
  $gui->modify_tc_rights = $_SESSION['currentUser']->hasRight($db,"mgt_modify_tc",$args->tproject_id);
	// $tsuite_mgr->show($smarty,$args->tproject_id,$gui,$templateCfg->template_dir,$args->old_container_id);
	$identity = new stdClass();
	$identity->tproject_id = $args->tproject_id;
	$identity->id = $args->old_container_id;
	$tsuite_mgr->show($smarty,$gui,$identity);
	
	
}
else if($args->do_copy)
{
	$user_feedback='';
	$msg = '';
	$options = array('check_duplicate_name' => config_get('check_names_for_duplicates'),
                     'action_on_duplicate_name' => config_get('action_on_duplicate_name'),
                     'copy_also' => $args->copy);

	$result = $tcase_mgr->copy_to($args->tcase_id,$args->new_container_id,$args->user_id,$options);
	$msg = $result['msg'];
    if($result['status_ok'])
    {
		    $tree_mgr->change_child_order($args->new_container_id,$result['id'],
		                                  $args->target_position,$cfg->exclude_node_types);
        
		    $ts_sep = config_get('testsuite_sep');
		    $tc_info = $tcase_mgr->get_by_id($args->tcase_id);
		    $container_info = $tree_mgr->get_node_hierarchy_info($args->new_container_id);
		    $container_path = $tree_mgr->get_path($args->new_container_id);
		    $path = '';
		    foreach($container_path as $key => $value)
		    {
		    	$path .= $value['name'] . $ts_sep;
		    }
		    $path = trim($path,$ts_sep);
		    $user_feedback = sprintf(lang_get('tc_copied'),$tc_info[0]['name'],$path);
    }

	$gui->refreshTree = $args->refreshTree;
	$viewer_args['action'] = 'copied';
	$viewer_args['refreshTree']=$args->refreshTree? 1 : 0;
	$viewer_args['msg_result'] = $msg;
	$viewer_args['user_feedback'] = $user_feedback;

  $identity = new stdClass();
  $identity->tproject_id = $args->tproject_id;
  $identity->id = $args->tcase_id;
  $identity->version_id = $args->tcversion_id;
	$tcase_mgr->show($smarty,$gui,$identity);
}
else if($args->do_activate_this || $args->do_deactivate_this)
{
	$gui->loadOnCancelURL = buildLoadOnCancelURL($args);
	
	$active_status = 0;
	$viewer_args['action'] = "deactivate_this_version";
	if($args->do_activate_this)
	{
		$active_status = 1;
		$viewer_args['action'] = "activate_this_version";
	}


	$tcase_mgr->update_active_status($args->tcase_id, $args->tcversion_id, $active_status);
	$viewer_args['action'] = $action_result;
	$viewer_args['refreshTree']=DONT_REFRESH;

  $identity = new stdClass();
  $identity->tproject_id = $args->tproject_id;
  $identity->id = $args->tcase_id;
  $identity->version_id = testcase::ALL_VERSIONS;
	$tcase_mgr->show($smarty,$gui,$identity);
}

// --------------------------------------------------------------------------
if ($show_newTC_form)
{
	
	// Create test case with same title, after submit, all data lost 
  $tc_default=array('id' => 0, 'name' => '');
  $tc_default['importance'] = $init_inputs ? $tlCfg->testcase_importance_default : $args->importance;
  $tc_default['execution_type'] = $init_inputs ? TESTCASE_EXECUTION_TYPE_MANUAL : $args->exec_type;
  $tc_default['status'] = $init_inputs ? $args->tcStatusCfg['status_code']['draft'] : $args->tc_status;
    

  	foreach ($oWebEditor->cfg as $key => $value)
  	{
  	    $of = &$oWebEditor->editor[$key];
  	    $rows = $oWebEditor->cfg[$key]['rows'];
  	    $cols = $oWebEditor->cfg[$key]['cols'];
  	    if( $init_inputs)
  	    {
  	      	$of->Value = getItemTemplateContents('testcase_template', $of->InstanceName, '');
  	    }
  	    else
  	    {
  	  		$of->Value = $args->$key;
		}
		
		$smarty->assign($key, $of->CreateHTML($rows,$cols));
  	} // foreach ($a_oWebEditor_cfg as $key)


	$filters=$tcase_mgr->buildCFLocationMap();
	foreach($filters as $locationKey => $locationFilter)
	{ 
		$cf_smarty[$locationKey] = 
			$tcase_mgr->html_table_of_custom_field_inputs($args->tcase_id,$args->container_id,'design','',
			                                              null,null,null,$locationFilter);
  }
	$gui->cf = $cf_smarty;
	$gui->tc = $tc_default;
	$gui->containerID = $args->container_id;
	$smarty->assign('gui',$gui);

  $templateCfg = templateConfiguration('tcNew');
  $smarty->display($templateCfg->template_dir . $templateCfg->default_template);
}



/*
  function:

  args:

  returns:
  
  @internal revisions
	
*/
function init_args(&$dbHandler,&$cfgObj,&$tprojectMgr)
{
  $_REQUEST = strings_stripSlashes($_REQUEST);
  
  $args = new stdClass();
	$args->basehref = $_SESSION['basehref'];
  $args->user_id = $_SESSION['userID'];

  $args->tproject_id = intval($_REQUEST['tproject_id']);

  $args->user = $_SESSION['currentUser'];
	$args->userGrants = new stdClass();
	$args->userGrants->mgt_modify_tc = $args->user->hasRight($dbHandler,'mgt_modify_tc',$args->tproject_id);
	$args->userGrants->mgt_view_req = $args->user->hasRight($dbHandler,"mgt_view_req",$args->tproject_id);
	$args->userGrants->testplan_planning = $args->user->hasRight($dbHandler,"testplan_planning",$args->tproject_id);

  $args->exec_type = isset($_REQUEST['exec_type']) ? $_REQUEST['exec_type'] : TESTCASE_EXECUTION_TYPE_MANUAL;
  $args->importance = isset($_REQUEST['importance']) ? $_REQUEST['importance'] : config_get('testcase_importance_default');
  $args->name = isset($_REQUEST['testcase_name']) ? $_REQUEST['testcase_name'] : null;
  
  $k2null = array('summary','preconditions','steps','expected_results','step_set','tcaseSteps','goback_url');
  foreach($k2null as $key)
  {
    $args->$key = isset($_REQUEST[$key]) ? $_REQUEST[$key] : null;
  }


  $id2get = array('tcase_id' => 'testcase_id','tcversion_id' => 'tcversion_id',
                  'new_container_id' => 'new_container', 'old_container_id' => 'old_container',
                  'has_been_executed' => 'has_been_executed', 'step_number' => 'step_number',
                  'step_id' => 'step_id');
  foreach($id2get as $prop => $input)
  {
    $args->$prop = isset($_REQUEST[$input]) ? intval($_REQUEST[$input]) : 0;
  }

  $id2get = array('tsuiteID' => array('tsuiteID','testsuite_id'));
  foreach($id2get as $prop => $inputSet)
  {
    $args->$prop = 0;
    foreach($inputSet as $input)
    {
      if( isset($_REQUEST[$input]) )
      {
        $args->$prop = intval($_REQUEST[$input]);
        break;
      }
    }
  }
  
  $dummy = getConfigAndLabels('testCaseStatus','code');
  $args->tcStatusCfg['status_code'] = $dummy['cfg'];
  $args->tcStatusCfg['code_label'] = $dummy['lbl'];
  $args->tc_status = isset($_REQUEST['tc_status']) ? intval($_REQUEST['tc_status']) : 
                     $args->tcStatusCfg['status_code']['draft'];

	$dk = 'estimated_execution_duration';
  $args->$dk = trim(isset($_REQUEST[$dk]) ? $_REQUEST[$dk] : '');
  $args->doAction = isset($_REQUEST['doAction']) ? $_REQUEST['doAction'] : '';

	$key2loop = array('edit_tc' => 'edit', 'delete_tc' => 'delete','do_delete' => 'doDelete',
					          'create_tc' => 'create','do_create' => 'doCreate');
	foreach($key2loop as $key => $action)
	{
		if( isset($_REQUEST[$key]) )
		{
			$args->doAction = $action;
			break;
		}
	}
	
	$key2loop = array('move_copy_tc','delete_tc_version','do_move','do_copy',
					          'do_create_new_version','do_delete_tc_version','stay_here');
	foreach($key2loop as $key)
	{
		$args->$key = isset($_REQUEST[$key]) ? 1 : 0;
	}

  $args->do_activate_this = isset($_REQUEST['activate_this_tcversion']) ? 1 : 0;
  $args->do_deactivate_this = isset($_REQUEST['deactivate_this_tcversion']) ? 1 : 0;
  $args->target_position = isset($_REQUEST['target_position']) ? $_REQUEST['target_position'] : 'bottom';
  
  $key2loop=array("keyword_assignments","requirement_assignments");
  foreach($key2loop as $key)
  {
     $args->copy[$key]=isset($_REQUEST[$key]) ? true : false;    
  }    
  
  $args->show_mode = (isset($_REQUEST['show_mode']) && $_REQUEST['show_mode'] != '') ? $_REQUEST['show_mode'] : null;


	$dummy = $tprojectMgr->get_by_id($args->tproject_id);
	$args->opt_requirements = $dummy['opt']->requirementsEnabled;
	$args->requirementsEnabled = $dummy['opt']->requirementsEnabled;
	$args->automationEnabled = $dummy['opt']->automationEnabled;
	$args->testPriorityEnabled = $dummy['opt']->testPriorityEnabled;

    
  // TABBED BROWSING
  $args->refreshTree = testproject::getUserChoice($args->tproject_id,array('tcaseTreeRefreshOnAction','edit_mode'));
    

	$action2check = array("editStep" => true,"createStep" => true, "doCreateStep" => true,
						            "doUpdateStep" => true, "doInsertStep" => true);
	if( isset($action2check[$args->doAction]) )
	{
		$cfgObj->webEditorCfg = getWebEditorCfg('steps_design');	
	}   

  $args->assigned_keyword_list = isset($_REQUEST['assigned_keyword_list'])? $_REQUEST['assigned_keyword_list'] : "";


  return $args;
}


/*
  function: initializeOptionTransferCfg
  args :
  returns: 
*/
function initializeOptionTransferCfg($otName,&$argsObj,&$tprojectMgr)
{
    $otCfg = new stdClass();
    switch($argsObj->doAction)
    {
    	case 'create':
    	case 'edit':
    	case 'doCreate':
        	$otCfg = opt_transf_empty_cfg();
        	$otCfg->global_lbl = '';
        	$otCfg->from->lbl = lang_get('available_kword');
        	$otCfg->from->map = $tprojectMgr->get_keywords_map($argsObj->tproject_id);
        	$otCfg->to->lbl = lang_get('assigned_kword');
    	break;
    }
    
    $otCfg->js_ot_name = $otName;
    return $otCfg;
}

/*
  function: createWebEditors

      When using tinymce or none as web editor, we need to set rows and cols
      to appropriate values, to avoid an ugly ui.
      null => use default values defined on editor class file
      Rows and Cols values are useless for FCKeditor

  args :
  
  returns: object
  
*/
function createWebEditors($basehref,$editorCfg,$editorSet=null,$edit_steps=false)
{
    $specGUICfg=config_get('spec_cfg');
    $layout=$specGUICfg->steps_results_layout;

    // Rows and Cols configuration
    $owe = new stdClass();

    $cols = array('steps' => array('horizontal' => 38, 'vertical' => 44),
                  'expected_results' => array('horizontal' => 38, 'vertical' => 44));

	$owe->cfg = null;
	if($edit_steps == false) {
		$owe->cfg = array('summary' => array('rows'=> null,'cols' => null),
		                  'preconditions' => array('rows'=> null,'cols' => null) );
	} else {
		$owe->cfg = array('steps' => array('rows'=> null,'cols' => null) ,
		                  'expected_results' => array('rows'=> null, 'cols' => null));
	}
    
    $owe->editor = array();
    $force_create = is_null($editorSet);
    foreach ($owe->cfg as $key => $value)
    {
    	if( $force_create || isset($editorSet[$key]) )
    	{
    		$owe->editor[$key] = web_editor($key,$basehref,$editorCfg);
    	}
    	else
    	{
    		unset($owe->cfg[$key]);
    	}
    }
    
    return $owe;
}

/*
  function: getCfg
  args :
  returns: object
*/
function getCfg()
{
  $cfg=new stdClass();
  $cfg->treemenu_default_testcase_order = config_get('treemenu_default_testcase_order');
  $cfg->spec = config_get('spec_cfg');
  $cfg->exclude_node_types = array('testplan' => 1, 'requirement' => 1, 'requirement_spec' => 1);
  $cfg->tcase_template = config_get('testcase_template');
  $cfg->webEditorCfg = getWebEditorCfg('design');

  $cfg->editorKeys = new stdClass();
  $cfg->editorKeys->testcase = array('summary' => true, 'preconditions' => true);    
  $cfg->editorKeys->step = array('steps' => true, 'expected_results' => true);    

  return $cfg;
}

/**
 * 
 *
 */
function initializeGui(&$dbHandler,&$argsObj,$cfgObj,&$tcaseMgr,&$userObj)
{
	$guiObj = new stdClass();
	$guiObj->tproject_id = $argsObj->tproject_id;
	$guiObj->tsuiteID = $argsObj->tsuiteID;

	$guiObj->editorType = $cfgObj->webEditorCfg['type'];
	$guiObj->grants = new stdClass();
  $guiObj->grants->requirement_mgmt = $userObj->hasRight($dbHandler,"mgt_modify_req",$argsObj->tproject_id) || 
										                  $userObj->hasRight($dbHandler,"req_tcase_link_management",$argsObj->tproject_id); 

	$guiObj->opt_requirements = $argsObj->opt_requirements; 
	$guiObj->requirementsEnabled = $argsObj->requirementsEnabled; 
	$guiObj->automationEnabled = $argsObj->automationEnabled; 
	$guiObj->testPriorityEnabled = $argsObj->testPriorityEnabled;

	$guiObj->action_on_duplicated_name = 'generate_new';
	$guiObj->show_mode = $argsObj->show_mode;
  $guiObj->has_been_executed = $argsObj->has_been_executed;
  $guiObj->attachments = null;
	$guiObj->parent_info = null;
	$guiObj->user_feedback = '';
	$guiObj->stay_here = $argsObj->stay_here;
	$guiObj->steps_results_layout = config_get('spec_cfg')->steps_results_layout;

	$guiObj->loadOnCancelURL = buildLoadOnCancelURL($argsObj);

	$guiObj->direct_link = $tcaseMgr->buildDirectWebLink($_SESSION['basehref'],$argsObj->tcase_id,$argsObj->tproject_id);
	$guiObj->domainTCStatus = $argsObj->tcStatusCfg['code_label'];
	
	return $guiObj;
}

/**
 * manage GUI rendering
 *
 */
function renderGui(&$argsObj,$guiObj,$opObj,$templateCfg,$cfgObj,$edit_steps)
{
    $smartyObj = new TLSmarty();
    
    // need by webeditor loading logic present on inc_head.tpl
    $smartyObj->assign('editorType',$guiObj->editorType);  

    $renderType = 'none';
    //
    // key: operation requested (normally received from GUI on doAction)
    // value: operation value to set on doAction HTML INPUT
    // This is useful when you use same template (example xxEdit.tpl), for create and edit.
    // When template is used for create -> operation: doCreate.
    // When template is used for edit -> operation: doUpdate.
    //              
    // used to set value of: $guiObj->operation
    //
    $actionOperation = array('create' => 'doCreate', 'doCreate' => 'doCreate',
                             'edit' => 'doUpdate','delete' => 'doDelete', 'doDelete' => '',
                             'createStep' => 'doCreateStep', 'doCreateStep' => 'doCreateStep',
                             'doCopyStep' => 'doUpdateStep',
                             'editStep' => 'doUpdateStep', 'doUpdateStep' => 'doUpdateStep',  
                             'doDeleteStep' => '', 'doReorderSteps' => '',
                             'doInsertStep' => 'doUpdateStep');

	
  $key2work = 'initWebEditorFromTemplate';
  $initWebEditorFromTemplate = property_exists($opObj,$key2work) ? $opObj->$key2work : false;                             
  $key2work = 'cleanUpWebEditor';
  $cleanUpWebEditor = property_exists($opObj,$key2work) ? $opObj->$key2work : false;                             

  $oWebEditor = createWebEditors($argsObj->basehref,$cfgObj->webEditorCfg,null,$edit_steps); 
	foreach ($oWebEditor->cfg as $key => $value)
  {
  	$of = &$oWebEditor->editor[$key];
  	$rows = $oWebEditor->cfg[$key]['rows'];
  	$cols = $oWebEditor->cfg[$key]['cols'];
		switch($argsObj->doAction)
    {
      case "edit":
    	case "delete":
    	case "editStep":
  		  $initWebEditorFromTemplate = false;
  			$of->Value = $argsObj->$key;
  		break;

      case "doCreate":
    	case "doDelete":
    	case "doCopyStep":
    	case "doUpdateStep":
        $initWebEditorFromTemplate = false;
  			$of->Value = $argsObj->$key;
  		break;
  			
      case "create":
			case "doCreateStep":
  		case "doInsertStep":
  		default:	
  				$initWebEditorFromTemplate = true;
  		break;
  	}
    $guiObj->operation = $actionOperation[$argsObj->doAction];
	
  	if(	$initWebEditorFromTemplate )
  	{
			$of->Value = getItemTemplateContents('testcase_template', $of->InstanceName, '');	
		}
		else if( $cleanUpWebEditor )
		{
			$of->Value = '';
		}
		$smartyObj->assign($key, $of->CreateHTML($rows,$cols));
	}
      
	// manage tree refresh
  switch($argsObj->doAction) 
  {
    case "doDelete":
      $guiObj->refreshTree = $argsObj->refreshTree;
    break;
  }

  switch($argsObj->doAction)
  {
      case "edit":
      case "create":
      case "delete":
      case "createStep":
      case "editStep":
      case "doCreate":
      case "doDelete":
      case "doCreateStep":
      case "doUpdateStep":
      case "doDeleteStep":
      case "doReorderSteps":
      case "doCopyStep":
      case "doInsertStep":
          $renderType = 'template';
          
          // Document !!!!
          $key2loop = get_object_vars($opObj);
          foreach($key2loop as $key => $value)
          {
          	$guiObj->$key = $value;
          }
          $guiObj->operation = $actionOperation[$argsObj->doAction];
          
          new dBug($opObj);
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


  switch($renderType)
  {
      case 'template':
        new dBug($tpl);
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


function buildLoadOnCancelURL(&$argsObj)
{

	$ret_url = $_SESSION['basehref'] . "/lib/testcases/archiveData.php?tproject_id={$argsObj->tproject_id}" . 
			   "&edit=testcase&id={$argsObj->tcase_id}&show_mode={$argsObj->show_mode}";
	return $ret_url;
}
?>