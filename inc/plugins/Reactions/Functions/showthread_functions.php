<?php
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

/**
 * Showthread start hook
 */
function reactions_showthread_start()
{
    // Placeholder for pre-fetching if needed
}

/**
 * Add tooltip template and JavaScript to showthread
 */
function reactions_add_tooltip_template()
{
    global $mybb, $templates, $theme;
    
    if(!$mybb->user['uid'])
    {
        return;
    }
    
    $active_reactions = reactions_get_active(true);
    
    if(count($active_reactions) <= 1)
    {
        return;
    }
    
    // Build tooltip items
    $reactions_tooltip_items = "";
    
    // Try to load template from file first, fallback to database
    $item_template = reactions_load_template('reactions_tooltip_item');
    if(!$item_template)
    {
        $item_template = $templates->get("reactions_tooltip_item");
    }
    
    if(!$item_template)
    {
        // Fallback or handle error
        return;
    }

    foreach($active_reactions as $reaction_id => $reaction)
    {
        $reaction_image = reactions_get_reaction_image($reaction, 'small');
        eval("\$item = \"" . $item_template . "\";");
        $reactions_tooltip_items .= $item;
    }
    
    // Try to load tooltip template from file first, fallback to database
    $tooltip_template = reactions_load_template('reactions_tooltip');
    if(!$tooltip_template)
    {
        $tooltip_template = $templates->get("reactions_tooltip");
    }
    
    if($tooltip_template)
    {
        eval("\$tooltip_html = \"" . $tooltip_template . "\";");
    }
    else
    {
        // Fallback script tag if template missing
        $tooltip_html = '<script type="text/template" id="reactionsTooltipTemplate"><div class="tooltip-content-inner"><div class="reactTooltip">'.$reactions_tooltip_items.'</div></div></script>';
    }
    
    // Get user notification preferences (only if admin allows)
    if(!empty($mybb->settings['reactions_user_notification_prefs']))
    {
        $notification_prefs = reactions_get_user_notification_prefs($mybb->user['uid']);
        $prefs_json = json_encode($notification_prefs);
    }
    else
    {
        // Admin disabled user preferences - pass null to show all notifications
        $prefs_json = 'null';
    }

    // Add JavaScript file directly (Thread Collaboration style)
    $reactions_js_html = '<script type="text/javascript">
// Reactions notification preferences
var reactionsNotifyPrefs = '.$prefs_json.';
</script>
'.reactions_js_lang_script().'
<script type="text/javascript" src="'.$mybb->asset_url.'/inc/plugins/Reactions/Assets/reactions.js"></script>';

    // Add tooltip HTML and JS to page (not headerinclude - this is body content)
    global $reactions_tooltip_html;
    $reactions_tooltip_html = $tooltip_html . $reactions_js_html;
}
