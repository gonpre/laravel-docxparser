<?php namespace Gonpre\Docx\Render\Element;

use Gonpre\Docx\Render\Element\Factory as FactoryRender;

class Table implements \Gonpre\Docx\Renderer
{
    const TAG = 'table';

    protected $data = [];

    public function __construct($data) {
        $this->data = $data;
    }

    public function render() {
        $currentRow  = null;
        $class       = '';
        $styles      = '';
        $html        = [];
        $htmlContent = [];

        if (!empty($this->data['cells'])) {
            $styles = empty($this->data['styles']) ? '' : ' style="' . $this->data['styles'] . '"';
            $class  = empty($this->data['class']) ? '' : ' class="' . $this->data['class'] . '"';
        }

        $html[] = sprintf('<%s%s%s>', SELF::TAG, $class, $styles);
        $html[] = '<tr>';

        if (empty($this->data['cells'])) {
            $html[] = '<td>';
            $html[] = '&nbsp;';
            $html[] = '</td>';
        }

        foreach ($this->data['cells'] as $currentRow => $rowData) {
            foreach ($rowData as $currentCell => $cellData) {
                $attrs  = '';
                $styles = empty($cellData['styles']) ? '' : ' style="' . $cellData['styles'] . '"';

                if (!empty($cellData['attributes']['rowspan'])) {
                    $attrs .= ' rowspan="'.$cellData['attributes']['rowspan'].'"';
                }

                if (!empty($cellData['attributes']['colspan'])) {
                    $attrs .= ' colspan="'.$cellData['attributes']['colspan'].'"';
                }

                $htmlContent[] = sprintf('<%s%s%s>', 'td', $styles, $attrs);
                foreach ($cellData['texts'] as $currentParagraph) {
                    $tag           = isset($currentParagraph['type']) ? $currentParagraph['type'] : '';
                    $elementRender = FactoryRender::make($tag, $currentParagraph);

                    if ($elementRender) {
                        $htmlContent[] = $elementRender->render();
                    }
                }
                $htmlContent[] = '</td>';
            }

            $htmlContent[] = '</tr>';
            $htmlContent[] = '<tr>';
        }

        $htmlContent[] = '</tr>';

        // foreach ($htmlContent as $currentContentIndex => $content) {
        //     $htmlContent = implode('', $htmlCon);
        // }

        $html[] = implode('', $htmlContent);
        $html[] = sprintf('</%s>', SELF::TAG);

        unset($htmlContent);

        return implode('', $html);
    }
}