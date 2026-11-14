<?php
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

/**
 * MyAlerts Integration for Reactions Plugin
 * 
 * This file provides integration with the MyAlerts plugin for notification alerts.
 */

/**
 * Check whether MyAlerts is installed and active
 * @param boolean $check_activated Also check if MyAlerts is activated
 * @param boolean $check_reaction_registered Check if reaction alert type is registered
 * @param boolean $check_reaction_enabled Check if reaction alert type is enabled
 * @return boolean True if all checks pass
 */
function reactions_have_myalerts($check_activated = false, $check_reaction_registered = false, $check_reaction_enabled = false)
{
    global $db, $cache, $plugins_cache;
    
    // Check if MyAlerts class files exist
    if(!file_exists(MYBB_ROOT . 'inc/plugins/MybbStuff/Core/ClassLoader.php') ||
       !file_exists(MYBB_ROOT . 'inc/plugins/MybbStuff/MyAlerts/src/AlertTypeManager.php'))
    {
        return false;
    }
    
    // Check if MyAlerts is in the plugin cache (activated)
    if($check_activated)
    {
        // Reload cache if not set (can happen during AJAX requests)
        if(!isset($plugins_cache['active']) || !isset($plugins_cache['active']['myalerts']))
        {
            $plugins_cache = $cache->read('plugins');
        }
        
        if(!isset($plugins_cache['active']) || !isset($plugins_cache['active']['myalerts']))
        {
            return false;
        }
    }
    
    // Check if reaction alert type is registered
    if($check_reaction_registered || $check_reaction_enabled)
    {
        if(!class_exists('MybbStuff_MyAlerts_AlertTypeManager'))
        {
            return false;
        }
        
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
        if(!$alertTypeManager)
        {
            return false;
        }
        
        $alertType = $alertTypeManager->getByCode('reaction');
        if($alertType === null)
        {
            return false;
        }
        
        // Check if enabled
        if($check_reaction_enabled && !$alertType->getEnabled())
        {
            return false;
        }
    }
    
    return true;
}

/**
 * Integrate with MyAlerts - register the reaction alert types
 * @return boolean True if successful
 */
function reactions_myalerts_integrate()
{
    global $db, $cache;
    
    // Only proceed if MyAlerts classes are available
    if(!class_exists('MybbStuff_MyAlerts_AlertTypeManager'))
    {
        return false;
    }
    
    $ret = false;
    
    if(reactions_have_myalerts(true))
    {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
        
        // Register reaction added alert type
        $alertType = $alertTypeManager->getByCode('reaction');
        if($alertType === null)
        {
            $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
            $alertType->setCode('reaction');
            $alertType->setEnabled(true);
            $alertType->setCanBeUserDisabled(false);
            $alertType->setDefaultUserEnabled(true);
            $alertTypeManager->add($alertType);
            $ret = true;
        }
        else if(!$alertType->getEnabled())
        {
            // Enable it
            $id = $alertType->getId();
            if($id > 0)
            {
                $db->update_query('alert_types', array('enabled' => 1), "id = {$id}");
                $alertTypeManager->getAlertTypes(true); // Force refresh
                $ret = true;
            }
        }
        
        // Register reaction removed alert type
        $alertTypeRemoved = $alertTypeManager->getByCode('reaction_removed');
        $is_new_alert_type = false;
        if($alertTypeRemoved === null)
        {
            $alertTypeRemoved = new MybbStuff_MyAlerts_Entity_AlertType();
            $alertTypeRemoved->setCode('reaction_removed');
            $alertTypeRemoved->setEnabled(true);
            $alertTypeRemoved->setCanBeUserDisabled(false);
            $alertTypeRemoved->setDefaultUserEnabled(false); // Unchecked by default
            $alertTypeManager->add($alertTypeRemoved);
            $is_new_alert_type = true;
            $ret = true;
        }
        else if(!$alertTypeRemoved->getEnabled())
        {
            // Enable it
            $id = $alertTypeRemoved->getId();
            if($id > 0)
            {
                $db->update_query('alert_types', array('enabled' => 1), "id = {$id}");
                $alertTypeManager->getAlertTypes(true); // Force refresh
                $ret = true;
            }
        }
        
        // If we just created the reaction_removed alert type, initialize existing users
        // Add reaction_removed to their disabled list so it appears unchecked by default
        if($is_new_alert_type)
        {
            // Get the ID of the newly created alert type
            $alertTypeRemoved = $alertTypeManager->getByCode('reaction_removed');
            if($alertTypeRemoved !== null)
            {
                $removed_id = $alertTypeRemoved->getId();
                
                // Find all users without any disabled alert preferences or empty preferences
                // and add reaction_removed to their disabled list
                $query = $db->simple_select('users', 'uid, myalerts_disabled_alert_types', "myalerts_disabled_alert_types IS NULL OR myalerts_disabled_alert_types = '' OR myalerts_disabled_alert_types = '[]'");
                while($user = $db->fetch_array($query))
                {
                    // Initialize with reaction_removed in disabled list
                    $db->update_query('users', array(
                        'myalerts_disabled_alert_types' => $db->escape_string(json_encode(array($removed_id)))
                    ), 'uid = ' . (int)$user['uid']);
                }
            }
        }
    }
    
    return $ret;
}

