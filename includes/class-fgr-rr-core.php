<?php
/**
 * Plant und verschickt die Bewertungs-Erinnerung.
 *
 * Ablauf: Bei Bestellabschluss wird pro Kochkurs-Produkt in der Bestellung ein
 * einmaliges WP-Cron-Ereignis fuer "Kursbeginn + X Stunden" eingeplant (kein
 * echter Server-Cron - laeuft ueber WordPress' eingebauten Scheduler, ausgeloest
 * durch normalen Seitenaufruf-Traffic). Beim Ausloesen werden alle FooEvents-
 * Tickets (event_magic_tickets) fuer genau diese Bestellung+Produkt geladen,
 * die Empfaenger-Adresse ermittelt (Teilnehmer-E-Mail falls vorhanden, sonst
 * Kaeufer-E-Mail als Fallback - identische Logik wie im FGR-FooEventsExport-
 * Plugin) und nach eindeutiger E-Mail-Adresse dedupliziert, damit eine Person
 * bei mehreren Tickets nur eine E-Mail bekommt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FGR_RR_Core {

	const CRON_HOOK    = 'fgr_rr_send_review_request';
	const SCHEDULED_KEY = '_fgr_rr_scheduled';

	public function __construct() {
		add_action( 'woocommerce_order_status_completed', array( $this, 'schedule_for_order' ) );
		add_action( self::CRON_HOOK, array( $this, 'send_for_order_product' ), 10, 2 );
	}

	/**
	 * Bei Bestellabschluss: fuer jedes Kochkurs-Produkt (mit FooEvents-Kursdatum)
	 * in der Bestellung ein einmaliges Versand-Ereignis einplanen.
	 */
	public function schedule_for_order( $order_id ) {
		if ( ! get_option( 'fgr_rr_enabled', true ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( self::SCHEDULED_KEY ) ) {
			return; // Schon eingeplant (z.B. Bestellstatus mehrfach durchlaufen) - kein Doppel-Planen.
		}

		$delay_hours = (float) get_option( 'fgr_rr_delay_hours', 24 );
		$scheduled   = false;

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$event_ts   = (int) get_post_meta( $product_id, 'WooCommerceEventsDateTimestamp', true );

			if ( ! $event_ts ) {
				continue; // kein Kurs-/Event-Produkt (z.B. Geschenkgutschein) - ueberspringen.
			}

			$send_at = $event_ts + (int) round( $delay_hours * HOUR_IN_SECONDS );

			wp_schedule_single_event( $send_at, self::CRON_HOOK, array( $order_id, $product_id ) );
			$scheduled = true;
		}

		if ( $scheduled ) {
			$order->update_meta_data( self::SCHEDULED_KEY, 1 );
			$order->save();
		}
	}

	/**
	 * Empfaenger fuer eine Bestellung+Produkt ermitteln: pro Ticket Teilnehmer-
	 * E-Mail, sonst Kaeufer-E-Mail als Fallback. Nach E-Mail-Adresse dedupliziert.
	 *
	 * @return array E-Mail (klein geschrieben) => Name
	 */
	private function get_recipients( $order_id, $product_id ) {
		$ticket_ids = get_posts( array(
			'post_type'      => 'event_magic_tickets',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => 'WooCommerceEventsOrderID',
					'value' => $order_id,
				),
				array(
					'key'   => 'WooCommerceEventsProductID',
					'value' => $product_id,
				),
			),
		) );

		$recipients = array();

		foreach ( $ticket_ids as $ticket_id ) {
			if ( 'Canceled' === get_post_meta( $ticket_id, 'WooCommerceEventsStatus', true ) ) {
				continue;
			}

			$name = trim(
				get_post_meta( $ticket_id, 'WooCommerceEventsAttendeeName', true ) . ' ' .
				get_post_meta( $ticket_id, 'WooCommerceEventsAttendeeLastName', true )
			);
			$email = get_post_meta( $ticket_id, 'WooCommerceEventsAttendeeEmail', true );

			if ( '' === $email ) {
				$email = get_post_meta( $ticket_id, 'WooCommerceEventsPurchaserEmail', true );
				if ( '' === $name ) {
					$name = trim(
						get_post_meta( $ticket_id, 'WooCommerceEventsPurchaserFirstName', true ) . ' ' .
						get_post_meta( $ticket_id, 'WooCommerceEventsPurchaserLastName', true )
					);
				}
			}

			if ( '' === $email || ! is_email( $email ) ) {
				continue;
			}

			$email_key = strtolower( $email );
			if ( ! isset( $recipients[ $email_key ] ) ) {
				$recipients[ $email_key ] = $name;
			}
		}

		return $recipients;
	}

	/**
	 * Wird zum geplanten Zeitpunkt ausgefuehrt: Empfaenger ermitteln und E-Mail verschicken.
	 */
	public function send_for_order_product( $order_id, $product_id ) {
		if ( ! get_option( 'fgr_rr_enabled', true ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Stornierte/rueckerstattete Bestellungen ueberspringen.
		if ( in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			return;
		}

		$recipients = $this->get_recipients( $order_id, $product_id );

		// Fallback: falls aus irgendeinem Grund kein Ticket gefunden wurde, trotzdem
		// an die Bestell-E-Mail schicken, statt stillschweigend nichts zu tun.
		if ( empty( $recipients ) ) {
			$billing_email = $order->get_billing_email();
			if ( $billing_email && is_email( $billing_email ) ) {
				$recipients[ strtolower( $billing_email ) ] = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			}
		}

		if ( empty( $recipients ) ) {
			return;
		}

		$product_name = get_the_title( $product_id );
		$link         = get_option( 'fgr_rr_review_link', 'https://share.google/t8D4vj9l1kKnVnw8Z' );
		$subject_tpl  = get_option( 'fgr_rr_subject', 'Wie hat Ihnen der Kochkurs bei PINTO gefallen?' );
		$body_tpl     = get_option( 'fgr_rr_body', FGR_RR_Settings::default_body() );

		foreach ( $recipients as $email => $name ) {
			$vars = array(
				'{name}' => '' !== $name ? $name : 'liebe Kundin, lieber Kunde',
				'{kurs}' => $product_name,
				'{link}' => $link,
			);
			$subject = strtr( $subject_tpl, $vars );
			$body    = strtr( $body_tpl, $vars );

			wp_mail( $email, $subject, $body );
		}
	}
}
