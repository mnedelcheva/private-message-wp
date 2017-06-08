<?php
/*
Plugin Name: PrivateMessageWP
Plugin URI:
Description: Allow members to send and receive private messages (PM)
Version: 1.0
Author:
Author URI:
License: GNU General Public License v3.0
*/

// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

define( 'CL_PMW_DIR', plugin_dir_path( __FILE__ ) );
define( 'CL_PMW_TEMPLATES_DIR', trailingslashit( CL_PMW_DIR . 'templates' ) );
define( 'CL_PMW_URL', plugin_dir_url( __FILE__ ) );
define( 'CL_PMW_CSS_URL', trailingslashit( CL_PMW_URL . 'css' ) );
define( 'CL_PMW_JS_URL', trailingslashit( CL_PMW_URL . 'js' ) );
include_once CL_PMW_TEMPLATES_DIR . 'widget.php';
	
class cl_pmw
{
	public function __construct() {
		register_activation_hook( __FILE__, array($this, 'activate'));
		add_action( 'plugins_loaded', array($this, 'load_text_domain'));
		add_action( 'admin_notices', array($this, 'notify'));
		add_action( 'admin_bar_menu', array($this, 'adminbar'), 300);
		add_action( 'wp_ajax_get_users', array($this, 'get_users'));
		add_action( 'wp_enqueue_scripts', array($this, 'custom_scripts'));
		register_uninstall_hook(__FILE__, array('cl_pmw', 'uninstall'));
		
		if(is_admin())
		{
			add_action( 'admin_init', array($this, 'add_admin_init') );
			add_action( 'admin_menu', array($this, 'add_menu') );
			add_action( 'delete_older_messages', array($this, 'delete_older') );
		}
	}
	
	// Add Option page and PM Menu
	public function add_menu() {
		global $wpdb, $current_user;

		// Get number of unread messages
		$num_unread = $wpdb->get_var( 'SELECT COUNT(`id`) FROM ' . $wpdb->prefix . 'pm WHERE `recipient` = "' . $current_user->ID . '" AND `read` = 0 AND `deleted` != "2"' );

		if ( empty( $num_unread ) )
			$num_unread = 0;

		// Option page
		add_options_page( __( 'Private Messages Options', 'cl_pmw' ), __( 'Private Messages', 'cl_pmw' ), 'manage_options', 'option', array($this,'option_page') );

		// Add Private Messages Menu
		$icon_url = CL_PMW_URL . 'icon.png';
		add_menu_page( __( 'Private Messages', 'cl_pmw' ), __( 'Messages', 'cl_pmw' ) . "<span class='update-plugins count-$num_unread'><span class='plugin-count'>$num_unread</span></span>", 'read', 'inbox', array( $this, 'inbox'), $icon_url);

		// Inbox page
		$inbox_page = add_submenu_page( 'inbox', __( 'Inbox', 'cl_pmw' ), __( 'Inbox', 'cl_pmw' ), 'read', 'inbox', array( $this, 'inbox') );
		add_action( "admin_print_styles-{$inbox_page}", array($this,'admin_print_styles_inbox') );

		// Outbox page
		$outbox_page = add_submenu_page( 'inbox', __( 'Outbox', 'cl_pmw' ), __( 'Outbox', 'cl_pmw' ), 'read', 'outbox', array( $this,'outbox') );
		add_action( "admin_print_styles-{$outbox_page}", array($this,'admin_print_styles_outbox') );

		// Send page
		$send_page = add_submenu_page( 'inbox', __( 'Send Private Message', 'cl_pmw' ), __( 'Send', 'cl_pmw' ), 'read', 'send', array( $this,'send') );
		add_action( "admin_print_styles-{$send_page}", array($this,'admin_print_styles_send') );
	}

