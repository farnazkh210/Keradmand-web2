<?php

namespace BitCode\BitFormPro\Core\Database;

use BitCode\BitForm\Core\Database\Model;
use BitCode\BitForm\Core\Util\IpTool;

class EntryRelatedInfoModel extends Model
{
  protected static $table = 'bitforms_form_entry_relatedinfo';

  public function getAllNotes($formID, $entryID)
  {
    $result =  $this->get(
      array(
        'id',
        'info_details',
        'created_at',
        'updated_at'
      ),
      array(
        'info_type' => 'note',
        'form_id' => $formID,
        'entry_id' => $entryID,
        'status' => 1
      ),
      null,
      null,
      'id',
      'DESC'
    );
    $response  = ['success'=>true,'data'=>[]];
      if(isset($result->errors['result_empty'])){
        wp_send_json($response);
      }
      return $result;
  }


  public function insertNote($formID, $entryID, $note_details)
  {
    $ipTool = new IpTool();
    $user_details = $ipTool->getUserDetail();
    $result = $this->insert(array(
      'info_type' => 'note',
      'info_details' => $note_details,
      'form_id' => $formID,
      'entry_id' => $entryID,
      'user_id' => $user_details['id'],
      'user_ip' => $user_details['ip'],
      'created_at' => $user_details['time']
    ));

    if ($result) {
      $this->app_db->insert(
        "{$this->app_db->prefix}bitforms_form_entry_log",
        array(
          'log_type' => 'note',
          'action_type' => 'create',
          'content' => $note_details,
          'form_id' => $formID,
          'form_entry_id' => $entryID,
          'user_id' => $user_details['id'],
          'ip' => $user_details['ip'],
          'created_at' => $user_details['time']
        )
      );
    }
    return $result;
  }

  public function updateNote($noteID, $formID, $entryID, $note_details)
  {
    $ipTool = new IpTool();
    $user_details = $ipTool->getUserDetail();
    $old_node = $this->get('info_details', ['id' => $noteID]);
    $result = $this->update(
      array(
        'info_details' => $note_details,
        'updated_at' => $user_details['time']
      ),
      array(
        'id' => $noteID
      )
    );
    $log_nodedetails = $note_details;
    if (count($old_node) > 0) {
      $old_node = $old_node[0];
      $log_nodedetails = $old_node->info_details == $note_details ? null : $note_details;
    }
    if ($result) {
      $this->app_db->insert(
        "{$this->app_db->prefix}bitforms_form_entry_log",
        array(
          'log_type' => 'note',
          'action_type' => 'update',
          'content' => $log_nodedetails,
          'form_id' => $formID,
          'form_entry_id' => $entryID,
          'user_id' => $user_details['id'],
          'ip' => $user_details['ip'],
          'created_at' => $user_details['time']
        )
      );
    }
    return $result;
  }

  public function deleteNote($noteID, $formID, $entryID)
  {
    $ipTool = new IpTool();
    $user_details = $ipTool->getUserDetail();
    $old_node = $this->get('info_details', ['id' => $noteID])[0];
    $result = $this->delete(
      array(
        'id' => $noteID
      )
    );
    if ($result) {
      $this->app_db->insert(
        "{$this->app_db->prefix}bitforms_form_entry_log",
        array(
          'log_type' => 'note',
          'action_type' => 'delete',
          'content' => $old_node->info_details,
          'form_id' => $formID,
          'form_entry_id' => $entryID,
          'user_id' => $user_details['id'],
          'ip' => $user_details['ip'],
          'created_at' => $user_details['time']
        )
      );
    }
    return $result;
  }
}
