# Silverstripe Instagram Module

Maintainer: Mitch Viner < mitch.viner (at) moosylvania (dot) com >

## Requirements

Silverstripe v 3.1+

## Introduction

Create and Manage real-time subscriptions to [Instagram's realtime API](http://instagram.com/developer/realtime/) via the SilverStripe Admin.

## Installation

Copy the instagram directory into the root of your Silverstripe installation.

## Setup

Open instagram/_config/config.yml - take note of the callbackURL already set in the configuration.

Create an [Instagram Client Application](http://instagram.com/developer/clients/manage/), set the Redirect URI to 'http://{yourdomain.com}/instagram/auth'.

Insert your [Instagram Application's](http://instagram.com/developer/clients/manage/) Client ID and Client Secret into the config.yml file.

You're now ready to create real-time subscriptions through the CMS.

### Creating Subscriptions

From the CMS create a new subscription by providing a name and a type, upon saving you can then click the 'Update Access Token' button, then 'Subscribe Real-time'.

A note on subscription types:
- User: All users that authorize the Instagram will be bucketed into a single subscription, removing one removes them all. This is how the Instagram API functions.
- Tag: Do not include the hashtag in the configuration.

### Advanced Options

To perform additional processing on each post:

Create a DataExtension in your 'mysite' directory with the functionality you wish to perform on each post as it is received. Attach the DataExtension to the InstagramPost class as you would with any Extension.

In the _config.yml file, set the PostReceivedCallback to the name of your method. An example Extension to use curl to download each post is included below.

When you're finished with your additional processing, set the InstagramPost::Processed property to true and write it to the database.

#### Example Extension

In mysite/InstagramPostExtension.php

	class InstagramPostExtension extends DataExtension {

		private static $has_one = array(
			"LocalImage" => "Image"
		);

		public function processNewPost() {

			if(isset($this->owner->ID)) {
				$this->_downloadImage($this->owner->ImageURL);

				$this->owner->Processed = true;
				$this->owner->write();
			}

		}

		protected function _downloadImage($img) {
			if($img) {
				$imgName = explode('/', $img);
				$imgName = $imgName[count($imgName)-1];
				
				$file = __DIR__.'/../../assets/instagram/'.$imgName;
				$fh = fopen(__DIR__.'/../../assets/instagram/'.$imgName, 'a+');
				$opts = array(
					CURLOPT_FILE => $fh,
					CURLOPT_TIMEOUT => 28800,
					CURLOPT_URL => $img
				);

				$ch = curl_init();
				curl_setopt_array($ch, $opts);
				curl_exec($ch);

				$imgObj = new Image();
				$imgObj->Filename = 'assets/instagram/'.$imgName;
				$imgObj->Title = str_replace('.jpg', '', $imgName);
				$imgObj->write();

				$this->owner->LocalImageID = $imgObj->ID;
				$this->owner->write();
			}
		}

	}

In mysite/_config.php add:

	DataObject::add_extension('InstagramPost', 'InstagramPostExtension');

Example instagram/_config.yml

	Instagram:
	  clientID: 
	  clientSecret: 
	  callbackUrl: instagram/auth
	  subscribeCallback: instagram/handlepost
	  postReceivedCallback: 'processNewPost'
