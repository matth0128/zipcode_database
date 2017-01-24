<?
// -----==========----- //
// Type: Class [zipCodeUtilities (Utility Class)]
// Filename: zipCodeUtilities.php
// Author: Matthew Heinsohn
//
// The zipCodeUtilities() class will be used for Zipcode related functions.
// -----==========----- //

namespace classes;

class zipCodeUtilities {
    function __construct(){
        //Create Database Connector
        $this->DAO = new \DAO\mysqlDAO();        
        
        //Define Zip Code Data Stale Offset
        $this->zipcode_data_stale_offset = 5184000; //60 Days
    }
    
    function __destruct(){
        unset($this->DAO);
    }
// ---------- MAIN FUNCTIONS ---------- //
    /*
     * Get Zipcodes by Radius
     * This fucntion will return a list of zipcodes with the defined radius around the provided zipcode.
     * 
     * @param integer $zipcode [Zipcode to Process]
     * @param integer $radius  [Search Radius in Miles]
     * @return array [Success & Zipcode List | Failure]
     */
    public function zipcodes_by_radius($zipcode = null, $radius = null){
        //Verify we have the $zipcode and $radius Arguments
        if(!empty($zipcode) && !empty($radius)){
            //Get Zipcode's Data
            $query = "SELECT lat, lon FROM zipData WHERE zipcode = '$zipcode'";
            $zipcode_data = $this->DAO->queryRow($query);
            //Find the Surrounding Zipcodes with-in the Radius
            if(!empty($zipcode_data)){
                //Pad the $radius Value to Improve the Zip Code Result Set
                $radius = $radius * 1.18; //118% of the Radius
                //Query for Additional Zip Codes
                $query = "SELECT zipcode FROM zipData WHERE (POW((69.1*(lon-'{$zipcode_data[lon]}')*cos({$zipcode_data[lat]}/57.3)),'2')+POW((69.1*(lat-'{$zipcode_data[lat]}')),'2'))<($radius*$radius) GROUP BY zipcode";
                $zipcode_results = $this->DAO->queryAll($query);
                if(!empty($zipcode_results)){foreach($zipcode_results as $key=>$item){$zipcode_list[] = $item[zipcode];}}
                return array("success"=>true, "zipcode_list"=>$zipcode_list);
                 
            }else{return array("success"=>false, "error"=>"Could not locate the zipcode provided");}
        }else{return array("success"=>false, "error"=>"Missing required arguments");}
    }

    /*
     * Check Zipcode Freshness
     * This function is intended to give the front-end freshness information about a specific zip code. It will check the provided zip code's "updated" value, and provide the proper response.
     * 
     * @param integer $zipcode [Zipcode to Check]
     * @return array [Success | Failure]
     */
    public function check_zipcode_data_freshness($zipcode = null){
        if(!empty($zipcode)){
            //Check Zip Code Data Status
            $query = "SELECT updated FROM zipData WHERE zipcode = '$zipcode'";
            $zipcode_updated = $this->DAO->queryOne($query);
            if(empty($zipcode_updated)){
                //Missing Zipcode Data
                return array("code"=>"200", "success"=>true, "zipcodeStatus"=>"missing", "message"=>"Zipcode Data Missing");
            }elseif((mktime() - strtotime($zipcode_updated)) >= $this->zipcode_data_stale_offset){
                //Zipcode Data is Stale...[Older Than 3 Months 5184000 Seconds = 60 Days]
                return array("code"=>"200", "success"=>true, "zipcodeStatus"=>"stale", "message"=>"Zipcode Data Stale");
            }else{
                //Zip Code Data is Current
                return array("code"=>"200", "success"=>true, "zipcodeStatus"=>"current", "message"=>"Zipcode Data is Current");
            }
        }else{return array("code"=>"401", "success"=>false, "error"=>"Missing Required Arguments");}
    }

