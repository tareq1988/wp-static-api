<?php
/**
 * Plugin Name: WP Static Site API
 * Description: A static site API endpoint
 * Plugin URI: https://tareq.co
 * Author: Tareq Hasan
 * Author URI: https://tareq.co
 * Version: 1.0
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

/**
 * Static Class
 */
class WP_Static_API {

    function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'static/v1', '/pages', array(
            'methods' => 'GET',
            'callback' => [ $this, 'available_routes' ],
        ) );
    }

    public function available_routes( $data ) {
        $post_types = [
            'page', 'post'
        ];
        $data = [
            'count'  => [],
            'routes' => [],
        ];

        // accomodation, reviews, outlets, points of interest, special offers, events
        $posts = (new WP_Query([
            'post_type'      => $post_types,
            'post_status'    => ['publish', 'inherit'],
            'posts_per_page' => -1,
            'cache_results'  => true
        ]))->get_posts();

        if ( 'posts' == get_option('show_on_front') ) {
            $data['routes'][] = [
                'url' => home_url('/'),
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

                $data['count'][$post->post_type] += 1;
                $data['count']['total']          += 1;
            }
        }

        return rest_ensure_response( $data );
    }
}

new WP_Static_API();
