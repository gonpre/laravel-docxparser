<?php namespace Gonpre\Docx\Render\Element;

class Paragraph implements \Gonpre\Docx\Renderer
{
    const TAG = 'p';

    protected $data = [];

    public function __construct($data) {
        $this->data = $data;
    }

    public function render() {
        $class  = '';
        $styles = '';
        $html   = [];

        if (!empty($this->data['texts'])) {
            $styles = empty($this->data['styles']) ? '' : ' style="' . $this->data['styles'] . '"';
            $class  = empty($this->data['class']) ? '' : ' class="' . $this->data['class'] . '"';
        } else {
            $class = ' class="empty-paragraph"';
        }

        $html[] = sprintf('<%s%s%s>', SELF::TAG, $class, $styles);

        if (empty($this->data['texts'])) {
            $html[] = '&nbsp;';
        }

        $htmlContent = [];
        foreach ($this->data['texts'] as $currentText => $textData) {
            if ($textData['text'] == '{{simulate-tab}}') {
                $htmlContent[$currentText]   = [];
                $htmlContent[$currentText][] = '<span class="js-simulate-tab"></span>';
            } elseif ($textData['text'] == '{{simulate-newpage}}') {
                $htmlContent[$currentText]   = [];
                $htmlContent[$currentText][] = '<span class="js-simulate-newpage"></span>';
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
        $html[] = sprintf('</%s>', SELF::TAG);

        unset($htmlContent);

        return implode('', $html);
    }
}