<?php
/**
 * MyBB Reactions Plugin - Admin Language File
 */

// Recount & Rebuild
$l['reactions_recount_title'] = "Reactions";
$l['reactions_recount_users'] = "Recount User Reactions";
$l['reactions_recount_users_desc'] = "Recalculates the reaction scores for each user based on the reactions received. This updates the reaction_score and reaction_given_score fields.";
$l['reactions_recount_posts'] = "Recount Post Reactions";
$l['reactions_recount_posts_desc'] = "Recalculates the reaction counts and scores for each post. This updates the reaction_score, reactions, and reaction_users fields.";
$l['success_recount_reactions_users'] = "User reaction scores have been recounted successfully.";
$l['success_recount_reactions_posts'] = "Post reaction scores have been recounted successfully.";

// Admin Settings descriptions
$l['reactions_user_notification_prefs'] = "Allow User Notification Preferences";
$l['reactions_user_notification_prefs_desc'] = "If enabled, users will see notification preference options in their UserCP to control which reaction notifications they receive.";
$l['reactions_reputation_allow_negative'] = "Allow Negative Reactions in Reputation";
$l['reactions_reputation_allow_negative_desc'] = "If enabled, negative reaction scores (e.g., Angry = -1) will subtract from the user's reputation. If disabled, only positive and neutral reactions will count towards reputation.";
$l['reactions_super_groups'] = "Super User Groups";
$l['reactions_super_groups_desc'] = "Select user groups that have super privileges for reactions. These groups bypass all restrictions including: daily limits, minimum posts/reputation requirements, excluded forums, self-reaction restrictions, and time limits for editing/removing reactions.";

// User Group Settings
$l['reactions_use_groups'] = "Restrict by User Groups";
$l['reactions_use_groups_desc'] = "If enabled, only selected user groups can use reactions. If disabled, all registered users can use reactions. Guests are usergroup 1.";
$l['reactions_use_groups_list'] = "User Groups Allowed to React";
$l['reactions_use_groups_list_desc'] = "Select which user groups can give reactions. Leave empty to allow all groups. Note: Guests are usergroup 1 (Guests) - include them to allow guest reactions.";
$l['reactions_remove_groups'] = "Allow Removal by User Groups";
$l['reactions_remove_groups_desc'] = "If enabled, only selected user groups can remove reactions after reacting. If disabled, all users can remove their own reactions.";
$l['reactions_remove_groups_list'] = "User Groups Allowed to Remove";
$l['reactions_remove_groups_list_desc'] = "Select which user groups can remove reactions. Leave empty to allow all groups.";


// Convert Reactions to Reputation
$l['reactions_convert_reputation'] = "Convert Reactions to Reputation";
$l['reactions_convert_reputation_desc'] = "Creates individual reputation entries for all existing reactions. This enables the detailed reputation report showing who reacted to what content. Only needed once when enabling reputation integration.";
$l['success_convert_reactions_reputation'] = "Reactions have been converted to reputation entries successfully.";

// Initialize Reaction Removed Alert
$l['reactions_init_removed_alert'] = "Initialize 'Reaction Removed' Alert";
$l['reactions_init_removed_alert_desc'] = "Adds the 'reaction removed' alert to existing users' disabled alert list so it appears unchecked by default. Run this after updating the plugin if existing users see this alert checked when it should be unchecked.";
$l['success_init_removed_alert'] = "The 'reaction removed' alert has been initialized for existing users successfully.";


// MyAlerts Integration
$l['reactions_integrate_myalerts'] = "Integrate with MyAlerts";
$l['reactions_integrate_myalerts_desc'] = "Send MyAlerts notifications when someone reacts to a user's post. Requires the MyAlerts plugin to be installed and activated.";
$l['reactions_myalerts_not_installed'] = "MyAlerts plugin is not installed. Please install MyAlerts first to enable this feature.";
$l['reactions_myalerts_install_link'] = "Install MyAlerts from MyBB Mods";

