<?php

/**
 * A single abstract class to create a Command Line Interface (CLI) with PHP.
 * 
 * - Full featured opts/args parser
 * - Simply add commands by adding a new method to the class
 * - Spelling suggestions
 * - Output colored/styled messages
 * - Command auto-completion and glob support
 * - Help generated from methods documentations
 * - Ability to "pipe" the output of one command in as input to another
 * 
 * @author ted@evolya.fr
 * @link http://blog.evolya.fr/labs/phponcli/
 */
abstract class PHPonCLI {

	/**
	 * Revision version.
	 * 
	 * @var string
	 */
	const VERSION = '1.14';
	
	/**
	 * Don't decorate outputs.
	 * 
	 * @var int
	 */
	const DECORATION_NONE  = 10;
	
	/**
	 * Decorate outputs with HTML markup.
	 * 
	 * @var int
	 */
	const DECORATION_HTML  = 11;
	
	/**
	 * Decorate outputs with AINSI codes.
	 * 
	 * @var int
	 * @see http://pueblo.sourceforge.net/doc/manual/ansi_color_codes.html
	 */
	const DECORATION_AINSI = 12;
	
	/**
	 * Result of the last execution.
	 * @var mixed[]
	 */
	public $result = null;
	
	/**
	 * Enable command piping.
	 * @var boolean
	 */
	public $enablePipes = true;
	
	/**
	 * List of available commands.
	 * @var mixed[]
	 */
	protected $commands = array();
	
	/**
	 * Decoration type of returned content.
	 * @var Closure|null
	 */
	protected $decorator = null;
	
	/**
	 * TODO
	 * @var boolean
	 */
	protected $printOuputs = true;
	
