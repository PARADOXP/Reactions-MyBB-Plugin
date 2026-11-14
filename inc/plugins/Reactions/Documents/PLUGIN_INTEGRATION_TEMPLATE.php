<?php
/**
 * Plugin Name: Your Plugin Name
 * Plugin Author: Your Name
 * Plugin Version: 1.0
 * 
 * This is a template showing how to integrate with the Reaction Plugin.
 * Copy this file and modify it for your plugin.
 */

// If accessed directly, display the source code as plain text
if(!defined("IN_MYBB"))
{
    header('Content-Type: text/plain');
    readfile(__FILE__);
    exit;
}

// ============================================================================
// STEP 1: DEFINE PLUGIN INFORMATION
// ============================================================================

$plugins->add_hook('global_start', 'yourplugin_global_start');

function yourplugin_info()
{
    return array(
        "name"          => "Your Plugin Name",
        "description"   => "Description of your plugin. Reaction Plugin integration included.",
        "website"       => "https://yourwebsite.com",
        "author"        => "Your Name",
        "authorsite"    => "https://yourwebsite.com",
        "version"       => "1.0",
        "guid"          => "",
        "compatibility" => "18*"
    );
}

// ============================================================================
// STEP 2: CHECK IF REACTION PLUGIN IS AVAILABLE
// ============================================================================

/**
 * Check if Reaction Plugin is installed and available
 * 
 * @return bool
 */
function yourplugin_reactions_available()
{
    return function_exists('reactions_get_active');
}

/**
 * Get Reaction Plugin version
 * 
 * @return string|false
 */
function yourplugin_reactions_get_version()
{
    if(!function_exists('reactions_info'))
    {
        return false;
    }
    
    $info = reactions_info();
    return isset($info['version']) ? $info['version'] : false;
}

/**
 * Check Reaction Plugin version meets minimum requirement
 * 
 * @param string $min_version Minimum required version
 * @return bool
 */
function yourplugin_reactions_version_check($min_version = '1.0')
{
    $version = yourplugin_reactions_get_version();
    if(!$version)
    {
        return false;
    }
    
    return version_compare($version, $min_version, '>=');
}

// ============================================================================
// STEP 3: REGISTER HOOKS (if Reaction Plugin is available)
// ============================================================================

function yourplugin_global_start()
{
    global $plugins;
    
    // Only register hooks if Reaction Plugin is available
    if(!yourplugin_reactions_available())
    {
        return;
    }
    
    // Register reaction event hooks
    $plugins->add_hook('reactions_after_add', 'yourplugin_on_reaction_added');
    $plugins->add_hook('reactions_after_remove', 'yourplugin_on_reaction_removed');
    $plugins->add_hook('reactions_after_update', 'yourplugin_on_reaction_updated');
    $plugins->add_hook('reactions_points_added', 'yourplugin_on_points_added');
    
    // Register permission hook (optional)
    // $plugins->add_hook('reactions_can_react_check', 'yourplugin_check_permissions');
    
    // Register profile hooks (optional)
    // $plugins->add_hook('reactions_profile_stats_start', 'yourplugin_profile_stats_start');
    // $plugins->add_hook('reactions_profile_stats_end', 'yourplugin_profile_stats_end');
    // $plugins->add_hook('reactions_profile_history_start', 'yourplugin_profile_history_start');
    // $plugins->add_hook('reactions_profile_history_end', 'yourplugin_profile_history_end');
}

// ============================================================================
// STEP 4: IMPLEMENT HOOK CALLBACKS
// ============================================================================

/**
 * Called when a reaction is added
 * 
 * @param array $args Event arguments
 */
function yourplugin_on_reaction_added($args)
{
    // Extract data from args
    $reaction_content_id = $args['reaction_content_id'];
    $reaction_id = $args['reaction_id'];
    $content_type = $args['content_type'];
    $content_id = $args['content_id'];
    $giver_id = $args['giver_id'];
    $receiver_id = $args['receiver_id'];
    $reaction_date = $args['reaction_date'];
    $reaction_score = $args['reaction_score'];
    
    // ========================================
    // EXAMPLE 1: Log the reaction
    // ========================================
    // yourplugin_log_reaction('add', $args);
    
    // ========================================
    // EXAMPLE 2: Award bonus for specific reactions
    // ========================================
    // if($reaction_id == 2) // Love reaction
    // {
    //     yourplugin_award_bonus($receiver_id, 5, 'Love reaction bonus');
    // }
    
    // ========================================
    // EXAMPLE 3: Check for achievements
    // ========================================
    // yourplugin_check_achievements($receiver_id);
    
    // ========================================
    // EXAMPLE 4: Send custom notifications
    // ========================================
    // if($reaction_score < 0) // Negative reaction
    // {
    //     yourplugin_notify_negative_reaction($receiver_id, $giver_id, $reaction_id);
    // }
}

/**
 * Called when a reaction is removed
 * 
 * @param array $args Event arguments
 */
