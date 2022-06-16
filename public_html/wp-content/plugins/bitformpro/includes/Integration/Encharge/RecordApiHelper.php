<?php

/**
 * Encharge Record Api
 */

namespace BitCode\BitFormPro\Integration\Encharge;

use BitCode\BitForm\Core\Util\HttpHelper;
use BitCode\BitForm\Core\Util\ApiResponse as UtilApiResponse;
use BitCode\BitForm\Core\Database\FormEntryLogModel;


/**
 * Provide functionality for Record insert
 */
class RecordApiHelper
{
  private $_defaultHeader;
  private $_integrationID;
  private $_logID;
  private $_logResponse;
  private $_entryID;
  private $_defaultDataConf;

  function __construct($api_key, $integId, $logID, $entryID)
  {
    // wp_send_json_success($tokenDetails);
    $this->_defaultHeader["Content-Type"] = 'application/json';
    $this->_defaultHeader["X-Encharge-Token"] = $api_key;
    $this->_integrationID = $integId;
    $this->_logID = $logID;
    $this->_logResponse = new UtilApiResponse();
    $this->_entryID = $entryID;
  }

  /**
   * serd data to api
   * @return json response
   */
  public function insertRecord($data)
  {
    $insertRecordEndpoint = "https://api.encharge.io/v1/people";
    return HttpHelper::post($insertRecordEndpoint, $data, $this->_defaultHeader);
  }

  public function executeRecordApi($fieldValues, $fieldMap, $tags)
  {
    $fieldData = [];

    foreach ($fieldMap as $fieldKey => $fieldPair) {
      if (!empty($fieldPair->enChargeFields)) {
        if ($fieldPair->formField === 'custom' && isset($fieldPair->customValue)) {
          $fieldData[$fieldPair->enChargeFields] = $fieldPair->customValue;
        } else {
          $fieldData[$fieldPair->enChargeFields] = $fieldValues[$fieldPair->formField];
        }
      }
    }
    if($tags !== null) {
      $fieldData['tags'] = $tags;
    }
    // wp_send_json_success($fieldData);
    $recordApiResponse = $this->insertRecord(json_encode($fieldData));
    $type = 'insert';

    if ($recordApiResponse && isset($recordApiResponse->user)) {
      $recordApiResponse = [
        'status' => 'success',
        'email' => $recordApiResponse->user->email
      ];
      $this->_logResponse->apiResponse($this->_logID, $this->_integrationID, ['type' =>  'record', 'type_name' => $type], 'success', $recordApiResponse);
    } else {
      $this->_logResponse->apiResponse($this->_logID, $this->_integrationID, ['type' =>  'record', 'type_name' => $type], 'error', $recordApiResponse);
    }
    return $recordApiResponse;
  }
}
