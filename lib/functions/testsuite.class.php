<?php
/** 
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 * This script is distributed under the GNU General Public License 2 or later.
 * 
 * @filesource  testsuite.class.php
 * @package     TestLink
 * @author      franciscom
 * @copyright   2005-2012, TestLink community 
 * @link        http://www.teamst.org/index.php
 *
 * @internal revisions
 * @since 2.0
 * 20121026 - franciscom - getNode()
 * 20121013 - franciscom - create() interface changed
 * 20120909 - franciscom - attachment management refactored (see show())
 */

/** include support for attachments */
require_once( dirname(__FILE__) . '/files.inc.php');

/**
 * Test Suite CRUD functionality
 * @package   TestLink
 */
class testsuite extends tlObjectWithAttachments
{
  const NODE_TYPE_FILTER_OFF=null;
  const CHECK_DUPLICATE_NAME=1;
  const DONT_CHECK_DUPLICATE_NAME=0;
  const DEFAULT_ORDER=0;
  const USE_RECURSIVE_MODE = 1;
  
  private $object_table;

  /** @var database handler */
  var $db;
  var $tree_manager;
  var $node_types_descr_id;
  var $node_types_id_descr;
  var $my_node_type;
  var $cfield_mgr;
  var $import_file_types = array("XML" => "XML");
  var $export_file_types = array("XML" => "XML");
 
  // Node Types (NT)
  var $nt2exclude=array('testplan' => 'exclude_me',
                        'requirement_spec'=> 'exclude_me',
                        'requirement'=> 'exclude_me');
                                                  

  var $nt2exclude_children=array('testcase' => 'exclude_my_children',
                                 'requirement_spec'=> 'exclude_my_children');

  /**
   * testplan class constructor
   * 
   * @param resource &$db reference to database handler
   */
  function __construct(&$db)
  {
    $this->db = &$db; 
    
    $this->tree_manager =  new tree($this->db);
    $this->node_types_descr_id = $this->tree_manager->get_available_node_types();
    $this->node_types_id_descr = array_flip($this->node_types_descr_id);
    $this->my_node_type = $this->node_types_descr_id['testsuite'];
    
    $this->cfield_mgr = new cfield_mgr($this->db);
    
    // ATTENTION:
    // second argument is used to set $this->attachmentTableName,property that this calls
    // get from his parent
    // tlObjectWithAttachments::__construct($this->db,'nodes_hierarchy');
    parent::__construct($this->db,"nodes_hierarchy");

    // Must be setted AFTER call to parent constructor
    $this->object_table = $this->tables['testsuites'];

  }


  /*
    returns: map  
             key: export file type code
             value: export file type verbose description 
  */
  function get_export_file_types()
  {
    return $this->export_file_types;
  }


  /*
    function: get_impor_file_types
              getter

    args: -
    
    returns: map  
             key: import file type code
             value: import file type verbose description 

  */
  function get_import_file_types()
  {
    return $this->import_file_types;
  }