    /*
     * Process Zipcode Update
     * This function will process the zip code update process.
     * 
     * @param integer $zipcode [Zipcode to Update]
     * @return array [Success | Failure]
     */
    public function process_zipcode_update($zipcode = null){
        if(!empty($zipcode)){
            //Get List of Surrounding Zip Codes
            $zipcodes = $this->get_zipcode_radius_data($zipcode);
            //Process Through the Zipcodes
            if(!empty($zipcodes[results])){
                //Validate the Zip Codes
                $zipcode_list = $this->verify_zipcodes($zipcodes[results]);
                //Process Validated Zip Code List
                if(!empty($zipcode_list[results])){
                    foreach($zipcode_list[results] as $key=>$item){
                        //Get the Last Update Date
                        $query = "SELECT updated FROM zipData WHERE zipcode = '{$item[zipcode]}'";
                        $zipcode_updated = $this->DAO->queryOne($query);
                        if($item[result] == "valid"){
                            if(empty($zipcode_updated)){
                                //Insert New Zip Code Record
                                //error_log(print_r("INSERT: ".$item[zipcode],1));
                                $this->insert_zipcode_data($item);                    
                            }elseif((mktime() - strtotime($zipcode_updated)) >= $this->zipcode_data_stale_offset){
                                //Update Existing Zip Code Record
                                //error_log(print_r("UPDATE: ".$item[zipcode],1));
                                $this->update_zipcode_data($item);
                            }
                        }elseif($item[result] == "invalid" && !empty($zipcode_updated)){
                            //Delete Zip Code Record
                            //error_log(print_r("DELETE: ".$item[zipcode],1));
                            $this->delete_zipcode_data($item);
                        }
                    }
                   return array("code"=>"200", "success"=>true, "message"=>"Zip Code Update Process Complete");
                }else{return array("code"=>"402", "success"=>false, "error"=>"USPS Address API Error");}
            }else{return array("code"=>"403", "success"=>false, "error"=>"ZipCodeAPI Error");}
        }else{return array("code"=>"401", "success"=>false, "error"=>"Missing Required Arguments");}
    }
// ---------- END MAIN FUNCTIONS ---------- //
// ----------==========---------- //
// ---------- ZIPCODE DATA UPDATE FUNCTIONS ---------- //
    /*
     * Update Zipcode Data
     * Update the zip code data we have stored in the "zipData" table. This function will first verify that the zip code is valid according to the USPS API.
     * 
     * @param integer $zipcode_data [Zipcode Data]
     * @return array [Success | Failure]
     */
    private function update_zipcode_data($zipcode_data = null){
        if(!empty($zipcode_data)){
            $data = $this->get_zipcode_location_data($zipcode_data[zipcode]);
            if(!empty($data[results])){
                $query = "UPDATE zipData SET lat = '".number_format($data[results][geometry][location][lat],6)."', lon = '".number_format($data[results][geometry][location][lng], 6)."', city = '{$zipcode_data[city]}', state = '{$zipcode_data[state]}', updated = NOW() WHERE zipcode = '{$zipcode_data[zipcode]}'"; 
                if($this->DAO->queryExec($query)){return array("success"=>true, "message"=>"Zip Code Updated");}
                else{return array("success"=>false, "error"=>"MySQL UPDATE Query Error");}
            }else{return array("success"=>true, "message"=>"No Zip Code Data");}
        }else{return array("success"=>false, "error"=>"Missing Required Arguments");}
    }    
    
    /*
     * Insert Zipcode Data
     * Insert the new zipcode data in the "zipData" table. This function will first verify that the zip code is valid according to the USPS API.
     * 
     * @param integer $zipcode_data [Zipcode Data]
     * @return array [Success | Failure]
     */    
    private function insert_zipcode_data($zipcode_data = null){
        if(!empty($zipcode_data)){
            $data = $this->get_zipcode_location_data($zipcode_data[zipcode]);
            if(!empty($data[results])){
                $query = "INSERT INTO zipData SET lat = '".number_format($data[results][geometry][location][lat],6)."', lon = '".number_format($data[results][geometry][location][lng], 6)."', city = '{$zipcode_data[city]}', state = '{$zipcode_data[state]}', updated = NOW(), zipcode = '{$zipcode_data[zipcode]}'"; 
                if($this->DAO->queryExec($query)){return array("success"=>true, "message"=>"Zip Code Inserted");}
                else{return array("success"=>false, "error"=>"MySQL INSERT Query Error");}
            }else{return array("success"=>true, "message"=>"No Zip Code Data");}
        }else{return array("success"=>false, "error"=>"Missing Required Arguments");}
    }

    /*
     * Delete Zipcode Data
     * Delete the zipcode data in the "zipData" table. The zipcode is no longer used by USPS.
     * 
     * @param integer $zipcode_data [Zipcode Data]
     * @return array [Success | Failure]
     */    
    private function delete_zipcode_data($zipcode_data = null){
        if(!empty($zipcode_data[zipcode])){
            $query = "DELETE FROM zipData WHERE zipcode = '{$zipcode_data[zipcode]}'";
            if($this->DAO->queryExec($query)){return array("success"=>true, "message"=>"Zip Code Deleted");}
            else{return array("success"=>false, "error"=>"MySQL DELETE Query Error");}
        }else{return array("success"=>false, "error"=>"Missing Required Arguments");}
    }

