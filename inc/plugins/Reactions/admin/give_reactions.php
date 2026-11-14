<?php
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

if($mybb->input['action'] == "give_reactions")
{
    // Give reactions to posts
    global $lang;
    if(!isset($lang->reactions_success_created_reactions))
    {
        $lang->load("reactions");
    }
    
    if($mybb->request_method == "post")
    {
        // Parse post identifiers
        $post_input = $mybb->input['post_ids'];
        $pids = reactions_parse_post_identifiers($post_input);
        
        if(empty($pids))
        {
            flash_message($lang->reactions_error_invalid_post_ids, "error");
            admin_redirect("index.php?module=config-reactions&action=give_reactions");
        }
        
        // Get users
        $uids = array();
        $user_selection_mode = $mybb->input['user_selection_mode']; // 'manual' or 'random'
        
        if($user_selection_mode == 'manual')
        {
            // Manual user IDs
            $user_ids_input = $mybb->input['user_ids'];
            $uids = explode(',', $user_ids_input);
            $uids = array_map('trim', $uids);
            $uids = array_filter($uids, 'is_numeric');
            $uids = array_map('intval', $uids);
        }
        else
        {
            // Random from usergroups
            $usergroup_config = array();
            
            // Parse usergroup selections from hidden field
            $selected_groups_json = $mybb->input['selected_usergroups'];
            if(!empty($selected_groups_json))
            {
                $selected_groups = json_decode($selected_groups_json, true);
                if(is_array($selected_groups))
                {
                    foreach($selected_groups as $group_data)
                    {
                        $gid = (int)$group_data['gid'];
                        $count = (int)$group_data['count'];
                        if($gid > 0 && $count > 0)
                        {
                            $usergroup_config[$gid] = $count;
                        }
                    }
                }
            }
            
            if(empty($usergroup_config))
            {
                flash_message($lang->reactions_error_select_usergroup, "error");
                admin_redirect("index.php?module=config-reactions&action=give_reactions");
            }
            
            $uids = reactions_get_random_users_from_usergroups($usergroup_config);
        }
        
        if(empty($uids))
        {
            flash_message($lang->reactions_error_no_valid_users, "error");
            admin_redirect("index.php?module=config-reactions&action=give_reactions");
        }
        
        // Get reaction distribution
        $total_reactions_input = (int)$mybb->input['total_reactions'];
        $distribution_mode = $mybb->input['distribution_mode']; // 'manual' or 'random'

        // Build reaction distribution
        $reaction_distribution = array();
        $total_reactions = 0; // Will be calculated based on mode

        if($distribution_mode == 'manual')
        {
            // Manual counts per reaction - calculate total from actual counts
            $reaction_counts = $mybb->input['reaction_counts']; // Array of reaction_id => count

            if(is_array($reaction_counts))
            {
                foreach($reaction_counts as $rid => $count)
                {
                    $rid = (int)$rid;
                    $count = (int)$count;
                    if($rid > 0 && $count > 0)
                    {
                        $reaction_distribution[$rid] = $count;
                        $total_reactions += $count;
                    }
                }
            }

            if($total_reactions <= 0)
            {
                flash_message($lang->reactions_error_specify_reaction_count, "error");
                admin_redirect("index.php?module=config-reactions&action=give_reactions");
            }
        }
        elseif($distribution_mode == 'random')
        {
            // For random mode, total reactions should be derived from the number of users
            // The hidden field total_reactions may be 0 or set to 0, so we calculate based on users
            $total_reactions = count($uids);

            if($total_reactions <= 0)
            {
                flash_message($lang->reactions_error_total_reactions, "error");
                admin_redirect("index.php?module=config-reactions&action=give_reactions");
            }

            // Random distribution - check the random_type sub-option
            $random_type = $mybb->input['random_type']; // 'positive', 'positive_neutral', or 'all'

            $active_reactions = reactions_get_active();
            if(empty($active_reactions))
            {
                flash_message($lang->reactions_error_no_active_reactions, "error");
                admin_redirect("index.php?module=config-reactions&action=give_reactions");
            }

            // Filter based on random_type
            $filtered_reactions = array();
            if($random_type == 'positive')
            {
                // Only positive reactions
                foreach($active_reactions as $rid => $reaction)
                {
                    if($reaction['reaction_type'] == 'positive')
                    {
                        $filtered_reactions[$rid] = $reaction;
                    }
                }

                if(empty($filtered_reactions))
                {
                    flash_message($lang->reactions_error_no_positive_reactions, "error");
                    admin_redirect("index.php?module=config-reactions&action=give_reactions");
                }
            }
            elseif($random_type == 'positive_neutral')
            {
                // Positive + Neutral reactions (no negative)
                foreach($active_reactions as $rid => $reaction)
                {
                    if($reaction['reaction_type'] == 'positive' || $reaction['reaction_type'] == 'neutral')
                    {
                        $filtered_reactions[$rid] = $reaction;
                    }
                }

                if(empty($filtered_reactions))
                {
                    flash_message($lang->reactions_error_no_positive_neutral_reactions, "error");
                    admin_redirect("index.php?module=config-reactions&action=give_reactions");
                }
            }
            else
            {
                // All reactions (including negative)
                $filtered_reactions = $active_reactions;
            }

            // Distribute randomly
            $reaction_ids = array_keys($filtered_reactions);
            for($i = 0; $i < $total_reactions; $i++)
            {
                $rid = $reaction_ids[array_rand($reaction_ids)];
                if(!isset($reaction_distribution[$rid]))
                {
                    $reaction_distribution[$rid] = 0;
                }
                $reaction_distribution[$rid]++;
            }
        }
        
        // Build assignments array
        $assignments = array();
        
        // Create a pool of reactions to distribute
        $reaction_pool = array();
        foreach($reaction_distribution as $rid => $count)
        {
            for($i = 0; $i < $count; $i++)
            {
                $reaction_pool[] = $rid;
            }
        }

        foreach($pids as $pid)
        {
            // Shuffle users so different posts get reactions from different people if uids > pool
            $shuffled_uids = $uids;
            shuffle($shuffled_uids);
            
            // Distribute the reaction pool among the available users
            $pool_index = 0;
            foreach($shuffled_uids as $uid)
            {
                if(!isset($reaction_pool[$pool_index]))
                {
                    break;
                }
                
                $assignments[] = array(
                    'pid' => $pid,
                    'uid' => $uid,
                    'reaction_id' => $reaction_pool[$pool_index]
                );
                $pool_index++;
            }
        }
        
        // Validate bulk assignment before creating anything
        $validate_only = isset($mybb->input['validate_only']) ? (int)$mybb->input['validate_only'] : 0;
        $reaction_ids = array_keys($reaction_distribution);
        $validation = reactions_validate_bulk_assignment($pids, $uids, $reaction_ids, true);
        
        $warnings = $validation['warnings'];
        
        // Dry-run mode: validate and report without creating reactions
        if($validate_only)
        {
            if(!$validation['valid'])
            {
                flash_message($lang->sprintf($lang->reactions_validate_blocked, implode(' ', $validation['errors'])), "error");
                admin_redirect("index.php?module=config-reactions&action=give_reactions");
            }
            
            $validation_message = $lang->sprintf(
                $lang->reactions_validate_passed,
                count($validation['valid_pids']),
                count($validation['valid_uids']),
                count(array_unique($validation['valid_reaction_ids']))
            );
            if(!empty($warnings))
            {
                $validation_message .= ' ' . implode(' ', $warnings);
                flash_message($validation_message, "error");
            }
            else
            {
                flash_message($validation_message, "success");
            }
            admin_redirect("index.php?module=config-reactions&action=give_reactions");
        }
        
        // Normal mode: block on fatal validation errors, otherwise proceed with warnings
        if(!$validation['valid'])
        {
            flash_message($lang->sprintf($lang->reactions_validate_blocked, implode(' ', $validation['errors'])), "error");
            admin_redirect("index.php?module=config-reactions&action=give_reactions");
        }
        
        // Create reactions
        $result = reactions_create_bulk_reactions($assignments, true);
        
        $message = $lang->sprintf($lang->reactions_success_created_reactions, $result['success_count']);
        if($result['skipped_count'] > 0)
        {
            $message .= $lang->sprintf($lang->reactions_success_skipped_duplicates, $result['skipped_count']);
        }
        if($result['error_count'] > 0)
        {
            $message .= $lang->sprintf($lang->reactions_success_errors_occurred, $result['error_count']);
        }
        if(!empty($warnings))
        {
            $message .= ' ' . implode(' ', $warnings);
        }
        
        // If we had more reactions in pool than users, warn the user
        $pool_size = count($reaction_pool);
        $user_count = count($uids);
        if($pool_size > $user_count)
        {
            $message .= $lang->sprintf($lang->reactions_success_note_users, $pool_size, $user_count, $user_count);
        }
        
        flash_message($message, "success");
        admin_redirect("index.php?module=config-reactions&action=give_reactions");
    }
    
    // Show form
    $page->output_header($lang->reactions_give_reactions_title);
    
    $sub_tabs['give_points'] = array(
        'title' => $lang->reactions_give_points,
        'link' => "index.php?module=config-reactions&action=give_points",
        'description' => $lang->reactions_give_points_tab_desc
    );
    
    $sub_tabs['give_reactions'] = array(
        'title' => $lang->reactions_give_reactions_title,
        'link' => "index.php?module=config-reactions&action=give_reactions",
        'description' => $lang->reactions_give_reactions_tab_desc
    );
    
    $page->output_nav_tabs($sub_tabs, "give_reactions");
    
    // Get all active reactions for the manual distribution
    $reactions = array();
    $query = $db->simple_select("reactions", "*", "active=1", array("order_by" => "display_order", "order_dir" => "ASC"));
    while($row = $db->fetch_array($query))
    {
        $reactions[$row['reaction_id']] = $row;
    }
    
    // Get all usergroups
    $usergroups = array();
    $query = $db->simple_select("usergroups", "gid, title", "", array("order_by" => "title"));
    while($row = $db->fetch_array($query))
    {
        $usergroups[$row['gid']] = $row;
    }
    
    $form = new Form("index.php?module=config-reactions&amp;action=give_reactions", "post", "give_reactions_form");
    
    // Post Selection
    $form_container = new FormContainer($lang->reactions_form_post_selection);
    $form_container->output_row($lang->reactions_post_urls_or_ids, $lang->reactions_post_urls_or_ids_desc, $form->generate_text_area("post_ids", "", array('id' => 'post_ids', 'style' => 'width: 100%; height: 80px;')), 'post_ids');
    $form_container->end();
    
    // User Selection
    $form_container = new FormContainer($lang->reactions_form_user_selection);
    $form_container->output_row("", "", "<label><input type='radio' name='user_selection_mode' value='manual' id='user_mode_manual' checked> {$lang->reactions_user_mode_manual}</label>");
    $form_container->output_row($lang->reactions_user_ids, $lang->reactions_user_ids_desc, $form->generate_text_area("user_ids", "", array('id' => 'user_ids', 'style' => 'width: 100%; height: 60px;')), 'user_ids');
    
    $form_container->output_row("", "", "<label><input type='radio' name='user_selection_mode' value='random' id='user_mode_random'> {$lang->reactions_user_mode_random}</label>");
    
    // Usergroup selector - using sortable lists for drag and drop (adapted from forum permissions)
    $usergroup_selector = "
    <div id='usergroup_selector' style='display: none; margin-top: 10px;'>
        <div class='quick_perm_fields'>
            <div class='enabled'>
                <div class='fields_title'>{$lang->reactions_selected_usergroups}</div>
                <ul id='selected_groups'>";
    
    // Start with empty selected list
    $usergroup_selector .= "</ul>
            </div>
            <div class='disabled'>
                <div class='fields_title'>{$lang->reactions_available_usergroups}</div>
                <ul id='available_groups'>";
    
    foreach($usergroups as $group)
    {
        $usergroup_selector .= "<li id='gid-{$group['gid']}'>" . htmlspecialchars_uni($group['title']) . "</li>";
    }

    $usergroup_selector .= "</ul>
            </div>
        </div>
        <div style='clear: both;'></div>
        <div id='usergroup_counts' style='margin-top: 15px;'></div>
    </div>
    <input type='hidden' name='selected_usergroups' id='selected_usergroups' value=''>
    ";
    
    $form_container->output_row("", "", $usergroup_selector);
    $form_container->end();
    
    // Reaction Distribution
    $form_container = new FormContainer($lang->reactions_form_reaction_distribution);
    
    // Default total reactions (will be auto-calculated based on users, or manual per post)
    $form_container->output_row("", "", $form->generate_hidden_field("total_reactions", "0", array('id' => 'total_reactions')));
    
    // Advanced options link (shown when multiple posts detected)
    $advanced_options = "
    <div id='advanced_options_container' style='display: none; margin-top: 10px;'>
        <div style='background: #f0f8ff; border: 1px solid #b3d7ff; border-radius: 4px; padding: 15px; margin-bottom: 15px;'>
            <strong>{$lang->reactions_advanced_options_title}</strong>
            <p style='margin: 5px 0; font-size: 12px; color: #666;'>{$lang->reactions_advanced_options_desc}</p>
            <div id='post_reaction_inputs' style='margin-top: 10px;'></div>
        </div>
    </div>
    <div id='show_advanced_link' style='margin: 10px 0; display: none;'>
        <a href='javascript:void(0)' onclick='UsergroupSelector.showAdvancedOptions();' style='color: #0066cc; text-decoration: underline;'>{$lang->reactions_show_advanced_options}</a>
    </div>
    <div id='hide_advanced_link' style='margin: 10px 0; display: none;'>
        <a href='javascript:void(0)' onclick='UsergroupSelector.hideAdvancedOptions();' style='color: #0066cc; text-decoration: underline;'>{$lang->reactions_hide_advanced_options}</a>
    </div>
    ";
    $form_container->output_row("", "", $advanced_options);
    
    // Random Distribution with toggle sub-options
    $random_dist = "<label id='random_dist_label' style='cursor: pointer;'><input type='radio' name='distribution_mode' value='random' id='dist_mode_random' checked> {$lang->reactions_random_distribution} <span id='random_dist_toggle' style='color: #666;'>▼</span></label>";
    $random_dist .= "<div id='random_dist_options' style='margin-left: 20px; margin-top: 10px; padding: 10px; background: #f5f5f5; border-radius: 4px; display: none;'>";
    $random_dist .= "<label style='display: block; margin-bottom: 5px;'><input type='radio' name='random_type' value='positive' id='random_type_positive' checked> {$lang->reactions_random_type_positive}</label>";
    $random_dist .= "<label style='display: block; margin-bottom: 5px;'><input type='radio' name='random_type' value='positive_neutral' id='random_type_positive_neutral'> {$lang->reactions_random_type_positive_neutral}</label>";
    $random_dist .= "<label style='display: block;'><input type='radio' name='random_type' value='all' id='random_type_all'> {$lang->reactions_random_type_all}</label>";
    $random_dist .= "</div>";
    
    $form_container->output_row("", "", $random_dist);
    $form_container->output_row("", "", "<label><input type='radio' name='distribution_mode' value='manual' id='dist_mode_manual'> {$lang->reactions_manual_distribution}</label>");
    
    // Manual distribution inputs
    $manual_dist = "<div id='manual_distribution' style='display: none; margin-top: 10px;'>";
    foreach($reactions as $reaction)
    {
        $manual_dist .= "<div style='margin: 5px 0;'>";
        $manual_dist .= "<label style='display: inline-block; width: 150px;'>{$reaction['title']}:</label>";
        $manual_dist .= "<input type='number' name='reaction_counts[{$reaction['reaction_id']}]' class='reaction-count-input' data-rid='{$reaction['reaction_id']}' value='0' min='0' style='width: 80px;'>{$lang->reactions_manual_reactions_suffix}";
        $manual_dist .= "</div>";
    }
    $manual_dist .= "<div id='count_validation' style='margin-top: 10px; font-weight: bold;'></div>";
    $manual_dist .= "</div>";
    
    $form_container->output_row("", "", $manual_dist);
    $form_container->end();
    
    $form_container = new FormContainer($lang->reactions_validate_only);
    $form_container->output_row($lang->reactions_validate_only, $lang->reactions_validate_only_desc, $form->generate_check_box("validate_only", "1", $lang->reactions_validate_only_desc, array('id' => 'validate_only')), 'validate_only');
    $form_container->end();
    
    $buttons[] = $form->generate_submit_button($lang->reactions_submit_give_reactions);
    $form->output_submit_wrapper($buttons);
    $form->end();

    // CSS for drag and drop styling (adapted from forum permissions)
    echo "
    <style type='text/css'>
    /* Usergroup selector styles - adapted from forum permissions */
    #usergroup_selector .quick_perm_fields .enabled, 
    #usergroup_selector .quick_perm_fields .disabled {
        width: 49%;
        float: left;
    }
    #usergroup_selector .quick_perm_fields .enabled {
        border-right: 5px solid #666;
    }
    #usergroup_selector .quick_perm_fields .disabled {
        float: right;
        margin-left: 0px;
    }
    #usergroup_selector .quick_perm_fields .fields_title {
        font-weight: bold;
        font-size: 120%;
        margin-bottom: 5px;
    }
    #usergroup_selector .quick_perm_fields ul, 
    #usergroup_selector .quick_perm_fields li {
        list-style: none;
        padding: 3px;
        margin: 0;
    }
    #usergroup_selector .quick_perm_fields ul {
        overflow-y: auto;
        overflow-x: hidden;
        height: 200px;
        margin-top: 4px;
        border: 1px solid #ccc;
        background: #f9f9f9;
    }
    #usergroup_selector .quick_perm_fields li {
        border-radius: 3px;
        padding: 6px;
        background: #666;
        color: #fff;
        font-size: 12px;
        margin-bottom: 3px;
        margin-right: 10px;
        cursor: move;
        user-select: none;
    }
    #usergroup_selector .quick_perm_fields li:hover {
        background: #555;
    }
    #usergroup_selector .quick_perm_fields .ui-sortable-helper {
        background: #444 !important;
        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    }
    /* Mobile clickable mode styles */
    body.mobile-usergroup-selector #usergroup_selector .quick_perm_fields li {
        cursor: pointer;
        position: relative;
        padding-right: 30px;
    }
    body.mobile-usergroup-selector #usergroup_selector .quick_perm_fields li::after {
        content: '→';
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 14px;
        font-weight: bold;
    }
    body.mobile-usergroup-selector #usergroup_selector #selected_groups li::after {
        content: '×';
        color: #ff6b6b;
    }
    body.mobile-usergroup-selector #usergroup_selector #available_groups li::after {
        content: '+';
        color: #51cf66;
    }
    body.mobile-usergroup-selector #usergroup_selector .quick_perm_fields li:active {
        background: #333;
        transform: scale(0.98);
    }
    /* Advanced options styles */
    #advanced_options_container {
        animation: fadeIn 0.3s ease-in;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    #post_reaction_inputs input[type=\"number\"] {
        padding: 5px;
        border: 1px solid #ccc;
        border-radius: 3px;
    }
    #post_reaction_inputs input[type=\"number\"]:focus {
        border-color: #0066cc;
        outline: none;
        box-shadow: 0 0 3px rgba(0,102,204,0.3);
    }
    #show_advanced_link a, #hide_advanced_link a {
        font-weight: bold;
        padding: 5px 0;
    }
    /* Validation message styles */
    #post_reaction_inputs .validation-msg {
        display: inline-block;
        font-weight: bold;
    }
    /* Mobile responsive */
    @media (max-width: 768px) {
        #usergroup_selector .quick_perm_fields .enabled, 
        #usergroup_selector .quick_perm_fields .disabled {
            width: 100%;
            float: none;
            border-right: none;
            border-bottom: 5px solid #666;
            margin-bottom: 15px;
        }
        #usergroup_selector .quick_perm_fields ul {
            height: 150px;
        }
        #post_reaction_inputs label {
            display: block !important;
            width: auto !important;
            margin-bottom: 5px;
        }
    }
    </style>
    ";

    // JavaScript for interactivity
    echo "
    <script type='text/javascript'>
    var reactionsAdminLang = " . json_encode(array(
        'total_matches' => $lang->reactions_js_total_matches,
        'exceeds_by' => $lang->reactions_js_exceeds_by,
        'short_by' => $lang->reactions_js_short_by,
        'user_counts_per_group' => $lang->reactions_js_user_counts_per_group,
        'users_suffix' => $lang->reactions_js_users_suffix,
        'total_available_users' => $lang->reactions_js_total_available_users,
        'each_post_can_have' => $lang->reactions_js_each_post_can_have,
        'no_valid_post_ids' => $lang->reactions_js_no_valid_post_ids,
        'post_id_label' => $lang->reactions_js_post_id_label,
        'auto_placeholder' => $lang->reactions_js_auto_placeholder,
        'no_users_selected' => $lang->reactions_js_no_users_selected,
        'exceeds_available_users' => $lang->reactions_js_exceeds_available_users,
        'valid' => $lang->reactions_js_valid,
        'reactions_suffix' => $lang->reactions_js_reactions_suffix
    )) . ";
    function rl(key, fallback) {
        return (typeof reactionsAdminLang[key] !== 'undefined' && reactionsAdminLang[key] !== null && reactionsAdminLang[key] !== '') ? reactionsAdminLang[key] : fallback;
    }
    function rf(key) {
        var args = Array.prototype.slice.call(arguments, 1);
        var s = rl(key, key);
        for(var i = 0; i < args.length; i++) {
            s = s.split('{' + (i + 1) + '}').join(args[i]);
        }
        return s;
    }
    document.addEventListener('DOMContentLoaded', function() {
        var userModeManual = document.getElementById('user_mode_manual');
        var userModeRandom = document.getElementById('user_mode_random');
        var userIdsField = document.getElementById('user_ids');
        var usergroupSelector = document.getElementById('usergroup_selector');

        var distModeRandom = document.getElementById('dist_mode_random');
        var distModeManual = document.getElementById('dist_mode_manual');
        var manualDistribution = document.getElementById('manual_distribution');
        var totalReactionsInput = document.getElementById('total_reactions');
        var countValidation = document.getElementById('count_validation');

        // User selection mode toggle
        userModeManual.addEventListener('change', function() {
            if(this.checked) {
                userIdsField.disabled = false;
                usergroupSelector.style.display = 'none';
            }
        });

        userModeRandom.addEventListener('change', function() {
            if(this.checked) {
                userIdsField.disabled = true;
                usergroupSelector.style.display = 'block';
            }
        });

        // Distribution mode toggle
        if(distModeRandom) {
            distModeRandom.addEventListener('change', function() {
                if(this.checked) {
                    manualDistribution.style.display = 'none';
                    document.getElementById('random_dist_options').style.display = 'block';
                }
            });
            
            // Toggle random distribution options when clicking the label
            var randomDistLabel = document.getElementById('random_dist_label');
            if(randomDistLabel) {
                randomDistLabel.addEventListener('click', function(e) {
                    // Don't toggle when clicking the radio button itself
                    if(e.target.type !== 'radio') {
                        var optionsDiv = document.getElementById('random_dist_options');
                        var toggleSpan = document.getElementById('random_dist_toggle');
                        if(optionsDiv.style.display === 'none') {
                            optionsDiv.style.display = 'block';
                            toggleSpan.textContent = '▲';
                        } else {
                            optionsDiv.style.display = 'none';
                            toggleSpan.textContent = '▼';
                        }
                    }
                });
            }
        }

        if(distModeManual) {
            distModeManual.addEventListener('change', function() {
                if(this.checked) {
                    manualDistribution.style.display = 'block';
                    // Hide random distribution options
                    document.getElementById('random_dist_options').style.display = 'none';
                    document.getElementById('random_dist_toggle').textContent = '▼';
                    validateReactionCounts();
                }
            });
        }
 
        // Reaction count validation
        var reactionCountInputs = document.querySelectorAll('.reaction-count-input');
        reactionCountInputs.forEach(function(input) {
            input.addEventListener('input', validateReactionCounts);
        });

        totalReactionsInput.addEventListener('input', validateReactionCounts);

        function validateReactionCounts() {
            if(!distModeManual.checked) return;

            var total = parseInt(totalReactionsInput.value) || 0;
            var sum = 0;

            reactionCountInputs.forEach(function(input) {
                sum += parseInt(input.value) || 0;
            });

            if(sum === total) {
                countValidation.innerHTML = '<span style=\"color: green;\">✓ ' + rf('total_matches', 'Total matches: {1} reactions', sum) + '</span>';
            } else if(sum > total) {
                var diff = sum - total;
                countValidation.innerHTML = '<span style=\"color: red;\">✗ ' + rf('exceeds_by', 'Exceeds by {1} reaction(s). Total: {2}, Target: {3}', diff, sum, total) + '</span>';
            } else {
                var diff = total - sum;
                countValidation.innerHTML = '<span style=\"color: orange;\">⚠ ' + rf('short_by', 'Short by {1} reaction(s). Total: {2}, Target: {3}', diff, sum, total) + '</span>';
            }
        }
    });
    </script>
    ";

    // Drag and drop for usergroups - adapted from forum permissions QuickPermEditor
    echo "
    <script type='text/javascript'>
    // Usergroup Selector Manager - similar to QuickPermEditor
    var UsergroupSelector = {
        isMobile: false,
        
        init: function() {
            // Check if the selector container is visible
            if($('#usergroup_selector').is(':hidden')) {
                return;
            }
            
            // Detect mobile/tablet based on screen width
            UsergroupSelector.checkMobile();
            
            // Check on window resize
            $(window).on('resize', function() {
                UsergroupSelector.checkMobile();
            });
            
            // Initialize based on device type
            if(UsergroupSelector.isMobile) {
                UsergroupSelector.initMobile();
            } else {
                UsergroupSelector.initDesktop();
            }
            
            // Initial build of groups list
            UsergroupSelector.buildGroupsList();
        },
        
        checkMobile: function() {
            // Check if screen width is tablet or mobile (less than 992px)
            var wasMobile = UsergroupSelector.isMobile;
            UsergroupSelector.isMobile = ($(window).width() < 992);
            
            // Add/remove mobile class to body
            if(UsergroupSelector.isMobile) {
                $('body').addClass('mobile-usergroup-selector');
            } else {
                $('body').removeClass('mobile-usergroup-selector');
            }
            
            // If switching between mobile and desktop, reinitialize
            if(wasMobile !== UsergroupSelector.isMobile) {
                UsergroupSelector.reinit();
            }
        },
        
        initDesktop: function() {
            // Destroy any existing sortable to prevent duplicates
            try {
                $('#selected_groups, #available_groups').sortable('destroy');
            } catch(e) {}
            
            // Enable sortable drag-and-drop
            $('#selected_groups').sortable({
                connectWith: '#available_groups',
                dropOnEmpty: true,
                update: function(event, ui) {
                    UsergroupSelector.buildGroupsList();
                }
            }).disableSelection();
            
            $('#available_groups').sortable({
                connectWith: '#selected_groups',
                dropOnEmpty: true,
                update: function(event, ui) {
                    UsergroupSelector.buildGroupsList();
                }
            }).disableSelection();
            
            // Remove click handlers by cloning and replacing
            $('#selected_groups li, #available_groups li').off('click');
        },
        
        initMobile: function() {
            // Destroy sortable on mobile
            try {
                $('#selected_groups, #available_groups').sortable('destroy');
            } catch(e) {}
            
            // Enable click mode
            UsergroupSelector.initClickHandlers();
        },
        
        reinit: function() {
            // Only reinit if selector is visible
            if($('#usergroup_selector').is(':hidden')) {
                return;
            }
            
            if(UsergroupSelector.isMobile) {
                UsergroupSelector.initMobile();
            } else {
                UsergroupSelector.initDesktop();
            }
            UsergroupSelector.buildGroupsList();
        },
        
        initClickHandlers: function() {
            // Remove existing click handlers
            $('#selected_groups li, #available_groups li').off('click');
            
            // Add click handler to available groups (move to selected)
            $('#available_groups').on('click', 'li', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).appendTo('#selected_groups');
                UsergroupSelector.buildGroupsList();
            });
            
            // Add click handler to selected groups (move to available)
            $('#selected_groups').on('click', 'li', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).appendTo('#available_groups');
                UsergroupSelector.buildGroupsList();
            });
        },

        buildGroupsList: function() {
            var selected_groups = [];
            var totalUsers = 0;
            
            $('#selected_groups').children().each(function() {
                var gid = $(this).attr('id');
                if(gid) {
                    // Extract gid from 'gid-XX' format
                    var match = gid.match(/^gid-([0-9]+)$/);
                    if(match) {
                        var count = 1;
                        // Try to get existing count from current data
                        var currentData = $('#selected_usergroups').val();
                        if(currentData) {
                            var groups = JSON.parse(currentData);
                            var existing = groups.find(function(g) { return g.gid == match[1]; });
                            if(existing) {
                                count = existing.count;
                            }
                        }
                        selected_groups.push({
                            gid: match[1],
                            title: $(this).text().trim(),
                            count: count
                        });
                        totalUsers += count;
                    }
                }
            });
            
            // Store as JSON in hidden field
            $('#selected_usergroups').val(JSON.stringify(selected_groups));
            
            // Update the count inputs display
            UsergroupSelector.updateCountsDisplay(selected_groups);
            
            // Store total available users for validation
            UsergroupSelector.totalAvailableUsers = totalUsers;
            
            // Re-validate post reaction counts if any exist
            UsergroupSelector.validatePostReactionCounts();
        },
        
        updateCountsDisplay: function(groups) {
            var usergroupCountsDiv = document.getElementById('usergroup_counts');
            
            if(groups.length === 0) {
                usergroupCountsDiv.innerHTML = '';
                return;
            }
            
            var html = '<strong>' + rl('user_counts_per_group', 'User Counts per Group:') + '</strong><br>';
            groups.forEach(function(group) {
                html += '<div style=\"margin: 5px 0;\">';
                html += '<label style=\"display: inline-block; width: 200px;\">' + group.title + ':</label>';
                html += '<input type=\"number\" class=\"usergroup-count\" data-gid=\"' + group.gid + '\" value=\"' + group.count + '\" min=\"1\" style=\"width: 80px;\">' + rl('users_suffix', ' users');
                html += '</div>';
            });
            usergroupCountsDiv.innerHTML = html;
            
            // Add event listeners to count inputs
            var countInputs = usergroupCountsDiv.querySelectorAll('.usergroup-count');
            countInputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    var gid = this.getAttribute('data-gid');
                    var count = parseInt(this.value) || 1;
                    
                    // Update the stored value
                    var currentVal = $('#selected_usergroups').val();
                    var groups = currentVal ? JSON.parse(currentVal) : [];
                    var group = groups.find(function(g) { return g.gid == gid; });
                    if(group) {
                        group.count = count;
                        $('#selected_usergroups').val(JSON.stringify(groups));
                    }
                    
                    // Recalculate total users
                    var total = 0;
                    groups.forEach(function(g) {
                        total += parseInt(g.count) || 1;
                    });
                    UsergroupSelector.totalAvailableUsers = total;
                    
                    // Update total users info display
                    $('#total_users_info').html('<strong>' + rl('total_available_users', 'Total Available Users:') + '</strong> ' + total + rl('users_suffix', ' users') + '<br><small style=\"color: #666;\">' + rf('each_post_can_have', 'Each post can have up to {1} reactions (one per user).', total) + '</small>');
                    
                    // Re-validate post reaction counts
                    UsergroupSelector.validatePostReactionCounts();
                });
            });
        },
        
        // Advanced options for per-post reaction counts
        parsedPostIds: [],
        
        parsePostIds: function() {
            var postIdsText = $('#post_ids').val();
            if(!postIdsText.trim()) {
                UsergroupSelector.parsedPostIds = [];
                UsergroupSelector.hideAdvancedLink();
                return;
            }
            
            // Parse post IDs - split by comma or newline, extract numbers from URLs
            var lines = postIdsText.split(/[,\\n]+/);
            var postIds = [];
            
            lines.forEach(function(line) {
                line = line.trim();
                if(!line) return;
                
                var pid = null;
                
                // Try different URL formats:
                // 1. pid=123 or pid:123 (query string) - e.g., showthread.php?pid=123
                var match = line.match(/pid[=:]([0-9]+)/i);
                if(match) {
                    pid = match[1];
                }
                // 2. #pid123 (fragment/hash) - e.g., #pid5500
                if(!pid) {
                    match = line.match(/#pid([0-9]+)/i);
                    if(match) {
                        pid = match[1];
                    }
                }
                // 3. post-123.html format - e.g., thread-230-post-5500.html
                if(!pid) {
                    match = line.match(/post-([0-9]+)/i);
                    if(match) {
                        pid = match[1];
                    }
                }
                // 4. Just a plain number
                if(!pid && /^[0-9]+$/.test(line)) {
                    pid = line;
                }
                
                if(pid) {
                    postIds.push(pid);
                }
            });
            
            // Remove duplicates
            postIds = postIds.filter(function(value, index, self) {
                return self.indexOf(value) === index;
            });
            
            UsergroupSelector.parsedPostIds = postIds;
            
            // Show advanced link if there are 1 or more posts (allow customization per post)
            if(postIds.length >= 1) {
                $('#show_advanced_link').show();
            } else {
                UsergroupSelector.hideAdvancedLink();
            }
            
            // Rebuild inputs if advanced options are visible
            if($('#advanced_options_container').is(':visible')) {
                UsergroupSelector.buildPostReactionInputs();
            }
        },
        
        showAdvancedLink: function() {
            $('#show_advanced_link').show();
            $('#hide_advanced_link').hide();
        },
        
        hideAdvancedLink: function() {
            $('#show_advanced_link').hide();
            $('#hide_advanced_link').hide();
            $('#advanced_options_container').hide();
        },
        
        showAdvancedOptions: function() {
            $('#show_advanced_link').hide();
            $('#hide_advanced_link').show();
            $('#advanced_options_container').show();
            UsergroupSelector.buildPostReactionInputs();
        },
        
        hideAdvancedOptions: function() {
            $('#show_advanced_link').show();
            $('#hide_advanced_link').hide();
            $('#advanced_options_container').hide();
        },
        
        buildPostReactionInputs: function() {
            var container = $('#post_reaction_inputs');
            container.empty();
            
            if(UsergroupSelector.parsedPostIds.length === 0) {
                container.html('<p style=\"color: #999;\">' + rl('no_valid_post_ids', 'No valid post IDs found. Please enter post IDs or URLs in the Post Selection field above.') + '</p>');
                return;
            }
            
            // Add info about total available users
            var totalUsers = UsergroupSelector.totalAvailableUsers || 0;
            var infoHtml = '<div id=\"total_users_info\" style=\"margin-bottom: 15px; padding: 10px; background: #e7f3ff; border: 1px solid #b3d7ff; border-radius: 3px;\">';
            infoHtml += '<strong>' + rl('total_available_users', 'Total Available Users:') + '</strong> ' + totalUsers + rl('users_suffix', ' users') + '<br>';
            infoHtml += '<small style=\"color: #666;\">' + rf('each_post_can_have', 'Each post can have up to {1} reactions (one per user).', totalUsers) + '</small>';
            infoHtml += '</div>';
            container.append(infoHtml);
            
            UsergroupSelector.parsedPostIds.forEach(function(pid) {
                var html = '<div style=\"margin: 8px 0; padding: 8px; background: #fff; border: 1px solid #ddd; border-radius: 3px;\" id=\"post_row_' + pid + '\">';
                html += '<label style=\"display: inline-block; width: 200px; font-weight: bold;\">' + rf('post_id_label', 'Post ID {1}:', pid) + '</label>';
                html += '<input type=\"number\" name=\"post_reaction_counts[' + pid + ']\" class=\"post-reaction-count\" data-pid=\"' + pid + '\" value=\"\" min=\"1\" placeholder=\"' + rl('auto_placeholder', 'Auto (use all users)') + '\" style=\"width: 150px;\">' + rl('reactions_suffix', ' reactions');
                html += '<span class=\"validation-msg\" id=\"validation_' + pid + '\" style=\"margin-left: 10px; font-size: 12px;\"></span>';
                html += '</div>';
                container.append(html);
            });
            
            // Add event listeners to update total and validate
            container.find('.post-reaction-count').on('input', function() {
                UsergroupSelector.updateTotalFromPostCounts();
                UsergroupSelector.validatePostReactionCounts();
            });
        },
        
        updateTotalFromPostCounts: function() {
            var total = 0;
            var hasCustomValues = false;
            
            $('#post_reaction_inputs .post-reaction-count').each(function() {
                var val = parseInt($(this).val());
                if(val && val > 0) {
                    total += val;
                    hasCustomValues = true;
                }
            });
            
            // Only update the hidden total_reactions if custom values are set
            if(hasCustomValues) {
                $('#total_reactions').val(total);
            } else {
                $('#total_reactions').val('0');
            }
        },
        
        validatePostReactionCounts: function() {
            var totalUsers = UsergroupSelector.totalAvailableUsers || 0;
            
            $('#post_reaction_inputs .post-reaction-count').each(function() {
                var input = $(this);
                var pid = input.attr('data-pid');
                var val = parseInt(input.val());
                var validationMsg = $('#validation_' + pid);
                var row = $('#post_row_' + pid);
                
                if(val && val > 0) {
                    if(totalUsers === 0) {
                        // No users selected yet
                        validationMsg.html('<span style=\"color: #ff9800;\">⚠ ' + rl('no_users_selected', 'No users selected') + '</span>');
                        row.css('border-color', '#ff9800');
                    } else if(val > totalUsers) {
                        // Exceeds available users - show error
                        validationMsg.html('<span style=\"color: #f44336;\">✗ ' + rf('exceeds_available_users', 'Exceeds available users ({1})', totalUsers) + '</span>');
                        row.css('border-color', '#f44336');
                        row.css('background', '#ffebee');
                    } else {
                        // Valid
                        validationMsg.html('<span style=\"color: #4caf50;\">✓ ' + rl('valid', 'Valid') + '</span>');
                        row.css('border-color', '#4caf50');
                        row.css('background', '#e8f5e9');
                    }
                } else {
                    // Empty or invalid - clear validation
                    validationMsg.html('');
                    row.css('border-color', '#ddd');
                    row.css('background', '#fff');
                }
            });
        },
        
        totalAvailableUsers: 0
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Track if selector has been initialized
        var selectorInitialized = false;
        
        // Initialize sortable when user selection mode changes to random
        $('#user_mode_random').on('change', function() {
            if(this.checked) {
                // Make sure container is visible
                $('#usergroup_selector').show();
                // Initialize after a delay to ensure DOM is ready and visible
                setTimeout(function() {
                    if(!selectorInitialized) {
                        UsergroupSelector.init();
                        selectorInitialized = true;
                    }
                }, 500);
            }
        });
        
        // Initialize immediately if already checked on page load
        if($('#user_mode_random').is(':checked')) {
            $('#usergroup_selector').show();
            setTimeout(function() {
                UsergroupSelector.init();
                selectorInitialized = true;
            }, 500);
        }
        
        // Monitor post_ids textarea for changes
        $('#post_ids').on('input keyup blur change', function() {
            UsergroupSelector.parsePostIds();
        });
        
        // Also try using native JavaScript event for broader compatibility
        var postIdsTextarea = document.getElementById('post_ids');
        if(postIdsTextarea) {
            postIdsTextarea.addEventListener('input', function() {
                UsergroupSelector.parsePostIds();
            });
        }
        
        // Initial parse
        UsergroupSelector.parsePostIds();
    });
    </script>
    ";

    $page->output_footer();
}

