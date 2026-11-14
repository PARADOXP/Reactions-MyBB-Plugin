<?php
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

/**
 * UserCP Reactions Settings Page
 * Handles the reactions settings page in UserCP under Miscellaneous
 */

/**
 * Hook: usercp_start
 * Handle reactions settings page display and form submission
 */
function reactions_usercp_settings_page()
{
    global $mybb, $db, $lang, $templates, $theme, $headerinclude, $header, $footer, $usercpnav, $plugins;
    
    // Check if this is our action
    if($mybb->get_input('action') != 'reactions')
    {
        return;
    }
    
    // Load reactions language
    $lang->load('reactions');
    
    // Handle form submission
    if($mybb->request_method == 'post' && verify_post_check($mybb->get_input('my_post_key')))
    {
        // Update notification preferences
        $notify_prefs = $mybb->get_input('reactions_notify', MyBB::INPUT_ARRAY);
        
        // Bit map for notification types
        $bit_map = array(
            'reactions_disabled' => 1,
            'no_permission_use' => 2,
            'no_permission_remove' => 4,
            'disabled_in_forum' => 8,
            'usergroup_no_give' => 16,
            'recipient_no_receive' => 32,
            'self_reaction_not_allowed' => 64,
            'min_posts_required' => 128,
            'min_reputation_required' => 256,
            'daily_limit_reached' => 512,
            'flood_control' => 1024,
            'reaction_success' => 2048,
            'thread_closed' => 4096,
        );
        
        $prefs_value = 0;
        foreach($bit_map as $type => $bit)
        {
            if(!empty($notify_prefs[$type]))
            {
                $prefs_value |= $bit;
            }
        }
        
        // Save notification preferences
        $db->update_query("users", array('reactions_notify_prefs' => $prefs_value), "uid=".(int)$mybb->user['uid']);
        
        // Handle MyAlerts reaction settings if MyAlerts integration is enabled
        if(!empty($mybb->settings['reactions_integrate_myalerts']) && 
           file_exists(MYBB_ROOT . 'inc/plugins/myalerts.php') && 
           class_exists('MybbStuff_MyAlerts_AlertTypeManager'))
        {
            $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
            if($alertTypeManager)
            {
                // Get current disabled alerts
                $disabled_alerts = array();
                if(!empty($mybb->user['myalerts_disabled_alert_types']))
                {
                    // Handle both string (JSON) and array formats
                    if(is_string($mybb->user['myalerts_disabled_alert_types']))
                    {
                        $disabled_alerts = json_decode($mybb->user['myalerts_disabled_alert_types'], true);
                    }
                    elseif(is_array($mybb->user['myalerts_disabled_alert_types']))
                    {
                        $disabled_alerts = $mybb->user['myalerts_disabled_alert_types'];
                    }
                    if(!is_array($disabled_alerts))
                    {
                        $disabled_alerts = array();
                    }
                }
                
                // Handle reaction added alert (default: enabled/checked)
                $alertType = $alertTypeManager->getByCode('reaction');
                if($alertType !== null)
                {
                    $reaction_alert_id = $alertType->getId();
                    
                    if($mybb->get_input('myalerts_reaction', MyBB::INPUT_INT))
                    {
                        // Enabled - remove from disabled list
                        $key = array_search($reaction_alert_id, $disabled_alerts);
                        if($key !== false)
                        {
                            unset($disabled_alerts[$key]);
                        }
                    }
                    else
                    {
                        // Disabled - add to disabled list
                        if(!in_array($reaction_alert_id, $disabled_alerts))
                        {
                            $disabled_alerts[] = $reaction_alert_id;
                        }
                    }
                }
                
                // Handle reaction removed alert (default: disabled/unchecked)
                $alertTypeRemoved = $alertTypeManager->getByCode('reaction_removed');
                if($alertTypeRemoved !== null)
                {
                    $reaction_removed_alert_id = $alertTypeRemoved->getId();
                    
                    // Check if this is the first time saving (no existing config)
                    $is_first_save = empty($mybb->user['myalerts_disabled_alert_types']);
                    
                    if(isset($mybb->input['myalerts_reaction_removed']))
                    {
                        // Enabled - remove from disabled list
                        $key = array_search($reaction_removed_alert_id, $disabled_alerts);
                        if($key !== false)
                        {
                            unset($disabled_alerts[$key]);
                        }
                    }
                    else
                    {
                        // Disabled - add to disabled list
                        if(!in_array($reaction_removed_alert_id, $disabled_alerts))
                        {
                            $disabled_alerts[] = $reaction_removed_alert_id;
                        }
                    }
                    
                    // On first save, if reaction_removed is not checked, ensure it's in disabled list
                    if($is_first_save && !isset($mybb->input['myalerts_reaction_removed']))
                    {
                        if(!in_array($reaction_removed_alert_id, $disabled_alerts))
                        {
                            $disabled_alerts[] = $reaction_removed_alert_id;
                        }
                    }
                }
                
                // Update database
                $disabled_alerts_json = $db->escape_string(json_encode(array_values($disabled_alerts)));
                $db->update_query('users', array(
                    'myalerts_disabled_alert_types' => $disabled_alerts_json
                ), 'uid=' . (int)$mybb->user['uid']);
                
                // Update session variable - store as JSON string to match database format
                $mybb->user['myalerts_disabled_alert_types'] = json_encode(array_values($disabled_alerts));
            }
        }
        
        // Redirect with success message
        $lang->load('reactions');
        redirect('usercp.php?action=reactions', $lang->reactions_settings_updated);
    }
    
    // Add breadcrumb
    add_breadcrumb($lang->nav_usercp, 'usercp.php');
    add_breadcrumb($lang->reactions_settings_title, 'usercp.php?action=reactions');
    
    // Note: usercp_menu() is already called by UserCP, don't call it again
    // The $usercpnav and $usercpmenu variables are already set
    
    // Get user's current notification preferences
    $prefs = reactions_get_user_notification_prefs($mybb->user['uid']);
    
    // Build notification checkboxes
    $notification_checkboxes = '';
    $notification_types = array(
        'reactions_disabled' => $lang->reactions_notify_disabled,
        'no_permission_use' => $lang->reactions_notify_no_permission_use,
        'no_permission_remove' => $lang->reactions_notify_no_permission_remove,
        'disabled_in_forum' => $lang->reactions_notify_disabled_in_forum,
        'thread_closed' => $lang->reactions_notify_thread_closed,
        'usergroup_no_give' => $lang->reactions_notify_usergroup_no_give,
        'recipient_no_receive' => $lang->reactions_notify_recipient_no_receive,
        'self_reaction_not_allowed' => $lang->reactions_notify_self_reaction_not_allowed,
        'min_posts_required' => $lang->reactions_notify_min_posts_required,
        'min_reputation_required' => $lang->reactions_notify_min_reputation_required,
        'daily_limit_reached' => $lang->reactions_notify_daily_limit_reached,
        'flood_control' => $lang->reactions_notify_flood_control,
        'reaction_success' => $lang->reactions_notify_reaction_success,
    );
    
    foreach($notification_types as $type => $label)
    {
        $checked = !empty($prefs[$type]) ? 'checked="checked"' : '';
        $altbg = alt_trow();
        eval('$notification_checkboxes .= "' . $templates->get('reactions_usercp_setting_row') . '";');
    }
    
    // MyAlerts integration section
    $myalerts_section = '';
    
    // Only show MyAlerts settings if integration is enabled in settings
    if(!empty($mybb->settings['reactions_integrate_myalerts']) && 
       file_exists(MYBB_ROOT . 'inc/plugins/myalerts.php') && 
       class_exists('MybbStuff_MyAlerts_AlertTypeManager'))
    {
        $myalerts_checkbox = '';
        $myalerts_removed_checkbox = '';
        
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
        if($alertTypeManager)
        {
            // Reaction added alert
            $alertType = $alertTypeManager->getByCode('reaction');
            if($alertType !== null)
            {
                $reaction_alert_id = $alertType->getId();
                
                // Check if user has disabled this alert type
                $checked = 'checked="checked"';
                $disabled_types = array();
                
                if(!empty($mybb->user['myalerts_disabled_alert_types']))
                {
                    // Handle both string (JSON) and array formats
                    if(is_string($mybb->user['myalerts_disabled_alert_types']))
                    {
                        $disabled_types = json_decode($mybb->user['myalerts_disabled_alert_types'], true);
                    }
                    elseif(is_array($mybb->user['myalerts_disabled_alert_types']))
                    {
                        $disabled_types = $mybb->user['myalerts_disabled_alert_types'];
                    }
                    
                    if(is_array($disabled_types) && in_array($reaction_alert_id, $disabled_types))
                    {
                        $checked = '';
                    }
                }
                
                // Get alternating row color for first checkbox
                $altbg = alt_trow();
                
                // Reaction removed alert - generate this first since it's included inside myalerts_row
                $alertTypeRemoved = $alertTypeManager->getByCode('reaction_removed');
                if($alertTypeRemoved !== null)
                {
                    $reaction_removed_alert_id = $alertTypeRemoved->getId();
                    
                    // Check if user has enabled this alert type
                    // Checked when NOT in the disabled list (same as reaction alert)
                    $checked_removed = '';
                    if(!in_array($reaction_removed_alert_id, $disabled_types))
                    {
                        // Not in disabled list = enabled = checked
                        $checked_removed = 'checked="checked"';
                    }
                    
                    // Get next alternating row color for second checkbox
                    $altbg_removed = alt_trow();
                    // Temporarily set altbg for the removed checkbox template
                    $saved_altbg = $altbg;
                    $altbg = $altbg_removed;
                    eval('$myalerts_removed_checkbox = "' . $templates->get('reactions_usercp_myalerts_removed_row') . '";');
                    // Restore altbg for the main checkbox
                    $altbg = $saved_altbg;
                }
                
                eval('$myalerts_checkbox = "' . $templates->get('reactions_usercp_myalerts_row') . '";');
                
                // Build the complete MyAlerts section
                $myalerts_section = $myalerts_checkbox;
            }
        }
    }
    
    // Output the page
    eval('$content = "' . $templates->get('reactions_usercp_settings_page') . '";');
    output_page($content);
    exit;
}

