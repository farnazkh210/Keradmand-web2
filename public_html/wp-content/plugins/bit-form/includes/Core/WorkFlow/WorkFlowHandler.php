<?php

namespace BitCode\BitForm\Core\WorkFlow;

use BitCode\BitForm\Core\Database\WorkFlowModel;

final class WorkFlowHandler
{
    private static $_formID;
    private static $_workFlowModel;
    private $_user_details;

    public function __construct($formID, array $user_details = null)
    {
        static::$_formID = $formID;
        static::$_workFlowModel = new WorkFlowModel();
        $this->_user_details = $user_details;
    }

    public function getAllworkFlow()
    {
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
            array(
                'form_id' => static::$_formID,
            ),
            null,
            null,
            'id',
            'desc'
        );
        $workFlowsFormated = array();
        if (empty($workFlows) || is_wp_error($workFlows)) {
            return [];
        }
        foreach ($workFlows as $key => $value) {
            $allAction  = json_decode($value->workflow_action);
            $workFlow['id'] = $value->id;
            $workFlow['title'] = $value->workflow_name;
            $workFlow['action_type'] =  $value->workflow_type;
            $workFlow['action_run'] = $value->workflow_run;
            $workFlow['action_behaviour'] = $value->workflow_behaviour;
            $workFlow['logics'] = json_decode($value->workflow_condition);
            $workFlow['actions'] = $allAction->action;
            $workFlow['successAction'] = $allAction->successAction;
            if (isset($allAction->validateMsg)) {
                $workFlow['validateMsg'] = $allAction->validateMsg;
            }
            if (isset($allAction->avoid_delete)) {
                $workFlow['avoid_delete'] = $allAction->avoid_delete;
            }
            $workFlowsFormated[$key] = $workFlow;
        }

