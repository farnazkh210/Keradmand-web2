<?php
/**
 * Provides Report Model Class
 */
namespace BitCode\BitForm\Core\Database;

use BitCode\BitForm\Core\Database\Model;

/**
 *
 */
class ReportsModel extends Model
{
    protected static $table = 'bitforms_reports';

    public function validateReportFields($reportData, $fieldNames)
    {
        $reportDetails = [];
        switch ($reportData->type) {
        case 'table':
            $tableAction = "table_ac";
            $reportDetails = !is_string($reportData->details)? $reportData->details : json_decode($reportData->details);
            if (!empty($reportDetails->order)) {
                $initialOrder = $fieldNames;
                $columnOrder = $reportDetails->order;
                foreach ($reportDetails->order as $key => $value) {
                    if (isset($initialOrder[$value])) {
                        unset($initialOrder[$value]);
                    } elseif (!isset($initialOrder[$value])) {
                        unset($columnOrder[$key]);
                    }
                }
                $columnOrder = array_values($columnOrder);
                if (!empty($initialOrder)) {
                    foreach ($initialOrder as $name => $label) {
                        $columnOrder[] =  $name;
                    }
                }
                $columnOrder[] = $tableAction;
                array_unshift($columnOrder, 'selection', 'sl');
                $reportDetails->order = $columnOrder;
            }
            if (!empty($reportDetails->hiddenColumns)) {
                $hiddenColumns = $reportDetails->hiddenColumns;
                foreach ($reportDetails->hiddenColumns as $key => $value) {
                    if (!isset($fieldNames[$value])  && ($value !== 'sl' && $value !== 'selection' && $value !== 'table_ac')) {
                        unset($hiddenColumns[$key]);
                    }
                }
                $reportDetails->hiddenColumns = array_values($hiddenColumns);
            }
            break;
        
        default:
            break;
        }
        return $reportDetails;
    }
}
