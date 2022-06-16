<?php

namespace BitCode\BitForm\API\Controller;

use WP_Error;
use WP_REST_Controller;
use BitCode\BitForm\Core\Util\IpTool;
use BitCode\BitForm\Core\Util\FileHandler;
use BitCode\BitForm\Core\Database\ApiModel;
use BitCode\BitForm\Core\Database\FormModel;
use BitCode\BitForm\Admin\Form\AdminFormManager;
use BitCode\BitForm\Core\Database\FormEntryModel;
use BitCode\BitForm\Core\Database\FormEntryLogModel;
use BitCode\BitForm\Core\WorkFlow\WorkFlowRunHelper;
use BitCode\BitForm\Core\Database\FormEntryMetaModel;
use BitCode\BitForm\Core\Form\Validator\FormFieldValidator;
use BitCode\BitForm\Core\Util\FieldValueHandler;

class EntryController extends WP_REST_Controller
{
    protected static $form;
    protected $formModel;
    protected $form_id;
    private $_has_upload;
    private $_field_label;
    private $_fields;

    function __construct()
    {
        $this->formModel = new FormModel();
    }

    public function googleAuth()
    {
        $state = $_GET['state'];
        $code = urlencode($_GET['code']);
        // echo $code;
        if (wp_redirect($state . '&code=' . $code, 302)) {
            exit;
        }
    }

    public function oneDriveAuth()
    {
        $state = $_GET['state'];
        $code = urlencode($_GET['code']);
        // echo $code;
        if (wp_redirect($state . '&code=' . $code, 302)) {
            exit;
        }
    }

    public function form_content($formID)
    {
        $form = $this->formModel->get(
            [
                "id",
                "form_content",
                "form_name",
                'created_at',
                'views',
                'entries',
                'status'
            ],
            array(
                'id' => $formID
            )
        );
        return $form;
    }

    public function get_forms()
    {
        $db = new ApiModel();
        $forms = $db->getForm();
        if (count($forms) > 0) {
            return rest_ensure_response(['forms' => $forms, 'status' => 200, 'code' => 4000, 'success' => true]);
        } else {
            return rest_ensure_response(['forms' => [], 'status' => 200, 'code' => 3910, 'message' => 'No forms available.', 'success' => true]);
        }
    }

    public function get_fields($request)
    {
        if (!empty($request['form_id'])) {
            $formManager = new AdminFormManager($request['form_id']);
            if (!$formManager->isExist()) {
                $error = ['message' => 'No Form with ID "<form_id>".', 'code' => '3200'];
                wp_send_json_error($error, 404);
            }
            $db = new ApiModel();
            $fields = $db->getField($request['form_id']);
            $workFlows = $db->getWorkFlow($request['form_id']);
            $fieldsKey = $formManager->getFieldsKey();
            $unset_types = ['razorpay', 'paypal', 'recaptcha'];
            if (!empty($fields)) {
                $filedsData = json_decode($fields[0]->form_content);
                foreach ($filedsData->fields as $key => $field) {
                    if (in_array($field->typ, $unset_types)) {
                        unset($filedsData->fields->$key);
                        unset($fieldsKey[$key]);
                    }
                }
                $data = ['fields' => $filedsData->fields, 'fieldkeys' => $fieldsKey, 'workflows' => $workFlows, 'workflow_key_name' => 'workflow'];
                return rest_ensure_response(['fields' => $data, 'status' => 200, 'code' => 4000, 'success' => true]);
            } else {
                return rest_ensure_response(['fields' => [], 'status' => 200, 'code' => 3920, 'message' => 'No fields available', 'success' => true]);
            }
        }
    }

