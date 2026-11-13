import { ToggleControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { PluginPostStatusInfo } from '@wordpress/edit-post';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

registerPlugin('hacklabr-unlisted-event', {
    render () {
        const meta = useSelect((select) => select('core/editor').getEditedPostAttribute('meta') || {}, []);
        const { editPost } = useDispatch('core/editor');

        function onChange (checked) {
            editPost({ meta: { ...meta, _ethos_unlisted: checked } });
        }

        return (
            <PluginPostStatusInfo>
                <ToggleControl
                    label={__('Hide from agenda', 'hacklabr')}
                    help={__('Keep this event accessible only by direct link — hidden from the agenda, search results and sitemap.', 'hacklabr')}
                    checked={Boolean(meta._ethos_unlisted)}
                    onChange={onChange}
                />
            </PluginPostStatusInfo>
        );
    },
});