	/**
	 * TODO
	 * @var string[]
	 * @see PHPonCLI::$printOuputs
	 */
	protected $output = array();
	
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Internal commands initialization
		foreach (get_class_methods($this) as $method) {
			$name = substr($method, 0, 7);
			if ($name !== 'handle_' || substr($method, -13) == '_autocomplete') {
				continue;
			}
			$this->addCommand(substr($method, 7), $this, $method);
		}
	}
	
	/**
	 * Add a command.
	 * 
	 * @param string $name
	 * @param object $class
	 * @param string $method
	 * @throws InvalidArgumentException
	 */
	public function addCommand($name, $class, $method) {
		if (!is_object($class)) {
			throw new InvalidArgumentException('Object expected', 500);
		}
		$this->commands[$name] = array(
			array($class, $method),
			self::get_method_doc($class, $method)
		);
	}
	
	
	#####
	#####   T O   O V E R R I D E
	#####
	
	
	/**
	 * 
	 * 
	 * @return null|string|int|mixed
	 */
	public abstract function getCurrentUserID();
	
	/**
	 * 
	 * @param string|int|mixed $userID
	 * @param string $object
	 * @param string $permission
	 * @param string $action
	 * @return boolean
	 */
	public abstract function checkPermission($userID, $object, $permission, $action);
	
	/**
	 * Write a string to the standard output. 
	 * 
	 * @param string $str The string to write.
	 * @param boolean $nl Append with a new line.
	 * @stdout string
	 */
	public function output($str, $nl = true) {
		if ($this->printOuputs) {
			echo $str . ($nl ? PHP_EOL : '');
		}
		else {
			if ($nl || sizeof($this->output($str) === 0)) {
				$this->output[] = $str;
			}
			else {
				$this->output[sizeof($this->output) - 1] .= $str;
			}
		}
	}
	
	
	#####
	#####   E X E C U T I O N
	#####
	
	
	/**
	 * Execute a command.
	 * 
	 * The $args var can be a string or an array of strings.
	 * 
	 * The method returns a boolean to indicate if the execution
	 * is successful. The full results and the return code are
	 * stored in $this->result variable.
	 * 
	 * All data are sent to standard output, even error messages.
	 * If the requested command is not found, suggestions for command
	 * names will be proposed using metaphone and levenshtein
	 * approximations.
	 * 
	 * To easily integrate a new order, just create a method with
	 * prefixed by "handle_". This method must be documented to manage
	 * ACLs. For details, see the documentation of "handle_help" method.
	 * 
	 * An ACL verification system is turned on if the method
	 * corresponding to the invoked command is documented, and declares
	 * a "@permission" doccomment. In this case, the method $this->checkACL()
	 * is called. This method is abstract by default and must be implemented.
	 * 
	 * The command format should be:
	 *  <COMMAND_NAME> [-option VALUE] [--longoption=VALUE] [ARGS...]
	 *  
	 * Important:
	 * The first argument of $argv must be the name of the executed PHP
	 * script. This is the default behavior when using PHP in CLI: the
	 * path to the script is placed first in $argv. To execute a command
	 * directly without going through the CLI, you must manually add the path.
	 * Eg. $shell->exec(__FILE__ . ' ' . $myCommandString);
	 * 
	 * @param string|string[] $argv
	 * @param boolean $return If you would like to capture the output of exec(),
	 * 	use the return parameter. When this parameter is set to TRUE,
	 *  exec() will return the information rather than print it.
	 * @param string[]|null Data piped to the command.
	 * @return boolean|string[] According to $return
	 * @stdout string
	 * @throws Exception
	 */
	public function exec($argv, $return = false, $pipedData = null) {
		
		// Set print flag
		$this->printOuputs = !$return;

		// Reset output array
		$this->output = array(); 
		
		// Default state
		$this->result = array(
			'failure' => false,
			'returnCode' => 0
		);

		// Split arguments
		if (!is_array($argv)) {
			$argv = self::split_commandline("$argv");
		}

		// Check arguments
		if (sizeof($argv) < 2) {

			// Save the error state
			$this->result = array(
				'failure' => true,
				'errorMsg' => 'Invalid query string (arguments missing)',
				'returnCode' => 50
			);
			
			// Display the error message
			$this->output("Usage: {$argv[0]} CMD [PARAM...]");
					
		}
		
		else {
			
			// Pipes array
			$piped = array();
			
			// Explode piped parts
			if ($this->enablePipes && in_array('|', $argv)) {
				// Split query string
				foreach (array_reverse(array_keys($argv, '|')) as $key) {
					$pipe = array_slice($argv, $key, null, true); // get pipe part
					array_splice($argv, $key); // remove the part
					array_shift($pipe); // remove pipe caractere
					$piped[] = $pipe;
				}
			}
			
			// Debug
			//echo '[Execute =>'; foreach ($argv as $t) echo ' ' . escapeshellarg("$t"); echo ']' . PHP_EOL;
	
			// Parse parameters
			$params = self::parse_parameters($argv);
	
			// Shift parameters
			$file = array_shift($params);
			$cmd  = array_shift($params);
			array_shift($argv);
			array_shift($argv);

			// Search for command
			//if (!method_exists($this, "handle_$cmd")) {
			if (!array_key_exists($cmd, $this->commands)) {
				
				// Save the error state
				$this->result = array(
					'failure' => true,
					'errorMsg' => "Command not found: $cmd",
					'returnCode' => 54
				);
				
				// Command is not found
				$this->output("Command not found: " . $this->bold($cmd));
				
				// Suggestions array
				$suggest = array();
				
				// Calculate command mataphone key
				$metaphone = metaphone($cmd);
				
				// Fetch available commands
				foreach ($this->commands as $method => $info) {
					
					// Security: don't provide commands the user do not have the
					// right to use.
					if (isset($info['@permission'])) {
						if (!$this->checkPermission($userID, $cmd, $info['@permission'][0], 'list')) {
							continue;
						}
					}
					
					// Syntactic correspondence
					// @see https://en.wikipedia.org/wiki/Levenshtein_distance
					if (levenshtein($cmd, $method) === 1) {
						$suggest[] = $method;
					}
					
					// Sound correspondence
					// @see https://en.wikipedia.org/wiki/Metaphone
					else if (metaphone($method) == $metaphone) {
						$suggest[] = $method;
					}
					
				}
				
				// Display suggestions
				if (sizeof($suggest) > 0) {
					$this->output("Did you mean '" . implode("' or '", $suggest) . "' ?");
				}
				
			}
			
			else {
	
				// Before starting the command, disable outputs and turn styles off
				if (sizeof($piped) > 0) {

					// Disable outputs
					$this->printOuputs = false;
					
					// Before piping, disable styles
					$decorator = $this->decorator;
					$this->decorator = null;
				
				}
				
				// Context
				$context = array();
				
				// Get data
				list($callback, $doc) = $this->commands[$cmd];
				
				// Execute the command
				try {
					$result = call_user_func_array(
						$callback,
						array($file, $cmd, $params, $argv, $pipedData, &$context)
					);
				}
				// Catch failures
				catch (Exception $ex) {
					
					// Save the error state
					$this->result = array(
						'failure' => true,
						'errorMsg' => $ex->getMessage(),
						'errorEx' => $ex,
						'returnCode' => 51
					);
					
					// Output the error message
					$this->output("$cmd: {$ex->getMessage()}");
					
					// Return the failure flag
					return false;
				}
				
				// Check result
				if (is_bool($result)) {
					$this->result['failure'] = !$result;
					$this->result['returnCode'] = $result ? 0 : 1;
				}
				else if (is_int($result)) {
					$this->result['failure'] = $result !== 0;
					$this->result['returnCode'] = $result;
				}
				else if (is_string($result)) {
					$this->result['failure'] = false;
					$this->result['returnCode'] = 0;
					$this->result['returnValue'] = $result;
				}
				else {
					$this->result['failure'] = true;
					$this->result['returnCode'] = 1;
				}
				
				// Merge result with context variable, which allows the
				// contents of the result to be overrided.
				$this->result = array_merge($this->result, $context);
			
			}
			
		}
		
		// Piping process
		if (isset($piped) && sizeof($piped) > 0) {

			// If the first command failed, all outputs are stored and should be printed 
			if ($this->result['failure']) {
				echo implode(PHP_EOL, $this->output) . PHP_EOL;
				// Disable piping
				return false;
			}
			
			// Fetch pipe parts
			while ($next = array_pop($piped)) {
				// Debug
				//echo 'Pipe to ' . $next[0] . ': ' . print_r($this->output, true). PHP_EOL;
				// Prepend with file path
				array_unshift($next	, $file);
				// Restore decorator for last piped command
				if (sizeof($piped) < 1 && isset($decorator)) {
					$this->decorator = $decorator;
				}
				// Execute next command
				$result = $this->exec($next, true, $this->output);
				// Failure
				if (!is_array($result)) {
					// Stop piping
					break;
				}
			}
			
			// Print outputs
			if (!$return) {
				echo implode(PHP_EOL, $this->output) . PHP_EOL;
			}
		}
		
		// Return the output array
		if ($return) {
			return $this->output;
		}
		// Return success or failure flag
		else {
			return !$this->result['failure'];
		}

	}
	
	
	#####
	#####   V E R I F I C A T I O N   P R O C E S S
	#####

	/**
	 * This method whill automatically check what permissions are required and
	 * the options passed when calling a command. It must be called in
	 * "handle_" methods that manage commands.
	 * 
	 * Used without arguments, this method will automatically set options and
	 * permissions required by the command, and verify from the data supplied
	 * to the "handle_" method of the command.
	 * 
	 * /**
	 *  * @permission hello
	 *  * @options hi
	 *  * /
	 * function handle_hello($file, $cmd, $params, $argv) {
	 * 		if ($this->check()) {
	 * 			return false;
	 * 		}
	 * 		$this->output(isset($params['hi']) ? 'Hi!' : 'Hello!');
	 * 		return true; 
	 * }
	 * 
	 * @param string $allowedOptions Options list, separated by coma.
	 * @param string $requiredPermission ACL permission required to execute the command.
	 * @return boolean 
	 */
	public function check($allowedOptions = '', $requiredPermission = null) {
		
		// Invocation data
		$traces = debug_backtrace();
		$traces = $traces[1];
		$cmd = substr($traces['function'], 7);
		$cmdinput = $traces['args'][1];
		$params = $traces['args'][2];
		$argv = $traces['args'][3];
		
		// No arguments passed to this methods : automatic mode
		if ($allowedOptions == '' || !is_string($requiredPermission)) {

			// Get method documentation
			list($callback, $doc) = $this->commands[$cmd];

			// If a documenation is provided
			if (is_array($doc)) {
				if (array_key_exists('@permission', $doc)) {
					$requiredPermission = @$doc['@permission'][0];
				}
				if (array_key_exists('@options', $doc)) {
					$allowedOptions = @$doc['@options'][0];
				}
			}
		}
		
		// Check permission
		if (is_string($requiredPermission)) {
			
			// Check 
			$ok = $this->checkPermission(
				$this->getCurrentUserID(),	// Current user ID
				$cmd,						// Command name (the object)
				$requiredPermission,		// Required permission
				'exec'						// Action
			);
			
			// Failure
			if (!$ok) {
				
				// Save the error state
				$this->result = array(
					'failure' => true,
					'errorMsg' => "Required permission: $requiredPermission",
					'returnCode' => 52
				);
			
				// Output a message
				$this->output("Required permission: " . $this->bold($requiredPermission));
				
				// Return the failure flag
				return false;
				
			}
		}
		
		// Check params
		if (!$this->checkOptions($cmd, $params, $allowedOptions)) {
			// Return the failure flag
			return false;
		}
		
		// Return the success flag
		return true;
	}
	
	
	/**
	 * Check command options.
	 * 
	 * @param string $cmd
	 * @param string[] $params
	 * @param string[]|string $modifiers
	 * @return boolean
	 */
	public function checkOptions($cmd, array $params, $options) {
		
		// Split options list
		if (!is_array($options)) {
			$options = preg_split('/\s+/', "$options");
		}
		
		// Fetch parameters
		foreach ($params as $k => $v) {
			
			// Ignore non-option parameters
			if (is_int($k)) {
				continue;
			}

			// Unauthorized option
			if (!in_array($k, $options)) {
				
				// Save the error state
				$this->result = array(
					'failure' => true,
					'errorMsg' => "Invalid option: $k",
					'returnCode' => 53
				);
				
				// Display an error message
				$this->output("$cmd: invalid option '" . $this->bold($k) . "'");
				
				// Return a failure flag
				return false;
			}
		}
		
		// Return a success flag
		return true;
	}
	
	
	#####
	#####   A U T O - C O M P L E T E
	#####
	
	
	/**
	 * 
	 * @param string $str
	 * @return string[]
	 */
	public function autocomplete($str) {
		
		// Explodes command expression
		$args = self::split_commandline("$str", false);

		// Output array
		$r = array();

		// Empty argument
		if (sizeof($args) < 1) {
			return $r;
		}

		// One argument only = it's a command name
		else if (sizeof($args) === 1) {

			$args = trim($args[0]);
			$length = strlen($args);
			
			if (empty($args)) {
				return $r;
			}
			
			foreach ($this->commands as $method => $info) {

				// Command begins with the required chain
				if (substr($method, 0, $length) == $args && strlen($method) > $length) {

					// Permissions verification
					if (is_array($info) && isset($info['@permission'])) {
						
						// Forbidden : switch this command
						if (!$this->checkPermission(
							$this->getCurrentUserID(),
							$method,
							$info['@permission'][0],
							'list'
						)) {
							continue;
						}
						
					}
					
					// Adds the command in the returned array, but only the characters
					// that complement what has been sent.
					$r[] = !$length ? $method : substr($method, $length);
					
				}
			}
		}

		// Several arguments = looking at whether there is a method to handle the autocompletion
		else if (method_exists($this, 'handle_' . $args[0] . '_autocomplete')) {
			
			// Check ACLs first
			$allowed = true;
			if (isset($this->commands[$args[0]])) {
				
				// Command exists and has a permission field
				if (isset($this->commands[$args[0]]['@permission'])) {
					
					$perm = $this->commands[$args[0]]['@permission'][0];
					
					if (!$this->checkPermission(
						$this->getCurrentUserID(),
						$args[0],
						$perm,
						'autocomplete'
					)) {
						$allowed = false;
					}
					
				}
			}
			
			// If user is allowed to run this command, autocomplete method is called 
			if ($allowed) {
				try {
					call_user_func_array(
						array($this, 'handle_' . $args[0] . '_autocomplete'),
						array($args, &$r)
					);
				}
				catch (Exception $ex) {
					trigger_error(get_class($ex) . " thrown in PHPonCLI::autocomplete()", E_USER_WARNING);
				}
			}
		}
		
		// Now we are going to pass on all outputs, and see if they start with
		// the same string. In other words, the partial completion.
		if (sizeof($r) > 1) {
			$length = 1;
			$prefix = '';
			while (true) {
				foreach ($r as $rs) {
					if ($length > strlen($rs)) {
						break 2;
					}
					$pre = substr($rs, 0, $length);
					if (strlen($prefix) < $length) {
						$prefix = $pre;
					}
					else if ($pre != $prefix) {
						break 2;
					}
				}
				$length++;
			}
			
			// If a common prefix has been noticed, it is added first to the list
			// of suggested in the list, and round pipes.
			// Then, the client has to manage the partial completion.
			if (strlen($prefix) > 1) {
				// We put pipes to indicate that the string is not terminated
				array_unshift($r, '|' . substr($prefix, 0, -1) . '|');
			}
		}

		// At the end, we return the output list
		return $r;
	
	}
	
	/**
	 * Autocompletion filter.
	 *
	 * This method facilitates the modification of the variable return
	 * type methods 'handle_*_autocomplete'.
	 *
	 * @param string|string[] $needle The word used to prefix comparison.
	 * @param string[] $haystack List of items to filter.
	 * @param string[] &$r Output array.
	 */
	public function autocompleteFilter($needle, array $haystack, array &$r) {
		if (is_array($needle)) {
			$needle = '' . array_pop($needle);
		}
		$length = strlen($needle);
		foreach ($haystack as $n) {
			if ($length === 0 || substr($n, 0, $length) === $needle) {
				$r[] = substr($n, $length);
			}
		}
	}
	
	
	#####
	#####   H E L P
	#####
	
	
	/**
	 * Display help about commands.
	 * 
	 * @usage ${cmdname} [command]
	 * @hidden This command is hidden from help list.
	 */
	public function handle_help($file, $cmd, array $params, array $argv, $pipedData, array &$context) {
	
		// Verification of parameters
		if (!$this->checkOptions($cmd, $params, '')) {
			return false;
		}
	
		// Help for a particular command
		if (isset($argv[0])) {
				
			// Command not found
			if (!array_key_exists($argv[0], $this->commands)) {
				$this->output("Command not found: " . $this->bold($argv[0]));
				return false;
			}
			
			// Get command documentation
			list($callback, $doc) = $this->commands[$argv[0]];
			
			// No help available
			if (!is_array($doc)) {
				$this->output("No help available for: " . $this->bold($argv[0]));
				return true;
			}
				
			// Check ACLs
			if (isset($doc['@permission'])) {
				
				if (!$this->checkPermission(
					$this->getCurrentUserID(),
					$argv[0],
					$doc['@permission'][0],
					'list')
				) {
					
					$this->output("Required permission: " . $this->bold($doc['@permission'][0]));
					return false;
					
				}

			}
			
			// Output string
			$help = '';
			
			// Usage(s)
			if (isset($doc['@usage'])) {
				foreach ($doc['@usage'] as $usage) {
					$help .= 'Usage: ' . $usage . PHP_EOL;
				}
			}
			
			// Documentation body
			$help .= implode(PHP_EOL, $doc['@doc']);
			
			// Replace
			$help = str_replace(
					array('${cmdname}'),
					array($argv[0]),
					$help
			);
			
			// Output the help
			$this->output($help);

			return true;
		}
	
		// Packages array
		$packages = array('Commands' => array());
		
		// Aliases array
		$aliases = array();
	
		// Fetch all commands
		foreach ($this->commands as $func => $data) {
			
			// Get command data
			list($callback, $doc) = $data;

			// This function is currently not documented: not displayed
			if (!is_array($doc)) {
				trigger_error("Method 'handle_$func' is not documented.", E_USER_NOTICE);
				continue;
			}
	
			// This method should not be displayed
			if (isset($doc['@hidden'])) {
				continue;
			}
	
			// Security: verifies that the user have permissions on this command
			if (isset($doc['@permission'])) {
				
				if (!$this->checkPermission(
					$this->getCurrentUserID(),
					$func,
					$doc['@permission'][0],
					'list')
				) {
					
					// Switch this command
					continue;
					
				}

			}
	
			// Package name (name of the stack)
			$package = isset($doc['@package']) ? $doc['@package'][0] : 'Commands';

			// Create stack
			if (!isset($packages[$package])) {
				$packages[$package] = array();
			}
	
			// Add the command to the stack
			$packages[$package][$func] = $doc;
	
		}
	
		// Managing aliases
		// Fetch packages
		foreach ($packages as $name => $commands) {

			// Fetch commands
			foreach ($commands as $func => $doc) {
				
				// This command in an alias
				if (isset($doc['@alias'])) {
					
					// Retrieves the name of the target command
					$alias = $doc['@alias'][0];
					
					// Targeted command is found
					if (array_key_exists($alias, $this->commands)) {
						
						// Create alias table
						if (!isset($aliases[$alias])) {
							$aliases[$alias] = array();
						}
						
						// Bind alias to targeted command
						$aliases[$alias][] = $func;
					}
					
					// Else, display a notice
					else {
						trigger_error("Alias '$alias' not found, declared by '$func'", E_USER_NOTICE);
					}
					
					// Remove the alias
					unset($packages[$name][$func]);
				}
			}
		}
		
		// Global help : commands list
		$this->output('Available commands are:');
		
		// Affichage des commandes
		foreach ($packages as $name => $commands) {
			
			if (sizeof($commands) < 1) {
				continue;
			}
			
			$this->output('');
			$this->output($this->underline($name));
			
			ksort($commands, SORT_STRING);
			
			foreach ($commands as $func => $cmd) {
				
				$alias = isset($aliases[$func]) ? ' | ' . implode(' | ', $aliases[$func]) : '';
				$pad = str_repeat(' ', 30 - strlen($alias) - strlen($func));
				$doc = isset($cmd['@doc'][0]) ? $cmd['@doc'][0] : '';
				$this->output(' ' . $this->bold($func) . "$alias$pad $doc");
				
			}
			
		}
	
		$this->output('');
		$this->output('Additionaly, a `cls` command should be provided by your shell.');
		return true;
	}
	
	/**
	 * Handle autocompletion for help command.
	 * 
	 * @param string[] $args
	 * @param string[] $suggestions
	 */
	public function handle_help_autocomplete(array $args, array &$suggestions) {
		
		if (sizeof($args) === 2) {
		
			$commands = array();
			
			foreach ($this->commands as $func => $data) {

				list($callback, $doc) = $data;
				
				if (isset($doc['@permission'])) {
					
					if (!$this->checkPermission(
						$this->getCurrentUserID(),
						$func,
						$doc['@permission'][0],
						'list')
					) {
						
						continue;
						
					}
					
				}
				
				$commands[] = $func;
				
			}
			
			if (sizeof($commands) > 0) {
				$this->autocompleteFilter(
					$args[1],
					$commands,
					$suggestions
				);
			}
				
		}
	}
	
	
	#####
	#####   D E C O R A T I O N
	#####
	
	
	/**
	 * Set output decorator.
	 * 
	 * @param int $value
	 * @return void
	 */
	public function setDecorator($value) {
	
		// Switch decorator type
		switch ($value) {
	
			// AINSI decoration
			case self::DECORATION_AINSI :
			case 'AINSI' :
				$this->decorator = function ($str, $style) {
					switch ($style) {
						case 'bold'     : return "\033[1m$str\033[22m";
						case 'underline': return "\033[4m$str\033[24m";
						case 'red'      : return "\033[31m$str\033[39m";
						case 'green'    : return "\033[32m$str\033[39m";
						case 'blue'     : return "\033[34m$str\033[39m";
						case 'orange'   : return "\033[33m$str\033[39m";
						case 'ok'       : return "[\033[1m\033[32m$str\033[39m\033[22m]";
						case 'failure'  : return "[\033[1m\033[31m$str\033[39m\033[22m]";
						case 'warning'  : return "[\033[1m\033[33m$str\033[39m\033[22m]";
						default        : return $str;
					}
				};
				break;
	
			// HTML decoration
			case self::DECORATION_HTML :
			case 'HTML' :
				$this->decorator = function ($str, $style) {
					switch ($style) {
						case 'bold'     : return "<b>$str</b>";
						case 'underline': return "<u>$str</u>";
						case 'italic'   : return "<i>$str</i>";
						case 'red'      : return "<span style='color:red'>$str</span>";
						case 'green'    : return "<span style='color:green'>$str</span>";
						case 'blue'     : return "<span style='color:blue'>$str</span>";
						case 'orange'   : return "<span style='color:orange'>$str</span>";
						case 'ok'       : return "<b>[<span style='color:green'>$str</span>]</b>";
						case 'failure'  : return "<b>[<span style='color:red'>$str</span>]</b>";
						case 'warning'  : return "<b>[<span style='color:orange'>$str</span>]</b>";
						default        : return $str;
					}
				};
				break;
	
			// No decorator
			case 'NONE' :
			default :
				$this->decorator = null;
				break;
	
		}
	
	}
	
	/**
	 * Apply a style to the given string.
	 * 
	 * @param string $str
	 * @param string $style
	 * @return string
	 */
	protected function decorate($str, $style) {
		$decorator = $this->decorator;
		return $decorator($str, $style);
	}
	
	/**
	 * Bold a text.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function bold($str) {
		return $this->decorator ? $this->decorate($str, 'bold') : $str;
	}
	
	/**
	 * Underline a text.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function underline($str) {
		return $this->decorator ? $this->decorate($str, 'underline') : $str;
	}
	
	/**
	 * Color a text in red.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function red($str) {
		return $this->decorator ? $this->decorate($str, 'red') : $str;
	}
	
	/**
	 * Color a text in green.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function green($str) {
		return $this->decorator ? $this->decorate($str, 'green') : $str;
	}
	
	/**
	 * Color a text in blue.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function blue($str) {
		return $this->decorator ? $this->decorate($str, 'blue') : $str;
	}
	
	/**
	 * Color a text in orange.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function orange($str) {
		return $this->decorator ? $this->decorate($str, 'orange') : $str;
	}
	
	/**
	 * Highlight a success text message.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function ok($str = 'OK') {
		return $this->decorator ? $this->decorate($str, 'ok') : "[$str]";
	}
	
	/**
	 * Highlight a failure text message.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function failure($str = 'FAILURE') {
		return $this->decorator ? $this->decorate($str, 'failure') : "[$str]";
	}
	
	/**
	 * Highlight a warning text message.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function warn($str = 'WARNING') {
		return $this->decorator ? $this->decorate($str, 'warning') : "[$str]";
	}
	
	
	#####
	#####   S T A T I C   U T I L S
	#####
	
	
	/**
	 * Apply trim on arrays
	 * 
	 * @param string[] $array
	 * @return string[]
	 */
	public static function atrim(array $array) {
		if (is_array($array)) {
			foreach ($array as &$a) {
				if (is_array($a)) {
					// Right
					for ($i = sizeof($a) - 1; $i >= 0; $i--) {
						if ($a[$i] == '') unset($a[$i]);
						else break;
					}
					// Left
					foreach ($a as $k => $v) {
						if ($v == '') unset($a[$k]);
						else break;
					}
					$a = array_values($a);
				}
			}
		}
		return $array;
	}
	
	/**
	 * Explode a chain of command.
	 *
	 * This method ensures that:
	 *  - The space between the commands may be several white
	 *  - Parameters with spaces between quotes are supported
	 *    and parsed as a single argument.
	 *
	 * @param string $str
	 * @param boolean $trim
	 * @return string[]
	 */
	public static function split_commandline($str, $trim = true) {
		// Tokens are separated with white characters, they gather by standardizing
		// the separation of tokens with a single space, then we use a function that will
		// explode string taking into account the chains.
		return self::split_str(implode(' ', preg_split('/\s+/', $trim ? trim("$str") : "$str")), ' ');
	}
	
	/**
	 * Parses $GLOBALS['argv'] for parameters and assigns them to an array.
	 *
	 * Supports:
	 * -e
	 * -e <value>
	 * --long-param
	 * --long-param=<value>
	 * --long-param <value>
	 * <value>
	 *
	 * Based on parseParameters() function published on PHP.net by mbirth@webwriters.de
	 * @see http://fr.php.net/manual/en/function.getopt.php#83414
	 *
	 * @param array $params List of parameters. Left null mean $GLOBALS['argv'].
	 * @param array $all If FALSE, stop parsing parameters after the first command met.
	 * @param array $noopt List of parameters without values.
	 * @return string[]
	 */
	public static function parse_parameters($params = null, $all = true, $noopt = array(), $allowShort = true) {
	
		// Output array
		$result = array();
	
		// Global $argv
		if (!is_array($params)) {
			$params = $GLOBALS['argv'];
		}
	
		// Stop option parsing after the first non-option parameter met
		$stopOpt = false;
	
		// Could use getopt() here (since PHP 5.3.0), but it doesn't work relyingly
		reset($params);
		while (list($tmp, $p) = each($params)) {
			
			if (!is_string($p)) {
				$result[] = $p;
				continue;
			}
			
			if (strlen($p) < 1) continue;
				
			if ($p{0} == '-' && ($all || !$stopOpt) && strlen($p) > 1) {
				$pname = substr($p, 1);
				$value = true;
				if ($pname{0} == '-') {
					// long-opt (--<param>)
					$pname = substr($pname, 1);
					if (strpos($p, '=') !== false) {
						// value specified inline (--<param>=<value>)
						list($pname, $value) = explode('=', substr($p, 2), 2);
					}
				}
				// check if next parameter is a descriptor or a value
				if ($allowShort) {
					$nextparm = current($params);
					if (!in_array($pname, $noopt) && $value === true && $nextparm !== false && $nextparm{0} != '-') {
						list($tmp, $value) = each($params);
					}
				}
				$result[$pname] = $value;
			}
			else {
				$stopOpt = true;
				// param doesn't belong to any option
				$result[] = $p;
			}
		}
	
		return $result;
	}
	
	/**
	 * Split a string using str_getcsv.
	 * 
	 * Based on str_getcsv() function published on PHP.net by hpartidas@deuz.net   
	 * @see http://fr.php.net/manual/en/function.str-getcsv.php#98088
	 * 
	 * @param string $input The string to parse.
	 * @param string $delimiter Set the field delimiter (one character only).
	 * @param string $enclosure Set the field enclosure character (one character only).
	 * @param string $escape Set the escape character (one character only). Defaults as a backslash (\)
	 * @param string $eol Set the end-of-line character (one character only). Defaults as a new-line (\n)
	 */
	public static function split_str($input, $delimiter = ',', $enclosure = '"', $escape = '\\', $eol = '\n') {
		if (function_exists('str_getcsv')) {
			return str_getcsv($input, $delimiter, $enclosure, $escape);
		}
		$output = array();
		$tmp    = preg_split("/".$eol."/", $input);
		if (is_array($tmp) && !empty($tmp)) {
			while (list($line_num, $line) = each($tmp)) {
				if (preg_match("/".$escape.$enclosure."/", $line)) {
					while ($strlen = strlen($line)) {
						$pos_delimiter       = strpos($line, $delimiter);
						$pos_enclosure_start = strpos($line, $enclosure);
						if (
								is_int($pos_delimiter) && is_int($pos_enclosure_start)
								&& ($pos_enclosure_start < $pos_delimiter)
						) {
							$enclosed_str = substr($line,1);
							$pos_enclosure_end = strpos($enclosed_str, $enclosure);
							$enclosed_str = substr($enclosed_str, 0, $pos_enclosure_end);
							$output[$line_num][] = $enclosed_str;
							$offset = $pos_enclosure_end + 3;
						}
						else {
							if (empty($pos_delimiter) && empty($pos_enclosure_start)) {
								$output[$line_num][] = substr($line, 0);
								$offset = strlen($line);
							}
							else {
								$output[$line_num][] = substr($line, 0, $pos_delimiter);
								$offset = (
										!empty($pos_enclosure_start)
										&& ($pos_enclosure_start < $pos_delimiter)
								)
								? $pos_enclosure_start
								: $pos_delimiter + 1;
							}
						}
						$line = substr($line, $offset);
					}
				}
				else {
					$line = preg_split("/".$delimiter."/", $line);
					// Validating against pesky extra line breaks creating false rows.
					if (is_array($line) && !empty($line[0])) {
						$output[$line_num] = $line;
					}
				}
			}
			return $output;
		}
		return false;
	}
	
	/**
	 * Get the documentation of a method.
	 *
	 * @param object|string $class
	 * @param string $method
	 * @return string[]|null
	 * @throws Exception
	 */
	public static function get_method_doc($class, $method, $parent = null, &$redirect = array()) {
	
		try {
				
			$class = new ReflectionClass(is_object($class) ? get_class($class) : $class);
				
			$method = $class->getMethod($method);
				
			if (!$method) {
				return null;
			}
				
			$doc = $method->getDocComment();
				
			if (is_string($doc)) {
				$c = '@doc';
				$r = array($c => array());
				$alias = array();
				foreach (explode("\n", substr($doc, 2, -2)) as $line) {
					$line = trim($line);
					while (substr($line, 0, 1) == '*') {
						$line = ltrim(substr($line, 1));
					}
					if (substr($line, 0, 1) == '@') {
						@list($c, $line) = explode(' ', $line, 2);
					}
					$r[$c][] = $line;
				}
				$r = self::atrim($r);
				if (isset($r['@alias'])) {
					if (in_array($method, $redirect)) {
						throw new Exception("Commands alias loop between '$method' and '$parent'");
					}
					$redirect[] = $method;
					$doc = self::get_method_doc($class, $r['@alias'][0], $method, $redirect);
					if (is_array($doc)) {
						$r = array_merge($r, $doc);
					}
				}
				return $r;
			}
		}
	
		catch (Exception $ex) {
		}
	
		return null;
	}
	
}

?>