<?php
/** 
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later. 
 *
 * Manages test plan operations and related items like Custom fields, 
 * Builds, Custom fields, etc
 *
 * @filesource	resultsByStatus.php
 * @package 	  TestLink
 * @copyright 	2007-2012, TestLink community 
 * @link 		    http://www.teamst.org/index.php
 *
 *
 * @internal revisions
 */

require('../../config.inc.php');
require_once('common.php');
require_once('displayMgr.php');
require_once('users.inc.php');
require_once('exec.inc.php'); // used for bug string lookup
if (config_get('interface_bugs') != 'NO')
{
  require_once(TL_ABS_PATH. 'lib' . DIRECTORY_SEPARATOR . 'bugtracking' .
               DIRECTORY_SEPARATOR . 'int_bugtracking.php');
}
testlinkInitPage($db);

$templateCfg = templateConfiguration();
$resultsCfg = config_get('results');
$statusCode = $resultsCfg['status_code'];

$args = init_args($statusCode);
checkRights($db,$_SESSION['currentUser'],$args);


$gui = initializeGui($statusCode,$args);

$tplan_mgr = new testplan($db);
$tproject_mgr = new testproject($db);
$tcase_mgr = new testcase($db);
$tplan_info = $tplan_mgr->get_by_id($args->tplan_id);
$tproject_info = $tproject_mgr->get_by_id($args->tproject_id);


$getOpt = array('outputFormat' => 'map');
$gui->platformSet = $tplan_mgr->getPlatforms($args->tplan_id,$getOpt);
$show_platforms = !is_null($gui->platformSet);
if( is_null($gui->platformSet) )
{
	$gui->platformSet = array('');
}
$gui->bugInterfaceOn = config_get('bugInterfaceOn');
$bugInterface = null;
if ($gui->bugInterfaceOn) {
	$bugInterface = config_get('bugInterface');
}

$labels = init_labels(array('deleted_user' => null, 'design' => null, 'execution' => null,
                            'execution_history' => null));

$gui->tplan_name = $tplan_info['name'];
$gui->tproject_name = $tproject_info['name'];
$testCaseCfg = config_get('testcase_cfg');

$history_img = TL_THEME_IMG_DIR . "history_small.png";
$exec_img = TL_THEME_IMG_DIR . "exec_icon.png";
$edit_img = TL_THEME_IMG_DIR . "edit_icon.png";

$mailCfg = buildMailCfg($gui);


$arrOwners = getUsersForHtmlOptions($db);

$fl=$tproject_mgr->tree_manager->get_children($args->tproject_id,
                                        array( 'testcase', 'exclude_me',
                                               'testplan' => 'exclude_me',
                                               'requirement_spec' => 'exclude_me' ));

$loop2do = count($fl);
$topLevelSuites=null;
$myRBB = null;
for($idx=0 ; $idx < $loop2do; $idx++)
{
	$topLevelSuites[$fl[$idx]['id']]=array('name' => $fl[$idx]['name'], 'items' => null);
}

