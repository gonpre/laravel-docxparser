<?php namespace Gonpre\Docx;

use ZipArchive;

class FileReader
{
	private static $zipFile;

	public static function init($file) {
		self::$zipFile = new ZipArchive;

		return self::$zipFile->open($file);
	}

	public static function getFile($filePath) {
		$file = false;

		if (false !== ($fileIndex = self::$zipFile->locateName($filePath))) {
			$file = self::$zipFile->getFromIndex($fileIndex);
		}

		return $file;
	}

	public static function extractTo($path, $files) {
		if (self::$zipFile) {
			self::$zipFile->extractTo($path, $files);
		}
	}

	public static function close() {
		self::$zipFile->close();
	}
}