<?php

class InstagramPost extends SocialPost
{

    private static $db = array(
        "Tags" => "Text",
        "Caption" => "Varchar(255)",
        "CommentCount" => "Int",
        "LikeCount" => "Int",
        "Link" => "Varchar(255)",
        "ImageURL" => "Varchar(255)",
        "Processed" => "Boolean",
        "OrigMessage" => "Text"
    );

    private static $has_one = array(
        "Subscription" => "InstagramSubscription"
    );

    private static $default_sort = "Posted DESC";

    public function setTags($tags)
    {
        $this->Tags = is_array($tags) ? implode(',', $tags) : $tags;
    }
}
