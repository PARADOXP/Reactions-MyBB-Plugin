<?php
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

/**
 * Build AJAX-compatible pagination for overlay
 * Similar to multipage() but generates links that work with AJAX overlays
 */
function reactions_build_ajax_pagination($total, $per_page, $current_page, $base_url)
{
    if($total <= $per_page)
    {
        return '';
    }
    
    $pages = ceil($total / $per_page);
    $pagination = '<div class="pagination"><span class="pages">Pages:</span> ';
    
    // Previous page link
    if($current_page > 1)
    {
        $prev_page = $current_page - 1;
        $pagination .= '<a href="'.$base_url.'&page='.$prev_page.'" class="pagination_previous">&laquo; Prev</a> ';
    }
    
    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($pages, $current_page + 2);
    
    if($start_page > 1)
    {
        $pagination .= '<a href="'.$base_url.'&page=1" class="pagination_first">1</a> ';
        if($start_page > 2)
        {
            $pagination .= '<span class="pagination_more">...</span> ';
        }
    }
    
    for($i = $start_page; $i <= $end_page; $i++)
    {
        if($i == $current_page)
        {
            $pagination .= '<span class="pagination_current">'.$i.'</span> ';
        }
        else
        {
            $pagination .= '<a href="'.$base_url.'&page='.$i.'" class="pagination_page">'.$i.'</a> ';
        }
    }
    
    if($end_page < $pages)
    {
        if($end_page < $pages - 1)
        {
            $pagination .= '<span class="pagination_more">...</span> ';
        }
        $pagination .= '<a href="'.$base_url.'&page='.$pages.'" class="pagination_last">'.$pages.'</a> ';
    }
    
    // Next page link
    if($current_page < $pages)
    {
        $next_page = $current_page + 1;
        $pagination .= '<a href="'.$base_url.'&page='.$next_page.'" class="pagination_next">Next &raquo;</a> ';
    }
    
    $pagination .= '</div>';
    
    return $pagination;
}

/**
 * Handle AJAX react action
 */
function reactions_handle_ajax_react()
{
    global $mybb, $db, $lang;

    if(!isset($lang->reactions))
    {
        $lang->load("reactions");
    }

    if(!isset($mybb->input['reaction_id']) || !isset($mybb->input['content_type']) || !isset($mybb->input['content_id']))
    {
        echo json_encode(array('error' => $lang->reactions_error_missing_parameters));
        exit;
    }

    $reaction_id = (int)$mybb->input['reaction_id'];
    $content_type = $db->escape_string($mybb->input['content_type']);
    $content_id = (int)$mybb->input['content_id'];
    $user_id = (int)$mybb->user['uid'];

    if(!$user_id)
    {
        echo json_encode(array('error' => $lang->reactions_error_not_logged_in));
        exit;
    }

    // Get content user ID
    $content_user_id = 0;
    if($content_type == 'post')
    {
        $content_user_id = $db->fetch_field($db->simple_select("posts", "uid", "pid=".$content_id), "uid");
    }

    // Check if this is a removal attempt (reaction_id = 0 or same as current reaction)
    $current_reaction = reactions_get_user_reaction($content_type, $content_id, $user_id);
    $is_removal = ($reaction_id == 0 || ($current_reaction && $current_reaction['reaction_id'] == $reaction_id));
    
    if($is_removal)
    {
        // Check if user can remove reactions
        if(!reactions_can_remove_reactions($user_id))
        {
            global $lang;
            if(!isset($lang->reactions))
            {
                $lang->load("reactions");
            }
            reactions_json_response(array('error' => $lang->reactions_error_no_permission_remove, 'notify_type' => 'no_permission_remove'));
        }
    }

    // Perform reaction
    try {
        $result = reactions_react_to_content($reaction_id, $content_type, $content_id, $user_id, $content_user_id);
    }
    catch(Throwable $e)
    {
        reactions_json_response(array('error' => $lang->reactions_error_generic_exception));
        return;
    }
    // Check for error in result
    if(is_array($result) && isset($result['error']))
    {
        $response = array('error' => $result['error']);
        if(!empty($result['notify_type']))
        {
            $response['notify_type'] = $result['notify_type'];
        }
        reactions_json_response($response);
    }

    // Get updated reaction data
    $user_reaction = reactions_get_user_reaction($content_type, $content_id, $user_id);
    // Get updated author information
    $author_info_html = '';
    if($content_type == 'post')
    {
        // Get post author ID
        $post_author_id = $db->fetch_field($db->simple_select("posts", "uid", "pid=".$content_id), "uid");
        if($post_author_id)
        {
            // Get updated reaction counts
            $received_count = reactions_get_user_reaction_count($post_author_id);
            $given_count = reactions_get_user_reaction_given_count($post_author_id);
            
            global $lang;
            if(!isset($lang->reactions))
            {
                $lang->load("reactions");
            }
            
            $author_info_html = '<div class="author_row author_reactions"><strong>'.$lang->reactions_received.':</strong> ' . $received_count . '</div>';
            $author_info_html .= '<div class="author_row author_reactions"><strong>'.$lang->reactions_given.':</strong> ' . $given_count . '</div>';
        }
    }

    // Build response
    $response = array(
        'reactionId' => $user_reaction ? $user_reaction['reaction_id'] : null,
        'linkReactionId' => $user_reaction ? $user_reaction['reaction_id'] : 1,
        'html' => array(
            'button' => reactions_build_button_html($content_type, $content_id),
            'content' => reactions_build_reactions_bar_html($content_type, $content_id),
            'author_info' => $author_info_html
        )
    );

    reactions_json_response($response);
}

