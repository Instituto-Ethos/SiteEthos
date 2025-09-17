import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';
import { registerBlockType } from '@wordpress/blocks';
import { Disabled, PanelBody, PanelRow, ToggleControl } from '@wordpress/components';
import { __experimentalNumberControl as NumberControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import { QueryPanel } from '../shared/QueryPanel';
import { SelectCardModel } from '../shared/SelectCardModel';
import { SelectCardModifier } from '../shared/SelectCardModifier';
import { SelectSize } from '../shared/SelectSize';
import { SelectTaxonomies } from '../shared/SelectTaxonomies';

import metadata from './block.json';

function Edit ({ clientId, attributes, setAttributes }) {
    const { instanceId } = attributes;

    useEffect(() => {
        if (!instanceId) setAttributes({ instanceId: clientId });
    }, [clientId]);

    const { cardModel, cardModifiers, enablePagination, gridGap, hideAuthor, hideCategories, hideDate, hideExcerpt, postsPerColumn, postsPerPage, postsPerRow, postType, preventRepeatPosts, showTaxonomies } = attributes;

    const blockProps = useBlockProps();

    return <>
        <InspectorControls>
            <PanelBody className="hacklabr-gutenberg-panel__panel-body" title={__('Layout', 'hacklabr')}>
                <PanelRow>
                    <SelectCardModel
                        value={cardModel}
                        onChange={(cardModel) => setAttributes({ cardModel })}
                    />
                </PanelRow>

                <PanelRow>
                    <SelectCardModifier
                        value={cardModifiers}
                        onChange={(cardModifiers) => setAttributes({ cardModifiers })}
                    />
                </PanelRow>

                <PanelRow>
                    <ToggleControl
                        label={__('Enable pagination?', 'hacklabr')}
                        checked={enablePagination}
                        onChange={(enablePagination) => setAttributes({ enablePagination })}
                    />
                </PanelRow>

                { enablePagination &&
                    <PanelRow>
                        <NumberControl
                            label={__('Posts per page', 'hacklabr')}
                            min={0}
                            max={99}
                            value={postsPerPage || 0}
                            onChange={(raw) => {
                                const n = parseInt(raw, 10);
                                setAttributes({ postsPerPage: Number.isFinite(n) ? Math.max(0, n) : 0 });
                            }}
                        />
                    </PanelRow>
                }

                <PanelRow>
                    <NumberControl
                        label={__('Grid columns', 'hacklabr')}
                        min={1}
                        value={postsPerRow}
                        onChange={(raw) => setAttributes({ postsPerRow: parseInt(raw) })}
                    />
                </PanelRow>

                { !enablePagination &&
                    <PanelRow>
                        <NumberControl
                            label={__('Grid rows', 'hacklabr')}
                            min={1}
                            value={postsPerColumn}
                            onChange={(raw) => setAttributes({ postsPerColumn: parseInt(raw) })}
                        />
                    </PanelRow>
                }

                <PanelRow>
                    <SelectSize
                        label={__('Grid gap', 'hacklabr')}
                        value={gridGap}
                        onChange={(gridGap) => setAttributes({ gridGap })}
                    />
                </PanelRow>

                <PanelRow>
                    <ToggleControl
                        label={__('Hide author', 'hacklabr')}
                        checked={hideAuthor}
                        onChange={(hideAuthor) => setAttributes({ hideAuthor })}
                    />
                </PanelRow>

                <PanelRow>
                    <ToggleControl
                        label={__('Hide categories', 'hacklabr')}
                        checked={hideCategories}
                        onChange={(hideCategories) => setAttributes({ hideCategories })}
                    />
                </PanelRow>

                <PanelRow>
                    <ToggleControl
                        label={__('Hide date', 'hacklabr')}
                        checked={hideDate}
                        onChange={(hideDate) => setAttributes({ hideDate })}
                    />
                </PanelRow>

                <PanelRow>
                    <ToggleControl
                        label={__('Hide excerpt', 'hacklabr')}
                        checked={hideExcerpt}
                        onChange={(hideExcerpt) => setAttributes({ hideExcerpt })}
                    />
                </PanelRow>

                <PanelRow>
                    <SelectTaxonomies
                        label={__('Show taxonomies', 'hacklabr')}
                        postType={postType}
                        value={showTaxonomies}
                        onChange={(showTaxonomies) => setAttributes({ showTaxonomies })}
                    />
                </PanelRow>
                <PanelRow>
                    <ToggleControl
                        label={__('Prevent repeat posts', 'hacklabr')}
                        checked={preventRepeatPosts}
                        onChange={(preventRepeatPosts) => setAttributes({ preventRepeatPosts })}
                    />
                </PanelRow>

            </PanelBody>

            <QueryPanel
                attributes={attributes}
                setAttributes={setAttributes}
            />
        </InspectorControls>

        <div {...blockProps}>
            <Disabled>
                <ServerSideRender block="hacklabr/posts-grid" attributes={attributes}/>
            </Disabled>
        </div>
    </>;
}

registerBlockType(metadata.name, {
    edit: Edit,
});
