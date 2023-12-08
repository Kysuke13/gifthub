<header>
   <img src="https://wasch.lu/wp-content/uploads/2023/11/Design-sans-titre-15.png" alt="Logo de votre entreprise" style="width: 150px; height: 150;">
    <h1>Bienvenue sur la 1ère plateforme de rewards pour les développeurs</h1>
    <nav>
        <ul>
            <li><a href="index.php">Accueil</a></li>
            <li><a href="toto.php">Paramètres</a></li>
			<li><a href="recompense.php">Récompenses</a></li>
			<li><a href="logout.php">Déconnexion</a></li>
        </ul>
    </nav>
	    <div class="avatar">
        <?php echo '<img src="' . $user['avatar_url'] . '" alt="Avatar de ' . $user['login'] . '">'; ?>
    </div>
</header>