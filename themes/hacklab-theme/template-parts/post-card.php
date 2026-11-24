<?php
global $post;
$original_post = $post;
$post = $args['post'] ?? $post;

$image_size = $args['image_size'] ?? 'card-large';

$hide_author = (bool) ($args['hide_author'] ?? false);
$hide_categories = (bool) ($args['hide_categories'] ?? false);
$hide_date = (bool) ($args['hide_date'] ?? false);
$hide_excerpt = (bool) ($args['hide_excerpt'] ?? false);

$modifiers = (array) ($args['modifiers'] ?? []);
$modifiers = array_map(fn ($modifier) => "post-card--{$modifier}", $modifiers);
$modifiers = implode(' ', $modifiers);
$post_id = get_the_ID();
$editoria = get_primary_term($post_id, 'tipo_post');
$categories = get_the_category();
$publication_terms = get_html_terms( $post_id , 'tipo_publicacao' );
$initiative_terms = get_html_terms( $post_id , 'tipo_iniciativa' );
$show_taxonomies = (array) ($args['show_taxonomies'] ?? []);

?>
<article id="post-ID-<?php the_ID(); ?>" class="post-card <?=$modifiers?>">
    <header class="post-card__image">
        <a href="<?php the_permalink();?>" aria-label="<?= esc_attr(get_the_title()) ?>">
            <?php if (has_post_thumbnail()): ?>
                <?php the_post_thumbnail($image_size); ?>
            <?php else: ?>
                <img class="placeholder" loading="lazy" decoding="async" src="<?= get_stylesheet_directory_uri() ?>/assets/images/placeholder-ethos.png" alt="">
            <?php endif; ?>
        </a>
    </header>

    <main class="post-card__content">

        <div class="post-card__terms">
            <?php if ( $publication_terms ) : ?>
                <div class="post-card__term">
                    <span class="tag"><?php echo $publication_terms ?></span>
                </div>
            <?php endif; ?>

            <?php if ( $initiative_terms ) : ?>
                <div class="post-card__term">
                    <span class="tag"><?php echo $initiative_terms ?></span>
                </div>
            <?php endif; ?>

            <?php if (!$hide_categories && (!empty($categories) || !empty($editoria))): ?>
                <div class="post-card__category">
                    <?php if (!empty($editoria) && !is_wp_error($editoria)): ?>
                        <a class="tag tag--outline" href="<?= get_term_link($editoria, 'tipo_post') ?>">
                            <?= $editoria->name ?>
                        </a>
                    <?php endif; ?>
                    <?php foreach ($categories as $category): ?>
                        <a class="tag tag--solid tag--<?= $category->slug ?>" href="<?= get_term_link($category, 'category') ?>">
                            <?= $category->name ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

        <h3 class="post-card__title">
            <a href="<?php the_permalink();?>"><?php the_title();?></a>
        </h3>

        <?php if (!$hide_author || !$hide_date): ?>
        <div class="post-card__meta">
            <?php if (!$hide_author): ?>
            <div class="post-card__author">
                <?php the_author(); ?>
            </div>
            <?php endif; ?>

            <?php if (!$hide_date): ?>
            <time class="post-card__date">
            <?php _e('Published in ', 'hacklabr') ?></a>
                <?php echo get_the_date('d/m/Y'); ?>
            </time>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!$hide_excerpt): ?>
        <div class="post-card__excerpt">
            <?= get_the_excerpt(); ?>
        </div>
        <?php endif; ?>
    </main>
</article>

<?php
$post = $original_post;
