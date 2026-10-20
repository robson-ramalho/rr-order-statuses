<?php
/**
 * Plugin Name:       RR Order Statuses
 * Plugin URI:        https://github.com/robson-ramalho/rr-order-statuses
 * Description:       Create, rename and remove custom WooCommerce order statuses from one simple screen. Free, no add-ons.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Robson Ramalho de Oliveira
 * Author URI:        https://github.com/robson-ramalho
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rr-order-statuses
 * Domain Path:       /languages
 *
 * @package RR_Order_Statuses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RROS_VERSION', '1.0.0' );
define( 'RROS_OPTION', 'rros_statuses' );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'rr-order-statuses', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	1
);

/**
 * Slugs WooCommerce and WordPress already use.
 *
 * @return string[]
 */
function rros_core_slugs() {
	return array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed', 'checkout-draft', 'trash', 'draft', 'auto-draft', 'publish', 'private', 'future', 'inherit' );
}

/**
 * Saved statuses: list of array( slug, label, paid ). Slugs have no "wc-" prefix.
 * The first time, statuses are imported so existing orders keep their status.
 *
 * @return array[]
 */
function rros_rows() {
	$rows = get_option( RROS_OPTION, null );
	if ( is_array( $rows ) ) {
		return $rows;
	}
	$rows = rros_import_rows();
	update_option( RROS_OPTION, $rows, true );
	return $rows;
}

/**
 * Statuses to start with. Reads WooCommerce Order Status Manager data if it is
 * still in the database; other plugins can provide theirs with the filter.
 *
 * @return array[]
 */
function rros_import_rows() {
	$rows  = array();
	$posts = get_posts(
		array(
			'post_type'   => 'wc_order_status',
			'numberposts' => -1,
			'post_status' => 'any',
		)
	);
	foreach ( $posts as $post ) {
		$slug = $post->post_name;
		if ( '' === $slug || in_array( $slug, rros_core_slugs(), true ) ) {
			continue;
		}
		$rows[] = array(
			'slug'  => substr( $slug, 0, 17 ),
			'label' => $post->post_title,
			'paid'  => 'yes' === get_post_meta( $post->ID, '_is_paid', true ) ? 1 : 0,
		);
	}
	/**
	 * Filters the statuses created on first run.
	 *
	 * @param array[] $rows Each row: array( 'slug' => string, 'label' => string, 'paid' => 0|1 ).
	 */
	return (array) apply_filters( 'rros_initial_statuses', $rows );
}

/**
 * Statuses keyed by WooCommerce slug ("wc-...").
 *
 * @return string[]
 */
function rros_statuses() {
	$out = array();
	foreach ( rros_rows() as $row ) {
		$out[ 'wc-' . $row['slug'] ] = $row['label'];
	}
	return $out;
}

/**
 * Registers each status as a post status (needed by the legacy orders table).
 */
function rros_register() {
	foreach ( rros_statuses() as $slug => $label ) {
		if ( get_post_status_object( $slug ) ) {
			continue;
		}
		$count = $label . ' <span class="count">(%s)</span>';
		register_post_status(
			$slug,
			array(
				'label'                     => $label,
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => array(
					0          => $count,
					1          => $count,
					'singular' => $count,
					'plural'   => $count,
					'context'  => null,
					'domain'   => null,
				),
			)
		);
	}
}
add_action( 'init', 'rros_register', 20 );

/**
 * Adds the statuses after "Processing", skipping any another plugin already added.
 *
 * @param string[] $statuses WooCommerce statuses.
 * @return string[]
 */
function rros_wc_order_statuses( $statuses ) {
	$extra = array_diff_key( rros_statuses(), $statuses );
	if ( ! $extra ) {
		return $statuses;
	}
	$out = array();
	foreach ( $statuses as $slug => $label ) {
		$out[ $slug ] = $label;
		if ( 'wc-processing' === $slug ) {
			$out += $extra;
		}
	}
	return $out + $extra;
}
add_filter( 'wc_order_statuses', 'rros_wc_order_statuses' );

/**
 * Statuses marked as paid count as paid orders and in reports.
 *
 * @param string[] $statuses Paid statuses, no prefix.
 * @return string[]
 */
function rros_paid_statuses( $statuses ) {
	foreach ( rros_rows() as $row ) {
		if ( ! empty( $row['paid'] ) ) {
			$statuses[] = $row['slug'];
		}
	}
	return array_values( array_unique( $statuses ) );
}
add_filter( 'woocommerce_order_is_paid_statuses', 'rros_paid_statuses' );
add_filter( 'woocommerce_reports_order_statuses', 'rros_paid_statuses' );

/**
 * "Change status to ..." bulk actions on both orders screens.
 *
 * @param string[] $actions Bulk actions.
 * @return string[]
 */
function rros_bulk_actions( $actions ) {
	foreach ( rros_statuses() as $slug => $label ) {
		/* translators: %s: order status name */
		$actions[ 'mark_' . substr( $slug, 3 ) ] = sprintf( __( 'Change status to %s', 'rr-order-statuses' ), $label );
	}
	return $actions;
}
add_filter( 'bulk_actions-edit-shop_order', 'rros_bulk_actions', 20 );
add_filter( 'bulk_actions-woocommerce_page_wc-orders', 'rros_bulk_actions', 20 );

