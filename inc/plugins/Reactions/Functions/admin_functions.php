<?php
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

/**
 * Add settings
 */
function reactions_add_settings()
{
    global $PL;

    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
        
        $settingsFile = MYBB_ROOT.'inc/plugins/Reactions/settings.json';
        if(file_exists($settingsFile))
        {
            $json = @file_get_contents($settingsFile);
            $data = @json_decode($json, true);
            if(is_array($data) && isset($data['group']) && isset($data['settings']) && is_array($data['settings']))
            {
                $group = $data['group'];
                $name = !empty($group['name']) ? $group['name'] : 'reactions';
                $title = !empty($group['title']) ? $group['title'] : 'Reactions Settings';
                $description = !empty($group['description']) ? $group['description'] : 'Configure the reaction system';
                
                // Format settings for PluginLibrary (needs name as key)
                $formattedSettings = array();
                foreach($data['settings'] as $setting)
                {
                    if(isset($setting['name']))
                    {
                        $sname = $setting['name'];
                        // Strip group name prefix if it exists to avoid doubling (e.g. reactions_reactions_enabled)
                        if(strpos($sname, $name . '_') === 0)
                        {
                            $sname = substr($sname, strlen($name) + 1);
                        }
                        unset($setting['name']);
                        $formattedSettings[$sname] = $setting;
                    }
                }
                
                // If MyAlerts is active, auto-enable integration by default
                if(function_exists('reactions_have_myalerts') && reactions_have_myalerts(true))
                {
                    foreach($formattedSettings as $key => &$setting)
                    {
                        if($key === 'integrate_myalerts')
                        {
                            $setting['value'] = '1';
                        }
                    }
                    unset($setting);
                }

                $PL->settings($name, $title, $description, $formattedSettings);
            }
        }
    }
}

/**
 * Remove settings
 */
function reactions_remove_settings()
{
    global $PL;

    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
        $PL->settings_delete('reactions', true);
    }
}

/**
 * Add templates using PluginLibrary (same approach as thread_collaboration)
 */
function reactions_add_templates()
{
    global $db, $PL;

    // Check if templates already exist - if so, don't overwrite user edits
    $query = $db->simple_select('templates', 'COUNT(*) as count', "title='reactions' AND sid=-2");
    $template_count = $db->fetch_field($query, 'count');
    
    if($template_count > 0)
    {
        // Templates already exist, don't overwrite user edits
        return;
    }

    // Prefer PluginLibrary + external template files (same as thread_collaboration)
    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();

        $basePath = MYBB_ROOT.'inc/plugins/Reactions/Templates/';

        $reaction_files = array(
            'tooltip' => 'reactions_tooltip.html',
            'tooltip_item' => 'reactions_tooltip_item.html',
            'bar' => 'reactions_bar.html',
            'button' => 'reactions_button.html',
            'overlay' => 'reactions_overlay.html',
            'overlay_row' => 'reactions_overlay_row.html',
            'overlay_tab' => 'reactions_overlay_tab.html',
            'summary_item' => 'reactions_summary_item.html',
            'profile_stats' => 'reactions_profile_stats.html',
            'profile_history' => 'reactions_profile_history.html',
            'profile_history_row' => 'reactions_profile_history_row.html',
            'info' => 'reactions_info.html',
            'info_row' => 'reactions_info_row.html',
            'usercp_options' => 'usercp_options.html',
            'usercp_settings_page' => 'reactions_usercp_settings_page.html',
            'usercp_setting_row' => 'reactions_usercp_setting_row.html',
            'usercp_myalerts_row' => 'reactions_usercp_myalerts_row.html',
            'usercp_myalerts_removed_row' => 'reactions_usercp_myalerts_removed_row.html',
            'usercp_menu_item' => 'reactions_usercp_menu_item.html'
        );

        $reaction_list = array();
        foreach($reaction_files as $key => $file)
        {
            $path = $basePath.$file;
            if(file_exists($path))
            {
                $reaction_list[$key] = file_get_contents($path);
            }
        }

        if($reaction_list)
        {
            $PL->templates('reactions', 'Reactions', $reaction_list);
        }

        // Don't delete customized templates - allow user edits to persist
        // Done via PluginLibrary; skip legacy inline insertion
        return;
    }
}