    private function fieldSanitize($form_fields, $data,$fieldKey)
    {
        $submitted_data = [];
        $message = [];
        foreach($_POST as $key => $da) { 
            if(!in_array($key,$fieldKey)){ 
                $message[$key] = "No field named ".$key." found.";
            }
        }
        if ($message != []) {
            $data = ['message' => $message, 'code' => 3710];
            wp_send_json_success($data, 200);
        }

        foreach ($form_fields as $key => $field) {
            if (array_key_exists($field['key'], $data) && $field['type'] != 'file-up') {
                $submitted_data[$field['key']] = sanitize_text_field($data[$field['key']]);
            } else if (!array_key_exists($field['key'], $data) && $field['type'] != 'file-up') {
                $submitted_data[$field['key']] = '';
            }
        }
        return $submitted_data;
    }

    private function fieldTypeValidation($form_fields, $data)
    {
        $message = [];
        $string_types = array("text", "textarea", "radio", "color", "email","decision-box");
        foreach ($form_fields as $key => $field) {
            if (isset($data[$field['key']])) {
                if ($field['type'] == 'file-up' && gettype($data[$field['key']]) != 'array') {
                    $message[$field['key']] = "Data type mismatch. Value for the " . $field['key'] . " field must be of type FILE";
                } else if (in_array($field['type'], $string_types) && gettype($data[$field['key']]) != 'string') {
                    $message[$field['key']] = "Data type mismatch. Value for the " . $field['key'] . " field must be of type STRING";
                } else if ($field['type'] == 'check' && gettype($data[$field['key']]) != 'array') {
                    $message[$field['key']] = "Data type mismatch. Value for the " . $field['key'] . " field must be of type Array";
                }
            }
        }
        if ($message != []) {
            $data = ['message' => $message, 'code' => 3120];
            wp_send_json_success($data, 200);
        }
        return $message;
    }
    public function entry_store($request)
    {
       // wp_create_nonce( 'wp_rest' );
        $formID = $request['form_id'];
        $formManager = new AdminFormManager($request['form_id']);
        if (!$formManager->isExist()) {
            $data = ['message' => 'No Form with ID "<form_id>"', 'code' => '3200'];
            wp_send_json_error($data, 400);
        }
        $formEntryModel = new FormEntryModel();
        $ipTool = new IpTool();
        $user_details = $ipTool->getUserDetail();
        $entryMeta = new FormEntryMetaModel();
        $form_fields = $this->getFields($formID);
        $fieldsKey = $formManager->getFieldsKey();
        $this->fieldTypeValidation($form_fields, $_POST);
        $submitted_data = $this->fieldSanitize($form_fields, $_POST,$fieldsKey );
        $formFieldValidator = new FormFieldValidator($form_fields, $submitted_data, $_FILES);
        $validateField = $formFieldValidator->validate('create', $formID);
        if (!$validateField) {
            $errorMessage = count($formFieldValidator->getMessage()) > 0 ?
                $formFieldValidator->getMessage() : __("Internal error occured!!!", 'bitform');
            $error = ['errors' => $errorMessage, 'code' => 3070];
            wp_send_json_error($error, 422);
        }
        $entry_id = $formEntryModel->insert(
            array(
                'form_id' => $formID,
                'user_id' => $user_details['id'],
                'user_ip' => $user_details['ip'],
                'user_device' => $user_details['device'],
                'referer' => $user_details['page'],
                'status' => 0,
                'created_at' => $user_details['time']
            )
        );
        $submitted_fields = $this->getFormContentWithValue($submitted_data, $formID)->fields;
        $formManageer = new AdminFormManager($formID);
        $file_fields = $formManageer->getUploadFields($formID);
        $fileHandler = new FileHandler;
        $workFlowRunHelper = new WorkFlowRunHelper($formID);
        if (isset($_POST['workflow'])) {
            $workFlowIds = is_array($_POST['workflow']) ? implode(",", $_POST['workflow']) : $_POST['workflow'];
            $workFlowreturnedOnSubmit = $workFlowRunHelper->executeOnSubmit(
                'create',
                $submitted_fields,
                $submitted_data,
                $entry_id,
                null,
                $workFlowIds
            );
            unset($workFlowreturnedOnSubmit['data']);
        }
        if (!empty($workFlowreturnedOnSubmit['fields'])) {
            $submitted_data = $workFlowreturnedOnSubmit['fields'];
            unset($workFlowreturnedOnSubmit['fields']);
        }
        unset($submitted_data['workflow']);
        foreach ($_FILES as $file_name => $file_details) {
            if ($file_fields && in_array($file_name, $file_fields)) {
                $filePath = $fileHandler->moveUploadedFiles($file_details, $formID, $entry_id);
                if (!empty($filePath)) {
                    $submitted_data[$file_name] = $filePath;
                }
            }
        }
        foreach ($submitted_data as $key => $value) {
            $entryMeta->insert(
                array(
                    'bitforms_form_entry_id' => $entry_id,
                    'meta_key' => $key,
                    'meta_value' => is_string($submitted_data[$key]) ?
                        $submitted_data[$key] :
                        wp_json_encode($submitted_data[$key])
                )
            );
        }
        return rest_ensure_response(['status' => 200, 'code' => 4000, 'message' => 'Data Added Successfully!!', 'success' => true]);
    }

