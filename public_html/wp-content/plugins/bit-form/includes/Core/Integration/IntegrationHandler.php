<?php

namespace BitCode\BitForm\Core\Integration;

use BitCode\BitForm\Admin\Form\AdminFormManager;
use BitCode\BitForm\Core\Util\MailNotifier;
use BitCode\BitForm\Core\Database\IntegrationModel;
use BitCode\BitForm\Core\Database\FormEntryLogModel;
use BitCode\BitForm\Core\Util\FieldValueHandler;

final class IntegrationHandler
{
    private static $_formID;
    private static $_integrationModel;
    private $_user_details;

    /**
     * Constructor of Integration Handler
     *
     * @param  Integer $formID       If Integration is accessible globally then
     *                               $formID will be 0 and category app
     * @param  Array   $user_details Details of user accessing data
     * @return void
     */
    public function __construct($formID, $user_details = null)
    {
        static::$_formID = $formID;
        static::$_integrationModel = new IntegrationModel();
        $this->_user_details = $user_details;
    }

    public function getAIntegration($integrationID, $integrationCategory = null, $integrationType = null)
    {
        $conditions = [
            'form_id' => static::$_formID,
            'id' => $integrationID,
        ];
        if (!is_null($integrationType)) {
            $conditions = array_merge($conditions, ['integration_type' => $integrationType]);
        }
        if (!is_null($integrationCategory)) {
            $conditions = array_merge($conditions, ['category' => $integrationCategory]);
        }
        return static::$_integrationModel->get(
            [
                'id',
                'integration_name',
                'integration_type',
                'integration_details',
                'form_id'
            ],
            $conditions
        );
    }

    public function getAllIntegration($integrationCategory = null, $integrationType = null, $status = null, $id = null)
    {
        $conditions = [
            'form_id' => static::$_formID
        ];
        if (!is_null($integrationType)) {
            $conditions = array_merge($conditions, ['integration_type' => $integrationType]);
        }

        if (!is_null($integrationCategory)) {
            $conditions = array_merge($conditions, ['category' => $integrationCategory]);
        }
        if (!is_null($status)) {
            $conditions = array_merge($conditions, ['status' => 1]);
        }
        if (!is_null($id)) {
            $conditions = array_merge($conditions, ['id' => $id]);
        }
        return static::$_integrationModel->get(
            [
                'id',
                'form_id',
                'integration_name',
                'integration_type',
                'integration_details',
                'status'
            ],
            $conditions
        );
    }

    public function getIntegrationWithoutFormId($integrationCategory = null, $integrationType = null, $status = null, $id = null)
    {
        $conditions = [];
        if (!is_null($integrationType)) {
            $conditions = array_merge($conditions, ['integration_type' => $integrationType]);
        }

        if (!is_null($integrationCategory)) {
            $conditions = array_merge($conditions, ['category' => $integrationCategory]);
        }
        if (!is_null($status)) {
            $conditions = array_merge($conditions, ['status' => 1]);
        }
        if (!is_null($id)) {
            $conditions = array_merge($conditions, ['id' => $id]);
        }
        return static::$_integrationModel->get(
            [
                'id',
                'form_id',
                'integration_name',
                'integration_type',
                'integration_details',
                'status'
            ],
            $conditions
        );
    }

    public function saveIntegration($integrationName, $integrationType, $integrationDetails, $integrationCategory, $status = null)
    {
        if ($status == null) {
            $status = 1;
        }
        return static::$_integrationModel->insert(
            [
                'integration_name' => $integrationName,
                'integration_type' => $integrationType,
                'integration_details' => $integrationDetails,
                'category' => $integrationCategory,
                'form_id' => static::$_formID,
                'user_id' => $this->_user_details['id'],
                'user_ip' => $this->_user_details['ip'],
                'status' => $status,
                'created_at' => $this->_user_details['time'],
                'updated_at' => $this->_user_details['time']
            ]
        );
    }

    public function updateIntegration($integrationID, $integrationName, $integrationType, $integrationDetails, $integrationCategory, $status = null)
    {
        if ($status === null) {
            $status = 1;
        }
        return static::$_integrationModel->update(
            [
                'integration_name' => $integrationName,
                'integration_type' => $integrationType,
                'integration_details' => $integrationDetails,
                'category' => $integrationCategory,
                'form_id' => static::$_formID,
                'user_id' => $this->_user_details['id'],
                'user_ip' => $this->_user_details['ip'],
                'status' => $status,
                'updated_at' => $this->_user_details['time']
            ],
            [
                'id' => $integrationID
            ]
        );
    }

    public function duplicateAllInAForm($oldFormId)
    {
        $integCols = ['integration_name', 'integration_type', 'integration_details', 'category', 'form_id', 'user_id', 'user_ip', 'status', 'created_at', 'updated_at'];
        $integDupData = [
            'integration_name',
            'integration_type',
            'integration_details',
            'category',
            static::$_formID,
            $this->_user_details['id'],
            $this->_user_details['ip'],
            'status',
            $this->_user_details['time'],
            $this->_user_details['time']
        ];
        return static::$_integrationModel->duplicate($integCols, $integDupData, ['form_id' => $oldFormId]);
    }

    public function deleteIntegration($integrationID)
    {
        return static::$_integrationModel->delete(
            [
                'id' => $integrationID,
                'form_id' => static::$_formID,
            ]
        );
    }

