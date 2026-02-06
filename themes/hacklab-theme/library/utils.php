<?php
/**
 *
 * Remove recaptcha from tainacan
 *
 */

add_action( 'init', function() {
    wp_dequeue_script( 'tainacan-google-recaptcha-script' );
}, 150 );

function get_page_by_template (string $template) {
	$pages = get_pages([
		'post_type' => 'page',
		'meta_key' => '_wp_page_template',
		'hierarchical' => 0,
		'meta_value' => $template,
	]);

	foreach ($pages as $page) {
		return $page;
	}

	return false;
}

/**
 * Print the excerpt with limit words
 */
function get_custom_excerpt( $post_id = '', $limit = 30 ) {

    if ( empty( $post_id ) ) {
        $post_id = get_the_ID();
    }

    // If exists excerpt metadata
    $excerpt = get_post_meta( $post_id, 'excerpt', true );

    if ( empty( $excerpt ) ) {
        $excerpt = get_the_excerpt( $post_id );
    }

    if ( empty( $excerpt ) ) {
        $excerpt = wp_trim_excerpt( '', $post_id );
    }

    $excerpt = wp_strip_all_tags( $excerpt );
    $excerpt = explode( ' ', $excerpt, $limit );

    if ( count( $excerpt ) >= $limit ) {
        array_pop( $excerpt );
        $excerpt = implode( ' ', $excerpt ) . ' ...';
    } else {
        $excerpt = implode( ' ', $excerpt );
    }

    return $excerpt;

}

function concat_class_list ($classes = []) {
    $filtered_classes = [];

    foreach ($classes as $class) {
        if (!empty($class)) {
            if (is_array($class)) {
                $filtered_classes[] = concat_class_list($class);
            } else {
                $filtered_classes[] = $class;
            }
        }
    }

    return implode(' ', $filtered_classes);
}

/**
 * Rename the defaults taxonomies
 */
function rename_taxonomies() {

    // Tags -> Temas
    $post_tag_args = get_taxonomy( 'post_tag' );

    $post_tag_args->label = 'Temas';
    $post_tag_args->labels->name = 'Temas';
    $post_tag_args->labels->singular_name = 'Tema';
    $post_tag_args->labels->search_items = 'Pesquisar tema';
    $post_tag_args->labels->popular_items = 'Temas populares';
    $post_tag_args->labels->all_items = 'Todos temas';
    $post_tag_args->labels->parent_item = 'Tema superior';
    $post_tag_args->labels->edit_item = 'Editar tema';
    $post_tag_args->labels->view_item = 'Ver tema';
    $post_tag_args->labels->update_item = 'Atualizar tema';
    $post_tag_args->labels->add_new_item = 'Adicionar novo tema';
    $post_tag_args->labels->new_item_name = 'Nome do novo tema';
    $post_tag_args->labels->separate_items_with_commas = 'Separe os temas com vírgulas';
    $post_tag_args->labels->add_or_remove_items = 'Adicionar ou remover temas';
    $post_tag_args->labels->choose_from_most_used = 'Escolha entre os temas mais usados';
    $post_tag_args->labels->not_found = 'Nenhum tema encontrado';
    $post_tag_args->labels->no_terms = 'Nenhum tema';
    $post_tag_args->labels->items_list_navigation = 'Navegação da lista de temas';
    $post_tag_args->labels->items_list = 'Lista de temas';
    $post_tag_args->labels->most_used = 'Temas mais utilizados';
    $post_tag_args->labels->back_to_items = '&larr; Ir para os temas';
    $post_tag_args->labels->item_link = 'Link do tema';
    $post_tag_args->labels->item_link_description = 'Um link para o tema';
    $post_tag_args->labels->menu_name = 'Temas';
    $post_tag_args->hierarchical = true;

    $object_type = array_merge( $post_tag_args->object_type, ['page'] );
    $object_type = array_unique( $object_type );

    register_taxonomy( 'post_tag', $object_type, (array) $post_tag_args );

    // Category -> Projetos
    $category_args = get_taxonomy( 'category' );

    $category_args->label = 'Projetos';
    $category_args->labels->name = 'Projetos';
    $category_args->labels->singular_name = 'Projeto';
    $category_args->labels->search_items = 'Pesquisar Projeto';
    $category_args->labels->popular_items = 'Projetos populares';
    $category_args->labels->all_items = 'Todos Projetos';
    $category_args->labels->parent_item = 'Projeto superior';
    $category_args->labels->edit_item = 'Editar Projeto';
    $category_args->labels->view_item = 'Ver Projeto';
    $category_args->labels->update_item = 'Atualizar Projeto';
    $category_args->labels->add_new_item = 'Adicionar novo Projeto';
    $category_args->labels->new_item_name = 'Nome do novo Projeto';
    $category_args->labels->separate_items_with_commas = 'Separe os Projetos com vírgulas';
    $category_args->labels->add_or_remove_items = 'Adicionar ou remover Projetos';
    $category_args->labels->choose_from_most_used = 'Escolha entre os Projetos mais usados';
    $category_args->labels->not_found = 'Nenhum Projeto encontrado';
    $category_args->labels->no_terms = 'Nenhum Projeto';
    $category_args->labels->items_list_navigation = 'Navegação da lista de Projetos';
    $category_args->labels->items_list = 'Lista de Projetos';
    $category_args->labels->most_used = 'Projetos mais utilizados';
    $category_args->labels->back_to_items = '&larr; Ir para os Projetos';
    $category_args->labels->item_link = 'Link do Projeto';
    $category_args->labels->item_link_description = 'Um link para o Projeto';
    $category_args->labels->menu_name = 'Projetos';

    $object_type = array_merge( $category_args->object_type, ['page'] );
    $object_type = array_unique( $object_type );

    register_taxonomy( 'category', $object_type, (array) $category_args );

}
// Descomentar para renomear as taxonomias padrão do WP
// add_action( 'init', 'rename_taxonomies', 11 );