/**
 * Enable or disable the reaction alert type
 * @param int $enabled 1 to enable, 0 to disable
 */
function reactions_myalerts_set_enabled($enabled)
{
    global $db;
    
    // Only proceed if MyAlerts classes are available
    if(!class_exists('MybbStuff_MyAlerts_AlertTypeManager'))
    {
        return;
    }
    
    if(reactions_have_myalerts())
    {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
        if($alertTypeManager)
        {
            $alertType = $alertTypeManager->getByCode('reaction');
            if($alertType !== null)
            {
                $id = $alertType->getId();
                if($id > 0)
                {
                    $db->update_query('alert_types', array('enabled' => (int)$enabled), "id = {$id}");
                    $alertTypeManager->getAlertTypes(true); // Force refresh
                }
            }
        }
    }
}

/**
 * Unintegrate from MyAlerts - delete alert types and all related alerts
 */
function reactions_myalerts_unintegrate()
{
    global $db;
    
    // Only proceed if MyAlerts classes are available
    if(!class_exists('MybbStuff_MyAlerts_AlertTypeManager'))
    {
        return;
    }
    
    if(reactions_have_myalerts())
    {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
        if($alertTypeManager)
        {
            // Delete reaction added alert type
            $alertType = $alertTypeManager->getByCode('reaction');
            if($alertType !== null)
            {
                $id = $alertType->getId();
                if($id > 0)
                {
                    // Delete alert type
                    $alertTypeManager->deleteById($id);
                    
                    // Delete all related alerts
                    if($db->table_exists('alerts'))
                    {
                        $db->delete_query('alerts', "alert_type_id = {$id}");
                    }
                }
            }
            
            // Delete reaction removed alert type
            $alertTypeRemoved = $alertTypeManager->getByCode('reaction_removed');
            if($alertTypeRemoved !== null)
            {
                $id = $alertTypeRemoved->getId();
                if($id > 0)
                {
                    // Delete alert type
                    $alertTypeManager->deleteById($id);
                    
                    // Delete all related alerts
                    if($db->table_exists('alerts'))
                    {
                        $db->delete_query('alerts', "alert_type_id = {$id}");
                    }
                }
            }
        }
    }
}

/**
 * Register MyAlerts formatter
 * Hook: myalerts_register_client_alert_formatters
 */
