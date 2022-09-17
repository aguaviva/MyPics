<?php

include 'task.php';

$uploadOk = 1;
$target_dir = "pics/";
$fileToUpload = basename($_FILES["fileToUpload"]["name"]);
$target_file = $target_dir . $fileToUpload;
$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));


// Check if image file is a actual image or fake image
if(isset($_POST["submit"])) 
{
  $check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
  if($check !== false) 
  {
    echo "File is an image - " . $check["mime"] . ".";
    $uploadOk = 1;
  } else 
  {
    echo "File is not an image.";
    $uploadOk = 0;
  }
}

// Check if file already exists
if (file_exists($target_file)) {
  echo "Sorry, file already exists.";
  $uploadOk = 0;
}

// Check file size
if ($_FILES["fileToUpload"]["size"] > 10000000) {
  echo "Sorry, your file is too large.";
  $uploadOk = 0;
}

// Allow certain file formats
if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) 
{
  echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
  $uploadOk = 0;
}

// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 1) 
{
  if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) 
  {
    echo "The file ". htmlspecialchars( $fileToUpload ). " has been uploaded.";
    
     $db = tasks_get_db();
     tasks_push_back($db, $target_file);
     $db->close();
     
     shell_exec("php database.php >/dev/null 2>/dev/null &");   
  } 
  else 
  {
    echo "Sorry, there was an error moving the uploaded file.";
  }
// if everything is ok, try to upload file
} else {
  echo "Sorry, your file was not uploaded.";
}
?>
