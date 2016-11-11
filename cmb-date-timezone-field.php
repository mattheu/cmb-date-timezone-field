<?php
/**
 * Plugin Name:     CMB Date Timezone Field
 * Plugin URI:      https://github.com/mattheu/cmb-date-timezone-field
 * Description:     This field uses the timezone setting of the WordPress install to ensure it always stores a UTC time in the database.
 * Author:          Matthew Haines-Young
 * Author URI:      http://matth.eu
 * Text Domain:     cmb-date-timezone-field
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Cmb_Date_Timezone_Field
 */

/* Register the field */
add_filter( 'cmb_field_types', function( $fields ) {
	$fields['date_timezone'] = 'CMB_Date_Timezone_Field';
	return $fields;
} );

class CMB_Date_Timezone_Field extends CMB_Field {

	public function __construct() {

		$args = func_get_args();
		call_user_func_array( [ 'parent', '__construct' ], $args );

		$this->update_initial_values();
	}

	/**
	 * Get initial values.
	 * Join dates + timezones together and create date objects.
	 *
	 * @return null
	 */
	private function update_initial_values() {

		$values    = get_post_meta( get_the_ID(), $this->id );
		$timezones = get_post_meta( get_the_ID(), $this->id . '-tz' );

		foreach ( $values as $key => &$date ) {

			$tzstring = isset( $timezones[ $key ] ) ? $timezones[ $key ] : get_option( 'timezone_string' );
			$date     = date_create_from_format( $this->args['format'], $date, new DateTimezone( 'UTC' ) );

			if ( $date && $tzstring ) {
				$date->setTimezone( new DateTimezone( $tzstring ) );
			}
		}

		$this->values = $values;
		$this->value = reset( $this->values );

	}

	public function get_default_args() {
		return array_merge(
			parent::get_default_args(),
			[
				'date'            => true,
				'time'            => true,
				'format'          => 'Y-m-d H:i:s', // MYSQL date format. Any format is OK as long as it can be passed to strtotime.
				'select_timezone' => true,
			]
		);
	}

