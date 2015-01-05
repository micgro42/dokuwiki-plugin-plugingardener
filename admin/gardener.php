<?php
/**
 * Plugin for a supervising Dokuwiki plugins
 *
 * !!! edits has been done in php.ini to allow_url_fopen !!!
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Håkan Sandell <hakan.sandell@home.se>
 */

 // must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'/plugingardener/classes/pg_gardener.class.php');
require_once(DOKU_PLUGIN.'/plugingardener/classes/pg_dokuwikiwebexaminer.class.php');
require_once(DOKU_PLUGIN.'/plugingardener/classes/pg_codedownloader.class.php');
require_once(DOKU_PLUGIN.'/plugingardener/classes/pg_codeexaminer.class.php');
require_once(DOKU_PLUGIN.'/plugingardener/classes/pg_reportwriter.class.php');

/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 */
class admin_plugin_plugingardener_gardener extends DokuWiki_Admin_Plugin {

    public $info = array();
    public $collections = array();
                        // danger ! localdir is hardcode in other admin
    public $cfg = array();

    function handle() {
    }

    function createConfig() {
        $this->cfg['doku_eventlist_uri'] = $this->getConf('doku_eventlist_uri');
        $this->cfg['doku_repo_uri'] = $this->getConf('doku_repo_uri');
        $this->cfg['doku_index_uri'] = $this->getConf('doku_index_uri');
        $this->cfg['doku_pluginbase_uri'] = $this->getConf('doku_pluginbase_uri');
        $this->cfg['bundledsourcedir'] = $this->getConf('bundledsourcedir');
        $this->cfg['localdir'] = $this->getConf('localdir');
        $this->cfg['previousYearTotal'] =  $this->getConf('previousYearTotal');
        $this->cfg['offline'] =  $this->getConf('offline');
        $this->cfg['downloadindex'] =  $this->getConf('downloadindex');
        $this->cfg['downloadpages'] =  $this->getConf('downloadpages');
        $this->cfg['downloadplugins'] = $this->getConf('downloadplugins');
        $this->cfg['overwrite'] =  $this->getConf('overwrite');
        $this->cfg['firstplugin'] =  $this->getConf('firstplugin');
        $this->cfg['lastplugin'] =  $this->getConf('lastplugin');
        $this->cfg['fasteval'] =  $this->getConf('fasteval');
        $this->cfg['plugin_page_timeout'] = $this->getConf('plugin_page_timeout');
        $this->cfg['external_page_timeout'] = $this->getConf('external_page_timeout');
        $this->cfg['survey_year'] = $this->getConf('survey_year');
    }

