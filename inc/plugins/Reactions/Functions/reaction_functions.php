<?php
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}


/**
 * Create database tables - Uses database.sql
 */
function reactions_create_tables()
{
    global $db;

    // Load and execute schema from database.sql
    $schemaFile = MYBB_ROOT.'inc/plugins/Reactions/database.sql';
    if(file_exists($schemaFile))
    {
        $sql = @file_get_contents($schemaFile);
        if($sql !== false)
        {
            $sql = str_replace('{TABLE_PREFIX}', $db->table_prefix, $sql);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach($statements as $statement)
            {
                if($statement !== '') {
                    $db->write_query($statement);
                }
            }
        }
    }

    // Add missing columns for scoring system
    reactions_add_scoring_columns();

    // Add notification preference columns
    reactions_add_notification_columns();
}

/**
 * Add notification preference columns to users table
 */
function reactions_add_notification_columns()
{
    global $db;

    // Notification preferences - stored as bitmask in a single integer column
    // Each bit represents a notification type (1 = enabled, 0 = disabled)
    if(!$db->field_exists("reactions_notify_prefs", "users"))
    {
        // Default: all notifications enabled (value 65535 = all 16 bits set)
        $db->add_column("users", "reactions_notify_prefs", "INT(11) NOT NULL DEFAULT 65535");
    }
}

/**
 * Get user's notification preferences
 * Returns array with notification types as keys and boolean values
 */
function reactions_get_user_notification_prefs($user_id = null)
{
    global $mybb, $db;

    if($user_id === null)
    {
        $user_id = $mybb->user['uid'];
    }

    // Default: all notifications enabled (removed: guests_not_allowed, reaction_error, ajax_error, edit_time_expired)
    $default_prefs = array(
        'reactions_disabled' => 1,        // Reactions are currently disabled
        'no_permission_use' => 1,         // You do not have permission to use reactions
        'no_permission_remove' => 1,      // You do not have permission to remove reactions
        'disabled_in_forum' => 1,         // Reactions are disabled in this forum
        'thread_closed' => 1,             // You cannot react in a closed thread
        'usergroup_no_give' => 1,         // Your usergroup does not have permission to give reactions
        'recipient_no_receive' => 1,      // The recipient cannot receive reactions
        'self_reaction_not_allowed' => 1, // You cannot react to your own content
        'min_posts_required' => 1,        // You need at least X posts to react
        'min_reputation_required' => 1,   // You need at least X reputation to react
        'daily_limit_reached' => 1,       // You have reached your daily limit
        'flood_control' => 1,             // Please wait X seconds before reacting again
        'reaction_success' => 1,          // Reaction added/removed successfully
    );

    if($user_id == 0)
    {
        return $default_prefs;
    }

    // Get stored preference value
    $query = $db->simple_select("users", "reactions_notify_prefs", "uid=".(int)$user_id);
    $prefs_value = $db->fetch_field($query, "reactions_notify_prefs");

    if($prefs_value === false || $prefs_value === null)
    {
        return $default_prefs;
    }

    $prefs_value = (int)$prefs_value;

    // Map bits to notification types (removed: guests_not_allowed, reaction_error, ajax_error, edit_time_expired)
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

    $prefs = array();
    foreach($bit_map as $type => $bit)
    {
        $prefs[$type] = ($prefs_value & $bit) ? 1 : 0;
    }

    return $prefs;
}

/**
 * Check if a specific notification type is enabled for the user
 */
function reactions_notification_enabled($type, $user_id = null)
{
    $prefs = reactions_get_user_notification_prefs($user_id);
    return isset($prefs[$type]) ? $prefs[$type] : true;
}

/**
 * Add scoring columns to existing MyBB tables
 * Note: Columns for the 'reactions' table are already in database.sql
 * This function only handles columns added to existing MyBB core tables
 */
function reactions_add_scoring_columns()
{
    global $db;

    // Add reaction_score column to users table
    if(!$db->field_exists("reaction_score", "users"))
    {
        $db->add_column("users", "reaction_score", "INT(11) NOT NULL DEFAULT 0");
    }

    // Add reaction_given_score column to users table
    if(!$db->field_exists("reaction_given_score", "users"))
    {
        $db->add_column("users", "reaction_given_score", "INT(11) NOT NULL DEFAULT 0");
    }

    // Add reaction_daily_limit column to usergroups table
    if(!$db->field_exists("reaction_daily_limit", "usergroups"))
    {
        $db->add_column("usergroups", "reaction_daily_limit", "INT(11) NOT NULL DEFAULT 0");
    }

    if(!$db->field_exists("can_give_reactions", "usergroups"))
    {
        $db->add_column("usergroups", "can_give_reactions", "INT(1) NOT NULL DEFAULT 1");
    }

    if(!$db->field_exists("can_view_reactions", "usergroups"))
    {
        $db->add_column("usergroups", "can_view_reactions", "INT(1) NOT NULL DEFAULT 1");
    }

    if(!$db->field_exists("can_receive_reactions", "usergroups"))
    {
        $db->add_column("usergroups", "can_receive_reactions", "INT(1) NOT NULL DEFAULT 1");
    }

    // Add columns to posts table - BATCHED for performance
    $posts_cols_to_add = array();
    if(!$db->field_exists("reaction_score", "posts"))
    {
        $posts_cols_to_add[] = "ADD `reaction_score` INT(11) NOT NULL DEFAULT 0";
    }
    if(!$db->field_exists("reactions", "posts"))
    {
        $posts_cols_to_add[] = "ADD `reactions` TEXT NULL";
    }
    if(!$db->field_exists("reaction_users", "posts"))
    {
        $posts_cols_to_add[] = "ADD `reaction_users` TEXT NULL";
    }

    if(!empty($posts_cols_to_add))
    {
        $db->write_query("ALTER TABLE `".$db->table_prefix."posts` " . implode(", ", $posts_cols_to_add));
    }
}

/**
 * Drop database tables
 */
function reactions_drop_tables()
{
    global $db, $cache;
    
    $db->drop_table("reactions");
    $db->drop_table("reaction_content");
    $db->drop_table("reaction_reputation");
    
    // Clear cache
    if(isset($cache))
    {
        $cache->delete("reactions_active");
    }
    
    // Remove columns from posts table - BATCHED for performance
    $posts_cols_to_drop = array();
    if($db->field_exists("reaction_score", "posts"))
    {
        $posts_cols_to_drop[] = "DROP COLUMN `reaction_score`";
    }
    if($db->field_exists("reactions", "posts"))
    {
        $posts_cols_to_drop[] = "DROP COLUMN `reactions`";
    }
    if($db->field_exists("reaction_users", "posts"))
    {
        $posts_cols_to_drop[] = "DROP COLUMN `reaction_users`";
    }

    if(!empty($posts_cols_to_drop))
    {
        $db->write_query("ALTER TABLE `".$db->table_prefix."posts` " . implode(", ", $posts_cols_to_drop));
    }
    
    // Remove columns from users table
    if($db->field_exists("reaction_score", "users"))
    {
        $db->write_query("ALTER TABLE `".$db->table_prefix."users` DROP COLUMN `reaction_score` ");
    }
    if($db->field_exists("reaction_given_score", "users"))
    {
        $db->write_query("ALTER TABLE `".$db->table_prefix."users` DROP COLUMN `reaction_given_score` ");
    }
    if($db->field_exists("reactions_notify_prefs", "users"))
    {
        $db->write_query("ALTER TABLE `".$db->table_prefix."users` DROP COLUMN `reactions_notify_prefs` ");
    }

    // Remove columns from usergroups table
    $usergroup_cols = array("reaction_daily_limit", "can_give_reactions", "can_view_reactions", "can_receive_reactions");
    foreach($usergroup_cols as $col)
    {
        if($db->field_exists($col, "usergroups"))
        {
            $db->write_query("ALTER TABLE `".$db->table_prefix."usergroups` DROP COLUMN `".$col."` ");
        }
    }
}

/**
 * Insert default reactions
 */