  /*
    returns:   hash 
                    $ret['status_ok'] -> 0/1
                    $ret['msg']
                    $ret['id']        -> when status_ok=1, id of the new element
  */
  function create($item)
  {
    $parent_id = intval($item->parent_id);
    $name = trim($item->name);
    $details = $item->details;
    $node_order = is_null($item->order) ? config_get('treemenu_default_testsuite_order') : intval($item->order);

    $prefix_name_for_copy = config_get('prefix_name_for_copy');
    $ret = array('status_ok' => 1, 'id' => 0, 'msg' => 'ok');
  
    if($item->check_duplicate_name)
    {
      $check = $this->tree_manager->nodeNameExists($name,$this->my_node_type,null,$parent_id);
      if( $check['status'] == 1)
      {
        if ($item->action_on_duplicate_name == 'block')
        {
          $ret['status_ok'] = 0;
          $ret['msg'] = sprintf(lang_get('component_name_already_exists'),$name); 
        } 
        else
        {
          $ret['status_ok'] = 1;      
          if($item->action_on_duplicate_name == 'generate_new')
          { 
            $ret['status_ok'] = 1;
            $desired_name = $name;      
            $name = config_get('prefix_name_for_copy') . " " . $desired_name ;      
            $ret['msg'] = sprintf(lang_get('created_with_new_name'),$name,$desired_name); 
          }
        }
      }       
    }
    
    if ($ret['status_ok'])
    {
      // get a new id
      $tsuite_id = $this->tree_manager->new_node($parent_id,$this->my_node_type,
                                                 $name,$node_order);
      $sql = " INSERT INTO {$this->tables['testsuites']} (id,details,author_id,creation_ts) " .
             " VALUES ({$tsuite_id},'" . $this->db->prepare_string($details) . "'," . intval($item->userID) . "," . 
             $this->db->db_now() .  ")";

      $result = $this->db->exec_query($sql);
      if ($result)
      {
        $ret['id'] = $tsuite_id;
      }
    }
    
    return $ret;
  }

  
  /**
   * update
   *
   * @internal revisions
   */
  function update($item)
  {
    $debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
    $ret = array('status_ok' => 0, 'reason' => null, 'msg' => '', 'more' => null);
    $check = $this->tree_manager->nodeNameExists($item->name,$this->my_node_type,$item->id,$item->parent_id);

    if($check['status']==0)
    {
      $where = " WHERE id = " . intval($item->id);  
      $sql = "/* $debugMsg */ " .
             " UPDATE {$this->tables['testsuites']} " .
             " SET details = '" . $this->db->prepare_string($item->details) . "'," .
             " modification_ts = " . $this->db->db_now() . "," .
             " updater_id = " . intval($item->userID) .
             $where . " AND modification_ts = '" . $item->modification_ts . "'";
      $result = $this->db->exec_query($sql);
       
      // Check for midair collision and exit if found
      if( $midAirCollision = ($this->db->Affected_Rows() == 0) )
      {
        // we need to abort gently
        // Using Bugzilla approach, get info about update
        $ret['status_ok'] = 0;
        $ret['reason'] = 'midAirCollision';
        $ret['msg'] = '';
        $ret['more'] = $this->get_by_id($item->id);
        return $ret;      
      }
      
      // Till here everything OK.
      // We can have potentially a 'soft crash' if two or moer people are trying
      // to add test suites with same name
      if ($result)
      {
        $sql = "/* $debugMsg */ UPDATE {$this->tables['nodes_hierarchy']} " .
               " SET name='" .  $this->db->prepare_string($item->name) . "' ";
        if( !is_null($item->order) && intval($item->order) > 0 )
        {
          $sql .= ', node_order=' . $this->db->prepare_int(intval($item->order));     
        }    
        $sql .= $where;
        $result = $this->db->exec_query($sql);
      }
      
      $ret['status_ok']=1;
      $ret['msg']='ok';
      if (!$result)
      {
        $ret['msg'] = $this->db->error_msg();
      }
    }
    else
    {
      $ret['msg']=$check['msg'];
    }
    return $ret;
  }
  
  
  /**
   * Delete a Test suite, deleting:
   * - Children Test Cases
   * - Test Suite Attachments
   * - Test Suite Custom fields 
   * - Test Suite Keywords
   *
   * IMPORTANT/CRITIC: 
   * this can used to delete a Test Suite that contains ONLY Test Cases.
   *
   * This function is needed by tree class method: delete_subtree_objects()
   *
   * To delete a Test Suite that contains other Test Suites delete_deep() 
   * must be used.
   *
   * ATTENTION: may be in future this can be refactored, and written better. 
   *
   */
  function delete($id)
  {
    $tcase_mgr = new testcase($this->db);
    $tsuite_info = $this->get_by_id($id);
  
    $testcases=$this->get_children_testcases($id);
    if (!is_null($testcases))
    {
      foreach($testcases as $the_key => $elem)
      {
          $tcase_mgr->delete($elem['id']);
      }
    }  
      
    // What about keywords ???
    $this->cfield_mgr->remove_all_design_values_from_node($id);
    $this->deleteAttachments($id);  //inherited
    $this->deleteKeywords($id);
      
    $sql = "DELETE FROM {$this->object_table} WHERE id={$id}";
    $result = $this->db->exec_query($sql);
      
    $sql = "DELETE FROM {$this->tables['nodes_hierarchy']} WHERE id={$id}";
    $result = $this->db->exec_query($sql);
  }
  
  
                      
  /*
    function: get_by_name
  
    args : name: testsuite name
    
    returns: array where every element is a map with following keys:
             
             id:  testsuite id (node id)
             details
             name: testsuite name
  
  */
  function get_by_name($name, $parent_id=null)
  {
    $sql = " SELECT TS.*, NH.name, NH.parent_id " .
           " FROM {$this->tables['testsuites']} TS " .
           " JOIN {$this->tables['nodes_hierarchy']} NH " .
           " ON NH.id = TS.id " .
           " WHERE NH.name = '" . $this->db->prepare_string($name) . "'";
    
    if( !is_null($parent_id) )
    {
      $sql .= " AND NH.parent_id = " . $this->db->prepare_int($parent_id);  
    }
    
    $recordset = $this->db->get_recordset($sql);
    return $recordset;
  }
  
