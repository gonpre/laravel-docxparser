<?php namespace Gonpre\Docx\Render\Element;

class OrderedList implements \Gonpre\Docx\Renderer
{
    const TAG = 'ol';

    protected $data = [];

    public function __construct($data) {
        $this->data = $data;
    }

    public function render() {
        $class       = '';
        $styles      = '';
        $html        = [];
        $htmlContent = [];
        $start       = 1;

        if (!empty($this->data['texts'])) {
            $class  = empty($this->data['class']) ? '' : ' class="' . $this->data['class'] . '"';
            $styles = empty($this->data['styles']) ? '' : ' style="' . $this->data['styles'] . '"';
            $start  = empty($this->data['list_start_value']) ? '' : ' start="' . $this->data['list_start_value'] . '"';
        }

        $html[] = sprintf('<%s%s%s%s>', SELF::TAG, $class, $styles, $start);
        $html[] = '<li>';

        foreach ($this->data['texts'] as $currentText => $textData) {
            if ($textData['text'] == '{{simulate-tab}}') {
                $htmlContent[$currentText]   = [];
                $htmlContent[$currentText][] = '<span class="js-simulate-tab"></span>';
            } else {
                $styles = empty($textData['styles']) ? '' : ' style="' . $textData['styles'] . '"';

                $htmlContent[$currentText]   = [];
                $htmlContent[$currentText][] = sprintf('<%s%s>', 'span', $styles);
                $htmlContent[$currentText][] = htmlentities($textData['text']);
                $htmlContent[$currentText][] = sprintf('</%s>', 'span');
            }
        }

        foreach ($htmlContent as $currentContentIndex => $content) {
            $htmlContent[$currentContentIndex] = implode('', $content);
        }

        $html[] = implode('', $htmlContent);
        $html[] = '</li>';
        $html[] = sprintf('</%s>', SELF::TAG);

        unset($htmlContent);

        return implode('', $html);
    }
}