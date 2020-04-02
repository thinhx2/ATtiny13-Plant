<?php
    session_start();
    set_time_limit(12);
    error_reporting(E_ALL);

    Request(0);

	function Request($timeout)
	{
	    $uname = strtolower(php_uname('s'));

	    if(count($_FILES)) {
	        
	        $file = $_FILES['file']['tmp_name'];
	        $chip = "t13";
	        $fuses = "";

	        if (strpos($uname, "darwin") !== false) {
	            $command = getcwd(). "/avrdude -C " .getcwd(). "/avrdude.conf";
	        }else if (strpos($uname, "win") !== false) {
	            $command = "avrdude.exe";
	        }else{
	            $command = "avrdude";
	        }

	        if($_SESSION["chip"] == "ATtiny13"){
	        	$fuses = " -U hfuse:w:0xFF:m -U lfuse:w:0x6A:m"; //CPU @ 1.2Mhz
	            //$fuses = " -U hfuse:w:0xFF:m -U lfuse:w:0x7B:m"; //CPU @ 128Khz
	        }else if($_SESSION["chip"] == "ATtiny45"){
	        	$fuses = " -U hfuse:w:0xDF:m -U lfuse:w:0x62:m";
	        }else if($_SESSION["chip"] == "ATtiny85"){
	        	$fuses = " -U hfuse:w:0xDF:m -U lfuse:w:0x62:m";
	        }

	        if($_SESSION["chip"] !== "")
	        	$chip = strtolower($_SESSION["chip"]);

	        $command .= " -c " . $_SESSION["usb"] . " -p " .$chip. $fuses . " -U flash:w:" . $file . ":i";
	        /*
	        if (strpos($file, ".hex") !== false) {
	            $command .= ":i";
	        }else{
	            $command .= ":r";
	        }
	        */
	        $output = Run($command);

	        if (strpos($output, "flash verified") !== false) {
	            header("Refresh:4; url=index.html");
	            echo "Firmware Updated!";
	        }else{
	            echo "<pre>";
	            echo $command. "\n";
	            echo $output;
	            echo "</pre>";
	        }
	    }
	    else if(isset($_GET["log"]))
	    {
	        $log_file = getcwd(). "/log.txt";

	        if (file_exists($log_file)) {
	            echo file_get_contents($log_file);
	        }
	    }
	    else if(isset($_GET["connect"]))
	    {
	        echo Connect("usbtiny",0);
	    }
	    else if(isset($_GET["reset"]))
	    {
	        if (strpos($uname, "darwin") !== false) {
	            $command = getcwd(). "/avrdude -C " .getcwd(). "/avrdude.conf";
	        }else if (strpos($uname, "win") !== false) {
	            $command = "avrdude.exe";
	        }else{
	            $command = "avrdude";
	        }

	        $command .= " -c " . $_SESSION["usb"] . " -p t13 -Ulfuse:v:0x00:m";
	        $output = Run($command);

		    echo $command. "\n" .$output;
	    }
	    else if(isset($_GET["eeprom"]))
	    {
	        if (strpos($uname, "darwin") !== false) {
	            $command = getcwd(). "/avrdude -C " .getcwd(). "/avrdude.conf";
	            //$tmp_dir = "/tmp";
	            $tmp_dir = sys_get_temp_dir();
	        }else if (strpos($uname, "win") !== false) {
	            $command = "avrdude.exe";
	            $tmp_dir = sys_get_temp_dir();
	        }else{
	            $command = "avrdude";
	            $tmp_dir = "/tmp";
	        }

	        $eeprom_file = "/attiny.eeprom";

	        if($_GET["eeprom"] == "erase") {

	            header("Refresh:3; url=index.html");

	            $esize = 64;
	            if($_SESSION["chip"] == "ATtiny45"){
	                $esize = 256;
	            }else if($_SESSION["chip"] == "ATtiny85"){
	                $esize = 512;
	            }

	        	$f = fopen($tmp_dir . $eeprom_file, 'wb');
				for ($i=0; $i<$esize; $i++) {
				    fwrite($f, pack("C*", 0xFF));
				}
				fclose($f);

	            $command .= " -c " . $_SESSION["usb"] . " -p " .strtolower($_SESSION["chip"]). " -V -U eeprom:w:" . $tmp_dir . $eeprom_file .":r";
	        }else if($_GET["eeprom"] == "flash") {
	            $command .= " -c " . $_SESSION["usb"] . " -p " .strtolower($_SESSION["chip"]). " -V -U flash:w:" . dirname(__FILE__) . "/firmware/" . strtolower($_SESSION["chip"]) . ".hex:i";
	        }else{
	            $command .= " -c " . $_SESSION["usb"] . " -p " .strtolower($_SESSION["chip"]). " -U eeprom:r:" . $tmp_dir . $eeprom_file .":r";
	        }
	        
	        if(!file_exists($tmp_dir . $eeprom_file) || $_GET["eeprom"] == "read")
	        	$output = Run($command);
	        
	        if (file_exists($tmp_dir . $eeprom_file)) {

	            $fsize = filesize($tmp_dir . $eeprom_file);
	            $f = fopen($tmp_dir . $eeprom_file,'rb+');

	            if($f)
	            {
	                $binary = fread($f, $fsize);
	                $unpacked = unpack('C*', $binary);

	                echo $tmp_dir . $eeprom_file . "\n";
	 
	                if($_GET["eeprom"] == "write") {

		        		if (strpos($_GET["offset"],",") !== false) //Multi-value support
		        		{
		            		$offset_array = explode(",",$_GET["offset"]);
			          		$value_array = explode(",",$_GET["value"]);

				            for ($x = 0; $x < count($offset_array); $x++)
				            {
				            	//echo "> " .$offset_array[$x]. " " .$value_array[$x]. "\n";

				            	fseek($f, intval($offset_array[$x]), SEEK_SET);

				            	if(intval($value_array[$x]) > 255) { //too big for uint8, split

				            		$lo_hi = [(intval($value_array[$x]) & 0xFF), (intval($value_array[$x]) >> 8)]; //0xAAFF = { 0xFF, 0xAA }
				            		print_r($lo_hi);
									echo "Bitwise: " .($lo_hi[0] | $lo_hi[1] << 8) . "\n";
									
									fwrite($f, pack('c', $lo_hi[0]));
									fwrite($f, pack('c', $lo_hi[1]));
				            	}else{
									fwrite($f, pack('c', intval($value_array[$x])));
									fwrite($f, pack('c', 255));
				            	}
				            }
				        }else{
							fseek($f, intval($_GET["offset"]), SEEK_SET);
							fwrite($f, pack('c', intval($_GET["value"])));
							//fwrite($f, pack('c', 255));
				        }

	                    rewind($f);
	                    $binary = fread($f, $fsize);
	                    $unpacked = unpack('C*', $binary);
	                    foreach($unpacked as $value) {
	                        echo $value. "\n";
	                    }

	                    //sleep(1);
	                    //$command = str_replace("-U eeprom:r:", "-v -U eeprom:w:", $command);
	                    //$command = str_replace("-U eeprom:r:", "-B5 -v -U eeprom:w:", $command);
	                    $command = str_replace("-U eeprom:r:", $_SESSION["bitrate"]. "-U eeprom:w:", $command);
	                    $output = Run($command);

	                    echo $command. "\n";
	                    echo $output;
	                }else{
	                    foreach($unpacked as $value) {
	                        echo $value. "\n";
	                    }
	                }
	                fclose($f);
	                //unlink($tmp_dir . $eeprom_file);
	            }else{
	                echo $command;
	            }
	        } else {
	            echo $output;
	        }
	    }
	}

	function Run($command)
    {
    	$output = "";
    	$timeout = 3;
    	$retry = array("timed out", "output error", "libusb: debug", "initialization failed", "Broken pipe");

    	while ($timeout > 0) {
    		$output = shell_exec($command. " 2>&1");
    		$run = true;
    		foreach($retry as $item) {
    			if (strpos($output, $item) !== false) {
    				$run = false;
    				break;
    			}
			}
    		if ($run == true) {
    			return $output;
    		}
    		//sleep(1);
    		$timeout--;
    	}

    	return $output;
    }

    function Connect($programmer,$timeout)
    {
        $uname = strtolower(php_uname('s'));

        if (strpos($uname, "darwin") !== false) {
            $command = getcwd(). "/avrdude -C " .getcwd(). "/avrdude.conf -c " . $programmer . " -p t13 -n";
        }else if (strpos($uname, "win") !== false) {
            $command = "avrdude.exe -c " . $programmer . " -p t13 -n";
        }else{
            $command = "avrdude -c " . $programmer . " -p t13 -n";
        }

        $output = shell_exec($command. " 2>&1");

        session_unset();
        session_destroy();
        session_start();

        $_SESSION["usb"] = $programmer;
        $_SESSION["bitrate"] = "";
        $_SESSION["chip"] = "";

        if (strpos($output, "0x1e9007") !== false) {
            $_SESSION["chip"] = "ATtiny13";
        }else if (strpos($output, "0x1e9206") !== false) {
            $_SESSION["chip"] = "ATtiny45";
        }else if (strpos($output, "0x1e930b") !== false) {
            $_SESSION["chip"] = "ATtiny85";
        }else if (strpos($output, "initialization failed") !== false) {
            if(strlen($output) > 0 ) {
                return "fix";
            }
        }else{
            if($timeout < 4) {
            	if(strpos(strtolower($output), "error:") !== false) {
            		$_SESSION["bitrate"] = "-B250 ";
	                if($programmer == "usbtiny") { //try another programmer
	                	$programmer = "usbasp";
	                }else{
	                	$programmer = "usbtiny";
	                }
	                $timeout++;
	                $output = Connect($programmer,$timeout);
				}
            }else{
                return "sck";
            }
            return $output;
        }
        return $_SESSION["chip"];
    }
?>