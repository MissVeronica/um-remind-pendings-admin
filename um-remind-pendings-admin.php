<?php
/*
 * Plugin Name:     Ultimate Member - Remind Pendings Admin Email
 * Description:     Extension to Ultimate Member with an email template sent daily or weekly with a placeholder {remind-pendings-admin} which creates a list to Remind Admin about all Users Pending for a Review.
 * Version:         1.0.0
 * Author:          Miss Veronica
 * License:         GPL v3 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica
 * Plugin URI:      https://github.com/MissVeronica/um-remind-pendings-admin
 * Update URI:      https://github.com/MissVeronica/um-remind-pendings-admin
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.10.6
*/

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'UM' ) ) return;

class UM_Remind_Pendings_Admin_Email {

    public $cronjob         = 'um_remind_pendings_admin';
    public $templates       = array( 'notification_review',
                                     'remind_pendings_admin'
                                  );
    public $replacement     = false;
    public $um_placeholder  = '{remind-pendings-admin}';

    public $default_hour    = '06';
    public $default_weekday = 'monday';

    function __construct() {

        define( 'Plugin_Basename_RPA', plugin_basename( __FILE__ ));
        define( 'Plugin_Path_RPA',     plugin_dir_path( __FILE__ ));

        add_filter( 'cron_schedules',           array( $this, $this->cronjob ), 10, 1 );
        add_action( 'um_remind_pendings_admin', array( $this, $this->cronjob . '_exec' ), 10, 0 );

        add_filter( 'um_template_tags_patterns_hook',       array( $this, 'add_placeholder' ));
        add_filter( 'um_template_tags_replaces_hook',       array( $this, 'add_replace_placeholder' ));
        add_action( 'um_before_email_notification_sending', array( $this, 'before_email_notification_sending' ), 10, 3 );

        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {

            add_filter( 'um_admin_settings_email_section_fields', array( $this, 'admin_settings_remind_pendings_admin' ), 10, 2 );
            add_filter( 'um_email_templates_columns',             array( $this, 'email_templates_columns_update_cronjob' ), 10, 1 );
            add_filter( 'um_email_notifications',                 array( $this, 'email_notification_remind_pendings_admin' ), 99 );
            add_action( 'admin_menu',                             array( $this, 'load_toplevel_page_content' ), 30 );
            add_action( 'um_admin_do_action__send_remind_email',  array( $this, 'send_remind_email_dashboard' ));
            add_filter( 'um_adm_action_custom_update_notice',     array( $this, 'send_remind_email_admin_notice' ), 99, 2 );
        }

        add_filter( 'plugin_action_links_' . Plugin_Basename_RPA, array( $this, 'plugin_settings_link' ), 10, 1 );

        register_deactivation_hook( __FILE__, array( $this, $this->cronjob ));
        register_activation_hook(   __FILE__, array( $this, $this->cronjob . '_activate' ));
    }

    public function plugin_settings_link( $links = array() ) {

        $url     = get_admin_url() . "admin.php?page=um_options&tab=email&email=" . $this->templates[1];
        $title   = esc_html__( 'Settings only for this email template', 'ultimate-member' );

        if ( empty( $links )) {
            $links = '<a href="' . esc_url( $url ) . '" class="button" title="' . $title . '">' . esc_html__( 'Settings' ) . '</a>';
        } else {
            $links[] = '<a href="' . esc_url( $url ) . '" title="' . $title . '">' . esc_html__( 'Settings' ) . '</a>';
        }

        return $links;
    }

    public function um_remind_pendings_admin_activate() {

        $this->create_next_cron_job();
    }

    public function um_remind_pendings_admin_deactivate() {

        $timestamp = wp_next_scheduled( $this->cronjob );
        if ( $timestamp !== false ) {
            wp_unschedule_event( $timestamp, $this->cronjob );
        }
    }

    public function um_remind_pendings_admin( $schedules = array() ) {

        if ( $schedules === false ) {

            wp_unschedule_hook( $this->cronjob );
        }

        if ( is_array( $schedules )) {

            if ( UM()->options()->get( $this->templates[1] . '_daily_reminder' ) == 1 ) {

                $schedules['daily'] = array( 'interval' => DAY_IN_SECONDS,
                                             'display'  => esc_html__( 'Once Daily' ),
                                            );
            } else {

                $schedules['weekly'] = array( 'interval' => WEEK_IN_SECONDS,
                                              'display'  => esc_html__( 'Once Weekly' ),
                                            );
            }
        }

        return $schedules;
    }

