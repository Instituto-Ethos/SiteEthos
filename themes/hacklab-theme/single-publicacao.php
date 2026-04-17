<?php
    get_header();
    $categories = get_the_category();
    $post_id = get_the_ID();
    $documento_url = get_field('arquivo_publicacao', $post_id);
?>
<div class="container container--wide book-detail">
    <aside class="stack">
        <div class="thumbnail thumbnail--vertical">
            <?php if (has_post_thumbnail()): ?>
                <?php the_post_thumbnail('card-large'); ?>
            <?php else: ?>
                <img class="placeholder" src="<?= get_stylesheet_directory_uri() ?>/assets/images/placeholder-ethos.png" alt="">
            <?php endif; ?>
        </div>
        <button>
            <?php if ($documento_url) : ?>
                <a class="button button--outline" href="<?php echo esc_url($documento_url); ?>">
                    <?php _e('Publication Sample', 'hacklabr'); ?>
                </a>
            <?php else : ?>
                <p><?php _e('No sample available.', 'hacklabr'); ?></p>
            <?php endif; ?>
        </button>
    </aside>
    <main class="container container--wide">
        <?php if (!empty($categories)): ?>
            <div class="post-card__category">
                <?php foreach ($categories as $category): ?>
                    <a class="tag tag--solid tag--<?= $category->slug ?>" href="<?= get_term_link($category, 'category') ?>">
                        <?= $category->name ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="post-header post-header__title post-header--title-start">
            <h2><?php the_title() ?></h2>
        </div>

        <div class="post-header__meta ">
            <p class="post-header__date"><?php _e('Published in', 'hacklabr') ?> <?= get_the_date() ?></p>
            <?php get_template_part('template-parts/share-links', null, ['link'=>get_the_permalink()]) ?>
        </div>
        <div class="post-content stack">
            <p> <?php the_content() ?></p>

           <!--  <div class="form-book">
                <div class="form-book form-book__title">
                    <h2>
                    <iconify-icon icon="fa-solid:book-open"></iconify-icon>
                    Solicite download gratuito
                    </h2>
                </div>
            </div> -->

        </div>

    </main>
</div>
<?php get_template_part('template-parts/content/related-posts', null, ['modifiers' => ['vertical-thumbnail']]) ?>



<?php get_footer() ?>
