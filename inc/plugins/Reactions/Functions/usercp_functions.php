<?php
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

/**
 * UserCP Options - Add Reactions Notification Settings
 * Hook: usercp_options_end
 */
function reactions_usercp_options()
{
    global $mybb, $db, $templates, $lang, $reactions_notification_options;

    // Check if admin allows users to configure notification preferences
    if(empty($mybb->settings['reactions_user_notification_prefs']))
    {
        $reactions_notification_options = '';
        return;
    }

    // Load language if not loaded (check for a variable that actually exists)
    if(!isset($lang->reactions_notifications_title))
    {
        $lang->load("reactions");
    }

    // Get user's current notification preferences
    $prefs = reactions_get_user_notification_prefs($mybb->user['uid']);

    // Set template variables for checked states
    // Removed: guests_not_allowed, reaction_error, ajax_error, edit_time_expired
    $notification_types = array(
        'reactions_disabled',
        'no_permission_use',
        'no_permission_remove',
        'disabled_in_forum',
        'thread_closed',
        'usergroup_no_give',
        'recipient_no_receive',
        'self_reaction_not_allowed',
        'min_posts_required',
        'min_reputation_required',
        'daily_limit_reached',
        'flood_control',
        'reaction_success',
    );

    foreach($notification_types as $type)
    {
        $var_name = 'reactions_notify_' . $type;
        $$var_name = !empty($prefs[$type]) ? 'checked="checked"' : '';
    }

    // Load template from file (same pattern as other templates in this plugin)
    $usercp_template = reactions_load_template('usercp_options');
    
    if($usercp_template)
    {
        eval("\$reactions_notification_options = \"" . $usercp_template . "\";");
    }
    else
    {
        // Fallback if template not found
        $reactions_notification_options = '';
    }
}

/**
 * UserCP Do Options - Save Reactions Notification Settings
 * Hook: usercp_do_options_end
 */
function reactions_usercp_do_options()
{
    global $mybb, $db;

    // Only save if admin allows user notification preferences
    if(empty($mybb->settings['reactions_user_notification_prefs']))
    {
        return;
    }

    // Calculate the bitmask from the form input
    $notify_prefs = $mybb->get_input('reactions_notify', MyBB::INPUT_ARRAY);

    // Bit map for notification types (removed: guests_not_allowed, reaction_error, ajax_error, edit_time_expired)
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

    // Save to database
    $db->update_query("users", array('reactions_notify_prefs' => $prefs_value), "uid=".(int)$mybb->user['uid']);
}

/**
 * Get notification type key from error message
 * Maps error messages to notification type keys
 */
function reactions_get_notification_type_from_error($error_message)
{
    global $lang;

    // Load language if not loaded
    if(!isset($lang->reactions_error_no_permission_use))
    {
        $lang->load("reactions");
    }

    $error_map = array(
        'reactions_disabled' => $lang->reactions_error_reactions_disabled,
        'no_permission_use' => $lang->reactions_error_no_permission_use,
        'no_permission_remove' => $lang->reactions_error_no_permission_remove,
        'disabled_in_forum' => $lang->reactions_error_disabled_in_forum,
        'usergroup_no_give' => $lang->reactions_error_usergroup_no_give,
        'recipient_no_receive' => $lang->reactions_error_recipient_no_receive,
        'guests_not_allowed' => $lang->reactions_error_guests_not_allowed,
        'self_reaction_not_allowed' => $lang->reactions_error_self_reaction_not_allowed,
        'min_posts_required' => $lang->reactions_error_min_posts_required,
        'min_reputation_required' => $lang->reactions_error_min_reputation_required,
        'daily_limit_reached' => $lang->reactions_error_daily_limit_reached,
        'flood_control' => $lang->reactions_error_flood_control,
        'edit_time_expired' => $lang->reactions_error_edit_time_expired,
    );

    foreach($error_map as $type => $pattern)
    {
        // Convert {1}/{2} placeholders into wildcard matches
        $regex = '/^' . preg_quote($pattern, '/') . '$/';
        $regex = str_replace(array('\{1\}', '\{2\}'), '.+', $regex);
        if(preg_match($regex, $error_message))
        {
            return $type;
        }
    }

    return 'reaction_error'; // Default fallback
}

/**
 * Check if a notification should be shown based on user preferences
 * This function is used by the AJAX handler
 */
function reactions_should_show_notification($error_message, $notification_type = null)
{
    if($notification_type === null)
    {
        $notification_type = reactions_get_notification_type_from_error($error_message);
    }

    return reactions_notification_enabled($notification_type);
}
