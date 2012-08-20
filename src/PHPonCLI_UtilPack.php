<?php

class PHPonCLI_UtilPack {
	
	/**
	 * @var PHPonCLI
	 */
	protected $cli;

	/**
	 * Constructor.
	 * 
	 * @param PHPonCLI $cli
	 */
	public function __construct(PHPonCLI $cli) {
		$this->cli = $cli;
	}
	
	/**
	 * TODO
	 * 
	 * @param PHPonCLI $cli
	 */
	public static function install(PHPonCLI $cli) {

		$utilPack = new PHPonCLI_UtilPack($cli);
		
		$cli->addCommand('grep', $utilPack, 'handle_grep');
		
		$cli->addCommand('echo', $utilPack, 'handle_echo');
		
		$cli->addCommand('print', $utilPack, 'handle_print');
		
	}
	
	/**
	 * Print lines matching a pattern.
	 *
	 * @package Utils
	 */
	public function handle_grep($file, $cmd, array $params, array $argv, $pipedData, array &$context) {
		if (!$this->cli->check()) {
			return false;
		}
		if (!is_array($pipedData)) {
			$this->cli->output("Error: $cmd command must be piped");
			return false;
		}
		$match = implode(' ', $params);
		foreach ($pipedData as $str) {
			if (stripos($str, $match) !== false) {
				$this->cli->output(str_replace($match, $this->cli->bold($this->cli->red($match)), $str));
			}
		}
		return true;
	}

	/**
	 * Just display a string.
	 *
	 * @package Utils
	 */
	public function handle_echo($file, $cmd, array $params, array $args, $pipedData) {
		if (!$this->cli->check()) {
			return false;
		}
		$this->cli->output(is_null($pipedData) ? implode(' ', $args) : implode(PHP_EOL, $pipedData));
		return true;
	}
	
	/**
	 * @alias echo
	 */
	public function handle_print($file, $cmd, array $params, array $args, $pipedData) {
		return $this->handle_echo($file, $cmd, $params, $args, $pipedData);
	}
	
}

?>