	// Enqueue scripts and styles for send page
	public function admin_print_styles_send() {
	    wp_enqueue_style( 'jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' );
		wp_enqueue_style( 'pmw_css', CL_PMW_CSS_URL . 'style.css' );
		wp_enqueue_script( 'pmw_js', CL_PMW_JS_URL . 'script.js', array( 'jquery-ui-autocomplete' ) );

		do_action( 'cl_pmw_print_styles', 'send' );
	}

	// Enqueue scripts and styles for outbox page
	public function admin_print_styles_outbox() {
		do_action( 'cl_pmw_print_styles', 'outbox' );
	}

	// Enqueue scripts and styles for inbox page
	public function admin_print_styles_inbox() {
		do_action( 'cl_pmw_print_styles', 'inbox' );
	}

	// Enqueue custom scripts
	public function custom_scripts(){
		//wp_enqueue_script( 'widget', CL_PMW_JS_URL . 'widget.js', array( 'jquery' ) );
	}

	public function load_text_domain() {
		load_plugin_textdomain( 'cl_pmw', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
	}
	
	public static function init() {
		return new self;
	}
	
	//Create table and register an option when activate
	public function activate() {
		global $wpdb;

		// Create table
		$query = 'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'pm (
			`id` bigint(20) unsigned NOT NULL auto_increment,
			`subject` varchar(255) NOT NULL,
			`content` longtext NOT NULL,
			`sender` bigint(20) unsigned NOT NULL,
			`recipient` bigint(20) unsigned NOT NULL,
			`date` datetime NOT NULL,
			`read` tinyint(1) NOT NULL,
			`deleted` tinyint(1) NOT NULL,
			PRIMARY KEY (`id`),
			KEY `sender`(`sender`),
			KEY `recipient`(`recipient`)
			) COLLATE utf8_general_ci;';

		// Note: deleted = 1 if message is deleted by sender, = 2 if it is deleted by recipient
		$wpdb->query( $query );

		// Default numbers of PM for each group
		$default_option = array(
			'val'			=> 50,
			'type'          => 'dropdown', // How to choose recipient: dropdown list or autocomplete based on user input
			'expires'		=> 0, // When a message expires (in days)
			'interval'		=> 90, // Shortest time interval for sending a message (in seconds)
		);
		add_option( 'option', $default_option, '', 'no' );
		
		// Schedule cron job event to delete messages older than entered time interval
		if (! wp_next_scheduled ( 'delete_older_messages' )) {
			wp_schedule_event(time(), 'daily', 'delete_older_messages');
		}
	}

	// Show notification of new PM
	public function notify() {
		global $wpdb, $current_user;

		// Get number of unread messages
		$num_unread = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pm WHERE `recipient` = "' . $current_user->ID . '" AND `read` = 0 AND `deleted` != "2"' );

		if ( !$num_unread )
			return;

		printf(
			'<div id="message" class="error"><p><b>%s</b> <a href="%s">%s</a></p></div>',
			sprintf( _n( 'You have %d new message!', 'You have %d new messages!', $num_unread, 'cl_pmw' ), $num_unread ),
			admin_url( 'admin.php?page=inbox' ),
			__( 'Click here to go to inbox', 'cl_pmw' )
		);
	}

	//Show number of unread messages in admin bar
	public function adminbar() {
		global $wp_admin_bar;
		global $wpdb, $current_user;

		// Get number of unread messages
		$num_unread = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pm WHERE `recipient` = "' . $current_user->ID . '" AND `read` = 0 AND `deleted` != "2"' );

		if ( $num_unread && is_admin_bar_showing() )
		{
			$wp_admin_bar->add_menu( array(
				'id'    => 'cl_pmw',
				'title' => sprintf( _n( 'You have %d new message!', 'You have %d new messages!', $num_unread, 'cl_pmw' ), $num_unread ),
				'href'  => admin_url( 'admin.php?page=inbox' ),
				'meta'  => array( 'class' => "cl_pmw_newmessages" ),
			) );
		}
	}

	// Ajax callback function to get list of users
	public function get_users() {
		$keyword = trim( strip_tags( $_POST['term'] ) );
		$values = array();
		$args = array( 'search' => '*' . $keyword . '*',
					   'fields' => 'all_with_meta' );
		$results_search_users = get_users( $args );
		$results_search_users = apply_filters( 'cl_pmw_recipients', $results_search_users );
		if ( !empty( $results_search_users ) )
		{
			foreach ( $results_search_users as $result )
			{
				$values[] = $result->display_name;
			}
		}
		die( json_encode( $values ) );
	}
	
	//Inbox page
	public function inbox() {
		global $wpdb, $current_user;

		// If view message
		if ( isset( $_GET['action'] ) && 'view' == $_GET['action'] && !empty( $_GET['id'] ) )
		{
			$id = $_GET['id'];

			check_admin_referer( "cl_pmw-view_inbox_msg_$id" );

			// Mark message as read
			$wpdb->update( $wpdb->prefix . 'pm', array( 'read' => 1 ), array( 'id' => $id ) );
			
			// Select message information
			$msg = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . 'pm WHERE `id` = "' . $id . '" LIMIT 1' );
			$msg->sender = $wpdb->get_var( "SELECT display_name FROM $wpdb->users WHERE ID = '$msg->sender'" );
			
			include_once CL_PMW_TEMPLATES_DIR . 'inboxmessage.php';
			
			// Doesn't need to do more!
			return;
		}

		// If mark messages as read
		if ( isset( $_GET['action'] ) && 'mar' == $_GET['action'] && !empty( $_GET['id'] ) )
		{
			$id = $_GET['id'];

			if ( !is_array( $id ) )
			{
				check_admin_referer( "cl_pmw-mar_inbox_msg_$id" );
				$id = array( $id );
			}
			else
			{
				check_admin_referer( "cl_pmw-bulk-action_inbox" );
			}
			$n = count( $id );
			$id = implode( ',', $id );
			if ( $wpdb->query( 'UPDATE ' . $wpdb->prefix . 'pm SET `read` = "1" WHERE `id` IN (' . $id . ')' ) )
			{
				$status = _n( 'Message marked as read.', 'Messages marked as read', $n, 'cl_pmw' );
			}
			else
			{
				$status = __( 'Error. Please try again.', 'cl_pmw' );
			}
		}

		// If delete message
		if ( isset( $_GET['action'] ) && 'delete' == $_GET['action'] && !empty( $_GET['id'] ) )
		{
			$id = $_GET['id'];

			if ( !is_array( $id ) )
			{
				check_admin_referer( "cl_pmw-delete_inbox_msg_$id" );
				$id = array( $id );
			}
			else
			{
				check_admin_referer( "cl_pmw-bulk-action_inbox" );
			}

			$error = false;
			foreach ( $id as $msg_id )
			{
				// Check if the sender has deleted this message
				$sender_deleted = $wpdb->get_var( 'SELECT `deleted` FROM ' . $wpdb->prefix . 'pm WHERE `id` = "' . $msg_id . '" LIMIT 1' );

				// Create corresponding query for deleting message
				if ( $sender_deleted == 1 )
				{
					$query = 'DELETE from ' . $wpdb->prefix . 'pm WHERE `id` = "' . $msg_id . '"';
				}
				else
				{
					$query = 'UPDATE ' . $wpdb->prefix . 'pm SET `deleted` = "2" WHERE `id` = "' . $msg_id . '"';
				}

				if ( !$wpdb->query( $query ) )
				{
					$error = true;
				}
			}
			if ( $error )
			{
				$status = __( 'Error. Please try again.', 'cl_pmw' );
			}
			else
			{
				$status = _n( 'Message deleted.', 'Messages deleted.', count( $id ), 'cl_pmw' );
			}
		}

		// Show all messages which have not been deleted by this user (deleted status != 2)
		$msgs = $wpdb->get_results( 'SELECT `id`, `sender`, `subject`, `read`, `date` FROM ' . $wpdb->prefix . 'pm WHERE `recipient` = "' . $current_user->ID . '" AND `deleted` != "2" ORDER BY `date` DESC' );
	
		include_once CL_PMW_TEMPLATES_DIR . 'inbox.php';
		
	}
	
