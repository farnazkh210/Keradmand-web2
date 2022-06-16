<?php

namespace BitCode\BitFormPro\Core\Database;

use BitCode\BitForm\Core\Database\Model;
use BitCode\BitForm\Core\Util\IpTool;

class PaymentInfoModel extends Model
{
  protected static $table = 'bitforms_payments';
  public function paymentInsert( $formID, $transactionID, $paymentName, $paymentType,$response ){
    $ipTool = new IpTool();
    $user_details = $ipTool->getUserDetail();
    $result = $this->insert(array(
      'payment_name' => $paymentName,
      'payment_type' => $paymentType,
      'payment_response' => $response,
      'form_id' => $formID,
      'transaction_id' => $transactionID,
      'user_id' => $user_details['id'],
      'user_ip' => $user_details['ip'],
      'created_at' => $user_details['time']
    ));
    return $result;
  }

  public function paymentDetail( $formID,$transactionID ){
    $result =  $this->get(
        array(
          'id',
          'payment_name',
          'payment_type',
          'payment_response',
          'created_at'
        ),
        array(
          'form_id' => $formID,
          'transaction_id' => $transactionID,
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

}