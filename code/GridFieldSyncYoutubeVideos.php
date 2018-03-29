<?php

class GridFieldSyncYoutubeVideos implements GridField_HTMLProvider, GridField_ActionProvider
{

    protected $targetFragment;

    public function __construct($targetFragment = "before") {
        $this->targetFragment = $targetFragment;
    }

    public function getHTMLFragments($gridField) {
        $button = new GridField_FormAction(
            $gridField,
            'syncwithyoutube',
            _t('GridFieldSyncYoutubeVideos.CTA','Sync with Youtube'),
            'syncwithyoutube',
            null
        );
        $button->setAttribute('data-icon', 'accept');
        $button->addExtraClass('no-ajax');
        return array(
            $this->targetFragment => '<p class="grid-csv-button">' . $button->Field() . '<br><br></p>',
        );
    }

    public function getActions($gridField) {
        return array('syncwithyoutube');
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data) {
        if($actionName == 'syncwithyoutube') {
            $this->handleSyncWithYoutube($gridField);
        }
    }

    /**
     * Call the youtube factory function to get and update the video entries
     */
    public function handleSyncWithYoutube($gridField, $request = null) {

        // Get the youtube factory as singleton
        $yf = Injector::inst()->get('YoutubeFactory');

        // Get the SiteConfig
        $sc = SiteConfig::current_site_config();

        // Call the factory
        if ($sc->YoutubeUserName)
            $yf->getVideosByUser($sc->YoutubeUserName, $sc->Playlists);
        else if ($sc->YoutubeChannelId)
            $yf->getVideosByChannelId($sc->YoutubeChannelId, $sc->Playlists);

        // Redirect to the grid overview
        Controller::curr()->redirectBack();
    }

}
