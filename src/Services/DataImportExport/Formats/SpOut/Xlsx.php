<?php

namespace Exceedone\Exment\Services\DataImportExport\Formats\SpOut;

use Exceedone\Exment\Services\DataImportExport\Formats;
use Exceedone\Exment\Services\DataImportExport\Formats\XlsxTrait;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
 
class Xlsx extends SpOut
{
    use XlsxTrait;
    
    protected $accept_extension = 'xlsx';
    
    /**
     * get data table list. contains self table, and relations (if contains)
     */
    public function getDataTable($request, array $options = [])
    {
        $options = $this->getDataOptions($options);
        return $this->_getData($request, function ($request) use ($options) {
            // if over row size, return number
            if (boolval($options['checkCount'])) {
                if (($count = $this->getRowCount($request)) > (config('exment.import_max_row_count', 1000) + 2)) {
                    return $count;
                }
            }

            // get all data
            $datalist = [];

            // open file
            list($path, $extension, $originalName, $file) = $this->getFileInfo($request);
            $reader = $this->createReader();
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                $sheetName = $sheet->getName();
                $datalist[$sheetName] = $this->getDataFromSheet($sheet, false, true, $options);
            }

            return $datalist;
        });
    }


    protected function _getData($request, $callback)
    {
        list($path, $extension, $originalName, $file) = $this->getFileInfo($request);
        
        // not read here. Because SpOut open is too slow
        // $reader = $this->createReader();
        // $reader->open($path);
        try {
            //return $callback($reader);
            return $callback($request);
        } finally {
        }
    }


    /**
     * Get all sheet's row count
     *
     * @return int
     */
    public function getRowCount($request) : int
    {
        //*Use PhpSpreadSheet*
        $phpSpreadSheet = new Formats\PhpSpreadSheet\Xlsx;

        list($path, $extension, $originalName, $file) = $this->getFileInfo($request);
        $reader = $phpSpreadSheet->createReader();
        $spreadsheet = $reader->load($path);
        
        return $phpSpreadSheet->getRowCount($spreadsheet);
    }

    
    /**
     * @return \Box\Spout\Writer\XLSX\Writer
     */
    public function createWriter($spreadsheet)
    {
        return WriterEntityFactory::createXLSXWriter();
    }
    
    
    /**
     * @return \Box\Spout\Reader\XLSX\Reader
     */
    public function createReader()
    {
        return ReaderEntityFactory::createXLSXReader();
    }
}