    public function find_pending_users_send_reminder_email() {

        $user_list = $this->find_pending_users();
        $users_count = 0;

        if ( is_array( $user_list ) && ! empty( $user_list )) {

            $users_count = count( $user_list );
            $this->replacement = $this->format_replacement( $user_list );
            UM()->mail()->send( get_option( 'admin_email' ), $this->templates[1] );
        }

        return $users_count;
    }

    public function um_remind_pendings_admin_exec() {

        if ( UM()->options()->get( $this->templates[1] . '_on' ) == 1 ) {

            $this->find_pending_users_send_reminder_email();
            $this->create_next_cron_job();
        }
    }

    public function create_next_cron_job() {

        $timestamp = wp_next_scheduled( $this->cronjob );
        if ( $timestamp !== false ) {
            wp_unschedule_event( $timestamp, $this->cronjob );
        }

        if ( UM()->options()->get( $this->templates[1] . '_daily_reminder' ) == 1 ) {
            wp_schedule_event( $this->time_next_cronjob( "tomorrow" ), 'daily', $this->cronjob );

        } else {

            $weekday = sanitize_text_field( UM()->options()->get( $this->templates[1] . '_weekday' ));
            if ( empty( $weekday )) {
                $weekday = $this->default_weekday;
            }

            wp_schedule_event( $this->time_next_cronjob( "next {$weekday}" ), 'weekly', $this->cronjob );
        }
    }

    public function time_next_cronjob( $weekday ) {

        $hour = sanitize_text_field( UM()->options()->get( $this->templates[1] . '_hour' ));
        if ( empty( $hour )) {
            $hour = $this->default_hour;
        }

        $seconds = absint( $hour ) * HOUR_IN_SECONDS;

        return strtotime( get_gmt_from_date( date_i18n( 'Y-m-d H:i:s', strtotime( $weekday )), 'Y-m-d H:i:s' )) + $seconds;
    }

    public function find_pending_users() {

        global $wpdb;

        $sql  = "SELECT {$wpdb->prefix}users.ID,
                        {$wpdb->prefix}users.user_email,
                        {$wpdb->prefix}users.user_login,
                        {$wpdb->prefix}users.user_registered
                 FROM   {$wpdb->prefix}users ";

        $sql .= "INNER JOIN {$wpdb->prefix}usermeta
                       ON ( {$wpdb->prefix}users.ID = {$wpdb->prefix}usermeta.user_id ) ";

        $sql .= " WHERE 1=1 ";
        $sql .= " AND ( {$wpdb->prefix}usermeta.meta_key = 'account_status'
                  AND   {$wpdb->prefix}usermeta.meta_value IN ('awaiting_admin_review'))";

        $sql .= " GROUP BY {$wpdb->prefix}users.ID
                  ORDER BY {$wpdb->prefix}users.ID";