  /*
    function: get_by_id
              get info for one (or several) test suite(s)
  
    args : id: testsuite id
    
    returns: map with following keys:
             
             id:  testsuite id (node id) (can be an array)
             details
             name: testsuite name
  
  */
  function get_by_id($id, $order_by = '')
  {
    $debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
    $sql = "/* $debugMsg */ SELECT TS.*, NH.name, NH.node_type_id, NH.node_order, NH.parent_id " .
           "  FROM {$this->tables['testsuites']} TS, " .
           " {$this->tables['nodes_hierarchy']} NH   WHERE TS.id = NH.id AND TS.id "; 

    $sql .= is_array($id) ? " IN (" . implode(',',$id) . ")" : " = {$id} ";
    $sql .= $order_by;
    
    
    $rs = $this->db->fetchRowsIntoMap($sql,'id');
    if( !is_null($rs) )
    {
        $rs = count($rs) == 1 ? current($rs) : $rs;
    }
    return $rs;
  }
  
  
  /*
    function: get_all()
              get array of info for every test suite without any kind of filter.
              Every array element contains an assoc array with test suite info
  
    args : -
    
    returns: array 
  
  */
  function get_all()
  {
    $sql = " SELECT testsuites.*, nodes_hierarchy.name " .
           " FROM {$this->tables['testsuites']} testsuites, " .
           " {$this->tables['nodes_hierarchy']} nodes_hierarchy " . 
           " WHERE testsuites.id = nodes_hierarchy.id";
           
    $recordset = $this->db->get_recordset($sql);
    return($recordset);
  }
  
  
  /**
   * show()
   *
   * args:  smarty [reference]
   *        id 
   *        sqlResult [default = '']
   *        action [default = 'update']
   *        modded_item_id [default = 0]
   * 
   * returns: -
   *
   **/
  function show(&$smarty,$guiObj,$identity,$options = null,$sqlResult = '', $action = 'update')
  {
    
    // need to understand why sometimes $guiObj can be null
    $gui = is_null($guiObj) ? new stdClass() : $guiObj;
    $gui->id = $identity->id;
    $gui->cf = '';

    $common = 'tproject_id=' . $identity->tproject_id . '&tsuiteID=' . $identity->id;
    $import = $smarty->baseHREF . 'lib/testcases/tcImport.php?';
    $export = $smarty->baseHREF . 'lib/testcases/tcExport.php?';
    
    $gui->actions = new stdClass();
    $gui->actions->importTestSuite = $import . $common . '&type=testsuite';
    $gui->actions->exportTestSuite = $export . $common . '&type=testsuite';

    $gui->actions->importTestCases = $import . $common . '&type=testcase';
    $gui->actions->exportTestCases = $export . $common .  '&type=testcase';
    
    $gui->sqlResult = '';
    $gui->sqlAction = '';
    $gui->refreshTree = property_exists($gui,'refreshTree') ? $gui->refreshTree : false;
    $gui->level = 'testsuite';
    
    $my['options'] = array('show_mode' => 'readwrite');   
    $my['options'] = array_merge($my['options'], (array)$options);

    if($my['options']['show_mode'] == 'readonly')
    {       
      $gui->modify_tc_rights = 'no';
    }
      
    if($sqlResult)
    { 
      $gui->sqlResult = $sqlResult;
      $gui->sqlAction = $action;
    }
    
    $gui->tsuite = $this->get_by_id($identity->id);
    $gui->pageTitle = $gui->viewerTitle = lang_get('testsuite') . ' : ' . $gui->tsuite['name'];
    
    $gui->cf = $this->html_table_of_custom_field_values($identity->id);
    $gui->keywords_map = $this->get_keywords_map($identity->id,' ORDER BY keyword ASC ');

    $gui->attach = new stdClass();
    $gui->attach->itemID = $identity->id;
    $gui->attach->dbTable = $this->attachmentTableName;

    $gui->attach->infoSet = null;
    $gui->attach->gui = null;
    list($gui->attach->infoSet,$gui->attach->gui) = $this->buildAttachSetup($identity->id,$my['options']);
    $gui->attach->gui->display=TRUE;
    $gui->attach->enabled = $gui->attach->gui->enabled;

    $gui->tproject_id = $identity->tproject_id;
    
    $gui->keywordsViewHREF = "lib/keywords/keywordsView.php?tproject_id=$identity->tproject_id " .
                             ' target="mainframe" class="bold" ' .
                             ' title="' . lang_get('menu_manage_keywords') . '"';
    $smarty->assign('gui',$gui);
    $smarty->display('testsuites/testSpecViewTestSuite.tpl');
  }
  
  
  /*
    function: viewer_edit_new
              Implements user interface (UI) for edit testuite and 
              new/create testsuite operations.
              
  
    args : 
    
           ... (TOBE DONE)  
           [messages]: default null
                       map with following keys
                       [result_msg]: default: null used to give information to user
                       [user_feedback]: default: null used to give information to user
                       
           [$userInput]
                            
    returns: -
  
    rev :
  */
  function viewer_edit_new(&$smarty,$gui,$action,$context,$oWebEditor,$messages=null,$userInput=null)
  {
    $internalMsg = array('result_msg' => null,  'user_feedback' => null);
    if( !is_null($messages) )
    {
      $internalMsg = array_merge($internalMsg, $messages);
    }
    $smarty->assign('sqlResult', $internalMsg['result_msg']);

    $useUserInput = is_null($userInput) ? 0 : 1;
    
    $tproject_id = $context['tproject_id'];
    $parent_id = $context['parent_id'];
    $id = $context['id'];
    
    
    $gui->cf = -2; // MAGIC must be explained

    $gui->parentID = $parent_id;
    $gui->tsuite = null;
    $gui->containerType = property_exists($gui,'containerType') ? $gui->containerType : 'testsuite';
    $gui->page_title = lang_get($gui->containerType);
    $gui->user_feedback = $internalMsg['user_feedback'];

 
    $tpl = $smarty->tlTemplateCfg->template_dir;
    switch($action)
    {
      case 'edit_testsuite':
      case 'update_testsuite':
        // update has been added to cope with Mid Air Collision management
        $tpl .= 'testSuiteEdit.tpl';
      break;

      case 'new_testsuite':
      case 'add_testsuite':
        $tpl .= 'testSuiteNew.tpl';
        $parent_info = $this->tree_manager->get_node_hierarchy_info($parent_id);
        $gui->page_title .= ' : ' . $parent_info['name']; 
      break;
    }

    if($useUserInput)
    {
      $webEditorData = $userInput;
      $look4 = array('name','container_name');
      foreach($look4 as $key)
      {
        if( isset($userInput[$key]) )
        {
          $gui->name = trim($userInput[$key]); 
        }
      }
    }
    else
    {
      $gui->name = '';
      if ($action == 'edit_testsuite')
      {
        $gui->tsuite = $this->get_by_id($id);
        $gui->name = $gui->tsuite['name'];
      } 
      $webEditorData = $gui->tsuite; // NOT TOO CLEAR
    }
    
    $gui->cf = $this->html_table_of_custom_field_inputs($id,$parent_id,'design','',$userInput);
    
    // webeditor
    // templates will be also used after 'add_testsuite', when presenting a new test suite 
    // with all other fields empty.
    if( !$useUserInput )
    {
      if( ($action == 'new_testsuite' || $action == 'add_testsuite') && !is_null($oWebEditor->templates) )
      {
         // need to understand if need to use templates
         $webEditorData = $this->_initializeWebEditors($oWebEditor->inputNames,$oWebEditor->templates);
      } 
    }
    foreach ($oWebEditor->inputNames as $key)
    {
      // Warning:
      // the data assignment will work while the keys in $the_data are identical
      // to the keys used on $oWebEditor.
      $of = &$oWebEditor->jsControls[$key];         
      $of->Value = isset($webEditorData[$key]) ? $webEditorData[$key] : null;
      $smarty->assign($key, $of->CreateHTML());
    }
    
    echo __METHOD__ . '<br>';
    new dBug($gui);
    $smarty->assign('gui',$gui); 
    $smarty->display($tpl);
  }
  
  
  /*
    function: copy_to
              deep copy one testsuite to another parent (testsuite or testproject).
              
  
    args : id: testsuite id (source or copy)
           parent_id:
           user_id: who is requesting copy operation
           [check_duplicate_name]: default: 0 -> do not check
                                            1 -> check for duplicate when doing copy
                                                 What to do if duplicate exists, is controlled
                                                 by action_on_duplicate_name argument.
                                                 
           [action_on_duplicate_name argument]: default: 'allow_repeat'.
                                                Used when check_duplicate_name=1.
                                                Specifies how to react if duplicate name exists.
                                                
                                                 
                                                 
    
    returns: map with foloowing keys:
             status_ok: 0 / 1
             msg: 'ok' if status_ok == 1
             id: new created if everything OK, -1 if problems.
  
  @internal revisions
  20110405 - franciscom - BUGID 4374: When copying a project, external TC ID is not preserved
              added option 'preserve_external_id' needed by tcase copy_to()
  */
  function copy_to($id, $parent_id, $user_id,$options=null,$mappings=null)
  {

        $my['options'] = array('check_duplicate_name' => 0,
                     'action_on_duplicate_name' => 'allow_repeat',
                     'copyKeywords' => 0, 'copyRequirements' => 0,
                     'preserve_external_id' => false);  

      $my['options'] = array_merge($my['options'], (array)$options);

      $my['mappings'] = array();
      $my['mappings'] = array_merge($my['mappings'], (array)$mappings);


    $copyTCaseOpt = array('preserve_external_id' => $my['options']['preserve_external_id'],
                'copy_also' => 
                          array('keyword_assignments' => $my['options']['copyKeywords'],
                                'requirement_assignments' => $my['options']['copyRequirements']) ); 
      
      $copyOptions = array('keyword_assignments' => $my['options']['copyKeywords']);
      
    $tcase_mgr = new testcase($this->db);
    $tsuite_info = $this->get_by_id($id);
    
    $op = $this->create($parent_id,$tsuite_info['name'],$tsuite_info['details'],
                        $tsuite_info['node_order'],$my['options']['check_duplicate_name'],
                        $my['options']['action_on_duplicate_name']);
    
    $op['mappings'][$id] = $op['id']; 
    $new_tsuite_id = $op['id'];
    
    // Work on root of these subtree
      // Attachments
      // Keyword assignment
      // Custom Field values
      $this->copy_attachments($id,$new_tsuite_id);
    if( $my['options']['copyKeywords'] )
    {
      $kmap = isset($my['mappings']['keywords']) ? $my['mappings']['keywords'] : null;
        $this->copy_keyword_assignment($id,$new_tsuite_id,$kmap);
    }
        $this->copy_cfields_values($id,$new_tsuite_id);
    
    
    $my['filters'] = array('exclude_children_of' => array('testcase' => 'exclude my children'));
    $subtree = $this->tree_manager->get_subtree($id,$my['filters']);
    if (!is_null($subtree))
    {
      
      $parent_decode=array();
        $parent_decode[$id]=$new_tsuite_id;
      foreach($subtree as $the_key => $elem)
      {
        $the_parent_id=$parent_decode[$elem['parent_id']];
        switch ($elem['node_type_id'])
        {
          case $this->node_types_descr_id['testcase']:
            // BUGID 4239: forgotten parameter $mappings caused requirement assignments to use wrong IDs
            $tcOp = $tcase_mgr->copy_to($elem['id'],$the_parent_id,$user_id,$copyTCaseOpt,$my['mappings']);
            $op['mappings'] += $tcOp['mappings'];
            break;
            
          case $this->node_types_descr_id['testsuite']:
            $tsuite_info = $this->get_by_id($elem['id']);
            $ret = $this->create($the_parent_id,$tsuite_info['name'],
                                 $tsuite_info['details'],$tsuite_info['node_order']);      
            
              $parent_decode[$elem['id']] = $ret['id'];
              $op['mappings'][$elem['id']] = $ret['id']; 
              
                $tcase_mgr->copy_attachments($elem['id'],$ret['id']);
            if( $my['options']['copyKeywords'] )
            {
                $this->copy_keyword_assignment($elem['id'],$ret['id'],$kmap);
            }
                $this->copy_cfields_values($elem['id'],$ret['id']);
                
            break;
        }
      }
    }
    return $op;
  }
  
  
  /*
    function: get_subtree
              Get subtree that has choosen testsuite as root.
              Only nodes of type: 
              testsuite and testcase are explored and retrieved.
  
    args: id: testsuite id
          [recursive_mode]: default false
          
    
    returns: map
             see tree->get_subtree() for details.
  
  */
  function get_subtree($id,$recursive_mode=false)
  {
      $my['options']=array('recursive' => $recursive_mode);
    $my['filters'] = array('exclude_node_types' => $this->nt2exclude,
                             'exclude_children_of' => $this->nt2exclude_children);
    
    $subtree = $this->tree_manager->get_subtree($id,$my['filters'],$my['options']);
      return $subtree;
  }
  
  
  
