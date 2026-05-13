<?php
get_header();

global $wp_query;

$post_type = get_post_type() ?: 'post';

$terms = get_terms_by_use_menu( 'category', get_post_type() );

if($wp_query->get('category_name')){
    $active_tab = $wp_query->get('category_name');

}else{
    $active_tab = 'all';
}

usort($terms, 'sort_categories');

?>

    <div class="container container--wide">

        <?php echo hacklabr\get_layout_part_header(); ?>

        <?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
            <div class="tabs" x-data="{ currentTab: '<?php echo $active_tab?>' }" x-bind="Tabs($data)">
                <div class="tabs__header" role="tablist">
                    <a class="tab" x-bind="TabButton('all', $data)" href="<?= esc_url( get_post_type_archive_link( $post_type ) ); ?>"><?php _e('All the themes', 'hacklabr') ?></a>
                    <?php foreach ( $terms as $term ) : ?>
                        <a class="tab" x-bind="TabButton('<?= esc_attr( $term->slug ); ?>', $data)" href="<?= esc_url( get_term_link( $term->term_id, 'category' ) ); ?>?post_type=<?= $post_type; ?>">
                            <?= esc_attr( $term->name ); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="tabs__panels">
                    <div class="tabs__panel" x-bind="TabPanel('<?php echo $active_tab?>', $data)">
                        <main class="posts-grid__content">
                            <?php while ( have_posts() ) : the_post(); ?>
                                <?php get_template_part( 'template-parts/post-card', null, [
                                    'hide_author' => true,
                                    'hide_date' => true,
                                    'hide_excerpt' => true,
                                    'show_taxonomies' => ['tipo_iniciativa'],
                                ] ); ?>
                            <?php endwhile; ?>
                        </main>
                        <?php
                        the_posts_pagination([
                            'prev_text' => '<iconify-icon icon="fa-solid:angle-left" aria-label="' . __('Previous page') . '"></iconify-icon>',
                            'next_text' => '<iconify-icon icon="fa-solid:angle-right" aria-label="' . __('Next page') . '"></iconify-icon>',
                        ]); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div><!-- /.container -->

<?php get_footer();