// Page Slug Body Class
function add_slug_body_class( $classes ) {
    global $post;
    if ( isset( $post ) ) {
    $classes[] = $post->post_type . '-' . $post->post_name;
    }
    return $classes;
}
add_filter( 'body_class', 'add_slug_body_class' );

/**
 * Return the structure HTML of the posts separetade by month
 *
 * @param array $args use params of the class WP_Query
 * @link https://developer.wordpress.org/reference/classes/wp_query/#parameters
 *
 * @return array months|slider
 */
function get_posts_by_month( $args = [] ) {

    $args['orderby'] = 'date';

    $items = new WP_Query( $args );

    if( $items->have_posts() ) :

        $month_titles   = [];
        $close_ul       = false;
        $content_slider = '';

        while( $items->have_posts() ) : $items->the_post();

            $month_full = [
                'Jan' => 'Janeiro',
                'Feb' => 'Fevereiro',
                'Mar' => 'Marco',
                'Apr' => 'Abril',
                'May' => 'Maio',
                'Jun' => 'Junho',
                'Jul' => 'Julho',
                'Aug' => 'Agosto',
                'Nov' => 'Novembro',
                'Sep' => 'Setembro',
                'Oct' => 'Outubro',
                'Dec' => 'Dezembro'
            ];

            $year = date( 'Y', strtotime( get_the_date( 'Y-m-d H:i:s' ) ) );
            $month = date( 'M', strtotime( get_the_date( 'Y-m-d H:i:s' ) ) );

            $month_title = $month_full[$month] . ' ' . $year;

            if ( ! in_array( $month_title, $month_titles ) ) :
                if ( $close_ul ) $content_slider .= '</ul>';
                $content_slider .= '<ul id="items-' . sanitize_title( $month_title ) . '" class="item-slider">';
                $month_titles[] = $month_title;
                $close_ul = true;
            endif;

            $thumbnail = ( has_post_thumbnail( get_the_ID() ) ) ? get_the_post_thumbnail( get_the_ID() ) : '<img src="' . get_stylesheet_directory_uri() . '/assets/images/default-image.png">';

            $content_slider .= sprintf(
                '<li id="item-%1$s" class="item item-month-%2$s"><a href="%3$s"><div class="thumb">%4$s</div><div class="title"><h3>%5$s</h3></div></a></li>',
                get_the_ID(),
                $month_title,
                get_permalink( get_the_ID() ),
                $thumbnail,
                get_the_title( get_the_ID() )
            );

        endwhile;

        if ( $close_ul ) $content_slider .= '</ul>';
    endif;

    return [
        'months' => $month_titles,
        'slider' => $content_slider
    ];

}

function allow_svg_uploads( $file_types ){
	$file_types['svg'] = 'image/svg+xml';
	return $file_types;
}
add_filter( 'upload_mimes', 'allow_svg_uploads' );