  /*
    function: get_testcases_deep
              get all test cases in the test suite and all children test suites
              no info about tcversions is returned.
  
    args : id: testsuite id
           [details]: default 'simple'
                      Structure of elements in returned array, changes according to
                      this argument:
            
                      'only_id'
                      Array that contains ONLY testcase id, no other info.
                      
                      'simple'
                      Array where each element is a map with following keys.
                      
                      id: testcase id
                      parent_id: testcase parent (a test suite id).
                      node_type_id: type id, for a testcase node
                      node_order
                      node_table: node table, for a testcase.
                      name: testcase name
                      external_id: 
                      
                      'full'
                      Complete info about testcase for LAST TCVERSION 
                      TO BE IMPLEMENTED
    
    returns: array
  
  */
  function get_testcases_deep($id, $details = 'simple')
  {
    $tcase_mgr = new testcase($this->db);
    $testcases = null;
    $subtree = $this->get_subtree($id);
    $only_id=($details=='only_id') ? true : false;                    
    $doit=!is_null($subtree);
    $parentSet=null;
    
    if($doit)
    {
      $testcases = array();
      $tcNodeType = $this->node_types_descr_id['testcase'];
      $prefix = null;
      foreach ($subtree as $the_key => $elem)
      {
        if($elem['node_type_id'] == $tcNodeType)
        {
          if ($only_id)
          {
            $testcases[] = $elem['id'];
          }
          else
          {
            // After first call passing $prefix with right value, avoids a function call
            // inside of getExternalID();
            list($identity,$prefix,$glueChar,$external) = $tcase_mgr->getExternalID($elem['id'],null,$prefix);
            $elem['external_id'] = $identity; 
            $testcases[]= $elem;
            $parentSet[$elem['parent_id']]=$elem['parent_id'];
          } 
        }
      }
      $doit = count($testcases) > 0;
    }
    
      if($doit && $details=='full')
      {
          $parentNodes=$this->tree_manager->get_node_hierarchy_info($parentSet);
      
          $rs=array();
          foreach($testcases as $idx => $value)
          {
            // 20110806 - franciscom - TICKET 4692
        $item=$tcase_mgr->get_last_version_info($value['id'],
                            array('output' => full, 'get_steps' => true));
              $item['tcversion_id']=$item['id'];
              $tsuite['tsuite_name']=$parentNodes[$value['parent_id']]['name'];
              unset($item['id']);
              $rs[]=$value+$item+$tsuite;   
          }
          $testcases=$rs;
      }
    return $testcases; 
  }
  
  
  /**
   * get_children_testcases
   * get only test cases with parent=testsuite without doing a deep search
   *
   */
  function get_children_testcases($id, $details = 'simple')
  {
      $testcases=null;
      $only_id=($details=='only_id') ? true : false;                    
      $subtree=$this->tree_manager->get_children($id,array('testsuite' => 'exclude_me'));
    $doit=!is_null($subtree);
    if($doit)
    {
      $tsuite=$this->get_by_id($id);
      $tsuiteName=$tsuite['name'];
      $testcases = array();
      foreach ($subtree as $the_key => $elem)
      {
        if ($only_id)
        {
          $testcases[] = $elem['id'];
        }
        else
        {
          $testcases[]= $elem;
        } 
      }
      $doit = count($testcases) > 0;
    }
      
      if($doit && $details=='full')
      {
          $rs=array();
          $tcase_mgr = new testcase($this->db);
          foreach($testcases as $idx => $value)
          {
            // 20110806 - franciscom - TICKET 4692
        $item=$tcase_mgr->get_last_version_info($value['id'],
                            array('output' => full, 'get_steps' => true));
              $item['tcversion_id']=$item['id'];
              $parent['tsuite_name']=$tsuiteName;
              unset($item['id']);
              $rs[]=$value+$item+$tsuite;   
          }
          $testcases=$rs;
      }
      return $testcases; 
  }  
  
  
  
  
  /*
    function: delete_deep
  
    args : $id
    
    returns: 
  
    rev :
         20070602 - franciscom
         added delete attachments
  */
  function delete_deep($id)
  {
    // BUGID 3147 - Delete test project with requirements defined crashed with memory exhausted
      $this->tree_manager->delete_subtree_objects($id,$id,'',array('testcase' => 'exclude_tcversion_nodes'));
      $this->delete($id);
  } // end function
  
  

  
  
