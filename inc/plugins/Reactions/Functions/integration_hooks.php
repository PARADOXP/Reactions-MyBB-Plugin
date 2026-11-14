<?php
/**
 * Reaction Plugin - Integration Hooks
 * 
 * This file contains the hook functions for third-party plugin integration.
 * Add hooks at strategic points in the reaction system.
 * 
 * @author PARAD0X
 * @version 1.3.2
 */

if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

/**
 * Fire hook after a reaction is added
 * 
 * @param int $reaction_content_id
 * @param int $reaction_id
 * @param string $content_type
 * @param int $content_id
 * @param int $giver_id
 * @param int $receiver_id
 * @param int $reaction_date
 * @param int $reaction_score
 */
function reactions_hook_after_add($reaction_content_id, $reaction_id, $content_type, $content_id, $giver_id, $receiver_id, $reaction_date, $reaction_score)
{
    global $plugins;
    
    $hook_args = array(
        'reaction_content_id' => $reaction_content_id,
        'reaction_id' => $reaction_id,
        'content_type' => $content_type,
        'content_id' => $content_id,
        'giver_id' => $giver_id,
        'receiver_id' => $receiver_id,
        'reaction_date' => $reaction_date,
        'reaction_score' => $reaction_score
    );
    
    $plugins->run_hooks('reactions_after_add', $hook_args);
}

/**
 * Fire hook after a reaction is removed
 * 
 * @param int $reaction_content_id
 * @param int $reaction_id
 * @param string $content_type
 * @param int $content_id
 * @param int $giver_id
 * @param int $receiver_id
 * @param int $reaction_score
 */
function reactions_hook_after_remove($reaction_content_id, $reaction_id, $content_type, $content_id, $giver_id, $receiver_id, $reaction_score)
{
    global $plugins;
    
    $hook_args = array(
        'reaction_content_id' => $reaction_content_id,
        'reaction_id' => $reaction_id,
        'content_type' => $content_type,
        'content_id' => $content_id,
        'giver_id' => $giver_id,
        'receiver_id' => $receiver_id,
        'reaction_score' => $reaction_score
    );
    
    $plugins->run_hooks('reactions_after_remove', $hook_args);
}

/**
 * Fire hook after a reaction is updated (changed from one type to another)
 * 
 * @param int $reaction_content_id
 * @param int $old_reaction_id
 * @param int $new_reaction_id
 * @param string $content_type
 * @param int $content_id
 * @param int $giver_id
 * @param int $receiver_id
 * @param int $old_score
 * @param int $new_score
 */
function reactions_hook_after_update($reaction_content_id, $old_reaction_id, $new_reaction_id, $content_type, $content_id, $giver_id, $receiver_id, $old_score, $new_score)
{
    global $plugins;
    
    $hook_args = array(
        'reaction_content_id' => $reaction_content_id,
        'old_reaction_id' => $old_reaction_id,
        'new_reaction_id' => $new_reaction_id,
        'content_type' => $content_type,
        'content_id' => $content_id,
        'giver_id' => $giver_id,
        'receiver_id' => $receiver_id,
        'old_score' => $old_score,
        'new_score' => $new_score,
        'score_diff' => $new_score - $old_score
    );
    
    $plugins->run_hooks('reactions_after_update', $hook_args);
}

/**
 * Fire hook when checking if a user can react
 * Plugins can modify the result by returning an array with 'can_react' and 'error'
 * 
 * @param string $content_type
 * @param int $content_id
 * @param int $user_id
 * @param int $content_user_id
 * @param array $current_result Current result array
 * @return array|null Modified result or null to continue
 */
function reactions_hook_can_react($content_type, $content_id, $user_id, $content_user_id, $current_result)
{
    global $plugins;
    
    $hook_args = array(
        'content_type' => $content_type,
        'content_id' => $content_id,
        'user_id' => $user_id,
        'content_user_id' => $content_user_id,
        'can_react' => $current_result['can_react'],
        'error' => isset($current_result['error']) ? $current_result['error'] : ''
    );
    
    $plugins->run_hooks('reactions_can_react_check', $hook_args);
    
    // Check if any plugin modified the result
    if(isset($hook_args['can_react']) && $hook_args['can_react'] !== $current_result['can_react'])
    {
        return array(
            'can_react' => $hook_args['can_react'],
            'error' => isset($hook_args['error']) ? $hook_args['error'] : ''
        );
    }
    
    return null;
}