    public function getFormContentWithValue($defaultValues, $formID)
    {
        $form_content = \json_decode($this->form_content($formID)[0]->form_content);
        if (!is_array($defaultValues)) {
            return $form_content;
        }
        foreach ($form_content->fields as $fieldKey => $fieldDetails) {
            // $field_name = empty($fieldDetails->lbl) ? null : \preg_replace('/[\`\~\!\@\#\$\'\.\s\?\+\-\*\&\|\/\\!]/', '_', $fieldDetails->lbl);
            if ((isset($fieldDetails->mul) ||  $fieldDetails->typ === 'check') && isset($defaultValues[$fieldKey])) {
                if (is_array($defaultValues[$fieldKey])) {
                    $fieldDetails->val =
                        wp_json_encode(
                            array_map('sanitize_text_field', $defaultValues[$fieldKey])
                        );
                } else {
                    $fieldDetails->val = sanitize_text_field($defaultValues[$fieldKey]);
                }
            } elseif (isset($defaultValues[$fieldKey])) {
                $fieldDetails->val = is_string($defaultValues[$fieldKey]) ?
                    sanitize_text_field($defaultValues[$fieldKey]) :
                    sanitize_text_field($defaultValues[$fieldKey][count($defaultValues[$fieldKey]) - 1]);
            }
        }
        return $form_content;
    }

    public function getFields($formID)
    {
        if (!is_null($this->_fields)) {
            return $this->_fields;
        }
        $form_content = \json_decode($this->form_content($formID)[0]->form_content);
        $layout = $form_content->layout;
        $fields = $form_content->fields;
        $field_details = array();
        foreach ($fields as $key => $field) {
            if ($field->typ === 'recaptcha') {
                continue;
            }
            $field_type = $field->typ;
            $field_details[$key]['label'] = empty($field->lbl) ? null : $field->lbl;
            $field_details[$key]['type'] =  $field_type;
            $field_details[$key]['key'] =  $key;
            if (isset($field->mul)) {
                $field_details[$key]['mul'] =  $field->mul;
            }
            if ($field_type === 'file-up' && isset($field->exts)) {
                $field_details[$key]['valid']['type'] = $field->exts;
            }
            if (isset($field->valid) && !is_null($field->valid)) {
                if (isset($field->valid->req)) {
                    $field_details[$key]['valid']['req'] = $field->valid->req;
                }
                if (isset($field->valid->reqMsg)) {
                    $field_details[$key]['valid']['reqMsg'] = $field->valid->reqMsg;
                }
                if (isset($field->valid->typMsg)) {
                    $field_details[$key]['valid']['typMsg'] = $field->valid->typMsg;
                }
            }
        }
        $unset_types = ['razorpay', 'paypal', 'recaptcha'];

        foreach ($field_details as $key => $field) {
            if (in_array($field['type'], $unset_types)) {
                unset($field_details[$key]);
            }
        }
        $this->_fields = $field_details;
        return $field_details;
    }

