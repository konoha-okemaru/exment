<?php

namespace Exceedone\Exment\Services\DataImportExport\Providers\Export;

use Illuminate\Support\Collection;
use Exceedone\Exment\Enums\ConditionType;
use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\File as ExmentFile;

class DefaultTableProvider extends ProviderBase
{
    protected $grid;
    protected $parent_table;

    public function __construct($args = [])
    {
        parent::__construct();
        $this->custom_table = array_get($args, 'custom_table');

        $this->grid = array_get($args, 'grid');
        $this->parent_table = array_get($args, 'parent_table');
    }

    /**
     * get data name
     */
    public function name()
    {
        return $this->custom_table->table_name;
    }

    /**
     * get data
     */
    public function data()
    {
        // get header info
        $columnDefines = $this->getColumnDefines();
        // get header and body
        $headers = $this->getHeaders($columnDefines);

        // if only template, output only headers
        if ($this->template) {
            $bodies = [];
        } else {
            $bodies = $this->getBodies($this->getRecords(), $columnDefines);
        }
        // get output items
        $outputs = array_merge($headers, $bodies);

        return $outputs;
    }

    /**
     * get column info
     * @return mixed list. first:fixed column id, suuid, parent_id, parent_type. second: custom columns: third: created_at, updated_at, deleted_at
     */
    protected function getColumnDefines()
    {
        $firstColumns = ['id','suuid','parent_id','parent_type'];
        $lastColumns = ['created_at','updated_at','deleted_at'];

        // get custom columns
        $custom_columns = $this->custom_table->custom_columns_cache;
        return [$firstColumns, $custom_columns, $lastColumns];
    }

    /**
     * get export headers
     * contains custom column name, column view name
     */
    protected function getHeaders($columnDefines)
    {
        // create 2 rows.
        $rows = [];

        list($firstColumns, $custom_columns, $lastColumns) = $columnDefines;
        
        // 1st row, column name
        $rows[] = array_merge(
            $firstColumns,
            collect($custom_columns)->map(function ($value) {
                return "value.".array_get($value, 'column_name');
            })->toArray(),
            $lastColumns
        );

        // 2st row, column view name
        $rows[] = array_merge(
            collect($firstColumns)->map(function ($value) {
                return exmtrans("common.$value");
            })->toArray(),
            collect($custom_columns)->map(function ($value) {
                return array_get($value, 'column_view_name');
            })->toArray(),
            collect($lastColumns)->map(function ($value) {
                return exmtrans("common.$value");
            })->toArray()
        );
        return $rows;
    }

    /**
     * get target chunk records
     */
    public function getRecords()
    {
        $this->grid->applyQuickSearch();
        $this->grid->getFilter()->chunk(function ($data) use (&$records) {
            if (!isset($records)) {
                $records = new Collection;
            }
            $records = $records->merge($data);
        }) ?? [];

        if (isset($this->parent_table) && $records->count() > 0) {
            return getModelName($this->name())
                ::whereIn('parent_id', $records->pluck('id'))
                ->where('parent_type', $this->parent_table)
                ->get();
        }
        return $records;
    }

    /**
     * get export bodies
     */
    protected function getBodies($records, $columnDefines)
    {
        if (!isset($records)) {
            return [];
        }
        
        $bodies = [];

        list($firstColumns, $custom_columns, $lastColumns) = $columnDefines;
        foreach ($records as $record) {
            $body_items = [];
            // add items
            $body_items = array_merge($body_items, $this->getBodyItems($record, $firstColumns));
            $body_items = array_merge($body_items, $this->getBodyItems($record, $custom_columns, "value.", ConditionType::COLUMN));
            $body_items = array_merge($body_items, $this->getBodyItems($record, $lastColumns));

            $bodies[] = $body_items;
        }

        return $bodies;
    }

    /**
     * get export body items
     */
    protected function getBodyItems($record, $columns, $array_header_key = null, $view_column_type = ConditionType::SYSTEM)
    {
        $body_items = [];
        foreach ($columns as $column) {
            // get key.
            if($column instanceof CustomColumn){
                $value = $record->pureValue($column);
            }
            else{
                if (is_array($column)) {
                    $key = (isset($array_header_key) ? $array_header_key : "").array_get($column, 'column_name');
                } else {
                    $key = (isset($array_header_key) ? $array_header_key : "").$column;
                }
                
                $value = array_get($record, $key);
            }

            if (is_array($value)) {
                $value = implode(",", $value);
            }

            // if $view_column_type is column, get customcolumn
            if ($view_column_type == ConditionType::COLUMN) {
                // if attachment, set url
                if ($column->isAttachment()) {
                    $value = ExmentFile::getUrl($value);
                }

                // if select table and has select_export_column_id, change value
                elseif ($column->isSelectTable()) {
                    $value = $this->getSelectTableExportValue($column, $value);
                }
            }

            // export parent id
            elseif ($view_column_type == ConditionType::SYSTEM && $column == 'parent_id') {
                $value = $this->getParentExportValue($value, array_get($record, 'parent_type'));
            }

            $body_items[] = $value;
        }
        return $body_items;
    }

    /**
     * Get select target value. Convert to export_column_id
     *
     * @param CustomColumn $column
     * @param string|int|null $value export id
     * @return mixed
     */
    protected function getSelectTableExportValue($column, $value)
    {
        if (is_nullorempty($value)) {
            return $value;
        }
        
        $select_export_column_id = array_get($column, 'options.select_export_column_id');
        if (is_nullorempty($select_export_column_id)) {
            return $value;
        }
        
        $select_target_table = $column->select_target_table;
        if (is_nullorempty($select_target_table)) {
            return $value;
        }

        $select_custom_value = $select_target_table->getValueModel($value);
        if (is_nullorempty($select_custom_value)) {
            return $value;
        }

        return $select_custom_value->getValue($select_export_column_id, true);
    }
    
    /**
     * Get parent target value. Convert to export_column_id
     *
     * @param CustomColumn $column
     * @param string|int|null $value export id
     * @return mixed
     */
    protected function getParentExportValue($value, $parent_table)
    {
        if (is_nullorempty($value) || is_nullorempty($parent_table)) {
            return $value;
        }
        
        // get relation
        $relation = CustomRelation::getRelationByParentChild($parent_table, $this->custom_table);
        if (!isset($relation)) {
            return $value;
        }

        $parent_export_column_id = array_get($relation, 'options.parent_export_column_id');
        if (is_nullorempty($parent_export_column_id)) {
            return $value;
        }
        
        $parent_table = CustomTable::getEloquent($parent_table);
        if (is_nullorempty($parent_table)) {
            return $value;
        }

        $parent_custom_value = $parent_table->getValueModel($value);
        if (is_nullorempty($parent_custom_value)) {
            return $value;
        }

        return $parent_custom_value->getValue($parent_export_column_id, true);
    }
}
