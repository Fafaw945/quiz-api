-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : jeu. 30 oct. 2025 à 18:10
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `quiz_game`
--

-- --------------------------------------------------------

--
-- Structure de la table `game_status`
--

CREATE TABLE `game_status` (
  `id` int(11) NOT NULL DEFAULT 1,
  `started` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `participants`
--

CREATE TABLE `participants` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `pseudo` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `score` int(11) DEFAULT 0,
  `is_admin` tinyint(1) DEFAULT 0,
  `is_ready` tinyint(1) DEFAULT 0,
  `game_started` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Déchargement des données de la table `participants`
--

INSERT INTO `participants` (`id`, `name`, `pseudo`, `email`, `password`, `score`, `is_admin`, `is_ready`, `game_started`) VALUES
(16, 'Fawzi Youjil', 'Fafa', 'fawziattia9@gmail.com', '$2y$10$8L41wbHmiKUdLOmIkFMijekKzSZJuZMj.5al8PbmOgKct9YEQxzAW', 45, 1, 1, 0),
(18, '3', '3', '3@3.co', '$2y$10$.JCh2awK3D91dL..3nT4pO1J1Vp9ht.68Z7Oei7BUSA1EYogX1ob2', 16, 0, 0, 0),
(19, '4', '4', '4@4.co', '$2y$10$owhSiLKj3DqZuwTu2hKqSOGpWkHaNep8IICTfCHDVSNrlKIKKexjC', 0, 0, 0, 0),
(20, '5', '5', '5@5.co', '$2y$10$XuhGhrPmmXZTPcCO/jRFHOI5tO2FpTkWj9r2IVrSYTFcFCiE3F/Pu', 9, 0, 0, 0),
(21, '6', '6', '6@6.co', '$2y$10$XqHq/t/nW7cS5SdHnvKp3egs.sg5bgO5Y2vxOeKw79kcgnzqPBJ8e', 15, 0, 0, 0),
(22, 'Alice', 'Ali', 'alice@example.com', '$2y$10$WKOtIT0V1ovI2bAwvb5bweUSCX4Vz/V8Je.Tf3.nQch0FFQEomh1e', 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Structure de la table `players`
--

CREATE TABLE `players` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `pseudo` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `score` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Déchargement des données de la table `players`
--

INSERT INTO `players` (`id`, `name`, `pseudo`, `email`, `password`, `score`, `created_at`, `updated_at`) VALUES
(1, 'Fawzi', NULL, 'fawzi@test.com', '$2y$10$uivmXEG9CThkv/Af0KVV/eMd0CUEdZsIfqALOUnEs9BIZlwbuCCSq', 0, '2025-08-26 20:17:13', '2025-08-26 20:17:13'),
(3, 'Fawzi', 'Faf', 'fawi@example.com', '$2y$10$Mz2/07zK3FjUFL6NNp5h6e9hPFA0m.JbegcRs/hgBlVuvk23MnCgi', 0, '2025-08-29 14:32:16', '2025-08-29 14:32:16'),
(4, 'bvsdb', 'dsfDF', 'ffff@g.com', '$2y$10$BaSsTV7atGpTo/zfBIWJfefJWajinhdFR7OQvMlN5QttzIR4t6Y96', 10, '2025-10-08 10:42:40', '2025-10-08 10:42:48'),
(5, 'lrkg', 'gqgfqs', 'gdggf@g.com', '$2y$10$DKwkTz3leqW1vtCoRhVaueY2kf9b1EVHEGT8Hgp1UrnuUgbE.QMeq', 50, '2025-10-08 10:43:19', '2025-10-08 10:50:22');

-- --------------------------------------------------------

--
-- Structure de la table `player_questions`
--

CREATE TABLE `player_questions` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `question` text NOT NULL,
  `correct_answer` varchar(255) NOT NULL,
  `incorrect_answers` text NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `difficulty` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Déchargement des données de la table `questions`
--

INSERT INTO `questions` (`id`, `question`, `correct_answer`, `incorrect_answers`, `category`, `difficulty`, `created_at`, `is_used`) VALUES
(564, 'Qui a peint la Joconde ?', 'Léonard de Vinci', '[\"Michel-Ange\",\"Raphaël\",\"Donatello\"]', 'Art', 'facile', '2025-10-18 09:21:20', 1),
(565, 'Combien y a-t-il de continents sur Terre ?', '7', '[\"5\",\"6\",\"8\"]', 'Géographie', 'facile', '2025-10-18 09:21:20', 1),
(566, 'Quel est le symbole chimique de l\'eau ?', 'H2O', '[\"O2\",\"HO2\",\"H2\"]', 'Science', 'facile', '2025-10-18 09:21:20', 1),
(569, 'En quelle année l’homme a-t-il marché sur la Lune pour la première fois ?', '1969', '[\"1965\",\"1971\",\"1975\"]', 'Histoire', 'moyen', '2025-10-18 09:21:20', 1),
(575, 'Quel est le symbole chimique de l\'or ?', 'Au', '[\"Ag\",\"Pb\",\"Gd\"]', 'Science', 'moyen', '2025-10-18 09:21:20', 1),
(578, 'Quelle ville est surnommée \'la ville lumière\' ?', 'Paris', '[\"Lyon\",\"Marseille\",\"Nice\"]', 'Géographie', 'facile', '2025-10-18 09:21:20', 1),
(580, 'Quelle est la capitale de l’Australie ?', 'Canberra', '[\"Sydney\",\"Melbourne\",\"Brisbane\"]', 'Géographie', 'moyen', '2025-10-18 09:21:20', 1),
(581, 'Qui a composé la 9e symphonie ?', 'Ludwig van Beethoven', '[\"Mozart\",\"Bach\",\"Chopin\"]', 'Musique', 'difficile', '2025-10-18 09:21:20', 1),
(584, 'Qui a écrit \'Candide\' ?', 'Voltaire', '[\"Rousseau\",\"Diderot\",\"Montesquieu\"]', 'Littérature', 'moyen', '2025-10-18 09:21:20', 1),
(585, 'Quel est le plus grand mammifère terrestre ?', 'Éléphant d\'Afrique', '[\"Rhinocéros\",\"Hippopotame\",\"Girafe\"]', 'Science', 'facile', '2025-10-18 09:21:20', 1),
(590, 'Qui a écrit \'Roméo et Juliette\' ?', 'William Shakespeare', '[\"Victor Hugo\",\"Molière\",\"Voltaire\"]', 'Littérature', 'facile', '2025-10-18 09:21:20', 1),
(591, 'Quelle est la formule chimique du sel de table ?', 'NaCl', '[\"KCl\",\"Na2SO4\",\"NaOH\"]', 'Science', 'facile', '2025-10-18 09:21:20', 1),
(594, 'Quel est le plus grand lac d\'Afrique ?', 'Lac Victoria', '[\"Lac Tanganyika\",\"Lac Malawi\",\"Lac Tchad\"]', 'Géographie', 'moyen', '2025-10-18 09:21:20', 1),
(597, 'Quel est le principal gaz à effet de serre ?', 'Dioxyde de carbone', '[\"Méthane\",\"Oxygène\",\"Azote\"]', 'Science', 'facile', '2025-10-18 09:21:20', 1),
(606, 'Quel est l\'élément chimique le plus abondant dans l\'univers ?', 'Hydrogène', '[\"Oxygène\",\"Hélium\",\"Carbone\"]', 'Science', 'moyen', '2025-10-18 09:21:20', 1),
(609, 'Qui a écrit \'Germinal\' ?', 'Émile Zola', '[\"Victor Hugo\",\"Balzac\",\"Flaubert\"]', 'Littérature', 'moyen', '2025-10-18 09:21:20', 1),
(611, 'Quel est le plus grand volcan actif du monde ?', 'Mauna Loa', '[\"Etna\",\"Kilauea\",\"Vésuve\"]', 'Science', 'moyen', '2025-10-18 09:21:20', 1),
(617, 'Qui a inventé le téléphone ?', 'Alexander Graham Bell', '[\"Thomas Edison\",\"Nikola Tesla\",\"Guglielmo Marconi\"]', 'Science', 'moyen', '2025-10-18 09:21:20', 1),
(618, 'Quelle est la capitale de la Chine ?', 'Pékin', '[\"Shanghai\",\"Hong Kong\",\"Guangzhou\"]', 'Géographie', 'facile', '2025-10-18 09:21:20', 1),
(621, 'Quelle est la capitale de la Russie ?', 'Moscou', '[\"Saint-Pétersbourg\",\"Novossibirsk\",\"Kazan\"]', 'Géographie', 'facile', '2025-10-18 09:21:20', 1),
(622, 'Qui a inventé l\'aviation moderne ?', 'Les frères Wright', '[\"Leonardo da Vinci\",\"Alberto Santos-Dumont\",\"Otto Lilienthal\"]', 'Science', 'moyen', '2025-10-18 09:21:20', 1),
(623, 'Quel est le plus grand fleuve d\'Amérique du Sud ?', 'Amazone', '[\"Orénoque\",\"Paraná\",\"São Francisco\"]', 'Géographie', 'moyen', '2025-10-18 09:21:20', 1),
(625, 'Quel est le plus grand pays d\'Afrique ?', 'Algérie', '[\"Soudan\",\"Libye\",\"République Démocratique du Congo\"]', 'Géographie', 'moyen', '2025-10-18 09:21:20', 1),
(626, 'Qui a inventé la radio ?', 'Guglielmo Marconi', '[\"Nikola Tesla\",\"Alexander Graham Bell\",\"Thomas Edison\"]', 'Science', 'moyen', '2025-10-18 09:21:20', 1),
(629, 'Quel est le plus haut sommet d\'Europe ?', 'Mont Blanc', '[\"Mont Elbrouz\",\"Matterhorn\",\"Grossglockner\"]', 'Géographie', 'moyen', '2025-10-18 09:21:20', 1),
(634, 'Qui a écrit \'La Chartreuse de Parme\' ?', 'Stendhal', '[\"Balzac\",\"Flaubert\",\"Victor Hugo\"]', 'Littérature', 'moyen', '2025-10-18 09:21:20', 1),
(635, 'Quel est le plus grand lac d\'Europe ?', 'Lac Léman', '[\"Lac de Constance\",\"Lac Balaton\",\"Lac Vänern\"]', 'Géographie', 'moyen', '2025-10-18 09:21:20', 1),
(638, 'Qui a écrit \'La Divine Comédie\' ?', 'Dante Alighieri', '[\"Pétrarque\",\"Boccace\",\"Chaucer\"]', 'Littérature', 'difficile', '2025-10-18 09:21:20', 1);

-- --------------------------------------------------------

--
-- Structure de la table `scores`
--

CREATE TABLE `scores` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `week` varchar(20) NOT NULL,
  `score` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `used_questions`
--

CREATE TABLE `used_questions` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Déchargement des données de la table `used_questions`
--

INSERT INTO `used_questions` (`id`, `question_id`, `user_id`, `used_at`) VALUES
(57, 569, 21, '2025-10-22 11:16:18'),
(58, 618, 21, '2025-10-22 11:16:18'),
(59, 621, 21, '2025-10-22 11:16:18'),
(60, 617, 21, '2025-10-22 11:16:18'),
(61, 611, 21, '2025-10-22 11:16:18'),
(62, 564, 21, '2025-10-22 11:16:18'),
(63, 566, 21, '2025-10-22 11:16:18'),
(64, 580, 21, '2025-10-22 11:16:18'),
(65, 626, 21, '2025-10-22 11:16:18'),
(66, 609, 21, '2025-10-22 11:16:18'),
(67, 635, 21, '2025-10-22 11:19:19'),
(68, 585, 21, '2025-10-22 11:19:19'),
(69, 638, 21, '2025-10-22 11:19:19'),
(70, 565, 21, '2025-10-22 11:19:19'),
(71, 575, 21, '2025-10-22 11:19:19'),
(72, 606, 21, '2025-10-22 11:19:19'),
(73, 629, 21, '2025-10-22 11:19:19'),
(74, 594, 21, '2025-10-22 11:19:19'),
(75, 623, 21, '2025-10-22 11:19:19'),
(76, 634, 21, '2025-10-22 11:19:19'),
(77, 617, 16, '2025-10-22 14:49:22'),
(78, 580, 16, '2025-10-22 14:49:22'),
(79, 609, 16, '2025-10-22 14:49:22'),
(80, 584, 16, '2025-10-22 14:49:22'),
(81, 569, 16, '2025-10-22 14:49:22'),
(82, 629, 16, '2025-10-22 14:49:22'),
(83, 565, 16, '2025-10-22 14:49:22'),
(84, 622, 16, '2025-10-22 14:49:22'),
(85, 606, 16, '2025-10-22 14:49:22'),
(86, 581, 16, '2025-10-22 14:49:22');

-- --------------------------------------------------------

--
-- Structure de la table `user_answers`
--

CREATE TABLE `user_answers` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `game_status`
--
ALTER TABLE `game_status`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `participants`
--
ALTER TABLE `participants`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `pseudo` (`pseudo`);

--
-- Index pour la table `player_questions`
--
ALTER TABLE `player_questions`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `scores`
--
ALTER TABLE `scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`);

--
-- Index pour la table `used_questions`
--
ALTER TABLE `used_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `user_answers`
--
ALTER TABLE `user_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_answer` (`player_id`,`question_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `participants`
--
ALTER TABLE `participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT pour la table `players`
--
ALTER TABLE `players`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `player_questions`
--
ALTER TABLE `player_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=641;

--
-- AUTO_INCREMENT pour la table `scores`
--
ALTER TABLE `scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `used_questions`
--
ALTER TABLE `used_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT pour la table `user_answers`
--
ALTER TABLE `user_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `scores`
--
ALTER TABLE `scores`
  ADD CONSTRAINT `scores_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `used_questions`
--
ALTER TABLE `used_questions`
  ADD CONSTRAINT `used_questions_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  ADD CONSTRAINT `used_questions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `participants` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
