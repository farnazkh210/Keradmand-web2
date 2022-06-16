<?php

/**
 * Provides Base Model Class
 */

namespace BitCode\BitForm\Core\Database;

/**
 * Undocumented class
 */

use BitCode\BitForm\Core\Database\Model;

class FormEntryMetaModel extends Model
{
    protected static $table = 'bitforms_form_entrymeta';


    public function duplicateEntryMeta($data)
    {
        $values[] = $data['duplicateID'];
        $values[] = $data['entryID'];
        $sql = "INSERT INTO $this->table_name (bitforms_form_entry_id,meta_key,meta_value)"
            . " SELECT %d as bitforms_form_entry_id,meta_key,meta_value"
            . " FROM `$this->table_name` WHERE bitforms_form_entry_id = %d";
        return $this->execute($sql, $values)->getResult();
    }

    public function update(array $data, array $condition)
    {
        $entryID = $condition['bitforms_form_entry_id'];
        if (empty($entryID)) {
            return false;
        }
        $formEntryMeta = $this->get(
            array(
                'meta_key',
                'meta_value'
            ),
            array(
                'bitforms_form_entry_id' => $entryID
            )
        );
        $oldEntries = array();
        foreach ($formEntryMeta as $oldKey => $oldValue) {
            $oldEntries[$oldValue->meta_key] = $oldValue->meta_value;
        }
        $updatedData = array();
        $oldEntriesKey = array_keys($oldEntries);
        foreach ($data as $upKey => $upValue) {
            $updatedData[$upKey] = is_string($upValue) ?
                $upValue :
                wp_json_encode($upValue);
            if (!\in_array($upKey, $oldEntriesKey)) {
                $this->insert(
                    array(
                        'bitforms_form_entry_id' => $entryID,
                        'meta_key' => $upKey,
                        'meta_value' => $updatedData[$upKey]
                    )
                );
                unset($data[$upKey]);
            }
        }
        if (empty($data)) {
            return $updatedData;
        }
        $checkCondition = $this->checkCondition($condition);
        if (is_wp_error($checkCondition)) {
            return $checkCondition;
        }
        $case_part = ' ';
        $all_values = array();
        $condition['meta_key'] = array_keys($data);
        foreach ($data as $key => $value) {
            $value = is_string($value) ? $value : wp_json_encode($value);
            $case_part .= "
            WHEN '$key' THEN " . $this->getFieldFormat($value);
            $all_values[] = $value;
        }
        $formattedCondition = $this->getFormatedCondition($condition);
        if ($formattedCondition) {
            $condition_to_check = $formattedCondition['conditions'];
            $all_values = array_merge($all_values, $formattedCondition['values']);
        } else {
            $condition_to_check = null;
        }

        $sql = "UPDATE $this->table_name
         SET meta_value = ( CASE meta_key
                $case_part
            END
            )";
        $sql .= " " . $condition_to_check;
        $status =  $this->execute($sql, $all_values)->getResult();
        if (is_wp_error($status) && $status->get_error_code() !== 'result_empty') {
            return $status;
        }
        return $updatedData;
    }

