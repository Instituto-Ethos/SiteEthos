<?php
/**
 * Template Name: Membership area
 */

namespace hacklabr;

get_header();

$current_post_id = get_the_ID();

?>

<div class="content-sidebar">
    <div class="container container--wide content-sidebar__container">
        <aside class="content-sidebar__sidebar">
            <p class="content-sidebar__sidebar-description"><?= __( 'Associeted panel', 'hacklabr' ); ?></p>
            <?php
            $associates_areas = \get_pages_by_template( 'template-associates-area.php' );

            if ( is_array( $associates_areas ) && ! empty( $associates_areas ) ) {
                echo '<ul class="content-sidebar__list">';

                $query_string = $_SERVER['QUERY_STRING'] ?? '';
                $get_params = [];

                if ( $query_string ) {
                    parse_str( $query_string, $get_params );

                    $allowed_params = ['organization'];

                    foreach ( $get_params as $key => $value ) {
                        if ( ! in_array( $key, $allowed_params, true ) ) {
                            unset( $get_params[$key] );
                        } else {
                            $get_params[$key] = sanitize_text_field( $value );
                        }
                    }
                }

                foreach ( $associates_areas as $associates_area ) {
                    if ( show_associated_page( $associates_area ) && $associates_area->post_name !== 'solicitacao-enviada' ) {
                        $css_class = $current_post_id === $associates_area->ID
                            ? 'content-sidebar__list-item content-sidebar__list-item--active'
                            : 'content-sidebar__list-item';

                        $url = get_permalink( $associates_area );
                        if ( ! empty( $get_params ) ) {
                            $url = add_query_arg( $get_params, $url );
                        }

                        echo '<li class="' . esc_attr( $css_class ) . '">';
                        echo '<a href="' . esc_url( $url ) . '">' . wp_kses_post( get_the_title( $associates_area ) ) . '</a>';
                        echo '</li>';
                    }
                }
                echo '</ul>';
            }
            ?>

            <a class="button button--outline" href="<?= wp_logout_url( get_login_page_url() ); ?>"><?= __( 'Logout', 'hacklabr' ); ?></a>
        </aside>
        <main class="content-sidebar__content stack">
            <div class="collapse-menu active"></div>
            <?php the_content(); ?>

            <script>
                document.querySelector( '.collapse-menu' ).addEventListener( 'click', function() {
                    var sidebar = document.querySelector( '.content-sidebar__sidebar' );
                    if ( sidebar.style.display === 'none' || sidebar.style.display === '' ) {
                        sidebar.style.display = 'block';
                    } else {
                        sidebar.style.display = 'none';
                    }
                });
            </script>
        </main>
    </div>
</div>

<?php get_footer(); ?>