    function html() {
        $this->createConfig();
        // ensure output directory exists
        $localdir = $this->cfg['localdir'];
        if (!file_exists($localdir)) {
            mkdir($localdir, 0777, true);
        }

        // get plugins that not are plugins (manualy managed local list)
        echo "<h5>Not plugins</h5>";
        if (file_exists($localdir.'not_plugins.txt')) {
            $this->collections['notPlugins'] = file($localdir . 'not_plugins.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }else{
            $this->collections['notPlugins'] = array();
        }
        $this->echodwlink($this->collections['notPlugins']);

        // get list of developers with special attention
        echo "<h5>developers with special attention</h5>";
        if (file_exists($localdir.'tracked_developers.txt')) {
            $this->collections['trackedDevelopers'] = file($localdir . 'tracked_developers.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }else {
            $this->collections['trackedDevelopers'] = array();
        }
        $this->collections['trackedDevErr'] = array();

        // get list of previous years developers
        echo "<h5>previous years developers</h5>";
        if (file_exists($localdir.'previous_developers.txt')) {
            $this->collections['previousDevelopers'] = file($localdir . 'previous_developers.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }else {
            $this->collections['previousDevelopers'] = array();
        }
        $this->collections['previousDevelopers'] = array_unique($this->collections['previousDevelopers']);

		$handler = new pg_dokuwikiwebexaminer($this);
		if (!$handler->execute()) {
            echo "<h1>Aborted</h1>";
            return;
        }

		$handler = new pg_codedownloader($this);
		$handler->execute();

		$handler = new pg_codeexaminer($this);
		$handler->execute();

        $handler = new pg_reportwriter($this);
        $handler->execute();

        echo "<h4>Done</h4>";
        echo "<hr/>\n";
        if ($this->cfg['firstplugin'] == $this->cfg['lastplugin'] && $this->cfg['firstplugin'] != '') print_r($this->info);
        echo "<hr/>\n";
//        echo print_r($this->collections['trackedDevErr']);
    }

    function echodwlink($plugins) {
        if (!is_array($plugins)) {
            $plugins = array($plugins);
        }
        foreach ($plugins as $plugin) {
            $tmp .= "<a href=\"http://www.dokuwiki.org/plugin:$plugin\">$plugin</a>, ";
        }
        echo substr($tmp, 0, -2);
    }

    var $languageCodes = array(
    "aa" => "Afar",
    "ab" => "Abkhazian",
    "ae" => "Avestan",
    "af" => "Afrikaans",
    "ak" => "Akan",
    "am" => "Amharic",
    "an" => "Aragonese",
    "ar" => "Arabic",
    "as" => "Assamese",
    "av" => "Avaric",
    "ay" => "Aymara",
    "az" => "Azerbaijani",
    "ba" => "Bashkir",
    "be" => "Belarusian",
    "bg" => "Bulgarian",
    "bh" => "Bihari",
    "bi" => "Bislama",
    "bm" => "Bambara",
    "bn" => "Bengali",
    "bo" => "Tibetan",
    "br" => "Breton",
    "bs" => "Bosnian",
    "ca" => "Catalan",
    "ce" => "Chechen",
    "ch" => "Chamorro",
    "co" => "Corsican",
    "cr" => "Cree",
    "cs" => "Czech",
    "cu" => "Church Slavic",
    "cv" => "Chuvash",
    "cy" => "Welsh",
    "da" => "Danish",
    "de" => "German",
    "dv" => "Divehi",
    "dz" => "Dzongkha",
    "ee" => "Ewe",
    "el" => "Greek",
    "en" => "English",
    "eo" => "Esperanto",
    "es" => "Spanish",
    "et" => "Estonian",
    "eu" => "Basque",
    "fa" => "Persian",
    "ff" => "Fulah",
    "fi" => "Finnish",
    "fj" => "Fijian",
    "fo" => "Faroese",
    "fr" => "French",
    "fy" => "Western Frisian",
    "ga" => "Irish",
    "gd" => "Scottish Gaelic",
    "gl" => "Galician",
    "gn" => "Guarani",
    "gu" => "Gujarati",
    "gv" => "Manx",
    "ha" => "Hausa",
    "he" => "Hebrew",
    "hi" => "Hindi",
    "ho" => "Hiri Motu",
    "hr" => "Croatian",
    "ht" => "Haitian",
    "hu" => "Hungarian",
    "hy" => "Armenian",
    "hz" => "Herero",
    "ia" => "Interlingua (International Auxiliary Language Association)",
    "id" => "Indonesian",
    "ie" => "Interlingue",
    "ig" => "Igbo",
    "ii" => "Sichuan Yi",
    "ik" => "Inupiaq",
    "io" => "Ido",
    "is" => "Icelandic",
    "it" => "Italian",
    "iu" => "Inuktitut",
    "ja" => "Japanese",
    "jv" => "Javanese",
    "ka" => "Georgian",
    "kg" => "Kongo",
    "ki" => "Kikuyu",
    "kj" => "Kwanyama",
    "kk" => "Kazakh",
    "kl" => "Kalaallisut",
    "km" => "Khmer",
    "kn" => "Kannada",
    "ko" => "Korean",
    "kr" => "Kanuri",
    "ks" => "Kashmiri",
    "ku" => "Kurdish",
    "kv" => "Komi",
    "kw" => "Cornish",
    "ky" => "Kirghiz",
    "la" => "Latin",
    "lb" => "Luxembourgish",
    "lg" => "Ganda",
    "li" => "Limburgish",
    "ln" => "Lingala",
    "lo" => "Lao",
    "lt" => "Lithuanian",
    "lu" => "Luba-Katanga",
    "lv" => "Latvian",
    "mg" => "Malagasy",
    "mh" => "Marshallese",
    "mi" => "Maori",
    "mk" => "Macedonian",
    "ml" => "Malayalam",
    "mn" => "Mongolian",
    "mr" => "Marathi",
    "ms" => "Malay",
    "mt" => "Maltese",
    "my" => "Burmese",
    "na" => "Nauru",
    "nb" => "Norwegian Bokmal",
    "nd" => "North Ndebele",
    "ne" => "Nepali",
    "ng" => "Ndonga",
    "nl" => "Dutch",
    "nn" => "Norwegian Nynorsk",
    "no" => "Norwegian",
    "nr" => "South Ndebele",
    "nv" => "Navajo",
    "ny" => "Chichewa",
    "oc" => "Occitan",
    "oj" => "Ojibwa",
    "om" => "Oromo",
    "or" => "Oriya",
    "os" => "Ossetian",
    "pa" => "Panjabi",
    "pi" => "Pali",
    "pl" => "Polish",
    "ps" => "Pashto",
    "pt" => "Portuguese",
    "pt-br" => "Brazilian Portuguese",
    "qu" => "Quechua",
    "rm" => "Raeto-Romance",
    "rn" => "Kirundi",
    "ro" => "Romanian",
    "ru" => "Russian",
    "rw" => "Kinyarwanda",
    "sa" => "Sanskrit",
    "sc" => "Sardinian",
    "sd" => "Sindhi",
    "se" => "Northern Sami",
    "sg" => "Sango",
    "si" => "Sinhala",
    "sk" => "Slovak",
    "sl" => "Slovenian",
    "sm" => "Samoan",
    "sn" => "Shona",
    "so" => "Somali",
    "sq" => "Albanian",
    "sr" => "Serbian",
    "ss" => "Swati",
    "st" => "Southern Sotho",
    "su" => "Sundanese",
    "sv" => "Swedish",
    "sw" => "Swahili",
    "ta" => "Tamil",
    "te" => "Telugu",
    "tg" => "Tajik",
    "th" => "Thai",
    "ti" => "Tigrinya",
    "tk" => "Turkmen",
    "tl" => "Tagalog",
    "tn" => "Tswana",
    "to" => "Tonga",
    "tr" => "Turkish",
    "ts" => "Tsonga",
    "tt" => "Tatar",
    "tw" => "Twi",
    "ty" => "Tahitian",
    "ug" => "Uighur",
    "uk" => "Ukrainian",
    "ur" => "Urdu",
    "uz" => "Uzbek",
    "ve" => "Venda",
    "vi" => "Vietnamese",
    "vo" => "Volapuk",
    "wa" => "Walloon",
    "wo" => "Wolof",
    "xh" => "Xhosa",
    "yi" => "Yiddish",
    "yo" => "Yoruba",
    "za" => "Zhuang",
    "zh" => "Chinese",
    "zu" => "Zulu"
    );
}
//Setup VIM: ex: et ts=4 enc=utf-8 :
