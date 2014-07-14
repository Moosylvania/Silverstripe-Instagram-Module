<?php

class InstagramService extends RestfulService {
    public $connection;

    private $clientID;
    private $clientSecret;
    private $access_token;

    public $config;

	protected $api_urls = array(
    	'user'						=> 'v1/users/%s/?access_token=%s',
        'user_feed'					=> 'v1/users/self/feed?access_token=%s&max_id=%s&min_id=%s',
        'user_recent'				=> 'v1/users/%s/media/recent/?access_token=%s&max_id=%s&min_id=%s&max_timestamp=%s&min_timestamp=%s',
        'user_search'				=> 'v1/users/search?q=%s&access_token=%s',
        'user_follows'				=> 'v1/users/%s/follows?access_token=%s',
        'user_followed_by'			=> 'v1/users/%s/followed-by?access_token=%s',
        'user_requested_by'			=> 'v1/users/self/requested-by?access_token=%s',
        'user_relationship'			=> 'v1/users/%s/relationship?access_token=%s',
        'modify_user_relationship'	=> 'v1/users/%s/relationship?action=%s&access_token=%s',
        'media'						=> 'v1/media/%s?access_token=%s',
        'media_search'				=> 'v1/media/search?lat=%s&lng=%s&max_timestamp=%s&min_timestamp=%s&distance=%s&access_token=%s',
        'media_popular'				=> 'v1/media/popular?access_token=%s',
        'media_comments'			=> 'v1/media/%s/comments?access_token=%s',
        'post_media_comment'		=> 'v1/media/%s/comments?access_token=%s',
        'delete_media_comment'		=> 'v1/media/%s/comments?comment_id=%s&access_token=%s',
        'likes'						=> 'v1/media/%s/likes?access_token=%s',
    	'post_like'					=> 'v1/media/%s/likes?access_token=%s',
        'remove_like'				=> 'v1/media/%s/likes?access_token=%s',
        'tags'						=> 'v1/tags/%s?access_token=%s',
        'tags_recent'				=> 'v1/tags/%s/media/recent?max_tag_id=%s&min_tag_id=%s&access_token=%s',
        'tags_search'				=> 'v1/tags/search?q=%s&access_token=%s',
        'locations'					=> 'v1/locations/%d?access_token=%s',
        'locations_recent'			=> 'v1/locations/%d/media/recent/?max_id=%s&min_id=%s&max_timestamp=%s&min_timestamp=%s&access_token=%s',
        'locations_search'			=> 'v1/locations/search?lat=%s&lng=%s&foursquare_id=%s&distance=%s&access_token=%s'
    );

	public function __construct($sub=false, $header = null, $expiry=-1) {

        parent::__construct('https://api.instagram.com/', $expiry);

        $this->clientID = Config::inst()->get('Instagram', 'clientID');
        $this->clientSecret = Config::inst()->get('Instagram', 'clientSecret');

        $this->config = SiteConfig::current_site_config();

        if($sub) {
            $this->access_token = $sub->AccessToken;
        } else {
            $this->access_token = false;
        }
	}

    public static function loginLink($subID) {
        $clientID = Config::inst()->get('Instagram', 'clientID');
        $callback = Director::absoluteBaseURL().Config::inst()->get('Instagram', 'callbackUrl');
        Session::set('InstaSub', $subID);
        return 'https://api.instagram.com/oauth/authorize/?client_id='.$clientID.'&redirect_uri='.$callback.'&response_type=code';
    }

    public function authorize($code) {
        $callback = Director::absoluteBaseURL().Config::inst()->get('Instagram', 'callbackUrl');

        $auth_url = 'oauth/access_token';

        $res = $this->request($auth_url, 'POST', 'client_id='.$this->clientID.'&client_secret='.$this->clientSecret.'&grant_type=authorization_code&redirect_uri='.$callback.'&code='.$code);

        $jData = json_decode($res->getBody());
        $subID = Session::get('InstaSub');
        $sub = InstagramSubscription::get()->byID($subID);
        $sub->AccessToken = $jData->access_token;
        $sub->write();

        Controller::curr()->redirect('admin/instagram-settings/InstagramSubscription/EditForm/field/InstagramSubscription/item/'.$subID.'/edit');
    }

