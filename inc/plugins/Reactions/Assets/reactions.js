/**
 * MyBB Reactions Handler
 * Compatible with MyBB's JavaScript framework
 */
(function ($, window, document, _undefined) {
    "use strict";

    // MyBB-compatible Reaction Handler

    // Language strings injected by the plugin (see reactions_js_lang_script)
    var reactionsLang = window.reactionsLang || {};

    // Helper function to get a translated message with fallback to English
    function t(key, fallback) {
        return (typeof reactionsLang[key] !== 'undefined' && reactionsLang[key] !== null && reactionsLang[key] !== '')
            ? reactionsLang[key]
            : fallback;
    }

    // Helper function to check if notification type is enabled
    function isNotificationEnabled(notificationType) {
        // If notification type is null, it means it's not user-configurable - always show
        if (notificationType === null) {
            return true;
        }

        // If preferences object doesn't exist, default to enabled
        if (typeof reactionsNotifyPrefs === 'undefined' || !reactionsNotifyPrefs) {
            return true;
        }

        // Check if this specific notification type is enabled
        if (typeof reactionsNotifyPrefs[notificationType] !== 'undefined') {
            return reactionsNotifyPrefs[notificationType] === 1 || reactionsNotifyPrefs[notificationType] === true;
        }

        // Default to enabled if type not found
        return true;
    }

    // Map error messages to notification types
    // Note: guests_not_allowed, edit_time_expired, ajax_error, and reaction_error 
    // are not user-configurable and will always be shown
    function getNotificationTypeFromError(message) {
        if (!message) return null; // Return null for non-configurable errors

        message = message.toLowerCase();

        if (message.indexOf('disabled') !== -1 && message.indexOf('currently') !== -1) return 'reactions_disabled';
        if (message.indexOf('permission') !== -1 && message.indexOf('use') !== -1) return 'no_permission_use';
        if (message.indexOf('permission') !== -1 && message.indexOf('remove') !== -1) return 'no_permission_remove';
        if (message.indexOf('disabled in this forum') !== -1) return 'disabled_in_forum';
        if (message.indexOf('closed') !== -1 && message.indexOf('thread') !== -1) return 'thread_closed';
        if (message.indexOf('usergroup') !== -1 && message.indexOf('give') !== -1) return 'usergroup_no_give';
        if (message.indexOf('recipient') !== -1 && message.indexOf('receive') !== -1) return 'recipient_no_receive';
        if (message.indexOf('own content') !== -1) return 'self_reaction_not_allowed';
        if (message.indexOf('posts') !== -1 && message.indexOf('need') !== -1) return 'min_posts_required';
        if (message.indexOf('reputation') !== -1 && message.indexOf('need') !== -1) return 'min_reputation_required';
        if (message.indexOf('daily limit') !== -1) return 'daily_limit_reached';
        if (message.indexOf('wait') !== -1 && message.indexOf('seconds') !== -1) return 'flood_control';
        if (message.indexOf('success') !== -1) return 'reaction_success';

        // These types are not user-configurable - always show them
        // guests_not_allowed, edit_time_expired, ajax_error, reaction_error
        return null;
    }

    // Toast notification system - vanilla JS, inspired by Theme Mode Toggle
    if (!window.reactionToasts) window.reactionToasts = [];
    var REACTION_TOAST_GAP = 12;
    var REACTION_TOAST_BOTTOM = 24;
    var REACTION_TOAST_STEP = 56;

    function reactionToastDismiss(el) {
        var toast = el.parentNode;
        if (!toast || !toast.parentNode) return;
        toast.classList.remove('show');
        setTimeout(function() {
            if (toast.parentNode) toast.remove();
            var idx = window.reactionToasts.indexOf(toast);
            if (idx !== -1) window.reactionToasts.splice(idx, 1);
            reactionToastReposition();
        }, 500);
    }

    function reactionToastReposition() {
        for (var i = 0; i < window.reactionToasts.length; i++) {
            window.reactionToasts[i].style.bottom = (REACTION_TOAST_BOTTOM + i * (REACTION_TOAST_STEP + REACTION_TOAST_GAP)) + 'px';
        }
    }

    // Helper function for user-friendly notifications
    // notifyType overrides getNotificationTypeFromError when the type is known
    // (e.g. from the server response or for success messages)
    function showNotification(message, type, notifyType) {
        type = type || 'error';

        // Determine the notification type: explicit wins, otherwise map from message
        var notificationType = notifyType || getNotificationTypeFromError(message);
        if (!isNotificationEnabled(notificationType)) {
            return;
        }

        // Wait for document to be ready if not already
        if (document.readyState !== 'complete') {
            $(document).ready(function () {
                showNotification(message, type, notifyType);
            });
            return;
        }

        createToastNotification(message, type);
    }

    // Vanilla JS toast notification
    function createToastNotification(message, type) {
        var toast = document.createElement('div');
        toast.className = 'reaction-toast reaction-toast--' + type;

        var iconSvg = '';
        if (type === 'error') {
            iconSvg = '<svg class="reaction-toast-icon" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
        } else if (type === 'success') {
            iconSvg = '<svg class="reaction-toast-icon" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="16 8 10 16 7 13"/></svg>';
        } else if (type === 'warning') {
            iconSvg = '<svg class="reaction-toast-icon" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        } else if (type === 'change') {
            iconSvg = '<svg class="reaction-toast-icon" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>';
        } else if (type === 'remove') {
            iconSvg = '<svg class="reaction-toast-icon" viewBox="0 0 24 24" fill="none" stroke="#e91e63" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';
        }

        var closeSvg = '<svg class="reaction-toast-close" viewBox="0 0 24 24" onclick="reactionToastDismiss(this)"><line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-linecap="round"/><line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-linecap="round"/></svg>';

        var bar = '<div class="reaction-toast-bar" style="width:100%"></div>';

        toast.innerHTML = iconSvg + '<span>' + message + '</span>' + closeSvg + bar;
        toast.style.bottom = REACTION_TOAST_BOTTOM + 'px';
        document.body.appendChild(toast);
        window.reactionToasts.unshift(toast);
        void document.body.offsetHeight;
        reactionToastReposition();
        void document.body.offsetHeight;
        toast.classList.add('show');

        requestAnimationFrame(function() {
            var barEl = toast.querySelector('.reaction-toast-bar');
            if (barEl) {
                barEl.style.transition = 'width 4s linear';
                barEl.style.width = '0%';
            }
        });

        setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                if (toast.parentNode) toast.remove();
                var idx = window.reactionToasts.indexOf(toast);
                if (idx !== -1) window.reactionToasts.splice(idx, 1);
                reactionToastReposition();
            }, 500);
        }, 4000);
    }

    var ReactionHandler = {
        options: {
            delay: 200,
            reactionList: null
        },

        $tooltipHtml: null,
        $tooltip: null,
        href: null,
        loading: false,
        showTimer: null,
        hideTimer: null,

        init: function ($target, options) {
            this.options = $.extend({}, this.options, options);
            this.$target = $target;

            if (!this.$target.length) {
                return;
            }

            // Get href from data attribute or href attribute
            this.href = this.$target.attr('data-reaction-url') || this.$target.attr('href') || '';

            // If href is javascript:void(0), we need the data attribute
            if (!this.href || this.href === 'javascript:void(0);' || this.href.indexOf('javascript:') === 0) {
                this.href = this.$target.attr('data-reaction-url');
            }

            // Use inline tooltip template or fetch from HTML
            var $template = $('#reactionsTooltipTemplate');
            if ($template.length) {
                this.$tooltipHtml = $('<div>').append($.parseHTML($template.html()));
            } else {
                // Get base URL more reliably
                var bburl = window.mybb_url || '';
                if (!bburl && typeof mybb_url !== 'undefined') bburl = mybb_url;
                if (!bburl) {
                    var path = window.location.pathname;
                    var parts = path.split('/');
                    if (parts.length > 1) {
                        bburl = '/' + parts[1];
                    } else {
                        bburl = '';
                    }
                }
                // Using divs instead of <a> tags for better mobile/touch support
                var templateHtml = '<div class="reactTooltip">' +
                    '<div class="reaction" data-reaction-id="1" role="button" tabindex="0"><img src="' + bburl + '/images/reactions/like.png" alt="' + t('reaction_name_like', 'Like') + '" class="reaction-image"></div>' +
                    '<div class="reaction" data-reaction-id="2" role="button" tabindex="0"><img src="' + bburl + '/images/reactions/love.png" alt="' + t('reaction_name_love', 'Love') + '" class="reaction-image"></div>' +
                    '<div class="reaction" data-reaction-id="3" role="button" tabindex="0"><img src="' + bburl + '/images/reactions/haha.png" alt="' + t('reaction_name_haha', 'Haha') + '" class="reaction-image"></div>' +
                    '<div class="reaction" data-reaction-id="4" role="button" tabindex="0"><img src="' + bburl + '/images/reactions/wow.png" alt="' + t('reaction_name_wow', 'Wow') + '" class="reaction-image"></div>' +
                    '<div class="reaction" data-reaction-id="5" role="button" tabindex="0"><img src="' + bburl + '/images/reactions/sad.png" alt="' + t('reaction_name_sad', 'Sad') + '" class="reaction-image"></div>' +
                    '<div class="reaction" data-reaction-id="6" role="button" tabindex="0"><img src="' + bburl + '/images/reactions/angry.png" alt="' + t('reaction_name_angry', 'Angry') + '" class="reaction-image"></div>' +
                    '</div>';
                this.$tooltipHtml = $('<div>').append($.parseHTML(templateHtml));
            }

            // Create tooltip
            this.createTooltip();
            this.setupTriggers();

            // Handle click - prevent default and use AJAX
            var self = this;
            this.$target.on('click', function (e) {
                return self.actionClick.call(self, e);
            });
            this.$target.on('mousedown', function (e) {
                // Prevent default on mousedown as backup
                e.preventDefault();
            });

            // Handle keyboard activation for div-based buttons (accessibility)
            this.$target.on('keydown', function (e) {
                // Allow Enter (13) or Space (32) keys to activate the button
                if (e.which === 13 || e.which === 32) {
                    e.preventDefault();
                    e.stopPropagation();
                    return self.actionClick.call(self, e);
                }
            });

            this.$target.removeAttr('onclick');

            // Mark as initialized
            this.$target.data('reaction-initialized', true);
        },

        createTooltip: function () {
            var self = this;

            this.tooltip = {
                show: function () {
                    var content = self.getTooltipContent();
                    if (!content) {
                        return;
                    }

                    // Remove existing tooltip if any
                    if (self.$tooltip) {
                        self.$tooltip.remove();
                    }

                    // Create tooltip element with arrow
                    self.$tooltip = $('<div class="tooltip tooltip--reaction tooltip--top"><div class="tooltip-arrow"></div></div>');
                    self.$tooltip.append(content);
                    self.$tooltip.css({
                        position: 'absolute',
                        zIndex: 10000,
                        display: 'none',
                        pointerEvents: 'auto'
                    });

                    $('body').append(self.$tooltip);
                    self.$tooltip.data('reaction-handler', self);

                    // Position tooltip above the button
                    self.positionTooltip();

                    // Show with animation
                    self.$tooltip.show().addClass('is-active');

                    // Keep tooltip visible when hovering over it
                    self.$tooltip.on('mouseenter', function () {
                        clearTimeout(self.hideTimer);
                    });

                    self.$tooltip.on('mouseleave', function () {
                        self.tooltip.hide();
                    });
                },

                hide: function () {
                    if (self.$tooltip) {
                        clearTimeout(self.showTimer);
                        self.hideTimer = setTimeout(function () {
                            if (self.$tooltip) {
                                self.$tooltip.removeClass('is-active');
                                (function ($t) {
                                    setTimeout(function () {
                                        if ($t) $t.remove();
                                    }, 200);
                                })(self.$tooltip);
                                self.$tooltip = null;
                            }
                        }, 100);
                    }
                }
            };
        },

        setupTriggers: function () {
            this.$target.on('mouseenter', $.proxy(this, 'onMouseEnter'));
            this.$target.on('mouseleave', $.proxy(this, 'onMouseLeave'));
            // Click binding is done in init() to avoid duplicates
        },

        onMouseEnter: function () {
            clearTimeout(this.hideTimer);
            this.showTimer = setTimeout($.proxy(this, 'showTooltip'), this.options.delay);
        },

        onMouseLeave: function () {
            clearTimeout(this.showTimer);
            this.hideTimer = setTimeout($.proxy(this.tooltip.hide, this.tooltip), 100);
        },

        showTooltip: function () {
            this.tooltip.show();
        },

        positionTooltip: function () {
            if (!this.$tooltip) {
                return;
            }

            var targetOffset = this.$target.offset();
            var targetHeight = this.$target.outerHeight();
            var tooltipHeight = this.$tooltip.outerHeight();
            var tooltipWidth = this.$tooltip.outerWidth();
            var targetWidth = this.$target.outerWidth();
            var windowWidth = $(window).width();
            var scrollTop = $(window).scrollTop();

            // Center of the target
            var targetCenter = targetOffset.left + (targetWidth / 2);

            // Ideal left position (centered)
            var leftPos = targetCenter - (tooltipWidth / 2);

            // Boundary checks
            var minLeft = 10;
            var maxLeft = windowWidth - tooltipWidth - 10;

            if (leftPos < minLeft) leftPos = minLeft;
            if (leftPos > maxLeft) leftPos = maxLeft;

            // Calculate arrow position (relative to tooltip)
            var arrowLeft = targetCenter - leftPos - 6; // Arrow width is 12px, center is 6px

            // Clamp arrow position
            if (arrowLeft < 6) arrowLeft = 6;
            if (arrowLeft > tooltipWidth - 18) arrowLeft = tooltipWidth - 18;

            // Try to position above first
            var topPos = targetOffset.top - tooltipHeight - 10;

            // Check if tooltip fits on top
            if (topPos < scrollTop + 10) {
                // Flip to bottom if not enough space on top
                topPos = targetOffset.top + targetHeight + 10;
                this.$tooltip.removeClass('tooltip--top').addClass('tooltip--bottom');
                this.$tooltip.find('.tooltip-arrow').css({
                    bottom: 'auto',
                    top: '-6px',
                    transform: 'rotate(225deg)'
                });
            }

            this.$tooltip.css({
                top: topPos + 'px',
                left: leftPos + 'px'
            });

            // Set arrow position
            this.$tooltip.find('.tooltip-arrow').css('left', arrowLeft + 'px');
        },

        getTooltipContent: function () {
            if (!this.$tooltipHtml || !this.$tooltipHtml.length) {
                return null;
            }

            var $content = this.$tooltipHtml.clone();

            // Update hrefs for each reaction
            var baseHref = this.$target.attr('data-reaction-url') || this.href;

            if (baseHref) {
                $content.find('.reaction').each(function (index) {
                    var $this = $(this);
                    var reactionId = $this.data('reaction-id') || (index + 1);

                    var newHref = baseHref;
                    if (newHref.indexOf('reaction_id=') !== -1) {
                        newHref = newHref.replace(/reaction_id=[^&]*/, 'reaction_id=' + reactionId);
                    } else {
                        newHref += (newHref.indexOf('?') === -1 ? '?' : '&') + 'reaction_id=' + reactionId;
                    }

                    $this.attr('href', newHref);
                });
            }

            return $content;
        },

        actionClick: function (e) {
            // CRITICAL: Prevent default immediately
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            if (this.loading) {
                return false;
            }

            this.loading = true;

            var $clicked = $(e.currentTarget);
            var href = $clicked.attr('data-reaction-url') || $clicked.attr('href') || this.href;

            // Ensure href includes all required parameters
            if (href) {
                var urlParts = href.split('?');
                var baseUrl = urlParts[0];
                var params = urlParts[1] ? urlParts[1] : '';

                // Add missing parameters
                var paramPairs = params.split('&');
                var hasContentType = false;
                var hasContentId = false;

                for (var i = 0; i < paramPairs.length; i++) {
                    if (paramPairs[i].indexOf('content_type=') === 0) hasContentType = true;
                    if (paramPairs[i].indexOf('content_id=') === 0) hasContentId = true;
                    if (paramPairs[i].indexOf('pid=') === 0 && !hasContentId) {
                        // Convert pid to content_id
                        paramPairs[i] = 'content_id=' + paramPairs[i].substring(4);
                        hasContentId = true;
                    }
                }

                if (!hasContentType) {
                    paramPairs.push('content_type=post');
                }

                params = paramPairs.join('&');
                href = baseUrl + '?' + params;
            }

            if (!href) {
                this.loading = false;
                return false;
            }

            // Hide tooltip
            if (this.tooltip) {
                this.tooltip.hide();
            }

            var self = this;

            // Use MyBB's AJAX method
            $.ajax({
                url: href,
                type: 'POST',
                dataType: 'json',
                timeout: 10000,
                success: function (data, status, xhr) {
                    self.actionComplete(data);
                },
                error: function (xhr, status, error) {
                    self.loading = false;

                    // Show error to user with friendly notification
                    var message = t('reactions_error_prefix', 'Error: ') + error + (xhr.status ? ' (Status: ' + xhr.status + ')' : '');
                    showNotification(message, 'error');
                },
                complete: function (xhr, status) {
                    setTimeout(function () {
                        self.loading = false;
                    }, 250);
                }
            });
            return false;
        },

        actionComplete: function (data) {
            if (data && data.error) {
                showNotification(data.error, 'error', data.notify_type);
                this.loading = false;
                return;
            }

            if (!data || !data.html) {
                showNotification(t('reaction_invalid_response', 'Invalid response from server'), 'error');
                this.loading = false;
                return;
            }

            // Show toast notification based on action type
            var oldReactionId = this.$target.data('reaction-id');
            if (data.reactionId && data.reactionId > 0) {
                if (!oldReactionId) {
                    showNotification(t('reaction_added_success', 'Reaction added successfully!'), 'success', 'reaction_success');
                } else if (oldReactionId !== data.reactionId) {
                    showNotification(t('reaction_changed_success', 'Reaction changed successfully!'), 'change', 'reaction_success');
                }
            } else if (oldReactionId) {
                showNotification(t('reaction_removed_success', 'Reaction removed successfully!'), 'remove', 'reaction_success');
            }

            var self = this;
            var $target = this.$target;
            var oldReactionId = $target.data('reaction-id');
            var newReactionId = data.reactionId;
            var linkReactionId = data.linkReactionId;

            // Update reaction button
            if (data.html.button) {
                var $newButton = $(data.html.button);
                var $oldReaction = $target.find('.js-reaction');
                var $oldReactionText = $target.find('.js-reactionText');
                var $newReaction = $newButton.find('.js-reaction');
                var $newReactionText = $newButton.find('.js-reactionText');

                if ($newReaction.length) {
                    $oldReaction.replaceWith($newReaction);
                }
                if ($oldReactionText.length && $newReactionText.length) {
                    $oldReactionText.replaceWith($newReactionText);
                }

                // Update classes
                $target.removeClass('has-reaction reaction--imageHidden');
                $target.removeClass(function (index, className) {
                    return (className.match(/(^|\s)reaction--\d+/g) || []).join(' ');
                });

                if (newReactionId) {
                    $target.addClass('has-reaction reaction--' + newReactionId);
                    $target.data('reaction-id', newReactionId);
                } else {
                    $target.addClass('reaction--imageHidden reaction--1');
                    $target.data('reaction-id', 0);
                }

                // Update href and data-reaction-url
                if (linkReactionId) {
                    var originalHref = $target.attr('data-reaction-url') || $target.attr('href') || self.href;
                    var newHref = originalHref.replace(/(\?|&)reaction_id=\d+(?:&|$)/, '$1reaction_id=' + linkReactionId);
                    if (newHref === originalHref && newHref.indexOf('reaction_id=') === -1) {
                        newHref += (originalHref.indexOf('?') === -1 ? '?' : '&') + 'reaction_id=' + linkReactionId;
                    }

                    $target.attr('data-reaction-url', newHref);
                    if ($target.attr('href') && $target.attr('href') !== 'javascript:void(0);') {
                        $target.attr('href', newHref);
                    }
                    self.href = newHref;
                }
            }

            // Update reactions bar
            var $container = $target.closest('.post_content, .post, .postbit');
            var $reactionList = this.options.reactionList ? $container.find(this.options.reactionList) : $container.find('.reactionsBar');
            var $parentWrapper = $container.find('.reaction_bar_parent');

            if (data.html && data.html.content) {
                // Check if the returned content already has the wrapper
                var hasWrapper = data.html.content.indexOf('reaction_bar_parent') !== -1;
                var contentToInsert = data.html.content;

                if ($parentWrapper.length) {
                    // Parent wrapper exists - replace just the inner reactionsBar
                    var $newBar = $(contentToInsert);
                    // If the new content has a wrapper, extract just the inner bar
                    if (hasWrapper) {
                        $newBar = $newBar.find('.reactionsBar').first();
                    }
                    // Find the existing bar inside parent and replace it
                    var $existingBar = $parentWrapper.find('.reactionsBar').first();
                    if ($existingBar.length) {
                        $existingBar.replaceWith($newBar);
                    } else {
                        // No existing bar, append to parent
                        $parentWrapper.html($newBar);
                    }
                } else if ($reactionList.length) {
                    // No parent wrapper but bar exists - wrap the new content
                    if (!hasWrapper) {
                        contentToInsert = '<div class="reaction_bar_parent">' + contentToInsert + '</div>';
                    }
                    $reactionList.closest('.reaction_bar_parent, .post_content, .post, .postbit').find('.reaction_bar_parent, .reactionsBar').first().replaceWith(contentToInsert);
                } else {
                    // No bar exists at all - insert new with wrapper
                    if (!hasWrapper) {
                        contentToInsert = '<div class="reaction_bar_parent">' + contentToInsert + '</div>';
                    }
                    var $controls = $container.find('.post_controls');
                    if ($controls.length) {
                        $controls.before(contentToInsert);
                    } else {
                        $container.append(contentToInsert);
                    }
                }
            } else if ($reactionList.length) {
                // No reactions left, hide the bar
                $reactionList.removeClass('is-active').empty();
            }

            // Update author information (Reactions Received/Given)
            if (data.html && data.html.author_info) {
                // Get the author ID from the current post to update all posts by same author
                var $currentPost = $target.closest('.post');
                var $authorLink = $currentPost.find('.author_information a[href*="user-"]');
                var authorId = null;

                if ($authorLink.length) {
                    // Extract user ID from URL like "user-17.html"
                    var href = $authorLink.attr('href');
                    var match = href.match(/user-(\d+)/);
                    if (match) {
                        authorId = match[1];
                    }
                }

                if (authorId) {
                    // Update ALL posts by this author on the page
                    $('.post').each(function () {
                        var $post = $(this);
                        var $postAuthorLink = $post.find('.author_information a[href*="user-' + authorId + '"]');
                        if ($postAuthorLink.length) {
                            // This post is by the same author, update their author info
                            var $postAuthor = $post.find('.post_author');
                            $postAuthor.find('.author_reactions').remove();
                            var $authorStats = $postAuthor.find('.author_statistics');
                            if ($authorStats.length) {
                                $authorStats.append(data.html.author_info);
                            } else {
                                $postAuthor.append(data.html.author_info);
                            }
                        }
                    });
                } else {
                    // Fallback: only update the current post if we can't determine author ID
                    var $postAuthor = $currentPost.find('.post_author');
                    if ($postAuthor.length) {
                        $postAuthor.find('.author_reactions').remove();
                        var $authorStats = $postAuthor.find('.author_statistics');
                        if ($authorStats.length) {
                            $authorStats.append(data.html.author_info);
                        } else {
                            $postAuthor.append(data.html.author_info);
                        }
                    }
                }
            }

            this.loading = false;
        }
    };

    ReactionHandler.activeTooltip = null;

    // MyBB Overlay Handler
    var Overlay = {
        $container: null,
        $overlay: null,
        cache: {},
        currentUrl: null,

        init: function () {
            var self = this;

            // Create container if not exists
            if (!$('.overlay-container').length) {
                this.$container = $('<div class="overlay-container"><div class="overlay"><div class="overlay-title">' + t('reactions_overlay_title', 'Members who reacted') + '<div class="overlay-close">&times;</div></div><div class="overlay-content"></div></div></div>');
                $('body').append(this.$container);
                this.$overlay = this.$container.find('.overlay');

                // Close handlers
                this.$container.find('.overlay-close').on('click', function () {
                    self.hide();
                });

                this.$container.on('click', function (e) {
                    if ($(e.target).is('.overlay-container')) {
                        self.hide();
                    }
                });

                $(document).on('keydown', function (e) {
                    if (e.key === "Escape" && self.$container.hasClass('is-active')) {
                        self.hide();
                    }
                });
            } else {
                this.$container = $('.overlay-container');
                this.$overlay = this.$container.find('.overlay');
            }

            // Delegate click for overlay triggers
            $(document).off('click.reactionsOverlay').on('click.reactionsOverlay', '[data-xf-click="overlay"]', function (e) {
                e.preventDefault();
                var href = $(this).attr('href');
                var $trigger = $(this);
                var parentInfo = $trigger.closest('.post, .reactionsBar, .reaction_bar_parent').attr('class') || 'unknown';

                // Convert reactions.php URL to xmlhttp.php AJAX endpoint
                var ajaxUrl = href.replace('/reactions.php?', '/xmlhttp.php?action=get_reactions&');
                self.load(ajaxUrl);
            });

            // Delegate click for tabs in overlay
            $(document).off('click.reactionsTabs').on('click.reactionsTabs', '.tabs-tab', function (e) {
                e.preventDefault();
                var $tab = $(this);
                if ($tab.hasClass('is-active')) return;

                var reactionId = $tab.data('reaction-id');
                var baseUrl = self.currentUrl;

                if (!baseUrl) {
                    // Fallback to window location if currentUrl is missing
                    baseUrl = window.location.href;
                }

                // Update active state immediately for perceived performance
                $tab.closest('.tabs').find('.tabs-tab').removeClass('is-active');
                $tab.addClass('is-active');

                // Robust URL builder
                var url = new URL(baseUrl, window.location.origin);
                url.searchParams.set('reaction_id', reactionId);
                var newUrl = url.toString();

                self.load(newUrl, true);
            });

            // Delegate click for pagination links in overlay
            $(document).off('click.reactionsPagination').on('click.reactionsPagination', '.overlay-container .reactions-history-pagination a, .overlay-container .pagination a', function (e) {
                e.preventDefault();
                var href = $(this).attr('href');
                if (href) {
                    self.load(href, true);
                }
            });

            // Delegate click for tabs on dedicated reactions page
            $(document).on('click', '.reaction-fullpage-tabs .reaction-tab', function (e) {
                e.preventDefault();
                var $tab = $(this);
                if ($tab.hasClass('active')) return;

                var href = $tab.attr('href');
                if (!href) return;

                // Update active state
                $tab.closest('.reaction-fullpage-tabs').find('.reaction-tab').removeClass('active');
                $tab.addClass('active');

                // Show loading state
                var $content = $('#reactions-content');
                if ($content.length) {
                    $content.css('opacity', '0.5');
                }

                // Convert to AJAX URL
                var ajaxUrl = href.replace(/reactions\.php/, 'xmlhttp.php');
                var queryString = href.split('?')[1];
                if (queryString) {
                    ajaxUrl = window.mybb_url + '/xmlhttp.php?' + queryString;
                }

                $.ajax({
                    url: ajaxUrl,
                    type: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        if (data && data.html && data.html.content) {
                            $('#reactions-content').html(data.html.content).css('opacity', '1');
                            history.pushState({}, '', href);
                        } else if (data && data.error) {
                            $('#reactions-content').html('<div style="padding: 20px; color: red;">' + data.error + '</div>').css('opacity', '1');
                        }
                    },
                    error: function () {
                        window.location.href = href;
                    }
                });
            });

            // Delegate click for pagination on dedicated reactions page
            $(document).on('click', '.reactions-fullpage .pagination a', function (e) {
                e.preventDefault();
                var $link = $(this);
                var href = $link.attr('href');
                if (!href) return;

                // Show loading state
                var $content = $('#reactions-content');
                if ($content.length) {
                    $content.css('opacity', '0.5');
                }

                // Convert to AJAX URL
                var queryString = href.split('?')[1];
                var ajaxUrl = window.mybb_url + '/xmlhttp.php?' + queryString;

                $.ajax({
                    url: ajaxUrl,
                    type: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        if (data && data.html && data.html.content) {
                            $('#reactions-content').html(data.html.content).css('opacity', '1');
                            history.pushState({}, '', href);
                        } else if (data && data.error) {
                            $('#reactions-content').html('<div style="padding: 20px; color: red;">' + data.error + '</div>').css('opacity', '1');
                        }
                    },
                    error: function () {
                        window.location.href = href;
                    }
                });
            });

            $(document).off('click.reactionsHistory').on('click.reactionsHistory', '.reactions-history-link', function (e) {
                e.preventDefault();
                var uid = $(this).data('uid');
                var type = $(this).data('type');

                // Get base URL more reliably
                var bburl = window.mybb_url || '';
                if (!bburl && typeof mybb_url !== 'undefined') bburl = mybb_url;

                // If still empty, try to determine it from the current location
                if (!bburl) {
                    bburl = window.location.origin;
                    var path = window.location.pathname;
                    var parts = path.split('/');
                    if (parts.length > 1 && parts[1] !== 'xmlhttp.php' && parts[1] !== 'member.php') {
                        // This is a guess, but better than nothing
                        // If we are in a subdirectory like /mybb/, parts[1] might be 'mybb'
                    }
                }

                var url = bburl + '/xmlhttp.php?action=get_history&uid=' + uid + '&type=' + type;

                // Set overlay title
                var title = (type === 'received') ? t('reactions_received_history', 'Reactions Received') : t('reactions_given_history', 'Reactions Given');
                self.$container.find('.overlay-title').html(title + '<div class="overlay-close">&times;</div>');

                // Re-bind close handler after updating title
                self.$container.find('.overlay-close').on('click', function () {
                    self.hide();
                });

                self.load(url);
            });

            // Delegate click for points breakdown links in overlay
            $(document).off('click.reactionsPoints').on('click.reactionsPoints', '.reactions-points-link', function (e) {
                e.preventDefault();
                var uid = $(this).data('uid');

                // Get base URL more reliably
                var bburl = window.mybb_url || '';
                if (!bburl && typeof mybb_url !== 'undefined') bburl = mybb_url;

                // If still empty, try to determine it from the current location
                if (!bburl) {
                    bburl = window.location.origin;
                    var path = window.location.pathname;
                    var parts = path.split('/');
                    if (parts.length > 1 && parts[1] !== 'xmlhttp.php' && parts[1] !== 'member.php') {
                        // This is a guess, but better than nothing
                        // If we are in a subdirectory like /mybb/, parts[1] might be 'mybb'
                    }
                }

                var url = bburl + '/xmlhttp.php?action=get_points&uid=' + uid;

                // Set overlay title
                self.$container.find('.overlay-title').html(t('reaction_points_breakdown', 'Reaction Points Breakdown') + '<div class="overlay-close">&times;</div>');

                // Re-bind close handler after updating title
                self.$container.find('.overlay-close').on('click', function () {
                    self.hide();
                });

                self.load(url);
            });
        },

        load: function (url, isTabSwitch) {
            var self = this;

            // Store current base URL if not switching tabs
            if (!isTabSwitch) {
                self.currentUrl = url;
                // Clear cache on fresh open to ensure fresh data
                self.cache = {};
            }

            // Check cache
            if (self.cache[url]) {
                self.$container.find('.overlay-content').html(self.cache[url]).css('opacity', 1);
                return;
            }

            // Show loading state
            self.$container.find('.overlay-content').css('opacity', 0.5);

            if (!self.$container.hasClass('is-active')) {
                self.$container.addClass('is-active');
            }

            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                success: function (data) {
                    if (data && data.error) {
                        showNotification(data.error, 'error');
                        return;
                    }

                    if (data && data.html && data.html.content) {
                        self.cache[url] = data.html.content;
                        self.$container.find('.overlay-content').html(data.html.content).css('opacity', 1);
                    } else if (data && data.error) {
                        self.$container.find('.overlay-content').html('<div style="padding: 20px; color: red;">' + data.error + '</div>').css('opacity', 1);
                    }
                },
                error: function () {
                    self.$container.find('.overlay-content').html('<div style="padding: 20px; color: red;">' + t('reactions_error_overlay_load', 'Error loading reactions.') + '</div>').css('opacity', 1);
                }
            });
        },

        hide: function () {
            this.$container.removeClass('is-active');
            // We clear cache on new open, so no need to clean here
        }
    };

    // Initialize when everything is ready
    $(document).ready(function () {
        // Hide MyBB's default jGrowl to prevent conflicts
        setTimeout(function () {
            $('#jGrowl').hide();
        }, 100);

        // Initialize Overlay
        Overlay.init();

        // Initialize reaction buttons immediately
        initializeReactions();

        // Also initialize after a short delay (in case content loads later)
        setTimeout(initializeReactions, 500);

        // Re-initialize on AJAX content load
        $(document).on('ajaxComplete', function () {
            setTimeout(initializeReactions, 100);
        });

        // Robust delegated event listener for reaction emojis in tooltips
        $(document).on('click', '.tooltip--reaction .reaction', function (e) {
            var $target = $(this);
            var $tooltip = $target.closest('.tooltip--reaction');
            var handler = $tooltip.data('reaction-handler');

            if (handler) {
                return handler.actionClick(e);
            }
        });
    });

    $(window).on('load', function () {
        $('#jGrowl').fadeOut(200);
    });

    function initializeReactions() {
        var buttons = $('[data-xf-init~="reaction"]');

        if (buttons.length === 0) {
            // Try alternative selector
            buttons = $('.actionBar-action--reaction');
        }

        buttons.each(function (index) {
            var $this = $(this);

            // Skip if already initialized
            if ($this.data('reaction-initialized')) {
                return;
            }

            var reactionList = $this.data('reaction-list');
            var delay = $this.data('delay') || 200;

            try {
                var handler = Object.create(ReactionHandler);
                handler.init($this, {
                    delay: delay,
                    reactionList: reactionList
                });
            } catch (e) {
                // Silently handle error
            }
        });
    }



})(jQuery, window, document);