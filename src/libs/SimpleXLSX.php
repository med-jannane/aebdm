<?php

class SimpleXLSX {
    private $workbook = [];
    private $rows = [];
    private $strings = [];
    
    public static function parse($filename) {
        $xlsx = new self();
        if ($xlsx->load($filename)) {
            return $xlsx;
        }
        return false;
    }

    public function rows() {
        return $this->rows;
    }

    private function load($filename) {
        $zip = new ZipArchive;
        if ($zip->open($filename) === TRUE) {
            
            // 1. Read Shared Strings
            $sharedStringsXML = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedStringsXML) {
                $xml = simplexml_load_string($sharedStringsXML);
                foreach ($xml->si as $val) {
                    $this->strings[] = (string)$val->t;
                }
            }

            // 2. Read Sheet 1
            $sheetXML = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXML) {
                $xml = simplexml_load_string($sheetXML);
                foreach ($xml->sheetData->row as $row) {
                    $r = [];
                    foreach ($row->c as $cell) {
                        $v = (string)$cell->v;
                        $t = (string)$cell['t'];
                        
                        if ($t == 's') {
                            $r[] = isset($this->strings[$v]) ? $this->strings[$v] : $v;
                        } else {
                            $r[] = $v;
                        }
                    }
                    $this->rows[] = $r;
                }
            }
            
            $zip->close();
            return true;
        }
        return false;
    }
}
?>
