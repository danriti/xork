<?php
/*
Plugin Name: xork
Plugin URI: http://operatorerror.org
Description: Display the contents of Weather.gov worded forecasts.
Version: 0.1.1
Author: Daniel Riti
Author URI: http://operatorerror.org
License: GPL2
*/

/*  Copyright 2011  RaymondDesign  (email : webmaster@raymonddesign.nl)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


// Define some plugin variables
    $plugindata['dir']          = basename(dirname(__FILE__));
    $plugindata['fullname']     = 'Advanced XML Reader';
    $plugindata['nicename']     = 'advanced-xml-reader';
    $plugindata['shortname']    = 'Advanced XML';
    $plugindata['shortnicename']= 'advanced-xml';
    $plugindata['versionhash']  = 'TQQLx9SFSu'; // This hash represents the current version

    $pluginvars['remove_tag']   = array(); // Contains the tags that has to be hidden

// Load/enable some hooks/functions
    load_plugin_textdomain( 'advanced-xml-reader', 'wp-content/plugins/'.$plugindata['dir']);
    add_action('admin_menu', 'aXMLreader_AdminMenu');
    add_action('the_content', 'aXMLreader_ParseTags',0); // DEPRECATED as of version 0.3.4
    add_filter('widget_title', 'aXMLreader_ParseTags'); // DEPRECATED as of version 0.3.4
    add_filter('widget_text', 'aXMLreader_ParseTags'); // DEPRECATED as of version 0.3.4
    add_shortcode('advanced-xml', 'aXMLreader_ParseShortcode');
    add_shortcode('xork', 'aXMLreader_DumpWeather');

// Define some usefull functions
    /**
     * Cuts a string after $number characters and puts ... after it
     * Return: string
     */
        function aXMLreader_cutstr($string, $number){
            if(strlen($string) > $number){
                $string = substr($string,0,$number).'...';
            }
            return $string;
        }
     /**
     * Transforms all urls and e-mail adresses into clickable links and transforms images in html img tags
     * Return: string
     */
        function aXMLreader_makeclickable($text)
        {
            $return = preg_replace('#(script|about|applet|activex|chrome):#is', "\\1:", $text);
            $return = preg_replace_callback("#([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", 'aXMLreader_parsetag', $return);
            $return = preg_replace("#([a-z0-9&\-_.]+?)@([\w\-]+\.([\w\-\.]+\.)*[\w]+)#i", "<a href=\"mailto:\\1@\\2\">\\1@\\2</a>", $return);
            return $return;
        }

        function aXMLreader_parsetag($matches){
            $ext = array('jpg', 'png', 'gif', 'bmp');
            if(in_array(substr($matches[0],-3,3),$ext)){
                return '<img src="'.$matches[0].'" alt="'.$matches[0].'" />';
            }
            else{
                return '<a href="http://'.$matches[0].'">'.$matches[0].'</a>';
            }
        }

    /**
     * Transforms the raw SimpleXMLElement array into a 3 dimensional array
     * Return: Array
     */
        function aXMLreader_show_data($array,$return=array(),$parent=''){
            global $list_count, $pluginvars;
            $children = $array->children();
            foreach($children as $key => $child){
                $strchild = trim(strval($child));
                if(!empty($strchild) && !in_array($key,$pluginvars['remove_tag'])){
                    if(empty($parent)){
                        $return[strtolower($child->getName())][0] = $strchild;
                    }else{
                        if(!isset($list_count[$parent])){ $list_count[$parent] = 1; }
                        $return[$parent][$list_count[$parent]][] = $strchild;
                    }
                }
                if(false !== $child->children()){
                    $return = aXMLreader_show_data($child,$return,strtolower($child->getName()));
                }
            }
            if(count($children) != 0){
                $list_count[$parent]++;
            }
            return $return;
        }

    /**
     * Transforms the array from aXMLreader_show_data into two arrays, which can be used in str_replace
     *
     * DEPRECATED as of version 0.3.4
     *
     * Return: A array containing two arrays: patterns & replacements
     */
        function aXMLreader_create_replace_arrays($array, $row_delimiter, $item_delimiter){
            global $plugindata;
            $pattern = $replace = array();
            foreach($array as $key => $value){
                foreach($value as $key2 => $value2){
                    if(is_array($value2)){
                        $array[$key][$key2] = implode($item_delimiter,aXMLreader_makeclickable($array[$key][$key2]));
                    }
                }
                $pattern[] = '['.$plugindata['shortnicename'].':'.$key.']';
                $replace[] = implode($row_delimiter,$array[$key]);
            }
            return array('pattern' => $pattern, 'replace' => $replace);
        }

     /**
     * Merge multiple items for same tag into one string
     *
     * Return: string
     */
        function aXMLreader_merge_data($array, $row_delimiter, $item_delimiter){
            global $plugindata;
            foreach($array as $key => $value){
                if(is_array($value)){
                    $array[$key] = implode($item_delimiter,aXMLreader_makeclickable($array[$key]));
                }
            }
            return implode($row_delimiter,$array);
        }

