<?php

if(!defined('LF')) define('LF',"\n");

class pg_stats {

    public $total = 0;
    private $info = null;
    private $collections = null;
    private $cache = array();

    function __construct(&$info, &$collections) {
        $this->info = &$info;
        $this->total = count($info);
        $this->collections = &$collections;
    }

    private function checkDevError($info, $plugin, $dev_error_msg) {
        if (in_array(strtolower($info['developer']), $this->collections['trackedDevelopers'])) {
            if (!$this->collections['trackedDevErr'][$info['developer']][$dev_error_msg] || !in_array($plugin, $this->collections['trackedDevErr'][$info['developer']][$dev_error_msg])) {
                $this->collections['trackedDevErr'][$info['developer']][$dev_error_msg][] = $plugin;
            }
        }
    }

    private function filter($expression, $dev_error_msg, $addinfo = false) {
        $retval = array();

        $func = create_function('$info' , "return ($expression);");
        $plugins = array_filter($this->info, $func);

        $retval['cnt'] = count($plugins);
        $retval['plugins'] = array_keys($plugins);
        if ($addinfo) {
            $retval['infos'] = $plugins;
            $retval['values'] = array_map($func, $plugins);
        }

        if ($dev_error_msg) {
            array_walk($plugins, array($this,'checkDevError'), $dev_error_msg);
        }
        return $retval;
    }

    function wiki_link($plugin) {
        return "[[plugin:$plugin]]";
    }

    function infos($expression, $dev_error_msg = null) {
        $key = hsc(str_replace(' ','',$expression));
        if (!$this->cache[$key] || !$this->cache[$key]['infos'] || $dev_error_msg) {
            $this->cache[$key] = $this->filter($expression, $dev_error_msg, true);
        }
        return $this->cache[$key]['infos'];
    }

    function count($expression, $dev_error_msg = null) {
        $key = hsc(str_replace(' ','',$expression));
        if (!$this->cache[$key] || $dev_error_msg) {
            $this->cache[$key] = $this->filter($expression, $dev_error_msg);
        }
        return $this->cache[$key]['cnt'];
    }

    /*
     *  returns "X plugins (Y%)"
     */
    function cnt($expression, $format = null, $dev_error_msg = null) {
        $key = hsc(str_replace(' ','',$expression));
        if (!$this->cache[$key] || $dev_error_msg) {
            $this->cache[$key] = $this->filter($expression, $dev_error_msg);
        }
        if ($format) {
            return sprintf($format, (string)$this->cache[$key]['cnt'], '('.round($this->cache[$key]['cnt']/$this->total*100).'%)');
        } else {
            return $this->cache[$key]['cnt'].' plugins ('.round($this->cache[$key]['cnt']/$this->total*100).'%)';
        }
    }

    /*
     *  returns list of links
     */
    function plugins($expression, $dev_error_msg = null) {
        $key = hsc(str_replace(' ','',$expression));
        if (!$this->cache[$key] || $dev_error_msg) {
            $this->cache[$key] = $this->filter($expression, $dev_error_msg);
        }
        return $this->plugins_from_array($this->cache[$key]['plugins']);
    }

    function plugins_from_array($plugins, $linksonly = false) {
        if (count($plugins) == 0) {
            return '  * No plugins matched expression'.LF;
        } else {
            $plugins = array_map('pg_stats::wiki_link', $plugins);
            if ($linksonly) {
                return implode(', ', $plugins);
            } else {
                return '  * '.implode(', ', $plugins).LF;
            }
        }
    }

    static public function taglink($key) {return "[[plugintag>$key]]";}