function reactions_register_myalerts_formatter()
{
    global $mybb, $lang;
    
    // Don't register if MyAlerts integration is disabled in settings
    if(empty($mybb->settings['reactions_integrate_myalerts']))
    {
        return;
    }
    
    if(reactions_have_myalerts(true, true, true) &&
       class_exists('MybbStuff_MyAlerts_Formatter_AbstractFormatter') &&
       class_exists('MybbStuff_MyAlerts_AlertFormatterManager') &&
       !class_exists('ReactionsAlertFormatter'))
    {
        class ReactionsAlertFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
        {
            public function init()
            {
                if(!$this->lang->reactions)
                {
                    $this->lang->load('reactions');
                }
            }
            
            public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
            {
                global $db;
                
                $extraDetails = $alert->getExtraDetails();
                
                // Get reaction details
                $reaction_id = isset($extraDetails['reaction_id']) ? (int)$extraDetails['reaction_id'] : 0;
                $reaction = reactions_get_reaction($reaction_id);
                
                if(!$reaction)
                {
                    return $this->lang->sprintf($this->lang->myalerts_reactions_fallback, $outputAlert['from_user']);
                }
                
                // Build reaction image HTML (16x16 for alerts)
                $reaction_image = '';
                if($reaction['sprite_mode'] && !empty($reaction['sprite_params']))
                {
                    $sprite_params = json_decode($reaction['sprite_params'], true);
                    $reaction_image = '<span class="reaction-sprite" style="display: inline-block; width: 16px; height: 16px; background-image: url(' . htmlspecialchars_uni($reaction['image_url']) . '); background-position: -' . (int)$sprite_params['x'] . 'px -' . (int)$sprite_params['y'] . 'px; background-size: ' . (int)$sprite_params['w'] . 'px ' . (int)$sprite_params['h'] . 'px; vertical-align: middle; margin: 0 3px;"></span>';
                }
                else
                {
                    $image_url = $reaction['image_url_2x'] ? $reaction['image_url_2x'] : $reaction['image_url'];
                    $reaction_image = '<img src="' . htmlspecialchars_uni($image_url) . '" alt="' . htmlspecialchars_uni($reaction['title']) . '" width="16" height="16" style="vertical-align: middle; margin: 0 3px;" />';
                }
                
                // Reaction name with color
                $reaction_name = '<span style="color: ' . htmlspecialchars_uni($reaction['text_color']) . '; font-weight: bold;">' . htmlspecialchars_uni($reaction['title']) . '</span>';
                
                // Get thread title and build link
                $tid = isset($extraDetails['tid']) ? (int)$extraDetails['tid'] : 0;
                $pid = isset($extraDetails['pid']) ? (int)$extraDetails['pid'] : 0;
                $thread_link = '';
                
                if($tid)
                {
                    $thread = get_thread($tid);
                    if($thread)
                    {
                        $thread_title = htmlspecialchars_uni($thread['subject']);
                        $post_url = $this->mybb->settings['bburl'] . '/' . get_post_link($pid, $tid) . '#pid' . $pid;
                        $thread_link = '<a href="' . $post_url . '">' . $thread_title . '</a>';
                    }
                }
                
                if(empty($thread_link))
                {
                    $thread_link = $this->lang->reactions_this_post;
                }
                
                // Build the formatted alert message
                // Format: PARADOX has reacted with (image)Love in (Thread Title linked)
                return $this->lang->sprintf(
                    $this->lang->myalerts_reactions_alert,
                    $outputAlert['from_user'],
                    $reaction_image,
                    $reaction_name,
                    $thread_link
                );
            }
            
            public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
            {
                $extraDetails = $alert->getExtraDetails();
                $pid = isset($extraDetails['pid']) ? (int)$extraDetails['pid'] : 0;
                $tid = isset($extraDetails['tid']) ? (int)$extraDetails['tid'] : 0;
                
                if($pid && $tid)
                {
                    return $this->mybb->settings['bburl'] . '/' . get_post_link($pid, $tid) . '#pid' . $pid;
                }
                
                return $this->mybb->settings['bburl'];
            }
        }
        
        // Register the formatter
        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
        if(!$formatterManager)
        {
            $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }
        if($formatterManager)
        {
            $formatterManager->registerFormatter(new ReactionsAlertFormatter($mybb, $lang, 'reaction'));
        }
    }
    
    // Register formatter for reaction_removed alerts
    if(reactions_have_myalerts(true, true, true) &&
       class_exists('MybbStuff_MyAlerts_Formatter_AbstractFormatter') &&
       class_exists('MybbStuff_MyAlerts_AlertFormatterManager') &&
       !class_exists('ReactionsRemovedAlertFormatter'))
    {
        class ReactionsRemovedAlertFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
        {
            public function init()
            {
                if(!$this->lang->reactions)
                {
                    $this->lang->load('reactions');
                }
            }
            
            public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
            {
                global $db;
                
                $extraDetails = $alert->getExtraDetails();
                
                // Get reaction details
                $reaction_id = isset($extraDetails['reaction_id']) ? (int)$extraDetails['reaction_id'] : 0;
                $reaction = reactions_get_reaction($reaction_id);
                
                if(!$reaction)
                {
                    return $this->lang->sprintf($this->lang->myalerts_reactions_removed_fallback, $outputAlert['from_user']);
                }
                
                // Build reaction image HTML (16x16 for alerts)
                $reaction_image = '';
                if($reaction['sprite_mode'] && !empty($reaction['sprite_params']))
                {
                    $sprite_params = json_decode($reaction['sprite_params'], true);
                    $reaction_image = '<span class="reaction-sprite" style="display: inline-block; width: 16px; height: 16px; background-image: url(' . htmlspecialchars_uni($reaction['image_url']) . '); background-position: -' . (int)$sprite_params['x'] . 'px -' . (int)$sprite_params['y'] . 'px; background-size: ' . (int)$sprite_params['w'] . 'px ' . (int)$sprite_params['h'] . 'px; vertical-align: middle; margin: 0 3px;"></span>';
                }
                else
                {
                    $image_url = $reaction['image_url_2x'] ? $reaction['image_url_2x'] : $reaction['image_url'];
                    $reaction_image = '<img src="' . htmlspecialchars_uni($image_url) . '" alt="' . htmlspecialchars_uni($reaction['title']) . '" width="16" height="16" style="vertical-align: middle; margin: 0 3px;" />';
                }
                
                // Reaction name with color
                $reaction_name = '<span style="color: ' . htmlspecialchars_uni($reaction['text_color']) . '; font-weight: bold;">' . htmlspecialchars_uni($reaction['title']) . '</span>';
                
                // Get thread title and build link
                $tid = isset($extraDetails['tid']) ? (int)$extraDetails['tid'] : 0;
                $pid = isset($extraDetails['pid']) ? (int)$extraDetails['pid'] : 0;
                $thread_link = '';
                
                if($tid)
                {
                    $thread = get_thread($tid);
                    if($thread)
                    {
                        $thread_title = htmlspecialchars_uni($thread['subject']);
                        $post_url = $this->mybb->settings['bburl'] . '/' . get_post_link($pid, $tid) . '#pid' . $pid;
                        $thread_link = '<a href="' . $post_url . '">' . $thread_title . '</a>';
                    }
                }
                
                if(empty($thread_link))
                {
                    $thread_link = $this->lang->reactions_this_post;
                }
                
                // Build the formatted alert message
                // Format: PARADOX has removed their (image)Love reaction from (Thread Title linked)
                return $this->lang->sprintf(
                    $this->lang->myalerts_reactions_removed_alert,
                    $outputAlert['from_user'],
                    $reaction_image,
                    $reaction_name,
                    $thread_link
                );
            }
            
            public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
            {
                $extraDetails = $alert->getExtraDetails();
                $pid = isset($extraDetails['pid']) ? (int)$extraDetails['pid'] : 0;
                $tid = isset($extraDetails['tid']) ? (int)$extraDetails['tid'] : 0;
                
                if($pid && $tid)
                {
                    return $this->mybb->settings['bburl'] . '/' . get_post_link($pid, $tid) . '#pid' . $pid;
                }
                
                return $this->mybb->settings['bburl'];
            }
        }
        
        // Register the formatter
        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
        if(!$formatterManager)
        {
            $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }
        if($formatterManager)
        {
            $formatterManager->registerFormatter(new ReactionsRemovedAlertFormatter($mybb, $lang, 'reaction_removed'));
        }
    }
}

