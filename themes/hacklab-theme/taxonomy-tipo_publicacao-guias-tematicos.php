<?php
get_header();

global $wp_query;

$post_type = get_post_type() ?: 'publicacao';

$tipo_post_term = get_queried_object();

$tipo_post_slug = $tipo_post_term->slug;

$terms = get_terms_by_use_menu( 'category', $post_type );

if ( $wp_query->get( 'category' ) ) {
    $active_tab = $wp_query->get( 'category' );
} else {
    $active_tab = 'all';
}

usort( $terms, 'sort_categories' );

?>
<div class="container container--wide">

    <?php echo hacklabr\get_layout_part_header(); ?>

    <?php if ( $terms && !is_wp_error( $terms ) ) : ?>
        <div class="tabs" x-data="{ currentTab: '<?php echo $active_tab ?>' }" x-bind="Tabs( $data )">
            <div class="tabs__header" role="tablist">
                <a class="tab" x-bind="TabButton('all', $data)" href="<?= esc_url( add_query_arg ( array ( 'tipo_publicacao' => $tipo_post_slug ), get_post_type_archive_link( 'publicacao' ) ) ); ?>"><?php _e( 'All the themes', 'hacklabr' ) ?></a>
                <?php foreach ( $terms as $term ) : ?>
                    <a class="tab" x-bind="TabButton( '<?= esc_attr( $term->slug ); ?>', $data )" href="<?= esc_url( add_query_arg( array( 'tipo_publicacao' => $tipo_post_slug, 'category' => $term->slug ), get_post_type_archive_link( 'publicacao' ) ) ); ?>">
                        <?= esc_attr( $term->name ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="tabs__panels">
                <div class="tabs__panel" x-bind="TabPanel( '<?php echo $active_tab ?>', $data )">
                    <main class="posts-grid__content">
                        <?php
                        // Verificação dos parâmetros
                        $current_category = get_query_var( 'category' );
                        $current_tipo_post = get_query_var( 'tipo_publicacao' );

                        $args = array(
                            'post_type' => 'publicacao',
                            'tax_query' => array(
                                array(
                                    'taxonomy' => 'tipo_publicacao',
                                    'field' => 'slug',
                                    'terms' => $current_tipo_post,
                                ),
                            ),
                        );

                        if ( $current_category && $current_category !== 'all' ) {
                            $args['tax_query'][] = array(
                                'taxonomy' => 'category',
                                'field' => 'slug',
                                'terms' => $current_category,
                            );
                        }

                        $query = new WP_Query($args);

                        if ( $query->have_posts() ) :
                            while ( $query->have_posts() ) : $query->the_post();
                                get_template_part('template-parts/post-card', null, [
                                    'hide_author' => true,
                                    'hide_excerpt' => true,
                                    'modifiers' => ['vertical-thumbnail'],
                                    'show_taxonomies' => ['tipo_publicacao'],
                                ]);
                            endwhile;
                            wp_reset_postdata();

                        endif;
                        ?>
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

<?php get_footer(); ?>
