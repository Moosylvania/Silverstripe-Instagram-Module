<?php

class SocialPost extends DataObject {

	private static $db = array(
		"PostID" => "Varchar(255)",
		"Type" => "Enum('Twitter,Facebook,Instagram,Blog')",
		"Posted" => "Int"
	);

	private static $default_sort = "Posted DESC";

	public function PostedDate() {
		$date = date('m.j.y', $this->Posted);
		return $date;
	}

	public function BlogEntry() {
		if($this->Type == 'Blog') {
			return BlogEntry::get()->filter(array('ID' => $this->PostID))->First();
		}
		return false;
	}
	
}