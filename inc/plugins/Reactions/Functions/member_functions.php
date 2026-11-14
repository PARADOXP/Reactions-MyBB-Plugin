<?php
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

/**
 * Handle member profile display
 */
function reactions_member_profile_end()
{
    global $mybb, $db, $lang, $memprofile, $templates, $theme, $reactions_profile_stats, $headerinclude;

    // Check if reactions are enabled and profile display is allowed
    if($mybb->settings['reactions_enabled'] == 0 || $mybb->settings['reactions_display_profile'] == 0)
    {
        return;
    }

    // Usergroup check for viewing
    $user_perms = reactions_get_user_permissions($mybb->user['uid']);
    if($user_perms && $user_perms['can_view_reactions'] == 0)
    {
        return;
    }

    if(!$memprofile['uid'])
    {
        return;
    }

    // Load language
    if(!isset($lang->reactions))
    {
        $lang->load("reactions");
    }

    // Get reaction counts (not points)
    $received_count = reactions_get_user_reaction_count($memprofile['uid']);
    $given_count = reactions_get_user_reaction_given_count($memprofile['uid']);
    
    // Get reaction points (sum of points)
    $reaction_points = (int)$memprofile['reaction_score'];
    
    // Use count variables for template (keeping variable names for compatibility)
    $received_score = $received_count;
    $given_score = $given_count;

    // Add CSS and JS to headerinclude (head section only)
    $js_url = $mybb->settings['bburl'].'/inc/plugins/Reactions/Assets/reactions.js';
    $css_url = $mybb->settings['bburl'].'/inc/plugins/Reactions/Assets/reactions.css';
    
    // Inject mybb_url for JS
    $headerinclude .= '<script type="text/javascript">window.mybb_url = "'.$mybb->settings['bburl'].'";</script>';
    
    if(!defined('REACTIONS_CSS_ADDED')) {
        $headerinclude .= '<link rel="stylesheet" href="'.$css_url.'" type="text/css" />';
        define('REACTIONS_CSS_ADDED', true);
    }
    if(!defined('REACTIONS_JS_ADDED')) {
        $headerinclude .= reactions_js_lang_script();
        $headerinclude .= '<script type="text/javascript" src="'.$js_url.'"></script>';
        define('REACTIONS_JS_ADDED', true);
    }

    // Fire hook before profile stats display
    reactions_hook_profile_stats_start($memprofile['uid']);

    // Load template for profile stats (body content - not headerinclude)
    $template = reactions_load_template("reactions_profile_stats");

    if($template)
    {
        eval("\$reactions_profile_stats = \"".$template."\";");
    }
    
    // Fire hook after profile stats display
    reactions_hook_profile_stats_end($memprofile['uid']);
}

/**
 * Handle reactions_info page
 */
function reactions_member_reactions_info()
{
    global $mybb, $db, $lang, $templates, $theme;
    
    // Only handle reactions_info action
    if($mybb->input['action'] != 'reactions_info')
    {
        return;
    }
    
    // Check if reactions are enabled
    if($mybb->settings['reactions_enabled'] == 0)
    {
        error_no_permission();
    }
    
    // Usergroup check for viewing
    $user_perms = reactions_get_user_permissions($mybb->user['uid']);
    if($user_perms && $user_perms['can_view_reactions'] == 0)
    {
        error_no_permission();
    }
    
    // Load language
    if(!isset($lang->reactions))
    {
        $lang->load("reactions");
    }
    
    // Get all active reactions with their points
    $active_reactions = reactions_get_active(true);
    
    // Build reactions list
    $reactions_list = '';
    $reaction_rows = '';
    
    if(!empty($active_reactions))
    {
        foreach($active_reactions as $reaction)
        {
            $reaction_image = reactions_get_reaction_image($reaction, 'medium');
            $reaction_title = htmlspecialchars_uni($reaction['title']);
            $reaction_points = (int)$reaction['reaction_score'];
            $reaction_type_key = 'reaction_type_' . $reaction['reaction_type'];
            $reaction_type = isset($lang->{$reaction_type_key}) ? $lang->{$reaction_type_key} : ucfirst($reaction['reaction_type']);
            
            // Format points display
            $points_display = $reaction_points;
            if($reaction_points > 0)
            {
                $points_display = '+' . $reaction_points;
            }
            
            // Try to use MyBB template system first
            if($templates->get("reactions_info_row"))
            {
                eval("\$row_content = \"".$templates->get("reactions_info_row")."\";");
                $reaction_rows .= $row_content;
            }
            else
            {
                // Skip if template doesn't exist (no fallback)
                continue;
            }
        }
    }
    else
    {
        $reaction_rows = '<tr><td class="trow1" colspan="4" style="text-align: center;">'.$lang->no_reactions_yet.'</td></tr>';
    }
    
    // Set page title and breadcrumb before building content
    $lang->nav_reactions_info = $lang->reactions_info_title;
    add_breadcrumb($lang->reactions_info_title, "member.php?action=reactions_info");
    
    // Ensure templates are registered first
    if(!function_exists('reactions_add_templates'))
    {
        require_once MYBB_ROOT . 'inc/plugins/Reactions/Functions/admin_functions.php';
    }
    reactions_add_templates();
    
    // Use MyBB's template system (no hardcoded fallbacks)
    global $headerinclude, $header, $footer;
    
    // Set variables for template
    $reaction_image = ''; $reaction_title = ''; $points_display = ''; $reaction_type = '';
    
    // Use proper template evaluation
    if($templates->get("reactions_info"))
    {
        eval("\$page_content = \"".$templates->get("reactions_info")."\";");
        echo $page_content;
    }
    else
    {
        // Error if template doesn't exist - forces proper template registration
        error($lang->reactions_error_template_not_found);
    }
    exit;
}