	// Outbox page
	public function outbox() {
		global $wpdb, $current_user;

		// If view message
		if (isset($_GET['action']) && 'view' == $_GET['action'] && !empty($_GET['id'])) {
			$id = $_GET['id'];

			check_admin_referer("cl_pmw-view_outbox_msg_$id");

			// Select message information
			$msg = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . 'pm WHERE `id` = "' . $id . '" LIMIT 1' );
			$msg->recipient = $wpdb->get_var("SELECT display_name FROM $wpdb->users WHERE ID = '$msg->recipient'");
		
			include_once CL_PMW_TEMPLATES_DIR . 'outboxmessage.php';
	
			// Doesn't need to do more!
			return;
		}

		// If delete message
		if (isset($_GET['action']) && 'delete' == $_GET['action'] && !empty($_GET['id'])) {
			$id = $_GET['id'];

			if (!is_array($id)) {
				check_admin_referer("cl_pmw-delete_outbox_msg_$id");
				$id = array($id);
			} else {
				check_admin_referer("cl_pmw-bulk-action_outbox");
			}
			$error = false;
			foreach ($id as $msg_id) {
				// Check if the recipient has deleted this message
				$recipient_deleted = $wpdb->get_var('SELECT `deleted` FROM ' . $wpdb->prefix . 'pm WHERE `id` = "' . $msg_id . '" LIMIT 1');
				// Create corresponding query for deleting message
				if ($recipient_deleted == 2) {
					$query = 'DELETE from ' . $wpdb->prefix . 'pm WHERE `id` = "' . $msg_id . '"';
				} else {
					$query = 'UPDATE ' . $wpdb->prefix . 'pm SET `deleted` = "1" WHERE `id` = "' . $msg_id . '"';
				}

				if (!$wpdb->query($query)) {
					$error = true;
				}
			}
			if ($error) {
				$status = __('Error. Please try again.', 'cl_pmw');
			} else {
				$status = _n('Message deleted.', 'Messages deleted.', count($id), 'cl_pmw');
			}
		}

