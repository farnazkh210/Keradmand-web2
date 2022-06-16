<?php

namespace BitCode\BitForm\Core\WorkFlow;

use BitCode\BitForm\Core\Util\MailConfig;
use BitCode\BitForm\Core\Util\MailNotifier;
use BitCode\BitForm\Admin\Form\AdminFormManager;
use BitCode\BitForm\Core\Database\WorkFlowModel;
use BitCode\BitForm\Core\Util\FieldValueHandler;
use BitCode\BitForm\Core\Integration\Integrations;
use BitCode\BitForm\Core\Database\FormEntryMetaModel;
use BitCode\BitForm\Core\Messages\EmailTemplateHandler;
use BitCode\BitForm\Core\Integration\IntegrationHandler;
use BitCode\BitForm\Core\Messages\SuccessMessageHandler;
use BitCode\BitForm\Core\Util\SmartTags;

final class WorkFlowRunHelper
{
    private static $_formID;
    private static $_workFlowModel;
    private $_user_details;

    public function __construct($formID, $user_details = null)
    {
        static::$_formID = $formID;
        static::$_workFlowModel = new WorkFlowModel();
        $this->_user_details = $user_details;
    }

    public function getWorkFlow($workFlowRun, $workFlowType, $workFlowIds = null)
    {
        if ($workFlowIds == null) {
            $condition = array(
                'form_id' => static::$_formID,
            );
        } else {
            $condition = array(
                'id' =>  array($workFlowIds),
            );
        }
        if (!empty($workFlowRun)) {
            $condition = \array_merge(
                $condition,
                array(
                    'workflow_run' => $workFlowRun
                )
            );
        }
        if (!empty($workFlowType)) {
            $condition = \array_merge(
                $condition,
                array(
                    'workflow_type' => $workFlowType
                )
            );
        }
        $workFlows =  static::$_workFlowModel->get(
            array(
                'id',
                'workflow_name',
                'workflow_type',
                'workflow_run',
                'workflow_behaviour',
                'workflow_condition',
                'workflow_action'
            ),
            $condition,
            null,
            null,
            'id'
        );
        if (empty($workFlows) || is_wp_error($workFlows)) {
            return [];
        }
        return $workFlows;
    }

    public function smartFldMargeFormFld($fieldData, $reffer = false)
    {

        $fieldKeys = SmartTags::smartTagFieldKeys();

        $data = SmartTags::getPostUserData($reffer);

        foreach ($fieldKeys as $key) {

            $fldKey = '${' . $key . '}';

            $val = SmartTags::getSmartTagValue($key, $data);

            $fieldData[$fldKey] = array(
                'key' => $key,
                'value' => empty($val) ? '' : $val,
                'type' => $val,
            );
        }

        return $fieldData;
    }

