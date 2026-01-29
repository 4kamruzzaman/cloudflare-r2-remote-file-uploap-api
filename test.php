<?php
phpinfo();

exit;
$apiUrl = "https://gup2461.gfxload.com/";

function upload($last_url, $filename) {
    global $apiUrl;

    $postData = [
        'url' => $last_url,
        'filename' => $filename
    ];

    // Initialize cURL
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0); // allow large files
    $response = curl_exec($ch);
    curl_close($ch);
    echo $response;

    if ($response === false) {
        return 'error';
    } else {
        $result = json_decode($response, true);

        if($result['success'] === true) {
            return 'success';
        } else {
            return 'error';
        }
    }

}

function checkUpload($filename) {
    global $apiUrl;

    // Use the key = "Month/Filename" used in upload
    $monthName = date('F');
    $key = strtolower($monthName) . '/' . $filename;

    echo $key;

    $ch = curl_init($apiUrl . "status?key=" . urlencode($key));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    echo $response;

    $result = json_decode($response, true);
    if ($result['success'] === true) {
        if ($result['status'] === 'pending') {
            return 'pending';
        } elseif ($result['status'] === 'completed') {
            return $result['file_url'];
        } else {
            return 'error';
        }
    } else {
        return 'error';
    }

}

$last_url = urldecode($_GET['url']);
$filename = strtolower($monthName) . '' . $_GET['filename'];

// $uploadResult = upload($last_url, $filename);

$r2File = checkUpload($filename);
if ($r2File != 'completed') {
    $last_url = $r2File;
}

echo $r2File['file_url'];

// $uploadResult = upload($last_url, $filename);
// if($uploadResult != 'error') {
//     $r2File = checkUpload($filename);
//     if($r2File == 'completed') {
//         $last_url = $r2File;
//     }
// }

// echo $r2File;
// echo $last_url;