function archive_filter_posts( $query ) {
    // Apply filter of the archives
    if ( $query->is_main_query() && ! is_admin() ) {

        $is_blog = false;
        $page_for_posts = get_option( 'page_for_posts' );

        if ( $query->is_home() && isset( $query->get_queried_object()->ID ) && $query->get_queried_object()->ID == $page_for_posts ) {
            $is_blog = true;
        }

        if ( is_archive() || $is_blog ) {
            if ( isset( $_GET['filter_term'] ) && 'all' !== $_GET['filter_term'] ) {
                $term = get_term_by_slug( $_GET['filter_term'] );

                if ( $term && ! is_wp_error( $term ) ) {
                    $tax_query = [
                        [
                            'field'    => 'slug',
                            'taxonomy' => $term->taxonomy,
                            'terms'    => [ $term->slug ]
                        ]
                    ];

                    $query->set( 'tax_query', $tax_query );
                }
            }
        }

        if ( is_search() ) {
            /**
             * Adds a tax query to the main query to filter posts by the 'curadoria' category.
             */
            if ( isset( $_GET['curadoria'] ) ) {
                $tax_query = [
                    [
                        'field'    => 'slug',
                        'taxonomy' => 'category',
                        'terms'    => 'exclusivo-do-associado',
                        'operator' => 'IN'
                    ]
                ];

                if ( isset( $query->query_vars['tax_query'] ) ) {
                    $mount_tax_query = $query->query_vars['tax_query'];

                    if ( ! isset( $mount_tax_query['relation'] ) ) {
                        $mount_tax_query['relation'] = 'AND';
                    }

                    $mount_tax_query[] = $tax_query;
                    $query->set( 'tax_query', $mount_tax_query );
                } else {
                    $query->set('tax_query', ['relation' => 'AND', $tax_query]);
                }
            }
        }
    }
}
add_action( 'pre_get_posts', 'archive_filter_posts' );

/**
 * Retrieves a list of pages by their template.
 *
 * @param string $template The page template to filter by. Defaults to 'default'.
 * @param array  $args     Optional. Additional arguments to pass to `get_posts()`.
 * @return WP_Post[]       An array of page posts.
 */
if ( ! \function_exists( 'get_pages_by_template' ) ) {
    function get_pages_by_template( $template = 'default', $args = [] ) {
        $args += [
            'post_type'      => 'page',
            'meta_key'       => '_wp_page_template',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'posts_per_page' => -1
        ];

        $args['meta_value'] = $template;

        return \get_posts( $args );
    }
}

/**
 * Get the URL for the login page.
 *
 * This function checks if the Paid Memberships Pro plugin is active and uses its login page URL if available,
 * otherwise it falls back to the default WordPress login URL.
 *
 * @return string The URL for the login page.
 */
if ( ! \function_exists( 'get_login_page_url' ) ) {
    function get_login_page_url() {
        $login_url = \function_exists( 'pmpro_url' ) && ( $pmpro_url = pmpro_url( 'login' ) ) ? $pmpro_url : wp_login_url();
        return esc_url( $login_url );
    }
}

function pmpro_add_placeholder_to_login() {
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            let loginField = document.querySelector('#user_login');
            let passwordField = document.querySelector('#user_pass');
            let loginLabel = document.querySelector('label[for="user_login"]');
            let actionNav = document.querySelector('p.pmpro_actions_nav');
            let submitButton = document.querySelector('#wp-submit');

            if ( loginField ) {
                loginField.setAttribute( 'placeholder', 'Insira o e-mail do usuário' );
            }

            if ( passwordField ) {
                passwordField.setAttribute( 'placeholder', 'Digite sua senha' );
            }

            if( loginLabel){
                loginLabel.innerHTML = 'E-mail';
            }

            if ( actionNav ) {
                let link = actionNav.querySelector('a');

                if ( link ) {
                    link.innerHTML = 'Esqueci a senha';
                }
            }

            if( submitButton ) {
                submitButton.value = 'Entrar';
            }

        });
    </script>
    <?php
}
add_action('wp_footer', 'pmpro_add_placeholder_to_login');

// Adiciona o campo de captcha no login

