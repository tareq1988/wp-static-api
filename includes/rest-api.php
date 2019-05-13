<?php
namespace WeDevs\Stapi;

/**
 * API
 */
class API {

    /**
     * Constructor
     */
    function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register API routes
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route( 'static/v1', '/pages', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'available_routes' ],
            'permission_callback' => [ $this, 'permission_callback' ]
        ) );
    }

    /**
     * Check if this is an authenticated request
     *
     * @param  \WP_REST_Request $request
     *
     * @return boolean
     */
    public function permission_callback( $request ) {
        if ( ! defined( 'BEDIQ_SITE_KEY' ) ) {
            return false;
        }

        $site_key = $request->get_header('X-Site-Key');

        if ( ! $site_key && isset( $_REQUEST['bediq_site_key'] ) ) {
            $site_key = $_REQUEST['bediq_site_key'];
        }

        if ( BEDIQ_SITE_KEY == $site_key ) {
            return true;
        }

        return false;
    }

    /**
     * Get a post type count
     *
     * @param  string $post_type
     *
     * @return integer
     */
    public function get_post_count( $post_type ) {
        $count = wp_count_posts( $post_type );

        return (int) $count->publish;
    }

    /**
     * Get URL of the posts page
     *
     * @return string|false
     */
    public function get_posts_page() {
        if ( 'posts' == get_option('show_on_front') ) {
            return home_url( '/' );
        } else {
            $posts_page = get_option( 'page_for_posts' );

            if ( '0' == $posts_page ) {
                return false;
            }

            return get_permalink( $posts_page );
        }
    }

    /**
     * Get all public post types
     *
     * @return array
     */
    public function get_post_types() {
        $post_types = get_post_types( [ 'public' => true ], 'names' );
        $skipped    = [ 'attachment', 'elementor_library' ];

        foreach ( $skipped as $skip ) {
            if ( array_key_exists( $skip, $post_types ) ) {
                unset( $post_types[ $skip ] );
            }
        }

        return $post_types;
    }

    /**
     * Get available routes
     *
     * @param  [type] $data
     *
     * @return [type]
     */
    public function available_routes( $data ) {
        $post_types = $this->get_post_types();

        $data = [
            'count'  => [ 'total' => 0 ],
            'routes' => [],
        ];

        $posts = (new \WP_Query([
            'post_type'      => $post_types,
            'post_status'    => ['publish', 'inherit'],
            'posts_per_page' => -1,
            'cache_results'  => true
        ]))->get_posts();

        if ( 'posts' == get_option('show_on_front') ) {
            $data['routes'][] = [
                'url' => home_url( '/' ),
                'type' => 'homepage'
            ];
        }

        if ( $posts ) {
            foreach ( $posts as $post ) {
                $data['routes'][] = [
                    'id'      => $post->ID,
                    'url'     => get_permalink( $post ),
                    'type'    => $post->post_type,
                    'created' => $post->post_date,
                    'updated' => $post->post_modified
                ];

                if ( ! isset( $data['count'][$post->post_type] ) ) {
                    $data['count'][$post->post_type] = 0;
                }

                $data['count'][$post->post_type] += 1;
                $data['count']['total']          += 1;
            }
        }

        if ( $pagination = $this->get_posts_pagination() ) {
            $data['count']['pagination'] = count( $pagination );
            $data['count']['total']      += $data['count']['pagination'];
            $data['routes' ]             = array_merge( $data['routes'], $pagination );
        }

        if ( $archive = $this->get_posttype_archives() ) {
            $data['count']['archive'] = count( $archive );
            $data['count']['total']   += $data['count']['archive'];
            $data['routes']           = array_merge( $data['routes'], $archive );
        }

        if ( $taxonomies = $this->get_taxonomy_terms() ) {
            $data['count']['taxonomy'] = count( $taxonomies );
            $data['count']['total']    += $data['count']['taxonomy'];
            $data['routes']            = array_merge( $data['routes'], $taxonomies );
        }

        return rest_ensure_response( $data );
    }

    /**
     * Get the posts pagination
     *
     * @param  string $post_type
     *
     * @return array|false
     */
    public function get_posts_pagination( $post_type = 'post' ) {
        $posts_page = $this->get_posts_page();

        if ( ! $posts_page ) {
            return false;
        }

        $per_page  = get_option( 'posts_per_page', 10 );
        $total     = $this->get_post_count( $post_type );
        $max_pages = 1;

        if ( $total > $per_page ) {
            $max_pages = (int) ceil( $total / $per_page );
        }

        if ( $max_pages == 1 ) {
            return false;
        }

        $pages = [];
        $base  = $posts_page . 'page/';

        for ( $i = 2; $i <= $max_pages; $i++) {
            $pages[] = [
                'url' => $base . $i . '/',
                'type' => 'pagination'
            ];
        }

        return $pages;
    }

    /**
     * Get monthly post type archive links
     *
     * @param  string $post_type
     *
     * @return array
     */
    public function get_posttype_archives( $post_type = 'post' ) {
        global $wpdb;

        // monthly archive
        $where        = $wpdb->prepare( "WHERE post_type = %s AND post_status = 'publish'", $post_type );
        $query        = "SELECT YEAR(post_date) AS `year`, MONTH(post_date) AS `month`, count(ID) as posts FROM $wpdb->posts $where GROUP BY YEAR(post_date), MONTH(post_date) ORDER BY post_date ASC";
        $key          = md5( $query );
        $last_changed = wp_cache_get_last_changed( 'posts' );
        $key          = "wp_get_archives:$key:$last_changed";
        $links        = [];

        if ( ! $results = wp_cache_get( $key, 'posts' ) ) {
            $results = $wpdb->get_results( $query );
            wp_cache_set( $key, $results, 'posts' );
        }

        if ( $results ) {
            foreach ( $results as $result ) {
                $links[] = [
                    'url'  => get_month_link( $result->year, $result->month ),
                    'type' => 'archive'
                ];
            }
        }

        return $links;
    }

    /**
     * Get all taxonomy terms
     *
     * @return array
     */
    public function get_taxonomy_terms() {
        $taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
        $links      = [];

        foreach ( $taxonomies as $taxonomy ) {
            $taxonomy_terms = get_terms( array(
                'hide_empty' => true,
                'taxonomy' => $taxonomy->name
            ) );

            if ( $taxonomy_terms ) {
                foreach ( $taxonomy_terms as $term ) {
                    if ( $term->count ) {
                        $links[] = [
                            'url' => get_term_link( $term ),
                            'type' => 'taxonomy'
                        ];
                    }
                }
            }
        }

        return $links;
    }
}
