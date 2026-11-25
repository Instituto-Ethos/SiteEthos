import { useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { Placeholder, SVG, Path } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';

function Icon ({ size }) {
    // https://icon-sets.iconify.design/grommet-icons/coins/
    return (
        <SVG height={size} width={size} viewBox="0 0 24 24">
            <Path fill="none" stroke="currentColor" stroke-width="2" d="M12 4V1L8 4l4 3V6a6 6 0 1 1-6 6H4a8 8 0 1 0 8-8zm7 8a7 7 0 0 1-1 3l1.5 1.5A9 9 0 0 0 21 12h-2z"/>
        </SVG>
    );
}

function Edit ({ attributes }) {
    const blockProps = useBlockProps();

    return (
        <div {...blockProps}>
            <Placeholder label={__('Meu saldo de créditos', 'hacklabr')} icon={Icon}/>
        </div>
    );
}

registerBlockType(metadata.name, {
    icon: Icon,
    edit: Edit,
});
