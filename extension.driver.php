<?php
Class extension_protected_download extends Extension
{
    public function about(){
      return array(
        'name' => 'Protected Download',
        'version' => '.1',
        'release-date' => '2013-07-04',
        'author' => array(
          'name' => 'Colin Brogan',
          'website' => 'http://cbrogan.com',
          'email' => 'colinbrogan@gmail.com'
        ),
        'description' => 'Allow files to be uploaded on the backend which are only downloadable by site visitors through a set of keys, which optionally expire after a client-defined number of downloads.'
      );
    }

    public function install(){
      try {
        Symphony::Database()->query("
          CREATE TABLE IF NOT EXISTS `sym_fields_protected_download` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `field_id` INT(11) UNSIGNED NOT NULL,
            `destination` TEXT NULL,
            `validator` varchar(100) default NULL,
            `taglist_validator` varchar(100) default NULL,
            `pre_populate_source` varchar(255) default NULL,
            `pre_populate_min` int(11) unsigned NOT NULL,
            `external_source_url` varchar(255) default NULL,
            `external_source_path` varchar(255) default NULL,
            `ordered` enum('yes','no') NOT NULL default 'no',
            `delimiter` varchar(5) NOT NULL default ',',
            PRIMARY KEY (`id`),
            UNIQUE KEY `field_id` (`field_id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ");
      }
      catch (Exception $ex) {
        $extension = $this->about();
        Administration::instance()->Page->pageAlert(__('An error occurred while installing %s. %s', array($extension['name'], $ex->getMessage())), Alert::ERROR);
        return false;
      }
      return true;
    }

    public function update() {
      return true;
    }

    public function uninstall(){
      if(parent::uninstall() == true){
        try {
          Symphony::Database()->query("DROP TABLE `sym_fields_protected_download`");
          return true;
        }
        catch (Exception $ex) {
          $extension = $this->about();
          Administration::instance()->Page->pageAlert(__('An error occurred while uninstalling %s. %s', array($extension['name'], $ex->getMessage())), Alert::ERROR);
          return false;
        }
      }

      return false;
    }

	// Set the delegates:
	public function getSubscribedDelegates()
	{
		return array(
			array(
				'delegate' => 'AddCustomPreferenceFieldsets',
				'page' => '/system/preferences/',
				'callback' => 'appendPreferences'
			),
			array(
				'page' => '/system/preferences/',
				'delegate' => 'Save',
				'callback' => 'savePreferences'
			)
		);
	}

	/**
	 * Returns an array of locations where force download is allowed to download from
	 *
	 * @return array
	 */
	public function getLocations()
	{
		$locations = unserialize(Symphony::Configuration()->get('trusted_locations', 'force_download'));
		if(is_array($locations))
		{
			return array_filter($locations);
		} else {
			return array();
		}
		
	}

	/**
	 * Save the preferences
	 *
	 * @param $context
	 */
	public function savePreferences($context)
	{
		if(isset($_POST['force_download']['trusted_locations']))
		{
			Symphony::Configuration()->set('trusted_locations', serialize(explode("\n", str_replace("\r", '', $_POST['force_download']['trusted_locations']))), 'force_download');
			Symphony::Configuration()->write();
		}
	}
}