        return $wpdb->get_results( $sql );
    }

    public function format_replacement( $user_list ) {

        $table_list = array();
        $um_title   = esc_html__( 'UM Profile', 'ultimate-member' );
        $wp_title   = esc_html__( 'WP User', 'ultimate-member' );

        foreach( $user_list as $user ) {

            $profile_url  = '<a href="' . esc_url( um_user_profile_url( $user->ID )) . '" title="' . $um_title . '" target="user_profile">' . $user->user_login . '</a>';
            $wp_users_url = '<a href="' . esc_url( get_admin_url() . 'users.php?s=' . $user->user_email ) . '" title="' . $wp_title . '"  target="wp_user">' . $user->user_email . '</a>';
            $registered   = date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ));

            $table_list[] = "<tr>
                                <td>{$user->ID}</td>
                                <td>{$profile_url}</td>
                                <td>{$wp_users_url}</td>
                                <td>{$registered}</td>
                             </tr>";
        }

        $header    = '<h3 style="text-align: center;">' . $this->create_status_message( $user_list ) . '</h3>';
        $date      = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ));
        $header   .= '<div style="text-align: center;">' . $date . '</div>';
        $wp_title  = esc_html__( 'Pending administrator review', 'ultimate-member' );
        $wp_url    = esc_url( get_admin_url() . 'users.php?s&um_user_status=awaiting_admin_review' );
        $header   .= '<div style="text-align: center;"><a href="' . $wp_url . '" title="' . $wp_title . '" target="wp_user">' . esc_html__( 'WP All Users', 'ultimate-member' ) . '</a></div>';
        $table_hdr = '<tr>
                          <th>' . esc_html__( 'ID', 'ultimate-member' ) . '</th>
                          <th>' . esc_html__( 'Profile', 'ultimate-member' ) . '</th>
                          <th>' . esc_html__( 'Email', 'ultimate-member' ) . '</th>
                          <th>' . esc_html__( 'Registered', 'ultimate-member' ) . '</th>
                      </tr>';

        return $header . '<table>' . $table_hdr . implode( chr(13), $table_list ) . '</table>';
    }

    public function create_status_message( $user_list ) {

        $number = count( $user_list );
        switch( $number ) {
            case 0:     $message = esc_html__( 'No Users are waiting for an Admin review', 'ultimate-member' ); break;
            case 1:     $message = esc_html__( 'One User is waiting for an Admin review', 'ultimate-member' ); break;
            default:    $message = sprintf( esc_html__( '%d Users are waiting for an Admin review', 'ultimate-member' ), $number );
        }

        return $message;
    }

    public function add_placeholder( $placeholders ) {

        if ( $this->replacement !== false ) {
            $placeholders[] = $this->um_placeholder;
        }
        return $placeholders;
    }

    public function add_replace_placeholder( $replace_placeholders ) {

        if ( $this->replacement !== false ) {
            $replace_placeholders[] = $this->replacement;
        }
        return $replace_placeholders;
    }

    public function before_email_notification_sending( $email, $template, $args ) {

        if ( in_array( $template, $this->templates ) && $this->replacement === false ) {

            $user_list = $this->find_pending_users();
            $this->replacement = '';

            if ( is_array( $user_list ) && ! empty( $user_list )) {
                $this->replacement = $this->format_replacement( $user_list );
            }
        }
    }

    public function email_templates_columns_update_cronjob( $array ) {

        $this->create_next_cron_job();
        return $array;
    }

    public function get_possible_plugin_update( $plugin ) {

        $plugin_data = get_plugin_data( __FILE__ );

        $documention = sprintf( ' <a href="%s" target="_blank" title="%s">%s</a>',
                                        esc_url( $plugin_data['PluginURI'] ),
                                        esc_html__( 'GitHub plugin documentation and download', 'ultimate-member' ),
                                        esc_html__( 'Documentation', 'ultimate-member' ));

        $description = sprintf( esc_html__( 'Plugin "Remind Pendings to Admin" version %s - Tested with UM 2.10.6 - %s', 'ultimate-member' ),
                                                                            $plugin_data['Version'], $documention );
        return $description;
    }

    public function admin_settings_remind_pendings_admin( $section_fields, $email_key ) {

        global $wp_locale;

        if ( $email_key == $this->templates[1] ) {

            $prefix = '&nbsp; * &nbsp;';

            $week_days = array( 
                                'sunday'    =>  $wp_locale->get_weekday( 0 ),
                                'monday'    =>  $wp_locale->get_weekday( 1 ),
                                'tuesday'   =>  $wp_locale->get_weekday( 2 ),
                                'wednesday' =>  $wp_locale->get_weekday( 3 ),
                                'thursday'  =>  $wp_locale->get_weekday( 4 ),
                                'friday'    =>  $wp_locale->get_weekday( 5 ),
                                'saturday'  =>  $wp_locale->get_weekday( 6 ),
                            );

            $time_format = strtolower( get_option( 'time_format' ));
            $hours = ( ! str_contains( $time_format, 'a' )) ?
                        array(
                            '00' => '00:00',
                            '01' => '01:00',
                            '02' => '02:00',
                            '03' => '03:00',
                            '04' => '04:00',
                            '05' => '05:00',
                            '06' => '06:00',
                            '07' => '07:00',
                            '08' => '08:00',
                            '09' => '09:00',
                            '10' => '10:00',
                            '11' => '11:00',
                            '12' => '12:00',
                            '13' => '13:00',
                            '14' => '14:00',
                            '15' => '15:00',
                            '16' => '16:00',
                            '17' => '17:00',
                            '18' => '18:00',
                            '19' => '19:00',
                            '20' => '20:00',
                            '21' => '21:00',
                            '22' => '22:00',
                            '23' => '23:00',
                        ) :

                        array(
                            '00' => '12:00 AM',
                            '01' => '01:00 AM',
                            '02' => '02:00 AM',
                            '03' => '03:00 AM',
                            '04' => '04:00 AM',
                            '05' => '05:00 AM',
                            '06' => '06:00 AM',
                            '07' => '07:00 AM',
                            '08' => '08:00 AM',
                            '09' => '09:00 AM',
                            '10' => '10:00 AM',
                            '11' => '11:00 AM',
                            '12' => '12:00 PM',
                            '13' => '01:00 PM',
                            '14' => '02:00 PM',
                            '15' => '03:00 PM',
                            '16' => '04:00 PM',
                            '17' => '05:00 PM',
                            '18' => '06:00 PM',
                            '19' => '07:00 PM',
                            '20' => '08:00 PM',
                            '21' => '09:00 PM',
                            '22' => '10:00 PM',
                            '23' => '11:00 PM',
                        );

            $section_fields[] = array(
                                    'id'             => $email_key . '_header',
                                    'type'           => 'header',
                                    'label'          => $this->get_possible_plugin_update( $this->templates[1] ),
                                    'conditional'    => array( $email_key . '_on', '=', 1 ),
                                );

            $section_fields[] = array(
                                    'id'             => $email_key . '_daily_reminder',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'Daily Reminder to Admin', 'ultimate-member' ),
                                    'checkbox_label' => esc_html__( 'Click to send the email reminder to admin daily otherwise email is sent weekly.', 'ultimate-member' ),
                                    'conditional'    => array( $email_key . '_on', '=', 1 ),
                                );

            $section_fields[] = array(
                                    'id'             => $email_key . '_weekday',
                                    'type'           => 'select',
                                    'size'           => 'short',
                                    'label'          => $prefix . __( 'Select weekday for reminder email', 'ultimate-member' ),
                                    'options'        => $week_days,
                                    'description'    => sprintf( esc_html__( 'Default weekday for sending the Reminder email is %s.', 'ultimate-member' ), $week_days[$this->default_weekday] ),
                                    'conditional'    => array( $email_key . '_daily_reminder', '!=', 1 ),
                                );

            $section_fields[] = array(
                                    'id'             => $email_key . '_hour',
                                    'type'           => 'select',
                                    'size'           => 'short',
                                    'label'          => $prefix . __( 'Select time for reminder email', 'ultimate-member' ),
                                    'options'        => $hours,
                                    'description'    => sprintf( esc_html__( 'Default time during the day for scheduling the WP cronjob to send the Reminder email is at %s.', 'ultimate-member' ), $hours[$this->default_hour] ),
                                    'conditional'    => array( $email_key . '_on', '=', 1 ),
                                );

            $section_fields[] = array(
                                    'id'             => $email_key . '_dashboard',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'Enable UM Dashboard status', 'ultimate-member' ),
                                    'checkbox_label' => esc_html__( 'Click to get number of Users waiting for a review and the schedule for next email at the UM dashboard and the possibilty to send extra Admin emails.', 'ultimate-member' ),
                                    'conditional'    => array( $email_key . '_on', '=', 1 ),
                                );
        }

        return $section_fields;
    }

    public function email_notification_remind_pendings_admin( $um_emails ) {

        $custom_emails = array(	$this->templates[1] => array(
                                        'key'            => $this->templates[1],
                                        'title'          => esc_html__( 'Remind Pendings Admin - Daily/Weekly email', 'ultimate-member' ),
                                        'description'    => esc_html__( 'User Pending Review Notification Email to Admin email template', 'ultimate-member' ),
                                        'recipient'      => 'admin',
                                        'default_active' => true,
                                        'subject'        => '[{site_name}] Profile reviews pending',
                                        'body'           => '',
                                ),
                            );

        if ( UM()->options()->get( $this->templates[1] . '_on' ) === '' ) {

            $email_on = empty( $custom_email[$this->templates[1]]['default_active'] ) ? 0 : 1;
            UM()->options()->update( $this->templates[1] . '_on', $email_on );
        }

        if ( UM()->options()->get( $this->templates[1] . '_sub' ) === '' ) {

            UM()->options()->update( $this->templates[1] . '_sub', $custom_emails[$this->templates[1]]['subject'] );
        }

        $this->copy_email_notification();

        return array_merge( $um_emails, $custom_emails );
    }

    public function copy_email_notification() {

        $located = UM()->mail()->locate_template( $this->templates[1] );

        if ( ! is_file( $located ) || filesize( $located ) == 0 ) {
            $located = wp_normalize_path( STYLESHEETPATH . '/ultimate-member/email/' . $this->templates[1] . '.php' );
        }

        clearstatcache();
        if ( ! file_exists( $located ) || filesize( $located ) == 0 ) {

            wp_mkdir_p( dirname( $located ) );

            $email_source = file_get_contents( Plugin_Path_RPA . $this->templates[1] . '.php' );
            file_put_contents( $located, $email_source );

            if ( ! file_exists( $located ) ) {
                file_put_contents( um_path . 'templates/email/' . $this->templates[1] . '.php', $email_source );
            }
        }
    }

    public function load_toplevel_page_content() {

        if ( UM()->options()->get( $this->templates[1] . '_dashboard' ) == 1 ) {

            $user_list   = $this->find_pending_users();
            $top_message = $this->create_status_message( $user_list );

            add_meta_box(   'load-toplevel_page_email_status',
                            $top_message,
                            array( $this, 'toplevel_page_email_status' ),
                            'toplevel_page_ultimatemember',
                            'side',
                            'core'
                        );
        }
    }

    public function toplevel_page_email_status() {

        $cron_job = wp_next_scheduled( $this->cronjob );
        if ( $cron_job === false ) {

            $message = esc_html__( 'There is no WP Cronjob scheduled for the Admiun Reminder email', 'ultimate-member' );

        } else {

            $format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
            $utc_timestamp_converted = date( $format, $cron_job );
            $local_timestamp = get_date_from_gmt( $utc_timestamp_converted, $format );

            $message = sprintf( esc_html__( 'The next Admin Reminder email is scheduled at %s', 'ultimate-member' ), $local_timestamp );
        }

        echo "<div>{$message}</div>";
        echo '<div>' . $this->send_remind_email_buttons() . '</div>';
    }

    public function send_remind_email_buttons() {

        $url = add_query_arg(
                              array(
                                     'um_adm_action' => 'send_remind_email',
                                     '_wpnonce'      => wp_create_nonce( 'send_remind_email' ),
                                   )
                            );

        $button_text  = esc_html__( 'Send Admin Reminder email now', 'ultimate-member' );
        $button_title = esc_html__( 'Press this button to send an email with current status of Users waiting for Admin review.', 'ultimate-member' );

        $settings_link = $this->plugin_settings_link();

        ob_start();
?>
        <p>
            <a href="<?php echo esc_url( $url ); ?>" class="button" title="<?php echo esc_attr( $button_title ); ?>">
                <?php esc_attr_e( $button_text ); ?>
            </a>
            <?php echo $settings_link; ?>
        </p>
<?php
        return ob_get_clean();
    }

    public function send_remind_email_dashboard() {

        $status = $this->find_pending_users_send_reminder_email();

        $url = add_query_arg(
                                array(
                                        'page'     => 'ultimatemember',
                                        'action'   => 'send_remind_email',
                                        'update'   => 'send_remind_email',
                                        'result'   =>  $status,
                                        '_wpnonce' =>  wp_create_nonce( 'send_remind_email' ),
                                    ),
                                admin_url( 'admin.php' )
                            );

        wp_safe_redirect( $url );
        exit;
    }

    public function send_remind_email_admin_notice( $message, $update ) {

        if ( $update == 'send_remind_email' && isset( $_REQUEST['result'] )) {

            $message[]['content'] = sprintf( esc_html__( 'Reminder email sent to Admin with a list of %s Users for review', 'ultimate-member' ),
                                                            sanitize_text_field( $_REQUEST['result'] ));
        }

        return $message;
    }
}


new UM_Remind_Pendings_Admin_Email();