/**
 * Backwards-compatible formatter registration for older MyAlerts versions
 * Hook: xmlhttp
 */
function reactions_register_myalerts_formatter_back_compat()
{
    if(function_exists('myalerts_info'))
    {
        $myalerts_info = myalerts_info();
        if(version_compare($myalerts_info['version'], '2.0.4') <= 0)
        {
            reactions_register_myalerts_formatter();
        }
    }
}

/**
 * Add reaction alert settings to MyAlerts settings page
 * Hook: global_start
 */
function reactions_myalerts_settings_add_checkbox()
{
    global $mybb, $lang, $templates;
    
    // Only run on alerts.php settings page
    if(THIS_SCRIPT != 'alerts.php' || $mybb->get_input('action') != 'settings')
    {
        return;
    }
    
    // Load reactions language
    if(!isset($lang->myalerts_setting_reaction))
    {
        $lang->load('reactions');
    }
    
    // Handle POST request to save setting
    if(strtolower($mybb->request_method) == 'post')
    {
        $disabled_alerts = array();
        if(!empty($mybb->user['myalerts_disabled_alert_types']))
        {
            // Handle both string (JSON) and array formats
            if(is_string($mybb->user['myalerts_disabled_alert_types']))
            {
                $disabled_alerts = json_decode($mybb->user['myalerts_disabled_alert_types'], true);
            }
            elseif(is_array($mybb->user['myalerts_disabled_alert_types']))
            {
                $disabled_alerts = $mybb->user['myalerts_disabled_alert_types'];
            }
            if(!is_array($disabled_alerts))
            {
                $disabled_alerts = array();
            }
        }
        
        // Get reaction alert type IDs
        if(class_exists('MybbStuff_MyAlerts_AlertTypeManager'))
        {
            $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
            if($alertTypeManager)
            {
                // Handle reaction added alert (default: enabled/checked)
                $alertType = $alertTypeManager->getByCode('reaction');
                if($alertType !== null)
                {
                    $reaction_alert_id = $alertType->getId();
                    
                    if(isset($mybb->input['reaction']))
                    {
                        // Checkbox is checked - remove from disabled list
                        $key = array_search($reaction_alert_id, $disabled_alerts);
                        if($key !== false)
                        {
                            unset($disabled_alerts[$key]);
                        }
                    }
                    else
                    {
                        // Checkbox is unchecked - add to disabled list
                        if(!in_array($reaction_alert_id, $disabled_alerts))
                        {
                            $disabled_alerts[] = $reaction_alert_id;
                        }
                    }
                }
                
                // Handle reaction removed alert (default: disabled/unchecked)
                $alertTypeRemoved = $alertTypeManager->getByCode('reaction_removed');
                if($alertTypeRemoved !== null)
                {
                    $reaction_removed_alert_id = $alertTypeRemoved->getId();
                    
                    // Check if this is the first time saving (no existing config)
                    $is_first_save = empty($mybb->user['myalerts_disabled_alert_types']);
                    
                    if(isset($mybb->input['reaction_removed']))
                    {
                        // Checkbox is checked - remove from disabled list to enable
                        $key = array_search($reaction_removed_alert_id, $disabled_alerts);
                        if($key !== false)
                        {
                            unset($disabled_alerts[$key]);
                        }
                    }
                    else
                    {
                        // Checkbox is unchecked - add to disabled list
                        if(!in_array($reaction_removed_alert_id, $disabled_alerts))
                        {
                            $disabled_alerts[] = $reaction_removed_alert_id;
                        }
                    }
                    
                    // On first save, if reaction_removed is not checked, ensure it's in disabled list
                    if($is_first_save && !isset($mybb->input['reaction_removed']))
                    {
                        if(!in_array($reaction_removed_alert_id, $disabled_alerts))
                        {
                            $disabled_alerts[] = $reaction_removed_alert_id;
                        }
                    }
                }
                
                // Update user's disabled alerts
                global $db;
                $disabled_alerts_json = $db->escape_string(json_encode(array_values($disabled_alerts)));
                $db->update_query('users', array(
                    'myalerts_disabled_alert_types' => $disabled_alerts_json
                ), 'uid=' . (int)$mybb->user['uid']);
                
                // Update session variable - store as JSON string to match database format
                $mybb->user['myalerts_disabled_alert_types'] = json_encode(array_values($disabled_alerts));
            }
        }
    }
}

