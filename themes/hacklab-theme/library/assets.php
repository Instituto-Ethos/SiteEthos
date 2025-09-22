<?php

namespace hacklabr;

class Assets
{
    private static $instances = [];
    protected $js_files;
    protected $css_files;
    protected $fonts_files;

    protected function __construct()
    {
        $this->initialize();
    }

    public static function getInstance()
    {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static();
        }

        return self::$instances[$cls];
    }

    /**
     * Adds the action and filter hooks to integrate with WordPress.
     */
    public function initialize()
    {
        add_action('wp_head', [$this, 'action_preload_javascripts']);
        add_action('wp_head', [$this, 'action_preload_fonts']);
        $this->enqueue_scripts();
        $this->enqueue_styles();
        add_action('enqueue_block_assets', [$this, 'enqueue_block_assets']);
        add_action('after_setup_theme', [$this, 'action_add_editor_styles']);
    }
    protected function get_font_files(): array
    {
        if (is_array($this->fonts_files)) {
            return $this->fonts_files;
        }
        $fonts_files = [
            'OpenSans-Regular'     => [
                'file' => 'OpenSans-Regular.ttf',
                'global' => true,
                'pre-load' => true,
            ],

            'Montserrat-Bold'     => [
                'file' => 'Montserrat-Bold.ttf',
                'pre-load' => true,
            ],

            'OpenSans-Bold' => [
                'file'   => 'OpenSans-Bold.ttf',
                'pre-load' => true,
            ],

        ];


        $this->fonts_files = [];
        foreach ($fonts_files as $handle => $data) {
            if (is_string($data)) {
                $data = ['file' => $data];
            }

            if (empty($data['file'])) {
                continue;
            }

            $this->fonts_files[$handle] = array_merge(
                [
                    'global'           => false,
                    'preload_callback' => null,
                ],
                $data
            );
        }

        return $this->fonts_files;
    }

    public function action_preload_javascripts()
    {
        $js_uri = get_theme_file_uri('/dist/js/functionalities/');
        $js_files = $this->get_js_files();

        foreach ($js_files as $handle => $data) {
            $src = $js_uri . $data['file'];
            if ($data['pre-load']) {
                echo "\n";
                echo '<link rel="preload" id="' . esc_attr($handle) . '-preload" href="' . esc_url($src) . '" as="script">';
                echo "\n";
            }
        }
    }
    public function action_preload_fonts()
    {
        $font_uri = get_theme_file_uri('/assets/fonts/');
        $font_files = $this->get_font_files();
        foreach ($font_files as $handle => $data) {
            $src = $font_uri . $data['file'];
            if ($data['pre-load']) {
                echo "\n";
                echo '<link rel="preload" id="' . esc_attr($handle) . '-preload" href="' . esc_url($src) . '" as="font">';
                echo "\n";
            }
        }
    }

    /**
     * Registers or enqueues scripts.
     *
     * Stylesheets that are global are enqueued. All other stylesheets are only registered, to be enqueued later.
     */
    public function enqueue_scripts()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_javascripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_javascripts']);
        add_action('wp_enqueue_scripts', [$this, 'add_externalized_dependencies'], 11);
        add_action('admin_enqueue_scripts', [$this, 'add_externalized_dependencies'], 11);
        add_action('wp_enqueue_scripts', [$this, 'localize_scripts'], 12);
        add_action('admin_enqueue_scripts', [$this, 'localize_scripts'], 12);
    }

    public function localize_scripts()
    {
        $js_files = $this->get_js_files();

        $language_path = get_stylesheet_directory() . '/languages';

        foreach ($js_files as $handle => $data) {
            wp_set_script_translations($handle, 'hacklabr', $language_path);
        }
    }

    /**
     * Registers or enqueues stylesheets.
     *
     * Stylesheets that are global are enqueued. All other stylesheets are only registered, to be enqueued later.
     */
    public function enqueue_styles()
    {
        add_action('wp_head', [$this, 'enqueue_inline_styles'], 99);
        add_action('wp_head', [$this, 'add_print_style'], 99);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_generic_styles']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
    }

    public function should_preload_asset($asset)
    {
        if ($asset['global']) {
            return true;
        }
        return is_callable($asset['preload_callback']) && call_user_func($asset['preload_callback']);
    }

    public function add_print_style() {
        if (is_singular()) {
            global $post;
            $raw_content = $post->post_content;

            if (has_shortcode($raw_content, 'ethosGeraCertificado') || has_shortcode($raw_content, 'ethosGeraCertificado2')) {
                echo '<style>@media print { @page { size: landscape } }</style>';
            }
        }
    }

    public function enqueue_inline_styles()
    {
        $css_uri = get_stylesheet_directory() . '/dist/css/';

        $css_files = $this->get_css_files();
        foreach ($css_files as $handle => $data) {
            $src = $css_uri . $data['file'];
            $content = file_get_contents($src);

            if ($data['inline'] && self::should_preload_asset($data)) {
                echo "<style id='$handle-css'>" . $content . "</style>";
            }
        }
    }

    public function enqueue_generic_styles()
    {
        $css_uri = get_theme_file_uri('/dist/css/');
        $css_dir = get_theme_file_path('/dist/css/');

        $css_files = $this->get_css_files();
        foreach ($css_files as $handle => $data) {
            /**
             * Skip inline styles
             */
            if ($data['inline']) {
                continue;
            }

            $src = $css_uri . $data['file'];
            $version = (string) filemtime($css_dir . $data['file']);
            $deps = [];
            if (isset($data['deps']) && !empty($data['deps'])) {
                $deps = $data['deps'];
            }

            /*
            * Enqueue global stylesheets immediately and register the other ones for later use
            */
            if (self::should_preload_asset($data)) {
                wp_enqueue_style($handle, $src, $deps, $version, $data['media']);
            } else {
                wp_register_style($handle, $src, $deps, $version, $data['media']);
            }

            wp_style_add_data($handle, 'precache', true);
        }
    }

    public function enqueue_javascripts()
    {
        $js_uri = get_theme_file_uri('/dist/js/functionalities/');

        $js_files = $this->get_js_files();

        foreach ($js_files as $handle => $data) {

            if ($data['admin']) {
                continue;
            }

            if (self::should_preload_asset($data)) {
                $src = $js_uri . $data['file'];

                // Version is overridden in the `add_externalized_dependencies` function below
                $version = false;

                if (empty($data['deps'])) {
                    $deps = [];
                } else {
                    $deps = $data['deps'];
                }

                wp_enqueue_script($handle, $src, $deps, $version, true);

                if (!empty($data['localize_callback'])) {
                    $object_name = 'hl_' . str_replace('-', '_', $handle) . '_data';
                    wp_localize_script($handle, $object_name, $data['localize_callback']());
                }
            }
        }
    }

    public function enqueue_admin_javascripts()
    {
        $js_uri = get_theme_file_uri('/dist/js/functionalities/');

        $js_files = $this->get_js_files();

        foreach ($js_files as $handle => $data) {

            if (!$data['admin']) {
                continue;
            }


            if (self::should_preload_asset($data)) {
                $src = $js_uri . $data['file'];

                // Version is overridden in the `add_externalized_dependencies` function below
                $version = false;

                if (empty($data['deps'])) {
                    $deps = [];
                } else {
                    $deps = $data['deps'];
                }

                wp_enqueue_script($handle, $src, $deps, $version, true);

                if (!empty($data['localize_callback'])) {
                    $object_name = 'hl_' . str_replace('-', '_', $handle) . '_data';
                    wp_localize_script($handle, $object_name, $data['localize_callback']());
                }
            }
        }
    }

    /**
     * Automatically add dependencies found by `/dist/assets.php` file
     */
    public function add_externalized_dependencies()
    {
        global $wp_scripts;

        $assets_meta = require __DIR__ . '/../dist/assets.php';
        $dist_dir = get_theme_file_uri('/dist/');

        foreach ($wp_scripts->registered as $wp_script) {
            if (str_starts_with($wp_script->src, $dist_dir)) {
                $asset_key = str_replace($dist_dir, '/', $wp_script->src);

                if (!empty($assets_meta[$asset_key])) {
                    $asset_meta = $assets_meta[$asset_key];

                    $wp_script->ver = $asset_meta['version'];
                    $wp_script->deps = array_unique(array_merge($wp_script->deps, $asset_meta['dependencies']), SORT_STRING);
                }
            }
        }
    }

    /**
     * Register and enqueue a custom stylesheet in the WordPress admin.
     */
    public function enqueue_admin_styles()
    {
        $css_uri = get_theme_file_uri('/dist/css/');

        wp_enqueue_style('hacklabr-editor', $css_uri . 'editor.css');
    }

    public function enqueue_block_assets()
    {
        if (is_admin()) {
            $css_uri = get_theme_file_uri('/dist/css/');

            wp_enqueue_style('app', $css_uri . 'app.css');
        }
    }

    /**
     * Enqueues WordPress theme styles for the editor.
     */
    public function action_add_editor_styles()
    {
        add_editor_style('assets/css/editor/editor-styles.min.css');
    }

    /**
     * Prints stylesheet link tags directly.
     *
     * This should be used for stylesheets that aren't global and thus should only be loaded if the HTML markup
     * they are responsible for is actually present. Template parts should use this method when the related markup
     * requires a specific stylesheet to be loaded. If preloading stylesheets is disabled, this method will not do
     * anything.
     *
     * If the `<link>` tag for a given stylesheet has already been printed, it will be skipped.
     *
     * @param string ...$handles One or more stylesheet handles.
     */
    public function print_styles(string ...$handles)
    {
        $css_files = $this->get_css_files();
        $handles   = array_filter(
            $handles,
            function ($handle) use ($css_files) {
                $is_valid = isset($css_files[$handle]) && !$css_files[$handle]['global'];
                if (!$is_valid) {
                    /* translators: %s: stylesheet handle */
                    _doing_it_wrong(__CLASS__ . '::print_styles()', esc_html(sprintf(__('Invalid theme stylesheet handle: %s', 'buddyx'), $handle)), 'Buddyx 2.0.0');
                }
                return $is_valid;
            }
        );

        if (empty($handles)) {
            return;
        }

        wp_print_styles($handles);
    }

    /**
     * Gets all CSS files.
     *
     * @return array Associative array of $handle => $data pairs.
     */
    protected function get_css_files(): array
    {
        if (is_array($this->css_files)) {
            return $this->css_files;
        }

        $css_files = [
            'app' => [
                'file' => 'app.css',
                'global' => true,
                'inline' => true,
            ],

            /*
            'page' => [
                'file' => 'p-page.css',
                'preload_callback' => function() {
					return !is_front_page() && is_page();
				},
            ],
			*/
        ];

        /**
         * Filters default CSS files.
         *
         * @param array $css_files Associative array of CSS files, as $handle => $data pairs.
         * $data must be an array with keys 'file' (file path relative to 'assets/css'
         * directory), and optionally 'global' (whether the file should immediately be
         * enqueued instead of just being registered) and 'preload_callback' (callback)
         * function determining whether the file should be preloaded for the current request).
         */
        $css_files = apply_filters('css_files_before_output', $css_files);


        $this->css_files = [];
        foreach ($css_files as $handle => $data) {
            if (empty($data['file'])) {
                continue;
            }

            $this->css_files[$handle] = array_merge(
                [
                    'global'           => false,
                    'preload_callback' => null,
                    'media'            => 'all',
                ],
                $data
            );
        }

        return $this->css_files;
    }


    /**
     * Gets all JS files.
     *
     * @return array Associative array of $handle => $data pairs.
     */
    protected function get_js_files(): array
    {
        if (is_array($this->js_files)) {
            return $this->js_files;
        }

        $js_files = [
            'app' => [
                'file' => 'app.js',
                'pre-load' => true,
                'global' => true,
            ],

            'gutenberg' => [
                'file'   => 'gutenberg.js',
                'pre-load' => true,
                'admin'  => true,
                'global' => true,
            ],

            'event-registration' => [
                'file' => 'event-registration.js',
                'pre-load' => false,
                'preload_callback' => function () {
                    return is_singular( 'tribe_events' );
                },
                'localize_callback' => function () {
                    return [
                        'baseUrl' => rest_url( 'hacklabr/v2/' ),
                    ];
                }
            ],

            'sync-crm' => [
                'file' => 'sync-crm.js',
                'pre-load' => false,
                'admin' => true,
                'preload_callback' => function () {
                    $screen = get_current_screen();
                    if ($screen && $screen->is_block_editor()) {
                        return $screen->base === 'post' && ($screen->post_type === 'tribe_events' || $screen->post_type === 'organizacao');
                    }
                    return false;
                },
                'localize_callback' => function () {
                    $screen = get_current_screen();

                    if ($screen->post_type === 'tribe_events') {
                        $project_id = get_post_meta(get_the_ID(), 'entity_fut_projeto', true);

                        if (!empty($project_id)) {
                            return [
                                'entityName' => 'fut_projeto',
                                'entityId' => $project_id,
                            ];
                        }
                    }

                    if ($screen->post_type === 'organizacao') {
                        $account_id = get_post_meta(get_the_ID(), '_ethos_crm:accountid', true);

                        if (!empty($account_id)) {
                            return [
                                'entityName' => 'account',
                                'entityId' => $account_id,
                            ];
                        }
                    }

                    return [
                        'entityName' => null,
                        'entityId' => null,
                    ];
                }
            ],

            'scroll-behavior'     => [
                'file' => 'anchor-behavior.js',
                'pre-load' => true,
                'global' => true,
            ],

            'search' => [
                'pre-load' => false,
                'file'   => 'search.js',
                'global' => true,
            ],

            'search-organizations' => [
                'pre-load'         => false,
                'file'             => 'search-organizations.js',
                'preload_callback' => function () {
                    return is_page_template( 'template-list-organizations.php' );
                },
                'localize_callback' => function () {
                    return [
                        'baseUrl' => rest_url( 'hacklabr/v2/' ),
                        'nonce'   => wp_create_nonce( 'wp_rest' )
                    ];
                }
            ],

            'copy-url' => [
                'pre-load' => false,
                'file' => 'copy-url.js',
                'global' => true,
            ],

            'anchor-sidebar'     => [
                'pre-load' => false,
                'file' => 'anchor-sidebar.js',
                'preload_callback' => function () {
                    return is_page_template('page-anchor.php');
                }
            ],

            'tabs' => [
                'pre-load' => false,
                'file' => 'tabs.js',
                'global' => true,
            ],

            'menu' => [
                'pre-load' => true,
                'file' => 'menu.js',
                'global' => true,
            ],
        ];

        $js_files = apply_filters('js_files_before_output', $js_files);

        $this->js_files = [];
        foreach ($js_files as $handle => $data) {
            if (empty($data['file'])) {
                continue;
            }

            $this->js_files[$handle] = array_merge(
                [
                    'global'           => false,
                    'admin'            => false,
                    'preload_callback' => null,
                ],
                $data
            );
        }

        return $this->js_files;
    }
}


$assets_manager = Assets::getInstance();
