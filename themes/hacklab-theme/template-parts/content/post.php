<article id="post-ID-<?php the_ID(); ?>" class="post">
    <div class="post-card">
        <div class="post-card--thumb">
            <a href="<?php the_permalink(); ?>">
                <div class="aspect-ratio">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail( 'medium_large' ); ?>
                    <?php else : ?>
                        <img loading="lazy" decoding="async" src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/default-image.png" alt="<?php the_title(); ?>">
                    <?php endif; ?>
                </div><!-- /.aspect-ratio -->
            </a>
        </div><!-- /.post-card--thumb -->

        <div class="post-card--content">
            <div class="entry-meta"><?php echo get_html_terms( get_the_ID(), 'category', true ); ?></div>
            <a href="<?php the_permalink(); ?>"><h5 class="entry-title"><?php the_title(); ?></h5></a>
            <div class="entry-date"><?php echo get_the_date(); ?></div>
            <div class="entry-excerpt">
                <?= get_custom_excerpt() ?>
            </div>
        </div><!-- /.post-card--content -->
    </div><!-- /.post-card -->
</article><!-- /.post -->
