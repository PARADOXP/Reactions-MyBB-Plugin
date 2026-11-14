<?php
/**
 * Reaction Plugin - Developer Integration Guide
 * 
 * This file demonstrates how other plugin developers can integrate with the Reaction Plugin.
 * 
 * @author Your Name
 * @version 1.3.2
 */

// If accessed directly, display the source code as plain text
if(!defined("IN_MYBB"))
{
    header('Content-Type: text/plain');
    readfile(__FILE__);
    exit;
}

// ============================================================================
// INTEGRATION APPROACH 1: REACTION EVENT HOOKS
// ============================================================================
// These hooks fire when reactions are added, removed, or updated
// Use these to perform actions when reaction events occur

/**
 * Hook into reaction events - ADD THIS TO YOUR PLUGIN'S MAIN FILE
 */

// Register hooks for reaction events
$plugins->add_hook('reactions_after_add', 'yourplugin_on_reaction_added');
$plugins->add_hook('reactions_after_remove', 'yourplugin_on_reaction_removed');
$plugins->add_hook('reactions_after_update', 'yourplugin_on_reaction_updated');
$plugins->add_hook('reactions_points_added', 'yourplugin_on_points_added');

/**
 * Called after a reaction is added
 * 
 * @param array $args Array containing:
 *   - reaction_content_id: ID of the reaction_content entry
 *   - reaction_id: ID of the reaction type
 *   - content_type: Type of content (e.g., 'post')
 *   - content_id: ID of the content
 *   - giver_id: User ID who gave the reaction
 *   - receiver_id: User ID who received the reaction
 *   - reaction_date: Timestamp of the reaction
 *   - reaction_score: Point value of the reaction
 */
function yourplugin_on_reaction_added($args)
{
    global $db, $mybb;
    
    // Example: Log reaction in a custom table
    $log_data = array(
        'user_id' => (int)$args['giver_id'],
        'target_user_id' => (int)$args['receiver_id'],
        'reaction_id' => (int)$args['reaction_id'],
        'content_type' => $db->escape_string($args['content_type']),
        'content_id' => (int)$args['content_id'],
        'points' => (int)$args['reaction_score'],
        'log_date' => TIME_NOW
    );
    
    // $db->insert_query('yourplugin_reaction_logs', $log_data);
    
    // Example: Award bonus points for specific reactions
    if($args['reaction_id'] == 2) // Love reaction
    {
        // Award bonus to the giver
        // reactions_add_points($args['giver_id'], 5, 'bonus_for_love');
    }
}

/**
 * Called after a reaction is removed
 * 
 * @param array $args Array containing:
 *   - reaction_content_id: ID of the reaction_content entry (may be 0 if already deleted)
 *   - reaction_id: ID of the reaction type
 *   - content_type: Type of content
 *   - content_id: ID of the content
 *   - giver_id: User ID who removed the reaction
 *   - receiver_id: User ID who originally received the reaction
 *   - reaction_score: Point value that was removed
 */
function yourplugin_on_reaction_removed($args)
{
    global $db;
    
    // Example: Log the removal
    // Example: Reverse any bonuses awarded
}

/**
 * Called after a reaction is updated (changed from one type to another)
 * 
 * @param array $args Array containing:
 *   - reaction_content_id: ID of the reaction_content entry
 *   - old_reaction_id: Previous reaction type ID
 *   - new_reaction_id: New reaction type ID
 *   - content_type: Type of content
 *   - content_id: ID of the content
 *   - giver_id: User ID who changed the reaction
 *   - receiver_id: User ID who received the reaction
 *   - old_score: Previous point value
 *   - new_score: New point value
 *   - score_diff: Difference between new and old scores
 */
function yourplugin_on_reaction_updated($args)
{
    global $db;
    
    // Example: Adjust any calculations based on score difference
    // $score_change = $args['score_diff'];
}

/**
 * Called when reaction points are added manually (gifted)
 * 
 * @param array $args Array containing:
 *   - user_id: User receiving points
 *   - points: Number of points added
 *   - reason: Reason for points
 *   - content_type: Optional content type reference
 *   - content_id: Optional content ID reference
 */
function yourplugin_on_points_added($args)
{
    global $db;
    
    // Example: Trigger an achievement or notification
    // yourplugin_on_gifted_points($args['user_id'], $args['points']);
}


// ============================================================================
// INTEGRATION APPROACH 2: CUSTOM CONTENT TYPE SUPPORT
// ============================================================================
// Allow reactions on your custom content types (not just posts)

/**
 * Register your custom content type for reactions
 */
$plugins->add_hook('reactions_register_content_types', 'yourplugin_register_reaction_content_type');

