<?php

namespace WP_TTS\Core;

/**
 * Plugin Activator
 *
 * Handles plugin activation tasks including database setup,
 * default configuration, and initial setup.
 */
class Activator {

	/**
	 * Activate the plugin
	 */
	public static function activate(): void {
		// Check WordPress and PHP version requirements
		self::checkRequirements();

		// Create necessary directories
		self::createDirectories();

		// Set up default configuration
		self::setupDefaultConfiguration();

		// Schedule cron jobs
		self::scheduleCronJobs();

		// Set activation flag
		update_option( 'wp_tts_plugin_activated', true );
		update_option( 'wp_tts_plugin_version', WP_TTS_PLUGIN_VERSION );
		update_option( 'wp_tts_plugin_activation_time', current_time( 'mysql' ) );

		// Flush rewrite rules
		flush_rewrite_rules();

		// Log activation
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'WP TTS Plugin activated successfully' );
	}

	/**
	 * Check system requirements
	 *
	 * @throws \Exception If requirements are not met
	 */
	private static function checkRequirements(): void {
		global $wp_version;

		// Check WordPress version
		if ( version_compare( $wp_version, '5.0', '<' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is safe
			throw new \Exception(
				esc_html__( 'El Plugin TTS de WordPress requiere WordPress 5.0 o superior.', 'tts-sesolibre' )
			);
		}

		// Check PHP version
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is safe
			throw new \Exception(
				esc_html__( 'El Plugin TTS de WordPress requiere PHP 7.4 o superior.', 'tts-sesolibre' )
			);
		}

		// Check required PHP extensions
		$required_extensions = array( 'curl', 'json', 'openssl' );
		foreach ( $required_extensions as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				throw new \Exception(
					esc_html( sprintf(
						/* translators: %s: PHP extension name */
						__( 'El Plugin TTS de WordPress requiere la extensión %s de PHP.', 'tts-sesolibre' ),
						$extension
					) )
				);
			}
		}

		// Check file permissions
		$upload_dir = wp_upload_dir();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Need to check write permissions during activation
		if ( ! is_writable( $upload_dir['basedir'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is safe
			throw new \Exception(
				esc_html__( 'El Plugin TTS de WordPress requiere permisos de escritura en el directorio de subidas.', 'tts-sesolibre' )
			);
		}
	}

	/**
	 * Create necessary directories
	 */
	private static function createDirectories(): void {
		$upload_dir = wp_upload_dir();
		$tts_dir    = $upload_dir['basedir'] . '/tts-audio';

		// Create TTS audio directory
		if ( ! file_exists( $tts_dir ) ) {
			wp_mkdir_p( $tts_dir );

			// Create .htaccess file for security
			$htaccess_content  = "# Protect TTS audio files\n";
			$htaccess_content .= "<Files *.php>\n";
			$htaccess_content .= "Order allow,deny\n";
			$htaccess_content .= "Deny from all\n";
			$htaccess_content .= "</Files>\n";

			file_put_contents( $tts_dir . '/.htaccess', $htaccess_content );
		}

		// Create subdirectories
		$subdirs = array( 'temp', 'cache', 'library' );
		foreach ( $subdirs as $subdir ) {
			$dir_path = $tts_dir . '/' . $subdir;
			if ( ! file_exists( $dir_path ) ) {
				wp_mkdir_p( $dir_path );
			}
		}

		// Create index.php files to prevent directory listing
		$index_content   = "<?php\n// Silence is golden.\n";
		$dirs_to_protect = array( $tts_dir, $tts_dir . '/temp', $tts_dir . '/cache', $tts_dir . '/library' );

		foreach ( $dirs_to_protect as $dir ) {
			$index_file = $dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, $index_content );
			}
		}
	}

	/**
	 * Set up default configuration
	 */
	private static function setupDefaultConfiguration(): void {
		$config_manager = new ConfigurationManager();

		// Only set defaults if this is a fresh installation
		if ( ! get_option( 'wp_tts_providers_config' ) ) {
			// Configuration will be set with defaults automatically
			$config_manager->save();
		}

		// Set up default audio library
		self::setupDefaultAudioLibrary();

		// Create default post meta for existing posts if needed
		self::setupExistingPosts();
	}

	/**
	 * Set up default audio library
	 */
	private static function setupDefaultAudioLibrary(): void {
		$upload_dir  = wp_upload_dir();
		$library_dir = $upload_dir['basedir'] . '/tts-audio/library';

		// Copy default audio files if they exist in the plugin
		$plugin_audio_dir = WP_TTS_PLUGIN_DIR . 'assets/audio';

		if ( is_dir( $plugin_audio_dir ) ) {
			$audio_types = array( 'intro', 'background', 'outro' );

			foreach ( $audio_types as $type ) {
				$source_dir = $plugin_audio_dir . '/' . $type;
				$dest_dir   = $library_dir . '/' . $type;

				if ( is_dir( $source_dir ) ) {
					wp_mkdir_p( $dest_dir );

					$files = glob( $source_dir . '/*.{mp3,wav,ogg}', GLOB_BRACE );
					foreach ( $files as $file ) {
						$filename  = basename( $file );
						$dest_file = $dest_dir . '/' . $filename;

						if ( ! file_exists( $dest_file ) ) {
							copy( $file, $dest_file );
						}
					}
				}
			}
		}
	}

	/**
	 * Set up TTS meta for existing posts
	 */
	private static function setupExistingPosts(): void {
		// Get setting for auto-setup
		$auto_setup = get_option( 'wp_tts_auto_setup_existing', false );

		if ( ! $auto_setup ) {
			return;
		}

		// Get posts that don't have TTS meta
		$posts = get_posts(
			array(
				'post_type'   => array( 'post', 'page' ),
				'post_status' => 'publish',
				'numberposts' => -1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to find posts without TTS meta during activation
				'meta_query'  => array(
					array(
						'key'     => '_tts_enabled',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $posts as $post ) {
			// Set default TTS meta
			update_post_meta( $post->ID, '_tts_enabled', 0 );
			update_post_meta( $post->ID, '_tts_voice_provider', '' );
			update_post_meta( $post->ID, '_tts_voice_id', '' );
			update_post_meta( $post->ID, '_tts_generation_status', 'not_generated' );
		}
	}

	/**
	 * Schedule cron jobs
	 */
	private static function scheduleCronJobs(): void {
		// Cache cleanup job
		if ( ! wp_next_scheduled( 'wp_tts_cache_cleanup' ) ) {
			wp_schedule_event( time(), 'hourly', 'wp_tts_cache_cleanup' );
		}

		// Analytics update job
		if ( ! wp_next_scheduled( 'wp_tts_analytics_update' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_tts_analytics_update' );
		}

		// Provider quota reset job (monthly)
		if ( ! wp_next_scheduled( 'wp_tts_quota_reset' ) ) {
			$next_month = strtotime( 'first day of next month' );
			wp_schedule_event( $next_month, 'monthly', 'wp_tts_quota_reset' );
		}

		// Health check job
		if ( ! wp_next_scheduled( 'wp_tts_health_check' ) ) {
			wp_schedule_event( time() + 300, 'twicedaily', 'wp_tts_health_check' );
		}
	}

	/**
	 * Create database tables if needed
	 *
	 * Note: Currently using WordPress meta/options, but this method
	 * is here for future custom table implementations
	 */
	private static function createDatabaseTables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Example custom table for analytics (optional)
		$table_name = $wpdb->prefix . 'tts_analytics';

		$sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            provider varchar(50) NOT NULL,
            action varchar(50) NOT NULL,
            characters_processed int(11) DEFAULT 0,
            duration float DEFAULT 0,
            cost decimal(10,4) DEFAULT 0,
            metadata text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY provider (provider),
            KEY created_at (created_at)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Set up user capabilities
	 */
	private static function setupCapabilities(): void {
		// Add capabilities to administrator role
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'manage_tts_settings' );
			$admin_role->add_cap( 'generate_tts_audio' );
			$admin_role->add_cap( 'view_tts_analytics' );
		}

		// Add capabilities to editor role
		$editor_role = get_role( 'editor' );
		if ( $editor_role ) {
			$editor_role->add_cap( 'generate_tts_audio' );
		}

		// Add capabilities to author role
		$author_role = get_role( 'author' );
		if ( $author_role ) {
			$author_role->add_cap( 'generate_tts_audio' );
		}
	}

	/**
	 * Perform database migration if needed
	 */
	private static function performMigration(): void {
		$current_version = get_option( 'wp_tts_plugin_version', '0.0.0' );

		if ( version_compare( $current_version, WP_TTS_PLUGIN_VERSION, '<' ) ) {
			// Perform version-specific migrations
			self::migrateFromVersion( $current_version );

			// Update version
			update_option( 'wp_tts_plugin_version', WP_TTS_PLUGIN_VERSION );
		}
	}

	/**
	 * Migrate from specific version
	 *
	 * @param string $from_version Version to migrate from
	 */
	private static function migrateFromVersion( string $from_version ): void {
		// Example migration logic
		if ( version_compare( $from_version, '1.0.0', '<' ) ) {
			// Migration from pre-1.0.0
			self::migrateToV1();
		}

		// Add more migration logic as needed
	}

	/**
	 * Migrate to version 1.0.0
	 */
	private static function migrateToV1(): void {
		// Example: Convert old option format to new format
		$old_config = get_option( 'wp_tts_old_config' );
		if ( $old_config ) {
			// Convert and save in new format
			$config_manager = new ConfigurationManager();
			// ... conversion logic ...

			// Remove old option
			delete_option( 'wp_tts_old_config' );
		}
	}

	/**
	 * Send activation notification
	 */
	private static function sendActivationNotification(): void {
		// Get admin email
		$admin_email = get_option( 'admin_email' );

		if ( $admin_email ) {
			$subject = __( 'Plugin TTS de WordPress Activado', 'tts-sesolibre' );
			$message = sprintf(
				/* translators: %s: site name */
				__( 'El Plugin TTS de WordPress ha sido activado exitosamente en %s.', 'tts-sesolibre' ),
				get_bloginfo( 'name' )
			);

			wp_mail( $admin_email, $subject, $message );
		}
	}

	/**
	 * Log activation details
	 */
	private static function logActivation(): void {
		$log_data = array(
			'plugin_version'    => WP_TTS_PLUGIN_VERSION,
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'site_url'          => get_site_url(),
			'activation_time'   => current_time( 'mysql' ),
			'user_id'           => get_current_user_id(),
		);

		// Store activation log
		update_option( 'wp_tts_activation_log', $log_data );

		// Log to file if debug is enabled
		if ( WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'WP TTS Plugin Activation: ' . json_encode( $log_data ) );
		}
	}
}
