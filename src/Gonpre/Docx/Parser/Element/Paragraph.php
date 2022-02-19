<?php namespace Gonpre\Docx\Parser\Element;

use Gonpre\Docx\Units as DocxUnits;
use Gonpre\Docx\Styles as DocxStyles;
use Gonpre\Docx\Listing as ListingFollower;
use Gonpre\Docx\FileReader as DocxFileReader;
use Gonpre\Docx\Info as InfoDocx;
use \CloudConvert\Laravel\Facades\CloudConvert;
use \CloudConvert\Models\Job;
use \CloudConvert\Models\Task;

class Paragraph {
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

    private function convertImage($srcPath, $srcName, $destinationPath, $destinationName) {
        $job = (new Job())
            ->setTag("convert-{$srcName}")
            ->addTask(
                (new Task('import/upload', "upload-{$srcName}"))
            )
            ->addTask(
                (new Task('convert', "convert-{$srcName}"))
                  ->set('input', ["upload-{$srcName}"])
                  ->set('output_format', 'png')
                  ->set('filename', $destinationName)
            )
            ->addTask(
                (new Task('export/url', "export-{$srcName}"))
                  ->set('input', ["convert-{$srcName}"])
            );

        CloudConvert::jobs()->create($job);
        $uploadTask = $job->getTasks()->whereName("upload-{$srcName}")[0];
        $inputStream = fopen(Storage::path($srcPath . $srcName), 'r');
        CloudConvert::tasks()->upload($uploadTask, $inputStream);

        CloudConvert::Jobs()->wait($job);
        foreach ($job->getExportUrls() as $file) {
            $source = CloudConvert::getHttpTransport()->download($file->url)->detach();
            $dest = fopen(Storage::path($destinationPath . $destinationName), 'w');

            stream_copy_to_stream($source, $dest);
        }
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
            if ($propertyName == 'pPr') {
                if ($propertyData->pStyle) {
                    $styleAttrs = $propertyData->pStyle->attributes('w', true);

                    if (isset($this->styles[(String) $styleAttrs['val']])) {
                        // Get the class name for the paragraph
                        $paragraph['class'] = (String) $styleAttrs['val'];
                    }
                }

                // Paragraph list
                if ($propertyData->numPr) {
                    if ($propertyData->numPr->numId) {
                        $numberingIdAttrs = $propertyData->numPr->numId->attributes('w', true);

                        if ($numberingIdAttrs['val']) {
                            $numberingId = (String) $numberingIdAttrs['val'];

                            if ($propertyData->numPr->ilvl) {
                                $numberingLevelAttrs = $propertyData->numPr->ilvl->attributes('w', true);

                                if ($numberingLevelAttrs['val']) {
                                    $numberingLevelId = (String) $numberingLevelAttrs['val'];

                                    if (empty($this->numbering[$numberingId][$numberingLevelId])) {
                                        continue;
                                    }

                                    $numbering = $this->numbering[$numberingId][$numberingLevelId];

                                    if (!empty($numbering['class'])) {
                                        $paragraph['class'] = $numbering['class'];
                                    }

                                    ListingFollower::setCounter($numberingId, $numberingLevelId, $numbering['start']);

                                    $paragraph['type']             = 'list';
                                    $paragraph['list_start_value'] = ListingFollower::getCounter($numberingId, $numberingLevelId);
                                    $paragraphStyles[]             = 'list-style-type: ' . $numbering['numFmt'];

                                    $paragraphStyles = array_merge($paragraphStyles, $numbering['styles']);
                                }
                            }
                        }
                    }
                }
            }

            // Paragraph texts
            if ($propertyName == 'r') {
                foreach ($propertyData->children($this->namespace) as $propertyElementName => $propertyElementData) {
                    if ($propertyElementName == 'drawing') {
                        $inline      = $propertyElementData->children('http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
                        $shapeStyles = '';

                        if ($inline) {
                            $inlineChilds = $inline->children('http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');

                            // Get the image size from the attributes of the shape
                            // TODO: Get image size from the image
                            foreach ($inlineChilds as $inlineChildName => $inlineChildValue) {
                                if ($inlineChildName !== 'extent') continue;
                                $extentAttr  = $inlineChildValue->attributes();
                                $shapeStyles = sprintf('width:%spx;height:%spx;', DocxUnits::emuToPixel((string) $extentAttr->cx), DocxUnits::emuToPixel((string) $extentAttr->cy));
                            }

                            $graphic = $inline->children('http://schemas.openxmlformats.org/drawingml/2006/main');

                            foreach ($graphic->children('http://schemas.openxmlformats.org/drawingml/2006/main') as $graphicChildName => $graphicChildValue) {

                                if ($graphicChildName == 'graphicData') {
                                    $pic = $graphicChildValue->children('http://schemas.openxmlformats.org/drawingml/2006/picture');

                                    foreach ($pic->children('http://schemas.openxmlformats.org/drawingml/2006/picture') as $picChildName => $picChildValue) {
                                        if ($picChildName == 'blipFill') {
                                            $blip       = $picChildValue->children('http://schemas.openxmlformats.org/drawingml/2006/main');
                                            $imgAttr    = $blip->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                                            $imgSrcId   = (string) $imgAttr->embed;
                                            $imgZipPath = $this->relations[$imgSrcId]['target'];

                                            if ($imageData = DocxFileReader::getFile($imgZipPath)) {
                                                $id              = InfoDocx::getDocumentId();
                                                $imgRelativePath = sprintf('doc/%s/', $id);
                                                $imgFullPath     = sprintf('%s%s', config('docx.img_path'), $imgRelativePath);
                                                $imageInfo       = pathinfo($imgZipPath);

                                                \File::exists($imgFullPath) or \File::makeDirectory($imgFullPath, 0775, true);

                                                if (isset($imageInfo['extension'])) {
                                                    switch ($imageInfo['extension']) {
                                                        case 'jpg':
                                                        case 'jpeg':
                                                        case 'png':
                                                        case 'gif':
                                                            $tmpPath = config('docx.tmp_path') . $imgRelativePath;
                                                            $imgName = sprintf('%s.%s', $imgSrcId, $imageInfo['extension']);

                                                            if (\File::exists($imgFullPath . $imgName)) {
                                                                break;
                                                            }

                                                            \File::exists($tmpPath) or \File::makeDirectory($tmpPath, 0775, true);
                                                            DocxFileReader::extractTo($tmpPath, $imgZipPath);
                                                            \File::move($tmpPath . $imgZipPath, $imgFullPath . $imgName);
                                                            break;
                                                        case 'wmf':
                                                        case 'emf':
                                                            $tmpPath = config('docx.tmp_path') . $imgRelativePath;
                                                            $imgName = sprintf('%s.%s', $imgSrcId, 'png');

                                                            if (\File::exists($imgFullPath . $imgName)) {
                                                                break;
                                                            }

                                                            \File::exists($tmpPath) or \File::makeDirectory($tmpPath, 0775, true);
                                                            DocxFileReader::extractTo($tmpPath, $imgZipPath);
                                                            convertImage($tmpPath, $imgZipPath, $imgFullPath, $imgName);
                                                            break;
                                                    }
                                                }

                                                $paragraph['src']       = $imgRelativePath . $imgName;
                                                $paragraph['img_style'] = $shapeStyles;
                                                $paragraph['type']      = 'img';
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if ($propertyElementName == 'object') {
                        $shapes = $propertyElementData->children('urn:schemas-microsoft-com:vml');

                        foreach ($shapes as $shapeName => $shapeData) {
                            if ($shapeName != 'shape') continue;

                            $images    = $shapeData->children('urn:schemas-microsoft-com:vml');
                            $shapeAttr = $shapeData->attributes();

                            foreach ($images as $imageName => $imageData) {
                                if ($imageName != 'imagedata') continue;

                                $imgAttr    = $imageData->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                                $imgSrcId   = (string) $imgAttr->id;
                                $imgZipPath = $this->relations[$imgSrcId]['target'];

                                if ($imageData = DocxFileReader::getFile($imgZipPath)) {
                                    $id              = InfoDocx::getDocumentId();
                                    $imgRelativePath = sprintf('doc/%s/', $id);
                                    $imgFullPath     = sprintf('%s%s', config('docx.img_path'), $imgRelativePath);
                                    $imageInfo       = pathinfo($imgZipPath);

                                    \File::exists($imgFullPath) or \File::makeDirectory($imgFullPath, 0775, true);


                                    if (isset($imageInfo['extension'])) {
                                        switch ($imageInfo['extension']) {
                                            case 'jpg':
                                            case 'jpge':
                                            case 'png':
                                            case 'gif':
                                                $tmpPath = config('docx.tmp_path') . $imgRelativePath;
                                                $imgName = sprintf('%s.%s', $imgSrcId, $imageInfo['extension']);

                                                if (\File::exists($imgFullPath . $imgName)) {
                                                    break;
                                                }

                                                \File::exists($tmpPath) or \File::makeDirectory($tmpPath, 0775, true);
                                                DocxFileReader::extractTo($tmpPath, $imgZipPath);
                                                \File::move($tmpPath . $imgZipPath, $imgFullPath . $imgName);
                                                break;
                                            case 'wmf':
                                            case 'emf':
                                                $tmpPath = config('docx.tmp_path') . $imgRelativePath;
                                                $imgName = sprintf('%s.%s', $imgSrcId, 'png');

                                                if (\File::exists($imgFullPath . $imgName)) {
                                                    break;
                                                }

                                                echo $imgFullPath . $imgName, PHP_EOL;
                                                echo $tmpPath . $imgZipPath, PHP_EOL;

                                                \File::exists($tmpPath) or \File::makeDirectory($tmpPath, 0775, true);
                                                DocxFileReader::extractTo($tmpPath, $imgZipPath);
                                                convertImage($tmpPath, $imgZipPath, $imgFullPath, $imgName);
                                                break;
                                        }
                                    }

                                    $paragraph['src']       = $imgRelativePath . $imgName;
                                    $paragraph['img_style'] = (string) $shapeAttr->style;
                                    $paragraph['type']      = 'img';
                                }
                            }
                        }
                    }

                    if ($propertyElementName == 't') {
                        $textStyles = DocxStyles::getClassData($propertyData, $this->namespace);
                        $text       = (String) $propertyElementData;

                        // Validate toggle styles
                        if (!empty($paragraph['class'])) {
                            if (in_array('font-weight: bold', $textStyles) && in_array('font-weight: bold', $this->styles[$paragraph['class']])) {
                                $index = array_search('font-weight: bold', $textStyles);
                                $textStyles[$index] = 'font-weight: normal';
                            }

                            if (in_array('font-style: italic', $textStyles) && in_array('font-style: italic', $this->styles[$paragraph['class']])) {
                                $index = array_search('font-style: italic', $textStyles);
                                $textStyles[$index] = 'font-style: normal';
                            }
                        }

                        $paragraph['texts'][] = [
                            'styles' => implode('; ', $textStyles),
                            'text'   => $text,
                        ];

                        $paragraph['text'] .= $text;
                    }

                    if ($propertyElementName == 'tab') {
                        $paragraph['texts'][] = [
                            'styles' => '',
                            'text'   => '{{simulate-tab}}',
                        ];
                    }

                    if ($propertyElementName == 'br') {
                        $brAttr = $propertyElementData->attributes('w', true);

                        if (isset($brAttr['type']) && $brAttr['type'] == 'page') {
                            $paragraph['texts'][] = [
                                'styles' => '',
                                'text'   => '{{simulate-newpage}}',
                            ];
                        } else {
                            $paragraph['texts'][] = [
                                'styles' => '',
                                'text'   => '{{simulate-br}}',
                            ];
                        }
                    }
                }

                if (preg_match('/^Artículo [0-9]+/is', $paragraph['text'])) {
                    $paragraph['content_type'] = 'Artículo';
                }

                if (0 === strpos($paragraph['text'], 'TÍTULO')) {
                    $paragraph['content_type'] = 'Título';
                }

                if (0 === strpos($paragraph['text'], 'CAPÍTULO')) {
                    $paragraph['content_type'] = 'Capítulo';
                }

                if (0 === strpos($paragraph['text'], 'Sección')) {
                    $paragraph['content_type'] = 'Sección';
                }
            }
        }

        if ($paragraph['type'] !== 'list') {
            ListingFollower::reset();
        }

        $paragraph['styles'] = implode('; ', $paragraphStyles);

        return $paragraph;
    }
}