    public function subscribeRealtime() {
        $subID = $_GET['subscription'];
        $sub = InstagramSubscription::get()->byID($subID);

        $callback = Director::absoluteBaseURL().Config::inst()->get('Instagram', 'subscribeCallback');

        $type = $sub->Type;
        
        if($type !== 'tag' && $type !== 'user') {
            throw new Exception('Subscription type is invalid');
        }

        $url = 'v1/subscriptions';
        if($type=='tag') {
            $key = $sub->Hashtag;
            $data = array(
                "client_id"=> $this->clientID,
                'client_secret' => $this->clientSecret,
                'object' => 'tag',
                'object_id' => $key,
                'aspect' => 'media', 
                //'verify_token' => $this->access_token,
                'callback_url' => $callback
            );
        } else if($type == 'user') {
            $data = array(
                "client_id"=> $this->clientID,
                'client_secret' => $this->clientSecret,
                'object' => 'user',
                'aspect' => 'media', 
                'verify_token' => $sub->AccessToken,
                'callback_url' => $callback
            );
        }

        $res = $this->json_request($url, 'POST', $data);

        if($res->meta->code == 200) {
            $sub->SubscriptionID = $res->data->id;
            $sub->write();     
            return true;
        } else {
           throw new Exception('Error Subscribing to Instagram');
        }
    }

    public function unsubscribeRealtime() {
        $subID = $_GET['subscription'];

        $sub = InstagramSubscription::get()->byID($subID);
        if(!$sub) {
            throw new Exception('Unable to find Subscription.');
        }

        $url = sprintf('v1/subscriptions?id=%s&client_id=%s&client_secret=%s',
            $sub->SubscriptionID,
            $this->clientID,
            $this->clientSecret
        );

        $res = $this->json_request($url, "DELETE");

        if($res->meta->code == 200) {
            $sub->SubscriptionID = '';
            $sub->MinID = '';
            $sub->write();
            return true;
        } else {
            throw new Exception('Unable to unsubscribe.');
        }
    }

    protected function json_request($url, $method = 'GET', $data=null) {
        $res = $this->request($url, $method, $data);

        return json_decode($res->getBody());
    }

    protected function access_token() {
        if(!$this->access_token) {
            throw new Exception('Missing a subscription to use with Instagram.');
        }
        return $this->access_token;
    }

    /*
     * Get an individual user's details
     * Accepts a user id
     * @param int Instagram user id
     * @return std_class data about the Instagram user
     */
    public function getUser($user_id) {
        $req_url = sprintf($this->api_urls['user'], $user_id, $this->access_token());

        return $this->json_request($req_url);
    }

    /*
     * Get an individual user's feed
     * Accepts optional max and min values
     * @param int return media after max id
     * @param int return media before min id
     * @return std_class of user's feed
     */
    public function getUserFeed($max = null, $min = null) {
        $url = sprintf($this->api_urls['user_feed'], $this->access_token(), $max, $min);

        return $this->json_request($url);
    }

    /*
     * Function to get a users recent published media
     * Accepts a user id and access token and optional max id, min id, max timestamp and min timestamp
     * @param int Instagram user id
     * @param int return media after max id
     * @param int return media before min id
     * @param int return media after this UNIX timestamp
     * @param int return media before this UNIX timestamp
     * @return std_class of media found based on parameters given
     */
    public function getUserRecent($user_id, $max_id = null, $min_id = null, $max_timestamp = null, $min_timestamp = null) {
        $url = sprintf($this->api_urls['user_recent'], $user_id, $this->access_token(), $maxi_id, $min_id, $max_timestamp, $min_timestamp);

        return $this->json_request($url);
    }

