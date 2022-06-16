<?php

namespace BitCode\BitFormPro\Auth;

use WP_Error;

/**
 * Class handling Wordpress Users.
 *
 * @since 1.0.0
 */
final class UserRowAction 
{
    public function userRowAction()
    {
      add_action('user_row_actions', array($this, 'usersActions'), 10, 2);
      add_action('wp_authenticate_user', array($this, 'user_authenticate'));
      add_action('admin_action_bitforms_reject', array($this, 'unapprove'));
      add_action('admin_action_bitforms_approve', array($this, 'approve'));
    }

    public function unapprove()
    {
        check_admin_referer('bitforms-reject-users');

        $this->doUnapprove();
    }
    public function approve()
    {

        check_admin_referer('bitforms-approve-users');

        $this->doApprove();
    }
    protected function check_user()
    {

        $site_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        $url     = 'site-users-network' === get_current_screen()->id ? add_query_arg(array('id' => $site_id), 'site-users.php') : 'users.php';

        if (empty($_REQUEST['users']) && empty($_REQUEST['user'])) {
            wp_redirect($url);
            exit();
        }

        if (!current_user_can('promote_users')) {
            wp_die(esc_html__('You can&#8217;t unapprove users.', 'bitform-user-registration'), '', array(
                'back_link' => true,
            ));
        }

        $userids = empty($_REQUEST['users']) ? array(intval($_REQUEST['user'])) : array_map('intval', (array) $_REQUEST['users']);
        $userids = array_diff($userids, array(get_user_by('email', get_bloginfo('admin_email'))->ID));

        return array($userids, $url);
    }

    protected function doUnapprove()
    {
        list($userids, $url) = $this->check_user();
        foreach ((array) $userids as $id) {
            $id = (int) $id;

            if (!current_user_can('edit_user', $id)) {
                wp_die(esc_html__('You can&#8217;t edit that user.'), '', array(
                    'back_link' => true,
                ));
            }

            update_user_meta($id, 'bf_activation', 0);
         
        }

        wp_redirect(
            add_query_arg(
                array(
                    'action' => 'bitform_update',
                    'update' => 'bitform-reject',
                    'count'  => count($userids),
                    'role'   => $this->get_role(),
                ),
                $url
            )
        );
        exit();
    }

    protected function doApprove()
    {

        list($userids, $url) = $this->check_user();

        foreach ((array) $userids as $id) {
            $id = (int) $id;

            if (!current_user_can('edit_user', $id)) {
                wp_die(esc_html__('You can&#8217;t edit that user.'), '', array(
                    'back_link' => true,
                ));
            }

            update_user_meta($id, 'bf_activation', 1);
        }

        wp_redirect(
            add_query_arg(
                array(
                    'action' => 'bitform_update',
                    'update' => 'bitform-approved',
                    'count'  => count($userids),
                    'role'   => $this->get_role(),
                ),
                $url
            )
        );
        exit();
    }

    protected function get_role()
    {

        $roles   = array_keys(get_editable_roles());
        $roles[] = 'bitforms_unapproved';
        $role    = false;

        if (isset($_REQUEST['role']) && in_array($_REQUEST['role'], $roles, true)) {
            $role = $_REQUEST['role'];
        }

        return $role;
    }

    public function usersActions($actions, $user_object)
    {

        if ((get_current_user_id() !== $user_object->ID)) {

            $site_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
            $url     = 'site-users-network' === get_current_screen()->id ? add_query_arg(array('id' => $site_id), 'site-users.php') : 'users.php';
            $status=2;
            
            $exitActivation = get_user_meta($user_object->ID, 'bf_activation');
            if($exitActivation){ 
                $status = $exitActivation[0];
             }

            if ($status == 1) {

                $url = wp_nonce_url(
                    add_query_arg(
                        array(
                            'action' => 'bitforms_reject',
                            'user'   => $user_object->ID,
                            'role'   => $this->get_role(),
                        ),
                        $url
                    ),
                    'bitforms-reject-users'
                );

                $actions['bitforms-reject'] = sprintf('<a class="submitapprove" href="%1$s">%2$s</a>', esc_url($url), esc_html__('Reject', 'bitforms-user-registration'));
            } else if($status == 0){

                $url = wp_nonce_url(
                    add_query_arg(
                        array(
                            'action' => 'bitforms_approve',
                            'user'   => $user_object->ID,
                            'role'   => $this->get_role(),
                        ),
                        $url
                    ),
                    'bitforms-approve-users'
                );

                $actions['bitforms-approve'] = sprintf('<a class="submitunapprove" href="%1$s">%2$s</a>', esc_url($url), esc_html__('Approve', 'bitforms-user-registration'));
            }
        }

        return $actions;
    }

    public function user_authenticate($userdata)
    {   
        $status = 1;
        $existActivation = get_user_meta($userdata->ID, 'bf_activation');
        
        if($existActivation){ 
            $status = $existActivation[0]; 
         }
        if (!is_wp_error($userdata)&&
            $status == 0 &&
            $userdata->user_email !== get_bloginfo('admin_email') 
        ) {

            $userdata = new WP_Error(
                'bitform_confirmation_error',
                wp_kses(
                    __('<strong>ERROR:</strong> Your account must be activated before you can login.', 'bitform-user-registration'),
                    array(
                        'strong' => array(),
                    )
                )
            );
        }
        return $userdata;
    }

}
