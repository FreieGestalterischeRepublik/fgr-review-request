<?php
/**
 * Backend-Einstellungsseite: Verzögerung, Bewertungslink, E-Mail-Text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FGR_RR_Settings {

	const OPTION_GROUP = 'fgr_rr_settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_fgr_rr_send_test', array( $this, 'handle_test_email' ) );
	}

	public function add_menu() {
		add_submenu_page(
			'woocommerce',
			'Bewertungs-Erinnerung',
			'Bewertungs-Erinnerung',
			'manage_woocommerce',
			'fgr-review-request',
			array( $this, 'render_page' )
		);
	}

	public static function default_body() {
		return "Liebe/r {name},\n\nwir hoffen, der Kochkurs \"{kurs}\" bei PINTO hat Ihnen gefallen!\n\nWenn Sie uns unterstützen möchten, würden wir uns sehr über eine kurze Google-Bewertung freuen:\n{link}\n\nVielen Dank und bis bald bei PINTO!\nIhr Pinto-Team";
	}

	public function register_settings() {
		register_setting( self::OPTION_GROUP, 'fgr_rr_delay_hours', array(
			'type'              => 'number',
			'sanitize_callback' => function ( $value ) {
				$value = (float) $value;
				return $value >= 0 ? $value : 24;
			},
			'default'           => 24,
		) );

		register_setting( self::OPTION_GROUP, 'fgr_rr_review_link', array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => 'https://share.google/t8D4vj9l1kKnVnw8Z',
		) );

		register_setting( self::OPTION_GROUP, 'fgr_rr_subject', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'Wie hat Ihnen der Kochkurs bei PINTO gefallen?',
		) );

		register_setting( self::OPTION_GROUP, 'fgr_rr_body', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => self::default_body(),
		) );

		register_setting( self::OPTION_GROUP, 'fgr_rr_enabled', array(
			'type'              => 'boolean',
			'sanitize_callback' => function ( $value ) {
				return ! empty( $value );
			},
			'default'           => true,
		) );
	}

	public function handle_test_email() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'fgr_rr_send_test' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}

		$to = isset( $_POST['fgr_rr_test_email'] ) ? sanitize_email( wp_unslash( $_POST['fgr_rr_test_email'] ) ) : '';
		if ( $to && is_email( $to ) ) {
			$vars = array(
				'{name}' => 'Max Mustermann',
				'{kurs}' => 'Thailändischer Streetfood-Kochkurs',
				'{link}' => get_option( 'fgr_rr_review_link' ),
			);
			$subject = strtr( get_option( 'fgr_rr_subject' ), $vars );
			$body    = strtr( get_option( 'fgr_rr_body' ), $vars );
			wp_mail( $to, '[Test] ' . $subject, $body );
			add_settings_error( 'fgr_rr_messages', 'fgr_rr_test_sent', 'Test-E-Mail an ' . esc_html( $to ) . ' verschickt.', 'success' );
		} else {
			add_settings_error( 'fgr_rr_messages', 'fgr_rr_test_invalid', 'Bitte eine gültige E-Mail-Adresse eingeben.', 'error' );
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=fgr-review-request&settings-updated=true' ) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>Google-Bewertungs-Erinnerung</h1>
			<p>Verschickt automatisch eine E-Mail nach jedem abgeschlossenen Kochkurs (erkannt über das FooEvents-Kursdatum am Produkt), mit Bitte um eine Google-Bewertung.</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="fgr_rr_enabled">Aktiv</label></th>
						<td>
							<label>
								<input type="checkbox" name="fgr_rr_enabled" id="fgr_rr_enabled" value="1" <?php checked( get_option( 'fgr_rr_enabled', true ) ); ?> />
								Bewertungs-Erinnerung verschicken
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fgr_rr_delay_hours">Verzögerung (Stunden nach Kursbeginn)</label></th>
						<td>
							<input type="number" step="0.5" min="0" name="fgr_rr_delay_hours" id="fgr_rr_delay_hours" value="<?php echo esc_attr( get_option( 'fgr_rr_delay_hours', 24 ) ); ?>" class="small-text" />
							<p class="description">Standard: 24 Stunden. Kann pro Bedarf angepasst werden.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fgr_rr_review_link">Google-Bewertungslink</label></th>
						<td>
							<input type="url" name="fgr_rr_review_link" id="fgr_rr_review_link" value="<?php echo esc_attr( get_option( 'fgr_rr_review_link', 'https://share.google/t8D4vj9l1kKnVnw8Z' ) ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fgr_rr_subject">E-Mail-Betreff</label></th>
						<td>
							<input type="text" name="fgr_rr_subject" id="fgr_rr_subject" value="<?php echo esc_attr( get_option( 'fgr_rr_subject', 'Wie hat Ihnen der Kochkurs bei PINTO gefallen?' ) ); ?>" class="regular-text" />
							<p class="description">Platzhalter: <code>{kurs}</code></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fgr_rr_body">E-Mail-Text</label></th>
						<td>
							<textarea name="fgr_rr_body" id="fgr_rr_body" rows="10" class="large-text"><?php echo esc_textarea( get_option( 'fgr_rr_body', self::default_body() ) ); ?></textarea>
							<p class="description">Platzhalter: <code>{name}</code>, <code>{kurs}</code>, <code>{link}</code></p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Speichern' ); ?>
			</form>

			<hr />
			<h2>Test-E-Mail</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fgr_rr_send_test' ); ?>
				<input type="hidden" name="action" value="fgr_rr_send_test" />
				<input type="email" name="fgr_rr_test_email" placeholder="test@beispiel.de" class="regular-text" required />
				<?php submit_button( 'Test-E-Mail senden', 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