if( $args->type == $statusCode['not_run'] )
{
	//BUGID 3722
	$cfg = config_get('results');
	$filters = array('exec_status' => $cfg['status_code']['not_run']);
	$options = array('output' => 'array', 'details' => 'summary');
	$myRBB = $tplan_mgr->get_linked_tcversions($args->tplan_id,$filters, $options);
	$user_key='user_id';
	
	//to be able to use only one php file to generate not run and failed/blocked report
	//we need to manipulate the myRBB array for not run report to match the same array
	//structure as on failed/blocked report: output-array vs output-mapOfMap
	//only manipulate the array if it has results to not pretend the array has content
	if(count($myRBB) > 0 ) {
		$myRBB = array(0 => $myRBB);
	}
	
}
else
{
	$filters = array('exec_status' => array($args->type));
	//mapOfMapPlatformBuild because we need all executions of all builds for each platform
	$options = array('output' => 'mapOfMapExecPlatform' , 'last_execution' => true, 'only_executed' => true, 'details' => 'summary',
	                 'execution_details' => 'add_build');
	$myRBB = $tplan_mgr->get_linked_tcversions($args->tplan_id,$filters,$options);
	$user_key='tester_id';
}
//echo "<pre>"; print_r($myRBB); echo "</pre>";
if( !is_null($myRBB) and count($myRBB) > 0 )
{
    $pathCache=null;
    $topCache=null;
    $levelCache=null;
    $gdx=0;
	foreach($myRBB as $item)
	{
		foreach($item as $testcase) {
		    $suiteName='';
			$bugString='';
		    if( $testcase[$user_key] == 0 )
		    {
		    	$testerName = lang_get('nobody');
		    }
		    else
		    {
		    	if (array_key_exists($testcase[$user_key], $arrOwners))
				{
				   $testerName = $arrOwners[$testcase[$user_key]];
				}
				else
				{
				    // user id has been deleted
				    $testerName = sprintf($labels['deleted_user'],$testcase[$user_key]);
				}
			}

		    // create linked icons

		    $exec_link = "";
		    $build_id = null;
		    if (isset($testcase['build_id'])) {
			    $build_id = $testcase['build_id'];
		    } else if (isset($testcase['assigned_build_id'])) {
			    $build_id = $testcase['assigned_build_id'];
		    }
		    
			$exec_history_link = "<a href=\"javascript:openExecHistoryWindow({$testcase['tc_id']});\">" .
			                     "<img title=\"{$labels['execution_history']}\" src=\"{$history_img}\" /></a> ";
		    
		    if (!is_null($build_id)) {
			    $exec_link = "<a href=\"javascript:openExecutionWindow(" .
				             "{$testcase['tc_id']}, {$testcase['tcversion_id']}, {$build_id}, " .
				             "{$args->tplan_id}, {$testcase['platform_id']});\">" .
				             "<img title=\"{$labels['execution']}\" src=\"{$exec_img}\" /></a> ";
		    }

			$edit_link = "<a href=\"javascript:openTCEditWindow({$gui->tproject_id},{$testcase['tc_id']});\">" .
						 "<img title=\"{$labels['design']}\" src=\"{$edit_img}\" /></a> ";

		    $ext_id = buildExternalIdString($tproject_info['prefix'], $testcase['external_id']);
			$tcaseName = $ext_id . ':' . $testcase['name'];

		    // 20101007 - asimon - BUGID 3857
		    $image_link = "<!-- " . sprintf("%010d", $testcase['external_id']) . " -->" . 
		                  $exec_history_link . $exec_link . $edit_link . $tcaseName;

		    // 20101013 - asimon - use linkto.php for emailed links
		    $dl = str_replace(" ", "%20", $args->basehref) . 'linkto.php?tprojectPrefix=' . urlencode($tproject_info['prefix']) .
		          '&item=testcase&id=' . urlencode($ext_id);
			$mail_link = "<a href='{$dl}'>{$tcaseName}</a>";
			
		    $tcLink = $args->format != FORMAT_HTML ? $mail_link : $image_link;

		    //$tcLink = '<a href="lib/testcases/archiveData.php?edit=testcase&id=' .
			//          $testcase['tc_id'] . '">' . htmlspecialchars($tcaseName) . '</a>';
			
			if( !isset($pathCache[$testcase['tc_id']]) )
			{
				$dummy=$tcase_mgr->getPathLayered(array($testcase['tc_id']));	
				$pathCache[$testcase['tc_id']] = $dummy[$testcase['testsuite_id']]['value'];
				$levelCache[$testcase['tc_id']] = $dummy[$testcase['testsuite_id']]['level'];
	            $ky=current(array_keys($dummy)); 
	            $topCache[$testcase['tc_id']]=$ky;
			}
		    $verbosePath = $pathCache[$testcase['tc_id']];
		    $level = $levelCache[$testcase['tc_id']];
		    
			if( $args->type == $statusCode['not_run'] )
			{
				
				$build_mgr = new build_mgr($db);
				if (isset($testcase['assigned_build_id'])) {
					$build_info = $build_mgr->get_by_id($testcase['assigned_build_id']);
					$testcase['assigned_build_name'] = $build_info['name'];
				} else {
					$testcase['assigned_build_name'] = lang_get('unassigned');
				}
				
				// When not run, test case version, is the version currently linked to test plan
				$topLevelSuites[$topCache[$testcase['tc_id']]]['items'][$level][] = 
								array('suiteName' => $verbosePath, 'level' => $level,
								      'testTitle' => $tcLink,
								      'testVersion' => $testcase['version'], 
								      'platformName' => htmlspecialchars($testcase['platform_name']),
								      'buildName' => htmlspecialchars($testcase['assigned_build_name']),
								      'testerName' => htmlspecialchars($testerName),
								      'notes' => strip_tags($testcase['summary']),
								      'platformID' => $testcase['platform_id']);
			}			
			else
			{
				// BUGID 3492
				// BUGID 3356
				// When test case has been runned, version must be get from executions.tcversion_number 
				if ($gui->bugInterfaceOn) {
					$bugs = get_bugs_for_exec($db, $bugInterface, $testcase['exec_id']);
					
					//count all test cases that have no bug linked
					if (count($bugs) == 0) {
						$gui->without_bugs_counter += 1;
					}
					
					foreach ($bugs as $bug) {
						$bugString .= $bug['link_to_bts'] . '<br/>';
 
					}
				}
				
				$topLevelSuites[$topCache[$testcase['tc_id']]]['items'][$level][] = 
								array('suiteName' => $verbosePath, 'testTitle' => $tcLink,
				                      'testVersion' => $testcase['tcversion_number'], 
				                      'platformName' => htmlspecialchars($testcase['platform_name']),
				                      'buildName' => htmlspecialchars($testcase['build_name']),
				                      'testerName' => htmlspecialchars($testerName),
				                      'localizedTS' => $testcase['execution_ts'],
				                      'notes' => strip_tags($testcase['execution_notes']),
				                      'bugString' => $bugString,
				                      'platformID' => $testcase['platform_id']);
			} 
		}  //END foreach item  
	}//END foreach MyRBB 

    // Rearrange for display
	$key2loop=array_keys($topLevelSuites);
	foreach($key2loop as $key)
	{
		if(	is_null($topLevelSuites[$key]['items']) )
		{
			unset($topLevelSuites[$key]);
		}
	}
	$key2loop=array_keys($topLevelSuites);
	$idx=0;
	foreach($key2loop as $key)
	{
		$elem=&$topLevelSuites[$key]['items'];
		$levelSet=array_keys($topLevelSuites[$key]['items']);
		foreach($levelSet as $level)
		{
			foreach ($elem[$level] as $item)
			{
				unset($item['level']);
				unset($item['platformID']);
				if (!$show_platforms)
				{
					unset($item['platformName']);
				}
		        
				$gui->dataSet[] = $item;
		        $idx++;
			}  		
		}
    }    	
} else {
	if($args->type == $statusCode['not_run']) {
		$gui->warning_msg = lang_get('no_notrun');
	}
	if($args->type == $statusCode['failed']) {
		$gui->warning_msg = lang_get('no_failed');
	}
	if($args->type == $statusCode['blocked']) {
		$gui->warning_msg = lang_get('no_blocked');
	}
}	