    public function entry_update($request)
    {
        $formEntryModel = new FormEntryModel();
        $entryID = $request['entry_id'];
        if($_SERVER['REQUEST_METHOD']=='PUT'){ 
            $requestData = $request->get_params();
            unset($requestData['entry_id']);
        }else{ 
            $requestData = $_POST;
        }
        $apiModel = new ApiModel();
        $forms = $apiModel->getFormId($entryID);
        if ($forms === []) {
            $data = ['message' => 'No record with ID "<entry_id>".', 'code' => '3100'];
            wp_send_json_error($data, 404);
        }
        $formID = $forms[0]->form_id;
        $formEntryLogModel = new FormEntryLogModel();
        $apiModel = new ApiModel();
        $formOldData = $apiModel->get_form_value($entryID);
        $key = null;
        $entryMeta = new FormEntryMetaModel();
        $ipTool = new IpTool();
        $user_details = $ipTool->getUserDetail();
        $form_fields = $this->getFields($formID);
        $field_map = [];
        foreach ($formOldData as $index => $data) {
            foreach ($form_fields as $field_key => $field) {
                if ($data->meta_key == $field['key']) {
                    $field_map[$field_key] = $field['key'];
                }
            }
        }
        $this->fieldTypeValidation($form_fields, $requestData);
        $formManageer = new AdminFormManager($formID);
        $fieldsKey = $formManageer->getFieldsKey();
        $updatedValue = $this->fieldSanitize($form_fields, $requestData,$fieldsKey);
        $formFieldValidator = new FormFieldValidator($form_fields, $requestData, $_FILES);
        $validateField = $formFieldValidator->validate('edit', $formID);
        if (!$validateField) {
            $errorMessage = count($formFieldValidator->getMessage()) > 0 ?
                $formFieldValidator->getMessage() : __("Internal error occured !!!", 'bitform');
            $error = ['errors' => $errorMessage, 'code' => 3070];
            wp_send_json_error($error, 422);
        }
        $formEntry = $formEntryModel->update(
            array(
                "user_id" => $user_details['id'],
                "user_ip" => $user_details['ip'],
                "user_device" => $user_details['device'],
                "status" => 0,
                "updated_at" => $user_details['time']
            ),
            array(
                'form_id' => $formID,
                'id' => $entryID
            )
        );
        $log_id = null;
        if ($formEntry) {
            $log_id = $formEntryLogModel->form_log_insert(
                array(
                    "user_id" => $user_details['id'],
                    "action_type" => "update",
                    "log_type" => "entry",
                    "ip" => $user_details['ip'],
                    "form_entry_id" => $entryID,
                    "form_id" => $formID,
                    "created_at" => $user_details['time'],
                )
            );
        }
        if (is_wp_error($formEntry) || !$formEntry) {
            $data = ['message' => 'No record with ID "<entry_id>', 'code' => '3100'];
            wp_send_json_error($data, 404);
        }
        $file_fields = $this->getUploadFields($formID);
        if (count($file_fields) > 0) {
            $fileHandler = new FileHandler();
            foreach ($file_fields as $file_name) {
                if (!empty($_FILES[$file_name]['name'])) {
                    $meta_value = $fileHandler->moveUploadedFiles($_FILES[$file_name], $formID, $entryID);
                    if (!empty($meta_value)) {
                        $updatedValue[$file_name] = wp_json_encode($meta_value);
                    }
                }
            }
        }
        unset($updatedValue['_ajax_nonce']);
        unset($_REQUEST['g-recaptcha-response']);
        $workFlowRunHelper = new WorkFlowRunHelper($formID);
        if (isset($requestData['workflow'])) {
            $workFlowIds = is_array($requestData['workflow']) ? implode(",", $requestData['workflow']) : $requestData['workflow'];
            $workFlowreturnedOnSubmit = $workFlowRunHelper->executeOnSubmit(
                'edit',
                $this->getFormContentWithValue($updatedValue, $formID)->fields,
                $updatedValue,
                $entryID,
                $log_id,
                $workFlowIds
            );
            if (!empty($workFlowreturnedOnSubmit['fields'])) {
                $updatedValue = $workFlowreturnedOnSubmit['fields'];
                unset($workFlowreturnedOnSubmit['fields']);
            }
        }
        $formEntryMetaUpdateStatus = $entryMeta->update(
            $updatedValue,
            array(
                'bitforms_form_entry_id' => $entryID
            )
        );
        if (is_wp_error($formEntryMetaUpdateStatus) || isset($newFileInsertStatus) && is_wp_error($newFileInsertStatus)) {
            return $formEntryMetaUpdateStatus;
        }
        $updatedValue = array_merge($formEntryMetaUpdateStatus, array('entry_id' => $entryID));
        if (empty($workFlowreturnedOnSubmit['message'])) {
            $workFlowreturnedOnSubmit['message'] = __('Entry Updated Successfully', 'bitform');
        }
        $workFlowreturnedOnSubmit['updatedData'] = $updatedValue;
        $counter = 0;
        for ($i = 0; $i < count($formOldData); $i++) {
            if (array_key_exists($formOldData[$i]->meta_key . '_old', $updatedValue)) {
                unset($updatedValue[$formOldData[$i]->meta_key . '_old']);
            }
            if (in_array($formOldData[$i]->meta_key, $file_fields)) {
                if (
                    empty($_FILES[$formOldData[$i]->meta_key]['name'])
                    || (is_array($_FILES[$formOldData[$i]->meta_key]['name'])
                        && count($_FILES[$formOldData[$i]->meta_key]['name']) === 1
                        && empty($_FILES[$formOldData[$i]->meta_key]['name'][0]))
                ) {
                    unset($updatedValue[$formOldData[$i]->meta_key]);
                    continue;
                }
                if (is_array($_FILES[$formOldData[$i]->meta_key]['name']) && !in_array($_FILES[$formOldData[$i]->meta_key]['name'], json_decode($formOldData[$i]->meta_value))) {
                    $key[$i] = '${' . $formOldData[$i]->meta_key . '} file was Updated  To ' . json_encode($_FILES[$formOldData[$i]->meta_key]['name']);
                } elseif (!is_array($_FILES[$formOldData[$i]->meta_key]['name']) && !in_array($_FILES[$formOldData[$i]->meta_key]['name'], json_decode($formOldData[$i]->meta_value))) {
                    $key[$i] = '${' . $formOldData[$i]->meta_key . '} file was Updated  To ' . $_FILES[$formOldData[$i]->meta_key]['name'];
                }
                unset($updatedValue[$formOldData[$i]->meta_key]);
            } else {
                if(isset($updatedValue[$formOldData[$i]->meta_key])){
                    if (is_array($updatedValue[$formOldData[$i]->meta_key])) {
                        if (json_decode($formOldData[$i]->meta_value) !== $updatedValue[$formOldData[$i]->meta_key]) {
                            $key[$i] = '${' . $formOldData[$i]->meta_key . '} was Updated From ' . implode(',', json_decode($formOldData[$i]->meta_value)) . ' To ' . implode(",", $updatedValue[$formOldData[$i]->meta_key]);
                        }
                    } elseif (is_string($updatedValue[$formOldData[$i]->meta_key]) && !FieldValueHandler::isEmpty($updatedValue[$formOldData[$i]->meta_key])) {
                        if ($formOldData[$i]->meta_value !== $updatedValue[$formOldData[$i]->meta_key]) {
                            $key[$i] = '${' . $formOldData[$i]->meta_key . '} was Updated' . ($formOldData[$i]->meta_value ? ' From ' . $formOldData[$i]->meta_value : '') . ' To ' . $updatedValue[$formOldData[$i]->meta_key];
                        }
                    }
                }
                
            }
            $counter++;
        }
        $newField = array_keys(array_diff_key($formEntryMetaUpdateStatus, $field_map));
        for ($i = 0; $i < count($newField); $i++) {
            if (is_array($updatedValue[$newField[$i]]) && !FieldValueHandler::isEmpty($updatedValue[$newField[$i]])) {
                    $key[$counter + $i] =  '${' . $newField[$i] . '} Updated To ' . implode(",", $updatedValue[$newField[$i]]);
            } elseif (is_string($newField[$i]) && !FieldValueHandler::isEmpty($updatedValue[$newField[$i]])) {
                    $key[$counter + $i] = '${' . $newField[$i] . '} Updated To ' . $updatedValue[$newField[$i]];
            }
        }
        if ($key !== null) {
            $logUpdate = implode("b::f", (array) $key);
            $apiModel->logUpdate($logUpdate, $log_id);
        }
        return rest_ensure_response(['status' => 200, 'code' => 4000, 'message' => 'Data Updated Successfully', 'success' => true]);
    }

