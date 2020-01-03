<?php namespace Gonpre\Docx;

use Gonpre\Docx\Units as DocxUnits;
/**
* Docx Styles Transformer
*/
class Styles
{
    public static function getBasedOn($style, $namespace) {
        $basedOn = false;

        if ($style->basedOn) {
            $basedOnAttr = $style->basedOn->attributes('w', true);

            if (!empty($basedOnAttr['val'])) {
                $basedOn = (string) $basedOnAttr['val'];
            }
        }

        return $basedOn;
    }

    public static function getClassData($style, $namespace) {
        $classData = [];

        if ($style->rPr) {
            foreach ($style->rPr->children($namespace) as $tagName => $styleData) {
                $tagAttrs = $styleData->attributes('w', true);
                $currentClassData = self::getTagData($tagName, $styleData, $tagAttrs, $namespace);
                if (!empty($currentClassData)) {
                    $classData = array_merge($classData, $currentClassData);
                }
            }
        }

        if ($style->pPr) {
            foreach ($style->pPr->children($namespace) as $tagName => $styleData) {
                $tagAttrs         = $styleData->attributes('w', true);
                $currentClassData = self::getTagData($tagName, $styleData, $tagAttrs, $namespace);

                if (!empty($currentClassData)) {
                    $classData = array_merge($classData, $currentClassData);
                }
            }
        }

        if ($style->tblPr) {
            foreach ($style->tblPr->children($namespace) as $tagName => $styleData) {
                $tagAttrs         = $styleData->attributes('w', true);
                $currentClassData = self::getTagData($tagName, $styleData, $tagAttrs, $namespace);

                if (!empty($currentClassData)) {
                    $classData = array_merge($classData, $currentClassData);
                }
            }
        }

        if ($style->tcPr) {
            foreach ($style->tcPr->children($namespace) as $tagName => $styleData) {
                $tagAttrs         = $styleData->attributes('w', true);
                $currentClassData = self::getTagData($tagName, $styleData, $tagAttrs, $namespace);

                if (!empty($currentClassData)) {
                    $classData = array_merge($classData, $currentClassData);
                }
            }
        }

        return $classData;
    }