function yourplugin_register_reaction_content_type(&$content_types)
{
    // Add your custom content type
    $content_types['yourcontent'] = array(
        'name' => 'Your Content Name',
        'can_receive_reactions' => true,
        'handler_class' => 'YourPlugin_Reaction_Content_Handler'
    );
}

/**
 * Create a handler class for your content type
 * This class tells the Reaction Plugin how to work with your content
 */
if(!class_exists('YourPlugin_Reaction_Content_Handler'))
{
    class YourPlugin_Reaction_Content_Handler
    {
        /**
         * Get the user ID of the content author
         * 
         * @param int $content_id
         * @return int User ID
         */
        public function get_content_user_id($content_id)
        {
            global $db;
            // Return the user ID who owns this content
            return (int)$db->fetch_field(
                $db->simple_select('your_content_table', 'user_id', 'id='.(int)$content_id),
                'user_id'
            );
        }
        
        /**
         * Get the URL to view the content
         * 
         * @param int $content_id
         * @return string URL
         */
        public function get_content_url($content_id)
        {
            // Return the URL to view this content
            global $mybb;
            return $mybb->settings['bburl'].'/yourcontent.php?id='.(int)$content_id;
        }
        
        /**
         * Get the title of the content
         * 
         * @param int $content_id
         * @return string Title
         */
        public function get_content_title($content_id)
        {
            global $db;
            return $db->fetch_field(
                $db->simple_select('your_content_table', 'title', 'id='.(int)$content_id),
                'title'
            );
        }
        
        /**
         * Check if content exists and reactions are allowed
         * 
         * @param int $content_id
         * @return bool
         */
        public function content_exists($content_id)
        {
            global $db;
            $exists = $db->fetch_field(
                $db->simple_select('your_content_table', 'COUNT(*) as count', 'id='.(int)$content_id),
                'count'
            );
            return (int)$exists > 0;
        }
        
        /**
         * Check if reactions are enabled for this specific content
         * 
         * @param int $content_id
         * @return bool
         */
        public function reactions_enabled_for_content($content_id)
        {
            // Return false if reactions are disabled for this specific content
            return true;
        }
        
        /**
         * Get reaction data for building the reaction bar
         * Called when displaying your content
         * 
         * @param int $content_id
         * @return array Reaction data
         */
        public function get_reaction_data($content_id)
        {
            // Return cached reaction data for this content
            // Format should match what's stored in your content table
            global $db;
            $content = $db->fetch_array(
                $db->simple_select('your_content_table', 'reactions, reaction_users, reaction_score', 'id='.(int)$content_id)
            );
            
            return array(
                'reactions' => !empty($content['reactions']) ? json_decode($content['reactions'], true) : array(),
                'reaction_users' => !empty($content['reaction_users']) ? json_decode($content['reaction_users'], true) : array(),
                'reaction_score' => (int)$content['reaction_score']
            );
        }
        
        /**
         * Update reaction cache for this content
         * Called after reactions are added/removed
         * 
         * @param int $content_id
         * @param array $reaction_data Reaction data to cache
         */
        public function update_reaction_cache($content_id, $reaction_data)
        {
            global $db;
            $update_data = array(
                'reactions' => $db->escape_string(json_encode($reaction_data['reactions'])),
                'reaction_users' => $db->escape_string(json_encode($reaction_data['reaction_users'])),
                'reaction_score' => (int)$reaction_data['reaction_score']
            );
            $db->update_query('your_content_table', $update_data, 'id='.(int)$content_id);
        }
    }
}


// ============================================================================
// INTEGRATION APPROACH 3: REACTION POINTS API
// ============================================================================
// Award or deduct reaction points programmatically

/**
 * Award reaction points to a user
 * 
 * @param int $user_id User to award points to
 * @param int $points Number of points (can be negative)
 * @param string $reason Reason for the award
 * @param string $content_type Optional content type reference
 * @param int $content_id Optional content ID reference
 * @return bool Success
 */