	public function enqueue_scripts() {

		parent::enqueue_scripts();

		wp_enqueue_style( 'cmb-jquery-ui', trailingslashit( CMB_URL ) . 'css/vendor/jquery-ui/jquery-ui.css' );
		wp_enqueue_style( 'cmb-new-date', plugins_url( 'cmb-date-timezone-field.css', __FILE__ ) );

		if ( $this->args['time'] ) {
			wp_enqueue_script( 'cmb-timepicker', trailingslashit( CMB_URL ) . 'js/jquery.timePicker.min.js', array( 'jquery', 'cmb-scripts' ) );
			wp_enqueue_script( 'cmb-new-date', plugins_url( 'cmb-date-timezone-field.js', __FILE__ ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker', 'cmb-timepicker', 'cmb-scripts' ) );
		} else {
			wp_enqueue_script( 'cmb-new-date', plugins_url( 'cmb-date-timezone-field.js', __FILE__ ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker', 'cmb-scripts' ) );
		}

	}

	public function html() {

		$date     = $this->value ? $this->value->format( 'Y-m-d' ) : '';
		$time     = $this->value ? $this->value->format( 'g:ia' ) : '';
		$tzstring = $this->value ? $this->value->getTimezone()->getName() : get_option( 'timezone_string' );

		echo '<div class="cmb-new-date-container">';

		if ( $this->args['date'] ) : ?>

			<div class="cmb-new-date-field cmb-new-date-field-date">

				<label <?php $this->for_attr( '-date' ); ?>><?php esc_html_e( 'Date' ) ?></label>

				<input
					type="text"
					data-alt-field="#<?php echo esc_attr( $this->get_the_id_attr('-alt-field') ); ?>"
					<?php $this->id_attr( '-date' ); ?>
					<?php $this->boolean_attr(); ?>
				/>

				<input
					type="hidden"
					value="<?php echo esc_attr( $date );?>"
					<?php $this->id_attr('-alt-field'); ?>
					<?php $this->boolean_attr(); ?>
					<?php $this->name_attr('[date]'); ?>
				/>

			</div>
		<?php

		endif;

		if ( $this->args['time'] ) : ?>

			<div class="cmb-new-date-field cmb-new-date-field-time">

				<label <?php $this->for_attr( '-time' ); ?>><?php esc_html_e('Time') ?></label>

				<input
					type="text"
					value="<?php echo esc_attr( $time );?>"
					<?php $this->id_attr( '-time' ); ?>
					<?php $this->boolean_attr(); ?>
					<?php $this->name_attr('[time]'); ?>
				/>

			</div>

		<?php endif; ?>

		<?php if ( $this->args['select_timezone'] ) : ?>

			<?php

			// Remove old Etc mappings. Fallback to gmt_offset.
			if ( false !== strpos( $tzstring,'Etc/GMT' ) ) {
				$tzstring = '';
			}

			if ( empty( $tzstring ) ) { // Create a UTC+- zone if no timezone string exists

				$current_offset  = get_option('gmt_offset');

				if ( 0 == $current_offset ) {
					$tzstring = 'UTC+0';
				} elseif ( $current_offset < 0 ) {
					$tzstring = 'UTC' . $current_offset;
				} else {
					$tzstring = 'UTC+' . $current_offset;
				}

			}

			?>

			<div class="cmb-new-date-field cmb-new-date-field-timezone">
				<label for="timezone_string"><?php esc_html_e( 'Timezone' ) ?></label>
				<select
					<?php $this->id_attr( '-tz' ); ?>
					<?php $this->name_attr( '[tz]' ); ?>
					>
					<?php echo wp_timezone_choice( $tzstring ); ?>
				</select>
			</div>

		<?php else : ?>
			<p class="cmb_metabox_description" style="margin: 10px 0 0 !important;">Dates are <?php echo esc_html( get_option('timezone_string') ); ?>.</p>
		<?php endif;

		echo '</div>';

	}

	/**
	 * Parse Save Values
	 *
	 * Convert all [date] and [time] values to a unix timestamp.
	 * Then handle GMT offset if required.
	 * Then convert to desired format.
	 * If date is empty, assume delete. If time is empty, assume 00:00.
	 *
	 * @return null
	 */
	function parse_save_values() {

		$this->values = array_map( [ $this, 'parse_value_to_date' ], (array) $this->get_values() );
		$this->values = array_filter( $this->values );
		sort( $this->values );

		parent::parse_save_values();
	}

	/**
	 * Parse single value.
	 *
	 * @param  array $value Value
	 * @return array Value
	 */
	function parse_value_to_date( $value = null ) {

		if ( empty( $value ) || empty( $value['date'] ) ) {
			return;
		}

		// Ensure time is set. Default to midnight.
		if ( ! isset( $value['time'] ) ) {
			$value['time'] = '0:00am';
		}

		if ( isset( $value['tz'] ) ) {
			$tz = new DateTimeZone( $value['tz'] );
		} else {
			$tz = new DateTimeZone( get_option( 'timezone_string' ) );
		}

		return date_create_from_format( 'Y-m-d g:ia', sprintf( '%s %s', $value['date'], $value['time'] ), $tz );

	}

	function save( $post_id, $values ) {

		// Don't save readonly values.
		if ( $this->args['readonly'] ) {
			return;
		}

		$this->values = $values;
		$this->parse_save_values();

		if ( ! empty( $this->args['save_callback'] ) ) {
			call_user_func( $this->args['save_callback'], $this->values, $post_id );
			return;
		}

		// If we are not on a post edit screen
		if ( ! $post_id ) {
			return;
		}

		delete_post_meta( $post_id, $this->id );
		delete_post_meta( $post_id, $this->id . '-tz' );

		foreach ( $this->values as $date ) {

			// Store timezone.
			add_post_meta( $post_id, $this->id . '-tz', $date->getTimezone()->getName() );

			// Store formatted date (UTC).
			$date->setTimezone( new DateTimeZone( 'UTC' ) );
			add_post_meta( $post_id, $this->id, $date->format( $this->args['format'] ) );

		}

	}

}
