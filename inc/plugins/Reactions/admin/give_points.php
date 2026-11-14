<?php
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

/**
 * Validate if a points/score combination is achievable and suggest valid alternatives
 * 
 * @param int $points Target sum of reaction points
 * @param int $score Number of reactions to create
 * @param array $positive_reactions Array of positive reactions
 * @param array $negative_reactions Array of negative reactions
 * @param array $neutral_reactions Array of neutral reactions (optional)
 * @param string $shuffle_type 'positive' or 'mixed'
 * @return array Array with 'valid' (bool) and 'suggestions' (array of valid scores)
 */
function reactions_validate_points_score($points, $score, $positive_reactions, $negative_reactions, $shuffle_type, $neutral_reactions = array())
{
    $result = array(
        'valid' => false,
        'suggestions' => array()
    );
    
    if($score <= 0)
    {
        return $result;
    }
    
    // Extract available scores
    $positive_scores = array();
    foreach($positive_reactions as $r)
    {
        $positive_scores[] = (int)$r['reaction_score'];
    }
    $positive_scores = array_unique($positive_scores);
    
    $negative_scores = array();
    foreach($negative_reactions as $r)
    {
        $negative_scores[] = (int)$r['reaction_score'];
    }
    $negative_scores = array_unique($negative_scores);
    
    $neutral_scores = array();
    foreach($neutral_reactions as $r)
    {
        $neutral_scores[] = (int)$r['reaction_score']; // Should be 0
    }
    $neutral_scores = array_unique($neutral_scores);
    
    if($shuffle_type == 'positive' || (empty($negative_scores) && empty($neutral_scores)))
    {
        // Positive-only mode
        if(empty($positive_scores))
        {
            return $result;
        }
        
        $min_score = min($positive_scores);
        $max_score = max($positive_scores);
        
        // Calculate min and max achievable points with $score reactions
        $min_achievable = $score * $min_score;
        $max_achievable = $score * $max_score;
        
        // Check if target is within range
        if($points < $min_achievable || $points > $max_achievable)
        {
            // Find valid scores for this point target
            $result['suggestions'] = reactions_find_valid_scores_for_points($points, $positive_scores, $negative_scores, $shuffle_type, $neutral_scores);
            return $result;
        }
        
        // Check if exact sum is achievable with the available scores
        if(reactions_can_achieve_sum($points, $score, $positive_scores))
        {
            $result['valid'] = true;
            return $result;
        }
        
        // Can't achieve exact sum, find suggestions
        $result['suggestions'] = reactions_find_valid_scores_for_points($points, $positive_scores, $negative_scores, $shuffle_type, $neutral_scores);
        return $result;
    }
    else
    {
        // Mixed mode - includes positive, negative and neutral
        $all_scores = array_merge($positive_scores, $negative_scores, $neutral_scores);
        
        if(empty($all_scores))
        {
            return $result;
        }
        
        $min_score = min($all_scores);
        $max_score = max($all_scores);
        
        // Calculate theoretical min and max
        $min_achievable = $score * $min_score;
        $max_achievable = $score * $max_score;
        
        // Check if target is within theoretical range
        if($points < $min_achievable || $points > $max_achievable)
        {
            $result['suggestions'] = reactions_find_valid_scores_for_points($points, $positive_scores, $negative_scores, $shuffle_type, $neutral_scores);
            return $result;
        }
        
        // Check if exact sum is achievable (including neutral scores which are 0)
        $all_scores_for_check = array_merge($positive_scores, $negative_scores, $neutral_scores);
        if(reactions_can_achieve_sum($points, $score, $all_scores_for_check))
        {
            $result['valid'] = true;
            return $result;
        }
        
        // Can't achieve exact sum, find suggestions
        $result['suggestions'] = reactions_find_valid_scores_for_points($points, $positive_scores, $negative_scores, $shuffle_type, $neutral_scores);
        return $result;
    }
}

/**
 * Check if a target sum can be achieved with exactly $count numbers from $scores
 * Uses dynamic programming approach
 */
function reactions_can_achieve_sum($target, $count, $scores)
{
    // DP array: dp[i][s] = true if sum s can be achieved with i numbers
    $dp = array();
    $dp[0] = array(0 => true);
    
    for($i = 1; $i <= $count; $i++)
    {
        $dp[$i] = array();
        foreach($dp[$i-1] as $prev_sum => $possible)
        {
            if($possible)
            {
                foreach($scores as $score)
                {
                    $new_sum = $prev_sum + $score;
                    $dp[$i][$new_sum] = true;
                }
            }
        }
    }
    
    return isset($dp[$count][$target]);
}

/**
 * Check if target sum can be achieved with mixed positive, negative and neutral scores
 */
function reactions_can_achieve_sum_mixed($target, $count, $positive_scores, $negative_scores)
{
    $all_scores = array_merge($positive_scores, $negative_scores);
    return reactions_can_achieve_sum($target, $count, $all_scores);
}

/**
 * Find valid scores (number of reactions) that can achieve the target points
 */