if ( class_exists( 'WPCaptcha_Functions' ) ) {
    function wprecaptcha_login_print_scripts() {
        remove_action( 'lostpassword_post', array('WPCaptcha_Functions', 'process_lost_password_form'), 10, 1 );
        remove_action( 'validate_password_reset', array('WPCaptcha_Functions', 'process_lost_password_form'), 10, 2 );
    }

    add_action( 'init', 'wprecaptcha_login_print_scripts' );

    add_action( 'doifd_display_recaptcha', 'open_captcha_container_div' );
    add_action( 'doifd_display_recaptcha', ['WPCaptcha_Functions', 'captcha_fields_print'] );
    add_action( 'doifd_display_recaptcha', ['WPCaptcha_Functions', 'login_scripts_print'] );
    add_action( 'doifd_display_recaptcha', 'close_captcha_container_div' );
}

/**
 * Adds actions to display the reCAPTCHA fields on the DOIFD download form.
 * This allows the reCAPTCHA fields to be displayed on the DOIFD download form.
 */

function open_captcha_container_div() {
    echo "<div class='captcha-container'>";
}

function close_captcha_container_div() {
    echo "</div>";
}

/**
 * Handles the submission of a DOIFD download form and verifies the reCAPTCHA response.
 *
 * This function is hooked to the 'doifd_registration_form_shortcode' action and is responsible for
 * validating the reCAPTCHA response submitted with the DOIFD download form. If the reCAPTCHA
 * verification fails, the function sets the form's valid download flag to false and sets an error
 * message. If the verification is successful, the function sets a success message on the form.
 *
 * @param object $get_form The DOIFD form object.
 * @return void
 */
function get_doifd_form_submit( object $get_form ) {

    if ( ! class_exists( 'WPCaptcha_Setup') ) {
        return;
    }

    if ( ! empty( $_POST['doifd_download_form'] ) ) {
        if ( ! empty( $_POST['g-recaptcha-response'] ) ) {
            $check_recaptcha = doifd_check_recaptcha( $_POST['g-recaptcha-response'] );

            if ( ! $check_recaptcha ) {
                $get_form->setValidDownload( false );
                $get_form->setErrorMessage( __( 'The reCAPTCHA verification failed. Try again!', 'hacklabr' ) );
                return;
            } else {
                $get_form->setMessage( 'doifd_success_msg:recaptcha verification successful' );
                return;
            }
        } else {
            $get_form->setValidDownload( false );
            $get_form->setErrorMessage( __( 'The reCAPTCHA verification failed. Try again!', 'hacklabr' ) );
            return;
        }
    }
}

add_action( 'doifd_registration_form_shortcode', 'get_doifd_form_submit' );

/**
 * Checks the reCAPTCHA response from the client-side.
 *
 * This function sends a request to the Google reCAPTCHA API to verify the reCAPTCHA response
 * submitted by the client. If the verification is successful, the function returns true,
 * otherwise it returns false.
 *
 * @param string $response The reCAPTCHA response from the client.
 * @return bool True if the reCAPTCHA verification is successful, false otherwise.
 */
function doifd_check_recaptcha( $response ) {
    if ( ! class_exists( 'WPCaptcha_Setup') ) {
        return;
    }

    $options = WPCaptcha_Setup::get_options();
    $secret_key = $options['captcha_secret_key'];

    $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret'   => $secret_key,
            'response' => $response
        ]
    ] );

    if ( is_wp_error( $response ) ) {
        return false;
    }

    $decoded_response = json_decode( $response['body'], true );

    return isset( $decoded_response['success'] ) && $decoded_response['success'];
}

