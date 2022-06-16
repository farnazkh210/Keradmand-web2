<?php

/**
 * Handle Form Create,Update,delete Operation
 *
 */

namespace BitCode\BitForm\Admin\Form;

use WP_Error;
use BitCode\BitForm\Core\Util\IpTool;
use BitCode\BitForm\Core\Util\FileHandler;
use BitCode\BitForm\Core\Database\FormModel;
use BitCode\BitForm\Core\Database\ReportsModel;
use BitCode\BitForm\Admin\Form\AdminFormManager;
use BitCode\BitForm\Core\Database\WorkFlowModel;
use BitCode\BitForm\Core\Database\FormEntryModel;
use BitCode\BitForm\Core\Util\FormDuplicateHelper;
use BitCode\BitForm\Core\WorkFlow\WorkFlowHandler;
use BitCode\BitForm\Core\Database\IntegrationModel;
use BitCode\BitForm\Core\Database\FormEntryLogModel;
use BitCode\BitForm\Core\WorkFlow\WorkFlowRunHelper;
use BitCode\BitForm\Core\Database\EmailTemplateModel;
use BitCode\BitForm\Core\Database\FormEntryMetaModel;
use BitCode\BitForm\Core\Database\SuccessMessageModel;
use BitCode\BitForm\Core\Messages\EmailTemplateHandler;
use BitCode\BitForm\Core\Integration\IntegrationHandler;
use BitCode\BitForm\Core\Messages\SuccessMessageHandler;
use BitCode\BitForm\Admin\Form\Template\TemplateProvider;
use BitCode\BitForm\Admin\Form\CustomFieldHandler;

class AdminFormHandler
{
    private static $formModel;
    private static $ipTool;
    public function __construct()
    {
        static::$formModel = new FormModel();
        static::$ipTool = new IpTool();
    }
    public function getTemplate($Request, $post)
    {
        $templateName = null;
        $newFormId = null;
        if (isset($post->template)) {
            $templateName = $post->template;
            $newFormId = $post->newFormId;
        }

        $templateProvider = new TemplateProvider();
        $form_content_raw = $templateProvider->getTemplate($templateName, $newFormId);
        if (!$form_content_raw) {
            return new WP_Error("template_empty", __("Template not found, by this name: ", "bit-form") . $templateName);
        } else {
            return \json_encode(
                array(
                    'id' => 0,
                    'form_content' => $form_content_raw,
                )
            );
        }
    }

