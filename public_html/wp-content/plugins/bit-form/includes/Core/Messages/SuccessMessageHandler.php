<?php

namespace BitCode\BitForm\Core\Messages;

use BitCode\BitForm\Core\Database\SuccessMessageModel;

final class SuccessMessageHandler
{
    private static $_formID;
    private static $_successMessageModel;
    private $_user_details;

    public function __construct($formID, $user_details = null)
    {
        static::$_formID = $formID;
        static::$_successMessageModel = new SuccessMessageModel();
        $this->_user_details = $user_details;
    }

    public function getAllMessage()
    {
        return static::$_successMessageModel->get(
            array(
                'id',
                'message_title',
                'message_content'
            ),
            array(
                'form_id' => static::$_formID,
            )
        );
    }

    public function getAMessage($messageID)
    {
        return static::$_successMessageModel->get(
            array(
                'id',
                'message_title',
                'message_content'
            ),
            array(
                'form_id' => static::$_formID,
                'id' => $messageID,
            )
        );
    }

    public function saveMessage($messageDetail)
    {
        return static::$_successMessageModel->insert(
            array(
            "message_title"=> $messageDetail->title,
            "message_content"=> $messageDetail->msg,
            "form_id"=> static::$_formID,
            "user_id"=> $this->_user_details['id'],
            "user_ip"=>$this->_user_details['ip'],
            "created_at"=> $this->_user_details['time'],
            "updated_at"=> $this->_user_details['time']
            )
        );
    }

    public function updateMessage($messageDetail)
    {
        return static::$_successMessageModel->update(
            array(
                "message_title"=> $messageDetail->title,
                "message_content"=> $messageDetail->msg,
                "form_id"=> static::$_formID,
                "user_id"=> $this->_user_details['id'],
                "user_ip"=>$this->_user_details['ip'],
                "updated_at"=> $this->_user_details['time']
            ),
            array(
                "id" => $messageDetail->id
                )
        );
    }

    public function duplicaleAllMessage($oldFormId)
    {
        $msgCols = ["message_title","message_content","form_id","user_id","user_ip","created_at","updated_at"];
        $msgDupData = array(
            "message_title",
            "message_content",
            static::$_formID,
            $this->_user_details['id'],
            $this->_user_details['ip'],
            $this->_user_details['time'],
            $this->_user_details['time']
        );
        return static::$_successMessageModel->duplicate($msgCols, $msgDupData, ['form_id' => $oldFormId]);
    }
    public function deleteMessage($messageID)
    {
        return static::$_successMessageModel->delete(
            array(
                'id' => $messageID,
                'form_id' => static::$_formID,
                )
        );
    }
}