function captcha_fields() {
    if ( ! class_exists( 'WPCaptcha_Setup' ) ) {
        error_log('WPCaptcha_Setup class not found. Please ensure the CAPTCHA plugin is active.');
        return '<p style="color: red;">CAPTCHA plugin is not active. Please contact the administrator.</p>';
    }

    $options = \WPCaptcha_Setup::get_options();
    $html = '';

    if ( ! isset($options['captcha']) || ! isset($options['captcha_site_key']) ) {
        error_log('CAPTCHA options are not properly set. Please configure the plugin correctly.');
        return '<p style="color: red;">CAPTCHA is not configured properly. Please contact the administrator.</p>';
    }

    if ( $options['captcha'] == 'recaptchav2' ) {
        $html .= '<div class="g-recaptcha" style="transform: scale(0.9); -webkit-transform: scale(0.9); transform-origin: 0 0; -webkit-transform-origin: 0 0;" data-sitekey="' . esc_html($options['captcha_site_key']) . '"></div>';
        $html .= '<script>
        jQuery("form.woocommerce-checkout").on("submit", function(){
            setTimeout(function(){
                grecaptcha.reset();
            },100);
        });
        </script>';
    } else if ( $options['captcha'] == 'recaptchav3' ) {
        $html .= '<input type="hidden" name="g-recaptcha-response" class="agr-recaptcha-response" value="" />';
        $html .= '<script>
        function wpcaptcha_captcha(){
            grecaptcha.execute("' . esc_html($options['captcha_site_key']) . '", {action: "submit"}).then(function(token) {
                var captchas = document.querySelectorAll(".agr-recaptcha-response");
                captchas.forEach(function(captcha) {
                    captcha.value = token;
                });
            });
        }

        jQuery("form.woocommerce-checkout").on("submit", function(){
            setTimeout(function(){
                wpcaptcha_captcha();
            },100);
        });
        </script>';
    } else if ( $options['captcha'] == 'builtin' ) {
        $html .= '<p><label for="wpcaptcha_captcha">Are you human? Please solve: ';
        $captcha_id = rand(1000,9999);
        $html .= '<img class="wpcaptcha-captcha-img" style="vertical-align: text-top;" src="' . esc_url(WPCAPTCHA_PLUGIN_URL) . 'libs/captcha.php?wpcaptcha-generate-image=true&color=' . esc_attr(urlencode('#FFFFFF')) . '&noise=1&id=' . intval($captcha_id) . '" alt="Captcha" />';
        $html .= '<input class="input" type="text" size="3" name="wpcaptcha_captcha[' . intval($captcha_id) . ']" id="wpcaptcha_captcha" />';
        $html .= '</label></p><br />';
    } else {
        error_log('Unknown CAPTCHA type configured.');
        return '<p style="color: red;">Unknown CAPTCHA type configured. Please contact the administrator.</p>';
    }

    return $html;
}


function tribe_events_category() {
	register_taxonomy_for_object_type( 'category', 'tribe_events' );
}

add_action( 'init', 'tribe_events_category', 0 );

function wp1482371_custom_post_type_args( $args, $post_type ) {
    if ( $post_type == "tribe_events" ) {
        $args['taxonomies'][] = 'category';
    }

    return $args;
}
add_filter( 'register_post_type_args', 'wp1482371_custom_post_type_args', 20, 2 );

function redirect_single_tribe_events_template() {
	if ( is_singular( 'tribe_events' ) ) {
		$new_template = locate_template('single-evento.php');
		if ($new_template) {
			include($new_template);
			exit;
		}
	}
}
add_action( 'template_redirect', 'redirect_single_tribe_events_template' );

/**
 * Redirect recurring event occurrences to the main event.
 */
function redirect_recurring_events_to_parent() {
    if ( is_admin() || ! is_singular( 'tribe_events' ) ) {
        return;
    }

    if ( ! function_exists( 'tribe_get_event' ) ) {
        return;
    }

    $event     = tribe_get_event( get_queried_object_id() );
    $parent_id = 0;

    if ( isset( $event->_tec_occurrence ) && $event->_tec_occurrence instanceof \TEC\Events\Custom_Tables\V1\Models\Occurrence ) {
        $parent_id = (int) $event->_tec_occurrence->post_id;
    } elseif ( ! empty( $event->post_parent ) ) {
        $parent_id = (int) $event->post_parent;
    }

    if ( ! $parent_id || $parent_id === (int) $event->ID ) {
        return;
    }

    $parent_permalink = get_permalink( $parent_id );
    if ( $parent_permalink && $parent_permalink !== get_permalink( $event->ID ) ) {
        wp_safe_redirect( $parent_permalink, 301 );
        exit;
    }

}
add_action( 'template_redirect', 'redirect_recurring_events_to_parent', 5 );

/**
 * Get the primary term of a given taxonomy
 * @param int $post_id Post ID
 * @param string $taxonomy Taxonomy slug
 * @param bool $force_primary If should avoid returning fallback if the primary term is not set
 * @return \WP_Term|false The primary term, or `false` on failure
 */
