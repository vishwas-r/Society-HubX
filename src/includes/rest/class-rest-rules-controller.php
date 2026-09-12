<?php
/**
 * Class: REST Rules Controller
 * Endpoints for Society Rules, Version Control, Acknowledgments, and Violations.
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_REST_Rules_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'rules';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// Rules CRUD
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'rules_manage_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/pending-acknowledgments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_pending_acknowledgments' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/violations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_violations' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'report_violation' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/violations/(?P<id>[\w-]+)/appeal',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'appeal_violation' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/violations/(?P<id>[\w-]+)/resolve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'resolve_violation' ),
					'permission_callback' => array( $this, 'rules_manage_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/violations/(?P<id>[\w-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_violation' ),
					'permission_callback' => array( $this, 'rules_manage_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'rules_manage_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'rules_manage_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)/acknowledge',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'acknowledge_rule' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);
	}

	/**
	 * Get published rules.
	 */
	public function get_items( $request ) {
		$db = new NAMMASOCIETY51_DB_Router();
		$rules = $db->get( 'rules' );

		$user_id = get_current_user_id();
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		$is_admin = $rbac->has_capability( $user_id, 'rules_manage' ) || current_user_can( 'manage_options' );

		if ( ! $is_admin ) {
			$rules = array_filter(
				$rules,
				function( $r ) {
					return isset( $r['status'] ) && $r['status'] === 'published';
				}
			);
			$rules = array_values( $rules );
		}

		return rest_ensure_response( $rules ? $rules : array() );
	}

	/**
	 * Get single rule with version info.
	 */
	public function get_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$rules = $db->get( 'rules', array( 'id' => $id ) );

		if ( empty( $rules ) ) {
			return new WP_Error( 'rest_rule_not_found', __( 'Rule not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $rules[0] );
	}

	/**
	 * Create rule.
	 */
	public function create_item( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$title   = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
		$content = isset( $params['content'] ) ? wp_kses_post( $params['content'] ) : '';

		if ( empty( $title ) || empty( $content ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Title and content are required.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$data = array(
			'id'                      => uniqid( 'rule_' ),
			'title'                   => $title,
			'slug'                    => isset( $params['slug'] ) ? sanitize_title( $params['slug'] ) : sanitize_title( $title ),
			'content'                 => $content,
			'category'                => isset( $params['category'] ) ? sanitize_text_field( $params['category'] ) : 'general',
			'priority'                => isset( $params['priority'] ) ? sanitize_text_field( $params['priority'] ) : 'medium',
			'tags'                    => isset( $params['tags'] ) ? sanitize_text_field( $params['tags'] ) : '',
			'status'                  => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'published',
			'fine_amount'             => isset( $params['fine_amount'] ) ? floatval( $params['fine_amount'] ) : 0.00,
			'requires_acknowledgment' => isset( $params['requires_acknowledgment'] ) ? intval( $params['requires_acknowledgment'] ) : 0,
			'created_at'              => current_time( 'mysql' ),
			'created_by'              => get_current_user_id(),
		);

		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->insert( 'rules', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $data['id'], 'rule' => $data ), 201 );
	}

	/**
	 * Update rule.
	 */
	public function update_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$db = new NAMMASOCIETY51_DB_Router();
		$existing = $db->get( 'rules', array( 'id' => $id ) );
		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_rule_not_found', __( 'Rule not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		$data = array();
		if ( isset( $params['title'] ) ) {
			$data['title'] = sanitize_text_field( $params['title'] );
		}
		if ( isset( $params['content'] ) ) {
			$data['content'] = wp_kses_post( $params['content'] );
		}
		if ( isset( $params['category'] ) ) {
			$data['category'] = sanitize_text_field( $params['category'] );
		}
		if ( isset( $params['status'] ) ) {
			$data['status'] = sanitize_text_field( $params['status'] );
		}
		if ( isset( $params['fine_amount'] ) ) {
			$data['fine_amount'] = floatval( $params['fine_amount'] );
		}

		$result = $db->update( 'rules', $data, array( 'id' => $id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Rule updated successfully.', 'namma-society' ) ) );
	}

	/**
	 * Delete / Archive rule.
	 */
	public function delete_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->update( 'rules', array( 'status' => 'archived' ), array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Rule archived successfully.', 'namma-society' ) ) );
	}

	/**
	 * Record digital signature acknowledgment.
	 */
	public function acknowledge_rule( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();

		$user_id = get_current_user_id();
		$db = new NAMMASOCIETY51_DB_Router();
		$resident = $db->get_resident_by_wp_id( $user_id );
		$flat_no = $resident['flat_no'] ?? '';

		$data = array(
			'rule_id'            => $id,
			'resident_id'        => $resident['id'] ?? $user_id,
			'flat_no'            => $flat_no,
			'user_id'            => $user_id,
			'acknowledged_at'    => current_time( 'mysql' ),
			'signature_data'     => isset( $params['signature'] ) ? sanitize_text_field( $params['signature'] ) : 'Digital Acknowledgment',
			'ip_address'         => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
			'user_agent'         => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
		);

		$result = $db->insert( 'rule_acknowledgments', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Rule acknowledged successfully.', 'namma-society' ) ) );
	}

	/**
	 * Get pending acknowledgments for the logged in resident.
	 */
	public function get_pending_acknowledgments( $request ) {
		$user_id = get_current_user_id();
		$db = new NAMMASOCIETY51_DB_Router();
		$rules = $db->get( 'rules', array( 'where' => array( 'requires_acknowledgment' => 1, 'status' => 'published' ) ) );
		$acks = $db->get( 'rule_acknowledgments', array( 'where' => array( 'user_id' => $user_id ) ) );

		$acked_rule_ids = array_column( $acks, 'rule_id' );
		$pending = array();

		foreach ( $rules as $r ) {
			if ( ! in_array( $r['id'], $acked_rule_ids, true ) ) {
				$pending[] = $r;
			}
		}

		return rest_ensure_response( $pending );
	}

	/**
	 * List violations.
	 */
	public function get_violations( $request ) {
		$db = new NAMMASOCIETY51_DB_Router();
		$violations = $db->get( 'rule_violations' );

		$user_id = get_current_user_id();
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		$is_admin = $rbac->has_capability( $user_id, 'rules_manage' ) || current_user_can( 'manage_options' );

		if ( ! $is_admin ) {
			$resident = $db->get_resident_by_wp_id( $user_id );
			$user_flat = $resident['flat_no'] ?? '';
			$violations = array_filter(
				$violations,
				function( $v ) use ( $user_flat, $user_id ) {
					return ( isset( $v['flat_no'] ) && $v['flat_no'] === $user_flat ) || ( isset( $v['reported_by'] ) && (int) $v['reported_by'] === $user_id );
				}
			);
			$violations = array_values( $violations );
		}

		return rest_ensure_response( $violations ? $violations : array() );
	}

	/**
	 * Report a rule violation.
	 */
	public function report_violation( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$rule_id = isset( $params['rule_id'] ) ? sanitize_text_field( $params['rule_id'] ) : '';
		$flat_no = isset( $params['flat_no'] ) ? sanitize_text_field( $params['flat_no'] ) : '';

		if ( empty( $rule_id ) || empty( $flat_no ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Rule ID and flat number are required.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$data = array(
			'id'           => uniqid( 'viol_' ),
			'rule_id'      => $rule_id,
			'flat_no'      => $flat_no,
			'description'  => isset( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : '',
			'photo_url'    => isset( $params['photo_url'] ) ? esc_url_raw( $params['photo_url'] ) : '',
			'fine_amount'  => isset( $params['fine_amount'] ) ? floatval( $params['fine_amount'] ) : 0.00,
			'status'       => 'pending',
			'reported_by'  => get_current_user_id(),
			'created_at'   => current_time( 'mysql' ),
		);

		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->insert( 'rule_violations', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $data['id'], 'violation' => $data ), 201 );
	}

	/**
	 * Submit violation appeal.
	 */
	public function appeal_violation( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		$reason = isset( $params['appeal_reason'] ) ? sanitize_textarea_field( $params['appeal_reason'] ) : '';

		if ( empty( $reason ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Appeal reason is required.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->update(
			'rule_violations',
			array(
				'status'        => 'appealed',
				'appeal_reason' => $reason,
				'appealed_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Appeal submitted successfully.', 'namma-society' ) ) );
	}

	/**
	 * Resolve violation.
	 */
	public function resolve_violation( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		$status = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'resolved';

		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->update(
			'rule_violations',
			array(
				'status'      => $status,
				'resolved_at' => current_time( 'mysql' ),
				'resolved_by' => get_current_user_id(),
			),
			array( 'id' => $id )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Violation status updated.', 'namma-society' ) ) );
	}

	/**
	 * Delete violation.
	 */
	public function delete_violation( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->delete( 'rule_violations', array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Violation record deleted.', 'namma-society' ) ) );
	}

	public function user_logged_in_check( $request ) {
		return NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
	}

	public function rules_manage_check( $request ) {
		NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		return $rbac->has_capability( get_current_user_id(), 'rules_manage' ) || current_user_can( 'manage_options' );
	}
}

