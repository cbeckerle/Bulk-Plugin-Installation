<?php
/*
	Plugin Name: Bulk Plugin Installation
	Description: Allows you to install one or more plugins simply by typing their names or download URLs in a textarea.
	Version: 2.0.1
	Author: Bee Web Hosting
	Author URI: https://www.beewh.com
	Text Domain: bulk-plugin-installation
	License: GPLv2 or later
	License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'BulkPluginInstallation' ) ) {
		
	class BulkPluginInstallation {

		/**
		 * Plugin version
		 * 
		 * @var string 
		 */
		var $version = '2.0.1';

		/**
		 * BulkPluginInstallation constructor.
		 *
		 * @access public
		 * @return void
		 */
		function __construct() {
			// Crear un submenú
			add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
		}

		/**
		 * Add submenu page under Plugins.
		 */
		function add_plugin_menu() {
			add_plugins_page(
				esc_html__( 'Bulk Plugin Installation', 'bulk-plugin-installation' ),
				esc_html__( 'Bulk Install', 'bulk-plugin-installation' ),
				'install_plugins',
				'bulk-plugin-installation',
				array( $this, 'render_admin_page' )
			);
		}

		/**
		 * Form and processing rendering.
		 */
		function render_admin_page() {
			echo '<div class="wrap">';
			
			if ( isset( $_POST['pluginurls'] ) ) {
				$this->bpi();
			} else {
				$this->install_plugins_dashboard();
			}

			echo '</div>';
		}

		/**
		 * Form.
		 */
		function install_plugins_dashboard() {
			?>
			<h2><?php esc_html_e( 'Install plugins from URL/name', 'bulk-plugin-installation' ); ?></h2>
			<p><?php esc_html_e( 'Type the plugin names, the WordPress plugin page URLs, or the direct URLs to the zip files, one on each line.', 'bulk-plugin-installation' ); ?></p>
			<form method="post" action="">
				<?php wp_nonce_field( 'plugin-bpi' ) ?>
				<textarea name="pluginurls" rows="10" cols="70" class="large-text code"></textarea><br /><br />
				<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Install now', 'bulk-plugin-installation' ); ?>" />
			</form>
			<br />
			<?php
		}

		/**
		 * Process the form POST.
		 */
		function bpi() {
			if ( ! is_user_logged_in() ) {
				wp_die( esc_html__( 'You are not logged in.', 'bulk-plugin-installation' ) );
			} else if ( ! current_user_can( 'install_plugins' ) ) {
				wp_die( esc_html__( 'You do not have the necessary administrative rights to be able to install plugins.', 'bulk-plugin-installation' ) );
			}

			// Nonce verification
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'plugin-bpi' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'bulk-plugin-installation' ) );
			}

			if ( ! empty( $_POST['pluginurls'] ) ) {
				$raw_urls = sanitize_textarea_field( wp_unslash( $_POST['pluginurls'] ) );
				$urls = explode( "\n", $raw_urls );
			} else {
				wp_die( esc_html__( 'No data supplied.', 'bulk-plugin-installation' ) );
			}

			$urls = array_unique( array_filter( array_map( 'trim', $urls ) ) );
			$correct = $errors = 0;

			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			foreach ( $urls as $url ) {
				if ( ! preg_match( '/https?:\/\//i', $url, $match ) ) {
						$plugin_name = $url;
				} else if ( preg_match( '/downloads\.wordpress\.org\/plugin\/([^\.]+)/i', $url, $match ) ) {
						$plugin_name = stripslashes( $match[1] );
				} else if ( preg_match( '/wordpress\.org\/(extend\/)?plugins\/([^\/]+)/i', $url, $match ) ) {
						$plugin_name = stripslashes( $match[2] ); 
				} else {
						$plugin_name = false;
				}

				if ( $plugin_name ) {
					$plugin = $this->get_plugin_information( $plugin_name );

					if ( is_wp_error( $plugin ) ) {
						$errors++;
						$code = $plugin->get_error_code();
						$message = $plugin->get_error_message();

						/* translators: %s: Plugin URL or name */
						echo '<h2>' . esc_html( sprintf( __( 'Installing plugin: %s', 'bulk-plugin-installation' ), $url ) ) . '</h2>';

						if ( $code == 'plugins_api_failed' ) {
							echo '<p style="color:red;">' . esc_html__( 'Couldn\'t install plugin, perhaps you misspelled the name?', 'bulk-plugin-installation' ) . '</p>';
						} else {
							echo '<p style="color:red;">' . esc_html( $message ) . '</p>';
						}
					} else {
						$correct++;
						/* translators: %s: Plugin name and version */
						echo '<h2>' . esc_html( sprintf( __( 'Installing plugin: %s', 'bulk-plugin-installation'), $plugin->name . ' ' . $plugin->version ) ) . '</h2>';
						$this->do_plugin_install( $plugin->download_link );
					}
				} else {
					$correct++;
					/* translators: %s: Plugin URL */
					echo '<h2>' . esc_html( sprintf( __( 'Installing plugin: %s', 'bulk-plugin-installation' ), $url ) ) . '</h2>';
					$this->do_external_plugin_install( $url );
				}
			}

			if ( ! $correct && ! $errors ) {
					echo '<p>' . esc_html__( 'No valid data supplied.', 'bulk-plugin-installation' ) . '</p>';
			}
			
			echo '<p><a href="' . esc_url( admin_url('plugins.php?page=bulk-plugin-installation') ) . '" class="button">' . esc_html__('&laquo; Return to Bulk Installer', 'bulk-plugin-installation') . '</a></p>';
		}

		/**
		 * Get plugin information.
		 * 
		 * @param string $plugin
		 * @return mixed
		 */
		function get_plugin_information( $plugin ) {
			$plugin = strtolower( trim( preg_replace( "/\s+/", ' ', $plugin ) ) );

			$api = plugins_api( 'plugin_information', array( 'slug' => $plugin, 'fields' => array( 'sections' => false, 'description' => false ) ) );

			if ( is_wp_error( $api ) ) {
				$api = plugins_api( 'query_plugins', array( 'search' => $plugin, 'per_page' => 1, 'fields' => array( 'sections' => false, 'description' => false ) ) );

				if ( ! is_wp_error( $api ) ) {
					if ( ! empty( $api->plugins[0] ) ) {
						$api = $api->plugins[0];

						if ( preg_match( '/^' . preg_quote( trim( $plugin ), '/' ) . '/i', trim( $api->name ) ) ) {
							$plugin = $api->slug;
							$api = plugins_api( 'plugin_information', array( 'slug' => $plugin, 'fields' => array( 'sections' => false, 'description' => false ) ) );
						} else {
							$api = new WP_Error( 'plugins_api_failed' );
						}
					} else {
						$api = new WP_Error( 'plugins_api_failed' );
					}
				}
			}

			return $api;
		}

		/**
		 * Plugin install.
		 * 
		 * @param string $download_url
		 */
		function do_plugin_install( $download_url ) {
			$upgrader = new Plugin_Upgrader( new Plugin_Installer_Skin( array( 'url' => '', 'plugin' => '', 'nonce' => '', 'title' => '' ) ) );
			$upgrader->install( $download_url );
		}

		/**
		 * External plugin install.
		 * 
		 * @param string $download_url
		 */
		function do_external_plugin_install( $download_url ) {
			if ( empty( $download_url ) ) {
				echo '<p>' . esc_html__( 'No plugin specified', 'bulk-plugin-installation' ) . '</p>';
				return;
			}

			$upgrader = new Plugin_Upgrader( new Plugin_Installer_Skin( array( 'url' => '', 'plugin' => '', 'nonce' => '', 'title' => '' ) ) );
			$result = $upgrader->install( $download_url );

			if ( is_wp_error( $result ) ) {
				echo '<p style="color:red;">' . esc_html__( 'Installation failed', 'bulk-plugin-installation' ) . '</p>';
			} else {
				/* translators: %s: Plugin download URL */
				echo '<p style="color:green;">' . wp_kses_post( sprintf( __( 'Successfully installed the plugin <strong>%s</strong>.', 'bulk-plugin-installation' ), esc_url( $download_url ) ) ) . '</p>';
			}
		}

	}

	/**
	 * Init BulkPluginInstallation class
	 */
	$GLOBALS['BulkPluginInstallation'] = new BulkPluginInstallation();
}
?>