function yourplugin_on_reaction_removed($args)
{
    // Reverse any actions taken when the reaction was added
    
    $reaction_content_id = $args['reaction_content_id'];
    $reaction_id = $args['reaction_id'];
    $content_type = $args['content_type'];
    $content_id = $args['content_id'];
    $giver_id = $args['giver_id'];
    $receiver_id = $args['receiver_id'];
    $reaction_score = $args['reaction_score'];
    
    // Example: Remove any bonuses that were awarded
    // yourplugin_remove_bonus($receiver_id, $reaction_score);
}

/**
 * Called when a reaction is updated (changed from one type to another)
 * 
 * @param array $args Event arguments
 */
function yourplugin_on_reaction_updated($args)
{
    $reaction_content_id = $args['reaction_content_id'];
    $old_reaction_id = $args['old_reaction_id'];
    $new_reaction_id = $args['new_reaction_id'];
    $old_score = $args['old_score'];
    $new_score = $args['new_score'];
    $score_diff = $args['score_diff'];
    
    // Example: Adjust calculations based on score difference
    // if($score_diff != 0)
    // {
    //     yourplugin_adjust_points($args['receiver_id'], $score_diff);
    // }
}

/**
 * Called when reaction points are added manually (gifted)
 * 
 * @param array $args Event arguments
 */
function yourplugin_on_points_added($args)
{
    $user_id = $args['user_id'];
    $points = $args['points'];
    $reason = $args['reason'];
    $content_type = $args['content_type'];
    $content_id = $args['content_id'];
    
    // Example: Award secondary currency based on gifted points
    // yourplugin_award_currency($user_id, $points * 10);
}

/**
 * Called before profile stats are displayed
 * 
 * @param int $uid Profile user ID
 */
function yourplugin_profile_stats_start($uid)
{
    // Add content before profile stats
}

/**
 * Called after profile stats are displayed
 * 
 * @param int $uid Profile user ID
 */
function yourplugin_profile_stats_end($uid)
{
    // Add content after profile stats
}

/**
 * Called before profile reaction history is displayed
 * 
 * @param int $uid Profile user ID
 */
function yourplugin_profile_history_start($uid)
{
    // Add content before profile history
}

/**
 * Called after profile reaction history is displayed
 * 
 * @param int $uid Profile user ID
 */
function yourplugin_profile_history_end($uid)
{
    // Add content after profile history
}

// ============================================================================
// STEP 5: HELPER FUNCTIONS (Customize these for your plugin)
// ============================================================================

/**
 * Get user's reaction statistics
 * 
 * @param int $user_id
 * @return array
 */
