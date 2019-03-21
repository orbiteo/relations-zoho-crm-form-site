<?php
if ( ! session_id() ) {
  @session_start();
}
require_once($_SERVER["DOCUMENT_ROOT"] . '/wp-config.php');

if($_POST) {
  $request = wp_remote_post( "https://accounts.zoho.eu/oauth/v2/token?refresh_token=1000.f60b1a00d5815f3f2499bc71bbcf41f1.d43d0a7c0602c9da2ba98ed1e0df70b3&client_id=1000.8587O48MKZWU7822118C2HDOQ39Q32&client_secret=cc95145e2740a105d1e67f1cc3b558d7a7860f9f74&grant_type=refresh_token");
  $pins = json_decode( $request['body'], true );
  $access_token = $pins["access_token"];

  if(!isset($_SESSION["audit"]["ip"])) { // Si pas de $_SESSION créé
      // Récuperer IP internaute + créer SESSION spéciale
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) { //check ip from share internet
      $_SESSION["audit"]["ip"]=$_SERVER['HTTP_CLIENT_IP'];
    }
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { //to check ip is pass from proxy
      $_SESSION["audit"]["ip"]=$_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    else {
      $_SESSION["audit"]["ip"]=$_SERVER['REMOTE_ADDR'];
    }

    // On récupère le secteur d'activité
    if(isset($_POST["Industry"]) && !empty($_POST["Industry"])) {
      $industry = $_POST["Industry"];
      $ebookDl = stripslashes($_POST["ebookDl"]);
      $champs = json_encode(array("data" => array(["Last_Name" => "Audit en ligne", "Industry" => stripslashes($industry), "Ebook_t_l_charg_s" => [$ebookDl]])));
      $apiUrl = "https://www.zohoapis.eu/crm/v2/Leads";
      $_SESSION["audit"]["industry"] = $industry;
      $_SESSION["audit"]["ebookDl"] = $ebookDl;
    }

    $headers = array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($champs),
        sprintf('Authorization: Zoho-oauthtoken %s', $access_token)
    );

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $champs);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $repCreateOrUpdate = curl_exec($ch);

    $repCreateOrUpdate = json_decode($repCreateOrUpdate);

    foreach($repCreateOrUpdate as $infoRep) {
      $_SESSION["audit"]["idLead"] = $infoRep[0]->details->id;
    }
    curl_close($ch);
    wp_redirect(get_site_url().'/audit-en-ligne-0/etape-1/');
  }
  elseif(empty($_SESSION["audit"]["idLead"])) {
    session_unset();   // détruit les variables de session
    session_destroy();
    wp_redirect(get_site_url().'/audit-en-ligne-0');
  }
  elseif(isset($_SESSION["audit"]["ip"])) {
    if(isset($_POST["website"])) {
      $champs = json_encode(array("data" => array(["Website" => $_POST["website"]])));
      $urlSuivante = "/audit-en-ligne-0/etape-1/etape-2/";
    }
    elseif(isset($_POST["concurrents"])) {
      $champs = json_encode(array("data" => array(["Concurrents" => $_POST["concurrents"], "Objectifs_Audit" => stripslashes($_POST["Objectifs_Audit"])])));
      $urlSuivante = "/audit-en-ligne-0/etape-1/etape-2/etape-3/";
    }
    elseif(isset($_POST["formule_audit"])) {
      if($_POST["formule_audit"] == " RECEVOIR L\'ANALYSE PAR E-MAIL ") {
        $champs = json_encode(array("data" => array(["Formule_choisie" => "Formule 1", "Pr_f_rence_de_contact" => "Email"])));
        $urlSuivante = "/audit-en-ligne-0/etape-1/etape-2/etape-3/etape-4-email/";
      }
      elseif($_POST["formule_audit"] == "PRÉSENTEZ-MOI CETTE ANALYSE !") {
        $champs = json_encode(array("data" => array(["Formule_choisie" => "Formule 2", "Pr_f_rence_de_contact" => "Téléphone"])));
        $urlSuivante = "/audit-en-ligne-0/etape-1/etape-2/etape-3/etape-4-telephone/";
      }

    }
    elseif(isset($_POST["Last_Name"])) {
      $champs = json_encode(array("data" => array(["Last_Name" => $_POST["Last_Name"], "First_Name" => $_POST["First_Name"], "Company" => $_POST["Company"], "Service" => $_POST["LEADCF2"], "Email" => $_POST["Email"], "Phone" => $_POST["Phone"], "Description" => $_POST["Description"]])));
      $urlSuivante = "/merci-audit/";
    }
    else { // Si la personne a déjà renseigné la 1ère question lors d'une session
      $urlSuivante = "/audit-en-ligne-0/etape-1/";
    }

    $header = array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($champs),
        sprintf('Authorization: Zoho-oauthtoken %s', $access_token)
    );

    $url = "https://www.zohoapis.eu/crm/v2/Leads/".$_SESSION["audit"]["idLead"];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $champs);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);

    $request = curl_exec($ch);
    $requestObject = json_decode($request);
    foreach($requestObject as $detDecode) {
      if($detDecode[0]->code == "INVALID_DATA") {
        session_unset();   // détruit les variables de session
        session_destroy();
        wp_redirect(get_site_url().'/audit-en-ligne-0');
      } else{
        curl_close($ch);
        wp_redirect(get_site_url().$urlSuivante);
      }
    }
  }
}
exit();
