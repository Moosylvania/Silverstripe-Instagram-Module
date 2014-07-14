<?php

class InstagramAdmin extends ModelAdmin {

	private static $managed_models = array(
		'InstagramSubscription',
		'InstagramPost'
	);

	private static $url_segment = 'instagram-settings';

	private static $menu_title  = 'Instagram Settings';


}