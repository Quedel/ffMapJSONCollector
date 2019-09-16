<?php
/**
 *  
 * Copyright (c) 2019, Freifunk Harz e.V. - Helmut Wenzel <h.wenzel@harz.freifunk.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.
 */

class ffMapJSONCollector {

    private $config             = [];
    private $returnStatus       = [];
    private $outputJSON         = [];

    public function __construct() {
        
        if (!extension_loaded('curl')) {
            trigger_error('The PHP curl extension is not loaded. Please correct this before proceeding!');
            exit();
        }

        if(file_exists("config.json")){
            $this->config = json_decode(file_get_contents("config.json"),true);
        }
        else {
            trigger_error('The config.json does not exist.');
            exit();
        }


        if($this->config['mapviewer'] == "hopglass") {
            $this->outputJSON['nodes'] == null;
            $this->outputJSON['graph'] == null;
        }
        elseif($this->config['mapviewer'] == "meshviewer") {
            $this->outputJSON['meshviewer'] == null;
        }
        if($this->config['nodelist']) {
            $this->outputJSON['nodelist'] == null;
        }
    }

    public function execute() {

        $this->returnStatus['start_time'] = date(DATE_ISO8601);

        $this->getJSON();
        $this->mergeJSON();

        $this->returnStatus['end_time'] = date(DATE_ISO8601);

        return json_encode($this->returnStatus,JSON_PRETTY_PRINT);
    }
  
    private function download($url, $path)
    {   
        $return = true;

        if(is_file($url)) {
            copy($url, $path); 
        } else {
            $httpCode = $this->checkURL($url);

            if($httpCode >= 200 && $httpCode <= 300) {

                $options = array(
                CURLOPT_FILE    => fopen($path, 'w'),
                CURLOPT_TIMEOUT =>  28800,
                CURLOPT_URL     => $url
                );
        
                $ch = curl_init();
                curl_setopt_array($ch, $options);
                curl_exec($ch);

                if (curl_errno($ch)) {
                    if($this->config['debug']) $this->returnStatus['z_debug'][] = ("cURL error for URL '$url': " . curl_error($ch));
                    $return = false;    
                }

                curl_close($ch);
            }
            else {
                if($this->config['debug']) $this->returnStatus['z_debug'][] = ("HTTP error for URL '$url': " . $httpCode);
                $return = false;                   
            }
        }
        return $return;
    }
   
    private function checkURL($url) {
        $handle = curl_init($url);
        curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);    
        $response = curl_exec($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
    
        return $httpCode;
    }
  
    private function getJSON() {
        
        if(is_array($this->config['datasources'])) {
            foreach ($this->config['datasources'] as $name => $data) {

                if(isset($data['update'])) {
                    $httpCode = $this->checkURL($data['update']);
                    if($httpCode >= 200 && $httpCode <= 300) {
                        $this->returnStatus['update'][$name] = true;
                    }
                    else {
                        $this->returnStatus['update'][$name] = false;
                    }
                }
                
                if($this->config['mapviewer'] == "hopglass") {
                    $this->returnStatus['download'][$name]['nodes.json'] = $this->download($data['url']."nodes.json", "tmp/$name.nodes.json");
                    $this->returnStatus['download'][$name]['graph.json'] = $this->download($data['url']."graph.json", "tmp/$name.graph.json");
                }
                elseif($this->config['mapviewer'] == "meshviewer") {
                    $this->returnStatus['download'][$name]['meshviewer.json'] = $this->download($data['url']."nodes.json", "tmp/$name.meshviewer.json");
                }

                if($this->config['nodelist']) {
                    $this->returnStatus['download'][$name]['nodelist.json'] = $this->download($data['url']."nodelist.json", "tmp/$name.nodelist.json");
                }
            }

            return true;
        }
        else {
            if($this->config['debug']) $this->returnStatus['z_debug'][] = ("No data sources defined in config.json");
            return false;
        }
    }

