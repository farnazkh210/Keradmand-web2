<?php

/**
 * User Login
 *
 */

namespace BitCode\BitFormPro\Auth;

use BitCode\BitForm\Core\Util\MailConfig;


/**
 * Provide functionality for Login
 */
class ForgotResetPassword
{

  public function __construct()
  {
    global $wpdb;
    $this->_wpdb = $wpdb;
  }

  public function sendEmailVerification($intDetails, $postData)
  {
    $data = [];
    $decode = json_decode($intDetails->integration_details);
    $userKey = $decode->forgot_map[0]->forgotField;
    $key = $decode->forgot_map[0]->formField;
    $user = get_user_by($userKey, $postData[$key]);

    if ($user) {
      $mailSubject = $decode->sub;
      $mailBody = $decode->body;
      $userLogin = $user->data->user_login;
      $key = get_password_reset_key($user);
      $mailBody = preg_replace("/{customer_name}/", $userLogin, $mailBody);
      $mailBody = preg_replace("/{reset_password_url}/", $decode->redirect_url . '?token=' . $key . '&id=' . $user->data->ID, $mailBody);
      $mailBody = preg_replace("/{site_url}/", home_url(), $mailBody);
      add_filter('wp_mail_content_type', [$this, 'filterMailContentType']);
      (new MailConfig())->sendMail();
      $mailStaus = wp_mail($user->data->user_email, $mailSubject, $mailBody);
      remove_filter('wp_mail_content_type', [$this, 'filterMailContentType']);

      if ($mailStaus == false) {
        $data['success'] = false;
        $data['message'] = "Error Mail Sending !! ";
        return $data;
      }

      $data['message'] = $decode->succ_msg;
      $data['success'] = true;
    } else {
      $data['success'] = false;
      $data['message'] = "We can't find a user with that e-mail address or username.";
    }
    return $data;
  }

  public function filterMailContentType()
  {
    return 'text/html; charset=UTF-8';
  }

  private function resetFieldMapping($resetMap, $fieldValues)
  {
    $fieldData = [];
    foreach ($resetMap as $fieldKey => $fieldPair) {
      if (!empty($fieldPair->resetField) && !empty($fieldPair->formField)) {
        if ($fieldPair->formField === 'custom' && isset($fieldPair->customValue)) {
          $fieldData[$fieldPair->resetField] = $fieldPair->customValue;
        } else {
          $fieldData[$fieldPair->resetField] = $fieldValues[$fieldPair->formField];
        }
      }
    }
    return $fieldData;
  }

  public function resetPassword($intDetails, $postData, $params)
  {
    $data = [];
    $intDetails = is_string($intDetails) ? json_decode($intDetails) : $intDetails;
    $mapping = $this->resetFieldMapping($intDetails->reset_map, $postData);
    $data['success'] = false;
    if (isset($params['id']) && isset($params['token'])) {

      $user = get_user_by("ID", $params['id']);

      if ($user  !== false) {

        $validToken = check_password_reset_key($params['token'], $user->user_login);

        if (is_wp_error($validToken)) {
          $data['message'] = "Access Token not yet valid";
          return $data;
        }
        if (empty($mapping['conf_password']) || empty($mapping['new_password'])) {
          $data['message'] = "The password field cannot be empty.";
          return $data;
        }

        if ($mapping['conf_password'] != $mapping['new_password']) {
          $data['message'] = "The password and confirmation password do not match";
          return $data;
        }

        $result = reset_password($user, $mapping['new_password']);

        if ($result === null) {
          $data['message'] = $intDetails->succ_msg;
          $data['success'] = true;
          $data['redirectPage'] = !empty($intDetails->redirect_url) ? $intDetails->redirect_url : '';
        } else {
          $data['message'] = 'Password reset succeffuly fail !!.';
        }
      } else {
        $data['message'] = 'Invalid User !!.';
      }
    }else { 
      $data['message']="URL must be a valid URL.";
     }
    return $data;
  }
}