/**
 * Register custom content types for reactions
 * 
 * @return array Content type handlers
 */
function reactions_hook_register_content_types()
{
    global $plugins;
    
    $content_types = array();
    $plugins->run_hooks('reactions_register_content_types', $content_types);
    
    return $content_types;
}

/**
 * Fire hook at the start of profile reaction history
 * 
 * @param int $uid Profile user ID
 */
function reactions_hook_profile_history_start($uid)
{
    global $plugins;
    
    $plugins->run_hooks('reactions_profile_history_start', $uid);
}

/**
 * Fire hook at the end of profile reaction history
 * 
 * @param int $uid Profile user ID
 */
function reactions_hook_profile_history_end($uid)
{
    global $plugins;
    
    $plugins->run_hooks('reactions_profile_history_end', $uid);
}

/**
 * Fire hook at the start of profile reaction stats
 * 
 * @param int $uid Profile user ID
 */
function reactions_hook_profile_stats_start($uid)
{
    global $plugins;
    
    $plugins->run_hooks('reactions_profile_stats_start', $uid);
}

/**
 * Fire hook at the end of profile reaction stats
 * 
 * @param int $uid Profile user ID
 */
function reactions_hook_profile_stats_end($uid)
{
    global $plugins;
    
    $plugins->run_hooks('reactions_profile_stats_end', $uid);
}

/**
 * Add or subtract reaction points from a user
 * This function can be called by other plugins to award/manage points
 * 
 * @param int $user_id User ID
 * @param int $points Points to add (negative to subtract)
 * @param string $reason Reason for the point change
 * @param string $content_type Optional content type reference
 * @param int $content_id Optional content ID reference
 * @return bool Success
 */
function reactions_add_points($user_id, $points, $reason = '', $content_type = '', $content_id = 0)
{
    global $db, $mybb;
    
    $user_id = (int)$user_id;
    $points = (int)$points;
    
    if($user_id <= 0 || $points == 0)
    {
        return false;
    }
    
    // Update user's reaction score
    $db->write_query("
        UPDATE ".TABLE_PREFIX."users 
        SET reaction_score = reaction_score + ".$points." 
        WHERE uid=".$user_id
    );
    
    // Log the point change if content type is provided
    if($content_type && $content_id)
    {
        $log_data = array(
            'user_id' => $user_id,
            'points' => $points,
            'reason' => $db->escape_string($reason),
            'content_type' => $db->escape_string($content_type),
            'content_id' => (int)$content_id,
            'log_date' => TIME_NOW
        );
        
        // You could create a reactions_points_log table for this
        // $db->insert_query('reactions_points_log', $log_data);
    }
    
    // Fire hook so other plugins know points were added
    global $plugins;
    $hook_args = array(
        'user_id' => $user_id,
        'points' => $points,
        'reason' => $reason,
        'content_type' => $content_type,
        'content_id' => $content_id
    );
    $plugins->run_hooks('reactions_points_added', $hook_args);
    
    return true;
}

/**
 * Get content handler for a specific content type
 * This allows plugins to register handlers for their custom content types
 * 
 * @param string $content_type
 * @return object|null Handler object or null
 */
function reactions_get_content_handler($content_type)
{
    static $handlers = null;
    static $loaded = false;
    
    if(!$loaded)
    {
        $handlers = reactions_hook_register_content_types();
        $loaded = true;
    }
    
    if(isset($handlers[$content_type]) && isset($handlers[$content_type]['handler_class']))
    {
        $class_name = $handlers[$content_type]['handler_class'];
        if(class_exists($class_name))
        {
            return new $class_name();
        }
    }
    
    return null;
}

/**
 * Check if a content type has a custom handler
 * 
 * @param string $content_type
 * @return bool
 */
function reactions_has_content_handler($content_type)
{
    $handlers = reactions_hook_register_content_types();
    return isset($handlers[$content_type]);
}
?>