function reactions_remove_templates()
{
    global $PL;

    // Use PluginLibrary to remove templates (same approach as thread_collaboration)
    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
        $PL->templates_delete('reactions', true);
    }
}

/**
 * Add stylesheet
 */
function reactions_add_stylesheet()
{
    global $PL;
    
    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
        
        $css_path = MYBB_ROOT . 'inc/plugins/Reactions/Assets/reactions.css';
        if(file_exists($css_path))
        {
            $css = @file_get_contents($css_path);
            if($css !== false)
            {
                $PL->stylesheet('reactions', $css);
            }
        }
    }
}

/**
 * Remove stylesheet
 */
function reactions_remove_stylesheet()
{
    global $PL;
    
    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
        $PL->stylesheet_delete('reactions', true);
    }
}

/**
 * Apply template edits (same approach as thread_collaboration)
 */
function reactions_apply_template_edits()
{
    require_once MYBB_ROOT."inc/adminfunctions_templates.php";

    $templates_to_edit = array("postbit", "postbit_classic");

    foreach($templates_to_edit as $tpl)
    {
        // Add reactions bar below buttons
        if(find_replace_templatesets($tpl, "#".preg_quote('{$post[\'button_reply\']}')."#", '{$post[\'button_reply\']}<div style="clear:both;"></div>{$post[\'reactions_bar\']}') == false)
        {
            // Fallback: Add after signature
            find_replace_templatesets($tpl, "#".preg_quote('{$post[\'signature\']}')."#", '{$post[\'signature\']}{$post[\'reactions_bar\']}');
        }

        // Add reaction button to postbit
        $button_locations = array(
            '{$post[\'button_report\']}',
            '{$post[\'button_warn\']}',
            '{$post[\'button_quote\']}',
            '{$post[\'button_multiquote\']}',
            '{$post[\'button_reputation\']}',
            '{$post[\'button_edit\']}',
            '{$post[\'post_management_buttons\']}'
        );

        $found = false;
        foreach($button_locations as $location)
        {
            if(find_replace_templatesets($tpl, "#".preg_quote($location)."#", $location.'{$post[\'reaction_button\']}'))
            {
                $found = true;
                break;
            }
        }

        if(!$found)
        {
            find_replace_templatesets($tpl, "#".preg_quote('</div>')."#", '{$post[\'reaction_button\']}</div>', 0);
        }
    }
}

/**
 * Remove template edits
 */