/**
 * Modify MyAlerts settings page output to add reaction checkboxes
 * Hook: pre_output_page
 */
function reactions_myalerts_settings_modify_output(&$page)
{
    global $mybb, $lang;
    
    // Only run on alerts.php settings page
    if(THIS_SCRIPT != 'alerts.php' || $mybb->get_input('action') != 'settings')
    {
        return;
    }
    
    // Load reactions language
    if(!isset($lang->myalerts_setting_reaction))
    {
        $lang->load('reactions');
    }
    
    $checkbox_rows = '';
    
    if(class_exists('MybbStuff_MyAlerts_AlertTypeManager'))
    {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
        if($alertTypeManager)
        {
            // Get disabled types once and handle both string (JSON) and array formats
            $disabled_types = array();
            if(!empty($mybb->user['myalerts_disabled_alert_types']))
            {
                if(is_string($mybb->user['myalerts_disabled_alert_types']))
                {
                    $disabled_types = json_decode($mybb->user['myalerts_disabled_alert_types'], true);
                }
                elseif(is_array($mybb->user['myalerts_disabled_alert_types']))
                {
                    $disabled_types = $mybb->user['myalerts_disabled_alert_types'];
                }
            }
            if(!is_array($disabled_types))
            {
                $disabled_types = array();
            }
            
            // Reaction added checkbox
            $checked = 'checked="checked"';
            $alertType = $alertTypeManager->getByCode('reaction');
            if($alertType !== null)
            {
                $reaction_alert_id = $alertType->getId();
                
                // Check if user has disabled this alert type
                if(in_array($reaction_alert_id, $disabled_types))
                {
                    $checked = '';
                }
                
                $checkbox_rows .= '<tr>
                    <td class="trow1">
                        <label for="input_reaction"><input type="checkbox" name="reaction" id="input_reaction" ' . $checked . '/> &nbsp;
                            ' . $lang->myalerts_setting_reaction . '</label>
                    </td>
                </tr>';
            }
            
            // Reaction removed checkbox
            // Checked when NOT in the disabled list (same as reaction alert)
            $checked_removed = '';
            $alertTypeRemoved = $alertTypeManager->getByCode('reaction_removed');
            if($alertTypeRemoved !== null)
            {
                $reaction_removed_alert_id = $alertTypeRemoved->getId();
                
                // Check if enabled (not in disabled list)
                if(!in_array($reaction_removed_alert_id, $disabled_types))
                {
                    $checked_removed = 'checked="checked"';
                }
                
                $checkbox_rows .= '<tr>
                    <td class="trow2">
                        <label for="input_reaction_removed"><input type="checkbox" name="reaction_removed" id="input_reaction_removed" ' . $checked_removed . '/> &nbsp;
                            ' . $lang->myalerts_setting_reaction_removed . '</label>
                    </td>
                </tr>';
            }
        }
    }
    
    // Inject before the closing </tbody> tag in the alertSettings table
    if($checkbox_rows)
    {
        $page = str_replace('{$alertSettings}</tbody>', '{$alertSettings}' . $checkbox_rows . '</tbody>', $page);
    }
}