// Give Reaction Points
$l['reactions_give_points'] = "Give Reaction Points";
$l['reactions_give_points_desc'] = "Give reaction points and scores to specific users. You can increase or decrease user reaction points.";
$l['reactions_user_ids'] = "User ID(s)";
$l['reactions_user_ids_desc'] = "Enter user ID(s), separated by commas. Example: 1, 5, 10";
$l['reactions_points_to_give'] = "Reaction Points";
$l['reactions_points_to_give_desc'] = "The total reaction points to give to the user(s). This is what will be displayed on their profile.";
$l['reactions_score'] = "Reaction Score";
$l['reactions_score_desc'] = "The actual score added to the user's profile. If different from points, the system will calculate which reaction images to use.";
$l['reactions_same_as_points'] = "Same as Points";
$l['reactions_reaction_image'] = "Reaction Image";
$l['reactions_reaction_image_desc'] = "Select which reaction image to use, or choose 'Shuffle' for variety. Images with 1 point are recommended for accurate display.";
$l['reactions_shuffle'] = "Shuffle (Multiple reaction types)";
$l['reactions_shuffle_type'] = "Shuffle Type";
$l['reactions_shuffle_type_desc'] = "Select which types of reactions to include in the shuffle.";
$l['reactions_shuffle_positive'] = "Positive reactions only";
$l['reactions_shuffle_mixed'] = "Positive and negative mixed";
$l['reactions_one_point_hint'] = "Reactions with 1 point (recommended)";
$l['reactions_validation_valid'] = "Valid combination";
$l['reactions_validation_invalid'] = "Invalid combination";
$l['reactions_success_give_points'] = "Successfully gave {1} reaction points (score: {2}) using {4} reaction(s) to {3} user(s).";

// Give Reaction Points - Form
$l['reactions_give_points_title'] = "Give Reaction Points";
$l['reactions_give_points_tab_desc'] = "Give reaction points and scores to users";
$l['reactions_error_invalid_user_ids'] = "Please enter valid user ID(s).";
$l['reactions_error_points_must_be_positive'] = "Reaction points must be greater than 0.";
$l['reactions_error_no_shuffle_reactions'] = "No reactions available for shuffle with selected filter.";
$l['reactions_error_no_valid_reactions'] = "No valid reactions selected.";
$l['reactions_error_score_not_achievable'] = "For showing {1} points, score {2} is not achievable. Suggested valid scores: {3}";
$l['reactions_same_as_points_checkbox'] = "Use same value for Reaction Score (Number of Reactions received)";
$l['reactions_reaction_points_form_desc'] = "The sum of reaction point values to add to the user's total. For example, if Love=2 points and Angry=-1 point, giving both would result in 1 Reaction Point (2 + -1 = 1). This affects the user's reaction point total.";
$l['reactions_reaction_score_form_desc'] = "The NUMBER of reactions the user will receive (the count). For example, if you want to give 50 reactions total, enter 50. This affects the user's reaction score count, not the point total.";
$l['reactions_submit_give_points'] = "Give Reaction Points";

// Give Reaction Points - Validation (JavaScript)
$l['reactions_js_no_reactions_shuffle'] = "No reactions available for shuffle.";
$l['reactions_js_will_create_points'] = "Will create {1} reactions with total sum of {2} points (average 1 point per reaction).";
$l['reactions_js_will_create_selected_type'] = "Will create {1} reactions with the selected reaction type.";
$l['reactions_js_enter_custom_score'] = "Enter a custom score value (number of reactions).";
$l['reactions_js_score_max'] = "Score (number of reactions) cannot exceed 10000.";
$l['reactions_js_no_positive_reactions'] = "No positive reactions available.";
$l['reactions_js_score_not_achievable'] = "For showing {1} points, score {2} is not achievable.";
$l['reactions_js_suggested_scores'] = "Suggested valid scores: {1}";
$l['reactions_js_cannot_achieve_points'] = "Cannot achieve {1} points with any number of reactions.";
$l['reactions_js_will_create_avg'] = "Will create {1} reactions with total sum of {2} points (average {3} per reaction).";
$l['reactions_js_exact_not_achievable'] = "Exact combination not achievable. Will get close to {1} points.";
$l['reactions_js_no_reactions_available'] = "No reactions available.";
$l['reactions_js_will_create_avg_mixed'] = "Will create {1} reactions with total sum of {2} points (average {3} per reaction) using mixed positive and neutral reactions.";
$l['reactions_js_will_create_type'] = "Will create {1} reactions of type &quot;{2}&quot; for total of {3} points.";
$l['reactions_js_selected_reaction_warning'] = "Selected reaction gives {1} points each. {2} reactions will give {3} points (requested: {4}).";
$l['reactions_js_please_select_reaction'] = "Please select a reaction type.";
$l['reactions_js_will_create_points_simple'] = "Will create {1} reactions with total sum of {2} points.";
$l['reactions_js_enter_custom_score_above'] = "Enter a custom score value above";