function reactions_find_valid_scores_for_points($points, $positive_scores, $negative_scores, $shuffle_type, $neutral_scores = array())
{
    $valid_scores = array();
    
    // Search range: from 1 to a reasonable upper limit
    $max_search = max($points * 2, 100);
    
    for($score = 1; $score <= $max_search; $score++)
    {
        $is_valid = false;
        
        if($shuffle_type == 'positive' || (empty($negative_scores) && empty($neutral_scores)))
        {
            // Positive-only check
            if(!empty($positive_scores))
            {
                $min_achievable = $score * min($positive_scores);
                $max_achievable = $score * max($positive_scores);
                
                if($points >= $min_achievable && $points <= $max_achievable)
                {
                    // Check if exact sum is achievable
                    if(reactions_can_achieve_sum($points, $score, $positive_scores))
                    {
                        $is_valid = true;
                    }
                }
            }
        }
        else
        {
            // Mixed mode check - include positive, negative and neutral
            $all_scores = array_merge($positive_scores, $negative_scores, $neutral_scores);
            if(!empty($all_scores))
            {
                $min_achievable = $score * min($all_scores);
                $max_achievable = $score * max($all_scores);
                
                if($points >= $min_achievable && $points <= $max_achievable)
                {
                    if(reactions_can_achieve_sum($points, $score, $all_scores))
                    {
                        $is_valid = true;
                    }
                }
            }
        }
        
        if($is_valid)
        {
            $valid_scores[] = $score;
            if(count($valid_scores) >= 15)
            {
                break; // Limit suggestions
            }
        }
    }
    
    return $valid_scores;
}

/**
 * Find exact combination of reactions that sum to target points with exactly count reactions
 * Uses dynamic programming with backtracking to construct the actual solution
 * For mixed mode, ensures variety by including negative and neutral reactions
 * 
 * @param int $target Target sum of reaction points
 * @param int $count Number of reactions needed
 * @param array $scores Available reaction score values
 * @param array $reactions Array of reaction objects to choose from
 * @param bool $ensure_variety If true, tries to include negative/neutral reactions for variety
 * @return array|false Array of reaction objects that sum to target, or false if impossible
 */
function reactions_find_exact_combination($target, $count, $scores, $reactions, $ensure_variety = true)
{
    // Separate reactions by type for variety
    $positive_reactions = array();
    $negative_reactions = array();
    $neutral_reactions = array();
    
    foreach($reactions as $r)
    {
        $s = (int)$r['reaction_score'];
        if($s > 0)
        {
            $positive_reactions[] = $r;
        }
        elseif($s < 0)
        {
            $negative_reactions[] = $r;
        }
        else
        {
            $neutral_reactions[] = $r;
        }
    }
    
    // If variety is requested and we have negative/neutral reactions, use a mixed approach
    if($ensure_variety && (!empty($negative_reactions) || !empty($neutral_reactions)))
    {
        // Try to allocate some slots to negative and neutral reactions
        $solution = array();
        
        // Decide how many of each type to include
        // Aim for: 60-70% positive, 15-25% negative, 10-15% neutral
        $neg_count = 0;
        $neutral_count = 0;
        
        if(!empty($negative_reactions))
        {
            // Allocate 15-25% to negative reactions
            $max_neg = min(count($negative_reactions) * 3, floor($count * 0.25));
            $min_neg = min(count($negative_reactions), max(1, floor($count * 0.15)));
            $neg_count = rand($min_neg, max($min_neg, $max_neg));
        }
        
        if(!empty($neutral_reactions))
        {
            // Allocate 10-15% to neutral reactions
            $max_neutral = min(count($neutral_reactions) * 3, floor($count * 0.15));
            $min_neutral = min(count($neutral_reactions), max(1, floor($count * 0.10)));
            $neutral_count = rand($min_neutral, max($min_neutral, $max_neutral));
        }
        
        // Make sure we don't exceed total count
        if($neg_count + $neutral_count >= $count)
        {
            $neg_count = floor($count * 0.2);
            $neutral_count = floor($count * 0.1);
        }
        
        $positive_count = $count - $neg_count - $neutral_count;
        
        // Calculate how many points we need from positive reactions
        // after accounting for negative reactions
        $points_from_negative = 0;
        for($i = 0; $i < $neg_count; $i++)
        {
            $r = $negative_reactions[array_rand($negative_reactions)];
            $solution[] = $r;
            $points_from_negative += $r['reaction_score'];
        }
        
        // Add neutral reactions (they don't affect points)
        for($i = 0; $i < $neutral_count; $i++)
        {
            $r = $neutral_reactions[array_rand($neutral_reactions)];
            $solution[] = $r;
        }
        
        // Now we need to achieve: $target - $points_from_negative with $positive_count reactions
        $needed_points = $target - $points_from_negative;
        
        // Use DP to find exact combination of positive reactions
        $positive_scores = array();
        foreach($positive_reactions as $r)
        {
            $positive_scores[] = (int)$r['reaction_score'];
        }
        $positive_scores = array_unique($positive_scores);
        
        $positive_solution = reactions_find_exact_combination_simple($needed_points, $positive_count, $positive_scores, $positive_reactions);
        
        if($positive_solution !== false)
        {
            // Combine all reactions
            $solution = array_merge($solution, $positive_solution);
            
            // Shuffle to mix them up
            shuffle($solution);
            
            return $solution;
        }
        
        // If that didn't work, fall back to simple approach
    }
    
    // Simple approach: just find any valid combination
    return reactions_find_exact_combination_simple($target, $count, $scores, $reactions);
}