    /*
     * Function to search for user
     * Accepts a user name to search for
     * @param string an Instagram user name
     * @return std_class user data
     */
    public function userSearch($user_name) {
        $url = sprintf($this->api_urls['user_search'], $user_name, $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to get all users the current user follows
     * Accepts a user id
     * @param int user id
     * @return std_class user's recent feed items
     */
    public function userFollows($user_id) {
        $url = sprintf($this->api_urls['user_follows'], $user_id, $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to get all users the current user follows
     * Accepts a user id
     * @param int user id
     * @return std_class other users that follow the one passed in
     */
    public function userFollowedBy($user_id) {
        $url = sprintf($this->api_urls['user_followed_by'], $user_id, $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to find who a user was requested by
     * Accepts an access token
     * @return std_class users who have requested this user's permission to follow
     */
    public function userRequestedBy() {
        $url = sprintf($this->api_urls['user_requested_by'], $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to get information about the current user's relationship (follow/following/etc) to another user
     * @param int user id
     * @return std_class user's relationship to another user
     */
    public function userRelationship($user_id) {
        $url = sprintf($this->api_urls['user_relationship'], $user_id, $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to modify the relationship between the current user and the target user
     * @param int Instagram user id
     * @param string action to effect relatonship (follow/unfollow/block/unblock/approve/deny)
     * @return std_class result of request
     */
    public function modifyUserRelationship($user_id, $action) {
        $url = sprintf($this->api_urls['modify_user_relationship'], $user_id, $action, $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to get data about a media id
     * Accepts a media id
     * @param int media id
     * @return std_class data about the media item
     */
    public function getMedia($media_id) {
        $url = sprintf($this->api_urls['media'], $media_id, $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to search for media
     * Accepts optional parameters
     * @param int latitude
     * @param int longitude
     * @param int max timestamp
     * @param int min timestamp
     * @param int distance
     * @return std_class media items found in search
     */
    public function mediaSearch($latitude=null, $longitude=null, $max_timestamp=null, $min_timestamp=null, $distance=null) {
        $url = sprintf($this->api_urls['media_search'], $latitude, $longitude, $max_timestamp, $min_timestamp, $distance);

        return $this->json_request($url);
    }

    /*
     * Function to get a list of what media is most popular at the moment
     * @return std_class popular media
     */
    public function popularMedia() {
        $url = sprintf($this->api_urls['media_popular'], $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to gget a full list of comments on a media
     * @param int media id
     * @return std_class media comments
     */
    public function mediaComments($media_id) {
        $url = sprintf($this->api_urls['media_comments'], $media_id, $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to get a list of users who have liked this media
     * @param int media id
     * @return std_class list of users
     */
    public function mediaLikes($media_id) {
        $url = sprintf($this->api_urls['likes'], $media_id, $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to post a like for a media item
     * @param int media id
     * @return std_class response to request
     */
    public function postLike($media_id) {
        $url = sprintf($this->api_urls['post_like'], $media_id, $this->access_token());

        return $this->json_request($url, 'POST');
    }

    /*
     * Function to remove a like for a media item
     * @param int media id
     * @return std_class response to request
     */
    public function removeLike($media_id) {
        $url = sprintf($this->api_urls['remove_like'], $media_id, $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to get information about a tag object
     * @param string tag
     * @return std_class of data about the tag
     */
    public function getTags($tag) {
        $url = sprintf($this->api_urls['tags'], $tag, $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to get a list of recently tagged media
     * @param string tag
     * @param int return media after this max_id
     * @param int return media before this min_id
     * @return std_class recently tagged media
     */
    public function tagsRecent($tag, $max_id=null, $min_id=null) {
        $url = sprintf($this->api_urls['tags_recent'], $tag, $max_id, $min_id, $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to search for tagged media
     * @param string valid tag name without a leading #. (eg. snow, nofilter)
     * @return std_class tags by name - results are ordered first as an exact match, then by popularity
     */
    public function tagsSearch($tag) {
        $url = sprintf($this->api_urls['tags_search'], $tag, $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to get information about a location. 
     * @param int location id
     * @return std_class data about the location
     */
    public function getLocation($location) {
        $url = sprintf($this->api_urls['locations'], $location, $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to get a list of recent media objects from a given location.
     * @param int location id
     * @param int return media after this max_id
     * @param int return media before this min_id
     * @param int return media after this UNIX timestamp
     * @param int return media before this UNIX timestamp
     * @return std_class recent media objects from a location
     */
    public function locationRecent($location, $max_id=null, $min_id=null, $max_timestamp=null, $min_timestamp=null) {
        $url = sprintf($this->api_urls['locations_recent'], $location, $max_id, $min_id, $max_timestamp, $min_timestamp, $this->access_token());

        return $this->json_request($url);
    }

    /*
     * Function to search for locations used in Instagram
     * @param int latitude of the center search coordinate. If used, longitude is required
     * @param int longitude of the center search coordinate. If used, latitude is required
     * @param int Foursquare id. Returns a location mapped off of a foursquare v1 api location id. If used, you are not required to use lat and lng. Note that this method will be deprecated over time and transitioned to new foursquare IDs with V2 of their API.
     * @param int distance. Default is 1000m (distance=1000), max distance is 5000
     * @return std_class location data
     */
    public function locationSearch($latitude=null, $longitude=null, $foursquare_id=null, $distance=null) {
        $url = sprintf($this->api_urls['locations_search'], $latitude, $longitude, $foursquare_id, $distance, $this->access_token());

        return $this->json_request($url);
    }

}