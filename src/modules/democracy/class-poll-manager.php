<?php
/**
 * Class: Poll Manager
 * Handles Digital Democracy (Polling & Voting).
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Poll_Manager implements SGVX51_Module {

	private $db;

	public function __construct() {
		$this->db = new SGVX51_DB_Router();
        
        // Admin Actions
		add_action( 'admin_post_sgvx51_create_poll', array( $this, 'handle_create_poll' ) );
        add_action( 'admin_post_sgvx51_delete_poll', array( $this, 'handle_delete_poll' ) );
        add_action( 'admin_post_sgvx51_close_poll', array( $this, 'handle_close_poll' ) );

        // Frontend Actions
        add_action( 'admin_post_sgvx51_cast_vote', array( $this, 'handle_cast_vote' ) );
        add_action( 'wp_ajax_sgvx51_cast_vote', array( $this, 'handle_cast_vote' ) );

        // Register Module
        add_filter( 'sgvx51_get_module_polls', array( $this, 'get_instance' ) );
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

            // Primary Key 'id' is AUTO_INCREMENT in MySQL schema, 
            // DB_Router handles it or we can let it be.
            return $this->db->insert('votes', $vote_data);
        }
        return new WP_Error( 'invalid_action', 'Unknown action' );
    }

	/**
	 * Create a new Poll.
	 */
	public function handle_create_poll() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sgvx51_poll_action' ) ) {
			wp_die( 'Unauthorized' );
		}

		$title   = sanitize_text_field( $_POST['title'] );
		$desc    = sanitize_textarea_field( $_POST['description'] );
		$options = array_filter( array_map( 'sanitize_text_field', $_POST['options'] ) );
		$expiry  = sanitize_text_field( $_POST['expiry_date'] );

		if ( count( $options ) < 2 ) {
			wp_die( 'At least 2 options are required.' );
		}

		$polls = $this->db->get( 'polls' );
        
        $new_poll = array(
            'id'          => uniqid( 'poll_' ),
            'title'       => $title,
            'description' => $desc,
            'options'     => json_encode(array_values($options)), // JSON for DB
            'expiry'      => $expiry,
            'status'      => 'open', // open, closed
            'created_at'  => current_time( 'mysql' ),
            'created_by'  => get_current_user_id()
        );

        $this->db->insert('polls', $new_poll);

		wp_redirect( admin_url( 'admin.php?page=sgvx51-polls&created=1' ) );
		exit;
	}

    /**
     * Delete a Poll.
     */
    public function handle_delete_poll() {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sgvx51_poll_action' ) ) {
			wp_die( 'Unauthorized' );
		}

        $id = sanitize_text_field( $_GET['id'] );
        $this->db->delete('polls', ['id' => $id]);

        // Also clean up votes for this poll
        // DB_Router doesn't support delete_many efficiently yet without key, 
        // but votes don't have unique ID in current schema? 
        // Wait, votes schema: id, poll_id... check schema.
        // Schema says: society_governx_votes (id, poll_id, flat_no, ...)
        // JSON file had flat_no+poll_id as key somewhat. 
        // For MySQL, we run a DELETE query. For JSON, we might leave orphans or iterate?
        // Since DB_Router relies on 'id' for delete(), we can't delete by poll_id easily.
        // Let's iterate and delete one by one or assume DB_Router needs upgrade.
        // For now: Iterate and delete found votes.
        $votes = $this->db->get( 'votes' );
        foreach($votes as $v) {
            if($v['poll_id'] === $id && isset($v['id'])) {
                $this->db->delete('votes', ['id' => $v['id']]);
            }
        }

        wp_redirect( admin_url( 'admin.php?page=sgvx51-polls&deleted=1' ) );
        exit;
    }

    /**
     * Close a Poll manually.
     */
    public function handle_close_poll() {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sgvx51_poll_action' ) ) {
			wp_die( 'Unauthorized' );
		}

        $id = sanitize_text_field( $_GET['id'] );
        // Update Poll Status
        $this->db->update('polls', ['status' => 'closed'], ['id' => $id]);

        wp_redirect( admin_url( 'admin.php?page=sgvx51-polls&closed=1' ) );
        exit;
    }

	/**
	 * Cast a Vote (Frontend).
	 */
	public function handle_cast_vote() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'sgvx51_vote_nonce' );
        } else {
            if ( ! is_user_logged_in() || ! check_admin_referer( 'sgvx51_vote_nonce' ) ) {
                wp_die( 'Unauthorized' );
            }
        }

		$poll_id = sanitize_text_field( $_POST['poll_id'] );
		$option  = sanitize_text_field( $_POST['vote_option'] );
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

        wp_redirect( wp_get_referer() . '#tab-polls' ); 
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
