<?php
/**
 * Class: REST Polls Controller
 * Endpoints for Digital Democracy (Society Polls & Voting).
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_REST_Polls_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'polls';

	/**
	 * Register routes.
	 */
	public function register_routes() {
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
					'permission_callback' => array( $this, 'polls_manage_check' ),
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
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'polls_manage_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)/vote',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'cast_vote' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)/close',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'close_poll' ),
					'permission_callback' => array( $this, 'polls_manage_check' ),
				),
			)
		);
	}

	/**
	 * List polls.
	 */
	public function get_items( $request ) {
		$db = new NAMMASOCIETY51_DB_Router();
		$polls = $db->get( 'polls' );

		if ( empty( $polls ) ) {
			return rest_ensure_response( array() );
		}

		return rest_ensure_response( $polls );
	}

	/**
	 * Get single poll with tally.
	 */
	public function get_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$polls = $db->get( 'polls', array( 'id' => $id ) );

		if ( empty( $polls ) ) {
			return new WP_Error( 'rest_poll_not_found', __( 'Poll not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		$poll = $polls[0];
		$all_votes = $db->get( 'votes', array( 'where' => array( 'poll_id' => $id ) ) );
		$options = is_string( $poll['options'] ) ? json_decode( $poll['options'], true ) : $poll['options'];
		$tally = array();
		if ( is_array( $options ) ) {
			foreach ( $options as $opt ) {
				$tally[ $opt ] = 0;
			}
		}

		$user_id = get_current_user_id();
		$user_voted = false;
		$user_vote_option = null;

		foreach ( $all_votes as $v ) {
			$opt = $v['option'] ?? '';
			if ( isset( $tally[ $opt ] ) ) {
				$tally[ $opt ]++;
			} else {
				$tally[ $opt ] = 1;
			}
			if ( intval( $v['user_id'] ?? 0 ) === $user_id ) {
				$user_voted = true;
				$user_vote_option = $opt;
			}
		}

		return rest_ensure_response(
			array(
				'poll'             => $poll,
				'total_votes'      => count( $all_votes ),
				'tally'            => $tally,
				'user_voted'       => $user_voted,
				'user_vote_option' => $user_vote_option,
			)
		);
	}

	/**
	 * Create poll.
	 */
	public function create_item( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$title   = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
		$options = isset( $params['options'] ) ? (array) $params['options'] : array();

		if ( empty( $title ) || count( $options ) < 2 ) {
			return new WP_Error( 'rest_invalid_params', __( 'Title and at least 2 options are required.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$clean_options = array_values( array_filter( array_map( 'sanitize_text_field', $options ) ) );

		$data = array(
			'id'          => uniqid( 'poll_' ),
			'title'       => $title,
			'description' => isset( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : '',
			'options'     => json_encode( $clean_options ),
			'expiry'      => isset( $params['expiry_date'] ) ? sanitize_text_field( $params['expiry_date'] ) : '',
			'status'      => 'open',
			'created_at'  => current_time( 'mysql' ),
			'created_by'  => get_current_user_id(),
		);

		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->insert( 'polls', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $data['id'], 'poll' => $data ), 201 );
	}

	/**
	 * Cast vote.
	 */
	public function cast_vote( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$option = isset( $params['option'] ) ? sanitize_text_field( $params['option'] ) : '';
		if ( empty( $option ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Vote option is required.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$db = new NAMMASOCIETY51_DB_Router();
		$polls = $db->get( 'polls', array( 'id' => $id ) );
		if ( empty( $polls ) ) {
			return new WP_Error( 'rest_poll_not_found', __( 'Poll not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		if ( ( $polls[0]['status'] ?? '' ) !== 'open' ) {
			return new WP_Error( 'rest_poll_closed', __( 'This poll is closed.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$user_id = get_current_user_id();
		$resident = $db->get_resident_by_wp_id( $user_id );
		$flat_no = $resident['flat_no'] ?? '';

		$votes = $db->get( 'votes', array( 'where' => array( 'poll_id' => $id, 'user_id' => $user_id ) ) );
		if ( ! empty( $votes ) ) {
			return new WP_Error( 'rest_already_voted', __( 'You have already voted on this poll.', 'namma-society' ), array( 'status' => 409 ) );
		}

		$vote_data = array(
			'poll_id'  => $id,
			'flat_no'  => $flat_no,
			'user_id'  => $user_id,
			'option'   => $option,
			'voted_at' => current_time( 'mysql' ),
		);

		$result = $db->insert( 'votes', $vote_data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Vote recorded successfully.', 'namma-society' ) ) );
	}

	/**
	 * Close poll.
	 */
	public function close_poll( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->update( 'polls', array( 'status' => 'closed' ), array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Poll closed successfully.', 'namma-society' ) ) );
	}

	/**
	 * Delete poll.
	 */
	public function delete_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->delete( 'polls', array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Poll deleted successfully.', 'namma-society' ) ) );
	}

	public function user_logged_in_check( $request ) {
		return NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
	}

	public function polls_manage_check( $request ) {
		NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		return $rbac->has_capability( get_current_user_id(), 'polls_manage' ) || current_user_can( 'manage_options' );
	}
}

// Backward Compatibility Aliases
if ( class_exists( 'NAMMASOCIETY51_REST_Polls_Controller' ) && ! class_exists( 'SHUBX51_REST_Polls_Controller', false ) ) {
	class_alias( 'NAMMASOCIETY51_REST_Polls_Controller', 'SHUBX51_REST_Polls_Controller' );
}
