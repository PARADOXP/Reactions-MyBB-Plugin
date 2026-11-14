<?php
if(!defined('IN_MYBB')) { die('Direct initialization of this file is not allowed.'); }

if (!defined('PLUGINLIBRARY')) {
    define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');
}

// Load required function files
require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/reaction_functions.php";
require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/admin_functions.php";

function reactions_install_run()
{
    global $db;
    
    // Create database tables
    reactions_create_tables();
    
    // Insert default reactions
    reactions_insert_default_reactions();
}

/**
 * Fix template URLs to ensure consistency
 * Updates database templates to use correct reactions.php URL
 */
function reactions_fix_template_urls()
{
    global $db;

    // Correct URL pattern for reaction bar
    $correct_pattern = 'href="{$reactions_link}"';
    $wrong_patterns = array(
        'href="{$mybb->settings[\'bburl\']}/xmlhttp.php?action=get_reactions',
        'href="{$mybb->settings[\'bburl\']}\/xmlhttp.php?action=get_reactions',
        'href="xmlhttp.php?action=get_reactions'
    );

    // Check and fix reactions_bar template
    $query = $db->simple_select('templates', 'tid, template', "title='reactions_bar'");
    while($row = $db->fetch_array($query))
    {
        $template = $row['template'];

        // Check if it has wrong URL pattern
        foreach($wrong_patterns as $wrong)
        {
            if(strpos($template, $wrong) !== false)
            {
                // Fix the template - replace xmlhttp.php with reactions_link variable
                $template = str_replace($wrong, $correct_pattern, $template);
                $db->update_query('templates', array('template' => $db->escape_string($template)), "tid=".$row['tid']);
            }
        }
    }

    // Clear template cache
    $db->delete_query('datacache', "title='templates'");
}

function reactions_activate_run()
{
    global $db, $PL, $mybb;

    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
    }

    // Ensure settings exist and are up to date (Thread Collaboration style)
    reactions_add_settings();

    // Insert any missing default reactions (safe for upgrades - skips existing)
    reactions_insert_default_reactions();

    // Add templates using PluginLibrary (Thread Collaboration style)
    reactions_add_templates();

    // Rebuild settings
    if(function_exists('rebuild_settings'))
    {
        rebuild_settings();
        // Ensure the local $mybb->settings reflects the DB value
        $mybb->settings['reactions_integrate_myalerts'] = $db->fetch_field(
            $db->simple_select('settings', 'value', "name = 'reactions_integrate_myalerts'", array('limit' => 1)),
            'value'
        );
    }

    // Auto-integrate with MyAlerts if installed
    if(file_exists(MYBB_ROOT . 'inc/plugins/myalerts.php'))
    {
        require_once MYBB_ROOT . 'inc/plugins/Reactions/Functions/myalerts_functions.php';
        if(function_exists('reactions_myalerts_integrate'))
        {
            reactions_myalerts_integrate();
        }
    }

    // Apply template edits using find_replace_templatesets (like PM Edit)
    require_once MYBB_ROOT."inc/adminfunctions_templates.php";

    // Add Font Awesome CSS to headerinclude template
    find_replace_templatesets("headerinclude", "#" . preg_quote('{$stylesheets}') . "#i", '{$stylesheets}' . "\n" . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />');

    // Add reactions bar after signature for postbit and postbit_classic
    find_replace_templatesets("postbit", "#" . preg_quote('{$post[\'signature\']}') . "#i", '{$post[\'signature\']}{$post[\'reactions_bar\']}');
    find_replace_templatesets("postbit_classic", "#" . preg_quote('{$post[\'signature\']}') . "#i", '{$post[\'signature\']}{$post[\'reactions_bar\']}');

    // Add reaction score to author statistics for postbit and postbit_classic
    find_replace_templatesets("postbit", "#" . preg_quote('{$post[\'user_details\']}') . "#i", '{$post[\'user_details\']}{$post[\'author_reactions\']}');
    find_replace_templatesets("postbit_classic", "#" . preg_quote('{$post[\'user_details\']}') . "#i", '{$post[\'user_details\']}{$post[\'author_reactions\']}');

    // Add reaction score to profile
    find_replace_templatesets("member_profile", "#" . preg_quote('{$profilefields}') . "#i", '{$profilefields}{$reactions_profile_stats}');

    // Add reaction button at FIRST position in postbit buttons list
    find_replace_templatesets("postbit", "#(<div class=\"postbit_buttons post_management_buttons float_right\">)#i", '$1{$post[\'reaction_button\']}');
    
    // Add reaction button at FIRST position in postbit_classic buttons list
    find_replace_templatesets("postbit_classic", "#(<div class=\"postbit_buttons post_management_buttons float_right\">)#i", '$1{$post[\'reaction_button\']}');

    // Fix template URLs to ensure consistency (reactions.php vs xmlhttp.php)
    reactions_fix_template_urls();

    // Install stylesheet via PluginLibrary
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

function reactions_deactivate_run()
{
    global $db, $PL;

    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
    }

    // Remove template edits using find_replace_templatesets (like PM Edit)
    require_once MYBB_ROOT."inc/adminfunctions_templates.php";

    // Remove reactions bar from both postbit and postbit_classic
    find_replace_templatesets("postbit", "#" . preg_quote('{$post[\'signature\']}{$post[\'reactions_bar\']}') . "#i", '{$post[\'signature\']}');
    find_replace_templatesets("postbit_classic", "#" . preg_quote('{$post[\'signature\']}{$post[\'reactions_bar\']}') . "#i", '{$post[\'signature\']}');

    // Remove reaction score from author
    find_replace_templatesets("postbit", "#" . preg_quote('{$post[\'user_details\']}{$post[\'author_reactions\']}') . "#i", '{$post[\'user_details\']}');
    find_replace_templatesets("postbit_classic", "#" . preg_quote('{$post[\'user_details\']}{$post[\'author_reactions\']}') . "#i", '{$post[\'user_details\']}');

    // Remove reaction score from profile
    find_replace_templatesets("member_profile", "#" . preg_quote('{$profilefields}{$reactions_profile_stats}') . "#i", '{$profilefields}');

    // Remove reaction button from first position in buttons list
    find_replace_templatesets("postbit", "#(<div class=\"postbit_buttons post_management_buttons float_right\">)" . preg_quote('{$post[\'reaction_button\']}') . "#i", '$1');
    find_replace_templatesets("postbit_classic", "#(<div class=\"postbit_buttons post_management_buttons float_right\">)" . preg_quote('{$post[\'reaction_button\']}') . "#i", '$1');

    // Remove Font Awesome CSS from headerinclude
    find_replace_templatesets("headerinclude", "#\n?" . preg_quote('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />') . "#i", '');
}

