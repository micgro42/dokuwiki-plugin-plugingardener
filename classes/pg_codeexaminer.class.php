<?php
class pg_codeexaminer extends pg_gardener {

    function execute() {
// indicate whether codeblock or download
        echo "<h4>Examine Code</h4>\n";
        echo "<ul>";
        $localpluginsdir = $this->cfg['localdir'].'plugins/';
$ii = 0;
        foreach ($this->collections['plugins'] as $plugin) {
//            if (in_array($plugin, array('swfobject','jquery'))) continue;

            $mtime = microtime();
            $mtime = explode(' ', $mtime);
            $mtime = $mtime[1] + $mtime[0];
            $starttime = $mtime;

            set_time_limit(2*60);
            $ii += 1;
            file_put_contents($this->cfg['localdir'].'debug.txt', "#$ii  $plugin  ");
			// now the plugin should be available in ..\plugins\myplugin\myplugin to filter out
			// downloaded junk like ..\plugins\myplugin\flashplayer etc
			// BUT we examine all directories
            $plugindir = $localpluginsdir.$plugin.'/';
            if (is_dir($plugindir)) {
                $dir = dir($plugindir);
                while (($file = $dir->read()) != false) {
                    $pluginpath = $plugindir.$file.'/'; 
                    if ($file != '.' && $file != '..' && is_dir($pluginpath)) {
                        if ($file != $plugin) $this->info[$plugin]['dirdiff'] = $file;
                        $this->_examine_plugin_dir($plugin, $pluginpath);
                        break;
                    }
                }
            }

            if (!$this->info[$plugin]['downloadexamined']) {
                $this->info[$plugin]['downloadexamined'] = 'unavailable';
            }

            if (file_exists($plugindir.'url.txt')) {
                if (file_get_contents($plugindir.'url.txt') == $this->info[$plugin]['downloadbutton']) {
                    $this->info[$plugin]['downloadstyle'] = 'Main URL';
                } else {
                    $this->info[$plugin]['downloadstyle'] = 'Other URL';
                }
            } elseif (file_exists($plugindir.$plugin.'/extracted__code__block.php') || file_exists($plugindir.$plugin.'/code.php')) {
                $this->info[$plugin]['downloadstyle'] = 'Code Block';

            } elseif ($this->info[$plugin]['downloadexamined'] == 'yes') {
                $this->info[$plugin]['downloadstyle'] = 'Manual Download';

            } elseif (file_exists($plugindir)) {
                $this->info[$plugin]['downloadstyle'] = 'Download Blocked';
            }
            
            if ($this->info[$plugin]['plugin']) {
                foreach ($this->info[$plugin]['plugin'] as $class_name => $module) {
                    if ($module['type']) {
                        $this->info[$plugin]['t_'.$module['type']] = 'yes'; 
                    }
                }
            }

            $mtime = microtime();
            $mtime = explode(" ", $mtime);
            $mtime = $mtime[1] + $mtime[0];
            $endtime = $mtime;
            $this->info[$plugin]['time'] = str_replace('.', ',', ($endtime - $starttime));
        }

        echo "<li>Done</li></ul>";
        return true;
    }