function get_primary_term( $post_id, $taxonomy, $force_primary = false ) {
	$primary_term_id = get_post_meta( $post_id, '_yoast_wpseo_primary_' . $taxonomy, true );

	// Returns the primary term, if it exists
	if ( ! empty( $primary_term_id ) ) {
		$primary_term = get_term( $primary_term_id, $taxonomy );

		if ( ! empty( $primary_term ) ) {
			return $primary_term;
		}
	}

	// Returns an assorted term, if primary term does not exists
	if ( ! $force_primary ) {
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			return $terms[0];
		}
	}

	// On failure, returns false
	return false;
}

function get_primary_category($terms, $post_id, $taxonomy){

    if(is_archive() || is_search() || is_page('indicadores') || is_front_page() || is_page() || is_singular() ) {
        if( $taxonomy == 'category' ){
            $term_id = get_post_meta($post_id, '_yoast_wpseo_primary_category', true);
            if ($term_id) {
                $get_term = get_term($term_id, $taxonomy);
                if( $get_term ){
                    $terms = [];
                    $terms[] = $get_term;
                }
            }
        }
    }
    return $terms;
}
add_filter('get_the_terms', 'get_primary_category', 10, 3);

/**
 * Return the first result from `get_posts`, or null if empty
 * @param array $args The args for `get_posts`
 */
function get_single_post (array $args) {
    unset($args['fields']);
    $posts = get_posts($args);

    if (empty($posts)) {
        return null;
    } else {
        $post = $posts[0];
        assert($post instanceof \WP_Post);
        return $post;
    }
}

/**
 * Return the first result from `get_users`, or null if empty
 * @param array $args The args for `get_users`
 */
function get_single_user (array $args) {
    unset($args['fields']);
    $users = get_users($args);

    if (empty($users)) {
        return null;
    } else {
        $user = $users[0];
        assert($user instanceof \WP_User);
        return $user;
    }
}

/**
 * Return event posts, suppressing TEC filters
 * @param array $args The args for `get_posts`
 * @return \WP_Post[]
 */
function get_tribe_events (array $args) {
    unset($args['fields']);

    $parsed_args = wp_parse_args($args, [
        'post_type' => 'tribe_events',

        // The Events Calendar can add filters that may cause a false mismatch
        'suppress_filters' => true,
        'tribe_remove_date_filters' => true,
        'tribe_suppress_query_filters' => true,
    ]);

    return get_posts($parsed_args);
}

/**
 * Return the first event from `get_posts`, suppressing TEC filters, or null if empty
 * @param array $args The args for `get_posts`
 */
function get_single_tribe_event (array $args) {
    unset($args['fields']);

    $parsed_args = wp_parse_args($args, [
        'post_type' => 'tribe_events',

        // The Events Calendar can add filters that may cause a false mismatch
        'suppress_filters' => true,
        'tribe_remove_date_filters' => true,
        'tribe_suppress_query_filters' => true,
    ]);

    return get_single_post($parsed_args);
}

//remove blocos do Events Calendar do Gutenberg
function filter_allowed_block_types($allowed_block_types, $editor_context)
{
	$registry = WP_Block_Type_Registry::get_instance();
	$registerd_blocks = $registry->get_all_registered();
	$registerd_blocks = array_keys($registerd_blocks);

	$blocks_to_remove = [
        'tribe/classic-event-details',
        'tribe/event-datetime',
        'tribe/event-venue',
        'tribe/event-organizer',
        'tribe/event-links',
        'tribe/event-price',
        'tribe/event-category',
        'tribe/event-tags',
        'tribe/event-website',
        'tribe/featured-image',
        'tribe/related-events',
        'tec/archive-events',
        'tec/single-event'
	];

	$allowed_block_types = array_diff($registerd_blocks, $blocks_to_remove);
	$allowed_block_types = array_values($allowed_block_types);

	return $allowed_block_types;
}
add_filter('allowed_block_types_all', 'filter_allowed_block_types', 10, 2);

//remove blocos do Events Calendar do início da edição de um novo evento
function my_custom_tribe_events_editor_template( $template) {
    return [];
}

add_filter( 'tribe_events_editor_default_template', 'my_custom_tribe_events_editor_template', 50, 3);
add_filter( 'tribe_events_editor_default_classic_template', 'my_custom_tribe_events_editor_template', 50, 3);

function list_registered_blocks() {
    $blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();

    echo '<div style="padding: 20px; background-color: #f5f5f5; margin-top: 20px;">';
    echo '<h2>Registered Blocks</h2>';
    echo '<ul>';
    foreach ($blocks as $block) {
        echo '<li>' . esc_html($block->name) . '</li>';
    }
    echo '</ul>';
    echo '</div>';
}

