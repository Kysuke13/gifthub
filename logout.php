<?php

include 'page.php';
// Remplacez les valeurs ci-dessous par vos informations d'application GitHub
$client_id = '5caac847508720c17873';
$client_secret = '6ac4cd61971e49437f225dc034aca43742736002';

// Récupérez l'access token de la session
$access_token = $_SESSION['access_token']; // Assurez-vous que la session est démarrée

// Révoquer l'accès à l'application GitHub
$ch = curl_init("https://api.github.com/applications/$client_id/token");
curl_setopt($ch, CURLOPT_CAINFO, 'C:\wamp64\www\github-stats\cacert.pem');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . base64_encode("$client_id:$client_secret"),
    'Accept: application/vnd.github.v3+json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['access_token' => $access_token]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo 'Erreur cURL : ' . curl_error($ch);
}

curl_close($ch);

// Détruire la session de votre application
session_unset();
session_destroy();

// Rediriger l'utilisateur vers la page d'accueil ou une autre page après la déconnexion

exit();
?>
