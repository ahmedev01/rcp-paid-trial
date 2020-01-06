<?php
/*
  Plugin Name: Restrict Content Pro - Paid Trial Add-on
  Plugin URI: https://ahmedev.com/
  Description: Add the possibility to make a paid trial period.
  Author: Ahmed Benali
  Author URI: https://ahmedev.com/
  Version: 1.0.0
  Text Domain: paid-trial
  Domain Path: /languages/
  Copyright 2019 Ahmedev (http://ahmedev.com/)
 */



if ( !defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly



if ( !class_exists( 'RCP_paid_trial' ) ) {



	class RCP_paid_trial {

		var $version = '1.0.0';
		var $title = 'Restrict Content Pro - paid Trial Add-on';
		var $name = 'rcp-paid-trial';
		var $dir_name = 'rcp-paid-trial';
		var $location = 'plugins';
		var $plugin_dir = '';
		var $plugin_url = '';

		function __construct() {

			$this->init_vars();

            add_action('plugins_loaded', array(&$this,'update_databases'));
            add_action( 'plugins_loaded', array(&$this, 'paid_trial_load_plugin_textdomain'), 0);
            add_action( 'rcp_add_subscription_form', array(&$this, 'rcppt_add_paid_trial_subscription_form') );
            add_action( 'rcp_edit_subscription_form', array(&$this, 'rcppt_edit_paid_trial_subscription_form') );
            add_action( 'rcp_edit_subscription_level', array(&$this, 'rcppt_edit_trial_price_to_level'),10,2 );
            add_action( 'rcp_add_subscription', array(&$this, 'rcppt_add_trial_price_to_level'),10,2 );
            add_filter('rcp_registration_get_total_trial', array(&$this, 'rcppt_add_paid_trial_to_registration_total'),10,2);
            add_filter('rcp_registration_total', array(&$this, 'rcppt_registration_total_trial'),10,1);
            add_filter('rcp_change_amount_trial', array(&$this, 'rcppt_change_trial_amount'),10,1);


		}

		function init_vars() {

            if ( defined( 'WP_PLUGIN_URL' ) && defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/' . $this->dir_name . '/' . basename( __FILE__ ) ) ) {

				$this->location = 'subfolder-plugins';

				$this->plugin_dir = WP_PLUGIN_DIR . '/' . $this->dir_name . '/';

				$this->plugin_url = plugins_url( '/', __FILE__ );
			} else if ( defined( 'WP_PLUGIN_URL' ) && defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/' . basename( __FILE__ ) ) ) {

				$this->location = 'plugins';

				$this->plugin_dir = WP_PLUGIN_DIR . '/';

				$this->plugin_url = plugins_url( '/', __FILE__ );
			} else if ( is_multisite() && defined( 'WPMU_PLUGIN_URL' ) && defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/' . basename( __FILE__ ) ) ) {

				$this->location = 'mu-plugins';

				$this->plugin_dir = WPMU_PLUGIN_DIR;

				$this->plugin_url = WPMU_PLUGIN_URL;
			} else {

				wp_die( sprintf( __( 'There was an issue determining where %s is installed. Please reinstall it.', 'tss' ), $this->title ) );
			}
		}

        function paid_trial_load_plugin_textdomain() {
            load_plugin_textdomain( 'paid-trial', FALSE, dirname( plugin_basename( __FILE__ ) )  . '/languages/' );
        }

        function update_databases(){
            global $wpdb;
            $db_name   = rcp_get_levels_db_name();

            $rcpptprice = $wpdb->get_row( "SELECT * FROM $db_name LIMIT 1" );

            if(!isset($rcpptprice->rcppt_price)) {
                $wpdb->query( "ALTER TABLE $db_name ADD rcppt_price VARCHAR(20) NOT NULL");
            }
        }

        function paid_trial_admin_notices() {
            if ( ! is_plugin_active( 'restrict-content-pro.php' ) ) {
                echo '<div class="error"><p>'.__('You need Restrict Content PRO activated in order to use RCP paid Trial','paid-trial').'</p></div>';
            }
        }


        function _rcppt_activate( $plugin, $network_wide ) {
            if ( ! is_plugin_active( 'restrict-content-pro.php' ) ) {
                $redirect = self_admin_url( 'plugins.php' );
                wp_redirect( $redirect );
                exit;
            }
        }

        function rcppt_add_paid_trial_subscription_form() {
        ?>
            <tr class="form-field">
                <th scope="row" valign="top">
                    <label for="rcppt_paid_trial"><?php _e( 'paid Trial Period', 'paid-trial' ); ?></label>
                </th>
                <td id="rcppt_paid_trial">
                    <input id="rcppt_paid_trial_price" type="text" name="rcppt_price" value="0" pattern="^(\d+\.\d{1,2})|(\d+)$" style="width:100px;"/>
                    <p class="description"><?php _e( 'Add price for the trial period, Add 0 for free.', 'paid-trial' ); ?></p>
                </td>
            </tr>
        <?php
        }

        function rcppt_edit_paid_trial_subscription_form($level) {
        ?>
            <tr class="form-field">
                <th scope="row" valign="top">
                    <label for="rcppt_paid_trial"><?php _e( 'paid Trial Period', 'paid-trial' ); ?></label>
                </th>
                <td id="rcppt_paid_trial">
                    <input id="rcppt_paid_trial_price" type="text" name="rcppt_price" value="<?php echo esc_attr( $level->rcppt_price ); ?>" pattern="^(\d+\.\d{1,2})|(\d+)$" style="width:100px;"/>
                    <p class="description"><?php _e( 'Add price for the trial period, Add 0 for free.', 'paid-trial' ); ?></p>
                </td>
            </tr>
        <?php
        }

        function rcppt_edit_trial_price_to_level($level_id, $args){
            global $wpdb;
            $db_name   = rcp_get_levels_db_name();

            if ( false === filter_var( $args['rcppt_price'], FILTER_VALIDATE_FLOAT ) || $args['rcppt_price'] < 0 ) {
            rcp_log( sprintf( 'Failed updating membership level #%d: invalid trial price ( %s ).', $level_id, $args['rcppt_price'] ), true );

            return new WP_Error( 'invalid_level_trial_price', __( 'Invalid trial price: the membership level trial price must be a valid positive number.', 'paid-trial' ) );
            }

            $update = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$db_name} SET
                    `rcppt_price`         = '%s'
                    WHERE `id`            = '%d'
                ;",
                sanitize_text_field( $args['rcppt_price'] ),
                absint( $args['id'] )
            )
        );
        }

        function rcppt_add_trial_price_to_level($level_id, $args){
            global $wpdb;
            $db_name   = rcp_get_levels_db_name();

            if ( false === filter_var( $args['rcppt_price'], FILTER_VALIDATE_FLOAT ) || $args['rcppt_price'] < 0 ) {
            rcp_log( sprintf( 'Failed adding membership level #%d: invalid trial price ( %s ).', $level_id, $args['rcppt_price'] ), true );

            return new WP_Error( 'invalid_level_trial_price', __( 'Invalid trial price: the membership level trial price must be a valid positive number.', 'paid-trial' ) );
            }

            $update = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$db_name} SET
                    `rcppt_price`         = '%s'
                    WHERE `id`            = '%d'
                ;",
                sanitize_text_field( $args['rcppt_price'] ),
                absint( $level_id )
            )
        );
        }

        function rcppt_add_paid_trial_to_registration_total($total, $registration_object){
            $price = $this->rcppt_get_subscription_trial_price($registration_object->get_membership_level_id());
            if ($registration_object->is_trial() && false != $price && 0 != $price) {
                $total = $price;
            }
            return $total;
        }

        function rcppt_get_subscription_trial_price( $id ) {
            $levels = new RCP_Levels();
            $price = $levels->get_level_field( $id, 'rcppt_price' );
            if( $price )
                return $price;
            return false;
        }

        function rcppt_registration_total_trial($total){
            global $rcp_levels_db;
			
            $level  = $rcp_levels_db->get_level( rcp_get_registration()->get_membership_level_id() );
            $trial_duration      = $rcp_levels_db->trial_duration( $level->id );
            $trial_duration_unit = $rcp_levels_db->trial_duration_unit( $level->id );
            $price  = $level->rcppt_price;
            if (! empty($price) && $price > 0){
                $total = sprintf( rcp_currency_filter($price).' - '.$trial_duration . ' ' . rcp_filter_duration_unit( $trial_duration_unit, $trial_duration ));
            }
            return $total;
        }

        function rcppt_change_trial_amount($amount){
            global $rcp_levels_db;
			
            $level  = $rcp_levels_db->get_level( rcp_get_registration()->get_membership_level_id() );
            $price  = $level->rcppt_price;
            if (! empty($price) && $price > 0 ){
                $amount = $price;
            }else{
                $amount = 0.00;
            }
            return $amount;
        }
    }
}
$RCP_paid_trial = new RCP_paid_trial();