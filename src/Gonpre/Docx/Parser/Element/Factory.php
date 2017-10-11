<?php namespace Gonpre\Docx\Parser\Element;

use Gonpre\Docx\Parser\Element\Paragraph as ParagraphParser;
use Gonpre\Docx\Parser\Element\Table as TableParser;
use Gonpre\Docx\Parser\Element\LineBreak as LineBreakParser;

class Factory
{
    public static function make($elementType, $data, $namespace, $styles, $numbering, $relations = null) {
        $elementParser = false;

        switch ($elementType) {
            case 'p':
                $elementParser = new ParagraphParser($data, $namespace, $styles, $numbering, $relations);
                break;
            case 'tbl':
                $elementParser = new TableParser($data, $namespace, $styles, $numbering, $relations);
                break;
            case 'r':
                $elementParser = new LineBreakParser($data, $namespace, $styles, $numbering, $relations);
                break;
        }

        return $elementParser;
    }
}