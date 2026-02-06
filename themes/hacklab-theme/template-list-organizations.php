<?php
/**
 * Template Name: List Organizations
 */

get_header();
?>
    <div class="container">
        <div class="post-header">
            <h1 class="post-header__title alignwide"><?php the_title() ?></h1>
        </div>
        <div class="post-content content stack">
            <?php the_content() ?>
        </div>

        <?php if ( current_user_can( 'edit_others_associates' ) ) : ?>
            <div class="search-posts">
                <div class="search-posts__input-wrapper">
                    <label for="organization-search" class="screen-reader-text"><?= __( 'Buscar organização', 'hacklabr' ) ?></label>
                    <input
                        id="organization-search"
                        class="organization-search__input"
                        type="search"
                        placeholder="<?= __( 'Buscar organização...', 'hacklabr' ) ?>"
                        autocomplete="off"
                        aria-controls="organization-list"
                        aria-label="<?= __( 'Buscar organização', 'hacklabr' ) ?>"
                    />
                    <div id="organization-search-status" class="organization-search__status" aria-live="polite" aria-atomic="true"></div>
                </div>
                <div class="search-posts__list-wrapper" id="organization-list-wrapper">
                    <?php
                    $organizations = get_posts( [
                        'post_type'      => 'organizacao',
                        'posts_per_page' => 10,
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                        'meta_query'     => [
                            [ 'key' => '_ethos_crm_account_id', 'compare' => 'EXISTS', 'value' => true ],
                        ],
                    ] );

                    if ( $organizations && is_array( $organizations ) ) : ?>
                        <ul>
                            <?php foreach ( $organizations as $organization ) : ?>
                                <li class="organization-card">
                                    <a href="/associados/boas-vindas/?organization=<?= $organization->ID ?>" class="organization-card__link"><?php echo esc_html( $organization->post_title ); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                            <p><?= __( 'No organizations found.', 'hacklabr' ) ?></p>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>
    </div>
<?php get_footer();
