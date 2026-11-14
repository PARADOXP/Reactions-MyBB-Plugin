<?php
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

global $lang;

if(!isset($lang->reactions_success_added))
{
    $lang->load("reactions");
}

$page->add_breadcrumb_item($lang->reactions_admin_menu_title, "index.php?module=config-reactions");

if($mybb->input['action'] == "add" || $mybb->input['action'] == "edit")
{
    // Add/Edit reaction
    if($mybb->request_method == "post")
    {
        $sprite_params = array(
            'w' => (int)$mybb->input['sprite_w'],
            'h' => (int)$mybb->input['sprite_h'],
            'x' => (int)$mybb->input['sprite_x'],
            'y' => (int)$mybb->input['sprite_y'],
            'bs' => $mybb->input['sprite_bs']
        );

        $reaction_data = array(
            'title' => $db->escape_string($mybb->input['title']),
            'image_url' => $db->escape_string($mybb->input['image_url']),
            'image_url_2x' => $db->escape_string($mybb->input['image_url_2x']),
            'sprite_mode' => (int)$mybb->input['sprite_mode'],
            'sprite_params' => $db->escape_string(json_encode($sprite_params)),
            'text_color' => $db->escape_string($mybb->input['text_color']),
            'reaction_score' => (int)$mybb->input['reaction_score'],
            'reaction_type' => $db->escape_string($mybb->input['reaction_type']),
            'display_order' => (int)$mybb->input['display_order'],
            'active' => (int)$mybb->input['active']
        );
        
        if($mybb->input['action'] == "add")
        {
            $reaction_id = $db->insert_query("reactions", $reaction_data);
            flash_message($lang->reactions_success_added, "success");
        }
        else
        {
            $reaction_id = (int)$mybb->input['reaction_id'];
            $db->update_query("reactions", $reaction_data, "reaction_id=".$reaction_id);
            flash_message($lang->reactions_success_updated, "success");
        }
        
        // Rebuild cache
        if(function_exists('reactions_get_active'))
        {
            $cache->update("reactions_active", reactions_get_active());
        }
        
        admin_redirect("index.php?module=config-reactions");
    }
    
    // Show add/edit form
    $reaction = array();
    if($mybb->input['action'] == "edit")
    {
        $reaction_id = (int)$mybb->input['reaction_id'];
        $reaction = $db->fetch_array($db->simple_select("reactions", "*", "reaction_id=".$reaction_id));
        if(!$reaction)
        {
            flash_message($lang->reactions_error_invalid_reaction, "error");
            admin_redirect("index.php?module=config-reactions");
        }
    }
    
    $sprite_params = array('w' => 32, 'h' => 32, 'x' => 0, 'y' => 0, 'bs' => '100%');
    if(!empty($reaction['sprite_params']))
    {
        $sprite_params = array_merge($sprite_params, (array)json_decode($reaction['sprite_params'], true));
    }

    $page->output_header($lang->reactions_list_title);
    
    $sub_tabs = array();
    $sub_tabs['reactions'] = array(
        'title' => $lang->reactions_manage_title,
        'link' => "index.php?module=config-reactions",
    );
    $sub_tabs['add_reaction'] = array(
        'title' => $lang->reactions_add_reaction,
        'link' => "index.php?module=config-reactions&action=add",
    );
    $sub_tabs['give_points'] = array(
        'title' => $lang->reactions_give_points_title,
        'link' => "index.php?module=config-reactions&action=give_points",
    );
    $sub_tabs['give_reactions'] = array(
        'title' => $lang->reactions_give_reactions_title,
        'link' => "index.php?module=config-reactions&action=give_reactions",
    );
    if($mybb->input['action'] == "edit")
    {
        $sub_tabs['edit_reaction'] = array(
            'title' => $lang->reactions_edit_reaction,
            'link' => "index.php?module=config-reactions&action=edit&reaction_id=".$reaction['reaction_id'],
            'description' => $lang->sprintf($lang->reactions_edit_reaction_desc, htmlspecialchars_uni($reaction['title']))
        );
    }

    $page->output_nav_tabs($sub_tabs, ($mybb->input['action'] == "add" ? "add_reaction" : "edit_reaction"));
    
    $form = new Form("index.php?module=config-reactions&amp;action=".$mybb->input['action']."&amp;reaction_id=".(!empty($reaction) ? $reaction['reaction_id'] : 0), "post");
    
    $form_container = new FormContainer(!empty($reaction) ? $lang->sprintf($lang->reactions_edit_reaction_form, htmlspecialchars_uni($reaction['title'])) : $lang->reactions_add_reaction_form);
    
    $form_container->output_row($lang->reactions_field_title, $lang->reactions_field_title_desc, $form->generate_text_box("title", $reaction['title'] ?? "", array('id' => 'title')), 'title');
    $form_container->output_row($lang->reactions_field_text_color, $lang->reactions_field_text_color_desc, $form->generate_text_box("text_color", $reaction['text_color'] ?? "#0084FF", array('id' => 'text_color', 'maxlength' => 7)), 'text_color');
    
    $reaction_types = array(
        'positive' => $lang->reactions_type_positive,
        'neutral' => $lang->reactions_type_neutral,
        'negative' => $lang->reactions_type_negative
    );
    $form_container->output_row($lang->reactions_field_points_type, $lang->reactions_field_points_type_desc, $form->generate_select_box("reaction_type", $reaction_types, $reaction['reaction_type'] ?? 'positive', array('id' => 'reaction_type')), 'reaction_type');
    
    $form_container->output_row($lang->reactions_field_custom_points, $lang->reactions_field_custom_points_desc, $form->generate_numeric_field("reaction_score", $reaction['reaction_score'] ?? 1, array('id' => 'reaction_score')), 'reaction_score');
    
    $default_display_order = $reaction['display_order'] ?? (int)$db->fetch_field($db->simple_select("reactions", "COUNT(*) as cnt"), "cnt") + 1;
    $form_container->output_row($lang->reactions_field_display_order, $lang->reactions_field_display_order_desc, $form->generate_numeric_field("display_order", $default_display_order, array('id' => 'display_order')), 'display_order');
    $form_container->output_row($lang->reactions_field_active, $lang->reactions_field_active_desc, $form->generate_yes_no_radio("active", $reaction['active'] ?? 1), 'active');
    
    $form_container->output_row($lang->reactions_field_image_url, $lang->reactions_field_image_url_desc, $form->generate_text_box("image_url", $reaction['image_url'] ?? "", array('id' => 'image_url')), 'image_url');
    $form_container->output_row($lang->reactions_field_image_url_2x, $lang->reactions_field_image_url_2x_desc, $form->generate_text_box("image_url_2x", $reaction['image_url_2x'] ?? "", array('id' => 'image_url_2x')), 'image_url_2x');
    
    $form_container->output_row($lang->reactions_field_sprite_mode, $lang->reactions_field_sprite_mode_desc, $form->generate_yes_no_radio("sprite_mode", $reaction['sprite_mode'] ?? 0), 'sprite_mode');
    
    $sprite_fields = $lang->reactions_sprite_width_label.$form->generate_numeric_field("sprite_w", $sprite_params['w'], array('style' => 'width: 50px;')).$lang->reactions_sprite_height_label.$form->generate_numeric_field("sprite_h", $sprite_params['h'], array('style' => 'width: 50px;'));
    $form_container->output_row($lang->reactions_field_sprite_dimensions, $lang->reactions_field_sprite_dimensions_desc, $sprite_fields);

    $pos_fields = $lang->reactions_sprite_x_label.$form->generate_numeric_field("sprite_x", $sprite_params['x'], array('style' => 'width: 50px;')).$lang->reactions_sprite_y_label.$form->generate_numeric_field("sprite_y", $sprite_params['y'], array('style' => 'width: 50px;'));
    $form_container->output_row($lang->reactions_field_sprite_position, $lang->reactions_field_sprite_position_desc, $pos_fields);

    $form_container->output_row($lang->reactions_field_background_size, $lang->reactions_field_background_size_desc, $form->generate_text_box("sprite_bs", $sprite_params['bs'], array('id' => 'sprite_bs', 'style' => 'width: 100px;')), 'sprite_bs');

    $form_container->end();
    
    $buttons[] = $form->generate_submit_button($lang->reactions_save_reaction);
    $form->output_submit_wrapper($buttons);
    $form->end();
    
    $page->output_footer();
}
elseif($mybb->input['action'] == "delete")
{
    // Delete reaction
    $reaction_id = (int)$mybb->input['reaction_id'];
    
    if($mybb->request_method == "post")
    {
        // Don't allow deleting reaction ID 1 (default Like)
        if($reaction_id == 1)
        {
            flash_message($lang->reactions_error_cannot_delete_default, "error");
            admin_redirect("index.php?module=config-reactions");
        }
        
        // Find affected users before deleting
        $affected_users = array();
        $query = $db->simple_select("reaction_content", "DISTINCT reaction_user_id, content_user_id", "reaction_id=".$reaction_id);
        while($row = $db->fetch_array($query))
        {
            $affected_users[] = $row['reaction_user_id'];
            $affected_users[] = $row['content_user_id'];
        }
        $affected_users = array_unique(array_filter($affected_users));

        $db->delete_query("reactions", "reaction_id=".$reaction_id);
        $db->delete_query("reaction_content", "reaction_id=".$reaction_id);
        
        // Update affected users' scores
        if(!empty($affected_users) && function_exists('reactions_update_user_reaction_scores'))
        {
            foreach($affected_users as $uid)
            {
                reactions_update_user_reaction_scores($uid);
            }
        }

        // Rebuild cache
        $cache->update("reactions_active", reactions_get_active(true));
        
        flash_message($lang->reactions_success_deleted, "success");
        admin_redirect("index.php?module=config-reactions");
    }
    
    $reaction = $db->fetch_array($db->simple_select("reactions", "*", "reaction_id=".$reaction_id));
    if(!$reaction)
    {
        flash_message($lang->reactions_error_invalid_reaction, "error");
        admin_redirect("index.php?module=config-reactions");
    }
    
    $page->output_confirm_action("index.php?module=config-reactions&amp;action=delete&amp;reaction_id=".$reaction_id, $lang->reactions_delete_confirm);
}
else
{
    // List reactions
    $page->output_header($lang->reactions_list_title);
    
    $sub_tabs['reactions'] = array(
        'title' => $lang->reactions_manage_title,
        'link' => "index.php?module=config-reactions",
        'description' => $lang->reactions_manage_title_desc
    );
    
    $sub_tabs['give_points'] = array(
        'title' => $lang->reactions_give_points_title,
        'link' => "index.php?module=config-reactions&action=give_points",
        'description' => $lang->reactions_give_points_tab_desc
    );
    
    $sub_tabs['give_reactions'] = array(
        'title' => $lang->reactions_give_reactions_title,
        'link' => "index.php?module=config-reactions&action=give_reactions",
        'description' => $lang->reactions_give_reactions_tab_desc
    );
    
    $page->output_nav_tabs($sub_tabs, "reactions");
    
    // Show Give Points button in addition to Add New Reaction
    echo "<div style=\"padding-bottom: 10px; text-align: right;\">";
    echo "<a href=\"index.php?module=config-reactions&amp;action=give_points\" class=\"button\" style=\"margin-right: 10px;\"><span>".$lang->reactions_give_points_to_user."</span></a>";
    echo "<a href=\"index.php?module=config-reactions&amp;action=add\" class=\"button\"><span>".$lang->reactions_add_new_reaction."</span></a>";
    echo "</div>";

    $table = new Table;
    $table->construct_header($lang->reactions_table_id);
    $table->construct_header($lang->reactions_table_title);
    $table->construct_header($lang->reactions_table_points);
    $table->construct_header($lang->reactions_table_type);
    $table->construct_header($lang->reactions_table_order);
    $table->construct_header($lang->reactions_table_active);
    $table->construct_header($lang->reactions_table_options, array("class" => "align_center", "colspan" => 2));
    
    $query = $db->simple_select("reactions", "*", "", array("order_by" => "display_order", "order_dir" => "ASC"));
    while($reaction = $db->fetch_array($query))
    {
        $table->construct_cell($reaction['reaction_id']);
        $table->construct_cell(htmlspecialchars_uni($reaction['title']));
        $table->construct_cell($reaction['reaction_score']);
        $table->construct_cell(ucfirst($reaction['reaction_type']));
        $table->construct_cell($reaction['display_order']);
        $table->construct_cell($reaction['active'] ? $lang->reactions_yes : $lang->reactions_no);
        
        $table->construct_cell("<a href=\"index.php?module=config-reactions&amp;action=edit&amp;reaction_id=".$reaction['reaction_id']."\">".$lang->reactions_edit."</a>", array("class" => "align_center"));
        
        if($reaction['reaction_id'] != 1)
        {
            $table->construct_cell("<a href=\"index.php?module=config-reactions&amp;action=delete&amp;reaction_id=".$reaction['reaction_id']."\">".$lang->reactions_delete."</a>", array("class" => "align_center"));
        }
        else
        {
            $table->construct_cell("-", array("class" => "align_center"));
        }
        
        $table->construct_row();
    }
    
    if($table->num_rows() == 0)
    {
        $table->construct_cell($lang->reactions_no_reactions_found."<a href=\"index.php?module=config-reactions&amp;action=add\">".$lang->reactions_add_one."</a>", array("colspan" => 8));
        $table->construct_row();
    }
    
    $table->output($lang->reactions_list_title);
    
    $page->output_footer();
}
