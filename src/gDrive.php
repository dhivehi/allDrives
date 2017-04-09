<?php
namespace Dhivehi;
class gDrive {
   private $client_id            = "YOUR_CLIENT_ID";
   private $client_secret        = "YOUR_CLIENT_SECRETE";
   private $tokenStore           = "/path/to/store/token.json";
   private $redirect_uri         = "YOUR_REDIRECT_URI"; //use: "http://".$_SERVER["HTTP_HOST"].$_SERVER["PHP_SELF"];
   public $paths                 = array();
   public function __construct($ts){
      if ($ts){
         $this->tokenStore       = $ts;
      }
      if ($this->has_token()) {
         $this->token            = json_decode($this->get_token(), 1);
      }
      else if (isset($_GET['code'])){
         $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, "https://accounts.google.com/o/oauth2/token");
         curl_setopt($ch, CURLOPT_POST, true);
         curl_setopt($ch, CURLOPT_POSTFIELDS, array(
         "code"          => $_GET['code'],
         "client_id"     => $this->client_id,
         "client_secret" => $this->client_secret,
         "redirect_uri"  => $this->redirect_uri,
         "grant_type"    => "authorization_code"
         ));
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
         $output         = curl_exec($ch);
         $info           = curl_getinfo($ch);
         curl_close($ch);
         if ($info['http_code'] == 200){
            $this->token            = json_decode($output, 1);
            $this->token['created'] = time();
            $this->put_token(json_encode($this->token));
         }
         else {
            echo "Error: $output\n";
         }
      }
      else {
         $authUrl = $this->createAuthUrl();
         header("Location: ".$authUrl);
         exit;
      }
   }

   public function has_token()
   {
      return file_exists($this->tokenStore);
   }
   
   public function get_token()
   {
      return file_get_contents($this->tokenStore);
   }

   public function put_token($data)
   {
      return file_put_contents($this->tokenStore, $data);
   }

   public function createAuthUrl(){
      $params = array(
      "response_type"   => "code",
      "access_type"     => "offline",
      "client_id"       => $this->client_id,
      "redirect_uri"    => $this->redirect_uri,
      "scope"           => "https://www.googleapis.com/auth/drive",
      "approval_prompt" => "force"
      );
      return "https://accounts.google.com/o/oauth2/auth?".http_build_query($params);
   }

   public function refreshToken() {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://accounts.google.com/o/oauth2/token");
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, array(
      "client_id"     => $this->client_id,
      "client_secret" => $this->client_secret,
      "refresh_token" => $this->token['refresh_token'],
      "grant_type"    => "refresh_token"
      ));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      $output         = curl_exec($ch);
      $info           = curl_getinfo($ch);
      curl_close($ch);
      if ($info['http_code'] == 200){
         $token                       = json_decode($output, 1);
         $this->token['access_token'] = $token['access_token'];
         $this->token['created']      = time();
         if (isset($token['refresh_token'])){
           $this->token['refresh_token'] = $token['refresh_token'];
         }
         $this->put_token(json_encode($this->token));
      }
      else {
         echo "Error: $output\n"; 
      }
   }

   public function checkToken(){
      $expireTime = $this->token['created']+$this->token['expires_in'];
      if ($expireTime - time() < (10*60)){
         $this->refreshToken();
      }
   }

   public function handleError($output){
      if (isset($output['error'])){
         print_r($output);
         echo "Process terminated.\n";
         exit;
      }
   }

   public function is_dir($name, $dir="root"){
      $this->checkToken();
      $optParams = array(
      'q'        => "name = '".addslashes($name)."' and mimeType = 'application/vnd.google-apps.folder' and '$dir' in parents and trashed = false",
      'fields'   => 'files(id, name, mimeType, kind, parents)'
      );
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/drive/v3/files?".http_build_query($optParams));
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Authorization: Bearer ".$this->token['access_token']
      ));
      $output = json_decode(curl_exec($ch), 1);
      curl_close($ch);
      $this->handleError($output);
      return (isset($output['files'])&&sizeof($output['files'])>0?$output['files'][0]['id']:false);
   }

   public function is_file($name, $mime, $parent="root"){
      $this->checkToken();
      $optParams = array(
      'q'        => "name = '".addslashes($name)."' and mimeType = '$mime' and '$parent' in parents and trashed = false",
      'fields'   => 'files(id, name, mimeType, kind, parents)'
      );

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/drive/v3/files?".http_build_query($optParams));
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Authorization: Bearer ".$this->token['access_token']
      ));
      $output = json_decode(curl_exec($ch), 1);
      curl_close($ch);
      $this->handleError($output);
      return (isset($output['files'])&&sizeof($output['files'])>0?$output['files'][0]['id']:false);
   }

   public function create_dir($name, $pid="root"){
      $check = $this->is_dir($name, $pid);
      if ($check){
         return $check;
      }
      else {
         $postdata     = array(
         'name'     => $name,
         'parents'  => array($pid),
         'mimeType' => "application/vnd.google-apps.folder"
         );

         $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/drive/v3/files");
         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
         curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
         curl_setopt($ch, CURLOPT_HTTPHEADER, array(
         "Content-Type: application/json",
         "Authorization: Bearer ".$this->token['access_token']
         ));
         curl_setopt($ch, CURLOPT_POST, true);
         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));

         $output = json_decode(curl_exec($ch), 1);
         curl_close($ch);
         $this->handleError($output);
         return (isset($output['id'])?$output['id']:false);
      }
   }

   public function simpleUpload($localpath, $pid="root"){
      $name  = basename($localpath);
      $mime  = mime_content_type($localpath);
      $postdata    = array('name' => $name, 'parents' => array($pid));
      $boundary    = "---------------------".md5(mt_rand().microtime());
      $post        = array();
      $post[]      = implode("\r\n", array("Content-Type: application/json; charset=UTF-8", "", json_encode($postdata)));
      $post[]      = implode("\r\n", array("Content-Type: $mime", "", file_get_contents($localpath)));

      array_walk($post, function (&$part) use ($boundary) {
         $part = "--{$boundary}\r\n{$part}";
      }
      );

      $post[]      = "--{$boundary}--";
      $post[]      = "";

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart");
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Authorization: Bearer ".$this->token['access_token'],
      "Content-Type: multipart/related; boundary=".$boundary
      ));
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, implode("\r\n", $post));
      curl_setopt($ch, CURLINFO_HEADER_OUT, true);
      $oo      = curl_exec($ch);
      $output  = json_decode($oo, 1);
      curl_close($ch);
      $this->handleError($output);
      return (isset($output['id'])?$output['id']:false);
   }
   public function get_headers_from_curl_response($response)
   {
      $headers     = array();
      $header_text = substr($response, 0, strpos($response, "\r\n\r\n"));
      foreach (explode("\r\n", $header_text) as $i => $line)
      if ($i === 0){
         $headers['http_code'] = $line;
      }
      else
      {
         list($key, $value) = explode(': ', $line);
         $headers[$key] = $value;
      }
      return $headers;
   }

   public function chunkUpload($localpath, $pid="root"){
      $chunksize   = 100*1024*1024;
      $name        = basename($localpath);
      $mime        = mime_content_type($localpath);
      $size        = filesize($localpath);
      $postdata    = array('name' => $name, 'parents' => array($pid));

      $ch          = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable");
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
      curl_setopt($ch, CURLOPT_HEADER, 1);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Authorization: Bearer ".$this->token['access_token'],
      "Content-Type: application/json; charset=UTF-8",
      "X-Upload-Content-Type: $mime",
      "X-Upload-Content-Length: $size"
      ));
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
      //curl_setopt($ch, CURLINFO_HEADER_OUT, true);
      $response      = curl_exec($ch);
      curl_close($ch);
      $headers       = $this->get_headers_from_curl_response($response);
      $location      = $headers['Location'];
      //--------------------------------------------------------------------------------------------------------------------
      if (!isset($headers['Location'])){
         print_r($headers);
      }
      else {
         $maxChunks      = ceil($size/$chunksize);

         for ($c=1; $c<=$maxChunks; $c++){
            $chunks[] = $c;
         }

         $file           = fopen($localpath, "rb");
         foreach($chunks as $chunk){
            $start       = ($chunk-1)*$chunksize;
            $end         = ($chunk)*$chunksize;
            if ($end > $size){
               $end      = $size;
            }
            fseek($file, $start);
            $ch          = curl_init();
            curl_setopt($ch, CURLOPT_URL, $location);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$this->token['access_token'],
            "Content-Type: $mime",
            "Content-Length: ".($end-$start),
            "Content-Range: bytes ".$start."-".($end-1)."/".$size
            ));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, fread($file, ($end-$start)));
            $out         = curl_exec($ch);
            curl_close($ch);
         }
         fclose($file);
         $res = json_decode($out, 1);
         return $res['id'];
      }
   }
   public function scandir($remotepath){
      $this->checkToken();
      $remotepath  = trim($remotepath,"/");
      if ($remotepath){
        $dir       = $this->mkdir($remotepath);
      } else {
        $dir       = "root";
      }
      $optParams = array(
      'q'        => "'$dir' in parents and trashed = false",
      'fields'   => 'files(id, name, mimeType, kind, parents)'
      );
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/drive/v3/files?".http_build_query($optParams));
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Authorization: Bearer ".$this->token['access_token']
      ));
      $output = json_decode(curl_exec($ch), 1);
      curl_close($ch);
      $this->handleError($output);
      return $output;
   }

   public function mkdir($dir, $level=0, $pid="root")
   {
      $this->checkToken();
      $dir     = trim($dir, "/");
      $levels  = explode("/", $dir);
      if (isset($levels[$level])){
         $path = implode("/", array_slice($levels, 0, $level+1));
         if (isset($this->paths[$path])){
            $pid  = $this->paths[$path];
         }
         else {
            $pid  = $this->create_dir($levels[$level], $pid);
            $this->paths[$path] = $pid;
         }
      }

      if ($pid && $level < sizeof($levels)){
         $pid  = $this->mkdir($dir, $level+1, $pid);
      }
      return $pid;
   }

   public function upload($localpath, $remotepath){
      $pid     = $this->mkdir(dirname($remotepath));
      if ($pid){
         $fid  = $this->is_file(basename($localpath), mime_content_type($localpath), $pid);
         if (filesize($localpath) <= 100*1024*1024){
            $nid = $this->simpleUpload($localpath, $pid);
         }
         else {
            $nid = $this->chunkUpload($localpath, $pid);
         }
         if ($fid&&$nid){
            $this->delete($fid);
         }
         return $nid;
      }
   }
   public function download($fid)
   {
      $this->checkToken();
      $ch          = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/drive/v3/files/$fid?alt=media");
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Authorization: Bearer ".$this->token['access_token']
      ));
      $out         = curl_exec($ch);
      curl_close($ch);
      return $out;
   }
   
   public function trash($fid)
   {
      $this->checkToken();
      $postdata    = array('trashed' => true);
      $ch          = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/drive/v3/files/$fid");
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Authorization: Bearer ".$this->token['access_token'],
      "Content-Type: application/json; charset=UTF-8"
      ));
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
      $out         = curl_exec($ch);
      curl_close($ch);
      return $out;
   }
   
   public function delete($fid)
   {
      $this->checkToken();
      $ch          = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/drive/v3/files/$fid");
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Authorization: Bearer ".$this->token['access_token']
      ));
      $out         = curl_exec($ch);
      curl_close($ch);
      return $out;
   }
}
