<?php
/** 
* 	TestLink Open Source Project - http://testlink.sourceforge.net/
* 
* 	@filesource getrequirementnodes.php
* 	@author 	Francisco Mancardi
* 
*   **** IMPORTANT *****   
*   Created using Ext JS example code
*
* 	Is the tree loader, will be called via AJAX.
*   Ext JS automatically will pass $_REQUEST['node']   
*   Other arguments will be added by TL php code that needs the tree.
*   
*   This tree is used to navigate Test Project, and is used in following feature:
*
*   - Create test suites, test cases on test project
*   - Assign keywords to test cases
*   - Assign requirements to test cases
*
*	@internal revision
*   @since 2.0
*	20110903 - franciscom - TICKET 4661 - req spec revisions     
*/
require_once('../../config.inc.php');
require_once('common.php');
testlinkInitPage($db);

$_REQUEST=strings_stripSlashes($_REQUEST);

$root_node=isset($_REQUEST['root_node']) ? $_REQUEST['root_node']: null;
$node=isset($_REQUEST['node']) ? $_REQUEST['node'] : $root_node;
$filter_node=isset($_REQUEST['filter_node']) ? $_REQUEST['filter_node'] : null;
$show_children=isset($_REQUEST['show_children']) ? $_REQUEST['show_children'] : 1;
$operation=isset($_REQUEST['operation']) ? $_REQUEST['operation']: 'manage';
$tproject_id=isset($_REQUEST['tproject_id']) ? intval($_REQUEST['tproject_id']) : 0;


$nodes = display_children($db,$tproject_id,$root_node,$node,$filter_node,$show_children,$operation);
echo json_encode($nodes);

/*

*/
function display_children($dbHandler,$tproject_id,$root_node,$parent,$filter_node,
                          $show_children=ON,$operation='manage') 
{             
    $tables = tlObjectWithDB::getDBTables(array('requirements','nodes_hierarchy','node_types','req_specs'));
	$cfg = config_get('req_cfg');
	$forbidden_parent['testproject'] = 'none';
	$forbidden_parent['requirement'] = 'testproject';
	$forbidden_parent['requirement_spec'] = 'none';
    
    switch($operation)
    {
        case 'print':
        	$js_function=array('testproject' => 'TPROJECT_PTP_RS',
        	                   'requirement_spec' =>'TPROJECT_PRS', 'requirement' => 'openLinkedReqWindow');
        break;
        
        case 'manage':
        default:
            $js_function = array('testproject' => 'TPROJECT_REQ_SPEC_MGMT',
                                 'requirement_spec' =>'REQ_SPEC_MGMT', 'requirement' => 'REQ_MGMT');
        	break;  
    }
    
    $nodes = null;
	$node2exclude =	"'testcase','testsuite','testcase_version','testplan','requirement_spec_revision'";
    $node2exclude .= $show_children ? '' : ",'requirement'";

    $sql = " SELECT NHA.*, NT.description AS node_type, RSPEC.doc_id " . 
           " FROM {$tables['nodes_hierarchy']} NHA JOIN {$tables['node_types']}  NT " .
           " ON NHA.node_type_id=NT.id " .
           " AND NT.description NOT IN ({$node2exclude}) " .
           " LEFT OUTER JOIN {$tables['req_specs']} RSPEC " .
           " ON RSPEC.id = NHA.id " . 
           " WHERE NHA.parent_id = {$parent} ";
    
    // DEBUG file_put_contents('/tmp/getrequirementnodes.php.txt', $sql);                            
    if(!is_null($filter_node) && $filter_node > 0 && $parent == $root_node)
    {
       $sql .= " AND NHA.id = {$filter_node} ";  
    }
    $sql .= " ORDER BY NHA.node_order ";    

    $nodeSet = $dbHandler->get_recordset($sql);
	if(!is_null($nodeSet)) 
	{
        $sql =  " SELECT DISTINCT req_doc_id AS doc_id,NHA.id" .
                " FROM {$tables['requirements']} REQ JOIN {$tables['nodes_hierarchy']} NHA ON NHA.id = REQ.id  " .
                " JOIN {$tables['nodes_hierarchy']}  NHB ON NHA.parent_id = NHB.id " . 
                " JOIN {$tables['node_types']} NT ON NT.id = NHA.node_type_id " .
                " WHERE NHB.id = {$parent} AND NT.description = 'requirement'";
        $requirements = $dbHandler->fetchRowsIntoMap($sql,'id');

	    $treeMgr = new tree($dbHandler);
	    $ntypes = $treeMgr->get_available_node_types();
		$peerTypes = array('target' => $ntypes['requirement'], 'container' => $ntypes['requirement_spec']); 
	    foreach($nodeSet as $key => $row)
	    {
	        $path['text'] = htmlspecialchars($row['name']);                                  
	        $path['id'] = $row['id'];                                                           
        
           	 // this attribute/property is used on custom code on drag and drop
	        $path['position'] = $row['node_order'];                                                   
            $path['leaf'] = false;
 	        $path['cls'] = 'folder';
 	        
 	        // Important:
 	        // We can add custom keys, and will be able to access it using
 	        // public property 'attributes' of object of Class Ext.tree.TreeNode 
 	        // 
 	        $path['testlink_node_type']	= $row['node_type'];		                                 
	        $path['testlink_node_name'] = $path['text']; // already htmlspecialchars() done

            $path['forbidden_parent'] = 'none';
            switch($row['node_type'])
            {
                case 'testproject':
	                $path['href'] = "javascript:EP({$path['id']})";
                    $path['forbidden_parent'] = $forbidden_parent[$row['node_type']];
	                break;

                case 'requirement_spec':
                	// BUGID 0003003: EXTJS does not count # req's
                    $req_list = array();
	                $treeMgr->getAllItemsID($row['id'],$req_list,$peerTypes);

	                $path['href'] = "javascript:" . $js_function[$row['node_type']] . 
	                				"({$tproject_id},{$path['id']})";
	                $path['text'] = htmlspecialchars($row['doc_id'] . ":") . $path['text'];
                    $path['forbidden_parent'] = $forbidden_parent[$row['node_type']];
   	        		if(!is_null($req_list))
	        		{
	        			$item_qty = count($req_list);
	        			$path['text'] .= " ({$item_qty})";   
	        		}
					
					
	        		
	                break;

                case 'requirement':
	                $path['href'] = "javascript:" . $js_function[$row['node_type']] . 
	                				"({$tproject_id},{$path['id']})";
	                $path['text'] = htmlspecialchars($requirements[$row['id']]['doc_id'] . ":") . $path['text'];
	                $path['leaf']	= true;
                    $path['forbidden_parent'] = $forbidden_parent[$row['node_type']];
	                break;
            }

            $nodes[] = $path;                                                                        
	    }	// foreach	
    }
	return $nodes;                                                                             
}                                                                                               