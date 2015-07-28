<?php

class Instagram extends DataObject {}

class Instagram_Controller extends Controller {

	private static $allowed_actions = array(
		'handlepost' => true, 
		'auth' => true, 
		'subscribe' => 'ADMIN', 
		"bounce" => true, 
		'unsubscribe' => 'ADMIN'
	);

	public function init() {
		parent::init();
	}

	/**
	 *
	 * Calls InstagramService::authorize on callback from a click on the InstagramOAuth Login Link
	 *
	 * @params _GET['code'] - returned from instagram
	 *
	 * */
	public function auth() {
        $code = $_GET['code'];

        $is = new InstagramService();
        $is->authorize($code);        

    }

    /**
     *
     *  Echos the challenge from InstagramAPI
     *
     * */
    public function bounce() {
    	echo $_GET['hub.challenge'];
    }

    /**
     *
	 * Subscribe to Instagram from a Link generated via the admin. Just forwards to the service.
     *
     * */
    public function subscribe() {
    	$is = new InstagramService();
    	if($is->subscribeRealtime()) {
    		return $this->redirect('admin/instagram-settings/InstagramSubscription/EditForm/field/InstagramSubscription/item/'.Session::get('InstaSub').'/edit');
    	} else {
		    throw new Exception('Unable to subscribe to Instagram. See logs for more info.');
	    }
    }

    /**
     *
	 * Unsubscribe to Instagram from a Link generated via the admin. Just forwards to the service.
     *
     * */
    public function unsubscribe() {
    	$is = new InstagramService();
    	if($is->unsubscribeRealtime()) {
    		return $this->redirect('admin/instagram-settings/InstagramSubscription/EditForm/field/InstagramSubscription/item/'.Session::get('InstaSub').'/edit');
    	} else {
		    throw new Exception('Unable to unsubscribe to Instagram. See logs for more info.');
	    }
    }

    /**
     *
	 * Handles the notification from Instagram that there is an update to the subscription.
	 * Makes a call to the Instagram API and gets the new/updated posts and creates InstagramPost objects
	 *
	 * Optionally: calls a callback function set in _config.yml for each post after the DataObject is created.
     *
     * */
	public function handlepost() {		
		
		if($this->request->isGET()) {
			echo $_GET['hub_challenge'];
			exit;
		} else {
			$config = SiteConfig::current_site_config();

			$mystring = file_get_contents('php://input');
			$ALL = date("F j, Y, g:i a")." ".$mystring."\r\n";
			$fh = fopen(__DIR__.'/instagramactivity.log', 'a+');
			fwrite($fh, $ALL);

			$data = json_decode($mystring);

			$sub = $this->findSubscription($data);

			if(!$sub) {
				error_log('Unable to find subscription for subscription id: '.$data[0]->subscription_id);
				return false; 
			}

			$is = new InstagramService($sub);

			$minID = $sub->MinID;

			$update = false;
			switch ($data[0]->object) {
				case "tag" :
					$update = $is->tagsRecent($data[0]->object_id, null, $minID);
					if($update) {
						$sub->MinID = $update->pagination->min_tag_id;
					}
					break;
				case "user":
					$update = $is->getUserRecent($data[0]->object_id, null, null, null,  $minID);
					if($update) {
						$sub->MinID = $update->data[0]->created_time;
					}
					break;
			}		

			$callbackFunc = Config::inst()->get('Instagram', 'postReceivedCallback');

			if($update) {
				$sub->write();
				foreach($update->data as $img) {
					if(isset($img->id)) {
						$p = InstagramPost::get()->Filter(array('PostID' => $img->id));
						if($p->Count() == 0)
							$p = new InstagramPost();
						else {
							$p = $p->first();
						}
						if(isset($img->tags)) {
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
						$p->SubscriptionID = $sub->ID;
						$p->write();						

						if($callbackFunc !== "") {
							$p->$callbackFunc();
						}
					}
				}
			}

			fclose($fh);			
			
		}
	}

	protected function findSubscription($post) {
		$subId = $post[0]->subscription_id;
		return InstagramSubscription::get()->filter(array('SubscriptionID' => $subId))->First();
	}

	protected function getMostRecent() {
		return InstagramPost::get()->sort("Posted DESC")->First();
	}

}
