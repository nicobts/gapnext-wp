<?php
// includes/views/admin-client-access.php
if ( ! defined( 'ABSPATH' ) ) exit;

$clients = GapNext_Client_Role::get_audit_clients( $sub->audit_uuid );
?>

<div style="max-width:600px">
    <h3><?php esc_html_e( 'Assigned Client Users', 'gapnext-wp' ); ?></h3>

    <?php if ( empty( $clients ) ) : ?>
        <p style="color:#646970;font-style:italic"><?php esc_html_e( 'No client users assigned to this audit yet.', 'gapnext-wp' ); ?></p>
    <?php else : ?>
        <table class="widefat" style="margin-bottom:20px">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name', 'gapnext-wp' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'gapnext-wp' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $clients as $client ) : ?>
                <tr>
                    <td><?php echo esc_html( $client['display_name'] ); ?></td>
                    <td><?php echo esc_html( $client['user_email'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3><?php esc_html_e( 'Add Client User', 'gapnext-wp' ); ?></h3>
    <p class="description" style="margin-bottom:12px">
        <?php esc_html_e( 'Create a new WordPress user with the GapNext Client role, or grant an existing user access to this audit.', 'gapnext-wp' ); ?>
    </p>

    <table class="form-table" style="margin:0">
        <tr>
            <th><label for="gnr-client-name"><?php esc_html_e( 'Name', 'gapnext-wp' ); ?></label></th>
            <td><input type="text" id="gnr-client-name" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Marco Rossi', 'gapnext-wp' ); ?>"></td>
        </tr>
        <tr>
            <th><label for="gnr-client-email"><?php esc_html_e( 'Email', 'gapnext-wp' ); ?></label></th>
            <td><input type="email" id="gnr-client-email" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. marco@acme.com', 'gapnext-wp' ); ?>"></td>
        </tr>
    </table>
    <p>
        <button type="button" id="gnr-create-client-btn" class="button button-primary">
            <?php esc_html_e( 'Create & Grant Access', 'gapnext-wp' ); ?>
        </button>
    </p>
</div>
