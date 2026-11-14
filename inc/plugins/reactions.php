<?php

if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

if (!defined("PLUGINLIBRARY")) {
    define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

// Load required function files
require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/reaction_functions.php";
require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/postbit_functions.php";
require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/showthread_functions.php";
require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/ajax_functions.php";
require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/member_functions.php";
require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/admin_functions.php";
require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/usercp_functions.php";
require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/usercp_reactions_settings.php";

// Load integration hooks for third-party plugins
require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/integration_hooks.php";

// Load MyAlerts integration if available
$myalerts_installed = file_exists(MYBB_ROOT . "inc/plugins/myalerts.php");
if($myalerts_installed)
{
    if(file_exists(MYBB_ROOT . "inc/plugins/Reactions/Functions/myalerts_functions.php"))
    {
        require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/myalerts_functions.php";
    }
}

// Load install functions if install.php exists
if(file_exists(MYBB_ROOT . "inc/plugins/Reactions/install.php"))
{
    require_once MYBB_ROOT . "inc/plugins/Reactions/install.php";
}

/**
 * Load template from file (Thread Collaboration style)
 * Templates are loaded from HTML files and registered in database via PluginLibrary
 */
if(!function_exists('reactions_load_template'))
{
    function reactions_load_template($template_name)
    {
        static $template_cache = array();
        
        if(isset($template_cache[$template_name]))
        {
            return $template_cache[$template_name];
        }
        
        $template_path = MYBB_ROOT . 'inc/plugins/Reactions/Templates/' . $template_name . '.html';
        
        if(file_exists($template_path))
        {
            $content = @file_get_contents($template_path);
            
            // Escape backslashes and double quotes exactly like MyBB's $templates->get()
            // This is required because the result is used in eval("\$var = \"$content\";")
            $content = str_replace("\\", "\\\\", $content);
            $content = str_replace("\"", "\\\"", $content);
            
            $template_cache[$template_name] = $content;
            return $template_cache[$template_name];
        }
        
        return false;
    }
}

// Plugin hooks
$plugins->add_hook("global_start", "reactions_global_start");
$plugins->add_hook("postbit", "reactions_postbit");
$plugins->add_hook("postbit_prev", "reactions_postbit_prev");
$plugins->add_hook("postbit_pm", "reactions_postbit_pm");
$plugins->add_hook("showthread_start", "reactions_showthread_start");
$plugins->add_hook("showthread_end", "reactions_showthread_end");
$plugins->add_hook("admin_config_menu", "reactions_admin_menu");
$plugins->add_hook("admin_config_action_handler", "reactions_admin_action_handler");
$plugins->add_hook("admin_load", "reactions_admin_load");
$plugins->add_hook("xmlhttp", "reactions_xmlhttp");
$plugins->add_hook("member_profile_end", "reactions_member_profile_end");
$plugins->add_hook("admin_user_groups_edit_graph", "reactions_admin_user_groups_edit_graph");
$plugins->add_hook("admin_user_groups_edit_commit", "reactions_admin_user_groups_edit_commit");
$plugins->add_hook("usercp_options_end", "reactions_usercp_options");
$plugins->add_hook("usercp_do_options_end", "reactions_usercp_do_options");

// Format reputation comments on reputation report page
// Run with negative priority to execute AFTER the comment is parsed (reputation.php runs parse_message first)
$plugins->add_hook("reputation_vote", "reactions_format_reputation_comment", -100);

// UserCP Reactions Settings Page (dedicated page under Miscellaneous)
$plugins->add_hook("usercp_start", "reactions_usercp_settings_page");
$plugins->add_hook("usercp_menu", "reactions_usercp_menu_item", 40); // Priority 40 to run after usercp_menu_misc

// MyAlerts integration hooks - only register if MyAlerts is installed
if($myalerts_installed)
{
    $plugins->add_hook("myalerts_register_client_alert_formatters", "reactions_register_myalerts_formatter");
    $plugins->add_hook("xmlhttp", "reactions_register_myalerts_formatter_back_compat", -2);
    $plugins->add_hook("datahandler_user_delete_end", "reactions_myalerts_user_delete");
    $plugins->add_hook("global_start", "reactions_myalerts_settings_add_checkbox");
    $plugins->add_hook("pre_output_page", "reactions_myalerts_settings_modify_output");

    // Admin settings hooks for MyAlerts validation
    $plugins->add_hook("admin_config_settings_start", "reactions_admin_settings_check_myalerts");
    $plugins->add_hook("admin_config_settings_change", "reactions_admin_settings_myalerts_notice");
    $plugins->add_hook("admin_config_settings_change_commit", "reactions_admin_settings_validate_myalerts");
    $plugins->add_hook("admin_formcontainer_output_row", "reactions_admin_settings_disable_myalerts_checkbox");
    $plugins->add_hook("admin_config_settings_change_commit", "reactions_admin_settings_myalerts_enable");
}

// Recount & Rebuild hooks
$plugins->add_hook("admin_tools_recount_rebuild_output_list", "reactions_admin_recount_rebuild_output_list");
$plugins->add_hook("admin_tools_recount_rebuild_stats", "reactions_admin_recount_rebuild_stats");

// Output tooltip HTML in page body (not head) - runs after all page content is generated
$plugins->add_hook("pre_output_page", "reactions_output_tooltip_html");

function reactions_info()
{
    global $PL, $mybb, $lang, $db;
    $PL or require_once PLUGINLIBRARY;

    // Only do heavy checks if we're actually on the plugins page to save resources
    if(defined('IN_ADMINCP') && ($mybb->input['module'] ?? '') == 'config-plugins')
    {
        reactions_plugin_edit();
    }

    $description = "Adds Facebook-style reaction system to MyBB posts with hover tooltips and AJAX functionality.";

    if (reactions_is_installed()) {
        // Only show integration status in Admin CP to save resources
        if(defined('IN_ADMINCP') && ($mybb->input['module'] ?? '') == 'config-plugins')
        {
            // Check MyAlerts integration status
            if(file_exists(MYBB_ROOT . "inc/plugins/myalerts.php"))
            {
                if(function_exists('reactions_have_myalerts') && reactions_have_myalerts(true))
                {
                    $integrate_setting = $mybb->settings['reactions_integrate_myalerts'] ?? '';
                    if($integrate_setting === '')
                    {
                        $integrate_setting = $db->fetch_field(
                            $db->simple_select('settings', 'value', "name = 'reactions_integrate_myalerts'", array('limit' => 1)),
                            'value'
                        );
                    }
                    if(!empty($integrate_setting))
                    {
                        $description .= "<br><span style='color: green;'>✓ MyAlerts integration active.</span>";
                    }
                    else
                    {
                        $description .= "<br><span style='color: orange;'>⚠ MyAlerts detected but integration is disabled in settings.</span>";
                    }
                }
                else
                {
                    $description .= "<br><span style='color: orange;'>⚠ MyAlerts detected but not active. Deactivate and reactivate the Like Reactions plugin to fully integrate.</span>";
                }
            }
        }
    }

    return array(
        "name"            => "Like Reactions",
        "description"    => $description,
        "website"        => "https://mybb.epizy.com",
        "author"        => "PARAD0X",
        "authorsite"    => "https://community.mybb.com/user-134311.html",
        "version"        => "1.3.5",
        "guid"            => "",
        "compatibility" => "18*"
    );
}

// Check if plugin is installed
function reactions_is_installed()
{
    global $db;
    
    // Check if reactions table exists
    if(!$db->table_exists("reactions"))
    {
        return false;
    }
    
    // Check if at least one reaction exists (means installation completed)
    $count = $db->fetch_field($db->simple_select("reactions", "COUNT(*) as count"), "count");
    if($count == 0)
    {
        return false;
    }
    
    return true;
}

// Install plugin
function reactions_install()
{
    // Load required functions
    require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/reaction_functions.php";
    require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/admin_functions.php";
    
    // Call install run function
    if(function_exists('reactions_install_run'))
    {
        reactions_install_run();
    }
    else
    {
        // Fallback if install.php not loaded
        global $db, $PL;
        
        if(file_exists(PLUGINLIBRARY))
        {
            require_once PLUGINLIBRARY;
            $PL = new PluginLibrary();
        }
        
        reactions_create_tables();
        reactions_add_settings();
        reactions_add_templates();
        reactions_add_stylesheet();
        reactions_add_javascript();
        reactions_copy_images();
        reactions_insert_default_reactions();
    }
}

// Activate plugin
function reactions_activate()
{
    // Load required functions
    require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/reaction_functions.php";
    require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/admin_functions.php";
    
    // Call activate run function
    if(function_exists('reactions_activate_run'))
    {
        reactions_activate_run();
    }
    else
    {
        // Fallback if install.php not loaded
        global $db, $PL;
        
        if(file_exists(PLUGINLIBRARY))
        {
            require_once PLUGINLIBRARY;
            $PL = new PluginLibrary();
        }
        
        if(function_exists('rebuild_settings'))
        {
            rebuild_settings();
        }
        
        reactions_apply_template_edits();
    }
}

// Deactivate plugin
function reactions_deactivate()
{
    // Load required functions
    require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/admin_functions.php";
    
    // Call deactivate run function
    if(function_exists('reactions_deactivate_run'))
    {
        reactions_deactivate_run();
    }
    else
    {
        // Fallback if install.php not loaded
        global $db, $PL;
        
        if(file_exists(PLUGINLIBRARY))
        {
            require_once PLUGINLIBRARY;
            $PL = new PluginLibrary();
        }
        
        reactions_remove_template_edits();
    }
}

// Uninstall plugin
function reactions_uninstall()
{
    // Load required functions
    require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/reaction_functions.php";
    require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/admin_functions.php";
    
    // Call uninstall run function
    if(function_exists('reactions_uninstall_run'))
    {
        reactions_uninstall_run();
    }
    else
    {
        // Fallback if install.php not loaded
        global $db, $PL;
        
        if(file_exists(PLUGINLIBRARY))
        {
            require_once PLUGINLIBRARY;
            $PL = new PluginLibrary();
        }
        
        reactions_drop_tables();
        reactions_remove_settings();
        reactions_remove_templates();
        reactions_remove_stylesheet();
        reactions_remove_javascript();
    }
}

// Global start hook
function reactions_global_start()
{
    global $mybb, $templatelist;
    
    // Add reaction templates to template list (same as thread_collaboration)
    if(!isset($templatelist))
    {
        $templatelist = "";
    }
    
    $templatelist .= ",reactions_tooltip,reactions_tooltip_item,reactions_bar,reactions_button,reactions_overlay,reactions_overlay_row,reactions_overlay_tab,reactions_summary_item";
    
    // Inject notification preferences for logged-in users (for pages other than showthread)
    // Only if admin allows user notification preferences
    if($mybb->user['uid'] && THIS_SCRIPT != 'showthread.php' && !empty($mybb->settings['reactions_user_notification_prefs']))
    {
        global $headerinclude;
        
        $notification_prefs = reactions_get_user_notification_prefs($mybb->user['uid']);
        $prefs_json = json_encode($notification_prefs);
        
        $prefs_script = '<script type="text/javascript">
var reactionsNotifyPrefs = '.$prefs_json.';
</script>';
        
        // Add to headerinclude if it exists
        if(isset($headerinclude))
        {
            $headerinclude .= $prefs_script;
        }
    }
}

// Hook to handle reactions_info action after member.php has loaded but before it processes unknown actions
$plugins->add_hook("global_end", "reactions_global_end");
function reactions_global_end()
{
    global $mybb;
    
    // Handle reactions_info action in member.php after global.php has fully loaded
    // This ensures all MyBB variables are properly set up
    if(THIS_SCRIPT == 'member.php' && $mybb->get_input('action') == 'reactions_info')
    {
        if(function_exists('reactions_member_reactions_info'))
        {
            reactions_member_reactions_info();
        }
    }
}

// Postbit hook
function reactions_postbit(&$post)
{
    // Display reactions in postbit
    if(function_exists('reactions_display_post_reactions'))
    {
        reactions_display_post_reactions($post);
    }
}

// Postbit preview hook — initialize reaction template variables to prevent undefined key errors
// The postbit template has {$post['author_reactions']}, {$post['reactions_bar']},
// and {$post['reaction_button']} inserted by find_replace_templatesets, but on newreply.php
// the preview calls build_postbit($post, 1) which fires postbit_prev, not postbit.
function reactions_postbit_prev(&$post)
{
    if(!isset($post['author_reactions']))
    {
        $post['author_reactions'] = '';
    }
    if(!isset($post['reactions_bar']))
    {
        $post['reactions_bar'] = '';
    }
    if(!isset($post['reaction_button']))
    {
        $post['reaction_button'] = '';
    }
}

// Postbit PM hook — initialize reaction template variables to prevent undefined key errors
// The postbit template has {$post['author_reactions']}, {$post['reactions_bar']},
// and {$post['reaction_button']} inserted by find_replace_templatesets, but on private.php
// only postbit_pm fires (not postbit), so these keys would never be set.
function reactions_postbit_pm(&$post)
{
    if(!isset($post['author_reactions']))
    {
        $post['author_reactions'] = '';
    }
    if(!isset($post['reactions_bar']))
    {
        $post['reactions_bar'] = '';
    }
    if(!isset($post['reaction_button']))
    {
        $post['reaction_button'] = '';
    }
}

// Showthread end hook
function reactions_showthread_end()
{
    global $mybb, $templates, $headerinclude;

    // Add reaction tooltip template to page
    // This sets $reactions_tooltip_html variable for use in templates
    if(function_exists('reactions_add_tooltip_template'))
    {
        reactions_add_tooltip_template();
    }

    // Set template variables for JavaScript and CSS
    $js_url = $mybb->settings['bburl'].'/inc/plugins/Reactions/Assets/reactions.js';
    $css_url = $mybb->settings['bburl'].'/inc/plugins/Reactions/Assets/reactions.css';

    // Add CSS and JS to headerinclude (head section)
    global $reactions_js_html, $reactions_css_html;
    if(!defined('REACTIONS_CSS_ADDED')) {
        $reactions_css_html = '<link rel="stylesheet" href="'.$css_url.'" type="text/css" />';
        $headerinclude .= $reactions_css_html;
        define('REACTIONS_CSS_ADDED', true);
    }

    if(!defined('REACTIONS_JS_ADDED')) {
        $reactions_js_html = '<script type="text/javascript" src="'.$js_url.'"></script>';
        $headerinclude .= reactions_js_lang_script() . $reactions_js_html;
        define('REACTIONS_JS_ADDED', true);
    }
}

// Output tooltip HTML at the end of page body (not in head)
function reactions_output_tooltip_html(&$page_content)
{
    global $reactions_tooltip_html;
    
    // Only add if we have tooltip HTML and it's not already in the page
    if(!empty($reactions_tooltip_html) && strpos($page_content, $reactions_tooltip_html) === false)
    {
        // Insert before closing body tag if possible, otherwise at the end
        if(strpos($page_content, '</body>') !== false)
        {
            $page_content = str_replace('</body>', $reactions_tooltip_html . '</body>', $page_content);
        }
        else
        {
            $page_content .= $reactions_tooltip_html;
        }
    }
}

// Admin menu hook
function reactions_admin_menu(&$sub_menu)
{
    global $lang;
    
    if(!isset($lang->reactions_admin_menu_title))
    {
        $lang->load("reactions");
    }
    
    $sub_menu[] = array(
        "id" => "reactions",
        "title" => $lang->reactions_admin_menu_title,
        "link" => "index.php?module=config-reactions"
    );
}

// Admin action handler hook
function reactions_admin_action_handler(&$actions)
{
    $actions['reactions'] = array(
        "active" => "reactions",
        "file" => "reactions.php"
    );
}

// Admin load hook
function reactions_admin_load()
{
    global $mybb, $page, $lang, $db, $cache, $sub_tabs;
    
    if($page->active_action == "reactions")
    {
        if(!isset($lang->reactions_admin_menu_title))
        {
            $lang->load("reactions");
        }
        $page->add_breadcrumb_item($lang->reactions_admin_menu_title, "index.php?module=config-reactions");
        
        // Load the admin module file
        $admin_file = MYBB_ROOT . "inc/plugins/Reactions/admin/reactions.php";
        if(file_exists($admin_file))
        {
            require $admin_file;
            exit; // Exit after loading the module to prevent further processing
        }
    }
}

// XMLHTTP hook
function reactions_xmlhttp()
{
    global $mybb, $db, $templates, $lang, $plugins, $theme;

    if(isset($mybb->input['action']) && ($mybb->input['action'] == 'react' || $mybb->input['action'] == 'get_reactions' || $mybb->input['action'] == 'get_history' || $mybb->input['action'] == 'get_points'))
    {
        // Ensure $templates is initialized for AJAX requests
        if(!isset($templates) || !is_object($templates))
        {
            require_once MYBB_ROOT."inc/class_templates.php";
            $templates = new templates;
        }

        // We also need $lang to be loaded for global and xmlhttp if not already
        if(!isset($lang->xmlhttp))
        {
            $lang->load("global");
            $lang->load("xmlhttp");
        }

        // Start output buffering to catch any accidental output (notices, etc.)
        ob_start();

        if($mybb->input['action'] == 'react' && function_exists('reactions_handle_ajax_react'))
        {
            reactions_handle_ajax_react();
        }
        elseif($mybb->input['action'] == 'get_reactions' && function_exists('reactions_handle_ajax_get_reactions'))
        {
            reactions_handle_ajax_get_reactions();
        }
        elseif($mybb->input['action'] == 'get_history' && function_exists('reactions_handle_ajax_get_history'))
        {
            reactions_handle_ajax_get_history();
        }
        elseif($mybb->input['action'] == 'get_points')
        {
            reactions_handle_ajax_get_points();
        }

        // Clean output buffer
        ob_end_clean();
    }
}

function reactions_plugin_edit()
{
    global $mybb;

    // Core edits are no longer needed for MyBB 1.8.x
    // This function is kept for backwards compatibility
    if (!empty($mybb->input['my_post_key']) && ($mybb->input['my_post_key'] ?? '') == $mybb->post_code) {
        if (($mybb->input['reactions_plugin'] ?? '') == 'apply' || ($mybb->input['reactions_plugin'] ?? '') == 'revert') {
            flash_message('No core edits are required for MyBB 1.8.x. The plugin works through hooks.', 'info');
            admin_redirect('index.php?module=config-plugins');
        }
    }
}