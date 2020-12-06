<?php

namespace Exceedone\Exment\Services\DataImportExport\Formats\SpOut;

use Exceedone\Exment\Services\DataImportExport\Formats;
use Exceedone\Exment\Services\DataImportExport\Formats\CsvTrait;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;

class Csv extends SpOut
{
    use CsvTrait;

    protected $accept_extension = 'csv,zip';

    
    /**
     * Get all csv's row count
     *
     * @param string|array|\Illuminate\Support\Collection $files
     * @return int
     */
    public function getRowCount($files) : int
    {
        //*Use PhpSpreadSheet*
        $phpSpreadSheet = new Formats\PhpSpreadSheet\Csv;
        return $phpSpreadSheet->getRowCount($files);
    }

    
    protected function getCsvArray($file, array $options = [])
    {
        $original_locale = setlocale(LC_CTYPE, 0);

        // set C locale
        if (0 === strpos(PHP_OS, 'WIN')) {
            setlocale(LC_CTYPE, 'C');
        }

        $reader = $this->createReader();
        $reader->setEncoding('UTF-8');
        $reader->setFieldDelimiter(",");
        $reader->open($file);

        $array = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $array = $this->getDataFromSheet($sheet, false, false, $options);
            // csv is only get first sheet.
            break;
        }

        // revert to original locale
        setlocale(LC_CTYPE, $original_locale);

        return $array;
    }

    
    /**
     * @return \Box\Spout\Writer\CSV\Writer
     */
    public function createWriter($spreadsheet)
    {
        return WriterEntityFactory::createCSVWriter();
    }

    
    /**
     * @return \Box\Spout\Reader\CSV\Reader
     */
    public function createReader()
    {
        return ReaderEntityFactory::createCSVReader();
    }
}
