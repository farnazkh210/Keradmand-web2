<?php

/**
 * ZohoSheet Integration
 *
 */

namespace BitCode\BitFormPro\Integration\SendinBlue;

use WP_Error;
use BitCode\BitForm\Core\Util\IpTool;
use BitCode\BitForm\Core\Util\HttpHelper;
use BitCode\BitForm\Core\Integration\IntegrationHandler;
use BitCode\BitFormPro\Integration\SendinBlue\RecordApiHelper;



/**
 * Provide functionality for ZohoCrm integration
 */
class SendinBlueHandler
{
  private $_formID;
  private $_integrationID;
  const APIENDPOINT = 'https://api.sendinblue.com/v3';

  public function __construct($integrationID, $fromID)
  {
    $this->_formID = $fromID;
    $this->_integrationID = $integrationID;
  }

  /**bitforms_zsheet_refresh_worksheet_headers
   * Helps to register ajax function's with wp
   *
   * @return null
   */
  public static function registerAjax()
  {
    add_action('wp_ajax_bitforms_sblue_authorize', array(__CLASS__, 'sendinBlueAuthorize'));
    add_action('wp_ajax_bitforms_sblue_refresh_lists', array(__CLASS__, 'refreshlists'));
    add_action('wp_ajax_bitforms_sblue_headers', array(__CLASS__, 'sendinblueHeaders'));
    add_action('wp_ajax_bitforms_sblue_refresh_template', array(__CLASS__, 'refreshTemplate'));
  }

  /**
   * Process ajax request for generate_token
   *
   * @return JSON zoho crm api response and status
   */
  public static function sendinBlueAuthorize()
  {
    if (isset($_REQUEST['_ajax_nonce']) && wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bitforms_save')) {
      $inputJSON = file_get_contents('php://input');
      $requestsParams = json_decode($inputJSON);
      if (
        empty($requestsParams->api_key)
      ) {
        wp_send_json_error(
          __(
            'Requested parameter is empty',
            'bitform'
          ),
          400
        );
      }

      $apiEndpoint = self::APIENDPOINT . '/account';
      $authorizationHeader["Accept"] = 'application/json';
      $authorizationHeader["api-key"] = $requestsParams->api_key;
      $apiResponse = HttpHelper::get($apiEndpoint, null, $authorizationHeader);

      if (is_wp_error($apiResponse) || $apiResponse->code === 'unauthorized') {
        wp_send_json_error(
          empty($apiResponse->code) ? 'Unknown' : $apiResponse->message,
          400
        );
      }

      wp_send_json_success(true);
    } else {
      wp_send_json_error(
        __(
          'Token expired',
          'bitform'
        ),
        401
      );
    }
  }
  /**
   * Process ajax request for refresh crm modules
   *
   * @return JSON crm module data
   */

  public static function refreshlists()
  {
    if (isset($_REQUEST['_ajax_nonce']) && wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bitforms_save')) {
      $inputJSON = file_get_contents('php://input');
      $requestsParams = json_decode($inputJSON);
      if (
        empty($requestsParams->api_key)
      ) {
        wp_send_json_error(
          __(
            'Requested parameter is empty',
            'bitform'
          ),
          400
        );
      }
      $apiEndpoint = self::APIENDPOINT . '/contacts/lists';
      $authorizationHeader["Accept"] = 'application/json';
      $authorizationHeader["api-key"] = $requestsParams->api_key;
      $sblueResponse = HttpHelper::get($apiEndpoint, null, $authorizationHeader);

      $allList = [];
      if (!is_wp_error($sblueResponse) && empty($sblueResponse->code)) {
        $sblueList = $sblueResponse->lists;

        foreach ($sblueList as $list) {
          $allList[$list->name] = (object) array(
            'id' => $list->id,
            'name' => $list->name
          );
        }
        uksort($allList, 'strnatcasecmp');

        $response['sblueList'] = $allList;
      } else {
        wp_send_json_error(
          $sblueResponse->message,
          400
        );
      }
      wp_send_json_success($response, 200);
    } else {
      wp_send_json_error(
        __(
          'Token expired',
          'bitform'
        ),
        401
      );
    }
  }

