<?php
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

// Router for reactions admin interface
// Routes requests to the appropriate handler file based on action parameter

$action = $mybb->input['action'] ?? '';

// Route to appropriate handler
if($action == 'give_points')
{
    // Give Reaction Points functionality
    require_once MYBB_ROOT . "inc/plugins/Reactions/admin/give_points.php";
}
elseif($action == 'give_reactions')
{
    // Give Reactions to Posts functionality
    require_once MYBB_ROOT . "inc/plugins/Reactions/admin/give_reactions.php";
}
else
{
    // Default: Manage Reactions (add/edit/delete/list)
    // Handles actions: add, edit, delete, and default (list)
    require_once MYBB_ROOT . "inc/plugins/Reactions/admin/manage_reactions.php";
}
