# Reaction Plugin - Developer Integration Guide

This guide explains how to integrate your plugin with the Reaction Plugin.

## Table of Contents

1. [Quick Start](#quick-start)
2. [Available Hooks](#available-hooks)
3. [Common Integration Patterns](#common-integration-patterns)
4. [Helper Functions](#helper-functions)
5. [Complete Examples](#complete-examples)

---

## Quick Start

Add this to your plugin's main file to detect the Reaction Plugin:

```php
// Check if Reaction Plugin is available
function yourplugin_reactions_available()
{
    return function_exists('reactions_get_active');
}

// Get Reaction Plugin version
function yourplugin_reactions_get_version()
{
    if(!function_exists('reactions_info'))
    {
        return false;
    }
    
    $info = reactions_info();
    return isset($info['version']) ? $info['version'] : false;
}
```

---

## Available Hooks

### 1. Reaction Event Hooks

#### `reactions_after_add`
Fired when a reaction is added.

```php
$plugins->add_hook('reactions_after_add', 'yourplugin_on_reaction_added');

function yourplugin_on_reaction_added($args)
{
    // $args contains:
    // - reaction_content_id: ID of the reaction entry
    // - reaction_id: ID of the reaction type (1=Like, 2=Love, etc.)
    // - content_type: Type of content ('post')
    // - content_id: ID of the content
    // - giver_id: User who gave the reaction
    // - receiver_id: User who received the reaction
    // - reaction_date: Timestamp
    // - reaction_score: Point value
    
    $giver_id = $args['giver_id'];
    $receiver_id = $args['receiver_id'];
    $reaction_id = $args['reaction_id'];
    $points = $args['reaction_score'];
    
    // Your code here
}
```

#### `reactions_after_remove`
Fired when a reaction is removed.

```php
$plugins->add_hook('reactions_after_remove', 'yourplugin_on_reaction_removed');

function yourplugin_on_reaction_removed($args)
{
    // $args contains similar data as reactions_after_add
    // Use this to reverse any actions taken when the reaction was added
}
```

#### `reactions_after_update`
Fired when a reaction is changed from one type to another.

```php
$plugins->add_hook('reactions_after_update', 'yourplugin_on_reaction_updated');

function yourplugin_on_reaction_updated($args)
{
    // $args contains:
    // - old_reaction_id: Previous reaction type
    // - new_reaction_id: New reaction type
    // - old_score: Previous point value
    // - new_score: New point value
    // - score_diff: Difference between new and old
}
```

#### `reactions_points_added`
Fired when reaction points are added manually or via API.

```php
$plugins->add_hook('reactions_points_added', 'yourplugin_on_points_added');

function yourplugin_on_points_added($args)
{
    // $args contains:
    // - user_id: User receiving points
    // - points: Number of points added
    // - reason: Reason for points
    // - content_type: Optional content type
    // - content_id: Optional content ID
}
```

---

### 2. Permission Hook

#### `reactions_can_react_check`
Modify whether a user can react.

```php
$plugins->add_hook('reactions_can_react_check', 'yourplugin_check_reaction_permissions');

function yourplugin_check_reaction_permissions(&$args)
{
    // $args contains:
    // - user_id: User attempting to react
    // - content_type: Type of content
    // - content_id: Content ID
    // - content_user_id: Content author
    // - can_react: Current permission (true/false)
    // - error: Current error message
    
    // Example: Prevent new users from reacting
    if($args['user_id'] > 0)
    {
        global $db;
        $post_count = $db->fetch_field(
            $db->simple_select('users', 'postnum', 'uid='.(int)$args['user_id']),
            'postnum'
        );
        
        if($post_count < 10)
        {
            $args['can_react'] = false;
            $args['error'] = 'You need at least 10 posts to use reactions.';
        }
    }
}
```

---

### 3. Profile Page Hooks

#### `reactions_profile_stats_start` / `reactions_profile_stats_end`
Add content to the reaction stats section on profile pages.

```php
$plugins->add_hook('reactions_profile_stats_start', 'yourplugin_profile_stats');

function yourplugin_profile_stats($uid)
{
    global $reactions_profile_stats;
    
    // Add custom HTML before/after reaction stats
    $custom_content = '<div class="custom-stat">Your custom content here</div>';
    $reactions_profile_stats = $custom_content . $reactions_profile_stats;
}
```

---

## Common Integration Patterns

### Pattern 1: Award Bonus Points

Give users bonus points for receiving certain reactions:

```php
$plugins->add_hook('reactions_after_add', 'award_bonus_points');

function award_bonus_points($args)
{
    // Award 5 bonus points for "Love" reaction (ID = 2)
    if($args['reaction_id'] == 2 && $args['receiver_id'] > 0)
    {
        // Use your points system here
        // award_points($args['receiver_id'], 5, 'Love reaction bonus');
    }
}
```

### Pattern 2: Achievement System

Track reactions for achievements:

```php
$plugins->add_hook('reactions_after_add', 'check_reaction_achievements');

function check_reaction_achievements($args)
{
    global $db;
    
    // Count user's received reactions
    $count = $db->fetch_field(
        $db->query("SELECT COUNT(*) as count 
                   FROM ".TABLE_PREFIX."reaction_content 
                   WHERE content_user_id=".(int)$args['receiver_id']." AND is_counted=1"),
        'count'
    );
    
    // Check for achievements
    if($count >= 100)
    {
        // award_achievement($args['receiver_id'], 'reaction_star');
    }
}
```

### Pattern 3: Notification System

Send custom notifications:

```php
$plugins->add_hook('reactions_after_add', 'send_custom_notification');

function send_custom_notification($args)
{
    // Only notify for negative reactions
    if($args['reaction_score'] < 0 && $args['receiver_id'] > 0)
    {
        // Send notification about negative reaction
        // send_pm_or_alert($args['receiver_id'], 'You received a negative reaction');
    }
}
```

### Pattern 4: Activity Logging

Log all reaction activity:

```php
$plugins->add_hook('reactions_after_add', 'log_reaction_activity');
$plugins->add_hook('reactions_after_remove', 'log_reaction_removal');

function log_reaction_activity($args)
{
    global $db;
    
    $log_data = array(
        'action' => 'add',
        'user_id' => (int)$args['giver_id'],
        'target_user_id' => (int)$args['receiver_id'],
        'reaction_id' => (int)$args['reaction_id'],
        'content_type' => $db->escape_string($args['content_type']),
        'content_id' => (int)$args['content_id'],
        'points' => (int)$args['reaction_score'],
        'log_date' => TIME_NOW
    );
    
    // $db->insert_query('yourplugin_reaction_logs', $log_data);
}

function log_reaction_removal($args)
{
    // Similar logging for removals
}
```

---

## Helper Functions

### User Stats Functions

```php
// Get user's reaction score (received points)
$score = reactions_get_user_reaction_score($user_id);

// Get user's given reaction score
$given_score = reactions_get_user_reaction_given_score($user_id);

// Get count of reactions received
$received_count = reactions_get_user_reaction_count($user_id);

// Get count of reactions given
$given_count = reactions_get_user_reaction_given_count($user_id);

// Get all user stats at once
function get_user_reaction_stats($user_id)
{
    return array(
        'score' => reactions_get_user_reaction_score($user_id),
        'given_score' => reactions_get_user_reaction_given_score($user_id),
        'received_count' => reactions_get_user_reaction_count($user_id),
        'given_count' => reactions_get_user_reaction_given_count($user_id)
    );
}
```

### Check Functions

```php
// Check if user can react
$can_react = reactions_can_react('post', $post_id, $user_id, $author_id);
// Returns: array('can_react' => true/false, 'error' => 'error message')

// Check if user can remove reactions
$can_remove = reactions_can_remove_reactions($user_id);

// Check if user can use reactions
$can_use = reactions_can_use_reactions($user_id);

// Get user permissions
$perms = reactions_get_user_permissions($user_id);
// Returns: can_give_reactions, can_view_reactions, can_receive_reactions, 
//          can_remove_reactions, reaction_daily_limit, is_super
```

### Reaction Data Functions

```php
// Get all active reactions
$reactions = reactions_get_active();

// Get specific reaction
$reaction = reactions_get_reaction($reaction_id);

// Get default reaction (Like)
$default = reactions_get_default();

// Get reaction score value
$points = reactions_get_reaction_score($reaction_id);

// Get reaction image HTML
$image_html = reactions_get_reaction_image($reaction, 'small'); // sizes: small, medium
```

---

## Complete Examples

### Example 1: Reaction Points Leaderboard

```php
// Add a new page showing reaction points leaderboard
$plugins->add_hook('misc_start', 'reactions_leaderboard_page');

function reactions_leaderboard_page()
{
    global $mybb, $db, $templates, $theme, $lang;
    
    if($mybb->input['action'] != 'reaction_leaderboard')
    {
        return;
    }
    
    // Get top users by reaction score
    $query = $db->query("
        SELECT u.uid, u.username, u.usergroup, u.displaygroup, u.reaction_score
        FROM ".TABLE_PREFIX."users u
        WHERE u.reaction_score > 0
        ORDER BY u.reaction_score DESC
        LIMIT 50
    ");
    
    $rows = '';
    $rank = 1;
    while($user = $db->fetch_array($query))
    {
        $username = build_profile_link(
            format_name($user['username'], $user['usergroup'], $user['displaygroup']),
            $user['uid']
        );
        $score = (int)$user['reaction_score'];
        
        $rows .= "<tr><td>#{$rank}</td><td>{$username}</td><td>{$score}</td></tr>";
        $rank++;
    }
    
    // Output page
    eval("\$page = \"".$templates->get('yourplugin_leaderboard')."\";");
    output_page($page);
}
```

### Example 2: Reaction-Based User Groups

Automatically promote users based on reaction score:

```php
$plugins->add_hook('reactions_after_add', 'auto_promote_users');

function auto_promote_users($args)
{
    global $db;
    
    $user_id = $args['receiver_id'];
    
    // Get user's reaction score
    $score = reactions_get_user_reaction_score($user_id);
    
    // Check if user qualifies for promotion
    if($score >= 100)
    {
        // Check current usergroup
        $user = $db->fetch_array($db->simple_select('users', 'usergroup', 'uid='.$user_id));
        
        // Promote to special group if in regular member group
        if($user['usergroup'] == 2) // Regular members
        {
            $db->update_query('users', array('usergroup' => 10), 'uid='.$user_id); // VIP group
            
            // Send PM about promotion
            // send_pm($user_id, 'Congratulations! You have been promoted to VIP...');
        }
    }
}
```

### Example 3: Daily Reaction Summary

Send daily summary of reactions received:

```php
// Add task hook
$plugins->add_hook('task_hourlycleanup', 'send_daily_reaction_summary');

function send_daily_reaction_summary($task)
{
    global $db;
    
    // Only run once per day (at midnight)
    if(date('H') != '00')
    {
        return;
    }
    
    $yesterday = TIME_NOW - 86400;
    
    // Get users who received reactions yesterday
    $query = $db->query("
        SELECT rc.content_user_id, COUNT(*) as count, u.username, u.email
        FROM ".TABLE_PREFIX."reaction_content rc
        INNER JOIN ".TABLE_PREFIX."users u ON (rc.content_user_id = u.uid)
        WHERE rc.reaction_date > {$yesterday}
        AND rc.content_user_id > 0
        GROUP BY rc.content_user_id
    ");
    
    while($row = $db->fetch_array($query))
    {
        $user_id = $row['content_user_id'];
        $count = $row['count'];
        
        // Send email notification
        $message = "Hello {$row['username']},\n\n";
        $message .= "You received {$count} new reactions yesterday!\n\n";
        $message .= "View your profile: " . $mybb->settings['bburl'] . "/member.php?action=profile&uid={$user_id}";
        
        // my_mail($row['email'], 'Your Daily Reaction Summary', $message);
    }
}
```

---

## Database Tables

### reaction_content
Stores all reaction records.

| Column | Type | Description |
|--------|------|-------------|
| reaction_content_id | int | Primary key |
| reaction_id | int | Reaction type ID |
| content_type | varchar | Content type ('post') |
| content_id | int | Content ID |
| reaction_user_id | int | User who gave reaction |
| reaction_date | int | Unix timestamp |
| content_user_id | int | User who received reaction |
| is_counted | tinyint | Whether reaction counts |

### reactions
Stores reaction types (Like, Love, Haha, Wow, Sad, Angry, Dislike).

| Column | Type | Description |
|--------|------|-------------|
| reaction_id | int | Primary key |
| title | varchar | Reaction name |
| image_url | varchar | Image path |
| reaction_score | int | Point value |
| reaction_type | varchar | positive/neutral/negative |
| display_order | int | Display order |
| active | tinyint | Enabled/disabled |

### User Columns Added
- `reaction_score` - Total reaction points received
- `reaction_given_score` - Total reaction points given
- `reactions_notify_prefs` - Notification preferences

---

## Tips & Best Practices

1. **Always check if Reaction Plugin is installed** before using its functions
2. **Use the hooks** rather than modifying core files
3. **Handle missing data gracefully** - users or content may be deleted
4. **Consider performance** - don't run heavy queries on every reaction
5. **Respect user privacy** - don't expose reaction data inappropriately
6. **Test with different usergroups** - permissions vary by group
7. **Handle guest users** - check for user_id == 0

---

## Support

For questions or issues with integration:
1. Check the full integration guide: `inc/plugins/Reactions/Documents/DEVELOPER_INTEGRATION_GUIDE.php`
2. Review the hook definitions: `inc/plugins/Reactions/Functions/integration_hooks.php`
3. Look at existing integrations: `inc/plugins/Reactions/Functions/myalerts_functions.php`

---

**Version**: 1.3.2  
**Last Updated**: 2026-06-09  
**Compatible With**: Reaction Plugin 1.3.x