  /*
    function: initializeWebEditors
  
    args:
    
    returns: 
  
  */
  private function _initializeWebEditors($WebEditors,$itemTemplateCfgKey)
  {
    $wdata=array();
    foreach ($WebEditors as $key => $html_name)
    {
      $wdata[$html_name] = getItemTemplateContents($itemTemplateCfgKey, $html_name, '');
    } 
    return $wdata;
  }
  
  
  /*
    function: getKeywords
              Get keyword assigned to a testsuite.
              Uses table object_keywords.
              
              Attention:
              probably write on obejct_keywords has not been implemented yet,
              then right now thie method can be useless.
               
  
    args: id: testsuite id
          kw_id: [default = null] the optional keyword id
    
    returns: null if nothing found.
             array, every elemen is map with following structure:
             id
             keyword
             notes
    
    rev : 
          20070116 - franciscom - BUGID 543
  
  */
  function getKeywords($id,$kw_id = null)
  {
    $debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
    
    $sql = "/* $debugMsg */ SELECT keyword_id,keywords.keyword, notes " .
           " FROM {$this->tables['object_keywords']}, {$this->tables['keywords']} keywords " .
           " WHERE keyword_id = keywords.id AND fk_id = {$id}";
    if (!is_null($kw_id))
    {
      $sql .= " AND keyword_id = {$kw_id}";
    } 
    $map_keywords = $this->db->fetchRowsIntoMap($sql,'keyword_id');
    
    return($map_keywords);
  } 
  
  
  /*
    function: get_keywords_map
              All keywords for a choosen testsuite
  
              Attention:
              probably write on obejct_keywords has not been implemented yet,
              then right now thie method can be useless.
  
  
    args :id: testsuite id
          [order_by_clause]: default: '' -> no order choosen
                             must be an string with complete clause, i.e.
                             'ORDER BY keyword'
  
    
    
    returns: map: key: keyword_id
                  value: keyword
    
  
  */
  function get_keywords_map($id,$order_by_clause='')
  {
    $debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
    $sql = "/* $debugMsg */ SELECT keyword_id,keywords.keyword " .
           " FROM {$this->tables['object_keywords']}, {$this->tables['keywords']} keywords " .
           " WHERE keyword_id = keywords.id ";

    if (is_array($id))
    {
      $sql .= " AND fk_id IN (".implode(",",$id).") ";
    }
    else
    {
      $sql .= " AND fk_id = {$id} ";
    }
      
    $sql .= $order_by_clause;
  
    $map_keywords = $this->db->fetchColumnsIntoMap($sql,'keyword_id','keyword');
    return($map_keywords);
  } 
  
  
  /**
   * 
   *
   */
  function addKeyword($id,$kw_id)
  {
    $debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
    $status = 1;
    $kw = $this->getKeywords($id,$kw_id);
    if( ($doLink = !sizeof($kw)) )
    {
      $sql = "/* $debugMsg */ INSERT INTO {$this->tables['object_keywords']} " .
             " (fk_id,fk_table,keyword_id) VALUES ($id,'nodes_hierarchy',$kw_id)";
          $status = $this->db->exec_query($sql) ? 1 : 0;
    } 
    return $status;
  }
  
  
  /*
    function: addKeywords
  
    args :
    
    returns: 
  
  */
  function addKeywords($id,$kw_ids)
  {
    $status = 1;
    $num_kws = sizeof($kw_ids);
    for($idx = 0; $idx < $num_kws; $idx++)
    {
      $status = $status && $this->addKeyword($id,$kw_ids[$idx]);
    }
    return($status);
  }
  
  
  /*
    function: deleteKeywords
  
    args :
    
    returns: 
  
  */
  function deleteKeywords($id,$kw_id = null)
  {
    $sql = " DELETE FROM {$this->tables['object_keywords']} WHERE fk_id = {$id} ";
    if (!is_null($kw_id))
    {
      $sql .= " AND keyword_id = {$kw_id}";
    } 
    return($this->db->exec_query($sql));
  }
  
