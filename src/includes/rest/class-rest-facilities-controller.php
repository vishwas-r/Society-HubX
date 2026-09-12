<?php
/**
 * Class: REST Facilities Controller
 * Endpoints for managing society facilities and slot bookings.
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_REST_Facilities_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the API.
	 *
	 * @var string
	 */
	protected $namespace = 'namma-society/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'facilities';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// Facilities Routes
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_facilities' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_facility' ),
					'permission_callback' => array( $this, 'manage_facility_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_facility' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_facility' ),
					'permission_callback' => array( $this, 'manage_facility_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_facility' ),
					'permission_callback' => array( $this, 'manage_facility_permissions_check' ),
				),
			)
		);

		// Bookings Routes
		register_rest_route(
			$this->namespace,
			'/bookings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_bookings' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_booking' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/bookings/(?P<id>[\w-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_booking' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_booking' ),
					'permission_callback' => array( $this, 'manage_facility_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'cancel_booking' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);
	}

	/**
	 * List facilities.
	 */
	public function get_facilities( $request ) {
		$db = new NAMMASOCIETY51_DB_Router();
		$facilities = $db->get( 'facilities' );
		return rest_ensure_response( $facilities ? $facilities : array() );
	}

	/**
	 * Get single facility.
	 */
	public function get_facility( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$facilities = $db->get( 'facilities', array( 'id' => $id ) );

		if ( empty( $facilities ) ) {
			return new WP_Error( 'rest_facility_not_found', __( 'Facility not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $facilities[0] );
	}

	/**
	 * Create facility.
	 */
	public function create_facility( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$name = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
		if ( empty( $name ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Facility name is required.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$data = array(
			'id'               => sanitize_title( $name ) . '-' . uniqid(),
			'name'             => $name,
			'rate'             => isset( $params['rate'] ) ? floatval( $params['rate'] ) : ( isset( $params['hourly_rate'] ) ? floatval( $params['hourly_rate'] ) : 0.00 ),
			'rate_unit'        => isset( $params['rate_unit'] ) ? sanitize_text_field( $params['rate_unit'] ) : 'Hour',
			'max_hours'        => isset( $params['max_hours'] ) ? intval( $params['max_hours'] ) : 0,
			'booking_required' => isset( $params['booking_required'] ) ? intval( $params['booking_required'] ) : 1,
			'rules'            => isset( $params['rules'] ) ? sanitize_textarea_field( $params['rules'] ) : '',
			'status'           => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'active',
			'created_at'       => current_time( 'mysql' ),
		);

		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->insert( 'facilities', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $data['id'], 'facility' => $data ), 201 );
	}

	/**
	 * Update facility.
	 */
	public function update_facility( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$db = new NAMMASOCIETY51_DB_Router();
		$existing = $db->get( 'facilities', array( 'id' => $id ) );
		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_facility_not_found', __( 'Facility not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		$data = array();
		if ( isset( $params['name'] ) ) {
			$data['name'] = sanitize_text_field( $params['name'] );
		}
		if ( isset( $params['rate'] ) ) {
			$data['rate'] = floatval( $params['rate'] );
		} elseif ( isset( $params['hourly_rate'] ) ) {
			$data['rate'] = floatval( $params['hourly_rate'] );
		}
		if ( isset( $params['rules'] ) ) {
			$data['rules'] = sanitize_textarea_field( $params['rules'] );
		}
		if ( isset( $params['status'] ) ) {
			$data['status'] = sanitize_text_field( $params['status'] );
		}

		$result = $db->update( 'facilities', $data, array( 'id' => $id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Facility updated successfully.', 'namma-society' ) ) );
	}

	/**
	 * Delete facility.
	 */
	public function delete_facility( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->delete( 'facilities', array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Facility deleted successfully.', 'namma-society' ) ) );
	}

	/**
	 * Get Bookings.
	 */
	public function get_bookings( $request ) {
		$db = new NAMMASOCIETY51_DB_Router();
		$bookings = $db->get( 'bookings' );

		$user_id = get_current_user_id();
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		$is_admin = $rbac->has_capability( $user_id, 'facilities_manage' ) || current_user_can( 'manage_options' );

		if ( ! $is_admin ) {
			$resident = $db->get_resident_by_wp_id( $user_id );
			$user_flat = $resident['flat_no'] ?? '';
			$resident_id = $resident['id'] ?? '';
			$bookings = array_filter(
				$bookings,
				function( $b ) use ( $user_flat, $resident_id ) {
					return ( isset( $b['flat_no'] ) && $b['flat_no'] === $user_flat ) || ( isset( $b['resident_id'] ) && $b['resident_id'] === $resident_id );
				}
			);
			$bookings = array_values( $bookings );
		}

		return rest_ensure_response( $bookings ? $bookings : array() );
	}

	/**
	 * Get single booking.
	 */
	public function get_booking( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$bookings = $db->get( 'bookings', array( 'id' => $id ) );

		if ( empty( $bookings ) ) {
			return new WP_Error( 'rest_booking_not_found', __( 'Booking not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $bookings[0] );
	}

	/**
	 * Create slot booking.
	 */
	public function create_booking( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$facility_id = isset( $params['facility_id'] ) ? sanitize_text_field( $params['facility_id'] ) : '';
		$start_time  = isset( $params['start_time'] ) ? sanitize_text_field( $params['start_time'] ) : '';
		$end_time    = isset( $params['end_time'] ) ? sanitize_text_field( $params['end_time'] ) : '';

		if ( empty( $facility_id ) || empty( $start_time ) || empty( $end_time ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Facility, start time, and end time are required.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$db = new NAMMASOCIETY51_DB_Router();

		// Check overlap
		$all_bookings = $db->get( 'bookings', array( 'where' => array( 'facility_id' => $facility_id ) ) );
		$start_ts = strtotime( $start_time );
		$end_ts   = strtotime( $end_time );

		foreach ( $all_bookings as $b ) {
			if ( ( $b['status'] ?? '' ) === 'cancelled' ) {
				continue;
			}
			$b_start = strtotime( $b['start_time'] );
			$b_end   = strtotime( $b['end_time'] );
			if ( ( $start_ts < $b_end ) && ( $end_ts > $b_start ) ) {
				return new WP_Error( 'rest_slot_conflict', __( 'This facility slot is already booked for the selected time range.', 'namma-society' ), array( 'status' => 409 ) );
			}
		}

		$user_id = get_current_user_id();
		$resident = $db->get_resident_by_wp_id( $user_id );
		$flat_no = $resident['flat_no'] ?? ( isset( $params['flat_no'] ) ? sanitize_text_field( $params['flat_no'] ) : '' );
		$resident_id = $resident['id'] ?? ( isset( $params['resident_id'] ) ? sanitize_text_field( $params['resident_id'] ) : '' );

		// Calculate rate
		$facility_rows = $db->get( 'facilities', array( 'id' => $facility_id ) );
		$rate = ! empty( $facility_rows ) ? floatval( $facility_rows[0]['rate'] ?? 0 ) : 0;
		$duration_hours = max( 1, ceil( ( $end_ts - $start_ts ) / 3600 ) );
		$amount = $rate * $duration_hours;

		$data = array(
			'id'          => uniqid( 'bk_' ),
			'block'       => $resident['block'] ?? '',
			'flat_no'     => $flat_no,
			'facility_id' => $facility_id,
			'resident_id' => $resident_id,
			'start_time'  => $start_time,
			'end_time'    => $end_time,
			'status'      => 'confirmed',
			'amount'      => $amount,
			'created_at'  => current_time( 'mysql' ),
		);

		$result = $db->insert( 'bookings', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $data['id'], 'booking' => $data ), 201 );
	}

	/**
	 * Update booking.
	 */
	public function update_booking( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$db = new NAMMASOCIETY51_DB_Router();
		$existing = $db->get( 'bookings', array( 'id' => $id ) );
		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_booking_not_found', __( 'Booking not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		$data = array();
		if ( isset( $params['status'] ) ) {
			$data['status'] = sanitize_text_field( $params['status'] );
		}

		$result = $db->update( 'bookings', $data, array( 'id' => $id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Booking updated successfully.', 'namma-society' ) ) );
	}

	/**
	 * Cancel / Delete booking.
	 */
	public function cancel_booking( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$existing = $db->get( 'bookings', array( 'id' => $id ) );

		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_booking_not_found', __( 'Booking not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		$user_id = get_current_user_id();
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		$is_admin = $rbac->has_capability( $user_id, 'facilities_manage' ) || current_user_can( 'manage_options' );

		$resident = $db->get_resident_by_wp_id( $user_id );
		$resident_id = $resident['id'] ?? '';

		if ( ! $is_admin && ( $existing[0]['resident_id'] ?? '' ) !== $resident_id ) {
			return new WP_Error( 'rest_forbidden', __( 'Unauthorized to cancel this booking.', 'namma-society' ), array( 'status' => 403 ) );
		}

		$result = $db->update( 'bookings', array( 'status' => 'cancelled' ), array( 'id' => $id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Booking cancelled successfully.', 'namma-society' ) ) );
	}

	public function user_logged_in_check( $request ) {
		return NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
	}

	public function manage_facility_permissions_check( $request ) {
		NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		return $rbac->has_capability( get_current_user_id(), 'facilities_manage' ) || current_user_can( 'manage_options' );
	}
}

// Backward Compatibility Aliases
if ( class_exists( 'NAMMASOCIETY51_REST_Facilities_Controller' ) && ! class_exists( 'SHUBX51_REST_Facilities_Controller', false ) ) {
	class_alias( 'NAMMASOCIETY51_REST_Facilities_Controller', 'SHUBX51_REST_Facilities_Controller' );
}