/**
 * Simple DP-based exact combination finder (no variety constraints)
 */
function reactions_find_exact_combination_simple($target, $count, $scores, $reactions)
{
    // Build a map of score => reactions with that score
    $score_map = array();
    foreach($reactions as $r)
    {
        $s = (int)$r['reaction_score'];
        if(!isset($score_map[$s]))
        {
            $score_map[$s] = array();
        }
        $score_map[$s][] = $r;
    }
    
    // DP array: dp[i][s] = true if sum s can be achieved with i reactions
    // Also track parent for backtracking: parent[i][s] = [prev_sum, score_used]
    $dp = array();
    $parent = array();
    $dp[0] = array(0 => true);
    $parent[0] = array();
    
    for($i = 1; $i <= $count; $i++)
    {
        $dp[$i] = array();
        $parent[$i] = array();
        
        foreach($dp[$i-1] as $prev_sum => $possible)
        {
            if($possible)
            {
                foreach($scores as $score)
                {
                    $new_sum = $prev_sum + $score;
                    
                    // Only track if we haven't found this sum yet (first path wins)
                    if(!isset($dp[$i][$new_sum]))
                    {
                        $dp[$i][$new_sum] = true;
                        $parent[$i][$new_sum] = array('prev_sum' => $prev_sum, 'score' => $score);
                    }
                }
            }
        }
    }
    
    // Check if target is achievable
    if(!isset($dp[$count][$target]))
    {
        return false;
    }
    
    // Backtrack to build the solution
    $solution = array();
    $current_sum = $target;
    
    for($i = $count; $i >= 1; $i--)
    {
        if(!isset($parent[$i][$current_sum]))
        {
            return false; // Should not happen
        }
        
        $step = $parent[$i][$current_sum];
        $score_used = $step['score'];
        
        // Pick a random reaction with this score
        if(isset($score_map[$score_used]) && !empty($score_map[$score_used]))
        {
            $reaction = $score_map[$score_used][array_rand($score_map[$score_used])];
            $solution[] = $reaction;
        }
        else
        {
            return false; // Should not happen
        }
        
        $current_sum = $step['prev_sum'];
    }
    
    return $solution;
}

// Load language file
if(!isset($lang->reactions_success_give_points))
{
    $lang->load("reactions");
}