//
// Back end plugin code
//
    /**
     * Write the admin link in the Settings menu
     * Return: -
     */
        function aXMLreader_AdminMenu(){
            global $plugindata;
            add_options_page($plugindata['fullname'], $plugindata['shortname'], 9, $plugindata['nicename'], 'aXMLreader_AdminPage');
            add_action('admin_init', 'register_aXMLreader_settings');
        }

    /**
     * Register allowed settings fields
     */
        function register_aXMLreader_settings() {
            global $plugindata;
            register_setting($plugindata['nicename'], $plugindata['nicename'].'_feed');
            register_setting($plugindata['nicename'], $plugindata['nicename'].'_itemdel');
            register_setting($plugindata['nicename'], $plugindata['nicename'].'_rowdel');
            register_setting($plugindata['nicename'], $plugindata['nicename'].'_hidetag');
        }

    /**
     * Show the admin page to manage feeds
     * Return: - (echo is used)
     */

        function aXMLreader_AdminPage(){
            global $plugindata;
            echo '<div class="wrap">
                    <h2>'.$plugindata['fullname'].' '.__('Options', 'advanced-xml-reader').'</h2>
                    <form method="post" action="options.php">';
                        settings_fields($plugindata['nicename']);
            echo '      <table class="form-table">
                            <tr valign="top">
                            <th scope="row">'.__('XML file', 'advanced-xml-reader').'</th>
                            <td><input type="text" name="'.$plugindata['nicename'].'_feed" value="'.get_option($plugindata['nicename'].'_feed').'" /></td>
                            </tr>
                            <tr valign="top">
                            <th scope="row">'.__('Item delimiter', 'advanced-xml-reader').'</th>
                            <td><input type="text" name="'.$plugindata['nicename'].'_itemdel" value="'.get_option($plugindata['nicename'].'_itemdel').'" /><br />
                                <small>'.__('Eg', 'advanced-xml-reader').': <pre style="display: inline;">-</pre>, <pre style="display: inline;">/</pre>, <pre style="display: inline;">&lt;/td&gt;&lt;td&gt;</pre></small></td>
                            </tr>
                            <tr valign="top">
                            <th scope="row">'.__('Row delimiter', 'advanced-xml-reader').'</th>
                            <td><input type="text" name="'.$plugindata['nicename'].'_rowdel" value="'.get_option($plugindata['nicename'].'_rowdel').'" /><br />
                                <small>'.__('Eg', 'advanced-xml-reader').': <pre style="display: inline;">&lt;br /&gt;</pre>, <pre style="display: inline;">&lt;/li&gt;&lt;li&gt;</pre>, <pre style="display: inline;">&lt;/tr&gt;&lt;tr&gt;</pre></small></td>
                            </tr>
                            <tr valign="top">
                            <th scope="row">'.__('Hide tags', 'advanced-xml-reader').'</th>
                            <td><input type="text" name="'.$plugindata['nicename'].'_hidetag" value="'.get_option($plugindata['nicename'].'_hidetag').'" /><br />
                                <small>'.__('Comma separated list of tags you want to be hidden. Case sensitive!', 'advanced-xml-reader').'</small></td>
                            </tr>
                        </table>
                        <p class="submit">
                        <input type="submit" class="button-primary" value="'.__('Save Changes', 'advanced-xml-reader').'" />
                        </p>
                    </form>
                </div>';
            if($feed = get_option($plugindata['nicename'].'_feed')){
                $data = wp_remote_get($feed);
                if(array_key_exists('errors', $data)){
                    echo __('Unable to load XML file!', 'advanced-xml-reader');
                }else{
                    $xml = new SimpleXmlElement($data['body'], LIBXML_NOCDATA);
                    $taglist = aXMLreader_show_data($xml);
                    echo '<table>
                            <tr><td><strong>'.__('XML tag', 'advanced-xml-reader').'</strong></td><td><strong>'.__('Value', 'advanced-xml-reader').'</strong></td><td><strong>'.__('How to use in a post', 'advanced-xml-reader').'</strong></td></tr>';
                        foreach($taglist as $key => $value){
                            $tmpvalue = '<em>This is a list containing multiple values.</em>';
                            if(isset($value[0])){
                                $tmpvalue = aXMLreader_cutstr($value[0],30);
                            }
                            echo '<tr><td>'.$key.'</td><td>'.$tmpvalue.'</td><td>['.$plugindata['shortnicename'].' tag=&quot;'.$key.'&quot;]</td></tr>';
                        }
                    echo '</table>';
                }
            }
            echo '<div style="border: 1px solid #ccc; background-color: #EEEEEE; padding: 5px;padding-bottom:13px; width: 700px;margin-top:30px;">
                        <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
                            <input type="hidden" name="cmd" value="_s-xclick">
                            <input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----
                            ">
                            <input style="float: left;" type="image" src="https://www.paypal.com/en_GB/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online.">
                            <img alt="" border="0" src="https://www.paypal.com/nl_NL/i/scr/pixel.gif" width="1" height="1">
                        </form>
                        <div style="margin-top: 7px; padding-left: 110px;">'.__('If you like this plugin you can contribute by making a small donation to the author.', 'advanced-xml-reader').'</div>
                    </div><br />
                    <img src="http://script.raymonddesign.nl/logo_'.$plugindata['versionhash'].'.jpg" alt="Logo" />';
        }

