<?php

namespace WP_TTS\Core;

/**
 * Plugin Deactivator
 *
 * Handles plugin deactivation tasks including cleanup,
 * cron job removal, and temporary file cleanup.
 */
class Deactivator {

	/**
	 * Deactivate the plugin
	 */
	public static function deactivate(): void {
		// Clear scheduled cron jobs
		self::clearCronJobs();

		// Clean up temporary files
		self::cleanupTemporaryFiles();

		// Clear transients and cache
		self::clearCache();

		// Log deactivation
		self::logDeactivation();

		// Set deactivation flag
		update_option( 'wp_tts_plugin_deactivated', true );
		update_option( 'wp_tts_plugin_deactivation_time', current_time( 'mysql' ) );

		// Flush rewrite rules
		flush_rewrite_rules();

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'WP TTS Plugin deactivated successfully' );
	}

	/**
	 * Clear all scheduled cron jobs
	 */
	private static function clearCronJobs(): void {
		$cron_jobs = array(
			'wp_tts_cache_cleanup',
			'wp_tts_analytics_update',
			'wp_tts_quota_reset',
			'wp_tts_health_check',
			'wp_tts_generate_audio_background',
		);

		foreach ( $cron_jobs as $job ) {
			$timestamp = wp_next_scheduled( $job );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $job );
			}

			// Clear all instances of the job
			wp_clear_scheduled_hook( $job );
		}
	}

	/**
	 * Clean up temporary files
	 */
	private static function cleanupTemporaryFiles(): void {
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/tts-audio/temp';

		if ( is_dir( $temp_dir ) ) {
			self::deleteDirectoryContents( $temp_dir );
		}

		// Clean up any orphaned files older than 24 hours
		$cache_dir = $upload_dir['basedir'] . '/tts-audio/cache';
		if ( is_dir( $cache_dir ) ) {
			self::cleanupOldFiles( $cache_dir, 86400 ); // 24 hours
		}
	}

	/**
	 * Clear all plugin-related cache and transients
	 */
	private static function clearCache(): void {
		global $wpdb;

		// Clear plugin transients
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for transient cleanup
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_wp_tts_%'
             OR option_name LIKE '_transient_timeout_wp_tts_%'"
		);

		// Clear object cache if available
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'wp_tts' );
		}

		// Clear any cached provider data
		delete_option( 'wp_tts_provider_cache' );
		delete_option( 'wp_tts_voice_cache' );
	}

	/**
	 * Delete contents of a directory
	 *
	 * @param string $dir Directory path
	 */
	private static function deleteDirectoryContents( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = glob( $dir . '/*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			} elseif ( is_dir( $file ) ) {
				self::deleteDirectoryContents( $file );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Direct rmdir needed for directory cleanup
				rmdir( $file );
			}
		}
	}

	/**
	 * Clean up old files in a directory
	 *
	 * @param string $dir Directory path
	 * @param int    $max_age Maximum age in seconds
	 */
	private static function cleanupOldFiles( string $dir, int $max_age ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files       = glob( $dir . '/*' );
		$cutoff_time = time() - $max_age;

		foreach ( $files as $file ) {
			if ( is_file( $file ) && filemtime( $file ) < $cutoff_time ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Remove user capabilities (optional - usually kept for reactivation)
	 */
	private static function removeCapabilities(): void {
		// Note: Usually we don't remove capabilities on deactivation
		// as users might want to reactivate the plugin later
		// This method is here for completeness but not called by default

		$roles        = array( 'administrator', 'editor', 'author' );
		$capabilities = array( 'manage_tts_settings', 'generate_tts_audio', 'view_tts_analytics' );

		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $capabilities as $cap ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * Clean up database (optional - for complete removal)
	 *
	 * Note: This is not called during normal deactivation
	 * It's here for potential uninstall functionality
	 */
	private static function cleanupDatabase(): void {
		global $wpdb;

		// Remove plugin options (optional)
		$options_to_remove = array(
			'wp_tts_providers_config',
			'wp_tts_default_settings',
			'wp_tts_round_robin_state',
			'wp_tts_cache_settings',
			'wp_tts_audio_library',
			'wp_tts_analytics_settings',
			'wp_tts_plugin_activated',
			'wp_tts_plugin_version',
			'wp_tts_activation_log',
		);

		foreach ( $options_to_remove as $option ) {
			delete_option( $option );
		}

		// Remove post meta (optional)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for meta cleanup
		$wpdb->query(
			"DELETE FROM {$wpdb->postmeta}
             WHERE meta_key LIKE '_tts_%'"
		);

		// Drop custom tables if they exist
		$table_name = $wpdb->prefix . 'tts_analytics';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query needed for table drop during uninstall, table name is safe as it uses wpdb prefix
		$wpdb->query( "DROP TABLE IF EXISTS " . esc_sql( $table_name ) );
	}

	/**
	 * Send deactivation notification
	 */
	private static function sendDeactivationNotification(): void {
		// Only send if enabled in settings
		$send_notifications = get_option( 'wp_tts_send_notifications', false );

		if ( ! $send_notifications ) {
			return;
		}

		$admin_email = get_option( 'admin_email' );

		if ( $admin_email ) {
			$subject = __( 'Plugin TTS de WordPress Desactivado', 'tts-sesolibre' );
			$message = sprintf(
				/* translators: %1$s: site name, %2$s: date and time */
				__( 'El Plugin TTS de WordPress ha sido desactivado en %1$s el %2$s.', 'tts-sesolibre' ),
				get_bloginfo( 'name' ),
				current_time( 'mysql' )
			);

			wp_mail( $admin_email, $subject, $message );
		}
	}

	/**
	 * Log deactivation details
	 */
	private static function logDeactivation(): void {
		$log_data = array(
			'plugin_version'    => WP_TTS_PLUGIN_VERSION,
			'deactivation_time' => current_time( 'mysql' ),
			'user_id'           => get_current_user_id(),
			'site_url'          => get_site_url(),
			'reason'            => self::getDeactivationReason(),
		);

		// Store deactivation log
		update_option( 'wp_tts_deactivation_log', $log_data );

		// Log to file if debug is enabled
		if ( WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'WP TTS Plugin Deactivation: ' . json_encode( $log_data ) );
		}
	}

	/**
	 * Get deactivation reason (if available)
	 *
	 * @return string Deactivation reason
	 */
	private static function getDeactivationReason(): string {
		// This could be enhanced to capture user feedback
		// For now, just return a generic reason
		return 'Plugin deactivated by user';
	}

	/**
	 * Backup configuration before deactivation
	 */
	private static function backupConfiguration(): void {
		$config_manager = new ConfigurationManager();
		$config         = $config_manager->export( false ); // Don't include credentials

		$backup_data = array(
			'config'         => $config,
			'backup_time'    => current_time( 'mysql' ),
			'plugin_version' => WP_TTS_PLUGIN_VERSION,
		);

		update_option( 'wp_tts_config_backup', $backup_data );
	}

	/**
	 * Clean up background processes
	 */
	private static function cleanupBackgroundProcesses(): void {
		// Cancel any running background processes
		$background_processes = array(
			'wp_tts_audio_generation',
			'wp_tts_batch_processing',
			'wp_tts_cache_warming',
		);

		foreach ( $background_processes as $process ) {
			// Clear any queued items
			delete_option( $process . '_queue' );
			delete_option( $process . '_status' );
		}
	}

	/**
	 * Notify external services of deactivation
	 */
	private static function notifyExternalServices(): void {
		// If the plugin integrates with external analytics or monitoring services,
		// notify them of the deactivation

		$config_manager     = new ConfigurationManager();
		$analytics_settings = $config_manager->get( 'analytics', array() );

		if ( ! empty( $analytics_settings['external_service_url'] ) ) {
			// Send deactivation event to external service
			$data = array(
				'event'     => 'plugin_deactivated',
				'site_url'  => get_site_url(),
				'timestamp' => current_time( 'timestamp' ),
			);

			wp_remote_post(
				$analytics_settings['external_service_url'],
				array(
					'body'    => json_encode( $data ),
					'headers' => array( 'Content-Type' => 'application/json' ),
					'timeout' => 5,
				)
			);
		}
	}

	/**
	 * Complete deactivation with cleanup options
	 *
	 * @param bool $remove_data Whether to remove all plugin data
	 * @param bool $backup_config Whether to backup configuration
	 */
	public static function completeDeactivation( bool $remove_data = false, bool $backup_config = true ): void {
		if ( $backup_config ) {
			self::backupConfiguration();
		}

		self::cleanupBackgroundProcesses();
		self::notifyExternalServices();

		if ( $remove_data ) {
			self::cleanupDatabase();
			self::removeCapabilities();

			// Remove all files
			$upload_dir = wp_upload_dir();
			$tts_dir    = $upload_dir['basedir'] . '/tts-audio';
			if ( is_dir( $tts_dir ) ) {
				self::deleteDirectoryContents( $tts_dir );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Direct rmdir needed for directory cleanup
				rmdir( $tts_dir );
			}
		}

		// Send notification if configured
		self::sendDeactivationNotification();
	}

	/**
	 * Get deactivation statistics
	 *
	 * @return array Deactivation statistics
	 */
	public static function getDeactivationStats(): array {
		$config_manager = new ConfigurationManager();

		return array(
			'activation_time'       => get_option( 'wp_tts_plugin_activation_time' ),
			'deactivation_time'     => current_time( 'mysql' ),
			'total_posts_with_tts'  => self::getTotalTTSPosts(),
			'total_audio_generated' => self::getTotalAudioGenerated(),
			'enabled_providers'     => count( $config_manager->getEnabledProviders() ),
			'plugin_version'        => WP_TTS_PLUGIN_VERSION,
		);
	}

	/**
	 * Get total posts with TTS enabled
	 *
	 * @return int Number of posts with TTS
	 */
	private static function getTotalTTSPosts(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for statistics
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = '_tts_enabled' AND meta_value = '1'"
		);
	}

	/**
	 * Get total audio files generated
	 *
	 * @return int Number of audio files generated
	 */
	private static function getTotalAudioGenerated(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for statistics
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = '_tts_audio_url' AND meta_value != ''"
		);
	}
}
