<?php

class Instagram extends DataObject
{
}

class Instagram_Controller extends Controller
{

    private static $allowed_actions = array(
        'handlepost' => true,
        'auth' => true,
        'subscribe' => 'ADMIN',
        "bounce" => true,
        'unsubscribe' => 'ADMIN',
        'prefetch' => true,
    );

    public function init()
    {
        parent::init();
    }

    /**
     *
     * Calls InstagramService::authorize on callback from a click on the InstagramOAuth Login Link
     *
     * @params _GET['code'] - returned from instagram
     *
     * */
    public function auth()
    {
        $code = $_GET['code'];

        $is = new InstagramService();
        $is->authorize($code);
    }

    /**
     *
     *  Echos the challenge from InstagramAPI
     *
     * */
    public function bounce()
    {
        echo $_GET['hub.challenge'];
    }

    /**
     *
     * Subscribe to Instagram from a Link generated via the admin. Just forwards to the service.
     *
     * */
    public function subscribe()
    {
        $is = new InstagramService();
        if ($is->subscribeRealtime()) {
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
    public function unsubscribe()
    {
        $is = new InstagramService();
        if ($is->unsubscribeRealtime()) {
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
    public function handlepost()
    {
        if ($this->request->isGET()) {
            echo $_GET['hub_challenge'];
            exit;
        } else {
            $config = SiteConfig::current_site_config();

            $mystring = file_get_contents('php://input');
            $ALL = date("F j, Y, g:i a")." ".$mystring."\r\n";
            $fh = fopen(__DIR__.'/instagramactivity.log', 'a+');
            fwrite($fh, $ALL);
            fclose($fh);

            $data = json_decode($mystring);

            $sub = $this->findSubscription($data);

            if (!$sub) {
                error_log('Unable to find subscription for subscription id: '.$data[0]->subscription_id);
                return false;
            }

            $sub->updatePosts($data[0]->object_id);
        }
    }

    /**
     * @param SS_HTTPRequest $request
     * @return SS_HTTPResponse
     * @throws Exception
     */
    public function prefetch($request)
    {
        $subscription = InstagramSubscription::get()->byID($request->getVar('subscription'));
        if (!$subscription) {
            $this->httpError(404, "Subscription not found");
        }

        if ($subscription->updatePosts()) {
            return $this->redirect('admin/instagram-settings/InstagramSubscription/EditForm/field/InstagramSubscription/item/'
                . Session::get('InstaSub') . '/edit');
        } else {
            throw new Exception('Unable to fetch posts from Instagram. See logs for more info.');
        }
    }

    protected function findSubscription($post)
    {
        $subId = $post[0]->subscription_id;
        return InstagramSubscription::get()->filter(array('SubscriptionID' => $subId))->First();
    }

    protected function getMostRecent()
    {
        return InstagramPost::get()->sort("Posted DESC")->First();
    }
}