/**
 * Hook: usercp_menu (priority 40 - runs after usercp_menu_misc)
 * Add Reactions Settings link to UserCP Miscellaneous section
 */
function reactions_usercp_menu_item()
{
    global $mybb, $templates, $lang, $usercpmenu;
    
    // Load reactions language
    if(!isset($lang->reactions_settings_title))
    {
        $lang->load('reactions');
    }
    
    // Build the menu item
    $menu_item_template = $templates->get('reactions_usercp_menu_item');
    if(empty($menu_item_template))
    {
        return; // Template not found
    }
    
    eval('$menu_item = "' . $menu_item_template . '";');
    
    // Try to insert after the "View Profile" link (last item in misc section)
    $search = 'usercp_nav_viewprofile">' . $lang->ucp_nav_view_profile . '</a></td></tr>';
    $replace = 'usercp_nav_viewprofile">' . $lang->ucp_nav_view_profile . '</a></td></tr>' . $menu_item;
    
    $new_menu = str_replace($search, $replace, $usercpmenu);
    
    // If replacement failed, try a more generic approach - append to end of menu
    if($new_menu === $usercpmenu)
    {
        // Find the last </tr> in the menu and insert after it
        $pos = strrpos($usercpmenu, '</tr>');
        if($pos !== false)
        {
            $new_menu = substr_replace($usercpmenu, '</tr>' . $menu_item, $pos, 4);
        }
    }
    
    $usercpmenu = $new_menu;
}
