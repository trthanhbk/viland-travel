<?php
/**
 * WooCommerce Tab Manager
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Tab Manager to newer
 * versions in the future. If you wish to customize WooCommerce Tab Manager for your
 * needs please refer to http://docs.woothemes.com/document/tab-manager/
 *
 * @package     WC-Tab-Manager/Admin
 * @author      SkyVerge
 * @copyright   Copyright (c) 2012-2016, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

/**
 * WooCommerce Tab Manager Write Panels
 *
 * Sets up the write panels added by the Tab Manager
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/** Product data Tab panel */
include_once( wc_tab_manager()->get_plugin_path() . '/admin/post-types/writepanels/writepanel-product_data-tabs.php' );

/** Product Tab Actions writepanel */
include_once( wc_tab_manager()->get_plugin_path() . '/admin/post-types/writepanels/writepanel-product-tab_actions.php' );


/**
 * Save meta boxes
 */
add_action( 'save_post_wc_product_tab', 'wc_tab_manager_meta_boxes_save', 1, 3 );

/**
 * Runs when a post is saved and does an action which the write panel save scripts can hook into.
 *
 * @access public
 * @param int $post_id post identifier
 * @param object $post post object
 */
function wc_tab_manager_meta_boxes_save( $post_id, $post, $is_update ) {
	$has_post_data  = ( ! empty( $post_id ) && ! empty( $post ) && ! empty( $_POST ) );
	$is_revision    = is_int( wp_is_post_revision( $post ) );
	$is_autosave    = is_int( wp_is_post_autosave( $post ) );
	$doing_autosave = ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE );
	$is_tab_update  = ( ! $is_revision && ! $is_autosave && ! $doing_autosave );
	$is_nonce_valid = ( ! empty( $_POST['woocommerce_meta_nonce'] ) && wp_verify_nonce( $_POST['woocommerce_meta_nonce'], 'woocommerce_save_data' ) );
	$user_can_edit  = current_user_can( 'edit_post', $post_id );

	if ( ! $has_post_data || ! $is_tab_update || ! $is_nonce_valid || ! $user_can_edit ) {
		return;
	}

	// Determine if the current tab should be included in search results.
	if ( isset( $_POST['tab_type'] ) && 'global' === $_POST['tab_type'] ) {
		if ( isset( $_POST['_include_in_search'] ) && 'yes' === $_POST['_include_in_search'] ) {
			$action = 'update';
			wc_tab_manager()->search->add_searchable_tab( $post_id );
		} else {
			$action = 'remove';
			wc_tab_manager()->search->remove_searchable_tab( $post_id );
		}

		$is_default_tab = wc_tab_manager()->search->is_default_tab( $post_id );
		$target = ( $is_default_tab ) ? 'default' : 'custom';

		$args = array(
			'action' => $action,
			'target' => $target,
		);
		wc_tab_manager()->search->update_products_for_tab( $post_id, $args );
	}

	do_action( 'woocommerce_process_wc_product_tab_meta', $post_id, $post );
}

// protect tab posts
add_action( 'publish_wc_product_tab', 'wc_tab_manager_protect_tab', 10, 2 );

/**
 * Automatically protect the product tab posts
 *
 * @access public
 * @param int $post_id the post tab identifier
 * @param object $post the post tab object
 */
function wc_tab_manager_protect_tab( $post_id, $post ) {
	global $wpdb;

	if ( ! $post->post_password ) {

		$wpdb->update( $wpdb->posts, array( 'post_password' => uniqid( 'tab_' ) ), array( 'ID' => $post_id ) );

	}
}


/**
 * Init the meta boxes.
 *
 * Inits the write panels for Product Tabs. Also removes unused default write panels.
 *
 * @access public
 */
function wc_tab_manager_meta_boxes() {

	add_meta_box( 'wc-tab-manager-product-tab-actions', __( 'Tab Actions', 'woocommerce-tab-manager' ), 'wc_tab_manager_product_tab_actions_meta_box', 'wc_product_tab', 'side', 'high' );
}

add_action( 'add_meta_boxes_wc_product_tab', 'wc_tab_manager_meta_boxes' );


/**
 * Remove any meta box that isn't whitelisted.
 *
 * @since 1.4.0
 * @param string  $post_type The current post type.
 * @param WP_Post $post      The current post object.
 */
function wc_tab_manager_remove_meta_boxes( $post_type, $post ) {

	if ( 'wc_product_tab' !== $post_type ) {
		return;
	}

	$screen = get_current_screen();

	$allowed_meta_box_ids = apply_filters( 'wc_tab_manager_allowed_meta_box_ids', array(
		'et_pb_layout', // Divi Builder
	) );

	foreach ( $GLOBALS['wp_meta_boxes'][ $screen->id ] as $context => $meta_boxes_by_context ) {
		foreach ( $meta_boxes_by_context as $subcontext => $meta_boxes_by_subcontext ) {
			foreach ( $meta_boxes_by_subcontext as $meta_box_id => $meta_box ) {
				if ( ! in_array( $meta_box_id, $allowed_meta_box_ids ) ) {
					remove_meta_box( $meta_box_id, $post_type, $context );
				}
			}
		}
	}
}

add_action( 'add_meta_boxes', 'wc_tab_manager_remove_meta_boxes', 30, 2 );