function reactions_uninstall_run()
{
    global $db, $PL;
    
    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
    }
    
    // Unintegrate from MyAlerts
    if(file_exists(MYBB_ROOT . "inc/plugins/Reactions/Functions/myalerts_functions.php"))
    {
        require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/myalerts_functions.php";
        reactions_myalerts_unintegrate();
    }
    
    // Remove template edits (same as deactivate)
    require_once MYBB_ROOT."inc/adminfunctions_templates.php";
    
    // Remove reactions bar
    find_replace_templatesets("postbit", "#" . preg_quote('{$post[\'signature\']}{$post[\'reactions_bar\']}') . "#i", '{$post[\'signature\']}');
    find_replace_templatesets("postbit_classic", "#" . preg_quote('{$post[\'signature\']}{$post[\'reactions_bar\']}') . "#i", '{$post[\'signature\']}');
    
    // Remove reaction score from author
    find_replace_templatesets("postbit", "#" . preg_quote('{$post[\'user_details\']}{$post[\'author_reactions\']}') . "#i", '{$post[\'user_details\']}');
    find_replace_templatesets("postbit_classic", "#" . preg_quote('{$post[\'user_details\']}{$post[\'author_reactions\']}') . "#i", '{$post[\'user_details\']}');
    
    // Remove reaction score from profile
    find_replace_templatesets("member_profile", "#" . preg_quote('{$profilefields}{$reactions_profile_stats}') . "#i", '{$profilefields}');
    
    // Remove reaction button
    find_replace_templatesets("postbit", "#(<div class=\"postbit_buttons post_management_buttons float_right\">)" . preg_quote('{$post[\'reaction_button\']}') . "#i", '$1');
    find_replace_templatesets("postbit_classic", "#(<div class=\"postbit_buttons post_management_buttons float_right\">)" . preg_quote('{$post[\'reaction_button\']}') . "#i", '$1');
    
    // Remove Font Awesome CSS from headerinclude
    find_replace_templatesets("headerinclude", "#\n?" . preg_quote('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />') . "#i", '');
    
    // Drop tables
    reactions_drop_tables();
    
    // Remove settings
    reactions_remove_settings();
    
    // Remove templates
    reactions_remove_templates();
    
    // Remove stylesheet
    reactions_remove_stylesheet();
}