// add_action('admin_notices', 'list_registered_blocks');

/**
 * Retrieves the name of the manager associated with an organization.
 * @param int|null $post_id The organization ID (default to current user's organization).
 * @return string|null The display name of the manager, or null if no manager is found.
 */
function get_manager_name($post_id = null) {
    if ( empty( $post_id ) ) {
        $current_user = get_current_user_id();

        $organization = hacklabr\get_organization_by_user( $current_user );

        if ( ! empty( $organization ) ) {
            $post_id = $organization->ID;
        }
    }

    if ( empty( $post_id ) ) {
        return null;
    }

    $owner_json = get_post_meta( $post_id, '_ethos_crm:ownerid', true );

    if ( empty( $owner_json ) ) {
        return null;
    }

    $owner_data = json_decode( $owner_json, false );
    return $owner_data->Name ?? null;
}

/**
 * Retrieves the name of an organization.
 *
 * @param int|null $post_id The organization ID (default to current user's organization).
 * @return string|null The title of the organization post, or null if no
 *                     organization is found.
 */
function get_organization_name( $post_id = null ) {
    if ( empty( $post_id ) ) {
        $current_user = get_current_user_id();

        $organization = hacklabr\get_organization_by_user( $current_user );

        if ( ! empty( $organization ) ) {
            $post_id = $organization->ID;
        }
    }

    if ( empty( $post_id ) ) {
        return null;
    }

    $organization = get_post( $post_id );

    return $organization->post_title ?? null;
}

/**
 * Retrieves the name of the primary contact associated with an organization.
 * @param int|null $post_id The organization ID (default to current user's organization).
 * @return string|null The display name of the contact, or null if no contact is found.
 */
function get_primary_contact_name($post_id = null) {
    if ( empty( $post_id ) ) {
        $current_user = get_current_user_id();

        $organization = hacklabr\get_organization_by_user( $current_user );

        if ( ! empty( $organization ) ) {
            $post_id = $organization->ID;
        }
    }

    if ( empty( $post_id ) ) {
        return null;
    }

    $organization = get_post( $post_id );

    $author_id = $organization->post_author;
    $author = get_user_by( 'ID', $author_id );

    return $author->display_name ?? null;
}

// Adiciona o reCAPTCHA ao formulário de redefinição de senha
function my_custom_recaptcha_for_reset_pass() {
    if (isset($_GET['action']) && $_GET['action'] == 'reset_pass') {
        echo '<div class="g-recaptcha" data-sitekey="6Ld-GewpAAAAAAcpRSdn9bn3nkTw_5U7dR65IA4a"></div>';
        wp_enqueue_script('recaptcha', 'https://www.google.com/recaptcha/api.js');
    }
}
add_action('login_form', 'my_custom_recaptcha_for_reset_pass');

// Valida o reCAPTCHA na redefinição de senha
function my_custom_recaptcha_verify() {
    if (isset($_GET['action']) && $_GET['action'] == 'reset_pass') {
        if (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response'])) {
            wp_die('Por favor, complete o reCAPTCHA.');
        }

        $response = wp_remote_get("https://www.google.com/recaptcha/api/siteverify?secret=6Ld-GewpAAAAANrN0d-lyhZlrHsKE1_Fw30guatM&response=" . $_POST['g-recaptcha-response']);
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        if (!$result['success']) {
            wp_die('Verificação reCAPTCHA falhou, por favor tente novamente.');
        }
    }
}
add_action('login_form_resetpass', 'my_custom_recaptcha_verify');
add_action('login_form_rp', 'my_custom_recaptcha_verify');

function custom_logout_redirect() {
    if (isset($_GET['action']) && $_GET['action'] == 'logout') {
        wp_logout();
        wp_redirect( home_url());
        exit;
    }
}

add_action('init', 'custom_logout_redirect');

