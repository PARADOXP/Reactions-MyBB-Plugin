<?php
$l['reactions'] = "Reactions";
$l['reaction'] = "Reaction";
$l['reactions_received'] = "Reactions Received";
$l['reactions_given'] = "Reactions Given";
$l['reactions_received_history'] = "Reactions Received History";
$l['reactions_given_history'] = "Reactions Given History";
$l['reactions_title'] = "Reactions";
$l['no_reactions_yet'] = "No reactions yet.";
$l['content'] = "Content";
$l['date'] = "Date";
$l['from'] = "From";
$l['to'] = "To";
$l['reactions_score'] = "Reaction Score";
$l['reactions_points'] = "Reaction Points";
$l['reactions_info'] = "Info";
$l['reactions_info_tooltip'] = "View reactions and their point values";
$l['reactions_info_title'] = "Reactions & Points Information";
$l['reactions_info_image'] = "Image";
$l['reactions_info_name'] = "Reaction Name";
$l['reactions_info_points'] = "Points";
$l['reactions_overlay_messages'] = "Messages: ";
$l['reactions_overlay_score'] = "Reaction score: ";
$l['reactions_info_type'] = "Type";
$l['reactions_info_description'] = "About Reactions & Points";
$l['reactions_info_explanation'] = "This page shows all available reactions and their point values. When you receive a reaction, you earn points based on the reaction type. Positive reactions add points, negative reactions subtract points, and neutral reactions don't change your point total.";
$l['reaction_points_breakdown'] = "Reaction Points Breakdown";
$l['reaction_points_description'] = "This shows how your reaction points are calculated based on the reactions you've received.";
$l['points_each'] = "Points Each";
$l['count'] = "Count";
$l['total_points'] = "Total Points";
$l['no_reaction_points_yet'] = "No reaction points earned yet.";

// User Notification Preferences (UserCP)
$l['reactions_notifications_title'] = "Reactions Notifications";
$l['reactions_notifications_desc'] = "Choose which reaction notifications you want to see:";
$l['reactions_notify_disabled'] = "Show 'Reactions are disabled' messages";
$l['reactions_notify_no_permission_use'] = "Show permission denied messages when trying to react";
$l['reactions_notify_no_permission_remove'] = "Show permission denied messages when trying to remove reactions";
$l['reactions_notify_disabled_in_forum'] = "Show 'Reactions disabled in this forum' messages";
$l['reactions_notify_thread_closed'] = "Show 'Cannot react in closed thread' messages";
$l['reactions_notify_usergroup_no_give'] = "Show usergroup permission error messages";
$l['reactions_notify_recipient_no_receive'] = "Show 'Recipient cannot receive reactions' messages";
$l['reactions_notify_self_reaction_not_allowed'] = "Show 'Cannot react to own content' messages";
$l['reactions_notify_min_posts_required'] = "Show minimum posts required messages";
$l['reactions_notify_min_reputation_required'] = "Show minimum reputation required messages";
$l['reactions_notify_daily_limit_reached'] = "Show daily limit reached messages";
$l['reactions_notify_flood_control'] = "Show flood control/wait messages";
$l['reactions_notify_reaction_success'] = "Show 'Reaction added/removed successfully' messages";

// Error messages
$l['reactions_error_no_permission_use'] = "You do not have permission to use reactions.";
$l['reactions_error_no_permission_remove'] = "You do not have permission to remove reactions.";
$l['reactions_error_thread_closed'] = "You cannot react in a closed thread.";
$l['reactions_error_reactions_disabled'] = "Reactions are currently disabled.";
$l['reactions_error_disabled_in_forum'] = "Reactions are disabled in this forum.";
$l['reactions_error_usergroup_no_give'] = "Your usergroup does not have permission to give reactions.";
$l['reactions_error_recipient_no_receive'] = "The recipient cannot receive reactions.";
$l['reactions_error_guests_not_allowed'] = "Guests are not allowed to react.";
$l['reactions_error_self_reaction_not_allowed'] = "You cannot react to your own content.";
$l['reactions_error_min_posts_required'] = "You need at least {1} posts to react.";
$l['reactions_error_min_reputation_required'] = "You need at least {1} reputation to react.";
$l['reactions_error_daily_limit_reached'] = "You have reached your daily limit of {1} reactions.";
$l['reactions_error_flood_control'] = "Please wait {1} more seconds before reacting again.";
$l['reactions_error_edit_time_expired'] = "The time limit to change this reaction has expired.";
$l['reactions_error_missing_parameters'] = "Missing parameters";
$l['reactions_error_not_logged_in'] = "Not logged in";
$l['reactions_error_template_not_found'] = "Reactions template not found. Please ensure the plugin is properly activated.";

