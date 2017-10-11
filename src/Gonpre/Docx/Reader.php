<?php namespace Gonpre\Docx;

use Gonpre\Docx\Units as DocxUnits;
use Gonpre\Docx\Styles as DocxStyles;
use Gonpre\Docx\Render\Html as HtmlRender;
use Gonpre\Docx\Render\Element\Factory as FactoryElementRender;
use Gonpre\Docx\Parser\Element\Factory as ParseElementFactory;
use Gonpre\Docx\FileReader as DocxFileReader;

class Reader {
    const DOC_TYPE_STYLES    = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles';
    const DOC_TYPE_HEADER    = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/header';
    const DOC_TYPE_FOOTER    = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer';
    const DOC_TYPE_NUMBERING = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering';
    const DOC_TYPE_FONTTABLE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable';
    const DOC_TYPE_SETTINGS  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings';
    const DOC_TYPE_IMAGE     = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';

    private $currentParagraph = 0;
    private $docRels          = [];
    private $docProperties    = [];
    private $errors           = [];
    private $fileData         = false;
    private $header           = [];
    private $headerPath       = [];
    private $headerRels       = [];
    private $numbering        = [];
    private $paragraphs       = [];
    private $styles           = [];
    private $zipFile          = false;

    private function load($file) {
        if (file_exists($file)) {
            if (true === DocxFileReader::init($file)) {
                $this->loadDocumentRelations()
                     ->loadStyles()
                     ->loadNumbering()
                     ->loadDocument()
                     ->loadDocumentSettings()
                     ->loadHeaderRelations()
                     ->loadHeader();
            } else {
                $this->errors[] = 'Could not open file.';
            }
        } else {
            $this->errors[] = 'File does not exist.';
        }

        return $this;
    }

    private function loadDocumentSettings() {
        if ($this->fileData) {
            $xml          = simplexml_load_string($this->fileData);
            $namespaces   = $xml->getNamespaces(true);
            $bodyElements = $xml->children($namespaces['w'])->body->children($namespaces['w']);

            if ($bodyElements->sectPr) {
                $sectProperties = $bodyElements->sectPr->children($namespaces['w']);

                foreach ($sectProperties as $secPropertyName => $secProperty) {
                    if ($secPropertyName == 'headerReference') {
                        $relAttr = $secProperty->attributes($namespaces['r']);
                        $attr    = $secProperty->attributes($namespaces['w']);

                        if ((string) $attr['type'] == 'default' && isset($this->docRels[(string) $relAttr['id']])) {
                            $this->headerPath['header'] = $this->docRels[(string) $relAttr['id']]['target'];

                            if (1 === preg_match('/(header[1-9])/', $this->headerPath['header'], $headerMatch)) {
                                $this->headerPath['header_rel'] = 'word/_rels/' . $headerMatch[0] . '.xml.rels';
                            }
                        }
                    }

                    if ($secPropertyName == 'pgSz') {
                        $attr = $secProperty->attributes($namespaces['w']);

                        $this->docProperties['height'] = DocxUnits::TwipToPixel($attr['h']);
                        $this->docProperties['width'] = DocxUnits::TwipToPixel($attr['w']);
                    }

                    if ($secPropertyName == 'pgMar') {
                        $attr = $secProperty->attributes($namespaces['w']);

                        $this->docProperties['margins'] = [
                            'bottom' => DocxUnits::TwipToPixel($attr['bottom']),
                            'left'   => DocxUnits::TwipToPixel($attr['left']),
                            'right'  => DocxUnits::TwipToPixel($attr['right']),
                            'top'    => DocxUnits::TwipToPixel($attr['top']),
                        ];
                    }
                }
            }
        }

        return $this;
    }

    private function loadDocumentRelations() {
        if ($docRelsXml = DocxFileReader::getFile('word/_rels/document.xml.rels')) {
            $xml = simplexml_load_string($docRelsXml);

            foreach ($xml->children() as $element) {
                $this->docRels[(string) $element['Id']] = [
                    'type' => (string) $element['Type'],
                    'target' => 'word/' . (string) $element['Target']
                ];
            }
        }

        return $this;
    }

    private function loadHeaderRelations() {
        if ($headerRelsXml = DocxFileReader::getFile($this->headerPath['header_rel'])) {
            $xml = simplexml_load_string($headerRelsXml);

            foreach ($xml->children() as $element) {
                $this->headerRels[(string) $element['Id']] = [
                    'type' => (string) $element['Type'],
                    'target' => 'word/' . (string) $element['Target']
                ];
            }
        }

        return $this;
    }

    private function loadDocument() {
        $this->fileData = DocxFileReader::getFile('word/document.xml');

        return $this;
    }

    private function loadHeader() {
        if ($headerXml = DocxFileReader::getFile($this->headerPath['header'])) {
            $header               = [];
            $currentHeaderElement = 0;
            $xml                  = simplexml_load_string($headerXml);
            $namespaces           = $xml->getNamespaces(true);
            $headerElements       = $xml->children($namespaces['w']);

            foreach($headerElements as $element => $elementData) {
                $elementParser = ParseElementFactory::make((String) $element, $elementData, $namespaces['w'], $this->styles, $this->numbering, $this->headerRels);

                if ($elementParser) {
                    $this->header[$currentHeaderElement] = $elementParser->parse();
                    $currentHeaderElement++;
                }
            }
        }

        return $this;
    }

