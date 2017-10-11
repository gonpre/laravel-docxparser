<?php namespace Gonpre\Docx;

class Listing
{
    private static $data         = [];
    private static $resetAttempt = false;

    public static function setCounter($numberingId, $levelId, $index = 1) {
        if (empty(self::$data[$numberingId][$levelId])) {
            self::$data[$numberingId][$levelId] = $index;
        } else {
            self::$data[$numberingId][$levelId]++;
        }
    }

    public static function reset() {
        if (self::$resetAttempt) {
            self::$data         = [];
            self::$resetAttempt = false;
        } else {
            self::$resetAttempt = true;
        }
    }

    public static function getCounter($numberingId, $levelId) {
        return !empty(self::$data[$numberingId][$levelId]) ? self::$data[$numberingId][$levelId] : false;
    }
}