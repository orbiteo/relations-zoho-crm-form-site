<?php
require_once($_SERVER["DOCUMENT_ROOT"] . '/wp-config.php');

if(isset($_POST)) {
  $request = wp_remote_post( "https://accounts.zoho.eu/oauth/v2/token?refresh_token=1000.f60b1a00d5815f3f2499bc71bbcf41f1.d43d0a7c0602c9da2ba98ed1e0df70b3&client_id=1000.8587O48MKZWU7822118C2HDOQ39Q32&client_secret=cc95145e2740a105d1e67f1cc3b558d7a7860f9f74&grant_type=refresh_token");
  $pins = json_decode( $request['body'], true );
  $access_token = $pins["access_token"];

  $nom = htmlspecialchars($_POST["Last_Name"]);
  $prenom = htmlspecialchars($_POST["First_Name"]);
  if(isset($_POST["Company"])) { // Uniquement sur formulaires de contact
    $company = htmlspecialchars($_POST["Company"]);
  }
  if(isset($_POST["Website"])) {
    $website = htmlspecialchars($_POST["Website"]);
  }
  if(isset($_POST["LEADCF2"])) { // Uniquement sur formulaires ebook
    $leadcf2 = $_POST["LEADCF2"];
  }
  if(isset($_POST["Industry"])) {
    $industry = $_POST["Industry"];
  }
  if(isset($_POST["Email"]) && !empty($_POST["Email"])) { // Si vide, on est susceptible de remplacer une variable renseignée dans le crm par rien
    $email = htmlspecialchars($_POST["Email"]);
  }
  if(isset($_POST["Phone"]) && !empty($_POST["Phone"])) {
    $phone = htmlspecialchars($_POST["Phone"]);
  }
  if(isset($_POST["Description"]) && !empty($_POST["Description"])) {
    $description = htmlspecialchars($_POST["Description"]);
  }

  // Ajouter specif en f° du formulaire envoyé: téléchargement ebook... "Ebook_t_l_charg_s"
  // Ajouter d'abord un champs caché dans le formulaire.
  $ebookDl = $_POST["ebookDl"];


  //Vérifier dans un 1er temps si le lead existe déjà (via email)
  $emailVerified = json_encode(array("data" => array(["Email" => $email])));
  $emailVerifiedUrl = "https://www.zohoapis.eu/crm/v2/Leads/search?email=".$email;
  $emailVerifiedHeaders = array(
      'Content-Type: application/json',
      sprintf('Authorization: Zoho-oauthtoken %s', $access_token)
  );

  $emailVerifiedCh = curl_init();
  curl_setopt($emailVerifiedCh, CURLOPT_URL, $emailVerifiedUrl);
  curl_setopt($emailVerifiedCh, CURLOPT_HTTPHEADER, $emailVerifiedHeaders);
  curl_setopt($emailVerifiedCh, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($emailVerifiedCh, CURLOPT_CONNECTTIMEOUT, 60);
  curl_setopt($emailVerifiedCh, CURLOPT_TIMEOUT, 60);

  $toto = curl_exec($emailVerifiedCh);
  $retourDecode = (json_decode($toto));

  curl_close($emailVerifiedCh);

  $fields = json_encode(array("data" => array(["Last_Name" => stripslashes($nom), "First_Name" => stripslashes($prenom), "Ebook_t_l_charg_s" => [$ebookDl], "Company" => stripslashes($company), "Website" => stripslashes($website), "Lead_Source" => "Formulaire Web", "Email" => $email, "Phone" => $phone, "Description" => stripslashes($description), "Service" => $leadcf2, "Industry" => $industry])));
  //$fields = json_encode(array("data" => array(["Last_Name" => "SCHMITT", "First_Name" => "Eloïse", "Company" => "Orbiteo", "Website" => "https://orbiteo.com", "Lead_Source" => "Formulaire Web", "Email" => "eloise@orbiteo.com", "Phone" => "0999877678", "Description" => "test"])));

  if($retourDecode != "NULL") { //S'il existe on met à jour
    $apiUrl = "https://www.zohoapis.eu/crm/v2/Leads/upsert";
  }
  else { // S'il n'existe pas, on créé
    $apiUrl = "https://www.zohoapis.eu/crm/v2/Leads";
  }
    $headers = array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($fields),
        sprintf('Authorization: Zoho-oauthtoken %s', $access_token)
    );

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $repCreateOrUpdate = curl_exec($ch);

    $repCreateOrUpdate = json_decode($repCreateOrUpdate);
    foreach($repCreateOrUpdate as $infoRep) {
      $idLead = $infoRep[0]->details->id;
    }
    curl_close($ch);

    //Gestion du fichier uplaoder:
    $files[] = array(
    'name' => $_FILES['theFile']['name'],
    'file' => $_FILES['theFile']['tmp_name']
    );

    if ( count($files) > 0 ) {
      // Upload Attachment
      foreach ( $files as $singleFile ) {
        $date = new DateTime();
        //Get the time stamp from the variable
        $currentTime= $date->getTimestamp();
        //Read the content from the pdf
        $pdfName = $singleFile['name'];
        $file_data = file_get_contents($singleFile['file']);

        //Declare a variable for enctype for sending the file to creator
        $KLineEnd = "\r\n";
        $kDoubleHypen = "--";
        $kContentDisp = "Content-Disposition: form-data; name=\"file\";filename=\"";

        //Encoding the fileds and makes body map variable
        $param = utf8_encode($KLineEnd);
        $header = ['ENCTYPE: multipart/form-data','Content-Type:multipart/form-data;boundary='.(string)$currentTime, sprintf('Authorization: Zoho-oauthtoken %s', $access_token)];
        $encode_var = $kDoubleHypen . (string)$currentTime . $KLineEnd ;
        $param = $param . utf8_encode($encode_var);
        $temp = $kContentDisp . $pdfName . "\"" . $KLineEnd . $KLineEnd ;
        $param = $param . utf8_encode($temp);
        $param = $param . $file_data . utf8_encode($KLineEnd);
        $temp_var = $kDoubleHypen . (string)$currentTime . $kDoubleHypen . $KLineEnd . $KLineEnd;
        $param = $param . utf8_encode($temp_var);
        $url = "https://www.zohoapis.eu/crm/v2/Leads/".$idLead."/Attachments";


        //curl declaration for sending the data as a post method to creator with header and body map variable with constant timeout
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);

        curl_exec($ch);
        curl_close($ch);
      }
    }
    if(isset($_POST["Company"])) {
      wp_redirect(get_site_url().'/merci-contact');
    }
    else{
      wp_redirect(get_site_url().'/merci-ressources');
    }

    exit();
}
