<?php namespace Gonpre\Docx\Render\Element;

use Gonpre\Docx\Render\Element\Paragraph as ParagraphRender;
use Gonpre\Docx\Render\Element\Image as ImageRender;
use Gonpre\Docx\Render\Element\Table as TableRender;
use Gonpre\Docx\Render\Element\OrderedList as OrderedListRender;

class Factory
{
    public static function make($tag, $data) {
        $elementRender = false;

        switch ($tag) {
            case 'p':
                $elementRender = new ParagraphRender($data);
                break;
            case 'img':
                $elementRender = new ImageRender($data);
                break;
            case 'table':
                $elementRender = new TableRender($data);
                break;
            case 'list':
                $elementRender = new OrderedListRender($data);
                break;
        }

        return $elementRender;
    }
}