// Give Reactions to Posts
$l['reactions_give_reactions_title'] = "Give Reactions to Posts";
$l['reactions_give_reactions_tab_desc'] = "Bulk assign reactions to posts from selected users";
$l['reactions_error_invalid_post_ids'] = "Please enter valid post ID(s) or URLs.";
$l['reactions_error_select_usergroup'] = "Please select at least one usergroup with a count greater than 0.";
$l['reactions_error_no_valid_users'] = "No valid users found.";
$l['reactions_error_specify_reaction_count'] = "Please specify at least one reaction count.";
$l['reactions_error_total_reactions'] = "Total reactions must be greater than 0. Please ensure you have selected valid users.";
$l['reactions_error_no_active_reactions'] = "No active reactions found.";
$l['reactions_error_no_positive_reactions'] = "No positive reactions available.";
$l['reactions_error_no_positive_neutral_reactions'] = "No positive or neutral reactions available.";
$l['reactions_success_created_reactions'] = "Successfully created {1} reaction(s). ";
$l['reactions_success_skipped_duplicates'] = "{1} duplicate(s) or user limit reached. ";
$l['reactions_success_errors_occurred'] = "{1} error(s) occurred.";
$l['reactions_success_note_users'] = " Note: You specified {1} reactions but only had {2} unique users. Only {2} reactions could be given per post.";
$l['reactions_form_post_selection'] = "Post Selection";
$l['reactions_post_urls_or_ids'] = "Post URLs or IDs";
$l['reactions_post_urls_or_ids_desc'] = "Enter post IDs or URLs, separated by commas or newlines. Examples: 123, 456 or http://localhost/mybb/showthread.php?pid=123";
$l['reactions_form_user_selection'] = "User Selection";
$l['reactions_user_mode_manual'] = "Manual User IDs";
$l['reactions_user_mode_random'] = "Random Users from Usergroups";
$l['reactions_selected_usergroups'] = "Selected Usergroups";
$l['reactions_available_usergroups'] = "Available Usergroups";
$l['reactions_form_reaction_distribution'] = "Reaction Distribution";
$l['reactions_advanced_options_title'] = "Advanced Options - Individual Post Reaction Counts";
$l['reactions_advanced_options_desc'] = "Set custom reaction counts for each post individually. If you leave these empty, the system will use all available users.";
$l['reactions_show_advanced_options'] = "Show Advanced Options (Set reactions per post)";
$l['reactions_hide_advanced_options'] = "Hide Advanced Options";
$l['reactions_random_distribution'] = "Random Distribution";
$l['reactions_random_type_positive'] = "Positive reactions only";
$l['reactions_random_type_positive_neutral'] = "Positive + Neutral reactions";
$l['reactions_random_type_all'] = "All reactions (including negative)";
$l['reactions_manual_distribution'] = "Manual Distribution (specify count for each reaction)";
$l['reactions_manual_reactions_suffix'] = " reactions";
$l['reactions_submit_give_reactions'] = "Give Reactions to Posts";

