</div>
<?php get_template_part('template-parts/partners-list');?>
<?php wp_reset_postdata() ?>
<?php if ( is_active_sidebar( 'footer_widgets' ) ):?> <!-- exibe os parceiros no footer -->
    <footer class="main-footer">
        <div class="main-footer__widgets container">
            <?php dynamic_sidebar('footer_widgets') ?>
        </div>

        <div class="main-footer__credit">
            <div class="content">
                <img loading="lazy" decoding="async" src="<?= get_template_directory_uri() ?>/assets/images/site-por-hacklab.png" alt="site por hacklab" height="15" width="103">
            </div>

        </div>
    </footer>
<?php endif; ?>
<?php wp_footer() ?>

</body>
</html>
