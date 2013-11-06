<?php

	/**
	 * @package toolkit
	 */

	require_once FACE . '/interface.exportablefield.php';
	require_once FACE . '/interface.importablefield.php';

	/**
	 * A simple Upload field that essentially maps to HTML's `<input type='file '/>`.
	 */
	class Fieldprotected_download extends Field implements ExportableField, ImportableField {
		protected static $imageMimeTypes = array(
			'image/gif',
			'image/jpg',
			'image/jpeg',
			'image/pjpeg',
			'image/png',
			'image/x-png',
		);

		public function __construct(){
			parent::__construct();

			$this->_name = __('Protected Download');
			$this->_required = true;

			$this->set('location', 'sidebar');
			$this->set('required', 'no');
		}

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function canFilter() {
			return true;
		}

		public function canPrePopulate(){
			return true;
		}

		public function allowDatasourceParamOutput(){
			return true;
		}

		public function isSortable(){
			return true;
		}

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

		public function createTable(){
			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `file` varchar(255) default NULL,
				  `size` int(11) unsigned NULL,
				  `mimetype` varchar(100) default NULL,
				  `meta` varchar(255) default NULL,
				  `handle` varchar(255) default NULL,
				  `value` varchar(255) default NULL,
				  `d-count` int(10) unsigned NULL,
				  PRIMARY KEY  (`id`),
				  KEY `file` (`file`),
				  KEY `mimetype` (`mimetype`),
				  KEY `handle` (`handle`),
				  UNIQUE KEY `value` (`value`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function findAllTags(){
			$pre_populate_source = array('existing');
			if(!is_array($pre_populate_source)) return;

			$values = array();
			$sql = "SELECT DISTINCT `value`, COUNT(value) AS Used FROM sym_entries_data_%d GROUP BY `value` HAVING Used >= " . $this->get('pre_populate_min') . " ORDER BY `value` ASC";

			foreach($pre_populate_source as $item){
				if($item == 'external') {
					$sourcedata = simplexml_load_file($this->get('external_source_url'));
					$terms = $sourcedata->xpath($this->get('external_source_path'));
					$values = array_merge($values, $terms);
				}
				else {
					$result = Symphony::Database()->fetchCol('value', sprintf($sql, ($item == 'existing' ? $this->get('id') : $item)));
					if(!is_array($result) || empty($result)) continue;
					$values = array_merge($values, $result);
				}
			}

			return array_unique($values);
		}

		public function findOtherTags($current_entry_id=null){
			if($current_entry_id!=null){
				$sql = sprintf("SELECT value FROM sym_entries_data_%s WHERE entry_id!=%s", $this->get('id'),$current_entry_id);
			} else {
				$sql = sprintf("SELECT value FROM sym_entries_data_%s", $this->get('id'));
			}
			echo $sql;
			$result = array();
			$result = Symphony::Database()->fetch( $sql );
			return $result;
		}

		public function search_codes($code, $array) {
			foreach($array as $key=>$value) {
				if($value['value']==$code) {
					return true;
				}
			}
			return false;
		}


		private static function __tagArrayToString(array $tags, $delimiter, $ordered){
			if(empty($tags)) return NULL;
			if ($ordered != 'yes') {
			  sort($tags);
			}
			return implode($delimiter . ' ', $tags);
		}

		public function entryDataCleanup($entry_id, $data=NULL){
			$file_location = WORKSPACE . '/' . ltrim($data['file'][0], '/');

			if(is_file($file_location)){
				General::deleteFile($file_location);
			}

			parent::entryDataCleanup($entry_id);

			return true;
		}

		public static function getMetaInfo($file, $type){
			$meta = array();

			if(!file_exists($file) || !is_readable($file)) return $meta;

			$meta['creation'] = DateTimeObj::get('c', filemtime($file));

			return $meta;
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);

			// Destination Folder
			$ignore = array(
				'/workspace/events',
				'/workspace/data-sources',
				'/workspace/text-formatters',
				'/workspace/pages',
				'/workspace/utilities'
			);
			$directories = General::listDirStructure(WORKSPACE, null, true, DOCROOT, $ignore);

			$label = Widget::Label(__('Destination Directory'));

			$options = array();
			$options[] = array('/workspace', false, '/workspace');
			if(!empty($directories) && is_array($directories)){
				foreach($directories as $d) {
					$d = '/' . trim($d, '/');
					if(!in_array($d, $ignore)) $options[] = array($d, ($this->get('destination') == $d), $d);
				}
			}

			$label->appendChild(Widget::Select('fields['.$this->get('sortorder').'][destination]', $options));

			if(isset($errors['destination'])) $wrapper->appendChild(Widget::Error($label, $errors['destination']));
			else $wrapper->appendChild($label);

			$this->buildValidationSelect($wrapper, $this->get('validator'), 'fields['.$this->get('sortorder').'][validator]', 'upload');

			$div = new XMLElement('div', NULL, array('class' => 'two columns'));
			$this->appendRequiredCheckbox($div);
			$this->appendShowColumnCheckbox($div);
			$wrapper->appendChild($div);
		}

		public function checkFields(array &$errors, $checkForDuplicates = true){
			if (is_dir(DOCROOT . $this->get('destination') . '/') === false) {
				$errors['destination'] = __('The destination directory, %s, does not exist.', array(
					'<code>' . $this->get('destination') . '</code>'
				));
			}

			else if (is_writable(DOCROOT . $this->get('destination') . '/') === false) {
				$errors['destination'] = __('The destination directory is not writable.')
					. ' '
					. __('Please check permissions on %s.', array(
						'<code>' . $this->get('destination') . '</code>'
					));
			}

			parent::checkFields($errors, $checkForDuplicates);
		}

		public function commit(){
			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			$fields = array();

			$fields['pre_populate_source'] = 'existing';
			$fields['destination'] = $this->get('destination');
			$fields['delimiter'] = ',';
			$fields['validator'] = ($fields['validator'] == 'custom' ? NULL : $this->get('validator'));

			return FieldManager::saveSettings($id, $fields);
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null) {
		    if(class_exists('Administration')
		      && Administration::instance() instanceof Administration
		      && Administration::instance()->Page instanceof HTMLPage
		    ) {
		      Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/protected_download/assets/field_protected_download.publish.css', 'screen', 100, false);
		    }

			if (is_dir(DOCROOT . $this->get('destination') . '/') === false) {
				$flagWithError = __('The destination directory, %s, does not exist.', array(
					'<code>' . $this->get('destination') . '</code>'
				));
			}

			else if ($flagWithError && is_writable(DOCROOT . $this->get('destination') . '/') === false) {
				$flagWithError = __('Destination folder is not writable.')
					. ' '
					. __('Please check permissions on %s.', array(
						'<code>' . $this->get('destination') . '</code>'
					));
			}

			$frame = new XMLElement('div',NULL, array('class'=>'frame'));

			$fieldlist = new XMLElement('ol', NULL);

			$li1 = new XMLElement('li');
			$li2 = new XMLElement('li');
			$li3 = new XMLElement('li');

			$label = Widget::Label($this->get('label'));
			$label->setAttribute('class', 'file');
			if(is_array($data['file'])) {
				$data['file'] = $data['file'][0];
			}
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));

			$span = new XMLElement('span', NULL, array('class' => 'frame'));
			if ($data['file']) {
				// Check to see if the file exists without a user having to
				// attempt to save the entry. RE: #1649
				$file = WORKSPACE . preg_replace(array('%/+%', '%(^|/)\.\./%'), '/', $data['file']);

				if (file_exists($file) === false || !is_readable($file)) {
					$flagWithError = __('The file uploaded is no longer available. Please check that it exists, and is readable.');
				}

				$span->appendChild(new XMLElement('span', Widget::Anchor('/workspace' . preg_replace("![^a-z0-9]+!i", "$0&#8203;", $data['file']), URL . '/workspace' . $data['file'])));
			}

			$span->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][file]'.$fieldnamePostfix, $data['file'], (!empty($data['file']) ? 'hidden' : 'file')));

			$label->appendChild($span);

			if($flagWithError != NULL) $li1->appendChild(Widget::Error($label, $flagWithError));
			else $li1->appendChild($label);

			$fieldlist->appendChild($li1);

			$label2 = Widget::Label(__('Maximum downloads'));

			// find the highest d-count in the lot
			$dcount = null;
			if ($data) {
				$dcount = $data['d-count'][0];
				foreach($data['d-count'] as $key=>$value) {
					if($value>$data['d-count']) {
						$dcount = $value;
					}
				}
			}
			
			// ===============================

			$label2->appendChild(Widget::Input('fields['.$this->get('element_name').'][d-count]',$dcount));
			$label2->appendChild(new XMLElement('p',__('If you want the keys to expire after a number of downloads, specify a number here. Otherwise, leave blank for unlimited downloads')));
			$li2->appendChild($label2);

			$fieldlist->appendChild($li2);

			// Begin enhancedtaglist import

			$value = NULL;
			if(isset($data['value'])){
				$value = (is_array($data['value']) ? self::__tagArrayToString($data['value'], $this->get('delimiter'), $this->get('ordered')) : $data['value']);
			}

			$label3 = Widget::Label('Download codes');
			$label3->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][keys]'.$fieldnamePostfix, (strlen($value) != 0 ? $value : NULL)));

			if($flagWithError != NULL) $li3->appendChild(Widget::Error($label3, $flagWithError));
			else $li3->appendChild($label3);

			$fieldlist->appendChild($li3);
			
			$existing_tags = $this->findAllTags();
			if(is_array($existing_tags) && !empty($existing_tags)){
				$taglist = new XMLElement('ul');
				$taglist->setAttribute('class', 'keys');
				$taglist->appendChild(new XMLElement('li', __('Existing Codes:')));
				foreach($existing_tags as $tag) $taglist->appendChild(new XMLElement('li', $tag));
				$li3->appendChild($taglist);
			}
			$frame->appendChild($fieldlist);
			$wrapper->appendChild($frame);
		}

		public function checkPostFieldData($data, &$message, $entry_id=NULL){
			/**
			 * For information about PHPs upload error constants see:
			 * @link http://php.net/manual/en/features.file-upload.errors.php
			 */
			$message = null;

			// check if other albums use the same download codes
			
			$prev_keys = $this->findOtherTags($entry_id);

			$posted_keys = explode(',',$data['keys']);

			$duplicate_keys = array();
			foreach($posted_keys as $index=>$key) {
				$key = trim($key);
				if($this->search_codes($key,$prev_keys)) {
					$duplicate_keys[] = $key;
				}
			}
			if(!empty($duplicate_keys)) {
				$message = __('The download codes specified are used in other Album entries, look at the list below and make sure to only add unique download codes.');
				return self::__ERROR_CUSTOM__;
			}

			if ( !is_numeric($data['d-count']) && !empty($data['d-count']) ) {
				$message = __('Maximum Download field must have a valid number or left empty');
				return self::__INVALID_FIELDS__;
			}

			// =================

			if (
				empty($data['file'])
				|| (
					is_array($data['file'])
					&& isset($data['file']['error'])
					&& $data['file']['error'] == UPLOAD_ERR_NO_FILE
				)
			) {
				if ($this->get('required') == 'yes') {
					$message = __('‘%s’ is a required field.', array($this->get('label')));

					return self::__MISSING_FIELDS__;
				}

				return self::__OK__;
			}

			// Its not an array, so just retain the current data and return
			if (is_array($data['file']) === false) {
				/**
				 * Ensure the file exists in the `WORKSPACE` directory
				 * @link http://symphony-cms.com/discuss/issues/view/610/
				 */
				$file = WORKSPACE . preg_replace(array('%/+%', '%(^|/)\.\./%'), '/', $data['file']);

				if (file_exists($file) === false || !is_readable($file)) {
					$message = __('The file uploaded is no longer available. Please check that it exists, and is readable.');

					return self::__INVALID_FIELDS__;
				}

				// Ensure that the file still matches the validator and hasn't
				// changed since it was uploaded.
				if ($this->get('validator') != null) {
					$rule = $this->get('validator');

					if (General::validateString($file, $rule) === false) {
						$message = __('File chosen in ‘%s’ does not match allowable file types for that field.', array(
							$this->get('label')
						));

						return self::__INVALID_FIELDS__;
					}
				}

				return self::__OK__;
			}

			if (is_dir(DOCROOT . $this->get('destination') . '/') === false) {
				$message = __('The destination directory, %s, does not exist.', array(
					'<code>' . $this->get('destination') . '</code>'
				));

				return self::__ERROR__;
			}

			else if (is_writable(DOCROOT . $this->get('destination') . '/') === false) {
				$message = __('Destination folder is not writable.')
					. ' '
					. __('Please check permissions on %s.', array(
						'<code>' . $this->get('destination') . '</code>'
					));

				return self::__ERROR__;
			}

			if ($data['file']['error'] != UPLOAD_ERR_NO_FILE && $data['file']['error'] != UPLOAD_ERR_OK) {
				switch ($data['error']) {
					case UPLOAD_ERR_INI_SIZE:
						$message = __('File chosen in ‘%1$s’ exceeds the maximum allowed upload size of %2$s specified by your host.', array($this->get('label'), (is_numeric(ini_get('upload_max_filesize')) ? General::formatFilesize(ini_get('upload_max_filesize')) : ini_get('upload_max_filesize'))));
						break;

					case UPLOAD_ERR_FORM_SIZE:
						$message = __('File chosen in ‘%1$s’ exceeds the maximum allowed upload size of %2$s, specified by Symphony.', array($this->get('label'), General::formatFilesize($_POST['MAX_FILE_SIZE'])));
						break;

					case UPLOAD_ERR_PARTIAL:
					case UPLOAD_ERR_NO_TMP_DIR:
						$message = __('File chosen in ‘%s’ was only partially uploaded due to an error.', array($this->get('label')));
						break;

					case UPLOAD_ERR_CANT_WRITE:
						$message = __('Uploading ‘%s’ failed. Could not write temporary file to disk.', array($this->get('label')));
						break;

					case UPLOAD_ERR_EXTENSION:
						$message = __('Uploading ‘%s’ failed. File upload stopped by extension.', array($this->get('label')));
						break;
				}

				return self::__ERROR_CUSTOM__;
			}

			// Sanitize the filename
			$data['file']['name'] = Lang::createFilename($data['file']['name']);

			if ($this->get('validator') != null) {
				$rule = $this->get('validator');

				if (!General::validateString($data['file']['name'], $rule)) {
					$message = __('File chosen in ‘%s’ does not match allowable file types for that field.', array($this->get('label')));

					return self::__INVALID_FIELDS__;
				}
			}

			return self::__OK__;

		}

		public function processRawFieldData($data, &$status, &$message=null, $simulate = false, $entry_id = null) {
			$status = self::__OK__;

			// begin enhancedtaglist import
			$tag_data = preg_split('/\\' . $this->get('delimiter') . '\s*/i', $data['keys'], -1, PREG_SPLIT_NO_EMPTY);
			$data['keys'] = array_map('trim', $tag_data);

			if(empty($data['keys'])) return;

			$data['keys'] = General::array_remove_duplicates($data['keys']);

			if ($this->get('ordered') != 'yes') {
				sort($data['keys']);
			}

			$result = array();
			foreach($data['keys'] as $value){
				$result['value'][] = $value;
				$result['handle'][] = Lang::createHandle($value);
				$result['d-count'][] = $data['d-count'];
			}

			if(empty($result)) {
				$result = array('value'=>array(0=>null),'handle'=>array(0=>null));
			}

			// end enhancedtaglist import

			// No file given, save empty data:
			if ($data['file'] === null) {
				foreach($result['value'] as $key => $value) {
					$result['file'][$key] = null;
					$result['mimetype'][$key] = null;
					$result['size'][$key] = null;
					$result['meta'][$key] = null;
				}
				return $result;
			}

			// Its not an array, so just retain the current data and return:
			if (is_array($data['file']) === false) {
				// Ensure the file exists in the `WORKSPACE` directory
				// @link http://symphony-cms.com/discuss/issues/view/610/
				$file = WORKSPACE . preg_replace(array('%/+%', '%(^|/)\.\./%'), '/', $data['file']);

				foreach($result['value'] as $key => $value) {
					$result['file'][$key] = $data['file'];
					$result['mimetype'][$key] = null;
					$result['size'][$key] = null;
					$result['meta'][$key] = null;
				}

				// Grab the existing entry data to preserve the MIME type and size information
				if (isset($entry_id)) {
					$row = Symphony::Database()->fetchRow(0, sprintf(
						"SELECT `file`, `mimetype`, `size`, `meta` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d",
						$this->get('id'),
						$entry_id
					));

					if (empty($row) === false) {
						foreach($result['value'] as $key => $value) {
							$result['file'][$key] = $row['file'];
							$result['mimetype'][$key] = $row['mimetype'];
							$result['size'][$key] = $row['size'];
							$result['meta'][$key] = $row['meta'];
						}
					}
				}

				// Found the file, add any missing meta information:
				if (file_exists($file) && is_readable($file)) {
				}

				// The file was not found, or is unreadable:
				else {
					$message = __('The file uploaded is no longer available. Please check that it exists, and is readable.');
					$status = self::__INVALID_FIELDS__;
				}

				return $result;
			}

			if ($simulate && is_null($entry_id)) return $data['file'];

			// Check to see if the entry already has a file associated with it:
			if (is_null($entry_id) === false) {
				$row = Symphony::Database()->fetchRow(0, sprintf(
					"SELECT * FROM `tbl_entries_data_%s` WHERE `entry_id` = %d LIMIT 1",
					$this->get('id'),
					$entry_id
				));

				$existing_file = '/' . trim($row['file'], '/');

				// File was removed:
				if (
					$data['file']['error'] == UPLOAD_ERR_NO_FILE
					&& !is_null($existing_file)
					&& is_file(WORKSPACE . $existing_file)
				) {
					General::deleteFile(WORKSPACE . $existing_file);
				}
			}

			// Do not continue on upload error:
			if ($data['file']['error'] == UPLOAD_ERR_NO_FILE || $data['file']['error'] != UPLOAD_ERR_OK) {
				return false;
			}

			// Where to upload the new file?
			$abs_path = DOCROOT . '/' . trim($this->get('destination'), '/');
			$rel_path = str_replace('/workspace', '', $this->get('destination'));

			// If a file already exists, then rename the file being uploaded by
			// adding `_1` to the filename. If `_1` already exists, the logic
			// will keep adding 1 until a filename is available (#672)
			if (file_exists($abs_path . '/' . $data['file']['name'])) {
				$extension = General::getExtension($data['file']['name']);
				$new_file = substr($abs_path . '/' . $data['file']['name'], 0, -1 - strlen($extension));
				$renamed_file = $new_file;
				$count = 1;

				do {
					$renamed_file = $new_file . '_' . $count . '.' . $extension;
					$count++;
				} while (file_exists($renamed_file));

				// Extract the name filename from `$renamed_file`.
				$data['file']['name'] = str_replace($abs_path . '/', '', $renamed_file);
			}

			// Sanitize the filename
			$data['file']['name'] = Lang::createFilename($data['file']['name']);
			$file = rtrim($rel_path, '/') . '/' . trim($data['file']['name'], '/');

			// Attempt to upload the file:
			$uploaded = General::uploadFile(
				$abs_path, $data['file']['name'], $data['file']['tmp_name'],
				0700
			);

			if ($uploaded === false) {
				$message = __(
					'There was an error while trying to upload the file %1$s to the target directory %2$s.',
					array(
						'<code>' . $data['file']['name'] . '</code>',
						'<code>workspace/' . ltrim($rel_path, '/') . '</code>'
					)
				);
				$status = self::__ERROR_CUSTOM__;

				return false;
			}

			// File has been replaced:
			if (
				isset($existing_file)
				&& $existing_file !== $file
				&& is_file(WORKSPACE . $existing_file)
			) {
				General::deleteFile(WORKSPACE . $existing_file);
			}

			// If browser doesn't send MIME type (e.g. .flv in Safari)
			if (strlen(trim($data['file']['type'])) == 0) {
				$data['file']['type'] = (
					function_exists('mime_content_type')
						? mime_content_type(WORKSPACE . $file)
						: 'application/octet-stream'
				);
			}
			foreach($result['value'] as $key => $value) {
				$result['file'][$key] =		$file;
				$result['size'][$key] =		$data['file']['size'];
				$result['mimetype'][$key] =	$data['file']['type'];
				$result['meta'][$key] =		serialize(self::getMetaInfo($file, $data['file']['type']));
			}
			return $result;
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null){
			// It is possible an array of NULL data will be passed in. Check for this.
			if(!is_array($data) || !isset($data['file']) || is_null($data['file'])){
				return;
			}

			if(is_array($data['file'])) $data['file'] = $data['file'][0];

			$item = new XMLElement('protected-file');
			$file = WORKSPACE . $data['file'];
			$item->setAttributeArray(array(
				'size' =>	(
								file_exists($file)
								&& is_readable($file)
									? General::formatFilesize(filesize($file))
									: 'unknown'
							),
			 	'path' =>	General::sanitize(
			 			 		str_replace(WORKSPACE, NULL, dirname(WORKSPACE . $data['file']))
			 				),
				'type' =>	$data['mimetype'][0]
			));

			$item->appendChild(new XMLElement('filename', General::sanitize(basename($data['file']))));

			$m = unserialize($data['meta'][0]);

			if(is_array($m) && !empty($m)){
				$item->appendChild(new XMLElement('meta', NULL, $m));
			}

			// begin enhancedtaglist import

			$list = new XMLElement('keys');

			if (!is_array($data['handle']) and !is_array($data['value'])) {
				$data['handle'] = array($data['handle']);
				$data['value'] = array($data['value']);
			}

			foreach ($data['value'] as $index => $value) {
				$attributes['handle'] = $data['handle'][$index];
				if ($this->get('ordered') == 'yes') {
					$attributes['order'] = $index + 1;
				}
				$list->appendChild(new XMLElement(
					'key', General::sanitize($value), $attributes
				));
			}

			$item->appendChild($list);
			// end enhancedtaglist import

			$wrapper->appendChild($item);

		}

		public function prepareTableValue($data, XMLElement $link=NULL, $entry_id = null){
			if(is_array($data['file'])) {
				$data['file'] = $data['file'][0];
			}
			if (!$file = $data['file']) {
				if ($link) return parent::prepareTableValue(null, $link, $entry_id);
				else return parent::prepareTableValue(null, $link, $entry_id);
			}

			if ($link) {
				$link->setValue(basename($file));
				$link->setAttribute('data-path', $file);

				return $link->generate();
			}

			else {
				$link = Widget::Anchor(basename($file), URL . '/workspace' . $file);
				$link->setAttribute('data-path', $file);

				return $link->generate();
			}

		}

	/*-------------------------------------------------------------------------
		Import:
	-------------------------------------------------------------------------*/

		public function getImportModes() {
			return array(
				'getValue' =>		ImportableField::STRING_VALUE,
				'getPostdata' =>	ImportableField::ARRAY_VALUE
			);
		}

		public function prepareImportValue($data, $mode, $entry_id = null) {
			$message = $status = null;
			$modes = (object)$this->getImportModes();

			if($mode === $modes->getValue) {
				return $data;
			}
			else if($mode === $modes->getPostdata) {
				return $this->processRawFieldData($data, $status, $message, true, $entry_id);
			}

			return null;
		}

	/*-------------------------------------------------------------------------
		Export:
	-------------------------------------------------------------------------*/

		/**
		 * Return a list of supported export modes for use with `prepareExportValue`.
		 *
		 * @return array
		 */
		public function getExportModes() {
			return array(
				'getFilename' =>	ExportableField::VALUE,
				'getObject' =>		ExportableField::OBJECT,
				'getPostdata' =>	ExportableField::POSTDATA
			);
		}

		/**
		 * Give the field some data and ask it to return a value using one of many
		 * possible modes.
		 *
		 * @param mixed $data
		 * @param integer $mode
		 * @param integer $entry_id
		 * @return array|string|null
		 */
		public function prepareExportValue($data, $mode, $entry_id = null) {
			$modes = (object)$this->getExportModes();

			// No file, or the file that the entry is meant to have no
			// longer exists.
			if (!isset($data['file']) || !is_file(WORKSPACE . $data['file'])) {
				return null;
			}

			if ($mode === $modes->getFilename) {
				return WORKSPACE . $data['file'];
			}

			if ($mode === $modes->getObject) {
				$object = (object)$data;

				if (isset($object->meta)) {
					$object->meta = unserialize($object->meta);
				}

				return $object;
			}

			if ($mode === $modes->getPostdata) {
				return $data['file'];
			}
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

//		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false) {
//			$field_id = $this->get('id');
//
//			if (preg_match('/^mimetype:/', $data[0])) {
//				$data[0] = str_replace('mimetype:', '', $data[0]);
//				$column = 'mimetype';
//			}
//			else if (preg_match('/^size:/', $data[0])) {
//				$data[0] = str_replace('size:', '', $data[0]);
//				$column = 'size';
//			}
//			else {
//				$column = 'file';
//			}
//
//			if (self::isFilterRegex($data[0])) {
//				$this->buildRegexSQL($data[0], array($column), $joins, $where);
//			}
//			else if ($andOperation) {
//				foreach ($data as $value) {
//					$this->_key++;
//					$value = $this->cleanValue($value);
//					$joins .= "
//						LEFT JOIN
//							`sym_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
//							ON (e.id = t{$field_id}_{$this->_key}.entry_id)
//					";
//					$where .= "
//						AND t{$field_id}_{$this->_key}.{$column} = '{$value}'
//					";
//				}
//			}
//			else {
//				if (!is_array($data)) $data = array($data);
//
//				foreach ($data as &$value) {
//					$value = $this->cleanValue($value);
//				}
//
//				$this->_key++;
//				$data = implode("', '", $data);
//				$joins .= "
//					LEFT JOIN
//						`sym_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
//						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
//				";
//				$where .= "
//					AND t{$field_id}_{$this->_key}.{$column} IN ('{$data}')
//				";
//			}

//			return true;
//		}

	/*-------------------------------------------------------------------------
		Sorting:
	-------------------------------------------------------------------------*/

//		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){
//			if(in_array(strtolower($order), array('random', 'rand'))) {
//				$sort = 'ORDER BY RAND()';
//			}
//			else {
//				$sort = sprintf(
//					'ORDER BY (
//						SELECT %s
//						FROM tbl_entries_data_%d AS `ed`
//						WHERE entry_id = e.id
//					) %s',
//					'`ed`.file',
//					$this->get('id'),
//					$order
//				);
//			}
//		}

	/*-------------------------------------------------------------------------
		Events:
	-------------------------------------------------------------------------*/

		public function getExampleFormMarkup(){
			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Input('fields['.$this->get('element_name').']', NULL, 'file'));

			return $label;
		}

		public function getParameterPoolValue(array $data, $entry_id=NULL) {
			return $this->get('id');
		}
	}