// Give Reactions to Posts - Validation (JavaScript)
$l['reactions_js_total_matches'] = "Total matches: {1} reactions";
$l['reactions_js_exceeds_by'] = "Exceeds by {1} reaction(s). Total: {2}, Target: {3}";
$l['reactions_js_short_by'] = "Short by {1} reaction(s). Total: {2}, Target: {3}";
$l['reactions_js_user_counts_per_group'] = "User Counts per Group:";
$l['reactions_js_users_suffix'] = " users";
$l['reactions_js_total_available_users'] = "Total Available Users:";
$l['reactions_js_each_post_can_have'] = "Each post can have up to {1} reactions (one per user).";
$l['reactions_js_no_valid_post_ids'] = "No valid post IDs found. Please enter post IDs or URLs in the Post Selection field above.";
$l['reactions_js_post_id_label'] = "Post ID {1}:";
$l['reactions_js_auto_placeholder'] = "Auto (use all users)";
$l['reactions_js_no_users_selected'] = "No users selected";
$l['reactions_js_exceeds_available_users'] = "Exceeds available users ({1})";
$l['reactions_js_valid'] = "Valid";
$l['reactions_js_reactions_suffix'] = " reactions";

// Give Reactions to Posts - Bulk validation (server-side)
$l['reactions_error_no_posts_specified'] = "No posts specified.";
$l['reactions_error_no_valid_posts'] = "No valid posts found.";
$l['reactions_error_no_users_specified'] = "No users specified.";
$l['reactions_error_no_reactions_specified'] = "No reactions specified.";
$l['reactions_error_no_valid_reactions'] = "No valid reactions found.";
$l['reactions_warn_posts_not_found'] = "{1} post(s) not found or deleted.";
$l['reactions_warn_users_not_found'] = "{1} user(s) not found.";
$l['reactions_warn_reactions_not_found'] = "{1} reaction(s) not found or inactive.";
$l['reactions_warn_duplicates_skipped'] = "{1} duplicate reaction(s) will be skipped.";
$l['reactions_validate_only'] = "Validate only (dry run)";
$l['reactions_validate_only_desc'] = "Check the selected posts, users and reactions for validity and show warnings without creating any reactions.";
$l['reactions_validate_passed'] = "Validation passed: {1} post(s), {2} user(s) and {3} reaction type(s) are valid.";
$l['reactions_validate_blocked'] = "Validation failed. No reactions were created. {1}";

