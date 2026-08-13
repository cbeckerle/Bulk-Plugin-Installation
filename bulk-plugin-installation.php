<?php
/*
	Plugin Name: Bulk Plugin Installation
	Description: Allows you to install one or more plugins simply by typing their names or download URLs in a textarea.
	Version: 2.0.1
	Author: Bee Web Hosting
	Author URI: https://www.beewh.com
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
			// Cargar traducciones
			add_action( 'init', array( $this, 'load_textdomain' ) );
			// Crear un submenú en lugar de los tabs obsoletos
			add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
		}

		function load_textdomain() {
			load_plugin_textdomain( 'bulk-plugin-installation', false, basename( dirname( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Add submenu page under Plugins.
		 */
		function add_plugin_menu() {
			add_plugins_page(
				__( 'Bulk Plugin Installation', 'bulk-plugin-installation' ),
				__( 'Bulk Install', 'bulk-plugin-installation' ),
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
			
			if ( isset( $_POST['pluginurls'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'plugin-bpi' ) ) {
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
			<h2><?php _e( 'Install plugins from URL/name', 'bulk-plugin-installation' ); ?></h2>
			<p><?php _e( 'Type the plugin names, the WordPress plugin page URLs, or the direct URLs to the zip files, one on each line.', 'bulk-plugin-installation' ); ?></p>
			<form method="post" action="">
				<?php wp_nonce_field( 'plugin-bpi' ) ?>
				<textarea name="pluginurls" rows="10" cols="70" class="large-text code"></textarea><br /><br />
				<input type="submit" class="button button-primary" value="<?php _e( 'Install now', 'bulk-plugin-installation' ); ?>" />
			</form>
			<br />
			<?php
		}

		/**
		 * Process the form POST.
		 */
		function bpi() {
			if ( ! is_user_logged_in() ) {
				wp_die( __( 'You are not logged in.', 'bulk-plugin-installation' ) );
			} else if ( ! current_user_can( 'install_plugins' ) ) {
				wp_die( __( 'You do not have the necessary administrative rights to be able to install plugins.', 'bulk-plugin-installation' ) );
			}

			if ( ! empty( $_REQUEST['pluginurls'] ) ) {
				if ( is_array( $_REQUEST['pluginurls'] ) ) {
					$urls = $_REQUEST['pluginurls'];
				} else {
					$urls = explode( "\n", sanitize_textarea_field( $_REQUEST['pluginurls'] ) );
				}
			} else {
				wp_die( __( 'No data supplied.', 'bulk-plugin-installation' ) );
			}

			$urls = array_unique( array_filter( array_map( 'trim', $urls ) ) );
			$correct = $errors = 0;

			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			foreach ( $urls as $url ) {
				if ( ! preg_match( '/https?:\/\//i', $url, $match ) ) {
						// Es un slug o nombre simple (ej: BellePopups)
						$plugin_name = $url;
				} else if ( preg_match( '/downloads\.wordpress\.org\/plugin\/([^\.]+)/i', $url, $match ) ) {
						// Es un ZIP del repositorio. Extrae el nombre ignorando la versión
						// Ej: performance-lab de performance-lab.4.2.0.zip
						$plugin_name = stripslashes( $match[1] );
				} else if ( preg_match( '/wordpress\.org\/(extend\/)?plugins\/([^\/]+)/i', $url, $match ) ) {
						// Es la URL de la página del plugin en WP.org
						$plugin_name = stripslashes( $match[2] ); 
				} else {
						// Es un ZIP externo o enlace directo
						$plugin_name = false;
				}

				if ( $plugin_name ) {
					$plugin = $this->get_plugin_information( $plugin_name );

					if ( is_wp_error( $plugin ) ) {
						$errors++;
						$code = $plugin->get_error_code();
						$message = $plugin->get_error_message();

						echo '<h2>' . sprintf( __( 'Installing plugin: %s', 'bulk-plugin-installation' ), esc_attr( $url ) ) . '</h2>';

						if ( $code == 'plugins_api_failed' ) {
							echo '<p style="color:red;">' . __( 'Couldn\'t install plugin, perhaps you misspelled the name?', 'bulk-plugin-installation' ) . '</p>';
						} else {
							echo '<p style="color:red;">' . $message . '</p>';
						}
					} else {
						$correct++;
						echo '<h2>', sprintf( __( 'Installing plugin: %s', 'bulk-plugin-installation'), $plugin->name . ' ' . $plugin->version ), '</h2>';
						$this->do_plugin_install( $plugin->download_link );
					}
				} else {
					$correct++;
					echo '<h2>' . sprintf( __( 'Installing plugin: %s', 'bulk-plugin-installation' ), esc_attr( $url ) ) . '</h2>';
					$this->do_external_plugin_install( $url );
				}
			}

			if ( ! $correct && ! $errors ) {
					echo '<p>' . __( 'No valid data supplied.', 'bulk-plugin-installation' ) . '</p>';
			}
			
			echo '<p><a href="' . admin_url('plugins.php?page=bulk-plugin-installation') . '" class="button">' . __('&laquo; Return to Bulk Installer', 'bulk-plugin-installation') . '</a></p>';
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
				echo '<p>' . __( 'No plugin specified', 'bulk-plugin-installation' ) . '</p>';
				return;
			}

			$upgrader = new Plugin_Upgrader( new Plugin_Installer_Skin( array( 'url' => '', 'plugin' => '', 'nonce' => '', 'title' => '' ) ) );
			$result = $upgrader->install( $download_url );

			if ( is_wp_error( $result ) ) {
				echo '<p style="color:red;">' . __( 'Installation failed', 'bulk-plugin-installation' ) . '</p>';
			} else {
				echo '<p style="color:green;">' . sprintf( __( 'Successfully installed the plugin <strong>%s </strong>.', 'bulk-plugin-installation' ), $download_url ) . '</p>';
			}
		}

	}

	/**
	 * Init BulkPluginInstallation class
	 */
	$GLOBALS['BulkPluginInstallation'] = new BulkPluginInstallation();
}
?>