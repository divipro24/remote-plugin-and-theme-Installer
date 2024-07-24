<?php
/*
Plugin Name: Remote Plugin and Theme Installer
Description: Install plugins and themes from a remote server.
Author URI: https://divipro24.com
Plugin URI: https://divipro24.com
Version: 1.0.0
Author: Dmitri Andrejev
Github URI: https://github.com/divipro24/remote-plugin-and-theme-Installer
License: GPLv2
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Remote_Installer {
    private $default_remote_url = 'https://example.com/my_wp_plugins/';
    private $plugins_dir = 'plugins/';
    private $themes_dir = 'themes/';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'create_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_fetch_files', array( $this, 'ajax_fetch_files' ) );
        add_action( 'wp_ajax_install_file', array( $this, 'ajax_install_file' ) );
    }

    public function create_menu() {
        add_menu_page(
            'Remote Installer',
            'Remote Installer',
            'manage_options',
            'remote-installer',
            array( $this, 'admin_page' ),
            'dashicons-admin-generic',
            90
        );
    }

    public function register_settings() {
        register_setting( 'remote_installer_settings', 'remote_installer_url' );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook != 'toplevel_page_remote-installer' ) {
            return;
        }
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'remote-installer-js', plugin_dir_url( __FILE__ ) . 'remote-installer.js', array( 'jquery' ), '1.0', true );
        wp_localize_script( 'remote-installer-js', 'remoteInstaller', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'fetch_files_nonce' => wp_create_nonce( 'fetch_files_nonce' ),
            'install_file_nonce' => wp_create_nonce( 'install_file_nonce' ),
        ));
    }

    public function admin_page() {
        $remote_url = get_option( 'remote_installer_url', $this->default_remote_url );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Remote Plugin and Theme Installer', 'remote-installer' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'remote_installer_settings' ); ?>
                <?php do_settings_sections( 'remote_installer_settings' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Remote URL', 'remote-installer' ); ?></th>
                        <td><input type="text" name="remote_installer_url" value="<?php echo esc_attr( $remote_url ); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <button id="fetch-plugins"><?php esc_html_e( 'Отобразить плагины и Темы', 'remote-installer' ); ?></button>
            <div id="plugins-list"></div>
            <div id="themes-list"></div>
        </div>
        <?php
    }

    private function fetch_remote_files( $type ) {
        $remote_url = get_option( 'remote_installer_url', $this->default_remote_url );
        $url = $remote_url . ( $type === 'plugin' ? $this->plugins_dir : $this->themes_dir );
        $response = wp_remote_get( $url );
        if ( is_wp_error( $response ) ) {
            return array();
        }
        $body = wp_remote_retrieve_body( $response );

        $doc = new DOMDocument();
        @$doc->loadHTML($body);
        $xpath = new DOMXPath($doc);

        $zip_files = [];
        foreach ($xpath->query('//a') as $node) {
            $href = $node->getAttribute('href');
            if (pathinfo($href, PATHINFO_EXTENSION) === 'zip') {
                $zip_files[] = $url . $href;
            }
        }

        return $zip_files;
    }

    public function ajax_fetch_files() {
        check_ajax_referer( 'fetch_files_nonce', 'nonce' );

        $plugins = $this->fetch_remote_files( 'plugin' );
        $themes = $this->fetch_remote_files( 'theme' );

        wp_send_json_success( array( 'plugins' => $plugins, 'themes' => $themes ) );
    }

    public function install_file( $file_url, $type ) {
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/misc.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';

        if ($type === 'plugin') {
            $upgrader = new Plugin_Upgrader( new WP_Upgrader_Skin() );
        } else {
            $upgrader = new Theme_Upgrader( new WP_Upgrader_Skin() );
        }

        $download = download_url( $file_url );

        if ( is_wp_error( $download ) ) {
            return false;
        }

        $install_result = $upgrader->install( $download );

        @unlink( $download );

        return $install_result;
    }

    public function ajax_install_file() {
        check_ajax_referer( 'install_file_nonce', 'nonce' );

        $file_url = esc_url_raw( $_POST['file_url'] );
        $type = sanitize_text_field( $_POST['type'] );

        $result = $this->install_file( $file_url, $type );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        } else {
            wp_send_json_success( 'File installed successfully.' );
        }
    }
}

new Remote_Installer();

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/divipro24/remote-plugin-and-theme-Installer',
    __FILE__,
    'remote-plugin-and-theme-Installer'
);

$myUpdateChecker->setBranch('main');
?>