$tableOptions = array('status_not_run' => ($args->type == $statusCode['not_run']),
                      'bugInterfaceOn' => $gui->bugInterfaceOn,
                      'format' => $args->format,
                      'show_platforms' => $show_platforms);

$gui->tableSet[] = buildMatrix($gui->dataSet, $args, $tableOptions ,$gui->platformSet);

$smarty = new TLSmarty();
$smarty->assign('gui', $gui );
displayReport($templateCfg->template_dir . $templateCfg->default_template, $smarty, $args->format,$mailCfg);

/**
* Function returns number of Test Cases in the Test Plan
* @deprecated 1.9
* @return string Link of Test ID + Title
*/
function buildTCLink($tcID,$tcversionID, $title, $buildID,$testCaseExternalId, $tplanId)
{
	$title = htmlspecialchars($title);
	$suffix = htmlspecialchars($testCaseExternalId) . ":&nbsp;<b>" . $title. "</b></a>";
	//Added tplan_id as a parameter - amitkhullar -BUGID 2267
	$testTitle = '<a href="lib/execute/execSetResults.php?level=testcase&build_id='
				 . $buildID . '&id=' . $tcID . '&version_id='. $tcversionID . '&tplan_id=' . $tplanId .'">';
	$testTitle .= $suffix;

	return $testTitle;
}


/**
 * 
 *
 */
function init_args($statusCode)
{
    $iParams = array("format" => array(tlInputParameter::INT_N),
		             "tproject_id" => array(tlInputParameter::INT_N),
		             "tplan_id" => array(tlInputParameter::INT_N),
    	             "type" => array(tlInputParameter::STRING_N,0,1));
	$args = new stdClass();
	R_PARAMS($iParams,$args);
	
	$args->user = $_SESSION['currentUser'];
	$args->basehref = $_SESSION['basehref'];

	return $args;
}

/**
 * initializeGui
 *
 */