    public function getUploadFields($formID)
    {
        if (!is_null($this->_has_upload)) {
            return $this->_has_upload;
        }
        $upload_fields = array();
        $form_field_details = $this->getFields($formID);
        foreach ($form_field_details as $field_name => $__field_detail) {
            if (isset($__field_detail['type']) && $__field_detail['type'] === 'file-up') {
                $upload_fields[] = $field_name;
            }
        }
        $this->_has_upload = $upload_fields;
        return $upload_fields;
    }

    public function entry_delete($request)
    {
        $entries['entry_id'] = $request['entry_id'];
        $apiModel = new ApiModel();
        $forms = $apiModel->getFormId($entries['entry_id']);
        if ($forms === []) {
            $data = ['message' => 'No record with ID "<entry_id>".', 'code' => '3100'];
            wp_send_json_error($data, 404);
        }
        $formID = $forms[0]->form_id;
        $formManager = new AdminFormManager($formID);
        if (!$formManager->isExist()) {
            $data = ['message' => 'No form found. Please check and try again', 'code' => '3130'];
            wp_send_json_error($data, 404);
        }
        $workFlowRunHelper = new WorkFlowRunHelper($formID);
        $workFlowreturnedOnDelete = $workFlowRunHelper->executeOnDelete(
            $formManager,
            $formID,
            $entries
        );
        if (isset($workFlowreturnedOnDelete['entries'])) {
            if (count($workFlowreturnedOnDelete['entries']) === 0) {
                return ["message" => __('Entry Deletetion prevented by  workflow', 'bitform')];
            } elseif (count($workFlowreturnedOnDelete['entries']) === count($entries)) {
                $message = __('Entry Deleted successfully', 'bitform');
            } else {
                $result['prevented'] = array_diff($entries, $workFlowreturnedOnDelete['entries']);
                $entries = $workFlowreturnedOnDelete['entries'];
                $message = __('Entry Deleted successfully, Some prevented by workflow', 'bitform');
            }
        } else {
            $message = __('Entry Deleted successfully', 'bitform');
        }
        global $wpdb;
        $prefix = $wpdb->prefix;
        $formEntryModel = new FormEntryModel();
        $delete_status = $formEntryModel->bulkDelete(
            array(
                "`{$prefix}bitforms_form_entries`.`id`" => $entries,
                "`{$prefix}bitforms_form_entries`.`form_id`" => $formID,
            )
        );
        if (file_exists(BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . $formID)) {
            $fileHandler = new FileHandler();
            foreach ($entries as $enrtyKey => $entryID) {
                $fileEntries = BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . $formID . DIRECTORY_SEPARATOR . $entryID;
                if (file_exists($fileEntries)) {
                    $fileHandler->rmrf($fileEntries);
                }
            }
        }
        $count = $formEntryModel->count(
            array(
                'form_id' => $formID,
            )
        );
        $formManager->resetSubmissionCount(intval($count[0]->count));
        if (is_wp_error($delete_status)) {
            return new WP_Error('entry_not_exists', __("Form entry deletion failed", "bitform"));
        }
        $result['message'] = $message;
        return rest_ensure_response(['status' => 200, 'code' => 4000, 'message' => $message, 'success' => true]);
    }
    public function entry_view($request)
    {
        $entryID = $request['entry_id'];
        $apiModel = new ApiModel();
        $forms = $apiModel->getFormId($entryID);
        if ($forms === []) {
            $data = ['message' => 'No record with ID "<entry_id>".', 'code' => '3100'];
            wp_send_json_error($data, 404);
        }
        $formID = $forms[0]->form_id;
        $formManager = new AdminFormManager($formID);
        if (!$formManager->isExist()) {
            $data = ['message' => 'No form was found. Please check and try again', 'code' => 3130];
            wp_send_json_error($data, 404);
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
            $data = ['message' => 'No Entry with ID"', 'code' => 3100];
            wp_send_json_error($data, 404);
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
        $fieldsKey = $formManager->getFieldsKey();
        $form_fields = $formContent->fields;
        foreach ($form_fields as $key => $value) {
            // $field_name = preg_replace('/[\`\~\!\@\#\$\'\.\s\?\+\-\*\&\|\/\\!]/', '_', $value->lbl);
            if (isset($entries[$key])) {
                $form_fields->{$key}->val = $entries[$key];
                $form_fields->{$key}->name = $key;
            }
        }
   
        $formData = array(
            'fields' => $form_fields,
            'fieldsKey' => $fieldsKey,
        );
        return rest_ensure_response(['data' =>  $formData, 'status' => 200, 'success' => true]);
    }
    public function getEntryResponse($request)
    {
        $formId = wp_unslash($request['id']);
        $limit = !empty($request['per_page']) ? wp_unslash($request['per_page']) : 200;
        if ($limit > 200) {
            $data = ['message' => 'A maximum of 200 records can be fetched per request', 'code' => 3970];
            wp_send_json_success($data, 200);
        }
        $offset = !empty($request['page']) ? wp_unslash($request['page']) : null;
        $formManager = new AdminFormManager($formId);
        if (!$formManager->isExist()) {
            $data = ['message' => 'No form was found. Please check and try again', 'code' => 3130];
            wp_send_json_error($data, 404);
        }
        $formEntry = new FormEntryModel();
        $entries = $formEntry->get(
            'id',
            array(
                'form_id' => $formId,
            ),
            null,
            null,
            'created_at',
            'DESC'
        );
        if (is_wp_error($entries)) {
            if ($entries->get_error_code() === 'result_empty') {
                return rest_ensure_response(['data' => [], 'status' => 200, 'message' => 'form is empty', 'success' => true]);
            }
            return $entries;
        }
        $formFields = $formManager->getFieldLabel(true);
        $entryMeta = new FormEntryMetaModel();
        $formEntries = $entryMeta->getEntryMeta($formFields, $entries, $limit, $offset);
        foreach ($formFields as $field) {
            foreach ($formEntries['entries'] as $entry) {
                if (isset($field['key'], $entry) && $field['type'] == 'file-up') {
                    $key = $field['key'];
                    if (is_array(json_decode($entry->$key))) {
                        $fileData = array();
                        foreach (json_decode($entry->$key) as $file) {
                            $path = "bitforms/bitforms-file/?formID=$formId&entryID=$entry->entry_id&fileID=$file";
                            $fileData[] = site_url($path, null);
                        }
                        $entry->$key = wp_json_encode($fileData, JSON_UNESCAPED_SLASHES);
                    }
                }
            }
        }
        return rest_ensure_response(['data' => $formEntries, 'status' => 200, 'success' => true]);
    }
}
