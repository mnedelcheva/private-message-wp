<div class="wrap">
    <h2><?php _e( 'Send Private Message', 'cl_pmw' ); ?></h2>
	<?php
	$option = get_option( 'option' );
	if ( $_REQUEST['page'] == 'send' && isset( $_POST['submit'] ) )
	{
		$error = false;
		$status = array();

		// Check if total pm of current user exceeds limit
		$role = $current_user->roles[0];
		$sender = $current_user->ID;
		$total = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pm WHERE `sender` = "' . $sender . '" OR `recipient` = "' . $sender . '"' );
		if ( ( $option[$role] != 0 ) && ( $total >= $option[$role] ) ) {
			$error = true;
			$status[] = __( 'You have exceeded the limit of mailbox. Please delete some messages before sending another.', 'cl_pmw' );
		}
		
		// Check if exceeds shortest time interval for sending a message (seconds)
		$count_exceeded = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pm WHERE `sender`= "' . $sender . '" AND `date`>(NOW()-INTERVAL '. $option['interval'] .' SECOND)' );
		if ( ( $option[$role] != 0 ) && ( $count_exceeded > 0 ) ) {		
			$error = true;
			$status[] = __( 'You have exceeded the time limit of mailbox. Please wait before sending another message.', 'cl_pmw' );
		}
		
		// Get input fields with no html tags and all are escaped
		$subject = strip_tags( $_POST['subject'] );
		$content = $_POST['content'] ;
		$recipient = $option['type'] == 'autosuggest' ? explode( ',', $_POST['recipient'] ) : $_POST['recipient'];  // Send to multiple users
		if ( isset( $_POST['recipient']) ) {
		$recipient = array_map( 'strip_tags', $recipient );
		}
		
		// Allow to filter content
		$content = apply_filters( 'cl_pmw_content_send', $content );
		
		// Remove slash automatically in wp
		$subject = stripslashes( $subject );
		$content = stripslashes( $content );
		if (isset( $_POST['recipient']) ) {
		$recipient = array_map( 'stripslashes', $recipient );
		}
		
		// Escape sql
		$subject = esc_sql( $subject );
		$content = esc_sql( $content );
		if (isset( $_POST['recipient']) ) {
		$recipient = array_map( 'esc_sql', $recipient );
		}

        if (isset( $_POST['type_recipient']) ) {
            $type_recipient = $_POST['type_recipient'] ;
            $type_recipient = esc_sql( $type_recipient );
        }

        // Remove duplicate and empty recipient
		if (isset( $_POST['recipient'])) {
		$recipient = array_unique( $recipient );
		$recipient = array_filter( $recipient );
		}

		// Check input fields
		if ( empty( $recipient ) && empty( $type_recipient ) ) {
			$error = true;
			$status[] = __( 'Please enter username of recipient.', 'cl_pmw' );
		}
		if ( empty( $type_recipient ) ) {
			$error = true;
			$status[] = __( 'Please enter type of recipient of recipient.', 'cl_pmw' );
		}
		if ( empty( $subject ) ) {
			$error = true;
			$status[] = __( 'Please enter subject of message.', 'cl_pmw' );
		}
		if ( empty( $content ) ) {
			$error = true;
			$status[] = __( 'Please enter content of message.', 'cl_pmw' );
		}

		if (( !$error ) && ($type_recipient == "user")) {
			$numOK = $numError = 0;
			foreach ( $recipient as $rec )
			{
				// Get ID field
				$rec = $wpdb->get_var( "SELECT ID FROM $wpdb->users WHERE display_name = '$rec' LIMIT 1" );
				$new_message = array(
					'id'        => NULL,
					'subject'   => $subject,
					'content'   => $content,
					'sender'    => $sender,
					'recipient' => $rec,
					'date'      => current_time( 'mysql' ),
					'read'      => 0,
					'deleted'   => 0
				);
				// Insert into database
				if ( $wpdb->insert( $wpdb->prefix . 'pm', $new_message, array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d' ) ) ) {
					$numOK++;
					unset( $_REQUEST['recipient'], $_REQUEST['subject'], $_REQUEST['content'] );
				}
				else {
					$numError++;
				}
			}
			$status[] = sprintf( _n( '%d message sent.', '%d messages sent.', $numOK, 'cl_pmw' ), $numOK ) . ' ' . sprintf( _n( '%d error.', '%d errors.', $numError, 'cl_pmw' ), $numError );
		}

		if (( !$error ) && ($type_recipient != "user")) {
			$numOK = $numError = 0;
			// Get ID field of users from selected role
			$role_recipients = get_users( array('role' => $type_recipient, 'fields' => 'ID') );
			foreach ( $role_recipients as $rec ) {
				$new_message = array(
						'id' => NULL,
						'subject' => $subject,
						'content' => $content,
						'sender' => $sender,
						'recipient' => $rec,
						'date' => current_time('mysql'),
						'read' => 0,
						'deleted' => 0
				);
				// Insert into database
				if ( $wpdb->insert( $wpdb->prefix . 'pm', $new_message, array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d' ) ) ) {
					$numOK++;
					unset( $_REQUEST['recipient'], $_REQUEST['subject'], $_REQUEST['content'] );
				}
				else {
					$numError++;
				}
			}
			$status[] = sprintf( _n( '%d message sent.', '%d messages sent.', $numOK, 'cl_pmw' ), $numOK ) . ' ' . sprintf( _n( '%d error.', '%d errors.', $numError, 'cl_pmw' ), $numError );
		}

		echo '<div id="message" class="updated fade"><p>', implode( '</p><p>', $status ), '</p></div>';
	}
	?>
	<?php do_action( 'cl_pmw_before_form_send' ); ?>
    <form method="post" action="" id="send-form" enctype="multipart/form-data">
	    <input type="hidden" name="page" value="send" />
        <table class="form-table">
			<?php 
			// Choose user or role for recipient
			if(is_admin()) { ?>
			<tr>
				<th>To</th>
				<td>
					<select name="type_recipient" id="typerecipient">
						<option value="user">User</option>
						<?php
							// Get user roles
							global $wp_roles;
							$all_roles = $wp_roles->roles;
							foreach ($all_roles as $role_key => $role_value) {
								echo "<option value=\"$role_key\">{$role_value['name']}</option>";
							}
						?>
					</select>
				</td>
			</tr>
			<?php
			}
			?>
            <tr id="recipient">
                <th><?php _e( 'Recipient', 'cl_pmw' ); ?></th>
                <td>
					<?php
					// If message is not sent (by errors) or in case of replying, all input is saved

					$recipient = !empty( $_POST['recipient'] ) ? $_POST['recipient'] : ( !empty( $_GET['recipient'] )
						? $_GET['recipient'] : '' );

					// Strip slashes if needed
					$subject = isset( $_REQUEST['subject'] ) ? ( get_magic_quotes_gpc() ? stripcslashes( $_REQUEST['subject'] )
						: $_REQUEST['subject'] ) : '';
					$subject = urldecode( $subject );  // for some chars like '?' when reply

					if ( empty( $_GET['id'] ) ) {
						$content = isset( $_REQUEST['content'] ) ?  $_REQUEST['content']  : '';
					}
					else {
						$id = $_GET['id'];
						$msg = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . 'pm WHERE `id` = "' . $id . '" LIMIT 1' );

						$content = '<p>&nbsp;</p>';
						$content .= '<p>---</p>';
						$content .= '<p><em>' . __( 'In: ', 'cl_pmw' ) . $msg->date . "\t" . $msg->sender . __( ' Wrote:', 'cl_pmw' ) . '</em></p>';
						$content .= wpautop( $msg->content );
						$content  = stripslashes( $content );
					}
					// If "auto suggest" feature is turned on
					if ( $option['type'] == 'autosuggest' ) {
						?>
                        <input id="recipient" type="text" name="recipient" class="large-text" />
						<?php
					}
					else { // Or if "select recipient from dropdown list" feature is turned on
						// Get all users of blog
						$args = array(
							'order'   => 'ASC',
							'orderby' => 'display_name' );
						$values = get_users( $args );
						$values = apply_filters( 'cl_pmw_recipients', $values );
						?>
						<select id="allusers" name="recipient[]" multiple="multiple" size="5">
							<?php
							foreach ( $values as $value ) {
								$selected = ( $value->display_name == $recipient ) ? ' selected="selected"' : '';  // Send to multiples users
								echo "<option value='$value->display_name'$selected>$value->display_name</option>";
							}
							?>
						</select>
						<?php
					}
					?>
                </td>
            </tr>
            <tr>
                <th><?php _e( 'Subject', 'cl_pmw' ); ?></th>
                <td><input type="text" name="subject" value="<?php echo $subject; ?>" class="large-text" /></td>
            </tr>
            <tr>
                <th><?php _e( 'Content', 'cl_pmw' ); ?></th>
                <th><?php  wp_editor( $content, 'rw-text-editor', $settings = array( 'textarea_name' => 'content', 'media_buttons' => false ) );?></th>
            </tr>
	        <?php do_action( 'cl_pmw_form_send' ); ?>
        </table>
	    <p class="submit"><input type="submit" value="Send" class="button-primary" id="submit" name="submit"></p>
    </form>
	<?php do_action( 'cl_pmw_after_form_send' ); ?>
</div>

<script type="text/javascript">
	jQuery( document ).ready( function ( $ )
{
	$(document).ready(function(){
		$("#typerecipient").change(function(){
			if($(this).val() != 'user')
			{
				$("#recipient").hide();
			}
			else
			{
				$("#recipient").show();
			}
		});
	});
});
</script>