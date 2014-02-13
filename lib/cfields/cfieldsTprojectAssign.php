<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later.
 *
 * @filesource	cfieldsTprojectAssign.php
 * @package 	TestLink
 * @copyright 	2005,2011 TestLink community 
 * @link 		http://www.teamst.org/index.php
 *
 * @internal revisions
 * 20110417 - franciscom - BUGID 4429: Code refactoring to remove global coupling as much as possible
 *
**/
require_once(dirname(__FILE__) . "/../../config.inc.php");
require_once("common.php");
testlinkInitPage($db);
$templateCfg = templateConfiguration();
$cfield_mgr = new cfield_mgr($db);
$args = init_args($cfield_mgr->tree_manager);
checkRights($db,$_SESSION['currentUser'],$args);


switch($args->doAction)
{
    case 'doAssign':
	    $cfield_ids = array_keys($args->cfield);
	    $cfield_mgr->link_to_testproject($args->tproject_id,$cfield_ids);
	    break;

    case 'doUnassign':
	    $cfield_ids = array_keys($args->cfield);
	    $cfield_mgr->unlink_from_testproject($args->tproject_id,$cfield_ids);
	    break;

    case 'doReorder':
	    $cfield_ids = array_keys($args->display_order);
	    $cfield_mgr->set_display_order($args->tproject_id,$args->display_order);
        if( !is_null($args->location) )
        {
        	$cfield_mgr->setDisplayLocation($args->tproject_id,$args->location);
        }
	    break;

    case 'doActiveMgmt':
		$my_cf = array_keys($args->hidden_active_cfield);
		if(!isset($args->active_cfield))
		{
			$cfield_mgr->set_active_for_testproject($args->tproject_id,$my_cf,0);
		}
		else
		{
			$active = null;
			$inactive = null;
			foreach($my_cf as $cf_id)
			{
				if(isset($args->active_cfield[$cf_id]))
				{
					$active[] = $cf_id;
				}
				else
				{
					$inactive[] = $cf_id;
				}	
			}

			if(!is_null($active))
			{
				$cfield_mgr->set_active_for_testproject($args->tproject_id,$active,1);
			}
			if(!is_null($inactive))
			{
				$cfield_mgr->set_active_for_testproject($args->tproject_id,$inactive,0);
			}	
		}
		break;
}

// Get all available custom fields
$cfield_map = $cfield_mgr->get_all();

$gui = new stdClass();
$gui->locations=createLocationsMenu($cfield_mgr->getLocations());
$gui->tproject_id = $args->tproject_id;
$gui->tproject_name = $args->tproject_name;
$gui->my_cf = $cfield_mgr->get_linked_to_testproject($args->tproject_id);

$cf2exclude = is_null($gui->my_cf) ? null :array_keys($gui->my_cf);
$gui->other_cf = $cfield_mgr->get_all($cf2exclude);
$gui->cf_available_types = $cfield_mgr->get_available_types();
$gui->cf_allowed_nodes = array();

$allowed_nodes = $cfield_mgr->get_allowed_nodes();
foreach($allowed_nodes as $verbose_type => $type_id)
{
	$gui->cf_allowed_nodes[$type_id] = lang_get($verbose_type);
}

$smarty = new TLSmarty();
$smarty->assign('gui',$gui);
$smarty->display($templateCfg->template_dir . $templateCfg->default_template);


/**
 * create object with all user inputs
 *
 * @internal revisions
 * 20110417 - franciscom - BUGID 4429: Code refactoring to remove global coupling as much as possible
 */
function init_args(&$treeMgr)
{
  	$_REQUEST = strings_stripSlashes($_REQUEST);
    $args = new stdClass();
     
    $key2search = array('doAction','cfield','display_order','location','hidden_active_cfield','active_cfield');
    
    foreach($key2search as $key)
    {
        $args->$key = isset($_REQUEST[$key]) ? $_REQUEST[$key] : null;
    }
    
    // Need comments
    if (!$args->cfield)
	{
	  $args->cfield = array();
	}
	
	$args->tproject_id = isset($_REQUEST['tproject_id']) ? intval($_REQUEST['tproject_id']) : 0;
	$args->tproject_name = '';
	if( $args->tproject_id > 0 )
	{
		$dummy = $treeMgr->get_node_hierarchy_info($args->tproject_id);
		$args->tproject_name = $dummy['name'];
	}

	return $args;
}


/**
 * 
 *
 */
function checkRights(&$db,&$userObj,$argsObj)
{
	$env['tproject_id'] = isset($argsObj->tproject_id) ? $argsObj->tproject_id : 0;
	$env['tplan_id'] = isset($argsObj->tplan_id) ? $argsObj->tplan_id : 0;
	checkSecurityClearance($db,$userObj,$env,array('cfield_management'),'and');
}


/**
 * @parame map of maps with locations of CF
 *         key: item type: 'testcase','testsuite', etc
 *
 */
function createLocationsMenu($locations)
{
	$menuContents=null;
	$items = $locations['testcase'];
	
	// loop over status for user interface, because these are the statuses
	// user can assign while executing test cases
	
	foreach($items as $code => $key4label)
	{
		$menuContents[$code] = lang_get($key4label); 
	}
	
	return $menuContents;
}

?>