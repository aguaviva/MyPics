<?php
    header("Content-Type: text/plain");

    $fp = fopen('/tmp/php-commit.lock', 'c');
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        print("flock!");
        exit;
    }

    $images_dir = 'pics/';
    $thumbs_dir = 'pics/thumbs/';

    function format_gps_data($gpsdata,$lat_lon_ref)
    {
        $gps_info = array();
        foreach($gpsdata as $gps)
        {
            list($j , $k) = explode('/', $gps);
            array_push($gps_info,$j/$k);
        }
        $coordination = $gps_info[0] + ($gps_info[1]/60.00) + ($gps_info[2]/3600.00);
        return (($lat_lon_ref == "S" || $lat_lon_ref == "W" ) ? '-'.$coordination : $coordination).' '.$lat_lon_ref;
    }

    function get_gps($exif_gps)
    {
        $lat = 0;
        $lon = 0;
        if (array_key_exists("GPS", $exif_gps))
        {
            $details = $exif_gps["GPS"];
            if (array_key_exists("GPSLatitude", $details) && array_key_exists("GPSLongitude", $details))
            {
                $lat = format_gps_data($details['GPSLatitude'],$details['GPSLatitudeRef']);
                $lon = format_gps_data($details['GPSLongitude'],$details['GPSLongitudeRef']);
            }
        }
        return array($lat, $lon);
    }
    
    function get_orientation($exif) {
        $angle = 0;
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
            case 3:
                $angle = 180 ;
                break;
        
            case 6:
                $angle = 270;
                break;
        
            case 8:
                $angle = 90; 
                break;
            default:
                $angle = 0;
                break;
            }   
        }   
        return $angle;
    }
    
    function make_thumb($src, $dest, $angle, $desired_height) {
        /* read the source image */
        $source_image = imagecreatefromjpeg($src);
      
        $source_image = imagerotate($source_image, $angle, 0);
        
        $width = imagesx($source_image);
        $height = imagesy($source_image);
        
        /* find the “desired height” of this thumbnail, relative to the desired width  */
        $desired_width = floor(($width * $desired_height) / $height);
        
        /* create a new, “virtual” image */
        $virtual_image = imagecreatetruecolor($desired_width, $desired_height);
        
        /* copy source image at a resized size */
        imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $desired_width, $desired_height, $width, $height);
        
        /* create the physical thumbnail image to its destination */
        imagejpeg($virtual_image, $dest);
    }    

    function get_list_of_images_sorted($images_dir)
    {
        $glob_files = glob($images_dir.'*.{jpg,jpeg}', GLOB_BRACE);
        $glob_files = array_map('basename', $glob_files); 
        $file_mtimes = array();
        foreach($glob_files as $file)
        {
            $file_mtimes[$file] = filemtime($images_dir.$file);
        }
        usort($glob_files, function($a, $b) {
            global $file_mtimes;
            return $file_mtimes[$b] - $file_mtimes[$a];
        });
        return $glob_files; 
    }
    
    function find_new_files($db, $glob_files)
    {
        $known_files = array();
        $qResult = $db->query("SELECT name FROM images");
        while ($row = $qResult->fetchArray()) 
        {
            array_push($known_files, $row["name"]);
        }
    
        # get new files
        $new_files = array();
        foreach($glob_files as $file)
        {
            if (array_search($file, $known_files) === false)
            {
                array_push($new_files, $file);
            }
        }
        return $new_files;
    }

    function create_tasks($db, $images_dir)
    {
        $glob_files = get_list_of_images_sorted($images_dir);
        $new_files = find_new_files($db, $glob_files);
        
        $db_tasks = tasks_get_db();
        foreach($new_files as $file)
        {
            print("'$file' </br>");
            tasks_push_back($db_tasks, $images_dir.$file);
        }
        $db_tasks->close();            
    }


    # open database & crate tables
    $database_filename = 'test.db';
    $db = new SQLite3($database_filename);
    $db->exec("CREATE TABLE IF NOT EXISTS images(id INTEGER PRIMARY KEY, name TEXT UNIQUE, date TEXT, width INT, height INT, twidth INT, theight INT, lat TEXT, lon TEXT)");
    $db->exec("CREATE TABLE IF NOT EXISTS tags(id INTEGER PRIMARY KEY, name TEXT UNIQUE)");
    $db->exec("CREATE TABLE IF NOT EXISTS imgs_tags(img_id INTEGER, tag_id INTEGER, UNIQUE(img_id, tag_id))");


    include 'task.php';    
    
    //create_tasks($db, $images_dir);    die();
    
    while(true)
    {
        $err = false;
        
        $db_tasks = tasks_get_db();
        $first_task = tasks_get_first_task($db_tasks);
        $db_tasks->close();
        
        if ($first_task==false)
            break;
        
        $img_file = $first_task["name"];
        $basefile = basename($img_file);
        //$img_file = $images_dir.$file;
        
        $thumbnail_file = $thumbs_dir.$basefile;
        print($img_file);
        
        $exif_array = exif_read_data($img_file,  null, true);
        //var_dump($exif_array);
        
        if (array_key_exists("COMPUTED", $exif_array))
        {
            $computed = $exif_array['COMPUTED'];
            $width = $computed["Width"];
            $height = $computed["Height"];
        }

        $date = date("Y-m-d H:i:s");
        if (array_key_exists("FILE", $exif_array))
        {
            $file = $exif_array['FILE'];
            $date = date('Y-m-d H:i:s', strtotime($file["FileDateTime"]));
        }
        
        if (array_key_exists("EXIF", $exif_array))
        {
            $exif = $exif_array['EXIF'];
            $date = date('Y-m-d H:i:s', strtotime($exif["DateTimeOriginal"]));
            
        }
        
        #is it a whatsapp image? Then take date from name
        if (preg_match("/^IMG-([0-9]{4})([0-9]{2})([0-9]{2})-WA/", $basefile, $out))
        {
            $date = $out[1]."-".$out[2]."-".$out[3]." 00:00:00";
        }
            
        //IFD0

        $angle = 0;
        if (array_key_exists("IFD0", $exif_array))
        {
            $idf0 = $exif_array['IFD0'];
            $angle = get_orientation($idf0);
        }

        // GPS
        
        $gps = get_gps($exif_array);
        $lat = $gps[0];
        $lon = $gps[1];

        
        if (file_exists($thumbnail_file)==false)
        {
            print("Error: no thumbnail '$thumbnail_file' '$angle'\n");
            $desired_height = 200;
            make_thumb($img_file, $thumbnail_file, $angle, $desired_height);
        }

        $tsize = getimagesize($thumbnail_file);
        $twidth = $tsize[0];
        $theight = $tsize[1];
        
        $str = "INSERT INTO images(name, date, width, height, twidth, theight, lat, lon) VALUES('$basefile', '$date', $width, $height, $twidth, $theight, '$lat', '$lon')"; 
        print("adding: '$basefile', '$date', $width, $height, $twidth, $theight, '$lat', '$lon' ");
        
        $res = $db->exec($str);
        print(" res: '$res' <br/>\n"   );
        if ($res==false)
        {
            $err = true;
        }


        // removed task if executed, otherwise move to error table        
        
        {
            $db_tasks = tasks_get_db();
            
            if ($err==true)
            {
                tasks_error($db_tasks, $first_task);
            }
            
            tasks_delete($db_tasks, $first_task["id"]);
            $db_tasks->close();
        }
        //break;
        flush();
    }
    
    //$db->exec("END TRANSACTION");
    $db->close();

    flock($fp, LOCK_UN);
    fclose($fp);
    
?>
    
    