        return $workFlowsFormated;
    }

    public function saveworkFlow($workFlowDetails, $actionIntegrationDetails)
    {
        $workFlowActions['action'] = $workFlowDetails->actions;
        foreach ($workFlowDetails->successAction as $successActionkey => $successActionValue) {
            if (!empty($successActionValue->details->id)) {
                if (is_array($successActionValue->details->id)) {
                    foreach ($successActionValue->details->id as $key => $value) {
                        $actionIntegrationID = \json_decode($value);
                        $id = $this->maybeGetSuccessActionID($actionIntegrationID, $actionIntegrationDetails, $successActionValue->type);
                        $workFlowDetails->successAction[$successActionkey]->details->id[$key]
                            = $id ?
                        json_encode(
                            [
                                'id'=> "$id"
                            ]
                        )
                        : $value;
                    }
                } else {
                    $actionIntegrationID = \json_decode($successActionValue->details->id);
                    $id = $this->maybeGetSuccessActionID($actionIntegrationID, $actionIntegrationDetails, $successActionValue->type);
                    $workFlowDetails->successAction[$successActionkey]->details->id
                        = $id ?
                        json_encode(
                            [
                                'id'=> "$id"
                            ]
                        )
                        : $successActionValue->details->id;
                }
            }
        }
        $workFlowActions['successAction'] = $workFlowDetails->successAction;
        if (!empty($workFlowDetails->validateMsg)) {
            $actionIntegrationID = \json_decode($workFlowDetails->validateMsg);
            $workFlowActions['validateMsg'] = isset($actionIntegrationID->index) ?
                        json_encode(
                            [
                                'id'=>
                                $actionIntegrationDetails['successMsg'][$actionIntegrationID->index]
                            ]
                        )
                        : $workFlowDetails->validateMsg;
        }
        if (!empty($workFlowDetails->avoid_delete)) {
            $workFlowActions['avoid_delete'] = $workFlowDetails->avoid_delete;
        }
        return static::$_workFlowModel->insert(
            array(
            "workflow_name"=> $workFlowDetails->title,
            "workflow_type"=> $workFlowDetails->action_run === 'delete'? 'delete' : $workFlowDetails->action_type,
            "workflow_run"=> $workFlowDetails->action_run,
            "workflow_behaviour" => $workFlowDetails->action_behaviour,
            "workflow_condition"=> json_encode($workFlowDetails->logics),
            "workflow_action"=> json_encode($workFlowActions),
            "form_id"=> static::$_formID,
            "user_id"=> $this->_user_details['id'],
            "user_ip"=> $this->_user_details['ip'],
            "user_location"=> '',
            "user_device"=> $this->_user_details['device'],
            "created_at"=> $this->_user_details['time'],
            "updated_at" => $this->_user_details['time']
            )
        );
    }

    public function updateworkFlow($workFlowID, $workFlowDetails, $actionIntegrationDetails)
    {
        $workFlowActions['action'] = $workFlowDetails->actions;
        foreach ($workFlowDetails->successAction as $successActionkey => $successActionValue) {
            if (!empty($successActionValue->details->id)) {
                if (is_array($successActionValue->details->id)) {
                    foreach ($successActionValue->details->id as $key => $value) {
                        $actionIntegrationID = \json_decode($value);
                        $workFlowDetails->successAction[$successActionkey]->details->id[$key]
                            = isset($actionIntegrationID->index) ?
                        json_encode(
                            [
                                'id'=>
                                $actionIntegrationDetails[$successActionValue->type][$actionIntegrationID->index]
                            ]
                        )
                        : $value;
                    }
                } else {
                    $actionIntegrationID = \json_decode($successActionValue->details->id);
                    $workFlowDetails->successAction[$successActionkey]->details->id
                        = isset($actionIntegrationID->index) ?
                        json_encode(
                            [
                                'id'=>
                                $actionIntegrationDetails[$successActionValue->type][$actionIntegrationID->index]
                            ]
                        )
                        : $successActionValue->details->id;
                }
            }
        }
        $workFlowActions['successAction'] = $workFlowDetails->successAction;
        if (!empty($workFlowDetails->validateMsg)) {
            $validateMsgID = \json_decode($workFlowDetails->validateMsg);
            $workFlowActions['validateMsg'] = isset($validateMsgID->index) ?
            json_encode(
                [
                    'id'=>
                    $actionIntegrationDetails['successMsg'][$validateMsgID->index]
                    ]
            )
                    : $workFlowDetails->validateMsg;
        }
        if (!empty($workFlowDetails->avoid_delete)) {
            $workFlowActions['avoid_delete'] = $workFlowDetails->avoid_delete;
        }
        return static::$_workFlowModel->update(
            array(
                "workflow_name"=> $workFlowDetails->title,
                "workflow_type"=> $workFlowDetails->action_run === 'delete'? 'delete' : $workFlowDetails->action_type,
                "workflow_run"=> $workFlowDetails->action_run,
                "workflow_behaviour" => $workFlowDetails->action_behaviour,
                "workflow_condition"=> json_encode($workFlowDetails->logics),
                "workflow_action"=> json_encode($workFlowActions),
                "user_id"=> $this->_user_details['id'],
                "user_ip"=> $this->_user_details['ip'],
                "user_location"=> '',
                "user_device"=> $this->_user_details['device'],
                "updated_at" => $this->_user_details['time']
            ),
            array(
            "id" => $workFlowID,
            'form_id' => static::$_formID,
            )
        );
    }

    public function duplicateWorkFlow($currentFormID)
    {
        $workFlowCols = [
            "workflow_name","workflow_type","workflow_run","workflow_behaviour" ,
            "workflow_condition","workflow_action","form_id","user_id",
            "user_ip","user_location","user_device","created_at","updated_at"
        ];
        $dupData =  array(
            "workflow_name",
            "workflow_type",
            "workflow_run",
            "workflow_behaviour",
            "workflow_condition",
            "workflow_action",
            static::$_formID,
             $this->_user_details['id'],
             $this->_user_details['ip'],
             '',
             $this->_user_details['device'],
             $this->_user_details['time'],
             $this->_user_details['time']
        );
        return static::$_workFlowModel->duplicate($workFlowCols, $dupData, ['form_id' => $currentFormID]);
    }

    public function deleteworkFlow($workFlowID)
    {
        return static::$_workFlowModel->delete(
            array(
                'id' => $workFlowID,
                'form_id' => static::$_formID,
                )
        );
    }

    public function maybeGetSuccessActionID($actionIntegrationID, $actionIntegrationDetails, $actionType)
    {
        if (isset($actionIntegrationID->index)) {
            return $actionIntegrationDetails[$actionType][$actionIntegrationID->index];
        } elseif (isset($actionIntegrationID->id) && isset($actionIntegrationDetails[$actionType][json_encode($actionIntegrationID)])) {
            return $actionIntegrationDetails[$actionType][json_encode($actionIntegrationID)];
        }
        return false;
    }
}