    private function loadStyles() {
        if ($stylesXml = DocxFileReader::getFile('word/styles.xml')) {
            $xml        = simplexml_load_string($stylesXml);
            $namespaces = $xml->getNamespaces(true);
            $children   = $xml->children($namespaces['w']);
            $styles     = DocxStyles::getClasses($children->style, $namespaces['w']);

            $this->styles = $styles;
        }

        return $this;
    }

    private function loadNumbering() {
        if ($numberingsXml = DocxFileReader::getFile('word/numbering.xml')) {
            $xml           = simplexml_load_string($numberingsXml);
            $namespaces    = $xml->getNamespaces(true);
            $childrens     = $xml->children($namespaces['w']);
            $formats       = [
                'bullet'                  => 'circle',
                'cardinalText'            => 'decimal',
                'chicago'                 => 'decimal',
                'decimal'                 => 'decimal',
                'decimalEnclosedCircle'   => 'decimal',
                'decimalEnclosedFullstop' => 'decimal',
                'decimalEnclosedParen'    => 'decimal',
                'decimalZero'             => 'decimal-leading-zero',
                'lowerLetter'             => 'lower-alpha',
                'lowerRoman'              => 'lower-roman',
                'none'                    => 'none',
                'ordinalText'             => 'decimal',
                'upperLetter'             => 'upper-alpha',
                'upperRoman'              => 'upper-roman',
            ];

            $this->numbering = [];

            foreach($childrens as $propertyName => $abstractNum) {
                if ($propertyName != 'abstractNum') continue;

                $attr   = $abstractNum->attributes('w', true);
                $levels = $abstractNum->children($namespaces['w']);
                $id     = (String) $attr['abstractNumId'];

                $this->numbering[$id] = [];

                foreach ($levels as $level) {
                    $levelAttr       = $level->attributes('w', true);
                    $levelId         = (String) $levelAttr['ilvl'];
                    $levelProperties = $level->children($namespaces['w']);

                    $this->numbering[$id][$levelId] = [
                        'numFmt' => 'decimal',
                        'start'  => '1',
                        'class'  => '',
                        'styles' => DocxStyles::getClassData($level, $namespaces['w']),
                    ];

                    foreach ($this->numbering[$id][$levelId]['styles'] as $styleIndex => $styleValue) {
                        if (strpos($styleValue, 'text-indent:') !== false) {
                            $this->numbering[$id][$levelId]['styles'][$styleIndex] = 'text-indent: 0';
                        }

                        // Remove the bold for the ol and keep only the bold for the inner texts
                        if (strpos($styleValue, 'font-weight:') !== false) {
                            unset($this->numbering[$id][$levelId]['styles'][$styleIndex]);
                        }
                    }

                    foreach ($levelProperties as $propertyName => $propertyData) {

                        if ($propertyName == 'numFmt') {
                            $propertyAttr = $propertyData->attributes('w', true);
                            $format       = isset($propertyAttr['val']) ? (string) $propertyAttr['val'] : 'decimal';

                            $this->numbering[$id][$levelId]['numFmt'] = isset($formats[$format]) ? $formats[$format] : 'decimal';
                        }

                        if ($propertyName == 'start') {
                            $propertyAttr = $propertyData->attributes('w', true);

                            if (isset($propertyAttr['val'])) {
                                $this->numbering[$id][$levelId]['start'] = (string) $propertyAttr['val'];
                            }
                        }

                        if ($propertyName == 'pStyle') {
                            $propertyAttr = $propertyData->attributes('w', true);

                            if (isset($propertyAttr['val'])) {
                                $this->numbering[$id][$levelId]['class'] = (string) $propertyAttr['val'];
                            }
                        }
                    }
                }
            }
        }

        return $this;
    }

    private function parseElements() {
        if ($this->fileData) {
            $xml          = simplexml_load_string($this->fileData);
            $namespaces   = $xml->getNamespaces(true);
            $bodyElements = $xml->children($namespaces['w'])->body->children($namespaces['w']);

            foreach($bodyElements as $element => $elementData) {
                $elementParser = ParseElementFactory::make((String) $element, $elementData, $namespaces['w'], $this->styles, $this->numbering, $this->docRels);

                if ($elementParser) {
                    $this->paragraphs[$this->currentParagraph] = $elementParser->parse();
                    $this->currentParagraph++;
                }
            }
        }
    }

    public function setFile($path) {
        $this->load($path);
        $this->parseElements();
        DocxFileReader::close();

        return true;
    }

    public function toHTML() {
        $renderer = new HtmlRender($this->paragraphs, $this->styles);

        return $renderer->render();
    }

    public function getParagraphs() {
        return $this->paragraphs;
    }

    public function getHeader() {
        return $this->header;
    }

    public function getProperties() {
        return $this->docProperties;
    }

    public function headerToHtml() {
        $headerHtml = '<div class="law-header">';

        foreach ($this->header as $currentParagraph) {
            $tag           = isset($currentParagraph['type']) ? $currentParagraph['type'] : '';
            $elementRender = FactoryElementRender::make($tag, $currentParagraph);

            if ($elementRender) {
                $headerHtml .= $elementRender->render();
            }
        }

        $headerHtml .= '</div>';

        return $headerHtml;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getStyles() {
        return $this->styles;
    }
}