    public static function getTagData($tagName, $styleData, $tagAttrs, $namespace) {
        $classData = [];

        switch ($tagName) {
            case "tblW": // Table width
            case "tcW": // Table Cell width
                if ($tagAttrs['w'] && isset($tagAttrs['type'])) {
                    switch ($tagAttrs['type']) {
                        case 'auto':
                            $classData[] = 'width: auto';
                            break;
                        case 'dxa':
                            $classData[] = 'width: ' . DocxUnits::TwipToPixel($tagAttrs['w']) . 'px';
                            break;
                        case 'pct':
                        default:
                            $classData[] = 'width: ' . DocxUnits::pctToPercentage($tagAttrs['w']) . '%';
                            break;
                    }
                }
                break;
            case "tblInd": // Table indentation
                if ($tagAttrs['w']) {
                    $classData[] = 'margin-left: ' . DocxUnits::TwipToPixel($tagAttrs['w']) . 'px';
                }
                break;
            case "tblCellMar": // Table margin
                $tagElements = $styleData->children($namespace);

                foreach ($tagElements as $elementName => $elementProperties) {
                    if (in_array($elementName, ['top','bottom','right','left'])) {
                        $paddingAttrs = $elementProperties->attributes('w', true);

                        if ($paddingAttrs['w']) {
                            $classData[] = 'padding-' . $elementName . ': ' . DocxUnits::TwipToPixel($paddingAttrs['w']) . 'px';
                        }
                    }
                }
                break;
            case "ind": // Indentation
                if (isset($tagAttrs['left'])) {
                    $classData[] = 'padding-left: ' . DocxUnits::TwipToPixel($tagAttrs['left']) . 'px';
                }

                if (isset($tagAttrs['right'])) {
                    $classData[] = 'padding-right: ' . DocxUnits::TwipToPixel($tagAttrs['right']) . 'px';
                }

                if (isset($tagAttrs['hanging']) && (isset($tagAttrs['left']) && $tagAttrs['left'] != 0)) {
                    $indent = DocxUnits::TwipToPixel($tagAttrs['hanging']) * -1;

                    $classData[] = 'text-indent: ' . $indent . 'px';
                } else if (isset($tagAttrs['firstLine'])) {
                    $indent = DocxUnits::TwipToPixel($tagAttrs['firstLine']);

                    $classData[] = 'text-indent: ' . $indent . 'px';
                }
                break;
            case "jc": // Text aligment
                $classData[] = 'text-align: ' . (($tagAttrs['val'] == 'both') ? 'justify' : $tagAttrs['val']);
                break;
            case "b": // Bold
                $classData[] = 'font-weight: bold';
                break;
            case "smallCaps": // SmallCaps
                $classData[] = 'font-variant: small-caps';
                break;
            case "i": // Italic
                $classData[] = 'font-style: italic';
                break;
            case "color":
                if ('auto' != $tagAttrs['val']) {
                    $classData[] = 'color: #' . $tagAttrs['val'];
                }
                break;
            case "sz": // Font Size
                $classData[] = 'font-size: ' . ($tagAttrs['val'] - 5) . 'px';
                $classData[] = 'line-height: ' . ($tagAttrs['val'] - 3) . 'px';
                break;
            case "shd": // Background Color
                if ('auto' != $tagAttrs['fill']) {
                    $classData[] = 'background-color: #' . $tagAttrs['fill'];
                }
                break;
            case "pBdr": // Border
            case "tblBorders": // Table Border
            case "tcBorders": // Table Cell Border
                $tagElements = $styleData->children($namespace);

                foreach ($tagElements as $elementName => $elementProperties) {
                    if (in_array($elementName, ['top','bottom','right','left'])) {
                        $borderAttrs    = $elementProperties->attributes('w', true);
                        $borderProperty = 'border-' . $elementName;
                        $size           = '1';
                        $color          = '000000';
                        $type           = 'solid';

                        if (isset($borderAttrs['sz'])) {
                            $size = DocxUnits::pointToPixel($borderAttrs['sz'] / 8);
                        }

                        if (isset($borderAttrs['val']) && in_array($borderAttrs['val'], ['dashed', 'dotted', 'double'])) {
                            $type = $borderAttrs['val'];
                        }

                        if (isset($borderAttrs['color']) && $borderAttrs['color'] != 'auto') {
                            $color = $borderAttrs['color'];
                        }

                        $classData[] = sprintf('%s: %spx %s #%s', $borderProperty, $size, $type, $color);
                    }
                }
                break;
            case "spacing":
                if (isset($tagAttrs['after'])) {
                    $classData[] = 'padding-bottom: ' . (DocxUnits::TwipToPixel($tagAttrs['after']) + 3) . 'px';
                }

                if (isset($tagAttrs['before'])) {
                    $classData[] = 'padding-top: ' . (DocxUnits::TwipToPixel($tagAttrs['before']) + 3) . 'px';
                }

                if (isset($tagAttrs['line'])) {
                    $classData[] = 'line-height: ' . (DocxUnits::TwipToPixel($tagAttrs['line']) + 3) . 'px';
                }

                break;
        }

        return $classData;
    }

    public static function getClasses($styles, $namespace) {
        $styleClasses = [];

        foreach ($styles as $styleData) {
            $styleAttrs = $styleData->attributes('w', true);

            if ($styleAttrs['styleId'] && (isset($styleAttrs['type']) && $styleAttrs['type'] == 'paragraph')) {
                $basedOn   = self::getBasedOn($styleData, $namespace);
                $classData = self::getClassData($styleData, $namespace);

                if ($basedOn !== false && !empty($styleClasses[$basedOn])) {
                    $tmpBasedOn   = [];
                    $tmpFinalData = [];

                    foreach ($styleClasses[$basedOn] as $value) {
                        list($k, $v) = explode(': ', $value);
                        $tmpBasedOn[$k] = $v;
                    }

                    foreach ($classData as $value) {
                        list($k, $v) = explode(': ', $value);
                        $tmpBasedOn[$k] = $v;
                    }

                    foreach ($tmpBasedOn as $key => $value) {
                        $tmpFinalData[] = $key . ': ' . $value;
                    }

                    $classData = $tmpFinalData;
                }

                if (!empty($classData)) {
                    $styleClasses[(String)$styleAttrs['styleId']] = $classData;
                }
            }
        }

        return $styleClasses;
    }
}