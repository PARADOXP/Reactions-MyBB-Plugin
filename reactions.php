<?php
/**
 * MyBB Reactions - Dedicated Reactions List Page
 * Displays who reacted to a specific post in a full page (like XenForo)
 * URL: reactions.php?content_type=post&content_id=123
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'reactions.php');

require_once "./global.php";
require_once MYBB_ROOT."inc/functions.php";

// Load reactions plugin functions
require_once MYBB_ROOT . "inc/plugins/Reactions/Functions/reaction_functions.php";

// Load language
$lang->load("reactions");

// Get parameters
$content_type = $mybb->get_input('content_type');
$content_id = $mybb->get_input('content_id', MyBB::INPUT_INT);
$reaction_id = $mybb->get_input('reaction_id', MyBB::INPUT_INT);
$page = $mybb->get_input('page', MyBB::INPUT_INT);

if($page < 1) $page = 1;
$per_page = 50;

// Validate content type
$valid_types = array('post');
if(!in_array($content_type, $valid_types))
{
    error($lang->reactions_invalid_content_type);
}

// Get content info
$post = null;
$thread = null;
$post_url = '';
$thread_subject = 'Unknown';
$thread_link = '';
$post_author = 'Unknown';
$post_author_link = '#';
$post_position = 0;

if($content_type == 'post')
{
    $query = $db->simple_select("posts", "*", "pid={$content_id}");
    $post = $db->fetch_array($query);
    
    if(!$post)
    {
        error($lang->reactions_post_not_found);
    }
    
    $thread = get_thread($post['tid']);
    $thread_subject = htmlspecialchars_uni($thread['subject']);
    $thread_link = get_thread_link($thread['tid']);
    $post_link = get_post_link($content_id, $post['tid'])."#pid{$content_id}";
    $post_url = $mybb->settings['bburl'].'/'.ltrim($post_link, '/');
    
    // Get post author info
    $post_author_id = (int)$post['uid'];
    if($post_author_id > 0)
    {
        $author_query = $db->simple_select("users", "username, usergroup, displaygroup", "uid={$post_author_id}");
        $author = $db->fetch_array($author_query);
        if($author)
        {
            $post_author = format_name($author['username'], $author['usergroup'], $author['displaygroup']);
            $post_author_link = "member.php?action=profile&uid={$post_author_id}";
        }
        else
        {
            $post_author = "Guest";
        }
    }
    else
    {
        $post_author = "Guest";
    }
    
    // Get post position (1-based index)
    $position_query = $db->query("
        SELECT COUNT(*) as position FROM ".$db->table_prefix."posts 
        WHERE tid = ".(int)$post['tid']." AND pid <= ".(int)$content_id."
    ");
    $position_result = $db->fetch_array($position_query);
    $post_position = (int)$position_result['position'];
}

// Check permissions
if(!reactions_can_view_reactions($mybb->user['uid']))
{
    error($lang->reactions_no_permission_view);
}

// Get reactions data
$reactions_html = reactions_get_overlay_html($content_type, $content_id, $reaction_id, $page, $per_page);

// Get tab summary for pagination
$tab_summary = reactions_get_content_tab_summary($content_type, $content_id);
$total_reactions = $tab_summary[0];

// Base URL for pagination
$base_url = $mybb->settings['bburl']."/reactions.php?content_type={$content_type}&content_id={$content_id}";

// Build pagination
$pages = ceil($total_reactions / $per_page);
$pagination = '';

if($pages > 1)
{
    $pagination = '<div class="pagination">';
    
    if($page > 1)
    {
        $pagination .= '<a href="'.$base_url.($reaction_id ? '&amp;reaction_id='.$reaction_id : '').'&amp;page='.($page-1).'" class="pagination_previous">&laquo; Previous</a> ';
    }
    
    $pagination .= '<span class="pages">Page '.$page.' of '.$pages.'</span>';
    
    if($page < $pages)
    {
        $pagination .= ' <a href="'.$base_url.($reaction_id ? '&amp;reaction_id='.$reaction_id : '').'&amp;page='.($page+1).'" class="pagination_next">Next &raquo;</a>';
    }
    
    $pagination .= '</div>';
}

// Page title
$page_title = "Who reacted to this post";

// Add breadcrumbs
add_breadcrumb(htmlspecialchars_uni($thread_subject), $thread_link);
add_breadcrumb("Post #{$content_id}", $post_link);
add_breadcrumb("Reactions");

// Direct output
echo '<!DOCTYPE html>
<html xml:lang="en" lang="en">
<head>
<title>'.$page_title.' - '.$mybb->settings['bbname'].'</title>
'.$headerinclude.'
<link rel="stylesheet" href="'.$mybb->settings['bburl'].'/inc/plugins/Reactions/Assets/reactions.css" type="text/css" />
<style type="text/css">
.reactions-fullpage-wrapper {
    padding: 20px;
}
.reactions-fullpage {
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 4px;
    overflow: hidden;
}
.reactions-fullpage-header {
    padding: 15px 20px;
    background: #548adc;
    border-bottom: 1px solid #ddd;
}
.reactions-fullpage-title {
    margin: 0 0 10px 0;
    font-size: 16px;
    color: #fff;
}
.reactions-post-info {
    font-size: 13px;
    color: rgba(255,255,255,0.9);
    margin-bottom: 10px;
}
.reactions-post-info a {
    color: #fff;
    text-decoration: none;
}
.reactions-post-info a:hover {
    text-decoration: underline;
}
.reactions-fullpage-content {
    padding: 15px 20px;
}
.reactions-fullpage-footer {
    padding: 15px 20px;
    background: #f5f5f5;
    border-top: 1px solid #ddd;
    text-align: center;
}
.reactions-back-link {
    color: #548adc;
    text-decoration: none;
}
.reactions-back-link:hover {
    text-decoration: underline;
}
</style>
<script type="text/javascript">
var ReactionsFullpage = {
    baseUrl: "'.$mybb->settings['bburl'].'/xmlhttp.php?action=get_reactions",
    contentType: "'.$content_type.'",
    contentId: '.$content_id.',
    
    init: function() {
        var self = this;
        
        // Tab click handler
        $(document).on("click", "#reactions-content .tabs-tab", function(e) {
            e.preventDefault();
            var $tab = $(this);
            if ($tab.hasClass("is-active")) return;
            
            var reactionId = $tab.data("reaction-id") || 0;
            
            // Update active state
            $("#reactions-content .tabs-tab").removeClass("is-active");
            $tab.addClass("is-active");
            
            // Load content via AJAX
            self.loadContent(reactionId, 1);
        });
        
        // Pagination click handler
        $(document).on("click", "#reactions-content .pagination a", function(e) {
            e.preventDefault();
            var href = $(this).attr("href");
            if (!href) return;
            
            // Extract page and reaction_id from URL
            var params = new URLSearchParams(href.split("?")[1]);
            var page = params.get("page") || 1;
            var reactionId = params.get("reaction_id") || 0;
            
            self.loadContent(reactionId, page);
        });
    },
    
    loadContent: function(reactionId, page) {
        var self = this;
        var $content = $("#reactions-content");
        
        // Show loading state
        $content.css("opacity", "0.5");
        
        $.ajax({
            url: self.baseUrl + "&content_type=" + self.contentType + "&content_id=" + self.contentId + "&reaction_id=" + reactionId + "&page=" + page,
            type: "GET",
            dataType: "json",
            success: function(data) {
                if (data && data.html && data.html.content) {
                    $content.html(data.html.content).css("opacity", "1");
                    
                    // Update URL
                    var newUrl = "'.$base_url.'&reaction_id=" + reactionId + "&page=" + page;
                    history.pushState({}, "", newUrl);
                } else if (data && data.error) {
                    $content.html("<div style=\"padding: 20px; color: red;\">" + data.error + "</div>").css("opacity", "1");
                }
            },
            error: function() {
                $content.css("opacity", "1");
            }
        });
    }
};

$(document).ready(function() {
    ReactionsFullpage.init();
});
</script>
</head>
<body>
'.$header.'
<div class="reactions-fullpage-wrapper">
    <div class="reactions-fullpage">
        <div class="reactions-fullpage-header">
            <h1 class="reactions-fullpage-title">'.$page_title.'</h1>
            <div class="reactions-post-info">
                <strong>Thread:</strong> <a href="'.$thread_link.'">'.$thread_subject.'</a> &nbsp;|&nbsp;
                <strong>Post:</strong> <a href="'.$post_link.'">#'.$post_position.'</a> &nbsp;|&nbsp;
                <strong>Author:</strong> <a href="'.$post_author_link.'">'.$post_author.'</a>
            </div>
        </div>
        <div class="reactions-fullpage-content" id="reactions-content">
            '.$reactions_html.'
        </div>
        '.$pagination.'
        <div class="reactions-fullpage-footer">
            <a href="'.$post_url.'" class="reactions-back-link">&laquo; Back to post</a>
        </div>
    </div>
</div>
'.$footer.'
</body>
</html>';