  /*
    function: exportTestSuiteDataToXML
  
    args :
    
    returns: 
    
    rev: 20090204 - franciscom - added node_order
  
  */
  function exportTestSuiteDataToXML($container_id,$tproject_id,$optExport = array())
  {
    static $keywordMgr;
    static $getLastVersionOpt = array('output' => 'minimun');
    static $tcase_mgr;
    
    if(is_null($keywordMgr))
    {
          $keywordMgr = new tlKeyword();      
    } 
    
    $xmlTC = null;
    $doRecursion = isset($optExport['RECURSIVE']) ? $optExport['RECURSIVE'] : 0;
    if($doRecursion)
    {
        $cfXML = null;
      $kwXML = null;
      $tsuiteData = $this->get_by_id($container_id);
      if( isset($optExport['KEYWORDS']) && $optExport['KEYWORDS'])
      {
        $kwMap = $this->getKeywords($container_id);
        if ($kwMap)
        {
          $kwXML = "<keywords>" . $keywordMgr->toXMLString($kwMap,true) . "</keywords>";
        } 
      }
      if (isset($optExport['CFIELDS']) && $optExport['CFIELDS'])
        {
            $cfMap = (array)$this->get_linked_cfields_at_design($container_id,null,null,$tproject_id);
        if( count($cfMap) > 0 )
          {
              $cfXML = $this->cfield_mgr->exportValueAsXML($cfMap);
          } 
        }
          $xmlTC = "<testsuite name=\"" . htmlspecialchars($tsuiteData['name']). '" >' .
                   "\n<node_order><![CDATA[{$tsuiteData['node_order']}]]></node_order>\n" .
                 "<details><![CDATA[{$tsuiteData['details']}]]> \n{$kwXML}{$cfXML}</details>";
    }
    else
    {
      $xmlTC = "<testcases>";
      }
    
    $test_spec = $this->get_subtree($container_id,self::USE_RECURSIVE_MODE);
    
    $childNodes = isset($test_spec['childNodes']) ? $test_spec['childNodes'] : null ;
    $tcase_mgr=null;
    if( !is_null($childNodes) )
    {
        $loop_qty=sizeof($childNodes); 
        for($idx = 0;$idx < $loop_qty;$idx++)
        {
          $cNode = $childNodes[$idx];
          $nTable = $cNode['node_table'];
          if ($doRecursion && $nTable == 'testsuites')
          {
            $xmlTC .= $this->exportTestSuiteDataToXML($cNode['id'],$tproject_id,$optExport);
          }
          else if ($nTable == 'testcases')
          {
              if( is_null($tcase_mgr) )
              {
                $tcase_mgr = new testcase($this->db);
            }
            $xmlTC .= $tcase_mgr->exportTestCaseDataToXML($cNode['id'],testcase::LATEST_VERSION,
                                                          $tproject_id,true,$optExport);
          }
        }
    }   
    $xmlTC .= $doRecursion ? "</testsuite>" : "</testcases>"; 
    return $xmlTC;
  }
  
  
  // -------------------------------------------------------------------------------
  // Custom field related methods
  // -------------------------------------------------------------------------------
  /*
    function: get_linked_cfields_at_design
              
              
    args: $id
          [$parent_id]:
          [$filters]: default: null
          
    returns: hash
    
    rev :
    20110129 - franciscom - BUGID 4202
  */
    function get_linked_cfields_at_design($id,$parent_id=null,$filters=null,$tproject_id = null) 
    {
      if (!$tproject_id)
      {
        $tproject_id = $this->getTestProjectFromTestSuite($id,$parent_id);
      }
      $cf_map = $this->cfield_mgr->get_linked_cfields_at_design($tproject_id,cfield_mgr::CF_ENABLED,
                                    $filters,'testsuite',$id);
      return $cf_map;
    }
    
