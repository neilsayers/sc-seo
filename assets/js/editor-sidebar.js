(function () {
    'use strict';

    var el = wp.element.createElement;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var __ = wp.i18n.__;
    var registerPlugin = wp.plugins.registerPlugin;
    var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var CheckboxControl = wp.components.CheckboxControl;
    var SelectControl = wp.components.SelectControl;
    var Button = wp.components.Button;
    var blockEditor = wp.blockEditor || wp.editor;
    var MediaUpload = blockEditor.MediaUpload;
    var MediaUploadCheck = blockEditor.MediaUploadCheck;

    var SCHEMA_TYPES = (window.scseoSidebar && window.scseoSidebar.schemaTypes) || { auto: 'Automatic (recommended)' };

    /**
     * Same field set as MetaBoxes\SeoMetaBox's classic box — this is
     * the block-editor rendering of the exact same _scseo_* postmeta
     * (see MetaBoxes\SeoMetaFields for the REST registration that
     * makes reading/writing it from here possible at all). Which one
     * a post type sees is decided server-side by
     * use_block_editor_for_post_type(), never both at once.
     */
    function charCount(value, recommended) {
        var length = (value || '').length;

        return length + ' characters' + (recommended ? ' (recommended up to ' + recommended + ')' : '');
    }

    function SCSEOPanel() {
        var meta = useSelect(function (select) {
            return select('core/editor').getEditedPostAttribute('meta') || {};
        }, []);

        var editPost = useDispatch('core/editor').editPost;

        function setMeta(key, value) {
            var patch = {};
            patch[key] = value;
            editPost({ meta: patch });
        }

        var ogImageId = meta._scseo_og_image || 0;

        var ogImage = useSelect(function (select) {
            return ogImageId ? select('core').getMedia(ogImageId) : null;
        }, [ogImageId]);

        return el(
            PluginDocumentSettingPanel,
            { name: 'scseo-panel', title: __('SC SEO', 'sc-seo'), className: 'scseo-sidebar-panel' },
            el(TextControl, {
                label: __('SEO title', 'sc-seo'),
                value: meta._scseo_title || '',
                help: __('Leave blank to use the site\'s default title template.', 'sc-seo') + ' ' + charCount(meta._scseo_title, 60),
                onChange: function (value) { setMeta('_scseo_title', value); },
            }),
            el(TextareaControl, {
                label: __('Meta description', 'sc-seo'),
                value: meta._scseo_description || '',
                help: charCount(meta._scseo_description, 155),
                onChange: function (value) { setMeta('_scseo_description', value); },
            }),
            el(TextControl, {
                label: __('Canonical URL', 'sc-seo'),
                type: 'url',
                value: meta._scseo_canonical || '',
                help: __('Only needed if this content is also reachable at another URL and this is the one you want indexed.', 'sc-seo'),
                onChange: function (value) { setMeta('_scseo_canonical', value); },
            }),
            el(CheckboxControl, {
                label: __('Discourage search engines from indexing this (noindex)', 'sc-seo'),
                checked: !! meta._scseo_noindex,
                onChange: function (value) { setMeta('_scseo_noindex', value); },
            }),
            el(CheckboxControl, {
                label: __('Don\'t follow links on this page (nofollow)', 'sc-seo'),
                checked: !! meta._scseo_nofollow,
                onChange: function (value) { setMeta('_scseo_nofollow', value); },
            }),
            el(TextControl, {
                label: __('Social title', 'sc-seo'),
                value: meta._scseo_og_title || '',
                placeholder: __('Falls back to the SEO title above', 'sc-seo'),
                onChange: function (value) { setMeta('_scseo_og_title', value); },
            }),
            el(TextareaControl, {
                label: __('Social description', 'sc-seo'),
                value: meta._scseo_og_description || '',
                placeholder: __('Falls back to the meta description above', 'sc-seo'),
                onChange: function (value) { setMeta('_scseo_og_description', value); },
            }),
            el(
                'div',
                { className: 'scseo-sidebar-field' },
                el('label', { className: 'components-base-control__label' }, __('Social image', 'sc-seo')),
                ogImage
                    ? el('img', { className: 'scseo-sidebar-image-preview', src: ogImage.source_url, alt: '' })
                    : null,
                el(
                    MediaUploadCheck,
                    {},
                    el(MediaUpload, {
                        onSelect: function (media) { setMeta('_scseo_og_image', media.id); },
                        allowedTypes: ['image'],
                        value: ogImageId,
                        render: function (props) {
                            return el(
                                'div',
                                {},
                                el(Button, { variant: 'secondary', onClick: props.open, className: 'scseo-sidebar-image-button' }, ogImageId ? __('Change image', 'sc-seo') : __('Choose image', 'sc-seo')),
                                ogImageId ? el(Button, { variant: 'link', isDestructive: true, onClick: function () { setMeta('_scseo_og_image', 0); } }, __('Remove', 'sc-seo')) : null
                            );
                        },
                    })
                ),
                el('p', { className: 'components-base-control__help' }, __('Falls back to the featured image, then the sitewide default image (SC SEO → General).', 'sc-seo'))
            ),
            el(SelectControl, {
                label: __('Structured data', 'sc-seo'),
                value: meta._scseo_schema_type || 'auto',
                options: Object.keys(SCHEMA_TYPES).map(function (value) {
                    return { label: SCHEMA_TYPES[value], value: value === '' ? 'auto' : value };
                }),
                onChange: function (value) { setMeta('_scseo_schema_type', value === 'auto' ? '' : value); },
            })
        );
    }

    registerPlugin('scseo-sidebar', {
        icon: 'search',
        render: SCSEOPanel,
    });
})();
