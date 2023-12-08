<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitHub Stats</title>
</head>
<body>

<?php
function get_github_data($owner, $repo, $token) {
    // URL pour les Pull Requests
    $pull_requests_url = "https://api.github.com/repos/$owner/$repo/pulls";
    
    $ch = curl_init($pull_requests_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Ajout de l'en-tête d'autorisation
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: token ' . $token]);

    // Ajout de l'en-tête User-Agent
    curl_setopt($ch, CURLOPT_USERAGENT, 'Nom_de_votre_application'); // Remplacez 'Nom_de_votre_application' par un nom significatif

    // Désactiver la vérification du certificat SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $pull_requests_data_raw = curl_exec($ch);

    // Ajout de vérifications d'erreur
    if (curl_errno($ch)) {
        echo 'Erreur cURL : ' . curl_error($ch);
    }

    curl_close($ch);

    // Afficher la réponse brute
    echo '<pre>';
    print_r($pull_requests_data_raw);
    echo '</pre>';

    $pull_requests_data = json_decode($pull_requests_data_raw, true);

    // URL pour les statistiques des contributeurs
    $stats_url = "https://api.github.com/repos/$owner/$repo/stats/contributors";
    $ch = curl_init($stats_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Ajout de l'en-tête d'autorisation
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: token ' . $token]);

    // Ajout de l'en-tête User-Agent
    curl_setopt($ch, CURLOPT_USERAGENT, 'Nom_de_votre_application'); // Remplacez 'Nom_de_votre_application' par un nom significatif

    // Désactiver la vérification du certificat SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $stats_data_raw = curl_exec($ch);

    // Ajout de vérifications d'erreur
    if (curl_errno($ch)) {
        echo 'Erreur cURL : ' . curl_error($ch);
    }

    curl_close($ch);

    // Afficher la réponse brute
    echo '<pre>';
    print_r($stats_data_raw);
    echo '</pre>';

    $stats_data = json_decode($stats_data_raw, true);

    return [$pull_requests_data, $stats_data];
}

// Remplacez 'votre_owner', 'votre_repo', et 'votre_token' par vos propres informations
$owner = 'kysuke13';
$repo = 'gifthub';
$token = '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';

list($pull_requests, $stats) = get_github_data($owner, $repo, $token);

?>

<h1>Nombre de Pull Requests</h1>
<?php if (!empty($pull_requests)): ?>
    <ul>
        <?php foreach ($pull_requests as $pr): ?>
            <li><?php echo $pr['number']; ?></li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>Aucune Pull Request trouvée.</p>
<?php endif; ?>

<h1>Repository Stats</h1>
<?php if (!empty($stats)): ?>
    <ul>
        <?php foreach ($stats as $contributor): ?>
            <li><?php echo $contributor['author']['login'] . ': ' . $contributor['total'] . ' lignes modifiées'; ?></li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>Aucune statistique trouvée.</p>
<?php endif; ?>

</body>
</html>