/**
 * Handle AJAX get reactions list
 */
function reactions_handle_ajax_get_reactions()
{
    global $mybb, $lang;

    if(!isset($lang->reactions))
    {
        $lang->load("reactions");
    }

    if(!isset($mybb->input['content_type']) || !isset($mybb->input['content_id']))
    {
        echo json_encode(array('error' => $lang->reactions_error_missing_parameters));
        return;
    }

    $content_type = $mybb->input['content_type'];
    $content_id = (int)$mybb->input['content_id'];
    $reaction_id = isset($mybb->input['reaction_id']) ? (int)$mybb->input['reaction_id'] : null;
    $page = isset($mybb->input['page']) ? (int)$mybb->input['page'] : 1;

    $html = reactions_get_overlay_html($content_type, $content_id, $reaction_id, $page);

    reactions_json_response(array('html' => array('content' => $html)));
}

/**
 * Handle AJAX get history
 */
function reactions_handle_ajax_get_history()
{
    global $mybb, $db, $lang, $templates, $theme, $parser;

    if(!isset($lang->reactions))
    {
        $lang->load("reactions");
    }

    if(!isset($mybb->input['uid']) || !isset($mybb->input['type']))
    {
        reactions_json_response(array('error' => $lang->reactions_error_missing_parameters));
        return;
    }

    require_once MYBB_ROOT . "inc/functions_post.php";

    $uid = (int)$mybb->input['uid'];
    $type = $mybb->input['type']; // 'received' or 'given'
    $page = isset($mybb->input['page']) ? (int)$mybb->input['page'] : 1;
    $per_page = 20;

    $where = "";
    $user_label = "";
    $history_title = "";

    if($type == 'received')
    {
        $where = "rc.content_user_id = $uid";
        $user_label = $lang->from;
        $history_title = $lang->reactions_received_history;
    }
    else
    {
        $where = "rc.reaction_user_id = $uid";
        $user_label = $lang->to;
        $history_title = $lang->reactions_given_history;
    }

    // Count total
    $total = $db->fetch_field($db->simple_select("reaction_content rc", "COUNT(*) as count", $where), "count");

    // Build custom pagination for AJAX overlay
    $multipage = reactions_build_ajax_pagination($total, $per_page, $page, $mybb->settings['bburl']."/xmlhttp.php?action=get_history&uid=$uid&type=$type");

    // Query
    $start = ($page - 1) * $per_page;
    $query = $db->query("
        SELECT rc.*, r.title as reaction_name, r.image_url as reaction_image, 
               u.uid as user_uid, u.username, u.usergroup, u.displaygroup, u.avatar,
               p.pid, p.subject as post_subject, p.message as post_message,
               t.tid, t.subject as thread_subject
        FROM ".$db->table_prefix."reaction_content rc
        INNER JOIN ".$db->table_prefix."reactions r ON (rc.reaction_id = r.reaction_id)
        LEFT JOIN ".$db->table_prefix."users u ON (".($type == 'received' ? 'rc.reaction_user_id' : 'rc.content_user_id')." = u.uid)
        LEFT JOIN ".$db->table_prefix."posts p ON (rc.content_type = 'post' AND rc.content_id = p.pid)
        LEFT JOIN ".$db->table_prefix."threads t ON (p.tid = t.tid)
        WHERE $where
        ORDER BY rc.reaction_date DESC
        LIMIT $start, $per_page
    ");

    $history_rows = "";
    while($row = $db->fetch_array($query))
    {
        $alt_bg = alt_trow();
        
        // Handle missing user data (e.g., admin gifts with no valid reaction_user_id)
        if(empty($row['username']))
        {
            $row['username'] = $lang->reactions_system;
            $row['usergroup'] = 0;
            $row['displaygroup'] = 0;
            $row['user_uid'] = 0;
            $row['avatar'] = '';
        }
        
        // Format user
        $formatted_user = build_profile_link(format_name($row['username'], $row['usergroup'], $row['displaygroup']), $row['user_uid']);
        
        // Format avatar
        $user_avatar = $row['avatar'];
        if(!$user_avatar)
        {
            $user_avatar = $theme['imgdir'] . "/default_avatar.png";
        }
        if(!strpos($user_avatar, '://'))
        {
            $user_avatar = $mybb->settings['bburl'] . '/' . $user_avatar;
        }
        
        // Format reaction
        $reaction = array(
            'name' => $row['reaction_name'],
            'image' => $row['reaction_image']
        );
        
        // Content info - handle admin gifts (no post associated)
        if($row['content_type'] == 'admin_gift')
        {
            $content_link = '#';
            $content_title = '<span style="color: #2ecc71; font-weight: bold;">'.htmlspecialchars_uni($lang->reactions_gifted_points).'</span>';
            $content_preview = '<em>'.htmlspecialchars_uni($lang->reactions_gifted_points_desc).'</em>';
        }
        else
        {
            $content_link = get_post_link($row['pid'], $row['tid']) . "#pid" . $row['pid'];
            $content_title = $row['thread_subject'];
            $preview_message = $row['post_message'];
            if(isset($parser) && is_object($parser))
            {
                $preview_message = $parser->parse_message($preview_message);
            }
            $content_preview = my_substr(strip_tags($preview_message), 0, 100) . "...";
        }
        
        // Date
        $formatted_date = my_date($mybb->settings['dateformat'], $row['reaction_date']) . " " . my_date($mybb->settings['timeformat'], $row['reaction_date']);

        $row_template = reactions_load_template("reactions_profile_history_row");
        eval("\$history_rows .= \"".$row_template."\";");
    }

    if(!$history_rows)
    {
        $history_rows = "<div class='reactions-history-empty'>".$lang->no_reactions_yet."</div>";
    }

    $history_template = reactions_load_template("reactions_profile_history");
    $html = str_replace('{$history_rows}', $history_rows, $history_template);
    $html = str_replace('{$multipage}', $multipage, $html);

    reactions_json_response(array('html' => array('content' => $html)));
}

/**
 * Handle AJAX get points breakdown
 */
function reactions_handle_ajax_get_points()
{
    global $mybb, $db, $lang, $templates, $theme;

    if(!isset($mybb->input['uid']))
    {
        reactions_json_response(array('error' => $lang->reactions_error_missing_parameters));
        return;
    }

    $uid = (int)$mybb->input['uid'];

    if(!isset($lang->reactions))
    {
        $lang->load("reactions");
    }

    // Get reaction points breakdown by reaction type
    $query = $db->query("
        SELECT r.reaction_id, r.title as reaction_name, r.image_url as reaction_image, 
               r.reaction_score as reaction_points, COUNT(*) as count,
               (r.reaction_score * COUNT(*)) as total_points,
               rc.content_type
        FROM ".$db->table_prefix."reaction_content rc
        INNER JOIN ".$db->table_prefix."reactions r ON (rc.reaction_id = r.reaction_id)
        WHERE rc.content_user_id = $uid AND rc.is_counted = 1 AND r.active = 1
        GROUP BY r.reaction_id, r.title, r.image_url, r.reaction_score, rc.content_type
        ORDER BY total_points DESC
    ");

    $points_rows = "";
    $total_points = 0;
    $total_reactions = 0;
    $unique_reactions = 0;
    $admin_gift_points = 0;
    $admin_gift_count = 0;
    
    while($row = $db->fetch_array($query))
    {
        $alt_bg = alt_trow();
        $reaction_id = $row['reaction_id'];
        $reaction_name = $row['reaction_name'];
        $reaction_image = $mybb->settings['bburl'] . '/' . $row['reaction_image'];
        $reaction_points = $row['reaction_points'];
        $count = $row['count'];
        $total_points += $row['total_points'];
        $total_reactions += $count;
        $unique_reactions++;
        
        // Track admin gift stats
        if($row['content_type'] == 'admin_gift')
        {
            $admin_gift_points += $row['total_points'];
            $admin_gift_count += $count;
        }
        
        // Add indicator for admin gifts (use single quotes to avoid conflicts with template escaping)
        $gift_indicator = ($row['content_type'] == 'admin_gift') ? ' <span style=\'color: #2ecc71; font-size: 11px;\'>('.htmlspecialchars_uni($lang->reactions_gifted_tag).')</span>' : '';
        $reaction_display_name = $reaction_name . $gift_indicator;
        
        $row_template = reactions_load_template("reactions_profile_points_row");
        eval("\$points_rows .= \"".$row_template."\";");
    }

    // Add admin gift summary row if there are gifted points
    if($admin_gift_count > 0)
    {
        $gift_summary_row = "
        <tr style='background-color: #e8f8f5;'>
            <td class='trow1' colspan='2' style='text-align: center; padding: 10px;'>
                <span style='color: #27ae60; font-weight: bold;'>".htmlspecialchars_uni($lang->reactions_gifted_points_summary)."</span><br>
                <small style='color: #666;'>".$lang->sprintf($lang->reactions_gifted_summary_line, $admin_gift_count, $admin_gift_points)."</small>
            </td>
        </tr>";
        $points_rows = $gift_summary_row . $points_rows;
    }
    
    if(!$points_rows || $points_rows == $gift_summary_row)
    {
        $points_rows = "<div class='empty-state'>
            <div class='empty-state-icon'>📊</div>
            <div class='empty-state-text'>".$lang->no_reaction_points_yet."</div>
        </div>";
    }

    $points_template = reactions_load_template("reactions_profile_points");
    eval("\$html = \"".$points_template."\";");

    reactions_json_response(array('html' => array('content' => $html)));
}



/**
 * Build reaction button HTML
 */
function reactions_build_button_html($content_type, $content_id)
{
    global $mybb, $db, $templates, $lang;

    if(!isset($lang->reactions))
    {
        $lang->load("reactions");
    }

    $user_reaction = reactions_get_user_reaction($content_type, $content_id, $mybb->user['uid']);
    $reaction_id = $user_reaction ? $user_reaction['reaction_id'] : 0;
    $reaction = $reaction_id ? reactions_get_reaction($reaction_id) : reactions_get_default();

    $button_classes = 'actionBar-action actionBar-action--reaction';
    if($reaction_id)
    {
        $button_classes .= ' has-reaction reaction--'.$reaction_id;
    }
    else
    {
        $button_classes .= ' reaction--1';
    }

    $reaction_image = $reaction ? '<img src="'.$mybb->settings['bburl'].'/'.$reaction['image_url'].'" alt="'.$reaction['title'].'" class="reaction-image js-reaction">' : '';
    $reaction_text = $reaction ? $reaction['title'] : $lang->reaction_name_like;
    $text_color = $reaction ? $reaction['text_color'] : '#0084FF';

    $href = $mybb->settings['bburl'].'/xmlhttp.php?action=react&reaction_id='.($reaction_id ?: 1).'&content_type='.$content_type.'&content_id='.$content_id;

    // Set variables for template
    $reaction_id_var = $reaction_id ?: 1;

    // Use dynamic template loading
    $button_tpl = reactions_load_template("reactions_button");
    if(!$button_tpl)
    {
        $button_tpl = $templates->get("reactions_button");
    }

    if($button_tpl)
    {
        $button_html = '';
        eval("\$button_html = \"" . $button_tpl . "\";");
        return $button_html;
    }

    // Fallback - XenForo-style: icon + text side by side
    return '<div class="'.$button_classes.'" data-reaction-url="'.$href.'" data-xf-init="reaction" data-reaction-id="'.$reaction_id.'" role="button" tabindex="0">
        <span class="reaction-button-icon">'.$reaction_image.'</span>
        <span class="js-reactionText reaction-text" style="color: '.$text_color.'">
            '.$reaction_text.'
        </span>
    </div>';
}

/**
 * Build reactions bar HTML
 */
function reactions_build_reactions_bar_html($content_type, $content_id)
{
    global $mybb, $db, $templates;

    $tab_summary = reactions_get_content_tab_summary($content_type, $content_id);

    if($tab_summary[0] == 0)
    {
        return '<div class="reactionsBar js-reactionsList"></div>';
    }

    // Get recent users
    $recent_query = $db->query("
        SELECT u.uid, u.username, rc.reaction_id
        FROM ".$db->table_prefix."reaction_content rc
        INNER JOIN ".$db->table_prefix."users u ON (rc.reaction_user_id = u.uid)
        INNER JOIN ".$db->table_prefix."reactions r ON (rc.reaction_id = r.reaction_id)
        WHERE rc.content_type = '".$db->escape_string($content_type)."'
        AND rc.content_id = ".(int)$content_id."
        AND r.active = 1
        ORDER BY rc.reaction_date DESC
        LIMIT 5
    ");

    $recent_users = array();
    while($row = $db->fetch_array($recent_query))
    {
        $recent_users[] = array(
            'user_id' => $row['uid'],
            'username' => $row['username'],
            'reaction_id' => $row['reaction_id']
        );
    }

    // Build summary icons
    $icons_html = '';
    arsort($tab_summary);
    $top_icons = 0;
    foreach($tab_summary as $rid => $count)
    {
        if($rid == 0 || $top_icons >= 3) continue;
        $reaction = reactions_get_reaction($rid);
        if($reaction)
        {
            $icons_html .= '<li>'.reactions_get_reaction_image($reaction, 'small').'</li>';
            $top_icons++;
        }
    }
    
    if($icons_html)
    {
        $icons_html = '<ul class="reactionSummary">'.$icons_html.'</ul>';
    }

    // Get formatted text (e.g. "You, John and 5 others")
    $type_info = reactions_get_content_type_info($content_type);
    $table = $type_info ? $type_info['table'] : "posts";
    $id_col = $type_info ? $type_info['id_column'] : "pid";

    $reaction_users_raw = $db->fetch_field($db->simple_select($table, "reaction_users", "$id_col=".(int)$content_id), "reaction_users");
    $reaction_users = json_decode($reaction_users_raw, true);
    if(!is_array($reaction_users)) $reaction_users = array();
    
    $reactions_text = reactions_format_reactions_text($tab_summary[0], $reaction_users, $mybb->user['uid']);
    
    // Link to dedicated reactions page - for right-click/open in new tab
    $reactions_link = $mybb->settings['bburl'].'/reactions.php?content_type='.$content_type.'&content_id='.$content_id;
    
    // AJAX endpoint for overlay loading (used by JS)
    $ajax_link = $mybb->settings['bburl'].'/xmlhttp.php?action=get_reactions&content_type='.$content_type.'&content_id='.$content_id;

    // Use dynamic template loading (template should NOT include reaction_bar_parent wrapper)
    $reactions_bar_tpl = reactions_load_template("reactions_bar");
    if(!$reactions_bar_tpl)
    {
        $reactions_bar_tpl = $templates->get("reactions_bar");
    }

    if($reactions_bar_tpl)
    {
        $reactions_bar_html = '';
        eval("\$reactions_bar_html = \"" . $reactions_bar_tpl . "\";");
        return $reactions_bar_html;
    }

    // Fallback - just the inner bar, no parent wrapper
    return '<div class="reactionsBar js-reactionsList is-active">
        <a href="'.$reactions_link.'" class="reactionsBar-link" data-xf-click="overlay">
            '.$icons_html.'
            <span class="reactionsBar-text">'.$reactions_text.'</span>
        </a>
    </div>';
}

/**
 * JSON response helper
 */
function reactions_json_response($data, $status_code = 200)
{
    // Clean all buffers to ensure NO accidental output (notices, etc.) is sent
    while(ob_get_level())
    {
        ob_end_clean();
    }

    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
