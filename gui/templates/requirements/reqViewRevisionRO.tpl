{* 
TestLink Open Source Project - http://testlink.sourceforge.net/
@filesource	reqViewRevisionRO.tpl
view requirement with version + revision in READ ONLY MODE

@internal revisions
20110309 - Julian - removed duplicate title
20110305 - franciscom - fixed unable to load template due to Smarty 3.0.7
*}
{lang_get var='labels' 
          s='relation_id, relation_type, relation_document, relation_status, relation_project,
             relation_set_by, relation_delete, relations, new_relation, by, title_created,
             relation_destination_doc_id, in, btn_add, img_title_delete_relation, current_req,
             no_records_found,other_versions,version,title_test_case,match_count, warning'}


{include file="inc_head.tpl" openHead='yes' jsValidate="yes"}
{config_load file="input_dimensions.conf"}
{$my_style=""}
{if $gui->hilite_item_name}
    {$my_style="background:#059; color:white; margin:0px 0px 4px 0px;padding:3px;"}
{/if}

{$this_template_dir=$smarty.current_dir}
<body>
<h1 class="title">{$gui->main_descr|escape}</h1>
<div class="workBack">

{include file="$this_template_dir/reqViewRevisionViewer.tpl" 
         args_req=$gui->item 
         args_gui=$gui
         args_grants=$gui->grants 
         args_can_copy=false
         args_show_version=true
         args_show_title=$gui->show_title
         args_tproject_name=$gui->tproject_name
         args_reqspec_name=$gui->item['req_spec_title']
         args_cf=$gui->cfields}
</div>
</body>
</html>