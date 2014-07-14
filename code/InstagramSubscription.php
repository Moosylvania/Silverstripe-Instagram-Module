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
		}		

		return $fields;
	}
}