// Give reaction points to user
if($mybb->request_method == "post")
{
    $user_ids = explode(',', $mybb->input['user_ids']);
    $user_ids = array_map('trim', $user_ids);
    $user_ids = array_filter($user_ids, 'is_numeric');
    $user_ids = array_map('intval', $user_ids);
    
    $points = (int)$mybb->input['points'];
    $score = (int)$mybb->input['score'];
    $reaction_id = $mybb->input['reaction_id']; // Can be 'shuffle' or numeric
    $shuffle_type = $mybb->input['shuffle_type']; // 'positive' or 'mixed'
    $is_custom_score = (int)$mybb->input['is_custom_score'];
    
    if(empty($user_ids))
    {
        flash_message($lang->reactions_error_invalid_user_ids, "error");
        admin_redirect("index.php?module=config-reactions&action=give_points");
    }
    
    if($points <= 0)
    {
        flash_message($lang->reactions_error_points_must_be_positive, "error");
        admin_redirect("index.php?module=config-reactions&action=give_points");
    }
    
    // If custom score not set, use points as score
    if(!$is_custom_score)
    {
        $score = $points;
    }
    
    // Get all active reactions for shuffle or specific selection
    $all_reactions = array();
    $query = $db->simple_select("reactions", "*", "active=1");
    while($row = $db->fetch_array($query))
    {
        $all_reactions[$row['reaction_id']] = $row;
    }
    
    // Build the list of reactions to use
    // Separate positive, negative and neutral reactions for mixed mode
    $positive_reactions = array();
    $negative_reactions = array();
    $neutral_reactions = array();
    
    foreach($all_reactions as $r)
    {
        $r_score = (int)$r['reaction_score'];
        if($r_score > 0)
        {
            $positive_reactions[] = $r;
        }
        elseif($r_score < 0)
        {
            $negative_reactions[] = $r;
        }
        else
        {
            $neutral_reactions[] = $r;
        }
    }
    
    // Determine which reactions to use based on mode
    $reactions_to_use = array();
    
    if($reaction_id === 'shuffle')
    {
        if($shuffle_type == 'positive')
        {
            $reactions_to_use = $positive_reactions;
        }
        else // mixed
        {
            // Include positive and neutral reactions only (exclude negative)
            $reactions_to_use = array_merge($positive_reactions, $neutral_reactions);
        }
        
        if(empty($reactions_to_use))
        {
            flash_message($lang->reactions_error_no_shuffle_reactions, "error");
            admin_redirect("index.php?module=config-reactions&action=give_points");
        }
    }
    else
    {
        // Specific reaction mode
        $reaction_id = (int)$reaction_id;
        if(isset($all_reactions[$reaction_id]))
        {
            $reactions_to_use[] = $all_reactions[$reaction_id];
        }
    }
    
    if(empty($reactions_to_use))
    {
        flash_message($lang->reactions_error_no_valid_reactions, "error");
        admin_redirect("index.php?module=config-reactions&action=give_points");
    }
    
    // Calculate reactions needed
    // $score = number of reactions to create (Reaction Score count)
    // $points = target sum of reaction point values (Reaction Points)
    
    // Validate if the combination is achievable
    // For mixed mode, exclude negative reactions from validation
    $validation_negative = ($shuffle_type == 'mixed') ? array() : $negative_reactions;
    $validation_result = reactions_validate_points_score($points, $score, $positive_reactions, $validation_negative, $shuffle_type, $neutral_reactions);
    if(!$validation_result['valid'])
    {
        $suggestions = implode(', ', array_slice($validation_result['suggestions'], 0, 10));
        flash_message($lang->sprintf($lang->reactions_error_score_not_achievable, $points, $score, $suggestions), "error");
        admin_redirect("index.php?module=config-reactions&action=give_points");
    }
    
    $reaction_entries = array();
    
    if($reaction_id === 'shuffle')
    {
        // For shuffle mode, we need to select $score reactions that sum to $points
        // This is a variation of the subset sum problem
        
        if($shuffle_type == 'positive' || empty($negative_reactions))
        {
            // Positive only mode: use simple distribution
            // Try to get as close to $points as possible with $score reactions
            $target_avg = $points / $score;
            
            // Sort positive reactions by how close they are to target average
            usort($positive_reactions, function($a, $b) use ($target_avg) {
                $diff_a = abs($a['reaction_score'] - $target_avg);
                $diff_b = abs($b['reaction_score'] - $target_avg);
                return $diff_a - $diff_b;
            });
            
            $current_sum = 0;
            for($i = 0; $i < $score; $i++)
            {
                $remaining_count = $score - $i;
                $remaining_points = $points - $current_sum;
                $needed_avg = $remaining_points / $remaining_count;
                
                // Find best reaction to add
                $best_reaction = null;
                $best_diff = PHP_FLOAT_MAX;
                
                foreach($positive_reactions as $r)
                {
                    $diff = abs($r['reaction_score'] - $needed_avg);
                    if($diff < $best_diff)
                    {
                        $best_diff = $diff;
                        $best_reaction = $r;
                    }
                }
                
                if($best_reaction)
                {
                    $reaction_entries[] = $best_reaction;
                    $current_sum += $best_reaction['reaction_score'];
                }
            }
        }
        else
        {
            // Mixed mode: Use dynamic programming to find exact combination
            // that sums to target points with exactly $score reactions
            
            // Build array of available reaction scores for DP
            $available_scores = array();
            foreach($reactions_to_use as $r)
            {
                $available_scores[] = (int)$r['reaction_score'];
            }
            $available_scores = array_unique($available_scores);
            
            // Use DP to find a valid combination
            $solution = reactions_find_exact_combination($points, $score, $available_scores, $reactions_to_use);
            
            if($solution !== false)
            {
                // Solution found - use it
                $reaction_entries = $solution;
            }
            else
            {
                // Fallback: This shouldn't happen if validation passed, but handle it
                // Try to get as close as possible
                $current_sum = 0;
                for($i = 0; $i < $score; $i++)
                {
                    $remaining = $score - $i;
                    $needed = $points - $current_sum;
                    $avg_needed = ($remaining > 0) ? $needed / $remaining : 0;
                    
                    // Find reaction closest to what we need
                    $best_reaction = null;
                    $best_diff = PHP_FLOAT_MAX;
                    
                    foreach($reactions_to_use as $r)
                    {
                        $diff = abs($r['reaction_score'] - $avg_needed);
                        if($diff < $best_diff)
                        {
                            $best_diff = $diff;
                            $best_reaction = $r;
                        }
                    }
                    
                    if($best_reaction)
                    {
                        $reaction_entries[] = $best_reaction;
                        $current_sum += $best_reaction['reaction_score'];
                    }
                }
            }
            
            // Shuffle the final array so all reaction types appear mixed throughout
            shuffle($reaction_entries);
        }
    }
    else
    {
        // Single reaction mode - create exactly $score reactions with this reaction type
        // The actual points will be: $score * reaction_score
        $reaction = $reactions_to_use[0];
        
        for($i = 0; $i < $score; $i++)
        {
            $reaction_entries[] = $reaction;
        }
    }
    
    // Process each user
    $success_count = 0;
    $error_users = array();
    
    foreach($user_ids as $uid)
    {
        $user = get_user($uid);
        if(!$user)
        {
            $error_users[] = $uid;
            continue;
        }
        
        // Add reaction entries with unique content_id to avoid duplicate key errors
        // Use microtime to generate unique IDs within int range
        $base_id = (int)(microtime(true) * 1000) % 1000000; // Keep within reasonable range
        $entry_counter = 0;
        
        foreach($reaction_entries as $reaction)
        {
            // Create unique content_id: base + (user_id * 100) + counter
            // This stays well within int(10) unsigned max (4 billion)
            $unique_content_id = $base_id + ($uid * 100) + $entry_counter;
            
            // Ensure it doesn't exceed max int
            if($unique_content_id > 2147483647) {
                $unique_content_id = $unique_content_id % 2147483647;
            }
            
            $reaction_content_id = $db->insert_query("reaction_content", array(
                'reaction_id' => $reaction['reaction_id'],
                'content_type' => 'admin_gift',
                'content_id' => $unique_content_id,
                'reaction_user_id' => $mybb->user['uid'], // Admin who gave the points
                'content_user_id' => $uid,
                'reaction_date' => TIME_NOW,
                'is_counted' => 1
            ));
            
            // Add reputation entry if integration is enabled and gifted reactions are allowed
            if(!empty($mybb->settings['reactions_integrate_reputation']) && !empty($mybb->settings['reactions_gift_reputation']))
            {
                reactions_add_reputation_entry(
                    $reaction['reaction_id'],
                    'admin_gift',
                    $unique_content_id,
                    $mybb->user['uid'],
                    $uid,
                    $reaction_content_id
                );
            }
            
            $entry_counter++;
        }
        
        // Update user reaction scores
        reactions_update_user_reaction_scores($uid);
        
        $success_count++;
    }
    
    $message = $lang->sprintf($lang->reactions_success_give_points, $points, $score, $success_count, count($reaction_entries));
    if(!empty($error_users))
    {
        $message .= " Failed to find users: " . implode(', ', $error_users);
    }
    
    flash_message($message, "success");
    admin_redirect("index.php?module=config-reactions&action=give_points");
}

