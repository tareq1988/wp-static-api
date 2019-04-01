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
        $this->includes();
        $this->instantiate();
    }

    public function includes() {
        require_once __DIR__ . '/includes/cleanup.php';
        require_once __DIR__ . '/includes/relative-url.php';
        require_once __DIR__ . '/includes/rest-api.php';
    }

    public function instantiate() {
        new \WeDevs\Stapi\Cleanup();
        new \WeDevs\Stapi\Relative();
        new \WeDevs\Stapi\API();
    }
}

new WP_Static_API();
