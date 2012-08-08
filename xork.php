<?php
/*
Plugin Name: xork
Plugin URI: http://operatorerror.org
Description: Display the contents of Weather.gov worded forecasts.
Version: 0.1.2
Author: Daniel Riti
Author URI: http://operatorerror.org
License: GPL2
*/

/*  Copyright 2012  Sharkworks  (email : dmriti@gmail.com)

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
    $plugindata['fullname']     = 'xork';
    $plugindata['nicename']     = 'xork';
    $plugindata['shortname']    = 'xork';
    $plugindata['shortnicename']= 'xork';
    $plugindata['versionhash']  = 'TQQLx9SFSu'; // This hash represents the current version

    $pluginvars['remove_tag']   = array(); // Contains the tags that has to be hidden

    // Load/enable some hooks/functions
    add_shortcode('xork', 'aXMLreader_DumpWeather');

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
