<?php
/* 
 * Class MyJablotron - JA100
 * Michal Hajek <michal@hajek.net>
 * 27.1.2018
 */

class MyJablotron {

	private $curlHandle; // curl handle (keepalive)
	private $curlResponse; // output from curl
	private $debug = false; // enable or disable debug
	private $debugFile = 'php://stdout'; // debug file

	private $username; // username
	private $password; // password
	private $serviceId; // service ID

	private $errors; // errors


	public function __construct($username, $password) {
		$this->username = $username;
		$this->password = $password;
		if( ! defined('MY_COOKIE_FILE')) {
			define('MY_COOKIE_FILE', '/tmp/cookies.txt');
		}
		if(is_file(MY_COOKIE_FILE)) {
			unlink(MY_COOKIE_FILE);
		}
	}


	/**
	 * Cleanup curl and cookies file
	 */
	public function __destruct() {
		if(is_resource($this->curlHandle)) {
			curl_close($this->curlHandle);
			$this->curlHandle = null;
		}
		if(is_file(MY_COOKIE_FILE)) {
			unlink(MY_COOKIE_FILE);
		}
	}


	/**
	 * Set debug to STDOUT
	 * @param $enable - boolean Enable or disable debug
	 * @return null
	 */
	public function debug($enable = true, $file = 'php://stdout') {
		$this->debug = $enable;
		$this->debugFile = $file;
	}

	/**
	 * Login and get service Id
	 * @return boolean
	 */
	public function login() {
		$this->curl('https://www.jablonet.net/ajax/login.php', 'login='.urlencode($this->username).'&heslo='.urlencode($this->password).'&aStatus=200&loginType=Login');
		$output = $this->curlGetResponse('output');
		if($output !== false) {
			$json = json_decode($output, true);
			if(isset($json['status']) && $json['status'] == 200) {

				$this->curl('https://www.jablonet.net/cloud', null);
				$output = $this->curlGetResponse('output');
				if(preg_match('~www.jablonet.net/app/ja100\?service=([0-9]+)~', $output, $m)) {
					$this->serviceId = $m[1];

					$this->curl('https://www.jablonet.net/app/ja100?service='.$this->serviceId, null);
					return true;
				}
				else {
					$this->errors[] = 'Not found service';
					return false;
				}
			}
			else {
				// Wrong password
				$this->errors[] = 'Wrong password';
				return false;
			}
		}
		else {
			// Something wrong
			$this->errors[] = 'Something wrong';
			return false;
		}
	}



	/**
	 * Send PGM signal on section
	 * @param $sectionName - string Section name (ex. PGM_1)
	 * @return boolean
	 */
	public function sendPGMSignal($sectionName, $pin) {
		return $this->lock($sectionName, $pin);
	}


	/**
	 * Lock section
	 * @param $sectionName - string Section name (ex. STATE_3)
	 * @param $pin - string PIN
	 * @return boolean
	 */
	public function lock($sectionName, $pin) {
		$this->curl('https://www.jablonet.net/app/ja100/ajax/ovladani2.php', 'section='.urlencode($sectionName).'&status=1&code='.urlencode($pin));
		$output = $this->curlGetResponse('output');
		$json = json_decode($output, true);
		if(isset($json['result']) && ( $json['result'] == 0 || $json['result'] == 1 )) {
			return true;
		}
		else {
			$this->errors[] = 'Unable lock';
			return false;
		}
	}


	/**
	 * UnLock section
	 * @param $sectionName - string Section name (ex. STATE_3)
	 * @param $pin - string PIN
	 * @return boolean
	 */
	public function unlock($sectionName, $pin) {
		$this->curl('https://www.jablonet.net/app/ja100/ajax/ovladani2.php', 'section='.urlencode($sectionName).'&status=0&code='.urlencode($pin));
		$output = $this->curlGetResponse('output');
		$json = json_decode($output, true);
		if(isset($json['result']) && ( $json['result'] == 0 || $json['result'] == 1 )) {
			return true;
		}
		else {
			$this->errors[] = 'Unable unlock';
			return false;
		}
	}



	/**
	 * Get Keyboard
	 * @return array
	 */
	public function getKeyboards() {
		$this->curl('https://www.jablonet.net/app/ja100/ajax/stav.php', null);
		$output = $this->curlGetResponse('output');
		$json = json_decode($output, true);
		if(isset($json['status']) && $json['status'] == 200) {
			return $json['moduly'];
		}
		else {
			$this->errors[] = 'Unable get keyboards';
			return false;
		}
	}


	/**
	 * Get Section
	 * @return array
	 */
	public function getSection() {
		$this->curl('https://www.jablonet.net/app/ja100/ajax/stav.php', 'activeTab=section');
		$output = $this->curlGetResponse('output');
		$json = json_decode($output, true);
		if(isset($json['status']) && $json['status'] == 200) {
			return $json['sekce'];
		}
		else {
			$this->errors[] = 'Unable get sections';
			return false;
		}
	}

