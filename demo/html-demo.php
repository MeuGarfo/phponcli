<!DOCTYPE html>
<html lang="en">
<head>
	<title>PHPonCLI Demo</title>

	<meta charset="utf-8">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<meta name="author" content="evolya.fr">
	<meta name="copyright" content="Copyright 2012 evolya.fr">
	<meta name="description" content="">
	<meta name="keywords" content="php, cli, api, library">

	<link href='//fonts.googleapis.com/css?family=Courgette' rel='stylesheet' type='text/css'>

	<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>
	
	<style type="text/css">
	body {
		font-family: Verdana, Arial, sans;
		font-size: 13px;
	}
	h1 {
		font-family: Georgia, Times, serif;
		font-weight: normal;
		font-size: 80px;
		margin: 10px;
		color: #232B30;
		text-shadow: #ccc 0 2px 0;
	}
	h1 span {
		font-family: Courgette, cursive;
		font-size: 30%;
		color: #ccc;
		text-shadow: none;
	}
	code {
		color: #1f5a96;
		font-weight: bolder;
	}
	kbd {
		background: #eee;
		border: 1px solid #ddd;
		padding: 1px 3px;
		box-shadow: 1px 1px 1px rgba(0,0,0,0.2);
		-moz-box-shadow: 1px 1px 1px rgba(0,0,0,0.2);
		-webkit-box-shadow: 1px 1px 1px rgba(0,0,0,0.2);
	}
	#terminal {
		background: #333;
		padding: 0; 
		width: 600px;
		border-radius: 4px;
	}
	#terminal .header {
		background: #232B30;
		background: -moz-linear-gradient(top, #3D4850 3%, #313D45 4%, #232B30 100%);
		background: -webkit-gradient(linear, left top, left bottom, color-stop(3%,#3D4850), color-stop(4%,#313D45), color-stop(100%,#232B30));
		filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#3D4850', endColorstr='#232B30',GradientType=0 );
		color: #fff;
		font-family: Verdana, Arial, sans;
		font-size: 90%;
		font-weight: bolder;
		padding: 4px 8px;
		border-radius: 4px;
	}
	#terminal samp {
		display: block;
		min-height: 450px;
		max-height: 800px;
		overflow: auto;
		color: #fff;
		font-size: 90%;
		padding: 5px;
		white-space: pre-wrap; /* css-3 */
		white-space: -moz-pre-wrap !important; /* Mozilla, since 1999 */
		white-space: -pre-wrap; /* Opera 4-6 */
		white-space: -o-pre-wrap; /* Opera 7 */
		word-wrap: break-word; /* Internet Explorer 5.5+ */
	}
	#terminal .footer {
		border-top: 1px solid #292829;
		height: 32px;
	}
	#terminal input[type="text"] {
		width: 535px;
		border: none;
		background: #333;
		color: #fff;
		height: 30px;
		padding: 0 0 0 5px;
		margin: 0;
		outline: 0;
	}
	#terminal input[type="submit"] {
		display: inline-block;
		width: 50px;
		outline: 0;
		padding: 4px 10px;
		color: #9FA8B0;
		font-weight: bold;
		text-shadow: 1px 1px #1F272B;
		border: 1px solid #1C252B;
		border-radius: 3px;
		background: #232B30;
		background: -moz-linear-gradient(top, #3D4850 3%, #313D45 4%, #232B30 100%);
		background: -webkit-gradient(linear, left top, left bottom, color-stop(3%,#3D4850), color-stop(4%,#313D45), color-stop(100%,#232B30));
		filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#3D4850', endColorstr='#232B30',GradientType=0 );
		box-shadow: 1px 1px 1px rgba(0,0,0,0.2);
		-moz-box-shadow: 1px 1px 1px rgba(0,0,0,0.2);
		-webkit-box-shadow: 1px 1px 1px rgba(0,0,0,0.2);
		cursor: pointer;
	}
	#terminal input[type="submit"]:hover {
		color: #fff;
		background: #4C5A64; /* old browsers */
		background: -moz-linear-gradient(top, #4C5A64 3%, #404F5A 4%, #2E3940 100%); /* firefox */
		background: -webkit-gradient(linear, left top, left bottom, color-stop(3%,#4C5A64), color-stop(4%,#404F5A), color-stop(100%,#2E3940)); /* webkit */
		filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#4C5A64', endColorstr='#2E3940',GradientType=0 ); /* ie */
	}
	#terminal input[type="submit"]:active {
		background-position: 0 top;
		color: #fff;
		padding: 4px 12px 4px;
		background: #20282D; /* old browsers */
		background: -moz-linear-gradient(top, #20282D 3%, #252E34 51%, #222A30 100%); /* firefox */
		background: -webkit-gradient(linear, left top, left bottom, color-stop(3%,#20282D), color-stop(51%,#252E34), color-stop(100%,#222A30)); /* webkit */
		filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#20282D', endColorstr='#222A30',GradientType=0 ); /* ie */
		-moz-box-shadow: 1px 1px 1px rgba(255,255,255,0.1); /* Firefox */
		-webkit-box-shadow: 1px 1px 1px rgba(255,255,255,0.1); /* Safari, Chrome */
		box-shadow: 1px 1px 1px rgba(255,255,255,0.1); /* CSS3 */
	}
	</style>

</head>

<body>

	<h1>PHPonCLI <span>Demo</span></h1>

	<form id="terminal" action="" method="get">
		<div class="header">Terminal</div>
		<samp id="output"><?php
		
		if (isset($_GET['input']) && !empty($_GET['input'])) {
			$cli = new MyCLI();
			$cli->setDecorator(PHPonCLI::DECORATION_HTML);
			PHPonCLI_UtilPack::install($cli);
			echo "> {$_GET['input']}\n";
			$cli->exec(__FILE__  . ' ' . $_GET['input']);
		}
		
		?></samp>
		<div class="footer">
			<input type="text" id="input" name="input" placeholder="Enter your command here" autofocus />
			<input type="submit" value="OK" />
		</div>
		<script>

		$('#output').click(function () {
			$('#input').focus();
		});

		var autocomp = null;
		
		$('#input').keydown(function (e) {

			// cls
			if (e.keyCode == 13 && this.value == 'cls') {
				e.preventDefault();
				this.value = '';
				document.getElementById('output').innerHTML = '';
				return false;
			}
			
			// Auto-completion (tab key)
			if (e.keyCode == 9) {

				e.preventDefault();

				if (autocomp) {
					autocomp.abort();
				}
				
				autocomp = $.ajax({
					method: 'post',
					url: 'autocomplete-webservice.php',
					data: { "q": this.value },
					dataType: 'json',
					context: this,
					success: function (data, textStatus, jqXHR) {

console.log(data);
						
						var inp = $(this);
						
						autocomp = null;

						// No result : do nothing
						if (data.length < 1) return;

						// A single result
						if (data.length === 1) {
							if (data[0].substr(-1) != '/') {
								tab = false;
								data[0] += ' ';
							}
							inp.val(inp.val() + data[0]);								
						}

						// Several results = display
						else {
							var d = '',
								m = inp.val().split(" ").pop(),
								out = $('#output');
							for (i in data) {
								// Partial completion
								if (data[i].substr(0, 1) == '|' && data[i].substr(-1) == '|') {
									inp.val(inp.val() + data[i].substr(1, data[i].length - 2));
								}
								else {
									d += "\n" + m + data[i];
								}
							}
							out.html(out.html() + ">" + d + "\n");
							out[0].scrollTop = out[0].scrollHeight;
						}
					
					},
					error: function (jqXHR, textStatus, errorThrown) {
						alert("Error: " + textStatus + ", " + errorThrown);
					}
				});
			}
		}).focus();
		</script>
	</form>

	<p>Type <code>help</code> for a list of available commands.
	Type <code>pong</code> or <code>hell</code> to test spelling suggestions.</p>
	<p>You can pipe commands using '|'. The <code>grep</code> command is used to filter results eg. <code>help | grep mm</code></p>
	<p>Use <kbd>TAB</kbd> key to trigger auto-completion.</p>
	
</body>
</html>