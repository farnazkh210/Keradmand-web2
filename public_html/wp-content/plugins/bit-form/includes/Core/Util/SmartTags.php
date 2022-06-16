<?php

namespace BitCode\BitForm\Core\Util;

use BitCode\BitForm\Core\Util\IpTool;

/**
 * Class handling SmartTags
 *
 * @since 1.0.0
 */

final class SmartTags
{

    public static function smartTagFieldKeys()
    {
        return [
            '_bf_current_time',
            '_bf_custom_date_format',
            '_bf_admin_email',
            '_bf_date_default',
            '_bf_date.m/d/y',
            '_bf_date.d/m/y',
            '_bf_date.y/m/d',
            '_bf_time',
            '_bf_user_email',
            '_bf_weekday',
            '_bf_http_referer_url',
            '_bf_ip_address',
            '_bf_operating_system',
            '_bf_browser_name',
            '_bf_random_digit_num',
            '_bf_user_id',
            '_bf_user_first_name',
            '_bf_user_last_name',
            '_bf_user_display_name',
            '_bf_user_nice_name',
            '_bf_user_login_name',
            '_bf_user_email',
            '_bf_user_url',
            '_bf_current_user_role',
            '_bf_author_id',
            '_bf_author_display',
            '_bf_author_email',
            '_bf_site_title',
            '_bf_site_description',
            '_bf_site_url',
            '_bf_wp_local_codes',
            '_bf_post_id',
            '_bf_post_name',
            '_bf_post_title',
            '_bf_post_date',
            '_bf_post_modified_date',
            '_bf_post_url',
            '_bf_query_param',
            '_bf_user_meta_key',
        ];
    }

    public static function getPostUserData($referer = false)
    {
        $post = [];
        $postId = url_to_postid($referer ? $_SERVER['HTTP_REFERER'] : $_SERVER['REQUEST_URI']);

        if ($postId) {
            $post = get_post($postId, 'OBJECT');
        }

        $user = wp_get_current_user();
        $user_roles = $user->roles;
        
        if(!is_wp_error($user_roles) && count($user_roles) > 0){
            $user->current_user_role = $user_roles[0];
         }

        $postAuthorInfo = [];
        if (isset($post->post_author)) {
            $postAuthorInfo = get_user_by('ID', $post->post_author);
        }

        return array('user' => $user, 'post' => $post, 'post_author_info' => $postAuthorInfo);

    }

    public static function getSmartTagValue($key, $data, $customValue = '')
    {
        $userDetail = IpTool::getUserDetail();
        $device = explode('|', $userDetail['device']);
        if (is_array($device)) {
            $browser = $device[0];
            $operating = $device[1];
        }

        $userMeta = "";
        if (!empty($customValue)) {
            $existMeta = get_user_meta($data['user']->ID, $customValue, true);
            if ($existMeta && is_string($existMeta)) {
                $userMeta = $existMeta;
            }
        }

        switch ($key) {
            case "_bf_current_time":
                return date('Y-m-d H:i:s');

            case "_bf_custom_date_format":
                return !empty($customValue) ? date($customValue) : date('Y-m-d H:i:s');

            case "_bf_admin_email":
                return get_bloginfo('admin_email');

            case "_bf_date_default":
                return wp_date(get_option('date_format'));

            case "_bf_date.m/d/y":
                return wp_date('m/d/y');

            case "_bf_date.d/m/y":
                return wp_date('d/m/y');

            case "_bf_date.y/m/d":
                return wp_date('Y-m-d');

            case "_bf_time":
                return wp_date(get_option('time_format'));

            case "_bf_weekday":
                return wp_date('l');

            case "_bf_http_referer_url":
                return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

            case "_bf_ip_address":
                return IpTool::getIP();

            case "_bf_browser_name":
                return isset($browser) ? $browser : '';

            case "_bf_operating_system":
                return isset($operating) ? $operating : '';

            case "_bf_random_digit_num":
                return time();

            case "_bf_user_id":
                return (isset($data['user']->ID) ? $data['user']->ID : " ");

            case "_bf_user_first_name":
                return (isset($data['user']->user_firstname) ? $data['user']->user_firstname : "");

            case "_bf_user_last_name":
                return (isset($data['user']->user_lastname) ? $data['user']->user_lastname : "");

            case "_bf_user_display_name":
                return (isset($data['user']->display_name) ? $data['user']->display_name : "");

            case "_bf_user_nice_name":
                return (isset($data['user']->user_nicename) ? $data['user']->user_nicename : "");

            case "_bf_user_login_name":
                return (isset($data['user']->user_login) ? $data['user']->user_login : "");

            case "_bf_user_email":
                return (isset($data['user']->user_email) ? $data['user']->user_email : "");

            case "_bf_user_url":
                return (isset($data['user']->user_url) ? $data['user']->user_url : "");
            
            case "_bf_current_user_role":
                return (isset($data['user']->current_user_role) ? $data['user']->current_user_role : "");

            case "_bf_user_meta_key":
                return $userMeta;

            case "_bf_author_id":
                return (isset($data['post_author_info']->ID) ? $data['post_author_info']->ID : "");

            case "_bf_author_display":
                return (isset($data['post_author_info']->display_name) ? $data['post_author_info']->display_name : "");

            case "_bf_author_email":
                return (isset($data['post_author_info']->user_email) ? $data['post_author_info']->user_email : "");

            case "_bf_site_title":
                return get_bloginfo('name');

            case "_bf_site_description":
                return get_bloginfo('description');

            case "_bf_site_url":
                return get_bloginfo('url');

            case "_bf_wp_local_codes":
                return get_bloginfo('language');

            case "_bf_post_id":
                return (is_object($data['post']) ? $data['post']->ID : "");

            case "_bf_post_name":
                return (is_object($data['post']) ? $data['post']->post_name : "");

            case "_bf_post_title":
                return (is_object($data['post']) ? $data['post']->post_title : "");

            case "_bf_post_date":
                return (is_object($data['post']) ? $data['post']->post_date : "");

            case "_bf_post_modified_date":
                return (is_object($data['post']) ? $data['post']->post_modified : "");

            case "_bf_post_url":
                return (is_object($data['post']) ? get_permalink($data['post']->ID) : "");

            case "_bf_query_param":
                return isset($_GET[$customValue]) ? urldecode(stripslashes($_GET[$customValue])) : '';
        }

    }
}
