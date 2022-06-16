<?php

namespace BitCode\BitForm\Frontend\Ajax;

use WP_Error;
use BitCode\BitForm\Core\Util\HttpHelper;
use BitCode\BitForm\Core\Util\MailNotifier;
use BitCode\BitForm\Admin\Form\AdminFormManager;
use BitCode\BitForm\Core\Database\FormEntryModel;
use BitCode\BitForm\Core\Database\FormEntryLogModel;
use BitCode\BitForm\Core\WorkFlow\WorkFlowRunHelper;
use BitCode\BitForm\Core\Database\FormEntryMetaModel;
use BitCode\BitForm\Frontend\Form\FrontendFormManager;
use BitCode\BitForm\Core\Integration\IntegrationHandler;
use BitCode\BitForm\Core\Form\Validator\FormFieldValidator;
use BitCode\BitForm\Core\Util\ApiResponse as UtilApiResponse;
use BitCode\BitForm\Core\Util\FieldValueHandler;

final class FrontendAjax
{
    public function register()
    {
        add_action('wp_ajax_nopriv_bitforms_submit_form', [$this, 'submit_form']);
        add_action('wp_ajax_bitforms_submit_form', [$this, 'submit_form']);
        add_action('wp_ajax_bitforms_before_submit_validate', [$this, 'beforeSubmittedValidate']);
        add_action('wp_ajax_nopriv_bitforms_before_submit_validate', [$this, 'beforeSubmittedValidate']);
        add_action('wp_ajax_nopriv_bitforms_trigger_workflow', [$this, 'triggerWorkFlow']);
        add_action('wp_ajax_bitforms_trigger_workflow', [$this, 'triggerWorkFlow']);
    }

    public function beforeSubmittedValidate()
    {
        $form_id = str_replace('bitforms_', null, $_POST['bitforms_id']);
        $FrontendFormManager = new FrontendFormManager($form_id);
        $validateStatus = $FrontendFormManager->beforeSubmittedValidate();
        if (is_wp_error($validateStatus)) {
            wp_send_json_error($validateStatus->get_error_message(), 400);
        } else {
            wp_send_json_success($validateStatus);
        }
    }

    public function submit_form()
    {
        \ignore_user_abort();
        $form_id = str_replace('bitforms_', null, $_POST['bitforms_id']);
        $FrontendFormManager = new FrontendFormManager($form_id);
        $submitSatus = $FrontendFormManager->handleSubmission();
        if (is_wp_error($submitSatus)) {
            wp_send_json_error($submitSatus->get_error_message(), 400);
        } else {
            wp_send_json_success($submitSatus);
        }
    }

    public function triggerWorkFlow()
    {
        ignore_user_abort();
        $inputJSON = file_get_contents('php://input');
        if ($inputJSON) {
            $request = is_string($inputJSON) ? \json_decode($inputJSON) : $inputJSON;
            $submitted_fields = [];
            if (isset($request->id) && isset($request->cronNotOk)) {
                $formID = str_replace('bitforms_', null, $request->id);
                if (!wp_verify_nonce($request->token, $request->id) && is_user_logged_in()) {
                    wp_send_json_error();
                }
                $cronNotOk = $request->cronNotOk;
                $entryID = $cronNotOk[0];
                $logID = $cronNotOk[1];
                $entryLog = new FormEntryLogModel();
                if (isset($cronNotOk[2]) && \is_int($cronNotOk[2])) {
                    $queueudEntry = $entryLog->get(
                        "response_obj",
                        ["id" => $cronNotOk[2]]
                    );
                    if ($queueudEntry) {
                        if (!empty($queueudEntry[0]->response_obj) && \strpos($queueudEntry[0]->response_obj, "processed") > 0) {
                            wp_send_json_error();
                        }
                    } else {
                        wp_send_json_error();
                    }
                } else {
                    wp_send_json_error();
                }
                $trnasientData = get_transient("bitform_trigger_transient_{$entryID}");

                if (!empty($trnasientData)) {
                    delete_transient("bitform_trigger_transient_{$entryID}");
                    $triggerData = is_string($trnasientData) ? json_decode($trnasientData) : $trnasientData;
                } else {
                    $formManager = new AdminFormManager($formID);
                    if (!$formManager->isExist()) {
                        return wp_send_json(new WP_Error('trigger_empty_form', __('provided form does not exists', 'bit-form')));
                    }
                    $formEntryModel = new FormEntryModel();
                    $entryMeta = new FormEntryMetaModel();

                    $formEntry = $formEntryModel->get(
                        "*",
                        array(
                            'form_id' => $formID,
                            'id' => $entryID,
                        )
                    );

                    if (!$formEntry) {
                        return new WP_Error('trigger_empty_form', __('provided form entries does not exists', 'bit-form'));
                    }
                    $formEntryMeta = $entryMeta->get(
                        array(
                            'meta_key',
                            'meta_value',
                        ),
                        array(
                            'bitforms_form_entry_id' => $entryID,
                        )
                    );
                    $entries = array();
                    foreach ($formEntryMeta as $key => $value) {
                        $entries[$value->meta_key] = $value->meta_value;
                    }
                    $formContent = $formManager->getFormContent();
                    $submitted_fields = $formContent->fields;
                    foreach ($submitted_fields as $key => $value) {
                        if (isset($entries[$key])) {
                            $submitted_fields->{$key}->val = $entries[$key];
                            $submitted_fields->{$key}->name = $key;
                        }
                    }
                    $workFlowRunHelper = new WorkFlowRunHelper($formID);
                    $workFlowreturnedOnSubmit = $workFlowRunHelper->executeOnSubmit(
                        'create',
                        $submitted_fields,
                        $entries,
                        $entryID,
                        $logID
                    );

                    $triggerData = isset($workFlowreturnedOnSubmit['triggerData']) ? $workFlowreturnedOnSubmit['triggerData'] : null;
                    $triggerData['fields'] = $entries;
                }
                if (!empty($triggerData)) {
                    if (isset($triggerData['mail'])) {
                        $formManager = new AdminFormManager($formID);
                        $formContent = $formManager->getFormContent();
                        $submitted_fields = $formContent->fields;
                        $fieldValueForMail = FieldValueHandler::formatFieldValueForMail($submitted_fields, $triggerData['fields']);
                        foreach ($triggerData['mail'] as $value) {
                            MailNotifier::notify($value, $triggerData['formID'], $fieldValueForMail, $entryID);
                        }
                    }
                    do_action("bitforms_exec_integrations", $triggerData['integrations'], $triggerData['fields'], $triggerData['formID'], $triggerData['entryID'], $triggerData['logID']);
                    if (isset($cronNotOk[2]) && \is_int($cronNotOk[2])) {
                        $queueuEntry = $entryLog->update(
                            array(
                                "response_type" => "success",
                                "response_obj" => json_encode(['status' => 'processed']),
                            ),
                            ["id" => $cronNotOk[2]]
                        );
                    }
                }
            }
        }

        wp_send_json_success();
    }
}