function custom_rewrite_rules() {
    add_rewrite_rule(
        '^tipo-post/([^/]+)/post_type/([^/]+)/?category/([^/]+)/',
        'index.php?tipo_post=$matches[1]&category=$matches[2]&post_type=$matches[3]',
        'top'
    );
    add_rewrite_rule(
        '^tipo-post/([^/]+)/post_type/([^/]+)/?',
        'index.php?tipo_post=$matches[1]&post_type=$matches[2]',
        'top'
    );
    add_rewrite_rule(
        '^tipo-publicacao/([^/]+)/post_type/([^/]+)/?category/([^/]+)/',
        'index.php?tipo_publicacao=$matches[1]&category=$matches[2]&post_type=$matches[3]',
        'top'
    );
    add_rewrite_rule(
        '^tipo-publicacao/([^/]+)/post_type/([^/]+)/?',
        'index.php?tipo_publicacao=$matches[1]&post_type=$matches[2]',
        'top'
    );
}
add_action('init', 'custom_rewrite_rules');

function add_custom_query_vars($vars) {
    $vars[] = 'tipo_post';
    $vars[] = 'category';
    $vars[] = 'tipo_publicacao';
    return $vars;
}
add_filter('query_vars', 'add_custom_query_vars');

function custom_pre_get_posts( $query ) {
    // Verifica se estamos no front-end e na consulta principal
    if ( !is_admin() && $query->is_main_query() ) {

        // Verifica se uma categoria foi passada
        if ( isset( $_GET['category'] ) && !empty( $_GET['category'] ) ) {
            $query->set( 'category_name', sanitize_text_field( $_GET['category'] ) );
        }

        // Verifica se o termo da taxonomia tipo_post foi passado
        if ( isset( $_GET['tipo_post'] ) && !empty( $_GET['tipo_post'] ) ) {
            $tax_query = $query->get( 'tax_query' );

            if ( !is_array( $tax_query ) ) {
                $tax_query = [];
            }

            $tax_query[] = [
                'taxonomy' => 'tipo_post',
                'field'    => 'slug',
                'terms'    => sanitize_text_field( $_GET['tipo_post'] ),
            ];

            $query->set( 'tax_query', $tax_query );
        }

        // Verifica se publicacao foi passado como um tipo de post
        if ( isset( $_GET['publicacao'] ) && !empty( $_GET['publicacao'] ) ) {
            $post_types = $query->get( 'post_type' );

            if ( !is_array( $post_types ) ) {
                $post_types = ['post']; // Garante que o tipo de post padrão seja 'post'
            }

            $post_types[] = 'publicacao'; // Adiciona 'publicacao' ao array de tipos de post

            $query->set( 'post_type', $post_types );
        }
    }
}
add_action( 'pre_get_posts', 'custom_pre_get_posts' );

/**
 * Logs data if the 'logger' parameter is set in the URL and the current user has the 'manage_options' capability.
 *
 * @param mixed $data The data to be logged.
 */
if ( ! function_exists( 'get_logger' ) ) {
    function get_logger( $data ) {
        if ( isset( $_GET['logger'] ) && current_user_can( 'manage_options' ) ) {
            do_action( 'logger', $data );
        }
    }
}

add_filter( 'wp_mail', 'fix_attachments_on_wp_mail' );

/**
 * Corrige os anexos no envio de e-mails no WordPress.
 *
 * Esta função verifica se a classe `Mailgun` está disponível e, caso contrário,
 * retorna os atributos do e-mail sem alterações. Se a classe estiver disponível,
 * ela verifica se o campo de anexos (`attachments`) é uma string e, se for igual
 * ao nome do site, redefine os anexos como um array vazio.
 *
 * @param array $atts Os atributos do e-mail, incluindo os anexos.
 *
 * @return array Os atributos do e-mail corrigidos.
 */
function fix_attachments_on_wp_mail( $atts ) {
    if ( ! class_exists( 'Mailgun' ) ) {
        return $atts;
    }

    if ( isset( $atts['attachments'] ) && is_string( $atts['attachments'] ) ) {
        $site_name = get_bloginfo( 'name' );

        if ( trim( $atts['attachments'] ) === trim( $site_name ) ) {
            $atts['attachments'] = [];
        }
    }

    return $atts;
}


add_filter('posts_results', function ($posts, $query) {
    // Não afeta admin
    if (is_admin()) {
        return $posts;
    }

    // Se não estiver logado, remove manualmente posts da categoria 'curadoria'
    if (!is_user_logged_in()) {
        foreach ($posts as $i => $post) {
            if (has_category('curadoria', $post)) {
                unset($posts[$i]);
            }
        }
        // Reindexa o array (importante!)
        $posts = array_values($posts);
    }

    return $posts;
}, 10, 2);
