<?php
/**
 * Plugin Name:       Breadcrumbs
 * Plugin URI:        https://github.com/Azanumer/wp-breadcrumbs
 * Description:       SEO breadcrumbs with JSON-LD structured data — auto-inserted or via [breadcrumbs] shortcode, with separator and home-label settings.
 * Version:           1.0.0
 * Author:            Azan Umer
 * License:           MIT
 * Text Domain:       wbcr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WBCR_VERSION', '1.0.0' );

/** Default settings. */
function wbcr_defaults() {
    return array(
        'separator'   => '›',
        'home_label'  => 'Home',
        'show_home'   => 1,
        'auto_insert' => 1,
    );
}

function wbcr_settings() {
    return wp_parse_args( get_option( 'wbcr_settings', array() ), wbcr_defaults() );
}

/**
 * Build the breadcrumb trail for the current query as an ordered list of
 * [ 'label' => string, 'url' => string|null ] items.
 */
function wbcr_get_trail() {
    $settings = wbcr_settings();
    $trail    = array();

    if ( $settings['show_home'] ) {
        $trail[] = array(
            'label' => $settings['home_label'],
            'url'   => home_url( '/' ),
        );
    }

    if ( is_front_page() ) {
        return $trail; // Home page: home crumb only.
    }

    if ( is_category() || is_tag() || is_tax() ) {
        $term = get_queried_object();
        // Walk up the term hierarchy so parent terms appear in order.
        $ancestors = array();
        $parent_id = (int) $term->parent;
        while ( $parent_id > 0 ) {
            $parent = get_term( $parent_id, $term->taxonomy );
            if ( ! $parent || is_wp_error( $parent ) ) {
                break;
            }
            array_unshift( $ancestors, $parent );
            $parent_id = (int) $parent->parent;
        }
        foreach ( $ancestors as $ancestor ) {
            $trail[] = array( 'label' => $ancestor->name, 'url' => get_term_link( $ancestor ) );
        }
        $trail[] = array( 'label' => $term->name, 'url' => null );
    } elseif ( is_singular() ) {
        $post = get_queried_object();
        // Primary category first (first category, filterable via wbcr_primary_category).
        $categories = get_the_category( $post->ID );
        if ( ! empty( $categories ) ) {
            $primary = apply_filters( 'wbcr_primary_category', $categories[0], $post );
            // Include the category's ancestors too.
            $ancestors = get_ancestors( $primary->term_id, 'category' );
            $ancestors = array_reverse( $ancestors );
            foreach ( $ancestors as $ancestor_id ) {
                $ancestor = get_category( $ancestor_id );
                $trail[]  = array( 'label' => $ancestor->name, 'url' => get_category_link( $ancestor ) );
            }
            $trail[] = array( 'label' => $primary->name, 'url' => get_category_link( $primary ) );
        }
        $trail[] = array( 'label' => get_the_title( $post ), 'url' => null );
    } elseif ( is_author() ) {
        $trail[] = array( 'label' => get_the_author(), 'url' => null );
    } elseif ( is_search() ) {
        // translators: %s is the search query.
        $trail[] = array( 'label' => sprintf( __( 'Search results for “%s”', 'wbcr' ), get_search_query() ), 'url' => null );
    } elseif ( is_404() ) {
        $trail[] = array( 'label' => __( 'Page not found', 'wbcr' ), 'url' => null );
    } elseif ( is_post_type_archive() ) {
        $trail[] = array( 'label' => post_type_archive_title( '', false ), 'url' => null );
    }

    return apply_filters( 'wbcr_trail', $trail );
}

