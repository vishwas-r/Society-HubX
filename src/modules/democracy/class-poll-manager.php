<?php
/**
 * Class: Poll Manager
 * Handles Digital Democracy (Polling & Voting).
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_Poll_Manager implements NAMMASOCIETY51_Module {

	private $db;

	public function __construct() {
		$this->db = new NAMMASOCIETY51_DB_Router();
        
        // Admin Actions (POST + AJAX)
		add_action( 'admin_post_nammasociety51_create_poll', array( $this, 'handle_create_poll' ) );
        add_action( 'admin_post_nammasociety51_delete_poll', array( $this, 'handle_delete_poll' ) );
        add_action( 'admin_post_nammasociety51_close_poll', array( $this, 'handle_close_poll' ) );

        add_action( 'wp_ajax_nammasociety51_create_poll', array( $this, 'handle_create_poll' ) );
        add_action( 'wp_ajax_nammasociety51_delete_poll', array( $this, 'handle_delete_poll' ) );
        add_action( 'wp_ajax_nammasociety51_close_poll', array( $this, 'handle_close_poll' ) );
        add_action( 'wp_ajax_nammasociety51_get_poll_results', array( $this, 'handle_get_poll_results' ) );

        // Frontend Actions
        add_action( 'admin_post_nammasociety51_cast_vote', array( $this, 'handle_cast_vote' ) );
        add_action( 'wp_ajax_nammasociety51_cast_vote', array( $this, 'handle_cast_vote' ) );

        // Register Module
        add_filter( 'nammasociety51_get_module_polls', array( $this, 'get_instance' ) );
	}

    public function get_instance() {
        return $this;
    }

    public function get_module_slug() {
        return 'polls';
    }

    /**
     * Handle incoming requests from Request Manager
     */
    public function execute_request( $action, $payload ) {
        $payload = (array) $payload;
        if ( $action === 'cast_vote' ) {
            $vote_data = array(
                'poll_id'    => $payload['poll_id'],
                'flat_no'    => $payload['flat_no'],
                'user_id'    => $payload['user_id'],
                'option'     => $payload['vote_option'],
                'voted_at'   => $payload['voted_at'] ?? current_time( 'mysql' )
            );

            return $this->db->insert('votes', $vote_data);
        }
        return new WP_Error( 'invalid_action', 'Unknown action' );
    }

	/**
	 * Create a new Poll.
	 */
	public function handle_create_poll() {
		if ( wp_doing_ajax() ) {
			$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'nammasociety51_poll_action' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_nonce' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_admin_nonce' ) ) {
				wp_send_json_error( array( 'message' => 'Nonce verification failed' ), 403 );
			}
		} else {
			if ( ! check_admin_referer( 'nammasociety51_poll_action' ) ) {
				wp_die( 'Security check failed' );
			}
		}

        $rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'polls_manage' ) && ! current_user_can( 'manage_options' ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
			}
			wp_die( 'Unauthorized' );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$desc = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$options = isset( $_POST['options'] ) ? array_filter( array_map( 'sanitize_text_field', wp_unslash( $_POST['options'] ) ) ) : array();
		$expiry = isset( $_POST['expiry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['expiry_date'] ) ) : '';

		if ( count( $options ) < 2 ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => 'At least 2 options are required.' ), 400 );
			}
			wp_die( 'At least 2 options are required.' );
		}

        $new_poll = array(
            'id'          => uniqid( 'poll_' ),
            'title'       => $title,
            'description' => $desc,
            'options'     => json_encode( array_values( $options ) ),
            'expiry'      => $expiry,
            'status'      => 'open',
            'created_at'  => current_time( 'mysql' ),
            'created_by'  => get_current_user_id()
        );

        $res = $this->db->insert( 'polls', $new_poll );

		if ( wp_doing_ajax() ) {
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ), 500 );
			}
			wp_send_json_success( array( 'message' => 'Poll created successfully', 'id' => $new_poll['id'] ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=nammasociety51-polls&created=1' ) );
		exit;
	}

    /**
     * Delete a Poll.
     */
    public function handle_delete_poll() {
		if ( wp_doing_ajax() ) {
			$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'nammasociety51_poll_action' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_nonce' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_admin_nonce' ) ) {
				wp_send_json_error( array( 'message' => 'Nonce verification failed' ), 403 );
			}
		} else {
			if ( ! check_admin_referer( 'nammasociety51_poll_action' ) ) {
				wp_die( 'Security check failed' );
			}
		}

        $rbac = new NAMMASOCIETY51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'polls_manage' ) && ! current_user_can( 'manage_options' ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
			}
			wp_die( 'Unauthorized' );
		}

        $id = isset( $_REQUEST['id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['id'] ) ) : ( isset( $_POST['poll_id'] ) ? sanitize_text_field( wp_unslash( $_POST['poll_id'] ) ) : '' );
        $res = $this->db->delete( 'polls', array( 'id' => $id ) );

        $votes = $this->db->get( 'votes' );
        foreach ( $votes as $v ) {
            if ( isset( $v['poll_id'], $v['id'] ) && $v['poll_id'] === $id ) {
                $this->db->delete( 'votes', array( 'id' => $v['id'] ) );
            }
        }

		if ( wp_doing_ajax() ) {
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ), 500 );
			}
			wp_send_json_success( array( 'message' => 'Poll deleted successfully' ) );
			exit;
		}

        wp_safe_redirect( admin_url( 'admin.php?page=nammasociety51-polls&deleted=1' ) );
        exit;
    }

    /**
     * Close a Poll manually.
     */
    public function handle_close_poll() {
		if ( wp_doing_ajax() ) {
			$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'nammasociety51_poll_action' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_nonce' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_admin_nonce' ) ) {
				wp_send_json_error( array( 'message' => 'Nonce verification failed' ), 403 );
			}
		} else {
			if ( ! check_admin_referer( 'nammasociety51_poll_action' ) ) {
				wp_die( 'Security check failed' );
			}
		}

        $rbac = new NAMMASOCIETY51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'polls_manage' ) && ! current_user_can( 'manage_options' ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
			}
			wp_die( 'Unauthorized' );
		}

        $id = isset( $_REQUEST['id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['id'] ) ) : ( isset( $_POST['poll_id'] ) ? sanitize_text_field( wp_unslash( $_POST['poll_id'] ) ) : '' );
        $res = $this->db->update( 'polls', array( 'status' => 'closed' ), array( 'id' => $id ) );

		if ( wp_doing_ajax() ) {
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ), 500 );
			}
			wp_send_json_success( array( 'message' => 'Poll closed successfully' ) );
			exit;
		}

        wp_safe_redirect( admin_url( 'admin.php?page=nammasociety51-polls&closed=1' ) );
        exit;
    }

	public function handle_get_poll_results() {
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'nammasociety51_poll_action' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_vote_nonce' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_nonce' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_admin_nonce' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_frontend_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce verification failed' ), 403 );
		}

		$id = isset( $_REQUEST['id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['id'] ) ) : ( isset( $_POST['poll_id'] ) ? sanitize_text_field( wp_unslash( $_POST['poll_id'] ) ) : '' );
		$polls = $this->db->get( 'polls', array( 'id' => $id ) );
		if ( empty( $polls ) ) {
			wp_send_json_error( array( 'message' => 'Poll not found' ), 404 );
		}

		$poll = $polls[0];
		$all_votes = $this->db->get( 'votes', array( 'where' => array( 'poll_id' => $id ) ) );
		$options = is_string( $poll['options'] ) ? json_decode( $poll['options'], true ) : $poll['options'];
		$tally = array();
		if ( is_array( $options ) ) {
			foreach ( $options as $opt ) {
				$tally[ $opt ] = 0;
			}
		}

		foreach ( $all_votes as $v ) {
			$opt = $v['option'] ?? '';
			if ( isset( $tally[ $opt ] ) ) {
				$tally[ $opt ]++;
			} else {
				$tally[ $opt ] = 1;
			}
		}

		wp_send_json_success( array(
			'poll'        => $poll,
			'total_votes' => count( $all_votes ),
			'tally'       => $tally,
		) );
	}

	/**
	 * Cast a Vote (Frontend).
	 */
	public function handle_cast_vote() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'nammasociety51_vote_nonce' );
        } else {
            if ( ! is_user_logged_in() || ! check_admin_referer( 'nammasociety51_vote_nonce' ) ) {
                wp_die( 'Unauthorized' );
            }
        }

		$poll_id = isset( $_POST['poll_id'] ) ? sanitize_text_field( wp_unslash( $_POST['poll_id'] ) ) : '';
		$option = isset( $_POST['vote_option'] ) ? sanitize_text_field( wp_unslash( $_POST['vote_option'] ) ) : '';
        $user_id = get_current_user_id();

        // 1. Get Resident/Flat ID
        $flat_no = $this->get_user_flat_no( $user_id );
        if ( ! $flat_no ) {
            if ( wp_doing_ajax() ) wp_send_json_error(['message' => 'Your account is not linked to a Flat.']);
            wp_die( 'Error: Your account is not linked to a Flat.' );
        }

        // 2. Already Voted? (Handle as Direct Update/Insert)
        $existing_vote = $this->get_user_vote( $poll_id, $flat_no );

        // 3. Security Check: Is Poll Open/Expired?
        $poll = $this->get_poll( $poll_id );
        if ( ! $poll || $poll['status'] !== 'open' ) {
            if ( wp_doing_ajax() ) wp_send_json_error(['message' => 'This poll is closed or invalid.']);
            wp_die( 'Error: This poll is closed or invalid.' );
        }
        if ( ! empty($poll['expiry']) && $poll['expiry'] !== '1970-01-01 00:00:01' && strtotime($poll['expiry']) < time() ) {
            if ( wp_doing_ajax() ) wp_send_json_error(['message' => 'This poll has expired.']);
            wp_die( 'Error: This poll has expired.' );
        }

        // 4. Save/Update Vote Directly
        $vote_data = array(
            'poll_id'    => $poll_id,
            'flat_no'    => $flat_no,
            'user_id'    => $user_id,
            'option'     => $option,
            'voted_at'   => current_time( 'mysql' )
        );

        if ( $existing_vote ) {
            $res = $this->db->update('votes', $vote_data, ['id' => $existing_vote['id']]);
            $msg = 'Vote updated successfully.';
        } else {
            $res = $this->db->insert('votes', $vote_data);
            $msg = 'Vote cast successfully.';
        }

        if ( wp_doing_ajax() ) {
            if ( is_wp_error( $res ) ) wp_send_json_error(['message' => $res->get_error_message()]);
            wp_send_json_success(['message' => $msg]);
            exit;
        }

        wp_safe_redirect( wp_get_referer() . '#tab-polls' ); 
        exit;
	}

    /**
     * Helper: Get Results for a Poll.
     */
    public function get_results( $poll_id ) {
        $votes = $this->db->get( 'votes' );
        $poll  = $this->get_poll( $poll_id );
        
        if ( ! $poll ) return array();

        $results = array();
        $options = is_string($poll['options']) ? json_decode($poll['options'], true) : $poll['options'];
        foreach ( ($options ?? []) as $opt ) {
            $results[$opt] = 0;
        }

        $count = 0;
        foreach ( $votes as $v ) {
            if ( $v['poll_id'] === $poll_id && isset($results[$v['option']]) ) {
                $results[$v['option']]++;
                $count++;
            }
        }

        return array(
            'counts' => $results,
            'total'  => $count
        );
    }

    public function get_poll( $id ) {
        $polls = $this->db->get( 'polls' );
        foreach ( $polls as $p ) {
            if ( $p['id'] === $id ) return $p;
        }
        return null;
    }

    public function has_voted( $poll_id, $flat_no ) {
        return $this->get_user_vote( $poll_id, $flat_no ) !== null;
    }

    public function get_user_vote( $poll_id, $flat_no ) {
        $votes = $this->db->get( 'votes' );
        foreach ( $votes as $v ) {
            if ( $v['poll_id'] === $poll_id && $v['flat_no'] === $flat_no ) {
                return $v;
            }
        }
        return null;
    }

    private function get_user_flat_no( $user_id ) {
        $residents = $this->db->get( 'residents' );
        foreach ( $residents as $r ) {
            if ( isset($r['wp_user_id']) && (int)$r['wp_user_id'] === $user_id ) {
                return $r['flat_no'];
            }
        }
        return null;
    }

	private function save_polls( $data ) {
		file_put_contents( $this->db->get_data_dir() . 'polls.json', json_encode( $data, JSON_PRETTY_PRINT ) );
	}

    private function save_votes( $data ) {
		file_put_contents( $this->db->get_data_dir() . 'votes.json', json_encode( $data, JSON_PRETTY_PRINT ) );
	}
}