function reactions_insert_default_reactions()
{
    global $db;

    $default_reactions = array(
        array(
            'title' => 'Like',
            'image_url' => 'images/reactions/like.png',
            'image_url_2x' => 'images/reactions/like_2x.png',
            'sprite_mode' => 0,
            'sprite_params' => json_encode(array('w' => 32, 'h' => 32, 'x' => 0, 'y' => 0, 'bs' => 'cover')),
            'text_color' => '#0084FF',
            'reaction_score' => 1,
            'reaction_type' => 'positive',
            'display_order' => 100,
            'active' => 1
        ),
        array(
            'title' => 'Love',
            'image_url' => 'images/reactions/love.png',
            'image_url_2x' => 'images/reactions/love_2x.png',
            'sprite_mode' => 0,
            'sprite_params' => json_encode(array('w' => 32, 'h' => 32, 'x' => 0, 'y' => 0, 'bs' => 'cover')),
            'text_color' => '#E81C27',
            'reaction_score' => 2,
            'reaction_type' => 'positive',
            'display_order' => 200,
            'active' => 1
        ),
        array(
            'title' => 'Haha',
            'image_url' => 'images/reactions/haha.png',
            'image_url_2x' => 'images/reactions/haha_2x.png',
            'sprite_mode' => 0,
            'sprite_params' => json_encode(array('w' => 32, 'h' => 32, 'x' => 0, 'y' => 0, 'bs' => 'cover')),
            'text_color' => '#FDCA47',
            'reaction_score' => 1,
            'reaction_type' => 'positive',
            'display_order' => 300,
            'active' => 1
        ),
        array(
            'title' => 'Wow',
            'image_url' => 'images/reactions/wow.png',
            'image_url_2x' => 'images/reactions/wow_2x.png',
            'sprite_mode' => 0,
            'sprite_params' => json_encode(array('w' => 32, 'h' => 32, 'x' => 0, 'y' => 0, 'bs' => 'cover')),
            'text_color' => '#FDCA47',
            'reaction_score' => 0,
            'reaction_type' => 'neutral',
            'display_order' => 400,
            'active' => 1
        ),
        array(
            'title' => 'Sad',
            'image_url' => 'images/reactions/sad.png',
            'image_url_2x' => 'images/reactions/sad_2x.png',
            'sprite_mode' => 0,
            'sprite_params' => json_encode(array('w' => 32, 'h' => 32, 'x' => 0, 'y' => 0, 'bs' => 'cover')),
            'text_color' => '#FDCA47',
            'reaction_score' => 0,
            'reaction_type' => 'neutral',
            'display_order' => 500,
            'active' => 1
        ),
        array(
            'title' => 'Angry',
            'image_url' => 'images/reactions/angry.png',
            'image_url_2x' => 'images/reactions/angry_2x.png',
            'sprite_mode' => 0,
            'sprite_params' => json_encode(array('w' => 32, 'h' => 32, 'x' => 0, 'y' => 0, 'bs' => 'cover')),
            'text_color' => '#FF4D4D',
            'reaction_score' => -1,
            'reaction_type' => 'negative',
            'display_order' => 600,
            'active' => 1
        ),
        array(
            'title' => 'Dislike',
            'image_url' => 'images/reactions/dislike.png',
            'image_url_2x' => 'images/reactions/dislike_2x.png',
            'sprite_mode' => 0,
            'sprite_params' => json_encode(array('w' => 32, 'h' => 32, 'x' => 0, 'y' => 0, 'bs' => 'cover')),
            'text_color' => '#db1d16',
            'reaction_score' => -1,
            'reaction_type' => 'negative',
            'display_order' => 700,
            'active' => 1
        )
    );

    foreach($default_reactions as $reaction)
    {
        $title = $db->escape_string($reaction['title']);
        $exists = $db->fetch_field($db->simple_select("reactions", "COUNT(*) as count", "title='{$title}'"), "count");
        if(!$exists)
        {
            $db->insert_query("reactions", $reaction);
        }
    }

    // Update cache
    global $cache;
    if(isset($cache) && function_exists('reactions_get_active'))
    {
        $cache->update("reactions_active", reactions_get_active(true));
    }
}

/**
 * Get all active reactions
 */
function reactions_get_active($force_refresh = false)
{
    global $db, $cache;
    
    $reactions = $cache->read("reactions_active");
    if($force_refresh || $reactions === false)
    {
        $query = $db->simple_select("reactions", "*", "active=1", array("order_by" => "display_order", "order_dir" => "ASC"));
        $reactions = array();
        while($reaction = $db->fetch_array($query))
        {
            $reactions[$reaction['reaction_id']] = $reaction;
        }
        $cache->update("reactions_active", $reactions);
    }
    
    return $reactions;
}

/**
 * Get reaction by ID
 */
function reactions_get_reaction($reaction_id)
{
    global $db;
    static $reactions_cache = array();

    $reaction_id = (int)$reaction_id;
    if(!$reaction_id) return null;

    if(!isset($reactions_cache[$reaction_id]))
    {
        // First check active reactions cache
        $active = reactions_get_active();
        if(isset($active[$reaction_id]))
        {
            $reactions_cache[$reaction_id] = $active[$reaction_id];
        }
        else
        {
            $reactions_cache[$reaction_id] = $db->fetch_array($db->simple_select("reactions", "*", "reaction_id=".$reaction_id));
        }
    }
    
    return $reactions_cache[$reaction_id];
}

/**
 * Get default reaction (Like)
 */
function reactions_get_default()
{
    global $db;
    static $default_cache = null;

    if($default_cache !== null)
    {
        return $default_cache;
    }
    
    // Check active reactions first
    $active = reactions_get_active();
    foreach($active as $reaction)
    {
        if($reaction['reaction_id'] == 1)
        {
            $default_cache = $reaction;
            return $default_cache;
        }
    }

    $default_cache = $db->fetch_array($db->simple_select("reactions", "*", "reaction_id=1"));
    if(!$default_cache)
    {
        // Fallback to first active reaction
        $default_cache = $db->fetch_array($db->simple_select("reactions", "*", "active=1", array("order_by" => "display_order", "limit" => 1)));
    }
    return $default_cache;
}

/**
 * Get reaction score
 */
function reactions_get_reaction_score($reaction_id)
{
    global $db;

    $reaction = reactions_get_reaction($reaction_id);
    return $reaction ? (int)$reaction['reaction_score'] : 0;
}

/**
 * Get sprite CSS
 */
function reactions_get_sprite_css($reaction, $size = 'small')
{
    global $mybb;

    $sprite_params = json_decode($reaction['sprite_params'], true);
    if(!$sprite_params)
    {
        return "";
    }

    $w = (int)$sprite_params['w'];
    $h = (int)$sprite_params['h'];
    $x = (int)$sprite_params['x'];
    $y = (int)$sprite_params['y'];
    $bs = !empty($sprite_params['bs']) ? $sprite_params['bs'] : '100%';

    if($size == 'small')
    {
        $w = $w / 2;
        $h = $h / 2;
        $x = $x / 2;
        $y = $y / 2;
    }
    elseif($size == 'medium')
    {
        $w = $w * 0.65625;
        $h = $h * 0.65625;
        $x = $x * 0.65625;
        $y = $y * 0.65625;
    }

    // Use MyBB's get_asset_url() to properly construct URLs
    $image_url = $mybb->get_asset_url($reaction['image_url']);

    return sprintf(
        'width: %dpx; height: %dpx; background: url(\'%s\') no-repeat %dpx %dpx; background-size: %s;',
        $w, $h, $image_url, $x, $y, $bs
    );
}

/**
 * Get reaction image HTML
 */
function reactions_get_reaction_image($reaction, $size = 'small')
{
    global $mybb;

    // Use MyBB's get_asset_url() to properly construct URLs (handles CDN, bburl, etc.)
    $image_url = $mybb->get_asset_url($reaction['image_url']);
    $image_url_2x = "";
    if(!empty($reaction['image_url_2x']))
    {
        $image_url_2x = $mybb->get_asset_url($reaction['image_url_2x']);
    }

    $title = htmlspecialchars_uni($reaction['title'] ?? '');

    if($reaction['sprite_mode'] && !empty($reaction['sprite_params']))
    {
        $sprite_css = reactions_get_sprite_css($reaction, $size);
        return '<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" class="reaction-sprite js-reaction" style="'.$sprite_css.'" alt="'.$title.'" title="'.$title.'" />';
    }
    else
    {
        $srcset = "";
        if($image_url_2x)
        {
            $srcset = ' srcset="'.$image_url_2x.' 2x"';
        }
        return '<img src="'.$image_url.'"'.$srcset.' class="reaction-image js-reaction" alt="'.$title.'" title="'.$title.'" />';
    }
}

/**
 * Format reactions text (e.g., "You, John, and 5 others")
 */
function reactions_format_reactions_text($total, $users, $visitor_id)
{
    global $lang, $mybb;

    if($total == 0)
    {
        return "";
    }

    // Ensure language file is loaded
    if(!isset($lang->reactions))
    {
        $lang->load("reactions");
    }

    // Filter out visitor from users list
    $other_users = array();
    $visitor_reacted = false;
    foreach($users as $user)
    {
        if($user['user_id'] == $visitor_id)
        {
            $visitor_reacted = true;
        }
        else
        {
            $other_users[] = $user;
        }
    }

    // Limit to 3 users and make them formatted but not clickable (they are inside a larger link)
    $other_users = array_slice($other_users, 0, 3);
    $names = array_map(function($u) { 
        return '<strong>'.htmlspecialchars_uni($u['username']).'</strong>'; 
    }, $other_users);

    if($visitor_reacted)
    {
        if($total == 1) return $lang->reactions_you;
        if($total == 2) return $lang->sprintf($lang->reactions_you_and_one, $names[0]);
        if($total == 3) return $lang->sprintf($lang->reactions_you_and_two, $names[0], $names[1]);
        if($total == 4) return $lang->sprintf($lang->reactions_you_three_one_other, $names[0], $names[1]);

        $others = $total - 3;
        return $lang->sprintf($lang->reactions_you_three_others, $names[0], $names[1], $others);
    }
    else
    {
        if($total == 1) return $names[0];
        if($total == 2) return $lang->sprintf($lang->reactions_two, $names[0], $names[1]);
        if($total == 3) return $lang->sprintf($lang->reactions_three, $names[0], $names[1], $names[2]);
        if($total == 4) return $lang->sprintf($lang->reactions_three_one_other, $names[0], $names[1], $names[2]);

        $others = $total - 3;
        return $lang->sprintf($lang->reactions_three_others, $names[0], $names[1], $names[2], $others);
    }
}

/**
 * Get user reaction score (Received)
 */
function reactions_get_user_reaction_score($user_id)
{
    global $db;

    $score = $db->fetch_field($db->simple_select("users", "reaction_score", "uid=".(int)$user_id), "reaction_score");
    return (int)$score;
}

/**
 * Get user reaction score (Given)
 */
function reactions_get_user_reaction_given_score($user_id)
{
    global $db;

    $score = $db->fetch_field($db->simple_select("users", "reaction_given_score", "uid=".(int)$user_id), "reaction_given_score");
    return (int)$score;
}

