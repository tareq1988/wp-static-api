<?php
namespace WeDevs\Stapi;

/**
 * API
 */
class API {

    private $count;

    function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'static/v1', '/pages', array(
            'methods' => 'GET',
            'callback' => [ $this, 'available_routes' ],
        ) );
    }

    /**
     * Get post count
     *
     * @return array
     */
    public function get_posts_count() {
        if ( ! $count ) {
            $this->count = wp_count_posts();
        }

        return $this->count;
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
     * Get available routes
     *
     * @param  [type] $data
     *
     * @return [type]
     */
    public function available_routes( $data ) {
        $post_types = get_post_types( [ 'public' => true ], 'names' );

        unset( $post_types['attachment'] );

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
            $data['routes' ] = array_merge( $data['routes'], $pagination );
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
}
