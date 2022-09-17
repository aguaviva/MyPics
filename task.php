<?php

function tasks_get_db()
{
    $database_filename = 'task_list.db';
    $db = new SQLite3($database_filename);
    $db->exec("CREATE TABLE IF NOT EXISTS tasks(id INTEGER PRIMARY KEY, name TEXT UNIQUE, date TEXT)");
    $db->exec("CREATE TABLE IF NOT EXISTS errors(id INTEGER, name TEXT UNIQUE, date TEXT)");
    return $db;
}

function tasks_push_back($db, $file)
{
    $file = SQLite3::escapeString($file);
    $str = "INSERT INTO tasks(name, date) VALUES('$file', CURRENT_TIMESTAMP)"; 
    $db->exec($str);
}

function tasks_get_first_task($db)
{
    $str = "SELECT id, name, date FROM tasks ORDER BY date DESC LIMIT 1";
    $qResult = $db->query($str);
    return $qResult->fetchArray();
}

function tasks_delete($db, $id)
{
    $str = "DELETE FROM tasks WHERE id =='$id'";
    $db->exec($str);
}

function tasks_error($db, $values)
{
    $id = $values["id"];
    $name = $values["name"];
    $date = $values["date"];
    
    $str = "INSERT INTO errors(id, name, date) VALUES('$id', '$name', '$date')"; 
    $db->exec($str);
}
    
function test()
{
     //$db = tasks_get_db();
     #push_back($db, "aaa");
     #push_back($db, "bbb");
     #push_back($db, "ccc");
     
    //$t = tasks_get_first_task($db);
    //var_dump($t);

    //delete_task($db, $t["id"]);
}
?>    