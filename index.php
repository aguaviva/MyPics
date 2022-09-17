<?php
    $images_dir = 'pics/';
    $thumbs_dir = "thumbs/";

    function get_database() { return new SQLite3('test.db'); }

    function concat($array)
    {
        return "('".implode("','", $array)."')";
    }
    
    function split_tag_str($tag_list)
    {
        $out = array();
        foreach($tag_list as $tag) {
            $tag = trim($tag);
            if (strlen($tag)>0)
                array_push ($out, $tag);
        }        
        
        return $out;
    }
    
    function add_images_tags($img_list, $tag_list)
    {
        $db = get_database();    
        
        foreach ($tag_list as $tag) {
            $res = $db->exec("INSERT OR IGNORE INTO tags(name) VALUES('$tag')");                    
        }
        
        $str = "INSERT OR REPLACE INTO imgs_tags(img_id,tag_id) SELECT * FROM ".
                "(select id from images where name in ". concat($img_list).") ".
                "CROSS JOIN (select id from tags where name in ".concat($tag_list).")";     
        $res = $db->exec($str);
    }
    
    function get_images($tag_list)
    {
        $tag_list = split_tag_str($tag_list);
        
        $db =get_database();
     
        if (count($tag_list)>0)
            //$str = "SELECT * FROM (imgs_tags JOIN images ON imgs_tags.img_id = images.id) where imgs_tags.tag_id in (select id from tags where tags.name in ".concat($tag_list).");";
            $str = "select * from images join (select img_id from ((select id from tags where name in ".concat($tag_list)." ) join imgs_tags  on id=tag_id) GROUP BY img_id HAVING COUNT(*) = ".count($tag_list)." ) on images.id=img_id;";
        else 
            $str = "SELECT * FROM images ORDER BY date desc";
        
        $results = $db->query($str);
        $out = array();
        while ($row = $results->fetchArray())
        {
            $out[basename($row["name"])] = array( "width"=> $row["width"], "height"=>$row["height"], "imgtWidth"=>$row["twidth"], "imgtHeight"=>$row["theight"], "date"=>$row["date"]);
        }
        
        return $out;
    }
    
    function get_tags()
    {
        $out = array();
        $db =get_database();
        $results = $db->query("SELECT name FROM tags");        
        while ($row = $results->fetchArray())
        {
           array_push($out, $row["name"]);
        }
        return $out;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST')
    {
        $query_string = $_SERVER['QUERY_STRING'];
        if(isset($query_string))
        {
            parse_str($query_string, $query_array);
            $command = $query_array["command"];
            if(isset($command))
            {
                header_remove(); 
                header('Content-Type: application/json; charset=utf-8');
                $data = json_decode(file_get_contents("php://input"), true);
                
                if($command=="set_tags")
                { // Check if form was submitted
                    add_images_tags($data["pics"], $data["tags"]);
                }
                else if($command=="get_images")
                { 
                    print(json_encode(get_images($data["tags"])));
                }
                else if($command=="get_tags")
                { 
                    print(json_encode(get_tags()));
                }
                else
                {
                    print($command);
                    return; 
                }
                
                http_response_code(200);
                return;
            }
            else
            {
                print("unknown command '$command'");
                return; 
            }
        }
        else
        {
            print("command not found");
            return; 
        }
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1, maximum-scale=1">          
        
        <!-- jQuery -->
        <script src="https://cdn.jsdelivr.net/npm/jquery@3.3.1/dist/jquery.min.js" type="text/javascript"></script>
      
        <!-- nanogallery2 -->
        <link  href="https://cdn.jsdelivr.net/npm/nanogallery2@3/dist/css/nanogallery2.min.css" rel="stylesheet" type="text/css">
        <script  type="text/javascript" src="https://cdn.jsdelivr.net/npm/nanogallery2@3/dist/jquery.nanogallery2.min.js"></script>
        
        <!-- bootstrap -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-gH2yIJqKdNHPEq0n4Mqa/HGKIhSkIHeL5AyhkYV8i59U5AR6csBvApHHNl/vI1Bx" crossorigin="anonymous">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-A3rJD856KowSb7dwlZdYEkO39Gagi7vIsF0jrRAoQmDKKtQBHUuLZ9AsSv4jD4Xa" crossorigin="anonymous"></script>
    </head>
    <body>

    <script>
    'use strict';
    
<?php 
    print("var images_dir = '$images_dir';\n");
    print("var thumbs_dir = '$thumbs_dir';\n");
?>    
    var splitTest = function (str) { return str.split('\\').pop().split('/').pop(); }    

    var items_selected = new Map();
    
    function get_selected_pictures() 
    {
        var pic_list = [];
        for (const [key, value] of items_selected.entries())
        {
            for (var p=0;p<value.length;p++)
            {
                pic_list.push(value[p]);
            }
        }
        return pic_list;        
    }

    function group_by_date(items)
    {
        var groups = {}
        for (const [key, value] of Object.entries(items)) 
        {
            var label = value["date"].substr(0,7);
            groups[label] = groups[label] || {};
            groups[label][key]= value;
        }
        return groups;
    }

    function create_gallery(id, items)
    {
        $(id).nanogallery2( {thumbnailSelectable: true, thumbnailHeight: 200, thumbnailWidth: 'auto', itemsBaseURL: images_dir, items: items});
        
        $(id).on( 'itemSelected.nanogallery2 itemUnSelected.nanogallery2', function() {
            var sel = [];
            $(id).nanogallery2('data').items.forEach( function(item) {
                if( item.selected ) {
                    sel.push(splitTest(item.src));
                }
            });                
            items_selected.set(id, sel);
        });
    }
    
    
    function create_all_galleries(image_list)
    {
        $("#gallery .ngy2_container").map(function(){return $(this).nanogallery2('destroy');});
        $("#gallery").empty();
    
        for (const [gallery_name, pic_list] of Object.entries(image_list)) 
        {
            $('#gallery').append(`<h1 style='text-align: center;'>${gallery_name}</h1><div id='${gallery_name}'>caca</div>\n`); 
            
            var items = []
            for (const [pic, data] of Object.entries(pic_list)) 
            {
                 data["src"]=pic;  
                 data["srct"]=thumbs_dir + pic;
                 items.push(data);
            }
            
            create_gallery("#"+gallery_name, items);
        }
    }    

    function post(command, myJSObject, cb_success)
    {
        $.ajax({
            type: "POST",
            url: "?command="+command,
            data: JSON.stringify(myJSObject),
            contentType : 'application/json',
            success: cb_success            
        });
    }
    
    function get_images(tag_list, cb_success) { post("get_images", {"pics":get_selected_pictures(), "tags":tag_list }, cb_success); }
    function get_tags(cb_success) { post("get_tags", {}, cb_success); }

    function checkbox_tag(name)
    {
        return '<div class="form-check">'+
                `<input class="form-check-input" type="checkbox" value="${name}"/>` +
                `<label class="form-check-label" for="flexCheckDefault" >${name}</label>`+
                '</div>';
    }

    function create_checkbox_grid(tag_list, columns)
    {
        var str = "";
        for(var y=0;y<Math.ceil(tag_list.length/columns);y++)
        {
            str += '<div class="row">'
            for(var x=0;x<columns;x++)
            {
                var i = y*columns+x;
                var name = (i < tag_list.length) ? checkbox_tag(tag_list[i]) : "";
                str += `<div class="col">${name}</div>`;
            }
            str += '</div>'
        }
        return str;
    }

    var selected_tags = [];

    jQuery(document).ready(function () 
    {
        get_images([], function(items) { create_all_galleries(group_by_date(items)) });
        
        $("#myModalFilter").on('show.bs.modal', function()
        {
            var sel_grid = $(this).find('.selected_grid')
            var tag_grid = $(this).find('.checkbox_tag_grid');

            sel_grid.empty().html(create_checkbox_grid(["selected"], 2)).find('input:checkbox').click(function() 
            {
                    selected_tags = tag_grid.find('input:checked').map(function(){ return $(this).val();}).get();
                    get_images(selected_tags, function(items)
                    {
                        var sel_pics = get_selected_pictures();
                        var d = {}
                        for (var i=0;i<sel_pics.length;i++)
                        {
                            d[sel_pics[i]] = items[sel_pics[i]]
                        }
                        
                        create_all_galleries(group_by_date(d)) 
                    });
            })

            get_tags(function(tag_list) 
            {
                tag_grid.empty().html(create_checkbox_grid(tag_list, 2)).find('input:checkbox').click(function() 
                {
                    selected_tags = tag_grid.find('input:checked').map(function(){ return $(this).val();}).get();
                    get_images(selected_tags, function(items)
                    {
                        create_all_galleries(group_by_date(items)) 
                    });
                })
                
                $.map(selected_tags, function(k) { grid.find("[value='"+k+"']").prop('checked', true); }) 
            })
        });        

        $("#myModal").on('show.bs.modal', function()
        {
            var grid = $(this).find('.checkbox_grid');
            
            get_tags(function(tag_list) 
            {
                grid.empty().html(create_checkbox_grid(tag_list, 2));
                
                $.map(selected_tags, function(k) { grid.find("[value='"+k+"']").prop('checked', true); }) 
            })
            
        }).on('hidden.bs.modal', function() {
            
            if ($(document.activeElement).attr('id') == "do_tag")
            {
                var checked_tags = $(this).find('input:checked').map(function(){return $(this).val();}).get().join(",");
                alert(checked_tags_str);
            }
            
            /*
                .find('input:checkbox').click(function() 
                {
                    
                    
                    var edit = $("#myModal #tags").val();
                    var tag_list = checked_tags_str ;
                    
                    post("set_tags", {"pics":get_selected_pictures(), "tags": tag_list}, function(result) 
                    {
                        process_tags(tag_list);
                    } ); 
                    
                })            
            $('#myModalFilter .do_tag').on('click', function(event) {
              var $button = $(event.target); // The clicked button
            
                
            
              $(this).closest('.modal').one('hidden.bs.modal', function() {
                  
                  _pictures($('#tags').val())
                // Fire if the button element 
                console.log('The button that closed the modal is: ', $button);
              });
            });            
            */            
             
            console.log('The button that closed the modal is: caca');
        });        

        
    });
    
    </script>

    <!-- bootstrap navbar -->
    
    <nav class="navbar navbar-expand-md navbar-dark fixed-top bg-dark">
      <div class="container-fluid">
        <a class="navbar-brand" href="#">Fixed navbar</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarCollapse">
          <ul class="navbar-nav me-auto mb-2 mb-md-0">
            <li class="nav-item">
              <a data-bs-toggle="modal" data-bs-target="#myModal" class="nav-link active">Tags</a>
            </li>
            <li class="nav-item">
              <a data-bs-toggle="modal" data-bs-target="#myModalFilter" class="nav-link active">Filter</a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                Selection
                </a>
                <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                    <a class="dropdown-item" href="#">All</a>
                    <a class="dropdown-item" href="#">None</a>
                    <a class="dropdown-item" href="#">Invert</a>
                    <a class="dropdown-item" href="#">Between</a>
                </div>
            </li>
          </ul>
        </div>
      </div>
    </nav>    
        
    <!-- bootstrap tag dialogs -->    
        
    <!-- Modal -->
    <div class="modal fade" id="myModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="staticBackdropLabel">Edit tags</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="container checkbox_grid">
            </div>
            <label for="validationTooltip02" class="form-label">Type tags below for the selected images</label>
            <input class="form-control me-2" type="search" placeholder="tags..." aria-label="Search" id="tags">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="do_tag" data-bs-dismiss="modal">Tag</button>
          </div>
        </div>
      </div>
    </div> 

    <!-- Modal -->
    <div class="modal fade" id="myModalFilter" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="staticBackdropLabel">Filter by tag</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" >
            <div class="container selected_grid"></div>
            <div class="container checkbox_tag_grid"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div> 
    
    <h1 style='text-align: center;'>My Gallery</h1>
    
    <div id="gallery"></div>
<?php 
/*
    $images_dir = 'pics/';
    $thumbs_dir = "thumbs/";
    
    $database_filename = 'test.db';
    $db = new SQLite3($database_filename);
    
    $tag_list = array("delete"); 
    $results = $db->query("SELECT * FROM (imgs_tags JOIN images ON imgs_tags.img_id = images.id) where imgs_tags.tag_id in (select id from tags where tags.name in (".concat($tag_list)."));");
    
    //$results = $db->query("SELECT * FROM images ORDER BY date desc");
    
    $old_key="";
    while ($row = $results->fetchArray())
    {
        $date = $row["date"];
        $file = $row["name"];
        $width = $row["width"];
        $height = $row["height"];
        $twidth = $row["twidth"];
        $theight = $row["theight"];
        
        $key = date('Y-M',  strtotime($date));
        if ($old_key!=$key)
        {
            if ($old_key!="")
            {
                print("]);");
                print("</script>\n");
            }
            
            $old_key = $key;
            print("<h1 style='text-align: center;'>$key</h1>\n");
    
            $id = str_replace(' ', '_', $key);
            print("<div id='$id'></div>\n");
            print("<script>\n");

            print("create_gallery('#$id', './$images_dir', './$thumbs_dir', [\n");
        } 
        
        $file=basename($file);
        $thumb = "thumbs/".$file;
        print("{ src: '$file', width:'$width', height:'$height', imgtWidth:'$twidth', imgtHeight:'$theight'  },\n");
    }

    print("]);");
    print("</script>\n");
*/    
?>
      </body>
  </html>
          