// Manage Reactions
$l['reactions_success_added'] = "Reaction added successfully.";
$l['reactions_success_updated'] = "Reaction updated successfully.";
$l['reactions_error_invalid_reaction'] = "Invalid reaction.";
$l['reactions_error_cannot_delete_default'] = "Cannot delete the default Like reaction.";
$l['reactions_success_deleted'] = "Reaction deleted successfully.";
$l['reactions_manage_title'] = "Manage Reactions";
$l['reactions_manage_title_desc'] = "Manage reaction types and settings";
$l['reactions_add_reaction'] = "Add Reaction";
$l['reactions_edit_reaction'] = "Edit Reaction";
$l['reactions_edit_reaction_desc'] = "Edit reaction: {1}";
$l['reactions_add_reaction_form'] = "Add Reaction";
$l['reactions_edit_reaction_form'] = "Edit Reaction: {1}";
$l['reactions_field_title'] = "Title";
$l['reactions_field_title_desc'] = "The name of this reaction";
$l['reactions_field_text_color'] = "Text Color";
$l['reactions_field_text_color_desc'] = "Hex color code for this reaction (e.g. #0084FF)";
$l['reactions_type_positive'] = "Positive (+1)";
$l['reactions_type_neutral'] = "Neutral (0)";
$l['reactions_type_negative'] = "Negative (-1)";
$l['reactions_field_points_type'] = "Reaction Points Type";
$l['reactions_field_points_type_desc'] = "The type of points this reaction provides";
$l['reactions_field_custom_points'] = "Custom Points";
$l['reactions_field_custom_points_desc'] = "If you want a custom points value (usually kept at 1 for positive, 0 for neutral, -1 for negative)";
$l['reactions_field_display_order'] = "Display Order";
$l['reactions_field_display_order_desc'] = "Order in which reactions appear";
$l['reactions_field_active'] = "Active";
$l['reactions_field_active_desc'] = "Is this reaction active?";
$l['reactions_field_image_url'] = "Image URL";
$l['reactions_field_image_url_desc'] = "Path to the reaction image";
$l['reactions_field_image_url_2x'] = "Image URL 2x";
$l['reactions_field_image_url_2x_desc'] = "Path to the 2x resolution image (optional)";
$l['reactions_field_sprite_mode'] = "Sprite Mode";
$l['reactions_field_sprite_mode_desc'] = "Enable CSS sprite mode with the following parameters";
$l['reactions_sprite_width_label'] = "Width: ";
$l['reactions_sprite_height_label'] = " x Height: ";
$l['reactions_field_sprite_dimensions'] = "Sprite Dimensions";
$l['reactions_field_sprite_dimensions_desc'] = "Width and Height of a single sprite in pixels";
$l['reactions_sprite_x_label'] = "X: ";
$l['reactions_sprite_y_label'] = " x Y: ";
$l['reactions_field_sprite_position'] = "Sprite Position";
$l['reactions_field_sprite_position_desc'] = "Horizontal and Vertical position of the sprite in the sheet";
$l['reactions_field_background_size'] = "Background Size";
$l['reactions_field_background_size_desc'] = "The background-size CSS property for this sprite (e.g. 100% or cover)";
$l['reactions_save_reaction'] = "Save Reaction";
$l['reactions_delete_confirm'] = "Are you sure you want to delete this reaction?";
$l['reactions_list_title'] = "Reactions";
$l['reactions_table_id'] = "ID";
$l['reactions_table_title'] = "Title";
$l['reactions_table_points'] = "Points";
$l['reactions_table_type'] = "Type";
$l['reactions_table_order'] = "Order";
$l['reactions_table_active'] = "Active";
$l['reactions_table_options'] = "Options";
$l['reactions_yes'] = "Yes";
$l['reactions_no'] = "No";
$l['reactions_edit'] = "Edit";
$l['reactions_delete'] = "Delete";
$l['reactions_no_reactions_found'] = "No reactions found. ";
$l['reactions_add_one'] = "Add one";
$l['reactions_give_points_to_user'] = "Give Reaction Points to User";
$l['reactions_add_new_reaction'] = "Add New Reaction";

// Admin Settings (usergroup edit + MyAlerts notices)
$l['reactions_settings_form_title'] = "Reactions";
$l['reactions_daily_reaction_limit'] = "Daily Reaction Limit";
$l['reactions_daily_reaction_limit_desc'] = "The maximum number of reactions members of this group can give per day. Set to 0 for unlimited.";
$l['reactions_daily_reaction_limit_blocked'] = " <br /><span style='color: #C00;'><strong>Note:</strong> This usergroup is blocked from giving reactions in the plugin settings. Remove this usergroup from Reactions Settings → User Groups not Allowed to React to activate this setting.</span>";
$l['reactions_error_myalerts_alert_type'] = "Error: Could not find 'reaction_removed' alert type. Make sure MyAlerts is installed and the plugin is activated.";
$l['reactions_myalerts_notice_html'] = "<div class=\"alert alert-warning\"><strong>Note:</strong> MyAlerts plugin is not installed. Please <a href=\"https://community.mybb.com/mods.php?action=view&pid=78\" target=\"_blank\">install MyAlerts</a> first to enable alert notifications.</div>";
$l['reactions_myalerts_note_html'] = "<br /><span style=\"color: #C00; font-size: 11px;\"><strong>Note:</strong> MyAlerts plugin is not installed. Please <a href=\"https://community.mybb.com/mods.php?action=view&pid=78\" target=\"_blank\">install MyAlerts</a> first to enable this feature.</span>";
$l['reactions_error_myalerts_integrate'] = "MyAlerts integration cannot be enabled because the MyAlerts plugin is not installed. Please install MyAlerts first from https://community.mybb.com/mods.php?action=view&pid=78";

// Admin Menu
$l['reactions_admin_menu_title'] = "Reactions";
