<?php

	require_once(TOOLKIT . '/class.event.php');
	
	Class eventprotected_download extends Event{
		
		const ROOTELEMENT = 'force-download';
		
		public $eParamFILTERS = array(
			
		);
			
		public static function about(){
			return array(
				'name' => 'Protected Download',
				'author' => array(
					'name' => 'Colin Brogan',
					'website' => 'http://cbrogan.com/work'),
				'version' => '.1',
				'release-date' => '2013-07-04');
		}

		public static function getSource(){
			return false;
		}

		public static function allowEditorToParse(){
			return false;
		}

		public static function documentation(){
			return '
			<h3>Force Download</h3>
			<p>
				When this event is attached to a page, it enables the page to force a download.
				The download can be triggered by adding the parameter <code>file</code> to the URL:
			</p>
			<pre class="XML"><code>'.htmlentities('<a href="/download/?file=workspace/uploads/manual.pdf">Download manual</a>').'</code></pre>
			<h3>Security</h3>
			<p>
				To prevent that anyone can download any file from your website you have to set which folders
				are allowed for visitors to download files of. Otherwise evil people can download your config-settings
				for example simply by changing the URL in the browser bar to: <code>/download/?file=manifest/config.php</code>.
			</p>
			<p>
				To do this, you need to add a list of trusted locations to the \'Force Download\'-section on the preferences page.
			</p>
			<h3>Download the current page</h3>
			<p>
				You can also download the page itself, by adding the parameter <code>download</code> to the URL. The value of this parameter will be the name of the file. For example:
			</p>
			<pre class="XML"><code>'.htmlentities('<a href="/sheet/?download=sheet.xml">Download sheet in XML-format</a>').'</code></pre>
        ';
		}
		
		public function load()
		{
			

			// In case of a file:
			if(isset($_GET['code'])) {
				// include_once('event.force_download.config.php');

				$driver = ExtensionManager::getInstance('protected_download');
				/* @var $driver extension_force_download */

				$downloadCode = pathinfo($_GET['code']);
				$downloadCode = $downloadCode['filename'];

				$field_id = Symphony::database()->fetch('SELECT field_id from sym_fields_protected_download');
				$field_id = $field_id[0]['field_id'];

				$sql = sprintf(
						"SELECT file FROM sym_entries_data_%s WHERE value='%s'",
						$field_id,
						$downloadCode
					);

				// find the file path from the download-code
				$filename = Symphony::database()->fetch(
					$sql
				);



				$env = $this->_env;
				$wpath = $env['param']['workspace'];

				if(!empty($filename)) {
					$filename = 'workspace'.$filename[0]['file'];
					$file = explode('/',$filename);
					$file = $file[count($file)-1];
					if (file_exists($filename)) {
						// Determine the mimetype:
						if(function_exists('mime_content_type'))
						{
							$mimeType = mime_content_type($filename);
						} elseif(function_exists('finfo_open')) {
							$finfo = finfo_open(FILEINFO_MIME_TYPE);
							$mimeType = finfo_file($finfo, $filename);
						} else {
							$mimeType = "application/force-download";
						}
						header('Content-Description: File Transfer');
						header('Content-Type: '.$mimeType);
						header('Content-Disposition: attachment; filename='.$file);
						header('Content-Transfer-Encoding: binary');
						header('Expires: 0');
						header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
						header('Pragma: public');
						header('Content-Length: ' . filesize($filename));
						ob_clean();
						flush();
						readfile($filename);
						exit;
					} else {
						die('File does not exist: '.$filename.'!');
					}
				} else {
					die('Permission denied!');
				}
			}
		}
		
		protected function __trigger(){
			include(TOOLKIT . '/events/event.section.php');
			return $result;
		}		

	}

