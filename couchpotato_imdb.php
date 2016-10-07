<?php

    /*
     * Written By: Tobias
     */

     // MySQL details
     define('SQLHOST', 'localhost'); //The MySQL host ip/fqdn
     define('SQLLOGIN', '');//The MySQL login
     define('SQLPASS', ''); //The MySQL password
     define('SQLDB', ''); //The MySQL database to use
     define('SQLPORT', 3306); //The MySQL port to connect on
     define('SQLSOCK', '/var/run/mysqld/mysqld.sock');

    // Make sure we can use the mysqli extension
    if (!extension_loaded ('mysqli')) {
        echo json_encode(array("error" => "Mysqli Extension not loaded."));
        die();
    }

    // Set content type
    header('Content-Type: application/json');

    // Do parameters match what we expect?
    if (empty($_REQUEST['passkey']) || strlen($_REQUEST['passkey']) != 32 || (empty($_REQUEST['imdbid']) && empty($_REQUEST['search'])) || empty($_REQUEST['user'])) {
        echo json_encode(array("error" => "Incorrect parameters."));
        die();
    }

    // Connect to DB manually for exposed service. Variables below come from config.php loaded above
    $DbLink = mysqli_connect(SQLHOST, SQLLOGIN, SQLPASS, SQLDB, SQLPORT, SQLSOCK) or die("Error: " . mysqli_error($DbLink));

    // Get the Username, passkey and\or IMDb ID\search string from request
    $Username = $DbLink->escape_string($_REQUEST['user']);
    $PassKey = $DbLink->escape_string($_REQUEST['passkey']);
    $IMDbID = $DbLink->escape_string($_REQUEST['imdbid']);
    //$SearchString = $DbLink->escape_string($_REQUEST['search']);

    //imdb
    //url
    $url = 'http://www.imdb.com/title/'.$IMDbID.'/';

    //get the page content
    $imdb_content = get_data($url);

    //parse for product name
    $name = get_match('/<title>(.*)<\/title>/isU',$imdb_content);

    //build content
    $content = ''.$name.'';
    $content = substr($content, 0, strpos($content, " ("));
    $content = str_replace(' ', '.', $content);
    $content = preg_replace('/[^A-Za-z0-9\.]/', '', $content);
    //echo $content;

    //gets the match content
    function get_match($regex,$content)
    {
	preg_match($regex,$content,$matches);
	return $matches[1];
    }

    //gets the data from a URL
    function get_data($url)
    {
	$ch = curl_init();
	$timeout = 5;
        $headers = array('Accept-Language: de-de;q=0.8,de;q=0.5',);
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
        curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
    }

    // Things look good, let's find this media
    $MediaQuery = "SELECT id, title, link, content FROM ttrss_entries WHERE title LIKE '%$content%'";
    $MediaLookupResults = $DbLink->query($MediaQuery);

    // Check for results
    $TotalResults = $MediaLookupResults->num_rows;
    if ($TotalResults == 0) {
        echo json_encode(array("total_results" => $TotalResults));
        }
        else {
            // Initialize for output
            $JSONOutput = array();

            // For each returned row build JSON output
            while($ResultRow = mysqli_fetch_array($MediaLookupResults)) {

                // Release Name
                $Name = $ResultRow['title'];

                // Download URL
                $DownloadURL = $ResultRow['link'];

                // Get download size (B -> KB -> MB)
                $content = $ResultRow['content'];
                $Size = get_match('/(\s[0-9].*|^[0-9].*)[G,M]/isU',$content);
                $Size = str_replace(' ', '', $Size);
                if ($Size < 10) {
                    $Size = round($Size * 1024, 0);
                    }
                    else {
                    $Size = round($Size, 0);
                }
                $MediaSize = $Size;

                // Build array for JSON encoding
                $Details = array("release_name" => $Name,
                                 "download_url" => $DownloadURL,
                                 "imdb_id" => $IMDbID,
                                 "size" => $MediaSize);

                // Add to final output array
                array_push($JSONOutput, $Details);
            }

            // Encode and return data!!
            echo json_encode(array("results" => $JSONOutput, "total_results" => $TotalResults));
        }
