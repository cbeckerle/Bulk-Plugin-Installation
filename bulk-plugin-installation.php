<?php
/*
	Plugin Name: Bulk Plugin Installation
	Description: Allows you to install one or more plugins simply by typing their names or download URLs in a textarea.
	Version: 1.1
	Author: Bee Estudio Web
	Author URI: http://www.estudiobee.com
 */

if ( ! defined( 'ABSPATH' ) )
		exit; // Exit if accessed directly

if ( ! class_exists( 'BulkPluginInstallation' ) ) {
		
	load_plugin_textdomain( 'bulk-plugin-installation', false, basename( dirname( __FILE__ ) ) . '/languages' );

	class BulkPluginInstallation {

		/**
		 * Plugin version
		 * 
		 * @var string 
		 */
		var $version = '1.1';

		/**
		 * BulkPluginInstallation constructor.
		 *
		 * @access public
		 * @return void
		 */
		function __construct() {
			add_filter( 'install_plugins_nonmenu_tabs', array( &$this, 'extra_tabs' ) );
			add_action( 'install_plugins_dashboard', array( &$this, 'install_plugins_dashboard' ) );
			add_action( 'install_plugins_bpi', array( &$this, 'bpi' ) );
		}

		/**
		 * Add tab.
		 * 
		 * @param array $tabs
		 * @return string
		 */
		function extra_tabs( $tabs ) {
			$tabs[] = 'bpi';

			return $tabs;
		}

		/**
		 * Form.
		 *
		 */
		function install_plugins_dashboard() {
			?>
			<h4><?php _e( 'Install plugins from URL/name', 'bulk-plugin-installation' ); ?></h4>
			<p><?php _e( 'Type the plugin names, the WordPress plugin page URLs, or the direct URLs to the zip files, one on each line.', 'bulk-plugin-installation' ); ?></p>
			<form method="post" action="<?php echo admin_url( 'plugin-install.php?tab=bpi' ); ?>">
				<?php wp_nonce_field( 'plugin-bpi' ) ?>
				<textarea name="pluginurls" rows="7" cols="70"></textarea><br />
				<input type="submit" class="button" value="<?php _e( 'Install now', 'bulk-plugin-installation' ); ?>" />
			</form>
			<br />
			<?php
		}

		/**
		 * Process the form POST.
		 * 
		 */
		function bpi() {
			check_admin_referer( 'plugin-bpi' );

			if ( ! is_user_logged_in() ) {
				wp_die( __( 'You are not logged in.', 'bulk-plugin-installation' ) );
			} else if ( ! current_user_can( 'install_plugins' ) ) {
				wp_die( __( 'You do not have the necessary administrative rights to be able to install plugins.', 'bulk-plugin-installation' ) );
			}

			if ( ! empty( $_REQUEST['pluginurls'] ) ) {
				if ( is_array( $_REQUEST['pluginurls'] ) ) {
					$urls = $_REQUEST['pluginurls'];
				} else {
					$urls = explode( "\n", $_REQUEST['pluginurls'] );
				}
			} else if ( ! empty( $_REQUEST['pluginurls'] ) ) {
				$urls = array( $_REQUEST['pluginurls'] );
			} else {
				wp_die( __( 'No data supplied.', 'bulk-plugin-installation' ) );
			}

			$urls = array_unique( $urls );

			$correct = $errors = 0;

			if ( get_filesystem_method() != 'direct' ) {
				global $wp_filesystem;

				$credentials_url = 'plugin-install.php?tab=bpi&';

				foreach ( $urls as $url ) {
					$credentials_url .= '&pluginurls[]=' . urlencode( $url );
				}

				$credentials_url = wp_nonce_url( $credentials_url, 'plugin-bpi' );

				if ( false === ( $credentials = request_filesystem_credentials( $credentials_url ) ) )
					return;

				if ( ! WP_Filesystem( $credentials ) ) {
					request_filesystem_credentials( $credentials_url, '', true );
					return;
				}

				if ( $wp_filesystem->errors->get_error_code() ) {
					foreach ( $wp_filesystem->errors->get_error_messages() as $message )
						show_message( $message );
					return;
				}
			}

			foreach ( $urls as $url ) {
				if ( ! $url = trim( stripslashes( trim( $url, "\r" ) ) ) ) {
					continue;
				}

				if ( ! preg_match( '/http:\/\//i', $url, $match ) ) {
						$plugin_name = $url;
				} else if ( preg_match( '/downloads\.wordpress\.org\/plugin\/([^\.]+)(.*)\.zip/i', $url, $match ) || preg_match( '/wordpress\.org\/extend\/plugins\/([^\/]*)\/?/i', $url, $match ) ) {
						$plugin_name = stripslashes( $match[1] );
				} else {
						$plugin_name = false;
				}

				if ( $plugin_name ) {
					$plugin = $this->get_plugin_information( $plugin_name );

					if ( is_wp_error( $plugin ) ) {
						$errors++;

						$code = $plugin->get_error_code();
						$message = $plugin->get_error_message();

						if ( count( $urls ) == 1 ) {
							if ( $code == 'plugins_api_failed' ) {
								echo '<p>' . __( 'Couldn\'t install plugin, perhaps you misspelled the name?', 'bulk-plugin-installation' ) . '</p>';
							} else {
								echo '<p>' . $message . '</p>';
							}
						} else {
							echo '<div class="wrap">';
							echo '<h2>' . sprintf( __( 'Installing plugin: %s', 'bulk-plugin-installation' ), esc_attr( $url ) ) . '</h2>';

							if ( $code == 'plugins_api_failed' ) {
								echo '<p>' . __( 'Couldn\'t install plugin, perhaps you misspelled the name?', 'bulk-plugin-installation' ) . '</p>';
							} else {
								echo '<p>' . $message . '</p>';
							}
							echo '</div>';
						}
					} else {
						$correct++;

						$_REQUEST['plugin_name'] = $plugin->name;
						$_REQUEST['download_url'] = $plugin->download_link;

						echo '<div class="wrap">';
						echo '<h2>', sprintf( __( 'Installing plugin: %s', 'bulk-plugin-installation'), $plugin->name . ' ' . $plugin->version ), '</h2>';

						$this->do_plugin_install( $plugin->download_link, $plugin );
						echo '</div>';
					}
				} else {
					$correct++;

					echo '<div class="wrap">';
					echo '<h2>' . sprintf( __( 'Installing plugin: %s', 'bulk-plugin-installation' ), esc_attr( $url ) ) . '</h2>';

					$this->do_external_plugin_install( $url );
					echo '</div>';
				}
			}

			if ( ! $correct && ! $errors ) {
					echo '<p>' . __( 'No valid data supplied.', 'bulk-plugin-installation' ) . '</p>';
			}
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
		 * @param mixed $api
		 */
		function do_plugin_install( $download_url, $api ) {
			if ( function_exists( 'do_plugin_install' ) ) {
				do_plugin_install( $download_url, $api );
			} else {
				include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

				$upgrader = new Plugin_Upgrader( new Plugin_Installer_Skin( compact( 'title', 'nonce', 'url', 'plugin' ) ) );
				$success = $upgrader->install( $download_url );
			}
		}

		/**
		 * External plugin install.
		 * 
		 * @global type $wp_filesystem
		 * @param string $download_url
		 */
		function do_external_plugin_install( $download_url ) {
			global $wp_filesystem;

			if ( empty( $download_url ) ) {
				show_message( __( 'No plugin specified', 'bulk-plugin-installation' ) );
				return;
			}

			if ( function_exists( 'wp_install_plugin' ) ) {
				$result = wp_install_plugin( $download_url, 'show_message' );
			} else {
				include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

				$upgrader = new Plugin_Upgrader( new Plugin_Installer_Skin( compact( 'title', 'nonce', 'url', 'plugin' ) ) );
				$upgrader->install( $download_url );
			}

			if ( is_wp_error( $result ) ) {
				show_message( $result );
				show_message( __( 'Installation failed', 'bulk-plugin-installation' ) );
			} else {
				show_message( sprintf( __( 'Successfully installed the plugin <strong>%s </strong>.', 'bulk-plugin-installation' ), $download_url ) );
			}
		}

	}

	/**
	 * Init BulkPluginInstallation class
	 */
	$GLOBALS['BulkPluginInstallation'] = new BulkPluginInstallation();
}
?>