/**
 * Create or update MyAlerts alert when a reaction is added
 * @param int $reaction_id The reaction ID
 * @param int $content_id The content ID (post ID)
 * @param int $from_uid The user who gave the reaction
 * @param int $to_uid The user who received the reaction
 */
function reactions_myalerts_add_alert($reaction_id, $content_id, $from_uid, $to_uid)
{
    global $db, $mybb;
    
    // Don't alert if MyAlerts classes are not available
    if(!class_exists('MybbStuff_MyAlerts_AlertTypeManager'))
    {
        return;
    }
    
    // Don't alert if MyAlerts integration is disabled in settings
    if(empty($mybb->settings['reactions_integrate_myalerts']))
    {
        return;
    }
    
    // Don't alert if MyAlerts isn't integrated or user is reacting to their own post
    if(!reactions_have_myalerts(true, true, true) || $from_uid == $to_uid || $to_uid == 0)
    {
        return;
    }
    
    // Check if user has disabled reaction alerts
    $user = get_user($to_uid);
    if(!empty($user['myalerts_disabled_alert_types']))
    {
        $disabled_types = json_decode($user['myalerts_disabled_alert_types'], true);
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
        if($alertTypeManager)
        {
            $alertType = $alertTypeManager->getByCode('reaction');
            if($alertType !== null && is_array($disabled_types) && in_array($alertType->getId(), $disabled_types))
            {
                return; // User has disabled reaction alerts
            }
        }
    }
    
    // Get post details
    $post = get_post($content_id);
    if(!$post)
    {
        return;
    }
    
    $tid = (int)$post['tid'];
    $pid = $content_id;
    $fid = (int)$post['fid'];
    
    $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
    $alertType = $alertTypeManager->getByCode('reaction');
    
    if(!$alertType)
    {
        if(defined('REACTIONS_DEBUG') && REACTIONS_DEBUG)
        {
            error_log("[Reactions MyAlerts] FAILED: Could not get 'reaction' alert type");
        }
        return;
    }
    
    // Check for existing unread alert for this post
    $query = $db->simple_select(
        'alerts',
        'id, unread, extra_details',
        "object_id = {$pid} AND alert_type_id = {$alertType->getId()} AND uid = {$to_uid}",
        array('order_by' => 'id', 'order_dir' => 'DESC')
    );
    $existing = $db->fetch_array($query);
    $db->free_result($query);
    
    if(!$existing || $existing['unread'] < 1)
    {
        // Create new alert
        $alert = new MybbStuff_MyAlerts_Entity_Alert($to_uid, $alertType, $pid, $from_uid);
        $alert->setExtraDetails(array(
            'reaction_id' => $reaction_id,
            'tid' => $tid,
            'pid' => $pid,
            'fid' => $fid
        ));
        
        MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
    }
    else
    {
        // Update existing unread alert - add new reaction to details
        $alert_id = $existing['id'];
        $extra_details = json_decode($existing['extra_details'], true);
        
        // Update reaction_id to the latest one
        $extra_details['reaction_id'] = $reaction_id;
        
        // Also store additional reaction if needed
        if(!isset($extra_details['additional_reactions']))
        {
            $extra_details['additional_reactions'] = array();
        }
        $extra_details['additional_reactions'][] = array(
            'reaction_id' => $reaction_id,
            'from_uid' => $from_uid,
            'dateline' => TIME_NOW
        );
        
        $db->update_query('alerts', array(
            'from_user_id' => $from_uid,
            'extra_details' => $db->escape_string(json_encode($extra_details)),
            'dateline' => date('Y-m-d H:i:s', TIME_NOW)
        ), "id = {$alert_id}");
    }
}