    public static function replaceFieldWithValue($dataToReplaceField, $fieldValues)
    {
        if (empty($dataToReplaceField)) {
            return false;
        }
        if (is_string($dataToReplaceField)) {
            $dataToReplaceField = static::replaceFieldWithValueHelper($dataToReplaceField, $fieldValues);
        } elseif (is_array($dataToReplaceField)) {
            foreach ($dataToReplaceField as $field => $value) {
                if (is_array($value) && count($value) === 1) {
                    $dataToReplaceField[$field] = static::replaceFieldWithValueHelper($value[0], $fieldValues);
                } elseif (is_array($value)) {
                    $dataToReplaceField[$field] = static::replaceFieldWithValue($value, $fieldValues);
                } else {
                    $dataToReplaceField[$field] = static::replaceFieldWithValueHelper($value, $fieldValues);
                }
            }
        }
        return $dataToReplaceField;
    }

    private static function replaceFieldWithValueHelper($stringToReplaceField, $fieldValues)
    {
        if (empty($stringToReplaceField)) {
            return $stringToReplaceField;
        }
        $fieldPattern = '/\${\w[^ ${}]*}/';
        preg_match_all($fieldPattern, $stringToReplaceField, $matchedField);
        $uniqueFieldsInStr = array_unique($matchedField[0]);
        foreach ($uniqueFieldsInStr as $key => $value) {
            $fieldName = substr($value, 2, strlen($value) - 3);
            if (!empty($fieldValues[$fieldName])) {
                $stringToReplaceField = is_string($fieldValues[$fieldName]) ? str_replace($value, $fieldValues[$fieldName], $stringToReplaceField) :
                    wp_json_encode($fieldValues[$fieldName]);
            }
        }
        return $stringToReplaceField;
    }

    public static function maybeSetCronForIntegration($workFlowReturnedData, $opType)
    {
        $responseData = [];

        if (isset($workFlowReturnedData['message'])) {
            $responseData['message'] = $workFlowReturnedData['message'];
        }
        if (isset($workFlowReturnedData['redirectPage'])) {
            $responseData['redirectPage'] = $workFlowReturnedData['redirectPage'];
        }
        if ($opType === 'update' && isset($workFlowReturnedData['updatedData'])) {
            $responseData['updatedData'] = $workFlowReturnedData['updatedData'];
        }
        if (!isset($workFlowReturnedData['triggerData'])) {
            return $responseData;
        }
        $triggerData = $workFlowReturnedData['triggerData'];
        // if (function_exists('fastcgi_finish_request')) {
        //     var_dump($triggerData['integrations'], 'beforeif');
        // }

        if (function_exists('fastcgi_finish_request') || !wp_doing_ajax()) {
            if (!headers_sent()) {
                header('Connection: close');
                $contentType = wp_doing_ajax() || defined('REST_REQUEST') ? 'application/json' : 'text/html';
                header('Content-Type: ' . $contentType . '; charset=' . get_option('blog_charset'));
                status_header(200);
                $response = [
                    'success' => true,
                    'data' => $responseData,
                ];
            }
            ob_start();
            echo wp_doing_ajax() || defined('REST_REQUEST') ? wp_json_encode($response) : $workFlowReturnedData['message'];
            ob_end_flush();
            flush();
            session_write_close();
            // if (function_exists('fastcgi_finish_request')) {
            //     fastcgi_finish_request();
            // }
            //var_dump('after');
            if (isset($triggerData['mail'])) {
                $formManager = new AdminFormManager($triggerData['formID']);
                $formContent = $formManager->getFormContent();
                $submitted_fields = $formContent->fields;
                $fieldValueForMail = FieldValueHandler::formatFieldValueForMail($submitted_fields, $workFlowReturnedData['fields']);
                foreach ($triggerData['mail'] as $value) {
                    MailNotifier::notify($value, $triggerData['formID'], $fieldValueForMail, $triggerData['entryID']);
                }
            }
            do_action('bitforms_exec_integrations', $triggerData['integrations'], $workFlowReturnedData['fields'], $triggerData['formID'], $triggerData['entryID'], $triggerData['logID']);

            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            if (defined('REST_REQUEST') || wp_doing_ajax()) {
                die;
            }
            return $workFlowReturnedData;
        }

        $entryID = $triggerData['entryID'];
        if (isset($workFlowReturnedData['cron']) && $workFlowReturnedData['cron'] && !isset($triggerData['mail'])) {
            $eventScheuled = wp_schedule_single_event(time(), 'bitforms_exec_integrations', [$triggerData['integrations'], $workFlowReturnedData['fields'], $triggerData['formID'], $triggerData['entryID'], $triggerData['logID']]);
            $scheduleTime = wp_next_scheduled('bitforms_exec_integrations', [$triggerData['integrations'], $workFlowReturnedData['fields'], $triggerData['formID'], $triggerData['entryID'], $triggerData['logID']]);
            $responseData['cron'] = get_site_url(null, "/wp-cron.php?doing_wp_cron&{$scheduleTime}");
        } elseif (!empty($triggerData['integrations']) || !empty($triggerData['mail'])) {
            $triggerData['fields'] = $workFlowReturnedData['fields'];
            set_transient("bitform_trigger_transient_{$entryID}", $triggerData, HOUR_IN_SECONDS);
            $entryLog = new FormEntryLogModel();
            $queueuEntry = $entryLog->log_history_insert(
                [
                    'log_id' => $triggerData['logID'],
                    'integration_id' => 0,
                    'api_type' => wp_json_encode(['type' => 'trigger', 'type_name' => 'Workflow', 'on' => $opType]),
                    'response_type' => 'queued',
                    'response_obj' => json_encode(['status' => 'queued']),
                    'created_at' => current_time('mysql'),
                ]
            );
            $responseData['cronNotOk'] = [
                $triggerData['entryID'],
                $triggerData['logID'],
                $queueuEntry
            ];
        }

        return $responseData;
    }
}