// Overlay / tabs
$l['reactions_all'] = "All";
$l['reactions_overlay_title'] = "Members who reacted";

// Profile points breakdown
$l['reactions_total_reactions'] = "Total Reactions";
$l['reactions_unique_types'] = "Unique Types";
$l['reactions_system'] = "System";
$l['reactions_gifted_points'] = "🎁 Gifted Points";
$l['reactions_gifted_points_desc'] = "These reaction points were gifted by an administrator. Not associated with any post.";
$l['reactions_gifted_tag'] = "gifted";
$l['reactions_gifted_points_summary'] = "🎁 Gifted Points Summary";
$l['reactions_gifted_summary_line'] = "{1} gift reaction(s) totaling {2} points";

// Miscellaneous
$l['reactions_error_prefix'] = "Error: ";
$l['reactions_error_overlay_load'] = "Error loading reactions.";
$l['reactions_error_missing_table'] = "The reactions database table is missing. Please reinstall or repair the reactions plugin.";
$l['reactions_error_generic_exception'] = "An unexpected error occurred while processing your reaction. Please try again later.";

// Reaction name fallbacks (used when no reaction title is available)
$l['reaction_name_like'] = "Like";
$l['reaction_name_love'] = "Love";
$l['reaction_name_haha'] = "Haha";
$l['reaction_name_wow'] = "Wow";
$l['reaction_name_sad'] = "Sad";
$l['reaction_name_angry'] = "Angry";


// Reputation Report Integration
$l['reactions_reputation_format'] = "%1\$s has reacted to your %2\$s in %3\$s with %4\$s points with %5\$s %6\$s";
$l['reactions_reputation_post'] = "post";
$l['reactions_this_post'] = "this post";
$l['reactions_admin_gift'] = "as an <strong>admin gift</strong>";
$l['reactions_reputation_in_thread'] = " in {1}";
$l['reactions_reputation_given'] = "{1} has given {2} {3} for {4}";

// UserCP Reactions Settings Page
$l['reactions_settings_title'] = "Reactions Settings";
$l['reactions_settings_updated'] = "Your reactions settings have been updated successfully.";
$l['reactions_notifications_heading'] = "Notification Preferences";
$l['save_reactions_settings'] = "Save Reactions Settings";
$l['reactions_myalerts_heading'] = "Reaction Alerts";
$l['reactions_myalerts_receive'] = "Receive alert when someone reacts to my post";
$l['reactions_myalerts_removed'] = "Receive alert when someone removes their reaction from my post";

// MyAlerts Integration
$l['myalerts_reactions_alert'] = "{1} has reacted with {2}{3} in {4}";
$l['myalerts_reactions_fallback'] = "{1} has reacted to your post";
$l['myalerts_reactions_removed_alert'] = "{1} has removed their {2}{3} reaction from {4}";
$l['myalerts_reactions_removed_fallback'] = "{1} has removed their reaction from your post";
$l['myalerts_setting_reaction'] = "Receive alert when someone reacts to my post?";
$l['myalerts_setting_reaction_removed'] = "Receive alert when someone removes their reaction from my post?";

// Reaction bar text
$l['reactions_you'] = "You";
$l['reactions_you_and_one'] = "You and {1}";
$l['reactions_you_and_two'] = "You, {1} and {2}";
$l['reactions_you_three_one_other'] = "You, {1}, {2} and 1 other person";
$l['reactions_you_three_others'] = "You, {1}, {2} and {3} others";
$l['reactions_two'] = "{1} and {2}";
$l['reactions_three'] = "{1}, {2} and {3}";
$l['reactions_three_one_other'] = "{1}, {2}, {3} and 1 other person";
$l['reactions_three_others'] = "{1}, {2}, {3} and {4} others";

// Reaction type labels
$l['reaction_type_positive'] = "Positive";
$l['reaction_type_neutral'] = "Neutral";
$l['reaction_type_negative'] = "Negative";

// JavaScript notification messages
$l['reaction_added_success'] = "Reaction added successfully!";
$l['reaction_changed_success'] = "Reaction changed successfully!";
$l['reaction_removed_success'] = "Reaction removed successfully!";
$l['reaction_invalid_response'] = "Invalid response from server";
