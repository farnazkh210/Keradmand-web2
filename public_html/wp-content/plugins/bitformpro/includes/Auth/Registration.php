<?php

/**
 * User Registratoion
 *
 */

namespace BitCode\BitFormPro\Auth;

use BitCode\BitForm\Core\Util\MailConfig;

/**
 * Provide functionality for USER Registration
 */
class Registration
{
  private $_formID;

  public function __construct()
  {
    global $wpdb;
    $this->_wpdb = $wpdb;
    add_action('set_logged_in_cookie', [$this, 'updateSessionCookie'], 10, 1);
  }

  /**
   * Helps to register ajax function's with wp
   *
   * @return null
   */


  private function userFieldMapping($user_map, $fieldValues)
  {
    $fieldData = [];
    foreach ($user_map as $fieldKey => $fieldPair) {
      if (!empty($fieldPair->userField) && !empty($fieldPair->formField)) {
        if ($fieldPair->formField === 'custom' && isset($fieldPair->customValue)) {
          $fieldData[$fieldPair->userField] = $fieldPair->customValue;
        } else {
          $fieldData[$fieldPair->userField] = $fieldValues[$fieldPair->formField];
        }
      }
    }

    if (!empty($fieldData['user_email'])) {
      if (isset($fieldData['user_login']) && empty($fieldData['user_login'])) {
        $fieldData['user_login'] = $fieldData['user_email'];
      } else if (!isset($fieldData['user_login'])) {
        $fieldData['user_login'] = $fieldData['user_email'];
      }
    }
    if (isset($fieldData['user_pass']) && empty($fieldData['user_pass'])) {
      $fieldData['user_pass'] = random_int(100000, 999999);
    } elseif (!isset($fieldData['user_pass'])) {
      $fieldData['user_pass'] = random_int(100000, 999999);
    }
    return $fieldData;
  }

  public function filterMailContentType()
  {
    return 'text/html; charset=UTF-8';
  }


  public function mailSend($intDetail)
  {

    $user = get_user_by("ID", $intDetail->user_id);
    $mailSubject = $intDetail->sub;
    $mailBody = $intDetail->body;
    $userLogin = $user->data->user_login;
    $mailBody = preg_replace("/{customer_name}/", $userLogin, $mailBody);

    $url = add_query_arg(array(
      'bit_activation_key' => $intDetail->key,
      'f_id' => $intDetail->form_id,
      'user_id' => $intDetail->user_id,
    ), home_url('/'));

    $mailBody = preg_replace("/{email}/", $user->data->user_email, $mailBody);
    $mailBody = preg_replace("/{activation_url}/", $url, $mailBody);

    add_filter('wp_mail_content_type', [$this, 'filterMailContentType']);

    (new MailConfig())->sendMail();

    wp_mail($user->data->user_email, $mailSubject, $mailBody);
    remove_filter('wp_mail_content_type', [$this, 'filterMailContentType']);
  }

  private function insertUserMeta($user_map, $fieldValues, $userId)
  {
    $mappingField = [];

    foreach ($user_map as $fieldKey => $fieldPair) {
      if (property_exists($fieldPair, "metaField")) {
        $mappingField[$fieldKey]['name'] = $fieldPair->metaField;
        if (!empty($fieldPair->metaField) && !empty($fieldPair->formField)) {
          if ($fieldPair->formField === 'custom' && isset($fieldPair->customValue)) {
            $mappingField[$fieldKey]['value'] =  $fieldPair->customValue;
          } else {
            $mappingField[$fieldKey]['value'] = $fieldValues[$fieldPair->formField];
          }
        }
      }
    }

    foreach ($mappingField as $userMeta) {
      if (isset($userMeta['name']) && isset($userMeta['value'])) {
        add_user_meta($userId,  $userMeta['name'], $userMeta['value'], true);
      }
    }
  }

  private function notification($intDetails, $userId)
  {

    (new MailConfig())->sendMail();
    if (isset($intDetails->user_notify)) {
      wp_new_user_notification($userId, null, 'user');
    }

    if (isset($intDetails->admin_notify)) {
      wp_new_user_notification($userId, null, 'admin');
    }
  }

  public function register($integrationDetails, $fieldValues, $formId)
  {
    $response = [];

    if (is_user_logged_in()) {
      $response['success'] = false;
      $response['message'] = "You are already logged in.";
      return $response;
    }

    $intDetails = is_string($integrationDetails) ? json_decode($integrationDetails) : $integrationDetails;
    $userData = $this->userFieldMapping($intDetails->user_map, $fieldValues);
    $userData['role'] = isset($intDetails->user_role) ? $intDetails->user_role : '';
    $userId = wp_insert_user($userData);

    if (is_wp_error($userId) || !$userId) {
      $response['message'] = is_wp_error($userId) ? $userId->get_error_message() : 'error';
      $response['success'] = false;
    } else {
      $response['message'] = !empty($intDetails->succ_msg) ? $intDetails->succ_msg : '';
      $response['success'] = true;
      $response['auth_type'] = 'register';
      $response['redirect_url'] = !empty($intDetails->redirect_url) ? $intDetails->redirect_url : '';
    }

    $this->notification($intDetails, $userId);
    $this->insertUserMeta($intDetails->meta_map, $fieldValues, $userId);

    if (isset($intDetails->activation)) {
      if ($intDetails->activation == "admin_review") {
        add_user_meta($userId, 'bf_activation', 0);
      } else if ($intDetails->activation == "email_verify") {
        $key = uniqid();
        add_user_meta($userId, 'bf_activation_code', $key, true);
        add_user_meta($userId, 'bf_activation', 0);
        $intDetails->user_id = $userId;
        $intDetails->key = $key;
        $intDetails->form_id = $formId;
        $this->mailSend($intDetails);
      } else {
        add_user_meta($userId, 'bf_activation', 1);

        if (isset($intDetails->auto_login)) {
          wp_set_current_user($userId);
          wp_set_auth_cookie($userId);
        }
      }
    } else {
      add_user_meta($userId, 'bf_activation', 1);
    }

    return $response;
  }

  public function updateSessionCookie($logged_in_cookie)
  {
    $_COOKIE[LOGGED_IN_COOKIE] = $logged_in_cookie;
  }
}
