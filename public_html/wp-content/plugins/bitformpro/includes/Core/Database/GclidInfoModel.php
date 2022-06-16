<?php

namespace BitCode\BitFormPro\Core\Database;

use BitCode\BitForm\Core\Database\Model;
use BitCode\BitForm\Core\Util\IpTool;

class GclidInfoModel extends Model
{
  protected static $table = 'bitforms_gclid_response';
  public function gclidResponseInsert($gclid, $response)
  {

    $ipTool = new IpTool();
    $user_details = $ipTool->getUserDetail();
    $result = $this->insert(array(
      'gclid_id' => $gclid,
      'gclid_response' => $response,
      'created_at' => $user_details['time']
    ));
    return $result;
  }

  public function gclidDetail($gclidId)
  {

    $result =  $this->get(
      array(
        'id',
        'gclid_id',
        'gclid_response',
        'created_at'
      ),
      array(
        'gclid_id ' => $gclidId,
      ),
      null,
      null,
      'id',
      'DESC'
    );
    $response  = ['success' => true, 'data' => []];
    if (isset($result->errors['result_empty'])) {
      return [];
    }
    return $result;
  }

  public function gclidApiLog()
  {
    global $wpdb;
    $sql =  "SELECT * FROM `{$wpdb->prefix}bitforms_app_log`";
    $result =  $wpdb->get_results($sql);
    return $result;
  }

  public function todayExistsData()
  {
    global $wpdb;
    $sql =  "SELECT response_type,DATE(created_at) as date FROM `{$wpdb->prefix}bitforms_app_log` WHERE DATE(created_at)=CURDATE() AND response_type='success'";
    $result =  $wpdb->get_results($sql);
    return $result;
  }

  public function gclidApiLogInsert($data)
  {
    global $wpdb;
    $table_name = "{$wpdb->prefix}bitforms_app_log";
    $format = array('%s', '%s', '%s');
    $result = $wpdb->insert($table_name, $data, $format);
    return $result;
  }
}
