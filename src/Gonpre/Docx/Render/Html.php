<?php namespace Gonpre\Docx\Render;

use Gonpre\Docx\Render\Element\Factory as FactoryElementRender;

class Html implements \Gonpre\Docx\Renderer
{
    protected $styles        = [];
    protected $defaultStyles = [];
    protected $paragraphs    = [];
    protected $html          = '';

    public function __construct(array $paragraphs, array $styles = [], array $defaultStyles = []) {
        $this->setParagraphs($paragraphs)
            ->setStyles($styles)
            ->defaultStyles($defaultStyles);
    }

    public function setParagraphs(array $paragraphs) {
        $this->paragraphs = $paragraphs;

        return $this;
    }

    public function getParagraphs() {
        return $this->paragraphs;
    }

    public function setStyles(array $styles) {
        $this->styles = $styles;

        return $this;
    }

    public function setDefaultStyles(array $defaultStyles) {
        $this->defaultStyles = $defaultStyles;

        return $this;
    }

    public function render() {
        $this->cleanUp()
            ->generateHeader()
            ->generateContent()
            ->generateFooter();

        return $this->html;
    }

    private function cleanUp() {
        $this->html = '';

        return $this;
    }

    private function generateHeader() {
        $this->html = '<style>';
        $this->html .= '.docx-content p:not(:empty),.docx-content ol{margin:0}';
        $this->html .= '.docx-header p:not(:empty){margin:0;line-height:0}';
        $this->html .= '.docx-header p:not(:empty) span{line-height:normal}';
        $this->html .= '.docx-content table,.docx-header table{border-collapse:collapse}';
        $this->html .= '.docx-header table td{vertical-align:top}';

        foreach($this->defaultStyles as $element => $defaultStyles) {
            $attrs = implode('; ', $defaultStyles);
            $this->html .= ".docx-content {$element} {{$attrs}}";
        }

        foreach($this->styles as $styleId => $style) {
            $attrs = implode('; ', $style);
            $this->html .= ".docx-content .{$styleId} {{$attrs}}";
        }

        $this->html .= '</style>';

        return $this;
    }

    private function generateContent() {
        $this->html .= '<div class="docx-content">';

        foreach ($this->paragraphs as $currentParagraph) {
            $tag           = isset($currentParagraph['type']) ? $currentParagraph['type'] : '';
            $elementRender = FactoryElementRender::make($tag, $currentParagraph);

            if ($elementRender) {
                $this->html .= $elementRender->render();
            }
        }

        $this->html .= '</div>';

        return $this;
    }

    private function generateFooter() {
        return $this;
    }
}