    /*
     *  returns pivot table WITHOUT table header
     */
    function pivot($expression, $showpercent = false, $sortlinkcount = false, $sortdesc = false, $num = null, $showlinks = false, $callback = false) {
        $result = array();
        $func = create_function('$info' , "return ($expression);");
        $plugin_cnt = 0;
        foreach($this->info as $name => $info) {
            $key = $func($info);
            if (is_array($key)) {
                foreach($key as $k) {
                    $result[$k][] = $this->wiki_link($name);
                }
                $plugin_cnt++;
            } else {
                if ($key) {
                    $result[$key][] = $this->wiki_link($name);
                    $plugin_cnt++;
                }
            }
        }
        if (!$result) {
            return '| No plugins matched expression |'.LF;
        }

        if ($sortlinkcount) {
            if ($sortdesc) {
                uasort($result, create_function('$a,$b' , 'return (count($b)-count($a));'));
            } else {
                uasort($result, create_function('$a,$b' , 'return (count($a)-count($b));'));
            }
        } else {
            if ($sortdesc) {
                krsort($result);
            } else {
                ksort($result);
            }
        }
        $retval = '';
        $callback = array('pg_stats',$callback);
        $cnt = 0;
        foreach($result as $key => $links) {
            $retval .= '|  ';
            if (is_callable($callback)) {
                $retval .= call_user_func($callback,$key);
            } else {
                $retval .= $key;
            }
            $retval .='  |  ';
            $retval .= count($links);
            if ($showpercent) {
                $retval .= ' ('.round(count($links)/$this->total*100).'%)';
            }
            $retval .= ' | ';
            if ($showlinks) {
                $retval .= ($links ? implode(', ', $links) : '').' |';
            }
            $retval .= LF;
            if ($num && ++$cnt >= $num) break;
        }
        $single = array_filter($result, create_function('$a' , 'return (count($a) == 1);'));
        $retval .= '-----------------------------------------------------------------------------'.LF;
        $retval .= '  Pivot generated '.count($result).' rows and contain '.$plugin_cnt.' plugins.'.LF;
        $retval .= '  '.count($single).' rows ('.round(count($single)/count($result)*100).'%) contain only one plugin.'.LF;
        $retval .= '-----------------------------------------------------------------------------'.LF;
        return $retval;
    }

    /**
     * @param $expression Field to evaluate, usually from $info
     * @param int $num number of items to return
     * @param bool $table if true, return as a table without headers
     * @param bool $pluginlink if true, make $name a dokuwiki pluginlink
     * @return string|void
     */
    function max($expression, $num = 1, $table = false, $pluginlink = false) {
        $key = hsc(str_replace(' ','',$expression));
        if (!$this->cache[$key] || !$this->cache[$key]['infos']) {
            $this->cache[$key] = $this->filter($expression, null, true);
        }
        if (!$this->cache[$key]['values']) return;

        arsort($this->cache[$key]['values']);
        $retval = '';
        $cnt = 0;
        foreach($this->cache[$key]['values'] as $name => $value) {
            if ($table) {
                $retval .= '|  ';
            }
            $retval .= $value;
            if ($table) {
                $retval .= '|  ';
            }
            if ($num == 1) break;
            if ($pluginlink) {
                $retval .= ' '.$this->wiki_link($name);
            } else {
                $retval .= ' ' . $name;
            }
            if ($table) {
                $retval .= '  |';
            }
            $retval .= LF;
            if (++$cnt >= $num) break;
        }
        return $retval;
    }

    /**
     * @param $expression Field to evaluate, usually from $info
     * @param int $num number of items to return
     * @param bool $table if true, return as a table without headers
     * @param bool $pluginlink if true, make $name a dokuwiki pluginlink
     * @return string|void
     */
    function min($expression, $num = 1, $table = false, $pluginlink = false) {
        $key = hsc(str_replace(' ','',$expression));
        if (!$this->cache[$key] || !$this->cache[$key]['infos']) {
            $this->cache[$key] = $this->filter($expression, null, true);
        }
        if (!$this->cache[$key]['values']) return;

        asort($this->cache[$key]['values']);
        $retval = '';
        $cnt = 0;
        foreach($this->cache[$key]['values'] as $name => $value) {
            if ($table) {
                $retval .= '|  ';
            }
            $retval .= $value;
            if ($table) {
                $retval .= '|  ';
            }
            if ($num == 1) break;
            if ($pluginlink) {
                $retval .= ' '.$this->wiki_link($name);
            } else {
                $retval .= ' ' . $name;
            }
            if ($table) {
                $retval .= '  |';
            }
            $retval .= LF;
            if (++$cnt >= $num) break;
        }
        return $retval;
    }

    function sum($expression) {
        $key = hsc(str_replace(' ','',$expression));
        if (!$this->cache[$key] || !$this->cache[$key]['infos']) {
            $this->cache[$key] = $this->filter($expression, null, true);
        }
        if (!$this->cache[$key]['values']) return;

        return array_sum($this->cache[$key]['values']);
    }

    function median($expression) {
        $key = hsc(str_replace(' ','',$expression));
        if (!$this->cache[$key] || !$this->cache[$key]['infos']) {
            $this->cache[$key] = $this->filter($expression, null, true);
        }
        if (!$this->cache[$key]['values']) return;

        $array = array_values($this->cache[$key]['values']);
        sort($array);

        if (count($array) == 1) return $array[0];
        if (count($array) % 2 == 0) {
            $idx = count($array)/2 - 1;
            return ($array[$idx]+$array[$idx+1])/2;
        } else {
            return $array[(count($array)-1)/2];
        }
    }

}