	/**
	 * Get PG Section
	 * @return array
	 */
	public function getPGM() {
		$this->curl('https://www.jablonet.net/app/ja100/ajax/stav.php', null);
		$output = $this->curlGetResponse('output');
		$json = json_decode($output, true);
		if(isset($json['status']) && $json['status'] == 200) {
			return $json['pgm'];
		}
		else {
			$this->errors[] = 'Unable get sections';
			return false;
		}
	}


	/**
	 * Return lock or unlock section
	 * @param $sectionName - string Section Name (ex. STATE_3)
	 * @return boolean - true (lock), false (unlock)
	 */
	public function checkStatusSection($sectionName) {
		$sections = $this->getKeyboards();
		foreach($sections as $keyboardIds) {
			foreach($keyboardIds as $a) {
				if($a['stateName'] == $sectionName) {
					return ($a['stav'] == 1 ? true : false);
				}
			}
		}
	}


	/**
	 * Get all statuses
	 * @return array
	 */
	public function getAllStatuses() {
		$this->curl('https://www.jablonet.net/app/ja100/ajax/stav.php', null);
		$output = $this->curlGetResponse('output');
		$json = json_decode($output, true);
		if(isset($json['status']) && $json['status'] == 200) {
			print_r($json);
		}
		else {
			$this->errors[] = 'Unable get status';
			return false;
		}
	}


	/**
	 * Get History without paging
	 * @return array
	 */
	public function getHistory() {
		$this->curl('https://www.jablonet.net/app/ja100/switch.php?switch=history', null);
		$output = $this->curlGetResponse('output');
		$output = explode("\n", $output);
		$data = Array();
		$i = 0;
		$day = '';
		foreach($output as $line) {
			// Day I.
			if(preg_match('~<span class="small_day">([0-9]+.*)</span>~', $line, $m)) {
				$day = $m[1];
				$data[$i]['day'] = $m[1];
			}
			// Day II.
			if(preg_match('~<span class="before_day">([0-9]+.*)</span>~', $line, $m)) {
				$day = $m[1];
				$data[$i]['day'] = $m[1];
			}

			// Fill missing day
			if( ! isset($data[$i]['day'])) {
				$data[$i]['day'] = $day;
			}


			// ON | OFF
			if(preg_match('~class=\'(OFF|ON|DISARM|ARM)\'~', $line, $m)) {
				$data[$i]['on_off'] = $m[1];
			}
			// Time
			if(preg_match('~<div class="time">([0-9]+:[0-9]+:?[0-9]*)~', $line, $m)) {
				$data[$i]['time'] = $m[1];
			}
			// Who activated
			if(preg_match('~<div class="name">([a-z]*)</div>~', $line, $m)) {
				$data[$i]['name'] = $m[1];
			}
			// Name section (place)
			if(preg_match('~<span class="span_place">(.*)</span>~', $line, $m)) {
				$data[$i]['place'] = $m[1];

				$i++;
			}
		}

		return $data;
	}


	/**
	 * Get Errors
	 * @return - array Errors
	 */
	public function getErrors() {
		return $this->errors;
	}




	/**
	 * Curl helper
	 * @param $url - string url address
	 * @param $postString - string post fields
	 * @return null
	 */
	private function curl($url, $postString) {
		if( ! is_resource($this->curlHandle)) {
			$this->curlHandle = curl_init();
		}

		if($this->debug) {
			file_put_contents($this->debugFile, date('Y-m-d H:i:s')." CURL: Connecting $url\n", FILE_APPEND);
		}

		curl_setopt($this->curlHandle, CURLOPT_URL, $url); 
		curl_setopt($this->curlHandle, CURLOPT_TIMEOUT, 60); 
		curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curlHandle, CURLOPT_COOKIESESSION, true);
		curl_setopt($this->curlHandle, CURLOPT_COOKIEJAR, MY_COOKIE_FILE); 
		curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, MY_COOKIE_FILE);
		if(strlen($postString)>0) {
			curl_setopt($this->curlHandle, CURLOPT_POST, 1);
			curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $postString);

			if($this->debug) {
				file_put_contents($this->debugFile, date('Y-m-d H:i:s')." CURL: Posting data $postString\n", FILE_APPEND);
			}
		}

		$this->curlResponse['output'] = curl_exec($this->curlHandle);
		$this->curlResponse['httpCode'] = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
		if($this->debug) {
			file_put_contents($this->debugFile, date('Y-m-d H:i:s')." CURL: HTTP CODE ".$this->curlResponse['httpCode']."\n", FILE_APPEND);
			file_put_contents($this->debugFile, date('Y-m-d H:i:s')." CURL: Response ".$this->curlResponse['output']."\n", FILE_APPEND);
			file_put_contents($this->debugFile, date('Y-m-d H:i:s')." ------------------------------------------------\n", FILE_APPEND);
		}
	}

	/**
	 * Curl get response output and http code
	 * @param $data - string output or httpCode
	 * @return string
	 */
	private function curlGetResponse($data=null) {
		if(is_null($data)) {
			return $this->curlResponse;
		}
		else {
			return $this->curlResponse[$data];
		}
	}


}


?>