		// Show all messages
		$msgs = $wpdb->get_results('SELECT `id`, `recipient`, `subject`, `date` FROM ' . $wpdb->prefix . 'pm WHERE `sender` = "' . $current_user->ID . '" AND `deleted` != 1 ORDER BY `date` DESC');
		
		include_once CL_PMW_TEMPLATES_DIR . 'outbox.php';
	}
		
	// Send form page
	public function send() {
		global $wpdb, $current_user;
		
		include_once CL_PMW_TEMPLATES_DIR . 'send.php';	
	}
		
	
	// Option page: Change number of PMs for each group
	function option_page() {
		// Include templates dir -> options.php
		include_once CL_PMW_TEMPLATES_DIR . 'options.php';
	}
	
	// Register plugin option
	public function add_admin_init() {
		register_setting( 'option_group', 'option' );
	}
	
	// Uninstall plugin option
	public static function uninstall() {
		global $wpdb;

		// Drop PM table and plugin option when uninstall
		$wpdb->query( "DROP table {$wpdb->prefix}pm" );
		delete_option( 'option' );
		wp_clear_scheduled_hook('delete_older_messages');
	}
	
	// Deletes messages older than entered time interval (days)
	public function delete_older() 
	{
		global $wpdb;
        global $option;

		if ( $option['expires'] > 0 )
			{
				$wpdb->query('DELETE from ' . $wpdb->prefix . 'pm WHERE `date`<DATE_SUB(NOW(), INTERVAL '. $option['expires'] .' DAY)');
			}
	}
}

cl_pmw::init();