/** Render the trail as HTML + JSON-LD (BreadcrumbList schema). */
function wbcr_render() {
    $settings = wbcr_settings();
    $trail    = wbcr_get_trail();
    if ( count( $trail ) < 2 && is_front_page() ) {
        return ''; // Nothing useful to show on the home page alone.
    }
    if ( empty( $trail ) ) {
        return '';
    }

    $html  = '<nav class="wbcr-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'wbcr' ) . '"><ol>';
    $items = array();
    $position = 1;
    foreach ( $trail as $index => $crumb ) {
        $is_last = ( $index === count( $trail ) - 1 );
        $html   .= '<li' . ( $is_last ? ' aria-current="page"' : '' ) . '>';
        if ( ! $is_last && ! empty( $crumb['url'] ) ) {
            $html .= '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['label'] ) . '</a>';
        } else {
            $html .= '<span>' . esc_html( $crumb['label'] ) . '</span>';
        }
        $html .= '</li>';
        if ( ! $is_last ) {
            $html .= '<li class="wbcr-separator" aria-hidden="true">' . esc_html( $settings['separator'] ) . '</li>';
        }
        $items[] = array(
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => $crumb['label'],
            'item'     => ! empty( $crumb['url'] ) ? $crumb['url'] : get_permalink(),
        );
    }
    $html .= '</ol></nav>';

    $schema = array(
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    );
    $html .= '<script type="application/ld+json">'
        . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
        . '</script>';

    return $html;
}

/** Shortcode: [breadcrumbs]. */
add_shortcode( 'breadcrumbs', 'wbcr_render' );

/** Auto-insert at the top of single post/page content when enabled. */
function wbcr_auto_insert( $content ) {
    if ( is_singular() && in_the_loop() && is_main_query() && wbcr_settings()['auto_insert'] ) {
        return wbcr_render() . $content;
    }
    return $content;
}
add_filter( 'the_content', 'wbcr_auto_insert' );

/** Settings page: Settings → Breadcrumbs. */
function wbcr_register_settings() {
    register_setting( 'wbcr_settings_group', 'wbcr_settings', array(
        'sanitize_callback' => function ( $input ) {
            $clean = wbcr_defaults();
            if ( isset( $input['separator'] ) ) {
                $clean['separator'] = sanitize_text_field( $input['separator'] );
            }
            if ( isset( $input['home_label'] ) ) {
                $clean['home_label'] = sanitize_text_field( $input['home_label'] );
            }
            $clean['show_home']   = ! empty( $input['show_home'] ) ? 1 : 0;
            $clean['auto_insert'] = ! empty( $input['auto_insert'] ) ? 1 : 0;
            return $clean;
        },
    ) );
    add_options_page(
        __( 'Breadcrumbs', 'wbcr' ),
        __( 'Breadcrumbs', 'wbcr' ),
        'manage_options',
        'wbcr-settings',
        'wbcr_settings_page'
    );
}
add_action( 'admin_menu', 'wbcr_register_settings' );

function wbcr_settings_page() {
    $settings = wbcr_settings();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Breadcrumbs', 'wbcr' ); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'wbcr_settings_group' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="wbcr_separator"><?php esc_html_e( 'Separator', 'wbcr' ); ?></label></th>
                    <td><input type="text" id="wbcr_separator" name="wbcr_settings[separator]" value="<?php echo esc_attr( $settings['separator'] ); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wbcr_home_label"><?php esc_html_e( 'Home label', 'wbcr' ); ?></label></th>
                    <td><input type="text" id="wbcr_home_label" name="wbcr_settings[home_label]" value="<?php echo esc_attr( $settings['home_label'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Show home crumb', 'wbcr' ); ?></th>
                    <td><input type="checkbox" name="wbcr_settings[show_home]" value="1" <?php checked( $settings['show_home'], 1 ); ?>></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Auto-insert into content', 'wbcr' ); ?></th>
                    <td><input type="checkbox" name="wbcr_settings[auto_insert]" value="1" <?php checked( $settings['auto_insert'], 1 ); ?>>
                        <p class="description"><?php esc_html_e( 'Uncheck to place breadcrumbs manually with the [breadcrumbs] shortcode.', 'wbcr' ); ?></p></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
