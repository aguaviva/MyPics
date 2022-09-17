<?php 
    function get_orientation($exif) {
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
    
    function make_thumb($src, $dest, $desired_height) {
        /* read the source image */
        $source_image = imagecreatefromjpeg($src);
      
        $exif = exif_read_data($src);
        $angle = get_orientation($exif);

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
    
    $file = basename($_GET['img']);
    
    $pics_dir = 'pics/';
    $thumbnails_dir = 'pics/thumbs/';
    
    header('Content-Type: image/jpeg');
    $pic_file = $pics_dir.$file;
    $thumbnail_file = $thumbnails_dir.$file;
    
    if (file_exists($thumbnail_file)==false)
    {
        make_thumb($pic_file, $thumbnail_file, 200);
    }    

    $myfile = fopen($thumbnail_file, "r") or die("Unable to open file!");
    echo fread($myfile,filesize($thumbnail_file));
    fclose($myfile);    
?>    