function yourplugin_award_reaction_points($user_id, $points, $reason = '', $content_type = '', $content_id = 0)
{
    global $db;
    
    // Check if Reaction Plugin functions exist
    if(!function_exists('reactions_add_points'))
    {
        // Fallback: manually update user's reaction score
        $db->write_query("UPDATE ".TABLE_PREFIX."users 
                          SET reaction_score = reaction_score + ".(int)$points." 
                          WHERE uid=".(int)$user_id);
        return true;
    }
    
    // Use Reaction Plugin's official function
    return reactions_add_points($user_id, $points, $reason, $content_type, $content_id);
}

/**
 * Deduct reaction points from a user
 * 
 * @param int $user_id User to deduct points from
 * @param int $points Number of points to deduct
 * @param string $reason Reason for deduction
 * @return bool Success
 */
function yourplugin_deduct_reaction_points($user_id, $points, $reason = '')
{
    return yourplugin_award_reaction_points($user_id, -(int)$points, $reason);
}


// ============================================================================
// INTEGRATION APPROACH 4: READ REACTION STATS
// ============================================================================
// Get reaction statistics for users

/**
 * Get comprehensive reaction stats for a user
 * 
 * @param int $user_id
 * @return array Stats including scores, counts, and breakdown
 */
function yourplugin_get_user_reaction_stats($user_id)
{
    global $db;
    
    $stats = array(
        'reaction_score' => 0,
        'reaction_given_score' => 0,
        'reaction_count_received' => 0,
        'reaction_count_given' => 0,
        'reaction_breakdown' => array()
    );
    
    if(!function_exists('reactions_get_user_reaction_score'))
    {
        // Fallback: query directly
        $user = $db->fetch_array($db->simple_select('users', 
            'reaction_score, reaction_given_score', 
            'uid='.(int)$user_id
        ));
        
        if($user)
        {
            $stats['reaction_score'] = (int)$user['reaction_score'];
            $stats['reaction_given_score'] = (int)$user['reaction_given_score'];
        }
    }
    else
    {
        // Use Reaction Plugin functions
        $stats['reaction_score'] = reactions_get_user_reaction_score($user_id);
        $stats['reaction_given_score'] = reactions_get_user_reaction_given_score($user_id);
        $stats['reaction_count_received'] = reactions_get_user_reaction_count($user_id);
        $stats['reaction_count_given'] = reactions_get_user_reaction_given_count($user_id);
    }
    
    // Get breakdown by reaction type
    $query = $db->query("
        SELECT r.title, r.reaction_score, COUNT(*) as count
        FROM ".TABLE_PREFIX."reaction_content rc
        INNER JOIN ".TABLE_PREFIX."reactions r ON (rc.reaction_id = r.reaction_id)
        WHERE rc.content_user_id = ".(int)$user_id." AND rc.is_counted = 1
        GROUP BY rc.reaction_id
        ORDER BY count DESC
    ");
    
    while($row = $db->fetch_array($query))
    {
        $stats['reaction_breakdown'][] = array(
            'reaction_name' => $row['title'],
            'reaction_score' => (int)$row['reaction_score'],
            'count' => (int)$row['count']
        );
    }
    
    return $stats;
}


// ============================================================================
// INTEGRATION APPROACH 5: PERMISSION HOOKS
// ============================================================================
// Modify reaction permissions dynamically

/**
 * Hook into permission checks
 */
$plugins->add_hook('reactions_can_react_check', 'yourplugin_modify_reaction_permissions');

/**
 * Modify whether a user can react
 * Return an array with 'can_react' and optional 'error' message
 * Return null to let default checks continue
 * 
 * @param array $args Contains:
 *   - user_id: User attempting to react
 *   - content_type: Content type
 *   - content_id: Content ID
 *   - content_user_id: Content author ID
 *   - can_react: Current permission status
 *   - error: Current error message
 * @return array|null Modified permission or null to continue
 */
function yourplugin_modify_reaction_permissions($args)
{
    global $mybb, $db;
    
    // Example: Prevent reacting if user has less than 10 posts in your plugin's forum
    // if($args['user_id'] && yourplugin_get_user_forum_postcount($args['user_id']) < 10)
    // {
    //     return array(
    //         'can_react' => false,
    //         'error' => 'You need at least 10 posts in our special forum to use reactions.'
    //     );
    // }
    
    // Example: Give VIP users unlimited reactions
    // $user_perms = reactions_get_user_permissions($args['user_id']);
    // if($user_perms['is_super'])
    // {
    //     return array('can_react' => true);
    // }
    
    // Return null to let default checks continue
    return null;
}


// ============================================================================
// INTEGRATION APPROACH 6: PROFILE TAB INTEGRATION
// ============================================================================
// Add custom content to the reaction profile tabs

/**
 * Hook into profile reaction history
 */
$plugins->add_hook('reactions_profile_history_start', 'yourplugin_profile_history_start');
$plugins->add_hook('reactions_profile_history_end', 'yourplugin_profile_history_end');
$plugins->add_hook('reactions_profile_stats_start', 'yourplugin_profile_stats_start');

function yourplugin_profile_history_start($uid)
{
    global $db;
    
    // Add custom stats before the reaction history
    // echo '<div class="yourplugin-custom-stats">Custom stats here</div>';
}

function yourplugin_profile_history_end($uid)
{
    // Add content after the reaction history
}

function yourplugin_profile_stats_start($uid)
{
    // Modify or add to the stats section
}

function yourplugin_profile_stats_end($uid)
{
    // Add content after the stats section
}


// ============================================================================
// INTEGRATION APPROACH 7: ADMIN INTEGRATION
// ============================================================================
// Add your own settings to the Reactions admin panel

/**
 * Hook into admin form
 */
$plugins->add_hook('admin_formcontainer_output_row', 'yourplugin_reactions_admin_form');

function yourplugin_reactions_admin_form(&$container)
{
    global $mybb, $lang;
    
    // Check if we're on the reactions settings page
    if($container['title'] == 'Reaction Settings' || 
       (isset($mybb->input['action']) && $mybb->input['action'] == 'reactions_settings'))
    {
        // Add your custom setting
        // $container['content'] .= '<tr><td colspan="2">Your custom setting here</td></tr>';
    }
}


// ============================================================================
// COMPLETE EXAMPLE: INTEGRATING A "ACHIEVEMENT SYSTEM" WITH REACTIONS
// ============================================================================

/**
 * Example: Achievement system that awards achievements based on reaction counts
 */

$plugins->add_hook('reactions_after_add', 'achievement_system_check_reactions');

function achievement_system_check_reactions($args)
{
    global $db;
    
    $receiver_id = (int)$args['receiver_id'];
    
    // Get user's total received reactions
    if(function_exists('reactions_get_user_reaction_count'))
    {
        $count = reactions_get_user_reaction_count($receiver_id);
    }
    else
    {
        $count = (int)$db->fetch_field(
            $db->query("SELECT COUNT(*) as count FROM ".TABLE_PREFIX."reaction_content 
                       WHERE content_user_id=".$receiver_id." AND is_counted=1"),
            'count'
        );
    }
    
    // Check for achievements
    $achievements = array(
        10 => 'reaction_rookie',
        50 => 'reaction_popular',
        100 => 'reaction_star',
        500 => 'reaction_legend'
    );
    
    foreach($achievements as $threshold => $achievement_code)
    {
        if($count >= $threshold)
        {
            // Award achievement (your achievement system code here)
            // award_achievement($receiver_id, $achievement_code);
        }
    }
}


// ============================================================================
// UTILITY FUNCTIONS PROVIDED BY REACTION PLUGIN
// ============================================================================
// These functions are available for use if Reaction Plugin is installed

/*
CORE FUNCTIONS:
- reactions_get_active($force_refresh = false) - Get all active reactions
- reactions_get_reaction($reaction_id) - Get a specific reaction
- reactions_get_default() - Get the default reaction
- reactions_get_reaction_score($reaction_id) - Get point value of a reaction
- reactions_get_reaction_image($reaction, $size = 'small') - Get HTML for reaction image

USER FUNCTIONS:
- reactions_get_user_reaction_score($user_id) - Get user's received score
- reactions_get_user_reaction_given_score($user_id) - Get user's given score
- reactions_get_user_reaction_count($user_id) - Get count of reactions received
- reactions_get_user_reaction_given_count($user_id) - Get count of reactions given
- reactions_get_user_permissions($user_id) - Get user's reaction permissions

CHECK FUNCTIONS:
- reactions_can_react($content_type, $content_id, $user_id, $content_user_id) - Check if user can react
- reactions_can_edit_reaction($reaction_date, $user_id = 0) - Check if reaction can be edited
- reactions_can_remove_reactions($user_id = 0) - Check if user can remove reactions
- reactions_can_use_reactions($user_id = 0) - Check if user can use reactions

ACTION FUNCTIONS:
- reactions_react_to_content($reaction_id, $content_type, $content_id, $user_id, $content_user_id) - Add/update reaction
- reactions_remove_reaction($reaction_content_id, $content_type, $content_id, $giver_id, $receiver_id) - Remove reaction

UTILITY FUNCTIONS:
- reactions_format_reactions_text($total, $users, $visitor_id) - Format reaction text
- reactions_build_reactions_bar_html($content_type, $content_id) - Build reaction bar HTML
- reactions_build_button_html($content_type, $content_id) - Build reaction button HTML
*/


// ============================================================================
// BACKWARDS COMPATIBILITY CHECK
// ============================================================================
// Always check if Reaction Plugin is available before using its functions

function yourplugin_reactions_available()
{
    // Check if Reaction Plugin functions exist
    return function_exists('reactions_get_active');
}

function yourplugin_reactions_get_version()
{
    if(!function_exists('reactions_info'))
    {
        return false;
    }
    
    $info = reactions_info();
    return isset($info['version']) ? $info['version'] : false;
}

function yourplugin_reactions_version_check($min_version)
{
    $version = yourplugin_reactions_available();
    if(!$version)
    {
        return false;
    }
    
    return version_compare(yourplugin_reactions_get_version(), $min_version, '>=');
}


// ============================================================================
// END OF DEVELOPER INTEGRATION GUIDE
// ============================================================================
?>