$page->output_header($lang->reactions_give_points_title);

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

$page->output_nav_tabs($sub_tabs, "give_points");

// Get all active reactions for the dropdown
$reactions = array();
$positive_reactions = array();
$all_reaction_scores = array();

$query = $db->simple_select("reactions", "*", "active=1", array("order_by" => "display_order", "order_dir" => "ASC"));
while($row = $db->fetch_array($query))
{
    $reactions[$row['reaction_id']] = $row;
    $all_reaction_scores[] = (int)$row['reaction_score'];
    
    if($row['reaction_type'] == 'positive')
    {
        $positive_reactions[] = $row;
    }
}

// Build reaction options for dropdown
$reaction_options = array();
foreach($reactions as $r)
{
    $reaction_options[$r['reaction_id']] = $r['title'] . " (Points: " . $r['reaction_score'] . ")";
}
// Add shuffle option at the beginning
$reaction_options = array('shuffle' => '★ Shuffle (Multiple reaction types)') + $reaction_options;

// Get reactions with 1 point for the hint
$one_point_reactions = array();
foreach($reactions as $r)
{
    if($r['reaction_score'] == 1)
    {
        $one_point_reactions[] = $r['title'];
    }
}

$form = new Form("index.php?module=config-reactions&amp;action=give_points", "post");
$form_container = new FormContainer($lang->reactions_give_points_title);

// User IDs
$form_container->output_row($lang->reactions_user_ids, $lang->reactions_user_ids_desc, $form->generate_text_box("user_ids", "", array('id' => 'user_ids', 'style' => 'width: 300px;')), 'user_ids');

// Reaction Points (sum of reaction point values)
$form_container->output_row($lang->reactions_points_to_give, $lang->reactions_reaction_points_form_desc, $form->generate_numeric_field("points", 100, array('id' => 'points', 'style' => 'width: 100px;', 'min' => 1)), 'points');

// Same score checkbox
$form_container->output_row("", "", "<label><input type='checkbox' id='same_as_points' name='same_as_points' value='1' checked> ".$lang->reactions_same_as_points_checkbox."</label>");

// Custom Score (disabled by default until checkbox unchecked)
$form_container->output_row($lang->reactions_score, $lang->reactions_reaction_score_form_desc, $form->generate_numeric_field("score", "", array('id' => 'score', 'style' => 'width: 100px;', 'min' => 1, 'disabled' => true)), 'score');

// Validation message area
$form_container->output_row("", "", "<div id='validation_message' style='color: green; font-weight: bold;'></div>");

// Reaction Image Selection
$form_container->output_row($lang->reactions_reaction_image, $lang->reactions_reaction_image_desc, $form->generate_select_box("reaction_id", $reaction_options, '', array('id' => 'reaction_id')), 'reaction_id');

// Shuffle Type Selection (hidden by default)
$shuffle_options = array(
    'positive' => $lang->reactions_shuffle_positive,
    'mixed' => $lang->reactions_shuffle_mixed
);
$form_container->output_row($lang->reactions_shuffle_type, $lang->reactions_shuffle_type_desc, $form->generate_select_box("shuffle_type", $shuffle_options, 'positive', array('id' => 'shuffle_type')), 'shuffle_type', array('id' => 'shuffle_type_row', 'style' => 'display: none;'));

// Available 1-point reactions hint
if(!empty($one_point_reactions))
{
    $form_container->output_row("", "", "<small style='color: #666;'>".$lang->reactions_one_point_hint.": " . implode(', ', $one_point_reactions) . "</small>");
}

// Hidden field for custom score flag
echo $form->generate_hidden_field("is_custom_score", "0", array('id' => 'is_custom_score'));

$form_container->end();