//
//Front end plugin code
//
    /**
     * Replaces the tags used in posts in real values
     *
     * DEPRECATED as of version 0.3.4
     *
     * Return: $text (string)
     */
     function aXMLreader_ParseTags($text){
        global $plugindata, $pluginvars;
        if($feed = get_option($plugindata['nicename'].'_feed')){
            $data = wp_remote_get($feed);
            if(!array_key_exists('errors', $data)){
                $xml = new SimpleXmlElement($data['body'], LIBXML_NOCDATA);
                $pluginvars['remove_tag'] = explode(',',str_replace(' ','',get_option($plugindata['nicename'].'_hidetag')));
                $taglist = aXMLreader_create_replace_arrays(aXMLreader_show_data($xml),get_option($plugindata['nicename'].'_rowdel'),get_option($plugindata['nicename'].'_itemdel'));
                //aXMLreader_multiarray_walk($xml,'aXMLreader_fill_replace_arrays');
                $text = str_replace($taglist['pattern'], $taglist['replace'], $text);
            }
            // Do nothing on error, we don't want visitors to see errors.
        }
        return $text;
     }

     /**
     * Parse Wordpress shortcode tags
     * Return: $text (string)
     */
     function aXMLreader_ParseShortcode($atts){
        global $plugindata;
        extract(shortcode_atts(array('tag' => false), $atts));
        if($tag !== false){
	        $tag = strtolower($tag);
	        if($feed = get_option($plugindata['nicename'].'_feed')){
	            $data = wp_remote_get($feed);
	            if(!array_key_exists('errors', $data)){
	                $xml = new SimpleXmlElement($data['body'], LIBXML_NOCDATA);
	                $pluginvars['remove_tag'] = explode(',',str_replace(' ','',get_option($plugindata['nicename'].'_hidetag')));
	                $data = aXMLreader_show_data($xml);
	                if(array_key_exists($tag,$data)){
		                $text = aXMLreader_merge_data($data[$tag],get_option($plugindata['nicename'].'_rowdel'),get_option($plugindata['nicename'].'_itemdel'));
		                return $text;
	                }
	            }
	        }
        }
        return false;
     }

     /**
     * Extract the weather information
     * Return: $weather (array of strings)
     */
     function getWeatherArray($xml){

        $weather = array();

        $xpath = "/Forecast/period";

        foreach ($xml->xpath($xpath) as $period) {
          array_push($weather, $period->text);
        }

        return $weather;
     }


     /**
     * Extract the day information
     * Return: $days (array of strings)
     */
     function getDayArray($xml){

        $days = array();

        $xpath = "/Forecast/period";

        foreach ($xml->xpath($xpath) as $period) {
          array_push($days, $period->valid);
        }

        return $days;
     }


     /**
     * Merge the weather information
     * Return: string
     */
     function mergeDayWeatherInfo($days, $weather, $delimiter){

       $dayWeather = array();

       $size = sizeof($days);

       for($i=0; $i<$size; $i++) {
         array_push($dayWeather, "<strong>".$days[$i].":</strong></br>".$weather[$i]);
       }

       return implode($dayWeather, $delimiter);
     }


     /**
     * Dump weather data.
     * Return: $text (string)
     */
     function aXMLreader_DumpWeather($atts){
        global $plugindata;

        $xml = simplexml_load_file('http://forecast.weather.gov/MapClick.php?lat=40.64&lon=-72.35&FcstType=xml');

        $days = getDayArray($xml);
        $weather = getWeatherArray($xml);
        $delimiter = "<br /><br />";

        return mergeDayWeatherInfo($days, $weather, $delimiter);
     }
?>
