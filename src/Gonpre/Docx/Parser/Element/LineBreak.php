<?php namespace Gonpre\Docx\Parser\Element;

use Gonpre\Docx\Units as DocxUnits;
use Gonpre\Docx\Styles as DocxStyles;
use Gonpre\Docx\Listing as ListingFollower;

class LineBreak {
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
            'texts'        => [],
            'text'         => '',
            'type'         => 'p',
            'content_type' => '',
        ];

        $className       = '';
        $paragraphStyles = [];
        $elementChilds   = $this->element->children($this->namespace);
        $paragraphStyles = DocxStyles::getClassData($elementChilds, $this->namespace);

        foreach ($elementChilds as $propertyName => $propertyData) {
            if ($propertyData->br) {
                $brAttrs = $propertyData->br->attributes('w', true);

                if (isset($brAttrs['type']) && $brAttrs['type'] == 'page') {
                    $paragraph['texts'][] = [
                        'styles' => '',
                        'text'   => '{{simulate-newpage}}',
                    ];
                }
            }
        }

        return $paragraph;
    }
}