function initializeGui($statusCode,&$argsObj)
{
    $guiObj = new stdClass();
    $guiObj->tproject_id = $argsObj->tproject_id; 
    $guiObj->tplan_id = $argsObj->tplan_id; 
    
    // Count for the Failed Issues whose bugs have to be raised/not linked. 
    $guiObj->without_bugs_counter = 0; 
    $guiObj->dataSet = null;
    $guiObj->title = null;
    $guiObj->type = $argsObj->type;
    $guiObj->warning_msg = '';

    // Humm this may be can be configured ???
    foreach(array('failed','blocked','not_run') as $verbose_status)
    {
        if($argsObj->type == $statusCode[$verbose_status])
        {
            $guiObj->title = lang_get('list_of_' . $verbose_status);
            break;
        }  
    }
    if(is_null($guiObj->title))
    {
    	tlog('wrong value of GET type');
    	exit();
    }
    
    return $guiObj;    
}

/**
 * checkRights
 *
 */
function checkRights(&$db,&$userObj,$argsObj)
{
	$env['tproject_id'] = isset($argsObj->tproject_id) ? $argsObj->tproject_id : 0;
	$env['tplan_id'] = isset($argsObj->tplan_id) ? $argsObj->tplan_id : 0;
	checkSecurityClearance($db,$userObj,$env,array('testplan_metrics'),'and');
}


/**
 * 
 *
 */
function buildMailCfg(&$guiObj)
{
	$labels = array('testplan' => lang_get('testplan'), 'testproject' => lang_get('testproject'));
	$cfg = new stdClass();
	$cfg->cc = ''; 
	$cfg->subject = $guiObj->title . ' : ' . $labels['testproject'] . ' : ' . $guiObj->tproject_name . 
	                ' : ' . $labels['testplan'] . ' : ' . $guiObj->tplan_name;
	                 
	return $cfg;
}

/**
 * Builds ext-js rich table to display matrix results
 *
 * @param map dataSet: data to be displayed on matrix
 *
 * return tlExtTable
 *
 */
function buildMatrix($dataSet, &$args, $options = array(), $platforms)
{
	$default_options = array(
		'bugInterfaceOn' => false,
		'show_platforms' => false,
		'status_not_run' => false,
		'format' => FORMAT_HTML,
	);
	$options = array_merge($default_options, $options);
	$columns = array();
	$columns[] = array('title_key' => 'title_test_suite_name', 'width' => 80, 'type' => 'text');
	$columns[] = array('title_key' => 'title_test_case_title', 'width' => 80, 'type' => 'text');
	$columns[] = array('title_key' => 'version', 'width' => 30);
	if ($options['show_platforms'])
	{
		$columns[] = array('title_key' => 'platform', 'width' => 60, 'filter' => 'list', 'filterOptions' => $platforms);
	}
	if( $options['status_not_run'] )
	{
		$columns[] = array('title_key' => 'th_build', 'width' => 35);
		$columns[] = array('title_key' => 'assigned_to', 'width' => 60);
		$columns[] = array('title_key' => 'summary', 'width' => 150, 'type' => 'text');
	}
	else
	{
		$columns[] = array('title_key' => 'th_build', 'width' => 35);
		$columns[] = array('title_key' => 'th_run_by', 'width' => 60);
		$columns[] = array('title_key' => 'th_date', 'width' => 60);
		$columns[] = array('title_key' => 'title_execution_notes', 'width' => 150, 'type' => 'text');
		if ($options['bugInterfaceOn'])
		{
			$columns[] = array('title_key' => 'th_bugs', 'type' => 'text');
		}
	}

	if ($options['format'] == FORMAT_HTML)
	{
		$matrix = new tlExtTable($columns, $dataSet, 'tl_table_results_by_status');
		
		//if not run report: sort by test suite
		//blocked, failed report: sort by platform (if enabled) else sort by date
		$sort_name = 0;
		if ($options['status_not_run']) {
			$sort_name = lang_get('assigned_to');
		} else {
			if ($options['show_platforms']) {
				$sort_name = lang_get('platform');
			} else {
				$sort_name = lang_get('th_date');
			}
		}
		
		$matrix->setSortByColumnName($sort_name);
		$matrix->setGroupByColumnName(lang_get('th_build'));

		$matrix->addCustomBehaviour('text', array('render' => 'columnWrap'));
	}
	else
	{
		$matrix = new tlHTMLTable($columns, $dataSet, 'tl_table_results_by_status');
	}
	return $matrix;
}
