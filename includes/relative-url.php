<?php
namespace WeDevs\Stapi;

/**
 * Relative Url
 */
class Relative {

    function __construct() {
        add_action( 'template_redirect', [ $this, 'apply_relative_url'], 20 );
    }

    function apply_relative_url() {
        $relative_filters = [
            'bloginfo_url',
            'site_url',
            'home_url',
            'the_permalink',
            'get_the_permalink',
            'wp_list_pages',
            'wp_list_categories',
            'wp_get_attachment_url',
            'the_content_more_link',
            'the_tags',
            'get_pagenum_link',
            'get_comment_link',
            'month_link',
            'day_link',
            'year_link',
            'term_link',
            'the_author_posts_link',
            'script_loader_src',
            'style_loader_src',
            'theme_file_uri',
            'parent_theme_file_uri',
        ];

        foreach ( $relative_filters as $filter ) {
            add_filter( $filter, [ $this, 'make_relative_url' ], 20 );
        }
    }

    function make_relative_url( $input ) {
        if ( is_feed() ) {
            return $input;
        }

        $url = parse_url( $input );
        if (!isset( $url['host'] ) || !isset( $url['path'] ) ) {
            return $input;
        }

        // fixes undefined "host" while parse_url() in wp_customize_support_script()
        if ( is_user_logged_in() && 'site_url' == current_filter() ) {
            return $input;
        }

        $site_url = parse_url(network_home_url());  // falls back to home_url

        if ( ! isset( $url['scheme'] ) ) {
            $url['scheme'] = $site_url['scheme'];
        }

        $hosts_match   = $site_url['host'] === $url['host'];
        $schemes_match = $site_url['scheme'] === $url['scheme'];
        $ports_exist   = isset($site_url['port']) && isset($url['port']);
        $ports_match   = ($ports_exist) ? $site_url['port'] === $url['port'] : true;

        if ( $hosts_match && $schemes_match && $ports_match ) {
            return wp_make_link_relative($input);
        }

        return $input;
    }
}