/**
 * Send MyAlerts alert when a reaction is removed
 * @param int $from_uid The user who removed the reaction
 * @param int $to_uid The user who received the reaction (post author)
 * @param int $reaction_id The reaction type ID
 * @param int $pid The post ID
 * @param int $tid The thread ID
 * @param int $fid The forum ID
 */
function reactions_myalerts_send_removed_alert($from_uid, $to_uid, $reaction_id, $pid, $tid, $fid)
{
    global $db, $mybb;
    
    // Don't alert if MyAlerts classes are not available
    if(!class_exists('MybbStuff_MyAlerts_AlertTypeManager') || !class_exists('MybbStuff_MyAlerts_AlertManager'))
    {
        if(defined('REACTIONS_DEBUG') && REACTIONS_DEBUG)
        {
            error_log("[Reactions MyAlerts] FAILED: MyAlerts classes not available");
        }
        return;
    }
    
    // Don't alert if MyAlerts integration is disabled in settings
    if(empty($mybb->settings['reactions_integrate_myalerts']))
    {
        if(defined('REACTIONS_DEBUG') && REACTIONS_DEBUG)
        {
            error_log("[Reactions MyAlerts] FAILED: MyAlerts integration disabled in settings");
        }
        return;
    }
    
    // Don't alert if MyAlerts is not fully active
    if(!reactions_have_myalerts(true, true, true))
    {
        if(defined('REACTIONS_DEBUG') && REACTIONS_DEBUG)
        {
            error_log("[Reactions MyAlerts] FAILED: MyAlerts not active");
        }
        return;
    }
    
    // Don't alert self
    if($from_uid == $to_uid)
    {
        return;
    }
    
    $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
    $alertType = $alertTypeManager->getByCode('reaction_removed');
    
    if(!$alertType)
    {
        if(defined('REACTIONS_DEBUG') && REACTIONS_DEBUG)
        {
            error_log("[Reactions MyAlerts] FAILED: Could not get 'reaction_removed' alert type");
        }
        return;
    }
    
    // Create new alert
    $alert = new MybbStuff_MyAlerts_Entity_Alert($to_uid, $alertType, $pid, $from_uid);
    $alert->setExtraDetails(array(
        'reaction_id' => $reaction_id,
        'tid' => $tid,
        'pid' => $pid,
        'fid' => $fid
    ));
    
    MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
}

