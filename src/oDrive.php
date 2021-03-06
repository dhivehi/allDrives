<?php
namespace Dhivehi;
class oDrive {
   private $client_id            = "YOUR_CLIENT_ID";
   private $client_secret        = "YOUR_CLIENT_SECRETE";
   private $tokenStore           = "/path/to/store/token.json";
   public $paths                 = array();
   
   public function __construct($ts){
      $this->redirect_uri        = "YOUR_REDIRECT_URI"; //use:$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"].$_SERVER["PHP_SELF"];
      if ($ts){
         $this->tokenStore       = $ts;
      }
      if ($this->has_token()) {
         $this->token            = json_decode($this->get_token(), 1);
      }
      else if (isset($_GET['code'])){
         $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, "https://login.live.com/oauth20_token.srf");
         curl_setopt($ch, CURLOPT_POST, 1);
         $data = "client_id=".$this->client_id."&redirect_uri=".urlencode($this->redirect_uri)."&client_secret=".urlencode($this->client_secret)."&code=".$_GET['code']."&grant_type=authorization_code";
         curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
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
      "client_id"       => $this->client_id,
      "redirect_uri"    => $this->redirect_uri,
      "scope"           => "wl.signin,wl.offline_access,wl.skydrive_update,wl.basic"
      );
      return "https://login.live.com/oauth20_authorize.srf?".http_build_query($params);
   }

   public function refreshToken() {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://login.live.com/oauth20_token.srf");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/x-www-form-urlencoded',
      ));
      curl_setopt($ch, CURLOPT_POST, TRUE);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
      "client_id"     =>$this->client_id,
      "redirect_uri"  =>$this->redirect_uri,
      "client_secret" =>$this->client_secret,
      "refresh_token" =>$this->token['refresh_token'],
      "grant_type"    =>"refresh_token"
      )));

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
   //---------------------------------------------------------------
   public function handleError($output){
      if (isset($output['error'])){
         print_r($output);
         echo "Process terminated.\n";
         exit;
      }
   }
   
   public function basename($path){
      $p = explode("/",$path);
      return end($p);
   }

   public function scandir($remotepath){
      $this->checkToken();
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://api.onedrive.com/v1.0/drive/root:".urlencode($remotepath).":/children");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FAILONERROR, false);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Authorization: Bearer '.$this->token['access_token']
      ));
      $output = json_decode(curl_exec($ch), 1);
      curl_close($ch);
      $this->handleError($output);
      return $output;
   }

   public function mkdir($path){
      $j           = array();
      $j['name']   = $this->basename($path);
      $j['folder'] = new \stdClass();
      $this->checkToken();
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://api.onedrive.com/v1.0/drive/root:".urlencode(dirname($path)).":/children?nameConflict=fail");
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FAILONERROR, false);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Authorization: Bearer '.$this->token['access_token']
      ));
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($j));
      $output = json_decode(curl_exec($ch), 1);
      curl_close($ch);
      $this->handleError($output);
      return (isset($output['id'])?$output['id']:false);
   }
   
   public function simpleUpload($localpath,$remotepath){
      $this->checkToken();
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://api.onedrive.com/v1.0/drive/root:".urlencode($remotepath).":/content?nameConflict=replace");
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FAILONERROR, false);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Authorization: Bearer '.$this->token['access_token']
      ));
      curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($localpath));
      $output  = json_decode(curl_exec($ch), 1);
      curl_close($ch);
      $this->handleError($output);
      return (isset($output['id'])?$output['id']:false);
   }
   
   public function chunkUpload($localpath, $remotepath){
      $chunksize   = 20*1024*1024;
      $name        = $this->basename($localpath);
      $mime        = mime_content_type($localpath);
      $size        = filesize($localpath);
      $postdata    = array("item"=>array("@name.conflictBehavior"=>"replace", "name"=>$name));

      $this->checkToken();
   
      $ch          = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://api.onedrive.com/v1.0/drive/root:".urlencode(dirname($remotepath))."/".urlencode($this->basename($remotepath)).":/upload.createSession");
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FAILONERROR, false);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Authorization: Bearer '.$this->token['access_token']
      ));
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
      $output                    = json_decode(curl_exec($ch),1);
      if (!isset($output['uploadUrl'])){
         print_r($headers);
      }
      else {
         $location                  = $output['uploadUrl'];
         $maxChunks                 = ceil($size/$chunksize);

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
   
   public function upload($localpath, $remotepath){
      $pid     = $this->mkdir(dirname($remotepath));
      if ($pid){
         if (filesize($localpath) < 100*1024*1024){
            $nid = $this->simpleUpload($localpath, $remotepath);
         }
         else {
            $nid = $this->chunkUpload($localpath, $remotepath);
         }
         return $nid;
      }
   }
   public function download($fid)
   {
      $this->checkToken();
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://api.onedrive.com/v1.0/drive/items/$fid/content");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FAILONERROR, false);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Authorization: Bearer '.$this->token['access_token']
      ));
      $out         = curl_exec($ch);
      curl_close($ch);
      return $out;
   }
   
   public function trash($fid)
   {
      $this->checkToken();
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://api.onedrive.com/v1.0/drive/items/$fid");
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Authorization: Bearer '.$this->token['access_token']
      ));
      $out         = curl_exec($ch);
      curl_close($ch);
      return $out;
   }
   
   public function delete($fid)
   {
      $this->checkToken();
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://api.onedrive.com/v1.0/drive/items/$fid");
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Authorization: Bearer '.$this->token['access_token']
      ));
      $out         = curl_exec($ch);
      curl_close($ch);
      return $out;
   }
}