    private function mergeJSON() {
        if(is_array($this->config['datasources'])) {
            foreach ($this->config['datasources'] as $name => $data) {
                
                if($this->config['mapviewer'] == "hopglass") {
                    $nodes = $this->readJSONasArray("tmp/$name.nodes.json");
                    $graph = $this->readJSONasArray("tmp/$name.graph.json");

                    if(strtotime($nodes['timestamp']) >= (time()-86400*$this->config['outdated-days'])) {

                        $this->returnStatus['generate']['nodes.json'] = $this->mergeNodesJSON($nodes);
                        $this->returnStatus['generate']['graph.json'] = $this->mergeGraphJSON($graph);

                        $this->returnStatus['write']['nodes.json'] = $this->writeJSON('nodes');
                        $this->returnStatus['write']['graph.json'] = $this->writeJSON('graph');
                    }
                    else {
                        $this->returnStatus['generate']['nodes.json'] = false;
                        $this->returnStatus['generate']['graph.json'] = false;

                        if($this->config['debug']) $this->returnStatus['z_debug'][] = ("$name is to old.");
                    }

                }
                elseif($this->config['mapviewer'] == "meshviewer") {
                    $meshviewer = $this->readJSONasArray("tmp/$name.meshviewer.json");

                    if(strtotime($meshviewer['timestamp']) >= (time()-86400*$this->config['outdated-days'])) {

                        $this->returnStatus['generate']['meshviewer.json'] = $this->mergeMeshviewerJSON($meshviewer);
                        $this->returnStatus['write']['meshviewer.json'] = $this->writeJSON('meshviewer');
                    }
                    else {
                        $this->returnStatus['generate']['meshviewer.json'] = false;
                        if($this->config['debug']) $this->returnStatus['z_debug'][] = ("$name is to old.");
                    }                        
                }

                if($this->config['nodelist']) {
                    $list = $this->readJSONasArray("tmp/$name.nodelist.json");

                    if(strtotime($list['updated_at']) >= (time()-86400*$this->config['outdated-days'])) {

                        $this->returnStatus['generate']['nodelist.json'] = $this->mergeNodelistJSON($list);
                        $this->returnStatus['write']['nodelist.json'] = $this->writeJSON('nodelist');
                    }
                    else {
                        $this->returnStatus['generate']['nodelist.json'] = false;
                        if($this->config['debug']) $this->returnStatus['z_debug'][] = ("$name is to old.");
                    }                           
                }
            }
        }
        else {
            if($this->config['debug']) $this->returnStatus['z_debug'][] = ("No data sources defined in config.json");
            return false;
        }        

    }

    private function readJSONasArray($path) {
        if(file_exists($path)){
            return json_decode(file_get_contents($path),true);
        }
        else {
            if($this->config['debug']) $this->returnStatus['z_debug'][] = ("The file $path does not exist. -> ignore");
            return false;
        }        
    }

    private function mergeNodesJSON($data) {
        if(isset($data['version']) && isset($data['timestamp']) && is_array($data['nodes'])) {
            if(is_null($this->outputJSON['nodes'])) {
                $this->outputJSON['nodes']['version'] = 2;
                $this->outputJSON['nodes']['timestamp'] = date(DATE_ISO8601);
                $this->outputJSON['nodes']['nodes'] = $data['nodes'];
            }
            else {
                foreach ($data['nodes'] as $node) {
                    $this->outputJSON['nodes']['nodes'][] = $node;
                }
            }
            return true;
        }
        else {
            if($this->config['debug']) $this->returnStatus['z_debug'][] = ("JSON input do not have the correct form for nodes.json.");
            return false;
        }

    }

    private function mergeGraphJSON($data) {
        if(isset($data['version']) && isset($data['timestamp']) && is_array($data['batadv'])) {
            if(is_null($this->outputJSON['graph'])) {
                $this->outputJSON['graph']['version'] = 1;
                $this->outputJSON['graph']['timestamp'] = date(DATE_ISO8601);
                $this->outputJSON['graph']['graph'] = null;
                $this->outputJSON['graph']['batadv']['multigraph'] = false;
                $this->outputJSON['graph']['batadv']['directed'] = false;
                $this->outputJSON['graph']['batadv']['nodes'] = $data['batadv']['nodes'];
                $this->outputJSON['graph']['batadv']['links'] = $data['batadv']['links'];
            }
            else {
                $offset = count($this->outputJSON['graph']['batadv']['nodes']);
                $target = $offset;

                foreach ($data['batadv']['nodes'] as $node) {
                    $this->outputJSON['graph']['batadv']['nodes'][] = $node;
                }

                foreach ($data['batadv']['links'] as $link) {
                    
                    $new_link = array();
                    $new_link['source'] = ++$offset;
                    $new_link['target'] = $target;
                    $new_link['tq'] = 1;
                    $new_link['type'] = 'other';

                    $this->outputJSON['graph']['batadv']['links'][] = $new_link;
                }
            }
            return true;
        }
        else {
            if($this->config['debug']) $this->returnStatus['z_debug'][] = ("JSON input do not have the correct form for graph.json.");
            return false;
        }

    }

    private function mergeMeshviewerJSON($data) {
        if($this->config['debug']) $this->returnStatus['z_debug'][] = ("Meshviewer not yet developed.");
        return false;
    }

    private function mergeNodelistJSON($data) {
        if(isset($data['version']) && isset($data['updated_at']) && is_array($data['nodes'])) {
            if(is_null($this->outputJSON['nodelist'])) {
                $this->outputJSON['nodelist']['version'] = "1.0.1";
                $this->outputJSON['nodelist']['updated_at'] = date(DATE_ISO8601);
                $this->outputJSON['nodelist']['nodes'] = $data['nodes'];
            }
            else {
                foreach ($data['nodes'] as $node) {
                    $this->outputJSON['nodelist']['nodes'][] = $node;
                }
            }
            return true;
        }
        else {
            if($this->config['debug']) $this->returnStatus['z_debug'][] = ("JSON input do not have the correct form for nodelist.json.");
            return false;
        }
    }

    private function writeJSON($json) {

        if(is_array($this->outputJSON[$json])) {
            return file_put_contents("data/".$json.".json", json_encode($this->outputJSON[$json],JSON_PRETTY_PRINT));
        }
        else {
            if($this->config['debug']) $this->returnStatus['z_debug'][] = ("Data for output ist not an array.");
            return false;
        }
    }
}