    /**
     * getTestProjectFromTestSuite()
     *
     */
    function getTestProjectFromTestSuite($id,$parent_id)
    {
      $tproject_id = $this->tree_manager->getTreeRoot( (!is_null($id) && $id > 0) ? $id : $parent_id);
      return $tproject_id;
    }
  
  /*
    function: get_linked_cfields_at_execution
              
              
    args: $id
          [$parent_id]
          [$filters]
                keys: $show_on_execution: default: null
                          1 -> filter on field show_on_execution=1
                          0 or null -> don't filter
          
          
    returns: hash
    
    rev :
     20110129 - franciscom - BUGID 4202
  */
  function get_linked_cfields_at_execution($id,$parent_id=null,$filters=null,$tproject_id=null) 
  {
    
    if (!$tproject_id)
    {
      $the_path=$this->tree_manager->get_path(!is_null($id) ? $id : $parent_id);
      $path_len=count($the_path);
      $tproject_id=($path_len > 0)? $the_path[$path_len-1]['parent_id'] : $parent_id;
    }
  
    $cf_map=$this->cfield_mgr->get_linked_cfields_at_design($tproject_id,cfield_mgr::CF_ENABLED,
                                    $filters,'testsuite',$id);
    return($cf_map);
  }
  
  
  
  /*
    function: html_table_of_custom_field_inputs
              
              
    args: $id
          [$parent_id]: need when you call this method during the creation
                        of a test suite, because the $id will be 0 or null.
                        
          [$scope]: 'design','execution'
          
    returns: html string
    
  */
  function html_table_of_custom_field_inputs($id,$parent_id=null,$scope='design',$name_suffix='',$input_values=null) 
  {
    $cf_smarty='';
      $method_suffix = $scope=='design' ? $scope : 'execution';
      $method_name = "get_linked_cfields_at_{$method_suffix}";
      $cf_map=$this->$method_name($id,$parent_id);

    if(!is_null($cf_map))
    {
      $cf_smarty = $this->cfield_mgr->html_table_inputs($cf_map,$name_suffix,$input_values);
        }
      return($cf_smarty);
  }
  
  
  /*
    function: html_table_of_custom_field_values
              
              
    args: $id
          [$scope]: 'design','execution'
          [$show_on_execution]: default: null
                                1 -> filter on field show_on_execution=1
                                0 or null -> don't filter
    
    returns: html string
    
  */
  function html_table_of_custom_field_values($id,$scope='design',$show_on_execution=null,
                                             $tproject_id = null,$formatOptions=null) 
  {
      $filters=array('show_on_execution' => $show_on_execution);    
      $label_css_style=' class="labelHolder" ' ;
      $value_css_style = ' ';

      $add_table=true;
      $table_style='';
      if( !is_null($formatOptions) )
      {
          $label_css_style = isset($formatOptions['label_css_style']) ? $formatOptions['label_css_style'] : $label_css_style;
      $value_css_style = isset($formatOptions['value_css_style']) ? $formatOptions['value_css_style'] : $value_css_style;

          $add_table=isset($formatOptions['add_table']) ? $formatOptions['add_table'] : true;
          $table_style=isset($formatOptions['table_css_style']) ? $formatOptions['table_css_style'] : $table_style;
      } 
  
      $cf_smarty='';
      $parent_id=null;

    // BUGID 3989
      $show_cf = config_get('custom_fields')->show_custom_fields_without_value;

      if( $scope=='design' )
      {
        $cf_map = $this->get_linked_cfields_at_design($id,$parent_id,$filters,$tproject_id);
      }
      else 
      {
        // Important: remember that for Test Suite, custom field value CAN NOT BE changed 
        // at execution time just displayed.
        // 20110129 - if we know test project id is better to use it
        $cf_map=$this->get_linked_cfields_at_execution($id,null,null,$tproject_id);
      }
        
      if( !is_null($cf_map) )
      {
        foreach($cf_map as $cf_id => $cf_info)
        {
          // if user has assigned a value, then node_id is not null
          // BUGID 3989
          if($cf_info['node_id'] || $show_cf)
          {
            // true => do not create input in audit log
            $label=str_replace(TL_LOCALIZE_TAG,'',lang_get($cf_info['label'],null,true));
            $cf_smarty .= "<tr><td {$label_css_style} >" . htmlspecialchars($label) . "</td>" .
              "<td {$value_css_style}>" .
                          $this->cfield_mgr->string_custom_field_value($cf_info,$id) .
                          "</td></tr>\n";
          }
        }
      }
      if((trim($cf_smarty) != "") && $add_table)
    {
       $cf_smarty = "<table {$table_style}>" . $cf_smarty . "</table>";
    }
      return($cf_smarty);
  } // function end


