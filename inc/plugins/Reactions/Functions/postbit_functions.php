<?php
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

/**
 * Display reactions in postbit
 */
function reactions_display_post_reactions(&$post)
{
    global $mybb, $db, $templates, $lang, $theme;

    $post['reactions_bar'] = '';
    $post['reaction_button'] = '';
    $post['author_reactions'] = '';
    
    // Check if reactions are enabled
    if(!($mybb->settings['reactions_enabled'] ?? 0))
    {
        return;
    }

    // Usergroup check for viewing
    $user_perms = reactions_get_user_permissions($mybb->user['uid']);
    if($user_perms && $user_perms['can_view_reactions'] == 0)
    {
        return;
    }
    
    // Check if user can use reactions based on group settings
    if(!reactions_can_use_reactions($mybb->user['uid']))
    {
        // User cannot use reactions, but can still view them if they have permission
        // Continue with viewing but limit button functionality
    }

    // Get post reactions data
    $reactions_data = json_decode((string)$post['reactions'], true);
    $reaction_users = json_decode((string)$post['reaction_users'], true);
    
    if(!is_array($reactions_data))
    {
        $reactions_data = array();
    }
    if(!is_array($reaction_users))
    {
        $reaction_users = array();
    }

    $reactions_count = array_sum($reactions_data);
    
    // Build reactions bar
    $icons_html = "";
    $reactions_text = "";
    $reactions_link = "";
    static $user_reactions_thread = null;
    if($user_reactions_thread === null && $mybb->user['uid'] && isset($mybb->input['tid']))
    {
        $user_reactions_thread = array();
        $tid = (int)$mybb->input['tid'];
        $uid = (int)$mybb->user['uid'];
        
        $query = $db->query("
            SELECT rc.content_id, rc.reaction_id 
            FROM ".$db->table_prefix."reaction_content rc
            INNER JOIN ".$db->table_prefix."posts p ON (rc.content_id = p.pid)
            WHERE rc.content_type='post' AND p.tid=$tid AND rc.reaction_user_id=$uid
        ");
        while($row = $db->fetch_array($query))
        {
            $user_reactions_thread[$row['content_id']] = $row['reaction_id'];
        }
    }
    
    $has_reaction = isset($user_reactions_thread[$post['pid']]);
    $user_reaction_id = $has_reaction ? $user_reactions_thread[$post['pid']] : 1;
    
    // Get default reaction
    $default_reaction = reactions_get_default();
    if(!$default_reaction)
    {
        return;
    }
    
    // Get active reactions
    $active_reactions = reactions_get_active();
    
    if(!empty($reactions_data))
    {
        // Get top 3 reactions
        arsort($reactions_data);
        $top_reactions = array_slice(array_keys($reactions_data), 0, 3);
        
        // Try to load template from file first, fallback to database
        $summary_template = reactions_load_template('reactions_summary_item');
        if(!$summary_template)
        {
            $summary_template = $templates->get("reactions_summary_item");
        }
        
        foreach($top_reactions as $reaction_id)
        {
            $reaction = reactions_get_reaction($reaction_id);
            if($reaction && $reaction['active'])
            {
                $reaction_image = reactions_get_reaction_image($reaction, 'small');
                if($summary_template)
                {
                    $icons_html .= str_replace('{reaction_image}', $reaction_image, $summary_template);
                }
                else
                {
                    $icons_html .= '<li>'.$reaction_image.'</li>';
                }
            }
        }
        
        if($icons_html)
        {
            $icons_html = '<ul class="reactionSummary">'.$icons_html.'</ul>';
        }
        
        // Build reactions text
        // "You, UserA, UserB and 5 others"
        $reactions_text = reactions_format_reactions_text($reactions_count, $reaction_users, $mybb->user['uid']);
        
        // Link to dedicated reactions page - for right-click/open in new tab
        // JavaScript will convert this to xmlhttp.php for AJAX overlay
        $reactions_link = $mybb->settings['bburl']."/reactions.php?content_type=post&content_id=".$post['pid'];
        
        // Try to load template from file first, fallback to database
        $bar_template = reactions_load_template('reactions_bar');
        if(!$bar_template)
        {
            $bar_template = $templates->get("reactions_bar");
        }
        
        if($bar_template)
        {
            // Use eval method for consistency with standard MyBB templates
            $inner_bar = '';
            eval("\$inner_bar = \"" . $bar_template . "\";");
            // Wrap in parent container for layout
            $reactions_bar = '<div class="reaction_bar_parent">'.$inner_bar.'</div>';
        }
        else
        {
            // Fallback - wrap in parent container
            $reactions_bar = '<div class="reaction_bar_parent"><div class="reactionsBar js-reactionsList is-active">
    <a class="reactionsBar-link" href="'.$reactions_link.'" data-xf-click="overlay" data-cache="false" rel="nofollow">
        '.$icons_html.'
        <span class="reactionsBar-text">'.$reactions_text.'</span>
    </a>
</div></div>';
        }
    }
    else
    {
        // Always output the container even if empty, so JS can populate it later
        // Wrap empty bar in parent for consistency
        $reactions_bar = '<div class="reaction_bar_parent"><div class="reactionsBar js-reactionsList"></div></div>';
    }
    
    // Build reaction button (always show, even if no reactions yet)
    $reaction_button = "";
    if(($mybb->user['uid'] || ($mybb->settings['reactions_guest_react'] ?? 0) == 1) && reactions_can_use_reactions($mybb->user['uid']))
    {
        $pid = (int)$post['pid'];
        $rid = (int)$user_reaction_id;
        $bburl = $mybb->settings['bburl'];
        $reaction_link = $bburl."/xmlhttp.php?action=react&pid=".$pid."&reaction_id=".$rid;
        
        $reaction_class = "reaction";
        if($has_reaction)
        {
            $reaction_class .= " has-reaction reaction--".$rid;
        }
        else
        {
            $reaction_class .= " reaction--1";
        }

        $current_reaction = $has_reaction ? reactions_get_reaction($user_reaction_id) : $default_reaction;
        $reaction_image = reactions_get_reaction_image($current_reaction, 'small');
        $reaction_title = htmlspecialchars_uni($current_reaction['title']);

        // Build HTML directly - only escape & in URL, nothing else
        $safe_url = str_replace('&', '&amp;', $reaction_link);
        // Use div instead of <a> tag for better mobile/touch support
        // This avoids hover issues on mobile devices while maintaining click functionality via JS
        // Structure: Icon + Text side by side (XenForo-style)
        $reaction_button = '<div data-reaction-url="'.$safe_url.'" class="actionBar-action actionBar-action--reaction postbit_reaction_add '.$reaction_class.'" data-xf-init="reaction" data-reaction-list=".js-reactionsList" role="button" tabindex="0" title="'.$reaction_title.'">';
        $reaction_button .= '<span class="reaction-button-icon">'.$reaction_image.'</span>';
        $reaction_button .= '<span class="reaction-text js-reactionText" style="color: '.$current_reaction['text_color'].'">';
        $reaction_button .= $reaction_title;
        $reaction_button .= '</span>';
        $reaction_button .= '</div>';
    }
    
    // Add to post array (MyBB will output it as-is in templates)
    $post['reactions_bar'] = $reactions_bar;
    $post['reaction_button'] = $reaction_button;

    // Display reaction scores in postbit if enabled
    if ($mybb->settings['reactions_display_postbit'] ?? 0) {
        // Ensure language file is loaded
        global $lang;
        if (!isset($lang->reactions_received)) {
            $lang->load('reactions');
        }

        // Get user's reaction counts (not points)
        $received_count = reactions_get_user_reaction_count($post['uid']);
        $given_count = reactions_get_user_reaction_given_count($post['uid']);
        
        $post['author_reactions'] = '<div class="author_row author_reactions"><strong>'.$lang->reactions_received.':</strong> ' . $received_count . '</div>';
        $post['author_reactions'] .= '<div class="author_row author_reactions"><strong>'.$lang->reactions_given.':</strong> ' . $given_count . '</div>';
    } else {
        $post['author_reactions'] = "";
    }
}
