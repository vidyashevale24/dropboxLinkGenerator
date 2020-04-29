<?php
/*
$_POST['folders'] = 'vid';
$_POST['api_key'] = 'sT6AJ3GA3cAAAAAAAAAADrsbyJ9fn7Sjnqxsb6uyWQ5jvF-KpQIs8yJE8orLkTmY';*/

if (isset($_POST)){
   //Set headers for the API 
    $headers = array();
    $headers[] = 'Authorization: Bearer '.$_POST['api_key'];
    $headers[] = 'Content-Type: application/json';

    //Set configurations
    ini_set('max_execution_time', 0);
	ini_set('post_max_size', '100M');
	ini_set('upload_max_filesize', '50M');
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);

    $filesArr = array();
    $shareLinkArr = array();
    $fileListsArray = array();
    $j = 0;
    $_POST['folders'] = (explode(',',$_POST['folders']));
        foreach ($_POST['folders'] as $key => $folder) {
            //Call list_folder API to get all files for given folder and pass below parameters as an array
            $ch = curl_init();
            $folderFields = array(
                        "path"=>'/'.$folder,
                        "recursive"=>true,
                        "include_media_info"=>false,
                        "include_deleted"=>false,
                        "include_has_explicit_shared_members"=>false,
                        "include_mounted_folders"=>true,
                        "include_non_downloadable_files"=>true,
                        "limit"=>2000 //(min=1, max=2000 )Note: This is an approximate number and there can be slightly more entries returned in some cases. This field is optional.
                    );
            $qbody = json_encode($folderFields);	
            curl_setopt($ch, CURLOPT_URL, 'https://api.dropboxapi.com/2/files/list_folder');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $qbody);
            curl_setopt($ch, CURLOPT_HTTPHEADER,  $GLOBALS['headers']);
            $result = curl_exec($ch);
           
            if (curl_errno($ch)) {
                $GLOBALS['fileListsArray'][$j]['Msg'] = curl_error($ch);
                $GLOBALS['fileListsArray'][$j]['Status'] = 0;
                $GLOBALS['fileListsArray'][$j]['File_path'] = '';
                $GLOBALS['fileListsArray'][$j]['Folder'] = $folder;
            }
            curl_close($ch);
            if(!empty($result)){
                $result = json_decode($result);
                if(!empty($result->entries)){
                    foreach ($result->entries as $key => $files) {
                        $GLOBALS['filesArr'][] = $files->path_display;
                    }
                    if($result->has_more == 1){
                        //Call to list_foder/continue API to get more results if the result.has_more is true in list_folder API
                        list_folder_continue($result->cursor,$folder,$GLOBALS['filesArr']);
                    }
                
                    //Store links in CSV file
                    $root = $_SERVER['DOCUMENT_ROOT'];
                    $header = array('File/Folder Name','Shareable Link','File/Folder Path','Size(Bytes)','Folder name','Created Date');
                    $dir = rand();
                    mkdir($root.'/dropbox/dropbox_files/'.$dir, 0777, true) ;
                    $filePath = $root . '/dropbox/dropbox_files/' .  $dir . '/' .$folder.'.csv' ;
                    $handle = fopen($filePath, "w");
                    fputcsv($handle, $header);
                    //Call to create Shareable link function 
                    create_share_link($filesArr,$folder,$handle);
                    $GLOBALS['fileListsArray'][$j]['Msg'] = 'Shared link created successfully.';
                    $GLOBALS['fileListsArray'][$j]['Status'] = 1;
                    $GLOBALS['fileListsArray'][$j]['File_path'] ='/dropbox/dropbox_files/' .  $dir . '/' .$folder.'.csv' ;;
                    $GLOBALS['fileListsArray'][$j]['Folder'] = $folder;
                }else{
                    if(isset($result->error_summary)){
                        if(strpos($result->error_summary, "path/not_found") !== false){
                            $GLOBALS['fileListsArray'][$j]['Msg'] = 'Path not found';
                            $GLOBALS['fileListsArray'][$j]['Status'] = 0;
                            $GLOBALS['fileListsArray'][$j]['File_path'] = '';
                            $GLOBALS['fileListsArray'][$j]['Folder'] = $folder;
                        }
                        elseif(strpos($result->error_summary, "invalid_access_token/.") !== false){
                            echo json_encode(array('Status'=> 0,'ErrorMsg'=>'Invalid Access token','Result'=>''));exit;
                        }else{
                            echo json_encode(array('Status'=> 0,'ErrorMsg'=>'Something went wrong.','Result'=>''));exit;
                        }
                    }else{
                        echo json_encode(array('Status'=> 0,'ErrorMsg'=>'Unauthorised API key or API call','Result'=>''));exit;
                    }
                }
            }
            //Reset array for next folder 
            $filesArr = array();
            $shareLinkArr = array();
            $j++;
        }
        echo json_encode($GLOBALS['fileListsArray']);exit;
    }else{
    echo json_encode(array('Status'=> 0,'Msg'=>'Post data not submitted.','Result'=>''));exit;
}
    // list_foder/continue API to get more results if the result.has_more is true in list_folder API
 	function list_folder_continue($cursor,$folder,$filesArr){
        $folderFieldsCont = array(
            "cursor"=>$cursor
        );
        $qbodyNew = json_encode($folderFieldsCont);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://api.dropboxapi.com/2/files/list_folder/continue');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $qbodyNew);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $GLOBALS['headers']);

		$resultNew = curl_exec($ch);
		if (curl_errno($ch)) {
            echo json_encode(array('Status'=> 0,'Msg'=>curl_error($ch),'Result'=>''));continue;
		}
		curl_close($ch);
        $resultCont = json_decode($resultNew);
        foreach ($resultCont->entries as $filesNew) {
            array_push($GLOBALS['filesArr'],$filesNew->path_display);
       	}
		if($resultCont->has_more == 1){
			// call to list_foder/continue API to get more results if the result.has_more is true
			list_folder_continue($resultCont->cursor,$folder,$filesArr);
		}
	}
    //Create share link funtion to get Shareable links
	function create_share_link($filesArr,$folder,$handle){
        $mh = curl_multi_init();

        // array of curl handles
        $multiCurl = array();
        // data to be returned
        $result = array();
        foreach ($filesArr as $i => $id) {
            $folderFieldsShare = array(
                'path' =>$id
            );
            $qbodyShare = json_encode($folderFieldsShare);
            // URL from which data will be fetched
            $fetchURL = 'https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings';
            $multiCurl[$i] = curl_init();
            curl_setopt($multiCurl[$i], CURLOPT_URL,$fetchURL);
            curl_setopt($multiCurl[$i], CURLOPT_HEADER,0);
            curl_setopt($multiCurl[$i], CURLOPT_POSTFIELDS, $qbodyShare);
            curl_setopt($multiCurl[$i], CURLOPT_HTTPHEADER, $GLOBALS['headers']);
            curl_setopt($multiCurl[$i], CURLOPT_RETURNTRANSFER,1);
            curl_multi_add_handle($mh, $multiCurl[$i]);
        }
        $index=null;
        do {
            curl_multi_exec($mh,$index);
        } while($index > 0);
        // get content and remove handles
        foreach($multiCurl as $k => $ch) {
            $result[$k] = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
        }
        curl_multi_close($mh); 
        if(!empty($result)){
            foreach($result as $res){
                $res = json_decode($res);
                $date =  date('Y-m-d H:i:s');
                if(!empty($res->error_summary)){
                    $shareLinkArr['name']  = $res->error->shared_link_already_exists->metadata->name;
                    $shareLinkArr['url']   = $res->error->shared_link_already_exists->metadata->url;
                    $shareLinkArr['path']  = $res->error->shared_link_already_exists->metadata->path_lower;
                    $shareLinkArr['size']  = isset($res->error->shared_link_already_exists->metadata->size)? $res->error->shared_link_already_exists->metadata->size : '';
                    $shareLinkArr['folder_name']  = $folder;
                    $shareLinkArr['created_date']  = $date;
                    fputcsv($handle, $shareLinkArr);
                    save_links($shareLinkArr);
                }else{
                    $shareLinkArr['url']   = $res->url;
                    $shareLinkArr['name']  = $res->name;
                    $shareLinkArr['path']  = $res->path_lower;
                    $shareLinkArr['size']  = isset($res->size)?$res->size: '';
                    $shareLinkArr['folder_name']  = $folder;
                    $shareLinkArr['created_date']  = $date;
                    fputcsv($handle, $shareLinkArr);
                    save_links($shareLinkArr);
                }
            }
        }
    }

    //Store links into the db
    function save_links($shareLinkArr){
        include("config.php");
        $sql = "INSERT INTO dropbox_share_links (`name`, `url`, `path`,`folder_name`,`created_at`)
        VALUES ('$shareLinkArr[name]', '$shareLinkArr[url]', '$shareLinkArr[path]','$shareLinkArr[folder_name]','$shareLinkArr[created_date]')";
        $con->query($sql);
    }
?>