/**
 * WooCommerce > Order statuses.
 */
function rros_menu() {
	add_submenu_page( 'woocommerce', __( 'Order statuses', 'rr-order-statuses' ), __( 'Order statuses', 'rr-order-statuses' ), 'manage_woocommerce', 'rr-order-statuses', 'rros_page' );
}
add_action( 'admin_menu', 'rros_menu', 60 );

/**
 * Settings link on the Plugins screen.
 *
 * @param string[] $links Plugin action links.
 * @return string[]
 */
function rros_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=rr-order-statuses' ) ) . '">' . esc_html__( 'Settings', 'rr-order-statuses' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'rros_action_links' );

/**
 * Registers the option saved by the screen.
 */
function rros_register_setting() {
	register_setting(
		'rros_group',
		RROS_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'rros_sanitize',
			'default'           => null,
		)
	);
}
add_action( 'admin_init', 'rros_register_setting' );

/**
 * Shop managers can save the screen, not only administrators.
 *
 * @return string
 */
function rros_option_capability() {
	return 'manage_woocommerce';
}
add_filter( 'option_page_capability_rros_group', 'rros_option_capability' );

/**
 * Number of orders in a status.
 *
 * @param string $slug Status slug, no prefix.
 * @return int
 */
function rros_count( $slug ) {
	return function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( $slug ) : 0;
}

/**
 * Validates the screen. A status that still has orders is never removed.
 *
 * @param mixed $input Posted rows.
 * @return array[]
 */
function rros_sanitize( $input ) {
	$out  = array();
	$seen = array();
	foreach ( (array) $input as $row ) {
		$row   = (array) $row;
		$label = sanitize_text_field( $row['label'] ?? '' );
		$slug  = sanitize_title( ( $row['slug'] ?? '' ) !== '' ? $row['slug'] : $label );
		$slug  = rtrim( substr( $slug, 0, 17 ), '-' ); // Post status names allow 20 characters, including "wc-".
		if ( '' === $label || '' === $slug ) {
			continue;
		}
		if ( in_array( $slug, rros_core_slugs(), true ) || isset( $seen[ $slug ] ) ) {
			/* translators: %s: status slug */
			add_settings_error( RROS_OPTION, 'rros-' . $slug, sprintf( __( 'The slug "%s" is already in use and was skipped.', 'rr-order-statuses' ), $slug ) );
			continue;
		}
		$seen[ $slug ] = true;
		$out[]         = array(
			'slug'  => $slug,
			'label' => $label,
			'paid'  => empty( $row['paid'] ) ? 0 : 1,
		);
	}
	foreach ( rros_rows() as $old ) {
		if ( isset( $seen[ $old['slug'] ] ) ) {
			continue;
		}
		$count = rros_count( $old['slug'] );
		if ( $count > 0 ) {
			$out[] = $old;
			/* translators: 1: status name, 2: number of orders */
			add_settings_error( RROS_OPTION, 'rros-keep-' . $old['slug'], sprintf( __( '"%1$s" still has %2$d order(s) and was not removed. Change those orders to another status first.', 'rr-order-statuses' ), $old['label'], $count ) );
		}
	}
	return $out;
}

/**
 * The screen.
 */
function rros_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$rows   = rros_rows();
	$rows[] = array(
		'slug'  => '',
		'label' => '',
		'paid'  => 1,
	);
	$name   = RROS_OPTION;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Order statuses', 'rr-order-statuses' ); ?></h1>
		<p><?php esc_html_e( 'Your own statuses, in addition to the WooCommerce defaults. To add one, fill in the last row. To remove one, clear its name. The slug never changes once created, because orders store it.', 'rr-order-statuses' ); ?></p>
		<?php settings_errors( RROS_OPTION ); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'rros_group' ); ?>
			<table class="widefat striped" style="max-width:760px">
				<thead><tr>
					<th><?php esc_html_e( 'Name', 'rr-order-statuses' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'rr-order-statuses' ); ?></th>
					<th><?php esc_html_e( 'Counts as paid', 'rr-order-statuses' ); ?></th>
					<th><?php esc_html_e( 'Orders', 'rr-order-statuses' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $i => $row ) : ?>
					<?php $is_new = '' === $row['slug']; ?>
					<tr>
						<td><input class="regular-text" type="text" name="<?php echo esc_attr( "{$name}[{$i}][label]" ); ?>" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php echo $is_new ? esc_attr__( 'New status', 'rr-order-statuses' ) : ''; ?>"></td>
						<td>
							<?php if ( $is_new ) : ?>
								<input type="text" maxlength="17" name="<?php echo esc_attr( "{$name}[{$i}][slug]" ); ?>" placeholder="<?php esc_attr_e( 'automatic', 'rr-order-statuses' ); ?>">
							<?php else : ?>
								<code><?php echo esc_html( $row['slug'] ); ?></code>
								<input type="hidden" name="<?php echo esc_attr( "{$name}[{$i}][slug]" ); ?>" value="<?php echo esc_attr( $row['slug'] ); ?>">
							<?php endif; ?>
						</td>
						<td><input type="checkbox" value="1" name="<?php echo esc_attr( "{$name}[{$i}][paid]" ); ?>" <?php checked( ! empty( $row['paid'] ) ); ?>></td>
						<td><?php echo $is_new ? '' : esc_html( (string) rros_count( $row['slug'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
