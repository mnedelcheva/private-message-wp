<div class="wrap">
    <h2><?php _e('Outbox', 'cl_pmw'); ?></h2>
    <?php
    if (!empty($status)) {
        echo '<div id="message" class="updated fade"><p>', $status, '</p></div>';
    }
    if (empty($msgs)) {
        echo '<p>', __('You have no items in outbox.', 'cl_pmw'), '</p>';
    } else {
        $n = count($msgs);
        echo '<p>', sprintf(_n('You wrote %d private message.', 'You wrote %d private messages.', $n, 'cl_pmw'), $n), '</p>';
        ?>
        <form action="" method="get">
            <?php wp_nonce_field('cl_pmw-bulk-action_outbox'); ?>
            <input type="hidden" name="action" value="delete"/> <input type="hidden" name="page" value="cl_pmw_outbox"/>

            <div class="tablenav">
                <input type="submit" class="button-secondary" value="<?php _e('Delete Selected', 'cl_pmw'); ?>"/>
            </div>

            <table class="widefat fixed" cellspacing="0">
                <thead>
                <tr>
                    <th class="manage-column check-column"><input type="checkbox"/></th>
                    <th class="manage-column" width="10%"><?php _e('Recipient', 'cl_pmw'); ?></th>
                    <th class="manage-column"><?php _e('Subject', 'cl_pmw'); ?></th>
                    <th class="manage-column" width="20%"><?php _e('Date', 'cl_pmw'); ?></th>
                </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($msgs as $msg) {
                        $msg->recipient = $wpdb->get_var("SELECT display_name FROM $wpdb->users WHERE ID = '$msg->recipient'");
                        ?>
                    <tr>
                        <th class="check-column"><input type="checkbox" name="id[]" value="<?php echo $msg->id; ?>"/>
                        </th>
                        <td><?php echo $msg->recipient; ?></td>
                        <td>
                            <?php
                            echo '<a href="', wp_nonce_url("?page=outbox&action=view&id=$msg->id", 'cl_pmw-view_outbox_msg_' . $msg->id), '">', stripcslashes($msg->subject), '</a>';
                            ?>
                            <div class="row-actions">
							<span>
								<a href="<?php echo wp_nonce_url("?page=outbox&action=view&id=$msg->id", 'cl_pmw-view_outbox_msg_' . $msg->id); ?>"><?php _e('View', 'cl_pmw'); ?></a>
							</span>
							<span class="delete">
								| <a class="delete"
                                     href="<?php echo wp_nonce_url("?page=outbox&action=delete&id=$msg->id", 'cl_pmw-delete_outbox_msg_' . $msg->id); ?>"><?php _e('Delete', 'cl_pmw'); ?></a>
							</span>
                            </div>
                        </td>
                        <td><?php echo $msg->date; ?></td>
                    </tr>
                        <?php

                    }
                    ?>
                </tbody>
                <tfoot>
                <tr>
                    <th class="manage-column check-column"><input type="checkbox"/></th>
                    <th class="manage-column"><?php _e('Recipient', 'cl_pmw'); ?></th>
                    <th class="manage-column"><?php _e('Subject', 'cl_pmw'); ?></th>
                    <th class="manage-column"><?php _e('Date', 'cl_pmw'); ?></th>
                </tr>
                </tfoot>
            </table>
        </form>
        <?php

    }
    ?>
</div>