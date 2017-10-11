<?php namespace Gonpre\Docx;

class Info
{
	private static $id;

	public static function setDocumentId($id) {
		self::$id = $id;
	}

	public static function getDocumentId() {
		return self::$id;
	}
}