/**
 * Delete MyAlerts alert when a reaction is removed
 * @param int $content_id The content ID (post ID)
 * @param int $from_uid The user who removed the reaction
 * @param int $to_uid The user who received the reaction
 */
function reactions_myalerts_delete_alert($content_id, $from_uid, $to_uid)
{
    global $db, $mybb;
    
    // Don't process if MyAlerts classes are not available
    if(!class_exists('MybbStuff_MyAlerts_AlertTypeManager'))
    {
        return;
    }
    
    // Don't process if MyAlerts integration is disabled in settings
    if(empty($mybb->settings['reactions_integrate_myalerts']))
    {
        return;
    }
    
    if(!reactions_have_myalerts(true, true, true))
    {
        return;
    }
    
    $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
    if(!$alertTypeManager)
    {
        return;
    }
    
    $alertType = $alertTypeManager->getByCode('reaction');
    
    if(!$alertType)
    {
        return;
    }
    
    // Find and delete unread alert from this user for this post
    $query = $db->simple_select(
        'alerts',
        'id, from_user_id, extra_details',
        "object_id = {$content_id} AND uid = {$to_uid} AND unread = 1 AND alert_type_id = {$alertType->getId()}"
    );
    
    if($db->num_rows($query) > 0)
    {
        $alert = $db->fetch_array($query);
        $db->free_result($query);
        
        // Only delete if this alert was from the user who removed the reaction
        if($alert['from_user_id'] == $from_uid)
        {
            $db->delete_query('alerts', "id = {$alert['id']}");
        }
    }
}

/**
 * Hook: datahandler_user_delete_end
 * Delete reaction alerts when a user is deleted
 */
function reactions_myalerts_user_delete($delete_uids)
{
    global $db;
    
    // Only proceed if MyAlerts classes are available
    if(!class_exists('MybbStuff_MyAlerts_AlertTypeManager'))
    {
        return;
    }
    
    if(!reactions_have_myalerts())
    {
        return;
    }
    
    $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
    if(!$alertTypeManager)
    {
        return;
    }
    
    $alertType = $alertTypeManager->getByCode('reaction');
    
    if(!$alertType)
    {
        return;
    }
    
    // The datahandler_user_delete_end hook passes the UserDataHandler object,
    // not an array of UIDs. Extract UIDs from the object or handle arrays/strings.
    if($delete_uids instanceof UserDataHandler)
    {
        $delete_uids = $delete_uids->delete_uids;
    }
    
    if(is_string($delete_uids))
    {
        $delete_uids = array_filter(array_map('intval', explode(',', $delete_uids)));
    }
    else
    {
        $delete_uids = array_map('intval', (array)$delete_uids);
    }
    
    if(empty($delete_uids))
    {
        return;
    }
    
    $uids = implode(',', $delete_uids);
    
    // Delete alerts where deleted user was the recipient
    $db->delete_query('alerts', "uid IN ({$uids}) AND alert_type_id = {$alertType->getId()}");
    
    // Delete alerts where deleted user was the sender
    $db->delete_query('alerts', "from_user_id IN ({$uids}) AND alert_type_id = {$alertType->getId()}");
    
    // Also handle reaction_removed alert type
    $alertTypeRemoved = $alertTypeManager->getByCode('reaction_removed');
    if($alertTypeRemoved)
    {
        $removed_id = $alertTypeRemoved->getId();
        // Delete alerts where deleted user was the recipient
        $db->delete_query('alerts', "uid IN ({$uids}) AND alert_type_id = {$removed_id}");
        // Delete alerts where deleted user was the sender
        $db->delete_query('alerts', "from_user_id IN ({$uids}) AND alert_type_id = {$removed_id}");
    }
}