    /*
     * Verify Zip Codes
     * Query the USPS API to verify the zip codes are currently used. Return the City/State if the zip code is still recognized by the USPS.
     * NOTE: This function takes an array of zip codes and breaks them in to 5 item segments. This is due to the fact that the USPS API will only process 5 zip codes in one request.
     * 
     * @param array $zipcodes [Array of Zipcodes]
     * @return array [Success | Failure]
     */
    private function verify_zipcodes($zipcodes = null){
        if(!empty($zipcodes)){
            //Break $zipcodes Array in to 5 item segments
            $zipcodes = array_chunk($zipcodes, 5); 
            //Process through the Zip Code Lists
            foreach($zipcodes as $key=>$item){
                //Prepare the XML Request
                $xml_data[] = "<CityStateLookupRequest USERID='".USPS_API_KEY."'>";
                foreach($item as $keyy=>$itemm){
                    $xml_data[] = "<ZipCode ID='$itemm'>";
                    $xml_data[] = "<Zip5>".$itemm."</Zip5>";
                    $xml_data[] = "</ZipCode>";
                }
                $xml_data[] = "</CityStateLookupRequest>";
                $xml_string = implode($xml_data, "");
                //Query USPS API for Zip Code Data
                $url = "http://production.shippingapis.com/ShippingAPI.dll?API=CityStateLookup&XML=".urlencode($xml_string);
                $curl = curl_init();
                curl_setopt_array($curl, array(CURLOPT_RETURNTRANSFER=>1, CURLOPT_URL=>$url));
                $response = curl_exec($curl);
                //Process Response
                if(!empty($response)){
                    $response = simplexml_load_string($response);
                    foreach($response->ZipCode as $keyy=>$itemm){
                        if(!empty($itemm->Zip5)){$zipcode_list[(string)$itemm->attributes()->ID] = array("zipcode"=>(string)$itemm->attributes()->ID, "result"=>"valid", "city"=>(string)$itemm->City, "state"=>(string)$itemm->State);}
                        elseif(!empty($itemm->Error)){$zipcode_list[(string)$itemm->attributes()->ID] = array("zipcode"=>(string)$itemm->attributes()->ID, "result"=>"invalid");}
                    }
                }
                //Reset the $xml_data Array
                unset($xml_data);
            }
            //Return Results
            return array("success"=>true, "results"=>$zipcode_list);
        }else{return array("success"=>false, "error"=>"Missing Required Arguments");}
    }

    /*
     * Get Zipcode Location Data
     * Query the Google Maps API for zip code geo-location data. It will only return data if we know that a 'postal_code' type is passed back from the Google Maps API.
     * 
     * @param integer $zipcode [Zipcode]
     * @return array [Success | Failure]
     */
    private function get_zipcode_location_data($zipcode = null){
        if(!empty($zipcode)){
            //Prepare $url Parameter
            $url = "http://maps.googleapis.com/maps/api/geocode/json?address=".$zipcode;
            //Query Google Maps API
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $response = json_decode(curl_exec($curl), true);
            //Pause if Query Limit Reached
            if($response[status] == "OVER_QUERY_LIMIT"){
                sleep(1);
                //Query Google Maps API
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                $response = json_decode(curl_exec($curl), true);                
            }
            if($response[status] == "ZERO_RESULTS"){return array("success"=>true, "message"=>"No Results", "results"=>null);} 
            elseif(!empty($response[results])){
                $results = array_pop($response[results]);
                if(in_array("postal_code", $results[types])){return array("success"=>true, "message"=>"Retreived Zip Code Data", "results"=>$results);}
                else{return array("success"=>true, "message"=>"Could Not Locate Zip Code", "results"=>null);}
            }else{return array("success"=>true, "message"=>"Could Not Locate Zip Code", "results"=>null);}
        }else{return array("success"=>false, "error"=>"Missing Required Arguments");}     
    }

    /*
     * Get Zipcode Radius Data
     * Query the ZipCodeAPI service for zip codes in a 50 mile radius.
     * 
     * @param integer $zipcode [Zipcode]
     * @return array [Success | Failure]
     */
    private function get_zipcode_radius_data($zipcode = null){
        if(!empty($zipcode)){
            //Prepare CURL Request
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, "http://www.zipcodeapi.com/rest/".ZIPCODEAPI_KEY."/radius.json/".$zipcode."/50/miles");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            //Query ZipCodeAPI
            $response = json_decode(curl_exec($curl), true);
            //Return Results
            if(!empty($response[zip_codes])){
                foreach($response[zip_codes] as $key=>$item){$zipcode_results[] = $item[zip_code];}
                return array("success"=>true, "results"=>$zipcode_results);
            }else{return array("success"=>true, "results"=>null);}
        }else{return array("success"=>false, "error"=>"Missing Required Arguments");}
    }
// ---------- END ZIPCODE DATA UPDATE FUNCTIONS ---------- //
}
    
