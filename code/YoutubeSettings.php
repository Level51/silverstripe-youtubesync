<?php

class YoutubeSettings extends DataExtension {
    private static $db = array(
        'YoutubeApiKey'    => 'Varchar(255)',
        'YoutubeUserName'  => 'Varchar',
        'YoutubeChannelId' => 'Varchar',
        'Playlists'        => 'Varchar(255)'
    );

    public function updateCMSFields(FieldList $fields) {
        $fields->addFieldsToTab('Root.Youtube', [
            TextField::create('YoutubeApiKey', _t('YoutubeSettings.YOUTUBE_API_KEY', 'Youtube API Key')),
            LiteralField::create('UserOrChannelHint', '<h4>' . _t('YoutubeSettings.USER_OR_CHANNEL_HINT', 'Please fill only one: Username OR Channel ID') . '</h4>'),
            TextField::create('YoutubeUserName', _t('YoutubeSettings.YOUTUBE_USER_NAME', 'Youtube user name')),
            TextField::create('YoutubeChannelId', _t('YoutubeSettings.YOUTUBE_CHANNEL_ID', 'Youtube channel id')),
            TextField::create('Playlists', _t('YoutubeSettings.PLAYLISTS', 'Youtube playlists'))
                ->setDescription(_t('YoutubeSettings.PLAYLISTS_DESCRIPTION', 'Comma-separated list of Youtube playlists - leave it empty to get all'))
        ]);
    }
}