  public static function refreshTemplate()
  {
    if (isset($_REQUEST['_ajax_nonce']) && wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bitforms_save')) {
      $inputJSON = file_get_contents('php://input');
      $requestsParams = json_decode($inputJSON);
      if (
        empty($requestsParams->api_key)
      ) {
        wp_send_json_error(
          __(
            'Requested parameter is empty',
            'bitform'
          ),
          400
        );
      }
      $apiEndpoint = self::APIENDPOINT . '/smtp/templates';
      $authorizationHeader["Accept"] = 'application/json';
      $authorizationHeader["api-key"] = $requestsParams->api_key;
      $sblueResponse = HttpHelper::get($apiEndpoint, null, $authorizationHeader);

      $allList = [];
      if (!is_wp_error($sblueResponse) && $sblueResponse->templates) {
        $sblueTemplates = $sblueResponse->templates;

        foreach ($sblueTemplates as $list) {
          $allList[$list->name] = (object) array(
            'id' => $list->id,
            'name' => ucfirst($list->name)
          );
        }
       
        uksort($allList, 'strnatcasecmp');

        $response['sblueTemplates'] = $allList;
      } else {
        wp_send_json_error(
          $sblueResponse->message,
          400
        );
      }
      wp_send_json_success($response, 200);
    } else {
      wp_send_json_error(
        __(
          'Token expired',
          'bitform'
        ),
        401
      );
    }
  }
  public static function sendinblueHeaders()
  {
    if (isset($_REQUEST['_ajax_nonce']) && wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bitforms_save')) {
      $inputJSON = file_get_contents('php://input');
      $queryParams = json_decode($inputJSON);
      if (
        empty($queryParams->api_key)
      ) {
        wp_send_json_error(
          __(
            'Requested parameter is empty',
            'bitform'
          ),
          400
        );
      }
      $apiEndpoint = self::APIENDPOINT . '/contacts/attributes';
      $authorizationHeader["Accept"] = 'application/json';
      $authorizationHeader["api-key"] = $queryParams->api_key;
      $sblueResponse = HttpHelper::get($apiEndpoint, null, $authorizationHeader);
      // var_dump($sblueResponse);die;
      $fields = [];
      if (!is_wp_error($sblueResponse)) {
        $allFields = $sblueResponse->attributes;
        // wp_send_json_success($allFields);
        foreach ($allFields as $field) {
          if ($field->type !== 'float' && !empty($field->type)) {
            $fields[$field->name] = (object) array(
              'fieldId' => $field->name,
              'fieldName' => $field->name
            );
          }
        }
        $fields['Email'] = (object) array('fieldId' => 'email', 'fieldName' => 'Email', 'required' => true);
        $response['sendinBlueField'] = $fields;
        wp_send_json_success($response);
      }
    } else {
      wp_send_json_error(
        __(
          'Token expired',
          'bitform'
        ),
        401
      );
    }
  }

  public function execute(IntegrationHandler $integrationHandler, $integrationData, $fieldValues, $entryID, $logID)
  {
    $integrationDetails = is_string($integrationData->integration_details) ? json_decode($integrationData->integration_details) : $integrationData->integration_details;
    
    $api_key = $integrationDetails->api_key;
    $lists = $integrationDetails->lists;
    $fieldMap = $integrationDetails->field_map;
    $actions = $integrationDetails->actions;
    $defaultDataConf = $integrationDetails->default;

    if (
      empty($api_key)
      || empty($lists)
      || empty($fieldMap)
      || empty($defaultDataConf)
    ) {
      return new WP_Error('REQ_FIELD_EMPTY', __('module, fields are required for Sendinblue api', 'bitform'));
    }
    $recordApiHelper = new RecordApiHelper($api_key, $this->_integrationID, $logID, $entryID);
    $sendinBlueApiResponse = $recordApiHelper->executeRecordApi(
      $lists,
      $defaultDataConf,
      $fieldValues,
      $fieldMap,
      $actions
    );

    if (is_wp_error($sendinBlueApiResponse)) {
      return $sendinBlueApiResponse;
    }
    return $sendinBlueApiResponse;
  }
}