$buttons[] = $form->generate_submit_button($lang->reactions_submit_give_points);
$form->output_submit_wrapper($buttons);
$form->end();

// Add JavaScript for the interactive logic
echo "
<script type='text/javascript'>
var reactionsAdminLang = " . json_encode(array(
    'no_reactions_shuffle' => $lang->reactions_js_no_reactions_shuffle,
    'will_create_points' => $lang->reactions_js_will_create_points,
    'will_create_selected_type' => $lang->reactions_js_will_create_selected_type,
    'enter_custom_score' => $lang->reactions_js_enter_custom_score,
    'score_max' => $lang->reactions_js_score_max,
    'no_positive_reactions' => $lang->reactions_js_no_positive_reactions,
    'score_not_achievable' => $lang->reactions_js_score_not_achievable,
    'suggested_scores' => $lang->reactions_js_suggested_scores,
    'cannot_achieve_points' => $lang->reactions_js_cannot_achieve_points,
    'will_create_avg' => $lang->reactions_js_will_create_avg,
    'exact_not_achievable' => $lang->reactions_js_exact_not_achievable,
    'no_reactions_available' => $lang->reactions_js_no_reactions_available,
    'will_create_avg_mixed' => $lang->reactions_js_will_create_avg_mixed,
    'will_create_type' => $lang->reactions_js_will_create_type,
    'selected_reaction_warning' => $lang->reactions_js_selected_reaction_warning,
    'please_select_reaction' => $lang->reactions_js_please_select_reaction,
    'will_create_points_simple' => $lang->reactions_js_will_create_points_simple,
    'enter_custom_score_above' => $lang->reactions_js_enter_custom_score_above
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
    var pointsInput = document.getElementById('points');
    var scoreInput = document.getElementById('score');
    var sameAsPointsCheckbox = document.getElementById('same_as_points');
    var validationDiv = document.getElementById('validation_message');
    var reactionSelect = document.getElementById('reaction_id');
    var shuffleTypeRow = document.getElementById('shuffle_type_row');
    var shuffleTypeSelect = document.getElementById('shuffle_type');
    var isCustomScoreField = document.getElementById('is_custom_score');
    var scoreHelpText = document.getElementById('score_help_text');
    
    // Reaction data from PHP
    var allReactions = " . json_encode($reactions) . ";
    var positiveReactionsRaw = " . json_encode($positive_reactions) . ";
    var allReactionScoresRaw = " . json_encode(array_unique($all_reaction_scores)) . ";
    
    // Separate reactions by type and score for calculations
    var reactions = {}; // All reactions (for mixed mode)
    var positiveReactions = []; // Positive type reactions
    var positiveScoreReactions = []; // Reactions with positive scores
    var negativeScoreReactions = []; // Reactions with negative scores
    var allReactionScores = [];
    var positiveOnlyScores = [];
    
    for(var id in allReactions) {
        if(allReactions.hasOwnProperty(id)) {
            var r = allReactions[id];
            var rScore = parseInt(r.reaction_score);
            reactions[id] = r;
            allReactionScores.push(rScore);
            
            if(r.reaction_type === 'positive') {
                positiveReactions.push(r);
            }
            
            if(rScore > 0) {
                positiveScoreReactions.push(r);
                positiveOnlyScores.push(rScore);
            } else if(rScore < 0) {
                negativeScoreReactions.push(r);
            }
        }
    }
    allReactionScores = [...new Set(allReactionScores)];
    positiveOnlyScores = [...new Set(positiveOnlyScores)];
    
    // Show/hide shuffle type based on reaction selection
    reactionSelect.addEventListener('change', function() {
        if(this.value === 'shuffle') {
            shuffleTypeRow.style.display = '';
        } else {
            shuffleTypeRow.style.display = 'none';
        }
        calculateValidScores();
    });
    
    shuffleTypeSelect.addEventListener('change', calculateValidScores);
    
    // Check if a target sum can be achieved with exactly 'count' numbers from 'scores'
    // Uses dynamic programming: dp[i][s] = true if sum s can be achieved with i numbers
    function canAchieveSum(target, count, scores) {
        // DP array using Sets to track achievable sums for each count
        var dp = [];
        dp[0] = new Set([0]);
        
        for(var i = 1; i <= count; i++) {
            dp[i] = new Set();
            dp[i-1].forEach(function(prevSum) {
                scores.forEach(function(score) {
                    var newSum = prevSum + score;
                    // Limit the range to avoid excessive computation
                    if(Math.abs(newSum - target) <= Math.abs(target) + 1000) {
                        dp[i].add(newSum);
                    }
                });
            });
            
            // Early termination check
            if(dp[i].has(target)) {
                return true;
            }
        }
        
        return dp[count].has(target);
    }
    
    // Find valid scores (number of reactions) that can achieve the target points
    function findValidScoresForPoints(points, scores, maxSearch) {
        var valid = [];
        maxSearch = maxSearch || Math.max(points * 2, 100);
        
        for(var score = 1; score <= maxSearch; score++) {
            // Quick range check first
            var minScore = Math.min(...scores);
            var maxScore = Math.max(...scores);
            var minAchievable = score * minScore;
            var maxAchievable = score * maxScore;
            
            if(points >= minAchievable && points <= maxAchievable) {
                // Check if exact sum is achievable
                if(canAchieveSum(points, score, scores)) {
                    valid.push(score);
                    if(valid.length >= 15) break;
                }
            }
        }
        
        return valid;
    }
    
    function calculateValidScores() {
        var points = parseInt(pointsInput.value) || 0;
        var score = parseInt(scoreInput.value) || 0;
        var isShuffle = (reactionSelect.value === 'shuffle');
        var shuffleType = shuffleTypeSelect.value;
        
        if(points <= 0) {
            validationDiv.innerHTML = '';
            return;
        }
        
        var message = '';
        
        if(sameAsPointsCheckbox.checked) {
            // Score = Points (number of reactions equals the points value)
            if(isShuffle) {
                var availableReactions;
                if(shuffleType === 'positive') {
                    availableReactions = positiveReactions;
                } else if(shuffleType === 'mixed') {
                    // For mixed mode, combine positive and neutral reactions only (exclude negative)
                    availableReactions = {};
                    for(var id in allReactions) {
                        if(allReactions.hasOwnProperty(id)) {
                            var r = allReactions[id];
                            var rScore = parseInt(r.reaction_score);
                            if(rScore >= 0) { // Only non-negative (positive and neutral)
                                availableReactions[id] = r;
                            }
                        }
                    }
                } else {
                    availableReactions = reactions;
                }
                var reactionList = [];
                for(var id in availableReactions) {
                    reactionList.push(availableReactions[id]);
                }
                
                if(reactionList.length === 0) {
                    message = '<span style=\"color: red;\">✗ ' + rl('no_reactions_shuffle', 'No reactions available for shuffle.') + '</span>';
                } else {
                    message = '<span style=\"color: green;\">✓ ' + rf('will_create_points', 'Will create {1} reactions with total sum of {2} points (average 1 point per reaction).', points, points) + '</span>';
                }
            } else {
                message = '<span style=\"color: green;\">✓ ' + rf('will_create_selected_type', 'Will create {1} reactions with the selected reaction type.', points) + '</span>';
            }
        } else {
            // Custom score
            if(score <= 0) {
                message = '<span style=\"color: orange;\">' + rl('enter_custom_score', 'Enter a custom score value (number of reactions).') + '</span>';
            } else if(score > 10000) {
                message = '<span style=\"color: red;\">✗ ' + rl('score_max', 'Score (number of reactions) cannot exceed 10000.') + '</span>';
            } else {
                var avgPoints = points / score;
                
                if(isShuffle) {
                    if(shuffleType === 'positive') {
                        // Positive only mode
                        if(positiveOnlyScores.length === 0) {
                            message = '<span style=\"color: red;\">✗ ' + rl('no_positive_reactions', 'No positive reactions available.') + '</span>';
                        } else {
                            var minPositive = Math.min(...positiveOnlyScores);
                            var maxPositive = Math.max(...positiveOnlyScores);
                            var minAchievable = score * minPositive;
                            var maxAchievable = score * maxPositive;
                            
                            // Check if target is within range
                            if(points < minAchievable || points > maxAchievable) {
                                // Out of range - find valid scores
                                var validScores = findValidScoresForPoints(points, positiveOnlyScores);
                                if(validScores.length > 0) {
                                    message = '<span style=\"color: red;\">✗ ' + rf('score_not_achievable', 'For showing {1} points, score {2} is not achievable.', points, score) + '</span><br>';
                                    message += '<span style=\"color: green;\">' + rf('suggested_scores', 'Suggested valid scores: {1}', validScores.join(', ')) + '</span>';
                                } else {
                                    message = '<span style=\"color: red;\">✗ ' + rf('cannot_achieve_points', 'Cannot achieve {1} points with any number of reactions.', points) + '</span>';
                                }
                            } else {
                                // Within range, check exact achievability
                                if(canAchieveSum(points, score, positiveOnlyScores)) {
                                    message = '<span style=\"color: green;\">✓ ' + rf('will_create_avg', 'Will create {1} reactions with total sum of {2} points (average {3} per reaction).', score, points, avgPoints.toFixed(2)) + '</span>';
                                } else {
                                    // Cannot achieve exact sum
                                    var validScores = findValidScoresForPoints(points, positiveOnlyScores);
                                    if(validScores.length > 0) {
                                        message = '<span style=\"color: red;\">✗ ' + rf('score_not_achievable', 'For showing {1} points, score {2} is not achievable.', points, score) + '</span><br>';
                                        message += '<span style=\"color: green;\">' + rf('suggested_scores', 'Suggested valid scores: {1}', validScores.join(', ')) + '</span>';
                                    } else {
                                        message = '<span style=\"color: orange;\">⚠ ' + rf('exact_not_achievable', 'Exact combination not achievable. Will get close to {1} points.', points) + '</span>';
                                    }
                                }
                            }
                        }
                    } else {
                        // Mixed mode - positive and neutral reactions only (no negative)
                        // Build array of positive and neutral scores only
                        var mixedScores = [];
                        for(var id in allReactions) {
                            if(allReactions.hasOwnProperty(id)) {
                                var rScore = parseInt(allReactions[id].reaction_score);
                                if(rScore >= 0) { // Only non-negative (positive and neutral)
                                    mixedScores.push(rScore);
                                }
                            }
                        }
                        mixedScores = [...new Set(mixedScores)];

                        if(mixedScores.length === 0) {
                            message = '<span style=\"color: red;\">✗ ' + rl('no_reactions_available', 'No reactions available.') + '</span>';
                        } else {
                            var minScore = Math.min(...mixedScores);
                            var maxScore = Math.max(...mixedScores);
                            var minAchievable = score * minScore;
                            var maxAchievable = score * maxScore;

                            // Check if target is within range
                            if(points < minAchievable || points > maxAchievable) {
                                var validScores = findValidScoresForPoints(points, mixedScores);
                                if(validScores.length > 0) {
                                    message = '<span style=\"color: red;\">✗ ' + rf('score_not_achievable', 'For showing {1} points, score {2} is not achievable.', points, score) + '</span><br>';
                                    message += '<span style=\"color: green;\">' + rf('suggested_scores', 'Suggested valid scores: {1}', validScores.join(', ')) + '</span>';
                                } else {
                                    message = '<span style=\"color: red;\">✗ ' + rf('cannot_achieve_points', 'Cannot achieve {1} points with any number of reactions.', points) + '</span>';
                                }
                            } else {
                                // Within range, check exact achievability
                                if(canAchieveSum(points, score, mixedScores)) {
                                    message = '<span style=\"color: green;\">✓ ' + rf('will_create_avg_mixed', 'Will create {1} reactions with total sum of {2} points (average {3} per reaction) using mixed positive and neutral reactions.', score, points, avgPoints.toFixed(2)) + '</span>';
                                } else {
                                    // Cannot achieve exact sum
                                    var validScores = findValidScoresForPoints(points, mixedScores);
                                    if(validScores.length > 0) {
                                        message = '<span style=\"color: red;\">✗ ' + rf('score_not_achievable', 'For showing {1} points, score {2} is not achievable.', points, score) + '</span><br>';
                                        message += '<span style=\"color: green;\">' + rf('suggested_scores', 'Suggested valid scores: {1}', validScores.join(', ')) + '</span>';
                                    } else {
                                        message = '<span style=\"color: orange;\">⚠ ' + rf('exact_not_achievable', 'Exact combination not achievable. Will get close to {1} points.', points) + '</span>';
                                    }
                                }
                            }
                        }
                    }
                } else {
                    // Single reaction mode
                    var selectedReactionId = reactionSelect.value;
                    if(allReactions[selectedReactionId]) {
                        var r = allReactions[selectedReactionId];
                        var actualPoints = r.reaction_score * score;
                        if(actualPoints == points) {
                            message = '<span style=\"color: green;\">✓ ' + rf('will_create_type', 'Will create {1} reactions of type &quot;{2}&quot; for total of {3} points.', score, r.title, actualPoints) + '</span>';
                        } else {
                            message = '<span style=\"color: orange;\">⚠ ' + rf('selected_reaction_warning', 'Selected reaction gives {1} points each. {2} reactions will give {3} points (requested: {4}).', r.reaction_score, score, actualPoints, points) + '</span>';
                        }
                    } else {
                        message = '<span style=\"color: red;\">✗ ' + rl('please_select_reaction', 'Please select a reaction type.') + '</span>';
                    }
                }
            }
        }
        
        validationDiv.innerHTML = message;
    }
    
    // Event listeners
    sameAsPointsCheckbox.addEventListener('change', function() {
        if(this.checked) {
            scoreInput.disabled = true;
            scoreInput.value = pointsInput.value;
            isCustomScoreField.value = '0';
            scoreHelpText.style.display = 'none';
            validationDiv.innerHTML = '<span style=\"color: #666;\">✓ ' + rf('will_create_points_simple', 'Will create {1} reactions with total sum of {2} points.', pointsInput.value, pointsInput.value) + '</span>';
        } else {
            scoreInput.disabled = false;
            scoreInput.focus();
            isCustomScoreField.value = '1';
            scoreHelpText.style.display = 'inline';
            // Don't set a default value - let user enter their own
            validationDiv.innerHTML = '<span style=\"color: orange;\">' + rl('enter_custom_score_above', 'Enter a custom score value above') + '</span>';
        }
    });
    
    pointsInput.addEventListener('input', function() {
        if(sameAsPointsCheckbox.checked) {
            scoreInput.value = this.value;
        }
        calculateValidScores();
    });
    
    scoreInput.addEventListener('input', function() {
        // Force validation update when score changes
        calculateValidScores();
    });
    
    // Initial state - show appropriate message
    var initialPoints = parseInt(pointsInput.value) || 0;
    validationDiv.innerHTML = '<span style=\"color: #666;\">✓ ' + rf('will_create_points_simple', 'Will create {1} reactions with total sum of {2} points.', initialPoints, initialPoints) + '</span>';
    
    // Initial state - check if shuffle is selected to show/hide shuffle type row
    if(reactionSelect.value === 'shuffle') {
        shuffleTypeRow.style.display = '';
    } else {
        shuffleTypeRow.style.display = 'none';
    }
    calculateValidScores();
});

</script>
";

$page->output_footer();