    public function executeOnLoad($workFlowRun, $fields)
    {
        $workFlows = $this->getWorkFlow(array("create_edit", $workFlowRun), array('onload'));
        $workFlowReturnable = array();
        if (empty($workFlows) || is_wp_error($workFlows)) {
            return [];
        }
        $fieldData = array();

        foreach ($fields as $fieldKey => $fieldDetail) {
            // $fieldLabel = !empty($fieldDetail->lbl) ? preg_replace('/[\`\~\!\@\#\$\'\.\s\?\+\-\*\&\|\/\\!]/', '_', $fieldDetail->lbl) : null;
            $fieldData[$fieldKey] = array(
                'key' => $fieldKey,
                'value' => empty($fieldDetail->val) ? '' : $fieldDetail->val,
                'type' => $fieldDetail->typ,
            );
            if (isset($fieldDetail->mul)) {
                $fieldData[$fieldKey] =
                    array_merge(
                        $fieldData[$fieldKey],
                        array(
                            'mul' => true
                        )
                    );
            }
        }
        $fieldData = $this->smartFldMargeFormFld($fieldData);

        foreach ($workFlows as $key => $value) {
            $allAction  = json_decode($value->workflow_action);
            if ($value->workflow_behaviour === 'cond' && !$this->getConditionStatus(json_decode($value->workflow_condition), $fieldData)) {
                continue;
            }
            foreach ($allAction->action as $actionKey => $actionDetail) {
                if (!empty($actionDetail->action) && !empty($actionDetail->field)) {
                    switch ($actionDetail->action) {
                        case 'value':
                            if (!empty($actionDetail->val) && !empty($fieldData[$actionDetail->field]['key'])) {
                                $actionValue = $this->replaceFieldWithValue($actionDetail->val, $fieldData);
                                $fields->{$fieldData[$actionDetail->field]['key']}->val = $actionValue;
                                $fieldData[$actionDetail->field]['value'] = $actionValue;
                            }
                            break;

                        case 'hide':
                            if (!empty($fieldData[$actionDetail->field]['key'])) {
                                $fields->{$fieldData[$actionDetail->field]['key']}->valid->hide = true;
                            }
                            break;

                        case 'disable':
                            if (!empty($fieldData[$actionDetail->field]['key'])) {
                                $fields->{$fieldData[$actionDetail->field]['key']}->valid->disabled = true;
                            }
                            break;

                        case 'readonly':
                            if (!empty($fieldData[$actionDetail->field]['key'])) {
                                $fields->{$fieldData[$actionDetail->field]['key']}->valid->readonly = true;
                            }
                            break;

                        case 'enable':
                            if (!empty($fieldData[$actionDetail->field]['key'])) {
                                $fields->{$fieldData[$actionDetail->field]['key']}->valid->disabled = false;
                            }
                            break;

                        case 'show':
                            if (!empty($fieldData[$actionDetail->field]['key'])) {
                                $fields->{$fieldData[$actionDetail->field]['key']}->valid->hide = false;
                                if ($fields->{$fieldData[$actionDetail->field]['key']}->typ === 'hidden') {
                                    $fields->{$fieldData[$actionDetail->field]['key']}->typ = 'text';
                                }
                            }
                            break;
                    }
                }
            }
        }
        $workFlowReturnable['fields'] = $fields;
        return $workFlowReturnable;
    }
    public function executeOnUserInput($workFlowRun, $fields)
    {
        $workFlows = $this->getWorkFlow(array("create_edit", $workFlowRun), array('oninput'));
        $workFlowReturnable = array();
        if (empty($workFlows) || is_wp_error($workFlows)) {
            return [];
        }

        $fieldData = array();
        foreach ($fields as $fieldKey => $fieldDetail) {
            // if (!empty($fieldDetail->lbl)) {
            //     $fieldData[$fieldKey . preg_replace('/[\`\~\!\@\#\$\'\.\s\?\+\-\*\&\|\/\\!]/', '_', $fieldDetail->lbl)] = $fieldKey;
            // } else {
            //     $fieldData[$fieldKey] = $fieldKey;
            // }
            $fieldData[$fieldKey] = $fieldKey;
        }
        foreach ($workFlows as $key => $value) {
            $allAction  = json_decode($value->workflow_action);
            $logics = json_decode($value->workflow_condition);
            $onUserInputWorkFlow = array(
                'logics' => $logics,
                'actions' => $allAction->action
            );
            foreach ($fieldData as $fieldfName => $fieldKey) {
                if ((strpos(wp_json_encode($logics), $fieldfName)) !== false) {
                    if (isset($workFlowReturnable['fieldToCheck'][$fieldfName])) {
                        $workFlowReturnable['fieldToCheck'][$fieldfName]  = array_merge($workFlowReturnable['fieldToCheck'][$fieldfName], [$key]);
                    } else {
                        $workFlowReturnable['fieldToCheck'][$fieldfName] = [$key];
                    }
                }
            }

            foreach ($allAction->action as $logic) {
                if (!is_null($logic->field) && isset($fieldData[$logic->field])) {
                    $workFlowReturnable['fieldToChange'][$logic->field] = $fieldData[$logic->field];
                }
            }
            $workFlowReturnable['conditional'][] = $onUserInputWorkFlow;
        }
        return $workFlowReturnable;
    }
    public function executeOnValidate($workFlowRun, $fieldData, $fieldValue)
    {
        $workFlows = $this->getWorkFlow(array("create_edit", $workFlowRun), 'onvalidate');
        $workFlowReturnable = array();
        if (empty($workFlows) || is_wp_error($workFlows)) {
            return [];
        }
        $fieldData = $this->smartFldMargeFormFld($fieldData);
        foreach ($workFlows as $key => $value) {
            $allAction  = json_decode($value->workflow_action);
            if ($value->workflow_behaviour === 'cond' && !$this->getConditionStatus(json_decode($value->workflow_condition), $fieldData)) {
                continue;
            }
            if (is_array($allAction->validateMsg)) {
                foreach ($allAction->validateMsg as $validateMsg) {
                    if (!empty($validateMsg)) {
                        $id = json_decode($validateMsg)->id;
                        $successMessageHandler
                            = new SuccessMessageHandler(static::$_formID);
                        $successMessage = $successMessageHandler->getAMessage($id);
                        if (!is_wp_error($successMessage) && !empty($successMessage)) {
                            $workFlowReturnable[] = $this->replaceFieldWithValue($successMessage[0]->message_content, $fieldValue);
                        }
                    }
                }
            } else {
                if (!empty($allAction->validateMsg)) {
                    $id = json_decode($allAction->validateMsg)->id;
                    $successMessageHandler
                        = new SuccessMessageHandler(static::$_formID);
                    $successMessage = $successMessageHandler->getAMessage($id);
                    if (!is_wp_error($successMessage) && !empty($successMessage)) {
                        $workFlowReturnable[] = $this->replaceFieldWithValue($successMessage[0]->message_content, $fieldValue);
                    }
                }
            }
            if ($allAction->action) {
                $fldActions = $allAction->action;
                foreach ($fldActions as $fldAction) {
                    if ($fldAction->action === 'required' && empty($fieldValue[$fldAction->field])) {
                        $workFlowReturnable[] = 'required';
                    }
                }
            }
        }
        return $workFlowReturnable;
    }
    public function executeOnSubmit($workFlowRun, $fields, $fieldValue, $entryID, $logID, $workflowsIds = null)
    {
        $workFlows = $this->getWorkFlow(array("create_edit", $workFlowRun), array('onsubmit'), $workflowsIds);
        $workFlowReturnable = array();
        if (empty($workFlows) || is_wp_error($workFlows)) {
            $workFlowReturnable['message'] = $this->setDefaultSubmitConfirmation('successMsg', $fieldValue);
            if (empty($workFlowReturnable['message'])) {
                $workFlowReturnable['message'] = $workFlowRun !== 'edit' ? __('Form Submitted Successfully', 'bit-form')
                    : __('Entry Updated Successfully', 'bit-form');
            }
            $workFlowReturnable['dflt_message'] = true;
            $workFlowReturnable['redirectPage'] = $this->setDefaultSubmitConfirmation('redirectPage', $fieldValue);
            $data = [
                "integrations" => [$this->setDefaultSubmitConfirmation('webHooks', $fieldValue, $logID)],
                "entryID" => $entryID,
                "logID" => $logID,
                "formID" => static::$_formID,
            ];
            $workFlowReturnable['triggerData'] = $data;
            $workFlowReturnable['cron'] = false;
            return $workFlowReturnable;
        }

        $fieldData = array();
        foreach ($fields as $fieldKey => $fieldDetail) {
            // $fieldLabel = !empty($fieldDetail->lbl) ? preg_replace('/[\`\~\!\@\#\$\'\.\s\?\+\-\*\&\|\/\\!]/', '_', $fieldDetail->lbl) : null;
            $fieldData[$fieldKey] = array(
                'key' => $fieldKey,
                'value' => empty($fieldDetail->val) ? '' : $fieldDetail->val,
                'type' => $fieldDetail->typ,
            );
            if (isset($fieldDetail->mul)) {
                $fieldData[$fieldKey] =
                    array_merge(
                        $fieldData[$fieldKey],
                        array(
                            'mul' => true
                        )
                    );
            }
        }
        $fieldData = $this->smartFldMargeFormFld($fieldData);

        $isWebHookQueued = false;
        $isCronOK = !defined('DOING_CRON') &&  wp_doing_ajax() && (!defined('DISABLE_WP_CRON') || (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON));
        if ($isCronOK) {
            // From wp spawn_cron()
            $gmt_time = microtime(true);
            $lock = get_transient('doing_cron');
            if ($lock > $gmt_time + 10 * MINUTE_IN_SECONDS) {
                $lock = 0;
            }
            if ($lock + WP_CRON_LOCK_TIMEOUT > $gmt_time) {
                $isCronOK = false;
            }
        }
        $integrationsToExc = [];
        $mailData = null;
        foreach ($workFlows as $key => $value) {
            $allAction  = json_decode($value->workflow_action);
            if ($value->workflow_behaviour === 'cond' && !$this->getConditionStatus(json_decode($value->workflow_condition), $fieldData)) {
                continue;
            }
            if (!empty($allAction->action)) {
                foreach ($allAction->action as $actionKey => $actionDetail) {
                    if (!empty($actionDetail->action) && !empty($actionDetail->field)) {
                        switch ($actionDetail->action) {
                            case 'value':
                                if (!empty($actionDetail->val)) {
                                    $actionValue = $this->replaceFieldWithValue($actionDetail->val, $fieldData);
                                    $fieldValue[$actionDetail->field] = $actionValue;
                                }
                                break;
                        }
                    }
                }
            }
            if (!empty($allAction->successAction)) {
                foreach ($allAction->successAction as $successActionIndex => $successActionDetail) {
                    switch ($successActionDetail->type) {
                        case 'successMsg':
                            if (!empty($successActionDetail->details->id)) {
                                $id = json_decode($successActionDetail->details->id)->id;
                                $successMessageHandler
                                    = new SuccessMessageHandler(static::$_formID);
                                $successMessage = $successMessageHandler->getAMessage($id);
                                if (!is_wp_error($successMessage) && !empty($successMessage)) {
                                    $workFlowReturnable['message'] = $this->replaceFieldWithValue($successMessage[0]->message_content, $fieldValue);
                                }
                            }
                            break;
                        case 'redirectPage':
                            if (!empty($successActionDetail->details->id)) {
                                $id = json_decode($successActionDetail->details->id)->id;
                                $integrationHandler = new IntegrationHandler(static::$_formID);
                                $redirectPage = $integrationHandler->getAIntegration($id, 'form', 'redirectPage');
                                if (!is_wp_error($redirectPage) && !empty($redirectPage)) {
                                    $url = json_decode($redirectPage[0]->integration_details)->url;
                                    if (!empty($url)) {
                                        $url = $this->replaceFieldWithValue($url, $fieldValue);
                                    }
                                    $workFlowReturnable['redirectPage'] = empty($url) ? false : esc_url_raw($url);
                                }
                            }
                            break;
                        case 'webHooks':
                            if (!empty($successActionDetail->details->id)) {
                                if (!$isWebHookQueued) {
                                    $isWebHookQueued = true;
                                }
                                $webHooks = $successActionDetail->details->id;
                                $integrationsToExc[] = $webHooks;
                            }
                            break;
                        case 'mailNotify':
                            if (!empty($successActionDetail->details->id)) {
                                $mailData[] = $successActionDetail->details;
                            }
                            break;

                        case 'integ':

                            if (!empty($successActionDetail->details->id)) {
                                $integrations = $successActionDetail->details->id;
                                $integrationsToExc[] = $integrations;
                            }
                            break;

                        default:
                            break;
                    }
                }
            }
            $workFlowReturnable['fields'] = $fieldValue;
        }

        if (empty($workFlowReturnable['message'])) {
            $workFlowReturnable['message'] = $this->setDefaultSubmitConfirmation('successMsg', $fieldValue);
            if (empty($workFlowReturnable['message'])) {
                $workFlowReturnable['message'] = $workFlowRun !== 'edit' ? __('Form Submitted Successfully', 'bit-form')
                    : __('Entry Updated Successfully', 'bit-form');
            }
            $workFlowReturnable['dflt_message'] = true;
        }
        if (empty($workFlowReturnable['redirectPage'])) {
            $workFlowReturnable['redirectPage'] = $this->setDefaultSubmitConfirmation('redirectPage', $fieldValue);
        }
        if (!$isWebHookQueued) {
            $integrationsToExc[] = $this->setDefaultSubmitConfirmation('webHooks', $fieldValue, 1);
        }

        if (!empty($integrationsToExc)) {
            $data = [
                "mail" => $mailData,
                "integrations" => $integrationsToExc,
                "entryID" => $entryID,
                "logID" => $logID,
                "formID" => static::$_formID,
            ];
            $workFlowReturnable['triggerData'] = $data;
            if ($isCronOK) {
                $workFlowReturnable['cron'] = true;
            } else {
                $workFlowReturnable['cron'] = false;
            }
        }
        return $workFlowReturnable;
    }
    public function executeOnDelete(AdminFormManager $formManager, $formID, $entries)
    {
        $workFlows = $this->getWorkFlow(array("delete"), 'delete');
        $workFlowReturnable = array();
        if (empty($workFlows) || is_wp_error($workFlows) || empty($entries)) {
            return [];
        }
        if (!$formManager instanceof AdminFormManager) {
            $formManager = new AdminFormManager($formID);
        }
        $returnableEntries = $entries;
        $formFields = $formManager->getFieldLabel();
        $entryMeta = new FormEntryMetaModel();
        $entryDetails = new \stdClass;
        foreach ($entries as $key => $entryID) {
            $entryDetails->id = $entryID;
            $entryValues = $entryMeta->getEntryMeta(
                $formFields,
                [$entryDetails]
            );
            if (!empty($entryValues['entries'][0])) {
                $fieldValue = (array)$entryValues['entries'][0];
                unset($fieldValue['entry_id']);
                $fields = $formManager->getFormContentWithValue($fieldValue)->fields;
                $fieldData = array();
                foreach ($fields as $fieldKey => $fieldDetail) {
                    // $fieldLabel = !empty($fieldDetail->lbl) ? preg_replace('/[\`\~\!\@\#\$\'\.\s\?\+\-\*\&\|\/\\!]/', '_', $fieldDetail->lbl) : null;
                    $fieldData[$fieldKey] = array(
                        'key' => $fieldKey,
                        'value' => empty($fieldDetail->val) ? '' : $fieldDetail->val,
                        'type' => $fieldDetail->typ,
                    );
                    if (isset($fieldDetail->mul)) {
                        $fieldData[$fieldKey] =
                            array_merge(
                                $fieldData[$fieldKey],
                                array(
                                    'mul' => true
                                )
                            );
                    }
                }
                $fieldData = $this->smartFldMargeFormFld($fieldData);
                foreach ($workFlows as $key => $value) {
                    $allAction  = json_decode($value->workflow_action);
                    if ($value->workflow_behaviour === 'cond' && !$this->getConditionStatus(json_decode($value->workflow_condition), $fieldData)) {
                        continue;
                    }
                    $isExists = \array_search($entryID, $returnableEntries);
                    if (!empty($allAction->avoid_delete) && $isExists !== false) {
                        unset($returnableEntries[$isExists]);
                        $returnableEntries = array_values($returnableEntries);
                    } elseif ($isExists === false) {
                        $returnableEntries[] = $entryID;
                    }
                    if (!empty($allAction->successAction)) {
                        foreach ($allAction->successAction as $successActionIndex => $successActionDetail) {
                            switch ($successActionDetail->type) {
                                case 'webHooks':
                                    if (!empty($successActionDetail->details->id)) {
                                        $webHooks = $successActionDetail->details->id;
                                        Integrations::executeIntegrations($webHooks, $fieldValue, static::$_formID);
                                    }
                                    break;
                                case 'mailNotify':
                                    if (!empty($successActionDetail->details->id)) {
                                        $emailTemplateHandler
                                            = new EmailTemplateHandler(static::$_formID);
                                        if (is_string($successActionDetail->details->id)) {
                                            $mailTemplateID = json_decode($successActionDetail->details->id)->id;
                                            $mailTemplate = $emailTemplateHandler->getATemplate($mailTemplateID);
                                            if (!is_wp_error($mailTemplate)) {
                                                $mailTo = FieldValueHandler::validateMailArry($successActionDetail->details->to, $fieldValue);
                                                if (!empty($mailTo)) {
                                                    (new MailConfig())->sendMail();
                                                    $mailSubject = $this->replaceFieldWithValue($mailTemplate[0]->sub, $fieldValue);
                                                    $mailBody = $this->replaceFieldWithValue($mailTemplate[0]->body, $fieldValue);

                                                    $mailHeaders = array();

                                                    if (!empty($successActionDetail->details->bcc)) {
                                                        $mailBCC = FieldValueHandler::validateMailArry($successActionDetail->details->bcc, $fieldValue);
                                                        if (is_array($mailBCC)) {
                                                            foreach ($mailBCC as $key => $emailAddress) {
                                                                $mailHeaders[] = "Bcc: " . trim($emailAddress);
                                                            }
                                                        } else {
                                                            $mailHeaders[] = "Bcc: " . trim($mailBCC);
                                                        }
                                                    }
                                                    if (!empty($successActionDetail->details->cc)) {
                                                        $mailCC = FieldValueHandler::validateMailArry($successActionDetail->details->cc, $fieldValue);
                                                        if (is_array($mailCC)) {
                                                            foreach ($mailCC as $key => $emailAddress) {
                                                                $mailHeaders[] = "Cc: " . trim($emailAddress);
                                                            }
                                                        } else {
                                                            $mailHeaders[] = "Cc: " . trim($mailCC);
                                                        }
                                                    }
                                                    if (!empty($successActionDetail->details->from)) {
                                                        $mailFrom = FieldValueHandler::validateMailArry($successActionDetail->details->from, $fieldValue);
                                                        $fromName = !empty($successActionDetail->details->fromName) ? $successActionDetail->details->fromName : explode('@', $mailFrom[0])[0];
                                                        $mailHeaders[] = "FROM: " . $fromName . " <" . trim($mailFrom[0]) . ">";
                                                    }
                                                    add_filter('wp_mail_content_type', [$this, 'filterMailContentType']);
                                                    $status = wp_mail($mailTo, $mailSubject, $mailBody, $mailHeaders);
                                                    remove_filter('wp_mail_content_type', [$this, 'filterMailContentType']);
                                                }
                                            }
                                        }
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                    }
                }
            }
        }
        $workFlowReturnable['entries'] = array_values($returnableEntries);
        return $workFlowReturnable;
    }

    private function getConditionStatus($workFlowCondition, $data)
    {
        if (is_array($workFlowCondition)) {
            foreach ($workFlowCondition as $sskey => $ssvalue) {
                if (!is_string($ssvalue)) {
                    $isCondition = $this->getConditionStatus($ssvalue, $data);
                    if ($sskey === 0) {
                        $conditionSatus = $isCondition;
                    }
                    if ($sskey - 1 >= 0 && is_string($workFlowCondition[$sskey - 1])) {
                        switch (strtolower($workFlowCondition[$sskey - 1])) {
                            case 'or':
                                $conditionSatus = $conditionSatus || $isCondition;
                                break;

                            case 'and':
                                $conditionSatus = $conditionSatus && $isCondition;
                                break;

                            default:
                                break;
                        }
                    }
                }
            }
            return (bool) $conditionSatus;
        } else {
            $workFlowCondition->val = $this->replaceFieldWithValue($workFlowCondition->val, $data);
            switch ($workFlowCondition->logic) {
                case 'equal':
                    if ((isset($data[$workFlowCondition->field]['mul']) && $data[$workFlowCondition->field]['mul'])
                        || $data[$workFlowCondition->field]['type'] === 'check'
                    ) {
                        $fieldValue = !empty($data[$workFlowCondition->field]['value']) ? $data[$workFlowCondition->field]['value'] : [];
                        if (is_string($fieldValue)) {
                            if ($fieldValue[0] === '[' && $fieldValue[strlen($fieldValue) - 1] === ']') {
                                $fieldValue = json_decode($fieldValue);
                            } else {
                                $fieldValue = explode(',', $fieldValue);
                            }
                        }
                        $valueToCheck = \explode(',', $workFlowCondition->val);
                        if (count($valueToCheck) !== count($fieldValue)) {
                            return false;
                        }
                        $checker = 0;
                        foreach ($valueToCheck as $key => $value) {
                            if (!empty($fieldValue) && \in_array($value, $fieldValue)) {
                                $checker = $checker + 1;
                            }
                        }
                        if ($checker === count($valueToCheck) && count($valueToCheck) === count($fieldValue)) {
                            return true;
                        }
                        return false;
                    }
                    return $data[$workFlowCondition->field]['value'] === $workFlowCondition->val;

                case 'not_equal':
                    if ((isset($data[$workFlowCondition->field]['mul']) && $data[$workFlowCondition->field]['mul'])
                        || $data[$workFlowCondition->field]['type'] === 'check'
                    ) {
                        $fieldValue = !empty($data[$workFlowCondition->field]['value']) ? $data[$workFlowCondition->field]['value'] : [];
                        if (is_string($fieldValue)) {
                            if ($fieldValue[0] === '[' && $fieldValue[strlen($fieldValue) - 1] === ']') {
                                $fieldValue = json_decode($fieldValue);
                            } else {
                                $fieldValue = explode(',', $fieldValue);
                            }
                        }
                        $valueToCheck = \explode(',', $workFlowCondition->val);
                        $valueToCheckLenght = count($valueToCheck);
                        if ($valueToCheckLenght !== count($fieldValue)) {
                            return true;
                        }
                        $checker = 0;
                        foreach ($valueToCheck as $key => $value) {
                            if (!in_array($value, $fieldValue)) {
                                $checker += 1;
                                // var_dump($checker, $valueToCheckLenght, $fieldValue);
                            }
                        }
                        return $valueToCheckLenght === $checker;
                    }
                    return $data[$workFlowCondition->field]['value'] !== $workFlowCondition->val;

                case 'null':
                    // var_dump('null',empty($data[$workFlowCondition->field]['value']));
                    return empty($data[$workFlowCondition->field]['value']);

                case 'not_null':
                    // var_dump('!null',$data[$workFlowCondition->field]['value']);
                    return !empty($data[$workFlowCondition->field]['value']);

                case 'contain':
                    if (!isset($data[$workFlowCondition->field]['value'])) {
                        return false;
                    }
                    if ((isset($data[$workFlowCondition->field]['mul']) && $data[$workFlowCondition->field]['mul'])
                        || $data[$workFlowCondition->field]['type'] === 'check'
                    ) {
                        $fieldValue = !empty($data[$workFlowCondition->field]['value']) ? $data[$workFlowCondition->field]['value'] : [];
                        if (is_string($fieldValue)) {
                            if ($fieldValue[0] === '[' && $fieldValue[strlen($fieldValue) - 1] === ']') {
                                $fieldValue = json_decode($fieldValue);
                            } else {
                                $fieldValue = explode(',', $fieldValue);
                            }
                        }
                        $valueToCheck = \explode(',', $workFlowCondition->val);
                        $checker = 0;
                        foreach ($valueToCheck as $key => $value) {
                            if (\in_array($value, $fieldValue)) {
                                $checker = $checker + 1;
                            }
                        }
                        if ($checker > 0) {
                            return true;
                        }
                        return false;
                    }
                    return isset($data[$workFlowCondition->field]['value']) && stripos($data[$workFlowCondition->field]['value'], $workFlowCondition->val) !== false;

                case 'contain_all':
                    if (!isset($data[$workFlowCondition->field]['value'])) {
                        return false;
                    }
                    if ((isset($data[$workFlowCondition->field]['mul']) && $data[$workFlowCondition->field]['mul'])
                        || $data[$workFlowCondition->field]['type'] === 'check'
                    ) {
                        $fieldValue = !empty($data[$workFlowCondition->field]['value']) ? $data[$workFlowCondition->field]['value'] : [];
                        if (is_string($fieldValue)) {
                            if ($fieldValue[0] === '[' && $fieldValue[strlen($fieldValue) - 1] === ']') {
                                $fieldValue = json_decode($fieldValue);
                            } else {
                                $fieldValue = explode(',', $fieldValue);
                            }
                        }
                        $valueToCheck = \explode(',', $workFlowCondition->val);
                        $checker = 0;
                        foreach ($valueToCheck as $key => $value) {
                            if (\in_array($value, $fieldValue)) {
                                $checker = $checker + 1;
                            }
                        }
                        if ($checker >= count($valueToCheck)) {
                            return true;
                        }
                        return false;
                    }
                    return isset($data[$workFlowCondition->field]['value']) && stripos($data[$workFlowCondition->field]['value'], $workFlowCondition->val) !== false;

                case 'not_contain':
                    if (!isset($data[$workFlowCondition->field]['value'])) {
                        return false;
                    }
                    if ((isset($data[$workFlowCondition->field]['mul']) && $data[$workFlowCondition->field]['mul'])
                        || $data[$workFlowCondition->field]['type'] === 'check'
                    ) {
                        $fieldValue = !empty($data[$workFlowCondition->field]['value']) ? $data[$workFlowCondition->field]['value'] : [];
                        if (is_string($fieldValue)) {
                            if ($fieldValue[0] === '[' && $fieldValue[strlen($fieldValue) - 1] === ']') {
                                $fieldValue = json_decode($fieldValue);
                            } else {
                                $fieldValue = explode(',', $fieldValue);
                            }
                        }
                        $valueToCheck = \explode(',', $workFlowCondition->val);
                        $checker = 0;
                        foreach ($valueToCheck as $key => $value) {
                            if (!in_array($value, $fieldValue)) {
                                $checker = $checker + 1;
                            }
                        }
                        if ($checker === count($valueToCheck)) {
                            return true;
                        }
                        return false;
                    }
                    return stripos($data[$workFlowCondition->field]['value'], $workFlowCondition->val) === false;

                case 'greater':
                    if (!isset($data[$workFlowCondition->field]['value'])) {
                        return false;
                    }
                    return isset($data[$workFlowCondition->field]['value']) && $data[$workFlowCondition->field]['value'] > $workFlowCondition->val;

                case 'less':
                    if (!isset($data[$workFlowCondition->field]['value'])) {
                        return false;
                    }
                    return isset($data[$workFlowCondition->field]['value']) && $data[$workFlowCondition->field]['value'] < $workFlowCondition->val;

                case 'greater_or_equal':
                    if (!isset($data[$workFlowCondition->field]['value'])) {
                        return false;
                    }
                    return isset($data[$workFlowCondition->field]['value']) && $data[$workFlowCondition->field]['value'] >= $workFlowCondition->val;

                case 'less_or_equal':
                    if (!isset($data[$workFlowCondition->field]['value'])) {
                        return false;
                    }
                    return isset($data[$workFlowCondition->field]['value']) && $data[$workFlowCondition->field]['value'] <= $workFlowCondition->val;

                case 'start_with':
                    if (!isset($data[$workFlowCondition->field]['value'])) {
                        return false;
                    }
                    return isset($data[$workFlowCondition->field]['value']) && stripos($data[$workFlowCondition->field]['value'], $workFlowCondition->val) === 0;

                case 'end_with':
                    if (!isset($data[$workFlowCondition->field]['value'])) {
                        return false;
                    }
                    $fieldValue = $data[$workFlowCondition->field]['value'];
                    $fieldValueLength = strlen($data[$workFlowCondition->field]['value']);
                    $compareValue = strtolower($workFlowCondition->val);
                    $compareValueLength = strlen($workFlowCondition->val);
                    $fieldValueEnds = strtolower(substr($fieldValue, $fieldValueLength - $compareValueLength, $fieldValueLength));
                    return $compareValue === $fieldValueEnds;


                default:
                    return false;
            }
        }
    }

    private function replaceFieldWithValue($stringToReplaceField, $fieldValues)
    {
        $stringToReplaceField = FieldValueHandler::replaceFieldWithValue($stringToReplaceField, $fieldValues);
        return $this->evalMathExpression($stringToReplaceField);
    }

    private function evalMathExpression($stringWithFieldValue)
    {
        $mathExpr = $stringWithFieldValue;
        if (empty($mathExpr)) {
            return $stringWithFieldValue;
        }
        preg_match_all('/[\+\-\*\/\s]+/', $mathExpr, $isMathExpr);
        if (empty($isMathExpr[0])) {
            return $stringWithFieldValue;
        }
        preg_match_all('/\w+/', $mathExpr, $exprValues);
        if (empty($exprValues[0])) {
            return $stringWithFieldValue;
        }

        foreach ($exprValues[0] as  $opreands) {
            if (!is_numeric($opreands)) {
                return $stringWithFieldValue;
            }
        }
        $validOperator = ['+', '-', '*', '^', '/'];
        foreach ($isMathExpr[0] as $key => $value) {
            if (!in_array(trim($value), $validOperator)) {
                return $stringWithFieldValue;
            }
        }
        $mathExpr = str_replace(' ', null, $mathExpr);
        $mathExpr = preg_replace('/\{|\[|\(/', '(', $mathExpr);
        $mathExpr = preg_replace('/\}|\]/', ')', $mathExpr);
        $calculated = $this->infixToPostfixEvalute($mathExpr);
        if (!is_null($calculated)) {
            return $calculated[0];
        }
        return $stringWithFieldValue;
    }

    private function setDefaultSubmitConfirmation($confirmationType, $fieldValue, $logID = 0)
    {
        $returnableData = null;
        $integrationHandler = new IntegrationHandler(static::$_formID);
        switch ($confirmationType) {
            case 'successMsg':
                $successMessageHandler
                    = new SuccessMessageHandler(static::$_formID);
                $successMessage = $successMessageHandler->getAllMessage();
                if (!is_wp_error($successMessage) && !empty($successMessage) && count($successMessage) > 0) {
                    $returnableData = $this->replaceFieldWithValue($successMessage[0]->message_content, $fieldValue);
                }
                break;
            case 'redirectPage':
                $redirectPage = $integrationHandler->getAllIntegration('form', 'redirectPage');
                if (!is_wp_error($redirectPage) && !empty($redirectPage) && count($redirectPage) > 0) {
                    $url = json_decode($redirectPage[0]->integration_details)->url;
                    if (!empty($url)) {
                        $url = $this->replaceFieldWithValue($url, $fieldValue);
                    }
                    $returnableData = empty($url) ? '' : esc_url_raw($url);
                }
                break;
            case 'webHooks':
                $webHooks = $integrationHandler->getAllIntegration('form', 'webHooks');
                if (!is_wp_error($webHooks) && !empty($webHooks) && count($webHooks) === 1) {
                    $returnableData = ["{\"id\":{$webHooks[0]->id}}"];
                }
                break;
            default:
                break;
        }

        return $returnableData;
    }

    public function infixToPostfixEvalute($expression)
    {
        $operatorStack = [];
        $outputQueue = [];
        $numTemp = null;
        for ($strIndex = 0; $strIndex < strlen($expression); $strIndex++) {
            $token = $expression[$strIndex];
            if ($token === '+' || $token === '-' || $token === '*' || $token === '/' || $token === '^' || $token === '(' || $token === ')') {
                if (!is_null($numTemp)) {
                    $outputQueue[] = $numTemp;
                    $numTemp = null;
                }
                $stackSize = count($operatorStack);
                if ($stackSize) {
                    $stackTop = $operatorStack[$stackSize - 1];
                }
                if ($token === '(') {
                    $operatorStack[] = $token;
                } elseif ($token === ')') {
                    while ($operatorStack[count($operatorStack) - 1] !== '(') {
                        $outputQueue[] = array_pop($operatorStack);
                        if ($operatorStack[count($operatorStack) - 1] === '(') {
                            array_pop($operatorStack);
                            break;
                        }
                    }
                } elseif (isset($stackTop) && $this->operatorPrecedence($token) > $this->operatorPrecedence($stackTop)) {
                    $operatorStack[] = $token;
                } elseif ($token !== '^' && $stackSize) {
                    $operatorStack[$stackSize - 1] = $token;
                    $outputQueue[] = $stackTop;
                } else {
                    $operatorStack[] = $token;
                }
                continue;
            }
            $numTemp .= $token;
            if ($strIndex === strlen($expression) - 1 && !is_null($numTemp)) {
                $outputQueue[] = $numTemp;
            }
        }

        if (!is_null($operatorStack)) {
            $outputQueue = array_merge($outputQueue, array_reverse($operatorStack));
        }
        // print_r($outputQueue);
        // print_r($operatorStack);
        $resultStack = [];
        foreach ($outputQueue as $value) {
            if (is_numeric($value)) {
                $resultStack[] = $value;
                continue;
            }
            $secondOperand = array_pop($resultStack);
            $firstOperand = array_pop($resultStack);
            $resultStack[] = $this->calculte($firstOperand, $secondOperand, $value);
        }
        return $resultStack;
    }

    public function operatorPrecedence($operator)
    {
        switch ($operator) {
            case '^':
                return 4;
            case '*':
            case '/':
                return 3;
            case '+':
            case '-':
                return 2;
            default:
                return 0;
        }
    }

    public function calculte($firstOperand, $secondOperand, $operator)
    {
        switch ($operator) {
            case '+':
                return $firstOperand + $secondOperand;
            case '-':
                return $firstOperand - $secondOperand;
            case '*':
                return $firstOperand * $secondOperand;
            case '/':
                return $firstOperand / $secondOperand;
            case '^':
                return $firstOperand ** $secondOperand;
        }
    }

    public function filterMailContentType()
    {
        return 'text/html';
    }
}
