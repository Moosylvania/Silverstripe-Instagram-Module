<?php

class InstagramSubscription extends DataObject {

	private static $db = array(
		'Name' => 'Varchar(255)',
		'Type' => "Enum('user,tag')",
		'Hashtag' => "Varchar(255)",
		'SubscriptionID' => "Text",
		"MinID" => "Varchar(255)",
		"AccessToken"=> "Text"
	);

	private static $has_many = array(
		"Posts" => "InstagramPost"
	);

	private static $summary_fields = array('ID', 'Name', 'Type');

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$token = $this->AccessToken;
		$subID = $this->SubscriptionID;

		$fields->removeByName('SubscriptionID');
		$fields->removeByName('AccessToken');
		$fields->removeByName('MinID');

		if($this->ID) {
			$fields->addFieldToTab("Root.Main", new LiteralField("UpdateAccessToken", '<div class="field text"><label class="left">Access Token</label><div class="middleColumn"><a href="'.InstagramService::loginLink($this->ID).'" id="sync-cigars" class="ss-ui-button ss-ui-action-constructive">Update Access Token</a> Current Token: '.$token.'</div></div>'));
			$fields->addFieldToTab("Root.Main", new LiteralField("SubscribeRealtime", '<div class="field text"><label class="left">Subscription ID:</label><div class="middleColumn"><a href="/instagram/subscribe?subscription='.$this->ID.'" id="sync-ciga" class="ss-ui-button ss-ui-action-constructive" >Subscribe Real-time</a> Current Subscription: '.$subID.'</div></div>'));
			if($this->SubscriptionID) {
				$fields->addFieldToTab("Root.Main", new LiteralField("SubscribeRealtime", '<div class="field text"><label class="left">Unsubscribe:</label><div class="middleColumn"><a href="/instagram/unsubscribe?subscription='.$this->ID.'" id="sync-cigars" class="ss-ui-button ss-ui-action-destructive" >Unsubscribe</a> Current Subscription: '.$subID.'</div></div>'));
			}
			if($this->Posts()->Count() == 0 && !empty($token)) {
				$fields->addFieldToTab("Root.Main", new LiteralField("PopulateSubscription", '<div class="field text"><label class="left">Fetch recent items:</label><div class="middleColumn"><a href="/instagram/prefetch?subscription='.$this->ID.'" id="sync-cigars" class="ss-ui-button ss-ui-action-constructive" >Fetch</a></div></div>'));
			}
		}

		return $fields;
	}

	public function updatePosts($objectID='') {
		$is = new InstagramService($this);
		$minID = $this->MinID;

		$update = false;
		switch ($this->Type) {
			case "tag" :
				if (empty($objectID)) $objectID = $this->HashTag;
				$update = $is->tagsRecent($objectID, null, $minID);
				if($update) {
					$this->MinID = $update->pagination->min_tag_id;
				}
				break;
			case "user":
				if (empty($objectID)) $objectID = 'self';
				$update = $is->getUserRecent($objectID, null, null, null,  $minID);
				if($update) {
					$this->MinID = $update->data[0]->created_time;
				}
				break;
		}

		$callbackFunc = (string)Config::inst()->get('Instagram', 'postReceivedCallback');

		if($update) {
			$this->write();
			foreach($update->data as $img) {
				if(isset($img->id)) {
					$p = InstagramPost::get()->Filter(array('PostID' => $img->id));
					if($p->Count() == 0)
						$p = new InstagramPost();
					else {
						$p = $p->first();
					}
					if(!empty($img->tags)) {
						$p->setTags($img->tags);
					}
					$p->Caption = $img->caption->text;
					$p->Link = $img->link;
					$p->Posted = $img->created_time;
					$p->ImageURL = $img->images->standard_resolution->url;
					$p->PostID = $img->id;
					$p->Processed = false;
					$p->OrigMessage = json_encode($img);
					$p->Type = "Instagram";
					$p->CommentCount = $img->comments->count;
					$p->LikeCount = $img->likes->count;
					$p->SubscriptionID = $this->ID;
					$p->write();

					if(!empty($callbackFunc)) {
						$p->$callbackFunc();
					}
				}
			}

			return true;
		} else {
			return false;
		}
	}
}