function reactions_remove_template_edits()
{
    require_once MYBB_ROOT."inc/adminfunctions_templates.php";

    $templates_to_edit = array("postbit", "postbit_classic");

    foreach($templates_to_edit as $tpl)
    {
        // Remove reactions bar from after signature (legacy support)
        find_replace_templatesets($tpl, "#".preg_quote('{$post[\'signature\']}{$post[\'reactions_bar\']}')."#", '{$post[\'signature\']}', 0);
        
        // Remove reactions bar from after button_reply with clear:both div
        find_replace_templatesets($tpl, "#".preg_quote('{$post[\'button_reply\']}<div style="clear:both;"></div>{$post[\'reactions_bar\']}')."#", '{$post[\'button_reply\']}', 0);
        
        // Remove from after button_delete_pm</div>
        $search = "#".preg_quote('{$post[\'button_delete_pm\']}')."(\s*</div>)".preg_quote('{$post[\'reactions_bar\']}')."#";
        $replace = '{$post[\'button_delete_pm\']}$1';
        find_replace_templatesets($tpl, $search, $replace, 0);

        // Remove reactions bar before </div> (fallback)
        find_replace_templatesets($tpl, "#".preg_quote('{$post[\'reactions_bar\']}</div>')."#", '</div>', 0);

        // Remove reaction score from author statistics
        find_replace_templatesets($tpl, "#".preg_quote('{$post[\'user_details\']}{$post[\'author_reactions\']}')."#", '{$post[\'user_details\']}', 0);

        // Remove reaction button
        $button_locations = array(
            '{$post[\'button_report\']}',
            '{$post[\'button_warn\']}',
            '{$post[\'button_quote\']}',
            '{$post[\'button_multiquote\']}',
            '{$post[\'button_reputation\']}',
            '{$post[\'button_edit\']}',
            '{$post[\'post_management_buttons\']}'
        );

        foreach($button_locations as $location)
        {
            // Button AFTER reaction_button
            find_replace_templatesets($tpl, "#".preg_quote($location.'{$post[\'reaction_button\']}')."#", $location, 0);
            // Button BEFORE reaction_button
            find_replace_templatesets($tpl, "#".preg_quote('{$post[\'reaction_button\']}'.$location)."#", $location, 0);
        }

        // Remove from other possible locations
        find_replace_templatesets($tpl, "#".preg_quote('{$post[\'reaction_button\']}</div>')."#", '</div>', 0);
    }
    
    // Remove reaction score from profile
    find_replace_templatesets("member_profile", "#".preg_quote('{$profilefields}{$reactions_profile_stats}')."#i", '{$profilefields}', 0);
    
    // Remove Font Awesome CSS from headerinclude
    find_replace_templatesets("headerinclude", "#".preg_quote("\n" . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" integrity="sha512-DxF_EoADOkOygM4IR9yXP8Sb2qwgidEmeqAEmDKIOfPRQZOWbXCzLC6vjbZyy0vPisbH2SyW27+ddLVCN+OMzQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />')."#", '', 0);
}

/**
 * Add reaction settings to usergroup edit page
 */
function reactions_admin_user_groups_edit_graph(&$group)
{
    global $db, $mybb, $form, $lang;

    // Load language if not loaded
    if(!isset($lang->reactions_settings_form_title))
    {
        $lang->load("reactions");
    }

    // Get the group ID - $group can be an array or the gid directly
    $gid = is_array($group) && isset($group['gid']) ? (int)$group['gid'] : (int)$group;
    
    $group_data = $db->fetch_array($db->simple_select("usergroups", "reaction_daily_limit", "gid='".$gid."'"));

    // Check if this usergroup is allowed to react based on plugin settings (BLACKLIST approach)
    // reactions_use_groups_list now uses blacklist: specifies who CANNOT react
    $blocked_groups_setting = $mybb->settings['reactions_use_groups_list'];
    $is_blocked = false;
    
    // -1 means all groups are blocked (nobody allowed)
    if($blocked_groups_setting == '-1')
    {
        $is_blocked = true;
    }
    // Empty means no groups are blocked (everyone allowed)
    elseif(empty($blocked_groups_setting))
    {
        $is_blocked = false;
    }
    // Check if this specific group is in the blocked list
    else
    {
        $blocked_groups = explode(',', $blocked_groups_setting);
        $blocked_groups = array_map('intval', $blocked_groups);
        $is_blocked = in_array($gid, $blocked_groups);
    }

    $form_container = new FormContainer($lang->reactions_settings_form_title);
    
    // If group is blocked, show disabled field with 0 (unlimited) as default
    // If group is not blocked, show enabled field with current value
    $daily_limit_value = !$is_blocked ? (int)($group_data['reaction_daily_limit'] ?? 0) : 0;
    
    $field_options = array('id' => 'reaction_daily_limit', 'min' => 0);
    if($is_blocked)
    {
        $field_options['disabled'] = 'disabled';
    }
    
    $field_html = $form->generate_numeric_field('reaction_daily_limit', $daily_limit_value, $field_options);
    
    // Add hidden field to preserve value when disabled
    if($is_blocked)
    {
        $field_html .= $form->generate_hidden_field('reaction_daily_limit', $daily_limit_value);
    }
    
    $description = $lang->reactions_daily_reaction_limit_desc;
    if($is_blocked)
    {
        $description .= $lang->reactions_daily_reaction_limit_blocked;
    }
    
    $form_container->output_row(
        $lang->reactions_daily_reaction_limit,
        $description,
        $field_html,
        'reaction_daily_limit'
    );

    $form_container->end();
}

/**
 * Save reaction settings from usergroup edit page
 */
function reactions_admin_user_groups_edit_commit()
{
    global $db, $mybb, $updated_group;

    if(isset($mybb->input['reaction_daily_limit']))
    {
        $updated_group['reaction_daily_limit'] = (int)$mybb->input['reaction_daily_limit'];
    }
}

/**
 * Add Reactions Recount option to Admin CP > Tools > Recount & Rebuild
 * Hook: admin_tools_recount_rebuild_output_list
 */
function reactions_admin_recount_rebuild_output_list()
{
    global $form, $lang, $form_container;
    
    // Load admin language if not loaded
    if(!isset($lang->reactions_recount_users))
    {
        $lang->load("reactions");
    }
    
    // Recount User Reactions
    $form_container->output_cell("<label>{$lang->reactions_recount_users}</label><div class=\"description\">{$lang->reactions_recount_users_desc}</div>");
    $form_container->output_cell($form->generate_numeric_field("reactionsusers", 500, array('style' => 'width: 150px;', 'min' => 0)));
    $form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_recountreactionsusers")));
    $form_container->construct_row();
    
    // Recount Post Reactions
    $form_container->output_cell("<label>{$lang->reactions_recount_posts}</label><div class=\"description\">{$lang->reactions_recount_posts_desc}</div>");
    $form_container->output_cell($form->generate_numeric_field("reactionsposts", 500, array('style' => 'width: 150px;', 'min' => 0)));
    $form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_recountreactionsposts")));
    $form_container->construct_row();
    
    // Convert Reactions to Reputation Entries
    $form_container->output_cell("<label>{$lang->reactions_convert_reputation}</label><div class=\"description\">{$lang->reactions_convert_reputation_desc}</div>");
    $form_container->output_cell($form->generate_numeric_field("reactionsconvert", 500, array('style' => 'width: 150px;', 'min' => 0)));
    $form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_convertreactionsreputation")));
    $form_container->construct_row();
    
    // Initialize Reaction Removed Alert for existing users
    $form_container->output_cell("<label>{$lang->reactions_init_removed_alert}</label><div class=\"description\">{$lang->reactions_init_removed_alert_desc}</div>");
    $form_container->output_cell($form->generate_numeric_field("reactionsinitremoved", 500, array('style' => 'width: 150px;', 'min' => 0)));
    $form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_initremovedalert")));
    $form_container->construct_row();
}

/**
 * Handle Reactions Recount form submission
 * Hook: admin_tools_recount_rebuild_stats
 */
function reactions_admin_recount_rebuild_stats()
{
    global $mybb, $db, $cache, $page, $lang;
    
    // Load admin language
    if(!isset($lang->reactions_recount_users))
    {
        $lang->load("reactions");
    }
    
    // Handle User Reactions Recount
    if(isset($mybb->input['do_recountreactionsusers']))
    {
        if($mybb->input['page'] == 1)
        {
            log_admin_action("reactionsusers");
        }
        
        $per_page = $mybb->get_input('reactionsusers', MyBB::INPUT_INT);
        if(!$per_page || $per_page <= 0)
        {
            $mybb->input['reactionsusers'] = 500;
        }
        
        reactions_acp_recount_user_reactions();
    }
    
    // Handle Post Reactions Recount
    if(isset($mybb->input['do_recountreactionsposts']))
    {
        if($mybb->input['page'] == 1)
        {
            log_admin_action("reactionsposts");
        }
        
        $per_page = $mybb->get_input('reactionsposts', MyBB::INPUT_INT);
        if(!$per_page || $per_page <= 0)
        {
            $mybb->input['reactionsposts'] = 500;
        }
        
        reactions_acp_recount_post_reactions();
    }
    
    // Handle Convert Reactions to Reputation
    if(isset($mybb->input['do_convertreactionsreputation']))
    {
        if($mybb->input['page'] == 1)
        {
            log_admin_action("convertreactions");
            
            // Clear old aggregated entries on first run
            $db->delete_query("reputation", "adduid=0 AND comments='Reaction Score'");
        }
        
        $per_page = $mybb->get_input('reactionsconvert', MyBB::INPUT_INT);
        if(!$per_page || $per_page <= 0)
        {
            $mybb->input['reactionsconvert'] = 500;
        }
        
        reactions_acp_convert_reactions_to_reputation();
    }
    
    // Handle Initialize Reaction Removed Alert
    if(isset($mybb->input['do_initremovedalert']))
    {
        if($mybb->input['page'] == 1)
        {
            log_admin_action("initremovedalert");
        }
        
        $per_page = $mybb->get_input('reactionsinitremoved', MyBB::INPUT_INT);
        if(!$per_page || $per_page <= 0)
        {
            $mybb->input['reactionsinitremoved'] = 500;
        }
        
        reactions_acp_init_removed_alert();
    }
}

/**
 * Recount user reaction scores
 */
function reactions_acp_recount_user_reactions()
{
    global $db, $mybb, $lang;
    
    $query = $db->simple_select("users", "COUNT(uid) as num_users");
    $num_users = $db->fetch_field($query, 'num_users');
    
    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('reactionsusers', MyBB::INPUT_INT);
    
    $start = ($page-1) * $per_page;
    $end = $start + $per_page;
    
    $query = $db->simple_select("users", "uid", '', array('order_by' => 'uid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
    while($user = $db->fetch_array($query))
    {
        reactions_update_user_reaction_scores($user['uid']);
    }
    
    reactions_check_proceed($num_users, $end, ++$page, $per_page, "reactionsusers", "do_recountreactionsusers", $lang->success_recount_reactions_users);
}

/**
 * Recount post reaction scores
 */
function reactions_acp_recount_post_reactions()
{
    global $db, $mybb, $lang;
    
    $query = $db->simple_select("posts", "COUNT(pid) as num_posts");
    $num_posts = $db->fetch_field($query, 'num_posts');
    
    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('reactionsposts', MyBB::INPUT_INT);
    
    $start = ($page-1) * $per_page;
    $end = $start + $per_page;
    
    $query = $db->simple_select("posts", "pid", '', array('order_by' => 'pid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
    while($post = $db->fetch_array($query))
    {
        reactions_rebuild_content_cache('post', $post['pid'], 0);
    }
    
    reactions_check_proceed($num_posts, $end, ++$page, $per_page, "reactionsposts", "do_recountreactionsposts", $lang->success_recount_reactions_posts);
}

/**
 * Check proceed for reactions recount (similar to MyBB's check_proceed)
 */
function reactions_check_proceed($current, $finish, $next_page, $per_page, $name, $name2, $message)
{
    global $page, $lang, $form;
    
    if($finish >= $current)
    {
        flash_message($message, 'success');
        admin_redirect("index.php?module=tools-recount_rebuild");
    }
    else
    {
        $page->output_header();
        
        $form = new Form("index.php?module=tools-recount_rebuild", 'post');
        
        echo $form->generate_hidden_field("page", $next_page);
        echo $form->generate_hidden_field($name, $per_page);
        echo $form->generate_hidden_field($name2, $lang->go);
        output_auto_redirect($form, $lang->confirm_proceed_rebuild);
        
        $form->end();
        
        $page->output_footer();
        exit;
    }
}

/**
 * Convert existing reactions to individual reputation entries
 * This is used when enabling reputation integration for the first time
 */
function reactions_acp_convert_reactions_to_reputation()
{
    global $db, $mybb, $lang;
    
    // Check if gifted reactions should be converted
    $include_gifts = !empty($mybb->settings['reactions_gift_reputation']);
    
    // Build where clause - exclude admin gifts if setting is disabled
    $where_clause = "rc.content_user_id != 0 AND r.active = 1";
    if(!$include_gifts)
    {
        $where_clause .= " AND rc.content_type != 'admin_gift'";
    }
    
    // Get total count of reactions that need reputation entries
    $query = $db->query("
        SELECT COUNT(*) as count
        FROM ".$db->table_prefix."reaction_content rc
        INNER JOIN ".$db->table_prefix."reactions r ON (rc.reaction_id = r.reaction_id)
        WHERE ".$where_clause
    );
    $total_reactions = $db->fetch_field($query, 'count');
    
    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('reactionsconvert', MyBB::INPUT_INT);
    
    $start = ($page-1) * $per_page;
    $end = $start + $per_page;
    
    // Get batch of reactions to convert
    $query = $db->query("
        SELECT rc.*, r.reaction_score
        FROM ".$db->table_prefix."reaction_content rc
        INNER JOIN ".$db->table_prefix."reactions r ON (rc.reaction_id = r.reaction_id)
        WHERE ".$where_clause."
        ORDER BY rc.reaction_content_id ASC
        LIMIT ".(int)$start.", ".(int)$per_page
    );
    
    while($reaction = $db->fetch_array($query))
    {
        // Check if reputation entry already exists for this reaction
        $existing = $db->fetch_field(
            $db->simple_select("reaction_reputation", "reputation_rid", "reaction_content_id = ".(int)$reaction['reaction_content_id']),
            "reputation_rid"
        );
        
        // Only create if not exists
        if(!$existing)
        {
            reactions_add_reputation_entry(
                $reaction['reaction_id'],
                $reaction['content_type'],
                $reaction['content_id'],
                $reaction['reaction_user_id'],
                $reaction['content_user_id'],
                $reaction['reaction_content_id']
            );
        }
    }
    
    reactions_check_proceed($total_reactions, $end, ++$page, $per_page, "reactionsconvert", "do_convertreactionsreputation", $lang->success_convert_reactions_reputation);
}

/**
 * Initialize Reaction Removed Alert for existing users
 * Adds reaction_removed alert to users' disabled list so it appears unchecked by default
 */
function reactions_acp_init_removed_alert()
{
    global $db, $mybb, $lang;
    
    if(!isset($lang->reactions_error_myalerts_alert_type))
    {
        $lang->load("reactions");
    }
    
    // Get the reaction_removed alert type ID
    $reaction_removed_id = null;
    if(class_exists('MybbStuff_MyAlerts_AlertTypeManager'))
    {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
        if($alertTypeManager)
        {
            $alertTypeRemoved = $alertTypeManager->getByCode('reaction_removed');
            if($alertTypeRemoved)
            {
                $reaction_removed_id = $alertTypeRemoved->getId();
            }
        }
    }
    
    if(!$reaction_removed_id)
    {
        flash_message($lang->reactions_error_myalerts_alert_type, 'error');
        admin_redirect("index.php?module=tools-recount_rebuild");
        return;
    }
    
    // Get total count of users who need initialization
    // (users who don't have reaction_removed in their disabled list)
    $query = $db->query("
        SELECT COUNT(*) as count
        FROM ".$db->table_prefix."users
        WHERE myalerts_disabled_alert_types IS NULL 
           OR myalerts_disabled_alert_types = '' 
           OR myalerts_disabled_alert_types = '[]'
           OR myalerts_disabled_alert_types NOT LIKE '%\"{$reaction_removed_id}\"%'
    ");
    $total_users = $db->fetch_field($query, 'count');
    
    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('reactionsinitremoved', MyBB::INPUT_INT);
    
    $start = ($page-1) * $per_page;
    $end = $start + $per_page;
    
    // Get batch of users to process
    $query = $db->query("
        SELECT uid, myalerts_disabled_alert_types
        FROM ".$db->table_prefix."users
        WHERE myalerts_disabled_alert_types IS NULL 
           OR myalerts_disabled_alert_types = '' 
           OR myalerts_disabled_alert_types = '[]'
           OR myalerts_disabled_alert_types NOT LIKE '%\"{$reaction_removed_id}\"%'
        ORDER BY uid ASC
        LIMIT ".(int)$start.", ".(int)$per_page
    );
    
    while($user = $db->fetch_array($query))
    {
        $disabled = array();
        if(!empty($user['myalerts_disabled_alert_types']))
        {
            $disabled = json_decode($user['myalerts_disabled_alert_types'], true);
            if(!is_array($disabled))
            {
                $disabled = array();
            }
        }
        
        // Add reaction_removed to disabled list if not already there
        if(!in_array($reaction_removed_id, $disabled))
        {
            $disabled[] = $reaction_removed_id;
            
            $db->update_query('users', array(
                'myalerts_disabled_alert_types' => $db->escape_string(json_encode(array_values($disabled)))
            ), 'uid = ' . (int)$user['uid']);
        }
    }
    
    reactions_check_proceed($total_users, $end, ++$page, $per_page, "reactionsinitremoved", "do_initremovedalert", $lang->success_init_removed_alert);
}

/**
 * Hook: admin_config_settings_start
 * Check MyAlerts status and show notice if needed
 */
function reactions_admin_settings_check_myalerts()
{
    global $mybb, $lang, $page;
    
    if(!isset($lang->reactions_myalerts_notice_html))
    {
        $lang->load("reactions");
    }
    
    // Only run on settings page
    if($mybb->input['module'] != 'config-settings' && $mybb->input['action'] != 'change')
    {
        return;
    }
    
    // Check if we're viewing the reactions settings group
    $gid = isset($mybb->input['gid']) ? (int)$mybb->input['gid'] : 0;
    if(!$gid)
    {
        $search = isset($mybb->input['search']) ? $mybb->input['search'] : '';
        if(stripos($search, 'myalerts') === false)
        {
            return;
        }
    }
    else
    {
        // Check if this is the reactions settings group
        global $db;
        $group = $db->fetch_array($db->simple_select('settinggroups', '*', "gid={$gid}"));
        if(!$group || $group['name'] != 'reactions')
        {
            return;
        }
    }
    
    // Check if MyAlerts is installed
    if(!file_exists(MYBB_ROOT . 'inc/plugins/myalerts.php'))
    {
        global $myalerts_notice;
        $myalerts_notice = $lang->reactions_myalerts_notice_html;
    }
}

/**
 * Hook: admin_config_settings_change
 * Display MyAlerts notice on settings page
 */
function reactions_admin_settings_myalerts_notice()
{
    global $mybb, $lang, $page, $myalerts_notice;
    
    if(!empty($myalerts_notice))
    {
        echo $myalerts_notice;
    }
}

/**
 * Hook: admin_formcontainer_output_row
 * Disable MyAlerts setting checkbox if MyAlerts not installed
 */
function reactions_admin_settings_disable_myalerts_checkbox(&$args)
{
    global $mybb, $lang;
    
    if(!isset($lang->reactions_myalerts_note_html))
    {
        $lang->load("reactions");
    }
    
    // Check if this is the MyAlerts integration setting
    if(isset($args['content']) && strpos($args['content'], 'reactions_integrate_myalerts') !== false)
    {
        // Check if MyAlerts is installed
        if(!file_exists(MYBB_ROOT . 'inc/plugins/myalerts.php'))
        {
            // MyAlerts not installed - disable the checkbox and add notice
            $args['content'] = str_replace('type="checkbox"', 'type="checkbox" disabled="disabled"', $args['content']);
            $args['content'] = str_replace('value="1" checked="checked"', 'value="0"', $args['content']);
            $args['content'] .= $lang->reactions_myalerts_note_html;
        }
    }
}

/**
 * Hook: admin_config_settings_change_commit
 * Prevent enabling MyAlerts if not installed
 */
function reactions_admin_settings_validate_myalerts()
{
    global $mybb, $lang;
    
    if(!isset($lang->reactions_error_myalerts_integrate))
    {
        $lang->load("reactions");
    }
    
    // Check if MyAlerts integration setting is being saved
    if(isset($mybb->input['upsetting']['reactions_integrate_myalerts']) && $mybb->input['upsetting']['reactions_integrate_myalerts'] == 1)
    {
        // Check if MyAlerts is installed
        if(!file_exists(MYBB_ROOT . 'inc/plugins/myalerts.php'))
        {
            // MyAlerts not installed - reset to 0 and show error
            $mybb->input['upsetting']['reactions_integrate_myalerts'] = 0;
            
            global $page;
            if(!defined('REACTIONS_MYALERTS_ERROR_SHOWN'))
            {
                define('REACTIONS_MYALERTS_ERROR_SHOWN', true);
                flash_message($lang->reactions_error_myalerts_integrate, 'error');
            }
        }
    }
}

/**
 * Hook: admin_config_settings_commit
 * Auto-integrate with MyAlerts when setting is enabled
 */
function reactions_admin_settings_myalerts_enable()
{
    global $mybb, $db;
    
    // Check if MyAlerts integration setting was just enabled
    if(isset($mybb->input['upsetting']['reactions_integrate_myalerts']) && $mybb->input['upsetting']['reactions_integrate_myalerts'] == 1)
    {
        // Check if MyAlerts is installed
        if(file_exists(MYBB_ROOT . 'inc/plugins/myalerts.php'))
        {
            // Load MyAlerts functions if not already loaded
            if(!function_exists('reactions_myalerts_integrate'))
            {
                require_once MYBB_ROOT . 'inc/plugins/Reactions/Functions/myalerts_functions.php';
            }
            
            // Auto-integrate
            reactions_myalerts_integrate();
        }
    }
}
