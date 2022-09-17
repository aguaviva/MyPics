<?php
function open_database()
{
    # open database & crate tables
    $database_filename = 'mytest.db';
    $db = new SQLite3($database_filename);
    $db->exec("CREATE TABLE IF NOT EXISTS images(id INTEGER PRIMARY KEY, name TEXT UNIQUE)");
    $db->exec("CREATE TABLE IF NOT EXISTS tags(id INTEGER PRIMARY KEY, name TEXT UNIQUE)");
    $db->exec("CREATE TABLE IF NOT EXISTS imgs_tags(img_id INTEGER, tag_id INTEGER, UNIQUE(img_id, tag_id))");
    return $db;
}

function concat_values($array)
{
    return "Values(".implode("), Values(", $array).")";
}

function concat($array)
{
    return "('".implode("','", $array)."')";
}

function add_images($db, $img_list)
{
    foreach($img_list as $img)
    {
        $db->exec("INSERT OR IGNORE INTO images(name) VALUES('$img')");               
    }
    return ;
}

function del_images($db, $img_list)
{
    foreach($img_list as $img)
    {
        $db->exec("DELETE FROM images WHERE name == '$img'");               
    }
    
    del_orphan_links($db);
}

function add_tags($db, $tag_list)
{
    foreach($tag_list as $tag)
    {
        $db->exec("INSERT OR IGNORE INTO tags(name) VALUES('$tag')");               
    }
    return ;
}

function del_tags($db, $tag_list)
{
    foreach($tag_list as $tag)
    {
        $db->exec("DELETE FROM tags WHERE name == '$tag'");               
    }
    
    del_orphan_links($db);
}

function del_orphan_links($db)
{
    $db->exec("DELETE FROM imgs_tags WHERE tag_id not in (select id from tags);".
              "DELETE FROM imgs_tags WHERE img_id not in (select id from images)");
}

function add_images_tags($db, $img_list, $tag_list)
{
    $str = "INSERT OR REPLACE INTO imgs_tags(img_id,tag_id) SELECT * FROM ".
            "(select id from images where name in ". concat($img_list).") ".
            "CROSS JOIN (select id from tags where name in ".concat($tag_list).")";     
    return $db->exec($str);
}

function get_images_with_tags_or($db, $tag_list)
{
    $str = "SELECT images.name FROM ".
            "(imgs_tags JOIN images ON imgs_tags.img_id = images.id) ".
            "JOIN tags ON tag_id in (select id from tags where name in ".concat($tag_list).") ".     
            "GROUP BY images.name;";
    $results = $db->query($str);
    
    while ($row = $results->fetchArray())
    {
        print($row["name"]." ");
    }
}

function get_images_with_tags_and($db, $tag_list)
{
    $str = "select name from images join (select img_id from ((select id from tags where name in ".concat($tag_list)." ) join imgs_tags  on id=tag_id) GROUP BY img_id HAVING COUNT(*) = ".count($tag_list)." ) on images.id=img_id;";
    print("$str</br>");
    $results = $db->query($str);
    
    while ($row = $results->fetchArray())
    {
        print($row["name"]." ");
    }
}


function dump($db)
{
    $str = "SELECT images.name, group_concat(tags.name,', ') AS tags_for_this_object  FROM (images LEFT JOIN imgs_tags ON (imgs_tags.img_id = images.id)) LEFT JOIN tags on (imgs_tags.tag_id = tags.id) GROUP BY images.name;";
    /*
    $str = "SELECT images.name, group_concat(tags.name,', ') AS tags_for_this_object ". 
            "FROM imgs_tags ". 
            "JOIN images ON img_id = images.id ".
            "JOIN tags ON tag_id = tags.id ".
            "GROUP BY images.name;";
    */
    $results = $db->query($str);
    
    while ($row = $results->fetchArray())
    {
        print($row["name"].": ");
        print($row["tags_for_this_object"]."</br>");
    }
}

function test()
{
    $db = open_database();
        
    $img_list = array("img1", "img2");
    $tag_list = array("tag1", "tag2", "tag3");    
    add_images($db, $img_list);
    add_tags($db, $tag_list);    
    add_images_tags($db, $img_list, $tag_list);    
    
    $img_list = array("img3");
    $tag_list = array("tag4", "tag5", "tag6");    
    add_images($db, $img_list);
    add_tags($db, $tag_list);
    add_images_tags($db, $img_list, $tag_list);    

    $img_list = array("img4");
    $tag_list = array("tag5");    
    add_images($db, $img_list);
    add_tags($db, $tag_list);
    add_images_tags($db, $img_list, $tag_list);    

    $img_list = array("img5");
    $tag_list = array("tag6");    
    add_images($db, $img_list);
    add_tags($db, $tag_list);
    add_images_tags($db, $img_list, $tag_list);    


    dump($db);print("</br>");

    print("get_images_with_tags_and</br>");
    get_images_with_tags_and($db, array("tag1", "tag2"));print("</br>");
    get_images_with_tags_and($db, array("tag5", "tag6"));print("</br>");

    print("---</br>");

    dump($db);print("</br>");
    
    get_images_with_tags_or($db, array("tag1"));print("</br>");
    get_images_with_tags_or($db, array("tag2"));print("</br>");
    get_images_with_tags_or($db, array("tag3"));print("</br>");
    get_images_with_tags_or($db, array("tag4"));print("</br>");
    get_images_with_tags_or($db, array("tag5", "tag6"));print("</br>");
    get_images_with_tags_or($db, array("none"));print("</br>");
    
    
    //del_tags($db, array("tag5"));
    dump($db);print("</br>");
    
    //del_images($db, array("img1"));
    dump($db);print("</br>");
}

test();
?>