    public function saveStyleSheet($styles, $sheetName)
    {
        $this->createFormStylesDirIfNotExists();

        if (
            file_exists(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles')
            && is_writable(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles')
        ) {
            $createStyleFile = fopen(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles' . DIRECTORY_SEPARATOR . $sheetName, 'w');
            fwrite($createStyleFile, $styles);
            fclose($createStyleFile);
            return true;
        }
        return false;
    }

    public function saveLayoutStyleSheet($layout, $sheetName, $lay_row_height = 43)
    {
        $md_width = 600;
        $sm_width = 400;
        $lg_styles = '';
        $md_styles = '';
        $sm_styles = '';
        for ($i = 0; $i < count($layout->lg); $i++) {
            $fld = $layout->lg[$i];

            $class = $fld->i;
            $lg_g_r_s = $fld->y + 1;
            $lg_g_c_s = $fld->x + 1;
            $lg_g_r_e = $fld->y !== 1 ? $fld->h + ($fld->y + 1) : 1;
            $lg_g_c_e = ($fld->x + 1) + $fld->w;
            $lg_g_r_span = $lg_g_r_e - $lg_g_r_s;
            $lg_g_c_span = $lg_g_c_e - $lg_g_c_s;
            $lg_min_height = $fld->h * $lay_row_height . "px;";

            $lg_styles .= ".$class { grid-area: $lg_g_r_s / $lg_g_c_s / $lg_g_r_e / $lg_g_c_e ; -ms-grid-row: $lg_g_r_s; -ms-grid-row-span: $lg_g_r_span; -ms-grid-column: $lg_g_c_s; -ms-grid-column-span: $lg_g_c_span;  min-height: $lg_min_height; }";

            $fld = $layout->md[$i];

            $class = $fld->i;
            $md_g_r_s = $fld->y + 1;
            $md_g_c_s = $fld->x + 1;
            $md_g_r_e = $fld->y !== 1 ? $fld->h + ($fld->y + 1) : 1;
            $md_g_c_e = ($fld->x + 1) + $fld->w;
            $md_g_r_span = $lg_g_r_e - $md_g_r_s;
            $md_g_c_span = $lg_g_c_e - $md_g_c_s;
            $md_min_height = $fld->h * $lay_row_height . "px;";

            $md_styles .= ".$class { grid-area: $md_g_r_s / $md_g_c_s / $md_g_r_e / $md_g_c_e ; -ms-grid-row: $md_g_r_s; -ms-grid-row-span: $md_g_r_span; -ms-grid-column: $md_g_c_s; -ms-grid-column-span: $md_g_c_span;  min-height: $md_min_height; }";

            $fld = $layout->sm[$i];

            $class = $fld->i;
            $sm_g_r_s = $fld->y + 1;
            $sm_g_c_s = $fld->x + 1;
            $sm_g_r_e = $fld->y !== 1 ? $fld->h + ($fld->y + 1) : 1;
            $sm_g_c_e = ($fld->x + 1) + $fld->w;
            $sm_g_r_span = $sm_g_r_e - $sm_g_r_s;
            $sm_g_c_span = $sm_g_c_e - $sm_g_c_s;
            $sm_min_height = $fld->h * $lay_row_height . "px;";

            $sm_styles .= ".$class { grid-area: $sm_g_r_s / $sm_g_c_s / $sm_g_r_e / $sm_g_c_e ; -ms-grid-row: $sm_g_r_s; -ms-grid-row-span: $sm_g_r_span; -ms-grid-column: $sm_g_c_s; -ms-grid-column-span: $sm_g_c_span;  min-height: $sm_min_height; }";
        }
        $formStyle = "._frm-g{
display: grid;
display: -ms-grid;
grid-template-columns: repeat( 6 , minmax( 30px , 1fr ));
-ms-grid-columns: minmax( 30px , 1fr ) minmax( 30px , 1fr ) minmax( 30px , 1fr ) minmax( 30px , 1fr ) minmax( 30px , 1fr ) minmax( 30px , 1fr );
}
{$lg_styles}
@media only screen and (max-width:{$md_width}px) {
._frm-g {grid-template-columns: repeat( 4 , minmax( 30px , 1fr ));-ms-grid-columns: minmax( 30px , 1fr ) minmax( 30px , 1fr ) minmax( 30px , 1fr ) minmax( 30px , 1fr );}
{$md_styles}
}
@media only screen and (max-width:{$sm_width}px) {
._frm-g {grid-template-columns: repeat( 2 , minmax( 30px , 1fr ));-ms-grid-columns: minmax( 30px , 1fr ) minmax( 30px , 1fr );}
{$sm_styles}
}";

        $this->createFormStylesDirIfNotExists();

        if (
            file_exists(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles')
            && is_writable(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles')
        ) {
            $createStyleFile = fopen(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles' . DIRECTORY_SEPARATOR . $sheetName, 'w');
            fwrite($createStyleFile, $formStyle);
            fclose($createStyleFile);
            return true;
        }
        return false;
    }

    public function createFormStylesDirIfNotExists()
    {
        if (!file_exists(BITFORMS_UPLOAD_DIR)) {
            wp_mkdir_p(BITFORMS_UPLOAD_DIR);
        }
        if (!file_exists(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles')) {
            wp_mkdir_p(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles');
        }
    }

    public function createNewForm($Request, $post)
    {
        if (!isset($post->form_id) || $post->form_id == '') {
            return new WP_Error('empty_form', __('Error Occurred, Please Reload', 'bit-form'));
        }

        $formManager = new AdminFormManager($post->form_id);
        if ($formManager->isExist()) {
            return new WP_Error('empty_form', __('Error Occurred, Please Reload', 'bit-form'));
        }

        if (!class_exists("BitCode\\BitFormPro\\Plugin")) {
            if (!empty($workFlows) && count($workFlows) > 2) {
                return new WP_Error(
                    'free_limit',
                    __('You are allowed to add maximum 2 workflows ', 'bit-form')
                );
            }
        }

        if (isset($post->formStyle) && $post->formStyle) {
            $sheetName = 'bitform-' . $post->form_id . '.css';
            $this->saveStyleSheet($post->formStyle, $sheetName);
        }

        if (isset($post->layoutChanged) && $post->layoutChanged) {
            $sheetName = 'bitform-layout-' . $post->form_id . '.css';
            $rowHeight = (int)$post->rowHeight;
            $this->saveLayoutStyleSheet($post->layout, $sheetName, $rowHeight);
        }

        $fields = wp_unslash($post->fields);
        $layout = wp_unslash($post->layout);
        $form_name = wp_unslash($post->form_name);
        $formSettings = (object) wp_unslash($post->formSettings);
        $workFlows = wp_unslash($post->workFlows);
        $additional = wp_unslash($post->additional);
        $reports = !empty($post->reports) ? $post->reports : (object) [];

        $mailTem = $formSettings->mailTem;
        $integrations = $formSettings->integrations;

        $user_details = static::$ipTool->getUserDetail();
        // var_dump($fields);
        if (empty($fields) || empty($layout)) {
            return new WP_Error(
                'empty_form',
                __('Can not save empty form.', 'bit-form')
            );
        }
        if (strlen($form_name) > 50) {
            return new WP_Error(
                'empty_form',
                __('Form Name Should Be Within 50 Characters', 'bit-form')
            );
        }
        $form_content = array(
            'fields' => $fields,
            'layout' => $layout,
        );

        /* if (!empty($formSettings->submitBtn)) {
            $form_content = array_merge($form_content, array('buttons' => $formSettings->submitBtn));
        } */
        if (!empty($formSettings->theme)) {
            $form_content = array_merge($form_content, array('theme' => $formSettings->theme));
        }

        if (!empty($additional)) {
            $form_content = array_merge($form_content, array('additional' => $additional));
        }
        if (!empty($workFlows)) {
            $workFlowsStr = is_string($workFlows) ? $workFlows : json_encode($workFlows);
            $workFlowExist = [];
            if (\strpos($workFlowsStr, 'onload')) {
                $workFlowExist['onload'] = true;
            }
            if (\strpos($workFlowsStr, 'oninput')) {
                $workFlowExist['oninput'] = true;
            }
            $form_content = array_merge($form_content, array('workFlowExist' => $workFlowExist));
        }
        $form_content = \wp_json_encode($form_content);

        $fdata = array(
            "id" => $post->form_id,
            "form_content" => $form_content,
            "user_id" => $user_details['id'],
            "user_ip" => $user_details['ip'],
            "user_device" => $user_details['device'],
            "status" => 1,
            "form_name" => $form_name,
            "created_at" => $user_details['time'],
            "updated_at" => $user_details['time'],
        );
        $save_status = static::$formModel->insert($fdata);

        if (is_wp_error($save_status)) {
            return $save_status;
        } elseif (!$save_status) {
            return false;
        } else {
            wp_mkdir_p(BITFORMS_UPLOAD_DIR . "/$save_status");
            $formID = $save_status;
            $integartionIDForWorkflow = array();
            // Confiramtion Message [start]
            if (!empty($formSettings->confirmation->type->successMsg)) {
                $successMessages = $formSettings->confirmation->type->successMsg;
                $successMessageHandler
                    = new SuccessMessageHandler($formID, $user_details);
                foreach ($successMessages as $messageKey => $messageDetail) {
                    $savedID = $successMessageHandler->saveMessage($messageDetail);
                    if ($savedID) {
                        $msgIdx = empty($messageDetail->id) ? $messageKey : \json_encode(["id" => $messageDetail->id]);
                        $integartionIDForWorkflow['successMsg'][$msgIdx] = $savedID;
                    }
                }
                unset($formSettings->confirmation->type->successMsg);
            }
            // return $formSettings;
            // Confiramtion Message [end] */
            // Email Template [start]
            if (!empty($mailTem)) {
                $emailTemplateHandler
                    = new EmailTemplateHandler($formID, $user_details);
                foreach ($mailTem as $templateKey => $templateDetail) {
                    $savedID = $emailTemplateHandler->saveTemplate($templateDetail);
                    if ($savedID) {
                        $tempIdx = empty($templateDetail->id) ? $templateKey : \json_encode(["id" => $templateDetail->id]);
                        $integartionIDForWorkflow['mailNotify'][$tempIdx] = $savedID;
                    }
                }
            }
            // Email Template [end] */
            //Integration [start] */
            $integrationHandler = new IntegrationHandler($formID, $user_details);
            if (!empty($formSettings->confirmation->type)) {
                $allIntegrations = $formSettings->confirmation->type;
                foreach ($allIntegrations as $integrationType => $integrationGroup) {
                    foreach ($integrationGroup as $singleIntegrationKey => $singleIntegrationDetail) {
                        if (empty($singleIntegrationDetail->details)) {
                            $integrationName = $singleIntegrationDetail->title;
                            unset($singleIntegrationDetail->title);
                            unset($singleIntegrationDetail->id);
                            $integrationDetails = !is_string($singleIntegrationDetail) ?
                                wp_json_encode($singleIntegrationDetail) : $singleIntegrationDetail;
                        } else {
                            $integrationName = $singleIntegrationDetail->title;
                            $integrationDetails = !is_string($singleIntegrationDetail->details) ?
                                wp_json_encode($singleIntegrationDetail->details) : $singleIntegrationDetail->details;
                        }
                        $savedID = $integrationHandler->saveIntegration($integrationName, $integrationType, $integrationDetails, 'form');
                        $integIdx = empty($singleIntegrationDetail->id) ? $savedID : \json_encode(["id" => $singleIntegrationDetail->id]);
                        $integartionIDForWorkflow[$integrationType][$integIdx] = $savedID;
                    }
                }
            }
            if (!empty($integrations)) {
                foreach ($integrations as $singleIntegrationKey => $singleIntegrationDetail) {
                    $integrationName = $singleIntegrationDetail->name;
                    unset($singleIntegrationDetail->name);
                    $integrationType = $singleIntegrationDetail->type;
                    unset($singleIntegrationDetail->type);
                    $singleIntegrationDetailID = empty($singleIntegrationDetail->id) ? null : $singleIntegrationDetail->id;
                    unset($singleIntegrationDetail->id);
                    if (empty($singleIntegrationDetail->details)) {
                        $integrationDetails = !is_string($singleIntegrationDetail) ?
                            wp_json_encode($singleIntegrationDetail) : $singleIntegrationDetail;
                    } else {
                        $integrationDetails = !is_string($singleIntegrationDetail->details) ?
                            wp_json_encode($singleIntegrationDetail->details) : $singleIntegrationDetail->details;
                    }
                    $savedID = $integrationHandler->saveIntegration($integrationName, $integrationType, $integrationDetails, 'form');
                    $integIdx = empty($singleIntegrationDetailID) ? $singleIntegrationKey : \json_encode(["id" => $singleIntegrationDetailID]);
                    $integartionIDForWorkflow['integ'][$integIdx] = $savedID;
                }
            }
            //Integrations [end]
            if (!empty($workFlows)) {
                $workFlowHandler = new WorkFlowHandler($formID, $user_details);
                $workflowCount = count($workFlows);
                while ($workflowCount) {
                    $workFlow = $workFlows[--$workflowCount];
                    $savedID = $workFlowHandler->saveworkFlow($workFlow, $integartionIDForWorkflow);
                    $newData['workflow'] = 1;
                }
            }
            //wrokFlows [end]
            if (!empty($reports)) {
                $reportsModel = new ReportsModel();
                foreach ($reports as $reportIndex => $report) {
                    $reportsModel->insert(
                        array(
                            "type" => 'table',
                            "category" => 'form',
                            "context" => $formID,
                            "details" => is_string($report->details) ? $report->details : wp_json_encode($report->details),
                            "isDefault" => 1,
                            "user_id" => $user_details['id'],
                            "user_ip" => $user_details['ip'],
                            "user_device" => $user_details['device'],
                            "created_at" => $user_details['time'],
                            "updated_at" => $user_details['time'],
                        )
                    );
                    $newData['reports'] = 1;
                }
            }
            $updated_data = $this->getAForm(array('id' => $save_status), null);
            $updated_data['message'] = __('Form Saved successfully', 'bit-form');

            return $updated_data;
        }
    }

    public function updateForm($Request, $post)
    {
        if ($post->formStyle) {
            $sheetName = 'bitform-' . $post->id . '.css';
            $this->saveStyleSheet($post->formStyle, $sheetName);
        }

        if ($post->layoutChanged) {
            $sheetName = 'bitform-layout-' . $post->id . '.css';
            $rowHeight = (int)$post->rowHeight;
            $this->saveLayoutStyleSheet($post->layout, $sheetName, $rowHeight);
        }

        if (property_exists($post, 'fields') && $post->fields !== null && property_exists($post, 'layout') && $post->layout) {
            $fields = wp_unslash($post->fields);
            $layout = wp_unslash($post->layout);
            $formID = wp_unslash($post->id);
            $form_name = wp_unslash($post->form_name);
            $formSettings = wp_unslash($post->formSettings);
            $workFlows = wp_unslash($post->workFlows);
            $additional = wp_unslash($post->additional);
            $reports = wp_unslash($post->reports);
        }

        $mailTem = $formSettings->mailTem;
        $integrations = $formSettings->integrations;

        $user_details = static::$ipTool->getUserDetail();
        if (empty($fields) || empty($layout) || is_null($formID)) {
            return new WP_Error('empty_form', 'Can not update empty form.');
        }

        if (!class_exists("BitCode\\BitFormPro\\Plugin")) {
            if (count($workFlows) > 2) {
                return new WP_Error(
                    'free_limit',
                    __('You are allowed to add maximum 2 workflows ', 'bit-form')
                );
            }
        }
        $form_content = array(
            'fields' => $fields,
            'layout' => $layout,
        );
        /* if (!empty($formSettings->submitBtn)) {
            $form_content = array_merge($form_content, array('buttons' => $formSettings->submitBtn));
        } */
        if (!empty($formSettings->theme)) {
            $form_content = array_merge($form_content, array('theme' => $formSettings->theme));
        }
        if (!empty($additional)) {
            $form_content = array_merge($form_content, array('additional' => $additional));
        }
        $integartionIDForWorkflow = array();
        $newData['settingConfiramation'] = 0;
        $newData['integation'] = 0;
        $newData['mailTemplate'] = 0;
        $newData['workflow'] = 0;
        $newData['reports'] = 0;
        // Confiramtion Message [start]
        if (!empty($formSettings->confirmation->type->successMsg)) {
            $successMessages = $formSettings->confirmation->type->successMsg;
            $successMessageHandler
                = new SuccessMessageHandler($formID, $user_details);
            foreach ($successMessages as $messageKey => $messageDetail) {
                if (empty($messageDetail->id)) {
                    $savedID = $successMessageHandler->saveMessage($messageDetail);
                    if ($savedID) {
                        $newData['settingConfiramation'] = 1;
                        $integartionIDForWorkflow['successMsg'][$messageKey] = $savedID;
                    }
                } else {
                    $successMessageHandler->updateMessage($messageDetail);
                }
            }
            unset($formSettings->confirmation->type->successMsg);
        }
        // return $formSettings;
        // Confiramtion Message [end] */
        // Email Template [start]
        if (!empty($mailTem)) {
            $emailTemplateHandler
                = new EmailTemplateHandler($formID, $user_details);
            foreach ($mailTem as $templateKey => $templateDetail) {
                if (empty($templateDetail->id)) {
                    $savedID = $emailTemplateHandler->saveTemplate($templateDetail);
                    if (is_wp_error($savedID) && $savedID->get_error_code() === 'result_empty') {
                        $newData['mailTemplate'] = 2;
                    } else {
                        $newData['mailTemplate'] = 1;
                        $integartionIDForWorkflow['mailTem'][$templateKey] = $savedID;
                    }
                } else {
                    $emailTemplateHandler->updateTemplate($templateDetail);
                }
            }
        }
        // return $formSettings;
        // Email Template [end] */
        //Integration [start] */
        $integrationHandler = new IntegrationHandler($formID, $user_details);
        if (!empty($formSettings->confirmation->type)) {
            $allIntegrations = $formSettings->confirmation->type;
            foreach ($allIntegrations as $integrationType => $integrationGroup) {
                foreach ($integrationGroup as $singleIntegrationKey => $singleIntegrationDetail) {
                    if (empty($singleIntegrationDetail->details)) {
                        $integrationName = $singleIntegrationDetail->title;
                        unset($singleIntegrationDetail->title);
                        $singleIntegrationDetailID = empty($singleIntegrationDetail->id) ? null : $singleIntegrationDetail->id;
                        unset($singleIntegrationDetail->id);
                        $integrationDetails = !is_string($singleIntegrationDetail) ?
                            wp_json_encode($singleIntegrationDetail) : $singleIntegrationDetail;
                    } else {
                        $singleIntegrationDetailID = empty($singleIntegrationDetail->id) ? null : $singleIntegrationDetail->id;
                        unset($singleIntegrationDetail->id);
                        $integrationName = $singleIntegrationDetail->title;
                        $integrationDetails = !is_string($singleIntegrationDetail->details) ?
                            wp_json_encode($singleIntegrationDetail->details) : $singleIntegrationDetail->details;
                    }
                    if (empty($singleIntegrationDetailID)) {
                        $savedID = $integrationHandler->saveIntegration($integrationName, $integrationType, $integrationDetails, 'form');
                        $newData['settingConfiramation'] = 1;
                        $integartionIDForWorkflow[$integrationType][$singleIntegrationKey] = $savedID;
                    } else {
                        $integrationHandler->updateIntegration($singleIntegrationDetailID, $integrationName, $integrationType, $integrationDetails, 'form');
                    }
                }
            }
        }
        if (!empty($integrations)) {
            foreach ($integrations as $singleIntegrationKey => $singleIntegrationDetail) {
                $integrationName = $singleIntegrationDetail->name;
                unset($singleIntegrationDetail->name);
                $integrationType = $singleIntegrationDetail->type;
                unset($singleIntegrationDetail->type);
                $singleIntegrationDetailID = empty($singleIntegrationDetail->id) ? null : $singleIntegrationDetail->id;
                unset($singleIntegrationDetail->id);
                if (empty($singleIntegrationDetail->details)) {
                    $integrationDetails = !is_string($singleIntegrationDetail) ?
                        wp_json_encode($singleIntegrationDetail) : $singleIntegrationDetail;
                } else {
                    $integrationDetails = !is_string($singleIntegrationDetail->details) ?
                        wp_json_encode($singleIntegrationDetail->details) : $singleIntegrationDetail->details;
                }
                if (empty($singleIntegrationDetailID)) {
                    $savedID = $integrationHandler->saveIntegration($integrationName, $integrationType, $integrationDetails, 'form');
                    $integartionIDForWorkflow[$integrationType][$singleIntegrationKey] = $savedID;
                    if (is_wp_error($savedID) && $savedID->get_error_code() !== 'result_empty') {
                        $newData['integation'] = 2;
                    } else {
                        $newData['integation'] = 1;
                    }
                } else {
                    $integrationHandler->updateIntegration($singleIntegrationDetailID, $integrationName, $integrationType, $integrationDetails, 'form');
                }
            }
        }
        //Integrations [end]
        //workFlows [start] */
        $workFlowExist = [];
        if (!empty($workFlows)) {
            $workFlowHandler = new WorkFlowHandler($formID, $user_details);
            // foreach ($workFlows as $workFlowIndex => $workFlow) {
            $workflowCount = count($workFlows);
            while ($workflowCount) {
                $workFlow = $workFlows[--$workflowCount];
                if ($workFlow->action_type === 'onload') {
                    $workFlowExist['onload'] = true;
                }
                if ($workFlow->action_type === 'oninput') {
                    $workFlowExist['oninput'] = true;
                }
                if (empty($workFlow->id)) {
                    $savedID = $workFlowHandler->saveworkFlow($workFlow, $integartionIDForWorkflow);
                    if (is_wp_error($savedID) && $savedID->get_error_code() !== 'result_empty') {
                        $newData['workflow'] = 2;
                    } else {
                        $newData['workflow'] = 1;
                    }
                } else {
                    $workFlowHandler->updateworkFlow($workFlow->id, $workFlow, $integartionIDForWorkflow);
                }
            }
        }
        $form_content = array_merge($form_content, array('workFlowExist' => $workFlowExist));
        //wrokFlows [end]
        //reports [start] */
        if (!empty($reports)) {
            $reportsModel = new ReportsModel();
            $fieldNames = [];
            foreach ($fields as $key => $field) {
                if (!empty($field->lbl)) {
                    // $name = preg_replace('/[\`\~\!\@\#\$\'\.\s\?\+\-\*\&\|\/\\\!]/', '_', $field->lbl);
                    $fieldNames["$key"] = $field->lbl;
                }
            }

            $fieldNames['__user_id'] = __('User', 'bit-form');
            $fieldNames['__user_ip'] = __('IP address', 'bit-form');
            // $fieldNames['__user_location'] = __('');
            $fieldNames['__referer'] = __('Refer URL', 'bit-form');
            $fieldNames['__user_device'] = __('Device', 'bit-form');
            $fieldNames['__created_at'] = __('Created Time', 'bit-form');
            $fieldNames['__updated_at'] = __('Modified Time', 'bit-form');
            foreach ($reports as $reportIndex => $report) {
                $reportDetails = $reportsModel->validateReportFields($report, $fieldNames);

                if (empty($report->id)) {
                    $reportSaveStatus = $reportsModel->insert(
                        array(
                            "type" => 'table',
                            "category" => 'form',
                            "context" => $formID,
                            "details" => is_string($reportDetails) ? $reportDetails : wp_json_encode($reportDetails),
                            "isDefault" => 1,
                            "user_id" => $user_details['id'],
                            "user_ip" => $user_details['ip'],
                            "user_device" => $user_details['device'],
                            "created_at" => $user_details['time'],
                            "updated_at" => $user_details['time'],
                        )
                    );
                    if (is_wp_error($reportSaveStatus) && $reportSaveStatus->get_error_code() !== 'result_empty') {
                        $newData['reports'] = 2;
                    } else {
                        $newData['reports'] = 1;
                    }
                } else {
                    $reportsModel->update(
                        array(
                            "type" => 'table',
                            "category" => 'form',
                            "context" => $formID,
                            "details" => is_string($reportDetails) ? $reportDetails : wp_json_encode($reportDetails),
                            "isDefault" => 1,
                            "user_id" => $user_details['id'],
                            "user_ip" => $user_details['ip'],
                            "user_device" => $user_details['device'],
                            "updated_at" => $user_details['time'],
                        ),
                        array(
                            'id' => $report->id,
                        )
                    );
                }
            }
        }
        //reports [end]
        $form_content = wp_json_encode($form_content);
        $update_status = static::$formModel->update(
            array(
                "form_name" => $form_name,
                "form_content" => $form_content,
                "user_id" => $user_details['id'],
                "user_ip" => $user_details['ip'],
                "user_device" => $user_details['device'],
                "status" => 1,
                "updated_at" => $user_details['time'],
            ),
            array(
                "id" => $formID,
            )
        );
        if (is_wp_error($update_status)) {
            return $update_status;
        } elseif (!$update_status) {
            return new WP_Error('empty_form', __('Form update failed.', 'bit-form'));
        } else {
            if (isset($post->deletedFldKey) && is_array($post->deletedFldKey)) {

                $deletedMetaFlds = "'" . implode("','", $post->deletedFldKey) . "'";
                global $wpdb;
                $tablename = $wpdb->prefix . "bitforms_form_entrymeta";

                $wpdb->query($wpdb->prepare("UPDATE $tablename SET meta_value = %s WHERE meta_key IN (%s)", " ", $deletedMetaFlds));
                unset($post->deletedFldKey);
            }

            $updated_data = $this->getAForm(array('id' => $formID), null);
            if ($newData['settingConfiramation'] === 0 && $newData['mailTemplate'] === 0 && $newData['integation'] === 0) {
                unset($updated_data['formSettings']);
            }
            if ($newData['integation'] === 0) {
                unset($updated_data['integrations']);
            } elseif ($newData['integation'] === 2) {
                $errorIN = '';
                $errorIN .= empty($errorIN) ? 'integation' : ', integation';
            }
            if ($newData['mailTemplate'] === 0) {
                unset($updated_data['mailTem']);
            } elseif ($newData['mailTemplate'] === 2) {
                $errorIN .= empty($errorIN) ? 'mailTemplate' : ', mailTemplate';
            }
            if ($newData['workflow'] === 0) {
                unset($updated_data['workFlows']);
            } elseif ($newData['workflow'] === 2) {
                $errorIN .= empty($errorIN) ? 'workflow' : ', workflow';
            }
            if ($newData['reports'] === 2) {
                $errorIN .= empty($errorIN) ? 'reports' : ', reports';
            }
            if (!empty($errorIN)) {
                $updated_data['message'] = sprintf(__('Error Occured in saving %s', 'bit-form'), $errorIN);
                return new WP_Error('Form update Error.', $updated_data);
            }
            $updated_data['message'] = __('Form updated successfully.', 'bit-form');
            return $updated_data;
        }
    }
    public function changeFormStatus($Request, $post)
    {
        if (isset($Request['status']) && $Request['id']) {
            $status = wp_unslash($Request['status']);
            $id = wp_unslash($Request['id']);
        } else {
            $status = wp_unslash($post->status);
            $id = wp_unslash($post->id);
        }
        $user_details = static::$ipTool->getUserDetail();
        // return $post->fields;
        $status = $status === 'true' || $status === true ? true : false;
        if (!is_bool($status) || is_null($id)) {
            // echo $status;
            return new WP_Error('status_change', __('Form status change failed.', 'bit-form'));
        }
        $update_status = static::$formModel->update(
            array(
                "user_id" => $user_details['id'],
                "user_ip" => $user_details['ip'],
                "user_device" => $user_details['device'],
                "status" => $status ? 1 : 0,
                "updated_at" => $user_details['time'],
            ),
            array(
                "id" => $id,
            )
        );
        if (is_wp_error($update_status)) {
            return $update_status;
        } elseif (!$update_status) {
            return false;
        } else {
            return __('Form status changed successfully', 'bit-form');
        }
    }
    public function changeBulkFormStatus($Request, $post)
    {
        if (isset($Request['status']) && $Request['formID']) {
            $status = wp_unslash($Request['status']);
            $formID = wp_unslash($Request['formID']);
        } else {
            $status = wp_unslash($post->status);
            $formID = wp_unslash($post->formID);
        }
        $user_details = static::$ipTool->getUserDetail();
        // return $post->fields;
        $status = $status === 'true' || $status === true ? true : false;
        if (!is_bool($status) || is_null($formID) || !is_array($formID)) {
            // echo $status;
            return new WP_Error('status_change', __('Form status change failed.', 'bit-form'));
        }
        $update_status = static::$formModel->bulkUpdate(
            array(
                "user_id" => $user_details['id'],
                "user_ip" => $user_details['ip'],
                "user_device" => $user_details['device'],
                "status" => $status ? 1 : 0,
                "updated_at" => $user_details['time'],
            ),
            array(
                "id" => $formID,
            )
        );
        if (is_wp_error($update_status)) {
            return $update_status;
        } elseif (!$update_status) {
            return false;
        } else {
            return __('Form status changed successfully', 'bit-form');
        }
    }
    public function getAllForm()
    {
        global $wpdb;

        $allForms = $wpdb->get_results("SELECT forms.id,forms.form_name,forms.status,forms.views,forms.created_at,COUNT(entries.id) as entries FROM `{$wpdb->prefix}bitforms_form` as forms LEFT JOIN `{$wpdb->prefix}bitforms_form_entries` as entries ON forms.id = entries.form_id GROUP BY forms.id");

        if (is_wp_error($allForms)) {
            return $allForms;
        } elseif (!$allForms) {
            return [];
        } else {
            return $allForms;
        }
    }

    public function getAForm($Request, $post)
    {
        if (isset($Request['id'])) {
            $formID = wp_unslash($Request['id']);
        } else {
            $formID = wp_unslash($post->id);
        }
        // return $post->fields;
        if (is_null($formID)) {
            return new WP_Error('empty_form', __('Form id is empty.', 'bit-form'));
        }
        $formManager = new AdminFormManager($formID);
        if (!$formManager->isExist()) {
            return new WP_Error('not_exists', __('Form is not exists.', 'bit-form'));
        }
        $form_content = $formManager->getFormContent();
        if ($formManager->isExist()) {
            $form_content_arr = array(
                'layout' => $form_content->layout,
                'fields' => $form_content->fields,
                'form_name' => $formManager->getFormName(),
                'workFlowExist' => $form_content->workFlowExist,
            );
            $successMessageHandler
                = new SuccessMessageHandler($formID);
            $successMessages = $successMessageHandler->getAllMessage();
            if (!is_wp_error($successMessages)) {
                foreach ($successMessages as $sucessMessagekey => $sucessMessagevalue) {
                    $allConfirmation['type']['successMsg'][$sucessMessagekey] =
                        array(
                            'id' => $sucessMessagevalue->id,
                            'title' => $sucessMessagevalue->message_title,
                            'msg' => $sucessMessagevalue->message_content,
                        );
                }
            }
            $emailTemplateHandler
                = new EmailTemplateHandler($formID);
            $emailTemplates = $emailTemplateHandler->getAllTemplate();
            if (!is_wp_error($emailTemplates)) {
                foreach ($emailTemplates as $emailTemplatekey => $emailTemplatevalue) {
                    $mailTem[] =
                        array(
                            'id' => $emailTemplatevalue->id,
                            'title' => $emailTemplatevalue->title,
                            'sub' => $emailTemplatevalue->sub,
                            'body' => $emailTemplatevalue->body,
                        );
                }
            }
            $integrationHandler = new IntegrationHandler($formID);
            $formIntegrations = $integrationHandler->getAllIntegration('form');
            if (!is_wp_error($formIntegrations)) {
                foreach ($formIntegrations as $integrationkey => $integrationValue) {
                    if ($integrationValue->integration_type === 'redirectPage' || $integrationValue->integration_type === 'webHooks') {
                        $integrationData = array(
                            'id' => $integrationValue->id,
                            'title' => $integrationValue->integration_name,
                        );
                        if (!empty($integrationValue->integration_details)) {
                            // var_dump(json_decode($integrationValue->integration_details));
                            $integration_details = (array) json_decode($integrationValue->integration_details);
                            $integrationData = array_merge($integration_details, $integrationData);
                        } else {
                            $integration_details = array(
                                'url' => '',
                            );
                            $integrationData = array_merge($integrationData, $integration_details);
                        }
                        $allConfirmation['type'][$integrationValue->integration_type][]
                            = $integrationData;
                    } else {
                        $integrationData = array(
                            'id' => $integrationValue->id,
                            'name' => $integrationValue->integration_name,
                            'type' => $integrationValue->integration_type,
                        );
                        $integrations[] = array_merge(
                            $integrationData,
                            is_string($integrationValue->integration_details) ?
                                (array) json_decode($integrationValue->integration_details) :
                                $integrationValue->integration_details
                        );
                    }
                }
            }
            $settingsContent = array(
                'formName' => $formManager->getFormName(),
                'theme' => $form_content->theme,
                // 'submitBtn' => $form_content->buttons,
            );

            if (!empty($allConfirmation)) {
                $confirmation = array(
                    'confirmation' => $allConfirmation,
                );
            } else {
                $confirmation = array(
                    'confirmation' => array('type' => []),
                );
            }
            $settingsContent = array_merge($settingsContent, $confirmation);
            if (!empty($integrations)) {
                $settingsContent = array_merge(
                    $settingsContent,
                    array(
                        'integrations' => $integrations,
                    )
                );
            } else {
                $settingsContent = array_merge(
                    $settingsContent,
                    array(
                        'integrations' => []
                    )
                );
            }
            if (!empty($mailTem)) {
                $settingsContent = array_merge(
                    $settingsContent,
                    array(
                        'mailTem' => $mailTem,
                    )
                );
            } else {
                $settingsContent = array_merge(
                    $settingsContent,
                    array(
                        'mailTem' => []
                    )
                );
            }
            $formSettings = array(
                'formSettings' => $settingsContent,
            );
            $data = array(
                "id" => $formID,
                "form_name" => $formManager->getFormName(),
                "form_content" => $form_content_arr,
                "additional" => empty($form_content->additional) ? array(
                    "enabled" => array(
                        "blocked_ip" => false,
                        "restrict_form" => false,
                    ),
                    "settings" => array(
                        "restrict_form" => array(
                            'day' => [],
                            'date' => array(
                                'from' => null,
                                'to' => null,
                            ),
                            'time' => array(
                                'from' => null,
                                'to' => null,
                            ),
                        ),
                        "entry_limit" => null,
                        "blocked_ip" => [],
                    ),
                    "onePerIp" => false,
                ) : $form_content->additional,
                "created_at" => $formManager->getFormMetaData()['created_at'],
                "views" => $formManager->getFormMetaData()['views'],
                "entries" => $formManager->getFormMetaData()['entries'],
                "status" => $formManager->getFormMetaData()['status'],
            );
            $data = array_merge($data, $formSettings);
            $workFlowHandler = new WorkFlowHandler($formID);
            $workFlows = array('workFlows' => $workFlowHandler->getAllworkFlow());
            $data = array_merge($data, $workFlows);
            $reportsModel = new ReportsModel();
            $returnedReportData = $reportsModel->get(
                array(
                    "id",
                    "details",
                    "isDefault",
                    "type",
                ),
                array(
                    "category" => 'form',
                    "context" => $formID,
                )
            );
            if (!is_wp_error($returnedReportData)) {
                $fieldNames = [];
                foreach ($formManager->getFieldLabel() as $key => $field) {
                    $fieldNames[$field['key']] = isset($field->lbl) ? $field->lbl : '';
                }
                if ($formManager->isGCLIDEnabled()) {
                    $fieldNames["GCLID"] = "GCLID";
                }
                foreach ($returnedReportData as $reportKey => $reportData) {
                    $reportDetails = $reportsModel->validateReportFields($reportData, $fieldNames);
                    if (!empty($reportDetails) && is_string($reportDetails)) {
                        $returnedReportData[$reportKey]->details = json_decode($reportDetails);
                    } elseif (!empty($reportDetails)) {
                        $returnedReportData[$reportKey]->details = $reportDetails;
                    } else {
                        $returnedReportData[$reportKey]->details = [];
                    }
                }
                $reports = array('reports' => $returnedReportData);
                $data = array_merge($data, $reports);
            }

            $formFields = $formManager->getFieldLabel();
            $data = array_merge($data, array('Labels' => $formFields));

            return $data;
        }
    }

    public function deleteAForm($Request, $post)
    {
        if (isset($Request['id'])) {
            $formID = wp_unslash($Request['id']);
        } else {
            $formID = wp_unslash($post->id);
        }
        if (is_null($formID)) {
            return new WP_Error('empty_form', __('Form id is empty.', 'bit-form'));
        }
        $delete_status = static::$formModel->delete(
            array(
                "id" => $formID,
            )
        );

        $successMessageModel
            = new SuccessMessageModel();
        $deleteMsgStatus = $successMessageModel->delete(['form_id' => $formID]);

        $emailTemplateModel
            = new EmailTemplateModel();
        $deleteTemplateStatus = $emailTemplateModel->delete(['form_id' => $formID]);

        $integrationModel = new IntegrationModel();
        $deleteIntegrationStatus = $integrationModel->delete(['form_id' => $formID]);

        $workFlowModel = new WorkFlowModel();
        $deleteworkFlowStatus = $workFlowModel->delete(['form_id' => $formID]);

        $reportsModel = new ReportsModel();
        $deleteReportStatus = $reportsModel->delete(['context' => $formID]);

        $formEntryModel = new FormEntryModel();
        $returnedEntries = $formEntryModel->get("id", ["form_id" => $formID]);
        $entries = [];

        if (!is_wp_error($returnedEntries)) {
            foreach ($returnedEntries as $entryKey => $entryInfo) {
                $entries[] = $entryInfo->id;
            }
            global $wpdb;
            $prefix = $wpdb->prefix;
            if (count($entries) > 0) {
                $deleteEntriesStatus = $formEntryModel->bulkDelete(
                    array(
                        "`{$prefix}bitforms_form_entries`.`id`" => $entries,
                        "`{$prefix}bitforms_form_entries`.`form_id`" => $formID,
                    )
                );
            }
        }

        $fileHandler = new FileHandler();
        if (file_exists(BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . $formID)) {
            $fileHandler->rmrf(BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . $formID);
        }
        if (file_exists(BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . $formID)) {
            $fileHandler->rmrf(BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . $formID);
        }
        if (file_exists(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles' . DIRECTORY_SEPARATOR . 'bitform-' . $formID . '.css')) {
            unlink(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles' . DIRECTORY_SEPARATOR . 'bitform-' . $formID . '.css');
        }
        if (file_exists(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles' . DIRECTORY_SEPARATOR . 'bitform-layout-' . $formID . '.css')) {
            unlink(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles' . DIRECTORY_SEPARATOR . 'bitform-layout-' . $formID . '.css');
        }
        return is_wp_error($delete_status) ? $delete_status : __('Form deleted successfully.', 'bit-form');
    }
    public function deleteBlukForm($Request, $post)
    {
        if (isset($Request['formID'])) {
            $formID = wp_unslash($Request['formID']);
        } else {
            $formID = wp_unslash($post->formID);
        }
        if (is_null($formID) || !is_array($formID)) {
            return new WP_Error('empty_form', __('Form id is empty.', 'bit-form'));
        }

        foreach ($formID as $id) {
            unlink(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles' . DIRECTORY_SEPARATOR . 'bitform-' . $id . '.css');
            unlink(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles' . DIRECTORY_SEPARATOR . 'bitform-layout-' . $id . '.css');
        }
        $delete_status = static::$formModel->bulkDelete(
            array(
                "id" => $formID,
            )
        );
        $successMessageModel
            = new SuccessMessageModel();
        $deleteMsgStatus = $successMessageModel->bulkDelete(['form_id' => $formID]);

        $emailTemplateModel
            = new EmailTemplateModel();
        $deleteTemplateStatus = $emailTemplateModel->bulkDelete(['form_id' => $formID]);

        $integrationModel = new IntegrationModel();
        $deleteIntegrationStatus = $integrationModel->bulkDelete(['form_id' => $formID]);

        $workFlowModel = new WorkFlowModel();
        $deleteworkFlowStatus = $workFlowModel->bulkDelete(['form_id' => $formID]);

        $reportsModel = new ReportsModel();
        $deleteReportStatus = $reportsModel->bulkDelete(['context' => $formID]);

        $formEntryModel = new FormEntryModel();
        $returnedEntries = $formEntryModel->get("id", ["form_id" => $formID]);
        $entries = [];
        foreach ($returnedEntries as $entryKey => $entryInfo) {
            $entries[] = $entryInfo->id;
        }
        global $wpdb;
        $prefix = $wpdb->prefix;
        if (count($entries) > 0) {
            $deleteEntriesStatus = $formEntryModel->bulkDelete(
                array(
                    "`{$prefix}bitforms_form_entries`.`id`" => $entries,
                    "`{$prefix}bitforms_form_entries`.`form_id`" => $formID,
                )
            );
        }

        $fileHandler = new FileHandler();
        foreach ($formID as $fId) {
            if (file_exists(BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . $fId)) {
                $fileHandler->rmrf(BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . $fId);
            }
            unlink(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles' . DIRECTORY_SEPARATOR . 'bitform-' . $fId . '.css');
            unlink(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles' . DIRECTORY_SEPARATOR . 'bitform-layout-' . $fId . '.css');
        }

        return is_wp_error($delete_status) ? $delete_status : __('Forms deleted successfully.', 'bit-form');
    }

    public function genarateNewLayoutNField($layout, $fields, $oldId, $newId)
    {
        $newField = (object) [];
        foreach ($layout->lg as $ind => $itm) {
            $fld_tmp = $fields->{$layout->lg[$ind]->i};
            $layout->lg[$ind]->i = str_replace("bf$oldId-", "bf$newId-", $layout->lg[$ind]->i);
            $layout->md[$ind]->i = str_replace("bf$oldId-", "bf$newId-", $layout->md[$ind]->i);
            $layout->sm[$ind]->i = str_replace("bf$oldId-", "bf$newId-", $layout->sm[$ind]->i);
            $newField->{$layout->lg[$ind]->i} = $fld_tmp;
        }
        return ['layout' => $layout, 'fields' => $newField];
    }

    public function duplicateAForm($Request, $post)
    {
        $oldFormId = intval($post->id);
        $newFormId = intval($post->newFormId);
        if ($oldFormId < 1) {
            return new WP_Error('empty_form', __('Form id is empty.', 'bit-form'));
        }

        $formManager = new AdminFormManager($oldFormId);
        if (!$formManager->isExist()) {
            return new WP_Error('empty_form', __('Form does not exists.', 'bit-form'));
        }

        $duplicatedForm = $this->getAForm($Request, $post);
        $duplicatedForm['form_name'] = 'Duplicate of ' . $duplicatedForm['form_name'];
        $formData = FormDuplicateHelper::createReplica($duplicatedForm, $oldFormId, $newFormId);


        /* echo wp_json_encode($formData);
        \wp_die(); */
        $duplicateResponse = $this->createNewForm(null, $formData);

        if (!is_wp_error($duplicateResponse) || (is_wp_error($duplicateResponse) && $duplicateResponse->get_error_code() === 'result_empty')) {
            // duplicate stylesheet
            $style_dir = BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles' . DIRECTORY_SEPARATOR;
            // layout style copy
            $mainFormLayStyle = fopen($style_dir . 'bitform-layout-' . $oldFormId . '.css', 'r');
            $styleStr =  fread($mainFormLayStyle, filesize($style_dir . 'bitform-layout-' . $oldFormId . '.css'));
            $newStyle =  str_replace(".bf$oldFormId-", ".bf$newFormId-", $styleStr);
            $createStyleFile = fopen($style_dir . "bitform-layout-$newFormId.css", 'w');
            fwrite($createStyleFile, $newStyle);
            fclose($createStyleFile);
            // style copy
            $mainFormStyle = fopen($style_dir . 'bitform-' . $oldFormId . '.css', 'r');
            $styleStr =  fread($mainFormStyle, filesize($style_dir . 'bitform-' . $oldFormId . '.css'));
            $newStyle =  str_replace("-$oldFormId", "-$newFormId", $styleStr);
            $createStyleFile = fopen($style_dir . "bitform-$newFormId.css", 'w');
            fwrite($createStyleFile, $newStyle);
            fclose($createStyleFile);
            $duplicatedForm = $this->getAForm(null, (object) ['id' => $newFormId]);
            $duplicatedForm['message'] = __('Form duplicated successfully', 'bit-form');
            return $duplicatedForm;
        }
        return $duplicateResponse;
    }

    public function importAForm($post)
    {

        $oldFormId = !empty($post->formDetail->form_id) ? $post->formDetail->form_id : null;
        $newFormId = intval($post->newFormId);
        if (!$oldFormId || empty($post->formDetail->fields) || empty($post->formDetail->layout)) {
            return new WP_Error('invalid_form', __('Please import a valid json.', 'bit-form'));
        }
        $importtedForm = (array)$post->formDetail;
        $importtedForm['form_content']['fields'] = $importtedForm['fields'];
        $importtedForm['form_content']['layout'] = $importtedForm['layout'];
        unset($importtedForm['fields'], $importtedForm['layout']);
        $formData = FormDuplicateHelper::createReplica((array)$importtedForm, $oldFormId, $newFormId);

        if (!empty($importtedForm['rowHeight'])) {
            $formData->rowHeight = $importtedForm['rowHeight'];
        }

        if (!empty($importtedForm['formStyle'])) {
            $formData->formStyle = str_replace("-$oldFormId", "-$newFormId", $importtedForm['formStyle']);
            $formData->layoutChanged = '-';
        }
        $importResponse = $this->createNewForm(null, $formData);

        if (!is_wp_error($importResponse) || (is_wp_error($importResponse) && $importResponse->get_error_code() === 'result_empty')) {
            $responseData = $this->getAForm(null, (object) ['id' => $newFormId]);
            $responseData['message'] = __('Form imported successfully.', 'bit-form');
            return $responseData;
        }
        return $importResponse;
    }

    public function exportAForm($Request)
    {
        $oldFormId = intval($Request['id']);
        if ($oldFormId < 1) {
            return new WP_Error('empty_form', __('Form id is empty.', 'bit-form'));
        }
        $formManager = new AdminFormManager($oldFormId);
        if (!$formManager->isExist()) {
            return new WP_Error('empty_form', __('Form does not exists.', 'bit-form'));
        }

        $newFormId = $oldFormId;
        $duplicatedForm = $this->getAForm($Request, (object)["id" => $oldFormId]);
        $formData = FormDuplicateHelper::createReplica($duplicatedForm, $oldFormId, $newFormId);

        $style_dir = BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles' . DIRECTORY_SEPARATOR;
        $mainFormStyle = fopen($style_dir . 'bitform-' . $oldFormId . '.css', 'r');
        $styleStr =  fread($mainFormStyle, filesize($style_dir . 'bitform-' . $oldFormId . '.css'));

        $formData->formStyle =  str_replace("-$oldFormId", "-$newFormId", $styleStr);
        $formData->layoutChanged =  "-";
        $formData->rowHeight =  FormDuplicateHelper::calcRowHeight($styleStr, $oldFormId);

        $formJson = json_encode($formData);
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header('Content-Disposition: attachment; filename="bitform_export_' . $oldFormId . '.json"');
        header('Content-Description: File Transfer');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . strlen($formJson));
        header("Content-Transfer-Encoding: binary ");
        flush();
        echo $formJson;
        die();
    }

    public function getFormEntryLabelAndCount($Request, $post)
    {
        if (isset($Request['id'])) {
            $id = wp_unslash($Request['id']);
        } else {
            $id = wp_unslash($post->id);
        }
        if (is_null($id)) {
            return new WP_Error('empty_form', __('Form id is empty.', 'bit-form'));
        }
        $formManager = new AdminFormManager($id);
        if (!$formManager->isExist()) {
            return new WP_Error('empty_form', __('Form does not exists.', 'bit-form'));
        }
        $formFields = $formManager->getFieldLabel();
        $formEntry = new FormEntryModel();
        $count = $formEntry->count(
            array(
                'form_id' => $id,
            )
        );
        $reportsModel = new ReportsModel();
        $returnedReportData = $reportsModel->get(
            array(
                "id",
                "details",
                "isDefault",
                "type",
            ),
            array(
                "category" => 'form',
                "context" => $id,
            )
        );
        $labels = [];
        if (!is_wp_error($returnedReportData)) {
            $fieldNames = [];
            foreach ($formFields as $field) {
                $fieldNames[$field['key']] = $field['name'];
                $labels[$field['key']] = $field;
            }
            foreach ($returnedReportData as $reportKey => $reportData) {
                $reportDetails = $reportsModel->validateReportFields($reportData, $fieldNames);
                if (!empty($reportDetails) && is_string($reportDetails)) {
                    $returnedReportData[$reportKey]->details = json_decode($reportDetails);
                } elseif (!empty($reportDetails)) {
                    $returnedReportData[$reportKey]->details = $reportDetails;
                }
            }
            $response['reports'] = empty($returnedReportData) ? [] : $returnedReportData;
        }

        $response['count'] = intval($count[0]->count);
        $response['Labels'] = $formFields;
        $response['fieldDetails'] = $labels;
        return $response;
    }
    public function getFormEntry($Request, $post)
    {
        if (isset($Request['id'])) {
            $id = wp_unslash($Request['id']);
            $offset = isset($Request['offset']) ?
                wp_unslash($Request['offset']) : 0;
            $pageSize = isset($Request['pageSize']) ?
                wp_unslash($Request['pageSize']) : 10;
        } else {
            $id = wp_unslash($post->id);
            $offset = isset($post->offset) ? wp_unslash($post->offset) : 0;
            $sortBy = isset($post->sortBy) ? wp_unslash($post->sortBy) : null;
            $filters = isset($post->filters) ? wp_unslash($post->filters) : null;
            $globalFilter = isset($post->globalFilter) ? wp_unslash($post->globalFilter) : null;
            $pageSize = isset($post->pageSize) ?
                wp_unslash($post->pageSize) : 10;
        }
        if (is_null($id)) {
            return new WP_Error('empty_form', __('Form id is empty.', 'bit-form'));
        }
        $formManager = new AdminFormManager($id);
        if (!$formManager->isExist()) {
            return new WP_Error('empty_form', __('Form does not exists', 'bit-form'));
        }

        $formEntry = new FormEntryModel();
        $entries = $formEntry->get(
            'id',
            array(
                'form_id' => $id,
            ),
            null,
            null,
            'created_at',
            'DESC'
        );
        if (is_wp_error($entries)) {
            if ($entries->get_error_code() === 'result_empty') {
                return array(
                    'count' => 0,
                    'entries' => [],
                );
            }
            return $entries;
        }
        $filter['field'] = $filters;
        $filter['global'] = $globalFilter;

        $formFields = $formManager->getFieldLabel(true);
        $fieldDetails = $formManager->getFields();

        $entryMeta = new FormEntryMetaModel();
        $formEntries = $entryMeta->getEntryMeta($formFields, $entries, $pageSize, $offset, $filter, $sortBy);
        $customFieldHandler = new CustomFieldHandler();
        $formEntries = $customFieldHandler->updatedEntries($formEntries, $fieldDetails);
        return $formEntries;
    }

    public function getExportEntry($post)
    {
        $formId = wp_unslash($post->formId);
        $limit = isset($post->limit) ? wp_unslash($post->limit) : null;
        $sortBy = isset($post->sort) ? wp_unslash($post->sort) : 'ASC';
        $sortByField = isset($post->sortField) ? wp_unslash($post->sortField) : 'bitforms_form_entry_id';
        $formFields = json_decode($post->selectedField);
        if (is_null($formId)) {
            return new WP_Error('empty_form', __('Form id is empty.', 'bit-form'));
        }
        $formManager = new AdminFormManager($formId);
        $fieldLabels = $formManager->getFieldLabel(true);
        if (!$formManager->isExist()) {
            return new WP_Error('empty_form', __('Form does not exists', 'bit-form'));
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
                return array(
                    'count' => 0,
                    'entries' => [],
                );
            }
            return $entries;
        }
        $entryMeta = new FormEntryMetaModel();
        $filter = [];
        $formEntries = $entryMeta->getExportEntry($formFields, $entries,  $formId, $fieldLabels, $limit, $sortBy, $sortByField);
        return $formEntries;
    }
    public function deleteBlukFormEntries($Request, $post)
    {
        if (isset($Request['formID'])) {
            $formID = wp_unslash($Request['formID']);
            $entries = wp_unslash($Request['entries']);
        } else {
            $formID = wp_unslash($post->formID);
            $entries = wp_unslash($post->entries);
        }
        if (is_null($formID) || !is_array($entries) || count($entries) === 0) {
            return new WP_Error('empty_form', __('Invalid Form ID or Entries ID.', 'bit-form'));
        }
        $formManager = new AdminFormManager($formID);
        if (!$formManager->isExist()) {
            return new WP_Error('empty_form', __('Form does not exist.', 'bit-form'));
        }
        $workFlowRunHelper = new WorkFlowRunHelper($formID);
        $workFlowreturnedOnDelete = $workFlowRunHelper->executeOnDelete(
            $formManager,
            $formID,
            $entries
        );
        if (isset($workFlowreturnedOnDelete['entries'])) {
            if (count($workFlowreturnedOnDelete['entries']) === 0) {
                return ["message" => __('Entry Deletetion prevented by  workflow', 'bit-form')];
            } elseif (count($workFlowreturnedOnDelete['entries']) === count($entries)) {
                $message = __('Entry Deleted successfully', 'bit-form');
            } else {
                $result['prevented'] = array_diff($entries, $workFlowreturnedOnDelete['entries']);
                $entries = $workFlowreturnedOnDelete['entries'];
                $message = __('Entry Deleted successfully, Some prevented by workflow', 'bit-form');
            }
        } else {
            $message = __('Entry Deleted successfully', 'bit-form');
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
            return new WP_Error('entry_not_exists', __("Form entry deletion failed.", "bit-form"));
        }
        $result['message'] = $message;
        return $result;
    }
    public function duplicateFormEntry($Request, $post)
    {
        if (isset($Request['formID'])) {
            $formID = wp_unslash($Request['formID']);
            $entries = wp_unslash($Request['entries']);
        } else {
            $formID = wp_unslash($post->formID);
            $entries = wp_unslash($post->entries);
        }
        if (is_null($formID) || !is_array($entries) || empty($entries)) {
            return new WP_Error('empty_form', __('Form id or entries id is invalid', 'bit-form'));
        }

        $formManager = new AdminFormManager($formID);
        if (!$formManager->isExist()) {
            return new WP_Error('empty_form', __('Form does not exist', 'bit-form'));
        }
        $formEntryModel = new FormEntryModel();
        $entryMeta = new FormEntryMetaModel();
        $user_details = static::$ipTool->getUserDetail();
        $total_entries = count($entries);
        $duplicate_count = 0;
        $test = '';
        $fileHandler = new FileHandler();
        $result = array();
        foreach ($entries as $entryIndex => $entryID) {
            $duplicatedEntryId = $formEntryModel->insert(
                array(
                    'form_id' => $formID,
                    'user_id' => $user_details['id'],
                    'user_ip' => $user_details['ip'],
                    'user_device' => $user_details['device'],
                    'referer' => 'duplicate of #' . $entryID,
                    'status' => 0,
                    'created_at' => $user_details['time'],
                )
            );
            if ($duplicatedEntryId) {
                $duplicate_status = $entryMeta->duplicateEntryMeta(
                    array(
                        'duplicateID' => $duplicatedEntryId,
                        'entryID' => $entryID,
                    )
                );
                if ($duplicate_status) {
                    $result['details'][$entryID] = $duplicatedEntryId;
                    $duplicate_count = $duplicate_count + 1;
                    if (file_exists(BITFORMS_UPLOAD_DIR . "/$formID/$entryID")) {
                        $fileHandler->cpyr(
                            BITFORMS_UPLOAD_DIR . "/$formID/$entryID",
                            BITFORMS_UPLOAD_DIR . "/$formID/$duplicatedEntryId"
                        );
                    }
                }
            }
        }

        $count = $formEntryModel->count(
            array(
                'form_id' => $formID,
            )
        );
        $formManager->resetSubmissionCount(intval($count[0]->count));
        $result['message'] = count($entries) === 1 ? __('Entry Duplicated successfully', 'bit-form') : __('Entries Duplicated successfully', 'bit-form');
        return ($total_entries === $duplicate_count) ? $result : false;
    }
    public function editFormEntry($Request, $post)
    {
        if (isset($Request['formID'])) {
            $formID = wp_unslash($Request['formID']);
            $entryID = wp_unslash($Request['entryID']);
        } else {
            $formID = wp_unslash($post->formID);
            $entryID = wp_unslash($post->entryID);
        }
        if (is_null($formID) || is_null($entryID)) {
            return new WP_Error('empty_form', __('Form id or entries id is invalid', 'bit-form'));
        }

        $formManager = new AdminFormManager($formID);
        if (!$formManager->isExist()) {
            return new WP_Error('empty_form', __('Form does not exist', 'bit-form'));
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
            return new WP_Error('empty_form', __('Form entries does not exist.', 'bit-form'));
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
        $layout = $formContent->layout;
        foreach ($form_fields as $key => $value) {
            // $field_name = preg_replace('/[\`\~\!\@\#\$\'\.\s\?\+\-\*\&\|\/\\!]/', '_', $value->lbl);
            if (isset($entries[$key])) {
                $form_fields->{$key}->val = $entries[$key];
                $form_fields->{$key}->name = $key;
            }
        }
        $workFlowRunHelper = new WorkFlowRunHelper($formID);
        $workFlowreturnedOnLoad = $workFlowRunHelper->executeOnLoad(
            'edit',
            $form_fields
        );
        $workFlowreturnedOnUserInput = $workFlowRunHelper->executeOnUserInput(
            'edit',
            $form_fields
        );
        // var_dump($workFlowreturned);
        if (!empty($workFlowreturnedOnLoad['fields'])) {
            $form_fields = $workFlowreturnedOnLoad['fields'];
        }
        $formData = array(
            'layout' => $layout,
            'fields' => $form_fields,
            'conditional' => !empty($workFlowreturnedOnUserInput['conditional']) ? $workFlowreturnedOnUserInput['conditional'] : false,
            'fieldToCheck' => !empty($workFlowreturnedOnUserInput['fieldToCheck']) ? $workFlowreturnedOnUserInput['fieldToCheck'] : false,
            'fieldToChange' => !empty($workFlowreturnedOnUserInput['fieldToChange']) ? $workFlowreturnedOnUserInput['fieldToChange'] : false,
            'fieldsKey' => $fieldsKey,
        );
        return $formData;
    }
    public function updateFormEntry($Request, $post)
    {
        if (isset($Request['formID']) && isset($Request['entryID'])) {
            $formID = wp_unslash($Request['formID']);
            $entryID = wp_unslash($Request['entryID']);
            unset($Request['action']);
            unset($Request['formID']);
            unset($Request['entryID']);
            $updatedValue = wp_unslash($post);
        }

        if (is_null($updatedValue) || !is_array($updatedValue)) {
            return new WP_Error('empty_data', __('Failed to update, Data is empty.', 'bit-form'));
        }

        if (is_null($formID) || is_null($entryID)) {
            return new WP_Error('empty_data', __('Invalid Form ID or entries ID.', 'bit-form'));
        }

        $formManager = new AdminFormManager($formID);
        if (!$formManager->isExist()) {
            return new WP_Error('empty_data', __('Form does not exist.', 'bit-form'));
        }
        return $formManager->updateFormEntry($updatedValue, $formID, $entryID);
    }

    public function getLogHistory($Request, $post)
    {
        if (isset($Request['formID'])) {
            $formID = wp_unslash($Request['formID']);
            $entryID = wp_unslash($Request['entryID']);
        } else {
            $formID = wp_unslash($post->formID);
            $entryID = wp_unslash($post->entryID);
        }

        if (is_null($formID) || is_null($entryID)) {
            return new WP_Error('empty_form', __('Invalid Form ID or Entries ID.', 'bit-form'));
        }

        $formManager = new AdminFormManager($formID);
        if (!$formManager->isExist()) {
            return new WP_Error('empty_form', __('Form does not exist.', 'bit-form'));
        }
        $formLogModel = new FormEntryLogModel();

        $log_history = $formLogModel->geLogHistory($formID, $entryID);
        return $log_history;
    }

    public function importDataStore($Request, $post)
    {
        if (isset($Request['formID'])) {
            $formID = wp_unslash($Request['formID']);
        } else {
            $formID = wp_unslash($post->formID);
        }
        if (is_null($formID)) {
            return new WP_Error('empty_form', __('Invalid Form id or entries id.', 'bit-form'));
        }
    }

    public function getAllWPPages($Request, $post)
    {
        $pages = get_pages(array('post_status' => 'publish', 'sort_column' => 'post_date', 'sort_order' => 'desc'));
        $allPages = array();
        foreach ($pages as $pageKey => $pageDetails) {
            $allPages[$pageKey]['title'] = $pageDetails->post_title;
            $allPages[$pageKey]['url'] = get_page_link($pageDetails->ID);
        }
        return $allPages;
    }
    public function deleteAIntegration($Request, $post)
    {
        if (isset($Request['formID']) && $Request['id']) {
            $formID = json_decode(wp_unslash($Request['formID']));
            $id = wp_unslash($Request['id']);
        } else {
            $formID = wp_unslash($post->formID);
            $id = wp_unslash($post->id);
        }
        if ($formID < 0 || empty($id)) {
            return new WP_Error('empty_form', 'Invalid Form ID or Integration ID.');
        }

        $integrationHandler = new IntegrationHandler($formID);
        $delete_status = $integrationHandler->deleteIntegration($id);
        if (is_wp_error($delete_status)) {
            return $delete_status;
        }

        return array(
            'message' => __('Integration deleted', 'bit-form'),
        );
    }

    public function deleteSuccessMessage($Request, $post)
    {
        if (isset($Request['formID']) && $Request['id']) {
            $formID = json_decode(wp_unslash($Request['formID']));
            $id = wp_unslash($Request['id']);
        } else {
            $formID = wp_unslash($post->formID);
            $id = wp_unslash($post->id);
        }
        if (empty($formID) || empty($id)) {
            return new WP_Error('empty_form', 'Invalid Form ID or Message ID.');
        }
        $successMessageHandler
            = new SuccessMessageHandler($formID);
        $delete_status = $successMessageHandler->deleteMessage($id);
        if (is_wp_error($delete_status)) {
            return $delete_status;
        }
        return array(
            'message' => __('Message deleted', 'bit-form'),
        );
    }

    public function deleteAWorkflow($Request, $post)
    {
        if (isset($Request['formID']) && $Request['id']) {
            $formID = json_decode(wp_unslash($Request['formID']));
            $id = wp_unslash($Request['id']);
        } else {
            $formID = wp_unslash($post->formID);
            $id = wp_unslash($post->id);
        }
        if (empty($formID) || empty($id)) {
            return new WP_Error('empty_form', 'Invalid Form ID or Workflow ID.');
        }
        $workFlowHandler = new WorkFlowHandler($formID);
        $delete_status = $workFlowHandler->deleteworkFlow($id);
        if (is_wp_error($delete_status)) {
            return $delete_status;
        }
        return array(
            'message' => __('workflow deleted', 'bit-form'),
        );
    }
    public function deleteAMailTemplate($Request, $post)
    {
        if (isset($Request['formID']) && $Request['id']) {
            $formID = json_decode(wp_unslash($Request['formID']));
            $id = wp_unslash($Request['id']);
        } else {
            $formID = wp_unslash($post->formID);
            $id = wp_unslash($post->id);
        }
        if (empty($formID) || empty($id)) {
            return new WP_Error('empty_form', 'Invalid Form ID or  Email Template ID.');
        }
        $emailTemplateHandler = new EmailTemplateHandler($formID);
        $delete_status = $emailTemplateHandler->deleteTemplate($id);
        if (is_wp_error($delete_status)) {
            return $delete_status;
        }
        return array(
            'message' => __('Email Template deleted', 'bit-form'),
        );
    }
    public function duplicateAMailTemplate($Request, $post)
    {
        if (isset($Request['formID']) && $Request['id']) {
            $formID = json_decode(wp_unslash($Request['formID']));
            $id = wp_unslash($Request['id']);
        } else {
            $formID = wp_unslash($post->formID);
            $id = wp_unslash($post->id);
        }
        if (empty($formID) || empty($id)) {
            return new WP_Error('empty_form', 'Invalid Form ID or Email Template ID.');
        }
        $emailTemplateHandler = new EmailTemplateHandler($formID);
        $duplicate_status = $emailTemplateHandler->duplicateTemplate($id);
        if (is_wp_error($duplicate_status)) {
            return $duplicate_status;
        }
        return array(
            'message' => __('Email Template duplicated', 'bit-form'),
        );
    }
    public function setAllFormsReport($Request, $post)
    {
        $reports = empty($post->reports) ? null : wp_unslash($post->reports);
        if (empty($reports)) {
            return new WP_Error('empty_report_prefs', 'Report data is Empty, nothing to save or update.');
        }
        if (!empty($reports)) {
            $reportsModel = new ReportsModel();
            $user_details = static::$ipTool->getUserDetail();
            foreach ($reports as $reportIndex => $report) {
                if (empty($report->id)) {
                    $reportSaveSatus = $reportsModel->insert(
                        array(
                            "type" => 'table',
                            "category" => 'app',
                            "context" => 'allForm',
                            "details" => is_string($report->details) ? $report->details : wp_json_encode($report->details),
                            "isDefault" => 1,
                            "user_id" => $user_details['id'],
                            "user_ip" => $user_details['ip'],
                            "user_device" => $user_details['device'],
                            "created_at" => $user_details['time'],
                            "updated_at" => $user_details['time'],
                        )
                    );
                    $newData['reports'] = 1;
                } else {
                    $reportSaveSatus = $reportsModel->update(
                        array(
                            "type" => 'table',
                            "category" => 'app',
                            "context" => 'allForm',
                            "details" => is_string($report->details) ? $report->details : wp_json_encode($report->details),
                            "isDefault" => 1,
                            "user_id" => $user_details['id'],
                            "user_ip" => $user_details['ip'],
                            "user_device" => $user_details['device'],
                            "updated_at" => $user_details['time'],
                        ),
                        array(
                            'id' => $report->id,
                        )
                    );
                }
            }
        }
        if (is_wp_error($reportSaveSatus)) {
            if ($reportSaveSatus->get_error_code() === 'result_empty') {
                return new WP_Error('empty_report_prefs', 'Nothing to save or update');
            }
            return new WP_Error('error_report_prefs', 'Error occured in saving reports');
        }
        $returnedReportData = $reportsModel->get(
            array(
                "id",
                "details",
                "isDefault",
                "category",
                "context",
            ),
            array(
                "category" => 'app',
                "context" => 'allForm',
            )
        );
        if (!is_wp_error($returnedReportData)) {
            foreach ($returnedReportData as $reportKey => $reportData) {
                if (isset($reportData->details) && is_string($reportData->details)) {
                    $returnedReportData[$reportKey]->details = json_decode($reportData->details);
                }
            }
            $reports = $returnedReportData;
        }
        return array(
            'message' => __('Report preferrences saved successfully', 'bit-form'),
            'reports' => empty($reports) ? [] : $reports,
        );
    }

    public function savegReCaptcha($Request, $post)
    {
        $reCaptcha = $post->reCaptcha;

        if (is_null($reCaptcha)) {
            return new WP_Error('empty_gcaptchdetails', __('g-ReCAPTCHA details is empty', 'bit-form'));
        }
        if (is_string($reCaptcha) && !empty(json_decode($reCaptcha)->id)) {
            $reCaptcha = json_decode($reCaptcha);
            $integrationID = $reCaptcha->id;
            unset($reCaptcha->id);
        } else {
            $integrationID = $reCaptcha->id;
            unset($reCaptcha->id);
        }
        $reCaptcha = json_encode($reCaptcha);
        $integrationName = 'google reCaptcha';
        $integrationType = $post->version === 'v2' ? 'gReCaptcha' : 'gReCaptchaV3';
        $integrationDetails = $reCaptcha;
        $user_details = static::$ipTool->getUserDetail();
        $integrationHandler = new IntegrationHandler(0, $user_details);
        $response = array();
        if (empty($integrationID)) {
            $captchaSaveStatus = $integrationHandler->saveIntegration($integrationName, $integrationType, $integrationDetails, 'app');
            $response['id'] = $captchaSaveStatus;
            $response['message'] = __('g-reCAPTCHA saved successfully', 'bit-form');
        } else {
            $captchaSaveStatus = $integrationHandler->updateIntegration($integrationID, $integrationName, $integrationType, $integrationDetails, 'app');
            $response['message'] = __('g-reCAPTCHA updated successfully', 'bit-form');
        }

        if (is_wp_error($captchaSaveStatus)) {
            return $captchaSaveStatus;
        }
        return $response;
    }

    public function savePaymentSetting($Request, $post)
    {
        if (isset($post->paySetting)) {
            $paySetting = $post->paySetting;
        } else {
            $paySetting = $post->paySetting;
        }
        if (is_null($paySetting)) {
            return new WP_Error('empty_gcaptchdetails', __('Setting details is empty', 'bit-form'));
        }
        if (is_string($paySetting) && !empty(json_decode($paySetting)->id)) {
            $paySetting = json_decode($paySetting);
            $integrationID = $paySetting->id;
            unset($paySetting->id);
        } else {
            $integrationID = $paySetting->id;
            unset($paySetting->id);
        }
        $integrationName = $paySetting->name;
        $integrationType = 'payments';
        $paySetting = json_encode($paySetting);
        $integrationDetails = $paySetting;
        $user_details = static::$ipTool->getUserDetail();
        $integrationHandler = new IntegrationHandler(0, $user_details);
        $response = array();
        if (empty($integrationID)) {
            $paymentSaveStatus = $integrationHandler->saveIntegration($integrationName, $integrationType, $integrationDetails, 'app');
            $response['id'] = $paymentSaveStatus;
            $response['message'] = __('Payment setting saved successfully', 'bit-form');
        } else {
            $paymentSaveStatus = $integrationHandler->updateIntegration($integrationID, $integrationName, $integrationType, $integrationDetails, 'app');
            $response['message'] = __('Payment setting updated successfully', 'bit-form');
        }

        if (is_wp_error($paymentSaveStatus)) {
            return $paymentSaveStatus;
        }
        return $response;
    }
}
