<?php namespace Gonpre\Docx\Parser\Element;

use Gonpre\Docx\Units as DocxUnits;
use Gonpre\Docx\Styles as DocxStyles;
use Gonpre\Docx\Parser\Element\Paragraph as ParagraphParser;

class Table {
    protected $element   = [];
    protected $namespace = '';
    protected $styles    = [];
    protected $numbering = [];
    protected $relations = [];

    public function __construct($element, $namespace, $styles, $numbering, $relations) {
        $this->element   = $element;
        $this->namespace = $namespace;
        $this->styles    = $styles;
        $this->numbering = $numbering;
        $this->relations = $relations;
    }

    public function parse() {
        $paragraph = [
            'class'        => '',
            'styles'       => '',
            'cells'        => [],
            'text'         => '',
            'type'         => 'table',
            'content_type' => '',
        ];

        $currentRow      = 0;
        $lastRowRestart  = 0;
        $currentCell     = 0;
        $className       = '';
        $skipRows        = [];
        $paragraphStyles = [];
        $elementChilds   = $this->element->children($this->namespace);
        $paragraphStyles = DocxStyles::getClassData($elementChilds, $this->namespace);

        $paragraph['styles'] = implode('; ', $paragraphStyles);

        foreach ($elementChilds as $propertyName => $propertyData) {
            if ($propertyName == 'tblPr') {
                if ($propertyData->pStyle) {
                    $styleAttrs = $propertyData->pStyle->attributes('w', true);

                    if (isset($this->styles[(String) $styleAttrs['val']])) {
                        // Get the class name for the paragraph
                        $paragraph['class'] = (String) $styleAttrs['val'];
                    }
                }
            }

            if ($propertyName == 'tr') {
                $trElements = $propertyData->children($this->namespace);

                foreach ($trElements as $trPropertyName => $trPropertyData) {

                    if ('tc' == $trPropertyName) {
                        $tcElements = $trPropertyData->children($this->namespace);
                        $tcStyles   = implode('; ', DocxStyles::getClassData($tcElements, $this->namespace));

                        $paragraph['cells'][$currentRow][$currentCell] = [
                            'styles'     => $tcStyles,
                            'texts'      => [],
                            'attributes' => [],
                        ];

                        foreach ($tcElements as $tcPropertyName => $tcPropertyData) {
                            if ('tcPr' == $tcPropertyName) {
                                $tcPrElements = $tcPropertyData->children($this->namespace);

                                if (isset($tcPrElements->gridSpan) && isset($tcPrElements->gridSpan->attributes('w', true)['val'])) {
                                    $paragraph['cells'][$currentRow][$currentCell]['attributes']['colspan'] = (string) $tcPrElements->gridSpan->attributes('w', true)['val'];
                                }

                                if (isset($tcPrElements->vMerge)) {
                                    if ($tcPrElements->vMerge->attributes('w', true)['val'] == 'restart') {
                                        $paragraph['cells'][$currentRow][$currentCell]['attributes']['rowspan'] = 1;
                                        $lastRowSpan = [
                                            'row'  =>$currentRow,
                                            'cell' => $currentCell,
                                        ];
                                    } else if (empty($tcPrElements->vMerge->attributes('w', true)['val']) || $tcPrElements->vMerge->attributes('w', true)['val'] == 'continue') {
                                        $paragraph['cells'][$lastRowSpan['row']][$lastRowSpan['cell']]['attributes']['rowspan']++;
                                        $skipRows[] = [
                                            'row'  =>$currentRow,
                                            'cell' => $currentCell,
                                        ];
                                    }
                                }
                            }

                            if ('p' == $tcPropertyName) {
                                $paragraphRender = new ParagraphParser($tcPropertyData, $this->namespace, $this->styles, $this->numbering, $this->relations);

                                $currentParagraph = $paragraphRender->parse();
                                $paragraph['cells'][$currentRow][$currentCell]['texts'][] = $currentParagraph;
                                $paragraph['text'] .= $currentParagraph['text'];
                            }
                        }

                        $currentCell++;
                    }
                }

                $currentRow++;
                $currentCell = 0;
            }
        }

        foreach ($skipRows as $skipRow) {
            if (isset($paragraph['cells'][$skipRow['row']][$skipRow['cell']])) {
                unset($paragraph['cells'][$skipRow['row']][$skipRow['cell']]);
            }
        }

        return $paragraph;
    }
}