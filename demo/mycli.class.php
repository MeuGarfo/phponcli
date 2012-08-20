<?php

class MyCLI extends PHPonCLI {

	/**
	 * (non-PHPdoc)
	 * @see PHPonCLI::getCurrentUserID()
	 */
	public function getCurrentUserID() {
		if (isset($_SERVER['USER'])) {
			return $_SERVER['USER'];
		}
		if (isset($_SERVER['LOGNAME'])) {
			return $_SERVER['LOGNAME'];
		}
		return 'anonymous';
	}

	/**
	 * (non-PHPdoc)
	 * @see PHPonCLI::checkPermission()
	 */
	public function checkPermission($userID, $object, $permission, $action) {
		if (!$userID) {
			return false;
		}
		if ($userID == 'anonymous') {
			return false;
		}
		return true;
	}

	/**
	 * Simply return a pong.
	 *
	 * The long description is displayed using help.
	 */
	public function handle_ping() {
		if (!$this->check()) {
			return false;
		}
		$this->output("Pong!");
		return true;
	}

	/**
	 * Display current user name.
	 */
	public function handle_whois() {
		if (!$this->check()) {
			return false;
		}
		$this->output("You are: " . $this->bold($this->orange($this->getCurrentUserID())));
		return true;
	}
	
	/**
	 * Display quotes from bash.org
	 */
	public function handle_bash($files, $cmd, array $args, array $argv) {
		
		if (!$this->check()) {
			return false;
		}
		
		if (!isset($args[0])) {
			$this->output("Usage: $cmd random          A random quote");
			$this->output("Usage: $cmd byid &lt;ID&gt;       The quote with the ID.");
			$this->output("Usage: $cmd [-n MAX] top    Top 100");
			return true;
		}
		
		switch ($args[0]) {
			
			// Random quotes
			case 'random' :
				if (!file_exists('./random.cache')) {
					$data = @file_get_contents('http://www.bash.org/?random');
					file_put_contents('./random.cache', $data);
				}
				else {
					$data = @file_get_contents('./random.cache');
				}
				if (!$data) {
					$this->output("Error: unable to fetch bash.org's URL");
					return false;
				}
				$matches = array();
				preg_match_all("/<p class=\"qt\">(.*?)<\/p>/si", $data, $matches);
				$this->output(strip_tags($matches[1][array_rand($matches[1], 1)]));
				break;
			
			// Quote by id
			case 'byid' :
				if (!isset($args[1])) {
					$this->output("Usage: $cmd byid &lt;ID&gt;");
					return false;
				}
				$data = @file_get_contents('http://www.bash.org/?' . $args[1]);
				if (stripos($data, 'does not exist')) {
					$this->output("Error: quote #{$args[1]} does not exists");
					return false;
				}
				$matches = array();
				preg_match("/<p class=\"qt\">(.*?)<\/p>/si", $data, $matches);
				echo strip_tags($matches[1]);
				break;
			
			// Top 100 quotes
			case 'top' :
				if (!file_exists('./top.cache')) {
					$data = @file_get_contents('http://www.bash.org/?top');
					file_put_contents('./top.cache', $data);
				}
				else {
					$data = @file_get_contents('./top.cache');
				}
				if (!$data) {
					$this->output("Error: unable to fetch bash.org's URL");
					return false;
				}
				$matches = array();
				preg_match_all("/<p class=\"qt\">(.*?)<\/p>/si", $data, $matches);
				$n = sizeof($matches[1]);
				if (isset($args['n'])) {
					$n = intval($args['n']);
				}
				foreach ($matches[1] as $i => $quote) {
					$this->output($this->bold("[" . ($i + 1) . "]"));
					$this->output(strip_tags($quote));
					if (--$n < 1) break;
				}
				break;
			
			// Invalid option
			default :
				$this->output("Error: invalid option '{$args[0]}'");
				return false;
			
		}
		
		return true;
	}
	
	public function handle_bash_autocomplete(array $args, array &$r) {
		if (sizeof($args) == 2) {
			$this->autocompleteFilter($args, array('random', 'byid', 'top'), $r);
		}
	}
	
	/**
	 * Calculate the md5 hash of a string
	 * 
	 * @package Encryption
	 */
	public function handle_md5($file, $cmd, array $params, array $args, $pipedData) {
		if (!$this->check()) {
			return false;
		}
		$this->output(md5(is_null($pipedData) ? implode(' ', $args) : implode(PHP_EOL, $pipedData)));
		return true;
	}
	
	/**
	 * Calculate the sha1 hash of a string
	 * 
	 * @package Encryption
	 */
	public function handle_sha1($file, $cmd, array $params, array $args, $pipedData) {
		if (!$this->check()) {
			return false;
		}
		$this->output(sha1(is_null($pipedData) ? implode(' ', $args) : implode(PHP_EOL, $pipedData)));
		return true;
	}
	
	/**
	 * Calculate the sha256 hash of a string
	 *
	 * @package Encryption
	 */
	public function handle_sha256($file, $cmd, array $params, array $args, $pipedData) {
		if (!$this->check()) {
			return false;
		}
		$this->output(hash('sha256', is_null($pipedData) ? implode(' ', $args) : implode(PHP_EOL, $pipedData)));
		return true;
	}

}

?>