/**
 * Get user reaction count (Received) - Total number of reactions received
 */
function reactions_get_user_reaction_count($user_id)
{
    global $db;

    $count = $db->fetch_field($db->query("
        SELECT COUNT(*) as count
        FROM ".$db->table_prefix."reaction_content rc
        INNER JOIN ".$db->table_prefix."reactions r ON (rc.reaction_id = r.reaction_id)
        WHERE rc.content_user_id = ".(int)$user_id."
        AND rc.is_counted = 1
        AND r.active = 1
    "), "count");
    return (int)$count;
}

/**
 * Get user reaction count (Given) - Total number of reactions given
 */
function reactions_get_user_reaction_given_count($user_id)
{
    global $db;

    $count = $db->fetch_field($db->query("
        SELECT COUNT(*) as count
        FROM ".$db->table_prefix."reaction_content rc
        INNER JOIN ".$db->table_prefix."reactions r ON (rc.reaction_id = r.reaction_id)
        WHERE rc.reaction_user_id = ".(int)$user_id."
        AND rc.is_counted = 1
        AND r.active = 1
    "), "count");
    return (int)$count;
}

/**
 * Check if a user can react to content
 */
function reactions_can_react($content_type, $content_id, $user_id, $content_user_id)
{
    global $mybb, $db, $lang;

    if(!isset($lang->reactions))
    {
        $lang->load("reactions");
    }

    // 1. Check if reactions are enabled globally
    if(!$mybb->settings['reactions_enabled'])
    {
        return array('can_react' => false, 'error' => $lang->reactions_error_reactions_disabled, 'notify_type' => 'reactions_disabled');
    }
    
    // 1a. Check if user can use reactions based on group settings
    if(!reactions_can_use_reactions($user_id))
    {
        return array('can_react' => false, 'error' => $lang->reactions_error_no_permission_use, 'notify_type' => 'no_permission_use');
    }

    // 1b. Check excluded forums (if content_type is post)
    if($content_type == 'post' && !empty($mybb->settings['reactions_excluded_forums']))
    {
        $excluded = explode(',', $mybb->settings['reactions_excluded_forums']);
        $excluded = array_map('intval', $excluded);
        
        // We need to get the fid of the post
        $post = get_post($content_id);
        if($post && in_array($post['fid'], $excluded))
        {
            return array('can_react' => false, 'error' => $lang->reactions_error_disabled_in_forum, 'notify_type' => 'disabled_in_forum');
        }
    }

    // Get user permissions
    $user_perms = reactions_get_user_permissions($user_id);

    // 1c. Usergroup permissions check (Can Give Reactions)
    if($user_id != 0 && $user_perms['can_give_reactions'] == 0)
    {
        return array('can_react' => false, 'error' => $lang->reactions_error_usergroup_no_give, 'notify_type' => 'usergroup_no_give');
    }

    // 1d. Recipient usergroup permission check (Can Receive Reactions)
    if($content_user_id != 0)
    {
        $recipient_perms = reactions_get_user_permissions($content_user_id);
        if($recipient_perms && $recipient_perms['can_receive_reactions'] == 0)
        {
            return array('can_react' => false, 'error' => $lang->reactions_error_recipient_no_receive, 'notify_type' => 'recipient_no_receive');
        }
    }

    // 1e. Closed thread check
    // Super users bypass this restriction
    if($content_type == 'post' && !($mybb->settings['reactions_closed_threads'] ?? 0) && !$user_perms['is_super'])
    {
        $post = get_post($content_id);
        if($post)
        {
            $thread = get_thread($post['tid']);
            if($thread && $thread['closed'] == 1)
            {
                return array('can_react' => false, 'error' => $lang->reactions_error_thread_closed, 'notify_type' => 'thread_closed');
            }
        }
    }

    // 2. Guest checks
    if($user_id == 0)
    {
        if(!$mybb->settings['reactions_guest_react'])
        {
            return array('can_react' => false, 'error' => $lang->reactions_error_guests_not_allowed, 'notify_type' => 'guests_not_allowed');
        }
    }

    // 3. Self-reaction check
    // Super users can always react to their own posts (bypass restriction)
    if($user_id == $content_user_id && $user_id != 0)
    {
        if(!$mybb->settings['reactions_self_react'] && !$user_perms['is_super'])
        {
            return array('can_react' => false, 'error' => $lang->reactions_error_self_reaction_not_allowed, 'notify_type' => 'self_reaction_not_allowed');
        }
    }

    // 4. Minimum posts/reputation check
    // Super users bypass these restrictions
    if($user_id != 0 && !$user_perms['is_super'])
    {
        if($mybb->settings['reactions_min_posts'] > 0 && $mybb->user['postnum'] < $mybb->settings['reactions_min_posts'])
        {
            return array('can_react' => false, 'error' => $lang->sprintf($lang->reactions_error_min_posts_required, $mybb->settings['reactions_min_posts']), 'notify_type' => 'min_posts_required');
        }

        if($mybb->settings['reactions_min_reputation'] > 0 && $mybb->user['reputation'] < $mybb->settings['reactions_min_reputation'])
        {
            return array('can_react' => false, 'error' => $lang->sprintf($lang->reactions_error_min_reputation_required, $mybb->user['reputation']), 'notify_type' => 'min_reputation_required');
        }
    }

    // 5. Daily limit check
    // Super users bypass daily limit
    $daily_limit = (int)($mybb->settings['reactions_daily_limit'] ?? 0);
    
    // Check usergroup specific limit
    if($user_perms['reaction_daily_limit'] > 0)
    {
        $daily_limit = (int)$user_perms['reaction_daily_limit'];
    }

    if($user_id != 0 && $daily_limit > 0 && !$user_perms['is_super'])
    {
        $time_24h_ago = TIME_NOW - (24 * 3600);
        $count = $db->fetch_field($db->simple_select("reaction_content", "COUNT(*) as count", "reaction_user_id=".(int)$user_id." AND reaction_date > $time_24h_ago"), "count");
        
        if($count >= $daily_limit)
        {
            // We need to check if the user is UPDATING an existing reaction on THIS content, 
            // which shouldn't count towards the limit if they're just swapping.
            $already_reacted = $db->fetch_field($db->simple_select("reaction_content", "COUNT(*) as count", "reaction_user_id=".(int)$user_id." AND content_type='".$db->escape_string($content_type)."' AND content_id=".(int)$content_id), "count");
            
            if(!$already_reacted)
            {
                return array('can_react' => false, 'error' => $lang->sprintf($lang->reactions_error_daily_limit_reached, $daily_limit), 'notify_type' => 'daily_limit_reached');
            }
        }
    }

    // 6. Flood check
    // Super users bypass flood control
    $flood_time = (int)$mybb->settings['reactions_flood_time'];
    if($user_id != 0 && $flood_time > 0 && !$user_perms['is_super'])
    {
        $last_reaction = $db->fetch_field($db->simple_select("reaction_content", "reaction_date", "reaction_user_id=".(int)$user_id, array("order_by" => "reaction_date", "order_dir" => "DESC", "limit" => 1)), "reaction_date");
        
        if($last_reaction && (TIME_NOW - $last_reaction) < $flood_time)
        {
            $wait = $flood_time - (TIME_NOW - $last_reaction);
            return array('can_react' => false, 'error' => $lang->sprintf($lang->reactions_error_flood_control, $wait), 'notify_type' => 'flood_control');
        }
    }

    return array('can_react' => true);
}

/**
 * Check if a user can edit/remove a reaction
 * 
 * @param int $reaction_date Timestamp when reaction was made
 * @param int $user_id User ID checking permission (0 for current user)
 * @return bool True if can edit, false otherwise
 */
function reactions_can_edit_reaction($reaction_date, $user_id = 0)
{
    global $mybb;

    // Super users bypass time limit
    if($user_id != 0)
    {
        $user_perms = reactions_get_user_permissions($user_id);
        if($user_perms['is_super'])
        {
            return true;
        }
    }
    else
    {
        // Check current user
        global $mybb;
        $user_perms = reactions_get_user_permissions($mybb->user['uid']);
        if($user_perms['is_super'])
        {
            return true;
        }
    }

    if($mybb->settings['reactions_edit_time_limit'] <= 0)
    {
        return true;
    }

    $time_limit = $mybb->settings['reactions_edit_time_limit'] * 60;
    if(TIME_NOW - $reaction_date > $time_limit)
    {
        return false;
    }

    return true;
}

/**
 * Check if user can remove reactions
 * 
 * @param int $user_id User ID to check
 * @return bool True if can remove, false otherwise
 */
function reactions_can_remove_reactions($user_id = 0)
{
    global $mybb;
    
    if($user_id == 0)
    {
        $user_id = $mybb->user['uid'];
    }
    
    $user_perms = reactions_get_user_permissions($user_id);
    return $user_perms['can_remove_reactions'] == 1;
}

/**
 * React to content
 */
function reactions_react_to_content($reaction_id, $content_type, $content_id, $user_id, $content_user_id)
{
    global $db, $mybb, $lang;

    if(!isset($lang->reactions))
    {
        $lang->load("reactions");
    }

    // Check if user can react (new advanced checks)
    $can_react = reactions_can_react($content_type, $content_id, $user_id, $content_user_id);
    if(!$can_react['can_react'])
    {
        return array('error' => $can_react['error'], 'notify_type' => isset($can_react['notify_type']) ? $can_react['notify_type'] : '');
    }
    
    // Check if table exists
    if(!$db->table_exists("reaction_content")) {
        
        return array('error' => $lang->reactions_error_missing_table, 'notify_type' => '');
    }
    
    // Check if user already reacted
    $query = $db->simple_select(
        "reaction_content",
        "*",
        "content_type='".$db->escape_string($content_type)."' AND content_id=".(int)$content_id." AND reaction_user_id=".(int)$user_id
    );
    
    $existing = $db->fetch_array($query);
    
    if($existing)
    {
        // Check edit time limit (pass user_id for super user bypass check)
        if(!reactions_can_edit_reaction($existing['reaction_date'], $user_id))
        {
            return array('error' => $lang->reactions_error_edit_time_expired);
        }

        // If same reaction, remove it
        if($existing['reaction_id'] == $reaction_id)
        {
            reactions_remove_reaction($existing['reaction_content_id'], $content_type, $content_id, $user_id, $content_user_id);
            return null;
        }
        // If different reaction, update it (remove old one first)
        else
        {
            reactions_remove_reaction($existing['reaction_content_id'], $content_type, $content_id, $user_id, $content_user_id);
        }
    }
    
    // Insert new reaction
    $reaction_data = array(
        'reaction_id' => (int)$reaction_id,
        'content_type' => $db->escape_string($content_type),
        'content_id' => (int)$content_id,
        'reaction_user_id' => (int)$user_id,
        'reaction_date' => TIME_NOW,
        'content_user_id' => (int)$content_user_id,
        'is_counted' => 1
    );
    $reaction_content_id = $db->insert_query("reaction_content", $reaction_data);
    
    // Add reputation entry
    reactions_add_reputation_entry($reaction_id, $content_type, $content_id, $user_id, $content_user_id, $reaction_content_id);
    
    // Rebuild content reaction cache
    reactions_rebuild_content_cache($content_type, $content_id, $user_id);
    
    // Send MyAlerts notification if integration is available
    if(function_exists('reactions_myalerts_add_alert') && $user_id != $content_user_id && $content_user_id != 0)
    {
        reactions_myalerts_add_alert($reaction_id, $content_id, $user_id, $content_user_id);
    }
    
    // Fire hook for third-party plugin integration
    $reaction = reactions_get_reaction($reaction_id);
    $reaction_score = $reaction ? (int)$reaction['reaction_score'] : 0;
    
    if($existing)
    {
        // This is an update (reaction was changed from one type to another)
        $old_reaction = reactions_get_reaction($existing['reaction_id']);
        $old_score = $old_reaction ? (int)$old_reaction['reaction_score'] : 0;
        reactions_hook_after_update($reaction_content_id, $existing['reaction_id'], $reaction_id, $content_type, $content_id, $user_id, $content_user_id, $old_score, $reaction_score);
    }
    else
    {
        // This is a new reaction
        reactions_hook_after_add($reaction_content_id, $reaction_id, $content_type, $content_id, $user_id, $content_user_id, TIME_NOW, $reaction_score);
    }
    
    return $reaction_content_id;
}

/**
 * Get thread ID for a post
 */
function reactions_get_post_tid($pid)
{
    global $db;
    return (int)$db->fetch_field($db->simple_select("posts", "tid", "pid=".(int)$pid), "tid");
}

/**
 * Remove reaction
 */
function reactions_remove_reaction($reaction_content_id, $content_type, $content_id, $giver_id = 0, $receiver_id = 0)
{
    global $db, $mybb;
    
    // Get reaction details before deletion (needed for alert)
    $reaction_data = $db->fetch_array($db->simple_select("reaction_content", "*", "reaction_content_id=".(int)$reaction_content_id));
    $reaction_id = !empty($reaction_data['reaction_id']) ? (int)$reaction_data['reaction_id'] : 0;
    
    // If giver_id not provided, try to find it
    if(!$giver_id)
    {
        $giver_id = !empty($reaction_data['reaction_user_id']) ? (int)$reaction_data['reaction_user_id'] : 0;
    }
    
    // If receiver_id not provided, try to find it
    if(!$receiver_id)
    {
        $receiver_id = !empty($reaction_data['content_user_id']) ? (int)$reaction_data['content_user_id'] : 0;
    }
    
    $db->delete_query("reaction_content", "reaction_content_id=".(int)$reaction_content_id);
    
    // Remove reputation entry
    reactions_remove_reputation_entry($reaction_content_id);
    
    // Delete MyAlerts notification if integration is available
    if(function_exists('reactions_myalerts_delete_alert') && $receiver_id != 0 && $giver_id != $receiver_id)
    {
        reactions_myalerts_delete_alert($content_id, $giver_id, $receiver_id);
    }
    
    // Send "reaction removed" alert if MyAlerts is enabled
    if(function_exists('reactions_myalerts_send_removed_alert') && $receiver_id != 0 && $giver_id != $receiver_id && $reaction_id > 0)
    {
        // Get post/thread info
        $tid = 0;
        $fid = 0;
        if($content_type == 'post')
        {
            $post = get_post($content_id);
            if($post)
            {
                $tid = (int)$post['tid'];
                $fid = (int)$post['fid'];
            }
        }
        
        reactions_myalerts_send_removed_alert($giver_id, $receiver_id, $reaction_id, $content_id, $tid, $fid);
    }
    
    // Rebuild content reaction cache
    reactions_rebuild_content_cache($content_type, $content_id, $giver_id);
    
    // Fire hook for third-party plugin integration
    $reaction = reactions_get_reaction($reaction_id);
    $reaction_score = $reaction ? (int)$reaction['reaction_score'] : 0;
    reactions_hook_after_remove($reaction_content_id, $reaction_id, $content_type, $content_id, $giver_id, $receiver_id, $reaction_score);
}

/**
 * Add reputation entry for reaction
 * Stores user-friendly comment and tracks in reaction_reputation table
 */
function reactions_add_reputation_entry($reaction_id, $content_type, $content_id, $from_uid, $to_uid, $reaction_content_id = 0)
{
    global $db, $mybb, $lang;
    
    // Check if reputation integration is enabled
    if(empty($mybb->settings['reactions_integrate_reputation']))
    {
        return false;
    }
    
    if($to_uid == 0 || $from_uid == $to_uid)
    {
        return false;
    }
    
    $reaction = reactions_get_reaction($reaction_id);
    if(!$reaction)
    {
        return false;
    }
    
    $score = (int)$reaction['reaction_score'];
    
    // Check if negative reactions are allowed
    if($score < 0 && empty($mybb->settings['reactions_reputation_allow_negative']))
    {
        $score = 0;
    }
    
    if($score == 0)
    {
        return false;
    }
    
    // Load language if needed
    if(!isset($lang->reactions))
    {
        $lang->load("reactions");
    }
    
    // Get user who gave the reaction
    $from_user = get_user($from_uid);
    if(!$from_user)
    {
        return false;
    }
    
    // Build plain text comment (will be formatted later by hook)
    // Format: "[REACTION_MARKER]Username|ReactionName|ContentType|ContentID|PostID|ThreadID|ThreadName"
    $post_id = 0;
    $thread_id = 0;
    $thread_name = '';
    
    if($content_type == 'post' && $content_id)
    {
        $post = get_post($content_id);
        if($post)
        {
            $post_id = $post['pid'];
            $thread_id = $post['tid'];
            $thread = get_thread($post['tid']);
            if($thread)
            {
                $thread_name = $thread['subject'];
            }
        }
    }
    elseif($content_type == 'admin_gift')
    {
        // Admin gift - mark with special identifier for formatting
        $thread_name = 'Admin Gift';
    }
    
    // Create machine-readable but user-friendly comment
    // The [REACTION] marker helps us identify this in the hook
    $comment_parts = array(
        '[REACTION]',                    // Marker for identification
        $from_user['username'],          // 1: Username
        $reaction['title'],              // 2: Reaction name
        $content_type,                   // 3: Content type
        $content_id,                     // 4: Content ID
        $post_id,                        // 5: Post ID
        $thread_id,                      // 6: Thread ID
        $thread_name                     // 7: Thread name
    );
    $comment = implode('|', $comment_parts);
    
    // Insert into reputation table
    $reputation_data = array(
        'uid' => $to_uid,
        'adduid' => $from_uid,
        'pid' => $post_id,
        'reputation' => $score,
        'dateline' => TIME_NOW,
        'comments' => $db->escape_string($comment)
    );
    
    $rid = $db->insert_query("reputation", $reputation_data);
    
    // Also track in our reaction_reputation table for easy lookup
    $reaction_rep_data = array(
        'reputation_rid' => $rid,
        'reaction_content_id' => $reaction_content_id,
        'from_uid' => $from_uid,
        'to_uid' => $to_uid,
        'reaction_id' => $reaction_id,
        'content_type' => $content_type,
        'content_id' => $content_id,
        'dateline' => TIME_NOW
    );
    
    $db->insert_query("reaction_reputation", $reaction_rep_data);
    
    // Update user reputation
    $db->write_query("UPDATE " . TABLE_PREFIX . "users SET reputation = reputation + " . $score . " WHERE uid = " . $to_uid);
    
    return $rid;
}

/**
 * Remove reputation entry for reaction
 */
function reactions_remove_reputation_entry($reaction_content_id)
{
    global $db, $mybb;
    
    // Check if reputation integration is enabled
    if(empty($mybb->settings['reactions_integrate_reputation']))
    {
        return false;
    }
    
    if(!$reaction_content_id)
    {
        return false;
    }
    
    // Find the reputation entry using our tracking table
    $reaction_rep = $db->fetch_array($db->simple_select(
        "reaction_reputation",
        "*",
        "reaction_content_id = " . (int)$reaction_content_id
    ));
    
    if($reaction_rep)
    {
        // Get reputation details before deleting
        $reputation = $db->fetch_array($db->simple_select(
            "reputation",
            "*",
            "rid = " . (int)$reaction_rep['reputation_rid']
        ));
        
        if($reputation)
        {
            // Delete from reputation table
            $db->delete_query("reputation", "rid = " . (int)$reputation['rid']);
            
            // Delete from our tracking table
            $db->delete_query("reaction_reputation", "reputation_rid = " . (int)$reputation['rid']);
            
            // Update user reputation
            $db->write_query("UPDATE " . TABLE_PREFIX . "users SET reputation = reputation - " . (int)$reputation['reputation'] . " WHERE uid = " . (int)$reputation['uid']);
            
            return true;
        }
    }
    
    return false;
}

/**
 * Format reputation comment for display on reputation report page
 * Hook: reputation_vote
 * 
 * Parses comments stored as: [REACTION]|Username|ReactionName|ContentType|ContentID|PostID|ThreadID|ThreadName
 */
function reactions_format_reputation_comment()
{
    global $reputation_vote, $mybb, $db, $parser, $lang;
    
    if(!isset($lang->reactions_reputation_given))
    {
        $lang->load("reactions");
    }
    
    // Check if this is a reaction-based reputation entry by looking at the parsed comment
    // The parser may have escaped HTML, so we check for the marker
    $comment = $reputation_vote['comments'];
    
    // Try to get the raw comment from database
    $raw_comment = $db->fetch_field(
        $db->simple_select("reputation", "comments", "rid = " . (int)$reputation_vote['rid']),
        "comments"
    );
    
    // Check if this is a reaction entry (new format starts with [REACTION])
    if(empty($raw_comment) || strpos($raw_comment, '[REACTION]|') !== 0)
    {
        // Check for old format (REACTION|id|from_uid)
        if(!empty($raw_comment) && strpos($raw_comment, 'REACTION|') === 0 && strpos($raw_comment, '[REACTION]') === false)
        {
            // Old format - handle legacy entries
            reactions_format_legacy_reputation_comment($raw_comment);
        }
        return;
    }
    
    // Parse the new format: [REACTION]|Username|ReactionName|ContentType|ContentID|PostID|ThreadID|ThreadName
    $parts = explode('|', $raw_comment);
    if(count($parts) < 8)
    {
        return;
    }
    
    $username = $parts[1];
    $reaction_name = $parts[2];
    $content_type = $parts[3];
    $content_id = (int)$parts[4];
    $post_id = (int)$parts[5];
    $thread_id = (int)$parts[6];
    $thread_name = $parts[7];
    
    // Get from_uid from reputation_vote array (adduid)
    $from_uid = (int)$reputation_vote['adduid'];
    
    // Build clickable username
    $from_user = get_user($from_uid);
    if($from_user)
    {
        $username_formatted = format_name(
            htmlspecialchars_uni($from_user['username']),
            $from_user['usergroup'],
            $from_user['displaygroup']
        );
        $username_link = build_profile_link($username_formatted, $from_uid);
    }
    else
    {
        $username_link = htmlspecialchars_uni($username);
    }
    
    // Get reaction details for image and color
    $reaction_image = '';
    $reaction_display = htmlspecialchars_uni($reaction_name);
    
    // Look up reaction by name to get image and color
    $reaction = $db->fetch_array($db->simple_select("reactions", "*", "title = '" . $db->escape_string($reaction_name) . "' AND active = 1"));
    if($reaction)
    {
        // Build reaction image
        if(!empty($reaction['image_url']))
        {
            $image_url = htmlspecialchars_uni($mybb->get_asset_url($reaction['image_url']));
            $reaction_image = '<img src="' . $image_url . '" alt="" style="width:16px;height:16px;vertical-align:middle;margin:0 3px;" />';
        }
        
        // Get reaction color
        $reaction_color = !empty($reaction['text_color']) ? htmlspecialchars_uni($reaction['text_color']) : '#666';
        $reaction_display = '<span style="color:' . $reaction_color . ';font-weight:bold;">' . htmlspecialchars_uni($reaction['title']) . '</span>';
    }
    
    // Build post and thread links
    $post_text = $lang->reactions_this_post;
    $thread_display = '';
    
    // Handle admin gift content type
    if($content_type == 'admin_gift')
    {
        $post_text = $lang->reactions_admin_gift;
    }
    elseif($post_id && $thread_id)
    {
        $post_link = get_post_link($post_id) . '#pid' . $post_id;
        $post_text = '<a href="' . $post_link . '">' . $lang->reactions_this_post . '</a>';
        
        $thread_link = get_thread_link($thread_id);
        $thread_name_clean = htmlspecialchars_uni($parser->parse_badwords($thread_name));
        $thread_display = $lang->sprintf($lang->reactions_reputation_in_thread, '<a href="' . $thread_link . '">' . $thread_name_clean . '</a>');
    }
    
    // Build final formatted comment
    $formatted_comment = $lang->sprintf($lang->reactions_reputation_given, $username_link, $reaction_image, $reaction_display, $post_text . $thread_display);
    
    $reputation_vote['comments'] = $formatted_comment;
}

/**
 * Format legacy reputation comments (old format)
 */
function reactions_format_legacy_reputation_comment($raw_comment)
{
    global $reputation_vote, $mybb, $db, $parser, $lang;
    
    if(!isset($lang->reactions_reputation_given))
    {
        $lang->load("reactions");
    }
    
    // Old format: REACTION|reaction_content_id|Reaction from uid
    $parts = explode('|', $raw_comment);
    if(count($parts) < 3)
    {
        return;
    }
    
    $reaction_content_id = (int)$parts[1];
    
    // Get reaction content details
    $reaction_content = $db->fetch_array($db->simple_select(
        "reaction_content",
        "*",
        "reaction_content_id=" . $reaction_content_id
    ));
    
    if(!$reaction_content)
    {
        return;
    }
    
    // Get reaction details
    $reaction = reactions_get_reaction($reaction_content['reaction_id']);
    if(!$reaction)
    {
        return;
    }
    
    // Get user who gave the reaction
    $from_uid = (int)$reputation_vote['adduid'];
    $from_user = get_user($from_uid);
    if(!$from_user)
    {
        return;
    }
    
    // Build clickable username
    $username_formatted = format_name(
        htmlspecialchars_uni($from_user['username']),
        $from_user['usergroup'],
        $from_user['displaygroup']
    );
    $username_link = build_profile_link($username_formatted, $from_uid);
    
    // Build reaction display
    $reaction_image = '';
    if(!empty($reaction['image_url']))
    {
        $image_url = htmlspecialchars_uni($mybb->get_asset_url($reaction['image_url']));
        $reaction_image = '<img src="' . $image_url . '" alt="" style="width:16px;height:16px;vertical-align:middle;margin:0 3px;" />';
    }
    
    $reaction_color = !empty($reaction['text_color']) ? htmlspecialchars_uni($reaction['text_color']) : '#666';
    $reaction_name = htmlspecialchars_uni($reaction['title']);
    $reaction_display = '<span style="color:' . $reaction_color . ';font-weight:bold;">' . $reaction_name . '</span>';
    
    // Get post and thread info
    $post_text = $lang->reactions_this_post;
    $thread_display = '';
    
    if($reaction_content['content_type'] == 'post' && $reaction_content['content_id'])
    {
        $post = get_post($reaction_content['content_id']);
        if($post)
        {
            $thread = get_thread($post['tid']);
            if($thread)
            {
                $post_link = get_post_link($reaction_content['content_id']) . '#pid' . $reaction_content['content_id'];
                $post_text = '<a href="' . $post_link . '">' . $lang->reactions_this_post . '</a>';
                
                $thread_link = get_thread_link($post['tid']);
                $thread_name = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
                $thread_display = $lang->sprintf($lang->reactions_reputation_in_thread, '<a href="' . $thread_link . '">' . $thread_name . '</a>');
            }
        }
    }
    
    $formatted_comment = $lang->sprintf($lang->reactions_reputation_given, $username_link, $reaction_image, $reaction_display, $post_text . $thread_display);
    $reputation_vote['comments'] = $formatted_comment;
}

/**
 * Get content type info
 */
function reactions_get_content_type_info($content_type)
{
    switch($content_type)
    {
        case 'post':
            return array(
                'table' => 'posts',
                'id_column' => 'pid',
                'user_column' => 'uid'
            );
        default:
            return null;
    }
}

/**
 * Rebuild content reaction cache and update user scores
 */
function reactions_rebuild_content_cache($content_type, $content_id, $giver_id = 0)
{
    global $db;
    $type_info = reactions_get_content_type_info($content_type);
    if(!$type_info)
    {
        
        return;
    }
    
    $counts_query = $db->query("
        SELECT rc.reaction_id, COUNT(*) as count
        FROM ".$db->table_prefix."reaction_content rc
        INNER JOIN ".$db->table_prefix."reactions r ON (rc.reaction_id = r.reaction_id)
        WHERE rc.content_type = '".$db->escape_string($content_type)."' 
        AND rc.content_id = ".(int)$content_id."
        AND r.active = 1
        GROUP BY rc.reaction_id
        ORDER BY count DESC
    ");
    $counts = array();
    while($row = $db->fetch_array($counts_query))
    {    
        $counts[$row['reaction_id']] = (int)$row['count'];
    }
    $users_query = $db->query("
        SELECT u.uid, u.username, rc.reaction_id
        FROM ".$db->table_prefix."reaction_content rc
        INNER JOIN ".$db->table_prefix."reactions r ON (rc.reaction_id = r.reaction_id)
        INNER JOIN ".$db->table_prefix."users u ON (rc.reaction_user_id = u.uid)
        WHERE rc.content_type = '".$db->escape_string($content_type)."' 
        AND rc.content_id = ".(int)$content_id."
        AND r.active = 1
        ORDER BY rc.reaction_date DESC
        LIMIT 5
    ");
    $users = array();
    while($row = $db->fetch_array($users_query))
    {    
        $users[] = array(
            'user_id' => (int)$row['uid'],
            'username' => $row['username'],
            'reaction_id' => (int)$row['reaction_id']
        );
    }
    $score_query = $db->query("
        SELECT SUM(r.reaction_score) as score
        FROM ".$db->table_prefix."reaction_content rc
        INNER JOIN ".$db->table_prefix."reactions r ON (rc.reaction_id = r.reaction_id)
        WHERE rc.content_type = '".$db->escape_string($content_type)."'
        AND rc.content_id = ".(int)$content_id."
        AND rc.is_counted = 1
    ");

    

    $score_row = $db->fetch_array($score_query);
    $score = !empty($score_row['score']) ? (int)$score_row['score'] : 0;

    
    
    // Update content
    
    
    $db->update_query($type_info['table'], array(
        'reaction_score' => $score,
        'reactions' => $db->escape_string(json_encode($counts)),
        'reaction_users' => $db->escape_string(json_encode($users))
    ), $type_info['id_column']."=".(int)$content_id);
    // Update user reaction scores
    // Update receiver score
    $content = $db->fetch_array($db->simple_select($type_info['table'], $type_info['user_column'], $type_info['id_column']."=".(int)$content_id));
    if($content && $content[$type_info['user_column']])
    {    
        reactions_update_user_reaction_scores($content[$type_info['user_column']]);
    }

    // Update giver score if provided
    if($giver_id)
    {    
        reactions_update_user_reaction_scores($giver_id);
    }
}

/**
 * Update user reaction scores (Both Received and Given)
 */
function reactions_update_user_reaction_scores($user_id)
{
    global $db;
    
    $user_id = (int)$user_id;
    if(!$user_id)
    {
        return;
    }
    
    // Calculate total reaction score received by user
    $received_query = $db->query("
        SELECT SUM(r.reaction_score) as total_score
        FROM ".$db->table_prefix."reaction_content rc
        INNER JOIN ".$db->table_prefix."reactions r ON (rc.reaction_id = r.reaction_id)
        WHERE rc.content_user_id = ".$user_id."
        AND rc.is_counted = 1
    ");
    $received_row = $db->fetch_array($received_query);
    $received_score = (int)$received_row['total_score'];
    
    // Calculate total reaction score given by user
    $given_query = $db->query("
        SELECT SUM(r.reaction_score) as total_score
        FROM ".$db->table_prefix."reaction_content rc
        INNER JOIN ".$db->table_prefix."reactions r ON (rc.reaction_id = r.reaction_id)
        WHERE rc.reaction_user_id = ".$user_id."
        AND rc.is_counted = 1
    ");
    $given_row = $db->fetch_array($given_query);
    $given_score = (int)$given_row['total_score'];

    $update_array = array(
        'reaction_score' => $received_score,
        'reaction_given_score' => $given_score
    );

    // Update user
    $db->update_query("users", $update_array, "uid=".$user_id);
}

/**
 * Get user's reaction to content
 */
function reactions_get_user_reaction($content_type, $content_id, $user_id)
{
    global $db;
    
    $reaction = $db->fetch_array($db->simple_select(
        "reaction_content",
        "*",
        "content_type='".$db->escape_string($content_type)."' AND content_id=".(int)$content_id." AND reaction_user_id=".(int)$user_id
    ));
    
    return $reaction;
}

/**
 * Get content reactions list
 */
function reactions_get_content_reactions($content_type, $content_id, $reaction_id = null, $page = 1, $per_page = 50)
{
    global $db;
    
    $where = "content_type='".$db->escape_string($content_type)."' AND content_id=".(int)$content_id;
    if($reaction_id)
    {
        $where .= " AND reaction_id=".(int)$reaction_id;
    }
    
    $start = ($page - 1) * $per_page;
    
    $query = $db->query("
        SELECT rc.*, u.username, u.avatar, u.usergroup, u.displaygroup
        FROM ".$db->table_prefix."reaction_content rc
        INNER JOIN ".$db->table_prefix."reactions r ON (rc.reaction_id = r.reaction_id)
        INNER JOIN ".$db->table_prefix."users u ON (rc.reaction_user_id = u.uid)
        WHERE ".$where."
        AND r.active = 1
        ORDER BY rc.reaction_date DESC
        LIMIT ".$start.", ".$per_page."
    ");
    
    $reactions = array();
    while($row = $db->fetch_array($query))
    {
        $reactions[] = $row;
    }
    
    return $reactions;
}

/**
 * Get content reaction tab summary
 */
function reactions_get_content_tab_summary($content_type, $content_id)
{
    global $db;

    $query = $db->query("
        SELECT rc.reaction_id, COUNT(*) as count
        FROM ".$db->table_prefix."reaction_content rc
        INNER JOIN ".$db->table_prefix."reactions r ON (rc.reaction_id = r.reaction_id)
        WHERE rc.content_type = '".$db->escape_string($content_type)."'
        AND rc.content_id = ".(int)$content_id."
        AND r.active = 1
        GROUP BY rc.reaction_id
        ORDER BY count DESC
    ");

    $summary = array(0 => 0); // All reactions
    while($row = $db->fetch_array($query))
    {
        $summary[0] += (int)$row['count'];
        $summary[$row['reaction_id']] = (int)$row['count'];
    }

    return $summary;
}

/**
 * Get reaction overlay HTML
 */
function reactions_get_overlay_html($content_type, $content_id, $reaction_id = null, $page = 1, $per_page = 50)
{
    global $db, $mybb, $templates, $lang;

    if(!isset($lang->reactions))
    {
        $lang->load("reactions");
    }

    $start = ($page - 1) * $per_page;

    $where = "rc.content_type='".$db->escape_string($content_type)."'
              AND rc.content_id=".(int)$content_id;

    if($reaction_id)
    {
        $where .= " AND rc.reaction_id=".(int)$reaction_id;
    }

    $query = $db->query("
        SELECT rc.*, u.uid, u.username, u.avatar, u.usergroup, u.displaygroup, u.usertitle, u.postnum, u.reaction_score, r.title as reaction_title, r.image_url, r.sprite_mode, r.sprite_params
        FROM ".$db->table_prefix."reaction_content rc
        INNER JOIN ".$db->table_prefix."reactions r ON (rc.reaction_id = r.reaction_id)
        INNER JOIN ".$db->table_prefix."users u ON (rc.reaction_user_id = u.uid)
        WHERE ".$where."
        AND r.active = 1
        ORDER BY rc.reaction_date DESC
        LIMIT ".$start.", ".$per_page."
    ");

    $reactions = array();
    while($row = $db->fetch_array($query))
    {
        $reactions[] = $row;
    }

    // Get tab summary
    $tab_summary = reactions_get_content_tab_summary($content_type, $content_id);

    // Build tabs
    $tabs = array();
    $tabs[] = array(
        'id' => 0,
        'title' => $lang->reactions_all,
        'count' => $tab_summary[0],
        'active' => !$reaction_id,
        'image' => ''
    );

    $active_reactions = reactions_get_active();
    foreach($active_reactions as $reaction)
    {
        if(isset($tab_summary[$reaction['reaction_id']]))
        {
            $tabs[] = array(
                'id' => $reaction['reaction_id'],
                'title' => $reaction['title'],
                'count' => $tab_summary[$reaction['reaction_id']],
                'active' => $reaction_id == $reaction['reaction_id'],
                'image' => reactions_get_reaction_image($reaction, 'small')
            );
        }
    }

    // Build content rows
    $content_rows = '';
    
    // Use dynamic template loading
    $row_tpl = reactions_load_template("reactions_overlay_row");
    if(!$row_tpl)
    {
        $row_tpl = $templates->get("reactions_overlay_row");
    }
    
    foreach($reactions as $reaction)
    {
        $avatar_url = $reaction['avatar'] ? (strpos($reaction['avatar'], '://') !== false ? $reaction['avatar'] : $mybb->settings['bburl'].'/'.$reaction['avatar']) : $mybb->settings['bburl'].'/images/default_avatar.png';
        $reaction_img = reactions_get_reaction_image(array('reaction_id' => $reaction['reaction_id'], 'image_url' => $reaction['image_url'], 'sprite_mode' => $reaction['sprite_mode'], 'sprite_params' => $reaction['sprite_params']), 'small');
        
        $formatted_username = format_name($reaction['username'], $reaction['usergroup'], $reaction['displaygroup']);
        $reacted_at = my_date($mybb->settings['dateformat'], $reaction['reaction_date']).' '.my_date($mybb->settings['timeformat'], $reaction['reaction_date']);

        if($row_tpl)
        {
            eval("\$row_html = \"" . $row_tpl . "\";");
            $content_rows .= $row_html;
        }
        else
        {
            // No fallback
           continue;
        }
    }

    // Build tabs HTML
    $tabs_html = '';
    
    // Use dynamic template loading
    $tab_tpl = reactions_load_template("reactions_overlay_tab");
    if(!$tab_tpl)
    {
        $tab_tpl = $templates->get("reactions_overlay_tab");
    }
    
    foreach($tabs as $tab)
    {
        $active_class = $tab['active'] ? 'is-active' : '';
        if($tab_tpl)
        {
            eval("\$tab_html = \"" . $tab_tpl . "\";");
            $tabs_html .= $tab_html;
        }
        else
        {
            // No fallback
            continue;
        }
    }

    // Use dynamic template loading
    $container_tpl = reactions_load_template("reactions_overlay");
    if(!$container_tpl)
    {
        $container_tpl = $templates->get("reactions_overlay");
    }
    
    if($container_tpl)
    {
        eval("\$html = \"" . $container_tpl . "\";");
    }
    return $html;
}


/**
 * Check if user groups are allowed based on a groupselect setting value (WHITELIST approach)
 * 
 * @param array $user_groups Array of user's group IDs
 * @param string $setting_value The groupselect setting value (-1 = all allowed, empty = none allowed, comma-separated = specific allowed)
 * @return bool True if allowed, false otherwise
 */
function reactions_is_group_allowed($user_groups, $setting_value)
{
    // -1 means all groups are allowed
    if($setting_value == '-1')
    {
        return true;
    }
    
    // Empty means no groups are allowed
    if($setting_value === '' || $setting_value === null)
    {
        return false;
    }
    
    // Parse allowed groups
    $allowed_groups = explode(',', $setting_value);
    $allowed_groups = array_map('intval', $allowed_groups);
    $allowed_groups = array_filter($allowed_groups); // Remove zeros/empty
    
    // Check if any of user's groups is in the allowed list
    foreach($user_groups as $group_id)
    {
        if(in_array((int)$group_id, $allowed_groups))
        {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if user groups are NOT blocked based on a groupselect setting value (BLACKLIST approach)
 * Used for "not allowed" settings where we specify who CANNOT do something
 * 
 * @param array $user_groups Array of user's group IDs
 * @param string $setting_value The groupselect setting value (-1 = all blocked, empty = none blocked, comma-separated = specific blocked)
 * @return bool True if NOT blocked (allowed), false if blocked
 */
function reactions_is_group_not_blocked($user_groups, $setting_value)
{
    // -1 means all groups are blocked (nobody allowed)
    if($setting_value == '-1')
    {
        return false;
    }
    
    // Empty means no groups are blocked (everyone allowed)
    if($setting_value === '' || $setting_value === null)
    {
        return true;
    }
    
    // Parse blocked groups
    $blocked_groups = explode(',', $setting_value);
    $blocked_groups = array_map('intval', $blocked_groups);
    $blocked_groups = array_filter($blocked_groups); // Remove zeros/empty
    
    // Check if any of user's groups is in the blocked list
    foreach($user_groups as $group_id)
    {
        if(in_array((int)$group_id, $blocked_groups))
        {
            return false; // User is blocked
        }
    }
    
    // User is not in any blocked group
    return true;
}

/**
 * Get user's groups (primary and additional)
 * 
 * @param int $uid User ID (0 for guests)
 * @return array Array of group IDs
 */
function reactions_get_user_groups($uid)
{
    global $db, $mybb;
    
    $uid = (int)$uid;
    
    if($uid == 0)
    {
        // Guests are in group 1
        return array(1);
    }
    
    if($uid == $mybb->user['uid'])
    {
        $user = $mybb->user;
    }
    else
    {
        $user = $db->fetch_array($db->simple_select("users", "usergroup, additionalgroups", "uid={$uid}"));
    }
    
    if(!$user)
    {
        return array();
    }
    
    $groups = array((int)$user['usergroup']);
    
    if(!empty($user['additionalgroups']))
    {
        $additional = explode(',', $user['additionalgroups']);
        foreach($additional as $group)
        {
            $group_id = (int)$group;
            if($group_id > 0 && !in_array($group_id, $groups))
            {
                $groups[] = $group_id;
            }
        }
    }
    
    return $groups;
}

/**
 * Get reaction-related permissions for a user across all their groups
 * Uses plugin settings (groupselect) instead of database columns
 */
function reactions_get_user_permissions($uid)
{
    global $db, $mybb, $cache;
    static $perm_cache = array();

    $uid = (int)$uid;
    
    // Check cache first
    if($uid != 0 && isset($perm_cache[$uid]))
    {
        return $perm_cache[$uid];
    }

    // Get user's groups
    $user_groups = reactions_get_user_groups($uid);
    
    if(empty($user_groups))
    {
        return array(
            'reaction_daily_limit' => 0,
            'can_give_reactions' => 0,
            'can_view_reactions' => 0,
            'can_receive_reactions' => 0,
            'can_remove_reactions' => 0,
            'is_super' => 0
        );
    }

    // Check permissions based on plugin settings (groupselect values)
    // Note: reactions_use_groups_list and reactions_receive_groups use BLACKLIST (not blocked) logic
    //       reactions_view_groups, reactions_remove_groups_list, reactions_super_groups use WHITELIST (allowed) logic

    // Check if user is super (super users bypass all restrictions including removal)
    $is_super_user = reactions_is_group_allowed($user_groups, $mybb->settings['reactions_super_groups']);

    // Can remove reactions:
    // - If user is in allowed groups (reactions_remove_groups_list), OR
    // - If user is super (bypass)
    // Note: If reactions_remove_groups_list is empty, only super users can remove
    $can_remove = reactions_is_group_allowed($user_groups, $mybb->settings['reactions_remove_groups_list']);

    // Super users can always remove (bypass)
    if($is_super_user) {
        $can_remove = true;
    }
    
    $perms = array(
        'reaction_daily_limit' => 0,
        'can_give_reactions' => reactions_is_group_not_blocked($user_groups, $mybb->settings['reactions_use_groups_list']) ? 1 : 0,
        'can_view_reactions' => reactions_is_group_allowed($user_groups, $mybb->settings['reactions_view_groups']) ? 1 : 0,
        'can_receive_reactions' => reactions_is_group_not_blocked($user_groups, $mybb->settings['reactions_receive_groups']) ? 1 : 0,
        'can_remove_reactions' => $can_remove ? 1 : 0,
        'is_super' => $is_super_user ? 1 : 0
    );

    // Get daily limit from usergroup (this is still stored in usergroups table)
    $groups_list = implode(',', $user_groups);
    $query = $db->simple_select("usergroups", "reaction_daily_limit", "gid IN ({$groups_list})");
    
    while($group = $db->fetch_array($query))
    {
        // Take the highest daily limit from any of user's groups
        if(isset($group['reaction_daily_limit']) && (int)$group['reaction_daily_limit'] > $perms['reaction_daily_limit'])
        {
            $perms['reaction_daily_limit'] = (int)$group['reaction_daily_limit'];
        }
    }

    if($uid != 0)
    {
        $perm_cache[$uid] = $perms;
    }
    
    return $perms;
}

/**
 * Check if user can use reactions based on group settings
 */
function reactions_can_use_reactions($user_id)
{
    global $mybb, $lang;
    
    if(!isset($lang->reactions))
    {
        $lang->load("reactions");
    }
    
    // Get user permissions from settings
    $user_perms = reactions_get_user_permissions($user_id);
    
    // Check if user can give reactions
    if($user_perms['can_give_reactions'] == 0 && $user_perms['is_super'] == 0)
    {
        return false;
    }
    
    return true;
}

/**
 * Check if user can view reactions
 */
function reactions_can_view_reactions($user_id)
{
    $user_perms = reactions_get_user_permissions($user_id);
    return ($user_perms['can_view_reactions'] == 1 || $user_perms['is_super'] == 1);
}

/**
 * Check if user can receive reactions
 */
function reactions_can_receive_reactions($user_id)
{
    $user_perms = reactions_get_user_permissions($user_id);
    return ($user_perms['can_receive_reactions'] == 1 || $user_perms['is_super'] == 1);
}

/**
 * Check if user is a super user (bypasses restrictions)
 */
function reactions_is_super_user($user_id)
{
    $user_perms = reactions_get_user_permissions($user_id);
    return ($user_perms['is_super'] == 1);
}

/**
 * Parse post identifiers from input (URLs or IDs)
 * Supports formats: "123", "123,456", "showthread.php?pid=123", full URLs
 * 
 * @param string $input Raw input from user
 * @return array Array of valid post IDs
 */
function reactions_parse_post_identifiers($input)
{
    global $db;
    
    if(empty($input))
    {
        return array();
    }
    
    $pids = array();
    
    // Split by commas and newlines
    $parts = preg_split('/[,\n\r]+/', $input);
    
    foreach($parts as $part)
    {
        $part = trim($part);
        if(empty($part)) continue;
        
        // Check if it's a URL
        if(strpos($part, 'http') === 0 || strpos($part, 'showthread.php') !== false)
        {
            // Extract PID from URL
            // Formats: showthread.php?pid=123, showthread.php?tid=1&pid=123#pid123
            if(preg_match('/[?&]pid=(\d+)/', $part, $matches))
            {
                $pids[] = (int)$matches[1];
            }
            elseif(preg_match('/#pid(\d+)/', $part, $matches))
            {
                $pids[] = (int)$matches[1];
            }
        }
        elseif(is_numeric($part))
        {
            // Direct PID
            $pids[] = (int)$part;
        }
    }
    
    // Remove duplicates and filter out invalid PIDs
    $pids = array_unique($pids);
    $pids = array_filter($pids, function($pid) { return $pid > 0; });
    
    return array_values($pids);
}

/**
 * Get random users from specified usergroups
 * 
 * @param array $usergroup_config Array of [usergroup_id => count]
 * @return array Array of user IDs
 */
function reactions_get_random_users_from_usergroups($usergroup_config)
{
    global $db;
    
    if(empty($usergroup_config))
    {
        return array();
    }
    
    $selected_users = array();
    
    foreach($usergroup_config as $gid => $count)
    {
        $gid = (int)$gid;
        $count = (int)$count;
        
        if($gid <= 0 || $count <= 0) continue;
        
        // Get users from this usergroup
        // Check both primary usergroup and additional usergroups
        $query = $db->query("
            SELECT uid 
            FROM ".$db->table_prefix."users 
            WHERE (usergroup = {$gid} OR CONCAT(',', additionalgroups, ',') LIKE '%,{$gid},%')
            ORDER BY RAND()
            LIMIT {$count}
        ");
        
        while($row = $db->fetch_array($query))
        {
            $selected_users[] = (int)$row['uid'];
        }
    }
    
    // Remove duplicates (in case a user is in multiple selected groups)
    $selected_users = array_unique($selected_users);
    
    return array_values($selected_users);
}

/**
 * Validate bulk reaction assignment
 * 
 * @param array $pids Array of post IDs
 * @param array $uids Array of user IDs
 * @param array $reaction_ids Array of reaction IDs to assign
 * @param bool $check_duplicates Whether to check for duplicate reactions
 * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
 */
function reactions_validate_bulk_assignment($pids, $uids, $reaction_ids, $check_duplicates = false)
{
    global $db, $lang;
    
    if(!isset($lang->reactions_error_no_valid_posts))
    {
        $lang->load("reactions");
    }
    
    $result = array(
        'valid' => true,
        'errors' => array(),
        'warnings' => array(),
        'valid_pids' => array(),
        'valid_uids' => array(),
        'valid_reaction_ids' => array()
    );
    
    // Validate posts exist and are not deleted
    if(!empty($pids))
    {
        $pid_list = implode(',', array_map('intval', $pids));
        $query = $db->simple_select("posts", "pid", "pid IN ({$pid_list}) AND visible != -1");
        
        while($row = $db->fetch_array($query))
        {
            $result['valid_pids'][] = (int)$row['pid'];
        }
        
        $invalid_count = count($pids) - count($result['valid_pids']);
        if($invalid_count > 0)
        {
            $result['warnings'][] = $lang->sprintf($lang->reactions_warn_posts_not_found, $invalid_count);
        }
        
        if(empty($result['valid_pids']))
        {
            $result['errors'][] = $lang->reactions_error_no_valid_posts;
            $result['valid'] = false;
        }
    }
    else
    {
        $result['errors'][] = $lang->reactions_error_no_posts_specified;
        $result['valid'] = false;
    }
    
    // Validate users exist
    if(!empty($uids))
    {
        $uid_list = implode(',', array_map('intval', $uids));
        $query = $db->simple_select("users", "uid", "uid IN ({$uid_list})");
        
        while($row = $db->fetch_array($query))
        {
            $result['valid_uids'][] = (int)$row['uid'];
        }
        
        $invalid_count = count($uids) - count($result['valid_uids']);
        if($invalid_count > 0)
        {
            $result['warnings'][] = $lang->sprintf($lang->reactions_warn_users_not_found, $invalid_count);
        }
        
        if(empty($result['valid_uids']))
        {
            $result['errors'][] = $lang->reactions_error_no_valid_users;
            $result['valid'] = false;
        }
    }
    else
    {
        $result['errors'][] = $lang->reactions_error_no_users_specified;
        $result['valid'] = false;
    }
    
    // Validate reactions exist and are active
    if(!empty($reaction_ids))
    {
        $rid_list = implode(',', array_map('intval', $reaction_ids));
        $query = $db->simple_select("reactions", "reaction_id", "reaction_id IN ({$rid_list}) AND active = 1");
        
        while($row = $db->fetch_array($query))
        {
            $result['valid_reaction_ids'][] = (int)$row['reaction_id'];
        }
        
        $invalid_count = count($reaction_ids) - count($result['valid_reaction_ids']);
        if($invalid_count > 0)
        {
            $result['warnings'][] = $lang->sprintf($lang->reactions_warn_reactions_not_found, $invalid_count);
        }
        
        if(empty($result['valid_reaction_ids']))
        {
            $result['errors'][] = $lang->reactions_error_no_valid_reactions;
            $result['valid'] = false;
        }
    }
    else
    {
        $result['errors'][] = $lang->reactions_error_no_reactions_specified;
        $result['valid'] = false;
    }
    
    // Check for duplicates if requested
    if($check_duplicates && $result['valid'])
    {
        $duplicate_count = 0;
        foreach($result['valid_pids'] as $pid)
        {
            foreach($result['valid_uids'] as $uid)
            {
                foreach($result['valid_reaction_ids'] as $rid)
                {
                    $exists = $db->fetch_field(
                        $db->simple_select(
                            "reaction_content",
                            "COUNT(*) as count",
                            "content_type='post' AND content_id={$pid} AND reaction_user_id={$uid} AND reaction_id={$rid}"
                        ),
                        "count"
                    );
                    
                    if($exists > 0)
                    {
                        $duplicate_count++;
                    }
                }
            }
        }
        
        if($duplicate_count > 0)
        {
            $result['warnings'][] = $lang->sprintf($lang->reactions_warn_duplicates_skipped, $duplicate_count);
        }
    }
    
    return $result;
}

/**
 * Create bulk reactions
 * 
 * @param array $assignments Array of ['pid' => int, 'uid' => int, 'reaction_id' => int]
 * @param bool $skip_duplicates Whether to skip existing reactions
 * @return array ['success_count' => int, 'skipped_count' => int, 'error_count' => int]
 */
function reactions_create_bulk_reactions($assignments, $skip_duplicates = true)
{
    global $db, $mybb;
    
    $result = array(
        'success_count' => 0,
        'skipped_count' => 0,
        'error_count' => 0,
        'affected_posts' => array(),
        'affected_users' => array()
    );
    
    if(empty($assignments))
    {
        return $result;
    }
    
    foreach($assignments as $assignment)
    {
        $pid = (int)$assignment['pid'];
        $uid = (int)$assignment['uid'];
        $reaction_id = (int)$assignment['reaction_id'];
        
        if($pid <= 0 || $uid <= 0 || $reaction_id <= 0)
        {
            $result['error_count']++;
            continue;
        }
        
        // Get post info
        $post = get_post($pid);
        if(!$post)
        {
            $result['error_count']++;
            continue;
        }
        
        $content_user_id = (int)$post['uid'];
        
        // Check if reaction already exists
        if($skip_duplicates)
        {
            $exists = $db->fetch_field(
                $db->simple_select(
                    "reaction_content",
                    "COUNT(*) as count",
                    "content_type='post' AND content_id={$pid} AND reaction_user_id={$uid}"
                ),
                "count"
            );
            
            if($exists > 0)
            {
                $result['skipped_count']++;
                continue;
            }
        }
        
        // Create reaction entry
        $insert_data = array(
            'reaction_id' => $reaction_id,
            'content_type' => 'post',
            'content_id' => $pid,
            'reaction_user_id' => $uid,
            'content_user_id' => $content_user_id,
            'reaction_date' => TIME_NOW,
            'is_counted' => 1
        );
        
        $reaction_content_id = $db->insert_query("reaction_content", $insert_data);
        
        if($reaction_content_id)
        {
            $result['success_count']++;
            $result['affected_posts'][$pid] = $pid;
            $result['affected_users'][$content_user_id] = $content_user_id;
            $result['affected_users'][$uid] = $uid;
        }
        else
        {
            $result['error_count']++;
        }
    }
    
    // Update post reaction data for affected posts
    if(!empty($result['affected_posts']))
    {
        foreach($result['affected_posts'] as $pid)
        {
            reactions_rebuild_content_cache('post', $pid, 0);
        }
    }
    
    // Update user reaction scores for affected users
    if(!empty($result['affected_users']))
    {
        foreach($result['affected_users'] as $uid)
        {
            reactions_update_user_reaction_scores($uid);
        }
    }
    
    return $result;
}

/**
 * Build an inline script that defines the reactionsLang object for reactions.js
 * Must be output BEFORE the reactions.js file is loaded.
 */
function reactions_js_lang_script()
{
    global $lang;

    if(!isset($lang->reactions))
    {
        $lang->load("reactions");
    }

    $lang_json = json_encode(array(
        'reaction_added_success' => $lang->reaction_added_success,
        'reaction_changed_success' => $lang->reaction_changed_success,
        'reaction_removed_success' => $lang->reaction_removed_success,
        'reaction_invalid_response' => $lang->reaction_invalid_response,
        'reactions_overlay_title' => $lang->reactions_overlay_title,
        'reactions_error_prefix' => $lang->reactions_error_prefix,
        'reactions_received_history' => $lang->reactions_received_history,
        'reactions_given_history' => $lang->reactions_given_history,
        'reaction_points_breakdown' => $lang->reaction_points_breakdown,
        'reaction_name_like' => $lang->reaction_name_like,
        'reaction_name_love' => $lang->reaction_name_love,
        'reaction_name_haha' => $lang->reaction_name_haha,
        'reaction_name_wow' => $lang->reaction_name_wow,
        'reaction_name_sad' => $lang->reaction_name_sad,
        'reaction_name_angry' => $lang->reaction_name_angry,
        'reactions_error_overlay_load' => $lang->reactions_error_overlay_load
    ));

    return '<script type="text/javascript">
var reactionsLang = '.$lang_json.';
</script>';
}
