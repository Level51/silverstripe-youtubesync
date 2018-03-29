<?php

class YoutubeFactory {

    protected $prepared, $service;

    public function __construct() {
        if (!$this->prepared) {

            if (!defined('YOUTUBE_API_KEY'))
                define('YOUTUBE_API_KEY', SiteConfig::current_site_config()->YoutubeApiKey);

            if (YOUTUBE_API_KEY == '')
                user_error('Please specify a valid Youtube Api Key in the site config.', E_USER_ERROR);

            // Create the service
            $this->service = RestfulService::create('https://www.googleapis.com/youtube/v3/');
            $this->prepared = true;
        }
    }

    /**
     * Get all videos of a given user.
     * First get the related playlists and then their entries - update the database
     *
     * @param      $user          : The youtube user name
     * @param null $playlistLimit : comma separated list of playlists
     *
     * @return array
     */
    public function getVideosByUser($user, $playlistLimit = null) {
        return $this->getVideos($user, 'user', $playlistLimit);
    }

    /**
     * Get all videos of a given channel.
     * First get the related playlists and then their entries - update the database
     *
     * @param string channelId The youtube user name
     * @param null $playlistLimit comma separated list of playlists
     *
     * @return array
     */
    public function getVideosByChannelId($channelId, $playlistLimit = null) {
        return $this->getVideos($channelId, 'channel', $playlistLimit);
    }

    /**
     * Get all videos of a given channel or user.
     * First get the related playlists and then their entries - update the database
     *
     * @param string channelId The youtube user name
     * @param null $playlistLimit comma separated list of playlists
     *
     * @return array
     */
    public function getVideos($userOrChannel, $by = 'user', $playlistLimit = null) {

        if ($by === 'user')
            $playlists = $this->getPlaylistsByUser($userOrChannel);
        else if ($by === 'channel')
            $playlists = $this->getPlaylistsByChannelId($userOrChannel);
        else
            return array(
                'status'  => 'error',
                'message' => 'videos can only be fetched by user or channel, ' . $by . ' passed'
            );

        // Explode the limit string if given
        if ($playlistLimit)
            $included = explode(',', $playlistLimit);

        // Get the videos and merge them in one array
        $videos = ArrayList::create();
        foreach ($playlists as $pName => $pID) {
            if ($playlistLimit) {
                if (in_array($pName, $included))
                    $videos->merge($this->getPlaylistItems($pID));
            } else
                $videos->merge($this->getPlaylistItems($pID));
        }

        // Update the db entries for all fetched videos
        $this->updateVideoEntries($videos);
    }

    /**
     * Call the API to get all playlists of a given channel
     *
     * @param $channelId : The youtube channel id
     *
     * @return array: A array with all playlists in title => id manner or an error array with status and message
     */
    public function getPlaylistsByChannelId($channelId) {
        return $this->getPlaylists(['id' => $channelId]);
    }

    /**
     * Call the API to get all playlists of a given user
     *
     * @param $user : The youtube user name
     *
     * @return array: A array with all playlists in title => id manner or an error array with status and message
     */
    public function getPlaylistsByUser($user) {
        return $this->getPlaylists(['forUsername' => $user]);
    }

    /**
     * Call the API to get all playlists of a given user or channel id.
     *
     * @param $params : Array with additional params. Needed is either the username with "forUsername" or a channel id with "id"
     *
     * @return array: A array with all playlists in title => id manner or an error array with status and message
     */
    public function getPlaylists($params) {

        // Set the related parameter
        $this->service->setQueryString(array_merge([
            'part' => 'contentDetails',
            'key'  => YOUTUBE_API_KEY
        ], $params));

        // Call the API
        $response = $this->service->request('channels');

        // Check if response given
        if (!$response)
            return array(
                'status'  => 'error',
                'message' => 'error during api call'
            );

        // Decode the JSON content
        $response = json_decode($response->getBody());

        // Check if the response contains the needed "items" element
        if (!isset($response->items) || count($response->items) == 0)
            return array(
                'status'  => 'error',
                'message' => 'response given, but contains no items'
            );

        // Get all playlists and return them
        return $response->items[0]->contentDetails->relatedPlaylists;
    }

    /**
     * Get all items of the given playlist
     *
     * @param $playlistID : The unique id of the playlist
     *
     * @return array: ArrayList including a ArrayData element for each item or an empty array
     */
    public function getPlaylistItems($playlistID) {

        $this->service->setQueryString(array(
            'part'       => 'contentDetails,snippet',
            'playlistId' => $playlistID,
            'key'        => YOUTUBE_API_KEY,
            'maxResults' => 50
        ));

        $response = $this->service->request('playlistItems');

        if ($response)
            $response = json_decode($response->getBody());

        // Build the results array if there are some items
        if (isset($response->items) && $response->pageInfo->totalResults > 0) {
            $items = ArrayList::create();
            foreach ($response->items as $vid) {
                $items->push(ArrayData::create($vid));
            }

            return $items;
        }

        return array();
    }

    /**
     * Update or create the database entries for each element of the given videos array
     *
     * @param $videos : Array with all videos to update
     */
    protected function updateVideoEntries($videos) {

        // Holder for all updated or newly created entries
        $processed = ArrayList::create();

        // Loop over the given videos fetched from the API
        foreach ($videos as $video) {

            // Get the entry or create new one
            if (!($yv = YoutubeVideo::get()->filter('PlaylistItemID', $video->id)->first()) && !($yv = YoutubeVideo::get()->filter('VideoID', $video->contentDetails->videoId)->first())) {
                $yv = new YoutubeVideo();
                $yv->Title = $video->snippet->title;
                $yv->Description = $video->snippet->description;
            }

            $yv->PlaylistItemID = $video->id;
            $yv->VideoID = $video->contentDetails->videoId;
            $thumbs = $video->snippet->thumbnails->toMap();
            $yv->ThumbnailURL = end($thumbs)->url;

            // Write to the db and store as processed item
            $yv->write();
            $processed->push($yv);
        }

        /**
         * Loop over all video entries from the database
         * delete all entries, which are found in the db but not processed above
         */
        foreach (YoutubeVideo::get() as $video) {
            if (!$processed->find('ID', $video->ID))
                $video->delete();
        }
    }
}