    // DIRECTORY & CLOC
    function _examine_plugin_dir($plugin, $plugindir, $runcloc=true) {
        $this->info[$plugin]['downloadexamined'] = 'yes';
        echo "<li><a href=\"http://www.dokuwiki.org/plugin:$plugin\">$plugin</a> - $plugindir</li>\n";

        // CLOC code metrics
        if ($runcloc && !$this->cfg['fasteval']) {
            exec("cloc \"$plugindir\"", $result);
            $result = implode("\n", $result);
            if (preg_match('/PHP\s+(\d+)\s+\d+\s+(\d+)\s+(\d+)/i', $result, $match)) {
                $this->info[$plugin]['php_files'] = $match[1];
                $this->info[$plugin]['php_lines'] = $match[3];
                $this->info[$plugin]['php_comments'] = $match[2];
            }
            if (preg_match('/Javascript\s+(\d+)\s+\d+\s+(\d+)\s+(\d+)/i', $result, $match)) {
                $this->info[$plugin]['java_files'] = $match[1];
                $this->info[$plugin]['java_lines'] = $match[3];
            }
            if (preg_match('/CSS\s+(\d+)\s+\d+\s+(\d+)\s+(\d+)/i', $result, $match)) {
                $this->info[$plugin]['css_files'] = $match[1];
                $this->info[$plugin]['css_lines'] = $match[3];
            }
        }

        // examine each file
        $dir = dir($plugindir);
        while (($file = $dir->read()) != false) {
//            echo "filename: " . $file . "<br />";
            $file = $plugindir.$file;
            $fileinfo = pathinfo($file);

            if (is_dir($file)) {
                if ($fileinfo['basename'] == 'lang') {
                    $this->_examine_lang($plugin,$file);

                } elseif ($fileinfo['basename'] == 'conf') {
                    $this->_examine_conf($plugin,$file);

                } elseif ($fileinfo['basename'] == 'images') {
                    $this->info[$plugin]['images'] = 'yes';

                } elseif (in_array($fileinfo['basename'], array('.svn','_darcs'))) {
                    $this->info[$plugin]['junk'] = $fileinfo['basename'];

                } elseif (in_array($fileinfo['basename'], array('syntax','admin','renderer','helper','action','_test'))) {
                    $this->_examine_plugin_dir($plugin, $file . '/', false);

                } else {
//                    echo '&nbsp;&nbsp;skipped<br/>';
                }

            } elseif ($fileinfo['basename'] == '.DS_Store') {
                $this->info[$plugin]['junk'] = $fileinfo['basename'];

            } elseif ($fileinfo['basename'] == 'script.js') {
                $this->_examine_javascript($plugin,$file);

            } elseif ($fileinfo['basename'] == 'plugin.info.txt') {
                $this->info[$plugin]['plugininfotxt'] = 'yes';

            } elseif ($fileinfo['extension'] == 'css') {
                $this->_examine_css($plugin, $file, $fileinfo['filename']);

            } elseif (strstr($fileinfo['basename'],".test.php") == ".test.php") {
                $this->info[$plugin]['unittests'] = 'yes';

            } elseif ($fileinfo['extension'] == 'php') {
                $this->_examine_php($plugin,$file);

            } else {
//                echo '&nbsp;&nbsp;skipped<br/>';
            }
        }
        $dir->close();
    }


    function _examine_lang($plugin,$langdir) {
        $dir = dir($langdir);
        while (($file = $dir->read()) != false) {
            if (strpos($file,'.') === false) {
//            if ($file != '.' && $file != '..') {
                $this->info[$plugin]['lang'][] = $file;
            }
        }
        $dir->close();
    }

    // PHP-CODE
    function _examine_php($plugin,$file) {
        $markup = file_get_contents($file);

        // plugin type and name
        $infos = io_grep($file,'/(?<=class )\s*\w*(?=_plugin)/i',0,true);
        $classtype = trim($infos[0][0]);

        $infos = io_grep($file,'/(?<=_plugin_)\w*(?= extends)/i',0,true);
        $classname = $infos[0][0];
        $class = $classtype . '_' . $classname;

        $this->info[$plugin]['plugin'][$class]['type'] = $classtype;
        $this->info[$plugin]['plugin'][$class]['name'] = $classname;

        // function getInfo
        /* TODO find a pupose for these variables
        $infos = io_grep($file,'/\'author\' *=> *\'(.*?)\'/',0,true);
        $this->info[$plugin]['plugin'][$class]['code_author'] = $infos[0][1];
        $infos = io_grep($file,'/\'date\' *=> *\'(.*?)\'/',0,true);
        $this->info[$plugin]['plugin'][$class]['code_date'] = $infos[0][1];
        $infos = io_grep($file,'/function getInfo\(\)/',0,true);
        */

        // function connectTo
        preg_match_all('/Lexer->addSpecialPattern\(\s*(.*?)\s*,\s*\$/',$markup,$matches);
        $this->info[$plugin]['plugin'][$class]['regexp_special'] = $matches[1];
        preg_match_all('/Lexer->addEntryPattern\(\s*(.*?)\s*,\s*\$/',$markup,$matches);
        $this->info[$plugin]['plugin'][$class]['regexp_entry'] = $matches[1];
        preg_match_all('/Lexer->addExitPattern\(\s*(.*?)\s*,\s*\'plugin/',$markup,$matches);
        $this->info[$plugin]['plugin'][$class]['regexp_exit'] = $matches[1];

        /* TODO: We need to check that these render the same format
        if (preg_match('/function canRender/',$markup))
            $this->info[$plugin]['canRender'] = 'yes';
        */

        if (strpos($markup, 'strftime') !== false)
            $this->info[$plugin]['strftime'] = 'yes';

        if (strpos($markup, 'dformat') !== false)
            $this->info[$plugin]['dformat'] = 'yes';

        if (preg_match('/\$ACT\s*==\s*\'save\'/i',$markup))
            $this->info[$plugin]['save_on_act'] = 'yes';

        if (preg_match('/private\s+function/i',$markup))
            $this->info[$plugin]['php5'] = 'yes';

        //check for usage of old auth backend
        if (strpos($markup, 'inc/auth/') !== false)
            $this->info[$plugin]['old_auth'] = 'yes';

        // registered event handlers
        preg_match_all('/->register_hook\(\s*\'([A-Z_]*)/s',$markup,$matches,PREG_SET_ORDER);
        foreach ($matches as $info) {
            $this->info[$plugin]['plugin'][$class]['events'][] = $info[1];
            if (!$this->info[$plugin]['events'] || !in_array($info[1], $this->info[$plugin]['events'])) {
                $this->info[$plugin]['events'][] = $info[1];
            }
        }
    }