function yourplugin_get_user_reaction_stats($user_id)
{
    global $db;
    
    $stats = array(
        'reaction_score' => 0,
        'reaction_given_score' => 0,
        'reaction_count_received' => 0,
        'reaction_count_given' => 0
    );
    
    // Use Reaction Plugin functions if available
    if(function_exists('reactions_get_user_reaction_score'))
    {
        $stats['reaction_score'] = reactions_get_user_reaction_score($user_id);
        $stats['reaction_given_score'] = reactions_get_user_reaction_given_score($user_id);
        $stats['reaction_count_received'] = reactions_get_user_reaction_count($user_id);
        $stats['reaction_count_given'] = reactions_get_user_reaction_given_count($user_id);
    }
    else
    {
        // Fallback: Query directly
        $user = $db->fetch_array($db->simple_select('users', 
            'reaction_score, reaction_given_score', 
            'uid='.(int)$user_id
        ));
        
        if($user)
        {
            $stats['reaction_score'] = (int)$user['reaction_score'];
            $stats['reaction_given_score'] = (int)$user['reaction_given_score'];
        }
        
        // Get counts from reaction_content table
        $stats['reaction_count_received'] = (int)$db->fetch_field(
            $db->query("SELECT COUNT(*) as count FROM ".TABLE_PREFIX."reaction_content 
                      WHERE content_user_id=".(int)$user_id." AND is_counted=1"),
            'count'
        );
        
        $stats['reaction_count_given'] = (int)$db->fetch_field(
            $db->query("SELECT COUNT(*) as count FROM ".TABLE_PREFIX."reaction_content 
                      WHERE reaction_user_id=".(int)$user_id." AND is_counted=1"),
            'count'
        );
    }
    
    return $stats;
}

/**
 * Get reaction breakdown by type for a user
 * 
 * @param int $user_id
 * @return array
 */
function yourplugin_get_reaction_breakdown($user_id)
{
    global $db;
    
    $breakdown = array();
    
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
        $breakdown[] = array(
            'reaction_name' => $row['title'],
            'reaction_score' => (int)$row['reaction_score'],
            'count' => (int)$row['count']
        );
    }
    
    return $breakdown;
}

// ============================================================================
// STEP 6: SAMPLE INTEGRATION FEATURES (Uncomment and customize as needed)
// ============================================================================

/**
 * SAMPLE: Check reaction achievements
 * 
 * @param int $user_id
 */
// function yourplugin_check_achievements($user_id)
// {
//     global $db;
//     
//     // Get user's reaction count
//     if(function_exists('reactions_get_user_reaction_count'))
//     {
//         $count = reactions_get_user_reaction_count($user_id);
//     }
//     else
//     {
//         $count = (int)$db->fetch_field(
//             $db->query("SELECT COUNT(*) as count FROM ".TABLE_PREFIX."reaction_content 
//                       WHERE content_user_id=".(int)$user_id." AND is_counted=1"),
//             'count'
//         );
//     }
//     
//     // Define achievements
//     $achievements = array(
//         10 => array('badge' => 'reaction_rookie', 'name' => 'Reaction Rookie'),
//         50 => array('badge' => 'reaction_popular', 'name' => 'Popular Poster'),
//         100 => array('badge' => 'reaction_star', 'name' => 'Rising Star'),
//         500 => array('badge' => 'reaction_legend', 'name' => 'Legend')
//     );
//     
//     foreach($achievements as $threshold => $achievement)
//     {
//         if($count >= $threshold)
//         {
//             // Check if user already has this achievement
//             // if(!yourplugin_user_has_achievement($user_id, $achievement['badge']))
//             // {
//             //     yourplugin_award_achievement($user_id, $achievement['badge'], $achievement['name']);
//             // }
//         }
//     }
// }

/**
 * SAMPLE: Award bonus points
 * 
 * @param int $user_id
 * @param int $points
 * @param string $reason
 */
// function yourplugin_award_bonus($user_id, $points, $reason = '')
// {
//     global $db;
//     
//     // Update user's reaction score
//     $db->write_query("
//         UPDATE ".TABLE_PREFIX."users 
//         SET reaction_score = reaction_score + ".(int)$points." 
//         WHERE uid=".(int)$user_id
//     );
//     
//     // Log the bonus
//     // yourplugin_log_bonus($user_id, $points, $reason);
// }

/**
 * SAMPLE: Log reaction activity
 * 
 * @param string $action 'add' or 'remove'
 * @param array $args Event arguments
 */
// function yourplugin_log_reaction($action, $args)
// {
//     global $db;
//     
//     $log_data = array(
//         'action' => $db->escape_string($action),
//         'user_id' => (int)$args['giver_id'],
//         'target_user_id' => (int)$args['receiver_id'],
//         'reaction_id' => (int)$args['reaction_id'],
//         'content_type' => $db->escape_string($args['content_type']),
//         'content_id' => (int)$args['content_id'],
//         'points' => (int)$args['reaction_score'],
//         'log_date' => TIME_NOW
//     );
//     
//     // Create this table in your plugin's install function
//     // $db->insert_query('yourplugin_reaction_logs', $log_data);
// }

/**
 * SAMPLE: Custom permission check
 * Modify whether a user can react
 * 
 * @param array $args Permission check arguments
 */
// function yourplugin_check_permissions(&$args)
// {
//     // Example: Require minimum posts to react
//     if($args['user_id'] > 0 && $args['can_react'])
//     {
//         global $db;
//         $post_count = $db->fetch_field(
//             $db->simple_select('users', 'postnum', 'uid='.(int)$args['user_id']),
//             'postnum'
//         );
//         
//         if($post_count < 10)
//         {
//             $args['can_react'] = false;
//             $args['error'] = 'You need at least 10 posts to use reactions.';
//         }
//     }
// }

// ============================================================================
// INSTALL/UNINSTALL FUNCTIONS
// ============================================================================

function yourplugin_is_installed()
{
    global $db;
    // Check if your tables/settings exist
    // return $db->table_exists('yourplugin_table');
    return false; // Change this
}

function yourplugin_install()
{
    global $db, $PL;
    
    // Load PluginLibrary if available
    if(!defined('PLUGINLIBRARY'))
    {
        define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');
    }
    
    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
    }
    
    // Create your tables here
    // $db->write_query("CREATE TABLE IF NOT EXISTS ...");
    
    // Add settings
    // Add templates
}

function yourplugin_uninstall()
{
    global $db, $PL;
    
    // Load PluginLibrary if available
    if(!defined('PLUGINLIBRARY'))
    {
        define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');
    }
    
    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
    }
    
    // Drop your tables
    // $db->drop_table('yourplugin_table');
    
    // Delete settings
    // Delete templates
}

function yourplugin_activate()
{
    // Code to run when plugin is activated
}

function yourplugin_deactivate()
{
    // Code to run when plugin is deactivated
}

// ============================================================================
// END OF TEMPLATE
// ============================================================================

/**
 * Remember to:
 * 
 * 1. Replace 'yourplugin' with your actual plugin prefix
 * 2. Implement the actual functionality in the helper functions
 * 3. Create any necessary database tables in your install function
 * 4. Test thoroughly with Reaction Plugin enabled and disabled
 * 5. Handle edge cases (deleted users, missing content, etc.)
 * 
 * For more examples and documentation, see:
 * - inc/plugins/Reactions/Documents/README_FOR_DEVELOPERS.md
 * - inc/plugins/Reactions/Documents/DEVELOPER_INTEGRATION_GUIDE.php
 */
?>