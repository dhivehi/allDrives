<?php
namespace Dhivehi;
class gDrive {
   private $credentialsPath   = "/var/www/html/home/cron/gdrive/.credentials/drive-php-quickstart.json";
   public $paths              = array();
   public function __construct() {
      $client        = new \Google_Client();
      $client->setClientId(YOUR_CLIENT_ID);
      $client->setClientSecret(YOUR_CLIENT_SECRETE);
      $client->setRedirectUri(YOUR_REDIRECT_URI);
      $client->setApplicationName('PHP');
      $client->setScopes(array('https://www.googleapis.com/auth/drive'));
      $client->setAccessType('offline');
      $client->setApprovalPrompt('force');

      if (file_exists($this->credentialsPath)) {
         $accessToken = json_decode(file_get_contents($this->credentialsPath), true);
      }
      else if (isset($_GET['code'])){
         $accessToken = $client->fetchAccessTokenWithAuthCode($_GET['code']);
         file_put_contents($this->credentialsPath, json_encode($accessToken));
      }
      else {
         $authUrl = $client->createAuthUrl();
         header("Location: ".$authUrl);
         exit;
      }
      $client->setAccessToken($accessToken);
      $this->client  = $client;
      $this->checkToken();
      $this->service = new \Google_Service_Drive($client);
   }

   public function checkToken(){
      if ($this->client->isAccessTokenExpired()){
         $refreshToken                       = $this->client->getRefreshToken();
         $this->client->refreshToken($refreshToken);
         $newAccessToken                     = $this->client->getAccessToken();
         if (!isset($newAccessToken['refresh_token'])){
            $newAccessToken['refresh_token'] = $refreshToken;
         }
         file_put_contents($this->credentialsPath, json_encode($newAccessToken));
      }
   }

   public function scandir($dir="root"){
      $optParams = array(
      'q'        => "'$dir' in parents and trashed = false",
      // 'pageSize' => 100,
      'fields'   => 'files(id, name, mimeType, kind, parents)'
      );
      $items   = array();
      $results = $this->service->files->listFiles($optParams);
      foreach ($results->getFiles() as $file) {
         $items[] = array("id"=>$file->getId(), "name"=>$file->getName(), "type"=>$file->getMimeType(), "parents"=>$file->getParents());
      }
      return $items;
   }

   public function is_dir($name, $dir="root"){
      //https://developers.google.com/drive/v3/web/mime-types
      //https://developers.google.com/drive/v3/web/search-parameters#examples
      $optParams = array(
      'q'        => "name = '$name' and mimeType = 'application/vnd.google-apps.folder' and '$dir' in parents and trashed = false",
      'fields'   => 'files(id, name, mimeType, kind, parents)'
      );
      $items   = array();
      $results = $this->service->files->listFiles($optParams);
      foreach ($response->files as $file) {
         $items[] = array("id"=>$file->id, "name"=>$file->name);
      }
      return $items;
   }

   public function is_file($name, $mime, $parent="root"){
      $optParams = array(
      'q'        => "name = '$name' and mimeType = '$mime' and '$parent' in parents and trashed = false",
      'fields'   => 'files(id, name, mimeType, kind, parents)'
      );
      $items   = array();
      $results = $this->service->files->listFiles($optParams);

      foreach ($response->files as $file) {
         $items[] = array("id"=>$file->id, "name"=>$file->name);
      }
      return $items;
   }

   public function readVideoChunk($handle, $chunkSize)
   {
      $byteCount = 0;
      $giantChunk = "";
      while (!feof($handle)) {
         // fread will never return more than 8192 bytes if the stream is read buffered and it does not represent a plain file
         $chunk = fread($handle, 1 * 1024 * 1024);
         $byteCount += strlen($chunk);
         $giantChunk .= $chunk;
         if ($byteCount >= $chunkSize)
         {
            return $giantChunk;
         }
      }
      return $giantChunk;
   }

   public function largeUpload($path, $parent="root"){
      $childs         = $this->is_file(basename($path), mime_content_type($path), $parent);

      $file           = new \Google_Service_Drive_DriveFile();
      $file->name     = basename($path);
      $file->parents  = array($parent);
      $file->mimeType = mime_content_type($path);

      $chunkSizeBytes = 10 * 1024 * 1024;
      $this->client->setDefer(true);
      $request        = $this->service->files->create($file);
      $media          = new \Google_Http_MediaFileUpload($this->client, $request, mime_content_type($path), null, true, $chunkSizeBytes);
      $media->setFileSize(filesize($path));

      $status         = false;
      $handle         = fopen($path, "rb");
      while (!$status && !feof($handle)) {
         $chunk       = $this->readVideoChunk($handle, $chunkSizeBytes);
         $status      = $media->nextChunk($chunk);
      }
      $result = false;
      if ($status != false) {
         $result = $status;
      }
      fclose($handle);

      if ($result->id && sizeof($childs) > 0){
         $a = $this->service->files->delete($childs[0]['id']);
      }

      return $result->id;
   }
   public function file_put_contents($path, $parent="root"){

      $childs = $this->is_file(basename($path), mime_content_type($path), $parent);

      $meta         = array('name'=>basename($path), 'parents'=>array($parent), 'mimeType'=>mime_content_type($path));

      $fileMetadata = new \Google_Service_Drive_DriveFile($meta);

      $file         = $this->service->files->create($fileMetadata, array(
      'data'       => file_get_contents($path),
      'mimeType'   => mime_content_type($path),
      'uploadType' => 'multipart',
      'fields'     => 'id')
      );

      if ($file->id && sizeof($childs) > 0){
         $this->service->files->delete($childs[0]['id']);
      }

      return $file->id;
   }

   public function mkdir($name, $level=0, $p=""){
      $name           = rtrim($name, "/");
      if (!isset($this->paths[$name])){
         $level       += 1;
         $parts       = explode("/", $name);
         $folder      = $parts[$level];
         $pid         = ($p==""?"root":$this->paths[$p]);
         $path        = $p."/".$folder;

         $optParams = array(
         'q'        => "'$pid' in parents and name = '$folder' and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
         'fields'   => 'files(id, name, mimeType, kind, parents)'
         );

         $results     = $this->service->files->listFiles($optParams);
         $childs      = $results->getFiles();

         if (count($childs) > 0){
            $file    = $childs[0];
            $this->paths[$path] = $file->getId();
            if (isset($parts[$level+1])){
               $this->mkdir($name, $level, $path);
            }
         }
         else {
            $fileMetadata       = new \Google_Service_Drive_DriveFile(array(
            'name'=>$folder, 'parents'=>array($pid), 'mimeType' => "application/vnd.google-apps.folder")
            );
            $file               = $this->service->files->create($fileMetadata, array('fields' => 'id'));

            if (isset($file->id)){
               $this->paths[$path] = $file->id;
               if (isset($parts[$level+1])){
                  $this->mkdir($name, $level, $path);
               }
            }
            else {
               echo "\nerror creating dir: $path\n";
               print_r($file);
               exit;
            }
         }
      }
      if (isset($this->paths[$name])){
         return $this->paths[$name];
      }
      else if ($name==""){
         return "root";
      }
      else {
         return false;
      }
   }
}