	// JAVA-CODE and LINT (from http://www.jslint.com/wsh/index.html)
    function _examine_javascript($plugin,$file) {
        $this->info[$plugin]['javascript'] = 'yes';
        $js = file_get_contents($file);
        if (strpos($js, 'DOKUWIKI:include') !== false) {
            $this->info[$plugin]['javainclude'] = 'yes';
        }
        if (strpos($js, 'toolbar[', $js) !== false) {
            $this->info[$plugin]['javatoolbar'] = 'yes';
        }
        if (strpos($js, 'jQuery(') !== false) {
            $this->info[$plugin]['jquery'] = 'yes';
        }
        if (strpos($js, '(\'tool__bar\')', $js) !== false) {
            $this->info[$plugin]['javatoolbar'] = 'dynamic';
        }
        if (strpos($js, '.runAJAX(', $js) !== false) {
            $this->info[$plugin]['java_ajax'] = 'yes';
        }
		if ($this->info[$plugin]['java_lines'] > 800 || $this->cfg['fasteval'] || true) {
				$this->info[$plugin]['java_lint'] = 'skipped';
        } else {
			$cmd = "cscript ".dirname(__FILE__)."\..\jslint.js";
			$descriptorspec = array(
				0 => array('pipe', 'r'), // stdin
				1 => array('pipe', 'w'), // stdout
				2 => array('pipe', 'w') // stderr
			);

			$process = proc_open($cmd, $descriptorspec, $pipes);
			if (is_resource($process)) {

				fwrite($pipes[0], $js);
				fclose($pipes[0]);

				stream_get_contents($pipes[1]);
				fclose($pipes[1]);

				$result = stream_get_contents($pipes[2]);
				if ($result) $this->info[$plugin]['java_lint'] = $result;
				fclose($pipes[2]);

				proc_close($process);
			}
		}
    }

// http://jigsaw.w3.org/css-validator/DOWNLOAD.html.en
// http://www.codestyle.org/css/tools/W3C-CSS-Validator.shtml
    function _examine_css($plugin,$file,$type) {
        $this->info[$plugin]['css'] = 'yes';
        if ($type == 'print') {
            $this->info[$plugin]['cssprint'] = 'yes';
        } elseif ($type == 'rtl') {
            $this->info[$plugin]['cssrtl'] = 'yes';
        }

        $css = file_get_contents($file);
        $oldreplacements = array('white','medium','darkgray','dark','black','darker','lightgray','lighter','mediumgray','light');
        if (preg_match_all('/__('. implode('|', $oldreplacements) .')__/', $css, $matches)) {
            $this->info[$plugin]['cssreplacements'] = $matches[0];
        }
    }


    function _examine_conf($plugin,$confdir) {
        $dir = dir($confdir);
        $conf = false;
        $meta = false;
        while (($file = $dir->read()) != false) {
            if (strtolower($file) == 'default.php') $conf = true;
            if (strtolower($file) == 'metadata.php') $meta = true;
        }
        $dir->close();
        if ($conf) $this->info[$plugin]['conf'] = 'nometa';
        if ($meta && $conf) $this->info[$plugin]['conf'] = 'yes';
    }

}