    public function getEntryMeta($formFields, $entries, $limit = null, $offset = null, $filter = null, $sortBy = null)
    {
        $entry_table = $this->app_db->prefix . 'bitforms_form_entries';
        $selectedMeta  = "`bitforms_form_entry_id` as entry_id,";
        $selectedMeta  .= "e.user_id as '__user_id',";
        $selectedMeta  .= "e.user_ip as '__user_ip',";
        // $selectedMeta  .= "e.status as '__entry_status',";
        $selectedMeta  .= "e.user_location as '__user_location',";
        $selectedMeta  .= "e.user_device as '__user_device',";
        $selectedMeta  .= "e.referer as '__referer',";
        $selectedMeta  .= "e.created_at as '__created_at',";
        $selectedMeta  .= "e.updated_at as '__updated_at'";

        $all_values = array();
        $metaChecker = 0;
        $fieldCount = count($formFields);
        $globalFilterString = '';
        $globalFilterValues = array();
        $formFieldsNames = array();

        if($fieldCount > 0){
            $selectedMeta .= ",";
         }

        foreach ($formFields as $fieldKey => $fieldDetails) {
            $fieldFormat = $this->getFieldFormat($fieldDetails['key']);
            $selectedMeta .= "GROUP_CONCAT(
                CASE
                  `meta_key`
                  WHEN '$fieldFormat' THEN `meta_value`
                END
              ) AS '$fieldFormat'";
            $metaChecker += 1;
            $all_values[] = $fieldDetails['key'];
            $all_values[] = $fieldDetails['key'];
            $formFieldsNames[] = $fieldDetails['key'];
            if ($metaChecker < $fieldCount) {
                $selectedMeta .= ",";
            }

            if (!empty($filter['global'])) {
                $globalFilterString .= " `" . $fieldDetails['key'] . "` LIKE '%%" . $this->getFieldFormat($filter['global']) . "%%' ";
                if ($metaChecker < $fieldCount) {
                    $globalFilterString .= " OR ";
                }
                $globalFilterValues[] = $filter['global'];
            }
        }
        $entryIDs = array();
        $entryCount = count($entries);
        $paginateEntry = empty($sortBy) && empty($filter['field']) && empty($filter['global']);
        $entries = $paginateEntry ? array_slice($entries, $offset, $limit) : $entries;
        foreach ($entries as $entryKey => $entryDetail) {
            $entryIDs[] = $entryDetail->id;
        }
        if (empty($entryIDs)) {
            return array(
                'count' => 0,
                'entries' => [],
            );
        }
        $condition['bitforms_form_entry_id'] = $entryIDs;
        $formattedCondition = $this->getFormatedCondition($condition);
        if ($formattedCondition) {
            $groupedCondition = $formattedCondition['conditions'] . " GROUP BY
            `bitforms_form_entry_id` ";
            $all_values = array_merge($all_values, $formattedCondition['values']);
        } else {
            $groupedCondition = null;
        }
        $isRecount = false;
        if (!empty($filter['field'])) {
            $isRecount = true;
            $filterFieldCount = count($filter['field']);
            $filterFieldChecker = 0;
            if ($filterFieldCount > 0) {
                $groupedCondition .= " HAVING ";
            }
            foreach ($filter['field'] as $filterFieldKey => $filterFieldDetails) {
                $groupedCondition .= " `$filterFieldDetails->id` ='%%" . $this->getFieldFormat($filterFieldDetails->value) . "%%'";
                $all_values[] = $filterFieldDetails->value;
                if ($filterFieldChecker < $filterFieldCount) {
                    $groupedCondition .= " AND ";
                }
            }
        }
        if (!empty($filter['global']) && !empty($globalFilterString) && !empty($globalFilterValues)) {
            $isRecount = true;
            if (!empty($filter['field'])) {
                $groupedCondition .= " AND (" . $globalFilterString . ") ";
            } else {
                $groupedCondition .= " HAVING $globalFilterString ";
            }
            $offset = 0;
            $all_values = array_merge($all_values, $globalFilterValues);
        }
        $orderCondition = null;
        if (!empty($sortBy)) {
            $sortableFieldCount = count($sortBy);
            $sortableFieldChecker = 0;
            if ($sortableFieldCount > 0) {
                $orderCondition .= " ORDER BY ";
            }
            $orderList = '';
            foreach ($sortBy as $sortableFieldKey => $sortableFieldDetails) {
                // $orderCondition .=" ".$this->getFieldFormat($sortableFieldDetails->id);
                $sortableFieldChecker += 1;
                if (in_array($sortableFieldDetails->id, $formFieldsNames)) {
                    $orderList .= " `$sortableFieldDetails->id` ";
                    $orderFollow = $sortableFieldDetails->desc ? ' DESC ' : ' ASC ';
                    $orderList .= " " . $orderFollow;
                    if ($sortableFieldChecker < $sortableFieldCount) {
                        $orderList .= ", ";
                    }
                }
            }
            if (empty($orderList)) {
                $orderCondition = null;
            } else {
                $orderCondition .= $orderList;
            }
        }
        $orderCondition .= empty($orderCondition) ?  " ORDER BY `bitforms_form_entry_id` DESC " : ",`bitforms_form_entry_id` DESC ";
        $paginate = null;
        if (!\is_null($limit) && !$paginateEntry) {
            $limit = \intval($limit);
            $paginate .= " LIMIT $limit ";
        }
        if (!\is_null($offset) && !$paginateEntry) {
            $offset = \intval($offset);
            $paginate .= " OFFSET  $offset ";
        }
        $sql =  "SELECT $selectedMeta FROM `$this->table_name` em";
        $sql .=   " INNER JOIN $entry_table e on e.id = em.bitforms_form_entry_id ";
        $sql .=   $groupedCondition . $orderCondition . $paginate;
        // echo $sql;
        $result = $this->execute($sql, $all_values)->getResult();
        if (is_wp_error($result)) {
            return array(
                'count' => $paginateEntry ? $entryCount : 0,
                'entries' => [],
                'error' => $result->get_error_message()
            );
        }
        if ($isRecount) {
            $sql =  "SELECT count(*) as count FROM (";
            $sql .=  "SELECT $selectedMeta FROM `$this->table_name`";
            $sql .=   $groupedCondition . $orderCondition;
            $sql .=   ") as retrievedData";
            $countResult = $this->execute($sql, $all_values)->getResult();
            if (!(is_wp_error($result) && $result->get_error_code() === 'result_empty')) {
                $entryCount = $countResult[0]->count;
            }
        }
        $resultedEntries = array(
            'count' => $entryCount,
            'entries' => $result,
        );
        return $resultedEntries;
    }
    public function getExportEntry($formFields, $entries, $formId, $fieldLabels, $limit = null, $sortBy = null, $sortByField = null)
    {
        $entry_table = $this->app_db->prefix . 'bitforms_form_entries';
        $selectedEntryMeta  = "`bitforms_form_entry_id` as entry_id,";
        $selectedEntryMeta  .= "e.user_id as '__user_id',";
        // $selectedEntryMeta  .= "e.status as '__entry_status',";
        $selectedEntryMeta  .= "e.user_ip as '__user_ip',";
        $selectedEntryMeta  .= "e.user_location as '__user_location',";
        $selectedEntryMeta  .= "e.user_device as '__user_device',";
        $selectedEntryMeta  .= "e.referer as '__referer',";
        $selectedEntryMeta  .= "e.created_at as '__created_at',";
        $selectedEntryMeta  .= "e.updated_at as '__updated_at',";
        $metaChecker = 0;
        
        $entryInfo = ['__user_id','__user_ip',/* '__user_location', */'__user_device',
        '__referer','__created_at','__updated_at'];
        $all_values = array();
        if ($formFields == []) {
            $data = array(
                'count' => 0,
                'entries' => [],
            );
            wp_send_json_success($data, 200);
        }
        $fieldCount = count($formFields) - count(array_intersect($formFields, $entryInfo));
        $formFieldsNames = array();
        foreach ($formFields as $fldKey) {
            $formFieldsNames[] = $fldKey;
            if (in_array($fldKey, $entryInfo)) {
                continue;
            }
            $fieldFormat = $this->getFieldFormat($fldKey);
            $selectedEntryMeta .= "GROUP_CONCAT(
                CASE
                  `meta_key`
                  WHEN '$fieldFormat' THEN `meta_value`
                END
              ) AS '$fieldFormat'";
            $metaChecker += 1;
            $all_values[] = $fldKey;
            $all_values[] = $fldKey;
            if ($metaChecker < $fieldCount) {
                $selectedEntryMeta .= ",";
            }
        }
        $entryIDs = array();
        foreach ($entries as $entryKey => $entryDetail) {
            $entryIDs[] = $entryDetail->id;
        }
        if (empty($entryIDs)) {
            return array(
                'count' => 0,
                'entries' => [],
            );
        }
        $condition['bitforms_form_entry_id'] = $entryIDs;
        $formattedCondition = $this->getFormatedCondition($condition);
        $groupedCondition = null;
        if ($formattedCondition) {
            $groupedCondition = $formattedCondition['conditions'] . " GROUP BY
            `bitforms_form_entry_id` ";
            $all_values = array_merge($all_values, $formattedCondition['values']);
        }
        $order  = \is_null($sortBy) ?  "DESC " : "$sortBy";
        $orderField  = \is_null($sortByField) ?  "bitforms_form_entry_id" : "`$sortByField`";

        $orderCondition = "ORDER BY $orderField $order ";
        if (!\is_null($limit)) {
            $limitInt = \intval($limit);
            $limit = " LIMIT $limitInt ";
        }
        $sql =  "SELECT $selectedEntryMeta FROM `$this->table_name` em";
        $sql .=   " INNER JOIN $entry_table e on e.id = em.bitforms_form_entry_id ";
        $sql .=   $groupedCondition . $orderCondition . $limit;
            
        $result = $this->execute($sql, $all_values)->getResult();
        $allData = [];
        $entry_id = "entry_id";
        $users = get_users(['fields' => ['ID', 'display_name']]);
        $userNames = [];
        foreach ($users as $key => $value) {
            $userNames[$value->ID] = $value->display_name;
        }
        foreach ($result as $key => $value) {
            foreach ($formFieldsNames as $formFieldName) {
                $allData[$key]['entry_id'] = preg_replace('/[\]["]/i', '', $value->$entry_id);
                if ($formFieldName === '__user_id' && intval($value->$formFieldName) > 0) {
                    $allData[$key][$formFieldName] = $userNames[$value->$formFieldName];
                } elseif ($formFieldName === '__user_ip') {
                    $allData[$key][$formFieldName] = long2ip($value->$formFieldName);
                } else {
                    $allData[$key][$formFieldName] = preg_replace('/[\]["]/i', '', $value->$formFieldName);
                }
            }
        }

        if (is_wp_error($result)) {
            wp_send_json_error('Internal server error', 500);
        } else {
            foreach ($fieldLabels as $field) {
                foreach ($allData as $index => $entry) {
                    if (array_key_exists($field['key'], $entry) && $field['type'] == 'file-up') {
                        $key = $field['key'];
                        if (empty($entry[$key])) {
                            continue;
                        }
                        $_upload_dir  = BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . $formId . DIRECTORY_SEPARATOR . $entry['entry_id'];
                        if (is_array(explode(",", $entry[$key]))) {
                            $fileData = array();
                            foreach (explode(",", $entry[$key]) as $file) {
                                $path = "bitforms/bitforms-file/?formID=$formId&entryID=" . $entry['entry_id'] . "&fileID=$file";
                                if (file_exists($_upload_dir . DIRECTORY_SEPARATOR . $file)) {
                                    $fileData[] = site_url($path, null);
                                }
                            }
                            $allData[$index][$key] = implode(',', $fileData);
                        } else {
                            $path = "bitforms/bitforms-file/?formID=$formId&entryID=" . $entry['entry_id'] . "&fileID=" . $entry[$key];
                            if (file_exists($_upload_dir . DIRECTORY_SEPARATOR . $entry[$key])) {
                                $allData[$index][$key] = site_url($path, null);
                            }
                        }
                    }
                }
            }
            wp_send_json_success($allData, 200);
        }
    }
}
