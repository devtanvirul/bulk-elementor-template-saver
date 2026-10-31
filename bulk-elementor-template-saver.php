<?php
/**
 * Plugin Name: Bulk Elementor Template Saver
 * Description: Lists every page and post built with Elementor, and saves the ones you tick into the Elementor Template Library in a single click.
 * Version: 1.3.0
 * Author: devtanvirul
 * Author URI: https://devtanvirul.com
 * Plugin URI: https://devtanvirul.com
 * Text Domain: bulk-elementor-template-saver
 * License: GPL-2.0-or-later
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bulk_Elementor_Template_Saver {

	/** Meta on the source post holding the ID of the template generated from it. */
	const META_LINK = '_bets_template_id';

	const PAGE_SLUG = 'bets-bulk-saver';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_bets_save_templates', array( $this, 'handle_save' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/* ---------------------------------------------------------------
	 * Admin menu
	 * ------------------------------------------------------------- */

	public function add_menu() {
		$parent = post_type_exists( 'elementor_library' )
			? 'edit.php?post_type=elementor_library'
			: 'tools.php';

		add_submenu_page(
			$parent,
			__( 'Bulk Template Saver', 'bulk-elementor-template-saver' ),
			__( 'Bulk Template Saver', 'bulk-elementor-template-saver' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/* ---------------------------------------------------------------
	 * Data
	 * ------------------------------------------------------------- */

	/**
	 * Every post built with Elementor, excluding the library itself.
	 *
	 * @return int[]
	 */
	private function get_elementor_items() {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm
				   ON pm.post_id = p.ID
				  AND pm.meta_key = '_elementor_edit_mode'
				  AND pm.meta_value = 'builder'
				 WHERE p.post_type != %s
				   AND p.post_status IN ('publish','draft','private')
				 ORDER BY p.post_title ASC",
				'elementor_library'
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * Template types Elementor accepts in _elementor_template_type.
	 *
	 * @return array<string,string>
	 */
	private function template_types() {
		return array(
			'page'        => __( 'Page', 'bulk-elementor-template-saver' ),
			'section'     => __( 'Section', 'bulk-elementor-template-saver' ),
			'header'      => __( 'Header', 'bulk-elementor-template-saver' ),
			'footer'      => __( 'Footer', 'bulk-elementor-template-saver' ),
			'popup'       => __( 'Popup', 'bulk-elementor-template-saver' ),
			'single-post' => __( 'Single Post', 'bulk-elementor-template-saver' ),
			'archive'     => __( 'Archive', 'bulk-elementor-template-saver' ),
		);
	}

	/**
	 * Pre-select a sensible type from the title so most rows need no change.
	 *
	 * @param string $title Post title.
	 * @return string
	 */
	private function guess_type( $title ) {
		$title = mb_strtolower( $title );

		foreach ( array( 'header', 'footer', 'popup' ) as $needle ) {
			if ( false !== strpos( $title, $needle ) ) {
				return $needle;
			}
		}

		return 'page';
	}

	/**
	 * Size and health of a post's Elementor data, so the screen can show
	 * whether a copy actually carried everything across.
	 *
	 * @param int $post_id Post to inspect.
	 * @return array{len:int,valid:bool,count:int}
	 */
	private function data_info( $post_id ) {
		$raw   = get_post_meta( $post_id, '_elementor_data', true );
		$len   = is_string( $raw ) ? strlen( $raw ) : 0;
		$valid = false;
		$count = 0;

		if ( $len > 0 ) {
			$decoded = json_decode( $raw, true );

			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$valid = true;
				$count = count( $decoded );
			}
		}

		return compact( 'len', 'valid', 'count' );
	}

	/* ---------------------------------------------------------------
	 * Admin screen
	 * ------------------------------------------------------------- */

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$items = $this->get_elementor_items();
		$types = $this->template_types();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bulk Template Saver', 'bulk-elementor-template-saver' ); ?></h1>

			<p>
				<?php esc_html_e( 'Every page and post built with Elementor is listed below. Tick the ones you want to save as templates, choose a type from the dropdown, then click the button at the bottom. They will all be saved into the Elementor Template Library at once.', 'bulk-elementor-template-saver' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Saving a row again updates the template it created earlier instead of making a duplicate. The "Source data" and "Template" columns show how much Elementor data each design holds, so you can confirm every copy came across in full.', 'bulk-elementor-template-saver' ); ?>
			</p>

			<?php if ( empty( $items ) ) : ?>

				<p><em><?php esc_html_e( 'No pages or posts built with Elementor were found on this site.', 'bulk-elementor-template-saver' ); ?></em></p>

			<?php else : ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="bets_save_templates">
					<?php wp_nonce_field( 'bets_save_templates_action', 'bets_nonce' ); ?>

					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<td class="manage-column column-cb check-column">
									<input type="checkbox" id="bets-select-all">
								</td>
								<th><?php esc_html_e( 'Title', 'bulk-elementor-template-saver' ); ?></th>
								<th style="width:110px;"><?php esc_html_e( 'Post type', 'bulk-elementor-template-saver' ); ?></th>
								<th style="width:150px;"><?php esc_html_e( 'Source data', 'bulk-elementor-template-saver' ); ?></th>
								<th style="width:150px;"><?php esc_html_e( 'Save as', 'bulk-elementor-template-saver' ); ?></th>
								<th style="width:230px;"><?php esc_html_e( 'Template', 'bulk-elementor-template-saver' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php
						foreach ( $items as $id ) :
							$title    = get_the_title( $id );
							$guess    = $this->guess_type( $title );
							$template = (int) get_post_meta( $id, self::META_LINK, true );
							$saved    = $template && get_post_status( $template );
							$source   = $this->data_info( $id );
							?>
							<tr>
								<th class="check-column">
									<input type="checkbox" name="bets_items[]" value="<?php echo esc_attr( $id ); ?>">
								</th>
								<td>
									<strong><?php echo esc_html( $title ? $title : sprintf( '(no title) #%d', $id ) ); ?></strong>
									<div class="row-actions">
										<a href="<?php echo esc_url( (string) get_edit_post_link( $id ) ); ?>" target="_blank">
											<?php esc_html_e( 'Edit source', 'bulk-elementor-template-saver' ); ?>
										</a>
									</div>
								</td>
								<td><?php echo esc_html( get_post_type( $id ) ); ?></td>
								<td>
									<?php if ( $source['len'] > 0 ) : ?>
										<?php
										printf(
											/* translators: 1: character count, 2: number of top level elements. */
											esc_html__( '%1$s chars, %2$d elements', 'bulk-elementor-template-saver' ),
											esc_html( number_format_i18n( $source['len'] ) ),
											(int) $source['count']
										);
										?>
									<?php else : ?>
										<span style="color:#b32d2e;"><?php esc_html_e( 'empty', 'bulk-elementor-template-saver' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<select name="bets_type[<?php echo esc_attr( $id ); ?>]">
										<?php foreach ( $types as $slug => $label ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $guess, $slug ); ?>>
												<?php echo esc_html( $label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<?php if ( ! $saved ) : ?>
										&mdash;
									<?php else : $copy = $this->data_info( $template ); ?>
										<a href="<?php echo esc_url( (string) get_edit_post_link( $template ) ); ?>" target="_blank">
											<?php esc_html_e( 'Open template', 'bulk-elementor-template-saver' ); ?>
										</a>
										<br>
										<?php if ( 0 === $copy['len'] ) : ?>
											<span style="color:#b32d2e;"><?php esc_html_e( 'No data copied', 'bulk-elementor-template-saver' ); ?></span>
										<?php elseif ( ! $copy['valid'] ) : ?>
											<span style="color:#b32d2e;"><?php esc_html_e( 'Broken JSON, save again', 'bulk-elementor-template-saver' ); ?></span>
										<?php elseif ( $copy['len'] !== $source['len'] ) : ?>
											<span style="color:#b32d2e;">
												<?php
												printf(
													/* translators: %s: number of characters missing from the copy. */
													esc_html__( '%s chars lost, save again', 'bulk-elementor-template-saver' ),
													esc_html( number_format_i18n( abs( $source['len'] - $copy['len'] ) ) )
												);
												?>
											</span>
										<?php else : ?>
											<span style="color:#1a7e1a;">
												<?php
												printf(
													/* translators: %s: character count that matched the source exactly. */
													esc_html__( '%s chars, exact match', 'bulk-elementor-template-saver' ),
													esc_html( number_format_i18n( $copy['len'] ) )
												);
												?>
											</span>
										<?php endif; ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary button-hero">
							<?php esc_html_e( 'Save selected as Templates', 'bulk-elementor-template-saver' ); ?>
						</button>
					</p>
				</form>

			<?php endif; ?>

			<hr>
			<p style="color:#646970;">
				<?php
				printf(
					/* translators: %s: developer name, linked to their website. */
					esc_html__( 'Developed by %s', 'bulk-elementor-template-saver' ),
					'<a href="https://devtanvirul.com" target="_blank" rel="noopener">devtanvirul</a>'
				);
				?>
			</p>
		</div>

		<script>
		document.getElementById( 'bets-select-all' ).addEventListener( 'change', function ( event ) {
			document.querySelectorAll( 'input[name="bets_items[]"]' ).forEach( function ( box ) {
				box.checked = event.target.checked;
			} );
		} );
		</script>
		<?php
	}

	/* ---------------------------------------------------------------
	 * Saving
	 * ------------------------------------------------------------- */

	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'bulk-elementor-template-saver' ) );
		}

		check_admin_referer( 'bets_save_templates_action', 'bets_nonce' );

		$ids   = isset( $_POST['bets_items'] ) ? array_map( 'intval', (array) $_POST['bets_items'] ) : array();
		$types = isset( $_POST['bets_type'] ) ? wp_unslash( (array) $_POST['bets_type'] ) : array();
		$valid = $this->template_types();

		$counts = array(
			'saved'   => 0,
			'skipped' => 0,
			'failed'  => 0,
		);

		foreach ( $ids as $source_id ) {
			$type = isset( $types[ $source_id ] ) ? sanitize_key( $types[ $source_id ] ) : 'page';

			if ( ! isset( $valid[ $type ] ) ) {
				$type = 'page';
			}

			$result = $this->save_as_template( $source_id, $type );
			$counts[ $result ]++;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'    => 'elementor_library',
					'page'         => self::PAGE_SLUG,
					'bets_saved'   => $counts['saved'],
					'bets_skipped' => $counts['skipped'],
					'bets_failed'  => $counts['failed'],
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Copy one post's Elementor design into an elementor_library template.
	 *
	 * @param int    $source_id Post to copy from.
	 * @param string $type      Elementor template type.
	 * @return string One of: saved, skipped, failed.
	 */
	private function save_as_template( $source_id, $type ) {
		$source = get_post( $source_id );
		$data   = get_post_meta( $source_id, '_elementor_data', true );

		if ( ! $source || empty( $data ) ) {
			return 'skipped';
		}

		$existing = (int) get_post_meta( $source_id, self::META_LINK, true );

		$postarr = array(
			'post_title'   => $source->post_title,
			'post_status'  => 'publish',
			'post_type'    => 'elementor_library',
			'post_content' => '',
		);

		if ( $existing && get_post_status( $existing ) ) {
			$postarr['ID'] = $existing;
		}

		$template_id = $existing && get_post_status( $existing )
			? wp_update_post( $postarr, true )
			: wp_insert_post( $postarr, true );

		if ( is_wp_error( $template_id ) || ! $template_id ) {
			return 'failed';
		}

		/*
		 * update_post_meta() runs wp_unslash() on the value internally, which
		 * strips a layer of backslashes out of the JSON:
		 *   "https:\/\/site.com"  ->  "https://site.com"   (shorter, still valid)
		 *   "he said \"hi\""      ->  "he said "hi""       (JSON now broken)
		 * Elementor wraps the value in wp_slash() for the same reason. Without
		 * it, any design containing a quote mark lands in the library
		 * unparseable and opens as a blank canvas.
		 */
		update_post_meta( $template_id, '_elementor_data', wp_slash( $data ) );
		update_post_meta( $template_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $template_id, '_elementor_template_type', $type );

		$version = get_post_meta( $source_id, '_elementor_version', true );

		if ( ! $version ) {
			$version = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0';
		}

		update_post_meta( $template_id, '_elementor_version', $version );

		$settings = get_post_meta( $source_id, '_elementor_page_settings', true );
		if ( ! empty( $settings ) ) {
			update_post_meta( $template_id, '_elementor_page_settings', wp_slash( $settings ) );
		}

		// Puts the template under the matching tab in the library screen.
		if ( taxonomy_exists( 'elementor_library_type' ) ) {
			wp_set_object_terms( $template_id, $type, 'elementor_library_type' );
		}

		update_post_meta( $source_id, self::META_LINK, $template_id );

		// Drop styles generated for whatever the template held before.
		delete_post_meta( $template_id, '_elementor_css' );
		$this->clear_elementor_cache();

		// The copy is only good if it came back byte for byte.
		if ( get_post_meta( $template_id, '_elementor_data', true ) !== $data ) {
			return 'failed';
		}

		return 'saved';
	}

	private function clear_elementor_cache() {
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	/* ---------------------------------------------------------------
	 * Result notice
	 * ------------------------------------------------------------- */

	public function notices() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( self::PAGE_SLUG !== $page || ! isset( $_GET['bets_saved'] ) ) {
			return;
		}

		$saved   = (int) $_GET['bets_saved'];
		$skipped = isset( $_GET['bets_skipped'] ) ? (int) $_GET['bets_skipped'] : 0;
		$failed  = isset( $_GET['bets_failed'] ) ? (int) $_GET['bets_failed'] : 0;

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: templates saved, 2: rows skipped. */
					__( '%1$d template(s) saved. %2$d skipped for holding no Elementor data.', 'bulk-elementor-template-saver' ),
					$saved,
					$skipped
				)
			)
		);

		if ( $failed > 0 ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of rows that could not be saved. */
						__( '%d row(s) could not be saved correctly and were left out. Try saving them again.', 'bulk-elementor-template-saver' ),
						$failed
					)
				)
			);
		}
	}
}

new Bulk_Elementor_Template_Saver();
