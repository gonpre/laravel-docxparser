<?php namespace Gonpre\Docx\Render\Element;

class Image implements \Gonpre\Docx\Renderer
{
    const TAG = 'img';

    protected $data = [];

    public function __construct($data) {
        $this->data = $data;
    }

    public function render() {
        $styles = '';
        $html   = [];

        if (!empty($this->data['styles'])) {
            $html[] = sprintf('<p style="%s">', $this->data['styles']);
        }

        if ($this->data['img_style']) {
            $styles = sprintf(' style="%s"', $this->data['img_style']);
        }

        $html[] = sprintf('<%s%s src="{{image-cdn}}/%s" />', SELF::TAG, $styles, $this->data['src']);

        if (!empty($this->data['styles'])) {
            $html[] = '</p>';
        }

        return implode('', $html);
    }
}