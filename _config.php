<?php

$dir = basename(dirname(__FILE__));
if($dir != "instagram") {
	user_error('Instagram: Directory must be "instagram" (currently "'.$dir.'")', E_USER_ERROR);
}