  /** 
   * Copy attachments from source test suite to target test suite
   * 
   **/
  function copy_attachments($source_id,$target_id)
  {
    $this->attachmentRepository->copyAttachments($source_id,$target_id,$this->attachmentTableName);
  }

  /** 
   * Copy keyword assignment
   * mappings is only useful when source_id and target_id do not belong to same Test Project.
   * Because keywords are defined INSIDE a Test Project, ID will be different for same keyword
   * in a different Test Project
   *
   **/
  function copy_keyword_assignment($source_id,$target_id,$mappings)
  {
    // Get source_id keyword assignment
    $sourceItems = $this->getKeywords($source_id);
    if( !is_null($sourceItems) )
    {
      // build item id list
      $keySet = array_keys($sourceItems);
      foreach($keySet as $itemPos => $itemID)
      {
        if( isset($mappings[$itemID]) )
        {
          $keySet[$itemPos] = $mappings[$itemID];
        }
      }
      $this->addKeywords($target_id,$keySet);   
    }
    }

  /** 
   * Copy Custom Fields values
   *
   **/
  function copy_cfields_values($source_id,$target_id)
  {
    $debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
    // Get source_id cfields assignment
    $sourceItems = $this->cfield_mgr->getByLinkID($source_id,array('scope' => 'design'));
    if( !is_null($sourceItems) )
    {
        $sql = "/* $debugMsg */ " . 
                 " INSERT INTO {$this->tables['cfield_design_values']} " . 
                 " (field_id,value,node_id) " .
               " SELECT field_id,value,{$target_id} AS target_id" .
                 " FROM {$this->tables['cfield_design_values']} " .
               " WHERE node_id = {$source_id} ";
      $this->db->exec_query($sql);
    }
    }


  /**
   * get_children
   * get test suites with parent = testsuite with given id
   *
   */
  function get_children($id,$options=null)
  {
      $itemSet = null;
      $my['options'] = array('details' => 'full');
      $my['options'] = array_merge($my['options'], (array)$options);
      
      $subtree = $this->tree_manager->get_children($id, array('testcase' => 'exclude_me'));
      if(!is_null($subtree) && count($subtree) > 0)
      {
      foreach( $subtree as $the_key => $elem)
      {
          $itemKeys[] = $elem['id'];
      }
      
      if($my['options']['details'] == 'full')
      {
          $itemSet = $this->get_by_id($itemKeys, 'ORDER BY node_order');
        }
        else
        {
          $itemSet = $itemKeys;
        } 
      }
      return $itemSet;
  }

  /**
   * get_branch
   * get ONLY test suites (no other kind of node) ON BRANCH with ROOT = testsuite with given id
   *
   */
  function get_branch($id)
  {
    $branch = $this->tree_manager->get_subtree_list($id,$this->my_node_type);
    return $branch;
  }

  function getNode($id)
  {
    $node = $this->tree_manager->get_node_hierarchy_info($id);
    return $node;
  }

} // end class
?>