/**
 * Hikmah Login — Admin UI Customizer
 *
 * Powers the live preview customizer in admin:
 * - Real-time color/layout changes
 * - Logo upload via WP Media Library
 * - Settings save via AJAX
 * - Preview iframe refresh
 * - Color preset selection
 *
 * @package Hikmah_Login
 * @version 1.0.0
 */

(function ($) {
    'use strict';

    var HikmahCustomizer = {

        config: window.hikmahAdmin || {},
        debounceTimer: null,
        isDirty: false,

        mediaFrames: {},

        init: function () {
            this.bindColorPickers();
            this.bindLayoutControls();
            this.bindLogoUpload();
            this.bindBackgroundUpload();
            this.bindColorPresets();
            this.bindSaveButton();
            this.bindRangeSliders();
            this.bindBackgroundType();
            this.bindLivePreview();
        },

        /* =============================================
         * COLOR PICKERS
         * ============================================= */

        bindColorPickers: function () {
            var self = this;

            $('input[type="color"]').on('input change', function () {
                // Manual color edits imply a custom preset.
                if ($(this).is('#hikmah-primary-color')) {
                    $('#hikmah-color-preset').val('custom');
                    $('.hikmah-color-swatch').css('border-color', 'transparent');
                }
                self.markDirty();
                self.updatePreview();
            });
        },

        /* =============================================
         * LAYOUT CONTROLS
         * ============================================= */

        bindLayoutControls: function () {
            var self = this;

            $('#hikmah-layout, #hikmah-theme-mode, #hikmah-btn-style, #hikmah-bg-type').on('change', function () {
                self.markDirty();
                self.updatePreview();
            });
        },

        /* =============================================
         * LOGO UPLOAD
         * ============================================= */

        bindLogoUpload: function () {
            var self = this;

            $('#hikmah-upload-logo').on('click', function (e) {
                e.preventDefault();
                self.openMediaFrame('hikmah-logo', function (frame) {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

                    $('#hikmah-logo-url').val(url);
                    $('#hikmah-logo-preview').html('<img src="' + url + '" style="max-height:50px;">');

                    self.markDirty();
                    self.updatePreview();
                });
            });

            $('#hikmah-remove-logo').on('click', function (e) {
                e.preventDefault();
                $('#hikmah-logo-url').val('');
                $('#hikmah-logo-preview').html('');
                self.markDirty();
                self.updatePreview();
            });
        },

        /* =============================================
         * BACKGROUND IMAGE UPLOAD
         * ============================================= */

        bindBackgroundUpload: function () {
            var self = this;

            $('#hikmah-upload-bg').on('click', function (e) {
                e.preventDefault();
                self.openMediaFrame('hikmah-bg', function (frame) {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var url = attachment.sizes && attachment.sizes.large ? attachment.sizes.large.url : attachment.url;

                    $('#hikmah-bg-image').val(url);
                    $('#hikmah-bg-image-preview').html('<img src="' + url + '" style="max-height:60px;max-width:180px;">');

                    self.markDirty();
                    self.updatePreview();
                });
            });

            $('#hikmah-remove-bg').on('click', function (e) {
                e.preventDefault();
                $('#hikmah-bg-image').val('');
                $('#hikmah-bg-image-preview').html('');
                self.markDirty();
                self.updatePreview();
            });
        },

        /**
         * Reusable WP media-frame opener.
         */
        openMediaFrame: function (key, onSelect) {
            var self = this;

            if (self.mediaFrames[key]) {
                self.mediaFrames[key].open();
                return;
            }

            var frame = wp.media({
                title: 'Select Image',
                button: { text: 'Use this image' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function () {
                onSelect(frame);
            });

            self.mediaFrames[key] = frame;
            frame.open();
        },

        /* =============================================
         * COLOR PRESETS
         * ============================================= */

        bindColorPresets: function () {
            var self = this;

            $('.hikmah-color-swatch').on('click', function () {
                var color = $(this).data('color');
                var preset = $(this).data('preset');

                $('.hikmah-color-swatch').css('border-color', 'transparent');
                $(this).css('border-color', '#333');

                $('#hikmah-color-preset').val(preset);
                $('#hikmah-primary-color').val(color);

                self.markDirty();
                self.updatePreview();
            });
        },

        /* =============================================
         * RANGE SLIDERS
         * ============================================= */

        bindRangeSliders: function () {
            var self = this;

            $('input[type="range"]').on('input', function () {
                var $val = $(this).next('span');
                if ($val.length) {
                    $val.text($(this).val() + 'px');
                }
                self.markDirty();
                self.debouncePreview();
            });
        },

        /* =============================================
         * BACKGROUND TYPE
         * ============================================= */

        bindBackgroundType: function () {
            var self = this;

            $('#hikmah-bg-type').on('change', function () {
                var type = $(this).val();

                $('.hikmah-bg-gradient-row, .hikmah-bg-image-row').hide();
                $('.hikmah-bg-' + type + '-row').show();

                self.markDirty();
                self.updatePreview();
            });
        },

        /* =============================================
         * LIVE PREVIEW
         * ============================================= */

        bindLivePreview: function () {
            var self = this;

            $('#hikmah-custom-css').on('input', function () {
                self.debouncePreview();
            });
        },

        /**
         * Debounce preview updates (300ms)
         */
        debouncePreview: function () {
            var self = this;
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(function () {
                self.updatePreview();
            }, 300);
        },

        /**
         * Update the preview iframe.
         */
        updatePreview: function () {
            var $iframe = $('#hikmah-preview-iframe');

            if (!$iframe.length) {
                return;
            }

            var settings = this.collectSettings();
            var css = this.buildPreviewCSS(settings);

            try {
                var iframeDoc = $iframe[0].contentDocument || $iframe[0].contentWindow.document;
                var $style = $(iframeDoc).find('#hikmah-preview-css');

                if (!$style.length) {
                    $style = $('<style id="hikmah-preview-css"></style>');
                    $(iframeDoc).find('head').append($style);
                }

                $style.text(css);

                var $wrapper = $(iframeDoc).find('.hikmah-login-wrapper');
                $wrapper.removeClass('hikmah-layout-compact hikmah-layout-wide hikmah-layout-split hikmah-dark-mode hikmah-light-mode');

                if (settings.layout && settings.layout !== 'default') {
                    $wrapper.addClass('hikmah-layout-' + settings.layout);
                }
                if (settings.theme_mode === 'dark') {
                    $wrapper.addClass('hikmah-dark-mode');
                } else if (settings.theme_mode === 'light') {
                    $wrapper.addClass('hikmah-light-mode');
                }

                var $body = $(iframeDoc).find('body.hikmah-login-page, body');
                $body.css('backgroundColor', settings.bg_color);
            } catch (e) {
                // Cross-origin iframe — can't inject.
                console.warn('Preview injection failed:', e.message);
            }
        },

        /**
         * Collect current settings from the form DOM.
         */
        collectSettings: function () {
            var self = this;

            var settings = {
                primary_color: $('#hikmah-primary-color').val(),
                bg_color: $('#hikmah-bg-color').val(),
                card_bg_color: $('#hikmah-card-bg').val(),
                text_color: $('#hikmah-text-color').val(),
                layout: $('#hikmah-layout').val(),
                theme_mode: $('#hikmah-theme-mode').val(),
                btn_style: $('#hikmah-btn-style').val(),
                bg_type: $('#hikmah-bg-type').val(),
                custom_css: $('#hikmah-custom-css').val(),
                logo_url: $('#hikmah-logo-url').val(),
                logo_width: $('#hikmah-logo-width').val(),
                btn_radius: $('input[name="btn_radius"]').val(),
                color_preset: $('#hikmah-color-preset').val(),
                bg_gradient: $('#hikmah-bg-gradient').val(),
                bg_image: $('#hikmah-bg-image').val()
            };

            // Only include fields that actually exist in the customizer so
            // partial saves never reset stored settings to defaults.
            var optionalSelectors = {
                form_width: 'input[name="form_width"]',
                border_radius: 'input[name="border_radius"]'
            };

            Object.keys(optionalSelectors).forEach(function (key) {
                var $el = $(optionalSelectors[key]);
                if ($el.length) {
                    settings[key] = $el.val();
                }
            });

            return settings;
        },

        /**
         * Build preview CSS from settings using the framework tokens.
         */
        buildPreviewCSS: function (s) {
            var css = ':root {\n';

            if (s.primary_color) {
                css += '  --hikmah-primary: ' + s.primary_color + ';\n';
                css += '  --hikmah-primary-hover: ' + this.shadeColor(s.primary_color, -10) + ';\n';
                css += '  --hikmah-primary-light: ' + this.shadeColor(s.primary_color, 40) + ';\n';
            }
            if (s.text_color) {
                css += '  --hikmah-text: ' + s.text_color + ';\n';
            }
            if (s.card_bg_color) {
                css += '  --hikmah-surface: ' + s.card_bg_color + ';\n';
            }
            if (s.border_radius) {
                css += '  --hikmah-card-radius: ' + s.border_radius + 'px;\n';
            }
            if (s.btn_radius) {
                css += '  --hikmah-radius: ' + s.btn_radius + 'px;\n';
            }

            css += '}\n';

            if (s.bg_image) {
                css += 'body.hikmah-login-page { --hikmah-page-bg-image: url(\'' + s.bg_image + '\'); }\n';
            } else if (s.bg_type === 'gradient' && s.bg_gradient) {
                css += 'body.hikmah-login-page { background-image: ' + s.bg_gradient + '; background-size: cover; background-position: center; }\n';
            } else if (s.bg_type === 'color' && s.bg_color) {
                css += 'body.hikmah-login-page { background-color: ' + s.bg_color + ' !important; }\n';
            }

            if (s.btn_style === 'outline') {
                css += '.hikmah-btn-primary { background: transparent !important; color: var(--hikmah-primary) !important; border: 1px solid var(--hikmah-primary) !important; }\n';
            } else if (s.btn_style === 'gradient') {
                css += '.hikmah-btn-primary { background: linear-gradient(135deg, var(--hikmah-primary), #7c3aed) !important; border-color: transparent !important; }\n';
            }

            if (s.custom_css) {
                css += s.custom_css + '\n';
            }

            return css;
        },

        /**
         * Lighten/darken a hex color for the preview (percent: -100..100).
         */
        shadeColor: function (hex, percent) {
            var r, g, b;

            hex = hex.replace('#', '');
            if (hex.length === 3) {
                hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
            }

            r = parseInt(hex.substring(0, 2), 16);
            g = parseInt(hex.substring(2, 4), 16);
            b = parseInt(hex.substring(4, 6), 16);

            var adjust = function (c) {
                if (percent === 0) { return c; }
                return Math.round(c + (c * (percent / 100)));
            };

            var clamp = function (c) {
                return Math.max(0, Math.min(255, c));
            };

            return '#' + ((1 << 24) + (clamp(adjust(r)) << 16) + (clamp(adjust(g)) << 8) + clamp(adjust(b))).toString(16).slice(1);
        },

        /* =============================================
         * SAVE
         * ============================================= */

        bindSaveButton: function () {
            var self = this;

            $('#hikmah-save-ui').on('click', function () {
                var $btn = $(this);
                var $status = $('#hikmah-save-status');

                $btn.prop('disabled', true).text('Saving...');
                $status.text('');

                var settings = self.collectSettings();

                $.post(ajaxurl, {
                    action: 'hikmah_save_ui_settings',
                    nonce: self.config.nonce,
                    settings: settings
                }, function (response) {
                    if (response.success) {
                        $status.text('✅ ' + response.data.message).css('color', '#10b981');
                        self.isDirty = false;

                        setTimeout(function () {
                            if ($('#hikmah-preview-iframe')[0]) {
                                $('#hikmah-preview-iframe')[0].contentWindow.location.reload();
                            }
                        }, 500);
                    } else {
                        $status.text('❌ Error saving.').css('color', '#ef4444');
                    }
                }).fail(function () {
                    $status.text('❌ Network error.').css('color', '#ef4444');
                }).always(function () {
                    $btn.prop('disabled', false).text('💾 Save Changes');
                });
            });
        },

        markDirty: function () {
            this.isDirty = true;
        }
    };

    $(document).ready(function () {
        if ($('#hikmah-preview-iframe').length) {
            HikmahCustomizer.init();
        }
    });
})(jQuery);