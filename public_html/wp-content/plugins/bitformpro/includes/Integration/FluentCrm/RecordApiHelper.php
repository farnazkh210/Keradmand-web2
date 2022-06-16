<?php

/**
 * Fluent CRM Record Api
 *
 */

namespace BitCode\BitFormPro\Integration\FluentCrm;

use BitCode\BitForm\Core\Util\ApiResponse as UtilApiResponse;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\Includes\Helpers\Arr;

/**
 * Provide functionality for Record insert
 */
class RecordApiHelper
{
  public function __construct($integId, $logID, $entryID)
  {
    $this->_integrationID = $integId;
    $this->_logID = $logID;
    $this->_logResponse = new UtilApiResponse();
    $this->_entryID = $entryID;
  }

  public function insertRecord($data, $actions)
  {
    $contact = Arr::only($data, ['first_name', 'last_name', 'email']);

    // for full name 
    if (!$contact['first_name'] && !$contact['last_name']) {
      $fullName = Arr::get($data, 'full_name');
      if ($fullName) {
        $nameArray = explode(' ', $fullName);
        if (count($nameArray) > 1) {
          $contact['last_name'] = array_pop($nameArray);
          $contact['first_name'] = implode(' ', $nameArray);
        } else {
          $contact['first_name'] = $fullName;
        }
      }
    }

    // for exsist user 
    $subscriber = Subscriber::where('email', $contact['email'])->first();

    if ($subscriber && isset($actions->skip_if_exists) && $actions->skip_if_exists ) {
      $response = [
        'success' => false,
        'messages' => 'Contact already exists!'
      ];
    } else {
      // for subscirber
      if (!$subscriber) {
        if (isset($actions->double_opt_in) && $actions->double_opt_in ) {
          $contact['status'] = 'pending';
        } else {
          $contact['status'] = 'subscribed';
        }

        if ($listId = Arr::get($data, 'list_id')) {
          $contact['lists'] = [$listId];
        }
        $contact['tags'] = $data['tags'];

        $subscriber = FluentCrmApi('contacts')->createOrUpdate($contact, false, false);

        if ($subscriber->status === 'pending') {
          $subscriber->sendDoubleOptinEmail();
        }
        if ($subscriber) {
          $response = [
            'success' => true,
            'messages' => 'Insert successfully!'
          ];
        } else {
          $response = [
            'success' => false,
            'messages' => 'Somthing wrong!'
          ];
        }
      } else {
        if ($listId = Arr::get($data, 'list_id')) {
          $contact['lists'] = [$listId];
        }

        $contact['tags'] = $data['tags'];

        $hasDouBleOptIn = isset($actions->double_opt_in) && $actions->double_opt_in;

        $forceSubscribed = !$hasDouBleOptIn && ($subscriber->status != 'subscribed');

        if ($forceSubscribed) {
          $contact['status'] = 'subscribed';
        }
        $subscriber = FluentCrmApi('contacts')->createOrUpdate($contact, $forceSubscribed, false);

        if ($hasDouBleOptIn && ($subscriber->status === 'pending' || $subscriber->status === 'unsubscribed')) {
          $subscriber->sendDoubleOptinEmail();
        }
        if ($subscriber) {
          $response = [
            'success' => true,
            'messages' => 'Insert successfully!'
          ];
        } else {
          $response = [
            'success' => false,
            'messages' => 'Somthing wrong!'
          ];
        }
      }
    }
    return $response;
  }

  public function executeRecordApi(
    $fieldValues,
    $fieldMap,
    $actions,
    $list_id,
    $tags
  ) {
    $fieldData = [];

    foreach ($fieldMap as $fieldKey => $fieldPair) {
      if (!empty($fieldPair->fluentCRMField)) {
        if ($fieldPair->formField === 'custom' && isset($fieldPair->customValue)) {
          $fieldData[$fieldPair->fluentCRMField] = $fieldPair->customValue;
        } else {
          $fieldData[$fieldPair->fluentCRMField] = $fieldValues[$fieldPair->formField];
        }
      }
    }
    $fieldData['list_id'] = $list_id;
    $fieldData['tags'] = $tags;

    $recordApiResponse = $this->insertRecord($fieldData, $actions);

    if ($recordApiResponse['success']) {
      $this->_logResponse->apiResponse($this->_logID, $this->_integrationID, ['type' =>  'record', 'type_name' => 'insert'], 'success', $recordApiResponse);
    } else {
      $this->_logResponse->apiResponse($this->_logID, $this->_integrationID, ['type' =>  'record', 'type_name' => 'insert'], 'error', $recordApiResponse);
    }
    return $recordApiResponse;
  }
}
